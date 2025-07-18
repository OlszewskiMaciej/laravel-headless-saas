<?php

namespace App\Modules\User\Providers;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class UserRepositoryProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
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
