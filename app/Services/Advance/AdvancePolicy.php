<?php

namespace App\Services\Advance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADV-POL-01: the written VNR advance policy (config/advance_policy.php) as checks.
 *  - autoAdvanceBlock(): refuses a supply-chain auto advance above 80% of the month's claim
 *    without DA, or before the month's claim is submitted.
 *  - report(): where the current set-up and this year's advances differ from the policy, newest first.
 */
class AdvancePolicy
{
    public static function monthClaimWithoutDa(string $conn, int $yearId, int $employeeId, int $month): float
    {
        return (float) DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('CrBy', $employeeId)->where('ClaimMonth', $month)
            ->whereNotIn('ClaimId', (array) config('advance_policy.supply_chain_auto.exclude_claim_ids', [19, 20]))
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->sum('FilledTAmt');
    }

    public static function monthSubmitted(string $conn, int $yearId, int $employeeId, int $month): bool
    {
        $r = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $employeeId)->where('Month', $month)->first(['DateOfSubmit', 'Status']);

        return $r && ((string) $r->DateOfSubmit > '2000-01-01' || in_array((string) $r->Status, ['Closed', 'Submitted'], true));
    }

    public static function autoAdvanceBlock(string $conn, int $yearId, int $employeeId, int $month, float $amount): ?string
    {
        $cfg = (array) config('advance_policy.supply_chain_auto', []);
        if (($cfg['require_submitted_month'] ?? true) && ! self::monthSubmitted($conn, $yearId, $employeeId, $month)) {
            return 'The employee has not submitted the expense claim for '.date('F', mktime(0, 0, 0, $month, 1)).' yet. The supply-chain advance is given only after that month\'s claim is submitted.';
        }
        $claim = self::monthClaimWithoutDa($conn, $yearId, $employeeId, $month);
        $pct = (float) ($cfg['max_percent'] ?? 80);
        $max = floor($claim * $pct / 100);
        if ($amount > $max + 0.5) {
            return 'The advance (₹'.number_format($amount, 0).') is more than '.$pct.'% of the month\'s claim without DA (₹'.number_format($claim, 0).' → at most ₹'.number_format($max, 0).').';
        }

        return null;
    }

    /** @return array<string, array{title: string, rows: array}> */
    public static function report(string $conn, int $yearId): array
    {
        $hr = DB::connection('hrims');
        $deptName = $hr->table('core_departments')->pluck('department_name', 'id')->all();
        $gradeName = $hr->table('core_grades')->pluck('grade_name', 'id')->all();
        $order = array_flip((array) config('advance_policy.tour.grade_order', []));
        $minTour = $order[config('advance_policy.tour.min_grade', 'J3')] ?? 4;
        $tourDepts = array_map('strtolower', (array) config('advance_policy.tour.departments', []));
        $out = [];

        // 1. set-up: tour (expense) advance masters for support functions
        $rows = [];
        foreach (DB::connection($conn)->table('advance_request_masters')->where('advance_type_id', 2)->where('is_active', 1)->get() as $m) {
            $dn = $deptName[$m->department_id] ?? ('dept '.$m->department_id);
            if (! in_array(strtolower($dn), $tourDepts, true)) {
                continue;
            }
            $grades = array_map(fn ($g) => $gradeName[(int) $g] ?? $g, (array) json_decode((string) $m->grade_ids, true));
            // Finance 10 Nov 2026: S1, S2, J1, J2 and MG on the support-function tour masters are accepted exceptions
            $accepted = (array) config('advance_policy.tour.accepted_exceptions', []);
            $below = array_values(array_filter($grades, fn ($g) => isset($order[$g]) && $order[$g] < $minTour && ! in_array($g, $accepted, true)));
            $missing = array_values(array_filter(['J3', 'J4', 'M1', 'M2', 'M3', 'M4', 'M5'], fn ($g) => ! in_array($g, $grades, true)));
            if ($below || $missing) {
                $rows[] = ['Department' => $dn, 'Grades enabled now' => implode(', ', $grades) ?: '(all)', 'Below J3 (not in policy)' => implode(', ', $below), 'J3 and above not enabled' => implode(', ', $missing), 'Master id' => $m->id];
            }
        }
        $out['tour_setup'] = ['title' => 'Tour advance set-up differs from the policy (Support Functions, J3 and above)', 'rows' => $rows];

        // 2. this year's supply-chain auto advances: above 80% or before the month was submitted
        $rows = [];
        $auto = DB::connection($conn)->table('advance_requests as ar')->join('advance_request_employees as e', 'e.advance_request_id', '=', 'ar.id')
            ->join('advance_request_activities as a', 'a.advance_request_employee_id', '=', 'e.id')
            ->whereNull('ar.deleted_at')->where('ar.year_id', $yearId)->where('a.purpose', 'Auto Advance based on expense percentage')
            ->orderByDesc('ar.created_at')->get(['a.id', 'e.employee_id', 'ar.month', 'a.amount', 'ar.created_at', 'a.payment_status']);
        $pct = (float) config('advance_policy.supply_chain_auto.max_percent', 80);
        foreach ($auto as $a) {
            $claim = self::monthClaimWithoutDa($conn, $yearId, (int) $a->employee_id, (int) $a->month);
            $sub = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $a->employee_id)->where('Month', $a->month)->value('DateOfSubmit');
            $issues = [];
            if ((float) $a->amount > floor($claim * $pct / 100) + 0.5) {
                $issues[] = 'above '.$pct.'% of claim without DA (₹'.number_format($claim, 0).')';
            }
            if (! $sub || $sub < '2000-01-02' || $sub > substr((string) $a->created_at, 0, 10)) {
                $issues[] = 'raised before the month was submitted'.($sub && $sub > '2000-01-01' ? ' (submitted '.date('d-m-Y', strtotime($sub)).')' : '');
            }
            if ($issues) {
                $rows[] = ['Advance' => $a->id, 'Employee' => $a->employee_id, 'Month' => date('M', mktime(0, 0, 0, (int) $a->month, 1)), 'Amount' => (float) $a->amount, 'Raised on' => date('d-m-Y', strtotime($a->created_at)), 'Payment' => $a->payment_status, 'Issue' => implode('; ', $issues)];
            }
        }
        $out['auto'] = ['title' => 'Supply-chain advances outside the rule (this year)', 'rows' => $rows];

        // 3. fixed travel allowance (imprest) amounts against the grade table
        $rows = [];
        $amounts = (array) config('advance_policy.fixed_travel.amounts', []);
        if (Schema::connection('mysql')->hasTable('imprest_requests')) {
            foreach (DB::connection('mysql')->table('imprest_requests')->orderByDesc('created_at')->get() as $r) {
                $g = strtoupper(trim((string) $r->grade));
                $should = $amounts[$g] ?? 0;
                $deptOk = in_array(strtolower(trim((string) $r->department)), array_map('strtolower', (array) config('advance_policy.fixed_travel.departments', [])), true);
                if ((float) $r->eligible_amount !== (float) $should || ! $deptOk) {
                    $rows[] = ['Request' => $r->id, 'Employee' => $r->emp_code.' '.$r->emp_name, 'Department' => $r->department, 'Grade' => $r->grade, 'Eligible amount' => (float) $r->eligible_amount, 'Policy amount' => $deptOk ? $should : 0, 'Status' => $r->status, 'Raised on' => date('d-m-Y', strtotime((string) $r->created_at))];
                }
            }
        }
        $out['fixed'] = ['title' => 'Fixed travel allowance requests that differ from the grade table or department', 'rows' => $rows];

        return $out;
    }
}
