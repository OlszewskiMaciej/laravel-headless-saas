<?php

namespace App\Providers;

use App\Modules\Admin\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Services\ApiKeyService;
use Illuminate\Support\ServiceProvider;

class ApiKeyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
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
