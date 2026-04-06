<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily soulmates cache refresh at 12:00 AM (midnight)
Schedule::command('soulmates:refresh --all')
    ->dailyAt('00:00')
    ->name('refresh-daily-soulmates')
    ->description('Refresh soulmates cache for all users daily at midnight')
    ->onSuccess(function () {
        \Log::info('Daily soulmates refresh completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Daily soulmates refresh failed');
    });

// Schedule mood cleanup every hour to remove expired moods
Schedule::command('moods:cleanup')
    ->hourly()
    ->name('cleanup-expired-moods')
    ->description('Remove expired user moods (older than 24 hours)')
    ->onSuccess(function () {
        \Log::info('Mood cleanup completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Mood cleanup failed');
    });

// Schedule AI image monitoring scan every hour
Schedule::command('images:scan')
    ->hourly()
    ->name('scan-user-images')
    ->description('Scan user images for AI moderation (reject bad images)')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Image scan completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Image scan failed');
    });

// Expire pending Direct Connect requests older than 7 days
Schedule::command('direct-connect:expire')
    ->hourly()
    ->name('expire-contact-requests')
    ->description('Expire pending contact requests and notify requesters')
    ->onSuccess(function () {
        \Log::info('Direct Connect expire check completed');
    })
    ->onFailure(function () {
        \Log::error('Direct Connect expire check failed');
    });

// Grant daily free coins to active subscribers (Premium, Gold, VIP)
Schedule::command('coins:grant-daily-subscriber')
    ->dailyAt('00:05')
    ->name('grant-daily-subscriber-coins')
    ->description('Grant daily free coins to subscribers based on their tier')
    ->onSuccess(function () {
        \Log::info('Daily subscriber coin grant completed');
    })
    ->onFailure(function () {
        \Log::error('Daily subscriber coin grant failed');
    });
