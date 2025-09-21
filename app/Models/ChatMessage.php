<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'sender_id',
        'sender_name',
        'message',
        'timestamp',
        'seen_by', // JSON: { user_id: true/false }
    ];

    protected $casts = [
        'seen_by' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(ChatGroup::class, 'group_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
