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
    $userInformation = $user ? $user->user_information : null;
        $userInformation = $user->user_information;

        // Get current user's preferences and location
        $currentLat = $userInformation->latitude ?? 0;
        $currentLng = $userInformation->longitude ?? 0;
        $searchRadius = $userInformation->search_radius ?? 50;
        $searchPreference = $userInformation->search_preference ?? 'both';

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
                'ui.relation_goals',
                'ui.interests',
                'ui.languages',
                'ui.latitude',
                'ui.longitude',
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
            ->where('u.id', '!=', $user->id);

        // Apply gender filter if not 'both'
        if ($searchPreference !== 'both') {
            $query->where('ui.gender', $searchPreference);
        }

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

        $results = $query->orderBy('distance')->limit(50)->get();

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

        // Calculate match scores in PHP for now (simpler than complex SQL)
        $scoredResults = $results->map(function($profile) use ($userInformation, $userRelationGoals, $userInterests, $userLanguages) {
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

            // Compute overlaps and score components for logging
            $rgOverlap = !empty($userRelationGoals) && !empty($profileRelationGoals) ? array_values(array_intersect($userRelationGoals, $profileRelationGoals)) : [];
            $langOverlap = !empty($userLanguages) && !empty($profileLanguages) ? array_values(array_intersect($userLanguages, $profileLanguages)) : [];
            $intOverlap = !empty($userInterests) && !empty($profileInterests) ? array_values(array_intersect($userInterests, $profileInterests)) : [];

            if (!empty($rgOverlap)) {
                $score += 2;
            }
            if (!empty($langOverlap)) {
                $score += 2;
            }
            if (!empty($intOverlap)) {
                $score += 1;
            }
            if ($profile->distance <= ($userInformation->search_radius ?? 100)) {
                $score += 3;
            }

            $profile->match_score = $score;
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
        $q = $request->input('q');
        $gender = $request->input('gender', 'both');
        $interests = $request->input('interests', []);
        $languages = $request->input('languages', []);
        $relationGoals = $request->input('relation_goals', []);
    // clients will not pass coordinates; use authenticated user's location instead
        $latitude =  $userInformation->latitude;
        $longitude =  $userInformation->longitude;
        $radius = $request->radius;

        $user = $request->user();

        // Build raw SQL for speed with bindings
        $selectBase = "u.id,u.name,u.email,u.image,ui.bio,ui.gender,ui.date_of_birth,ui.relation_goals,ui.interests,ui.languages,ui.latitude,ui.longitude,ui.religion_id";

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

        $sql = "$select FROM users u JOIN user_information ui ON ui.user_id = u.id WHERE u.id != ?";
        $bindings[] = $user ? $user->id : 0;

        if ($q) {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $bindings[] = "%{$q}%";
            $bindings[] = "%{$q}%";
        }

        $religionId = $request->input('religion_id');

        if ($gender !== 'both') {
            $sql .= " AND ui.gender = ?";
            $bindings[] = $gender;
        }

        if ($religionId) {
            $sql .= " AND ui.religion_id = ?";
            $bindings[] = $religionId;
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
}