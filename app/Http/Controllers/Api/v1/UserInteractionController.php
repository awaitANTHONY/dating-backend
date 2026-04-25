<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserInteraction;
use App\Models\UserMatch;
use App\Models\UserBlock;
use App\Models\CoinTransaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserInteractionController extends Controller
{
    /**
     * Store a new user interaction (swipe).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
            ],
            'action' => [
                'required',
                Rule::in(['like', 'dislike', 'pass']),
            ],
        ]);

        $userId = $request->user()->id;
        $targetUserId = $request->target_user_id;
        $action = $request->action;

        if ($userId === $targetUserId) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot interact with yourself',
            ], 400);
        }

        // Check if there's a mutual block between users
        $isBlocked = UserBlock::where('user_id', $userId)
            ->where('blocked_user_id', $targetUserId)
            ->exists();
        $isBlockedBy = UserBlock::where('user_id', $targetUserId)
            ->where('blocked_user_id', $userId)
            ->exists();

        if ($isBlocked || $isBlockedBy) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot interact with blocked user',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Store or update the interaction (track re-swipes via interaction_count)
            $existing = UserInteraction::where('user_id', $userId)
                ->where('target_user_id', $targetUserId)
                ->first();

            if ($existing) {
                $existing->update([
                    'action' => $action,
                    'interaction_count' => $existing->interaction_count + 1,
                ]);
                $interaction = $existing;
            } else {
                $interaction = UserInteraction::create([
                    'user_id' => $userId,
                    'target_user_id' => $targetUserId,
                    'action' => $action,
                    'interaction_count' => 1,
                ]);
            }

            $isMatch = false;
            $matchId = null;

            // If the action is 'like', check for mutual match
            if ($action === 'like') {
                $match = UserMatch::createIfMutual($userId, $targetUserId);
                
                if ($match) {
                    $isMatch = true;
                    $matchId = $match->id;
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'data' => [
                    'interaction_id' => $interaction->id,
                    'action' => $action,
                    'is_match' => $isMatch,
                    'match_id' => $matchId,
                ],
                'message' => 'Interaction recorded successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to record interaction',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user interactions history.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $interactions = UserInteraction::getUserInteractions($userId);

            return response()->json([
                'status' => true,
                'data' => $interactions,
                'message' => 'Interactions retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve interactions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get users who liked the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLikes(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $likes = UserInteraction::getReceivedLikes($userId);

            return response()->json([
                'status' => true,
                'data' => $likes,
                'message' => 'Received likes retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve likes',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all matches for the authenticated user, classified as active or expired.
     * Returns the format expected by the Flutter app: { active:[...], expired:[...], limits:{...} }
     */
    public function getMatches(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $isPremium = (bool) $user->isVipActive();

            // Read configurable expiry hours from settings, with safe defaults
            $freeExpiryHours    = (int) (Setting::where('name', 'match_expiry_hours_free')->value('value')    ?: 48);
            $premiumExpiryHours = (int) (Setting::where('name', 'match_expiry_hours_premium')->value('value') ?: 168);
            $freeVisibleCount   = (int) (Setting::where('name', 'match_free_visible_count')->value('value')   ?: 3);
            $reviveCoinCost     = (int) (Setting::where('name', 'match_revive_coin_cost')->value('value')     ?: 10);

            $expiryHours = $isPremium ? $premiumExpiryHours : $freeExpiryHours;
            $cutoff      = now()->subHours($expiryHours);

            $rows = UserMatch::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('target_user_id', $user->id);
                })
                ->with(['user.user_information', 'targetUser.user_information'])
                ->orderBy('created_at', 'desc')
                ->get();

            $active  = [];
            $expired = [];

            foreach ($rows as $match) {
                $otherUser = $match->user_id === $user->id ? $match->targetUser : $match->user;
                if (!$otherUser) continue;

                $otherUser->is_vip      = (bool) $otherUser->isVipActive();
                $otherUser->is_verified = $otherUser->user_information
                    ? (bool) ($otherUser->user_information->is_verified ?? false)
                    : false;

                // Use updated_at as the expiry reference: starts equal to created_at,
                // gets reset to now() on revive via touch
                $referenceTime = $match->updated_at ?? $match->created_at;
                $expiresAt     = $referenceTime->copy()->addHours($expiryHours);
                $isExpired     = $referenceTime->lt($cutoff);

                $item = [
                    'id'         => $match->id,
                    'match_id'   => $match->id,
                    'user_id'    => $otherUser->id,
                    'user'       => $otherUser,
                    'created_at' => $match->created_at,
                    'expires_at' => $expiresAt,
                    'expired_at' => $isExpired ? $expiresAt : null,
                    'status'     => $isExpired ? 'expired' : 'active',
                ];

                if ($isExpired) {
                    $expired[] = $item;
                } else {
                    $active[] = $item;
                }
            }

            return response()->json([
                'status'  => true,
                'data'    => [
                    'active'  => $active,
                    'expired' => $expired,
                    'limits'  => [
                        'free_visible_count' => $freeVisibleCount,
                        'revive_coin_cost'   => $reviveCoinCost,
                        'is_premium'         => $isPremium,
                    ],
                ],
                'message' => 'Matches retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to retrieve matches',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Revive an expired match. Costs coins for free users; free for premium.
     * POST /api/v1/matches/{matchId}/revive
     */
    public function reviveMatch(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();

        try {
            $isPremium      = (bool) $user->isVipActive();
            $reviveCoinCost = (int) (Setting::where('name', 'match_revive_coin_cost')->value('value') ?: 10);

            $match = UserMatch::where('id', $matchId)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('target_user_id', $user->id);
                })
                ->first();

            if (!$match) {
                return response()->json(['status' => false, 'message' => 'Match not found'], 404);
            }

            DB::transaction(function () use ($user, $match, $reviveCoinCost, $isPremium) {
                if (!$isPremium) {
                    $balance = (int) $user->coin_balance;
                    if ($balance < $reviveCoinCost) {
                        throw new \Exception("NOT_ENOUGH_COINS:{$reviveCoinCost}:{$balance}");
                    }
                    $user->coin_balance = $balance - $reviveCoinCost;
                    $user->save();

                    CoinTransaction::create([
                        'user_id'        => $user->id,
                        'amount'         => $reviveCoinCost,
                        'status'         => 'Debit',
                        'description'    => 'Match revive',
                        'reference_type' => 'match_revive',
                        'reference_id'   => $match->id,
                    ]);
                }

                // Reset expiry: update updated_at so the expiry window restarts from now
                $match->timestamps = false;
                $match->updated_at = now();
                $match->save();
                $match->timestamps = true;
            });

            return response()->json([
                'status'  => true,
                'message' => 'Match revived successfully',
                'data'    => ['balance' => (int) $user->coin_balance],
            ]);

        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'NOT_ENOUGH_COINS:')) {
                [, $required, $available] = explode(':', $msg);
                return response()->json([
                    'status'  => false,
                    'message' => "Not enough coins. You need {$required} coins.",
                    'data'    => ['required' => (int) $required, 'available' => (int) $available],
                ], 402);
            }
            return response()->json([
                'status'  => false,
                'message' => 'Failed to revive match',
                'error'   => config('app.debug') ? $msg : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check if two users are matched.
     *
     * @param Request $request
     * @param int $targetUserId
     * @return JsonResponse
     */
    public function checkMatch(Request $request, int $targetUserId): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $isMatched = UserMatch::areMatched($userId, $targetUserId);

            return response()->json([
                'status' => true,
                'data' => [
                    'is_matched' => $isMatched,
                    'user_id' => $userId,
                    'target_user_id' => $targetUserId,
                ],
                'message' => 'Match status retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check match status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Unmatch with a user (soft delete the match).
     *
     * @param Request $request
     * @param int $targetUserId
     * @return JsonResponse
     */
    public function unmatch(Request $request, int $targetUserId): JsonResponse
    {
        $userId = $request->user()->id;

        // Validate that the target user exists
        $request->validate([
            'target_user_id' => 'exists:users,id',
        ]);

        try {
            $unmatched = UserMatch::unmatch($userId, $targetUserId);

            if ($unmatched) {
                return response()->json([
                    'status' => true,
                    'message' => 'Successfully unmatched',
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No match found to unmatch',
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to unmatch',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get match statistics for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMatchStats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $totalMatches = UserMatch::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('target_user_id', $userId);
            })->count();

            $activeMatches = UserMatch::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('target_user_id', $userId);
            })->whereNull('deleted_at')->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'total_matches' => $totalMatches,
                    'active_matches' => $activeMatches,
                ],
                'message' => 'Match statistics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve match statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
