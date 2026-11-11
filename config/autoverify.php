<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-Verification Enabled Flags
    |--------------------------------------------------------------------------
    | Master toggle and per-feature switches for the automated verification pipeline.
    */
    'enabled' => env('AUTOVERIFY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | GPS vs Odometer Telemetry Tolerance
    |--------------------------------------------------------------------------
    | Allowable percentage variance between claimed distance (Odometer closing - opening)
    | and raw GPS distance recorded in tracking_sessions table.
    | E.g. 15.0 means claimed KM within 15% of GPS distance will pass automatically.
    */
    'gps_tolerance_percent' => env('AUTOVERIFY_GPS_TOLERANCE_PCT', 15.0),

    /*
    |--------------------------------------------------------------------------
    | OCR Receipt Tolerance
    |--------------------------------------------------------------------------
    | Allowable percentage difference between OCR extracted total amount and claimant
    | entered amount. Flat buffer allows minor rounding discrepancies (e.g. ₹5).
    */
    'ocr_tolerance_percent' => env('AUTOVERIFY_OCR_TOLERANCE_PCT', 5.0),
    'ocr_tolerance_flat' => env('AUTOVERIFY_OCR_TOLERANCE_FLAT', 5.0),

    /*
    |--------------------------------------------------------------------------
    | Policy Capping Behavior
    |--------------------------------------------------------------------------
    | When a claim exceeds the company eligibility policy limit:
    | true  => Auto-cap to maximum eligible limit with 'auto_adjusted' source tag.
    | false => Do not auto-verify; send to human verifier review queue.
    */
    'auto_cap_da' => env('AUTOVERIFY_AUTO_CAP_DA', true),
    'auto_cap_travel' => env('AUTOVERIFY_AUTO_CAP_TRAVEL', true),
    'auto_cap_lodging' => env('AUTOVERIFY_AUTO_CAP_LODGING', false),
    'auto_cap_group_meal' => env('AUTOVERIFY_AUTO_CAP_GROUP_MEAL', true),

    /*
    |--------------------------------------------------------------------------
    | Monthly Voucher Auto-Submission
    |--------------------------------------------------------------------------
    | If all claims in an employee's month pass auto-verification:
    | When courier_required_check is true, month will only be auto-submitted if
    | physical courier is marked received (or not required for that claim profile).
    */
    'auto_submit_month_if_all_verified' => env('AUTOVERIFY_AUTO_SUBMIT_MONTH', true),
    'courier_required_check' => env('AUTOVERIFY_COURIER_REQUIRED_CHECK', true),

    /*
    |--------------------------------------------------------------------------
    | Performance & Batch Processing
    |--------------------------------------------------------------------------
    | Chunk size for batch processing pending claims.
    */
    'batch_size' => env('AUTOVERIFY_BATCH_SIZE', 250),
];
