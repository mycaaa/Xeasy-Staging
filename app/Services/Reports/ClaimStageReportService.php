<?php

namespace App\Services\Reports;

use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use App\Models\MonthExpenseFinal;
use Illuminate\Support\Facades\DB;

class ClaimStageReportService
{
    protected string $expenseConn;

    protected string $hrimsConn;

    protected int $yearId;

    protected int $companyId;

    public function __construct()
    {
        $this->expenseConn = session('expense_connection', DatabaseSwitcher::getExpenseConnection());
        $this->hrimsConn = session('hrims_connection', 'hrims');
        $this->yearId = \App\Support\CurrentYear::id();
        $this->companyId = (int) session('company_id', 1);
    }

    protected function applyOrganogramFilters($query, array $filters, string $egAlias = 'eg', string $empIdExpr = 'eg.EmployeeID'): void
    {
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        OrganogramFilterHelper::applyToQuery($query, $filters, $egAlias, $empIdExpr, $hrimsDb);
    }

    /**
     * Get monthly claim stage summary data
     * VNR workflow: Claim → Submit → Courier → Verify → Approve → Payment
     * VVNR workflow: Claim → Submit → Approve → Payment → Courier → Verify
     */
    /**
     * STAGE-01: employee-months that have claims but no y*_monthexpensefinal row.
     * The stage reports joined claims onto the month table, so these claims were left out
     * (FY2026-27 test copy: 67 claims, 34 employee-months). They are returned as "Open"
     * months (claims uploaded, month not yet submitted) so every claim is counted once.
     *
     * $applyFilters(Builder $q) applies the report's organogram filters (aliases e / eg).
     */
    public static function claimMonthsWithoutMonthRow(string $expenseConn, string $hrimsDb, int $yearId, array $months, ?callable $applyFilters = null): \Illuminate\Support\Collection
    {
        $claims = 'y'.$yearId.'_expenseclaims';
        $monthTable = 'y'.$yearId.'_monthexpensefinal';
        $q = DB::connection($expenseConn)
            ->table(DB::raw("(SELECT crby, ClaimMonth, COUNT(*) AS total_claims FROM {$claims}
                WHERE ClaimStatus NOT LIKE 'Deactiv%' AND FilledOkay IN (1,0) GROUP BY crby, ClaimMonth) AS ec"))
            ->leftJoin("{$monthTable} as mef", function ($j) use ($yearId) {
                $j->on('mef.EmployeeID', '=', 'ec.crby')->on('mef.Month', '=', 'ec.ClaimMonth')->where('mef.YearId', '=', $yearId);
            })
            ->leftJoin("{$hrimsDb}.hrm_employee as e", 'ec.crby', '=', 'e.EmployeeID')
            ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->whereNull('mef.EmployeeID')
            ->whereIn('ec.ClaimMonth', $months);
        if ($applyFilters) {
            $applyFilters($q);
        }

        return $q->get([
            DB::raw('ec.crby as EmployeeID'), DB::raw('ec.ClaimMonth as Month'), DB::raw("'Open' as Status"),
            DB::raw('NULL as DateOfSubmit'), DB::raw('NULL as RecevingDate'), DB::raw('NULL as Verified_Date'),
            DB::raw('NULL as Approved_Date'), DB::raw('NULL as Finance_Date'), DB::raw('NULL as Fin_PayDate'),
            DB::raw('0 as Fin_PayAmt'), DB::raw('ec.total_claims as claim_count'),
        ]);
    }

    public function getMonthlyStageSummary(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $expenseClaimsTable = 'y'.$this->yearId.'_expenseclaims';
        $months = $filters['months'] ?? range(1, 12);
        $functionId = $filters['function_id'] ?? null;
        $departmentId = $filters['department_id'] ?? null;
        $isVVNR = ($this->companyId == 11);

        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        // ═══════════════════════════════════════════════════════════════════
        // STEP 1: Get employees WITH claims (same as monthFinalStage)
        // This is the exact same query as ReportController::monthFinalStage
        // with WHERE IFNULL(ec.total_claims, 0) > 0
        // ═══════════════════════════════════════════════════════════════════
        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->leftJoin("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin(DB::raw("(
                SELECT crby, ClaimMonth, COUNT(*) AS total_claims
                FROM {$expenseClaimsTable}
                WHERE ClaimStatus != 'Deactivate' AND FilledOkay IN (1,0)
                GROUP BY crby, ClaimMonth
            ) AS ec"), function ($join) {
                $join->on('ec.crby', '=', 'mef.EmployeeID')
                    ->on('ec.ClaimMonth', '=', 'mef.Month');
            })
            ->whereIn('mef.Month', $months)
            ->where('mef.YearId', $this->yearId)
            ->whereRaw('IFNULL(ec.total_claims, 0) > 0');

        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

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
            ...array_diff(\App\Support\Paid::selects($this->expenseConn, $table, 'mef'), ['mef.Fin_PayDate']), // PAID-01
        ])->get();
        // STAGE-01: claims whose month has no month record are counted as open months.
        $rows = $rows->concat(self::claimMonthsWithoutMonthRow($this->expenseConn, $hrimsDb, $this->yearId, $months,
            fn ($q) => $this->applyOrganogramFilters($q, $filters, 'eg', 'e.EmployeeID')))->values();

        // ═══════════════════════════════════════════════════════════════════
        // STEP 2: Get No Claim count PER MONTH (same as export does per-month)
        // Export filters for a specific month, gets employeeIds for that month,
        // then counts no-claim. We must do the same per month.
        // ═══════════════════════════════════════════════════════════════════
        $noClaimPerMonth = [];
        foreach ($months as $month) {
            $employeeIdsThisMonth = $rows->where('Month', $month)
                ->pluck('EmployeeID')->unique()->toArray();
            $noClaimPerMonth[$month] = $this->getNoClaimCount($employeeIdsThisMonth, $filters);
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 3: Classify each row by stage (same as ClaimStagesExport)
        // ═══════════════════════════════════════════════════════════════════
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

        foreach ($rows as $row) {
            $m = (int) $row->Month;
            if (!isset($results[$m])) continue;

            $results[$m]['total_claimant']++;

            $stage = $isVVNR
                ? $this->detectStageVVNR($row)
                : $this->detectStageVNR($row);

            switch ($stage) {
                case 'open_final_submission':
                    $results[$m]['open_final_submission']++;
                    break;
                case 'submitted_courier_not_sent':
                    $results[$m]['submitted_courier_not_sent']++;
                    break;
                case 'pending_verification':
                    $results[$m]['pending_verification']++;
                    break;
                case 'pending_approval':
                    $results[$m]['pending_approval']++;
                    break;
                case 'pending_finance':
                    $results[$m]['pending_finance']++;
                    break;
                case 'pending_payment':
                    $results[$m]['pending_payment']++;
                    break;
                case 'paid_pending_courier':
                    $results[$m]['paid_pending_courier']++;
                    break;
                case 'paid':
                    $results[$m]['total_paid']++;
                    break;
                default:
                    // no_claims_submitted won't happen since we filtered ec.total_claims > 0
                    break;
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 4: Calculate totals (same as export - per month)
        // ═══════════════════════════════════════════════════════════════════
        foreach ($results as $m => &$data) {
            $noClaimCount = $noClaimPerMonth[$m] ?? 0;
            $data['no_claims_submitted'] = $noClaimCount;
            $data['total_claims'] = $data['total_claimant']; // total_claimant here = only those WITH claims
            $data['total_claimant'] = $data['total_claimant'] + $noClaimCount;
            $data['percent_complete'] = $data['total_claims'] > 0
                ? round(($data['total_paid'] / $data['total_claims']) * 100, 2)
                : 0;
        }

        return $results;
    }

    /**
     * VNR stage detection - exact same logic as ClaimStagesExport::detectStageVNR()
     * Hierarchical: each record appears in the HIGHEST completed stage only.
     * PAID-01: "paid" is App\Support\Paid::is (pay date, confirmed with UTR, or no payment due).
     */
    private function detectStageVNR($row): string
    {
        if (\App\Support\Paid::is($row)) return 'paid'; // PAID-01
        if (!$this->isEmptyDate($row->Finance_Date ?? null)) return 'pending_payment';
        if (!$this->isEmptyDate($row->Approved_Date ?? null)) return 'pending_finance';
        if (!$this->isEmptyDate($row->Verified_Date ?? null)) return 'pending_approval';
        if (!$this->isEmptyDate($row->RecevingDate ?? null)) return 'pending_verification';

        if (!$this->isEmptyDate($row->DateOfSubmit ?? null) && ($row->Status ?? null) === 'Closed') {
            return 'submitted_courier_not_sent';
        }

        // Open but has uploaded claims
        if (($row->Status ?? null) === 'Open' && ($row->claim_count ?? 0) > 0) {
            return 'open_final_submission';
        }

        return 'no_claims_submitted';
    }

    /**
     * VVNR stage detection - exact same logic as ClaimStagesExport::detectStageVVNR()
     * VVNR workflow: Claim → Submit → Approve → Payment → Courier → Verify
     * PAID-01: "paid" is App\Support\Paid::is (pay date, confirmed with UTR, or no payment due).
     */
    private function detectStageVVNR($row): string
    {
        if (!$this->isEmptyDate($row->Verified_Date ?? null)) return 'paid';
        if (!$this->isEmptyDate($row->RecevingDate ?? null)) return 'pending_verification';
        if (\App\Support\Paid::is($row)) return 'paid_pending_courier'; // PAID-01
        if (!$this->isEmptyDate($row->Approved_Date ?? null)) return 'pending_payment';

        if (!$this->isEmptyDate($row->DateOfSubmit ?? null)) {
            return 'pending_approval';
        }

        if (($row->Status ?? null) === 'Open' && ($row->claim_count ?? 0) > 0) {
            return 'open_final_submission';
        }

        return 'no_claims_submitted';
    }

    private function isEmptyDate(?string $date): bool
    {
        return empty($date) || \App\Support\ZeroDate::none($date) || \App\Support\ZeroDate::none($date);
    }

    /**
     * Get stage-wise pending claims detail
     */
    public function getStagePendingDetail(string $stage, array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $expenseClaimsTable = 'y'.$this->yearId.'_expenseclaims';
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("{$hrimsDb}.core_functions as f", 'eg.EmpFunction', '=', 'f.id')
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId);

        // Apply stage filter
        switch ($stage) {
            case 'open':
                // Only include employees who have uploaded at least one claim
                $query->join(DB::raw("(
                    SELECT crby, ClaimMonth, COUNT(*) AS total_claims
                    FROM {$expenseClaimsTable}
                    WHERE ClaimStatus != 'Deactivate' AND FilledOkay IN(1, 0)
                    GROUP BY crby, ClaimMonth
                ) AS ec"), function ($join) {
                    $join->on('ec.crby', '=', 'mef.EmployeeID')
                        ->on('ec.ClaimMonth', '=', 'mef.Month');
                });
                $query->where('mef.Status', 'Open')
                    ->where(function ($q) {
                        $q->whereNull('mef.DateOfSubmit')
                            ->orWhere('mef.DateOfSubmit', '0000-00-00');
                    });
                break;
            case 'submitted_no_courier':
                // VNR only: submitted but courier not sent
                $query->where('mef.DateOfSubmit', '!=', '0000-00-00')
                    ->whereNotNull('mef.DateOfSubmit')
                    ->where('mef.Status', 'Closed')
                    ->where(function ($q) {
                        $q->whereNull('mef.RecevingDate')
                            ->orWhere('mef.RecevingDate', '0000-00-00');
                    });
                break;
            case 'pending_verification':
                if ($this->companyId == 11) {
                    // VVNR: courier received but not verified
                    $query->where('mef.RecevingDate', '!=', '0000-00-00')
                        ->whereNotNull('mef.RecevingDate')
                        ->where(function ($q) {
                            $q->whereNull('mef.Verified_Date')
                                ->orWhere('mef.Verified_Date', '0000-00-00');
                        });
                } else {
                    // VNR: courier received but not verified
                    $query->where('mef.RecevingDate', '!=', '0000-00-00')
                        ->whereNotNull('mef.RecevingDate')
                        ->where(function ($q) {
                            $q->whereNull('mef.Verified_Date')
                                ->orWhere('mef.Verified_Date', '0000-00-00');
                        });
                }
                break;
            case 'pending_approval':
                if ($this->companyId == 11) {
                    // VVNR: submitted but not approved (no verifier step before approval)
                    $query->where('mef.DateOfSubmit', '!=', '0000-00-00')
                        ->whereNotNull('mef.DateOfSubmit')
                        ->where(function ($q) {
                            $q->whereNull('mef.Approved_Date')
                                ->orWhere('mef.Approved_Date', '0000-00-00');
                        });
                } else {
                    // VNR: verified but not approved
                    $query->where('mef.Verified_Date', '!=', '0000-00-00')
                        ->whereNotNull('mef.Verified_Date')
                        ->where(function ($q) {
                            $q->whereNull('mef.Approved_Date')
                                ->orWhere('mef.Approved_Date', '0000-00-00');
                        });
                }
                break;
            case 'pending_payment':
                // VNR: Finance processed but not paid
                $query->where('mef.Finance_Date', '!=', '0000-00-00')
                    ->whereNotNull('mef.Finance_Date')
                    ->whereRaw('NOT '.\App\Support\Paid::sql($this->expenseConn, $table, 'mef'));
                break;
            case 'pending_finance':
                // VNR only: Approved but finance not processed
                $query->where('mef.Approved_Date', '!=', '0000-00-00')
                    ->whereNotNull('mef.Approved_Date')
                    ->where(function ($q) {
                        $q->whereNull('mef.Finance_Date')
                            ->orWhere('mef.Finance_Date', '0000-00-00');
                    });
                break;
            case 'paid_pending_courier':
                // VVNR only: paid but courier not sent/received
                $query->whereRaw(\App\Support\Paid::sql($this->expenseConn, $table, 'mef'))
                    ->where(function ($q) {
                        $q->whereNull('mef.RecevingDate')
                            ->orWhere('mef.RecevingDate', '0000-00-00');
                    });
                break;
            case 'paid':
                // Paid/completed claims (PAID-01: App\Support\Paid)
                $query->whereRaw(\App\Support\Paid::sql($this->expenseConn, $table, 'mef'));
                break;
            case 'no_claim':
                // No Claim Employee List: active employees not in monthexpensefinal
                // This requires a different query - return from HRIMS directly
                return $this->getNoClaimEmployeeList($filters);
        }

        // Apply additional filters
        if (! empty($filters['months'])) {
            $query->whereIn('mef.Month', (array) $filters['months']);
        }
        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        return $query->select([
            'e.EmpCode',
            DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
            'e.EmpStatus',
            'd.department_name',
            'f.function_name',
            'mef.Month',
            'mef.Total_Claim',
            'mef.Claim_Amount',
            'mef.DateOfSubmit',
            'mef.PostDate',
            'mef.RecevingDate',
            'mef.Verified_Date',
            'mef.Approved_Date',
            'mef.Finance_Date',
            'mef.Fin_PayDate',
            'mef.DocRmk',
        ])->orderBy('mef.Month')->orderBy('e.EmpCode')->get()->toArray();
    }

    /**
     * Get No Claim Employee List - active employees with no entry in monthexpensefinal
     * PLUS employees in monthexpensefinal but with no verified claims in expenseclaims
     */
    private function getNoClaimEmployeeList(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $expenseClaimsTable = 'y'.$this->yearId.'_expenseclaims';
        $months = (array) ($filters['months'] ?? range(1, 12));
        $functionId = $filters['function_id'] ?? null;
        $departmentId = $filters['department_id'] ?? null;
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        // Part 1: Employees in monthexpensefinal but with no actual claims (claim_count = 0)
        $inTableNoClaimsQuery = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("{$hrimsDb}.core_functions as f", 'eg.EmpFunction', '=', 'f.id')
            ->leftJoin(DB::raw("(
                SELECT crby, ClaimMonth, COUNT(*) AS total_claims
                FROM {$expenseClaimsTable}
                WHERE ClaimStatus != 'Deactivate' AND FilledOkay IN(1,0)
                GROUP BY crby, ClaimMonth
            ) AS ec"), function ($join) {
                $join->on('ec.crby', '=', 'mef.EmployeeID')
                    ->on('ec.ClaimMonth', '=', 'mef.Month');
            })
            ->where('mef.YearId', $this->yearId)
            ->whereIn('mef.Month', $months)
            ->where('e.EmpStatus', 'A');

        // Company filter
        if ($this->companyId == 1) {
            $inTableNoClaimsQuery->where('e.CompanyId', 1);
        } elseif ($this->companyId == 11) {
            $inTableNoClaimsQuery->where('e.CompanyId', 11)
                ->where('mef.EmployeeID', '>', 100000);
        }

        $this->applyOrganogramFilters($inTableNoClaimsQuery, $filters, 'eg', 'e.EmployeeID');

        // No claims = Open status with no verified claims OR no submission
        $inTableNoClaimsQuery->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('mef.Status', 'Open')
                    ->where(function ($q3) {
                        $q3->whereNull('mef.DateOfSubmit')
                            ->orWhere('mef.DateOfSubmit', '0000-00-00');
                    })
                    ->whereRaw('IFNULL(ec.total_claims, 0) = 0');
            });
        });

        $inTableNoClaims = $inTableNoClaimsQuery->select([
            'e.EmpCode',
            DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
            'e.EmpStatus',
            DB::raw("COALESCE(d.department_name, '-') as department_name"),
            DB::raw("COALESCE(f.function_name, '-') as function_name"),
            'mef.Month',
            DB::raw("0 as Total_Claim"),
            DB::raw("0 as Claim_Amount"),
            DB::raw("'0000-00-00' as DateOfSubmit"),
            DB::raw("'0000-00-00' as PostDate"),
            DB::raw("'0000-00-00' as RecevingDate"),
            DB::raw("'0000-00-00' as Verified_Date"),
            DB::raw("'0000-00-00' as Approved_Date"),
            DB::raw("'0000-00-00' as Finance_Date"),
            DB::raw("'0000-00-00' as Fin_PayDate"),
            DB::raw("'In monthexpensefinal but no claims' as DocRmk"),
        ])->get()->toArray();

        // Part 2: Active employees NOT in monthexpensefinal at all
        $employeeIdsInTable = DB::connection($this->expenseConn)
            ->table($table)
            ->where('YearId', $this->yearId)
            ->whereIn('Month', $months)
            ->pluck('EmployeeID')
            ->unique()
            ->toArray();

        $query = $this->buildNoClaimBaseQuery($employeeIdsInTable, $filters);

        $notInTable = $query->select([
            'hrm_employee.EmpCode',
            DB::raw("CONCAT(hrm_employee.Fname, ' ', COALESCE(hrm_employee.Sname, ''), ' ', COALESCE(hrm_employee.Lname, '')) as employee_name"),
            'hrm_employee.EmpStatus',
            DB::raw("COALESCE(core_departments.department_name, '-') as department_name"),
            DB::raw("COALESCE(core_functions.function_name, '-') as function_name"),
            DB::raw("NULL as Month"),
            DB::raw("0 as Total_Claim"),
            DB::raw("0 as Claim_Amount"),
            DB::raw("'0000-00-00' as DateOfSubmit"),
            DB::raw("'0000-00-00' as PostDate"),
            DB::raw("'0000-00-00' as RecevingDate"),
            DB::raw("'0000-00-00' as Verified_Date"),
            DB::raw("'0000-00-00' as Approved_Date"),
            DB::raw("'0000-00-00' as Finance_Date"),
            DB::raw("'0000-00-00' as Fin_PayDate"),
            DB::raw("'Not in monthexpensefinal' as DocRmk"),
        ])->orderBy('hrm_employee.EmpCode')->get()->toArray();

        // Merge both sets — deduplicate by EmpCode
        $merged = array_merge($inTableNoClaims, $notInTable);
        $seen = [];
        $result = [];
        foreach ($merged as $row) {
            $empCode = is_array($row) ? $row['EmpCode'] : $row->EmpCode;
            if (!isset($seen[$empCode])) {
                $seen[$empCode] = true;
                $result[] = $row;
            }
        }

        // Sort by EmpCode
        usort($result, function ($a, $b) {
            $codeA = is_array($a) ? $a['EmpCode'] : $a->EmpCode;
            $codeB = is_array($b) ? $b['EmpCode'] : $b->EmpCode;
            return strcmp($codeA, $codeB);
        });

        return $result;
    }

    /**
     * Get count of no-claim employees — uses same logic as ReportController::noClaimEmployeeList
     * Queries hrm_employee with EmpStatus='A', company filter, excludes $excludeEmployeeIds
     */
    private function getNoClaimCount(array $excludeEmployeeIds, $functionIdOrFilters = null, ?string $departmentId = null): int
    {
        $filters = is_array($functionIdOrFilters) ? $functionIdOrFilters : [
            'function_id' => $functionIdOrFilters,
            'department_id' => $departmentId,
        ];
        $hrimsConnection = session('hrims_connection', $this->hrimsConn);

        $query = DB::connection($hrimsConnection)
            ->table('hrm_employee')
            ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
            ->where('hrm_employee.EmpStatus', 'A');

        // Company filter — same as ReportController::noClaimEmployeeList
        if ($this->companyId == 1) {
            $query->where('hrm_employee.CompanyId', 1);
        } elseif ($this->companyId == 11) {
            $query->where('hrm_employee.CompanyId', 11)
                ->where('hrm_employee.EmployeeID', '>', 100000);
        }

        // Exclude employees who have claims
        if (!empty($excludeEmployeeIds)) {
            $query->whereNotIn('hrm_employee.EmployeeID', $excludeEmployeeIds);
        }

        $this->applyOrganogramFilters($query, $filters, 'hrm_employee_general', 'hrm_employee.EmployeeID');

        return $query->count();
    }

    /**
     * Build the base query for no-claim employees (used by both count and list)
     * Matches exactly: ReportController::noClaimEmployeeList()
     */
    private function buildNoClaimBaseQuery(array $excludeEmployeeIds, $functionIdOrFilters = null, ?string $departmentId = null)
    {
        $filters = is_array($functionIdOrFilters) ? $functionIdOrFilters : [
            'function_id' => $functionIdOrFilters,
            'department_id' => $departmentId,
        ];
        $query = DB::connection($this->hrimsConn)
            ->table('hrm_employee')
            ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
            ->leftJoin('core_departments', 'core_departments.id', '=', 'hrm_employee_general.DepartmentId')
            ->leftJoin('core_functions', 'core_functions.id', '=', 'hrm_employee_general.EmpFunction')
            ->where('hrm_employee.EmpStatus', 'A');

        if ($this->companyId == 1) {
            $query->where('hrm_employee.CompanyId', 1);
        } elseif ($this->companyId == 11) {
            $query->where('hrm_employee.CompanyId', 11)
                ->where('hrm_employee.EmployeeID', '>', 100000);
        }

        if (!empty($excludeEmployeeIds)) {
            $query->whereNotIn('hrm_employee.EmployeeID', $excludeEmployeeIds);
        }

        $this->applyOrganogramFilters($query, $filters, 'hrm_employee_general', 'hrm_employee.EmployeeID');

        return $query;
    }

    /**
     * Get department-wise stage summary (pivot table)
     */
    public function getDepartmentStageSummary(string $stage, array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("{$hrimsDb}.core_functions as f", 'eg.EmpFunction', '=', 'f.id')
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId);

        // Apply common filters
        if (! empty($filters['months'])) {
            $query->whereIn('mef.Month', $filters['months']);
        }
        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        // Apply stage filter (same as above)
        switch ($stage) {
            case 'submitted_no_courier':
                $query->where('mef.DateOfSubmit', '!=', '0000-00-00')
                    ->whereNotNull('mef.DateOfSubmit')
                    ->where(function ($q) {
                        $q->whereNull('mef.RecevingDate')
                            ->orWhere('mef.RecevingDate', '0000-00-00');
                    });
                break;
            case 'pending_verification':
                $query->where('mef.RecevingDate', '!=', '0000-00-00')
                    ->whereNotNull('mef.RecevingDate')
                    ->where(function ($q) {
                        $q->whereNull('mef.Verified_Date')
                            ->orWhere('mef.Verified_Date', '0000-00-00');
                    });
                break;
            case 'pending_approval':
                $query->where('mef.Verified_Date', '!=', '0000-00-00')
                    ->whereNotNull('mef.Verified_Date')
                    ->where(function ($q) {
                        $q->whereNull('mef.Approved_Date')
                            ->orWhere('mef.Approved_Date', '0000-00-00');
                    });
                break;
            case 'pending_payment':
                $query->where('mef.Approved_Date', '!=', '0000-00-00')
                    ->whereNotNull('mef.Approved_Date')
                    ->whereRaw('NOT '.\App\Support\Paid::sql($this->expenseConn, $table, 'mef'));
                break;
        }

        return $query->select([
            'f.function_name',
            'd.department_name',
            'mef.Month',
            DB::raw('COUNT(*) as count'),
        ])
            ->groupBy('f.function_name', 'd.department_name', 'mef.Month')
            ->orderBy('f.function_name')
            ->orderBy('d.department_name')
            ->orderBy('mef.Month')
            ->get()
            ->toArray();
    }

    /**
     * Get TAT (Turn Around Time) analysis — workflow-aware
     * VNR: Submit → Courier → Receive → Verify → Approve → Pay
     * VVNR: Submit → Approve → Pay → Courier → Receive → Verify
     */
    public function getTATAnalysis(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        $isVVNR = ($this->companyId == 11);

        $months = $filters['months'] ?? range(1, 12);
        $results = [];

        foreach ($months as $month) {
            $query = DB::connection($this->expenseConn)
                ->table("$table as mef")
                ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
                ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->where('mef.Month', $month)
                ->where('mef.YearId', $this->yearId)
                ->where('e.CompanyId', $this->companyId)
                ->where('mef.DateOfSubmit', '!=', '0000-00-00')
                ->whereNotNull('mef.DateOfSubmit');

            $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

            if ($isVVNR) {
                // VVNR: Submit → Approve → Pay → Courier → Verify
                $data = $query->select([
                    // 1. Submit to Approve
                    DB::raw("ROUND(AVG(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Approved_Date, mef.DateOfSubmit) END)) as submit_to_approve"),
                    // 2. Approve to Pay
                    DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.Approved_Date) END)) as approve_to_pay"),
                    // 3. Pay to Courier posting
                    DB::raw("ROUND(AVG(CASE WHEN mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' AND mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' THEN DATEDIFF(mef.PostDate, mef.Fin_PayDate) END)) as pay_to_courier"),
                    // 4. Courier to Receive
                    DB::raw("ROUND(AVG(CASE WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' AND mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.RecevingDate, mef.PostDate) END)) as courier_to_receive"),
                    // 5. Receive to Verify
                    DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.RecevingDate) END)) as receive_to_verify"),
                    // 6. Total: Submit to Verify (complete cycle)
                    DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.DateOfSubmit) END)) as total_tat"),
                ])->first();

                $results[$month] = [
                    'month' => $month,
                    'submit_to_approve' => (int) ($data->submit_to_approve ?? 0),
                    'approve_to_pay' => (int) ($data->approve_to_pay ?? 0),
                    'pay_to_courier' => (int) ($data->pay_to_courier ?? 0),
                    'courier_to_receive' => (int) ($data->courier_to_receive ?? 0),
                    'receive_to_verify' => (int) ($data->receive_to_verify ?? 0),
                    'total_tat' => (int) ($data->total_tat ?? 0),
                    'workflow' => 'vvnr',
                ];
            } else {
                // VNR/Nursery: Submit → Courier → Receive → Verify → Approve → Pay
                $data = $query->select([
                    // 1. Submit to Courier posting
                    DB::raw("ROUND(AVG(CASE WHEN mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.PostDate, mef.DateOfSubmit) END)) as submit_to_courier"),
                    // 2. Post to receive
                    DB::raw("ROUND(AVG(CASE WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' AND mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.RecevingDate, mef.PostDate) ELSE 0 END)) as post_to_receive"),
                    // 3. Receive to verify
                    DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.RecevingDate) END)) as receive_to_verify"),
                    // 4. Verify to approve
                    DB::raw("ROUND(AVG(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' AND mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Approved_Date, mef.Verified_Date) END)) as verify_to_approve"),
                    // 5. Approve to pay
                    DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.Approved_Date) END)) as approve_to_pay"),
                    // 6. Courier Receipt to pay (total from receipt)
                    DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.RecevingDate) END)) as courier_receipt_to_pay"),
                ])->first();

                $results[$month] = [
                    'month' => $month,
                    'submit_to_courier' => (int) ($data->submit_to_courier ?? 0),
                    'post_to_receive' => (int) ($data->post_to_receive ?? 0),
                    'receive_to_verify' => (int) ($data->receive_to_verify ?? 0),
                    'verify_to_approve' => (int) ($data->verify_to_approve ?? 0),
                    'approve_to_pay' => (int) ($data->approve_to_pay ?? 0),
                    'courier_receipt_to_pay' => (int) ($data->courier_receipt_to_pay ?? 0),
                    'workflow' => 'vnr',
                ];
            }
        }

        return $results;
    }

    /**
     * Get submission timeline distribution (for bar chart)
     */
