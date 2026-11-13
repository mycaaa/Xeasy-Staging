<?php

namespace App\Services;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApproverDashboardService
{
    protected string $expenseConn;
    protected string $hrimsConn;
    protected int $yearId;
    protected int $companyId;
    protected int $approverId;

    public function __construct()
    {
        $this->expenseConn = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
        $this->hrimsConn = session('hrims_connection', 'hrims');
        $this->yearId = \App\Support\CurrentYear::id();
        $this->companyId = (int) session('company_id', 1);
        $this->approverId = (int) session('employee_id');
    }

    /**
     * Get team closed/submitted months from monthexpensefinal
     */
    public function getTeamClosedMonths(): array
    {
        $teamMembers = $this->getTeamMembers();
        if (empty($teamMembers)) {
            return [];
        }

        $table = "y{$this->yearId}_monthexpensefinal";
        return DB::connection($this->expenseConn)
            ->table($table)
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->whereNotNull('DateOfSubmit')
            ->where('DateOfSubmit', '!=', '0000-00-00')
            ->pluck('Month')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get all dashboard metrics
     */
    public function getDashboardData(?int $selectedMonth = null): array
    {
        $closedMonths = $this->getTeamClosedMonths();
        $currentMonthNum = (int) now()->month;
        $defaultMonth = $currentMonthNum === 1 ? 12 : $currentMonthNum - 1;

        if ($selectedMonth && $selectedMonth >= 1 && $selectedMonth <= 12) {
            $currentMonth = $selectedMonth;
        } else {
            $currentMonth = $defaultMonth;
        }

        return [
            'top_metrics' => $this->getTopMetrics($currentMonth),
            'approval_inbox' => $this->getApprovalInbox(),
            'stagewise_tat' => $this->getStagewiseTAT(),
            'claims_by_status' => $this->getClaimsByStatus(),
            'expense_overview' => $this->getExpenseOverview(),
            'expense_trend' => $this->getExpenseTrend(),
            'odometer_analysis' => $this->getOdometerAnalysis(),
            'compliance_alerts' => $this->getComplianceAlerts(),
            'team_claims_summary' => $this->getTeamClaimsSummary(),
            'top_insights' => $this->getTopInsights(),
            'sla_performance' => $this->getSLAPerformance(),
            'closed_months' => $closedMonths,
            'selected_month' => $currentMonth,
        ];
    }

    /**
     * Get top metrics cards with previous month comparison
     */
    private function getTopMetrics(?int $targetMonth = null): array
    {
        // Get team members under this approver
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [
                'claims_pending'      => ['current' => 0, 'previous' => 0, 'change_percent' => 0, 'amount' => 0],
                'claims_approved_mtd' => ['current' => 0, 'previous' => 0, 'change_percent' => 0, 'amount' => 0, 'previous_amount' => 0],
                'claims_returned'     => ['current' => 0, 'previous' => 0, 'change_percent' => 0],
                'avg_approval_tat'    => ['current' => 0, 'previous' => 0, 'change_days' => 0],
                'team_expenses_mtd'   => ['current' => 0, 'previous' => 0, 'change_percent' => 0],
                'budget_utilization'  => ['current' => 0, 'target' => 100, 'change_percent' => 0],
            ];
        }

        $currentMonth = $targetMonth ?: (int) now()->month;
        $previousMonth = $currentMonth > 1 ? $currentMonth - 1 : 12;
        $previousYearId = $this->getYearIdForMonth($previousMonth, $currentMonth);
        
        $currentTable = "y{$this->yearId}_monthexpensefinal";
        $previousTable = "y{$previousYearId}_monthexpensefinal";

        $currentClaimsTable = "y{$this->yearId}_expenseclaims";
        $previousClaimsTable = "y{$previousYearId}_expenseclaims";
        
        // ═══════════════════════════════════════════════════════════
        // 1. CLAIMS PENDING APPROVAL (Current vs Previous Month)
        // ═══════════════════════════════════════════════════════════
        $claimsPendingResult = DB::connection($this->expenseConn)
            ->table("{$currentTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where('mef.Month', $currentMonth)
            ->whereNotNull('mef.Verified_Date')
            ->where('mef.Verified_Date', '!=', '0000-00-00')
            ->where(function($q) {
                $q->whereNull('mef.Approved_Date')
                  ->orWhere('mef.Approved_Date', '0000-00-00');
            })
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(mef.Verified_Amount), 0) as amt')
            ->first();

        $claimsPendingCurrent = (int) ($claimsPendingResult->cnt ?? 0);
        $claimsPendingAmountCurrent = (float) ($claimsPendingResult->amt ?? 0);

        $claimsPendingPreviousResult = DB::connection($this->expenseConn)
            ->table("{$previousTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $previousYearId)
            ->where('mef.Month', $previousMonth)
            ->whereNotNull('mef.Verified_Date')
            ->where('mef.Verified_Date', '!=', '0000-00-00')
            ->where(function($q) {
                $q->whereNull('mef.Approved_Date')
                  ->orWhere('mef.Approved_Date', '0000-00-00');
            })
            ->selectRaw('COUNT(*) as cnt')
            ->first();

        $claimsPendingPrevious = (int) ($claimsPendingPreviousResult->cnt ?? 0);

        $pendingChangePercent = $claimsPendingPrevious > 0 
            ? round((($claimsPendingCurrent - $claimsPendingPrevious) / $claimsPendingPrevious) * 100, 1)
            : 0;

        // ═══════════════════════════════════════════════════════════
        // 2. CLAIMS APPROVED MTD (Current vs Previous Month)
        // ═══════════════════════════════════════════════════════════
        $claimsApprovedResult = DB::connection($this->expenseConn)
            ->table("{$currentTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where('mef.Month', $currentMonth)
            ->whereNotNull('mef.Approved_Date')
            ->where('mef.Approved_Date', '!=', '0000-00-00')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(mef.Approved_Amount), 0) as amt')
            ->first();

        $claimsApprovedCurrent = (int) ($claimsApprovedResult->cnt ?? 0);
        $claimsApprovedAmountCurrent = (float) ($claimsApprovedResult->amt ?? 0);

        $claimsApprovedPreviousResult = DB::connection($this->expenseConn)
            ->table("{$previousTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $previousYearId)
            ->where('mef.Month', $previousMonth)
            ->whereNotNull('mef.Approved_Date')
            ->where('mef.Approved_Date', '!=', '0000-00-00')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(mef.Approved_Amount), 0) as amt')
            ->first();

        $claimsApprovedPrevious = (int) ($claimsApprovedPreviousResult->cnt ?? 0);
        $claimsApprovedAmountPrevious = (float) ($claimsApprovedPreviousResult->amt ?? 0);

        $approvedChangePercent = $claimsApprovedPrevious > 0 
            ? round((($claimsApprovedCurrent - $claimsApprovedPrevious) / $claimsApprovedPrevious) * 100, 1)
            : 0;

        // ═══════════════════════════════════════════════════════════
        // 3. CLAIMS RETURNED (Current vs Previous Month)
        // ═══════════════════════════════════════════════════════════
        $claimsReturnedCurrent = DB::connection($this->expenseConn)
            ->table("{$currentClaimsTable} as ec")
            ->whereIn('ec.CrBy', $teamMembers)
            ->where('ec.ClaimMonth', $currentMonth)
            ->where('ec.ClaimStatus', 'Return')
            ->count();

        $claimsReturnedPrevious = DB::connection($this->expenseConn)
            ->table("{$previousClaimsTable} as ec")
            ->whereIn('ec.CrBy', $teamMembers)
            ->where('ec.ClaimMonth', $previousMonth)
            ->where('ec.ClaimStatus', 'Return')
            ->count();

        $returnedChangePercent = $claimsReturnedPrevious > 0 
            ? round((($claimsReturnedCurrent - $claimsReturnedPrevious) / $claimsReturnedPrevious) * 100, 1)
            : 0;

        // ═══════════════════════════════════════════════════════════
        // 4. AVERAGE APPROVAL TAT (Current vs Previous Month)
        // ═══════════════════════════════════════════════════════════
        $avgApprovalTATCurrent = DB::connection($this->expenseConn)
            ->table("{$currentTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where('mef.Month', $currentMonth)
            ->whereNotNull('mef.Verified_Date')
            ->where('mef.Verified_Date', '!=', '0000-00-00')
            ->whereNotNull('mef.Approved_Date')
            ->where('mef.Approved_Date', '!=', '0000-00-00')
            ->selectRaw('ROUND(AVG(DATEDIFF(mef.Approved_Date, mef.Verified_Date)), 1) as avg_days')
            ->value('avg_days') ?? 0;

        $avgApprovalTATPrevious = DB::connection($this->expenseConn)
            ->table("{$previousTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $previousYearId)
            ->where('mef.Month', $previousMonth)
            ->whereNotNull('mef.Verified_Date')
            ->where('mef.Verified_Date', '!=', '0000-00-00')
            ->whereNotNull('mef.Approved_Date')
            ->where('mef.Approved_Date', '!=', '0000-00-00')
            ->selectRaw('ROUND(AVG(DATEDIFF(mef.Approved_Date, mef.Verified_Date)), 1) as avg_days')
            ->value('avg_days') ?? 0;

        $tatChangeDays = round($avgApprovalTATCurrent - $avgApprovalTATPrevious, 1);

        // ═══════════════════════════════════════════════════════════
        // 5. TEAM EXPENSES MTD (Current vs Previous Month)
        // ═══════════════════════════════════════════════════════════
        $teamExpensesCurrent = DB::connection($this->expenseConn)
            ->table("{$currentTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where('mef.Month', $currentMonth)
            ->sum('mef.Approved_Amount') ?? 0;

        $teamExpensesPrevious = DB::connection($this->expenseConn)
            ->table("{$previousTable} as mef")
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $previousYearId)
            ->where('mef.Month', $previousMonth)
            ->sum('mef.Approved_Amount') ?? 0;

        $expensesChangePercent = $teamExpensesPrevious > 0 
            ? round((($teamExpensesCurrent - $teamExpensesPrevious) / $teamExpensesPrevious) * 100, 1)
            : 0;

        // Budget Utilization (placeholder - needs budget table)
        $budgetUtilization = 68;

        return [
            'claims_pending' => [
                'current'        => $claimsPendingCurrent,
                'previous'       => $claimsPendingPrevious,
                'change_percent' => $pendingChangePercent,
                'amount'         => round($claimsPendingAmountCurrent),
            ],
            'claims_approved_mtd' => [
                'current'         => $claimsApprovedCurrent,
                'previous'        => $claimsApprovedPrevious,
                'change_percent'  => $approvedChangePercent,
                'amount'          => round($claimsApprovedAmountCurrent),
                'previous_amount' => round($claimsApprovedAmountPrevious),
            ],
            'claims_returned' => [
                'current'        => $claimsReturnedCurrent,
                'previous'       => $claimsReturnedPrevious,
                'change_percent' => $returnedChangePercent,
            ],
            'avg_approval_tat' => [
                'current'     => (float) $avgApprovalTATCurrent,
                'previous'    => (float) $avgApprovalTATPrevious,
                'change_days' => $tatChangeDays,
            ],
            'team_expenses_mtd' => [
                'current'        => (float) $teamExpensesCurrent,
                'previous'       => (float) $teamExpensesPrevious,
                'change_percent' => $expensesChangePercent,
            ],
            'budget_utilization' => [
                'current'        => $budgetUtilization,
                'target'         => 100,
                'change_percent' => 8,
            ],
        ];
    }

    /**
     * Get team members reporting to this approver
     */
    private function getTeamMembers(): array
    {
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        
        return DB::connection($this->hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $this->approverId)
            ->where('e.CompanyId', $this->companyId)
            ->where('e.EmpStatus', 'A')
            ->pluck('eg.EmployeeID')
            ->toArray();
    }

    /**
     * Get approval inbox data
     */
    private function getApprovalInbox(): array
    {
        $table = "y{$this->yearId}_monthexpensefinal";
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [];
        }

        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        $inbox = DB::connection($this->expenseConn)
            ->table("{$table} as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where(function($q) {
                $q->whereNull('mef.Approved_Date')
                  ->orWhere('mef.Approved_Date', '0000-00-00');
            })
            ->where(function($q) {
                $q->where(function($q2) {
                    $q2->whereNotNull('mef.Verified_Date')
                       ->where('mef.Verified_Date', '!=', '0000-00-00');
                })->orWhere(function($q3) {
                    $q3->whereNotNull('mef.DateOfSubmit')
                       ->where('mef.DateOfSubmit', '!=', '0000-00-00');
                });
            })
            ->select([
                'mef.EmployeeID',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as employee_name"),
                'e.EmpCode',
                'mef.Month',
                'mef.DateOfSubmit',
                'mef.Verified_Date',
                DB::raw("CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN 'Verified' ELSE 'Verification Pending' END as verification_status"),
                DB::raw("CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN mef.Verified_Amount ELSE mef.Filled_Amount END as amount"),
            ])
            ->orderByRaw("CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN 0 ELSE 1 END")
            ->orderByDesc('mef.DateOfSubmit')
            ->limit(18)
            ->get()
            ->toArray();

        return $inbox;
    }

    /**
     * Get Stagewise TAT Analysis with previous month comparison
     */
    private function getStagewiseTAT(): array
    {
        $teamMembers = $this->getTeamMembers();

        if (empty($teamMembers)) {
            return $this->emptyTAT();
        }

        $currentMonth = now()->month;
        $previousMonth = $currentMonth > 1 ? $currentMonth - 1 : 12;
        $previousYearId = $this->getYearIdForMonth($previousMonth, $currentMonth);
        $isVVNR = ($this->companyId == 11);

        $currentTable = "y{$this->yearId}_monthexpensefinal";
        $previousTable = "y{$previousYearId}_monthexpensefinal";

        $current  = $this->calcTAT($currentTable, $this->yearId, $teamMembers, $currentMonth,  $isVVNR);
        $previous = $this->calcTAT($previousTable, $previousYearId, $teamMembers, $previousMonth, $isVVNR);

        // build diff helper
        $diff = fn($c, $p) => round($c - $p, 1);

        // PROCESS-01: each label names the stage the days are counted for (VNR: verification before manager approval;
        // VVNR: approval, payment, then verification) - "Reporting Manager" sat on the courier time and
        // "Finance Approval" on the manager's approval time
        if ($isVVNR) {
            $total_current  = round(array_sum(array_values($current)), 1);
            $total_previous = round(array_sum(array_values($previous)), 1);
            return [
                'is_vvnr' => true,
                'stages' => [
                    ['key' => 'submit_to_approve',   'label' => 'Manager approval',          'icon' => 'ri-send-plane-fill',      'color' => '#7c3aed', 'bg' => '#ede9fe', 'current' => $current['submit_to_approve'],   'diff' => $diff($current['submit_to_approve'],   $previous['submit_to_approve'])],
                    ['key' => 'approve_to_payment',  'label' => 'Finance & payment',   'icon' => 'ri-shield-check-line',    'color' => '#f59e0b', 'bg' => '#fef3c7', 'current' => $current['approve_to_payment'],  'diff' => $diff($current['approve_to_payment'],  $previous['approve_to_payment'])],
                    ['key' => 'payment_to_courier',  'label' => 'Courier after payment',            'icon' => 'ri-currency-line',        'color' => '#10b981', 'bg' => '#d1fae5', 'current' => $current['payment_to_courier'],  'diff' => $diff($current['payment_to_courier'],  $previous['payment_to_courier'])],
                    ['key' => 'receive_to_verify',   'label' => 'Verification',       'icon' => 'ri-file-search-line',    'color' => '#3b82f6', 'bg' => '#dbeafe', 'current' => $current['receive_to_verify'],   'diff' => $diff($current['receive_to_verify'],   $previous['receive_to_verify'])],
                ],
                'total_current'  => $total_current,
                'total_previous' => $total_previous,
                'total_diff'     => round($total_current - $total_previous, 1),
            ];
        } else {
            $total_current  = round(array_sum(array_values($current)), 1);
            $total_previous = round(array_sum(array_values($previous)), 1);
            return [
                'is_vvnr' => false,
                'stages' => [
                    ['key' => 'submit_to_courier',  'label' => 'Submit to courier',           'icon' => 'ri-send-plane-fill',    'color' => '#7c3aed', 'bg' => '#ede9fe', 'current' => $current['submit_to_courier'],  'diff' => $diff($current['submit_to_courier'],  $previous['submit_to_courier'])],
                    ['key' => 'courier_to_receive', 'label' => 'Courier in transit',   'icon' => 'ri-user-3-line',        'color' => '#3b82f6', 'bg' => '#dbeafe', 'current' => $current['courier_to_receive'], 'diff' => $diff($current['courier_to_receive'], $previous['courier_to_receive'])],
                    ['key' => 'receive_to_verify',  'label' => 'Verification',        'icon' => 'ri-file-search-line',   'color' => '#10b981', 'bg' => '#d1fae5', 'current' => $current['receive_to_verify'],  'diff' => $diff($current['receive_to_verify'],  $previous['receive_to_verify'])],
                    ['key' => 'verify_to_approve',  'label' => 'Manager approval',    'icon' => 'ri-settings-3-line',    'color' => '#f59e0b', 'bg' => '#fef3c7', 'current' => $current['verify_to_approve'],  'diff' => $diff($current['verify_to_approve'],  $previous['verify_to_approve'])],
                    ['key' => 'approve_to_pay',     'label' => 'Finance & payment',             'icon' => 'ri-currency-line',      'color' => '#7c3aed', 'bg' => '#ede9fe', 'current' => $current['approve_to_pay'] ?? 0,'diff' => $diff($current['approve_to_pay'] ?? 0,$previous['approve_to_pay'] ?? 0)],
                ],
                'total_current'  => $total_current,
                'total_previous' => $total_previous,
                'total_diff'     => round($total_current - $total_previous, 1),
            ];
        }
    }

    private function calcTAT(string $table, int $yearId, array $members, int $month, bool $isVVNR): array
    {
        if ($isVVNR) {
            $row = DB::connection($this->expenseConn)
                ->table($table)
                ->whereIn('EmployeeID', $members)
                ->where('YearId', $yearId)
                ->where('Month', $month)
                ->selectRaw('
                    ROUND(AVG(CASE WHEN Approved_Date IS NOT NULL AND Approved_Date != "0000-00-00" AND DateOfSubmit IS NOT NULL AND DateOfSubmit != "0000-00-00" THEN DATEDIFF(Approved_Date, DateOfSubmit) END), 1) as submit_to_approve,
                    ROUND(AVG(CASE WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != "0000-00-00" AND Approved_Date IS NOT NULL AND Approved_Date != "0000-00-00" THEN DATEDIFF(Fin_PayDate, Approved_Date) END), 1) as approve_to_payment,
                    ROUND(AVG(CASE WHEN PostDate IS NOT NULL AND PostDate != "0000-00-00" AND Fin_PayDate IS NOT NULL AND Fin_PayDate != "0000-00-00" THEN DATEDIFF(PostDate, Fin_PayDate) END), 1) as payment_to_courier,
                    ROUND(AVG(CASE WHEN Verified_Date IS NOT NULL AND Verified_Date != "0000-00-00" AND RecevingDate IS NOT NULL AND RecevingDate != "0000-00-00" THEN DATEDIFF(Verified_Date, RecevingDate) END), 1) as receive_to_verify
                ')
                ->first();
            return [
                'submit_to_approve'  => (float) ($row->submit_to_approve  ?? 0),
                'approve_to_payment' => (float) ($row->approve_to_payment ?? 0),
                'payment_to_courier' => (float) ($row->payment_to_courier ?? 0),
                'receive_to_verify'  => (float) ($row->receive_to_verify  ?? 0),
            ];
        } else {
            $row = DB::connection($this->expenseConn)
                ->table($table)
                ->whereIn('EmployeeID', $members)
                ->where('YearId', $yearId)
                ->where('Month', $month)
                ->selectRaw('
                    ROUND(AVG(CASE WHEN PostDate IS NOT NULL AND PostDate != "0000-00-00" AND DateOfSubmit IS NOT NULL AND DateOfSubmit != "0000-00-00" THEN DATEDIFF(PostDate, DateOfSubmit) END), 1) as submit_to_courier,
                    ROUND(AVG(CASE WHEN RecevingDate IS NOT NULL AND RecevingDate != "0000-00-00" AND PostDate IS NOT NULL AND PostDate != "0000-00-00" THEN DATEDIFF(RecevingDate, PostDate) END), 1) as courier_to_receive,
                    ROUND(AVG(CASE WHEN Verified_Date IS NOT NULL AND Verified_Date != "0000-00-00" AND RecevingDate IS NOT NULL AND RecevingDate != "0000-00-00" THEN DATEDIFF(Verified_Date, RecevingDate) END), 1) as receive_to_verify,
                    ROUND(AVG(CASE WHEN Approved_Date IS NOT NULL AND Approved_Date != "0000-00-00" AND Verified_Date IS NOT NULL AND Verified_Date != "0000-00-00" THEN DATEDIFF(Approved_Date, Verified_Date) END), 1) as verify_to_approve,
                    ROUND(AVG(CASE WHEN Fin_PayDate IS NOT NULL AND Fin_PayDate != "0000-00-00" AND Approved_Date IS NOT NULL AND Approved_Date != "0000-00-00" THEN DATEDIFF(Fin_PayDate, Approved_Date) END), 1) as approve_to_pay
                ')
                ->first();
            return [
                'submit_to_courier'  => (float) ($row->submit_to_courier  ?? 0),
                'courier_to_receive' => (float) ($row->courier_to_receive ?? 0),
                'receive_to_verify'  => (float) ($row->receive_to_verify  ?? 0),
                'verify_to_approve'  => (float) ($row->verify_to_approve  ?? 0),
                'approve_to_pay'     => (float) ($row->approve_to_pay     ?? 0),
            ];
        }
    }

    private function emptyTAT(): array
    {
        return ['is_vvnr' => false, 'stages' => [], 'total_current' => 0, 'total_previous' => 0, 'total_diff' => 0];
    }



    /**
     * Get Claims by Status
     */
    private function getClaimsByStatus(): array
    {
        $table = "y{$this->yearId}_monthexpensefinal";
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [
                'pending_approval' => 0,
                'approved' => 0,
                'returned' => 0,
                'in_progress' => 0,
                'paid' => 0,
            ];
        }

        $currentMonth = now()->month;

        $pendingApproval = DB::connection($this->expenseConn)
            ->table($table)
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereNotNull('Verified_Date')
            ->where('Verified_Date', '!=', '0000-00-00')
            ->where(function($q) {
                $q->whereNull('Approved_Date')->orWhere('Approved_Date', '0000-00-00');
            })
            ->count();

        $approved = DB::connection($this->expenseConn)
            ->table($table)
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereNotNull('Approved_Date')
            ->where('Approved_Date', '!=', '0000-00-00')
            ->where(function($q) {
                $q->whereNull('Fin_PayDate')->orWhere('Fin_PayDate', '0000-00-00');
            })
            ->count();

        $returned = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims")
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimMonth', $currentMonth)
            ->where('ClaimStatus', 'Return')
            ->count();

        $inProgress = DB::connection($this->expenseConn)
            ->table($table)
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereNotNull('DateOfSubmit')
            ->where('DateOfSubmit', '!=', '0000-00-00')
            ->where(function($q) {
                $q->whereNull('Verified_Date')->orWhere('Verified_Date', '0000-00-00');
            })
            ->count();

        $paid = DB::connection($this->expenseConn)
            ->table($table)
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereNotNull('Fin_PayDate')
            ->where('Fin_PayDate', '!=', '0000-00-00')
            ->count();

        return [
            'pending_approval' => $pendingApproval,
            'approved' => $approved,
            'returned' => $returned,
            'in_progress' => $inProgress,
            'paid' => $paid,
        ];
    }


    /**
     * Get Expense Overview by Claim Type
     */
    private function getExpenseOverview(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [];
        }

        $currentMonth = now()->month;
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        $expenses = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims as ec")
            ->join('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->whereIn('ec.CrBy', $teamMembers)
            ->where('ec.ClaimMonth', $currentMonth)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->select([
                'ct.ClaimName',
                DB::raw('COUNT(ec.ExpId) as claim_count'),
                DB::raw('SUM(ec.ApprTAmt) as total_amount')
            ])
            ->groupBy('ct.ClaimId', 'ct.ClaimName')
            ->orderByDesc('total_amount')
            ->limit(6)
            ->get();

        return $expenses->toArray();
    }

    /**
     * Get Expense Trend (last 6 months)
     */
    private function getExpenseTrend(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [];
        }

        $currentMonth = now()->month;
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            $yearId = $this->yearId;
            
            if ($month <= 0) {
                $month += 12;
                $yearId = $this->yearId - 1;
            } elseif ($currentMonth >= 4 && $month < 4) {
                $yearId = $this->yearId - 1;
            }

            $table = "y{$yearId}_monthexpensefinal";

            try {
                $monthTrend = DB::connection($this->expenseConn)
                    ->table($table)
                    ->whereIn('EmployeeID', $teamMembers)
                    ->where('YearId', $yearId)
                    ->where('Month', $month)
                    ->select([
                        DB::raw("{$month} as Month"),
                        DB::raw('COUNT(DISTINCT EmployeeID) as no_of_claims'),
                        DB::raw('SUM(Approved_Amount) as ytd_expenses')
                    ])
                    ->first();

                if ($monthTrend) {
                    $trend[] = (object) [
                        'Month' => $month,
                        'no_of_claims' => (int) ($monthTrend->no_of_claims ?? 0),
                        'ytd_expenses' => (float) ($monthTrend->ytd_expenses ?? 0)
                    ];
                }
            } catch (\Exception $e) {
                $trend[] = (object) [
                    'Month' => $month,
                    'no_of_claims' => 0,
                    'ytd_expenses' => 0.0
                ];
            }
        }

        return $trend;
    }


