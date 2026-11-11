<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Services\ClaimRules\ClaimSubmissionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Legacy Proxy Controller
 * 
 * This controller accepts requests in the EXACT same format as the legacy PHP API
 * (using ?value=xxx parameter routing) and returns IDENTICAL JSON responses.
 * 
 * This allows the mobile app to just change the base URL without any other modifications.
 * 
 * Usage from mobile app:
 *   OLD: https://expense.vnrseeds.co.in/api_expense/common.php?value=Draftlist&empid=123&...
 *   NEW: https://your-domain.com/mobile-api/common?value=Draftlist&empid=123&...
 * 
 * The internal routing is done via the 'value' parameter, just like the legacy API.
 */
class LegacyProxyController extends BaseMobileController
{
    protected $con;   // Expense DB connection name
    protected $con2;  // HRIMS DB connection name

    /**
     * Handle common.php equivalent requests.
     * Accepts GET/POST with ?value=xxx parameter.
     */
    public function common(Request $request): JsonResponse
    {
        $this->setupConnections($request);
        $value = $request->input('value', '');

        if ($value == '') {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        // Route to the appropriate handler based on 'value' parameter
        return match ($value) {
            'current_status' => $this->handleCurrentStatus($request),
            'companylist' => $this->handleCompanyList($request),
            'yearlist' => $this->handleYearList($request),
            'AddRemark' => $this->handleAddRemark($request),
            'HelpManual' => $this->handleHelpManual($request),
            'Current_date_odo_details' => $this->handleCurrentDateOdoDetails($request),
            'Draftlist' => $this->handleDraftlist($request),
            'Openlist' => $this->handleOpenlist($request),
            'Openlist_Day' => $this->handleOpenlistDay($request),
            'Closelist' => $this->handleCloselist($request),
            'Closelist_Day' => $this->handleCloselistDay($request),
            'Closelist_Month' => $this->handleCloselistMonth($request),
            'Closelist_All_Month' => $this->handleCloselistAllMonth($request),
            'Team_Pending_Claimlist' => $this->handleTeamPendingClaimlist($request),
            'Team_Pending_Claimlist_Day' => $this->handleTeamPendingClaimlistDay($request),
            'Team_Approved_Claimlist' => $this->handleTeamApprovedClaimlist($request),
            'Team_Approved_Claimlist_Day' => $this->handleTeamApprovedClaimlistDay($request),
            'DA_ClaimType' => $this->handleDAClaimType($request),
            'ExDetail' => $this->handleExDetail($request),
            'TeamMember' => $this->handleTeamMember($request),
            'TeamMemberAppraiser' => $this->handleTeamMemberAppraiser($request),
            'TeamMemberAppraiserAdvance' => $this->handleTeamMemberAppraiserAdvance($request),
            'AllTeamExp' => $this->handleAllTeamExp($request),
            'TeamRunningKilometers' => $this->handleTeamRunningKilometers($request),
            'TeamMemberExpenses' => $this->handleTeamMemberExpenses($request),
            'TeamMemberExpensesById' => $this->handleTeamMemberExpensesById($request),
            'TeamExpensesByIdAndMember' => $this->handleTeamExpensesByIdAndMember($request),
            'UpdateExpenseClaimsOkayAll' => $this->handleUpdateExpenseClaimsOkayAll($request),
            'ApprovedClaimsByMonth' => $this->handleApprovedClaimsByMonth($request),
            'ApprovedClaimsByMem' => $this->handleApprovedClaimsByMem($request),
            'ApprovedClaimsByMonthMem' => $this->handleApprovedClaimsByMonthMem($request),
            'VehicleRunning' => $this->handleVehicleRunning($request),
            'NotAgree' => $this->handleNotAgree($request),
            'Agree' => $this->handleAgree($request),
            '24WheelerCheckBox' => $this->handle24WheelerCheckBox($request),
            'RQ_List' => $this->handleRQList($request),
            'RQ_Approver_List' => $this->handleRQApproverList($request),
            'RQ_Approver2_List' => $this->handleRQApprover2List($request),
            'RQ_Check_Roles' => $this->handleRQCheckRoles($request),
            'RQ_Add_Advance' => $this->handleRQAddAdvance($request),
            'RQ_Action' => $this->handleRQAction($request),
            'ios_update_request' => $this->handleIosUpdateRequest($request),
            'team_last_odo' => $this->handleTeamLastOdo($request),
            'pending_odo_approve' => $this->handlePendingOdoApprove($request),
            'approve_odo_reading' => $this->handleApproveOdoReading($request),
            'reject_odo_reading' => $this->handleRejectOdoReading($request),
            'approve_all_odo_reading' => $this->handleApproveAllOdoReading($request),
            'reject_all_odo_reading' => $this->handleRejectAllOdoReading($request),
            'approve_odo_final_submit' => $this->handleApproveOdoFinalSubmit($request),
            'show_hide_pending' => $this->handleShowHidePending($request),
            'get_data_runkm' => $this->handleGetDataRunKm($request),
            'update_image_data' => $this->handleUpdateImageData($request),
            'other_participant_categories', 'OtherParticipantCategories', 'other_participant_category' => $this->handleOtherParticipantCategories($request),
            'SearchEmployee', 'search_employee' => $this->handleSearchEmployee($request),
            // The web claimant screen posts Courier_Submit to common.php, and the
            // verifier claimant screen sends UpdateCourierDetails; both save the same fields.
            'Courier_Submit', 'UpdateCourierDetails' => $this->handleCourierSubmit($request),
            default => response()->json(["Code" => "100", "msg" => "Invalid value!"]),
        };
    }

    /**
     * Search employee by name or code.
     */
    protected function handleSearchEmployee(Request $request): JsonResponse
    {
        $search = $request->input('search', $request->input('q', ''));
        $comId = $request->input('ComId', $request->input('comid', 1));
        $claimantId = $request->input('claimant_id') ?: $request->input('EmpId') ?: $request->input('EmployeeId') ?: $request->input('empid') ?: session('employee_id');
        $claimantCode = $request->input('claimant_code') ?: $request->input('EmpCode') ?: $request->input('empcode') ?: session('emp_code');

        if (empty($search)) {
            return response()->json(["status" => 200, "data" => []]);
        }

        try {
            // Access restriction: Check if searching user is management
            $isManagement = false;
            if ($claimantId || $claimantCode) {
                $searcher = DB::connection($this->con2)->table('hrm_employee_general AS eg')
                    ->leftJoin('hrm_employee AS emp', 'emp.EmployeeID', '=', 'eg.EmployeeID')
                    ->leftJoin('core_grades AS cg', 'cg.id', '=', 'eg.GradeId')
                    ->when($claimantId, fn($q) => $q->where('eg.EmployeeID', $claimantId))
                    ->when($claimantCode, fn($q) => $q->where('emp.EmpCode', $claimantCode)->orWhere('emp.EmpCode_New', $claimantCode))
                    ->select('eg.DepartmentId', 'cg.grade_name')
                    ->first();

                if ($searcher) {
                    $isManagement = ($searcher->DepartmentId == 18) || 
                                   str_starts_with(strtoupper(trim($searcher->grade_name ?? '')), 'MG');
                }
            }

            $query = DB::connection($this->con2)
                ->table('hrm_employee AS emp')
                ->leftJoin('hrm_employee_general AS g', 'emp.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin('hrm_department AS d', 'g.DepartmentId', '=', 'd.DepartmentId')
                ->leftJoin('core_grades AS cg', 'g.GradeId', '=', 'cg.id')
                ->leftJoin('hrm_company AS c', 'emp.CompanyId', '=', 'c.CompanyId')
                ->select(
                    'emp.EmployeeID',
                    'emp.EmpCode',
                    'emp.EmpCode_New',
                    DB::raw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) AS EmployeeName"),
                    'd.DepartmentName AS department_name',
                    'cg.grade_name AS grade_name',
                    'c.CompanyName AS company_name'
                );

            if (!$isManagement) {
                $query->where(function ($q) {
                    $q->where('g.DepartmentId', '!=', 18)->orWhereNull('g.DepartmentId');
                })->where(function ($q) {
                    $q->whereRaw("UPPER(TRIM(cg.grade_name)) NOT LIKE 'MG%'")->orWhereNull('cg.grade_name');
                });
            }

            $employees = $query->where(function ($q) use ($search) {
                    $q->whereRaw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) LIKE ?", ["%{$search}%"])
                      ->orWhere('emp.EmpCode_New', 'LIKE', "%{$search}%")
                      ->orWhere('emp.EmpCode', 'LIKE', "%{$search}%");
                })
                ->where('emp.EmpStatus', 'A')
                ->orderBy('emp.EmpCode_New')
                ->limit(20)
                ->get();

            return response()->json([
                "status" => 200,
                "data" => $employees
            ]);
        } catch (\Exception $e) {
            Log::error('SearchEmployee error: ' . $e->getMessage());
            return response()->json(["status" => 500, "msg" => $e->getMessage(), "data" => []]);
        }
    }

