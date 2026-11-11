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
];
