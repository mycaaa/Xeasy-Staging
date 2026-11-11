<?php

namespace App\Http\Controllers\admin;

use App\Exports\EmployeeReportExport;
use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeReportController extends Controller
{
    /**
     * Common query builder for all employee fields.
     */
    private function buildQuery(Request $request, string $hrimsConn, int $companyId)
    {
        $query = DB::connection($hrimsConn)
            ->table('hrm_employee as e')
            ->leftJoin('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin('core_grades as g',           'eg.GradeId',    '=', 'g.id')
            ->leftJoin('core_departments as d',      'eg.DepartmentId','=', 'd.id')
            ->leftJoin('core_designation as des',    'eg.DesigId',    '=', 'des.id')
            ->leftJoin('core_verticals as v',        'eg.EmpVertical','=', 'v.id')
            ->leftJoin('core_territory as tr',       'eg.TerrId',     '=', 'tr.id')
            ->leftJoin('core_city_village_by_state as hq', 'eg.HqId', '=', 'hq.id')
            ->leftJoin('core_zones as zn',           'eg.ZoneId',     '=', 'zn.id')
            ->leftJoin('core_regions as rg',         'eg.RegionId',   '=', 'rg.id')
            ->leftJoin('core_states as st',          'eg.CostCenter', '=', 'st.id')
            ->where('e.CompanyId', $companyId)
            ->select(
                'e.EmployeeID',
                DB::raw("COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code"),
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS emp_name"),
                'e.EmpStatus as status',
                'e.DateOfResignation',
                'e.DateOfSepration',
                'eg.DateJoining as date_joining',
                'eg.MobileNo_Vnr as mobile',
                'g.grade_name',
                'd.department_name',
                'des.designation_name',
                'v.vertical_name',
                DB::raw('CASE WHEN eg.TerrId > 0 THEN tr.territory_name ELSE hq.city_village_name END as hq_name'),
                'zn.zone_name',
                'rg.region_name',
                'st.state_name',
                'eg.ReportingName as reporting_name',
                DB::raw("eg.EmailId_Vnr as email")
            );

        // Filters
        if ($request->filled('status')) {
            $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
            if ($request->status === 'A') {
                $query->where('e.EmpStatus', 'A')
                    ->where(function ($q) use ($blankDates) {
                        $q->whereNull('e.DateOfSepration')
                          ->orWhereIn('e.DateOfSepration', $blankDates);
                    })
                    ->where(function ($q) use ($blankDates) {
                        $q->whereNull('e.DateOfResignation')
                          ->orWhereIn('e.DateOfResignation', $blankDates);
                    });
            } elseif ($request->status === 'D') {
                $query->where(function ($q) use ($blankDates) {
                    $q->where('e.EmpStatus', '!=', 'A')
                      ->orWhere(function ($q2) use ($blankDates) {
                          $q2->whereNotNull('e.DateOfSepration')
                             ->whereNotIn('e.DateOfSepration', $blankDates);
                      })
                      ->orWhere(function ($q2) use ($blankDates) {
                          $q2->whereNotNull('e.DateOfResignation')
                             ->whereNotIn('e.DateOfResignation', $blankDates);
                      });
                });
            }
        }
        if ($request->filled('department')) {
            $query->where('eg.DepartmentId', $request->department);
        }
        if ($request->filled('grade')) {
            $query->where('eg.GradeId', $request->grade);
        }
        if ($request->filled('designation')) {
            $query->where('eg.DesigId', $request->designation);
        }
        if ($request->filled('vertical')) {
            $query->where('eg.EmpVertical', $request->vertical);
        }
        if ($request->filled('zone')) {
            $query->where('eg.ZoneId', $request->zone);
        }

        // Apply All Organogram & Policy Filters
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'e.EmployeeID', $hrimsConn);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereRaw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) LIKE ?", ["%{$s}%"])
                  ->orWhere('e.EmpCode_New', 'LIKE', "%{$s}%")
                  ->orWhere('e.EmpCode',     'LIKE', "%{$s}%");
            });
        }

        return $query->orderBy('d.department_name')
                     ->orderBy('g.grade_name')
                     ->orderBy('e.Fname');
    }

    public function index(Request $request)
    {
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $companyId = (int) session('company_id', 1);

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        // Filter dropdown options (grades and designations are specific to employee report)
        $grades       = DB::connection($hrimsConn)->table('core_grades')->orderBy('grade_name')->pluck('grade_name', 'id');
        $designations = DB::connection($hrimsConn)->table('core_designation')->orderBy('designation_name')->pluck('designation_name', 'id');

        $query = $this->buildQuery($request, $hrimsConn, $companyId);

        $total   = (clone $query)->count();
        $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
        $active  = (clone $query)
            ->where('e.EmpStatus', 'A')
            ->where(function ($q) use ($blankDates) {
                $q->whereNull('e.DateOfSepration')
                  ->orWhereIn('e.DateOfSepration', $blankDates);
            })
            ->where(function ($q) use ($blankDates) {
                $q->whereNull('e.DateOfResignation')
                  ->orWhereIn('e.DateOfResignation', $blankDates);
            })
            ->count();
        $inactive = $total - $active;

        $employees = $query->paginate(50)->withQueryString();

        return view('admin.employee-report', array_merge($organogramOptions, compact(
            'employees', 'grades', 'designations',
            'total', 'active', 'inactive'
        )));
    }

    public function export(Request $request)
    {
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $companyId = (int) session('company_id', 1);

        $employees = $this->buildQuery($request, $hrimsConn, $companyId)->get();

        $filename = 'Employee_Report_' . now()->format('d-M-Y') . '.xlsx';

        return Excel::download(new EmployeeReportExport($employees), $filename);
    }
}
