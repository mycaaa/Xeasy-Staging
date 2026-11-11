<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API: Export running KM report scoped to a manager's full reporting hierarchy,
 * combining data from ALL companies (VNR + VVNR + Nursery).
 *
 * Same employee detail columns as expense-type-report.
 * Month columns are dynamic — only months that have actual KM data are included.
 *
 * GET /api/running-km/export-by-manager
 *
 * Required:
 *   employee_id   int    Manager — exports their full reportee tree
 *   year_id       int    Financial year ID (falls back to session / default 7)
 *
 * Optional filters (same as expense-type-report):
 *   month         int    1-12 — restrict to a single month
 *   department_id int
 *   vertical_id   int
 *   function_id   int
 *   grade_id      int
 *   zone_id       int
 *   region_id     int
 *   territory_id  int
 *   emp_status    string 'A' | 'D'
 *   include_self  bool   1 to include the manager's own KM (default 0)
 *   format        string 'csv' (default) | 'json'
 */
class RunningKmReportApiController extends Controller
{
    /** Financial year month order Apr → Mar */
    private const MONTHS = [
        'apr', 'may', 'jun', 'jul', 'aug', 'sep',
        'oct', 'nov', 'dec', 'jan', 'feb', 'mar',
    ];

    private const MONTH_LABELS = [
        'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
        'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
        'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
        'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
    ];

    /** numeric month → short key */
    private const MONTH_NUM_MAP = [
        4 => 'apr', 5 => 'may',  6 => 'jun', 7 => 'jul',
        8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov',
        12 => 'dec', 1 => 'jan',  2 => 'feb',  3 => 'mar',
    ];

    private const COMPANIES = [
        ['id' => 1,  'label' => 'VNR',     'expense_conn' => 'expense'],
        ['id' => 11, 'label' => 'VVNR',    'expense_conn' => 'expense_vvnr'],
        ['id' => 3,  'label' => 'Nursery',  'expense_conn' => 'expense_nursery'],
    ];

    // ── Recursive BFS: all descendant employee IDs ────────────────────────────
 private function getReportingEmployeeIds(int $managerId): array
    {
        $hrimsConn = DatabaseSwitcher::getHrimsConnection();
        $visited   = [];
        $queue     = [$managerId];

        while (! empty($queue)) {
            $direct = DB::connection($hrimsConn)
                ->table('hrm_employee_general')
                ->whereIn('RepEmployeeID', $queue)
                ->whereNotIn('EmployeeID', array_merge($visited, [$managerId]))
                ->pluck('EmployeeID')
                ->toArray();

            $direct  = array_map('intval', array_unique($direct));
            $newIds  = array_diff($direct, $visited, [$managerId]);
            $visited = array_merge($visited, $queue);
            $queue   = array_values($newIds);
        }

        return array_values(array_diff($visited, [$managerId]));
    }

