<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Controller to fetch Holded Employees Expense Details and DA amounts.
 * Replicates the data displayed on: /expense-payments?chkval=6&v=0
 * 
 * Primary Endpoints:
 *   GET /api/expense-payments/holded-details?employee_id={employee_id}
 *   GET /api/employee/{employeeId}/holded-expense-details
 *   GET /api/expense-payments/holded-details (List all holded employees)
 */
class HoldedExpensePaymentApiController extends Controller
{
    /**
     * Get Holded Expense Details, Employee Details, and DA amounts.
     *
     * @param Request $request
     * @param int|string|null $employeeIdParam
     * @return JsonResponse
     */
    public function getHoldedExpenseDetails(Request $request, $employeeIdParam = null): JsonResponse
    {
        try {
            // 1. Resolve Employee ID
            $rawEmpId = $employeeIdParam ?: $request->input('employee_id', $request->input('empid', $request->input('EmployeeID')));
            $employeeId = ($rawEmpId && is_numeric($rawEmpId) && (int) $rawEmpId > 0) ? (int) $rawEmpId : null;

            // 2. Resolve Year ID & Month Filter
            $yearId = $request->filled('year_id') ? (int) $request->input('year_id') : (int) session('year_id', 8);
            if ($yearId <= 0) {
                $yearId = 8;
            }

            // Month: 0 or empty means all months
            $month = $request->filled('month') ? (int) $request->input('month') : (int) $request->input('v', 0);

            // Optional company filter
            $requestedCompanyId = $request->filled('company_id') ? (int) $request->input('company_id') : null;

            // Include itemized DA claims flag (defaults to false)
            $includeItemizedDA = $request->boolean('include_itemized_da', false);

            // If employee_id is specified -> Single Employee Mode
            if ($employeeId) {
                return $this->getSingleEmployeeHoldedDetails($employeeId, $yearId, $month, $requestedCompanyId, $includeItemizedDA);
            }

            // If employee_id is NOT specified -> List All Holded Employees Mode
            return $this->getAllHoldedEmployeesList($yearId, $month, $requestedCompanyId);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Error retrieving holded expense payment details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve single employee holded expense payment details with DA breakdown.
     */
    protected function getSingleEmployeeHoldedDetails(int $employeeId, int $yearId, int $month, ?int $requestedCompanyId, bool $includeItemizedDA): JsonResponse
    {
        $hrimsConn = DatabaseSwitcher::getHrimsConnection();

        // 1. Fetch Employee Profile from HRIMS
        $empRow = DB::connection($hrimsConn)->selectOne("
            SELECT 
                e.EmployeeID,
                e.EmpCode,
                e.EmpStatus,
                e.EmpType,
                e.CompanyId,
                e.Fname,
                e.Sname,
                e.Lname,
                TRIM(CONCAT(COALESCE(e.Fname,''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname,''))) AS FullName,
                e.DateOfResignation,
                e.DateOfSepration,
                p.Gender,
                p.DR,
                g.DepartmentId,
                dept.department_name,
                g.DesigId,
                desig.designation_name,
                g.GradeId,
                gr.grade_name,
                g.CostCenter AS StateId,
                st.state_name,
                g.HqId,
                vlg.city_village_name AS hq_name,
                g.ZoneId,
                Zn.zone_name,
                g.RegionId,
                Rg.region_name,
                g.TerrId,
                Tr.territory_name,
                g.BankName,
                g.AccountNo,
                g.BankIfscCode,
                g.BranchName,
                g.EmailId_Vnr AS email,
                g.MobileNo_Vnr AS mobile,
                g.DateLeaving,
                g.ReasonLeaving,
                g.RepEmployeeID AS reporting_manager_id,
                mgr.EmpCode AS reporting_manager_code,
                TRIM(CONCAT(COALESCE(mgr.Fname,''), ' ', COALESCE(mgr.Sname, ''), ' ', COALESCE(mgr.Lname,''))) AS reporting_manager_name
            FROM hrm_employee e
            LEFT JOIN hrm_employee_general g  ON e.EmployeeID = g.EmployeeID
            LEFT JOIN hrm_employee_personal p ON e.EmployeeID = p.EmployeeID
            LEFT JOIN core_departments dept    ON g.DepartmentId = dept.id
            LEFT JOIN core_designation desig   ON g.DesigId = desig.id
            LEFT JOIN core_grades gr           ON g.GradeId = gr.id
            LEFT JOIN core_states st           ON g.CostCenter = st.id
            LEFT JOIN core_city_village_by_state vlg ON g.HqId = vlg.id
            LEFT JOIN core_zones Zn            ON g.ZoneId = Zn.id
            LEFT JOIN core_regions Rg          ON g.RegionId = Rg.id
            LEFT JOIN core_territory Tr        ON g.TerrId = Tr.id
            LEFT JOIN hrm_employee mgr         ON g.RepEmployeeID = mgr.EmployeeID
            WHERE e.EmployeeID = ?
            LIMIT 1
        ", [$employeeId]);

        if (!$empRow) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => "Employee with ID {$employeeId} was not found in HRIMS records.",
            ], 404);
        }

        // Determine company & expense connection
        $companyId = $requestedCompanyId ?: (int) ($empRow->CompanyId ?? 1);
        $expenseConn = DatabaseSwitcher::getExpenseConnection($companyId);
        $companyName = DatabaseSwitcher::getCompanyName($companyId);

        // Active financial years to check (check selected year first; if none, allow fallback to active years)
        $yearsToCheck = [$yearId];
        if ($yearId === 8 && !in_array(7, $yearsToCheck)) {
            $yearsToCheck[] = 7;
        }

        $allHoldedMonths = [];
        $totalApprovedHoldedAmount = 0.0;
        $totalFinanceHoldedAmount  = 0.0;
        $totalPaymentHoldedAmount  = 0.0;
        $totalDAAmountAllMonths    = 0.0;
        $totalHoldedClaimsCount    = 0;

        foreach ($yearsToCheck as $yId) {
            $mefTable    = "y{$yId}_monthexpensefinal";
            $claimsTable = "y{$yId}_expenseclaims";

            try {
                // Query holded records (is_exported = 2) for this employee
                $query = "
                    SELECT * 
                    FROM {$mefTable}
                    WHERE EmployeeID = ?
                      AND is_exported = 2
                ";
                $bindings = [$employeeId];

                if ($month > 0) {
                    $query .= " AND Month = ?";
                    $bindings[] = $month;
                }

                $query .= " ORDER BY YearId DESC, Month DESC";

                $mefRecords = DB::connection($expenseConn)->select($query, $bindings);

                foreach ($mefRecords as $mef) {
                    $monthNum = (int) $mef->Month;
                    $monthName = date('F', mktime(0, 0, 0, $monthNum, 10));

                    // Approved date resolution matching holded.blade.php
                    $approvedDateFormatted = !empty($mef->payment_approved_date) && \App\Support\ZeroDate::has($mef->payment_approved_date) && \App\Support\ZeroDate::has($mef->payment_approved_date)
                        ? Carbon::parse($mef->payment_approved_date)->format('Y-m-d H:i:s')
                        : (!empty($mef->Fin_AppDate) && \App\Support\ZeroDate::has($mef->Fin_AppDate)
                            ? Carbon::parse($mef->Fin_AppDate)->format('Y-m-d')
                            : (!empty($mef->Approved_Date) && \App\Support\ZeroDate::has($mef->Approved_Date)
                                ? Carbon::parse($mef->Approved_Date)->format('Y-m-d')
                                : null));

                    // Hold date resolution
                    $holdDateFormatted = !empty($mef->focus_exported_date) && \App\Support\ZeroDate::has($mef->focus_exported_date) && \App\Support\ZeroDate::has($mef->focus_exported_date)
                        ? Carbon::parse($mef->focus_exported_date)->format('Y-m-d H:i:s')
                        : null;

                    // Finance amount / Approved amount / Payment amount
                    $approvedAmt = (float) ($mef->Approved_Amount ?: 0);
                    $financeAmt  = (float) ($mef->Finance_Amount ?: 0);
                    $paymentAmt  = (float) ($mef->payment_amount ?: $mef->actual_expense_amount ?: $financeAmt);

                    // 2. Fetch DA amounts and claims breakdown for this month
                    $daSummaryRow = DB::connection($expenseConn)->selectOne("
                        SELECT 
                            SUM(CASE WHEN ec.ClaimId IN (19, 20) THEN COALESCE(ec.FinancedTAmt, 0) ELSE 0 END) AS TotalDAFinancedAmount,
                            SUM(CASE WHEN ec.ClaimId IN (19, 20) THEN COALESCE(ec.ApprTAmt, 0) ELSE 0 END) AS TotalDAApprovedAmount,
                            SUM(CASE WHEN ec.ClaimId IN (19, 20) THEN COALESCE(ec.FilledTAmt, 0) ELSE 0 END) AS TotalDAClaimedAmount,
                            SUM(CASE WHEN ec.ClaimId = 19 THEN COALESCE(ec.FinancedTAmt, ec.ApprTAmt, 0) ELSE 0 END) AS DAHQAmount,
                            SUM(CASE WHEN ec.ClaimId = 20 THEN COALESCE(ec.FinancedTAmt, ec.ApprTAmt, 0) ELSE 0 END) AS DAOutstationAmount,
                            COUNT(CASE WHEN ec.ClaimId IN (19, 20) THEN 1 END) AS DACount,
                            SUM(CASE WHEN ct.cgId = 1 AND ec.ClaimId NOT IN (19, 20) THEN COALESCE(ec.FinancedTAmt, ec.ApprTAmt, 0) ELSE 0 END) AS NonDAExpenseAmount,
                            SUM(CASE WHEN ct.cgId = 7 THEN COALESCE(ec.FinancedTAmt, ec.ApprTAmt, 0) ELSE 0 END) AS ActivityAmount
                        FROM {$claimsTable} ec
                        LEFT JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                        WHERE ec.ClaimMonth = ?
                          AND ec.CrBy = ?
                          AND ec.ClaimStatus != 'Deactivate'
                          AND ec.FilledOkay = 1
                    ", [$monthNum, $employeeId]);

                    $totalDAFinanced = (float) ($daSummaryRow->TotalDAFinancedAmount ?? 0);
                    $totalDAApproved = (float) ($daSummaryRow->TotalDAApprovedAmount ?? 0);
                    $totalDAClaimed  = (float) ($daSummaryRow->TotalDAClaimedAmount ?? 0);
                    $effectiveDA     = $totalDAFinanced > 0 ? $totalDAFinanced : $totalDAApproved;

                    $daDetails = [
                        'total_da_amount'          => round($effectiveDA, 2),
                        'total_da_financed_amount' => round($totalDAFinanced, 2),
                        'total_da_approved_amount' => round($totalDAApproved, 2),
                        'total_da_claimed_amount'  => round($totalDAClaimed, 2),
                        'da_hq_amount'             => round((float) ($daSummaryRow->DAHQAmount ?? 0), 2),
                        'da_outstation_amount'     => round((float) ($daSummaryRow->DAOutstationAmount ?? 0), 2),
                        'da_claims_count'          => (int) ($daSummaryRow->DACount ?? 0),
                        'non_da_expense_amount'    => round((float) ($daSummaryRow->NonDAExpenseAmount ?? 0), 2),
                        'activity_amount'          => round((float) ($daSummaryRow->ActivityAmount ?? 0), 2),
                        'net_expense_without_da'   => round(max(0, $financeAmt - $effectiveDA), 2),
                    ];

                    // 3. Itemized DA claims list if requested
                    $itemizedDAClaims = [];
                    if ($includeItemizedDA && ($daSummaryRow->DACount ?? 0) > 0) {
                        $claimsRows = DB::connection($expenseConn)->select("
                            SELECT 
                                ec.ExpId,
                                ec.ClaimId,
                                ct.ClaimName,
                                ec.BillDate,
                                ec.FilledTAmt,
                                ec.VerifyTAmt,
                                ec.ApprTAmt,
                                ec.FinancedTAmt,
                                ec.Remark,
                                ec.ClaimStatus
                            FROM {$claimsTable} ec
                            LEFT JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                            WHERE ec.ClaimMonth = ?
                              AND ec.CrBy = ?
                              AND ec.ClaimId IN (19, 20)
                              AND ec.ClaimStatus != 'Deactivate'
                            ORDER BY ec.BillDate ASC, ec.ExpId ASC
                        ", [$monthNum, $employeeId]);

                        foreach ($claimsRows as $cr) {
                            $itemizedDAClaims[] = [
                                'exp_id'          => $cr->ExpId,
                                'claim_id'        => $cr->ClaimId,
                                'claim_type'      => $cr->ClaimName ?: ($cr->ClaimId == 19 ? 'DA@HQ' : 'DA'),
                                'claim_date'      => $cr->BillDate,
                                'claimed_amount'  => (float) ($cr->FilledTAmt ?? 0),
                                'verified_amount' => (float) ($cr->VerifyTAmt ?? 0),
                                'approved_amount' => (float) ($cr->ApprTAmt ?? 0),
                                'financed_amount' => (float) ($cr->FinancedTAmt ?? 0),
                                'remark'          => $cr->Remark ?: null,
                                'status'          => $cr->ClaimStatus,
                            ];
                        }
                    }

                    // Build Month Details
                    $monthData = [
                        'monthexpensefinal_id'  => (int) $mef->id,
                        'month'                 => $monthNum,
                        'month_name'            => $monthName,
                        'year_id'               => (int) $mef->YearId,
                        'doc_no'                => $mef->DocNo ?: null,
                        'total_claims_count'    => (int) ($mef->Total_Claim ?? 0),
                        'claim_amount'          => round((float) ($mef->Claim_Amount ?? 0), 2),
                        'verified_amount'       => round((float) ($mef->Verified_Amount ?? 0), 2),
                        'approved_amount'       => round($approvedAmt, 2),
                        'finance_amount'        => round($financeAmt, 2),
                        'payment_amount'        => round($paymentAmt, 2),
                        'actual_expense_amount' => round((float) ($mef->actual_expense_amount ?? 0), 2),
                        'total_adjustment'      => round((float) ($mef->total_adjustment ?? 0), 2),
                        'payment_status'        => $mef->payment_status ?: 'Approved',
                        'is_exported'           => (int) $mef->is_exported,
                        'hold_status'           => 'HOLD',
                        'hold_remark'           => $mef->hold_remark ?: null,
                        'hold_date'             => $holdDateFormatted,
                        'payment_approved_date' => $approvedDateFormatted,
                        'da_amount_details'     => $daDetails,
                    ];

                    if ($includeItemizedDA) {
                        $monthData['itemized_da_claims'] = $itemizedDAClaims;
                    }

                    $allHoldedMonths[] = $monthData;

                    $totalApprovedHoldedAmount += $approvedAmt;
                    $totalFinanceHoldedAmount  += $financeAmt;
                    $totalPaymentHoldedAmount  += $paymentAmt;
                    $totalDAAmountAllMonths    += $effectiveDA;
                    $totalHoldedClaimsCount    += (int) ($mef->Total_Claim ?? 0);
                }
            } catch (\Throwable $e) {
                // Continue if table doesn't exist for this FY
            }
        }

        return response()->json([
            'success'          => true,
            'code'             => 200,
            'message'          => count($allHoldedMonths) > 0 
                ? 'Holded expense payment details retrieved successfully.' 
                : 'No holded expense records found for this employee.',
            'employee_details' => [
                'employee_id'            => (int) $empRow->EmployeeID,
                'emp_code'               => $empRow->EmpCode,
                'full_name'              => $empRow->FullName,
                'status'                 => $empRow->EmpStatus,
                'status_label'           => in_array($empRow->EmpStatus, ['D', 'R']) ? 'Resigned / Deactivated' : 'Active',
                'is_resigned'            => in_array($empRow->EmpStatus, ['D', 'R']) || (!empty($empRow->DateOfResignation) && \App\Support\ZeroDate::has($empRow->DateOfResignation)),
                'date_of_resignation'    => ($empRow->DateOfResignation && \App\Support\ZeroDate::has($empRow->DateOfResignation)) ? $empRow->DateOfResignation : null,
                'date_of_separation'     => ($empRow->DateOfSepration && \App\Support\ZeroDate::has($empRow->DateOfSepration)) ? $empRow->DateOfSepration : null,
                'date_leaving'           => ($empRow->DateLeaving && \App\Support\ZeroDate::has($empRow->DateLeaving)) ? $empRow->DateLeaving : null,
                'reason_leaving'         => $empRow->ReasonLeaving ?: null,
                'department_id'          => $empRow->DepartmentId,
                'department_name'        => $empRow->department_name ?: 'General',
                'designation_id'         => $empRow->DesigId,
                'designation_name'       => $empRow->designation_name ?: 'N/A',
                'grade_name'             => $empRow->grade_name ?: 'N/A',
                'hq_name'                => $empRow->hq_name ?: 'N/A',
                'company_id'             => $companyId,
                'company_name'           => $companyName,
                'email'                  => $empRow->email,
                'mobile'                 => $empRow->mobile,
                'bank_details'           => [
                    'bank_name'          => $empRow->BankName,
                    'account_no'         => $empRow->AccountNo,
                    'ifsc_code'          => $empRow->BankIfscCode,
                    'branch_name'        => $empRow->BranchName,
                ],
                'reporting_manager_name' => $empRow->reporting_manager_name ?: null,
            ],
            'summary'          => [
                'total_holded_months_count'  => count($allHoldedMonths),
                'total_holded_claims_count'  => $totalHoldedClaimsCount,
                'total_holded_approved_amt'  => round($totalApprovedHoldedAmount, 2),
                'total_holded_finance_amt'   => round($totalFinanceHoldedAmount, 2),
                'total_holded_payable_amt'   => round($totalPaymentHoldedAmount, 2),
                'total_holded_da_amount'     => round($totalDAAmountAllMonths, 2),
                'net_expense_without_da'     => round(max(0, $totalFinanceHoldedAmount - $totalDAAmountAllMonths), 2),
            ],
            'holded_months'    => $allHoldedMonths,
        ]);
    }

    /**
     * Retrieve list of all employees currently on Hold across all companies or filtered company.
     */
    protected function getAllHoldedEmployeesList(int $yearId, int $month, ?int $requestedCompanyId): JsonResponse
    {
        $companies = $requestedCompanyId ? [$requestedCompanyId] : [1, 2, 3, 4, 11];
        $allHoldedList = [];

        foreach ($companies as $cId) {
            $expenseConn = DatabaseSwitcher::getExpenseConnection($cId);
            $companyName = DatabaseSwitcher::getCompanyName($cId);
            $mefTable    = "y{$yearId}_monthexpensefinal";
            $claimsTable = "y{$yearId}_expenseclaims";

            try {
                $query = "
                    SELECT 
                        mef.id, mef.EmployeeID, mef.Month, mef.YearId, mef.Total_Claim, 
                        mef.Finance_Amount, mef.Approved_Amount, mef.payment_amount, mef.actual_expense_amount,
                        mef.payment_status, mef.is_exported, mef.hold_remark, mef.focus_exported_date, mef.payment_approved_date,
                        e.EmpCode, e.Fname, e.Sname, e.Lname, e.EmpStatus, e.DateOfResignation, e.DateOfSepration,
                        d.department_name, gr.grade_name
                    FROM {$mefTable} mef
                    LEFT JOIN hrims.hrm_employee e ON mef.EmployeeID = e.EmployeeID
                    LEFT JOIN hrims.hrm_employee_general eg ON e.EmployeeID = eg.EmployeeID
                    LEFT JOIN hrims.core_departments d ON eg.DepartmentId = d.id
                    LEFT JOIN hrims.core_grades gr ON eg.GradeId = gr.id
                    WHERE mef.is_exported = 2
                ";
                $bindings = [];

                if ($month > 0) {
                    $query .= " AND mef.Month = ?";
                    $bindings[] = $month;
                }

                $query .= " ORDER BY mef.payment_approved_date DESC, mef.Month ASC";

                $rows = DB::connection($expenseConn)->select($query, $bindings);

                foreach ($rows as $r) {
                    $monthNum = (int) $r->Month;

                    // Query DA amount for this month and employee
                    $daRow = DB::connection($expenseConn)->selectOne("
                        SELECT 
                            SUM(CASE WHEN ec.ClaimId IN (19, 20) THEN COALESCE(ec.FinancedTAmt, ec.ApprTAmt, 0) ELSE 0 END) AS DAAmount,
                            COUNT(CASE WHEN ec.ClaimId IN (19, 20) THEN 1 END) AS DACount
                        FROM {$claimsTable} ec
                        WHERE ec.ClaimMonth = ?
                          AND ec.CrBy = ?
                          AND ec.ClaimStatus != 'Deactivate'
                          AND ec.FilledOkay = 1
                    ", [$monthNum, $r->EmployeeID]);

                    $daAmount = round((float) ($daRow->DAAmount ?? 0), 2);
                    $financeAmount = (float) ($r->Finance_Amount ?: $r->Approved_Amount ?: 0);
                    $payableAmount = (float) ($r->payment_amount ?: $r->actual_expense_amount ?: $financeAmount);

                    $allHoldedList[] = [
                        'monthexpensefinal_id' => (int) $r->id,
                        'employee_id'          => (int) $r->EmployeeID,
                        'emp_code'             => $r->EmpCode,
                        'employee_name'        => trim("{$r->Fname} {$r->Sname} {$r->Lname}"),
                        'emp_status'           => $r->EmpStatus,
                        'is_resigned'          => in_array($r->EmpStatus, ['D', 'R']) || (!empty($r->DateOfResignation) && \App\Support\ZeroDate::has($r->DateOfResignation)),
                        'date_of_resignation'  => ($r->DateOfResignation && \App\Support\ZeroDate::has($r->DateOfResignation)) ? $r->DateOfResignation : null,
                        'department'           => $r->department_name ?: 'General',
                        'grade'                => $r->grade_name ?: 'N/A',
                        'company_id'           => $cId,
                        'company_name'         => $companyName,
                        'month'                => $monthNum,
                        'month_name'           => date('F', mktime(0, 0, 0, $monthNum, 10)),
                        'year_id'              => (int) $r->YearId,
                        'total_claims'         => (int) ($r->Total_Claim ?? 0),
                        'finance_amount'       => round($financeAmount, 2),
                        'payable_amount'       => round($payableAmount, 2),
                        'da_amount'            => $daAmount,
                        'net_without_da'       => round(max(0, $financeAmount - $daAmount), 2),
                        'da_claims_count'      => (int) ($daRow->DACount ?? 0),
                        'hold_status'          => 'HOLD',
                        'hold_remark'          => $r->hold_remark ?: null,
                        'hold_date'            => (!empty($r->focus_exported_date) && \App\Support\ZeroDate::has($r->focus_exported_date)) ? $r->focus_exported_date : null,
                        'payment_status'       => $r->payment_status ?: 'Approved',
                    ];
                }
            } catch (\Throwable $e) {
                // Skip if connection or table error
            }
        }

        return response()->json([
            'success'       => true,
            'code'          => 200,
            'total_records' => count($allHoldedList),
            'year_id'       => $yearId,
            'month'         => $month > 0 ? $month : 'All',
            'records'       => $allHoldedList,
        ]);
    }
}
