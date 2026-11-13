<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaimListController extends BaseMobileController
{
    /**
     * Get open months claim list for employee.
     * Replicates: common.php?value=Openlist
     */
    public function handleOpenlist(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($YearId) || empty($ComId)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            // Get open months from monthexpensefinal
            $sql = "SELECT Month, SubPermission, YearId, EmployeeID FROM y{$YearId}_monthexpensefinal WHERE EmployeeID='{$empid}' and YearId={$YearId} and Status='Open' group by Month order by Month asc";
            $openMonths = DB::connection($expenseConn)->select($sql);

            // Get month_list
            $sqlMonthList = "SELECT Month FROM y{$YearId}_monthexpensefinal WHERE EmployeeID='{$empid}' AND YearId={$YearId} AND Status='Open' GROUP BY Month ORDER BY Month ASC";
            $monthListRows = DB::connection($expenseConn)->select($sqlMonthList);

            $month_list = [];
            foreach ($monthListRows as $row) {
                $month_list[] = (array) $row;
            }

            $Open_list = [];
            $currentMonth = (int) date('n');
            $currentDay = (int) date('j');

            foreach ($openMonths as $monthRow) {
                $month = $monthRow->Month;
                $SubPermission = $monthRow->SubPermission;

                // TotalFilled query
                $sqlFilled = "SELECT COUNT(CASE WHEN (ClaimId != 7 OR (ClaimId = 7 AND v_verify = 'Y' AND ClaimStatus = 'Filled')) THEN 1 END) AS TotClaim, COALESCE(SUM(CASE WHEN FilledTAmt != '' AND (ClaimId != 7 OR (ClaimId = 7 AND v_verify = 'Y' AND ClaimStatus = 'Filled')) THEN FilledTAmt END), 0) AS TotFilledAmt, COUNT(CASE WHEN FilledOkay = 1 THEN 1 END) AS TotVerifiedClaims, COUNT(CASE WHEN FilledOkay = 2 THEN 1 END) AS TotDeniedClaims, COUNT(CASE WHEN FilledOkay = 0 THEN 1 END) AS TotUnactionedClaims FROM y{$YearId}_expenseclaims WHERE ClaimYearId = '{$YearId}' AND CrBy = '{$empid}' AND ClaimMonth = '{$month}' AND FilledBy > 0 AND FilledDate != '0000-00-00' AND FilledDate != '1970-01-01' AND ClaimStatus = 'Filled' AND ClaimStatus != 'Deactivate' AND ClaimStatus != 'Draft'";
                $filledResult = DB::connection($expenseConn)->select($sqlFilled);

                if (!empty($filledResult)) {
                    $filled = (array) $filledResult[0];
                    $TotClaim = $filled['TotClaim'] ?? 0;
                    $TotFilledAmt = $filled['TotFilledAmt'] ?? 0;
                    $TotVerifiedClaims = $filled['TotVerifiedClaims'] ?? 0;
                    $TotDeniedClaims = $filled['TotDeniedClaims'] ?? 0;
                    $TotUnactionedClaims = $filled['TotUnactionedClaims'] ?? 0;

                    if ($TotClaim > 0) {
                        // Get TotVerifyAmt
                        $sqlVerify = "SELECT COALESCE(SUM(CASE WHEN FilledTAmt != '' THEN FilledTAmt END), 0) as TotVerifyAmt FROM y{$YearId}_expenseclaims WHERE ClaimYearId = '{$YearId}' AND CrBy = '{$empid}' AND ClaimMonth = '{$month}' AND FilledOkay = 1 AND ClaimStatus != 'Submitted' AND ClaimStatus != 'Deactivate' AND ClaimStatus != 'Draft'";
                        $verifyResult = DB::connection($expenseConn)->select($sqlVerify);
                        $TotVerifyAmt = '0';
                        if (!empty($verifyResult)) {
                            $TotVerifyAmt = ((array) $verifyResult[0])['TotVerifyAmt'] ?? '0';
                        }

                        // SUBMIT-BTN-01: same rule as the submit itself (month ended, all punched, due date / late approval)
                        $st = \App\Services\ClaimRules\MonthSubmit::state($expenseConn, (int) $ComId, (int) $YearId, (int) $empid, (int) $month, $SubPermission);
                        $submit_btn = $st['allowed'] ? 'Y' : 'N';

                        // Match legacy response structure EXACTLY
                        $Open_list[] = [
                            'Month' => $month,
                            'SubPermission' => $SubPermission,
                            'YearId' => (string) $YearId,
                            'EmployeeID' => (string) $empid,
                            'TotalFilled_Claim_with_Amount' => [
                                'TotClaim' => (string) $TotClaim,
                                'TotFilledAmt' => (string) $TotFilledAmt,
                                'TotVerifiedClaims' => (string) $TotVerifiedClaims,
                                'TotDeniedClaims' => (string) $TotDeniedClaims,
                                'TotUnactionedClaims' => (string) $TotUnactionedClaims,
                            ],
                            'TotalVerify_Claim_with_Amount' => [
                                'TotVerifyAmt' => (string) $TotVerifyAmt,
                            ],
                            'submit_btn' => $submit_btn,
                            'submit_note' => $st['reason'],
                            'ComId' => (string) $ComId,
                        ];
                    }
                }
            }

            if (!empty($Open_list)) {
                return response()->json([
                    'Code' => '300',
                    'msg' => 'Data Available!',
                    'Open_list' => $Open_list,
                    'month_list' => $month_list,
                ]);
            }

            // If no data with amounts, check previous month for 'Open' status
            $prevMonth = $currentMonth - 1;
            if ($prevMonth <= 0) $prevMonth = 12;

            $sqlPrev = "SELECT * FROM y{$YearId}_monthexpensefinal WHERE EmployeeID='{$empid}' AND Month='{$prevMonth}' AND YearId={$YearId} AND Status='Open'";
            $prevResult = DB::connection($expenseConn)->select($sqlPrev);

            if (!empty($prevResult)) {
                return response()->json([
                    'Code' => '300',
                    'msg' => 'Previous Month Open Claims Found!',
                    'OpenMonth' => 'Y',
                    'month_list' => $month_list,
                ]);
            }

            return response()->json(['Code' => '404', 'OpenMonth' => 'N', 'msg' => 'No Claims with Filled Amount Found!', 'month_list' => $month_list]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleOpenlist: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get closed months claim list for employee.
     * Replicates: common.php?value=Closelist
     */
    public function handleCloselist(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($YearId) || empty($ComId)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            $sql = "SELECT * FROM y{$YearId}_monthexpensefinal WHERE EmployeeID='{$empid}' and YearId={$YearId} and Status='Closed' and Total_Claim>0 and Fin_PayBy = 0 and (Fin_PayDate IS NULL OR Fin_PayDate = '0000-00-00') group by Month order by Month asc";
            $closeRows = DB::connection($expenseConn)->select($sql);

            if (empty($closeRows)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $Close_list = [];
            foreach ($closeRows as $row) {
                $rowArr = (array) $row;
                // Sanitize: convert nulls to empty string, fix 0000-00-00 dates
                foreach ($rowArr as $key => $value) {
                    if (is_null($value)) {
                        // D-09: zero dates are NULL now; the app still gets 1970-01-01 ("no date") for date columns
                        $rowArr[$key] = stripos((string) $key, 'date') !== false ? '1970-01-01' : '';
                    } else {
                        $strVal = (string) $value;
                        if ($strVal === '0000-00-00') {
                            $rowArr[$key] = '1970-01-01';
                        } elseif ($strVal === '0000-00-00 00:00:00') {
                            $rowArr[$key] = '1970-01-01 00:00:00';
                        } else {
                            $rowArr[$key] = $strVal;
                        }
                    }
                }
                $Close_list[] = $rowArr;
            }

            // Get closed month list with Month_Name and Month_Number
            $Closed_months = [];
            foreach ($closeRows as $row) {
                $rowArr = (array) $row;
                $monthNum = $rowArr['Month'] ?? '';
                $monthName = date('F', mktime(0, 0, 0, (int) $monthNum, 1));
                $Closed_months[] = [
                    'Month_Name' => $monthName,
                    'Month_Number' => $monthNum,
                ];
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'Close_list' => $Close_list,
                'Closed_months' => $Closed_months,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleCloselist: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get closed months claim list (all months - no payment filter).
     * Replicates: common.php?value=Closelist_All_Month
     */
    public function handleCloselistAllMonth(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($YearId) || empty($ComId)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            $sql = "SELECT * FROM y{$YearId}_monthexpensefinal WHERE EmployeeID='{$empid}' and YearId={$YearId} and Status='Closed' and Total_Claim>0 group by Month order by Month asc";
            $closeRows = DB::connection($expenseConn)->select($sql);

            if (empty($closeRows)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $Close_list = [];
            foreach ($closeRows as $row) {
                $Close_list[] = (array) $row;
            }

            $Closed_months = [];
            foreach ($closeRows as $row) {
                $rowArr = (array) $row;
                $monthNum = $rowArr['Month'] ?? '';
                $monthName = date('F', mktime(0, 0, 0, (int) $monthNum, 1));
                $Closed_months[] = [
                    'Month_Name' => $monthName,
                    'Month_Number' => $monthNum,
                ];
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'Close_list' => $Close_list,
                'Closed_months' => $Closed_months,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleCloselistAllMonth: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get closed claims for a specific month.
     * Replicates: common.php?value=Closelist_Month
     */
    public function handleCloselistMonth(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $Month = (int) $request->input('Month');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($YearId) || empty($ComId) || empty($Month)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            $sql = "SELECT * FROM y{$YearId}_monthexpensefinal WHERE EmployeeID='{$empid}' and YearId={$YearId} and Status='Closed' and Total_Claim>0 and Fin_PayBy = 0 and (Fin_PayDate IS NULL OR Fin_PayDate = '0000-00-00') and Month='{$Month}' group by Month order by Month asc";
            $closeRows = DB::connection($expenseConn)->select($sql);

            if (empty($closeRows)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $Close_list = [];
            foreach ($closeRows as $row) {
                $Close_list[] = (array) $row;
            }

            $Closed_months = [];
            foreach ($closeRows as $row) {
                $rowArr = (array) $row;
                $monthNum = $rowArr['Month'] ?? '';
                $monthName = date('F', mktime(0, 0, 0, (int) $monthNum, 1));
                $Closed_months[] = [
                    'Month_Name' => $monthName,
                    'Month_Number' => $monthNum,
                ];
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'Close_list' => $Close_list,
                'Closed_months' => $Closed_months,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleCloselistMonth: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get draft claims list for employee.
     * Replicates: common.php?value=Draftlist
     */
    public function handleDraftlist(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($YearId) || empty($ComId)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            $sql = "SELECT ec.ExpId, ec.ClaimId, ct.ClaimName, ec.CrDate, ec.BillDate, ec.ClaimYearId, ec.CrBy, ec.TotKm, ec.FilledTAmt as FilledAmt, ec.Remark, ec.DateEntryRemark, ec.odomtr_opening, ec.opening_filepath, ec.odomtr_closing, ec.closing_filepath, ec.offline_opening_filepath, ec.offline_clossing_filepath, ec.Img_1, ec.Img_2, ec.Img_3, ec.Img_4 as pdf_1, ec.Img_5 as pdf_2, ec.Img1_offline, ec.Img2_offline, ec.Img3_offline, ec.Pdf1_offline, ec.Pdf2_offline, ec.offline_exp_id, er.Remark AS remark, er.`by` AS sent_by, ec.Backdate_Odometer_Status, ec.App_Odometer, ec.ClaimMonth, er.CrDateTime AS remark_date FROM y{$YearId}_expenseclaims ec LEFT JOIN y{$YearId}_expenseremark er ON ec.ExpId = er.ExpId LEFT JOIN claimtype ct ON ct.ClaimId = ec.ClaimId WHERE ec.CrBy = '{$empid}' AND ec.ClaimStatus != 'Deactivate' AND ((ec.ClaimStatus IN ('Submitted','Draft') AND ec.AttachTo = 0) OR (ec.ClaimStatus IN ('Submitted','Draft') AND (ec.Backdate_Odometer_Status != 'A' OR (ec.ClaimId = 7 and ec.v_verify = 'N')))) ORDER BY ec.ExpId DESC";
            $draftRows = DB::connection($expenseConn)->select($sql);

            if (empty($draftRows)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            // Group results by ExpId (LEFT JOIN with remarks can duplicate rows)
            $grouped = [];
            foreach ($draftRows as $row) {
                $rowArr = (array) $row;
                $expId = $rowArr['ExpId'];

                if (!isset($grouped[$expId])) {
                    $grouped[$expId] = $rowArr;
                    $grouped[$expId]['remarks'] = [];

                    // Add Backdate_Odometer_Msg based on status (legacy behavior)
                    $bdStatus = $rowArr['Backdate_Odometer_Status'] ?? '';
                    if ($bdStatus === 'P') {
                        $grouped[$expId]['Backdate_Odometer_Msg'] = 'GM Approval Pending';
                    } elseif ($bdStatus === 'R') {
                        $grouped[$expId]['Backdate_Odometer_Msg'] = 'Rejected by GM';
                    } elseif ($bdStatus === 'A') {
                        $grouped[$expId]['Backdate_Odometer_Msg'] = 'Approved by GM';
                    } else {
                        $grouped[$expId]['Backdate_Odometer_Msg'] = '';
                    }

                    // Add Access_Running_KM fields (defaults, will be populated for ClaimId=7)
                    $grouped[$expId]['Access_Running_KM_Status'] = '';
                    $grouped[$expId]['Access_Running_KM_Msg'] = '';

                    // Resolve ALL media file URLs to full S3 URLs (Flutter uses them directly as NetworkImage URLs)
                    $userId = $rowArr['CrBy'] ?? $empid;
                    $grouped[$expId]['opening_filepath'] = $this->resolveFileUrl($rowArr['opening_filepath'] ?? '', (int) $YearId, $userId);
                    $grouped[$expId]['closing_filepath'] = $this->resolveFileUrl($rowArr['closing_filepath'] ?? '', (int) $YearId, $userId);
                    $grouped[$expId]['Img_1'] = $this->resolveFileUrl($rowArr['Img_1'] ?? '', (int) $YearId, $userId);
                    $grouped[$expId]['Img_2'] = $this->resolveFileUrl($rowArr['Img_2'] ?? '', (int) $YearId, $userId);
                    $grouped[$expId]['Img_3'] = $this->resolveFileUrl($rowArr['Img_3'] ?? '', (int) $YearId, $userId);
                    $grouped[$expId]['pdf_1'] = $this->resolveFileUrl($rowArr['pdf_1'] ?? '', (int) $YearId, $userId);
                    $grouped[$expId]['pdf_2'] = $this->resolveFileUrl($rowArr['pdf_2'] ?? '', (int) $YearId, $userId);
                }

                // Add remark if present
                if (!empty($rowArr['remark']) || !empty($rowArr['sent_by'])) {
                    $grouped[$expId]['remarks'][] = [
                        'remark' => $rowArr['remark'] ?? '',
                        'sent_by' => $rowArr['sent_by'] ?? '',
                        'remark_date' => $rowArr['remark_date'] ?? '',
                    ];
                }
            }

            // For each claim with ClaimId=7, check Access Running KM settings
            $Draft_list = [];
            foreach ($grouped as $expId => $claim) {
                if ($claim['ClaimId'] == 7) {
                    try {
                        $sqlOdo = "SELECT * FROM odo_approval_rules WHERE status = 'A' LIMIT 1";
                        $odoResult = DB::connection($expenseConn)->select($sqlOdo);
                        if (!empty($odoResult)) {
                            $claim['odo_approval_rules'] = (array) $odoResult[0];
                        }
                    } catch (\Exception $e) {
                        // Table may not exist - skip
                    }
                }

                // Remove duplicate remark/sent_by/remark_date from top-level (keep in remarks array)
                unset($claim['remark'], $claim['sent_by'], $claim['remark_date']);

                // Stringify all values to match legacy mysqli_fetch_assoc behavior
                // Flutter model uses "as String" casts so all values MUST be strings
                $stringified = [];
                foreach ($claim as $key => $value) {
                    if ($key === 'remarks' || $key === 'odo_approval_rules') {
                        $stringified[$key] = $value; // Keep arrays as-is
                    } elseif (is_null($value)) {
                        $stringified[$key] = '';
                    } else {
                        $stringified[$key] = (string) $value;
                    }
                }

                // Ensure required fields exist with defaults (Flutter crashes on missing keys)
                $stringified['Backdate_Odometer_Msg'] = $stringified['Backdate_Odometer_Msg'] ?? '';
                $stringified['Access_Running_KM_Status'] = $stringified['Access_Running_KM_Status'] ?? '';
                $stringified['Access_Running_KM_Msg'] = $stringified['Access_Running_KM_Msg'] ?? '';

                $Draft_list[] = $stringified;
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'Draft_list' => $Draft_list,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleDraftlist: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get open claims day-wise details for a specific month.
     * Replicates: common.php?value=Openlist_Day
     */
    public function handleOpenlistDay(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $Month = (int) $request->input('Month');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($Month) || empty($YearId) || empty($ComId)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            $sql = "SELECT e.ExpId, e.ClaimYearId, e.BillDate, e.FilledTAmt, e.ClaimStatus, e.CrBy, e.Remark, e.DateEntryRemark, e.FilledOkay, e.ClaimAtStep, ct.ClaimName, ct.cgId, e.ClaimMonth, e.opening_filepath, e.closing_filepath, Img_1, Img_2, Img_3, Img_4 as pdf_1, Img_5 as pdf_2, e.TotKm, e.v_verify, e.ClaimId FROM y{$YearId}_expenseclaims e LEFT JOIN claimtype ct ON e.ClaimId = ct.ClaimId WHERE e.ClaimMonth = '{$Month}' AND e.ClaimYearId = '{$YearId}' AND e.CrBy = {$empid} AND e.ClaimStatus NOT IN ('Deactivate', 'Draft', 'Submitted') AND e.FilledBy > 0 AND (e.Backdate_Odometer_Status IS NULL OR e.Backdate_Odometer_Status = '' OR (e.Backdate_Odometer_Status = 'A' AND e.v_verify = 'Y')) ORDER BY BillDate ASC";
            $dayRows = DB::connection($expenseConn)->select($sql);

            if (empty($dayRows)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $DayWiseDetails = [];
            foreach ($dayRows as $row) {
                $rowArr = (array) $row;
                // Resolve file URLs to S3
                $userId = $rowArr['CrBy'] ?? $empid;
                $rowArr['opening_filepath'] = $this->resolveFileUrl($rowArr['opening_filepath'] ?? '', (int) $YearId, $userId);
                $rowArr['closing_filepath'] = $this->resolveFileUrl($rowArr['closing_filepath'] ?? '', (int) $YearId, $userId);
                $rowArr['Img_1'] = $this->resolveFileUrl($rowArr['Img_1'] ?? '', (int) $YearId, $userId);
                $rowArr['Img_2'] = $this->resolveFileUrl($rowArr['Img_2'] ?? '', (int) $YearId, $userId);
                $rowArr['Img_3'] = $this->resolveFileUrl($rowArr['Img_3'] ?? '', (int) $YearId, $userId);
                $rowArr['pdf_1'] = $this->resolveFileUrl($rowArr['pdf_1'] ?? '', (int) $YearId, $userId);
                $rowArr['pdf_2'] = $this->resolveFileUrl($rowArr['pdf_2'] ?? '', (int) $YearId, $userId);
                $DayWiseDetails[] = $rowArr;
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'DayWiseDetails' => $DayWiseDetails,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleOpenlistDay: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get closed claims day-wise details for a specific month.
     * Replicates: common.php?value=Closelist_Day
     */
    public function handleCloselistDay(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $Month = (int) $request->input('Month');
        $YearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $ComId = (int) $request->input('ComId');
        $tokenid = $request->input('tokenid');

        if (empty($empid) || empty($Month) || empty($YearId) || empty($ComId)) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }

        if (!$this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $expenseConn = $this->getExpenseConnection((int) $ComId);

        try {
            $sql = "SELECT e.ExpId, e.ClaimYearId, e.CrBy, e.ClaimId, e.BillDate, e.FilledTAmt, e.TotKm, e.VerifyTAmt, VerifyBy, VerifyDate, VerifyTRemark, e.ApprTAmt, ApprBy, ApprDate, ApprTRemark, e.FinancedTAmt, FinancedBy, FinancedDate, FinancedTRemark, e.ClaimStatus, e.Remark, e.DateEntryRemark, e.FilledOkay, e.ClaimAtStep, ct.ClaimName, ct.cgId, e.ClaimMonth, e.opening_filepath, e.closing_filepath, Img_1, Img_2, Img_3, Img_4 as pdf_1, Img_5 as pdf_2 FROM y{$YearId}_expenseclaims e left join claimtype ct on e.ClaimId=ct.ClaimId where e.ClaimMonth='{$Month}' and e.ClaimYearId='{$YearId}' and e.CrBy={$empid} and e.ClaimStatus!='Deactivate' and e.ClaimStatus!='Draft' and e.ClaimStatus!='Submitted' and e.FilledBy>0 order by BillDate ASC";
            $dayRows = DB::connection($expenseConn)->select($sql);

            if (empty($dayRows)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $DayWiseDetails = [];
            foreach ($dayRows as $row) {
                $rowArr = (array) $row;
                // Resolve file URLs to S3
                $userId = $rowArr['CrBy'] ?? $empid;
                $rowArr['opening_filepath'] = $this->resolveFileUrl($rowArr['opening_filepath'] ?? '', (int) $YearId, $userId);
                $rowArr['closing_filepath'] = $this->resolveFileUrl($rowArr['closing_filepath'] ?? '', (int) $YearId, $userId);
                $rowArr['Img_1'] = $this->resolveFileUrl($rowArr['Img_1'] ?? '', (int) $YearId, $userId);
                $rowArr['Img_2'] = $this->resolveFileUrl($rowArr['Img_2'] ?? '', (int) $YearId, $userId);
                $rowArr['Img_3'] = $this->resolveFileUrl($rowArr['Img_3'] ?? '', (int) $YearId, $userId);
                $rowArr['pdf_1'] = $this->resolveFileUrl($rowArr['pdf_1'] ?? '', (int) $YearId, $userId);
                $rowArr['pdf_2'] = $this->resolveFileUrl($rowArr['pdf_2'] ?? '', (int) $YearId, $userId);
                $DayWiseDetails[] = $rowArr;
            }

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Available!',
                'DayWiseDetails' => $DayWiseDetails,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimListController@handleCloselistDay: ' . $e->getMessage());
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }
}
