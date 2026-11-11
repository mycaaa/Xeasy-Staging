<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Services\ClaimFiles\ClaimFileBatch;
use App\Services\ClaimFiles\ClaimFileException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles data extraction/OCR and activity-related APIs.
 * Replicates: extract-data.php
 */
class ExtractionController extends BaseMobileController
{
    /**
     * Extract data from uploaded image (OCR for claims).
     * Replicates: extract-data.php?value=extract_data
     */
    public function extractData(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $claimId = (int) $request->input('claim_id');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $claimId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Get API endpoint for this claim type
        $apiDetails = DB::connection($expenseConn)
            ->table('api_list')
            ->where('claim_id', $claimId)
            ->where('status', 1)
            ->first();

        if (!$apiDetails || empty($apiDetails->endpoint)) {
            return response()->json(['success' => 'false', 'message' => 'No API endpoint configured for this claim type']);
        }

        // Handle file upload to S3 and get URL
        if (!$request->hasFile('file')) {
            return response()->json(['success' => 'false', 'message' => 'No file uploaded']);
        }

        $file = $request->file('file');
        $fileUrl = null;

        try {
            $s3Service = app(\App\Services\S3Service::class);
            // LV-S10: type from the content; server-made name (no client file name in the S3 key)
            try {
                $safeExt = \App\Support\SafeUpload::check($file, ['jpg', 'png', 'webp', 'heic', 'gif', 'pdf'], 15360);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => 'false', 'message' => $e->getMessage()]);
            }
            $fileName = \App\Support\SafeUpload::name('Ocr_'.$empId, $safeExt);
            $result = $s3Service->uploadFile([
                'name' => $fileName,
                'tmp_name' => $file->getRealPath(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
            ], $yearId, $empId);

            if ($result['success']) {
                $fileUrl = $result['url'];
            }
        } catch (\Exception $e) {
            return response()->json(['success' => 'false', 'message' => 'S3 upload failed: ' . $e->getMessage()]);
        }

        if (!$fileUrl) {
            return response()->json(['success' => 'false', 'message' => 'File upload failed']);
        }

