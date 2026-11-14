<?php

/*
| PROCESS-01 (13 Nov 2026): the claim process, stage by stage, with the names used on screens and reports.
| One list per process (config claim_transitions.process: company 1 / 3 = vnr, 11 = vvnr).
| 'done_when' says what the system records when the stage is complete; claims:process-check uses it.
*/
return [
    'vnr' => [
        // VNR Seeds and Nursery: claims are verified before the manager approves
        ['key' => 'upload',       'name' => 'Bill upload',                'by' => 'Employee',          'done_when' => 'claim saved (ClaimStatus Submitted)'],
        ['key' => 'entry',        'name' => 'Data entry',                 'by' => 'Bill reader / Employee', 'done_when' => 'details read automatically or typed by the employee'],
        ['key' => 'punching',     'name' => 'Punching check',             'by' => 'Punching team',     'done_when' => 'ClaimStatus Filled (corrected where needed)'],
        ['key' => 'confirm',      'name' => 'Employee confirmation & submit', 'by' => 'Employee',      'done_when' => 'FilledOkay 1 and the month submitted (Status Closed, DateOfSubmit)'],
        ['key' => 'verification', 'name' => 'Verification',               'by' => 'Verification team', 'done_when' => 'ClaimStatus Verified; month Verified_Date'],
        ['key' => 'approval',     'name' => 'Manager approval',           'by' => 'Reporting manager', 'done_when' => 'ClaimStatus Approved; month Approved_Date'],
        ['key' => 'finance',      'name' => 'Finance approval',           'by' => 'Finance',           'done_when' => 'month Fin_AppDate (Finance_Amount)'],
        ['key' => 'adjustment',   'name' => 'Payment & advance adjustment', 'by' => 'Payment team',    'done_when' => 'payment_status Approved (advance adjusted manually or automatically)'],
        ['key' => 'treasury',     'name' => 'Payment release',            'by' => 'Treasury',          'done_when' => 'payment_status InstructedPayment, then ConfirmedPayment (paid, UTR)'],
    ],
    'vvnr' => [
        // VVNR: the manager approves first, the claim is paid, and verification follows payment
        ['key' => 'upload',       'name' => 'Bill upload',                'by' => 'Employee',          'done_when' => 'claim saved'],
        ['key' => 'punching',     'name' => 'Data entry / punching',      'by' => 'Employee / Punching team', 'done_when' => 'ClaimStatus Filled'],
        ['key' => 'confirm',      'name' => 'Employee confirmation & submit', 'by' => 'Employee',      'done_when' => 'month submitted (Status Closed)'],
        ['key' => 'approval',     'name' => 'Manager approval',           'by' => 'Reporting manager', 'done_when' => 'ClaimStatus Approved; month Approved_Date'],
        ['key' => 'finance',      'name' => 'Finance approval',           'by' => 'Finance',           'done_when' => 'month Fin_AppDate'],
        ['key' => 'adjustment',   'name' => 'Payment & advance adjustment', 'by' => 'Payment team',    'done_when' => 'payment_status Approved'],
        ['key' => 'treasury',     'name' => 'Payment release',            'by' => 'Treasury',          'done_when' => 'payment_status ConfirmedPayment'],
        ['key' => 'verification', 'name' => 'Verification (after payment)', 'by' => 'Verification team', 'done_when' => 'ClaimStatus Verified; difference adjusted next month'],
    ],

    // Month submission refuses a month that still has bills the punching team has not punched (they would be left
    // out of the month and never reach verification). Late bills waiting for approval count as not punched.
    'submit_requires_punched' => env('CLAIM_SUBMIT_REQUIRES_PUNCHED', true),

    // EMP-VERIFY-ALL-01 (tracker #22): a bill punched by the punching team waits in the employee's Pending tab for
    // OK & Verify (like odometer claims). false = saved as confirmed, as before. VVNR is not affected.
    'employee_verifies_punched' => env('CLAIM_EMPLOYEE_VERIFIES_PUNCHED', true),

    // EMP-CONFIRM-01: the employee cannot change a claim the punching team has punched; OK or deny with a remark only
    'employee_cannot_edit_punched' => env('CLAIM_EMPLOYEE_CANNOT_EDIT_PUNCHED', true),
];
