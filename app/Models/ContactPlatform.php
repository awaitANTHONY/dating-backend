<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPlatform extends Model
{
    protected $fillable = ['name', 'icon', 'placeholder', 'sort_order', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function userContacts()
    {
        return $this->hasMany(UserContact::class, 'contact_platform_id');
    }
}
