<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class ClaimReportController extends Controller
{
    /**
     * Get with driver badge HTML
     */
    private function getWithDriverBadge($claim)
    {
        // Adjust this logic based on your actual requirements
        if (isset($claim->with_driver) && $claim->with_driver == 1) {
            return '<span class="badge badge-info">With Driver</span>';
        }

        return '<span class="badge badge-secondary">Self Drive</span>';
    }

    /**
     * Get KM details for claim
     */
    private function getKmDetails($claim, $yearId)
    {
        // Adjust this logic based on your actual requirements
        $details = [];

        if ($claim->ClaimId == 7) { // Assuming ClaimId 7 is for transport/fuel
            $details['km_travelled'] = $claim->KmTravelled ?? 0;
            $details['fuel_liters'] = $claim->FuelLiters ?? 0;
            $details['mileage'] = $claim->Mileage ?? 0;
        }

        return (object) $details;
    }

    /**
     * Calculate claim totals for employee
     */
    private function calculateClaimTotals($empId, $month, $yearId)
    {
        try {
            $expTable = "y{$yearId}_expenseclaims";

            $totals = DB::connection(session('expense_connection'))
                ->table($expTable)
                ->where('CrBy', $empId)
                ->where('ClaimMonth', $month)
                ->where('ClaimYearId', $yearId)
                ->where('FilledOkay', 1)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
                ->select(
                    DB::raw('COUNT(*) as total_claims'),
                    DB::raw('SUM(ClaimAmount) as total_claimed'),
                    DB::raw('SUM(ApprovedAmount) as total_approved'),
                    DB::raw('SUM(CASE WHEN ClaimStatus = "Verified" THEN 1 ELSE 0 END) as verified_count'),
                    DB::raw('SUM(CASE WHEN ClaimStatus = "Pending" THEN 1 ELSE 0 END) as pending_count')
                )
                ->first();

            return $totals;
        } catch (\Exception $e) {
            \Log::error('Error calculating totals: '.$e->getMessage());

            return (object) [
                'total_claims' => 0,
                'total_claimed' => 0,
                'total_approved' => 0,
                'verified_count' => 0,
                'pending_count' => 0,
            ];
        }
    }

    public function getEmployeeClaimsAjaxByVerifier(Request $request, $empid = null, $month = null)
    {
        try {
            $empId = $empid ?? $request->empid;
            $month = $month ?? $request->month;
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);

            $yearId = session('year_id');
            $companyId = session('company_id');
            $expTable = "y{$yearId}_expenseclaims";
            $expDetailsTable = "y{$yearId}_g1_expensefilldata";

            $employee = DB::connection(session('hrims_connection'))->table('hrm_employee')
                ->join('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                ->where('hrm_employee.EmployeeID', $empId)
                ->first(['EmpCode', DB::raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', Lname) AS Name")]);

            if (! $employee) {
                return response()->json(['success' => false, 'message' => 'Employee not found']);
            }

            $deptData = DB::connection(session('hrims_connection'))->table('hrm_employee_general')
                ->where('EmployeeID', $empId)
                ->first(['DepartmentId', 'EmpVertical']);

            $deptId = $deptData->DepartmentId ?? 0;
            $empVertical = $deptData->EmpVertical ?? 0;

            $appOdometerCond = '';
            if (in_array($deptId, [2, 3])) {
                $appOdometerCond = " AND ((e.ClaimId = 7 AND e.App_Odometer NOT IN ('N','R')) OR (e.ClaimId != 7))";
            }

            // Get total count first
            $countQuery = DB::connection(session('expense_connection'))->select("
            SELECT COUNT(*) as total
            FROM `{$expTable}` e
            INNER JOIN claimtype c ON c.ClaimId = e.ClaimId
            LEFT JOIN `{$expDetailsTable}` ed ON ed.ExpId = e.ExpId
            WHERE e.ClaimMonth = ?
            AND e.ClaimYearId = ?
            AND e.VerifyBy = ?
            AND e.FilledOkay = 1
            AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
            AND e.ClaimAtStep >= 3
            {$appOdometerCond}
        ", [$month, $yearId, $empId]);

            $totalRecords = $countQuery[0]->total ?? 0;

            // Get paginated data
            $offset = ($page - 1) * $perPage;
            $claims = DB::connection(session('expense_connection'))->select("
            SELECT e.*, c.ClaimName, c.cgid, ed.CityCategory
            FROM `{$expTable}` e
            INNER JOIN claimtype c ON c.ClaimId = e.ClaimId
            LEFT JOIN `{$expDetailsTable}` ed ON ed.ExpId = e.ExpId
            WHERE e.ClaimMonth = ?
            AND e.ClaimYearId = ?
            AND e.VerifyBy = ?
            AND e.FilledOkay = 1
            AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
            AND e.ClaimAtStep >= 3
            {$appOdometerCond}
            ORDER BY e.BillDate ASC
            LIMIT {$offset}, {$perPage}
        ", [$month, $yearId, $empId]);

            $processedClaims = [];
            foreach ($claims as $claim) {
                $claim->file_preview = $this->getFilePreview($claim->ExpId, $yearId, $empId);
                $claim->with_driver = $this->getWithDriverBadge($claim);
                $claim->km_details = $this->getKmDetails($claim, $yearId);
                $claim->emp_att_status = getUserAttStatus($empId, $claim->BillDate);
                $claim->emp_elig = getUserExpEligibility($empId);
                $claim->stay_details = ($empId);
                $processedClaims[] = $claim;
            }

            $totals = $this->calculateClaimTotals($empId, $month, $yearId);
// dd($totals);
            return response()->json([
                'success' => true,
                'data' => $processedClaims,
                'employee' => $employee,
                'totals' => $totals,
                'monthName' => \Carbon\Carbon::createFromDate(null, $month, 1)->format('F Y'),
                'pagination' => [
                    'current_page' => (int) $page,
                    'per_page' => (int) $perPage,
                    'total' => (int) $totalRecords,
                    'last_page' => ceil($totalRecords / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the claims verification report page with data
     */
    public function claimsVerificationReport(Request $request)
    {
        $months = $this->getApprovedClaimsAvailableMonths($request);
        $employees = $this->getApprovedClaimsAvailableEmployees($request);
        $departments = $this->getApprovedClaimsAvailableDepartments($request);

        // dd($employees);
        return view('verifier.verified-by-report', compact('departments', 'months', 'employees'));

    }

    protected function getApprovedClaimsAvailableMonths(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_monthexpensefinal";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::connection(session('expense_connection'))->table($table.' as m')->leftJoin($hrimsDb.'.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb.'.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where('m.Verified_Date', '!=', '0000-00-00')->where('m.Verified_Date', '!=', '1970-01-01');
        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
            });
        }
        if ($request->filled('department')) {
            $query->where('dep.id', $request->department);
        }
        if ($request->filled('employee')) {
            $query->where('m.EmployeeID', $request->employee);
        }

        return $query->select(DB::raw('DISTINCT m.Month as month_value'))->whereNotNull('m.Month')->orderBy('m.Month')->get()->map(function ($item) {
            return ['value' => (int) $item->month_value, 'text' => Carbon::createFromDate(null, (int) $item->month_value, 1)->format('F')];
        });
    }

    protected function getApprovedClaimsAvailableEmployees(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_expenseclaims";
        $query = DB::connection(session('expense_connection'))->table($table.' as m')->where('m.ClaimYearId', $yearId)->where('m.VerifyBy', '>', 0)->where('m.VerifyDate', '!=', '0000-00-00')->where('m.VerifyDate', '!=', '1970-01-01');
        // $courierStatus = $request->input('courier_status', 'all');
        // if ($courierStatus === 'received') {
        //     $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        // } elseif ($courierStatus === 'not_received') {
        //     $query->where(function ($q) {
        //         $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
        //     });
        // }
        if ($request->filled('month')) {
            $query->where('m.Month', (int) $request->month);
        }
        // if (session('company_id') == 11) {
        //     $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
        // }
        if ($request->filled('department')) {
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $query->leftJoin($hrimsDb.'.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')->where('g.DepartmentId', $request->department);
        }
        $employeeIds = $query->select('m.VerifyBy')->distinct()->pluck('m.VerifyBy')->toArray();
        if (empty($employeeIds)) {
            return collect();
        }

        return DB::connection(session('hrims_connection'))->table('hrm_employee')->whereIn('EmployeeID', $employeeIds)->select(['EmployeeID', 'EmpCode', DB::raw("CONCAT(EmpCode, ' - ', Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName")])->orderBy('EmpCode')->get()->map(function ($employee) {
            return ['value' => $employee->EmployeeID, 'text' => $employee->EmpName];
        });
    }

    protected function getApprovedClaimsAvailableDepartments(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_monthexpensefinal";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::connection(session('expense_connection'))->table($table.' as m')->leftJoin($hrimsDb.'.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb.'.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where('m.Verified_Date', '!=', '0000-00-00')->where('m.Verified_Date', '!=', '1970-01-01');
        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
            });
        }
        if ($request->filled('month')) {
            $query->where('m.Month', (int) $request->month);
        }
        if ($request->filled('employee')) {
            $query->where('m.EmployeeID', $request->employee);
        }
        if (session('company_id') == 11) {
            $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
        }

        return $query->select('dep.id', 'dep.department_name')->whereNotNull('dep.id')->whereNotNull('dep.department_name')->distinct()->orderBy('dep.department_name')->get()->map(function ($department) {
            return ['value' => $department->id, 'text' => $department->department_name];
        });
    }

    /**
     * Get single claim details for modal view
     */
    public function getClaimDetails(Request $request)
    {
        try {
            $claimId = $request->input('id');
            if (! $claimId) {
                return response()->json(['success' => false, 'message' => 'Claim ID required'], 400);
            }

            $yearId = session('year_id');
            $expTable = "y{$yearId}_expenseclaims";
            $expDetailsTable = "y{$yearId}_g1_expensefilldata";

            $claim = DB::connection(session('expense_connection'))
                ->table("{$expTable} as e")
                ->leftJoin('claimtype as c', 'c.ClaimId', '=', 'e.ClaimId')
                ->leftJoin("{$expDetailsTable} as ed", 'ed.ExpId', '=', 'e.ExpId')
                ->where('e.ExpId', $claimId)
                ->first();

            if (! $claim) {
                return response()->json(['success' => false, 'message' => 'Claim not found'], 404);
            }

            // Get employee details
            $employee = DB::connection(session('hrims_connection'))
                ->table('hrm_employee')
                ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                ->where('hrm_employee.EmployeeID', $claim->CrBy)
                ->first([
                    'hrm_employee.EmployeeID',
                    'hrm_employee.EmpCode',
                    DB::raw("CONCAT(hrm_employee.Fname, ' ', COALESCE(hrm_employee.Sname, ''), ' ', hrm_employee.Lname) AS employee_name"),
                    'hrm_employee_general.DepartmentId',
                ]);

            // Get department name
            $department = null;
            if ($employee && $employee->DepartmentId) {
                $department = DB::connection(session('hrims_connection'))
                    ->table('hrm_department')
                    ->where('DepartmentId', $employee->DepartmentId)
                    ->first('DepartmentName');
            }

            // Get verifier details
            $verifier = null;
            if ($claim->VerifyBy && $claim->VerifyBy != 0) {
                $verifier = DB::connection(session('hrims_connection'))
                    ->table('hrm_employee')
                    ->where('EmployeeID', $claim->VerifyBy)
                    ->first([
                        'EmployeeID',
                        DB::raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', Lname) AS verifier_name"),
                    ]);
            }

            $claim->employee_details = $employee;
            $claim->department_name = $department ? $department->DepartmentName : 'N/A';
            $claim->verifier_details = $verifier;
            $claim->verification_date_formatted = $claim->VerifyDate ? Carbon::parse($claim->VerifyDate)->format('d-m-Y H:i:s') : null;
            $claim->claim_date_formatted = $claim->BillDate ? Carbon::parse($claim->BillDate)->format('d-m-Y') : null;
            $claim->file_preview = $this->getFilePreview($claim->ExpId, $yearId, $claim->CrBy);

            return response()->json([
                'success' => true,
                'data' => $claim,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getClaimDetails: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export data to Excel or PDF
     */
    private function exportData($claims, $summary, $format)
    {
        // This is a simple CSV export example
        // You can implement Excel/PDF using packages like Maatwebsite/Excel or Barryvdh/DomPDF

        if ($format == 'excel') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="claims_report_'.date('Y-m-d').'.csv"');

            $output = fopen('php://output', 'w');

            // Add headers
            fputcsv($output, [
                'Claim ID', 'Claim Date', 'Employee', 'Emp Code', 'Department',
                'Claim Type', 'Claim Amount', 'Approved Amount', 'Status',
                'Verified By', 'Verification Date', 'Remarks',
            ]);

            // Add data rows
            foreach ($claims as $claim) {
                fputcsv($output, [
                    $claim->ExpId,
                    $claim->claim_date_formatted,
                    $claim->employee_details->employee_name ?? $claim->CrBy,
                    $claim->employee_details->EmpCode ?? '-',
                    $claim->department_name,
                    $claim->ClaimName,
                    $claim->ClaimAmount,
                    $claim->ApprovedAmount,
                    $claim->ClaimStatus,
                    $claim->verifier_details->verifier_name ?? '-',
                    $claim->verification_date_formatted,
                    $claim->Remarks ?? '',
                ]);
            }

            fputcsv($output, []); fputcsv($output, \App\Support\ReportStamp::csvRow()); // STAMP-01
            fclose($output);
            exit;
        }

        return response()->json(['success' => false, 'message' => 'Export format not implemented']);
    }

    /**
     * Get file preview for claim
     */
    protected function getExpenseBasePath(): string
    {
        $companyId = session('company_id');

        return \App\Support\ClaimStorage::prefix((int) $companyId); // S3-FOLDER-01 (old files found via ClaimStorage::resolve)
    }

    protected function getBackupFolder(): string
    {
        $companyId = session('company_id');

        return ($companyId == 11) ? 'vxeasyfile' : 'xeasyfile';
    }

    protected function getS3Url(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        $disk = Storage::disk('s3');
        $path = \App\Support\ClaimStorage::resolve($path); // S3-FOLDER-01: new or old company folder
        if (! $disk->exists($path)) {
            return null;
        }
        if ($disk instanceof FilesystemAdapter && method_exists($disk, 'url')) {
            return $disk->url($path);
        }

        return null;
    }

    private function getFilePreview($expId, $yearId, $empId)
    {
        $upload = DB::connection(session('expense_connection'))->table("y{$yearId}_claimuploads")->where('ExpId', $expId)->first(['FileName']);
        if (! $upload || empty($upload->FileName)) {
            return '<span class="text-muted"></span>';
        }
        $fileName = $upload->FileName;
        $basePath = $this->getExpenseBasePath();
        $backupFolder = $this->getBackupFolder();
        $path = "{$basePath}/{$yearId}/{$empId}/{$fileName}";
        $url = $this->getS3Url($path);
        if (! $url) {
            $url = "https://vnragro.com/File_Bkp/{$backupFolder}/{$yearId}/{$empId}/".rawurlencode($fileName);
        }
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            return '<img style="width:150px;height:80px;" src="'.htmlspecialchars($url).'" class="rounded-sm zoom-verify-img" onclick="viewFullImage(\''.htmlspecialchars($url).'\')">';
        } elseif (in_array($ext, ['pdf', 'doc', 'docx'])) {
            return '<a href="'.htmlspecialchars($url).'" target="_blank" class="btn btn-sm btn-primary"><i class="ri-file-line"></i></a>';
        }

        return '<a href="'.htmlspecialchars($url).'" target="_blank">File</a>';
    }

    /** UAT-03: the "Verified by" report loads its claim-type list here; the route existed, the method did not. */
    public function getClaimTypes()
    {
        $rows = DB::connection(session('expense_connection', 'expense'))->table('claimtype')
            ->where(function ($q) { $q->where('ClaimStatus', 'A')->orWhereNull('ClaimStatus'); })
            ->orderBy('ClaimName')->get(['ClaimId', 'ClaimName']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** UAT-03: verifiers who verified at least one claim in the session year, for the same report. */
    public function getVerifiers()
    {
        $yearId = (int) session('year_id');
        $conn = session('expense_connection', 'expense');
        $ids = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('VerifyBy', '>', 0)->distinct()->pluck('VerifyBy')->map(fn ($v) => (int) $v)->all();
        $rows = $ids ? DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee')->whereIn('EmployeeID', $ids)
            ->selectRaw("EmployeeID, TRIM(CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,''))) as VerifierName")
            ->orderBy('Fname')->get() : collect();

        return response()->json(['success' => true, 'data' => $rows]);
    }
}
