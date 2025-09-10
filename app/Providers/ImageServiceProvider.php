<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('image', function ($app) {
            return new ImageManager(new Driver());
        });
        
        // Register the facade alias
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Image', \App\Facades\Image::class);
    }

    public function boot()
    {
        //
    }
}
