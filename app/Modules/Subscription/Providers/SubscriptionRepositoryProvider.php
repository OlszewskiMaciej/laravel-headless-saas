<?php

namespace App\Modules\Subscription\Providers;

use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Repositories\SubscriptionRepository;
use Illuminate\Support\ServiceProvider;

class SubscriptionRepositoryProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     */
    public function register(): void
    {
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