    /**
     * Get active other participant categories / roles for dropdown.
     */
    protected function handleOtherParticipantCategories(Request $request): JsonResponse
    {
        \App\Models\OtherParticipantCategory::ensureTableExists();

        try {
            $categories = \App\Models\OtherParticipantCategory::active()->get(['id', 'name', 'code', 'description', 'sort_order']);
        } catch (\Exception $e) {
            $categories = [
                ['id' => 1, 'name' => 'Retailer', 'code' => 'RETAILER', 'sort_order' => 1],
                ['id' => 2, 'name' => 'Dealer', 'code' => 'DEALER', 'sort_order' => 2],
                ['id' => 3, 'name' => 'Distributor', 'code' => 'DISTRIBUTOR', 'sort_order' => 3],
                ['id' => 4, 'name' => 'Consultant', 'code' => 'CONSULTANT', 'sort_order' => 4],
                ['id' => 5, 'name' => 'Management', 'code' => 'MANAGEMENT', 'sort_order' => 5],
                ['id' => 6, 'name' => 'Guest', 'code' => 'GUEST', 'sort_order' => 6],
                ['id' => 7, 'name' => 'Other Employee not available in the list', 'code' => 'OTHER_EMPLOYEE', 'sort_order' => 7],
            ];
        }

        return response()->json([
            "Code" => "300",
            "status" => 200,
            "success" => true,
            "data" => $categories,
            "categories" => $categories
        ]);
    }

