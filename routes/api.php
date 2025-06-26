<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Subscription\Controllers\SubscriptionController;
use App\Modules\Subscription\Controllers\WebhookController;
use App\Modules\User\Controllers\ProfileController;
use App\Modules\Admin\Controllers\UserController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\ActivityLogController;
use App\Modules\Admin\Controllers\ApiKeyController;

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
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});

// Protected routes with both API key and user authentication
Route::middleware(['api-key', 'auth:sanctum'])->group(function () {
    Route::prefix('subscription')->name('subscription.')->group(function () {
        // Existing subscription routes
        // Route::post('/', [SubscriptionController::class, 'subscribe'])->name('subscribe')->middleware('permission:subscribe to plan');
        Route::get('/', [SubscriptionController::class, 'show'])->name('show');
        // Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('cancel')->middleware('permission:cancel subscription');
        // Route::post('resume', [SubscriptionController::class, 'resume'])->name('resume')->middleware('permission:resume subscription');
        Route::post('start-trial', [SubscriptionController::class, 'startTrial'])->name('start-trial')->middleware('permission:start trial');
        // Route::post('payment-method', [SubscriptionController::class, 'updatePaymentMethod'])->name('update-payment-method');
        // Route::get('invoice', [SubscriptionController::class, 'getInvoice'])->name('get-invoice')->middleware('permission:get invoice');
        // Route::get('invoices', [SubscriptionController::class, 'listInvoices'])->name('list-invoices')->middleware('permission:get invoice');
        
        // New Stripe Checkout and Billing Portal routes
        Route::post('checkout', [SubscriptionController::class, 'checkout'])->name('checkout')->middleware('permission:subscribe to plan');
        Route::post('billing-portal', [SubscriptionController::class, 'billingPortal'])->name('billing-portal');
    });
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
        Route::apiResource('roles', RoleController::class)
            ->middleware([
                'index' => 'permission:view roles',
                'store' => 'permission:create roles',
                'show' => 'permission:view roles',
                'update' => 'permission:update roles',
                'destroy' => 'permission:delete roles'
            ]);
        
        // Activity logs
        Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index')->middleware('permission:view activity logs');
        
        // API Key Management
        Route::prefix('api-keys')->name('api-keys.')->middleware('permission:manage api keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index'])->name('index');
            Route::post('/', [ApiKeyController::class, 'store'])->name('store');
            Route::get('/{apiKey}', [ApiKeyController::class, 'show'])->name('show');
            Route::put('/{apiKey}', [ApiKeyController::class, 'update'])->name('update');
            Route::post('/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('revoke');
            Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy'])->name('destroy');
        });
    });
});
