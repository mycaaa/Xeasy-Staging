<?php

/*
 * SEC-02 (5-6 Nov 2026 regression): admin screens and their data/actions were behind the login
 * check only, so any Employee login could open them (payments data, employee export, S3 file
 * delete, git version control, activity log, ledger import, settings ...).
 *
 * Each rule: path regex (no leading slash) => the permissions that may use it (any one is enough).
 * The permission is the one the screen's menu item already requires, so whoever sees the menu
 * keeps access. Super Admin passes everything (Gate::before). First matching rule wins.
 * A rule with 'redirect' sends users without the permission to that path instead of 403.
 */
return [
    'enabled' => env('ROUTE_ACCESS_ENFORCE', true),

    'rules' => [
        // FILES-01 (UAT-03): claim-stage / Focus export downloads (were public files)
        ['#^(exports/download|storage/temp)/#', ['section_reports', 'claim_stages', 'payment_section', 'finance', 'settings_focus_export', 'treasury']],

        // Utilities / system tools (menu "Utilities" = section_utilities; no menu for the rest)
        ['#^(admin/s3-transfer|version-control|clear-caches|companies-list|system-requirements|temp_files|temp-files|api-manager|api/tables|api/columns/|api/fields-mapping/|api/fields/mapping/|api/map-fields|system-configurations|admin/activity-log|activity-log|test/)#', ['section_utilities']],

        // Git command box in the top bar (shown with @can('git_command_panel')); the controller
        // only checks a fixed password, so any login knowing it could run git on the server
        ['#^run-git-command$#', ['git_command_panel', 'section_utilities']],

        // Ledger import / edit (menu "Employee Ledger (Imprest)")
        ['#^(employee-ledger|api/employee-ledger|admin/employee-ledger)(/|$)#', ['employee_ledger_imprest']],

        // Auto-verify settings and the payment-verification screen
        ['#^admin/settings/auto-verify#', ['seection_settings']],
        ['#^payment-verification/#', ['payment_section']],

        // Settings (menu "Settings" = seection_settings)
        ['#^(financial(/|$)|admin/submission-settings|admin/duplicate-expense|open-month(/|$)|resigned-submission-reminder|city-categories|other-participant-categories(/|$)|vvnr/email-management|advance/admin/)#', ['seection_settings']],

        // Email format / control / logs (menus "Email Format", "Email Control", "Email logs")
        ['#^(email-templates|email-template(/|-list|-variables|$)|email-control|email-notification-schedule)#', ['settings_email_format', 'section_notification']],
        ['#^(sentemails|email-send-log)(/|$)#', ['section_notification', 'settings_email_format']],

        // ELIG-REPORT-01 / PUNCH-TRACK-01: reports (menu "Reports")
        ['#^(eligibility-report|punching-change-report|vehicle-rate-gaps|advance-policy-check)(/|$)#', ['section_reports']],

        // FOCUS-LOG-01: Focus export log (Finance / Focus export users)
        ['#^focus-export-log$#', ['payment_section', 'settings_focus_export', 'finance', 'section_reports']],

        // Focus export mappings (menu "Focus Export")
        ['#^(state-code-mappings|department-code-mappings|claim-focus-mappings|state-field-promotion-mappings|department-vertical-mappings)(/|$)#', ['settings_focus_export']],

        // Treasury (menu "Treasury")
        ['#^treasury/#', ['treasury', 'treasury_advance', 'payment_section']],

        // Payments / finance actions outside the payment_section group (menu "Payment")
        ['#^(payments(/|$)|expense-payments/|update-hold-remark|matrix/|bill-to-company(/|$))#', ['payment_section', 'finance', 'treasury', 'treasury_advance']],

        // Finance claim screen ("Finance Claim" menu, permission finance): its list and actions
        ['#^claims/(display|month-details|close-month|reject-finance|approve-finance|finance|update|get-value|verify|approve)$#', ['finance', 'payment_section']],
        // "claims" is both the employee menu and the Finance Claim menu; the later route definition
        // made everyone land on the Finance screen. Employees go to their own claim page.
        ['#^claims$#', ['finance', 'payment_section'], 'redirect' => 'new-claim'],

        // Imprest automation: reports (view) and Finance (approve)
        ['#^imprest-automation(/|$)#', ['section_reports', 'imprest.approve']],

        // Reports (menu "Reports" = section_reports)
        ['#^(employee-report|reports/extract|employees-multiple-vehicles|api/employees-multiple-vehicles|advance-adjustment-report)#', ['section_reports', 'payment_section']],
        // ADV-REPORT-01 (14 Nov 2026): advance-requests/report and /advanceReport are checked in AdvanceRequestViewController::reportAllowed
        // (reports / payment staff, HODs, and managers for their own team)

        // Verifier acting on a claimant's month (menu "Verifier")
        ['#^verifier/#', ['section_verifier', 'section_data_punching', 'payment_section']],
        ['#^employee/(toggle-allow-submit|return-claim|backend-submit-claim)$#', ['section_verifier', 'section_data_punching', 'payment_section', 'seection_settings']],

        // SEC-03: sample/demo pages with no menu (sample PDF with fixed data, advance table demo)
        ['#^(generate-expense-pdf|advance/table-view)$#', ['section_utilities']],

        // SEC-03: organogram filter lookups used by the report screens (employee names by department)
        ['#^(employees/by-department|verticals/by-function|departments/by-vertical|sub-departments/by-department)$#', ['section_reports', 'payment_section', 'section_verifier', 'section_data_punching', 'seection_settings', 'user_management', 'ledger_summary', 'employee_ledger_imprest', 'department_filter', 'user_filter', 'finance', 'treasury', 'treasury_advance', 'section_dashboard', 'section_daily_activity', 'high_amount_claim_report', 'top_rated_employees', 'claim_stages', 'mrmreport']],

        // Admin employee search (employees/search stays open: the claim form uses it)
        ['#^admin/employees/search$#', ['section_reports', 'payment_section', 'section_verifier', 'section_data_punching', 'seection_settings', 'user_management']],
    ],
];
