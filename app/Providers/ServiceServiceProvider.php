<?php

namespace App\Providers;

use App\Console\Commands\ApiKey\Providers\ApiKeyServiceProvider;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    /**
     * Register service bindings.
     */
    public function register(): void
    {
        $this->app->register(ApiKeyServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
