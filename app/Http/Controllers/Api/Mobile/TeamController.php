<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends BaseMobileController
{
    /**
     * Get team members under the approver.
     * Replicates: common.php?value=TeamMember
     */
    public function teamMembers(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();

        $members = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->where('e.CompanyId', $comId)
            ->select([
                'e.EmployeeID', 'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as EmpName"),
                'eg.DepartmentId', 'eg.DesigId', 'eg.GradeId',
            ])
            ->orderBy('e.Fname')
            ->get();

        if ($members->isEmpty()) {
            return $this->error('No team members found.');
        }

        return $this->success(['team_members' => $members->toArray()]);
    }

    /**
     * Get team members (for appraiser - includes more detail).
     * Replicates: common.php?value=TeamMemberAppraiser
     */
    public function teamMembersAppraiser(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get team members with expense summary
        $members = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->where('e.CompanyId', $comId)
            ->select([
                'e.EmployeeID', 'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as EmpName"),
                'eg.DepartmentId', 'eg.DesigId', 'eg.GradeId',
                'eg.ReportingName',
            ])
            ->orderBy('e.Fname')
            ->get();

        // Attach expense month summary for each member
        $membersWithSummary = $members->map(function ($member) use ($expenseConn, $yearId) {
            $monthSummary = DB::connection($expenseConn)
                ->table($this->monthFinalTable($yearId))
                ->where('EmployeeID', $member->EmployeeID)
                ->where('YearId', $yearId)
                ->select('Month', 'Status', 'SubmitDate')
                ->get();

            $member->month_summary = $monthSummary->toArray();
            return $member;
        });

        return $this->success(['team_members' => $membersWithSummary->toArray()]);
    }

    /**
     * Get all team expenses summary.
     * Replicates: common.php?value=AllTeamExp
     */
    public function allTeamExpenses(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get team member IDs
        $teamMemberIds = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->where('e.CompanyId', $comId)
            ->pluck('eg.EmployeeID')
            ->toArray();

        if (empty($teamMemberIds)) {
            return $this->success(['team_expenses' => []]);
        }

        // Get expense summary by claim type for all team members
        $expenses = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId) . ' as ec')
            ->leftJoin('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->whereIn('ec.CrBy', $teamMemberIds)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->where('ec.ClaimStatus', '!=', 'Draft')
            ->where('ec.ClaimYearId', $yearId)
            ->selectRaw('ec.ClaimId, ct.ClaimName, SUM(ec.FilledTAmt) as TotalAmt, COUNT(ec.ExpId) as TotalClaims')
            ->groupBy('ec.ClaimId', 'ct.ClaimName')
            ->get();

        return $this->success(['team_expenses' => $expenses->toArray()]);
    }

    /**
     * Get team running kilometers.
     * Replicates: common.php?value=TeamRunningKilometers
     */
    public function teamRunningKm(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return response()->json(['success' => false, 'data' => [], 'error' => 'Parameter Missing!']);
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        try {
            // Get team employee IDs (same as legacy fetchTeamEmployeeIds)
            $teamMemberIds = DB::connection($hrimsConn)
                ->table('hrm_employee_general as eg')
                ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->where('e.EmpStatus', 'A')
                ->where('e.CompanyId', $comId)
                ->where(function ($q) use ($empId) {
                    $q->where('eg.RepEmployeeID', $empId)
                      ->orWhere('eg.EmployeeID', $empId);
                })
                ->pluck('eg.EmployeeID')
                ->toArray();

            if (empty($teamMemberIds)) {
                return response()->json(['success' => true, 'data' => ['TotalKm' => ['employees' => []]], 'error' => '']);
            }

            $employeeIdsStr = implode(',', array_map('intval', $teamMemberIds));

            // Query the runningkm table (same as legacy)
            $kmRows = DB::connection($expenseConn)->select("
                SELECT empid,
                    (IFNULL(apr_2w_km, 0) + IFNULL(apr_4w_km, 0) + 
                     IFNULL(may_2w_km, 0) + IFNULL(may_4w_km, 0) +
                     IFNULL(jun_2w_km, 0) + IFNULL(jun_4w_km, 0) +
                     IFNULL(jul_2w_km, 0) + IFNULL(jul_4w_km, 0) +
                     IFNULL(aug_2w_km, 0) + IFNULL(aug_4w_km, 0) +
                     IFNULL(sep_2w_km, 0) + IFNULL(sep_4w_km, 0) +
                     IFNULL(oct_2w_km, 0) + IFNULL(oct_4w_km, 0) +
                     IFNULL(nov_2w_km, 0) + IFNULL(nov_4w_km, 0) +
                     IFNULL(dec_2w_km, 0) + IFNULL(dec_4w_km, 0) +
                     IFNULL(jan_2w_km, 0) + IFNULL(jan_4w_km, 0) +
                     IFNULL(feb_2w_km, 0) + IFNULL(feb_4w_km, 0) +
                     IFNULL(mar_2w_km, 0) + IFNULL(mar_4w_km, 0)) AS TotalKm,
                    apr_2w_km, apr_4w_km, may_2w_km, may_4w_km,
                    jun_2w_km, jun_4w_km, jul_2w_km, jul_4w_km,
                    aug_2w_km, aug_4w_km, sep_2w_km, sep_4w_km,
                    oct_2w_km, oct_4w_km, nov_2w_km, nov_4w_km,
                    dec_2w_km, dec_4w_km, jan_2w_km, jan_4w_km,
                    feb_2w_km, feb_4w_km, mar_2w_km, mar_4w_km
                FROM runningkm
                WHERE yearid = ? AND empid IN ({$employeeIdsStr})
            ", [$yearId]);

            // Build employee KM data
            $employeesKm = [];
            foreach ($kmRows as $row) {
                $employeesKm[(string)$row->empid] = [
                    'TotalKm' => (string)($row->TotalKm ?? '0'),
                    'MonthlyKm' => [
                        'Apr' => ['2W' => (string)($row->apr_2w_km ?? '0'), '4W' => (string)($row->apr_4w_km ?? '0')],
                        'May' => ['2W' => (string)($row->may_2w_km ?? '0'), '4W' => (string)($row->may_4w_km ?? '0')],
                        'Jun' => ['2W' => (string)($row->jun_2w_km ?? '0'), '4W' => (string)($row->jun_4w_km ?? '0')],
                        'Jul' => ['2W' => (string)($row->jul_2w_km ?? '0'), '4W' => (string)($row->jul_4w_km ?? '0')],
                        'Aug' => ['2W' => (string)($row->aug_2w_km ?? '0'), '4W' => (string)($row->aug_4w_km ?? '0')],
                        'Sep' => ['2W' => (string)($row->sep_2w_km ?? '0'), '4W' => (string)($row->sep_4w_km ?? '0')],
                        'Oct' => ['2W' => (string)($row->oct_2w_km ?? '0'), '4W' => (string)($row->oct_4w_km ?? '0')],
                        'Nov' => ['2W' => (string)($row->nov_2w_km ?? '0'), '4W' => (string)($row->nov_4w_km ?? '0')],
                        'Dec' => ['2W' => (string)($row->dec_2w_km ?? '0'), '4W' => (string)($row->dec_4w_km ?? '0')],
                        'Jan' => ['2W' => (string)($row->jan_2w_km ?? '0'), '4W' => (string)($row->jan_4w_km ?? '0')],
                        'Feb' => ['2W' => (string)($row->feb_2w_km ?? '0'), '4W' => (string)($row->feb_4w_km ?? '0')],
                        'Mar' => ['2W' => (string)($row->mar_2w_km ?? '0'), '4W' => (string)($row->mar_4w_km ?? '0')],
                    ],
                ];
            }

            // Get employee names and merge
            $nameRows = DB::connection($hrimsConn)->select("
                SELECT EmployeeID, EmpCode, CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', Lname) AS FullName
                FROM hrm_employee
                WHERE EmployeeID IN ({$employeeIdsStr})
            ");

            $employeeData = [];
            foreach ($nameRows as $nameRow) {
                $eid = (string)$nameRow->EmployeeID;
                if (isset($employeesKm[$eid])) {
                    $employeeData[] = [
                        'EmployeeID' => (string)$nameRow->EmpCode,
                        'FullName' => (string)$nameRow->FullName,
                        'TotalKm' => $employeesKm[$eid]['TotalKm'],
                        'MonthlyKm' => $employeesKm[$eid]['MonthlyKm'],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['TotalKm' => ['employees' => $employeeData]],
                'error' => '',
            ]);
        } catch (\Exception $e) {
            \Log::error('teamRunningKm error: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get expenses for a specific team member.
     * Replicates: common.php?value=TeamMemberExpenses
     */
    public function teamMemberExpenses(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $memEmpId = (int) $request->input('memempid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $memEmpId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $expenses = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId) . ' as ec')
            ->leftJoin('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->where('ec.CrBy', $memEmpId)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->where('ec.ClaimStatus', '!=', 'Draft')
            ->where('ec.ClaimYearId', $yearId)
            ->selectRaw('ec.ClaimId, ct.ClaimName, SUM(ec.FilledTAmt) as TotalAmt, COUNT(ec.ExpId) as TotalClaims, ec.ClaimMonth')
            ->groupBy('ec.ClaimId', 'ct.ClaimName', 'ec.ClaimMonth')
            ->orderBy('ec.ClaimMonth')
            ->get();

        return $this->success(['member_expenses' => $expenses->toArray()]);
    }

    /**
     * Get expenses for a team member by expense claim ID.
     * Replicates: common.php?value=TeamMemberExpensesById
     */
    public function teamMemberExpensesById(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $claimId = (int) $request->input('expcid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $claimId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get team member IDs
        $teamMemberIds = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->pluck('eg.EmployeeID')
            ->toArray();

        $expenses = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId) . ' as ec')
            ->join(DB::connection($hrimsConn)->getDatabaseName() . '.hrm_employee as e', 'ec.CrBy', '=', 'e.EmployeeID')
            ->whereIn('ec.CrBy', $teamMemberIds)
            ->where('ec.ClaimId', $claimId)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->where('ec.ClaimStatus', '!=', 'Draft')
            ->where('ec.ClaimYearId', $yearId)
            ->select([
                'ec.ExpId', 'ec.BillDate', 'ec.FilledTAmt as TotAmt', 'ec.ClaimMonth', 'ec.ClaimStatus',
                'ec.CrBy',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as EmpName"),
                'e.EmpCode',
            ])
            ->orderByDesc('ec.BillDate')
            ->get();

        return $this->success(['expenses' => $expenses->toArray()]);
    }

    /**
     * Get expenses by ID and specific member.
     * Replicates: common.php?value=TeamExpensesByIdAndMember
     */
    public function teamExpensesByIdAndMember(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $memEmpId = (int) $request->input('memempid');
        $claimId = (int) $request->input('expcid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $memEmpId <= 0 || $claimId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $expenses = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId) . ' as ec')
            ->leftJoin('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->where('ec.CrBy', $memEmpId)
            ->where('ec.ClaimId', $claimId)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->where('ec.ClaimStatus', '!=', 'Draft')
            ->where('ec.ClaimYearId', $yearId)
            ->select([
                'ec.ExpId', 'ec.ClaimId', 'ct.ClaimName', 'ec.BillDate',
                DB::raw('NULL as BillNo'), 'ec.FilledTAmt as TotAmt', 'ec.VerifyTAmt as VerifiedAmt', 'ec.ClaimMonth',
                'ec.Remark', 'ec.ClaimStatus',
            ])
            ->orderByDesc('ec.BillDate')
            ->get();

        return $this->success(['expenses' => $expenses->toArray()]);
    }

    /**
     * Get last odometer reading for team members.
     * Replicates: common.php?value=team_last_odo
     */
    public function teamLastOdo(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);

        if ($empId <= 0 || $yearId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($this->getComId($request));

        $teamMemberIds = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->pluck('eg.EmployeeID')
            ->toArray();

        if (empty($teamMemberIds)) {
            return $this->success(['team_last_odo' => []]);
        }

        $lastOdo = [];
        foreach ($teamMemberIds as $memberId) {
            $odo = DB::connection($expenseConn)
                ->table($this->claimsTable($yearId))
                ->where('CrBy', $memberId)
                ->where('ClaimId', 7)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('odomtr_closing', '>', 0)
                ->orderByDesc('BillDate')
                ->orderByDesc('ExpId')
                ->select('odomtr_closing', 'BillDate', 'CrBy')
                ->first();

            if ($odo) {
                $lastOdo[] = $odo;
            }
        }

        return $this->success(['team_last_odo' => $lastOdo]);
    }

    /*
     * UAT-03 (mobile team lists): these filtered claims on ClaimStatus 'Submitted' (which means uploaded
     * but not yet filled) and 'Close' (never used), and found the team through RepEmployeeID. They now
     * mirror legacy api/common.php and expense_functions.php getExpenseData(): the team comes from
     * hrm_employee_general.RepEmployeeID (the new HRIMS has no hrm_employee_reporting), pending and approved are month rows in y*_monthexpensefinal (Closed, and
     * Approved_Date empty or set), and the day lists are FilledOkay = 1 claims at step >= 3 (pending) or
     * >= 5 (approved). Legacy response keys are returned alongside the existing lower-case ones.
     */

    private function zeroDate($q, string $col)
    {
        return $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, '0000-00-00'));
    }

    private function setDate($q, string $col)
    {
        return $q->whereNotNull($col)->where($col, '!=', '0000-00-00');
    }

    /**
     * Legacy Team_* lists read hrm_employee_reporting.AppraiserId. That table is not in the new HRIMS;
     * its appraiser is hrm_employee_general.RepEmployeeID, so that is used (no status filter, as legacy).
     */
    private function appraiseeIds(int $empId): array
    {
        return DB::connection($this->getHrimsConnection())->table('hrm_employee_general')
            ->where('RepEmployeeID', $empId)->pluck('EmployeeID')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Legacy getExpenseData(): appraiser, reviewer or HOD, active, same company. The new HRIMS keeps only
     * the reporting manager (RepEmployeeID); reviewer and HOD links do not exist there.
     */
    private function reportingTeamIds(int $empId, int $comId): array
    {
        return DB::connection($this->getHrimsConnection())->table('hrm_employee_general as g')
            ->join('hrm_employee as e', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->where('e.EmpStatus', 'A')->where('e.CompanyId', $comId)
            ->where('g.RepEmployeeID', $empId)
            ->pluck('g.EmployeeID')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    private function empNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return DB::connection($this->getHrimsConnection())->table('hrm_employee')
            ->whereIn('EmployeeID', array_unique($ids))
            ->select('EmployeeID', DB::raw("CONCAT_WS(' ', Fname, Sname, Lname) as Ename"))
            ->pluck('Ename', 'EmployeeID')->all();
    }

    private function teamClaimColumns(): array
    {
        return [
            'e.ExpId', 'e.ClaimId', 'e.ClaimYearId', 'e.CrBy', 'e.CrBy as EmployeeID', 'e.CrDate', 'e.BillDate',
            'e.DateEntryRemark as EmpRemark', 'e.FilledTAmt', 'e.VerifyTAmt', 'e.TotKm', 'e.VerifyBy', 'e.VerifyDate',
            'e.VerifyTRemark', 'e.ApprTAmt', 'e.ApprBy', 'e.ApprDate', 'e.ApprTRemark', 'e.FinancedTAmt', 'e.FinancedBy',
            'e.FinancedDate', 'e.FinancedTRemark', 'e.ClaimStatus', 'e.Remark', 'e.DateEntryRemark', 'e.FilledOkay',
            'e.ClaimAtStep', 'ct.ClaimName', 'e.ClaimMonth', 'e.opening_filepath', 'e.closing_filepath',
            'e.Img_1', 'e.Img_2', 'e.Img_3', 'e.Img_4 as pdf_1', 'e.Img_5 as pdf_2',
        ];
    }

    /** FilledOkay = 1 claims of one member and month at or beyond a claim step. */
    private function memberMonthClaims(string $conn, int $yearId, int $memberId, int $month, int $minStep): array
    {
        return DB::connection($conn)->table("y{$yearId}_expenseclaims as e")
            ->leftJoin('claimtype as ct', 'e.ClaimId', '=', 'ct.ClaimId')
            ->where('e.ClaimMonth', $month)->where('e.ClaimYearId', $yearId)->where('e.CrBy', $memberId)
            ->where('e.FilledOkay', 1)
            ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Draft'])
            ->where('e.ClaimAtStep', '>=', $minStep)
            ->orderBy('e.BillDate')
            ->select($this->teamClaimColumns())
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    private function teamListContext(Request $request, bool $needMonth = false)
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('Month', $request->input('month'));

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || ($needMonth && $month <= 0)) {
            return $this->error('Parameter Missing!');
        }
        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        return [$empId, $yearId, $comId, $this->getExpenseConnection($comId), $month];
    }

    /**
     * Team months closed by the employee and not yet approved.
     * Replicates: common.php?value=Team_Pending_Claimlist
     */
    public function teamPendingClaimList(Request $request): JsonResponse
    {
        $ctx = $this->teamListContext($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, $comId, $conn] = $ctx;

        $team = $this->appraiseeIds($empId);
        if (empty($team)) {
            return $this->error('Data Not Available!');
        }
        $db = DB::connection($conn);
        $q = $db->table("y{$yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $team)->where('YearId', $yearId)
            ->where('Status', 'Closed')->where('Total_Claim', '>', 0);
        if (\App\Support\ApproverAfterVerifier::applies((int) $comId)) {
            $q->whereRaw(\App\Support\ApproverAfterVerifier::verifiedSql()); // APPR-VER-01
        }
        $rows = $this->zeroDate($q, 'Approved_Date')->orderBy('Month')->orderBy('EmployeeID')->get();
        if ($rows->isEmpty()) {
            return $this->error('Data Not Available!');
        }

        $names = $this->empNames($rows->pluck('EmployeeID')->all());
        $list = $rows->map(function ($r) use ($names, $db, $yearId) {
            $a = (array) $r;
            $a['EmpName'] = ['Ename' => $names[$r->EmployeeID] ?? null];
            $a['TotalApprovedAmt'] = $db->table("y{$yearId}_expenseclaims")
                ->where('ClaimMonth', $r->Month)->where('ClaimYearId', $yearId)->where('CrBy', $r->EmployeeID)
                ->where('FilledOkay', 1)->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
                ->selectRaw("SUM(CASE WHEN ApprBy > 0 AND ApprDate IS NOT NULL AND ApprDate != '' THEN ApprTAmt ELSE 0 END) AS t")
                ->value('t');
            return $a;
        })->all();

        return $this->success(['Pending_list' => $list, 'pending_claims' => $list], 'Data Available!');
    }

    /**
     * Claims of one member and month waiting for approval.
     * Replicates: common.php?value=Team_Pending_Claimlist_Day (legacy reads CrBy = empid; memid, when sent, selects the member)
     */
    public function teamPendingClaimListDay(Request $request): JsonResponse
    {
        $ctx = $this->teamListContext($request, true);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, $comId, $conn, $month] = $ctx;
        $memberId = (int) ($request->input('memid') ?: $request->input('memempid') ?: $empId);
        if ($memberId !== $empId && !in_array($memberId, $this->appraiseeIds($empId), true)) {
            return $this->error('Invalid Employee!');
        }
        // APPR-VER-01: the reporting manager's day view opens after verification (VNR process)
        if ($memberId !== $empId && \App\Support\ApproverAfterVerifier::applies((int) $comId)
            && ! \App\Support\ApproverAfterVerifier::monthVerified($conn, $yearId, $memberId, $month)) {
            return $this->error(\App\Support\ApproverAfterVerifier::MESSAGE);
        }

        $rows = $this->memberMonthClaims($conn, $yearId, $memberId, $month, 3);

        return $this->success(['DayWiseDetails' => $rows, 'pending_claims' => $rows], 'Data Available!');
    }

    /**
     * Team months that are verified and approved.
     * Replicates: common.php?value=Team_Approved_Claimlist
     */
    public function teamApprovedClaimList(Request $request): JsonResponse
    {
        $ctx = $this->teamListContext($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn] = $ctx;

        $team = $this->appraiseeIds($empId);
        if (empty($team)) {
            return $this->error('Data Not Available!');
        }
        $q = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $team)->where('YearId', $yearId)
            ->where('Status', 'Closed')->where('Total_Claim', '>', 0)
            ->where('Verified_Amount', '>=', 0)->where('Approved_Amount', '>=', 0);
        $this->setDate($q, 'Verified_Date');
        $this->setDate($q, 'Approved_Date');
        $rows = $q->orderByDesc('DateOfSubmit')->orderByDesc('Month')->orderBy('EmployeeID')->get();
        if ($rows->isEmpty()) {
            return $this->error('Data Not Available!');
        }

        $names = $this->empNames($rows->pluck('EmployeeID')->all());
        $list = $rows->map(function ($r) use ($names) {
            $a = (array) $r;
            $a['EmpName'] = ['Ename' => $names[$r->EmployeeID] ?? null];
            return $a;
        })->all();

        return $this->success(['Approved_list' => $list, 'approved_claims' => $list], 'Data Available!');
    }

    /**
     * Approved claims of one member and month.
     * Replicates: common.php?value=Team_Approved_Claimlist_Day
     */
    public function teamApprovedClaimListDay(Request $request): JsonResponse
    {
        $ctx = $this->teamListContext($request, true);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn, $month] = $ctx;
        $memberId = (int) ($request->input('memid') ?: $request->input('memempid') ?: $empId);
        if ($memberId !== $empId && !in_array($memberId, $this->appraiseeIds($empId), true)) {
            return $this->error('Invalid Employee!');
        }

        $rows = $this->memberMonthClaims($conn, $yearId, $memberId, $month, 5);

        return $this->success(['DayWiseDetails' => $rows, 'approved_claims' => $rows], 'Data Available!');
    }

    /** Legacy getExpenseData(): approved months of the reporting team, optionally one month and/or one member. */
    private function approvedMonths(Request $request, bool $needMonth, bool $needMember): JsonResponse
    {
        $ctx = $this->teamListContext($request, $needMonth);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, $comId, $conn, $month] = $ctx;
        $memberId = (int) $request->input('memid');
        if ($needMember && $memberId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $team = $this->reportingTeamIds($empId, $comId);
        if (empty($team)) {
            return response()->json(['Code' => '100', 'success' => false, 'msg' => 'No employees found for the provided AppraiserId.', 'error' => 'No employees found for the provided AppraiserId.']);
        }
        if ($needMember && !in_array($memberId, $team, true)) {
            return $this->error('Invalid Employee!');
        }

        $q = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->whereIn('EmployeeID', $team)->where('YearId', $yearId)
            ->where('Status', 'Closed')->where('Total_Claim', '>', 0)
            ->where('Verified_Amount', '>=', 0)->where('Approved_Amount', '>=', 0);
        $this->setDate($q, 'Verified_Date');
        $this->setDate($q, 'Approved_Date');
        if ($needMonth) {
            $q->where('Month', $month);
        }
        if ($needMember) {
            $q->where('EmployeeID', $memberId);
        }
        $rows = $q->orderByDesc('Month')->orderBy('EmployeeID')->get();

        $names = $this->empNames($rows->pluck('EmployeeID')->all());
        $list = $rows->map(function ($r) use ($names, $conn, $yearId) {
            $a = (array) $r;
            $a['EmpName'] = $names[$r->EmployeeID] ?? 'Unknown';
            $a['DayWiseDetails'] = $this->memberMonthClaims($conn, $yearId, (int) $r->EmployeeID, (int) $r->Month, 5);
            return $a;
        })->all();

        return $this->success(['success' => true, 'Approved_list' => $list, 'approved_claims' => $list, 'error' => ''], 'Data Available!');
    }

    /**
     * Replicates: common.php?value=ApprovedClaimsByMonth
     */
    public function approvedClaimsByMonth(Request $request): JsonResponse
    {
        return $this->approvedMonths($request, true, false);
    }

    /**
     * Replicates: common.php?value=ApprovedClaimsByMem
     */
    public function approvedClaimsByMember(Request $request): JsonResponse
    {
        return $this->approvedMonths($request, false, true);
    }

    /**
     * Replicates: common.php?value=ApprovedClaimsByMonthMem
     */
    public function approvedClaimsByMonthMember(Request $request): JsonResponse
    {
        return $this->approvedMonths($request, true, true);
    }

    /**
     * Update all expense claims to Okay status.
     * Replicates: common.php?value=UpdateExpenseClaimsOkayAll
     */
    public function updateExpenseClaimsOkayAll(Request $request): JsonResponse
    {
        $emp = (int) $request->input('emp');
        $month = (int) $request->input('month');
        $cgid = $request->input('cgid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($emp <= 0 || $month <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $updated = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $emp)
            ->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->update(['Okay' => 'Y']);

        return $this->success(['updated' => $updated], 'All claims marked as okay');
    }

    /**
     * Agree to verified amounts for a month.
     * Replicates: common.php?value=Agree
     */
    public function agree(Request $request): JsonResponse
    {
        $comId = $this->getComId($request);
        $yearId = $this->getYearId($request);
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('month');

        if ($comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Update monthexpensefinal to agree
        DB::connection($expenseConn)
            ->table($this->monthFinalTable($yearId))
            ->where('EmployeeID', $empId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->update([
                'AgreeStatus' => 'Agree',
                'AgreeDate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->success([], 'Agreed successfully');
    }

    /**
     * Not agree to verified amounts for a month.
     * Replicates: common.php?value=NotAgree
     */
    public function notAgree(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('month');
        $remark = $request->input('remark', '');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $month <= 0 || empty($remark) || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        DB::connection($expenseConn)
            ->table($this->monthFinalTable($yearId))
            ->where('EmployeeID', $empId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->update([
                'AgreeStatus' => 'NotAgree',
                'AgreeRemark' => $remark,
                'AgreeDate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->success([], 'Not Agree submitted');
    }
}
