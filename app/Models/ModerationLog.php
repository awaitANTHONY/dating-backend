<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'image_path',
        'decision',
        'reason',
        'confidence',
        'scanned_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'scanned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
