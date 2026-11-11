<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpenMonthController extends Controller
{
    /**
     * Display the open month management page
     */
    public function index(Request $request)
    {
        $departmentId = $request->get('d', 0);
        $yearId = \App\Support\CurrentYear::id();
        $companyId = (int) session('company_id', 1);

        // Get departments list from HRIMS
        $hrimsConnection = session('hrims_connection', DatabaseSwitcher::getHrimsConnection());
        $departments = DB::connection($hrimsConnection)
            ->table('core_departments')
            ->where('is_active', 1)
            ->orderBy('department_name')
            ->get(['id as DepartmentId', 'department_code as DepartmentCode', 'department_name as DepartmentName']);

        // Get employees for selected department
        $employees = collect();
        if ($departmentId > 0) {
            $employees = DB::connection($hrimsConnection)
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->join('core_grades as gr', 'g.GradeId', '=', 'gr.id')
                ->where('g.DepartmentId', $departmentId)
                ->where('e.EmpStatus', 'A')
                ->where('e.CompanyId', $companyId)
                ->orderBy('e.ECode')
                ->select('e.EmpCode', 'e.EmployeeID', 'e.Fname', 'e.Sname', 'e.Lname', 'gr.grade_name as GradeValue')
                ->get();

            // Get available months for each employee
            $expenseConnection = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
            foreach ($employees as $emp) {
                $months = DB::connection($expenseConnection)
                    ->table("y{$yearId}_monthexpensefinal")
                    ->where('EmployeeID', $emp->EmployeeID)
                    ->where('YearId', $yearId)
                    ->orderBy('Month')
                    ->pluck('Month')
                    ->toArray();
                $emp->available_months = $months;
            }
        }

        return view('admin.open-month', compact('departments', 'employees', 'departmentId', 'yearId', 'companyId'));
    }

    /**
     * Open a month for an employee
     */
    public function openMonth(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $employeeId = $request->employee_id;
        $month = $request->month;
        $yearId = \App\Support\CurrentYear::id();
        $expenseConnection = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
        $table = "y{$yearId}_monthexpensefinal";

        // Check if month already exists
        $exists = DB::connection($expenseConnection)
            ->table($table)
            ->where('EmployeeID', $employeeId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Month already available for this employee.'
            ]);
        }

        // Insert new month
        DB::connection($expenseConnection)->table($table)->insert([
            'EmployeeID' => $employeeId,
            'Month' => $month,
            'YearId' => $yearId,
            'Status' => 'Open',
            'Crdate' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Month opened successfully!'
        ]);
    }
}
