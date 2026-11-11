<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintDetailsEmpController extends Controller
{
    public function generate(Request $request)
    {
        $empid = (int) $request->input('e');
        $yearId = (int) ($request->input('y') ?: session('year_id'));
        $month = (int) $request->input('m');
        // UAT-03: without these the queries ran against "y0_..." tables and returned a 500.
        if ($empid <= 0 || $yearId <= 0 || $month < 1 || $month > 12) {
            abort(400, 'Employee (e), year (y) and month (m) are required.');
        }
        $nn = (int) $request->input('nn', 2);  // 1=all except deactivate, 2=FilledOkay=1
        $companyId = (int) ($request->input('c') ?: session('company_id', 1));

        // ── condition mirrors old $sqry / $ssqry ──
        if ($nn == 1) {
            $sqry = 'FilledOkay != 2';
            $ssqry = 'FilledOkay != 2';
        } else {
            $sqry = 'FilledOkay = 1';
            $ssqry = 'clm.FilledOkay = 1';
        }

        $expConn = DatabaseSwitcher::getExpenseConnection($companyId);
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();
        $expTable = "y{$yearId}_expenseclaims";

        // ── month master ──
        $mlist = DB::connection($expConn)->selectOne(
            "SELECT * FROM y{$yearId}_monthexpensefinal
             WHERE YearId=? AND Month=? AND EmployeeID=?",
            [$yearId, $month, $empid]
        );

        if (!$mlist) {
            $claimTotal = (float) DB::connection($expConn)->table($expTable)
                ->where('CrBy', $empid)
                ->where('ClaimYearId', $yearId)
                ->where('ClaimMonth', $month)
                ->whereNotIn('ClaimStatus', ['Draft', 'Deactivate'])
                ->sum('FilledTAmt');

            $mlist = (object) [
                'Claim_Amount' => $claimTotal,
                'DateOfSubmit' => null,
            ];
        }

        // ── financial year label ──
        $fyRow = DB::connection($expConn)->selectOne(
            'SELECT Year FROM financialyear WHERE YearId=?', [$yearId]
        );
        $fyLabel = $fyRow->Year ?? $yearId;

        // ── employee details ──
        $emp = DB::connection('hrims')->selectOne("
            SELECT e.EmployeeID, e.EmpCode, e.EmpCode_New,
                   CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname) AS Name,
                   p.Gender, p.Married, p.DR,
                   g.DepartmentId, g.EmpVertical, g.TerrId, g.HqId,
                   fun.function_name, ver.vertical_name,
                   dept.department_name,
                   desig.designation_name,
                   gr.grade_name,
                   st.state_name,
                   vlg.city_village_name,
                   Zn.zone_name, Rg.region_name, Tr.territory_name
            FROM {$hrimsDb}.hrm_employee e
            LEFT JOIN {$hrimsDb}.hrm_employee_general g  ON e.EmployeeID = g.EmployeeID
            LEFT JOIN {$hrimsDb}.hrm_employee_personal p ON e.EmployeeID = p.EmployeeID
            LEFT JOIN {$hrimsDb}.core_functions fun       ON g.EmpFunction = fun.id
            LEFT JOIN {$hrimsDb}.core_verticals ver       ON g.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_departments dept    ON g.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_designation desig   ON g.DesigId = desig.id
            LEFT JOIN {$hrimsDb}.core_grades gr           ON g.GradeId = gr.id
            LEFT JOIN {$hrimsDb}.core_states st           ON g.CostCenter = st.id
            LEFT JOIN {$hrimsDb}.core_city_village_by_state vlg ON g.HqId = vlg.id
            LEFT JOIN {$hrimsDb}.core_zones Zn            ON g.ZoneId = Zn.id
            LEFT JOIN {$hrimsDb}.core_regions Rg          ON g.RegionId = Rg.id
            LEFT JOIN {$hrimsDb}.core_territory Tr        ON g.TerrId = Tr.id
            WHERE e.EmployeeID = ?
        ", [$empid]);

        // greeting
        $greeting = '';
        if (!empty($emp->DR) && $emp->DR === 'Y')
            $greeting = 'Dr.';
        elseif (!empty($emp->Gender) && $emp->Gender === 'M')
            $greeting = 'Mr.';
        elseif (!empty($emp->Gender) && $emp->Gender === 'F' && !empty($emp->Married) && $emp->Married === 'Y')
            $greeting = 'Mrs.';
        elseif (!empty($emp->Gender) && $emp->Gender === 'F')
            $greeting = 'Miss.';

        // emp code padding
        $ec = $emp->EmpCode_New ?? '';
        $empDisplay = $ec . ' - ' . ($emp->Name ?? '');

        // ── odometer exclusion (dept 2/3, vertical 1/2) ──
        $deptId = (int) ($emp->DepartmentId ?? 0);
        $empVertical = (int) ($emp->EmpVertical ?? 0);
        $odoExcludeIds = [];

        if (in_array($deptId, [2, 3]) && in_array($empVertical, [1, 2])) {
            $kmRows = DB::connection($expConn)->select(
                "SELECT ExpId, TotKm, App_Odometer
                 FROM `{$expTable}`
                 WHERE CrBy=? AND ClaimMonth=? AND ClaimId=7
                   AND WType IN (2,4)
                   AND ClaimStatus NOT IN ('Draft','Deactivate')
                 ORDER BY ExpId ASC",
                [$empid, $month]
            );
            $cumKm = 0;
            $crossed = false;
            foreach ($kmRows as $kr) {
                $cumKm += (int) $kr->TotKm;
                if ($cumKm > 1200)
                    $crossed = true;
                if ($crossed && in_array($kr->App_Odometer, ['N', 'R'])) {
                    $odoExcludeIds[] = (int) $kr->ExpId;
                }
            }
        }
        $odoCondition = !empty($odoExcludeIds)
            ? ' AND ExpId NOT IN (' . implode(',', $odoExcludeIds) . ')'
            : '';

        // ── claim groups ──
        $groups = DB::connection($expConn)->select(
            'SELECT * FROM claimgroup ORDER BY cgId ASC'
        );

        $sections = [];

        foreach ($groups as $grp) {
            $cgId = (int) $grp->cgId;

            // build claimId list for this group
            $claimTypes = DB::connection($expConn)->select(
                "SELECT ClaimId, ClaimCode, ClaimName
                 FROM claimtype
                 WHERE cgId=? AND (ClaimStatus='A' OR ClaimStatus='D' OR ClaimStatus='B')
                 ORDER BY ClaimCode ASC",
                [$cgId]
            );
            if (empty($claimTypes))
                continue;

            $claimIds = collect($claimTypes)->pluck('ClaimId')->toArray();
            $claimIdStr = implode(',', $claimIds);

            // check if any rows exist
            $rowCount = DB::connection($expConn)->selectOne(
                "SELECT COUNT(*) as cnt
                 FROM `{$expTable}`
                 WHERE ClaimId IN ({$claimIdStr})
                   AND {$sqry}
                   AND ClaimStatus NOT IN ('Deactivate','Draft')
                   AND FilledBy > 0
                   AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?",
                [$empid, $yearId, $month]
            );
            if (!$rowCount || $rowCount->cnt == 0)
                continue;

            // ── date-level rows ──
            $dateRows = DB::connection($expConn)->select(
                "SELECT BillDate,
                        MAX(Remark) as Remark,
                        MAX(DateEntryRemark) as DateEntryRemark,
                        SUM(FinancedTAmt) as Tot2Amt,
                        SUM(FilledTAmt)   as Tot2Fill
                 FROM `{$expTable}`
                 WHERE FilledBy > 0
                   AND {$sqry}
                   AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?
                   AND ({$companyId}=3 OR (ClaimId=7 AND v_verify='Y') OR ClaimId!=7)
                   AND ClaimId IN ({$claimIdStr})
                   AND (ClaimId!=7 OR (ClaimId=7 {$odoCondition}))
                   AND ClaimStatus NOT IN ('Deactivate','Draft')
                   AND (Backdate_Odometer_Status IS NULL
                        OR Backdate_Odometer_Status=''
                        OR (Backdate_Odometer_Status='A' AND v_verify='Y'))
                 GROUP BY BillDate
                 ORDER BY BillDate ASC",
                [$empid, $yearId, $month]
            );

            // ── visible claim-type columns (skip 7 — handled as 2W/4W) ──
            $visibleCols = [];
            foreach ($claimTypes as $ct) {
                if ((int) $ct->ClaimId === 7)
                    continue;
                $chk = DB::connection($expConn)->selectOne(
                    "SELECT COUNT(*) as cnt FROM `{$expTable}`
                     WHERE ClaimId=? AND {$sqry}
                       AND ClaimStatus NOT IN ('Deactivate','Draft')
                       AND FilledBy>0
                       AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?",
                    [$ct->ClaimId, $empid, $yearId, $month]
                );
                if ($chk && $chk->cnt > 0) {
                    $visibleCols[] = $ct;
                }
            }

            $has7 = collect($claimTypes)->contains('ClaimId', 7);

            // ── per-date detail ──
            $rows = [];
            $totKmAmt = 0;
            $colTotals = [];

            foreach ($dateRows as $dr) {
                $billDate = $dr->BillDate;
                $rowData = ['date' => $billDate, 'remark' => '', 'km' => null, 'twoW' => 0, 'fourW' => 0, 'cols' => [], 'rowTotal' => 0];

                // remark
                $remark = $dr->Remark ?? '';
                if ($remark === '') {
                    $rmkRow = DB::connection($expConn)->selectOne(
                        "SELECT DateEntryRemark FROM `{$expTable}`
                         WHERE FilledBy>0 AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?
                           AND BillDate=? AND Remark=''
                           AND ClaimStatus NOT IN ('Deactivate','Draft')
                         LIMIT 1",
                        [$empid, $yearId, $month, $billDate]
                    );
                    $remark = $rmkRow->DateEntryRemark ?? '';
                }
                $rowData['remark'] = mb_substr(ucfirst(strtolower($remark)), 0, 45);

                // KM (cgId==1)
                if ($cgId === 1 && $has7) {
                    $kmRow = DB::connection($expConn)->selectOne(
                        "SELECT ExpId, odomtr_opening, odomtr_closing, TotKm, v_verify
                         FROM `{$expTable}`
                         WHERE BillDate=? AND {$sqry}
                           AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?
                           AND ClaimId=7
                           AND ClaimStatus NOT IN ('Deactivate','Draft')
                           {$odoCondition}
                         LIMIT 1",
                        [$billDate, $empid, $yearId, $month]
                    );
                    if ($kmRow && ($kmRow->v_verify === 'Y' || $companyId == 3)) {
                        $rowData['km'] = [
                            'from' => $kmRow->odomtr_opening,
                            'to' => $kmRow->odomtr_closing,
                            'tot' => $kmRow->TotKm,
                        ];
                        $totKmAmt += (float) $kmRow->TotKm;
                    }

                    // 2W
                    $r2 = DB::connection($expConn)->selectOne(
                        "SELECT SUM(FilledTAmt) as amt FROM `{$expTable}` clm
                         WHERE clm.ClaimId=7 AND clm.WType=2
                           AND clm.BillDate=? AND {$ssqry}
                           AND clm.ClaimStatus NOT IN ('Deactivate','Draft')
                           AND clm.FilledBy>0 AND clm.c_verify='Y'
                           AND clm.CrBy=? AND clm.ClaimYearId=? AND clm.ClaimMonth=?
                           AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status='A')
                           {$odoCondition}",
                        [$billDate, $empid, $yearId, $month]
                    );
                    $rowData['twoW'] = (float) ($r2->amt ?? 0);

                    // 4W
                    $r4 = DB::connection($expConn)->selectOne(
                        "SELECT SUM(FilledTAmt) as amt FROM `{$expTable}` clm
                         WHERE clm.ClaimId=7 AND clm.WType=4
                           AND clm.BillDate=? AND {$ssqry}
                           AND clm.ClaimStatus NOT IN ('Deactivate','Draft')
                           AND clm.FilledBy>0 AND clm.c_verify='Y'
                           AND clm.CrBy=? AND clm.ClaimYearId=? AND clm.ClaimMonth=?
                           AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status='A')
                           {$odoCondition}",
                        [$billDate, $empid, $yearId, $month]
                    );
                    $rowData['fourW'] = (float) ($r4->amt ?? 0);
                }

                // other columns
                foreach ($visibleCols as $ct) {
                    $cid = (int) $ct->ClaimId;
                    $amt = DB::connection($expConn)->selectOne(
                        "SELECT SUM(FilledTAmt) as amt FROM `{$expTable}`
                         WHERE ClaimId=? AND BillDate=? AND {$sqry}
                           AND ClaimStatus NOT IN ('Deactivate','Draft')
                           AND FilledBy>0
                           AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status='A')
                           AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?",
                        [$cid, $billDate, $empid, $yearId, $month]
                    );
                    $v = (float) ($amt->amt ?? 0);
                    $rowData['cols'][$cid] = $v;
                    $colTotals[$cid] = ($colTotals[$cid] ?? 0) + $v;
                }

                $rowData['rowTotal'] = (float) ($dr->Tot2Fill > 0 ? $dr->Tot2Fill : $dr->Tot2Amt);
                $rows[] = $rowData;
            }

            // ── column grand totals for 2W/4W ──
            $grand2W = 0;
            $grand4W = 0;
            if ($cgId === 1 && $has7) {
                $g2 = DB::connection($expConn)->selectOne(
                    "SELECT SUM(FilledTAmt) as amt FROM `{$expTable}` clm
                     WHERE clm.ClaimId=7 AND clm.WType=2 AND {$ssqry}
                       AND clm.c_verify='Y'
                       AND clm.ClaimStatus NOT IN ('Deactivate','Draft')
                       AND clm.FilledBy>0
                       AND clm.CrBy=? AND clm.ClaimYearId=? AND clm.ClaimMonth=?
                       AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status='A')
                       {$odoCondition}",
                    [$empid, $yearId, $month]
                );
                $grand2W = (float) ($g2->amt ?? 0);

                $g4 = DB::connection($expConn)->selectOne(
                    "SELECT SUM(FilledTAmt) as amt FROM `{$expTable}` clm
                     WHERE clm.ClaimId=7 AND clm.WType=4 AND {$ssqry}
                       AND clm.c_verify='Y'
                       AND clm.ClaimStatus NOT IN ('Deactivate','Draft')
                       AND clm.FilledBy>0
                       AND clm.CrBy=? AND clm.ClaimYearId=? AND clm.ClaimMonth=?
                       AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status='A')
                       {$odoCondition}",
                    [$empid, $yearId, $month]
                );
                $grand4W = (float) ($g4->amt ?? 0);
            }

            // ── section grand total ──
            $grandTotal = DB::connection($expConn)->selectOne(
                "SELECT SUM(FilledTAmt) as amt FROM `{$expTable}`
                 WHERE {$sqry}
                   AND ClaimStatus NOT IN ('Deactivate','Draft')
                   AND FilledBy>0
                   AND (Backdate_Odometer_Status IS NULL OR Backdate_Odometer_Status='A')
                   AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?
                   AND ClaimId IN ({$claimIdStr})
                   AND ({$companyId}=3 OR (ClaimId=7 AND c_verify='Y') OR ClaimId!=7)
                   {$odoCondition}",
                [$empid, $yearId, $month]
            );

            $sections[] = [
                'cgId' => $cgId,
                'cgName' => strtoupper($grp->cgName . ' (' . $grp->cgCode . ')'),
                'isKm' => $cgId === 1,
                'has7' => $has7,
                'visibleCols' => $visibleCols,
                'rows' => $rows,
                'totKmAmt' => $totKmAmt,
                'grand2W' => $grand2W,
                'grand4W' => $grand4W,
                'colTotals' => $colTotals,
                'grandTotal' => (float) ($grandTotal->amt ?? 0),
            ];
        }

        // ── render & stream PDF ──
        $monthName = Carbon::createFromDate(null, $month, 1)->format('F');
        $filename = "Expense_{$ec}_{$monthName}_{$fyLabel}.pdf";

        $html = view('pdf.printdetailsemp', compact(
            'mlist', 'emp', 'empDisplay', 'greeting',
            'fyLabel', 'monthName', 'sections', 'companyId', 'nn'
        ))->render();

        if (class_exists(\Mpdf\Mpdf::class)) {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4'
            ]);
            $mpdf->WriteHTML($html);
            return response($mpdf->Output($filename, 'I'))
                ->header('Content-Type', 'application/pdf');
        }

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            return $pdf->stream($filename);
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return response($dompdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "inline; filename=\"{$filename}\"");
        }

        return response($html)->header('Content-Type', 'text/html');
    }
}
