<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class UserMatch extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'matches';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'target_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the first user in the match.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the second user in the match.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Create a match if mutual like exists between two users.
     */
    public static function createIfMutual(int $userId, int $targetUserId): ?self
    {
        // Check if mutual like exists
        if (!UserInteraction::isMutualLike($userId, $targetUserId)) {
            return null;
        }

        // Ensure user_id is always smaller than target_user_id
        $userOneId = min($userId, $targetUserId);
        $userTwoId = max($userId, $targetUserId);

        // Check if match already exists
        $existingMatch = self::where('user_id', $userOneId)
            ->where('target_user_id', $userTwoId)
            ->first();

        if ($existingMatch) {
            // If it was soft deleted, restore it
            if ($existingMatch->trashed()) {
                $existingMatch->restore();
            }
            return $existingMatch;
        }

        // Create new match
        return self::create([
            'user_id' => $userOneId,
            'target_user_id' => $userTwoId,
        ]);
    }

    /**
     * Get all matches for a specific user.
     */
    public static function getMatchesForUser(int $userId)
    {
        return self::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('target_user_id', $userId);
        })
        ->with(['user', 'targetUser'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($match) use ($userId) {
            // Return the other user in the match
            $otherUser = $match->user_id === $userId 
                ? $match->targetUser 
                : $match->user;
            
            $otherUser->is_vip = (bool) $otherUser->isVipActive();
            
            return [
                'match_id' => $match->id,
                'matched_at' => $match->created_at,
                'user' => $otherUser,
            ];
        });
    }

    /**
     * Check if two users are matched.
     */
    public static function areMatched(int $userId, int $targetUserId): bool
    {
        $userOneId = min($userId, $targetUserId);
        $userTwoId = max($userId, $targetUserId);

        return self::where('user_id', $userOneId)
            ->where('target_user_id', $userTwoId)
            ->exists();
    }

    /**
     * Unmatch two users (soft delete).
     */
    public static function unmatch(int $userId, int $targetUserId): bool
    {
        $userOneId = min($userId, $targetUserId);
        $userTwoId = max($userId, $targetUserId);

        $match = self::where('user_id', $userOneId)
            ->where('target_user_id', $userTwoId)
            ->first();

        if ($match) {
            return $match->delete();
        }

        return false;
    }
}
