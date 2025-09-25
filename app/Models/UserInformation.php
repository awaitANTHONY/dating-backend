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
        'images',
        'is_zodiac_sign_matter',
        'is_food_preference_matter',
        'age',
        'relationship_status_id',
        'ethnicity_id',
        'alkohol',
        'smoke',
        'education_id',
        'preffered_age',
        'tall',
        'carrer_field_id',
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
        'relationship_status_details',
        'ethnicity_details',
        'education_details',
        'career_field_details',
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

    // Get relationship status details
    public function getRelationshipStatusDetailsAttribute()
    {
        return $this->relationshipStatus;
    }

    // Get ethnicity details
    public function getEthnicityDetailsAttribute()
    {
        return $this->ethnicity;
    }

    // Get education details
    public function getEducationDetailsAttribute()
    {
        return $this->education;
    }

    // Get career field details
    public function getCareerFieldDetailsAttribute()
    {
        return $this->careerField;
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

    public function relationshipStatus()
    {
        return $this->hasOne('App\Models\RelationshipStatus', 'id', 'relationship_status_id')->withDefault();
    }

    public function ethnicity()
    {
        return $this->hasOne('App\Models\Ethnicity', 'id', 'ethnicity_id')->withDefault();
    }

    public function education()
    {
        return $this->hasOne('App\Models\Education', 'id', 'education_id')->withDefault();
    }

    public function careerField()
    {
        return $this->hasOne('App\Models\CareerField', 'id', 'carrer_field_id')->withDefault();
    }
}
