<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInteraction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'target_user_id',
        'action',
        'interaction_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'action' => 'string',
    ];

    /**
     * Get the user who performed the interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the target user who was interacted with.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Check if there's a mutual like between two users.
     */
    public static function isMutualLike(int $userId, int $targetUserId): bool
    {
        // Check if both users have liked each other
        $userLikesTarget = self::where('user_id', $userId)
            ->where('target_user_id', $targetUserId)
            ->where('action', 'like')
            ->exists();

        $targetLikesUser = self::where('user_id', $targetUserId)
            ->where('target_user_id', $userId)
            ->where('action', 'like')
            ->exists();

        return $userLikesTarget && $targetLikesUser;
    }

    /**
     * Get all interactions where the current user was the target and the action was 'like'.
     */
    public static function getReceivedLikes(int $userId)
    {
        return self::where('target_user_id', $userId)
            ->where('action', 'like')
            ->with(['user.user_information'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn($interaction) => $interaction->user !== null) // skip deleted users
            ->map(function($interaction) {
                $user     = $interaction->user;
                $userInfo = $user->user_information;

                $interaction->user->is_vip      = (bool) $user->isVipActive();
                $interaction->user->is_boosted  = (bool) $user->isBoosted();
                $interaction->user->is_verified = $userInfo ? ($userInfo->is_verified ?? false) : false;
                $interaction->user->is_online   = $user->last_activity && $user->last_activity->diffInHours(now()) <= 3;

                return $interaction;
            })
            ->values();
    }

    /**
     * Get all interactions performed by the current user.
     */
    public static function getUserInteractions(int $userId)
    {
        return self::where('user_id', $userId)
            ->with('targetUser')
            ->get()
            ->filter(fn($interaction) => $interaction->targetUser !== null) // skip deleted users
            ->map(function($interaction) {
                $interaction->targetUser->is_vip = (bool) $interaction->targetUser->isVipActive();
                return $interaction;
            })
            ->values();
    }
}
