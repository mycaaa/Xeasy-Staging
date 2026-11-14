<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseDetailController extends BaseMobileController
{
    /**
     * Get full expense claim details including type-specific data.
     * Replicates: expense_functions.php -> getExpenseDetails (ExDetail endpoint)
     */
    public function handleExDetail(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $expId = (int) $request->input('expid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $expId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            // 1. Get expense claim data
            $expenseData = DB::connection($expenseConn)->select(
                "SELECT * FROM y{$yearId}_expenseclaims WHERE ExpId = ?",
                [$expId]
            );

            if (empty($expenseData)) {
                return $this->error('Data Not Available!');
            }

            $expense = (array) $expenseData[0];
            $claimId = (int) $expense['ClaimId'];

            // APPR-VER-01: a reporting manager opens a team member's claim only after the month is verified
            $webUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
            $viewer = $webUser ? (int) $webUser->employee_id : $empId;
            if (! \App\Support\ApproverAfterVerifier::mayOpen($comId, $expenseConn, $yearId, $viewer, (int) ($expense['CrBy'] ?? 0), (int) ($expense['ClaimMonth'] ?? 0), $webUser)) {
                return $this->error(\App\Support\ApproverAfterVerifier::MESSAGE);
            }

            // 2. Get claim type info
            $claimTypeData = DB::connection($expenseConn)->select(
                "SELECT cgId, ClaimName FROM claimtype WHERE ClaimId = ?",
                [$claimId]
            );

            if (empty($claimTypeData)) {
                return $this->error('Data Not Available!');
            }

            $cgId = (int) $claimTypeData[0]->cgId;
            $claimName = $claimTypeData[0]->ClaimName;

            // 3. Get expense fill data (type-specific fields)
            $expenseFillData = [];
            if ($cgId > 0) {
                $fillData = DB::connection($expenseConn)->select(
                    "SELECT * FROM y{$yearId}_g{$cgId}_expensefilldata WHERE ExpId = ?",
                    [$expId]
                );
                if (!empty($fillData)) {
                    $expenseFillData = (array) $fillData[0];
                }
            }

            // 4. Get expense claim details (line items)
            $expenseDetails = DB::connection($expenseConn)->select(
                "SELECT Title, Remark, Amount FROM y{$yearId}_expenseclaimsdetails WHERE ExpId = ?",
                [$expId]
            );

            $particulars = [];
            foreach ($expenseDetails as $detail) {
                $particulars[] = [
                    'Title' => $detail->Title,
                    'Remark' => $detail->Remark,
                    'Amount' => $detail->Amount,
                ];
            }

            // 5. Get remarks
            $remarks = DB::connection($expenseConn)->select(
                "SELECT Remark, `by` as sent_by, CrDateTime FROM y{$yearId}_expenseremark WHERE ExpId = ? ORDER BY CrDateTime ASC",
                [$expId]
            );

            $remarksArray = [];
            foreach ($remarks as $remark) {
                $remarksArray[] = (array) $remark;
            }

            // 6. Get employee info from HRIMS
            $employeeInfo = DB::connection($hrimsConn)->select(
                "SELECT CONCAT(Fname, ' ', Sname, ' ', Lname) AS Bpname, hq.city_village_name AS HqName, MobileNo_Vnr
                 FROM hrm_employee e
                 LEFT JOIN hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                 LEFT JOIN core_city_village_by_state hq ON g.HqId = hq.id
                 WHERE e.EmployeeID = ?",
                [$empId]
            );

            $employee = !empty($employeeInfo) ? (array) $employeeInfo[0] : ['Bpname' => '', 'HqName' => '', 'MobileNo_Vnr' => ''];

            // Determine mobile number (legacy behavior)
            $mobileNumber = !empty($employee['MobileNo_Vnr'])
                ? $employee['MobileNo_Vnr']
                : ($expenseFillData['Mobile'] ?? '');

            // Build filtered_data based on ClaimId
            $filteredData = $this->buildFilteredData(
                $claimId, $expId, $expense, $claimName, $expenseFillData,
                $employee, $mobileNumber, $particulars, $yearId, $expenseConn
            );

            // Add Remarks to filtered_data
            if (is_array($filteredData)) {
                $filteredData['Remarks'] = $remarksArray;
            }

            // Build expenses_summary for activity claims (cgId=7) - needed for edit pre-fill
            $expensesSummary = null;
            if ($cgId == 7) {
                $expensesSummary = $this->buildExpensesSummary(
                    $expId, $claimId, $expense, $expenseFillData, $empId, $yearId, $comId, $expenseConn, $hrimsConn
                );
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'filtered_data' => $filteredData,
                'details' => $filteredData,
                'remarks' => $remarksArray,
                'expenses_summary' => $expensesSummary,
                'expenseData' => $expense,
                'expenseFillData' => $expenseFillData,
            ]);

        } catch (\Exception $e) {
            Log::error('ExpenseDetail::handleExDetail error', [
                'message' => $e->getMessage(),
                'empid' => $empId,
                'expid' => $expId,
            ]);
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Build the filtered_data array based on ClaimId.
     */
    private function buildFilteredData(
        int $claimId,
        int $expId,
        array $expense,
        string $claimName,
        array $fillData,
        array $employee,
        string $mobileNumber,
        array $particulars,
        int $yearId,
        string $expenseConn
    ): array|string {
        // Common fields for all claim types
        $common = [
            'ExpId' => $expId,
            'Deny Remark' => $expense['FilOkDenyRemark'] ?? '',
            'Bill Date' => $expense['BillDate'] ?? '',
            'Amount' => $expense['FilledTAmt'] ?? '',
            'Expense Name' => $claimName,
            'Remark' => $expense['DateEntryRemark'] ?? '',
        ];

        // Common remark fields (appended at end for most types)
        $remarkFields = [
            'Verifier Remark' => $expense['VerifyTRemark'] ?? '',
            'Approver Remark' => $expense['ApprTRemark'] ?? '',
            'Finance Remark' => $expense['FinancedTRemark'] ?? '',
        ];

        switch ($claimId) {
            case 1: // Hotel
                return array_merge($common, [
                    'Check-In' => $fillData['Arrival'] ?? '',
                    'Check-Out' => $fillData['Departure'] ?? '',
                    'City Name' => $fillData['CityName'] ?? '',
                    'Hotel Name' => $fillData['HotelName'] ?? '',
                    'Hotel Address' => $fillData['HotelAddress'] ?? '',
                    'Billing Address' => $fillData['BillingAddress'] ?? '',
                    'Meal Plan' => $fillData['Plan'] ?? '',
                    'BillNo' => $fillData['BillNo'] ?? '',
                    'Stay Duration' => $fillData['StayDuration'] ?? '',
                    'GST' => $fillData['GST'] ?? '',
                    'Billing Instruction' => $fillData['BillingInstruction'] ?? '',
                    'NoOfPAX' => $fillData['NoOfPAX'] ?? '',
                    'Pax Details' => $fillData['PaxDetails'] ?? '',
                    'Other Pax Detail' => $fillData['PaxDetails_Other'] ?? '',
                    'Payment Mode' => $fillData['Mode'] ?? '',
                    'Hotel Contact' => $fillData['HotelContact'] ?? '',
                    'Hotel Email' => $fillData['HotelEmail'] ?? '',
                ]);

            case 2: // Flight
                return array_merge($common, [
                    'Booking Date' => $fillData['BookingDate'] ?? '',
                    'Period: (Between Booking & Journey Date)' => $fillData['StayDuration'] ?? '',
                    'Agent Name' => $fillData['Agent'] ?? '',
                    'PNR No.' => $fillData['PNRNo'] ?? '',
                    'Flight Name' => $fillData['TrainBusName'] ?? '',
                    'Class' => $fillData['Class'] ?? '',
                    'Journey From' => $fillData['JourneyFrom'] ?? '',
                    'Journey Upto' => $fillData['JourneyUpto'] ?? '',
                    'Travel Insurance' => $fillData['TravelInsurance'] ?? '',
                ], $remarkFields);

            case 3: // Local Conveyance
                return array_merge($common, [
                    'Mode' => $fillData['Mode'] ?? '',
                    'JourneyFrom' => $fillData['JourneyFrom'] ?? '',
                    'JourneyUpto' => $fillData['JourneyUpto'] ?? '',
                ], $remarkFields);

            case 4: // Vehicle Hire
                return array_merge($common, [
                    'Journey From' => $fillData['JourneyFrom'] ?? '',
                    'Journey Upto' => $fillData['JourneyUpto'] ?? '',
                    'GST' => $fillData['GST'] ?? '',
                    'Bill No' => $fillData['BillNo'] ?? '',
                    'Agency Name' => $fillData['AgencyName'] ?? '',
                    'Agency Address' => $fillData['AgencyAddress'] ?? '',
                    'Invoice No' => $fillData['Invoice'] ?? '',
                    'Date of Travel' => $expense['BillDate'] ?? '',
                    'Journey Start' => $fillData['JourneyStartDt'] ?? '',
                    'Journey End' => $fillData['JourneyEndDt'] ?? '',
                    'Charges Type' => $fillData['DailyBasisCharges'] ?? '',
                    'Fuel Rate' => $fillData['FuelRate'] ?? '',
                    'Kms Travel' => $fillData['KmsTrv'] ?? '',
                    'Amount (Fuel * Kms)' => $fillData['CalAmount'] ?? '',
                    'Fixed Amount' => $fillData['FixedAmount'] ?? '',
                    'Opening Reading' => $fillData['DistTraOpen'] ?? '',
                    'Closing Reading' => $fillData['DistTraClose'] ?? '',
                    'Rate' => $fillData['KmBasisCharges'] ?? '',
                ], $remarkFields);

            case 5: // Telecom
                $dueDate = ($fillData['DueDate'] ?? '') >= '2010-01-01'
                    ? date('d-m-Y', strtotime($fillData['DueDate']))
                    : '';

                return array_merge($common, [
                    'Billing address' => $fillData['BillingAddress'] ?? '',
                    'Mobile No' => $mobileNumber,
                    'If Other Mobile No.' => $fillData['Mobile'] ?? '',
                    'Service ' => $fillData['MobileService'] ?? '',
                    'Tariff Plan' => $fillData['Plan'] ?? '',
                    'Billing Cycle' => $fillData['BillingCycle'] ?? '',
                    'Due Date' => $dueDate,
                    'Bill Amount' => $fillData['RechargeAmt'] ?? '',
                    'Last Payment' => $fillData['LastPayement'] ?? '',
                    'Payment Mode' => $fillData['PaymentMode'] ?? '',
                    'Payment Detail' => $fillData['PaymenDetails'] ?? '',
                ], $remarkFields);

            case 6: // Courier
                return array_merge($common, [
                    'Sender Name' => $fillData['SenderName'] ?? '',
                    'Sender Address' => $fillData['SenderAddress'] ?? '',
                    'Provider Name' => $fillData['ServiceProvider'] ?? '',
                    'Weight Charged' => $fillData['WeightCharged'] ?? '',
                    'Docket No' => $fillData['DocketNumber'] ?? '',
                    'Booked Date' => $fillData['DocketBookedDt'] ?? '',
                    'Receiver Name' => $fillData['ReceiverName'] ?? '',
                    'Receiver Address' => $fillData['ReceiverAddress'] ?? '',
                    'Source City' => $fillData['JourneyFrom'] ?? '',
                    'Destination City' => $fillData['JourneyUpto'] ?? '',
                    'Remarks' => $fillData['Remark'] ?? '',
                ], $remarkFields);

            case 7: // Travel KM / Odometer
                // Get 24 wheeler entry data
                $wheelerData = DB::connection($expenseConn)->select(
                    "SELECT a.*, b.BillDate, b.DateEntryRemark, b.WType
                     FROM y{$yearId}_24_wheeler_entry a
                     LEFT JOIN y{$yearId}_expenseclaims b ON a.ExpId = b.ExpId
                     WHERE a.ExpId = ?",
                    [$expId]
                );

                return array_merge($common, [
                    'Vehicle Type' => (($expense['v_verify'] ?? '') === 'Y') ? ($expense['WType'] ?? '') : '',
                    'Dist. Trv. Open' => $expense['odomtr_opening'] ?? '',
                    'Dist. Trv. Close' => $expense['odomtr_closing'] ?? '',
                    'Total KM' => $expense['TotKm'] ?? '',
                ]);

            case 8: // Entertainment (Self)
            case 9: // Entertainment (Others)
                return array_merge($common, [
                    'Person' => $employee['Bpname'] ?? '',
                    'Person Address' => $employee['HqName'] ?? '',
                ], $remarkFields);

            case 10: // Demo
                return array_merge($common, [
                    'Crop' => $fillData['Crop'] ?? '',
                    'Crop Details' => $fillData['CropDetails'] ?? '',
                    'Particulars' => $particulars,
                ], $remarkFields);

            case 11: // Field Days
                return array_merge($common, [
                    'Crop' => $fillData['Vegetable'] ?? '',
                    'No. of Farmers (FMS)' => $fillData['fms'] ?? '',
                    'Dealers/Trade Partners (DTP)' => $fillData['dtp'] ?? '',
                    'Particulars' => $particulars,
                ], $remarkFields);

            case 12: // Field Days (variant)
                return array_merge($common, [
                    'Crop' => $fillData['Vegetable'] ?? '',
                    'Particulars' => $particulars,
                ], $remarkFields);

            case 13: // Field Days (variant)
                return array_merge($common, [
                    'Crop' => $fillData['Crop'] ?? '',
                    'Crop Detail' => $fillData['CropDetails'] ?? '',
                    'Particulars' => $particulars,
                ], $remarkFields);

            case 14: // Rail
                return array_merge($common, [
                    'Passenger' => $employee['Bpname'] ?? '',
                    'Mode' => 'Rail',
                    'Agent Name' => $fillData['Agent'] ?? '',
                    'PNR Number' => $fillData['PNRNo'] ?? '',
                    'Train Name' => $fillData['TrainBusName'] ?? '',
                    'Class' => $fillData['Class'] ?? '',
                    'Booking Date' => $fillData['BookingDate'] ?? '',
                    'Journey Date' => $fillData['JourneyStartDt'] ?? '',
                    'Period (Between Booking & Journey Date)' => $fillData['StayDuration'] ?? '',
                    'Journey from' => $fillData['JourneyFrom'] ?? '',
                    'Journey Upto' => $fillData['JourneyUpto'] ?? '',
                    'Travel Insurance' => $fillData['TravelInsurance'] ?? '',
                    'Booking Status' => $fillData['BookingStatus'] ?? '',
                ], $remarkFields);

            case 15: // Bus
                return array_merge($common, [
                    'Passenger' => $employee['Bpname'] ?? '',
                    'Mode' => 'Bus',
                    'Agent Name' => $fillData['Agent'] ?? '',
                    'Booking Number' => $fillData['PNRNo'] ?? '',
                    'Bus Name' => $fillData['TrainBusName'] ?? '',
                    'Class' => $fillData['Class'] ?? '',
                    'Booking Date' => $fillData['BookingDate'] ?? '',
                    'Journey Date' => $fillData['JourneyStartDt'] ?? '',
                    'Period (Between Booking & Journey Date)' => $fillData['StayDuration'] ?? '',
                    'Journey from' => $fillData['JourneyFrom'] ?? '',
                    'Journey Upto' => $fillData['JourneyUpto'] ?? '',
                    'Travel Insurance' => $fillData['TravelInsurance'] ?? '',
                ], $remarkFields);

            case 16: // Printing
                return array_merge($common, [
                    'Particulars' => $particulars,
                ], $remarkFields);

            case 17: // Group Meal / Others
                return array_merge($common, [
                    'Particulars' => $particulars,
                    'NoOfPAX' => $fillData['NoOfPAX'] ?? '',
                    'Pax Details' => $fillData['PaxDetails'] ?? '',
                    'Other Pax Detail' => $fillData['PaxDetails_Other'] ?? '',
                ], $remarkFields);

            case 18: // Toll
                return array_merge($common, [
                    'Trip Started On' => $fillData['JourneyStartDt'] ?? '',
                    'Trip Ended On' => $fillData['JourneyEndDt'] ?? '',
                    'Journey From' => $fillData['JourneyFrom'] ?? '',
                    'Journey Upto' => $fillData['JourneyUpto'] ?? '',
                ], $remarkFields);

            case 19: // DA Inside
            case 20: // DA Outside
                return array_merge($common, $remarkFields);

            default:
                return array_merge($common, $remarkFields);
        }
    }

    /**
     * Build expenses_summary for activity claims (cgId=7).
     * This replicates the legacy getExpenseDetails() logic that returns
     * activity name, type, crops, varieties, and per-field expense amounts with files.
     */
    private function buildExpensesSummary(
        int $expId,
        int $claimId,
        array $expense,
        array $fillData,
        int $empId,
        int $yearId,
        int $comId,
        string $expenseConn,
        string $hrimsConn
    ): ?array {
        try {
            // Get activity name
            $activityName = DB::connection($expenseConn)
                ->selectOne("SELECT ClaimName FROM claimtype WHERE ClaimId = ?", [$claimId]);
            $activityNameStr = $activityName ? $activityName->ClaimName : '';

            // Get activity type name
            $activityTypeId = $expense['activity_type'] ?? 0;
            $activityTypeStr = '';
            if ($activityTypeId > 0) {
                $activityType = DB::connection($expenseConn)
                    ->selectOne("SELECT type_name FROM adv_activity_types WHERE id = ?", [$activityTypeId]);
                $activityTypeStr = $activityType ? $activityType->type_name : '';
            }

            // Get crop names
            // LV-S08: the stored list is free text; keep only numeric ids before building IN (...)
            $cropIds = implode(',', array_filter(array_map('intval', explode(',', (string) ($fillData['crop_list'] ?? '')))));
            $cropNames = '';
            if (!empty($cropIds) && $cropIds !== '0') {
                $cropResult = DB::connection($hrimsConn)
                    ->selectOne("SELECT GROUP_CONCAT(crop_name) AS crops FROM core_crops WHERE id IN ({$cropIds})");
                $cropNames = $cropResult ? ($cropResult->crops ?? '') : '';
            }

            // Get variety names
            $varietyIds = implode(',', array_filter(array_map('intval', explode(',', (string) ($fillData['variety_list'] ?? '')))));
            $varietyNames = '';
            if (!empty($varietyIds) && $varietyIds !== '0') {
                $varietyResult = DB::connection($hrimsConn)
                    ->selectOne("SELECT GROUP_CONCAT(variety_name) AS varieties FROM core_varieties WHERE id IN ({$varietyIds})");
                $varietyNames = $varietyResult ? ($varietyResult->varieties ?? '') : '';
            }

            // Get expense field definitions for this claim type
            $expenseFields = [];
            try {
                // Call the legacy API endpoint format to get additional fields
                $fields = DB::connection($expenseConn)->select(
                    "SELECT ehc.expense_head_name as label, LOWER(REPLACE(REPLACE(TRIM(ehc.expense_head_name), ' ', '_'), '-', '_')) as value,
                            CONCAT('file_', LOWER(REPLACE(REPLACE(TRIM(ehc.expense_head_name), ' ', '_'), '-', '_')), '[]') as file_input,
                            '1' as has_file
                     FROM adv_expense_head_name_mapping ehnm
                     LEFT JOIN adv_expense_head_category ehc ON ehnm.expense_head_id = ehc.id
                     WHERE ehnm.activity_id = (SELECT id FROM adv_activity_names WHERE activity_name = ? LIMIT 1)",
                    [$claimId]
                );
                foreach ($fields as $field) {
                    $expenseFields[] = (array) $field;
                }
            } catch (\Exception $e) {
                Log::warning('buildExpensesSummary: Could not get expense fields: ' . $e->getMessage());
            }

            // Build expense details with amounts and file URLs
            $totalAmount = 0;
            $expenseDetails = [];

            foreach ($expenseFields as $field) {
                $colName = $this->formatActivityColumnName($field['label']);
                $fieldAmount = $fillData[$colName] ?? '0';
                $totalAmount += (float) $fieldAmount;

                // Get files for this field
                $fileColumn = 'file_' . $colName;
                $files = [];
                if (isset($fillData[$fileColumn]) && !empty($fillData[$fileColumn])) {
                    $decoded = json_decode($fillData[$fileColumn], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $fileName) {
                            // Try multiple S3 paths (same as legacy)
                            $s3Paths = [
                                "Expense/activity/{$yearId}/{$claimId}/{$empId}/{$fileName}",
                                "Expense/{$yearId}/{$empId}/{$fileName}",
                                "Expense/6/{$empId}/{$fileName}",
                            ];
                            $fileUrl = '';
                            try {
                                $s3Service = app(\App\Services\S3Service::class);
                                foreach ($s3Paths as $s3Path) {
                                    if ($s3Service->fileExists($s3Path)) {
                                        $fileUrl = $s3Service->getFileViewUrl($s3Path);
                                        break;
                                    }
                                }
                            } catch (\Exception $e) {
                                // S3 not available
                            }
                            if (empty($fileUrl)) {
                                $fileUrl = "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$empId}/" . rawurlencode($fileName);
                            }
                            $files[] = $fileUrl;
                        }
                    }
                }

                // Get verifier amount and remark
                $verAmount = $fillData['ver_' . $colName] ?? '0';
                $verRemark = $fillData['ver_remark_' . $colName] ?? '';

                $expenseDetails[] = [
                    'label' => $field['label'],
                    'label_value' => $colName,
                    'amount' => empty($fieldAmount) ? '0' : (string) $fieldAmount,
                    'files' => $files,
                    'verifier_amount' => empty($verAmount) ? '0' : (string) $verAmount,
                    'verifier_remark' => (string) $verRemark,
                ];
            }

            // Check if employee is in Trialing & PD department (depId=17)
            $summary = [
                'Exp Id' => (string) $expId,
                'ExpId' => (string) $expId,
                'Activity Name' => $activityNameStr,
                'Activity Type' => $activityTypeStr,
                'Crops' => $cropNames,
                'Varieties' => $varietyNames,
                'Total Amount' => $totalAmount,
                'expenses' => $expenseDetails,
            ];

            // Add Trail No for Trialing department
            $empDept = DB::connection($hrimsConn)
                ->selectOne("SELECT DepartmentId FROM hrm_employee_general WHERE EmployeeID = ?", [$empId]);
            if ($empDept && (int) $empDept->DepartmentId === 17) {
                $summary['Trail No'] = $expense['traill_no'] ?? '';
            }

            return $summary;
        } catch (\Exception $e) {
            Log::error('buildExpensesSummary error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format expense head name to column name (lowercase, spaces/special to underscore).
     */
    private function formatActivityColumnName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }

    /**
     * Mark expense as Agreed by employee.
     * Replicates: expense_functions.php -> Agree endpoint
     */
    public function handleAgree(Request $request): JsonResponse
    {
        // Flutter sends JSON body with camelCase keys: empId, month, year, ComId, tokenId, items, remark
        $empId = (int) ($request->input('empId') ?: $request->input('empid'));
        $month = (int) $request->input('month');
        $yearId = (int) ($request->input('year') ?: $request->input('YearId'));
        $comId = (int) ($request->input('ComId') ?: $request->input('comId', 1));
        $remark = $request->input('remark', '');
        $items = $request->input('items', []);
        $policyId = $request->input('policyId', '');

        if ($empId <= 0 || $month <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        $expenseConn = $this->getExpenseConnection($comId);

        try {
            $tableName = "y{$yearId}_expenseclaims";
            $table2Name = "y{$yearId}_24_wheeler_entry";

            // Update claims based on policy type (same as legacy)
            if (empty($policyId) || !in_array((int)$policyId, [1, 2, 5, 7, 12])) {
                // Non-policy vehicles: bulk update all verified odometer claims
                DB::connection($expenseConn)->statement(
                    "UPDATE `{$tableName}` SET c_verify = 'Y', FilledOkay=1 
                     WHERE v_verify = 'Y' AND ClaimId=7 AND CrBy = ? AND MONTH(BillDate) = ?",
                    [$empId, $month]
                );
            } else {
                // Policy vehicles: update per-item rates
                if (!empty($items)) {
                    foreach ($items as $item) {
                        $expId = (int) ($item['expId'] ?? 0);
                        $mainRate = $item['MainRate'] ?? '0';
                        $withDriv = $item['WithDriv'] ?? 'N';
                        $rAmt = $item['RAmt'] ?? '0';
                        $r2Amt = $item['R2Amt'] ?? '0';

                        if ($expId > 0 && $rAmt != $r2Amt) {
                            DB::connection($expenseConn)->statement(
                                "UPDATE `{$tableName}` SET RatePerKM=?, FilledTAmt=?, c_verify='Y', With_Driver=?, FilledOkay=1 
                                 WHERE v_verify = 'Y' AND ClaimId=7 AND CrBy = ? AND ExpId = ?",
                                [$mainRate, $rAmt, $withDriv, $empId, $expId]
                            );
                            // Also update 24_wheeler_entry
                            try {
                                DB::connection($expenseConn)->statement(
                                    "UPDATE `{$table2Name}` SET Rate_PerKm=?, FilledTAmt=? WHERE ExpId=?",
                                    [$mainRate, $rAmt, $expId]
                                );
                            } catch (\Exception $e) {
                                // Table may not exist
                            }
                        }
                    }
                }
            }

            // Log the agree activity to runkm_logdetails (same as legacy)
            DB::connection($expenseConn)->statement(
                "INSERT INTO y{$yearId}_runkm_logdetails (remark, emp_id, month, year, CrBy, action, date_submitted) 
                 VALUES (?, ?, ?, ?, ?, 'Agree', NOW())",
                [$remark, $empId, $month, $yearId, $empId]
            );

            return response()->json(['status' => 'success', 'message' => 'Remark submitted successfully.']);

        } catch (\Exception $e) {
            Log::error('ExpenseDetail::handleAgree error', [
                'message' => $e->getMessage(),
                'empid' => $empId,
                'month' => $month,
            ]);
            return response()->json(['status' => 'error', 'Code' => '100', 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Mark expense as Not Agreed by employee with remark.
     * Replicates: expense_functions.php -> NotAgree endpoint
     */
    public function handleNotAgree(Request $request): JsonResponse
    {
        // Flutter sends all params in URL query string for NotAgree
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('month');
        $remark = $request->input('remark', '');
        $yearId = (int) ($request->input('YearId') ?: $request->input('year'));
        $comId = (int) ($request->input('ComId') ?: $request->input('comId', 1));

        if ($empId <= 0 || $month <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Parameter Missing!']);
        }

        // ODO-AGREE-01: a reason is needed, and the readings go back to the running-km verifier (Denied list there)
        if (trim((string) $remark) === '') {
            return response()->json(['status' => 'error', 'message' => 'Please write why you do not agree.']);
        }
        $expenseConn = $this->getExpenseConnection($comId);

        try {
            DB::connection($expenseConn)->statement(
                "UPDATE y{$yearId}_expenseclaims SET FilledOkay = 2
                 WHERE ClaimId=7 AND CrBy=? AND ClaimMonth=? AND v_verify='Y' AND COALESCE(c_verify,'N')<>'Y'
                 AND ClaimStatus NOT IN ('Deactivate','Deactivat','Draft')",
                [$empId, $month]
            );
            // Log the not agree activity to runkm_logdetails (same as legacy)
            DB::connection($expenseConn)->statement(
                "INSERT INTO y{$yearId}_runkm_logdetails (remark, emp_id, month, year, CrBy, action, date_submitted) 
                 VALUES (?, ?, ?, ?, ?, 'NotAgree', NOW())",
                [$remark, $empId, $month, $yearId, $empId]
            );

            return response()->json(['status' => 'success', 'message' => 'Not Agree submitted successfully.']);

        } catch (\Exception $e) {
            Log::error('ExpenseDetail::handleNotAgree error', [
                'message' => $e->getMessage(),
                'empid' => $empId,
                'month' => $month,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Get vehicle running KM details for an employee for a given month.
     * Simplified version of legacy 300+ line VehicleRunning endpoint.
     */
    public function handleVehicleRunning(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('month');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $month <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            // 1. Get employee eligibility & vehicle policy
            $eligibility = DB::connection($hrimsConn)->select(
                "SELECT e.EmpCode, VehiclePolicy, old_VehiclePolicy, g.GradeId
                 FROM hrm_employee_eligibility el
                 LEFT JOIN hrm_employee e ON el.EmployeeID = e.EmployeeID
                 LEFT JOIN hrm_employee_general g ON el.EmployeeID = g.EmployeeID
                 WHERE el.EmployeeID = ? AND el.Status = 'A'",
                [$empId]
            );

            if (empty($eligibility)) {
                return response()->json(['status' => 'error', 'message' => 'Employee eligibility not found.', 'data' => []]);
            }

            $empEligibility = (array) $eligibility[0];
            $vehiclePolicy = $empEligibility['VehiclePolicy'] ?? '';

            // 2. Get running KM items for the month
            $runningKm = DB::connection($expenseConn)->select(
                // ODO-AGREE-01 (13 Nov 2026): the readings the verifier has checked (v_verify), so the employee can agree
                // or not agree; this listed only the ones already agreed (c_verify), so there was nothing to agree to
                "SELECT ExpId, ClaimId, BillDate, TotKm, WType, RatePerKM, Amount, With_Driver,
                        odomtr_opening, opening_filepath, odomtr_closing, closing_filepath, c_verify, v_verify, FilledOkay, VerifyTAmt
                 FROM y{$yearId}_expenseclaims
                 WHERE ClaimId=7 AND ClaimMonth=? AND CrBy=?
                 AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft' AND (v_verify='Y' OR c_verify='Y')
                 ORDER BY BillDate ASC",
                [$month, $empId]
            );

            // 3. Build items with correct keys matching Flutter model
            $items = [];
            $totalKm = 0;
            $sno = 0;

            foreach ($runningKm as $row) {
                $sno++;
                $totalKm += (float) $row->TotKm;

                $items[] = [
                    'serial_no' => (string) $sno,
                    'ExpId' => (string) $row->ExpId,
                    'ClaimId' => (string) $row->ClaimId,
                    'BillDate' => $row->BillDate,
                    'TotKm' => (string) $row->TotKm,
                    'RatePerKM' => (string) ($row->RatePerKM ?? '0'),
                    'Amount' => (string) ($row->Amount ?? '0'),
                    'With_Driver' => (string) ($row->With_Driver ?? 'N'),
                    'c_verify' => (string) ($row->c_verify ?? 'N'),
                    'FilledOkay' => (string) ($row->FilledOkay ?? '0'),
                    'VerifyTAmt' => (string) ($row->VerifyTAmt ?? '0'),
                    'odomtr_opening' => (string) ($row->odomtr_opening ?? '0'),
                    'opening_filepath' => $this->resolveFileUrl($row->opening_filepath ?? '', $yearId, $empId),
                    'odomtr_closing' => (string) ($row->odomtr_closing ?? '0'),
                    'closing_filepath' => $this->resolveFileUrl($row->closing_filepath ?? '', $yearId, $empId),
                ];
            }

            // 4. Get yearly total km for the plan calculation
            $yearlyKmRow = DB::connection($expenseConn)->selectOne(
                "SELECT COALESCE(SUM(TotKm), 0) as yearly_total
                 FROM y{$yearId}_expenseclaims
                 WHERE ClaimId=7 AND CrBy=? AND ClaimYearId=?
                 AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft' AND c_verify='Y'",
                [$empId, $yearId]
            );
            $yearlyTotalKm = $yearlyKmRow ? (string) $yearlyKmRow->yearly_total : '0';

            // 5. Get activity log (agree/not agree actions)
            $activity = [];
            try {
                $activityRows = DB::connection($expenseConn)->select(
                    "SELECT id, remark, emp_id, month, year, action, CrBy, date_submitted
                     FROM y{$yearId}_runkm_logdetails
                     WHERE emp_id=? AND month=? AND year=?
                     ORDER BY id DESC",
                    [$empId, $month, $yearId]
                );
                foreach ($activityRows as $ar) {
                    $activity[] = [
                        'id' => (string) $ar->id,
                        'remark' => (string) ($ar->remark ?? ''),
                        'emp_id' => (string) $ar->emp_id,
                        'month' => (string) $ar->month,
                        'year' => (string) $ar->year,
                        'action' => (string) ($ar->action ?? ''),
                        'CrBy' => (string) ($ar->CrBy ?? ''),
                        'date_submitted' => (string) ($ar->date_submitted ?? ''),
                    ];
                }
            } catch (\Exception $e) {
                // Table may not exist
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data retrieved successfully',
                'data' => [
                    'items' => $items,
                    'activity' => $activity,
                    'Vehicle_policy' => (string) $vehiclePolicy,
                    'Vehicle_policy_id' => (string) $vehiclePolicy,
                    'Plan' => '',
                    'Max_km' => '',
                    'monthly_total_km' => (string) $totalKm,
                    'yearly_total_km' => $yearlyTotalKm,
                    'With_ass' => 'No',
                    'Rate' => '0',
                    'ReduceRate' => '0',
                    'Rate_VDriv' => '0',
                    'ReduceRate_VDriv' => '0',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('ExpenseDetail::handleVehicleRunning error', [
                'message' => $e->getMessage(),
                'empid' => $empId,
                'month' => $month,
            ]);
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get last odometer readings for team members.
     * Replicates: TeamLastOdo endpoint
     */
    public function handleTeamLastOdo(Request $request): JsonResponse
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

        // TEAM-ODO-01: the app's VNR / Nursery / VVNR tabs name the company in the path (api/3/common.php,
        // api/11/api/common_rep.php) but send the user's own ComId and year; the tab's company decides, and the year is
        // matched to that company by its label (VNR 8 = VVNR 6 = 2026-2027)
        $tabCom = (int) $request->route('comId');
        if (in_array($tabCom, [1, 3, 11], true) && $tabCom !== $comId) {
            try {
                $label = DB::connection($this->getExpenseConnection($comId))->table('financialyear')->where('YearId', $yearId)->value('Year');
                $mapped = $label ? DB::connection($this->getExpenseConnection($tabCom))->table('financialyear')->where('Year', $label)->value('YearId') : null;
                if (! $mapped) {
                    return response()->json(['Code' => '200', 'msg' => 'No data for this company and year.', 'data' => [], 'team_odo' => []]);
                }
                $yearId = (int) $mapped;
                $comId = $tabCom;
            } catch (\Throwable $e) {
                return response()->json(['Code' => '200', 'msg' => 'This company\'s data cannot be read just now.', 'data' => [], 'team_odo' => []]);
            }
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            // TEAM-ODO-01 (14 Nov 2026): "My Team Last Odometer" in the app showed "Error: Exception: API Error: Data
            // Available!" on all three tabs. The app reads the legacy reply - Code "200" and a "data" list with
            // FullName, EmpCode, BillDate, OdomtrOpening / OdomtrClosing and the two photos - while this answered
            // Code "300" with a differently named "team_odo" list. It now answers in the app's format (team_odo kept),
            // for active team members only, with photo links through claim-file.php (the app sends its login headers).
            $teamMembers = DB::connection($hrimsConn)->table('hrm_employee_general as eg')
                ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->where('eg.RepEmployeeID', $empId)->where('e.EmpStatus', 'A')
                ->orderBy('e.Fname')
                ->get(['e.EmployeeID', 'e.EmpCode', DB::raw("TRIM(CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) AS EmpName")]);

            $base = rtrim((string) config('app.url'), '/').'/api/'.$comId.'/claim-file.php';
            $fileUrl = fn ($emp, $name) => trim((string) $name) === '' ? '' : $base.'?'.http_build_query(['y' => $yearId, 'e' => $emp, 'f' => basename((string) $name)]);

            $data = [];
            $teamOdo = [];
            foreach ($teamMembers as $m) {
                $last = DB::connection($expenseConn)->table("y{$yearId}_expenseclaims")
                    ->where('CrBy', $m->EmployeeID)->where('ClaimId', 7)
                    ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat'])
                    ->where('odomtr_closing', '>', 0)
                    ->orderByDesc('BillDate')->orderByDesc('ExpId')
                    ->first(['ExpId', 'BillDate', 'odomtr_opening', 'odomtr_closing', 'opening_filepath', 'closing_filepath']);
                $teamOdo[] = [
                    'EmployeeID' => (string) $m->EmployeeID,
                    'EmpCode' => (string) $m->EmpCode,
                    'EmpName' => preg_replace('/\s+/', ' ', (string) $m->EmpName),
                    'last_odo_closing' => $last ? (string) $last->odomtr_closing : '0',
                    'last_odo_date' => $last ? (string) $last->BillDate : '',
                ];
                if (! $last) {
                    continue;
                }
                $data[] = [
                    'EmployeeID' => (string) $m->EmployeeID,
                    'EmpCode' => (string) $m->EmpCode,
                    'FullName' => preg_replace('/\s+/', ' ', (string) $m->EmpName),
                    'ExpId' => (string) $last->ExpId,
                    'BillDate' => (string) $last->BillDate,
                    'OdomtrOpening' => (string) ($last->odomtr_opening ?? ''),
                    'OdomtrClosing' => (string) ($last->odomtr_closing ?? ''),
                    'opening_filepath' => $fileUrl($m->EmployeeID, $last->opening_filepath),
                    'closing_filepath' => $fileUrl($m->EmployeeID, $last->closing_filepath),
                ];
            }
            usort($data, fn ($a, $b) => strcmp($b['BillDate'], $a['BillDate']));   // latest first

            return response()->json([
                'Code' => '200',
                'msg' => $data ? 'Data Available!' : 'No odometer entries for your team this year.',
                'data' => $data,
                'team_odo' => $teamOdo,
            ]);

        } catch (\Exception $e) {
            Log::error('ExpenseDetail::handleTeamLastOdo error', [
                'message' => $e->getMessage(),
                'empid' => $empId,
            ]);
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Check if there are pending odometer approvals for the team.
     * Replicates: ShowHidePending endpoint
     */
    public function handleShowHidePending(Request $request): JsonResponse
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

        try {
            // 1. Get team members from reporting hierarchy
            $teamMembers = DB::connection($hrimsConn)->select(
                "SELECT EmployeeID FROM hrm_employee_general WHERE RepEmployeeID = ?",
                [$empId]
            );

            if (empty($teamMembers)) {
                return response()->json([
                    'Code' => '300',
                    'show_hide_pending' => 'N',
                    'count' => '0',
                ]);
            }

            // Build comma-separated IDs for IN clause
            $memberIds = array_map(function ($m) {
                return (int) $m->EmployeeID;
            }, $teamMembers);

            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

            // 2. Count pending odo approvals
            $pendingCount = DB::connection($expenseConn)->select(
                "SELECT COUNT(*) as cnt FROM y{$yearId}_expenseclaims
                 WHERE CrBy IN ({$placeholders}) AND ClaimId=7 AND App_Odometer='N'
                 AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft' AND v_verify='Y'",
                $memberIds
            );

            $count = !empty($pendingCount) ? (int) $pendingCount[0]->cnt : 0;

            $val = $count > 0 ? 'Y' : 'N';
            return response()->json([
                'Code' => '200',
                'code' => '200',
                'data' => $val,
                'Data' => $val,
                'show_hide_pending' => $val,
                'count' => (string) $count,
                'msg' => 'Success',
            ]);

        } catch (\Exception $e) {
            Log::error('ExpenseDetail::handleShowHidePending error', [
                'message' => $e->getMessage(),
                'empid' => $empId,
            ]);
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }
}
