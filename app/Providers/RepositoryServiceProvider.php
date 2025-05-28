<?php

namespace App\Providers;

use App\Modules\Admin\Repositories\Interfaces\ActivityLogRepositoryInterface;
use App\Modules\Admin\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Modules\Admin\Repositories\Interfaces\RoleRepositoryInterface;
use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Modules\Admin\Repositories\ActivityLogRepository;
use App\Modules\Admin\Repositories\ApiKeyRepository;
use App\Modules\Admin\Repositories\RoleRepository;
use App\Modules\Subscription\Repositories\SubscriptionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     */
    public function register(): void
    {
        // Bind API Key Repository
        $this->app->bind(
            ApiKeyRepositoryInterface::class,
            ApiKeyRepository::class
        );

        // Bind Role Repository
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );

        // Bind Activity Log Repository
        $this->app->bind(
            ActivityLogRepositoryInterface::class,
            ActivityLogRepository::class
        );

        // Bind Subscription Repository
        $this->app->bind(
            SubscriptionRepositoryInterface::class,
            SubscriptionRepository::class
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
