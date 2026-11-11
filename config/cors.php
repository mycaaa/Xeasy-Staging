<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // LV-S13 (audit, 6 Nov 2026): one origin list, from CORS_ALLOWED_ORIGINS (comma separated).
    // No localhost and no wildcard subdomains unless APP_ENV is local / development.
    'allowed_origins' => array_values(array_filter(array_merge(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://expense.vnrseeds.co.in,https://expensenew.vnrseeds.co.in'))),
        in_array(env('APP_ENV', 'production'), ['local', 'development'], true)
            ? ['http://localhost', 'http://localhost:3000', 'http://localhost:8000', 'http://127.0.0.1', 'http://127.0.0.1:8000']
            : []
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];