        // Call external extraction API
        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => \App\Support\TlsVerify::option()])
                ->post($apiDetails->endpoint, ['fileUrl' => $fileUrl]);

            if ($response->successful()) {
                $apiResponse = $response->json();
                
                // The external API returns: {"statusCode":200,"data":{...extracted fields...},"message":"Success","success":true}
                // Flutter expects: {"success":"true","data":{...extracted fields directly...},"fileUrl":"..."}
                $extractedData = [];
                if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                    $extractedData = $apiResponse['data'];
                } elseif (isset($apiResponse['response']) && is_array($apiResponse['response'])) {
                    $extractedData = $apiResponse['response'];
                }

                return response()->json([
                    'success' => 'true',
                    'data' => $extractedData,
                    'fileUrl' => $fileUrl,
                ]);
            }

            return response()->json(['success' => 'false', 'message' => 'API returned error: ' . $response->status()]);
        } catch (\Exception $e) {
            return response()->json(['success' => 'false', 'message' => 'API call failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Extract odometer data from uploaded image.
     * Replicates: extract-data.php?value=extract_odo_data
     */
    public function extractOdoData(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Handle opening and closing file uploads
        $messages = [];
        $openingValue = 0;
        $closingValue = 0;
        $tempPunchId = (int) $request->input('temp_punch_id', 0);

        try {
            $s3Service = app(\App\Services\S3Service::class);

            // Process opening file
            if ($request->hasFile('opening_file')) {
                $openingFile = $request->file('opening_file');
                $uuid = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
                $fileName = "opening_{$empId}_{$uuid}_1.jpg";

                $uploadResult = $s3Service->uploadFile([
                    'name' => $fileName,
                    'tmp_name' => $openingFile->getRealPath(),
                    'size' => $openingFile->getSize(),
                    'type' => $openingFile->getMimeType(),
                ], $yearId, $empId);

                if ($uploadResult['success'] && $uploadResult['url']) {
                    // Call OCR API
                    $ocrResult = $this->callOdometerOcrApi($uploadResult['url']);
                    if ($ocrResult['success']) {
                        $openingValue = $ocrResult['response']['extractedValue'] ?? 0;
                        $messages[] = "Opening extracted: {$openingValue}";
                    }
                }
            }

            // Process closing file
            if ($request->hasFile('closing_file')) {
                $closingFile = $request->file('closing_file');
                $uuid = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
                $fileName = "closing_{$empId}_{$uuid}_2.jpg";

                $uploadResult = $s3Service->uploadFile([
                    'name' => $fileName,
                    'tmp_name' => $closingFile->getRealPath(),
                    'size' => $closingFile->getSize(),
                    'type' => $closingFile->getMimeType(),
                ], $yearId, $empId);

                if ($uploadResult['success'] && $uploadResult['url']) {
                    $ocrResult = $this->callOdometerOcrApi($uploadResult['url']);
                    if ($ocrResult['success']) {
                        $closingValue = $ocrResult['response']['extractedValue'] ?? 0;
                        $messages[] = "Closing extracted: {$closingValue}";
                    }
                }
            }

            // Store in temp_punch table
            $table = "y{$yearId}_temp_punch_7";
            $now = now()->format('Y-m-d H:i:s');

            if ($tempPunchId > 0) {
                DB::connection($expenseConn)->table($table)
                    ->where('id', $tempPunchId)
                    ->update(['closeing' => $closingValue, 'closeing_created_at' => $now]);
            } else {
                $tempPunchId = DB::connection($expenseConn)->table($table)
                    ->insertGetId(['emp_id' => $empId, 'opening' => $openingValue, 'opening_created_at' => $now]);
            }

            // Fetch stored data
            $storedData = DB::connection($expenseConn)->table($table)
                ->where('id', $tempPunchId)
                ->select('id as temp_punch_id', 'opening', 'opening_created_at', 'closeing', 'closeing_created_at')
                ->first();

            return response()->json([
                'Code' => '200',
                'success' => 'true',
                'data' => $storedData,
                'opening_value' => (string) $openingValue,
                'closing_value' => (string) $closingValue,
                'messages' => $messages,
            ]);
        } catch (\Exception $e) {
            Log::error('extractOdoData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Show activity tab configuration.
     * Replicates: extract-data.php?value=show_activity_tab
     */
    public function showActivityTab(Request $request): JsonResponse
    {
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => 200, 'data' => 'no']);
        }

        $expenseConn = $this->getExpenseConnection($comId);

        try {
            // Check if activity tab should be shown from app_settings
            $setting = DB::connection($expenseConn)
                ->select("SELECT setting_value FROM app_settings WHERE setting_key = 'show_activity_tab' LIMIT 1");

            $showTab = 'no';
            if (!empty($setting)) {
                $value = $setting[0]->setting_value ?? '';
                if ($value === '1' || $value === 'Y' || $value === 'yes') {
                    $showTab = 'yes';
                }
            }

            return response()->json(['success' => 200, 'data' => $showTab]);
        } catch (\Exception $e) {
            // Table might not exist - default to yes
            return response()->json(['success' => 200, 'data' => 'yes']);
        }
    }

    /**
     * Get claim list for extraction.
     * Replicates: extract-data.php?value=claim_list
     */
    public function claimList(Request $request): JsonResponse
    {
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => 'false', 'message' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['success' => 'false', 'message' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);

        try {
            $claims = DB::connection($expenseConn)
                ->select("SELECT ClaimId, ClaimName, ClaimStatus FROM claimtype WHERE ClaimStatus='A' ORDER BY ClaimId");

            $data = array_map(function ($row) {
                return (array) $row;
            }, $claims);

            return response()->json(['success' => 'true', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => 'false', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get activity claim list for employee.
     * Replicates: extract-data.php?value=activity_claim_list
     */
    public function activityClaimList(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => 'false', 'message' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['success' => 'false', 'message' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            // Get employee department
            $empDept = DB::connection($hrimsConn)->select(
                "SELECT DepartmentId FROM hrm_employee_general WHERE EmployeeID = ?",
                [$empId]
            );
            $depId = !empty($empDept) ? $empDept[0]->DepartmentId : 0;

            // Get activity names for this department with claim info
            $activities = DB::connection($expenseConn)->select(
                "SELECT an.id, an.activity_name as ClaimId, ct.ClaimName
                 FROM adv_activity_names an 
                 LEFT JOIN claimtype ct ON ct.ClaimId = an.activity_name 
                 WHERE FIND_IN_SET(?, an.dept_id)
                 ORDER BY ct.ClaimName",
                [$depId]
            );

            $data = array_map(function ($row) {
                return [
                    'ClaimId' => (string) ($row->ClaimId ?? ''),
                    'ClaimName' => (string) ($row->ClaimName ?? ''),
                    'min_amount' => '0.00',
                    'max_amount' => '1000000.00',
                    'has_limit' => 'true',
                ];
            }, $activities);

            return response()->json(['success' => 'true', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => 'false', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get advance activity claim list.
     * Replicates: extract-data.php?value=adv_activity_claim_list
     */
    public function advActivityClaimList(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => 'false', 'message' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['success' => 'false', 'message' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            // Get employee department
            $empDept = DB::connection($hrimsConn)->select(
                "SELECT DepartmentId FROM hrm_employee_general WHERE EmployeeID = ?",
                [$empId]
            );
            $depId = !empty($empDept) ? $empDept[0]->DepartmentId : 0;

            // Get activity names for this department
            $activities = DB::connection($expenseConn)->select(
                "SELECT an.id, ct.ClaimId, ct.ClaimName 
                 FROM adv_activity_names an 
                 LEFT JOIN claimtype ct ON ct.ClaimId = an.activity_name 
                 WHERE FIND_IN_SET(?, an.dept_id) 
                 AND ct.ClaimStatus = 'A'
                 ORDER BY ct.ClaimName",
                [$depId]
            );

            $data = array_map(function ($row) {
                return [
                    'ClaimId' => (string) $row->ClaimId,
                    'ClaimName' => (string) $row->ClaimName,
                ];
            }, $activities);

            return response()->json(['success' => 'true', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => 'false', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get activity types.
     * Replicates: extract-data.php?value=activity_type
     */
    public function activityType(Request $request): JsonResponse
    {
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => 'false', 'message' => 'Parameter Missing!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);

        try {
            $types = DB::connection($expenseConn)
                ->select("SELECT id, type_name FROM adv_activity_types WHERE status = 1 ORDER BY type_name");

            $data = array_map(function ($row) {
                return [
                    'id' => (string) $row->id,
                    'type_name' => (string) $row->type_name,
                ];
            }, $types);

            return response()->json(['success' => 'true', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => 'true', 'data' => []]);
        }
    }

    /**
     * Get additional fields for a claim type.
     * Replicates: extract-data.php?value=get_additional_fields
     */
    public function getAdditionalFields(Request $request): JsonResponse
    {
        $claimId = (int) $request->input('ClaimId');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $empId = (int) $request->input('empid');

        if ($claimId <= 0 || $yearId <= 0) {
            return response()->json(['success' => 'false', 'message' => 'Parameter Missing!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            // 1. Get activity_name_id from adv_activity_names
            $activityRow = DB::connection($expenseConn)->selectOne(
                "SELECT id as activity_name_id FROM adv_activity_names WHERE activity_name = ?",
                [$claimId]
            );
            $activityId = $activityRow ? $activityRow->activity_name_id : 0;

            // 2. Get expense head fields for this activity
            $expenses = [];
            if ($activityId > 0) {
                $fields = DB::connection($expenseConn)->select(
                    "SELECT adv_expense_head_category.expense_head_name, 
                            adv_expense_head_category.short_code, 
                            adv_expense_head_category.field_type, 
                            adv_expense_head_category.has_file, 
                            adv_expense_head_category.file_required 
                     FROM adv_expense_head_name_mapping 
                     LEFT JOIN adv_expense_head_category ON adv_expense_head_name_mapping.expense_head_id = adv_expense_head_category.id 
                     WHERE activity_id = ? AND adv_expense_head_name_mapping.checked = 1 
                     ORDER BY adv_expense_head_category.expense_head_name ASC",
                    [$activityId]
                );

                foreach ($fields as $row) {
                    $label = $row->expense_head_name;
                    // Format value: remove special chars, replace spaces with underscore, lowercase
                    $value = preg_replace("/[^A-Za-z0-9 ]/", "", $label);
                    $value = preg_replace("/\s+/", "_", trim($value));
                    $value = preg_replace("/__+/", "_", $value);
                    $value = strtolower($value);

                    $expenses[] = [
                        'label' => $label,
                        'value' => $value,
                        'file_input' => 'file_' . $value . '[]',
                        'field_type' => $row->field_type ?? 'number',
                        'has_file' => $row->has_file ?? '1',
                        'file_required' => $row->file_required ?? '0',
                    ];
                }
            }

            // 3. Get crops filtered by employee's vertical
            $crops = [];
            $vertical = 0;
            if ($empId > 0) {
                $verticalRow = DB::connection($hrimsConn)->selectOne(
                    "SELECT EmpVertical, DepartmentId FROM hrm_employee_general WHERE EmployeeID = ?",
                    [$empId]
                );
                $vertical = $verticalRow ? ($verticalRow->EmpVertical ?? 0) : 0;
            }

            if ($vertical > 0) {
                $cropResults = DB::connection($hrimsConn)->select(
                    "SELECT id, crop_name FROM core_crops WHERE is_active = 1 AND vertical_id = ? AND id NOT IN (32, 33)",
                    [$vertical]
                );
            } else {
                $cropResults = DB::connection($hrimsConn)->select(
                    "SELECT id, crop_name FROM core_crops WHERE is_active = 1 AND id NOT IN (32, 33) ORDER BY crop_name"
                );
            }

            foreach ($cropResults as $row) {
                $crops[] = ['id' => (string) $row->id, 'crop_name' => (string) $row->crop_name];
            }

            return response()->json(['code' => 200, 'msg' => 'Success', 'data' => ['expenses' => $expenses, 'crops' => $crops]]);
        } catch (\Exception $e) {
            Log::error('getAdditionalFields error: ' . $e->getMessage());
            return response()->json(['code' => 200, 'msg' => 'Success', 'data' => ['expenses' => [], 'crops' => []]]);
        }
    }

    /**
     * Get crop varieties.
     * Replicates: extract-data.php?value=get_varieties
     */
    public function getVarieties(Request $request): JsonResponse
    {
        $cropIds = $request->input('crop_ids');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if (empty($cropIds) || $yearId <= 0) {
            return response()->json(['success' => 'false', 'message' => 'Parameter Missing!']);
        }

        $hrimsConn = $this->getHrimsConnection();

        $cropIdArray = is_array($cropIds) ? $cropIds : explode(',', $cropIds);
        $placeholders = implode(',', array_fill(0, count($cropIdArray), '?'));

        try {
            $varieties = DB::connection($hrimsConn)->select(
                "SELECT id, crop_id, variety_name FROM core_varieties WHERE crop_id IN ({$placeholders}) ORDER BY variety_name",
                $cropIdArray
            );

            $data = array_map(function ($row) {
                return ['id' => (string) $row->id, 'crop_id' => (string) $row->crop_id, 'variety_name' => (string) $row->variety_name];
            }, $varieties);

            return response()->json(['success' => 'true', 'code' => 200, 'data' => ['varieties' => $data]]);
        } catch (\Exception $e) {
            // Try without crop_id filter or different connection
            try {
                $expenseConn = $this->getExpenseConnection($this->getComId($request));
                $varieties = DB::connection($expenseConn)->select(
                    "SELECT id, crop_id, variety_name FROM core_varieties WHERE crop_id IN ({$placeholders}) ORDER BY variety_name",
                    $cropIdArray
                );
                $data = array_map(function ($row) {
                    return ['id' => (string) $row->id, 'crop_id' => (string) $row->crop_id, 'variety_name' => (string) $row->variety_name];
                }, $varieties);
                return response()->json(['success' => 'true', 'code' => 200, 'data' => ['varieties' => $data]]);
            } catch (\Exception $e2) {
                return response()->json(['success' => 'true', 'code' => 200, 'data' => ['varieties' => []]]);
            }
        }
    }

    /**
     * Upload activity bill/file and create/update expense claim.
     * Replicates: extract-data.php?value=upload_activity_bill
     * 
     * This is the MAIN activity submission endpoint. It:
     * 1. Creates a new expense claim in y{yearId}_expenseclaims (or updates if is_edit=1)
     * 2. Inserts/updates fill data in y{yearId}_g7_expensefilldata
     * 3. Uploads files to S3 and stores file references
     * 4. Creates monthexpensefinal record if needed
     */
    public function uploadActivityBill(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $expId = (int) $request->input('expid', 0);
        $claimId = (int) $request->input('claim_id');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $claimId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        // UAT E-06: same token check as the other extract-data.php writes.
        if (! $this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $billDate = $request->input('bill_date');
        // BILLDATE-03: day-first (dd/mm/yyyy); strtotime read 05/08 as 8 May
        $billDate = (!empty($billDate)) ? (ClaimSubmissionValidator::parseDate($billDate) ?? date('Y-m-d', strtotime(str_replace('/', '-', (string) $billDate)))) : '0000-00-00';
        $claimMonth = date('m', strtotime($billDate));
        $remark = $request->input('remark', '');
        $amount = (float) $request->input('amount', 0);
        $activityType = $request->input('activity_type', 0);
        $trailNo = $request->input('traill_no', 0);
        $cropList = $request->input('crop_list', '0');
        $varietyList = $request->input('variety_list', '0');

        if (empty($activityType) || $activityType == 0) {
            return response()->json(['Code' => '100', 'msg' => 'Activity type is required!']);
        }

        $isEdit = $request->input('is_edit') == '1' ? true : false;

        // UAT E-06: an edit may change only the token holder's own claim, and only before
        // verification (the edit writes the amount into VerifyTAmt / ApprTAmt / FinancedTAmt).
        if ($isEdit && $expId > 0) {
            $own = DB::connection($expenseConn)->table("y{$yearId}_expenseclaims")
                ->where('ExpId', $expId)->where('ClaimId', $claimId)->where('FilledBy', $empId)
                ->select('ClaimStatus')->first();
            if (! $own) {
                return response()->json(['Code' => '100', 'msg' => 'Claim not found for this employee.']);
            }
            if (in_array((string) $own->ClaimStatus, ['Verified', 'Approved', 'Financed'], true)) {
                return response()->json(['Code' => '100', 'msg' => "This claim is already {$own->ClaimStatus} and can no longer be edited."]);
            }
        }

        // Tracker #21: participant list mandatory for meeting / RCT style activity claims.
        $existingParticipantFiles = 0;
        if ($isEdit && $expId > 0) {
            try {
                $stored = DB::connection($expenseConn)->table("y{$yearId}_g7_expensefilldata")->where('ExpId', $expId)->value('file_participant_list');
                $existingParticipantFiles = count(array_filter((array) json_decode((string) $stored, true)));
            } catch (\Throwable $e) {
                $existingParticipantFiles = 0;
            }
        }
        $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateActivityRequest($request, $existingParticipantFiles, $expenseConn));
        if ($ruleError !== null) {
            return response()->json(['Code' => '100', 'msg' => $ruleError]);
        }
        $participantFields = $this->participantFields($request);

        // UAT R-01: files and DB writes of this save are all-or-nothing. A file that cannot be
        // stored throws ClaimFileException; the transaction rolls back and stored files are deleted.
        $files = new ClaimFileBatch();
        $db = DB::connection($expenseConn);
        $db->beginTransaction();
        try {
            if ($isEdit && $expId > 0) {
                // ─── UPDATE existing expense claim ───
                DB::connection($expenseConn)->statement(
                    "UPDATE y{$yearId}_expenseclaims SET 
                        DateEntryRemark = ?, BillDate = ?, ClaimMonth = ?, 
                        Other_Remark = ?, activity_type = ?, 
                        FilledTAmt = ?, VerifyTAmt = ?, ApprTAmt = ?, FinancedTAmt = ?,
                        traill_no = ?
                    WHERE ExpId = ? AND ClaimId = ? AND FilledBy = ?",
                    [$remark, $billDate, $claimMonth, $remark, $activityType, $amount, $amount, $amount, $amount, $trailNo, $expId, $claimId, $empId]
                );

                // Get existing fill data
                $existingData = DB::connection($expenseConn)
                    ->select("SELECT * FROM y{$yearId}_g7_expensefilldata WHERE ExpId = ?", [$expId]);
                $existingData = !empty($existingData) ? (array) $existingData[0] : [];

                // Get valid columns for this claim type
                $validColumns = $this->getActivityColumns($expenseConn, $claimId, $yearId);

                // Build update data
                $updateData = array_merge(['crop_list' => $cropList, 'variety_list' => $varietyList], $participantFields);

                foreach ($validColumns as $column) {
                    // Set amount value from request
                    $columnValue = $request->input($column);
                    if ($columnValue !== null) {
                        $updateData[$column] = $columnValue;
                    }

                    // Handle file upload for this column
                    $fileKey = "file_" . $column;
                    $fileColumn = "file_" . $column;

                    // Get existing files
                    $existingFiles = [];
                    if (isset($existingData[$fileColumn]) && !empty($existingData[$fileColumn])) {
                        $decoded = json_decode($existingData[$fileColumn], true);
                        if (is_array($decoded)) {
                            $existingFiles = $decoded;
                        }
                    }

                    // Upload new files if present
                    if ($request->hasFile($fileKey)) {
                        $newFileNames = $this->uploadActivityFiles($request, $fileKey, $yearId, $empId, $claimId, $files);
                        if (!empty($newFileNames)) {
                            $combinedFiles = array_unique(array_merge($existingFiles, $newFileNames));
                            $updateData[$fileColumn] = json_encode($combinedFiles);
                        } else {
                            $updateData[$fileColumn] = !empty($existingFiles) ? json_encode($existingFiles) : null;
                        }
                    } else {
                        if (!empty($existingFiles)) {
                            $updateData[$fileColumn] = json_encode($existingFiles);
                        }
                    }

                    // Copy to ver_, app_, pay_ columns
                    foreach (['ver', 'app', 'pay'] as $prefix) {
                        $prefixedColumn = $prefix . '_' . $column;
                        if ($columnValue !== null) {
                            $updateData[$prefixedColumn] = $columnValue;
                        }
                    }
                }

                $this->mergeParticipantListUpload($request, $updateData, $existingData['file_participant_list'] ?? null, $yearId, $empId, $claimId, $files);

                // Filter to only columns that exist in the table
                $tableColumns = $this->getTableColumns($expenseConn, "y{$yearId}_g7_expensefilldata");
                $filteredUpdate = array_intersect_key($updateData, array_flip($tableColumns));

                if (!empty($filteredUpdate)) {
                    DB::connection($expenseConn)->table("y{$yearId}_g7_expensefilldata")
                        ->where('ExpId', $expId)
                        ->update($filteredUpdate);
                }

                $this->assertStoredParticipantFiles($request, $files, $existingParticipantFiles, $expenseConn);
                $db->commit();
                $files->keep();

                return response()->json(['Code' => '200', 'msg' => 'Expense data updated successfully.']);

            } else {
                // ─── CREATE new expense claim ───

                // Deactivate old claim if expid provided (resubmission)
                if ($expId > 0) {
                    // B-01: only the claimant's own, not yet verified claim may be replaced
                    \App\Services\Claims\ClaimTransition::guard($expenseConn, (int) $yearId, fn ($q) => $q->where('ExpId', (int) $expId)->where('ClaimStatus', '!=', 'Deactivate'), 'Deactivate', 'claimant', 'app_activity_resubmit', null, 'app', (int) $empId, (int) ($comId ?? 0) ?: null);
                    DB::connection($expenseConn)->statement(
                        "UPDATE y{$yearId}_expenseclaims SET ClaimStatus = 'Deactivate' WHERE ExpId = ?",
                        [$expId]
                    );
                }

                // Insert into expenseclaims
                $newExpId = DB::connection($expenseConn)->table("y{$yearId}_expenseclaims")->insertGetId([
                    'DateEntryRemark' => $remark,
                    'CrBy' => $empId,
                    'CrDate' => now(),
                    'ClaimYearId' => $yearId,
                    'ClaimStatus' => 'Filled',
                    'ClaimAtStep' => 2,
                    'BillDate' => $billDate,
                    'ClaimMonth' => $claimMonth,
                    'Other_Remark' => $remark,
                    'ClaimId' => $claimId,
                    'activity_type' => $activityType,
                    'FilledTAmt' => $amount,
                    'VerifyTAmt' => $amount,
                    'ApprTAmt' => $amount,
                    'FinancedTAmt' => $amount,
                    'FilledBy' => $empId,
                    'FilledDate' => now(),
                    'FilledOkay' => 1,
                    'traill_no' => $trailNo,
                ], 'ExpId');

                if ($newExpId <= 0) {
                    $db->rollBack();
                    $files->discard();
                    return response()->json(['Code' => '101', 'msg' => 'Failed to retrieve valid ExpId from expenseclaims insert.']);
                }

                // Get valid columns for this claim type
                $validColumns = $this->getActivityColumns($expenseConn, $claimId, $yearId);

                // Build insert data for fill table
                $insertData = array_merge(['ExpId' => $newExpId, 'crop_list' => $cropList, 'variety_list' => $varietyList], $participantFields);

                foreach ($validColumns as $column) {
                    $columnValue = $request->input($column);
                    if ($columnValue !== null) {
                        $insertData[$column] = $columnValue;
                    }

                    // Handle file upload
                    $fileKey = "file_" . $column;
                    $fileColumn = "file_" . $column;

                    if ($request->hasFile($fileKey)) {
                        $newFileNames = $this->uploadActivityFiles($request, $fileKey, $yearId, $empId, $claimId, $files);
                        if (!empty($newFileNames)) {
                            $insertData[$fileColumn] = json_encode($newFileNames);
                        }
                    }

                    // Copy to ver_, app_, pay_ columns
                    foreach (['ver', 'app', 'pay'] as $prefix) {
                        $prefixedColumn = $prefix . '_' . $column;
                        if ($columnValue !== null) {
                            $insertData[$prefixedColumn] = $columnValue;
                        }
                    }
                }

                $this->mergeParticipantListUpload($request, $insertData, $insertData['file_participant_list'] ?? null, $yearId, $empId, $claimId, $files);

                // Filter to only columns that exist in the table
                $tableColumns = $this->getTableColumns($expenseConn, "y{$yearId}_g7_expensefilldata");
                $filteredInsert = array_intersect_key($insertData, array_flip($tableColumns));

                DB::connection($expenseConn)->table("y{$yearId}_g7_expensefilldata")->insert($filteredInsert);

                // Ensure monthexpensefinal record exists for this month
                $monthExists = DB::connection($expenseConn)->select(
                    "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID = ? AND Month = ? AND YearId = ? AND (Status = 'Closed' OR Status = 'Open')",
                    [$empId, $claimMonth, $yearId]
                );

                if (empty($monthExists)) {
                    DB::connection($expenseConn)->table("y{$yearId}_monthexpensefinal")->insertOrIgnore([
                        'EmployeeID' => $empId,
                        'Month' => $claimMonth,
                        'YearId' => $yearId,
                        'Status' => 'Open',
                        'Crdate' => date('Y-m-d'),
                    ]);
                }

                $this->assertStoredParticipantFiles($request, $files, 0, $expenseConn);
                $db->commit();
                $files->keep();

                return response()->json(['Code' => '200', 'msg' => 'Expense data saved successfully.']);
            }
        } catch (\Throwable $e) {
            $db->rollBack();
            $files->discard();
            if ($e instanceof ClaimFileException) {
                return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
            }
            Log::error('uploadActivityBill error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['Code' => '100', 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    /** UAT R-01: tracker #21 again, counting only participant-list files actually stored. */
    private function assertStoredParticipantFiles(Request $request, ClaimFileBatch $files, int $existingParticipantFiles, string $expenseConn): void
    {
        $stored = $files->count('file_participant_list') + $files->count('participant_list_file');
        $error = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateActivityRequest($request, $existingParticipantFiles, $expenseConn, $stored));
        if ($error !== null) {
            throw new ClaimFileException($error);
        }
    }

    /**
     * Participant details sent by the web activity form (participant_names,
     * participant_count, participant_department). Columns are added by
     * migration 2026_11_05_000001; callers filter to existing columns.
     */
    private function participantFields(Request $request): array
    {
        $fields = [];
        foreach (['participant_names', 'participant_department'] as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                $fields[$key] = $value;
            }
        }
        if ((int) $request->input('participant_count', 0) > 0) {
            $fields['participant_count'] = (int) $request->input('participant_count');
        }

        return $fields;
    }

    /** Store the web form's participant_list_file under file_participant_list. */
    private function mergeParticipantListUpload(Request $request, array &$data, $current, int $yearId, int $empId, int $claimId, ClaimFileBatch $files): void
    {
        if (! $request->hasFile('participant_list_file')) {
            return;
        }
        $uploaded = $this->uploadActivityFiles($request, 'participant_list_file', $yearId, $empId, $claimId, $files);
        if (empty($uploaded)) {
            return;
        }
        $existing = is_array($current) ? $current : (array) json_decode((string) ($data['file_participant_list'] ?? $current), true);
        $data['file_participant_list'] = json_encode(array_values(array_unique(array_merge(array_filter($existing), $uploaded))));
    }

    /**
     * Get the valid expense head columns for a given claim/activity type.
     */
    private function getActivityColumns(string $expenseConn, int $claimId, int $yearId): array
    {
        try {
            $rows = DB::connection($expenseConn)->select(
                "SELECT adv_expense_head_category.expense_head_name 
                 FROM adv_expense_head_name_mapping 
                 LEFT JOIN adv_expense_head_category ON adv_expense_head_name_mapping.expense_head_id = adv_expense_head_category.id 
                 WHERE activity_id = (SELECT id FROM adv_activity_names WHERE activity_name = ? LIMIT 1)",
                [$claimId]
            );

            $columns = [];
            foreach ($rows as $row) {
                $columns[] = $this->formatColumnName($row->expense_head_name);
            }

            // Filter against actual table columns
            $tableColumns = $this->getTableColumns($expenseConn, "y{$yearId}_g7_expensefilldata");
            return array_intersect($columns, $tableColumns);
        } catch (\Exception $e) {
            Log::error('getActivityColumns error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all column names from a table.
     */
    private function getTableColumns(string $connection, string $tableName): array
    {
        try {
            $columns = DB::connection($connection)->select("SHOW COLUMNS FROM `{$tableName}`");
            return array_map(function ($col) {
                return $col->Field;
            }, $columns);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format expense head name to column name (same as legacy formatColumnName function).
     */
    private function formatColumnName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }

    /**
     * Upload activity files to S3 and return array of file names.
     *
     * UAT R-01: stored through the save's ClaimFileBatch. A file that cannot be stored
     * throws ClaimFileException (it used to be logged and skipped, and the claim saved).
     * Path unchanged: Expense/{yearId}/{empId}/{name} (S3Service::uploadFile never used
     * the activity path that used to be passed as a 4th argument).
     */
    private function uploadActivityFiles(Request $request, string $fileKey, int $yearId, int $empId, int $claimId, ClaimFileBatch $files): array
    {
        $fileNames = [];
        foreach (array_filter(\Illuminate\Support\Arr::wrap($request->file($fileKey))) as $file) {
            // LV-S10: no client file name in the stored key - only safe characters, server-made prefix
            $orig = $file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : 'file';
            $fileName = 'Act_'.$empId.'_'.date('dmyHis').'_'.ClaimFileBatch::uniqueSuffix().'_'.substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $orig), -60);
            $fileNames[] = $files->putMobile($file, $yearId, $empId, $fileName, $fileKey);
        }

        return $fileNames;
    }

    /**
     * Remove activity bill file.
     * Replicates: extract-data.php?value=remove_activity_bill_file
     */
    public function removeActivityBillFile(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $expId = (int) $request->input('expid');
        $claimId = (int) $request->input('claim_id');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $claimId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $fileName = $request->input('file_name', '');

        if (empty($fileName)) {
            return response()->json(['success' => false, 'message' => 'File name required']);
        }

        try {
            $s3Service = app(\App\Services\S3Service::class);
            $s3Path = $s3Service->generateS3Path($yearId, $empId, $fileName);
            $s3Service->deleteFile($s3Path);

            // Remove from uploads table
            if ($expId > 0) {
                DB::connection($expenseConn)->table($this->uploadsTable($yearId))
                    ->where('ExpId', $expId)
                    ->where('FileName', $fileName)
                    ->delete();
            }

            return response()->json(['success' => true, 'message' => 'File removed successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Cron: Move data from temp to final tables.
     * Replicates: extract-data.php?value=cron_move_data
     */
    public function cronMoveData(Request $request): JsonResponse
    {
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // This would typically be triggered by a scheduler, not the mobile app
        // Implementation depends on specific business logic for moving temp data
        return response()->json(['success' => true, 'message' => 'Cron data move triggered']);
    }

    /**
     * Auto-punch data processing.
     * Replicates: extract-data.php?value=autopunch_data
     */
    public function autopunchData(Request $request): JsonResponse
    {
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // M-08: the punch-to-claim mover (was a stub that moved nothing)
        $queueId = (int) $request->input('queue_id', 0);
        $r = (new \App\Services\Claims\PunchQueueMover($expenseConn, $yearId))->run($queueId ?: null);

        return response()->json([
            'status' => ($r['moved'] + $r['failed']) ? 'success' : 'error',
            'success' => $r['failed'] === 0,
            'messages' => $r['messages'],
            'message' => ($r['moved'] + $r['failed']) ? "{$r['moved']} moved, {$r['failed']} failed" : 'No pending queue items processed',
        ]);
    }

    /**
     * Handle legacy extract-data.php requests via ?value= parameter.
     */
    public function handleLegacy(Request $request): JsonResponse
    {
        $value = $request->input('value', '');

        return match ($value) {
            'extract_data' => $this->extractData($request),
            'extract_odo_data' => $this->extractOdoData($request),
            'show_activity_tab' => $this->showActivityTab($request),
            'claim_list' => $this->claimList($request),
            'activity_claim_list' => $this->activityClaimList($request),
            'adv_activity_claim_list' => $this->advActivityClaimList($request),
            'activity_type' => $this->activityType($request),
            'get_additional_fields' => $this->getAdditionalFields($request),
            'get_varieties' => $this->getVarieties($request),
            'upload_activity_bill' => $this->uploadActivityBill($request),
            'remove_activity_bill_file' => $this->removeActivityBillFile($request),
            'cron_move_data' => $this->cronMoveData($request),
            'autopunch_data' => $this->autopunchData($request),
            default => response()->json(["Code" => "100", "msg" => "Invalid value!"]),
        };
    }

    /**
     * Call the odometer OCR API.
     */
    private function callOdometerOcrApi(string $imageUrl): array
    {
        $apiUrl = (string) config('services.odometer_ocr.url'); // G-02: one place, listed in docs/INTEGRATIONS.md

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => \App\Support\TlsVerify::option()])
                ->post($apiUrl, ['fileUrl' => $imageUrl]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['rawReading'])) {
                    return [
                        'success' => true,
                        'response' => [
                            'extractedValue' => $data['data']['rawReading'],
                            'type' => $data['data']['type'] ?? '',
                            'vehicle' => $data['data']['vehicle'] ?? '',
                            'reading' => $data['data']['reading'] ?? '',
                        ],
                    ];
                }
            }

            return ['success' => false, 'message' => 'OCR extraction failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
