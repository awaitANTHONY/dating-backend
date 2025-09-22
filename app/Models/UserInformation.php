<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserInformation extends Model
{
    use HasFactory;

    public function getDateOfBirthAttribute($value)
    {
        $date = $this->attributes['date_of_birth'] ?? $value;
        return $date ? \Illuminate\Support\Carbon::parse($date)->format('Y-m-d') : null;
    }
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
        'wallet_balance',
        'search_radius',
        'country_code',
        'phone',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'search_preference' => 'string',
        'relation_goals' => 'array',
        'interests' => 'array',
        'languages' => 'array',
        'latitude' => 'string',
        'longitude' => 'string',
        'wallet_balance' => 'decimal:2'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $appends = [
        'relation_goals_details',
        'interests_details',
        'languages_details',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Get relation goals details
    public function getRelationGoalsDetailsAttribute()
    {
        $ids = $this->relation_goals;

       
        if (empty($ids)) return collect();
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                return collect();
            }
        }
        if (!is_array($ids) || empty($ids)) return collect();
        
        $ids = array_map('intval', $ids);
        return RelationGoal::whereIn('id', $ids)->get();
    }

    // Get interests details
    public function getInterestsDetailsAttribute()
    {
        $ids = $this->interests;
        if (empty($ids)) return collect();
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                return collect();
            }
        }
        if (!is_array($ids) || empty($ids)) return collect();
        $ids = array_map('intval', $ids);
        return Interest::whereIn('id', $ids)->get();
    }

    // Get languages details
    public function getLanguagesDetailsAttribute()
    {
        $ids = $this->languages;
        if (empty($ids)) return collect();
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                return collect();
            }
        }
        if (!is_array($ids) || empty($ids)) return collect();
        $ids = array_map('intval', $ids);
        return Language::whereIn('id', $ids)->get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function religion()
    {
        return $this->hasOne('App\Models\Religion', 'id', 'religion_id')->withDefault();
    }
}
