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

class ProfileController extends Controller
{
    public function recommendations(Request $request)
    {
        $user = $request->user();
        
        // Get user information directly from raw table
        $userInformation = $user ? $user->user_information : null;
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        // Get current user's preferences and location
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;
        $searchRadius = $userInformation->search_radius ?? 100; // Use user's actual search radius, default 100km
        $searchPreference = $userInformation->search_preference ?? 'male';

        $searchRadius = 350; //temporarily increased to include the 307km user for testing
        
        // Debug: Log current user coordinates and search settings (remove this later)
        \Log::info('Recommendations Debug', [
            'current_user_id' => $user->id,
            'current_lat' => $currentLat,
            'current_lng' => $currentLng,
            'search_radius' => $searchRadius,
            'search_preference' => $searchPreference
        ]);

        // Start with a simple query first to get all potential matches
        $query = \DB::table('users as u')
            ->join('user_information as ui', 'ui.user_id', '=', 'u.id')
            ->select([
                'u.id',
                'u.name',
                'u.email',
                'u.image',
                'ui.bio',
                'ui.gender',
                'ui.date_of_birth',
                'ui.age',
                'ui.height',
                'ui.relation_goals',
                'ui.interests',
                'ui.languages',
                'ui.latitude',
                'ui.longitude',
                'ui.religion_id',
                'ui.relationship_status_id',
                'ui.ethnicity_id',
                'ui.education_id',
                'ui.carrer_field_id',
                'ui.alkohol',
                'ui.smoke',
                'ui.preffered_age',
                'ui.is_zodiac_sign_matter',
                'ui.is_food_preference_matter',
                'ui.country_code',
                \DB::raw("(
                    6371 * acos(
                        cos(radians({$currentLat})) *
                        cos(radians(ui.latitude)) *
                        cos(radians(ui.longitude) - radians({$currentLng})) +
                        sin(radians({$currentLat})) *
                        sin(radians(ui.latitude))
                    )
                ) AS distance")
            ])
            ->where('u.id', '!=', $user->id)
            ->where('u.status', 1);

        // Apply gender filter - always filter by gender preference (no 'both' option)
        $query->where('ui.gender', $searchPreference);

        // Apply distance filter if coordinates are available
        if ($currentLat != 0 && $currentLng != 0) {
            $query->whereRaw("(
                6371 * acos(
                    cos(radians({$currentLat})) *
                    cos(radians(ui.latitude)) *
                    cos(radians(ui.longitude) - radians({$currentLng})) +
                    sin(radians({$currentLat})) *
                    sin(radians(ui.latitude))
                )
            ) <= {$searchRadius}");
        }
        
        $results = $query->orderBy(\DB::raw("(
            6371 * acos(
                cos(radians({$currentLat})) *
                cos(radians(ui.latitude)) *
                cos(radians(ui.longitude) - radians({$currentLng})) +
                sin(radians({$currentLat})) *
                sin(radians(ui.latitude))
            )
        )"))->limit(50)->get();

        // Debug: Log found users (remove this later)
        \Log::info('Found Users Debug', [
            'total_found' => $results->count(),
            'users' => $results->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'gender' => $user->gender,
                    'distance' => $user->distance,
                    'lat' => $user->latitude,
                    'lng' => $user->longitude
                ];
            })->toArray()
        ]);

        // Decode current user's values robustly (handle double-encoded JSON strings)
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

        // Calculate enhanced match scores including new profile fields
        $scoredResults = $results->map(function($profile) use ($userInformation, $userRelationGoals, $userInterests, $userLanguages, $searchRadius) {
            $score = 0;

            // Get profile's preferences - handle double-encoded JSON strings
            $profileRelationGoals = $profile->relation_goals;
            $profileInterests = $profile->interests;
            $profileLanguages = $profile->languages;

            // First decode removes outer quotes, second decode parses the JSON array
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

            // Core compatibility scoring (existing)
            $rgOverlap = !empty($userRelationGoals) && !empty($profileRelationGoals) ? array_values(array_intersect($userRelationGoals, $profileRelationGoals)) : [];
            $langOverlap = !empty($userLanguages) && !empty($profileLanguages) ? array_values(array_intersect($userLanguages, $profileLanguages)) : [];
            $intOverlap = !empty($userInterests) && !empty($profileInterests) ? array_values(array_intersect($userInterests, $profileInterests)) : [];

            if (!empty($rgOverlap)) {
                $score += 3; // Increased from 2 - relationship goals are very important
            }
            if (!empty($langOverlap)) {
                $score += 2; // Language compatibility
            }
            if (!empty($intOverlap)) {
                $score += 1; // Shared interests
            }
            if ($profile->distance <= $searchRadius) {
                $score += 4; // Increased from 3 - proximity is crucial
            }

            // NEW ENHANCED COMPATIBILITY SCORING

            // 1. Religion Compatibility (High importance for many users)
            if ($userInformation->religion_id && $profile->religion_id) {
                if ($userInformation->religion_id == $profile->religion_id) {
                    $score += 3; // Same religion
                }
            }

            // 2. Lifestyle Compatibility - Alcohol
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

            // 3. Lifestyle Compatibility - Smoking
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

            // 4. Education Level Compatibility
            if ($userInformation->education_id && $profile->education_id) {
                if ($userInformation->education_id == $profile->education_id) {
                    $score += 2; // Same education level
                } elseif (abs($userInformation->education_id - $profile->education_id) <= 1) {
                    $score += 1; // Similar education level (adjacent IDs)
                }
            }

            // 5. Age Compatibility & Age Preference Matching
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
                // Parse preferred age range (e.g., "25-35", "18-25")
                if (preg_match('/(\d+)-(\d+)/', $userInformation->preffered_age, $matches)) {
                    $minAge = (int)$matches[1];
                    $maxAge = (int)$matches[2];
                    if ($profile->age >= $minAge && $profile->age <= $maxAge) {
                        $score += 2; // Profile age fits user's preference
                    }
                }
            }

            // 6. Height Compatibility (if both have height preferences)
            if ($userInformation->height && $profile->height) {
                $heightDiff = abs($userInformation->height - $profile->height);
                if ($heightDiff <= 10) { // Within 10cm
                    $score += 1;
                }
            }

            // 7. Career Field Compatibility
            if ($userInformation->carrer_field_id && $profile->carrer_field_id) {
                if ($userInformation->carrer_field_id == $profile->carrer_field_id) {
                    $score += 2; // Same career field
                }
            }

            // 8. Relationship Status Compatibility
            if ($userInformation->relationship_status_id && $profile->relationship_status_id) {
                if ($userInformation->relationship_status_id == $profile->relationship_status_id) {
                    $score += 1; // Same relationship status
                }
            }

            // 9. Special Compatibility Preferences
            if ($userInformation->is_zodiac_sign_matter && $profile->is_zodiac_sign_matter) {
                $score += 1; // Both care about zodiac signs
            }

            if ($userInformation->is_food_preference_matter && $profile->is_food_preference_matter) {
                $score += 1; // Both care about food preferences
            }

            // 10. Bonus for Complete Profiles
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
        })->sortByDesc('match_score')->values();

        return response()->json(['status' => true, 'data' => $scoredResults]);
    }

    /**
     * Search profiles by query and optional filters.
     * Accepts:
     * - q: text to search by name or email
     * - gender: male|female|both
     * - interests[]: array of interest ids
     * - languages[]: array of language ids
     * - relation_goals[]: array of relation goal ids
     * - lat, lng, radius: to filter by distance (km)
     */
    public function search(Request $request)
    {
        $user = $request->user();
        
        // Get user information directly from raw table
        $userInformation = $user ? $user->user_information : null;
        
        if (!$userInformation) {
            return response()->json([
                'status' => false, 
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        $q = $request->input('q');
        $gender = $request->input('gender', 'male'); // Default to 'male', no 'both' option
        $interests = $request->input('interests', []);
        $languages = $request->input('languages', []);
        $relationGoals = $request->input('relation_goals', []);
        // Use authenticated user's location instead of client coordinates
        $latitude = $userInformation->latitude;
        $longitude = $userInformation->longitude;
        $radius = $request->input('radius', $userInformation->search_radius ?? 50);

        // Build raw SQL for speed with bindings
        $selectBase = "u.id,u.name,u.email,u.image,ui.bio,ui.gender,ui.date_of_birth,ui.age,ui.height,ui.relation_goals,ui.interests,ui.languages,ui.latitude,ui.longitude,ui.religion_id,ui.relationship_status_id,ui.ethnicity_id,ui.education_id,ui.carrer_field_id,ui.alkohol,ui.smoke,ui.preffered_age";

        // Prefer authenticated user's stored location and search radius when available
        if (!empty($userInformation->search_radius)) {
            $radius = $userInformation->search_radius;
        }

        $bindings = [];
        if ($latitude !== null && $longitude !== null) {
            $distanceExpr = "(6371 * acos(cos(radians(?)) * cos(radians(ui.latitude)) * cos(radians(ui.longitude) - radians(?)) + sin(radians(?)) * sin(radians(ui.latitude))))";
            $select = "SELECT {$selectBase}, {$distanceExpr} AS distance";
            // distance placeholders: lat, lng, lat
            $bindings[] = $latitude;
            $bindings[] = $longitude;
            $bindings[] = $latitude;
        } else {
            $select = "SELECT {$selectBase}, NULL AS distance";
        }

        $sql = "$select FROM users u JOIN user_information ui ON ui.user_id = u.id WHERE u.id != ? AND u.status = 1";
        $bindings[] = $user ? $user->id : 0;

        if ($q) {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $bindings[] = "%{$q}%";
            $bindings[] = "%{$q}%";
        }

        $religionId = $request->input('religion_id');
        $relationshipStatusId = $request->input('relationship_status_id');
        $ethnicityId = $request->input('ethnicity_id');
        $educationId = $request->input('education_id');
        $careerFieldId = $request->input('carrer_field_id');
        $alkohol = $request->input('alkohol');
        $smoke = $request->input('smoke');
        $minAge = $request->input('min_age');
        $maxAge = $request->input('max_age');
        $minHeight = $request->input('min_height');
        $maxHeight = $request->input('max_height');

        // Apply gender filter - always filter by gender (no 'both' option)
        $sql .= " AND ui.gender = ?";
        $bindings[] = $gender;

        if ($religionId) {
            $sql .= " AND ui.religion_id = ?";
            $bindings[] = $religionId;
        }

        if ($relationshipStatusId) {
            $sql .= " AND ui.relationship_status_id = ?";
            $bindings[] = $relationshipStatusId;
        }

        if ($ethnicityId) {
            $sql .= " AND ui.ethnicity_id = ?";
            $bindings[] = $ethnicityId;
        }

        if ($educationId) {
            $sql .= " AND ui.education_id = ?";
            $bindings[] = $educationId;
        }

        if ($careerFieldId) {
            $sql .= " AND ui.carrer_field_id = ?";
            $bindings[] = $careerFieldId;
        }

        if ($alkohol) {
            $sql .= " AND ui.alkohol = ?";
            $bindings[] = $alkohol;
        }

        if ($smoke) {
            $sql .= " AND ui.smoke = ?";
            $bindings[] = $smoke;
        }

        if ($minAge) {
            $sql .= " AND ui.age >= ?";
            $bindings[] = $minAge;
        }

        if ($maxAge) {
            $sql .= " AND ui.age <= ?";
            $bindings[] = $maxAge;
        }

        if ($minHeight) {
            $sql .= " AND ui.height >= ?";
            $bindings[] = $minHeight;
        }

        if ($maxHeight) {
            $sql .= " AND ui.height <= ?";
            $bindings[] = $maxHeight;
        }

        // Add HAVING distance <= ? when coords are provided (uses alias from SELECT)
        if ($latitude !== null && $longitude !== null) {
            $sql .= " HAVING distance <= ?";
            $bindings[] = $radius;
            $sql .= " ORDER BY distance ASC";
        } else {
            $sql .= " ORDER BY u.id DESC";
        }

        $sql .= " LIMIT 50";

        $rows = collect(\DB::select($sql, $bindings));

        // Decode JSON fields that may be double-encoded
        $results = $rows->map(function($profile) {
            foreach (['relation_goals','interests','languages'] as $field) {
                if (isset($profile->{$field})) {
                    $val = $profile->{$field};
                    if (is_string($val)) {
                        $decoded = json_decode($val, true);
                        if (is_string($decoded)) {
                            $decoded = json_decode($decoded, true) ?? [];
                        }
                        $profile->{$field} = is_array($decoded) ? $decoded : [];
                    }
                }
            }
            return $profile;
        });

        return response()->json(['status' => true, 'data' => $results]);
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
}