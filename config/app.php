<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    // PRIV-01: grievance officer named in the privacy notice
    'privacy_contact' => env('PRIVACY_CONTACT', 'to be named by HR'),

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),
    'api_base_url' => env('API_BASE_URL'),
    'api_local_base_url' => env('API_LOCAL_BASE_URL'),
    'api_new_base_url' => env('API_NEW_BASE_URL'),

    // device token passed to the old API on web approvals when the session has none (was a hard-coded token in 3 controllers)
    'api_token_id' => env('API_TOKEN_ID', ''),

    // DASH-DUP-01: where employee photos are read from (the HR portal); a test host can point at its own copy
    'employee_photo_base' => rtrim(env('EMPLOYEE_PHOTO_BASE', 'https://vnrseeds.co.in/file-view/Employee_Image'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'Asia/Kolkata',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    | Audit fix SEC-01: login checks on API and web routes.
    | api_auth_enforce=false only logs unauthenticated API calls (rollout switch).
    | internal_api_key: server-to-server callers send it as X-Api-Key.
    | cron_key: cron/* URLs need ?key=<cron_key> when called without a session.
    */
    'api_auth_enforce' => env('API_AUTH_ENFORCE', true),
    // UTR-01: refuse to mark a payment paid without the bank UTR
    'payments_require_utr' => env('PAYMENTS_REQUIRE_UTR', true),

    // LV-S02: single-sign-on token key (was hard-coded in AuthController) and expiry rule
    'sso_jwt_key' => env('SSO_JWT_KEY', ''),
    'sso_jwt_require_exp' => (bool) env('SSO_JWT_REQUIRE_EXP', false),

    'internal_api_key' => env('INTERNAL_API_KEY', ''),
    'cron_key' => env('CRON_KEY', ''),

    // SCHED-01: scheduler switches
    'schedule_queue_worker' => env('SCHEDULE_QUEUE_WORKER', true),
    'schedule_matrix_sync' => env('SCHEDULE_MATRIX_SYNC', true),

    // SEC-03: password for the git command panel (empty = panel disabled). Never in code.
    'git_panel_password' => env('GIT_PANEL_PASSWORD', ''),

];
