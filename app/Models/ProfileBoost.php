<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ProfileBoost extends Model
{
    use HasFactory;

    protected $table = 'profile_boosts';

    protected $fillable = [
        'user_id',
        'boost_package_id',
        'activated_at',
        'expires_at',
        'status'
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    /**
     * Get the user that owns the boost
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the boost package
     */
    public function boostPackage()
    {
        return $this->belongsTo(BoostPackage::class);
    }

    /**
     * Activate a boost for the package's specified duration
     */
    public function activate()
    {
        if ($this->status !== 'purchased') {
            return false;
        }

        // Get boost duration from the package, default to 30 minutes if not found
        $boostDuration = $this->boostPackage ? $this->boostPackage->boost_duration : 30;

        $this->status = 'active';
        $this->activated_at = now();
        $this->expires_at = now()->addMinutes($boostDuration);
        $this->save();

        // Cache the boost status
        cache()->put("profile_boost_active_{$this->user_id}", true, now()->addMinutes($boostDuration));

        return true;
    }

    /**
     * Check if boost is currently active
     */
    public function isActive()
    {
        return $this->status === 'active' && 
               $this->expires_at && 
               $this->expires_at->isFuture();
    }

    /**
     * Get active boosts for a user
     */
    public static function getActiveBoost($userId)
    {
        return static::where('user_id', $userId)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();
    }

    /**
     * Get all currently active boosted users
     */
    public static function getActiveBoostedUsers()
    {
        return static::where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->with('user')
                    ->get()
                    ->pluck('user_id')
                    ->toArray();
    }

    /**
     * Expire old boosts (run via scheduler)
     */
    public static function expireOldBoosts()
    {
        $expiredBoosts = static::where('status', 'active')
                              ->where('expires_at', '<=', now())
                              ->get();

        foreach ($expiredBoosts as $boost) {
            // Clear cache for expired boosts
            cache()->forget("profile_boost_active_{$boost->user_id}");
        }

        return static::where('status', 'active')
                    ->where('expires_at', '<=', now())
                    ->update(['status' => 'expired']);
    }

    /**
     * Get boost packages from BoostPackage model (deprecated - use BoostPackage::getPackagesForApi() directly)
     */
    public static function getBoostPackages($platform = null)
    {
        return BoostPackage::getPackagesForApi($platform);
    }
}
