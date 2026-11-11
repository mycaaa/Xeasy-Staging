<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExpenseTypeReportController extends Controller
{
    public function index()
    {
        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

        $departments = DB::connection('hrims')->table('core_departments')
            ->where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name']);

        $verticals = DB::connection('hrims')->table('core_verticals')
            ->where('is_active', 1)->orderBy('vertical_name')->get(['id', 'vertical_name']);

        $businessUnits = DB::connection('hrims')->table('core_business_unit')
            ->where('is_active', 1)->orderBy('business_unit_name')->get(['id', 'business_unit_name']);

        $years = DB::connection($expConn)->table('financialyear')
            ->orderByDesc('YearId')->get(['YearId', 'Year']);

        $claimTypes = DB::connection($expConn)->table('claimtype')
            ->whereIn('ClaimStatus', ['A', 'B','D'])->orderBy('ClaimName')->get(['ClaimId', 'ClaimName']);

        $companyId = session('company_id', 1);
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        return view('admin.expense-type-report', array_merge($organogramOptions, compact('departments', 'verticals', 'businessUnits', 'years', 'claimTypes')));
    }

    private function extractOrganogramFilters(Request $request): array
    {
        $filters = \App\Helpers\OrganogramFilterHelper::extractFilters($request);
        if (empty($filters['department_ids']) && $request->filled('department_id')) {
            $filters['department_ids'] = [$request->input('department_id')];
        }
        if (empty($filters['vertical_ids']) && $request->filled('vertical_id')) {
            $filters['vertical_ids'] = [$request->input('vertical_id')];
        }
        if (empty($filters['bu_ids']) && $request->filled('bu_id')) {
            $filters['bu_ids'] = [$request->input('bu_id')];
        }
        if (empty($filters['function_ids']) && $request->filled('function_id')) {
            $filters['function_ids'] = [$request->input('function_id')];
        }
        return $filters;
    }

    public function getData(Request $request)
    {
        $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
        $departmentId = $request->input('department_id');
        $verticalId = $request->input('vertical_id');
        $buId = $request->input('bu_id');
        $functionId = $request->input('function_id');
        $claimTypeId = $request->input('claim_type_id');
        $month = $request->input('month');
        $employeeId = $request->input('employee_id');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 100);

        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $table = "y{$yearId}_expenseclaims";

        $baseQuery = "
            FROM {$table} c
            LEFT JOIN claimtype t ON c.ClaimId = t.ClaimId
            INNER JOIN {$hrimsDb}.hrm_employee e ON c.CrBy = e.EmployeeID
            LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
            LEFT JOIN {$hrimsDb}.core_functions fun ON g.EmpFunction = fun.id
            LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_sub_department_master subdept ON g.SubDepartmentId = subdept.id
            LEFT JOIN {$hrimsDb}.core_section sec ON g.EmpSection = sec.id
            LEFT JOIN {$hrimsDb}.core_designation desig ON g.DesigId = desig.id
            LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id
            LEFT JOIN {$hrimsDb}.core_states st ON g.CostCenter = st.id
            LEFT JOIN {$hrimsDb}.core_business_unit Bu ON g.BUId = Bu.id
            LEFT JOIN {$hrimsDb}.core_zones Zn ON g.ZoneId = Zn.id
            LEFT JOIN {$hrimsDb}.core_regions Rg ON g.RegionId = Rg.id
            LEFT JOIN {$hrimsDb}.core_territory Tr ON g.TerrId = Tr.id
            WHERE c.ClaimStatus NOT IN ('Deactivate', 'Draft')
            AND c.FilledBy > 0
            AND c.ClaimYearId = ?
        ";

        $bindings = [$yearId];

        $organogramFilters = $this->extractOrganogramFilters($request);
        $extraWheres = [];
        \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
        if (!empty($extraWheres)) {
            $baseQuery .= " AND " . implode(" AND ", $extraWheres);
        }
        if ($claimTypeId) {
            $baseQuery .= " AND c.ClaimId = ?";
            $bindings[] = $claimTypeId;
        }
        if ($month) {
            $baseQuery .= " AND c.ClaimMonth = ?";
            $bindings[] = $month;
        }
        if ($employeeId) {
            $baseQuery .= " AND c.CrBy = ?";
            $bindings[] = $employeeId;
        }

        $orderBy = " ORDER BY e.EmpCode_New, c.ClaimMonth DESC, c.BillDate DESC";

        // Count total
        $countSql = "SELECT COUNT(*) as total {$baseQuery}";
        $total = DB::connection($expConn)->selectOne($countSql, $bindings)->total ?? 0;

        // Paginated data
        $offset = ($page - 1) * $perPage;
        $dataSql = "SELECT 
                e.EmpCode_New AS emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) AS employee_name,
                e.EmpStatus AS status,
                fun.function_name,
                ver.vertical_name,
                dept.department_name,
                subdept.sub_department_name,
                sec.section_name,
                desig.designation_name,
                gr.grade_name,
                st.state_name,
                Bu.business_unit_name,
                Zn.zone_name,
                Rg.region_name,
                Tr.territory_name,
                c.ClaimMonth AS month,
                t.ClaimCode AS claim_code,
                t.ClaimName AS expense_type,
                c.CrDate AS filled_date,
                c.BillDate AS bill_date,
                c.FilledTAmt AS employee_amt,
                c.VerifyDate AS verify_date,
                c.VerifyTAmt AS verify_amt,
                c.ApprDate AS appr_date,
                c.ApprTAmt AS approver_amt,
                c.FinancedDate AS financed_date,
                c.FinancedTAmt AS finance_amt,
                c.ClaimStatus AS claim_status,
                c.ExpId
            {$baseQuery} {$orderBy} LIMIT {$perPage} OFFSET {$offset}";

        $data = DB::connection($expConn)->select($dataSql, $bindings);

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage),
        ]);
    }

    public function exportGrouped(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900);
        set_time_limit(900);

        $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
        $departmentId = $request->input('department_id');
        $verticalId = $request->input('vertical_id');
        $buId = $request->input('bu_id');
        $claimTypeId = $request->input('claim_type_id');
        $month = $request->input('month');

        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $table = "y{$yearId}_expenseclaims";

        $where = "WHERE c.ClaimStatus NOT IN ('Deactivate', 'Draft') AND c.FilledBy > 0 AND c.ClaimYearId = ?";
        $bindings = [$yearId];

        $organogramFilters = $this->extractOrganogramFilters($request);
        $extraWheres = [];
        \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
        if (!empty($extraWheres)) {
            $where .= " AND " . implode(" AND ", $extraWheres);
        }
        if ($claimTypeId) {
            $where .= " AND c.ClaimId = ?";
            $bindings[] = $claimTypeId;
        }
        if ($month) {
            $where .= " AND c.ClaimMonth = ?";
            $bindings[] = $month;
        }

        $filename = "expense_grouped_by_claim_FY{$yearId}_" . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($expConn, $table, $hrimsDb, $where, $bindings) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            $headers = [
                'S.No', 'Emp Code', 'Name', 'Status', 'Function', 'Vertical', 'Department',
                'Designation', 'Grade', 'BU', 'Zone', 'Region', 'Territory',
                'Month', 'Claim Type',
                'Total Employee Amt', 'Total Verify Amt', 'Total Approver Amt', 'Total Finance Amt', 'Claim Count'
            ];
            fputcsv($output, $headers);

            $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            $query = "
                SELECT
                    e.EmpCode_New AS emp_code,
                    CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) AS employee_name,
                    e.EmpStatus AS status,
                    IFNULL(fun.function_name, '') AS function_name,
                    IFNULL(ver.vertical_name, '') AS vertical_name,
                    IFNULL(dept.department_name, '') AS department_name,
                    IFNULL(desig.designation_name, '') AS designation_name,
                    IFNULL(gr.grade_name, '') AS grade_name,
                    IFNULL(Bu.business_unit_name, '') AS business_unit_name,
                    IFNULL(Zn.zone_name, '') AS zone_name,
                    IFNULL(Rg.region_name, '') AS region_name,
                    IFNULL(Tr.territory_name, '') AS territory_name,
                    c.ClaimMonth AS month,
                    IFNULL(t.ClaimName, '') AS claim_name,
                    SUM(c.FilledTAmt) AS total_employee_amt,
                    SUM(c.VerifyTAmt) AS total_verify_amt,
                    SUM(c.ApprTAmt) AS total_approver_amt,
                    SUM(c.FinancedTAmt) AS total_finance_amt,
                    COUNT(*) AS claim_count
                FROM {$table} c
                LEFT JOIN claimtype t ON c.ClaimId = t.ClaimId
                INNER JOIN {$hrimsDb}.hrm_employee e ON c.CrBy = e.EmployeeID
                LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                LEFT JOIN {$hrimsDb}.core_functions fun ON g.EmpFunction = fun.id
                LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id
                LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
                LEFT JOIN {$hrimsDb}.core_designation desig ON g.DesigId = desig.id
                LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id
                LEFT JOIN {$hrimsDb}.core_business_unit Bu ON g.BUId = Bu.id
                LEFT JOIN {$hrimsDb}.core_zones Zn ON g.ZoneId = Zn.id
                LEFT JOIN {$hrimsDb}.core_regions Rg ON g.RegionId = Rg.id
                LEFT JOIN {$hrimsDb}.core_territory Tr ON g.TerrId = Tr.id
                {$where}
                GROUP BY e.EmpCode_New, e.Fname, e.Sname, e.Lname, e.EmpStatus,
                         fun.function_name, ver.vertical_name, dept.department_name,
                         desig.designation_name, gr.grade_name, Bu.business_unit_name, Zn.zone_name,
                         Rg.region_name, Tr.territory_name, c.ClaimMonth, t.ClaimName
                ORDER BY e.EmpCode_New, c.ClaimMonth DESC, t.ClaimName
            ";

            $data = DB::connection($expConn)->select($query, $bindings);

            $rowNum = 1;
            foreach ($data as $row) {
                fputcsv($output, [
                    $rowNum,
                    $row->emp_code ?? '',
                    $row->employee_name ?? '',
                    $row->status ?? '',
                    $row->function_name ?? '',
                    $row->vertical_name ?? '',
                    $row->department_name ?? '',
                    $row->designation_name ?? '',
                    $row->grade_name ?? '',
                    $row->business_unit_name ?? '',
                    $row->zone_name ?? '',
                    $row->region_name ?? '',
                    $row->territory_name ?? '',
                    $months[(int)($row->month ?? 0)] ?? '',
                    $row->claim_name ?? '',
                    (float)($row->total_employee_amt ?? 0),
                    (float)($row->total_verify_amt ?? 0),
                    (float)($row->total_approver_amt ?? 0),
                    (float)($row->total_finance_amt ?? 0),
                    (int)($row->claim_count ?? 0),
                ]);
                $rowNum++;
            }

            fputcsv($output, []); fputcsv($output, \App\Support\ReportStamp::csvRow()); // STAMP-01
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportDetailedRecords(Request $request)
    {
        // Increase limits for large exports
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
        $departmentId = $request->input('department_id');
        $verticalId = $request->input('vertical_id');
        $buId = $request->input('bu_id');
        $claimTypeId = $request->input('claim_type_id');
        $month = $request->input('month');

        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $table = "y{$yearId}_expenseclaims";

        $query = "
            SELECT
                ec.ExpId,
                e.EmpCode_New AS emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) AS employee_name,
                ct.ClaimName AS expense_type,
                ec.ClaimMonth AS month,
                ec.FilledTAmt AS claimed_amount,
                ec.VerifyTAmt AS verified_amount,
                ec.ApprTAmt AS approved_amount,
                ec.FinancedTAmt AS financed_amount,
                ver.vertical_name,
                bu.business_unit_name,
                rg.region_name,
                dept.department_name
            FROM {$table} ec
            INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
            INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
            INNER JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_business_unit bu ON g.BUId = bu.id
            LEFT JOIN {$hrimsDb}.core_regions rg ON g.RegionId = rg.id
            INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
            WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft')
            AND ec.FilledBy > 0
            AND ec.ClaimYearId = ?
        ";

        $bindings = [$yearId];

        $organogramFilters = $this->extractOrganogramFilters($request);
        $extraWheres = [];
        \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
        if (!empty($extraWheres)) {
            $query .= " AND " . implode(" AND ", $extraWheres);
        }
        if (!empty($claimTypeId)) {
            $query .= " AND ec.ClaimId = ?";
            $bindings[] = $claimTypeId;
        }
        if (!empty($month)) {
            $query .= " AND ec.ClaimMonth = ?";
            $bindings[] = $month;
        }

        $query .= " ORDER BY e.EmpCode_New, ec.ClaimMonth, ct.ClaimName";

        // Get data
        $data = DB::connection($expConn)->select($query, $bindings);

        // Use CSV format for memory efficiency
        $filename = "expense_type_detailed_FY{$yearId}_" . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        $headers = ['S.No', 'ExpId', 'Emp Code', 'Employee Name', 'Expense Type', 'Month', 'Claimed Amount', 'Verified Amount', 'Approved Amount', 'Financed Amount', 'Vertical', 'BU', 'Region', 'Department'];
        fputcsv($output, $headers);

        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Write data row by row
        $rowNum = 1;
        foreach ($data as $row) {
            $csvRow = [
                $rowNum,
                $row->ExpId,
                $row->emp_code,
                $row->employee_name,
                $row->expense_type,
                $months[(int)$row->month] ?? $row->month,
                (float)$row->claimed_amount,
                (float)$row->verified_amount,
                (float)$row->approved_amount,
                (float)$row->financed_amount,
                $row->vertical_name ?? '',
                $row->business_unit_name ?? '',
                $row->region_name ?? '',
                $row->department_name ?? '',
            ];
            fputcsv($output, $csvRow);
            $rowNum++;

            // Clear memory periodically
            if ($rowNum % 5000 == 0) {
                gc_collect_cycles();
            }
        }

        fputcsv($output, []); fputcsv($output, \App\Support\ReportStamp::csvRow()); // STAMP-01
        fclose($output);
        exit;
    }

    public function export(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900);
        set_time_limit(900);

        $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
        $departmentId = $request->input('department_id');
        $verticalId = $request->input('vertical_id');
        $buId = $request->input('bu_id');
        $claimTypeId = $request->input('claim_type_id');
        $month = $request->input('month');

        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $table = "y{$yearId}_expenseclaims";

        // Build WHERE clause
        $where = "WHERE c.ClaimStatus NOT IN ('Deactivate', 'Draft') AND c.FilledBy > 0 AND c.ClaimYearId = ?";
        $bindings = [$yearId];

        $organogramFilters = $this->extractOrganogramFilters($request);
        $extraWheres = [];
        \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
        if (!empty($extraWheres)) {
            $where .= " AND " . implode(" AND ", $extraWheres);
        }
        if ($claimTypeId) {
            $where .= " AND c.ClaimId = ?";
            $bindings[] = $claimTypeId;
        }
        if ($month) {
            $where .= " AND c.ClaimMonth = ?";
            $bindings[] = $month;
        }

        $filename = "expense_type_report_FY{$yearId}_" . date('Ymd_His') . '.csv';

        // Stream CSV response
        return response()->streamDownload(function () use ($expConn, $table, $hrimsDb, $where, $bindings) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            $headers = [
                'S.No', 'Emp Code', 'Name', 'Status', 'Function', 'Vertical', 'Department',
                'Sub Dept', 'Section', 'Designation', 'Grade', 'Cost Center',
                'BU', 'Zone', 'Region', 'Territory',
                'Month', 'Claim Code', 'Bill Date', 'Upload Date',
                'Employee Amt', 'Verify Date', 'Verify Amt', 'Appr Date', 'Approver Amt',
                'Finance Date', 'Finance Amt', 'Claim Status', 'ExpId'
            ];
            fputcsv($output, $headers);

            $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chunkSize = 5000;
            $offset = 0;
            $rowNum = 1;

            do {
                $query = "
                    SELECT
                        e.EmpCode_New AS emp_code,
                        CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) AS employee_name,
                        e.EmpStatus AS status,
                        IFNULL(fun.function_name, '') AS function_name,
                        IFNULL(ver.vertical_name, '') AS vertical_name,
                        IFNULL(dept.department_name, '') AS department_name,
                        IFNULL(subdept.sub_department_name, '') AS sub_department_name,
                        IFNULL(sec.section_name, '') AS section_name,
                        IFNULL(desig.designation_name, '') AS designation_name,
                        IFNULL(gr.grade_name, '') AS grade_name,
                        IFNULL(st.state_name, '') AS state_name,
                        IFNULL(Bu.business_unit_name, '') AS business_unit_name,
                        IFNULL(Zn.zone_name, '') AS zone_name,
                        IFNULL(Rg.region_name, '') AS region_name,
                        IFNULL(Tr.territory_name, '') AS territory_name,
                        c.ClaimMonth AS month,
                        IFNULL(t.ClaimCode, '') AS claim_code,
                        c.BillDate AS bill_date,
                        c.CrDate AS filled_date,
                        c.FilledTAmt AS employee_amt,
                        c.VerifyDate AS verify_date,
                        c.VerifyTAmt AS verify_amt,
                        c.ApprDate AS appr_date,
                        c.ApprTAmt AS approver_amt,
                        c.FinancedDate AS financed_date,
                        c.FinancedTAmt AS finance_amt,
                        c.ClaimStatus AS claim_status,
                        c.ExpId
                    FROM {$table} c
                    LEFT JOIN claimtype t ON c.ClaimId = t.ClaimId
                    INNER JOIN {$hrimsDb}.hrm_employee e ON c.CrBy = e.EmployeeID
                    LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_functions fun ON g.EmpFunction = fun.id
                    LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id
                    LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
                    LEFT JOIN {$hrimsDb}.core_sub_department_master subdept ON g.SubDepartmentId = subdept.id
                    LEFT JOIN {$hrimsDb}.core_section sec ON g.EmpSection = sec.id
                    LEFT JOIN {$hrimsDb}.core_designation desig ON g.DesigId = desig.id
                    LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id
                    LEFT JOIN {$hrimsDb}.core_states st ON g.CostCenter = st.id
                    LEFT JOIN {$hrimsDb}.core_business_unit Bu ON g.BUId = Bu.id
                    LEFT JOIN {$hrimsDb}.core_zones Zn ON g.ZoneId = Zn.id
                    LEFT JOIN {$hrimsDb}.core_regions Rg ON g.RegionId = Rg.id
                    LEFT JOIN {$hrimsDb}.core_territory Tr ON g.TerrId = Tr.id
                    {$where}
                    ORDER BY e.EmpCode_New, c.ClaimMonth DESC
                    LIMIT {$chunkSize} OFFSET {$offset}
                ";

                $chunk = DB::connection($expConn)->select($query, $bindings);
                $chunkCount = count($chunk);

                foreach ($chunk as $row) {
                    fputcsv($output, [
                        $rowNum,
                        $row->emp_code ?? '',
                        $row->employee_name ?? '',
                        $row->status ?? '',
                        $row->function_name ?? '',
                        $row->vertical_name ?? '',
                        $row->department_name ?? '',
                        $row->sub_department_name ?? '',
                        $row->section_name ?? '',
                        $row->designation_name ?? '',
                        $row->grade_name ?? '',
                        $row->state_name ?? '',
                        $row->business_unit_name ?? '',
                        $row->zone_name ?? '',
                        $row->region_name ?? '',
                        $row->territory_name ?? '',
                        $months[(int)($row->month ?? 0)] ?? '',
                        $row->claim_code ?? '',
                        $row->bill_date ?? '',
                        $row->filled_date ?? '',
                        (float)($row->employee_amt ?? 0),
                        $row->verify_date ?? '',
                        (float)($row->verify_amt ?? 0),
                        $row->appr_date ?? '',
                        (float)($row->approver_amt ?? 0),
                        $row->financed_date ?? '',
                        (float)($row->finance_amt ?? 0),
                        $row->claim_status ?? '',
                        $row->ExpId ?? '',
                    ]);
                    $rowNum++;
                }

                $offset += $chunkSize;

                // Free memory
                unset($chunk);
                gc_collect_cycles();

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

            } while ($chunkCount === $chunkSize);

            fputcsv($output, []); fputcsv($output, \App\Support\ReportStamp::csvRow()); // STAMP-01
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function getContributionReport(Request $request)
    {
        try {
            $yearId = (int) $request->input('year_id', \App\Support\CurrentYear::id());
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $buId = $request->input('bu_id');
            $functionId = $request->input('function_id');
            $month = $request->input('month');
            $viewBy = $request->input('view_by', 'claim_type');
            $topLimit = (int) $request->input('top_limit', 10);

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $table = "y{$yearId}_expenseclaims";

            $joinClause = '';
            $groupByColumn = '';
            $categoryName = '';

            switch($viewBy) {
                case 'region':
                    $joinClause = "LEFT JOIN {$hrimsDb}.core_regions rg ON g.RegionId = rg.id";
                    $groupByColumn = 'COALESCE(rg.region_name, "Unassigned")';
                    $categoryName = 'COALESCE(rg.region_name, "Unassigned")';
                    break;
                case 'department':
                    $joinClause = "LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id";
                    $groupByColumn = 'COALESCE(dept.department_name, "Unassigned")';
                    $categoryName = 'COALESCE(dept.department_name, "Unassigned")';
                    break;
                case 'grade':
                    $joinClause = "LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id";
                    $groupByColumn = 'COALESCE(gr.grade_name, "Unassigned")';
                    $categoryName = 'COALESCE(gr.grade_name, "Unassigned")';
                    break;
                case 'vertical':
                    $joinClause = "LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id";
                    $groupByColumn = 'COALESCE(ver.vertical_name, "Unassigned")';
                    $categoryName = 'COALESCE(ver.vertical_name, "Unassigned")';
                    break;
                case 'business_unit':
                case 'bu':
                    $joinClause = "LEFT JOIN {$hrimsDb}.core_business_unit bu ON g.BUId = bu.id";
                    $groupByColumn = 'COALESCE(bu.business_unit_name, "Unassigned")';
                    $categoryName = 'COALESCE(bu.business_unit_name, "Unassigned")';
                    break;
                case 'function':
                    $joinClause = "LEFT JOIN {$hrimsDb}.core_functions f ON g.EmpFunction = f.id";
                    $groupByColumn = 'COALESCE(f.function_name, "Unassigned")';
                    $categoryName = 'COALESCE(f.function_name, "Unassigned")';
                    break;
                case 'employee':
                    $joinClause = '';
                    $groupByColumn = 'e.EmployeeID, CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname)))';
                    $categoryName = 'CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname)))';
                    break;
                case 'claim_type':
                default:
                    $joinClause = "INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId";
                    $groupByColumn = 'ct.ClaimName';
                    $categoryName = 'ct.ClaimName';
            }

            $bindings = [$yearId];

            $query = "
                SELECT
                    {$categoryName} AS category_name,
                    COALESCE(SUM(ec.FilledTAmt), 0) AS total_claimed,
                    COALESCE(SUM(ec.VerifyTAmt), 0) AS total_verified,
                    COALESCE(SUM(ec.ApprTAmt), 0) AS total_approved,
                    COALESCE(SUM(ec.FinancedTAmt), 0) AS total_financed,
                    COUNT(ec.ClaimId) AS claim_count
                FROM {$table} ec
                INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
                INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                {$joinClause}
                WHERE ec.FilledBy > 0
                AND ec.ClaimYearId = ?
            ";

            $organogramFilters = $this->extractOrganogramFilters($request);
            $extraWheres = [];
            \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
            if (!empty($extraWheres)) {
                $query .= " AND " . implode(" AND ", $extraWheres);
            }
            if ($month) {
                $query .= " AND ec.ClaimMonth = ?";
                $bindings[] = $month;
            }

            $query .= " GROUP BY {$groupByColumn}";
            $query .= " ORDER BY total_financed DESC";

            if ($topLimit > 0) {
                $query .= " LIMIT {$topLimit}";
            }

            $data = DB::connection($expConn)->select($query, $bindings);

            return response()->json([
                'success' => true,
                'data' => $data ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getDepartments(Request $request)
    {
        try {
            $yearId = (int) $request->input('year_id', \App\Support\CurrentYear::id());
            $functionId = $request->input('function_id');

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $table = "y{$yearId}_expenseclaims";

            $query = DB::connection($expConn)->table(DB::raw("{$table} ec"))
                ->distinct()
                ->select('dept.id', 'dept.department_name as name')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->where('ec.FilledBy', '>', 0)
                ->where('ec.ClaimYearId', $yearId);

            if ($functionId) {
                $query->where('g.EmpFunction', $functionId);
            }

            $data = $query->orderBy('dept.department_name')->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    public function getFunctions(Request $request)
    {
        try {
            $yearId = (int) $request->input('year_id', \App\Support\CurrentYear::id());
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $table = "y{$yearId}_expenseclaims";

            $query = DB::connection($expConn)->table(DB::raw("{$table} ec"))
                ->distinct()
                ->select('f.id', 'f.function_name as name')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_functions f"), 'g.EmpFunction', '=', 'f.id')
                ->where('ec.FilledBy', '>', 0)
                ->where('ec.ClaimYearId', $yearId)
                ->whereNotNull('f.id');

            if ($departmentId) {
                $query->where('g.DepartmentId', $departmentId);
            }
            if ($verticalId) {
                $query->where('g.EmpVertical', $verticalId);
            }

            $data = $query->orderBy('f.function_name')->get();

            return response()->json([
                'success' => true,
                'data' => $data ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getEmployees(Request $request)
    {
        try {
            $yearId = (int) $request->input('year_id', \App\Support\CurrentYear::id());
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $table = "y{$yearId}_expenseclaims";

            $bindings = [$yearId];

            $query = "
                SELECT DISTINCT
                    e.EmployeeID AS id,
                    e.EmpCode_New AS code,
                    CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) AS name
                FROM {$table} ec
                INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
                INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                WHERE ec.FilledBy > 0
                AND ec.ClaimYearId = ?
            ";

            if ($departmentId) {
                $query .= " AND g.DepartmentId = ?";
                $bindings[] = $departmentId;
            }
            if ($verticalId) {
                $query .= " AND g.EmpVertical = ?";
                $bindings[] = $verticalId;
            }
            if ($functionId) {
                $query .= " AND g.EmpFunction = ?";
                $bindings[] = $functionId;
            }

            $query .= " ORDER BY e.EmpCode_New ASC";

            $data = DB::connection($expConn)->select($query, $bindings);

            return response()->json([
                'success' => true,
                'data' => $data ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function exportContribution(Request $request)
    {
        $yearId = (int) $request->input('year_id', \App\Support\CurrentYear::id());
        $departmentId = $request->input('department_id');
        $verticalId = $request->input('vertical_id');
        $buId = $request->input('bu_id');
        $functionId = $request->input('function_id');
        $month = $request->input('month');
        $viewBy = $request->input('view_by', 'claim_type');
        $topLimit = (int) $request->input('top_limit', 10);

        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $table = "y{$yearId}_expenseclaims";

        $joinClause = '';
        $groupByColumn = '';
        $categoryName = '';

        switch($viewBy) {
            case 'region':
                $joinClause = "LEFT JOIN {$hrimsDb}.core_regions rg ON g.RegionId = rg.id";
                $groupByColumn = 'COALESCE(rg.region_name, "Unassigned")';
                $categoryName = 'COALESCE(rg.region_name, "Unassigned")';
                break;
            case 'department':
                $joinClause = "LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id";
                $groupByColumn = 'COALESCE(dept.department_name, "Unassigned")';
                $categoryName = 'COALESCE(dept.department_name, "Unassigned")';
                break;
            case 'grade':
                $joinClause = "LEFT JOIN {$hrimsDb}.core_grades gr ON g.GradeId = gr.id";
                $groupByColumn = 'COALESCE(gr.grade_name, "Unassigned")';
                $categoryName = 'COALESCE(gr.grade_name, "Unassigned")';
                break;
            case 'vertical':
                $joinClause = "LEFT JOIN {$hrimsDb}.core_verticals ver ON g.EmpVertical = ver.id";
                $groupByColumn = 'COALESCE(ver.vertical_name, "Unassigned")';
                $categoryName = 'COALESCE(ver.vertical_name, "Unassigned")';
                break;
            case 'business_unit':
            case 'bu':
                $joinClause = "LEFT JOIN {$hrimsDb}.core_business_unit bu ON g.BUId = bu.id";
                $groupByColumn = 'COALESCE(bu.business_unit_name, "Unassigned")';
                $categoryName = 'COALESCE(bu.business_unit_name, "Unassigned")';
                break;
            case 'function':
                $joinClause = "LEFT JOIN {$hrimsDb}.core_functions f ON g.EmpFunction = f.id";
                $groupByColumn = 'COALESCE(f.function_name, "Unassigned")';
                $categoryName = 'COALESCE(f.function_name, "Unassigned")';
                break;
            case 'employee':
                $joinClause = '';
                $groupByColumn = 'e.EmployeeID, CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname)))';
                $categoryName = 'CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname)))';
                break;
            case 'claim_type':
            default:
                $joinClause = "INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId";
                $groupByColumn = 'ct.ClaimName';
                $categoryName = 'ct.ClaimName';
        }

        $bindings = [$yearId];

        $query = "
            SELECT
                {$categoryName} AS category_name,
                COALESCE(SUM(ec.FilledTAmt), 0) AS total_claimed,
                COALESCE(SUM(ec.VerifyTAmt), 0) AS total_verified,
                COALESCE(SUM(ec.ApprTAmt), 0) AS total_approved,
                COALESCE(SUM(ec.FinancedTAmt), 0) AS total_financed,
                COUNT(ec.ClaimId) AS claim_count
            FROM {$table} ec
            INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
            INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
            {$joinClause}
            WHERE ec.FilledBy > 0
            AND ec.ClaimYearId = ?
        ";

        $organogramFilters = $this->extractOrganogramFilters($request);
        $extraWheres = [];
        \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
        if (!empty($extraWheres)) {
            $query .= " AND " . implode(" AND ", $extraWheres);
        }
        if ($month) {
            $query .= " AND ec.ClaimMonth = ?";
            $bindings[] = $month;
        }

        $query .= " GROUP BY {$groupByColumn}";
        $query .= " ORDER BY total_financed DESC";

        if ($topLimit > 0) {
            $query .= " LIMIT {$topLimit}";
        }

        $data = DB::connection($expConn)->select($query, $bindings);

        $viewByLabel = [
            'claim_type' => 'Claim Type',
            'region' => 'Region',
            'department' => 'Department',
            'grade' => 'Grade',
            'vertical' => 'Vertical',
            'business_unit' => 'Business Unit',
            'bu' => 'Business Unit',
            'function' => 'Function',
            'employee' => 'Employee'
        ][$viewBy] ?? 'Category';

        // Build Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Top Contribution');

        $headers = ['S.No', $viewByLabel, 'Claim Count', 'Claimed Amount', 'Verified Amount', 'Approved Amount', 'Financed Amount', '% of Total'];
        $sheet->fromArray([$headers], null, 'A1');

        $rows = [];
        $grandTotal = array_sum(array_map(fn($r) => (float)$r->total_financed, $data));
        foreach ($data as $i => $row) {
            $pct = $grandTotal > 0 ? ((float)$row->total_financed / $grandTotal * 100) : 0;
            $rows[] = [
                $i + 1,
                $row->category_name,
                (int)$row->claim_count,
                (float)$row->total_claimed,
                (float)$row->total_verified,
                (float)$row->total_approved,
                (float)$row->total_financed,
                number_format($pct, 2) . '%',
            ];
        }

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $lastRow = count($rows) + 1;

        // Style header
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A1:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('C2:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Title
        $sheet->insertNewRowBefore(1, 2);
        $yearLabel = DB::connection($expConn)->table('financialyear')->where('YearId', $yearId)->value('Year') ?? $yearId;
        $sheet->setCellValue('A1', "Top Contribution Report — {$viewByLabel} — FY {$yearLabel}");
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '15648C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('A2', 'Generated: ' . date('d-M-Y H:i') . ' | Records: ' . count($rows));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        $filename = "contribution_report_FY{$yearId}_" . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        \App\Support\ReportStamp::workbook($spreadsheet); // STAMP-01

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * View for Manager Grouped Expense Type Report (Multi-Company).
     */
    public function managerGroupedIndex(Request $request)
    {
        $expConn = session('expense_connection', 'expense');
        $years = [];
        try {
            $years = DB::connection($expConn)->table('financialyear')
                ->orderByDesc('YearId')->get(['YearId', 'Year']);
        } catch (\Exception $e) {
            // Fallback default years
            $years = collect([
                (object)['YearId' => 8, 'Year' => '2024-2025'],
                (object)['YearId' => 7, 'Year' => '2023-2024'],
                (object)['YearId' => 6, 'Year' => '2022-2023'],
            ]);
        }

        $selectedEmployeeId = $request->input('employee_id');
        $selectedYearId = (int) ($request->input('year_id', session('year_id', 8)));
        $initialEmployee = null;

        if ($selectedEmployeeId) {
            try {
                $emp = DB::connection('hrims')->table('hrm_employee as e')
                    ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                    ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
                    ->leftJoin('core_designation as desig', 'g.DesigId', '=', 'desig.id')
                    ->select(
                        'e.EmployeeID as id',
                        'e.EmpCode_New as emp_code',
                        DB::raw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as emp_name"),
                        'e.EmpStatus as emp_status',
                        'd.department_name',
                        'desig.designation_name'
                    )
                    ->where('e.EmployeeID', $selectedEmployeeId)
                    ->first();

                if ($emp) {
                    $statusLabel = ($emp->emp_status === 'A') ? '' : ' [Deactive]';
                    $extra = array_filter([$emp->department_name, $emp->designation_name]);
                    $extraStr = !empty($extra) ? ' (' . implode(' - ', $extra) . ')' : '';
                    $initialEmployee = [
                        'id' => $emp->id,
                        'text' => "{$emp->emp_code} - {$emp->emp_name}{$extraStr}{$statusLabel}",
                        'emp_code' => $emp->emp_code,
                        'emp_name' => $emp->emp_name,
                    ];
                }
            } catch (\Exception $e) {
                // Ignore DB error if connection is temporarily unavailable
            }
        }

        return view('admin.reports.manager-grouped-expense-report', compact(
            'years',
            'selectedEmployeeId',
            'selectedYearId',
            'initialEmployee'
        ));
    }

    /**
     * Search employees/managers across companies for Select2 autocomplete.
     */
    public function searchManagers(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['results' => []]);
        }

        try {
            $employees = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
                ->leftJoin('core_designation as desig', 'g.DesigId', '=', 'desig.id')
                ->select(
                    'e.EmployeeID as id',
                    'e.EmpCode_New as emp_code',
                    DB::raw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as emp_name"),
                    'e.EmpStatus as emp_status',
                    'd.department_name',
                    'desig.designation_name'
                )
                ->where(function ($query) use ($q) {
                    $query->where('e.EmpCode_New', 'LIKE', "%{$q}%")
                          ->orWhereRaw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) LIKE ?", ["%{$q}%"]);
                })
                ->orderBy('e.EmpCode_New')
                ->limit(30)
                ->get();

            $results = $employees->map(function ($emp) {
                $statusLabel = ($emp->emp_status === 'A') ? '' : ' [Deactive]';
                $extra = array_filter([$emp->department_name, $emp->designation_name]);
                $extraStr = !empty($extra) ? ' (' . implode(' - ', $extra) . ')' : '';
                return [
                    'id' => $emp->id,
                    'text' => "{$emp->emp_code} - {$emp->emp_name}{$extraStr}{$statusLabel}",
                    'emp_code' => $emp->emp_code,
                    'emp_name' => $emp->emp_name,
                ];
            });

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['results' => [], 'error' => $e->getMessage()]);
        }
    }
}
