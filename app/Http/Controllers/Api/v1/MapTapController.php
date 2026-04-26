<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MapTapController extends Controller
{
    private const FREE_DAILY_TAP_LIMIT = 10;

    /**
     * Log a map profile tap and enforce the daily free-tier limit.
     *
     * Premium/VIP users are always allowed (unlimited taps).
     * Free users get FREE_DAILY_TAP_LIMIT taps per calendar day.
     *
     * Returns:
     *   { allowed: true,  remaining: N }   — tap was counted, proceed to show profile
     *   { allowed: false, remaining: 0 }   — limit hit, show upgrade prompt instead
     */
    public function tap(Request $request): JsonResponse
    {
        $user = $request->user();

        // Premium / Gold / VIP users have unlimited map taps
        if ($user->isVipActive() || $this->hasActiveSubscription($user)) {
            return response()->json([
                'status'    => true,
                'allowed'   => true,
                'remaining' => 9999,
            ]);
        }

        $today   = now()->toDateString();               // e.g. "2026-04-26"
        $cacheKey = "map_taps:{$user->id}:{$today}";

        // Atomic increment — initialise to 0 if key does not exist yet
        $current = (int) Cache::get($cacheKey, 0);

        if ($current >= self::FREE_DAILY_TAP_LIMIT) {
            return response()->json([
                'status'    => false,
                'allowed'   => false,
                'remaining' => 0,
                'message'   => 'Daily tap limit reached. Upgrade to Premium for unlimited taps.',
            ], 429);
        }

        // Persist with TTL until midnight so counts reset at the start of each day
        $ttl = now()->endOfDay()->diffInSeconds(now());
        Cache::put($cacheKey, $current + 1, $ttl);

        return response()->json([
            'status'    => true,
            'allowed'   => true,
            'remaining' => self::FREE_DAILY_TAP_LIMIT - $current - 1,
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function hasActiveSubscription($user): bool
    {
        try {
            // Reuse the same subscription check already used throughout the app
            return (bool) $user->hasActivePremium();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
