<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserContact extends Model
{
    use HasFactory;

    protected $table = 'user_contacts';

    protected $fillable = [
        'user_id',
        'contact_platform_id',
        'value',
        'status',
    ];

    protected $casts = [
        'value' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function platform()
    {
        return $this->belongsTo(ContactPlatform::class, 'contact_platform_id');
    }
}
