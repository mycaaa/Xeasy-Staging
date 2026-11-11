<?php

namespace App\Http\Controllers;

use App\Models\ExpenseClaim;
use App\Models\HRMEmployeeGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ReportingManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {
        try {
            $reportingManagerId = session('employee_id');
            $yearId = session('year_id');
            $companyId = session('company_id');

            $reportingStats = $this->getReportingStats($reportingManagerId, $yearId, $companyId);
            $pendingApprovals = $this->getPendingApprovals($reportingManagerId, $yearId, $companyId);
            $teamClaimsSummary = $this->getTeamClaimsSummary($reportingManagerId, $yearId, $companyId);
            
            $claimsStatusData = $this->getClaimsStatusData($reportingManagerId, $yearId, $companyId);
            $expenseOverviewData = $this->getExpenseOverviewData($reportingManagerId, $yearId, $companyId);
            $expenseTrendData = $this->getExpenseTrendData($reportingManagerId, $yearId, $companyId);
            $slaPerformance = $this->getSLAPerformance($reportingManagerId, $yearId, $companyId);
            $complianceAlerts = $this->getComplianceAlerts($reportingManagerId, $yearId, $companyId);
            $topInsights = $this->getTopInsights($reportingManagerId, $yearId, $companyId);

            return view('employee.reporting-manager-dashboard', compact(
                'reportingStats',
                'pendingApprovals',
                'teamClaimsSummary',
                'claimsStatusData',
                'expenseOverviewData',
                'expenseTrendData',
                'slaPerformance',
                'complianceAlerts',
                'topInsights'
            ));
        } catch (\Exception $e) {
            Log::error('Error in reporting manager dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return view('employee.reporting-manager-dashboard', [
                'reportingStats' => (object)[
                    'pending_approval' => 0,
                    'approved' => 0,
                    'returned' => 0,
                    'team_expenses' => 0,
                ],
                'pendingApprovals' => [],
                'teamClaimsSummary' => [],
                'claimsStatusData' => ['series' => [], 'labels' => []],
                'expenseOverviewData' => ['series' => [], 'labels' => []],
                'expenseTrendData' => ['data' => [], 'months' => []],
                'slaPerformance' => ['on_time' => 0, 'delayed' => 0],
                'complianceAlerts' => [],
                'topInsights' => [],
            ]);
        }
    }

    public function approvals(Request $request)
    {
        $status = $request->get('status', 'pending');
        return view('employee.reporting-manager-approvals', compact('status'));
    }

    public function team(Request $request)
    {
        $reportingManagerId = session('employee_id');
        $yearId = session('year_id');
        $companyId = session('company_id');

        $teamMembers = $this->getTeamMembers($reportingManagerId, $yearId, $companyId);
        return view('employee.reporting-manager-team', compact('teamMembers'));
    }

    /*
     * UAT-03 (6 Nov): this controller was written against columns that do not exist
     * (ec.ReportingManager, ec.ClaimAmount, emp.FirstName in the expense DB, status 'Pending Approval'),
     * so every screen failed. It now uses the real schema: the team comes from HRIMS
     * (hrm_employee_general.RepEmployeeID), claims from the session company's year table,
     * amounts from FilledTAmt, and the real statuses. Pending approval = waiting for the RM:
     * VNR after verification (Verified), VVNR before payment (Filled/Submitted).
     */
    private function teamIds($rmId): array
    {
        return DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee_general as g')
            ->join('hrm_employee as e', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->where('g.RepEmployeeID', (int) $rmId)->where('e.EmpStatus', 'A')
            ->pluck('g.EmployeeID')->map(fn ($v) => (int) $v)->all();
    }

    private function names(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee')->whereIn('EmployeeID', $ids)
            ->get(['EmployeeID', 'Fname', 'Sname', 'Lname', 'EmpCode'])
            ->mapWithKeys(fn ($e) => [(int) $e->EmployeeID => trim(preg_replace('/\s+/', ' ', "{$e->Fname} {$e->Sname} {$e->Lname}"))])->all();
    }

    private function pendingStatuses(): array
    {
        return (int) session('company_id') === 11 ? ['Filled', 'Submitted'] : ['Verified'];
    }

    private function claims($rmId, $yearId)
    {
        $ids = $this->teamIds($rmId);

        return DB::connection(session('expense_connection', 'expense'))
            ->table(ExpenseClaim::tableName((int) $yearId).' as ec')
            ->whereIn('ec.CrBy', $ids ?: [0])
            ->where('ec.ClaimStatus', '!=', 'Deactivate');
    }

    public function getApprovalInbox(Request $request)
    {
        $rows = $this->claims(session('employee_id'), session('year_id'))
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')
            ->whereIn('ec.ClaimStatus', $this->pendingStatuses())
            ->orderByDesc('ec.CrDate')
            ->get(['ec.ExpId', 'ec.CrBy', 'ct.ClaimName', 'ec.FilledTAmt', 'ec.ClaimStatus', 'ec.CrDate']);
        $names = $this->names($rows->pluck('CrBy')->map(fn ($v) => (int) $v)->unique()->all());

        return DataTables::of($rows)
            ->addColumn('employee_name', fn ($r) => e($names[(int) $r->CrBy] ?? ''))
            ->addColumn('claim_id', fn ($r) => $r->ExpId ?? '-')
            ->addColumn('amount', fn ($r) => (float) ($r->FilledTAmt ?? 0))
            ->make(true);
    }

    public function getTeamClaimsSummary($reportingManagerId = null, $yearId = null, $companyId = null)
    {
        // also served as GET api/team-claims (no route parameters) - take them from the session then
        if ($reportingManagerId instanceof Request || $reportingManagerId === null) {
            $reportingManagerId = session('employee_id');
            $yearId = session('year_id');
        }
        $pending = $this->pendingStatuses();
        $rows = $this->claims($reportingManagerId, $yearId)
            ->groupBy('ec.CrBy')
            ->selectRaw('ec.CrBy, COUNT(*) as total_claims, SUM(ec.ClaimStatus IN ('.implode(',', array_fill(0, count($pending), '?')).')) as pending, '
                ."SUM(ec.ClaimStatus IN ('Approved','Financed')) as approved, SUM(ec.FilledTAmt) as total_amount", $pending)
            ->get();
        $names = $this->names($rows->pluck('CrBy')->map(fn ($v) => (int) $v)->all());
        $rows->each(fn ($r) => $r->employee_name = $names[(int) $r->CrBy] ?? '');

        return request()->expectsJson() || request()->is('*/api/*') ? response()->json($rows) : $rows;
    }

    private function getReportingStats($reportingManagerId, $yearId, $companyId)
    {
        $pending = $this->pendingStatuses();
        $stats = $this->claims($reportingManagerId, $yearId)
            ->selectRaw('SUM(ClaimStatus IN ('.implode(',', array_fill(0, count($pending), '?')).')) as pending_approval, '
                ."SUM(ClaimStatus IN ('Approved','Financed')) as approved, SUM(ClaimStatus = 'Return') as returned, SUM(FilledTAmt) as team_expenses", $pending)
            ->first();

        return $stats ?? (object) ['pending_approval' => 0, 'approved' => 0, 'returned' => 0, 'team_expenses' => 0];
    }

    private function getPendingApprovals($reportingManagerId, $yearId, $companyId)
    {
        $rows = $this->claims($reportingManagerId, $yearId)
            ->whereIn('ec.ClaimStatus', $this->pendingStatuses())
            ->orderByDesc('ec.CrDate')->limit(10)
            ->get(['ec.ExpId as claim_id', 'ec.CrBy', 'ec.FilledTAmt as amount', 'ec.ClaimStatus as status']);
        $names = $this->names($rows->pluck('CrBy')->map(fn ($v) => (int) $v)->all());
        $rows->each(fn ($r) => $r->employee_name = $names[(int) $r->CrBy] ?? '');

        return $rows;
    }

    private function getClaimsStatusData($reportingManagerId, $yearId, $companyId)
    {
        $data = $this->claims($reportingManagerId, $yearId)->groupBy('ec.ClaimStatus')->selectRaw('ec.ClaimStatus, COUNT(*) as count')->get();

        return ['series' => $data->pluck('count')->map(fn ($v) => (int) $v)->all(), 'labels' => $data->pluck('ClaimStatus')->all()];
    }

    private function getExpenseOverviewData($reportingManagerId, $yearId, $companyId)
    {
        $data = $this->claims($reportingManagerId, $yearId)->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')
            ->groupBy('ct.ClaimName')->selectRaw('ct.ClaimName, SUM(ec.FilledTAmt) as total')->orderByDesc('total')->limit(5)->get();

        return ['series' => $data->pluck('total')->map(fn ($v) => (float) $v)->all(), 'labels' => $data->pluck('ClaimName')->map(fn ($v) => $v ?? 'Others')->all()];
    }

    private function getExpenseTrendData($reportingManagerId, $yearId, $companyId)
    {
        $data = $this->claims($reportingManagerId, $yearId)
            ->groupBy('ec.ClaimMonth')->selectRaw('ec.ClaimMonth as month, SUM(ec.FilledTAmt) as total')->orderBy('ec.ClaimMonth')->get();
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return ['data' => $data->pluck('total')->map(fn ($v) => (float) $v)->all(), 'months' => $data->pluck('month')->map(fn ($m) => $monthNames[(int) $m] ?? '')->all()];
    }

    private function getSLAPerformance($reportingManagerId, $yearId, $companyId)
    {
        $row = $this->claims($reportingManagerId, $yearId)->whereIn('ec.ClaimStatus', ['Approved', 'Financed'])->whereNotNull('ec.ApprDate')
            ->selectRaw('COUNT(*) as total, SUM(DATEDIFF(ec.ApprDate, ec.CrDate) <= 7) as on_time')->first();
        $total = (int) ($row->total ?? 0);
        $onTime = $total > 0 ? (int) round(((int) $row->on_time / $total) * 100) : 0;

        return ['on_time' => $onTime, 'delayed' => $total > 0 ? 100 - $onTime : 0];
    }

    private function getComplianceAlerts($reportingManagerId, $yearId, $companyId)
    {
        $pending = $this->pendingStatuses();
        $base = fn () => $this->claims($reportingManagerId, $yearId);
        $alerts = [];
        $overdue = $base()->whereIn('ec.ClaimStatus', $pending)->whereRaw('DATEDIFF(CURDATE(), ec.CrDate) > 7')->count();
        if ($overdue) {
            $alerts[] = (object) ['severity' => 'high', 'title' => 'Overdue Approvals', 'message' => "$overdue claims pending approval for more than 7 days"];
        }
        $high = $base()->whereIn('ec.ClaimStatus', $pending)->where('ec.FilledTAmt', '>', 50000)->count();
        if ($high) {
            $alerts[] = (object) ['severity' => 'medium', 'title' => 'High Value Claims', 'message' => "$high high-value claims awaiting approval"];
        }
        $returned = $base()->where('ec.ClaimStatus', 'Return')->count();
        if ($returned) {
            $alerts[] = (object) ['severity' => 'medium', 'title' => 'Returned Claims', 'message' => "$returned claims have been returned for corrections"];
        }

        return $alerts;
    }

    private function getTopInsights($reportingManagerId, $yearId, $companyId)
    {
        $insights = [];
        $top = $this->claims($reportingManagerId, $yearId)->groupBy('ec.CrBy')->selectRaw('ec.CrBy, SUM(ec.FilledTAmt) as total')->orderByDesc('total')->first();
        if ($top) {
            $name = $this->names([(int) $top->CrBy])[(int) $top->CrBy] ?? 'An employee';
            $insights[] = (object) ['message' => e($name).' is your top spender with ₹'.number_format((float) $top->total, 0)];
        }
        $rate = $this->claims($reportingManagerId, $yearId)->selectRaw("COUNT(*) as total, SUM(ec.ClaimStatus IN ('Approved','Financed')) as approved")->first();
        if ($rate && $rate->total > 0) {
            $insights[] = (object) ['message' => 'Your approval rate is '.round(($rate->approved / $rate->total) * 100).'% across '.$rate->total.' claims'];
        }

        return $insights;
    }

    private function getTeamMembers($reportingManagerId, $yearId, $companyId)
    {
        return DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee_general as g')
            ->join('hrm_employee as e', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_designation as d', 'd.id', '=', 'g.DesigId')
            ->where('g.RepEmployeeID', (int) $reportingManagerId)->where('e.EmpStatus', 'A')
            ->selectRaw("e.EmployeeID, e.Fname as FirstName, TRIM(CONCAT(COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) as LastName, COALESCE(d.designation_name, g.Designation) as Designation, g.DepartmentId")
            ->orderBy('e.Fname')
            ->get();
    }
}
