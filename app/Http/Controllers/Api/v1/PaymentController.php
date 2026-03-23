<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\RevenueCatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserInformation;
use App\Models\BoostPackage;
use App\Models\ProfileBoost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Utils\Overrider;

class PaymentController extends Controller
{
    private RevenueCatService $revenueCat;

    public function __construct()
    {
        $this->revenueCat = new RevenueCatService();
    }

    /**
     * Get the RevenueCat user ID for server-side verification.
     * Uses the user's backend ID as the RevenueCat app_user_id.
     */
    private function getRevenueCatUserId(User $user): string
    {
        return (string) $user->id;
    }

    /**
     * Check if a transaction has already been processed (prevent replay).
     */
    private function isTransactionProcessed(string $transactionKey): bool
    {
        return Payment::where('transaction_id', $transactionKey)->exists();
    }

    // ─── Subscribe (RevenueCat Verified) ─────────────────────────────────────

    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $rcUserId = $this->getRevenueCatUserId($user);

        // Clear cache to get fresh data after purchase
        $this->revenueCat->clearCache($rcUserId);

        // Verify with RevenueCat — check both premium and gold entitlements
        $entitlement = $this->revenueCat->getActiveEntitlement($rcUserId, 'premium_access')
            ?? $this->revenueCat->getActiveEntitlement($rcUserId, 'gold_access');

        if (!$entitlement) {
            Log::warning('PAYMENT_REJECTED: No active subscription entitlement', [
                'user_id' => $user->id,
                'product_id' => $request->product_id,
            ]);
            return response()->json([
                'result' => false,
                'message' => 'Purchase could not be verified. Please try again or contact support.',
            ], 422);
        }

        // Build unique transaction key from RevenueCat data
        $transactionKey = $this->revenueCat->buildTransactionKey($entitlement);

        // Prevent duplicate processing
        if ($this->isTransactionProcessed($transactionKey)) {
            return response()->json([
                'result' => true,
                'message' => 'Subscription already active.',
                'data' => $user->makeHidden(['id', 'user_type', 'created_at', 'updated_at', 'apps', 'app_id', 'email_verified_at', 'status', 'subscription', 'verification', 'forget_code']),
            ]);
        }

        // Find subscription by product_id
        $subscription = Subscription::where('product_id', $request->product_id)->first();

        if (!$subscription) {
            // Try matching by RevenueCat's product_identifier
            $subscription = Subscription::where('product_id', $entitlement['product_identifier'] ?? '')->first();
        }

        if (!$subscription) {
            return response()->json(['result' => false, 'message' => 'Product not found.']);
        }

        // Use RevenueCat's expires_date as the authoritative expiration
        $expiresDate = $entitlement['expires_date'] ?? null;
        if ($expiresDate) {
            $date = Carbon::parse($expiresDate);
        } else {
            // Fallback: compute from subscription model duration
            if ($user->expired_at != null && $user->expired_at > now()) {
                $expired_at = $user->expired_at;
                $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", Carbon::parse($expired_at)->timestamp));
            } else {
                $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", now()->timestamp));
            }
        }

        $user->expired_at = $date;
        $user->subscription_id = $subscription->id;
        $user->save();

        // Create verified payment record
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = $subscription->name;
        $payment->date = now();
        $payment->amount = $request->amount ?? '0.00';
        $payment->platform = $request->platform;
        $payment->transaction_id = $transactionKey;
        $payment->original_transaction_id = $transactionKey;
        $payment->payment_type = 'subscription';
        $payment->save();

        Cache::forget("payments_" . $user->id);

        $user->subscription_name = $user->subscription->name;

        // Send Mail
        $this->send_mail($user, $payment);

