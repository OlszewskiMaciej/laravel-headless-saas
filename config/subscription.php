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
];
