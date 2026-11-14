<?php

/*
|--------------------------------------------------------------------------
| Server-side claim submission rules (implementation-point tracker)
|--------------------------------------------------------------------------
| Read by App\Services\ClaimRules\ClaimSubmissionValidator, which every claim
| save path calls (web claimant relay, legacy/mobile claim.php, mobile REST,
| punch/verifier save). CLAIM_RULES_ENABLED=false switches all of them off.
*/

return [
    'enabled' => env('CLAIM_RULES_ENABLED', true),

    // APPR-VER-01: VNR / Nursery reporting managers see a month's claims only after the verifier has verified it
    'approver_after_verifier' => env('CLAIM_RULES_APPROVER_AFTER_VERIFIER', true),

    // Tracker #16: lodging bill above this amount needs an online payment receipt.
    'lodging' => [
        'claim_ids' => [1],
        'receipt_threshold' => (float) env('CLAIM_RULES_LODGING_RECEIPT_THRESHOLD', 10000),
        // Clients that cannot tag a file as a receipt (current mobile app) pass
        // when they upload at least this many files. 0 = no fallback (strict).
        'legacy_min_files' => (int) env('CLAIM_RULES_LODGING_LEGACY_MIN_FILES', 2),
    ],

    // Tracker #18: rail fare ticket category is mandatory. Canonical value => accepted spellings.
    'rail' => [
        'claim_ids' => [14],
        'ticket_categories' => [
            'General' => ['general', 'normal', 'gn'],
            'Tatkal' => ['tatkal', 'ck'],
            'Premium Tatkal' => ['premium tatkal', 'premium', 'pt'],
            'Ladies' => ['ladies'],
            'Senior Citizen' => ['senior citizen'],
        ],
    ],

    // Tracker #20: courier (dispatch) date must be a real date, not in the future and
    // not before min_basis: 'month_start' (first day of the claim month, default) or
    // 'submission' (the month's DateOfSubmit when set, else month start).
    // Tracker #21/#35: participant details mandatory for Team Meeting and for activity
    // claims. An activity claim needs participants when its head mapping has a checked
    // "Participant list" head (adv_expense_head_name_mapping), or its ClaimId is listed here.
    'participants' => [
        'team_meeting_claim_ids' => [42],
        'activity_extra_claim_ids' => array_filter(array_map('intval', explode(',', env('CLAIM_RULES_PARTICIPANT_ACTIVITY_IDS', '')))),
        'activity_head_name' => 'Participant list',
        // PART-48 (decided 5 Nov): Distributor Meeting (48) shows the participant section as optional.
        'activity_optional_claim_ids' => array_filter(array_map('intval', explode(',', env('CLAIM_RULES_PARTICIPANT_OPTIONAL_IDS', '48')))),
        'format_file' => 'templates/participant_list_format.xlsx',
    ],

    // Tracker #38: employee + other participants may not exceed the PAX entered.
    // ClaimId => [employee field, other-participant field] on the upload_bill request.
    'pax' => [
        'claims' => [
            17 => ['meal_participants', 'meal_other_participants'],
            1 => ['lodging_participants', 'lodging_other_participants'],
        ],
    ],

    // Tracker #17: DA Outstation (DOS) needs a proof attachment (hotel bill / night-halt
    // proof). There is no separate night-halt field, so the rule is "at least one file".
    'dos' => [
        'claim_ids' => [20],
    ],

    'courier' => [
        'min_basis' => env('CLAIM_RULES_COURIER_MIN_BASIS', 'month_start'),
    ],

    // DATE-01: bill dates must be real, not in the future, and inside the claim's financial year.
    'bill_date' => [
        'enabled' => env('CLAIM_RULES_BILL_DATE', true),
        // claim report "Bill Date Exception": upload more than this many days after the bill date
        'late_days' => (int) env('CLAIM_RULES_BILL_LATE_DAYS', 60),
    ],

    // LEAVE-01: HRIMS attendance codes treated as leave/absent on the verifier screen
    // (row highlighted, unverified amount defaults to 0). Holiday / week-off / on-duty are not listed.
    'leave_codes' => array_filter(array_map('trim', explode(',', env('CLAIM_RULES_LEAVE_CODES', 'A,L,CL,SL,EL,PL,ML,LWP,LOP,HL')))),

    // GST-01: GSTIN check on the punch form; verifier flags lodging above this amount with no GSTIN.
    'gstin' => [
        'enabled' => env('CLAIM_RULES_GSTIN', true),
        'flag_missing_above' => (float) env('CLAIM_RULES_GSTIN_FLAG_ABOVE', 1000),
    ],

    // ODO-45: typed reading may differ from the photo (OCR) reading by this many km before it is flagged
    'odometer' => [
        'ocr_tolerance_km' => (float) env('CLAIM_RULES_ODO_OCR_TOLERANCE', 1),
    ],
    // RATE-03: Vehicle Policy (Own Your Car) - a car up to this much below the grade's eligible
    // value still gets that grade's rate (HR manual, vehicle-policy). Below that, the lower grade applies.
    'vehicle_value_allowance' => (float) env('VEHICLE_VALUE_ALLOWANCE', 50000),
    // RATE-04: Own Your Car - claimed km after which the reduced rate applies
    'oyc_lifetime_km' => (float) env('OYC_LIFETIME_KM', 252000),

    // Q-02 (audit): a verifier may not verify above the claimed amount, an approver not above the
    // verified (or claimed) amount, without a reason in the remark. Raises let through are logged.
    'stage_caps' => [
        'enabled' => env('CLAIM_RULES_STAGE_CAPS', true),
        'mode' => env('CLAIM_RULES_STAGE_CAPS_MODE', 'enforce'),   // enforce | log
        'tolerance' => (float) env('CLAIM_RULES_STAGE_CAPS_TOLERANCE', 1),   // rupees (rounding)
        // PAY-REMARK-01 (Finance 7 Nov 2026): a VNR approver who cuts the amount must give the reason
        'cut_needs_remark' => env('CLAIM_RULES_CUT_NEEDS_REMARK', true),
        // APPR-CAP-01 (13 Nov 2026): the approver can never raise the amount: ceiling = verified (else claimed), no override
        'approve_never_above_claimed' => env('CLAIM_RULES_APPROVE_NEVER_ABOVE_CLAIMED', true),
        // APPR-CAP-02 (14 Nov 2026): the claimed amount is the ceiling for the approver and the verifier, no override
        'verify_never_above_claimed' => env('CLAIM_RULES_VERIFY_NEVER_ABOVE_CLAIMED', true),
    ],

    // HARDCOPY-PAY-01 (Finance 7 Nov 2026): no payment while the previous month's hard copy is not received
    'hardcopy_before_payment' => env('CLAIM_RULES_HARDCOPY_BEFORE_PAYMENT', true),

    // FF-01 (Finance 7 Nov 2026): separation date entered -> unpaid months on hold, payable with F&F
    'separation_payment_hold' => env('CLAIM_RULES_SEPARATION_HOLD', true),

    // VVNR-ADJ-01 (Finance 7 Nov 2026): from this date the verification excess of every earlier verified
    // month is offered for recovery; Apr-Sep 2026-27 (YearId 6 on VVNR) are reconciled by Finance by hand.
    // VEH-RATE-REPORT-01: weekly HR mail (comma separated addresses)
    'vehicle_rate_report_to' => env('VEHICLE_RATE_REPORT_TO', ''),

    // RATE-EFF-01 (HR 7 Nov 2026): a vehicle / policy change applies from its effective date
    'rate_by_effective_date' => env('RATE_BY_EFFECTIVE_DATE', true),

    // RND-2W-01 (HR 7 Nov 2026): R&D two-wheeler policy 3 = flat Rs 3.50/km (no 6.75 / 1.95 slabs)
    'rnd_2w_flat' => [
        'policies' => [3],
        'rate' => (float) env('RND_2W_FLAT_RATE', 3.50),
        'from' => env('RND_2W_FLAT_FROM', '2026-11-01'),
    ],

    'vvnr_auto_adjust' => [
        'from' => env('VVNR_AUTO_ADJUST_FROM', '2026-11-01'),
        'exclude_months' => [6 => [4, 5, 6, 7, 8, 9]],
    ],

    // Year from the bill date (audit): only the current year, and last year for this many days after it ends.
    'year' => [
        'enabled' => env('CLAIM_RULES_YEAR_OPEN', true),
        'previous_year_grace_days' => (int) env('CLAIM_RULES_PREV_YEAR_GRACE_DAYS', 60),
    ],

    // Submission cut-off and maximum bill age (audit). 0 = off until Finance sets a value.
    'bill_age' => [
        'max_days' => (int) env('CLAIM_RULES_MAX_BILL_AGE_DAYS', 0),
    ],
    // LATE-PUNCH-01: a bill uploaded after its month's due date (cut-off below) is punched only after the GM's or an
    // admin's approval (VVNR: admin). Upload itself is always allowed within the financial year.
    // LATE-PUNCH-02 (14 Nov 2026, management): GM approval is for back-dated ODOMETER punching only, Sales department
    // only (odo_backdate_settings + config odo_backdate.departments). Other bills need no GM approval, so this is off.
    'late_punch' => [
        'enabled' => env('CLAIM_RULES_LATE_PUNCH', false),
    ],
    'cutoff' => [
        // month submit refused after the last submission day of the following month, unless the
        // month has SubPermission = 'Y'. The day comes from Settings > Submission settings
        // (submission_settings.last_submission_day); this value overrides it when set (1-28).
        'enforce_submit' => env('CLAIM_RULES_SUBMISSION_CUTOFF', true),
        'day' => (int) env('CLAIM_RULES_SUBMISSION_CUTOFF_DAY', 0),
    ],

    // Lodging cap: amount <= HRIMS per-night limit for the city tier x nights (punch / verifier save).
    // Above it the save needs a reason (override goes to manual verification/approval). mode: enforce | log.
    'lodging_cap' => [
        'enabled' => env('CLAIM_RULES_LODGING_CAP', true),
        'mode' => env('CLAIM_RULES_LODGING_CAP_MODE', 'enforce'),
    ],

    // Running km limits (HR decision, 10 Nov 2026). Monthly, by department (HRIMS department name) and grade:
    //  - Sales J3-J4: four-wheeler at most 2,500 km a month, and at most 3,000 km a month two- and four-wheeler
    //    together. Above it the punch/verifier save needs a reason and the claim goes to manual approval (logged).
    //  - Breeding and Breeding Support: above 1,200 km a month (2W + 4W) the claim needs the HOD's approval
    //    (Km limit approvals screen); until the HOD approves, the claim cannot be approved.
    //  - No grade-wise annual limit for anyone else (annual_2w / annual_4w stay empty).
    'km_cap' => [
        'enabled' => env('CLAIM_RULES_KM_CAP', true),
        'monthly' => [
            ['name' => 'Sales J3-J4', 'departments' => ['Sales'], 'grades' => ['J3', 'J4'], 'max_4w' => 2500, 'max_total' => 3000, 'approval' => 'reason'],
            ['name' => 'Breeding', 'departments' => ['Breeding', 'Breeding Support'], 'grades' => [], 'max_total' => 1200, 'approval' => 'hod'],
        ],
        // annual cap by grade (2W / 4W), JSON by grade, e.g. CLAIM_RULES_KM_CAP_2W='{"S1":12000,"*":10000}'; none set
        'annual_2w' => json_decode((string) env('CLAIM_RULES_KM_CAP_2W', '{}'), true) ?: [],
        'annual_4w' => json_decode((string) env('CLAIM_RULES_KM_CAP_4W', '{}'), true) ?: [],
    ],

    // RKM-RATE-01 (decided 10 Nov 2026): running km - the verifier / punching corrects readings and the
    // rate is always fetched from the employee's eligibility; true brings back a typed rate.
    'running_km' => [
        'manual_rate' => (bool) env('CLAIM_RULES_RUNNING_KM_MANUAL_RATE', false),
    ],

    // F-02 / F-06 / F-08 (10 Nov 2026): thresholds of the Finance review flags screen (review lists, no blocking)
    'review_flags' => [
        'peer_min_group' => (int) env('REVIEW_PEER_MIN_GROUP', 20),
        'peer_min_outlier_months' => (int) env('REVIEW_PEER_MIN_MONTHS', 3),
        'peer_min_excess' => (float) env('REVIEW_PEER_MIN_EXCESS', 10000),
        'backfill_min_claims' => (int) env('REVIEW_BACKFILL_MIN_CLAIMS', 15),
        'backfill_min_old' => (int) env('REVIEW_BACKFILL_MIN_OLD', 10),
        'backfill_old_days' => (int) env('REVIEW_BACKFILL_OLD_DAYS', 30),
        'backfill_watch_roles' => ['Treasury', 'Super Admin'],
        'round_min_lines' => (int) env('REVIEW_ROUND_MIN_LINES', 30),
        'round_min_amount' => (float) env('REVIEW_ROUND_MIN_AMOUNT', 100),
        'round_min_z' => (float) env('REVIEW_ROUND_MIN_Z', 3),
        'round_min_ratio' => (float) env('REVIEW_ROUND_MIN_RATIO', 2),
    ],

    // E-03: limits come from the HRIMS eligibility; the old local claimlimits table is read only when this is true
    'local_limits' => (bool) env('CLAIM_RULES_LOCAL_LIMITS', false),

    // VEH-RATE-ALERT-01 (HR 11 Nov 2026): zero-rate mail to HR (VEHICLE_RATE_REPORT_TO), Super Admin in Cc
    'vehicle_rate_alert' => [
        'enabled' => (bool) env('VEHICLE_RATE_ALERT', true),
        'repeat_days' => (int) env('VEHICLE_RATE_ALERT_REPEAT_DAYS', 3),
    ],
    // OWN-CAR-VALUE-01 (HR 11 Nov 2026): Own Your Car (policy 12) running km needs the vehicle value in HRIMS;
    // without it the rate is 0. The employees listed here (HRIMS EmployeeIDs) are allowed for now.
    'own_car_value' => [
        'required' => (bool) env('OWN_CAR_VALUE_REQUIRED', true),
        // HR 11 Nov 2026: 748 (619), 1506 (1745), 1568 (1813) allowed for now
        'exempt' => array_values(array_filter(array_map('intval', explode(',', (string) env('OWN_CAR_VALUE_EXEMPT', '619,1745,1813'))))),
    ],

    // SOD-02 (11 Nov 2026): a claimant may not verify or approve their own claim
    // D-09 (11 Nov 2026): mobile API legacy answers put "0000-00-00" back in null old-style date keys (BillDate,
    // Fin_PayDate, ...) so the app in the stores sees what it saw before the zero dates became NULL. Turn off once
    // the app reads the v2 envelope (LV-U03).
    'legacy_zero_dates' => (bool) env('LEGACY_ZERO_DATES', true),

    'sod' => [
        'block_own_claim' => (bool) env('CLAIM_RULES_BLOCK_OWN_CLAIM', true),
    ],
];