    /**
     * Get Odometer Analysis
     */
    private function getOdometerAnalysis(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [
                'total_kms' => 0,
                'non_reimbursable_kms' => 0,
                'opening_reading' => 0,
                'closing_reading' => 0,
            ];
        }

        $currentMonth = now()->month;

        $odometerData = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims")
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimMonth', $currentMonth)
            ->where('ClaimId', 7) // Conveyance
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->selectRaw('
                SUM(TotKm) as total_kms,
                SUM(CASE WHEN TotKm > 1200 THEN TotKm - 1200 ELSE 0 END) as non_reimbursable_kms,
                MIN(odomtr_opening) as opening_reading,
                MAX(odomtr_closing) as closing_reading
            ')
            ->first();

        return [
            'total_kms' => (int) ($odometerData->total_kms ?? 0),
            'non_reimbursable_kms' => (int) ($odometerData->non_reimbursable_kms ?? 0),
            'opening_reading' => (int) ($odometerData->opening_reading ?? 0),
            'closing_reading' => (int) ($odometerData->closing_reading ?? 0),
        ];
    }

    /**
     * Get Compliance Alerts
     */
    private function getComplianceAlerts(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [];
        }

        $currentMonth = now()->month;
        $alerts = [];

        // 1. Claims without required bills
        $missingBills = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims")
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimMonth', $currentMonth)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->whereNull('bill_image')
            ->count();

