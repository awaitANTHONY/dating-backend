<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Cache;
use App\Models\User;
use App\Models\Interest;
use App\Models\Language;
use App\Models\RelationGoal;
use App\Models\Religion;
use App\Models\UserInformation;
use App\Models\UserInteraction;
use App\Models\UserMatch;
use App\Models\UserBlock;
use App\Models\ProfileVisitor;
use App\Models\ProfileBoost;
use App\Models\RelationshipStatus;
use App\Models\Ethnicity;
use App\Models\Education;
use App\Models\CareerField;

class ProfileController extends Controller
{
    /**
     * Unified method for both recommendations and search
     * When no filters are provided, it works as recommendations with smart matching
     * When filters are provided, it works as filtered search
     */
    public function recommendations(Request $request)
    {
        $user = $request->user();
        
        // Get user information using model relationship
        $userInformation = $user ? $user->user_information : null;
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        // Check if this is a search request (has filters) or recommendations request (no filters)
        $isSearchRequest = $this->hasSearchFilters($request);
        
        // For search requests, don't use cache as they are dynamic
        if ($isSearchRequest) {
            return $this->handleSearchWithFilters($request, $user, $userInformation);
        }

        // For recommendations, use cache with user-specific key
        $cacheKey = "recommendations_user_{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function() use ($request, $user, $userInformation) {
            return $this->generateRecommendations($request, $user, $userInformation);
        });
    }

    /**
     * Generate recommendations without caching (used by cache callback)
     */
    private function generateRecommendations(Request $request, $user, $userInformation)
    {
        // Get current user's preferences and location
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;
        $searchRadius = $userInformation->search_radius ?? 1000;
        $searchPreference = $userInformation->search_preference ?? 'male';

        // Build query for recommendations with smart matching
        $query = User::with(['user_information'])
            ->whereHas('user_information', function($q) use ($searchPreference) {
                $q->where('gender', $searchPreference);
            })
            ->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            });

        // Apply distance filter if coordinates are available
        if ($currentLat != 0 && $currentLng != 0) {
            $query->whereHas('user_information', function($q) use ($currentLat, $currentLng, $searchRadius) {
                $q->whereRaw("(
                    6371 * acos(
                        cos(radians({$currentLat})) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians({$currentLng})) +
                        sin(radians({$currentLat})) *
                        sin(radians(latitude))
                    )
                ) <= {$searchRadius}");
            });
        }
        
        $results = $query->limit(100)->get();

        // Transform results and add distance calculation
        $transformedResults = $results->map(function($user) use ($currentLat, $currentLng) {
            $userInfo = $user->user_information;
            if (!$userInfo) return null;

            // Calculate distance
            $distance = $this->calculateDistance(
                $currentLat, $currentLng,
                $userInfo->latitude, $userInfo->longitude
            );

            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'images' => $userInfo->images ,
                'bio' => $userInfo->bio,
                'gender' => $userInfo->gender,
                'date_of_birth' => $userInfo->date_of_birth,
                'age' => $userInfo->age,
                'height' => $userInfo->height,
                'relation_goals' => $userInfo->relation_goals,
                'interests' => $userInfo->interests,
                'languages' => $userInfo->languages,
                'latitude' => $userInfo->latitude,
                'longitude' => $userInfo->longitude,
                'religion_id' => $userInfo->religion_id,
                'relationship_status_id' => $userInfo->relationship_status_id,
                'ethnicity_id' => $userInfo->ethnicity_id,
                'education_id' => $userInfo->education_id,
                'carrer_field_id' => $userInfo->carrer_field_id,
                'alkohol' => $userInfo->alkohol,
                'smoke' => $userInfo->smoke,
                'preffered_age' => $userInfo->preffered_age,
                'is_zodiac_sign_matter' => $userInfo->is_zodiac_sign_matter,
                'is_food_preference_matter' => $userInfo->is_food_preference_matter,
                'country_code' => $userInfo->country_code,
                'is_verified' => $userInfo->is_verified ?? false,
                'is_online' => $user->last_activity && $user->last_activity->diffInHours(now()) <= 3,
                'distance' => $distance,
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                
                // Add detailed attributes from UserInformation model accessors
                'relation_goals_details' => $userInfo->relation_goals_details,
                'interests_details' => $userInfo->interests_details,
                'ethnicity_details' => $userInfo->ethnicity_details,
                'languages_details' => $userInfo->languages_details,

            ];
        })->filter()->values();

        // Get current user's decoded preferences for compatibility scoring
        $userRelationGoals = $userInformation->relation_goals ?? [];
        $userInterests = $userInformation->interests ?? [];
        $userLanguages = $userInformation->languages ?? [];

        if (is_string($userRelationGoals)) {
            $userRelationGoals = json_decode($userRelationGoals, true);
            if (is_string($userRelationGoals)) {
                $userRelationGoals = json_decode($userRelationGoals, true) ?? [];
            }
        }
        $userRelationGoals = is_array($userRelationGoals) ? $userRelationGoals : [];

        if (is_string($userInterests)) {
            $userInterests = json_decode($userInterests, true);
            if (is_string($userInterests)) {
                $userInterests = json_decode($userInterests, true) ?? [];
            }
        }
        $userInterests = is_array($userInterests) ? $userInterests : [];

        if (is_string($userLanguages)) {
            $userLanguages = json_decode($userLanguages, true);
            if (is_string($userLanguages)) {
                $userLanguages = json_decode($userLanguages, true) ?? [];
            }
        }
        $userLanguages = is_array($userLanguages) ? $userLanguages : [];

        // Calculate enhanced match scores for recommendations
        $scoredResults = $transformedResults->map(function($profile) use ($userInformation, $userRelationGoals, $userInterests, $userLanguages, $searchRadius) {
            $score = 0;

            // Get profile's preferences - handle double-encoded JSON strings
            $profileRelationGoals = $profile->relation_goals;
            $profileInterests = $profile->interests;
            $profileLanguages = $profile->languages;

            // Decode JSON fields
            if (is_string($profileRelationGoals)) {
                $profileRelationGoals = json_decode($profileRelationGoals, true);
                if (is_string($profileRelationGoals)) {
                    $profileRelationGoals = json_decode($profileRelationGoals, true) ?? [];
                }
            }
            $profileRelationGoals = is_array($profileRelationGoals) ? $profileRelationGoals : [];

            if (is_string($profileInterests)) {
                $profileInterests = json_decode($profileInterests, true);
                if (is_string($profileInterests)) {
                    $profileInterests = json_decode($profileInterests, true) ?? [];
                }
            }
            $profileInterests = is_array($profileInterests) ? $profileInterests : [];

            if (is_string($profileLanguages)) {
                $profileLanguages = json_decode($profileLanguages, true);
                if (is_string($profileLanguages)) {
                    $profileLanguages = json_decode($profileLanguages, true) ?? [];
                }
            }
            $profileLanguages = is_array($profileLanguages) ? $profileLanguages : [];

            // Core compatibility scoring
            $rgOverlap = !empty($userRelationGoals) && !empty($profileRelationGoals) ? array_values(array_intersect($userRelationGoals, $profileRelationGoals)) : [];
            $langOverlap = !empty($userLanguages) && !empty($profileLanguages) ? array_values(array_intersect($userLanguages, $profileLanguages)) : [];
            $intOverlap = !empty($userInterests) && !empty($profileInterests) ? array_values(array_intersect($userInterests, $profileInterests)) : [];

            if (!empty($rgOverlap)) {
                $score += 3; // Relationship goals are very important
            }
            if (!empty($langOverlap)) {
                $score += 2; // Language compatibility
            }
            if (!empty($intOverlap)) {
                $score += 1; // Shared interests
            }
            if ($profile->distance <= $searchRadius) {
                $score += 4; // Proximity is crucial
            }

            // Enhanced compatibility scoring
            // Religion Compatibility
            if ($userInformation->religion_id && $profile->religion_id) {
                if ($userInformation->religion_id == $profile->religion_id) {
                    $score += 3; // Same religion
                }
            }

            // Lifestyle Compatibility - Alcohol
            if ($userInformation->alkohol && $profile->alkohol) {
                if ($userInformation->alkohol == $profile->alkohol) {
                    $score += 2; // Same alcohol preference
                } elseif (
                    ($userInformation->alkohol == 'dont_drink' && $profile->alkohol == 'prefer_not_to_say') ||
                    ($userInformation->alkohol == 'prefer_not_to_say' && $profile->alkohol == 'dont_drink') ||
                    ($userInformation->alkohol == 'drink_socially' && $profile->alkohol == 'drink_frequently') ||
                    ($userInformation->alkohol == 'drink_frequently' && $profile->alkohol == 'drink_socially')
                ) {
                    $score += 1; // Compatible alcohol preferences
                }
            }

            // Lifestyle Compatibility - Smoking
            if ($userInformation->smoke && $profile->smoke) {
                if ($userInformation->smoke == $profile->smoke) {
                    $score += 2; // Same smoking preference
                } elseif (
                    ($userInformation->smoke == 'dont_smoke' && $profile->smoke == 'prefer_not_to_say') ||
                    ($userInformation->smoke == 'prefer_not_to_say' && $profile->smoke == 'dont_smoke') ||
                    ($userInformation->smoke == 'smoke_occasionally' && $profile->smoke == 'smoke_regularly') ||
                    ($userInformation->smoke == 'smoke_regularly' && $profile->smoke == 'smoke_occasionally')
                ) {
                    $score += 1; // Compatible smoking preferences
                }
            }

            // Education Level Compatibility
            if ($userInformation->education_id && $profile->education_id) {
                if ($userInformation->education_id == $profile->education_id) {
                    $score += 2; // Same education level
                } elseif (abs($userInformation->education_id - $profile->education_id) <= 1) {
                    $score += 1; // Similar education level
                }
            }

            // Age Compatibility & Age Preference Matching
            if ($userInformation->age && $profile->age) {
                $ageDiff = abs($userInformation->age - $profile->age);
                if ($ageDiff <= 2) {
                    $score += 3; // Very close age
                } elseif ($ageDiff <= 5) {
                    $score += 2; // Close age
                } elseif ($ageDiff <= 10) {
                    $score += 1; // Reasonable age difference
                }
            }

            // Age preference compatibility check
            if ($userInformation->preffered_age && $profile->age) {
                if (preg_match('/(\d+)-(\d+)/', $userInformation->preffered_age, $matches)) {
                    $minAge = (int)$matches[1];
                    $maxAge = (int)$matches[2];
                    if ($profile->age >= $minAge && $profile->age <= $maxAge) {
                        $score += 2; // Profile age fits user's preference
                    }
                }
            }

            // Height Compatibility
            if ($userInformation->height && $profile->height) {
                $heightDiff = abs($userInformation->height - $profile->height);
                if ($heightDiff <= 10) { // Within 10cm
                    $score += 1;
                }
            }

            // Career Field Compatibility
            if ($userInformation->carrer_field_id && $profile->carrer_field_id) {
                if ($userInformation->carrer_field_id == $profile->carrer_field_id) {
                    $score += 2; // Same career field
                }
            }

            // Relationship Status Compatibility
            if ($userInformation->relationship_status_id && $profile->relationship_status_id) {
                if ($userInformation->relationship_status_id == $profile->relationship_status_id) {
                    $score += 1; // Same relationship status
                }
            }

            // Special Compatibility Preferences
            if ($userInformation->is_zodiac_sign_matter && $profile->is_zodiac_sign_matter) {
                $score += 1; // Both care about zodiac signs
            }

            if ($userInformation->is_food_preference_matter && $profile->is_food_preference_matter) {
                $score += 1; // Both care about food preferences
            }

            // Bonus for Complete Profiles
            $userFieldCount = 0;
            $profileFieldCount = 0;
            
            $checkFields = ['bio', 'religion_id', 'relationship_status_id', 'ethnicity_id', 'education_id', 'carrer_field_id', 'alkohol', 'smoke', 'age', 'height'];
            
            foreach ($checkFields as $field) {
                if (!empty($userInformation->$field)) $userFieldCount++;
                if (!empty($profile->$field)) $profileFieldCount++;
            }
            
            // Bonus for profiles that are both well-filled
            if ($userFieldCount >= 7 && $profileFieldCount >= 7) {
                $score += 2; // Both profiles are comprehensive
            } elseif ($profileFieldCount >= 5) {
                $score += 1; // Target profile is reasonably complete
            }

            $profile->match_score = $score;
            $profile->compatibility_details = [
                'relation_goals_match' => !empty($rgOverlap),
                'language_match' => !empty($langOverlap),
                'interests_match' => !empty($intOverlap),
                'religion_match' => ($userInformation->religion_id && $profile->religion_id && $userInformation->religion_id == $profile->religion_id),
                'lifestyle_compatible' => (
                    ($userInformation->alkohol && $profile->alkohol && $userInformation->alkohol == $profile->alkohol) ||
                    ($userInformation->smoke && $profile->smoke && $userInformation->smoke == $profile->smoke)
                ),
                'age_compatible' => ($userInformation->age && $profile->age && abs($userInformation->age - $profile->age) <= 5),
                'within_distance' => ($profile->distance <= $searchRadius)
            ];
            
            return $profile;
        });

        // Get boosted users and prioritize them
        $boostedUserIds = ProfileBoost::getActiveBoostedUsers();
        
        // Separate boosted and regular profiles
        $boostedProfiles = $scoredResults->filter(function($profile) use ($boostedUserIds) {
            return in_array($profile->id, $boostedUserIds);
        })->sortByDesc('match_score')->values();

        $regularProfiles = $scoredResults->filter(function($profile) use ($boostedUserIds) {
            return !in_array($profile->id, $boostedUserIds);
        })->sortByDesc('match_score')->values();

        // Combine: boosted profiles first, then regular profiles
        $finalResults = $boostedProfiles->concat($regularProfiles)->values();

        return response()->json(['status' => true, 'data' => $finalResults]);
    }

    /**
     * Helper method to check if request has search filters
     */
    private function hasSearchFilters(Request $request)
    {
        $searchParams = [
            'q', 'gender', 'interests', 'languages', 'relation_goals',
            'religion_id', 'relationship_status_id', 'ethnicity_id', 
            'education_id', 'carrer_field_id', 'alkohol', 'smoke',
            'min_age', 'max_age', 'min_height', 'max_height', 'radius'
        ];

        foreach ($searchParams as $param) {
            if ($request->has($param) && !is_null($request->input($param))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle search with filters - optimized for filtering
     */
    private function handleSearchWithFilters(Request $request, $user, $userInformation)
    {
        $q = $request->input('q');
        $gender = $request->input('gender', $userInformation->search_preference ?? 'male');
        $interests = $request->input('interests', []);
        $languages = $request->input('languages', []);
        $relationGoals = $request->input('relation_goals', []);
        $latitude = $userInformation->latitude;
        $longitude = $userInformation->longitude;
        $radius = $request->input('radius', $userInformation->search_radius ?? 50);

        // Build Eloquent query for better performance and readability
        $query = User::with(['user_information'])
            ->whereHas('user_information', function($q) use ($gender) {
                $q->where('gender', $gender);
            })
            ->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            });

        // Apply text search filter
        if ($q) {
            $query->where(function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            });
        }

        // Apply profile filters
        $this->applyProfileFilters($query, $request);

        // Apply distance filter
        if ($latitude !== null && $longitude !== null) {
            $query->whereHas('user_information', function($q) use ($latitude, $longitude, $radius) {
                $q->whereRaw("(
                    6371 * acos(
                        cos(radians({$latitude})) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians({$longitude})) +
                        sin(radians({$latitude})) *
                        sin(radians(latitude))
                    )
                ) <= {$radius}");
            });
        }

        $results = $query->limit(50)->get();

        // Transform results with distance calculation
        $transformedResults = $results->map(function($user) use ($latitude, $longitude) {
            $userInfo = $user->user_information;
            if (!$userInfo) return null;

            // Calculate distance
            $distance = $this->calculateDistance(
                $latitude, $longitude,
                $userInfo->latitude, $userInfo->longitude
            );

            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'images' => $userInfo->images,
                'bio' => $userInfo->bio,
                'gender' => $userInfo->gender,
                'date_of_birth' => $userInfo->date_of_birth,
                'age' => $userInfo->age,
                'height' => $userInfo->height,
                'relation_goals' => $userInfo->relation_goals,
                'interests' => $userInfo->interests,
                'languages' => $userInfo->languages,
                'latitude' => $userInfo->latitude,
                'longitude' => $userInfo->longitude,
                'religion_id' => $userInfo->religion_id,
                'relationship_status_id' => $userInfo->relationship_status_id,
                'ethnicity_id' => $userInfo->ethnicity_id,
                'education_id' => $userInfo->education_id,
                'carrer_field_id' => $userInfo->carrer_field_id,
                'alkohol' => $userInfo->alkohol,
                'smoke' => $userInfo->smoke,
                'preffered_age' => $userInfo->preffered_age,
                'is_verified' => $userInfo->is_verified ?? false,
                'is_online' => $user->last_activity && $user->last_activity->diffInHours(now()) <= 3,
                'distance' => $distance,
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                
                // Add detailed attributes from UserInformation model accessors
                'relation_goals_details' => $userInfo->relation_goals_details,
                'interests_details' => $userInfo->interests_details,
                'ethnicity_details' => $userInfo->ethnicity_details,
                'languages_details' => $userInfo->languages_details,

            ];
        })->filter()->values();

        // For search results, sort by distance instead of compatibility score
        if ($latitude !== null && $longitude !== null) {
            $transformedResults = $transformedResults->sortBy('distance')->values();
        }

        return response()->json(['status' => true, 'data' => $transformedResults]);
    }

    /**
     * Apply profile-based filters to the query
     */
    private function applyProfileFilters($query, Request $request)
    {
        $filters = [
            'religion_id', 'relationship_status_id', 'ethnicity_id', 
            'education_id', 'carrer_field_id', 'alkohol', 'smoke'
        ];

        foreach ($filters as $filter) {
            $value = $request->input($filter);
            if ($value) {
                $query->whereHas('user_information', function($q) use ($filter, $value) {
                    $q->where($filter, $value);
                });
            }
        }

        // Age filters
        $minAge = $request->input('min_age');
        $maxAge = $request->input('max_age');
        if ($minAge || $maxAge) {
            $query->whereHas('user_information', function($q) use ($minAge, $maxAge) {
                if ($minAge) $q->where('age', '>=', $minAge);
                if ($maxAge) $q->where('age', '<=', $maxAge);
            });
        }

        // Height filters
        $minHeight = $request->input('min_height');
        $maxHeight = $request->input('max_height');
        if ($minHeight || $maxHeight) {
            $query->whereHas('user_information', function($q) use ($minHeight, $maxHeight) {
                if ($minHeight) $q->where('height', '>=', $minHeight);
                if ($maxHeight) $q->where('height', '<=', $maxHeight);
            });
        }
    }

    /**
     * Get user's profile completion status and tasks
     */
    public function profile_completion(Request $request)
    {
        $user = $request->user();
        
        // Get user information using model relationship
        $userInformation = $user ? $user->user_information : null;
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        // Use cache for profile completion - refreshes every 30 minutes
        $cacheKey = "profile_completion_user_{$user->id}";
        
        return $this->calculateProfileCompletion($user, $userInformation);
    }

    /**
     * Get detailed profile with compatibility analysis
     */
    public function profile_compatibility(Request $request)
    {
        $user = $request->user();
        $targetUserId = $request->input('target_user_id');
        
        // Get user information directly from raw table
        $userInformation = \DB::table('user_information')->where('user_id', $user->id)->first();
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        if (!$targetUserId) {
            return response()->json([
                'status' => false, 
                'message' => 'Target user ID is required.'
            ], 400);
        }

        // Get target user with full profile information
        $targetUser = \DB::table('users as u')
            ->join('user_information as ui', 'ui.user_id', '=', 'u.id')
            ->leftJoin('religions as r', 'ui.religion_id', '=', 'r.id')
            ->leftJoin('relationship_statuses as rs', 'ui.relationship_status_id', '=', 'rs.id')
            ->leftJoin('ethnicities as e', 'ui.ethnicity_id', '=', 'e.id')
            ->leftJoin('educations as ed', 'ui.education_id', '=', 'ed.id')
            ->leftJoin('career_fields as cf', 'ui.carrer_field_id', '=', 'cf.id')
            ->select([
                'u.id', 'u.name', 'u.email', 'u.image',
                'ui.*',
                'r.name as religion_name',
                'rs.name as relationship_status_name',
                'e.name as ethnicity_name',
                'ed.name as education_name',
                'cf.name as career_field_name',
                \DB::raw("(
                    6371 * acos(
                        cos(radians({$userInformation->latitude})) *
                        cos(radians(ui.latitude)) *
                        cos(radians(ui.longitude) - radians({$userInformation->longitude})) +
                        sin(radians({$userInformation->latitude})) *
                        sin(radians(ui.latitude))
                    )
                ) AS distance")
            ])
            ->where('u.id', $targetUserId)
            ->where('u.status', 1)
            ->first();

        if (!$targetUser) {
            return response()->json([
                'status' => false, 
                'message' => 'Target user not found or inactive.'
            ], 404);
        }

        // Calculate detailed compatibility analysis
        $compatibility = $this->calculateDetailedCompatibility($userInformation, $targetUser);

        return response()->json([
            'status' => true, 
            'data' => [
                'profile' => $targetUser,
                'compatibility' => $compatibility
            ]
        ]);
    }

    /**
     * Calculate detailed compatibility between two users
     */
    private function calculateDetailedCompatibility($userInfo, $targetProfile)
    {
        $compatibility = [
            'overall_score' => 0,
            'max_possible_score' => 100,
            'percentage' => 0,
            'details' => []
        ];

        $score = 0;

        // 1. Location Compatibility (15 points max)
        if ($targetProfile->distance !== null) {
            if ($targetProfile->distance <= 5) {
                $score += 15;
                $compatibility['details']['location'] = ['score' => 15, 'status' => 'excellent', 'message' => 'Very close distance'];
            } elseif ($targetProfile->distance <= 25) {
                $score += 10;
                $compatibility['details']['location'] = ['score' => 10, 'status' => 'good', 'message' => 'Reasonable distance'];
            } elseif ($targetProfile->distance <= 50) {
                $score += 5;
                $compatibility['details']['location'] = ['score' => 5, 'status' => 'fair', 'message' => 'Moderate distance'];
            } else {
                $compatibility['details']['location'] = ['score' => 0, 'status' => 'poor', 'message' => 'Long distance'];
            }
        }

        // 2. Age Compatibility (15 points max)
        if ($userInfo->age && $targetProfile->age) {
            $ageDiff = abs($userInfo->age - $targetProfile->age);
            if ($ageDiff <= 2) {
                $score += 15;
                $compatibility['details']['age'] = ['score' => 15, 'status' => 'excellent', 'message' => 'Perfect age match'];
            } elseif ($ageDiff <= 5) {
                $score += 10;
                $compatibility['details']['age'] = ['score' => 10, 'status' => 'good', 'message' => 'Good age compatibility'];
            } elseif ($ageDiff <= 10) {
                $score += 5;
                $compatibility['details']['age'] = ['score' => 5, 'status' => 'fair', 'message' => 'Acceptable age difference'];
            } else {
                $compatibility['details']['age'] = ['score' => 0, 'status' => 'poor', 'message' => 'Significant age difference'];
            }
        }

        // 3. Religion Compatibility (10 points max)
        if ($userInfo->religion_id && $targetProfile->religion_id) {
            if ($userInfo->religion_id == $targetProfile->religion_id) {
                $score += 10;
                $compatibility['details']['religion'] = ['score' => 10, 'status' => 'excellent', 'message' => 'Same religious beliefs'];
            } else {
                $compatibility['details']['religion'] = ['score' => 0, 'status' => 'different', 'message' => 'Different religious beliefs'];
            }
        }

        // 4. Lifestyle Compatibility - Alcohol & Smoking (10 points max)
        $lifestyleScore = 0;
        if ($userInfo->alkohol && $targetProfile->alkohol) {
            if ($userInfo->alkohol == $targetProfile->alkohol) {
                $lifestyleScore += 5;
            } elseif (
                ($userInfo->alkohol == 'drink_socially' && $targetProfile->alkohol == 'drink_frequently') ||
                ($userInfo->alkohol == 'drink_frequently' && $targetProfile->alkohol == 'drink_socially')
            ) {
                $lifestyleScore += 3;
            }
        }
        
        if ($userInfo->smoke && $targetProfile->smoke) {
            if ($userInfo->smoke == $targetProfile->smoke) {
                $lifestyleScore += 5;
            } elseif (
                ($userInfo->smoke == 'smoke_occasionally' && $targetProfile->smoke == 'smoke_regularly') ||
                ($userInfo->smoke == 'smoke_regularly' && $targetProfile->smoke == 'smoke_occasionally')
            ) {
                $lifestyleScore += 3;
            }
        }
        
        $score += $lifestyleScore;
        $compatibility['details']['lifestyle'] = [
            'score' => $lifestyleScore, 
            'status' => $lifestyleScore >= 8 ? 'excellent' : ($lifestyleScore >= 5 ? 'good' : 'fair'),
            'message' => 'Alcohol and smoking compatibility'
        ];

        // 5. Education & Career Compatibility (10 points max)
        $careerScore = 0;
        if ($userInfo->education_id && $targetProfile->education_id) {
            if ($userInfo->education_id == $targetProfile->education_id) {
                $careerScore += 5;
            } elseif (abs($userInfo->education_id - $targetProfile->education_id) <= 1) {
                $careerScore += 3;
            }
        }
        
        if ($userInfo->carrer_field_id && $targetProfile->carrer_field_id) {
            if ($userInfo->carrer_field_id == $targetProfile->carrer_field_id) {
                $careerScore += 5;
            }
        }
        
        $score += $careerScore;
        $compatibility['details']['career'] = [
            'score' => $careerScore,
            'status' => $careerScore >= 8 ? 'excellent' : ($careerScore >= 5 ? 'good' : 'fair'),
            'message' => 'Education and career compatibility'
        ];

        // 6. Interests, Goals, Languages (40 points max)
        $commonScore = 0;
        
        // Decode JSON fields
        $userGoals = is_string($userInfo->relation_goals) ? json_decode($userInfo->relation_goals, true) : ($userInfo->relation_goals ?? []);
        $targetGoals = is_string($targetProfile->relation_goals) ? json_decode($targetProfile->relation_goals, true) : ($targetProfile->relation_goals ?? []);
        
        $userInterests = is_string($userInfo->interests) ? json_decode($userInfo->interests, true) : ($userInfo->interests ?? []);
        $targetInterests = is_string($targetProfile->interests) ? json_decode($targetProfile->interests, true) : ($targetProfile->interests ?? []);
        
        $userLanguages = is_string($userInfo->languages) ? json_decode($userInfo->languages, true) : ($userInfo->languages ?? []);
        $targetLanguages = is_string($targetProfile->languages) ? json_decode($targetProfile->languages, true) : ($targetProfile->languages ?? []);

        if (!empty($userGoals) && !empty($targetGoals) && array_intersect($userGoals, $targetGoals)) {
            $commonScore += 15; // Relationship goals match
        }
        
        if (!empty($userInterests) && !empty($targetInterests) && array_intersect($userInterests, $targetInterests)) {
            $commonScore += 15; // Common interests
        }
        
        if (!empty($userLanguages) && !empty($targetLanguages) && array_intersect($userLanguages, $targetLanguages)) {
            $commonScore += 10; // Common languages
        }

        $score += $commonScore;
        $compatibility['details']['common_ground'] = [
            'score' => $commonScore,
            'status' => $commonScore >= 30 ? 'excellent' : ($commonScore >= 20 ? 'good' : 'fair'),
            'message' => 'Shared interests, goals, and languages'
        ];

        $compatibility['overall_score'] = $score;
        $compatibility['percentage'] = round(($score / 100) * 100, 1);
        
        // Overall compatibility rating
        if ($compatibility['percentage'] >= 80) {
            $compatibility['rating'] = 'Excellent Match';
            $compatibility['description'] = 'You have outstanding compatibility across multiple areas.';
        } elseif ($compatibility['percentage'] >= 60) {
            $compatibility['rating'] = 'Good Match';
            $compatibility['description'] = 'You share many important compatibilities.';
        } elseif ($compatibility['percentage'] >= 40) {
            $compatibility['rating'] = 'Fair Match';
            $compatibility['description'] = 'You have some areas of compatibility.';
        } else {
            $compatibility['rating'] = 'Limited Match';
            $compatibility['description'] = 'You may have some significant differences.';
        }

        return $compatibility;
    }

    /**
     * Calculate profile completion percentage and tasks
     */
    private function calculateProfileCompletion($user, $userInformation)
    {
        $tasks = [];
        $completedTasks = 0;
        $totalTasks = 7; // Total number of completion tasks

        // 1. Add Photos (Check if user has multiple images)
        $images = is_string($userInformation->images) ? json_decode($userInformation->images, true) : ($userInformation->images ?? []);
        $imageCount = is_array($images) ? count(array_filter($images)) : 0;
        
        $tasks[] = [
            'id' => 'add_photos',
            'title' => 'Add 2/3 photos',
            'description' => 'Let your personality shine through',
            'completed' => $imageCount >= 2,
            'action_text' => $imageCount >= 2 ? 'Complete' : 'Add photos',
            'completion_text' => $imageCount >= 2 ? '✓ Photos added' : "Add {$imageCount}/3 photos"
        ];
        if ($imageCount >= 2) $completedTasks++;

        // 2. Write About Me (Bio)
        $hasBio = !empty($userInformation->bio);
        $tasks[] = [
            'id' => 'write_bio',
            'title' => 'Write your "About me"',
            'description' => "We'll find better matches for you",
            'completed' => $hasBio,
            'action_text' => $hasBio ? 'Complete' : 'Write bio',
            'completion_text' => $hasBio ? '✓ Bio complete' : 'Write about yourself'
        ];
        if ($hasBio) $completedTasks++;

        // 3. Add Interests/Hobbies
        $interests = is_string($userInformation->interests) ? json_decode($userInformation->interests, true) : ($userInformation->interests ?? []);
        $hasInterests = is_array($interests);
        
        $tasks[] = [
            'id' => 'add_interests',
            'title' => 'Write more about you',
            'description' => 'Tell about your hobbies and passions',
            'completed' => $hasInterests,
            'action_text' => $hasInterests ? 'Complete' : 'Add interests',
            'completion_text' => $hasInterests ? '✓ Interests added' : 'Add your interests'
        ];
        if ($hasInterests) $completedTasks++;

        // 4. Fill Profile Info (Age, location, basic info)
        $hasBasicInfo = !empty($userInformation->age) && 
                       !empty($userInformation->gender) && 
                       !empty($userInformation->latitude) && 
                       !empty($userInformation->longitude);
        
        $tasks[] = [
            'id' => 'fill_profile_info',
            'title' => 'Fill up your profile info',
            'description' => "We'll find better matches for you",
            'completed' => $hasBasicInfo,
            'action_text' => $hasBasicInfo ? 'Complete' : 'Fill info',
            'completion_text' => $hasBasicInfo ? '✓ Profile info complete' : 'Add basic information'
        ];
        if ($hasBasicInfo) $completedTasks++;

        // 5. Get Verified (Check actual verification status)
        $isVerified = $userInformation->is_verified ?? false;
        $tasks[] = [
            'id' => 'get_verified',
            'title' => 'Get verified',
            'description' => 'Verified members get 30% more likes',
            'completed' => $isVerified,
            'action_text' => $isVerified ? 'Verified' : 'Get verified',
            'completion_text' => $isVerified ? '✓ Account verified' : 'Verify your account'
        ];
        if ($isVerified) $completedTasks++;

        // 6. Join Communities (Interests/Groups)
        $relationGoals = is_string($userInformation->relation_goals) ? json_decode($userInformation->relation_goals, true) : ($userInformation->relation_goals ?? []);
        $languages = is_string($userInformation->languages) ? json_decode($userInformation->languages, true) : ($userInformation->languages ?? []);
        
        $hasCommunitiesInfo = (is_array($relationGoals) && count($relationGoals) >= 1) && 
                             (is_array($languages) && count($languages) >= 1);
        
        $tasks[] = [
            'id' => 'join_communities',
            'title' => 'Join 3 communities',
            'description' => 'Show others what interests you',
            'completed' => $hasCommunitiesInfo,
            'action_text' => $hasCommunitiesInfo ? 'Complete' : 'Join communities',
            'completion_text' => $hasCommunitiesInfo ? '✓ Communities joined' : 'Join interest communities'
        ];
        if ($hasCommunitiesInfo) $completedTasks++;

        // 7. Upgrade to Premium (Always show as incomplete for monetization)
        $isPremium = false; // Check user subscription status
        $tasks[] = [
            'id' => 'upgrade_premium',
            'title' => 'Upgrade to Premium',
            'description' => 'Unlock all benefits',
            'completed' => $isPremium,
            'action_text' => $isPremium ? 'Premium' : 'Upgrade',
            'completion_text' => $isPremium ? '✓ Premium member' : 'Unlock premium features'
        ];
        if ($isPremium) $completedTasks++;

        // Calculate completion percentage
        $completionPercentage = round(($completedTasks / $totalTasks) * 100);

        $completion = [
            'completion_percentage' => $completionPercentage,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks,
            'title' => 'Profile completion',
            'subtitle' => 'Men with fully filled profiles receive twice as many matches.',
            'tasks' => $tasks,
            'benefits' => [
                'more_matches' => $completionPercentage >= 80,
                'better_visibility' => $completionPercentage >= 60,
                'algorithm_boost' => $completionPercentage >= 40
            ]
        ];

        return response()->json([
            'status' => true,
            'data' => $completion
        ]);
    }

    /**
     * Get detailed user profile information by user ID
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function details(Request $request, $id)
    {
        $user = $request->user();
        $targetUserId = $id;

        // Validate required parameter
        if (!$targetUserId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID is required.'
            ], 400);
        }

        // Get current user information using model relationship
        $userInformation = $user->user_information;
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        try {
            // Get target user with complete profile information using Eloquent models
            $targetUser = User::with([
                'user_information.religion',
                'user_information.relationshipStatus', 
                'user_information.ethnicity',
                'user_information.education',
                'user_information.careerField'
            ])
            ->where('id', $targetUserId)
            ->where('status', 1)
            ->first();

            if (!$targetUser || !$targetUser->user_information) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found or inactive.'
                ], 404);
            }

            // Track profile visit (async to not block response)
            try {
                ProfileVisitor::trackVisit($user->id, $targetUserId);
                
                // Clear the profile visitors cache for the target user so the visit appears immediately
                Cache::forget("profile_visitors_user_{$targetUserId}");
            } catch (\Exception $e) {
                // Log the error but don't fail the request
                \Log::error('Failed to track profile visit: ' . $e->getMessage());
            }

            // Calculate distance from current user
            $distance = $this->calculateDistance(
                $userInformation->latitude,
                $userInformation->longitude,
                $targetUser->user_information->latitude,
                $targetUser->user_information->longitude
            );

            // Prepare profile data with all information
            $profileData = $targetUser;

            // Calculate compatibility with current user
            $compatibility = $this->calculateDetailedCompatibility($userInformation, (object) $profileData);

            // Check if users have interacted before using models
            $interactionStatus = $this->getInteractionStatusUsingModels($user->id, $targetUserId);

            return response()->json([
                'status' => true,
                'data' => [
                    'profile' => $profileData,
                    'compatibility' => $compatibility,
                    'interaction_status' => $interactionStatus,
                    'distance' => $distance ? round($distance, 1) . ' km' : 'Unknown'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user details.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        if (is_null($lat1) || is_null($lng1) || is_null($lat2) || is_null($lng2)) {
            return null;
        }

        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get interaction status between two users using Eloquent models
     */
    private function getInteractionStatusUsingModels($currentUserId, $targetUserId)
    {
        // Check if current user has swiped on target user
        $myInteraction = UserInteraction::where('user_id', $currentUserId)
            ->where('target_user_id', $targetUserId)
            ->first();

        // Check if target user has swiped on current user
        $theirInteraction = UserInteraction::where('user_id', $targetUserId)
            ->where('target_user_id', $currentUserId)
            ->first();

        // Check if they are matched
        $isMatched = UserMatch::where(function($query) use ($currentUserId, $targetUserId) {
                $query->where('user_id', min($currentUserId, $targetUserId))
                      ->where('target_user_id', max($currentUserId, $targetUserId));
            })
            ->whereNull('deleted_at')
            ->exists();

        // Check if either user has blocked the other
        $isBlocked = UserBlock::where(function($query) use ($currentUserId, $targetUserId) {
                $query->where('user_id', $currentUserId)
                      ->where('blocked_user_id', $targetUserId);
            })
            ->orWhere(function($query) use ($currentUserId, $targetUserId) {
                $query->where('user_id', $targetUserId)
                      ->where('blocked_user_id', $currentUserId);
            })
            ->exists();

        return [
            'my_action' => $myInteraction ? $myInteraction->action : null,
            'their_action' => $theirInteraction ? $theirInteraction->action : null,
            'is_matched' => $isMatched,
            'is_blocked' => $isBlocked,
            'can_interact' => !$isBlocked,
            'interaction_date' => $myInteraction ? $myInteraction->created_at : null
        ];
    }

    /**
     * Enrich profile data with detailed information for interests, languages, etc.
     * (Deprecated: Now using UserInformation model accessors)
     */
    private function enrichProfileData($profile)
    {
        // This method is now deprecated as we use model accessors
        // in UserInformation model for better data handling
        return $profile;
    }

    /**
     * Get interaction status between two users
     * (Deprecated: Use getInteractionStatusUsingModels instead)
     */
    private function getInteractionStatus($currentUserId, $targetUserId)
    {
        // Redirect to model-based method
        return $this->getInteractionStatusUsingModels($currentUserId, $targetUserId);
    }

    /**
     * Get daily soulmates - most compatible people (premium feature)
     * This list is updated daily and shows only the highest compatibility matches
     * If empty results, will clear cache and retry up to 3 times
     */
    public function soulmates(Request $request)
    {
        $user = $request->user();
        
        // Get user information using model relationship
        $userInformation = $user ? $user->user_information : null;
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        // Check if user has premium subscription (optional - remove if not needed)
        // if (!$user->hasActiveSubscription()) {
        //     return response()->json([
        //         'status' => false, 
        //         'message' => 'Soulmates feature requires premium subscription.'
        //     ], 403);
        // }

        $today = now()->format('Y-m-d');
        $cacheKey = "soulmates_user_{$user->id}_date_{$today}";
        $maxRetries = 3;
        $currentTry = 0;
        
        while ($currentTry < $maxRetries) {
            // Try to get cached result first (except on retries)
            $result = null;
            if ($currentTry === 0) {
                $result = Cache::get($cacheKey);
            }
            
            // If no cached result or we're retrying, generate new data
            if (!$result) {
                $result = Cache::remember($cacheKey, now()->addDay(), function() use ($request, $user, $userInformation) {
                    return $this->generateSoulmates($request, $user, $userInformation);
                });
            }
            
            // Check if result has data
            if ($this->hasSoulmateData($result)) {
                if ($currentTry > 0) {
                    \Log::info("Soulmates found for user {$user->id} after {$currentTry} retries");
                }
                return $result;
            }
            
            // If empty and we haven't exceeded retries, clear cache and try again
            $currentTry++;
            if ($currentTry < $maxRetries) {
                Cache::forget($cacheKey);
                \Log::info("Soulmates empty for user {$user->id}, clearing cache and retrying {$currentTry}/{$maxRetries}");
                
                // Optional: Add small delay between retries to allow for data changes
                usleep(200000); // 0.2 second delay
            } else {
                \Log::warning("Soulmates failed after {$maxRetries} attempts for user {$user->id}");
            }
        }
        
        // If all retries failed, return empty result with message
        return response()->json([
            'status' => true, 
            'data' => [], 
            'message' => 'No soulmates found at this time. Please try again later or expand your search preferences.'
        ]);
    }

    /**
     * Generate soulmates list with highest compatibility scores
     */
    private function generateSoulmates(Request $request, $user, $userInformation)
    {
        // Get current user's preferences and location
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;
        $searchRadius = ($userInformation->search_radius ?? 1000) * 2; // Expand radius for soulmates
        $searchPreference = $userInformation->search_preference ?? 'male';

        // Build query for high-quality profiles only
        $query = User::with(['user_information'])
            ->whereHas('user_information', function($q) use ($searchPreference) {
                $q->where('gender', $searchPreference)
                  // Only include users with complete profiles for soulmates
                  ->whereNotNull('bio')
                  ->whereNotNull('age')
                  ->whereNotNull('religion_id')
                  ->whereNotNull('education_id')
                //   ->whereNotNull('carrer_field_id')
                  ->where('bio', '!=', '')
                  ->where('age', '>', 0);
            })
            ->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            })
            // Exclude users already swiped on (for fresh soulmates)
            ->whereDoesntHave('receivedInteractions', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        // Apply expanded distance filter for soulmates
        if ($currentLat != 0 && $currentLng != 0) {
            $query->whereHas('user_information', function($q) use ($currentLat, $currentLng, $searchRadius) {
                $q->whereRaw("(
                    6371 * acos(
                        cos(radians({$currentLat})) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians({$currentLng})) +
                        sin(radians({$currentLat})) *
                        sin(radians(latitude))
                    )
                ) <= {$searchRadius}");
            });
        }
        
        $results = $query->limit(1000)->get();
         // Get more candidates for better filtering

        // Transform results and add distance calculation
        $transformedResults = $results->map(function($user) use ($currentLat, $currentLng) {
            $userInfo = $user->user_information;
            if (!$userInfo) return null;

            // Calculate distance
            $distance = $this->calculateDistance(
                $currentLat, $currentLng,
                $userInfo->latitude, $userInfo->longitude
            );

            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'images' => $userInfo->images,
                'bio' => $userInfo->bio,
                'gender' => $userInfo->gender,
                'date_of_birth' => $userInfo->date_of_birth,
                'age' => $userInfo->age,
                'height' => $userInfo->height,
                'relation_goals' => $userInfo->relation_goals,
                'interests' => $userInfo->interests,
                'languages' => $userInfo->languages,
                'latitude' => $userInfo->latitude,
                'longitude' => $userInfo->longitude,
                'religion_id' => $userInfo->religion_id,
                'relationship_status_id' => $userInfo->relationship_status_id,
                'ethnicity_id' => $userInfo->ethnicity_id,
                'education_id' => $userInfo->education_id,
                'carrer_field_id' => $userInfo->carrer_field_id,
                'alkohol' => $userInfo->alkohol,
                'smoke' => $userInfo->smoke,
                'preffered_age' => $userInfo->preffered_age,
                'is_zodiac_sign_matter' => $userInfo->is_zodiac_sign_matter,
                'is_food_preference_matter' => $userInfo->is_food_preference_matter,
                'country_code' => $userInfo->country_code,
                'is_verified' => $userInfo->is_verified ?? false,
                'is_online' => $user->last_activity && $user->last_activity->diffInHours(now()) <= 3,
                'distance' => $distance,
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                
                // Add detailed attributes
                'relation_goals_details' => $userInfo->relation_goals_details,
                'interests_details' => $userInfo->interests_details,
                'ethnicity_details' => $userInfo->ethnicity_details,
                'languages_details' => $userInfo->languages_details,
            ];
        })->filter()->values();

        // Get current user's decoded preferences for enhanced compatibility scoring
        $userRelationGoals = $userInformation->relation_goals ?? [];
        $userInterests = $userInformation->interests ?? [];
        $userLanguages = $userInformation->languages ?? [];

        // Decode JSON fields for current user
        if (is_string($userRelationGoals)) {
            $userRelationGoals = json_decode($userRelationGoals, true);
            if (is_string($userRelationGoals)) {
                $userRelationGoals = json_decode($userRelationGoals, true) ?? [];
            }
        }
        $userRelationGoals = is_array($userRelationGoals) ? $userRelationGoals : [];

        if (is_string($userInterests)) {
            $userInterests = json_decode($userInterests, true);
            if (is_string($userInterests)) {
                $userInterests = json_decode($userInterests, true) ?? [];
            }
        }
        $userInterests = is_array($userInterests) ? $userInterests : [];

        if (is_string($userLanguages)) {
            $userLanguages = json_decode($userLanguages, true);
            if (is_string($userLanguages)) {
                $userLanguages = json_decode($userLanguages, true) ?? [];
            }
        }
        $userLanguages = is_array($userLanguages) ? $userLanguages : [];

        // Calculate ENHANCED compatibility scores for soulmates
        $scoredResults = $transformedResults->map(function($profile) use ($userInformation, $userRelationGoals, $userInterests, $userLanguages, $searchRadius) {
            $score = 0;

            // Get profile's preferences
            $profileRelationGoals = $profile->relation_goals;
            $profileInterests = $profile->interests;
            $profileLanguages = $profile->languages;

            // Decode JSON fields for profile
            if (is_string($profileRelationGoals)) {
                $profileRelationGoals = json_decode($profileRelationGoals, true);
                if (is_string($profileRelationGoals)) {
                    $profileRelationGoals = json_decode($profileRelationGoals, true) ?? [];
                }
            }
            $profileRelationGoals = is_array($profileRelationGoals) ? $profileRelationGoals : [];

            if (is_string($profileInterests)) {
                $profileInterests = json_decode($profileInterests, true);
                if (is_string($profileInterests)) {
                    $profileInterests = json_decode($profileInterests, true) ?? [];
                }
            }
            $profileInterests = is_array($profileInterests) ? $profileInterests : [];

            if (is_string($profileLanguages)) {
                $profileLanguages = json_decode($profileLanguages, true);
                if (is_string($profileLanguages)) {
                    $profileLanguages = json_decode($profileLanguages, true) ?? [];
                }
            }
            $profileLanguages = is_array($profileLanguages) ? $profileLanguages : [];

            // ENHANCED SOULMATE SCORING (Higher thresholds and more criteria)
            
            // 1. Core Compatibility (Higher weights for soulmates)
            $rgOverlap = !empty($userRelationGoals) && !empty($profileRelationGoals) ? array_values(array_intersect($userRelationGoals, $profileRelationGoals)) : [];
            $langOverlap = !empty($userLanguages) && !empty($profileLanguages) ? array_values(array_intersect($userLanguages, $profileLanguages)) : [];
            $intOverlap = !empty($userInterests) && !empty($profileInterests) ? array_values(array_intersect($userInterests, $profileInterests)) : [];

            if (!empty($rgOverlap)) {
                $score += 10; // Increased from 3 - relationship goals are crucial for soulmates
            }
            if (!empty($langOverlap)) {
                $score += 8; // Increased from 2 - communication is key
            }
            if (!empty($intOverlap)) {
                $score += 6; // Increased from 1 - shared interests create bonds
            }

            // 2. Essential Compatibility Requirements for Soulmates
            
            // Religion Compatibility (Higher scoring)
            if ($userInformation->religion_id && $profile->religion_id) {
                if ($userInformation->religion_id == $profile->religion_id) {
                    $score += 15; // Increased from 3 - spiritual alignment
                }
            }

            // Lifestyle Perfect Match (Alcohol & Smoking)
            if ($userInformation->alkohol && $profile->alkohol) {
                if ($userInformation->alkohol == $profile->alkohol) {
                    $score += 10; // Perfect lifestyle match
                } elseif (
                    ($userInformation->alkohol == 'drink_socially' && $profile->alkohol == 'drink_frequently') ||
                    ($userInformation->alkohol == 'drink_frequently' && $profile->alkohol == 'drink_socially')
                ) {
                    $score += 5; // Compatible but not perfect
                }
            }

            if ($userInformation->smoke && $profile->smoke) {
                if ($userInformation->smoke == $profile->smoke) {
                    $score += 10; // Perfect smoking match
                } elseif (
                    ($userInformation->smoke == 'smoke_occasionally' && $profile->smoke == 'smoke_regularly') ||
                    ($userInformation->smoke == 'smoke_regularly' && $profile->smoke == 'smoke_occasionally')
                ) {
                    $score += 5; // Compatible but not perfect
                }
            }

            // 3. Education & Intellectual Compatibility
            if ($userInformation->education_id && $profile->education_id) {
                if ($userInformation->education_id == $profile->education_id) {
                    $score += 12; // Same education level
                } elseif (abs($userInformation->education_id - $profile->education_id) <= 1) {
                    $score += 8; // Similar education level
                }
            }

            // Career Field Synergy
            if ($userInformation->carrer_field_id && $profile->carrer_field_id) {
                if ($userInformation->carrer_field_id == $profile->carrer_field_id) {
                    $score += 10; // Same career field - understanding work life
                }
            }

            // 4. Age & Life Stage Compatibility (Stricter for soulmates)
            if ($userInformation->age && $profile->age) {
                $ageDiff = abs($userInformation->age - $profile->age);
                if ($ageDiff <= 2) {
                    $score += 15; // Perfect age match
                } elseif ($ageDiff <= 3) {
                    $score += 12; // Very close age
                } elseif ($ageDiff <= 5) {
                    $score += 8; // Close age
                } elseif ($ageDiff <= 7) {
                    $score += 4; // Acceptable age difference
                }
                // Beyond 7 years gets no points for soulmates
            }

            // Age preference perfect matching
            if ($userInformation->preffered_age && $profile->age) {
                if (preg_match('/(\d+)-(\d+)/', $userInformation->preffered_age, $matches)) {
                    $minAge = (int)$matches[1];
                    $maxAge = (int)$matches[2];
                    if ($profile->age >= $minAge && $profile->age <= $maxAge) {
                        $score += 8; // Profile age perfectly fits user's preference
                    }
                }
            }

            // 5. Physical Compatibility
            if ($userInformation->height && $profile->height) {
                $heightDiff = abs($userInformation->height - $profile->height);
                if ($heightDiff <= 5) { // Within 5cm - very close
                    $score += 5;
                } elseif ($heightDiff <= 10) { // Within 10cm - close
                    $score += 3;
                }
            }

            // 6. Distance Scoring (Proximity matters for soulmates)
            if ($profile->distance <= 15) {
                $score += 20; // Very close - easy to meet
            } elseif ($profile->distance <= 30) {
                $score += 15; // Close enough for regular dates
            } elseif ($profile->distance <= 50) {
                $score += 10; // Reasonable distance
            } elseif ($profile->distance <= $searchRadius) {
                $score += 5; // Within extended search radius
            }

            // 7. Special Preferences Alignment
            if ($userInformation->is_zodiac_sign_matter && $profile->is_zodiac_sign_matter) {
                $score += 5; // Both care about zodiac compatibility
            }

            if ($userInformation->is_food_preference_matter && $profile->is_food_preference_matter) {
                $score += 5; // Both care about food preferences
            }

            // 8. Relationship Status Harmony
            if ($userInformation->relationship_status_id && $profile->relationship_status_id) {
                if ($userInformation->relationship_status_id == $profile->relationship_status_id) {
                    $score += 8; // Same relationship mindset
                }
            }

            // 9. Profile Completeness Bonus (Both should have rich profiles)
            $userFieldCount = 0;
            $profileFieldCount = 0;
            
            $checkFields = ['bio', 'religion_id', 'relationship_status_id', 'ethnicity_id', 'education_id', 'carrer_field_id', 'alkohol', 'smoke', 'age', 'height'];
            
            foreach ($checkFields as $field) {
                if (!empty($userInformation->$field)) $userFieldCount++;
                if (!empty($profile->$field)) $profileFieldCount++;
            }
            
            // Higher bonus for soulmates - both need complete profiles
            if ($userFieldCount >= 8 && $profileFieldCount >= 8) {
                $score += 15; // Both profiles are very comprehensive
            } elseif ($userFieldCount >= 6 && $profileFieldCount >= 6) {
                $score += 10; // Both profiles are reasonably complete
            }

            $profile->soulmate_score = $score;
            $profile->soulmate_details = [
                'total_score' => $score,
                'relation_goals_match' => !empty($rgOverlap),
                'language_match' => !empty($langOverlap),
                'interests_match' => !empty($intOverlap),
                'religion_match' => ($userInformation->religion_id && $profile->religion_id && $userInformation->religion_id == $profile->religion_id),
                'lifestyle_match' => (
                    ($userInformation->alkohol && $profile->alkohol && $userInformation->alkohol == $profile->alkohol) &&
                    ($userInformation->smoke && $profile->smoke && $userInformation->smoke == $profile->smoke)
                ),
                'education_match' => ($userInformation->education_id && $profile->education_id && $userInformation->education_id == $profile->education_id),
                'age_perfect_match' => ($userInformation->age && $profile->age && abs($userInformation->age - $profile->age) <= 3),
                'close_distance' => ($profile->distance <= 30),
                'compatibility_percentage' => min(100, round(($score / 150) * 100, 1))
            ];
            
            return $profile;
        });

        // Filter for TRUE SOULMATES - only the highest compatibility scores
        $soulmates = $scoredResults
            ->filter(function($profile) {
                // Only include profiles with soulmate score >= 60 (high threshold)
                return $profile->soulmate_score >= 60;
            })
            ->shuffle() // Randomize order before sorting to add variety
            // ->sortByDesc('soulmate_score')
            ->take(15) // Limit to top 15 soulmates per day
            ->values();

        // If no high-scoring soulmates found, try with lower threshold
        if ($soulmates->isEmpty()) {
            $soulmates = $scoredResults
                ->filter(function($profile) {
                    // Lower threshold fallback
                    return $profile->soulmate_score >= 40;
                })
                ->shuffle()
                ->take(10) // Fewer results for lower threshold
                ->values();
        }

        // If still empty, try even lower threshold
        if ($soulmates->isEmpty()) {
            $soulmates = $scoredResults
                ->filter(function($profile) {
                    // Very low threshold fallback
                    return $profile->soulmate_score >= 20;
                })
                ->shuffle()
                ->take(5) // Even fewer results for very low threshold
                ->values();
        }

        return response()->json(['status' => true, 'data' => $soulmates]);
    }

    /**
     * Check if soulmate data is valid and non-empty
     * @param mixed $result
     * @return bool
     */
    private function hasSoulmateData($result)
    {
        // If result is null or false, no data
        if (!$result) {
            return false;
        }
        
        // If it's a JsonResponse, decode it to check the data
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            $data = $result->getData(true);
            if (isset($data['status']) && $data['status'] === true && isset($data['data'])) {
                return is_array($data['data']) && count($data['data']) > 0;
            }
            return false;
        }
        
        // If it's an array, check if it has data
        if (is_array($result)) {
            return count($result) > 0;
        }
        
        // If it's an object, try to access data property
        if (is_object($result) && isset($result->data)) {
            return is_array($result->data) && count($result->data) > 0;
        }
        
        return false;
    }

    /**
     * Clear soulmates cache for a specific user or all users
     */
    public function clearSoulmatesCache($userId = null)
    {
        if ($userId) {
            $today = now()->format('Y-m-d');
            $cacheKey = "soulmates_user_{$userId}_date_{$today}";
            Cache::forget($cacheKey);
        } else {
            // Clear all soulmates caches (admin function)
            $pattern = "soulmates_user_*";
            // Note: This requires Redis or a cache store that supports pattern deletion
            Cache::flush(); // Alternative: clear all cache (use with caution)
        }
    }

    /**
     * Clear recommendations cache for a specific user
     * Call this method when user profile data is updated
     */
    public function clearRecommendationsCache($userId = null)
    {
        if ($userId) {
            $cacheKey = "recommendations_user_{$userId}";
            Cache::forget($cacheKey);
            
            // Also clear soulmates cache when profile changes
            $this->clearSoulmatesCache($userId);
            
            // Also clear profile completion cache when profile changes
            $this->clearProfileCompletionCache($userId);
        } else {
            // Clear all recommendation caches (use with caution)
            Cache::flush();
        }
    }

    /**
     * Clear profile completion cache for a specific user
     * Call this method when user profile data is updated
     */
    public function clearProfileCompletionCache($userId = null)
    {
        if ($userId) {
            $cacheKey = "profile_completion_user_{$userId}";
            Cache::forget($cacheKey);
        } else {
            // Clear all profile completion caches (admin function)
            $pattern = "profile_completion_user_*";
            // Note: This requires Redis or a cache store that supports pattern deletion
            Cache::flush(); // Alternative: clear all cache (use with caution)
        }
    }

    /**
     * Get profile visitors - last 20 visitors with 24-hour cache
     */
    public function profile_visitors(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated.'
            ], 401);
        }

        // Use 24-hour cache for profile visitors
        $cacheKey = "profile_visitors_user_{$user->id}";
        
        return Cache::remember($cacheKey, 86400, function() use ($user) { // 86400 seconds = 24 hours
            return $this->generateProfileVisitors($user);
        });
    }

    /**
     * Generate profile visitors list - simple version
     */
    private function generateProfileVisitors($user)
    {
        try {
            // Get last 20 visitors using the model scope
            $visitors = ProfileVisitor::recentVisitors($user->id, 20)->get();

            // Transform visitor data - only basic info
            $transformedVisitors = $visitors->map(function($visit) {
                $visitor = $visit->visitor;
                $visitorInfo = $visitor ? $visitor->user_information : null;

                if (!$visitor) {
                    return null;
                }

                return [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'image' => $visitor->image,
                    'is_verified' => $visitorInfo ? ($visitorInfo->is_verified ?? false) : false,
                    'visited_at' => $visit->visited_at->diffForHumans()
                ];
            })->filter()->values();

            return response()->json([
                'status' => true,
                'data' => [
                    'visitors' => $transformedVisitors,
                    'total_visitors' => $transformedVisitors->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve profile visitors.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}