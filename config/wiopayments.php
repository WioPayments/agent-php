<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WioPayments API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your WioPayments API settings. You can find
    | your API credentials in your WioPayments dashboard.
    |
    */

    'api_key' => env('WIOPAYMENTS_API_KEY'),
    'secret_key' => env('WIOPAYMENTS_SECRET_KEY'),
    'base_url' => env('WIOPAYMENTS_BASE_URL', 'https://gw.wiopayments.com/api/'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for payment operations when not specified.
    |
    */

    'default_currency' => env('WIOPAYMENTS_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook handling and verification.
    |
    */

    'webhook' => [
        'secret' => env('WIOPAYMENTS_WEBHOOK_SECRET'),
        'tolerance' => env('WIOPAYMENTS_WEBHOOK_TOLERANCE', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging of API requests and responses for debugging purposes.
    |
    */

    'logging' => [
        'enabled' => env('WIOPAYMENTS_LOGGING_ENABLED', false),
        'channel' => env('WIOPAYMENTS_LOG_CHANNEL', 'stack'),
        'level' => env('WIOPAYMENTS_LOG_LEVEL', 'info'),
    ],
];