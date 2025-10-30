<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Bind ImageManager to the container
        $this->app->singleton('imagemanager', function ($app) {
            return new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        });

        // Fallback registration for FirebaseService when Firebase database is not available
        $this->app->singleton(\App\Services\FirebaseService::class, function ($app) {
            try {
                // Try to resolve Firebase database from container
                $database = $app->make(\Kreait\Firebase\Database::class);
                return new \App\Services\FirebaseService($database);
            } catch (\Exception $e) {
                // If Firebase database cannot be resolved, create service with null database
                \Log::warning('Firebase database not available, creating FirebaseService with null database', [
                    'error' => $e->getMessage()
                ]);
                return new \App\Services\FirebaseService(null);
            }
        });

        // Firebase-specific bindings have been moved to
        // App\Providers\FirebaseServiceProvider to keep this provider focused
        // on application-wide services. PSR-7/PSR-17 bindings are registered
        // there as well so Kreait and Guzzle dependencies can be resolved.
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
    }
}
