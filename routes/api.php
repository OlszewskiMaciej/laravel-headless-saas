<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\User\Controllers\ProfileController;
use App\Modules\Admin\Controllers\UserController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\ActivityLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and will be assigned to the
| "api" middleware group. Make something great!
|
*/

// Basic API health route
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working',
        'data' => [
            'version' => '1.0',
            'environment' => app()->environment(),
        ]
    ]);
});

// Public auth routes (require API key but no user auth)
Route::post('register', [AuthController::class, 'register'])->name('register');
Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('forgot-password');
Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('reset-password');
    
// Protected auth routes (require both API key and user auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');
});
Route::middleware('auth:sanctum')->group(function () {
      // User routes
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    });
      // Admin routes

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('permission:view users');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('permission:create users');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show')->middleware('permission:show users');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:update users');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:delete users');
        
        // Roles and permissions routes
        Route::apiResource('roles', RoleController::class)->middleware('permission:view roles,create roles,update roles,delete roles');
        
        // Activity logs
        Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index')->middleware('permission:view activity logs');
    });
    });
