<?php

/*
| LV-S04 (6 Nov 2026): login check for routes/mobile-api.php (the Laravel copy of the legacy
| mobile API). Same rules and switch as routes/api.php (ApiAuthenticate, API_AUTH_ENFORCE):
| a valid app token (X-Xeasy-Empid + X-Xeasy-Token headers, or empid + tokenid fields), a web
| session, or the INTERNAL_API_KEY header for server jobs.
| The acting employee is the token's employee: an empid / user_id
| field naming someone else is refused (403), so a caller can no longer act as another person.
*/
return [
    'enforce' => env('MOBILE_API_AUTH_ENFORCE', env('API_AUTH_ENFORCE', true)),

    // Paths (relative to /api) that work before login
    'public_paths' => [
        'login', 'login.php', 'login_new.php', '*/login_new.php',
        'version', 'appversion.php', '*/appversion.php', 'ios-update-request',
    ],

    // common.php "value" calls made before login (login screen year / company lists)
    'public_values' => ['yearlist', 'companylist', 'currentstatus', 'helpmanual'],

    // Request fields that name the acting employee; must equal the token's employee
    'actor_fields' => ['empid', 'user_id'],  // the acting user in every mobile call; EmpId / EmployeeID name the claimant

    // Server jobs (cron, OCR callback): internal key or a token; listed here for go-live checks
    'server_job_paths' => [
        'extraction/cron-move-data', 'extraction/autopunch-data', 'OdoMUpdate.php', '*/OdoMUpdate.php',
        'update-image-data', 'get-data-runkm',
    ],
];
