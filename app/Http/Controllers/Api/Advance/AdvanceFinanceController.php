<?php
namespace App\Http\Controllers\Api\Advance;

use App\Exports\PaymentExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advance\FinanceApproveRequest;
use App\Http\Requests\Advance\RejectRequest;
use App\Http\Requests\Advance\RevertRequest;
use App\Mail\AdvanceRequestNotification;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestAction;
use App\Models\AdvanceRequestActivity;
use App\Models\DigiNextPaymentFile;
use App\Models\FinancialYear;
use App\Models\MonthExpenseFinal;
use App\Traits\HandlesNullValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class AdvanceFinanceController extends Controller
{
    use HandlesNullValues;

    public function __construct()
    {
        // No service dependency needed
    }

    protected function jsonSuccess($data = null, $message = 'Success')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function jsonError($message = 'Error', $code = 400)
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }

    public function pending(Request $request)
    {
        try {
            // Get year_id from request parameter (default to current year if not provided)
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            // Get requests that have approved activities ready for finance processing
            $requests = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])
                ->where(function ($query) {
                    $query->where('current_status', AdvanceRequest::STATUS_FINANCE)->orWhere(function ($subQuery) {
                        // Or requests in approval that have some approved activities
                        $subQuery->where('current_status', AdvanceRequest::STATUS_IN_APPROVAL)->whereHas('activities', function ($activityQuery) {
                            $activityQuery->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->where('finance_status', 'Pending');
                        });
                    });
                })
                // Only show requests that actually have approved activities ready for finance
                ->whereHas('activities', function ($activityQuery) {
                    $activityQuery->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->where('finance_status', 'Pending');
                })
                ->orderBy('updated_at', 'asc')
                ->get();
            // Transform the data to match the expected format - employees with req_data inside (same as AdvanceRequestController)
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get overall approval summary for this request (same as AdvanceRequestController)
                $approvalSummary = [];
                try {
                    $overallApprovals = DB::table('advance_activity_approvals as aaa')->join('advance_request_activities as ara', 'aaa.advance_request_activity_id', '=', 'ara.id')->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')->where('are.advance_request_id', $advanceRequest->id)->where('aaa.submission_no', $advanceRequest->submission_no)->select(['aaa.approval_level', DB::raw('COUNT(aaa.id) as total_approvals'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Approved" THEN 1 END) as approved_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Rejected" THEN 1 END) as rejected_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Pending" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Reverted" THEN 1 END) as reverted_count'), DB::raw('SUM(CASE WHEN aaa.approver_status = "Approved" THEN aaa.approver_amount ELSE 0 END) as total_approved_amount'), DB::raw('MIN(aaa.approver_date) as first_action_date'), DB::raw('MAX(aaa.approver_date) as last_action_date')])->groupBy('aaa.approval_level')->orderBy('aaa.approval_level')->get();
                    foreach ($overallApprovals as $levelSummary) {
                        // Determine overall status for this level
                        $levelStatus = 'Pending';
                        if ($levelSummary->rejected_count > 0) {
                            $levelStatus = 'Rejected';
                        } elseif ($levelSummary->reverted_count > 0) {
                            $levelStatus = 'Reverted';
                        } elseif ($levelSummary->pending_count == 0 && $levelSummary->approved_count > 0) {
                            $levelStatus = 'Approved';
                        }
                        $approvalSummary[] = ['level' => $levelSummary->approval_level, 'total_approvals' => $levelSummary->total_approvals, 'approved_count' => $levelSummary->approved_count, 'rejected_count' => $levelSummary->rejected_count, 'pending_count' => $levelSummary->pending_count, 'reverted_count' => $levelSummary->reverted_count, 'total_approved_amount' => $levelSummary->total_approved_amount ? (float) $levelSummary->total_approved_amount : 0, 'first_action_date' => $levelSummary->first_action_date, 'last_action_date' => $levelSummary->last_action_date, 'status' => $levelStatus, 'completion_percentage' => $levelSummary->total_approvals > 0 ? round((($levelSummary->approved_count + $levelSummary->rejected_count + $levelSummary->reverted_count) / $levelSummary->total_approvals) * 100, 2) : 0];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval summary for request ' . $advanceRequest->id . ': ' . $e->getMessage());
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'approval_summary' => $approvalSummary, 'advance_history' => $this->getAdvanceHistory($advanceRequest->id), 'expense_submissions' => $this->getExpenseSubmissions($advanceRequest, $yearId)];
                // Process each employee for this request (same logic as AdvanceRequestController)
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any approved activities ready for finance
                    $hasFinancePendingActivities = $employee->activities->filter(function ($activity) {
                        return $activity->approval_status === AdvanceRequestActivity::APPROVAL_STATUS_APPROVED && $activity->finance_status === 'Pending';
                    })->isNotEmpty();
                    if (!$hasFinancePendingActivities) {
                        continue;  // Skip employees with no finance-pending activities
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee (same structure as AdvanceRequestController)
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include approved activities that haven't been processed by finance
                        if ($activity->approval_status !== AdvanceRequestActivity::APPROVAL_STATUS_APPROVED || $activity->finance_status !== 'Pending') {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                    $cropNames = $crops->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching crop names: ' . $e->getMessage());
                            }
                        }
                        // Get variety names
                        $varietyNames = [];
                        if ($activity->activity_variety_id) {
                            try {
                                $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }
                        // Get approval details for this activity (same as AdvanceRequestController)
                        $approvalDetails = [];
                        try {
                            $approvals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $advanceRequest->submission_no)->orderBy('approval_level')->get();
                            foreach ($approvals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                // Get reverter details if reverted
                                $reverterDetails = null;
                                if ($approval->reverted_by) {
                                    $reverterDetails = $this->getEmployeeDetails($approval->reverted_by);
                                }
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have finance-pending activities (same structure as AdvanceRequestController)
                    if (!empty($activitiesData)) {
                        // Get employee ledger balance
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Pending finance approvals retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve pending finance approvals: ' . $e->getMessage());
        }
    }

    public function approve(FinanceApproveRequest $request)
    {
        try {
            $userId = $request->get('user_id');
            $employeeId = $request->input('employee_id');  // Employee ID to approve
            $requestId = $request->input('request_id');  // Advance request ID
            $activityId = $request->input('activity_id');  // Specific activity ID (optional)
            $approvedAmount = $request->input('finance_approved_amount');
            $remarks = $request->input('finance_remark');
            $fxPaidRate = (float) $request->input('fx_paid_rate', 0); // FX-53: actual rate for a foreign-currency advance
            // Validate required parameters
            if (!$employeeId || !$requestId) {
                return $this->jsonError('employee_id and request_id are required');
            }
            $advanceRequest = $this->financeApproveEmployeeActivities($requestId, $employeeId, $userId, $approvedAmount, $remarks, $activityId, $fxPaidRate);
            return $this->jsonSuccess($advanceRequest, 'Employee activities approved by finance successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to approve request: ' . $e->getMessage());
        }
    }

    public function reject(RejectRequest $request)
    {
        try {
            $userId = $request->get('user_id');
            $employeeId = $request->input('employee_id');  // Employee ID to reject
            $requestId = $request->input('request_id');  // Advance request ID
            $activityId = $request->input('activity_id');  // Specific activity ID (optional)
            $remarks = $request->input('remarks');
            // Validate required parameters
            if (!$employeeId || !$requestId) {
                return $this->jsonError('employee_id and request_id are required');
            }
            $advanceRequest = $this->financeRejectEmployeeActivities($requestId, $employeeId, $userId, $remarks, $activityId);
            return $this->jsonSuccess($advanceRequest, 'Employee activities rejected by finance successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to reject request: ' . $e->getMessage());
        }
    }

    public function revert(RevertRequest $request)
    {
        try {
            $userId = $request->get('user_id');
            $employeeId = $request->input('employee_id');  // Employee ID to revert
            $requestId = $request->input('request_id');  // Advance request ID
            $activityId = $request->input('activity_id');  // Specific activity ID (optional)
            $remarks = $request->input('remarks');
            // Validate required parameters
            if (!$employeeId || !$requestId) {
                return $this->jsonError('employee_id and request_id are required');
            }
            $advanceRequest = $this->financeRevertEmployeeActivities($requestId, $employeeId, $userId, $remarks, $activityId);
            return $this->jsonSuccess($advanceRequest, 'Employee activities reverted by finance successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to revert request: ' . $e->getMessage());
        }
    }

    private function financeApproveEmployeeActivities($requestId, $employeeId, $financeUserId, $approvedAmount = null, $remarks = null, $specificActivityId = null, float $fxPaidRate = 0)
    {
        return DB::transaction(function () use ($requestId, $employeeId, $financeUserId, $approvedAmount, $remarks, $specificActivityId, $fxPaidRate) {
            $request = AdvanceRequest::findOrFail($requestId);
            // Allow finance processing for both Finance status and In Approval status (with approved activities)
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_FINANCE, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot process by finance. Expected 'Finance' or 'In Approval'");
            }
            // Get the employee record for this request
            $requestEmployee = $request->employees()->where('employee_id', $employeeId)->first();
            if (!$requestEmployee) {
                throw new Exception("Employee ID {$employeeId} not found in request ID {$requestId}");
            }
            // Get activities for this employee that are approved and not yet processed by finance
            $activitiesQuery = $requestEmployee->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->where('finance_status', 'Pending');
            // If specific activity ID is provided, filter to that activity
            if ($specificActivityId) {
                $activitiesQuery->where('id', $specificActivityId);
            }
            $activities = $activitiesQuery->get();
            if ($activities->isEmpty()) {
                throw new Exception("No approved activities ready for finance found for employee ID {$employeeId}");
            }
            // ADV-AMT-02: Finance approves the amount the approvers approved; it cannot be changed here
            $carried = [];
            foreach ($activities as $a) {
                $carried[$a->id] = \App\Support\AdvanceAmount::carried($a);
            }
            $totalAmount = array_sum($carried);
            if ($fxPaidRate <= 0 && $approvedAmount && \App\Support\AdvanceAmount::differs($approvedAmount, $totalAmount)) {
                throw new Exception('The amount cannot be changed at Finance. Approved amount is ' . number_format($totalAmount, 2) . '.');
            }
            // FX-53: a foreign-currency advance paid at the actual rate (one activity at a time)
            $fxPaid = null;
            if ($fxPaidRate > 0) {
                if ($activities->count() !== 1 || ! \App\Services\Advance\ForeignCurrencyAdvance::isForeign($activities->first()->currency ?? null)) {
                    throw new Exception('fx_paid_rate needs activity_id of one foreign-currency activity');
                }
                $fxPaid = \App\Services\Advance\ForeignCurrencyAdvance::paidInr($activities->first(), $fxPaidRate);
                $approvedAmount = $approvedAmount ?: $fxPaid;
                $totalAmount = max($totalAmount, $fxPaid);
            }
            $finalAmount = $approvedAmount ?: $totalAmount;
            if ($finalAmount > $totalAmount) {
                throw new Exception('Finance approved amount cannot exceed total activities amount');
            }
            // Process all activities for this employee
            foreach ($activities as $activity) {
                // Calculate proportional amount for this activity
                $activityApprovalAmount = $fxPaid === null ? $carried[$activity->id] : ($activities->count() > 1 ? ($activity->amount / $totalAmount) * $finalAmount : $finalAmount);
                // Update activity
                $upd = ['finance_id' => $financeUserId, 'finance_approved_amount' => $activityApprovalAmount, 'finance_status' => AdvanceRequestActivity::FINANCE_STATUS_APPROVED, 'finance_remark' => $remarks, 'finance_date' => now()->toDateString()];
                if ($fxPaid !== null) {
                    $upd['fx_paid_rate'] = round($fxPaidRate, 4);
                    $upd['fx_paid_amount'] = round($activityApprovalAmount / $fxPaidRate, 2);
                }
                $activity->update($upd);
                // Log action for each activity
                $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_APPROVE, $financeUserId, null, $activity->id, null, $remarks);
            }
            // Update employee ledger balance with the total approved amount
            $employeeDetails = $this->getEmployeeDetails($employeeId);
            $employeeName = $employeeDetails['EmployeeName'] ?? 'Unknown';
            $balanceRemark = "Advance approved for {$employeeName} - Request #{$requestId} - Amount: {$finalAmount}";
            // $this->updateEmployeeLedgerBalance($employeeId, $finalAmount, null,  // Use current year
            //     1,  // Default company ID
            //     $balanceRemark);
            // Check if all activities are finance approved
            $this->checkAndUpdateRequestFinanceStatus($request);
            // Send notification emails
            $this->sendFinanceApprovalNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function financeRejectEmployeeActivities($requestId, $employeeId, $financeUserId, $remarks, $specificActivityId = null)
    {
        return DB::transaction(function () use ($requestId, $employeeId, $financeUserId, $remarks, $specificActivityId) {
            $request = AdvanceRequest::findOrFail($requestId);
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_FINANCE, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot process by finance. Expected 'Finance' or 'In Approval'");
            }
            // Get the employee record for this request
            $requestEmployee = $request->employees()->where('employee_id', $employeeId)->first();
            if (!$requestEmployee) {
                throw new Exception("Employee ID {$employeeId} not found in request ID {$requestId}");
            }
            // Get activities for this employee that are approved and not yet processed by finance
            $activitiesQuery = $requestEmployee->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->where('finance_status', 'Pending');
            // If specific activity ID is provided, filter to that activity
            if ($specificActivityId) {
                $activitiesQuery->where('id', $specificActivityId);
            }
            $activities = $activitiesQuery->get();
            if ($activities->isEmpty()) {
                throw new Exception("No approved activities ready for finance found for employee ID {$employeeId}");
            }
            // Process all activities for this employee
            foreach ($activities as $activity) {
                // Update activity
                $activity->update(['finance_id' => $financeUserId, 'finance_status' => AdvanceRequestActivity::FINANCE_STATUS_REJECTED, 'finance_remark' => $remarks, 'finance_date' => now()->toDateString()]);
                // Log action for each activity
                $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_REJECT, $financeUserId, null, $activity->id, null, $remarks);
            }
            // Check if entire request should move to next status
            $this->checkAndUpdateRequestFinanceStatus($request);
            // Send notification emails
            $this->sendFinanceRejectionNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function financeRevertEmployeeActivities($requestId, $employeeId, $financeUserId, $remarks, $specificActivityId = null)
    {
        return DB::transaction(function () use ($requestId, $employeeId, $financeUserId, $remarks, $specificActivityId) {
            $request = AdvanceRequest::findOrFail($requestId);
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_FINANCE, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot process by finance. Expected 'Finance' or 'In Approval'");
            }
            // Get the employee record for this request
            $requestEmployee = $request->employees()->where('employee_id', $employeeId)->first();
            if (!$requestEmployee) {
                throw new Exception("Employee ID {$employeeId} not found in request ID {$requestId}");
            }
            // Get activities for this employee that are approved and not yet processed by finance
            $activitiesQuery = $requestEmployee->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->where('finance_status', 'Pending');
            // If specific activity ID is provided, filter to that activity
            if ($specificActivityId) {
                $activitiesQuery->where('id', $specificActivityId);
            }
            $activities = $activitiesQuery->get();
            if ($activities->isEmpty()) {
                throw new Exception("No approved activities ready for finance found for employee ID {$employeeId}");
            }
            // Process all activities for this employee
            foreach ($activities as $activity) {
                // Find the last approved level for this activity using direct query
                $lastApprovedLevel = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('approver_status', 'Approved')->max('approval_level');
                // Update activity to go back to last approved level
                $activity->update(['current_approval_level' => $lastApprovedLevel, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_REVERTED]);
                // Log action for each activity
                $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_REVERT, $financeUserId, null, $activity->id, null, $remarks);
            }
            // Update request status
            $request->update(['current_status' => AdvanceRequest::STATUS_REVERTED, 'reverted_from_status' => $request->current_status, 'revert_reason' => $remarks, 'reverted_by' => $financeUserId, 'reverted_at' => now()]);
            // Send notification emails
            $this->sendFinanceRevertNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function financeApproveActivity($activityId, $financeUserId, $approvedAmount = null, $remarks = null)
    {
        return DB::transaction(function () use ($activityId, $financeUserId, $approvedAmount, $remarks) {
            $activity = AdvanceRequestActivity::findOrFail($activityId);
            $request = $activity->employee->advanceRequest;
            // Allow finance processing for both Finance status and In Approval status (with approved activities)
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_FINANCE, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot process by finance. Expected 'Finance' or 'In Approval'");
            }
            // Ensure the activity is approved and not already processed by finance
            if ($activity->approval_status !== AdvanceRequestActivity::APPROVAL_STATUS_APPROVED) {
                throw new Exception("Activity is not approved yet. Current status: {$activity->approval_status}");
            }
            if ($activity->finance_status !== 'Pending') {
                throw new Exception("Activity has already been processed by finance. Status: {$activity->finance_status}");
            }
            // ADV-AMT-02: Finance approves the amount the approvers approved; it cannot be changed here
            $finalAmount = \App\Support\AdvanceAmount::carried($activity);
            if ($approvedAmount && \App\Support\AdvanceAmount::differs($approvedAmount, $finalAmount)) {
                throw new Exception('The amount cannot be changed at Finance. Approved amount is ' . number_format($finalAmount, 2) . '.');
            }
            // Update activity
            $activity->update(['finance_id' => $financeUserId, 'finance_approved_amount' => $finalAmount, 'finance_status' => AdvanceRequestActivity::FINANCE_STATUS_APPROVED, 'finance_remark' => $remarks, 'finance_date' => now()->toDateString()]);
            // Check if all activities are finance approved
            $this->checkAndUpdateRequestFinanceStatus($request);
            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_APPROVE, $financeUserId, null, $activity->id, null, $remarks);
            // Send notification emails
            $this->sendFinanceApprovalNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function financeRejectActivity($activityId, $financeUserId, $remarks)
    {
        return DB::transaction(function () use ($activityId, $financeUserId, $remarks) {
            $activity = AdvanceRequestActivity::findOrFail($activityId);
            $request = $activity->employee->advanceRequest;
            if ($request->current_status !== AdvanceRequest::STATUS_FINANCE) {
                throw new Exception("Request is in '{$request->current_status}' status, not at finance level. Expected 'Finance'");
            }
            // Update activity
            $activity->update(['finance_id' => $financeUserId, 'finance_status' => AdvanceRequestActivity::FINANCE_STATUS_REJECTED, 'finance_remark' => $remarks, 'finance_date' => now()->toDateString()]);
            // Update request status
            $request->update(['current_status' => AdvanceRequest::STATUS_REJECTED]);
            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_REJECT, $financeUserId, null, $activity->id, null, $remarks);
            // Send notification emails
            $this->sendFinanceRejectionNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function financeRevertActivity($activityId, $financeUserId, $remarks)
    {
        return DB::transaction(function () use ($activityId, $financeUserId, $remarks) {
            $activity = AdvanceRequestActivity::findOrFail($activityId);
            $request = $activity->employee->advanceRequest;
            if ($request->current_status !== AdvanceRequest::STATUS_FINANCE) {
                throw new Exception("Request is in '{$request->current_status}' status, not at finance level. Expected 'Finance'");
            }
            // Find the last approved level for this activity using direct query
            $lastApprovedLevel = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activityId)->where('approver_status', 'Approved')->max('approval_level');
            // Update activity to go back to last approved level
            $activity->update(['current_approval_level' => $lastApprovedLevel, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_REVERTED]);
            // Update request status
            $request->update(['current_status' => AdvanceRequest::STATUS_REVERTED, 'reverted_from_status' => AdvanceRequest::STATUS_FINANCE, 'revert_reason' => $remarks, 'reverted_by' => $financeUserId, 'reverted_at' => now()]);
            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_REVERT, $financeUserId, null, $activity->id, null, $remarks);
            // Send notification emails
            $this->sendFinanceRevertNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function checkAndUpdateRequestFinanceStatus(AdvanceRequest $request)
    {
        // Check if all approved activities have been processed by finance
        $approvedActivities = $request->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->count();
        $financeProcessedActivities = $request->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->whereIn('finance_status', [AdvanceRequestActivity::FINANCE_STATUS_APPROVED, AdvanceRequestActivity::FINANCE_STATUS_REJECTED])->count();
        // If all approved activities have been processed by finance, close the request
        if ($approvedActivities > 0 && $financeProcessedActivities === $approvedActivities) {
            // Check if there are any activities still pending approval
            $pendingApprovalActivities = $request->activities()->whereIn('approval_status', [AdvanceRequestActivity::APPROVAL_STATUS_PENDING])->count();
            if ($pendingApprovalActivities === 0) {
                // All activities are either finance-processed or rejected at approval level
                $request->update(['current_status' => AdvanceRequest::STATUS_CLOSED]);
            } else {
                // Some activities still pending approval, keep in finance status
                $request->update(['current_status' => AdvanceRequest::STATUS_FINANCE]);
            }
        }
    }

    private function logAction(AdvanceRequest $request, $actionType, $actorId, $targetId = null, $activityId = null, $approvalLevel = null, $remarks = null, $templateId = null)
    {
        AdvanceRequestAction::create(['advance_request_id' => $request->id, 'submission_no' => $request->submission_no, 'action_type' => $actionType, 'action_by' => $actorId, 'action_to' => $targetId, 'related_activity_id' => $activityId, 'related_approval_level' => $approvalLevel, 'email_template_id' => $templateId, 'remarks' => $remarks, 'action_at' => now()]);
    }

    // Email notification methods (simplified for now)
    private function sendFinanceApprovalNotifications(AdvanceRequest $request)
    {
        $this->sendFinanceEmailNotification($request, 'finance_approval');
    }

    private function sendFinanceRejectionNotifications(AdvanceRequest $request)
    {
        $this->sendFinanceEmailNotification($request, 'finance_rejection');
    }

    private function sendFinanceRevertNotifications(AdvanceRequest $request)
    {
        $this->sendFinanceEmailNotification($request, 'finance_revert');
    }

    private function sendFinanceEmailNotification(AdvanceRequest $request, $actionType)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }
            // Get requester details
            $requester = $this->getEmployeeDetails($request->req_app_submit_id);
            if (!$requester || empty($requester['EmailId_Vnr'])) {
                \Log::warning("No email found for requester ID: {$request->req_app_submit_id}");
                return;
            }
            // Get activity details for email table
            $activities = $this->getActivityDetailsForEmail($request);
            // Prepare email data
            $purpose = $request->activities->first()->purpose ?? 'Advance Request';

            $emailData = [
                'request_no' => $request->id,
                'requester_name' => $requester['EmployeeName'],
                'amount' => $request->activities->sum('amount'),
                'finance_approved_amount' => $request->activities->sum('finance_approved_amount'),
                'purpose' => $purpose,
                'status' => $request->current_status,
                'action_type' => $actionType,
                'created_date' => $request->created_at->format('Y-m-d'),
                'processed_date' => now()->format('Y-m-d'),
                'activities' => $activities
            ];
            if ($purpose != 'Auto Advance based on expense percentage') {
                // Mail::to($requester['EmailId_Vnr'])->send(new AdvanceRequestNotification($emailData));
                // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));
            }
        } catch (\Exception $e) {
            \Log::error('Error sending finance email notification: ' . $e->getMessage());
        }
    }

    public function finalizeAmounts(Request $request)
    {
        try {
            // Validate request
            $request->validate(['payment_file' => 'required|file|mimes:xlsx,xls|max:10240', 'year_id' => 'nullable|integer', 'company_id' => 'nullable|integer']);
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $companyId = $request->input('company_id', 1);
            $userId = $request->get('user_id');
            $result = $this->processPaymentConfirmationFile($request->file('payment_file'), $yearId, $companyId, $userId);
            return $this->jsonSuccess($result, 'Payment confirmation processed successfully');
        } catch (\Exception $e) {
            return $this->jsonError('Failed to process payment confirmation: ' . $e->getMessage());
        }
    }

    private function processPaymentConfirmationFile($file, $yearId = null, $companyId = 1, $userId = null)
    {
        return DB::transaction(function () use ($file, $yearId, $companyId, $userId) {
            // Read Excel file
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = [];
            $highestRow = $worksheet->getHighestRow();
            // Read data starting from row 2 (assuming row 1 has headers)
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= 17; $col++) {  // Assuming 17 columns based on digi_next_payment_file structure
                    $cell = $worksheet->getCell([$col, $row]);
                    $value = $cell->getValue();
                    // Handle date formatting
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('d/m/Y');
                    }
                    $rowData[] = $value;
                }
                // Skip empty rows
                if (!empty(array_filter($rowData))) {
                    $data[] = $rowData;
                }
            }
            $totalRows = count($data);
            $processedRows = 0;
            $matchedAdvances = 0;
            $finalizedAdvances = 0;
            $errors = [];
            $matchedRecords = [];
            foreach ($data as $index => $row) {
                try {
                    $rowNumber = $index + 2;  // Excel row number
                    // Extract payment data from Excel row
                    $paymentData = ['record_no' => $this->cleanValue($row[0] ?? null), 'beneficiary_account_number' => $this->cleanValue($row[1] ?? null), 'transaction_refno' => $this->cleanValue($row[2] ?? null), 'bank_name' => $this->cleanValue($row[3] ?? null), 'ifsc_code' => $this->cleanValue($row[4] ?? null), 'debit_account_number' => $this->cleanValue($row[5] ?? null), 'value_date' => $this->convertDate($row[6] ?? null), 'amount' => $this->convertAmount($row[7] ?? null), 'beneficiary_name' => $this->cleanValue($row[8] ?? null), 'payment_type' => $this->cleanValue($row[9] ?? null), 'rbi_utr_reference_number' => $this->cleanValue($row[10] ?? null), 'currency' => $this->cleanValue($row[11] ?? null) ?: 'INR', 'error_description' => $this->cleanValue($row[12] ?? null), 'transaction_status' => $this->cleanValue($row[13] ?? null) ?: 'SUCCESS', 'beneficiary_aadhaar_number' => $this->cleanValue($row[14] ?? null), 'destination_bank' => $this->cleanValue($row[15] ?? null), 'credit_reference' => $this->cleanValue($row[16] ?? null)];
                    // Validate required fields
                    if (empty($paymentData['beneficiary_name']) || empty($paymentData['amount'])) {
                        $errors[] = ['row' => $rowNumber, 'error' => 'Missing required fields: beneficiary_name or amount'];
                        continue;
                    }
                    // Check if this payment record exists in digi_next_payment_file
                    $existingPayment = DigiNextPaymentFile::where('transaction_refno', $paymentData['transaction_refno'])->orWhere(function ($query) use ($paymentData) {
                        $query->where('beneficiary_name', 'LIKE', '%' . $paymentData['beneficiary_name'] . '%')->where('amount', $paymentData['amount']);
                    })->first();
                    if (!$existingPayment) {
                        // Create new payment record if it doesn't exist
                        $paymentData['import_date'] = now()->format('Y-m-d');
                        $paymentData['import_batch_id'] = 'PAYMENT_CONFIRM_' . now()->format('YmdHis');
                        $existingPayment = DigiNextPaymentFile::create($paymentData);
                    }
                    // Find matching advance request activities
                    $matchingActivities = $this->findMatchingAdvanceActivities($paymentData['beneficiary_name'], $paymentData['amount'], $yearId);
                    if ($matchingActivities->isNotEmpty()) {
                        $matchedAdvances++;
                        foreach ($matchingActivities as $activity) {
                            // Update activity with payment information
                            $activity->update(['payment_id' => $userId, 'payment_amount' => $paymentData['amount'], 'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_RELEASED, 'payment_remark' => 'Payment confirmed via Excel upload - Transaction: ' . $paymentData['transaction_refno'], 'payment_date' => now()->toDateString(), 'final_amount' => $paymentData['amount']  // Add final amount field
                            ]);
                            // Log the finalization action
                            $this->logAction($activity->employee->advanceRequest, AdvanceRequestAction::ACTION_PAYMENT_RELEASED, $userId, null, $activity->id, null, 'Payment finalized via Excel upload - Amount: ' . $paymentData['amount']);
                            $finalizedAdvances++;
                        }
                        // Check if all activities for the request are now finalized
                        $this->checkAndFinalizeAdvanceRequest($matchingActivities->first()->employee->advanceRequest);
                        $matchedRecords[] = ['row' => $rowNumber, 'beneficiary_name' => $paymentData['beneficiary_name'], 'amount' => $paymentData['amount'], 'transaction_refno' => $paymentData['transaction_refno'], 'matched_activities' => $matchingActivities->count(), 'advance_request_ids' => $matchingActivities->pluck('employee.advance_request_id')->unique()->values()];
                    }
                    $processedRows++;
                } catch (\Exception $e) {
                    $errors[] = ['row' => $index + 2, 'error' => $e->getMessage()];
                }
            }
            return ['total_rows' => $totalRows, 'processed_rows' => $processedRows, 'matched_advances' => $matchedAdvances, 'finalized_advances' => $finalizedAdvances, 'errors' => $errors, 'matched_records' => $matchedRecords, 'summary' => ['success_rate' => $totalRows > 0 ? round(($processedRows / $totalRows) * 100, 2) : 0, 'match_rate' => $processedRows > 0 ? round(($matchedAdvances / $processedRows) * 100, 2) : 0]];
        });
    }

    private function findMatchingAdvanceActivities($beneficiaryName, $amount, $yearId = null)
    {
        // Get finance approved activities that haven't been released for payment yet
        $activities = AdvanceRequestActivity::with(['employee.advanceRequest'])->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where(function ($query) {
            $query->whereNull('payment_status')->orWhere('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PENDING);
        })->where('finance_approved_amount', $amount)->get();
        // Filter by employee name match
        $matchingActivities = $activities->filter(function ($activity) use ($beneficiaryName) {
            $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
            $employeeName = $employeeDetails['EmployeeName'] ?? '';
            // Check for name similarity (case-insensitive, partial match)
            return stripos($employeeName, $beneficiaryName) !== false || stripos($beneficiaryName, $employeeName) !== false || $this->calculateNameSimilarity($employeeName, $beneficiaryName) > 0.8;
        });
        return $matchingActivities;
    }

    private function checkAndFinalizeAdvanceRequest(AdvanceRequest $request)
    {
        // Check if all finance-approved activities have been released for payment
        $approvedActivities = $request->activities()->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->count();
        $releasedActivities = $request->activities()->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_RELEASED)->count();
        // If all approved activities have been released, mark request as finalized
        if ($approvedActivities > 0 && $releasedActivities === $approvedActivities) {
            $request->update(['current_status' => AdvanceRequest::STATUS_FINALIZED, 'finalized_at' => now()]);
        }
    }

    public function getFinalizationReady(Request $request)
    {
        try {
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            // Get requests that have finance-approved activities ready for payment release
            $requests = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->whereHas('activities', function ($activityQuery) {
                $activityQuery->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where(function ($paymentQuery) {
                    $paymentQuery->whereNull('payment_status')->orWhere('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PENDING);
                });
            })->orderBy('updated_at', 'desc')->get();
            // Transform data similar to approved() method but focus on finalization readiness
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at];
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has activities ready for finalization
                    $hasFinalizationReadyActivities = $employee->activities->filter(function ($activity) {
                        return $activity->finance_status === AdvanceRequestActivity::FINANCE_STATUS_APPROVED && (is_null($activity->payment_status) || $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PENDING);
                    })->isNotEmpty();
                    if (!$hasFinalizationReadyActivities) {
                        continue;
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include activities ready for finalization
                        if ($activity->finance_status !== AdvanceRequestActivity::FINANCE_STATUS_APPROVED || (!is_null($activity->payment_status) && $activity->payment_status !== AdvanceRequestActivity::PAYMENT_STATUS_PENDING)) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_status' => $activity->payment_status, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at];
                    }
                    // Only add employee if they have finalization-ready activities
                    if (!empty($activitiesData)) {
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'total_finalization_amount' => collect($activitiesData)->sum('finance_approved_amount'), 'activities_count' => count($activitiesData), 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Finalization-ready advance requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve finalization-ready requests: ' . $e->getMessage());
        }
    }

    public function getFinalized(Request $request)
    {
        try {
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            // Get requests that have been finalized
            $requests = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->where('current_status', AdvanceRequest::STATUS_FINALIZED)->orWhereHas('activities', function ($activityQuery) {
                $activityQuery->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_RELEASED);
            })->orderBy('updated_at', 'desc')->get();
            // Transform data similar to other methods
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'finalized_at' => $advanceRequest->finalized_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at];
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has finalized activities
                    $hasFinalizedActivities = $employee->activities->filter(function ($activity) {
                        return $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_RELEASED;
                    })->isNotEmpty();
                    if (!$hasFinalizedActivities) {
                        continue;
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include finalized activities
                        if ($activity->payment_status !== AdvanceRequestActivity::PAYMENT_STATUS_RELEASED) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'final_amount' => $activity->final_amount, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at];
                    }
                    // Only add employee if they have finalized activities
                    if (!empty($activitiesData)) {
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'total_payment_amount' => collect($activitiesData)->sum('payment_amount'), 'activities_count' => count($activitiesData), 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Finalized advance requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve finalized requests: ' . $e->getMessage());
        }
    }

    private function getEmployeeLedgerBalance($employeeId, $yearId = null, $companyId = 1)
    {
        try {
            $query = DB::table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->orderBy('id', 'desc');
            if (!is_null($yearId)) {
                $query->where('year_id', $yearId);
            }
            $balances = $query->get();
            if ($balances->isEmpty()) {
                return [];
            }
            return $balances->map(function ($row) {
                return [
                    'id' => $row->id,
                    'year_id' => $row->year_id,
                    'employee_id' => $row->employee_id,
                    'company_id' => $row->company_id,
                    'fixed_imprest_amt_1' => (float) $row->fixed_imprest_amt_1,
                    'fixed_imprest_date_1' => $row->fixed_imprest_date_1,
                    'fixed_imprest_amt_2' => (float) ($row->fixed_imprest_amt_2 ?? 0),
                    'fixed_imprest_date_2' => $row->fixed_imprest_date_2,
                    'advance_amt_1' => (float) ($row->advance_amt_1 ?? 0),
                    'advance_date_1' => $row->advance_date_1,
                    'advance_month_1' => $row->advance_month_1,
                    'advance_purpose_1' => $row->advance_purpose_1,
                    'advance_amt_2' => (float) ($row->advance_amt_2 ?? 0),
                    'advance_date_2' => $row->advance_date_2,
                    'advance_month_2' => $row->advance_month_2,
                    'advance_purpose_2' => $row->advance_purpose_2,
                    'advance_amt_3' => (float) ($row->advance_amt_3 ?? 0),
                    'advance_date_3' => $row->advance_date_3,
                    'advance_month_3' => $row->advance_month_3,
                    'advance_purpose_3' => $row->advance_purpose_3,
                    'advance_amt_4' => (float) ($row->advance_amt_4 ?? 0),
                    'advance_date_4' => $row->advance_date_4,
                    'advance_month_4' => $row->advance_month_4,
                    'advance_purpose_4' => $row->advance_purpose_4,
                    'balance' => (float) $row->balance,
                    'remark' => $row->remark,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function updateEmployeeLedgerBalance($employeeId, $amount, $yearId = null, $companyId = 1, $remark = null, $fixedImprest = null)
    {
        try {
            // Get current balance
            $currentBalance = $this->getEmployeeLedgerBalance($employeeId, $yearId, $companyId);
            $newBalance = $currentBalance['balance'] + $amount;
            // Use existing fixed_imprest if not provided
            $finalFixedImprest = $fixedImprest !== null ? $fixedImprest : $currentBalance['fixed_imprest'];
            // Create new ledger entry
            DB::table('employee_ledger_balances')->insert(['employee_id' => $employeeId, 'year_id' => $yearId, 'company_id' => $companyId, 'dr' => $amount > 0 ? $amount : 0, 'cr' => $amount < 0 ? abs($amount) : 0, 'balance' => $newBalance, 'fixed_imprest' => $finalFixedImprest, 'entry_date' => now()->toDateString(), 'remark' => $remark ?: 'Advance payment processed', 'created_at' => now(), 'updated_at' => now()]);
            \Log::info("Updated ledger balance for employee {$employeeId}: Amount {$amount}, New Balance {$newBalance}, Fixed Imprest {$finalFixedImprest}");
            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating employee ledger balance: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync completed fixed imprest advance payment to employee_ledger_balances.
     * If imprest 1 is already given (> 0), updates imprest 2 amount and date.
     * If no record exists for employee, inserts a new record.
     */
    public function syncImprestToEmployeeLedger($employeeId, $amount, $paymentDate = null, $yearId = null, $companyId = 1)
    {
        try {
            $expConn = session('expense_connection') ?: 'expense';
            $date = $paymentDate ? date('Y-m-d', strtotime($paymentDate)) : now()->toDateString();
            $yearId = $yearId ?: session('year_id', date('Y'));
            $numAmount = floatval($amount);

            // Check if employee has an existing record
            $ledger = DB::connection($expConn)->table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            if (!$ledger) {
                // Insert new record
                DB::connection($expConn)->table('employee_ledger_balances')->insert([
                    'employee_id'          => $employeeId,
                    'year_id'              => $yearId,
                    'company_id'           => $companyId ?: 1,
                    'fixed_imprest_amt_1'  => $numAmount,
                    'fixed_imprest_date_1' => $date,
                    'dr'                   => 0,
                    'cr'                   => 0,
                    'balance'              => 0,
                    'remark'               => 'Auto-created from Fixed Imprest Auto Advance',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                \Log::info("Created new employee_ledger_balances row for employee {$employeeId}: fixed_imprest_amt_1 = {$numAmount}, date = {$date}");
            } else {
                // If fixed_imprest_amt_1 is already given (> 0), update fixed_imprest_amt_2
                if ((float) ($ledger->fixed_imprest_amt_1 ?? 0) > 0) {
                    DB::connection($expConn)->table('employee_ledger_balances')
                        ->where('id', $ledger->id)
                        ->update([
                            'fixed_imprest_amt_2'  => $numAmount,
                            'fixed_imprest_date_2' => $date,
                            'updated_at'           => now(),
                        ]);

                    \Log::info("Updated employee_ledger_balances row #{$ledger->id} for employee {$employeeId}: fixed_imprest_amt_2 = {$numAmount}, date = {$date}");
                } else {
                    // Else update fixed_imprest_amt_1
                    DB::connection($expConn)->table('employee_ledger_balances')
                        ->where('id', $ledger->id)
                        ->update([
                            'fixed_imprest_amt_1'  => $numAmount,
                            'fixed_imprest_date_1' => $date,
                            'updated_at'           => now(),
                        ]);

                    \Log::info("Updated employee_ledger_balances row #{$ledger->id} for employee {$employeeId}: fixed_imprest_amt_1 = {$numAmount}, date = {$date}");
                }
            }
            return true;
        } catch (\Exception $e) {
            \Log::error("syncImprestToEmployeeLedger failed for employee {$employeeId}: " . $e->getMessage());
            return false;
        }
    }

    private function getEmployeeDetails($employeeId)
    {
        if (empty($employeeId) || (int)$employeeId <= 0) {
            return [
                'EmployeeID' => 0,
                'EmployeeCode' => 'SYSTEM',
                'EmployeeName' => 'System Generated (Auto Advance for Imprest)',
                'EmpStatus' => 'A',
                'DateOfSepration' => null,
                'EmailId_Vnr' => null,
                'RepEmployeeID' => null,
                'DepartmentId' => null,
                'DesigId' => null,
                'BUId' => null,
                'ZoneId' => null,
                'ReportingName' => null,
                'ReportingDesigId' => null,
                'ReportingContactNo' => null,
                'ReportingEmailId' => null,
                'Designation' => 'Auto Imprest',
                'DepartmentName' => 'System Automation'
            ];
        }

        try {
            $employee = DB::connection('hrims')->selectOne("
                SELECT
                    gen.EmployeeID AS EmployeeID,
                    hrm_employee.EmpCode_New AS EmployeeCode,
                    CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) AS EmployeeName,
                    hrm_employee.EmpStatus,
                    hrm_employee.DateOfSepration,
                    gen.EmailId_Vnr,
                    gen.RepEmployeeID,
                    gen.DepartmentId,
                    gen.DesigId,
                    gen.BUId,
                    gen.ZoneId,
                    gen.ReportingName,
                    gen.ReportingDesigId,
                    gen.ReportingContactNo,
                    gen.ReportingEmailId,
                    desig.designation_name AS Designation,
                    dept.department_name AS DepartmentName
                FROM hrm_employee_general AS gen
                LEFT JOIN hrm_employee ON gen.EmployeeID = hrm_employee.EmployeeID
                LEFT JOIN core_designation AS desig ON gen.DesigId = desig.id
                LEFT JOIN core_departments AS dept ON gen.DepartmentId = dept.id
                WHERE gen.EmployeeID = ?
            ", [$employeeId]);

            if (!$employee) return null;

            $result = (array) $employee;

            // Override EmpStatus based on separation date (separation date takes priority)
            $sepDate = $result['DateOfSepration'] ?? null;
            if (!empty($sepDate) && \App\Support\ZeroDate::has($sepDate) && $sepDate != '1970-01-01' && $sepDate != '1971-01-01' && strtotime($sepDate) > strtotime('2000-01-01') && strtotime($sepDate) < strtotime(date('Y-m-d'))) {
                $result['EmpStatus'] = 'D';
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error getting employee details: ' . $e->getMessage());
            return null;
        }
    }

    private function getActivityDetailsForEmail($request)
    {
        $activities = [];
        foreach ($request->activities as $activity) {
            $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
            // Get activity type name
            $activityTypeName = 'N/A';
            if ($activity->activity_type_id) {
                try {
                    $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                    $activityTypeName = $activityType->type_name ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity type: ' . $e->getMessage());
                }
            }
            // Get activity name from claimtype table (stored at request level)
            $activityName = 'N/A';
            $requestActivityNameId = $activity->employee->advanceRequest->activity_name_id;
            if ($requestActivityNameId) {
                try {
                    $activityNameRecord = DB::table('claimtype')
                        ->where('ClaimId', $requestActivityNameId)
                        ->where('ClaimStatus', 'A')  // Only active claims
                        ->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
                }
            }
            // Get crop names from HRIMS database
            $cropNames = [];
            if ($activity->activity_crops_id) {
                try {
                    $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                    if (is_array($cropIds) && !empty($cropIds)) {
                        $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                        $cropNames = $crops->toArray();
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching crop names: ' . $e->getMessage());
                }
            }
            $activities[] = ['employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'activity_type' => $activityTypeName, 'activity_name' => $activityName, 'crops' => !empty($cropNames) ? implode(', ', $cropNames) : 'N/A', 'amount' => (float) $activity->amount, 'purpose' => $activity->purpose ?? 'N/A'];
        }
        return $activities;
    }

    public function rejected(Request $request)
    {
        try {
            // Get year_id from request parameter (default to current year if not provided)
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            // Get requests that have activities rejected by finance
            $requests = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->whereHas('activities', function ($activityQuery) {
                $activityQuery->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_REJECTED);
            })->orderBy('updated_at', 'desc')->get();
            // Transform the data to match the expected format - employees with req_data inside (same as AdvanceRequestController)
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get overall approval summary for this request (same as AdvanceRequestController)
                $approvalSummary = [];
                try {
                    $overallApprovals = DB::table('advance_activity_approvals as aaa')->join('advance_request_activities as ara', 'aaa.advance_request_activity_id', '=', 'ara.id')->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')->where('are.advance_request_id', $advanceRequest->id)->where('aaa.submission_no', $advanceRequest->submission_no)->select(['aaa.approval_level', DB::raw('COUNT(aaa.id) as total_approvals'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Approved" THEN 1 END) as approved_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Rejected" THEN 1 END) as rejected_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Pending" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Reverted" THEN 1 END) as reverted_count'), DB::raw('SUM(CASE WHEN aaa.approver_status = "Approved" THEN aaa.approver_amount ELSE 0 END) as total_approved_amount'), DB::raw('MIN(aaa.approver_date) as first_action_date'), DB::raw('MAX(aaa.approver_date) as last_action_date')])->groupBy('aaa.approval_level')->orderBy('aaa.approval_level')->get();
                    foreach ($overallApprovals as $levelSummary) {
                        // Determine overall status for this level
                        $levelStatus = 'Pending';
                        if ($levelSummary->rejected_count > 0) {
                            $levelStatus = 'Rejected';
                        } elseif ($levelSummary->reverted_count > 0) {
                            $levelStatus = 'Reverted';
                        } elseif ($levelSummary->pending_count == 0 && $levelSummary->approved_count > 0) {
                            $levelStatus = 'Approved';
                        }
                        $approvalSummary[] = ['level' => $levelSummary->approval_level, 'total_approvals' => $levelSummary->total_approvals, 'approved_count' => $levelSummary->approved_count, 'rejected_count' => $levelSummary->rejected_count, 'pending_count' => $levelSummary->pending_count, 'reverted_count' => $levelSummary->reverted_count, 'total_approved_amount' => $levelSummary->total_approved_amount ? (float) $levelSummary->total_approved_amount : 0, 'first_action_date' => $levelSummary->first_action_date, 'last_action_date' => $levelSummary->last_action_date, 'status' => $levelStatus, 'completion_percentage' => $levelSummary->total_approvals > 0 ? round((($levelSummary->approved_count + $levelSummary->rejected_count + $levelSummary->reverted_count) / $levelSummary->total_approvals) * 100, 2) : 0];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval summary for request ' . $advanceRequest->id . ': ' . $e->getMessage());
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object (same structure as AdvanceRequestController)
                $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'approval_summary' => $approvalSummary, 'advance_history' => $this->getAdvanceHistory($advanceRequest->id), 'expense_submissions' => $this->getExpenseSubmissions($advanceRequest, $yearId)];
                // Process each employee for this request (same logic as AdvanceRequestController)
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any rejected activities
                    $hasRejectedActivities = $employee->activities->filter(function ($activity) {
                        return $activity->finance_status === AdvanceRequestActivity::FINANCE_STATUS_REJECTED;
                    })->isNotEmpty();
                    if (!$hasRejectedActivities) {
                        continue;  // Skip employees with no rejected activities
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee (same structure as AdvanceRequestController)
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include rejected activities
                        if ($activity->finance_status !== AdvanceRequestActivity::FINANCE_STATUS_REJECTED) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                    $cropNames = $crops->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching crop names: ' . $e->getMessage());
                            }
                        }
                        // Get variety names
                        $varietyNames = [];
                        if ($activity->activity_variety_id) {
                            try {
                                $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }
                        // Get approval details for this activity (same as AdvanceRequestController)
                        $approvalDetails = [];
                        try {
                            $approvals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $advanceRequest->submission_no)->orderBy('approval_level')->get();
                            foreach ($approvals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                // Get reverter details if reverted
                                $reverterDetails = null;
                                if ($approval->reverted_by) {
                                    $reverterDetails = $this->getEmployeeDetails($approval->reverted_by);
                                }
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have rejected activities (same structure as AdvanceRequestController)
                    if (!empty($activitiesData)) {
                        // Get employee ledger balance
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Finance rejected requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve finance rejected requests: ' . $e->getMessage());
        }
    }

    private function updateEmployeeFixedImprest($employeeId, $fixedImprest, $yearId = null, $companyId = 1, $remark = null)
    {
        try {
            // Get current balance
            $currentBalance = $this->getEmployeeLedgerBalance($employeeId, $yearId, $companyId);
            // Create new ledger entry with updated fixed imprest
            DB::table('employee_ledger_balances')->insert(['employee_id' => $employeeId, 'year_id' => $yearId, 'company_id' => $companyId, 'dr' => 0, 'cr' => 0, 'balance' => $currentBalance['balance'],  // Keep same balance
                'fixed_imprest' => $fixedImprest, 'entry_date' => now()->toDateString(), 'remark' => $remark ?: "Fixed imprest updated to {$fixedImprest}", 'created_at' => now(), 'updated_at' => now()]);
            \Log::info("Updated fixed imprest for employee {$employeeId}: Fixed Imprest {$fixedImprest}");
            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating employee fixed imprest: ' . $e->getMessage());
            return false;
        }
    }

    public function getEmployeeBalance(Request $request)
    {
        try {
            $employeeId    = $request->input('employee_id');
            $companyId     = $request->input('company_id', 1);

            if (! $employeeId) {
                return $this->jsonError('employee_id is required');
            }

            $expConn = session('expense_connection', 'expense');

            // ── 1. Latest ledger record ────────────────────────────────────────
            $ledgerBalance = DB::connection($expConn)
                ->table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            $totalImprest   = 0;
            $runningBalance = 0;
            $advances       = [];
            $imprests       = [];

            if ($ledgerBalance) {
                // Imprest slots
                for ($i = 1; $i <= 2; $i++) {
                    $amt = (float) ($ledgerBalance->{"fixed_imprest_amt_{$i}"} ?? 0);
                    if ($amt > 0) {
                        $totalImprest += $amt;
                        $imprests[] = [
                            'slot'             => $i,
                            'amount'           => $amt,
                            'date'             => $ledgerBalance->{"fixed_imprest_date_{$i}"} ?? null,
                        ];
                    }
                }

                // Ledger advance slots → credit
                for ($i = 1; $i <= 4; $i++) {
                    $amt = (float) ($ledgerBalance->{"advance_amt_{$i}"} ?? 0);
                    if ($amt > 0) {
                        $runningBalance += $amt;
                        $advances[] = [
                            'slot'    => $i,
                            'amount'  => $amt,
                            'date'    => $ledgerBalance->{"advance_date_{$i}"} ?? null,
                            'month'   => $ledgerBalance->{"advance_month_{$i}"} ?? null,
                            'purpose' => $ledgerBalance->{"advance_purpose_{$i}"} ?? null,
                        ];
                    }
                }
            }

            // ── 2. Paid advances across ALL years → credit ─────────────────────
            $paidRows = DB::connection($expConn)
                ->table('advance_requests as ar')
                ->join('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
                ->join('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
                ->where('are.employee_id', $employeeId)
                ->whereIn('ara.payment_status', ['Paid', 'Unsettled'])
                ->where(function ($q) {
                    $q->where('ar.is_imprest', 0)->orWhereNull('ar.is_imprest');
                })
                ->where(function ($q) {
                    $q->where('ara.is_imprest', 0)->orWhereNull('ara.is_imprest');
                })
                ->where(function ($q) {
                    $q->where('ara.purpose', 'NOT LIKE', '%imprest%')->orWhereNull('ara.purpose');
                })
                ->where(function ($q) {
                    $q->where('ara.purpose', 'NOT LIKE', '%auto advance%')->orWhereNull('ara.purpose');
                })
                ->whereNull('ar.deleted_at')
                ->whereNull('are.deleted_at')
                ->whereNull('ara.deleted_at')
                ->select(
                    'ara.id as activity_id',
                    'ara.payment_date',
                    'ara.payment_status',
                    'ara.purpose',
                    'ara.utr_number',
                    'ar.year_id',
                    'ar.month',
                    DB::raw('COALESCE(ara.finance_approved_amount, ara.payment_amount, ara.final_amount, ara.amount) as amount')
                )
                ->orderBy('ara.payment_date')
                ->get();

            $paidAdvances = [];
            $totalPaid    = 0;

            foreach ($paidRows as $row) {
                $amt = (float) ($row->amount ?? 0);
                if ($amt > 0) {
                    $runningBalance += $amt;
                    $totalPaid      += $amt;
                    $paidAdvances[] = [
                        'activity_id'    => $row->activity_id,
                        'amount'         => $amt,
                        'payment_date'   => $row->payment_date,
                        'payment_status' => $row->payment_status,
                        'purpose'        => $row->purpose ?? null,
                        'utr_number'     => $row->utr_number ?? null,
                        'year_id'        => $row->year_id,
                        'month'          => $row->month,
                    ];
                }
            }

            // ── 3. Carried advances across ALL years → debit ───────────────────
            $allYearIds = DB::connection($expConn)
                ->table('financialyear')
                ->orderByDesc('YearId')
                ->pluck('YearId')
                ->toArray();

            $carriedAdvances    = [];
            $totalCarried       = 0;

            foreach ($allYearIds as $yearId) {
                $mefTable = 'y'.$yearId.'_monthexpensefinal';
                try {
                    $rows = DB::connection($expConn)
                        ->table($mefTable)
                        ->where('EmployeeID', $employeeId)
                        ->where('payment_status', 'ConfirmedPayment')
                        ->orderByDesc('Month')
                        ->orderByDesc('id')
                        ->get();
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (! empty($row->carried_balance_json)) {
                        $json = json_decode($row->carried_balance_json, true);
                        if (is_string($json)) {
                            $json = json_decode($json, true);
                        }
                        if (is_array($json)) {
                            $allAdvances = array_merge(
                                $json['ledger']  ?? [],
                                $json['paid']    ?? [],
                                $json['expense'] ?? [],
                                $json['carried'] ?? []
                            );
                            foreach ($allAdvances as $adv) {
                                $remaining = (float) ($adv['remaining_amount'] ?? 0);
                                if ($remaining > 0) {
                                    $debit = (float) ($row->total_adjustment ?? 0) ?: $remaining;
                                    $runningBalance -= $debit;
                                    $totalCarried   += $debit;
                                    $carriedAdvances[] = [
                                        'source_month'     => $row->Month ?? null,
                                        'year_id'          => $yearId,
                                        'original_amount'  => (float) ($adv['original_amount'] ?? 0),
                                        'remaining_amount' => $remaining,
                                        'debit_amount'     => $debit,
                                        'payment_date'     => $adv['payment_date'] ?? null,
                                        'category'         => $adv['category'] ?? null,
                                    ];
                                }
                            }
                        }
                    } elseif (! empty($row->total_adjustment) && $row->total_adjustment > 0) {
                        $debit = (float) $row->total_adjustment;
                        $runningBalance -= $debit;
                        $totalCarried   += $debit;
                        $carriedAdvances[] = [
                            'source_month'    => $row->Month ?? null,
                            'year_id'         => $yearId,
                            'original_amount' => 0,
                            'remaining_amount'=> 0,
                            'debit_amount'    => $debit,
                            'payment_date'    => $row->Fin_PayDate ?? null,
                            'category'        => 'adjustment',
                        ];
                    }
                }
            }

            $totalBalance = $totalImprest + $runningBalance;

            return $this->jsonSuccess([
                'employee_id'     => (int) $employeeId,
                // Computed totals — matches the ledger modal "Final Balance"
                'total_imprest'   => $totalImprest,
                'advance_balance' => $runningBalance,
                'total_balance'   => $totalBalance,
                // Breakdown
                'summary' => [
                    'total_paid_advances' => $totalPaid,
                    'total_carried'       => $totalCarried,
                    'total_ledger_advances' => array_sum(array_column($advances, 'amount')),
                ],
                // Detail arrays
                'imprests'         => $imprests,
                'ledger_advances'  => $advances,
                'paid_advances'    => $paidAdvances,
                'carried_advances' => $carriedAdvances,
            ], 'Employee ledger balance retrieved successfully');

        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve employee balance: '.$e->getMessage());
        }
    }

    public function updateFixedImprest(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');
            $fixedImprest = $request->input('fixed_imprest');
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $companyId = $request->input('company_id', 1);
            $remark = $request->input('remark');
            // Validate required parameters
            if (!$employeeId || $fixedImprest === null) {
                return $this->jsonError('employee_id and fixed_imprest are required');
            }
            if ($fixedImprest < 0) {
                return $this->jsonError('fixed_imprest cannot be negative');
            }
            // Update fixed imprest
            $success = $this->updateEmployeeFixedImprest($employeeId, $fixedImprest, $yearId, $companyId, $remark);
            if ($success) {
                // Get updated balance
                $updatedBalance = $this->getEmployeeLedgerBalance($employeeId, $yearId, $companyId);
                return $this->jsonSuccess(['employee_id' => $employeeId, 'updated_balance' => $updatedBalance, 'message' => 'Fixed imprest updated successfully'], 'Fixed imprest updated successfully');
            } else {
                return $this->jsonError('Failed to update fixed imprest');
            }
        } catch (Exception $e) {
            return $this->jsonError('Failed to update fixed imprest: ' . $e->getMessage());
        }
    }

    public function approved(Request $request)
    {
        try {
            // Get year_id from request parameter (default to current year if not provided)
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            // Get requests that have activities approved by finance but NOT yet released for payment
            // This ensures approved list only shows items ready for payment release, not already released
            $requests = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->whereHas('activities', function ($activityQuery) {
                $activityQuery->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where(function ($paymentQuery) {
                    // Include activities that have no payment status set OR are still pending payment
                    $paymentQuery->whereNull('payment_status')->orWhere('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PENDING);
                });
            })->orderBy('updated_at', 'desc')->get();
            // Transform the data to match the expected format - employees with req_data inside (same as AdvanceRequestController)
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get overall approval summary for this request (same as AdvanceRequestController)
                $approvalSummary = [];
                try {
                    $overallApprovals = DB::table('advance_activity_approvals as aaa')->join('advance_request_activities as ara', 'aaa.advance_request_activity_id', '=', 'ara.id')->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')->where('are.advance_request_id', $advanceRequest->id)->where('aaa.submission_no', $advanceRequest->submission_no)->select(['aaa.approval_level', DB::raw('COUNT(aaa.id) as total_approvals'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Approved" THEN 1 END) as approved_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Rejected" THEN 1 END) as rejected_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Pending" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Reverted" THEN 1 END) as reverted_count'), DB::raw('SUM(CASE WHEN aaa.approver_status = "Approved" THEN aaa.approver_amount ELSE 0 END) as total_approved_amount'), DB::raw('MIN(aaa.approver_date) as first_action_date'), DB::raw('MAX(aaa.approver_date) as last_action_date')])->groupBy('aaa.approval_level')->orderBy('aaa.approval_level')->get();
                    foreach ($overallApprovals as $levelSummary) {
                        // Determine overall status for this level
                        $levelStatus = 'Pending';
                        if ($levelSummary->rejected_count > 0) {
                            $levelStatus = 'Rejected';
                        } elseif ($levelSummary->reverted_count > 0) {
                            $levelStatus = 'Reverted';
                        } elseif ($levelSummary->pending_count == 0 && $levelSummary->approved_count > 0) {
                            $levelStatus = 'Approved';
                        }
                        $approvalSummary[] = ['level' => $levelSummary->approval_level, 'total_approvals' => $levelSummary->total_approvals, 'approved_count' => $levelSummary->approved_count, 'rejected_count' => $levelSummary->rejected_count, 'pending_count' => $levelSummary->pending_count, 'reverted_count' => $levelSummary->reverted_count, 'total_approved_amount' => $levelSummary->total_approved_amount ? (float) $levelSummary->total_approved_amount : 0, 'first_action_date' => $levelSummary->first_action_date, 'last_action_date' => $levelSummary->last_action_date, 'status' => $levelStatus, 'completion_percentage' => $levelSummary->total_approvals > 0 ? round((($levelSummary->approved_count + $levelSummary->rejected_count + $levelSummary->reverted_count) / $levelSummary->total_approvals) * 100, 2) : 0];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval summary for request ' . $advanceRequest->id . ': ' . $e->getMessage());
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object (same structure as AdvanceRequestController)
                $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'approval_summary' => $approvalSummary, 'advance_history' => $this->getAdvanceHistory($advanceRequest->id), 'expense_submissions' => $this->getExpenseSubmissions($advanceRequest, $yearId)];
                // Process each employee for this request (same logic as AdvanceRequestController)
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any approved activities that are NOT yet released for payment
                    $hasApprovedActivities = $employee->activities->filter(function ($activity) {
                        return $activity->finance_status === AdvanceRequestActivity::FINANCE_STATUS_APPROVED && (is_null($activity->payment_status) || $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PENDING);
                    })->isNotEmpty();
                    if (!$hasApprovedActivities) {
                        continue;  // Skip employees with no approved activities
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee (same structure as AdvanceRequestController)
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include approved activities that are NOT yet released for payment
                        if ($activity->finance_status !== AdvanceRequestActivity::FINANCE_STATUS_APPROVED || (!is_null($activity->payment_status) && $activity->payment_status !== AdvanceRequestActivity::PAYMENT_STATUS_PENDING)) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                    $cropNames = $crops->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching crop names: ' . $e->getMessage());
                            }
                        }
                        // Get variety names
                        $varietyNames = [];
                        if ($activity->activity_variety_id) {
                            try {
                                $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }
                        // Get approval details for this activity (same as AdvanceRequestController)
                        $approvalDetails = [];
                        try {
                            $approvals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $advanceRequest->submission_no)->orderBy('approval_level')->get();
                            foreach ($approvals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                // Get reverter details if reverted
                                $reverterDetails = null;
                                if ($approval->reverted_by) {
                                    $reverterDetails = $this->getEmployeeDetails($approval->reverted_by);
                                }
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have approved activities (same structure as AdvanceRequestController)
                    if (!empty($activitiesData)) {
                        // Get employee ledger balance
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Finance approved requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve finance approved requests: ' . $e->getMessage());
        }
    }

    private function getAdvanceHistory($requestId)
    {
        try {
            $advanceHistory = [];
            // Get the current advance request to find the employees
            $currentRequest = AdvanceRequest::with(['employees'])->find($requestId);
            if (!$currentRequest) {
                return [];
            }
            // Get advance history for each employee in the current request
            foreach ($currentRequest->employees as $employee) {
                // Get previous advance requests for this employee (excluding current request)
                $previousRequests = AdvanceRequest::with(['activities', 'advanceType'])
                    ->whereHas('employees', function ($query) use ($employee) {
                        $query->where('employee_id', $employee->employee_id);
                    })
                    ->where('id', '!=', $requestId)  // Exclude current request
                    ->where('current_status', '!=', 'Draft')  // Only submitted requests
                    ->orderBy('last_submitted_at', 'desc')
                    ->limit(10)  // Limit to last 10 requests
                    ->get();
                $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                foreach ($previousRequests as $request) {
                    // Get advance type information
                    $advanceTypeName = 'N/A';
                    $advanceTypeCode = 'N/A';
                    if ($request->advance_type_id) {
                        try {
                            $advanceTypeRecord = DB::table('advance_request_types')->where('id', $request->advance_type_id)->where('is_active', 1)->first();
                            if ($advanceTypeRecord) {
                                $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                                $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching advance type: ' . $e->getMessage());
                        }
                    }
                    // Get activity name
                    $activityName = 'N/A';
                    if ($request->activity_name_id) {
                        try {
                            $activityNameRecord = DB::table('claimtype')->where('ClaimId', $request->activity_name_id)->where('ClaimStatus', 'A')->first();
                            $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching activity name: ' . $e->getMessage());
                        }
                    }
                    // Get total amount for this employee's activities in this request
                    $employeeActivities = $request->activities()->whereHas('employee', function ($query) use ($employee) {
                        $query->where('employee_id', $employee->employee_id);
                    })->get();
                    $totalAmount = $employeeActivities->sum('amount');
                    $approvedAmount = $employeeActivities->where('approval_status', 'Approved')->sum('amount');
                    $financeApprovedAmount = $employeeActivities->whereNotNull('finance_approved_amount')->sum('finance_approved_amount');
                    // Determine status description
                    $statusDescription = $this->getStatusDescription($request->current_status, $employeeActivities);
                    $advanceHistory[] = ['request_id' => $request->id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'advance_type_id' => $request->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $request->activity_name_id, 'activity_name' => $activityName, 'month' => $request->month, 'submission_no' => $request->submission_no, 'submitted_at' => $request->last_submitted_at, 'current_status' => $request->current_status, 'status_description' => $statusDescription, 'total_amount' => (float) $totalAmount, 'approved_amount' => (float) $approvedAmount, 'finance_approved_amount' => (float) $financeApprovedAmount, 'activities_count' => $employeeActivities->count(), 'created_at' => $request->created_at, 'updated_at' => $request->updated_at, 'reverted_from_status' => $request->reverted_from_status, 'revert_reason' => $request->revert_reason, 'reverted_at' => $request->reverted_at];
                }
            }
            // Sort by submitted date (most recent first)
            usort($advanceHistory, function ($a, $b) {
                return $b['submitted_at'] <=> $a['submitted_at'];
            });
            return $advanceHistory;
        } catch (\Exception $e) {
            \Log::warning('Error fetching advance history for request ' . $requestId . ': ' . $e->getMessage());
            return [];
        }
    }

    private function getStatusDescription($status, $activities)
    {
        switch ($status) {
            case 'Draft':
                return 'Request saved as draft';
            case 'Submitted':
                return 'Request submitted for approval';
            case 'In_Approval':
                $pendingCount = $activities->where('approval_status', 'Pending')->count();
                $approvedCount = $activities->where('approval_status', 'Approved')->count();
                return "In approval process - {$approvedCount} approved, {$pendingCount} pending";
            case 'Approved':
                return 'All activities approved, ready for finance';
            case 'Finance':
                return 'Under finance review';
            case 'Finance_Approved':
                return 'Finance approved';
            case 'Finance_Rejected':
                return 'Finance rejected';
            case 'Rejected':
                return 'Request rejected';
            case 'Reverted':
                return 'Request reverted for corrections';
            case 'Closed':
                return 'Request completed and closed';
            default:
                return 'Status: ' . $status;
        }
    }

    private function getExpenseSubmissions($advanceRequest, $yearId = null)
    {
        try {
            $expenseSubmissions = [];
            // Get all monthly expense final records for each employee (not limited to advance request month/year)
            foreach ($advanceRequest->employees as $employee) {
                $allMonthlyExpenses = $this->getAllMonthlyExpenseRecords($employee->employee_id, $yearId);
                if (!empty($allMonthlyExpenses)) {
                    $expenseSubmissions[] = ['employee_id' => $employee->employee_id, 'employee_name' => $this->getEmployeeDetails($employee->employee_id)['EmployeeName'] ?? 'Unknown', 'monthly_expenses' => $allMonthlyExpenses];
                }
            }
            return $expenseSubmissions;
        } catch (\Exception $e) {
            \Log::warning('Error fetching expense submissions for request ' . $advanceRequest->id . ': ' . $e->getMessage());
            return [];
        }
    }

    private function getAllMonthlyExpenseRecords($employeeId, $yearId)
    {
        try {
            $allExpenses = [];
            // Use dynamic table names based on provided year_id
            $monthExpenseTable = 'y' . $yearId . '_monthexpensefinal';
            $expenseClaimsTable = 'y' . $yearId . '_expenseclaims';
            // Check if both tables exist
            $monthTableExists = DB::select("SHOW TABLES LIKE '{$monthExpenseTable}'");
            $claimsTableExists = DB::select("SHOW TABLES LIKE '{$expenseClaimsTable}'");
            if (empty($monthTableExists)) {
                \Log::warning("Table {$monthExpenseTable} does not exist");
                return [];
            }
            // Get monthly expense records for this employee from the year-specific table
            $expenses = DB::table($monthExpenseTable)
                ->where('EmployeeID', $employeeId)
                //  ->orderBy('Month', 'asc')
                ->orderBy('DateOfSubmit', 'desc')
                ->get();
            foreach ($expenses as $expense) {
                // Calculate monthly DA amount for this employee and month using year-specific table
                $monthlyDAAmount = 0;
                if (!empty($claimsTableExists)) {
                    try {
                        $daResult = DB::selectOne("
                            SELECT SUM(FinancedTAmt) as DA_Amount
                            FROM `{$expenseClaimsTable}`
                            WHERE CrBy = ?
                            AND ClaimMonth = ?
                            AND ClaimStatus != 'Deactivate'
                            AND FilledOkay = 1
                            AND FinancedDate != '0000-00-00'
                            AND (ClaimId = 19 OR ClaimId = 20)
                        ", [$employeeId, $expense->Month ?? 0]);
                        $monthlyDAAmount = $daResult->DA_Amount ? (float) $daResult->DA_Amount : 0;
                    } catch (\Exception $e) {
                        \Log::warning('Error calculating monthly DA amount for employee ' . $employeeId . ' month ' . ($expense->Month ?? 0) . ' year ' . $yearId . ': ' . $e->getMessage());
                    }
                }
                $allExpenses[] = ['id' => $expense->id ?? null, 'doc_id' => $expense->DocId ?? null, 'doc_no' => $expense->DocNo ?? null, 'employee_id' => $expense->EmployeeID, 'month' => $expense->Month ?? null, 'year_id' => $expense->YearId ?? $yearId, 'allow_submit' => $expense->AllowSubmit ?? null, 'status' => $expense->Status ?? 'Open', 'sub_permission' => $expense->SubPermission ?? null, 'odo_approve_final_submit' => $expense->Odo_Approve_Final_Submit ?? null, 'created_date' => $expense->Crdate ?? null, 'date_of_submit' => $expense->DateOfSubmit ?? null, 'km_confirmation' => $expense->Km_Confirmation ?? null, 'total_claim' => $expense->Total_Claim ? (float) $expense->Total_Claim : 0, 'claim_amount' => $expense->Claim_Amount ? (float) $expense->Claim_Amount : 0, 'verified_amount' => $expense->Verified_Amount ? (float) $expense->Verified_Amount : 0, 'verified_date' => $expense->Verified_Date ?? null, 'approved_amount' => $expense->Approved_Amount ? (float) $expense->Approved_Amount : 0, 'approved_date' => $expense->Approved_Date ?? null, 'finance_amount' => $expense->Finance_Amount ? (float) $expense->Finance_Amount : 0, 'finance_date' => $expense->Finance_Date ?? null, 'move_focus_sheet' => $expense->Move_FocuseSheet ?? null, 'fin_app_by' => $expense->Fin_AppBy ?? null, 'fin_app_date' => $expense->Fin_AppDate ?? null, 'fin_advance_pay' => $expense->Fin_AdvancePay ? (float) $expense->Fin_AdvancePay : 0, 'fin_pay_option' => $expense->Fin_PayOption ?? null, 'fin_pay_by' => $expense->Fin_PayBy ?? null, 'fin_pay_amount' => $expense->Fin_PayAmt ? (float) $expense->Fin_PayAmt : 0, 'fin_pay_date' => $expense->Fin_PayDate ?? null, 'fin_pay_remark' => $expense->Fin_PayRemark ?? null, 'fin_pay_remark_1' => $expense->Fin_PayRemark_1 ?? null, 'fin_pay_remark_2' => $expense->Fin_PayRemark_2 ?? null, 'post_date' => $expense->PostDate ?? null, 'docate_no' => $expense->DocateNo ?? null, 'agency' => $expense->Agency ?? null, 'cu_image' => $expense->CuImag ?? null, 'receiving_date' => $expense->RecevingDate ?? null, 'verif_date' => $expense->VerifDate ?? null, 'cn_issue_date' => $expense->CNIssueDate ?? null, 'doc_remark' => $expense->DocRmk ?? null, 'monthly_da_amount' => $monthlyDAAmount, 'table_year' => $yearId
                // Add year info for reference
                ];
            }
            return $allExpenses;
        } catch (\Exception $e) {
            \Log::warning('Error fetching all monthly expense records for employee ' . $employeeId . ' year ' . $yearId . ': ' . $e->getMessage());
            return [];
        }
    }

    private function getMonthlyExpenseRecords($employeeId, $month, $year)
    {
        try {
            // Use MonthExpenseFinal table instead of ExpenseClaim
            // Determine the table name based on year
            $monthExpenseTable = 'y' . $year . '_monthexpensefinal';
            $expenseClaimsTable = 'y' . $year . '_expenseclaims';
            // Check if tables exist first
            $monthTableExists = DB::select("SHOW TABLES LIKE '{$monthExpenseTable}'");
            $claimsTableExists = DB::select("SHOW TABLES LIKE '{$expenseClaimsTable}'");
            if (empty($monthTableExists)) {
                return [];
            }
            // Get monthly expense final records for this employee in the specified month
            $monthExpenses = DB::table($monthExpenseTable)->where('EmployeeID', $employeeId)->where('Month', $month)->where('YearId', $year)->orderBy('DateOfSubmit', 'desc')->get();
            $formattedExpenses = [];
            foreach ($monthExpenses as $expense) {
                // Calculate monthly DA amount for this employee and month using year-specific table
                $monthlyDAAmount = 0;
                if (!empty($claimsTableExists)) {
                    try {
                        $daResult = DB::selectOne("
                            SELECT SUM(FinancedTAmt) as DA_Amount
                            FROM `{$expenseClaimsTable}`
                            WHERE CrBy = ?
                            AND ClaimMonth = ?
                            AND ClaimStatus != 'Deactivate'
                            AND FilledOkay = 1
                            AND FinancedDate != '0000-00-00'
                            AND (ClaimId = 19 OR ClaimId = 20)
                        ", [$employeeId, $month]);
                        $monthlyDAAmount = $daResult->DA_Amount ? (float) $daResult->DA_Amount : 0;
                    } catch (\Exception $e) {
                        \Log::warning('Error calculating monthly DA amount for employee ' . $employeeId . ' month ' . $month . ' year ' . $year . ': ' . $e->getMessage());
                    }
                }
                $formattedExpenses[] = ['id' => $expense->id, 'doc_id' => $expense->DocId, 'doc_no' => $expense->DocNo, 'employee_id' => $expense->EmployeeID, 'month' => $expense->Month, 'year_id' => $expense->YearId, 'allow_submit' => $expense->AllowSubmit, 'status' => $expense->Status, 'sub_permission' => $expense->SubPermission, 'odo_approve_final_submit' => $expense->Odo_Approve_Final_Submit, 'created_date' => $expense->Crdate, 'date_of_submit' => $expense->DateOfSubmit, 'km_confirmation' => $expense->Km_Confirmation, 'total_claim' => $expense->Total_Claim ? (float) $expense->Total_Claim : 0, 'claim_amount' => $expense->Claim_Amount ? (float) $expense->Claim_Amount : 0, 'verified_amount' => $expense->Verified_Amount ? (float) $expense->Verified_Amount : 0, 'verified_date' => $expense->Verified_Date, 'approved_amount' => $expense->Approved_Amount ? (float) $expense->Approved_Amount : 0, 'approved_date' => $expense->Approved_Date, 'finance_amount' => $expense->Finance_Amount ? (float) $expense->Finance_Amount : 0, 'finance_date' => $expense->Finance_Date, 'move_focus_sheet' => $expense->Move_FocuseSheet, 'fin_app_by' => $expense->Fin_AppBy, 'fin_app_date' => $expense->Fin_AppDate, 'fin_advance_pay' => $expense->Fin_AdvancePay ? (float) $expense->Fin_AdvancePay : 0, 'fin_pay_option' => $expense->Fin_PayOption, 'fin_pay_by' => $expense->Fin_PayBy, 'fin_pay_amount' => $expense->Fin_PayAmt ? (float) $expense->Fin_PayAmt : 0, 'fin_pay_date' => $expense->Fin_PayDate, 'fin_pay_remark' => $expense->Fin_PayRemark, 'fin_pay_remark_1' => $expense->Fin_PayRemark_1, 'fin_pay_remark_2' => $expense->Fin_PayRemark_2, 'post_date' => $expense->PostDate, 'docate_no' => $expense->DocateNo, 'agency' => $expense->Agency, 'cu_image' => $expense->CuImag, 'receiving_date' => $expense->RecevingDate, 'verif_date' => $expense->VerifDate, 'cn_issue_date' => $expense->CNIssueDate, 'doc_remark' => $expense->DocRmk, 'monthly_da_amount' => $monthlyDAAmount, 'table_year' => $year
                // Add year info for reference
                ];
            }
            return $formattedExpenses;
        } catch (\Exception $e) {
            \Log::warning('Error fetching monthly expense final records for employee ' . $employeeId . ': ' . $e->getMessage());
            return [];
        }
    }

    public function releasePayment(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $selectedRequests = $request->input('selected_requests', []);
            // Validate required parameters
            if (!$userId) {
                return $this->jsonError('user_id is required');
            }
            if (empty($selectedRequests) || !is_array($selectedRequests)) {
                return $this->jsonError('selected_requests is required and must be a non-empty array');
            }
            // Validate each selected request
            foreach ($selectedRequests as $index => $selectedRequest) {
                if (!isset($selectedRequest['id']) || !isset($selectedRequest['employee_id']) || !isset($selectedRequest['advance_request_id']) || !isset($selectedRequest['finance_approved_amount'])) {
                    return $this->jsonError("Invalid request structure at index {$index}. Required fields: id, employee_id, advance_request_id, finance_approved_amount");
                }
                if ($selectedRequest['finance_approved_amount'] <= 0) {
                    return $this->jsonError("finance_approved_amount must be positive at index {$index}");
                }
            }
            // Process release in transaction
            $result = DB::transaction(function () use ($selectedRequests, $userId) {
                $releasedRequests = [];
                $totalAmount = 0;
                $releasedCount = 0;
                // Generate unique payment batch ID
                $paymentBatchId = \App\Services\Payments\PaymentBatches::nextId(); // LV-C06: sequence, no random clash
                // Ensure payment_batches table exists or create it
                $this->ensurePaymentTablesExist();
                foreach ($selectedRequests as $selectedRequest) {
                    $activityId = $selectedRequest['id'];
                    $employeeId = $selectedRequest['employee_id'];
                    $advanceRequestId = $selectedRequest['advance_request_id'];
                    $financeApprovedAmount = $selectedRequest['finance_approved_amount'];
                    // The frontend sends advance_request_employee_id as 'id', so look up by that first
                    $activity = AdvanceRequestActivity::with(['employee.advanceRequest'])
                        ->where('advance_request_employee_id', $activityId)
                        ->first();
                    // Fallback: try by primary key
                    if (!$activity) {
                        $activity = AdvanceRequestActivity::with(['employee.advanceRequest'])
                            ->where('id', $activityId)
                            ->first();
                    }
                    if (!$activity) {
                        throw new Exception("Activity with ID {$activityId} not found");
                    }
                    if ($activity->finance_status !== AdvanceRequestActivity::FINANCE_STATUS_APPROVED) {
                        throw new Exception("Activity {$activity->id} is not approved by finance. Current status: {$activity->finance_status}");
                    }
                    if ($activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_READY || $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PAID) {
                        throw new Exception("Activity {$activity->id} is already released for payment or paid");
                    }
                    if ($activity->employee->employee_id != $employeeId) {
                        throw new Exception("Employee ID mismatch for activity {$activity->id}");
                    }
                    if ($activity->employee->advance_request_id != $advanceRequestId) {
                        throw new Exception("Advance request ID mismatch for activity {$activity->id}");
                    }
                    // LV-C05b: the Finance user who approved the advance may not release it
                    if (\App\Support\MakerChecker::blocks($activity->finance_id, $userId, 'advance_release', ['activity' => $activity->id])) {
                        throw new Exception("Advance {$activity->id} was finance-approved by you; another Finance user must release it.");
                    }
                    $pclBefore = \App\Support\PaymentChangeLog::snapshot($activity->getConnectionName() ?: config('database.default'), $activity->getTable(), [$activity->id]);
                    // Get employee details
                    $employeeDetails = $this->getEmployeeDetails($employeeId);
                    $employeeName = $employeeDetails['EmployeeName'] ?? 'Unknown';
                    $employeeCode = $employeeDetails['EmployeeCode'] ?? $employeeId;
                    // Get activity name
                    $activityName = 'N/A';
                    $advanceRequest = $activity->employee->advanceRequest;
                    if ($advanceRequest && $advanceRequest->activity_name_id) {
                        try {
                            $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                            $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching activity name: ' . $e->getMessage());
                        }
                    }
                    // Update activity for payment release
                    // ADV-AMT-02: the release pays the Finance-approved amount on record, not a figure sent by the screen
                    if (\App\Support\AdvanceAmount::differs($financeApprovedAmount, $activity->finance_approved_amount)) {
                        \Log::warning('ADV-AMT-02: release amount from the screen ignored', ['activity' => $activity->id, 'sent' => $financeApprovedAmount, 'approved' => (float) $activity->finance_approved_amount]);
                    }
                    $financeApprovedAmount = (float) $activity->finance_approved_amount;
                    $activity->update(['payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_READY, 'payment_amount' => $financeApprovedAmount, 'payment_released_date' => now(), 'payment_released_by' => $userId, 'payment_batch_id' => $paymentBatchId]);
                    \App\Support\PaymentChangeLog::diff($activity->getConnectionName() ?: config('database.default'), $activity->getTable(), $pclBefore, 'advance', 'advance_release', "Batch {$paymentBatchId}"); // PAY-LOG-01
                    // Update advance request if all activities are released
                    $this->checkAndUpdateRequestPaymentStatus($advanceRequest);
                    // Log the release action
                    $this->logAction($advanceRequest, AdvanceRequestAction::ACTION_PAYMENT_RELEASE, $userId, null, $activity->id, null, "Released for payment - Batch: {$paymentBatchId}, Amount: {$financeApprovedAmount}");
                    // Create payment log entry
                    $this->createPaymentLog($activityId, $userId, 'RELEASE', $financeApprovedAmount, $paymentBatchId);
                    $releasedRequests[] = ['id' => $activityId, 'employee_name' => $employeeName, 'employee_code' => $employeeCode, 'activity_name' => $activityName, 'amount' => (float) $financeApprovedAmount, 'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_READY, 'payment_released_date' => now()->toISOString()];
                    $totalAmount += $financeApprovedAmount;
                    $releasedCount++;
                }
                // Create payment batch record
                $this->createPaymentBatch($paymentBatchId, $userId, $releasedCount, $totalAmount);
                return ['released_count' => $releasedCount, 'total_amount' => $totalAmount, 'payment_batch_id' => $paymentBatchId, 'released_requests' => $releasedRequests];
            });
            return $this->jsonSuccess($result, "{$result['released_count']} request(s) released for payment successfully");
        } catch (Exception $e) {
            return $this->jsonError('Failed to release payments: ' . $e->getMessage());
        }
    }

    private function ensurePaymentTablesExist()
    {
        try {
            // Check if payment_batches table exists
            if (!Schema::hasTable('payment_batches')) {
                throw new Exception('payment_batches table does not exist. Please run migrations.');
            }
            // Check if payment_logs table exists
            if (!Schema::hasTable('payment_logs')) {
                throw new Exception('payment_logs table does not exist. Please run migrations.');
            }
        } catch (\Exception $e) {
            \Log::error('Error checking payment tables: ' . $e->getMessage());
            throw new Exception('Payment tables are not properly configured: ' . $e->getMessage());
        }
    }

    private function createPaymentBatch($batchId, $userId, $totalRequests, $totalAmount)
    {
        try {
            DB::table('payment_batches')->insert(['batch_id' => $batchId, 'created_by' => $userId, 'total_requests' => $totalRequests, 'total_amount' => $totalAmount, 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
            \Log::info("Created payment batch {$batchId} with {$totalRequests} requests totaling {$totalAmount}");
        } catch (\Exception $e) {
            \Log::error('Error creating payment batch: ' . $e->getMessage());
            throw new Exception('Failed to create payment batch record');
        }
    }

    private function createPaymentLog($activityId, $userId, $action, $amount, $batchId = null, $remarks = null)
    {
        try {
            DB::table('payment_logs')->insert(['activity_id' => $activityId, 'user_id' => $userId, 'action' => $action, 'amount' => $amount, 'batch_id' => $batchId, 'remarks' => $remarks, 'created_at' => now()]);
            \Log::info("Created payment log for activity {$activityId}: {$action} - {$amount}");
        } catch (\Exception $e) {
            \Log::error('Error creating payment log: ' . $e->getMessage());
            // Don't throw exception here as it's just logging
        }
    }

    public function releasedPayments(Request $request)
    {
        try {
            // Get parameters from request
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $batchId = $request->input('batch_id');
            $employeeId = $request->input('employee_id');
            $status = $request->input('status', AdvanceRequestActivity::PAYMENT_STATUS_READY);  // Default to Ready for Payment
            $limit = $request->input('limit', 50);  // Default limit
            $page = $request->input('page', 1);  // Default page
            // Build query for requests that have activities released for payment
            $query = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->whereHas('activities', function ($activityQuery) use ($status, $batchId) {
                $activityQuery->where('payment_status', $status);
                if ($batchId) {
                    $activityQuery->where('payment_batch_id', $batchId);
                }
            });
            // Filter by employee if specified
            if ($employeeId) {
                $query->whereHas('employees', function ($employeeQuery) use ($employeeId) {
                    $employeeQuery->where('employee_id', $employeeId);
                });
            }
            $requests = $query->orderBy('updated_at', 'desc')->limit($limit)->offset(($page - 1) * $limit)->get();
            // Get total count for pagination
            $totalCount = $query->count();
            // Transform the data to match the expected format - employees with req_data inside
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get overall approval summary for this request
                $approvalSummary = [];
                try {
                    $overallApprovals = DB::table('advance_activity_approvals as aaa')->join('advance_request_activities as ara', 'aaa.advance_request_activity_id', '=', 'ara.id')->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')->where('are.advance_request_id', $advanceRequest->id)->where('aaa.submission_no', $advanceRequest->submission_no)->select(['aaa.approval_level', DB::raw('COUNT(aaa.id) as total_approvals'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Approved" THEN 1 END) as approved_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Rejected" THEN 1 END) as rejected_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Pending" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Reverted" THEN 1 END) as reverted_count'), DB::raw('SUM(CASE WHEN aaa.approver_status = "Approved" THEN aaa.approver_amount ELSE 0 END) as total_approved_amount'), DB::raw('MIN(aaa.approver_date) as first_action_date'), DB::raw('MAX(aaa.approver_date) as last_action_date')])->groupBy('aaa.approval_level')->orderBy('aaa.approval_level')->get();
                    foreach ($overallApprovals as $levelSummary) {
                        // Determine overall status for this level
                        $levelStatus = 'Pending';
                        if ($levelSummary->rejected_count > 0) {
                            $levelStatus = 'Rejected';
                        } elseif ($levelSummary->reverted_count > 0) {
                            $levelStatus = 'Reverted';
                        } elseif ($levelSummary->pending_count == 0 && $levelSummary->approved_count > 0) {
                            $levelStatus = 'Approved';
                        }
                        $approvalSummary[] = ['level' => $levelSummary->approval_level, 'total_approvals' => $levelSummary->total_approvals, 'approved_count' => $levelSummary->approved_count, 'rejected_count' => $levelSummary->rejected_count, 'pending_count' => $levelSummary->pending_count, 'reverted_count' => $levelSummary->reverted_count, 'total_approved_amount' => $levelSummary->total_approved_amount ? (float) $levelSummary->total_approved_amount : 0, 'first_action_date' => $levelSummary->first_action_date, 'last_action_date' => $levelSummary->last_action_date, 'status' => $levelStatus, 'completion_percentage' => $levelSummary->total_approvals > 0 ? round((($levelSummary->approved_count + $levelSummary->rejected_count + $levelSummary->reverted_count) / $levelSummary->total_approvals) * 100, 2) : 0];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval summary for request ' . $advanceRequest->id . ': ' . $e->getMessage());
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object
                $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approval_summary' => $approvalSummary, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'advance_history' => $this->getAdvanceHistory($advanceRequest->id), 'expense_submissions' => $this->getExpenseSubmissions($advanceRequest, $yearId)];
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any released payment activities
                    $hasReleasedActivities = $employee->activities->filter(function ($activity) use ($status, $batchId) {
                        $matchesStatus = $activity->payment_status === $status;
                        $matchesBatch = !$batchId || $activity->payment_batch_id === $batchId;
                        return $matchesStatus && $matchesBatch;
                    })->isNotEmpty();
                    if (!$hasReleasedActivities) {
                        continue;  // Skip employees with no released activities
                    }
                    // Filter by specific employee if requested
                    if ($employeeId && $employee->employee_id != $employeeId) {
                        continue;
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include activities with matching payment status and batch
                        $matchesStatus = $activity->payment_status === $status;
                        $matchesBatch = !$batchId || $activity->payment_batch_id === $batchId;
                        if (!$matchesStatus || !$matchesBatch) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                    $cropNames = $crops->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching crop names: ' . $e->getMessage());
                            }
                        }
                        // Get variety names
                        $varietyNames = [];
                        if ($activity->activity_variety_id) {
                            try {
                                $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }
                        // Get approval details for this activity
                        $approvalDetails = [];
                        try {
                            $approvals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $advanceRequest->submission_no)->orderBy('approval_level')->get();
                            foreach ($approvals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                // Get reverter details if reverted
                                $reverterDetails = null;
                                if ($approval->reverted_by) {
                                    $reverterDetails = $this->getEmployeeDetails($approval->reverted_by);
                                }
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        // Get payment release details
                        $paymentReleaseDetails = null;
                        if ($activity->payment_released_by) {
                            $releaseByDetails = $this->getEmployeeDetails($activity->payment_released_by);
                            $paymentReleaseDetails = ['released_by_id' => $activity->payment_released_by, 'released_by_name' => $releaseByDetails['EmployeeName'] ?? 'Unknown', 'released_date' => $activity->payment_released_date, 'batch_id' => $activity->payment_batch_id];
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_release_details' => $paymentReleaseDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have released activities
                    if (!empty($activitiesData)) {
                        // Get employee ledger balance
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            // Get payment batch summary if batch_id is provided
            $batchSummary = null;
            if ($batchId) {
                $batchSummary = $this->getPaymentBatchSummary($batchId);
            }
            // Prepare response with pagination info
            $response = ['employees' => $allEmployees, 'pagination' => ['current_page' => (int) $page, 'per_page' => (int) $limit, 'total' => $totalCount, 'total_pages' => ceil($totalCount / $limit)], 'filters' => ['year_id' => $yearId, 'batch_id' => $batchId, 'employee_id' => $employeeId, 'status' => $status]];
            if ($batchSummary) {
                $response['batch_summary'] = $batchSummary;
            }
            return $this->jsonSuccess($response, 'Released payments retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve released payments: ' . $e->getMessage());
        }
    }

    private function getPaymentBatchSummary($batchId)
    {
        try {
            // Get batch details
            $batch = DB::table('payment_batches')->where('batch_id', $batchId)->first();
            if (!$batch) {
                return null;
            }
            // Get actual activities in this batch
            $activities = DB::table('advance_request_activities')->where('payment_batch_id', $batchId)->get();
            $actualCount = $activities->count();
            $actualAmount = $activities->sum('payment_amount');
            // Get batch creator details
            $creatorDetails = $this->getEmployeeDetails($batch->created_by);
            // Get processor details if processed
            $processorDetails = null;
            if ($batch->processed_by) {
                $processorDetails = $this->getEmployeeDetails($batch->processed_by);
            }
            return ['batch_id' => $batch->batch_id, 'status' => $batch->status, 'created_by' => $batch->created_by, 'created_by_name' => $creatorDetails['EmployeeName'] ?? 'Unknown', 'created_at' => $batch->created_at, 'processed_by' => $batch->processed_by, 'processed_by_name' => $processorDetails['EmployeeName'] ?? null, 'processed_at' => $batch->processed_at, 'total_requests' => $batch->total_requests, 'total_amount' => (float) $batch->total_amount, 'actual_requests' => $actualCount, 'actual_amount' => (float) $actualAmount, 'remarks' => $batch->remarks];
        } catch (\Exception $e) {
            \Log::error('Error getting payment batch summary: ' . $e->getMessage());
            return null;
        }
    }

    public function paymentBatches(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);
            $status = $request->input('status');  // Optional status filter
            // Build query for payment batches
            $query = DB::table('payment_batches');
            if ($status) {
                $query->where('status', $status);
            }
            $totalCount = $query->count();
            $batches = $query->orderBy('created_at', 'desc')->limit($limit)->offset(($page - 1) * $limit)->get();
            $batchesData = [];
            foreach ($batches as $batch) {
                // Get creator details
                $creatorDetails = $this->getEmployeeDetails($batch->created_by);
                // Get processor details if processed
                $processorDetails = null;
                if ($batch->processed_by) {
                    $processorDetails = $this->getEmployeeDetails($batch->processed_by);
                }
                // Get actual activities count and amount
                $activities = DB::table('advance_request_activities')->where('payment_batch_id', $batch->batch_id)->get();
                $actualCount = $activities->count();
                $actualAmount = $activities->sum('payment_amount');
                $batchesData[] = ['id' => $batch->id, 'batch_id' => $batch->batch_id, 'status' => $batch->status, 'created_by' => $batch->created_by, 'created_by_name' => $creatorDetails['EmployeeName'] ?? 'Unknown', 'created_at' => $batch->created_at, 'processed_by' => $batch->processed_by, 'processed_by_name' => $processorDetails['EmployeeName'] ?? null, 'processed_at' => $batch->processed_at, 'total_requests' => $batch->total_requests, 'total_amount' => (float) $batch->total_amount, 'actual_requests' => $actualCount, 'actual_amount' => (float) $actualAmount, 'remarks' => $batch->remarks, 'updated_at' => $batch->updated_at];
            }
            $response = ['batches' => $batchesData, 'pagination' => ['current_page' => (int) $page, 'per_page' => (int) $limit, 'total' => $totalCount, 'total_pages' => ceil($totalCount / $limit)], 'filters' => ['status' => $status]];
            return $this->jsonSuccess($response, 'Payment batches retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve payment batches: ' . $e->getMessage());
        }
    }

    public function markAsExported(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $activityIds = $request->input('activity_ids', []);
            $focusBatchId = $request->input('focus_batch_id');
            $remarks = $request->input('remarks');
            // Validate required parameters
            if (empty($activityIds)) {
                return $this->jsonError('activity_ids are required');
            }
            if (empty($focusBatchId)) {
                return $this->jsonError('focus_batch_id is required');
            }
            return DB::transaction(function () use ($activityIds, $focusBatchId, $remarks, $userId) {
                // Get activities that are ready for Focus export (payment status = 'Paid')
                $activities = AdvanceRequestActivity::whereIn('id', $activityIds)->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->where('final_status', AdvanceRequestActivity::FINAL_STATUS_PENDING)->get();
                if ($activities->isEmpty()) {
                    throw new Exception('No eligible activities found for Focus export. Activities must be paid and not already exported.');
                }
                $updatedCount = 0;
                foreach ($activities as $activity) {
                    $activity->update(['final_status' => AdvanceRequestActivity::FINAL_STATUS_EXPORTED, 'focus_batch_id' => $focusBatchId, 'focus_exported_at' => now(), 'final_remarks' => $remarks]);
                    // Log the export action
                    $this->logAction($activity->advanceRequest, 'Focus_Export', $userId, null, $activity->id, null, "Exported to Focus - Batch: {$focusBatchId}" . ($remarks ? " - {$remarks}" : ''));
                    $updatedCount++;
                }
                return $this->jsonSuccess(['exported_count' => $updatedCount, 'focus_batch_id' => $focusBatchId, 'exported_at' => now(), 'activities' => $activities->map(function ($activity) {
                    return ['id' => $activity->id, 'employee_id' => $activity->employee->employee_id, 'amount' => $activity->payment_amount, 'final_status' => $activity->final_status];
                })], "{$updatedCount} activities marked as exported to Focus successfully");
            });
        } catch (Exception $e) {
            return $this->jsonError('Failed to mark activities as exported: ' . $e->getMessage());
        }
    }

    public function markAsImported(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $focusBatchId = $request->input('focus_batch_id');
            $remarks = $request->input('remarks');
            // Validate required parameters
            if (empty($focusBatchId)) {
                return $this->jsonError('focus_batch_id is required');
            }
            return DB::transaction(function () use ($focusBatchId, $remarks, $userId) {
                // Get activities that were exported with this batch ID
                $activities = AdvanceRequestActivity::where('focus_batch_id', $focusBatchId)->where('final_status', AdvanceRequestActivity::FINAL_STATUS_EXPORTED)->get();
                if ($activities->isEmpty()) {
                    throw new Exception("No exported activities found for Focus batch ID: {$focusBatchId}");
                }
                $updatedCount = 0;
                foreach ($activities as $activity) {
                    $activity->update(['final_status' => AdvanceRequestActivity::FINAL_STATUS_IMPORTED, 'focus_imported_at' => now(), 'final_remarks' => $remarks]);
                    // Log the import action
                    $this->logAction($activity->advanceRequest, 'Focus_Import', $userId, null, $activity->id, null, "Imported from Focus - Batch: {$focusBatchId}" . ($remarks ? " - {$remarks}" : ''));
                    $updatedCount++;
                }
                return $this->jsonSuccess(['imported_count' => $updatedCount, 'focus_batch_id' => $focusBatchId, 'imported_at' => now(), 'activities' => $activities->map(function ($activity) {
                    return ['id' => $activity->id, 'employee_id' => $activity->employee->employee_id, 'amount' => $activity->payment_amount, 'final_status' => $activity->final_status];
                })], "{$updatedCount} activities marked as imported from Focus successfully");
            });
        } catch (Exception $e) {
            return $this->jsonError('Failed to mark activities as imported: ' . $e->getMessage());
        }
    }

    public function getActivitiesForFocusExport(Request $request)
    {
        try {
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            // Get activities that are paid but not yet exported to Focus
            $activities = AdvanceRequestActivity::with(['employee.advanceRequest', 'employee'])->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->where('final_status', AdvanceRequestActivity::FINAL_STATUS_PENDING)->whereHas('employee.advanceRequest', function ($query) use ($yearId) {
                if ($yearId) {
                    // Add year filtering if needed based on your year logic
                }
            })->orderBy('payment_date', 'asc')->get();
            $activitiesData = [];
            foreach ($activities as $activity) {
                $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                $advanceRequest = $activity->employee->advanceRequest;
                $activitiesData[] = ['id' => $activity->id, 'advance_request_id' => $advanceRequest->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id, 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'finance_approved_amount' => $activity->finance_approved_amount, 'payment_amount' => $activity->payment_amount, 'payment_date' => $activity->payment_date, 'payment_batch_id' => $activity->payment_batch_id, 'final_status' => $activity->final_status, 'submission_no' => $advanceRequest->submission_no, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at];
            }
            return $this->jsonSuccess(['activities' => $activitiesData, 'total_count' => count($activitiesData), 'total_amount' => $activities->sum('payment_amount')], 'Activities ready for Focus export retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve activities for Focus export: ' . $e->getMessage());
        }
    }

    public function getActivitiesForFinalization(Request $request)
    {
        try {
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $focusBatchId = $request->input('focus_batch_id');
            // Build query for imported activities
            $query = AdvanceRequestActivity::with(['employee.advanceRequest', 'employee'])->where('final_status', AdvanceRequestActivity::FINAL_STATUS_IMPORTED);
            if ($focusBatchId) {
                $query->where('focus_batch_id', $focusBatchId);
            }
            if ($yearId) {
                $query->whereHas('employee.advanceRequest', function ($subQuery) use ($yearId) {
                    // Add year filtering if needed based on your year logic
                });
            }
            $activities = $query->orderBy('focus_imported_at', 'asc')->get();
            $activitiesData = [];
            foreach ($activities as $activity) {
                $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                $advanceRequest = $activity->employee->advanceRequest;
                $activitiesData[] = ['id' => $activity->id, 'advance_request_id' => $advanceRequest->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id, 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'finance_approved_amount' => $activity->finance_approved_amount, 'payment_amount' => $activity->payment_amount, 'final_amount' => $activity->final_amount, 'final_status' => $activity->final_status, 'focus_batch_id' => $activity->focus_batch_id, 'focus_exported_at' => $activity->focus_exported_at, 'focus_imported_at' => $activity->focus_imported_at, 'submission_no' => $advanceRequest->submission_no, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at];
            }
            return $this->jsonSuccess(['activities' => $activitiesData, 'total_count' => count($activitiesData), 'total_payment_amount' => $activities->sum('payment_amount'), 'focus_batches' => $activities->pluck('focus_batch_id')->unique()->values()], 'Activities ready for finalization retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve activities for finalization: ' . $e->getMessage());
        }
    }

    public function getFinalizedActivities(Request $request)
    {
        try {
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $focusBatchId = $request->input('focus_batch_id');
            // Build query for finalized activities
            $query = AdvanceRequestActivity::with(['employee.advanceRequest', 'employee'])->where('final_status', AdvanceRequestActivity::FINAL_STATUS_FINALIZED);
            if ($focusBatchId) {
                $query->where('focus_batch_id', $focusBatchId);
            }
            if ($yearId) {
                $query->whereHas('employee.advanceRequest', function ($subQuery) use ($yearId) {
                    // Add year filtering if needed based on your year logic
                });
            }
            $activities = $query->orderBy('finalized_at', 'desc')->get();
            $activitiesData = [];
            foreach ($activities as $activity) {
                $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                $advanceRequest = $activity->employee->advanceRequest;
                $finalizerDetails = $this->getEmployeeDetails($activity->finalized_by);
                $activitiesData[] = ['id' => $activity->id, 'advance_request_id' => $advanceRequest->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id, 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'finance_approved_amount' => $activity->finance_approved_amount, 'payment_amount' => $activity->payment_amount, 'final_amount' => $activity->final_amount, 'final_status' => $activity->final_status, 'final_remarks' => $activity->final_remarks, 'finalized_by' => $activity->finalized_by, 'finalizer_name' => $finalizerDetails['EmployeeName'] ?? 'Unknown', 'finalized_at' => $activity->finalized_at, 'focus_batch_id' => $activity->focus_batch_id, 'focus_exported_at' => $activity->focus_exported_at, 'focus_imported_at' => $activity->focus_imported_at, 'submission_no' => $advanceRequest->submission_no, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at];
            }
            return $this->jsonSuccess(['activities' => $activitiesData, 'total_count' => count($activitiesData), 'total_payment_amount' => $activities->sum('payment_amount'), 'total_final_amount' => $activities->sum('final_amount'), 'focus_batches' => $activities->pluck('focus_batch_id')->unique()->values()], 'Finalized activities retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve finalized activities: ' . $e->getMessage());
        }
    }

    // public function exportPayments(Request $request)
    // {
    //     try {
    //         // Get filter parameters
    //         $employeeId = $request->input('employee_id');
    //         $requestId = $request->input('request_id');
    //         $batchId = $request->input('batch_id');
    //         $fromDate = $request->input('from_date');
    //         $toDate = $request->input('to_date');
    //         $yearId = $request->input('year_id');
    //         $format = $request->input('format', 'excel');  // Default to excel format
    //         $includeExported = $request->input('include_exported', false);  // Option to include already exported records
    //         $userId = $request->get('user_id');
    //         // Use Eloquent with proper relationships to avoid relationship errors
    //         $activitiesQuery = AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY);
    //         // Exclude already exported records unless explicitly requested
    //         if (!$includeExported) {
    //             $activitiesQuery->where(function ($query) {
    //                 $query->where('is_exported', false)->orWhereNull('is_exported');
    //             });
    //         }
    //         // Apply filters
    //         if ($employeeId) {
    //             $activitiesQuery->whereHas('employee', function ($query) use ($employeeId) {
    //                 $query->where('employee_id', $employeeId);
    //             });
    //         }
    //         if ($requestId) {
    //             $activitiesQuery->whereHas('employee.advanceRequest', function ($query) use ($requestId) {
    //                 $query->where('id', $requestId);
    //             });
    //         }
    //         if ($batchId) {
    //             $activitiesQuery->where('payment_batch_id', $batchId);
    //         }
    //         if ($fromDate) {
    //             $activitiesQuery->where('created_at', '>=', $fromDate);
    //         }
    //         if ($toDate) {
    //             $activitiesQuery->where('created_at', '<=', $toDate);
    //         }
    //         // Filter by year_id if provided
    //         if ($yearId) {
    //             // Get the financial year details
    //             try {
    //                 $financialYear = DB::table('financial_years')->where('id', $yearId)->first();
    //                 if ($financialYear) {
    //                     $activitiesQuery->whereBetween('created_at', [$financialYear->start_date, $financialYear->end_date]);
    //                 }
    //             } catch (\Exception $e) {
    //                 \Log::warning('Error filtering by year_id: ' . $e->getMessage());
    //             }
    //         }
    //         $activities = $activitiesQuery->orderBy('created_at', 'desc')->get();
    //         // If no activities found for export, check if there are excluded (already exported) activities
    //         if ($activities->isEmpty()) {
    //             // Get the excluded activities (already exported)
    //             $excludedQuery = AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY)->where('is_exported', true);
    //             // Apply same filters to excluded query
    //             if ($employeeId) {
    //                 $excludedQuery->whereHas('employee', function ($query) use ($employeeId) {
    //                     $query->where('employee_id', $employeeId);
    //                 });
    //             }
    //             if ($requestId) {
    //                 $excludedQuery->whereHas('employee.advanceRequest', function ($query) use ($requestId) {
    //                     $query->where('id', $requestId);
    //                 });
    //             }
    //             if ($batchId) {
    //                 $excludedQuery->where('payment_batch_id', $batchId);
    //             }
    //             if ($fromDate) {
    //                 $excludedQuery->where('created_at', '>=', $fromDate);
    //             }
    //             if ($toDate) {
    //                 $excludedQuery->where('created_at', '<=', $toDate);
    //             }
    //             if ($yearId) {
    //                 try {
    //                     $financialYear = DB::table('financial_years')->where('id', $yearId)->first();
    //                     if ($financialYear) {
    //                         $excludedQuery->whereBetween('created_at', [$financialYear->start_date, $financialYear->end_date]);
    //                     }
    //                 } catch (\Exception $e) {
    //                     \Log::warning('Error filtering by year_id: ' . $e->getMessage());
    //                 }
    //             }
    //             $excludedActivities = $excludedQuery->orderBy('last_export_date', 'desc')->get();
    //             // Build excluded list with details
    //             $excludedList = [];
    //             foreach ($excludedActivities as $activity) {
    //                 $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
    //                 $exporterDetails = null;
    //                 if ($activity->exported_by) {
    //                     $exporterDetails = $this->getEmployeeDetails($activity->exported_by);
    //                 }
    //                 $excludedList[] = ['activity_id' => $activity->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'amount' => (float) ($activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount), 'export_count' => $activity->export_count, 'first_export_date' => $activity->first_export_date, 'last_export_date' => $activity->last_export_date, 'exported_by' => $activity->exported_by, 'exporter_name' => $exporterDetails['EmployeeName'] ?? 'Unknown', 'payment_batch_id' => $activity->payment_batch_id, 'reason' => 'Already exported'];
    //             }
    //             // Log the excluded activities attempt
    //             $responseData = ['excluded_activities' => $excludedList, 'excluded_count' => count($excludedList), 'total_excluded_amount' => array_sum(array_column($excludedList, 'amount')), 'suggestion' => 'All activities matching your criteria have already been exported. Use include_exported=true parameter to re-export them.'];
    //             // Create export log entry for excluded activities
    //             try {
    //                 DB::table('advance_export_logs')->insert(['export_type' => 'payment_export_excluded', 'exported_by' => $userId, 'activity_count' => 0,  // No activities exported
    //                     'excluded_count' => count($excludedList), 'total_amount' => 0,  // No amount exported
    //                     'excluded_amount' => array_sum(array_column($excludedList, 'amount')), 'file_name' => null, 'file_path' => null, 'status' => 'excluded', 'filters' => json_encode(['employee_id' => $employeeId, 'request_id' => $requestId, 'batch_id' => $batchId, 'from_date' => $fromDate, 'to_date' => $toDate, 'year_id' => $yearId]), 'excluded_activities' => json_encode($excludedList), 'remarks' => 'Export attempted but all matching activities have already been exported', 'exported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    //                 \Log::info("Export excluded log created: User {$userId} attempted export but {$responseData['excluded_count']} activities were already exported");
    //             } catch (\Exception $e) {
    //                 \Log::error('Failed to create export excluded log: ' . $e->getMessage());
    //             }
    //             return response()->json(['success' => false, 'message' => 'No activities found with "Ready for Payment" status for export', 'data' => $responseData], 400);
    //         }
    //         // Transform data for export with comprehensive information
    //         $exportData = $activities->map(function ($activity) {
    //             // Get employee details from HRIMS
    //             $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
    //             // Get activity name from claimtype table
    //             $activityName = 'Advance Payment';
    //             if ($activity->employee->advanceRequest->activity_name_id) {
    //                 try {
    //                     $activityNameRecord = DB::table('claimtype')->where('ClaimId', $activity->employee->advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
    //                     $activityName = $activityNameRecord->ClaimName ?? 'Advance Payment';
    //                 } catch (\Exception $e) {
    //                     \Log::warning('Error fetching activity name: ' . $e->getMessage());
    //                 }
    //             }
    //             // Get advance type information
    //             $advanceTypeName = 'General';
    //             $advanceTypeCode = 'GEN';
    //             if ($activity->employee->advanceRequest->advance_type_id) {
    //                 try {
    //                     $advanceType = DB::table('advance_request_types')->where('id', $activity->employee->advanceRequest->advance_type_id)->first();
    //                     if ($advanceType) {
    //                         $advanceTypeName = $advanceType->type_name ?? 'General';
    //                         $advanceTypeCode = $advanceType->type_code ?? 'GEN';
    //                     }
    //                 } catch (\Exception $e) {
    //                     \Log::warning('Error fetching advance type: ' . $e->getMessage());
    //                 }
    //             }
    //             // Get crop names
    //             $cropNames = 'All Crop';
    //             if ($activity->activity_crops_id) {
    //                 try {
    //                     $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
    //                     if (is_array($cropIds) && !empty($cropIds)) {
    //                         $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->where('is_active', 1)->pluck('crop_name');
    //                         $cropNames = $crops->isNotEmpty() ? $crops->implode(', ') : 'All Crop';
    //                     }
    //                 } catch (\Exception $e) {
    //                     \Log::warning('Error fetching crop names: ' . $e->getMessage());
    //                 }
    //             }
    //             // Get variety names
    //             $varietyNames = '';
    //             if ($activity->activity_variety_id) {
    //                 try {
    //                     $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
    //                     if (is_array($varietyIds) && !empty($varietyIds)) {
    //                         $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->where('is_active', 1)->pluck('variety_name');
    //                         $varietyNames = $varieties->isNotEmpty() ? $varieties->implode(', ') : '';
    //                     }
    //                 } catch (\Exception $e) {
    //                     \Log::warning('Error fetching variety names: ' . $e->getMessage());
    //                 }
    //             }
    //             return (object) ['id' => $activity->id, 'doc_no' => null,  // Will be generated in export sheet
    //                 'date' => $activity->finance_date ?? $activity->created_at, 'created_at' => $activity->created_at, 'employee_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id, 'emp_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'activity_name' => $activityName, 'activity_type_name' => $activity->activityType->type_name ?? 'General', 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'purpose' => $activity->purpose ?? $activityName, 'description' => 'Advance payment for ' . $activityName, 'amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount, 'final_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount, 'payment_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount, 'reference' => "ADV-{$activity->id}", 'batch_id' => $activity->payment_batch_id, 'cash_bank_ac' => 'BANK-26', 'party_ac' => null, 'narration' => null, 'category' => 'N/A', 'region' => $employeeDetails['RegionName'] ?? '', 'region_name' => $employeeDetails['RegionName'] ?? '', 'region_code' => '', 'crop' => $cropNames, 'crop_name' => $cropNames, 'variety_names' => $varietyNames, 'cost_center' => $employeeDetails['StateName'] ?? '', 'state_name' => $employeeDetails['StateName'] ?? '', 'state_code' => '', 'department' => $employeeDetails['DepartmentName'] ?? '', 'department_name' => $employeeDetails['DepartmentName'] ?? '', 'department_code' => '', 'business_unit' => $employeeDetails['BusinessUnitName'] ?? '', 'business_unit_name' => $employeeDetails['BusinessUnitName'] ?? '', 'bu_code' => '', 'location' => $employeeDetails['LocationName'] ?? '', 'location_name' => $employeeDetails['LocationName'] ?? '', 'function' => $employeeDetails['FunctionName'] ?? '', 'function_name' => $employeeDetails['FunctionName'] ?? '', 'function_code' => '', 'vertical' => $employeeDetails['VerticalName'] ?? '', 'vertical_name' => $employeeDetails['VerticalName'] ?? '', 'vertical_code' => '', 'sub_department' => $employeeDetails['SubDepartmentName'] ?? '', 'sub_department_name' => $employeeDetails['SubDepartmentName'] ?? '', 'sub_department_code' => '', 'grade_name' => $employeeDetails['GradeName'] ?? '', 'designation_name' => $employeeDetails['Designation'] ?? '', 'business_entity' => '120', 'transaction_type' => '', 'financial_year' => date('Y'), 'request_month' => $activity->employee->advanceRequest->month ?? date('n'), 'remarks' => '',
    //                 // Export tracking info
    //                 'export_count' => $activity->export_count ?? 0, 'first_export_date' => $activity->first_export_date, 'last_export_date' => $activity->last_export_date];
    //         });
    //         // Generate export batch ID
    //         $exportBatchId = 'EXP_' . date('Ymd_His') . '_' . uniqid();
    //         $filename = 'adv_payment_export';
    //         if ($yearId) {
    //             $filename .= '_year_' . $yearId;
    //         }
    //         $filename .= '_' . date('Y_m_d_H_i_s') . '.xlsx';
    //         // Mark all activities as exported and log each export in a transaction
    //         try {
    //             DB::transaction(function () use ($activities, $userId, $exportBatchId, $filename, $exportData) {
    //                 $totalActivities = $activities->count();
    //                 foreach ($activities as $activity) {
    //                     // Mark as exported (updates counter and dates)
    //                     $activity->markAsExported($userId);
    //                     // Log this export request with full details
    //                     try {
    //                         \App\Models\AdvanceExportLog::create(['activity_id' => $activity->id, 'exported_by' => $userId, 'export_batch_id' => $exportBatchId, 'export_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount, 'export_type' => 'payment', 'export_status' => 'success', 'export_remarks' => "Payment export - Batch: {$exportBatchId}", 'file_name' => $filename, 'total_activities_in_export' => $totalActivities, 'exported_at' => now()]);
    //                         \Log::info("Export log created for activity {$activity->id}, batch: {$exportBatchId}");
    //                     } catch (\Exception $logError) {
    //                         \Log::error("Failed to create export log for activity {$activity->id}: " . $logError->getMessage());
    //                         // Don't throw - continue with export even if logging fails
    //                     }
    //                 }
    //             });
    //         } catch (\Exception $transactionError) {
    //             \Log::error('Transaction failed during export: ' . $transactionError->getMessage());
    //             throw $transactionError;
    //         }
    //         // Create and return export
    //         $export = new \App\Exports\PaymentExport(collect($exportData));
    //         // Log export activity
    //         \Log::info("Payment export completed: {$activities->count()} activities exported by user {$userId}, Batch: {$exportBatchId}");
    //         return $export->download($filename);
    //     } catch (Exception $e) {
    //         // Log failed export
    //         \Log::error('Payment export failed: ' . $e->getMessage());
    //         return $this->jsonError('Failed to export payment data: ' . $e->getMessage());
    //     }
    // }
    public function exportPayments(Request $request)
    {
        try {
            // Get filter parameters
            $employeeId = $request->input('employee_id');
            $requestId = $request->input('request_id');
            $batchId = $request->input('batch_id');
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $format = $request->input('format');  // Remove default value
            $includeExported = $request->input('include_exported', false);
            $userId = $request->get('user_id');
            // Build query
            $activitiesQuery = AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY);
            // Exclude already exported records unless explicitly requested
            if (!$includeExported) {
                $activitiesQuery->where(function ($query) {
                    $query->where('is_exported', false)->orWhereNull('is_exported');
                });
            }
            // Apply filters
            if ($employeeId) {
                $activitiesQuery->whereHas('employee', function ($query) use ($employeeId) {
                    $query->where('employee_id', $employeeId);
                });
            }
            if ($requestId) {
                $activitiesQuery->whereHas('employee.advanceRequest', function ($query) use ($requestId) {
                    $query->where('id', $requestId);
                });
            }
            if ($batchId) {
                $activitiesQuery->where('payment_batch_id', $batchId);
            }
            if ($fromDate) {
                $activitiesQuery->where('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $activitiesQuery->where('created_at', '<=', $toDate);
            }
            // Filter by year_id if provided
            if ($yearId) {
                try {
                    $financialYear = FinancialYear::find($yearId);
                    $activitiesQuery->where(function ($q) use ($yearId, $financialYear) {
                        $q->whereHas('employee.advanceRequest', function ($subQuery) use ($yearId) {
                            $subQuery->where('year_id', $yearId);
                        });
                        if ($financialYear && !empty($financialYear->FromDate) && !empty($financialYear->Todate)) {
                            $q->orWhereBetween('created_at', [
                                $financialYear->FromDate . ' 00:00:00',
                                $financialYear->Todate . ' 23:59:59'
                            ]);
                        }
                    });
                } catch (\Exception $e) {
                    \Log::warning('Error filtering by year_id: ' . $e->getMessage());
                }
            }
            $activities = $activitiesQuery->orderBy('created_at', 'desc')->get();
            // If no activities found for export
            if ($activities->isEmpty()) {
                // Get excluded activities (already exported)
                $excludedQuery = AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY)->where('is_exported', true);
                // Apply same filters to excluded query
                if ($employeeId) {
                    $excludedQuery->whereHas('employee', function ($query) use ($employeeId) {
                        $query->where('employee_id', $employeeId);
                    });
                }
                if ($requestId) {
                    $excludedQuery->whereHas('employee.advanceRequest', function ($query) use ($requestId) {
                        $query->where('id', $requestId);
                    });
                }
                if ($batchId) {
                    $excludedQuery->where('payment_batch_id', $batchId);
                }
                if ($fromDate) {
                    $excludedQuery->where('created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $excludedQuery->where('created_at', '<=', $toDate);
                }
                if ($yearId) {
                    try {
                        $financialYear = FinancialYear::find($yearId);
                        $excludedQuery->where(function ($q) use ($yearId, $financialYear) {
                            $q->whereHas('employee.advanceRequest', function ($subQuery) use ($yearId) {
                                $subQuery->where('year_id', $yearId);
                            });
                            if ($financialYear && !empty($financialYear->FromDate) && !empty($financialYear->Todate)) {
                                $q->orWhereBetween('created_at', [
                                    $financialYear->FromDate . ' 00:00:00',
                                    $financialYear->Todate . ' 23:59:59'
                                ]);
                            }
                        });
                    } catch (\Exception $e) {
                        \Log::warning('Error filtering by year_id: ' . $e->getMessage());
                    }
                }
                $excludedActivities = $excludedQuery->orderBy('last_export_date', 'desc')->get();
                // Build excluded list with details
                $excludedList = [];
                foreach ($excludedActivities as $activity) {
                    $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                    $exporterDetails = null;
                    if ($activity->exported_by) {
                        $exporterDetails = $this->getEmployeeDetails($activity->exported_by);
                    }
                    $excludedList[] = ['activity_id' => $activity->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'amount' => (float) ($activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount), 'export_count' => $activity->export_count, 'first_export_date' => $activity->first_export_date, 'last_export_date' => $activity->last_export_date, 'exported_by' => $activity->exported_by, 'exporter_name' => $exporterDetails['EmployeeName'] ?? 'Unknown', 'payment_batch_id' => $activity->payment_batch_id, 'reason' => 'Already exported'];
                }
                $responseData = ['excluded_activities' => $excludedList, 'excluded_count' => count($excludedList), 'total_excluded_amount' => array_sum(array_column($excludedList, 'amount')), 'suggestion' => 'All activities matching your criteria have already been exported. Use include_exported=true parameter to re-export them.'];
                // Create export log entry for excluded activities
                try {
                    DB::table('advance_export_logs')->insert(['export_type' => 'payment_export_excluded', 'exported_by' => $userId, 'activity_count' => 0, 'excluded_count' => count($excludedList), 'total_amount' => 0, 'excluded_amount' => array_sum(array_column($excludedList, 'amount')), 'file_name' => null, 'file_path' => null, 'status' => 'excluded', 'filters' => json_encode(['employee_id' => $employeeId, 'request_id' => $requestId, 'batch_id' => $batchId, 'from_date' => $fromDate, 'to_date' => $toDate, 'year_id' => $yearId]), 'excluded_activities' => json_encode($excludedList), 'remarks' => 'Export attempted but all matching activities have already been exported', 'exported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                    \Log::info("Export excluded log created: User {$userId} attempted export but {$responseData['excluded_count']} activities were already exported");
                } catch (\Exception $e) {
                    \Log::error('Failed to create export excluded log: ' . $e->getMessage());
                }
                return response()->json(['success' => false, 'message' => 'No pending payments are available for export.', 'data' => $responseData], 200);
            }
            // **CRITICAL: Check if this is just a validation request (no format or format != excel)**
            if (!$format || $format !== 'excel') {
                // Calculate total amount
                $totalAmount = $activities->sum(function ($activity) {
                    return $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount;
                });
                return response()->json(['success' => true, 'message' => 'Data available for export', 'data' => ['activity_count' => $activities->count(), 'total_amount' => $totalAmount]]);
            }
            // **FROM HERE: Only execute if format=excel**
            // Transform data for export with comprehensive information
            $exportData = $activities->map(function ($activity) use ($yearId) {
                // Get employee details from HRIMS
                $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                // Get activity name from claimtype table
                $activityName = 'Advance Payment';
                if ($activity->employee && $activity->employee->advanceRequest && $activity->employee->advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $activity->employee->advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimCode ?? 'Advance Payment';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = 'General';
                $advanceTypeCode = 'GEN';
                if ($activity->employee && $activity->employee->advanceRequest && $activity->employee->advanceRequest->advance_type_id) {
                    try {
                        $advanceType = DB::table('advance_request_types')->where('id', $activity->employee->advanceRequest->advance_type_id)->first();
                        if ($advanceType) {
                            $advanceTypeName = $advanceType->type_name ?? 'General';
                            $advanceTypeCode = $advanceType->type_code ?? 'GEN';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Get crop names
                $cropNames = 'All Crop';
                if ($activity->activity_crops_id) {
                    try {
                        $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                        if (is_array($cropIds) && !empty($cropIds)) {
                            $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->where('is_active', 1)->pluck('crop_code');
                            $cropNames = $crops->isNotEmpty() ? $crops->implode(', ') : 'All Crop';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching crop names: ' . $e->getMessage());
                    }
                }
                // Get variety names
                $varietyNames = '';
                if ($activity->activity_variety_id) {
                    try {
                        $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                        if (is_array($varietyIds) && !empty($varietyIds)) {
                            $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->where('is_active', 1)->pluck('variety_name');
                            $varietyNames = $varieties->isNotEmpty() ? $varieties->implode(', ') : '';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching variety names: ' . $e->getMessage());
                    }
                }

                // Check if advance is fixed imprest or promotional imprest
                $advanceRequest = $activity->employee->advanceRequest ?? null;
                $empId = $activity->employee->employee_id ?? null;

                $isImprest = false;
                if (!empty($activity->is_imprest) || (!empty($advanceRequest) && !empty($advanceRequest->is_imprest))) {
                    $isImprest = true;
                }

                $actPurpose = strtolower(trim($activity->purpose ?? ''));
                $reqPurpose = strtolower(trim($advanceRequest->purpose ?? ''));
                $finRemark = strtolower(trim($activity->finance_remark ?? ''));
                $pmtRemark = strtolower(trim($activity->payment_remark ?? ''));
                $combinedText = "{$actPurpose} {$reqPurpose} {$finRemark} {$pmtRemark}";

                if (str_contains($combinedText, 'imprest')) {
                    $isImprest = true;
                }

                // Check approved imprest requests
                $imprestReq = null;
                if ($empId) {
                    try {
                        $expConn = session('expense_connection') ?: 'expense';
                        $imprestReq = DB::connection($expConn)->table('imprest_requests')
                            ->where('employee_id', $empId)
                            ->where('status', 'approved')
                            ->orderByDesc('id')
                            ->first();
                        if (!$imprestReq) {
                            $imprestReq = DB::table('imprest_requests')
                                ->where('employee_id', $empId)
                                ->where('status', 'approved')
                                ->orderByDesc('id')
                                ->first();
                        }
                    } catch (\Exception $e) {
                        // table may not exist or query fails
                    }
                }

                if ($imprestReq && ($activity->is_imprest || ($advanceRequest && $advanceRequest->is_imprest) || str_contains($combinedText, 'imprest'))) {
                    $isImprest = true;
                }

                $isPromotional = false;
                if ($isImprest) {
                    if (
                        str_contains($combinedText, 'promotion') ||
                        str_contains($combinedText, 'promotional') ||
                        str_contains($combinedText, 'grade upgrade') ||
                        str_contains($combinedText, 'grade promotion') ||
                        str_contains($combinedText, 'upgrade')
                    ) {
                        $isPromotional = true;
                    } elseif ($imprestReq && in_array($imprestReq->trigger_type, ['promotion', 'grade_upgrade'])) {
                        $isPromotional = true;
                    }
                } elseif (
                    (str_contains($combinedText, 'promotion') || str_contains($combinedText, 'promotional') || str_contains($combinedText, 'grade upgrade') || str_contains($combinedText, 'grade promotion')) &&
                    str_contains($combinedText, 'imprest')
                ) {
                    $isImprest = true;
                    $isPromotional = true;
                }

                // Determine dynamic FY string
                $targetYearId = $yearId ?: ($advanceRequest->year_id ?? null);
                $fyString = null;
                if ($targetYearId) {
                    try {
                        $financialYearRecord = FinancialYear::find($targetYearId);
                        if ($financialYearRecord) {
                            if (!empty($financialYearRecord->y1) && !empty($financialYearRecord->y2)) {
                                $fyString = 'FY ' . $financialYearRecord->y1 . '-' . substr($financialYearRecord->y2, -2);
                            } elseif (!empty($financialYearRecord->Year)) {
                                $parts = explode('-', $financialYearRecord->Year);
                                $fyString = count($parts) === 2 ? 'FY ' . $parts[0] . '-' . substr($parts[1], -2) : 'FY ' . $financialYearRecord->Year;
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error resolving financial year: ' . $e->getMessage());
                    }
                }

                if (!$fyString) {
                    $dateObj = $activity->finance_date ? \Carbon\Carbon::parse($activity->finance_date) : ($activity->created_at ? \Carbon\Carbon::parse($activity->created_at) : now());
                    $yearVal = (int) $dateObj->format('Y');
                    $monthVal = (int) $dateObj->format('n');
                    $startYear = ($monthVal >= 4) ? $yearVal : $yearVal - 1;
                    $endYear = $startYear + 1;
                    $fyString = 'FY ' . $startYear . '-' . substr((string) $endYear, -2);
                }

                // Build Narration
                if ($isPromotional) {
                    $narration = 'Additional Fixed imprest as per grade promotion';
                } elseif ($isImprest) {
                    $narration = "Fixed Travel Imprest {$fyString}";
                } else {
                    $requestMonth = (int) ($advanceRequest->month ?? date('n'));
                    $months = [
                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                    ];
                    $monthName = $months[$requestMonth] ?? 'Jan';
                    $yearShort = (int) date('y');

                    if ($advanceTypeCode === 'Act') {
                        $narration = "Advance for {$activityName} {$monthName}-{$yearShort}";
                    } elseif ($advanceTypeCode === 'Exp') {
                        $narration = "Advance against expenses {$monthName}-{$yearShort}";
                    } else {
                        $narration = "Other Advance payment {$monthName}-{$yearShort}";
                    }
                }

                return (object) [
                    'id' => $activity->id,
                    'doc_no' => null,
                    'date' => $activity->finance_date ?? $activity->created_at,
                    'created_at' => $activity->created_at,
                    'employee_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id,
                    'emp_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee->employee_id,
                    'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown',
                    'activity_name' => $activityName,
                    'activity_type_name' => $activity->activityType->type_name ?? 'General',
                    'advance_type_name' => $advanceTypeName,
                    'advance_type_code' => $advanceTypeCode,
                    'purpose' => $activity->purpose ?? $activityName,
                    'description' => 'Advance payment for ' . $activityName,
                    'amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount,
                    'final_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount,
                    'payment_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount,
                    'reference' => "ADV-{$activity->id}",
                    'batch_id' => $activity->payment_batch_id,
                    'cash_bank_ac' => 'BANK-26',
                    'party_ac' => null,
                    'narration' => $narration,
                    'is_imprest' => $isImprest,
                    'is_promotional' => $isPromotional,
                    'year_id' => $targetYearId,
                    'financial_year_name' => $fyString,
                    'category' => 'N/A',
                    'region' => $employeeDetails['RegionName'] ?? '',
                    'region_name' => $employeeDetails['RegionName'] ?? '',
                    'region_code' => '',
                    'crop' => $cropNames,
                    'crop_name' => $cropNames,
                    'variety_names' => $varietyNames,
                    'cost_center' => $employeeDetails['StateName'] ?? '',
                    'state_name' => $employeeDetails['StateName'] ?? '',
                    'state_code' => '',
                    'department' => $employeeDetails['DepartmentName'] ?? '',
                    'department_name' => $employeeDetails['DepartmentName'] ?? '',
                    'department_code' => '',
                    'business_unit' => $employeeDetails['BusinessUnitName'] ?? '',
                    'business_unit_name' => $employeeDetails['BusinessUnitName'] ?? '',
                    'bu_code' => '',
                    'location' => $employeeDetails['LocationName'] ?? '',
                    'location_name' => $employeeDetails['LocationName'] ?? '',
                    'function' => $employeeDetails['FunctionName'] ?? '',
                    'function_name' => $employeeDetails['FunctionName'] ?? '',
                    'function_code' => '',
                    'vertical' => $employeeDetails['VerticalName'] ?? '',
                    'vertical_name' => $employeeDetails['VerticalName'] ?? '',
                    'vertical_code' => '',
                    'sub_department' => $employeeDetails['SubDepartmentName'] ?? '',
                    'sub_department_name' => $employeeDetails['SubDepartmentName'] ?? '',
                    'sub_department_code' => '',
                    'grade_name' => $employeeDetails['GradeName'] ?? '',
                    'designation_name' => $employeeDetails['Designation'] ?? '',
                    'business_entity' => '120',
                    'transaction_type' => '',
                    'financial_year' => date('Y'),
                    'request_month' => $activity->employee->advanceRequest->month ?? date('n'),
                    'remarks' => '',
                    'export_count' => $activity->export_count ?? 0,
                    'first_export_date' => $activity->first_export_date,
                    'last_export_date' => $activity->last_export_date
                ];
            });
            // Generate export batch ID
            $exportBatchId = 'EXP_' . date('Ymd_His') . '_' . uniqid();
            $filename = 'adv_payment_export';
            if ($yearId) {
                $filename .= '_year_' . $yearId;
            }
            $filename .= '_' . date('Y_m_d_H_i_s') . '.xlsx';
            // Mark all activities as exported and log each export in a transaction
            try {
                DB::transaction(function () use ($activities, $userId, $exportBatchId, $filename, $exportData) {
                    $totalActivities = $activities->count();
                    foreach ($activities as $activity) {
                        // Mark as exported (updates counter and dates)
                        $activity->markAsExported($userId);
                        // Log this export request with full details
                        try {
                            \App\Models\AdvanceExportLog::create(['activity_id' => $activity->id, 'exported_by' => $userId, 'export_batch_id' => $exportBatchId, 'export_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount, 'export_type' => 'payment', 'export_status' => 'success', 'export_remarks' => "Payment export - Batch: {$exportBatchId}", 'file_name' => $filename, 'total_activities_in_export' => $totalActivities, 'exported_at' => now()]);
                            \Log::info("Export log created for activity {$activity->id}, batch: {$exportBatchId}");
                        } catch (\Exception $logError) {
                            \Log::error("Failed to create export log for activity {$activity->id}: " . $logError->getMessage());
                            // Don't throw - continue with export even if logging fails
                        }
                    }
                });
            } catch (\Exception $transactionError) {
                \Log::error('Transaction failed during export: ' . $transactionError->getMessage());
                throw $transactionError;
            }
            // Create and return export
            $export = new \App\Exports\PaymentExport(collect($exportData));
            // Log export activity
            \Log::info("Payment export completed: {$activities->count()} activities exported by user {$userId}, Batch: {$exportBatchId}");
            return $export->download($filename);
        } catch (Exception $e) {
            // Log failed export
            \Log::error('Payment export failed: ' . $e->getMessage());
            return $this->jsonError('Failed to export payment data: ' . $e->getMessage());
        }
    }

    private function getCropNames($activity)
    {
        try {
            if ($activity->activity_crops_id && is_array($activity->activity_crops_id)) {
                $hrimsConnection = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
                $crops = DB::connection($hrimsConnection)->table('core_crops')->whereIn('id', $activity->activity_crops_id)->where('is_active', 1)->pluck('crop_name')->toArray();
                return implode(', ', $crops);
            }
        } catch (\Exception $e) {
            \Log::warning('Error fetching crop names: ' . $e->getMessage());
        }
        return 'All Crop';
    }

    public function confirmAdvancePayments(Request $request)
    {
        try {
            $userId = $request->get('confirmed_by');
            $yearId = ($request->filled('yearId') ? (int) $request->get('yearId') : null);
            DB::beginTransaction();
            // Step 1: Get all activities with PAYMENT_STATUS_READY
            $readyActivities = AdvanceRequestActivity::with(['employee'])->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY)->get();
            if ($readyActivities->isEmpty()) {
                DB::rollback();
                return response()->json(['success' => false, 'message' => 'No advance requests are ready for payment confirmation'], 400);
            }
            // Get the earliest finance_date from ready activities
            $earliestFinanceDate = $readyActivities->min('finance_date');
            // Get payment records from digi_next_payment_file (only PENDING status)
            $paymentRecords = DigiNextPaymentFile::where('match_status', 'PENDING')->whereDate('created_at', '>=', $earliestFinanceDate)->get();
            if ($paymentRecords->isEmpty()) {
                DB::rollback();
                return response()->json(['success' => false, 'message' => 'No payment records found for processing'], 400);
            }

            $matchedPayments = 0;
            $unsettledRequests = 0;
            $matchedActivityIds = [];

            // Step 2: Process each payment record (existing logic)
            foreach ($paymentRecords as $paymentRecord) {
                $paymentAmount = floatval($paymentRecord->amount ?? 0);
                $transactionRef = trim($paymentRecord->transaction_refno ?? '');
                $accountNo = trim($paymentRecord->beneficiary_account_number ?? '');

                if ($paymentAmount <= 0 || empty($accountNo)) {
                    continue;
                }

                $bestMatch = null;
                // Check account number and amount match
                foreach ($readyActivities as $activity) {
                    // Skip if already matched
                    if (in_array($activity->id, $matchedActivityIds)) {
                        continue;
                    }
                    // Get employee_id from the activity's employee relationship
                    $employeeId = null;
                    if ($activity->employee && isset($activity->employee->employee_id)) {
                        $employeeId = $activity->employee->employee_id;
                    }
                    if (!$employeeId) {
                        continue;
                    }
                    // Get account number from HRIMS
                    try {
                        $employeeGeneral = DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $employeeId)->first(['AccountNo']);
                        $activityAccountNo = $employeeGeneral ? trim($employeeGeneral->AccountNo) : '';
                    } catch (\Exception $e) {
                        $activityAccountNo = '';
                    }
                    // FIRST CHECK: Account number must match
                    if (empty($activityAccountNo) || $activityAccountNo !== $accountNo) {
                        continue;
                    }
                    // SECOND CHECK: Amount must match (only if account matched)
                    $activityAmount = floatval($activity->finance_approved_amount);
                    $amountDiff = abs($activityAmount - $paymentAmount);
                    if ($amountDiff <= 0.01) {
                        $bestMatch = $activity;
                        break;
                    }
                }
                // If we found a match, process it
                if ($bestMatch) {
                    // Update activity to PAID status
                    // UTR-01: copy the bank UTR; without one the payment stays unconfirmed
                    // UTR-04: a same-bank transfer has no UTR; its bank reference is the proof
                    [$pUtr, $pRef] = \App\Support\PaymentUtr::proof($paymentRecord->rbi_utr_reference_number ?? null, $paymentRecord->credit_reference ?? null, $paymentRecord->transaction_refno ?? null);
                    if ($pUtr === null && $pRef === null && \App\Support\PaymentUtr::required()) {
                        continue;
                    }
                    // LV-C05b: the user who released the payment may not confirm it against the bank file
                    if (\App\Support\MakerChecker::blocks($bestMatch->payment_released_by, $userId, 'advance_confirm', ['activity' => $bestMatch->id])) {
                        $makerCheckerSkipped = ($makerCheckerSkipped ?? 0) + 1;
                        continue;
                    }
                    $pclBefore = \App\Support\PaymentChangeLog::snapshot($bestMatch->getConnectionName() ?: config('database.default'), $bestMatch->getTable(), [$bestMatch->id]);
                    $bestMatch->update([
                        'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_PAID,
                        'payment_amount' => $paymentAmount,
                        'payment_date' => $paymentRecord->value_date ?? now()->toDateString(),
                        'utr_number' => $pUtr,
                        'payment_remark' => "Payment confirmed - Ref: {$transactionRef}"
                    ] + ($pRef !== null ? ['bank_reference' => $pRef] : []));
                    \App\Support\PaymentChangeLog::diff($bestMatch->getConnectionName() ?: config('database.default'), $bestMatch->getTable(), $pclBefore, 'advance', 'advance_bank_confirm', "Ref {$transactionRef}"); // PAY-LOG-01

                    // Update month final table with advance payment details
                    $employeeId = null;
                    $advanceRequestMonth = null;
                    if ($bestMatch->employee && isset($bestMatch->employee->employee_id)) {
                        $employeeId = $bestMatch->employee->employee_id;
                    }
                    // Get the month from advance_requests table
                    $advanceRequest = $bestMatch->employee->advanceRequest ?? null;
                    if ($advanceRequest) {
                        $advanceRequestMonth = $advanceRequest->month;
                    }

                    // Check if advance request or activity is flagged as imprest
                    $isImprest = ($advanceRequest && $advanceRequest->is_imprest) || (!empty($bestMatch->is_imprest));

                    if ($isImprest) {
                        // Imprest Auto Advance: Sync to employee_ledger_balances instead of monthly advance deduction
                        $this->syncImprestToEmployeeLedger(
                            $employeeId,
                            $paymentAmount,
                            $paymentRecord->value_date ?? now()->toDateString(),
                            $yearId,
                            $bestMatch->employee->company_id ?? 1
                        );
                    } else {
                        // Regular Advance: update month final table with advance payment details
                        if ($employeeId && $yearId && $advanceRequestMonth) {
                            $monthFinalTable = "y{$yearId}_monthexpensefinal";
                            DB::table($monthFinalTable)
                                ->where('EmployeeID', $employeeId)
                                ->where('Month', $advanceRequestMonth)
                                ->update([
                                    'Fin_AdvancePay' => $paymentAmount,
                                    'advance_payment_amount' => $paymentAmount,
                                    'advance_payment_date' => $paymentRecord->value_date,
                                    'advance_payment_for_month' => $advanceRequestMonth
                                ]);
                        }
                    }

                    // Update payment record as processed and matched
                    $paymentRecord->update([
                        'is_processed' => true,
                        'matched_activity_id' => $bestMatch->id,
                        'processed_at' => now(),
                        'processed_by' => $userId,
                        'match_status' => 'MATCHED',
                        'application' => 'Xeasy_Advance',
                        'year_id' => $yearId,
                        'month' => $advanceRequestMonth,
                        'match_remarks' => "Matched with activity ID {$bestMatch->id} - Account: {$accountNo}"
                    ]);

                    $matchedActivityIds[] = $bestMatch->id;
                    $matchedPayments++;

                    // Check if all activities in the advance request are now paid
                    $advanceRequest = $bestMatch->employee->advanceRequest ?? null;
                    if ($advanceRequest) {
                        $totalActivities = $advanceRequest->activities()->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->count();
                        $paidActivities = $advanceRequest->activities()->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->count();
                        if ($totalActivities > 0 && $paidActivities === $totalActivities) {
                            $advanceRequest->update(['current_status' => AdvanceRequest::STATUS_CLOSED]);
                        }
                    }
                }
            }

            // Mark unmatched activities as unsettled (FIXED LOGIC)
            foreach ($readyActivities as $activity) {
                if (in_array($activity->id, $matchedActivityIds)) {
                    continue;
                }

                $employeeName = 'Unknown';
                $hrimsAccountNo = 'N/A';
                $employeeId = null;

                // Get employee_id from relationship
                if ($activity->employee && isset($activity->employee->employee_id)) {
                    $employeeId = $activity->employee->employee_id;
                }

                // Get employee details from HRIMS
                if ($employeeId) {
                    try {
                        $employeeData = DB::connection('hrims')
                            ->table('hrm_employee_general as gen')
                            ->leftJoin('hrm_employee', 'gen.EmployeeID', '=', 'hrm_employee.EmployeeID')
                            ->where('gen.EmployeeID', $employeeId)
                            ->select(DB::raw("CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) as EmployeeName"), 'hrm_employee.EmpCode', 'gen.AccountNo')
                            ->first();

                        $employeeName = $employeeData ? $employeeData->EmployeeName . ' (' . $employeeData->EmpCode . ')' : 'Unknown';
                        $hrimsAccountNo = $employeeData ? trim($employeeData->AccountNo) : 'N/A';
                    } catch (\Exception $e) {
                        // Silent fail - use defaults
                    }
                }

                // Build base reason
                $reason = "Activity ID: {$activity->id} | Employee: {$employeeName} | HRIMS Account: {$hrimsAccountNo} | Requested Amount: ₹{$activity->finance_approved_amount}";

                // 🔹 1. Check payment with matching account first
                $paymentWithMatchingAccount = DigiNextPaymentFile::where('match_status', 'PENDING')
                    ->where('beneficiary_account_number', $hrimsAccountNo)
                    ->whereDate('created_at', '>=', $earliestFinanceDate)
                    ->first();

                if ($paymentWithMatchingAccount) {
                    $foundAmount = floatval($paymentWithMatchingAccount->amount);
                    $expectedAmount = floatval($activity->finance_approved_amount);
                    $transactionRef = $paymentWithMatchingAccount->transaction_refno ?? 'N/A';
                    $beneficiary = $paymentWithMatchingAccount->beneficiary_name ?? 'Unknown';

                    if (abs($foundAmount - $expectedAmount) > 0.01) {
                        $reason .= ' | ISSUE: Amount mismatch';
                    }

                    $excelAmount = $foundAmount;
                } else {
                    // 🔹 2. Check matching amount for different account
                    $paymentWithMatchingAmount = DigiNextPaymentFile::where('match_status', 'PENDING')
                        ->where('amount', $activity->finance_approved_amount)
                        ->whereDate('created_at', '>=', $earliestFinanceDate)
                        ->first();

                    if ($paymentWithMatchingAmount) {
                        $reason .= ' | ISSUE: Account number mismatch';

                        $excelAmount = $paymentWithMatchingAmount->amount;
                    } else {
                        // 🔹 3. Nothing found
                        $reason .= " | ISSUE: No payment record found for account {$hrimsAccountNo} or amount ₹{$activity->finance_approved_amount}";
                        $excelAmount = null;
                    }
                }

                // Insert or update UnsettledPayment
                $existingUnsettled = UnsettledPayment::where('activity_id', $activity->id)
                    ->where('application', 'Xeasy_Advance')
                    ->where('status', 'Unsettled')
                    ->first();

                $unsettledData = [
                    'transaction_refno' => 'UNSETTLED_ACTIVITY_' . $activity->id,
                    'beneficiary_name' => $employeeName,
                    'account_no' => $hrimsAccountNo,
                    'excel_amount' => isset($excelAmount) ? $excelAmount : null,
                    'db_amount' => $activity->finance_approved_amount,
                    'reason' => $reason,
                    'status' => 'Unsettled',
                    'application' => 'Xeasy_Advance',
                    'activity_id' => $activity->id
                ];

                if (!$existingUnsettled) {
                    UnsettledPayment::create($unsettledData);
                    $unsettledRequests++;
                } else {
                    // Update existing unsettled record with latest info and reason
                    $existingUnsettled->update($unsettledData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmation sync processed successfully',
                'data' => [
                    'total_payment_records' => $paymentRecords->count(),
                    'processed_payment_records' => $paymentRecords->count(),
                    'total_advance_requests' => $readyActivities->count(),
                    'matched_payments' => $matchedPayments,
                    're_matched_payments' => 0,
                    'maker_checker_skipped' => $makerCheckerSkipped ?? 0, // LV-C05b: left for another Finance user
                    'unsettled_requests' => $unsettledRequests,
                    'success_rate' => $readyActivities->count() > 0 ? round(($matchedPayments / $readyActivities->count()) * 100, 2) : 0,
                    'payment_match_rate' => $paymentRecords->count() > 0 ? round(($matchedPayments / $paymentRecords->count()) * 100, 2) : 0,
                    'force_resync_enabled' => false,
                    'errors' => []
                ]
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to confirm payments: ' . $e->getMessage()], 500);
        }
    }

    // public function confirmExpensePayments(Request $request)
    // {
    //     try {
    //         $userId = $request->get('confirmed_by');
    //         $yearId = $request->get('yearId');
    //         DB::beginTransaction();
    //         // Get the correct table name based on yearId
    //         $tableName = "y{$yearId}_monthexpensefinal";
    //         // Step 1: Get all activities with PAYMENT_STATUS_READY
    //         $readyActivities = DB::connection(session('expense_connection'))->table($tableName)->where('payment_status', 'InstructedPayment')->get();
    //         if ($readyActivities->isEmpty()) {
    //             DB::rollback();
    //             return response()->json(['success' => false, 'message' => 'No expense requests are ready for payment confirmation'], 400);
    //         }
    //         // Get the earliest finance_date from ready activities
    //         $earliestFinanceDate = $readyActivities->min('payment_instructed_date');
    //         // Get payment records from digi_next_payment_file (only PENDING status)
    //         $paymentRecords = DigiNextPaymentFile::where('match_status', 'PENDING')->whereDate('created_at', '>=', $earliestFinanceDate)->get();
    //         if ($paymentRecords->isEmpty()) {
    //             DB::rollback();
    //             return response()->json(['success' => false, 'message' => 'No payment records found for processing'], 400);
    //         }
    //         $matchedPayments = 0;
    //         $unsettledRequests = 0;
    //         $matchedActivityIds = [];
    //         // Step 2: Process each payment record
    //         foreach ($paymentRecords as $paymentRecord) {
    //             $paymentAmount = floatval($paymentRecord->amount ?? 0);
    //             $transactionRef = trim($paymentRecord->transaction_refno ?? '');
    //             $accountNo = trim($paymentRecord->beneficiary_account_number ?? '');
    //             if ($paymentAmount <= 0 || empty($transactionRef) || empty($accountNo)) {
    //                 continue;
    //             }
    //             $bestMatch = null;
    //             // Check account number and amount match
    //             foreach ($readyActivities as $activity) {
    //                 // Skip if already matched
    //                 if (in_array($activity->id, $matchedActivityIds)) {
    //                     continue;
    //                 }
    //                 // Get employee_id from the activity
    //                 $employeeId = $activity->EmployeeID ?? null;
    //                 if (!$employeeId) {
    //                     continue;
    //                 }
    //                 // Get account number from HRIMS
    //                 try {
    //                     $employeeGeneral = DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $employeeId)->first(['AccountNo']);
    //                     $activityAccountNo = $employeeGeneral ? trim($employeeGeneral->AccountNo) : '';
    //                 } catch (\Exception $e) {
    //                     $activityAccountNo = '';
    //                 }
    //                 // FIRST CHECK: Account number must match
    //                 if (empty($activityAccountNo) || $activityAccountNo !== $accountNo) {
    //                     continue;
    //                 }
    //                 // SECOND CHECK: Amount must match (only if account matched)
    //                 $activityAmount = floatval($activity->payment_amount);
    //                 $amountDiff = abs($activityAmount - $paymentAmount);
    //                 if ($amountDiff <= 0.01) {
    //                     $bestMatch = $activity;
    //                     break;
    //                 }
    //             }
    //             // If we found a match, process it
    //             if ($bestMatch) {
    //                 // Get amounts
    //                 $employeeId = $bestMatch->EmployeeID;
    //                 $financeAmount = floatval($bestMatch->Finance_Amount ?? 0);
    //                 $advanceAmount = floatval($bestMatch->advance_payment_amount ?? 0);
    //                 $daAmount = floatval($bestMatch->da_payment_amount ?? 0);
    //                 $adjustActivityAmount = floatval($bestMatch->adjust_activity_amount ?? 0);
    //                 $adjustExpenseAmount = floatval($bestMatch->adjust_expense_amount ?? 0);
    //                 $ledgerBalanceAtApproval = floatval($bestMatch->ledger_balance_at_approval ?? 0);
    //                 // Calculate total adjustments
    //                 $totalAdjustments = $daAmount + $adjustActivityAmount + $adjustExpenseAmount;
    //                 // Build the payment remark based on different scenarios
    //                 $paymentRemark = "Paid Rs. {$paymentAmount}";
    //                 // Case 1: No advance, DA, and no adjustments
    //                 if ($totalAdjustments == 0) {
    //                     $paymentRemark .= ', No adjustments';
    //                 }
    //                 // Case 2: Has advance, DA, or adjustments
    //                 else {
    //                     $adjustmentParts = [];
    //                     // Add DA if exists
    //                     if ($daAmount > 0) {
    //                         $adjustmentParts[] = "DA Rs. {$daAmount}";
    //                     }
    //                     // Add activity adjustment if exists
    //                     if ($adjustActivityAmount > 0) {
    //                         $adjustmentParts[] = "Activity Adv. Rs. {$adjustActivityAmount}";
    //                     }
    //                     // Add expense adjustment if exists
    //                     if ($adjustExpenseAmount > 0) {
    //                         $adjustmentParts[] = "Expense Adv. Rs. {$adjustExpenseAmount}";
    //                     }
    //                     if (!empty($adjustmentParts)) {
    //                         $paymentRemark .= ', Adjusted with ' . implode(', ', $adjustmentParts);
    //                     }
    //                 }
    //                 // Additional remark for verification
    //                 $finPayRemark1 = "Bank Ref: {$transactionRef} | Account: {$accountNo}";
    //                 // Breakdown remark
    //                 $finPayRemark2 = "Finance Amt: Rs. {$financeAmount}";
    //                 if ($totalAdjustments > 0) {
    //                     $finPayRemark2 .= " | Total Adj: Rs. {$totalAdjustments}";
    //                 }
    //                 $finPayRemark2 .= " | Net Payment: Rs. {$paymentAmount}";
    //                 // Determine if advance was paid
    //                 $finAdvancePay = ($advanceAmount > 0) ? 'Yes' : 'No';
    //                 // Update MonthExpenseFinal record using DB query builder
    //                 DB::connection(session('expense_connection'))->table($tableName)->where('id', $bestMatch->id)->update(['payment_status' => 'ConfirmedPayment', 'payment_date' => $paymentRecord->value_date ?? now()->toDateString(), 'payment_confirmed_date' => now()->toDateString(), 'Fin_AdvancePay' => $finAdvancePay, 'Fin_PayOption' => 'Bank', 'Fin_PayBy' => $userId, 'Fin_PayAmt' => $paymentAmount, 'Fin_PayDate' => $paymentRecord->value_date ?? now()->toDateString(), 'Fin_PayRemark' => $paymentRemark, 'Fin_PayRemark_1' => $finPayRemark1, 'Fin_PayRemark_2' => $finPayRemark2]);
    //                 // Update employee_ledger_balances
    //                 if ($employeeId && $yearId) {
    //                     try {
    //                         // Get current ledger balance
    //                         $ledgerRecord = DB::table('employee_ledger_balances')->where('employee_id', $employeeId)->where('year_id', $yearId)->first();
    //                         if ($ledgerRecord) {
    //                             // Record exists - Update it
    //                             $oldBalance = floatval($ledgerRecord->balance ?? 0);
    //                             // Calculate new balance: Use ledger balance at approval
    //                             $newBalance = $ledgerBalanceAtApproval;
    //                             // Build ledger remark with payment details
    //                             $ledgerRemark = "Payment confirmed - Month Expense Final ID: {$bestMatch->id}";
    //                             $ledgerRemark .= " | {$paymentRemark}";  // Include payment remark with adjustments
    //                             $ledgerRemark .= " | Old Balance: Rs. {$oldBalance}";
    //                             $ledgerRemark .= " | New Balance: Rs. {$newBalance}";
    //                             $ledgerRemark .= " | Ref: {$transactionRef}";
    //                             // Update ledger balance
    //                             DB::table('employee_ledger_balances')->where('employee_id', $employeeId)->where('year_id', $yearId)->update(['balance' => $newBalance, 'entry_date' => now()->toDateString(), 'remark' => $ledgerRemark, 'updated_at' => now()]);
    //                         } else {
    //                             // No record exists - Insert new one
    //                             $newBalance = 0 - $paymentAmount;
    //                             // Build ledger remark for new record with payment details
    //                             $ledgerRemark = "Initial Payment confirmed - Month Expense Final ID: {$bestMatch->id}";
    //                             $ledgerRemark .= " | {$paymentRemark}";  // Include payment remark with adjustments
    //                             $ledgerRemark .= " | New Balance: Rs. {$newBalance}";
    //                             $ledgerRemark .= " | Ref: {$transactionRef}";
    //                             // Get company_id from employee or use default
    //                             $companyId = 1;  // Default company ID
    //                             try {
    //                                 $employeeCompany = DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $employeeId)->first(['CompanyId']);
    //                                 if ($employeeCompany && $employeeCompany->CompanyId) {
    //                                     $companyId = $employeeCompany->CompanyId;
    //                                 }
    //                             } catch (\Exception $e) {
    //                                 // Use default company_id
    //                             }
    //                             // Insert new ledger record
    //                             DB::table('employee_ledger_balances')->insert(['year_id' => $yearId, 'employee_id' => $employeeId, 'company_id' => $companyId, 'balance' => $newBalance, 'entry_date' => now()->toDateString(), 'fixed_imprest' => 0.0, 'fixed_imprest_date' => '0000-00-00', 'grade' => null, 'remark' => $ledgerRemark, 'created_at' => now(), 'updated_at' => now()]);
    //                         }
    //                     } catch (\Exception $e) {
    //                         // Log error but don't fail the transaction
    //                         \Log::error("Failed to update/insert employee ledger balance for Employee ID {$employeeId}: " . $e->getMessage());
    //                     }
    //                 }
    //                 // Update payment record as processed and matched
    //                 $paymentRecord->update(['is_processed' => true, 'matched_activity_id' => $bestMatch->id, 'processed_at' => now(), 'processed_by' => $userId, 'match_status' => 'MATCHED', 'application' => 'Xeasy_Expense', 'match_remarks' => "Matched with MonthExpenseFinal ID {$bestMatch->id} - Account: {$accountNo}"]);
    //                 $matchedActivityIds[] = $bestMatch->id;
    //                 $matchedPayments++;
    //             }
    //         }
    //         // Mark unmatched activities as unsettled
    //         foreach ($readyActivities as $activity) {
    //             if (!in_array($activity->id, $matchedActivityIds)) {
    //                 $employeeName = 'Unknown';
    //                 $hrimsAccountNo = 'N/A';
    //                 $employeeId = $activity->EmployeeID ?? null;
    //                 // Get employee details from HRIMS
    //                 if ($employeeId) {
    //                     try {
    //                         $employeeData = DB::connection('hrims')->table('hrm_employee_general as gen')->leftJoin('hrm_employee', 'gen.EmployeeID', '=', 'hrm_employee.EmployeeID')->where('gen.EmployeeID', $employeeId)->select(DB::raw("CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) as EmployeeName"), 'hrm_employee.EmpCode', 'gen.AccountNo')->first();
    //                         $employeeName = $employeeData ? $employeeData->EmployeeName . ' (' . $employeeData->EmpCode . ')' : 'Unknown';
    //                         $hrimsAccountNo = $employeeData ? $employeeData->AccountNo : 'N/A';
    //                     } catch (\Exception $e) {
    //                         // Silent fail - use defaults
    //                     }
    //                 }
    //                 // Check if there's a payment record with matching amount but different account
    //                 $paymentWithMatchingAmount = DigiNextPaymentFile::where('match_status', 'PENDING')->where('amount', $activity->payment_amount)->whereDate('created_at', '>=', $earliestFinanceDate)->first();
    //                 // Build detailed reason for unsettled status
    //                 $reason = "Activity ID: {$activity->id} | Employee: {$employeeName} | HRIMS Account: {$hrimsAccountNo} | Requested Amount: ₹{$activity->payment_amount}";
    //                 if ($paymentWithMatchingAmount) {
    //                     $paymentAccountNo = $paymentWithMatchingAmount->beneficiary_account_number ?? 'Unknown';
    //                     $beneficiaryName = $paymentWithMatchingAmount->beneficiary_name ?? 'Unknown';
    //                     $transactionRef = $paymentWithMatchingAmount->transaction_refno ?? 'N/A';
    //                     $reason .= " | ISSUE: Payment found with matching amount (₹{$paymentWithMatchingAmount->amount}) but account number mismatch";
    //                     $reason .= " | Payment File Account: {$paymentAccountNo}";
    //                     $reason .= " | Payment Beneficiary: {$beneficiaryName}";
    //                     $reason .= " | Transaction Ref: {$transactionRef}";
    //                 } else {
    //                     $reason .= " | ISSUE: No payment record found in payment file with amount ₹{$activity->payment_amount}";
    //                 }
    //                 // Check if unsettled record already exists for this activity
    //                 $existingUnsettled = UnsettledPayment::where('activity_id', $activity->id)->where('application', 'Xeasy_Expense')->where('status', 'Unsettled')->first();
    //                 if (!$existingUnsettled) {
    //                     // Create new unsettled record
    //                     UnsettledPayment::create(['transaction_refno' => 'UNSETTLED_EXPENSE_' . $activity->id, 'beneficiary_name' => $employeeName, 'account_no' => $hrimsAccountNo, 'excel_amount' => $paymentWithMatchingAmount ? $paymentWithMatchingAmount->amount : null, 'db_amount' => $activity->payment_amount, 'reason' => $reason, 'status' => 'Unsettled', 'application' => 'Xeasy_Expense', 'activity_id' => $activity->id]);
    //                     $unsettledRequests++;
    //                 } else {
    //                     // Update existing unsettled record
    //                     $existingUnsettled->update(['beneficiary_name' => $employeeName, 'account_no' => $hrimsAccountNo, 'excel_amount' => $paymentWithMatchingAmount ? $paymentWithMatchingAmount->amount : null, 'db_amount' => $activity->payment_amount, 'reason' => $reason, 'application' => 'Xeasy_Expense']);
    //                 }
    //             }
    //         }
    //         DB::commit();
    //         return response()->json(['success' => true, 'message' => 'Expense payment confirmation processed successfully', 'data' => ['total_payment_records' => $paymentRecords->count(), 'processed_payment_records' => $paymentRecords->count(), 'total_expense_requests' => $readyActivities->count(), 'matched_payments' => $matchedPayments, 're_matched_payments' => 0, 'unsettled_requests' => $unsettledRequests, 'success_rate' => $readyActivities->count() > 0 ? round(($matchedPayments / $readyActivities->count()) * 100, 2) : 0, 'payment_match_rate' => $paymentRecords->count() > 0 ? round(($matchedPayments / $paymentRecords->count()) * 100, 2) : 0, 'force_resync_enabled' => false, 'errors' => []]]);
    //     } catch (Exception $e) {
    //         DB::rollback();
    //         return response()->json(['success' => false, 'message' => 'Failed to confirm expense payments: ' . $e->getMessage()], 500);
    //     }
    // }

    public function confirmExpensePayments(Request $request)
    {
        try {
            $userId = $request->get('confirmed_by');
            $yearId = ($request->filled('yearId') ? (int) $request->get('yearId') : null);

            \Log::info('=== Expense Payment Confirmation Started ===', [
                'user_id' => $userId,
                'year_id' => $yearId,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Log database connection details
            $expenseConnection = DB::connection(session('expense_connection'));
            $hrimsConnection = DB::connection('hrims');
            $defaultConnection = DB::connection();

            \Log::info('Database Connection Details', [
                'expense_connection' => [
                    'driver' => $expenseConnection->getDriverName(),
                    'database' => $expenseConnection->getDatabaseName(),
                    'host' => config('database.connections.expense.host'),
                ],
                'hrims_connection' => [
                    'driver' => $hrimsConnection->getDriverName(),
                    'database' => $hrimsConnection->getDatabaseName(),
                    'host' => config('database.connections.hrims.host'),
                ],
                'default_connection' => [
                    'driver' => $defaultConnection->getDriverName(),
                    'database' => $defaultConnection->getDatabaseName(),
                    'host' => config('database.connections.' . config('database.default') . '.host'),
                ]
            ]);

            DB::beginTransaction();
            // Get the correct table name based on yearId
            $tableName = "y{$yearId}_monthexpensefinal";

            \Log::info("Using expense table: {$tableName}");

            // Step 1: Get all activities with PAYMENT_STATUS_READY
            $readyActivities = DB::connection(session('expense_connection'))->table($tableName)->where('payment_status', 'InstructedPayment')->get();

            \Log::info("Found {$readyActivities->count()} expense activities with InstructedPayment status");

            if ($readyActivities->isEmpty()) {
                \Log::warning('No expense requests ready for payment confirmation');
                DB::rollback();
                return response()->json(['success' => false, 'message' => 'No expense requests are ready for payment confirmation'], 400);
            }
            // Get the earliest finance_date from ready activities
            $earliestFinanceDate = $readyActivities->min('payment_instructed_date');

            \Log::info("Earliest payment instructed date: {$earliestFinanceDate}");

            // Get payment records from digi_next_payment_file (only PENDING status)
            $paymentRecords = DigiNextPaymentFile::where('match_status', 'PENDING')->whereDate('created_at', '>=', $earliestFinanceDate)->get();
            // $paymentRecords = DigiNextPaymentFile::where('match_status', 'PENDING')->get();

            \Log::info("Found {$paymentRecords->count()} pending payment records from digi_next_payment_file");

            if ($paymentRecords->isEmpty()) {
                \Log::warning('No payment records found for processing');
                DB::rollback();
                return response()->json(['success' => false, 'message' => 'No payment records found for processing'], 400);
            }
            $matchedPayments = 0;
            $unsettledRequests = 0;
            $matchedActivityIds = [];

            \Log::info('=== Starting Payment Matching Process ===');

            // Step 2: Process each payment record
            foreach ($paymentRecords as $paymentRecord) {
                $paymentAmount = floatval($paymentRecord->amount ?? 0);
                $transactionRef = trim($paymentRecord->transaction_refno ?? '');
                $accountNo = trim($paymentRecord->beneficiary_account_number ?? '');

                \Log::debug('Processing payment record', [
                    'payment_id' => $paymentRecord->id,
                    'transaction_ref' => $transactionRef,
                    'account_no' => $accountNo,
                    'amount' => $paymentAmount
                ]);

                if ($paymentAmount <= 0 || empty($accountNo)) {
                    \Log::warning('Skipping invalid payment record', [
                        'payment_id' => $paymentRecord->id,
                        'reason' => $paymentAmount <= 0 ? 'Invalid amount' : 'Empty account number'
                    ]);
                    continue;
                }
                $bestMatch = null;
                // Check account number and amount match
                foreach ($readyActivities as $activity) {
                    // Skip if already matched
                    if (in_array($activity->id, $matchedActivityIds)) {
                        continue;
                    }
                    // Get employee_id from the activity
                    $employeeId = $activity->EmployeeID ?? null;
                    if (!$employeeId) {
                        continue;
                    }
                    // Get account number from HRIMS
                    try {
                        $employeeGeneral = DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $employeeId)->first(['AccountNo']);
                        $activityAccountNo = $employeeGeneral ? trim($employeeGeneral->AccountNo) : '';

                        \Log::debug('Fetched employee account from HRIMS for matching', [
                            'activity_id' => $activity->id,
                            'employee_id' => $employeeId,
                            'account_no' => $activityAccountNo,
                            'payment_account_no' => $accountNo,
                            'connection' => 'hrims',
                            'table' => 'hrm_employee_general'
                        ]);
                    } catch (\Exception $e) {
                        $activityAccountNo = '';
                        \Log::error('Failed to fetch employee account from HRIMS', [
                            'activity_id' => $activity->id,
                            'employee_id' => $employeeId,
                            'error' => $e->getMessage(),
                            'connection' => 'hrims'
                        ]);
                    }
                    // FIRST CHECK: Account number must match
                    if (empty($activityAccountNo) || $activityAccountNo !== $accountNo) {
                        continue;
                    }
                    // SECOND CHECK: Amount must match (only if account matched)
                    $activityAmount = floatval($activity->payment_amount);
                    $amountDiff = abs($activityAmount - $paymentAmount);
                    if ($amountDiff <= 0.01) {
                        // LV-C06: value date must not be before the payment instruction
                        if (! empty($paymentRecord->value_date) && ! empty($activity->payment_instructed_date) && substr((string) $paymentRecord->value_date, 0, 10) < substr((string) $activity->payment_instructed_date, 0, 10)) {
                            continue;
                        }
                        $candidates[] = $activity;
                    }
                }
                // LV-C06: two open months for the same account and amount -> not matched automatically
                if (count($candidates ?? []) === 1) {
                    $bestMatch = $candidates[0];
                } elseif (count($candidates ?? []) > 1) {
                    \Log::warning('LV-C06 ambiguous bank match left for manual review', ['payment_id' => $paymentRecord->id, 'months' => array_map(fn ($c) => $c->id, $candidates)]);
                }
                $candidates = [];
                // If we found a match, process it
                if ($bestMatch) {
                    \Log::info('✓ Payment matched with expense activity', [
                        'payment_id' => $paymentRecord->id,
                        'activity_id' => $bestMatch->id,
                        'employee_id' => $bestMatch->EmployeeID,
                        'account_no' => $accountNo,
                        'payment_amount' => $paymentAmount,
                        'activity_amount' => floatval($bestMatch->payment_amount)
                    ]);

                    // Get amounts
                    $employeeId = $bestMatch->EmployeeID;
                    $financeAmount = floatval($bestMatch->Finance_Amount ?? 0);
                    $advanceAmount = floatval($bestMatch->advance_payment_amount ?? 0);
                    $daAmount = floatval($bestMatch->da_payment_amount ?? 0);
                    if ($daAmount == 0) {
                        try {
                            $claimsTable = "y{$yearId}_expenseclaims";
                            $daRow = DB::connection(session('expense_connection'))->selectOne("
                                SELECT 
                                    COALESCE(SUM(CASE WHEN FinancedTAmt > 0 THEN FinancedTAmt ELSE 0 END), 0) as da_fin,
                                    COALESCE(SUM(CASE WHEN ApprTAmt > 0 THEN ApprTAmt ELSE 0 END), 0) as da_appr,
                                    COALESCE(SUM(CASE WHEN VerifyTAmt > 0 THEN VerifyTAmt ELSE 0 END), 0) as da_ver,
                                    COALESCE(SUM(CASE WHEN FilledTAmt > 0 THEN FilledTAmt ELSE 0 END), 0) as da_fill
                                FROM {$claimsTable}
                                WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                            ", [$employeeId, $bestMatch->Month]);
                            if ($daRow) {
                                $daAmount = floatval($daRow->da_fin > 0 ? $daRow->da_fin : ($daRow->da_appr > 0 ? $daRow->da_appr : ($daRow->da_ver > 0 ? $daRow->da_ver : ($daRow->da_fill ?? 0))));
                            }
                        } catch (\Exception $e) {}
                    }
                    $adjustActivityAmount = floatval($bestMatch->adjust_activity_amount ?? 0);
                    $adjustExpenseAmount = floatval($bestMatch->adjust_expense_amount ?? 0);
                    $ledgerBalanceAtApproval = floatval($bestMatch->ledger_balance_at_approval ?? 0);
                    // Calculate total adjustments
                    $totalAdjustments = $adjustActivityAmount + $adjustExpenseAmount;
                    if ($daAmount == 0) {
                        $appr = floatval($bestMatch->Approved_Amount ?? ($bestMatch->Verified_Amount ?? 0));
                        $paid = floatval($paymentAmount);
                        if ($appr > 0 && $paid > 0 && ($appr - $totalAdjustments - $paid) > 0) {
                            $daAmount = round($appr - $totalAdjustments - $paid, 2);
                        }
                    }
                    // Build the payment remark based on different scenarios
                    $companyId = session('company_id');
                    if ($companyId == 11 && function_exists('formatVvnrPaymentRemark')) {
                        $paymentRemark = formatVvnrPaymentRemark($bestMatch->EmployeeID, $bestMatch->Month, $yearId, $paymentAmount, '', $bestMatch);
                    } else {
                        $paymentRemark = "Paid Rs. {$paymentAmount}";
                        // Case 1: No advance adjustments
                        if ($totalAdjustments == 0) {
                            $paymentRemark .= ', No adjustments';
                        }
                        // Case 2: Has advance adjustments
                        else {
                            $adjustmentParts = [];
                            // Add activity adjustment if exists
                            if ($adjustActivityAmount > 0) {
                                $adjustmentParts[] = "Activity Adv. Rs. {$adjustActivityAmount}";
                            }
                            // Add expense adjustment if exists
                            if ($adjustExpenseAmount > 0) {
                                $adjustmentParts[] = "Expense Adv. Rs. {$adjustExpenseAmount}";
                            }
                            if (!empty($adjustmentParts)) {
                                $paymentRemark .= ', Adjusted with ' . implode(', ', $adjustmentParts);
                            }
                        }
                        // Mention DA separately if exists
                        if ($daAmount > 0) {
                            $daStr = (floor($daAmount) == $daAmount) ? number_format($daAmount, 0, '', '') : number_format($daAmount, 2);
                            $paymentRemark .= ", DA Rs. {$daStr}";
                        }
                    }
                    // Additional remark for verification
                    $finPayRemark1 = "Bank Ref: {$transactionRef} | Account: {$accountNo}";
                    // Breakdown remark
                    $finPayRemark2 = "Finance Amt: Rs. {$financeAmount}";
                    if ($totalAdjustments > 0) {
                        $finPayRemark2 .= " | Total Adj: Rs. {$totalAdjustments}";
                    }
                    $finPayRemark2 .= " | Net Payment: Rs. {$paymentAmount}";
                    // Determine if advance was paid
                    $finAdvancePay = ($advanceAmount > 0) ? 'Yes' : 'No';
                    // Update MonthExpenseFinal record using DB query builder
                    \Log::info('Updating expense activity to ConfirmedPayment', [
                        'activity_id' => $bestMatch->id,
                        'payment_status' => 'ConfirmedPayment',
                        'payment_amount' => $paymentAmount,
                        'payment_date' => $paymentRecord->value_date ?? now()->toDateString()
                    ]);

                    // LV-C05b: the user who released the month to the bank may not confirm it
                    if (\App\Support\MakerChecker::blocks($bestMatch->payment_instructed_by ?? null, $userId, 'expense_bank_confirm', ['month_final' => $bestMatch->id])) {
                        $makerCheckerSkipped = ($makerCheckerSkipped ?? 0) + 1;
                        continue;
                    }
                    $pclBefore = \App\Support\PaymentChangeLog::snapshot(session('expense_connection'), $tableName, [$bestMatch->id]);
                    DB::connection(session('expense_connection'))->table($tableName)->where('id', $bestMatch->id)->update(['payment_status' => 'ConfirmedPayment', 'payment_date' => $paymentRecord->value_date ?? now()->toDateString(), 'payment_confirmed_date' => now()->toDateString(), 'Fin_PayOption' => 'Bank', 'Fin_PayBy' => $userId, 'Fin_PayAmt' => $paymentAmount, 'Fin_PayDate' => $paymentRecord->value_date ?? now()->toDateString(), 'Fin_PayRemark' => $paymentRemark, 'Fin_PayRemark_1' => $finPayRemark1, 'Fin_PayRemark_2' => $finPayRemark2] + (\Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->hasColumn($tableName, 'utr_number') && $transactionRef !== '' ? ['utr_number' => $transactionRef] : [])); // LV-C06: UTR on the month
                    \App\Support\PaymentChangeLog::diff(session('expense_connection'), $tableName, $pclBefore, 'month_final', 'expense_bank_confirm', "Ref {$transactionRef}"); // PAY-LOG-01
                    $getMonth = DB::connection(session('expense_connection'))->table($tableName)->where('id', $bestMatch->id)->first();
                    // Update payment record as processed and matched
                    \Log::info('Updating payment record to MATCHED status', [
                        'payment_id' => $paymentRecord->id,
                        'matched_activity_id' => $bestMatch->id,
                        'employee_id' => $employeeId,
                        'month' => $getMonth->Month
                    ]);

                    $paymentRecord->update([
                        'is_processed' => true,
                        'matched_activity_id' => (int) $bestMatch->id, // LV-C06: link to the month record
                        'processed_at' => now(),
                        'processed_by' => $userId,
                        'match_status' => 'MATCHED',
                        'application' => 'Xeasy_Expense',
                        'year_id' => $yearId,
                        'month' => $getMonth->Month,
                        'employee_id' => $employeeId,
                        'match_remarks' => "Matched with MonthExpenseFinal ID {$bestMatch->id} - Account: {$accountNo}"
                    ]);

                    \Log::info('Payment record updated successfully', [
                        'payment_id' => $paymentRecord->id,
                        'stored_matched_activity_id' => $bestMatch->id,
                        'stored_employee_id' => $employeeId,
                        'stored_month' => $getMonth->Month,
                        'stored_year_id' => $yearId
                    ]);
                    $matchedActivityIds[] = $bestMatch->id;
                    $matchedPayments++;
                } else {
                    \Log::warning('✗ No matching expense activity found for payment', [
                        'payment_id' => $paymentRecord->id,
                        'transaction_ref' => $transactionRef,
                        'account_no' => $accountNo,
                        'amount' => $paymentAmount
                    ]);
                }
            }

            \Log::info('=== Payment Matching Completed ===', [
                'total_payments_processed' => $paymentRecords->count(),
                'matched_payments' => $matchedPayments,
                'unmatched_payments' => $paymentRecords->count() - $matchedPayments
            ]);

            \Log::info('=== Processing Unsettled Activities ===');

            // Mark unmatched activities as unsettled
            foreach ($readyActivities as $activity) {
                if (in_array($activity->id, $matchedActivityIds)) {
                    continue;
                }

                \Log::info('Processing unmatched activity for unsettled payment', [
                    'activity_id' => $activity->id,
                    'employee_id' => $activity->EmployeeID ?? null,
                    'payment_amount' => $activity->payment_amount
                ]);

                $employeeName = 'Unknown';
                $hrimsAccountNo = 'N/A';
                $employeeId = $activity->EmployeeID ?? null;

                // Get employee details from HRIMS
                if ($employeeId) {
                    try {
                        $employeeData = DB::connection('hrims')
                            ->table('hrm_employee_general as gen')
                            ->leftJoin('hrm_employee', 'gen.EmployeeID', '=', 'hrm_employee.EmployeeID')
                            ->where('gen.EmployeeID', $employeeId)
                            ->select(DB::raw("CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) as EmployeeName"), 'hrm_employee.EmpCode', 'gen.AccountNo')
                            ->first();

                        $employeeName = $employeeData ? $employeeData->EmployeeName . ' (' . $employeeData->EmpCode . ')' : 'Unknown';
                        $hrimsAccountNo = $employeeData ? trim($employeeData->AccountNo) : 'N/A';

                        \Log::debug('Fetched employee details from HRIMS for unsettled payment', [
                            'activity_id' => $activity->id,
                            'employee_id' => $employeeId,
                            'employee_name' => $employeeName,
                            'account_no' => $hrimsAccountNo,
                            'connection' => 'hrims',
                            'tables' => 'hrm_employee_general, hrm_employee'
                        ]);
                    } catch (\Exception $e) {
                        // Silent fail - use defaults
                        \Log::error('Failed to fetch employee details from HRIMS for unsettled payment', [
                            'activity_id' => $activity->id,
                            'employee_id' => $employeeId,
                            'error' => $e->getMessage(),
                            'connection' => 'hrims'
                        ]);
                    }
                }

                // Build base reason
                $reason = "Activity ID: {$activity->id} | Employee: {$employeeName} | HRIMS Account: {$hrimsAccountNo} | Requested Amount: ₹{$activity->payment_amount}";

                // 🔹 1. Check payment with matching account first
                $paymentWithMatchingAccount = DigiNextPaymentFile::where('match_status', 'PENDING')
                    ->where('beneficiary_account_number', $hrimsAccountNo)
                    ->whereDate('created_at', '>=', $earliestFinanceDate)
                    ->first();

                if ($paymentWithMatchingAccount) {
                    $foundAmount = floatval($paymentWithMatchingAccount->amount);
                    $expectedAmount = floatval($activity->payment_amount);

                    if (abs($foundAmount - $expectedAmount) > 0.01) {
                        $reason .= ' | ISSUE: Amount mismatch';
                    }

                    $excelAmount = $foundAmount;
                } else {
                    // 🔹 2. Check matching amount for different account
                    $paymentWithMatchingAmount = DigiNextPaymentFile::where('match_status', 'PENDING')
                        ->where('amount', $activity->payment_amount)
                        ->whereDate('created_at', '>=', $earliestFinanceDate)
                        ->first();

                    if ($paymentWithMatchingAmount) {
                        $reason .= ' | ISSUE: Account number mismatch';

                        $excelAmount = $paymentWithMatchingAmount->amount;
                    } else {
                        // 🔹 3. Nothing found
                        $reason .= " | ISSUE: No payment record found for account {$hrimsAccountNo} or amount ₹{$activity->payment_amount}";
                        $excelAmount = null;
                    }
                }

                // Insert or update UnsettledPayment
                $existingUnsettled = UnsettledPayment::where('activity_id', $activity->id)
                    ->where('application', 'Xeasy_Expense')
                    ->where('status', 'Unsettled')
                    ->first();

                $unsettledData = [
                    'transaction_refno' => 'UNSETTLED_EXPENSE_' . $activity->id,
                    'beneficiary_name' => $employeeName,
                    'account_no' => $hrimsAccountNo,
                    'excel_amount' => isset($excelAmount) ? $excelAmount : null,
                    'db_amount' => $activity->payment_amount,
                    'reason' => $reason,
                    'status' => 'Unsettled',
                    'application' => 'Xeasy_Expense',
                    'activity_id' => $activity->id
                ];

                if (!$existingUnsettled) {
                    UnsettledPayment::create($unsettledData);
                    $unsettledRequests++;
                    \Log::info('Created new unsettled payment record', [
                        'activity_id' => $activity->id,
                        'employee_name' => $employeeName,
                        'account_no' => $hrimsAccountNo,
                        'db_amount' => $activity->payment_amount,
                        'excel_amount' => isset($excelAmount) ? $excelAmount : null,
                        'reason' => $reason
                    ]);
                } else {
                    // Update existing unsettled record with latest info and reason
                    $existingUnsettled->update($unsettledData);
                    \Log::info('Updated existing unsettled payment record', [
                        'unsettled_id' => $existingUnsettled->id,
                        'activity_id' => $activity->id,
                        'reason' => $reason
                    ]);
                }
            }
            DB::commit();

            \Log::info('=== Expense Payment Confirmation Completed Successfully ===', [
                'total_payment_records' => $paymentRecords->count(),
                'total_expense_requests' => $readyActivities->count(),
                'matched_payments' => $matchedPayments,
                'unsettled_requests' => $unsettledRequests,
                'success_rate' => $readyActivities->count() > 0 ? round(($matchedPayments / $readyActivities->count()) * 100, 2) : 0,
                'payment_match_rate' => $paymentRecords->count() > 0 ? round(($matchedPayments / $paymentRecords->count()) * 100, 2) : 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expense payment confirmation processed successfully',
                'data' => [
                    'total_payment_records' => $paymentRecords->count(),
                    'processed_payment_records' => $paymentRecords->count(),
                    'total_expense_requests' => $readyActivities->count(),
                    'matched_payments' => $matchedPayments,
                    're_matched_payments' => 0,
                    'maker_checker_skipped' => $makerCheckerSkipped ?? 0, // LV-C05b: left for another Finance user
                    'unsettled_requests' => $unsettledRequests,
                    'success_rate' => $readyActivities->count() > 0 ? round(($matchedPayments / $readyActivities->count()) * 100, 2) : 0,
                    'payment_match_rate' => $paymentRecords->count() > 0 ? round(($matchedPayments / $paymentRecords->count()) * 100, 2) : 0,
                    'force_resync_enabled' => false,
                    'errors' => []
                ]
            ]);
        } catch (Exception $e) {
            DB::rollback();

            \Log::error('=== Expense Payment Confirmation Failed ===', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to confirm expense payments: ' . $e->getMessage()], 500);
        }
    }

    public function forceResyncPayments(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            DB::beginTransaction();
            // Reset all payment records to allow re-processing
            $resetCount = DigiNextPaymentFile::where('transaction_status', 'SUCCESS')->where('is_processed', true)->update(['is_processed' => false, 'matched_activity_id' => null, 'processed_at' => null, 'processed_by' => null, 'match_status' => 'PENDING', 'match_remarks' => 'Reset for re-sync processing']);
            // Reset all activities from PAID back to READY for re-matching
            $resetActivities = AdvanceRequestActivity::where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->update(['payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_READY, 'payment_amount' => null, 'payment_date' => null, 'payment_remark' => 'Reset for re-sync processing']);
            // Mark all unsettled records as resolved (they will be recreated if still unmatched)
            $resolvedUnsettled = DB::table('unsettled_payments')->where('status', 'Unsettled')->update(['status' => 'Resolved', 'reason' => 'Reset for re-sync processing', 'updated_at' => now()]);
            DB::commit();
            \Log::info("Force re-sync initiated: Reset {$resetCount} payment records, {$resetActivities} activities, {$resolvedUnsettled} unsettled records");
            // Now call the regular confirmAdvancePayments with force_resync flag
            $request->merge(['force_resync' => true]);
            return $this->confirmAdvancePayments($request);
        } catch (Exception $e) {
            DB::rollback();
            \Log::error('Force re-sync failed: ' . $e->getMessage());
            return $this->jsonError('Failed to force re-sync payments: ' . $e->getMessage());
        }
    }

    private function handleExistingUnsettledRecords($readyActivities, $paymentRecords, $userId, &$matchedActivityIds, &$reMatchedPayments)
    {
        try {
            // Get existing unsettled records for activities that are ready for payment
            $activityIds = $readyActivities->pluck('id')->toArray();
            $unsettledRecords = DB::table('unsettled_payments')->whereIn('activity_id', $activityIds)->where('status', 'Unsettled')->get();
            foreach ($unsettledRecords as $unsettledRecord) {
                $activity = $readyActivities->firstWhere('id', $unsettledRecord->activity_id);
                if (!$activity)
                    continue;
                $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                $employeeName = $employeeDetails['EmployeeName'] ?? '';
                $activityAmount = $activity->finance_approved_amount;
                // Try to find a matching payment record for this unsettled activity
                $bestMatch = null;
                $bestSimilarity = 0;
                foreach ($paymentRecords as $paymentRecord) {
                    // Skip if already processed and matched
                    if ($paymentRecord->is_processed && $paymentRecord->match_status === 'MATCHED') {
                        continue;
                    }
                    $beneficiaryName = trim($paymentRecord->beneficiary_name ?? '');
                    $amount = floatval($paymentRecord->amount ?? 0);
                    // Check amount match first (exact match with proper decimal handling)
                    $activityAmount = $this->cleanAmount($activityAmount);
                    $paymentAmount = $this->cleanAmount($amount);
                    if (abs($activityAmount - $paymentAmount) <= 0.01) {
                        // Calculate name similarity
                        $similarity = $this->calculateNameSimilarity($beneficiaryName, $employeeName);
                        if ($similarity > $bestSimilarity && $similarity > 0.8) {
                            $bestMatch = $paymentRecord;
                            $bestSimilarity = $similarity;
                        }
                    }
                }
                // If we found a match, process it
                if ($bestMatch) {
                    $transactionRef = trim($bestMatch->transaction_refno ?? '');
                    // Update activity status
                    [$pUtr, $pRef] = \App\Support\PaymentUtr::proof($bestMatch->rbi_utr_reference_number ?? null, $bestMatch->credit_reference ?? null, $bestMatch->transaction_refno ?? null); // UTR-01 / UTR-04
                    if ($pUtr === null && $pRef === null && \App\Support\PaymentUtr::required()) {
                        continue;
                    }
                    $activity->update(($pRef !== null ? ['bank_reference' => $pRef] : []) + ['payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_PAID, 'payment_amount' => $bestMatch->amount, 'payment_date' => $bestMatch->value_date ?? now()->toDateString(), 'utr_number' => $pUtr, 'payment_remark' => "Payment re-matched from unsettled - Ref: {$transactionRef} - UTR: {$bestMatch->rbi_utr_reference_number}"]);
                    // Update digi_next_payment_file record
                    $bestMatch->update(['is_processed' => true, 'matched_activity_id' => $activity->id, 'processed_at' => now(), 'processed_by' => $userId, 'match_status' => 'MATCHED', 'application' => 'Xeasy_Advance', 'match_remarks' => "Re-matched from unsettled - Activity ID {$activity->id} - Employee: {$employeeName} - Amount: {$bestMatch->amount}"]);
                    // Remove the unsettled record
                    DB::table('unsettled_payments')->where('id', $unsettledRecord->id)->update(['status' => 'Resolved', 'updated_at' => now()]);
                    // Track this activity as matched
                    $matchedActivityIds[] = $activity->id;
                    $reMatchedPayments++;
                    // Check if all activities in the request are now paid
                    $advanceRequest = $activity->employee->advanceRequest;
                    $this->checkAndUpdateRequestPaymentStatus($advanceRequest);
                    \Log::info("Re-matched unsettled payment: Activity {$activity->id} with Payment Record {$bestMatch->id} - Amount: {$bestMatch->amount}");
                }
            }
        } catch (Exception $e) {
            \Log::error('Error handling existing unsettled records: ' . $e->getMessage());
        }
    }

    private function removeUnsettledRecord($activityId, $reason = 'Payment confirmed')
    {
        try {
            $updated = DB::table('unsettled_payments')->where('activity_id', $activityId)->where('status', 'Unsettled')->update(['status' => 'Resolved', 'reason' => $reason, 'updated_at' => now()]);
            if ($updated > 0) {
                \Log::info("Removed unsettled record for activity {$activityId}: {$reason}");
            }
        } catch (Exception $e) {
            \Log::error("Error removing unsettled record for activity {$activityId}: " . $e->getMessage());
        }
    }

    private function createUnsettledRecord($transactionRef, $beneficiaryName, $excelAmount, $dbAmount, $reason, $activityId = null)
    {
        // For unmatched advance requests, use activity_id as the primary identifier to prevent duplicates
        if ($activityId) {
            // Use database transaction to prevent race conditions
            return DB::transaction(function () use ($transactionRef, $beneficiaryName, $excelAmount, $dbAmount, $reason, $activityId) {
                // Check if an unsettled record already exists for this activity
                $existingRecord = DB::table('unsettled_payments')
                    ->where('activity_id', $activityId)
                    ->where('status', 'Unsettled')
                    ->lockForUpdate()  // Prevent race conditions
                    ->first();
                if ($existingRecord) {
                    // Update existing record instead of creating duplicate
                    DB::table('unsettled_payments')->where('id', $existingRecord->id)->update(['beneficiary_name' => $beneficiaryName, 'excel_amount' => $excelAmount, 'db_amount' => $dbAmount, 'reason' => $reason, 'updated_at' => now()]);
                    \Log::info("Updated existing unsettled record for activity_id: {$activityId}");
                    return $existingRecord->id;
                }
                // For unmatched advance requests (no transaction ref), create a consistent identifier
                if ($transactionRef === null && $activityId) {
                    // Use activity_id as part of the reference to ensure uniqueness and consistency
                    $transactionRef = 'UNMATCHED_ACTIVITY_' . $activityId;
                } elseif ($transactionRef === null) {
                    // Fallback for cases without activity_id
                    $transactionRef = 'UNMATCHED_' . time() . '_' . rand(1000, 9999);
                }
                // Handle null excel_amount - set to null for database (now that column is nullable)
                // This represents cases where there's no Excel entry for the advance request
                $excelAmount = $excelAmount;  // Keep as null if null, or use the provided value
                // Create new unsettled payment record
                $recordId = DB::table('unsettled_payments')->insertGetId(['transaction_refno' => $transactionRef, 'beneficiary_name' => $beneficiaryName, 'excel_amount' => $excelAmount,  // Can now be null
                    'db_amount' => $dbAmount, 'reason' => $reason, 'status' => 'Unsettled', 'activity_id' => $activityId,  // Store the matching activity ID if available
                    'created_at' => now(), 'updated_at' => now()]);
                \Log::info("Created new unsettled record for activity_id: {$activityId}, record_id: {$recordId}");
                return $recordId;
            });
        }
        // For cases without activity_id, create record directly (less likely to have duplicates)
        if ($transactionRef === null) {
            $transactionRef = 'UNMATCHED_' . time() . '_' . rand(1000, 9999);
        }
        $recordId = DB::table('unsettled_payments')->insertGetId(['transaction_refno' => $transactionRef, 'beneficiary_name' => $beneficiaryName, 'excel_amount' => $excelAmount, 'db_amount' => $dbAmount, 'reason' => $reason, 'status' => 'Unsettled', 'activity_id' => $activityId, 'created_at' => now(), 'updated_at' => now()]);
        \Log::info("Created unsettled record without activity_id, record_id: {$recordId}");
        return $recordId;
    }

    public function getConfirmedPayments(Request $request)
    {
        try {
            // Get parameters from request (same as releasedPayments)
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $batchId = $request->input('batch_id');
            $employeeId = $request->input('employee_id');
            $limit = $request->input('limit', 50);  // Default limit
            $page = $request->input('page', 1);  // Default page
            // Build query for requests that have activities marked as "Paid"
            $query = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->whereHas('activities', function ($activityQuery) use ($batchId) {
                $activityQuery->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->whereNotNull('payment_date');
                if ($batchId) {
                    $activityQuery->where('payment_batch_id', $batchId);
                }
            });
            // Filter by employee if specified
            if ($employeeId) {
                $query->whereHas('employees', function ($employeeQuery) use ($employeeId) {
                    $employeeQuery->where('employee_id', $employeeId);
                });
            }
            $requests = $query->orderBy('updated_at', 'desc')->limit($limit)->offset(($page - 1) * $limit)->get();
            // Get total count for pagination
            $totalCount = $query->count();
            // Transform the data to match the expected format - employees with req_data inside (same as releasedPayments)
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get overall approval summary for this request
                $approvalSummary = [];
                try {
                    $overallApprovals = DB::table('advance_activity_approvals as aaa')->join('advance_request_activities as ara', 'aaa.advance_request_activity_id', '=', 'ara.id')->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')->where('are.advance_request_id', $advanceRequest->id)->where('aaa.submission_no', $advanceRequest->submission_no)->select(['aaa.approval_level', DB::raw('COUNT(aaa.id) as total_approvals'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Approved" THEN 1 END) as approved_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Rejected" THEN 1 END) as rejected_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Pending" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Reverted" THEN 1 END) as reverted_count'), DB::raw('SUM(CASE WHEN aaa.approver_status = "Approved" THEN aaa.approver_amount ELSE 0 END) as total_approved_amount'), DB::raw('MIN(aaa.approver_date) as first_action_date'), DB::raw('MAX(aaa.approver_date) as last_action_date')])->groupBy('aaa.approval_level')->orderBy('aaa.approval_level')->get();
                    foreach ($overallApprovals as $levelSummary) {
                        // Determine overall status for this level
                        $levelStatus = 'Pending';
                        if ($levelSummary->rejected_count > 0) {
                            $levelStatus = 'Rejected';
                        } elseif ($levelSummary->reverted_count > 0) {
                            $levelStatus = 'Reverted';
                        } elseif ($levelSummary->pending_count == 0 && $levelSummary->approved_count > 0) {
                            $levelStatus = 'Approved';
                        }
                        $approvalSummary[] = ['level' => $levelSummary->approval_level, 'total_approvals' => $levelSummary->total_approvals, 'approved_count' => $levelSummary->approved_count, 'rejected_count' => $levelSummary->rejected_count, 'pending_count' => $levelSummary->pending_count, 'reverted_count' => $levelSummary->reverted_count, 'total_approved_amount' => $levelSummary->total_approved_amount ? (float) $levelSummary->total_approved_amount : 0, 'first_action_date' => $levelSummary->first_action_date, 'last_action_date' => $levelSummary->last_action_date, 'status' => $levelStatus, 'completion_percentage' => $levelSummary->total_approvals > 0 ? round((($levelSummary->approved_count + $levelSummary->rejected_count + $levelSummary->reverted_count) / $levelSummary->total_approvals) * 100, 2) : 0];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval summary for request ' . $advanceRequest->id . ': ' . $e->getMessage());
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object (same as releasedPayments)
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approval_summary' => $approvalSummary, 'advance_history' => $this->getAdvanceHistory($advanceRequest->id), 'expense_submissions' => $this->getExpenseSubmissions($advanceRequest, $yearId)];
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any confirmed payment activities
                    $hasConfirmedActivities = $employee->activities->filter(function ($activity) use ($batchId) {
                        $matchesStatus = $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PAID && $activity->payment_date !== null;
                        $matchesBatch = !$batchId || $activity->payment_batch_id === $batchId;
                        return $matchesStatus && $matchesBatch;
                    })->isNotEmpty();
                    if (!$hasConfirmedActivities) {
                        continue;  // Skip employees with no confirmed activities
                    }
                    // Filter by specific employee if requested
                    if ($employeeId && $employee->employee_id != $employeeId) {
                        continue;
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include activities with "Paid" status and payment date
                        $matchesStatus = $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PAID && $activity->payment_date !== null;
                        $matchesBatch = !$batchId || $activity->payment_batch_id === $batchId;
                        if (!$matchesStatus || !$matchesBatch) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                    $cropNames = $crops->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching crop names: ' . $e->getMessage());
                            }
                        }
                        // Get variety names
                        $varietyNames = [];
                        if ($activity->activity_variety_id) {
                            try {
                                $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }
                        // Get approval details for this activity
                        $approvalDetails = [];
                        try {
                            $approvals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $advanceRequest->submission_no)->orderBy('approval_level')->get();
                            foreach ($approvals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                // Get reverter details if reverted
                                $reverterDetails = null;
                                if ($approval->reverted_by) {
                                    $reverterDetails = $this->getEmployeeDetails($approval->reverted_by);
                                }
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => (isset($approverDetails['EmployeeName']) && $approverDetails['EmployeeName'] != 'N/A')
                                    ? $approverDetails['EmployeeName']
                                    : 'By System', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        // Get payment confirmation details
                        $paymentConfirmationDetails = null;
                        $digiNextPaymentDetails = null;
                        if ($activity->payment_date) {
                            $paymentConfirmationDetails = ['confirmed_date' => $activity->payment_date, 'confirmed_amount' => $activity->payment_amount, 'confirmation_remark' => $activity->payment_remark, 'batch_id' => $activity->payment_batch_id];
                            // Extract reference from payment remark and get digi_next_payment_file details
                            if ($activity->payment_remark) {
                                // Extract reference from remark (format: "Payment confirmed via Excel upload - Ref: BT26013049926940")
                                if (preg_match('/Ref:\s*([A-Z0-9]+)/', $activity->payment_remark, $matches)) {
                                    $transactionRef = $matches[1];
                                    try {
                                        $digiNextRecord = DigiNextPaymentFile::where('transaction_refno', $transactionRef)->first();
                                        if ($digiNextRecord) {
                                            $digiNextPaymentDetails = ['id' => $digiNextRecord->id, 'record_no' => $digiNextRecord->record_no, 'transaction_refno' => $digiNextRecord->transaction_refno, 'amount' => $digiNextRecord->amount, 'beneficiary_name' => $digiNextRecord->beneficiary_name, 'beneficiary_account_number' => $digiNextRecord->beneficiary_account_number, 'beneficiary_aadhaar_number' => $digiNextRecord->beneficiary_aadhaar_number, 'bank_name' => $digiNextRecord->bank_name, 'ifsc_code' => $digiNextRecord->ifsc_code, 'destination_bank' => $digiNextRecord->destination_bank, 'debit_account_number' => $digiNextRecord->debit_account_number, 'payment_type' => $digiNextRecord->payment_type, 'value_date' => $digiNextRecord->value_date, 'rbi_utr_reference_number' => $digiNextRecord->rbi_utr_reference_number, 'credit_reference' => $digiNextRecord->credit_reference, 'currency' => $digiNextRecord->currency, 'transaction_status' => $digiNextRecord->transaction_status, 'error_description' => $digiNextRecord->error_description, 'import_date' => $digiNextRecord->import_date, 'import_batch_id' => $digiNextRecord->import_batch_id, 'created_at' => $digiNextRecord->created_at];
                                        }
                                    } catch (\Exception $e) {
                                        \Log::warning('Error fetching digi_next_payment_file details for reference ' . $transactionRef . ': ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have confirmed activities
                    if (!empty($activitiesData)) {
                        // Get employee ledger balance
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            // Get payment batch summary if batch_id is provided
            $batchSummary = null;
            if ($batchId) {
                $batchSummary = $this->getPaymentBatchSummary($batchId);
            }
            // Prepare response with pagination info (same format as releasedPayments)
            $response = ['employees' => $allEmployees, 'pagination' => ['current_page' => (int) $page, 'per_page' => (int) $limit, 'total' => $totalCount, 'total_pages' => ceil($totalCount / $limit)], 'filters' => ['year_id' => $yearId, 'batch_id' => $batchId, 'employee_id' => $employeeId, 'status' => 'Paid']];
            if ($batchSummary) {
                $response['batch_summary'] = $batchSummary;
            }
            return $this->jsonSuccess($response, 'Confirmed payments retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve confirmed payments: ' . $e->getMessage());
        }
    }

    public function getEmployeeConfirmedPayments(Request $request)
    {
        try {
            // Get parameters from request
            $employeeId = $request->input('employee_id');
            $month = $request->input('month');
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $batchId = $request->input('batch_id');
            $limit = $request->input('limit', 50);
            $page = $request->input('page', 1);
            // Validate required parameters
            if (!$employeeId) {
                return $this->jsonError('employee_id is required');
            }
            // Build query for requests that have activities marked as "Paid" for specific employee
            $query = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->whereHas('activities', function ($activityQuery) use ($batchId) {
                $activityQuery->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->whereNotNull('payment_date');
                if ($batchId) {
                    $activityQuery->where('payment_batch_id', $batchId);
                }
            })->whereHas('employees', function ($employeeQuery) use ($employeeId) {
                $employeeQuery->where('employee_id', $employeeId);
            });
            // Filter by month if provided
            if ($month) {
                $query->where('month', $month);
            }
            $requests = $query->orderBy('updated_at', 'desc')->limit($limit)->offset(($page - 1) * $limit)->get();
            // Get total count for pagination
            $totalCount = $query->count();
            // Transform the data to match the expected format - employees with req_data inside
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get overall approval summary for this request
                $approvalSummary = [];
                try {
                    $overallApprovals = DB::table('advance_activity_approvals as aaa')->join('advance_request_activities as ara', 'aaa.advance_request_activity_id', '=', 'ara.id')->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')->where('are.advance_request_id', $advanceRequest->id)->where('aaa.submission_no', $advanceRequest->submission_no)->select(['aaa.approval_level', DB::raw('COUNT(aaa.id) as total_approvals'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Approved" THEN 1 END) as approved_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Rejected" THEN 1 END) as rejected_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Pending" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN aaa.approver_status = "Reverted" THEN 1 END) as reverted_count'), DB::raw('SUM(CASE WHEN aaa.approver_status = "Approved" THEN aaa.approver_amount ELSE 0 END) as total_approved_amount'), DB::raw('MIN(aaa.approver_date) as first_action_date'), DB::raw('MAX(aaa.approver_date) as last_action_date')])->groupBy('aaa.approval_level')->orderBy('aaa.approval_level')->get();
                    foreach ($overallApprovals as $levelSummary) {
                        // Determine overall status for this level
                        $levelStatus = 'Pending';
                        if ($levelSummary->rejected_count > 0) {
                            $levelStatus = 'Rejected';
                        } elseif ($levelSummary->reverted_count > 0) {
                            $levelStatus = 'Reverted';
                        } elseif ($levelSummary->pending_count == 0 && $levelSummary->approved_count > 0) {
                            $levelStatus = 'Approved';
                        }
                        $approvalSummary[] = ['level' => $levelSummary->approval_level, 'total_approvals' => $levelSummary->total_approvals, 'approved_count' => $levelSummary->approved_count, 'rejected_count' => $levelSummary->rejected_count, 'pending_count' => $levelSummary->pending_count, 'reverted_count' => $levelSummary->reverted_count, 'total_approved_amount' => $levelSummary->total_approved_amount ? (float) $levelSummary->total_approved_amount : 0, 'first_action_date' => $levelSummary->first_action_date, 'last_action_date' => $levelSummary->last_action_date, 'status' => $levelStatus, 'completion_percentage' => $levelSummary->total_approvals > 0 ? round((($levelSummary->approved_count + $levelSummary->rejected_count + $levelSummary->reverted_count) / $levelSummary->total_approvals) * 100, 2) : 0];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval summary for request ' . $advanceRequest->id . ': ' . $e->getMessage());
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object (same format as getConfirmedPayments)
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approval_summary' => $approvalSummary, 'advance_history' => $this->getAdvanceHistory($advanceRequest->id), 'expense_submissions' => $this->getExpenseSubmissions($advanceRequest, $yearId)];
                // Process each employee for this request (filter by specific employee)
                foreach ($advanceRequest->employees as $employee) {
                    // Only process the specific employee
                    if ($employee->employee_id != $employeeId) {
                        continue;
                    }
                    // Check if this employee has any confirmed payment activities
                    $hasConfirmedActivities = $employee->activities->filter(function ($activity) use ($batchId) {
                        $matchesStatus = $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PAID && $activity->payment_date !== null;
                        $matchesBatch = !$batchId || $activity->payment_batch_id === $batchId;
                        return $matchesStatus && $matchesBatch;
                    })->isNotEmpty();
                    if (!$hasConfirmedActivities) {
                        continue;  // Skip employees with no confirmed activities
                    }
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
                        // Only include activities with "Paid" status and payment date
                        $matchesStatus = $activity->payment_status === AdvanceRequestActivity::PAYMENT_STATUS_PAID && $activity->payment_date !== null;
                        $matchesBatch = !$batchId || $activity->payment_batch_id === $batchId;
                        if (!$matchesStatus || !$matchesBatch) {
                            continue;
                        }
                        // Get activity type name
                        $activityTypeName = 'N/A';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }
                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                    $cropNames = $crops->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching crop names: ' . $e->getMessage());
                            }
                        }
                        // Get variety names
                        $varietyNames = [];
                        if ($activity->activity_variety_id) {
                            try {
                                $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }
                        // Get approval details for this activity
                        $approvalDetails = [];
                        try {
                            $approvals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $advanceRequest->submission_no)->orderBy('approval_level')->get();
                            foreach ($approvals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                // Get reverter details if reverted
                                $reverterDetails = null;
                                if ($approval->reverted_by) {
                                    $reverterDetails = $this->getEmployeeDetails($approval->reverted_by);
                                }
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        // Get payment confirmation details
                        $paymentConfirmationDetails = null;
                        $digiNextPaymentDetails = null;
                        if ($activity->payment_date) {
                            $paymentConfirmationDetails = ['confirmed_date' => $activity->payment_date, 'confirmed_amount' => $activity->payment_amount, 'confirmation_remark' => $activity->payment_remark, 'batch_id' => $activity->payment_batch_id];
                            // Extract reference from payment remark and get digi_next_payment_file details
                            if ($activity->payment_remark) {
                                // Extract reference from remark (format: "Payment confirmed via Excel upload - Ref: BT26013049926940")
                                if (preg_match('/Ref:\s*([A-Z0-9]+)/', $activity->payment_remark, $matches)) {
                                    $transactionRef = $matches[1];
                                    try {
                                        $digiNextRecord = DigiNextPaymentFile::where('transaction_refno', $transactionRef)->first();
                                        if ($digiNextRecord) {
                                            $digiNextPaymentDetails = ['id' => $digiNextRecord->id, 'record_no' => $digiNextRecord->record_no, 'transaction_refno' => $digiNextRecord->transaction_refno, 'amount' => $digiNextRecord->amount, 'beneficiary_name' => $digiNextRecord->beneficiary_name, 'beneficiary_account_number' => $digiNextRecord->beneficiary_account_number, 'beneficiary_aadhaar_number' => $digiNextRecord->beneficiary_aadhaar_number, 'bank_name' => $digiNextRecord->bank_name, 'ifsc_code' => $digiNextRecord->ifsc_code, 'destination_bank' => $digiNextRecord->destination_bank, 'debit_account_number' => $digiNextRecord->debit_account_number, 'payment_type' => $digiNextRecord->payment_type, 'value_date' => $digiNextRecord->value_date, 'rbi_utr_reference_number' => $digiNextRecord->rbi_utr_reference_number, 'credit_reference' => $digiNextRecord->credit_reference, 'currency' => $digiNextRecord->currency, 'transaction_status' => $digiNextRecord->transaction_status, 'error_description' => $digiNextRecord->error_description, 'import_date' => $digiNextRecord->import_date, 'import_batch_id' => $digiNextRecord->import_batch_id, 'created_at' => $digiNextRecord->created_at];
                                        }
                                    } catch (\Exception $e) {
                                        \Log::warning('Error fetching digi_next_payment_file details for reference ' . $transactionRef . ': ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have confirmed activities
                    if (!empty($activitiesData)) {
                        // Get employee ledger balance
                        $ledgerBalance = $this->getEmployeeLedgerBalance($employee->employee_id, $yearId);
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'ledger_balance' => $ledgerBalance, 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            // Get payment batch summary if batch_id is provided
            $batchSummary = null;
            if ($batchId) {
                $batchSummary = $this->getPaymentBatchSummary($batchId);
            }
            // Prepare response with pagination info (same format as getConfirmedPayments)
            $response = ['employees' => $allEmployees, 'pagination' => ['current_page' => (int) $page, 'per_page' => (int) $limit, 'total' => $totalCount, 'total_pages' => ceil($totalCount / $limit)], 'filters' => ['employee_id' => $employeeId, 'month' => $month, 'year_id' => $yearId, 'batch_id' => $batchId, 'status' => 'Paid']];
            if ($batchSummary) {
                $response['batch_summary'] = $batchSummary;
            }
            return $this->jsonSuccess($response, 'Employee confirmed payments retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve employee confirmed payments: ' . $e->getMessage());
        }
    }

    public function getPaymentSyncStatus(Request $request)
    {
        try {
            $batchId = $request->input('batch_id');
            // Get payment records statistics
            $paymentRecordsQuery = DigiNextPaymentFile::where('transaction_status', 'SUCCESS');
            if ($batchId) {
                $paymentRecordsQuery->where('import_batch_id', $batchId);
            }
            $totalPaymentRecords = $paymentRecordsQuery->count();
            $processedRecords = $paymentRecordsQuery->where('is_processed', true)->count();
            $matchedRecords = $paymentRecordsQuery->where('match_status', 'MATCHED')->count();
            $unmatchedRecords = $paymentRecordsQuery->where('match_status', 'UNMATCHED')->count();
            $pendingRecords = $paymentRecordsQuery->where('match_status', 'PENDING')->count();
            // Get advance requests statistics
            $readyActivitiesQuery = AdvanceRequestActivity::where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY);
            if ($batchId) {
                $readyActivitiesQuery->where('payment_batch_id', $batchId);
            }
            $totalReadyActivities = $readyActivitiesQuery->count();
            $paidActivities = AdvanceRequestActivity::where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->count();
            // Get unsettled payments count
            $unsettledPayments = DB::table('unsettled_payments')->where('status', 'Unsettled')->count();
            // Get recent sync activities
            $recentSyncActivities = DigiNextPaymentFile::where('is_processed', true)->orderBy('processed_at', 'desc')->limit(10)->get(['id', 'transaction_refno', 'beneficiary_name', 'amount', 'match_status', 'processed_at', 'processed_by']);
            $recentSyncData = [];
            foreach ($recentSyncActivities as $record) {
                $processorDetails = null;
                if ($record->processed_by) {
                    $processorDetails = $this->getEmployeeDetails($record->processed_by);
                }
                $recentSyncData[] = ['id' => $record->id, 'transaction_refno' => $record->transaction_refno, 'beneficiary_name' => $record->beneficiary_name, 'amount' => (float) $record->amount, 'match_status' => $record->match_status, 'processed_at' => $record->processed_at, 'processed_by_name' => $processorDetails['EmployeeName'] ?? 'Unknown'];
            }
            return $this->jsonSuccess(['payment_records' => ['total' => $totalPaymentRecords, 'processed' => $processedRecords, 'matched' => $matchedRecords, 'unmatched' => $unmatchedRecords, 'pending' => $pendingRecords, 'processing_rate' => $totalPaymentRecords > 0 ? round(($processedRecords / $totalPaymentRecords) * 100, 2) : 0, 'match_rate' => $processedRecords > 0 ? round(($matchedRecords / $processedRecords) * 100, 2) : 0], 'advance_requests' => ['total_ready' => $totalReadyActivities, 'total_paid' => $paidActivities, 'confirmation_rate' => $totalReadyActivities > 0 ? round(($paidActivities / ($totalReadyActivities + $paidActivities)) * 100, 2) : 0], 'unsettled_payments' => $unsettledPayments, 'recent_sync_activities' => $recentSyncData, 'last_sync_at' => $recentSyncActivities->first()->processed_at ?? null], 'Payment sync status retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve payment sync status: ' . $e->getMessage());
        }
    }

    public function getUnprocessedPaymentRecords(Request $request)
    {
        try {
            $limit = $request->input('limit', 50);
            $page = $request->input('page', 1);
            $batchId = $request->input('batch_id');
            // Build query for unprocessed payment records
            $query = DigiNextPaymentFile::where('transaction_status', 'SUCCESS')->where(function ($q) {
                $q->whereNull('is_processed')->orWhere('is_processed', false);
            });
            if ($batchId) {
                $query->where('import_batch_id', $batchId);
            }
            $totalCount = $query->count();
            $paymentRecords = $query->orderBy('created_at', 'desc')->limit($limit)->offset(($page - 1) * $limit)->get();
            $recordsData = [];
            foreach ($paymentRecords as $record) {
                $recordsData[] = ['id' => $record->id, 'record_no' => $record->record_no, 'transaction_refno' => $record->transaction_refno, 'beneficiary_name' => $record->beneficiary_name, 'amount' => (float) $record->amount, 'beneficiary_account_number' => $record->beneficiary_account_number, 'bank_name' => $record->bank_name, 'ifsc_code' => $record->ifsc_code, 'payment_type' => $record->payment_type, 'value_date' => $record->value_date, 'rbi_utr_reference_number' => $record->rbi_utr_reference_number, 'transaction_status' => $record->transaction_status, 'import_date' => $record->import_date, 'import_batch_id' => $record->import_batch_id, 'is_processed' => $record->is_processed, 'match_status' => $record->match_status, 'created_at' => $record->created_at];
            }
            return $this->jsonSuccess(['payment_records' => $recordsData, 'pagination' => ['current_page' => (int) $page, 'per_page' => (int) $limit, 'total' => $totalCount, 'total_pages' => ceil($totalCount / $limit)], 'filters' => ['batch_id' => $batchId]], 'Unprocessed payment records retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve unprocessed payment records: ' . $e->getMessage());
        }
    }

    public function getUnsettledPayments(Request $request)
    {
        try {
            $includeResolved = $request->input('include_resolved', false);
            $search          = $request->input('search');
            $perPage         = (int) $request->input('per_page', 50);

            // ── Primary source: advance_request_activities with payment_status = 'Unsettled'
            $query = AdvanceRequestActivity::with(['employee.advanceRequest'])
                ->whereNull('deleted_at');

            if ($includeResolved) {
                $query->whereIn('payment_status', ['Unsettled', 'Paid']);
            } else {
                $query->where('payment_status', 'Unsettled');
            }

            $activities = $query->orderByDesc('updated_at')->get();

            $unsettledData = [];

            foreach ($activities as $activity) {
                if (! $activity->employee) {
                    continue;
                }

                $employeeId      = $activity->employee->employee_id;
                $employeeDetails = $this->getEmployeeDetails($employeeId);

                // Account number from HRIMS
                $employeeAccountNo = null;
                try {
                    $employeeGeneral   = DB::connection('hrims')
                        ->table('hrm_employee_general')
                        ->where('EmployeeID', $employeeId)
                        ->first(['AccountNo']);
                    $employeeAccountNo = $employeeGeneral ? trim($employeeGeneral->AccountNo) : null;
                } catch (\Exception $e) {
                    $employeeAccountNo = null;
                }

                // All activities for this employee that are Unsettled or Paid
                $relatedActivities = AdvanceRequestActivity::with(['employee.advanceRequest'])
                    ->whereHas('employee', function ($q) use ($employeeId) {
                        $q->where('employee_id', $employeeId);
                    })
                    ->whereIn('payment_status', ['Unsettled', 'Paid', 'Released'])
                    ->whereNull('deleted_at')
                    ->orderByDesc('payment_date')
                    ->get()
                    ->map(function ($act) use ($activity) {
                        return [
                            'activity_id'         => $act->id,
                            'amount'              => (float) ($act->finance_approved_amount ?? $act->payment_amount ?? $act->amount ?? 0),
                            'payment_date'        => $act->payment_date,
                            'payment_status'      => $act->payment_status,
                            'payment_remark'      => $act->payment_remark ?? null,
                            'utr_number'          => $act->utr_number ?? null,
                            'bank_reference'      => $act->bank_reference ?? null,
                            'unsettled_reason'    => $act->payment_unsettled_reason ?? null,
                            'request_id'          => $act->employee->advance_request_id ?? null,
                            'finance_date'        => $act->finance_date,
                            'is_current_activity' => $act->id === $activity->id,
                        ];
                    })
                    ->toArray();

                // Also check unsettled_payments table for any linked record
                $unsettledRecord = DB::table('unsettled_payments')
                    ->where('activity_id', $activity->id)
                    ->where('status', 'Unsettled')
                    ->first();

                $approvedAmount = (float) ($activity->finance_approved_amount ?? $activity->payment_amount ?? $activity->amount ?? 0);

                $matchingActivities = array_filter($relatedActivities, function ($act) use ($approvedAmount) {
                    return abs($act['amount'] - $approvedAmount) <= 0.01;
                });

                $unsettledData[] = [
                    // Core activity fields
                    'activity_id'             => $activity->id,
                    'request_id'              => $activity->employee->advance_request_id ?? null,
                    'payment_status'          => $activity->payment_status,
                    'payment_unsettled_reason'=> $activity->payment_unsettled_reason ?? null,
                    'utr_number'              => $activity->utr_number ?? null,
                    'bank_reference'          => $activity->bank_reference ?? null,
                    'payment_date'            => $activity->payment_date,
                    'payment_released_date'   => $activity->payment_released_date ?? null,
                    'finance_date'            => $activity->finance_date ?? null,
                    'approved_amount'         => $approvedAmount,
                    'payment_remark'          => $activity->payment_remark ?? null,
                    // Unsettled payments table record (if exists)
                    'unsettled_record_id'     => $unsettledRecord->id ?? null,
                    'transaction_refno'       => $unsettledRecord->transaction_refno ?? null,
                    'excel_amount'            => (float) ($unsettledRecord->excel_amount ?? 0),
                    'db_amount'               => (float) ($unsettledRecord->db_amount ?? $approvedAmount),
                    'amount_difference'       => (float) (($unsettledRecord->excel_amount ?? 0) - ($unsettledRecord->db_amount ?? $approvedAmount)),
                    'unsettled_reason'        => $unsettledRecord->reason ?? $activity->payment_unsettled_reason ?? null,
                    'unsettled_status'        => $unsettledRecord->status ?? 'Unsettled',
                    'created_at'              => $activity->created_at,
                    'updated_at'              => $activity->updated_at,
                    // Employee / account info
                    'activity_info' => [
                        'activity_id'            => $activity->id,
                        'employee_id'            => $employeeId,
                        'employee_name'          => $employeeDetails['EmployeeName'] ?? 'Unknown',
                        'employee_code'          => $employeeDetails['EmployeeCode'] ?? 'N/A',
                        'hrims_account_no'       => $employeeAccountNo,
                        'approved_amount'        => $approvedAmount,
                        'request_id'             => $activity->employee->advance_request_id ?? null,
                        'current_payment_status' => $activity->payment_status,
                        'finance_date'           => $activity->finance_date ?? null,
                    ],
                    // Related activities for this employee
                    'all_employee_payments'       => array_values($relatedActivities),
                    'all_employee_payments_count' => count($relatedActivities),
                    'matching_payment_records'    => array_values($matchingActivities),
                    'matching_records_count'      => count($matchingActivities),
                    'has_exact_match'             => count($matchingActivities) > 0,
                ];
            }

            $unsettledCount = collect($unsettledData)->where('payment_status', 'Unsettled')->count();
            $resolvedCount  = collect($unsettledData)->where('payment_status', 'Paid')->count();

            return $this->jsonSuccess([
                'unsettled_payments'  => $unsettledData,
                'total_count'         => count($unsettledData),
                'unsettled_count'     => $unsettledCount,
                'resolved_count'      => $resolvedCount,
                'total_db_amount'     => collect($unsettledData)->sum('approved_amount'),
                'status_filter'       => $includeResolved ? 'all' : 'Unsettled',
                'include_resolved'    => $includeResolved,
            ], 'Unsettled payments retrieved successfully');

        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve unsettled payments: ' . $e->getMessage());
        }
    }

    private function getMatchType($isAmountMatch, $isPending)
    {
        if ($isAmountMatch && $isPending) {
            return 'perfect_match';  // Green - can be matched
        } elseif ($isAmountMatch && !$isPending) {
            return 'already_matched';  // Gray - already processed
        } elseif (!$isAmountMatch && $isPending) {
            return 'amount_mismatch';  // Yellow - pending but wrong amount
        } else {
            return 'not_applicable';  // Red - neither matches
        }
    }

    /**
     * Settle an unsettled payment manually.
     *
     * POST /api/advance/finance/settle-unsettled-payment
     *
     * Required body:
     *   activity_id    int     The advance_request_activities.id to settle
     *   payment_amount float   Actual amount paid
     *   payment_date   date    Date payment was received (Y-m-d)
     *
     * Optional body:
     *   utr_number     string  Bank UTR / reference number
     *   bank_reference string  Bank reference
     *   payment_remark string  Free-text remark
     */
    public function settleUnsettledPayment(Request $request)
    {
        $request->validate([
            'activity_id'    => 'required|integer',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $activity = AdvanceRequestActivity::with(['employee.advanceRequest'])
                ->find($request->activity_id);

            if (! $activity) {
                DB::rollback();
                return $this->jsonError('Activity not found');
            }

            $paymentAmount = (float) $request->payment_amount;
            $paymentDate   = $request->payment_date;
            $utrNumber     = $request->input('utr_number');
            $bankReference = $request->input('bank_reference');
            $remark        = $request->input('payment_remark')
                ?? 'Manually settled'
                .($utrNumber ? " | UTR: {$utrNumber}" : '');

            // Mark activity as Paid
            $activity->update([
                'payment_status'           => AdvanceRequestActivity::PAYMENT_STATUS_PAID,
                'payment_amount'           => $paymentAmount,
                'payment_date'             => $paymentDate,
                'payment_remark'           => $remark,
                'utr_number'               => $utrNumber,
                'bank_reference'           => $bankReference,
                'payment_unsettled_reason' => null, // clear the unsettled reason
            ]);

            // Mark any linked unsettled_payments record as Resolved
            DB::table('unsettled_payments')
                ->where('activity_id', $activity->id)
                ->where('status', 'Unsettled')
                ->update([
                    'status'     => 'Resolved',
                    'reason'     => 'Manually settled via API',
                    'updated_at' => now(),
                ]);

            // Check if all finance-approved activities in the request are now paid
            $advanceRequest = $activity->employee->advanceRequest ?? null;
            if ($advanceRequest) {
                $this->checkAndUpdateRequestPaymentStatus($advanceRequest);
            }

            DB::commit();

            return $this->jsonSuccess([
                'activity_id'    => $activity->id,
                'payment_status' => $activity->payment_status,
                'payment_amount' => $paymentAmount,
                'payment_date'   => $paymentDate,
                'utr_number'     => $utrNumber,
                'bank_reference' => $bankReference,
                'request_status' => $advanceRequest ? $advanceRequest->fresh()->current_status : null,
            ], 'Payment settled successfully');

        } catch (Exception $e) {
            DB::rollback();
            return $this->jsonError('Failed to settle payment: '.$e->getMessage());
        }
    }

    private function cleanupDuplicateUnsettledRecords()
    {
        try {
            DB::transaction(function () {
                // Find activities that have multiple unsettled records
                $duplicateActivities = DB::table('unsettled_payments')
                    ->select('activity_id', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('activity_id')
                    ->where('status', 'Unsettled')
                    ->groupBy('activity_id')
                    ->having('count', '>', 1)
                    ->lockForUpdate()  // Prevent race conditions during cleanup
                    ->get();
                $totalCleaned = 0;
                foreach ($duplicateActivities as $duplicate) {
                    // Keep the most recent record and delete the older ones
                    $records = DB::table('unsettled_payments')->where('activity_id', $duplicate->activity_id)->where('status', 'Unsettled')->orderBy('created_at', 'desc')->get();
                    // Keep the first (most recent) record and delete the rest
                    $recordsToDelete = $records->slice(1);
                    foreach ($recordsToDelete as $record) {
                        DB::table('unsettled_payments')->where('id', $record->id)->delete();
                        $totalCleaned++;
                    }
                    \Log::info('Cleaned up ' . $recordsToDelete->count() . " duplicate records for activity_id: {$duplicate->activity_id}");
                }
                \Log::info("Total cleanup completed: Cleaned up {$totalCleaned} duplicate unsettled records for " . $duplicateActivities->count() . ' activities');
            });
        } catch (\Exception $e) {
            \Log::error('Error cleaning up duplicate unsettled records: ' . $e->getMessage());
            throw $e;  // Re-throw to handle in calling method
        }
    }

    public function cleanupUnsettledDuplicates(Request $request)
    {
        try {
            DB::beginTransaction();
            // First, clean up duplicates using the standard method
            $this->cleanupDuplicateUnsettledRecords();
            // Additional cleanup for legacy duplicate patterns
            // Handle cases where old transaction references were used (UNMATCHED_{activity_id}_{timestamp})
            $legacyDuplicates = DB::table('unsettled_payments')->select('activity_id', DB::raw('COUNT(*) as count'))->whereNotNull('activity_id')->where('status', 'Unsettled')->where('transaction_refno', 'LIKE', 'UNMATCHED_%')->where('transaction_refno', 'NOT LIKE', 'UNMATCHED_ACTIVITY_%')->groupBy('activity_id')->having('count', '>', 1)->get();
            $legacyCleaned = 0;
            foreach ($legacyDuplicates as $legacy) {
                // Keep the most recent record and delete older ones
                $records = DB::table('unsettled_payments')->where('activity_id', $legacy->activity_id)->where('status', 'Unsettled')->where('transaction_refno', 'LIKE', 'UNMATCHED_%')->where('transaction_refno', 'NOT LIKE', 'UNMATCHED_ACTIVITY_%')->orderBy('created_at', 'desc')->get();
                // Keep the first (most recent) record and delete the rest
                $recordsToDelete = $records->slice(1);
                foreach ($recordsToDelete as $record) {
                    DB::table('unsettled_payments')->where('id', $record->id)->delete();
                    $legacyCleaned++;
                }
                // Update the remaining record to use the new transaction reference format
                if ($records->isNotEmpty()) {
                    $remainingRecord = $records->first();
                    DB::table('unsettled_payments')->where('id', $remainingRecord->id)->update(['transaction_refno' => 'UNMATCHED_ACTIVITY_' . $legacy->activity_id, 'updated_at' => now()]);
                }
                \Log::info('Legacy cleanup: Cleaned up ' . $recordsToDelete->count() . " duplicate records for activity_id: {$legacy->activity_id}");
            }
            DB::commit();
            // Get count after cleanup
            $remainingCount = DB::table('unsettled_payments')->where('status', 'Unsettled')->count();
            return $this->jsonSuccess(['remaining_unsettled_count' => $remainingCount, 'legacy_duplicates_cleaned' => $legacyCleaned, 'legacy_activities_processed' => $legacyDuplicates->count(), 'cleanup_completed' => true], 'Duplicate unsettled records cleaned up successfully');
        } catch (Exception $e) {
            DB::rollback();
            return $this->jsonError('Failed to cleanup duplicate unsettled records: ' . $e->getMessage());
        }
    }

    public function resolveDiscrepancy(Request $request)
    {
        try {
            $userId             = $request->get('user_id');
            $activityId         = $request->input('activity_id');
            $userPaymentAmount  = $request->input('payment_amount');
            $userPaymentDate    = $request->input('payment_date');
            $userPaymentRemark  = $request->input('payment_remark');
            $utrNumber          = $request->input('utr_reference') ?? $request->input('utr_number');
            $bankReference      = $request->input('bank_reference');

            if (! $activityId) {
                return $this->jsonError('activity_id is required');
            }

            if (! $userPaymentAmount || floatval($userPaymentAmount) <= 0) {
                return $this->jsonError('payment_amount is required and must be greater than 0');
            }

            if (! $userPaymentDate) {
                return $this->jsonError('payment_date is required');
            }

            DB::beginTransaction();

            // Get the activity — no status restriction, any status can be resolved
            $activity = AdvanceRequestActivity::with(['employee.advanceRequest'])->find($activityId);

            if (! $activity) {
                DB::rollback();
                return $this->jsonError('Activity not found');
            }

            $paymentAmount = floatval($userPaymentAmount);
            $paymentDate   = $userPaymentDate;
            $paymentRemark = $userPaymentRemark
                ?: 'Manually resolved'
                .($utrNumber ? " | UTR: {$utrNumber}" : '');

            // Update activity to PAID
            $activity->update([
                'payment_status'           => AdvanceRequestActivity::PAYMENT_STATUS_PAID,
                'payment_amount'           => $paymentAmount,
                'payment_date'             => $paymentDate,
                'payment_remark'           => $paymentRemark,
                'utr_number'               => $utrNumber,
                'bank_reference'           => $bankReference,
                'payment_unsettled_reason' => null,
            ]);

            // Resolve any linked unsettled_payments record
            $this->removeUnsettledRecord($activityId, 'Manually resolved via resolve-discrepancy');

            // Close the advance request if all finance-approved activities are now paid
            $advanceRequest = $activity->employee->advanceRequest ?? null;
            if ($advanceRequest) {
                $this->checkAndUpdateRequestPaymentStatus($advanceRequest);
            }

            DB::commit();

            return $this->jsonSuccess([
                'activity_id'           => $activity->id,
                'payment_status'        => $activity->payment_status,
                'payment_amount'        => $paymentAmount,
                'payment_date'          => $paymentDate,
                'utr_number'            => $utrNumber,
                'advance_request_status'=> $advanceRequest ? $advanceRequest->fresh()->current_status : null,
            ], 'Payment discrepancy resolved successfully');

        } catch (Exception $e) {
            DB::rollback();
            return $this->jsonError('Failed to resolve discrepancy: '.$e->getMessage());
        }
    }

    private function calculateNameSimilarity($name1, $name2)
    {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));
        if (empty($name1) || empty($name2)) {
            return 0;
        }
        $maxLength = max(strlen($name1), strlen($name2));
        if ($maxLength === 0) {
            return 1;
        }
        $distance = levenshtein($name1, $name2);
        return 1 - ($distance / $maxLength);
    }

    private function checkAndUpdateRequestPaymentStatus($advanceRequest)
    {
        $totalActivities = $advanceRequest->activities()->where('finance_status', AdvanceRequestActivity::FINANCE_STATUS_APPROVED)->count();
        $paidActivities = $advanceRequest->activities()->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID)->count();
        if ($totalActivities > 0 && $paidActivities === $totalActivities) {
            $advanceRequest->update(['current_status' => AdvanceRequest::STATUS_CLOSED]);
        }
    }

    private function cleanAmount($amount)
    {
        // Handle null or empty values
        if ($amount === null || $amount === '') {
            return 0.0;
        }
        // Convert to string first to handle any format
        $amount = (string) $amount;
        // Remove any non-numeric characters except decimal point and minus sign
        $amount = preg_replace('/[^0-9.-]/', '', $amount);
        // Convert to float and round to 2 decimal places
        return round(floatval($amount), 2);
    }

    public function debugPaymentMatching(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $limit = $request->input('limit', 10);
            // Get ready activities
            $readyActivities = AdvanceRequestActivity::with(['employee'])->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY)->limit($limit)->get();
            // Get unprocessed payment records
            $paymentRecords = DigiNextPaymentFile::where('match_status', 'PENDING')->where(function ($query) {
                $query->whereNull('is_processed')->orWhere('is_processed', false);
            })->limit($limit)->get();
            $debugInfo = [];
            foreach ($paymentRecords as $paymentRecord) {
                $beneficiaryName = trim($paymentRecord->beneficiary_name ?? '');
                $amount = floatval($paymentRecord->amount ?? 0);
                $transactionRef = trim($paymentRecord->transaction_refno ?? '');
                $paymentDebug = ['payment_record_id' => $paymentRecord->id, 'transaction_ref' => $transactionRef, 'beneficiary_name' => $beneficiaryName, 'amount' => $amount, 'potential_matches' => []];
                foreach ($readyActivities as $activity) {
                    $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                    $employeeName = $employeeDetails['EmployeeName'] ?? '';
                    $activityAmount = $this->cleanAmount($activity->finance_approved_amount);
                    $paymentAmount = $this->cleanAmount($amount);
                    $amountDiff = abs($activityAmount - $paymentAmount);
                    $amountMatch = $amountDiff <= 0.01;
                    $nameSimilarity = $this->calculateNameSimilarity($beneficiaryName, $employeeName);
                    $nameMatch = $nameSimilarity > 0.8;
                    $paymentDebug['potential_matches'][] = ['activity_id' => $activity->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeName, 'activity_amount' => $activityAmount, 'payment_amount' => $paymentAmount, 'amount_difference' => $amountDiff, 'amount_match' => $amountMatch, 'name_similarity' => round($nameSimilarity, 4), 'name_match' => $nameMatch, 'would_match' => $amountMatch && $nameMatch, 'payment_status' => $activity->payment_status];
                }
                // Sort potential matches by best similarity
                usort($paymentDebug['potential_matches'], function ($a, $b) {
                    if ($a['would_match'] && !$b['would_match'])
                        return -1;
                    if (!$a['would_match'] && $b['would_match'])
                        return 1;
                    return $b['name_similarity'] <=> $a['name_similarity'];
                });
                $debugInfo[] = $paymentDebug;
            }
            return $this->jsonSuccess(['total_ready_activities' => $readyActivities->count(), 'total_payment_records' => $paymentRecords->count(), 'debug_info' => $debugInfo, 'matching_criteria' => ['amount_tolerance' => '0.01', 'name_similarity_threshold' => '0.8', 'both_criteria_required' => true]], 'Payment matching debug information retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to get debug information: ' . $e->getMessage());
        }
    }

    /*
     * Allows manual matching of unsettled expense records with payment records
     */
    public function manualExpenseSettlement(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $activityId = $request->input('activity_id');  // MonthExpenseFinal ID
            $transactionRef = $request->input('transaction_ref');  // Payment record transaction reference
            // Optional user inputs (will override payment record values if provided)
            $userPaymentAmount = $request->input('payment_amount');
            $userPaymentDate = $request->input('payment_date');
            $userPaymentRemark = $request->input('payment_remark');
            // Validate required parameters
            if (!$yearId || !$activityId || !$transactionRef) {
                return $this->jsonError('year_id, activity_id, and transaction_ref are required');
            }
            DB::beginTransaction();
            // Get the correct table name based on yearId
            $tableName = "y{$yearId}_monthexpensefinal";
            // Get the expense activity
            $activity = DB::connection(session('expense_connection'))->table($tableName)->where('id', $activityId)->first();
            if (!$activity) {
                DB::rollback();
                return $this->jsonError('Expense activity not found');
            }
            // Check if activity is in InstructedPayment status
            if ($activity->payment_status !== 'InstructedPayment') {
                DB::rollback();
                return $this->jsonError('Activity is not in InstructedPayment status. Current status: ' . $activity->payment_status);
            }
            // Get the payment record
            $paymentRecord = DigiNextPaymentFile::where('transaction_refno', $transactionRef)->first();
            if (!$paymentRecord) {
                DB::rollback();
                return $this->jsonError('Payment record not found with transaction reference: ' . $transactionRef);
            }
            // Use user-provided values if available, otherwise use payment record values
            $paymentAmount = $userPaymentAmount !== null ? floatval($userPaymentAmount) : floatval($paymentRecord->amount ?? 0);
            $paymentDate = $userPaymentDate ?: ($paymentRecord->value_date ?? now()->toDateString());
            // Get amounts for remark building
            $employeeId = $activity->EmployeeID;
            $financeAmount = floatval($activity->Finance_Amount ?? 0);
            $advanceAmount = floatval($activity->advance_payment_amount ?? 0);
            $daAmount = floatval($activity->da_payment_amount ?? 0);
            if ($daAmount == 0) {
                try {
                    $claimsTable = "y{$yearId}_expenseclaims";
                    $daRow = DB::connection(session('expense_connection'))->selectOne("
                        SELECT 
                            COALESCE(SUM(CASE WHEN FinancedTAmt > 0 THEN FinancedTAmt ELSE 0 END), 0) as da_fin,
                            COALESCE(SUM(CASE WHEN ApprTAmt > 0 THEN ApprTAmt ELSE 0 END), 0) as da_appr,
                            COALESCE(SUM(CASE WHEN VerifyTAmt > 0 THEN VerifyTAmt ELSE 0 END), 0) as da_ver,
                            COALESCE(SUM(CASE WHEN FilledTAmt > 0 THEN FilledTAmt ELSE 0 END), 0) as da_fill
                        FROM {$claimsTable}
                        WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                    ", [$employeeId, $activity->Month]);
                    if ($daRow) {
                        $daAmount = floatval($daRow->da_fin > 0 ? $daRow->da_fin : ($daRow->da_appr > 0 ? $daRow->da_appr : ($daRow->da_ver > 0 ? $daRow->da_ver : ($daRow->da_fill ?? 0))));
                    }
                } catch (\Exception $e) {}
            }
            $adjustActivityAmount = floatval($activity->adjust_activity_amount ?? 0);
            $adjustExpenseAmount = floatval($activity->adjust_expense_amount ?? 0);
            $ledgerBalanceAtApproval = floatval($activity->ledger_balance_at_approval ?? 0);
            // Calculate total adjustments
            $totalAdjustments = $adjustActivityAmount + $adjustExpenseAmount;
            if ($daAmount == 0) {
                $appr = floatval($activity->Approved_Amount ?? ($activity->Verified_Amount ?? 0));
                $paid = floatval($paymentAmount);
                if ($appr > 0 && $paid > 0 && ($appr - $totalAdjustments - $paid) > 0) {
                    $daAmount = round($appr - $totalAdjustments - $paid, 2);
                }
            }
            // Build the payment remark
            $companyId = session('company_id');
            if ($companyId == 11 && function_exists('formatVvnrPaymentRemark')) {
                $paymentRemark = $userPaymentRemark ?: formatVvnrPaymentRemark($activity->EmployeeID, $activity->Month, $yearId, $paymentAmount, '', $activity);
            } else {
                $paymentRemark = $userPaymentRemark ?: "Paid Rs. {$paymentAmount}";
                if ($totalAdjustments == 0) {
                    $paymentRemark .= ', No adjustments';
                } else {
                    $adjustmentParts = [];
                    if ($adjustActivityAmount > 0) {
                        $adjustmentParts[] = "Activity Adv. Rs. {$adjustActivityAmount}";
                    }
                    if ($adjustExpenseAmount > 0) {
                        $adjustmentParts[] = "Expense Adv. Rs. {$adjustExpenseAmount}";
                    }
                    if (!empty($adjustmentParts)) {
                        $paymentRemark .= ', Adjusted with ' . implode(', ', $adjustmentParts);
                    }
                }
                if ($daAmount > 0) {
                    $daStr = (floor($daAmount) == $daAmount) ? number_format($daAmount, 0, '', '') : number_format($daAmount, 2);
                    $paymentRemark .= ", DA Rs. {$daStr}";
                }
            }
            $paymentRemark .= ' (Manual Settlement)';
            // Additional remarks
            $finPayRemark1 = "Bank Ref: {$transactionRef} | Account: " . ($paymentRecord->beneficiary_account_number ?? 'N/A') . ' (Manual)';
            $finPayRemark2 = "Finance Amt: Rs. {$financeAmount}";
            if ($totalAdjustments > 0) {
                $finPayRemark2 .= " | Total Adj: Rs. {$totalAdjustments}";
            }
            $finPayRemark2 .= " | Net Payment: Rs. {$paymentAmount} (Manual)";
            $finAdvancePay = ($advanceAmount > 0) ? 'Yes' : 'No';
            // Update MonthExpenseFinal record
            DB::connection(session('expense_connection'))->table($tableName)->where('id', $activityId)->update(['payment_status' => 'ConfirmedPayment', 'payment_date' => $paymentDate, 'payment_confirmed_date' => now()->toDateString(), 'Fin_AdvancePay' => $finAdvancePay, 'Fin_PayOption' => 'Bank', 'Fin_PayBy' => $userId, 'Fin_PayAmt' => $paymentAmount, 'Fin_PayDate' => $paymentDate, 'Fin_PayRemark' => $paymentRemark, 'Fin_PayRemark_1' => $finPayRemark1, 'Fin_PayRemark_2' => $finPayRemark2]);
            // Update employee_ledger_balances
            if ($employeeId && $yearId) {
                try {
                    $ledgerRecord = DB::table('employee_ledger_balances')->where('employee_id', $employeeId)->where('year_id', $yearId)->first();
                    if ($ledgerRecord) {
                        $oldBalance = floatval($ledgerRecord->balance ?? 0);
                        $newBalance = $ledgerBalanceAtApproval;
                        $ledgerRemark = "Payment confirmed (Manual) - Month Expense Final ID: {$activityId}";
                        $ledgerRemark .= " | Old Balance: Rs. {$oldBalance}";
                        $ledgerRemark .= " | Payment: Rs. {$paymentAmount}";
                        $ledgerRemark .= " | New Balance: Rs. {$newBalance}";
                        $ledgerRemark .= " | Balance at Approval: Rs. {$ledgerBalanceAtApproval}";
                        $ledgerRemark .= " | Ref: {$transactionRef}";
                        DB::table('employee_ledger_balances')->where('employee_id', $employeeId)->where('year_id', $yearId)->update(['balance' => $newBalance, 'entry_date' => now()->toDateString(), 'remark' => $ledgerRemark, 'updated_at' => now()]);
                    } else {
                        $newBalance = 0 - $paymentAmount;
                        $ledgerRemark = "Initial Payment confirmed (Manual) - Month Expense Final ID: {$activityId}";
                        $ledgerRemark .= " | Payment: Rs. {$paymentAmount}";
                        $ledgerRemark .= " | New Balance: Rs. {$newBalance}";
                        $ledgerRemark .= " | Balance at Approval: Rs. {$ledgerBalanceAtApproval}";
                        $ledgerRemark .= " | Ref: {$transactionRef}";
                        $companyId = 1;
                        try {
                            $employeeCompany = DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $employeeId)->first(['CompanyId']);
                            if ($employeeCompany && $employeeCompany->CompanyId) {
                                $companyId = $employeeCompany->CompanyId;
                            }
                        } catch (\Exception $e) {
                            // Use default company_id
                        }
                        DB::table('employee_ledger_balances')->insert(['year_id' => $yearId, 'employee_id' => $employeeId, 'company_id' => $companyId, 'balance' => $newBalance, 'entry_date' => now()->toDateString(), 'fixed_imprest' => 0.0, 'fixed_imprest_date' => null, 'grade' => null, 'remark' => $ledgerRemark, 'created_at' => now(), 'updated_at' => now()]);
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to update/insert employee ledger balance for Employee ID {$employeeId}: " . $e->getMessage());
                }
            }
            // Update payment record as processed and matched
            $paymentRecord->update(['is_processed' => true, 'matched_activity_id' => $activityId, 'processed_at' => now(), 'processed_by' => $userId, 'match_status' => 'MATCHED', 'application' => 'Xeasy_Expense', 'match_remarks' => "Manually matched with MonthExpenseFinal ID {$activityId} via manual-expense-settlement"]);
            // Remove unsettled record if exists
            DB::table('unsettled_payments')->where('activity_id', $activityId)->where('application', 'Xeasy_Expense')->where('status', 'Unsettled')->update(['status' => 'Resolved', 'reason' => 'Manually resolved via manual-expense-settlement', 'updated_at' => now()]);
            DB::commit();
            return $this->jsonSuccess(['activity_id' => $activityId, 'transaction_ref' => $transactionRef, 'payment_amount' => $paymentAmount, 'payment_date' => $paymentDate, 'employee_id' => $employeeId, 'year_id' => $yearId], 'Expense payment manually settled successfully');
        } catch (Exception $e) {
            DB::rollback();
            return $this->jsonError('Failed to manually settle expense payment: ' . $e->getMessage());
        }
    }

    public function getExportHistory(Request $request)
    {
        try {
            $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $activityId = $request->input('activity_id');
            $batchId = $request->input('batch_id');
            $limit = $request->input('limit', 50);
            $page = $request->input('page', 1);
            // Build query for export logs
            $query = \App\Models\AdvanceExportLog::with(['activity.employee'])->orderBy('exported_at', 'desc');
            // Apply filters
            if ($activityId) {
                $query->where('activity_id', $activityId);
            }
            if ($batchId) {
                $query->where('export_batch_id', $batchId);
            }
            if ($fromDate) {
                $query->where('exported_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->where('exported_at', '<=', $toDate);
            }
            if ($yearId) {
                try {
                    $financialYear = DB::table('financial_years')->where('id', $yearId)->first();
                    if ($financialYear) {
                        $query->whereBetween('exported_at', [$financialYear->start_date, $financialYear->end_date]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error filtering by year_id: ' . $e->getMessage());
                }
            }
            $totalCount = $query->count();
            $exportLogs = $query->limit($limit)->offset(($page - 1) * $limit)->get();
            // Transform data
            $exportHistory = [];
            foreach ($exportLogs as $log) {
                $employeeDetails = $this->getEmployeeDetails($log->activity->employee->employee_id);
                $exporterDetails = null;
                if ($log->exported_by) {
                    $exporterDetails = $this->getEmployeeDetails($log->exported_by);
                }
                $exportHistory[] = ['log_id' => $log->id, 'activity_id' => $log->activity_id, 'employee_id' => $log->activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'export_amount' => (float) $log->export_amount, 'export_batch_id' => $log->export_batch_id, 'export_type' => $log->export_type, 'export_status' => $log->export_status, 'export_remarks' => $log->export_remarks, 'file_name' => $log->file_name, 'total_activities_in_export' => $log->total_activities_in_export, 'exported_at' => $log->exported_at, 'exported_by' => $log->exported_by, 'exporter_name' => $exporterDetails['EmployeeName'] ?? 'Unknown', 'payment_status' => $log->activity->payment_status, 'payment_batch_id' => $log->activity->payment_batch_id];
            }
            // Get statistics
            $stats = ['total_export_requests' => \App\Models\AdvanceExportLog::count(), 'total_exported_activities' => AdvanceRequestActivity::where('is_exported', true)->count(), 'total_not_exported' => AdvanceRequestActivity::where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY)->where(function ($q) {
                $q->where('is_exported', false)->orWhereNull('is_exported');
            })->count(), 'total_export_amount' => \App\Models\AdvanceExportLog::sum('export_amount'), 'unique_batches' => \App\Models\AdvanceExportLog::distinct('export_batch_id')->count('export_batch_id'), 'today_exports' => \App\Models\AdvanceExportLog::whereDate('exported_at', now()->toDateString())->count(), 'this_month_exports' => \App\Models\AdvanceExportLog::whereMonth('exported_at', now()->month)->whereYear('exported_at', now()->year)->count()];
            return $this->jsonSuccess(['export_history' => $exportHistory, 'statistics' => $stats, 'pagination' => ['current_page' => (int) $page, 'per_page' => (int) $limit, 'total' => $totalCount, 'total_pages' => ceil($totalCount / $limit)], 'filters' => ['year_id' => $yearId, 'from_date' => $fromDate, 'to_date' => $toDate, 'activity_id' => $activityId, 'batch_id' => $batchId]], 'Export history retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve export history: ' . $e->getMessage());
        }
    }

    public function resetExportStatus(Request $request)
    {
        try {
            $activityIds = $request->input('activity_ids', []);
            $userId = $request->get('user_id');
            if (empty($activityIds)) {
                return $this->jsonError('activity_ids are required');
            }
            DB::transaction(function () use ($activityIds, $userId) {
                $activities = AdvanceRequestActivity::whereIn('id', $activityIds)->get();
                foreach ($activities as $activity) {
                    $activity->resetExportStatus();
                    // Log the reset action
                    \Log::info("Export status reset for activity {$activity->id} by user {$userId}");
                }
            });
            return $this->jsonSuccess(['reset_count' => count($activityIds), 'activity_ids' => $activityIds], 'Export status reset successfully for ' . count($activityIds) . ' activities');
        } catch (Exception $e) {
            return $this->jsonError('Failed to reset export status: ' . $e->getMessage());
        }
    }

    public function getActivityExportLogs(Request $request, $activityId)
    {
        try {
            $activity = AdvanceRequestActivity::with(['employee'])->findOrFail($activityId);
            // Get all export logs for this activity
            $exportLogs = \App\Models\AdvanceExportLog::where('activity_id', $activityId)->orderBy('exported_at', 'desc')->get();
            $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
            // Transform logs
            $logs = [];
            foreach ($exportLogs as $log) {
                $exporterDetails = null;
                if ($log->exported_by) {
                    $exporterDetails = $this->getEmployeeDetails($log->exported_by);
                }
                $logs[] = ['log_id' => $log->id, 'export_batch_id' => $log->export_batch_id, 'export_amount' => (float) $log->export_amount, 'export_type' => $log->export_type, 'export_status' => $log->export_status, 'export_remarks' => $log->export_remarks, 'file_name' => $log->file_name, 'total_activities_in_export' => $log->total_activities_in_export, 'exported_at' => $log->exported_at, 'exported_by' => $log->exported_by, 'exporter_name' => $exporterDetails['EmployeeName'] ?? 'Unknown', 'exporter_code' => $exporterDetails['EmployeeCode'] ?? 'N/A'];
            }
            return $this->jsonSuccess(['activity_id' => $activity->id, 'employee_id' => $activity->employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'current_status' => ['is_exported' => $activity->is_exported, 'export_count' => $activity->export_count, 'first_export_date' => $activity->first_export_date, 'last_export_date' => $activity->last_export_date, 'payment_status' => $activity->payment_status], 'export_logs' => $logs, 'total_exports' => count($logs)], 'Activity export logs retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve activity export logs: ' . $e->getMessage());
        }
    }
}