        return response()->json([
            'status' => true,
            'data' => $user->makeHidden(['id', 'user_type', 'created_at', 'updated_at', 'apps', 'app_id', 'email_verified_at', 'status', 'subscription', 'verification', 'forget_code']),
        ]);
    }

    // ─── Verification Payment (RevenueCat Verified) ──────────────────────────

    public function purchase_verification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        // Check if user already verified
        $userInfo = $user->user_information;
        if ($userInfo && $userInfo->is_verified) {
            return response()->json([
                'status' => false,
                'message' => 'User is already verified.',
            ]);
        }

        $rcUserId = $this->getRevenueCatUserId($user);
        $this->revenueCat->clearCache($rcUserId);

        // Verify the verification product purchase exists in RevenueCat
        $purchase = $this->revenueCat->findNonSubscriptionPurchase($rcUserId, $request->product_id);

        if (!$purchase) {
            Log::warning('PAYMENT_REJECTED: No verification purchase found', [
                'user_id' => $user->id,
                'product_id' => $request->product_id,
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Purchase could not be verified. Please try again or contact support.',
            ], 422);
        }

        // Build transaction key and prevent duplicate processing
        $transactionKey = ($purchase['id'] ?? '') . '_' . ($purchase['purchase_date'] ?? '');
        if ($this->isTransactionProcessed($transactionKey)) {
            return response()->json([
                'status' => true,
                'message' => 'Verification already applied.',
            ]);
        }

        // Create verified payment record
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = 'Account Verification';
        $payment->date = now();
        $payment->amount = $request->amount ?? '0.00';
        $payment->platform = $request->platform;
        $payment->transaction_id = $transactionKey;
        $payment->original_transaction_id = $transactionKey;
        $payment->payment_type = 'verification';
        $payment->save();

        // Update user verification status
        if (!$userInfo) {
            $userInfo = new UserInformation();
            $userInfo->user_id = $user->id;
        }
        $userInfo->is_verified = true;
        $userInfo->save();

        // Clear caches
        Cache::forget("payments_" . $user->id);
        $this->clearProfileCompletionCache($user->id);

        return response()->json([
            'status' => true,
            'message' => 'Verification successful!',
        ]);
    }

    // ─── Payment History ─────────────────────────────────────────────────────

    public function payments(Request $request)
    {
        $user = $request->user();
        $payments = Cache::remember("payments_{$user->id}", 3600, function () use ($user) {
            return Payment::where('user_id', $user->id)
                ->orderBy('id', 'DESC')
                ->get();
        });

        return response()->json(['status' => true, 'data' => $payments]);
    }

    // ─── Subscription Restore (RevenueCat Verified) ──────────────────────────

    public function subscription_restore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $rcUserId = $this->getRevenueCatUserId($user);
        $this->revenueCat->clearCache($rcUserId);

        // Verify with RevenueCat that the subscription is still active
        $entitlement = $this->revenueCat->getActiveEntitlement($rcUserId, 'premium_access')
            ?? $this->revenueCat->getActiveEntitlement($rcUserId, 'gold_access');

        if (!$entitlement) {
            return response()->json([
                'result' => false,
                'message' => 'No active subscription found to restore.',
            ]);
        }

        $subscription = Subscription::where('product_id', $request->product_id)->first()
            ?? Subscription::where('product_id', $entitlement['product_identifier'] ?? '')->first();

        if (!$subscription) {
            return response()->json(['result' => false, 'message' => 'Product not found.']);
        }

        // Use RevenueCat's expires_date
        $expiresDate = $entitlement['expires_date'] ?? null;
        if ($expiresDate) {
            $user->expired_at = Carbon::parse($expiresDate);
        } else {
            if ($user->expired_at != null && $user->expired_at > now()) {
                $expired_at = $user->expired_at;
                $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", Carbon::parse($expired_at)->timestamp));
            } else {
                $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", now()->timestamp));
            }
            $user->expired_at = $date;
        }

        $user->subscription_id = $subscription->id;
        $user->save();

        $user->subscription_name = $user->subscription->name;

        return response()->json([
            'status' => true,
            'user' => $user->makeHidden(['id', 'user_type', 'created_at', 'updated_at', 'apps', 'app_id', 'email_verified_at', 'status', 'subscription']),
        ]);
    }

    // ─── Purchase Profile Boost (RevenueCat Verified) ────────────────────────

    public function purchase_boost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $rcUserId = $this->getRevenueCatUserId($user);
        $this->revenueCat->clearCache($rcUserId);

        // Verify the boost purchase exists in RevenueCat
        $purchase = $this->revenueCat->findNonSubscriptionPurchase($rcUserId, $request->product_id);

        if (!$purchase) {
            Log::warning('PAYMENT_REJECTED: No boost purchase found in RevenueCat', [
                'user_id' => $user->id,
                'product_id' => $request->product_id,
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Purchase could not be verified. Please try again or contact support.',
            ], 422);
        }

        // Build transaction key and prevent duplicate processing
        $transactionKey = ($purchase['id'] ?? '') . '_' . ($purchase['purchase_date'] ?? '');
        if ($this->isTransactionProcessed($transactionKey)) {
            return response()->json([
                'status' => true,
                'message' => 'Boost already activated.',
            ]);
        }

        // Find boost package by product_id
        $boostPackage = BoostPackage::findByProductId($request->product_id);

        if (!$boostPackage) {
            return response()->json(['status' => false, 'message' => 'Invalid boost package.']);
        }

        // Verify platform compatibility
        if ($boostPackage->platform !== 'both' && $boostPackage->platform !== $request->platform) {
            return response()->json(['status' => false, 'message' => 'Package not available for this platform.']);
        }

        // Create boost record and auto-activate
        $boost = new ProfileBoost();
        $boost->user_id = $user->id;
        $boost->boost_package_id = $boostPackage->id;
        $boost->status = 'purchased';
        $boost->save();

        // Auto-activate the boost immediately
        $boost->activate();

        // Clear recommendations cache to prioritize boosted profile
        Cache::flush();

        // Create verified payment record
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = $boostPackage->name . ' - Profile Boost';
        $payment->date = now();
        $payment->amount = $request->amount ?? '0.00';
        $payment->platform = $request->platform;
        $payment->transaction_id = $transactionKey;
        $payment->original_transaction_id = $transactionKey;
        $payment->payment_type = 'boost';
        $payment->save();

        Cache::forget("payments_" . $user->id);

        $boostDuration = $boostPackage->boost_duration ?? 30;

        return response()->json([
            'status' => true,
            'message' => "Boost purchased and activated! You are top profile for {$boostDuration} minutes.",
            'data' => [
                'package_name' => $boostPackage->name,
                'boost_count' => $boostPackage->boost_count,
                'boost_duration' => $boostPackage->boost_duration,
                'boost_active' => true,
                'activated_at' => $boost->activated_at->toISOString(),
                'expires_at' => $boost->expires_at->toISOString(),
                'remaining_minutes' => $boostDuration,
                'available_boosts' => $this->getAvailableBoosts($user->id),
            ],
        ]);
    }

    // ─── Purchase VIP (RevenueCat Verified — no client-controlled duration) ──

    public function purchase_vip(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $rcUserId = $this->getRevenueCatUserId($user);
        $this->revenueCat->clearCache($rcUserId);

        // Verify VIP entitlement with RevenueCat
        $entitlement = $this->revenueCat->getActiveEntitlement($rcUserId, 'vip_access');

        if (!$entitlement) {
            Log::warning('PAYMENT_REJECTED: No active VIP entitlement', [
                'user_id' => $user->id,
            ]);
            return response()->json([
                'status' => false,
                'message' => 'VIP purchase could not be verified. Please try again or contact support.',
            ], 422);
        }

        // Build transaction key and prevent duplicate processing
        $transactionKey = $this->revenueCat->buildTransactionKey($entitlement);
        if ($this->isTransactionProcessed($transactionKey)) {
            return response()->json([
                'status' => true,
                'message' => 'VIP already active.',
                'data' => [
                    'is_vip' => true,
                    'vip_expire' => $user->vip_expire ? Carbon::parse($user->vip_expire)->toISOString() : null,
                ],
            ]);
        }

        // Use RevenueCat's expires_date — NOT client-provided duration
        $expiresDate = $entitlement['expires_date'] ?? null;
        if ($expiresDate) {
            $vipExpireDate = Carbon::parse($expiresDate);
        } else {
            // Fallback: 7 days if no expires_date (lifetime entitlement)
            $vipExpireDate = now()->addDays(7);
        }

        // Update user VIP status
        $user->is_vip = true;
        $user->vip_expire = $vipExpireDate;
        $user->save();

        // Create verified payment record
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = 'VIP Membership';
        $payment->date = now();
        $payment->amount = $request->amount ?? '0.00';
        $payment->platform = $request->platform;
        $payment->transaction_id = $transactionKey;
        $payment->original_transaction_id = $transactionKey;
        $payment->payment_type = 'vip';
        $payment->save();

        Cache::forget("payments_" . $user->id);

        $vipDuration = now()->diffInDays($vipExpireDate);

        return response()->json([
            'status' => true,
            'message' => "VIP membership activated for {$vipDuration} days!",
            'data' => [
                'is_vip' => true,
                'vip_expire' => $user->vip_expire->toISOString(),
                'activated_at' => now()->toISOString(),
                'duration_days' => $vipDuration,
                'remaining_days' => $vipDuration,
            ],
        ]);
    }

    // ─── Sync All Entitlements from RevenueCat ───────────────────────────────

    public function syncEntitlements(Request $request)
    {
        $user = $request->user();
        $rcUserId = $this->getRevenueCatUserId($user);
        $this->revenueCat->clearCache($rcUserId);

        $activeEntitlements = $this->revenueCat->getActiveEntitlements($rcUserId);

        $changes = [];

        // ── Sync Premium/Gold subscription ──
        $subEntitlement = $activeEntitlements['premium_access'] ?? $activeEntitlements['gold_access'] ?? null;
        $subEntitlementName = isset($activeEntitlements['premium_access']) ? 'premium' : (isset($activeEntitlements['gold_access']) ? 'gold' : null);

        if ($subEntitlement) {
            $productId = $subEntitlement['product_identifier'] ?? '';
            $subscription = Subscription::where('product_id', $productId)->first();

            if ($subscription) {
                $expiresDate = $subEntitlement['expires_date'] ?? null;
                $user->subscription_id = $subscription->id;
                $user->expired_at = $expiresDate ? Carbon::parse($expiresDate) : $user->expired_at;
                $changes['subscription'] = $subEntitlementName;
                $changes['subscription_expires'] = $expiresDate;

                // Record payment if not already recorded
                $txKey = $this->revenueCat->buildTransactionKey($subEntitlement);
                if (!$this->isTransactionProcessed($txKey)) {
                    $payment = new Payment();
                    $payment->user_id = $user->id;
                    $payment->title = $subscription->name;
                    $payment->date = now();
                    $payment->amount = '0.00';
                    $payment->platform = $request->input('platform', 'unknown');
                    $payment->transaction_id = $txKey;
                    $payment->original_transaction_id = $txKey;
                    $payment->payment_type = 'subscription';
                    $payment->save();
                }
            }
        } else {
            // No active subscription — revoke if expired
            if ($user->expired_at && Carbon::parse($user->expired_at)->isPast()) {
                $user->subscription_id = null;
                $user->expired_at = null;
                $changes['subscription'] = 'expired';
            }
        }

        // ── Sync VIP ──
        $vipEntitlement = $activeEntitlements['vip_access'] ?? null;

        if ($vipEntitlement) {
            $expiresDate = $vipEntitlement['expires_date'] ?? null;
            $user->is_vip = true;
            $user->vip_expire = $expiresDate ? Carbon::parse($expiresDate) : $user->vip_expire;
            $changes['vip'] = true;
            $changes['vip_expires'] = $expiresDate;

            // Record payment if not already recorded
            $txKey = $this->revenueCat->buildTransactionKey($vipEntitlement);
            if (!$this->isTransactionProcessed($txKey)) {
                $payment = new Payment();
                $payment->user_id = $user->id;
                $payment->title = 'VIP Membership';
                $payment->date = now();
                $payment->amount = '0.00';
                $payment->platform = $request->input('platform', 'unknown');
                $payment->transaction_id = $txKey;
                $payment->original_transaction_id = $txKey;
                $payment->payment_type = 'vip';
                $payment->save();
            }
        } else {
            // VIP expired — revoke
            if ($user->is_vip && (!$user->vip_expire || Carbon::parse($user->vip_expire)->isPast())) {
                $user->is_vip = false;
                $changes['vip'] = false;
            }
        }

        $user->save();
        Cache::forget("payments_" . $user->id);

        return response()->json([
            'status' => true,
            'message' => 'Entitlements synced.',
            'data' => [
                'has_premium' => isset($activeEntitlements['premium_access']),
                'has_gold' => isset($activeEntitlements['gold_access']),
                'is_vip' => $user->is_vip,
                'subscription_expires' => $user->expired_at,
                'vip_expires' => $user->vip_expire,
                'changes' => $changes,
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function clearProfileCompletionCache($userId)
    {
        Cache::forget("profile_completion_user_{$userId}");
    }

    private function getAvailableBoosts($userId)
    {
        return ProfileBoost::where('user_id', $userId)
            ->where('status', 'purchased')
            ->count();
    }

    public function send_mail($user, $payment)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        \App\Utils\Overrider::load('Settings');

        try {
            Mail::to($user->email)->send(new InvoiceMail($user, $payment));
            return json_encode(array('result' => true, 'message' => _lang('Your Message send sucessfully.')));
        } catch (\Exception $e) {
            return json_encode(array('result' => false, 'message' => _lang('Error Occured, Please try again !')));
        }
    }
}
