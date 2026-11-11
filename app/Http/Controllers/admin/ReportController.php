<?php

namespace App\Http\Controllers\admin;

use App\Exports\AccessRunningKmExport;
use App\Exports\ClaimReportExport;
use App\Exports\ClaimStagesExport;
use App\Exports\ClaimTypeWiseClaimReportExport;
use App\Exports\DailyActivityReportExport;
use App\Exports\DAInOutReportExport;
use App\Exports\DelayTrackingExport;
use App\Exports\DepartmentWiseClaimReportExport;
use App\Exports\FocusExport;
use App\Exports\HeadWiseClaimReport;
use App\Exports\MonthClaimTypeWiseExpenseExport;
use App\Exports\MonthWiseClaimReportExport;
use App\Exports\MonthWiseExpenseExport;
use App\Exports\PaymentStagesSummaryExport;
use App\Exports\RunningKmExport;
use App\Exports\TotalRunningKmExport;
use App\Exports\SameDayUploadClaimExport;
use App\Exports\SubmissionWiseExport;
use App\Http\Controllers\Controller;
use App\Models\ClaimType;
use App\Models\CoreDepartments;
use App\Models\CoreFunctions;
use App\Models\CoreSubDepartment;
use App\Models\CoreVertical;
use App\Models\EligibilityPolicy;
use App\Models\ExpenseClaim;
use App\Models\FinancialYear;
use App\Models\MonthExpenseFinal;
use App\Services\ClaimActionLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Helpers\OrganogramFilterHelper;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ReportController extends Controller
{
    public function claimReport()
    {
        $company_id = session('company_id');
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->select(
                'e.EmployeeID',
                'e.EmpCode',
                'e.EmpCode_New',
                'e.Fname',
                'e.Sname',
                'e.Lname',
                'e.EmpStatus'
            )
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', $company_id)
            ->orderBy('e.EmployeeID', 'asc')
            ->get();
        $guestUsers = collect([]);
        try {
            $guestUsers = DB::connection(session('expense_connection'))
                ->table('users')
                ->where('is_guest', 1)
                ->where('status', 1)
                ->select('id', 'name', 'employee_id')
                ->orderBy('name', 'asc')
                ->get();
        } catch (\Throwable $e) {
            // In case is_guest column does not exist on this company connection
        }
        $claimTypes = DB::connection(session('expense_connection'))->table('claimtype as ct')->leftJoin('claimgroup as cg', 'cg.cgId', '=', 'ct.cgId')->whereIn('ct.ClaimStatus', ['A', 'B'])->whereIn('ct.cgId', [1, 7])->orderBy('cg.cgName')->orderBy('ct.ClaimName')->get(['ct.ClaimId', 'ct.ClaimName', 'cg.cgName']);

        return view('admin.claim_report', array_merge($organogramOptions, compact('employees', 'guestUsers', 'claimTypes')));
    }

    public function getEmployeesWithMultipleVehiclesView(Request $request)
    {
        try {
            $company_id = session('company_id', 1);
            $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
            $hrimsDb = DB::connection($hrimsConnection)->getDatabaseName();

            $vehicleType = $request->get('vehicle_type');
            $minVehicles = $request->get('min_vehicles', 2);

            // Get all employees who have more than one vehicle
            $query = DB::connection($hrimsConnection)
                ->table('hrm_employee_vehicle as v')
                ->select(
                    'v.EmployeeID',
                    'v.vehicle_type',
                    'v.registration_no',
                    'v.model_name',
                    'v.brand',
                    DB::raw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as employee_name"),
                    'e.EmpCode as employee_code'
                )
                ->join('hrm_employee as e', 'v.EmployeeID', '=', 'e.EmployeeID')
                ->join($hrimsDb.'.hrm_employee_general as eg', 'v.EmployeeID', '=', 'eg.EmployeeID')
                ->whereIn('v.EmployeeID', function ($subquery) use ($minVehicles) {
                    $subquery
                        ->select('EmployeeID')
                        ->from('hrm_employee_vehicle')
                        ->groupBy('EmployeeID')
                        ->havingRaw('COUNT(*) >= ?', [$minVehicles]);
                });

            // Apply vehicle type filter
            if ($vehicleType) {
                $query->where('v.vehicle_type', $vehicleType);
            }

            // Apply 10-level organogram filters
            $filters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'eg.EmployeeID', $hrimsDb);

            $records = $query->orderBy('v.EmployeeID')->get();

            // Calculate totals
            $totalEmployees = $records->unique('EmployeeID')->count();
            $totalVehicles = $records->count();

            $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

            return view('reports.employees_multiple_vehicles', array_merge($organogramOptions, compact('records', 'totalEmployees', 'totalVehicles', 'filters')));
        } catch (\Exception $e) {
            Log::error('Error in getEmployeesWithMultipleVehiclesView: '.$e->getMessage());

            // Return view with empty data
            return view('reports.employees_multiple_vehicles', [
                'records' => collect([]),
                'totalEmployees' => 0,
                'totalVehicles' => 0,
                'error' => 'Failed to load data: '.$e->getMessage(),
            ]);
        }
    }

    public function highAmountClaimReport(Request $request)
    {
        $fromDate = $request->query('fromDate', date('Y-m-01'));
        $toDate = $request->query('toDate', date('Y-m-d'));

        $company_id = session('company_id', 1);
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);

        $year_id = session('year_id');

        $expenseConnection = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

        $table = ExpenseClaim::tableName($year_id);

        $query = DB::connection(session('expense_connection'))
            ->table("$table as ec")
            // Employee join
            ->leftJoin($hrimsDb.'.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')
            // Employee general join for organogram filters
            ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'emp.EmployeeID', '=', 'eg.EmployeeID')
            // Claim type join
            ->leftJoin('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            // Avoid DATE() for performance
            ->whereBetween('ec.CrDate', [$fromDate, $toDate]);

        OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

        $data = $query->select(
                DB::raw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) AS EmployeeName"),
                'emp.EmpCode',
                DB::raw('COUNT(ec.ExpId) as TotalClaims'),
                DB::raw('SUM(ec.FinancedTAmt) as TotalAmount'),
                // Most frequent claim type
                DB::raw("
(
    SELECT MAX(ct2.ClaimName)
    FROM $table ec2
    LEFT JOIN claimtype ct2 ON ec2.ClaimId = ct2.ClaimId
    WHERE ec2.CrBy = ec.CrBy
    AND ec2.CrDate BETWEEN '$fromDate' AND '$toDate'
    GROUP BY ec2.ClaimId
    ORDER BY COUNT(*) DESC
    LIMIT 1
) as TopClaimType
")
            )
            ->groupBy(
                'ec.CrBy',
                'emp.EmployeeID',
                'emp.Fname',
                'emp.Sname',
                'emp.Lname',
                'emp.EmpCode'
            )
            ->orderByDesc('TotalAmount')  // highest spender first
            ->limit(100)
            ->get();

        return view('admin.high_amount_claim_report', array_merge($organogramOptions, compact(
            'data',
            'fromDate',
            'toDate',
            'organogramOptions'
        )));
    }

    public function claimStage()
    {
        $company_id = session('company_id');
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->select(
                'e.EmployeeID',
                'e.EmpCode',
                'e.EmpCode_New',
                'e.Fname',
                'e.Sname',
                'e.Lname',
                'e.EmpStatus'
            )
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', $company_id)
            ->orderBy('e.EmployeeID', 'asc')
            ->get();
        $claimTypes = DB::connection(session('expense_connection'))->table('claimtype as ct')->leftJoin('claimgroup as cg', 'cg.cgId', '=', 'ct.cgId')->whereIn('ct.ClaimStatus', ['A', 'B'])->whereIn('ct.cgId', [1, 7])->orderBy('cg.cgName')->orderBy('ct.ClaimName')->get(['ct.ClaimId', 'ct.ClaimName', 'cg.cgName']);

        return view('admin.claim_stage', array_merge($organogramOptions, compact('employees', 'claimTypes')));
    }

    public function claimStageFy()
    {
        $functions = CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
        $departments = CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);
        $financialYears = DB::connection(session('expense_connection'))->table('financialyear')->orderByDesc('YearId')->get(['YearId', 'Year']);
        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->select('e.EmployeeID', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', session('company_id'))
            ->orderBy('e.EmpCode_New')
            ->get();
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions(session('company_id'));
        return view('admin.claim_stage_fy', array_merge($organogramOptions, compact('functions', 'departments', 'financialYears', 'employees')));
    }

    public function topRatingEmployee(Request $request)
    {
        $fromDate = $request->query('fromDate', date('Y-m-d'));
        $toDate = $request->query('toDate', date('Y-m-d'));
        $type = $request->query('type', 'top');  // default = top

        $year_id = session('year_id');
        $company_id = session('company_id', 1);

        $expenseConnection = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $hrimsDb = DB::connection($hrimsConnection)->getDatabaseName();

        $table = ExpenseClaim::tableName($year_id);

        $query = DB::connection(session('expense_connection'))
            ->table("$table as ec")
            ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'ec.CrBy', '=', 'eg.EmployeeID')
            ->leftJoin($hrimsDb.'.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')
            ->leftJoin($hrimsDb.'.core_verticals as cv', 'cv.id', '=', 'eg.EmpVertical')
            ->leftJoin($hrimsDb.'.core_departments as cd', 'cd.id', '=', 'eg.DepartmentId')
            ->leftJoin($hrimsDb.'.core_grades as cg', 'cg.id', '=', 'eg.GradeId')
            ->whereBetween(DB::raw('DATE(ec.CrDate)'), [$fromDate, $toDate]);

        // Apply 10-level organogram filters
        $filters = OrganogramFilterHelper::extractFilters($request);
        OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'eg.EmployeeID', $hrimsDb);

        $query->select(
                DB::raw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) AS EmployeeName"),
                'emp.EmpCode',
                'cg.grade_name',
                'cv.vertical_name',
                'cd.department_name',
                DB::raw('COUNT(ec.ExpId) AS TotalClaimsUploaded'),
                DB::raw('
                SUM(CASE
                    WHEN DATE(ec.BillDate) = DATE(ec.CrDate)
                    THEN 1 ELSE 0
                END) AS SameDayCount
            ')
            )
            ->groupBy(
                'emp.EmployeeID',
                'emp.Fname',
                'emp.Sname',
                'emp.Lname',
                'emp.EmpCode',
                'cg.grade_name',
                'cv.vertical_name',
                'cd.department_name'
            );

        // ✅ Apply condition based on type
        if ($type === 'top') {
            $query->havingRaw('SameDayCount = COUNT(*)');
        } else {
            $query->havingRaw('SameDayCount < COUNT(*)');
        }

        $data = $query
            ->orderByDesc('TotalClaimsUploaded')
            ->limit(100)
            ->get();

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

        return view('admin.top_rating_employee', array_merge($organogramOptions, compact(
            'data',
            'fromDate',
            'toDate',
            'type',
            'filters'
        )));
    }

    public function getEmployeesWithMultipleVehicles(Request $request)
    {
        try {
            $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
            $hrimsDb = DB::connection($hrimsConnection)->getDatabaseName();

            $minVehicles = $request->input('min_vehicles', 2);
            $vehicleType = $request->input('vehicle_type');
            $employeeSearch = $request->input('employee_search');

            // Get all employees who have more than min_vehicles
            $subquery = DB::connection($hrimsConnection)
                ->table('hrm_employee_vehicle')
                ->select('EmployeeID')
                ->groupBy('EmployeeID')
                ->havingRaw('COUNT(*) >= ?', [$minVehicles]);

            $query = DB::connection($hrimsConnection)
                ->table('hrm_employee_vehicle as v')
                ->select(
                    'v.EmployeeID',
                    'v.vehicle_type',
                    'v.registration_no',
                    'v.model_name',
                    'v.brand'
                )
                ->join($hrimsDb.'.hrm_employee_general as eg', 'v.EmployeeID', '=', 'eg.EmployeeID')
                ->whereIn('v.EmployeeID', $subquery);

            if ($vehicleType) {
                $query->where('v.vehicle_type', $vehicleType);
            }

            // Apply 10-level organogram filters
            $filters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'eg.EmployeeID', $hrimsDb);

            $vehicles = $query->orderBy('v.EmployeeID')->get();

            // Format response
            $groupedVehicles = $vehicles->groupBy('EmployeeID')->map(function ($employeeVehicles, $employeeId) use ($hrimsConnection, $employeeSearch) {
                // Get employee details
                $employee = DB::connection($hrimsConnection)
                    ->table('hrm_employee')
                    ->select('EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname')
                    ->where('EmployeeID', $employeeId)
                    ->first();

                $employeeName = $employee ? trim($employee->Fname.' '.$employee->Sname.' '.$employee->Lname) : '';
                $employeeCode = $employee ? $employee->EmpCode : '';

                // Apply search filter
                if ($employeeSearch && stripos($employeeCode, $employeeSearch) === false && stripos($employeeName, $employeeSearch) === false) {
                    return null;
                }

                return [
                    'employee_id' => $employeeId,
                    'employee_code' => $employeeCode,
                    'employee_name' => $employeeName,
                    'total_vehicles' => $employeeVehicles->count(),
                    'vehicles' => $employeeVehicles->map(function ($vehicle) {
                        return [
                            'vehicle_type' => $vehicle->vehicle_type,
                            'registration_no' => $vehicle->registration_no,
                            'model_name' => $vehicle->model_name,
                            'brand' => $vehicle->brand,
                        ];
                    }),
                ];
            })->filter()->values();

            return response()->json([
                'status' => 'success',
                'data' => $groupedVehicles,
                'total_employees' => $groupedVehicles->count(),
                'total_vehicle_records' => $vehicles->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getEmployeesWithMultipleVehicles: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch employees with multiple vehicles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dailyActivity()
    {
        $company_id = session('company_id', 1);
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        return view('admin.daily_activity', array_merge($organogramOptions, ['organogramOptions' => $organogramOptions]));
    }

    /**
     * Which fill tables the participant export columns can join (Tracker #35).
     * g7 participant_* columns exist only after migration 2026_11_05_000001.
     */
    private function participantExportTables($yearId, array $columns): array
    {
        $result = ['g1' => false, 'g7_names' => false];
        if (empty($yearId) || ! array_intersect(['participant_pax', 'participants_employee', 'participants_other', 'participants_activity'], $columns)) {
            return $result;
        }
        $conn = session('expense_connection') ?: 'expense';
        try {
            $schema = \Illuminate\Support\Facades\Schema::connection($conn);
            $result['g1'] = (bool) array_intersect(['participant_pax', 'participants_employee', 'participants_other'], $columns)
                && $schema->hasTable("y{$yearId}_g1_expensefilldata");
            $result['g7_names'] = in_array('participants_activity', $columns)
                && $schema->hasColumn("y{$yearId}_g7_expensefilldata", 'participant_names');
        } catch (\Throwable $e) {
            // leave false: columns export as blank
        }

        return $result;
    }

    private function buildClaimQuery(array $filters, string $table, bool $forCount = false, $columns = null, string $sortColumn = 'ExpId', string $sortDir = 'DESC')
    {
        $year_id = session('year_id');
        $columns = is_array($columns) ? $columns : [];
        $originalColumnsEmpty = empty($columns);
        if (! empty($filters['claim_filter_type']) && $filters['claim_filter_type'] == 'access_running' && ! $originalColumnsEmpty) {
            $requiredColumns = ['exp_id', 'emp_code', 'bill_date', 'TotKm', 'cumulative_km'];
            foreach ($requiredColumns as $reqCol) {
                if (! in_array($reqCol, $columns)) {
                    $columns[] = $reqCol;
                }
            }
        }
        $query = DB::connection(session('expense_connection'))->table("{$table}");
        if ($forCount) {
            $query->select("{$table}.ExpId");
        } elseif (! empty($columns)) {
            $selects = [];
            if (in_array('exp_id', $columns)) {
                $selects[] = "{$table}.ExpId as ExpId";
            }
            $selects[] = DB::raw("MAX({$table}.ClaimId) as ClaimId");
            if (in_array('claim_type', $columns)) {
                $selects[] = DB::raw('MAX(claimtype.ClaimName) as claim_type_name');
            }
            if (in_array('claim_status', $columns)) {
                $selects[] = DB::raw("MAX({$table}.ClaimStatus) as ClaimStatus");
            }
            if (in_array('emp_name', $columns)) {
                $selects[] = DB::raw("MAX(COALESCE(NULLIF(TRIM(CONCAT(COALESCE(hrims.hrm_employee.Fname, ''), ' ', COALESCE(hrims.hrm_employee.Sname, ''), ' ', COALESCE(hrims.hrm_employee.Lname, ''))), ''), cr_user.name)) as employee_name");
            }
            if (in_array('emp_status', $columns)) {
                $selects[] = DB::raw("MAX(CASE 
                    WHEN hrims.hrm_employee.DateOfSepration IS NOT NULL 
                         AND hrims.hrm_employee.DateOfSepration != '0000-00-00' 
                         AND hrims.hrm_employee.DateOfSepration != '1970-01-01' 
                         AND hrims.hrm_employee.DateOfSepration != '1971-01-01'
                         AND hrims.hrm_employee.DateOfSepration > '2000-01-01'
                         AND hrims.hrm_employee.DateOfSepration < CURDATE() THEN 'Deactive'
                    WHEN (hrims.hrm_employee.DateOfSepration IS NOT NULL 
                         AND hrims.hrm_employee.DateOfSepration != '0000-00-00' 
                         AND hrims.hrm_employee.DateOfSepration != '1970-01-01' 
                         AND hrims.hrm_employee.DateOfSepration != '1971-01-01'
                         AND hrims.hrm_employee.DateOfSepration > '2000-01-01'
                         AND hrims.hrm_employee.DateOfSepration >= CURDATE())
                         OR (hrims.hrm_employee.DateOfResignation IS NOT NULL 
                         AND hrims.hrm_employee.DateOfResignation != '0000-00-00'
                         AND hrims.hrm_employee.DateOfResignation != '1970-01-01'
                         AND hrims.hrm_employee.DateOfResignation != '1971-01-01') THEN 'Resigned'
                    WHEN hrims.hrm_employee.EmpStatus = 'A' THEN 'Active'
                    WHEN hrims.hrm_employee.EmpStatus = 'D' THEN 'Deactive'
                    ELSE COALESCE(hrims.hrm_employee.EmpStatus, 'Active')
                END) as emp_status");
            }
            if (in_array('emp_code', $columns)) {
                $selects[] = DB::raw("MAX(COALESCE(NULLIF(hrims.hrm_employee.EmpCode, ''), cr_user.employee_id)) as employee_code");
            }
            if (in_array('month', $columns)) {
                $selects[] = DB::raw("MAX({$table}.ClaimMonth) as ClaimMonth");
            }
            if (in_array('upload_date', $columns)) {
                $selects[] = DB::raw("MAX({$table}.CrDate) as CrDate");
            }
            if (in_array('bill_date', $columns)) {
                $selects[] = DB::raw("MAX({$table}.BillDate) as BillDate");
            }
            if (in_array('function', $columns)) {
                $selects[] = DB::raw('MAX(hrims.core_functions.function_name) as function_name');
            }
            if (in_array('vertical', $columns)) {
                $selects[] = DB::raw('MAX(hrims.core_verticals.vertical_name) as vertical_name');
            }
            if (in_array('department', $columns)) {
                $selects[] = DB::raw('MAX(hrims.core_departments.department_name) as department_name');
            }
            if (in_array('sub_department', $columns)) {
                $selects[] = DB::raw('MAX(hrims.core_sub_department_master.sub_department_name) as sub_department_name');
            }
            if (in_array('grade', $columns)) {
                $selects[] = DB::raw('MAX(hrims.core_grades.grade_name) as grade');
            }
            if (in_array('policy', $columns)) {
                $selects[] = DB::raw('MAX(hrims.hrm_master_eligibility_policy.PolicyName) as policy_name');
            }
            if (in_array('vehicle_type', $columns)) {
                $selects[] = DB::raw('MAX(hrims.hrm_employee_eligibility.VehicleType) as vehicle_type');
            }
            if (in_array('odomtr_opening', $columns)) {
                $selects[] = DB::raw("MAX({$table}.odomtr_opening) as odomtr_opening");
            }
            if (in_array('odomtr_closing', $columns)) {
                $selects[] = DB::raw("MAX({$table}.odomtr_closing) as odomtr_closing");
            }
            if (in_array('TotKm', $columns)) {
                $selects[] = DB::raw("MAX({$table}.TotKm) as TotKm");
            }
            if (in_array('cumulative_km', $columns)) {
                $selects[] = DB::raw('0 as cumulative_km');
            }
            if (in_array('WType', $columns)) {
                $selects[] = DB::raw("MAX({$table}.WType) as WType");
            }
            if (in_array('RatePerKM', $columns)) {
                $selects[] = DB::raw("MAX({$table}.RatePerKM) as RatePerKM");
            }
            if (in_array('FilledAmt', $columns)) {
                $selects[] = DB::raw("MAX({$table}.FilledTAmt) as FilledTAmt");
            }
            if (in_array('FilledDate', $columns)) {
                $selects[] = DB::raw("MAX({$table}.FilledDate) as FilledDate");
            }
            if (in_array('FilledByName', $columns)) {
                $query->leftJoin('users as filled_user', 'filled_user.id', '=', "{$table}.FilledBy");
                $query->leftJoin('hrims.hrm_employee as filled_emp', function ($join) use ($table) {
                    $join->on('filled_emp.EmployeeID', '=', DB::raw("CASE WHEN {$table}.FilledBy = {$table}.CrBy THEN {$table}.FilledBy ELSE filled_user.employee_id END"));
                });
                $selects[] = DB::raw("MAX(COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(filled_emp.Fname, ''), ' ', COALESCE(filled_emp.Sname, ''), ' ', COALESCE(filled_emp.Lname, ''))), ''),
                    CASE 
                        WHEN filled_user.name IS NOT NULL AND filled_user.employee_id IS NOT NULL AND filled_user.employee_id != ''
                            THEN CONCAT(filled_user.name, ' (', filled_user.employee_id, ')')
                        WHEN filled_user.name IS NOT NULL 
                            THEN filled_user.name
                        ELSE NULL
                    END
                )) AS FilledByName");
            }
            if (in_array('VerifyAmt', $columns)) {
                $selects[] = DB::raw("MAX({$table}.VerifyTAmt) as VerifyTAmt");
            }
            if (in_array('VerifyTRemark', $columns)) {
                $selects[] = DB::raw("MAX({$table}.VerifyTRemark) as VerifyTRemark");
            }
            if (in_array('VerifyDate', $columns)) {
                $selects[] = DB::raw("MAX({$table}.VerifyDate) as VerifyDate");
            }
            if (in_array('VerifyByName', $columns)) {
                $query->leftJoin('hrims.hrm_employee as verify_emp', 'verify_emp.EmployeeID', '=', "{$table}.VerifyBy");
                $selects[] = DB::raw("MAX(CONCAT(verify_emp.Fname, ' ', COALESCE(verify_emp.Sname, ''), ' ', verify_emp.Lname)) AS VerifyByName");
            }
            if (in_array('ApprAmt', $columns)) {
                $selects[] = DB::raw("MAX({$table}.ApprTAmt) as ApprTAmt");
            }
            if (in_array('ApprTRemark', $columns)) {
                $selects[] = DB::raw("MAX({$table}.ApprTRemark) as ApprTRemark");
            }
            if (in_array('ApprDate', $columns)) {
                $selects[] = DB::raw("MAX({$table}.ApprDate) as ApprDate");
            }
            if (in_array('ApprByName', $columns)) {
                $query->leftJoin('hrims.hrm_employee as appr_emp', 'appr_emp.EmployeeID', '=', "{$table}.ApprBy");
                $selects[] = DB::raw("MAX(CONCAT(appr_emp.Fname, ' ', COALESCE(appr_emp.Sname, ''), ' ', appr_emp.Lname)) AS ApprByName");
            }
            if (in_array('FinancedTAmt', $columns)) {
                $selects[] = DB::raw("MAX({$table}.FinancedTAmt) as FinancedTAmt");
            }
            if (in_array('FinancedTRemark', $columns)) {
                $selects[] = DB::raw("MAX({$table}.FinancedTRemark) as FinancedTRemark");
            }
            if (in_array('FinancedDate', $columns)) {
                $selects[] = DB::raw("MAX({$table}.FinancedDate) as FinancedDate");
            }
            if (in_array('FinancedByName', $columns)) {
                $query->leftJoin('hrims.hrm_employee as finance_emp', 'finance_emp.EmployeeID', '=', "{$table}.FinancedBy");
                $selects[] = DB::raw("MAX(CONCAT(finance_emp.Fname, ' ', COALESCE(finance_emp.Sname, ''), ' ', finance_emp.Lname)) AS FinancedByName");
            }
            if (in_array('backdate_odometer_status', $columns)) {
                $selects[] = DB::raw("MAX({$table}.Backdate_Odometer_Status) as backdate_odometer_status");
            }
            if (in_array('backdate_odometer_by', $columns)) {
                $query->leftJoin('hrims.hrm_employee as back_emp', "{$table}.Backdate_Odometer_By", '=', 'back_emp.EmployeeID');
                $selects[] = DB::raw("MAX(CONCAT(back_emp.Fname, ' ', COALESCE(back_emp.Sname, ''), ' ', back_emp.Lname)) AS backdate_odometer_by");
            }
            if (in_array('backdate_odometer_date', $columns)) {
                $selects[] = DB::raw("MAX({$table}.Backdate_Odometer_Date) as backdate_odometer_date");
            }
            if (in_array('access_running_status', $columns)) {
                $selects[] = DB::raw("MAX({$table}.App_Odometer) as access_running_status");
            }
            if (in_array('access_running_by', $columns)) {
                $query->leftJoin('hrims.hrm_employee as access_emp', "{$table}.App_Odometer_By", '=', 'access_emp.EmployeeID');
                $selects[] = DB::raw("MAX(CONCAT(access_emp.Fname, ' ', COALESCE(access_emp.Sname, ''), ' ', access_emp.Lname)) AS access_running_by");
            }
            if (in_array('access_running_date', $columns)) {
                $selects[] = DB::raw("MAX({$table}.App_Odometer_Date) as access_running_date");
            }
            if (in_array('activity_category', $columns)) {
                $selects[] = DB::raw('MAX(adv_activity_categories.category_name) as activity_category');
            }
            if (in_array('activity_type', $columns)) {
                $selects[] = DB::raw('MAX(adv_activity_types.type_name) as activity_type');
            }
            if (in_array('traill_no', $columns)) {
                $selects[] = DB::raw("MAX({$table}.traill_no) as traill_no");
            }
            $hasG7Table = false;
            if (in_array('crop', $columns) || in_array('variety', $columns)) {
                $conn = session('expense_connection') ?: 'expense';
                $g7Table = "y{$year_id}_g7_expensefilldata";
                try {
                    $hasG7Table = !empty($year_id) && \Illuminate\Support\Facades\Schema::connection($conn)->hasTable($g7Table);
                } catch (\Throwable $e) {
                    $hasG7Table = false;
                }
            }
            if (in_array('crop', $columns)) {
                $selects[] = $hasG7Table ? DB::raw('GROUP_CONCAT(DISTINCT core_crops.crop_name) as crop') : DB::raw("'' as crop");
            }
            if (in_array('variety', $columns)) {
                $selects[] = $hasG7Table ? DB::raw('GROUP_CONCAT(DISTINCT core_varieties.variety_name) as variety') : DB::raw("'' as variety");
            }
            // Tracker #35: participant columns for the summary sheet (g1 = meal / lodging / team meeting, g7 = activity)
            $participantTables = $this->participantExportTables($year_id, $columns);
            if (in_array('participant_pax', $columns)) {
                $selects[] = $participantTables['g1'] ? DB::raw('MAX(g1p.NoOfPAX) as participant_pax') : DB::raw("'' as participant_pax");
            }
            if (in_array('participants_employee', $columns)) {
                $selects[] = $participantTables['g1'] ? DB::raw('MAX(g1p.PaxDetails) as participants_employee') : DB::raw("'' as participants_employee");
            }
            if (in_array('participants_other', $columns)) {
                $selects[] = $participantTables['g1'] ? DB::raw('MAX(g1p.PaxDetails_Other) as participants_other') : DB::raw("'' as participants_other");
            }
            if (in_array('participants_activity', $columns)) {
                $selects[] = $participantTables['g7_names']
                    ? DB::raw("MAX(CONCAT_WS(' | ', NULLIF(g7p.participant_department, ''), IF(g7p.participant_count > 0, CONCAT(g7p.participant_count, ' pax'), NULL), NULLIF(g7p.participant_names, ''))) as participants_activity")
                    : DB::raw("'' as participants_activity");
            }
            if (empty($selects)) {
                $selects[] = "{$table}.ExpId as ExpId";
            }
            $query->select($selects);
        } else {
            $query->select([
                "{$table}.ExpId as ExpId",
                DB::raw("MAX({$table}.CrBy) as CrBy"),
                DB::raw('MAX(claimtype.ClaimName) as claim_type_name'),
                DB::raw("MAX(COALESCE(NULLIF(TRIM(CONCAT(hrims.hrm_employee.EmpCode, ' - ', hrims.hrm_employee.Fname, ' ', COALESCE(hrims.hrm_employee.Sname, ''), ' ', hrims.hrm_employee.Lname)), ''), CASE WHEN cr_user.name IS NOT NULL THEN CONCAT(COALESCE(cr_user.employee_id, 'GUEST'), ' - ', cr_user.name) ELSE NULL END)) as employee_name"),
                DB::raw("MAX(COALESCE(NULLIF(hrims.hrm_employee.EmpCode, ''), cr_user.employee_id)) as employee_code"),
                DB::raw("MAX({$table}.ClaimMonth) as ClaimMonth"),
                DB::raw("MAX({$table}.CrDate) as CrDate"),
                DB::raw("MAX({$table}.BillDate) as BillDate"),
                DB::raw("MAX({$table}.FilledTAmt) as FilledTAmt"),
                DB::raw("MAX({$table}.ClaimAtStep) as ClaimAtStep"),
                DB::raw("MAX({$table}.ClaimStatus) as ClaimStatus"),
                DB::raw("MAX({$table}.ClaimId) as ClaimId"),
            ]);
        }
        $query->leftJoin('claimtype', "{$table}.ClaimId", '=', 'claimtype.ClaimId');
        $query->leftJoin('hrims.hrm_employee', "{$table}.CrBy", '=', 'hrims.hrm_employee.EmployeeID');
        $query->leftJoin('users as cr_user', "{$table}.CrBy", '=', 'cr_user.id');
        if (! empty($filters['function_ids']) || ! empty($filters['vertical_ids']) || ! empty($filters['department_ids']) || ! empty($filters['sub_department_ids']) || ! empty($filters['bu_ids']) || ! empty($filters['zone_ids']) || ! empty($filters['region_ids']) || ! empty($filters['territory_ids']) || ! empty($filters['hq_ids']) || in_array('function', $columns) || in_array('grade', $columns) || in_array('vertical', $columns) || in_array('department', $columns)) {
            $query->leftJoin('hrims.hrm_employee_general', 'hrims.hrm_employee.EmployeeID', '=', 'hrims.hrm_employee_general.EmployeeID');
            if (in_array('function', $columns)) {
                $query->leftJoin('hrims.core_functions', 'hrims.core_functions.id', '=', 'hrims.hrm_employee_general.EmpFunction');
            }
            if (in_array('vertical', $columns)) {
                $query->leftJoin('hrims.core_verticals', 'hrims.core_verticals.id', '=', 'hrims.hrm_employee_general.EmpVertical');
            }
            if (in_array('department', $columns)) {
                $query->leftJoin('hrims.core_departments', 'hrims.core_departments.id', '=', 'hrims.hrm_employee_general.DepartmentId');
            }
            if (in_array('sub_department', $columns)) {
                $query->leftJoin('hrims.core_sub_department_master', 'hrims.core_sub_department_master.id', '=', 'hrims.hrm_employee_general.SubDepartmentId');
            }
            if (in_array('grade', $columns)) {
                $query->leftJoin('hrims.core_grades', 'hrims.core_grades.id', '=', 'hrims.hrm_employee_general.GradeId');
            }
            if (! empty($filters['function_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.EmpFunction', $filters['function_ids']);
            }
            if (! empty($filters['vertical_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.EmpVertical', $filters['vertical_ids']);
            }
            if (! empty($filters['department_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.DepartmentId', $filters['department_ids']);
            }
            if (! empty($filters['sub_department_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.SubDepartmentId', $filters['sub_department_ids'] ?? []);
            }
            if (! empty($filters['bu_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.BUId', $filters['bu_ids']);
            }
            if (! empty($filters['zone_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.ZoneId', $filters['zone_ids']);
            }
            if (! empty($filters['region_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.RegionId', $filters['region_ids']);
            }
            if (! empty($filters['territory_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.TerrId', $filters['territory_ids']);
            }
            if (! empty($filters['hq_ids'])) {
                $query->whereIn('hrims.hrm_employee_general.HqId', $filters['hq_ids']);
            }
        }
        if (! empty($filters['policy_ids']) || ! empty($filters['vehicle_types']) || in_array('policy', $columns) || in_array('vehicle_type', $columns)) {
            $query->leftJoin('hrims.hrm_employee_eligibility', 'hrims.hrm_employee.EmployeeID', '=', 'hrims.hrm_employee_eligibility.EmployeeID')->where('hrims.hrm_employee_eligibility.Status', 'A');
            if (in_array('policy', $columns)) {
                $query->leftJoin('hrims.hrm_master_eligibility_policy', 'hrims.hrm_master_eligibility_policy.PolicyId', '=', 'hrims.hrm_employee_eligibility.VehiclePolicy');
            }
            if (! empty($filters['policy_ids'])) {
                $query->whereIn('hrims.hrm_employee_eligibility.VehiclePolicy', $filters['policy_ids']);
            }
            if (! empty($filters['vehicle_types'])) {
                $query->whereIn('hrims.hrm_employee_eligibility.VehicleType', $filters['vehicle_types']);
            }
        }
        if (! empty($filters['user_ids'])) {
            $query->where(function ($q) use ($table, $filters) {
                $q->whereIn('hrims.hrm_employee.EmployeeID', $filters['user_ids'])
                  ->orWhereIn("{$table}.CrBy", $filters['user_ids']);
            });
        }
        if (! empty($filters['filed_by_ids'])) {
            $query->where(function ($q) use ($table, $filters) {
                // Direct match on FilledBy (when FilledBy is users.id e.g. guest users or web operators, or EmployeeID when self-filled)
                $q->whereIn("{$table}.FilledBy", $filters['filed_by_ids']);

                // If EmployeeIDs are selected, match web users who have those employee_ids, or match users by id or employee_id
                $q->orWhereIn("{$table}.FilledBy", function ($sub) use ($filters) {
                    $sub->select('id')
                        ->from('users')
                        ->where(function ($uq) use ($filters) {
                            $uq->whereIn('employee_id', $filters['filed_by_ids'])
                               ->orWhereIn('id', $filters['filed_by_ids']);
                        });
                });
            });
        }
        if (! empty($filters['months'])) {
            $query->whereIn("{$table}.ClaimMonth", $filters['months']);
        }
        if (! empty($filters['claim_type_ids'])) {
            if (in_array(7, $filters['claim_type_ids'])) {
                $query->where("{$table}.ClaimId", 7);
                if (! empty($filters['wheeler_type'])) {
                    $query->where("{$table}.WType", $filters['wheeler_type']);
                }
            } else {
                $query->whereIn("{$table}.ClaimId", $filters['claim_type_ids']);
            }
        }
        if (! empty($filters['claim_statuses'])) {
            $statuses = $filters['claim_statuses'];
            // Handle both single value and array (multi-select sends array)
            if (is_array($statuses)) {
                $status = count($statuses) === 1 ? $statuses[0] : $statuses;
            } else {
                $status = $statuses;
            }
            $isType7 = ! empty($filters['claim_type_ids']) && in_array(7, $filters['claim_type_ids']);
            $isBackdateOdometer = ($filters['claim_filter_type'] ?? null) === 'backdate_odometer_submission';
            $query->where(function ($q) use ($status, $isType7, $isBackdateOdometer, $table) {
                if ($isBackdateOdometer) {
                    $q->whereNotNull("{$table}.Backdate_Odometer_Status");
                }
                // If multiple statuses selected, use whereIn directly
                if (is_array($status)) {
                    $q->whereIn("{$table}.ClaimStatus", $status);
                } elseif ($status === 'Submitted') {
                    $q->whereIn("{$table}.ClaimStatus", [
                        'Submitted',
                        'Filled',
                        'Verified',
                        'Approved',
                        'Financed',
                    ]);
                } elseif ($status === 'Filled') {
                    $q->whereIn("{$table}.ClaimStatus", [
                        'Filled',
                        'Verified',
                        'Approved',
                        'Financed',
                    ]);
                    if (! $isBackdateOdometer) {
                        $q->where("{$table}.v_verify", 'Y')->where("{$table}.c_verify", 'Y');
                    }
                } elseif ($status === 'Verified') {
                    $q->whereIn("{$table}.ClaimStatus", [
                        'Verified',
                        'Approved',
                        'Financed',
                    ]);
                    if ($isType7) {
                        $q->where("{$table}.v_verify", 'Y')->where("{$table}.c_verify", 'Y');
                    }
                } elseif ($status === 'Approved') {
                    $q->whereIn("{$table}.ClaimStatus", [
                        'Approved',
                        'Financed',
                    ]);
                    if ($isType7) {
                        $q->where("{$table}.v_verify", 'Y')->where("{$table}.c_verify", 'Y');
                    }
                } elseif ($status === 'Financed') {
                    $q->where("{$table}.ClaimStatus", 'Financed');
                } else {
                    if ($isType7 && ! $isBackdateOdometer) {
                        $q->where("{$table}.v_verify", 'Y')->where("{$table}.c_verify", 'Y');
                    }
                }
            });
        } else {
            $isType7 = ! empty($filters['claim_type_ids']) && in_array(7, $filters['claim_type_ids']);
            $isBackdateOdometer = ($filters['claim_filter_type'] ?? null) === 'backdate_odometer_submission';
            if ($isBackdateOdometer) {
                $query->whereNotNull("{$table}.Backdate_Odometer_Status");
            }
            if ($isType7 && ! $isBackdateOdometer) {
                $query->where("{$table}.v_verify", 'Y')->where("{$table}.c_verify", 'Y');
            }
        }
        if (! empty($filters['claim_filter_type'])) {
            $filterType = $filters['claim_filter_type'];
            if ($filterType == 'deactivate_after_filling' || $filterType == 'deactivated_claims') {
                if ($filterType == 'deactivate_after_filling') {
                    $query->where("{$table}.ClaimAtStep", 1)->where("{$table}.ClaimStatus", 'Deactivate')->where("{$table}.FilledDate", '!=', '0000-00-00')->where("{$table}.FilledBy", '>', 0)->where("{$table}.FilledTAmt", '>', 0)->where("{$table}.ClaimId", '!=', 0);
                } elseif ($filterType == 'deactivated_claims') {
                    $query->where("{$table}.ClaimStatus", 'Deactivate');
                }
            } elseif ($filterType == 'pending_for_punching') {
                $query->where("{$table}.ClaimStatus", '!=', 'Deactivate');
                $query->where(function ($q) use ($table) {
                    $q->where(function ($q2) use ($table) {
                        $q2->where("{$table}.FilledBy", 0);
                    })->orWhereNull("{$table}.FilledBy")->orWhereIn("{$table}.ClaimStatus", [
                        'Draft',
                        'Submitted',
                    ])->orWhereIn("{$table}.FilledDate", [
                        '0000-00-00',
                        '1970-01-01',
                    ]);
                })->where(function ($q) use ($table) {
                    $q->whereNull("{$table}.Backdate_Odometer_Status")->orWhere("{$table}.Backdate_Odometer_Status", '!=', 'R');
                })->where(function ($q) use ($table) {
                    $q->whereNull("{$table}.App_Odometer")->orWhere("{$table}.App_Odometer", '!=', 'R');
                });
            } elseif ($filterType == 'opened_list_only_employee') {
                $mefTable = "y{$year_id}_monthexpensefinal";
                $query
                    ->whereNotIn("{$table}.ClaimStatus", ['Draft', 'Deactivate'])
                    ->whereExists(function ($sub) use ($table, $mefTable) {
                        $sub
                            ->select(DB::raw(1))
                            ->from($mefTable)
                            ->whereColumn("{$mefTable}.EmployeeID", "{$table}.CrBy")
                            ->whereColumn("{$mefTable}.Month", "{$table}.ClaimMonth")
                            ->where("{$mefTable}.Status", 'Open');
                    });
            } elseif ($filterType == 'access_running') {
                $query->where("{$table}.ClaimStatus", '!=', 'Deactivate')->where(function ($q) use ($table) {
                    $q->whereNull("{$table}.Backdate_Odometer_Status")->orWhere("{$table}.Backdate_Odometer_Status", '!=', 'R');
                })->where("{$table}.App_Odometer", '=', 'Y')->where("{$table}.ClaimId", 7)->whereIn("{$table}.WType", [2, 4])->where("{$table}.v_verify", 'Y')->where("{$table}.c_verify", 'Y')->whereExists(function ($subQuery) use ($table) {
                    $subQuery->select(DB::raw(1))->from('odo_approval_rules as oar_filter')->join('hrims.hrm_employee_general as eg_filter', function ($join) use ($table) {
                        $join->on('eg_filter.EmployeeID', '=', "{$table}.CrBy");
                    })->join(DB::raw("(
                            SELECT CrBy, ClaimMonth, SUM(TotKm) as total_km
                            FROM {$table} as km_calc
                            WHERE km_calc.ClaimId = 7
                            AND km_calc.WType IN (2, 4)
                            AND km_calc.v_verify = 'Y'
                            AND km_calc.c_verify = 'Y'
                            AND km_calc.ClaimStatus != 'Deactivate'
                            GROUP BY CrBy, ClaimMonth
                        ) as km_summary"), function ($join) use ($table) {
                        $join->on('km_summary.CrBy', '=', "{$table}.CrBy")->on('km_summary.ClaimMonth', '=', "{$table}.ClaimMonth");
                    })->whereColumn('oar_filter.department_id', 'eg_filter.DepartmentId')->where('oar_filter.is_active', 1)->where('oar_filter.dept_active', 'Y')->where('oar_filter.effective_from', '<=', DB::raw('CURDATE()'))->where(function ($dateQuery) {
                        $dateQuery->whereNull('oar_filter.effective_to')->orWhere('oar_filter.effective_to', '>=', DB::raw('CURDATE()'));
                    })->where(function ($verticalQuery) {
                        $verticalQuery->whereNull('oar_filter.vertical_ids')->orWhere('oar_filter.vertical_ids', '=', '')->orWhereRaw('FIND_IN_SET(eg_filter.EmpVertical, oar_filter.vertical_ids) > 0');
                    })->whereRaw('km_summary.total_km > oar_filter.monthly_km_limit');
                });
            } else {
                $query->whereNotIn("{$table}.ClaimStatus", [
                    'Draft',
                    'Deactivate',
                ]);
            }
        } else {
            $query->whereNotIn("{$table}.ClaimStatus", ['Draft', 'Deactivate']);
        }
        if (! empty($filters['claim_filter_type']) && $filters['claim_filter_type'] == 'expense_sunday_holiday') {
            $query->leftJoin('hrims.hrm_employee_attendance as ho', function ($join) use ($table) {
                $join->on('ho.AttDate', '=', "{$table}.BillDate")->where('ho.AttValue', '=', 'HO')->where('ho.Year', '=', date('Y'));
            });
            $query->where(function ($q) use ($table) {
                $q->whereRaw("DAYOFWEEK({$table}.BillDate) = 1")->orWhereNotNull('ho.AttDate');
            });
        }
        if (! empty($filters['claim_filter_type']) && $filters['claim_filter_type'] == 'bill_date_exception') {
            $yearRange = FinancialYear::where('YearId', $year_id)->select('FromDate', 'Todate')->first();
            // Punching tracker #2 / DATE-01: also catch bills dated after their upload (future-dated)
            // and bills uploaded more than claim_rules.bill_date.late_days after the bill date.
            $lateDays = (int) config('claim_rules.bill_date.late_days', 60);
            $query->where(function ($q) use ($table, $yearRange, $lateDays) {
                $q->whereRaw("MONTH({$table}.BillDate) != {$table}.ClaimMonth")->orWhereRaw("{$table}.BillDate NOT BETWEEN '{$yearRange->FromDate}' AND '{$yearRange->Todate}'")
                    ->orWhereRaw("{$table}.BillDate > DATE({$table}.CrDate)")
                    ->orWhereRaw("DATEDIFF(DATE({$table}.CrDate), {$table}.BillDate) > ?", [$lateDays]);
            });
        } else {
            if (! empty($filters['from_date']) && ! empty($filters['to_date'])) {
                $dateColumn = match ($filters['date_type']) {
                    'billDate' => 'BillDate',
                    'uploadDate' => 'CrDate',
                    'filledDate' => 'FilledDate',
                    default => 'BillDate'
                };
                $query->whereBetween("{$table}.{$dateColumn}", [
                    $filters['from_date'],
                    $filters['to_date'],
                ]);
            }
        }
        if (! empty($filters['exp_id'])) {
            $query->where("{$table}.ExpId", $filters['exp_id']);
        }
        if (in_array('BackdateOdometerBy', $columns)) {
            $query->leftJoin('hrims.hrm_employee as back_emp', "{$table}.Backdate_Odometer_By", '=', 'back_emp.EmployeeID');
            $selects[] = DB::raw("MAX(CONCAT(back_emp.Fname, ' ', COALESCE(back_emp.Sname, ''), ' ', back_emp.Lname)) as Backdate_Odometer_By");
        }
        if (in_array('activity_category', $columns)) {
            $query->leftJoin('adv_activity_names', 'adv_activity_names.activity_name', '=', "{$table}.ClaimId");
            $query->leftJoin('adv_activity_categories', 'adv_activity_categories.id', '=', 'adv_activity_names.category_id');
        }
        if (in_array('activity_type', $columns)) {
            $query->leftJoin('adv_activity_types', 'adv_activity_types.id', '=', "{$table}.activity_type");
        }
        if (in_array('crop', $columns) || in_array('variety', $columns)) {
            $conn = session('expense_connection') ?: 'expense';
            $g7Table = "y{$year_id}_g7_expensefilldata";
            if (\Illuminate\Support\Facades\Schema::connection($conn)->hasTable($g7Table)) {
                $query->leftJoin($g7Table, "{$g7Table}.ExpId", '=', "{$table}.ExpId");
                if (in_array('crop', $columns)) {
                    $query->leftJoin('hrims.core_crops', function ($join) use ($g7Table) {
                        $join->on(DB::raw("FIND_IN_SET(core_crops.id, {$g7Table}.crop_list)"), '>', DB::raw('0'));
                    });
                }
                if (in_array('variety', $columns)) {
                    $query->leftJoin('hrims.core_varieties', function ($join) use ($g7Table) {
                        $join->on(DB::raw("FIND_IN_SET(core_varieties.id, {$g7Table}.variety_list)"), '>', DB::raw('0'));
                    });
                }
            }
        }
        $participantTables = $this->participantExportTables($year_id, $columns);
        if ($participantTables['g1']) {
            $query->leftJoin("y{$year_id}_g1_expensefilldata as g1p", 'g1p.ExpId', '=', "{$table}.ExpId");
        }
        if ($participantTables['g7_names']) {
            $query->leftJoin("y{$year_id}_g7_expensefilldata as g7p", 'g7p.ExpId', '=', "{$table}.ExpId");
        }
        if (! empty($filters['claim_filter_type']) && $filters['claim_filter_type'] == 'access_running') {
            $query->leftJoin('hrims.hrm_employee as emp_order', "{$table}.CrBy", '=', 'emp_order.EmployeeID');

            return $query->groupBy("{$table}.ExpId")->orderBy('emp_order.EmpCode', 'ASC')->orderBy("{$table}.BillDate", 'ASC');
        }

        // For raw/aggregated expressions, direction must be embedded since orderBy()
        // ignores the $direction argument when passed a DB::raw() value.
        // Note: when called from filterClaims(), yajra DataTables handles ordering via
        // ->orderColumn() mappings, so this only applies to the export path.
        $sortExpressions = [
            'claim_type_name' => "MAX(claimtype.ClaimName) {$sortDir}",
            'employee_name'   => "MAX(CONCAT(hrims.hrm_employee.Fname, ' ', COALESCE(hrims.hrm_employee.Sname, ''), ' ', hrims.hrm_employee.Lname)) {$sortDir}",
            'ClaimMonth'      => "MAX({$table}.ClaimMonth) {$sortDir}",
            'CrDate'          => "MAX({$table}.CrDate) {$sortDir}",
            'BillDate'        => "MAX({$table}.BillDate) {$sortDir}",
            'FilledTAmt'      => "MAX({$table}.FilledTAmt) {$sortDir}",
            'ClaimStatus'     => "MAX({$table}.ClaimStatus) {$sortDir}",
        ];

        $query->groupBy("{$table}.ExpId");

        if (isset($sortExpressions[$sortColumn])) {
            return $query->orderByRaw($sortExpressions[$sortColumn]);
        }

        return $query->orderBy("{$table}.ExpId", $sortDir);
    }

    public function export(Request $request)
    {
        ini_set('max_execution_time', 90000);
        ini_set('memory_limit', '5120M');
        $filters = [
            'function_ids' => $request->input('function_ids', []),
            'vertical_ids' => $request->input('vertical_ids', []),
            'department_ids' => $request->input('department_ids', []),
            'sub_department_ids' => $request->input('sub_department_ids', []),
            'bu_ids' => $request->input('bu_ids', []),
            'zone_ids' => $request->input('zone_ids', []),
            'region_ids' => $request->input('region_ids', []),
            'territory_ids' => $request->input('territory_ids', []),
            'hq_ids' => $request->input('hq_ids', []),
            'user_ids' => $request->input('user_ids', []),
            'filed_by_ids' => $request->input('filed_by_ids', []),
            'months' => $request->input('months', []),
            'claim_type_ids' => $request->input('claim_type_ids', []),
            'claim_statuses' => $request->input('claim_statuses'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'date_type' => $request->input('date_type', 'billDate'),
            'policy_ids' => $request->input('policy_ids', []),
            'vehicle_types' => $request->input('vehicle_types', []),
            'wheeler_type' => $request->input('wheeler_type'),
            'claim_filter_type' => $request->input('claim_filter_type'),
        ];
        $year_id = session('year_id');
        $columns = $request->input('columns', []);
        $reportType = $request->input('reportType', 'general');
        $protectSheets = $request->boolean('protectSheets', false);
        $verifyAmtVersions = $request->input('verify_amt_versions', ['current']); // array: ['current'], ['previous'], or ['current', 'previous']
        $apprAmtVersions   = $request->input('appr_amt_versions', ['current']);   // array: ['current'], ['previous'], or ['current', 'previous']
        $table = ExpenseClaim::tableName($year_id);
        if (empty($columns)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select at least one column to export',
            ], 422);
        }
        try {
            if ($reportType === 'delay_tracking_stage_wise') {
                $year_id = session('year_id');
                $userId = $request->input('user_id');
                $month = $request->input('month');
                $protectSheets = $request->boolean('protectSheets', false);
                $export = new \App\Exports\DelayTrackingExport($year_id, $userId, $month, 'Delay Tracking');
                $fileName = sprintf('Delay_Tracking_%s.xlsx', date('Ymd_His'));

                return \Maatwebsite\Excel\Facades\Excel::download($export, $fileName);
            } else {
                if (! in_array('emp_status', $columns) && (in_array('emp_name', $columns) || $reportType === 'head_wise')) {
                    $columns[] = 'emp_status';
                }
                $query = $this->buildClaimQuery($filters, $table, false, $columns);
                if (! $query->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No claims found for the given filters',
                    ], 404);
                }
                $export = match ($reportType) {
                    'month_wise' => new MonthWiseClaimReportExport($query, $filters, $columns, $protectSheets, $table, $verifyAmtVersions, $apprAmtVersions),
                    'department_wise' => new DepartmentWiseClaimReportExport($query, $filters, $columns, $protectSheets, $table, $verifyAmtVersions, $apprAmtVersions),
                    'claim_type_wise' => new ClaimTypeWiseClaimReportExport($query, $filters, $columns, $protectSheets, $table, $verifyAmtVersions, $apprAmtVersions),
                    'head_wise' => new HeadWiseClaimReport($query, $filters, $columns, 'Claims', $protectSheets, $table, $verifyAmtVersions, $apprAmtVersions),
                    default => new ClaimReportExport($query, $filters, $columns, 'Claims', $protectSheets, $table, $verifyAmtVersions, $apprAmtVersions)
                };
                $reportName = match ($reportType) {
                    'month_wise' => 'MonthWise_Claims',
                    'department_wise' => 'DepartmentWise_Claims',
                    'claim_type_wise' => 'ClaimTypeWise_Claims',
                    'head_wise' => 'Headwise_Claim',
                    default => 'General_Claims'
                };
            }
            $fileName = sprintf('%s_%s.xlsx', $reportName, date('Ymd_His'));

            return \Maatwebsite\Excel\Facades\Excel::download($export, $fileName);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Could not export claims: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not export claims: ' . $e->getMessage(),
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    private function monthFinalStage(array $filters, string $table, string $tableDetail, string $reportType)
    {
        $yearId = session('year_id');
        $expenseConnection = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $hrimsDbName = config("database.connections.{$hrimsConnection}.database");
        $query = DB::connection(session('expense_connection'))->table($table);
        if ($reportType === 'running_km') {
            $selectColumns = [
                "$hrimsDbName.hrm_employee.EmployeeId",
                DB::raw("MAX($table.Month) AS Month"),
                DB::raw("MAX($table.DateOfSubmit) AS DateOfSubmit"),
                DB::raw("MAX($table.PostDate) AS PostDate"),
                DB::raw("MAX($table.RecevingDate) AS RecevingDate"),
                DB::raw("MAX($table.Verified_Date) AS Verified_Date"),
                DB::raw("MAX($table.Approved_Date) AS Approved_Date"),
                DB::raw("MAX($table.Finance_Date) AS Finance_Date"),
                DB::raw("MAX($table.Fin_PayDate) AS Fin_PayDate"),
                DB::raw("MAX($table.Status) AS Status"),
                DB::raw("MAX($hrimsDbName.hrm_employee.EmpCode) AS employee_code"),
                DB::raw("MAX($hrimsDbName.hrm_employee_general.DateJoining) AS DateJoining"),
                DB::raw("MAX($hrimsDbName.hrm_employee.DateOfResignation) AS DateOfResignation"),
                DB::raw("MAX($hrimsDbName.hrm_employee.DateOfSepration) AS DateOfSepration"),
                DB::raw("MAX($hrimsDbName.hrm_employee.EmpStatus) AS EmpStatus"),
                DB::raw("MAX(CONCAT($hrimsDbName.hrm_employee.Fname, ' ', COALESCE($hrimsDbName.hrm_employee.Sname, ''), ' ', $hrimsDbName.hrm_employee.Lname)) AS employee_name"),
                DB::raw("MAX(CASE WHEN m1.EmployeeID IS NOT NULL THEN CONCAT(m1.Fname, ' ', COALESCE(m1.Sname, ''), ' ', m1.Lname) END) AS level1_manager_name"),
                DB::raw("MAX(CASE WHEN m2.EmployeeID IS NOT NULL AND m2.EmployeeID != m1.EmployeeID THEN CONCAT(m2.Fname, ' ', COALESCE(m2.Sname, ''), ' ', m2.Lname) ELSE NULL END) AS level2_manager_name"),
                DB::raw("MAX(CASE WHEN m3.EmployeeID IS NOT NULL AND m3.EmployeeID != m2.EmployeeID AND m3.EmployeeID != m1.EmployeeID THEN CONCAT(m3.Fname, ' ', COALESCE(m3.Sname, ''), ' ', m3.Lname) ELSE NULL END) AS level3_manager_name"),
                DB::raw('MAX(CASE WHEN m1.EmployeeID IS NOT NULL THEN m1.EmpCode END) AS level1_manager_code'),
                DB::raw('MAX(CASE WHEN m2.EmployeeID IS NOT NULL AND m2.EmployeeID != m1.EmployeeID THEN m2.EmpCode ELSE NULL END) AS level2_manager_code'),
                DB::raw('MAX(CASE WHEN m3.EmployeeID IS NOT NULL AND m3.EmployeeID != m2.EmployeeID AND m3.EmployeeID != m1.EmployeeID THEN m3.EmpCode ELSE NULL END) AS level3_manager_code'),
                DB::raw('IFNULL(SUM(ec.total_claims), 0) AS total_claims'),
                DB::raw("MAX($hrimsDbName.core_functions.function_name) AS function_name"),
                DB::raw("MAX($hrimsDbName.core_verticals.vertical_name) AS vertical_name"),
                DB::raw("MAX($hrimsDbName.core_departments.department_name) AS department_name"),
                DB::raw("MAX($hrimsDbName.core_sub_department_master.sub_department_name) AS sub_department_name"),
                DB::raw("MAX($hrimsDbName.core_grades.grade_name) AS grade_name"),
                DB::raw("MAX(IFNULL($hrimsDbName.hrm_employee_general.ReportingName, '')) AS reporting_name"),
            ];
            if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
                if ($this->tableExists('hrm_master_eligibility_policy', $hrimsConnection)) {
                    $selectColumns[] = DB::raw("MAX($hrimsDbName.hrm_master_eligibility_policy.PolicyName) AS policy_name");
                } else {
                    $selectColumns[] = DB::raw("MAX('N/A') AS policy_name");
                }
                $selectColumns[] = DB::raw("MAX($hrimsDbName.hrm_employee_eligibility.VehicleType) AS vehicle_type");
            } else {
                $selectColumns[] = DB::raw("MAX('N/A') AS policy_name");
                $selectColumns[] = DB::raw("MAX('N/A') AS vehicle_type");
            }
            $kmColumns = [
                'apr_2w_km',
                'apr_4w_km',
                'may_2w_km',
                'may_4w_km',
                'jun_2w_km',
                'jun_4w_km',
                'jul_2w_km',
                'jul_4w_km',
                'aug_2w_km',
                'aug_4w_km',
                'sep_2w_km',
                'sep_4w_km',
                'oct_2w_km',
                'oct_4w_km',
                'nov_2w_km',
                'nov_4w_km',
                'dec_2w_km',
                'dec_4w_km',
                'jan_2w_km',
                'jan_4w_km',
                'feb_2w_km',
                'feb_4w_km',
                'mar_2w_km',
                'mar_4w_km',
            ];
            foreach ($kmColumns as $col) {
                $selectColumns[] = DB::raw("MAX(runningkm.{$col}) AS {$col}");
            }
        } else {
            $selectColumns = [
                "$table.EmployeeId",
                "$table.Month",
                "$table.DateOfSubmit",
                "$table.PostDate",
                "$table.RecevingDate",
                "$table.Verified_Date",
                "$table.Approved_Date",
                "$table.Finance_Date",
                "$table.Fin_PayDate",
                "$table.Status",
                "$hrimsDbName.hrm_employee.EmpCode AS employee_code",
                "$hrimsDbName.hrm_employee_general.DateJoining",
                "$hrimsDbName.hrm_employee.DateOfResignation",
                "$hrimsDbName.hrm_employee.DateOfSepration",
                "$hrimsDbName.hrm_employee.EmpStatus",
                DB::raw("CONCAT($hrimsDbName.hrm_employee.Fname, ' ', COALESCE($hrimsDbName.hrm_employee.Sname, ''), ' ', $hrimsDbName.hrm_employee.Lname) AS employee_name"),
                DB::raw("CASE WHEN m1.EmployeeID IS NOT NULL THEN CONCAT(m1.Fname, ' ', COALESCE(m1.Sname, ''), ' ', m1.Lname) END AS level1_manager_name"),
                DB::raw("CASE WHEN m2.EmployeeID IS NOT NULL AND m2.EmployeeID != m1.EmployeeID THEN CONCAT(m2.Fname, ' ', COALESCE(m2.Sname, ''), ' ', m2.Lname) ELSE NULL END AS level2_manager_name"),
                DB::raw("CASE WHEN m3.EmployeeID IS NOT NULL AND m3.EmployeeID != m2.EmployeeID AND m3.EmployeeID != m1.EmployeeID THEN CONCAT(m3.Fname, ' ', COALESCE(m3.Sname, ''), ' ', m3.Lname) ELSE NULL END AS level3_manager_name"),
                DB::raw('CASE WHEN m1.EmployeeID IS NOT NULL THEN m1.EmpCode END AS level1_manager_code'),
                DB::raw('CASE WHEN m2.EmployeeID IS NOT NULL AND m2.EmployeeID != m1.EmployeeID THEN m2.EmpCode ELSE NULL END AS level2_manager_code'),
                DB::raw('CASE WHEN m3.EmployeeID IS NOT NULL AND m3.EmployeeID != m2.EmployeeID AND m3.EmployeeID != m1.EmployeeID THEN m3.EmpCode ELSE NULL END AS level3_manager_code'),
                DB::raw('IFNULL(ec.total_claims, 0) AS total_claims'),
                "$hrimsDbName.core_functions.function_name",
                "$hrimsDbName.core_verticals.vertical_name",
                "$hrimsDbName.core_departments.department_name",
                "$hrimsDbName.core_sub_department_master.sub_department_name",
                "$hrimsDbName.core_grades.grade_name",
                DB::raw("IFNULL($hrimsDbName.hrm_employee_general.ReportingName, '') AS reporting_name"),
            ];
            // Add mail columns only for non-VVNR companies
            if (session('company_id') != 11) {
                $selectColumns[] = DB::raw("(SELECT eml.created_at FROM eml_stage_email_logs AS eml WHERE eml.employee_id = $table.EmployeeId AND eml.sent_for_month = CONCAT(YEAR(NOW()), '-', LPAD($table.Month, 2, '0')) ORDER BY eml.created_at DESC LIMIT 1) AS last_mail_date");
                $selectColumns[] = DB::raw("(SELECT CONCAT(eml.notification_type, ' - ', eml.stage) FROM eml_stage_email_logs AS eml WHERE eml.employee_id = $table.EmployeeId AND eml.sent_for_month = CONCAT(YEAR(NOW()), '-', LPAD($table.Month, 2, '0')) ORDER BY eml.created_at DESC LIMIT 1) AS last_mail_subject");
            }
            if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
                if ($this->tableExists('hrm_master_eligibility_policy', $hrimsConnection)) {
                    $selectColumns[] = "$hrimsDbName.hrm_master_eligibility_policy.PolicyName AS policy_name";
                } else {
                    $selectColumns[] = DB::raw("'N/A' AS policy_name");
                }
                $selectColumns[] = "$hrimsDbName.hrm_employee_eligibility.VehicleType AS vehicle_type";
            } else {
                $selectColumns[] = DB::raw("'N/A' AS policy_name");
                $selectColumns[] = DB::raw("'N/A' AS vehicle_type");
            }
        }
        // PAID-01 (9371b16): the paid-rule columns, so ClaimStagesExport's Paid::is($row) sees UTR / no-payment-due months
        foreach (array_diff(\App\Support\Paid::existing(session('expense_connection'), $table), ['Fin_PayDate']) as $paidCol) {
            $selectColumns[] = $reportType === 'running_km' ? DB::raw("MAX($table.$paidCol) AS $paidCol") : "$table.$paidCol";
        }
        $query->select($selectColumns);
        $query->leftJoin("$hrimsDbName.hrm_employee", "$table.EmployeeId", '=', "$hrimsDbName.hrm_employee.EmployeeId")->leftJoin("$hrimsDbName.hrm_employee_general", "$hrimsDbName.hrm_employee.EmployeeID", '=', "$hrimsDbName.hrm_employee_general.EmployeeID")->leftJoin("$hrimsDbName.core_functions", "$hrimsDbName.core_functions.id", '=', "$hrimsDbName.hrm_employee_general.EmpFunction")->leftJoin("$hrimsDbName.core_verticals", "$hrimsDbName.core_verticals.id", '=', "$hrimsDbName.hrm_employee_general.EmpVertical")->leftJoin("$hrimsDbName.core_departments", "$hrimsDbName.core_departments.id", '=', "$hrimsDbName.hrm_employee_general.DepartmentId")->leftJoin("$hrimsDbName.core_sub_department_master", "$hrimsDbName.core_sub_department_master.id", '=', "$hrimsDbName.hrm_employee_general.SubDepartmentId")->leftJoin("$hrimsDbName.core_grades", "$hrimsDbName.core_grades.id", '=', "$hrimsDbName.hrm_employee_general.GradeId")->leftJoin("$hrimsDbName.hrm_employee_general AS g1", "$hrimsDbName.hrm_employee_general.RepEmployeeID", '=', 'g1.EmployeeID')->leftJoin("$hrimsDbName.hrm_employee AS m1", 'g1.EmployeeID', '=', 'm1.EmployeeID')->leftJoin("$hrimsDbName.hrm_employee_general AS g2", 'g1.RepEmployeeID', '=', 'g2.EmployeeID')->leftJoin("$hrimsDbName.hrm_employee AS m2", 'g2.EmployeeID', '=', 'm2.EmployeeID')->leftJoin("$hrimsDbName.hrm_employee_general AS g3", 'g2.RepEmployeeID', '=', 'g3.EmployeeID')->leftJoin("$hrimsDbName.hrm_employee AS m3", 'g3.EmployeeID', '=', 'm3.EmployeeID');
        if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
            $query->leftJoin("$hrimsDbName.hrm_employee_eligibility", function ($join) use ($hrimsDbName) {
                $join->on("$hrimsDbName.hrm_employee.EmployeeID", '=', "$hrimsDbName.hrm_employee_eligibility.EmployeeID")->where("$hrimsDbName.hrm_employee_eligibility.Status", 'A');
            });
            if ($this->tableExists('hrm_master_eligibility_policy', $hrimsConnection)) {
                $query->leftJoin("$hrimsDbName.hrm_master_eligibility_policy", "$hrimsDbName.hrm_master_eligibility_policy.PolicyId", '=', "$hrimsDbName.hrm_employee_eligibility.VehiclePolicy");
            }
        }
        if ($reportType === 'running_km') {
            $query->leftJoin('runningkm', function ($join) use ($yearId, $hrimsDbName) {
                // comid: runningkm also holds comid = 0 duplicates of the same employee/year
                $join->on('runningkm.empid', '=', "$hrimsDbName.hrm_employee.EmployeeId")->where('runningkm.yearid', '=', $yearId)
                    ->where('runningkm.comid', '=', (int) session('company_id', 1));
            });
        }
        if (! empty($filters['months'])) {
            $query->whereIn("$table.Month", $filters['months']);
        }
        if (! empty($filters['function_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.EmpFunction", $filters['function_ids']);
        }
        if (! empty($filters['vertical_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.EmpVertical", $filters['vertical_ids']);
        }
        if (! empty($filters['department_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.DepartmentId", $filters['department_ids']);
        }
        if (! empty($filters['sub_department_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.SubDepartmentId", $filters['sub_department_ids']);
        }
        if (! empty($filters['user_ids'])) {
            $query->whereIn("$table.EmployeeID", $filters['user_ids']);
        }
        if (! empty($filters['policy_ids'])) {
            if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
                $query->whereIn("$hrimsDbName.hrm_employee_eligibility.VehiclePolicy", $filters['policy_ids']);
            }
        }
        if (! empty($filters['bu_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.BUId", $filters['bu_ids']);
        }
        if (! empty($filters['zone_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.ZoneId", $filters['zone_ids']);
        }
        if (! empty($filters['region_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.RegionId", $filters['region_ids']);
        }
        if (! empty($filters['territory_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.TerrId", $filters['territory_ids']);
        }
        if (! empty($filters['hq_ids'])) {
            $query->whereIn("$hrimsDbName.hrm_employee_general.HqId", $filters['hq_ids']);
        }
        if ($tableDetail) {
            $query->leftJoin(DB::raw("(
                SELECT crby, COUNT(*) AS total_claims, ClaimMonth
                FROM {$tableDetail}
                WHERE ClaimStatus != 'Deactivate' AND FilledOkay IN(1, 0)
                GROUP BY crby, ClaimMonth
            ) AS ec"), function ($join) use ($table, $hrimsDbName) {
                $join->on("$hrimsDbName.hrm_employee.EmployeeID", '=', 'ec.crby')->on("$table.Month", '=', 'ec.ClaimMonth');
            });
            $query->whereRaw('IFNULL(ec.total_claims, 0) > 0');
        }
        if ($reportType === 'delay_tracking_stage_wise') {
            $query->where("$table.Status", 'Closed')->where("$table.DateOfSubmit", '!=', '0000-00-00');
        }
        if ($reportType === 'focus_export') {
            $query->where("$table.Status", 'Closed')->where("$table.Fin_AppDate", '!=', '0000-00-00')->where("$table.Fin_AppBy", '>', 0);
        }
        if ($reportType === 'running_km') {
            $query->groupBy("$hrimsDbName.hrm_employee.EmployeeId");
        }
        $query->orderByRaw("$hrimsDbName.hrm_employee.EmployeeID ASC");

        return $query;
    }

    /**
     * Check if a table exists in the database
     */
    private function tableExists(string $tableName, ?string $connection = null): bool
    {
        try {
            if ($connection) {
                $result = DB::connection($connection)->select('SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$tableName]);

                return $result[0]->count > 0;
            }
            $parts = explode('.', $tableName);
            if (count($parts) === 2) {
                $database = $parts[0];
                $table = $parts[1];
                $result = DB::select('SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$database, $table]);

                return $result[0]->count > 0;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function noClaimEmployeeList(array $filters, string $table, string $tableDetail, array $excludeEmployeeIds)
    {
        $selectedMonths = $filters['months'];
        $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $baseQuery = DB::connection($hrimsConnection)->table('hrm_employee')->select([
            'hrm_employee.EmployeeId',
            'hrm_employee.EmpCode AS employee_code',
            'hrm_employee.DateOfSepration',
            'hrm_employee.EmpStatus',
            DB::raw("CONCAT(hrm_employee.Fname, ' ', COALESCE(hrm_employee.Sname, ''), ' ', hrm_employee.Lname) AS employee_name"),
            DB::raw("CASE WHEN m1.EmployeeID IS NOT NULL THEN CONCAT(m1.Fname, ' ', COALESCE(m1.Sname, ''), ' ', m1.Lname) END AS level1_manager_name"),
            DB::raw("CASE WHEN m2.EmployeeID IS NOT NULL AND m2.EmployeeID != m1.EmployeeID THEN CONCAT(m2.Fname, ' ', COALESCE(m2.Sname, ''), ' ', m2.Lname) ELSE NULL END AS level2_manager_name"),
            DB::raw("CASE WHEN m3.EmployeeID IS NOT NULL AND m3.EmployeeID != m2.EmployeeID AND m3.EmployeeID != m1.EmployeeID THEN CONCAT(m3.Fname, ' ', COALESCE(m3.Sname, ''), ' ', m3.Lname) ELSE NULL END AS level3_manager_name"),
            DB::raw('CASE WHEN m1.EmployeeID IS NOT NULL THEN m1.EmpCode END AS level1_manager_code'),
            DB::raw('CASE WHEN m2.EmployeeID IS NOT NULL AND m2.EmployeeID != m1.EmployeeID THEN m2.EmpCode ELSE NULL END AS level2_manager_code'),
            DB::raw('CASE WHEN m3.EmployeeID IS NOT NULL AND m3.EmployeeID != m2.EmployeeID AND m3.EmployeeID != m1.EmployeeID THEN m3.EmpCode ELSE NULL END AS level3_manager_code'),
            'core_functions.function_name',
            'core_verticals.vertical_name',
            'core_departments.department_name',
            'core_sub_department_master.sub_department_name',
            'core_grades.grade_name',
            DB::raw("IFNULL(hrm_employee_general.ReportingName, '') AS reporting_name"),
        ]);
        if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
            if ($this->tableExists('hrm_master_eligibility_policy', $hrimsConnection)) {
                $baseQuery->addSelect('hrm_master_eligibility_policy.PolicyName AS policy_name');
            } else {
                $baseQuery->addSelect(DB::raw("'N/A' AS policy_name"));
            }
            $baseQuery->addSelect('hrm_employee_eligibility.VehicleType AS vehicle_type');
        } else {
            $baseQuery->addSelect(DB::raw("'N/A' AS policy_name"));
            $baseQuery->addSelect(DB::raw("'N/A' AS vehicle_type"));
        }
        $baseQuery->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')->leftJoin('core_functions', 'core_functions.id', '=', 'hrm_employee_general.EmpFunction')->leftJoin('core_verticals', 'core_verticals.id', '=', 'hrm_employee_general.EmpVertical')->leftJoin('core_departments', 'core_departments.id', '=', 'hrm_employee_general.DepartmentId')->leftJoin('core_sub_department_master', 'core_sub_department_master.id', '=', 'hrm_employee_general.SubDepartmentId')->leftJoin('core_grades', 'core_grades.id', '=', 'hrm_employee_general.GradeId');
        $baseQuery->leftJoin('hrm_employee_general AS g1', 'hrm_employee_general.RepEmployeeID', '=', 'g1.EmployeeID')->leftJoin('hrm_employee AS m1', 'g1.EmployeeID', '=', 'm1.EmployeeID')->leftJoin('hrm_employee_general AS g2', 'g1.RepEmployeeID', '=', 'g2.EmployeeID')->leftJoin('hrm_employee AS m2', 'g2.EmployeeID', '=', 'm2.EmployeeID')->leftJoin('hrm_employee_general AS g3', 'g2.RepEmployeeID', '=', 'g3.EmployeeID')->leftJoin('hrm_employee AS m3', 'g3.EmployeeID', '=', 'm3.EmployeeID');
        if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
            $baseQuery->leftJoin('hrm_employee_eligibility', function ($join) {
                $join->on('hrm_employee.EmployeeID', '=', 'hrm_employee_eligibility.EmployeeID')->where('hrm_employee_eligibility.Status', 'A');
            });
            if ($this->tableExists('hrm_master_eligibility_policy', $hrimsConnection)) {
                $baseQuery->leftJoin('hrm_master_eligibility_policy', 'hrm_master_eligibility_policy.PolicyId', '=', 'hrm_employee_eligibility.VehiclePolicy');
            }
        }
        if (! empty($excludeEmployeeIds)) {
            $baseQuery->whereNotIn('hrm_employee.EmployeeID', $excludeEmployeeIds);
        }
        if (! empty($filters['function_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.EmpFunction', $filters['function_ids']);
        }
        if (! empty($filters['vertical_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.EmpVertical', $filters['vertical_ids']);
        }
        if (! empty($filters['department_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.DepartmentId', $filters['department_ids']);
        }
        if (! empty($filters['sub_department_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.SubDepartmentId', $filters['sub_department_ids']);
        }
        if (! empty($filters['user_ids'])) {
            $baseQuery->whereIn('hrm_employee.EmployeeID', $filters['user_ids']);
        }
        if (! empty($filters['policy_ids'])) {
            if ($this->tableExists('hrm_employee_eligibility', $hrimsConnection)) {
                $baseQuery->whereIn('hrm_employee_eligibility.VehiclePolicy', $filters['policy_ids']);
            }
        }
        if (! empty($filters['bu_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.BUId', $filters['bu_ids']);
        }
        if (! empty($filters['zone_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.ZoneId', $filters['zone_ids']);
        }
        if (! empty($filters['region_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.RegionId', $filters['region_ids']);
        }
        if (! empty($filters['territory_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.TerrId', $filters['territory_ids']);
        }
        if (! empty($filters['hq_ids'])) {
            $baseQuery->whereIn('hrm_employee_general.HqId', $filters['hq_ids']);
        }
        if (session('company_id') == 1) {
            $baseQuery->where('hrm_employee.CompanyId', 1);
        }
        if (session('company_id') == 11) {
            $baseQuery->where('hrm_employee.CompanyId', 11)->where('hrm_employee.EmployeeID', '>', 100000);
        }
        $baseQuery->where('hrm_employee.EmpStatus', 'A');
        $baseQuery->orderBy('hrm_employee.EmployeeID', 'ASC');
        $employees = $baseQuery->get();
        $result = collect();
        foreach ($employees as $employee) {
            foreach ($selectedMonths as $month) {
                $row = clone $employee;
                $row->Month = $month;
                $row->DateOfSubmit = '0000-00-00';
                $row->PostDate = '0000-00-00';
                $row->RecevingDate = '0000-00-00';
                $row->Verified_Date = '0000-00-00';
                $row->Approved_Date = '0000-00-00';
                $row->Finance_Date = '0000-00-00';
                $row->Fin_PayDate = '0000-00-00';
                $row->total_claims = 0;
                $result->push($row);
            }
        }

        return $result;
    }

    private function stageExpenseWise(string $tableDetail, array $months, array $employeeIds, array $claimTypeIds = [])
    {
        $yearId = session('year_id');
        $expenseConnection = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $financialYear = FinancialYear::where('YearId', $yearId)->select('FromDate', 'Todate', 'y1', 'y2')->first();
        $query = DB::connection(session('expense_connection'))->table($tableDetail)->select("{$tableDetail}.ClaimMonth", DB::raw("CASE WHEN FilledBy > 0 AND FilledDate != '0000-00-00' THEN FilledTAmt ELSE 0 END AS FilledTAmt"), DB::raw('CASE WHEN VerifyBy > 0  THEN VerifyTAmt ELSE 0 END AS VerifyTAmt'), DB::raw("CASE WHEN ApprBy > 0 AND ApprDate != '0000-00-00' THEN ApprTAmt ELSE 0 END AS ApprTAmt"), DB::raw("CASE WHEN FinancedBy > 0 AND FinancedDate != '0000-00-00' THEN FinancedTAmt ELSE 0 END AS FinancedTAmt"), "{$tableDetail}.CrBy", "{$tableDetail}.ClaimStatus", "{$tableDetail}.BillDate", 'claimtype.ClaimName AS ClaimTypeName')->leftJoin('claimtype', "{$tableDetail}.ClaimId", '=', 'claimtype.ClaimId')->where("{$tableDetail}.BillDate", '!=', '0000-00-00')->whereNotNull("{$tableDetail}.BillDate")->where("{$tableDetail}.ClaimMonth", '!=', 0)->where("{$tableDetail}.FilledOkay", 1)->whereNotIn("{$tableDetail}.ClaimStatus", [
            'Draft',
            'Submitted',
            'Deactivate',
        ]);
        if (! empty($months) && $financialYear) {
            $query->where(function ($q) use ($months, $tableDetail, $financialYear) {
                foreach ($months as $month) {
                    if ($month >= 4) {
                        $year = $financialYear->y1;
                    } else {
                        $year = $financialYear->y2;
                    }
                    $startDate = sprintf('%04d-%02d-01', $year, $month);
                    $endDate = date('Y-m-t', strtotime($startDate));
                    $q->orWhere(function ($sub) use ($tableDetail, $startDate, $endDate) {
                        $sub->whereBetween("{$tableDetail}.BillDate", [
                            $startDate,
                            $endDate,
                        ]);
                    });
                }
            });
        }
        if (! empty($employeeIds)) {
            $query->whereIn("{$tableDetail}.CrBy", $employeeIds);
        }
        if (! empty($claimTypeIds)) {
            $query->whereIn("{$tableDetail}.ClaimId", $claimTypeIds);
        }

        return $query->orderBy("{$tableDetail}.ClaimMonth", 'asc')->get();
    }

    private function cleanStorageUrl(string $path): string
    {
        // FILES-01: exports are private; the link goes through the authorised download route.
        return \App\Support\PrivateExport::url($path);
    }

    /**
     * Generate HTML preview of an exported Excel file using PhpSpreadsheet (Laravel Excel)
     */
    private function generateExcelPreview(string $storagePath): ?array
    {
        try {
            $found = \App\Support\PrivateExport::locate((string) $storagePath);
            if (! $found) {
                return null;
            }
            $fullPath = Storage::disk($found[0])->path($found[1]);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($fullPath);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
            $styles = $writer->generateStyles(true);

            $sheets = [];
            $sheetCount = $spreadsheet->getSheetCount();
            $sheetNames = $spreadsheet->getSheetNames();

            for ($i = 0; $i < $sheetCount; $i++) {
                $writer->setSheetIndex($i);
                $sheets[] = [
                    'name' => $sheetNames[$i],
                    'html' => $writer->generateSheetData(),
                ];
            }

            return [
                'styles' => $styles,
                'sheets' => $sheets,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Excel preview generation error: ' . $e->getMessage());
            return null;
        }
    }

    private function excelExportResponse(string $path): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'file_url' => $this->cleanStorageUrl($path),
            'preview' => $this->generateExcelPreview($path),
        ]);
    }

    /**
     * Endpoint to preview an existing generated Excel file using Laravel Excel / PhpSpreadsheet
     */
    public function previewExcelFile(Request $request): \Illuminate\Http\JsonResponse
    {
        $path = $request->input('path');
        $fileUrl = $request->input('file_url');

        // FILES-01: only a stored export name is accepted (new /exports/download/ or old /storage/temp/ link)
        $path = \App\Support\PrivateExport::pathFrom($path ?: $fileUrl);

        if (empty($path)) {
            return response()->json(['status' => 'error', 'message' => 'Path or file_url is required'], 422);
        }

        $preview = $this->generateExcelPreview($path);
        if (! $preview) {
            return response()->json(['status' => 'error', 'message' => 'Could not generate preview'], 500);
        }

        return response()->json([
            'status' => 'success',
            'preview' => $preview,
        ]);
    }

    public function exportClaimStage(Request $request)
    {
        ini_set('max_execution_time', 10000);
        ini_set('memory_limit', '5120M');
        $yearId = session('year_id');
        $table = MonthExpenseFinal::tableName($yearId);
        $tableDetail = ExpenseClaim::tableName($yearId);
        $rawMonths = array_filter((array) $request->input('monthSelect', []), fn ($m) => is_numeric($m) && $m > 0);

        $filters = [
            'function_ids' => (array) $request->input('functionSelect', []),
            'vertical_ids' => (array) $request->input('verticalSelect', []),
            'department_ids' => (array) $request->input('departmentSelect', []),
            'sub_department_ids' => (array) $request->input('subDepartmentSelect', []),
            'bu_ids' => (array) ($request->input('buSelect') ?: $request->input('bu_ids', [])),
            'zone_ids' => (array) ($request->input('zoneSelect') ?: $request->input('zone_ids', [])),
            'region_ids' => (array) ($request->input('regionSelect') ?: $request->input('region_ids', [])),
            'territory_ids' => (array) ($request->input('territorySelect') ?: $request->input('territory_ids', [])),
            'hq_ids' => (array) ($request->input('hqSelect') ?: $request->input('hq_ids', [])),
            'user_ids' => (array) $request->input('userSelect', []),
            'claim_type_ids' => array_filter((array) $request->input('claimTypeSelect', []), 'is_numeric'),
            'policy_ids' => (array) $request->input('policySelect', []),
            'months' => array_values($rawMonths),
            'column' => (array) $request->input('columnSelect', []),
        ];
        $reportType = $request->input('reportType');
        // Skip month validation for DA In/Out report (uses date range instead)
        if ($reportType !== 'da_in_out_report' && empty($filters['months'])) {
            return response()->json(['status' => 'error', 'message' => 'Please select month'], 422);
        }
        if ($reportType === 'delay_tracking_stage_wise') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $delayCategory = $request->input('delayCategory', 'all');
            $fileName = 'Delay_Tracking_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store(new DelayTrackingExport($query, $delayCategory), $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'claim_stages_report') {
            $stage = $request->input('stage', 'all');
            $baseQuery = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $allRows = $baseQuery->get();
            $employeeIdsWithClaims = $allRows->pluck('EmployeeId')->unique()->toArray();
            $noClaimEmployees = $this->noClaimEmployeeList($filters, $table, $tableDetail, $employeeIdsWithClaims);
            $grouped = $allRows->groupBy('Month');
            $export = new ClaimStagesExport($grouped, $noClaimEmployees, $stage);
            $stageSuffix = $stage !== 'all' ? '_'.str_replace(' ', '_', $stage) : '';
            $fileName = "Claim_Stages_Report{$stageSuffix}_".now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'submission_wise') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $data = $query->get();
            $export = new SubmissionWiseExport($data);
            $fileName = 'Submission_Wise_Report_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'running_km') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $data = $query->get();
            $export = new RunningKmExport($data);
            $fileName = 'Running_KM_Report_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'month_wise_expense') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $employeeData = $query->get();
            $employeeIds = $employeeData->pluck('EmployeeId')->unique()->toArray();
            $expenseData = $this->stageExpenseWise($tableDetail, $filters['months'] ?? [], $employeeIds);
            $selectedColumn = $filters['column'] ?? 'all';
            $export = new MonthWiseExpenseExport($employeeData, $expenseData, true, $selectedColumn);
            $fileName = 'Month_Wise_Expense_Report_Single_Sheet_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'month_wise_expense_multiple_sheet') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $employeeData = $query->get();
            $employeeIds = $employeeData->pluck('EmployeeId')->unique()->toArray();
            $expenseData = $this->stageExpenseWise($tableDetail, $filters['months'] ?? [], $employeeIds);
            $selectedColumn = $filters['column'] ?? 'all';
            $export = new MonthWiseExpenseExport($employeeData, $expenseData, false, $selectedColumn);
            $fileName = 'Month_Wise_Expense_Report_Multiple_Sheet_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'month_claim_type_wise_expense') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $employeeData = $query->get();
            $employeeIds = $employeeData->pluck('EmployeeId')->unique()->toArray();
            $expenseData = $this->stageExpenseWise($tableDetail, $filters['months'] ?? [], $employeeIds, $filters['claim_type_ids'] ?? []);
            $selectedColumn = $filters['column'] ?? 'all';
            $export = new MonthClaimTypeWiseExpenseExport($employeeData, $expenseData, true, $selectedColumn);
            $fileName = 'Month_Claim_Type_Wise_Expense_Report_Single_Sheet_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'month_claim_type_wise_expense_multiple_sheet') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $employeeData = $query->get();
            $employeeIds = $employeeData->pluck('EmployeeId')->unique()->toArray();
            $expenseData = $this->stageExpenseWise($tableDetail, $filters['months'] ?? [], $employeeIds, $filters['claim_type_ids'] ?? []);
            $selectedColumn = $filters['column'] ?? 'all';
            $export = new MonthClaimTypeWiseExpenseExport($employeeData, $expenseData, false, $selectedColumn);
            $fileName = 'Month_Claim_Type_Wise_Expense_Report_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'employee_claim_type_wise_expense') {
            $query = $this->monthFinalStage($filters, $table, $tableDetail, $reportType);
            $employeeData = $query->get()->unique('EmployeeId');
            $employeeIds = $employeeData->pluck('EmployeeId')->toArray();
            $expenseData = $this->stageExpenseWise($tableDetail, $filters['months'] ?? [], $employeeIds, $filters['claim_type_ids'] ?? []);
            $selectedColumn = $filters['column'] ?? 'all';
            $export = new \App\Exports\EmployeeClaimTypeWiseExpenseExport($employeeData, $expenseData, $selectedColumn);
            $fileName = 'Employee_Claim_Type_Wise_Expense_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'focus_export') {
            $expenseTable = "y{$yearId}_expenseclaims";
            $detailTable = "y{$yearId}_expenseclaimsdetails";
            $monthFinalTable = "y{$yearId}_monthexpensefinal";
            $g1Table = "y{$yearId}_g1_expensefilldata";
            $g2Table = "y{$yearId}_g2_expensefilldata";
            $g3Table = "y{$yearId}_g3_expensefilldata";
            $g4Table = "y{$yearId}_g4_expensefilldata";
            $g5Table = "y{$yearId}_g5_expensefilldata";
            $g7Table = "y{$yearId}_g7_expensefilldata";
            $query = $this->focusFinalStage($filters, $expenseTable, $detailTable, $monthFinalTable, $g1Table, $g2Table, $g3Table, $g4Table, $g5Table, $g7Table, false);
            $focusData = $this->reconcileFocusActivityLines($query->get());
            $export = new FocusExport($focusData);
            $fileName = 'Focus_Export_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }
            \App\Support\FocusExportLog::record('claim_focus', $focusData, (array) $filters, $fileName, $path); // FOCUS-LOG-01

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'access_running_km') {
            $yearId = session('year_id');
            $expenseTable = "y{$yearId}_expenseclaims";
            $monthFinalTable = "y{$yearId}_monthexpensefinal";
            $query = $this->getAccessRunningKmData($filters, $expenseTable, $monthFinalTable);
            $data = $query->get();
            $export = new AccessRunningKmExport($data);
            $fileName = 'Access_Running_KM_Report_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }
        if ($reportType === 'da_in_out_report') {
            $company_id = session('company_id', 1);
            $expenseConnection = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
            $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
            $hrimsDbName = config("database.connections.{$hrimsConnection}.database");
            $yearId = session('year_id');
            $expenseTable = "y{$yearId}_expenseclaims";
            $monthFinalTable = "y{$yearId}_monthexpensefinal";
            $dateRange = $request->input('dateRange');
            if (empty($dateRange)) {
                return response()->json(['status' => 'error', 'message' => 'Please select a date range'], 422);
            }
            $dates = explode(' to ', $dateRange);
            $startDate = trim($dates[0] ?? '');
            $endDate = trim($dates[1] ?? $dates[0] ?? '');
            if (! $startDate || ! $endDate) {
                return response()->json(['status' => 'error', 'message' => 'Invalid date range format'], 422);
            }

            $isVvnr = ((int) $company_id === 11);
            $amountColumn = $isVvnr ? 'VerifyTAmt' : 'FinancedTAmt';

            // Subquery for pre-aggregating verified/financed amount on ClaimId 19 and 20
            $subquery = DB::connection($expenseConnection)
                ->table($expenseTable)
                ->select([
                    'CrBy',
                    'ClaimMonth',
                    DB::raw("SUM(COALESCE({$amountColumn}, 0)) AS TotalVerifiedAmount"),
                ])
                ->whereIn('ClaimId', [19, 20])
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('FilledOkay', 1);

            if (! $isVvnr) {
                $subquery->where('FinancedTAmt', '>', 0);
            }

            if (! empty($filters['user_ids'])) {
                $subquery->whereIn('CrBy', $filters['user_ids']);
            }

            $subquery->groupBy('CrBy', 'ClaimMonth');

            // DA-02: the report is by payment date (the date the money went out), not the finance
            // approval date, so a month approved in one month and paid in the next falls in the
            // month it was paid. Months not yet paid are left out.
            $financeDateExpr = "COALESCE(NULLIF(f.Fin_PayDate, '0000-00-00'), NULLIF(f.payment_date, '0000-00-00'))";

            $payDateSelect = $isVvnr
                ? DB::raw('MAX(f.Verified_Date) AS PayDate')
                : DB::raw("MAX({$financeDateExpr}) AS PayDate");

            $query = DB::connection($expenseConnection)
                ->table("{$monthFinalTable} as f")
                ->joinSub($subquery, 'c', function ($join) {
                    $join->on('c.CrBy', '=', 'f.EmployeeID')
                        ->on('c.ClaimMonth', '=', 'f.Month');
                })
                ->leftJoin("{$hrimsDbName}.hrm_employee as e", 'e.EmployeeID', '=', 'f.EmployeeID')
                ->leftJoin("{$hrimsDbName}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin("{$hrimsDbName}.core_departments as cd", 'cd.id', '=', 'eg.DepartmentId')
                ->leftJoin("{$hrimsDbName}.core_designation as cdes", 'cdes.id', '=', 'eg.DesigId')
                ->select([
                    DB::raw("CONCAT_WS(' ', e.fname, e.sname, e.lname) AS EmployeeName"),
                    'e.EmpCode',
                    'e.DateOfResignation',
                    'cd.department_name',
                    'cdes.designation_name',
                    'f.Month AS ClaimMonth',
                    $payDateSelect,
                    DB::raw('MAX(f.payment_date) AS payment_date'),
                    'c.TotalVerifiedAmount',
                ]);

            if ($isVvnr) {
                // For VVNR: monthexpensefinal must have Verified_Amount > 0 and valid Verified_Date within date range
                $query->whereRaw('COALESCE(f.Verified_Amount, 0) > 0')
                    ->whereNotNull('f.Verified_Date')
                    ->whereNotIn('f.Verified_Date', ['0000-00-00', '', '1970-01-01'])
                    ->whereBetween(DB::raw('DATE(f.Verified_Date)'), [$startDate, $endDate]);
            } else {
                // For VNR: monthexpensefinal must have Finance_Amount/Fin_PayAmt > 0 and valid finance/pay date within date range
                $query->whereRaw('(COALESCE(f.Finance_Amount, 0) > 0 OR COALESCE(f.Fin_PayAmt, 0) > 0)')
                    ->whereRaw("{$financeDateExpr} IS NOT NULL")
                    ->whereBetween(DB::raw("DATE({$financeDateExpr})"), [$startDate, $endDate]);
            }

            if (! empty($filters['user_ids'])) {
                $query->whereIn('f.EmployeeID', $filters['user_ids']);
            }
            if (! empty($filters['department_ids'])) {
                $query->whereIn('eg.DepartmentId', $filters['department_ids']);
            }
            if (! empty($filters['sub_department_ids'])) {
                $query->whereIn('eg.SubDepartmentId', $filters['sub_department_ids']);
            }
            if (! empty($filters['function_ids'])) {
                $query->whereIn('eg.EmpFunction', $filters['function_ids']);
            }
            if (! empty($filters['vertical_ids'])) {
                $query->whereIn('eg.EmpVertical', $filters['vertical_ids']);
            }
            if (! empty($filters['policy_ids'])) {
                $query->join("{$hrimsDbName}.hrm_employee_eligibility as elig_filter", function ($join) {
                    $join->on('elig_filter.EmployeeID', '=', 'f.EmployeeID')
                        ->where('elig_filter.Status', 'A');
                });
                $query->whereIn('elig_filter.VehiclePolicy', $filters['policy_ids']);
            }

            $query->groupBy([
                'f.EmployeeID',
                'f.Month',
                'e.fname',
                'e.sname',
                'e.lname',
                'e.EmpCode',
                'e.DateOfResignation',
                'cd.department_name',
                'cdes.designation_name',
                'c.TotalVerifiedAmount',
            ])->havingRaw('c.TotalVerifiedAmount > 0');

            $data = $query->get();
            $export = new DAInOutReportExport($data, $company_id, $dateRange);
            $fileName = 'DA_In_Out_Report_'.now()->format('Ymd_His').'.xlsx';
            $path = 'temp/'.$fileName;
            Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
            if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
                throw new \Exception('File not created');
            }

            return $this->excelExportResponse($path);
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid report type'], 400);
    }

    private function getAccessRunningKmData(array $filters, string $expenseTable, string $monthFinalTable)
    {
        $query = DB::connection(session('expense_connection'))->table("{$expenseTable} as ec")->join("{$monthFinalTable} as m", function ($join) {
            $join->on('ec.CrBy', '=', 'm.EmployeeID')->on('ec.ClaimMonth', '=', 'm.Month');
        })->join('hrims.hrm_employee_general as eg', 'ec.CrBy', '=', 'eg.EmployeeID')->join('hrims.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')->leftJoin('hrims.hrm_employee as hod', 'eg.RepEmployeeID', '=', 'hod.EmployeeID')->leftJoin('hrims.core_verticals as cv', 'cv.id', '=', 'eg.EmpVertical')->leftJoin('odo_approval_rules as oar', function ($join) {
            $join->on('oar.department_id', '=', 'eg.DepartmentId')->where('oar.is_active', '=', 1)->where('oar.dept_active', '=', 'Y')->where('oar.effective_from', '<=', DB::raw('CURDATE()'))->where(function ($query) {
                $query->whereNull('oar.effective_to')->orWhere('oar.effective_to', '>=', DB::raw('CURDATE()'));
            });
        })->leftJoin('hrims.hrm_employee as access_emp', 'ec.App_Odometer_By', '=', 'access_emp.EmployeeID')->select([
            'm.EmployeeID',
            DB::raw("CONCAT_WS(' ', hod.Fname, hod.Sname, hod.Lname) AS HodName"),
            DB::raw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) AS EmployeeName"),
            'emp.EmpCode',
            'cv.vertical_name',
            'ec.WType',
            DB::raw('SUM(ec.TotKm) AS TotalKm'),
            'eg.MobileNo_Vnr',
            'eg.ReportingName',
            'eg.ReportingContactNo',
            'm.Status',
            'm.Odo_Approve_Final_Submit',
            'm.Month',
            'oar.monthly_km_limit',
            'oar.vertical_ids as rule_vertical_ids',
            'oar.auto_approve_if_below_limit',
            DB::raw("MAX(CASE WHEN access_emp.EmployeeID IS NOT NULL THEN CONCAT(access_emp.Fname, ' ', COALESCE(access_emp.Sname, ''), ' ', access_emp.Lname) END) AS access_running_by"),
            DB::raw('MAX(ec.App_Odometer_Date) AS access_running_date'),
        ])->where('emp.EmpStatus', 'A')->where('ec.v_verify', 'Y')->whereIn('ec.WType', [2, 4])->whereNotNull('oar.department_id')->where(function ($query) {
            $query->whereNull('oar.vertical_ids')->orWhere('oar.vertical_ids', '=', '')->orWhereRaw('FIND_IN_SET(eg.EmpVertical, oar.vertical_ids) > 0');
        });
        if (! empty($filters['months'])) {
            $query->whereIn('ec.ClaimMonth', $filters['months']);
        }
        if (! empty($filters['vertical_ids'])) {
            $query->whereIn('eg.EmpVertical', $filters['vertical_ids']);
        }
        if (! empty($filters['function_ids'])) {
            $query->whereIn('eg.EmpFunction', $filters['function_ids']);
        }
        if (! empty($filters['department_ids'])) {
            $query->whereIn('eg.DepartmentId', $filters['department_ids']);
        } else {
            $query->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))->from('odo_approval_rules as oar_exists')->whereColumn('oar_exists.department_id', 'eg.DepartmentId')->where('oar_exists.is_active', 1)->where('oar_exists.dept_active', 'Y')->where('oar_exists.effective_from', '<=', DB::raw('CURDATE()'))->where(function ($dateQuery) {
                    $dateQuery->whereNull('oar_exists.effective_to')->orWhere('oar_exists.effective_to', '>=', DB::raw('CURDATE()'));
                });
            });
        }
        if (! empty($filters['sub_department_ids'])) {
            $query->whereIn('eg.SubDepartmentId', $filters['sub_department_ids']);
        }
        if (! empty($filters['user_ids'])) {
            $query->whereIn('ec.CrBy', $filters['user_ids']);
        }
        if (! empty($filters['policy_ids'])) {
            $query->join('hrims.hrm_employee_eligibility as elig_filter', function ($join) {
                $join->on('elig_filter.EmployeeID', '=', 'ec.CrBy')
                    ->where('elig_filter.Status', 'A');
            });
            $query->whereIn('elig_filter.VehiclePolicy', $filters['policy_ids']);
        }
        $query->groupBy([
            'm.EmployeeID',
            'hod.Fname',
            'hod.Sname',
            'hod.Lname',
            'emp.Fname',
            'emp.Sname',
            'emp.Lname',
            'emp.EmpCode',
            'cv.vertical_name',
            'ec.WType',
            'eg.MobileNo_Vnr',
            'eg.ReportingName',
            'eg.ReportingContactNo',
            'm.Status',
            'm.Odo_Approve_Final_Submit',
            'm.Month',
            'oar.monthly_km_limit',
            'oar.vertical_ids',
            'oar.auto_approve_if_below_limit',
        ])->havingRaw('SUM(ec.TotKm) > oar.monthly_km_limit')->orderBy('m.Month')->orderBy('HodName', 'DESC');

        return $query;
    }

    private function ensureMonthexpenseDocNumbers($yearId, $monthFinalTable, array $recordIds = [], array $filters = [])
    {
        try {
            $connection = DB::connection(session('expense_connection'));

            $query = $connection->table($monthFinalTable)
                ->where('YearId', $yearId)
                ->where(function ($q) {
                    $q->whereNull('DocNo')->orWhere('DocNo', '')->orWhereNull('DocId')->orWhere('DocId', 0);
                });

            if (! empty($recordIds)) {
                $query->whereIn('id', $recordIds);
            } elseif (! empty($filters['user_ids'])) {
                $query->where(function ($q) use ($filters) {
                    foreach ($filters['user_ids'] as $userId => $months) {
                        if (! empty($months)) {
                            $q->orWhere(function ($sub) use ($userId, $months) {
                                $sub->where('EmployeeID', $userId)->whereIn('Month', $months);
                            });
                        }
                    }
                });
            } elseif (! empty($filters['months'])) {
                $query->whereIn('Month', (array) $filters['months']);
            }

            $missingDocs = $query->orderBy('id', 'asc')->get(['id']);

            if ($missingDocs->isNotEmpty()) {
                $maxDocId = (int) $connection->table($monthFinalTable)->max('DocId');
                foreach ($missingDocs as $mDoc) {
                    $maxDocId++;
                    $docNo = str_pad($maxDocId, 4, '0', STR_PAD_LEFT);
                    $connection->table($monthFinalTable)
                        ->where('id', $mDoc->id)
                        ->update([
                            'DocId' => $maxDocId,
                            'DocNo' => $docNo,
                        ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('ensureMonthexpenseDocNumbers error: ' . $e->getMessage());
        }
    }

    /**
     * Activity claims (claim group 7) are exported one line per expense head. The head amount is
     * taken from the g7 pay_/app_/ver_ columns, which are not always updated when finance edits the
     * line, so the lines of a claim could add up to more or less than the claim's FinancedTAmt
     * (FY 2026-27: 97 claims over by Rs 1,52,256, 22 under by Rs 40,065). When the lines do not add
     * up to the claim but the finance-edited detail amounts do, use the detail amounts.
     */
    private function reconcileFocusActivityLines($rows)
    {
        if ((int) session('company_id') === 11) {
            return $rows; // VVNR exports ApprTAmt per claim (Tracker #33), not per head
        }

        foreach ($rows->filter(fn ($r) => (int) ($r->cgId ?? 0) === 7)->groupBy('ExpId') as $lines) {
            if (! isset($lines->first()->ClaimFinancedTAmt)) {
                continue;
            }
            $claimTotal = (float) $lines->first()->ClaimFinancedTAmt;
            $lineTotal = (float) $lines->sum('FinancedTAmt');
            if (abs($lineTotal - $claimTotal) < 0.5) {
                continue;
            }
            if (! $lines->contains(fn ($l) => $l->LineFinanceEditAmount === null)) {
                $editTotal = (float) $lines->sum('LineFinanceEditAmount');
                if (abs($editTotal - $claimTotal) < 0.5) {
                    foreach ($lines as $l) {
                        $l->FinancedTAmt = (float) $l->LineFinanceEditAmount;
                    }
                    continue;
                }
            }
            // FOCUS-FIN-01 (Finance decision 7 Nov 2026): Focus must get the FINANCED amount for every
            // VNR claim. When Finance changed the claim total and the head lines were not edited, the
            // lines are scaled to the financed total (rounded; the rupee left over goes on the largest line).
            if ($claimTotal > 0 && $lineTotal > 0) {
                $factor = $claimTotal / $lineTotal;
                $sum = 0.0;
                foreach ($lines as $l) {
                    $l->FinancedTAmt = round((float) $l->FinancedTAmt * $factor, 2);
                    $sum += $l->FinancedTAmt;
                }
                $big = $lines->sortByDesc('FinancedTAmt')->first();
                $big->FinancedTAmt = round($big->FinancedTAmt + ($claimTotal - $sum), 2);
            } elseif ($claimTotal <= 0) {
                foreach ($lines as $l) {
                    $l->FinancedTAmt = 0.0;
                }
            }
        }

        return $rows;
    }

    /**
     * The g7 head list grew in FY 2026-27 (other_expenses, diesel_for_generator, training, ...);
     * referencing those columns made the Focus export fail with "Unknown column" for FY 2025-26.
     * Replace references to g7 columns that the year's table does not have with NULL.
     */
    private function withExistingG7Columns(string $g7Table, string $sql): string
    {
        try {
            $existing = array_flip(\Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->getColumnListing($g7Table));
        } catch (\Throwable $e) {
            return $sql;
        }
        if (empty($existing)) {
            return $sql;
        }

        return preg_replace_callback('/\bg7\.([A-Za-z0-9_]+)/', function ($m) use ($existing) {
            return isset($existing[$m[1]]) ? $m[0] : 'NULL';
        }, $sql);
    }

    private function focusFinalStage(array $filters, string $expenseTable, string $detailTable, string $monthFinalTable, string $g1Table, string $g2Table, string $g3Table, string $g4Table, string $g5Table, string $g7Table, $isPaymentExport = false, $companyId = null, $yearId = null)
    {
        $company = session('company_id') ?? $companyId;
        $year_id = session('year_id') ?? $yearId;

        // Auto-assign DocId & DocNo if any qualifying records are missing them
        $this->ensureMonthexpenseDocNumbers($year_id, $monthFinalTable, [], $filters);

        $query = DB::connection(session('expense_connection'))
            ->table("{$expenseTable} AS e")
            /* ================= SELECT ================= */
            ->select([
                'e.ClaimId',
                'e.WType',
                'edtl.Title',
                DB::raw('MAX(e.CrBy) as CrBy'),
                /* ✅ FIXED FINANCE AMOUNT
                 * Tracker #33: Focus gets the APPROVED amount, not the verified one.
                 * VVNR (11) exported MAX(e.VerifyTAmt); it now exports MAX(e.ApprTAmt).
                 * VNR / Nursery keep FinancedTAmt (finance-confirmed amount, set from
                 * the approved amount at finance stage), and activity heads keep the
                 * pay_ -> app_ -> ver_ head fallback reconciled by reconcileFocusActivityLines(). */
                DB::raw($this->withExistingG7Columns($g7Table, '
    CASE
        WHEN '.(int) $company.' = 11
            THEN MAX(e.ApprTAmt)
        ELSE
            CASE
                WHEN MAX(c.cgId) = 7
                    THEN MAX(COALESCE(
                        CASE 
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "food" 
                                THEN COALESCE(NULLIF(g7.pay_food, 0), NULLIF(g7.app_food, 0), NULLIF(g7.ver_food, 0), NULLIF(g7.food, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "snacks" 
                                THEN COALESCE(NULLIF(g7.pay_snacks, 0), NULLIF(g7.app_snacks, 0), NULLIF(g7.ver_snacks, 0), NULLIF(g7.snacks, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("conveyance_for_meeting_participants", "conveyance") 
                                THEN COALESCE(NULLIF(g7.pay_conveyance_for_meeting_participants, 0), NULLIF(g7.app_conveyance_for_meeting_participants, 0), NULLIF(g7.ver_conveyance_for_meeting_participants, 0), NULLIF(g7.conveyance_for_meeting_participants, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "business_entertainment" 
                                THEN COALESCE(NULLIF(g7.pay_business_entertainment, 0), NULLIF(g7.app_business_entertainment, 0), NULLIF(g7.ver_business_entertainment, 0), NULLIF(g7.business_entertainment, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "product_list" 
                                THEN COALESCE(NULLIF(g7.pay_product_list, 0), NULLIF(g7.app_product_list, 0), NULLIF(g7.ver_product_list, 0), NULLIF(g7.product_list, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("hrv_employee_team", "hrv_employee___team", "hrv") 
                                THEN COALESCE(NULLIF(g7.pay_hrv_employee_team, 0), NULLIF(g7.app_hrv_employee_team, 0), NULLIF(g7.ver_hrv_employee_team, 0), NULLIF(g7.hrv_employee_team, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "gifts" 
                                THEN COALESCE(NULLIF(g7.pay_gifts, 0), NULLIF(g7.app_gifts, 0), NULLIF(g7.ver_gifts, 0), NULLIF(g7.gifts, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("hall_tent_chair_sound_system_projector", "hall___tent___chair___sound_system___projector") 
                                THEN COALESCE(NULLIF(g7.pay_hall_tent_chair_sound_system_projector, 0), NULLIF(g7.app_hall_tent_chair_sound_system_projector, 0), NULLIF(g7.ver_hall_tent_chair_sound_system_projector, 0), NULLIF(g7.hall_tent_chair_sound_system_projector, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("misc_labour_chuna_rope_etc", "misc__labour__chuna__rope_etc_") 
                                THEN COALESCE(NULLIF(g7.pay_misc_labour_chuna_rope_etc, 0), NULLIF(g7.app_misc_labour_chuna_rope_etc, 0), NULLIF(g7.ver_misc_labour_chuna_rope_etc, 0), NULLIF(g7.misc_labour_chuna_rope_etc, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("merchandise_cost_branding_transport", "merchandise_cost__branding__transport_") 
                                THEN COALESCE(NULLIF(g7.pay_merchandise_cost_branding_transport, 0), NULLIF(g7.app_merchandise_cost_branding_transport, 0), NULLIF(g7.ver_merchandise_cost_branding_transport, 0), NULLIF(g7.merchandise_cost_branding_transport, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "hired_vehicle" 
                                THEN COALESCE(NULLIF(g7.pay_hired_vehicle, 0), NULLIF(g7.app_hired_vehicle, 0), NULLIF(g7.ver_hired_vehicle, 0), NULLIF(g7.hired_vehicle, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("other", "other_expenses") 
                                THEN COALESCE(NULLIF(g7.pay_other, 0), NULLIF(g7.app_other, 0), NULLIF(g7.ver_other, 0), NULLIF(g7.other, 0), NULLIF(g7.pay_other_expenses, 0), NULLIF(g7.app_other_expenses, 0), NULLIF(g7.ver_other_expenses, 0), NULLIF(g7.other_expenses, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "no_of_farmers" 
                                THEN COALESCE(NULLIF(g7.pay_no_of_farmers, 0), NULLIF(g7.app_no_of_farmers, 0), NULLIF(g7.ver_no_of_farmers, 0), NULLIF(g7.no_of_farmers, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("dealerstrade_partners", "dealers_trade_partners") 
                                THEN COALESCE(NULLIF(g7.pay_dealerstrade_partners, 0), NULLIF(g7.app_dealerstrade_partners, 0), NULLIF(g7.ver_dealerstrade_partners, 0), NULLIF(g7.dealerstrade_partners, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "seed_purchase" 
                                THEN COALESCE(NULLIF(g7.pay_seed_purchase, 0), NULLIF(g7.app_seed_purchase, 0), NULLIF(g7.ver_seed_purchase, 0), NULLIF(g7.seed_purchase, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "labour_payment" 
                                THEN COALESCE(NULLIF(g7.pay_labour_payment, 0), NULLIF(g7.app_labour_payment, 0), NULLIF(g7.ver_labour_payment, 0), NULLIF(g7.labour_payment, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("fertilizers_pesticides", "fertilizers__pesticides") 
                                THEN COALESCE(NULLIF(g7.pay_fertilizers_pesticides, 0), NULLIF(g7.app_fertilizers_pesticides, 0), NULLIF(g7.ver_fertilizers_pesticides, 0), NULLIF(g7.fertilizers_pesticides, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) IN ("loading_unloading", "loading___unloading") 
                                THEN COALESCE(NULLIF(g7.pay_loading_unloading, 0), NULLIF(g7.app_loading_unloading, 0), NULLIF(g7.ver_loading_unloading, 0), NULLIF(g7.loading_unloading, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "distribution_charges" 
                                THEN COALESCE(NULLIF(g7.pay_distribution_charges, 0), NULLIF(g7.app_distribution_charges, 0), NULLIF(g7.ver_distribution_charges, 0), NULLIF(g7.distribution_charges, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "godown_rent" 
                                THEN COALESCE(NULLIF(g7.pay_godown_rent, 0), NULLIF(g7.app_godown_rent, 0), NULLIF(g7.ver_godown_rent, 0), NULLIF(g7.godown_rent, 0))
                            WHEN LOWER(REPLACE(REPLACE(REPLACE(COALESCE(edtl.Title, ""), " ", "_"), "&", ""), "/", "_")) = "diesel_for_generator" 
                                THEN COALESCE(NULLIF(g7.pay_diesel_for_generator, 0), NULLIF(g7.app_diesel_for_generator, 0), NULLIF(g7.ver_diesel_for_generator, 0), NULLIF(g7.diesel_for_generator, 0))
                            ELSE NULL
                        END,
                        edtl.FinanceEditAmount,
                        e.FinancedTAmt
                    ))
                ELSE MAX(e.FinancedTAmt)
            END
    END AS FinancedTAmt
')),
                DB::raw('MAX(fy.Year) as Year'),
                DB::raw('MAX(e.ExpId) as ExpId'),
                DB::raw('MAX(c.ClaimName) AS claim_name'),
                DB::raw('MAX(c.focus_code) AS focus_claim_name'),
                DB::raw('MAX(mef.DocNo) AS VOUCHER_NUMBER'),
                DB::raw('MAX(mef.Finance_Date) AS Date'),
                DB::raw('MAX(mef.Fin_AppTime) AS Time'),
                DB::raw('MAX(COALESCE(h.EmpCode_New, h.EmpCode)) AS Account_EC'),
                DB::raw("'120' AS Business_Entity"),
                DB::raw('MAX(ac.category_name) AS sNarration'),
                DB::raw('MAX(e.ClaimMonth) AS ActMonths'),
                DB::raw("
                CASE
                    WHEN MAX(c.cgId) = 7
                        THEN (SELECT GROUP_CONCAT(DISTINCT crops_sub.crop_name SEPARATOR ', ')
                              FROM hrims.core_crops AS crops_sub
                              INNER JOIN {$g7Table} AS g7_sub ON g7_sub.ExpId = e.ExpId
                              WHERE FIND_IN_SET(crops_sub.id, REPLACE(g7_sub.crop_list,' ','')) > 0
                              AND crops_sub.is_active = 1)
                    ELSE MAX(g2.CropDetails)
                END AS Crop
            "),
                DB::raw('MAX(hq.city_village_name) AS Location'),
                DB::raw('MAX(ac.category_name) AS Category'),
                DB::raw('MAX(fcm.focus_name_subl) AS Subledger'),
                DB::raw('MAX(fcm.focus_ledger_code) AS Ledger'),
                DB::raw('MAX(fcm.focus_name) AS Ledger_Name'),
                DB::raw('MAX(atype.type_name) AS Activity_Head'),
                DB::raw('MAX(atype.focus_code) AS Activity_Focus_Head'),
                DB::raw('MAX(fsc.state_code) AS State'),
                DB::raw('MAX(grade.grade_name) AS Grade'),
                DB::raw('MAX(func.function_name) AS Function'),
                DB::raw('MAX(func.function_code) AS Function_Code'),
                DB::raw('MAX(vert.vertical_name) AS Vertical'),
                DB::raw('MAX(vert.vertical_code) AS Vertical_Code'),
                DB::raw('
                MAX(COALESCE(
                    fdv.mapped_department_name,
                    fd.mapped_department_name,
                    dept.department_name
                )) AS department_name
            '),
                DB::raw('MAX(fd.mapped_department_code) AS Department_Code'),
                DB::raw('MAX(subd.sub_department_name) AS Sub_Department'),
                DB::raw('MAX(subd.sub_department_code) AS Sub_Department_Code'),
                DB::raw('MAX(state.state_name) as state_name'),
                DB::raw('MAX(reg.region_name) AS Region'),
                DB::raw('MAX(bu.business_unit_code) AS BU'),
                DB::raw('MAX(reg.region_code) AS Region_Code'),
                DB::raw('MAX(c.cgId) as cgId'),
                DB::raw('MAX(grade.id) AS GradeId'),
                DB::raw('MAX(edtl.FinanceEditAmount) as FinanceEditAmount'),
                DB::raw('MAX(e.FinancedTAmt) AS ClaimFinancedTAmt'),
                // all detail lines of this head (duplicate titles were collapsed by MAX above)
                DB::raw("(SELECT SUM(d2.FinanceEditAmount) FROM {$detailTable} AS d2 WHERE d2.ExpId = e.ExpId AND d2.Title <=> edtl.Title) AS LineFinanceEditAmount"),
                DB::raw('MAX(g1.VehicleType) as VehicleType'),
                DB::raw('MAX(g3.Vegetable) AS veg1'),
                DB::raw('MAX(g4.Vegetable) AS veg2'),
                DB::raw('MAX(g5.CropDetails) AS g5_CropDetails'),
            ])
            /* ================= CORE JOINS ================= */
            ->join('claimtype AS c', 'e.ClaimId', '=', 'c.ClaimId')
            ->leftJoin("{$detailTable} AS edtl", function ($join) {
                $join->on('edtl.ExpId', '=', 'e.ExpId')->whereRaw('c.cgId = 7');
            })
            ->join("{$monthFinalTable} AS mef", function ($join) use ($year_id) {
                $join->on('mef.EmployeeID', '=', 'e.CrBy')
                    ->on('mef.Month', '=', 'e.ClaimMonth')
                    ->where('mef.Status', 'Closed')
                    ->where('mef.YearId', $year_id);
            })
            /* ================= EMPLOYEE & ORG ================= */
            ->leftJoin('hrims.hrm_employee AS h', 'h.EmployeeID', '=', 'e.CrBy')
            ->leftJoin('hrims.hrm_employee_general AS gen', 'gen.EmployeeId', '=', 'e.CrBy')
            ->leftJoin('hrims.core_regions AS reg', 'reg.id', '=', 'gen.RegionId')
            ->leftJoin('hrims.core_business_unit AS bu', 'bu.id', '=', 'gen.BUId')
            ->leftJoin('hrims.core_departments AS dept', 'dept.id', '=', 'gen.DepartmentId')
            ->leftJoin('hrims.core_city_village_by_state AS hq', 'hq.id', '=', 'gen.HqId')
            ->leftJoin('hrims.core_states AS state', 'state.id', '=', 'gen.CostCenter')
            ->leftJoin('hrims.core_grades AS grade', 'grade.id', '=', 'gen.GradeId')
            ->leftJoin('hrims.core_functions AS func', 'func.id', '=', 'gen.EmpFunction')
            ->leftJoin('hrims.core_verticals AS vert', 'vert.id', '=', 'gen.EmpVertical')
            ->leftJoin('hrims.core_sub_department_master AS subd', 'subd.id', '=', 'gen.SubDepartmentId')
            ->leftJoin('financialyear AS fy', 'fy.YearId', '=', 'e.ClaimYearId')
            /* ================= G TABLES ================= */
            ->leftJoin("{$g1Table} AS g1", fn ($j) => $j->on('g1.ExpId', '=', 'e.ExpId')->whereRaw('c.CgId = 1'))
            ->leftJoin("{$g2Table} AS g2", 'g2.ExpId', '=', 'e.ExpId')
            ->leftJoin("{$g3Table} AS g3", fn ($j) => $j->on('g3.ExpId', '=', 'e.ExpId')->whereRaw('c.CgId = 3'))
            ->leftJoin("{$g4Table} AS g4", fn ($j) => $j->on('g4.ExpId', '=', 'e.ExpId')->whereRaw('c.CgId = 4'))
            ->leftJoin("{$g5Table} AS g5", 'g5.ExpId', '=', 'e.ExpId')
            ->leftJoin("{$g7Table} AS g7", fn ($j) => $j->on('g7.ExpId', '=', 'e.ExpId')->whereRaw('c.CgId = 7'))
            /* ================= ACTIVITY ================= */
            ->leftJoin('adv_activity_types AS atype', 'e.activity_type', '=', 'atype.id')
            ->leftJoin('adv_activity_names AS an', 'e.ClaimId', '=', 'an.activity_name')
            ->leftJoin('adv_activity_categories AS ac', 'an.category_id', '=', 'ac.id')
            /* ================= FOCUS MAPPING ================= */
            ->leftJoin('fc_claim_focus_mapping AS fcm', function ($join) {
                $join->on('fcm.claim_id', '=', 'c.ClaimId')->where(function ($q) {
                    $q->whereExists(function ($sq) {
                        $sq->select(DB::raw(1))->from('adv_expense_head_category as ehc')->whereColumn('ehc.id', 'fcm.expense_head_category_id')->whereColumn('ehc.expense_head_name', 'edtl.Title');
                    })->orWhereNull('fcm.expense_head_category_id');
                })->where(function ($q) {
                    $q->whereColumn('fcm.wtype', 'e.WType')->orWhereNull('fcm.wtype');
                })->where('fcm.is_active', 1);
            })
            ->leftJoin('fc_state_code_mapping AS fsc', fn ($j) => $j->on('fsc.state_name', '=', 'state.state_name')->where('fsc.is_active', 1))
            ->leftJoin('fc_department_code_mapping AS fd', fn ($j) => $j->on('fd.original_department_name', '=', 'dept.department_name')->where('fd.is_active', 1))
            ->leftJoin('fc_department_vertical_mapping AS fdv', fn ($j) => $j->on('fdv.base_department', '=', 'dept.department_name')->on('fdv.vertical_name', '=', 'vert.vertical_name')->where('fdv.is_active', 1))
            /* ================= WHERE ================= */
            ->where('e.FilledOkay', 1)
            ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Draft'])
            ->where('e.ClaimAtStep', '>=', 4)
            ->where('e.ClaimYearId', $year_id);

        if ((int) $company === 11) {
            $query->where(function ($q) {
                $q->where('mef.Fin_PayBy', '>', 0)
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('mef.Verified_Date')
                            ->where('mef.Verified_Date', '!=', '0000-00-00')
                            ->where('mef.Verified_Date', '!=', '1970-01-01');
                    })
                    ->orWhere('h.EmpStatus', '!=', 'A')
                    ->orWhereNull('h.EmpStatus');
            });
        } else {
            $query->where('mef.Fin_AppBy', '>', 0);
        }
        /* 🔁 Filters */
        if ($isPaymentExport && ! empty($filters['user_ids'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['user_ids'] as $userId => $months) {
                    if (! empty($months)) {
                        $q->orWhere(function ($sub) use ($userId, $months) {
                            $sub
                                ->where('e.CrBy', $userId)
                                ->whereIn('e.ClaimMonth', $months);
                        });
                    }
                }
            });
        } else {
            if (! empty($filters['months'])) {
                $query->whereIn('e.ClaimMonth', $filters['months']);
            }
            if (! empty($filters['user_ids'])) {
                $query->whereIn('e.CrBy', $filters['user_ids']);
            }
        }
        if (! empty($filters['function_ids'])) {
            $query->whereIn('gen.EmpFunction', $filters['function_ids']);
        }
        if (! empty($filters['department_ids'])) {
            $query->whereIn('gen.DepartmentId', $filters['department_ids']);
        }
        if (! empty($filters['vertical_ids'])) {
            $query->whereIn('gen.EmpVertical', $filters['vertical_ids']);
        }
        if (! empty($filters['sub_department_ids'])) {
            $query->whereIn('gen.SubDepartmentId', $filters['sub_department_ids']);
        }
        if (! empty($filters['policy_ids'])) {
            $query->leftJoin('hrims.hrm_employee_eligibility AS elig', function ($join) {
                $join->on('elig.EmployeeID', '=', 'e.CrBy')
                    ->where('elig.Status', 'A');
            });
            $query->whereIn('elig.VehiclePolicy', $filters['policy_ids']);
        }
        /* DA RULE */
        if ((int) $company === 11) {
            $query->where(function ($q) {
                $q->whereNotIn('c.ClaimName', ['DAOutStation@HQ', 'DA', 'DA@HQ']);
            });
        } else {
            $query->where(function ($q) {
                $q
                    ->whereNotIn('c.ClaimName', ['DAOutStation@HQ', 'DA', 'DA@HQ'])
                    ->orWhere(function ($q2) {
                        $q2
                            ->whereIn('c.ClaimName', ['DAOutStation@HQ', 'DA', 'DA@HQ'])
                            ->where('grade.id', '>', 67);
                    });
            });
        }

        return $query->groupBy(['e.ClaimMonth', 'e.ExpId', 'e.Crby', 'e.ClaimId', 'e.WType', 'c.focus_code', 'edtl.Title'])->orderBy('e.Crby', 'ASC')->orderBy('e.ClaimId', 'ASC');
    }

    public function filterClaims(Request $request)
    {
        try {
            $filters = [
                'function_ids' => $request->input('function_ids', []),
                'vertical_ids' => $request->input('vertical_ids', []),
                'department_ids' => $request->input('department_ids', []),
                'sub_department_ids' => $request->input('sub_department_ids', []),
                'bu_ids' => $request->input('bu_ids', []),
                'zone_ids' => $request->input('zone_ids', []),
                'region_ids' => $request->input('region_ids', []),
                'territory_ids' => $request->input('territory_ids', []),
                'hq_ids' => $request->input('hq_ids', []),
                'user_ids' => $request->input('user_ids', []),
                'filed_by_ids' => $request->input('filed_by_ids', []),
                'months' => $request->input('months', []),
                'claim_type_ids' => $request->input('claim_type_ids', []),
                'claim_statuses' => $request->input('claim_statuses'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'exp_id' => $request->input('expId'),
                'date_type' => $request->input('date_type', 'billDate'),
                'policy_ids' => $request->input('policy_ids', []),
                'vehicle_types' => $request->input('vehicle_types', []),
                'wheeler_type' => $request->input('wheeler_type'),
                'claim_filter_type' => $request->input('claim_filter_type'),
            ];

            // Resolve DataTables sort column and direction
            $allowedSortColumns = [
                'ExpId'           => 'ExpId',
                'claim_type_name' => 'claim_type_name',
                'employee_name'   => 'employee_name',
                'ClaimMonth'      => 'ClaimMonth',
                'CrDate'          => 'CrDate',
                'BillDate'        => 'BillDate',
                'FilledTAmt'      => 'FilledTAmt',
                'ClaimStatus'     => 'ClaimStatus',
            ];

            // DataTables sends order[0][column] as the column index (e.g. "5"),
            // then columns[5][name] holds the column name string.
            $orderColIndex   = $request->input('order.0.column', '1');
            $orderColumnName = $request->input("columns.{$orderColIndex}.name", 'ExpId');
            $orderDir        = strtoupper($request->input('order.0.dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
            $sortColumn      = $allowedSortColumns[$orderColumnName] ?? 'ExpId';

            $year_id = session('year_id');
            $table = ExpenseClaim::tableName($year_id);
            $countQuery = $this->buildClaimQuery($filters, $table, true);
            $totalRecords = $countQuery->get()->count();
            $query = $this->buildClaimQuery($filters, $table, false, null, $sortColumn, $orderDir);

            return DataTables::of($query)
                ->with('recordsTotal', $totalRecords)
                ->with('recordsFiltered', $totalRecords)
                ->addIndexColumn()
                // Tell yajra how to ORDER BY each named column using the actual SQL expressions.
                // This prevents yajra from generating invalid ORDER BY clauses on aggregated columns.
                ->orderColumn('ExpId',           "{$table}.ExpId $1")
                ->orderColumn('claim_type_name', "MAX(claimtype.ClaimName) $1")
                ->orderColumn('employee_name',   "MAX(CONCAT(hrims.hrm_employee.Fname, ' ', COALESCE(hrims.hrm_employee.Sname, ''), ' ', hrims.hrm_employee.Lname)) $1")
                ->orderColumn('ClaimMonth',      "MAX({$table}.ClaimMonth) $1")
                ->orderColumn('CrDate',          "MAX({$table}.CrDate) $1")
                ->orderColumn('BillDate',        "MAX({$table}.BillDate) $1")
                ->orderColumn('FilledTAmt',      "MAX({$table}.FilledTAmt) $1")
                ->orderColumn('ClaimStatus',     "MAX({$table}.ClaimStatus) $1")
                ->editColumn('ClaimStatus', function ($row) {
                $badgeClass = 'bg-secondary-subtle text-secondary';
                $statusText = ucfirst($row->ClaimStatus);
                switch (strtolower($row->ClaimStatus)) {
                    case 'deactivate':
                        $badgeClass = 'bg-dark-subtle text-dark';
                        break;
                    case 'draft':
                    case 'submitted':
                        $badgeClass = 'bg-warning-subtle text-warning';
                        break;
                    case 'filled':
                        $badgeClass = 'bg-info-subtle text-info';
                        break;
                    case 'verified':
                        $badgeClass = 'bg-primary-subtle text-primary';
                        break;
                    case 'approved':
                        $badgeClass = 'bg-success-subtle text-success';
                        break;
                    case 'financed':
                        $badgeClass = 'bg-teal-subtle text-teal';
                        break;
                }

                return '<span style="width: 60px;" class="badge '.$badgeClass.' badge-border">'.$statusText.'</span>';
            })->editColumn('CrDate', function ($row) {
                return $row->CrDate ? Carbon::parse($row->CrDate)->format('d-m-Y') : '';
            })->editColumn('BillDate', function ($row) {
                return $row->BillDate ? Carbon::parse($row->BillDate)->format('d-m-Y') : '';
            })->editColumn('ClaimMonth', function ($row) {
                return $row->ClaimMonth ? Carbon::create()->month($row->ClaimMonth)->format('F') : '';
            })->addColumn('action', function ($row) {
                $dropdownId = 'dropdownMenuLink'.$row->ExpId;
                $cgId = ClaimType::getCgIdByClaimId($row->ClaimId);
                $html = '
                    <div class="dropdown">
                        <a href="javascript:void(0)" role="button" id="'.$dropdownId.'" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ri-more-2-fill"></i>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="'.$dropdownId.'">
                            <li>
                                <a class="dropdown-item view-claim" href="#"
                                data-claim-id="'.$row->ClaimId.'"
                                data-empid="'.$row->CrBy.'"
                                data-cgid="'.$cgId.'"
                                data-expid="'.$row->ExpId.'">
                                View
                                </a>
                            </li>';
                $html .= '</ul></div>';

                return $html;
            })->rawColumns(['ClaimStatus', 'action'])->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not load claims: '.$e->getMessage()], 500);
        }
    }

    public function sameDayClaimUpload()
    {
        return Excel::download(new SameDayUploadClaimExport('y'.(int) session('year_id').'_expenseclaims') /* UAT-03: was hard-coded y7 */, 'SameDayUpload.xlsx');
    }

    public function executeRefactorMigration()
    {
        try {
            \Artisan::call('migrate', ['--path' => 'database/migrations/2026_01_18_000001_refactor_fc_claim_focus_mapping_table.php', '--force' => true]);
            $output = \Artisan::output();
            $newTableCount = DB::connection(session('expense_connection'))->table('fc_claim_focus_mapping_new')->count();
            $oldTableCount = DB::connection(session('expense_connection'))->table('fc_claim_focus_mapping')->count();

            return response()->json(['success' => true, 'message' => 'Migration executed successfully', 'migration_output' => $output, 'old_table_records' => $oldTableCount, 'new_table_records' => $newTableCount, 'details' => 'New table uses claim_id instead of claim_name and expense_head_category_id for specific expense heads']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error executing migration: '.$e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public function switchToNewFocusMapping()
    {
        try {
            DB::beginTransaction();
            DB::statement('RENAME TABLE fc_claim_focus_mapping TO fc_claim_focus_mapping_old');
            DB::statement('RENAME TABLE fc_claim_focus_mapping_new TO fc_claim_focus_mapping');
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Successfully switched to new Focus mapping table structure', 'details' => ['Old table backed up as: fc_claim_focus_mapping_old', 'New table is now active as: fc_claim_focus_mapping', 'Application now uses claim_id and expense_head_category_id for mapping']]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Error switching tables: '.$e->getMessage()], 500);
        }
    }

    public function getDailyActivityData(Request $request)
    {
        $fromDate = $request->input('fromDate');
        $toDate = $request->input('toDate');
        $year_id = session('year_id');
        $table = ExpenseClaim::tableName($year_id);
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $hasOrganogramFilters = !empty(array_filter($organogramFilters));

        $baseQuery = DB::connection(session('expense_connection'))->table("{$table} as e")->where('e.ClaimStatus', '!=', 'Deactivate')->whereNotIn('e.ClaimId', [19, 20, 21]);

        if ($hasOrganogramFilters) {
            $baseQuery->join("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', DB::raw('COALESCE(NULLIF(e.CrBy, 0), e.FilledBy)'));
            OrganogramFilterHelper::applyToQuery($baseQuery, $organogramFilters, 'eg', 'eg.EmployeeID', $hrimsDb);
        }

        $uploadQuery = (clone $baseQuery)->selectRaw('DATE(e.CrDate) AS ActionDate, COUNT(*) AS TotalUpload, 0 AS Punching, 0 AS Verified, 0 AS Approved, 0 AS Financed')->where('e.CrDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.CrDate)'), [$fromDate, $toDate])->groupBy(DB::raw('DATE(e.CrDate)'));
        $punchingQuery = (clone $baseQuery)->selectRaw('DATE(e.FilledDate) AS ActionDate, 0 AS TotalUpload, COUNT(*) AS Punching, 0 AS Verified, 0 AS Approved, 0 AS Financed')->where('e.FilledDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.FilledDate)'), [$fromDate, $toDate])->groupBy(DB::raw('DATE(e.FilledDate)'));
        $verifiedQuery = (clone $baseQuery)->selectRaw('DATE(e.VerifyDate) AS ActionDate, 0 AS TotalUpload, 0 AS Punching, COUNT(*) AS Verified, 0 AS Approved, 0 AS Financed')->where('e.VerifyDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.VerifyDate)'), [$fromDate, $toDate])->groupBy(DB::raw('DATE(e.VerifyDate)'));
        $approvedQuery = (clone $baseQuery)->selectRaw('DATE(e.ApprDate) AS ActionDate, 0 AS TotalUpload, 0 AS Punching, 0 AS Verified, COUNT(*) AS Approved, 0 AS Financed')->where('e.ApprDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.ApprDate)'), [$fromDate, $toDate])->groupBy(DB::raw('DATE(e.ApprDate)'));
        $financedQuery = (clone $baseQuery)->selectRaw('DATE(e.FinancedDate) AS ActionDate, 0 AS TotalUpload, 0 AS Punching, 0 AS Verified, 0 AS Approved, COUNT(*) AS Financed')->where('e.FinancedDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.FinancedDate)'), [
            $fromDate,
            $toDate,
        ])->groupBy(DB::raw('DATE(e.FinancedDate)'));
        $unionQuery = $uploadQuery->unionAll($punchingQuery)->unionAll($verifiedQuery)->unionAll($approvedQuery)->unionAll($financedQuery);
        $finalQuery = DB::connection(session('expense_connection'))->table(DB::raw("({$unionQuery->toSql()}) as sub"))->mergeBindings($unionQuery)->selectRaw('ActionDate, SUM(TotalUpload) AS TotalUpload, SUM(Punching) AS Punching, SUM(Verified) AS Verified, SUM(Approved) AS Approved, SUM(Financed) AS Financed')->groupBy('ActionDate')->orderBy('ActionDate')->get();
        $formattedData = $finalQuery->map(function ($row) {
            return [
                'ActionDate' => $row->ActionDate,
                'TotalUpload' => $row->TotalUpload,
                'Punching' => $row->Punching,
                'Verified' => $row->Verified,
                'Approved' => $row->Approved,
                'Financed' => $row->Financed,
            ];
        })->toArray();

        return $this->jsonSuccess($formattedData, 'Daily activity data loaded successfully.');
    }

    public function exportDailyActivity(Request $request)
    {
        $fromDate = $request->input('fromDate');
        $toDate = $request->input('toDate');
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);

        return Excel::download(new DailyActivityReportExport($fromDate, $toDate, $organogramFilters), 'daily_activity_report_'.$fromDate.'_to_'.$toDate.'.xlsx');
    }

    public function returnClaim(Request $request)
    {
        $request->validate([
            'expid'    => 'required|integer',
            'claim_id' => 'required|integer',
        ]);
        $year_id = session('year_id');
        $table   = ExpenseClaim::tableName($year_id);
        $reason  = $request->input('reason', '');

        // Fetch claim data needed for logging before updating
        $claim = DB::connection(session('expense_connection'))
            ->table($table)
            ->where('ExpId', $request->expid)
            ->select('CrBy', 'ClaimMonth', 'ClaimStatus')
            ->first();

        // B-01: return to the claimant only from a status the verifier may return (checked, logged)
        \App\Services\Claims\ClaimTransition::guardTable(session('expense_connection'), $table, (int) $request->expid, 'Submitted', 'verifier', 'return_claim', $reason ?: null);
        $updated = DB::connection(session('expense_connection'))
            ->table($table)
            ->where('ExpId', $request->expid)
            ->update([
                'ClaimStatus' => 'Submitted',
                'ClaimAtStep' => 2,
                'RtnBy'       => Auth::id(),
            ]);

        if ($updated) {
            if ($claim) {
                ClaimActionLogService::logClaimReturn(
                    (int) $request->expid,
                    (int) $claim->CrBy,
                    (int) $claim->ClaimMonth,
                    $claim->ClaimStatus ?? 'Verified',
                    'Submitted',
                    $reason,
                    \App\Models\ClaimActionLog::ROLE_VERIFIER
                );
            }
            return $this->jsonSuccess([], 'Claim returned successfully.');
        }

        return $this->jsonError('No record updated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employee Running KM Detail  (used by modal on the report page)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /employee-running-km-view/{employeeId}
     * Standalone page (no layout) — safe to embed in an iframe or open in a modal.
     */
    public function employeeRunningKmView(int $employeeId)
    {
        $expConn   = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $hrimsDb   = config("database.connections.{$hrimsConn}.database");
        $companyId = session('company_id');

        // ── Employee info ─────────────────────────────────────────────────────
        $emp = DB::connection($expConn)
            ->table("{$hrimsDb}.hrm_employee AS e")
            ->join("{$hrimsDb}.hrm_employee_general AS eg", 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments AS dep", 'dep.id', '=', 'eg.DepartmentId')
            ->leftJoin("{$hrimsDb}.core_grades AS gr", 'gr.id', '=', 'eg.GradeId')
            ->leftJoin("{$hrimsDb}.core_verticals AS vt", 'vt.id', '=', 'eg.EmpVertical')
            ->leftJoin("{$hrimsDb}.hrm_employee_eligibility AS elig", function ($j) {
                $j->on('elig.EmployeeID', '=', 'e.EmployeeID')->where('elig.Status', 'A');
            })
            ->leftJoin("{$hrimsDb}.hrm_master_eligibility_policy AS pol", 'pol.PolicyId', '=', 'elig.VehiclePolicy')
            ->where('e.EmployeeID', $employeeId)
            ->select([
                'e.EmployeeID', 'e.EmpCode',
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS employee_name"),
                'e.EmpStatus',
                'dep.department_name', 'gr.grade_name', 'vt.vertical_name',
                'pol.PolicyName AS policy_name',
                'elig.VehicleType AS vehicle_type',
            ])
            ->first();

        if (!$emp) {
            abort(404, 'Employee not found');
        }

        // ── All financial years ───────────────────────────────────────────────
        $allYears = DB::connection($expConn)
            ->table('financialyear')
            ->orderBy('YearId', 'asc')
            ->get(['YearId', 'Year']);

        $months = [
            'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
            'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
            'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
            'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
        ];

        // ── KM data per year ──────────────────────────────────────────────────
        $yearData     = [];
        $grandTotal2W = 0;
        $grandTotal4W = 0;

        foreach ($allYears as $fy) {
            $row = DB::connection($expConn)
                ->table('runningkm')
                ->where('empid', $employeeId)
                ->where('yearid', $fy->YearId)
                ->where('comid', (int) session('company_id', 1))
                ->first();

            if (!$row) continue;

            $yearTotal2W = 0;
            $yearTotal4W = 0;
            $monthRows   = [];

            foreach ($months as $key => $label) {
                $km2w = (float) ($row->{$key . '_2w_km'} ?? 0);
                $km4w = (float) ($row->{$key . '_4w_km'} ?? 0);
                $yearTotal2W += $km2w;
                $yearTotal4W += $km4w;
                $monthRows[] = ['month' => $label, 'km_2w' => $km2w, 'km_4w' => $km4w];
            }

            if ($yearTotal2W == 0 && $yearTotal4W == 0) continue;

            $grandTotal2W += $yearTotal2W;
            $grandTotal4W += $yearTotal4W;

            $yearData[] = [
                'year_label' => $fy->Year,
                'months'     => $monthRows,
                'total_2w'   => $yearTotal2W,
                'total_4w'   => $yearTotal4W,
            ];
        }

        // ── policy_change_details ─────────────────────────────────────────────
        $pcd = null;
        try {
            $pcdRow = DB::connection($hrimsConn)
                ->table('policy_change_details')
                ->where('CompID', $companyId ?: '1')
                ->where('EmpCode', $emp->EmpCode)
                ->first([
                    'AlreadyRunBeforeApril24', 'AlreadyRunAfterApril24', 'TotalRun',
                    'PolicyName', 'Existing_Monthly_KM', 'Proposed_Monthly_KM',
                    'Remaining_Km', 'Policy',
                ]);

            if ($pcdRow) {
                $pcd = [
                    'before_apr24'        => (float) str_replace(',', '', $pcdRow->AlreadyRunBeforeApril24 ?? 0),
                    'after_apr24'         => (float) str_replace(',', '', $pcdRow->AlreadyRunAfterApril24  ?? 0),
                    'total_run'           => (float) str_replace(',', '', $pcdRow->TotalRun                ?? 0),
                    'remaining_km'        => $pcdRow->Remaining_Km        ?? '',
                    'policy'              => $pcdRow->Policy               ?? '',
                    'policy_name'         => $pcdRow->PolicyName           ?? '',
                    'existing_monthly_km' => $pcdRow->Existing_Monthly_KM ?? '',
                    'proposed_monthly_km' => $pcdRow->Proposed_Monthly_KM ?? '',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('policy_change_details fetch failed: ' . $e->getMessage());
        }

        $monthLabels = array_values($months);

        return view('reports.employee_running_km_view', compact(
            'emp', 'yearData', 'grandTotal2W', 'grandTotal4W', 'pcd', 'monthLabels'
        ));
    }
    public function employeeRunningKmDetail(int $employeeId)
    {
        $expConn   = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $hrimsDb   = config("database.connections.{$hrimsConn}.database");
        $companyId = session('company_id');

        // ── Employee info ─────────────────────────────────────────────────────
        $emp = DB::connection($expConn)
            ->table("{$hrimsDb}.hrm_employee AS e")
            ->join("{$hrimsDb}.hrm_employee_general AS eg", 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments AS dep", 'dep.id', '=', 'eg.DepartmentId')
            ->leftJoin("{$hrimsDb}.core_grades AS gr", 'gr.id', '=', 'eg.GradeId')
            ->leftJoin("{$hrimsDb}.core_verticals AS vt", 'vt.id', '=', 'eg.EmpVertical')
            ->leftJoin("{$hrimsDb}.hrm_employee_eligibility AS elig", function ($j) {
                $j->on('elig.EmployeeID', '=', 'e.EmployeeID')->where('elig.Status', 'A');
            })
            ->leftJoin("{$hrimsDb}.hrm_master_eligibility_policy AS pol", 'pol.PolicyId', '=', 'elig.VehiclePolicy')
            ->where('e.EmployeeID', $employeeId)
            ->select([
                'e.EmployeeID',
                'e.EmpCode',
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS employee_name"),
                'e.EmpStatus',
                'dep.department_name',
                'gr.grade_name',
                'vt.vertical_name',
                'pol.PolicyName AS policy_name',
                'elig.VehicleType AS vehicle_type',
            ])
            ->first();

        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        // ── All financial years ───────────────────────────────────────────────
        $allYears = DB::connection($expConn)
            ->table('financialyear')
            ->orderBy('YearId', 'asc')
            ->get(['YearId', 'Year']);

        $months = [
            'apr' => 'Apr', 'may' => 'May', 'jun' => 'Jun',
            'jul' => 'Jul', 'aug' => 'Aug', 'sep' => 'Sep',
            'oct' => 'Oct', 'nov' => 'Nov', 'dec' => 'Dec',
            'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar',
        ];

        // ── KM data per year ──────────────────────────────────────────────────
        $yearData = [];
        $grandTotal2W = 0;
        $grandTotal4W = 0;

        foreach ($allYears as $fy) {
            $row = DB::connection($expConn)
                ->table('runningkm')
                ->where('empid', $employeeId)
                ->where('yearid', $fy->YearId)
                ->where('comid', (int) session('company_id', 1))
                ->first();

            if (!$row) continue;

            $yearTotal2W = 0;
            $yearTotal4W = 0;
            $monthRows   = [];

            foreach ($months as $key => $label) {
                $km2w = (float) ($row->{$key . '_2w_km'} ?? 0);
                $km4w = (float) ($row->{$key . '_4w_km'} ?? 0);
                $yearTotal2W += $km2w;
                $yearTotal4W += $km4w;
                $monthRows[] = [
                    'month' => $label,
                    'km_2w' => $km2w,
                    'km_4w' => $km4w,
                    'total' => $km2w + $km4w,
                ];
            }

            if ($yearTotal2W == 0 && $yearTotal4W == 0) continue;

            $grandTotal2W += $yearTotal2W;
            $grandTotal4W += $yearTotal4W;

            $yearData[] = [
                'year_id'    => $fy->YearId,
                'year_label' => $fy->Year,
                'months'     => $monthRows,
                'total_2w'   => $yearTotal2W,
                'total_4w'   => $yearTotal4W,
                'total'      => $yearTotal2W + $yearTotal4W,
            ];
        }

        // ── policy_change_details (pre-Apr-2024 data) ─────────────────────────
        $pcd = null;
        try {
            $pcdRow = DB::connection($hrimsConn)
                ->table('policy_change_details')
                ->where('CompID', $companyId ?: '1')
                ->where('EmpCode', $emp->EmpCode)
                ->first([
                    'AlreadyRunBeforeApril24',
                    'AlreadyRunAfterApril24',
                    'TotalRun',
                    'PolicyName',
                    'Existing_Monthly_KM',
                    'Proposed_Monthly_KM',
                ]);

            if ($pcdRow) {
                $pcd = [
                    'before_apr24'       => (float) str_replace(',', '', $pcdRow->AlreadyRunBeforeApril24 ?? 0),
                    'after_apr24'        => (float) str_replace(',', '', $pcdRow->AlreadyRunAfterApril24  ?? 0),
                    'total_run'          => (float) str_replace(',', '', $pcdRow->TotalRun                ?? 0),
                    'policy_name'        => $pcdRow->PolicyName ?? '',
                    'existing_monthly_km'=> $pcdRow->Existing_Monthly_KM ?? '',
                    'proposed_monthly_km'=> $pcdRow->Proposed_Monthly_KM ?? '',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('policy_change_details fetch failed: ' . $e->getMessage());
        }

        return response()->json([
            'success'       => true,
            'employee'      => $emp,
            'years'         => $yearData,
            'grand_total_2w'=> $grandTotal2W,
            'grand_total_4w'=> $grandTotal4W,
            'grand_total'   => $grandTotal2W + $grandTotal4W,
            'pcd'           => $pcd,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Total Running KM – All Years – Employee-Wise Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show the Total Running KM report page with all filter dropdowns.
     */
    public function totalRunningKmReport()
    {
        $company_id = session('company_id', 1);
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

        $grades          = \App\Models\CoreGrade::where('is_active', 1)->orderBy('grade_name')->get(['id', 'grade_name']);
        $policies        = $organogramOptions['eligibility_policy'];

        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', $company_id)
            ->orderBy('e.EmpCode')
            ->get(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'e.EmpStatus']);

        return view('reports.total_running_km', array_merge($organogramOptions, compact(
            'grades', 'policies', 'employees'
        )));
    }

    /**
     * Return JSON data for the Total Running KM report (AJAX).
     */
    public function totalRunningKmData(Request $request)
    {
        try {
            $data = $this->buildTotalRunningKmQuery($request);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('totalRunningKmData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export the Total Running KM report to Excel.
     */
    public function totalRunningKmExport(Request $request)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        try {
            [$rows, $years] = $this->buildTotalRunningKmQuery($request, true);

            $export   = new \App\Exports\TotalRunningKmExport(collect($rows), $years);
            $fileName = 'Total_Running_KM_' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download($export, $fileName);
        } catch (\Throwable $e) {
            Log::error('totalRunningKmExport error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Core query builder for Total Running KM report.
     *
     * @param  bool  $forExport  When true returns [$rows, $years]; when false returns $rows only.
     */
    private function buildTotalRunningKmQuery(Request $request, bool $forExport = false)
    {
        // ── Connections ───────────────────────────────────────────────────────
        $expConn   = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $hrimsDb   = config("database.connections.{$hrimsConn}.database");
        $companyId = session('company_id');

        // ── Filters ───────────────────────────────────────────────────────────
        $functionIds   = array_values(array_filter((array) $request->input('function_ids',       [])));
        $verticalIds   = array_values(array_filter((array) $request->input('vertical_ids',       [])));
        $departmentIds = array_values(array_filter((array) $request->input('department_ids',     [])));
        $subDeptIds    = array_values(array_filter((array) $request->input('sub_department_ids', [])));
        $buIds         = array_values(array_filter((array) ($request->input('bu_ids') ?: $request->input('buSelect', []))));
        $zoneIds       = array_values(array_filter((array) ($request->input('zone_ids') ?: $request->input('zoneSelect', []))));
        $regionIds     = array_values(array_filter((array) ($request->input('region_ids') ?: $request->input('regionSelect', []))));
        $territoryIds  = array_values(array_filter((array) ($request->input('territory_ids') ?: $request->input('territorySelect', []))));
        $hqIds         = array_values(array_filter((array) ($request->input('hq_ids') ?: $request->input('hqSelect', []))));
        $gradeIds      = array_values(array_filter((array) $request->input('grade_ids',          [])));
        $policyIds     = array_values(array_filter((array) ($request->input('policy_ids') ?: $request->input('policySelect', []))));
        $employeeIds   = array_values(array_filter((array) $request->input('employee_ids',       [])));
        $yearIds       = array_values(array_filter((array) $request->input('year_ids',           [])));
        $empStatus     = trim($request->input('emp_status',   ''));
        $vehicleType   = trim($request->input('vehicle_type', ''));
        $search        = trim($request->input('search',       ''));

        // ── Resolve financial years ───────────────────────────────────────────
        // All years from DB
        $allYearsRaw = DB::connection($expConn)
            ->table('financialyear')
            ->orderBy('YearId', 'asc')
            ->get(['YearId', 'Year']);

        // Find 2023-24 year (used for the "Before Apr'24 2W" column)
        $year2324 = $allYearsRaw->first(function ($y) {
            $yr = strtolower($y->Year);
            return str_contains($yr, '2023') && (str_contains($yr, '2024') || str_contains($yr, '23-24') || str_contains($yr, '2023-24'));
        });
        $yearId2324 = $year2324 ? $year2324->YearId : null;

        // Find 2024-25 year (default start)
        $year2425 = $allYearsRaw->first(function ($y) {
            $yr = strtolower($y->Year);
            return str_contains($yr, '2024') && (str_contains($yr, '2025') || str_contains($yr, '24-25') || str_contains($yr, '2024-25'));
        });

        // Determine which years to show as columns (2024-25 onwards, never 2023-24)
        $allYears = $allYearsRaw;
        if (!empty($yearIds)) {
            // User-selected years — still exclude 2023-24 from columns
            $allYears = $allYears
                ->whereIn('YearId', array_map('intval', $yearIds))
                ->filter(fn($y) => $y->YearId !== $yearId2324)
                ->values();
        } else {
            // Default: 2024-25 onwards, exclude 2023-24
            if ($year2425) {
                $allYears = $allYears
                    ->where('YearId', '>=', $year2425->YearId)
                    ->filter(fn($y) => $y->YearId !== $yearId2324)
                    ->values();
            }
        }

        if ($allYears->isEmpty()) {
            return $forExport ? [collect(), []] : ['rows' => [], 'years' => []];
        }

        $yearLabels = $allYears->pluck('Year', 'YearId')->toArray();
        $yearList   = $allYears->pluck('YearId')->toArray();

        // ── Load policy_change_details (pre-Apr-2024 4W KM) ──────────────────
        $policyChangeData = [];
        try {
            $pcdRows = DB::connection($hrimsConn)
                ->table('policy_change_details')
                ->where('CompID', $companyId ?: '1')
                ->get(['EmpCode', 'AlreadyRunBeforeApril24', 'AlreadyRunAfterApril24', 'TotalRun']);

            foreach ($pcdRows as $pcd) {
                $policyChangeData[trim($pcd->EmpCode)] = [
                    'before_apr24' => (float) str_replace(',', '', $pcd->AlreadyRunBeforeApril24 ?? 0),
                    'after_apr24'  => (float) str_replace(',', '', $pcd->AlreadyRunAfterApril24  ?? 0),
                    'total_run'    => (float) str_replace(',', '', $pcd->TotalRun                ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('policy_change_details not available: ' . $e->getMessage());
        }

        // ── Per-year KM selects ───────────────────────────────────────────────
        $months    = ['apr','may','jun','jul','aug','sep','oct','nov','dec','jan','feb','mar'];
        $kmSelects = [];

        foreach ($yearList as $yi => $yearId) {
            $sum2w = implode(' + ', array_map(fn($m) => "COALESCE(rk{$yi}.{$m}_2w_km, 0)", $months));
            $sum4w = implode(' + ', array_map(fn($m) => "COALESCE(rk{$yi}.{$m}_4w_km, 0)", $months));

            if ($vehicleType === '2W') {
                $kmSelects[] = DB::raw("({$sum2w}) AS km_2w_{$yi}");
                $kmSelects[] = DB::raw("0 AS km_4w_{$yi}");
            } elseif ($vehicleType === '4W') {
                $kmSelects[] = DB::raw("0 AS km_2w_{$yi}");
                $kmSelects[] = DB::raw("({$sum4w}) AS km_4w_{$yi}");
            } else {
                $kmSelects[] = DB::raw("({$sum2w}) AS km_2w_{$yi}");
                $kmSelects[] = DB::raw("({$sum4w}) AS km_4w_{$yi}");
            }
        }

        // ── 2023-24 2W KM — shown as a fixed "Before Apr'24 (2W)" column ─────
        // Sum all 12 months of 2W KM from the 2023-24 runningkm row.
        // This is separate from the year columns and always shown (unless 4W-only filter).
        if ($yearId2324 && $vehicleType !== '4W') {
            $sum2w2324 = implode(' + ', array_map(fn($m) => "COALESCE(rk2324.{$m}_2w_km, 0)", $months));
            $kmSelects[] = DB::raw("({$sum2w2324}) AS km_2w_before_apr24");
        } else {
            $kmSelects[] = DB::raw("0 AS km_2w_before_apr24");
        }

        // ── Main query ────────────────────────────────────────────────────────
        $query = DB::connection($expConn)
            ->table("{$hrimsDb}.hrm_employee AS emp")
            ->join("{$hrimsDb}.hrm_employee_general AS eg",             'eg.EmployeeID', '=', 'emp.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_functions AS fn",               'fn.id',         '=', 'eg.EmpFunction')
            ->leftJoin("{$hrimsDb}.core_verticals AS vt",               'vt.id',         '=', 'eg.EmpVertical')
            ->leftJoin("{$hrimsDb}.core_departments AS dep",            'dep.id',        '=', 'eg.DepartmentId')
            ->leftJoin("{$hrimsDb}.core_sub_department_master AS sdep", 'sdep.id',       '=', 'eg.SubDepartmentId')
            ->leftJoin("{$hrimsDb}.core_grades AS gr",                  'gr.id',         '=', 'eg.GradeId')
            ->leftJoin("{$hrimsDb}.hrm_employee_eligibility AS elig", function ($j) {
                $j->on('elig.EmployeeID', '=', 'emp.EmployeeID')
                  ->where('elig.Status', 'A');
            })
            ->leftJoin("{$hrimsDb}.hrm_master_eligibility_policy AS pol",
                'pol.PolicyId', '=', 'elig.VehiclePolicy');

        // Left-join each year's runningkm (one row per employee/year/company; legacy comid = 0
        // duplicates used to multiply the employee's rows)
        $rkCompanyId = (int) ($companyId ?: session('company_id', 1));
        foreach ($yearList as $yi => $yearId) {
            $query->leftJoin(
                DB::raw("(SELECT * FROM runningkm WHERE yearid = ".(int) $yearId." AND comid = {$rkCompanyId}) AS rk{$yi}"),
                "rk{$yi}.empid", '=', 'emp.EmployeeID'
            );
        }

        // Left-join 2023-24 runningkm for the "Before Apr'24 (2W)" column
        if ($yearId2324 && $vehicleType !== '4W') {
            $query->leftJoin(
                DB::raw("(SELECT * FROM runningkm WHERE yearid = ".(int) $yearId2324." AND comid = {$rkCompanyId}) AS rk2324"),
                'rk2324.empid', '=', 'emp.EmployeeID'
            );
        }

        // ── Select ────────────────────────────────────────────────────────────
        $query->select(array_merge([
            'emp.EmployeeID',
            'emp.EmpCode AS employee_code',
            DB::raw("TRIM(CONCAT(emp.Fname,' ',COALESCE(emp.Sname,''),' ',emp.Lname)) AS employee_name"),
            'emp.EmpStatus',
            'fn.function_name',
            'vt.vertical_name',
            'dep.department_name',
            'sdep.sub_department_name',
            'gr.grade_name',
            'pol.PolicyName AS policy_name',
            'elig.VehicleType AS vehicle_type',
        ], $kmSelects));

        // ── Company filter ────────────────────────────────────────────────────
        if ($companyId) {
            $query->where('emp.CompanyId', $companyId);
        }

        // ── Org filters (all on non-nullable columns — safe) ──────────────────
        if (!empty($functionIds)) {
            $query->whereIn('eg.EmpFunction', $functionIds);
        }
        if (!empty($verticalIds)) {
            $query->whereIn('eg.EmpVertical', $verticalIds);
        }
        if (!empty($departmentIds)) {
            $query->whereIn('eg.DepartmentId', $departmentIds);
        }
        if (!empty($subDeptIds)) {
            $query->whereIn('eg.SubDepartmentId', $subDeptIds);
        }
        if (!empty($buIds)) {
            $query->whereIn('eg.BUId', $buIds);
        }
        if (!empty($zoneIds)) {
            $query->whereIn('eg.ZoneId', $zoneIds);
        }
        if (!empty($regionIds)) {
            $query->whereIn('eg.RegionId', $regionIds);
        }
        if (!empty($territoryIds)) {
            $query->whereIn('eg.TerrId', $territoryIds);
        }
        if (!empty($hqIds)) {
            $query->whereIn('eg.HqId', $hqIds);
        }
        if (!empty($gradeIds)) {
            $query->whereIn('eg.GradeId', $gradeIds);
        }

        // ── Policy filter (LEFT JOIN column — must not exclude NULL rows) ────────
        if (!empty($policyIds)) {
            $query->where(function ($q) use ($policyIds) {
                $q->whereIn('elig.VehiclePolicy', $policyIds);
            });
        }

        // ── Vehicle type filter ───────────────────────────────────────────────
        // Filter based on actual KM data in runningkm, not the eligibility table.
        // Employees with 2W data have non-zero _2w_km; 4W employees have _4w_km.
        // We also check elig.VehicleType as a secondary signal (with NULL safety).
        if ($vehicleType === '2W') {
            // Must have 2W KM data OR eligibility says 2W
            $twoWConditions = [];
            foreach ($yearList as $yi => $yearId) {
                foreach ($months as $m) {
                    $twoWConditions[] = "COALESCE(rk{$yi}.{$m}_2w_km, 0) > 0";
                }
            }
            if (!empty($twoWConditions)) {
                $query->where(function ($q) use ($twoWConditions) {
                    $q->whereRaw('(' . implode(' OR ', $twoWConditions) . ')')
                      ->orWhere('elig.VehicleType', 2);
                });
            }
        } elseif ($vehicleType === '4W') {
            // Must have 4W KM data OR eligibility says 4W
            $fourWConditions = [];
            foreach ($yearList as $yi => $yearId) {
                foreach ($months as $m) {
                    $fourWConditions[] = "COALESCE(rk{$yi}.{$m}_4w_km, 0) > 0";
                }
            }
            if (!empty($fourWConditions)) {
                $query->where(function ($q) use ($fourWConditions) {
                    $q->whereRaw('(' . implode(' OR ', $fourWConditions) . ')')
                      ->orWhere('elig.VehicleType', 4);
                });
            }
        }

        // ── Employee filter ───────────────────────────────────────────────────
        if (!empty($employeeIds)) {
            $query->whereIn('emp.EmployeeID', $employeeIds);
        }

        // ── Status filter ─────────────────────────────────────────────────────
        if ($empStatus !== '') {
            $query->where('emp.EmpStatus', $empStatus);
        }

        // ── Search filter ─────────────────────────────────────────────────────
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('emp.EmpCode', 'like', "%{$search}%")
                  ->orWhereRaw(
                      "TRIM(CONCAT(emp.Fname,' ',COALESCE(emp.Sname,''),' ',emp.Lname)) LIKE ?",
                      ["%{$search}%"]
                  );
            });
        }

        // ── KM data filter ────────────────────────────────────────────────────
        // Include all years (display years + 2023-24) in the EXISTS check
        $allYearIdsForCheck = $yearList;
        if ($yearId2324 && !in_array($yearId2324, $allYearIdsForCheck)) {
            $allYearIdsForCheck[] = $yearId2324;
        }
        $yearIdList = implode(',', $allYearIdsForCheck);

        if (!empty($policyChangeData)) {
            // Employees in policy_change_details always included
            $pcdCodes = array_keys($policyChangeData);
            $query->where(function ($q) use ($yearIdList, $pcdCodes) {
                // Has any runningkm record for the selected years
                $q->whereRaw("EXISTS (
                        SELECT 1 FROM runningkm rk_check
                        WHERE rk_check.empid = emp.EmployeeID
                          AND rk_check.comid = {$rkCompanyId}
                          AND rk_check.yearid IN ({$yearIdList})
                    )")
                  ->orWhereIn('emp.EmpCode', $pcdCodes);
            });
        } else {
            // No policy_change_details — only show employees with runningkm data
            $query->whereRaw("EXISTS (
                SELECT 1 FROM runningkm rk_check
                WHERE rk_check.empid = emp.EmployeeID
                  AND rk_check.comid = {$rkCompanyId}
                  AND rk_check.yearid IN ({$yearIdList})
            )");
        }

        $query->orderBy('emp.EmpCode', 'asc');

        $rows = $query->get();

        // ── Merge policy_change_details + 2023-24 2W KM into rows ────────────
        $rows = $rows->map(function ($row) use ($policyChangeData) {
            $code = trim($row->employee_code ?? '');
            if (isset($policyChangeData[$code])) {
                $pcd = $policyChangeData[$code];
                $row->pcd_before_apr24 = $pcd['before_apr24'];
                $row->pcd_after_apr24  = $pcd['after_apr24'];
                $row->pcd_total_run    = $pcd['total_run'];
                $row->has_pcd          = true;
            } else {
                $row->pcd_before_apr24 = null;
                $row->pcd_after_apr24  = null;
                $row->pcd_total_run    = null;
                $row->has_pcd          = false;
            }
            // km_2w_before_apr24 comes directly from the SQL select (rk2324 join)
            // It's already on the row object; just ensure it's a float
            $row->km_2w_before_apr24 = (float) ($row->km_2w_before_apr24 ?? 0);
            return $row;
        });

        $yearsIndexed = array_values($yearLabels);

        if ($forExport) {
            return [$rows, $yearsIndexed];
        }

        return [
            'rows'  => $rows,
            'years' => $yearsIndexed,
        ];
    }

    public function exportClaimStageData(Request $request)
    {
        ini_set('max_execution_time', 10000);
        ini_set('memory_limit', '5120M');
        $yearId = session('year_id') ?? $request->input('yearId');
        $companyId = session('company_id') ?? $request->input('company_id');
        $monthSelect = (array) $request->input('monthSelect', []);
        $userSelect = (array) $request->input('userSelect', []);
        $rows = $request->input('rows', []);
        if (empty($yearId)) {
            return response()->json(['status' => 'error', 'message' => 'Please provide yearId'], 422);
        }
        if (empty($monthSelect)) {
            return response()->json(['status' => 'error', 'message' => 'Please select month'], 422);
        }

        // Extract ALL record IDs that are being exported (both released and on-hold)
        $allRecordIdsToUpdate = [];
        foreach ($rows as $row) {
            $allRecordIdsToUpdate[] = $row['id'];
        }

        // For payment export, restructure user_ids as associative array: [userId => [months]]
        $userIdsForPayment = [];

        if (! empty($request->rows)) {
            foreach ($request->rows as $row) {
                $userIdsForPayment[$row['employee_id']][] = $row['month'];
            }
        }

        $filters = [
            'months' => $monthSelect,
            'user_ids' => $userIdsForPayment,
        ];

        $expenseTable = "y{$yearId}_expenseclaims";
        $detailTable = "y{$yearId}_expenseclaimsdetails";
        $monthFinalTable = "y{$yearId}_monthexpensefinal";
        $g1Table = "y{$yearId}_g1_expensefilldata";
        $g2Table = "y{$yearId}_g2_expensefilldata";
        $g3Table = "y{$yearId}_g3_expensefilldata";
        $g4Table = "y{$yearId}_g4_expensefilldata";
        $g5Table = "y{$yearId}_g5_expensefilldata";
        $g7Table = "y{$yearId}_g7_expensefilldata";
        // Ensure all exported records have valid DocId and DocNo assigned
        $this->ensureMonthexpenseDocNumbers($yearId, $monthFinalTable, $allRecordIdsToUpdate, $filters);

        DB::enableQueryLog();
        $query = $this->focusFinalStage($filters, $expenseTable, $detailTable, $monthFinalTable, $g1Table, $g2Table, $g3Table, $g4Table, $g5Table, $g7Table, true, $companyId, $yearId);
        // Execute query
        $focusData = $this->reconcileFocusActivityLines($query->get());
        // Now get logs
        $queries = DB::getQueryLog();
        $lastQuery = end($queries);
        Log::info('Last Executed Query', [
            'sql' => $lastQuery['query'] ?? null,
            'bindings' => $lastQuery['bindings'] ?? [],
            'time_ms' => $lastQuery['time'] ?? null,
        ]);
        $export = new FocusExport($focusData);
        $fileName = 'Focus_Export_'.now()->format('Ymd_His').'.xlsx';
        $path = 'temp/'.$fileName;
        Excel::store($export, $path, \App\Support\PrivateExport::DISK); // FILES-01
        if (! Storage::disk(\App\Support\PrivateExport::DISK)->exists($path)) {
            throw new \Exception('File not created');
        }
        \App\Support\FocusExportLog::record('claim_focus_final', $focusData, (array) $filters, $fileName, $path); // FOCUS-LOG-01

        // AFTER successful file creation, update ALL exported records
        if (! empty($allRecordIdsToUpdate)) {
            $tableName = 'y'.$yearId.'_monthexpensefinal';
            $connection = DB::connection(session('expense_connection'));

            $updated = $connection
                ->table($tableName)
                ->whereIn('id', $allRecordIdsToUpdate)
                ->update([
                    'is_focus_exported' => 1,
                    'focus_exported_by' => Auth::id(),
                    'focus_exported_date' => now(),
                ]);

            // For VVNR (company 11): zero-amount records skip actual bank payment,
            // so auto-confirm them the same way markAsExported does.
            if ((int) $companyId === 11) {
                $zeroUpdated = $connection
                    ->table($tableName)
                    ->whereIn('id', $allRecordIdsToUpdate)
                    ->where(function ($q) {
                        $q->whereNull('payment_amount')->orWhere('payment_amount', 0);
                    })
                    ->update([
                        'payment_status'  => 'ConfirmedPayment',
                        'payment_date'    => now(),
                        'Fin_PayDate'     => now(),
                    ]);

                Log::info('VVNR zero-amount records auto-confirmed', [
                    'zero_confirmed_count' => $zeroUpdated,
                    'record_ids'           => $allRecordIdsToUpdate,
                    'table'                => $tableName,
                ]);
            }

            Log::info('Focus export records updated', [
                'total_exported_count' => count($allRecordIdsToUpdate),
                'updated_rows'         => $updated,
                'record_ids'           => $allRecordIdsToUpdate,
                'table'                => $tableName,
            ]);
        }

        return response()->json(['status' => 'success', 'file_url' => $this->cleanStorageUrl($path), 'file_name' => $fileName]);
    }

    /**
     * Display hotel price variation report view
     */
    public function hotelPriceVariationReport()
    {
        $company_id = session('company_id');
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

        $functions = $organogramOptions['functions'] ?? CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
        $verticals = $organogramOptions['verticals'] ?? CoreVertical::where('is_active', 1)->get(['id', 'vertical_name']);
        $departments = $organogramOptions['departments'] ?? CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);
        $sub_departments = $organogramOptions['sub_departments'] ?? CoreSubDepartment::where('is_active', 1)->get(['id', 'sub_department_name']);

        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->select('e.EmployeeID', 'e.EmpCode', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'e.EmpStatus')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', $company_id)
            ->orderBy('e.EmployeeID', 'asc')
            ->get();

        return view('reports.hotel_price_variation_report', array_merge($organogramOptions, compact('functions', 'verticals', 'departments', 'sub_departments', 'employees')));
    }

    /**
     * Get hotel price variation data via AJAX for DataTable
     */
    public function getHotelPriceVariationData(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $expenseFileTable = 'y'.$year_id.'_g1_expensefilldata';

            $query = DB::connection(session('expense_connection'))
                ->table($expenseFileTable.' as h')
                ->select(
                    'h.did',
                    'h.ExpId',
                    'h.DocName',
                    'h.HotelName',
                    'h.CityName',
                    'h.CityCategory',
                    'h.HotelAddress',
                    'h.HotelContact',
                    'h.HotelEmail',
                    'h.BillNo',
                    'h.BillDate',
                    'h.Arrival',
                    'h.Departure',
                    'h.StayDuration',
                    'h.NoOfPAX',
                    'h.PaxDetails',
                    'h.RoomRateType',
                    'h.Plan',
                    'h.GST',
                    'c.ExpId as ClaimExpId',
                    'c.ClaimId',
                    'c.FinancedTAmt',
                    'v.v_min',
                    'v.v_max'
                )
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->whereNotNull('h.HotelName')
                ->where('h.HotelName', '!=', '')
                ->whereNotNull('h.CityName')
                ->where('h.CityName', '!=', '')
                // UAT-03: the hotels-with-price-variation list was a WHERE IN (GROUP BY ... HAVING)
                // subquery that MySQL re-ran per row (over 90 s). It is now one derived table joined
                // on hotel + city, which also matches the city as the grouping intended.
                ->joinSub(
                    DB::connection(session('expense_connection'))->table($expenseFileTable.' as h2')
                        ->join($expenseTable.' as c2', 'h2.ExpId', '=', 'c2.ExpId')
                        ->whereNotNull('h2.HotelName')->where('h2.HotelName', '!=', '')
                        ->whereNotNull('h2.CityName')->where('h2.CityName', '!=', '')
                        ->groupBy('h2.HotelName', 'h2.CityName')
                        ->havingRaw('COUNT(DISTINCT c2.FinancedTAmt) > 1')
                        ->select('h2.HotelName as v_hotel', 'h2.CityName as v_city',
                            DB::raw('MIN(c2.FinancedTAmt) as v_min'), DB::raw('MAX(c2.FinancedTAmt) as v_max')),
                    'v', function ($j) {
                        $j->on('v.v_hotel', '=', 'h.HotelName')->on('v.v_city', '=', 'h.CityName');
                    });

            // Apply filters
            if ($request->filled('hotel_name')) {
                $query->where('h.HotelName', 'like', '%'.$request->hotel_name.'%');
            }

            if ($request->filled('city_name')) {
                $query->where('h.CityName', 'like', '%'.$request->city_name.'%');
            }

            if ($request->filled('from_date')) {
                $query->where('h.BillDate', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('h.BillDate', '<=', $request->to_date);
            }

            if ($request->filled('min_price')) {
                $query->where('c.FinancedTAmt', '>=', $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('c.FinancedTAmt', '<=', $request->max_price);
            }

            if ($request->filled('user_ids')) {
                $query->whereIn('c.CrBy', $request->user_ids);
            }

            // Apply organogram filters
            $company_id = session('company_id');
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = config("database.connections.{$hrimsConn}.database", 'hrims');
            $query->join("{$hrimsDb}.hrm_employee as emp", 'emp.EmployeeID', '=', 'c.CrBy')
                  ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'emp.EmployeeID');
            if ($company_id) {
                $query->where('emp.CompanyId', $company_id);
            }
            $organogramFilters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('BillDate', function ($row) {
                    return $row->BillDate ? Carbon::parse($row->BillDate)->format('d-m-Y') : '-';
                })
                ->editColumn('Arrival', function ($row) {
                    return $row->Arrival ? Carbon::parse($row->Arrival)->format('d-m-Y') : '-';
                })
                ->editColumn('Departure', function ($row) {
                    return $row->Departure ? Carbon::parse($row->Departure)->format('d-m-Y') : '-';
                })
                ->editColumn('FinancedTAmt', function ($row) {
                    return '₹ '.number_format($row->FinancedTAmt, 2);
                })
                ->editColumn('NoOfPAX', function ($row) {
                    $badge = $row->NoOfPAX > 1 ? 'warning' : 'secondary';

                    return '<span class="badge bg-'.$badge.'">'.(int) $row->NoOfPAX.' Pax</span>';
                })
                ->editColumn('HotelName', function ($row) {
                    return '<strong>'.e($row->HotelName).'</strong>';
                })
                ->addColumn('price_difference', function ($row) {
                    // UAT-03: min/max now come from the derived table; the per-row query read a
                    // hard-coded y7 table (wrong year, and missing for VVNR).
                    $min = (float) $row->v_min;
                    $max = (float) $row->v_max;

                    return '<span class="badge bg-danger">Diff: ₹ '.number_format($max - $min, 2).'</span><br>
                        <small class="text-muted">Min: ₹ '.number_format($min, 2).'<br>Max: ₹ '.number_format($max, 2).'</small>';
                })
                ->addColumn('action', function ($row) {
                    return '<button type="button" class="btn btn-sm btn-info view-hotel-details"
                            data-hotel-name="'.e($row->HotelName).'"
                            data-city-name="'.e($row->CityName).'"
                            data-exp-id="'.e($row->ExpId).'">
                            <i class="ri-eye-line"></i> View
                        </button>';
                })
                ->rawColumns(['HotelName', 'FinancedTAmt', 'NoOfPAX', 'price_difference', 'action'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Error in getHotelPriceVariationData: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get hotel variation summary (same hotel, different prices)
     */
    public function getHotelPriceVariationSummary(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';

            $query = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.HotelName',
                    'h.CityName',
                    'h.HotelAddress',
                    DB::raw('COUNT(DISTINCT c.FinancedTAmt) as unique_prices'),
                    DB::raw('MIN(c.FinancedTAmt) as min_price'),
                    DB::raw('MAX(c.FinancedTAmt) as max_price'),
                    DB::raw('(MAX(c.FinancedTAmt) - MIN(c.FinancedTAmt)) as price_difference'),
                    DB::raw('COUNT(DISTINCT h.ExpId) as total_claims'),
                    DB::raw('SUM(h.NoOfPAX) as total_pax'),
                    DB::raw('GROUP_CONCAT(DISTINCT h.BillNo ORDER BY h.BillDate DESC SEPARATOR ", ") as bill_numbers'),
                    DB::raw('MIN(h.BillDate) as first_bill_date'),
                    DB::raw('MAX(h.BillDate) as last_bill_date')
                )
                ->whereNotNull('h.HotelName')
                ->where('h.HotelName', '!=', '')
                ->whereNotNull('h.CityName')
                ->where('h.CityName', '!=', '')
                ->groupBy('h.HotelName', 'h.CityName', 'h.HotelAddress')
                ->havingRaw('COUNT(DISTINCT c.FinancedTAmt) > 1')
                ->orderBy('price_difference', 'desc');

            // Apply filters
            if ($request->filled('hotel_name')) {
                $query->where('h.HotelName', 'like', '%'.$request->hotel_name.'%');
            }

            if ($request->filled('city_name')) {
                $query->where('h.CityName', 'like', '%'.$request->city_name.'%');
            }

            if ($request->filled('from_date')) {
                $query->where('h.BillDate', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('h.BillDate', '<=', $request->to_date);
            }

            if ($request->filled('user_ids')) {
                $query->whereIn('c.CrBy', $request->user_ids);
            }

            // Apply All Organogram & Policy Filters
            $company_id = session('company_id');
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = config("database.connections.{$hrimsConn}.database", 'hrims');
            $query->join("{$hrimsDb}.hrm_employee as emp", 'emp.EmployeeID', '=', 'c.CrBy')
                  ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'emp.EmployeeID');
            if ($company_id) {
                $query->where('emp.CompanyId', $company_id);
            }
            $organogramFilters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

            $data = $query->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('min_price', function ($row) {
                    return '₹ '.number_format($row->min_price, 2);
                })
                ->editColumn('max_price', function ($row) {
                    return '₹ '.number_format($row->max_price, 2);
                })
                ->editColumn('price_difference', function ($row) {
                    $percentage = $row->min_price > 0 ? ($row->price_difference / $row->min_price) * 100 : 0;

                    return '₹ '.number_format($row->price_difference, 2).' ('.number_format($percentage, 1).'%)';
                })
                ->editColumn('first_bill_date', function ($row) {
                    return $row->first_bill_date ? Carbon::parse($row->first_bill_date)->format('d-m-Y') : '-';
                })
                ->editColumn('last_bill_date', function ($row) {
                    return $row->last_bill_date ? Carbon::parse($row->last_bill_date)->format('d-m-Y') : '-';
                })
                ->addColumn('action', function ($row) {
                    return '<button type="button" class="btn btn-sm btn-primary view-hotel-variations"
                            data-hotel-name="'.e($row->HotelName).'"
                            data-city-name="'.e($row->CityName).'">
                            <i class="ri-bar-chart-line"></i> View Variations
                        </button>';
                })
                ->rawColumns(['action'])
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Error in getHotelPriceVariationSummary: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export hotel price variation report
     */
    public function exportHotelPriceVariation(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = ExpenseClaim::tableName($year_id);

            $query = DB::connection(session('expense_connection'))
                ->table('y'.(int) $year_id.'_g1_expensefilldata as h') // UAT-03: was hard-coded y7
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.ExpId',
                    'h.HotelName',
                    'h.CityName',
                    'h.HotelAddress',
                    'h.HotelContact',
                    'h.BillNo',
                    'h.BillDate',
                    'h.Arrival',
                    'h.Departure',
                    'h.StayDuration',
                    'h.NoOfPAX',
                    'h.PaxDetails',
                    'h.RoomRateType',
                    'c.FinancedTAmt',
                    'c.ClaimStatus'
                )
                ->whereNotNull('h.HotelName')
                ->where('h.HotelName', '!=', '')
                ->whereNotNull('h.CityName')
                ->where('h.CityName', '!=', '');

            // Apply filters
            if ($request->filled('hotel_name')) {
                $query->where('h.HotelName', 'like', '%'.$request->hotel_name.'%');
            }

            if ($request->filled('city_name')) {
                $query->where('h.CityName', 'like', '%'.$request->city_name.'%');
            }

            if ($request->filled('from_date')) {
                $query->where('h.BillDate', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('h.BillDate', '<=', $request->to_date);
            }

            if ($request->filled('user_ids')) {
                $query->whereIn('c.CrBy', $request->user_ids);
            }

            // Apply All Organogram & Policy Filters
            $company_id = session('company_id');
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = config("database.connections.{$hrimsConn}.database", 'hrims');
            $query->join("{$hrimsDb}.hrm_employee as emp", 'emp.EmployeeID', '=', 'c.CrBy')
                  ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'emp.EmployeeID');
            if ($company_id) {
                $query->where('emp.CompanyId', $company_id);
            }
            $organogramFilters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

            $data = $query
                ->orderBy('h.HotelName')
                ->orderBy('h.CityName')
                ->orderBy('c.FinancedTAmt', 'desc')
                ->get();

            $fileName = 'Hotel_Price_Variation_Report_'.date('Ymd_His').'.xlsx';
            $export = new \App\Exports\HotelPriceVariationExport($data);

            return Excel::download($export, $fileName);
        } catch (\Exception $e) {
            Log::error('Error in exportHotelPriceVariation: '.$e->getMessage());

            return response()->json(['error' => 'Failed to export report'], 500);
        }
    }

    /**
     * Get hotel details by ExpId for modal view
     */
    public function getHotelDetailsByExpId($expId)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';

            $data = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.*',
                    'c.FinancedTAmt',
                    'c.ClaimStatus',
                    'c.VerifyTAmt',
                    'c.ApprTAmt',
                    'c.FilledTAmt',
                    'c.CrBy'
                )
                ->where('h.ExpId', $expId)
                ->first();

            // Parse PaxDetails if exists
            if ($data && $data->PaxDetails) {
                $data->PaxDetailsArray = $this->parsePaxDetails($data->PaxDetails);

                // Get employee names for PaxDetails
                if (! empty($data->PaxDetailsArray)) {
                    $employees = DB::connection(session('hrims_connection'))
                        ->table('hrm_employee')
                        ->whereIn('EmployeeID', $data->PaxDetailsArray)
                        ->select('EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname')
                        ->get();
                    $data->EmployeeDetails = $employees;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getHotelDetailsByExpId: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Failed to load hotel details: '.$e->getMessage()], 500);
        }
    }

    /**
     * Helper function to parse PaxDetails
     */
    private function parsePaxDetails($paxDetails)
    {
        if (empty($paxDetails)) {
            return [];
        }

        // Try to decode as JSON first
        $decoded = json_decode($paxDetails, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If not JSON, try comma-separated values
        if (strpos($paxDetails, ',') !== false) {
            return array_map('trim', explode(',', $paxDetails));
        }

        // Return as single value array
        return [$paxDetails];
    }

    /**
     * Get detailed claims for a specific hotel
     */
    public function getHotelClaimsDetails(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';

            $hotelName = $request->hotel_name;
            $cityName = $request->city_name;

            $query = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.did',
                    'h.ExpId',
                    'h.DocName',
                    'h.BillNo',
                    'h.BillDate',
                    'h.Arrival',
                    'h.Departure',
                    'h.StayDuration',
                    'h.NoOfPAX',
                    'h.PaxDetails',
                    'h.RoomRateType',
                    'h.Plan',
                    'h.GST',
                    'c.FinancedTAmt as price',
                    'c.ClaimStatus',
                    'c.CrBy as employee_id',
                    DB::raw('(SELECT CONCAT(Fname, " ", Sname) FROM hrims.hrm_employee WHERE EmployeeID = c.CrBy) as employee_name')
                )
                ->where('h.HotelName', $hotelName)
                ->where('h.CityName', $cityName)
                ->orderBy('h.BillDate', 'desc')
                ->orderBy('c.FinancedTAmt', 'desc');

            $claims = $query->get();

            // Calculate statistics
            $stats = [
                'total_claims' => $claims->count(),
                'total_pax' => $claims->sum('NoOfPAX'),
                'avg_price' => $claims->avg('price'),
                'min_price' => $claims->min('price'),
                'max_price' => $claims->max('price'),
                'price_range' => $claims->max('price') - $claims->min('price'),
                'date_range' => [
                    'first' => $claims->min('BillDate'),
                    'last' => $claims->max('BillDate'),
                ],
            ];

            // Group by date for date-wise summary
            $dateWiseSummary = $claims->groupBy(function ($item) {
                return Carbon::parse($item->BillDate)->format('Y-m-d');
            })->map(function ($group, $date) {
                return [
                    'date' => $date,
                    'total_claims' => $group->count(),
                    'total_pax' => $group->sum('NoOfPAX'),
                    'total_amount' => $group->sum('price'),
                    'avg_price' => $group->avg('price'),
                    'min_price' => $group->min('price'),
                    'max_price' => $group->max('price'),
                    'claims' => $group,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'hotel_name' => $hotelName,
                    'city_name' => $cityName,
                    'statistics' => $stats,
                    'date_wise_summary' => $dateWiseSummary,
                    'all_claims' => $claims,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getHotelClaimsDetails: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load hotel claims details: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display hotel high price report view
     */
    public function hotelHighPriceReport()
    {
        $functions = CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
        $verticals = CoreVertical::where('is_active', 1)->get(['id', 'vertical_name']);
        $departments = CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);
        $sub_departments = CoreSubDepartment::where('is_active', 1)->get(['id', 'sub_department_name']);

        $company_id = session('company_id');
        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->select('e.EmployeeID', 'e.EmpCode', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'e.EmpStatus')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', $company_id)
            ->orderBy('e.EmployeeID', 'asc')
            ->get();

        return view('admin.hotel_high_price_report', compact('functions', 'verticals', 'departments', 'sub_departments', 'employees'));
    }

    /**
     * Get hotel high price data (hotels with highest prices)
     */
    public function getHotelHighPriceData(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';

            $query = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->select(
                    'h.HotelName',
                    'h.CityName',
                    'h.CityCategory',
                    'h.HotelAddress',
                    DB::raw('MAX(c.FinancedTAmt) as max_price'),
                    DB::raw('MIN(c.FinancedTAmt) as min_price'),
                    DB::raw('AVG(c.FinancedTAmt) as avg_price'),
                    DB::raw('COUNT(DISTINCT h.ExpId) as total_stays'),
                    DB::raw('SUM(h.NoOfPAX) as total_pax'),
                    DB::raw('MAX(h.BillDate) as last_stay_date'),
                    DB::raw('GROUP_CONCAT(DISTINCT CONCAT(h.BillNo, ":", DATE_FORMAT(h.BillDate, "%Y-%m-%d"), ":", c.FinancedTAmt) ORDER BY c.FinancedTAmt DESC SEPARATOR " | ") as price_details')
                )
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->whereNotNull('h.HotelName')
                ->where('h.HotelName', '!=', '')
                ->whereNotNull('h.CityName')
                ->where('h.CityName', '!=', '')
                ->where('c.FinancedTAmt', '>', 0)
                ->groupBy('h.HotelName', 'h.CityName', 'h.CityCategory', 'h.HotelAddress');

            // Apply filters
            if ($request->filled('hotel_name')) {
                $query->where('h.HotelName', 'like', '%'.$request->hotel_name.'%');
            }

            if ($request->filled('city_name')) {
                $query->where('h.CityName', 'like', '%'.$request->city_name.'%');
            }

            if ($request->filled('min_price')) {
                $query->having('max_price', '>=', $request->min_price);
            }

            if ($request->filled('from_date')) {
                $query->where('h.BillDate', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('h.BillDate', '<=', $request->to_date);
            }

            // Order by highest price
            $sortBy = $request->input('sort_by', 'max_price');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->input('length', 25);
            $page = ($request->input('start', 0) / $perPage) + 1;

            $results = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data
            $data = [];
            foreach ($results as $index => $row) {
                $priceRange = '₹ '.number_format($row->min_price, 2).' - ₹ '.number_format($row->max_price, 2);
                $avgPrice = '₹ '.number_format($row->avg_price, 2);

                // Determine price level badge
                $priceLevel = '';
                if ($row->max_price >= 50000) {
                    $priceLevel = '<span class="badge bg-danger">Luxury</span>';
                } elseif ($row->max_price >= 25000) {
                    $priceLevel = '<span class="badge bg-warning">Premium</span>';
                } elseif ($row->max_price >= 10000) {
                    $priceLevel = '<span class="badge bg-info">Standard</span>';
                } else {
                    $priceLevel = '<span class="badge bg-secondary">Budget</span>';
                }

                $data[] = [
                    'DT_RowIndex' => ($page - 1) * $perPage + $index + 1,
                    'HotelName' => '<strong>'.e($row->HotelName).'</strong>',
                    'CityName' => $row->CityName,
                    'CityCategory' => $row->CityCategory,
                    'HotelAddress' => $row->HotelAddress,
                    'max_price' => '₹ '.number_format($row->max_price, 2),
                    'min_price' => '₹ '.number_format($row->min_price, 2),
                    'avg_price' => $avgPrice,
                    'price_range' => $priceRange,
                    'price_level' => $priceLevel,
                    'total_stays' => $row->total_stays,
                    'total_pax' => $row->total_pax,
                    'last_stay_date' => $row->last_stay_date ? Carbon::parse($row->last_stay_date)->format('d-m-Y') : '-',
                    'price_details' => '<button type="button" class="btn btn-sm btn-info view-price-details"
                                    data-hotel-name="'.e($row->HotelName).'"
                                    data-city-name="'.e($row->CityName).'">
                                    <i class="ri-eye-line"></i> View Details
                                </button>',
                    'action' => '<button type="button" class="btn btn-sm btn-primary view-hotel-stays"
                            data-hotel-name="'.e($row->HotelName).'"
                            data-city-name="'.e($row->CityName).'">
                            <i class="ri-hotel-line"></i> View Stays
                        </button>',
                ];
            }

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => $results->total(),
                'recordsFiltered' => $results->total(),
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getHotelHighPriceData: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get top N highest priced hotels
     */
    public function getTopHighestPriceHotels(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';
            $limit = $request->input('limit', 10);

            $hotels = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.HotelName',
                    'h.CityName',
                    DB::raw('MAX(c.FinancedTAmt) as max_price'),
                    DB::raw('AVG(c.FinancedTAmt) as avg_price'),
                    DB::raw('COUNT(DISTINCT h.ExpId) as total_stays')
                )
                ->whereNotNull('h.HotelName')
                ->where('h.HotelName', '!=', '')
                ->where('c.FinancedTAmt', '>', 0)
                ->groupBy('h.HotelName', 'h.CityName')
                ->orderBy('max_price', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $hotels,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getTopHighestPriceHotels: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get hotel stay details by hotel name and city
     */
    public function getHotelStaysByHotelName(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';

            $hotelName = $request->input('hotel_name');
            $cityName = $request->input('city_name');

            $stays = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.ExpId',
                    'h.BillNo',
                    'h.BillDate',
                    'h.Arrival',
                    'h.Departure',
                    'h.StayDuration',
                    'h.NoOfPAX',
                    'h.PaxDetails',
                    'h.RoomRateType',
                    'c.FinancedTAmt',
                    'c.ClaimStatus'
                )
                ->where('h.HotelName', $hotelName)
                ->where('h.CityName', $cityName)
                ->orderBy('c.FinancedTAmt', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $stays,
                'hotel_name' => $hotelName,
                'city_name' => $cityName,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getHotelStaysByHotelName: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export hotel high price report
     */
    public function exportHotelHighPrice(Request $request)
    {
        try {
            $year_id = session('year_id');
            $expenseTable = 'y'.$year_id.'_expenseclaims';
            $g1Table = 'y'.$year_id.'_g1_expensefilldata';

            $query = DB::connection(session('expense_connection'))
                ->table($g1Table.' as h')
                ->join($expenseTable.' as c', 'h.ExpId', '=', 'c.ExpId')
                ->select(
                    'h.HotelName',
                    'h.CityName',
                    'h.CityCategory',
                    'h.HotelAddress',
                    DB::raw('MAX(c.FinancedTAmt) as max_price'),
                    DB::raw('MIN(c.FinancedTAmt) as min_price'),
                    DB::raw('AVG(c.FinancedTAmt) as avg_price'),
                    DB::raw('COUNT(DISTINCT h.ExpId) as total_stays'),
                    DB::raw('SUM(h.NoOfPAX) as total_pax')
                )
                ->whereNotNull('h.HotelName')
                ->where('h.HotelName', '!=', '')
                ->where('c.FinancedTAmt', '>', 0)
                ->groupBy('h.HotelName', 'h.CityName', 'h.CityCategory', 'h.HotelAddress')
                ->orderBy('max_price', 'desc');

            // Apply filters
            if ($request->filled('hotel_name')) {
                $query->where('h.HotelName', 'like', '%'.$request->hotel_name.'%');
            }

            if ($request->filled('city_name')) {
                $query->where('h.CityName', 'like', '%'.$request->city_name.'%');
            }

            $data = $query->get();

            $fileName = 'Hotel_High_Price_Report_'.date('Ymd_His').'.xlsx';
            $export = new \App\Exports\HotelHighPriceExport($data);

            return Excel::download($export, $fileName);
        } catch (\Exception $e) {
            Log::error('Error in exportHotelHighPrice: '.$e->getMessage());

            return response()->json(['error' => 'Failed to export report'], 500);
        }
    }

    public function paymentStagesSummary(Request $request)
    {
        try {
            // Validate session data
            if (! session('company_id')) {
                return redirect()->back()->with('error', 'Company not selected. Please select a company first.');
            }

            if (! session('year_id')) {
                return redirect()->back()->with('error', 'Year not selected. Please select a year first.');
            }

            $company_id = session('company_id');
            $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

            $functions = $organogramOptions['functions'] ?? CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
            $verticals = $organogramOptions['verticals'] ?? CoreVertical::where('is_active', 1)->get(['id', 'vertical_name']);
            $departments = $organogramOptions['departments'] ?? CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);
            $sub_departments = $organogramOptions['sub_departments'] ?? CoreSubDepartment::where('is_active', 1)->get(['id', 'sub_department_name']);

            $employees = DB::connection(session('hrims_connection'))
                ->table('hrm_employee as e')
                ->select('e.EmployeeID', 'e.EmpCode', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'e.EmpStatus')
                ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->where('e.CompanyId', $company_id)
                ->where('e.EmpStatus', 'Active')
                ->orderBy('e.EmpCode', 'asc')
                ->get();

            return view('reports.payments_report', array_merge($organogramOptions, compact('functions', 'verticals', 'departments', 'sub_departments', 'employees', 'organogramOptions')));
        } catch (\Exception $e) {
            Log::error('Payment Stages Summary View Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to load payment stages summary. Please try again.');
        }
    }

    public function getPaymentStagesData(Request $request)
    {
        try {
            // Validate required parameters
            // $year = $request->input('year');
            // if (! $year) {
            //     return response()->json(['error' => 'Year is required'], 400);
            // }

            $year_id = session('year_id');
            if (! $year_id) {
                return response()->json(['error' => 'Year session not found'], 400);
            }

            $table = "y{$year_id}_monthexpensefinal";
            $withEmployee = $request->boolean('with_employee', false);

            // Check if table exists
            $tableExists = DB::connection(session('expense_connection'))
                ->getSchemaBuilder()
                ->hasTable($table);

            if (! $tableExists) {
                return response()->json(['error' => 'Data table not found for selected year'], 404);
            }

            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $query = DB::connection(session('expense_connection'))
                ->table($table.' as m')
                ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'm.EmployeeID', '=', 'eg.EmployeeID');
            // ->whereYear('m.CreatedDate', $year); // Add year filter

            // Apply filters
            $this->applyFilters($query, $request);

            $months = $request->input('months', []);
            if (is_string($months)) {
                $months = array_filter(explode(',', $months));
            }

            // Validate months
            if (! empty($months)) {
                $validMonths = array_intersect($months, range(1, 12));
                if (empty($validMonths)) {
                    return response()->json(['message' => 'No valid months selected', 'data' => []]);
                }
                $months = $validMonths;
            }

            // Execute query based on employee detail requirement
            if ($withEmployee) {
                $data = $this->getDataWithEmployeeDetails($query, $months);
            } else {
                $data = $this->getDataWithoutEmployeeDetails($query, $months);
            }

            // Format the data for DataTable
            $formattedData = $this->formatDataForDatatable($data, $withEmployee);

            return response()->json([
                'data' => $formattedData,
                'recordsTotal' => count($formattedData),
                'recordsFiltered' => count($formattedData),
            ]);
        } catch (\Exception $e) {
            Log::error('Payment Stages Data Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            $errorMessage = config('app.debug') ? $e->getMessage() : 'An error occurred while fetching data. Please try again.';

            return response()->json(['error' => $errorMessage], 500);
        }
    }

    /**
     * "Advance" column of the Payment Stages Summary.
     * Legacy months store the advance set off in Fin_AdvancePay; months approved in this app store it
     * only in total_adjustment (Fin_AdvancePay = 0), so summing Fin_AdvancePay alone dropped every
     * advance adjusted through the new payment approval.
     */
    private function paymentStageAdvanceSql(): string
    {
        $table = 'y'.session('year_id').'_monthexpensefinal';
        try {
            $hasAdj = DB::connection(session('expense_connection'))->getSchemaBuilder()->hasColumn($table, 'total_adjustment');
        } catch (\Throwable $e) {
            $hasAdj = false;
        }

        return $hasAdj
            ? 'COALESCE(SUM(COALESCE(NULLIF(m.Fin_AdvancePay + 0, 0), m.total_adjustment, 0)), 0)'
            : 'COALESCE(SUM(m.Fin_AdvancePay), 0)';
    }

    private function applyFilters($query, Request $request)
    {
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'm.EmployeeID', $hrimsDb);

        // Apply employee filter
        if ($request->filled('employee_ids')) {
            $employeeIds = is_array($request->employee_ids) ? $request->employee_ids : explode(',', $request->employee_ids);
            $query->whereIn('m.EmployeeID', $employeeIds);
        }
    }

    private function getDataWithEmployeeDetails($query, $months)
    {
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query->leftJoin("{$hrimsDb}.hrm_employee as e", 'm.EmployeeID', '=', 'e.EmployeeID');

        if (empty($months)) {
            return $query
                ->select(
                    DB::raw("'All Months' as Month"),
                    DB::raw("CONCAT(COALESCE(e.Fname,''), ' ', COALESCE(e.Sname,''), ' ', COALESCE(e.Lname,'')) as EmployeeName"),
                    DB::raw("COALESCE(e.EmpCode, '') as EmpCode"),
                    DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                    DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                    DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                    DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                    DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                    DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
                )
                ->groupBy('e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname')
                ->orderBy('e.EmpCode')
                ->get();
        } else {
            return $query
                ->select(
                    'm.Month',
                    DB::raw("CONCAT(COALESCE(e.Fname,''), ' ', COALESCE(e.Sname,''), ' ', COALESCE(e.Lname,'')) as EmployeeName"),
                    DB::raw("COALESCE(e.EmpCode, '') as EmpCode"),
                    DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                    DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                    DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                    DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                    DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                    DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
                )
                ->whereIn('m.Month', $months)
                ->groupBy('e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'm.Month')
                ->orderBy('e.EmpCode')
                ->orderBy('m.Month')
                ->get();
        }
    }

    private function getDataWithoutEmployeeDetails($query, $months)
    {
        if (empty($months)) {
            return $query->select(
                DB::raw("'All Months' as Month"),
                DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
            )->get();
        } else {
            return $query
                ->select(
                    'm.Month',
                    DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                    DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                    DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                    DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                    DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                    DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
                )
                ->whereIn('m.Month', $months)
                ->groupBy('m.Month')
                ->orderBy('m.Month')
                ->get();
        }
    }

    private function formatDataForDatatable($results, $withEmployee)
    {
        $data = [];
        $counter = 1;

        foreach ($results as $row) {
            $formattedRow = [
                'DT_RowIndex' => $counter++,
                'Month' => $this->formatMonth($row),
                'filled_amount' => number_format($row->filled_amount, 2),
                'verified_amount' => number_format($row->verified_amount, 2),
                'approved_amount' => number_format($row->approved_amount, 2),
                'financed_amount' => number_format($row->financed_amount, 2),
                'advance_amount' => number_format($row->advance_amount, 2),
                'paid_amount' => number_format($row->paid_amount, 2),
            ];

            if ($withEmployee && isset($row->EmployeeName)) {
                $formattedRow['EmployeeName'] = trim($row->EmployeeName) ?: 'N/A';
                $formattedRow['EmpCode'] = $row->EmpCode ?: 'N/A';
            }

            $data[] = $formattedRow;
        }

        return $data;
    }

    private function formatMonth($row)
    {
        if (isset($row->Month)) {
            if ($row->Month == 'All Months') {
                return 'All Months';
            }
            if (is_numeric($row->Month)) {
                return \Carbon\Carbon::create()->month((int) $row->Month)->format('F');
            }

            return $row->Month;
        }

        return 'N/A';
    }

    public function exportPaymentStages(Request $request)
    {
        try {
            // Validate required parameters
            // $year = $request->input('year');
            // if (! $year) {
            //     return redirect()->back()->with('error', 'Year is required for export');
            // }

            $year_id = session('year_id');
            if (! $year_id) {
                return redirect()->back()->with('error', 'Year session not found');
            }

            $table = "y{$year_id}_monthexpensefinal";
            $withEmployee = $request->boolean('with_employee', false);

            // Check if table exists
            $tableExists = DB::connection(session('expense_connection'))
                ->getSchemaBuilder()
                ->hasTable($table);

            if (! $tableExists) {
                return redirect()->back()->with('error', 'Data table not found for selected year');
            }

            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $query = DB::connection(session('expense_connection'))
                ->table($table.' as m')
                ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'm.EmployeeID', '=', 'eg.EmployeeID');
            // ->whereYear('m.CreatedDate', $year);

            // Apply filters
            $this->applyFilters($query, $request);

            $months = $request->input('months', []);
            if (is_string($months)) {
                $months = array_filter(explode(',', $months));
            }

            // Validate months
            if (! empty($months)) {
                $validMonths = array_intersect($months, range(1, 12));
                if (empty($validMonths)) {
                    return redirect()->back()->with('error', 'No valid months selected');
                }
                $months = $validMonths;
            }

            // Get data based on employee detail requirement
            if ($withEmployee) {
                $data = $this->getExportDataWithEmployeeDetails($query, $months);
            } else {
                $data = $this->getExportDataWithoutEmployeeDetails($query, $months);
            }

            if ($data->isEmpty()) {
                return redirect()->back()->with('warning', 'No data found for the selected filters');
            }

            $fileName = 'Payment_Summary_'.date('Ymd_His').'.xlsx';

            return Excel::download(new PaymentStagesSummaryExport($data, $withEmployee), $fileName);
        } catch (\Exception $e) {
            Log::error('Payment Stages Export Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            // dd($e->getMessage());

            return redirect()->back()->with('error', 'Export failed: '.$e->getMessage());
        }
    }

    private function getExportDataWithEmployeeDetails($query, $months)
    {
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query->leftJoin("{$hrimsDb}.hrm_employee as e", 'm.EmployeeID', '=', 'e.EmployeeID');

        if (empty($months)) {
            $data = $query
                ->select(
                    DB::raw("'All Months' as Month"),
                    DB::raw("CONCAT(COALESCE(e.Fname,''), ' ', COALESCE(e.Sname,''), ' ', COALESCE(e.Lname,'')) as EmployeeName"),
                    DB::raw("COALESCE(e.EmpCode, '') as EmpCode"),
                    DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                    DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                    DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                    DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                    DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                    DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
                )
                ->groupBy('e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname')
                ->orderBy('e.EmpCode')
                ->get();

            // Add calculated column
            foreach ($data as $item) {
                $item->adv_plus_paid = (float) $item->advance_amount + (float) $item->paid_amount;
            }

            return $data;
        } else {
            $data = $query
                ->select(
                    DB::raw("CASE
        WHEN m.Month = 1 THEN 'January'
        WHEN m.Month = 2 THEN 'February'
        WHEN m.Month = 3 THEN 'March'
        WHEN m.Month = 4 THEN 'April'
        WHEN m.Month = 5 THEN 'May'
        WHEN m.Month = 6 THEN 'June'
        WHEN m.Month = 7 THEN 'July'
        WHEN m.Month = 8 THEN 'August'
        WHEN m.Month = 9 THEN 'September'
        WHEN m.Month = 10 THEN 'October'
        WHEN m.Month = 11 THEN 'November'
        WHEN m.Month = 12 THEN 'December'
    END as Month"),
                    DB::raw("CONCAT(COALESCE(e.Fname,''), ' ', COALESCE(e.Sname,''), ' ', COALESCE(e.Lname,'')) as EmployeeName"),
                    DB::raw("COALESCE(e.EmpCode, '') as EmpCode"),
                    DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                    DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                    DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                    DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                    DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                    DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
                )
                ->whereIn('m.Month', $months)
                ->groupBy('e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'm.Month')
                ->orderBy('e.EmpCode')
                ->orderBy('m.Month')
                ->get();

            // Add calculated column
            foreach ($data as $item) {
                $item->adv_plus_paid = (float) $item->advance_amount + (float) $item->paid_amount;
            }

            return $data;
        }
    }

    private function getExportDataWithoutEmployeeDetails($query, $months)
    {
        if (empty($months)) {
            return $query->select(
                DB::raw("'All Months' as month"),
                DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
            )->get();
        } else {
            return $query
                ->select(
                    DB::raw("CASE
                    WHEN m.Month = 1 THEN 'January'
                    WHEN m.Month = 2 THEN 'February'
                    WHEN m.Month = 3 THEN 'March'
                    WHEN m.Month = 4 THEN 'April'
                    WHEN m.Month = 5 THEN 'May'
                    WHEN m.Month = 6 THEN 'June'
                    WHEN m.Month = 7 THEN 'July'
                    WHEN m.Month = 8 THEN 'August'
                    WHEN m.Month = 9 THEN 'September'
                    WHEN m.Month = 10 THEN 'October'
                    WHEN m.Month = 11 THEN 'November'
                    WHEN m.Month = 12 THEN 'December'
                END as month"),
                    DB::raw('COALESCE(SUM(m.Claim_Amount), 0) as filled_amount'),
                    DB::raw('COALESCE(SUM(m.Verified_Amount), 0) as verified_amount'),
                    DB::raw('COALESCE(SUM(m.Approved_Amount), 0) as approved_amount'),
                    DB::raw('COALESCE(SUM(m.Finance_Amount), 0) as financed_amount'),
                    DB::raw($this->paymentStageAdvanceSql().' as advance_amount'),
                    DB::raw('COALESCE(SUM(m.Fin_PayAmt), 0) as paid_amount')
                )
                ->whereIn('m.Month', $months)
                ->groupBy('m.Month')
                ->orderBy('m.Month')
                ->get();
        }
    }
}