<?php

namespace App\Providers;

use App\Console\Commands\ApiKey\Providers\ApiKeyRepositoryProvider;
use App\Modules\Subscription\Providers\SubscriptionRepositoryProvider;
use App\Modules\User\Providers\UserRepositoryProvider;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     */
    public function register(): void
    {
        $this->app->register(UserRepositoryProvider::class);
        $this->app->register(ApiKeyRepositoryProvider::class);
        $this->app->register(SubscriptionRepositoryProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
