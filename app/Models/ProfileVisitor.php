<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProfileVisitor extends Model
{
    use HasFactory;

    protected $table = 'profile_visitors';

    protected $fillable = [
        'visitor_id',
        'visited_user_id',
        'visited_at'
    ];

    protected $casts = [
        'visited_at' => 'datetime'
    ];

    /**
     * Get the user who visited the profile
     */
    public function visitor()
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    /**
     * Get the user whose profile was visited
     */
    public function visitedUser()
    {
        return $this->belongsTo(User::class, 'visited_user_id');
    }

    /**
     * Scope to get recent visitors for a specific user
     */
    public function scopeRecentVisitors($query, $userId, $limit = 20)
    {
        return $query->where('visited_user_id', $userId)
                    ->with(['visitor.user_information'])
                    ->orderBy('visited_at', 'desc')
                    ->limit($limit);
    }

    /**
     * Track a profile visit (simple - just store the visit)
     */
    public static function trackVisit($visitorId, $visitedUserId)
    {
        // Don't track self-visits
        if ($visitorId == $visitedUserId) {
            return null;
        }

        // Simple insert - no duplicate checking, just store every visit
        return static::create([
            'visitor_id' => $visitorId,
            'visited_user_id' => $visitedUserId,
            'visited_at' => now()
        ]);
    }
}
