<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure standard API responses
        Response::macro('success', function ($data = null, string $message = 'Success', int $statusCode = 200) {
            return Response::json([
                'status'  => 'success',
                'message' => $message,
                'data'    => $data,
            ], $statusCode);
        });

        Response::macro('error', function (string $message = 'Error', int $statusCode = 400, $errors = null) {
            return Response::json([
                'status'  => 'error',
                'message' => $message,
                'errors'  => $errors,
            ], $statusCode);
        });
    }
}
