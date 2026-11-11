<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // FILES-01 (UAT-03): claim-stage / Focus exports, downloaded only through /exports/download/{file}
        'private_exports' => [
            'driver' => 'local',
            'root' => storage_path('app/private_exports'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
        // TEST-S3-01 (10 Nov 2026): on a test server without the bucket, CLAIM_FILES_LOCAL=true keeps the
        // claim files in storage/app/s3_local under the same keys (Expense/{year}/{emp}/...), shown only to
        // logged-in users through /s3-local/{path}. Never set on live: live keeps the S3 bucket.
        's3' => env('CLAIM_FILES_LOCAL', false) ? [
            'driver' => 'local',
            'root' => storage_path('app/s3_local'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/s3-local',
            'visibility' => 'private',
            'permissions' => ['file' => ['public' => 0664, 'private' => 0660], 'dir' => ['public' => 0775, 'private' => 0770]],
            'serve' => false,
            'throw' => false,
            'report' => false,
        ] : [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT') ? env('AWS_URL') : null,
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
        'hrims_s3' => [
            'driver' => 's3',
            'key' => env('HRIMS_AWS_ACCESS_KEY_ID'),
            'secret' => env('HRIMS_AWS_SECRET_ACCESS_KEY'),
            'region' => env('HRIMS_AWS_DEFAULT_REGION', 'ap-south-1'),
            'bucket' => env('HRIMS_AWS_BUCKET'),
            'use_path_style_endpoint' => env('HRIMS_AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],


    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
