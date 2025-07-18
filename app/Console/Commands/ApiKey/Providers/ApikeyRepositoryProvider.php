<?php

namespace App\Console\Commands\ApiKey\Providers;

use App\Console\Commands\ApiKey\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Console\Commands\ApiKey\Repositories\ApiKeyRepository;
use Illuminate\Support\ServiceProvider;

class ApiKeyRepositoryProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     */
    public function register(): void
    {
        $this->app->bind(
            ApiKeyRepositoryInterface::class,
            ApiKeyRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
