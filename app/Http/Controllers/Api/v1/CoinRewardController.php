<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\CoinReward;
use App\Models\CoinTransaction;
use App\Services\SubscriptionTierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;

class CoinRewardController extends Controller
{
    /**
     * Reward type → settings key mapping.
     */
    private function rewardAmount(string $type): int
    {
        return match ($type) {
            'daily_login'       => (int) get_option('coin_daily_login', '1'),
            'follow_instagram',
            'follow_twitter',
            'follow_tiktok'     => (int) get_option('coin_follow_social', '5'),
            'referral'          => (int) get_option('coin_referral', '10'),
            'complete_profile'  => (int) get_option('coin_complete_profile', '5'),
            default             => 0,
        };
    }

    /**
     * GET /api/v1/coin-rewards/status
     * Returns which rewards are available/claimed for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        $allTypes = array_merge(
            CoinReward::dailyTypes(),
            CoinReward::oneTimeTypes(),
            CoinReward::perEventTypes(),
        );

        $rewards = [];
        foreach ($allTypes as $type) {
            $amount = $this->rewardAmount($type);

            if (in_array($type, CoinReward::dailyTypes())) {
                $claimed = CoinReward::where('user_id', $user->id)
                    ->claimedToday($type)
                    ->exists();
            } elseif (in_array($type, CoinReward::oneTimeTypes())) {
                $claimed = CoinReward::where('user_id', $user->id)
                    ->alreadyClaimed($type)
                    ->exists();
            } else {
                // Per-event types (referral) — show total count
                $claimed = false;
            }

            $rewards[$type] = [
                'coins'   => $amount,
                'claimed' => $claimed,
            ];
        }

        // Subscriber daily coins info — use shared tier service with RevenueCat fallback
        $subscriberCoins = 0;
        $subscriberClaimed = false;
        $tier = SubscriptionTierService::getTierWithRevenueCat($user);

        if ($tier) {
            $subscriberCoins = match ($tier) {
                'premium' => (int) get_option('coin_daily_premium', '2'),
                'gold'    => (int) get_option('coin_daily_gold', '5'),
                'vip'     => (int) get_option('coin_daily_vip', '10'),
                default   => 0,
            };
            $subscriberClaimed = CoinTransaction::where('user_id', $user->id)
                ->where('type', 'daily_grant')
                ->whereDate('created_at', $today)
                ->exists();
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'rewards'            => $rewards,
                'coin_balance'       => $user->coin_balance ?? 0,
                'subscriber_daily'   => [
                    'coins'   => $subscriberCoins,
                    'claimed' => $subscriberClaimed,
                    'tier'    => $tier,
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/coin-rewards/claim
     * Claim a specific reward.
     */
    public function claim(Request $request): JsonResponse
    {
        $request->validate([
            'reward_type' => 'required|string|in:daily_login,follow_instagram,follow_twitter,follow_tiktok,referral,complete_profile',
            'reference'   => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $type = $request->reward_type;
        $amount = $this->rewardAmount($type);

        if ($amount <= 0) {
            return response()->json([
                'status'  => false,
                'message' => 'This reward is currently disabled.',
            ], 400);
        }

        // ── Check eligibility ──

        if (in_array($type, CoinReward::dailyTypes())) {
            $alreadyClaimed = CoinReward::where('user_id', $user->id)
                ->claimedToday($type)
                ->exists();

            if ($alreadyClaimed) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have already claimed this reward today.',
                ], 409);
            }
        } elseif (in_array($type, CoinReward::oneTimeTypes())) {
            if ($type === 'complete_profile') {
                // Verify profile is actually complete
                $completion = $this->getProfileCompletion($user);
                if ($completion < 100) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Complete your profile first to earn this reward.',
                    ], 400);
                }
            }

            $alreadyClaimed = CoinReward::where('user_id', $user->id)
                ->alreadyClaimed($type)
                ->exists();

            if ($alreadyClaimed) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have already claimed this reward.',
                ], 409);
            }
        } elseif ($type === 'referral') {
            // Referral requires a valid reference code — validated by caller
            if (empty($request->reference)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Referral code is required.',
                ], 400);
            }

            // Prevent duplicate referral for same code
            $alreadyClaimed = CoinReward::where('user_id', $user->id)
                ->where('reward_type', 'referral')
                ->where('reference', $request->reference)
                ->exists();

            if ($alreadyClaimed) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have already claimed this referral.',
                ], 409);
            }
        }

        // ── Grant reward ──

        CoinReward::create([
            'user_id'     => $user->id,
            'reward_type' => $type,
            'coins_earned' => $amount,
            'reference'   => $request->reference,
            'claimed_at'  => now(),
        ]);

        CoinTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $amount,
            'type'        => 'reward',
            'description' => $this->rewardDescription($type),
        ]);

        $user->increment('coin_balance', $amount);

        return response()->json([
            'status'  => true,
            'message' => "You earned {$amount} coins!",
            'data'    => [
                'coins_earned' => $amount,
                'coin_balance' => $user->fresh()->coin_balance,
            ],
        ]);
    }

    private function rewardDescription(string $type): string
    {
        return match ($type) {
            'daily_login'      => 'Daily login reward',
            'follow_instagram' => 'Follow on Instagram reward',
            'follow_twitter'   => 'Follow on Twitter/X reward',
            'follow_tiktok'    => 'Follow on TikTok reward',
            'referral'         => 'Share & referral reward',
            'complete_profile' => 'Profile completion reward',
            default            => 'Reward',
        };
    }

    private function getProfileCompletion($user): int
    {
        // Simple check — count filled profile fields
        $fields = ['name', 'bio', 'image', 'gender', 'age', 'zodiac_sign'];
        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($user->{$field})) $filled++;
        }
        return (int) round(($filled / count($fields)) * 100);
    }
}
