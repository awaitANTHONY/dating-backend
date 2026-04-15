<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\UserMatch;
use App\Models\UserEngagementScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComputeEngagementScores extends Command
{
    protected $signature = 'engagement:compute
        {--user_id= : Compute for a specific user}
        {--all : Compute for all active users}';

    protected $description = 'Compute engagement scores for recommendation ranking';

    public function handle(): int
    {
        $startTime = microtime(true);

        if ($userId = $this->option('user_id')) {
            $users = User::where('id', $userId)->where('status', 1)->get();
        } elseif ($this->option('all')) {
            $users = User::where('status', 1)
                ->whereHas('user_information')
                ->get();
        } else {
            $this->error('Please specify --user_id=X or --all');
            return 1;
        }

        $this->info("Computing engagement scores for {$users->count()} users...");

        // Pre-compute max received likes per country for popularity normalization
        $maxLikesByCountry = $this->getMaxLikesByCountry();

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $processed = 0;

        foreach ($users->chunk(100) as $chunk) {
            foreach ($chunk as $user) {
                try {
                    $this->computeForUser($user, $maxLikesByCountry);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::error("Engagement score computation failed for user {$user->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Done. Processed {$processed}/{$users->count()} users in {$elapsed}s.");
        Log::info("Engagement scores computed for {$processed} users in {$elapsed}s");

        return 0;
    }

    private function computeForUser(User $user, array $maxLikesByCountry): void
    {
        $userId = $user->id;
        $countryCode = $user->user_information->country_code ?? 'unknown';

        // --- Raw metrics ---

        $receivedLikes = UserInteraction::where('target_user_id', $userId)
            ->where('action', 'like')
            ->count();

        $receivedLikes7d = UserInteraction::where('target_user_id', $userId)
            ->where('action', 'like')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $sentLikes = UserInteraction::where('user_id', $userId)
            ->where('action', 'like')
            ->count();

        $matchCount = UserMatch::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('target_user_id', $userId);
        })->whereNull('deleted_at')->count();

        $matchRate = $receivedLikes > 0
            ? min(1.0, $matchCount / $receivedLikes)
            : 0;

        // Profile completeness (10 key fields + up to 2 for images)
        $profileCompleteness = $this->computeProfileCompleteness($user);

        // Response rate: how many match chats did this user reply to
        $responseRate = $this->computeResponseRate($userId);

        // Get existing impressions count (preserved across computations)
        $existing = UserEngagementScore::where('user_id', $userId)->first();
        $impressionsCount = $existing->impressions_count ?? 0;
        $impressionsWithoutLike = max(0, $impressionsCount - $receivedLikes);

        // --- Sub-scores ---

        // 1. Popularity (0-1): normalized against max in same country
        $maxInCountry = $maxLikesByCountry[$countryCode] ?? 1;
        $popularityScore = min(1.0, $receivedLikes7d / max($maxInCountry, 1));

        // 2. Quality (0-1): blend of match rate and profile completeness
        //    (response_rate omitted — chat is Firebase, not MySQL)
        $qualityScore = ($matchRate * 0.6) + ($profileCompleteness * 0.4);

        // 3. Activity (0-1): based on last_activity
        $activityScore = $this->computeActivityScore($user);

        // 4. Freshness (0-1): new user boost + impression-to-like decay
        $freshnessScore = $this->computeFreshnessScore($user, $impressionsCount, $receivedLikes);

        // --- Final composite (0-10) ---
        $engagementScore = round(
            ($popularityScore * 0.30 + $qualityScore * 0.20 + $activityScore * 0.25 + $freshnessScore * 0.25) * 10,
            2
        );

        // Upsert
        UserEngagementScore::updateOrCreate(
            ['user_id' => $userId],
            [
                'received_likes_count' => $receivedLikes,
                'received_likes_7d' => $receivedLikes7d,
                'sent_likes_count' => $sentLikes,
                'match_count' => $matchCount,
                'match_rate' => round($matchRate, 4),
                'profile_completeness' => round($profileCompleteness, 4),
                'response_rate' => round($responseRate, 4),
                'impressions_count' => $impressionsCount,
                'impressions_without_like' => $impressionsWithoutLike,
                'popularity_score' => round($popularityScore, 4),
                'quality_score' => round($qualityScore, 4),
                'activity_score' => round($activityScore, 4),
                'freshness_score' => round($freshnessScore, 4),
                'engagement_score' => $engagementScore,
                'last_computed_at' => now(),
            ]
        );

        // Send push notification when user crosses the Popular threshold organically
        // Popular threshold: engagement_score >= 3.0 (maps to popularityScore >= 30 in Flutter)
        $previousScore = $existing->engagement_score ?? 0;
        $popularThreshold = 3.0;

        if ($previousScore < $popularThreshold && $engagementScore >= $popularThreshold) {
            // User just entered Popular — not boosted, purely organic
            if (empty($user->is_boosted) && $user->device_token) {
                try {
                    send_notification(
                        'single',
                        "You're Trending! ⚡",
                        "Your profile is getting noticed! You've made it to the Popular section. Keep the momentum going!",
                        null,
                        [
                            'device_token' => $user->device_token,
                            'type' => 'popular_achieved',
                        ]
                    );
                    Log::info("Popular notification sent to user {$userId} (score: {$engagementScore})");
                } catch (\Throwable $e) {
                    Log::warning("Failed to send Popular notification to user {$userId}: {$e->getMessage()}");
                }
            }
        }
    }

    private function computeProfileCompleteness(User $user): float
    {
        $info = $user->user_information;
        if (!$info) return 0;

        $fields = [
            'bio', 'religion_id', 'relationship_status_id', 'ethnicity_id',
            'education_id', 'carrer_field_id', 'alkohol', 'smoke', 'age', 'height',
        ];

        $filled = 0;
        foreach ($fields as $field) {
            if (!empty($info->$field)) $filled++;
        }

        // Bonus for images (up to 2 points)
        $images = is_array($info->images) ? $info->images : json_decode($info->images ?? '[]', true);
        $imageBonus = min(2, count($images ?? []));

        return min(1.0, ($filled + $imageBonus) / 12);
    }

    private function computeResponseRate(int $userId): float
    {
        // Chat is handled by Firebase, not MySQL — return 0 (neutral)
        // Response rate will be factored out of quality_score weighting
        return 0;
    }

    private function computeActivityScore(User $user): float
    {
        if (!$user->last_activity) return 0;

        $minutesAgo = $user->last_activity->diffInMinutes(now());

        if ($minutesAgo < 60) return 1.0;
        if ($minutesAgo < 360) return 0.8;       // < 6 hours
        if ($minutesAgo < 1440) return 0.6;      // < 24 hours
        if ($minutesAgo < 4320) return 0.3;      // < 3 days
        if ($minutesAgo < 10080) return 0.1;     // < 7 days
        return 0;
    }

    private function computeFreshnessScore(User $user, int $impressions, int $receivedLikes): float
    {
        if (!$user->created_at) return 0.5;

        $hoursOld = $user->created_at->diffInHours(now());

        // New user boost
        if ($hoursOld < 48) return 1.0;
        if ($hoursOld < 168) return 0.8; // < 7 days

        // For older accounts: decay based on impression-to-like ratio
        if ($impressions === 0) return 0.5; // No data yet, neutral

        $likeRate = $receivedLikes / $impressions;
        return max(0.1, min(1.0, $likeRate * 2));
    }

    private function getMaxLikesByCountry(): array
    {
        $results = DB::table('user_interactions as ui')
            ->join('user_information as info', 'info.user_id', '=', 'ui.target_user_id')
            ->where('ui.action', 'like')
            ->where('ui.created_at', '>=', now()->subDays(7))
            ->select('info.country_code', 'ui.target_user_id', DB::raw('COUNT(*) as like_count'))
            ->groupBy('info.country_code', 'ui.target_user_id')
            ->get();

        $maxByCountry = [];
        foreach ($results as $row) {
            $cc = $row->country_code ?? 'unknown';
            if (!isset($maxByCountry[$cc]) || $row->like_count > $maxByCountry[$cc]) {
                $maxByCountry[$cc] = $row->like_count;
            }
        }

        return $maxByCountry;
    }
}
