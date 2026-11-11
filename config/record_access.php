<?php

/*
 * SEC-03 (6 Nov 2026): record-level access (App\Support\RecordAccess, applied by
 * App\Http\Middleware\EnforceRecordAccess on the web group).
 *
 * Each rule: path regex (no leading slash) and where the record ids are:
 *   employee        request fields holding an employee id
 *   employee_route  route parameters holding an employee id
 *   claim           request fields holding an expense claim id (ExpId)
 *   claim_route     route parameters holding a claim id
 *   year / company  request fields that pick the claim's year / company (optional)
 *   approver        true = the screen is only for people with direct reports (or staff)
 * Every id present in the request must pass; a missing id is left to the action (it falls back
 * to the logged-in employee). First matching rule wins.
 */
return [
    'enabled' => env('RECORD_ACCESS_ENFORCE', true),

    // Levels above the employee that count as "their manager" (RM, RMM, HOD, BU head ...)
    'chain_levels' => (int) env('RECORD_ACCESS_CHAIN_LEVELS', 6),

    // Any one of these sees every employee's records (Super Admin passes via Gate::before).
    // Same names the menus use (config/route_access.php, menus table).
    'staff_permissions' => [
        'section_verifier', 'section_data_punching', 'verifier_verifier_screen', 'view_claim_detail',
        'pending_claims_view', 'verify_pending_list', 'verify_approved_list',
        'finance', 'finance_details', 'payment_section', 'treasury', 'treasury_advance', 'imprest.approve',
        'section_reports', 'reports_claim_report', 'approval_details', 'verification_details',
        'employee_ledger_imprest', 'ledger_summary',
    ],

    'rules' => [
        // (approver = the screen is for people with direct reports or who approved claims this year)
        // Claimant screen and its AJAX (also embedded by the verifier with ?ei=)
        ['#^(claimant(/|$)|claimant-embed$)#', 'employee' => ['ei', 'empid'], 'claim' => ['expid', 'exp_id']],

        // Claim detail / form by claim id
        ['#^showclaim$#', 'claim' => ['expid']],
        ['#^claims/show-details/#', 'claim_route' => ['expid']],
        ['#^claims/show-form$#', 'claim' => ['expid']],

        // Old claim-list AJAX (?emp= is the employee)
        ['#^claimlistajax$#', 'employee' => ['emp'], 'claim' => ['expid']],

        // Prints, PDFs and the account summary (?e= / ?empid= is the employee)
        ['#^(printdetails|account-summary|expense-pdf|expense/print-details)$#', 'employee' => ['e', 'empid']],

        // Employee month list by id
        ['#^employee/\d+$#', 'employee_route' => ['id', 'employeeId']],

        // PAX details of a claim (claim form, data punch)
        ['#^api/pax-details/#', 'claim' => ['exp_id'], 'year' => ['year_id'], 'company' => ['com_id']],

        // Company approver screens: the claimant named must be in the approver's chain
        ['#^(vnr|vvnr|nursery)/approver(/|$)#', 'employee' => ['empid', 'emp_id', 'employee_id'], 'claim' => ['exp_id'], 'year' => ['year_id', 'yearid']],

        // Approver screen (routes/web.php ApproverController, menu "Approver" shown to everyone):
        // the page itself stays open (it lists only the user's own team); its data and actions are
        // for approvers (direct reports, or approved claims this year) and staff only
        ['#^approver/(pending-list|verified-list|approved-list|denied-list|expense-details|claim-details|month-details|approve|deny|submit|running-km|expense-report|month-summary-print|save-courier|courier-detail|team-members|team-expense|team-runningkm)$#', 'approver' => true, 'employee' => ['empid', 'emp_id', 'employee_id'], 'claim' => ['expid', 'exp_id'], 'year' => ['year_id', 'yearid']],
    ],
];
