<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvanceController extends BaseMobileController
{
    /**
     * Get advance request list for employee.
     * Replicates: common.php?value=RQ_List
     */
    public function list(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $requests = DB::connection($expenseConn)
            ->table('advance_requests')
            ->where('employee_id', $empId)
            ->orderByDesc('created_at')
            ->get();

        if ($requests->isEmpty()) {
            return $this->error('No advance requests found.');
        }

        return $this->success(['advance_list' => $requests->toArray()]);
    }

    /**
     * Get advance requests pending for approver.
     * Replicates: common.php?value=RQ_Approver_List
     */
    public function approverList(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $requests = DB::connection($expenseConn)
            ->table('advance_requests')
            ->where('approver_id', $empId)
            ->where('status', 'Pending')
            ->orderByDesc('created_at')
            ->get();

        if ($requests->isEmpty()) {
            return $this->success(['approver_list' => []]);
        }

        return $this->success(['approver_list' => $requests->toArray()]);
    }

    /**
     * Get advance requests for second level approver.
     * Replicates: common.php?value=RQ_Approver2_List
     */
    public function approver2List(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $requests = DB::connection($expenseConn)
            ->table('advance_requests')
            ->where('approver2_id', $empId)
            ->where('status', 'L1_Approved')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(['approver2_list' => $requests->toArray()]);
    }

    /**
     * Check employee roles for advance request.
     * Replicates: common.php?value=RQ_Check_Roles
     */
    public function checkRoles(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        // Check if employee is an approver
        $isApprover = DB::connection($hrimsConn)
            ->table('hrm_employee_general')
            ->where('RepEmployeeID', $empId)
            ->exists();

        // Check if employee is a second-level approver
        $isApprover2 = DB::connection($expenseConn)
            ->table('advance_requests')
            ->where('approver2_id', $empId)
            ->exists();

        // Check if employee is a finance approver
        $isFinance = DB::connection($expenseConn)
            ->table('advance_requests')
            ->where('finance_id', $empId)
            ->exists();

        return $this->success([
            'is_approver' => $isApprover ? 'Y' : 'N',
            'is_approver2' => $isApprover2 ? 'Y' : 'N',
            'is_finance' => $isFinance ? 'Y' : 'N',
        ]);
    }

    /**
     * Add/submit a new advance request.
     * Replicates: common.php?value=RQ_Add_Advance
     */
    public function addAdvance(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $requestType = $request->input('request_type', '');
        $requestMonth = $request->input('request_month', '');
        $amount = $request->input('amount', '');
        $purpose = $request->input('purpose', '');
        $activityNameId = (int) $request->input('activity_name_id', 0);
        $activityTypeId = (int) $request->input('activity_type_id', 0);

        // Validate
        $errors = [];
        if (empty($requestType)) $errors[] = 'Please select a type of request.';
        if (empty($requestMonth)) $errors[] = 'Please select a month.';
        if (empty($amount)) $errors[] = 'Please enter an amount.';
        if (empty($purpose)) $errors[] = 'Please enter a purpose.';

        if ($requestType === 'Activity') {
            if ($activityNameId === 0) $errors[] = 'Activity Name is required.';
            if ($activityTypeId === 0) $errors[] = 'Activity Type is required.';
        }

        if (!empty($errors)) {
            return response()->json(['Code' => '100', 'errors' => $errors, 'msg' => implode(' ', $errors)]);
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        // Get employee name
        $employee = DB::connection($hrimsConn)
            ->table('hrm_employee')
            ->where('EmployeeID', $empId)
            ->select(DB::raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName"), 'EmpCode')
            ->first();

        // Get approver
        $appraiser = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.RepEmployeeID', '=', 'e.EmployeeID')
            ->where('eg.EmployeeID', $empId)
            ->select('e.EmployeeID as AppraiserId', DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as AppraiserName"))
            ->first();

        $requestId = DB::connection($expenseConn)
            ->table('advance_requests')
            ->insertGetId([
                'employee_id' => $empId,
                'employee_name' => $employee->EmpName ?? '',
                'emp_code' => $employee->EmpCode ?? '',
                'request_type' => $requestType,
                'request_month' => $requestMonth,
                'amount' => $amount,
                'approver_amount' => $amount,
                'purpose' => $purpose,
                'activity_name_id' => $activityNameId,
                'activity_type_id' => $activityTypeId,
                'approver_id' => $appraiser->AppraiserId ?? null,
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->success(['request_id' => $requestId], 'Advance request submitted successfully');
    }

    /**
     * Perform action on advance request (approve/reject).
     * Replicates: common.php?value=RQ_Action
     */
    public function action(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $requestId = (int) $request->input('request_id');
        $role = $request->input('role', '');
        $action = $request->input('action', '');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $requestId <= 0 || empty($role) || empty($action) || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $advanceRequest = DB::connection($expenseConn)
            ->table('advance_requests')
            ->where('request_id', $requestId)
            ->first();

        if (!$advanceRequest) {
            return $this->error('Advance request not found.');
        }

        $remark = $request->input('remark', '');
        $approverAmount = $request->input('approver_amount', $advanceRequest->amount);

        if ($action === 'approve') {
            $newStatus = $role === 'L1' ? 'L1_Approved' : ($role === 'L2' ? 'Approved' : 'Finance_Approved');

            DB::connection($expenseConn)
                ->table('advance_requests')
                ->where('request_id', $requestId)
                ->update([
                    'status' => $newStatus,
                    'approver_amount' => $approverAmount,
                    'approver_remark' => $remark,
                    'approved_date' => now(),
                    'updated_at' => now(),
                ]);

            return $this->success([], 'Advance request approved');
        } elseif ($action === 'reject') {
            DB::connection($expenseConn)
                ->table('advance_requests')
                ->where('request_id', $requestId)
                ->update([
                    'status' => 'Rejected',
                    'approver_remark' => $remark,
                    'rejected_date' => now(),
                    'updated_at' => now(),
                ]);

            return $this->success([], 'Advance request rejected');
        }

        return $this->error('Invalid action.');
    }
}
