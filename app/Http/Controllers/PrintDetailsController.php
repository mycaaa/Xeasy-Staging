<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintDetailsController extends Controller
{
    public function printDetails(Request $request)
    {
        $employeeId = (int) $request->e;
        $month      = (int) $request->m;
        $yearId     = (int) $request->y;
        $format     = (int) $request->n;

        /* ================= EMPLOYEE FULL DETAILS ================= */
        $employee = DB::connection('hrims')->selectOne("
            SELECT e.EmployeeID, e.EmpCode,
            CONCAT(e.Fname,' ',e.Sname,' ',e.Lname) as name,
            desig.designation_name,
            gr.grade_name,
            dept.department_name,
            ver.vertical_name,
            st.state_name,
            vlg.city_village_name,
            tr.territory_name
            FROM hrm_employee_general g
            LEFT JOIN hrm_employee e ON g.EmployeeID=e.EmployeeID
            LEFT JOIN core_departments dept ON g.DepartmentId=dept.id
            LEFT JOIN core_verticals ver ON g.EmpVertical=ver.id
            LEFT JOIN core_designation desig ON g.DesigId=desig.id
            LEFT JOIN core_grades gr ON g.GradeId=gr.id
            LEFT JOIN core_states st ON g.CostCenter=st.id
            LEFT JOIN core_city_village_by_state vlg ON g.HqId=vlg.id
            LEFT JOIN core_territory tr ON g.TerrId=tr.id
            WHERE e.EmployeeID = ?
        ", [$employeeId]);

        if (!$employee) {
            abort(404, 'Employee not found');
        }

        /* Normalize fields for Blade */
        $employee->code        = $employee->EmpCode;
        $employee->designation = $employee->designation_name ?? '';
        $employee->grade       = $employee->grade_name ?? '';
        $employee->hq          = $employee->territory_name ?: $employee->city_village_name;
        $employee->state       = $employee->state_name ?? '';

        /* ================= MONTH FINAL SUMMARY ================= */
        $monthFinal = DB::selectOne("
            SELECT * FROM y{$yearId}_monthexpensefinal
            WHERE YearId=? AND Month=? AND EmployeeID=?
        ", [$yearId, $month, $employeeId]);

        /* ================= TOTALS ================= */
        $grandTotal    = (float) ($monthFinal->Finance_Amount ?? 0);
        $totalClaimed  = (float) ($monthFinal->Claim_Amount ?? 0);
        $totalVerified = (float) ($monthFinal->Verified_Amount ?? 0);
        $totalApproved = (float) ($monthFinal->Approved_Amount ?? 0);

        /* ================= CLAIM COUNT ================= */
        $claimCount = DB::selectOne("
            SELECT COUNT(*) as TotClaim
            FROM y{$yearId}_expenseclaims
            WHERE ClaimAtStep=6
            AND CrBy=? AND ClaimYearId=? AND ClaimMonth=?
            AND FilledOkay=1 AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft'
        ", [$employeeId, $yearId, $month]);

        /* ================= FORMAT 1 CLAIM LIST ================= */
        $claimDetails = [];
        if ($format == 1) {
            $claimDetails = DB::select("
                SELECT e.*, c.ClaimName
                FROM y{$yearId}_expenseclaims e
                JOIN claimtype c ON c.ClaimId=e.ClaimId
                WHERE e.CrBy=? AND e.ClaimYearId=? AND e.ClaimMonth=?
                AND e.ClaimAtStep>=6
                AND e.FilledOkay=1
                AND e.ClaimStatus!='Deactivate'
                AND e.ClaimStatus!='Draft'
                ORDER BY e.BillDate ASC
            ", [$employeeId, $yearId, $month]);
        }

        /* ================= FORMAT 2 GROUPED TRAVEL DATA ================= */
        $travelRows = [];
        if ($format == 2) {
           $travelRows = DB::select("
    SELECT 
        BillDate,
        MAX(Remark) as Remark,
        SUM(FinancedTAmt) as total
    FROM y{$yearId}_expenseclaims
    WHERE ClaimAtStep=6
    AND CrBy=? 
    AND ClaimYearId=? 
    AND ClaimMonth=?
    AND FilledOkay=1 
    AND ClaimStatus!='Deactivate' 
    AND ClaimStatus!='Draft'
    GROUP BY BillDate
    ORDER BY BillDate ASC
", [$employeeId, $yearId, $month]);
        }

        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        return view('expense-payments.prints.expense-details', [
            'employee'      => $employee,
            'monthFinal'    => $monthFinal,
            'claimCount'    => $claimCount->TotClaim ?? 0,
            'claimDetails'  => $claimDetails,
            'activities'    => $claimDetails, // alias for blade compatibility
            'travelRows'    => $travelRows,
            'grandTotal'    => $grandTotal,
            'totalClaimed'  => $totalClaimed,
            'totalVerified' => $totalVerified,
            'totalApproved' => $totalApproved,
            'month'         => $month,
            'monthName'     => $monthName,
            'yearId'        => $yearId,
            'format'        => $format
        ]);
    }
}
