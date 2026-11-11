<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\CoreFunctions;
use App\Models\CoreSubDepartment;
use App\Models\CoreVertical;
use App\Exports\AdvanceReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AdvanceReportController extends Controller
{
    public function index()
    {
        $companyId = session('company_id', 1);
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $employees = DB::connection($hrimsConnection)
            ->table('hrm_employee')
            ->select('hrm_employee.EmployeeID', 'hrm_employee.EmpCode', 'hrm_employee.Fname', 'hrm_employee.Sname', 'hrm_employee.Lname', 'hrm_employee.EmpStatus')
            ->join('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
            ->get();

        $activityTypes = DB::table('adv_activity_types')->where('status', 1)->orderBy('type_name')->get(['id', 'type_name']);

        $activityNames = DB::table('adv_activity_names as an')
            ->join('claimtype as ct', 'ct.ClaimId', '=', 'an.activity_name')
            ->where('an.status', 1)
            ->orderBy('ct.ClaimName')
            ->get(['an.id', 'ct.ClaimName']);

        return view('admin.advance_report', array_merge($organogramOptions, compact(
            'employees', 'activityTypes', 'activityNames'
        )));
    }

    public function export(Request $request)
    {
        ini_set('max_execution_time', 10000);
        ini_set('memory_limit', '2048M');

        $reportType = $request->input('reportType');

        if (!$reportType) {
            return response()->json(['status' => 'error', 'message' => 'Please select a report type'], 422);
        }

        $rawMonths = array_filter((array) $request->input('monthSelect', []), fn($m) => is_numeric($m) && $m > 0);

        $submitDateFrom  = $request->input('submitDateFrom');
        $submitDateTo    = $request->input('submitDateTo');
        $approveDateFrom = $request->input('approveDateFrom');
        $approveDateTo   = $request->input('approveDateTo');
        $hasDateRange    = $submitDateFrom || $submitDateTo || $approveDateFrom || $approveDateTo;

        if (empty($rawMonths) && !$hasDateRange) {
            return response()->json(['status' => 'error', 'message' => 'Please select a month or provide a date range filter'], 422);
        }

        $filters = [
            'months'              => array_values($rawMonths),
            'function_ids'        => (array) $request->input('functionSelect', []),
            'vertical_ids'        => (array) $request->input('verticalSelect', []),
            'department_ids'      => (array) $request->input('departmentSelect', []),
            'sub_department_ids'  => (array) $request->input('subDepartmentSelect', []),
            'bu_ids'              => (array) ($request->input('buSelect') ?: $request->input('bu_ids', [])),
            'zone_ids'            => (array) ($request->input('zoneSelect') ?: $request->input('zone_ids', [])),
            'region_ids'          => (array) ($request->input('regionSelect') ?: $request->input('region_ids', [])),
            'territory_ids'       => (array) ($request->input('territorySelect') ?: $request->input('territory_ids', [])),
            'hq_ids'              => (array) ($request->input('hqSelect') ?: $request->input('hq_ids', [])),
            'policy_ids'          => (array) ($request->input('policySelect') ?: $request->input('policy_ids', [])),
            'user_ids'            => (array) $request->input('userSelect', []),
            'activity_type_ids'   => (array) $request->input('activityTypeSelect', []),
            'activity_name_ids'   => (array) $request->input('activityNameSelect', []),
            'auto_advance'        => $request->input('autoAdvanceFilter', 'all'),
            'submit_date_from'    => $submitDateFrom,
            'submit_date_to'      => $submitDateTo,
            'approve_date_from'   => $approveDateFrom,
            'approve_date_to'     => $approveDateTo,
        ];

        $data = $this->buildQuery($filters, $reportType);

        $export   = new AdvanceReportExport($data, $reportType, $filters['months']);
        $fileName = 'Advance_Report_' . now()->format('Ymd_His') . '.xlsx';
        $path     = 'temp/' . $fileName;

        Excel::store($export, $path, 'public');

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['status' => 'error', 'message' => 'Failed to generate file'], 500);
        }

        return response()->json([
            'status'   => 'success',
            'file_url' => preg_replace('#([^:])//+#', '$1/', Storage::disk('public')->url($path)),
        ]);
    }

    // -------------------------------------------------------------------------
    // Query builder
    // -------------------------------------------------------------------------

    private function buildQuery(array $filters, string $reportType): \Illuminate\Support\Collection
    {
        $expenseConn  = \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $autoAdvance  = $filters['auto_advance'] ?? 'all';

        // ── Build a UNION subquery of y{n}_monthexpensefinal for all active years ──
        // We always include it so Claim_Amount is available; it's just NULL for manual rows.
        $years = DB::connection($expenseConn)
            ->table('financialyear')
            ->pluck('YearId');

        $unionParts = [];
        foreach ($years as $yearId) {
            $table = 'y' . $yearId . '_monthexpensefinal';
            // Check table exists before adding to union
            $exists = DB::connection($expenseConn)
                ->select("SHOW TABLES LIKE '{$table}'");
            if (!empty($exists)) {
                $unionParts[] = "SELECT EmployeeID, Month, YearId, Claim_Amount FROM `{$table}`";
            }
        }

        $claimAmountSubquery = null;
        if (!empty($unionParts)) {
            $claimAmountSubquery = '(' . implode(' UNION ALL ', $unionParts) . ')';
        }

        $query = DB::connection($expenseConn)->table('advance_requests as ar')
            ->join('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
            ->join('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
            ->join('hrims.hrm_employee as emp', 'emp.EmployeeID', '=', 'are.employee_id')
            ->join('hrims.hrm_employee_general as eg', 'eg.EmployeeID', '=', 'emp.EmployeeID')
            ->leftJoin('hrims.core_departments as dept', 'dept.id', '=', 'eg.DepartmentId')
            ->leftJoin('hrims.core_sub_department_master as sub_dept', 'sub_dept.id', '=', 'eg.SubDepartmentId')
            ->leftJoin('hrims.core_verticals as vert', 'vert.id', '=', 'eg.EmpVertical')
            ->leftJoin('hrims.core_functions as func', 'func.id', '=', 'eg.EmpFunction')
            ->leftJoin('hrims.hrm_employee as raised_emp', 'raised_emp.EmployeeID', '=', 'ar.req_app_submit_id')
            ->leftJoin('adv_activity_names as an', 'an.id', '=', 'ar.activity_name_id')
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'an.activity_name')
            ->leftJoin('advance_request_types as art', 'art.id', '=', 'ar.advance_type_id')
            ->leftJoin('adv_activity_types as at', 'at.id', '=', 'ara.activity_type_id')
            ->leftJoin('financialyear as fy', 'fy.YearId', '=', 'ar.year_id')
            ->leftJoin('advance_request_masters as arm', function ($join) {
                $join->on('arm.advance_type_id', '=', 'ar.advance_type_id')
                     ->on('arm.department_id', '=', 'eg.DepartmentId')
                     ->where('arm.is_active', 1);
            })
            ->whereNull('ar.deleted_at')
            ->whereNull('are.deleted_at')
            ->whereNull('ara.deleted_at');

        // Month filter — only applied when months are provided
        if (!empty($filters['months'])) {
            $query->whereIn('ar.month', $filters['months']);
        }

        // Join the UNION subquery for Claim_Amount
        $claimAmountExpr = DB::raw('NULL');
        if ($claimAmountSubquery) {
            $query->leftJoin(
                DB::raw("{$claimAmountSubquery} AS mef"),
                function ($join) {
                    $join->on('mef.EmployeeID', '=', 'are.employee_id')
                         ->on('mef.Month', '=', 'ar.month')
                         ->on('mef.YearId', '=', 'ar.year_id');
                }
            );
            $claimAmountExpr = DB::raw('mef.Claim_Amount as claim_amount');
        }

        $query->select(
            'ar.id as request_id',
            'ar.month',
            'ar.last_submitted_at as submitted_at',
            'fy.Year as financial_year',
            $claimAmountExpr,
            'ara.created_at as requested_date',
            'func.function_name',
            DB::raw("CASE 
                WHEN ar.is_imprest = 1 OR ar.req_app_submit_id = 0 OR ar.created_by = 0 THEN 'System Generated (Auto Advance for Imprest)'
                ELSE TRIM(CONCAT(COALESCE(raised_emp.Fname,''), ' ', COALESCE(raised_emp.Sname,''), ' ', COALESCE(raised_emp.Lname,'')))
            END as raised_by_name"),
            DB::raw("(
                SELECT MAX(aaa.approver_date)
                FROM advance_activity_approvals aaa
                WHERE aaa.advance_request_activity_id = ara.id
                  AND aaa.approver_status = 'Approved'
            ) as approval_date"),
            DB::raw("(
                SELECT MAX(aaa.updated_at)
                FROM advance_activity_approvals aaa
                WHERE aaa.advance_request_activity_id = ara.id
                  AND aaa.approver_status = 'Approved'
            ) as approval_datetime"),
            DB::raw("(
                SELECT TRIM(CONCAT(COALESCE(ae.Fname,''), ' ', COALESCE(ae.Sname,''), ' ', COALESCE(ae.Lname,'')))
                FROM advance_activity_approvals aaa
                JOIN hrims.hrm_employee ae ON ae.EmployeeID = aaa.approver_id
                WHERE aaa.advance_request_activity_id = ara.id
                  AND aaa.approver_status = 'Approved'
                ORDER BY aaa.approver_date DESC
                LIMIT 1
            ) as approved_by_name"),
            'are.employee_id',
            'emp.EmpCode as emp_code',
            DB::raw("TRIM(CONCAT(COALESCE(emp.Fname,''), ' ', COALESCE(emp.Sname,''), ' ', COALESCE(emp.Lname,''))) as emp_name"),
            'dept.department_name',
            'sub_dept.sub_department_name',
            'vert.vertical_name',
            'ct.ClaimName as activity_name',
            'art.type_name as advance_type_name',
            'at.type_name as activity_type_name',
            'ara.purpose',
            'ara.amount as requested_amount',
            'ara.approval_status',
            'ara.finance_approved_amount',
            'ara.finance_status',
            'ara.finance_date',
            'ara.payment_amount',
            'ara.payment_status',
            'ara.payment_date'
        );

        // Apply filters
        if (!empty($filters['function_ids'])) {
            $query->whereIn('eg.EmpFunction', $filters['function_ids']);
        }
        if (!empty($filters['vertical_ids'])) {
            $query->whereIn('eg.EmpVertical', $filters['vertical_ids']);
        }
        if (!empty($filters['department_ids'])) {
            $query->whereIn('eg.DepartmentId', $filters['department_ids']);
        }
        if (!empty($filters['sub_department_ids'])) {
            $query->whereIn('eg.SubDepartmentId', $filters['sub_department_ids']);
        }
        if (!empty($filters['bu_ids'])) {
            $query->whereIn('eg.BUId', $filters['bu_ids']);
        }
        if (!empty($filters['zone_ids'])) {
            $query->whereIn('eg.ZoneId', $filters['zone_ids']);
        }
        if (!empty($filters['region_ids'])) {
            $query->whereIn('eg.RegionId', $filters['region_ids']);
        }
        if (!empty($filters['territory_ids'])) {
            $query->whereIn('eg.TerrId', $filters['territory_ids']);
        }
        if (!empty($filters['hq_ids'])) {
            $query->whereIn('eg.HqId', $filters['hq_ids']);
        }
        if (!empty($filters['policy_ids'])) {
            $query->whereIn('are.employee_id', function ($sub) use ($filters) {
                $sub->select('EmployeeID')
                    ->from('hrims.hrm_employee_eligibility')
                    ->where('Status', 'A')
                    ->whereIn('VehiclePolicy', $filters['policy_ids']);
            });
        }
        if (!empty($filters['user_ids'])) {
            $query->whereIn('are.employee_id', $filters['user_ids']);
        }
        if (!empty($filters['activity_type_ids'])) {
            $query->whereIn('ara.activity_type_id', $filters['activity_type_ids']);
        }
        if (!empty($filters['activity_name_ids'])) {
            $query->whereIn('ar.activity_name_id', $filters['activity_name_ids']);
        }
        if ($autoAdvance === 'auto') {
            $query->where('ara.purpose', 'Auto Advance based on expense percentage');
        } elseif ($autoAdvance === 'manual') {
            $query->where(function ($q) {
                $q->whereNull('ara.purpose')
                  ->orWhere('ara.purpose', '!=', 'Auto Advance based on expense percentage');
            });
        }

        // Submit date range filter (ar.last_submitted_at)
        if (!empty($filters['submit_date_from'])) {
            $query->whereDate('ar.last_submitted_at', '>=', $filters['submit_date_from']);
        }
        if (!empty($filters['submit_date_to'])) {
            $query->whereDate('ar.last_submitted_at', '<=', $filters['submit_date_to']);
        }

        // Approve date range filter (from advance_activity_approvals)
        if (!empty($filters['approve_date_from']) || !empty($filters['approve_date_to'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('advance_activity_approvals as aaa_f')
                    ->whereColumn('aaa_f.advance_request_activity_id', 'ara.id')
                    ->where('aaa_f.approver_status', 'Approved');
                if (!empty($filters['approve_date_from'])) {
                    $sub->whereDate('aaa_f.approver_date', '>=', $filters['approve_date_from']);
                }
                if (!empty($filters['approve_date_to'])) {
                    $sub->whereDate('aaa_f.approver_date', '<=', $filters['approve_date_to']);
                }
            });
        }

        return $query->orderBy('ar.month')->orderBy('emp.EmpCode')->get();
    }
}
