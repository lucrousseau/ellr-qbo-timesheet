<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Credentialed SPA requests require explicit origins (no wildcard) and
    | supports_credentials enabled. Origins are derived from frontend URLs.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_ADMIN_URL', 'http://localhost:5173'),
        env('FRONTEND_TIMESHEET_URL', 'http://localhost:5174'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
