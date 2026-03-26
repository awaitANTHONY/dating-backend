<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RevenueCatService
{
    private string $secretApiKey;
    private string $baseUrl = 'https://api.revenuecat.com/v1';

    public function __construct()
    {
        $this->secretApiKey = config('services.revenuecat.secret_key') ?? '';
    }

    /**
     * Fetch the full subscriber record from RevenueCat.
     * Caches for 60 seconds to avoid rate limiting on rapid requests.
     */
    public function getSubscriber(string $appUserId): ?array
    {
        if (empty($this->secretApiKey)) {
            Log::error('REVENUECAT: Secret API key not configured');
            return null;
        }

        $cacheKey = "revenuecat_subscriber_{$appUserId}";

        return Cache::remember($cacheKey, 60, function () use ($appUserId) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->secretApiKey}",
                    'Content-Type' => 'application/json',
                ])->get("{$this->baseUrl}/subscribers/{$appUserId}");

                if ($response->successful()) {
                    return $response->json('subscriber');
                }

                Log::warning('REVENUECAT: Failed to fetch subscriber', [
                    'user_id' => $appUserId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('REVENUECAT: API error', [
                    'user_id' => $appUserId,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Check if a specific entitlement is active for a user.
     * Returns the entitlement data array if active, null otherwise.
     */
    public function getActiveEntitlement(string $appUserId, string $entitlementId): ?array
    {
        $subscriber = $this->getSubscriber($appUserId);
        if (!$subscriber) return null;

        $entitlements = $subscriber['entitlements'] ?? [];
        $entitlement = $entitlements[$entitlementId] ?? null;

        if (!$entitlement) return null;

        // Check if the entitlement has expired
        $expiresDate = $entitlement['expires_date'] ?? null;
        if ($expiresDate) {
            $expires = Carbon::parse($expiresDate);
            if ($expires->isPast()) return null;
        }

        return $entitlement;
    }

    /**
     * Find a non-subscription (one-time) purchase for a specific product.
     * Returns the most recent matching transaction or null.
     */
    public function findNonSubscriptionPurchase(string $appUserId, string $productId): ?array
    {
        $subscriber = $this->getSubscriber($appUserId);
        if (!$subscriber) return null;

        $nonSubscriptions = $subscriber['non_subscriptions'] ?? [];
        $purchases = $nonSubscriptions[$productId] ?? [];

        if (empty($purchases)) return null;

        // Return the most recent purchase (last in array)
        return end($purchases);
    }

    /**
     * Get all active entitlements for a user.
     * Returns an associative array of entitlement_id => entitlement_data.
     */
    public function getActiveEntitlements(string $appUserId): array
    {
        $subscriber = $this->getSubscriber($appUserId);
        if (!$subscriber) return [];

        $entitlements = $subscriber['entitlements'] ?? [];
        $active = [];

        foreach ($entitlements as $id => $entitlement) {
            $expiresDate = $entitlement['expires_date'] ?? null;
            if ($expiresDate) {
                $expires = Carbon::parse($expiresDate);
                if ($expires->isPast()) continue;
            }
            $active[$id] = $entitlement;
        }

        return $active;
    }

    /**
     * Build a unique transaction key from RevenueCat entitlement data.
     * Used to prevent duplicate payment processing.
     */
    public function buildTransactionKey(array $entitlement): string
    {
        $productId = $entitlement['product_identifier'] ?? 'unknown';
        $purchaseDate = $entitlement['purchase_date'] ?? now()->toIso8601String();
        return "{$productId}_{$purchaseDate}";
    }

    /**
     * Clear the cached subscriber data (e.g. after a purchase).
     */
    public function clearCache(string $appUserId): void
    {
        Cache::forget("revenuecat_subscriber_{$appUserId}");
    }
}
