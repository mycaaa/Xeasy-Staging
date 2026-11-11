<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestAction;
use App\Models\AdvanceRequestActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class AdvanceFinanceController extends Controller
{
    public function __construct()
    {
        // Apply middleware if needed
    }

    /**
     * Display finance pending requests page
     */
    public function index()
    {
        return view('admin.advance.finance.index');
    }

    /**
     * Get pending finance approvals
     */
    public function pending(Request $request)
    {
        try {
            // Get advance requests that are in finance status
            $requests = AdvanceRequest::with([
                'employees.activities' => function ($query) {
                    $query
                        ->whereNull('deleted_at')
                        ->where('approval_status', AdvanceRequestActivity::APPROVAL_STATUS_APPROVED);
                },
                'actions'
            ])
                ->where('current_status', AdvanceRequest::STATUS_FINANCE)
                ->orderBy('last_submitted_at', 'asc')
                ->get();

            // Transform the data to match the expected format
            $allEmployees = [];
            foreach ($requests as $advanceRequest) {
                // Get activity name from claimtype table
                $activityName = 'N/A';
                if ($advanceRequest->activity_name_id) {
                    try {
                        $activityNameRecord = DB::table('claimtype')
                            ->where('ClaimId', $advanceRequest->activity_name_id)
                            ->where('ClaimStatus', 'A')
                            ->first();
                        $activityName = $activityNameRecord->ClaimName ?? 'N/A';
                    } catch (\Exception $e) {
                        \Log::warning('Error fetching activity name: ' . $e->getMessage());
                    }
                }

                // Prepare request data object
                $reqData = [
                    'id' => $advanceRequest->id,
                    'activity_name_id' => $advanceRequest->activity_name_id,
                    'activity_name' => $activityName,
                    'month' => $advanceRequest->month,
                    'req_app_submit_id' => $advanceRequest->req_app_submit_id,
                    'submission_no' => $advanceRequest->submission_no,
                    'last_submitted_at' => $advanceRequest->last_submitted_at,
                    'advance_fill_date' => $advanceRequest->advance_fill_date,
                    'current_status' => $advanceRequest->current_status,
                    'reverted_from_status' => $advanceRequest->reverted_from_status,
                    'revert_reason' => $advanceRequest->revert_reason,
                    'reverted_by' => $advanceRequest->reverted_by,
                    'created_by' => $advanceRequest->created_by,
                    'updated_by' => $advanceRequest->updated_by,
                    'reverted_at' => $advanceRequest->reverted_at,
                    'created_at' => $advanceRequest->created_at,
                    'updated_at' => $advanceRequest->updated_at,
                    'deleted_at' => $advanceRequest->deleted_at
                ];

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
                                $activityType = DB::table('adv_activity_types')
                                    ->where('id', $activity->activity_type_id)
                                    ->first();
                                $activityTypeName = $activityType->type_name ?? 'N/A';
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching activity type: ' . $e->getMessage());
                            }
                        }

                        // Get crop names
                        $cropNames = [];
                        if ($activity->activity_crops_id) {
                            try {
                                $cropIds = is_string($activity->activity_crops_id)
                                    ? json_decode($activity->activity_crops_id, true)
                                    : $activity->activity_crops_id;

                                if (is_array($cropIds) && !empty($cropIds)) {
                                    $crops = DB::connection('hrims')
                                        ->table('core_crops')
                                        ->whereIn('id', $cropIds)
                                        ->pluck('crop_name');
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
                                $varietyIds = is_string($activity->activity_variety_id)
                                    ? json_decode($activity->activity_variety_id, true)
                                    : $activity->activity_variety_id;

                                if (is_array($varietyIds) && !empty($varietyIds)) {
                                    $varieties = DB::connection('hrims')
                                        ->table('core_varieties')
                                        ->whereIn('id', $varietyIds)
                                        ->pluck('variety_name');
                                    $varietyNames = $varieties->toArray();
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error fetching variety names: ' . $e->getMessage());
                            }
                        }

                        $activitiesData[] = [
                            'id' => $activity->id,
                            'advance_request_employee_id' => $activity->advance_request_employee_id,
                            'activity_type_id' => $activity->activity_type_id,
                            'activity_type_name' => $activityTypeName,
                            'activity_crops_id' => $activity->activity_crops_id,
                            'crop_names' => $cropNames,
                            'activity_variety_id' => $activity->activity_variety_id,
                            'variety_names' => $varietyNames,
                            'amount' => $activity->amount,
                            'is_imprest' => (bool) ($activity->is_imprest ?? $request->is_imprest ?? false),
                            'purpose' => $activity->purpose,
                            'current_approval_level' => $activity->current_approval_level,
                            'approval_status' => $activity->approval_status,
                            'finance_id' => $activity->finance_id,
                            'finance_approved_amount' => $activity->finance_approved_amount,
                            'finance_status' => $activity->finance_status,
                            'finance_remark' => $activity->finance_remark,
                            'finance_date' => $activity->finance_date,
                            'payment_id' => $activity->payment_id,
                            'payment_amount' => $activity->payment_amount,
                            'payment_status' => $activity->payment_status,
                            'payment_remark' => $activity->payment_remark,
                            'payment_date' => $activity->payment_date,
                            'created_at' => $activity->created_at,
                            'updated_at' => $activity->updated_at,
                            'deleted_at' => $activity->deleted_at
                        ];
                    }

                    // Add employee with req_data inside
                    $allEmployees[] = [
                        'id' => $employee->id,
                        'advance_request_id' => $employee->advance_request_id,
                        'employee_id' => $employee->employee_id,
                        'employee_name' => $employeeDetails['EmployeeName'] ?? 'Unknown',
                        'employee_code' => $employeeDetails['EmployeeCode'] ?? $employee->employee_id,
                        'employee_email' => $employeeDetails['EmailId_Vnr'] ?? 'N/A',
                        'department' => $employeeDetails['DepartmentName'] ?? 'N/A',
                        'designation' => $employeeDetails['Designation'] ?? 'N/A',
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'deleted_at' => $employee->deleted_at,
                        'req_data' => $reqData,
                        'activities' => $activitiesData
                    ];
                }
            }

            return $this->jsonSuccess($allEmployees, 'Pending finance approvals retrieved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to retrieve finance pending requests: ' . $e->getMessage());
        }
    }

    /**
     * Approve finance request
     */
    public function approve(Request $request)
    {
        try {
            $request->validate([
                'request_id' => 'required|integer|exists:advance_requests,id',
                'activities' => 'required|array',
                'activities.*.activity_id' => 'required|integer|exists:advance_request_activities,id',
                'activities.*.approved_amount' => 'required|numeric|min:0',
                'remarks' => 'nullable|string|max:500'
            ]);

            $userId = auth()->id();  // Get current authenticated user
            $requestId = $request->input('request_id');
            $activities = $request->input('activities');
            $remarks = $request->input('remarks');

            $advanceRequest = $this->approveFinanceRequest($requestId, $activities, $userId, $remarks);

            return $this->jsonSuccess($advanceRequest, 'Finance request approved successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to approve finance request: ' . $e->getMessage());
        }
    }

    /**
     * Reject finance request
     */
    public function reject(Request $request)
    {
        try {
            $request->validate([
                'request_id' => 'required|integer|exists:advance_requests,id',
                'remarks' => 'required|string|max:500'
            ]);

            $userId = auth()->id();
            $requestId = $request->input('request_id');
            $remarks = $request->input('remarks');

            $advanceRequest = $this->rejectFinanceRequest($requestId, $userId, $remarks);

            return $this->jsonSuccess($advanceRequest, 'Finance request rejected successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to reject finance request: ' . $e->getMessage());
        }
    }

    /**
     * Revert finance request
     */
    public function revert(Request $request)
    {
        try {
            $request->validate([
                'request_id' => 'required|integer|exists:advance_requests,id',
                'remarks' => 'required|string|max:500'
            ]);

            $userId = auth()->id();
            $requestId = $request->input('request_id');
            $remarks = $request->input('remarks');

            $advanceRequest = $this->revertFinanceRequest($requestId, $userId, $remarks);

            return $this->jsonSuccess($advanceRequest, 'Finance request reverted successfully');
        } catch (Exception $e) {
            return $this->jsonError('Failed to revert finance request: ' . $e->getMessage());
        }
    }

    /**
     * Approve finance request
     */
    private function approveFinanceRequest($requestId, $activities, $financeId, $remarks)
    {
        return DB::transaction(function () use ($requestId, $activities, $financeId, $remarks) {
            $request = AdvanceRequest::findOrFail($requestId);

            if ($request->current_status !== AdvanceRequest::STATUS_FINANCE) {
                throw new Exception('Request is not in finance status');
            }

            // Update each activity with finance approval
            foreach ($activities as $activityData) {
                $activity = AdvanceRequestActivity::findOrFail($activityData['activity_id']);

                if ($activity->approval_status !== AdvanceRequestActivity::APPROVAL_STATUS_APPROVED) {
                    throw new Exception('Activity must be approved before finance processing');
                }

                $activity->update([
                    'finance_id' => $financeId,
                    'finance_approved_amount' => $activityData['approved_amount'],
                    'finance_status' => 'Approved',
                    'finance_remark' => $remarks,
                    'finance_date' => now()->toDateString()
                ]);
            }

            // Update request status
            $request->update([
                'current_status' => AdvanceRequest::STATUS_FINANCE_APPROVED
            ]);

            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_APPROVE, $financeId, null, null, null, $remarks);

            return $request->load(['employees.activities', 'actions']);
        });
    }

    /**
     * Reject finance request
     */
    private function rejectFinanceRequest($requestId, $financeId, $remarks)
    {
        return DB::transaction(function () use ($requestId, $financeId, $remarks) {
            $request = AdvanceRequest::findOrFail($requestId);

            if ($request->current_status !== AdvanceRequest::STATUS_FINANCE) {
                throw new Exception('Request is not in finance status');
            }

            // Update all activities with finance rejection
            $request->activities()->update([
                'finance_id' => $financeId,
                'finance_status' => 'Rejected',
                'finance_remark' => $remarks,
                'finance_date' => now()->toDateString()
            ]);

            // Update request status
            $request->update([
                'current_status' => AdvanceRequest::STATUS_FINANCE_REJECTED
            ]);

            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_REJECT, $financeId, null, null, null, $remarks);

            return $request->load(['employees.activities', 'actions']);
        });
    }

    /**
     * Revert finance request
     */
    private function revertFinanceRequest($requestId, $financeId, $remarks)
    {
        return DB::transaction(function () use ($requestId, $financeId, $remarks) {
            $request = AdvanceRequest::findOrFail($requestId);

            if ($request->current_status !== AdvanceRequest::STATUS_FINANCE) {
                throw new Exception('Request is not in finance status');
            }

            // Update request status back to approved (before finance)
            $request->update([
                'current_status' => AdvanceRequest::STATUS_APPROVED,
                'reverted_from_status' => AdvanceRequest::STATUS_FINANCE,
                'revert_reason' => $remarks,
                'reverted_by' => $financeId,
                'reverted_at' => now()
            ]);

            // Log action
            $this->logAction($request, AdvanceRequestAction::ACTION_FINANCE_REVERT, $financeId, null, null, null, $remarks);

            return $request->load(['employees.activities', 'actions']);
        });
    }

    /**
     * Get employee details from HRIMS
     */
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

    /**
     * Log action in audit trail
     */
    private function logAction(AdvanceRequest $request, $actionType, $actorId, $targetId = null, $activityId = null, $approvalLevel = null, $remarks = null, $templateId = null)
    {
        AdvanceRequestAction::create([
            'advance_request_id' => $request->id,
            'submission_no' => $request->submission_no,
            'action_type' => $actionType,
            'action_by' => $actorId,
            'action_to' => $targetId,
            'related_activity_id' => $activityId,
            'related_approval_level' => $approvalLevel,
            'email_template_id' => $templateId,
            'remarks' => $remarks,
            'action_at' => now()
        ]);
    }

    /**
     * Confirm payments via Excel upload
     */
    public function confirmAdvancePayments(Request $request)
    {
        try {
            // Validate file upload
            $request->validate([
                'payment_file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            $file = $request->file('payment_file');
            // Laravel-Excel 4 requires an Import instance (3.1 accepted []); a bare marker import keeps the raw-array read.
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\Import {}, $file);

            if (empty($data) || empty($data[0])) {
                return $this->jsonError('Excel file is empty or invalid');
            }

            $rows = $data[0];
            $processedExcelRows = 0;
            $matchedPayments = 0;
            $unsettledRequests = 0;
            $errors = [];

            DB::beginTransaction();

            // Get all activities ready for payment
            $readyActivities = AdvanceRequestActivity::with(['employee'])
                ->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_READY)
                ->get();

            if ($readyActivities->isEmpty()) {
                DB::rollback();
                return $this->jsonError('No advance requests are ready for payment confirmation');
            }

            // Track which activities have been matched
            $matchedActivityIds = [];

            // Process Excel rows to find matches
            foreach ($rows as $index => $row) {
                // Skip header row
                if ($index === 0)
                    continue;

                try {
                    // Extract data from Excel row (8 columns)
                    // 0: Value Date, 1: Beneficiary Name, 2: Payment Type, 3: Amount,
                    // 4: RBI/UTR Reference Number, 5: Transaction Refno, 6: Beneficiary Account Number, 7: IFSC Code
                    $beneficiaryName = trim($row[1] ?? '');  // Column 2: Beneficiary Name
                    $amount = floatval($row[3] ?? 0);  // Column 4: Amount
                    $transactionRef = trim($row[5] ?? '');  // Column 6: Transaction Refno

                    if (empty($beneficiaryName) || $amount <= 0 || empty($transactionRef)) {
                        $errors[] = 'Row ' . ($index + 1) . ': Missing required fields (beneficiary name, amount, or transaction ref)';
                        continue;
                    }

                    $processedExcelRows++;

                    // STEP 1: Check if reference exists in digi_next_payment_file table
                    $paymentRecord = \App\Models\DigiNextPaymentFile::where('transaction_refno', $transactionRef)->first();

                    if (!$paymentRecord) {
                        // Reference not found in digi_next_payment_file - skip this Excel row
                        continue;
                    }

                    // STEP 2: Find matching activity by Name and Amount (only if reference exists)
                    $bestMatch = null;
                    $bestSimilarity = 0;

                    foreach ($readyActivities as $activity) {
                        // Skip if already matched
                        if (in_array($activity->id, $matchedActivityIds)) {
                            continue;
                        }

                        // Check amount match first (exact match)
                        if (abs($activity->finance_approved_amount - $amount) < 0.01) {
                            $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                            $employeeName = $employeeDetails['EmployeeName'] ?? '';

                            // Calculate name similarity
                            $similarity = $this->calculateNameSimilarity($beneficiaryName, $employeeName);

                            if ($similarity > $bestSimilarity && $similarity > 0.8) {
                                $bestMatch = $activity;
                                $bestSimilarity = $similarity;
                            }
                        }
                    }

                    // STEP 3: Only mark as paid if ALL criteria match (Name + Amount + Reference)
                    // UTR-01: the Excel row (column 5) or the bank-file record must carry the UTR
                    $rowUtr = \App\Support\PaymentUtr::clean($row[4] ?? null) ?? \App\Support\PaymentUtr::clean($paymentRecord->rbi_utr_reference_number ?? null);
                    if ($bestMatch && \App\Support\PaymentUtr::missing($rowUtr)) {
                        $errors[] = 'Row '.($index + 1).": {$beneficiaryName} - no UTR, not marked paid";
                        $bestMatch = null;
                    }
                    if ($bestMatch) {
                        $bestMatch->update([
                            'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_PAID,
                            'payment_amount' => $amount,
                            'payment_date' => now()->toDateString(),
                            'utr_number' => $rowUtr,
                            'payment_remark' => "Payment confirmed via Excel upload - Ref: {$transactionRef}"
                        ]);

                        // Track this activity as matched
                        $matchedActivityIds[] = $bestMatch->id;

                        // Check if all activities in the request are now paid
                        $advanceRequest = $bestMatch->employee->advanceRequest;
                        $this->checkAndUpdateRequestPaymentStatus($advanceRequest);

                        // If flagged as imprest, sync to employee_ledger_balances
                        $isImprest = ($advanceRequest && $advanceRequest->is_imprest) || (!empty($bestMatch->is_imprest));
                        if ($isImprest) {
                            $apiFinance = app(\App\Http\Controllers\Api\Advance\AdvanceFinanceController::class);
                            $apiFinance->syncImprestToEmployeeLedger(
                                $bestMatch->employee->employee_id,
                                $amount,
                                now()->toDateString(),
                                $advanceRequest->year_id ?? null,
                                $bestMatch->employee->company_id ?? 1
                            );
                        }

                        $matchedPayments++;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Row ' . ($index + 1) . ': ' . $e->getMessage();
                }
            }

            // Now mark all unmatched advance requests as unsettled
            foreach ($readyActivities as $activity) {
                if (!in_array($activity->id, $matchedActivityIds)) {
                    // This advance request was not matched with any Excel row
                    $employeeDetails = $this->getEmployeeDetails($activity->employee->employee_id);
                    $employeeName = $employeeDetails['EmployeeName'] ?? 'Unknown';

                    $this->createUnsettledRecord(
                        null,  // No transaction reference since no Excel match
                        $employeeName,
                        null,  // No Excel amount since no match
                        $activity->finance_approved_amount,  // System amount
                        "Advance request not found in payment file - Employee: {$employeeName}, Amount: {$activity->finance_approved_amount}",
                        $activity->id
                    );
                    $unsettledRequests++;
                }
            }

            DB::commit();

            return $this->jsonSuccess([
                'total_excel_rows' => count($rows) - 1,  // Exclude header
                'processed_excel_rows' => $processedExcelRows,
                'total_advance_requests' => $readyActivities->count(),
                'matched_payments' => $matchedPayments,
                'unsettled_requests' => $unsettledRequests,
                'success_rate' => $readyActivities->count() > 0 ? round(($matchedPayments / $readyActivities->count()) * 100, 2) : 0,
                'errors' => $errors
            ], 'Payment confirmation processed successfully');
        } catch (Exception $e) {
            DB::rollback();
            return $this->jsonError('Failed to process payment confirmation: ' . $e->getMessage());
        }
    }

    /**
     * Calculate name similarity between two strings
     */
    private function calculateNameSimilarity($name1, $name2)
    {
        // Convert to lowercase and remove extra spaces
        $name1 = strtolower(trim(preg_replace('/\s+/', ' ', $name1)));
        $name2 = strtolower(trim(preg_replace('/\s+/', ' ', $name2)));

        // If names are identical, return 100% similarity
        if ($name1 === $name2) {
            return 1.0;
        }

        // Calculate similarity using similar_text function
        $similarity = 0;
        similar_text($name1, $name2, $similarity);

        return $similarity / 100;  // Convert percentage to decimal
    }

    /**
     * Create unsettled payment record
     */
    private function createUnsettledRecord($transactionRef, $beneficiaryName, $excelAmount, $dbAmount, $reason, $activityId = null)
    {
        // For unmatched advance requests (no transaction ref), create a unique identifier
        if ($transactionRef === null) {
            // Generate a unique reference for unmatched advance requests
            $transactionRef = 'UNMATCHED_' . ($activityId ?? 'UNKNOWN') . '_' . time();
        }

        // Create or update unsettled payments table
        DB::table('unsettled_payments')->updateOrInsert(
            ['transaction_refno' => $transactionRef],
            [
                'beneficiary_name' => $beneficiaryName,
                'excel_amount' => $excelAmount,
                'db_amount' => $dbAmount,
                'reason' => $reason,
                'status' => 'Unsettled',
                'activity_id' => $activityId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Check and update request payment status
     */
    private function checkAndUpdateRequestPaymentStatus($advanceRequest)
    {
        // Get all activities for this request
        $allActivities = $advanceRequest->activities()->whereNull('deleted_at')->get();
        $paidActivities = $allActivities->where('payment_status', AdvanceRequestActivity::PAYMENT_STATUS_PAID);

        // If all activities are paid, mark request as closed
        if ($allActivities->count() > 0 && $paidActivities->count() === $allActivities->count()) {
            $advanceRequest->update([
                'current_status' => AdvanceRequest::STATUS_CLOSED
            ]);

            // Log the status change
            $this->logAction($advanceRequest, AdvanceRequestAction::ACTION_CLOSE, auth()->id(), null, null, null, 'All payments confirmed - Request closed');
        }
    }

    /**
     * JSON success response helper
     */
    protected function jsonSuccess($data = null, $message = 'Success')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * JSON error response helper
     */
    protected function jsonError($message = 'Error', $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }
}
