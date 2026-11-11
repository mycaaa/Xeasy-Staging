<?php

/*
| ADV-POL-01: VNR Employee Advance Policy (written by Finance, 7 Nov 2026), as rules Xeasy checks.
|
| 1. Advance against expenses
|    A. Fixed travel allowance (imprest): Sales and Trialing & PD, grades J1-M1. J1, J2 = Rs 20,000;
|       J3, J4, M1 = Rs 40,000.
|    B. Supply chain expense-based advance (auto advance): Vegetable and Fruit crop supply chain
|       (Parent Seed, Production). Up to 80% of the month's claim excluding DA, auto-approved, only
|       after the employee has submitted that month's claim; released the next month, before verification.
|    C. Tour advance: Support Functions, grade J3 and above.
| 2. Advance against activities: Sales (ABM raises, ZBM/RBM approves); Trialing & PD (DGM raises and approves).
| Super Admin may enable advances for any employee or department (employee-wise and department-wise
| enablement stays); such advances still go through Xeasy.
*/
return [
    'fixed_travel' => [
        'departments' => ['Sales', 'Trialing & PD'],
        'amounts' => ['J1' => 20000, 'J2' => 20000, 'J3' => 40000, 'J4' => 40000, 'M1' => 40000],
    ],
    'supply_chain_auto' => [
        'departments' => ['Parent Seed', 'Production'],
        'max_percent' => 80,
        'exclude_claim_ids' => [19, 20],   // DA
        'require_submitted_month' => true,
    ],
    'tour' => [
        // Finance decision 10 Nov 2026: exceptional cases, allowed on the support-function tour advance masters
        'accepted_exceptions' => ['S1', 'S2', 'J1', 'J2', 'MG'],
        'departments' => ['Administration', 'Human Resource', 'Legal', 'Accounts Receivable & MIS', 'Finance & Account', 'Information Technology', 'Marketing', 'Quality Assurance'],
        'min_grade' => 'J3',
        // grade order, lowest first (core_grades)
        'grade_order' => ['S1', 'S2', 'J1', 'J2', 'J3', 'J4', 'M1', 'M2', 'M3', 'M4', 'M5', 'L1', 'L2', 'L3', 'L4', 'L5', 'MG'],
    ],
    'activity' => [
        'departments' => ['Sales', 'Trialing & PD'],
    ],
];
