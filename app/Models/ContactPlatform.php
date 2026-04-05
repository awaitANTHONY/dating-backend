<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactPlatform extends Model
{
    use HasFactory;

    protected $table = 'contact_platforms';

    protected $fillable = [
        'name',
        'icon',
        'placeholder',
        'sort_order',
        'status',
    ];

    public function userContacts()
    {
        return $this->hasMany(UserContact::class, 'contact_platform_id');
    }

    public static function getActivePlatforms()
    {
        return static::where('status', true)
            ->orderBy('sort_order')
            ->get();
    }
}