public function getSubmissionTimeline(array $filters = []): array
{
    $table = MonthExpenseFinal::tableName($this->yearId);
    $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
    $months = $filters['months'] ?? range(1, 12);
    $results = [];

    foreach ($months as $month) {
        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('mef.Month', $month)
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId)
            ->where('mef.DateOfSubmit', '!=', '0000-00-00')
            ->whereNotNull('mef.DateOfSubmit');

        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        $data = $query->select([

            // 1st - 7th of NEXT month
            DB::raw("
                SUM(
                    CASE
                        WHEN DAY(mef.DateOfSubmit) BETWEEN 1 AND 7
                        AND PERIOD_DIFF(
                            DATE_FORMAT(mef.DateOfSubmit, '%Y%m'),
                            CONCAT(YEAR(mef.Crdate), LPAD(mef.Month, 2, '0'))
                        ) = 1
                        THEN 1
                        ELSE 0
                    END
                ) as between_1_7
            "),

            // 8th - 15th of NEXT month
            DB::raw("
                SUM(
                    CASE
                        WHEN DAY(mef.DateOfSubmit) BETWEEN 8 AND 15
                        AND PERIOD_DIFF(
                            DATE_FORMAT(mef.DateOfSubmit, '%Y%m'),
                            CONCAT(YEAR(mef.Crdate), LPAD(mef.Month, 2, '0'))
                        ) = 1
                        THEN 1
                        ELSE 0
                    END
                ) as between_8_15
            "),

            // After 15th of NEXT month
            DB::raw("
                SUM(
                    CASE
                        WHEN DAY(mef.DateOfSubmit) > 15
                        AND PERIOD_DIFF(
                            DATE_FORMAT(mef.DateOfSubmit, '%Y%m'),
                            CONCAT(YEAR(mef.Crdate), LPAD(mef.Month, 2, '0'))
                        ) = 1
                        THEN 1
                        ELSE 0
                    END
                ) as after_15th_next_month
            "),

            // Submitted 2 or more months after claim month
            DB::raw("
                SUM(
                    CASE
                        WHEN PERIOD_DIFF(
                            DATE_FORMAT(mef.DateOfSubmit, '%Y%m'),
                            CONCAT(YEAR(mef.Crdate), LPAD(mef.Month, 2, '0'))
                        ) >= 2
                        THEN 1
                        ELSE 0
                    END
                ) as after_2_months
            "),

            DB::raw('COUNT(*) as total'),

        ])->first();

        $results[$month] = [
            'month' => $month,
            'between_1_7' => (int) ($data->between_1_7 ?? 0),
            'between_8_15' => (int) ($data->between_8_15 ?? 0),
            'after_15th_next_month' => (int) ($data->after_15th_next_month ?? 0),
            'after_2_months' => (int) ($data->after_2_months ?? 0),
            'total' => (int) ($data->total ?? 0),
        ];
    }

    return $results;
}
    /**
     * Get F&F (Full & Final) settlement status
     */
    public function getFnFStatus(array $filters = []): array
    {
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        $query = DB::connection($this->hrimsConn)
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin('core_departments as d', 'eg.DepartmentId', '=', 'd.id')
            ->where('e.CompanyId', $this->companyId)
            ->where('e.EmpStatus', 'D')
            ->where(function ($q) {
                $q->whereNotNull('e.DateOfResignation')
                    ->where('e.DateOfResignation', '!=', '0000-00-00');
            });

        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        return $query->select([
            'e.EmpCode',
            DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
            'd.department_name',
            'e.DateOfResignation as resignation_date',
            'e.DateOfSepration as relieving_date',
        ])
            ->orderBy('e.DateOfResignation', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Get Courier Posting Status — Function/Department pivot with Yes/No counts
     */
    public function getCourierPostingStatus(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("{$hrimsDb}.core_functions as f", 'eg.EmpFunction', '=', 'f.id')
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId)
            ->where('mef.DateOfSubmit', '!=', '0000-00-00')
            ->whereNotNull('mef.DateOfSubmit');

        if (! empty($filters['months'])) {
            $query->whereIn('mef.Month', $filters['months']);
        }
        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        return $query->select([
            'f.function_name',
            'd.department_name',
            DB::raw("SUM(CASE 
                WHEN mef.PostDate IS NULL 
                  OR TRIM(mef.PostDate) = '' 
                  OR mef.PostDate = '0000-00-00' 
                  OR mef.PostDate = '0000-00-00 00:00:00' 
                  OR mef.PostDate = '00-00-0000' 
                  OR mef.PostDate LIKE '0000%' 
                  OR mef.PostDate LIKE '%0000' 
                  OR mef.PostDate = '1970-01-01' 
                  OR NOT (mef.PostDate REGEXP '^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}' OR mef.PostDate REGEXP '^[0-9]{2}-[0-9]{2}-[1-9][0-9]{3}')
                THEN 1 
                ELSE 0 
            END) as courier_no"),
            DB::raw("SUM(CASE 
                WHEN mef.PostDate IS NOT NULL 
                  AND TRIM(mef.PostDate) != '' 
                  AND mef.PostDate != '0000-00-00' 
                  AND mef.PostDate != '0000-00-00 00:00:00' 
                  AND mef.PostDate != '00-00-0000' 
                  AND mef.PostDate NOT LIKE '0000%' 
                  AND mef.PostDate NOT LIKE '%0000' 
                  AND mef.PostDate != '1970-01-01' 
                  AND (mef.PostDate REGEXP '^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}' OR mef.PostDate REGEXP '^[0-9]{2}-[0-9]{2}-[1-9][0-9]{3}')
                THEN 1 
                ELSE 0 
            END) as courier_yes"),
            DB::raw('COUNT(*) as grand_total'),
        ])
            ->groupBy('f.function_name', 'd.department_name')
            ->orderBy('f.function_name')
            ->orderBy('d.department_name')
            ->get()
            ->toArray();
    }

    /**
     * Get Management Claim Status — management-level employees with pending claims
     */
    public function getManagementClaimStatus(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();

        // Management grades typically are higher levels (M1, M2, etc.)
        // We'll get employees whose claims are pending at any stage
        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_grades as g", 'eg.GradeId', '=', 'g.id')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId)
            ->where('e.EmpStatus', 'A')
            // Management level: grade level <= 3 or grade name starts with M/S/VP/Director
            ->where(function ($q) {
                $q->where('g.level_id', '<=', 3)
                    ->orWhere('g.grade_name', 'LIKE', 'M%')
                    ->orWhere('g.grade_name', 'LIKE', 'S%')
                    ->orWhere('g.grade_name', 'LIKE', 'VP%');
            })
            // Has pending claims (submitted but not fully paid)
            ->where('mef.DateOfSubmit', '!=', '0000-00-00')
            ->whereNotNull('mef.DateOfSubmit')
            ->whereRaw('NOT '.\App\Support\Paid::sql($this->expenseConn, $table, 'mef'));

        if (! empty($filters['months'])) {
            $query->whereIn('mef.Month', $filters['months']);
        }
        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        return $query->select([
            'e.EmpCode',
            DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
            'd.department_name',
            'g.grade_name',
            'mef.Month',
            'mef.DateOfSubmit',
            'mef.RecevingDate',
            'mef.Verified_Date',
            'mef.Approved_Date',
            'mef.Claim_Amount',
            DB::raw("CASE
                WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN 'Pending Payment'
                WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN 'Pending Approval'
                WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN 'Pending Verification'
                WHEN mef.RecevingDate IS NULL OR mef.RecevingDate = '0000-00-00' THEN 'Courier not received'
                ELSE 'Submitted'
            END as pending_status"),
        ])
            ->orderBy('mef.Month')
            ->orderBy('e.EmpCode')
            ->get()
            ->toArray();
    }

    /**
     * Get Employees Promoted Out from Imprest Criteria
     */
    public function getImprestPromotions(array $filters = []): array
    {
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        $expenseDb = DB::connection($this->expenseConn)->getDatabaseName();

        // Get employees who have imprest and were recently promoted
        $query = DB::connection($this->expenseConn)
            ->table('employee_ledger_balances as elb')
            ->join("{$hrimsDb}.hrm_employee as e", 'elb.employee_id', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("{$hrimsDb}.core_grades as g", 'eg.GradeId', '=', 'g.id')
            ->where('e.CompanyId', $this->companyId)
            ->where('e.EmpStatus', 'A')
            ->where(function ($q) {
                $q->where('elb.fixed_imprest_amt_1', '>', 0)
                    ->orWhere('elb.fixed_imprest_amt_2', '>', 0);
            });

        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        return $query->select([
            'e.EmpCode',
            DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
            'd.department_name',
            'g.grade_name',
            'elb.fixed_imprest_amt_1 as amount',
            'elb.fixed_imprest_date_1 as released_date',
        ])
            ->orderBy('e.EmpCode')
            ->get()
            ->toArray();
    }

    /**
     * Get Employee-wise TAT Analysis with total claims & TAT breakdown per employee
     */
    public function getEmployeeWiseTATAnalysis(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        $isVVNR = ($this->companyId == 11);
        $months = $filters['months'] ?? range(1, 12);

        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("{$hrimsDb}.core_functions as f", 'eg.EmpFunction', '=', 'f.id')
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId)
            ->where('mef.DateOfSubmit', '!=', '0000-00-00')
            ->whereNotNull('mef.DateOfSubmit')
            ->whereIn('mef.Month', $months);

        if (! empty($filters['employee_id'])) {
            $query->where('mef.EmployeeID', $filters['employee_id']);
        }
        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        if ($isVVNR) {
            // VVNR: Submit → Approve → Pay → Courier → Verify
            $results = $query->select([
                'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
                'd.department_name',
                'f.function_name',
                DB::raw('COUNT(*) as total_claims'),
                DB::raw('ROUND(AVG(mef.Claim_Amount)) as avg_claim_amount'),
                DB::raw("ROUND(AVG(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Approved_Date, mef.DateOfSubmit) END)) as submit_to_approve"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.Approved_Date) END)) as approve_to_pay"),
                DB::raw("ROUND(AVG(CASE WHEN mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' AND mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' THEN DATEDIFF(mef.PostDate, mef.Fin_PayDate) END)) as pay_to_courier"),
                DB::raw("ROUND(AVG(CASE WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' AND mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.RecevingDate, mef.PostDate) END)) as courier_to_receive"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.RecevingDate) END)) as receive_to_verify"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.DateOfSubmit) END)) as total_tat"),
            ])->groupBy('mef.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'd.department_name', 'f.function_name')
                ->orderByRaw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.DateOfSubmit) END)) DESC")
                ->get()
                ->toArray();
        } else {
            // VNR: Submit → Courier → Receive → Verify → Approve → Pay
            $results = $query->select([
                'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
                'd.department_name',
                'f.function_name',
                DB::raw('COUNT(*) as total_claims'),
                DB::raw('ROUND(AVG(mef.Claim_Amount)) as avg_claim_amount'),
                DB::raw("ROUND(AVG(CASE WHEN mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.PostDate, mef.DateOfSubmit) END)) as submit_to_courier"),
                DB::raw("ROUND(AVG(CASE WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' AND mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.RecevingDate, mef.PostDate) ELSE 0 END)) as post_to_receive"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.RecevingDate) END)) as receive_to_verify"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' AND mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Approved_Date, mef.Verified_Date) END)) as verify_to_approve"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.Approved_Date) END)) as approve_to_pay"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.RecevingDate) END)) as courier_receipt_to_pay"),
            ])->groupBy('mef.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'd.department_name', 'f.function_name')
                ->orderByRaw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.RecevingDate) END)) DESC")
                ->get()
                ->toArray();
        }

        return $results;
    }

    /**
     * Get Claim-wise TAT Analysis grouped by claim type
     */
    public function getClaimWiseTATAnalysis(array $filters = []): array
    {
        $table = MonthExpenseFinal::tableName($this->yearId);
        $expenseClaimsTable = 'y'.$this->yearId.'_expenseclaims';
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        $isVVNR = ($this->companyId == 11);
        $months = $filters['months'] ?? range(1, 12);

        $query = DB::connection($this->expenseConn)
            ->table("$table as mef")
            ->join("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("$expenseClaimsTable as ec", function ($join) {
                $join->on('mef.EmployeeID', '=', 'ec.CrBy')
                     ->on('mef.Month', '=', 'ec.ClaimMonth')
                     ->on('mef.YearId', '=', 'ec.ClaimYearId');
            })
            ->leftJoin("claimtype as ct", 'ec.ClaimId', '=', 'ct.ClaimId')
            ->where('mef.YearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId)
            ->where('mef.DateOfSubmit', '!=', '0000-00-00')
            ->whereNotNull('mef.DateOfSubmit')
            ->whereIn('mef.Month', $months)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->whereIn('ec.FilledOkay', [0, 1]) ;

        if (! empty($filters['claim_type_id'])) {
            $query->where('ct.ClaimId', $filters['claim_type_id']);
        }
        $this->applyOrganogramFilters($query, $filters, 'eg', 'e.EmployeeID');

        if ($isVVNR) {
            $results = $query->select([
                'ct.ClaimName as claim_type',
                DB::raw('COUNT(DISTINCT ec.ExpId) as total_claims'),
                DB::raw("COALESCE(ROUND(SUM(CASE WHEN ec.FilledTAmt > 0 THEN ec.FilledTAmt ELSE ec.Amount END) / COUNT(DISTINCT ec.ExpId)), 0) as avg_claim_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN ec.FilledTAmt > 0 THEN ec.FilledTAmt ELSE ec.Amount END), 0) as total_amount"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Approved_Date, mef.DateOfSubmit) END)) as submit_to_approve"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.Approved_Date) END)) as approve_to_pay"),
                DB::raw("ROUND(AVG(CASE WHEN mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' AND mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' THEN DATEDIFF(mef.PostDate, mef.Fin_PayDate) END)) as pay_to_courier"),
                DB::raw("ROUND(AVG(CASE WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' AND mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.RecevingDate, mef.PostDate) END)) as courier_to_receive"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.RecevingDate) END)) as receive_to_verify"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.DateOfSubmit) END)) as total_tat"),
            ])->groupBy('ct.ClaimId', 'ct.ClaimName')
                ->orderByRaw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.DateOfSubmit) END)) DESC")
                ->get()
                ->toArray();
        } else {
            $results = $query->select([
                'ct.ClaimName as claim_type',
                DB::raw('COUNT(DISTINCT ec.ExpId) as total_claims'),
                DB::raw("COALESCE(ROUND(SUM(CASE WHEN ec.FilledTAmt > 0 THEN ec.FilledTAmt ELSE ec.Amount END) / COUNT(DISTINCT ec.ExpId)), 0) as avg_claim_amount"),
                DB::raw("COALESCE(SUM(CASE WHEN ec.FilledTAmt > 0 THEN ec.FilledTAmt ELSE ec.Amount END), 0) as total_amount"),
                DB::raw("ROUND(AVG(CASE WHEN mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.PostDate, mef.DateOfSubmit) END)) as submit_to_courier"),
                DB::raw("ROUND(AVG(CASE WHEN mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' AND mef.PostDate IS NOT NULL AND mef.PostDate != '0000-00-00' THEN DATEDIFF(mef.RecevingDate, mef.PostDate) ELSE 0 END)) as post_to_receive"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Verified_Date, mef.RecevingDate) END)) as receive_to_verify"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' AND mef.Verified_Date IS NOT NULL AND mef.Verified_Date != '0000-00-00' THEN DATEDIFF(mef.Approved_Date, mef.Verified_Date) END)) as verify_to_approve"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.Approved_Date IS NOT NULL AND mef.Approved_Date != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.Approved_Date) END)) as approve_to_pay"),
                DB::raw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.RecevingDate) END)) as courier_receipt_to_pay"),
            ])->groupBy('ct.ClaimId', 'ct.ClaimName')
                ->orderByRaw("ROUND(AVG(CASE WHEN mef.Fin_PayDate IS NOT NULL AND mef.Fin_PayDate != '0000-00-00' AND mef.RecevingDate IS NOT NULL AND mef.RecevingDate != '0000-00-00' THEN DATEDIFF(mef.Fin_PayDate, mef.RecevingDate) END)) DESC")
                ->get()
                ->toArray();
        }

        return $results;
    }
}
