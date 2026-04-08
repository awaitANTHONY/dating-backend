<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannedEmail extends Model
{
    protected $fillable = [
        'email',
        'user_id',
        'reason',
    ];

    public static function isBanned(string $email): bool
    {
        return static::where('email', strtolower(trim($email)))->exists();
    }

    public static function ban(string $email, ?int $userId = null, string $reason = 'Repeated fake verification attempts'): void
    {
        static::firstOrCreate(
            ['email' => strtolower(trim($email))],
            ['user_id' => $userId, 'reason' => $reason]
        );
    }
}
