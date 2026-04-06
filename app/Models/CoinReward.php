<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CoinReward extends Model
{
    protected $fillable = [
        'user_id',
        'reward_type',
        'coins_earned',
        'reference',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a daily reward was already claimed today.
     */
    public function scopeClaimedToday($query, string $type)
    {
        return $query->where('reward_type', $type)
                     ->whereDate('claimed_at', Carbon::today());
    }

    /**
     * Check if a one-time reward has ever been claimed.
     */
    public function scopeAlreadyClaimed($query, string $type)
    {
        return $query->where('reward_type', $type);
    }

    /**
     * Daily reward types (can claim once per day).
     */
    public static function dailyTypes(): array
    {
        return ['daily_login'];
    }

    /**
     * One-time reward types (can only claim once ever).
     */
    public static function oneTimeTypes(): array
    {
        return ['complete_profile', 'follow_instagram', 'follow_twitter', 'follow_tiktok'];
    }

    /**
     * Per-event reward types (can claim multiple times).
     */
    public static function perEventTypes(): array
    {
        return ['referral'];
    }
}
