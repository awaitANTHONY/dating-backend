<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannedDevice extends Model
{
    protected $fillable = ['device_token', 'user_id', 'reason'];

    public static function isBanned(?string $deviceToken): bool
    {
        if (!$deviceToken) return false;
        return static::where('device_token', $deviceToken)->exists();
    }

    public static function ban(?string $deviceToken, ?int $userId = null, string $reason = 'Banned by admin.'): void
    {
        if (!$deviceToken) return;
        static::firstOrCreate(
            ['device_token' => $deviceToken],
            ['user_id' => $userId, 'reason' => $reason]
        );
    }

    public static function unban(?string $deviceToken): void
    {
        if (!$deviceToken) return;
        static::where('device_token', $deviceToken)->delete();
    }
}
