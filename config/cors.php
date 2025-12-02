<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | List of origins that are allowed to access the API.
    | Use '*' to allow all origins (not recommended for production).
    |
    */
    'allowed_origins' => [
        'https://flashsale.com',
        'https://www.flashsale.com',
        'https://api.flashsale.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allow All Origins (Development Only)
    |--------------------------------------------------------------------------
    |
    | Allow all origins in development environment.
    | NEVER enable this in production!
    |
    */
    'allow_all_origins' => env('CORS_ALLOW_ALL_ORIGINS', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | HTTP methods that are allowed for CORS requests.
    |
    */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | HTTP headers that are allowed for CORS requests.
    |
    */
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Request-ID',
        'Idempotency-Key',
        'X-Idempotency-Key',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
        'X-API-Key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | HTTP headers that are exposed to the browser.
    |
    */
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Request-ID',
        'X-Response-Time',
        'X-API-Version',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | How long the results of a preflight request can be cached (in seconds).
    |
    */
    'max_age' => 86400, // 24 hours

    /*
    |--------------------------------------------------------------------------
    | Allow Credentials
    |--------------------------------------------------------------------------
    |
    | Whether cookies/credentials should be allowed in CORS requests.
    |
    */
    'allow_credentials' => true,
];
