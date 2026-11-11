<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExpenseTreeReportController extends Controller
{
    /**
     * Display the main Expense Tree Report page.
     * All options (years, companies) loaded 100% dynamically from the database.
     */
    public function index()
    {
        $companyId = (int) session('company_id', 1);
        $isVVNR    = ($companyId == 11);
        $expConn   = DatabaseSwitcher::getExpenseConnection($companyId);

        // Fetch all financial years directly from the database table
        try {
            $years = DB::connection($expConn)->table('financialyear')
                ->orderByDesc('YearId')
                ->get(['YearId', 'Year', 'Status']);
        } catch (\Exception $e) {
            Log::error('ExpenseTreeReportController::index financialyear query error: ' . $e->getMessage());
            $years = collect([]);
        }

        // Dynamically determine the active financial year from DB
        $activeYear = $years->firstWhere('Status', 'A')
            ?? $years->firstWhere('YearId', session('year_id'))
            ?? $years->first();
        $currentYearId = $activeYear ? $activeYear->YearId : \App\Support\CurrentYear::id();

        // Fetch active companies directly from HRIMS database table
        try {
            $companies = DB::connection('hrims')->table('core_company')
                ->where('is_active', 1)
                ->orderBy('id')
                ->pluck('company_name', 'id')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('ExpenseTreeReportController::index core_company query error: ' . $e->getMessage());
            $companies = [];
        }

        // Fetch all 9 organogram levels + policy via OrganogramFilterHelper
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        return view('admin.reports.expense-tree-report', array_merge([
            'years'         => $years,
            'companies'     => $companies,
            'companyId'     => $companyId,
            'currentYearId' => $currentYearId,
            'isVVNR'        => $isVVNR,
        ], $organogramOptions));
    }

    /**
     * Fetch hierarchical tree data aggregated across organizational entities directly from DB.
     * ZERO static fallback data — all nodes, names, metrics, and levels come from the database.
     */
    public function getTreeData(Request $request)
    {
        try {
            $companyId = (int) ($request->input('company_id') ?: session('company_id', 1));
            $isVVNR    = ($companyId == 11);
            $expConn   = DatabaseSwitcher::getExpenseConnection($companyId);
            $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName($companyId);

            // Dynamically resolve year from DB if not passed
            $requestedYearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            if (empty($requestedYearId)) {
                try {
                    $activeFy = DB::connection($expConn)->table('financialyear')
                        ->where('Status', 'A')->orderByDesc('YearId')->first()
                        ?? DB::connection($expConn)->table('financialyear')->orderByDesc('YearId')->first();
                    $yearId = $activeFy ? (int) $activeFy->YearId : \App\Support\CurrentYear::id();
                } catch (\Exception $e) {
                    $yearId = \App\Support\CurrentYear::id();
                }
            } else {
                $yearId = (int) $requestedYearId;
            }

            $table = "y{$yearId}_expenseclaims";

            // Verify expense table exists in the database
            try {
                $tableExists = DB::connection($expConn)->select("SHOW TABLES LIKE '{$table}'");
                if (empty($tableExists)) {
                    return response()->json([
                        'success'      => false,
                        'message'      => "Database table `{$table}` does not exist in the database.",
                        'grand_totals' => $this->emptyTotals(),
                        'hierarchy'    => [],
                        'total_rows'   => 0,
                    ]);
                }
            } catch (\Exception $chkEx) {
                Log::warning('Table check error: ' . $chkEx->getMessage());
            }

            // Valid claims condition from database
            $claimWhere = [
                "c.ClaimStatus NOT IN ('Deactivate', 'Draft')",
                "(c.FilledBy > 0 OR c.FilledOkay = 1 OR c.FilledTAmt > 0 OR c.CrBy > 0)"
            ];
            $claimBindings = [];

            // Optional month filter if provided
            $month = $request->input('month');
            if (!empty($month) && is_numeric($month)) {
                $claimWhere[] = "c.ClaimMonth = ?";
                $claimBindings[] = (int) $month;
            }

            // Additional Organogram & Policy Filters from drawer
            $filters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::appendRawSqlFilters($claimWhere, $claimBindings, $filters, 'g', 'vfm', $hrimsDb, 'COALESCE(NULLIF(c.CrBy, 0), c.FilledBy)');

            $claimWhereSql = "WHERE " . implode(" AND ", $claimWhere);

            // Financed / Final column calculation:
            // In VVNR, Verified amount is the final settlement amount.
            // In VNR, Financed amount is the final settlement amount.
            $financeCol = $isVVNR ? "COALESCE(c.VerifyTAmt, 0)" : "COALESCE(c.FinancedTAmt, 0)";

            // Pure database organizational aggregation (no hardcoded strings, no static employee joins)
            $sql = "
                SELECT
                    COALESCE(NULLIF(g.EmpFunction, 0), vfm.org_function_id) AS function_id,
                    fun.function_name,
                    g.EmpVertical AS vertical_id,
                    ver.vertical_name,
                    g.DepartmentId AS department_id,
                    dept.department_name,
                    g.SubDepartmentId AS subdept_id,
                    subdept.sub_department_name AS subdept_name,
                    g.BUId AS bu_id,
                    bu.business_unit_name AS bu_name,
                    g.ZoneId AS zone_id,
                    zn.zone_name,
                    g.RegionId AS region_id,
                    rg.region_name,
                    g.TerrId AS territory_id,
                    tr.territory_name,
                    g.HqId AS hq_id,
                    hq.city_village_name AS hq_name,
                    COUNT(c.ExpId) AS claims_count,
                    COALESCE(SUM(c.FilledTAmt), 0) AS total_filled_amt,
                    COALESCE(SUM(c.VerifyTAmt), 0) AS total_verify_amt,
                    COALESCE(SUM(c.ApprTAmt), 0) AS total_appr_amt,
                    COALESCE(SUM({$financeCol}), 0) AS total_finance_amt
                FROM {$table} c
                LEFT JOIN {$hrimsDb}.hrm_employee_general g ON COALESCE(NULLIF(c.CrBy, 0), c.FilledBy) = g.EmployeeID
                LEFT JOIN (
                    SELECT vertical_id, MIN(org_function_id) AS org_function_id
                    FROM {$hrimsDb}.core_vertical_function_mapping
                    GROUP BY vertical_id
                ) vfm ON (g.EmpFunction IS NULL OR g.EmpFunction = 0) AND g.EmpVertical = vfm.vertical_id
                LEFT JOIN {$hrimsDb}.core_functions fun ON COALESCE(NULLIF(g.EmpFunction, 0), vfm.org_function_id) = fun.id
                LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id
                LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
                LEFT JOIN {$hrimsDb}.core_sub_department_master subdept ON g.SubDepartmentId = subdept.id
                LEFT JOIN {$hrimsDb}.core_business_unit bu ON g.BUId = bu.id
                LEFT JOIN {$hrimsDb}.core_zones zn ON g.ZoneId = zn.id
                LEFT JOIN {$hrimsDb}.core_regions rg ON g.RegionId = rg.id
                LEFT JOIN {$hrimsDb}.core_territory tr ON g.TerrId = tr.id
                LEFT JOIN {$hrimsDb}.core_city_village_by_state hq ON g.HqId = hq.id
                {$claimWhereSql}
                GROUP BY
                    COALESCE(NULLIF(g.EmpFunction, 0), vfm.org_function_id), fun.function_name,
                    g.EmpVertical, ver.vertical_name,
                    g.DepartmentId, dept.department_name,
                    g.SubDepartmentId, subdept.sub_department_name,
                    g.BUId, bu.business_unit_name,
                    g.ZoneId, zn.zone_name,
                    g.RegionId, rg.region_name,
                    g.TerrId, tr.territory_name,
                    g.HqId, hq.city_village_name
            ";

            $rows = [];
            $dbError = null;

            try {
                $rows = DB::connection($expConn)->select($sql, $claimBindings);
            } catch (\Exception $dbEx) {
                $dbError = $dbEx->getMessage();
                Log::error('ExpenseTreeReportController::getTreeData DB query error: ' . $dbError);
                $rows = [];
            }

            // Compute Grand Totals across all returned rows from DB
            $distinctEntities = [
                'functions'   => [],
                'verticals'   => [],
                'departments' => [],
                'subdepts'    => [],
                'bus'         => [],
                'zones'       => [],
                'regions'     => [],
                'territories' => [],
                'hqs'         => [],
            ];

            $grandClaims  = 0;
            $grandFilled  = 0.0;
            $grandVerify  = 0.0;
            $grandAppr    = 0.0;
            $grandFinance = 0.0;

            foreach ($rows as $r) {
                $grandClaims  += (int) $r->claims_count;
                $grandFilled  += (float) $r->total_filled_amt;
                $grandVerify  += (float) $r->total_verify_amt;
                $grandAppr    += (float) $r->total_appr_amt;
                $grandFinance += (float) $r->total_finance_amt;

                if (!empty($r->function_name))   $distinctEntities['functions'][$r->function_id] = true;
                if (!empty($r->vertical_name))   $distinctEntities['verticals'][$r->vertical_id] = true;
                if (!empty($r->department_name)) $distinctEntities['departments'][$r->department_id] = true;
                if (!empty($r->subdept_name))    $distinctEntities['subdepts'][$r->subdept_id] = true;
                if (!empty($r->bu_name))         $distinctEntities['bus'][$r->bu_id] = true;
                if (!empty($r->zone_name))       $distinctEntities['zones'][$r->zone_id] = true;
                if (!empty($r->region_name))     $distinctEntities['regions'][$r->region_id] = true;
                if (!empty($r->territory_name))  $distinctEntities['territories'][$r->territory_id] = true;
                if (!empty($r->hq_name))         $distinctEntities['hqs'][$r->hq_id] = true;
            }

            $summary = [
                'claims_count'      => $grandClaims,
                'total_filled_amt'  => round($grandFilled, 2),
                'total_verify_amt'  => round($grandVerify, 2),
                'total_appr_amt'    => round($grandAppr, 2),
                'total_finance_amt' => round($grandFinance, 2),
                'total_final_amt'   => $isVVNR ? round($grandVerify, 2) : round($grandFinance, 2),
                'is_vvnr'           => $isVVNR,
                'total_functions'   => count($distinctEntities['functions']),
                'total_verticals'   => count($distinctEntities['verticals']),
                'total_departments' => count($distinctEntities['departments']),
                'total_subdepts'    => count($distinctEntities['subdepts']),
                'total_bus'         => count($distinctEntities['bus']),
                'total_zones'       => count($distinctEntities['zones']),
                'total_regions'     => count($distinctEntities['regions']),
                'total_territories' => count($distinctEntities['territories']),
                'total_hqs'         => count($distinctEntities['hqs']),
            ];

            // Build pure organizational hierarchy (all levels and names directly from DB)
            $finalMetric = $isVVNR ? $summary['total_verify_amt'] : $summary['total_finance_amt'];
            $totalForPercent = ($finalMetric > 0)
                ? $finalMetric
                : ($summary['total_appr_amt'] > 0 ? $summary['total_appr_amt'] : $summary['total_filled_amt']);

            $tree = $this->buildPureOrgTreeHierarchy($rows, $totalForPercent, $isVVNR);

            return response()->json([
                'success'      => empty($dbError),
                'db_error'     => $dbError,
                'is_vvnr'      => $isVVNR,
                'grand_totals' => $summary,
                'hierarchy'    => $tree,
                'total_rows'   => count($rows),
            ]);
        } catch (\Exception $e) {
            Log::error('ExpenseTreeReportController::getTreeData exception: ' . $e->getMessage());

            return response()->json([
                'success'      => false,
                'message'      => 'Error retrieving expense tree data: ' . $e->getMessage(),
                'grand_totals' => $this->emptyTotals(),
                'hierarchy'    => [],
                'total_rows'   => 0,
            ], 500);
        }
    }

    /**
     * Build nested tree hierarchy based purely on database organization levels.
     * All entity names are sourced directly from the database without static dummy fallbacks.
     */
    private function buildPureOrgTreeHierarchy(array $rows, float $grandTotalMetric, bool $isVVNR = false): array
    {
        $rootMap = [];

        foreach ($rows as $r) {
            // Function is strictly the top-level entity in the hierarchy.
            // Under no circumstances should a vertical or department leak to the root/function level.
            $functionName = !empty($r->function_name) && trim($r->function_name) !== ''
                ? trim($r->function_name)
                : (!empty($r->function_id) ? 'Function ' . $r->function_id : 'Corporate / General');

            $functionId = !empty($r->function_id) ? (int) $r->function_id : 0;

            $levelsInRow = [
                ['type' => 'function', 'id' => $functionId, 'name' => $functionName],
            ];

            if (!empty($r->vertical_name) && trim($r->vertical_name) !== '') {
                $levelsInRow[] = ['type' => 'vertical', 'id' => (int) $r->vertical_id, 'name' => trim($r->vertical_name)];
            }
            if (!empty($r->department_name) && trim($r->department_name) !== '') {
                $levelsInRow[] = ['type' => 'department', 'id' => (int) $r->department_id, 'name' => trim($r->department_name)];
            }
            if (!empty($r->subdept_name) && trim($r->subdept_name) !== '') {
                $levelsInRow[] = ['type' => 'subdept', 'id' => (int) $r->subdept_id, 'name' => trim($r->subdept_name)];
            }
            if (!empty($r->bu_name) && trim($r->bu_name) !== '') {
                $levelsInRow[] = ['type' => 'bu', 'id' => (int) $r->bu_id, 'name' => trim($r->bu_name)];
            }
            if (!empty($r->zone_name) && trim($r->zone_name) !== '') {
                $levelsInRow[] = ['type' => 'zone', 'id' => (int) $r->zone_id, 'name' => trim($r->zone_name)];
            }
            if (!empty($r->region_name) && trim($r->region_name) !== '') {
                $levelsInRow[] = ['type' => 'region', 'id' => (int) $r->region_id, 'name' => trim($r->region_name)];
            }
            if (!empty($r->territory_name) && trim($r->territory_name) !== '') {
                $levelsInRow[] = ['type' => 'territory', 'id' => (int) $r->territory_id, 'name' => trim($r->territory_name)];
            }
            if (!empty($r->hq_name) && trim($r->hq_name) !== '') {
                $levelsInRow[] = ['type' => 'hq', 'id' => (int) $r->hq_id, 'name' => trim($r->hq_name)];
            }

            if (empty($levelsInRow)) {
                continue;
            }

            $currNode = &$rootMap;
            $pathSoFar = '';

            foreach ($levelsInRow as $idx => $lvl) {
                $key = $lvl['type'] . '__' . $lvl['id'] . '__' . $lvl['name'];
                $pathSoFar .= '/' . $key;

                if (!isset($currNode[$key])) {
                    $currNode[$key] = [
                        'key'               => $pathSoFar,
                        'level_type'        => $lvl['type'],
                        'level_index'       => $idx + 1,
                        'id'                => $lvl['id'],
                        'name'              => $lvl['name'],
                        'claims_count'      => 0,
                        'total_filled_amt'  => 0.0,
                        'total_verify_amt'  => 0.0,
                        'total_appr_amt'    => 0.0,
                        'total_finance_amt' => 0.0,
                        'total_final_amt'   => 0.0,
                        'children'          => [],
                    ];
                }

                $currNode[$key]['claims_count']      += (int) $r->claims_count;
                $currNode[$key]['total_filled_amt']  += (float) $r->total_filled_amt;
                $currNode[$key]['total_verify_amt']  += (float) $r->total_verify_amt;
                $currNode[$key]['total_appr_amt']    += (float) $r->total_appr_amt;
                $currNode[$key]['total_finance_amt'] += (float) $r->total_finance_amt;

                $currNode = &$currNode[$key]['children'];
            }
        }

        return $this->formatTreeNodes($rootMap, $grandTotalMetric, $isVVNR);
    }

    /**
     * Recursively format nodes and calculate parent share percentages.
     */
    private function formatTreeNodes(array &$nodes, float $parentTotal, bool $isVVNR = false): array
    {
        $result = [];

        foreach ($nodes as &$node) {
            $node['total_filled_amt']  = round($node['total_filled_amt'], 2);
            $node['total_verify_amt']  = round($node['total_verify_amt'], 2);
            $node['total_appr_amt']    = round($node['total_appr_amt'], 2);
            $node['total_finance_amt'] = round($node['total_finance_amt'], 2);

            // In VVNR, Verified is the final amount; in VNR, Financed is the final amount
            $finalAmt = $isVVNR ? $node['total_verify_amt'] : $node['total_finance_amt'];
            $node['total_final_amt'] = $finalAmt;

            $metric = ($finalAmt > 0)
                ? $finalAmt
                : ($node['total_appr_amt'] > 0 ? $node['total_appr_amt'] : $node['total_filled_amt']);

            $node['percent_of_parent'] = ($parentTotal > 0)
                ? round(($metric / $parentTotal) * 100, 1)
                : 0;

            if (!empty($node['children'])) {
                $childParentTotal = ($finalAmt > 0)
                    ? $finalAmt
                    : ($node['total_appr_amt'] > 0 ? $node['total_appr_amt'] : $node['total_filled_amt']);

                $node['children'] = $this->formatTreeNodes($node['children'], $childParentTotal, $isVVNR);
                $node['is_leaf'] = false;
            } else {
                $node['children'] = [];
                $node['is_leaf'] = true;
            }

            $result[] = $node;
        }

        // Sort siblings: highest final expenditure first, then approved, then by name
        usort($result, function ($a, $b) use ($isVVNR) {
            $aFinal = $isVVNR ? $a['total_verify_amt'] : $a['total_finance_amt'];
            $bFinal = $isVVNR ? $b['total_verify_amt'] : $b['total_finance_amt'];
            if ($bFinal != $aFinal) {
                return ($bFinal > $aFinal) ? 1 : -1;
            }
            if ($b['total_appr_amt'] != $a['total_appr_amt']) {
                return ($b['total_appr_amt'] > $a['total_appr_amt']) ? 1 : -1;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Export tree summary report to CSV segregated month-wise directly from database.
     * Supports both month-by-month row breakdown and 12-month pivot matrix layout.
     */
    public function export(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $companyId = (int) ($request->input('company_id') ?: session('company_id', 1));
        $isVVNR    = ($companyId == 11);
        $expConn   = DatabaseSwitcher::getExpenseConnection($companyId);
        $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName($companyId);
        $yearId    = (int) ($request->input('year_id') ?: \App\Support\CurrentYear::id());
        $month     = $request->input('month');
        $format    = $request->input('format', 'excel'); // 'excel' (default) or 'csv'

        $table = "y{$yearId}_expenseclaims";

        $whereClauses = [
            "c.ClaimStatus NOT IN ('Deactivate', 'Draft')",
            "(c.FilledBy > 0 OR c.FilledOkay = 1 OR c.FilledTAmt > 0 OR c.CrBy > 0)"
        ];
        $bindings = [];

        if (!empty($month) && is_numeric($month)) {
            $whereClauses[] = "c.ClaimMonth = ?";
            $bindings[] = (int) $month;
        }

        // Additional Organogram & Policy Filters from drawer
        $filters = OrganogramFilterHelper::extractFilters($request);
        OrganogramFilterHelper::appendRawSqlFilters($whereClauses, $bindings, $filters, 'g', 'vfm', $hrimsDb, 'COALESCE(NULLIF(c.CrBy, 0), c.FilledBy)');

        $where = "WHERE " . implode(" AND ", $whereClauses);

        // Financial Year Month definitions (April to March)
        $fyMonths = [
            4  => ['short' => 'Apr', 'full' => 'April'],
            5  => ['short' => 'May', 'full' => 'May'],
            6  => ['short' => 'Jun', 'full' => 'June'],
            7  => ['short' => 'Jul', 'full' => 'July'],
            8  => ['short' => 'Aug', 'full' => 'August'],
            9  => ['short' => 'Sep', 'full' => 'September'],
            10 => ['short' => 'Oct', 'full' => 'October'],
            11 => ['short' => 'Nov', 'full' => 'November'],
            12 => ['short' => 'Dec', 'full' => 'December'],
            1  => ['short' => 'Jan', 'full' => 'January'],
            2  => ['short' => 'Feb', 'full' => 'February'],
            3  => ['short' => 'Mar', 'full' => 'March'],
        ];

        $activeMonths = (!empty($month) && isset($fyMonths[(int) $month]))
            ? [(int) $month => $fyMonths[(int) $month]]
            : $fyMonths;

        // Build SQL Select for each month:
        // In VVNR, the workflow is Claimed -> Approved -> Verified (Final) [4 stages]
        // In VNR, the workflow is Claimed -> Verified -> Approved -> Financed (Final) [5 stages]
        $subColsCount = $isVVNR ? 4 : 5;
        $stageLabels  = $isVVNR
            ? ['Claims', 'Claimed (Rs)', 'Approved (Rs)', 'Verified (Rs)']
            : ['Claims', 'Claimed (Rs)', 'Verified (Rs)', 'Approved (Rs)', 'Financed (Rs)'];
        $totalLabels  = $isVVNR
            ? ['Total Claims', 'Total Claimed (Rs)', 'Total Approved (Rs)', 'Total Verified (Rs)']
            : ['Total Claims', 'Total Claimed (Rs)', 'Total Verified (Rs)', 'Total Approved (Rs)', 'Total Financed (Rs)'];

        $stageSelects = [];
        if ($isVVNR) {
            foreach ($activeMonths as $mNum => $mInfo) {
                $code = strtolower($mInfo['short']);
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN 1 ELSE 0 END), 0) AS {$code}_claims";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.FilledTAmt ELSE 0 END), 0) AS {$code}_claimed";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.ApprTAmt ELSE 0 END), 0)   AS {$code}_approved";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.VerifyTAmt ELSE 0 END), 0) AS {$code}_verified";
            }
            $stageSelects[] = "COUNT(c.ExpId) AS total_claims";
            $stageSelects[] = "COALESCE(SUM(c.FilledTAmt), 0) AS total_claimed";
            $stageSelects[] = "COALESCE(SUM(c.ApprTAmt), 0)   AS total_approved";
            $stageSelects[] = "COALESCE(SUM(c.VerifyTAmt), 0) AS total_verified";
            $orderFinalCol = "total_verified DESC";
        } else {
            foreach ($activeMonths as $mNum => $mInfo) {
                $code = strtolower($mInfo['short']);
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN 1 ELSE 0 END), 0) AS {$code}_claims";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.FilledTAmt ELSE 0 END), 0) AS {$code}_claimed";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.VerifyTAmt ELSE 0 END), 0) AS {$code}_verified";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.ApprTAmt ELSE 0 END), 0)   AS {$code}_approved";
                $stageSelects[] = "COALESCE(SUM(CASE WHEN c.ClaimMonth = {$mNum} THEN c.FinancedTAmt ELSE 0 END), 0) AS {$code}_financed";
            }
            $stageSelects[] = "COUNT(c.ExpId) AS total_claims";
            $stageSelects[] = "COALESCE(SUM(c.FilledTAmt), 0) AS total_claimed";
            $stageSelects[] = "COALESCE(SUM(c.VerifyTAmt), 0) AS total_verified";
            $stageSelects[] = "COALESCE(SUM(c.ApprTAmt), 0)   AS total_approved";
            $stageSelects[] = "COALESCE(SUM(c.FinancedTAmt), 0) AS total_financed";
            $orderFinalCol = "total_financed DESC";
        }

        $sql = "
            SELECT
                fun.function_name,
                ver.vertical_name,
                dept.department_name,
                subdept.sub_department_name AS subdept_name,
                bu.business_unit_name AS bu_name,
                zn.zone_name,
                rg.region_name,
                tr.territory_name,
                hq.city_village_name AS hq_name,
                " . implode(",\n                ", $stageSelects) . "
            FROM {$table} c
            LEFT JOIN {$hrimsDb}.hrm_employee_general g ON COALESCE(NULLIF(c.CrBy, 0), c.FilledBy) = g.EmployeeID
            LEFT JOIN (
                SELECT vertical_id, MIN(org_function_id) AS org_function_id
                FROM {$hrimsDb}.core_vertical_function_mapping
                GROUP BY vertical_id
            ) vfm ON (g.EmpFunction IS NULL OR g.EmpFunction = 0) AND g.EmpVertical = vfm.vertical_id
            LEFT JOIN {$hrimsDb}.core_functions fun ON COALESCE(NULLIF(g.EmpFunction, 0), vfm.org_function_id) = fun.id
            LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_sub_department_master subdept ON g.SubDepartmentId = subdept.id
            LEFT JOIN {$hrimsDb}.core_business_unit bu ON g.BUId = bu.id
            LEFT JOIN {$hrimsDb}.core_zones zn ON g.ZoneId = zn.id
            LEFT JOIN {$hrimsDb}.core_regions rg ON g.RegionId = rg.id
            LEFT JOIN {$hrimsDb}.core_territory tr ON g.TerrId = tr.id
            LEFT JOIN {$hrimsDb}.core_city_village_by_state hq ON g.HqId = hq.id
            {$where}
            GROUP BY
                COALESCE(NULLIF(g.EmpFunction, 0), vfm.org_function_id), fun.function_name,
                g.EmpVertical, ver.vertical_name,
                g.DepartmentId, dept.department_name,
                g.SubDepartmentId, subdept.sub_department_name,
                g.BUId, bu.business_unit_name,
                g.ZoneId, zn.zone_name,
                g.RegionId, rg.region_name,
                g.TerrId, tr.territory_name,
                g.HqId, hq.city_village_name
            ORDER BY fun.function_name, ver.vertical_name, dept.department_name, {$orderFinalCol}
        ";

        $data = DB::connection($expConn)->select($sql, $bindings);

        // Format 1: CSV Export with 2-Tier Headers
        if ($format === 'csv') {
            $compLabel = $isVVNR ? 'VVNR' : 'VNR';
            $filename = "expense_tree_report_{$compLabel}_monthly_FY{$yearId}_" . date('Y_m_d_His') . '.csv';

            return response()->streamDownload(function () use ($data, $activeMonths, $isVVNR, $subColsCount, $stageLabels, $totalLabels) {
                $output = fopen('php://output', 'w');
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

                // Header Row 1: Entity Labels + Month Names (repeated across sub-columns)
                $row1 = ['#', 'Function', 'Vertical', 'Department', 'Sub Department', 'Business Unit (BU)', 'Zone', 'Region', 'Territory', 'Loc / HQ'];
                foreach ($activeMonths as $mInfo) {
                    $monthLabel = strtoupper($mInfo['full']);
                    for ($s = 0; $s < $subColsCount; $s++) {
                        $row1[] = $monthLabel;
                    }
                }
                if (count($activeMonths) > 1) {
                    for ($s = 0; $s < $subColsCount; $s++) {
                        $row1[] = 'FULL YEAR TOTAL';
                    }
                }
                fputcsv($output, $row1);

                // Header Row 2: Sub-headers for stages
                $row2 = ['#', 'Function', 'Vertical', 'Department', 'Sub Department', 'Business Unit (BU)', 'Zone', 'Region', 'Territory', 'Loc / HQ'];
                foreach ($activeMonths as $mInfo) {
                    foreach ($stageLabels as $lbl) {
                        $row2[] = $lbl;
                    }
                }
                if (count($activeMonths) > 1) {
                    foreach ($totalLabels as $lbl) {
                        $row2[] = $lbl;
                    }
                }
                fputcsv($output, $row2);

                // Data Rows
                $i = 1;
                foreach ($data as $r) {
                    $row = [
                        $i++,
                        $r->function_name ?? '-',
                        $r->vertical_name ?? '-',
                        $r->department_name ?? '-',
                        $r->subdept_name ?? '-',
                        $r->bu_name ?? '-',
                        $r->zone_name ?? '-',
                        $r->region_name ?? '-',
                        $r->territory_name ?? '-',
                        $r->hq_name ?? '-',
                    ];

                    foreach ($activeMonths as $mInfo) {
                        $code = strtolower($mInfo['short']);
                        $row[] = (int) ($r->{$code . '_claims'} ?? 0);
                        $row[] = (float) ($r->{$code . '_claimed'} ?? 0);
                        if ($isVVNR) {
                            $row[] = (float) ($r->{$code . '_approved'} ?? 0);
                            $row[] = (float) ($r->{$code . '_verified'} ?? 0);
                        } else {
                            $row[] = (float) ($r->{$code . '_verified'} ?? 0);
                            $row[] = (float) ($r->{$code . '_approved'} ?? 0);
                            $row[] = (float) ($r->{$code . '_financed'} ?? 0);
                        }
                    }

                    if (count($activeMonths) > 1) {
                        $row[] = (int) ($r->total_claims ?? 0);
                        $row[] = (float) ($r->total_claimed ?? 0);
                        if ($isVVNR) {
                            $row[] = (float) ($r->total_approved ?? 0);
                            $row[] = (float) ($r->total_verified ?? 0);
                        } else {
                            $row[] = (float) ($r->total_verified ?? 0);
                            $row[] = (float) ($r->total_approved ?? 0);
                            $row[] = (float) ($r->total_financed ?? 0);
                        }
                    }

                    fputcsv($output, $row);
                }

                fputcsv($output, []); fputcsv($output, \App\Support\ReportStamp::csvRow()); // STAMP-01
                fclose($output);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Cache-Control' => 'max-age=0',
            ]);
        }

        // Format 2 (Default): Fully Styled Excel (.xlsx) with 2-Tier Month Headers & Color Highlights
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Expense Report');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

        // Fixed Entity Columns (Cols 1 to 10)
        $entityHeaders = [
            1  => '#',
            2  => 'Function',
            3  => 'Vertical',
            4  => 'Department',
            5  => 'Sub Department',
            6  => 'Business Unit (BU)',
            7  => 'Zone',
            8  => 'Region',
            9  => 'Territory',
            10 => 'Loc / HQ',
        ];

        foreach ($entityHeaders as $colIdx => $title) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->mergeCells("{$colLetter}1:{$colLetter}2");
            $sheet->setCellValue("{$colLetter}1", $title);
        }

        // Slate header for entity columns
        $sheet->getStyle('A1:J2')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800
            ],
        ]);

        $currentCol = 11;
        $monthColors = [
            0 => ['hdr' => '1E3A8A', 'sub' => 'DBEAFE', 'subText' => '1E3A8A', 'finHdr' => 'DCFCE7', 'finText' => '166534'], // Deep Blue
            1 => ['hdr' => '0F766E', 'sub' => 'CCFBF1', 'subText' => '0F766E', 'finHdr' => 'DCFCE7', 'finText' => '166534'], // Deep Teal
        ];

        $monthIndex = 0;
        $monthStageColIndices = [];

        foreach ($activeMonths as $mNum => $mInfo) {
            $startColLetter = Coordinate::stringFromColumnIndex($currentCol);
            $endColLetter   = Coordinate::stringFromColumnIndex($currentCol + $subColsCount - 1);

            // Month Name merged across sub-columns in Row 1
            $sheet->mergeCells("{$startColLetter}1:{$endColLetter}1");
            $sheet->setCellValue("{$startColLetter}1", strtoupper($mInfo['full']));

            $palette = $monthColors[$monthIndex % 2];
            $monthIndex++;

            $sheet->getStyle("{$startColLetter}1:{$endColLetter}1")->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size'  => 11,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $palette['hdr']],
                ],
            ]);

            // Sub-headers in Row 2
            foreach ($stageLabels as $sIdx => $sLabel) {
                $cLetter = Coordinate::stringFromColumnIndex($currentCol + $sIdx);
                $sheet->setCellValue("{$cLetter}2", $sLabel);
                $isFin = ($sIdx === ($subColsCount - 1)); // Final amount column (Verified for VVNR, Financed for VNR)

                $sheet->getStyle("{$cLetter}2")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => $isFin ? $palette['finText'] : $palette['subText']],
                        'size'  => 9,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $isFin ? $palette['finHdr'] : $palette['sub']],
                    ],
                ]);
            }

            $monthStageColIndices[] = [
                'short' => strtolower($mInfo['short']),
                'start' => $currentCol,
            ];

            $currentCol += $subColsCount;
        }

        // Full Year Total (merged across sub-columns)
        $totalStartLetter = '';
        $totalEndLetter   = '';
        if (count($activeMonths) > 1) {
            $totalStartLetter = Coordinate::stringFromColumnIndex($currentCol);
            $totalEndLetter   = Coordinate::stringFromColumnIndex($currentCol + $subColsCount - 1);

            $sheet->mergeCells("{$totalStartLetter}1:{$totalEndLetter}1");
            $sheet->setCellValue("{$totalStartLetter}1", 'FULL YEAR TOTAL');

            $sheet->getStyle("{$totalStartLetter}1:{$totalEndLetter}1")->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size'  => 11,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '064E3B'], // Deep Forest Green
                ],
            ]);

            foreach ($totalLabels as $tIdx => $tLabel) {
                $cLetter = Coordinate::stringFromColumnIndex($currentCol + $tIdx);
                $sheet->setCellValue("{$cLetter}2", $tLabel);
                $isFinTotal = ($tIdx === ($subColsCount - 1));

                $sheet->getStyle("{$cLetter}2")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => '064E3B'],
                        'size'  => 9,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $isFinTotal ? '86EFAC' : 'BBF7D0'],
                    ],
                ]);
            }
            $currentCol += $subColsCount;
        }

        $maxColLetter = Coordinate::stringFromColumnIndex($currentCol - 1);

        // Populate Data Rows
        $rowNum = 3;
        $i = 1;
        foreach ($data as $r) {
            $sheet->setCellValue("A{$rowNum}", $i++);
            $sheet->setCellValue("B{$rowNum}", $r->function_name ?? '-');
            $sheet->setCellValue("C{$rowNum}", $r->vertical_name ?? '-');
            $sheet->setCellValue("D{$rowNum}", $r->department_name ?? '-');
            $sheet->setCellValue("E{$rowNum}", $r->subdept_name ?? '-');
            $sheet->setCellValue("F{$rowNum}", $r->bu_name ?? '-');
            $sheet->setCellValue("G{$rowNum}", $r->zone_name ?? '-');
            $sheet->setCellValue("H{$rowNum}", $r->region_name ?? '-');
            $sheet->setCellValue("I{$rowNum}", $r->territory_name ?? '-');
            $sheet->setCellValue("J{$rowNum}", $r->hq_name ?? '-');

            $colIdx = 11;
            foreach ($activeMonths as $mInfo) {
                $code = strtolower($mInfo['short']);

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (int) ($r->{$code . '_claims'} ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->{$code . '_claimed'} ?? 0));
                if ($isVVNR) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->{$code . '_approved'} ?? 0));
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->{$code . '_verified'} ?? 0));
                } else {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->{$code . '_verified'} ?? 0));
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->{$code . '_approved'} ?? 0));
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->{$code . '_financed'} ?? 0));
                }
            }

            if (count($activeMonths) > 1) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (int) ($r->total_claims ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->total_claimed ?? 0));
                if ($isVVNR) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->total_approved ?? 0));
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->total_verified ?? 0));
                } else {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->total_verified ?? 0));
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->total_approved ?? 0));
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $rowNum, (float) ($r->total_financed ?? 0));
                }
            }

            $rowNum++;
        }

        $lastDataRow = max(3, $rowNum - 1);

        // Apply Borders across entire table
        $sheet->getStyle("A1:{$maxColLetter}{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        // Format Numbers and Apply Soft Highlights on Data Columns
        $sheet->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($monthStageColIndices as $m) {
            $cCol   = Coordinate::stringFromColumnIndex($m['start']);
            $fCol   = Coordinate::stringFromColumnIndex($m['start'] + 1);
            $finCol = Coordinate::stringFromColumnIndex($m['start'] + $subColsCount - 1);

            // Claims count format
            $sheet->getStyle("{$cCol}3:{$cCol}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
            // Amounts format
            $sheet->getStyle("{$fCol}3:{$finCol}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Soft highlight on Final column
            $sheet->getStyle("{$finCol}3:{$finCol}{$lastDataRow}")->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0FDF4'],
                ],
            ]);
        }

        // Full Year Total Columns highlight and format
        if (count($activeMonths) > 1 && $totalStartLetter && $totalEndLetter) {
            $sheet->getStyle("{$totalStartLetter}3:{$totalEndLetter}{$lastDataRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'ECFDF5'],
                ],
            ]);
            $sheet->getStyle("{$totalStartLetter}3:{$totalStartLetter}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
            $tFldLetter = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($totalStartLetter) + 1);
            $sheet->getStyle("{$tFldLetter}3:{$totalEndLetter}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Double highlight on Total Final column
            $sheet->getStyle("{$totalEndLetter}3:{$totalEndLetter}{$lastDataRow}")->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DCFCE7'],
                ],
            ]);
        }

        // Freeze panes at E3 so Function and Department stay pinned when scrolling horizontally
        $sheet->freezePane('E3');

        // Set Header Row Heights
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(24);

        // Pre-set Column Widths
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(14);

        for ($c = 11; $c < $currentCol; $c++) {
            $cLet = Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($cLet)->setWidth(15);
        }

        \App\Support\ReportStamp::workbook($spreadsheet); // STAMP-01

        $writer = new Xlsx($spreadsheet);
        $compLabel = $isVVNR ? 'VVNR' : 'VNR';
        $filename = "expense_tree_report_{$compLabel}_monthly_FY{$yearId}_" . date('Y_m_d_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function emptyTotals(): array
    {
        return [
            'claims_count'      => 0,
            'total_filled_amt'  => 0,
            'total_verify_amt'  => 0,
            'total_appr_amt'    => 0,
            'total_finance_amt' => 0,
            'total_functions'   => 0,
            'total_verticals'   => 0,
            'total_departments' => 0,
            'total_subdepts'    => 0,
            'total_bus'         => 0,
            'total_zones'       => 0,
            'total_regions'     => 0,
            'total_territories' => 0,
            'total_hqs'         => 0,
        ];
    }

    /**
     * Parse input into a clean array of positive integers.
     */
    protected function parseIdArray($val): array
    {
        if (empty($val)) {
            return [];
        }
        if (is_string($val)) {
            $val = explode(',', $val);
        }
        return array_values(array_filter(array_map('intval', (array) $val), function ($id) {
            return $id > 0;
        }));
    }
}
