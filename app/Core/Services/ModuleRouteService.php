<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class ModuleRouteService
{
    /**
     * Load all module routes.
     */
    public function loadAllModuleRoutes(): void
    {
        if (!config('modules.enabled', true)) {
            return;
        }

        $modulesPath = config('modules.modules_path', app_path('Modules'));

        if (!File::exists($modulesPath)) {
            return;
        }

        $modules = $this->getModulesFromCache();

        foreach ($modules as $modulePath) {
            $this->loadModuleRoutes($modulePath);
        }
    }

    /**
     * Get modules from cache or scan directory.
     */
    protected function getModulesFromCache(): array
    {
        if (!config('modules.cache_enabled', true)) {
            return $this->scanModules();
        }

        // Avoid cache usage if cache table does not exist (e.g. during install)
        try {
            if (Schema::hasTable('cache')) {
                return Cache::remember(
                    config('modules.cache_key', 'module_routes_list'),
                    config('modules.cache_ttl', 3600),
                    fn () => $this->scanModules()
                );
            }
        } catch (\Exception $e) {
            // Fallback if cache table is missing or migration not run
            return $this->scanModules();
        }

        return $this->scanModules();
    }

    /**
     * Scan modules directory.
     */
    protected function scanModules(): array
    {
        $modulesPath = config('modules.modules_path', app_path('Modules'));

        if (!File::exists($modulesPath)) {
            return [];
        }

        return File::directories($modulesPath);
    }

    /**
     * Load routes for a specific module.
     */
    public function loadModuleRoutes(string $modulePath): void
    {
        $moduleName = basename($modulePath);

        // Skip excluded modules
        if (in_array($moduleName, config('modules.excluded_modules', []))) {
            return;
        }

        $routesPath = $modulePath . '/Routes';

        if (!File::exists($routesPath)) {
            return;
        }

        $this->loadApiRoutes($routesPath, $moduleName);
        $this->loadWebRoutes($routesPath, $moduleName);
    }

    /**
     * Load API routes for a module.
     */
    protected function loadApiRoutes(string $routesPath, string $moduleName): void
    {
        $apiRoutesFile = $routesPath . '/' . config('modules.route_files.api', 'api.php');

        if (File::exists($apiRoutesFile)) {
            Route::middleware(config('modules.middleware.api', ['api']))
                ->prefix(config('modules.prefixes.api', 'api'))
                ->name(config('modules.name_prefixes.api', 'api') . '.')
                ->group($apiRoutesFile);
        }
    }

    /**
     * Load web routes for a module.
     */
    protected function loadWebRoutes(string $routesPath, string $moduleName): void
    {
        $webRoutesFile = $routesPath . '/' . config('modules.route_files.web', 'web.php');

        if (File::exists($webRoutesFile)) {
            Route::middleware(config('modules.middleware.web', ['web']))
                ->prefix(config('modules.prefixes.web'))
                ->name(config('modules.name_prefixes.web') ? config('modules.name_prefixes.web') . '.' : '')
                ->group($webRoutesFile);
        }
    }

    /**
     * Clear module routes cache.
     */
    public function clearCache(): void
    {
        Cache::forget(config('modules.cache_key', 'module_routes_list'));
    }

    /**
     * Get all available modules.
     */
    public function getModules(): array
    {
        $modulesPath = config('modules.modules_path', app_path('Modules'));

        if (!File::exists($modulesPath)) {
            return [];
        }

        $modules = File::directories($modulesPath);

        return collect($modules)
            ->map(fn ($path) => basename($path))
            ->filter(fn ($module) => !in_array($module, config('modules.excluded_modules', [])))
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Check if a module has routes.
     */
    public function moduleHasRoutes(string $moduleName): bool
    {
        $routesPath = app_path("Modules/{$moduleName}/Routes");

        return File::exists($routesPath) && (
            File::exists($routesPath . '/' . config('modules.route_files.api', 'api.php')) || File::exists($routesPath . '/' . config('modules.route_files.web', 'web.php'))
        );
    }

    /**
     * Get route count for a module.
     */
    public function getModuleRouteCount(string $moduleName): int
    {
        $routesPath = app_path("Modules/{$moduleName}/Routes");
        $count      = 0;

        $apiFile = $routesPath . '/' . config('modules.route_files.api', 'api.php');
        if (File::exists($apiFile)) {
            $count += $this->countRoutesInFile($apiFile);
        }

        $webFile = $routesPath . '/' . config('modules.route_files.web', 'web.php');
        if (File::exists($webFile)) {
            $count += $this->countRoutesInFile($webFile);
        }

        return $count;
    }

    /**
     * Count routes in a file (simple estimation).
     */
    protected function countRoutesInFile(string $filePath): int
    {
        $content = File::get($filePath);

        return preg_match_all('/Route::(get|post|put|patch|delete|options|any|match|resource|apiResource)/', $content);
    }
}
