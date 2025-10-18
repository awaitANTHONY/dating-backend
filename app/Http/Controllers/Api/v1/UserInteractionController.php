<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserInteraction;
use App\Models\UserMatch;
use App\Models\UserBlock;
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

            // Store or update the interaction
            $interaction = UserInteraction::updateOrCreate(
                [
                    'user_id' => $userId,
                    'target_user_id' => $targetUserId,
                ],
                [
                    'action' => $action,
                ]
            );

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
     * Get all matches for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMatches(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $matches = UserMatch::getMatchesForUser($userId);

            return response()->json([
                'status' => true,
                'data' => $matches,
                'message' => 'Matches retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve matches',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
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
