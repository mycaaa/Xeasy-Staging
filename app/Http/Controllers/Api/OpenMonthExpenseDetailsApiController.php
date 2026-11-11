<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use App\Services\S3Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * API Controller to fetch Open Month Expense details,
 * Employee details, and Claim details by Employee ID for Open Months only.
 *
 * Checks 3 continuous months (Anchor month, 1 month ago, 2 months ago)
 * across financial year table boundaries when year_id is not specified.
 *
 * GET /api/open-month-expense-details?employee_id=1126
 * GET /api/open-month-expense-details?employee_id=1126&month=5
 * GET /api/employee/{employeeId}/open-month-expenses
 */
class OpenMonthExpenseDetailsApiController extends Controller
{
    /**
     * Get Open Month Expense Details, Employee Details, and Claim Details.
     *
     * @param Request $request
     * @param int|string|null $employeeIdParam
     * @return JsonResponse
     */
    public function getOpenMonthDetails(Request $request, $employeeIdParam = null): JsonResponse
    {
        try {
            // 1. Resolve & Validate Employee ID
            $employeeId = (int) ($employeeIdParam ?: $request->input('employee_id', $request->input('empid', $request->input('EmployeeID'))));

            if (!$employeeId || $employeeId <= 0) {
                return response()->json([
                    'success' => false,
                    'code'    => 422,
                    'message' => 'Valid employee_id is required.',
                ], 422);
            }

            // 2. Database connections & Company ID
            $companyId = (int) $request->input('company_id', $request->input('ComId', session('company_id', 1)));
            $hrimsConn = DatabaseSwitcher::getHrimsConnection();
            $expConn   = DatabaseSwitcher::getExpenseConnection($companyId);

            // 3. Fetch Employee Details from HRIMS Connection
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
                    CONCAT(COALESCE(e.Fname,''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname,'')) AS FullName,
                    p.Gender,
                    p.Married,
                    p.DR,
                    g.DepartmentId,
                    dept.department_name,
                    g.DesigId,
                    desig.designation_name,
                    g.GradeId,
                    gr.grade_name,
                    g.EmpVertical,
                    ver.vertical_name,
                    g.EmpFunction,
                    fun.function_name,
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
                    g.RepEmployeeID AS reporting_manager_id,
                    mgr.EmpCode AS reporting_manager_code,
                    CONCAT(COALESCE(mgr.Fname,''), ' ', COALESCE(mgr.Sname, ''), ' ', COALESCE(mgr.Lname,'')) AS reporting_manager_name
                FROM hrm_employee e
                LEFT JOIN hrm_employee_general g  ON e.EmployeeID = g.EmployeeID
                LEFT JOIN hrm_employee_personal p ON e.EmployeeID = p.EmployeeID
                LEFT JOIN core_functions fun       ON g.EmpFunction = fun.id
                LEFT JOIN core_verticals ver       ON g.EmpVertical = ver.id
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
            ", [$employeeId]);

            if (!$empRow) {
                return response()->json([
                    'success' => false,
                    'code'    => 404,
                    'message' => "Employee with ID {$employeeId} not found.",
                ], 404);
            }

            // Determine greeting
            $greeting = '';
            if (!empty($empRow->DR) && $empRow->DR === 'Y') {
                $greeting = 'Dr.';
            } elseif (!empty($empRow->Gender) && $empRow->Gender === 'M') {
                $greeting = 'Mr.';
            } elseif (!empty($empRow->Gender) && $empRow->Gender === 'F' && !empty($empRow->Married) && $empRow->Married === 'Y') {
                $greeting = 'Mrs.';
            } elseif (!empty($empRow->Gender) && $empRow->Gender === 'F') {
                $greeting = 'Miss.';
            }

            $employeeDetails = [
                'employee_id'            => (int) $empRow->EmployeeID,
                'emp_code'               => (string) ($empRow->EmpCode ?? ''),
                'fname'                  => $empRow->Fname ?? '',
                'sname'                  => $empRow->Sname ?? '',
                'lname'                  => $empRow->Lname ?? '',
                'full_name'              => trim($empRow->FullName ?? ''),
                'greeting'               => $greeting,
                'gender'                 => $empRow->Gender ?? '',
                'married'                => $empRow->Married ?? '',
                'emp_status'             => $empRow->EmpStatus ?? '',
                'emp_type'               => $empRow->EmpType ?? '',
                'company_id'             => (int) $empRow->CompanyId,
                'department_id'          => (int) ($empRow->DepartmentId ?? 0),
                'department_name'        => $empRow->department_name ?? '',
                'designation_id'         => (int) ($empRow->DesigId ?? 0),
                'designation_name'       => $empRow->designation_name ?? '',
                'grade_id'               => (int) ($empRow->GradeId ?? 0),
                'grade_name'             => $empRow->grade_name ?? '',
                'vertical_id'            => (int) ($empRow->EmpVertical ?? 0),
                'vertical_name'          => $empRow->vertical_name ?? '',
                'function_id'            => (int) ($empRow->EmpFunction ?? 0),
                'function_name'          => $empRow->function_name ?? '',
                'state_id'               => (int) ($empRow->StateId ?? 0),
                'state_name'             => $empRow->state_name ?? '',
                'hq_id'                  => (int) ($empRow->HqId ?? 0),
                'hq_name'                => $empRow->hq_name ?? '',
                'zone_id'                => (int) ($empRow->ZoneId ?? 0),
                'zone_name'              => $empRow->zone_name ?? '',
                'region_id'              => (int) ($empRow->RegionId ?? 0),
                'region_name'            => $empRow->region_name ?? '',
                'territory_id'           => (int) ($empRow->TerrId ?? 0),
                'territory_name'         => $empRow->territory_name ?? '',
                'reporting_manager_id'   => (int) ($empRow->reporting_manager_id ?? 0),
                'reporting_manager_code' => $empRow->reporting_manager_code ?? '',
                'reporting_manager_name' => trim($empRow->reporting_manager_name ?? ''),
            ];

            // 4. Resolve 3 Continuous Target Months & Financial Years
            $monthInput = $request->filled('month') ? (int) $request->input('month') : ($request->filled('Month') ? (int) $request->input('Month') : null);
            $hasYearInput = $request->filled('year_id') || $request->filled('YearId');
            $yearInput = $hasYearInput ? (int) $request->input('year_id', $request->input('YearId')) : null;

            $anchorDate = $this->determineAnchorDate($expConn, $monthInput, $yearInput);

            // Generate the 3 target months (Anchor month, -1 month, -2 months)
            $targetMonths = [];
            for ($i = 0; $i < 3; $i++) {
                $targetCarbon = (clone $anchorDate)->startOfMonth()->subMonths($i);
                $fyMeta = $this->resolveFinancialYearForDate($targetCarbon, $expConn);

                $targetMonths[] = [
                    'carbon'       => $targetCarbon,
                    'month_num'    => (int) $targetCarbon->format('n'),
                    'month_name'   => $targetCarbon->format('F'),
                    'year_id'      => $fyMeta['year_id'],
                    'year_label'   => $fyMeta['year_label'],
                ];
            }

            // Reverse so months appear in chronological order (earliest to latest)
            $targetMonths = array_reverse($targetMonths);

            // 5. Query Open Months across the respective financial year tables
            $openMonthsData = [];

            foreach ($targetMonths as $tm) {
                $yId      = $tm['year_id'];
                $monthNum = $tm['month_num'];
                $mefTable = "y{$yId}_monthexpensefinal";
                $claimsTable = "y{$yId}_expenseclaims";

                // Check if table exists for this financial year
                try {
                    $tableExists = DB::connection($expConn)->select("SHOW TABLES LIKE '{$mefTable}'");
                    if (empty($tableExists)) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }

                // Query MonthExpenseFinal for this target month where Status = 'Open'
                $mef = DB::connection($expConn)
                    ->table($mefTable)
                    ->where('EmployeeID', $employeeId)
                    ->where('YearId', $yId)
                    ->where('Month', $monthNum)
                    ->where('Status', 'Open')
                    ->first();

                // If not Open, skip this month
                if (!$mef) {
                    continue;
                }

                // Query Claims for this open month
                $claimsSql = "
                    SELECT 
                        c.ExpId,
                        c.ClaimId,
                        ct.ClaimCode,
                        ct.ClaimName,
                        ct.cgId,
                        cg.cgName,
                        c.BillDate,
                        c.FilledTAmt,
                        c.VerifyTAmt,
                        c.ApprTAmt,
                        c.FinancedTAmt,
                        c.ClaimStatus,
                        c.FilledOkay,
                        c.ClaimAtStep,
                        c.Remark,
                        c.DateEntryRemark,
                        c.TotKm,
                        c.odomtr_opening,
                        c.odomtr_closing,
                        c.opening_filepath,
                        c.closing_filepath,
                        c.Img_1,
                        c.Img_2,
                        c.Img_3,
                        c.Img_4,
                        c.Img_5,
                        c.v_verify,
                        c.c_verify,
                        c.WType,
                        c.CrDate,
                        c.FilledDate
                    FROM `{$claimsTable}` c
                    LEFT JOIN claimtype ct ON c.ClaimId = ct.ClaimId
                    LEFT JOIN claimgroup cg ON ct.cgId = cg.cgId
                    WHERE c.CrBy = ? 
                      AND c.ClaimYearId = ? 
                      AND c.ClaimMonth = ?
                      AND c.ClaimStatus NOT IN ('Deactivate')
                    ORDER BY c.BillDate ASC, c.ExpId ASC
                ";

                $claimRows = DB::connection($expConn)->select($claimsSql, [$employeeId, $yId, $monthNum]);

                $claimsList = [];
                $totClaimedAmt = 0.0;
                $totVerifiedAmt = 0.0;
                $totApprovedAmt = 0.0;
                $totFinanceAmt = 0.0;

                foreach ($claimRows as $cr) {
                    $filledAmt  = (float) ($cr->FilledTAmt ?? 0);
                    $verifyAmt  = (float) ($cr->VerifyTAmt ?? 0);
                    $apprAmt    = (float) ($cr->ApprTAmt ?? 0);
                    $finAmt     = (float) ($cr->FinancedTAmt ?? 0);

                    $totClaimedAmt  += $filledAmt;
                    $totVerifiedAmt += $verifyAmt;
                    $totApprovedAmt += $apprAmt;
                    $totFinanceAmt  += $finAmt;

                    $claimsList[] = [
                        'exp_id'            => (int) $cr->ExpId,
                        'claim_id'          => (int) $cr->ClaimId,
                        'claim_code'        => $cr->ClaimCode ?? '',
                        'claim_name'        => $cr->ClaimName ?? '',
                        'group_id'          => (int) ($cr->cgId ?? 0),
                        'group_name'        => $cr->cgName ?? '',
                        'bill_date'         => $cr->BillDate ? date('Y-m-d', strtotime($cr->BillDate)) : null,
                        'filled_amount'     => $filledAmt,
                        'verified_amount'   => $verifyAmt,
                        'approved_amount'   => $apprAmt,
                        'financed_amount'   => $finAmt,
                        'claim_status'      => $cr->ClaimStatus ?? '',
                        'filled_okay'       => (int) ($cr->FilledOkay ?? 0),
                        'claim_at_step'     => (int) ($cr->ClaimAtStep ?? 0),
                        'remark'            => $cr->Remark ?? '',
                        'date_entry_remark' => $cr->DateEntryRemark ?? '',
                        'total_km'          => (float) ($cr->TotKm ?? 0),
                        'odometer_opening'  => $cr->odomtr_opening ?? '',
                        'odometer_closing'  => $cr->odomtr_closing ?? '',
                        'v_verify'          => $cr->v_verify ?? '',
                        'c_verify'          => $cr->c_verify ?? '',
                        'wheeler_type'      => (int) ($cr->WType ?? 0),
                        'created_at'        => $cr->CrDate ? date('Y-m-d H:i:s', strtotime($cr->CrDate)) : null,
                        'filled_date'       => $cr->FilledDate ? date('Y-m-d', strtotime($cr->FilledDate)) : null,
                        'attachments'       => [
                            'opening_filepath' => $this->resolveFileUrl($cr->opening_filepath ?? '', $yId, $employeeId),
                            'closing_filepath' => $this->resolveFileUrl($cr->closing_filepath ?? '', $yId, $employeeId),
                            'img_1'            => $this->resolveFileUrl($cr->Img_1 ?? '', $yId, $employeeId),
                            'img_2'            => $this->resolveFileUrl($cr->Img_2 ?? '', $yId, $employeeId),
                            'img_3'            => $this->resolveFileUrl($cr->Img_3 ?? '', $yId, $employeeId),
                            'img_4'            => $this->resolveFileUrl($cr->Img_4 ?? '', $yId, $employeeId),
                            'img_5'            => $this->resolveFileUrl($cr->Img_5 ?? '', $yId, $employeeId),
                        ],
                    ];
                }

                $openMonthsData[] = [
                    'month_number'            => $monthNum,
                    'month_name'              => $tm['month_name'],
                    'year_id'                 => $yId,
                    'financial_year'          => $tm['year_label'],
                    'month_expense_final'     => [
                        'id'                       => (int) $mef->id,
                        'doc_id'                   => $mef->DocId ?? null,
                        'doc_no'                   => $mef->DocNo ?? null,
                        'employee_id'              => (int) $mef->EmployeeID,
                        'month'                    => (int) $mef->Month,
                        'year_id'                  => (int) $mef->YearId,
                        'status'                   => $mef->Status ?? 'Open',
                        'allow_submit'             => $mef->AllowSubmit ?? 'N',
                        'sub_permission'           => $mef->SubPermission ?? 'N',
                        'km_confirmation'          => $mef->Km_Confirmation ?? '',
                        'odo_approve_final_submit' => $mef->Odo_Approve_Final_Submit ?? '',
                        'created_date'             => $mef->Crdate ?? null,
                        'date_of_submit'           => $mef->DateOfSubmit ?? null,
                        'verified_date'            => $mef->Verified_Date ?? null,
                        'approved_date'            => $mef->Approved_Date ?? null,
                        'finance_date'             => $mef->Finance_Date ?? null,
                        'total_claim_count'        => (int) ($mef->Total_Claim ?? count($claimsList)),
                        'claim_amount'             => (float) ($mef->Claim_Amount ?? $totClaimedAmt),
                        'verified_amount'          => (float) ($mef->Verified_Amount ?? $totVerifiedAmt),
                        'approved_amount'          => (float) ($mef->Approved_Amount ?? $totApprovedAmt),
                        'finance_amount'           => (float) ($mef->Finance_Amount ?? $totFinanceAmt),
                        'advance_payment_amount'   => (float) ($mef->advance_payment_amount ?? 0),
                        'advance_payment_date'     => $mef->advance_payment_date ?? null,
                        'payment_status'           => $mef->payment_status ?? '',
                        'payment_amount'           => (float) ($mef->payment_amount ?? 0),
                        'docate_no'                => $mef->DocateNo ?? '',
                        'agency'                   => $mef->Agency ?? '',
                    ],
                    'claims_summary' => [
                        'total_claims'           => count($claimsList),
                        'total_claimed_amount'   => $totClaimedAmt,
                        'total_verified_amount'  => $totVerifiedAmt,
                        'total_approved_amount'  => $totApprovedAmt,
                        'total_finance_amount'   => $totFinanceAmt,
                    ],
                    'claims' => $claimsList,
                ];
            }

            return response()->json([
                'success'                   => true,
                'code'                      => 200,
                'message'                   => 'Open month expense details retrieved successfully for 3 continuous months.',
                'employee_id'               => $employeeId,
                'company_id'                => $companyId,
                'anchor_month'              => (int) $anchorDate->format('n'),
                'anchor_year'               => (int) $anchorDate->format('Y'),
                'evaluated_months_count'    => count($targetMonths),
                'employee'                  => $employeeDetails,
                'open_months_count'         => count($openMonthsData),
                'open_months'               => $openMonthsData,
            ], 200);

        } catch (\Exception $e) {
            Log::error("OpenMonthExpenseDetailsApiController Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'An error occurred while fetching open month expense details.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine the Anchor Date (Carbon instance) based on inputs or current date.
     */
    private function determineAnchorDate(string $expConn, ?int $monthInput, ?int $yearInput): Carbon
    {
        // 1. If explicit year_id passed
        if ($yearInput) {
            $fyRow = DB::connection($expConn)
                ->table('financialyear')
                ->where('YearId', $yearInput)
                ->first();

            if ($fyRow) {
                $fyStartYear = (int) ($fyRow->y1 ?? date('Y', strtotime($fyRow->FromDate)));
                $m = $monthInput ?: (int) date('n');
                $calYear = ($m >= 4) ? $fyStartYear : ($fyStartYear + 1);
                return Carbon::createFromDate($calYear, $m, 1);
            }
        }

        // 2. If monthInput passed without year_id (e.g. month=5 or month=4)
        if ($monthInput && $monthInput >= 1 && $monthInput <= 12) {
            // Find active financial year or current date
            $activeFy = DB::connection($expConn)
                ->table('financialyear')
                ->where('Status', 'Active')
                ->first();

            $activeStartYear = $activeFy ? (int) ($activeFy->y1 ?? date('Y', strtotime($activeFy->FromDate))) : (int) date('Y');
            $calYear = ($monthInput >= 4) ? $activeStartYear : ($activeStartYear + 1);
            return Carbon::createFromDate($calYear, $monthInput, 1);
        }

        // 3. Default to current date
        return Carbon::now();
    }

    /**
     * Resolve Financial Year ID & Label for a given target date from financialyear table.
     */
    private function resolveFinancialYearForDate(Carbon $targetDate, string $expConn): array
    {
        $dateStr = $targetDate->toDateString();

        $fyRow = DB::connection($expConn)
            ->table('financialyear')
            ->where('FromDate', '<=', $dateStr)
            ->where('Todate', '>=', $dateStr)
            ->first();

        if ($fyRow) {
            return [
                'year_id'    => (int) $fyRow->YearId,
                'year_label' => $fyRow->Year ?? '',
            ];
        }

        // Fallback: query active FY and estimate relative YearId
        $activeFy = DB::connection($expConn)
            ->table('financialyear')
            ->where('Status', 'Active')
            ->first();

        if ($activeFy) {
            $activeStartYear = (int) ($activeFy->y1 ?? date('Y', strtotime($activeFy->FromDate)));
            $targetMonth = (int) $targetDate->format('n');
            $targetYear = (int) $targetDate->format('Y');
            $targetStartYear = ($targetMonth >= 4) ? $targetYear : ($targetYear - 1);
            $diff = $targetStartYear - $activeStartYear;
            $calculatedYearId = (int) $activeFy->YearId + $diff;

            return [
                'year_id'    => max(1, $calculatedYearId),
                'year_label' => "{$targetStartYear}-" . ($targetStartYear + 1),
            ];
        }

        // Default fallback to 7
        return [
            'year_id'    => 7,
            'year_label' => '2025-2026',
        ];
    }

    /**
     * Resolve attachment file path to S3 or fallback URL.
     */
    private function resolveFileUrl(?string $filename, int $yearId, $userId): string
    {
        if (empty($filename)) {
            return '';
        }

        try {
            $s3Service = app(S3Service::class);
            $locations = [
                "Expense/{$yearId}/{$userId}/{$filename}",
                "Expense/6/{$userId}/{$filename}",
            ];

            foreach ($locations as $path) {
                if ($s3Service->fileExists($path)) {
                    return $s3Service->getFileViewUrl($path);
                }
            }
        } catch (\Exception $e) {
            // Fallback if S3 service unavailable
        }

        $sanitized = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($filename));
        return "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$userId}/{$sanitized}";
    }
}
