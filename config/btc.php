<?php

/**
 * BTC-01: Bill To Company (BTC) process — VNR Finance & Accounts, "Current BTC process" note (5 Nov 2026).
 *
 * Routes
 *   A  admin_booking     Employee requirement → Admin books hotel / vendor → service → Admin gets bill → DMS
 *   B  employee_advance  Employee requirement → vendor quotation → manager approval → advance from Finance
 *                        → service → final bill → DMS
 *   C  direct_bill       Service done → employee / vendor sends final bill → DMS
 *
 * After the bill: DMS → Finance verification → Focus JV → Finance checker → Treasury
 *   → department approval → payment → advance adjustment / supporting closure.
 */
return [
    'routes' => [
        'admin_booking' => 'A · Admin-arranged booking',
        'employee_advance' => 'B · Employee-arranged with advance',
        'direct_bill' => 'C · Final bill after the activity',
    ],

    // Category rules (section 4 of the note). key => [label, gate document, original bill needed before closure]
    'categories' => [
        'hotel' => ['label' => 'Hotel / Accommodation', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true],
        'vehicle' => ['label' => 'Hired Vehicle Vendor', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => false],
        'air' => ['label' => 'Travel Desk – Air', 'boarding_pass' => true, 'original_required' => false, 'scan_ok' => true],
        'train' => ['label' => 'Travel Desk – Train', 'boarding_pass' => false, 'original_required' => false, 'scan_ok' => true],
        'meeting' => ['label' => 'Meeting / Event Service', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true],
        'other' => ['label' => 'Other service', 'boarding_pass' => false, 'original_required' => true, 'scan_ok' => true],
    ],

    // Stages in order. key => label
    'statuses' => [
        'draft' => 'Draft',
        'pending_manager' => 'Waiting for manager approval',
        'advance_requested' => 'Advance requested',
        'advance_paid' => 'Advance paid',
        'booked' => 'Booked / service in progress',
        'bill_submitted' => 'Bill submitted to DMS',
        'dms_processed' => 'DMS processed',
        'finance_verified' => 'Finance verified, JV prepared',
        'checked' => 'Checked by Finance authority',
        'with_treasury' => 'With Treasury',
        'dept_approved' => 'Department approved',
        'paid' => 'Paid',
        'closed' => 'Closed',
        'returned' => 'Returned for correction',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ],

    // Focus JV ledgers (names/codes as in Focus; Finance to confirm)
    'focus' => [
        // BTC-LEDGER-01 (Finance 10 Nov 2026): a separate BTC ledger per employee, named like "Arnav_BTC".
        // {first_name} = first name; when two active employees share it, the employee code is added ("Arnav 1234_BTC").
        'employee_btc_ledger' => env('BTC_LEDGER_EMPLOYEE', '{first_name}_BTC'),
        'tds_ledger' => env('BTC_LEDGER_TDS', 'TDS Payable'),
        'gst_input_ledger' => env('BTC_LEDGER_GST', 'GST Input Credit'),
        'vendor_advance_ledger' => env('BTC_LEDGER_VENDOR_ADV', 'Advance to Vendors'),
        'expense_ledgers' => [
            'hotel' => 'Lodging & Boarding (BTC)',
            'vehicle' => 'Vehicle Hire Charges',
            'air' => 'Travelling - Air',
            'train' => 'Travelling - Train',
            'meeting' => 'Meeting & Conference Expenses',
            'other' => 'Miscellaneous Expenses',
        ],
    ],

    'tds_sections' => ['194C' => 'Contractors (194C)', '194J' => 'Professional / technical (194J)', '194I' => 'Rent (194I)', 'none' => 'No TDS'],

    // BTC-TDS-01 (Finance 10 Nov 2026): TDS is decided per transaction from the nature of the payment.
    // Income-tax Act, 2025 (tax year 2026-27): all under section 393(1); the old 1961 sections are shown too.
    // rate = [individual / HUF, others] in %; threshold = single bill (and year total) in rupees. Finance to confirm.
    'tds_natures' => [
        'contract' => ['label' => 'Contract work / hired vehicle / event service (393(1) Sl. 6(i), old 194C)', 'section' => '194C', 'rate' => [1, 2], 'threshold' => [30000, 100000]],
        'professional' => ['label' => 'Professional fees (393(1) Sl. 11, old 194J)', 'section' => '194J', 'rate' => [10, 10], 'threshold' => [50000, 50000]],
        'technical' => ['label' => 'Technical services (393(1) Sl. 11, old 194J)', 'section' => '194J', 'rate' => [2, 2], 'threshold' => [50000, 50000]],
        'rent_plant' => ['label' => 'Rent of plant / machinery / equipment (393(1) Sl. 10, old 194I)', 'section' => '194I', 'rate' => [2, 2], 'threshold' => [50000, 50000]],
        'rent_premises' => ['label' => 'Rent of premises / hotel rooms on a regular basis (393(1) Sl. 10, old 194I)', 'section' => '194I', 'rate' => [10, 10], 'threshold' => [50000, 50000]],
        'none' => ['label' => 'No TDS (tickets, occasional hotel stay, below threshold, goods)', 'section' => 'none', 'rate' => [0, 0], 'threshold' => [0, 0]],
    ],
    // default nature per BTC category (Finance can change it on each case)
    'tds_nature_by_category' => ['hotel' => 'none', 'vehicle' => 'contract', 'air' => 'none', 'train' => 'none', 'meeting' => 'contract', 'other' => 'contract'],

    // remind DMS / Finance about missing originals and boarding passes after this many days
    'follow_up_days' => (int) env('BTC_FOLLOW_UP_DAYS', 7),
];
