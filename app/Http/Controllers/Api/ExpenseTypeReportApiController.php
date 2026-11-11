<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * API: Export expense-type grouped report scoped to a manager's full reporting
 * hierarchy, combining data from ALL companies (VNR + VVNR + Nursery).
 *
 * GET /api/expense-type-report/export-grouped-by-manager
 *
 * Required:
 *   employee_id   int    Manager whose entire reportee tree should be exported
 *   year_id       int    Financial year ID (falls back to session / default 7)
 *
 * Optional filters:
 *   month         int        1-12  (omit for full year)
 *   claim_type_id int        ClaimId from claimtype table
 *   department_id int
 *   vertical_id   int
 *   function_id   int
 *   grade_id      int
 *   zone_id       int
 *   region_id     int
 *   territory_id  int
 *   emp_status    string     'A' (Active) | 'D' (Deactive)
 *   claim_status  string     e.g. 'Financed', 'Approved'
 *   include_self  bool       1 to include the manager's own claims (default 1)
 *   format        string     'csv' (default) | 'json'
 */
class ExpenseTypeReportApiController extends Controller
{
    /**
     * All companies and their expense DB connection names.
     * HRIMS is shared across all of them.
     */
    private const COMPANIES = [
        ['id' => 1, 'label' => 'VNR', 'expense_conn' => 'expense'],
        ['id' => 11, 'label' => 'VVNR', 'expense_conn' => 'expense_vvnr'],
        ['id' => 3, 'label' => 'Nursery', 'expense_conn' => 'expense_nursery'],
    ];

    /**
     * Recursively resolve ALL reportees (direct + indirect) under a manager
     * using a BFS walk of hrm_employee_general.RepEmployeeID.
     * HRIMS is shared, so one query covers all companies.
     */
    private function getReportingEmployeeIds(int $managerId): array
    {
        $hrimsConn = DatabaseSwitcher::getHrimsConnection();

        $visited = [];
        $queue = [$managerId];

        while (!empty($queue)) {
            $directReports = DB::connection($hrimsConn)
                ->table('hrm_employee_general')
                ->whereIn('RepEmployeeID', $queue)
                ->whereNotIn('EmployeeID', array_merge($visited, [$managerId]))
                ->pluck('EmployeeID')
                ->toArray();

            $directReports = array_map('intval', array_unique($directReports));
            $newIds = array_diff($directReports, $visited, [$managerId]);

            $visited = array_merge($visited, $queue);
            $queue = array_values($newIds);
        }

        $reporteeIds = array_values(array_diff($visited, [$managerId]));

        Log::info("ExpenseTypeReportApi: getReportingEmployeeIds completed", [
            'manager_id' => $managerId,
            'reportee_count' => count($reporteeIds),
            'reportee_ids' => $reporteeIds,
        ]);

        return $reporteeIds;
    }

