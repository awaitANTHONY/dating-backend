<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshSoulmatesCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'soulmates:refresh {--user_id= : Refresh for specific user ID} {--all : Refresh for all active users}';

    /**
     * The console command description.
     */
    protected $description = 'Refresh the daily soulmates cache for users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting soulmates cache refresh...');
        
        $userId = $this->option('user_id');
        $refreshAll = $this->option('all');
        
        if ($userId) {
            // Refresh for specific user
            $this->refreshUserSoulmates($userId);
        } elseif ($refreshAll) {
            // Refresh for all active users with complete profiles
            $this->refreshAllUsersSoulmates();
        } else {
            $this->error('Please specify either --user_id=ID or --all option');
            return 1;
        }
        
        $this->info('Soulmates cache refresh completed!');
        return 0;
    }
    
    /**
     * Refresh soulmates cache for a specific user
     */
    private function refreshUserSoulmates($userId)
    {
        $user = User::with('user_information')->find($userId);
        
        if (!$user || !$user->user_information) {
            $this->error("User {$userId} not found or has incomplete profile");
            return;
        }
        
        // Clear existing cache
        $today = now()->format('Y-m-d');
        $cacheKey = "soulmates_user_{$userId}_date_{$today}";
        Cache::forget($cacheKey);
        
        // Pre-warm cache by calling the soulmates method
        try {
            $request = new \Illuminate\Http\Request();
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            
            $controller = new \App\Http\Controllers\Api\v1\ProfileController();
            $response = $controller->soulmates($request);
            
            if ($response->getStatusCode() === 200) {
                $this->info("✓ Refreshed soulmates for user {$userId}");
                Log::info("Soulmates cache refreshed for user {$userId}");
            } else {
                $this->warn("⚠ Issue refreshing soulmates for user {$userId}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Failed to refresh soulmates for user {$userId}: " . $e->getMessage());
            Log::error("Failed to refresh soulmates for user {$userId}", ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Refresh soulmates cache for all active users
     */
    private function refreshAllUsersSoulmates()
    {
        // Get all active users with complete profiles
        $users = User::with('user_information')
            ->where('status', 1)
            ->whereHas('user_information', function($q) {
                $q->whereNotNull('bio')
                  ->whereNotNull('age')
                  ->whereNotNull('gender')
                  ->whereNotNull('search_preference')
                  ->where('bio', '!=', '')
                  ->where('age', '>', 0);
            })
            ->get();
        
        $this->info("Found {$users->count()} active users with complete profiles");
        
        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($users as $user) {
            try {
                $this->refreshUserSoulmates($user->id);
                $successCount++;
                
                // Small delay to prevent overwhelming the system
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Failed to refresh soulmates for user {$user->id}", ['error' => $e->getMessage()]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info("Refresh completed:");
        $this->info("✓ Success: {$successCount} users");
        
        if ($errorCount > 0) {
            $this->warn("⚠ Errors: {$errorCount} users");
        }
        
        Log::info("Bulk soulmates cache refresh completed", [
            'total_users' => $users->count(),
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
    }
}