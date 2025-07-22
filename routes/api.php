<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and will be assigned to the
| "api" middleware group. Make something great!
|
| Note: Module-specific routes are now automatically loaded from their
| respective app/Modules/{ModuleName}/Routes/api.php files.
|
*/

// Main API health check route
Route::get('/up', function () {
    return response()->json([
        'status'    => 'up',
        'timestamp' => now()->toISOString(),
        'version'   => config('app.version', '1.0.0')
    ]);
});
