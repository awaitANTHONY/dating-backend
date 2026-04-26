<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectConnectRequest extends Model
{
    protected $table = 'direct_connect_requests';

    protected $fillable = [
        'requester_id',
        'owner_id',
        'platform_id',
        'status',
        'coins_spent',
        'responded_at',
        'expires_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'expires_at'   => 'datetime',
        'coins_spent'  => 'integer',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id')
            ->select(['id', 'name', 'image', 'last_activity']);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id')
            ->select(['id', 'name', 'image', 'last_activity']);
    }

    public function platform()
    {
        return $this->belongsTo(ContactPlatform::class, 'platform_id');
    }
}
