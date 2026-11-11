<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommonController extends BaseMobileController
{
    /**
     * Get current employee status.
     * Exact replica of: common.php?value=current_status
     */
    public function currentStatus(Request $request): JsonResponse
    {
        $comId = $this->getComId($request);
        $empId = (int) $request->input('empid');

        if ($empId <= 0 || $comId <= 0) {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        $hrimsConn = $this->getHrimsConnection();
        $qry = DB::connection($hrimsConn)
            ->select("select EmpStatus,DateOfSepration from hrm_employee where EmployeeID=? and EmpStatus!='De' and CompanyId=?", [$empId, $comId]);

        if (empty($qry)) {
            return response()->json(["Code" => "300", "current_status" => "Deactive"]);
        }

        $rqry = (array) $qry[0];

        if ($rqry['EmpStatus'] == 'A' || $rqry['EmpStatus'] == 'R') {
            return response()->json(["Code" => "300", "current_status" => "Active"]);
        } elseif ($rqry['EmpStatus'] == 'D' && \App\Support\ZeroDate::has($rqry['DateOfSepration']) && $rqry['DateOfSepration'] != '1970-01-01') {
            if (date('Y-m-d') <= date('Y-m-d', strtotime($rqry['DateOfSepration'] . '+15 day'))) {
                return response()->json(["Code" => "300", "current_status" => "Active"]);
            } else {
                return response()->json(["Code" => "300", "current_status" => "Deactive"]);
            }
        } else {
            return response()->json(["Code" => "300", "current_status" => "Deactive"]);
        }
    }

    /**
     * Get list of active companies.
     * Exact replica of: common.php?value=companylist
     */
    public function companyList(Request $request): JsonResponse
    {
        $hrimsConn = $this->getHrimsConnection();
        $run_qry = DB::connection($hrimsConn)
            ->select("select CompanyId as ComId,CompanyName as ComName from hrm_company_basic where Status='A' AND (CompanyId=1 OR CompanyId=3) order by CompanyId asc");

        if (count($run_qry) > 0) {
            $carray = $this->stringifyRows($run_qry);
            return response()->json(["Code" => "300", "company_list" => $carray]);
        } else {
            return response()->json(["Code" => "100", "msg" => "Data Not Available!"]);
        }
    }

    /**
     * Get list of financial years.
     * Exact replica of: common.php?value=yearlist
     */
    public function yearList(Request $request): JsonResponse
    {
        $comId = $this->getComId($request);
        $expenseConn = $this->getExpenseConnection($comId);

        $run_qry = DB::connection($expenseConn)
            ->select("select YearId, Year, Status from financialyear order by YearId desc limit 0,3");

        if (count($run_qry) > 0) {
            $carray = $this->stringifyRows($run_qry);
            return response()->json(["Code" => "300", "year_list" => $carray]);
        } else {
            return response()->json(["Code" => "100", "msg" => "Data Not Available!"]);
        }
    }

    /**
     * Add remark to an expense claim.
     * Exact replica of: common.php?value=AddRemark
     */
    public function addRemark(Request $request): JsonResponse
    {
        $empid = (int) $request->input('empid');
        $expid = (int) $request->input('expid');
        $FYearId = (int) $request->input('YearId');
        $comId = $this->getComId($request);
        $remark = $request->input('remark', '');
        $emprole = $request->input('emprole', '');
        $tokenid = $request->input('tokenid', '');

        if ($empid <= 0 || $expid <= 0 || $FYearId <= 0 || empty($remark) || empty($emprole) || empty($tokenid)) {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        // Token validation (legacy: rows >= 0, which always passes)
        $special_characters = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '{', '}', '_', '+'];
        $token = str_replace($special_characters, '', $tokenid);
        $hrimsConn = $this->getHrimsConnection();
        $run_qry = DB::connection($hrimsConn)
            ->select("select * from hrm_employee where EmployeeID=? and (xeasy_tokenid=? OR xeasy_tokenid_ios=?)", [$empid, $token, $token]);

        // Legacy uses >= 0 which always passes (even 0 results)
        $expenseConn = $this->getExpenseConnection($comId);

        $byid = $empid;
        $insert_result = DB::connection($expenseConn)
            ->insert("INSERT INTO y{$FYearId}_expenseremark (`ExpId`, `Remark`, `By`, `ByRole`, `CrDateTime`) VALUES (?, ?, ?, ?, ?)", [
                $expid, addslashes($remark), $byid, $emprole, date('Y-m-d H:i:s')
            ]);

        if ($insert_result) {
            $se = DB::connection($expenseConn)
                ->select("SELECT CrBy FROM y{$FYearId}_expenseclaims WHERE ExpId = ?", [$expid]);
            $re = !empty($se) ? (array) $se[0] : ['CrBy' => 0];

            $attchqry = ($byid == $re['CrBy']) ? 'Rmk = 0' : 'Rmk = 1';
            $up = DB::connection($expenseConn)
                ->update("UPDATE y{$FYearId}_expenseclaims SET {$attchqry} WHERE ExpId = ?", [$expid]);

            return response()->json(["Code" => "200", "msg" => "Remark added and expense claim updated successfully."]);
        } else {
            return response()->json(["Code" => "500", "msg" => "Failed to add remark."]);
        }
    }

    /**
     * Get help manual list.
     * Exact replica of: common.php?value=HelpManual
     */
    public function helpManual(Request $request): JsonResponse
    {
        $comId = $this->getComId($request);
        $yearId = $this->getYearId($request);
        $empid = (int) $request->input('empid');
        $tokenid = $request->input('tokenid', '');

        if ($yearId <= 0 || $comId <= 0 || empty($tokenid)) {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        $special_characters = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '{', '}', '_', '+'];
        $token = str_replace($special_characters, '', $tokenid);
        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Token check (legacy >= 0)
        $run_qry = DB::connection($hrimsConn)
            ->select("select * from hrm_employee where EmployeeID=? and (xeasy_tokenid=? OR xeasy_tokenid_ios=?)", [$empid, $token, $token]);

        $result = DB::connection($expenseConn)
            ->select("SELECT id, topic AS helpManual_name, description, video_path, `order`, video_language, status FROM help_manual where status = 1 ORDER BY `order` ASC");

        if (empty($result)) {
            return response()->json(["status" => 404, "message" => "No help manuals found"]);
        }

        $data = [];
        $s3Service = null;
        try {
            $s3Service = app(\App\Services\S3Service::class);
        } catch (\Exception $e) {
            // Continue without S3
        }

        foreach ($result as $row) {
            $row = (array) $row;
            if (!empty($row['video_path'])) {
                $s3Path = 'Expense/Help_Video/' . $row['video_path'];
                $row['video_path'] = ($s3Service && $s3Service->fileExists($s3Path)) ? $s3Service->getFileViewUrl($s3Path) : '';
            } else {
                $row['video_path'] = null;
            }
            $data[] = $row;
        }

        return response()->json(["status" => 200, "data" => $data]);
    }

    /**
     * Get DA claim types.
     * Exact replica of: common.php?value=DA_ClaimType
     */
    public function daClaimTypes(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $tokenid = $request->input('tokenid', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || empty($tokenid)) {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get DA eligibility
        $seluq = DB::connection($hrimsConn)->select(
            "SELECT DA_Outside_Hq, DA_Inside_Hq FROM hrm_employee_eligibility where Status='A' AND EmployeeID=?",
            [$empId]
        );

        if (empty($seluq)) {
            return response()->json(["Code" => "100", "msg" => "Data Empty!"]);
        }

        $rElig = (array) $seluq[0];
        $DAInside = $rElig['DA_Inside_Hq'] ?? '';
        $DAOutside = $rElig['DA_Outside_Hq'] ?? '';

        // Determine which claim types to show
        $clmid = '';
        if (!empty($DAInside) && !empty($DAOutside)) {
            $clmid = "(ClaimId=19 OR ClaimId=20)";
        } elseif (!empty($DAInside) && empty($DAOutside)) {
            $clmid = "ClaimId=19";
        } elseif (empty($DAInside) && !empty($DAOutside)) {
            $clmid = "ClaimId=20";
        }

        // Special employee overrides
        if ($empId == 1503 || $empId == 1486) {
            $clmid = "(ClaimId=19 OR ClaimId=20)";
            $DAInside = 'Upto Rs. 350/day (With Bills)';
            $DAOutside = 'Upto Rs. 400/day (With Bills)';
        }

        if (empty($clmid)) {
            return response()->json(["Code" => "100", "msg" => "Data Empty!"]);
        }

        $runqry = DB::connection($expenseConn)->select(
            "select ct.ClaimId, ct.ClaimName, cg.cgCode, cg.cgName from claimtype ct, claimgroup cg where ct.ClaimStatus='B' and ct.cgId=cg.cgId and {$clmid} group by ct.ClaimId"
        );

        if (empty($runqry)) {
            return response()->json(["Code" => "100", "msg" => "Data Empty!"]);
        }

        $carray = [];
        foreach ($runqry as $row) {
            $carray[] = (array) $row;
        }

        return response()->json([
            "Code" => "300",
            "DAClaim_type" => $carray,
            "DA_Inside_Amt" => (string) $DAInside,
            "DA_Outside_Amt" => (string) $DAOutside,
        ]);
    }

    /**
     * Get 2-4 wheeler checkbox status.
     * Exact replica of: common.php?value=24WheelerCheckBox
     */
    public function wheelCheckbox(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $comId = (int) $request->input('CompanyId', $this->getComId($request));
        $yearId = $this->getYearId($request);
        $tokenid = $request->input('tokenid', '');

        if ($empId <= 0 || $yearId <= 0 || empty($tokenid)) {
            return response()->json(["Code" => "100", "msg" => "Parameter Missing!"]);
        }

        $special_characters = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '{', '}', '_', '+'];
        $token = str_replace($special_characters, '', $tokenid);
        $hrimsConn = $this->getHrimsConnection();

        $run_qry = DB::connection($hrimsConn)
            ->select("select * from hrm_employee where EmployeeID=? and (xeasy_tokenid=? OR xeasy_tokenid_ios=?)", [$empId, $token, $token]);

        $eligibility = DB::connection($hrimsConn)
            ->select("select Travel_TwoWeeKM, Travel_FourWeeKM, FourWElig from hrm_employee_eligibility where EmployeeID=? and Status='A'", [$empId]);

        $twoWheeler = 'N';
        $fourWheeler = 'N';

        if (!empty($eligibility)) {
            $elig = (array) $eligibility[0];
            if (!empty($elig['Travel_TwoWeeKM']) && $elig['Travel_TwoWeeKM'] > 0) {
                $twoWheeler = 'Y';
            }
            if (!empty($elig['Travel_FourWeeKM']) && $elig['Travel_FourWeeKM'] > 0) {
                $fourWheeler = 'Y';
            }
            if (isset($elig['FourWElig']) && $elig['FourWElig'] === 'Y') {
                $fourWheeler = 'Y';
            }
        }

        return response()->json([
            "Code" => "300",
            "TwoWheeler" => $twoWheeler,
            "FourWheeler" => $fourWheeler,
        ]);
    }

    /**
     * Get active other participant categories / roles for dropdown.
     * Endpoint: common.php?value=other_participant_categories
     */
    public function otherParticipantCategories(Request $request): JsonResponse
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
}
