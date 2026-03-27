<?php

namespace App\Console\Commands;

use App\Jobs\GenerateThumbnails;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateExistingThumbnails extends Command
{
    protected $signature = 'thumbnails:generate {--user= : Generate for a specific user ID}';
    protected $description = 'Generate thumbnails for all existing user images';

    public function handle(): int
    {
        $query = User::whereNotNull('image');

        if ($userId = $this->option('user')) {
            $query->where('id', $userId);
        }

        $total = $query->count();
        $this->info("Processing {$total} users...");
        $bar = $this->output->createProgressBar($total);

        $query->with('user_information')->chunk(50, function ($users) use ($bar) {
            foreach ($users as $user) {
                // Profile image
                $rawImage = $user->getRawOriginal('image');
                if ($rawImage) {
                    GenerateThumbnails::dispatch($rawImage);
                }

                // Gallery images
                $userInfo = $user->user_information;
                if ($userInfo) {
                    $rawImages = $userInfo->getRawOriginal('images');
                    $paths = is_string($rawImages) ? json_decode($rawImages, true) : $rawImages;
                    if (is_array($paths)) {
                        foreach ($paths as $path) {
                            if ($path) {
                                GenerateThumbnails::dispatch($path);
                            }
                        }
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Thumbnail generation jobs dispatched. Run `php artisan queue:work` to process them.');

        return 0;
    }
}
