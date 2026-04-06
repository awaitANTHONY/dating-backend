<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Shared subscription tier detection logic.
 * Reuses the same pattern as DirectConnectController::getSubscriptionTier().
 */
class SubscriptionTierService
{
    /**
     * Determine a user's subscription tier from local DB records.
     * Returns 'vip', 'gold', 'premium', or null.
     */
    public static function getTier(User $user): ?string
    {
        // 1. Check VIP first (separate from subscription)
        if ($user->isVipActive()) {
            return 'vip';
        }

        // 2. Check subscription via subscription_id + expired_at
        if ($user->subscription_id && $user->subscription_id != 0) {
            // Only reject if expired_at is set AND in the past
            if (!$user->expired_at || !\Carbon\Carbon::parse($user->expired_at)->isPast()) {
                $subName   = strtolower($user->subscription->name ?? '');
                $productId = strtolower($user->subscription->product_id ?? '');

                if (str_contains($subName, 'gold') || str_contains($productId, 'gold')) {
                    return 'gold';
                }
                if (str_contains($subName, 'premium') || str_contains($productId, 'premium')) {
                    return 'premium';
                }

                // Any active subscription is at least premium
                return 'premium';
            }
        }

        return null;
    }

    /**
     * Determine tier with RevenueCat fallback (for HTTP contexts only).
     * Requires RevenueCatService to be available.
     */
    public static function getTierWithRevenueCat(User $user): ?string
    {
        $tier = self::getTier($user);
        if ($tier) {
            return $tier;
        }

        // Fallback: check RevenueCat entitlements directly
        try {
            $revenueCat = new \App\Services\RevenueCatService();

            $goldEnt = $revenueCat->getActiveEntitlement((string) $user->id, 'gold_access');
            if ($goldEnt) return 'gold';

            $premiumEnt = $revenueCat->getActiveEntitlement((string) $user->id, 'premium_access');
            if ($premiumEnt) return 'premium';
        } catch (\Exception $e) {
            Log::warning('SubscriptionTierService: RevenueCat check failed', [
                'user'  => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
