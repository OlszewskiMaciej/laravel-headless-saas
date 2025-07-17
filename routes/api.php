<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Subscription\Controllers\SubscriptionController;
use App\Modules\Subscription\Controllers\WebhookController;
use App\Modules\User\Controllers\ProfileController;

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
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working',
        'data' => [
            'version' => '1.0',
            'environment' => app()->environment(),
        ]
    ]);
});

// Stripe webhook route (webhook secret secured instead of API key)
Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);

// Auth routes are protected with API key middleware
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

// Protected routes with both API key and user authentication
Route::middleware(['api-key', 'auth:sanctum'])->group(function () {
    Route::prefix('subscription')->name('subscription.')->group(function () {
        // Existing subscription routes
        Route::get('/', [SubscriptionController::class, 'show'])->name('show');
        Route::post('start-trial', [SubscriptionController::class, 'startTrial'])->name('start-trial')->middleware('permission:start trial');
        
        // New Stripe Checkout and Billing Portal routes
        Route::post('checkout', [SubscriptionController::class, 'checkout'])->name('checkout');
        Route::post('billing-portal', [SubscriptionController::class, 'billingPortal'])->name('billing-portal');
        
        // Currency and plan information routes
        Route::get('currencies', [SubscriptionController::class, 'currencies'])->name('currencies');
        Route::get('plans', [SubscriptionController::class, 'plans'])->name('plans');
    });
      // User routes
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});