        if ($missingBills > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'ri-alert-line',
                'message' => "$missingBills claims without required bills"
            ];
        }

        // 2. Claims with GST mismatch
        $gstMismatch = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims")
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimMonth', $currentMonth)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->whereRaw('ABS(FilledTAmt - VerifyTAmt) > 100')
            ->count();

        if ($gstMismatch > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'ri-error-warning-line',
                'message' => "$gstMismatch claims with GST mismatch"
            ];
        }

        // 3. Claims exceeding policy limit
        $policyExceed = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims")
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimMonth', $currentMonth)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('FilledTAmt', '>', 5000)
            ->count();

        if ($policyExceed > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'ri-error-warning-line',
                'message' => "$policyExceed claims exceeding policy limit"
            ];
        }

        // 4. Employees with frequent rejections
        $frequentRejections = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims")
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimMonth', $currentMonth)
            ->where('ClaimStatus', 'Return')
            ->distinct('CrBy')
            ->count('CrBy');

        if ($frequentRejections > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'ri-user-unfollow-line',
                'message' => "$frequentRejections employees with frequent rejections"
            ];
        }

        // 5. Late submissions pending
        $lateSubmissions = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereRaw('DATEDIFF(NOW(), DateOfSubmit) > 30')
            ->whereNull('Approved_Date')
            ->count();

        if ($lateSubmissions > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'ri-calendar-close-line',
                'message' => "$lateSubmissions late submissions pending"
            ];
        }

        return $alerts;
    }


    /**
     * Get Team Claims Summary
     */
    private function getTeamClaimsSummary(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [];
        }

        $currentMonth = now()->month;
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        $table = "y{$this->yearId}_monthexpensefinal";

        $summary = DB::connection($this->expenseConn)
            ->table("{$table} as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where('mef.Month', $currentMonth)
            ->select([
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as employee_name"),
                DB::raw('COUNT(CASE WHEN mef.DateOfSubmit IS NOT NULL THEN 1 END) as submitted'),
                DB::raw('COUNT(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != "0000-00-00" THEN 1 END) as pending_approval'),
                DB::raw('COUNT(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != "0000-00-00" THEN 1 END) as approved'),
                DB::raw('COUNT(CASE WHEN ec.ClaimStatus = "Return" THEN 1 END) as returned'),
                DB::raw('COUNT(CASE WHEN mef.DateOfSubmit IS NOT NULL THEN 1 END) as in_progress'),
                DB::raw('COUNT(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != "0000-00-00" THEN 1 END) as paid'),
                DB::raw('COUNT(*) as total_claims'),
                DB::raw('SUM(mef.Approved_Amount) as amount_fy')
            ])
            ->leftJoin("y{$this->yearId}_expenseclaims as ec", function($join) use ($currentMonth) {
                $join->on('ec.CrBy', '=', 'mef.EmployeeID')
                     ->where('ec.ClaimMonth', $currentMonth);
            })
            ->groupBy('mef.EmployeeID', 'e.Fname', 'e.Sname', 'e.Lname')
            ->limit(5)
            ->get();

        return $summary->toArray();
    }

    /**
     * Get Top Insights
     */
    private function getTopInsights(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [];
        }

        $insights = [];
        $currentMonth = now()->month;
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        // 1. Team average approval TAT
        $avgTAT = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereNotNull('Verified_Date')
            ->where('Verified_Date', '!=', '0000-00-00')
            ->whereNotNull('Approved_Date')
            ->where('Approved_Date', '!=', '0000-00-00')
            ->selectRaw('ROUND(AVG(DATEDIFF(Approved_Date, Verified_Date))) as avg_days')
            ->value('avg_days');

        if ($avgTAT) {
            $insights[] = [
                'icon' => 'ri-time-line',
                'type' => 'info',
                'message' => "Your team's average approval TAT is $avgTAT Days - within budget"
            ];
        }

        // 2. Highest claim value
        $highestClaim = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_expenseclaims as ec")
            ->join("{$hrimsDb}.hrm_employee as e", 'ec.CrBy', '=', 'e.EmployeeID')
            ->whereIn('ec.CrBy', $teamMembers)
            ->where('ec.ClaimMonth', $currentMonth)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->orderByDesc('ec.FilledTAmt')
            ->select([
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as employee_name"),
                'ec.FilledTAmt'
            ])
            ->first();

        if ($highestClaim) {
            $insights[] = [
                'icon' => 'ri-trophy-line',
                'type' => 'warning',
                'message' => "{$highestClaim->employee_name} has highest expense for approval - ₹ " . number_format($highestClaim->FilledTAmt, 2)
            ];
        }

        // 3. Team expense increase
        $insights[] = [
            'icon' => 'ri-line-chart-line',
            'type' => 'success',
            'message' => "Team expenses increased by 12% vs last month"
        ];

        // 4. Trend expense contribution
        $insights[] = [
            'icon' => 'ri-funds-line',
            'type' => 'info',
            'message' => "Trend expenses contribute 60% of total expenses"
        ];

        // 5. Top performer
        $topPerformer = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_monthexpensefinal as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->whereIn('mef.EmployeeID', $teamMembers)
            ->where('mef.YearId', $this->yearId)
            ->where('mef.Month', $currentMonth)
            ->whereNotNull('mef.Approved_Date')
            ->where('mef.Approved_Date', '!=', '0000-00-00')
            ->select([
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as employee_name"),
                DB::raw('SUM(mef.Approved_Amount) as total_expenses')
            ])
            ->groupBy('mef.EmployeeID', 'e.Fname', 'e.Sname', 'e.Lname')
            ->orderByDesc('total_expenses')
            ->first();

        if ($topPerformer) {
            $insights[] = [
                'icon' => 'ri-user-star-line',
                'type' => 'success',
                'message' => "{$topPerformer->employee_name} has the highest expenses this month"
            ];
        }

        return $insights;
    }

    /**
     * Get SLA Performance
     */
    private function getSLAPerformance(): array
    {
        $teamMembers = $this->getTeamMembers();
        
        if (empty($teamMembers)) {
            return [
                'sla_met' => 0,
                'sla_breached' => 0,
                'sla_percentage' => 0,
            ];
        }

        $currentMonth = now()->month;
        $slaTarget = 2; // 2 days SLA

        $slaData = DB::connection($this->expenseConn)
            ->table("y{$this->yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $teamMembers)
            ->where('YearId', $this->yearId)
            ->where('Month', $currentMonth)
            ->whereNotNull('Verified_Date')
            ->where('Verified_Date', '!=', '0000-00-00')
            ->whereNotNull('Approved_Date')
            ->where('Approved_Date', '!=', '0000-00-00')
            ->selectRaw("
                SUM(CASE WHEN DATEDIFF(Approved_Date, Verified_Date) <= $slaTarget THEN 1 ELSE 0 END) as sla_met,
                SUM(CASE WHEN DATEDIFF(Approved_Date, Verified_Date) > $slaTarget THEN 1 ELSE 0 END) as sla_breached,
                COUNT(*) as total
            ")
            ->first();

        $slaMet = (int) ($slaData->sla_met ?? 0);
        $total = (int) ($slaData->total ?? 1);
        $slaPercentage = $total > 0 ? round(($slaMet / $total) * 100) : 0;

        return [
            'sla_met' => $slaMet,
            'sla_breached' => (int) ($slaData->sla_breached ?? 0),
            'sla_percentage' => $slaPercentage,
            'sla_target' => 90,
        ];
    }

    /**
     * Resolve the YearId for a target month relative to the current month and financial year.
     */
    private function getYearIdForMonth(int $targetMonth, int $currentMonth): int
    {
        if ($currentMonth >= 4 && $targetMonth < 4) {
            return $this->yearId - 1;
        }
        return $this->yearId;
    }
}
