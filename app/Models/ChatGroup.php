<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', // 'private' or 'group'
        'group_name',
        'last_message',
        'last_timestamp',
    ];

    public function members()
    {
        return $this->hasMany(ChatGroupMember::class, 'group_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'group_id');
    }
}
