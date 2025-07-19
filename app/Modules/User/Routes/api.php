<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| User Module API Routes
|--------------------------------------------------------------------------
|
| User management routes including profile management and user-related
| functionality.
|
*/

// Protected routes with both API key and user authentication
Route::middleware(['api-key', 'auth:sanctum'])->prefix('user')->name('user.')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
});
