<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ClaimActionLogController extends Controller
{
    /**
     * GET /reports/claim-action-log
     * Render the report page.
     */
    public function index()
    {
        $company_id = session('company_id', 1);
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $monthNames = [
            1=>'January',2=>'February',3=>'March',4=>'April',
            5=>'May',6=>'June',7=>'July',8=>'August',
            9=>'September',10=>'October',11=>'November',12=>'December',
        ];
        return view('reports.claim-action-log', array_merge($organogramOptions, compact('monthNames', 'organogramOptions')));
    }

    /**
     * POST /reports/claim-action-log/data
     * Return filtered JSON for the DataTable.
     */
    public function getData(Request $request)
    {
        $query = ClaimActionLog::query()
            ->where('company_id', session('company_id', 1));

        // ── Filters ────────────────────────────────────────────────────────
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('month')) {
            $query->where('claim_month', $request->month);
        }
        if ($request->filled('year_id')) {
            $query->where('year_id', $request->year_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('action_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('action_at', '<=', $request->date_to);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('action_by')) {
            $query->where('action_by', $request->action_by);
        }

        // Organogram Filters
        $organogramFilters = \App\Helpers\OrganogramFilterHelper::extractFilters($request);
        if (!empty(array_filter($organogramFilters))) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $orgEmpQuery = DB::connection($hrimsConn)->table('hrm_employee_general as eg')->select('eg.EmployeeID');
            \App\Helpers\OrganogramFilterHelper::applyToQuery($orgEmpQuery, $organogramFilters, 'eg', 'eg.EmployeeID', $hrimsDb);
            $orgEmpIds = $orgEmpQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('employee_id', !empty($orgEmpIds) ? $orgEmpIds : [-1]);
        }

        // Single Employee Search (Code or Name) via HRIMS
        if ($request->filled('employee') || $request->filled('emp_code') || $request->filled('emp_name')) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $empQuery = DB::connection($hrimsConn)->table('hrm_employee');

            if ($request->filled('employee')) {
                $term = trim($request->employee);
                $empQuery->where(function ($q) use ($term) {
                    $q->where('EmpCode', 'LIKE', "%{$term}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$term}%")
                      ->orWhere('ECode', 'LIKE', "%{$term}%")
                      ->orWhere('Fname', 'LIKE', "%{$term}%")
                      ->orWhere('Lname', 'LIKE', "%{$term}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$term}%"]);
                });
            }

            if ($request->filled('emp_code')) {
                $code = trim($request->emp_code);
                $empQuery->where(function ($q) use ($code) {
                    $q->where('EmpCode', 'LIKE', "%{$code}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$code}%")
                      ->orWhere('ECode', 'LIKE', "%{$code}%");
                });
            }

            if ($request->filled('emp_name')) {
                $name = trim($request->emp_name);
                $empQuery->where(function ($q) use ($name) {
                    $q->where('Fname', 'LIKE', "%{$name}%")
                      ->orWhere('Lname', 'LIKE', "%{$name}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$name}%"]);
                });
            }

            $matchingIds = $empQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('employee_id', !empty($matchingIds) ? $matchingIds : [-1]);
        }

        // ── Summary Metrics across filtered query ───────────────────────────
        $allFiltered = (clone $query)->get();
        $cntReturn   = $allFiltered->where('action', ClaimActionLog::ACTION_RETURN)->count();
        $cntSubmit   = $allFiltered->where('action', ClaimActionLog::ACTION_SUBMIT)->count();
        $cntResubmit = $allFiltered->where('action', ClaimActionLog::ACTION_RESUBMIT)->count();
        $cntEmp      = $allFiltered->pluck('employee_id')->unique()->filter()->count();

        // ── Sorting ─────────────────────────────────────────────────────────
        $sortBy  = $request->input('sort_by', 'action_at');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id'              => 'id',
            'employee_id'     => 'employee_id',
            'month'           => 'claim_month',
            'exp_id'          => 'exp_id',
            'action'          => 'action',
            'action_by_role'  => 'action_by_role',
            'action_by_name'  => 'action_by_name',
            'reason'          => 'reason',
            'from_status'     => 'from_status',
            'to_status'       => 'to_status',
            'return_count'    => 'return_count',
            'action_at'       => 'action_at',
        ];

        $sortColumn = $allowedSorts[$sortBy] ?? 'action_at';

        // ── Pagination ──────────────────────────────────────────────────────
        $total   = $query->count();
        $perPage = max(1, (int) $request->input('per_page', 15));
        $page    = max(1, (int) $request->input('page', 1));
        $offset  = ($page - 1) * $perPage;

        if ($sortBy === 'employee') {
            $logs = $query->get();
        } else {
            $logs = $query->orderBy($sortColumn, $sortDir)
                ->offset($offset)
                ->limit($perPage)
                ->get();
        }

        $empIds  = $logs->pluck('employee_id')->unique()->filter()->values()->toArray();
        $userIds = $logs->pluck('action_by')->unique()->filter()->values()->toArray();

        // Batch-fetch employee names
        $empNames = [];
        if (!empty($empIds)) {
            try {
                $rows = DB::connection(session('hrims_connection', 'hrims'))
                    ->table('hrm_employee')
                    ->whereIn('EmployeeID', $empIds)
                    ->select('EmployeeID',
                        DB::raw("CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) as emp_name"),
                        DB::raw("COALESCE(EmpCode, EmpCode_New, ECode) as emp_code"))
                    ->get()
                    ->keyBy('EmployeeID');
                foreach ($rows as $id => $r) {
                    $empNames[$id] = trim($r->emp_name) . ' (' . $r->emp_code . ')';
                }
            } catch (\Exception $e) {
                // hrims unavailable — names stay empty
            }
        }

        $monthNames = [
            1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
            7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
        ];

        $actionLabels = [
            ClaimActionLog::ACTION_RETURN       => '<span class="badge bg-danger">Return</span>',
            ClaimActionLog::ACTION_SUBMIT       => '<span class="badge bg-success">Submit</span>',
            ClaimActionLog::ACTION_RESUBMIT     => '<span class="badge bg-warning text-dark">Resubmit</span>',
            ClaimActionLog::ACTION_ALLOW_SUBMIT => '<span class="badge bg-info">Allow Submit</span>',
            ClaimActionLog::ACTION_REVERT       => '<span class="badge bg-warning-subtle text-warning border border-warning">Revert</span>',
        ];

        $roleLabels = [
            ClaimActionLog::ROLE_VERIFIER => 'Verifier',
            ClaimActionLog::ROLE_APPROVER => 'Approver',
            ClaimActionLog::ROLE_ADMIN    => 'Admin',
            ClaimActionLog::ROLE_EMPLOYEE => 'Employee',
        ];

        // Batch-fetch monthexpensefinal details (Approved_Date, RecevingDate, Fin_PayDate)
        $mefData = [];
        if ($logs->isNotEmpty()) {
            $expenseConn = session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
            $yearGroups = $logs->groupBy('year_id');
            foreach ($yearGroups as $yId => $grpLogs) {
                $yId = (int) $yId;
                if ($yId <= 0) continue;
                $empIdsForYear = $grpLogs->pluck('employee_id')->unique()->filter()->values()->toArray();
                $monthsForYear = $grpLogs->pluck('claim_month')->unique()->filter()->values()->toArray();
                if (!empty($empIdsForYear) && !empty($monthsForYear)) {
                    $finalTable = "y{$yId}_monthexpensefinal";
                    try {
                        $tableExists = DB::connection($expenseConn)->selectOne(
                            "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                            [$finalTable]
                        );
                        if ($tableExists && $tableExists->cnt > 0) {
                            $mefRows = DB::connection($expenseConn)
                                ->table($finalTable)
                                ->whereIn('EmployeeID', $empIdsForYear)
                                ->whereIn('Month', $monthsForYear)
                                ->get(['EmployeeID', 'Month', 'Approved_Date', 'RecevingDate', 'Fin_PayDate', 'payment_date']);
                            foreach ($mefRows as $mr) {
                                $mefData[$yId . '_' . $mr->EmployeeID . '_' . $mr->Month] = $mr;
                            }
                        }
                    } catch (\Exception $e) {}
                }
            }
        }

        $formatDate = function ($dt) {
            if (empty($dt) || \App\Support\ZeroDate::none($dt) || \App\Support\ZeroDate::none($dt)) {
                return '—';
            }
            try {
                return \Carbon\Carbon::parse($dt)->format('d-M-Y');
            } catch (\Exception $e) {
                return '—';
            }
        };

        $mappedLogs = $logs->map(function ($log) use ($empNames, $monthNames, $actionLabels, $roleLabels, $mefData, $formatDate) {
            $mefKey = $log->year_id . '_' . $log->employee_id . '_' . $log->claim_month;
            $mef = $mefData[$mefKey] ?? null;

            $approvedDate = ($mef && !empty($mef->Approved_Date) && \App\Support\ZeroDate::has($mef->Approved_Date))
                ? $formatDate($mef->Approved_Date)
                : '—';

            $courierRecvDate = ($mef && !empty($mef->RecevingDate) && \App\Support\ZeroDate::has($mef->RecevingDate))
                ? $formatDate($mef->RecevingDate)
                : '—';

            $paymentDate = ($mef && !empty($mef->Fin_PayDate) && \App\Support\ZeroDate::has($mef->Fin_PayDate))
                ? $formatDate($mef->Fin_PayDate)
                : (($mef && !empty($mef->payment_date) && \App\Support\ZeroDate::has($mef->payment_date))
                    ? $formatDate($mef->payment_date)
                    : '—');

            return [
                'id'                     => $log->id,
                'employee'               => $empNames[$log->employee_id] ?? 'Emp #' . $log->employee_id,
                'month'                  => ($monthNames[$log->claim_month] ?? $log->claim_month) . ' ' . $log->year_id,
                'exp_id'                 => $log->exp_id ?? '—',
                'action'                 => $actionLabels[$log->action] ?? ucfirst($log->action),
                'action_by_role'         => $roleLabels[$log->action_by_role] ?? $log->action_by_role,
                'action_by_name'         => $log->action_by_name ?: 'User #' . $log->action_by,
                'reason'                 => $log->reason ?: '—',
                'from_status'            => $log->from_status ?? '—',
                'to_status'              => $log->to_status ?? '—',
                'return_count'           => $log->return_count,
                'approved_date'          => $approvedDate,
                'courier_receiving_date' => $courierRecvDate,
                'payment_date'           => $paymentDate,
                'action_at'              => \Carbon\Carbon::parse($log->action_at)->format('d-M-Y H:i'),
                '_raw_action_at'         => $log->action_at,
            ];
        });

        if ($sortBy === 'employee') {
            $sorted = $sortDir === 'asc' 
                ? $mappedLogs->sortBy('employee', SORT_NATURAL | SORT_FLAG_CASE) 
                : $mappedLogs->sortByDesc('employee', SORT_NATURAL | SORT_FLAG_CASE);
            $data = $sorted->slice($offset, $perPage)->values();
        } else {
            $data = $mappedLogs->values();
        }

        $lastPage = max(1, (int) ceil($total / $perPage));
        $from     = $total > 0 ? $offset + 1 : 0;
        $to       = min($offset + $perPage, $total);

        return response()->json([
            'data'         => $data,
            'total'        => $total,
            'current_page' => $page,
            'per_page'     => $perPage,
            'last_page'    => $lastPage,
            'from'         => $from,
            'to'           => $to,
            'cnt_return'   => $cntReturn,
            'cnt_submit'   => $cntSubmit,
            'cnt_resubmit' => $cntResubmit,
            'cnt_emp'      => $cntEmp,
        ]);
    }

    /**
     * GET /reports/claim-action-log/summary
     * Return per-employee return count summary.
     */
    public function summary(Request $request)
    {
        $query = ClaimActionLog::query()
            ->where('company_id', session('company_id', 1))
            ->where('action', ClaimActionLog::ACTION_RETURN);

        if ($request->filled('month'))   $query->where('claim_month', $request->month);
        if ($request->filled('year_id')) $query->where('year_id',     $request->year_id);

        // Organogram Filters
        $organogramFilters = \App\Helpers\OrganogramFilterHelper::extractFilters($request);
        if (!empty(array_filter($organogramFilters))) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $orgEmpQuery = DB::connection($hrimsConn)->table('hrm_employee_general as eg')->select('eg.EmployeeID');
            \App\Helpers\OrganogramFilterHelper::applyToQuery($orgEmpQuery, $organogramFilters, 'eg', 'eg.EmployeeID', $hrimsDb);
            $orgEmpIds = $orgEmpQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('employee_id', !empty($orgEmpIds) ? $orgEmpIds : [-1]);
        }

        if ($request->filled('employee') || $request->filled('emp_code') || $request->filled('emp_name')) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $empQuery = DB::connection($hrimsConn)->table('hrm_employee');

            if ($request->filled('employee')) {
                $term = trim($request->employee);
                $empQuery->where(function ($q) use ($term) {
                    $q->where('EmpCode', 'LIKE', "%{$term}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$term}%")
                      ->orWhere('ECode', 'LIKE', "%{$term}%")
                      ->orWhere('Fname', 'LIKE', "%{$term}%")
                      ->orWhere('Lname', 'LIKE', "%{$term}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$term}%"]);
                });
            }

            if ($request->filled('emp_code')) {
                $code = trim($request->emp_code);
                $empQuery->where(function ($q) use ($code) {
                    $q->where('EmpCode', 'LIKE', "%{$code}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$code}%")
                      ->orWhere('ECode', 'LIKE', "%{$code}%");
                });
            }

            if ($request->filled('emp_name')) {
                $name = trim($request->emp_name);
                $empQuery->where(function ($q) use ($name) {
                    $q->where('Fname', 'LIKE', "%{$name}%")
                      ->orWhere('Lname', 'LIKE', "%{$name}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$name}%"]);
                });
            }

            $matchingIds = $empQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('employee_id', !empty($matchingIds) ? $matchingIds : [-1]);
        }

        $summary = $query
            ->selectRaw('employee_id, claim_month, year_id, COUNT(*) as total_returns, MAX(action_at) as last_return_at')
            ->groupBy('employee_id', 'claim_month', 'year_id')
            ->orderByDesc('total_returns')
            ->get();

        // Enrich names
        $empIds = $summary->pluck('employee_id')->unique()->toArray();
        $empNames = [];
        if (!empty($empIds)) {
            try {
                $rows = DB::connection(session('hrims_connection', 'hrims'))
                    ->table('hrm_employee')
                    ->whereIn('EmployeeID', $empIds)
                    ->select('EmployeeID',
                        DB::raw("CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) as emp_name"),
                        DB::raw("COALESCE(EmpCode, EmpCode_New, ECode) as emp_code"))
                    ->get()->keyBy('EmployeeID');
                foreach ($rows as $id => $r) {
                    $empNames[$id] = trim($r->emp_name) . ' (' . $r->emp_code . ')';
                }
            } catch (\Exception $e) {}
        }

        $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                       7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

        return response()->json([
            'data' => $summary->map(fn($r) => [
                'employee'      => $empNames[$r->employee_id] ?? 'Emp #' . $r->employee_id,
                'month'         => ($monthNames[$r->claim_month] ?? $r->claim_month) . ' ' . $r->year_id,
                'total_returns' => $r->total_returns,
                'last_return'   => \Carbon\Carbon::parse($r->last_return_at)->format('d-M-Y H:i'),
            ])->values()
        ]);
    }

    /**
     * GET /reports/claim-action-log/export
     * Export logs to Excel.
     */
    public function export(Request $request)
    {
        return Excel::download(
            new \App\Exports\Reports\ClaimActionLogExport($request),
            'claim_action_log_' . date('Ymd_His') . '.xlsx'
        );
    }
}
