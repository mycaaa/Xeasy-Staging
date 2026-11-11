<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ClaimAjaxController extends Controller
{
    public function handleAjax(Request $request)
    {
        $act = $request->get('act');

        switch($act) {
            case 'clGrpShowMonthDet':
                return $this->clGrpShowMonthDet($request);
            case 'daysOfMonthDetToClaimer':
                return $this->daysOfMonthDetToClaimer($request);
            case 'clShowDaydet':
                return $this->clShowDaydet($request);
            case 'monthdettomediator':
                return $this->monthdettomediator($request);
            case 'monthdettoverifier':
                return $this->monthdettoverifier($request);
            case 'monthdettoapprover':
                return $this->monthdettoapprover($request);
            case 'monthdettofinance':
                return $this->monthdettofinance($request);
            case 'submitrem':
                return $this->submitrem($request);
            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }
    }

    private function clGrpShowMonthDet(Request $request)
    {
        $month = $request->get('month');
        $employeeId = session('EmployeeID');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        // Get month expense status
        $mstsd = $expenseConn->selectOne("SELECT Status FROM `y{$yearId}_monthexpensefinal`
                                          WHERE Month = ? AND EmployeeID = ?", [$month, $employeeId]);

        // Get claim groups from default connection
        $cg = DB::select("SELECT cgId, cgName FROM claimgroup");

        $grpscount = [];
        foreach ($cg as $cgd) {
            $t = $expenseConn->select("SELECT e.ClaimId FROM `y{$yearId}_expenseclaims` e, claimtype ct
                                       WHERE e.ClaimMonth = ?
                                       AND e.ClaimYearId = ?
                                       AND e.CrBy = ?
                                       AND e.ClaimId = ct.ClaimId
                                       AND e.ClaimStatus NOT IN ('Draft', 'Deactivate')
                                       AND e.FilledBY > 0
                                       AND e.AttachTo = 0
                                       AND ct.cgId = ?",
                                       [$month, $yearId, $employeeId, $cgd->cgId]);

            $grpscount['g' . $cgd->cgId] = count($t);
        }

        $html = '<td colspan="6" style="text-align:justify;">';
        $html .= '<span class="pull-left" style="padding-left: 1px !important;"></span>';

        // Get claim groups for buttons
        $cge = DB::select("SELECT cgId, cgCode FROM claimgroup");

        foreach ($cge as $cged) {
            $count = isset($grpscount['g' . $cged->cgId]) ? $grpscount['g' . $cged->cgId] : 0;
            $html .= '<button class="btn btn-outline-primary btn-sm pull-left grpbtns"
                            style="font-weight:bold;height:25px;font-size:10px;margin-left:2px;"
                            onclick="clShowDaysOfMonth(this,\'' . $month . '\',\'' . $employeeId . '\',\'' . $cged->cgId . '\',\'' . ($mstsd ? $mstsd->Status : '') . '\',\'Filled\')">
                            ' . $cged->cgCode . ' <span class="badge badge-danger" style="font-size:10px;">' . $count . '</span>
                      </button>';
        }

        $html .= '</td>';

        return response($html);
    }

    private function daysOfMonthDetToClaimer(Request $request)
    {
        $month = $request->get('month');
        $emp = $request->get('emp');
        $cgid = $request->get('cgid');
        $csts = $request->get('csts');
        $msts = $request->get('msts');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        $html = '<td colspan="7" style="text-align:left;padding-left:1px !important;">
                    <div id="clcldetdiv" style="display: inline-block !important;">
                        <table class="table shadow" style="width:100%;" align="center">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" style="background-color:#007bff; width:20px;"><font style="font-size:11px;">Sn</font></th>
                                    <th scope="col" style="background-color:#007bff; width:70px;"><font style="font-size:11px;">Date</font></th>
                                    <th scope="col" style="background-color:#007bff; width:60px;"><font style="font-size:11px;">Total Amt</font></th>';

        if ($msts == 'Closed') {
            $html .= '<th scope="col" style="background-color:#007bff;width:60px;"><font style="font-size:11px;">Paid Amt</font></th>';
        }

        $html .= '<th scope="col" style="background-color:#007bff; width:150px;">';

        if ($cgid != 0 && $msts != 'Closed') {
            $html .= '<label for="okayall' . $cgid . '">
                        <span id="okbtn" class="btn btn-sm btn-success">
                            <input id="okayall' . $cgid . '" type="checkbox" onclick="okayall(\'' . $emp . '\',\'' . $month . '\',\'' . $cgid . '\')">
                            <font style="font-size:10px;">Okay All</font>
                        </span>
                      </label>';
        }

        $html .= '</th></tr></thead><tbody>';

        // Build query
        if ($cgid == 0 || $csts == 'Draft') {
            $q = "SELECT e.CrDate as finalDate, e.* FROM `y{$yearId}_expenseclaims` e
                  WHERE e.ClaimMonth = ? AND e.ClaimYearId = ? AND e.CrBy = ?
                  AND (e.ClaimStatus IN ('Submitted', 'Draft')) AND e.AttachTo = 0
                  GROUP BY finalDate";
            $params = [$month, $yearId, $emp];
        } else {
            $q = "SELECT e.*, c.ClaimName, e.BillDate as finalDate
                  FROM `y{$yearId}_expenseclaims` e, claimtype c, `y{$yearId}_g{$cgid}_expensefilldata` ef
                  WHERE e.ClaimMonth = ? AND e.CrBy = ? AND c.ClaimId = e.ClaimId
                  AND c.cgId = ? AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
                  AND e.FilledBy > 0 GROUP BY finalDate";
            $params = [$month, $emp, $cgid];
        }

        $seleq = $expenseConn->select($q, $params);

        $i = 1;
        $k = 0;
        foreach ($seleq as $exp) {
            $finalDate = date('j/n/Y', strtotime($exp->finalDate));
            $dateParts = explode('/', $finalDate);

            if ($dateParts[1] == $month) {
                $k++;

                // Get total paid amount
                if ($cgid == 0 || $csts == 'Draft') {
                    $totpaida = $expenseConn->selectOne("SELECT SUM(FilledTAmt) as paid FROM `y{$yearId}_expenseclaims` e, claimtype c
                                               WHERE ClaimYearId = ? AND CrBy = ? AND c.ClaimId = e.ClaimId
                                               AND c.cgId = ? AND e.CrDate = ? AND e.ClaimStatus = ?
                                               AND e.FilledOkay != 2",
                                               [$yearId, $emp, $cgid, $exp->finalDate, $csts]);
                } else {
                    $totpaida = $expenseConn->selectOne("SELECT SUM(FilledTAmt) as paid FROM `y{$yearId}_expenseclaims` e, claimtype c
                                               WHERE ClaimYearId = ? AND CrBy = ? AND c.ClaimId = e.ClaimId
                                               AND c.cgId = ? AND e.BillDate = ?
                                               AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
                                               AND e.FilledBy > 0 AND e.FilledOkay != 2",
                                               [$yearId, $emp, $cgid, $exp->finalDate]);
                }

                $paidAmount = $totpaida ? $totpaida->paid : 0;

                $html .= '<tr onclick="showdet(\'' . $exp->CrBy . '\')">
                            <th scope="row"><font style="font-size:11px;">' . $k . '</font>
                                <input type="hidden" id="sts' . $exp->finalDate . '1" value="close">
                            </th>
                            <td>
                                <a href="JavaScript:Void(0);" onclick="clShowDaydet(this,\'' . $exp->finalDate . '\',\'' . session('EmployeeID') . '\',\'' . $cgid . '\',\'' . $csts . '\')">
                                    <font style="font-size:12px;cursor:pointer;">' . date("d/m/y", strtotime($exp->finalDate)) . '</font>
                                </a>
                             </td>
                            <td class="text-center" style="vertical-align:middle;">
                                <font style="font-size:11px;">' . ($paidAmount ? $paidAmount . '/-' : '') . '</font>
                             </td>';

                if ($msts == 'Closed') {
                    $totpaid = $expenseConn->selectOne("SELECT SUM(FinancedTAmt) as paid FROM `y{$yearId}_expenseclaims` e, claimtype c
                                              WHERE ClaimYearId = ? AND CrBy = ? AND c.ClaimId = e.ClaimId
                                              AND c.cgId = ? AND e.BillDate = ?
                                              AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
                                              AND e.FilledBy > 0 AND e.FinancedBy > 0 AND e.FinancedDate != '0000-00-00'",
                                              [$yearId, $emp, $cgid, $exp->finalDate]);
                    $financedAmount = $totpaid ? $totpaid->paid : 0;
                    $html .= '<td class="text-right"><font style="font-size:11px;">' . ($financedAmount ? $financedAmount . '/-' : '') . '</font></td>';
                } else {
                    $filla = $expenseConn->select("SELECT CrDate FROM `y{$yearId}_expenseclaims`
                                         WHERE ClaimYearId = ? AND CrBy = ? AND BillDate = ?
                                         AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                         AND FilledBy > 0 AND AttachTo = 0",
                                         [$yearId, $emp, $exp->finalDate]);
                    $fill = count($filla);

                    $ok = $expenseConn->select("SELECT CrDate FROM `y{$yearId}_expenseclaims`
                                      WHERE ClaimYearId = ? AND CrBy = ? AND BillDate = ?
                                      AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                      AND FilledBy > 0 AND AttachTo = 0",
                                      [$yearId, $emp, $exp->finalDate]);
                    $okCount = count($ok);

                    $den = $expenseConn->select("SELECT CrDate FROM `y{$yearId}_expenseclaims`
                                       WHERE ClaimYearId = ? AND CrBy = ? AND BillDate = ?
                                       AND FilledOkay = 2 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                       AND FilledBy > 0 AND AttachTo = 0",
                                       [$yearId, $emp, $exp->finalDate]);
                    $denCount = count($den);

                    $html .= '<td class="text-right">';
                    if ($fill > 0) {
                        $html .= '<button class="btn btn-outline-info btn-sm pull-left grpbtns" style="font-weight: bold;font-size:10px;">
                                    F&nbsp;<span class="badge badge-info" style="font-size: 10px;">' . $fill . '</span>
                                  </button>';
                    }
                    if ($okCount > 0) {
                        $html .= '<button class="btn btn-outline-success btn-sm pull-left grpbtns" style="font-size:10px;">
                                    &#10003;&nbsp;<span class="badge badge-success" style="font-size: 10px;">' . $okCount . '</span>
                                  </button>';
                    }
                    if ($denCount > 0) {
                        $html .= '<button class="btn btn-outline-danger btn-sm pull-left grpbtns" style="font-weight: bold;font-size:10px;">
                                    D&nbsp;<span class="badge badge-danger" style="font-size: 10px;">' . $denCount . '</span>
                                  </button>';
                    }
                    $html .= '</td>';
                }

                $html .= '</tr><tr id="' . $exp->finalDate . '1">';
            }
        }

        $html .= '</tbody></td></div></td>';

        return response($html);
    }

    private function clShowDaydet(Request $request)
    {
        $date = $request->get('date');
        $emp = $request->get('emp');
        $cgid = $request->get('cgid');
        $csts = $request->get('csts');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        $html = '<td colspan="6" style="text-align:left;padding-left: 6px !important;">
                    <div id="clcldetdiv" style="display: inline-block !important;">
                        <table class="table shadow" style="width:100%;" align="center">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" style="width:10px;background-color:#008C8C;"><font style="font-size:11px;">Sn</font></th>
                                    <th scope="col" style="width:90px;background-color:#008C8C;"><font style="font-size:11px;">Claim Id</font></th>
                                    <th scope="col" style="width:90px;background-color:#008C8C;"><font style="font-size:11px;">Claim Type</font></th>
                                    <th scope="col" style="width:60px;background-color:#008C8C;"><font style="font-size:11px;">Amount</font></th>
                                    <th scope="col" style="background-color:#008C8C;width:150px;"><font style="font-size:11px;">Status</font></th>
                                    <th scope="col" style="background-color:#008C8C;width:50px;"><font style="font-size:11px;"></font></th>
                                    <th scope="col" style="background-color:#008C8C;width:100px;"><font style="font-size:11px;">Remark</font></th>
                                </tr>
                            </thead>
                            <tbody>';

        // Build query
        if ($cgid == 0 || $csts == 'Draft') {
            $q = "SELECT e.* FROM `y{$yearId}_expenseclaims` e
                  WHERE e.ClaimYearId = ? AND e.CrBy = ? AND e.CrDate = ?
                  AND e.AttachTo = 0 AND (e.ClaimStatus IN ('Submitted', 'Draft'))
                  GROUP BY e.ExpId";
            $params = [$yearId, $emp, $date];
        } else {
            $q = "SELECT e.*, c.ClaimName FROM `y{$yearId}_expenseclaims` e, claimtype c, `y{$yearId}_g{$cgid}_expensefilldata` ef
                  WHERE e.ExpId = ef.ExpId AND e.ClaimYearId = ? AND e.CrBy = ?
                  AND c.ClaimId = e.ClaimId AND c.cgId = ? AND e.BillDate = ?
                  AND e.AttachTo = 0 AND e.FilledBy > 0
                  AND e.ClaimStatus NOT IN ('Deactivate', 'Draft') GROUP BY e.ExpId";
            $params = [$yearId, $emp, $cgid, $date];
        }

        $seleq = $expenseConn->select($q, $params);
        $i = 1;

        foreach ($seleq as $exp) {
            $trcolor = ($exp->ClaimStatus == 'Deactivate') ? 'background-color:#ffcccc;' : '';

            $html .= '<tr onclick="showdet(\'' . $exp->CrBy . '\')" style="' . $trcolor . '">
                        <th scope="row"><font style="font-size:11px;">' . $i . '</font></th>
                        <td>' . $exp->ExpId . '</td>
                        <td>
                            <a href="JavaScript:Void(0);" onclick="showexpdet(\'' . $exp->ExpId . '\')">
                                <font style="font-size:11px;">' . (isset($exp->ClaimName) ? substr($exp->ClaimName, 0, 12) : 'Not Filled') . '</font>
                            </a>
                        </td>
                        <td class="text-right">
                            <font style="font-size:11px;">' . (in_array($exp->ClaimStatus, ['Filled', 'Verified', 'Approved', 'Financed']) ? intval($exp->FilledTAmt) : '') . '</font>
                        </td>
                        <td style="text-align: left;">';

            if ($exp->ClaimStatus == 'Submitted' || $exp->ClaimStatus == 'Draft') {
                $s = 'P';
                $clss = 'btn btn-sm btn-outline-warning font-weight-bold';
            } elseif ($exp->ClaimStatus == 'Filled') {
                $s = 'F';
                $clss = 'btn btn-sm btn-outline-info font-weight-bold';
            } elseif ($exp->ClaimStatus == 'Deactivate') {
                $s = 'D';
                $clss = 'btn btn-sm btn-danger font-weight-bold';
            } else {
                $s = '';
                $clss = '';
            }

            $html .= '<div id="stsdiv' . $exp->ExpId . '" class="' . $clss . '">
                        <font style="font-size:10px;">' . $s . '</font>
                      </div>';

            $html .= '<span id="okspanarea' . $exp->ExpId . '">';

            if ($exp->ClaimStatus == 'Filled' && $exp->FilledOkay == 0) {
                $closed = '';
                if ($date) {
                    $m = date("n", strtotime($date));
                    $ct = $expenseConn->selectOne("SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID = ? AND Month = ?", [$exp->CrBy, $m]);
                    if ($ct && $ct->Status == 'Closed') {
                        $closed = 'disabled';
                    }
                }

                $html .= '<button id="okbtn' . $exp->ExpId . '" class="btn btn-sm btn-success" onclick="showOkDenyForm(\'' . $exp->ExpId . '\',\'okay\')" ' . $closed . '>
                            <font style="font-size:10px;">Okay</font>
                          </button>
                          &nbsp;
                          <button id="denybtn' . $exp->ExpId . '" class="btn btn-sm btn-danger" onclick="showOkDenyForm(\'' . $exp->ExpId . '\',\'deny\')" ' . $closed . '>
                            <font style="font-size:10px;">Deny</font>
                          </button>
                          <span id="okdenyspan' . $exp->ExpId . '" style="display: none;">
                              <input id="okdenyremark' . $exp->ExpId . '" class="" placeholder="Pls type reason" style="width:110px;">
                              <button id="saveokbtn' . $exp->ExpId . '" class="btn btn-sm btn-success" onclick="expfillok(\'' . $exp->ExpId . '\')" style="padding: 1px !important; width:35px;display: none;">Save</button>
                              <button id="savedenybtn' . $exp->ExpId . '" class="btn btn-sm btn-danger" onclick="expfilldeny(\'' . $exp->ExpId . '\')" style="padding: 1px !important; width:35px;display: none;">Save</button>
                          </span>';
            } elseif ($exp->ClaimStatus == 'Filled' && $exp->FilledOkay == 1) {
                $html .= '<div class="btn btn-sm btn-success"><font style="font-size:10px;">&#10003;</font></div>';
            } elseif ($exp->ClaimStatus == 'Filled' && $exp->FilledOkay == 2) {
                $html .= '<div class="btn btn-sm btn-danger"><font style="font-size:10px;">D</font></div>';
            }

            $html .= '</span>';

            if ($exp->ClaimStatus == 'Submitted' || $exp->ClaimStatus == 'Draft') {
                $html .= '<button class="btn btn-sm btn-info pull-right" style="padding:3px 3px !important;align-self: top!important;margin-right:10px;" onclick="attachclaims(this,\'' . $exp->ExpId . '\')">
                            <i class="fa fa-paperclip" aria-hidden="true"></i>
                          </button>';
            }

            $html .= '</td>
                      <td class="text-right">';

            if ($exp->ClaimAtStep == 1 || $exp->ClaimAtStep == 2) {
                $html .= '<button class="btn btn-sm btn-danger pull-right" style="padding:3px 3px !important;align-self: top!important;" onclick="deactivate(this,\'' . $exp->ExpId . '\')">
                            <i class="fa fa-times" aria-hidden="true"></i>
                          </button>';
            }

            $html .= '</td>
                      <td class="text-left">' . (\App\Support\ZeroDate::has($exp->VerifyDate) && $exp->VerifyBy > 0 ? $exp->VerifyTRemark : '') . '</td>
                     </tr>';

            $i++;
        }

        $html .= '</tbody></table></div></td>';

        return response($html);
    }

    private function monthdettomediator(Request $request)
    {
        $month = $request->get('month');
        $emp = $request->get('emp');
        $csts = $request->get('csts');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        // Get employee details from HRIMS
        $expn = DB::connection('hrims')->selectOne("SELECT Fname, Sname, Lname FROM hrm_employee WHERE EmployeeID = ?", [$emp]);

        $html = '<td style="background-color:#CCCCCC;"></td>
                <td colspan="10">
                    <table class="table shadow" style="padding:0px;">
                        <thead style="background-color:#ffbf80;border:1px solid black;">
                            <tr>
                                <th colspan="15" scope="col" style="width:30px;text-align:left;color:#FFFFFF;">
                                    <font color="#000000">Applied By:</font>
                                    &nbsp;&nbsp;' . ($expn ? $expn->Fname . ' ' . $expn->Sname . ' ' . $expn->Lname : '') . '
                                </th>
                            </tr>
                            <tr>
                                <th scope="col" style="width:30px;text-align:center;">Sn</th>
                                <th scope="col" style="width:50px;text-align:center;">Claim<br />ID</th>
                                <th scope="col" style="width:120px;text-align:center;">Claim Type</th>
                                <th scope="col" style="text-align:center;">Date</th>
                                <th scope="col" style="text-align:center;">Claim Status</th>
                                <th scope="col" style="text-align:center;">Action</th>
                                <th colspan="4" style="text-align:left;">Remark to Claimant</th>
                            </tr>
                        </thead>
                        <tbody>';

        $stepcond = "e.ClaimAtStep IN (1,2) AND ((e.ClaimStatus = ? OR e.ClaimStatus = 'Filled' OR e.ClaimStatus = 'Draft') OR (e.FilledOkay = 2))";

        $q = "SELECT e.*, c.ClaimName,
              CASE WHEN e.ClaimId != 0 THEN e.BillDate ELSE e.CrDate END AS finalDate
              FROM `y{$yearId}_expenseclaims` e, claimtype c
              WHERE e.ClaimYearId = ? AND e.CrBy = ?
              AND (c.ClaimId = e.ClaimId OR e.ClaimId = 0)
              AND {$stepcond} AND e.AttachTo = 0
              GROUP BY e.ExpId ORDER BY finalDate";

        $seleq = $expenseConn->select($q, [$csts, $yearId, $emp]);
        $i = 1;

        foreach ($seleq as $exp) {
            $html .= '<tr onclick="showdet(\'' . $exp->CrBy . '\')">
                        <th scope="row" style="text-align:center;">' . $i . '</th>
                        <td scope="row" style="text-align:center;">' . $exp->ExpId . '</td>
                        <td>' . ($exp->ClaimId != 0 ? substr($exp->ClaimName, 0, 15) : '') . '</td>
                        <td style="text-align:center;">' . date("d-m-Y", strtotime($exp->finalDate)) . '</td>
                        <td style="text-align:center; width:250px; vertical-align:middle;">';

            if ($exp->ClaimStatus == 'Filled') {
                $html .= '<div id="' . $exp->ExpId . 'FilledBtn" class="btn btn-sm btn-success font-weight-bold" style="height:22px;width:40px;">F</div>';
            }

            $c = 'outline-secondary';
            if ($exp->FilledOkay == 2) {
                $c = 'danger';
            }
            $s = $exp->ClaimStatus;
            if ($s == 'Draft') {
                $c = 'secondary';
            }

            if ($s == 'Submitted' || $s == 'Draft' || $exp->FilledOkay == 2) {
                $statusText = ($s == 'Submitted') ? 'Up' : (($s == 'Draft') ? 'Drft' : (($exp->FilledOkay == 2) ? 'D' : ''));
                $html .= '<div id="' . $exp->ExpId . 'Status" class="btn btn-sm btn-' . $c . ' font-weight-bold" style="height:22px; width:40px;">' . $statusText . '</div>';
            }

            if ($c == 'danger') {
                $html .= '<input id="' . $exp->ExpId . 'Rem" value="' . e((string) $exp->FilOkDenyRemark) . '" placeholder="Remark" readonly>';
            }

            $html .= '</td>
                      <td style="text-align:center; width:100px;">';

            if ($s == 'Submitted') {
                $html .= '<button id="' . $exp->ExpId . 'btn" class="btn btn-sm btn-primary btnfill" style="height:22px;width:50px;" onclick="showexpdet(\'' . $exp->ExpId . '\')">Fill</button>';
            } elseif ($s == 'Filled') {
                $html .= '<button id="' . $exp->ExpId . 'btn" class="btn btn-sm btn-info btnfill" style="height:22px;width:50px;" onclick="showexpdet(\'' . $exp->ExpId . '\')">View</button>';
            } elseif ($s == 'Draft') {
                $html .= '<button id="' . $exp->ExpId . 'btn" class="btn btn-sm btn-primary btnfill" style="height:22px;width:50px;" onclick="showexpdet(\'' . $exp->ExpId . '\')">Edit</button>';
            }

            if ($s != 'Filled') {
                $html .= '<button id="' . $exp->ExpId . 'atbtn" class="btn btn-sm btn-info pull-right" style="padding:3px 3px !important;align-self: top!important;margin-right:10px;" onclick="attachclaims(this,\'' . $exp->ExpId . '\',\'' . $exp->CrBy . '\')">
                            <i class="fa fa-paperclip" aria-hidden="true"></i>
                          </button>';
            }

            $html .= '</td>
                      <td colspan="4" style="width: 400px;">
                          <input id="RemToClaimer' . $exp->ExpId . '" value="' . e($exp->DateEntryRemark) . '" placeholder="Remark to Claimant" style="width: 70%;border:0px;">
                          <button onclick="submitrem(\'' . $exp->ExpId . '\',this)">Submit</button>
                          <span id="RemSpan' . $exp->ExpId . '" style="display: inline-block;"></span>
                       </td>
                     </tr>
                    <tr id="">
                        <td colspan="20" style="padding: 0px;margin:0px;">
                            <iframe id="clMonClmDet' . $exp->ExpId . '" src="" class="clframes" width="100%" style="display:none;height: 400px;"></iframe>
                        </td>
                     </tr>';

            $i++;
        }

        $html .= '</tbody></table></td>';

        return response($html);
    }

    private function monthdettoverifier(Request $request)
    {
        $month = $request->get('month');
        $emp = $request->get('emp');
        $csts = $request->get('csts');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        $html = '<td style="background-color: #CCCCCC;"></td>
                <td colspan="10">
                    <table class="table shadow">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col" style="width:30px;">Sn</th>
                                <th scope="col" style="width:40px;">Claim ID</th>
                                <th scope="col" style="width:100px;">Remark</th>
                                <th scope="col" style="width:100px;">File</th>
                                <th scope="row" class="text-center table-active">With Driver</th>
                                <th scope="col">Claim<br>Type</th>
                                <th scope="col">Upload<br>Date</th>
                                <th scope="col">Bill<br>Date</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Claimed</span><br>Amt</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Verified</span><br>Amt</th>
                                <th scope="row" class="text-center table-active">Verifier Remark</th>
                                <th scope="col">Claim Status</th>
                                <th scope="col">Detail</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>';

        // Get employee eligibility from HRIMS
        $rElig = DB::connection('hrims')->selectOne("SELECT * FROM hrm_employee_eligibility WHERE EmployeeID = ? AND Status = 'A'", [$emp]);
        $LodA = $rElig ? $rElig->Lodging_CategoryA : 0;
        $LodB = $rElig ? $rElig->Lodging_CategoryB : 0;
        $LodC = $rElig ? $rElig->Lodging_CategoryC : 0;
        $InsHq = $rElig ? preg_replace('/[^0-9]/', '', $rElig->DA_Inside_Hq) : 0;
        $OutHq = $rElig ? preg_replace('/[^0-9]/', '', $rElig->DA_Outside_Hq) : 0;

        // Total row
        $totpaid = $expenseConn->selectOne("SELECT SUM(FilledTAmt) as paid FROM `y{$yearId}_expenseclaims`
                                  WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ?
                                  AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                  AND FilledBy > 0", [$yearId, $emp, $month]);

        $totpaid2 = $expenseConn->selectOne("SELECT SUM(VerifyTAmt) as paid FROM `y{$yearId}_expenseclaims`
                                   WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ?
                                   AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                   AND VerifyBy > 0", [$yearId, $emp, $month]);

        $html .= '<tr class="totalrow">
                    <th scope="row" colspan="8" class="text-right"><b>Total&nbsp;</b></th>
                    <td class="text-right">' . ($totpaid ? $totpaid->paid : 0) . '</td>
                    <td class="text-right">' . ($totpaid2 ? $totpaid2->paid : 0) . '</td>
                    <th colspan="4"></th>
                   </tr>';

        // Query records
        if ($csts == 'Filled') {
            $stepcond = "e.ClaimAtStep >= 3";
        } elseif ($csts == 'Verified') {
            $stepcond = "e.ClaimAtStep >= 3";
        } else {
            $stepcond = "1=1";
        }

        $q = "SELECT e.*, c.ClaimName, c.cgid FROM `y{$yearId}_expenseclaims` e, claimtype c
              WHERE c.ClaimId = e.ClaimId AND e.ClaimMonth = ?
              AND e.ClaimYearId = ? AND e.CrBy = ?
              AND e.FilledOkay = 1 AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
              AND {$stepcond} ORDER BY e.BillDate ASC";

        $seleq = $expenseConn->select($q, [$month, $yearId, $emp]);
        $i = 1;

        foreach ($seleq as $exp) {
            $rowColor = '';
            // Check for holiday
            $holidayColor = $this->checkHoliday($exp->BillDate, $emp);
            if ($holidayColor) {
                $rowColor = $holidayColor;
            } elseif ($exp->FilledTAmt != $exp->VerifyTAmt) {
                $rowColor = '#ffff0091';
            } elseif (($exp->ClaimId == 19 && $exp->FilledTAmt > $InsHq) ||
                      ($exp->ClaimId == 20 && $exp->FilledTAmt > $OutHq)) {
                $rowColor = '#FFD2D2';
            }

            $html .= '<tr onclick="showdet(\'' . $exp->CrBy . '\')" style="background-color: ' . $rowColor . '">
                        <th scope="row">' . $i . '</th>
                        <td>' . $exp->ExpId . '</td>
                        <td>' . e($exp->DateEntryRemark) . '</td>';

            // File handling
            $dImg = $expenseConn->selectOne("SELECT FileName FROM `y{$yearId}_claimuploads` WHERE ExpId = ?", [$exp->ExpId]);
            $fileName = $dImg ? $dImg->FileName : '';
            $docUrl = $fileName ? "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$emp}/" . rawurlencode($fileName) : '';

            $html .= '<td style="text-align:center;cursor:pointer;vertical-align:middle;">';
            if ($fileName) {
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), ['pdf', 'doc', 'docx'])) {
                    $html .= '<img class="zoom" src="' . $docUrl . '" width="30" height="5" />';
                } else {
                    $html .= '<a href="' . $docUrl . '" target="_blank">Pdf/Doc</a>';
                }
            }
            $html .= '</td>';

            // With Driver
            $html .= '<td>';
            if ($exp->ClaimId == '7') {
                if ($exp->With_Driver == 'Y') {
                    $html .= '<span style="display: inline-block;padding: 2px 5px;font-size: 10px;color: #fff;background-color: #28a745;border-radius: 5px;">Yes</span>';
                } elseif ($exp->With_Driver == 'N') {
                    $html .= '<span style="display: inline-block;padding: 2px 5px;font-size: 10px;color: #fff;background-color: #ff0000b5;border-radius: 5px;">No</span>';
                }
            }
            $html .= '</td>';

            $html .= '<td>' . e((string) $exp->ClaimName) . '</td>
                      <td>' . date("d-m-Y", strtotime($exp->CrDate)) . '</td>
                      <td>' . date("d-m-Y", strtotime($exp->BillDate)) . '</td>
                      <td><input style="width: 70px;" class="form-control text-right" type="text" value="' . $exp->FilledTAmt . '" readonly></td>
                      <td><input style="width: 70px;" class="form-control text-right" type="text"
                                 id="' . $exp->ExpId . 'verifiedtamt" value="' . $exp->VerifyTAmt . '"
                                 onkeypress="return isNumber(event)"
                                 onkeyup="checkrange(this,\'' . $exp->FilledTAmt . '\',' . $exp->ExpId . ')"></td>
                      <td><textarea style="width: 120px;height: 27px;" class="form-control text-left"
                                    id="' . $exp->ExpId . 'verifiedtremark" rows="1">' . e($exp->VerifyTRemark) . '</textarea></td>
                      <td><div id="' . $exp->ExpId . 'Status" class="btn btn-sm btn-outline-secondary font-weight-bold">' . $exp->ClaimStatus . '</div></td>
                      <td><button id="' . $exp->ExpId . 'btn" class="btn btn-sm btn-primary" onclick="showexpdet(\'' . $exp->ExpId . '\')">Detail</button></td>
                      <td>';

            if ($csts == 'Filled') {
                $html .= '<span id="' . $exp->ExpId . 'verifyaction">
                            <input type="checkbox" onclick="showbtn(this,\'' . $exp->ExpId . '\')">
                            <button id="' . $exp->ExpId . 'verifybtn" class="btn btn-sm btn-success pull-right" onclick="verifyClaim(\'' . $exp->ExpId . '\')" disabled>
                                <i class="fa fa-check" aria-hidden="true"></i> Verify
                            </button>
                          </span>';
            }

            $html .= '</tr>';
            $i++;
        }

        $html .= '</tbody></table></div></td>';

        return response($html);
    }

    private function monthdettoapprover(Request $request)
    {
        $month = $request->get('month');
        $emp = $request->get('emp');
        $csts = $request->get('csts');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        $html = '<td colspan="10">
                    <table class="table shadow" style="width:100%;">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col" style="width:20px;background-color:#008C8C;"><font style="font-size:11px;">Sn</font></th>
                                <th scope="col" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Claim ID</font></th>
                                <th scope="col" style="width:100px;background-color:#008C8C;"><font style="font-size:11px;">Claim<br />Type</font></th>
                                <th scope="col" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Upload<br />Date</font></th>
                                <th scope="col" style="width:200px;background-color:#008C8C;"><font style="font-size:11px;">Remark</font></th>
                                <th scope="col" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Bill<br />Date</font></th>
                                <th scope="col" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Claimed<br>Amt</font></th>
                                <th scope="row" class="text-center table-active" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Verified<br>Amt</font></th>
                                <th scope="row" class="text-center table-active" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Diff<br>Amt</font></th>
                                <th scope="row" class="text-center table-active" style="width:100px;background-color:#008C8C;"><font style="font-size:11px;">Verifier Remark</font></th>
                                <th scope="row" class="text-center table-active" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Approver<br>Amt</font></th>
                                <th scope="row" class="text-center table-active" style="width:150px;background-color:#008C8C;"><font style="font-size:11px;">Approver Remark</font></th>
                                <th scope="col" style="width:40px;background-color:#008C8C;"><font style="font-size:11px;">Claim Sts</font></th>
                                <th scope="col" style="width:50px;background-color:#008C8C;"><font style="font-size:11px;">Action</font></th>
                            </tr>
                        </thead>
                        <tbody>';

        // Totals
        $totpaid = $expenseConn->selectOne("SELECT SUM(FilledTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                  WHERE ClaimMonth = ? AND ClaimYearId = ? AND e.CrBy = ?
                                  AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                  AND FilledBy > 0", [$month, $yearId, $emp]);

        $totpaid2 = $expenseConn->selectOne("SELECT SUM(VerifyTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                   WHERE ClaimMonth = ? AND ClaimYearId = ? AND e.CrBy = ?
                                   AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                   AND VerifyBy > 0", [$month, $yearId, $emp]);

        $difference = ($totpaid ? $totpaid->paid : 0) - ($totpaid2 ? $totpaid2->paid : 0);

        $totpaid3 = $expenseConn->selectOne("SELECT SUM(ApprTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                   WHERE ClaimMonth = ? AND ClaimYearId = ? AND e.CrBy = ?
                                   AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                   AND ApprBy > 0", [$month, $yearId, $emp]);

        $html .= '<tr class="totalrow">
                    <th scope="row" colspan="6" class="text-right"><b>Total&nbsp;</b></th>
                    <td class="text-right"><font style="font-size:11px;">' . ($totpaid ? $totpaid->paid : 0) . '</font></td>
                    <td class="text-right"><font style="font-size:11px;">' . ($totpaid2 ? $totpaid2->paid : 0) . '</font></td>
                    <td class="text-right"><font style="font-size:11px;">' . $difference . '</font></td>
                    <td class="text-left">&nbsp;</td>
                    <td class="text-right"><font style="font-size:11px;">' . ($totpaid3 ? $totpaid3->paid : 0) . '</font></td>
                    <th colspan="4"></th>
                   </tr>';

        // Query records
        if ($csts == 'Verified') {
            $stepcond = "e.ClaimAtStep >= 3";
        } elseif ($csts == 'Approved') {
            $stepcond = "e.ClaimAtStep >= 5";
        } else {
            $stepcond = "1=1";
        }

        $q = "SELECT e.*, c.ClaimName, c.cgid FROM `y{$yearId}_expenseclaims` e, claimtype c
              WHERE c.ClaimId = e.ClaimId AND e.ClaimMonth = ?
              AND e.ClaimYearId = ? AND e.CrBy = ?
              AND e.FilledOkay = 1 AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
              AND {$stepcond} ORDER BY e.BillDate ASC";

        $seleq = $expenseConn->select($q, [$month, $yearId, $emp]);
        $i = 1;

        foreach ($seleq as $exp) {
            $rowColor = ($exp->FilledTAmt != $exp->VerifyTAmt) ? '#ffff0091' : $this->checkHoliday($exp->BillDate, $emp);
            $differenceAmt = $exp->FilledTAmt - $exp->VerifyTAmt;

            $html .= '<tr style="background-color: ' . $rowColor . '" onclick="showdet(\'' . $exp->CrBy . '\')">
                        <th scope="row">' . $i . '</th>
                        <td class="text-right" style="text-align: center !important;"><font style="font-size:11px;">' . $exp->ExpId . '</font></td>
                        <td><a href="javascript:void(0)" onclick="showexpdet(\'' . $exp->ExpId . '\')">
                            <font style="font-size:11px;cursor:pointer;">' . substr($exp->ClaimName, 0, 12) . '..</font>
                          </a></td>
                        <td><a href="javascript:void(0)" onclick="showexpdet(\'' . $exp->ExpId . '\')">
                            <font style="font-size:11px;cursor:pointer;">' . date("d/m/y", strtotime($exp->CrDate)) . '</font>
                          </a></td>
                        <td style="text-align:left;">' . strtoupper($exp->Remark) . '</td>
                        <td><a href="javascript:void(0)" onclick="showexpdet(\'' . $exp->ExpId . '\')">
                            <font style="font-size:11px;cursor:pointer;">' . date("d/m/y", strtotime($exp->BillDate)) . '</font>
                          </a></td>
                        <td class="text-right"><font id="' . $exp->ExpId . '_ClaimedAmt" style="font-size:11px;">' . intval($exp->FilledTAmt) . '</font></td>';

            $vcstyle = (intval($exp->VerifyTAmt) != intval($exp->FilledTAmt)) ? 'style="background-color: #ffe6cc;"' : '';
            $html .= '<td ' . $vcstyle . ' id="' . $exp->ExpId . '_VerifiedAmt" class="text-right">' . ($exp->ClaimAtStep >= 4 ? $exp->VerifyTAmt : '') . '</td>
                      <td class="text-right">' . ($differenceAmt < 0 ? '<span style="color: red;">' . $differenceAmt . '</span>' : $differenceAmt) . '</td>
                      <td class="text-left">' . e($exp->VerifyTRemark) . '</td>
                      <td><input style="width:100%;" class="form-control text-right" type="text"
                                 id="' . $exp->ExpId . 'apprtamt" value="' . ($exp->ClaimAtStep >= 4 ? $exp->ApprTAmt : 0) . '"
                                 onkeypress="return isNumber(event)" onkeyup="checkrange(this,\'' . $exp->FilledTAmt . '\')"></td>
                      <td><input style="width:100%;height:27px;" class="form-control text-left"
                                 id="' . $exp->ExpId . 'apprtremark" value="' . e($exp->ApprTRemark) . '"></td>';

            if ($exp->ClaimStatus == 'Verified') {
                $c = 'outline-warning';
                $s = 'P';
            } elseif ($exp->ClaimAtStep >= 5) {
                $c = 'outline-success';
                $s = 'A';
            } else {
                $c = '';
                $s = '';
            }

            $html .= '<td><div id="' . $exp->ExpId . 'Status" class="btn btn-sm btn-' . $c . ' font-weight-bold">' . $s . '</div></td>
                      <td>';

            if (($exp->ClaimStatus != 'Approved' && $exp->ClaimAtStep <= 4)) {
                $html .= '<span id="appraction">
                            <input type="checkbox" onclick="showbtn(this,\'' . $exp->ExpId . '\')">
                            <button id="' . $exp->ExpId . 'apprbtn" class="btn btn-sm btn-success pull-right" onclick="approveClaim(\'' . $exp->ExpId . '\')" disabled>
                                <i class="fa fa-check" aria-hidden="true"></i> Approve
                            </button>
                          </span>';
            }

            $html .= '</td></tr>';
            $i++;
        }

        $html .= '</tbody></table></div></td>';

        return response($html);
    }

    private function monthdettofinance(Request $request)
    {
        $month = $request->get('month');
        $emp = $request->get('emp');
        $sts = $request->get('sts');
        $csts = $request->get('csts');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        $html = '<tr>
                <td colspan="10">
                    <table class="table shadow table-responsive">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col" style="width: 30px;">Sn</th>
                                <th scope="col" style="width: 50px;">Claim ID</th>
                                <th scope="col">Claim Type</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">Bill Date</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Claimed</span><br>Amt</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Verified</span><br>Amt</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Verifier</span><br>Remark</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Approver</span><br>Amt</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Approver</span><br>Remark</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Finance</span><br>Amt</th>
                                <th scope="row" class="text-center table-active"><span style="font-size: 10px !important;">Finance</span><br>Remark</th>
                                <th scope="col">Claim Status</th>
                                <th scope="col">Detail</th>';

        if ($sts == 'Open') {
            $html .= '<th scope="col" style="width:140px;">Action</th>';
        }

        $html .= '</thead><tbody>';

        // Totals
        $totpaid = $expenseConn->selectOne("SELECT SUM(FilledTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                  WHERE ClaimMonth = ? AND FilledOkay = 1 AND ClaimYearId = ?
                                  AND e.CrBy = ? AND FilledOkay = 1
                                  AND ClaimStatus NOT IN ('Deactivate', 'Draft') AND FilledBy > 0",
                                  [$month, $yearId, $emp]);

        $totpaid2 = $expenseConn->selectOne("SELECT SUM(VerifyTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                   WHERE ClaimMonth = ? AND ClaimYearId = ? AND e.CrBy = ?
                                   AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                   AND VerifyBy > 0", [$month, $yearId, $emp]);

        $totpaid3 = $expenseConn->selectOne("SELECT SUM(ApprTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                   WHERE ClaimMonth = ? AND ClaimYearId = ? AND e.CrBy = ?
                                   AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                   AND ApprBy > 0", [$month, $yearId, $emp]);

        $totpaid4 = $expenseConn->selectOne("SELECT SUM(FinancedTAmt) as paid FROM `y{$yearId}_expenseclaims` e
                                   WHERE ClaimMonth = ? AND ClaimYearId = ? AND e.CrBy = ?
                                   AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                                   AND FinancedBy > 0", [$month, $yearId, $emp]);

        $html .= '<tr class="totalrow">
                    <th scope="row" colspan="5" class="text-right"><b>Total&nbsp;</b></th>
                    <td class="text-right"><font style="font-size:11px;">' . ($totpaid ? $totpaid->paid : 0) . '</font></td>
                    <td class="text-right"><font style="font-size:11px;">' . ($totpaid2 ? $totpaid2->paid : 0) . '</font></td>
                    <td class="text-left"></td>
                    <td class="text-right"><font style="font-size:11px;">' . ($totpaid3 ? $totpaid3->paid : 0) . '</font></td>
                    <td class="text-left"></td>
                    <td class="text-right">
                        <input type="text" style="font-family:Times New Roman; font-size:12px; height:20px; width:95%; text-align:right;"
                               id="FinTAmt" value="' . ($totpaid4 ? $totpaid4->paid : 0) . '" readonly />
                    </td>
                    <th colspan="4"></th>
                   </tr>';

        // Query records
        if ($csts == 'Closed') {
            $stepcond = "e.ClaimAtStep >= 6";
        } else {
            $stepcond = "e.ClaimAtStep >= 5";
        }

        $q = "SELECT e.*, c.ClaimName, c.cgid FROM `y{$yearId}_expenseclaims` e, claimtype c
              WHERE c.ClaimId = e.ClaimId AND e.ClaimMonth = ?
              AND e.ClaimYearId = ? AND e.CrBy = ?
              AND e.FilledOkay = 1 AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
              AND {$stepcond} ORDER BY e.BillDate ASC";

        $seleq = $expenseConn->select($q, [$month, $yearId, $emp]);
        $i = 1;

        foreach ($seleq as $exp) {
            $html .= '<tr onclick="showdet(\'' . $exp->CrBy . '\')">
                        <th scope="row">' . $i . '</th>
                        <td>' . $exp->ExpId . '</td>
                        <td style="width:150px;"><input type="text" style="border:hidden;width:99%;" value="' . e($exp->ClaimName) . '" /></td>
                        <td>' . date("d-m-y", strtotime($exp->CrDate)) . '</td>
                        <td>' . date("d-m-y", strtotime($exp->BillDate)) . '</td>
                        <td id="' . $exp->ExpId . '__FilledTAmt">' . $exp->FilledTAmt . '</td>
                        <td id="' . $exp->ExpId . '__VerifyTAmt">' . $exp->VerifyTAmt . '</td>
                        <td><textarea style="width:' . (strlen($exp->VerifyTRemark) > 0 ? 80 : 50) . 'px;height:' . (strlen($exp->VerifyTRemark) > 0 ? 75 : 25) . 'px;" class="form-control text-left" readonly>' . e($exp->VerifyTRemark) . '</textarea></td>
                        <td id="' . $exp->ExpId . '__ApprTAmt">' . $exp->ApprTAmt . '</td>
                        <td><textarea readonly style="width:' . (strlen($exp->ApprTRemark) > 0 ? 80 : 50) . 'px;height:' . (strlen($exp->ApprTRemark) > 0 ? 75 : 25) . 'px;" class="form-control text-left">' . e($exp->ApprTRemark) . '</textarea></td>
                        <td>
                            <input style="width: 50px;" class="form-control text-right" type="text"
                                   id="' . $exp->ExpId . 'financetamt" value="' . $exp->FinancedTAmt . '"
                                   onkeypress="return isNumber(event)" onkeyup="checkrange(this,\'' . $exp->FinancedTAmt . '\')">
                        </td>
                        <td>
                            <textarea style="width: 100px;height:' . (strlen($exp->FinancedTRemark) > 0 ? 75 : 25) . 'px;" class="form-control text-left"
                                      id="' . $exp->ExpId . 'financetremark" rows="1">' . e($exp->FinancedTRemark) . '</textarea>
                        </td>';

            if ($exp->ClaimStatus == 'Financed') {
                $s = 'Ok';
            } elseif ($exp->ClaimStatus == 'Rej_Fin') {
                $s = 'Rejected';
            } else {
                $s = '';
            }

            $html .= '<td><div id="' . $exp->ExpId . 'Status" class="btn btn-sm btn- font-weight-bold">' . $s . '</div></td>
                      <td><button id="' . $exp->ExpId . 'btn" class="btn btn-sm btn-primary" onclick="showexpdet(\'' . $exp->ExpId . '\',\'' . $exp->ClaimId . '\')">Detail</button></td>';

            if ($sts == 'Open' && $exp->ClaimStatus != 'Financed' && $exp->ClaimStatus != 'Rej_Fin') {
                $html .= '<td style="text-align:center;">
                            <span id="financection' . $exp->ExpId . '" style="text-align:center;">
                                <button id="' . $exp->ExpId . 'finbtn" class="btn btn-sm btn-success pull-center" onclick="financeClaim(\'A\',\'' . $exp->ExpId . '\')">Approval</button>
                                &nbsp;&nbsp;
                                <button id="' . $exp->ExpId . 'finbtn" class="btn btn-sm btn-danger pull-center" onclick="financeClaim(\'R\',\'' . $exp->ExpId . '\')">Reject</button>
                            </span>
                         </div>';
            }

            $html .= '</tr>';
            $i++;
        }

        $html .= '</tbody></table></div></td>';

        return response($html);
    }

    private function submitrem(Request $request)
    {
        $expid = $request->get('expid');
        $remark = $request->get('remark');
        $yearId = session('year_id');

        // Use expense connection
        $expenseConn = DB::connection(session('expense_connection'));

        $expenseConn->update("UPDATE y{$yearId}_expenseclaims SET DateEntryRemark = ? WHERE ExpId = ?", [$remark, $expid]);

        return response('updated');
    }

    private function checkHoliday($billDate, $employeeID)
    {
        $billDateFormatted = (\App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($billDate) ?? date('Y-m-d', strtotime($billDate))); // BILLDATE-03 day-first
        $year = date('Y');

        // Check for Sunday
        if (date('w', strtotime($billDateFormatted)) == 0) {
            return '#fe4a495c';
        }

        // Check for holidays from HRIMS connection
        $holidays = DB::connection('hrims')->select("SELECT AttDate FROM hrm_employee_attendance
                                WHERE EmployeeID = ? AND AttValue IN ('HO', 'SL', 'PL', 'EL', 'FL', 'CL', 'ML')
                                AND Year = ?", [$employeeID, $year]);

        if (empty($holidays)) {
            $holidays = DB::connection('hrims')->select("SELECT AttDate FROM hrm_employee_attendance_$year
                                    WHERE EmployeeID = ? AND AttValue IN ('HO', 'SL', 'PL', 'EL', 'FL', 'CL', 'ML')
                                    AND Year = ?", [$employeeID, $year]);
        }

        $holidayDates = array_map(function($h) { return $h->AttDate; }, $holidays);

        if (in_array($billDateFormatted, $holidayDates)) {
            return '#d0e4ff';
        }

        return '';
    }
}
