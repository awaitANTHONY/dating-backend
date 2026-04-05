<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactRequest extends Model
{
    use HasFactory;

    protected $table = 'contact_requests';

    protected $fillable = [
        'requester_id',
        'owner_id',
        'contact_platform_id',
        'status',
        'coins_spent',
        'responded_at',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function platform()
    {
        return $this->belongsTo(ContactPlatform::class, 'contact_platform_id');
    }

    public static function expirePendingRequests()
    {
        return static::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }
}
