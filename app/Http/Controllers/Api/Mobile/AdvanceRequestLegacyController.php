<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles the legacy advance_request.php endpoints for the mobile app.
 * Uses raw SQL queries via DB::select() and returns responses with all values as strings
 * (mimicking mysqli_fetch_assoc behavior).
 */
class AdvanceRequestLegacyController extends BaseMobileController
{
    /**
     * Main entry point that routes to internal handlers based on 'value' parameter.
     */
    public function handleLegacy(Request $request): JsonResponse
    {
        $value = $request->input('value', '');

        return match ($value) {
            'show_request_tab' => $this->showRequestTab($request),
            'get_advance_request' => $this->getAdvanceRequest($request),
            'show_approver_tab' => $this->showApproverTab($request),
            'advance_approver_levels' => $this->advanceApproverLevels($request),
            'get_advance_request_by_id' => $this->getAdvanceRequestById($request),
            'action_advance_request' => $this->actionAdvanceRequest($request),
            'get_month_list' => $this->getMonthList($request),
            'save_advance_request' => $this->saveAdvanceRequest($request),
            default => response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Invalid value parameter.',
            ]),
        };
    }

    /**
     * Handle show_request_tab - determines advance access permissions for an employee.
     */
    protected function showRequestTab(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $comId = (int) $request->input('ComId');
        $tokenId = $request->input('tokenid');

        // Validate required params
        if (empty($empId) || empty($yearId) || empty($comId) || empty($tokenId)) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Missing required parameters (empid, YearId, ComId, tokenid).',
            ]);
        }

        $employeeID = (int) $empId;
        $yearId = (int) $yearId;
        $comId = (int) $comId;

        // Setup connections
        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        // Validate token
        $employee = $this->validateToken($request);
        if (!$employee) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Invalid request or token.',
            ]);
        }

        // OLD VERSION KEYS (for backward compatibility)
        $response = [
            'Activity' => 'N',
            'Expense' => 'N',
            'Other' => 'N',
            'AdvanceRequest' => 'N',
            'AdvanceRequestsSubmittedForMe' => 'N',
            'IsApprover' => 'N',
            'IsHOD' => 'N',
        ];

        // NEW VERSION KEYS (for new system)
        $newResponse = [
            'ActivityAdvance' => 'N',
            'ExpenseAdvance' => 'N',
            'OtherAdvance' => 'N',
            'ActivityAdvanceMonths' => [],
            'ExpenseAdvanceMonths' => [],
            'OtherAdvanceMonths' => [],
            'IsTourTravel' => 'N',
            'tour_travel_min_amount' => 0,
            'tour_travel_max_amount' => 0,
            'isAllowExpense' => 'N',
            'TourTravelMonths' => [],
        ];

        // Get employee department, designation, grade, vertical
        $depId = 0;
        $desigId = 0;
        $gradeId = 0;
        $empVertical = 0;

        try {
            $empRows = DB::connection($hrimsConn)->select(
                "SELECT DepartmentId, DesigId, GradeId, EmpVertical FROM hrm_employee_general WHERE EmployeeID = ?",
                [$employeeID]
            );

            if (!empty($empRows)) {
                $empRow = (array) $empRows[0];
                $depId = isset($empRow['DepartmentId']) ? (int) $empRow['DepartmentId'] : 0;
                $desigId = isset($empRow['DesigId']) ? (int) $empRow['DesigId'] : 0;
                $gradeId = isset($empRow['GradeId']) ? (int) $empRow['GradeId'] : 0;
                $empVertical = isset($empRow['EmpVertical']) ? (int) $empRow['EmpVertical'] : 0;
            }
        } catch (\Exception $e) {
            // Default values already set
        }

        // ===================================================
        // OLD VERSION ACCESS CHECKS (Legacy System)
        // ===================================================

        // Check IsApprover
        try {
            $approverRows = DB::connection($expenseConn)->select(
                "SELECT id FROM advance_activity_approvals WHERE approver_id = ? LIMIT 1",
                [$employeeID]
            );
            if (!empty($approverRows)) {
                $response['IsApprover'] = 'Y';
            }
        } catch (\Exception $e) {
            // table may not exist
        }

        // Check legacy mapping
        try {
            $legacyRows = DB::connection($expenseConn)->select(
                "SELECT access_for FROM adv_activity_others_mapping WHERE employee_id = ? AND status = '1' AND access_for = 'Advance_Access'",
                [$employeeID]
            );
            if (!empty($legacyRows)) {
                foreach ($legacyRows as $row) {
                    $rowArr = (array) $row;
                    if (isset($response[$rowArr['access_for']])) {
                        $response[$rowArr['access_for']] = 'Y';
                    }
                }
            }
        } catch (\Exception $e) {
            // table may not exist
        }

        // Check activity names by department
        if ($depId > 0) {
            try {
                $activityRows = DB::connection($expenseConn)->select(
                    "SELECT * FROM adv_activity_names WHERE FIND_IN_SET(?, dept_id)",
                    [$depId]
                );
                if (!empty($activityRows)) {
                    $response['Activity'] = 'Y';
                }
            } catch (\Exception $e) {
                // table may not exist
            }
        }

        // Check old advance_request_masters for AdvanceRequest flag
        if ($depId > 0 && $desigId > 0) {
            try {
                $oldAdvanceRows = DB::connection($expenseConn)->select(
                    "SELECT * FROM advance_request_masters 
                     WHERE department_id = ? 
                     AND is_active = 1 
                     AND effective_date <= CURDATE()
                     AND (designation_ids LIKE ? OR designation_ids LIKE ?)",
                    [$depId, '%"' . $desigId . '"%', '%' . $desigId . '%']
                );
                if (!empty($oldAdvanceRows)) {
                    $response['AdvanceRequest'] = 'Y';
                }
            } catch (\Exception $e) {
                // table may not exist
            }
        }

        // ===================================================
        // NEW VERSION ACCESS CHECKS (New System)
        // ===================================================

        if ($depId > 0) {
            // Check Activity Advance Access (Department + Vertical + Designation based)
            if ($desigId > 0 && $empVertical > 0) {
                try {
                    $activityRows = DB::connection($expenseConn)->select(
                        "SELECT arm.*, arm.vertical_designation 
                         FROM advance_request_masters arm 
                         INNER JOIN advance_request_types art ON arm.advance_type_id = art.id 
                         WHERE art.type_code = 'Act' 
                         AND arm.department_id = ? 
                         AND arm.is_active = 1 
                         AND arm.effective_from <= CURDATE() 
                         AND (arm.effective_to IS NULL OR arm.effective_to >= CURDATE())",
                        [$depId]
                    );

                    if (!empty($activityRows)) {
                        $hasAccess = false;

                        foreach ($activityRows as $actRow) {
                            $actRowArr = (array) $actRow;
                            $verticalDesignation = $actRowArr['vertical_designation'] ?? null;

                            if (!empty($verticalDesignation) && $verticalDesignation != '[]' && $verticalDesignation != 'null') {
                                $verticalDesignationData = json_decode($verticalDesignation, true);

                                if (is_array($verticalDesignationData) && isset($verticalDesignationData[$empVertical])) {
                                    $designationsInVertical = $verticalDesignationData[$empVertical];

                                    if (is_array($designationsInVertical)) {
                                        foreach ($designationsInVertical as $desigConfig) {
                                            if (is_array($desigConfig) && isset($desigConfig['id'])) {
                                                if ((int) $desigConfig['id'] == $desigId) {
                                                    $hasAccess = true;
                                                    break 2;
                                                }
                                            } elseif (is_numeric($desigConfig)) {
                                                if ((int) $desigConfig == $desigId) {
                                                    $hasAccess = true;
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if ($hasAccess) {
                            $newResponse['ActivityAdvance'] = 'Y';

                            // Generate ActivityAdvanceMonths (current month + next 3 months)
                            $activityMonths = [];
                            $currentYear = (int) date('Y');
                            $currentMonth = (int) date('n');

                            for ($i = 0; $i < 4; $i++) {
                                $targetMonth = $currentMonth + $i;
                                $targetYear = $currentYear;

                                if ($targetMonth > 12) {
                                    $targetYear += (int) floor(($targetMonth - 1) / 12);
                                    $targetMonth = (($targetMonth - 1) % 12) + 1;
                                }

                                $monthDate = new \DateTime("$targetYear-$targetMonth-01");
                                $activityMonths[] = [
                                    'month_year' => $monthDate->format('Y-m'),
                                    'month_number' => (int) $monthDate->format('n'),
                                    'month_name' => $monthDate->format('F'),
                                    'year' => (int) $monthDate->format('Y'),
                                ];
                            }

                            $newResponse['ActivityAdvanceMonths'] = $activityMonths;
                        }
                    }
                } catch (\Exception $e) {
                    // table may not exist
                }
            }

            // Check Expense Advance Access (Department based + Grade restriction check)
            try {
                $expenseRows = DB::connection($expenseConn)->select(
                    "SELECT 
                        arm.*, 
                        arl.min_amount AS tour_travel_min_amount,
                        arl.max_amount AS tour_travel_max_amount
                    FROM advance_request_masters arm 
                    INNER JOIN advance_request_types art 
                        ON arm.advance_type_id = art.id 
                    LEFT JOIN advance_request_limits arl 
                        ON arl.advance_type_id = arm.advance_type_id 
                        AND arl.department_id = arm.department_id
                    WHERE art.type_code = 'Exp' 
                    AND arm.department_id = ? 
                    AND arm.is_active = 1 
                    AND arm.effective_from <= CURDATE() 
                    AND (arm.effective_to IS NULL OR arm.effective_to >= CURDATE())
                    AND JSON_CONTAINS(arm.vertical_ids, ?)",
                    [$depId, '"' . $empVertical . '"']
                );

                if (!empty($expenseRows)) {
                    $expenseRow = (array) $expenseRows[0];
                    $gradeIds = $expenseRow['grade_ids'] ?? null;
                    $isTourTravel = $expenseRow['is_tour_travel'] ?? 0;
                    $newResponse['IsTourTravel'] = ($isTourTravel == 1 || $isTourTravel === true) ? 'Y' : 'N';
                    $isAllowExpense = $expenseRow['allow_expense'] ?? 0;
                    $newResponse['isAllowExpense'] = ($isAllowExpense == 1 || $isAllowExpense === true) ? 'Y' : 'N';
                    $newResponse['tour_travel_min_amount'] = $expenseRow['tour_travel_min_amount'] ?? 0;
                    $newResponse['tour_travel_max_amount'] = $expenseRow['tour_travel_max_amount'] ?? 0;

                    // Generate TourTravelMonths
                    $tourTravelMonths = [];
                    if ($isTourTravel == 1 || $isTourTravel === true) {
                        $currentYear = (int) date('Y');
                        $currentMonth = (int) date('n');

                        for ($i = 0; $i < 4; $i++) {
                            $targetMonth = $currentMonth + $i;
                            $targetYear = $currentYear;

                            if ($targetMonth > 12) {
                                $targetYear += (int) floor(($targetMonth - 1) / 12);
                                $targetMonth = (($targetMonth - 1) % 12) + 1;
                            }

                            $monthDate = new \DateTime("$targetYear-$targetMonth-01");
                            $tourTravelMonths[] = [
                                'month_year' => $monthDate->format('Y-m'),
                                'month_number' => (int) $monthDate->format('n'),
                                'month_name' => $monthDate->format('F'),
                                'year' => (int) $monthDate->format('Y'),
                            ];
                        }
                    }
                    $newResponse['TourTravelMonths'] = $tourTravelMonths;

                    // Check if employee's grade is in the restricted list
                    $isGradeRestricted = false;

                    if ($gradeId > 0 && !empty($gradeIds) && $gradeIds != '[]' && $gradeIds != 'null') {
                        try {
                            $gradeCheckRows = DB::connection($expenseConn)->select(
                                "SELECT JSON_CONTAINS(?, ?) as is_restricted",
                                [$gradeIds, '"' . $gradeId . '"']
                            );
                            if (!empty($gradeCheckRows)) {
                                $gradeCheckRow = (array) $gradeCheckRows[0];
                                $isGradeRestricted = ($gradeCheckRow['is_restricted'] == 1);
                            }
                        } catch (\Exception $e) {
                            // Fallback - use string matching
                            if (strpos($gradeIds, '"' . $gradeId . '"') !== false || strpos($gradeIds, (string) $gradeId) !== false) {
                                $isGradeRestricted = true;
                            }
                        }
                    }

                    // Grant access ONLY if grade is NOT restricted
                    if (!$isGradeRestricted) {
                        $newResponse['ExpenseAdvance'] = 'Y';

                        // Get closed months for Expense Advance
                        try {
                            $expenseMonthRows = DB::connection($expenseConn)->select(
                                "SELECT `Month`, `Claim_Amount` 
                                 FROM `y{$yearId}_monthexpensefinal`
                                 WHERE `Status` = 'Closed'
                                 AND (`Fin_PayDate` IS NULL OR `Fin_PayDate` = '0000-00-00')
                                 AND `EmployeeID` = ? 
                                 ORDER BY `Month`",
                                [$employeeID]
                            );

                            $expenseMonths = [];

                            if (!empty($expenseMonthRows)) {
                                foreach ($expenseMonthRows as $monthRowObj) {
                                    $monthRow = (array) $monthRowObj;
                                    $currentMonthNum = (int) $monthRow['Month'];
                                    $claimAmount = $monthRow['Claim_Amount'];
                                    $claim80Percent = round($claimAmount * 0.8, 2);

                                    // Calculate previous month
                                    $previousMonth = $currentMonthNum - 1;
                                    if ($previousMonth == 0) {
                                        $previousMonth = 12;
                                    }

                                    $allowAdvance = false;

                                    // Check if previous month is closed
                                    try {
                                        $prevMonthRows = DB::connection($expenseConn)->select(
                                            "SELECT `RecevingDate`
                                             FROM `y{$yearId}_monthexpensefinal`
                                             WHERE `EmployeeID` = ?
                                             AND `Month` = ?
                                             AND `Status` = 'Closed'
                                             LIMIT 1",
                                            [$employeeID, $previousMonth]
                                        );

                                        // Allow advance if previous month record exists (matching legacy >= 0 behavior)
                                        if (count($prevMonthRows) >= 0) {
                                            $allowAdvance = true;
                                        }
                                    } catch (\Exception $e) {
                                        $allowAdvance = true;
                                    }

                                    if ($allowAdvance) {
                                        $dateObj = \DateTime::createFromFormat('!m', (string) $currentMonthNum);
                                        if ($dateObj) {
                                            $expenseMonths[] = [
                                                'month_number' => $currentMonthNum,
                                                'month_name' => $dateObj->format('F'),
                                                'year' => (int) date('Y'),
                                                'claim_amount' => $claim80Percent,
                                                'actual_claim_amount' => $claimAmount,
                                            ];
                                        }
                                    }
                                }
                            }

                            $newResponse['ExpenseAdvanceMonths'] = $expenseMonths;
                        } catch (\Exception $e) {
                            $newResponse['ExpenseAdvanceMonths'] = [];
                        }
                    }
                }
            } catch (\Exception $e) {
                // table may not exist
            }
        }

        // Check Other/Special Advance Access (Employee based)
        try {
            $otherRows = DB::connection($expenseConn)->select(
                "SELECT arm.* 
                 FROM advance_request_masters arm 
                 INNER JOIN advance_request_types art ON arm.advance_type_id = art.id 
                 WHERE art.type_code = 'Oth' 
                 AND arm.is_active = 1 
                 AND arm.effective_from <= CURDATE() 
                 AND (arm.effective_to IS NULL OR arm.effective_to >= CURDATE())
                 AND JSON_CONTAINS(arm.employee_ids, ?)",
                ['"' . $employeeID . '"']
            );

            // Fallback if JSON_CONTAINS not supported
            if (empty($otherRows)) {
                $otherRows = DB::connection($expenseConn)->select(
                    "SELECT arm.* 
                     FROM advance_request_masters arm 
                     INNER JOIN advance_request_types art ON arm.advance_type_id = art.id 
                     WHERE art.type_code = 'Oth' 
                     AND arm.is_active = 1 
                     AND arm.effective_from <= CURDATE() 
                     AND (arm.effective_to IS NULL OR arm.effective_to >= CURDATE())
                     AND (arm.employee_ids LIKE ? OR arm.employee_ids LIKE ?)",
                    ['%"' . $employeeID . '"%', '%' . $employeeID . '%']
                );
            }
        } catch (\Exception $e) {
            // Try fallback with LIKE
            try {
                $otherRows = DB::connection($expenseConn)->select(
                    "SELECT arm.* 
                     FROM advance_request_masters arm 
                     INNER JOIN advance_request_types art ON arm.advance_type_id = art.id 
                     WHERE art.type_code = 'Oth' 
                     AND arm.is_active = 1 
                     AND arm.effective_from <= CURDATE() 
                     AND (arm.effective_to IS NULL OR arm.effective_to >= CURDATE())
                     AND (arm.employee_ids LIKE ? OR arm.employee_ids LIKE ?)",
                    ['%"' . $employeeID . '"%', '%' . $employeeID . '%']
                );
            } catch (\Exception $e2) {
                $otherRows = [];
            }
        }

        if (!empty($otherRows)) {
            $newResponse['OtherAdvance'] = 'Y';

            // Get current month and next 3 months for Other Advance
            $otherMonths = [];
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('n');

            for ($i = 0; $i < 4; $i++) {
                $targetMonth = $currentMonth + $i;
                $targetYear = $currentYear;

                if ($targetMonth > 12) {
                    $targetYear += (int) floor(($targetMonth - 1) / 12);
                    $targetMonth = (($targetMonth - 1) % 12) + 1;
                }

                $monthDate = new \DateTime("$targetYear-$targetMonth-01");
                $otherMonths[] = [
                    'month_year' => $monthDate->format('Y-m'),
                    'month_number' => (int) $monthDate->format('n'),
                    'month_name' => $monthDate->format('F'),
                    'year' => (int) $monthDate->format('Y'),
                ];
            }
            $newResponse['OtherAdvanceMonths'] = $otherMonths;
        }

        // Set AdvanceRequest = 'Y' if any new advance type is 'Y'
        if ($newResponse['ActivityAdvance'] == 'Y' || $newResponse['ExpenseAdvance'] == 'Y' || $newResponse['OtherAdvance'] == 'Y') {
            $response['AdvanceRequest'] = 'Y';
        }

        // ===================================================
        // SUBMITTED FOR ME CHECKS
        // ===================================================
        try {
            $submittedForMeRows = DB::connection($expenseConn)->select(
                "SELECT COUNT(DISTINCT are.advance_request_id) as request_count 
                 FROM advance_request_employees are
                 INNER JOIN advance_requests ar ON are.advance_request_id = ar.id
                 WHERE are.employee_id = ? 
                 AND ar.req_app_submit_id != ?
                 AND are.deleted_at IS NULL
                 AND ar.deleted_at IS NULL",
                [$employeeID, $employeeID]
            );

            if (!empty($submittedForMeRows)) {
                $submittedForMeRow = (array) $submittedForMeRows[0];
                if ($submittedForMeRow['request_count'] > 0) {
                    $response['AdvanceRequestsSubmittedForMe'] = 'Y';
                }
            }
        } catch (\Exception $e) {
            // table may not exist
        }

        // ===================================================
        // Check IsHOD
        // ===================================================
        $hasTeamAdvances = false;

        try {
            $teamMembersRows = DB::connection($hrimsConn)->select(
                "SELECT EmployeeID FROM hrm_employee_general WHERE RepEmployeeID = ?",
                [$employeeID]
            );

            if (!empty($teamMembersRows)) {
                $teamMemberIds = [];
                foreach ($teamMembersRows as $row) {
                    $teamMemberIds[] = ((array) $row)['EmployeeID'];
                }

                if (!empty($teamMemberIds)) {
                    $ids = implode(',', $teamMemberIds);
                    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));

                    try {
                        $advanceRows = DB::connection($expenseConn)->select(
                            "SELECT COUNT(DISTINCT are.advance_request_id) AS total_requests
                             FROM advance_request_employees are
                             INNER JOIN advance_requests ar 
                                 ON ar.id = are.advance_request_id
                             WHERE are.employee_id IN ($placeholders)
                             AND are.deleted_at IS NULL
                             AND ar.deleted_at IS NULL",
                            $teamMemberIds
                        );

                        if (!empty($advanceRows)) {
                            $advRow = (array) $advanceRows[0];
                            if ($advRow['total_requests'] > 0) {
                                $hasTeamAdvances = true;
                            }
                        }
                    } catch (\Exception $e) {
                        // table may not exist
                    }
                }
            }
        } catch (\Exception $e) {
            // table may not exist
        }

        $response['IsHOD'] = $hasTeamAdvances ? 'Y' : 'N';

        // ===================================================
        // Check DateOfResignation
        // ===================================================
        try {
            $resignRows = DB::connection($hrimsConn)->select(
                "SELECT hrm_employee.DateOfResignation 
                 FROM hrm_employee 
                 LEFT JOIN hrm_employee_general 
                 ON hrm_employee.EmployeeID = hrm_employee_general.EmployeeID 
                 WHERE hrm_employee.EmployeeID = ?",
                [$employeeID]
            );

            if (!empty($resignRows)) {
                $resignRow = (array) $resignRows[0];
                $dateOfResignation = $resignRow['DateOfResignation'] ?? null;

                if (!empty($dateOfResignation) && \App\Support\ZeroDate::has($dateOfResignation)) {
                    return response()->json([
                        'code' => 200,
                        'status' => 'success',
                        'message' => 'Resigned employee can not take advance',
                        'data' => [],
                    ]);
                }
            }
        } catch (\Exception $e) {
            // continue with merge
        }

        // Merge responses and return
        $finalResponse = array_merge($response, $newResponse);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Advance access data',
            'data' => $finalResponse,
        ]);
    }

    /**
     * Handle get_advance_request - retrieves advance requests list for an employee.
     */
    protected function getAdvanceRequest(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $comId = (int) $request->input('ComId');
        $tokenId = $request->input('tokenid');

        // Validate required params
        if (empty($empId) || empty($yearId) || empty($comId) || empty($tokenId)) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Missing required parameters (empid, YearId, ComId, tokenid).',
            ]);
        }

        $employeeId = (int) $empId;
        $yearId = (int) $yearId;
        $comId = (int) $comId;

        // Validate token
        $employee = $this->validateToken($request);
        if (!$employee) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Invalid request or token.',
            ]);
        }

        // Setup connection
        $expenseConn = $this->getExpenseConnection($comId);

        try {
            $rows = DB::connection($expenseConn)->select(
                "SELECT ar.request_id, ar.request_code, ar.created_date as requested_date, 
                        ar.request_type, 
                        ELT(ar.request_month, 'January', 'February', 'March', 'April', 'May', 'June', 
                            'July', 'August', 'September', 'October', 'November', 'December') AS request_month_name, 
                        ar.requested_amount, ar.request_purpose, ar.request_status, 
                        ct.ClaimName AS activity_name, at.type_name, 
                        ar.activity_crops_id, ar.activity_variety_id, 
                        ar.activity_type_id, an.activity_name as activity_name_id 
                 FROM y{$yearId}_advance_requests AS ar 
                 LEFT JOIN adv_activity_names AS an ON ar.activity_name_id = an.id 
                 LEFT JOIN claimtype AS ct ON ct.ClaimId = an.activity_name 
                 LEFT JOIN adv_activity_types AS at ON ar.activity_type_id = at.id 
                 WHERE ar.emp_id = ? 
                 ORDER BY ar.request_id DESC",
                [$employeeId]
            );

            if (!empty($rows)) {
                $requests = $this->stringifyRows($rows);

                return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Advance requests retrieved successfully.',
                    'data' => $requests,
                ]);
            } else {
                return response()->json([
                    'code' => 200,
                    'status' => 'error',
                    'message' => 'No advance requests found for this employee.',
                    'data' => [],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Error retrieving advance requests: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle show_approver_tab - checks if employee is an approver on any advance requests.
     */
    protected function showApproverTab(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $comId = (int) $request->input('ComId');
        $tokenId = $request->input('tokenid');

        if (empty($empId) || empty($yearId) || empty($comId)) {
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'data' => 'NO',
                'Data' => 'NO',
                'msg' => 'Parameter Missing',
            ]);
        }

        $employeeId = (int) $empId;
        $yearId = (int) $yearId;
        $comId = (int) $comId;

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        $isApprover = false;

        // 1. Check if employee is in hrm_employee_general as reporting manager
        try {
            $repCheck = DB::connection($hrimsConn)->select(
                "SELECT COUNT(*) as cnt FROM hrm_employee_general WHERE RepEmployeeID = ?",
                [$employeeId]
            );
            if (!empty($repCheck) && $repCheck[0]->cnt > 0) {
                $isApprover = true;
            }
        } catch (\Exception $e) {
            // continue
        }

        // 2. Check advance_activity_approvals
        if (!$isApprover) {
            try {
                $apprCheck = DB::connection($expenseConn)->select(
                    "SELECT COUNT(*) as cnt FROM advance_activity_approvals WHERE approver_id = ? AND deleted_at IS NULL",
                    [$employeeId]
                );
                if (!empty($apprCheck) && $apprCheck[0]->cnt > 0) {
                    $isApprover = true;
                }
            } catch (\Exception $e) {
                // continue
            }
        }

        // 3. Check year table y{YearId}_advance_requests
        if (!$isApprover) {
            try {
                $tableCheck = DB::connection($expenseConn)->select(
                    "SELECT COUNT(*) as cnt FROM y{$yearId}_advance_requests 
                     WHERE (approver1_id = ? OR approver2_id = ? OR approver3_id = ? OR approver4_id = ? OR approver5_id = ?)",
                    [$employeeId, $employeeId, $employeeId, $employeeId, $employeeId]
                );
                if (!empty($tableCheck) && $tableCheck[0]->cnt > 0) {
                    $isApprover = true;
                }
            } catch (\Exception $e) {
                // continue
            }
        }

        $val = $isApprover ? 'YES' : 'NO';
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => $val,
            'Data' => $val,
            'msg' => 'Success',
        ]);
    }

    /**
     * Handle advance_approver_levels - fetches categorized lists (Pending, Approved, Rejected, Reverted).
     */
    protected function advanceApproverLevels(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = ($request->filled('YearId') ? (int) $request->input('YearId') : null);
        $comId = (int) $request->input('ComId');

        if (empty($empId) || empty($yearId) || empty($comId)) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Missing required parameters.',
            ]);
        }

        $employeeId = (int) $empId;
        $yearId = (int) $yearId;
        $comId = (int) $comId;

        $expenseConn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();

        $pending = [];
        $approved = [];
        $rejected = [];
        $reverted = [];

        // Check y{YearId}_advance_requests
        try {
            $yearRows = DB::connection($expenseConn)->select(
                "SELECT ar.*, ct.ClaimName AS activity_name 
                 FROM y{$yearId}_advance_requests ar 
                 LEFT JOIN adv_activity_names an ON ar.activity_name_id = an.id 
                 LEFT JOIN claimtype ct ON ct.ClaimId = an.activity_name 
                 WHERE (ar.approver1_id = ? OR ar.approver2_id = ? OR ar.approver3_id = ? OR ar.approver4_id = ? OR ar.approver5_id = ?) 
                 ORDER BY ar.request_id DESC",
                [$employeeId, $employeeId, $employeeId, $employeeId, $employeeId]
            );

            foreach ($yearRows as $row) {
                $r = (array) $row;
                $reqId = (string) ($r['request_id'] ?? $r['id'] ?? '');

                // Fetch employee name
                $eName = '';
                $eCode = '';
                if (!empty($r['emp_id'])) {
                    $empRes = DB::connection($hrimsConn)->select(
                        "SELECT EmpCode, TRIM(CONCAT(Fname, ' ', IFNULL(Sname, ''), ' ', Lname)) as Ename FROM hrm_employee WHERE EmployeeID = ?",
                        [$r['emp_id']]
                    );
                    if (!empty($empRes)) {
                        $eName = $empRes[0]->Ename ?? '';
                        $eCode = $empRes[0]->EmpCode ?? '';
                    }
                }

                // Determine level
                $myLevel = 1;
                for ($lvl = 1; $lvl <= 5; $lvl++) {
                    if (($r["approver{$lvl}_id"] ?? null) == $employeeId) {
                        $myLevel = $lvl;
                        break;
                    }
                }

                $apprStatus = $r["approver{$myLevel}_status"] ?? 'P';
                $overallStatus = $r['request_status'] ?? 'Pending';
                $reqType = !empty($r['request_type']) ? $r['request_type'] : 'Expense';

                $item = [
                    'request_id' => $reqId,
                    'request_code' => (string) ($r['request_code'] ?? ''),
                    'request_type' => (string) $reqType,
                    'activity_name' => (string) ($r['activity_name'] ?? ''),
                    'requested_amount' => (string) ($r['requested_amount'] ?? '0'),
                    'approved_amount' => (string) ($r["approver{$myLevel}_amount"] ?? $r['approved_amount'] ?? $r['requested_amount'] ?? '0'),
                    'requested_date' => (string) ($r['created_date'] ?? $r['request_date'] ?? ''),
                    'reason' => (string) ($r['request_purpose'] ?? $r['reason'] ?? ''),
                    'request_purpose' => (string) ($r['request_purpose'] ?? $r['reason'] ?? ''),
                    'level' => (string) $myLevel,
                    'employee_name' => $eName,
                    'employee_code' => $eCode,
                    'request_status' => (string) $overallStatus,
                    'status' => (string) $overallStatus,
                ];

                if ($overallStatus == 'R' || $apprStatus == 'R' || strcasecmp($overallStatus, 'Rejected') === 0) {
                    $item['request_status'] = 'Rejected';
                    $rejected[] = $item;
                } elseif ($overallStatus == 'H' || $apprStatus == 'H' || strcasecmp($overallStatus, 'Reverted') === 0) {
                    $item['request_status'] = 'Reverted';
                    $reverted[] = $item;
                } elseif ($apprStatus == 'A' || $overallStatus == 'A' || strcasecmp($overallStatus, 'Approved') === 0) {
                    $item['request_status'] = 'Approved';
                    $approved[] = $item;
                } else {
                    $item['request_status'] = 'Pending';
                    $pending[] = $item;
                }
            }
        } catch (\Exception $e) {
            // fallback
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => [
                'Pending' => $pending,
                'Approved' => $approved,
                'Rejected' => $rejected,
                'Reverted' => $reverted,
            ],
            'msg' => 'Data retrieved successfully',
        ]);
    }

    /**
     * Handle get_advance_request_by_id - details of single request and its approver history.
     */
    protected function getAdvanceRequestById(Request $request): JsonResponse
    {
        $requestId = $request->input('request_id') ?? $request->input('id');
        $yearId = (int) ($request->input('YearId') ?? $request->input('year_id'));
        $comId = $request->input('ComId') ?? $request->input('com_id');

        if (empty($requestId) || empty($yearId) || empty($comId)) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Missing required parameters.',
            ]);
        }

        $expenseConn = $this->getExpenseConnection((int) $comId);
        $hrimsConn = $this->getHrimsConnection();

        try {
            $rows = DB::connection($expenseConn)->select(
                "SELECT ar.*, ct.ClaimName as activity_name, 
                        ac.crop_name, av.variety_name 
                 FROM y{$yearId}_advance_requests ar 
                 LEFT JOIN adv_activity_names an ON ar.activity_name_id = an.id 
                 LEFT JOIN claimtype ct ON ct.ClaimId = an.activity_name 
                 LEFT JOIN adv_activity_crops ac ON ar.activity_crops_id = ac.id 
                 LEFT JOIN adv_activity_varieties av ON ar.activity_variety_id = av.id 
                 WHERE ar.request_id = ? OR ar.id = ?",
                [$requestId, $requestId]
            );

            if (empty($rows)) {
                return response()->json([
                    'code' => 404,
                    'status' => 'error',
                    'message' => 'Request not found.',
                ]);
            }

            $r = (array) $rows[0];

            $eName = '';
            $eCode = '';
            if (!empty($r['emp_id'])) {
                $empRes = DB::connection($hrimsConn)->select(
                    "SELECT EmpCode, TRIM(CONCAT(Fname, ' ', IFNULL(Sname, ''), ' ', Lname)) as Ename FROM hrm_employee WHERE EmployeeID = ?",
                    [$r['emp_id']]
                );
                if (!empty($empRes)) {
                    $eName = $empRes[0]->Ename ?? '';
                    $eCode = $empRes[0]->EmpCode ?? '';
                }
            }

            $detail = [
                'Employee Name' => $eName,
                'Employee Code' => $eCode,
                'Crops' => (string) ($r['crop_name'] ?? '-'),
                'Variety' => (string) ($r['variety_name'] ?? '-'),
                'Purpose' => (string) ($r['request_purpose'] ?? $r['reason'] ?? '-'),
            ];

            // Build level cards
            for ($lvl = 1; $lvl <= 5; $lvl++) {
                if (!empty($r["approver{$lvl}_id"])) {
                    $st = $r["approver{$lvl}_status"] ?? 'P';
                    $statusLabel = 'Pending';
                    if ($st == 'A') $statusLabel = 'Approved';
                    elseif ($st == 'R') $statusLabel = 'Rejected';
                    elseif ($st == 'H') $statusLabel = 'Reverted';

                    $apprDate = !empty($r["approver{$lvl}_date"]) ? date('d-m-Y', strtotime($r["approver{$lvl}_date"])) : '-';
                    $detail["Level {$lvl}"] = [
                        'Amount' => '₹' . ($r["approver{$lvl}_amount"] ?? $r['requested_amount'] ?? '0'),
                        'Approval Date' => $apprDate,
                        'Remark' => (string) ($r["approver{$lvl}_remark"] ?? '-'),
                        'Status' => $statusLabel,
                    ];
                }
            }

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'data' => $detail,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle action_advance_request - Approve, Reject, or Revert.
     */
    protected function actionAdvanceRequest(Request $request): JsonResponse
    {
        $requestId = $request->input('request_id') ?? $request->input('id');
        $yearId = (int) ($request->input('YearId') ?? $request->input('year_id'));
        $comId = $request->input('ComId') ?? $request->input('com_id');
        $empId = $request->input('empid') ?? $request->input('emp_id');
        $level = (int) ($request->input('level') ?? 1);
        $amount = $request->input('amount') ?? '0';
        $remark = $request->input('remark') ?? '';
        $action = $request->input('action') ?? 'A'; // A, R, H

        if (empty($requestId) || empty($yearId) || empty($comId)) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Missing required parameters.',
            ]);
        }

        $expenseConn = $this->getExpenseConnection((int) $comId);

        $statusChar = 'A';
        if (strtoupper($action) === 'R' || strcasecmp($action, 'Reject') === 0) {
            $statusChar = 'R';
        } elseif (strtoupper($action) === 'H' || strcasecmp($action, 'Revert') === 0) {
            $statusChar = 'H';
        }

        try {
            $tableName = "y{$yearId}_advance_requests";
            $upData = [
                "approver{$level}_status" => $statusChar,
                "approver{$level}_amount" => $amount,
                "approver{$level}_remark" => $remark,
                "approver{$level}_date" => now(),
            ];

            if ($statusChar === 'A') {
                $nextLvl = $level + 1;
                // Check if next approver exists
                $row = DB::connection($expenseConn)->select(
                    "SELECT approver{$nextLvl}_id FROM {$tableName} WHERE request_id = ? OR id = ?",
                    [$requestId, $requestId]
                );
                if (!empty($row) && !empty($row[0]->{"approver{$nextLvl}_id"})) {
                    $upData['current_level'] = $nextLvl;
                } else {
                    $upData['request_status'] = 'Approved';
                    $upData['approved_amount'] = $amount;
                }
            } elseif ($statusChar === 'R') {
                $upData['request_status'] = 'Rejected';
            } elseif ($statusChar === 'H') {
                $upData['request_status'] = 'Reverted';
            }

            DB::connection($expenseConn)->table($tableName)
                ->where('request_id', $requestId)
                ->orWhere('id', $requestId)
                ->update($upData);

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Advance request updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Failed to update advance request: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get month list for advance request.
     * Replicates: advance_request.php?value=get_month_list
     */
    public function getMonthList(Request $request): JsonResponse
    {
        $employeeId = (int) $request->input('empid');
        $yearId = (int) $request->input('YearId');
        $comId = (int) $request->input('ComId', 1);
        $requestId = (int) $request->input('request_id', 0);
        $requestType = trim((string) $request->input('request_type', ''));

        if ($employeeId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Invalid request or token.']);
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Check if existing request
        if ($requestId > 0) {
            $tableName = "y{$yearId}_advance_requests";
            $row = DB::connection($expenseConn)->table($tableName)
                ->where('emp_id', $employeeId)
                ->where('request_id', $requestId)
                ->select('request_month')
                ->first();

            if ($row) {
                $monthNum = (int) $row->request_month;
                $monthsData = [[
                    'month_number' => $monthNum,
                    'month_name' => date('F', mktime(0, 0, 0, $monthNum, 10))
                ]];
                return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Existing request month retrieved successfully.',
                    'data' => $monthsData,
                ]);
            }
        }

        if ($requestType === 'Activity' || $requestType === 'Other') {
            $currentMonth = (int) date('n');
            $monthsData = [];
            for ($i = 0; $i < 3; $i++) {
                $monthNum = ($currentMonth + $i) > 12 ? ($currentMonth + $i - 12) : ($currentMonth + $i);
                $monthsData[] = [
                    'month_number' => $monthNum,
                    'month_name' => date('F', mktime(0, 0, 0, $monthNum, 10))
                ];
            }
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Upcoming months data retrieved successfully.',
                'data' => $monthsData,
            ]);
        } elseif ($requestType === 'Against Expense') {
            $tableName = "y{$yearId}_monthexpensefinal";
            $months = DB::connection($expenseConn)->table($tableName)
                ->where('EmployeeID', $employeeId)
                ->where('YearId', $yearId)
                ->where('Status', 'Closed')
                ->where('Claim_Amount', '>', 0)
                ->where('Total_Claim', '>', 0)
                ->where(function ($q) {
                    $q->where('Fin_PayDate', '0000-00-00')
                      ->orWhereNull('Fin_PayDate')
                      ->orWhere('Fin_PayAmt', '')
                      ->orWhere('Fin_PayAmt', 0)
                      ->orWhereNull('Fin_PayAmt');
                })
                ->groupBy('Month')
                ->pluck('Month');

            $monthsData = [];
            foreach ($months as $m) {
                $mNum = (int) $m;
                $monthsData[] = [
                    'month_number' => $mNum,
                    'month_name' => date('F', mktime(0, 0, 0, $mNum, 10))
                ];
            }
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Expense-based months data retrieved successfully.',
                'data' => $monthsData,
            ]);
        }

        return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Invalid request type.']);
    }

    /**
     * Save advance request.
     * Replicates: advance_request.php?value=save_advance_request
     */
    public function saveAdvanceRequest(Request $request): JsonResponse
    {
        $employeeId = (int) $request->input('empid');
        $yearId = (int) $request->input('YearId');
        $comId = (int) $request->input('ComId', 1);

        if ($employeeId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Invalid request or token.']);
        }

        $requestType = trim((string) $request->input('request_type', ''));
        $requestId = (int) $request->input('request_id', 0);
        $activityNameId = (int) $request->input('activity_name_id', 0);
        $activityTypeId = (int) $request->input('activity_type_id', 0);
        $activityCropsId = (string) $request->input('activity_crops_id', '0');
        $activityVarietyId = (string) $request->input('activity_variety_id', '0');
        $requestMonth = trim((string) $request->input('request_month', ''));
        $amount = trim((string) $request->input('amount', ''));
        $purpose = trim((string) $request->input('purpose', ''));

        if (empty($requestType)) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Please select a type of request.']);
        }
        if (empty($requestMonth)) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Please select a month.']);
        }
        if (empty($amount)) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Please enter an amount.']);
        }
        if (empty($purpose)) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Please enter a purpose.']);
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);
        $tableName = "y{$yearId}_advance_requests";

        // Calculate year
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        $reqMonthNum = (int) $requestMonth;
        $reqYear = ($reqMonthNum < $currentMonth) ? ($currentYear + 1) : $currentYear;

        // Check duplicate
        if ($requestId === 0) {
            $exists = DB::connection($expenseConn)->table($tableName)
                ->where('emp_id', $employeeId)
                ->where('request_month', $requestMonth)
                ->where('request_year', $reqYear)
                ->where('request_type', $requestType)
                ->where('request_status', '!=', 'Rejected')
                ->exists();

            if ($exists) {
                return response()->json(['code' => 400, 'status' => 'error', 'message' => 'You have already submitted a request for this month.']);
            }
        }

        // Get employee details
        $emp = DB::connection($hrimsConn)->table('hrm_employee as e')
            ->leftJoin('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.EmployeeID', $employeeId)
            ->select('e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'eg.DepartmentId', 'eg.RepEmployeeID')
            ->first();

        if (!$emp) {
            return response()->json(['code' => 400, 'status' => 'error', 'message' => 'Employee data not found.']);
        }

        $employeeName = trim($emp->Fname . ' ' . ($emp->Sname ?? '') . ' ' . $emp->Lname);
        $empCode = $emp->EmpCode;
        $approver1Id = (int) ($emp->RepEmployeeID ?? 0);

        // Activity Name ID lookup if mapped
        $newActivityNameId = 0;
        if ($activityNameId > 0) {
            $actRow = DB::connection($expenseConn)->table('adv_activity_names')
                ->where('activity_name', $activityNameId)
                ->orWhere('id', $activityNameId)
                ->first();
            $newActivityNameId = $actRow ? $actRow->id : $activityNameId;
        }

        if ($requestId > 0) {
            DB::connection($expenseConn)->table($tableName)
                ->where('request_id', $requestId)
                ->update([
                    'activity_type_id' => $activityTypeId,
                    'activity_name_id' => $newActivityNameId,
                    'activity_crops_id' => $activityCropsId,
                    'activity_variety_id' => $activityVarietyId,
                    'request_status' => 'Submitted',
                    'approver1_id' => $approver1Id,
                    'approver1_status' => 'P',
                    'request_type' => $requestType,
                    'request_month' => $requestMonth,
                    'request_year' => $reqYear,
                    'requested_amount' => $amount,
                    'request_purpose' => $purpose,
                    'updated_date' => now(),
                ]);

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Request updated successfully.',
            ]);
        } else {
            $insertedId = DB::connection($expenseConn)->table($tableName)->insertGetId([
                'emp_id' => $employeeId,
                'activity_type_id' => $activityTypeId,
                'activity_name_id' => $newActivityNameId,
                'activity_crops_id' => $activityCropsId,
                'activity_variety_id' => $activityVarietyId,
                'emp_code' => $empCode,
                'employee_name' => $employeeName,
                'request_type' => $requestType,
                'request_month' => $requestMonth,
                'request_year' => $reqYear,
                'year_id' => $yearId,
                'requested_amount' => $amount,
                'request_purpose' => $purpose,
                'request_status' => 'Submitted',
                'approver1_id' => $approver1Id,
                'approver1_status' => 'P',
                'created_date' => now(),
            ]);

            $requestCode = 'RQ' . str_pad($insertedId, 6, '0', STR_PAD_LEFT);
            DB::connection($expenseConn)->table($tableName)
                ->where('request_id', $insertedId)
                ->orWhere('id', $insertedId)
                ->update(['request_code' => $requestCode]);

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Request submitted successfully.',
            ]);
        }
    }
}
