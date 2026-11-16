<?php

namespace App\Services\Imprest;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * IMP-STATUS-01 (16 Nov 2026): one imprest status per employee for the System Notifications tabs (VVNR -> VNR
 * transfers, new joinings, grade promotions), worked out the way Missing Imprest does it:
 *  - eligibility: Travel Imprest Policy 25.1 - Sales / PD / Plant Disease, grade J1-J2 Rs 20,000, J3-M1 Rs 40,000
 *    (current grade and department in HR);
 *  - held: fixed imprest in employee_ledger_balances (fixed_imprest_amt_1 + fixed_imprest_amt_2);
 *  - the employee's latest imprest request (pending / approved / rejected) and any settlement on exit.
 * Four queries for the whole list, whatever its length.
 */
class ImprestStatus
{
    /**
     * @param  int[]  $ids
     * @return array<int,array{key:string,label:string,badge:string,detail:string}>
     */
    public static function forEmployees(array $ids, string $expConn): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }
        try {
            $hr = DB::connection(DatabaseSwitcher::getHrimsConnection())->table('hrm_employee as e')
                ->leftJoin('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_grades as gr', 'gr.id', '=', 'g.GradeId')
                ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
                ->whereIn('e.EmployeeID', $ids)
                ->get(['e.EmployeeID', 'e.DateOfResignation', 'e.DateOfSepration', 'gr.grade_name', 'd.department_name'])
                ->keyBy(fn ($r) => (int) $r->EmployeeID);

            $held = [];
            if (Schema::connection($expConn)->hasTable('employee_ledger_balances')) {
                foreach (DB::connection($expConn)->table('employee_ledger_balances')->whereIn('employee_id', $ids)->groupBy('employee_id')
                    ->get(['employee_id', DB::raw('COALESCE(MAX(fixed_imprest_amt_1),0) + COALESCE(MAX(fixed_imprest_amt_2),0) AS held')]) as $r) {
                    $held[(int) $r->employee_id] = (float) $r->held;
                }
            }
            $reqs = collect();
            if (Schema::connection($expConn)->hasTable('imprest_requests')) {
                $reqs = DB::connection($expConn)->table('imprest_requests')->whereIn('employee_id', $ids)->orderByDesc('id')
                    ->get(['employee_id', 'grade', 'status', 'request_amount', 'remarks'])->groupBy(fn ($r) => (int) $r->employee_id);
            }
            $settled = [];
            if (Schema::connection($expConn)->hasTable(SeparationImprest::TABLE)) {
                $settled = DB::connection($expConn)->table(SeparationImprest::TABLE)->whereIn('employee_id', $ids)
                    ->pluck('action', 'employee_id')->mapWithKeys(fn ($a, $k) => [(int) $k => $a])->all();
            }
        } catch (\Throwable $e) {
            Log::warning('ImprestStatus::forEmployees failed: '.$e->getMessage());

            return [];
        }

        $rs = fn ($n) => 'Rs '.number_format((float) $n);
        $out = [];
        foreach ($ids as $id) {
            $p = $hr->get($id);
            $h = $held[$id] ?? 0.0;
            $grade = strtoupper(str_replace(['-', ' '], '', (string) ($p->grade_name ?? '')));
            $dept = strtolower((string) ($p->department_name ?? ''));
            $eligibleDept = str_contains($dept, 'sales') || str_contains($dept, 'pd') || str_contains($dept, 'plant disease');
            $policy = $eligibleDept ? SeparationImprest::policyAmount($grade) : 0;
            $exit = $p && ((string) $p->DateOfResignation > '2000-01-01' || (string) $p->DateOfSepration > '2000-01-01');
            // the latest request for the current grade, else the latest one
            $list = $reqs->get($id, collect());
            $req = $list->first(fn ($r) => strtoupper(str_replace(['-', ' '], '', (string) $r->grade)) === $grade) ?? $list->first();

            if ($exit) {
                $out[$id] = isset($settled[$id])
                    ? ['key' => 'exit_settled', 'label' => 'Settled on exit', 'badge' => 'secondary', 'detail' => SeparationImprest::ACTIONS[$settled[$id]] ?? $settled[$id]]
                    : ($h > 0.5
                        ? ['key' => 'exit_settle', 'label' => 'Settle on exit', 'badge' => 'danger', 'detail' => 'Held '.$rs($h).' - resigned / separated']
                        : ['key' => 'exit_none', 'label' => 'Exit - none held', 'badge' => 'secondary', 'detail' => 'No imprest is released after resignation']);
                continue;
            }
            if ($policy <= 0) {
                $out[$id] = $h > 0.5
                    ? ['key' => 'held_not_eligible', 'label' => 'Held, not eligible', 'badge' => 'warning', 'detail' => 'Held '.$rs($h).'; grade / department not in the policy']
                    : ['key' => 'not_eligible', 'label' => 'Not eligible', 'badge' => 'light', 'detail' => ($p->grade_name ?? '-').' / '.($p->department_name ?? '-')];
                continue;
            }
            if ($h + 0.5 >= $policy) {
                $out[$id] = ['key' => 'held', 'label' => 'Held', 'badge' => 'success', 'detail' => $rs($h).' (policy '.$rs($policy).')'];
                continue;
            }
            $status = strtolower((string) ($req->status ?? ''));
            if ($status === 'pending') {
                $out[$id] = ['key' => 'pending', 'label' => 'Pending approval', 'badge' => 'warning', 'detail' => 'Request '.$rs($req->request_amount)];
            } elseif ($status === 'approved') {
                $out[$id] = ['key' => 'released', 'label' => 'Released', 'badge' => 'info', 'detail' => $rs($req->request_amount).' approved; not yet in the ledger'];
            } elseif (in_array($status, ['rejected', 'missing_reject'], true)) {
                $out[$id] = ['key' => 'rejected', 'label' => 'Rejected', 'badge' => 'danger', 'detail' => (string) ($req->remarks ?: 'Rejected by Finance')];
            } elseif ($h > 0.5) {
                $out[$id] = ['key' => 'topup_due', 'label' => 'Top-up due', 'badge' => 'warning', 'detail' => 'Held '.$rs($h).', policy '.$rs($policy).': due '.$rs($policy - $h)];
            } else {
                $out[$id] = ['key' => 'due', 'label' => 'Due', 'badge' => 'danger', 'detail' => 'Not released yet: '.$rs($policy)];
            }
        }

        return $out;
    }
}
