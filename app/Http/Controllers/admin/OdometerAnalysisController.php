<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OdometerAnalysisController extends Controller
{
    public function index()
    {
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $expConn = session('expense_connection', 'expense');
        $yearId = session('year_id', 8);

        $departments = DB::connection('hrims')->table('core_departments')
            ->where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name']);

        $verticals = DB::connection('hrims')->table('core_verticals')
            ->where('is_active', 1)->orderBy('vertical_name')->get(['id', 'vertical_name']);

        $functions = DB::connection('hrims')->table('core_functions')
            ->where('is_active', 1)->orderBy('function_name')->get(['id', 'function_name']);

        $grades = DB::connection('hrims')->table('core_grades')
            ->where('is_active', 1)->orderBy('grade_name')->get(['id', 'grade_name']);

        $employees = DB::connection('hrims')->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->where('e.EmpStatus', 'A')
            ->where('e.CompanyId', session('company_id', 3))
            ->select('e.EmployeeID', 'e.EmpCode_New as EmpCode',
                DB::raw("CONCAT(TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname))) AS employee_name"))
            ->orderBy('e.EmpCode_New')
            ->get();

        return view('admin.odometer-analysis-report', compact(
            'departments', 'verticals', 'functions', 'grades', 'employees'
        ));
    }

    public function getSummary(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $vehicleType = $request->input('vehicle_type');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $claimTable = "y{$yearId}_expenseclaims";
            $baseQuery = function() use ($expConn, $wheelTable, $hrimsDb, $claimTable) {
                return DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                    ->join(DB::raw("{$claimTable} ec"), 'we.ExpId', '=', 'ec.ExpId')
                    ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                    ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID');
            };

            $applyFilters = function($q) use ($departmentId, $verticalId, $functionId, $gradeId, $employeeId, $vehicleType, $month, $yearId) {
                if ($departmentId) {
                    $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                    $q->whereIn('g.DepartmentId', $depts);
                }
                if ($verticalId) {
                    $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                    $q->whereIn('g.EmpVertical', $verts);
                }
                if ($functionId) {
                    $funcs = is_array($functionId) ? $functionId : [$functionId];
                    $q->whereIn('g.EmpFunction', $funcs);
                }
                if ($gradeId) {
                    $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                    $q->whereIn('g.GradeId', $grades);
                }
                if ($employeeId) {
                    $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                    $q->whereIn('ec.CrBy', $emps);
                }
                if ($vehicleType === '2W') {
                    $q->whereIn('we.WType', [1, 2]);
                } elseif ($vehicleType === '4W') {
                    $q->whereIn('we.WType', [3, 4]);
                }
                if ($month && $month !== '') {
                    $q->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
                }
                return $q;
            };

            $query = $baseQuery();
            $query = $applyFilters($query);

            $summary = $query->selectRaw(
                'COUNT(DISTINCT we.WheelId) as total_claims,
                 COALESCE(SUM(we.Totalkm), 0) as total_km,
                 COALESCE(AVG(we.Totalkm), 0) as avg_km,
                 COALESCE(MAX(we.Totalkm), 0) as max_km,
                 COALESCE(MIN(we.Totalkm), 0) as min_km,
                 COALESCE(SUM(we.FilledTAmt), 0) as total_amount'
            )->first();

            // Get 2W breakdown
            $query2W = $baseQuery();
            $query2W = $applyFilters($query2W);
            $twoWheeler = $query2W->whereIn('we.WType', [1, 2])
                ->selectRaw('COUNT(DISTINCT we.WheelId) as claims_2w, COALESCE(SUM(we.Totalkm), 0) as total_km_2w')
                ->first();

            // Get 4W breakdown
            $query4W = $baseQuery();
            $query4W = $applyFilters($query4W);
            $fourWheeler = $query4W->whereIn('we.WType', [3, 4])
                ->selectRaw('COUNT(DISTINCT we.WheelId) as claims_4w, COALESCE(SUM(we.Totalkm), 0) as total_km_4w')
                ->first();

            $data = [
                'total_claims' => (int)($summary->total_claims ?? 0),
                'total_km' => (float)($summary->total_km ?? 0),
                'avg_km' => (float)($summary->avg_km ?? 0),
                'max_km' => (float)($summary->max_km ?? 0),
                'min_km' => (float)($summary->min_km ?? 0),
                'total_amount' => (float)($summary->total_amount ?? 0),
                'claims_2w' => (int)($twoWheeler->claims_2w ?? 0),
                'total_km_2w' => (float)($twoWheeler->total_km_2w ?? 0),
                'claims_4w' => (int)($fourWheeler->claims_4w ?? 0),
                'total_km_4w' => (float)($fourWheeler->total_km_4w ?? 0),
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer Summary Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getEmployeeWise(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $vehicleType = $request->input('vehicle_type');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $query = DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                ->join(DB::raw("y{$yearId}_expenseclaims ec"), 'we.ExpId', '=', 'ec.ExpId')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id');

            if ($departmentId) {
                $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                $query->whereIn('g.DepartmentId', $depts);
            }
            if ($verticalId) {
                $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                $query->whereIn('g.EmpVertical', $verts);
            }
            if ($functionId) {
                $funcs = is_array($functionId) ? $functionId : [$functionId];
                $query->whereIn('g.EmpFunction', $funcs);
            }
            if ($gradeId) {
                $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                $query->whereIn('g.GradeId', $grades);
            }
            if ($employeeId) {
                $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                $query->whereIn('ec.CrBy', $emps);
            }
            if ($vehicleType === '2W') {
                $query->whereIn('we.WType', [1, 2]);
            } elseif ($vehicleType === '4W') {
                $query->whereIn('we.WType', [3, 4]);
            }
            if ($month && $month !== '') {
                $query->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
            }

            $data = $query->selectRaw(
                'ec.CrBy as employee_id,
                 e.EmpCode_New as emp_code,
                 CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname))) as employee_name,
                 COALESCE(dept.department_name, "N/A") as department_name,
                 COUNT(DISTINCT we.WheelId) as total_claims,
                 COALESCE(SUM(we.Totalkm), 0) as total_km,
                 COALESCE(AVG(we.Totalkm), 0) as avg_km,
                 COALESCE(SUM(CASE WHEN we.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                 COALESCE(SUM(CASE WHEN we.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                 COALESCE(SUM(we.FilledTAmt), 0) as total_amount'
            )
            ->groupBy('ec.CrBy', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer Employee Wise Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getTwoWheeler(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $query = DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                ->join(DB::raw("y{$yearId}_expenseclaims ec"), 'we.ExpId', '=', 'ec.ExpId')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->whereIn('we.WType', [1, 2]);

            if ($departmentId) {
                $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                $query->whereIn('g.DepartmentId', $depts);
            }
            if ($verticalId) {
                $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                $query->whereIn('g.EmpVertical', $verts);
            }
            if ($functionId) {
                $funcs = is_array($functionId) ? $functionId : [$functionId];
                $query->whereIn('g.EmpFunction', $funcs);
            }
            if ($gradeId) {
                $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                $query->whereIn('g.GradeId', $grades);
            }
            if ($employeeId) {
                $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                $query->whereIn('ec.CrBy', $emps);
            }
            if ($month && $month !== '') {
                $query->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
            }

            $data = $query->selectRaw(
                'ec.CrBy as employee_id,
                 e.EmpCode_New as emp_code,
                 CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname))) as employee_name,
                 COALESCE(dept.department_name, "N/A") as department_name,
                 COUNT(DISTINCT we.WheelId) as total_claims,
                 COALESCE(SUM(we.Totalkm), 0) as total_km,
                 COALESCE(AVG(we.Totalkm), 0) as avg_km,
                 COALESCE(AVG(we.Rate_PerKm), 0) as avg_rate_per_km,
                 COALESCE(SUM(we.FilledTAmt), 0) as total_amount'
            )
            ->groupBy('ec.CrBy', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer 2-Wheeler Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getFourWheeler(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $query = DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                ->join(DB::raw("y{$yearId}_expenseclaims ec"), 'we.ExpId', '=', 'ec.ExpId')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->whereIn('we.WType', [3, 4]);

            if ($departmentId) {
                $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                $query->whereIn('g.DepartmentId', $depts);
            }
            if ($verticalId) {
                $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                $query->whereIn('g.EmpVertical', $verts);
            }
            if ($functionId) {
                $funcs = is_array($functionId) ? $functionId : [$functionId];
                $query->whereIn('g.EmpFunction', $funcs);
            }
            if ($gradeId) {
                $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                $query->whereIn('g.GradeId', $grades);
            }
            if ($employeeId) {
                $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                $query->whereIn('ec.CrBy', $emps);
            }
            if ($month && $month !== '') {
                $query->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
            }

            $data = $query->selectRaw(
                'ec.CrBy as employee_id,
                 e.EmpCode_New as emp_code,
                 CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname))) as employee_name,
                 COALESCE(dept.department_name, "N/A") as department_name,
                 COUNT(DISTINCT we.WheelId) as total_claims,
                 COALESCE(SUM(we.Totalkm), 0) as total_km,
                 COALESCE(AVG(we.Totalkm), 0) as avg_km,
                 COALESCE(AVG(we.Rate_PerKm), 0) as avg_rate_per_km,
                 COALESCE(SUM(we.FilledTAmt), 0) as total_amount'
            )
            ->groupBy('ec.CrBy', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer 4-Wheeler Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getDepartmentWise(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $vehicleType = $request->input('vehicle_type');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $query = DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                ->join(DB::raw("y{$yearId}_expenseclaims ec"), 'we.ExpId', '=', 'ec.ExpId')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id');

            if ($departmentId) {
                $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                $query->whereIn('g.DepartmentId', $depts);
            }
            if ($verticalId) {
                $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                $query->whereIn('g.EmpVertical', $verts);
            }
            if ($functionId) {
                $funcs = is_array($functionId) ? $functionId : [$functionId];
                $query->whereIn('g.EmpFunction', $funcs);
            }
            if ($gradeId) {
                $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                $query->whereIn('g.GradeId', $grades);
            }
            if ($employeeId) {
                $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                $query->whereIn('ec.CrBy', $emps);
            }
            if ($vehicleType === '2W') {
                $query->whereIn('we.WType', [1, 2]);
            } elseif ($vehicleType === '4W') {
                $query->whereIn('we.WType', [3, 4]);
            }
            if ($month && $month !== '') {
                $query->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
            }

            $data = $query->selectRaw(
                'dept.id as dept_id,
                 dept.department_name,
                 COUNT(DISTINCT we.WheelId) as total_claims,
                 COALESCE(SUM(we.Totalkm), 0) as total_km,
                 COALESCE(AVG(we.Totalkm), 0) as avg_km,
                 COALESCE(SUM(CASE WHEN we.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                 COALESCE(SUM(CASE WHEN we.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                 COALESCE(SUM(we.FilledTAmt), 0) as total_amount'
            )
            ->groupBy('dept.id', 'dept.department_name')
            ->orderByDesc('total_km')
            ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer Department Wise Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getVerticalWise(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $vehicleType = $request->input('vehicle_type');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $query = DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                ->join(DB::raw("y{$yearId}_expenseclaims ec"), 'we.ExpId', '=', 'ec.ExpId')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_verticals ver"), 'g.EmpVertical', '=', 'ver.id');

            if ($departmentId) {
                $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                $query->whereIn('g.DepartmentId', $depts);
            }
            if ($verticalId) {
                $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                $query->whereIn('g.EmpVertical', $verts);
            }
            if ($functionId) {
                $funcs = is_array($functionId) ? $functionId : [$functionId];
                $query->whereIn('g.EmpFunction', $funcs);
            }
            if ($gradeId) {
                $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                $query->whereIn('g.GradeId', $grades);
            }
            if ($employeeId) {
                $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                $query->whereIn('ec.CrBy', $emps);
            }
            if ($vehicleType === '2W') {
                $query->whereIn('we.WType', [1, 2]);
            } elseif ($vehicleType === '4W') {
                $query->whereIn('we.WType', [3, 4]);
            }
            if ($month && $month !== '') {
                $query->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
            }

            $data = $query->selectRaw(
                'ver.id as vertical_id,
                 ver.vertical_name,
                 COUNT(DISTINCT we.WheelId) as total_claims,
                 COALESCE(SUM(we.Totalkm), 0) as total_km,
                 COALESCE(AVG(we.Totalkm), 0) as avg_km,
                 COALESCE(SUM(CASE WHEN we.WType IN (1,2) THEN we.Totalkm ELSE 0 END), 0) as km_2w,
                 COALESCE(SUM(CASE WHEN we.WType IN (3,4) THEN we.Totalkm ELSE 0 END), 0) as km_4w,
                 COALESCE(SUM(we.FilledTAmt), 0) as total_amount'
            )
            ->groupBy('ver.id', 'ver.vertical_name')
            ->orderByDesc('total_km')
            ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer Vertical Wise Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $expConn = session('expense_connection', 'expense');
            $yearId = (int)$request->input('year_id', session('year_id', 8));
            $departmentId = $request->input('department_id');
            $verticalId = $request->input('vertical_id');
            $functionId = $request->input('function_id');
            $gradeId = $request->input('grade_id');
            $employeeId = $request->input('employee_id');
            $vehicleType = $request->input('vehicle_type');
            $month = $request->input('month');

            $wheelTable = "y{$yearId}_24_wheeler_entry";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

            $query = DB::connection($expConn)->table(DB::raw("{$wheelTable} we"))
                ->join(DB::raw("y{$yearId}_expenseclaims ec"), 'we.ExpId', '=', 'ec.ExpId')
                ->join(DB::raw("{$hrimsDb}.hrm_employee e"), 'ec.CrBy', '=', 'e.EmployeeID')
                ->join(DB::raw("{$hrimsDb}.hrm_employee_general g"), 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin(DB::raw("{$hrimsDb}.core_departments dept"), 'g.DepartmentId', '=', 'dept.id')
                ->leftJoin(DB::raw("{$hrimsDb}.core_verticals ver"), 'g.EmpVertical', '=', 'ver.id');

            if ($departmentId) {
                $depts = is_array($departmentId) ? $departmentId : [$departmentId];
                $query->whereIn('g.DepartmentId', $depts);
            }
            if ($verticalId) {
                $verts = is_array($verticalId) ? $verticalId : [$verticalId];
                $query->whereIn('g.EmpVertical', $verts);
            }
            if ($functionId) {
                $funcs = is_array($functionId) ? $functionId : [$functionId];
                $query->whereIn('g.EmpFunction', $funcs);
            }
            if ($gradeId) {
                $grades = is_array($gradeId) ? $gradeId : [$gradeId];
                $query->whereIn('g.GradeId', $grades);
            }
            if ($employeeId) {
                $emps = is_array($employeeId) ? $employeeId : [$employeeId];
                $query->whereIn('ec.CrBy', $emps);
            }
            if ($vehicleType === '2W') {
                $query->whereIn('we.WType', [1, 2]);
            } elseif ($vehicleType === '4W') {
                $query->whereIn('we.WType', [3, 4]);
            }
            if ($month && $month !== '') {
                $query->where('ec.ClaimMonth', (int)$month)
                      ->where('ec.ClaimYearId', $yearId);
            }

            $data = $query->selectRaw(
                'e.EmpCode_New,
                 CONCAT(TRIM(CONCAT(e.Fname, \' \', COALESCE(e.Sname, \'\'), \' \', e.Lname))) as employee_name,
                 CASE WHEN we.WType IN (1,2) THEN "2-Wheeler" ELSE "4-Wheeler" END as vehicle_type,
                 we.Totalkm,
                 we.Rate_PerKm,
                 we.FilledTAmt,
                 dept.department_name,
                 ver.vertical_name,
                 ec.BillDate'
            )
            ->orderByDesc('ec.BillDate')
            ->orderBy('e.EmpCode_New')
            ->get();

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="Odometer_Analysis_'.date('Ymd_His').'.csv"',
            ];

            $filename = tempnam(sys_get_temp_dir(), 'csv');
            $handle = fopen($filename, 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Emp Code', 'Employee Name', 'Vehicle Type', 'Total KM', 'Rate/KM', 'Amount', 'Department', 'Vertical', 'Bill Date']);

            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->EmpCode_New,
                    $row->employee_name,
                    $row->vehicle_type,
                    $row->Totalkm,
                    $row->Rate_PerKm,
                    $row->FilledTAmt,
                    $row->department_name,
                    $row->vertical_name,
                    $row->BillDate,
                ]);
            }

            fclose($handle);
            return response()->download($filename, 'Odometer_Analysis_'.date('Ymd_His').'.csv', $headers)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error('Odometer Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
