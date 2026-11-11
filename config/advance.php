<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Advance Request Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Advance Request system
    |
    */

    // Email template names for different actions
    'email_templates' => [
        'submission' => 'advance_submission',
        'approved' => 'advance_approved',
        'rejected' => 'advance_rejected',
        'reverted' => 'advance_reverted',
        'pending_approval' => 'advance_pending_approval',
        'finance_approved' => 'advance_finance_approved',
        'finance_rejected' => 'advance_finance_rejected',
        'finance_reverted' => 'advance_finance_reverted',
    ],

    // Default approval matrix (can be overridden by database settings)
    'default_approval_levels' => 3,
    'max_approval_levels' => 5,

    // Finance user roles/permissions
    'finance_roles' => [
        'finance_manager',
        'finance_admin',
        'super_admin'
    ],

    'finance_permissions' => [
        'advance_finance_approval',
        'advance_finance_management'
    ],

    // Request number format
    'request_number_format' => 'ADV{year}{month}{sequence}',

    // Maximum amount limits (can be overridden by approval matrix)
    'amount_limits' => [
        'level_1' => 50000,
        'level_2' => 100000,
        'level_3' => 500000,
        'finance' => null, // No limit for finance
    ],

    // Notification settings
    'notifications' => [
        'send_emails' => true,
        'send_sms' => false,
        'send_push' => false,
    ],

    // Audit settings
    'audit' => [
        'log_all_actions' => true,
        'retention_days' => 365,
    ],
];