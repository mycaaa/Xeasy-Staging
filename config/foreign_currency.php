<?php

/**
 * FX-53 (tracker #53): foreign-currency advances (overseas travel).
 * The ledger, payments and Focus export stay in INR. A foreign-currency activity keeps the
 * currency, the foreign amount and the rate; `amount` is always the INR equivalent.
 */
return [
    'enabled' => env('FX_ADVANCE_ENABLED', true),

    // ISO codes offered on the request form
    'currencies' => array_values(array_filter(array_map('trim', explode(',', env(
        'FX_ADVANCE_CURRENCIES',
        'USD,EUR,GBP,AED,SGD,THB,JPY,CNY,AUD,CAD,CHF,MYR,LKR,NPR,BDT,SAR,QAR,KES,ZAR,PHP,IDR,VND'
    ))))),

    // a rate entered by Finance for a currency is used for requests for this many days
    'rate_valid_days' => (int) env('FX_ADVANCE_RATE_VALID_DAYS', 7),

    // Finance may pay at the actual (card-loading / bank) rate; the INR amount may then exceed
    // the requested INR amount by at most this percentage
    'paid_rate_tolerance_pct' => (float) env('FX_ADVANCE_PAID_RATE_TOLERANCE_PCT', 5),
];
