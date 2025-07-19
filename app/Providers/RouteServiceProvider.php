<?php

namespace App\Providers;

use App\Core\Services\ModuleRouteService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->loadModuleRoutes();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Disabled rate limiter since we're not using Redis
        // RateLimiter::for('api', function (Request $request) {
        //     return Limit::perMinute(60)->by($request->user()?->uuid ?: $request->ip());
        // });
    }

    /**
     * Load routes from all modules automatically.
     */
    protected function loadModuleRoutes(): void
    {
        $routeService = new ModuleRouteService();
        $routeService->loadAllModuleRoutes();
    }
}
