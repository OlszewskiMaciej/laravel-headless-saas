<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Here you can define the subscription plans for your application.
    |
    */
    
    'plans' => [
        'monthly' => [
            'name' => 'Monthly Plan',
            'stripe_id' => env('STRIPE_MONTHLY_PLAN_ID', 'price_monthly'),
            'price' => 10,
            'interval' => 'month',
        ],
        'annual' => [
            'name' => 'Annual Plan',
            'stripe_id' => env('STRIPE_ANNUAL_PLAN_ID', 'price_annual'),
            'price' => 100,
            'interval' => 'year',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Trial Period
    |--------------------------------------------------------------------------
    |
    | Here you can define the trial period for new users.
    |
    */
    
    'trial_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of the subscription fallback system
    | when Stripe API is unavailable.
    |
    */
    
    'fallback' => [
        /*
        | Enable fallback to local database when Stripe API fails
        */
        'enabled' => env('SUBSCRIPTION_FALLBACK_ENABLED', true),

        /*
        | Maximum age of local subscription data to consider valid (in hours)
        | If local data is older than this, it will be marked as potentially stale
        */
        'max_local_data_age_hours' => env('SUBSCRIPTION_FALLBACK_MAX_AGE', 72),

        /*
        | Log fallback events for monitoring
        */
        'log_fallback_events' => env('SUBSCRIPTION_LOG_FALLBACK', true),
    ],
];
