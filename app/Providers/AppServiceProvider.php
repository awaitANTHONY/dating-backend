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
