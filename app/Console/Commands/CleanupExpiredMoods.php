<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserMood;
use Carbon\Carbon;

class CleanupExpiredMoods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moods:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired user moods (older than 24 hours)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired user moods...');

        try {
            $deletedCount = UserMood::cleanupExpired();
            
            $this->info("Successfully cleaned up {$deletedCount} expired mood(s).");
            
            return 0; // Success exit code
            
        } catch (\Exception $e) {
            $this->error('Failed to cleanup expired moods: ' . $e->getMessage());
            
            return 1; // Error exit code
        }
    }
}