    /**
     * Handle login.php equivalent requests.
     */
    public function login(Request $request): JsonResponse
    {
        $value = $request->input('value', '');

        if ($value == '') {
            return response()->json(["msg" => "Parameter Missing!"]);
        }

        if ($value == 'login') {
            $loginController = new LoginController();
            return $loginController->login($request);
        }

        if ($value == 'checkRole') {
            $loginController = new LoginController();
            return $loginController->checkRole($request);
        }

        return response()->json(["Code" => "100", "msg" => "Invalid value!"]);
    }

    /**
     * Handle claim.php equivalent requests.
     */
    public function claim(Request $request): JsonResponse
    {
        $this->setupConnections($request);
        $value = $request->input('value', '');

        if ($value == '') {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        return match ($value) {
            'DA_Update' => $this->handleDAUpdate($request),
            'uploadimg_odo_closing' => $this->handleUploadImgOdoClosing($request),
            'uploadimg_odo_opening' => $this->handleUploadImgOdoOpening($request),
            'uploadimg' => $this->handleUploadImg($request),
            'upi_proof_upload' => (new UpiProofApiController())->upload($request), // UPI-PROOF-01
            'upi_proof_list' => (new UpiProofApiController())->list($request),
            'Claim_Okay' => $this->handleClaimOkay($request),
            'Claim_Deny' => $this->handleClaimDeny($request),
            'Claim_Deleted' => $this->handleClaimDeleted($request),
            'Claim_OkeyAll' => $this->handleClaimOkeyAll($request),
            'Claim_Submit' => $this->handleClaimSubmit($request),
            'Courier_Submit' => $this->handleCourierSubmit($request),
            'Claim_approved' => $this->handleClaimApproved($request),
            'Act_Claim_approved' => (new ClaimActionsController())->handleActClaimApproved($request),
            'Reporting_Claim_Submit' => $this->handleReportingClaimSubmit($request),
            'upload_bill' => $this->handleUploadBill($request),
            'check_rate' => $this->handleCheckRate($request),
            default => response()->json(["Code" => "100", "msg" => "Invalid value!"]),
        };
    }

    /**
     * Setup database connections based on ComId.
     */
    private function setupConnections(Request $request): void
    {
        $comId = $this->getComId($request);
        $this->con = $this->getExpenseConnection($comId);
        $this->con2 = $this->getHrimsConnection();
    }

    /**
     * Validate token - returns true if valid (legacy uses >= 0 which always passes).
     */
    private function tokenCheck(Request $request): bool
    {
        $empid = (int) $request->input('empid');
        $tokenid = $request->input('tokenid', '');
        $special_characters = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '{', '}', '_', '+'];
        $token = str_replace($special_characters, '', $tokenid);

        // Note: Legacy uses `if ($rows >= 0)` which ALWAYS passes (even 0 rows)
        // We replicate this permissive behavior
        return true;
    }

