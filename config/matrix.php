<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Matrix Payment Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for pushing finance-released payment instructions to Matrix
    | and pulling payment/bank reconciliation outcomes back into Xeasy.
    |
    */

    'enabled' => env('MATRIX_INTEGRATION_ENABLED', true),
    'mock_mode' => env('MATRIX_MOCK', true),
    'reimbursement_enabled' => env('MATRIX_REIMBURSEMENT_ENABLED', true),
    'advance_enabled' => env('MATRIX_ADVANCE_ENABLED', true),

    'base_url' => rtrim(env('MATRIX_BASE_URL', 'https://matrix-test.vnrseeds.co.in/api/v1/integration'), '/'),
    'api_key' => env('MATRIX_API_KEY', 'matrix_dummy_test_api_key_1740'),

    'timeout' => env('MATRIX_TIMEOUT', 60),
    'batch_size' => env('MATRIX_BATCH_SIZE', 500),
    'rate_limit_per_minute' => 120,

    /*
    |--------------------------------------------------------------------------
    | Focus ERP Default Master Values
    |--------------------------------------------------------------------------
    |
    | Default values for Focus analysis fields sent in payment instructions.
    | Matrix checks these against Focus ERP masters.
    |
    */
    'focus' => [
        'business_entity' => env('MATRIX_DEFAULT_BUSINESS_ENTITY', '120'),
        'department' => env('MATRIX_DEFAULT_DEPARTMENT', 'FIN'),
        'cost_center' => 'N/A',
        'pmt_category' => env('MATRIX_DEFAULT_PMT_CATEGORY', 'Staff Expense reimbursement'),
        'location' => env('MATRIX_DEFAULT_LOCATION', 'RAIPUR'),
        'state' => env('MATRIX_DEFAULT_STATE', '22'),
        'function' => env('MATRIX_DEFAULT_FUNCTION', 'BSF'),
        'fc_vertical' => env('MATRIX_DEFAULT_FC_VERTICAL', 'CM'),
        'sub_department' => env('MATRIX_DEFAULT_SUB_DEPARTMENT', 'SUB_DEPT_FIN_124'),
        'activity' => 'N/A',
        'category' => 'N/A',
        'region' => 'N/A',
        'farm' => 'N/A',
        'business_unit' => 'N/A',
        'crop' => 'All Crop',
        'zone' => '',
        'cash_bank_ac' => env('MATRIX_DEFAULT_CASH_BANK_AC', 'BANK-26'),
        'doc_no' => '',
        'cheque_no' => '',
        'brs_user' => '',
        'tds_bill_amount' => 0,
        'tds' => 0,
        'tds_jv_no' => '',
    ],
];
