<?php

namespace App\Http\Controllers\admin;

use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\CoreFunctions;
use App\Services\Reports\ClaimStageReportService;
use App\Services\Reports\ReportCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ClaimStageReportController extends Controller
{
    /**
     * Show the Claim Stage Report dashboard
     */
    public function index()
    {
        $company_id = session('company_id');
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $functions = $organogramOptions['functions'] ?? CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
        $departments = $organogramOptions['departments'] ?? CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);

        return view('reports.claim-stage-dashboard', array_merge($organogramOptions, compact('functions', 'departments')));
    }

    /**
     * API: Get monthly stage summary data
     * Uses the EXACT same logic as ReportController::exportClaimStage (monthFinalStage + noClaimEmployeeList)
     */
    public function getStageSummary(Request $request)
    {
        $months = (array) $request->input('months', range(1, 12));
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);

        $filters = array_merge([
            'months' => $months,
        ], $organogramFilters);

        $data = ReportCacheService::remember('stage_summary_v3', $filters, function () use ($months, $organogramFilters) {
            $yearId = \App\Support\CurrentYear::id();
            $companyId = (int) session('company_id', 1);
            $expenseConn = session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDbName = DB::connection($hrimsConn)->getDatabaseName();
            $table = 'y' . $yearId . '_monthexpensefinal';
            $tableDetail = 'y' . $yearId . '_expenseclaims';
            $isVVNR = ($companyId == 11);

            // ─── Build query same as monthFinalStage ───
            $query = DB::connection($expenseConn)
                ->table("$table as mef")
                ->leftJoin("{$hrimsDbName}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin("{$hrimsDbName}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->leftJoin(DB::raw("(
                    SELECT crby, ClaimMonth, COUNT(*) AS total_claims
                    FROM {$tableDetail}
                    WHERE ClaimStatus != 'Deactivate' AND FilledOkay IN (1,0)
                    GROUP BY crby, ClaimMonth
                ) AS ec"), function ($join) {
                    $join->on('ec.crby', '=', 'mef.EmployeeID')
                        ->on('ec.ClaimMonth', '=', 'mef.Month');
                })
                ->whereIn('mef.Month', $months)
                ->where('mef.YearId', $yearId)
                ->whereRaw('IFNULL(ec.total_claims, 0) > 0');

            OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'e.EmployeeID', $hrimsDbName);

            $rows = $query->select([
                'mef.EmployeeID',
                'mef.Month',
                'mef.Status',
                'mef.DateOfSubmit',
                'mef.RecevingDate',
                'mef.Verified_Date',
                'mef.Approved_Date',
                'mef.Finance_Date',
                'mef.Fin_PayDate',
                'mef.Fin_PayAmt',
                DB::raw('IFNULL(ec.total_claims, 0) as claim_count'),
                ...array_diff(\App\Support\Paid::selects($expenseConn, $table, 'mef'), ['mef.Fin_PayDate']), // PAID-01
            ])->get();
            // STAGE-01: claims whose month has no month record are counted as open months.
            $rows = $rows->concat(\App\Services\Reports\ClaimStageReportService::claimMonthsWithoutMonthRow(
                $expenseConn, $hrimsDbName, $yearId, $months,
                fn ($q) => OrganogramFilterHelper::applyToQuery($q, $organogramFilters, 'eg', 'e.EmployeeID', $hrimsDbName)
            ))->values();

            // ─── No Claim count per month (same as export) ───
            // Group rows by month, get unique employee IDs per month, count no-claim per month
            $rowsByMonth = $rows->groupBy('Month');

            // Build results
            $results = [];
            foreach ($months as $month) {
                $results[$month] = [
                    'month' => $month,
                    'total_claimant' => 0,
                    'no_claims_submitted' => 0,
                    'total_claims' => 0,
                    'total_paid' => 0,
                    'percent_complete' => 0,
                    'open_final_submission' => 0,
                    'submitted_courier_not_sent' => 0,
                    'pending_verification' => 0,
                    'pending_approval' => 0,
                    'pending_finance' => 0,
                    'pending_payment' => 0,
                    'paid_pending_courier' => 0,
                ];
            }

            // Classify each row
            foreach ($rows as $row) {
                $m = (int) $row->Month;
                if (!isset($results[$m])) continue;

                $results[$m]['total_claimant']++;

                $stage = $isVVNR
                    ? $this->detectStageVVNR($row)
                    : $this->detectStageVNR($row);

                switch ($stage) {
                    case 'open_final_submission': $results[$m]['open_final_submission']++; break;
                    case 'submitted_courier_not_sent': $results[$m]['submitted_courier_not_sent']++; break;
                    case 'pending_verification': $results[$m]['pending_verification']++; break;
                    case 'pending_approval': $results[$m]['pending_approval']++; break;
                    case 'pending_finance': $results[$m]['pending_finance']++; break;
                    case 'pending_payment': $results[$m]['pending_payment']++; break;
                    case 'paid_pending_courier': $results[$m]['paid_pending_courier']++; break;
                    case 'paid': $results[$m]['total_paid']++; break;
                }
            }

            // Calculate no-claim per month and totals
            foreach ($months as $month) {
                $monthRows = $rowsByMonth->get($month, collect());
                $employeeIdsThisMonth = $monthRows->pluck('EmployeeID')->unique()->toArray();

                // Same query as ReportController::noClaimEmployeeList — count active employees not in list
                $noClaimQuery = DB::connection($hrimsConn)
                    ->table('hrm_employee')
                    ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                    ->where('hrm_employee.EmpStatus', 'A');

                if ($companyId == 1) {
                    $noClaimQuery->where('hrm_employee.CompanyId', 1);
                } elseif ($companyId == 11) {
                    $noClaimQuery->where('hrm_employee.CompanyId', 11)
                        ->where('hrm_employee.EmployeeID', '>', 100000);
                }
                if (!empty($employeeIdsThisMonth)) {
                    $noClaimQuery->whereNotIn('hrm_employee.EmployeeID', $employeeIdsThisMonth);
                }
                OrganogramFilterHelper::applyToQuery($noClaimQuery, $organogramFilters, 'hrm_employee_general', 'hrm_employee.EmployeeID', $hrimsDbName);

                $noClaimCount = $noClaimQuery->count();

                $results[$month]['no_claims_submitted'] = $noClaimCount;
                $results[$month]['total_claims'] = $results[$month]['total_claimant'];
                $results[$month]['total_claimant'] = $results[$month]['total_claimant'] + $noClaimCount;
                $results[$month]['percent_complete'] = $results[$month]['total_claims'] > 0
                    ? round(($results[$month]['total_paid'] / $results[$month]['total_claims']) * 100, 2)
                    : 0;
            }

            return $results;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─── Stage detection helpers (same as ClaimStageReportService) ───
    private function detectStageVNR($row): string
    {
        if (\App\Support\Paid::is($row)) return 'paid'; // PAID-01
        if (!$this->isEmptyDate($row->Finance_Date ?? null)) return 'pending_payment';
        if (!$this->isEmptyDate($row->Approved_Date ?? null)) return 'pending_finance';
        if (!$this->isEmptyDate($row->Verified_Date ?? null)) return 'pending_approval';
        if (!$this->isEmptyDate($row->RecevingDate ?? null)) return 'pending_verification';
        if (!$this->isEmptyDate($row->DateOfSubmit ?? null) && ($row->Status ?? null) === 'Closed') return 'submitted_courier_not_sent';
        if (($row->Status ?? null) === 'Open' && ($row->claim_count ?? 0) > 0) return 'open_final_submission';
        return 'no_claims_submitted';
    }

    private function detectStageVVNR($row): string
    {
        if (!$this->isEmptyDate($row->Verified_Date ?? null)) return 'paid';
        if (!$this->isEmptyDate($row->RecevingDate ?? null)) return 'pending_verification';
        if (\App\Support\Paid::is($row)) return 'paid_pending_courier'; // PAID-01
        if (!$this->isEmptyDate($row->Approved_Date ?? null)) return 'pending_payment';
        if (!$this->isEmptyDate($row->DateOfSubmit ?? null)) return 'pending_approval';
        if (($row->Status ?? null) === 'Open' && ($row->claim_count ?? 0) > 0) return 'open_final_submission';
        return 'no_claims_submitted';
    }

    private function isEmptyDate(?string $date): bool
    {
        return empty($date) || \App\Support\ZeroDate::none($date) || \App\Support\ZeroDate::none($date);
    }

    /**
     * API: Get stage-wise pending detail (employee list)
     */
    public function getStagePendingDetail(Request $request)
    {
        $service = new ClaimStageReportService();

        $stage = $request->input('stage', 'open');
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => (array) ($request->input('months') ?? []),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        // If months is empty but month param exists, use it
        if (empty($filters['months']) && $request->input('month')) {
            $filters['months'] = [(int)$request->input('month')];
        }

        // Ensure months values are integers
        if (!empty($filters['months'])) {
            $filters['months'] = array_map('intval', $filters['months']);
        }

        \Log::info('getStagePendingDetail', ['stage' => $stage, 'filters' => $filters, 'raw_months' => $request->input('months')]);

        $data = $service->getStagePendingDetail($stage, $filters);

        // If export=excel, return downloadable Excel
        if ($request->input('export') === 'excel') {
            $export = new \App\Exports\Reports\StagePendingDetailExport($stage, $filters);
            $monthLabel = !empty($filters['months']) ? implode('_', (array)$filters['months']) : 'all';
            $fileName = "Stage_Detail_{$stage}_{$monthLabel}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';
            return \Maatwebsite\Excel\Facades\Excel::download($export, $fileName);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get department-wise stage summary (pivot)
     */
    public function getDepartmentStageSummary(Request $request)
    {
        $service = new ClaimStageReportService();

        $stage = $request->input('stage', 'submitted_no_courier');
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => (array) $request->input('months', range(1, 12)),
            'stage' => $stage,
        ], $organogramFilters);

        $data = $service->getDepartmentStageSummary($stage, $filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get TAT analysis data
     */
    public function getTATAnalysis(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getTATAnalysis($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get submission timeline data (for bar chart)
     */
    public function getSubmissionTimeline(Request $request)
    {
        $service = new ClaimStageReportService();
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getSubmissionTimeline($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get F&F status
     */
    public function getFnFStatus(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getFnFStatus($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get Courier Posting Status
     */
    public function getCourierPostingStatus(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getCourierPostingStatus($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get Management Claim Status
     */
    public function getManagementClaimStatus(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getManagementClaimStatus($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get Imprest Promotions
     */
    public function getImprestPromotions(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getImprestPromotions($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get Employee-wise TAT Analysis
     */
    public function getEmployeeWiseTATAnalysis(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
            'employee_id' => $request->input('employee_id'),
        ], $organogramFilters);

        $data = $service->getEmployeeWiseTATAnalysis($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get Claim-wise TAT Analysis
     */
    public function getClaimWiseTATAnalysis(Request $request)
    {
        $service = new ClaimStageReportService();

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
            'claim_type_id' => $request->input('claim_type_id'),
        ], $organogramFilters);

        $data = $service->getClaimWiseTATAnalysis($filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get Submission Return Report
     */
    public function getSubmissionReturnReport(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        try {
            $yearId = \App\Support\CurrentYear::id();
            $expenseConn = session('expense_connection', 'expense');
            $hrimsDb = DB::connection('hrims')->getDatabaseName();

            $mefTable = 'y' . $yearId . '_monthexpensefinal';

            $query = DB::connection($expenseConn)
                ->table("{$mefTable} as mef")
                ->selectRaw("mef.EmployeeID,
                    mef.Month,
                    emp.EmpCode as emp_code,
                    CONCAT(emp.Fname, ' ', emp.Sname, ' ', emp.Lname) as employee_name,
                    cd.department_name,
                    cf.function_name,
                    MIN(mef.Crdate) as created_at")
                ->join("{$hrimsDb}.hrm_employee as emp", 'mef.EmployeeID', '=', 'emp.EmployeeID')
                ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'emp.EmployeeID', '=', 'eg.EmployeeID')
                ->leftJoin("{$hrimsDb}.core_departments as cd", 'eg.DepartmentId', '=', 'cd.id')
                ->leftJoin("{$hrimsDb}.core_functions as cf", 'eg.EmpFunction', '=', 'cf.id')
                ->where('mef.SubPermission', '=', 'Y')
                ->groupBy('mef.EmployeeID', 'mef.Month');

            OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

            $query->orderByDesc('created_at');

            $data = $query->get();

            // Transform data for consistent output
            $result = $data->map(function($item) {
                return (array) $item;
            })->toArray();

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────────

    /**
     * Export: Stage Summary to Excel
     */
    public function exportStageSummary(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => (array) ($request->input('months') ?? range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "Claim_Stage_Report_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\ClaimStageSummaryExportNew($filters),
            $filename
        );
    }

    /**
     * Export: Pending Detail to Excel
     */
    public function exportPendingDetail(Request $request)
    {
        $stage = $request->input('stage', 'open');
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'month' => $request->input('month'),
            'months' => (array) ($request->input('months') ?? []),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "Stage_Pending_Detail_{$company}_{$stage}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\StagePendingDetailExport($stage, $filters),
            $filename
        );
    }

    /**
     * Export: TAT Analysis to Excel
     */
    public function exportTATAnalysis(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "TAT_Analysis_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\TATAnalysisExport($filters),
            $filename
        );
    }

    /**
     * Export: Employee-wise TAT Analysis to Excel
     */
    public function exportEmployeeWiseTAT(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
            'employee_id' => $request->input('employee_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "Employee_Wise_TAT_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\EmployeeWiseTATExport($filters),
            $filename
        );
    }

    /**
     * Export: Claim-wise TAT Analysis to Excel
     */
    public function exportClaimWiseTAT(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
            'claim_type_id' => $request->input('claim_type_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "Claim_Wise_TAT_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\ClaimWiseTATExport($filters),
            $filename
        );
    }

    /**
     * Export: Submission Return Report to Excel
     */
    public function exportSubmissionReturn(Request $request)
    {
        try {
            $data = $this->getSubmissionReturnReport($request)->getData();

            if (!$data->success) {
                return back()->with('error', 'Failed to export data');
            }

            $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
            $filename = "Submission_Return_Report_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\Reports\SubmissionReturnExport($data->data),
                $filename
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export: F&F Status to Excel
     */
    public function exportFnFStatus(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "FnF_Status_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\FnFStatusExport($filters),
            $filename
        );
    }

    /**
     * Export: Full Dashboard Report (all sheets combined)
     */
    public function exportFullReport(Request $request)
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        $filename = "Claim_Dashboard_Full_Report_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Reports\ClaimDashboardFullExport($filters),
            $filename
        );
    }

    // ── PDF EXPORTS ─────────────────────────────────────────────────────────

    /**
     * PDF Export: Stage Summary
     */
    public function pdfStageSummary(Request $request)
    {
        $service = new ClaimStageReportService();
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getMonthlyStageSummary($filters);
        $months = array_keys($data);
        sort($months);

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';

        $firstMonth = $monthNames[$months[0] ?? 1] ?? 'Jan';
        $lastMonth = $monthNames[end($months) ?? 12] ?? 'Dec';
        $periodLabel = "{$firstMonth}'25 - {$lastMonth}'26";

        $pdf = Pdf::loadView('reports.pdf.claim-stage-summary', [
            'data' => $data,
            'months' => $months,
            'monthNames' => $monthNames,
            'company' => $company,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d-m-Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("Claim_Stage_Report_{$company}_" . now()->format('d-m-Y_h-i-s_A') . '.pdf');
    }

    /**
     * PDF Export: Pending Detail
     */
    public function pdfPendingDetail(Request $request)
    {
        $service = new ClaimStageReportService();
        $stage = $request->input('stage', 'open');
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        $filters = array_merge([
            'month' => $request->input('month'),
            'months' => (array) ($request->input('months') ?? []),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ], $organogramFilters);

        $data = $service->getStagePendingDetail($stage, $filters);

        $stageLabels = [
            'open' => 'Total open, final submission not done',
            'submitted_no_courier' => 'Total submitted, courier not sent',
            'pending_verification' => 'Total received, pending verification',
            'pending_approval' => 'Total verified, pending manager approval',
            'pending_payment' => 'Total approved, pending payment',
        ];

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';

        $pdf = Pdf::loadView('reports.pdf.pending-detail', [
            'data' => $data,
            'stageLabel' => $stageLabels[$stage] ?? $stage,
            'monthNames' => $monthNames,
            'company' => $company,
            'periodLabel' => "FY 2025-26",
            'generatedAt' => now()->format('d-m-Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("Pending_Detail_{$company}_{$stage}_" . now()->format('d-m-Y_h-i-s_A') . '.pdf');
    }

    // ── PRINT VIEW ──────────────────────────────────────────────────────────

    /**
     * Print-ready view (PPT-style) for stage summary
     */
    public function printView(Request $request)
    {
        $service = new ClaimStageReportService();
        $filters = [
            'months' => $request->input('months', range(1, 12)),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ];

        $data = $service->getMonthlyStageSummary($filters);
        $tatData = $service->getTATAnalysis($filters);
        $months = array_keys($data);
        sort($months);

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';

        return view('reports.print.claim-stage-print', compact(
            'data', 'tatData', 'months', 'monthNames', 'company', 'filters'
        ));
    }

    // ── CACHE MANAGEMENT ────────────────────────────────────────────────────

    /**
     * Clear report cache (called after claim status changes)
     */
    public function clearCache()
    {
        ReportCacheService::flushAll();
        return response()->json(['success' => true, 'message' => 'Report cache cleared']);
    }

    // ── FULL YEAR EMPLOYEE REPORT (No Stage Separation) ─────────────────────

    public function fullYearReport()
    {
        $company_id = session('company_id');
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $functions = CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
        $departments = CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);
        $financialYears = \Illuminate\Support\Facades\DB::connection(session('expense_connection', 'expense'))
            ->table('financialyear')->orderByDesc('YearId')->get(['YearId', 'Year']);
        $employees = \Illuminate\Support\Facades\DB::connection(session('hrims_connection', 'hrims'))
            ->table('hrm_employee as e')
            ->select('e.EmployeeID', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', session('company_id'))
            ->orderBy('e.EmpCode_New')
            ->get();
        return view('admin.claim_stage_fy', array_merge($organogramOptions, compact('functions', 'departments', 'financialYears', 'employees')));
    }

    public function getFullYearData(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $service = new ClaimStageReportService();
        $yearId = \App\Support\CurrentYear::id();
        $companyId = (int) session('company_id', 1);
        $expenseConn = session('expense_connection', 'expense');
        $hrimsConn = session('hrims_connection', 'hrims');
        $hrimsDb = \Illuminate\Support\Facades\DB::connection($hrimsConn)->getDatabaseName();

        $table = 'y' . $yearId . '_monthexpensefinal';
        $functionId = $request->input('function_id');
        $departmentId = $request->input('department_id');

        $query = \Illuminate\Support\Facades\DB::connection($expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as dept", 'eg.DepartmentId', '=', 'dept.id')
            ->leftJoin("{$hrimsDb}.core_verticals as ver", 'eg.EmpVertical', '=', 'ver.id')
            ->leftJoin("{$hrimsDb}.core_designation as desig", 'eg.DesigId', '=', 'desig.id')
            ->where('mef.YearId', $yearId)
            ->select([
                'e.EmpCode_New as emp_code',
                \Illuminate\Support\Facades\DB::raw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as employee_name"),
                'e.EmpStatus as status',
                'dept.department_name',
                'ver.vertical_name',
                'desig.designation_name',
                'mef.Month',
                'mef.Status as month_status',
                'mef.DateOfSubmit',
                'mef.RecevingDate',
                'mef.Verified_Date',
                'mef.Verified_Amount',
                'mef.Approved_Date',
                'mef.Approved_Amount',
                'mef.Finance_Date',
                'mef.Finance_Amount',
                'mef.Fin_PayDate',
                'mef.Fin_PayAmt',
                'mef.Claim_Amount',
            ]);

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'e.EmployeeID', $hrimsDb);

        $rows = $query->orderBy('e.EmpCode_New')->orderBy('mef.Month')->get();

        // Group by employee
        $employees = [];
        foreach ($rows as $row) {
            $key = $row->emp_code;
            if (!isset($employees[$key])) {
                $employees[$key] = [
                    'emp_code' => $row->emp_code,
                    'employee_name' => $row->employee_name,
                    'status' => $row->status,
                    'department' => $row->department_name ?? '',
                    'vertical' => $row->vertical_name ?? '',
                    'designation' => $row->designation_name ?? '',
                    'months' => [],
                ];
            }
            $employees[$key]['months'][(int)$row->Month] = [
                'month_status' => $row->month_status,
                'claim_amount' => (float)($row->Claim_Amount ?? 0),
                'submit_date' => $row->DateOfSubmit,
                'courier_date' => $row->RecevingDate,
                'verified_date' => $row->Verified_Date,
                'verified_amount' => (float)($row->Verified_Amount ?? 0),
                'approved_date' => $row->Approved_Date,
                'approved_amount' => (float)($row->Approved_Amount ?? 0),
                'finance_date' => $row->Finance_Date,
                'finance_amount' => (float)($row->Finance_Amount ?? 0),
                'pay_date' => $row->Fin_PayDate,
                'pay_amount' => (float)($row->Fin_PayAmt ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => array_values($employees),
            'total' => count($employees),
        ]);
    }

    public function exportFullYearData(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 900);
        set_time_limit(900);

        $yearId = (int) ($request->input('year_id') ?: \App\Support\CurrentYear::id());
        $expenseConn = session('expense_connection', 'expense');
        $hrimsConn = session('hrims_connection', 'hrims');
        $hrimsDb = \Illuminate\Support\Facades\DB::connection($hrimsConn)->getDatabaseName();
        $table = 'y' . $yearId . '_monthexpensefinal';
        $functionId = $request->input('function_id');
        $departmentId = $request->input('department_id');
        $employeeId = $request->input('employee_id');
        $monthStatus = $request->input('month_status', 'all'); // all, open, closed
        $selectedColumns = $request->input('columns') ? explode(',', $request->input('columns')) : [];
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);

        $whereClauses = ["mef.YearId = ?"];
        $bindings = [$yearId];
        if ($employeeId) { $whereClauses[] = "mef.EmployeeID = ?"; $bindings[] = $employeeId; }
        // Month status filter: open = not submitted, closed = submitted
        if ($monthStatus === 'open') {
            $whereClauses[] = "(mef.Status IS NULL OR mef.Status = '' OR mef.Status = 'Open')";
        } elseif ($monthStatus === 'closed') {
            $whereClauses[] = "mef.Status NOT IN ('', 'Open') AND mef.Status IS NOT NULL";
        }
        OrganogramFilterHelper::appendRawSqlFilters($whereClauses, $bindings, $organogramFilters, 'eg', 'e.EmployeeID', $hrimsDb);
        $where = "WHERE " . implode(" AND ", $whereClauses);

        // All possible columns
        $allColumns = [
            'emp_code' => 'Emp Code',
            'name' => 'Name',
            'status' => 'Status',
            'fy_year' => 'FY Year',
            'function' => 'Function',
            'vertical' => 'Vertical',
            'department' => 'Department',
            'sub_department' => 'Sub Department',
            'section' => 'Section',
            'designation' => 'Designation',
            'grade' => 'Grade',
            'cost_center' => 'Cost Center',
            'bu' => 'Business Unit',
            'zone' => 'Zone',
            'region' => 'Region',
            'territory' => 'Territory',
            'reporting' => 'Reporting To',
            'month' => 'Month',
            'claim_amount' => 'Claim Amount',
            'submit_date' => 'Submit Date',
            'courier_date' => 'Courier Received',
            'verified_date' => 'Verified Date',
            'verified_amount' => 'Verified Amount',
            'approved_date' => 'Approved Date',
            'approved_amount' => 'Approved Amount',
            'finance_date' => 'Finance Date',
            'finance_amount' => 'Finance Amount',
            'pay_date' => 'Payment Date',
            'pay_amount' => 'Payment Amount',
            'total_claims' => 'Total Claims',
            'courier_send_date' => 'Courier Send Date',
            'last_mail_date' => 'Last Mail Date',
            'last_mail_subject' => 'Last Mail Subject',
            'stage' => 'Current Stage',
        ];

        if (empty($selectedColumns)) {
            $selectedColumns = array_keys($allColumns);
        }

        // Remove claims_uploaded column if only closed months are selected
        if ($monthStatus === 'closed') {
            $selectedColumns = array_values($selectedColumns);
        }

        $expenseClaimsTable = "y{$yearId}_expenseclaims";
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $paidCols = implode('', array_map(fn ($c) => " mef.{$c},", array_diff(\App\Support\Paid::existing($expenseConn, $table), ['Fin_PayDate']))); // PAID-01
        $query = "
            SELECT e.EmpCode_New, CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as FullName,
                e.EmpStatus,
                IFNULL(fun.function_name, '') as function_name,
                IFNULL(ver.vertical_name, '') as vertical_name,
                IFNULL(dept.department_name, '') as department_name,
                IFNULL(subdept.sub_department_name, '') as sub_department_name,
                IFNULL(sec.section_name, '') as section_name,
                IFNULL(desig.designation_name, '') as designation_name,
                IFNULL(gr.grade_name, '') as grade_name,
                IFNULL(st.state_name, '') as state_name,
                IFNULL(bu.business_unit_name, '') as business_unit_name,
                IFNULL(zn.zone_name, '') as zone_name,
                IFNULL(rg.region_name, '') as region_name,
                IFNULL(tr.territory_name, '') as territory_name,
                IFNULL(eg.ReportingName, '') as reporting_name,
                mef.Month, mef.Claim_Amount, mef.DateOfSubmit, mef.RecevingDate,
                mef.Verified_Date, mef.Verified_Amount, mef.Approved_Date, mef.Approved_Amount,
                mef.Finance_Date, mef.Finance_Amount, mef.Fin_PayDate, mef.Fin_PayAmt,
                mef.PostDate,{$paidCols}
                (SELECT COUNT(*) FROM {$expenseClaimsTable} WHERE CrBy = mef.EmployeeID AND ClaimMonth = mef.Month AND ClaimStatus != 'Deactivate' AND FilledOkay IN(1,0)) as total_claims,
                NULL as last_mail_date,
                NULL as last_mail_subject
            FROM {$table} mef
            INNER JOIN {$hrimsDb}.hrm_employee e ON mef.EmployeeID = e.EmployeeID
            LEFT JOIN {$hrimsDb}.hrm_employee_general eg ON e.EmployeeID = eg.EmployeeID
            LEFT JOIN {$hrimsDb}.core_functions fun ON eg.EmpFunction = fun.id
            LEFT JOIN {$hrimsDb}.core_verticals ver ON eg.EmpVertical = ver.id
            LEFT JOIN {$hrimsDb}.core_departments dept ON eg.DepartmentId = dept.id
            LEFT JOIN {$hrimsDb}.core_sub_department_master subdept ON eg.SubDepartmentId = subdept.id
            LEFT JOIN {$hrimsDb}.core_section sec ON eg.EmpSection = sec.id
            LEFT JOIN {$hrimsDb}.core_designation desig ON eg.DesigId = desig.id
            LEFT JOIN {$hrimsDb}.core_grades gr ON eg.GradeId = gr.id
            LEFT JOIN {$hrimsDb}.core_states st ON eg.CostCenter = st.id
            LEFT JOIN {$hrimsDb}.core_business_unit bu ON eg.BUId = bu.id
            LEFT JOIN {$hrimsDb}.core_zones zn ON eg.ZoneId = zn.id
            LEFT JOIN {$hrimsDb}.core_regions rg ON eg.RegionId = rg.id
            LEFT JOIN {$hrimsDb}.core_territory tr ON eg.TerrId = tr.id
            {$where}
            ORDER BY e.EmpCode_New, mef.Month
        ";

        $data = \Illuminate\Support\Facades\DB::connection($expenseConn)->select($query, $bindings);

        // Get FY label for the fy_year column
        $yearLabel = \Illuminate\Support\Facades\DB::connection($expenseConn)->table('financialyear')->where('YearId', $yearId)->value('Year') ?? $yearId;

        // Build Excel using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Claim Stage FY Report');

        // Headers
        $headers = ['S.No'];
        foreach ($selectedColumns as $col) {
            if (isset($allColumns[$col])) $headers[] = $allColumns[$col];
        }
        $sheet->fromArray([$headers], null, 'A1');

        // Style header row
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
        ]);

        // Data rows
        $rowNum = 2;
        $sno = 1;
        foreach ($data as $row) {
            // Skip open months with no claims uploaded
            if ($monthStatus === 'open' && (int)($row->total_claims ?? 0) === 0) {
                continue;
            }
            // For "all" status, also skip open months with no claims
            if ($monthStatus === 'all') {
                $isOpenMonth = !$row->DateOfSubmit || \App\Support\ZeroDate::none($row->DateOfSubmit);
                if ($isOpenMonth && (int)($row->total_claims ?? 0) === 0) {
                    continue;
                }
            }
            $stage = '-';
            if (\App\Support\Paid::is($row)) $stage = 'Paid'; // PAID-01
            elseif ($row->Finance_Date && \App\Support\ZeroDate::has($row->Finance_Date)) $stage = 'Pending Payment';
            elseif ($row->Approved_Date && \App\Support\ZeroDate::has($row->Approved_Date)) $stage = 'Pending Finance';
            elseif ($row->Verified_Date && \App\Support\ZeroDate::has($row->Verified_Date)) $stage = 'Pending Approval';
            elseif ($row->RecevingDate && \App\Support\ZeroDate::has($row->RecevingDate)) $stage = 'Pending Verification';
            elseif ($row->DateOfSubmit && \App\Support\ZeroDate::has($row->DateOfSubmit)) $stage = 'Submitted - Pending Courier';
            else $stage = 'Pending Submission';

            $csvRow = [$sno];
            foreach ($selectedColumns as $col) {
                switch ($col) {
                    case 'emp_code': $csvRow[] = $row->EmpCode_New ?? ''; break;
                    case 'name': $csvRow[] = $row->FullName ?? ''; break;
                    case 'status': $csvRow[] = $row->EmpStatus ?? ''; break;
                    case 'fy_year': $csvRow[] = $yearLabel; break;
                    case 'function': $csvRow[] = $row->function_name ?? ''; break;
                    case 'vertical': $csvRow[] = $row->vertical_name ?? ''; break;
                    case 'department': $csvRow[] = $row->department_name ?? ''; break;
                    case 'sub_department': $csvRow[] = $row->sub_department_name ?? ''; break;
                    case 'section': $csvRow[] = $row->section_name ?? ''; break;
                    case 'designation': $csvRow[] = $row->designation_name ?? ''; break;
                    case 'grade': $csvRow[] = $row->grade_name ?? ''; break;
                    case 'cost_center': $csvRow[] = $row->state_name ?? ''; break;
                    case 'bu': $csvRow[] = $row->business_unit_name ?? ''; break;
                    case 'zone': $csvRow[] = $row->zone_name ?? ''; break;
                    case 'region': $csvRow[] = $row->region_name ?? ''; break;
                    case 'territory': $csvRow[] = $row->territory_name ?? ''; break;
                    case 'reporting': $csvRow[] = $row->reporting_name ?? ''; break;
                    case 'month': $csvRow[] = $months[(int)($row->Month ?? 0)] ?? ''; break;
                    case 'claim_amount': $csvRow[] = (float)($row->Claim_Amount ?? 0); break;
                    case 'submit_date': $csvRow[] = $row->DateOfSubmit ?? ''; break;
                    case 'courier_date': $csvRow[] = $row->RecevingDate ?? ''; break;
                    case 'verified_date': $csvRow[] = $row->Verified_Date ?? ''; break;
                    case 'verified_amount': $csvRow[] = (float)($row->Verified_Amount ?? 0); break;
                    case 'approved_date': $csvRow[] = $row->Approved_Date ?? ''; break;
                    case 'approved_amount': $csvRow[] = (float)($row->Approved_Amount ?? 0); break;
                    case 'finance_date': $csvRow[] = $row->Finance_Date ?? ''; break;
                    case 'finance_amount': $csvRow[] = (float)($row->Finance_Amount ?? 0); break;
                    case 'pay_date': $csvRow[] = $row->Fin_PayDate ?? ''; break;
                    case 'pay_amount': $csvRow[] = (float)($row->Fin_PayAmt ?? 0); break;
                    case 'total_claims': $csvRow[] = (int)($row->total_claims ?? 0); break;
                    case 'courier_send_date': $csvRow[] = $row->PostDate ?? ''; break;
                    case 'last_mail_date': $csvRow[] = $row->last_mail_date ?? ''; break;
                    case 'last_mail_subject': $csvRow[] = $row->last_mail_subject ?? ''; break;
                    case 'stage': $csvRow[] = $stage; break;
                }
            }
            $sheet->fromArray([$csvRow], null, "A{$rowNum}");
            $rowNum++;
            $sno++;
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle("A1:{$lastCol}" . ($rowNum - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Store to temp and return URL (same as /claim-stage)
        $yearLabel = \Illuminate\Support\Facades\DB::connection($expenseConn)->table('financialyear')->where('YearId', $yearId)->value('Year') ?? $yearId;
        $fileName = "Claim_Stage_FY_{$yearLabel}_" . now()->format('Ymd_His') . '.xlsx';
        $path = 'temp/' . $fileName;

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        // FILES-01 (UAT-03): private disk, downloaded through the authorised /exports/download route
        $fullPath = \Illuminate\Support\Facades\Storage::disk(\App\Support\PrivateExport::DISK)->path($path);
        @mkdir(dirname($fullPath), 0750, true);
        $writer->save($fullPath);

        return response()->json([
            'status' => 'success',
            'file_url' => \App\Support\PrivateExport::url($path),
        ]);
    }
}
