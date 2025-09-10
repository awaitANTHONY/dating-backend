<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserInformation extends Model
{
    use HasFactory;

    protected $table = 'user_information';

    protected $fillable = [
        'user_id',
        'bio',
        'gender',
        'date_of_birth',
        'religion_id',
        'latitude',
        'longitude',
        'search_preference',
        'relation_goals',
        'interests',
        'languages',
        'wallet_balance'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'search_preference' => 'array',
        'relation_goals' => 'array',
        'interests' => 'array',
        'languages' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'wallet_balance' => 'decimal:2'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function religion()
    {
        return $this->belongsTo(Religion::class);
    }

    // Get relation goals details
    public function getRelationGoalsDetailsAttribute()
    {
        if (!$this->relation_goals) return collect();
        
        return RelationGoal::whereIn('id', $this->relation_goals)->get();
    }

    // Get interests details
    public function getInterestsDetailsAttribute()
    {
        if (!$this->interests) return collect();
        
        return Interest::whereIn('id', $this->interests)->get();
    }

    // Get languages details
    public function getLanguagesDetailsAttribute()
    {
        if (!$this->languages) return collect();
        
        return Language::whereIn('id', $this->languages)->get();
    }

    // Scope for filtering by gender
    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    // Scope for filtering by search preference
    public function scopeBySearchPreference($query, $preference)
    {
        return $query->whereJsonContains('search_preference', $preference);
    }

    // Scope for filtering by age range
    public function scopeByAgeRange($query, $minAge, $maxAge)
    {
        $minDate = now()->subYears($maxAge)->format('Y-m-d');
        $maxDate = now()->subYears($minAge)->format('Y-m-d');
        
        return $query->whereBetween('date_of_birth', [$minDate, $maxDate]);
    }

    // Calculate age from date of birth
    public function getAgeAttribute()
    {
        if (!$this->date_of_birth) return null;
        
        return $this->date_of_birth->age;
    }
}
