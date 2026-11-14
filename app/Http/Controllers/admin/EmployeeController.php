<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\HRMEmployeeGeneral;
use App\Services\ClaimActionLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
     public function search(Request $request)
    {
        try {
            // Get parameters
            $search = $request->input('search', '');
            $page = max(1, (int) $request->input('page', 1));
            $limit = 15;
            $offset = ($page - 1) * $limit;
            
            $claimantId = $request->input('claimant_id') ?: session('employee_id');
            $claimantCode = $request->input('claimant_code') ?: session('emp_code');
            $companyId = session('company_id');
            
            // Build query
            $query = DB::connection('hrims')->table('hrm_employee AS emp')
                ->select(
                    DB::raw("TRIM(CONCAT_WS(' ', emp.Fname, COALESCE(emp.Sname, ''), emp.Lname)) AS EmployeeName"),
                    'emp.EmployeeID',
                    'emp.EmpCode',
                    'emp.CompanyId', // PHOTO-01
                    DB::raw('emp.EmpCode AS EmpCode_New'),
                    'emp.EmpStatus',
                    'emp.DateOfResignation',
                    'emp.DateOfSepration',
                    'emp.companyid',
                    'eg.DepartmentId AS department_id',
                    'cg.grade_name',
                    'cd.department_name'
                )
                ->leftJoin('hrm_employee_general AS eg', 'emp.EmployeeID', '=', 'eg.EmployeeID')
                ->leftJoin('core_departments AS cd', 'cd.id', '=', 'eg.DepartmentId')
                ->leftJoin('core_grades AS cg', 'cg.id', '=', 'eg.GradeId');
            
            // Exclude self
            if ($request->boolean('exclude_claimant', false)) {
                if ($claimantId) $query->where('emp.EmployeeID', '!=', $claimantId);
                if ($claimantCode) {
                    $query->where('emp.EmpCode', '!=', $claimantCode);
                }
            }
            
            // Status filter:
            // - 'A' (Default: Active & Resigned): includes both active and resigned employees, excludes deactivated.
            // - 'pure_active' (or active_only=true): pure active employees only (no resignation / separation).
            // - 'R': resigned employees only.
            // - 'D': deactivated employees only.
            // - 'all': all employees regardless of status.
            $statusParam = $request->input('status', 'A');
            $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
            $today = date('Y-m-d');

            if ($request->boolean('active_only', false) || $statusParam === 'pure_active') {
                // Pure active only
                $query->where('emp.EmpStatus', 'A')
                    ->where(function ($q) use ($blankDates) {
                        $q->whereNull('emp.DateOfSepration')->orWhereIn('emp.DateOfSepration', $blankDates);
                    })
                    ->where(function ($q) use ($blankDates) {
                        $q->whereNull('emp.DateOfResignation')->orWhereIn('emp.DateOfResignation', $blankDates);
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('hrm_employee_separation AS sep')
                            ->whereColumn('sep.EmployeeID', 'emp.EmployeeID')
                            ->where('sep.Rep_Approved', '!=', 'C')
                            ->where('sep.Hod_Approved', '!=', 'C')
                            ->where('sep.HR_Approved', '!=', 'C');
                    });
            } elseif ($statusParam === 'R') {
                // Resigned only: not deactivated, and has resignation date or separation date >= today or active separation record
                $query->whereNotIn('emp.EmpStatus', ['D', 'De'])
                    ->where(function ($q) use ($blankDates, $today) {
                        $q->where(function ($q2) use ($blankDates) {
                            $q2->whereNotNull('emp.DateOfResignation')
                               ->whereNotIn('emp.DateOfResignation', $blankDates)
                               ->where('emp.DateOfResignation', '>', '2000-01-01');
                        })
                        ->orWhere(function ($q2) use ($blankDates, $today) {
                            $q2->whereNotNull('emp.DateOfSepration')
                               ->whereNotIn('emp.DateOfSepration', $blankDates)
                               ->where('emp.DateOfSepration', '>=', $today);
                        })
                        ->orWhereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('hrm_employee_separation AS sep')
                                ->whereColumn('sep.EmployeeID', 'emp.EmployeeID')
                                ->where('sep.Rep_Approved', '!=', 'C')
                                ->where('sep.Hod_Approved', '!=', 'C')
                                ->where('sep.HR_Approved', '!=', 'C');
                        });
                    })
                    ->where(function ($q) use ($blankDates, $today) {
                        $q->whereNull('emp.DateOfSepration')
                          ->orWhereIn('emp.DateOfSepration', $blankDates)
                          ->orWhere('emp.DateOfSepration', '>=', $today);
                    });
            } elseif ($statusParam === 'D') {
                // Deactivated only: EmpStatus is D/De or past DateOfSepration
                $query->where(function ($q) use ($blankDates, $today) {
                    $q->whereIn('emp.EmpStatus', ['D', 'De'])
                      ->orWhere(function ($q2) use ($blankDates, $today) {
                          $q2->whereNotNull('emp.DateOfSepration')
                             ->whereNotIn('emp.DateOfSepration', $blankDates)
                             ->where('emp.DateOfSepration', '<', $today);
                      });
                });
            } elseif ($statusParam === 'A') {
                // Active & Resigned (Default): exclude deactivated employees, include active and resigned
                $query->whereNotIn('emp.EmpStatus', ['D', 'De'])
                    ->where(function ($q) use ($blankDates, $today) {
                        $q->whereNull('emp.DateOfSepration')
                          ->orWhereIn('emp.DateOfSepration', $blankDates)
                          ->orWhere('emp.DateOfSepration', '>=', $today);
                    });
            }
            // If statusParam === 'all', do not apply any status filter
            
            // Session-wise company filter for standard header / employee search
            if ($request->filled('company_id')) {
                $query->where('emp.companyid', $request->company_id);
            } elseif ($companyId) {
                $query->where('emp.companyid', $companyId);
            }
            
            // Search
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('emp.Fname', 'LIKE', "%{$search}%")
                      ->orWhere('emp.Lname', 'LIKE', "%{$search}%")
                      ->orWhere('emp.EmpCode', 'LIKE', "%{$search}%")
                      ->orWhere('cd.department_name', 'LIKE', "%{$search}%")
                      ->orWhere('cg.grade_name', 'LIKE', "%{$search}%")
                      ->orWhereRaw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) LIKE ?", ["%{$search}%"]);
                });
            }
            
            // Paginate
            $total = (clone $query)->count();
            $employees = $query->orderBy('emp.EmployeeID')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($emp) use ($companyId) {
                    $grade = strtoupper(trim($emp->grade_name ?? ''));
                    $deptId = (int) ($emp->department_id ?? 0);
                    
                    $code = $emp->EmpCode_New ?? $emp->EmpCode ?? '';
                    $emp->image_url = $code ? \App\Support\EmployeePhoto::url($code, (int) (($emp->CompanyId ?? $emp->companyid ?? null) ?: $companyId)) : '';
                    $emp->is_management = ($deptId === 18) || str_starts_with($grade, 'MG');
                    $emp->company_name = \App\Helpers\DatabaseSwitcher::getCompanyName($emp->companyid ?? 1);
                    $emp->emp_code = $code;
                    $emp->emp_name = $emp->EmployeeName ?? '';
                    $emp->grade = $emp->grade_name ?? '';
                    
                    // Accurately resolve employee status (A: Active, R: Resigned, D: Deactive)
                    $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
                    $hasResignation = !empty($emp->DateOfResignation) && !in_array($emp->DateOfResignation, $blankDates) && strtotime($emp->DateOfResignation) > strtotime('2000-01-01');
                    $hasSeparation = !empty($emp->DateOfSepration) && !in_array($emp->DateOfSepration, $blankDates) && strtotime($emp->DateOfSepration) > strtotime('2000-01-01');
                    
                    if ($hasSeparation) {
                        $actualStatus = (strtotime($emp->DateOfSepration) < strtotime(date('Y-m-d'))) ? 'D' : 'R';
                    } elseif ($hasResignation) {
                        $actualStatus = (in_array($emp->EmpStatus, ['D', 'De'])) ? 'D' : 'R';
                    } elseif (in_array($emp->EmpStatus, ['D', 'De'])) {
                        $actualStatus = 'D';
                    } else {
                        $actualStatus = function_exists('getStsUser') ? (getStsUser($emp->EmployeeID, $emp) ?: ($emp->EmpStatus ?: 'A')) : ($emp->EmpStatus ?: 'A');
                    }
                    
                    $emp->EmpStatus = $actualStatus;
                    $emp->status = $actualStatus;
                    $emp->status_label = ($actualStatus === 'R') ? 'Resigned' : (($actualStatus === 'D') ? 'Deactive' : 'Active');
                    $emp->is_resigned = ($actualStatus === 'R');
                    $emp->resigned_status = ($actualStatus === 'R') ? 'Resigned' : null;
                    
                    return $emp;
                });
            
            return response()->json([
                'employees' => $employees,
                'data' => $employees,
                'has_more' => ($offset + $limit) < $total,
                'total' => $total
            ]);
            
        } catch (\Exception $e) {
            Log::error('Search failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'error' => 'Search failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Search failed'
            ], 500);
        }
    }

    /**
     * Unrestricted employee search for Group Meal / Group Lodging participant selection.
     * Returns active employees across all companies and departments (no management filter).
     * Only the claimant (self) is excluded when requested.
     */
    public function searchAll(Request $request)
    {
        try {
            $search      = $request->input('search') ?: $request->input('q', '');
            $page        = max(1, (int) $request->input('page', 1));
            $limit       = 15;
            $offset      = ($page - 1) * $limit;
            $companyId   = session('company_id');

            $claimantId   = $request->input('claimant_id')   ?: session('employee_id');
            $claimantCode = $request->input('claimant_code') ?: session('emp_code');

            // Build base query — no company or grade/department restrictions
            $query = DB::connection('hrims')->table('hrm_employee AS emp')
                ->select(
                    DB::raw("TRIM(CONCAT_WS(' ', emp.Fname, COALESCE(emp.Sname, ''), emp.Lname)) AS EmployeeName"),
                    'emp.EmployeeID',
                    'emp.EmpCode',
                    DB::raw('emp.EmpCode AS EmpCode_New'),
                    'emp.EmpStatus',
                    'emp.companyid',
                    'eg.DepartmentId AS department_id',
                    'cg.grade_name',
                    'cd.department_name'
                )
                ->leftJoin('hrm_employee_general AS eg', 'emp.EmployeeID', '=', 'eg.EmployeeID')
                ->leftJoin('core_departments AS cd', 'cd.id', '=', 'eg.DepartmentId')
                ->leftJoin('core_grades AS cg', 'cg.id', '=', 'eg.GradeId');

            // Exclude claimant (self) - claimant can never be added as their own participant
            if ($claimantId) {
                $query->where('emp.EmployeeID', '!=', $claimantId);
            }
            if ($claimantCode) {
                $query->where('emp.EmpCode', '!=', $claimantCode)
                      ->where('emp.EmpCode_New', '!=', $claimantCode);
            }

            // Exclude management employees by EmployeeID and EmpCode across HRIMS
            $mgmtEmpIds = DB::connection('hrims')->table('hrm_employee_general AS eg_sub')
                ->leftJoin('core_grades AS cg_sub', 'cg_sub.id', '=', 'eg_sub.GradeId')
                ->where('eg_sub.DepartmentId', 18)
                ->orWhereRaw("UPPER(TRIM(cg_sub.grade_name)) LIKE 'MG%'")
                ->pluck('eg_sub.EmployeeID')
                ->filter()
                ->toArray();

            $mgmtEmpCodes = DB::connection('hrims')->table('hrm_employee AS emp_sub')
                ->leftJoin('hrm_employee_general AS eg_sub', 'emp_sub.EmployeeID', '=', 'eg_sub.EmployeeID')
                ->leftJoin('core_grades AS cg_sub', 'cg_sub.id', '=', 'eg_sub.GradeId')
                ->where('eg_sub.DepartmentId', 18)
                ->orWhereRaw("UPPER(TRIM(cg_sub.grade_name)) LIKE 'MG%'")
                ->pluck('emp_sub.EmpCode')
                ->filter()
                ->toArray();

            if (!empty($mgmtEmpIds)) {
                $query->whereNotIn('emp.EmployeeID', $mgmtEmpIds);
            }
            if (!empty($mgmtEmpCodes)) {
                $query->whereNotIn('emp.EmpCode', $mgmtEmpCodes);
            }

            // Only active employees
            $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
            $query->where('emp.EmpStatus', 'A')
                ->where(function ($q) use ($blankDates) {
                    $q->whereNull('emp.DateOfSepration')->orWhereIn('emp.DateOfSepration', $blankDates);
                })
                ->where(function ($q) use ($blankDates) {
                    $q->whereNull('emp.DateOfResignation')->orWhereIn('emp.DateOfResignation', $blankDates);
                });

            // Keyword search
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('emp.Fname', 'LIKE', "%{$search}%")
                      ->orWhere('emp.Lname', 'LIKE', "%{$search}%")
                      ->orWhere('emp.EmpCode', 'LIKE', "%{$search}%")
                      ->orWhere('cd.department_name', 'LIKE', "%{$search}%")
                      ->orWhere('cg.grade_name', 'LIKE', "%{$search}%")
                      ->orWhereRaw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) LIKE ?", ["%{$search}%"]);
                });
            }

            // Deduplicate employee rows using groupBy
            $query->groupBy('emp.EmployeeID');

            $total     = (clone $query)->get()->count();
            $employees = $query->orderBy('emp.EmployeeID')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($emp) use ($companyId) {
                    $grade  = strtoupper(trim($emp->grade_name ?? ''));
                    $deptId = (int) ($emp->department_id ?? 0);
                    $code   = $emp->EmpCode_New ?? $emp->EmpCode ?? '';

                    $emp->image_url    = $code ? \App\Support\EmployeePhoto::url($code, (int) (($emp->CompanyId ?? $emp->companyid ?? null) ?: $companyId)) : '';
                    $emp->is_management = ($deptId === 18) || str_starts_with($grade, 'MG');
                    $emp->company_name  = \App\Helpers\DatabaseSwitcher::getCompanyName($emp->companyid ?? 1);
                    $emp->emp_code      = $code;
                    $emp->emp_name      = $emp->EmployeeName ?? '';
                    $emp->grade         = $emp->grade_name ?? '';

                    return $emp;
                });

            return response()->json([
                'employees' => $employees,
                'data'      => $employees,
                'has_more'  => ($offset + $limit) < $total,
                'total'     => $total,
            ]);

        } catch (\Exception $e) {
            Log::error('SearchAll failed: ' . $e->getMessage());
            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    public function employee(Request $request, $employeeId)
    {
        $year_id = session('year_id') ?: (DB::connection(session('expense_connection', 'expense'))->table('financialyear')->where('FromDate', '<=', date('Y-m-d'))->where('Todate', '>=', date('Y-m-d'))->value('YearId') ?: 8);
        $claimsTable = "y{$year_id}_expenseclaims";
        $summaryTable = "y{$year_id}_monthexpensefinal";
        $data = DB::connection(session('expense_connection'))
            ->table($claimsTable . ' as e')
            ->leftJoin($summaryTable . ' as m', function ($join) {
                $join
                    ->on('m.EmployeeID', '=', 'e.CrBy')
                    ->whereColumn('m.Month', '=', 'e.ClaimMonth');
            })
            ->where('e.CrBy', $employeeId)
            ->whereNotNull('e.BillDate')
            ->where('e.BillDate', '!=', '0000-00-00')
            ->where('e.ClaimMonth', '!=', 0)
            ->whereNotIn('e.ClaimStatus', ['Deactivate'])
            ->select(
                'm.EmployeeID',
                'e.ClaimMonth',
                'm.Status',
                'm.Total_Claim',
                'm.SubPermission',
                DB::raw("SUM(CASE WHEN e.ClaimStatus != 'Deactivate' THEN 1 ELSE 0 END) AS TotalClaims"),
                DB::raw("SUM(CASE WHEN e.FilledBy > 0 AND e.FilledDate != '0000-00-00' THEN 1 ELSE 0 END) AS FilledCount"),
                DB::raw('SUM(CASE WHEN e.FilledOkay = 1 THEN 1 ELSE 0 END) AS ClaimantApprovalCount'),
                DB::raw("SUM(CASE WHEN e.VerifyBy > 0 AND e.FilledOkay = 1 AND e.VerifyDate != '0000-00-00' THEN 1 ELSE 0 END) AS VerifyCount"),
                DB::raw("SUM(CASE WHEN e.ApprBy > 0 AND e.FilledOkay = 1 AND e.ApprDate != '0000-00-00' THEN 1 ELSE 0 END) AS ApprCount"),
                DB::raw("SUM(CASE WHEN e.FinancedBy > 0 AND e.FilledOkay = 1 AND e.FinancedDate != '0000-00-00' THEN 1 ELSE 0 END) AS FinanceCount")
            )
            ->groupBy('e.ClaimMonth', 'm.Status', 'm.Total_Claim', 'm.SubPermission', 'm.EmployeeID')
            ->orderByRaw('FIELD(e.ClaimMonth, 4,5,6,7,8,9,10,11,12,1,2,3)')
            ->get();
        $reporting = HRMEmployeeGeneral::select('ReportingName', 'ReportingContactNo')
            ->where('EmployeeID', $employeeId)
            ->first();
        $employees = HRMEmployeeGeneral::select([
            'EmployeeID',
            'RepEmployeeID',
            'DepartmentId'
        ])
            ->with(['employee' => function ($q) {
                $q->select([
                    'EmployeeID',
                    'EmpCode',
                    'EmpCode_New',
                    'Fname',
                    'Sname',
                    'Lname',
                    'EmpStatus',
                    'DateOfSepration',
                    'DateOfResignation'
                ]);
            }])
            ->where('RepEmployeeID', $employeeId)
            ->whereHas('employee', function ($q) {
                $q->where('EmpStatus', 'A')
                  ->where(function ($q2) {
                      $q2->whereNull('DateOfSepration')
                         ->orWhere('DateOfSepration', '0000-00-00')
                         ->orWhere('DateOfSepration', '1970-01-01')
                         ->orWhere('DateOfSepration', '')
                         ->orWhereDate('DateOfSepration', '>=', date('Y-m-d'));
                  });
            })
            ->get();
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $empInfo = \Illuminate\Support\Facades\DB::connection('hrims')->selectOne("
            SELECT e.EmployeeID, e.EmpCode, e.EmpCode_New,
                   CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS FullName,
                   e.EmpStatus,
                   e.DateOfSepration,
                   e.DateOfResignation,
                   g.MobileNo_Vnr, g.EmailId_Vnr,
                   dept.department_name,
                   desig.designation_name,
                   gr.grade_name,
                   ver.vertical_name,
                   st.state_name,
                   vlg.city_village_name,
                   Tr.territory_name,
                   g.ReportingName, g.ReportingContactNo,
                   rep_emp.EmpCode_New AS ReportingEmpCode
            FROM {$hrimsDb}.hrm_employee e
            LEFT JOIN {$hrimsDb}.hrm_employee_general g   ON e.EmployeeID = g.EmployeeID
            LEFT JOIN {$hrimsDb}.core_departments dept    ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_designation desig   ON g.DesigId = desig.id
            LEFT JOIN {$hrimsDb}.core_grades gr           ON g.GradeId = gr.id
            LEFT JOIN {$hrimsDb}.core_verticals ver       ON g.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_states st           ON g.CostCenter = st.id
            LEFT JOIN {$hrimsDb}.core_city_village_by_state vlg ON g.HqId = vlg.id
            LEFT JOIN {$hrimsDb}.core_territory Tr        ON g.TerrId = Tr.id
            LEFT JOIN {$hrimsDb}.hrm_employee rep_emp     ON g.RepEmployeeID = rep_emp.EmployeeID
            WHERE e.EmployeeID = ?
        ", [$employeeId]);

        // Check if employee is deactivated using centralised helper
        $isDeactivated = false;
        if ($empInfo) {
            $isDeactivated = in_array(getStsUser($employeeId), ['D', 'R']);
        }

        return view('admin.employee', compact('data', 'employees', 'reporting', 'empInfo', 'isDeactivated', 'year_id'));
    }

    public function returnClaim(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $month      = $request->input('month');
        $year_id    = session('year_id');
        $reason     = $request->input('reason', '');

        $summaryTable = "y{$year_id}_monthexpensefinal";

        // Fetch current status before update for the log
        $current = DB::connection(session('expense_connection'))
            ->table($summaryTable)
            ->where('EmployeeID', $employeeId)
            ->where('Month', $month)
            ->where('YearId', $year_id)
            ->value('Status');

        $updated = DB::connection(session('expense_connection'))
            ->table($summaryTable)
            ->where('EmployeeID', $employeeId)
            ->where('Month', $month)
            ->where('YearId', $year_id)
            ->update([
                'Status'                   => 'Open',
                'Odo_Approve_Final_Submit' => 'Y',
                'Verified_Amount'          => 0,
                'Verified_Date'            => null,
                'DateOfSubmit'             => '0000-00-00',
            ]);

        if ($updated) {
            \App\Services\Advance\AutoAdvanceOnReopen::handle((int) $employeeId, (int) $month, (int) $year_id, (string) ($reason ?? '')); // ADV-11
            ClaimActionLogService::logMonthReturn(
                (int) $employeeId,
                (int) $month,
                $current ?? 'Submitted',
                'Open',
                $reason,
                \App\Models\ClaimActionLog::ROLE_ADMIN
            );
            return response()->json(['success' => true, 'message' => 'Returned to Claimant Successfully']);
        }

        return response()->json(['success' => false, 'message' => 'Nothing to return or already open.']);
    }

    public function toggleAllowSubmit(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $month = $request->input('month');
        $year_id = session('year_id');
        $reason = $request->input('reason', '');
        $summaryTable = "y{$year_id}_monthexpensefinal";

        $updated = DB::connection(session('expense_connection'))
            ->table($summaryTable)
            ->where('EmployeeID', $employeeId)
            ->where('Month', $month)
            ->where('SubPermission', '!=', 'Y')
            ->update(['SubPermission' => 'Y']);

        if ($updated) {
            ClaimActionLogService::logAllowSubmit(
                (int) $employeeId,
                (int) $month,
                $reason,
                ClaimActionLog::ROLE_ADMIN
            );
        }

        return response()->json(['success' => true]);
    }

    /**
     * Backend Submission - Submit claims via external API for deactivated employees
     */
    public function backendSubmitClaim(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'month' => 'required|integer|between:1,12',
        ]);

        $employeeId = $request->input('employee_id');
        $month = $request->input('month');
        $yearId = session('year_id');
        $companyId = session('company_id');
        $tokenId = env('API_TOKEN_ID');
        $baseUrl = \App\Support\LegacyApi::baseUrl();

        try {
            $response = \App\Support\LegacyApi::http(30)->get("{$baseUrl}/claim.php", [
                'value' => 'Claim_Submit',
                'backend_submit' => '1',   // admin override of the submission cut-off (checked with the relay key)
                'month' => $month,
                'empid' => $employeeId,
                'YearId' => $yearId,
                'ComId' => $companyId,
                'tokenid' => $tokenId,
            ]);


            if ($response->successful() && $response->json('Code') == 300) {
                return response()->json([
                    'success' => true,
                    'message' => 'Claim submitted successfully.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit claim. API response: ' . ($response->json('Message') ?? 'Unknown error'),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Backend Submit Claim Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit claim. Please try again.',
            ], 500);
        }
    }

    public function eHomeDashboard(Request $request)
    {
        $employeeId = $request->input('employee_id', session('employee_id'));
        // EHOME-MAIN-01: own record, or one the user may see (reporting chain, approval history, staff); was open to any login
        abort_unless(\App\Support\RecordAccess::canViewEmployee(auth()->user(), $employeeId), 403);
        $companyId = (int) ($request->input('company_id') ?: session('company_id', 1));
        $tokenId = env('API_TOKEN_ID');
        $baseUrl = \App\Support\LegacyApi::baseUrl();

        $drafts = [];
        $openList = [];
        $closeList = [];

        // ── Employee info ──
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $empInfo = \Illuminate\Support\Facades\DB::connection('hrims')->selectOne("
            SELECT e.EmployeeID, e.EmpCode, e.EmpCode_New, e.CompanyId,
                   CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS FullName,
                   e.EmpStatus,
                   g.DepartmentId, g.GradeId, g.DesigId,
                   g.MobileNo_Vnr, g.EmailId_Vnr,
                   dept.department_name,
                   desig.designation_name,
                   gr.grade_name,
                   ver.vertical_name,
                   st.state_name,
                   vlg.city_village_name,
                   Tr.territory_name,
                   g.ReportingName, g.ReportingContactNo
            FROM {$hrimsDb}.hrm_employee e
            LEFT JOIN {$hrimsDb}.hrm_employee_general g   ON e.EmployeeID = g.EmployeeID
            LEFT JOIN {$hrimsDb}.core_departments dept    ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_designation desig   ON g.DesigId = desig.id
            LEFT JOIN {$hrimsDb}.core_grades gr           ON g.GradeId = gr.id
            LEFT JOIN {$hrimsDb}.core_verticals ver       ON g.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_states st           ON g.CostCenter = st.id
            LEFT JOIN {$hrimsDb}.core_city_village_by_state vlg ON g.HqId = vlg.id
            LEFT JOIN {$hrimsDb}.core_territory Tr        ON g.TerrId = Tr.id
            WHERE e.EmployeeID = ?
        ", [$employeeId]);

        // Auto-detect employee company if not explicitly specified
        $askedCompany = $companyId;
        if ($empInfo && !empty($empInfo->CompanyId)) {
            $companyId = (int) $empInfo->CompanyId;
        }
        // EHOME-FY-01 (14 Nov 2026): each company numbers its financial years differently (VVNR 2026-2027 is not
        // VNR's YearId of the same number). When the employee belongs to another company than the one signed in to,
        // carry the year over by its name (2026-2027), not by its number - the number pointed at 2024-2025.
        $askedYearId = $request->filled('year_id') ? (int) $request->input('year_id') : (int) session('year_id');
        if ($askedYearId > 0 && $askedCompany !== $companyId) {
            $askedConn = match ($askedCompany) { 11 => 'expense_vvnr', 3 => 'expense_nursery', default => 'expense' };
            $targetConn = match ($companyId) { 11 => 'expense_vvnr', 3 => 'expense_nursery', default => 'expense' };
            try {
                $label = DB::connection($askedConn)->table('financialyear')->where('YearId', $askedYearId)->value('Year');
                $mapped = $label ? DB::connection($targetConn)->table('financialyear')->where('Year', $label)->value('YearId') : null;
            } catch (\Throwable $e) {
                $mapped = null;
            }
            if ($mapped) {
                $request->merge(['year_id' => (int) $mapped]);
            } else {
                $request->offsetUnset('year_id'); // falls back to the company's current year below
                $request->attributes->set('ehome_ignore_session_year', true);
            }
        }

        // Determine expense connection
        $expenseConn = match ((int) $companyId) {
            11 => 'expense_vvnr',
            3 => 'expense_nursery',
            default => 'expense',
        };

        // Resolve current financial year for the company
        $today = date('Y-m-d');
        $currentFy = DB::connection($expenseConn)->table('financialyear')
            ->where('FromDate', '<=', $today)
            ->where('Todate', '>=', $today)
            ->first(['YearId', 'Year']);

        $currentYearId = $currentFy ? (int) $currentFy->YearId : ((int) $companyId === 11 ? 6 : 8);
        $yearValue = $currentFy->Year ?? '2026-2027';

        // Use requested year_id if provided, otherwise strictly default to current FY
        $yearId = $request->filled('year_id') ? (int) $request->input('year_id')
            : ((session('year_id') && ! $request->attributes->get('ehome_ignore_session_year')) ? (int) session('year_id') : $currentYearId);
        // Fallback yearValue from yearId
        if (!$currentFy || (int) $currentFy->YearId !== (int) $yearId) {
            $matchedYear = DB::connection($expenseConn)->table('financialyear')->where('YearId', $yearId)->value('Year');
            if ($matchedYear) {
                $yearValue = $matchedYear;
            }
        }

        $apiBase = "{$baseUrl}/{$companyId}/common.php";

        // ── Drafts ──
        try {
            $r = \App\Support\LegacyApi::http(50)->get($apiBase, [
                'value' => 'Draftlist',
                'empid' => $employeeId,
                'YearId' => $yearId,
                'ComId' => $companyId,
                'tokenid' => $tokenId,
            ]);
            if ($r->successful() && ((string) $r->json('Code') === '300')) {
                $drafts = $r->json('Draft_list', []);
            }
        } catch (\Exception $e) {
            Log::error('eHome Draftlist: ' . $e->getMessage());
        }
        // DASH-NUM-01: same list as the dashboard's Drafts card; when the call back to this app fails, read it directly
        if (empty($drafts) && \App\Support\LegacyApi::isOwn()) {
            try {
                $dr = \Illuminate\Http\Request::create('/api/common.php', 'GET', ['value' => 'Draftlist', 'empid' => $employeeId, 'YearId' => $yearId, 'ComId' => $companyId]);
                $dr->headers->set('X-Xeasy-Internal', (string) config('app.internal_api_key'));
                $dj = json_decode((new \App\Http\Controllers\Api\Mobile\ClaimListController())->handleDraftlist($dr)->getContent(), true);
                if ((string) ($dj['Code'] ?? '') === '300') {
                    $drafts = $dj['Draft_list'] ?? [];
                }
            } catch (\Throwable $e) {
                Log::warning('eHome Draftlist direct: ' . $e->getMessage());
            }
        }

        // ── Open list ──
        try {
            $r = \App\Support\LegacyApi::http(50)->get($apiBase, [
                'value' => 'Openlist',
                'empid' => $employeeId,
                'YearId' => $yearId,
                'ComId' => $companyId,
                'tokenid' => $tokenId,
            ]);
            if ($r->successful() && ((string) $r->json('Code') === '300')) {
                $openList = $r->json('Open_list', []);
                // Filter strictly to current FY YearId
                $openList = array_values(array_filter($openList, function ($item) use ($yearId) {
                    return !isset($item['YearId']) || (int) $item['YearId'] === (int) $yearId;
                }));
                usort($openList, fn($a, $b) =>
                    array_search((int) $a['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]) <=>
                    array_search((int) $b['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]));
            }
        } catch (\Exception $e) {
            Log::error('eHome Openlist: ' . $e->getMessage());
        }

        // Direct DB fallback for Open list if API call returned empty or failed
        if (empty($openList)) {
            try {
                $openMonths = DB::connection($expenseConn)->select(
                    "SELECT Month, SubPermission, YearId, EmployeeID FROM y{$yearId}_monthexpensefinal WHERE EmployeeID = ? AND YearId = ? AND Status = 'Open' GROUP BY Month ORDER BY Month ASC",
                    [$employeeId, $yearId]
                );

                $currentMonth = (int) date('n');
                $currentDay = (int) date('j');
                $lastSubmissionDay = \App\Models\SubmissionSetting::getLastDay((int) $companyId);
                $lastMonth = $currentMonth - 1;
                if ($lastMonth <= 0) $lastMonth = 12;

                foreach ($openMonths as $monthRow) {
                    $month = $monthRow->Month;
                    $subPermission = $monthRow->SubPermission;

                    $sqlFilled = "SELECT COUNT(CASE WHEN (ClaimId != 7 OR (ClaimId = 7 AND v_verify = 'Y' AND ClaimStatus = 'Filled')) THEN 1 END) AS TotClaim,
                                         COALESCE(SUM(CASE WHEN FilledTAmt != '' AND (ClaimId != 7 OR (ClaimId = 7 AND v_verify = 'Y' AND ClaimStatus = 'Filled')) THEN FilledTAmt END), 0) AS TotFilledAmt,
                                         COUNT(CASE WHEN FilledOkay = 1 THEN 1 END) AS TotVerifiedClaims,
                                         COUNT(CASE WHEN FilledOkay = 2 THEN 1 END) AS TotDeniedClaims,
                                         COUNT(CASE WHEN FilledOkay = 0 THEN 1 END) AS TotUnactionedClaims
                                  FROM y{$yearId}_expenseclaims
                                  WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ? AND FilledBy > 0 AND FilledDate != '0000-00-00' AND FilledDate != '1970-01-01' AND ClaimStatus = 'Filled' AND ClaimStatus != 'Deactivate' AND ClaimStatus != 'Draft'";
                    $filledResult = DB::connection($expenseConn)->select($sqlFilled, [$yearId, $employeeId, $month]);

                    if (!empty($filledResult)) {
                        $filled = (array) $filledResult[0];
                        $totClaim = (int) ($filled['TotClaim'] ?? 0);
                        $totFilledAmt = $filled['TotFilledAmt'] ?? 0;

                        if ($totClaim > 0) {
                            $sqlVerify = "SELECT COALESCE(SUM(CASE WHEN FilledTAmt != '' THEN FilledTAmt END), 0) as TotVerifyAmt
                                          FROM y{$yearId}_expenseclaims
                                          WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ? AND FilledOkay = 1 AND ClaimStatus != 'Submitted' AND ClaimStatus != 'Deactivate' AND ClaimStatus != 'Draft'";
                            $verifyResult = DB::connection($expenseConn)->select($sqlVerify, [$yearId, $employeeId, $month]);
                            $totVerifyAmt = !empty($verifyResult) ? (((array) $verifyResult[0])['TotVerifyAmt'] ?? '0') : '0';

                            // SUBMIT-BTN-01: same rule as the submit itself
                            $st = \App\Services\ClaimRules\MonthSubmit::state($expenseConn, (int) $companyId, (int) $yearId, (int) $employeeId, (int) $month, $subPermission);
                            $submitBtn = $st['allowed'] ? 'Y' : 'N';

                            $openList[] = [
                                'Month' => $month,
                                'SubPermission' => $subPermission,
                                'YearId' => (string) $yearId,
                                'EmployeeID' => (string) $employeeId,
                                'TotalFilled_Claim_with_Amount' => [
                                    'TotClaim' => (string) $totClaim,
                                    'TotFilledAmt' => (string) $totFilledAmt,
                                    'TotVerifiedClaims' => (string) ($filled['TotVerifiedClaims'] ?? 0),
                                    'TotDeniedClaims' => (string) ($filled['TotDeniedClaims'] ?? 0),
                                    'TotUnactionedClaims' => (string) ($filled['TotUnactionedClaims'] ?? 0),
                                ],
                                'TotalVerify_Claim_with_Amount' => [
                                    'TotVerifyAmt' => (string) $totVerifyAmt,
                                ],
                                'submit_btn' => $submitBtn,
                                'submit_note' => $st['reason'],
                                'ComId' => (string) $companyId,
                            ];
                        }
                    }
                }

                if (!empty($openList)) {
                    usort($openList, fn($a, $b) =>
                        array_search((int) $a['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]) <=>
                        array_search((int) $b['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]));
                }
            } catch (\Exception $e) {
                Log::error('eHome Openlist fallback: ' . $e->getMessage());
            }
        }

        // ── Closed list ──
        try {
            $r = \App\Support\LegacyApi::http(50)->get($apiBase, [
                'value' => 'Closelist_All_Month',
                'empid' => $employeeId,
                'YearId' => $yearId,
                'ComId' => $companyId,
                'tokenid' => $tokenId,
            ]);
            if (!$r->successful() || $r->json('Code') !== '300') {
                $r = \App\Support\LegacyApi::http(50)->get("{$baseUrl}/common.php", [
                    'value' => 'Closelist_All_Month',
                    'empid' => $employeeId,
                    'YearId' => $yearId,
                    'ComId' => $companyId,
                    'tokenid' => $tokenId,
                ]);
            }
            if ($r->successful() && ((string) $r->json('Code') === '300')) {
                $closeList = $r->json('Close_list', []);
                // Filter strictly to current FY YearId
                $closeList = array_values(array_filter($closeList, function ($item) use ($yearId) {
                    return !isset($item['YearId']) || (int) $item['YearId'] === (int) $yearId;
                }));
                usort($closeList, fn($a, $b) =>
                    array_search((int) $a['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]) <=>
                    array_search((int) $b['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]));
            }
        } catch (\Exception $e) {
            Log::error('eHome Closelist: ' . $e->getMessage());
        }

        // Direct DB fallback for Closed list if API returned empty
        if (empty($closeList)) {
            try {
                $dbClosed = DB::connection($expenseConn)->select(
                    "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID = ? AND YearId = ? AND Status = 'Closed' ORDER BY Month ASC",
                    [$employeeId, $yearId]
                );
                foreach ($dbClosed as $row) {
                    $closeList[] = (array) $row;
                }
                if (!empty($closeList)) {
                    usort($closeList, fn($a, $b) =>
                        array_search((int) $a['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]) <=>
                        array_search((int) $b['Month'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]));
                }
            } catch (\Exception $e) {
                Log::error('eHome Closelist DB fallback: ' . $e->getMessage());
            }
        }

        // Format remarks to accurately reflect previous month adjustments and DA
        if (!empty($closeList)) {
            foreach ($closeList as &$cItem) {
                $cMonth = (int) ($cItem['Month'] ?? 0);
                $cPaid = (float) ($cItem['Fin_PayAmt'] ?? 0);
                $currRem = trim($cItem['Fin_PayRemark'] ?? '');
                $payDate = trim($cItem['Fin_PayDate'] ?? '');
                $payStatus = trim($cItem['payment_status'] ?? '');

                $isPaid = ($cPaid > 0)
                    || (!empty($payDate) && \App\Support\ZeroDate::has($payDate))
                    || in_array(strtolower($payStatus), ['confirmedpayment', 'paid', 'confirmed']);

                // If not paid yet, it must not display or store any "Paid Rs." remark
                if (!$isPaid) {
                    if (stripos($currRem, 'Paid Rs.') !== false) {
                        $currRem = '';
                        $cItem['Fin_PayRemark'] = '';
                        try {
                            $updateMef = DB::connection($expenseConn)->table("y{$yearId}_monthexpensefinal");
                            if (!empty($cItem['id'])) {
                                $updateMef->where('id', $cItem['id']);
                            } else {
                                $updateMef->where('EmployeeID', $employeeId)->where('Month', $cMonth)->where('YearId', $yearId);
                            }
                            $updateMef->update(['Fin_PayRemark' => '']);
                        } catch (\Exception $e) {}
                    }
                    continue;
                }

                if ((int) $companyId === 11 && function_exists('formatVvnrPaymentRemark')) {
                    if ($cPaid > 0 || stripos($currRem, 'Paid') !== false || stripos($currRem, 'No adjustments') !== false) {
                        $newRemark = formatVvnrPaymentRemark($employeeId, $cMonth, $yearId, $cPaid, $currRem, (object) $cItem, $expenseConn);
                        if (!empty($newRemark)) {
                            $cItem['Fin_PayRemark'] = $newRemark;
                            try {
                                $updateMef = DB::connection($expenseConn)->table("y{$yearId}_monthexpensefinal");
                                if (!empty($cItem['id'])) {
                                    $updateMef->where('id', $cItem['id']);
                                } else {
                                    $updateMef->where('EmployeeID', $employeeId)->where('Month', $cMonth)->where('YearId', $yearId);
                                }
                                $updateMef->update(['Fin_PayRemark' => $newRemark]);
                            } catch (\Exception $e) {
                                // Ignore update failure
                            }
                        }
                    }
                } else {
                    // For Company 1 and other companies: ensure DA is mentioned in remark if DA exists
                    try {
                        $daAmt = 0;
                        try {
                            $claimsTable = "y{$yearId}_expenseclaims";
                            $daRow = DB::connection($expenseConn)->selectOne("
                                SELECT 
                                    COALESCE(SUM(CASE WHEN FinancedTAmt > 0 THEN FinancedTAmt ELSE 0 END), 0) as da_fin,
                                    COALESCE(SUM(CASE WHEN ApprTAmt > 0 THEN ApprTAmt ELSE 0 END), 0) as da_appr,
                                    COALESCE(SUM(CASE WHEN VerifyTAmt > 0 THEN VerifyTAmt ELSE 0 END), 0) as da_ver,
                                    COALESCE(SUM(CASE WHEN FilledTAmt > 0 THEN FilledTAmt ELSE 0 END), 0) as da_fill
                                FROM {$claimsTable}
                                WHERE CrBy = ? 
                                  AND ClaimMonth = ? 
                                  AND ClaimStatus != 'Deactivate' 
                                  AND FilledOkay = 1
                                  AND (ClaimId = 19 OR ClaimId = 20)
                            ", [$employeeId, $cMonth]);

                            if ($daRow) {
                                $daAmt = (float) ($daRow->da_fin > 0 ? $daRow->da_fin : ($daRow->da_appr > 0 ? $daRow->da_appr : ($daRow->da_ver > 0 ? $daRow->da_ver : ($daRow->da_fill ?? 0))));
                            }
                        } catch (\Exception $e) {
                            // Ignore claims table query failure
                        }

                        // Fallback: If claims query yielded 0, infer from (Approved - Adjustments - Paid)
                        if ($daAmt <= 0) {
                            $cAppr = (float) ($cItem['Approved_Amount'] ?? ($cItem['Verified_Amount'] ?? 0));
                            $cTotAdj = (float) ($cItem['total_adjustment'] ?? ((float) ($cItem['total_activity_adjustment'] ?? 0) + (float) ($cItem['total_expense_adjustment'] ?? 0)));
                            if ($cAppr > 0 && $cPaid > 0 && ($cAppr - $cTotAdj - $cPaid) > 0) {
                                $daAmt = round($cAppr - $cTotAdj - $cPaid, 2);
                            }
                        }

                        if ($daAmt > 0) {
                            $daStr = (floor($daAmt) == $daAmt) ? number_format($daAmt, 0, '', '') : number_format($daAmt, 2);
                            $daTag = "DA Rs. {$daStr}";

                            if (preg_match('/DA\s+Rs\.?\s*([0-9.]+)/i', $currRem, $matches)) {
                                if ((float) $matches[1] != $daAmt) {
                                    $newRemark = preg_replace('/DA\s+Rs\.?\s*[0-9.]+/i', $daTag, $currRem);
                                } else {
                                    $newRemark = $currRem;
                                }
                            } else {
                                if (empty($currRem) || $currRem === '—' || $currRem === '-') {
                                    $paidFormatted = (floor($cPaid) == $cPaid) ? number_format($cPaid, 0, '', '') : number_format($cPaid, 2);
                                    $newRemark = "Paid Rs. {$paidFormatted}, No adjustments, {$daTag}";
                                } else {
                                    $newRemark = "{$currRem}, {$daTag}";
                                }
                            }
                        } else {
                            if (preg_match('/DA\s+Rs\.?\s*[0-9.]+/i', $currRem)) {
                                $newRemark = preg_replace('/,?\s*DA\s+Rs\.?\s*[0-9.]+/i', '', $currRem);
                                $newRemark = trim($newRemark, " ,");
                            } else {
                                $newRemark = $currRem;
                            }
                        }

                        if ($newRemark !== $currRem) {
                            $cItem['Fin_PayRemark'] = $newRemark;
                            try {
                                $updateMef = DB::connection($expenseConn)->table("y{$yearId}_monthexpensefinal");
                                if (!empty($cItem['id'])) {
                                    $updateMef->where('id', $cItem['id']);
                                } else {
                                    $updateMef->where('EmployeeID', $employeeId)
                                        ->where('Month', $cMonth)
                                        ->where('YearId', $yearId);
                                }
                                $updateMef->update(['Fin_PayRemark' => $newRemark]);
                            } catch (\Exception $e) {
                                // Ignore update failure
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("eHome remark DA check error for emp {$employeeId} month {$cMonth}: " . $e->getMessage());
                    }
                }
            }
            unset($cItem);
        }

        return view('expense.ehome-dashboard', compact('drafts', 'openList', 'closeList', 'employeeId', 'yearId', 'yearValue', 'companyId', 'empInfo'));
    }

    public function eHomeMonthDetails(Request $request)
    {
        $employeeId = $request->input('employee_id', session('employee_id'));
        // EHOME-MAIN-01: own record, or one the user may see (reporting chain, approval history, staff); was open to any login
        abort_unless(\App\Support\RecordAccess::canViewEmployee(auth()->user(), $employeeId), 403);
        $month = $request->input('month');
        $type = $request->input('type', 'open');
        $companyId = (int) ($request->input('company_id') ?: session('company_id', 1));

        $expenseConn = match ($companyId) {
            11 => 'expense_vvnr',
            3 => 'expense_nursery',
            default => 'expense',
        };

        $today = date('Y-m-d');
        $currentFy = DB::connection($expenseConn)->table('financialyear')
            ->where('FromDate', '<=', $today)
            ->where('Todate', '>=', $today)
            ->value('YearId');
        $currentYearId = $currentFy ? (int) $currentFy : ($companyId === 11 ? 6 : 8);

        $yearId = $request->filled('year_id') ? (int) $request->input('year_id') : (session('year_id') ? (int) session('year_id') : $currentYearId);
        $tokenId = env('API_TOKEN_ID');
        $baseUrl = \App\Support\LegacyApi::baseUrl();

        $value = $type === 'closed' ? 'Closelist_Day' : 'Openlist_Day';
        $apiBase = "{$baseUrl}/{$companyId}/common.php";

        try {
            $r = \App\Support\LegacyApi::http(50)->get($apiBase, [
                'value' => $value,
                'empid' => $employeeId,
                'Month' => $month,
                'YearId' => $yearId,
                'ComId' => $companyId,
                'tokenid' => $tokenId,
            ]);

            if ($r->successful() && ((string) $r->json('Code') === '300')) {
                return response()->json([
                    'success' => true,
                    'data' => $r->json('DayWiseDetails', []),
                ]);
            }

            // Direct DB fallback if API returned non-300 or empty
            $expenseConn = session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
            if ($type === 'open') {
                $sql = "SELECT e.ExpId, e.ClaimYearId, e.BillDate, e.FilledTAmt, e.ClaimStatus, e.CrBy, e.Remark, e.DateEntryRemark, e.FilledOkay, e.ClaimAtStep, ct.ClaimName, ct.cgId, ct.cgCode, e.ClaimMonth, e.opening_filepath, e.closing_filepath, Img_1, Img_2, Img_3, Img_4 as pdf_1, Img_5 as pdf_2, e.TotKm, e.v_verify, e.ClaimId
                        FROM y{$yearId}_expenseclaims e
                        LEFT JOIN claimtype ct ON e.ClaimId = ct.ClaimId
                        WHERE e.ClaimMonth = ? AND e.ClaimYearId = ? AND e.CrBy = ? AND e.ClaimStatus NOT IN ('Deactivate', 'Draft', 'Submitted') AND e.FilledBy > 0 AND (e.Backdate_Odometer_Status IS NULL OR e.Backdate_Odometer_Status = '' OR (e.Backdate_Odometer_Status = 'A' AND e.v_verify = 'Y'))
                        ORDER BY BillDate ASC";
                $dayRows = DB::connection($expenseConn)->select($sql, [$month, $yearId, $employeeId]);
            } else {
                $sql = "SELECT e.ExpId, e.ClaimYearId, e.CrBy, e.ClaimId, e.BillDate, e.FilledTAmt, e.TotKm, e.VerifyTAmt, VerifyBy, VerifyDate, VerifyTRemark, e.ApprTAmt, ApprBy, ApprDate, ApprTRemark, e.FinancedTAmt, FinancedBy, FinancedDate, FinancedTRemark, e.ClaimStatus, e.Remark, e.DateEntryRemark, e.FilledOkay, e.ClaimAtStep, ct.ClaimName, ct.cgId, ct.cgCode, e.ClaimMonth, e.opening_filepath, e.closing_filepath, Img_1, Img_2, Img_3, Img_4 as pdf_1, Img_5 as pdf_2
                        FROM y{$yearId}_expenseclaims e
                        LEFT JOIN claimtype ct ON e.ClaimId = ct.ClaimId
                        WHERE e.ClaimMonth = ? AND e.ClaimYearId = ? AND e.CrBy = ? AND e.ClaimStatus NOT IN ('Deactivate', 'Draft', 'Submitted') AND e.FilledBy > 0
                        ORDER BY BillDate ASC";
                $dayRows = DB::connection($expenseConn)->select($sql, [$month, $yearId, $employeeId]);
            }

            if (!empty($dayRows)) {
                $dayList = array_map(function ($row) {
                    return (array) $row;
                }, $dayRows);
                return response()->json([
                    'success' => true,
                    'data' => $dayList,
                ]);
            }

            return response()->json(['success' => false, 'data' => []]);
        } catch (\Exception $e) {
            Log::error('eHome MonthDetails: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => []]);
        }
    }

    /**
     * Get mediator and eligibility info for an employee
     */
    public function getMediatorEligibility($employeeId)
    {
        try {
            // Get employee & mediator assignment from hrm_employee + hrm_employee_general
            $empInfo = DB::connection('hrims')->selectOne("
                SELECT 
                    e.EmployeeID,
                    e.EmpCode AS EmpCode_New,
                    CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS EmployeeName,
                    g.DataPunchMediator,
                    g.DataPunchMediatorName
                FROM hrm_employee e
                LEFT JOIN hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                WHERE e.EmployeeID = ?
                LIMIT 1
            ", [$employeeId]);

            if (!$empInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Get available mediators for assignment
            $mediators = DB::connection('hrims')->select("
                SELECT DISTINCT
                    e.EmployeeID,
                    e.EmpCode AS EmpCode,
                    e.EmpCode AS EmpCode_New,
                    CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS MediatorName
                FROM hrm_employee e
                LEFT JOIN hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                WHERE e.EmpStatus = 'A'
                    AND (e.DateOfSepration IS NULL OR e.DateOfSepration IN ('0000-00-00', '1970-01-01', ''))
                    AND (e.DateOfResignation IS NULL OR e.DateOfResignation IN ('0000-00-00', '1970-01-01', ''))
                ORDER BY e.Fname ASC
            ");

            return response()->json([
                'success' => true,
                'employee' => [
                    'employee_id' => $empInfo->EmployeeID,
                    'emp_code' => $empInfo->EmpCode_New,
                    'name' => $empInfo->EmployeeName,
                    'current_mediator_id' => $empInfo->DataPunchMediator,
                    'current_mediator_name' => $empInfo->DataPunchMediatorName,
                    'current_policy_id' => null,
                    'current_policy_name' => null,
                ],
                'available_mediators' => $mediators,
                'available_policies' => [],
            ]);
        } catch (\Exception $e) {
            Log::error('Get Mediator Eligibility Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mediator info'
            ], 500);
        }
    }

    /**
     * Assign mediator and/or eligibility policy to an employee
     */
    public function assignMediator(Request $request, $employeeId)
    {
        $request->validate([
            'mediator_id' => 'nullable|integer',
            'policy_id' => 'nullable|integer',
        ]);

        try {
            $mediatorId = $request->input('mediator_id');
            $policyId = $request->input('policy_id');

            // Update mediator assignment in employee_general
            if ($mediatorId !== null) {
                $mediator = DB::connection('hrims')->selectOne("
                    SELECT EmpCode AS EmpCode_New, EmpCode, CONCAT(TRIM(CONCAT(Fname,' ',COALESCE(Sname,''),' ',Lname))) AS FullName
                    FROM hrm_employee WHERE EmployeeID = ?
                ", [$mediatorId]);

                if ($mediator) {
                    DB::connection('hrims')->table('hrm_employee_general')
                        ->where('EmployeeID', $employeeId)
                        ->update([
                            'DataPunchMediator' => $mediatorId,
                            'DataPunchMediatorName' => $mediator->FullName,
                        ]);
                }
            }

            // Update eligibility policy
            if ($policyId !== null) {
                $policy = DB::connection('hrims')->selectOne("
                    SELECT PolicyName FROM hrm_master_eligibility_policy WHERE PolicyId = ?
                ", [$policyId]);

                if ($policy) {
                    // Check if assignment exists
                    $exists = DB::connection('hrims')->selectOne("
                        SELECT id FROM hrm_master_eligibility_policy WHERE EmployeeID = ? AND PolicyId = ?
                    ", [$employeeId, $policyId]);

                    if (!$exists) {
                        DB::connection('hrims')->table('hrm_master_eligibility_policy')
                            ->insert([
                                'EmployeeID' => $employeeId,
                                'PolicyId' => $policyId,
                                'PolicyName' => $policy->PolicyName,
                            ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Mediator and eligibility info updated successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Assign Mediator Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assignment'
            ], 500);
        }
    }
}
