<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OdometerApprovalController extends BaseMobileController
{
    /**
     * Get odometer claims pending approval from team members under this HOD.
     */
    public function handlePendingOdoApprove(Request $request): JsonResponse
    {
        try {
            $empId = (int) $request->input('empid');
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');

            $conn = $this->getExpenseConnection((int) $comId);
            $hrimsConn = $this->getHrimsConnection();

            // 1. Get team members
            $teamMembers = DB::connection($hrimsConn)->select(
                "SELECT eg.EmployeeID, eg.DepartmentId, eg.EmpVertical
                 FROM hrm_employee_general eg
                 JOIN hrm_employee e ON eg.EmployeeID = e.EmployeeID
                 WHERE eg.RepEmployeeID = ? AND e.EmpStatus = 'A'",
                [$empId]
            );

            // 2. If no team members, return empty
            if (empty($teamMembers)) {
                return response()->json(['Code' => '200', 'msg' => 'Success', 'data' => []]);
            }

            // Build IN clause for team member IDs
            $teamIds = array_map(function ($member) {
                return $member->EmployeeID;
            }, $teamMembers);
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

            // 3. Get pending odometer claims
            $table = "y{$yearId}_expenseclaims";
            $claims = DB::connection($conn)->select(
                "SELECT e.ExpId, e.CrBy, e.ClaimMonth, e.TotKm, e.BillDate, e.CrDate,
                        e.odomtr_opening, e.odomtr_closing, e.opening_filepath, e.closing_filepath,
                        e.WType, e.App_Odometer, e.Remark, e.DateEntryRemark
                 FROM {$table} e
                 WHERE e.ClaimId = 7
                   AND e.v_verify = 'Y'
                   AND e.ClaimStatus NOT IN ('Draft', 'Deactivate')
                   AND e.WType IN (2, 4)
                   AND e.ClaimYearId = ?
                   AND e.CrBy IN ({$placeholders})
                   AND e.App_Odometer = 'N'
                 ORDER BY e.CrBy, e.ClaimMonth, e.BillDate",
                array_merge([$yearId], $teamIds)
            );

            // 4. For each claim, get employee info and group by employee+month
            $grouped = [];
            $empCache = [];

            foreach ($claims as $claim) {
                $crBy = $claim->CrBy;

                if (!isset($empCache[$crBy])) {
                    $empInfo = DB::connection($hrimsConn)->select(
                        "SELECT EmpCode, CONCAT_WS(' ', Fname, Sname, Lname) AS FullName
                         FROM hrm_employee WHERE EmployeeID = ?",
                        [$crBy]
                    );
                    $empCache[$crBy] = !empty($empInfo) ? $empInfo[0] : null;
                }

                $empData = $empCache[$crBy];
                $key = $crBy . '_' . $claim->ClaimMonth;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'EmpId' => (string) $crBy,
                        'EmpCode' => $empData ? $empData->EmpCode : '',
                        'FullName' => $empData ? $empData->FullName : '',
                        'ClaimMonth' => (string) $claim->ClaimMonth,
                        'items' => [],
                    ];
                }

                $grouped[$key]['items'][] = (array) $claim;
            }

            return response()->json([
                'Code' => '200',
                'msg' => 'Success',
                'data' => array_values($grouped),
            ]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Approve odometer reading for a specific expense.
     */
    public function handleApproveOdoReading(Request $request): JsonResponse
    {
        try {
            $empId = (int) $request->input('empid');
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');
            $expId = (int) $request->input('ExpId');
            $split = (int) $request->input('split', 0);
            $below1200km = $request->input('below_1200km');
            $above1200km = $request->input('above_1200km');

            $conn = $this->getExpenseConnection((int) $comId);
            $table = "y{$yearId}_expenseclaims";

            if ($split == 0) {
                // Simple approval
                DB::connection($conn)->statement(
                    "UPDATE {$table} SET App_Odometer = 'Y' WHERE ExpId = ?",
                    [$expId]
                );
            } else {
                // Split approval: get RatePerKM and adjust amounts
                $expense = DB::connection($conn)->select(
                    "SELECT * FROM {$table} WHERE ExpId = ?",
                    [$expId]
                );

                if (!empty($expense)) {
                    $expense = $expense[0];
                    $ratePerKm = $expense->RatePerKM ?? 0;

                    // Calculate amounts for below and above 1200km
                    $belowAmount = $below1200km * $ratePerKm;
                    $aboveAmount = $above1200km * $ratePerKm;

                    // Approve current record with below_1200km
                    DB::connection($conn)->statement(
                        "UPDATE {$table} SET App_Odometer = 'Y', TotKm = ?, TotAmt = ? WHERE ExpId = ?",
                        [$below1200km, $belowAmount, $expId]
                    );

                    // Reject remaining records if above threshold
                    if ($above1200km > 0) {
                        DB::connection($conn)->statement(
                            "UPDATE {$table} SET App_Odometer = 'R' WHERE ExpId != ? AND CrBy = ? AND ClaimMonth = ? AND ClaimId = 7 AND App_Odometer = 'N'",
                            [$expId, $expense->CrBy, $expense->ClaimMonth]
                        );
                    }
                }
            }

            return response()->json(['Code' => '200', 'msg' => 'Odometer reading approved successfully.']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Reject odometer reading for a specific expense.
     */
    public function handleRejectOdoReading(Request $request): JsonResponse
    {
        try {
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');
            $expId = (int) $request->input('ExpId');

            $conn = $this->getExpenseConnection((int) $comId);
            $table = "y{$yearId}_expenseclaims";

            DB::connection($conn)->statement(
                "UPDATE {$table} SET App_Odometer = 'R' WHERE ExpId = ?",
                [$expId]
            );

            return response()->json(['Code' => '200', 'msg' => 'Odometer reading rejected successfully.']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Approve all odometer readings for a team member in a given month.
     */
    public function handleApproveAllOdoReading(Request $request): JsonResponse
    {
        try {
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');
            $tmEmpId = $request->input('tmempid');
            $month = (int) $request->input('month');

            $conn = $this->getExpenseConnection((int) $comId);
            $table = "y{$yearId}_expenseclaims";

            DB::connection($conn)->statement(
                "UPDATE {$table} SET App_Odometer = 'Y'
                 WHERE CrBy = ? AND ClaimMonth = ? AND ClaimId = 7
                   AND App_Odometer = 'N' AND v_verify = 'Y'
                   AND ClaimStatus NOT IN ('Draft', 'Deactivate')",
                [$tmEmpId, $month]
            );

            return response()->json(['Code' => '200', 'msg' => 'All odometer readings approved.']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Reject all odometer readings for a team member in a given month.
     */
    public function handleRejectAllOdoReading(Request $request): JsonResponse
    {
        try {
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');
            $tmEmpId = $request->input('tmempid');
            $month = (int) $request->input('month');

            $conn = $this->getExpenseConnection((int) $comId);
            $table = "y{$yearId}_expenseclaims";

            DB::connection($conn)->statement(
                "UPDATE {$table} SET App_Odometer = 'R'
                 WHERE CrBy = ? AND ClaimMonth = ? AND ClaimId = 7
                   AND App_Odometer = 'N' AND v_verify = 'Y'
                   AND ClaimStatus NOT IN ('Draft', 'Deactivate')",
                [$tmEmpId, $month]
            );

            return response()->json(['Code' => '200', 'msg' => 'All odometer readings rejected.']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Approve odometer final submit for a team member in a given month.
     */
    public function handleApproveOdoFinalSubmit(Request $request): JsonResponse
    {
        try {
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');
            $tmEmpId = $request->input('tmempid');
            $month = (int) $request->input('month');

            $conn = $this->getExpenseConnection((int) $comId);
            $table = "y{$yearId}_monthexpensefinal";

            DB::connection($conn)->statement(
                "UPDATE {$table} SET Odo_Approve_Final_Submit = 'Y'
                 WHERE EmployeeId = ? AND Month = ? AND YearId = ?",
                [$tmEmpId, $month, $yearId]
            );

            return response()->json(['Code' => '200', 'msg' => 'Odometer final submit approved.']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Get current date odometer details for an employee.
     */
    public function handleCurrentDateOdoDetails(Request $request): JsonResponse
    {
        try {
            $empId = (int) $request->input('empid');
            $date = $request->input('date');
            $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
            $comId = (int) $request->input('ComId');
            $grade = $request->input('Grade');
            $expId = (int) $request->input('ExpId');

            $conn = $this->getExpenseConnection((int) $comId);
            $hrimsConn = $this->getHrimsConnection();

            // 1. Check driving license
            $dlInfo = DB::connection($hrimsConn)->select(
                "SELECT Driv_ExpiryDateTo, DrivingLicNo_YN FROM hrm_employee_personal WHERE EmployeeID = ?",
                [$empId]
            );

            $dlExpired = 'N';
            $dlMessage = '';

            if (!empty($dlInfo)) {
                $dlData = $dlInfo[0];
                if (!empty($dlData->Driv_ExpiryDateTo)) {
                    $expiryDate = strtotime($dlData->Driv_ExpiryDateTo);
                    $currentDate = strtotime($date);
                    if ($currentDate > $expiryDate) {
                        $dlExpired = 'Y';
                        $dlMessage = 'Your driving license has expired on ' . $dlData->Driv_ExpiryDateTo;
                    }
                }
                if (empty($dlData->DrivingLicNo_YN) || $dlData->DrivingLicNo_YN == 'N') {
                    $dlExpired = 'Y';
                    $dlMessage = 'Driving license not available.';
                }
            }

            // 2. Get employee eligibility for vehicle type
            $eligibility = DB::connection($hrimsConn)->select(
                "SELECT VehiclePolicy, Travel_TwoWeeKM, Travel_FourWeeKM
                 FROM hrm_employee_eligibility el
                 LEFT JOIN hrm_employee e ON el.EmployeeID = e.EmployeeID
                 LEFT JOIN hrm_employee_general g ON el.EmployeeID = g.EmployeeID
                 WHERE el.EmployeeID = ? AND el.Status = 'A' AND e.CompanyId = ?",
                [$empId, $comId]
            );

            $vehicleType = '0';
            $vehicleType2W = '0';
            $vehicleType4W = '0';

            if (!empty($eligibility)) {
                $elig = $eligibility[0];
                $policy = $elig->VehiclePolicy ?? '';

                // Determine VehicleType2W and VehicleType4W based on policy
                if ($elig->Travel_TwoWeeKM > 0) {
                    $vehicleType2W = '2';
                }
                if ($elig->Travel_FourWeeKM > 0) {
                    $vehicleType4W = '4';
                }

                // Set default vehicle type
                if ($vehicleType4W == '4') {
                    $vehicleType = '4';
                } elseif ($vehicleType2W == '2') {
                    $vehicleType = '2';
                }
            }

            // 3. Get odometer claims for this date or by ExpId
            $table = "y{$yearId}_expenseclaims";
            
            // When ExpId is provided (from draft list), query by ExpId
            // When no ExpId, query by BillDate (new odometer entry)
            if (!empty($expId) && is_numeric($expId) && (int) $expId > 0) {
                $odoClaims = DB::connection($conn)->select(
                    "SELECT ExpId, ClaimId, CrDate, BillDate, CrBy, Remark, DateEntryRemark,
                            odomtr_opening, opening_filepath, temp_punch_id, TotKm,
                            odomtr_closing, closing_filepath, Img_1, Img_2, Img_3,
                            Img_4 as pdf_1, Img_5 as pdf_2, VType, WType
                     FROM {$table}
                     WHERE ClaimStatus != 'Deactivate' AND ClaimId = 7 AND CrBy = ? AND ExpId = ?",
                    [$empId, $expId]
                );
            } else {
                // Parse date to handle both date and datetime formats
                $queryDate = date('Y-m-d', strtotime($date));
                $odoClaims = DB::connection($conn)->select(
                    "SELECT ExpId, ClaimId, CrDate, BillDate, CrBy, Remark, DateEntryRemark,
                            odomtr_opening, opening_filepath, temp_punch_id, TotKm,
                            odomtr_closing, closing_filepath, Img_1, Img_2, Img_3,
                            Img_4 as pdf_1, Img_5 as pdf_2, VType, WType
                     FROM {$table}
                     WHERE ClaimStatus != 'Deactivate' AND ClaimId = 7 AND CrBy = ? AND BillDate = ?",
                    [$empId, $queryDate]
                );
            }

            $odoDetails = [];
            foreach ($odoClaims as $claim) {
                $rowArr = (array) $claim;
                // Resolve opening/closing filepath to full S3 URLs
                $userId = $rowArr['CrBy'] ?? $empId;
                $rowArr['opening_filepath'] = $this->resolveFileUrl($rowArr['opening_filepath'] ?? '', (int) $yearId, $userId);
                $rowArr['closing_filepath'] = $this->resolveFileUrl($rowArr['closing_filepath'] ?? '', (int) $yearId, $userId);
                $rowArr['Img_1'] = $this->resolveFileUrl($rowArr['Img_1'] ?? '', (int) $yearId, $userId);
                $rowArr['Img_2'] = $this->resolveFileUrl($rowArr['Img_2'] ?? '', (int) $yearId, $userId);
                $rowArr['Img_3'] = $this->resolveFileUrl($rowArr['Img_3'] ?? '', (int) $yearId, $userId);
                $rowArr['pdf_1'] = $this->resolveFileUrl($rowArr['pdf_1'] ?? '', (int) $yearId, $userId);
                $rowArr['pdf_2'] = $this->resolveFileUrl($rowArr['pdf_2'] ?? '', (int) $yearId, $userId);

                // Ensure all values are strings to match legacy mysqli_fetch_assoc behavior
                // Flutter uses string parsing (e.g., double.parse on TotKm)
                foreach ($rowArr as $key => $value) {
                    if (is_null($value)) {
                        $rowArr[$key] = '';
                    } else {
                        $rowArr[$key] = (string) $value;
                    }
                }

                $odoDetails[] = $rowArr;
            }

            // Get remarks for the expense
            $remarks = [];
            $remarkExpId = !empty($expId) && is_numeric($expId) && (int) $expId > 0
                ? (int) $expId
                : (!empty($odoDetails) ? ($odoDetails[0]['ExpId'] ?? null) : null);

            if ($remarkExpId) {
                try {
                    $remarkRows = DB::connection($conn)->select(
                        "SELECT Remark, `by` AS sent_by, CrDateTime FROM y{$yearId}_expenseremark WHERE ExpId = ? ORDER BY CrDateTime ASC",
                        [$remarkExpId]
                    );
                    foreach ($remarkRows as $remark) {
                        $remarks[] = (array) $remark;
                    }
                } catch (\Exception $e) {
                    // Remark table may not exist - skip
                }
            }

            if (!empty($odoDetails)) {
                return response()->json([
                    'Code' => '300',
                    'DLExpired' => $dlExpired,
                    'DLMessage' => $dlMessage,
                    'VehicleType' => $vehicleType,
                    'VehicleType2W' => $vehicleType2W,
                    'VehicleType4W' => $vehicleType4W,
                    'CurrentDate_Odo_Details' => $odoDetails,
                    'Remarks' => $remarks,
                ]);
            } else {
                return response()->json([
                    'Code' => '300',
                    'DLExpired' => $dlExpired,
                    'DLMessage' => $dlMessage,
                    'VehicleType' => $vehicleType,
                    'VehicleType2W' => $vehicleType2W,
                    'VehicleType4W' => $vehicleType4W,
                    'msg' => 'Data Not Available!',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => $e->getMessage()]);
        }
    }
}
