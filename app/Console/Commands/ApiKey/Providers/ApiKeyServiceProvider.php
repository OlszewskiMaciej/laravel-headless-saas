<?php

namespace App\Console\Commands\ApiKey\Providers;

use App\Console\Commands\ApiKey\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Console\Commands\ApiKey\Services\ApiKeyService;
use Illuminate\Support\ServiceProvider;

class ApiKeyServiceProvider extends ServiceProvider
{
    /**
     * Register service bindings.
     */
    public function register(): void
    {
        $this->app->singleton(ApiKeyService::class, function ($app) {
            return new ApiKeyService(
                $app->make(ApiKeyRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
