<?php

return [
    /*
    | ODO-BD-02 (decision 6 Nov 2026): back-dated odometer claims are approved first by the
    | claimant's reporting manager, then by the BU head / HOD (odo_backdate_settings.approval_type).
    */
    'two_step' => env('ODO_BACKDATE_TWO_STEP', true),

    // Claims entered before this date skip the RM step. Decision 7 Nov 2026: none do - the claims
    // already waiting with the BU head / HOD go to the RM first too, so this is empty by default.
    'two_step_from' => env('ODO_BACKDATE_TWO_STEP_FROM', ''),

    // A rejection needs a remark (?remark=... on the reject calls). The app must send it; set false
    // on live until the new app is out, otherwise rejections from the old app are refused.
    // LATE-PUNCH-02 (14 Nov 2026): GM approval of back-dated odometer punching applies only to these departments
    // (HRIMS core_departments.department_name, comma separated); settings rows for other departments are ignored
    'departments' => env('ODO_BACKDATE_DEPARTMENTS', 'Sales'),

    'reject_remark_required' => env('ODO_BACKDATE_REJECT_REMARK_REQUIRED', true),
];
