<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Strict CORS policy for OpticVault.
    | Only whitelisted origins can access the API. No wildcards.
    |
    | React (Axios) must send withCredentials: true to match this config.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'https://opalshot.studio',
        'https://www.opalshot.studio',
        ...explode(',', env('CORS_ALLOWED_ORIGINS', '')),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'Content-Disposition',  // Needed for batch download/upload tracking
    ],

    'max_age' => 600,  // Cache pre-flight OPTIONS for 10 minutes

    'supports_credentials' => true,  // Required for Sanctum cookie-based auth

];
