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
     * 
     * Accepts 'exclude_ids' parameter to prevent showing already-seen profiles
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
        // Note: is_verified and sort_by are handled inside generateRecommendations
        // so they go through the full scoring pipeline with compatibility_details
        $isSearchRequest = $this->hasSearchFilters($request);

        // For search requests, don't use cache as they are dynamic
        if ($isSearchRequest) {
            return $this->handleSearchWithFilters($request, $user, $userInformation);
        }

        // Just Joined tab — dedicated lightweight query (skips recommendation engine)
        if ($request->input('sort_by') === 'newest') {
            return $this->handleJustJoinedTab($user, $userInformation, $request);
        }

        // For recommendations, if client sends exclude_ids or tab-specific filters,
        // don't use cache (fresh results)
        $excludeIds = $request->input('exclude_ids', []);
        $hasTabFilters = $request->has('is_verified') || $request->has('is_online') || $request->has('same_goal') || $request->has('sort_by');
        
        if (!empty($excludeIds) || $hasTabFilters) {
            // Client is tracking shown profiles or using tab filters - generate fresh results
            return $this->generateRecommendations($request, $user, $userInformation, $excludeIds);
        }
        
        // For initial load without exclusions, use cache
        $cacheKey = "recommendations_user_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function() use ($request, $user, $userInformation) {
            return $this->generateRecommendations($request, $user, $userInformation);
        });
    }

    /**
     * Generate recommendations without caching (used by cache callback)
     * 
     * @param array $excludeIds Array of user IDs to exclude from results (already shown to user)
     */
    private function generateRecommendations(Request $request, $user, $userInformation, $excludeIds = [])
    {
        $isFakeUser = (bool) $user->is_fake;

        // Get current user's preferences and location
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;
        $searchRadius = $userInformation->search_radius ?? 50;
        if ($request->has('radius')) {
            $searchRadius = (int) $request->input('radius');
        }
        $searchPreference = $userInformation->search_preference ?? 'male';
        $currentCountryCode = $userInformation->country_code ?? null;
        $minAge = $request->input('min_age');
        $maxAge = $request->input('max_age');

        $viewerGender = $userInformation->gender ?? null;

        // Build query for recommendations with smart matching
        $query = User::with(['user_information', 'engagementScore'])
            ->whereHas('user_information', function($q) use ($searchPreference, $minAge, $maxAge, $viewerGender) {
                $q->where('gender', $searchPreference);
                if ($viewerGender) {
                    $q->wantsToMeet($viewerGender);
                }
                if ($minAge) {
                    $q->where('age', '>=', (int) $minAge);
                }
                if ($maxAge) {
                    $q->where('age', '<=', (int) $maxAge);
                }
            })
            ->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            })
            // Exclude users the current user has liked (permanent — already matched)
            ->whereDoesntHave('receivedInteractions', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('action', 'like');
            })
            // Exclude users the current user has disliked/passed (dynamic cooldown based on re-swipe count)
            ->whereDoesntHave('receivedInteractions', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->whereIn('action', ['dislike', 'pass'])
                  ->where(function($sub) {
                      // 1st dislike = 7 days, 2nd = 14 days, 3rd+ = 30 days
                      $sub->where(function($s) {
                          $s->where('interaction_count', '<=', 1)
                            ->where('created_at', '>=', now()->subDays(7));
                      })->orWhere(function($s) {
                          $s->where('interaction_count', 2)
                            ->where('created_at', '>=', now()->subDays(14));
                      })->orWhere(function($s) {
                          $s->where('interaction_count', '>=', 3)
                            ->where('created_at', '>=', now()->subDays(30));
                      });
                  });
            });

        // Fake/test accounts only see other fake users (app store review isolation)
        if ($isFakeUser) {
            $query->where('is_fake', 1);
        } else {
            $query->where('is_fake', 0);
        }

        // Filter by same country to prevent cross-country matching
        // Skip for fake users so reviewers from any country see profiles
        if ($currentCountryCode && !$isFakeUser) {
            $query->whereHas('user_information', function($q) use ($currentCountryCode) {
                $q->where('country_code', $currentCountryCode);
            });
        }

        // Exclude already-shown profiles (from client's current session)
        if (!empty($excludeIds) && is_array($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Verified tab — only show verified users when requested
        if ($request->has('is_verified') && !is_null($request->input('is_verified'))) {
            $isVerified = filter_var($request->input('is_verified'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('user_information', function($q) use ($isVerified) {
                $q->where('is_verified', $isVerified);
            });
        }

        // Online tab — show users active within the last 30 minutes (online + recently active)
        if ($request->has('is_online') && filter_var($request->input('is_online'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('last_activity', '>=', now()->subMinutes(30))
                  ->where('hide_online_status', false);
        }

        // Apply distance filter if coordinates are available
        // Skip for fake users (reviewers can be anywhere) and "Just Joined" tab
        $isNewestTab = $request->input('sort_by') === 'newest';
        if ($currentLat != 0 && $currentLng != 0 && !$isNewestTab && !$isFakeUser) {
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
        
        $results = $query->limit(2000)->get();

        // Fetch IDs of users who have already liked the current user ("secret admirers")
        // These should appear higher in the stack without revealing they liked you
        $secretAdmirerIds = \DB::table('user_interactions')
            ->where('target_user_id', $user->id)
            ->where('action', 'like')
            ->pluck('user_id')
            ->toArray();

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
                'is_vip' => (bool) $user->isVipActive(),
                'is_boosted' => (bool) $user->isBoosted(),
                'is_online' => $user->isOnlineVisible(),
                'last_activity' => $user->lastActivityVisible(),
                'created_at' => $user->created_at,
                'distance' => $distance,
                'engagement_score' => $user->engagementScore->engagement_score ?? 5.0,
                'popularity_score' => (int) round(($user->engagementScore->engagement_score ?? 0) * 10),
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                'device_token' => $userInfo->device_token,

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

            // Blended rank_score: 60% compatibility + 25% engagement + 15% photo quality
            $engagementScore = $profile->engagement_score ?? 5.0;
            $photoQuality = $profile->user_information->photo_quality_score ?? 5.0;
            $profile->rank_score = round(($score * 0.60) + ($engagementScore * 0.25) + ($photoQuality * 0.15), 2);

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

        // Get boosted users in same country and prioritize them
        // Boosted users should appear even if already matched/chatted
        $boostedUserIds = [];
        try {
            $boostedUserIds = ProfileBoost::getActiveBoostedUsers($currentCountryCode);

            // Fetch boosted users that were excluded by the interaction/distance filter
            $missingBoostedIds = array_diff($boostedUserIds, $scoredResults->pluck('id')->toArray());
            // Never include self in own recommendations
            $missingBoostedIds = array_diff($missingBoostedIds, [$user->id]);
            if (!empty($missingBoostedIds)) {
                $missingBoosted = User::with(['user_information'])
                    ->whereIn('id', $missingBoostedIds)
                    ->where('status', 1)
                    ->whereHas('user_information', function($q) use ($searchPreference, $viewerGender) {
                        $q->where('gender', $searchPreference);
                        if ($viewerGender) {
                            $q->wantsToMeet($viewerGender);
                        }
                    })
                    ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                        $q->where('blocked_user_id', $user->id);
                    })
                    ->get()
                    ->map(function ($u) use ($currentLat, $currentLng) {
                        $userInfo = $u->user_information;
                        if (!$userInfo) return null;

                        $distance = $this->calculateDistance(
                            $currentLat, $currentLng,
                            $userInfo->latitude, $userInfo->longitude
                        );

                        $profile = (object) [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                            'image' => $u->image,
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
                            'is_vip' => (bool) $u->isVipActive(),
                            'is_boosted' => true,
                            'is_online' => $u->isOnlineVisible(),
                            'last_activity' => $u->lastActivityVisible(),
                            'created_at' => $u->created_at,
                            'distance' => $distance,
                            'mood' => $userInfo->mood,
                            'address' => $userInfo->address,
                            'device_token' => $userInfo->device_token,
                            'relation_goals_details' => $userInfo->relation_goals_details,
                            'interests_details' => $userInfo->interests_details,
                            'ethnicity_details' => $userInfo->ethnicity_details,
                            'languages_details' => $userInfo->languages_details,
                        ];
                        $profile->match_score = 0;
                        $profile->compatibility_details = new \stdClass();
                        return $profile;
                    })->filter()->values();

                $scoredResults = $scoredResults->concat($missingBoosted);
            }
        } catch (\Throwable $e) {
            \Log::error('Boost logic error in generateRecommendations', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }
        
        // 5-tier sorting: Boosted > Secret Admirers > VIP > Goal-match > Regular
        // Tier 1: Boosted profiles (paid placement)
        $boostedProfiles = $scoredResults->filter(function($profile) use ($boostedUserIds) {
            return in_array($profile->id, $boostedUserIds);
        })->sortByDesc('rank_score')->values();

        // Tier 2: Secret admirers (liked current user — NOT revealed to frontend)
        $admirerProfiles = $scoredResults->filter(function($profile) use ($boostedUserIds, $secretAdmirerIds) {
            return !in_array($profile->id, $boostedUserIds)
                && in_array($profile->id, $secretAdmirerIds);
        })->sortByDesc('rank_score')->values();

        // Tier 3: VIP profiles
        $vipProfiles = $scoredResults->filter(function($profile) use ($boostedUserIds, $secretAdmirerIds) {
            return !in_array($profile->id, $boostedUserIds)
                && !in_array($profile->id, $secretAdmirerIds)
                && $profile->is_vip;
        })->sortByDesc('rank_score')->values();

        // Tier 4 & 5: Regular profiles (split by goal match)
        $regularProfiles = $scoredResults->filter(function($profile) use ($boostedUserIds, $secretAdmirerIds) {
            return !in_array($profile->id, $boostedUserIds)
                && !in_array($profile->id, $secretAdmirerIds)
                && !$profile->is_vip;
        })->sortByDesc('rank_score')->values();

        $profilesWithGoalMatch = $regularProfiles->filter(function($profile) {
            return isset($profile->compatibility_details['relation_goals_match']) &&
                   $profile->compatibility_details['relation_goals_match'] === true;
        });

        $profilesWithoutGoalMatch = $regularProfiles->filter(function($profile) {
            return !isset($profile->compatibility_details['relation_goals_match']) ||
                   $profile->compatibility_details['relation_goals_match'] === false;
        });

        // Combine: boosted → early VIPs → admirers → remaining VIPs → goal-match → others
        // Interleave admirers after a few VIPs to avoid suspicious patterns
        $limit = get_option('recommendation_limit', 50);
        $goalMatchLimit = (int)($limit * 1.5);
        $admirerSlice = $admirerProfiles->take(5); // Cap admirers at 5 per batch

        $finalResults = $boostedProfiles
            ->concat($vipProfiles->take(3))
            ->concat($admirerSlice)
            ->concat($vipProfiles->skip(3)->take($limit))
            ->concat($profilesWithGoalMatch->take($goalMatchLimit))
            ->concat($profilesWithoutGoalMatch->take($limit))
            ->unique('id')
            ->take($limit * 2)
            ->values();

        // Same Goal tab — only show users whose relationship goals match
        if ($request->has('same_goal') && filter_var($request->input('same_goal'), FILTER_VALIDATE_BOOLEAN)) {
            $finalResults = $finalResults->filter(function($profile) {
                return isset($profile->compatibility_details['relation_goals_match']) &&
                       $profile->compatibility_details['relation_goals_match'] === true;
            })->values();
        }

        // Near You tab — sort by distance ascending when requested
        $sortBy = $request->input('sort_by');
        if ($sortBy === 'distance') {
            $finalResults = $finalResults->sortBy(function ($profile) {
                return $profile->distance ?? PHP_INT_MAX;
            })->values();
        }

        // Just Joined tab — sort by newest first and filter to recent users
        if ($sortBy === 'newest') {
            $finalResults = $finalResults->filter(function ($profile) {
                if (empty($profile->created_at)) return false;
                $joined = \Carbon\Carbon::parse($profile->created_at);
                return $joined->greaterThanOrEqualTo(now()->subDays(3));
            })->sortByDesc('created_at')->values();
        }

        // Popular tab — sort by boosted first, then engagement_score DESC, then distance ASC
        if ($sortBy === 'popular') {
            $finalResults = $finalResults->sortBy(function ($profile) {
                $priority = 0;
                if (!empty($profile->is_boosted)) $priority -= 10000;
                // Higher engagement_score = lower sort key = appears first
                $score = $profile->engagement_score ?? 0;
                $priority -= ($score * 100);
                // Tie-break by distance (closer first)
                $priority += ($profile->distance ?? 9999) * 0.01;
                return $priority;
            })->values();
        }

        // Track impressions for freshness/visibility decay (non-blocking, runs after response)
        $returnedUserIds = $finalResults->pluck('id')->toArray();
        if (!empty($returnedUserIds)) {
            dispatch(function () use ($returnedUserIds) {
                \DB::table('user_engagement_scores')
                    ->whereIn('user_id', $returnedUserIds)
                    ->increment('impressions_count');
            })->afterResponse();
        }

        return response()->json(['status' => true, 'data' => $finalResults]);
    }

    /**
     * Dedicated handler for the "Just Joined" tab.
     * Uses a direct query instead of the recommendation engine so that:
     *   - Already-swiped users still appear (new user discovery)
     *   - No distance filter (same country is enough)
     *   - Only users created in the last 3 days
     */
    private function handleJustJoinedTab($user, $userInformation, $request)
    {
        $isFakeUser = (bool) $user->is_fake;

        $searchPreference = $userInformation->search_preference ?? 'male';
        $currentCountryCode = $userInformation->country_code ?? null;
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;

        // Optional filters from the Just Joined filter sheet
        $minAge = $request->input('min_age');
        $maxAge = $request->input('max_age');
        $radius = $request->input('radius');

        $viewerGender = $userInformation->gender ?? null;

        $query = User::with(['user_information', 'engagementScore']);

        // Fake users skip the "just joined" date filter — show all fake profiles
        if (!$isFakeUser) {
            $query->where('created_at', '>=', now()->subDays(3));
        }

        $query->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereHas('user_information', function($q) use ($searchPreference, $minAge, $maxAge, $viewerGender) {
                $q->where('gender', $searchPreference);
                if ($viewerGender) {
                    $q->wantsToMeet($viewerGender);
                }
                if ($minAge) {
                    $q->where('age', '>=', (int) $minAge);
                }
                if ($maxAge) {
                    $q->where('age', '<=', (int) $maxAge);
                }
            })
            ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            })
            // Exclude users the current user has liked (permanent — already matched)
            ->whereDoesntHave('receivedInteractions', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('action', 'like');
            })
            // Exclude users the current user has disliked/passed (dynamic cooldown)
            ->whereDoesntHave('receivedInteractions', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->whereIn('action', ['dislike', 'pass'])
                  ->where(function($sub) {
                      // 1st dislike = 7 days, 2nd = 14 days, 3rd+ = 30 days
                      $sub->where(function($s) {
                          $s->where('interaction_count', '<=', 1)
                            ->where('created_at', '>=', now()->subDays(7));
                      })->orWhere(function($s) {
                          $s->where('interaction_count', 2)
                            ->where('created_at', '>=', now()->subDays(14));
                      })->orWhere(function($s) {
                          $s->where('interaction_count', '>=', 3)
                            ->where('created_at', '>=', now()->subDays(30));
                      });
                  });
            });

        // Fake/test accounts only see other fake users
        if ($isFakeUser) {
            $query->where('is_fake', 1);
        } else {
            $query->where('is_fake', 0);
        }

        // Strict country filter: only show users whose country_code exactly matches
        // the viewer's country.  Users with NULL / empty country_code are excluded —
        // they have not had their location resolved yet and must NOT leak into other
        // countries' feeds (the orWhereNull pattern was the source of the bug).
        // Skip for fake users so reviewers from any country see profiles.
        if ($isFakeUser) {
            // No country filter for test accounts
        } else if ($currentCountryCode) {
            $query->whereHas('user_information', function($q) use ($currentCountryCode) {
                $q->where('country_code', $currentCountryCode);
            });
        } else {
            // Viewer's own country is unknown — show nothing rather than a global feed.
            return response()->json(['status' => true, 'data' => []]);
        }

        $results = $query->orderBy('created_at', 'desc')->limit(50)->get();

        $transformedResults = $results->map(function($u) use ($currentLat, $currentLng) {
            $info = $u->user_information;
            if (!$info) return null;

            $distance = $this->calculateDistance(
                $currentLat, $currentLng,
                $info->latitude, $info->longitude
            );

            return (object) [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'image' => $u->image,
                'image_thumb' => $u->image_thumb ?? null,
                'image_medium' => $u->image_medium ?? null,
                'images' => $info->images,
                'images_thumbnails' => $info->images_thumbnails ?? null,
                'images_medium' => $info->images_medium ?? null,
                'bio' => $info->bio,
                'mood' => $info->mood ?? null,
                'gender' => $info->gender,
                'date_of_birth' => $info->date_of_birth,
                'age' => $info->age,
                'height' => $info->height,
                'address' => $info->address ?? null,
                'relation_goals' => $info->relation_goals,
                'interests' => $info->interests,
                'languages' => $info->languages,
                'latitude' => $info->latitude,
                'longitude' => $info->longitude,
                'religion_id' => $info->religion_id,
                'relationship_status_id' => $info->relationship_status_id,
                'ethnicity_id' => $info->ethnicity_id,
                'education_id' => $info->education_id,
                'carrer_field_id' => $info->carrer_field_id,
                'alkohol' => $info->alkohol,
                'smoke' => $info->smoke,
                'preffered_age' => $info->preffered_age,
                'is_zodiac_sign_matter' => $info->is_zodiac_sign_matter,
                'is_food_preference_matter' => $info->is_food_preference_matter,
                'country_code' => $info->country_code,
                'distance' => round($distance, 1),
                'is_verified' => (bool) ($info->is_verified ?? false),
                'is_vip' => (bool) $u->isVipActive(),
                'is_online' => $u->isOnlineVisible(),
                'is_boosted' => (bool) $u->isBoosted(),
                'device_token' => $info->device_token ?? $u->device_token ?? '--',
                'last_activity' => $u->lastActivityVisible(),
                'created_at' => $u->created_at,
                'popularity_score' => (int) round(($u->engagementScore->engagement_score ?? 0) * 10),

                // Detailed attributes from UserInformation model accessors
                'relation_goals_details' => $info->relation_goals_details,
                'interests_details' => $info->interests_details,
                'ethnicity_details' => $info->ethnicity_details,
                'languages_details' => $info->languages_details,
            ];
        })->filter()->values();

        // Post-query distance filter (for Gold subscribers who set a radius)
        if ($radius) {
            $transformedResults = $transformedResults->filter(function($u) use ($radius) {
                return isset($u->distance) && $u->distance <= (float) $radius;
            })->values();
        }

        return response()->json(['status' => true, 'data' => $transformedResults]);
    }

    /**
     * Helper method to check if request has search filters
     */
    private function hasSearchFilters(Request $request)
    {
        // Tab-specific filters handled by generateRecommendations — not a "search"
        if ($request->has('is_verified') || $request->has('is_online') || $request->has('same_goal') || $request->has('sort_by')) {
            return false;
        }

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
     * Also supports exclude_ids to prevent showing already-seen profiles
     */
    private function handleSearchWithFilters(Request $request, $user, $userInformation)
    {
        $isFakeUser = (bool) $user->is_fake;

        $q = $request->input('q');
        $gender = $request->input('gender', $userInformation->search_preference ?? 'male');
        $interests = $request->input('interests', []);
        $languages = $request->input('languages', []);
        $relationGoals = $request->input('relation_goals', []);
        $latitude = $userInformation->latitude;
        $longitude = $userInformation->longitude;
        $radius = $request->input('radius', $userInformation->search_radius ?? 50);
        $excludeIds = $request->input('exclude_ids', []);

        // Build Eloquent query for better performance and readability
        $viewerGender = $userInformation->gender ?? null;

        $query = User::with(['user_information'])
            ->whereHas('user_information', function($q) use ($gender, $viewerGender) {
                $q->where('gender', $gender);
                if ($viewerGender) {
                    $q->wantsToMeet($viewerGender);
                }
            })
            ->where('id', '!=', $user->id)
            ->where('status', 1)
            ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            })
            // Exclude users the current user has already interacted with
            ->whereDoesntHave('receivedInteractions', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        // Fake/test accounts only see other fake users
        if ($isFakeUser) {
            $query->where('is_fake', 1);
        } else {
            $query->where('is_fake', 0);
        }

        // Exclude already-shown profiles (from client's current session)
        if (!empty($excludeIds) && is_array($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Apply text search filter
        if ($q) {
            $query->where(function($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            });
        }

        // Apply profile filters
        $this->applyProfileFilters($query, $request);

        // Apply distance filter (skip for fake users — reviewers can be anywhere)
        if ($latitude !== null && $longitude !== null && !$isFakeUser) {
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

        $results = $query->limit(get_option('recommendation_limit', 50))->get();

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
                'is_vip' => (bool) $user->isVipActive(),
                'is_boosted' => (bool) $user->isBoosted(),
                'is_online' => $user->isOnlineVisible(),
                'last_activity' => $user->lastActivityVisible(),
                'created_at' => $user->created_at,
                'distance' => $distance,
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                'device_token' => $userInfo->device_token,
                'relation_goals_details' => $userInfo->relation_goals_details,
                'interests_details' => $userInfo->interests_details,
                'ethnicity_details' => $userInfo->ethnicity_details,
                'languages_details' => $userInfo->languages_details,

            ];
        })->filter()->values();

        // Add compatibility scoring to search results so tabs (Same Goal, etc.) work
        $userRelationGoals = $userInformation->relation_goals ?? [];
        if (is_string($userRelationGoals)) {
            $userRelationGoals = json_decode($userRelationGoals, true);
            if (is_string($userRelationGoals)) $userRelationGoals = json_decode($userRelationGoals, true) ?? [];
        }
        $userRelationGoals = is_array($userRelationGoals) ? $userRelationGoals : [];

        $transformedResults = $transformedResults->map(function($profile) use ($userInformation, $userRelationGoals) {
            $profileGoals = $profile->relation_goals;
            if (is_string($profileGoals)) {
                $profileGoals = json_decode($profileGoals, true);
                if (is_string($profileGoals)) $profileGoals = json_decode($profileGoals, true) ?? [];
            }
            $profileGoals = is_array($profileGoals) ? $profileGoals : [];

            $rgOverlap = !empty($userRelationGoals) && !empty($profileGoals)
                ? array_values(array_intersect($userRelationGoals, $profileGoals))
                : [];

            $profile->compatibility_details = [
                'relation_goals_match' => !empty($rgOverlap),
                'age_compatible' => ($userInformation->age && $profile->age && abs($userInformation->age - $profile->age) <= 5),
                'within_distance' => ($profile->distance !== null && $profile->distance <= ($userInformation->search_radius ?? 50)),
            ];
            $profile->match_score = 0;
            return $profile;
        });

        // Get boosted users in same country and prioritize them
        $currentCountryCode = $userInformation->country_code ?? null;
        $boostedUserIds = [];
        try {
            $boostedUserIds = ProfileBoost::getActiveBoostedUsers($currentCountryCode);

            // Fetch boosted users that were excluded by distance/interaction filters
            $missingBoostedIds = array_diff($boostedUserIds, $transformedResults->pluck('id')->toArray());
            // Never include self in own recommendations
            $missingBoostedIds = array_diff($missingBoostedIds, [$user->id]);
            if (!empty($missingBoostedIds)) {
                $missingBoosted = User::with(['user_information'])
                    ->whereIn('id', $missingBoostedIds)
                    ->where('status', 1)
                    ->whereHas('user_information', function($q) use ($gender, $viewerGender) {
                        $q->where('gender', $gender);
                        if ($viewerGender) {
                            $q->wantsToMeet($viewerGender);
                        }
                    })
                    ->whereDoesntHave('blockedByUsers', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->whereDoesntHave('blockedUsers', function($q) use ($user) {
                        $q->where('blocked_user_id', $user->id);
                    })
                    ->get()
                    ->map(function ($u) use ($latitude, $longitude) {
                        $userInfo = $u->user_information;
                        if (!$userInfo) return null;

                        $distance = $this->calculateDistance(
                            $latitude, $longitude,
                            $userInfo->latitude, $userInfo->longitude
                        );

                        $profile = (object) [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                            'image' => $u->image,
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
                            'is_vip' => (bool) $u->isVipActive(),
                            'is_boosted' => true,
                            'is_online' => $u->isOnlineVisible(),
                            'last_activity' => $u->lastActivityVisible(),
                            'created_at' => $u->created_at,
                            'distance' => $distance,
                            'mood' => $userInfo->mood,
                            'address' => $userInfo->address,
                            'device_token' => $userInfo->device_token,
                            'relation_goals_details' => $userInfo->relation_goals_details,
                            'interests_details' => $userInfo->interests_details,
                            'ethnicity_details' => $userInfo->ethnicity_details,
                            'languages_details' => $userInfo->languages_details,
                        ];
                        $profile->match_score = 0;
                        $profile->compatibility_details = new \stdClass();
                        return $profile;
                    })->filter()->values();

                $transformedResults = $transformedResults->concat($missingBoosted);
            }
        } catch (\Throwable $e) {
            \Log::error('Boost logic error in handleSearchWithFilters', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
        }

        // Separate boosted, VIP, and regular profiles
        $boostedProfiles = $transformedResults->filter(function($profile) use ($boostedUserIds) {
            return in_array($profile->id, $boostedUserIds);
        })->values();

        $vipProfiles = $transformedResults->filter(function($profile) use ($boostedUserIds) {
            return !in_array($profile->id, $boostedUserIds) && $profile->is_vip;
        });

        $regularProfiles = $transformedResults->filter(function($profile) use ($boostedUserIds) {
            return !in_array($profile->id, $boostedUserIds) && !$profile->is_vip;
        });

        // For search results, sort by distance within each group
        if ($latitude !== null && $longitude !== null) {
            $vipProfiles = $vipProfiles->sortBy('distance')->values();
            $regularProfiles = $regularProfiles->sortBy('distance')->values();
        }

        // Combine: boosted first, then VIP, then regular profiles
        $finalResults = $boostedProfiles->concat($vipProfiles)->concat($regularProfiles)->values();

        return response()->json(['status' => true, 'data' => $finalResults]);
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

        // Verified filter (accepts true/false, 1/0, "true"/"false")
        if ($request->has('is_verified') && !is_null($request->input('is_verified'))) {
            $isVerified = filter_var($request->input('is_verified'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('user_information', function($q) use ($isVerified) {
                $q->where('is_verified', $isVerified);
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

            // Track profile visit — skip if the viewer has incognito mode enabled
            try {
                if (!$user->incognito_mode) {
                    // Check if this is the first visit today (before inserting) to avoid spam notifications
                    $isFirstVisitToday = !\App\Models\ProfileVisitor::where('visitor_id', $user->id)
                        ->where('visited_user_id', $targetUserId)
                        ->whereDate('visited_at', today())
                        ->exists();

                    ProfileVisitor::trackVisit($user->id, $targetUserId);

                    // Clear the profile visitors cache for the target user so the visit appears immediately
                    Cache::forget("profile_visitors_user_{$targetUserId}");

                    // Notify target user — only on first visit today to avoid spam
                    if ($isFirstVisitToday) {
                        $targetUser = \App\Models\User::find($targetUserId);
                        if ($targetUser && $targetUser->device_token) {
                            $visitorName = $user->name ?? 'Someone';
                            send_notification('single', '👀 New Visitor', "{$visitorName} visited your profile!", null, [
                                'device_token' => $targetUser->device_token,
                                'type'         => 'new_visitor',
                                'user_id'      => (string) $user->id,
                            ]);
                        }
                    }
                } // end if (!$user->incognito_mode)
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
                    'is_vip' => (bool) $targetUser->isVipActive(),
                    'is_boosted' => (bool) $targetUser->isBoosted(),
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
                
                // If cached result exists but is empty, clear cache and regenerate
                if ($result && !$this->hasSoulmateData($result)) {
                    Cache::forget($cacheKey);
                    $result = null;
                }
            }
            
            // If no cached result or we're retrying, generate new data
            if (!$result) {
                $result = Cache::remember($cacheKey, now()->addDay(), function() use ($request, $user, $userInformation) {
                    return $this->generateSoulmates($request, $user, $userInformation);
                });
            }
            
            // Filter cached data to remove profiles with interactions
            if ($result) {
                $result = $this->filterInteractedProfiles($result, $user->id);
            }
            
            // Check if result has data
            if ($this->hasSoulmateData($result)) {
                if ($currentTry > 0) {
                    //\Log::info("Soulmates found for user {$user->id} after {$currentTry} retries");
                }
                return $result;
            }
            
            // If empty and we haven't exceeded retries, clear cache and try again
            $currentTry++;
            if ($currentTry <= $maxRetries) {
                Cache::forget($cacheKey);
                // \Log::info("Soulmates empty for user {$user->id}, clearing cache and retrying {$currentTry}/{$maxRetries}");
                
                // Optional: Add small delay between retries to allow for data changes
                usleep(200000); // 0.2 second delay
            } else {
                // \Log::warning("Soulmates failed after {$maxRetries} attempts for user {$user->id}");
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
        $isFakeUser = (bool) $user->is_fake;

        // Get current user's preferences and location
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;
        $searchRadius = ($userInformation->search_radius ?? 1000) * 2; // Expand radius for soulmates
        $searchPreference = $userInformation->search_preference ?? 'male';

        $viewerGender = $userInformation->gender ?? null;

        // Build query for high-quality profiles only
        $query = User::with(['user_information'])
            ->whereHas('user_information', function($q) use ($searchPreference, $viewerGender) {
                $q->where('gender', $searchPreference);
                if ($viewerGender) {
                    $q->wantsToMeet($viewerGender);
                }
                $q->whereNotNull('age')
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
            })
            // Exclude users who have already swiped on the current user
            ->whereDoesntHave('sentInteractions', function($q) use ($user) {
                $q->where('target_user_id', $user->id);
            });

        // Fake/test accounts only see other fake users
        if ($isFakeUser) {
            $query->where('is_fake', 1);
        } else {
            $query->where('is_fake', 0);
        }

        // Apply expanded distance filter for soulmates (skip for fake users)
        if ($currentLat != 0 && $currentLng != 0 && !$isFakeUser) {
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
        
        $results = $query->limit(2000)->get();
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
                'is_vip' => (bool) $user->isVipActive(),
                'is_boosted' => (bool) $user->isBoosted(),
                'is_online' => $user->isOnlineVisible(),
                'created_at' => $user->created_at,
                'distance' => $distance,
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                'device_token' => $userInfo->device_token,

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
        $highScoreSoulmates = $scoredResults
            ->filter(function($profile) {
                // Only include profiles with soulmate score >= 60 (high threshold)
                return $profile->soulmate_score >= 60;
            });

        // Separate VIP and regular soulmates
        $vipSoulmates = $highScoreSoulmates->filter(function($profile) {
            return $profile->is_vip;
        })->shuffle()->values();

        $regularSoulmates = $highScoreSoulmates->filter(function($profile) {
            return !$profile->is_vip;
        })->shuffle()->values();

        // Combine VIP first, then regular
        $soulmates = $vipSoulmates
            ->concat($regularSoulmates)
            ->take(15) // Limit to top 15 soulmates per day
            ->values();

        // If no high-scoring soulmates found, try with lower threshold
        if ($soulmates->isEmpty()) {
            $mediumScoreSoulmates = $scoredResults
                ->filter(function($profile) {
                    // Lower threshold fallback
                    return $profile->soulmate_score >= 40;
                });

            $vipSoulmates = $mediumScoreSoulmates->filter(function($profile) {
                return $profile->is_vip;
            })->shuffle()->values();

            $regularSoulmates = $mediumScoreSoulmates->filter(function($profile) {
                return !$profile->is_vip;
            })->shuffle()->values();

            $soulmates = $vipSoulmates
                ->concat($regularSoulmates)
                ->take(10) // Fewer results for lower threshold
                ->values();
        }

        // If still empty, try even lower threshold
        if ($soulmates->isEmpty()) {
            $lowScoreSoulmates = $scoredResults
                ->filter(function($profile) {
                    // Very low threshold fallback
                    return $profile->soulmate_score >= 20;
                });

            $vipSoulmates = $lowScoreSoulmates->filter(function($profile) {
                return $profile->is_vip;
            })->shuffle()->values();

            $regularSoulmates = $lowScoreSoulmates->filter(function($profile) {
                return !$profile->is_vip;
            })->shuffle()->values();

            $soulmates = $vipSoulmates
                ->concat($regularSoulmates)
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
     * Filter out profiles that the user has already interacted with
     * @param mixed $result The response result (JsonResponse or array)
     * @param int $userId The current user's ID
     * @return mixed Filtered result
     */
    private function filterInteractedProfiles($result, $userId)
    {
        // If it's a JsonResponse, decode it
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            $data = $result->getData(true);
            if (isset($data['status']) && $data['status'] === true && isset($data['data'])) {
                $profiles = $data['data'];
                
                if (is_array($profiles) && count($profiles) > 0) {
                    // Get all user IDs from profiles
                    $profileIds = array_column($profiles, 'id');
                    
                    // Get interacted user IDs (both directions)
                    $interactedIds = \DB::table('user_interactions')
                        ->where(function($query) use ($userId, $profileIds) {
                            $query->where('user_id', $userId)
                                  ->whereIn('target_user_id', $profileIds);
                        })
                        ->orWhere(function($query) use ($userId, $profileIds) {
                            $query->whereIn('user_id', $profileIds)
                                  ->where('target_user_id', $userId);
                        })
                        ->pluck('target_user_id')
                        ->merge(
                            \DB::table('user_interactions')
                                ->whereIn('user_id', $profileIds)
                                ->where('target_user_id', $userId)
                                ->pluck('user_id')
                        )
                        ->unique()
                        ->toArray();
                    
                    // Filter out interacted profiles
                    $filteredProfiles = array_filter($profiles, function($profile) use ($interactedIds) {
                        return !in_array($profile['id'] ?? $profile->id ?? null, $interactedIds);
                    });
                    
                    // Reindex array
                    $data['data'] = array_values($filteredProfiles);
                    
                    return response()->json($data);
                }
            }
        }
        
        return $result;
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

        // Use 5-minute cache for profile visitors
        $cacheKey = "profile_visitors_user_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function() use ($user) { // 300 seconds = 5 minutes
            return $this->generateProfileVisitors($user);
        });
    }

    /**
     * Generate profile visitors list - simple version
     */
    private function generateProfileVisitors($user)
    {
        try {
            // Get unique visitors (only most recent visit per visitor)
            $visitors = ProfileVisitor::where('visited_user_id', $user->id)
                ->with(['visitor.user_information'])
                ->select('visitor_id', \DB::raw('MAX(visited_at) as visited_at'), \DB::raw('MAX(id) as id'))
                ->groupBy('visitor_id')
                ->orderBy('visited_at', 'desc')
                ->limit(20)
                ->get()
                ->filter();

            // Transform visitor data
            $transformedVisitors = $visitors
                ->filter(fn($visit) => $visit->visitor !== null) // skip deleted users
                ->map(function($visit) {
                    $visitor  = $visit->visitor;
                    $userInfo = $visitor->user_information;

                    return [
                        'id'         => $visitor->id,
                        'name'       => $visitor->name,
                        'image'      => $visitor->image,
                        'age'        => $userInfo ? $userInfo->age : null,
                        'is_vip'     => (bool) $visitor->isVipActive(),
                        'is_boosted' => (bool) $visitor->isBoosted(),
                        'is_verified'=> $userInfo ? ($userInfo->is_verified ?? false) : false,
                        'is_online'  => $visitor->isOnlineVisible(),
                        'visited_at' => $visit->visited_at,
                    ];
                })->values();

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

    /**
     * Get nearby users for the map view
     */
    public function nearby(Request $request)
    {
        $user = $request->user();
        $isFakeUser = (bool) $user->is_fake;

        $userInformation = $user ? $user->user_information : null;

        if (!$userInformation) {
            return response()->json([
                'status' => false,
                'message' => 'User profile information not found. Please complete your profile first.'
            ], 400);
        }

        
        $latitude = $request->input('latitude', $userInformation->latitude);
        $longitude = $request->input('longitude', $userInformation->longitude);
        $radius = $request->input('radius', $userInformation->search_radius ?? 300);
        $filter = $request->input('filter');
        $searchPreference = $userInformation->search_preference ?? 'male';

        if (!$latitude || !$longitude) {
            if (!$isFakeUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location coordinates are required. Please enable location services.'
                ], 400);
            }
        }

        $viewerGender = $userInformation->gender ?? null;

        // Build base query
        $query = User::with(['user_information', 'engagementScore'])
            ->whereHas('user_information', function ($q) use ($searchPreference, $viewerGender) {
                $q->where('gender', $searchPreference);
                if ($viewerGender) {
                    $q->wantsToMeet($viewerGender);
                }
            })
            ->where('id', '!=', $user->id)
            ->where('status', 1);

        // Fake/test accounts only see other fake users, skip map visibility check
        if ($isFakeUser) {
            $query->where('is_fake', 1);
        } else {
            $query->where('is_fake', 0)
                  ->whereHas('user_information', function ($q) {
                $q->where('visible_on_map', true);
            })
            // Hide users inactive for more than 60 days — keep the map feeling alive
            ->where('last_activity', '>=', now()->subDays(60));
        }

        $query->whereDoesntHave('blockedByUsers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereDoesntHave('blockedUsers', function ($q) use ($user) {
                $q->where('blocked_user_id', $user->id);
            });

        // Apply filter-specific constraints
        if ($filter === 'online') {
            $query->where('last_activity', '>=', now()->subMinutes(15));
        }

        if ($filter === 'verified') {
            $query->whereHas('user_information', function ($q) {
                $q->where('is_verified', true);
            });
        }

        if ($filter === 'popular') {
            // Show boosted users AND users with high engagement scores
            $countryCode = $userInformation->country_code ?? null;
            $boostedIds = ProfileBoost::getActiveBoostedUsers($countryCode);
            $highEngagementIds = \DB::table('user_engagement_scores')
                ->where('engagement_score', '>=', 3.0)
                ->pluck('user_id')
                ->toArray();
            $popularIds = array_unique(array_merge($boostedIds, $highEngagementIds));
            if (!empty($popularIds)) {
                $query->whereIn('id', $popularIds);
            }
        }

        // Get country-wide boosted user IDs for merging into results
        $countryBoostedIds = ProfileBoost::getActiveBoostedUsers($userInformation->country_code ?? null);

        // Distance filter using ST_Distance_Sphere for accuracy
        // Boosted users from same country bypass the distance filter
        // Fake/test users skip distance entirely — reviewers can be anywhere
        if ($isFakeUser) {
            // No distance filter for test accounts
        } else if (!empty($countryBoostedIds)) {
            $query->where(function ($q) use ($latitude, $longitude, $radius, $countryBoostedIds) {
                // Include users within radius OR boosted users from same country
                $q->whereHas('user_information', function ($sub) use ($latitude, $longitude, $radius) {
                    $sub->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->whereRaw('
                            ST_Distance_Sphere(
                                point(longitude, latitude),
                                point(?, ?)
                            ) <= ?
                        ', [$longitude, $latitude, $radius * 1000]);
                })->orWhereIn('id', $countryBoostedIds);
            });
        } else {
            $query->whereHas('user_information', function ($q) use ($latitude, $longitude, $radius) {
                $q->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->whereRaw('
                        ST_Distance_Sphere(
                            point(longitude, latitude),
                            point(?, ?)
                        ) <= ?
                    ', [$longitude, $latitude, $radius * 1000]);
            });
        }

        // Order by distance ascending so the closest users are returned
        $results = $query
            ->orderByRaw('
                ST_Distance_Sphere(
                    point((SELECT longitude FROM user_information WHERE user_id = users.id),
                          (SELECT latitude FROM user_information WHERE user_id = users.id)),
                    point(?, ?)
                ) ASC
            ', [$longitude, $latitude])
            ->limit(50)
            ->get();

        // Transform results and add distance calculation
        $transformedResults = $results->map(function ($user) use ($latitude, $longitude) {
            $userInfo = $user->user_information;
            if (!$userInfo || !$userInfo->latitude || !$userInfo->longitude) return null;

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
                'is_zodiac_sign_matter' => $userInfo->is_zodiac_sign_matter,
                'is_food_preference_matter' => $userInfo->is_food_preference_matter,
                'country_code' => $userInfo->country_code,
                'is_verified' => $userInfo->is_verified ?? false,
                'is_vip' => (bool) $user->isVipActive(),
                'is_boosted' => (bool) $user->isBoosted(),
                'is_online' => $user->isOnlineVisible(),
                'last_activity' => $user->lastActivityVisible(),
                'created_at' => $user->created_at,
                'distance' => $distance ? round($distance, 1) : null,
                'popularity_score' => (int) round(($user->engagementScore->engagement_score ?? 0) * 10),
                'mood' => $userInfo->mood,
                'address' => $userInfo->address,
                'device_token' => $userInfo->device_token,

                // Add detailed attributes from UserInformation model accessors
                'relation_goals_details' => $userInfo->relation_goals_details,
                'interests_details' => $userInfo->interests_details,
                'ethnicity_details' => $userInfo->ethnicity_details,
                'languages_details' => $userInfo->languages_details,
            ];
        })->filter()->values();

        // Add match_score and compatibility_details so the Flutter app can use them
        $transformedResults = $transformedResults->map(function ($profile) {
            $profile->match_score = 0;
            $profile->compatibility_details = [
                'relation_goals_match' => false,
                'language_match' => false,
                'interests_match' => false,
                'religion_match' => false,
                'lifestyle_compatible' => false,
                'age_compatible' => false,
                'within_distance' => true,
            ];
            return $profile;
        });

        // Sort: boosted first, then online, then by distance
        $sorted = $transformedResults->sortBy(function ($profile) {
            $priority = 0;
            if ($profile->is_boosted) $priority -= 1000;
            if ($profile->is_online) $priority -= 500;
            $priority += ($profile->distance ?? 9999);
            return $priority;
        })->values();

        return response()->json([
            'status' => true,
            'data' => $sorted,
            'meta' => [
                'total' => $sorted->count(),
                'radius' => $radius,
                'filter' => $filter,
                'center' => [
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                ],
            ],
        ]);
    }
}