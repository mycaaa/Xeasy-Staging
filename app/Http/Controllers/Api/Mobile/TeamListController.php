<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamListController extends BaseMobileController
{
    /**
     * Get pending claim list for team members under the approver.
     * Replicates: common.php?value=Team_Pending_Claimlist
     */
    public function handleTeamPendingClaimlist(Request $request): JsonResponse
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

        try {
            $hrimsConn = $this->getHrimsConnection();
            $expenseConn = $this->getExpenseConnection($comId);

            // Get team members under this appraiser
            $teamMembers = DB::connection($hrimsConn)->select(
                "SELECT EmployeeID FROM hrm_employee_general WHERE RepEmployeeID = ?",
                [$empId]
            );

            if (empty($teamMembers)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $array_e = [];
            foreach ($teamMembers as $row) {
                $array_e[] = ((array) $row)['EmployeeID'];
            }
            $ee = implode(',', $array_e);

            // Get pending month expense final records
            // APPR-VER-01: VNR process - the month reaches the reporting manager after verification
            $verifiedCond = \App\Support\ApproverAfterVerifier::applies($comId) ? ' AND ' . \App\Support\ApproverAfterVerifier::verifiedSql('f') : '';
            $sql = "SELECT f.* FROM y{$yearId}_monthexpensefinal f WHERE f.EmployeeID IN ({$ee}) AND f.YearId = {$yearId} AND Status = 'Closed' AND Total_Claim > 0 AND (Approved_Date = '0000-00-00' OR Approved_Date IS NULL){$verifiedCond} ORDER BY Month ASC, EmployeeID";
            $results = DB::connection($expenseConn)->select($sql);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $carray = [];
            foreach ($results as $row) {
                $res = (array) $row;

                // Get employee name
                $empNameResult = DB::connection($hrimsConn)->select(
                    "SELECT CONCAT(EmpCode, ' - ', TRIM(CONCAT(Fname, ' ', IFNULL(Sname, ''), ' ', Lname))) AS Ename FROM hrm_employee WHERE EmployeeID = ?",
                    [$res['EmployeeID']]
                );
                $res['EmpName'] = !empty($empNameResult) ? (array) $empNameResult[0] : ['Ename' => ''];

                // Get TotalApprovedAmt
                $approvedSql = "SELECT SUM(CASE WHEN ApprBy > 0 AND ApprBy IS NOT NULL AND ApprDate IS NOT NULL AND ApprDate != '' THEN e.ApprTAmt ELSE 0 END) AS TotalApprovedAmt FROM y{$yearId}_expenseclaims e WHERE e.ClaimMonth = '{$res['Month']}' AND e.ClaimYearId = '{$yearId}' AND e.CrBy = {$res['EmployeeID']} AND e.FilledOkay = 1 AND e.ClaimStatus != 'Deactivate' AND e.ClaimStatus != 'Draft' AND ((e.ClaimId = 7 AND e.App_Odometer = 'Y') OR (e.ClaimId != 7))";
                $approvedResult = DB::connection($expenseConn)->select($approvedSql);
                $res['TotalApprovedAmt'] = !empty($approvedResult) ? (((array) $approvedResult[0])['TotalApprovedAmt'] ?? '0') : '0';

                // Stringify all values (Flutter uses non-null String types)
                $stringified = [];
                // Known date fields and amount fields for Flutter type safety
                $dateFields = ['Verified_Date', 'Approved_Date', 'Finance_Date', 'Fin_AppDate', 'Fin_PayDate', 'PostDate', 'RecevingDate', 'VerifDate', 'CNIssueDate', 'DateOfSubmit', 'Crdate', 'payment_date', 'payment_approved_date', 'payment_instructed_date', 'payment_confirmed_date', 'payment_unsettled_date'];
                $amountFields = ['Claim_Amount', 'Verified_Amount', 'Approved_Amount', 'Finance_Amount', 'Total_Expense', 'Total_Claim', 'Total_Km', 'App_Km', 'Claim_Km', 'TotalApprovedAmt', 'Fin_AppAmt', 'Advance', 'Paid_Amount', 'Balance', 'TotalAmt', 'TotalPaidAmt'];

                foreach ($res as $key => $value) {
                    if (is_array($value)) {
                        $stringified[$key] = array_map(function ($v) { return $v === null ? '' : (string) $v; }, $value);
                    } elseif (is_null($value)) {
                        if (in_array($key, $dateFields)) {
                            $stringified[$key] = '1970-01-01';
                        } elseif (in_array($key, $amountFields)) {
                            $stringified[$key] = '0';
                        } else {
                            $stringified[$key] = '';
                        }
                    } else {
                        $strVal = (string) $value;
                        // D-09: the app reads 1970-01-01 as "no date"; '' stays '' unless the key is a date field (below)
                        if ($strVal === '0000-00-00') {
                            $strVal = '1970-01-01';
                        } elseif ($strVal === '0000-00-00 00:00:00') {
                            $strVal = '1970-01-01 00:00:00';
                        } elseif ($strVal === '' && in_array($key, $dateFields)) {
                            $strVal = '1970-01-01';
                        } elseif ($strVal === '' && in_array($key, $amountFields)) {
                            $strVal = '0';
                        }
                        $stringified[$key] = $strVal;
                    }
                }

                $carray[] = $stringified;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'Pending_list' => $carray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get day-wise pending claim details for a team member.
     * Replicates: common.php?value=Team_Pending_Claimlist_Day
     */
    public function handleTeamPendingClaimlistDay(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('Month');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || empty($month) || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        // Note: No token validation here - legacy uses >= 0 which always passes
        // The empid is the team MEMBER's ID, not the approver who owns the token

        try {
            $expenseConn = $this->getExpenseConnection($comId);

            // APPR-VER-01: this is the reporting manager's day view; VNR process opens after verification
            if (\App\Support\ApproverAfterVerifier::applies($comId) && ! \App\Support\ApproverAfterVerifier::monthVerified($expenseConn, $yearId, $empId, $month)) {
                return response()->json(['Code' => '100', 'msg' => \App\Support\ApproverAfterVerifier::MESSAGE]);
            }

            $sql = "SELECT e.ExpId, e.ClaimYearId, e.ClaimId, e.CrBy, e.CrBy as EmployeeID, e.CrDate, e.BillDate, e.DateEntryRemark as EmpRemark, e.FilledTAmt, e.VerifyTAmt, e.TotKm, VerifyBy, VerifyDate, VerifyTRemark, e.ApprTAmt, ApprBy, ApprDate, ApprTRemark, e.FinancedTAmt, FinancedBy, FinancedDate, FinancedTRemark, e.ClaimStatus, e.Remark, e.DateEntryRemark, e.FilledOkay, e.ClaimAtStep, ct.ClaimName, ct.cgId, e.ClaimMonth, e.opening_filepath, e.closing_filepath, Img_1, Img_2, Img_3, Img_4 as pdf_1, Img_5 as pdf_2 FROM y{$yearId}_expenseclaims e LEFT JOIN claimtype ct ON e.ClaimId = ct.ClaimId WHERE e.ClaimMonth = ? AND e.ClaimYearId = ? AND e.CrBy = ? AND e.FilledOkay = 1 AND e.ClaimStatus != 'Deactivate' AND e.ClaimStatus != 'Draft' AND e.ClaimAtStep >= 3 AND ((e.ClaimId = 7 AND e.App_Odometer = 'Y') OR (e.ClaimId != 7)) ORDER BY BillDate ASC";

            $results = DB::connection($expenseConn)->select($sql, [$month, $yearId, $empId]);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $hrimsConn = $this->getHrimsConnection();
            $Detail_carray = [];
            foreach ($results as $row) {
                $rowArr = (array) $row;
                // Resolve file URLs to S3
                $userId = $rowArr['CrBy'] ?? $rowArr['EmployeeID'] ?? $empId;
                $rowArr['opening_filepath'] = $this->resolveFileUrl($rowArr['opening_filepath'] ?? '', $yearId, $userId);
                $rowArr['closing_filepath'] = $this->resolveFileUrl($rowArr['closing_filepath'] ?? '', $yearId, $userId);
                $rowArr['Img_1'] = $this->resolveFileUrl($rowArr['Img_1'] ?? '', $yearId, $userId);
                $rowArr['Img_2'] = $this->resolveFileUrl($rowArr['Img_2'] ?? '', $yearId, $userId);
                $rowArr['Img_3'] = $this->resolveFileUrl($rowArr['Img_3'] ?? '', $yearId, $userId);
                $rowArr['pdf_1'] = $this->resolveFileUrl($rowArr['pdf_1'] ?? '', $yearId, $userId);
                $rowArr['pdf_2'] = $this->resolveFileUrl($rowArr['pdf_2'] ?? '', $yearId, $userId);

                // Add ColorCode (holiday/leave/mismatch indicator)
                $rowArr['ColorCode'] = $this->checkHoliday(
                    (int) ($rowArr['ClaimId'] ?? 0),
                    $rowArr['BillDate'] ?? null,
                    $rowArr['CrDate'] ?? null,
                    $userId,
                    $hrimsConn,
                    $rowArr['FilledTAmt'] ?? '0',
                    $rowArr['VerifyTAmt'] ?? '0'
                );

                $Detail_carray[] = $rowArr;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'DayWiseDetails' => $Detail_carray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get approved claim list for team members.
     * Replicates: common.php?value=Team_Approved_Claimlist
     */
    public function handleTeamApprovedClaimlist(Request $request): JsonResponse
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

        try {
            $hrimsConn = $this->getHrimsConnection();
            $expenseConn = $this->getExpenseConnection($comId);

            // Get team members from hrm_employee_general
            $teamMembers = DB::connection($hrimsConn)->select(
                "SELECT EmployeeID FROM hrm_employee_general WHERE RepEmployeeID = ?",
                [$empId]
            );

            if (empty($teamMembers)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $array_e = [];
            foreach ($teamMembers as $row) {
                $array_e[] = ((array) $row)['EmployeeID'];
            }
            $ee = implode(',', $array_e);

            // Get approved month expense final records
            $sql = "SELECT f.* FROM y{$yearId}_monthexpensefinal f WHERE f.EmployeeID IN ({$ee}) AND f.YearId = {$yearId} AND Status = 'Closed' AND Total_Claim > 0 AND Verified_Amount >= 0 AND Verified_Date != '0000-00-00' AND Approved_Amount >= 0 AND Approved_Date != '0000-00-00' ORDER BY DateOfSubmit DESC, Month DESC, EmployeeID";
            $results = DB::connection($expenseConn)->select($sql);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $carray = [];
            foreach ($results as $row) {
                $res = (array) $row;

                // Get employee name
                $empNameResult = DB::connection($hrimsConn)->select(
                    "SELECT CONCAT(EmpCode, ' - ', TRIM(CONCAT(Fname, ' ', IFNULL(Sname, ''), ' ', Lname))) AS Ename FROM hrm_employee WHERE EmployeeID = ?",
                    [$res['EmployeeID']]
                );
                $res['EmpName'] = !empty($empNameResult) ? (array) $empNameResult[0] : ['Ename' => ''];

                // Stringify all values
                $stringified = [];
                foreach ($res as $key => $value) {
                    $amountFields = ['Claim_Amount', 'Verified_Amount', 'Approved_Amount', 'Finance_Amount', 'Total_Expense', 'Total_Claim', 'Total_Km', 'App_Km', 'Claim_Km', 'TotalApprovedAmt', 'Fin_AppAmt', 'Advance', 'Paid_Amount', 'Balance', 'TotalAmt', 'TotalPaidAmt'];
                    if (is_array($value)) {
                        $stringified[$key] = array_map(function ($v) { return $v === null ? '' : (string) $v; }, $value);
                    } elseif (is_null($value)) {
                        $stringified[$key] = in_array($key, $amountFields) ? '0' : '';
                    } else {
                        $strVal = (string) $value;
                        // D-09: the app reads 1970-01-01 as "no date"; '' stays '' unless the key is a date field (below)
                        if ($strVal === '0000-00-00') {
                            $strVal = '1970-01-01';
                        } elseif ($strVal === '0000-00-00 00:00:00') {
                            $strVal = '1970-01-01 00:00:00';
                        } elseif ($strVal === '' && in_array($key, $amountFields)) {
                            $strVal = '0';
                        }
                        $stringified[$key] = $strVal;
                    }
                }

                $carray[] = $stringified;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'Approved_list' => $carray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get day-wise approved claim details for a team member.
     * Replicates: common.php?value=Team_Approved_Claimlist_Day
     */
    public function handleTeamApprovedClaimlistDay(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('Month');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || empty($month) || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        // No token validation - empid is team member's ID, not token owner

        try {
            $expenseConn = $this->getExpenseConnection($comId);

            $sql = "SELECT e.ExpId, e.ClaimYearId, e.ClaimId, e.CrBy, e.CrBy as EmployeeID, e.CrDate, e.BillDate, e.DateEntryRemark as EmpRemark, e.FilledTAmt, e.VerifyTAmt, e.TotKm, VerifyBy, VerifyDate, VerifyTRemark, e.ApprTAmt, ApprBy, ApprDate, ApprTRemark, e.FinancedTAmt, FinancedBy, FinancedDate, FinancedTRemark, e.ClaimStatus, e.Remark, e.DateEntryRemark, e.FilledOkay, e.ClaimAtStep, ct.ClaimName, ct.cgId, e.ClaimMonth, e.opening_filepath, e.closing_filepath, Img_1, Img_2, Img_3, Img_4 as pdf_1, Img_5 as pdf_2 FROM y{$yearId}_expenseclaims e LEFT JOIN claimtype ct ON e.ClaimId = ct.ClaimId WHERE e.ClaimMonth = ? AND e.ClaimYearId = ? AND e.CrBy = ? AND e.FilledOkay = 1 AND e.ClaimStatus != 'Deactivate' AND e.ClaimStatus != 'Draft' AND e.ClaimAtStep >= 5 AND ((e.ClaimId = 7 AND e.App_Odometer = 'Y') OR (e.ClaimId != 7)) ORDER BY BillDate ASC";

            $results = DB::connection($expenseConn)->select($sql, [$month, $yearId, $empId]);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $Detail_carray = [];
            foreach ($results as $row) {
                $rowArr = (array) $row;
                // Resolve file URLs to S3
                $userId = $rowArr['CrBy'] ?? $rowArr['EmployeeID'] ?? $empId;
                $rowArr['opening_filepath'] = $this->resolveFileUrl($rowArr['opening_filepath'] ?? '', $yearId, $userId);
                $rowArr['closing_filepath'] = $this->resolveFileUrl($rowArr['closing_filepath'] ?? '', $yearId, $userId);
                $rowArr['Img_1'] = $this->resolveFileUrl($rowArr['Img_1'] ?? '', $yearId, $userId);
                $rowArr['Img_2'] = $this->resolveFileUrl($rowArr['Img_2'] ?? '', $yearId, $userId);
                $rowArr['Img_3'] = $this->resolveFileUrl($rowArr['Img_3'] ?? '', $yearId, $userId);
                $rowArr['pdf_1'] = $this->resolveFileUrl($rowArr['pdf_1'] ?? '', $yearId, $userId);
                $rowArr['pdf_2'] = $this->resolveFileUrl($rowArr['pdf_2'] ?? '', $yearId, $userId);
                $Detail_carray[] = $rowArr;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'DayWiseDetails' => $Detail_carray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get team members list (reporting-based: Appraiser/Reviewer/HOD).
     * Replicates: common.php?value=TeamMember
     */
    public function handleTeamMember(Request $request): JsonResponse
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

        try {
            $hrimsConn = $this->getHrimsConnection();

            $sql = "SELECT e.EmployeeID, e.EmpCode, CONCAT(e.Fname, ' ', e.Sname, ' ', e.Lname) AS FullName, eg.HqId AS HeadQuarterId, CASE WHEN eg.TerrId > 0 THEN ct.territory_name ELSE ccvs.city_village_name END AS HeadQuarterName FROM hrm_employee e INNER JOIN hrm_employee_general eg ON e.EmployeeID = eg.EmployeeID LEFT JOIN core_territory ct ON eg.TerrId = ct.id LEFT JOIN core_city_village_by_state ccvs ON eg.HqId = ccvs.id WHERE e.EmpStatus = 'A' AND e.CompanyId = ? AND eg.RepEmployeeID = ?";

            $results = DB::connection($hrimsConn)->select($sql, [$comId, $empId]);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $tarray = [];
            foreach ($results as $row) {
                $tarray[] = (array) $row;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'Team_list' => $tarray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get team members by RepEmployeeID (appraiser view).
     * Replicates: common.php?value=TeamMemberAppraiser
     */
    public function handleTeamMemberAppraiser(Request $request): JsonResponse
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

        try {
            $hrimsConn = $this->getHrimsConnection();

            $sql = "SELECT e.EmployeeID, e.EmpCode, CONCAT(e.Fname, ' ', e.Sname, ' ', e.Lname) AS FullName, eg.HqId AS HeadQuarterId, CASE WHEN eg.TerrId > 0 THEN ct.territory_name ELSE ccvs.city_village_name END AS HeadQuarterName FROM hrm_employee e LEFT JOIN hrm_employee_general eg ON e.EmployeeID = eg.EmployeeID LEFT JOIN core_territory ct ON eg.TerrId = ct.id LEFT JOIN core_city_village_by_state ccvs ON eg.HqId = ccvs.id WHERE e.EmpStatus = 'A' AND e.CompanyId = ? AND (eg.RepEmployeeID = ?)";

            $results = DB::connection($hrimsConn)->select($sql, [$comId, $empId]);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $tarray = [];
            foreach ($results as $row) {
                $tarray[] = (array) $row;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'Team_list' => $tarray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }

    /**
     * Get team members for advance approval (with conditional Reviewer/HOD access).
     * Replicates: common.php?value=TeamMemberAppraiserAdvance
     */
    public function handleTeamMemberAppraiserAdvance(Request $request): JsonResponse
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

        try {
            $hrimsConn = $this->getHrimsConnection();
            $expenseConn = $this->getExpenseConnection($comId);

            $approvalRequired = true;

            // Get employee's department, function, and designation
            $empGenResult = DB::connection($hrimsConn)->select(
                "SELECT DepartmentId, EmpFunction, DesigId FROM hrm_employee_general WHERE EmployeeID = ? LIMIT 1",
                [$empId]
            );

            if (!empty($empGenResult)) {
                $employee = (array) $empGenResult[0];
                $today = date('Y-m-d');

                // Check advance_request_masters for approval_required flag
                $configResult = DB::connection($expenseConn)->select(
                    "SELECT vertical_designation FROM advance_request_masters WHERE department_id = ? AND is_active = 1 AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) LIMIT 1",
                    [$employee['DepartmentId'], $today, $today]
                );

                if (!empty($configResult)) {
                    $config = (array) $configResult[0];

                    if (!empty($config['vertical_designation'])) {
                        $verticalDesignation = json_decode($config['vertical_designation'], true);

                        if (is_array($verticalDesignation)) {
                            $verticalId = $employee['EmpFunction'];
                            $designationId = $employee['DesigId'];

                            if (isset($verticalDesignation[$verticalId])) {
                                foreach ($verticalDesignation[$verticalId] as $designation) {
                                    if (is_array($designation) && isset($designation['id']) && $designation['id'] == $designationId) {
                                        if ($designation['approval_required'] == 1) {
                                            $approvalRequired = false;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Build condition based on approval requirement
            $condition = "(eg.RepEmployeeID = {$empId} OR eg.EmployeeID = {$empId})";

            // Run team query
            $sql = "SELECT e.EmployeeID, e.EmpCode, CONCAT(e.Fname, ' ', e.Sname, ' ', e.Lname) AS FullName, eg.HqId AS HeadQuarterId, CASE WHEN eg.TerrId > 0 THEN ct.territory_name ELSE ccvs.city_village_name END AS HeadQuarterName FROM hrm_employee e INNER JOIN hrm_employee_general eg ON e.EmployeeID = eg.EmployeeID LEFT JOIN core_territory ct ON eg.TerrId = ct.id LEFT JOIN core_city_village_by_state ccvs ON eg.HqId = ccvs.id WHERE e.EmpStatus = 'A' AND e.CompanyId = {$comId} AND ({$condition})";

            $results = DB::connection($hrimsConn)->select($sql);

            if (empty($results)) {
                return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
            }

            $tarray = [];
            foreach ($results as $row) {
                $tarray[] = (array) $row;
            }

            return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'Team_list' => $tarray]);
        } catch (\Exception $e) {
            return response()->json(['Code' => '100', 'msg' => 'Data Not Available!']);
        }
    }
}
