<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectConnectRequest extends Model
{
    protected $table = 'contact_requests';

    protected $fillable = [
        'requester_id',
        'owner_id',
        'contact_platform_id',
        'status',
        'coin_cost',
        'was_free',
        'expires_at',
        'responded_at',
        'approved_expires_at',
    ];

    protected $casts = [
        'was_free'            => 'boolean',
        'expires_at'          => 'datetime',
        'responded_at'        => 'datetime',
        'approved_expires_at' => 'datetime',
    ];

    // ── Relationships ──

    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'requester_id');
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function contactPlatform()
    {
        return $this->belongsTo(ContactPlatform::class, 'contact_platform_id');
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
