<?php

// ODO-DUP-01: the same odometer trip saved twice (same employee, date, opening and closing reading).
return [
    // drop the extra claim when the month is submitted / auto-verified
    'on_submit' => env('ODO_DUPLICATES_ON_SUBMIT', true),
    // daily sweep of unpaid months (odo:duplicates --apply); paid months are only reported
    'daily_sweep' => env('ODO_DUPLICATES_DAILY_SWEEP', true),
];
