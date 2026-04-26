<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannedIp extends Model
{
    protected $fillable = ['ip_address', 'user_id', 'reason'];

    public static function isBanned(?string $ip): bool
    {
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'])) {
            return false;
        }
        return static::where('ip_address', $ip)->exists();
    }

    public static function ban(?string $ip, ?int $userId = null, string $reason = 'Banned by admin.'): void
    {
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'])) {
            return;
        }
        static::firstOrCreate(
            ['ip_address' => $ip],
            ['user_id' => $userId, 'reason' => $reason]
        );
    }

    public static function unban(?string $ip): void
    {
        if (!$ip) return;
        static::where('ip_address', $ip)->delete();
    }
}
