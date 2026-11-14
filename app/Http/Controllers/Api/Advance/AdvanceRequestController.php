<?php
namespace App\Http\Controllers\Api\Advance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advance\StoreDraftRequest;
use App\Http\Resources\AdvanceRequestResource;
use App\Mail\AdvanceRequestNotification;
use App\Models\AdvanceActivityApproval;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestAction;
use App\Models\AdvanceRequestActivity;
use App\Models\AdvanceRequestEmployee;
use App\Models\DigiNextPaymentFile;
use App\Models\HRMEmployeeGeneral;
use App\Services\CentralNotificationService;
use App\Traits\HandlesNullValues;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class AdvanceRequestController extends Controller
{
    use HandlesNullValues;

    private $currentAdvanceRequest = null;
    private CentralNotificationService $centralNotification;

    public function __construct()
    {
        $this->centralNotification = new CentralNotificationService();
    }

    public function index(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $status = $request->get('status');
            // Get the Activity advance type ID
            $activityType = DB::table('advance_request_types')->where('type_code', 'Act')->where('is_active', 1)->first();
            if (!$activityType) {
                return $this->jsonError('Activity advance type not found');
            }
            $query = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->where('created_by', $userId)->where('advance_type_id', $activityType->id);
            if ($status) {
                $query->where('current_status', $status);
            }
            $requests = $query->orderBy('created_at', 'desc')->get();
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
                // Get advance type information - hardcoded for Activity type since we're filtering for Activity only
                $advanceTypeName = 'Activity';
                $advanceTypeCode = 'Act';
                // Prepare request data object
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approval_summary' => $approvalSummary];
                // Process each employee for this request
                foreach ($advanceRequest->employees as $employee) {
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
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
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'N/A', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
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
                    // Add employee with req_data inside
                    $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                }
            }
            return $this->jsonSuccess($allEmployees, 'Activity advance requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve activity advance requests: ' . $e->getMessage());
        }
    }

    public function show($requestId)
    {
        try {
            $request = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions.emailTemplate'])->findOrFail($requestId);
            return $this->jsonSuccess(new AdvanceRequestResource($request), 'Advance request details retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve request details: ' . $e->getMessage());
        }
    }

    // public function storeDraft(StoreDraftRequest $request)
    // {
    //     // Handle OPTIONS preflight request first
    //     if ($request->isMethod('OPTIONS')) {
    //         return response('', 200)->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')->header('Access-Control-Allow-Credentials', 'true')->header('Access-Control-Max-Age', '86400');
    //     }
    //     try {
    //         $userId = $request->get('user_id');
    //         $data = $request->validated();
    //         \Log::info('Validated data received:', $data);
    //         $data['request']['created_by'] = $userId;
    //         $data['request']['updated_by'] = $userId;
    //         foreach ($data['employees'] as &$employee) {
    //             $employee['created_by'] = $userId;
    //             $employee['updated_by'] = $userId;
    //             if (isset($employee['activities'])) {
    //                 foreach ($employee['activities'] as &$activity) {
    //                     $activity['created_by'] = $userId;
    //                     $activity['updated_by'] = $userId;
    //                 }
    //             }
    //         }
    //         // Create the draft request
    //         \Log::info('Creating draft request...');
    //         $advanceRequest = $this->createOrUpdateDraft($data);
    //         \Log::info('Draft request created with ID:', ['id' => $advanceRequest->id]);
    //         // Automatically submit the request after creating it
    //         \Log::info('Submitting request...');
    //         $submittedRequest = $this->submitRequest($advanceRequest->id, $userId);
    //         \Log::info('Request submitted successfully');
    //         // Return response with CORS headers
    //         return response()->json(['success' => true, 'data' => new AdvanceRequestResource($submittedRequest), 'message' => 'Draft advance request created and submitted successfully'])->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')->header('Access-Control-Allow-Credentials', 'true');
    //     } catch (Exception $e) {
    //         \Log::error('Error in storeDraft:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'request_data' => $request->all()]);
    //         // Return error response with CORS headers
    //         return response()->json(['success' => false, 'message' => 'Failed to create and submit draft request: ' . $e->getMessage()], 500)->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')->header('Access-Control-Allow-Credentials', 'true');
    //     }
    // }

    public function storeDraft(StoreDraftRequest $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        try {
            $userId = $request->get('user_id');
            $data = $request->validated();

            \Log::info('Validated data received:', $data);

            $data['request']['created_by'] = $userId;
            $data['request']['updated_by'] = $userId;

            foreach ($data['employees'] as &$employee) {
                $employee['created_by'] = $userId;
                $employee['updated_by'] = $userId;

                if (isset($employee['activities'])) {
                    foreach ($employee['activities'] as &$activity) {
                        $activity['created_by'] = $userId;
                        $activity['updated_by'] = $userId;
                    }
                }
            }

            // ADV-FX-ELIG-01: foreign currency only for eligible employees (self and team members)
            if ($fxMsg = $this->fxIneligible($data)) {
                return response()->json(['success' => false, 'message' => $fxMsg], 422);
            }

            // Create draft
            \Log::info('Creating draft request...');
            $advanceRequest = $this->createOrUpdateDraft($data);

            \Log::info('Draft request created with ID:', ['id' => $advanceRequest->id]);

            // Submit request
            \Log::info('Submitting request...');
            $submittedRequest = $this->submitRequest($advanceRequest->id, $userId);

            $approvalRequired = true;

            try {
                $employee = DB::connection('hrims')
                    ->table('hrm_employee_general')
                    ->select('DepartmentId', 'EmpFunction', 'DesigId')
                    ->where('EmployeeID', $userId)
                    ->first();

                if ($employee) {
                    $config = DB::table('advance_request_masters')
                        ->where('department_id', $employee->DepartmentId)
                        ->where('advance_type_id', $submittedRequest->advance_type_id)
                        ->where('is_active', 1)
                        ->whereDate('effective_from', '<=', Carbon::today())
                        ->where(function ($q) {
                            $q
                                ->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', Carbon::today());
                        })
                        ->first();

                    if ($config && !empty($config->vertical_designation)) {
                        $verticalDesignation = json_decode($config->vertical_designation, true);

                        if (is_array($verticalDesignation)) {
                            $verticalId = $employee->EmpFunction;
                            $designationId = $employee->DesigId;

                            if (isset($verticalDesignation[$verticalId])) {
                                foreach ($verticalDesignation[$verticalId] as $designation) {
                                    if (
                                        is_array($designation) &&
                                        isset($designation['id']) &&
                                        $designation['id'] == $designationId
                                    ) {
                                        // approval_required = 1 → auto approve
                                        $approvalRequired = !($designation['approval_required'] == 1);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $innerException) {
                Log::error('Auto approval check failed', [
                    'error' => $innerException->getMessage(),
                    'user_id' => $userId
                ]);

                $approvalRequired = true;
            }

            // Move request directly to Finance if approval not required
            if (!$approvalRequired) {
                $submittedRequest->update([
                    'current_status' => AdvanceRequest::STATUS_FINANCE
                ]);

                // Get employee ids linked to this request
                $employeeIds = AdvanceRequestEmployee::where('advance_request_id', $submittedRequest->id)
                    ->pluck('id');

                // Get activity ids
                $activityIds = AdvanceRequestActivity::whereIn('advance_request_employee_id', $employeeIds)
                    ->pluck('id');

                // Update activities
                AdvanceRequestActivity::whereIn('id', $activityIds)
                    ->update([
                        'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_APPROVED,
                        'current_approval_level' => 0
                    ]);

                // Update approval records and set approver_amount = activity amount
                DB::table('advance_activity_approvals as aaa')
                    ->join('advance_request_activities as ara', 'ara.id', '=', 'aaa.advance_request_activity_id')
                    ->whereIn('aaa.advance_request_activity_id', $activityIds)
                    ->update([
                        'aaa.approver_amount' => DB::raw('ara.amount'),
                        'aaa.approver_remark' => 'Auto-approved based on vertical designation rule',
                        'aaa.approver_status' => 'Approved',
                        'aaa.approver_date' => now()
                    ]);
            }

            \Log::info('Request submitted successfully');

            return response()
                ->json([
                    'success' => true,
                    'data' => (new AdvanceRequestResource($submittedRequest))->additional([
                        'approval_required' => $approvalRequired
                    ]),
                    'message' => 'Draft advance request created and submitted successfully'
                ])
                ->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true');
        } catch (Exception $e) {
            \Log::error('Error in storeDraft:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()
                ->json([
                    'success' => false,
                    'message' => 'Failed to create and submit draft request: ' . $e->getMessage()
                ], 500)
                ->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    public function updateDraft(StoreDraftRequest $request, $requestId)
    {
        try {
            $userId = $request->get('user_id');
            $data = $request->validated();
            $data['request']['updated_by'] = $userId;
            foreach ($data['employees'] as &$employee) {
                $employee['updated_by'] = $userId;
                if (isset($employee['activities'])) {
                    foreach ($employee['activities'] as &$activity) {
                        $activity['updated_by'] = $userId;
                    }
                }
            }
            if ($fxMsg = $this->fxIneligible($data)) {   // ADV-FX-ELIG-01
                return response()->json(['success' => false, 'message' => $fxMsg], 422);
            }
            $advanceRequest = $this->createOrUpdateDraft($data, $requestId);
            return $this->jsonSuccess(new AdvanceRequestResource($advanceRequest), 'Draft advance request updated successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to update draft request: ' . $e->getMessage());
        }
    }

    public function submit(Request $request, $requestId)
    {
        try {
            $userId = $request->get('user_id');
            $advanceRequest = $this->submitRequest($requestId, $userId);

            // Notify Central Notification Service - Raise action
            try {
                $this->centralNotification->raiseAdvanceRequest(
                    "{$requestId}",
                    $userId,
                    $userId, // applicant code as fallback for name
                    '', // approver will be determined by approval workflow
                    '',
                    1,
                    "Advance Request #{$requestId} Submitted",
                    "Advance request submitted for approval",
                    ['request_id' => $requestId]
                );
            } catch (\Throwable $e) {
                Log::warning('Central Notification (advance submit) failed: ' . $e->getMessage());
            }

            return $this->jsonSuccess(new AdvanceRequestResource($advanceRequest), 'Advance request submitted successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to submit request: ' . $e->getMessage());
        }
    }

    // public function submit(Request $request, $requestId)
    // {
    //     try {
    //         $userId = $request->get('user_id');
    //         $advanceRequest = $this->submitRequest($requestId, $userId);
    //         $approvalRequired = true;
    //         try {
    //             $employee = DB::connection('hrims')
    //                 ->table('hrm_employee_general')
    //                 ->select('DepartmentId', 'EmpFunction', 'DesigId')
    //                 ->where('EmployeeID', $userId)
    //                 ->first();
    //             if ($employee) {
    //                 $config = DB::table('advance_request_masters')
    //                     ->where('department_id', $employee->DepartmentId)
    //                     ->where('advance_type_id', $advanceRequest->advance_type_id)
    //                     ->where('is_active', 1)
    //                     ->whereDate('effective_from', '<=', Carbon::today())
    //                     ->where(function ($q) {
    //                         $q
    //                             ->whereNull('effective_to')
    //                             ->orWhereDate('effective_to', '>=', Carbon::today());
    //                     })
    //                     ->first();
    //                 if ($config && !empty($config->vertical_designation)) {
    //                     $verticalDesignation = json_decode($config->vertical_designation, true);
    //                     if (is_array($verticalDesignation)) {
    //                         $verticalId = $employee->EmpFunction;
    //                         $designationId = $employee->DesigId;
    //                         if (isset($verticalDesignation[$verticalId])) {
    //                             foreach ($verticalDesignation[$verticalId] as $designation) {
    //                                 if (
    //                                     is_array($designation) &&
    //                                     isset($designation['id']) &&
    //                                     $designation['id'] == $designationId
    //                                 ) {
    //                                     $approvalRequired = !($designation['approval_required'] == 1);
    //                                     break;
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //         } catch (\Exception $innerException) {
    //             Log::error('Auto approval check failed', [
    //                 'error' => $innerException->getMessage(),
    //                 'user_id' => $userId
    //             ]);
    //             $approvalRequired = true;
    //         }
    //         /** If approval NOT required → move request directly to Finance */
    //         if (!$approvalRequired) {
    //             $advanceRequest->update([
    //                 'current_status' => AdvanceRequest::STATUS_FINANCE
    //             ]);
    //             AdvanceRequestActivity::whereHas('employee', function ($q) use ($advanceRequest) {
    //                 $q->where('advance_request_id', $advanceRequest->id);
    //             })->update([
    //                 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_APPROVED,
    //                 'current_approval_level' => 0
    //             ]);
    //         }
    //         $resource = (new AdvanceRequestResource($advanceRequest))->additional(['approval_required' => $approvalRequired]);
    //         return $this->jsonSuccess($resource, 'Advance request submitted successfully');
    //     } catch (Exception $e) {
    //         return $this->jsonError('Failed to submit request: ' . $e->getMessage());
    //     }
    // }

    public function resubmit(Request $request, $requestId)
    {
        try {
            $userId = $request->get('user_id');
            $advanceRequest = $this->resubmitRequest($requestId, $userId);
            return $this->jsonSuccess(new AdvanceRequestResource($advanceRequest), 'Advance request resubmitted successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to resubmit request: ' . $e->getMessage());
        }
    }

    public function employeeSummary(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $month = $request->get('month');
            $year = $request->get('year', date('Y'));
            // Get employee-wise summary
            $query = AdvanceRequestActivity::join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')->join('advance_requests', 'advance_request_employees.advance_request_id', '=', 'advance_requests.id')->select(['advance_request_employees.employee_id', DB::raw('COUNT(advance_request_activities.id) as total_activities'), DB::raw('SUM(advance_request_activities.amount) as total_amount'), DB::raw('SUM(advance_request_activities.finance_approved_amount) as total_finance_approved'), DB::raw('COUNT(CASE WHEN advance_request_activities.approval_status = "Approved" THEN 1 END) as approved_activities'), DB::raw('COUNT(CASE WHEN advance_request_activities.finance_status = "Approved" THEN 1 END) as finance_approved_activities')])->groupBy('advance_request_employees.employee_id');
            if ($userId) {
                $query->where('advance_requests.created_by', $userId);
            }
            if ($month) {
                $query->whereMonth('advance_requests.created_at', $month);
            }
            $query->whereYear('advance_requests.created_at', $year);
            $summaryData = $query->get();
            // Enhance with employee details
            $employeeSummary = [];
            foreach ($summaryData as $summary) {
                $employeeDetails = $this->getEmployeeDetails($summary->employee_id);
                $employeeSummary[] = ['employee_id' => $summary->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $summary->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'total_activities' => (int) $summary->total_activities, 'total_amount' => (float) $summary->total_amount, 'total_finance_approved' => (float) $summary->total_finance_approved, 'approved_activities' => (int) $summary->approved_activities, 'finance_approved_activities' => (int) $summary->finance_approved_activities, 'pending_activities' => (int) $summary->total_activities - (int) $summary->approved_activities, 'approval_percentage' => $summary->total_activities > 0 ? round(($summary->approved_activities / $summary->total_activities) * 100, 2) : 0];
            }
            return $this->jsonSuccess($employeeSummary, 'Employee summary retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve employee summary: ' . $e->getMessage());
        }
    }

    public function tableView(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $status = $request->get('status');
            $month = $request->get('month');
            $year = $request->get('year', date('Y'));
            $employeeId = $request->get('employee_id');
            // Build query for activities with employee and request details
            $query = AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')->join('advance_requests', 'advance_request_employees.advance_request_id', '=', 'advance_requests.id');
            // Apply filters
            if ($userId) {
                $query->where('advance_requests.created_by', $userId);
            }
            if ($status) {
                $query->where('advance_requests.current_status', $status);
            }
            if ($employeeId) {
                $query->where('advance_request_employees.employee_id', $employeeId);
            }
            if ($month) {
                $query->whereMonth('advance_requests.created_at', $month);
            }
            $query->whereYear('advance_requests.created_at', $year);
            // Select specific fields for table view
            $query->select(['advance_request_activities.*', 'advance_request_employees.employee_id', 'advance_requests.id as request_id', 'advance_requests.current_status', 'advance_requests.advance_type_id', 'advance_requests.activity_name_id as request_activity_name_id', 'advance_requests.created_at as request_created_at']);
            $activities = $query->orderBy('advance_requests.created_at', 'desc')->get();
            // Transform data for table view
            $tableData = [];
            foreach ($activities as $activity) {
                // Get employee details from HRIMS
                $employeeDetails = $this->getEmployeeDetails($activity->employee_id);
                // Get crop names from HRIMS database
                $cropNames = [];
                if ($activity->activity_crops_id) {
                    $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                    if (is_array($cropIds)) {
                        try {
                            $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                            $cropNames = $crops->toArray();
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching crop names: ' . $e->getMessage());
                        }
                    }
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
                // Get activity name from claimtype table (stored at request level)
                $activityName = 'N/A';
                if ($activity->request_activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')
                            ->where('ClaimId', $activity->request_activity_name_id)
                            ->where('ClaimStatus', 'A')  // Only active claims
                            ->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }
                // Get advance type information
                $advanceTypeName = 'N/A';
                $advanceTypeCode = 'N/A';
                if ($activity->advance_type_id) {
                    try {
                        $advanceTypeRecord = DB::table('advance_request_types')->where('id', $activity->advance_type_id)->where('is_active', 1)->first();
                        if ($advanceTypeRecord) {
                            $advanceTypeName = $advanceTypeRecord->type_name ?? 'N/A';
                            $advanceTypeCode = $advanceTypeRecord->type_code ?? 'N/A';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching advance type: ' . $e->getMessage());
                    }
                }
                $tableData[] = ['request_id' => $activity->request_id, 'activity_id' => $activity->id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $activity->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'advance_type_id' => $activity->advance_type_id, 'advance_type_name' => $advanceTypeName, 'advance_type_code' => $advanceTypeCode, 'activity_type' => $activityTypeName, 'activity_name' => $activityName, 'crops' => implode(', ', $cropNames), 'amount' => (float) $activity->amount, 'finance_approved_amount' => (float) $activity->finance_approved_amount, 'purpose' => $activity->purpose, 'status' => $activity->current_status, 'approval_status' => $activity->approval_status, 'finance_status' => $activity->finance_status, 'activity_date' => $activity->activity_date, 'created_date' => $activity->request_created_at, 'month' => date('F Y', strtotime($activity->request_created_at))];
            }
            $response = ['data' => $tableData, 'summary' => ['total_records' => count($tableData), 'total_amount' => array_sum(array_column($tableData, 'amount')), 'total_finance_approved' => array_sum(array_column($tableData, 'finance_approved_amount')), 'unique_employees' => count(array_unique(array_column($tableData, 'employee_id')))]];
            return $this->jsonSuccess($response, 'Advance requests table data retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve table data: ' . $e->getMessage());
        }
    }

    private function createOrUpdateDraft(array $data, $requestId = null)
    {
        return DB::transaction(function () use ($data, $requestId) {
            \Log::info('createOrUpdateDraft called with data:', $data);
            if ($requestId) {
                $request = AdvanceRequest::findOrFail($requestId);
                if (!in_array($request->current_status, [AdvanceRequest::STATUS_DRAFT, AdvanceRequest::STATUS_REVERTED])) {
                    throw new Exception('Request cannot be modified in current status');
                }
                // Determine advance type if not already set
                if (!isset($data['request']['advance_type_id']) && isset($data['request']['activity_name_id'])) {
                    $data['request']['advance_type_id'] = $this->determineAdvanceTypeId($data['request']['activity_name_id']);
                }
                \Log::info('Updating existing request with data:', $data['request']);
                $request->update($data['request']);
                $actionType = AdvanceRequestAction::ACTION_DRAFT_SAVE;
            } else {
                $data['request']['current_status'] = AdvanceRequest::STATUS_DRAFT;
                $data['request']['submission_no'] = 1;
                // Determine advance type if not already set
                if (!isset($data['request']['advance_type_id']) && isset($data['request']['activity_name_id'])) {
                    $data['request']['advance_type_id'] = $this->determineAdvanceTypeId($data['request']['activity_name_id']);
                }
                \Log::info('Creating new request with data:', $data['request']);
                $request = AdvanceRequest::create($data['request']);
                \Log::info('Created request with ID and advance_type_id:', ['id' => $request->id, 'advance_type_id' => $request->advance_type_id]);
                $actionType = AdvanceRequestAction::ACTION_DRAFT_SAVE;
            }
            if (isset($data['employees'])) {
                if ($requestId) {
                    $request->employees()->delete();
                }
                foreach ($data['employees'] as $employeeData) {
                    $employeeData['advance_request_id'] = $request->id;
                    $employee = AdvanceRequestEmployee::create($employeeData);
                    if (isset($employeeData['activities'])) {
                        foreach ($employeeData['activities'] as $activityData) {
                            $activityData['advance_request_employee_id'] = $employee->id;
                            $activityData['current_approval_level'] = 0;
                            $activityData['approval_status'] = AdvanceRequestActivity::APPROVAL_STATUS_PENDING;

                            // Handle is_tour_travel field
                            \Log::info('Before is_tour_travel processing:', [
                                'isset' => isset($activityData['is_tour_travel']),
                                'value' => $activityData['is_tour_travel'] ?? 'NOT SET',
                                'type' => gettype($activityData['is_tour_travel'] ?? null)
                            ]);

                            if (isset($activityData['is_tour_travel'])) {
                                $val = $activityData['is_tour_travel'];
                                $activityData['is_tour_travel'] = in_array($val, ['Y', 'y', '1', 1, true, 'true'], true) ? 'Y' : 'N';
                            } else {
                                $activityData['is_tour_travel'] = 'N';
                            }

                            \Log::info('After is_tour_travel processing:', [
                                'value' => $activityData['is_tour_travel']
                            ]);

                            \Log::info('Creating activity with data:', $activityData);
                            AdvanceRequestActivity::create($activityData);
                        }
                    }
                }
            }
            $this->logAction($request, $actionType, $data['request']['req_app_submit_id'] ?? 1);
            return $request->load(['employees.activities', 'actions']);
        });
    }

    private function determineAdvanceTypeId($activityNameId)
    {
        try {
            if (!$activityNameId) {
                // Default to Act type
                $activityType = DB::table('advance_request_types')->where('type_code', 'Act')->where('is_active', 1)->first();
                return $activityType ? $activityType->id : 1;
            }
            // Get claim type details
            $claimType = DB::table('claimtype')->where('ClaimId', $activityNameId)->first();
            if ($claimType) {
                $claimName = strtolower($claimType->ClaimName);
                // Check for expense-related keywords
                if (stripos($claimName, 'expense') !== false || stripos($claimName, 'travel') !== false || stripos($claimName, 'conveyance') !== false || stripos($claimName, 'accommodation') !== false) {
                    $expenseType = DB::table('advance_request_types')->where('type_code', 'Exp')->where('is_active', 1)->first();
                    return $expenseType ? $expenseType->id : 2;
                }
                // Check for other/special advance keywords
                if (stripos($claimName, 'special') !== false || stripos($claimName, 'other') !== false || stripos($claimName, 'emergency') !== false) {
                    $otherType = DB::table('advance_request_types')->where('type_code', 'Oth')->where('is_active', 1)->first();
                    return $otherType ? $otherType->id : 3;
                }
            }
            // Default to Act type
            $activityType = DB::table('advance_request_types')->where('type_code', 'Act')->where('is_active', 1)->first();
            return $activityType ? $activityType->id : 1;
        } catch (\Exception $e) {
            \Log::error('Error determining advance type ID: ' . $e->getMessage());
            // Default to Act type
            return 1;
        }
    }

    private function submitRequest($requestId, $userId)
    {
        return DB::transaction(function () use ($requestId, $userId) {
            $request = AdvanceRequest::findOrFail($requestId);
            if (!$request->canBeSubmitted()) {
                throw new Exception('Request cannot be submitted in current status');
            }
            // Set current request context for approval hierarchy determination
            $this->setCurrentAdvanceRequestContext($request);
            $request->update(['current_status' => AdvanceRequest::STATUS_SUBMITTED, 'last_submitted_at' => now()]);
            $this->createApprovalMatrixForAllActivities($request);
            $request->activities()->update(['current_approval_level' => 1, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_PENDING]);
            $this->logAction($request, AdvanceRequestAction::ACTION_SUBMIT, $userId);
            $this->sendSubmissionNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function resubmitRequest($requestId, $userId)
    {
        return DB::transaction(function () use ($requestId, $userId) {
            $request = AdvanceRequest::findOrFail($requestId);
            if (!$request->canBeResubmitted()) {
                throw new Exception('Request cannot be resubmitted in current status');
            }
            // Set current request context for approval hierarchy determination
            $this->setCurrentAdvanceRequestContext($request);
            $request->increment('submission_no');
            $request->update(['current_status' => AdvanceRequest::STATUS_SUBMITTED, 'last_submitted_at' => now(), 'reverted_from_status' => null, 'revert_reason' => null, 'reverted_by' => null, 'reverted_at' => null]);
            $this->createApprovalMatrixForAllActivities($request);
            $request->activities()->update(['current_approval_level' => 1, 'approval_status' => AdvanceRequestActivity::APPROVAL_STATUS_PENDING]);
            $this->logAction($request, AdvanceRequestAction::ACTION_RESUBMIT, $userId);
            $this->sendSubmissionNotifications($request);
            return $request->load(['employees.activities.approvals', 'actions']);
        });
    }

    private function createApprovalMatrixForAllActivities(AdvanceRequest $request)
    {
        foreach ($request->activities as $activity) {
            $this->createApprovalMatrixForActivity($activity, $request->submission_no);
        }
    }

    private function createApprovalMatrixForActivity(AdvanceRequestActivity $activity, $submissionNo)
    {
        try {
            $approvers = $this->getApproversForActivity($activity);
            foreach ($approvers as $level => $levelApprovers) {
                foreach ($levelApprovers as $approver) {
                    AdvanceActivityApproval::create(['advance_request_activity_id' => $activity->id, 'submission_no' => $submissionNo, 'approval_level' => $level, 'approver_id' => $approver['id'], 'approver_status' => AdvanceActivityApproval::STATUS_PENDING]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error creating approval matrix for activity ' . $activity->id . ': ' . $e->getMessage());
            throw new Exception('Failed to create approval matrix: ' . $e->getMessage());
        }
    }

    private function getApproversForActivity(AdvanceRequestActivity $activity)
    {
        $submitterId = $activity->employee->advanceRequest->req_app_submit_id;
        $advanceRequest = $activity->employee->advanceRequest;
        if (!$submitterId) {
            throw new Exception('No submitter ID found for the request');
        }
        // Check advance type to determine approval logic
        if ($advanceRequest->advance_type_id == 1) {
            // Activity type - use master configuration logic
            \Log::info("Activity advance type detected (ID: 1) - using master configuration logic for Employee ID: {$submitterId}");
            return $this->getEmployeeHierarchy($submitterId);
        } else {
            // Expense and Other types - use simple reporting manager logic
            \Log::info("Non-Activity advance type detected (ID: {$advanceRequest->advance_type_id}) - using reporting manager logic for Employee ID: {$submitterId}");
            return $this->getReportingManagerHierarchy($submitterId);
        }
    }

    private function getReportingManagerHierarchy($employeeId)
    {
        try {
            // Get employee details including reporting manager
            $employee = DB::connection('hrims')->selectOne("
                SELECT 
                    gen.EmployeeID AS EmployeeID, 
                    CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) AS EmployeeName, 
                    gen.EmailId_Vnr,
                    gen.RepEmployeeID,
                    gen.DepartmentId,
                    gen.DesigId,
                    gen.BUId,
                    gen.ZoneId,
                    gen.ReportingName,
                    gen.ReportingDesigId,
                    gen.ReportingContactNo,
                    gen.ReportingEmailId
                FROM hrm_employee_general AS gen 
                LEFT JOIN hrm_employee ON gen.EmployeeID = hrm_employee.EmployeeID 
                WHERE gen.EmployeeID = ?
            ", [$employeeId]);
            if (!$employee) {
                throw new Exception("Employee not found for Employee ID: {$employeeId}");
            }
            // Check if employee is reporting to themselves (top level - no approval needed)
            if ($employee->EmployeeID == $employee->RepEmployeeID || !$employee->RepEmployeeID) {
                throw new Exception("Employee ID {$employeeId} is a top-level employee (reports to self or no manager). No approval required.");
            }
            $approvers = [];
            // Level 1: Always the reporting manager for Expense and Other types
            $level1Approver = $this->getEmployeeDetails($employee->RepEmployeeID);
            if (!$level1Approver) {
                throw new Exception("Reporting manager not found for Employee ID: {$employee->RepEmployeeID}");
            }
            $approvers[1] = [['id' => $level1Approver['EmployeeID'], 'name' => $level1Approver['EmployeeName'], 'email' => $level1Approver['EmailId_Vnr'], 'designation' => $level1Approver['Designation'] ?? 'Manager']];
            \Log::info("Using reporting manager hierarchy for Employee ID: {$employeeId}, Manager: {$level1Approver['EmployeeName']} (ID: {$level1Approver['EmployeeID']})");
            return $approvers;
        } catch (\Exception $e) {
            \Log::error('Error getting reporting manager hierarchy: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getEmployeeHierarchy($employeeId)
    {
        try {
            \Log::info("Using master configuration hierarchy logic for Activity advance type, Employee ID: {$employeeId}");
            // Get employee details including department and designation
            $employee = DB::connection('hrims')->selectOne("
                SELECT 
                    gen.EmployeeID AS EmployeeID, 
                    CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) AS EmployeeName, 
                    gen.EmailId_Vnr,
                    gen.RepEmployeeID,
                    gen.DepartmentId,
                    gen.DesigId,
                    gen.BUId,
                    gen.ZoneId,
                    gen.ReportingName,
                    gen.ReportingDesigId,
                    gen.ReportingContactNo,
                    gen.ReportingEmailId
                FROM hrm_employee_general AS gen 
                LEFT JOIN hrm_employee ON gen.EmployeeID = hrm_employee.EmployeeID 
                WHERE gen.EmployeeID = ?
            ", [$employeeId]);
            if (!$employee) {
                throw new Exception("Employee not found for Employee ID: {$employeeId}");
            }
            // Get the advance request to determine the advance type
            $advanceRequest = $this->getCurrentAdvanceRequest($employeeId);
            if (!$advanceRequest) {
                throw new Exception("No advance request context found for Employee ID: {$employeeId}");
            }
            // Determine advance type based on request context (you may need to add advance_type_id to advance_requests table)
            // For now, we'll use a fallback logic based on activity_name_id or other indicators
            $advanceTypeCode = $this->determineAdvanceTypeFromRequest($advanceRequest);
            // Get master configuration based on advance type and access mode
            $masterConfig = $this->getAdvanceRequestMasterConfig($employee, $advanceTypeCode);
            if (!$masterConfig) {
                // Fallback to old logic if no master configuration found
                \Log::info("No advance request master configuration found for Employee ID: {$employeeId}, Department: {$employee->DepartmentId}, Advance Type: {$advanceTypeCode}. Using fallback logic.");
                return $this->getFallbackEmployeeHierarchy($employee);
            }
            // Use the configured approval level from master configuration
            $configuredLevel = $masterConfig->approver_level;
            \Log::info("Using advance request master configuration for Employee ID: {$employeeId}, Advance Type: {$advanceTypeCode}, Configured Level: {$configuredLevel}");
            // Check if employee is reporting to themselves (top level - no approval needed)
            if ($employee->EmployeeID == $employee->RepEmployeeID || !$employee->RepEmployeeID) {
                throw new Exception("Employee ID {$employeeId} is a top-level employee (reports to self or no manager). No approval required.");
            }
            $approvers = [];
            // Build approval hierarchy based on configured level
            $currentEmployee = $employee;
            for ($level = 1; $level <= $configuredLevel; $level++) {
                if (!$currentEmployee->RepEmployeeID || $currentEmployee->EmployeeID == $currentEmployee->RepEmployeeID) {
                    // No more managers in hierarchy
                    break;
                }
                $approverDetails = $this->getEmployeeDetails($currentEmployee->RepEmployeeID);
                if (!$approverDetails) {
                    \Log::warning("Level {$level} approver not found for Employee ID: {$currentEmployee->RepEmployeeID}");
                    break;
                }
                $approvers[$level] = [['id' => $approverDetails['EmployeeID'], 'name' => $approverDetails['EmployeeName'], 'email' => $approverDetails['EmailId_Vnr'], 'designation' => $approverDetails['Designation'] ?? "Level {$level} Manager"]];
                // Move to next level in hierarchy
                $currentEmployee = (object) $approverDetails;
            }
            if (empty($approvers)) {
                throw new Exception("No approvers found in hierarchy for Employee ID: {$employeeId} with configured level: {$configuredLevel}");
            }
            return $approvers;
        } catch (\Exception $e) {
            \Log::error('Error getting employee hierarchy from master config: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getAdvanceRequestMasterConfig($employee, $advanceTypeCode)
    {
        try {
            // Get advance type details
            $advanceType = DB::table('advance_request_types')->where('type_code', $advanceTypeCode)->where('is_active', 1)->first();
            if (!$advanceType) {
                \Log::warning("Advance type not found for code: {$advanceTypeCode}");
                return null;
            }
            $masterConfig = null;
            if ($advanceType->access_mode === 'DEPARTMENT') {
                // Department-based access (Act and Exp)
                if ($advanceTypeCode === 'Act') {
                    // Activity requires both department and designation
                    $masterConfig = DB::table('advance_request_masters as arm')->join('advance_request_types as art', 'arm.advance_type_id', '=', 'art.id')->where('art.type_code', $advanceTypeCode)->where('arm.department_id', $employee->DepartmentId)->where('arm.is_active', 1)->where('arm.effective_from', '<=', now()->toDateString())->where(function ($query) {
                        $query->whereNull('arm.effective_to')->orWhere('arm.effective_to', '>=', now()->toDateString());
                    })->where(function ($query) use ($employee) {
                        // Handle both JSON_CONTAINS and fallback for older MySQL versions
                        $query->whereRaw('JSON_CONTAINS(arm.designation_ids, ?)', ['"' . $employee->DesigId . '"'])->orWhere('arm.designation_ids', 'LIKE', '%"' . $employee->DesigId . '"%')->orWhere('arm.designation_ids', 'LIKE', '%' . $employee->DesigId . '%');
                    })->select('arm.*')->first();
                } else {
                    // Expense requires only department (no designation check)
                    $masterConfig = DB::table('advance_request_masters as arm')->join('advance_request_types as art', 'arm.advance_type_id', '=', 'art.id')->where('art.type_code', $advanceTypeCode)->where('arm.department_id', $employee->DepartmentId)->where('arm.is_active', 1)->where('arm.effective_from', '<=', now()->toDateString())->where(function ($query) {
                        $query->whereNull('arm.effective_to')->orWhere('arm.effective_to', '>=', now()->toDateString());
                    })->select('arm.*')->first();
                }
            } else {
                // Employee-based access (Oth)
                $masterConfig = DB::table('advance_request_masters as arm')->join('advance_request_types as art', 'arm.advance_type_id', '=', 'art.id')->where('art.type_code', $advanceTypeCode)->where('arm.is_active', 1)->where('arm.effective_from', '<=', now()->toDateString())->where(function ($query) {
                    $query->whereNull('arm.effective_to')->orWhere('arm.effective_to', '>=', now()->toDateString());
                })->where(function ($query) use ($employee) {
                    // Handle both JSON_CONTAINS and fallback for older MySQL versions
                    $query->whereRaw('JSON_CONTAINS(arm.employee_ids, ?)', ['"' . $employee->EmployeeID . '"'])->orWhere('arm.employee_ids', 'LIKE', '%"' . $employee->EmployeeID . '"%')->orWhere('arm.employee_ids', 'LIKE', '%' . $employee->EmployeeID . '%');
                })->select('arm.*')->first();
            }
            return $masterConfig;
        } catch (\Exception $e) {
            \Log::error('Error getting advance request master config: ' . $e->getMessage());
            return null;
        }
    }

    private function determineAdvanceTypeFromRequest($advanceRequest)
    {
        // If you have advance_type_id in advance_requests table, use that
        if (isset($advanceRequest->advance_type_id)) {
            $advanceType = DB::table('advance_request_types')->where('id', $advanceRequest->advance_type_id)->first();
            return $advanceType ? $advanceType->type_code : 'Act';
        }
        // Enhanced logic based on activity_name_id and business rules
        if ($advanceRequest->activity_name_id) {
            // Check if this is an expense-related activity
            $claimType = DB::table('claimtype')->where('ClaimId', $advanceRequest->activity_name_id)->first();
            if ($claimType) {
                $claimName = strtolower($claimType->ClaimName);
                // Check for expense-related keywords
                if (stripos($claimName, 'expense') !== false || stripos($claimName, 'travel') !== false || stripos($claimName, 'conveyance') !== false || stripos($claimName, 'accommodation') !== false) {
                    return 'Exp';
                }
                // Check for other/special advance keywords
                if (stripos($claimName, 'special') !== false || stripos($claimName, 'other') !== false || stripos($claimName, 'emergency') !== false) {
                    return 'Oth';
                }
            }
        }
        // Default to Act if no specific indicators found
        return 'Act';
    }

    private function getCurrentAdvanceRequest($employeeId)
    {
        // This method should return the current advance request being processed
        // We'll try multiple approaches to get the most relevant request
        // First, try to get from the current processing context (if available)
        if (property_exists($this, 'currentAdvanceRequest') && $this->currentAdvanceRequest) {
            return $this->currentAdvanceRequest;
        }
        // Try to get the most recent submitted request for this employee
        $recentRequest = DB::table('advance_requests as ar')->join('advance_request_employees as are', 'ar.id', '=', 'are.advance_request_id')->where('are.employee_id', $employeeId)->where('ar.current_status', 'Submitted')->orderBy('ar.created_at', 'desc')->select('ar.*')->first();
        if ($recentRequest) {
            return $recentRequest;
        }
        // Fallback: get any recent request for this employee
        $fallbackRequest = DB::table('advance_requests as ar')->join('advance_request_employees as are', 'ar.id', '=', 'are.advance_request_id')->where('are.employee_id', $employeeId)->orderBy('ar.created_at', 'desc')->select('ar.*')->first();
        return $fallbackRequest;
    }

    private function setCurrentAdvanceRequestContext($advanceRequest)
    {
        $this->currentAdvanceRequest = $advanceRequest;
    }

    private function getFallbackEmployeeHierarchy($employee)
    {
        try {
            \Log::info("Using fallback hierarchy logic for Employee ID: {$employee->EmployeeID}");
            // Check if employee is reporting to themselves (top level - no approval needed)
            if ($employee->EmployeeID == $employee->RepEmployeeID || !$employee->RepEmployeeID) {
                throw new Exception("Employee ID {$employee->EmployeeID} is a top-level employee (reports to self or no manager). No approval required.");
            }
            $approvers = [];
            // Level 1: Always the reporting manager
            $level1Approver = $this->getEmployeeDetails($employee->RepEmployeeID);
            if (!$level1Approver) {
                throw new Exception("Level 1 approver not found for Employee ID: {$employee->RepEmployeeID}");
            }
            $approvers[1] = [['id' => $level1Approver['EmployeeID'], 'name' => $level1Approver['EmployeeName'], 'email' => $level1Approver['EmailId_Vnr'], 'designation' => $level1Approver['Designation'] ?? 'Manager']];
            // Level 2: Only if ZoneId = 0 (not zone level employee) and level1 approver is not top-level
            if ($employee->ZoneId == 0 && !empty($level1Approver['RepEmployeeID']) && $level1Approver['EmployeeID'] != $level1Approver['RepEmployeeID']) {
                $level2Approver = $this->getEmployeeDetails($level1Approver['RepEmployeeID']);
                if ($level2Approver) {
                    $approvers[2] = [['id' => $level2Approver['EmployeeID'], 'name' => $level2Approver['EmployeeName'], 'email' => $level2Approver['EmailId_Vnr'], 'designation' => $level2Approver['Designation'] ?? 'Senior Manager']];
                }
            }
            if (empty($approvers)) {
                throw new Exception("No approvers found in fallback hierarchy for Employee ID: {$employee->EmployeeID}");
            }
            return $approvers;
        } catch (\Exception $e) {
            \Log::error('Error in fallback employee hierarchy: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getEmployeeDetails($employeeId)
    {
        try {
            $employee = DB::connection('hrims')->selectOne("
                SELECT 
                    gen.EmployeeID AS EmployeeID, 
                    gen.EC AS EmployeeCode,
                    CONCAT(hrm_employee.Fname, ' ', hrm_employee.Sname, ' ', hrm_employee.Lname) AS EmployeeName, 
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
            return $employee ? (array) $employee : null;
        } catch (\Exception $e) {
            \Log::error('Error getting employee details: ' . $e->getMessage());
            return null;
        }
    }

    /** ADV-FX-ELIG-01 (14 Nov 2026): null when every non-INR line belongs to an eligible employee, else the message. */
    private function fxIneligible(array $data): ?string
    {
        $ids = [];
        foreach ((array) ($data['employees'] ?? []) as $emp) {
            foreach ((array) ($emp['activities'] ?? []) as $act) {
                $cur = strtoupper(trim((string) ($act['currency'] ?? 'INR')));
                if ($cur !== '' && $cur !== 'INR') {
                    $ids[] = (int) ($emp['employee_id'] ?? 0);
                }
            }
        }
        if (! $ids) {
            return null;
        }
        $company = (int) (request()->input('company_id') ?: session('company_id') ?: 1);
        $bad = array_keys(array_filter(\App\Services\Advance\FxEligibility::check($ids, $company), fn ($ok) => ! $ok));
        if (! $bad) {
            return null;
        }
        $names = [];
        foreach ($bad as $id) {
            $d = $this->getEmployeeDetails($id);
            $names[] = trim(($d['EmployeeName'] ?? '').' ('.($d['EmployeeCode'] ?? $id).')');
        }

        return 'A foreign-currency advance is not allowed for: '.implode(', ', $names).'. Request it in INR, or ask the admin to add them in Advance Configuration Setting (foreign currency).';
    }

    private function logAction(AdvanceRequest $request, $actionType, $actorId, $targetId = null, $activityId = null, $approvalLevel = null, $remarks = null, $templateId = null)
    {
        AdvanceRequestAction::create(['advance_request_id' => $request->id, 'submission_no' => $request->submission_no, 'action_type' => $actionType, 'action_by' => $actorId, 'action_to' => $targetId, 'related_activity_id' => $activityId, 'related_approval_level' => $approvalLevel, 'email_template_id' => $templateId, 'remarks' => $remarks, 'action_at' => now()]);
    }

    private function sendSubmissionNotifications(AdvanceRequest $request)
    {
        try {
            // Send notifications to level 1 approvers
            $level1Approvers = $request->activities()->with(['approvals' => function ($query) use ($request) {
                $query->where('approval_level', 1)->where('submission_no', $request->submission_no);
            }])->get()->pluck('approvals')->flatten()->unique('approver_id');
            foreach ($level1Approvers as $approval) {
                $this->sendSubmissionEmailNotification($request, $approval);
            }

            // Send notifications to employees
            $this->sendEmployeeNotifications($request);
        } catch (\Exception $e) {
            \Log::error('Error sending submission notifications: ' . $e->getMessage());
        }
    }

    private function sendSubmissionEmailNotification(AdvanceRequest $request, AdvanceActivityApproval $approval)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }
            $approver = $this->getEmployeeDetails($approval->approver_id);
            if (!$approver || empty($approver['EmailId_Vnr'])) {
                \Log::warning("No email found for approver ID: {$approval->approver_id}");
                return;
            }
            $requester = $this->getEmployeeDetails($request->req_app_submit_id);

            // Get activity name and type for subject
            $activityName = 'N/A';
            $activityTypeName = 'N/A';
            if ($request->activity_name_id) {
                try {
                    $activityNameRecord = DB::table('claimtype')
                        ->where('ClaimId', $request->activity_name_id)
                        ->where('ClaimStatus', 'A')
                        ->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
                }
            }

            // Get activity type and crop/variety details from first activity
            $activityCrops = [];
            $activityVarieties = [];
            $firstActivity = $request->activities->first();
            if ($firstActivity) {
                if ($firstActivity->activity_type_id) {
                    try {
                        $activityType = DB::table('adv_activity_types')->where('id', $firstActivity->activity_type_id)->first();
                        $activityTypeName = $activityType->type_name ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity type: ' . $e->getMessage());
                    }
                }

                // Get crop names
                if ($firstActivity->activity_crops_id) {
                    try {
                        $cropIds = is_string($firstActivity->activity_crops_id) ? json_decode($firstActivity->activity_crops_id, true) : $firstActivity->activity_crops_id;
                        if (is_array($cropIds) && !empty($cropIds)) {
                            $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                            $activityCrops = $crops->toArray();
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching crop names: ' . $e->getMessage());
                    }
                }

                // Get variety names
                if ($firstActivity->activity_variety_id) {
                    try {
                        $varietyIds = is_string($firstActivity->activity_variety_id) ? json_decode($firstActivity->activity_variety_id, true) : $firstActivity->activity_variety_id;
                        if (is_array($varietyIds) && !empty($varietyIds)) {
                            $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                            $activityVarieties = $varieties->toArray();
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching variety names: ' . $e->getMessage());
                    }
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
            $advanceTypeCode = 'Act';
            try {
                $advanceType = DB::table('advance_request_types')->where('id', $request->advance_type_id)->first();
                $advanceTypeCode = $advanceType->type_code ?? 'Act';
            } catch (\Exception $e) {
                \Log::warning('Error fetching advance type: ' . $e->getMessage());
            }

            // If advance type is Activity, check is_tour_travel on the activity
            // If is_tour_travel = 'Y', treat it as Expense type for the email
            if ($advanceTypeCode === 'Act' && $firstActivity && in_array($firstActivity->is_tour_travel, ['Y', 'y', '1', 1, true, 'true'], true)) {
                $advanceTypeCode = 'Exp';
            }

            // Get expense submission date for Expense type requests
            $expenseSubmissionDate = null;
            if ($advanceTypeCode === 'Exp' && $request->month) {
                try {
                    // Get the active financial year to determine the table name
                    $activeYear = DB::table('financialyear')
                        ->where('Status', 'Active')
                        ->first();

                    if ($activeYear) {
                        $tableName = 'y' . $activeYear->YearId . '_monthexpensefinal';

                        // Get the first employee from the request to fetch expense submission date
                        $firstEmployee = $request->employees->first();
                        if ($firstEmployee) {
                            $expenseRecord = DB::ctable($tableName)
                                ->where('EmployeeID', $firstEmployee->employee_id)
                                ->where('Month', $request->month)
                                ->first();
                            if ($expenseRecord && isset($expenseRecord->DateOfSubmit)) {
                                $expenseSubmissionDate = \Carbon\Carbon::parse($expenseRecord->DateOfSubmit)->format('d-m-Y');
                            }
                        }
                    } else {
                        \Log::warning('No active financial year found for expense submission date');
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching expense submission date: ' . $e->getMessage());
                }
            }

            // Get unique employees for "Request for" section
            $requestForEmployees = [];
            foreach ($request->employees as $employee) {
                $empDetails = $this->getEmployeeDetails($employee->employee_id);
                $requestForEmployees[] = [
                    'code' => $empDetails['EmployeeCode'] ?? 'N/A',
                    'name' => $empDetails['EmployeeName'] ?? 'N/A',
                    'designation' => $empDetails['Designation'] ?? 'N/A'
                ];
            }

            $emailData = [
                'request_no' => $request->id,
                'requester_name' => $requester['EmployeeName'] ?? 'N/A',
                'requester_code' => $requester['EmployeeCode'] ?? 'N/A',
                'requester_designation' => $requester['Designation'] ?? 'N/A',
                'approver_name' => $approver['EmployeeName'],
                'amount' => $request->activities->sum('amount'),
                'created_date' => $request->created_at->format('d-m-Y'),
                'submitted_date' => $request->last_submitted_at ? $request->last_submitted_at->format('d-m-Y') : null,
                'expense_submission_date' => $expenseSubmissionDate,
                'status' => $request->current_status,
                'approval_level' => $approval->approval_level,
                'action_type' => 'submission',
                'activity_name' => $activityName,
                'activity_type' => $activityTypeName,
                'activity_crops' => !empty($activityCrops) ? implode(', ', $activityCrops) : null,
                'activity_varieties' => !empty($activityVarieties) ? implode(', ', $activityVarieties) : null,
                'month_year' => $monthYear,
                'advance_type_code' => $advanceTypeCode,
                'request_for_employees' => $requestForEmployees
            ];

            // Send email
            Mail::to($approver['EmailId_Vnr'])->send(new AdvanceRequestNotification($emailData));
            // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));

            app(\App\Services\EmailLogService::class)->logSent([
                'notification_type' => 'advance_submission',
                'mail_category'     => 'advance',
                'stage'             => (string) ($approval->approval_level ?? 1),
                'subject'           => "Advance Request #{$request->id} - Pending Approval",
                'recipient_email'   => $approver['EmailId_Vnr'],
                'recipient_name'    => $approver['EmployeeName'] ?? null,
                'ref_type'          => 'advance_request',
                'ref_id'            => $request->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending submission email notification: ' . $e->getMessage());
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
            // Get variety names from HRIMS database
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
            $activities[] = ['employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A', 'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A', 'activity_type' => $activityTypeName, 'activity_name' => $activityName, 'crops' => !empty($cropNames) ? implode(', ', $cropNames) : 'N/A', 'varieties' => !empty($varietyNames) ? implode(', ', $varietyNames) : 'N/A', 'amount' => (float) $activity->amount, 'purpose' => $activity->purpose ?? 'N/A'];
        }
        return $activities;
    }

    private function sendEmployeeNotifications(AdvanceRequest $request)
    {
        try {
            // Check if email is active
            if (!config('xeasy_notifications.mail_active', false)) {
                \Log::info('Email sending is disabled via MAIL_ACTIVE environment variable');
                return;
            }

            // Get unique employees from the request
            $uniqueEmployees = $request->employees->unique('employee_id');

            foreach ($uniqueEmployees as $employee) {
                $this->sendEmployeeEmailNotification($request, $employee->employee_id);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending employee notifications: ' . $e->getMessage());
        }
    }

    private function sendEmployeeEmailNotification(AdvanceRequest $request, $employeeId)
    {
        try {
            $employeeDetails = $this->getEmployeeDetails($employeeId);
            if (!$employeeDetails || empty($employeeDetails['EmailId_Vnr'])) {
                \Log::warning("No email found for employee ID: {$employeeId}");
                return;
            }

            $requester = $this->getEmployeeDetails($request->req_app_submit_id);

            // Get activity name and type for subject
            $activityName = 'N/A';
            $activityTypeName = 'N/A';
            if ($request->activity_name_id) {
                try {
                    $activityNameRecord = DB::table('claimtype')
                        ->where('ClaimId', $request->activity_name_id)
                        ->where('ClaimStatus', 'A')
                        ->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
                }
            }

            // Get activity type and crop/variety details for this employee
            $activityCrops = [];
            $activityVarieties = [];
            $totalAmount = 0;

            foreach ($request->activities as $activity) {
                if ($activity->employee->employee_id == $employeeId) {
                    $totalAmount += $activity->amount;

                    if ($activity->activity_type_id && $activityTypeName === 'N/A') {
                        try {
                            $activityType = DB::table('adv_activity_types')->where('id', $activity->activity_type_id)->first();
                            $activityTypeName = $activityType->type_name ?? 'N/A';
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching activity type: ' . $e->getMessage());
                        }
                    }

                    // Get crop names
                    if ($activity->activity_crops_id && empty($activityCrops)) {
                        try {
                            $cropIds = is_string($activity->activity_crops_id) ? json_decode($activity->activity_crops_id, true) : $activity->activity_crops_id;
                            if (is_array($cropIds) && !empty($cropIds)) {
                                $crops = DB::connection('hrims')->table('core_crops')->whereIn('id', $cropIds)->pluck('crop_name');
                                $activityCrops = $crops->toArray();
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching crop names: ' . $e->getMessage());
                        }
                    }

                    // Get variety names
                    if ($activity->activity_variety_id && empty($activityVarieties)) {
                        try {
                            $varietyIds = is_string($activity->activity_variety_id) ? json_decode($activity->activity_variety_id, true) : $activity->activity_variety_id;
                            if (is_array($varietyIds) && !empty($varietyIds)) {
                                $varieties = DB::connection('hrims')->table('core_varieties')->whereIn('id', $varietyIds)->pluck('variety_name');
                                $activityVarieties = $varieties->toArray();
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching variety names: ' . $e->getMessage());
                        }
                    }
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

            // Determine advance type code with is_tour_travel check
            $advanceTypeCode = 'Act';
            try {
                $advanceType = DB::table('advance_request_types')->where('id', $request->advance_type_id)->first();
                $advanceTypeCode = $advanceType->type_code ?? 'Act';
            } catch (\Exception $e) {
                \Log::warning('Error fetching advance type: ' . $e->getMessage());
            }

            // If advance type is Activity, check is_tour_travel on the activity
            // If is_tour_travel = 'Y', treat it as Expense type for the email
            if ($advanceTypeCode === 'Act') {
                $employeeActivities = $request->activities->filter(function ($act) use ($employeeId) {
                    return $act->employee->employee_id == $employeeId;
                });
                $firstEmployeeActivity = $employeeActivities->first();
                if ($firstEmployeeActivity && in_array($firstEmployeeActivity->is_tour_travel, ['Y', 'y', '1', 1, true, 'true'], true)) {
                    $advanceTypeCode = 'Exp';
                }
            }

            $emailData = [
                'request_no' => $request->id,
                'requester_name' => $requester['EmployeeName'] ?? 'N/A',
                'requester_code' => $requester['EmployeeCode'] ?? 'N/A',
                'requester_designation' => $requester['Designation'] ?? 'N/A',
                'employee_name' => $employeeDetails['EmployeeName'],
                'amount' => $totalAmount,
                'created_date' => $request->created_at->format('d-m-Y'),
                'submitted_date' => $request->last_submitted_at ? $request->last_submitted_at->format('d-m-Y') : null,
                'status' => $request->current_status,
                'action_type' => 'employee_submission',
                'activity_name' => $activityName,
                'activity_type' => $activityTypeName,
                'activity_crops' => !empty($activityCrops) ? implode(', ', $activityCrops) : null,
                'activity_varieties' => !empty($activityVarieties) ? implode(', ', $activityVarieties) : null,
                'month_year' => $monthYear,
                'advance_type_code' => $advanceTypeCode
            ];

            // Send email
            Mail::to($employeeDetails['EmailId_Vnr'])->send(new AdvanceRequestNotification($emailData));
            // Mail::to('devendrakumar.vspl@gmail.com')->send(new AdvanceRequestNotification($emailData));
            \Log::info("Employee notification sent to: {$employeeDetails['EmailId_Vnr']} for request #{$request->id}");

            app(\App\Services\EmailLogService::class)->logSent([
                'notification_type'     => 'advance_employee_submission',
                'mail_category'         => 'advance',
                'subject'               => "Advance Request #{$request->id} - Submitted",
                'recipient_employee_id' => $employeeId,
                'recipient_emp_code'    => $employeeDetails['EmployeeCode'] ?? null,
                'recipient_name'        => $employeeDetails['EmployeeName'] ?? null,
                'recipient_email'       => $employeeDetails['EmailId_Vnr'],
                'ref_type'              => 'advance_request',
                'ref_id'                => $request->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending employee email notification: ' . $e->getMessage());
        }
    }

    private function getActivityDetailsForEmployeeEmail($request, $employeeId)
    {
        $activities = [];
        foreach ($request->activities as $activity) {
            // Only include activities for this specific employee
            if ($activity->employee->employee_id != $employeeId) {
                continue;
            }

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

            // Get activity name from claimtype table
            $activityName = 'N/A';
            $requestActivityNameId = $activity->employee->advanceRequest->activity_name_id;
            if ($requestActivityNameId) {
                try {
                    $activityNameRecord = DB::table('claimtype')
                        ->where('ClaimId', $requestActivityNameId)
                        ->where('ClaimStatus', 'A')
                        ->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                } catch (\Exception $e) {
                    \Log::warning('Error fetching activity name: ' . $e->getMessage());
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

            $activities[] = [
                'employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A',
                'employee_code' => $employeeDetails['EmployeeCode'] ?? 'N/A',
                'activity_type' => $activityTypeName,
                'activity_name' => $activityName,
                'crops' => !empty($cropNames) ? implode(', ', $cropNames) : 'N/A',
                'varieties' => !empty($varietyNames) ? implode(', ', $varietyNames) : 'N/A',
                'amount' => (float) $activity->amount,
                'purpose' => $activity->purpose ?? 'N/A'
            ];
        }
        return $activities;
    }

    public function indexExpense(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $status = $request->get('status');
            // Get the Expense advance type ID
            $expenseType = DB::table('advance_request_types')->where('type_code', 'Exp')->where('is_active', 1)->first();
            if (!$expenseType) {
                return $this->jsonError('Expense advance type not found');
            }
            $query = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])
                ->where('created_by', $userId)
                ->where(function ($q) use ($expenseType) {
                    $q
                        ->where('advance_type_id', $expenseType->id)
                        ->orWhere('advance_type_id', 0);
                });
            if ($status) {
                $query->where('current_status', $status);
            }
            $requests = $query->orderBy('created_at', 'desc')->get();
            // Transform the data using the same logic as index method
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
                // Prepare request data object
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => 'Expense', 'advance_type_code' => 'Exp', 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approval_summary' => $approvalSummary];
                // Process each employee for this request (same logic as index method)
                foreach ($advanceRequest->employees as $employee) {
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
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
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'N/A', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => [], 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => [], 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Add employee with req_data inside
                    $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                }
            }
            return $this->jsonSuccess($allEmployees, 'Expense advance requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve expense advance requests: ' . $e->getMessage());
        }
    }

    public function indexOther(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $status = $request->get('status');
            // Get the Other advance type ID
            $otherType = DB::table('advance_request_types')->where('type_code', 'Oth')->where('is_active', 1)->first();
            if (!$otherType) {
                return $this->jsonError('Other advance type not found');
            }
            $query = AdvanceRequest::with(['employees.activities', 'currentApprovals', 'actions'])->where('created_by', $userId)->where('advance_type_id', $otherType->id);
            if ($status) {
                $query->where('current_status', $status);
            }
            $requests = $query->orderBy('created_at', 'desc')->get();
            // Transform the data using the same logic as index method
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
                // Prepare request data object
                $reqData = ['id' => $advanceRequest->id, 'advance_type_id' => $advanceRequest->advance_type_id, 'advance_type_name' => 'Other', 'advance_type_code' => 'Oth', 'activity_name_id' => $advanceRequest->activity_name_id, 'activity_name' => $activityName, 'month' => $advanceRequest->month, 'req_app_submit_id' => $advanceRequest->req_app_submit_id, 'submission_no' => $advanceRequest->submission_no, 'last_submitted_at' => $advanceRequest->last_submitted_at, 'advance_fill_date' => $advanceRequest->advance_fill_date, 'current_status' => $advanceRequest->current_status, 'reverted_from_status' => $advanceRequest->reverted_from_status, 'revert_reason' => $advanceRequest->revert_reason, 'reverted_by' => $advanceRequest->reverted_by, 'created_by' => $advanceRequest->created_by, 'updated_by' => $advanceRequest->updated_by, 'reverted_at' => $advanceRequest->reverted_at, 'created_at' => $advanceRequest->created_at, 'updated_at' => $advanceRequest->updated_at, 'deleted_at' => $advanceRequest->deleted_at, 'approval_summary' => $approvalSummary];
                // Process each employee for this request (same logic as index method)
                foreach ($advanceRequest->employees as $employee) {
                    $employeeDetails = $this->getEmployeeDetails($employee->employee_id);
                    // Transform activities for this employee
                    $activitiesData = [];
                    foreach ($employee->activities as $activity) {
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
                                $approvalDetails[] = ['id' => $approval->id, 'approval_level' => $approval->approval_level, 'approver_id' => $approval->approver_id, 'approver_name' => $approverDetails['EmployeeName'] ?? 'N/A', 'approver_email' => $approverDetails['EmailId_Vnr'] ?? 'N/A', 'approver_designation' => $approverDetails['Designation'] ?? 'N/A', 'approver_status' => $approval->approver_status, 'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null, 'approver_remark' => $approval->approver_remark, 'approver_date' => $approval->approver_date, 'reverted_by' => $approval->reverted_by, 'reverted_by_name' => $reverterDetails['EmployeeName'] ?? null, 'reverted_at' => $approval->reverted_at, 'revert_reason' => $approval->revert_reason, 'created_at' => $approval->created_at, 'updated_at' => $approval->updated_at];
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error fetching approval details for activity ' . $activity->id . ': ' . $e->getMessage());
                        }
                        $activitiesData[] = ['id' => $activity->id, 'advance_request_employee_id' => $activity->advance_request_employee_id, 'activity_type_id' => $activity->activity_type_id, 'activity_type_name' => $activityTypeName, 'activity_crops_id' => $activity->activity_crops_id, 'crop_names' => [], 'activity_variety_id' => $activity->activity_variety_id, 'variety_names' => [], 'amount' => $activity->amount, 'currency' => $activity->currency ?? 'INR', 'fx_amount' => $activity->fx_amount ?? null, 'fx_rate' => $activity->fx_rate ?? null, 'fx_paid_rate' => $activity->fx_paid_rate ?? null, 'fx_label' => $activity->fx_label ?? null, 'purpose' => $activity->purpose, 'current_approval_level' => $activity->current_approval_level, 'approval_status' => $activity->approval_status, 'approvals' => $approvalDetails, 'finance_id' => $activity->finance_id, 'finance_approved_amount' => $activity->finance_approved_amount, 'finance_status' => $activity->finance_status, 'finance_remark' => $activity->finance_remark, 'finance_date' => $activity->finance_date, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'deleted_at' => $activity->deleted_at];
                    }
                    // Add employee with req_data inside
                    $allEmployees[] = ['id' => $employee->id, 'advance_request_id' => $employee->advance_request_id, 'employee_id' => $employee->employee_id, 'employee_name' => $employeeDetails['EmployeeName'] ?? 'N/A', 'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id, 'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A', 'department' => $employeeDetails['DepartmentName'] ?? 'N/A', 'designation' => $employeeDetails['Designation'] ?? 'N/A', 'created_at' => $employee->created_at, 'updated_at' => $employee->updated_at, 'deleted_at' => $employee->deleted_at, 'req_data' => $reqData, 'activities' => $activitiesData];
                }
            }
            return $this->jsonSuccess($allEmployees, 'Other advance requests retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve other advance requests: ' . $e->getMessage());
        }
    }

    public function delete(Request $request, $requestId)
    {
        try {
            $userId = $request->get('user_id');
            $advanceRequest = AdvanceRequest::findOrFail($requestId);
            if ($advanceRequest->current_status !== AdvanceRequest::STATUS_DRAFT) {
                return $this->jsonError('Only draft requests can be deleted');
            }
            $advanceRequest->employees()->delete();
            $advanceRequest->activities()->delete();
            $advanceRequest->delete();
            $this->logAction($advanceRequest, AdvanceRequestAction::ACTION_DELETE, $userId, null, null, null, 'Request deleted by user');
            return $this->jsonSuccess(null, 'Advance request deleted successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to delete request: ' . $e->getMessage());
        }
    }
}
