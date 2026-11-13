<?php

namespace App\Services;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemAlertsService
{
    /**
     * Check if the given user is authorized to view notifications (Verifier, Admin, Super Admin).
     */
    public static function isAllowedUser(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }

        // 1. Super Admin
        if ($user->hasRole('Super Admin') || $user->hasRole('super_admin') || $user->id == 1) {
            return true;
        }

        // 2. Admin
        if ($user->hasRole('Admin') || $user->hasRole('admin') || $user->can('section_admin') || session('user_type') === 'Admin') {
            return true;
        }

        // 3. Verifier
        if (
            $user->can('section_verifier') ||
            $user->can('verify_pending_list') ||
            $user->hasRole('Verifier') ||
            $user->hasRole('verifier') ||
            session('emp_role') === 'V' ||
            session('verifier') === 'Y'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get combined notification summary and preview lists.
     */
    /**
     * PERF-08 (13 Nov 2026): the bell in the top bar ran ~10 HR / expense queries on every page for admins and
     * verifiers. The same company sees the same alerts, so the top bar reuses the summary for 10 minutes.
     */
    public function cachedNotificationSummary(): array
    {
        $key = 'sys_alerts_summary:'.session('company_id', 1).':'.session('expense_connection', 'expense');
        try {
            return \Illuminate\Support\Facades\Cache::remember($key, 600, fn () => $this->getNotificationSummary());
        } catch (\Throwable $e) {
            return $this->getNotificationSummary();
        }
    }

    public function getNotificationSummary(): array
    {
        $companyId = session('company_id', 1);

        $transfers      = $this->getVvnrToVnrTransfers(90);
        $joinings       = $this->getNewJoinings(90);
        $missingImprest = $this->getMissingImprest($companyId);
        $promotions     = $this->getPromotions(90);

        $totalCount = count($transfers) + count($joinings) + count($missingImprest) + count($promotions);

        return [
            'total_count'           => $totalCount,
            'vvnr_transfers_count'  => count($transfers),
            'new_joinings_count'   => count($joinings),
            'missing_imprest_count' => count($missingImprest),
            'promotions_count'      => count($promotions),
            'transfers'             => $transfers,
            'joinings'              => $joinings,
            'missing_imprest'       => $missingImprest,
            'promotions'            => $promotions,
        ];
    }

    /**
     * Get VVNR to VNR transferred employees using MoveVESStoESS = 'Y' and MoveVESStoESS_Date (default 90 days).
     */
    public function getVvnrToVnrTransfers(int $days = 90): array
    {
        try {
            $hrimsConn = DatabaseSwitcher::getHrimsConnection();
            $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName();
            $expConn   = DatabaseSwitcher::getExpenseConnection();

            // 1. Primary Query: Active employees with MoveVESStoESS = 'Y' and valid transfer date in $days window
            $transfers = DB::connection($hrimsConn)->select("
                SELECT
                    e.EmployeeID,
                    COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                    TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                    e.CompanyId AS current_company_id,
                    11 AS previous_company,
                    e.MoveVESStoESS_Date AS transfer_date,
                    'company_transfer' AS transfer_type,
                    g.MobileNo_Vnr AS mobile,
                    g.EmailId_Vnr AS email,
                    g.ReportingName AS reporting_name,
                    d.department_name,
                    des.designation_name
                FROM {$hrimsDb}.hrm_employee e
                LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                WHERE e.EmpStatus = 'A'
                  AND UPPER(e.MoveVESStoESS) = 'Y'
                  AND e.MoveVESStoESS_Date IS NOT NULL
                  AND e.MoveVESStoESS_Date != '0000-00-00'
                  AND e.MoveVESStoESS_Date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                ORDER BY e.MoveVESStoESS_Date DESC, e.EmployeeID DESC
                LIMIT 100
            ");

            // Fallback 1: Query active employees where MoveVESStoESS = 'Y' and has valid MoveVESStoESS_Date
            if (empty($transfers)) {
                $transfers = DB::connection($hrimsConn)->select("
                    SELECT
                        e.EmployeeID,
                        COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                        TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                        e.CompanyId AS current_company_id,
                        11 AS previous_company,
                        COALESCE(e.MoveVESStoESS_Date, g.DateJoining, e.DateOfJoining) AS transfer_date,
                        'company_transfer' AS transfer_type,
                        g.MobileNo_Vnr AS mobile,
                        g.EmailId_Vnr AS email,
                        g.ReportingName AS reporting_name,
                        d.department_name,
                        des.designation_name
                    FROM {$hrimsDb}.hrm_employee e
                    LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                    LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                    WHERE e.EmpStatus = 'A'
                      AND UPPER(e.MoveVESStoESS) = 'Y'
                      AND e.MoveVESStoESS_Date IS NOT NULL
                      AND e.MoveVESStoESS_Date != '0000-00-00'
                    ORDER BY e.MoveVESStoESS_Date DESC, e.EmployeeID DESC
                    LIMIT 50
                ");
            }

            // Fallback 2: Check transfer_notifications table in expense DB if present
            if (empty($transfers)) {
                $hasNotif = DB::connection($expConn)->select("SHOW TABLES LIKE 'transfer_notifications'");
                if (!empty($hasNotif)) {
                    $notifTransfers = DB::connection($expConn)->select("
                        SELECT
                            tn.employee_id AS EmployeeID,
                            COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                            TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                            e.CompanyId AS current_company_id,
                            11 AS previous_company,
                            tn.transfer_date,
                            tn.notification_type AS transfer_type,
                            g.MobileNo_Vnr AS mobile,
                            g.EmailId_Vnr AS email,
                            g.ReportingName AS reporting_name,
                            d.department_name,
                            des.designation_name
                        FROM transfer_notifications tn
                        JOIN {$hrimsDb}.hrm_employee e ON tn.employee_id = e.EmployeeID
                        LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                        LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                        LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                        ORDER BY tn.id DESC
                        LIMIT 100
                    ");

                    if (!empty($notifTransfers)) {
                        return array_map(fn($t) => (array) $t, $notifTransfers);
                    }
                }
            }

            // Fallback 3: Query active employees in Company 1 or 11
            if (empty($transfers)) {
                $transfers = DB::connection($hrimsConn)->select("
                    SELECT
                        e.EmployeeID,
                        COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                        TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                        e.CompanyId AS current_company_id,
                        CASE WHEN e.CompanyId = 1 THEN 11 ELSE 1 END AS previous_company,
                        COALESCE(g.DateJoining, e.DateOfJoining) AS transfer_date,
                        'company_transfer' AS transfer_type,
                        g.MobileNo_Vnr AS mobile,
                        g.EmailId_Vnr AS email,
                        g.ReportingName AS reporting_name,
                        d.department_name,
                        des.designation_name
                    FROM {$hrimsDb}.hrm_employee e
                    LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                    LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                    WHERE e.EmpStatus = 'A'
                      AND e.CompanyId IN (1, 11)
                    ORDER BY e.EmployeeID DESC
                    LIMIT 50
                ");
            }

            return array_map(fn($t) => (array) $t, $transfers);
        } catch (\Exception $e) {
            Log::error('SystemAlertsService@getVvnrToVnrTransfers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get New Joinings using hrm_employee_general.DateJoining (default 90 days).
     */
    public function getNewJoinings(int $days = 90): array
    {
        try {
            $hrimsConn = DatabaseSwitcher::getHrimsConnection();
            $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName();

            // Primary query: Active employees who joined in the last $days using hrm_employee_general.DateJoining
            $joinings = DB::connection($hrimsConn)->select("
                SELECT
                    e.EmployeeID,
                    COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                    TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                    e.CompanyId,
                    c.company_name,
                    g.DateJoining AS DateJoining,
                    g.MobileNo_Vnr AS mobile,
                    g.EmailId_Vnr AS email,
                    g.ReportingName AS reporting_name,
                    d.department_name,
                    des.designation_name,
                    gr.grade_name,
                    ccvs.city_village_name AS hq_name
                FROM {$hrimsDb}.hrm_employee e
                JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                LEFT JOIN {$hrimsDb}.core_company c ON e.CompanyId = c.id
                LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id
                LEFT JOIN {$hrimsDb}.core_city_village_by_state ccvs ON g.HqId = ccvs.id
                WHERE e.EmpStatus = 'A'
                  AND g.DateJoining IS NOT NULL
                  AND g.DateJoining != '0000-00-00'
                  AND g.DateJoining >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                ORDER BY g.DateJoining DESC
                LIMIT 100
            ");

            // Fallback 1: Try 180 days window if 90 days returns empty
            if (empty($joinings)) {
                $joinings = DB::connection($hrimsConn)->select("
                    SELECT
                        e.EmployeeID,
                        COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                        TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                        e.CompanyId,
                        c.company_name,
                        g.DateJoining AS DateJoining,
                        g.MobileNo_Vnr AS mobile,
                        g.EmailId_Vnr AS email,
                        g.ReportingName AS reporting_name,
                        d.department_name,
                        des.designation_name,
                        gr.grade_name,
                        ccvs.city_village_name AS hq_name
                    FROM {$hrimsDb}.hrm_employee e
                    JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_company c ON e.CompanyId = c.id
                    LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                    LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                    LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id
                    LEFT JOIN {$hrimsDb}.core_city_village_by_state ccvs ON g.HqId = ccvs.id
                    WHERE e.EmpStatus = 'A'
                      AND g.DateJoining IS NOT NULL
                      AND g.DateJoining != '0000-00-00'
                      AND g.DateJoining >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    ORDER BY g.DateJoining DESC
                    LIMIT 100
                ");
            }

            // Fallback 2: Latest active employees ordered by hrm_employee_general.DateJoining DESC
            if (empty($joinings)) {
                $joinings = DB::connection($hrimsConn)->select("
                    SELECT
                        e.EmployeeID,
                        COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                        TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                        e.CompanyId,
                        c.company_name,
                        g.DateJoining AS DateJoining,
                        g.MobileNo_Vnr AS mobile,
                        g.EmailId_Vnr AS email,
                        g.ReportingName AS reporting_name,
                        d.department_name,
                        des.designation_name,
                        gr.grade_name,
                        ccvs.city_village_name AS hq_name
                    FROM {$hrimsDb}.hrm_employee e
                    JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_company c ON e.CompanyId = c.id
                    LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                    LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                    LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id
                    LEFT JOIN {$hrimsDb}.core_city_village_by_state ccvs ON g.HqId = ccvs.id
                    WHERE e.EmpStatus = 'A'
                      AND g.DateJoining IS NOT NULL
                      AND g.DateJoining != '0000-00-00'
                    ORDER BY g.DateJoining DESC
                    LIMIT 50
                ");
            }

            return array_map(fn($j) => (array) $j, $joinings);
        } catch (\Exception $e) {
            Log::error('SystemAlertsService@getNewJoinings error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Promoted Employees from hrm_pms_appraisal_history in the last 90 days.
     */
    public function getPromotions(int $days = 90): array
    {
        try {
            $hrimsConn = DatabaseSwitcher::getHrimsConnection();
            $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName();

            // Check if hrm_pms_appraisal_history table exists
            $hasTable = DB::connection($hrimsConn)
                ->select("SHOW TABLES LIKE 'hrm_pms_appraisal_history'");

            if (!empty($hasTable)) {
                $promotions = DB::connection($hrimsConn)->select("
                    SELECT
                        e.EmployeeID,
                        COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code,
                        TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) AS emp_name,
                        c.company_name,
                        ah.Current_Grade AS previous_grade,
                        ah.Proposed_Grade AS new_grade,
                        ah.SalaryChange_Date AS promotion_date,
                        g.MobileNo_Vnr AS mobile,
                        g.EmailId_Vnr AS email,
                        g.ReportingName AS reporting_name,
                        d.department_name,
                        des.designation_name
                    FROM {$hrimsDb}.hrm_pms_appraisal_history ah
                    JOIN {$hrimsDb}.hrm_employee e ON ah.EmployeeID = e.EmployeeID
                    LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_company c ON e.CompanyId = c.id
                    LEFT JOIN {$hrimsDb}.core_departments d ON g.DepartmentId = d.id
                    LEFT JOIN {$hrimsDb}.core_designation des ON g.DesigId = des.id
                    WHERE e.EmpStatus = 'A'
                      AND ah.Current_Grade != ah.Proposed_Grade
                      AND ah.Proposed_Grade IS NOT NULL
                      AND ah.Proposed_Grade != ''
                      AND ah.SalaryChange_Date IS NOT NULL
                      AND ah.SalaryChange_Date != '0000-00-00'
                      AND ah.SalaryChange_Date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                    ORDER BY ah.SalaryChange_Date DESC, ah.AppHistoryId DESC
                    LIMIT 100
                ");

                if (!empty($promotions)) {
                    return array_map(fn($p) => (array) $p, $promotions);
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('SystemAlertsService@getPromotions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Missing Imprest List strictly matching /imprest-automation/missing-imprest logic.
     */
    public function getMissingImprest(int $companyId = 1): array
    {
        try {
            $hrimsConn = DatabaseSwitcher::getHrimsConnection();
            $expConn   = session('expense_connection', DatabaseSwitcher::getExpenseConnection());

            $gradeAmounts = ['J1' => 20000, 'J2' => 20000, 'J3' => 40000, 'J4' => 40000, 'M1' => 40000];
            $eligibleGradeKeys = ['J1', 'J2', 'J3', 'J4', 'M1', 'J-1', 'J-2', 'J-3', 'J-4', 'M-1'];

            // Fetch eligible grade IDs from core_grades using normalized name matching
            $eligibleGradeIds = [];
            try {
                $eligibleGradeIds = DB::connection($hrimsConn)
                    ->table('core_grades')
                    ->get()
                    ->filter(function ($g) use ($gradeAmounts) {
                        $clean = strtoupper(str_replace(['-', ' '], '', $g->grade_name ?? ''));
                        return isset($gradeAmounts[$clean]);
                    })
                    ->pluck('id')
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('SystemAlertsService fetch eligible grade IDs failed: ' . $e->getMessage());
            }

            $empQuery = DB::connection($hrimsConn)
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->join('core_grades as g', 'eg.GradeId', '=', 'g.id')
                ->join('core_departments as d', 'eg.DepartmentId', '=', 'd.id')
                ->leftJoin('core_company as c', 'e.CompanyId', '=', 'c.id')
                ->leftJoin('core_city_village_by_state as hq', 'eg.HqId', '=', 'hq.id')
                ->where('e.EmpStatus', 'A')
                ->where(function ($q) use ($eligibleGradeIds, $eligibleGradeKeys) {
                    if (!empty($eligibleGradeIds)) {
                        $q->whereIn('eg.GradeId', $eligibleGradeIds);
                    } else {
                        $q->whereIn('g.grade_name', $eligibleGradeKeys);
                    }
                })
                ->where(function ($q) {
                    $q->where(DB::raw('LOWER(d.department_name)'), 'like', '%sales%')
                      ->orWhere(DB::raw('LOWER(d.department_name)'), 'like', '%pd%')
                      ->orWhere(DB::raw('LOWER(d.department_name)'), 'like', '%plant disease%');
                })
                ->select(
                    'e.EmployeeID',
                    DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) AS emp_name"),
                    DB::raw("COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code"),
                    'e.CompanyId',
                    'c.company_name',
                    DB::raw("eg.DateJoining AS DateJoining"),
                    'g.grade_name',
                    'd.department_name',
                    'hq.city_village_name as hq_name',
                    'eg.ReportingName as reporting_name'
                )
                ->orderBy('d.department_name')
                ->orderBy('g.grade_name')
                ->orderBy('e.Fname');

            $employees = (clone $empQuery)->where('e.CompanyId', $companyId)->get();

            if ($employees->isEmpty()) {
                // Fallback 1: Try without companyId filter if 0 rows returned
                $employees = $empQuery->get();
            }

            if ($employees->isEmpty()) {
                return [];
            }

            // Ledger balances per employee (fixed_imprest_amt_1 + fixed_imprest_amt_2)
            $ledgerBalances = collect();
            try {
                $targetExpConn = null;
                if (DB::connection($expConn)->getSchemaBuilder()->hasTable('employee_ledger_balances')) {
                    $targetExpConn = $expConn;
                } elseif (config()->has('database.connections.expense') && DB::connection('expense')->getSchemaBuilder()->hasTable('employee_ledger_balances')) {
                    $targetExpConn = 'expense';
                }

                if ($targetExpConn) {
                    $ledgerBalances = DB::connection($targetExpConn)
                        ->table('employee_ledger_balances')
                        ->whereIn('employee_id', $employees->pluck('EmployeeID')->toArray())
                        ->groupBy('employee_id')
                        ->select(
                            'employee_id',
                            DB::raw('COALESCE(MAX(fixed_imprest_amt_1), 0) as amt_1'),
                            DB::raw('COALESCE(MAX(fixed_imprest_amt_2), 0) as amt_2')
                        )
                        ->get()
                        ->keyBy('employee_id');
                }
            } catch (\Exception $e) {
                Log::warning('SystemAlertsService missing imprest check warning: ' . $e->getMessage());
            }

            // Fetch existing imprest_requests status and remarks across available connections (expense, mysql)
            $imprestReqs = collect();
            try {
                $connsToTry = array_unique([session('expense_connection', 'expense'), 'mysql', config('database.default')]);
                foreach ($connsToTry as $c) {
                    try {
                        if (DB::connection($c)->getSchemaBuilder()->hasTable('imprest_requests')) {
                            $records = DB::connection($c)
                                ->table('imprest_requests')
                                ->whereIn('employee_id', $employees->pluck('EmployeeID')->toArray())
                                ->orderBy('id', 'desc')
                                ->get()
                                ->groupBy('employee_id');

                            if ($records->isNotEmpty()) {
                                foreach ($records as $empId => $recs) {
                                    if (!$imprestReqs->has($empId)) {
                                        $imprestReqs->put($empId, $recs);
                                    }
                                }
                            }
                        }
                    } catch (\Exception $exConn) {
                        // Skip connection if unavailable
                    }
                }
            } catch (\Exception $e) {
                Log::warning('SystemAlertsService fetch imprest_requests warning: ' . $e->getMessage());
            }

            $result = $employees
                ->map(function ($e) use ($gradeAmounts, $ledgerBalances, $imprestReqs) {
                    $arr = (array) $e;
                    $cleanGrade = strtoupper(str_replace(['-', ' '], '', $e->grade_name ?? ''));
                    $arr['eligible_amount'] = $gradeAmounts[$cleanGrade] ?? 20000;

                    $lb = $ledgerBalances->get($e->EmployeeID);
                    $currentImprest = $lb ? ((float)$lb->amt_1 + (float)$lb->amt_2) : 0.0;
                    $remainingAmount = max(0.0, (float)$arr['eligible_amount'] - (float)$currentImprest);

                    $arr['current_imprest'] = $currentImprest;
                    $arr['request_amount']  = $remainingAmount;
                    $arr['is_upgrade']      = ($currentImprest > 0);

                    // Match imprest request for the current grade
                    $userReqs = $imprestReqs->get($e->EmployeeID, collect());
                    $currentReq = $userReqs->first(function ($r) use ($cleanGrade) {
                        $reqGrade = strtoupper(str_replace(['-', ' '], '', $r->grade ?? ''));
                        return $reqGrade === $cleanGrade;
                    }) ?? $userReqs->first();

                    if ($currentReq) {
                        $reqGrade = strtoupper(str_replace(['-', ' '], '', $currentReq->grade ?? ''));
                        if ($reqGrade !== $cleanGrade || (float)$currentReq->eligible_amount < (float)$arr['eligible_amount']) {
                            $arr['imprest_status']  = null;
                            $arr['imprest_remarks'] = null;
                        } else {
                            $status = strtolower($currentReq->status ?? '');
                            $arr['imprest_status']  = in_array($status, ['rejected', 'reject', 'missing_reject']) ? 'rejected' : $currentReq->status;
                            $arr['imprest_remarks'] = $currentReq->remarks;
                        }
                    } else {
                        $arr['imprest_status']  = null;
                        $arr['imprest_remarks'] = null;
                    }

                    return $arr;
                })
                ->filter(fn($arr) => ($arr['request_amount'] ?? 0) > 0)
                ->values()
                ->toArray();

            return $result;
        } catch (\Exception $ex) {
            Log::error('SystemAlertsService@getMissingImprest error: ' . $ex->getMessage());
            return [];
        }
    }
}
