<?php
namespace App\Http\Controllers\Api\Advance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advance\ApproveRequest;
use App\Http\Requests\Advance\RejectRequest;
use App\Http\Requests\Advance\RevertRequest;
use App\Mail\AdvanceRequestNotification;
use App\Models\AdvanceActivityApproval;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestAction;
use App\Models\AdvanceRequestActivity;
use App\Models\DigiNextPaymentFile;
use App\Services\CentralNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Exception;

class AdvanceApprovalController extends Controller
{
    private CentralNotificationService $centralNotification;

    public function __construct()
    {
        $this->centralNotification = new CentralNotificationService();
    }

    public function rejected(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            // Get advance requests that have been rejected by this user
            $requestIds = DB::table('advance_activity_approvals')->join('advance_request_activities', 'advance_activity_approvals.advance_request_activity_id', '=', 'advance_request_activities.id')->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')->where('advance_activity_approvals.approver_id', $userId)->where('advance_activity_approvals.approver_status', AdvanceActivityApproval::STATUS_REJECTED)->whereNull('advance_request_activities.deleted_at')->pluck('advance_request_employees.advance_request_id')->unique();
            if ($requestIds->isEmpty()) {
                return $this->jsonSuccess([], 'No rejected requests found');
            }
            // Get the actual requests with their relationships
            $requests = AdvanceRequest::with(['employees.activities.approvals' => function ($query) use ($userId) {
                $query->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED);
            }, 'employees.activities' => function ($query) {
                $query->whereNull('deleted_at');
            }, 'actions'])->whereIn('id', $requestIds)->orderBy('last_submitted_at', 'desc')->get();
            // Transform the data to match the expected format - employees with req_data inside
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = '';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = '';
                $advanceTypeCode = '';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceType = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        $advanceTypeName = $advanceType->type_name ?? '';
                        $advanceTypeCode = $advanceType->type_code ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any rejected activities by this approver
                    $hasRejectedActivities = $employee->activities->filter(function ($activity) use ($userId) {
                        return $activity->approvals->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED)->isNotEmpty();
                    })->isNotEmpty();
                    if (!$hasRejectedActivities) {
                        continue;  // Skip employees with no rejected activities by this approver
                    }
                    // Prepare request data object with employee-specific history
                    $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                    $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approver_advance_history' => $this->getApproverAdvanceHistory($userId, $advanceRequest->id, $employee->employee_id)];
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee (only rejected ones by this approver)
                    $activitiesData = [];
                    $totalRejectedAmount = 0;
                    $rejectionLevel = null;
                    $rejectedDate = null;
                    foreach ($employee->activities as $activity) {
                        // Check if this activity has rejected status by this approver
                        $rejectedApproval = $activity->approvals->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED)->first();
                        if (!$rejectedApproval) {
                            continue;  // Skip activities not rejected by this approver
                        }
                        // Set the rejection level and date (should be same for all activities of this employee)
                        if ($rejectionLevel === null) {
                            $rejectionLevel = $rejectedApproval->approval_level;
                            $rejectedDate = $rejectedApproval->approver_date;
                        }
                        $totalRejectedAmount += $activity->amount;
                        // Get activity type name
                        $activityTypeName = '';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? '';
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
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'rejection_reason' => $rejectedApproval->approver_remark, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have rejected activities
                    if (!empty($activitiesData)) {
                        // Add employee with req_data inside
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? '', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? '', 'designation' => $employeeDetails['Designation'] ?? '', 'total_rejected_amount' => $totalRejectedAmount, 'rejected_activities_count' => count($activitiesData), 'rejected_at_level' => $rejectionLevel, 'rejected_date' => $rejectedDate, 'approver_level_info' => "Rejected at Level {$rejectionLevel}", 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Rejected requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve rejected requests: ' . $e->getMessage());
        }
    }

    public function rejectedForMe(Request $request)
    {
        try {
            $userId = $request->get('user_id');  // This is the employee ID
            // Get advance requests where this user is listed as an employee and has rejected activities
            $requests = AdvanceRequest::with(['employees.activities' => function ($query) {
                $query->whereNull('deleted_at')->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_REJECTED);
            }, 'employees.activities.approvals' => function ($query) {
                $query->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED);
            }, 'actions'])->whereHas('employees', function ($query) use ($userId) {
                $query->where('employee_id', $userId)->whereHas('activities', function ($activityQuery) {
                    $activityQuery->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_REJECTED);
                });
            })->whereIn('current_status', [AdvanceRequest::STATUS_REJECTED, AdvanceRequest::STATUS_IN_APPROVAL
            // Partially rejected
            ])->orderBy('last_submitted_at', 'desc')->get();
            if ($requests->isEmpty()) {
                return $this->jsonSuccess([], 'No rejected advance requests found for you');
            }
            // Transform the data to match the expected format - only show data for the current employee
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = '';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get submitter details
                $submitterDetails = (!empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by)) ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by);
                // Get advance type information
                $advanceTypeName = '';
                $advanceTypeCode = '';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceType = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        $advanceTypeName = $advanceType->type_name ?? '';
                        $advanceTypeCode = $advanceType->type_code ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'submitted_by_name' => $submitterDetails['EmployeeName'] ?? 'Unknown', 'submitted_by_code' => $submitterDetails['EmployeeCode'] ?? $advanceRequest->created_by, 'submitted_by_designation' => $submitterDetails['Designation'] ?? ''];
                // Find the employee record for the current user
                $currentEmployee = $advanceRequest->employees->where('employee_id', $userId)->first();
                if (!$currentEmployee) {
                    continue;  // Skip if current user is not in this request
                }
                $employeeDetails = $this->getEmployeeDetails($currentEmployee->employee_id);
                // Transform activities for this employee (only rejected ones)
                $activitiesData = [];
                $totalRejectedAmount = 0;
                $rejectedActivitiesCount = 0;
                foreach ($currentEmployee->activities as $activity) {
                    if ($activity->approval_status !== AdvanceRequestActivity::APPROVAL_STATUS_REJECTED) {
                        continue;  // Skip non-rejected activities
                    }
                    $totalRejectedAmount += $activity->amount;
                    $rejectedActivitiesCount++;
                    // Get activity type name
                    $activityTypeName = '';
                    if ($activity->activity_type_id) {
                        try {
                            $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                            $activityTypeName = $activityType->type_name ?? '';
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
                    // Get rejection details
                    $rejectionDetails = [];
                    $rejectedApproval = $activity->approvals->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED)->first();
                    if ($rejectedApproval) {
                        $rejectorDetails = $this->getEmployeeDetails($rejectedApproval->approver_id);
                        $rejectionDetails = ['rejected_by_id' => $rejectedApproval->approver_id, 'rejected_by_name' => $rejectorDetails['EmployeeName'] ?? 'Unknown', 'rejected_by_designation' => $rejectorDetails['Designation'] ?? '', 'rejection_level' => $rejectedApproval->approval_level, 'rejection_reason' => $rejectedApproval->approver_remark, 'rejected_date' => $rejectedApproval->approver_date];
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
                    $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'purpose' => $activity->purpose, 'approval_status' => $activity->approval_status, 'rejection_details' => $rejectionDetails, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                }
                // Only add if there are rejected activities
                if ($rejectedActivitiesCount > 0) {
                    // Add employee data
                    $allEmployees[] = ['id' => $currentEmployee->id, 'advance_request_id' => $currentEmployee->advance_request_id, 'employee_id' => $currentEmployee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $currentEmployee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? '', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? '', 'designation' => $employeeDetails['Designation'] ?? '', 'total_rejected_amount' => $totalRejectedAmount, 'rejected_activities_count' => $rejectedActivitiesCount, 'overall_status' => 'Rejected', 'status_info' => 'Request Rejected', 'created_at' => $currentEmployee->created_at, 'updated_at' => $currentEmployee->updated_at, 'deleted_at' => $currentEmployee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                }
            }
            return $this->jsonSuccess($allEmployees, 'Rejected advance requests for you retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve rejected requests for you: ' . $e->getMessage());
        }
    }

    public function submittedForMe(Request $request)
    {
        try {
            $userId = $request->get('user_id');  // This is the employee ID
            // Get advance requests where this user is listed as an employee but didn't create the request
            $requests = AdvanceRequest::with(['employees.activities' => function ($query) {
                $query->whereNull('deleted_at');
            }, 'employees.activities.approvals', 'actions'])
                ->whereHas('employees', function ($query) use ($userId) {
                    $query->where('employee_id', $userId);
                })
                ->where('created_by', '!=', $userId)
                ->whereIn('current_status', [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_IN_APPROVAL, AdvanceRequest::STATUS_APPROVED, AdvanceRequest::STATUS_FINANCE, AdvanceRequest::STATUS_FINANCE_APPROVED, AdvanceRequest::STATUS_REJECTED, AdvanceRequest::STATUS_REVERTED, AdvanceRequest::STATUS_CLOSED])
                ->orderBy('last_submitted_at', 'desc')
                ->get();
            if ($requests->isEmpty()) {
                return $this->jsonSuccess([], 'No advance requests submitted for you found');
            }
            // Transform the data to match the expected format - only show data for the current employee
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = '';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get submitter details
                $submitterDetails = (!empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by)) ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by);
                // Get advance type information
                $advanceTypeName = '';
                $advanceTypeCode = '';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceType = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        $advanceTypeName = $advanceType->type_name ?? '';
                        $advanceTypeCode = $advanceType->type_code ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Prepare request data object
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'submitted_by_name' => $submitterDetails['EmployeeName'] ?? 'Unknown', 'submitted_by_code' => $submitterDetails['EmployeeCode'] ?? $advanceRequest->created_by, 'submitted_by_designation' => $submitterDetails['Designation'] ?? ''];
                // Find the employee record for the current user
                $currentEmployee = $advanceRequest->employees->where('employee_id', $userId)->first();
                if (!$currentEmployee) {
                    continue;  // Skip if current user is not in this request
                }
                $employeeDetails = $this->getEmployeeDetails($currentEmployee->employee_id);
                // Transform activities for this employee
                $activitiesData = [];
                $totalAmount = 0;
                $totalApprovedAmount = 0;
                $totalFinanceApprovedAmount = 0;
                $currentApprovalLevel = null;
                $overallStatus = 'Pending';
                foreach ($currentEmployee->activities as $activity) {
                    $totalAmount += $activity->amount;
                    // Determine activity status
                    $activityStatus = $activity->approval_status;
                    if ($activityStatus === AdvanceRequestActivity::APPROVAL_STATUS_APPROVED) {
                        $totalApprovedAmount += $activity->amount;
                        if ($activity->finance_status === 'Approved') {
                            $totalFinanceApprovedAmount += $activity->finance_approved_amount ?? $activity->amount;
                        }
                    }
                    // Set current approval level
                    if ($currentApprovalLevel === null || $activity->current_approval_level > $currentApprovalLevel) {
                        $currentApprovalLevel = $activity->current_approval_level;
                    }
                    // Get activity type name
                    $activityTypeName = '';
                    if ($activity->activity_type_id) {
                        try {
                            $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                            $activityTypeName = $activityType->type_name ?? '';
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
                    // Get current approver information
                    $currentApprover = null;
                    $pendingApproval = $activity->approvals->where('approval_level', $activity->current_approval_level)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->first();
                    if ($pendingApproval) {
                        $approverDetails = $this->getEmployeeDetails($pendingApproval->approver_id);
                        $currentApprover = ['id' => $pendingApproval->approver_id, 'name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'designation' => $approverDetails['Designation'] ?? ''];
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
                    $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'current_approver' => $currentApprover, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                }
                // Determine overall status for this employee
                $approvedCount = collect($activitiesData)->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->count();
                $rejectedCount = collect($activitiesData)->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_REJECTED)->count();
                $totalActivitiesCount = count($activitiesData);
                if ($approvedCount === $totalActivitiesCount) {
                    $overallStatus = 'Approved';
                } elseif ($rejectedCount > 0) {
                    $overallStatus = 'Rejected';
                } elseif ($advanceRequest->current_status === AdvanceRequest::STATUS_IN_APPROVAL) {
                    $overallStatus = "In Approval (Level {$currentApprovalLevel})";
                } else {
                    $overallStatus = ucfirst(str_replace('_', ' ', $advanceRequest->current_status));
                }
                // Add employee data
                $allEmployees[] = ['id' => $currentEmployee->id, 'advance_request_id' => $currentEmployee->advance_request_id, 'employee_id' => $currentEmployee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $currentEmployee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? '', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? '', 'designation' => $employeeDetails['Designation'] ?? '', 'total_amount' => $totalAmount, 'total_approved_amount' => $totalApprovedAmount, 'total_finance_approved_amount' => $totalFinanceApprovedAmount, 'activities_count' => $totalActivitiesCount, 'current_approval_level' => $currentApprovalLevel, 'overall_status' => $overallStatus, 'status_info' => "Request {$overallStatus}", 'created_at' => $currentEmployee->created_at, 'updated_at' => $currentEmployee->updated_at, 'deleted_at' => $currentEmployee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
            }
            return $this->jsonSuccess($allEmployees, 'Advance requests submitted for you retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve requests submitted for you: ' . $e->getMessage());
        }
    }

    public function approved(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            // Get advance requests that have been approved by this user
            $requestIds = DB::table('advance_activity_approvals')->join('advance_request_activities', 'advance_activity_approvals.advance_request_activity_id', '=', 'advance_request_activities.id')->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')->where('advance_activity_approvals.approver_id', $userId)->where('advance_activity_approvals.approver_status', AdvanceActivityApproval::STATUS_APPROVED)->whereNull('advance_request_activities.deleted_at')->pluck('advance_request_employees.advance_request_id')->unique();
            if ($requestIds->isEmpty()) {
                return $this->jsonSuccess([], 'No approved requests found');
            }
            // Get the actual requests with their relationships
            $requests = AdvanceRequest::with(['employees.activities.approvals' => function ($query) use ($userId) {
                $query->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED);
            }, 'employees.activities' => function ($query) {
                $query->whereNull('deleted_at');
            }, 'actions'])->whereIn('id', $requestIds)->orderBy('last_submitted_at', 'desc')->get();
            // Transform the data to match the expected format - employees with req_data inside
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = '';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = '';
                $advanceTypeCode = '';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceType = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        $advanceTypeName = $advanceType->type_name ?? '';
                        $advanceTypeCode = $advanceType->type_code ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any approved activities by this approver
                    $hasApprovedActivities = $employee->activities->filter(function ($activity) use ($userId) {
                        return $activity->approvals->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)->isNotEmpty();
                    })->isNotEmpty();
                    if (!$hasApprovedActivities) {
                        continue;  // Skip employees with no approved activities by this approver
                    }
                    // Prepare request data object with employee-specific history
                    $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                    $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approver_advance_history' => $this->getApproverAdvanceHistory($userId, $advanceRequest->id, $employee->employee_id)];
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee (only approved ones by this approver)
                    $activitiesData = [];
                    $totalApprovedAmount = 0;
                    $approvalLevel = null;
                    $approvedDate = null;
                    foreach ($employee->activities as $activity) {
                        // Check if this activity has approved status by this approver
                        $approvedApproval = $activity->approvals->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)->first();
                        if (!$approvedApproval) {
                            continue;  // Skip activities not approved by this approver
                        }
                        // Set the approval level and date (should be same for all activities of this employee)
                        if ($approvalLevel === null) {
                            $approvalLevel = $approvedApproval->approval_level;
                            $approvedDate = $approvedApproval->approver_date;
                        }
                        $totalApprovedAmount += $approvedApproval->approver_amount ?? $activity->amount;
                        // Get activity type name
                        $activityTypeName = '';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? '';
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
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'approved_amount' => $approvedApproval->approver_amount ?? $activity->amount, 'approver_remark' => $approvedApproval->approver_remark, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have approved activities
                    if (!empty($activitiesData)) {
                        // Add employee with req_data inside
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? '', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? '', 'designation' => $employeeDetails['Designation'] ?? '', 'total_approved_amount' => $totalApprovedAmount, 'approved_activities_count' => count($activitiesData), 'approved_at_level' => $approvalLevel, 'approved_date' => $approvedDate, 'approver_level_info' => "Approved at Level {$approvalLevel}", 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Approved requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve approved requests: ' . $e->getMessage());
        }
    }

    public function pending(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            // Get advance requests that have pending approvals for this user at current level
            $requestIds = DB::table('advance_activity_approvals')->join('advance_request_activities', 'advance_activity_approvals.advance_request_activity_id', '=', 'advance_request_activities.id')->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')->where('advance_activity_approvals.approver_id', $userId)->where('advance_activity_approvals.approver_status', AdvanceActivityApproval::STATUS_PENDING)->whereColumn('advance_activity_approvals.approval_level', 'advance_request_activities.current_approval_level')->whereNull('advance_request_activities.deleted_at')->pluck('advance_request_employees.advance_request_id')->unique();
            if ($requestIds->isEmpty()) {
                return $this->jsonSuccess([], 'No pending approvals found');
            }
            // Get the actual requests with their relationships
            $requests = AdvanceRequest::with(['employees.activities.approvals' => function ($query) use ($userId) {
                $query->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING);
            }, 'employees.activities' => function ($query) {
                $query->whereNull('deleted_at');
            }, 'actions'])
                ->whereIn('id', $requestIds)
                // ->whereIn('current_status', [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_IN_APPROVAL])
                ->orderBy('last_submitted_at', 'asc')
                ->get();
            // Transform the data to match the expected format - employees with req_data inside
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = '';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->where('ClaimStatus', 'A')->first();
                        $activityName = $activityNameRecord->ClaimName ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = '';
                $advanceTypeCode = '';
                if ($advanceRequest->advance_type_id) {
                    try {
                        $advanceType = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->where('is_active', 1)->first();
                        $advanceTypeName = $advanceType->type_name ?? '';
                        $advanceTypeCode = $advanceType->type_code ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    // Check if this employee has any pending activities for this approver at their current level
                    $hasPendingActivities = $employee->activities->filter(function ($activity) use ($userId) {
                        return $activity->approvals->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->where('approval_level', $activity->current_approval_level)->isNotEmpty();
                    })->isNotEmpty();
                    if (!$hasPendingActivities) {
                        continue;  // Skip employees with no pending activities for this approver
                    }
                    // Prepare request data object with employee-specific history
                    $isImprestReq = !empty($advanceRequest->is_imprest) || empty($advanceRequest->created_by);
                    $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'is_imprest' => $isImprestReq ? 1 : 0, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'submitted_employee_details' => $isImprestReq ? $this->getEmployeeDetails(0) : $this->getEmployeeDetails($advanceRequest->created_by), 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approver_advance_history' => $this->getApproverAdvanceHistory($userId, $advanceRequest->id, $employee->employee_id)];
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee (only pending ones for this approver)
                    $activitiesData = [];
                    $totalPendingAmount = 0;
                    $currentApprovalLevel = null;
                    foreach ($employee->activities as $activity) {
                        // Check if this activity has pending approval for this approver
                        $pendingApproval = $activity->approvals->where('approver_id', $userId)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->where('approval_level', $activity->current_approval_level)->first();
                        if (!$pendingApproval) {
                            continue;  // Skip activities not pending for this approver
                        }
                        // Set the current approval level (should be same for all activities of this employee)
                        if ($currentApprovalLevel === null) {
                            $currentApprovalLevel = $activity->current_approval_level;
                        }
                        $totalPendingAmount += $activity->amount;
                        // Get activity type name
                        $activityTypeName = '';
                        if ($activity->activity_type_id) {
                            try {
                                $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                                $activityTypeName = $activityType->type_name ?? '';
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
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => $cropNames, 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => $varietyNames, 'amount' => $activity->amount, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'payment_id' => $activity->payment_id, 'payment_amount' => $activity->payment_amount, 'payment_status' => $activity->payment_status, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_confirmation_details' => $paymentConfirmationDetails, 'digi_next_payment_details' => $digiNextPaymentDetails, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Only add employee if they have pending activities
                    if (!empty($activitiesData)) {
                        // Add employee with req_data inside
                        $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? '', 'emp_status' => $employeeDetails['EmpStatus'] ?? 'A', 'is_active' => ($employeeDetails['EmpStatus'] ?? 'A') === 'A', 'department' => $employeeDetails['DepartmentName'] ?? '', 'designation' => $employeeDetails['Designation'] ?? '', 'total_pending_amount' => $totalPendingAmount, 'pending_activities_count' => count($activitiesData), 'current_approval_level' => $currentApprovalLevel, 'approver_level_info' => "Level {$currentApprovalLevel} Approval", 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                    }
                }
            }
            return $this->jsonSuccess($allEmployees, 'Pending approvals retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve pending approvals: ' . $e->getMessage());
        }
    }

    public function approve(ApproveRequest $request)
    {
        try {
            $userId = $request->get('user_id');
            $employeeId = $request->input('employee_id');  // Employee ID to approve
            $requestId = $request->input('request_id');  // Advance request ID
            $approverAmount = $request->input('approver_amount');
            $remarks = $request->input('approver_remark') ?? $request->input('remarks');
            $activityId = $request->input('activity_id');

            // If activity_id is provided, approve specific activity
            if ($activityId) {
                $this->approveActivity($activityId, $userId, $approverAmount, $remarks);
            } else {
                // Validate required parameters
                if (!$employeeId || !$requestId) {
                    return $this->jsonError('employee_id and request_id are required');
                }
                $this->approveEmployeeActivities($requestId, $employeeId, $userId, $approverAmount, $remarks);
            }

            // Notify Central Notification Service
            try {
                $this->centralNotification->approveAction(
                    'advance_request',
                    "{$requestId}",
                    $remarks ?? 'Approved',
                    ['approved_by' => $userId, 'employee_id' => $employeeId, 'amount' => $approverAmount, 'activity_id' => $activityId]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Central Notification (advance approve) failed: ' . $e->getMessage());
            }

            return $this->jsonSuccess(null, 'Request approved successfully');
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
            $remarks = $request->input('remarks') ?? $request->input('approver_remark');
            $activityId = $request->input('activity_id');

            // If activity_id is provided, reject specific activity
            if ($activityId) {
                $this->rejectActivity($activityId, $userId, $remarks);
            } else {
                // Validate required parameters
                if (!$employeeId || !$requestId) {
                    return $this->jsonError('employee_id and request_id are required');
                }
                $this->rejectEmployeeActivities($requestId, $employeeId, $userId, $remarks);
            }

            // Notify Central Notification Service
            try {
                $this->centralNotification->rejectAction(
                    'advance_request',
                    "{$requestId}",
                    $remarks ?? 'Rejected',
                    ['rejected_by' => $userId, 'employee_id' => $employeeId, 'activity_id' => $activityId]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Central Notification (advance reject) failed: ' . $e->getMessage());
            }

            return $this->jsonSuccess(null, 'Request rejected successfully');
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
            $remarks = $request->input('remarks') ?? $request->input('revert_reason');
            $activityId = $request->input('activity_id');

            // If activity_id is provided, revert specific activity
            if ($activityId) {
                $this->revertActivity($activityId, $userId, $remarks);
            } else {
                // Validate required parameters
                if (!$employeeId || !$requestId) {
                    return $this->jsonError('employee_id and request_id are required');
                }
                $this->revertEmployeeActivities($requestId, $employeeId, $userId, $remarks);
            }

            // Notify Central Notification Service - Cancel/Revert
            try {
                $this->centralNotification->cancelAction(
                    'advance_request',
                    "{$requestId}"
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Central Notification (advance revert) failed: ' . $e->getMessage());
            }

            return $this->jsonSuccess(null, 'Request reverted successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to revert request: ' . $e->getMessage());
        }
    }

    private function approveEmployeeActivities($requestId, $employeeId, $approverId, $approverAmount = null, $remarks = null)
    {
        return DB::transaction(function () use ($requestId, $employeeId, $approverId, $approverAmount, $remarks) {
            $request = AdvanceRequest::findOrFail($requestId);
            // Get the employee record for this request (try matching by employee_id or table id)
            $requestEmployee = $request->employees()
                ->where(function ($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId)
                      ->orWhere('id', $employeeId);
                })->first();

            // Fallback: if only one employee in request, use that employee
            if (!$requestEmployee && $request->employees()->count() === 1) {
                $requestEmployee = $request->employees()->first();
            }

            if (!$requestEmployee) {
                throw new Exception("Employee ID {$employeeId} not found in request ID {$requestId}");
            }

            // Get all activities for this employee that have pending approvals for this approver
            // 1. Try with matching current_approval_level
            $activities = $requestEmployee->activities()->whereHas('approvals', function ($query) use ($approverId) {
                $query->where('approver_id', $approverId)
                      ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)
                      ->whereColumn('approval_level', 'advance_request_activities.current_approval_level');
            })->get();

            // 2. Fallback: match without checking current_approval_level column equality
            if ($activities->isEmpty()) {
                $activities = $requestEmployee->activities()->whereHas('approvals', function ($query) use ($approverId) {
                    $query->where('approver_id', $approverId)
                          ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING);
                })->get();
            }

            // 3. Fallback: check across entire request for this approver if still empty
            if ($activities->isEmpty()) {
                $activities = $request->activities()->whereHas('approvals', function ($query) use ($approverId) {
                    $query->where('approver_id', $approverId)
                          ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING);
                })->get();
            }

            // 4. Check if already approved (idempotent success)
            if ($activities->isEmpty()) {
                $alreadyApproved = AdvanceActivityApproval::where('approver_id', $approverId)
                    ->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)
                    ->whereIn('advance_request_activity_id', $requestEmployee->activities()->pluck('id'))
                    ->exists();

                if ($alreadyApproved) {
                    return true;
                }

                throw new Exception("No pending activities found for employee ID {$employeeId} and approver ID {$approverId}");
            }

            $totalAmount = $activities->sum('amount');
            $finalAmount = $approverAmount ?: $totalAmount;
            if ($finalAmount > $totalAmount) {
                throw new Exception('Approval amount cannot exceed total activities amount');
            }
            // Approve all activities for this employee
            foreach ($activities as $activity) {
                // Calculate proportional amount for this activity
                $activityApprovalAmount = $activities->count() > 1 ? ($activity->amount / $totalAmount) * $finalAmount : $finalAmount;
                // Get pending approval for this approver and activity at current level
                $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
                    ->where('approver_id', $approverId)
                    ->where('approval_level', $activity->current_approval_level)
                    ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)
                    ->first();

                if (!$approval) {
                    $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
                        ->where('approver_id', $approverId)
                        ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)
                        ->first();
                }

                if ($approval) {
                    // Update approval
                    $approval->update(['approver_status' => AdvanceActivityApproval::STATUS_APPROVED, 'approver_amount' => $activityApprovalAmount, 'approver_remark' => $remarks, 'approver_date' => now()->toDateString()]);
                    // Check if all approvals at current level are complete for this activity
                    $pendingAtLevel = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)->where('approval_level', $approval->approval_level)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->count();
                    if ($pendingAtLevel === 0) {
                        // Move activity to next level or complete approval
                        $this->moveActivityToNextApprovalLevel($activity);
                    }
                    // Log action for each activity
                    $this->logAction($request, AdvanceRequestAction::ACTION_APPROVE, $approverId, null, $activity->id, $approval->approval_level, $remarks);
                }
            }
            // Check if entire request should move to next status
            $this->checkAndUpdateRequestStatus($request);
            // Send notification emails (using first activity's approval for notification)
            if (!$activities->isEmpty()) {
                $firstApproval = $activities->first()->approvals()->where('approver_id', $approverId)->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)->first();

                if ($firstApproval) {
                    $this->sendApprovalNotifications($request, $firstApproval);
                    // Check if any activities moved to next level and send notifications to next level approvers
                    $this->sendNextLevelNotifications($request, $activities, $employeeId);
                }
            }
            return true;  // Success indicator
        });
    }

    private function rejectEmployeeActivities($requestId, $employeeId, $approverId, $remarks)
    {
        return DB::transaction(function () use ($requestId, $employeeId, $approverId, $remarks) {
            $request = AdvanceRequest::findOrFail($requestId);
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot reject. Expected 'Submitted' or 'In Approval'");
            }
            // Get the employee record for this request (try matching by employee_id or table id)
            $requestEmployee = $request->employees()
                ->where(function ($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId)
                      ->orWhere('id', $employeeId);
                })->first();

            // Fallback: if only one employee in request, use that employee
            if (!$requestEmployee && $request->employees()->count() === 1) {
                $requestEmployee = $request->employees()->first();
            }

            if (!$requestEmployee) {
                throw new Exception("Employee ID {$employeeId} not found in request ID {$requestId}");
            }

            // Get all activities for this employee that have pending approvals for this approver
            // 1. Try with matching current_approval_level
            $activities = $requestEmployee->activities()->whereHas('approvals', function ($query) use ($approverId) {
                $query->where('approver_id', $approverId)
                      ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)
                      ->whereColumn('approval_level', 'advance_request_activities.current_approval_level');
            })->get();

            // 2. Fallback: match without checking current_approval_level column equality
            if ($activities->isEmpty()) {
                $activities = $requestEmployee->activities()->whereHas('approvals', function ($query) use ($approverId) {
                    $query->where('approver_id', $approverId)
                          ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING);
                })->get();
            }

            // 3. Fallback: check across entire request for this approver if still empty
            if ($activities->isEmpty()) {
                $activities = $request->activities()->whereHas('approvals', function ($query) use ($approverId) {
                    $query->where('approver_id', $approverId)
                          ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING);
                })->get();
            }

            // 4. Check if already rejected (idempotent success)
            if ($activities->isEmpty()) {
                $alreadyRejected = AdvanceActivityApproval::where('approver_id', $approverId)
                    ->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED)
                    ->whereIn('advance_request_activity_id', $requestEmployee->activities()->pluck('id'))
                    ->exists();

                if ($alreadyRejected) {
                    return true;
                }

                throw new Exception("No pending activities found for employee ID {$employeeId} and approver ID {$approverId}");
            }

            // Reject all activities for this employee
            foreach ($activities as $activity) {
                // Get pending approval for this approver at current level
                $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
                    ->where('approver_id', $approverId)
                    ->where('approval_level', $activity->current_approval_level)
                    ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)
                    ->first();

                if (!$approval) {
                    $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
                        ->where('approver_id', $approverId)
                        ->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)
                        ->first();
                }

                if ($approval) {
                    // Update approval
                    $approval->update(['approver_status' => AdvanceActivityApproval::STATUS_REJECTED, 'approver_remark' => $remarks, 'approver_date' => now()->toDateString()]);
                    // Update activity status
                    $activity->update(['approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_REJECTED]);
                    // Log action for each activity
                    $this->logAction($request, AdvanceRequestAction::ACTION_REJECT, $approverId, null, $activity->id, $approval->approval_level, $remarks);
                }
            }
            // Check if entire request should move to next status
            $this->checkAndUpdateRequestStatus($request);
            // Send notification emails (using first activity's approval for notification)
            if (!$activities->isEmpty()) {
                $firstApproval = $activities->first()->approvals()->where('approver_id', $approverId)->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED)->first();
                if ($firstApproval) {
                    $this->sendRejectionNotifications($request, $firstApproval);
                }
            }
            return true;  // Success indicator
        });
    }

    private function revertEmployeeActivities($requestId, $employeeId, $reverterId, $reason)
    {
        return DB::transaction(function () use ($requestId, $employeeId, $reverterId, $reason) {
            $request = AdvanceRequest::findOrFail($requestId);
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_APPROVED])) {
                throw new Exception('Request cannot be reverted in current status');
            }
            // Get the employee record for this request (try matching by employee_id or table id)
            $requestEmployee = $request->employees()
                ->where(function ($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId)
                      ->orWhere('id', $employeeId);
                })->first();

            // Fallback: if only one employee in request, use that employee
            if (!$requestEmployee && $request->employees()->count() === 1) {
                $requestEmployee = $request->employees()->first();
            }

            if (!$requestEmployee) {
                throw new Exception("Employee ID {$employeeId} not found in request ID {$requestId}");
            }
            // Get all activities for this employee that have approvals for this reverter
            // 1. Try matching current_approval_level
            $activities = $requestEmployee->activities()->whereHas('approvals', function ($query) use ($reverterId) {
                $query->where('approver_id', $reverterId)->whereColumn('approval_level', 'advance_request_activities.current_approval_level');
            })->get();

            // 2. Fallback: match without checking current_approval_level column equality
            if ($activities->isEmpty()) {
                $activities = $requestEmployee->activities()->whereHas('approvals', function ($query) use ($reverterId) {
                    $query->where('approver_id', $reverterId);
                })->get();
            }

            // 3. Fallback: check across entire request for this reverter if still empty
            if ($activities->isEmpty()) {
                $activities = $request->activities()->whereHas('approvals', function ($query) use ($reverterId) {
                    $query->where('approver_id', $reverterId);
                })->get();
            }

            // 4. Check if already reverted (idempotent success)
            if ($activities->isEmpty()) {
                $alreadyReverted = AdvanceActivityApproval::where('approver_id', $reverterId)
                    ->where('approver_status', AdvanceActivityApproval::STATUS_REVERTED)
                    ->whereIn('advance_request_activity_id', $requestEmployee->activities()->pluck('id'))
                    ->exists();

                if ($alreadyReverted) {
                    return true;
                }

                throw new Exception("No activities found for employee ID {$employeeId} and reverter ID {$reverterId}");
            }
            // Revert all activities for this employee
            foreach ($activities as $activity) {
                // Get current approver's approval
                $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
                    ->where('approver_id', $reverterId)
                    ->where('approval_level', $activity->current_approval_level)
                    ->first();

                if (!$approval) {
                    $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
                        ->where('approver_id', $reverterId)
                        ->first();
                }

                if ($approval) {
                    // Mark approval as reverted
                    $approval->update(['approver_status' => AdvanceActivityApproval::STATUS_REVERTED, 'reverted_by' => $reverterId, 'reverted_at' => now(), 'revert_reason' => $reason]);
                    // Determine revert target level
                    $targetLevel = $this->getRevertTargetLevel($activity, $approval->approval_level);
                    // Update activity
                    $activity->update(['current_approval_level' => $targetLevel, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_REVERTED]);
                    // Log action for each activity
                    $this->logAction($request, AdvanceRequestAction::ACTION_REVERT, $reverterId, null, $activity->id, $approval->approval_level, $reason);
                }
            }
            // Update request status
            $request->update(['current_status' => AdvanceRequest::STATUS_REVERTED, 'reverted_from_status' => $request->current_status, 'revert_reason' => $reason, 'reverted_by' => $reverterId, 'reverted_at' => now()]);
            // Send notification emails (using first activity's approval for notification)
            if (!$activities->isEmpty()) {
                $firstApproval = $activities->first()->approvals()->where('approver_id', $reverterId)->where('approver_status', AdvanceActivityApproval::STATUS_REVERTED)->first();
                if ($firstApproval) {
                    $this->sendRevertNotifications($request, $firstApproval);
                }
            }
            return true;  // Success indicator
        });
    }

    private function approveActivity($activityId, $approverId, $approverAmount = null, $remarks = null)
    {
        return DB::transaction(function () use ($activityId, $approverId, $approverAmount, $remarks) {
            $activity = AdvanceRequestActivity::findOrFail($activityId);
            $request = $activity->employee->advanceRequest;
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot approve. Expected 'Submitted' or 'In Approval'");
            }
            // Get pending approval for this approver and activity at current level
            $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $approverId)->where('approval_level', $activity->current_approval_level)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->first();
            if (!$approval) {
                $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $approverId)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->first();
            }
            if (!$approval) {
                $alreadyApproved = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $approverId)->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)->first();
                if ($alreadyApproved) {
                    return $request->load(['employees.activities.approvals', 'actions']);
                }
                throw new Exception("No pending approval found for approver ID {$approverId} at level {$activity->current_approval_level} for activity ID {$activityId}");
            }
            // Validate approval amount
            $maxAmount = $activity->amount;
            $finalAmount = $approverAmount ?: $maxAmount;
            if ($finalAmount > $maxAmount) {
                throw new Exception('Approval amount cannot exceed activity amount');
            }
            // Update approval
            $approval->update(['approver_status' => AdvanceActivityApproval::STATUS_APPROVED, 'approver_amount' => $finalAmount, 'approver_remark' => $remarks, 'approver_date' => now()->toDateString()]);
            // Check if all approvals at current level are complete for this activity
            $pendingAtLevel = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approval_level', $approval->approval_level)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->count();
            if ($pendingAtLevel === 0) {
                // Move activity to next level or complete approval
                $this->moveActivityToNextApprovalLevel($activity);
            }
            // Check if entire request should move to next status
            $this->checkAndUpdateRequestStatus($request);
            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_APPROVE, $approverId, null, $activity->id, $approval->approval_level, $remarks);
            // Send notification emails
            $this->sendApprovalNotifications($request, $approval);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function rejectActivity($activityId, $approverId, $remarks)
    {
        return DB::transaction(function () use ($activityId, $approverId, $remarks) {
            $activity = AdvanceRequestActivity::findOrFail($activityId);
            $request = $activity->employee->advanceRequest;
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_IN_APPROVAL])) {
                throw new Exception("Request is in '{$request->current_status}' status, cannot reject. Expected 'Submitted' or 'In Approval'");
            }
            // Get pending approval for this approver at current level
            $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $approverId)->where('approval_level', $activity->current_approval_level)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->first();
            if (!$approval) {
                $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $approverId)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->first();
            }
            if (!$approval) {
                $alreadyRejected = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $approverId)->where('approver_status', AdvanceActivityApproval::STATUS_REJECTED)->first();
                if ($alreadyRejected) {
                    return $request->load(['employees.activities.approvals', 'actions']);
                }
                throw new Exception("No pending approval found for approver ID {$approverId} at level {$activity->current_approval_level} for activity ID {$activityId}");
            }
            // Update approval
            $approval->update(['approver_status' => AdvanceActivityApproval::STATUS_REJECTED, 'approver_remark' => $remarks, 'approver_date' => now()->toDateString()]);
            // Update activity status
            $activity->update(['approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_REJECTED]);
            // Update request status
            $this->checkAndUpdateRequestStatus($request);
            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_REJECT, $approverId, null, $activity->id, $approval->approval_level, $remarks);
            // Send notification emails
            $this->sendRejectionNotifications($request, $approval);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function revertActivity($activityId, $reverterId, $reason)
    {
        return DB::transaction(function () use ($activityId, $reverterId, $reason) {
            $activity = AdvanceRequestActivity::findOrFail($activityId);
            $request = $activity->employee->advanceRequest;
            if (!in_array($request->current_status, [AdvanceRequest::STATUS_SUBMITTED, AdvanceRequest::STATUS_APPROVED])) {
                throw new Exception('Request cannot be reverted in current status');
            }
            // Get current approver's approval
            $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $reverterId)->where('approval_level', $activity->current_approval_level)->first();
            if (!$approval) {
                $approval = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $reverterId)->first();
            }
            if (!$approval) {
                $alreadyReverted = AdvanceActivityApproval::where('advance_request_activity_id', $activityId)->where('approver_id', $reverterId)->where('approver_status', AdvanceActivityApproval::STATUS_REVERTED)->first();
                if ($alreadyReverted) {
                    return $request->load(['employees.activities.approvals', 'actions']);
                }
                throw new Exception("No approval found for reverter ID {$reverterId} at level {$activity->current_approval_level} for activity ID {$activityId}");
            }
            // Mark approval as reverted
            $approval->update(['approver_status' => AdvanceActivityApproval::STATUS_REVERTED, 'reverted_by' => $reverterId, 'reverted_at' => now(), 'revert_reason' => $reason]);
            // Determine revert target level
            $targetLevel = $this->getRevertTargetLevel($activity, $approval->approval_level);
            // Update activity
            $activity->update(['current_approval_level' => $targetLevel, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_REVERTED]);
            // Update request
            $request->update(['current_status' => AdvanceRequest::STATUS_REVERTED, 'reverted_from_status' => $request->current_status, 'revert_reason' => $reason, 'reverted_by' => $reverterId, 'reverted_at' => now()]);
            // Log actionf
            $this->logAction($request, AdvanceRequestAction::ACTION_REVERT, $reverterId, null, $activity->id, $approval->approval_level, $reason);
            // Send notification emails
            $this->sendRevertNotifications($request, $approval);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function moveActivityToNextApprovalLevel(AdvanceRequestActivity $activity)
    {
        $nextLevel = $activity->current_approval_level + 1;
        // Check if there are more approval levels for this activity
        $hasNextLevel = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)->where('approval_level', $nextLevel)->exists();
        if ($hasNextLevel) {
            // Move to next level
            $activity->update(['current_approval_level' => $nextLevel, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_PENDING]);
            \Log::info("Activity {$activity->id} moved to approval level {$nextLevel}");
        } else {
            // All approvals complete for this activity
            $activity->update(['approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_APPROVED]);
            \Log::info("Activity {$activity->id} Approved - no more levels");
        }
    }

    private function checkAndUpdateRequestStatus(AdvanceRequest $request)
    {
        // Get counts of different activity statuses across all employees
        // Use table-qualified column names to avoid ambiguous deleted_at references
        $totalActivities = $request->activities()->whereNull('advance_request_activities.deleted_at')->whereNull('advance_request_employees.deleted_at')->count();
        $approvedActivities = $request->activities()->where('advance_request_activities.approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->whereNull('advance_request_activities.deleted_at')->whereNull('advance_request_employees.deleted_at')->count();
        $rejectedActivities = $request->activities()->where('advance_request_activities.approval_status', AdvanceRequestActivity::APPROVAL_STATUS_REJECTED)->whereNull('advance_request_activities.deleted_at')->whereNull('advance_request_employees.deleted_at')->count();
        $pendingActivities = $request->activities()->whereIn('advance_request_activities.approval_status', [AdvanceRequestActivity::APPROVAL_STATUS_PENDING])->whereNull('advance_request_activities.deleted_at')->whereNull('advance_request_employees.deleted_at')->count();
        $revertedActivities = $request->activities()->where('advance_request_activities.approval_status', AdvanceRequestActivity::APPROVAL_STATUS_REVERTED)->whereNull('advance_request_activities.deleted_at')->whereNull('advance_request_employees.deleted_at')->count();
        \Log::info("Request {$request->id} status check: Total={$totalActivities}, Approved={$approvedActivities}, Rejected={$rejectedActivities}, Pending={$pendingActivities}, Reverted={$revertedActivities}");
        $previousStatus = $request->current_status;

        // Check each employee's activities and send approval emails if all their activities are approved
        foreach ($request->employees as $employee) {
            $employeeTotalActivities = $employee->activities()->whereNull('deleted_at')->count();
            $employeeApprovedActivities = $employee->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->whereNull('deleted_at')->count();

            // Check if this employee hasn't been notified yet and all their activities are approved
            if ($employeeTotalActivities > 0 && $employeeApprovedActivities === $employeeTotalActivities) {
                // Check if we've already sent approval email for this employee in this submission
                $approvalEmailSent = AdvanceRequestAction::where('advance_request_id', $request->id)
                    ->where('submission_no', $request->submission_no)
                    ->where('action_type', AdvanceRequestAction::ACTION_APPROVE)
                    ->where('action_to', $employee->employee_id)
                    ->exists();

                if (!$approvalEmailSent) {
                    // Send approval email to this employee
                    $this->sendEmployeeApprovalEmail($request, $employee);

                    // Log that we sent the approval email
                    $this->logAction($request, AdvanceRequestAction::ACTION_APPROVE, $request->req_app_submit_id, $employee->employee_id);

                    \Log::info("Approval email sent to employee {$employee->employee_id} for request {$request->id}");
                }
            }
        }

        // Determine the appropriate status
        if ($revertedActivities > 0) {
            // If any activity is reverted, entire request is reverted
            $request->update(['current_status' => AdvanceRequest::STATUS_REVERTED]);
            \Log::info("Request {$request->id} status updated to REVERTED");
        } elseif ($rejectedActivities > 0 && $pendingActivities === 0) {
            // All non-rejected activities are complete, but some are rejected
            if ($approvedActivities > 0) {
                // Mixed result: some approved, some rejected - partial approval
                $request->update(['current_status' => AdvanceRequest::STATUS_IN_APPROVAL]);
                \Log::info("Request {$request->id} status remains IN_APPROVAL (mixed results)");
            } else {
                // All activities are rejected
                $request->update(['current_status' => AdvanceRequest::STATUS_REJECTED]);
                \Log::info("Request {$request->id} status updated to REJECTED");
            }
        } elseif ($approvedActivities === $totalActivities) {
            // All activities approved across all employees, move to finance
            $request->update(['current_status' => AdvanceRequest::STATUS_FINANCE]);
            \Log::info("Request {$request->id} status updated to FINANCE");
            $this->sendFinanceNotifications($request);
        } elseif ($pendingActivities > 0) {
            // Still have pending activities (some employees may be at different levels)
            $request->update(['current_status' => AdvanceRequest::STATUS_IN_APPROVAL]);
            \Log::info("Request {$request->id} status remains IN_APPROVAL");
        }
    }

    private function sendFinanceNotifications(AdvanceRequest $request)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }
            \Log::info("Sending finance notifications for request {$request->id}");
            // Get activity name and type for subject
            $activityName = 'N/A';
            $activityTypeName = 'N/A';
            if ($request->activity_name_id) {
                try {
                    $activityNameRecord = DB::table('claimtype')->where('ClaimId', $request->activity_name_id)->where('ClaimStatus', 'A')->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
                }
            }
            // Get activity type from first activity
            $firstActivity = $request->activities->first();
            if ($firstActivity && $firstActivity->activity_type_id) {
                try {
                    $activityType = DB::table('adv_activity_types')->where('id', $firstActivity->activity_type_id)->first();
                    $activityTypeName = $activityType->type_name ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity type: ' . $e->getMessage());
                }
            }
            // Format month-year from the 'month' field (stored as month number like 3 for March)
            $monthYear = 'N/A';
            if ($request->month) {
                try {
                    // Month is stored as a number (1-12), create date from it
                    $year = $request->created_at ? $request->created_at->format('Y') : now()->format('Y');
                    $monthYear = \Carbon\Carbon::createFromDate($year, $request->month, 1)->format('M-y');
                } catch (\Exception $e) {
                    \Log::warning('Error formatting month-year: ' . $e->getMessage());
                    $monthYear = 'N/A';
                }
            }

            // Get advance type code
            $advanceTypeCode = 'Activity';
            try {
                $advanceType = DB::table('advance_request_types')->where('id', $request->advance_type_id)->first();
                $advanceTypeCode = $advanceType->type_code ?? 'Activity';
            } catch (\Exception $e) {
                \Log::warning('Error fetching advance type: ' . $e->getMessage());
            }

            // If advance type is Activity, check is_tour_travel on activities
            // If is_tour_travel = 'Y', treat it as Expense type for the email
            if ($advanceTypeCode === 'Act') {
                $firstActivity = $request->activities->first();
                if ($firstActivity && in_array($firstActivity->is_tour_travel, ['Y', 'y', '1', 1, true, 'true'], true)) {
                    $advanceTypeCode = 'Exp';
                }
            }

            // Send email for each employee in the request
            foreach ($request->employees as $employee) {
                $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                if (!$employeeDetails) {
                    \Log::warning("No details found for employee ID: {$employee->employee_id}");
                    continue;
                }
                // Calculate total approved amount for this employee
                $totalAmount = $employee->activities()->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->whereNull('deleted_at')->sum('amount');
                $emailData = ['request_no' => $request->id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'created_date' => $request->created_at->format('d-m-Y'), 'submitted_date' => $request->last_submitted_at ? $request->last_submitted_at->format('d-m-Y') : null, 'activity_name' => $activityName, 'activity_type' => $activityTypeName, 'month_year' => $monthYear, 'amount' => $totalAmount, 'action_type' => 'finance_submission', 'advance_type_code' => $advanceTypeCode];
                Mail::to('srexe1.accounts@vnrseeds.com')
                    ->cc([
                        'poojaacharya.vspl@gmail.com',
                        'anishapoddar.vspl@gmail.com'
                    ])
                    ->send(new AdvanceRequestNotification($emailData));
                // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));
                \Log::info("Finance notification sent for employee {$employee->employee_id} in request {$request->id}");

                app(\App\Services\EmailLogService::class)->logSent([
                    'notification_type'     => 'advance_finance_submission',
                    'mail_category'         => 'advance',
                    'subject'               => "Advance Finance Submission - Request #{$request->id}",
                    'recipient_email'       => 'srexe1.accounts@vnrseeds.com',
                    'cc_emails'             => 'poojaacharya.vspl@gmail.com,anishapoddar.vspl@gmail.com',
                    'recipient_employee_id' => $employee->employee_id,
                    'recipient_emp_code'    => $employeeDetails['EmployeeCode'] ?? null,
                    'recipient_name'        => $employeeDetails['EmployeeName'] ?? null,
                    'ref_type'              => 'advance_request',
                    'ref_id'                => $request->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending finance notifications: ' . $e->getMessage());
        }
    }

    private function getRevertTargetLevel(AdvanceRequestActivity $activity, $currentLevel)
    {
        if ($currentLevel === 1) {
            return 0;  // Revert to requester
        }
        return $currentLevel - 1;  // Revert to previous level
    }

    private function sendEmployeeApprovalEmail(AdvanceRequest $request, $employee)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }

            // Get employee details
            $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
            if (!$employeeDetails || empty($employeeDetails['EmailId_Vnr'])) {
                \Log::warning("No email found for employee ID: {$employee->employee_id}");
                return;
            }

            // Get requester details (who submitted the request)
            $requester = $this->getEmployeeDetails($request->req_app_submit_id);

            // Get activity name and type for email
            $activityName = 'N/A';
            $activityTypeName = 'N/A';
            if ($request->activity_name_id) {
                try {
                    $activityNameRecord = DB::table('claimtype')->where('ClaimId', $request->activity_name_id)->where('ClaimStatus', 'A')->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
                }
            }

            // Get activity type from employee's first activity
            $firstActivity = $employee->activities->first();
            if ($firstActivity && $firstActivity->activity_type_id) {
                try {
                    $activityType = DB::table('adv_activity_types')->where('id', $firstActivity->activity_type_id)->first();
                    $activityTypeName = $activityType->type_name ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity type: ' . $e->getMessage());
                }
            }

            // Format month-year
            $monthYear = 'N/A';
            if ($request->month) {
                try {
                    $year = $request->year_id ?? ($request->created_at ? $request->created_at->format('Y') : now()->format('Y'));
                    $monthYear = \Carbon\Carbon::createFromDate($year, $request->month, 1)->format('M-y');
                } catch (\Exception $e) {
                    \Log::warning('Error formatting month-year: ' . $e->getMessage());
                }
            }

            // Get crops and varieties from employee's first activity
            $activityCrops = [];
            $activityVarieties = [];
            if ($firstActivity) {
                if ($firstActivity->activity_crops_id) {
                    try {
                        $cropIds = is_string($firstActivity->activity_crops_id) ? json_decode($firstActivity->activity_crops_id, true) : $firstActivity->activity_crops_id;
                        if (is_array($cropIds) && !empty($cropIds)) {
                            $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                            $activityCrops = $crops->toArray();
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching crops: ' . $e->getMessage());
                    }
                }

                if ($firstActivity->activity_variety_id) {
                    try {
                        $varietyIds = is_string($firstActivity->activity_variety_id) ? json_decode($firstActivity->activity_variety_id, true) : $firstActivity->activity_variety_id;
                        if (is_array($varietyIds) && !empty($varietyIds)) {
                            $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                            $activityVarieties = $varieties->toArray();
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching varieties: ' . $e->getMessage());
                    }
                }
            }

            // Get activity details only for this employee
            $activities = [];
            foreach ($employee->activities as $activity) {
                // Get activity type name
                $actTypeName = '';
                if ($activity->activity_type_id) {
                    try {
                        $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                        $actTypeName = $activityType->type_name ?? '';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity type: ' . $e->getMessage());
                    }
                }

                $activities[] = [
                    'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown',
                    'employee_code' => $employeeDetails['EmployeeCode'] ?? '',
                    'activity_type' => $actTypeName,
                    'activity_name' => $activityName,
                    'amount' => (float) $activity->amount,
                    'purpose' => $activity->purpose ?? ''
                ];
            }

            // Calculate total amount for this employee only
            $totalAmount = $employee->activities->sum('amount');

            // Get the last approver who approved this employee's activities
            $lastApproval = AdvanceActivityApproval::whereIn('advance_request_activity_id', $employee->activities->pluck('id'))
                ->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)
                ->orderBy('approver_date', 'desc')
                ->first();

            $approverName = 'N/A';
            $approvalLevel = 'N/A';
            if ($lastApproval) {
                $approver = $this->getEmployeeDetails($lastApproval->approver_id);
                $approverName = $approver['EmployeeName'] ?? 'N/A';
                $approvalLevel = $lastApproval->approval_level;
            }

            // Prepare email data
            $emailData = [
                'request_no' => $request->id,
                'requester_name' => $employeeDetails['EmployeeName'] ?? 'Unknown',
                'approver_name' => $approverName,
                'amount' => $totalAmount,
                'purpose' => $firstActivity->purpose ?? 'Advance Request',
                'status' => $request->current_status,
                'action_type' => 'approval',
                'approval_level' => $approvalLevel,
                'remarks' => $lastApproval->approver_remark ?? null,
                'created_date' => $request->created_at->format('Y-m-d'),
                'submitted_date' => $request->last_submitted_at ? $request->last_submitted_at->format('Y-m-d') : null,
                'activity_name' => $activityName,
                'activity_type' => $activityTypeName,
                'activity_crops' => !empty($activityCrops) ? implode(', ', $activityCrops) : null,
                'activity_varieties' => !empty($activityVarieties) ? implode(', ', $activityVarieties) : null,
                'month_year' => $monthYear,
                'activities' => $activities
            ];

            // Send email to employee
            Mail::to($employeeDetails['EmailId_Vnr'])->send(new AdvanceRequestNotification($emailData));
            // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));

            \Log::info("Approval email sent to employee {$employee->employee_id} for request {$request->id}");

            app(\App\Services\EmailLogService::class)->logSent([
                'notification_type'     => 'advance_approval',
                'mail_category'         => 'advance',
                'subject'               => "Advance Request #{$request->id} - Approval Update",
                'recipient_employee_id' => $employee->employee_id,
                'recipient_emp_code'    => $employeeDetails['EmployeeCode'] ?? null,
                'recipient_name'        => $employeeDetails['EmployeeName'] ?? null,
                'recipient_email'       => $employeeDetails['EmailId_Vnr'],
                'ref_type'              => 'advance_request',
                'ref_id'                => $request->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending employee approval email: ' . $e->getMessage());
        }
    }

    private function logAction(AdvanceRequest $request, $actionType, $actorId, $targetId = null, $activityId = null, $approvalLevel = null, $remarks = null, $templateId = null)
    {
        AdvanceRequestAction::create(['advance_request_id' => $request->id, 'submission_no' => $request->submission_no, 'action_type' => $actionType, 'action_by' => $actorId, 'action_to' => $targetId, 'related_activity_id' => $activityId, 'related_approval_level' => $approvalLevel, 'email_template_id' => $templateId, 'remarks' => $remarks, 'action_at' => now()]);
    }

    // Email notification methods (simplified for now)
    private function sendApprovalNotifications(AdvanceRequest $request, AdvanceActivityApproval $approval)
    {
        $this->sendEmailNotification($request, $approval, 'approval');
    }

    private function sendRejectionNotifications(AdvanceRequest $request, AdvanceActivityApproval $approval)
    {
        $this->sendEmailNotification($request, $approval, 'rejection');
    }

    private function sendRevertNotifications(AdvanceRequest $request, AdvanceActivityApproval $approval)
    {
        $this->sendEmailNotification($request, $approval, 'revert');
    }

    private function sendEmailNotification(AdvanceRequest $request, AdvanceActivityApproval $approval, $actionType)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }
            // Get approver details from HRIMS
            $approver = $this->getEmployeeDetails($approval->approver_id);
            if (!$approver || empty($approver['EmailId_Vnr'])) {
                \Log::warning("No email found for approver ID: {$approval->approver_id}");
                return;
            }
            // Get requester details
            $requester = $this->getEmployeeDetails($request->req_app_submit_id);

            // Get activity name and type for email
            $activityName = 'N/A';
            $activityTypeName = 'N/A';
            if ($request->activity_name_id) {
                try {
                    $activityNameRecord = DB::table('claimtype')->where('ClaimId', $request->activity_name_id)->where('ClaimStatus', 'A')->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
                }
            }

            // Get activity type from first activity of requester
            $requesterEmployee = $request->employees()->where('employee_id', $request->req_app_submit_id)->first();
            $firstActivity = $requesterEmployee ? $requesterEmployee->activities->first() : null;

            if ($firstActivity && $firstActivity->activity_type_id) {
                try {
                    $activityType = DB::table('adv_activity_types')->where('id', $firstActivity->activity_type_id)->first();
                    $activityTypeName = $activityType->type_name ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity type: ' . $e->getMessage());
                }
            }

            // Format month-year
            $monthYear = 'N/A';
            if ($request->month) {
                try {
                    $year = $request->year_id ?? ($request->created_at ? $request->created_at->format('Y') : now()->format('Y'));
                    $monthYear = \Carbon\Carbon::createFromDate($year, $request->month, 1)->format('M-y');
                } catch (\Exception $e) {
                    \Log::warning('Error formatting month-year: ' . $e->getMessage());
                }
            }

            // Get crops and varieties from requester's first activity
            $activityCrops = [];
            $activityVarieties = [];
            if ($firstActivity) {
                if ($firstActivity->activity_crops_id) {
                    try {
                        $cropIds = is_string($firstActivity->activity_crops_id) ? json_decode($firstActivity->activity_crops_id, true) : $firstActivity->activity_crops_id;
                        if (is_array($cropIds) && !empty($cropIds)) {
                            $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                            $activityCrops = $crops->toArray();
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching crops: ' . $e->getMessage());
                    }
                }

                if ($firstActivity->activity_variety_id) {
                    try {
                        $varietyIds = is_string($firstActivity->activity_variety_id) ? json_decode($firstActivity->activity_variety_id, true) : $firstActivity->activity_variety_id;
                        if (is_array($varietyIds) && !empty($varietyIds)) {
                            $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                            $activityVarieties = $varieties->toArray();
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching varieties: ' . $e->getMessage());
                    }
                }
            }

            // Get activity details only for the requester
            $activities = [];
            if ($requesterEmployee) {
                foreach ($requesterEmployee->activities as $activity) {
                    // Get activity type name
                    $activityTypeName = '';
                    if ($activity->activity_type_id) {
                        try {
                            $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                            $activityTypeName = $activityType->type_name ?? '';
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching activity type: ' . $e->getMessage());
                        }
                    }

                    $activities[] = [
                        'employee_name' => $requester['EmployeeName'] ?? 'Unknown',
                        'employee_code' => $requester['EmployeeCode'] ?? '',
                        'activity_type' => $activityTypeName,
                        'activity_name' => $activityName,
                        'amount' => (float) $activity->amount,
                        'purpose' => $activity->purpose ?? ''
                    ];
                }
            }

            // Calculate total amount for requester only
            $totalAmount = $requesterEmployee ? $requesterEmployee->activities->sum('amount') : 0;

            // Prepare email data
            $emailData = [
                'request_no' => $request->id,
                'requester_name' => $requester['EmployeeName'] ?? 'Unknown',
                'approver_name' => $approver['EmployeeName'],
                'amount' => $totalAmount,
                'purpose' => $firstActivity->purpose ?? 'Advance Request',
                'status' => $request->current_status,
                'action_type' => $actionType,
                'approval_level' => $approval->approval_level,
                'remarks' => $approval->approver_remark ?? $approval->revert_reason,
                'created_date' => $request->created_at->format('Y-m-d'),
                'submitted_date' => $request->last_submitted_at ? $request->last_submitted_at->format('Y-m-d') : null,
                'activity_name' => $activityName,
                'activity_type' => $activityTypeName,
                'activity_crops' => !empty($activityCrops) ? implode(', ', $activityCrops) : null,
                'activity_varieties' => !empty($activityVarieties) ? implode(', ', $activityVarieties) : null,
                'month_year' => $monthYear,
                'activities' => $activities
            ];

            // Send email to requester for status updates
            if ($requester && !empty($requester['EmailId_Vnr'])) {
                Mail::to($requester['EmailId_Vnr'])->send(new AdvanceRequestNotification($emailData));
                // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));

                app(\App\Services\EmailLogService::class)->logSent([
                    'notification_type'     => 'advance_status_update',
                    'mail_category'         => 'advance',
                    'subject'               => "Advance Request #{$request->id} - {$actionType}",
                    'recipient_employee_id' => $request->req_app_submit_id,
                    'recipient_name'        => $requester['EmployeeName'] ?? null,
                    'recipient_email'       => $requester['EmailId_Vnr'],
                    'ref_type'              => 'advance_request',
                    'ref_id'                => $request->id,
                ]);
            }
            // Log email sending
            \Log::info('Email notification sent', ['to' => $requester['EmailId_Vnr'] ?? '', 'type' => $actionType, 'request_id' => $request->id, 'activities_count' => count($activities)]);
        } catch (\Exception $e) {
            \Log::error('Error sending email notification: ' . $e->getMessage());
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
                    gen.EC AS EmployeeCode,
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

    private function sendNextLevelNotifications(AdvanceRequest $request, $activities, $employeeId)
    {
        try {
            // Get activities that moved to next level
            $nextLevelActivities = $activities->filter(function ($activity) {
                return $activity->approval_status === AdvanceRequestActivity::APPROVAL_STATUS_PENDING;
            });
            if ($nextLevelActivities->isEmpty()) {
                return;  // No activities moved to next level
            }
            // Get the current level of these activities (should be same for all)
            $currentLevel = $nextLevelActivities->first()->current_approval_level;
            // Get next level approvers for these activities
            $nextLevelApprovers = AdvanceActivityApproval::whereIn('advance_request_activity_id', $nextLevelActivities->pluck('id'))->where('approval_level', $currentLevel)->where('approver_status', AdvanceActivityApproval::STATUS_PENDING)->get()->unique('approver_id');
            foreach ($nextLevelApprovers as $approval) {
                $this->sendNextLevelEmailNotification($request, $approval, $employeeId, $currentLevel);
            }
            \Log::info("Next level notifications sent for employee {$employeeId} at level {$currentLevel}");
        } catch (\Exception $e) {
            \Log::error('Error sending next level notifications: ' . $e->getMessage());
        }
    }

    private function sendNextLevelEmailNotification(AdvanceRequest $request, AdvanceActivityApproval $approval, $employeeId, $approvalLevel)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }
            $approver = $this->getEmployeeDetails($approval->approver_id);
            if (!$approver || empty($approver['EmailId_Vnr'])) {
                \Log::warning("No email found for next level approver ID: {$approval->approver_id}");
                return;
            }
            $requester = $this->getEmployeeDetails($request->req_app_submit_id);
            $employee = $this->getEmployeeDetails($employeeId);
            // Get activity details for email table
            $activities = $this->getActivityDetailsForEmail($request);
            $emailData = ['request_no' => $request->id, 'requester_name' => $requester['EmployeeName'] ?? 'Unknown', 'employee_name' => $employee['EmployeeName'] ?? 'Unknown', 'approver_name' => $approver['EmployeeName'], 'amount' => $request->activities->sum('amount'), 'purpose' => $request->activities->first()->purpose ?? 'Advance Request', 'status' => $request->current_status, 'approval_level' => $approvalLevel, 'created_date' => $request->created_at->format('Y-m-d'), 'submitted_date' => $request->last_submitted_at->format('Y-m-d'), 'action_type' => 'next_level_notification', 'activities' => $activities];
            // Send email
            Mail::to($approver['EmailId_Vnr'])->send(new AdvanceRequestNotification($emailData));
            // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));
            \Log::info('Next level email notification sent', ['to' => $approver['EmailId_Vnr'], 'request_id' => $request->id, 'employee_id' => $employeeId, 'approval_level' => $approvalLevel]);

            app(\App\Services\EmailLogService::class)->logSent([
                'notification_type'     => 'advance_next_level_approval',
                'mail_category'         => 'advance',
                'stage'                 => (string) $approvalLevel,
                'subject'               => "Advance Request #{$request->id} - Pending Approval (Level {$approvalLevel})",
                'recipient_employee_id' => $approval->approver_id,
                'recipient_name'        => $approver['EmployeeName'] ?? null,
                'recipient_email'       => $approver['EmailId_Vnr'],
                'ref_type'              => 'advance_request',
                'ref_id'                => $request->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending next level email notification: ' . $e->getMessage());
        }
    }

    private function getActivityDetailsForEmail($request)
    {
        $activities = [];
        foreach ($request->activities as $activity) {
            $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
            // Get activity type name
            $activityTypeName = '';
            if ($activity->activity_type_id) {
                try {
                    $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                    $activityTypeName = $activityType->type_name ?? '';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity type: ' . $e->getMessage());
                }
            }
            // Get activity name from claimtype table (stored at request level)
            $activityName = '';
            $requestActivityNameId = $activity->employee->advanceRequest->activity_name_id;
            if ($requestActivityNameId) {
                try {
                    $activityNameRecord = DB::table('claimtype')
                        ->where('ClaimId', $requestActivityNameId)
                        ->where('ClaimStatus', 'A')  // Only active claims
                        ->first();
                    $activityName = $activityNameRecord->ClaimName ?? '';
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
            $activities[] = ['employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? '', 'activity_type' => $activityTypeName, 'activity_name' => $activityName, 'crops' => !empty($cropNames) ? implode(', ', $cropNames) : '', 'amount' => (float) $activity->amount, 'purpose' => $activity->purpose ?? ''];
        }
        return $activities;
    }

    public function employeeApprovalSummary(Request $request, $requestId)
    {
        try {
            $advanceRequest = AdvanceRequest::with(['employees.activities.approvals'])->findOrFail($requestId);
            $employeeSummary = [];
            foreach ($advanceRequest->employees as $employee) {
                $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                // Get approval status for this employee
                $totalActivities = $employee->activities->count();
                $approvedActivities = $employee->activities->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->count();
                $rejectedActivities = $employee->activities->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_REJECTED)->count();
                $pendingActivities = $employee->activities->whereIn('approval_status', [AdvanceRequestActivity::APPROVAL_STATUS_PENDING])->count();
                // Get current approval levels for this employee's activities
                $currentLevels = $employee->activities->pluck('current_approval_level')->unique()->sort()->values();
                $maxLevel = $employee->activities->max('current_approval_level');
                // Determine overall status for this employee
                $employeeStatus = 'Pending';
                if ($approvedActivities === $totalActivities) {
                    $employeeStatus = 'Approved';
                } elseif ($rejectedActivities > 0) {
                    $employeeStatus = 'Partially/Fully Rejected';
                } elseif ($pendingActivities > 0) {
                    $employeeStatus = "In Progress (Level {$maxLevel})";
                }
                $employeeSummary[] = ['employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'department' => $employeeDetails['DepartmentName'] ?? '', 'designation' => $employeeDetails['Designation'] ?? '', 'total_activities' => $totalActivities, 'approved_activities' => $approvedActivities, 'rejected_activities' => $rejectedActivities, 'pending_activities' => $pendingActivities, 'current_approval_levels' => $currentLevels->toArray(), 'max_approval_level' => $maxLevel, 'employee_status' => $employeeStatus, 'total_amount' => $employee->activities->sum('amount'), 'approved_amount' => $employee->activities->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED)->sum('amount')];
            }
            return $this->jsonSuccess(['request_id' => $requestId, 'request_status' => $advanceRequest->current_status, 'employees' => $employeeSummary], 'Employee approval summary retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve employee approval summary: ' . $e->getMessage());
        }
    }

    private function getApproverAdvanceHistory($approverId, $currentRequestId = null, $specificEmployeeId = null)
    {
        try {
            $advanceHistory = [];
            // Get advance requests that have been approved by this approver (excluding current request)
            $approvedRequestIds = DB::table('advance_activity_approvals')->join('advance_request_activities', 'advance_activity_approvals.advance_request_activity_id', '=', 'advance_request_activities.id')->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')->where('advance_activity_approvals.approver_id', $approverId)->where('advance_activity_approvals.approver_status', AdvanceActivityApproval::STATUS_APPROVED)->when($currentRequestId, function ($query, $currentRequestId) {
                return $query->where('advance_request_employees.advance_request_id', '!=', $currentRequestId);
            })->when($specificEmployeeId, function ($query, $specificEmployeeId) {
                return $query->where('advance_request_employees.employee_id', $specificEmployeeId);
            })->pluck('advance_request_employees.advance_request_id')->unique()->take(10);  // Limit to last 10 approved requests
            if ($approvedRequestIds->isEmpty()) {
                return [];
            }
            // Get the actual requests with their details
            $requests = AdvanceRequest::with(['employees.activities'])->whereIn('id', $approvedRequestIds)->orderBy('last_submitted_at', 'desc')->get();
            foreach ($requests as $request) {
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
                // Get employees that were approved by this approver in this request
                // If specificEmployeeId is provided, only get that employee's data
                $approvedEmployees = [];
                foreach ($request->employees as $employee) {
                    // Skip if we're looking for a specific employee and this isn't the one
                    if ($specificEmployeeId && $employee->employee_id != $specificEmployeeId) {
                        continue;
                    }
                    // Check if this employee has activities approved by this approver
                    $hasApprovedActivities = DB::table('advance_activity_approvals')->join('advance_request_activities', 'advance_activity_approvals.advance_request_activity_id', '=', 'advance_request_activities.id')->where('advance_request_activities.advance_request_employee_id', $employee->id)->where('advance_activity_approvals.approver_id', $approverId)->where('advance_activity_approvals.approver_status', AdvanceActivityApproval::STATUS_APPROVED)->exists();
                    if ($hasApprovedActivities) {
                        $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                        // Get ALL approval levels for this employee's activities (not just current approver)
                        $allApprovals = [];
                        $approvalLevelSummary = [];
                        foreach ($employee->activities as $activity) {
                            // Get all approvals for this activity
                            $activityApprovals = DB::table('advance_activity_approvals')->where('advance_request_activity_id', $activity->id)->where('submission_no', $request->submission_no)->orderBy('approval_level')->get();
                            foreach ($activityApprovals as $approval) {
                                $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                                $allApprovals[] = ['activity_id' => $activity->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'activity_amount' => (float) $activity->amount, 'activity_purpose' => $activity->purpose];
                                // Build level summary
                                $level = $approval->approval_level;
                                if (!isset($approvalLevelSummary[$level])) {
                                    $approvalLevelSummary[$level] = ['level' => $level, 'approver_name' => $approverDetails['EmployeeName'] ?? 'Unknown', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'status' => $approval->approver_status, 'total_amount' => 0, 'approved_amount' => 0, 'activities_count' => 0, 'date' => $approval->approver_date, 'remark' => $approval->approver_remark];
                                }
                                $approvalLevelSummary[$level]['total_amount'] += (float) $activity->amount;
                                if ($approval->approver_amount) {
                                    $approvalLevelSummary[$level]['approved_amount'] += (float) $approval->approver_amount;
                                }
                                $approvalLevelSummary[$level]['activities_count']++;
                            }
                        }
                        // Get finance details for this employee's activities
                        $financeDetails = [];
                        $paymentDetails = [];
                        $totalFinanceAmount = 0;
                        $totalPaymentAmount = 0;
                        $financeStatus = 'Not Processed';
                        $paymentStatus = 'Not Processed';
                        foreach ($employee->activities as $activity) {
                            // Finance details
                            if ($activity->finance_status) {
                                $financeApproverDetails = null;
                                if ($activity->finance_id) {
                                    $financeApproverDetails = $this->getEmployeeDetails($activity->finance_id);
                                }
                                $financeDetails[] = ['activity_id' => $activity->id, 'finance_status' => $activity->finance_status, 'finance_approved_amount' => $activity->finance_approved_amount ? (float) $activity->finance_approved_amount : null, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'finance_approver_id' => $activity->finance_id, 'finance_approver_name' => $financeApproverDetails['EmployeeName'] ?? 'Unknown', 'finance_approver_designation' => $financeApproverDetails['Designation'] ?? 'N/A', 'activity_amount' => (float) $activity->amount, 'activity_purpose' => $activity->purpose];
                                if ($activity->finance_approved_amount) {
                                    $totalFinanceAmount += (float) $activity->finance_approved_amount;
                                }
                                $financeStatus = $activity->finance_status;
                            }
                            // Payment details
                            if ($activity->payment_status) {
                                $paymentApproverDetails = null;
                                if ($activity->payment_id) {
                                    $paymentApproverDetails = $this->getEmployeeDetails($activity->payment_id);
                                }
                                $paymentDetails[] = ['activity_id' => $activity->id, 'payment_status' => $activity->payment_status, 'payment_amount' => $activity->payment_amount ? (float) $activity->payment_amount : null, 'payment_remark' => $activity->payment_remark, 'payment_date' => $activity->payment_date, 'payment_processor_id' => $activity->payment_id, 'payment_processor_name' => $paymentApproverDetails['EmployeeName'] ?? 'Unknown', 'payment_processor_designation' => $paymentApproverDetails['Designation'] ?? 'N/A', 'activity_amount' => (float) $activity->amount, 'activity_purpose' => $activity->purpose, 'finance_approved_amount' => $activity->finance_approved_amount ? (float) $activity->finance_approved_amount : null];
                                if ($activity->payment_amount) {
                                    $totalPaymentAmount += (float) $activity->payment_amount;
                                }
                                $paymentStatus = $activity->payment_status;
                            }
                        }
                        // Get current approver's specific details
                        $currentApproverInfo = DB::table('advance_activity_approvals')->join('advance_request_activities', 'advance_activity_approvals.advance_request_activity_id', '=', 'advance_request_activities.id')->where('advance_request_activities.advance_request_employee_id', $employee->id)->where('advance_activity_approvals.approver_id', $approverId)->where('advance_activity_approvals.approver_status', AdvanceActivityApproval::STATUS_APPROVED)->select(['advance_activity_approvals.approval_level', 'advance_activity_approvals.approver_date', 'advance_activity_approvals.approver_amount', 'advance_activity_approvals.approver_remark'])->first();
                        // Get total amounts
                        $totalAmount = $employee->activities->sum('amount');
                        $currentApproverAmount = $currentApproverInfo->approver_amount ?? $totalAmount;
                        $approvedEmployees[] = ['employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'total_amount' => (float) $totalAmount, 'activities_count' => $employee->activities->count(),
                            // Current approver's details
                            'current_approver_level' => $currentApproverInfo->approval_level ?? null, 'current_approver_amount' => (float) $currentApproverAmount, 'current_approver_date' => $currentApproverInfo->approver_date ?? null, 'current_approver_remark' => $currentApproverInfo->approver_remark ?? null,
                            // All approval levels summary
                            'approval_levels_summary' => array_values($approvalLevelSummary),
                            // Finance details
                            'finance_status' => $financeStatus, 'total_finance_amount' => $totalFinanceAmount, 'finance_details' => $financeDetails,
                            // Payment details
                            'payment_status' => $paymentStatus, 'total_payment_amount' => $totalPaymentAmount, 'payment_details' => $paymentDetails,
                            // Complete approval history
                            'all_approvals' => $allApprovals];
                    }
                }
                if (!empty($approvedEmployees)) {
                    // Calculate overall totals
                    $totalRequestAmount = array_sum(array_column($approvedEmployees, 'total_amount'));
                    $totalApprovedAmount = array_sum(array_column($approvedEmployees, 'current_approver_amount'));
                    $totalFinanceAmount = array_sum(array_column($approvedEmployees, 'total_finance_amount'));
                    $totalPaymentAmount = array_sum(array_column($approvedEmployees, 'total_payment_amount'));
                    $advanceHistory[] = ['request_id' => $request->id, 'advance_type_id' => $request->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $request->activity_name_id, 'activity_name' => $activityName, 'month' => $request->month, 'submission_no' => $request->submission_no, 'submitted_at' => $request->last_submitted_at, 'current_status' => $request->current_status, 'total_employees_approved' => count($approvedEmployees), 'total_request_amount' => $totalRequestAmount, 'total_approved_amount' => $totalApprovedAmount, 'total_finance_amount' => $totalFinanceAmount, 'total_payment_amount' => $totalPaymentAmount, 'created_at' => $request->created_at, 'updated_at' => $request->updated_at, 'approved_employees' => $approvedEmployees];
                }
            }
            // Sort by submitted date (most recent first)
            usort($advanceHistory, function ($a, $b) {
                return $b['submitted_at'] <=> $a['submitted_at'];
            });
            return $advanceHistory;
        } catch (\Exception $e) {
            \Log::warning('Error fetching approver advance history for approver ' . $approverId . ': ' . $e->getMessage());
            return [];
        }
    }

    protected function jsonSuccess($data = null, $message = 'Success')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function jsonError($message = 'Error', $code = 400)
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}
