<?php

namespace App\Http\Controllers\admin;

use App\Exports\ClaimActionTrackingExport;
use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ClaimActionTrackingController extends Controller
{
    /**
     * GET /reports/claim-action-tracking
     * Render the Action Tracking report view.
     */
    public function index(Request $request)
    {
        $company_id = session('company_id', 1);
        $expenseConn = session('expense_connection', DatabaseSwitcher::getExpenseConnection());

        // Financial Years
        $financialYears = DB::connection($expenseConn)
            ->table('financialyear')
            ->orderBy('YearId', 'desc')
            ->get(['YearId', 'Year', 'Status']);

        $currentYearId = session('year_id', $financialYears->first()?->YearId ?? 7);

        // Claim Types
        $claimTypes = DB::connection($expenseConn)
            ->table('claimtype')
            ->where('ClaimStatus', 'A')
            ->orderBy('ClaimName')
            ->get(['ClaimId', 'ClaimName']);

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        return view('reports.claim-action-tracking', array_merge($organogramOptions, compact(
            'financialYears',
            'currentYearId',
            'claimTypes',
            'monthNames',
            'organogramOptions'
        )));
    }

    /**
     * POST /reports/claim-action-tracking/data
     * Return Claim-Wise Action Tracking records for DataTable.
     */
    public function getData(Request $request)
    {
        try {
            $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
            $expenseConn = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDbName = DB::connection($hrimsConn)->getDatabaseName();
            $claimsTable = "y{$yearId}_expenseclaims";

            // Verify table exists
            $tableExists = DB::connection($expenseConn)->selectOne(
                "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$claimsTable]
            );

            if (!$tableExists || $tableExists->cnt == 0) {
                return response()->json([
                    'draw' => intval($request->input('draw', 1)),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => "Table {$claimsTable} not found."
                ]);
            }

            $query = DB::connection($expenseConn)
                ->table("{$claimsTable} as ec")
                ->where('ec.ClaimStatus', '!=', 'Deactivate');

            // ── Filters ────────────────────────────────────────────────────────
            $this->applyFilters($query, $request, $hrimsDbName);

            // Total count before pagination
            $recordsFiltered = (clone $query)->count();

            // Pagination & Sorting
            $perPage = intval($request->input('length', 25));
            $start = intval($request->input('start', 0));
            if ($perPage > 200) { $perPage = 200; }
            if ($perPage < 1) { $perPage = 25; }

            $rows = $query->select([
                'ec.ExpId',
                'ec.ClaimId',
                'ec.ClaimMonth',
                'ec.ClaimYearId',
                'ec.BillDate',
                'ec.CrBy',
                'ec.CrDate',
                'ec.Amount',
                'ec.Remark',
                'ec.ClaimStatus',
                'ec.ClaimAtStep',
                'ec.FilledBy',
                'ec.FilledDate',
                'ec.FilledTAmt',
                'ec.FilledOkay',
                'ec.FilOkDenyRemark',
                'ec.VerifyBy',
                'ec.VerifyDate',
                'ec.VerifyTAmt',
                'ec.VerifyTRemark',
                'ec.ApprBy',
                'ec.ApprDate',
                'ec.ApprTAmt',
                'ec.ApprTRemark',
                'ec.FinancedBy',
                'ec.FinancedDate',
                'ec.FinancedTAmt',
                'ec.FinancedTRemark',
                'ec.RtnBy',
            ])
            ->orderBy('ec.ExpId', 'desc')
            ->offset($start)
            ->limit($perPage)
            ->get();

            // Bulk hydrate employee details and claim types
            $hydratedData = $this->hydrateActionDetails($rows, $hrimsConn, $expenseConn);

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => $recordsFiltered,
                'recordsFiltered' => $recordsFiltered,
                'data' => $hydratedData,
            ]);
        } catch (\Exception $e) {
            \Log::error('ClaimActionTrackingController::getData error: ' . $e->getMessage());
            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /reports/claim-action-tracking/employee-summary
     * Return Employee-Wise Monthly Rollup Data.
     */
    public function getEmployeeSummary(Request $request)
    {
        try {
            $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
            $expenseConn = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDbName = DB::connection($hrimsConn)->getDatabaseName();
            $claimsTable = "y{$yearId}_expenseclaims";
            $finalTable = "y{$yearId}_monthexpensefinal";

            $query = DB::connection($expenseConn)
                ->table("{$claimsTable} as ec")
                ->where('ec.ClaimStatus', '!=', 'Deactivate');

            $this->applyFilters($query, $request, $hrimsDbName);

            $groupedQuery = $query->groupBy('ec.CrBy', 'ec.ClaimMonth')
                ->select([
                    'ec.CrBy as employee_id',
                    'ec.ClaimMonth as month',
                    DB::raw('COUNT(ec.ExpId) as total_claims'),
                    DB::raw('SUM(ec.Amount) as total_claimed_amt'),
                    DB::raw('SUM(ec.FilledTAmt) as total_filled_amt'),
                    DB::raw('SUM(ec.VerifyTAmt) as total_verified_amt'),
                    DB::raw('SUM(ec.ApprTAmt) as total_approved_amt'),
                    DB::raw('SUM(ec.FinancedTAmt) as total_financed_amt'),
                    DB::raw('MAX(ec.FilledBy) as sample_filled_by'),
                    DB::raw('MAX(ec.VerifyBy) as sample_verify_by'),
                    DB::raw('MAX(ec.ApprBy) as sample_appr_by'),
                    DB::raw('MAX(ec.FinancedBy) as sample_financed_by'),
                    DB::raw('MIN(ec.CrDate) as first_claim_date'),
                    DB::raw('MAX(ec.CrDate) as last_claim_date'),
                    DB::raw('MAX(ec.FilledDate) as last_filled_date'),
                    DB::raw('MAX(ec.VerifyDate) as last_verify_date'),
                    DB::raw('MAX(ec.ApprDate) as last_appr_date'),
                    DB::raw('MAX(ec.FinancedDate) as last_financed_date'),
                ]);

            $recordsFiltered = DB::connection($expenseConn)->table(DB::raw("({$groupedQuery->toSql()}) as grp"))
                ->mergeBindings($groupedQuery)
                ->count();

            $perPage = intval($request->input('length', 25));
            $start = intval($request->input('start', 0));
            if ($perPage > 200) { $perPage = 200; }
            if ($perPage < 1) { $perPage = 25; }

            $rows = $groupedQuery->orderBy('ec.ClaimMonth', 'desc')
                ->offset($start)
                ->limit($perPage)
                ->get();

            // Bulk collect employee IDs and month final records
            $empIds = $rows->pluck('employee_id')->unique()->filter()->values()->toArray();
            $actionTakerIds = collect([
                $rows->pluck('sample_filled_by'),
                $rows->pluck('sample_verify_by'),
                $rows->pluck('sample_appr_by'),
                $rows->pluck('sample_financed_by'),
            ])->flatten()->unique()->filter()->values()->toArray();

            $allEmpIds = array_unique(array_merge($empIds, $actionTakerIds));
            $empMap = $this->getEmployeesMap($allEmpIds, $hrimsConn);

            // Fetch monthexpensefinal info if table exists
            $finalMap = [];
            $finalTableExists = DB::connection($expenseConn)->selectOne(
                "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$finalTable]
            );
            if ($finalTableExists && $finalTableExists->cnt > 0 && !empty($empIds)) {
                $finalRows = DB::connection($expenseConn)->table($finalTable)
                    ->whereIn('EmployeeID', $empIds)
                    ->where('YearId', $yearId)
                    ->get(['EmployeeID', 'Month', 'Status', 'DateOfSubmit', 'Verified_Date', 'Approved_Date', 'Finance_Date', 'Fin_PayDate', 'payment_date', 'RecevingDate', 'Fin_PayAmt', 'Fin_AppBy', 'Fin_PayBy']);
                foreach ($finalRows as $fr) {
                    $finalMap[$fr->EmployeeID . '_' . $fr->Month] = $fr;
                }
            }

            $monthNames = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];

            $fmtDt = function ($dt) {
                if (empty($dt) || \App\Support\ZeroDate::none($dt) || \App\Support\ZeroDate::none($dt)) return '-';
                try {
                    return Carbon::parse($dt)->format('d-m-Y');
                } catch (\Exception $e) {
                    return '-';
                }
            };

            $data = [];
            foreach ($rows as $row) {
                $claimant = $empMap[$row->employee_id] ?? ['name' => "EMP #{$row->employee_id}", 'code' => '-', 'dept' => '-', 'desig' => '-'];
                $filler = $empMap[$row->sample_filled_by] ?? null;
                $verifier = $empMap[$row->sample_verify_by] ?? null;
                $approver = $empMap[$row->sample_appr_by] ?? null;
                $financier = $empMap[$row->sample_financed_by] ?? null;

                $finalKey = $row->employee_id . '_' . $row->month;
                $finalRec = $finalMap[$finalKey] ?? null;

                // Month Final App By & Pay By
                $finAppBy = $finalRec && $finalRec->Fin_AppBy ? ($empMap[$finalRec->Fin_AppBy] ?? null) : null;
                $finPayBy = $finalRec && $finalRec->Fin_PayBy ? ($empMap[$finalRec->Fin_PayBy] ?? null) : null;

                // Dates
                $apprDate = ($finalRec && !empty($finalRec->Approved_Date) && \App\Support\ZeroDate::has($finalRec->Approved_Date))
                    ? $fmtDt($finalRec->Approved_Date)
                    : ($row->last_appr_date ? $fmtDt($row->last_appr_date) : '-');

                $courierDate = ($finalRec && !empty($finalRec->RecevingDate) && \App\Support\ZeroDate::has($finalRec->RecevingDate))
                    ? $fmtDt($finalRec->RecevingDate)
                    : '-';

                $payDate = ($finalRec && !empty($finalRec->Fin_PayDate) && \App\Support\ZeroDate::has($finalRec->Fin_PayDate))
                    ? $fmtDt($finalRec->Fin_PayDate)
                    : (($finalRec && !empty($finalRec->payment_date) && \App\Support\ZeroDate::has($finalRec->payment_date))
                        ? $fmtDt($finalRec->payment_date)
                        : '-');

                // Calculate total TAT in days (from first claim to last financed or approved date)
                $firstDate = $row->first_claim_date ? Carbon::parse($row->first_claim_date) : null;
                $lastDate = $finalRec && $finalRec->Fin_PayDate && \App\Support\ZeroDate::has($finalRec->Fin_PayDate)
                    ? Carbon::parse($finalRec->Fin_PayDate)
                    : ($row->last_financed_date && \App\Support\ZeroDate::has($row->last_financed_date)
                        ? Carbon::parse($row->last_financed_date)
                        : ($row->last_appr_date && \App\Support\ZeroDate::has($row->last_appr_date) ? Carbon::parse($row->last_appr_date) : null));

                $overallTatDays = ($firstDate && $lastDate && $lastDate->greaterThanOrEqualTo($firstDate))
                    ? $firstDate->diffInDays($lastDate)
                    : null;

                $data[] = [
                    'employee_id' => $row->employee_id,
                    'employee_name' => $claimant['name'],
                    'employee_code' => $claimant['code'],
                    'department' => $claimant['dept'],
                    'designation' => $claimant['desig'],
                    'month_num' => $row->month,
                    'month_name' => $monthNames[$row->month] ?? "Month {$row->month}",
                    'total_claims' => $row->total_claims,
                    'claimed_amt' => floatval($row->total_claimed_amt),
                    'filled_amt' => floatval($row->total_filled_amt),
                    'verified_amt' => floatval($row->total_verified_amt),
                    'approved_amt' => floatval($row->total_approved_amt),
                    'financed_amt' => floatval($row->total_financed_amt),
                    'paid_amt' => floatval($finalRec->Fin_PayAmt ?? 0),
                    'month_status' => $finalRec->Status ?? ($row->last_financed_date ? 'Financed' : ($row->last_appr_date ? 'Approved' : 'Pending')),
                    'filler_name' => $filler['name'] ?? '-',
                    'filler_code' => $filler['code'] ?? '',
                    'verifier_name' => $verifier['name'] ?? '-',
                    'verifier_code' => $verifier['code'] ?? '',
                    'verify_date' => $finalRec->Verified_Date ?? ($row->last_verify_date ? Carbon::parse($row->last_verify_date)->format('d-m-Y') : '-'),
                    'approver_name' => $approver['name'] ?? '-',
                    'approver_code' => $approver['code'] ?? '',
                    'appr_date' => $apprDate,
                    'approved_date' => $apprDate,
                    'courier_receiving_date' => $courierDate,
                    'fin_app_name' => $finAppBy['name'] ?? ($financier['name'] ?? '-'),
                    'fin_app_code' => $finAppBy['code'] ?? ($financier['code'] ?? ''),
                    'finance_date' => $finalRec->Finance_Date ?? ($row->last_financed_date ? Carbon::parse($row->last_financed_date)->format('d-m-Y') : '-'),
                    'fin_pay_name' => $finPayBy['name'] ?? '-',
                    'fin_pay_code' => $finPayBy['code'] ?? '',
                    'pay_date' => $payDate,
                    'payment_date' => $payDate,
                    'overall_tat_days' => $overallTatDays,
                ];
            }

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => $recordsFiltered,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('ClaimActionTrackingController::getEmployeeSummary error: ' . $e->getMessage());
            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /reports/claim-action-tracking/summary
     * Return KPI statistics for the filter selection.
     */
    public function getSummary(Request $request)
    {
        try {
            $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
            $expenseConn = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDbName = DB::connection($hrimsConn)->getDatabaseName();
            $claimsTable = "y{$yearId}_expenseclaims";

            $query = DB::connection($expenseConn)
                ->table("{$claimsTable} as ec")
                ->where('ec.ClaimStatus', '!=', 'Deactivate');

            $this->applyFilters($query, $request, $hrimsDbName);

            $summary = $query->selectRaw("
                COUNT(ec.ExpId) as total_claims,
                COALESCE(SUM(ec.Amount), 0) as total_claimed_amt,
                COUNT(CASE WHEN ec.FilledDate IS NOT NULL AND ec.FilledDate != '0000-00-00 00:00:00' AND ec.FilledDate != '0000-00-00' THEN 1 END) as filled_count,
                COALESCE(SUM(CASE WHEN ec.FilledDate IS NOT NULL AND ec.FilledDate != '0000-00-00 00:00:00' AND ec.FilledDate != '0000-00-00' THEN ec.FilledTAmt ELSE 0 END), 0) as filled_amt,
                COUNT(CASE WHEN ec.VerifyDate IS NOT NULL AND ec.VerifyDate != '0000-00-00 00:00:00' AND ec.VerifyDate != '0000-00-00' THEN 1 END) as verified_count,
                COALESCE(SUM(CASE WHEN ec.VerifyDate IS NOT NULL AND ec.VerifyDate != '0000-00-00 00:00:00' AND ec.VerifyDate != '0000-00-00' THEN ec.VerifyTAmt ELSE 0 END), 0) as verified_amt,
                COUNT(CASE WHEN ec.ApprDate IS NOT NULL AND ec.ApprDate != '0000-00-00 00:00:00' AND ec.ApprDate != '0000-00-00' THEN 1 END) as approved_count,
                COALESCE(SUM(CASE WHEN ec.ApprDate IS NOT NULL AND ec.ApprDate != '0000-00-00 00:00:00' AND ec.ApprDate != '0000-00-00' THEN ec.ApprTAmt ELSE 0 END), 0) as approved_amt,
                COUNT(CASE WHEN ec.FinancedDate IS NOT NULL AND ec.FinancedDate != '0000-00-00 00:00:00' AND ec.FinancedDate != '0000-00-00' THEN 1 END) as financed_count,
                COALESCE(SUM(CASE WHEN ec.FinancedDate IS NOT NULL AND ec.FinancedDate != '0000-00-00 00:00:00' AND ec.FinancedDate != '0000-00-00' THEN ec.FinancedTAmt ELSE 0 END), 0) as financed_amt,
                COUNT(DISTINCT ec.CrBy) as unique_claimants,
                COUNT(DISTINCT ec.VerifyBy) as unique_verifiers,
                COUNT(DISTINCT ec.ApprBy) as unique_approvers
            ")->first();

            return response()->json([
                'success' => true,
                'summary' => [
                    'total_claims' => intval($summary->total_claims ?? 0),
                    'total_claimed_amt' => floatval($summary->total_claimed_amt ?? 0),
                    'filled_count' => intval($summary->filled_count ?? 0),
                    'filled_amt' => floatval($summary->filled_amt ?? 0),
                    'verified_count' => intval($summary->verified_count ?? 0),
                    'verified_amt' => floatval($summary->verified_amt ?? 0),
                    'approved_count' => intval($summary->approved_count ?? 0),
                    'approved_amt' => floatval($summary->approved_amt ?? 0),
                    'financed_count' => intval($summary->financed_count ?? 0),
                    'financed_amt' => floatval($summary->financed_amt ?? 0),
                    'unique_claimants' => intval($summary->unique_claimants ?? 0),
                    'unique_verifiers' => intval($summary->unique_verifiers ?? 0),
                    'unique_approvers' => intval($summary->unique_approvers ?? 0),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('ClaimActionTrackingController::getSummary error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /reports/claim-action-tracking/export
     * Export Action Tracking data to Excel.
     */
    public function export(Request $request)
    {
        $yearId = (int) ($request->input('year_id', \App\Support\CurrentYear::id()));
        $type = $request->input('export_type', 'claim_wise'); // 'claim_wise' or 'employee_summary'
        $filename = ($type === 'employee_summary')
            ? "employee_action_tracking_summary_fy{$yearId}_" . date('Ymd_His') . ".xlsx"
            : "claim_action_tracking_audit_fy{$yearId}_" . date('Ymd_His') . ".xlsx";

        return Excel::download(new ClaimActionTrackingExport($request->all(), $type), $filename);
    }

    /**
     * Helper: Apply all filters to the base claims query.
     */
    private function applyFilters($query, Request $request, string $hrimsDbName): void
    {
        // Month filter
        if ($request->filled('month')) {
            $query->where('ec.ClaimMonth', $request->month);
        }

        // Claim Type filter
        if ($request->filled('claim_type_id')) {
            $query->where('ec.ClaimId', $request->claim_type_id);
        }

        // Claim Status / Stage filter
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'Pending_Verification') {
                $query->where('ec.ClaimStatus', 'Filled')->where(function($q) {
                    $q->whereNull('ec.VerifyDate')->orWhere('ec.VerifyDate', '0000-00-00 00:00:00')->orWhere('ec.VerifyDate', '0000-00-00');
                });
            } elseif ($status === 'Pending_Approval') {
                $query->where('ec.ClaimStatus', 'Verified')->where(function($q) {
                    $q->whereNull('ec.ApprDate')->orWhere('ec.ApprDate', '0000-00-00 00:00:00')->orWhere('ec.ApprDate', '0000-00-00');
                });
            } elseif ($status === 'Pending_Finance') {
                $query->where('ec.ClaimStatus', 'Approved')->where(function($q) {
                    $q->whereNull('ec.FinancedDate')->orWhere('ec.FinancedDate', '0000-00-00 00:00:00')->orWhere('ec.FinancedDate', '0000-00-00');
                });
            } elseif ($status === 'Financed') {
                $query->where('ec.ClaimStatus', 'Financed');
            } elseif ($status === 'Returned') {
                $query->whereNotNull('ec.RtnBy')->where('ec.RtnBy', '>', 0);
            } else {
                $query->where('ec.ClaimStatus', $status);
            }
        }

        // Date range on Claim Date / Bill Date
        if ($request->filled('date_from')) {
            $query->whereDate('ec.CrDate', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('ec.CrDate', '<=', $request->date_to);
        }

        // Action Taker Filters
        if ($request->filled('filled_by')) {
            $query->where('ec.FilledBy', $request->filled_by);
        }
        if ($request->filled('verified_by')) {
            $query->where('ec.VerifyBy', $request->verified_by);
        }
        if ($request->filled('approved_by')) {
            $query->where('ec.ApprBy', $request->approved_by);
        }
        if ($request->filled('financed_by')) {
            $query->where('ec.FinancedBy', $request->financed_by);
        }

        // Specific Employee / Claimant Search
        if ($request->filled('employee_id')) {
            $query->where('ec.CrBy', $request->employee_id);
        }

        // Text search by Employee Code / Name
        if ($request->filled('employee')) {
            $term = trim($request->employee);
            $hrimsConn = session('hrims_connection', 'hrims');
            $matchedEmpIds = DB::connection($hrimsConn)->table('hrm_employee')
                ->where(function ($q) use ($term) {
                    $q->where('EmpCode', 'LIKE', "%{$term}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$term}%")
                      ->orWhere('ECode', 'LIKE', "%{$term}%")
                      ->orWhere('Fname', 'LIKE', "%{$term}%")
                      ->orWhere('Lname', 'LIKE', "%{$term}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$term}%"]);
                })
                ->pluck('EmployeeID')
                ->toArray();

            $query->whereIn('ec.CrBy', !empty($matchedEmpIds) ? $matchedEmpIds : [-1]);
        }

        // Organogram Filters
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        if (!empty(array_filter($organogramFilters))) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $orgEmpQuery = DB::connection($hrimsConn)->table('hrm_employee_general as eg')->select('eg.EmployeeID');
            OrganogramFilterHelper::applyToQuery($orgEmpQuery, $organogramFilters, 'eg', 'eg.EmployeeID', $hrimsDbName);
            $orgEmpIds = $orgEmpQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('ec.CrBy', !empty($orgEmpIds) ? $orgEmpIds : [-1]);
        }
    }

    /**
     * Helper: Hydrate raw claim records with Claimant, Action-Takers and TATs.
     */
    private function hydrateActionDetails($rows, string $hrimsConn, string $expenseConn): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        // Collect all unique Employee IDs for bulk fetching
        $empIds = collect([
            $rows->pluck('CrBy'),
            $rows->pluck('FilledBy'),
            $rows->pluck('VerifyBy'),
            $rows->pluck('ApprBy'),
            $rows->pluck('FinancedBy'),
            $rows->pluck('RtnBy'),
        ])->flatten()->unique()->filter()->values()->toArray();

        $empMap = $this->getEmployeesMap($empIds, $hrimsConn);

        // Collect all unique Claim IDs for claim type name lookup
        $claimTypeIds = $rows->pluck('ClaimId')->unique()->filter()->values()->toArray();
        $claimTypesMap = DB::connection($expenseConn)->table('claimtype')
            ->whereIn('ClaimId', $claimTypeIds)
            ->pluck('ClaimName', 'ClaimId')
            ->toArray();

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        // Batch-fetch monthexpensefinal records for rows (for Courier & Payment dates)
        $mefMap = [];
        $firstRow = $rows->first();
        $calcYearId = $firstRow ? ($firstRow->ClaimYearId ?? \App\Support\CurrentYear::id()) : \App\Support\CurrentYear::id();
        $finalTable = "y{$calcYearId}_monthexpensefinal";
        $claimantIds = $rows->pluck('CrBy')->unique()->filter()->values()->toArray();
        $claimMonths = $rows->pluck('ClaimMonth')->unique()->filter()->values()->toArray();
        if (!empty($claimantIds) && !empty($claimMonths)) {
            try {
                $finalTableExists = DB::connection($expenseConn)->selectOne(
                    "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                    [$finalTable]
                );
                if ($finalTableExists && $finalTableExists->cnt > 0) {
                    $mefRows = DB::connection($expenseConn)->table($finalTable)
                        ->whereIn('EmployeeID', $claimantIds)
                        ->whereIn('Month', $claimMonths)
                        ->get(['EmployeeID', 'Month', 'Approved_Date', 'RecevingDate', 'Fin_PayDate', 'payment_date']);
                    foreach ($mefRows as $mr) {
                        $mefMap[$mr->EmployeeID . '_' . $mr->Month] = $mr;
                    }
                }
            } catch (\Exception $e) {}
        }

        $output = [];
        foreach ($rows as $row) {
            $claimant = $empMap[$row->CrBy] ?? ['name' => "EMP #{$row->CrBy}", 'code' => '-', 'dept' => '-', 'desig' => '-'];
            $filler = $empMap[$row->FilledBy] ?? null;
            $verifier = $empMap[$row->VerifyBy] ?? null;
            $approver = $empMap[$row->ApprBy] ?? null;
            $financier = $empMap[$row->FinancedBy] ?? null;
            $returner = $empMap[$row->RtnBy] ?? null;

            // Date formatting
            $crDate = $this->parseValidDate($row->CrDate);
            $billDate = $this->parseValidDate($row->BillDate);
            $filledDate = $this->parseValidDate($row->FilledDate);
            $verifyDate = $this->parseValidDate($row->VerifyDate);
            $apprDate = $this->parseValidDate($row->ApprDate);
            $financedDate = $this->parseValidDate($row->FinancedDate);

            // Turnaround times (TAT in days)
            $tatClaimToFill = ($crDate && $filledDate && $filledDate->greaterThanOrEqualTo($crDate)) ? $crDate->diffInDays($filledDate) : null;
            $tatFillToVerify = ($filledDate && $verifyDate && $verifyDate->greaterThanOrEqualTo($filledDate)) ? $filledDate->diffInDays($verifyDate) : null;
            $tatVerifyToAppr = ($verifyDate && $apprDate && $apprDate->greaterThanOrEqualTo($verifyDate)) ? $verifyDate->diffInDays($apprDate) : null;
            $tatApprToFinance = ($apprDate && $financedDate && $financedDate->greaterThanOrEqualTo($apprDate)) ? $apprDate->diffInDays($financedDate) : null;

            // Overall TAT: Claim to latest step
            $latestStepDate = $financedDate ?: ($apprDate ?: ($verifyDate ?: $filledDate));
            $overallTat = ($crDate && $latestStepDate && $latestStepDate->greaterThanOrEqualTo($crDate)) ? $crDate->diffInDays($latestStepDate) : null;

            $mef = $mefMap[$row->CrBy . '_' . $row->ClaimMonth] ?? null;
            $mefApprDate = ($mef && !empty($mef->Approved_Date) && \App\Support\ZeroDate::has($mef->Approved_Date))
                ? $this->parseValidDate($mef->Approved_Date)
                : null;
            $courierRecvDate = ($mef && !empty($mef->RecevingDate) && \App\Support\ZeroDate::has($mef->RecevingDate))
                ? $this->parseValidDate($mef->RecevingDate)
                : null;
            $mefPayDate = ($mef && !empty($mef->Fin_PayDate) && \App\Support\ZeroDate::has($mef->Fin_PayDate))
                ? $this->parseValidDate($mef->Fin_PayDate)
                : (($mef && !empty($mef->payment_date) && \App\Support\ZeroDate::has($mef->payment_date))
                    ? $this->parseValidDate($mef->payment_date)
                    : null);

            $effectiveApprDate = $apprDate ?: $mefApprDate;
            $effectivePayDate = $mefPayDate ?: $financedDate;

            $output[] = [
                'exp_id' => $row->ExpId,
                'claim_month_num' => $row->ClaimMonth,
                'claim_month' => $monthNames[$row->ClaimMonth] ?? $row->ClaimMonth,
                'bill_date' => $billDate ? $billDate->format('d-m-Y') : '-',
                'claim_type' => $claimTypesMap[$row->ClaimId] ?? "Type #{$row->ClaimId}",
                'amount' => floatval($row->Amount),
                'status' => $row->ClaimStatus,
                'claim_step' => $row->ClaimAtStep,
                'remark' => $row->Remark ?? '',

                // Claimant
                'claimant_id' => $row->CrBy,
                'claimant_name' => $claimant['name'],
                'claimant_code' => $claimant['code'],
                'department' => $claimant['dept'],
                'designation' => $claimant['desig'],
                'cr_date' => $crDate ? $crDate->format('d-m-Y H:i') : '-',

                // Filled
                'filled_by_id' => $row->FilledBy,
                'filled_by_name' => $filler['name'] ?? '-',
                'filled_by_code' => $filler['code'] ?? '',
                'filled_date' => $filledDate ? $filledDate->format('d-m-Y H:i') : '-',
                'filled_amt' => floatval($row->FilledTAmt),
                'filled_okay' => $row->FilledOkay,
                'filled_remark' => $row->FilOkDenyRemark ?? '',
                'tat_fill' => $tatClaimToFill,

                // Verified
                'verify_by_id' => $row->VerifyBy,
                'verify_by_name' => $verifier['name'] ?? '-',
                'verify_by_code' => $verifier['code'] ?? '',
                'verify_date' => $verifyDate ? $verifyDate->format('d-m-Y H:i') : '-',
                'verify_amt' => floatval($row->VerifyTAmt),
                'verify_remark' => $row->VerifyTRemark ?? '',
                'tat_verify' => $tatFillToVerify,

                // Approved
                'appr_by_id' => $row->ApprBy,
                'appr_by_name' => $approver['name'] ?? '-',
                'appr_by_code' => $approver['code'] ?? '',
                'appr_date' => $effectiveApprDate ? $effectiveApprDate->format('d-m-Y H:i') : '-',
                'approved_date' => $effectiveApprDate ? $effectiveApprDate->format('d-m-Y H:i') : '-',
                'appr_amt' => floatval($row->ApprTAmt),
                'appr_remark' => $row->ApprTRemark ?? '',
                'tat_approve' => $tatVerifyToAppr,

                // Courier & Payment Dates
                'courier_receiving_date' => $courierRecvDate ? $courierRecvDate->format('d-m-Y') : '-',
                'payment_date' => $effectivePayDate ? $effectivePayDate->format('d-m-Y') : '-',

                // Financed
                'financed_by_id' => $row->FinancedBy,
                'financed_by_name' => $financier['name'] ?? '-',
                'financed_by_code' => $financier['code'] ?? '',
                'financed_date' => $financedDate ? $financedDate->format('d-m-Y H:i') : '-',
                'financed_amt' => floatval($row->FinancedTAmt),
                'financed_remark' => $row->FinancedTRemark ?? '',
                'tat_finance' => $tatApprToFinance,

                // Returner
                'return_by_id' => $row->RtnBy,
                'return_by_name' => $returner['name'] ?? null,

                // Overall TAT
                'overall_tat_days' => $overallTat,
            ];
        }

        return $output;
    }

    /**
     * Helper: Bulk lookup employee details from HRIMS database.
     */
    private function getEmployeesMap(array $empIds, string $hrimsConn): array
    {
        if (empty($empIds)) {
            return [];
        }

        $empRows = DB::connection($hrimsConn)->table('hrm_employee as e')
            ->leftJoin('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin('core_departments as d', 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin('core_designation as deg', 'eg.DesigId', '=', 'deg.id')
            ->whereIn('e.EmployeeID', $empIds)
            ->select([
                'e.EmployeeID',
                'e.EmpCode',
                DB::raw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as full_name"),
                'd.department_name',
                'deg.designation_name',
            ])
            ->get();

        $map = [];
        foreach ($empRows as $er) {
            $map[$er->EmployeeID] = [
                'name' => trim($er->full_name),
                'code' => $er->EmpCode,
                'dept' => $er->department_name ?? '-',
                'desig' => $er->designation_name ?? '-',
            ];
        }

        return $map;
    }

    /**
     * Helper: Parse dates ignoring zeroes.
     */
    private function parseValidDate($dateVal): ?Carbon
    {
        if (!$dateVal || in_array($dateVal, ['0000-00-00', '0000-00-00 00:00:00', '1970-01-01', '1970-01-01 00:00:00'])) {
            return null;
        }
        try {
            return Carbon::parse($dateVal);
        } catch (\Exception $e) {
            return null;
        }
    }
}