    // ── Fetch rows for one company ────────────────────────────────────────────
    private function fetchCompanyRows(
        string $expConn,
        string $companyLabel,
        int    $yearId,
        array  $reporteeIds,
        array  $filters
    ): array {
        $hrimsDb  = DatabaseSwitcher::getHrimsDatabaseName();
        $mefTable = "y{$yearId}_monthexpensefinal";

        try {
            $exists = DB::connection($expConn)->select("SHOW TABLES LIKE '{$mefTable}'");
            if (empty($exists)) return [];
        } catch (\Exception $e) {
            \Log::warning("RunningKmApi: skipped {$companyLabel} — " . $e->getMessage());
            return [];
        }

        // ── km SELECTs ────────────────────────────────────────────────────────
        $kmSelects = [];
        foreach (self::MONTHS as $m) {
            $kmSelects[] = "MAX(COALESCE(rk.{$m}_2w_km, 0)) AS {$m}_2w_km";
            $kmSelects[] = "MAX(COALESCE(rk.{$m}_4w_km, 0)) AS {$m}_4w_km";
        }
        $kmSelectSql = implode(",\n                ", $kmSelects);

        // ── WHERE + bindings ──────────────────────────────────────────────────
        $empPlaceholders = implode(',', array_fill(0, count($reporteeIds), '?'));

        $where         = "WHERE mef.EmployeeId IN ({$empPlaceholders})";
        $whereBindings = array_values($reporteeIds);

        foreach ([
            'month'         => ['col' => 'mef.Month',      'cast' => 'int'],
            'emp_status'    => ['col' => 'e.EmpStatus',    'cast' => 'str'],
            'department_id' => ['col' => 'g.DepartmentId', 'cast' => 'int'],
            'vertical_id'   => ['col' => 'g.EmpVertical',  'cast' => 'int'],
            'function_id'   => ['col' => 'g.EmpFunction',  'cast' => 'int'],
            'grade_id'      => ['col' => 'g.GradeId',      'cast' => 'int'],
            'zone_id'       => ['col' => 'g.ZoneId',       'cast' => 'int'],
            'region_id'     => ['col' => 'g.RegionId',     'cast' => 'int'],
            'territory_id'  => ['col' => 'g.TerrId',       'cast' => 'int'],
        ] as $key => $meta) {
            if (! empty($filters[$key])) {
                $where         .= " AND {$meta['col']} = ?";
                $whereBindings[] = $meta['cast'] === 'int'
                    ? (int) $filters[$key]
                    : $filters[$key];
            }
        }

        // yearId binding is used in the runningkm LEFT JOIN ON clause
        $allBindings = array_merge([$yearId], $whereBindings);

        $sql = "
            SELECT
                '{$companyLabel}'                                                AS company,
                MAX(e.EmpCode_New)                                               AS emp_code,
                MAX(TRIM(CONCAT_WS(' ', e.Fname, e.Sname, e.Lname)))            AS employee_name,
                MAX(e.EmpStatus)                                                 AS emp_status,
                MAX(IFNULL(fun.function_name,   ''))                             AS function_name,
                MAX(IFNULL(ver.vertical_name,   ''))                             AS vertical_name,
                MAX(IFNULL(dept.department_name,''))                             AS department_name,
                MAX(IFNULL(desig.designation_name,''))                           AS designation_name,
                MAX(IFNULL(gr.grade_name,        ''))                            AS grade_name,
                MAX(IFNULL(Zn.zone_name,         ''))                            AS zone_name,
                MAX(IFNULL(Rg.region_name,       ''))                            AS region_name,
                MAX(IFNULL(Tr.territory_name,    ''))                            AS territory_name,
                MAX(IFNULL(pol.PolicyName,       ''))                            AS policy_name,
                MAX(IFNULL(elig.VehicleType,     ''))                            AS vehicle_type,
                {$kmSelectSql}
            FROM {$mefTable} mef
            INNER JOIN {$hrimsDb}.hrm_employee e               ON mef.EmployeeId  = e.EmployeeID
            LEFT  JOIN {$hrimsDb}.hrm_employee_general g        ON e.EmployeeID   = g.EmployeeID
            LEFT  JOIN {$hrimsDb}.core_functions fun            ON g.EmpFunction  = fun.id
            LEFT  JOIN {$hrimsDb}.core_verticals ver            ON g.EmpVertical  = ver.id
            LEFT  JOIN {$hrimsDb}.core_departments dept         ON g.DepartmentId = dept.id
            LEFT  JOIN {$hrimsDb}.core_designation desig        ON g.DesigId      = desig.id
            LEFT  JOIN {$hrimsDb}.core_grades gr                ON g.GradeId      = gr.id
            LEFT  JOIN {$hrimsDb}.core_zones Zn                 ON g.ZoneId       = Zn.id
            LEFT  JOIN {$hrimsDb}.core_regions Rg               ON g.RegionId     = Rg.id
            LEFT  JOIN {$hrimsDb}.core_territory Tr             ON g.TerrId       = Tr.id
            LEFT  JOIN {$hrimsDb}.hrm_employee_eligibility elig
                ON elig.EmployeeID = e.EmployeeID AND elig.Status = 'A'
            LEFT  JOIN {$hrimsDb}.hrm_master_eligibility_policy pol
                ON pol.PolicyId = elig.VehiclePolicy
            LEFT  JOIN runningkm rk
                ON rk.empid = e.EmployeeID AND rk.yearid = ?
            {$where}
            GROUP BY mef.EmployeeId
            ORDER BY emp_code ASC
        ";

        try {
            return DB::connection($expConn)->select($sql, $allBindings);
        } catch (\Exception $e) {
            \Log::warning("RunningKmApi [{$companyLabel}]: " . $e->getMessage());
            return [];
        }
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
            // ignore
        }

