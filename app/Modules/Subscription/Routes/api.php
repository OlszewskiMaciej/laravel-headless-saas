<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Subscription\Controllers\SubscriptionController;
use App\Modules\Subscription\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Subscription Module API Routes
|--------------------------------------------------------------------------
|
| Subscription management routes including plans, billing, webhooks,
| and Stripe integration.
|
*/

// Stripe webhook route (webhook secret secured instead of API key)
Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook']);

// Protected routes with both API key and user authentication
Route::middleware(['api-key', 'auth:sanctum'])->prefix('subscription')->name('subscription.')->group(function () {
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
