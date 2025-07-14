<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for expired trials and downgrade users
Schedule::command('subscription:check-expired-trials')
    ->daily()
    ->at('02:00')
    ->timezone('UTC')
    ->description('Check for expired trials and downgrade users to free role')
    ->onSuccess(function () {
        Log::info('Expired trials check command completed successfully');
    })
    ->onFailure(function () {
        Log::error('Expired trials check command failed');
    });

// Sync subscription data from Stripe to local database and update user roles
Schedule::command('subscription:sync --days=1 --sync-roles')
    ->hourly()
    ->description('Synchronize Stripe subscription data with local database and update user roles')
    ->onSuccess(function () {
        Log::info('Subscription sync completed successfully');
    })
    ->onFailure(function () {
        Log::error('Subscription sync command failed');
    });
