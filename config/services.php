<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NutriMatch External Service Configuration
    |--------------------------------------------------------------------------
    | All credentials are read from environment variables only.
    | No secrets are stored in this file or in source control.
    */

    // --------------------------------------------------------
    // USDA FoodData Central API
    // Free API key: https://fdc.nal.usda.gov/api-key-signup.html
    // --------------------------------------------------------
    'usda' => [
        'api_key'      => env('USDA_API_KEY'),
        'base_url'     => env('USDA_API_BASE_URL', 'https://api.nal.usda.gov/fdc/v1'),
        'cache_ttl'    => (int) env('USDA_CACHE_TTL_HOURS', 24),
        'timeout'      => 15,
    ],

    // --------------------------------------------------------
    // PayMongo (Philippine Payment Gateway)
    // Dashboard: https://dashboard.paymongo.com
    // --------------------------------------------------------
    'paymongo' => [
        'secret_key'      => env('PAYMONGO_SECRET_KEY'),
        'public_key'      => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret'  => env('PAYMONGO_WEBHOOK_SECRET'),
        'base_url'        => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
        'timeout'         => 30,
    ],

    // --------------------------------------------------------
    // Daily.co (Video Consultation)
    // Dashboard: https://dashboard.daily.co/developers
    // --------------------------------------------------------
    'daily_co' => [
        'api_key'   => env('DAILY_CO_API_KEY'),
        'domain'    => env('DAILY_CO_DOMAIN'),
        'base_url'  => env('DAILY_CO_BASE_URL', 'https://api.daily.co/v1'),
        'timeout'   => 15,
    ],

];