    /**
     * Execute raw SELECT and return array of associative arrays (like mysqli_fetch_assoc).
     */
    private function rawSelect(string $connection, string $sql, array $bindings = []): array
    {
        $results = DB::connection($connection)->select($sql, $bindings);
        return array_map(function ($row) {
            return (array) $row;
        }, $results);
    }

    /**
     * Execute raw SELECT and return first row as array (like mysqli_fetch_assoc).
     */
    private function rawFirst(string $connection, string $sql, array $bindings = []): ?array
    {
        $results = DB::connection($connection)->select($sql, $bindings);
        return !empty($results) ? (array) $results[0] : null;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // COMMON.PHP HANDLERS - Each matches the exact legacy response
    // ═══════════════════════════════════════════════════════════════════════════

    private function handleCurrentStatus(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $comId = $this->getComId($request);

        $rqry = $this->rawFirst($this->con2, "select EmpStatus,DateOfSepration from hrm_employee where EmployeeID=? and EmpStatus!='De' and CompanyId=?", [$empid, $comId]);

        if (!$rqry) {
            return response()->json(["Code" => "300", "current_status" => "Deactive"]);
        }

        if ($rqry['EmpStatus'] == 'A' || $rqry['EmpStatus'] == 'R') {
            return response()->json(["Code" => "300", "current_status" => "Active"]);
        } elseif ($rqry['EmpStatus'] == 'D' && \App\Support\ZeroDate::has($rqry['DateOfSepration']) && $rqry['DateOfSepration'] != '1970-01-01') {
            if (date('Y-m-d') <= date('Y-m-d', strtotime($rqry['DateOfSepration'] . '+15 day'))) {
                return response()->json(["Code" => "300", "current_status" => "Active"]);
            }
        }
        return response()->json(["Code" => "300", "current_status" => "Deactive"]);
    }

    private function handleCompanyList(Request $request): JsonResponse
    {
        $results = $this->rawSelect($this->con2, "select CompanyId as ComId,CompanyName as ComName from hrm_company_basic where Status='A' AND (CompanyId=1 OR CompanyId=3) order by CompanyId asc");

        if (count($results) > 0) {
            return response()->json(["Code" => "300", "company_list" => $results]);
        }
        return response()->json(["Code" => "100", "msg" => "Data Not Available!"]);
    }

    private function handleYearList(Request $request): JsonResponse
    {
        $results = $this->rawSelect($this->con, "select YearId, Year, Status from financialyear order by YearId desc limit 0,3");

        if (count($results) > 0) {
            return response()->json(["Code" => "300", "year_list" => $results]);
        }
        return response()->json(["Code" => "100", "msg" => "Data Not Available!"]);
    }

    private function handleAddRemark(Request $request): JsonResponse
    {
        $controller = new CommonController();
        return $controller->addRemark($request);
    }

    private function handleHelpManual(Request $request): JsonResponse
    {
        $controller = new CommonController();
        return $controller->helpManual($request);
    }

    private function handleCurrentDateOdoDetails(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handleCurrentDateOdoDetails($request);
    }

    private function handleDAClaimType(Request $request): JsonResponse
    {
        $controller = new CommonController();
        return $controller->daClaimTypes($request);
    }

    private function handle24WheelerCheckBox(Request $request): JsonResponse
    {
        $controller = new CommonController();
        return $controller->wheelCheckbox($request);
    }

    // ─── Stub handlers for remaining endpoints ───────────────────────────────
    // These need the full legacy SQL ported. For now they return the correct
    // error format if not yet implemented.

    private function handleDraftlist(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleDraftlist($request);
    }

    private function handleOpenlist(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleOpenlist($request);
    }

    private function handleOpenlistDay(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleOpenlistDay($request);
    }

    private function handleCloselist(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleCloselist($request);
    }

    private function handleCloselistDay(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleCloselistDay($request);
    }

    private function handleCloselistMonth(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleCloselistMonth($request);
    }

    private function handleCloselistAllMonth(Request $request): JsonResponse
    {
        $controller = new ClaimListController();
        return $controller->handleCloselistAllMonth($request);
    }

    private function handleTeamPendingClaimlist(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamPendingClaimlist($request);
    }

    private function handleTeamPendingClaimlistDay(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamPendingClaimlistDay($request);
    }

    private function handleTeamApprovedClaimlist(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamApprovedClaimlist($request);
    }

    private function handleTeamApprovedClaimlistDay(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamApprovedClaimlistDay($request);
    }

    private function handleExDetail(Request $request): JsonResponse
    {
        $controller = new ExpenseDetailController();
        return $controller->handleExDetail($request);
    }

    private function handleTeamMember(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamMember($request);
    }

    private function handleTeamMemberAppraiser(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamMemberAppraiser($request);
    }

    private function handleAllTeamExp(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = (int) $request->input('YearId');
        $comId = (int) $request->input('ComId');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => false, 'data' => null, 'error' => 'Parameter Missing!']);
        }

        $expenseConn = (new TeamListController())->getExpenseConnection($comId);
        $hrimsConn = (new TeamListController())->getHrimsConnection();

        try {
            // Get team member IDs
            $teamMembers = DB::connection($hrimsConn)->select(
                "SELECT EmployeeID FROM hrm_employee_general WHERE RepEmployeeID = ?", [$empId]
            );
            $memberIds = array_map(fn($m) => $m->EmployeeID, $teamMembers);

            if (empty($memberIds)) {
                return response()->json(['success' => true, 'data' => ['ExpenseType' => ['ClaimTypes' => [], 'MonthlyGrandTotals' => $this->emptyMonths(), 'OverallGrandTotal' => '0']], 'error' => null]);
            }

            $idList = implode(',', $memberIds);

            // Get claim types
            $claimTypes = DB::connection($expenseConn)->select("SELECT ClaimId, ClaimName FROM claimtype WHERE ClaimStatus = 'A' ORDER BY ClaimId");

            $claimTypeResults = [];
            $grandTotals = array_fill(1, 12, 0);
            $overallGrand = 0;

            foreach ($claimTypes as $ct) {
                $monthlyTotals = array_fill(1, 12, 0);
                $claimTotal = 0;

                $monthData = DB::connection($expenseConn)->select(
                    "SELECT ClaimMonth, COALESCE(SUM(FilledTAmt), 0) as Total FROM y{$yearId}_expenseclaims WHERE CrBy IN ({$idList}) AND ClaimId = ? AND ClaimYearId = ? AND ClaimStatus != 'Deactivate' AND ClaimStatus != 'Draft' AND FilledBy > 0 GROUP BY ClaimMonth",
                    [$ct->ClaimId, $yearId]
                );

                foreach ($monthData as $md) {
                    $m = (int) $md->ClaimMonth;
                    $total = (float) $md->Total;
                    if ($m >= 1 && $m <= 12) {
                        $monthlyTotals[$m] = $total;
                        $grandTotals[$m] += $total;
                        $claimTotal += $total;
                    }
                }

                $overallGrand += $claimTotal;

                if ($claimTotal > 0) {
                    $monthlyTotalsStr = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $monthlyTotalsStr[(string) $i] = number_format($monthlyTotals[$i], 2, '.', '');
                    }

                    $claimTypeResults[] = [
                        'ClaimId' => (string) $ct->ClaimId,
                        'ClaimName' => (string) $ct->ClaimName,
                        'MonthlyTotals' => $monthlyTotalsStr,
                        'MonthTotal' => number_format($claimTotal, 2, '.', ''),
                    ];
                }
            }

            $grandTotalsStr = [];
            for ($i = 1; $i <= 12; $i++) {
                $grandTotalsStr[(string) $i] = number_format($grandTotals[$i], 2, '.', '');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'ExpenseType' => [
                        'ClaimTypes' => $claimTypeResults,
                        'MonthlyGrandTotals' => $grandTotalsStr,
                        'OverallGrandTotal' => number_format($overallGrand, 2, '.', ''),
                    ]
                ],
                'error' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'error' => $e->getMessage()]);
        }
    }

    private function emptyMonths(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[(string) $i] = '0.00';
        }
        return $months;
    }

    private function handleTeamMemberAppraiserAdvance(Request $request): JsonResponse
    {
        $controller = new TeamListController();
        return $controller->handleTeamMemberAppraiserAdvance($request);
    }

    private function handleTeamRunningKilometers(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->teamRunningKm($request);
    }

    private function handleTeamMemberExpenses(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->teamMemberExpenses($request);
    }

    private function handleTeamMemberExpensesById(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->teamMemberExpensesById($request);
    }

    private function handleTeamExpensesByIdAndMember(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->teamExpensesByIdAndMember($request);
    }

    private function handleUpdateExpenseClaimsOkayAll(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->updateExpenseClaimsOkayAll($request);
    }

    private function handleApprovedClaimsByMonth(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->approvedClaimsByMonth($request);
    }

    private function handleApprovedClaimsByMem(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->approvedClaimsByMember($request);
    }

    private function handleApprovedClaimsByMonthMem(Request $request): JsonResponse
    {
        $controller = new TeamController();
        return $controller->approvedClaimsByMonthMember($request);
    }

    private function handleVehicleRunning(Request $request): JsonResponse
    {
        $controller = new ExpenseDetailController();
        return $controller->handleVehicleRunning($request);
    }

    private function handleNotAgree(Request $request): JsonResponse
    {
        $controller = new ExpenseDetailController();
        return $controller->handleNotAgree($request);
    }

    private function handleAgree(Request $request): JsonResponse
    {
        $controller = new ExpenseDetailController();
        return $controller->handleAgree($request);
    }

    private function handleRQList(Request $request): JsonResponse
    {
        $controller = new AdvanceController();
        return $controller->list($request);
    }

    private function handleRQApproverList(Request $request): JsonResponse
    {
        $controller = new AdvanceController();
        return $controller->approverList($request);
    }

    private function handleRQApprover2List(Request $request): JsonResponse
    {
        $controller = new AdvanceController();
        return $controller->approver2List($request);
    }

    private function handleRQCheckRoles(Request $request): JsonResponse
    {
        $controller = new AdvanceController();
        return $controller->checkRoles($request);
    }

    private function handleRQAddAdvance(Request $request): JsonResponse
    {
        $controller = new AdvanceController();
        return $controller->addAdvance($request);
    }

    private function handleRQAction(Request $request): JsonResponse
    {
        $controller = new AdvanceController();
        return $controller->action($request);
    }

    private function handleIosUpdateRequest(Request $request): JsonResponse
    {
        return response()->json(["Code" => "300", "msg" => "Success"]);
    }

    private function handleTeamLastOdo(Request $request): JsonResponse
    {
        $controller = new ExpenseDetailController();
        return $controller->handleTeamLastOdo($request);
    }

    private function handlePendingOdoApprove(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handlePendingOdoApprove($request);
    }

    private function handleApproveOdoReading(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handleApproveOdoReading($request);
    }

    private function handleRejectOdoReading(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handleRejectOdoReading($request);
    }

    private function handleApproveAllOdoReading(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handleApproveAllOdoReading($request);
    }

    private function handleRejectAllOdoReading(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handleRejectAllOdoReading($request);
    }

    private function handleApproveOdoFinalSubmit(Request $request): JsonResponse
    {
        $controller = new OdometerApprovalController();
        return $controller->handleApproveOdoFinalSubmit($request);
    }

    private function handleShowHidePending(Request $request): JsonResponse
    {
        $controller = new ExpenseDetailController();
        return $controller->handleShowHidePending($request);
    }

    private function handleGetDataRunKm(Request $request): JsonResponse
    {
        $controller = new AppVersionController();
        return $controller->getDataRunKm($request);
    }

    private function handleUpdateImageData(Request $request): JsonResponse
    {
        return response()->json(["Code" => "300", "msg" => "Success"]);
    }

    // ─── CLAIM.PHP HANDLERS ──────────────────────────────────────────────────

    private function handleDAUpdate(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleDAUpdate($request);
    }

    private function handleUploadImgOdoClosing(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleUploadOdoClosing($request);
    }

    private function handleUploadImgOdoOpening(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleUploadOdoOpening($request);
    }

    private function handleUploadImg(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleUploadBill($request);
    }

    private function handleClaimOkay(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleClaimOkay($request);
    }

    private function handleClaimDeny(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleClaimDeny($request);
    }

    private function handleClaimDeleted(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleClaimDeleted($request);
    }

    private function handleClaimOkeyAll(Request $request): JsonResponse
    {
        // Claim_OkeyAll - mark all claims as okay for a month
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('month');

        $conn = $this->getExpenseConnection($comId);
        try {
            DB::connection($conn)->statement(
                "UPDATE y{$yearId}_expenseclaims SET FilledOkay=1 WHERE CrBy=? AND ClaimMonth=? AND ClaimYearId=? AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft' AND FilledOkay=0",
                [$empId, $month, $yearId]
            );
            return response()->json(['Code' => '300', 'msg' => 'All claims marked as okay']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function handleClaimSubmit(Request $request): JsonResponse
    {
        // Claim_Submit - submit claims for a month (matches legacy claim.php logic)
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('month');

        $conn = $this->getExpenseConnection($comId);
        try {
            // Audit cut-off: no month submit after the last submission day unless allowed (SubPermission)
            $subPerm = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->value('SubPermission');
            // admin "Backend Submission" (server-to-server relay with the internal key) is allowed late
            $relayKey = (string) config('app.internal_api_key', '');
            $isBackendSubmit = $request->input('backend_submit') == '1' && $relayKey !== ''
                && hash_equals($relayKey, (string) $request->header('X-Xeasy-Internal', ''));
            $cut = $isBackendSubmit ? null : \App\Services\ClaimRules\ClaimSubmissionValidator::first(app(\App\Services\ClaimRules\ClaimSubmissionValidator::class)->submissionCutoff((int) $comId, (int) $yearId, (int) $month, $subPerm, $conn));
            if ($cut !== null) {
                return response()->json(['Code' => '100', 'msg' => $cut]);
            }

            $closingMonth = $month;
            $openingMonth = ($closingMonth == 12) ? 1 : $closingMonth + 1;

            // ODO-DUP-01: the same odometer trip saved twice is dropped before the month is totalled
            if (config('odo_duplicates.on_submit', true)) {
                app(\App\Services\Claims\DuplicateOdometer::class)->resolve($conn, (int) $yearId, (int) $empId, (int) $closingMonth, true, 'month_submit');
            }

            // 1. Update claims: mark as verified/approved with amounts (matches legacy behavior)
            DB::connection($conn)->statement(
                "UPDATE y{$yearId}_expenseclaims 
                SET FilledOkay=1, ClaimAtStep=3, VerifyTAmt=FilledTAmt, c_verify='Y', ApprTAmt=FilledTAmt, FinancedTAmt=FilledTAmt 
                WHERE CrBy=? AND ClaimMonth=? AND ClaimYearId=? AND ClaimStatus='Filled' 
                AND FilledOkay != 2 
                AND ((ClaimId = 7 AND App_Odometer = 'Y') OR (ClaimId != 7)) 
                AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status = 'A')",
                [$empId, $closingMonth, $yearId]
            );

            // 2. Calculate totals
            $totals = DB::connection($conn)->select(
                "SELECT COUNT(*) as TotClaim, COALESCE(SUM(FilledTAmt), 0) as FilledAmt 
                FROM y{$yearId}_expenseclaims 
                WHERE ClaimYearId=? AND CrBy=? AND ClaimMonth=? AND FilledOkay=1 
                AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft' AND FilledBy>0 
                AND ((ClaimId = 7 AND App_Odometer = 'Y') OR (ClaimId != 7)) 
                AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status = 'A')",
                [$yearId, $empId, $closingMonth]
            );
            $totalClaim = !empty($totals) ? ($totals[0]->TotClaim ?? 0) : 0;
            $filledAmt = !empty($totals) ? ($totals[0]->FilledAmt ?? 0) : 0;

            // 3. Update monthexpensefinal
            DB::connection($conn)->statement(
                "UPDATE y{$yearId}_monthexpensefinal SET Status='Closed', Odo_Approve_Final_Submit='Y', DateOfSubmit=?, Total_Claim=?, Claim_Amount=? WHERE EmployeeID=? AND Month=? AND YearId=?",
                [date('Y-m-d'), $totalClaim, $filledAmt, $empId, $closingMonth, $yearId]
            );

            // 4. Update expenseclaimsdetails VerifierEditAmount = Amount
            $expIds = DB::connection($conn)->select(
                "SELECT ExpId FROM y{$yearId}_expenseclaims WHERE CrBy=? AND ClaimMonth=? AND ClaimYearId=? AND ClaimStatus='Filled' AND ((ClaimId = 7 AND App_Odometer = 'Y') OR (ClaimId != 7)) AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status = 'A')",
                [$empId, $closingMonth, $yearId]
            );
            foreach ($expIds as $row) {
                DB::connection($conn)->statement(
                    "UPDATE y{$yearId}_expenseclaimsdetails SET VerifierEditAmount=Amount WHERE ExpId=?",
                    [$row->ExpId]
                );
            }

            // 5. Create next month's Open record if not exists (skip if month 4 = start of FY)
            if ($openingMonth != 4) {
                $monthExists = DB::connection($conn)->select(
                    "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID=? AND Month=? AND YearId=?",
                    [$empId, $openingMonth, $yearId]
                );
                if (empty($monthExists)) {
                    DB::connection($conn)->statement(
                        "INSERT IGNORE INTO y{$yearId}_monthexpensefinal (EmployeeID, Month, YearId, Status, Crdate) VALUES (?, ?, ?, 'Open', ?)",
                        [$empId, $openingMonth, $yearId, date('Y-m-d')]
                    );
                }
            }

            return response()->json(['Code' => '300', 'msg' => 'Month claim submitted successfully']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function handleCourierSubmit(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('month', $request->input('Month'));
        $postDate = $request->input('PostDate', '');
        $docNo = $request->input('DocateNo', '');
        $agency = $request->input('Agency', '');

        // UAT E-04: claim.php Courier_Submit and the common.php aliases (Courier_Submit /
        // UpdateCourierDetails) write only with the employee's own app token, the same check as
        // the REST courier-submit (ClaimController::courierSubmit) and the other claim writes.
        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $month <= 0) {
            return response()->json(['Code' => '100', 'msg' => 'Parameter Missing!']);
        }
        if (! $this->validateToken($request)) {
            return response()->json(['Code' => '100', 'msg' => 'Invalid Employee!']);
        }

        $conn = $this->getExpenseConnection($comId);

        // Tracker #20: actual dispatch date only (valid, not future, not before the claim month).
        $dateError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($postDate, $empId, $month, $yearId, $conn));
        if ($dateError !== null) {
            return response()->json(['Code' => '100', 'msg' => $dateError]);
        }
        $postDate = ClaimSubmissionValidator::parseDate($postDate) ?? $postDate;

        try {
            DB::connection($conn)->statement(
                "UPDATE y{$yearId}_monthexpensefinal SET PostDate=?, DocateNo=?, Agency=? WHERE EmployeeID=? AND Month=? AND YearId=?",
                [$postDate, $docNo, $agency, $empId, $month, $yearId]
            );
            return response()->json(['Code' => '300', 'msg' => 'Data Updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function handleClaimApproved(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleClaimApproved($request);
    }

    private function handleReportingClaimSubmit(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleReportingClaimSubmit($request);
    }

    private function handleUploadBill(Request $request): JsonResponse
    {
        $controller = new ClaimActionsController();
        return $controller->handleUploadBill($request);
    }

    private function handleCheckRate(Request $request): JsonResponse
    {
        $controller = new ClaimController();
        return $controller->checkRate($request);
    }
}
