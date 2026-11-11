<?php

/*
| Employee-facing notifications added for the implementation-point tracker.
| Mail goes out only when MAIL_ACTIVE=true (same switch as the existing
| reminder mails) and the feature flag below is on; nothing is sent by SMS.
*/

return [
    'mail_active' => env('MAIL_ACTIVE', false),

    // Tracker #3: daily courier-details reminder from the day after month submission
    // until PostDate (courier date) is entered. Escalation mails stay on their fixed days.
    'courier_reminder' => [
        'enabled' => env('XEASY_COURIER_REMINDER_DAILY', true),
        'time' => env('XEASY_COURIER_REMINDER_TIME', '10:15'),
        // eml_email_templates.id; default = the template the pending_hardcopy reminder uses (id 4).
        'template_id' => env('XEASY_COURIER_REMINDER_TEMPLATE_ID'),
        // COURIER-03 (decision 5 Nov 2026): first reminder only after this many days from submission.
        'start_after_days' => (int) env('XEASY_COURIER_REMINDER_START_AFTER_DAYS', 3),
        // Stop reminding after this many days (0 = until entered).
        'max_days' => (int) env('XEASY_COURIER_REMINDER_MAX_DAYS', 90), // REM-SET-01: 90 days by default; screen setting
    ],

    // APPR-REM-01 (tracker, 27 Oct; decision 5 Nov 2026): remind the reporting manager when a
    // submitted month still waits for his approval 3 days after submission; repeat every N days.
    'approval_reminder' => [
        'enabled' => env('XEASY_APPROVAL_REMINDER', true),
        'time' => env('XEASY_APPROVAL_REMINDER_TIME', '10:35'),
        'start_after_days' => (int) env('XEASY_APPROVAL_REMINDER_START_AFTER_DAYS', 3),
        'repeat_every_days' => (int) env('XEASY_APPROVAL_REMINDER_REPEAT_DAYS', 3),
        // REM-SET-01: months submitted longer ago than this are no longer reminded (0 = never stop)
        'stop_after_days' => (int) env('XEASY_APPROVAL_REMINDER_STOP_AFTER_DAYS', 90),
        // companies to run for: 1 = VNR, 11 = VVNR
        'companies' => array_filter(array_map('intval', explode(',', (string) env('XEASY_APPROVAL_REMINDER_COMPANIES', '1,11')))),
    ],

    // VVNR-HIER-01: monthly hierarchy-wise summary to managers' managers (VVNR), previous month
    'vvnr_hierarchy_summary' => [
        'enabled' => env('XEASY_VVNR_HIERARCHY_SUMMARY', true),
        'day' => (int) env('XEASY_VVNR_HIERARCHY_SUMMARY_DAY', 10),
        'time' => env('XEASY_VVNR_HIERARCHY_SUMMARY_TIME', '10:50'),
    ],

    // TRANSFER-02: transfer / deputation notices read HR operation requests effective in the last N days
    'transfer' => [
        'lookback_days' => (int) env('XEASY_TRANSFER_LOOKBACK_DAYS', 7),
    ],

    // Tracker #29: employee mails for deductions (verification / approval), month
    // approved / financed, and confirmed payment. Queued (needs queue:work + jobs table).
    'employee' => [
        'enabled' => env('XEASY_EMPLOYEE_NOTIFICATIONS', true),
        'cron' => env('XEASY_EMPLOYEE_NOTIFICATIONS_CRON', '7 * * * *'),
        // Only events dated within this many days are mailed (no backlog on go-live).
        'lookback_days' => (int) env('XEASY_EMPLOYEE_NOTIFICATIONS_LOOKBACK_DAYS', 2),
    ],
];
