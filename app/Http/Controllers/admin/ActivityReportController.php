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

class ActivityReportController extends Controller
{
    public function index()
    {
        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

        $departments = DB::connection('hrims')->table('core_departments')
            ->where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name']);

        $verticals = DB::connection('hrims')->table('core_verticals')
            ->where('is_active', 1)->orderBy('vertical_name')->get(['id', 'vertical_name']);

        $functions = DB::connection('hrims')->table('core_functions')
            ->where('is_active', 1)->orderBy('function_name')->get(['id', 'function_name']);

        $claimTypes = DB::connection($expConn)->table('claimtype')
            ->whereIn('ClaimStatus', ['A', 'B','D'])->where('cgId', 7)->orderBy('ClaimName')->get(['ClaimId as id', 'ClaimName as name']);

        $companyId = session('company_id', 1);
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        return view('admin.activity-report', array_merge($organogramOptions, compact('departments', 'verticals', 'functions', 'claimTypes')));
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

    public function getActivityWise(Request $request)
    {
        try {
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $claimTypeId = $request->input('claim_type_id');
            $month = $request->input('month');

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $yearId = \App\Support\CurrentYear::id();
            $table = "y{$yearId}_expenseclaims";

            $query = "
                SELECT 
                    ct.ClaimName AS activity_type,
                    COUNT(ec.ExpId) AS count,
                    COUNT(DISTINCT ec.CrBy) AS employee_count,
                    COALESCE(SUM(ec.FilledTAmt), 0) AS total_claimed,
                    COALESCE(SUM(ec.VerifyTAmt), 0) AS total_verified,
                    COALESCE(SUM(ec.ApprTAmt), 0) AS total_approved,
                    COALESCE(SUM(ec.FinancedTAmt), 0) AS total_financed
                FROM {$table} ec
                INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
                INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                LEFT JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft')
                AND ec.FilledBy > 0
                AND ct.cgId = 7
            ";

            $bindings = [];

            $organogramFilters = $this->extractOrganogramFilters($request);
            $extraWheres = [];
            \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
            if (!empty($extraWheres)) {
                $query .= " AND " . implode(" AND ", $extraWheres);
            }
            if ($claimTypeId) {
                $query .= " AND ec.ClaimId = ?";
                $bindings[] = $claimTypeId;
            }
            if ($month) {
                $query .= " AND ec.ClaimMonth = ?";
                $bindings[] = $month;
            }

            $query .= " GROUP BY ct.ClaimName ORDER BY total_financed DESC";

            $data = DB::connection($expConn)->select($query, $bindings);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getDailySummary(Request $request)
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $yearId = \App\Support\CurrentYear::id();
            $table = "y{$yearId}_expenseclaims";

            $query = "
                SELECT 
                    DATE(ec.BillDate) AS date,
                    COUNT(ec.ExpId) AS count,
                    COALESCE(SUM(ec.FilledTAmt), 0) AS total_claimed,
                    COALESCE(SUM(ec.VerifyTAmt), 0) AS total_verified,
                    COALESCE(SUM(ec.ApprTAmt), 0) AS total_approved,
                    COALESCE(SUM(ec.FinancedTAmt), 0) AS total_financed
                FROM {$table} ec
                INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft')
                AND ec.FilledBy > 0
                AND ct.cgId = 7
            ";

            $bindings = [];

            if ($fromDate) {
                $query .= " AND DATE(ec.BillDate) >= ?";
                $bindings[] = $fromDate;
            }
            if ($toDate) {
                $query .= " AND DATE(ec.BillDate) <= ?";
                $bindings[] = $toDate;
            }

            $query .= " GROUP BY DATE(ec.BillDate) ORDER BY date DESC";

            $data = DB::connection($expConn)->select($query, $bindings);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getEmployeeTracking(Request $request)
    {
        try {
            $departmentId = $request->input('department_id');
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $topLimit = (int) $request->input('top_limit', 10);

            $expConn = session('expense_connection', 'expense');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $yearId = \App\Support\CurrentYear::id();
            $table = "y{$yearId}_expenseclaims";

            $query = "
                SELECT 
                    e.EmpCode_New AS emp_code,
                    CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) AS employee_name,
                    dept.department_name,
                    COUNT(ec.ExpId) AS count,
                    COALESCE(SUM(ec.FilledTAmt), 0) AS total_claimed,
                    COALESCE(SUM(ec.VerifyTAmt), 0) AS total_verified,
                    COALESCE(SUM(ec.ApprTAmt), 0) AS total_approved,
                    COALESCE(SUM(ec.FinancedTAmt), 0) AS total_financed
                FROM {$table} ec
                INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
                INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
                INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft')
                AND ec.FilledBy > 0
                AND ct.cgId = 7
            ";

            $bindings = [];

            $organogramFilters = $this->extractOrganogramFilters($request);
            $extraWheres = [];
            \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
            if (!empty($extraWheres)) {
                $query .= " AND " . implode(" AND ", $extraWheres);
            }
            if ($fromDate) {
                $query .= " AND DATE(ec.BillDate) >= ?";
                $bindings[] = $fromDate;
            }
            if ($toDate) {
                $query .= " AND DATE(ec.BillDate) <= ?";
                $bindings[] = $toDate;
            }

            $query .= " GROUP BY e.EmployeeID, e.EmpCode_New, e.Fname, e.Sname, e.Lname, dept.department_name";
            $query .= " ORDER BY total_claimed DESC";

            if ($topLimit > 0) {
                $query .= " LIMIT {$topLimit}";
            }

            $data = DB::connection($expConn)->select($query, $bindings);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getPipeline(Request $request)
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $expConn = session('expense_connection', 'expense');
            $yearId = \App\Support\CurrentYear::id();
            $table = "y{$yearId}_expenseclaims";

            $bindings = [];
            $dateFilter = "INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft') AND ec.FilledBy > 0 AND ct.cgId = 7";

            if ($fromDate) {
                $dateFilter .= " AND DATE(ec.BillDate) >= ?";
                $bindings[] = $fromDate;
            }
            if ($toDate) {
                $dateFilter .= " AND DATE(ec.BillDate) <= ?";
                $bindings[] = $toDate;
            }

            $claimed = DB::connection($expConn)->selectOne(
                "SELECT COUNT(ec.ExpId) as count, COALESCE(SUM(ec.FilledTAmt), 0) as amount, COUNT(DISTINCT ec.CrBy) as employees FROM {$table} ec {$dateFilter}",
                $bindings
            );

            $verified = DB::connection($expConn)->selectOne(
                "SELECT COUNT(ec.ExpId) as count, COALESCE(SUM(ec.VerifyTAmt), 0) as amount, COUNT(DISTINCT ec.CrBy) as employees FROM {$table} ec {$dateFilter} AND ec.VerifyTAmt > 0",
                $bindings
            );

            $approved = DB::connection($expConn)->selectOne(
                "SELECT COUNT(ec.ExpId) as count, COALESCE(SUM(ec.ApprTAmt), 0) as amount, COUNT(DISTINCT ec.CrBy) as employees FROM {$table} ec {$dateFilter} AND ec.ApprTAmt > 0",
                $bindings
            );

            $financed = DB::connection($expConn)->selectOne(
                "SELECT COUNT(ec.ExpId) as count, COALESCE(SUM(ec.FinancedTAmt), 0) as amount, COUNT(DISTINCT ec.CrBy) as employees FROM {$table} ec {$dateFilter} AND ec.FinancedTAmt > 0",
                $bindings
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'claimed' => (array)$claimed,
                    'verified' => (array)$verified,
                    'approved' => (array)$approved,
                    'financed' => (array)$financed,
                    'total_claimed' => $claimed->amount ?? 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getPerformance(Request $request)
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $expConn = session('expense_connection', 'expense');
            $yearId = \App\Support\CurrentYear::id();
            $table = "y{$yearId}_expenseclaims";

            $bindings = [];
            $dateFilter = "INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft') AND ec.FilledBy > 0 AND ct.cgId = 7";

            if ($fromDate) {
                $dateFilter .= " AND DATE(ec.BillDate) >= ?";
                $bindings[] = $fromDate;
            }
            if ($toDate) {
                $dateFilter .= " AND DATE(ec.BillDate) <= ?";
                $bindings[] = $toDate;
            }

            $claimedCount = DB::connection($expConn)->selectOne("SELECT COUNT(ec.ExpId) as cnt FROM {$table} ec {$dateFilter}", $bindings)->cnt ?? 0;
            $verifiedCount = DB::connection($expConn)->selectOne("SELECT COUNT(ec.ExpId) as cnt FROM {$table} ec {$dateFilter} AND ec.VerifyTAmt > 0", $bindings)->cnt ?? 0;
            $approvedCount = DB::connection($expConn)->selectOne("SELECT COUNT(ec.ExpId) as cnt FROM {$table} ec {$dateFilter} AND ec.ApprTAmt > 0", $bindings)->cnt ?? 0;
            $financedCount = DB::connection($expConn)->selectOne("SELECT COUNT(ec.ExpId) as cnt FROM {$table} ec {$dateFilter} AND ec.FinancedTAmt > 0", $bindings)->cnt ?? 0;
            
            $totalAmount = DB::connection($expConn)->selectOne("SELECT COALESCE(SUM(ec.FilledTAmt), 0) as amt FROM {$table} ec {$dateFilter}", $bindings)->amt ?? 0;
            $avgDays = DB::connection($expConn)->selectOne(
                "SELECT COALESCE(AVG(DATEDIFF(COALESCE(ec.ApprovingDate, CURDATE()), ec.BillDate)), 0) as days FROM {$table} ec {$dateFilter} AND ec.FinancedTAmt > 0",
                $bindings
            )->days ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_count' => $claimedCount,
                    'claimed_count' => $claimedCount,
                    'verified_count' => $verifiedCount,
                    'approved_count' => $approvedCount,
                    'financed_count' => $financedCount,
                    'total_amount' => $totalAmount,
                    'avg_days_to_finance' => $avgDays
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Export month-wise activity data to Excel
     */
    public function exportMonthWise(Request $request)
    {
        $departmentId = $request->input('department_id');
        $verticalId = $request->input('vertical_id');
        $functionId = $request->input('function_id');
        $claimTypeId = $request->input('claim_type_id');

        $expConn = session('expense_connection', 'expense');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $yearId = \App\Support\CurrentYear::id();
        $table = "y{$yearId}_expenseclaims";

        $query = "
            SELECT 
                ec.ClaimMonth AS month,
                ct.ClaimName AS activity_type,
                e.EmpCode_New AS emp_code,
                CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) AS employee_name,
                dept.department_name,
                f.function_name,
                COUNT(ec.ExpId) AS count,
                COALESCE(SUM(ec.FilledTAmt), 0) AS total_claimed,
                COALESCE(SUM(ec.VerifyTAmt), 0) AS total_verified,
                COALESCE(SUM(ec.ApprTAmt), 0) AS total_approved,
                COALESCE(SUM(ec.FinancedTAmt), 0) AS total_financed
            FROM {$table} ec
            INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
            INNER JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
            LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_functions f ON g.EmpFunction = f.id
            INNER JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
            WHERE ec.ClaimStatus NOT IN ('Deactivate', 'Draft')
            AND ec.FilledBy > 0
            AND ct.cgId IN (7,2,4,5,6)
        ";

        $bindings = [];

        $organogramFilters = $this->extractOrganogramFilters($request);
        $extraWheres = [];
        \App\Helpers\OrganogramFilterHelper::appendRawSqlFilters($extraWheres, $bindings, $organogramFilters, 'g', 'e.EmployeeID', $hrimsDb);
        if (!empty($extraWheres)) {
            $query .= " AND " . implode(" AND ", $extraWheres);
        }
        if ($claimTypeId) {
            $query .= " AND ec.ClaimId = ?";
            $bindings[] = $claimTypeId;
        }

        $query .= " GROUP BY ec.ClaimMonth, ct.ClaimName, e.EmpCode_New, e.Fname, e.Sname, e.Lname, dept.department_name, f.function_name";
        $query .= " ORDER BY ec.ClaimMonth, e.EmpCode_New, ct.ClaimName";

        $data = DB::connection($expConn)->select($query, $bindings);

        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Month Wise Report');

        $headers = ['#', 'Month', 'Emp Code', 'Employee Name', 'Department', 'Function', 'Activity Type', 'Count', 'Claimed', 'Verified', 'Approved', 'Financed'];
        $sheet->fromArray([$headers], null, 'A1');

        $excelRows = [];
        $serial = 1;
        foreach ($data as $row) {
            $monthName = $months[(int)$row->month] ?? 'Month ' . $row->month;
            $excelRows[] = [
                $serial++,
                $monthName,
                $row->emp_code,
                trim($row->employee_name),
                $row->department_name ?? '',
                $row->function_name ?? '',
                $row->activity_type ?? '',
                (int)$row->count,
                (float)$row->total_claimed,
                (float)$row->total_verified,
                (float)$row->total_approved,
                (float)$row->total_financed,
            ];
        }

        if (!empty($excelRows)) {
            $sheet->fromArray($excelRows, null, 'A2');
        }

        $lastRow = count($excelRows) + 1;
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
        ]);
        $sheet->getStyle('A1:L' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "activity_report_month_wise_" . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        \App\Support\ReportStamp::workbook($spreadsheet); // STAMP-01

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
