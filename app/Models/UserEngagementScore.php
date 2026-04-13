<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEngagementScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'received_likes_count',
        'received_likes_7d',
        'sent_likes_count',
        'match_count',
        'match_rate',
        'profile_completeness',
        'response_rate',
        'impressions_count',
        'impressions_without_like',
        'popularity_score',
        'quality_score',
        'activity_score',
        'freshness_score',
        'engagement_score',
        'last_computed_at',
    ];

    protected $casts = [
        'match_rate' => 'decimal:4',
        'profile_completeness' => 'decimal:4',
        'response_rate' => 'decimal:4',
        'popularity_score' => 'decimal:4',
        'quality_score' => 'decimal:4',
        'activity_score' => 'decimal:4',
        'freshness_score' => 'decimal:4',
        'engagement_score' => 'decimal:2',
        'last_computed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
