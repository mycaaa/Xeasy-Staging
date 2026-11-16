<?php

namespace App\Services\Imprest;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * IMP-SEP-01 (16 Nov 2026): imprest of employees who resigned or separated.
 *
 * Travel Imprest Policy 25.1 (w.e.f. 17-03-2025): imprest J1-J2 Rs 20,000, J3-M1 Rs 40,000 for Sales and
 * Trialing & PD field staff, settled on resignation / separation. So once HR enters a resignation or separation
 * date:
 *  - no new imprest is released (Missing Imprest refuses it, see ImprestAutomationController::releaseMissing);
 *  - the imprest still held is settled: Finance records it on System Notifications > Separation / Resignation
 *    (recover in F&F / already returned or adjusted / not applicable), open imprest requests are closed, and the
 *    employee leaves the list.
 */
class SeparationImprest
{
    public const TABLE = 'imprest_separation_actions';

    /** Policy amounts by grade (Policy 25.1; same table as ImprestAutomationService / Missing Imprest). */
    public const GRADE_AMOUNTS = ['J1' => 20000, 'J2' => 20000, 'J3' => 40000, 'J4' => 40000, 'M1' => 40000];

    public const ACTIONS = [
        'recover_ff' => 'Recover in F&F settlement',
        'returned' => 'Already returned / adjusted',
        'not_applicable' => 'Not applicable',
    ];

    public static function policyAmount(?string $grade): int
    {
        return self::GRADE_AMOUNTS[strtoupper(str_replace(['-', ' '], '', (string) $grade))] ?? 0;
    }

    private static function day($v): ?string
    {
        return ($v && (string) $v > '2000-01-01') ? substr((string) $v, 0, 10) : null;
    }

    /** Resignation / separation dates of one employee (Y-m-d or null each). */
    public static function dates(int $employeeId): array
    {
        try {
            $e = DB::connection(DatabaseSwitcher::getHrimsConnection())->table('hrm_employee')->where('EmployeeID', $employeeId)
                ->first(['DateOfResignation', 'DateOfSepration']);
        } catch (\Throwable $x) {
            return ['resignation' => null, 'separation' => null];
        }
        return ['resignation' => self::day($e->DateOfResignation ?? null), 'separation' => self::day($e->DateOfSepration ?? null)];
    }

    /** "Resigned on 11-08-2026" / "Separated on ..." or null when neither date is entered. */
    public static function exitText(int $employeeId): ?string
    {
        $d = self::dates($employeeId);
        if ($d['separation']) {
            return 'separated on '.date('d-m-Y', strtotime($d['separation']));
        }

        return $d['resignation'] ? 'resigned on '.date('d-m-Y', strtotime($d['resignation'])) : null;
    }

    /**
     * Employees of the company with a resignation or separation date who still hold imprest (ledger) or have an
     * open imprest request, and whose imprest has not been settled here yet. Latest resignation / separation first.
     */
    public static function pending(string $expConn, int $companyId): array
    {
        try {
            $hrims = DatabaseSwitcher::getHrimsConnection();
            $people = DB::connection($hrims)->table('hrm_employee as e')
                ->leftJoin('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_company as c', 'c.id', '=', 'e.CompanyId')
                ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
                ->leftJoin('core_designation as des', 'des.id', '=', 'g.DesigId')
                ->leftJoin('core_grades as gr', 'gr.id', '=', 'g.GradeId')
                ->where('e.CompanyId', $companyId)
                ->where(fn ($q) => $q->where('e.DateOfResignation', '>', '2000-01-01')->orWhere('e.DateOfSepration', '>', '2000-01-01'))
                ->get(['e.EmployeeID', DB::raw('COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code'),
                    DB::raw("TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name"),
                    'e.EmpStatus', 'e.DateOfResignation', 'e.DateOfSepration', 'c.company_name', 'd.department_name', 'des.designation_name',
                    'gr.grade_name', 'g.ReportingName as reporting_name', 'g.MobileNo_Vnr as mobile', 'g.EmailId_Vnr as email'])
                ->keyBy(fn ($r) => (int) $r->EmployeeID);
            if ($people->isEmpty()) {
                return [];
            }
            $ids = $people->keys()->all();

            $done = [];
            $openReq = collect();
            if (Schema::connection($expConn)->hasTable(self::TABLE)) {
                $done = array_flip(DB::connection($expConn)->table(self::TABLE)->whereIn('employee_id', $ids)->pluck('employee_id')->map(fn ($x) => (int) $x)->all());
            }
            if (Schema::connection($expConn)->hasTable('imprest_requests')) {
                $openReq = DB::connection($expConn)->table('imprest_requests')->whereIn('employee_id', $ids)->where('status', 'pending')
                    ->get(['id', 'employee_id', 'grade', 'request_amount'])->groupBy('employee_id');
            }
            $ledger = app(\App\Http\Controllers\ExpensePaymentController::class)->computeBatchEmployeeLedgerBalances($ids);

            $rows = [];
            foreach ($people as $id => $p) {
                if (isset($done[$id])) {
                    continue;
                }
                $held = round((float) ($ledger[$id]['total_imprest'] ?? 0), 2);
                $open = $openReq->get($id, collect());
                if ($held < 0.5 && $open->isEmpty()) {
                    continue;
                }
                $res = self::day($p->DateOfResignation);
                $sep = self::day($p->DateOfSepration);
                $policy = self::policyAmount($p->grade_name);
                $rows[] = [
                    'EmployeeID' => $id, 'emp_code' => $p->emp_code, 'emp_name' => preg_replace('/\s+/', ' ', (string) $p->emp_name),
                    'emp_status' => $p->EmpStatus, 'company_name' => $p->company_name ?: 'VNR', 'department_name' => $p->department_name,
                    'designation_name' => $p->designation_name, 'grade_name' => $p->grade_name, 'reporting_name' => $p->reporting_name,
                    'mobile' => $p->mobile, 'email' => $p->email, 'resignation_date' => $res, 'separation_date' => $sep,
                    'exit_date' => max((string) $res, (string) $sep),
                    'imprest_held' => $held, 'policy_amount' => $policy,
                    'open_requests' => $open->count(), 'open_request_amount' => (float) $open->sum('request_amount'),
                ];
            }
            usort($rows, fn ($a, $b) => strcmp($b['exit_date'], $a['exit_date']) ?: $b['EmployeeID'] <=> $a['EmployeeID']); // latest on top

            return $rows;
        } catch (\Throwable $e) {
            Log::error('SeparationImprest::pending failed: '.$e->getMessage());

            return [];
        }
    }
}

