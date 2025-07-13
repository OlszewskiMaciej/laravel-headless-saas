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
            'interval' => 'month',
            'currencies' => [
                'PLN' => [
                    'stripe_id' => env('STRIPE_MONTHLY_PLAN_PLN_ID', 'price_monthly_pln'),
                    'price' => 10,
                ],
                'USD' => [
                    'stripe_id' => env('STRIPE_MONTHLY_PLAN_USD_ID', 'price_monthly_usd'),
                    'price' => 10,
                ],
                'EUR' => [
                    'stripe_id' => env('STRIPE_MONTHLY_PLAN_EUR_ID', 'price_monthly_eur'),
                    'price' => 10,
                ],
            ],
        ],
        'annual' => [
            'name' => 'Annual Plan',
            'interval' => 'year',
            'currencies' => [
                'PLN' => [
                    'stripe_id' => env('STRIPE_ANNUAL_PLAN_PLN_ID', 'price_annual_pln'),
                    'price' => 100,
                ],
                'USD' => [
                    'stripe_id' => env('STRIPE_ANNUAL_PLAN_USD_ID', 'price_annual_usd'),
                    'price' => 100,
                ],
                'EUR' => [
                    'stripe_id' => env('STRIPE_ANNUAL_PLAN_EUR_ID', 'price_annual_eur'),
                    'price' => 100,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | Here you can define the currencies supported by your application.
    |
    */
    
    'currencies' => [
        'PLN' => [
            'name' => 'Polish Złoty',
            'symbol' => 'zł',
            'default' => true,
        ],
        'USD' => [
            'name' => 'US Dollar',
            'symbol' => '$',
            'default' => false,
        ],
        'EUR' => [
            'name' => 'Euro',
            'symbol' => '€',
            'default' => false,
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
