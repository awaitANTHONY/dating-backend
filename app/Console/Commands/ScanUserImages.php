<?php

namespace App\Console\Commands;

use App\Jobs\MonitorUserImages;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScanUserImages extends Command
{
    protected $signature = 'images:scan {--limit= : Limit the number of users to scan} {--batch=1000 : Max users per hourly run}';

    protected $description = 'Scan user images for moderation using AI (profile + gallery batch)';

    public function handle(): int
    {
        $this->info('Starting image scan...');

        $delay = 0;
        $dispatched = 0;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $batch = (int) $this->option('batch');
        $maxPerRun = $limit ?? $batch;

        User::whereNotNull('image')
            ->where(function ($query) {
                $query->whereNull('last_scanned_at')
                      ->orWhere('last_scanned_at', '<', now()->subDays(7));
            })
            ->chunkById(200, function ($users) use (&$delay, &$dispatched, $maxPerRun) {
                foreach ($users as $user) {
                    if ($dispatched >= $maxPerRun) {
                        return false;
                    }

                    MonitorUserImages::dispatch($user->id)
                        ->delay(now()->addSeconds($delay));

                    $delay += rand(2, 4);
                    $dispatched++;
                }

                if ($dispatched >= $maxPerRun) {
                    return false;
                }
            });

        $this->info("Dispatched {$dispatched} image monitoring jobs.");
        Log::info("ScanUserImages: Dispatched {$dispatched} jobs.");

        return self::SUCCESS;
    }
}
