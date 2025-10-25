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
