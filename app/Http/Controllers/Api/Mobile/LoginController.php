<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginController extends BaseMobileController
{
    /**
     * Mobile app login endpoint.
     * Exact replica of: login_new.php?value=login
     * 
     * Flutter calls this as POST with body:
     *   value=login, ComId=1, empcode=xxx, emppass=xxx, platform=android, tokenid=xxx, yearid=7
     */
    public function login(Request $request): JsonResponse
    {
        $value = $request->input('value', '');

        if ($value == '') {
            return response()->json(["msg" => "Parameter Missing!"]);
        }

        if ($value == 'checkRole') {
            return $this->checkRole($request);
        }

        if ($value != 'login') {
            return response()->json(["Code" => "100", "msg" => "Invalid value!"]);
        }

        $empcode = $request->input('empcode', '');
        $emppass = $request->input('emppass', '');
        $yearid = (int) ($request->input('yearid', ''));
        $tokenid = $request->input('tokenid', '');
        $platform = $request->input('platform', '');

        if (empty($empcode) || empty($emppass) || empty($yearid) || empty($tokenid) || empty($platform)) {
            return response()->json(["msg" => "Parameter Missing!"]);
        }

        // C-06: lockout after repeated wrong passwords
        if ($lockMsg = \App\Support\LoginLockout::blocked($empcode)) {
            return response()->json(['Code' => '100', 'login' => 'false', 'Msg' => $lockMsg]);
        }

        // Strip leading zeros
        $u = $empcode;
        $str = ltrim($u, "0");
        $empcode = (string) $str;

        $hrimsConn = $this->getHrimsConnection();

        // Auto-detect CompanyId from EmpCode_New or EmpCode
        $comId = (int) $request->input('ComId', 1);
        $qry = DB::connection($hrimsConn)
            ->select("select CompanyId from hrm_employee where (EmpCode_New=? or EmpCode=?) and EmpStatus!='De' LIMIT 1", [$empcode, $empcode]);
        if (!empty($qry)) {
            $detectedComId = (int) ((array) $qry[0])['CompanyId'];
            if ($detectedComId > 0) {
                $comId = $detectedComId;
            }
            if ($comId == 11) {
                $yearid = (string) ((int) $yearid - 2);
            }
        }

        // Set expense connection based on detected ComId
        $expenseConn = $this->getExpenseConnection($comId);

        // Check employee status
        $qry = DB::connection($hrimsConn)
            ->select("select EmpStatus, DateOfSepration from hrm_employee where (EmpCode_New=? or EmpCode=?) and EmpStatus!='De' and CompanyId=?", [$empcode, $empcode, $comId]);

        if (empty($qry)) {
            \App\Support\LoginLockout::failed($empcode);
            return response()->json([
                "Code" => "100",
                "login" => "false",
                "Msg" => "Something went wrong, Pls check login-id & password",
            ]);
        }

        $rqry = (array) $qry[0];
        $DatAcc = date("Y-m-d", strtotime($rqry["DateOfSepration"] . "+15 day"));
        $run_qry = [];
        $num = 0;

        if ($rqry["EmpStatus"] == "A" || $rqry["EmpStatus"] == "R") {
            $run_qry = DB::connection($hrimsConn)
                ->select("select * from hrm_employee where (EmpCode_New=? or EmpCode=?) and CompanyId=? and (EmpStatus='A' or EmpStatus='R')", [$empcode, $empcode, $comId]);
        } elseif ($rqry["EmpStatus"] == "D" && \App\Support\ZeroDate::has($DatAcc) && $DatAcc != "1970-01-01") {
            $run_qry = DB::connection($hrimsConn)
                ->select("select * from hrm_employee where (EmpCode_New=? or EmpCode=?) and CompanyId=? and EmpStatus='D' AND ? <= ?", [$empcode, $empcode, $comId, date("Y-m-d"), $DatAcc]);
        }

        $num = count($run_qry);

        if ($num > 0) {
            $info = (array) $run_qry[0];

            // Decrypt password
            $decryptService = new \App\Services\MobileApi\PasswordDecryptService();
            $emppass1 = $decryptService->decrypt($info["EmpPass"]);

            // LV-S02: password must match; hard-coded bypasses removed
            $isMatch = is_string($emppass1) && $emppass1 !== '' && hash_equals($emppass1, (string) $emppass);

            if ($isMatch) {
                \App\Support\LoginLockout::succeeded($empcode);
                $special_characters = [":", "-", "`", "~", "^", "!", "@", "#", '$', "%", "^", "&", "*", "(", ")", "{", "}", "_", "+"];
                $token = str_replace($special_characters, "", $tokenid);

                if ($token != "" && $platform != "ios") {
                    DB::connection($hrimsConn)->update("update hrm_employee set xeasy_tokenid=? where EmployeeID=?", [$token, $info["EmployeeID"]]);
                }

                if ($platform == "ios") {
                    DB::connection($hrimsConn)->update("update hrm_employee set xeasy_tokenid_ios=? where EmployeeID=?", [$token, $info["EmployeeID"]]);
                }

                // Get grade
                $sGr = DB::connection($hrimsConn)
                    ->select("select GradeValue from hrm_employee_general g inner join hrm_grade gr on g.GradeId=gr.GradeId where g.EmployeeID=?", [$info["EmployeeID"]]);
                $rGr = !empty($sGr) ? (array) $sGr[0] : [];

                // Check MoveRep
                $svs = DB::connection($hrimsConn)
                    ->select("select MoveRep from hrm_employee where EmployeeID=?", [$info["EmployeeID"]]);
                $resv = !empty($svs) ? (array) $svs[0] : ["MoveRep" => "N"];

                // Determine role
                $apprsel = DB::connection($hrimsConn)
                    ->select("select EmployeeID from hrm_employee_general where RepEmployeeID=?", [$info["EmployeeID"]]);
                $EmpRole = (count($apprsel) > 0 || $resv["MoveRep"] == "Y") ? "A" : "E";

                // Get employee details
                $empDT = DB::connection($hrimsConn)
                    ->select("SELECT e.EmployeeID AS EmployeeID, eg.GradeId AS GradeId, eg.MobileNo_Vnr AS OfficialMobile, cg.grade_name AS GradeValue, eg.HqId AS HeadquarterId, CASE WHEN eg.TerrId > 0 THEN ct.territory_name ELSE ccvs.city_village_name END AS HqName, eg.DepartmentId AS DepartmentId, cd.department_name AS DepartmentName, eg.DesigId AS DesignationId, cdes.designation_name AS DesigName, eg.RepEmployeeID AS ReportingEmployeeID, ep.MobileNo AS PersonalMobile, eg.ReportingName AS ReportingName FROM hrm_employee AS e LEFT JOIN hrm_employee_general AS eg ON e.EmployeeID = eg.EmployeeID LEFT JOIN hrm_employee_personal AS ep ON e.EmployeeID = ep.EmployeeID LEFT JOIN core_city_village_by_state AS ccvs ON eg.HqId = ccvs.id LEFT JOIN core_territory AS ct ON eg.TerrId = ct.id LEFT JOIN core_departments AS cd ON eg.DepartmentId = cd.id LEFT JOIN core_designation AS cdes ON eg.DesigId = cdes.id LEFT JOIN core_grades AS cg ON eg.GradeId = cg.id WHERE e.EmployeeId=? AND e.CompanyId=?", [$info["EmployeeID"], $comId]);
                $empD = !empty($empDT) ? (array) $empDT[0] : [];

                // Cross-company reporting
                $NrRep = 'N';
                $VVRep = 'N';

                $sNr = DB::connection($hrimsConn)
                    ->select("select * from hrm_employee_general g inner join hrm_employee e on g.EmployeeID=e.EmployeeID where g.RepEmployeeID=? AND (e.EmpStatus='A' or e.EmpStatus='R') and e.CompanyId=3", [$info["EmployeeID"]]);
                if (count($sNr) > 0) {
                    $NrRep = 'Y';
                }

                $sVV = DB::connection($hrimsConn)
                    ->select("select * from hrm_employee_general g inner join hrm_employee e on g.EmployeeID=e.EmployeeID where g.RepEmployeeID=? AND (e.EmpStatus='A' or e.EmpStatus='R') and e.CompanyId=11", [$info["EmployeeID"]]);
                if (count($sVV) > 0) {
                    $VVRep = 'Y';
                }

                // Nursery reporting check
                $employeeId = (int) $info["EmployeeID"];
                $nurseryChk = DB::connection($hrimsConn)
                    ->select("SELECT 1 FROM hrm_employee_general WHERE RepEmployeeID = ? AND EmployeeID IN (SELECT EmployeeID FROM hrm_employee WHERE CompanyId = 3 AND (EmpStatus = 'A' OR EmpStatus = 'R'))", [$employeeId]);
                $Reporting_NURSURY = (count($nurseryChk) > 0) ? "Y" : "N";

                // Get financial year dates (FromDate, ToDate)
                $checkFY = DB::connection($expenseConn)
                    ->select("SELECT FromDate, Todate FROM financialyear WHERE YearId = ?", [$yearid]);
                $fY = !empty($checkFY) ? (array) $checkFY[0] : ['FromDate' => '', 'Todate' => ''];

                return response()->json([
                    "Code" => "300",
                    "login" => "true",
                    "EmployeeID" => (string) $info["EmployeeID"],
                    "EmpCode" => (string) ($info["EmpCode_New"] ?? $info["EmpCode"] ?? ""),
                    "EmpName" => $info["Fname"] . " " . ($info["Sname"] ?? "") . " " . $info["Lname"],
                    "ComId" => (string) $info["CompanyId"],
                    "YearId" => (string) $yearid,
                    "FromDate" => (string) ($fY['FromDate'] ?? ''),
                    "ToDate" => (string) ($fY['Todate'] ?? ''),
                    "Role" => $EmpRole,
                    "Grade" => isset($rGr["GradeValue"]) ? (string) $rGr["GradeValue"] : "",
                    "Reporting_VESS" => $VVRep,
                    "Reporting_NURSURY" => $NrRep,
                    "Status" => (string) $info["EmpStatus"],
                    "GradeValue" => isset($empD["GradeValue"]) ? (string) $empD["GradeValue"] : "",
                    "HeadQuarterName" => isset($empD["HqName"]) ? (string) $empD["HqName"] : "",
                    "DepartmentName" => isset($empD["DepartmentName"]) ? (string) $empD["DepartmentName"] : "",
                    "DesigName" => isset($empD["DesigName"]) ? (string) $empD["DesigName"] : "",
                    "MobileNo" => isset($empD["OfficialMobile"]) ? (string) $empD["OfficialMobile"] : "",
                    "ReportingName" => isset($empD["ReportingName"]) ? (string) $empD["ReportingName"] : "",
                    "ProfileImg" => "https://vnrseeds.co.in/file-view/Employee_Image/" . $comId . "/" . ($info['EmpCode'] ?? '') . ".jpg",
                    "Msg" => "Login successfully"
                ]);
            } else {
                \App\Support\LoginLockout::failed($empcode);
                return response()->json([
                    "Code" => "100",
                    "login" => "false",
                    "Msg" => "Login failed",
                ]);
            }
        } else {
            \App\Support\LoginLockout::failed($empcode);
            return response()->json([
                "Code" => "100",
                "login" => "false",
                "Msg" => "Something went wrong, Pls check login-id & password",
            ]);
        }
    }

    /**
     * Check employee reporting role.
     * Replicates: login_new.php?value=checkRole
     */
    public function checkRole(Request $request): JsonResponse
    {
        $employeeId = (int) $request->input('empid');
        if ($employeeId <= 0) {
            return response()->json(["Code" => "100", "msg" => "Invalid value!"]);
        }

        $hrimsConn = $this->getHrimsConnection();

        // MoveRep flag
        $sMove = DB::connection($hrimsConn)->select("SELECT MoveRep FROM hrm_employee WHERE EmployeeID = ?", [$employeeId]);
        $rMove = !empty($sMove) ? (array) $sMove[0] : ['MoveRep' => 'N'];

        // Does anyone report to this employee?
        $sAppr = DB::connection($hrimsConn)->select("SELECT 1 FROM hrm_employee_general WHERE RepEmployeeID = ? LIMIT 1", [$employeeId]);
        $role = (!empty($sAppr) || ($rMove["MoveRep"] ?? '') === "Y") ? "A" : "E";

        // VESS (CompanyId 11)
        $sVV = DB::connection($hrimsConn)->select("SELECT 1 FROM hrm_employee_general g
                                    INNER JOIN hrm_employee e ON g.EmployeeID = e.EmployeeID
                                    WHERE g.RepEmployeeID = ?
                                    AND e.EmpStatus != 'De' AND e.CompanyId = 11 LIMIT 1", [$employeeId]);
        $VVRep = !empty($sVV) ? "Y" : "N";

        // Nursery (CompanyId 3)
        $sNr = DB::connection($hrimsConn)->select("SELECT 1 FROM hrm_employee_general g
                                    INNER JOIN hrm_employee e ON g.EmployeeID = e.EmployeeID
                                    WHERE g.RepEmployeeID = ?
                                    AND e.EmpStatus != 'De' AND e.CompanyId = 3 LIMIT 1", [$employeeId]);
        $NrRep = !empty($sNr) ? "Y" : "N";

        return response()->json([
            "Code"              => "300",
            "Role"              => $role,
            "Reporting_VESS"    => $VVRep,
            "Reporting_NURSURY" => $NrRep,
        ]);
    }
}
