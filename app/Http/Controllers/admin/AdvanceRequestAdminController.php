<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AdvanceActivityApproval;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestAction;
use App\Models\AdvanceRequestActivity;
use App\Models\AdvanceRequestEmployee;
use App\Models\HRMEmployeeGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class AdvanceRequestAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            $empRole = session('emp_role') ?: session('role_id');

            if (!$user) {
                return redirect()->route('login');
            }

            $isAdmin = false;
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin'])) {
                $isAdmin = true;
            }
            if (method_exists($user, 'can') && ($user->can('seection_settings') || $user->can('Section Settings'))) {
                $isAdmin = true;
            }

            // Normal employees ('E') or users without admin privileges cannot access
            if ($empRole === 'E' || !$isAdmin) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. This page is not available for normal employees.'
                    ], 403);
                }
                abort(403, 'Unauthorized access. This page is not available for normal employees.');
            }

            return $next($request);
        });
    }

    public function index()
    {
        return view('admin.advance.admin-submit');
    }

    public function getEmployees(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $departmentId = $request->get('department_id');
            $query = DB::connection('hrims')->table('hrm_employee as e')->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')->join('core_departments as d', 'g.DepartmentId', '=', 'd.id')->join('core_designation as des', 'g.DesigId', '=', 'des.id')->where('e.EmpStatus', 'A')->select(['e.EmployeeID', 'e.EmpCode', DB::raw("CONCAT(TRIM(COALESCE(e.Fname, '')), ' ', TRIM(COALESCE(e.Sname, '')), ' ', TRIM(COALESCE(e.Lname, ''))) as EmployeeName"), 'd.department_name as DepartmentName', 'des.designation_name as Designation']);
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw("CONCAT(e.Fname, ' ', e.Sname, ' ', e.Lname)"), 'like', "%{$search}%")->orWhere('e.EmpCode', 'like', "%{$search}%");
                });
            }
            if ($departmentId) {
                $query->where('g.DepartmentId', $departmentId);
            }
            $employees = $query->limit(50000)->get();
            return response()->json(['success' => true, 'data' => $employees]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch employees: ' . $e->getMessage()], 500);
        }
    }

    public function getDepartments()
    {
        try {
            $departments = DB::connection('hrims')->table('core_departments')->where('is_active', 1)->select('id', 'department_name')->orderBy('department_name')->get();
            return response()->json(['success' => true, 'data' => $departments]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch departments: ' . $e->getMessage()], 500);
        }
    }

    public function getAdvanceTypes()
    {
        try {
            $types = DB::connection(session('expense_connection'))->table('advance_request_types')->where('is_active', 1)->select('id', 'type_name', 'type_code')->where('id', 1)->get();
            return response()->json(['success' => true, 'data' => $types]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch advance types: ' . $e->getMessage()], 500);
        }
    }

    public function getActivityNames()
    {
        try {
            $activities = DB::connection(session('expense_connection'))->table('claimtype')->where('ClaimStatus', 'A')->where('cgid', '7')->select('ClaimId as id', 'ClaimName as name')->orderBy('ClaimName')->get();
            return response()->json(['success' => true, 'data' => $activities]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch activity names: ' . $e->getMessage()], 500);
        }
    }

    public function getActivityTypes()
    {
        try {
            $types = DB::connection(session('expense_connection'))->table('adv_activity_types')->where('status', 1)->select('id', 'type_name')->orderBy('type_name')->get();
            return response()->json(['success' => true, 'data' => $types]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch activity types: ' . $e->getMessage()], 500);
        }
    }

    public function getCrops()
    {
        try {
            $crops = DB::connection('hrims')->table('core_crops')->where('is_active', 1)->select('id', 'crop_name')->orderBy('crop_name')->get();
            return response()->json(['success' => true, 'data' => $crops]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch crops: ' . $e->getMessage()], 500);
        }
    }

    public function getVarieties(Request $request)
    {
        try {
            $cropIds = $request->get('crop_ids', []);
            $query = DB::connection('hrims')->table('core_varieties')->where('is_active', 1)->select('id', 'variety_name', 'crop_id');
            if (!empty($cropIds)) {
                $query->whereIn('crop_id', $cropIds);
            }
            $varieties = $query->orderBy('variety_name')->get();
            return response()->json(['success' => true, 'data' => $varieties]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch varieties: ' . $e->getMessage()], 500);
        }
    }

    public function submitRequest(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'advance_type_id' => 'required|exists:advance_request_types,id',
            'activity_name_id' => 'required',
            'month' => 'required',
            'activities' => 'required|array|min:1',
            'activities.*.activity_type_id' => 'required',
            'activities.*.amount' => 'required|numeric|min:0',
            'activities.*.purpose' => 'required|string',
        ]);
        try {
            return DB::transaction(function () use ($request) {
                $adminUserId = 1729;
                $employeeId = $request->employee_id;
                $yearId = session('year_id');
                // Create advance request
                $advanceRequest = AdvanceRequest::create([
                    'year_id' => $yearId,
                    'advance_type_id' => $request->advance_type_id,
                    'activity_name_id' => $request->activity_name_id,
                    'month' => $request->month,
                    'advance_fill_date' => now(),
                    'current_status' => AdvanceRequest::STATUS_FINANCE,  // Auto-approved, directly to finance
                    'submission_no' => 1,
                    'last_submitted_at' => now(),
                    'req_app_submit_id' => $adminUserId,
                    'created_by' => $adminUserId,
                    'updated_by' => $adminUserId,
                ]);
                // Create employee record
                $advanceRequestEmployee = AdvanceRequestEmployee::create([
                    'advance_request_id' => $advanceRequest->id,
                    'employee_id' => $employeeId,
                    'created_by' => $adminUserId,
                    'updated_by' => $adminUserId,
                ]);
                // Create activities
                foreach ($request->activities as $activityData) {
                    // Convert crop and variety arrays to JSON if they are arrays
                    $cropIds = $activityData['activity_crops_id'] ?? null;
                    $varietyIds = $activityData['activity_variety_id'] ?? null;
                    if (is_array($cropIds)) {
                        $cropIds = json_encode($cropIds);
                    }
                    if (is_array($varietyIds)) {
                        $varietyIds = json_encode($varietyIds);
                    }
                    $activity = AdvanceRequestActivity::create([
                        'advance_request_employee_id' => $advanceRequestEmployee->id,
                        'activity_type_id' => $activityData['activity_type_id'],
                        'activity_crops_id' => $cropIds,
                        'activity_variety_id' => $varietyIds,
                        'amount' => $activityData['amount'],
                        'purpose' => $activityData['purpose'],
                        'current_approval_level' => 0,  // No approval needed
                        'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_APPROVED,  // Auto-approved
                        'finance_status' => AdvanceRequestActivity::FINANCE_STATUS_PENDING,
                        'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_PENDING,
                        'final_status' => AdvanceRequestActivity::FINAL_STATUS_PENDING,
                        'created_by' => $adminUserId,
                        'updated_by' => $adminUserId,
                    ]);
                    // Create auto-approval record for tracking
                    AdvanceActivityApproval::create([
                        'advance_request_activity_id' => $activity->id,
                        'submission_no' => 1,
                        'approval_level' => 0,
                        'approver_id' => $adminUserId,
                        'approver_status' => 'Approved',
                        'approver_amount' => $activityData['amount'],
                        'approver_remark' => 'Auto-approved by admin',
                        'approver_date' => now(),
                        'created_by' => $adminUserId,
                        'updated_by' => $adminUserId,
                    ]);
                }
                // Log the action
                AdvanceRequestAction::create([
                    'advance_request_id' => $advanceRequest->id,
                    'submission_no' => 1,
                    'action_type' => 'Submit',
                    'action_by' => session('employee_id'),
                    'action_to' => null,
                    'related_employee_id' => $employeeId,
                    'remarks' => 'Advance request submitted by admin with auto-approval',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return response()->json(['success' => true, 'message' => 'Advance request submitted successfully and auto-approved. Now available at finance level.', 'data' => ['request_id' => $advanceRequest->id, 'status' => $advanceRequest->current_status]]);
            });
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to submit advance request: ' . $e->getMessage()], 500);
        }
    }

    public function submitManualAdvance(Request $request)
    {
        $request->validate([
            'department_id' => 'required',
            'employee_id' => 'required',
            'month' => 'required|integer|min:1|max:12',
            'amount' => 'required|numeric|min:1',
        ]);
        try {
            return DB::transaction(function () use ($request) {
                $adminUserId = 1729;
                $employeeId = $request->employee_id;
                $yearId = session('year_id');
                $amount = $request->amount;
                $month = $request->month;
                // Validate employee exists and is active
                $employee = DB::connection('hrims')->table('hrm_employee_general')->join('hrm_employee', 'hrm_employee_general.EmployeeID', '=', 'hrm_employee.EmployeeID')->where('hrm_employee_general.EmployeeID', $employeeId)->where('hrm_employee.EmpStatus', 'A')->whereRaw("(hrm_employee.DateOfResignation IS NULL OR hrm_employee.DateOfResignation = '0000-00-00' OR hrm_employee.DateOfResignation = '')")->first();
                if (!$employee) {
                    return response()->json(['success' => false, 'message' => 'Employee not found or not active'], 400);
                }
                // ADV-POL-01: supply-chain auto advance = at most 80% of the month's claim without DA,
                // and only after the employee has submitted that month's claim
                if ($polMsg = \App\Services\Advance\AdvancePolicy::autoAdvanceBlock((string) session('expense_connection'), (int) $yearId, (int) $employeeId, (int) $month, (float) $amount)) {
                    return response()->json(['success' => false, 'message' => $polMsg], 422);
                }
                // Check if advance already exists for this month
                $existingRequest = AdvanceRequest::where('advance_type_id', 2)  // Auto Advance type
                    ->where('month', $month)
                    ->where('req_app_submit_id', $employeeId)
                    ->whereIn('current_status', ['Finance', 'Payment', 'Completed'])
                    ->first();
                if ($existingRequest) {
                    return response()->json(['success' => false, 'message' => 'Advance already exists for this employee and month (Request ID: ' . $existingRequest->id . ')'], 400);
                }
                // Create advance request - Auto Advance type (ID: 2)
                $advanceRequest = AdvanceRequest::create([
                    'year_id' => $yearId,
                    'advance_type_id' => 2,  // Auto Advance
                    'activity_name_id' => 0,
                    'month' => $month,
                    'advance_fill_date' => now(),
                    'current_status' => AdvanceRequest::STATUS_FINANCE,  // Directly to finance
                    'submission_no' => 1,
                    'last_submitted_at' => now(),
                    'req_app_submit_id' => $employeeId,
                    'created_by' => $adminUserId,
                    'updated_by' => $adminUserId,
                ]);
                // Create employee record
                $advanceRequestEmployee = AdvanceRequestEmployee::create([
                    'advance_request_id' => $advanceRequest->id,
                    'employee_id' => $employeeId,
                    'created_by' => $adminUserId,
                    'updated_by' => $adminUserId,
                ]);
                // Create single activity
                $activity = AdvanceRequestActivity::create([
                    'advance_request_employee_id' => $advanceRequestEmployee->id,
                    'activity_type_id' => null,
                    'activity_crops_id' => null,
                    'activity_variety_id' => null,
                    'amount' => $amount,
                    'purpose' => 'Auto Advance based on expense percentage',
                    'current_approval_level' => 0,
                    'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_APPROVED,
                    'finance_status' => AdvanceRequestActivity::FINANCE_STATUS_PENDING,
                    'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_PENDING,
                    'final_status' => AdvanceRequestActivity::FINAL_STATUS_PENDING,
                    'created_by' => $adminUserId,
                    'updated_by' => $adminUserId,
                ]);
                // Create auto-approval record
                AdvanceActivityApproval::create([
                    'advance_request_activity_id' => $activity->id,
                    'submission_no' => 1,
                    'approval_level' => 0,
                    'approver_id' => $adminUserId,
                    'approver_status' => 'Approved',
                    'approver_amount' => $amount,
                    'approver_remark' => 'Auto-approved for Auto Advance',
                    'approver_date' => now(),
                    'created_by' => $adminUserId,
                    'updated_by' => $adminUserId,
                ]);
                // Log the action
                AdvanceRequestAction::create([
                    'advance_request_id' => $advanceRequest->id,
                    'submission_no' => 1,
                    'action_type' => 'Submit',
                    'action_by' => $adminUserId,
                    'action_to' => null,
                    'related_employee_id' => $employeeId,
                    'remarks' => 'Manual advance submitted by admin - sent to Finance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return response()->json(['success' => true, 'message' => 'Manual advance submitted successfully and sent to Finance', 'data' => ['request_id' => $advanceRequest->id, 'employee_id' => $employeeId, 'amount' => $amount, 'month' => $month, 'status' => $advanceRequest->current_status]]);
            });
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to submit manual advance: ' . $e->getMessage()], 500);
        }
    }
}
