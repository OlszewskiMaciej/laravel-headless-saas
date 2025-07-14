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
                    'fallback_price' => 10, // Used only when Stripe API is unavailable
                ],
                'USD' => [
                    'stripe_id' => env('STRIPE_MONTHLY_PLAN_USD_ID', 'price_monthly_usd'),
                    'fallback_price' => 10, // Used only when Stripe API is unavailable
                ],
                'EUR' => [
                    'stripe_id' => env('STRIPE_MONTHLY_PLAN_EUR_ID', 'price_monthly_eur'),
                    'fallback_price' => 10, // Used only when Stripe API is unavailable
                ],
            ],
        ],
        'annual' => [
            'name' => 'Annual Plan',
            'interval' => 'year',
            'currencies' => [
                'PLN' => [
                    'stripe_id' => env('STRIPE_ANNUAL_PLAN_PLN_ID', 'price_annual_pln'),
                    'fallback_price' => 100, // Used only when Stripe API is unavailable
                ],
                'USD' => [
                    'stripe_id' => env('STRIPE_ANNUAL_PLAN_USD_ID', 'price_annual_usd'),
                    'fallback_price' => 100, // Used only when Stripe API is unavailable
                ],
                'EUR' => [
                    'stripe_id' => env('STRIPE_ANNUAL_PLAN_EUR_ID', 'price_annual_eur'),
                    'fallback_price' => 100, // Used only when Stripe API is unavailable
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for how prices are retrieved and displayed.
    |
    */
    
    'pricing' => [
        /*
        | Fetch prices from Stripe API instead of using local configuration
        */
        'use_stripe_api' => env('SUBSCRIPTION_USE_STRIPE_API', true),

        /*
        | Cache duration for Stripe price data (in seconds)
        */
        'cache_duration' => env('SUBSCRIPTION_PRICE_CACHE_DURATION', 3600),

        /*
        | Whether to log pricing fallbacks
        */
        'log_pricing_fallbacks' => env('SUBSCRIPTION_LOG_PRICING_FALLBACKS', true),
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
