<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    protected $table = 'users';

    protected $appends = [
        
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'user_type',
        'password',
        'status',
        'email_otp',
        'email_verified_at',
        'last_activity',
        'is_vip',
        'vip_expire',
        'image',
        'verification_status',
        'verified_at',
        'last_scanned_at',
        'image_hash',
        'verification_attempts',
        'verification_cooldown_until',
        'is_banned',
        'ban_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'updated_at',
        'provider',
        'is_fake',
        'email_otp',
        'email_verified_at',
        'user_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_activity' => 'datetime',
        'vip_expire' => 'datetime',
        'verified_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'verification_cooldown_until' => 'datetime',
        'is_vip' => 'boolean',
        'is_banned' => 'boolean',
        'verification_attempts' => 'integer',
    ];

    public function getImageAttribute($data)
    {
        if (!$data) {
            return asset('public/default/profile.png');
        }
        return asset($data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function app()
    {
        return $this->hasOne('App\Models\AppModel', 'id', 'app_id')->withDefault();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function subscription()
    {
        return $this->hasOne('App\Models\Subscription', 'id', 'subscription_id')->withDefault(['id' => 0, 'name' => 'Free User']);
    }

    /**
     * Get the user information for the user.
     */
    public function user_information()
    {
        return $this->hasOne(UserInformation::class);
    }

    /**
     * Get the verification queue entry for this user.
     */
    public function verificationQueue()
    {
        return $this->hasOne(VerificationQueue::class);
    }

    /**
     * Users that this user has blocked
     */
    public function blockedUsers()
    {
        return $this->hasMany(UserBlock::class, 'user_id');
    }

    /**
     * Users that have blocked this user
     */
    public function blockedByUsers()
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id');
    }

    /**
     * Interactions performed by this user
     */
    public function sentInteractions()
    {
        return $this->hasMany(UserInteraction::class, 'user_id');
    }

    /**
     * Interactions received by this user
     */
    public function receivedInteractions()
    {
        return $this->hasMany(UserInteraction::class, 'target_user_id');
    }

    /**
     * Get the verification request for the user.
     */
    public function verificationRequest()
    {
        return $this->hasOne(VerificationRequest::class)->latestOfMany();
    }

    /**
     * Get all verification requests for the user.
     */
    public function verificationRequests()
    {
        return $this->hasMany(VerificationRequest::class);
    }

    /**
     * Get all profile boosts for the user.
     */
    public function profileBoosts()
    {
        return $this->hasMany(ProfileBoost::class);
    }

    /**
     * Get the engagement score for the user.
     */
    public function engagementScore()
    {
        return $this->hasOne(UserEngagementScore::class);
    }

    /**
     * Get active profile boost for the user.
     */
    public function activeBoost()
    {
        return $this->hasOne(ProfileBoost::class)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now());
    }

    /**
     * Check if user is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'approved' && $this->verified_at !== null;
    }

    /**
     * Check if user has pending verification.
     */
    public function hasPendingVerification(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Check if user is permanently banned.
     */
    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    /**
     * Check if user is currently in a verification cooldown.
     */
    public function isInVerificationCooldown(): bool
    {
        return $this->verification_cooldown_until !== null
            && $this->verification_cooldown_until->isFuture();
    }

    /**
     * Increment verification rejection attempts and apply cooldown/ban when thresholds are hit.
     * Thresholds (configurable via config/verification.php):
     *   - After COOLDOWN_AFTER rejections  → set a cooldown period
     *   - After BAN_AFTER total rejections → permanently ban the account
     */
    public function handleVerificationRejection(): void
    {
        $attempts = ($this->verification_attempts ?? 0) + 1;
        $cooldownAfter = config('verification.attempt_limits.cooldown_after', 3);
        $banAfter      = config('verification.attempt_limits.ban_after', 6);
        $cooldownDays  = (int) config('verification.attempt_limits.cooldown_days', 7);

        $updates = ['verification_attempts' => $attempts];

        if ($attempts >= $banAfter) {
            $updates['is_banned']   = true;
            $updates['ban_reason']  = 'Permanently banned after ' . $attempts . ' failed verification attempts.';
            $updates['verification_cooldown_until'] = null;
        } elseif ($attempts >= $cooldownAfter) {
            $updates['verification_cooldown_until'] = now()->addDays($cooldownDays);
        }

        $this->update($updates);

        // If banned, also flag the email so they can't re-register
        if ($attempts >= $banAfter) {
            \App\Models\BannedEmail::ban($this->email, $this->id, $updates['ban_reason']);
        }
    }

    /**
     * Check if user has active VIP membership
     * 
     * @return bool
     */
    public function isVipActive()
    {
        return ($this->is_vip ?? false) && ($this->vip_expire && $this->vip_expire->isFuture());
    }

    /**
     * Check if user has active boost
     * 
     * @return bool
     */
    public function isBoosted()
    {
        return $this->activeBoost()->exists();
    }
}
