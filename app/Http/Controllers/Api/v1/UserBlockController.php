<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserBlock;
use App\Models\UserMatch;
use App\Models\UserInteraction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserBlockController extends Controller
{
    /**
     * Toggle block/unblock a user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleBlock(Request $request): JsonResponse
    {
        // Validate the request
        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $userId = $request->user()->id;
        $targetUserId = $request->target_user_id;
        $reason = $request->reason ?? null;

        // Prevent users from blocking themselves
        if ($userId === $targetUserId) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot block yourself',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Check if user is already blocked
            $existingBlock = UserBlock::where('user_id', $userId)
                ->where('blocked_user_id', $targetUserId)
                ->first();

            if ($existingBlock) {
                // User is blocked - unblock them
                $existingBlock->delete();
                
                DB::commit();

                return response()->json([
                    'status' => true,
                    'data' => [
                        'action' => 'unblocked',
                        'target_user_id' => $targetUserId,
                        'is_blocked' => false,
                    ],
                    'message' => 'User unblocked successfully',
                ]);
            } else {
                // User is not blocked - block them
                $block = UserBlock::create([
                    'user_id' => $userId,
                    'blocked_user_id' => $targetUserId,
                    'reason' => $reason,
                ]);

                // Remove any existing match if users are matched
                if (UserMatch::areMatched($userId, $targetUserId)) {
                    UserMatch::unmatch($userId, $targetUserId);
                }

                // Remove any existing interactions
                UserInteraction::where(function ($query) use ($userId, $targetUserId) {
                    $query->where('user_id', $userId)->where('target_user_id', $targetUserId);
                })->orWhere(function ($query) use ($userId, $targetUserId) {
                    $query->where('user_id', $targetUserId)->where('target_user_id', $userId);
                })->delete();

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'User blocked successfully',
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to toggle block status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get list of blocked users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBlockedUsers(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $blockedUsers = UserBlock::where('user_id', $userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $blockedUsers,
            'message' => 'Blocked users retrieved successfully',
        ]);
    }
}