    /**
     * Build the grouped SQL for a single expense DB connection.
     * Returns [$sql, $bindings].
     *
     * @param string $expenseConn  Laravel connection name for this company
     * @param string $companyLabel Human-readable company label added as a column
     * @param string $table        e.g. y7_expenseclaims
     * @param string $hrimsDb      HRIMS database name (for cross-db joins)
     * @param array  $reporteeIds  Employee IDs to restrict to
     * @param array  $filters      Assoc array of optional filter values
     */
    private function buildCompanyQuery(
        string $expenseConn,
        string $companyLabel,
        int $companyId,
        string $table,
        string $hrimsDb,
        int $yearId,
        array $reporteeIds,
        array $filters
    ): array {
        // Verify the table exists in this DB before querying
        try {
            $tableExists = DB::connection($expenseConn)
                ->select("SHOW TABLES LIKE '{$table}'");
        } catch (\Exception $e) {
            Log::error("ExpenseTypeReportApi: buildCompanyQuery DB error checking table", [
                'company' => $companyLabel,
                'connection' => $expenseConn,
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            return [null, []]; // connection not available
        }

        if (empty($tableExists)) {
            Log::warning("ExpenseTypeReportApi: buildCompanyQuery table does not exist", [
                'company' => $companyLabel,
                'connection' => $expenseConn,
                'table' => $table,
            ]);
            return [null, []];
        }

        $placeholders = implode(',', array_fill(0, count($reporteeIds), '?'));

        $statusCondition =  "c.ClaimStatus IN('Financed', 'Verified' )";
        $where = "WHERE {$statusCondition}
                       AND c.FilledBy > 0 
                       AND c.FilledOkay = 1
                       AND c.ClaimYearId = ?
                       AND c.CrBy IN ({$placeholders})";
        $bindings = array_merge([$yearId], $reporteeIds);

        foreach ([
            'month' => ['col' => 'c.ClaimMonth', 'cast' => 'int'],
            'claim_type_id' => ['col' => 'c.ClaimId', 'cast' => 'int'],
            'department_id' => ['col' => 'g.DepartmentId', 'cast' => 'int'],
            'vertical_id' => ['col' => 'g.EmpVertical', 'cast' => 'int'],
            'function_id' => ['col' => 'g.EmpFunction', 'cast' => 'int'],
            'grade_id' => ['col' => 'g.GradeId', 'cast' => 'int'],
            'zone_id' => ['col' => 'g.ZoneId', 'cast' => 'int'],
            'region_id' => ['col' => 'g.RegionId', 'cast' => 'int'],
            'territory_id' => ['col' => 'g.TerrId', 'cast' => 'int'],
            'emp_status' => ['col' => 'e.EmpStatus', 'cast' => 'str'],
            'claim_status' => ['col' => 'c.ClaimStatus', 'cast' => 'str'],
        ] as $key => $meta) {
            if (!empty($filters[$key])) {
                $where .= " AND {$meta['col']} = ?";
                $bindings[] = $meta['cast'] === 'int'
                    ? (int) $filters[$key]
                    : $filters[$key];
            }
        }

        $mefTable = "y{$yearId}_monthexpensefinal";

        Log::info("ExpenseTypeReportApi: buildCompanyQuery constructed SQL", [
            'company' => $companyLabel,
            'connection' => $expenseConn,
            'table' => $table,
            'mefTable' => $mefTable,
            'yearId_binding' => $yearId,
            'reportee_count' => count($reporteeIds),
            'where' => $where,
        ]);

        $amountCol = ($companyId == 11) ? "SUM(c.VerifyTAmt)" : "SUM(c.FinancedTAmt)";

        $sql = "
            SELECT
                '{$companyLabel}'                                                AS company,
                e.EmpCode_New                                                    AS emp_code,
                CONCAT_WS(' ', e.Fname, e.Sname, e.Lname)                       AS employee_name,
                e.EmpStatus                                                      AS emp_status,
                IFNULL(fun.function_name,  '')                                   AS function_name,
                IFNULL(ver.vertical_name,  '')                                   AS vertical_name,
                IFNULL(dept.department_name, '')                                 AS department_name,
                IFNULL(desig.designation_name, '')                               AS designation_name,
                IFNULL(gr.grade_name,      '')                                   AS grade_name,
                IFNULL(Zn.zone_name,       '')                                   AS zone_name,
                IFNULL(Rg.region_name,     '')                                   AS region_name,
                IFNULL(Tr.territory_name,  '')                                   AS territory_name,
                c.ClaimMonth                                                     AS month,
                IFNULL(t.ClaimName, '')                                          AS claim_name,
                -- SUM(c.FilledTAmt)                                             AS total_employee_amt,
                -- SUM(c.VerifyTAmt)                                             AS total_verify_amt,
                -- SUM(c.ApprTAmt)                                               AS total_approver_amt,
                {$amountCol}                                                     AS total_finance_amt,
                COUNT(*)                                                         AS claim_count
            FROM {$table} c
            -- Only include claims whose month is Closed in monthexpensefinal
            LEFT JOIN {$mefTable} mef
                ON  mef.EmployeeId = c.CrBy
                AND mef.Month      = c.ClaimMonth
                AND mef.Status     = 'Closed'
            LEFT  JOIN claimtype t                           ON c.ClaimId      = t.ClaimId
            LEFT  JOIN {$hrimsDb}.hrm_employee e              ON c.CrBy         = e.EmployeeID
            LEFT  JOIN {$hrimsDb}.hrm_employee_general g      ON e.EmployeeID   = g.EmployeeID
            LEFT  JOIN {$hrimsDb}.core_functions fun          ON g.EmpFunction  = fun.id
            LEFT  JOIN {$hrimsDb}.core_verticals ver          ON g.EmpVertical  = ver.id
            LEFT  JOIN {$hrimsDb}.core_departments dept       ON g.DepartmentId = dept.id
            LEFT  JOIN {$hrimsDb}.core_designation desig      ON g.DesigId      = desig.id
            LEFT  JOIN {$hrimsDb}.core_grades gr              ON g.GradeId      = gr.id
            LEFT  JOIN {$hrimsDb}.core_zones Zn               ON g.ZoneId       = Zn.id
            LEFT  JOIN {$hrimsDb}.core_regions Rg             ON g.RegionId     = Rg.id
            LEFT  JOIN {$hrimsDb}.core_territory Tr           ON g.TerrId       = Tr.id
            {$where}
            GROUP BY
                e.EmpCode_New, e.Fname, e.Sname, e.Lname, e.EmpStatus,
                fun.function_name, ver.vertical_name, dept.department_name,
                desig.designation_name, gr.grade_name,
                Zn.zone_name, Rg.region_name, Tr.territory_name,
                c.ClaimMonth, t.ClaimName
        ";

        return [$sql, $bindings];
    }

    /**
     * Map a master VNR yearId to a specific company's corresponding YearId in its financialyear table.
     * Fallback for VVNR (company 11) is ($yearId - 2) if mapping by Year string fails.
     */
    private function getCompanyYearId(string $expenseConn, int $companyId, int $masterYearId): int
    {
        $vnrYear = null;
        try {
            $vnrYear = DB::connection('expense')
                ->table('financialyear')
                ->where('YearId', $masterYearId)
                ->value('Year');
        } catch (\Exception $e) {
            Log::error("ExpenseTypeReportApi: getCompanyYearId error reading VNR financialyear", [
                'masterYearId' => $masterYearId,
                'error' => $e->getMessage(),
            ]);
        }

        $resolvedYearId = null;

        if (!empty($vnrYear)) {
            try {
                $matchedId = DB::connection($expenseConn)
                    ->table('financialyear')
                    ->where('Year', $vnrYear)
                    ->value('YearId');

                if ($matchedId) {
                    $resolvedYearId = (int) $matchedId;
                }
            } catch (\Exception $e) {
                Log::error("ExpenseTypeReportApi: getCompanyYearId error matching financialyear in target connection", [
                    'connection' => $expenseConn,
                    'vnrYear' => $vnrYear,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($resolvedYearId === null) {
            if ($companyId == 11) {
                $resolvedYearId = max(1, $masterYearId - 2);
            } else {
                $resolvedYearId = $masterYearId;
            }
        }

        Log::info("ExpenseTypeReportApi: getCompanyYearId resolved", [
            'company_id' => $companyId,
            'expense_conn' => $expenseConn,
            'masterYearId' => $masterYearId,
            'vnrYearLabel' => $vnrYear,
            'resolvedCompanyYearId' => $resolvedYearId,
        ]);

        return $resolvedYearId;
    }

    /**
     * Helper to interpolate bindings into a raw SQL query string for easy copying/debugging.
     */
    private function getInterpolatedSql(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            if (is_numeric($binding)) {
                $value = (string) $binding;
            } elseif (is_bool($binding)) {
                $value = $binding ? '1' : '0';
            } elseif (is_null($binding)) {
                $value = 'NULL';
            } else {
                $value = "'" . addslashes((string) $binding) . "'";
            }

            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return preg_replace('/\s+/', ' ', trim($sql));
    }

    /**
     * Fetch rows from all three companies and merge into one sorted collection.
     */
    private function fetchAllCompanies(
        int $yearId,
        array $reporteeIds,
        array $filters
    ): array {
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();
        $rows = [];

        Log::info("ExpenseTypeReportApi: fetchAllCompanies START", [
            'masterYearId' => $yearId,
            'reportee_count' => count($reporteeIds),
            'hrimsDb' => $hrimsDb,
            'filters' => $filters,
        ]);

        foreach (self::COMPANIES as $company) {
            // Pass $yearId directly – getCompanyYearId() will apply the -2 if needed for id=11
            $companyYearId = $this->getCompanyYearId(
                $company['expense_conn'],
                $company['id'],
                $yearId
            );

            $table = "y{$companyYearId}_expenseclaims";

            [$sql, $bindings] = $this->buildCompanyQuery(
                $company['expense_conn'],
                $company['label'],
                $company['id'],
                $table,
                $hrimsDb,
                $companyYearId,
                $reporteeIds,
                $filters
            );

            if ($sql === null) {
                Log::warning("ExpenseTypeReportApi: fetchAllCompanies skipping company due to null SQL", [
                    'company' => $company['label'],
                    'companyYearId' => $companyYearId,
                ]);
                continue;
            }

            $rawSql = $this->getInterpolatedSql($sql, $bindings);

            Log::info("ExpenseTypeReportApi: fetchAllCompanies EXECUTING QUERY", [
                'company' => $company['label'],
                'companyYearId' => $companyYearId,
                'connection' => $company['expense_conn'],
                'raw_sql' => $rawSql,
            ]);

            try {
                $companyRows = DB::connection($company['expense_conn'])
                    ->select($sql, $bindings);

                Log::info("ExpenseTypeReportApi: fetchAllCompanies fetched company data", [
                    'company' => $company['label'],
                    'companyYearId' => $companyYearId,
                    'rows_count' => count($companyRows),
                ]);

                $rows = array_merge($rows, $companyRows);
            } catch (\Exception $e) {
                Log::error("ExpenseTypeReportApiController: skipped {$company['label']} due to query execution error", [
                    'company' => $company['label'],
                    'error' => $e->getMessage(),
                    'raw_sql' => $rawSql,
                ]);
            }
        }

        Log::info("ExpenseTypeReportApi: fetchAllCompanies END", [
            'total_merged_rows' => count($rows),
        ]);

        usort($rows, function ($a, $b) {
            $empCmp = strcmp($a->emp_code ?? '', $b->emp_code ?? '');
            if ($empCmp !== 0)
                return $empCmp;
            $monthCmp = ($b->month ?? 0) <=> ($a->month ?? 0);
            if ($monthCmp !== 0)
                return $monthCmp;
            return strcmp($a->claim_name ?? '', $b->claim_name ?? '');
        });

        return $rows;
    }

    /**
     * Main export handler.
     */
    public function exportGroupedByManager(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900);
        set_time_limit(900);

        // ── Required params ───────────────────────────────────────────────────
        $managerId = (int) $request->input('employee_id');
        if (!$managerId) {
            Log::warning("ExpenseTypeReportApi: missing employee_id parameter");
            return response()->json([
                'success' => false,
                'message' => 'employee_id is required.',
            ], 422);
        }

        $yearId = (int) ($request->input('year_id') ?? session('year_id') ?? 7);
        $format = $request->input('format', 'csv');

        Log::info("ExpenseTypeReportApi: exportGroupedByManager REQUEST RECEIVED", [
            'manager_id' => $managerId,
            'year_id_param' => $request->input('year_id'),
            'year_id_resolved' => $yearId,
            'session_year_id' => session('year_id'),
            'format' => $format,
            'include_self' => $request->boolean('include_self', true),
            'all_inputs' => $request->all(),
        ]);

        // ── Resolve full reportee tree (HRIMS is shared) ──────────────────────
        $reporteeIds = $this->getReportingEmployeeIds($managerId);

        if ($request->boolean('include_self', true)) {
            $reporteeIds[] = $managerId;
            $reporteeIds = array_values(array_unique($reporteeIds));
            Log::info("ExpenseTypeReportApi: included self in reporteeIds", [
                'final_reportee_count' => count($reporteeIds),
                'reportee_ids' => $reporteeIds,
            ]);
        }

        if (empty($reporteeIds)) {
            $emptyMsg = 'No reporting employees found for the given employee_id.';
            Log::warning("ExpenseTypeReportApi: empty reporteeIds for manager", [
                'manager_id' => $managerId,
            ]);

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => $emptyMsg,
                ]);
            }

            return response()->streamDownload(function () use ($emptyMsg) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, [$emptyMsg]);
                fclose($out);
            }, "expense_grouped_manager_{$managerId}_FY{$yearId}_" . date('Ymd_His') . '.csv', [
                'Content-Type' => 'text/csv; charset=utf-8',
            ]);
        }

        // ── Collect optional filters ──────────────────────────────────────────
        $filters = [
            'month' => $request->input('month'),
            'claim_type_id' => $request->input('claim_type_id'),
            'department_id' => $request->input('department_id'),
            'vertical_id' => $request->input('vertical_id'),
            'function_id' => $request->input('function_id'),
            'grade_id' => $request->input('grade_id'),
            'zone_id' => $request->input('zone_id'),
            'region_id' => $request->input('region_id'),
            'territory_id' => $request->input('territory_id'),
            'emp_status' => $request->input('emp_status'),
            'claim_status' => $request->input('claim_status'),
        ];

        $months = [
            '',
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec'
        ];

        // ── JSON response ─────────────────────────────────────────────────────
        if ($format === 'json') {
            try {
                $data = $this->fetchAllCompanies($yearId, $reporteeIds, $filters);

                $result = array_map(function ($row) use ($months) {
                    return [
                        'company' => $row->company,
                        'emp_code' => $row->emp_code,
                        'employee_name' => $row->employee_name,
                        'emp_status' => $row->emp_status,
                        'function' => $row->function_name,
                        'vertical' => $row->vertical_name,
                        'department' => $row->department_name,
                        'designation' => $row->designation_name,
                        'grade' => $row->grade_name,
                        'zone' => $row->zone_name,
                        'region' => $row->region_name,
                        'territory' => $row->territory_name,
                        'month' => $months[(int) ($row->month ?? 0)] ?? $row->month,
                        'claim_type' => $row->claim_name,
                        'total_finance_amt' => (float) ($row->total_finance_amt ?? 0),
                        'claim_count' => (int) ($row->claim_count ?? 0),
                    ];
                }, $data);

                Log::info("ExpenseTypeReportApi: JSON RESPONSE PREPARED", [
                    'manager_id' => $managerId,
                    'year_id' => $yearId,
                    'reportee_count' => count($reporteeIds),
                    'total_rows' => count($result),
                ]);

                return response()->json([
                    'success' => true,
                    'manager_id' => $managerId,
                    'reportee_count' => count($reporteeIds),
                    'total_rows' => count($result),
                    'data' => $result,
                ]);
            } catch (\Exception $e) {
                Log::error("ExpenseTypeReportApi: JSON RESPONSE ERROR", [
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        // ── CSV streaming response ────────────────────────────────────────────
        $filename = "expense_grouped_manager_{$managerId}_FY{$yearId}_" . date('Ymd_His') . '.csv';

        Log::info("ExpenseTypeReportApi: STREAMING CSV RESPONSE START", [
            'manager_id' => $managerId,
            'year_id' => $yearId,
            'filename' => $filename,
        ]);

        return response()->streamDownload(
            function () use ($yearId, $reporteeIds, $filters, $managerId, $months) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($out, [
                    'S.No',
                    'Company',
                    'Emp Code',
                    'Name',
                    'Status',
                    'Function',
                    'Vertical',
                    'Department',
                    'Designation',
                    'Grade',
                    'Zone',
                    'Region',
                    'Territory',
                    'Month',
                    'Claim Type',
                    'Total Finance Amt',
                    'Claim Count',
                ]);

                $data = $this->fetchAllCompanies($yearId, $reporteeIds, $filters);
                $rowNum = 1;

                foreach ($data as $row) {
                    fputcsv($out, [
                        $rowNum++,
                        $row->company ?? '',
                        $row->emp_code ?? '',
                        $row->employee_name ?? '',
                        $row->emp_status ?? '',
                        $row->function_name ?? '',
                        $row->vertical_name ?? '',
                        $row->department_name ?? '',
                        $row->designation_name ?? '',
                        $row->grade_name ?? '',
                        $row->zone_name ?? '',
                        $row->region_name ?? '',
                        $row->territory_name ?? '',
                        $months[(int) ($row->month ?? 0)] ?? '',
                        $row->claim_name ?? '',
                        (float) ($row->total_finance_amt ?? 0),
                        (int) ($row->claim_count ?? 0),
                    ]);
                }

                Log::info("ExpenseTypeReportApi: STREAMING CSV RESPONSE END", [
                    'manager_id' => $managerId,
                    'year_id' => $yearId,
                    'total_rows_streamed' => $rowNum - 1,
                ]);

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
