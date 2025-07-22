<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\PasswordResetController;

/*
|--------------------------------------------------------------------------
| Auth Module API Routes
|--------------------------------------------------------------------------
|
| Authentication related routes including registration, login, logout,
| password reset functionality.
|
*/

Route::middleware('api-key')->prefix('auth')->name('auth.')->group(function () {
    // Public auth routes (require API key but no user auth)
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('reset-password');

    // Protected auth routes (require both API key and user auth)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
