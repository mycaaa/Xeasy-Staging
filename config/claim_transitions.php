<?php

/*
| B-01 / L-01 (audit, 6 Nov 2026): allowed claim status changes, who may make them, and from
| which status. Every Laravel screen and mobile route that changes y{year}_expenseclaims.ClaimStatus
| goes through App\Services\Claims\ClaimTransition, which checks this table, checks the actor
| (own claim / reporting chain or approval history / staff permission), and logs the change in
| claim_amount_change_logs (field ClaimStatus, with actor role and mode).
|
| Processes: VNR (company 1) submit -> punch/verify -> approve -> finance/pay;
|            VVNR (company 11) submit -> approve -> pay -> verify (difference next month).
| '' means a new claim with no status yet. '*' means any status.
*/
$pre = ['', 'Draft', 'Open', 'Filled', 'Submitted', 'Return'];          // before verification
$notPaid = ['', 'Draft', 'Open', 'Filled', 'Submitted', 'Return', 'Verified', 'Approved', 'Deactivate', 'Close'];

return [
    // false = log refused changes but let them through (rollout switch)
    'enforce' => env('CLAIM_TRANSITIONS_ENFORCE', true),

    // FIN-APPR-01: claim statuses that stop Finance closing a month
    'month_close_blocking' => ['Draft', 'Open', 'Filled', 'Submitted', 'Return', 'Verified'],

    // company id => process
    'process' => [1 => 'vnr', 3 => 'vnr', 11 => 'vvnr'],

    // role => permissions that grant it (any one); 'claimant' / 'approver' / 'system' are checked by record
    'role_permissions' => [
        'punching' => ['section_data_punching', 'section_verifier', 'payment_section'],
        'verifier' => ['section_verifier', 'verifier_verifier_screen', 'section_data_punching', 'payment_section'],
        'mediator' => ['section_data_punching', 'section_verifier', 'seection_settings', 'payment_section'],
        'finance'  => ['finance', 'payment_section', 'treasury'],
        'approver' => ['approval_details', 'payment_section'],   // staff approvers; others via chain / history
    ],

    'transitions' => [
        'vnr' => [
            // to => [role => allowed from-statuses]
            'Draft'      => ['claimant' => $pre, 'punching' => $pre, 'mediator' => $pre],
            'Open'       => ['claimant' => $pre, 'punching' => $pre],
            'Filled'     => ['claimant' => $pre, 'punching' => array_merge($pre, ['Deactivate']), 'verifier' => array_merge($pre, ['Deactivate', 'Verified']), 'mediator' => array_merge($pre, ['Deactivate']), 'approver' => $pre, 'system' => $pre],
            'Submitted'  => ['claimant' => $pre, 'punching' => array_merge($pre, ['Deactivate', 'Verified']), 'verifier' => array_merge($pre, ['Deactivate', 'Verified', 'Approved']), 'mediator' => array_merge($pre, ['Deactivate'])],
            'Return'     => ['verifier' => array_merge($pre, ['Verified']), 'approver' => array_merge($pre, ['Verified'])],
            'Verified'   => ['verifier' => array_merge($pre, ['Verified']), 'punching' => array_merge($pre, ['Verified']), 'system' => array_merge($pre, ['Verified'])],
            // Decided 6 Nov: a VNR claim must be verified before it can be approved.
            'Approved'   => ['approver' => ['Verified', 'Approved']],
            'Close'      => ['approver' => ['Approved', 'Close']],
            'Payment'    => ['finance' => ['Approved', 'Payment', 'Close']],
            // FIN-APPR-01 (6 Nov): finance only after approval (Verified removed)
            'Financed'   => ['finance' => ['Approved', 'Payment', 'Close', 'Financed', 'Rej_Fin']],
            'Deactivate' => ['claimant' => $pre, 'punching' => array_diff($notPaid, ['Close']), 'verifier' => array_diff($notPaid, ['Close']), 'mediator' => array_diff($notPaid, ['Close'])],
        ],
        'vvnr' => [
            'Draft'      => ['claimant' => $pre, 'punching' => $pre, 'mediator' => $pre],
            'Open'       => ['claimant' => $pre, 'punching' => $pre],
            'Filled'     => ['claimant' => $pre, 'punching' => array_merge($pre, ['Deactivate']), 'verifier' => array_merge($pre, ['Deactivate']), 'mediator' => array_merge($pre, ['Deactivate']), 'approver' => $pre, 'system' => $pre],
            'Submitted'  => ['claimant' => $pre, 'punching' => array_merge($pre, ['Deactivate']), 'verifier' => array_merge($pre, ['Deactivate', 'Approved']), 'mediator' => array_merge($pre, ['Deactivate'])],
            'Return'     => ['verifier' => $pre, 'approver' => $pre],
            // VVNR verifies after approval and payment
            'Verified'   => ['verifier' => array_merge($pre, ['Approved', 'Payment', 'Financed', 'Verified']), 'punching' => array_merge($pre, ['Approved', 'Payment', 'Financed', 'Verified']), 'system' => array_merge($pre, ['Approved', 'Payment', 'Financed', 'Verified'])],
            'Approved'   => ['approver' => ['Filled', 'Submitted', 'Approved', 'Verified']],
            'Close'      => ['approver' => ['Approved', 'Close']],
            // FIN-APPR-01 (6 Nov): pay only after approval (VVNR verifies after payment, so Verified is never a pay-from status)
            'Payment'    => ['finance' => ['Approved', 'Payment', 'Close']],
            'Financed'   => ['finance' => ['Approved', 'Payment', 'Close', 'Financed', 'Rej_Fin']],
            'Deactivate' => ['claimant' => $pre, 'punching' => array_diff($notPaid, ['Close']), 'verifier' => array_diff($notPaid, ['Close']), 'mediator' => array_diff($notPaid, ['Close'])],
        ],
    ],
];
