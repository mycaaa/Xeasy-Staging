<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FilterController extends Controller
{
    public function getEmployeesByDepartment(Request $request)
    {
        try {
            $departmentIds = $request->input('department_ids', []);
            $subDepartmentIds = $request->input('sub_department_ids', []);

            $query = DB::connection('hrims')->table('hrm_employee')
                ->select('hrm_employee.EmployeeID', 'hrm_employee.EmpCode', 'hrm_employee.Fname', 'hrm_employee.Sname', 'hrm_employee.Lname', 'hrm_employee.EmpStatus')
                ->join('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                ->where('hrm_employee.CompanyId', session('company_id'));

            if (!empty($departmentIds)) {
                $query->whereIn('hrm_employee_general.DepartmentId', $departmentIds);
            }
            if (!empty($subDepartmentIds)) {
                $query->whereIn('hrm_employee_general.SubDepartmentId', $subDepartmentIds);
            }

            $employees = $query->get();
            return $this->jsonSuccess($employees, 'Employees loaded successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Failed to load employees.');
        }
    }

    public function getVerticalsByFunction(Request $request)
    {
        try {
            $functionIds = $request->input('function_ids', []);

            $query = DB::connection('hrims')->table('core_verticals as v')
                ->select('v.id', 'v.vertical_name');

            if (!empty($functionIds)) {
                $query->join('core_vertical_function_mapping as vfm', 'v.id', '=', 'vfm.vertical_id')
                    ->whereIn('vfm.org_function_id', $functionIds);
            }

            $verticals = $query->groupBy('v.id', 'v.vertical_name')->get();
            return $this->jsonSuccess($verticals, 'Verticals loaded successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Failed to load verticals.');
        }
    }

    public function getDepartmentsByVertical(Request $request)
    {
        try {
            $verticalIds = $request->input('vertical_ids', []);

            $query = DB::connection('hrims')->table('core_departments as d')
                ->select('d.id', 'd.department_name');

            if (!empty($verticalIds)) {
                $query->join('core_fun_vertical_dept_mapping as vdm', 'd.id', '=', 'vdm.department_id')
                    ->whereIn('vdm.function_vertical_id', function ($subQuery) use ($verticalIds) {
                        $subQuery->select('vfm.id')
                            ->from('core_function_vertical_mapping   as vfm')
                            ->whereIn('vfm.vertical_id', $verticalIds);
                    });
            }

            $departments = $query->groupBy('d.id', 'd.department_name')->get();
            return $this->jsonSuccess($departments, 'Departments loaded successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Failed to load departments.');
        }
    }

    public function getSubDepartmentsByDepartment(Request $request)
    {
        try {
            $departmentIds = $request->input('department_ids', []);

            $query = DB::connection('hrims')->table('core_sub_department_master as sd')
                ->select('sd.id', 'sd.sub_department_name');

            if (!empty($departmentIds)) {
                $query->join('core_sub_department_mapping as sdm', 'sd.id', '=', 'sdm.sub_department_id')
                    ->whereIn('sdm.fun_vertical_dept_id', function ($subQuery) use ($departmentIds) {
                        $subQuery->select('vdm.id')
                            ->from('core_vertical_department_mapping as vdm')
                            ->whereIn('vdm.department_id', $departmentIds);
                    });
            }

            $subDepartments = $query->groupBy('sd.id', 'sd.sub_department_name')->get();
            return $this->jsonSuccess($subDepartments, 'Sub-Departments loaded successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Failed to load sub-departments.');
        }
    }
}