<?php

namespace App\Providers;

use App\Modules\Admin\Repositories\Interfaces\ActivityLogRepositoryInterface;
use App\Modules\Admin\Repositories\ActivityLogRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     */
    public function register(): void
    {
        // Bind Activity Log Repository
        $this->app->bind(
            ActivityLogRepositoryInterface::class,
            ActivityLogRepository::class
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
