<?php

return [
    /*
    | LV-C05 (audit, 6 Nov 2026): maker-checker in the expense payment chain. The person who
    | approved a month's payment (payment_approved_by) may not also release it to the bank
    | (InstructedPayment) or mark a zero-amount month as confirmed. Releases are recorded in
    | payment_instructed_by. Switch off only where a single person must do both (logged).
    */
    'maker_checker' => env('PAYMENT_MAKER_CHECKER', true),

    // 'enforce' refuses the release; 'log' (decided 6 Nov for the first weeks on live) allows it but
    // writes a warning, so Finance can see how often the same person approves and releases.
    'maker_checker_mode' => env('PAYMENT_MAKER_CHECKER_MODE', 'enforce'),

    // PAY-SAME-USER-01 (24 Nov 2026, testing point 14): on Payment > Expense Payment the same person may move a month
    // from Pending to Approved and then release it / generate the Focus export. Every such case is still written to
    // the log ('Maker-checker (log only)') and payment_approved_by / payment_instructed_by keep both names.
    // Set PAYMENT_EXPENSE_MAKER_CHECKER_MODE=enforce to require a second person again. Advances keep maker_checker_mode.
    'expense_payment_mode' => env('PAYMENT_EXPENSE_MAKER_CHECKER_MODE', 'log'),
];
