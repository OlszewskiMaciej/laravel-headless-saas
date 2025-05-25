<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Subscription;
use App\Policies\UserPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
     protected $policies = [
        User::class => UserPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register generic profile gates
        Gate::define('view', [ProfilePolicy::class, 'view']);
        Gate::define('update', [ProfilePolicy::class, 'update']);
    }
}
