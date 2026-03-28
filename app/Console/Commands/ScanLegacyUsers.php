<?php

namespace App\Console\Commands;

use App\Jobs\ScanUserImagesBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * images:scan-legacy
 *
 * Scans all users who have never been moderated (last_scanned_at IS NULL)
 * and have at least a profile image or gallery images.
 *
 * Dispatches ScanUserImagesBatch jobs in chunks of 20, each delayed by
 * 3 seconds × batch index to space out processing on the queue.
 *
 * Self-stops: when no unscanned users remain, logs completion and exits.
 */
class ScanLegacyUsers extends Command
{
    protected $signature   = 'images:scan-legacy';
    protected $description = 'Batch-scan all unscanned users with AWS Rekognition (legacy backfill)';

    private const BATCH_SIZE    = 20;
    private const DELAY_SECONDS = 3;

    public function handle(): int
    {
        // ── Count unscanned users ─────────────────────────────────────────────
        $totalUnscanned = DB::table('users as u')
            ->leftJoin('user_information as ui', 'ui.user_id', '=', 'u.id')
            ->whereNull('u.last_scanned_at')
            ->where('u.status', 1)
            ->where(function ($q) {
                $q->whereNotNull('u.image')
                  ->where('u.image', '!=', '')
                  ->where('u.image', 'not like', '%default/profile%');
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('ui.images')
                  ->where('ui.images', '!=', '[]')
                  ->where('ui.images', '!=', '');
            })
            ->whereNull('u.last_scanned_at')
            ->where('u.status', 1)
            ->distinct('u.id')
            ->count('u.id');

        if ($totalUnscanned === 0) {
            $this->info('Legacy scan complete — all users scanned.');
            Log::info('[ScanLegacyUsers] Legacy scan complete — all users scanned.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalUnscanned} unscanned users. Dispatching batches of " . self::BATCH_SIZE . "...");
        Log::info("[ScanLegacyUsers] Starting legacy scan", ['unscanned_count' => $totalUnscanned]);

        // ── Fetch user IDs ────────────────────────────────────────────────────
        $userIds = DB::table('users as u')
            ->leftJoin('user_information as ui', 'ui.user_id', '=', 'u.id')
            ->whereNull('u.last_scanned_at')
            ->where('u.status', 1)
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('u.image')
                          ->where('u.image', '!=', '')
                          ->where('u.image', 'not like', '%default/profile%');
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('ui.images')
                          ->where('ui.images', '!=', '[]')
                          ->where('ui.images', '!=', '');
                });
            })
            ->orderBy('u.created_at')
            ->distinct()
            ->pluck('u.id')
            ->toArray();

        // ── Dispatch batches with staggered delays ────────────────────────────
        $batches    = array_chunk($userIds, self::BATCH_SIZE);
        $batchCount = count($batches);

        foreach ($batches as $index => $batch) {
            $delaySeconds = $index * self::DELAY_SECONDS;

            ScanUserImagesBatch::dispatch($batch)
                ->delay(now()->addSeconds($delaySeconds));
        }

        // ── Report ────────────────────────────────────────────────────────────
        $estimatedMinutes = ($batchCount * self::DELAY_SECONDS) / 60;
        $estimatedMinutes = ceil($estimatedMinutes);

        $this->info("Dispatched {$batchCount} batches covering {$totalUnscanned} users.");
        $this->info("Estimated processing time: ~{$estimatedMinutes} minute(s) (queue delay only — actual scan time is additional).");

        Log::info("[ScanLegacyUsers] Dispatched batches", [
            'batches'            => $batchCount,
            'users'              => $totalUnscanned,
            'estimated_delay_s'  => $batchCount * self::DELAY_SECONDS,
        ]);

        return self::SUCCESS;
    }
}
