<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'duration_type',
        'duration',
        'platform',
        'product_id',
        'filter_include',
        'audio_video',
        'direct_chat',
        'chat',
        'like_menu',
        'position',
        'status',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'status', 
        'position',
        'created_at', 
        'updated_at'
    ];

}
