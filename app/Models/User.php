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
    ];

    public function getImageAttribute($data)
    {
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
}
