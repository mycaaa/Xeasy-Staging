<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Legacy-format claim API (claim.php / common.php / extract-data.php) that
    | the employee web screens relay to. Empty base_url = this app's own
    | Laravel endpoints (APP_URL/api). Production hosts are refused unless
    | XEASY_LEGACY_ALLOW_PRODUCTION=true. TLS is always verified; set
    | XEASY_LEGACY_CA_BUNDLE for an internal CA.
    */
    'xeasy_legacy' => [
        'base_url' => env('XEASY_LEGACY_API_BASE_URL', ''),
        'allow_production' => env('XEASY_LEGACY_ALLOW_PRODUCTION', false),
        'production_hosts' => array_filter(explode(',', env('XEASY_LEGACY_PRODUCTION_HOSTS', 'expense.vnrseeds.co.in,expensenew.vnrseeds.co.in'))),
        'web_token' => env('XEASY_LEGACY_WEB_TOKEN', ''),
        'extract_token' => env('XEASY_LEGACY_EXTRACT_TOKEN', ''),
        'ca_bundle' => env('XEASY_LEGACY_CA_BUNDLE', ''),
        'timeout' => (int) env('XEASY_LEGACY_TIMEOUT', 60),
    ],

    'central_notification' => [
        'base_url' => env('CENTRAL_NOTIFICATION_BASE_URL', ''),
        'app_name' => env('CENTRAL_NOTIFICATION_APP_NAME', 'xeasy'),
        'app_token' => env('CENTRAL_NOTIFICATION_APP_TOKEN', ''),
    ],


    // LV-S11: TLS verification for outbound HTTP calls. true (default) uses the system CA store;
    // set HTTP_CA_BUNDLE to a .pem path for an internal CA. Only a test server may set HTTP_VERIFY_TLS=false.
    'http_tls' => [
        'verify' => env('HTTP_VERIFY_TLS', true),
        'ca_bundle' => env('HTTP_CA_BUNDLE', ''),
    ],

    // G-02: odometer reading service (Google Cloud Run); see docs/INTEGRATIONS.md
    'odometer_ocr' => [
        'url' => env('ODOMETER_OCR_URL', 'https://api-gf4tdduqha-uc.a.run.app/api/v1/extract-odometer-reading'),
    ],
];
