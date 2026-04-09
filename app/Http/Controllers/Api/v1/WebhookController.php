<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\BoostPackage;
use App\Models\Payment;
use App\Models\ProfileBoost;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming RevenueCat webhook events.
     *
     * RevenueCat sends POST requests here when purchases, renewals,
     * cancellations, etc. happen — even if the app never calls our API.
     *
     * @see https://www.revenuecat.com/docs/integrations/webhooks
     */
    public function revenuecat(Request $request)
    {
        // Verify webhook authorization
        $authHeader = $request->header('Authorization');
        $expectedKey = config('services.revenuecat.webhook_secret');

        if (!$expectedKey || $authHeader !== "Bearer {$expectedKey}") {
            Log::warning('WEBHOOK: Unauthorized RevenueCat webhook attempt', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => false], 401);
        }

        $event = $request->input('event');
        if (!$event) {
            return response()->json(['status' => false, 'message' => 'No event data'], 400);
        }

        $eventType = $event['type'] ?? null;
        $appUserId = $event['app_user_id'] ?? null;
        $productId = $event['product_id'] ?? null;
        $store = $event['store'] ?? null;
        $environment = $event['environment'] ?? 'PRODUCTION';

        Log::info('WEBHOOK: RevenueCat event received', [
            'type' => $eventType,
            'app_user_id' => $appUserId,
            'product_id' => $productId,
            'store' => $store,
            'environment' => $environment,
        ]);

        // Skip sandbox events in production
        if ($environment === 'SANDBOX' && app()->environment('production')) {
            return response()->json(['status' => true, 'message' => 'Sandbox event ignored']);
        }

        // The app_user_id is the backend user ID
        if (!$appUserId || !is_numeric($appUserId)) {
            Log::warning('WEBHOOK: Invalid app_user_id', ['app_user_id' => $appUserId]);
            return response()->json(['status' => true, 'message' => 'Skipped — no valid user']);
        }

        $user = User::find($appUserId);
        if (!$user) {
            Log::warning('WEBHOOK: User not found', ['app_user_id' => $appUserId]);
            return response()->json(['status' => true, 'message' => 'User not found']);
        }

        $platform = match ($store) {
            'PLAY_STORE' => 'android',
            'APP_STORE'  => 'ios',
            default      => 'unknown',
        };

        switch ($eventType) {
            // ─── One-time purchases (boosts, verification, coins) ────────
            case 'NON_RENEWING_PURCHASE':
                $this->handleNonRenewingPurchase($user, $event, $platform);
                break;

            // ─── Subscription events ─────────────────────────────────────
            case 'INITIAL_PURCHASE':
            case 'RENEWAL':
            case 'PRODUCT_CHANGE':
                $this->handleSubscriptionActive($user, $event, $platform);
                break;

            case 'EXPIRATION':
            case 'CANCELLATION':
                $this->handleSubscriptionExpired($user, $event);
                break;

            // ─── Billing issues ──────────────────────────────────────────
            case 'BILLING_ISSUE':
                Log::warning('WEBHOOK: Billing issue', [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                ]);
                break;

            default:
                Log::info('WEBHOOK: Unhandled event type', ['type' => $eventType]);
        }

        // Always return 200 so RevenueCat doesn't retry
        return response()->json(['status' => true]);
    }

    // ─── Handle Non-Renewing (One-Time) Purchases ────────────────────────────

    private function handleNonRenewingPurchase(User $user, array $event, string $platform): void
    {
        $productId = $event['product_id'] ?? null;
        $transactionId = $event['transaction_id'] ?? $event['id'] ?? null;
        $purchaseDate = $event['purchased_at_ms'] ?? $event['event_timestamp_ms'] ?? null;

        if (!$productId) return;

        // Build unique transaction key to prevent duplicates
        $transactionKey = $transactionId . '_' . ($purchaseDate ?? now()->timestamp);

        if (Payment::where('transaction_id', $transactionKey)->exists()) {
            Log::info('WEBHOOK: Transaction already processed', [
                'user_id' => $user->id,
                'transaction_key' => $transactionKey,
            ]);
            return;
        }

        // Check if this is a boost product
        $boostPackage = BoostPackage::findByProductId($productId);

        if ($boostPackage) {
            $this->activateBoost($user, $boostPackage, $transactionKey, $platform);
            return;
        }

        Log::info('WEBHOOK: Non-boost one-time purchase', [
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);
    }

    private function activateBoost(User $user, BoostPackage $boostPackage, string $transactionKey, string $platform): void
    {
        $boost = new ProfileBoost();
        $boost->user_id = $user->id;
        $boost->boost_package_id = $boostPackage->id;
        $boost->status = 'purchased';
        $boost->save();

        $boost->activate();

        // Clear recommendations cache so boosted profile appears immediately
        Cache::flush();

        // Record payment
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = $boostPackage->name . ' - Profile Boost (Webhook)';
        $payment->date = now();
        $payment->amount = '0.00';
        $payment->platform = $platform;
        $payment->transaction_id = $transactionKey;
        $payment->original_transaction_id = $transactionKey;
        $payment->payment_type = 'boost';
        $payment->save();

        Cache::forget("payments_" . $user->id);

        Log::info('WEBHOOK: Boost activated', [
            'user_id' => $user->id,
            'package' => $boostPackage->name,
            'duration' => $boostPackage->boost_duration . ' min',
            'expires_at' => $boost->expires_at,
        ]);
    }

    // ─── Handle Active Subscriptions (Initial + Renewal) ─────────────────────

    private function handleSubscriptionActive(User $user, array $event, string $platform): void
    {
        $productId = $event['product_id'] ?? null;
        $expirationAtMs = $event['expiration_at_ms'] ?? null;
        $transactionId = $event['transaction_id'] ?? $event['id'] ?? null;
        $purchaseDate = $event['purchased_at_ms'] ?? $event['event_timestamp_ms'] ?? null;
        $entitlementIds = $event['entitlement_ids'] ?? [];

        if (!$productId) return;

        $transactionKey = $transactionId . '_' . ($purchaseDate ?? now()->timestamp);

        // Check for VIP entitlement
        if (in_array('vip_access', $entitlementIds)) {
            $this->activateVip($user, $expirationAtMs, $transactionKey, $platform);
            return;
        }

        // Handle subscription (premium/gold)
        $subscription = Subscription::where('product_id', $productId)->first();

        if (!$subscription) {
            Log::warning('WEBHOOK: Subscription product not found', [
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
            return;
        }

        // Set expiry from RevenueCat's authoritative timestamp
        if ($expirationAtMs) {
            $user->expired_at = Carbon::createFromTimestampMs($expirationAtMs);
        }
        $user->subscription_id = $subscription->id;
        $user->save();

        // Record payment if not duplicate
        if (!Payment::where('transaction_id', $transactionKey)->exists()) {
            $payment = new Payment();
            $payment->user_id = $user->id;
            $payment->title = $subscription->name . ' (Webhook)';
            $payment->date = now();
            $payment->amount = '0.00';
            $payment->platform = $platform;
            $payment->transaction_id = $transactionKey;
            $payment->original_transaction_id = $transactionKey;
            $payment->payment_type = 'subscription';
            $payment->save();

            Cache::forget("payments_" . $user->id);
        }

        Log::info('WEBHOOK: Subscription activated/renewed', [
            'user_id' => $user->id,
            'type' => $event['type'],
            'subscription' => $subscription->name,
            'expires' => $user->expired_at,
        ]);
    }

    private function activateVip(User $user, ?int $expirationAtMs, string $transactionKey, string $platform): void
    {
        $vipExpire = $expirationAtMs
            ? Carbon::createFromTimestampMs($expirationAtMs)
            : now()->addDays(7);

        $user->is_vip = true;
        $user->vip_expire = $vipExpire;
        $user->save();

        if (!Payment::where('transaction_id', $transactionKey)->exists()) {
            $payment = new Payment();
            $payment->user_id = $user->id;
            $payment->title = 'VIP Membership (Webhook)';
            $payment->date = now();
            $payment->amount = '0.00';
            $payment->platform = $platform;
            $payment->transaction_id = $transactionKey;
            $payment->original_transaction_id = $transactionKey;
            $payment->payment_type = 'vip';
            $payment->save();

            Cache::forget("payments_" . $user->id);
        }

        Log::info('WEBHOOK: VIP activated', [
            'user_id' => $user->id,
            'expires' => $vipExpire,
        ]);
    }

    // ─── Handle Subscription Expiration / Cancellation ───────────────────────

    private function handleSubscriptionExpired(User $user, array $event): void
    {
        $entitlementIds = $event['entitlement_ids'] ?? [];

        if (in_array('vip_access', $entitlementIds)) {
            $user->is_vip = false;
            $user->save();

            Log::info('WEBHOOK: VIP expired/cancelled', ['user_id' => $user->id]);
            return;
        }

        // Subscription expired — clear subscription (keep subscription_id for history)
        $user->expired_at = now();
        $user->save();

        Log::info('WEBHOOK: Subscription expired/cancelled', [
            'user_id' => $user->id,
            'type' => $event['type'],
        ]);
    }
}
