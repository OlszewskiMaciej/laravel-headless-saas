<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Routes Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for module-based routing system.
    |
    */

    'enabled' => true,

    'modules_path' => app_path('Modules'),

    'route_files' => [
        'api' => 'api.php',
        'web' => 'web.php',
    ],

    'middleware' => [
        'api' => ['api'],
        'web' => ['web'],
    ],

    'prefixes' => [
        'api' => 'api',
        'web' => null,
    ],

    'name_prefixes' => [
        'api' => 'api',
        'web' => null,
    ],

    'excluded_modules' => [
        // List modules that should not have routes auto-loaded
        // 'ExampleModule',
    ],

    'cache_enabled' => env('MODULE_ROUTES_CACHE', true),

    'cache_key' => 'module_routes_list',

    'cache_ttl' => 3600, // 1 hour
];
