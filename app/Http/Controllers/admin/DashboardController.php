<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimRatingResult;
use App\Models\ExpenseClaim;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\DatabaseSwitcher;

class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        $companyId = (int) session('company_id', 1);
        $yearId = \App\Support\CurrentYear::id();
        $employeeId = session('employee_id');
        $empRole = session('emp_role', 'E');

        // Check if user/session belongs to VVNR (Company 11 or 2)
        $isVVNR = ($companyId === 11 || $companyId === 2);
        if (!$isVVNR && !empty($employeeId)) {
            try {
                $empCompanyId = $this->hrimsDb()->table('hrm_employee')->where('EmployeeID', $employeeId)->value('CompanyId');
                if ($empCompanyId == 11 || $empCompanyId == 2) {
                    $isVVNR = true;
                }
            } catch (\Throwable $e) {}
        }

        // Dynamic table: y7_expenseclaims etc.
        $table = ExpenseClaim::tableName($yearId);

        // Prefetch team members early (direct reportees from hrm_employee_general)
        $teamMembers = $this->hrimsDb()->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $employeeId)
            ->where('e.EmpStatus', 'A')
            ->pluck('eg.EmployeeID')
            ->unique()
            ->values()
            ->toArray();

        $hasTeam = !empty($teamMembers);

        // Determine user roles/access
        $isApprover = ($empRole === 'A') || $hasTeam || (session('reporting_vess') == 'Y') || (session('reporting_nursery') == 'Y');
        $isVerifier = $user->can('section_verifier');
        $isFinance = $user->can('payment_section');
        $isReports = $user->can('section_reports');

        // Fetch all closed/submitted months for employee & team in current YearId
        $allMembers = array_values(array_unique(array_merge([$employeeId], $teamMembers)));

        $mefMonths = $this->expenseDb()->table("y{$yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $allMembers)
            ->where('YearId', $yearId)
            ->whereNotNull('DateOfSubmit')
            ->where('DateOfSubmit', '!=', '0000-00-00')
            ->pluck('Month')
            ->toArray();

        $ecMonths = $this->expenseDb()->table("y{$yearId}_expenseclaims")
            ->whereIn('CrBy', $allMembers)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->pluck('ClaimMonth')
            ->toArray();

        $closedMonths = array_values(array_unique(array_filter(array_merge($mefMonths, $ecMonths))));
        sort($closedMonths);

        if (empty($closedMonths)) {
            $closedMonths = range(1, 12);
        }

        // Determine requested month filter or default to current month - 1
        $selectedMonth = $request->get('month');
        $currentMonthNum = (int) now()->month;
        $defaultMonth = $currentMonthNum === 1 ? 12 : $currentMonthNum - 1;

        if ($selectedMonth && is_numeric($selectedMonth) && $selectedMonth >= 1 && $selectedMonth <= 12) {
            $displayMonth = (int) $selectedMonth;
        } else {
            $displayMonth = $defaultMonth;
        }

        if (!in_array($displayMonth, $closedMonths)) {
            $closedMonths[] = $displayMonth;
            sort($closedMonths);
        }

        // Determine comparison month (previous closed month in list or displayMonth - 1)
        if (!empty($closedMonths) && in_array($displayMonth, $closedMonths)) {
            $idx = array_search($displayMonth, $closedMonths);
            $comparisonMonth = ($idx !== false && $idx > 0) ? (int) $closedMonths[$idx - 1] : ($displayMonth === 1 ? 12 : $displayMonth - 1);
        } else {
            $comparisonMonth = $displayMonth === 1 ? 12 : $displayMonth - 1;
        }
        $currentMonth = $displayMonth;

        // Claimant stats (common for all)
        $claimantStats = $this->getClaimantStats($employeeId, $table);

        // Approver stats
        $approverStats = $isApprover
            ? $this->getApproverStats($employeeId, $table)
            : null;

        // Verifier stats (verified by me)
        $verifierStats = $isVerifier
            ? $this->getVerifierStats($employeeId, $table, $currentMonth)
            : null;

        // Missing Imprest list — shown in Verifier tab for verifiers
        $missingImprestList = $isVerifier ? $this->getMissingImprestList($companyId) : collect();

        // Finance stats (financed by me)
        $financeStats = $isFinance
            ? $this->getFinanceStats($employeeId, $table, $currentMonth)
            : null;

        // Top 10 ratings - role-specific
        $topRatings = $this->getTopRatings($companyId, $yearId, $empRole, $isVerifier, $isFinance);

        // Report summary stats
        $reportStats = $this->getReportStats($table, $isApprover, $isVerifier, $isFinance);

        // Monthly chart data
        $monthlyClaimData = $this->getMonthlyClaimData($employeeId, $table);
        $approverMonthlyData = $isApprover
            ? $this->getApproverMonthlyData($employeeId, $table)
            : [];
        $financeMonthlyData = $isFinance
            ? $this->getFinanceMonthlyData($employeeId, $table)
            : [];

        // MRM Stage Summary (claim stage report logic)
        $mrmStageData = $this->getMrmStageSummary();

        // Reporting Manager Dashboard data (for Approver tab)
        $rmDashData = $isApprover ? $this->getReportingManagerDashData($employeeId, $yearId, $table, $displayMonth, $teamMembers) : null;

        // Stagewise TAT Analysis for the employee (selected month data, compared with month before)
        $stagewiseTAT = $this->getStagewiseTATForEmployee($employeeId, $yearId, $displayMonth, $isVVNR);
        $stagewiseTATPrev = $this->getStagewiseTATForEmployee($employeeId, $yearId, $comparisonMonth, $isVVNR);

        // Expense Overview by Claim Type (selected month)
        $expenseOverview = $this->getExpenseOverviewByType($employeeId, $table, $displayMonth);

        // Odometer Summary (selected month)
        $odometerSummary = $this->getOdometerSummary($employeeId, $yearId, $displayMonth);

        // Odometer for previous month & current month (for combined KPI card)
        $calCurrentMonth = (int) now()->month;
        $calPrevMonth = $calCurrentMonth === 1 ? 12 : $calCurrentMonth - 1;

        if ($displayMonth === $calCurrentMonth) {
            $kmPrevMonth = $comparisonMonth;
            $kmCurrMonth = $displayMonth;
        } else {
            $kmPrevMonth = $displayMonth;
            $kmCurrMonth = $calCurrentMonth;
        }

        $odometerSummaryPrev = $this->getOdometerSummary($employeeId, $yearId, $kmPrevMonth);
        $odometerSummaryCurr = $this->getOdometerSummary($employeeId, $yearId, $kmCurrMonth);

        // Amount Paid (selected month, compared with month before)
        $amountPaidMTD = $this->getAmountPaidMTD($employeeId, $table, $displayMonth);
        $amountPaidPrev = $this->getAmountPaidMTD($employeeId, $table, $comparisonMonth);

        // Claims Paid (selected month, compared with month before)
        $claimsPaidMTD = $this->getClaimsPaidMTD($employeeId, $table, $displayMonth);
        $claimsPaidPrev = $this->getClaimsPaidMTD($employeeId, $table, $comparisonMonth);

        // Month claims summary for employee (Total submitted, Draft, Verified, Approved, Denied)
        $monthClaimsSummary = $this->getMonthClaimsSummary($employeeId, $yearId, $displayMonth, $isVVNR);

        // My Claims for the table
        $myClaimsCurrentMonth = $this->getMyClaimsTable($employeeId, $table, $displayMonth, $isVVNR);

        // Odometer Trend (last 6 months)
        $odometerTrend = $this->getOdometerTrend($employeeId, $yearId);

        // Financial years for dropdown filter
        $financialYears = $this->expenseDb()->table('financialyear')
            ->orderByDesc('YearId')
            ->get(['YearId', 'Year']);
        $currentFYLabel = $financialYears->firstWhere('YearId', $yearId)->Year ?? '';

        return view('employee.dashboard', compact(
            'isApprover',
            'isVerifier',
            'isFinance',
            'isReports',
            'claimantStats',
            'approverStats',
            'verifierStats',
            'financeStats',
            'topRatings',
            'reportStats',
            'empRole',
            'monthlyClaimData',
            'approverMonthlyData',
            'financeMonthlyData',
            'mrmStageData',
            'rmDashData',
            'stagewiseTAT',
            'stagewiseTATPrev',
            'expenseOverview',
            'odometerSummary',
            'odometerSummaryPrev',
            'odometerSummaryCurr',
            'kmPrevMonth',
            'kmCurrMonth',
            'amountPaidMTD',
            'amountPaidPrev',
            'claimsPaidMTD',
            'claimsPaidPrev',
            'myClaimsCurrentMonth',
            'odometerTrend',
            'currentMonth',
            'displayMonth',
            'comparisonMonth',
            'closedMonths',
            'selectedMonth',
            'financialYears',
            'currentFYLabel',
            'missingImprestList',
            'isVVNR',
            'monthClaimsSummary'
        ));
    }

    private function expenseDb()
    {
        return DB::connection(session('expense_connection', DatabaseSwitcher::getExpenseConnection()));
    }

    /**
     * Get active employees in eligible grades/departments who have no fixed imprest in the ledger.
     * Shown as an alert card in the Verifier tab.
     */
    private function getMissingImprestList(int $companyId): \Illuminate\Support\Collection
    {
        try {
            $hrimsConn = DatabaseSwitcher::getHrimsConnection();
            $expConn   = session('expense_connection', DatabaseSwitcher::getExpenseConnection());

            $gradeAmounts = ['J1'=>20000,'J2'=>20000,'J3'=>40000,'J4'=>40000,'M1'=>40000];
            $eligibleGradeKeys = ['J1', 'J2', 'J3', 'J4', 'M1', 'J-1', 'J-2', 'J-3', 'J-4', 'M-1'];

            // Fetch eligible grade IDs from core_grades using normalized name matching
            $eligibleGradeIds = [];
            try {
                $eligibleGradeIds = DB::connection($hrimsConn)
                    ->table('core_grades')
                    ->get()
                    ->filter(function ($g) use ($gradeAmounts) {
                        $clean = strtoupper(str_replace(['-', ' '], '', $g->grade_name ?? ''));
                        return isset($gradeAmounts[$clean]);
                    })
                    ->pluck('id')
                    ->toArray();
            } catch (\Exception $e) {
                \Log::warning('DashboardController fetch eligible grade IDs failed: ' . $e->getMessage());
            }

            $empQuery = DB::connection($hrimsConn)
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->join('core_grades as g',            'eg.GradeId',    '=', 'g.id')
                ->join('core_departments as d',       'eg.DepartmentId','=', 'd.id')
                ->where('e.EmpStatus', 'A')
                ->where(function ($q) use ($eligibleGradeIds, $eligibleGradeKeys) {
                    if (!empty($eligibleGradeIds)) {
                        $q->whereIn('eg.GradeId', $eligibleGradeIds);
                    } else {
                        $q->whereIn('g.grade_name', $eligibleGradeKeys);
                    }
                })
                ->where(function ($q) {
                    $q->where(DB::raw('LOWER(d.department_name)'), 'like', '%sales%')
                      ->orWhere(DB::raw('LOWER(d.department_name)'), 'like', '%pd%')
                      ->orWhere(DB::raw('LOWER(d.department_name)'), 'like', '%plant disease%');
                })
                ->select(
                    'e.EmployeeID',
                    DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS emp_name"),
                    DB::raw("COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code"),
                    'eg.DateJoining',
                    'g.grade_name',
                    'd.department_name'
                );

            $employees = (clone $empQuery)->where('e.CompanyId', $companyId)->get();

            if ($employees->isEmpty()) {
                $employees = $empQuery->get();
            }

            if ($employees->isEmpty()) {
                return collect();
            }

            // Ledger balances per employee
            $ledgerBalances = collect();
            try {
                $ledgerBalances = DB::connection($expConn)
                    ->table('employee_ledger_balances')
                    ->whereIn('employee_id', $employees->pluck('EmployeeID')->toArray())
                    ->groupBy('employee_id')
                    ->select(
                        'employee_id',
                        DB::raw('COALESCE(MAX(fixed_imprest_amt_1), 0) as amt_1'),
                        DB::raw('COALESCE(MAX(fixed_imprest_amt_2), 0) as amt_2')
                    )
                    ->get()
                    ->keyBy('employee_id');
            } catch (\Exception $e) {
                \Log::warning('DashboardController ledger balance check warning: ' . $e->getMessage());
            }

            return $employees
                ->map(function ($e) use ($gradeAmounts, $ledgerBalances) {
                    $cleanGrade = strtoupper(str_replace(['-', ' '], '', $e->grade_name ?? ''));
                    $e->eligible_amount = $gradeAmounts[$cleanGrade] ?? 0;

                    $lb = $ledgerBalances->get($e->EmployeeID);
                    $e->current_imprest = $lb ? ((float)$lb->amt_1 + (float)$lb->amt_2) : 0.0;
                    $e->request_amount  = max(0.0, (float)$e->eligible_amount - (float)$e->current_imprest);
                    $e->is_upgrade      = ($e->current_imprest > 0);

                    return $e;
                })
                ->filter(fn($e) => $e->request_amount > 0)
                ->values();
        } catch (\Exception $ex) {
            \Log::warning('getMissingImprestList error: ' . $ex->getMessage());
            return collect();
        }
    }

    private function hrimsDb()
    {
        return DB::connection(session('hrims_connection', DatabaseSwitcher::getHrimsConnection()));
    }

    /**
     * Claimant stats - my own claims
     * Uses: CrBy, ClaimStatus, FilledTAmt, ApprTAmt, FinancedTAmt
     */
    private function getClaimantStats($employeeId, $table)
    {
        try {
            $result = $this->expenseDb()->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->selectRaw("
                    COUNT(*) as total_claims,
                    SUM(CASE WHEN ClaimStatus = 'Draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN ClaimStatus = 'Submitted' THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN ClaimStatus = 'Filled' THEN 1 ELSE 0 END) as filled,
                    SUM(CASE WHEN ClaimStatus = 'Verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN ClaimStatus = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN ClaimStatus = 'Financed' THEN 1 ELSE 0 END) as financed,
                    COALESCE(SUM(FilledTAmt), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN ApprBy > 0 AND ApprDate != '0000-00-00' THEN ApprTAmt ELSE 0 END), 0) as approved_amount,
                    COALESCE(SUM(CASE WHEN FinancedBy > 0 AND FinancedDate != '0000-00-00' THEN FinancedTAmt ELSE 0 END), 0) as financed_amount
                ")
                ->first();

            return $result ?: $this->emptyClaimantStats();
        } catch (\Exception $e) {
            \Log::error('Dashboard claimant stats: ' . $e->getMessage());
            return $this->emptyClaimantStats();
        }
    }

    private function emptyClaimantStats()
    {
        return (object) [
            'total_claims' => 0, 'draft' => 0, 'submitted' => 0, 'filled' => 0,
            'verified' => 0, 'approved' => 0, 'financed' => 0,
            'total_amount' => 0, 'approved_amount' => 0, 'financed_amount' => 0
        ];
    }

    /**
    /**
     * Approver stats - team claims pending/approved by me
     */
    private function getApproverStats($employeeId, $table)
    {
        try {
            // Get direct reportees from hrm_employee_general (RepEmployeeID = this manager)
            $teamMembers = $this->hrimsDb()
                ->table('hrm_employee_general as eg')
                ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->where('eg.RepEmployeeID', $employeeId)
                ->where('e.EmpStatus', 'A')
                ->pluck('eg.EmployeeID')
                ->unique()
                ->toArray();

            $teamCount = count($teamMembers);

            if (empty($teamMembers)) {
                return (object) [
                    'pending' => 0, 'approved_by_me' => 0, 'total_team' => 0,
                    'pending_amount' => 0, 'approved_amount' => 0
                ];
            }

            $db = $this->expenseDb();

            // Pending = claims from team with status 'Verified' not yet approved
            $pendingRow = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('ClaimStatus', 'Verified')
                ->where(function ($q) {
                    $q->whereNull('ApprDate')->orWhere('ApprDate', '0000-00-00');
                })
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(FilledTAmt), 0) as amt')
                ->first();

            // Approved by me = claims where ApprBy = this manager with a valid ApprDate
            $approvedRow = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('ApprBy', $employeeId)
                ->where('ApprDate', '!=', '0000-00-00')
                ->whereNotNull('ApprDate')
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(ApprTAmt), 0) as amt')
                ->first();

            return (object) [
                'pending'         => (int)  ($pendingRow->cnt  ?? 0),
                'pending_amount'  => (float)($pendingRow->amt  ?? 0),
                'approved_by_me'  => (int)  ($approvedRow->cnt ?? 0),
                'approved_amount' => (float)($approvedRow->amt ?? 0),
                'total_team'      => $teamCount,
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard approver stats: ' . $e->getMessage());
            return (object) [
                'pending' => 0, 'approved_by_me' => 0, 'total_team' => 0,
                'pending_amount' => 0, 'approved_amount' => 0
            ];
        }
    }

    /**
     * Verifier stats - claims verified by me (VerifyBy = employeeId)
     */
    private function getVerifierStats($employeeId, $table, ?int $month = null)
    {
        try {
            $db = $this->expenseDb();

            $stats = $db->table($table)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->selectRaw("
                    SUM(CASE WHEN ClaimStatus IN ('Filled','Submitted') AND FilledOkay = 1 THEN 1 ELSE 0 END) as pending_verification,
                    SUM(CASE WHEN VerifyBy = ? AND VerifyDate != '0000-00-00' THEN 1 ELSE 0 END) as verified_by_me,
                    COALESCE(SUM(CASE WHEN VerifyBy = ? AND VerifyDate != '0000-00-00' THEN VerifyTAmt ELSE 0 END), 0) as verified_amount,
                    SUM(CASE WHEN VerifyBy = ? AND VerifyDate != '0000-00-00' AND ClaimMonth = ? THEN 1 ELSE 0 END) as verified_this_month
                ", [$employeeId, $employeeId, $employeeId, $month ?: now()->month]) // DASH-NUM-01: the month picked on the dashboard
                ->first();

            return $stats ?: (object) [
                'pending_verification' => 0, 'verified_by_me' => 0,
                'verified_amount' => 0, 'verified_this_month' => 0
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard verifier stats: ' . $e->getMessage());
            return (object) [
                'pending_verification' => 0, 'verified_by_me' => 0,
                'verified_amount' => 0, 'verified_this_month' => 0
            ];
        }
    }

    /**
     * Finance stats - claims financed by me (FinancedBy = employeeId)
     */
    private function getFinanceStats($employeeId, $table, ?int $month = null)
    {
        try {
            $db = $this->expenseDb();

            $stats = $db->table($table)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->selectRaw("
                    SUM(CASE WHEN ClaimStatus = 'Approved' THEN 1 ELSE 0 END) as pending_finance,
                    SUM(CASE WHEN FinancedBy = ? AND FinancedDate != '0000-00-00' THEN 1 ELSE 0 END) as financed_by_me,
                    COALESCE(SUM(CASE WHEN FinancedBy = ? AND FinancedDate != '0000-00-00' THEN FinancedTAmt ELSE 0 END), 0) as financed_amount,
                    SUM(CASE WHEN FinancedBy = ? AND FinancedDate != '0000-00-00' AND ClaimMonth = ? THEN 1 ELSE 0 END) as financed_this_month
                ", [$employeeId, $employeeId, $employeeId, $month ?: now()->month]) // DASH-NUM-01: the month picked on the dashboard
                ->first();

            return $stats ?: (object) [
                'pending_finance' => 0, 'financed_by_me' => 0,
                'financed_amount' => 0, 'financed_this_month' => 0
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard finance stats: ' . $e->getMessage());
            return (object) [
                'pending_finance' => 0, 'financed_by_me' => 0,
                'financed_amount' => 0, 'financed_this_month' => 0
            ];
        }
    }

    /**
     * Top 10 ratings - returns data per role tab
     * Claimant tab: always shows 'employee' rated_role (everyone is a claimant)
     * Other tabs: show their respective role's top performers
     * All filtered by previous month
     */
    private function getTopRatings($companyId, $yearId, $empRole, $isVerifier, $isFinance)
    {
        try {
            $previousMonth = now()->subMonth()->month;
            $monthName = now()->subMonth()->format('F');

            // Claimant - always 'employee' role, common for everyone
            $claimantTop = ClaimRatingResult::where('company_id', $companyId)
                ->where('year_id', $yearId)
                ->where('rated_role', 'employee')
                ->where('month', $previousMonth)
                ->selectRaw('employee_id, employee_name, department_name, AVG(stars) as avg_rating, COUNT(*) as total_rated')
                ->groupBy('employee_id', 'employee_name', 'department_name')
                ->orderByDesc('avg_rating')
                ->limit(10)
                ->get();

            // Approver top performers
            $approverTop = ($empRole === 'A') ? ClaimRatingResult::where('company_id', $companyId)
                ->where('year_id', $yearId)
                ->where('rated_role', 'approver')
                ->where('month', $previousMonth)
                ->whereNotNull('rated_person_id') // M-06: rank the verifier / approver / finance person, not the claimant
                ->selectRaw('rated_person_id as employee_id, MAX(rated_person_name) as employee_name, NULL as department_name, AVG(stars) as avg_rating, COUNT(*) as total_rated')
                ->groupBy('rated_person_id')
                ->orderByDesc('avg_rating')
                ->limit(10)
                ->get() : collect();

            // Verifier top performers
            $verifierTop = $isVerifier ? ClaimRatingResult::where('company_id', $companyId)
                ->where('year_id', $yearId)
                ->where('rated_role', 'verifier')
                ->where('month', $previousMonth)
                ->whereNotNull('rated_person_id') // M-06: rank the verifier / approver / finance person, not the claimant
                ->selectRaw('rated_person_id as employee_id, MAX(rated_person_name) as employee_name, NULL as department_name, AVG(stars) as avg_rating, COUNT(*) as total_rated')
                ->groupBy('rated_person_id')
                ->orderByDesc('avg_rating')
                ->limit(10)
                ->get() : collect();

            // Finance top performers
            $financeTop = $isFinance ? ClaimRatingResult::where('company_id', $companyId)
                ->where('year_id', $yearId)
                ->where('rated_role', 'finance')
                ->where('month', $previousMonth)
                ->whereNotNull('rated_person_id') // M-06: rank the verifier / approver / finance person, not the claimant
                ->selectRaw('rated_person_id as employee_id, MAX(rated_person_name) as employee_name, NULL as department_name, AVG(stars) as avg_rating, COUNT(*) as total_rated')
                ->groupBy('rated_person_id')
                ->orderByDesc('avg_rating')
                ->limit(10)
                ->get() : collect();

            return [
                'claimant' => $claimantTop,
                'approver' => $approverTop,
                'verifier' => $verifierTop,
                'finance' => $financeTop,
                'month' => $monthName,
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard top ratings: ' . $e->getMessage());
            return [
                'claimant' => collect(),
                'approver' => collect(),
                'verifier' => collect(),
                'finance' => collect(),
                'month' => now()->subMonth()->format('F'),
            ];
        }
    }

    /**
     * Report summary stats for the sidebar
     */
    private function getReportStats($table, $isApprover, $isVerifier, $isFinance)
    {
        try {
            $db = $this->expenseDb();
            $stats = [];

            $stats['total_claims_system'] = $db->table($table)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->count();

            if ($isVerifier) {
                $stats['pending_verification'] = $db->table($table)
                    ->whereIn('ClaimStatus', ['Filled', 'Submitted'])
                    ->where('FilledOkay', 1)
                    ->count();
            }

            if ($isFinance) {
                $stats['pending_finance'] = $db->table($table)
                    ->where('ClaimStatus', 'Approved')
                    ->count();

                $stats['total_financed_amount'] = $db->table($table)
                    ->where('ClaimStatus', 'Financed')
                    ->sum('FinancedTAmt');
            }

            if ($isApprover) {
                $stats['pending_approval'] = $db->table($table)
                    ->where('ClaimStatus', 'Verified')
                    ->count();
            }

            return $stats;
        } catch (\Exception $e) {
            \Log::error('Dashboard report stats: ' . $e->getMessage());
            return ['total_claims_system' => 0];
        }
    }

    /**
     * Monthly claim counts for logged-in employee (Apr-Mar)
     */
    private function getMonthlyClaimData($employeeId, $table)
    {
        try {
            $counts = $this->expenseDb()->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->selectRaw('ClaimMonth, COUNT(*) as cnt')
                ->groupBy('ClaimMonth')
                ->pluck('cnt', 'ClaimMonth')
                ->toArray();

            $months = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
            return array_map(fn($m) => (int) ($counts[$m] ?? 0), $months);
        } catch (\Exception $e) {
            return array_fill(0, 12, 0);
        }
    }

    /**
     * Monthly approved-by-me counts for approver (Apr-Mar)
     */
    private function getApproverMonthlyData($employeeId, $table)
    {
        try {
            $counts = $this->expenseDb()->table($table)
                ->where('ApprBy', $employeeId)
                ->where('ApprDate', '!=', '0000-00-00')
                ->selectRaw('ClaimMonth, COUNT(*) as cnt')
                ->groupBy('ClaimMonth')
                ->pluck('cnt', 'ClaimMonth')
                ->toArray();

            $months = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
            return array_map(fn($m) => (int) ($counts[$m] ?? 0), $months);
        } catch (\Exception $e) {
            return array_fill(0, 12, 0);
        }
    }

    /**
     * Monthly financed-by-me counts for finance (Apr-Mar)
     */
    private function getFinanceMonthlyData($employeeId, $table)
    {
        try {
            $counts = $this->expenseDb()->table($table)
                ->where('FinancedBy', $employeeId)
                ->where('FinancedDate', '!=', '0000-00-00')
                ->selectRaw('ClaimMonth, COUNT(*) as cnt')
                ->groupBy('ClaimMonth')
                ->pluck('cnt', 'ClaimMonth')
                ->toArray();

            $months = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
            return array_map(fn($m) => (int) ($counts[$m] ?? 0), $months);
        } catch (\Exception $e) {
            return array_fill(0, 12, 0);
        }
    }

    /**
     * MRM Stage Summary - Uses same logic as ClaimStageReportService
     * Returns month-wise stage data for the dashboard
     */
    private function getMrmStageSummary(): array
    {
        try {
            $service = new \App\Services\Reports\ClaimStageReportService();
            return $service->getMonthlyStageSummary(['months' => range(1, 12)]);
        } catch (\Exception $e) {
            \Log::error('MRM Stage Summary error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * API endpoint: Get month-wise claims for the claimant section (mobile-app style)
     * Returns open months with day-wise claim details grouped by month
     */
    public function getMyClaimsMonthWise()
    {
        $employeeId = session('employee_id');
        $yearId = \App\Support\CurrentYear::id();
        $table = ExpenseClaim::tableName($yearId);

        try {
            $db = $this->expenseDb();

            // Get all non-deactivated claims for the employee grouped by month
            $claims = $db->table($table . ' as e')
                ->leftJoin('claimtype as ct', 'e.ClaimId', '=', 'ct.ClaimId')
                ->where('e.CrBy', $employeeId)
                ->where('e.ClaimStatus', '!=', 'Deactivate')
                ->where('e.FilledBy', '>', 0)
                ->where('e.AttachTo', 0)
                ->select(
                    'e.ExpId',
                    'e.ClaimId',
                    'e.ClaimMonth',
                    'e.BillDate',
                    'e.FilledTAmt',
                    'e.ClaimStatus',
                    'e.FilledOkay',
                    'e.ClaimAtStep',
                    'e.Img_1',
                    'e.Img_2',
                    'e.Img_3',
                    'e.TotKm',
                    'e.CrDate',
                    'e.DateEntryRemark',
                    'e.VerifyTAmt',
                    'e.VerifyBy',
                    'e.VerifyDate',
                    'ct.ClaimName',
                    'ct.cgId'
                )
                ->orderBy('e.BillDate', 'desc')
                ->get();

            // Group by month
            $monthGroups = $claims->groupBy('ClaimMonth');

            $openList = [];
            // Financial year month order DESCENDING: Mar(3) down to Apr(4) — most recent first
            $monthOrder = [3, 2, 1, 12, 11, 10, 9, 8, 7, 6, 5, 4];

            foreach ($monthOrder as $month) {
                if (!$monthGroups->has($month)) continue;

                $monthClaims = $monthGroups[$month];
                $totalClaims = $monthClaims->count();
                $totalFilledAmt = $monthClaims->sum('FilledTAmt');
                // DASH-NUM-01: "verified" = checked by the verifier (status or VerifyBy/VerifyDate); FilledOkay = 1 is
                // only the employee's own confirmation of the punched figures and is reported as "confirmed"
                $verifiedClaims = $monthClaims->filter(fn ($c) => in_array($c->ClaimStatus, ['Verified', 'Approved', 'Financed', 'Payment', 'Close'], true)
                    || ((int) ($c->VerifyBy ?? 0) > 0 && \App\Support\ZeroDate::has($c->VerifyDate ?? null)));
                $totalVerifiedAmt = $verifiedClaims->sum('VerifyTAmt');
                $totalDenied = $monthClaims->where('FilledOkay', 2)->count();
                $totalVerified = $verifiedClaims->count();
                $totalConfirmed = $monthClaims->where('FilledOkay', 1)->count();

                // Check if month is submitted/closed
                $monthStatus = $db->table('y' . $yearId . '_monthexpensefinal')
                    ->where('EmployeeID', $employeeId)
                    ->where('Month', $month)
                    ->value('Status');

                // Get dates for timeline from monthexpensefinal
                $monthRecord = $db->table('y' . $yearId . '_monthexpensefinal')
                    ->where('EmployeeID', $employeeId)
                    ->where('Month', $month)
                    ->first();

                $submitDate   = $monthRecord->DateOfSubmit ?? null;
                $verifiedDate = $monthRecord->Verified_Date ?? null;
                $approvedDate = $monthRecord->Approved_Date ?? null;
                $financeDate  = $monthRecord->Finance_Date ?? null;
                $verifiedAmt  = $monthRecord->Verified_Amount ?? 0;
                $approvedAmt  = $monthRecord->Approved_Amount ?? 0;
                $financeAmt   = $monthRecord->Finance_Amount ?? 0;

                $dayWiseDetails = $monthClaims->map(function ($claim) use ($yearId) {
                    return [
                        'ExpId' => $claim->ExpId,
                        'ClaimId' => $claim->ClaimId,
                        'BillDate' => $claim->BillDate,
                        'FilledTAmt' => $claim->FilledTAmt,
                        'ClaimStatus' => $claim->ClaimStatus,
                        'FilledOkay' => $claim->FilledOkay,
                        'ClaimAtStep' => $claim->ClaimAtStep,
                        'ClaimName' => $claim->ClaimName,
                        'cgId' => $claim->cgId,
                        'ClaimMonth' => $claim->ClaimMonth,
                        'TotKm' => $claim->TotKm ?? '0',
                        'Img_1' => $claim->Img_1 ?? '',
                        'Img_2' => $claim->Img_2 ?? '',
                        'Img_3' => $claim->Img_3 ?? '',
                        'DateEntryRemark' => $claim->DateEntryRemark ?? '',
                        'VerifyTAmt' => $claim->VerifyTAmt ?? 0,
                    ];
                })->values()->toArray();

                $openList[] = [
                    'Month' => $month,
                    'MonthStatus' => $monthStatus ?? 'Open',
                    'DateOfSubmit' => $submitDate,
                    'Verified_Date' => $verifiedDate,
                    'Verified_Amount' => round((float)$verifiedAmt),
                    'Approved_Date' => $approvedDate,
                    'Approved_Amount' => round((float)$approvedAmt),
                    'Finance_Date' => $financeDate,
                    'Finance_Amount' => round((float)$financeAmt),
                    'TotalFilled_Claim_with_Amount' => [
                        'TotClaim' => (string) $totalClaims,
                        'TotFilledAmt' => (string) round($totalFilledAmt),
                        'TotDeniedClaims' => (string) $totalDenied,
                        'TotVerifiedClaims' => (string) $totalVerified,
                        'TotConfirmedClaims' => (string) $totalConfirmed,
                    ],
                    'TotalVerify_Claim_with_Amount' => [
                        'TotClaim' => (string) $totalVerified,
                        'TotVerifyAmt' => (string) round($totalVerifiedAmt),
                    ],
                    'DayWiseDetails' => $dayWiseDetails,
                ];
            }

            return response()->json([
                'success' => true,
                'open_list' => $openList,
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard getMyClaimsMonthWise: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Stagewise TAT Analysis for a specific employee and month.
     * Supports both VNR (Submit -> Verify -> Approve -> Finance -> Pay)
     * and VVNR (Submit -> Approve -> Finance -> Pay -> Verify).
     */
    private function getStagewiseTATForEmployee($employeeId, $yearId, $month, bool $isVVNR = false)
    {
        try {
            $table = 'y' . $yearId . '_monthexpensefinal';
            $db = $this->expenseDb();

            if ($isVVNR) {
                $row = $db->table($table)
                    ->where('EmployeeID', $employeeId)
                    ->where('Month', $month)
                    ->whereNotNull('DateOfSubmit')
                    ->where('DateOfSubmit', '!=', '0000-00-00')
                    ->selectRaw("
                        ROUND(AVG(CASE
                            WHEN Approved_Date IS NOT NULL AND Approved_Date != '0000-00-00'
                                 AND DateOfSubmit IS NOT NULL AND DateOfSubmit != '0000-00-00'
                            THEN GREATEST(0, DATEDIFF(Approved_Date, DateOfSubmit))
                        END), 1) AS submit_to_approve,
                        ROUND(AVG(CASE
                            WHEN Finance_Date IS NOT NULL AND Finance_Date != '0000-00-00'
                                 AND Approved_Date IS NOT NULL AND Approved_Date != '0000-00-00'
                            THEN GREATEST(0, DATEDIFF(Finance_Date, Approved_Date))
                        END), 1) AS approve_to_finance,
                        ROUND(AVG(CASE
                            WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != '0000-00-00'
                                 AND COALESCE(Finance_Date, Approved_Date) IS NOT NULL AND COALESCE(Finance_Date, Approved_Date) != '0000-00-00'
                            THEN GREATEST(0, DATEDIFF(Fin_PayDate, COALESCE(Finance_Date, Approved_Date)))
                        END), 1) AS finance_to_pay,
                        ROUND(AVG(CASE
                            WHEN Verified_Date IS NOT NULL AND Verified_Date != '0000-00-00'
                                 AND COALESCE(RecevingDate, Fin_PayDate) IS NOT NULL AND COALESCE(RecevingDate, Fin_PayDate) != '0000-00-00'
                            THEN GREATEST(0, DATEDIFF(Verified_Date, COALESCE(RecevingDate, Fin_PayDate)))
                        END), 1) AS receive_to_verify,
                        ROUND(AVG(CASE
                            WHEN Verified_Date IS NOT NULL AND Verified_Date != '0000-00-00'
                                 AND DateOfSubmit IS NOT NULL AND DateOfSubmit != '0000-00-00'
                            THEN GREATEST(0, DATEDIFF(Verified_Date, DateOfSubmit))
                            WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != '0000-00-00'
                                 AND DateOfSubmit IS NOT NULL AND DateOfSubmit != '0000-00-00'
                            THEN GREATEST(0, DATEDIFF(Fin_PayDate, DateOfSubmit))
                        END), 1) AS total_days
                    ")
                    ->first();

                $submitToApprove  = (float) ($row->submit_to_approve  ?? 0);
                $approveToFinance = (float) ($row->approve_to_finance ?? 0);
                $financeToPay     = (float) ($row->finance_to_pay     ?? 0);
                $receiveToVerify  = (float) ($row->receive_to_verify  ?? 0);
                $total = ($row->total_days ?? 0) > 0
                    ? (float) $row->total_days
                    : round($submitToApprove + $approveToFinance + $financeToPay + $receiveToVerify, 1);

                return [
                    'submitted'            => 0,
                    'reporting_manager'    => $submitToApprove,
                    'finance_approval'     => $approveToFinance,
                    'payment'              => $financeToPay,
                    'finance_verification' => $receiveToVerify,
                    'total'                => $total,
                ];
            }

            $row = $db->table($table)
                ->where('EmployeeID', $employeeId)
                ->where('Month', $month)
                ->whereNotNull('DateOfSubmit')
                ->where('DateOfSubmit', '!=', '0000-00-00')
                ->selectRaw("
                    ROUND(AVG(CASE
                        WHEN RecevingDate IS NOT NULL AND RecevingDate != '0000-00-00'
                             AND DateOfSubmit IS NOT NULL AND DateOfSubmit != '0000-00-00'
                        THEN GREATEST(0, DATEDIFF(RecevingDate, DateOfSubmit))
                    END), 1) AS submit_to_receive,
                    ROUND(AVG(CASE
                        WHEN Verified_Date IS NOT NULL AND Verified_Date != '0000-00-00'
                             AND RecevingDate IS NOT NULL AND RecevingDate != '0000-00-00'
                        THEN GREATEST(0, DATEDIFF(Verified_Date, RecevingDate))
                    END), 1) AS receive_to_verify,
                    ROUND(AVG(CASE
                        WHEN Approved_Date IS NOT NULL AND Approved_Date != '0000-00-00'
                             AND Verified_Date IS NOT NULL AND Verified_Date != '0000-00-00'
                        THEN GREATEST(0, DATEDIFF(Approved_Date, Verified_Date))
                    END), 1) AS verify_to_approve,
                    ROUND(AVG(CASE
                        WHEN Finance_Date IS NOT NULL AND Finance_Date != '0000-00-00'
                             AND Approved_Date IS NOT NULL AND Approved_Date != '0000-00-00'
                        THEN GREATEST(0, DATEDIFF(Finance_Date, Approved_Date))
                    END), 1) AS approve_to_finance,
                    ROUND(AVG(CASE
                        WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != '0000-00-00'
                             AND Finance_Date IS NOT NULL AND Finance_Date != '0000-00-00'
                        THEN GREATEST(0, DATEDIFF(Fin_PayDate, Finance_Date))
                    END), 1) AS finance_to_pay,
                    ROUND(AVG(CASE
                        WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != '0000-00-00'
                             AND DateOfSubmit IS NOT NULL AND DateOfSubmit != '0000-00-00'
                        THEN GREATEST(0, DATEDIFF(Fin_PayDate, DateOfSubmit))
                    END), 1) AS total_days
                ")
                ->first();

            $submitToReceive  = (float) ($row->submit_to_receive  ?? 0);
            $receiveToVerify  = (float) ($row->receive_to_verify  ?? 0);
            $verifyToApprove  = (float) ($row->verify_to_approve  ?? 0);
            $approveToFinance = (float) ($row->approve_to_finance ?? 0);
            $financeToPay     = (float) ($row->finance_to_pay     ?? 0);

            // Use DB-calculated total; fall back to sum of completed stages when
            // payment hasn't happened yet (so in-progress claims still show data).
            $total = ($row->total_days ?? 0) > 0
                ? (float) $row->total_days
                : round($submitToReceive + $receiveToVerify + $verifyToApprove + $approveToFinance + $financeToPay, 1);

            return [
                'submitted'            => $submitToReceive,
                'reporting_manager'    => $verifyToApprove,
                'finance_verification' => $receiveToVerify,
                'finance_approval'     => $approveToFinance,
                'payment'              => $financeToPay,
                'total'                => $total,
            ];
        } catch (\Exception $e) {
            \Log::error('Stagewise TAT error: ' . $e->getMessage());
            return [
                'submitted' => 0, 'reporting_manager' => 0,
                'finance_verification' => 0, 'finance_approval' => 0,
                'payment' => 0, 'total' => 0,
            ];
        }
    }

    /**
     * Expense Overview by Claim Type for current month
     */
    private function getExpenseOverviewByType($employeeId, $table, $month)
    {
        try {
            $db = $this->expenseDb();
            $data = $db->table("$table as e")
                ->where('e.CrBy', $employeeId)
                ->where('e.ClaimMonth', $month)
                ->where('e.ClaimStatus', '!=', 'Deactivate')
                ->where('e.FilledOkay', 1)
                ->select('e.ClaimId', DB::raw('SUM(e.FilledTAmt) as total_amount'), DB::raw('COUNT(*) as claim_count'))
                ->groupBy('e.ClaimId')
                ->get();

            // Get claim names
            $claimIds = $data->pluck('ClaimId')->toArray();
            $claimNames = [];
            if (!empty($claimIds)) {
                $claimNames = $this->expenseDb()->table('claimtype')
                    ->whereIn('ClaimId', $claimIds)
                    ->pluck('ClaimName', 'ClaimId')
                    ->toArray();
            }

            $result = [];
            $totalAmount = 0;
            foreach ($data as $row) {
                $name = $claimNames[$row->ClaimId] ?? 'Others';
                $result[] = [
                    'name' => $name,
                    'amount' => round((float)$row->total_amount),
                    'count' => $row->claim_count,
                ];
                $totalAmount += (float)$row->total_amount;
            }

            return [
                'items' => $result,
                'total' => round($totalAmount),
            ];
        } catch (\Exception $e) {
            \Log::error('Expense Overview error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Odometer Summary for current month
     */
    private function getOdometerSummary($employeeId, $yearId, $month)
    {
        try {
            $table = 'y' . $yearId . '_expenseclaims';
            $db = $this->expenseDb();

            // ClaimId = 7 is typically the odometer/local conveyance claim
            $odometerData = $db->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimMonth', $month)
                ->where('ClaimId', 7)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->select([
                    DB::raw('MIN(odomtr_opening) as opening_reading'),
                    DB::raw('MAX(odomtr_closing) as closing_reading'),
                    DB::raw('SUM(TotKm) as total_km'),
                    DB::raw('SUM(CASE WHEN FilledOkay = 1 THEN TotKm ELSE 0 END) as reimbursable_km'),
                    DB::raw('AVG(RatePerKM) as rate_per_km'),
                    DB::raw('SUM(CASE WHEN FilledOkay = 1 THEN FilledTAmt ELSE 0 END) as amount_eligible'),
                ])
                ->first();

            if (!$odometerData || !$odometerData->opening_reading) {
                return [
                    'opening_reading' => 0,
                    'closing_reading' => 0,
                    'total_km' => 0,
                    'reimbursable_km' => 0,
                    'rate_per_km' => 0,
                    'amount_eligible' => 0,
                ];
            }

            return [
                'opening_reading' => (int)$odometerData->opening_reading,
                'closing_reading' => (int)$odometerData->closing_reading,
                'total_km' => (int)$odometerData->total_km,
                'reimbursable_km' => (int)$odometerData->reimbursable_km,
                'rate_per_km' => round((float)$odometerData->rate_per_km, 2),
                'amount_eligible' => round((float)$odometerData->amount_eligible),
            ];
        } catch (\Exception $e) {
            \Log::error('Odometer Summary error: ' . $e->getMessage());
            return [
                'opening_reading' => 0, 'closing_reading' => 0,
                'total_km' => 0, 'reimbursable_km' => 0,
                'rate_per_km' => 0, 'amount_eligible' => 0,
            ];
        }
    }

    /**
     * Amount Paid MTD (current month financed amount)
     */
    private function getAmountPaidMTD($employeeId, $table, $month)
    {
        try {
            $db = $this->expenseDb();
            $result = $db->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimMonth', $month)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('FinancedDate', '!=', '0000-00-00')
                ->whereNotNull('FinancedDate')
                ->selectRaw('COALESCE(SUM(FinancedTAmt), 0) as total_paid, COUNT(*) as claims_count')
                ->first();

            return [
                'amount' => round((float)($result->total_paid ?? 0)),
                'claims' => (int)($result->claims_count ?? 0),
            ];
        } catch (\Exception $e) {
            return ['amount' => 0, 'claims' => 0];
        }
    }

    /**
     * Claims Paid MTD count
     */
    private function getClaimsPaidMTD($employeeId, $table, $month)
    {
        try {
            $db = $this->expenseDb();
            return (int)$db->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimMonth', $month)
                ->where('ClaimStatus', 'Financed')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get claims summary (Total, Draft, Verified, Approved, Denied) for a specific month
     */
    private function getMonthClaimsSummary($employeeId, $yearId, $month, bool $isVVNR = false)
    {
        try {
            $table = ExpenseClaim::tableName($yearId);
            $db = $this->expenseDb();

            // All claims for this employee matching month
            $claims = $db->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('AttachTo', 0)
                ->where(function($q) use ($month) {
                    $q->where('ClaimMonth', $month)
                      ->orWhere(function($sub) use ($month) {
                          $sub->where('ClaimStatus', 'Draft')
                              ->whereRaw('MONTH(BillDate) = ?', [$month]);
                      });
                })
                ->get();

            // Total submitted claims for this month (matches My Claims table)
            $submittedClaims = $claims->filter(function($c) {
                return $c->ClaimStatus !== 'Draft' && !empty($c->FilledBy) && $c->FilledBy > 0;
            });

            // Draft claims
            $draftClaims = $claims->filter(function($c) {
                return $c->ClaimStatus === 'Draft' || empty($c->FilledBy) || $c->FilledBy == 0;
            });

            // Check monthexpensefinal
            $mef = $db->table("y{$yearId}_monthexpensefinal")
                ->where('EmployeeID', $employeeId)
                ->where('Month', $month)
                ->first();

            $isMonthVerified = !empty($mef?->Verified_Date) && \App\Support\ZeroDate::has($mef->Verified_Date);
            $isMonthApproved = !empty($mef?->Approved_Date) && \App\Support\ZeroDate::has($mef->Approved_Date);

            // Verified count. DASH-NUM-01: FilledOkay = 1 only means the bill was punched correctly, not that
            // the verifier verified it, so it no longer counts here (it showed "Verified" on Filled claims)
            $verifiedCount = $submittedClaims->filter(function($c) use ($isMonthVerified) {
                return $isMonthVerified
                    || in_array($c->ClaimStatus, ['Verified', 'Approved', 'Financed', 'Payment', 'Close'], true)
                    || ((int) ($c->VerifyBy ?? 0) > 0 && \App\Support\ZeroDate::has($c->VerifyDate ?? null));
            })->count();

            // Approved count
            $approvedCount = $submittedClaims->filter(function($c) use ($isMonthApproved) {
                return $isMonthApproved || (!empty($c->ApprBy) && $c->ApprBy > 0) || $c->ClaimStatus === 'Approved';
            })->count();

            // Denied count
            $deniedCount = $submittedClaims->where('FilledOkay', 2)->count();

            return [
                'total'    => $submittedClaims->count(),
                'draft'    => $draftClaims->count(),
                'verified' => $verifiedCount,
                'approved' => $approvedCount,
                'denied'   => $deniedCount,
            ];
        } catch (\Throwable $e) {
            \Log::error('getMonthClaimsSummary error: ' . $e->getMessage());
            return [
                'total'    => 0,
                'draft'    => 0,
                'verified' => 0,
                'approved' => 0,
                'denied'   => 0,
            ];
        }
    }

    /**
     * My Claims table for current month (recent claims with status & TAT)
     */
    private function getMyClaimsTable($employeeId, $table, $month, bool $isVVNR = false)
    {
        try {
            $yearId = \App\Support\CurrentYear::id();
            $db = $this->expenseDb();
            $mefTable = "y{$yearId}_monthexpensefinal";

            $claims = $db->table("$table as e")
                ->leftJoin(DB::raw('claimtype as ct'), 'e.ClaimId', '=', 'ct.ClaimId')
                ->where('e.CrBy', $employeeId)
                ->where('e.ClaimMonth', $month)
                ->where('e.ClaimStatus', '!=', 'Deactivate')
                ->where('e.AttachTo', 0)
                ->select([
                    'e.ExpId',
                    'e.ClaimId',
                    'e.ClaimMonth',
                    'e.BillDate',
                    'e.FilledTAmt',
                    'e.ClaimStatus',
                    'e.ClaimAtStep',
                    'e.FilledOkay',
                    'e.CrDate',
                    'e.DateEntryRemark',
                    'ct.ClaimName',
                ])
                ->orderBy('e.BillDate', 'desc')
                ->limit(10)
                ->get();

            $mefRecords = [];

            return $claims->map(function ($claim) use ($db, $mefTable, $employeeId, &$mefRecords, $isVVNR) {
                // Calculate TAT: difference in days between Uploaded Date (CrDate) and Bill Date (BillDate)
                $tatDays = 0;
                if (!empty($claim->CrDate) && \App\Support\ZeroDate::has($claim->CrDate) && !empty($claim->BillDate) && \App\Support\ZeroDate::has($claim->BillDate)) {
                    $uploadTime = strtotime($claim->CrDate);
                    $billTime = strtotime($claim->BillDate);
                    $tatDays = max(0, round(($uploadTime - $billTime) / 86400, 1));
                }

                // Determine Current Stage from monthexpensefinal table
                $cMonth = (int) ($claim->ClaimMonth ?? 0);
                if (!isset($mefRecords[$cMonth])) {
                    $mefRecords[$cMonth] = $db->table($mefTable)
                        ->where('EmployeeID', $employeeId)
                        ->where('Month', $cMonth)
                        ->first();
                }
                $mefRow = $mefRecords[$cMonth];

                $hasValidDate = function ($date) {
                    return !empty($date) && \App\Support\ZeroDate::has($date) && \App\Support\ZeroDate::has($date);
                };

                $stage = 'Pending for Submission';
                $stageColor = 'secondary';

                if ($mefRow) {
                    $isClosed = strtolower(trim($mefRow->Status ?? '')) === 'closed' || $hasValidDate($mefRow->DateOfSubmit ?? null);

                    if (!$isClosed) {
                        $stage = 'Pending for Submission';
                        $stageColor = 'secondary';
                    } elseif ($isVVNR) {
                        // VVNR Flow: Submit -> RM Approve -> Finance -> Payment -> Courier Receive -> Verify
                        if ($hasValidDate($mefRow->Verified_Date ?? null)) {
                            $stage = 'Verified - Completed';
                            $stageColor = 'success';
                        } elseif ($hasValidDate($mefRow->RecevingDate ?? null)) {
                            $stage = 'Courier Completed - Pending Verification';
                            $stageColor = 'info';
                        } elseif (\App\Support\Paid::is($mefRow)) { // PAID-01
                            $stage = 'Paid - Pending Courier';
                            $stageColor = 'primary';
                        } elseif ($hasValidDate($mefRow->Finance_Date ?? null)) {
                            $stage = 'Pending for Payment';
                            $stageColor = 'info';
                        } elseif ($hasValidDate($mefRow->Approved_Date ?? null)) {
                            $stage = 'Approved - Pending Finance';
                            $stageColor = 'purple';
                        } else {
                            $stage = 'Pending for Approval';
                            $stageColor = 'warning';
                        }
                    } else {
                        // VNR Flow: Submit -> Courier Receive -> Verify -> RM Approve -> Finance -> Payment
                        if (\App\Support\Paid::is($mefRow)) { // PAID-01
                            $stage = 'Paid';
                            $stageColor = 'success';
                        } elseif ($hasValidDate($mefRow->Finance_Date ?? null)) {
                            $stage = 'Pending for Payment';
                            $stageColor = 'info';
                        } elseif ($hasValidDate($mefRow->Approved_Date ?? null)) {
                            $stage = 'Pending for Finance';
                            $stageColor = 'purple';
                        } elseif ($hasValidDate($mefRow->Verified_Date ?? null)) {
                            $stage = 'Pending for Approval';
                            $stageColor = 'warning';
                        } else {
                            // Closed / Submitted, but Verified_Date is blank -> Pending for Verification
                            $stage = 'Pending for Verification';
                            $stageColor = 'primary';
                        }
                    }
                }

                // Status from ClaimStatus column
                $rawStatus = trim($claim->ClaimStatus ?? '');
                if ($claim->FilledOkay == 2) {
                    $status = 'Denied';
                    $statusColor = 'danger';
                } elseif (in_array(strtolower($rawStatus), ['submitted', 'draft'])) {
                    $status = 'Draft';
                    $statusColor = 'secondary';
                } elseif (strtolower($rawStatus) === 'filled') {
                    $status = 'Filled';
                    $statusColor = 'primary';
                } elseif (strtolower($rawStatus) === 'verified') {
                    $status = 'Verified';
                    $statusColor = 'info';
                } elseif (strtolower($rawStatus) === 'approved') {
                    $status = 'Approved';
                    $statusColor = 'success';
                } elseif (strtolower($rawStatus) === 'financed') {
                    $status = 'Financed';
                    $statusColor = 'success';
                } elseif (in_array(strtolower($rawStatus), ['deactivate', 'deactivat'])) {
                    $status = 'Deactivated';
                    $statusColor = 'dark';
                } else {
                    $status = $rawStatus ?: 'Draft';
                    $statusColor = 'warning';
                }

                return [
                    'ExpId' => $claim->ExpId,
                    'ClaimId' => $claim->ClaimId,
                    'BillDate' => $claim->BillDate,
                    'ClaimName' => $claim->ClaimName ?? 'Unknown',
                    'Purpose' => $claim->DateEntryRemark ?? '-',
                    'Amount' => round((float)$claim->FilledTAmt),
                    'Stage' => $stage,
                    'StageColor' => $stageColor,
                    'Status' => $status,
                    'StatusColor' => $statusColor,
                    'TAT' => $tatDays,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('My Claims Table error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Reporting Manager Dashboard data - aggregated for the Approver tab
     */
    private function getReportingManagerDashData($employeeId, $yearId, $table, $selectedMonth = null, $prefetchedTeamMembers = [])
    {
        try {
            $db = $this->expenseDb();
            $hrimsDb = $this->hrimsDb();
            $mefTable = 'y' . $yearId . '_monthexpensefinal';

            $teamMembers = $prefetchedTeamMembers;
            if (empty($teamMembers)) {
                $teamMembers = $hrimsDb->table('hrm_employee_general as eg')
                    ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                    ->where('eg.RepEmployeeID', $employeeId)
                    ->where('e.EmpStatus', 'A')
                    ->pluck('eg.EmployeeID')
                    ->unique()
                    ->values()
                    ->toArray();
            }

            if (empty($teamMembers)) {
                return $this->emptyRmDashData();
            }

            // Determine team closed months if not explicitly passed
            $teamClosedMonths = $db->table($mefTable)
                ->whereIn('EmployeeID', $teamMembers)
                ->where('YearId', $yearId)
                ->whereNotNull('DateOfSubmit')
                ->where('DateOfSubmit', '!=', '0000-00-00')
                ->pluck('Month')
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            $currentMonthNum = (int) now()->month;
            $defaultMonth = $currentMonthNum === 1 ? 12 : $currentMonthNum - 1;

            if ($selectedMonth && is_numeric($selectedMonth) && $selectedMonth >= 1 && $selectedMonth <= 12) {
                $displayMonth = (int) $selectedMonth;
            } else {
                $displayMonth = $defaultMonth;
            }

            $prevMonth = $displayMonth > 1 ? $displayMonth - 1 : 12;

            // Pending approval count & amount (claims from team, status = Verified / Closed / Submitted, not yet approved)
            $pendingMef = $db->table($mefTable)
                ->whereIn('EmployeeID', $teamMembers)
                ->where('YearId', $yearId)
                ->where(function($q) {
                    $q->where('Status', 'Closed')
                      ->orWhere(function($q2) {
                          $q2->whereNotNull('DateOfSubmit')->where('DateOfSubmit', '!=', '0000-00-00');
                      });
                })
                ->where(function($q) {
                    $q->whereNull('Approved_Date')->orWhere('Approved_Date', '0000-00-00');
                })
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(CASE WHEN Verified_Amount > 0 THEN Verified_Amount ELSE Claim_Amount END), 0) as amt')
                ->first();

            $pendingCount = (int) ($pendingMef->cnt ?? 0);
            $pendingAmount = (float) ($pendingMef->amt ?? 0);

            if ($pendingCount === 0) {
                $pendingEc = $db->table($table)
                    ->whereIn('CrBy', $teamMembers)
                    ->whereIn('ClaimStatus', ['Submitted', 'Filled', 'Verified'])
                    ->where(function($q) {
                        $q->whereNull('ApprDate')->orWhere('ApprDate', '0000-00-00');
                    })
                    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(FilledTAmt), 0) as amt')
                    ->first();
                $pendingCount = (int) ($pendingEc->cnt ?? 0);
                $pendingAmount = (float) ($pendingEc->amt ?? 0);
            }

            // Approved this month
            $approvedMef = $db->table($mefTable)
                ->whereIn('EmployeeID', $teamMembers)
                ->where('YearId', $yearId)
                ->where('Month', $displayMonth)
                ->whereNotNull('Approved_Date')
                ->where('Approved_Date', '!=', '0000-00-00')
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(Approved_Amount), 0) as amt')
                ->first();

            $approvedCount = (int) ($approvedMef->cnt ?? 0);
            $approvedAmount = (float) ($approvedMef->amt ?? 0);

            if ($approvedCount === 0) {
                $approvedEc = $db->table($table)
                    ->whereIn('CrBy', $teamMembers)
                    ->where('ClaimMonth', $displayMonth)
                    ->where('ApprDate', '!=', '0000-00-00')
                    ->whereNotNull('ApprDate')
                    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(ApprTAmt), 0) as amt')
                    ->first();
                $approvedCount = (int) ($approvedEc->cnt ?? 0);
                $approvedAmount = (float) ($approvedEc->amt ?? 0);
            }

            // Approved prev month (for comparison)
            $approvedPrevMef = $db->table($mefTable)
                ->whereIn('EmployeeID', $teamMembers)
                ->where('YearId', $yearId)
                ->where('Month', $prevMonth)
                ->whereNotNull('Approved_Date')
                ->where('Approved_Date', '!=', '0000-00-00')
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(Approved_Amount), 0) as amt')
                ->first();

            $approvedCountPrev = (int) ($approvedPrevMef->cnt ?? 0);
            $approvedAmountPrev = (float) ($approvedPrevMef->amt ?? 0);

            // Returned/Denied
            $returned = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('FilledOkay', 2)
                ->where('ClaimMonth', $displayMonth)
                ->count();

            // Returned prev month (for comparison)
            $returnedPrev = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('FilledOkay', 2)
                ->where('ClaimMonth', $prevMonth)
                ->count();

            // Team total expenses this year
            $teamExpenses = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('FilledOkay', 1)
                ->sum('FilledTAmt');

            // Total claims count
            $totalClaims = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->count();

            // Approval Inbox (recent pending - top 6)
            $approvalInbox = $db->table("$table as e")
                ->leftJoin(DB::raw('claimtype as ct'), 'e.ClaimId', '=', 'ct.ClaimId')
                ->whereIn('e.CrBy', $teamMembers)
                ->whereIn('e.ClaimStatus', ['Submitted', 'Filled', 'Verified'])
                ->where(function($q) {
                    $q->where('e.ApprDate', '0000-00-00')->orWhereNull('e.ApprDate');
                })
                ->select('e.ExpId', 'e.CrBy', 'e.ClaimId', 'e.FilledTAmt', 'e.CrDate', 'e.BillDate', 'e.DateEntryRemark', 'ct.ClaimName')
                ->orderBy('e.CrDate', 'desc')
                ->limit(6)
                ->get();

            // Get employee names for inbox
            $empIds = array_unique(array_merge($teamMembers, $approvalInbox->pluck('CrBy')->toArray()));
            $empNames = [];
            if (!empty($empIds)) {
                $empNames = $hrimsDb->table('hrm_employee')
                    ->whereIn('EmployeeID', $empIds)
                    ->selectRaw("EmployeeID, CONCAT(Fname, ' ', COALESCE(Lname, '')) as name")
                    ->pluck('name', 'EmployeeID')
                    ->toArray();
            }

            // Expense by claim type (team, display month)
            $expenseByType = $db->table("$table as e")
                ->whereIn('e.CrBy', $teamMembers)
                ->where('e.ClaimMonth', $displayMonth)
                ->where('e.ClaimStatus', '!=', 'Deactivate')
                ->where('e.FilledOkay', 1)
                ->select('e.ClaimId', DB::raw('SUM(e.FilledTAmt) as total'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('e.ClaimId')
                ->orderByDesc('total')
                ->limit(6)
                ->get();

            $claimNames = $db->table('claimtype')->pluck('ClaimName', 'ClaimId')->toArray();

            // Expense trend (last 6 months)
            $trendMonths = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = $displayMonth - $i;
                if ($m <= 0) $m += 12;
                $trendMonths[] = $m;
            }
            $monthlyTrend = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('FilledOkay', 1)
                ->whereIn('ClaimMonth', $trendMonths)
                ->select('ClaimMonth', DB::raw('SUM(FilledTAmt) as amt'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('ClaimMonth')
                ->get()
                ->keyBy('ClaimMonth');

            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $trendLabels = [];
            $trendAmounts = [];
            $trendCounts = [];
            foreach ($trendMonths as $m) {
                $trendLabels[] = $monthNames[$m];
                $trendAmounts[] = round((float)($monthlyTrend[$m]->amt ?? 0));
                $trendCounts[] = (int)($monthlyTrend[$m]->cnt ?? 0);
            }

            // Team Claims Summary table
            $teamSummary = $db->table("$table as e")
                ->whereIn('e.CrBy', $teamMembers)
                ->where('e.ClaimStatus', '!=', 'Deactivate')
                ->where('e.ClaimMonth', $displayMonth)
                ->select(
                    'e.CrBy',
                    DB::raw('COUNT(*) as total_claims'),
                    DB::raw("SUM(CASE WHEN e.ClaimStatus = 'Verified' AND (e.ApprDate = '0000-00-00' OR e.ApprDate IS NULL) THEN 1 ELSE 0 END) as pending_approval"),
                    DB::raw("SUM(CASE WHEN e.ApprBy > 0 AND e.ApprDate != '0000-00-00' THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN e.FilledOkay = 2 THEN 1 ELSE 0 END) as returned"),
                    DB::raw("SUM(CASE WHEN e.ClaimStatus IN ('Filled','Submitted') THEN 1 ELSE 0 END) as in_progress"),
                    DB::raw("SUM(CASE WHEN e.ClaimStatus = 'Financed' THEN 1 ELSE 0 END) as paid"),
                    DB::raw('COALESCE(SUM(e.FilledTAmt), 0) as total_amount')
                )
                ->groupBy('e.CrBy')
                ->get();

            // Map employee names to team summary
            $allTeamEmpIds = $teamSummary->pluck('CrBy')->unique()->toArray();
            if (!empty($allTeamEmpIds)) {
                $moreNames = $hrimsDb->table('hrm_employee')
                    ->whereIn('EmployeeID', $allTeamEmpIds)
                    ->selectRaw("EmployeeID, CONCAT(Fname, ' ', COALESCE(Lname, '')) as name")
                    ->pluck('name', 'EmployeeID')
                    ->toArray();
                $empNames = $empNames + $moreNames;
            }

            // Stagewise TAT for team (aggregate) — current display month
            // Each CASE independently guards both boundary dates so stages with
            // incomplete downstream dates (e.g. payment not yet done) still show
            // data for the stages that ARE complete.
            // NOTE: YearId filter is OMITTED intentionally — the table name already
            // encodes the FY (y8_monthexpensefinal), and YearId is not reliably
            // populated in all records. Month alone is sufficient here.
            $mefTable = 'y' . $yearId . '_monthexpensefinal';
            $tatSelect = [
                DB::raw("ROUND(AVG(CASE WHEN RecevingDate IS NOT NULL AND RecevingDate != '0000-00-00' AND DateOfSubmit IS NOT NULL AND DateOfSubmit != '0000-00-00' THEN GREATEST(0, DATEDIFF(RecevingDate, DateOfSubmit)) END), 1) as submitted"),
                DB::raw("ROUND(AVG(CASE WHEN Verified_Date IS NOT NULL AND Verified_Date != '0000-00-00' AND RecevingDate IS NOT NULL AND RecevingDate != '0000-00-00' THEN GREATEST(0, DATEDIFF(Verified_Date, RecevingDate)) END), 1) as finance_verification"),
                DB::raw("ROUND(AVG(CASE WHEN Approved_Date IS NOT NULL AND Approved_Date != '0000-00-00' AND Verified_Date IS NOT NULL AND Verified_Date != '0000-00-00' THEN GREATEST(0, DATEDIFF(Approved_Date, Verified_Date)) END), 1) as reporting_manager"),
                DB::raw("ROUND(AVG(CASE WHEN Finance_Date IS NOT NULL AND Finance_Date != '0000-00-00' AND Approved_Date IS NOT NULL AND Approved_Date != '0000-00-00' THEN GREATEST(0, DATEDIFF(Finance_Date, Approved_Date)) END), 1) as finance_approval"),
                DB::raw("ROUND(AVG(CASE WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != '0000-00-00' AND Finance_Date IS NOT NULL AND Finance_Date != '0000-00-00' THEN GREATEST(0, DATEDIFF(Fin_PayDate, Finance_Date)) END), 1) as payment"),
            ];

            $teamTAT = $db->table($mefTable)
                ->whereIn('EmployeeID', $teamMembers)
                ->where('Month', $displayMonth)
                ->whereNotNull('DateOfSubmit')
                ->where('DateOfSubmit', '!=', '0000-00-00')
                ->select($tatSelect)
                ->first();

            // Prev month TAT — handle financial year boundary (Mar = month 3 is in prev FY)
            $prevYearId  = ($prevMonth > $displayMonth) ? $yearId - 1 : $yearId;
            $prevMefTable = 'y' . $prevYearId . '_monthexpensefinal';

            $teamTATPrev = $db->table($prevMefTable)
                ->whereIn('EmployeeID', $teamMembers)
                ->where('Month', $prevMonth)
                ->whereNotNull('DateOfSubmit')
                ->where('DateOfSubmit', '!=', '0000-00-00')
                ->select($tatSelect)
                ->first();

            // Claims by status (donut) for team
            $statusCounts = $db->table($table)
                ->whereIn('CrBy', $teamMembers)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('ClaimMonth', $displayMonth)
                ->select('ClaimStatus', DB::raw('COUNT(*) as cnt'))
                ->groupBy('ClaimStatus')
                ->pluck('cnt', 'ClaimStatus')
                ->toArray();

            return [
                'pending_count'  => $pendingCount,
                'pending_amount' => round($pendingAmount),
                'approved_count' => $approvedCount,
                'approved_amount' => round($approvedAmount),
                'returned_count' => $returned,
                'approved_count_prev'  => $approvedCountPrev,
                'approved_amount_prev' => round($approvedAmountPrev),
                'returned_count_prev'  => $returnedPrev,
                'team_expenses' => round((float)$teamExpenses),
                'total_claims' => $totalClaims,
                'team_size' => count($teamMembers),
                'approval_inbox' => $approvalInbox,
                'emp_names' => $empNames,
                'expense_by_type' => $expenseByType,
                'claim_names' => $claimNames,
                'trend_labels' => $trendLabels,
                'trend_amounts' => $trendAmounts,
                'trend_counts' => $trendCounts,
                'team_summary' => $teamSummary,
                'team_tat' => $teamTAT,
                'team_tat_prev' => $teamTATPrev,
                'status_counts' => $statusCounts,
                'display_month' => $displayMonth,
            ];
        } catch (\Exception $e) {
            \Log::error('RM Dashboard data error: ' . $e->getMessage());
            return $this->emptyRmDashData();
        }
    }

    private function emptyRmDashData()
    {
        return [
            'pending_count' => 0, 'pending_amount' => 0,
            'approved_count' => 0, 'approved_amount' => 0,
            'returned_count' => 0,
            'approved_count_prev' => 0, 'approved_amount_prev' => 0,
            'returned_count_prev' => 0, 'team_expenses' => 0,
            'total_claims' => 0, 'team_size' => 0,
            'approval_inbox' => collect(), 'emp_names' => [],
            'expense_by_type' => collect(), 'claim_names' => [],
            'trend_labels' => [], 'trend_amounts' => [], 'trend_counts' => [],
            'team_summary' => collect(), 'team_tat' => null, 'team_tat_prev' => null,
            'status_counts' => [], 'display_month' => 0,
        ];
    }

    /**
     * AJAX: Get odometer trend data for a specific financial year
     */
    public function getOdometerTrendByYear()
    {
        $employeeId = session('employee_id');
        $yearId = (int) request('year_id', \App\Support\CurrentYear::id());
        $data = $this->getOdometerTrend($employeeId, $yearId);
        $fyLabel = $this->expenseDb()->table('financialyear')->where('YearId', $yearId)->value('Year') ?? '';
        $data['fy_label'] = $fyLabel;
        return response()->json($data);
    }

    /**
     * Odometer Trend (Full Financial Year: Apr-Mar) - Kms Driven vs Reimbursable Kms
     */
    private function getOdometerTrend($employeeId, $yearId)
    {
        try {
            $table = 'y' . $yearId . '_expenseclaims';
            $db = $this->expenseDb();

            // Financial year months: Apr(4) to Mar(3)
            $fyMonths = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];

            $data = $db->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimId', 7) // Odometer claim type
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->whereIn('ClaimMonth', $fyMonths)
                ->select([
                    'ClaimMonth',
                    DB::raw('SUM(TotKm) as total_km'),
                    DB::raw('SUM(CASE WHEN FilledOkay = 1 THEN TotKm ELSE 0 END) as reimbursable_km'),
                ])
                ->groupBy('ClaimMonth')
                ->get()
                ->keyBy('ClaimMonth');

            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $result = [
                'labels' => [],
                'kms_driven' => [],
                'reimbursable_kms' => [],
                'ytd_driven' => 0,
                'ytd_reimbursable' => 0,
                'ytd_amount' => 0,
            ];

            foreach ($fyMonths as $m) {
                $result['labels'][] = $monthNames[$m];
                $result['kms_driven'][] = (int)($data[$m]->total_km ?? 0);
                $result['reimbursable_kms'][] = (int)($data[$m]->reimbursable_km ?? 0);
            }

            // YTD totals (full FY in this year table)
            $ytd = $db->table($table)
                ->where('CrBy', $employeeId)
                ->where('ClaimId', 7)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->selectRaw('SUM(TotKm) as ytd_driven, SUM(CASE WHEN FilledOkay = 1 THEN TotKm ELSE 0 END) as ytd_reimbursable, SUM(CASE WHEN FilledOkay = 1 THEN FilledTAmt ELSE 0 END) as ytd_amount')
                ->first();

            $result['ytd_driven'] = (int)($ytd->ytd_driven ?? 0);
            $result['ytd_reimbursable'] = (int)($ytd->ytd_reimbursable ?? 0);
            $result['ytd_amount'] = round((float)($ytd->ytd_amount ?? 0));

            return $result;
        } catch (\Exception $e) {
            \Log::error('Odometer Trend error: ' . $e->getMessage());
            return [
                'labels' => [], 'kms_driven' => [], 'reimbursable_kms' => [],
                'ytd_driven' => 0, 'ytd_reimbursable' => 0, 'ytd_amount' => 0,
            ];
        }
    }
}