        if (! empty($vnrYear)) {
            try {
                $matchedId = DB::connection($expenseConn)
                    ->table('financialyear')
                    ->where('Year', $vnrYear)
                    ->value('YearId');

                if ($matchedId) {
                    return (int) $matchedId;
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        if ($companyId == 11) {
            return max(1, $masterYearId - 2);
        }

        return $masterYearId;
    }

    // ── Merge all companies and sort by emp_code ──────────────────────────────
    private function fetchAllCompanies(int $yearId, array $reporteeIds, array $filters): array
    {
        $rows = [];
        foreach (self::COMPANIES as $company) {
            $companyYearId = $this->getCompanyYearId(
                $company['expense_conn'],
                $company['id'],
                $yearId
            );

            $rows = array_merge($rows, $this->fetchCompanyRows(
                $company['expense_conn'],
                $company['label'],
                $companyYearId,
                $reporteeIds,
                $filters
            ));
        }
        usort($rows, fn($a, $b) => strcmp($a->emp_code ?? '', $b->emp_code ?? ''));
        return $rows;
    }

    /**
     * Determine which month keys actually have data across all rows.
     * If a specific month filter was passed, respect it but still only
     * include that month's columns if data exists.
     * Always returns a subset of MONTHS in the original Apr→Mar order.
     */
    private function resolveActiveMonths(array $rows, ?string $singleMonthKey): array
    {
        // When a specific month is requested, only those two columns are relevant
        if ($singleMonthKey !== null) {
            foreach ($rows as $row) {
                $has2w = (float) ($row->{"{$singleMonthKey}_2w_km"} ?? 0) > 0;
                $has4w = (float) ($row->{"{$singleMonthKey}_4w_km"} ?? 0) > 0;
                if ($has2w || $has4w) return [$singleMonthKey];
            }
            return [$singleMonthKey]; // even if no data — we asked for it
        }

        // Full year: only include months where at least one row has non-zero KM
        $active = [];
        foreach (self::MONTHS as $m) {
            foreach ($rows as $row) {
                $has = (float) ($row->{"{$m}_2w_km"} ?? 0) > 0
                    || (float) ($row->{"{$m}_4w_km"} ?? 0) > 0;
                if ($has) {
                    $active[] = $m;
                    break;
                }
            }
        }
        return $active;
    }

    // ── Per-row totals across all 12 months ───────────────────────────────────
    private function rowTotals(object $row): array
    {
        $total2w = 0.0;
        $total4w = 0.0;
        foreach (self::MONTHS as $m) {
            $total2w += (float) ($row->{"{$m}_2w_km"} ?? 0);
            $total4w += (float) ($row->{"{$m}_4w_km"} ?? 0);
        }
        return [$total2w, $total4w];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Main handler
    // ─────────────────────────────────────────────────────────────────────────
    public function exportByManager(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900);
        set_time_limit(900);

        // ── Required ──────────────────────────────────────────────────────────
        $managerId = (int) $request->input('employee_id');
        if (! $managerId) {
            return response()->json(['success' => false, 'message' => 'employee_id is required.'], 422);
        }

        $yearId = (int) $request->input('year_id', \App\Support\CurrentYear::id());
        $format = $request->input('format', 'csv');

        // ── Resolve full reportee tree ────────────────────────────────────────
        $reporteeIds = $this->getReportingEmployeeIds($managerId);

        if ($request->boolean('include_self')) {
            $reporteeIds[] = $managerId;
            $reporteeIds   = array_values(array_unique($reporteeIds));
        }

        if (empty($reporteeIds)) {
            $msg = 'No reporting employees found for the given employee_id.';
            if ($format === 'json') {
                return response()->json(['success' => true, 'data' => [], 'message' => $msg]);
            }
            return response()->streamDownload(function () use ($msg) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, [$msg]);
                fclose($out);
            }, "running_km_manager_{$managerId}_FY{$yearId}_" . date('Ymd_His') . '.csv', [
                'Content-Type' => 'text/csv; charset=utf-8',
            ]);
        }

        // ── Normalise month filter → numeric ──────────────────────────────────
        $monthRaw = $request->input('month');
        $monthNum = null;
        $monthKey = null; // short key e.g. 'apr'

        if ($monthRaw !== null && $monthRaw !== '') {
            if (is_numeric($monthRaw)) {
                $monthNum = (int) $monthRaw;
                $monthKey = self::MONTH_NUM_MAP[$monthNum] ?? null;
            } else {
                $k = strtolower(trim($monthRaw));
                if (array_key_exists($k, self::MONTH_LABELS)) {
                    $monthKey = $k;
                    // find numeric value
                    foreach (self::MONTH_NUM_MAP as $num => $mk) {
                        if ($mk === $k) { $monthNum = $num; break; }
                    }
                }
            }
        }

        // ── Filters ───────────────────────────────────────────────────────────
        $filters = [
            'month'         => $monthNum,   // passed as mef.Month filter
            'emp_status'    => $request->input('emp_status'),
            'department_id' => $request->input('department_id'),
            'vertical_id'   => $request->input('vertical_id'),
            'function_id'   => $request->input('function_id'),
            'grade_id'      => $request->input('grade_id'),
            'zone_id'       => $request->input('zone_id'),
            'region_id'     => $request->input('region_id'),
            'territory_id'  => $request->input('territory_id'),
        ];

        // ── Fetch data from all companies ─────────────────────────────────────
        $rows         = $this->fetchAllCompanies($yearId, $reporteeIds, $filters);
        $activeMonths = $this->resolveActiveMonths($rows, $monthKey);

        // ── JSON ──────────────────────────────────────────────────────────────
        if ($format === 'json') {
            try {
                $result = [];

                foreach ($rows as $row) {
                    [$total2w, $total4w] = $this->rowTotals($row);

                    $entry = [
                        'company'      => $row->company,
                        'emp_code'     => $row->emp_code,
                        'employee_name'=> $row->employee_name,
                        'emp_status'   => $row->emp_status,
                        'function'     => $row->function_name,
                        'vertical'     => $row->vertical_name,
                        'department'   => $row->department_name,
                        'designation'  => $row->designation_name,
                        'grade'        => $row->grade_name,
                        'zone'         => $row->zone_name,
                        'region'       => $row->region_name,
                        'territory'    => $row->territory_name,
                        'vehicle_policy' => $row->policy_name,
                        'vehicle_type'   => $row->vehicle_type,
                    ];

                    foreach ($activeMonths as $m) {
                        $entry["{$m}_2w_km"] = (float) ($row->{"{$m}_2w_km"} ?? 0);
                        $entry["{$m}_4w_km"] = (float) ($row->{"{$m}_4w_km"} ?? 0);
                    }

                    $entry['total_2w_km'] = $total2w;
                    $entry['total_4w_km'] = $total4w;
                    $entry['total_km']    = $total2w + $total4w;

                    $result[] = $entry;
                }

                return response()->json([
                    'success'        => true,
                    'manager_id'     => $managerId,
                    'year_id'        => $yearId,
                    'reportee_count' => count($reporteeIds),
                    'total_rows'     => count($result),
                    'months_included'=> $activeMonths,
                    'data'           => $result,
                ]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        // ── CSV ───────────────────────────────────────────────────────────────
        $filename = "running_km_manager_{$managerId}_FY{$yearId}_" . date('Ymd_His') . '.csv';

        return response()->streamDownload(
            function () use ($rows, $activeMonths) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

                // ── Header ────────────────────────────────────────────────────
                $header = [
                    'S.No', 'Company', 'Emp Code', 'Name', 'Status',
                    'Function', 'Vertical', 'Department', 'Designation', 'Grade',
                    'Zone', 'Region', 'Territory',
                    'Vehicle Policy', 'Vehicle Type',
                ];

                foreach ($activeMonths as $m) {
                    $lbl = self::MONTH_LABELS[$m];
                    $header[] = "{$lbl} 2W KM";
                    $header[] = "{$lbl} 4W KM";
                }

                $header[] = 'Total 2W KM';
                $header[] = 'Total 4W KM';
                $header[] = 'Total KM';

                fputcsv($out, $header);

                // ── Rows ──────────────────────────────────────────────────────
                $rowNum  = 1;
                $gt2w    = 0.0;
                $gt4w    = 0.0;

                foreach ($rows as $row) {
                    [$total2w, $total4w] = $this->rowTotals($row);
                    $gt2w += $total2w;
                    $gt4w += $total4w;

                    $line = [
                        $rowNum++,
                        $row->company           ?? '',
                        $row->emp_code          ?? '',
                        $row->employee_name     ?? '',
                        $row->emp_status        ?? '',
                        $row->function_name     ?? '',
                        $row->vertical_name     ?? '',
                        $row->department_name   ?? '',
                        $row->designation_name  ?? '',
                        $row->grade_name        ?? '',
                        $row->zone_name         ?? '',
                        $row->region_name       ?? '',
                        $row->territory_name    ?? '',
                        $row->policy_name       ?? '',
                        $row->vehicle_type      ?? '',
                    ];

                    foreach ($activeMonths as $m) {
                        $km2w = (float) ($row->{"{$m}_2w_km"} ?? 0);
                        $km4w = (float) ($row->{"{$m}_4w_km"} ?? 0);
                        $line[] = $km2w ?: '';
                        $line[] = $km4w ?: '';
                    }

                    $line[] = $total2w ?: '';
                    $line[] = $total4w ?: '';
                    $line[] = ($total2w + $total4w) ?: '';

                    fputcsv($out, $line);
                }

                // ── Grand total row ───────────────────────────────────────────
                if ($rowNum > 1) {
                    $gtLine = array_fill(0, 15, ''); // S.No + 14 info cols
                    $gtLine[0] = 'Grand Total';
                    foreach ($activeMonths as $m) {
                        $gtLine[] = ''; // per-month totals omitted for brevity
                        $gtLine[] = '';
                    }
                    $gtLine[] = $gt2w ?: '';
                    $gtLine[] = $gt4w ?: '';
                    $gtLine[] = ($gt2w + $gt4w) ?: '';
                    fputcsv($out, $gtLine);
                }

                fclose($out);
            },
            $filename,
            [
                'Content-Type'  => 'text/csv; charset=utf-8',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
