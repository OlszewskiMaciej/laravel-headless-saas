<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Disable API rate limiting for now (requires Redis)
        // $middleware->throttleApi(60, 'api');
        
        // Add CORS headers to API responses
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        
        // Add common web middleware
        $middleware->web(append: [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
