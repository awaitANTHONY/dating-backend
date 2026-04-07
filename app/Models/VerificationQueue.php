<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationQueue extends Model
{
    protected $table = 'verification_queue';

    protected $fillable = [
        'user_id',
        'selfie_image',
        'status',
        'ai_confidence',
        'ai_response',
        'reason',
        'approved_by_admin',
        'approved_at',
        'manual_notes',
    ];

    protected $casts = [
        'ai_confidence' => 'float',
        'ai_response' => 'json',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationship: Belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: User who approved (nullable)
     */
    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeHighConfidence($query)
    {
        return $query->where('ai_confidence', '>=', 0.95);
    }

    public function scopeManualReview($query)
    {
        return $query->whereBetween('ai_confidence', [0.85, 0.95]);
    }

    public function scopeAutoRejected($query)
    {
        return $query->where('ai_confidence', '<', 0.85);
    }
}
