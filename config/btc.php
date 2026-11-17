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

    // BTC-ADV-02 (17 Nov 2026): an advance not settled by a bill within this many days blocks a new advance
    'advance_settle_days' => (int) env('BTC_ADVANCE_SETTLE_DAYS', 30),

    // BTC-TRY-01 (17 Nov 2026): a Treasury checker (another person) authorises each payment entered by the maker
    'treasury_checker' => (bool) env('BTC_TREASURY_CHECKER', true),
    // BTC-EXC-01: a bill above the booked / quoted / estimated amount by more than this % is a request mismatch
    'mismatch_pct' => (int) env('BTC_MISMATCH_PCT', 10),

    // BTC-PAY-01 (17 Nov 2026): payment modes on Payment Confirmation
    'payment_modes' => ['neft' => 'NEFT', 'rtgs' => 'RTGS', 'imps' => 'IMPS', 'upi' => 'UPI', 'cheque' => 'Cheque', 'other' => 'Other'],

    // BTC-REQ-10 (17 Nov 2026): short route names in the lists
    'route_short' => ['admin_booking' => 'Admin booking', 'employee_advance' => 'Advance requested', 'direct_bill' => 'Final bill'],

    // BTC-FORM-01: routes an employee may choose on the request form (the BTC admin may choose all)
    // BTC-REQ-03 (16 Nov 2026, "BTC requirements" tab): the new-requirement form gives two clear choices,
    // "Admin Booking Required" (A) and "Advance Required" (B); C (final bill after the activity) stays with the BTC admin
    'employee_routes' => ['admin_booking', 'employee_advance'],
    'route_choices' => [
        'admin_booking' => ['Admin Booking Required', 'The BTC admin books the hotel / vehicle / ticket with the vendor; you only state the need.'],
        'employee_advance' => ['Advance Required', 'You arrange the vendor and attach its quotation; Finance pays an advance after your manager approves.'],
        'direct_bill' => ['Final bill after the activity', 'The service is over and the vendor bill is in hand (BTC admin only).'],
    ],

    // BTC-FORM-02: vendor types (vendor_list.vendor_type) offered for each category; [] = all vendors
    'vendor_types_by_category' => [
        'hotel' => ['hotel'],
        'vehicle' => ['agency', 'agent'],
        'air' => ['agency', 'agent'],
        'train' => ['agency', 'agent'],
        'meeting' => [],
        'other' => [],
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
        'booking_requested' => 'Waiting for admin booking',
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

    // ---- BTC-REQ-01 / 02 (16 Nov 2026, "BTC requirements" tab): roles and access -----------------------------------
    // Screen-wise and action-wise permissions shown on BTC > Access. Existing names are kept (btc.dms ... btc.admin).
    'permission_catalog' => [
        'Screens' => [
            'btc.dashboard' => 'BTC Dashboard',
            'btc.register' => 'BTC Request Register (all requests)',
            'btc.booking' => 'Admin Booking Queue',
            'btc.advance' => 'Advance Request & Processing',
            'btc.invoices' => 'Nova Invoice Inbox and request / invoice matching',
            'btc.integration' => 'Integration Monitoring (Nova sync, retry)',
            'btc.access' => 'BTC roles, users and access history',
            'btc.exceptions' => 'Validation & Exception Queue',                                   // BTC-EXC-01
            'btc.audit' => 'Internal Audit: read every BTC record and report (no changes)',      // BTC-PERM-02
            'btc.export' => 'Download BTC reports (Excel)',
            'btc.configure' => 'BTC settings and masters',
        ],
        'Actions' => [
            'btc.verifier' => 'Verify invoice and supporting documents (DMS step)',
            'btc.dms' => 'DMS processing',
            'btc.finance' => 'Finance verification: GST, TDS, ledger and JV',
            'btc.checker' => 'Finance checker / financial approval',
            'btc.treasury' => 'Treasury maker: send for department approval, enter payment and UTR',
            'btc.treasury_checker' => 'Treasury checker: authorise payments entered by another person',
            'btc.admin' => 'All BTC cases and every step (full control)',
        ],
    ],
    // The recommended roles (sheet item 2). Employee, Reporting Manager and Authorised Approver also act through the
    // case itself (the requester, the manager on the case, the department approver chosen by Treasury).
    'roles' => [
        'BTC Super Admin' => ['Complete BTC module control', ['btc.admin', 'btc.access', 'btc.dashboard', 'btc.register', 'btc.booking', 'btc.advance', 'btc.invoices', 'btc.integration', 'btc.verifier', 'btc.dms', 'btc.finance', 'btc.checker', 'btc.treasury', 'btc.treasury_checker', 'btc.exceptions', 'btc.audit', 'btc.export', 'btc.configure']],
        'BTC DMS Admin' => ['Nova integration, invoice inbox and document management', ['btc.invoices', 'btc.integration', 'btc.dms', 'btc.register', 'btc.dashboard']],
        'BTC Admin' => ['Request management, booking and operational coordination', ['btc.booking', 'btc.register', 'btc.dashboard']],
        'BTC Employee' => ['Create request, upload documents and track own BTC cases', []],
        'BTC Reporting Manager' => ['Request approval and business justification', []],
        'BTC Authorised Approver' => ['Final approval as per approval matrix', []],
        'BTC Finance Advance' => ['Advance processing and payment', ['btc.advance', 'btc.register']],
        'BTC Verifier' => ['Invoice and supporting document verification', ['btc.verifier', 'btc.invoices', 'btc.register']],
        'BTC Finance Verifier' => ['Financial, GST, TDS and accounting verification', ['btc.finance', 'btc.register', 'btc.dashboard']],
        'BTC Payment User' => ['Treasury maker: payment entry, UTR (a Treasury Checker authorises)', ['btc.treasury', 'btc.register']],
        'BTC Treasury Checker' => ['Payment authorisation (not own entries)', ['btc.treasury_checker', 'btc.register']],
        'BTC Internal Audit' => ['Read-only BTC records and reports', ['btc.audit', 'btc.register', 'btc.dashboard', 'btc.export', 'btc.exceptions']],
        'BTC Finance Head' => ['Financial approval and oversight', ['btc.checker', 'btc.dashboard', 'btc.register', 'btc.advance']],
    ],

    // ---- BTC-REQ-07..09: Nova (DMS) invoice feed ----------------------------------------------------------------------
    // Nova posts invoices to POST /api/btc/nova/invoices with header X-Api-Key: <BTC_NOVA_API_KEY>. Until the key is
    // set the endpoint is closed; the inbox also takes a CSV export from Nova.
    'nova' => [
        'api_key' => env('BTC_NOVA_API_KEY'),
        // an invoice is expected this many days after the service ended (used for "Nova has not received the invoice")
        'expected_after_days' => 7,
        // matching: suggest a case from this score (0-100); auto-link only when one case scores at least auto_link
        'suggest_from' => 40,
        'auto_link' => 90,
    ],
];
