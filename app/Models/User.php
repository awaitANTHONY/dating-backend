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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
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
     * Check if user has active VIP membership
     * 
     * @return bool
     */
    public function isVipActive()
    {
        return ($this->is_vip ?? false) || ($this->vip_expire && $this->vip_expire->isFuture());
    }
}
