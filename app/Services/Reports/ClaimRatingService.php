<?php

namespace App\Services\Reports;

use App\Helpers\DatabaseSwitcher;
use App\Models\ClaimRatingRule;
use App\Models\MonthExpenseFinal;
use Illuminate\Support\Facades\DB;

class ClaimRatingService
{
    protected string $expenseConn;
    protected string $hrimsConn;
    protected int $yearId;
    protected int $companyId;

    /** M-06: longest stage time accepted; anything longer comes from a wrong or zero date and is not rated. */
    public const MAX_DAYS = 365;

    /** M-06: who is rated for each responsible role (the claimant only for the employee and courier stages). */
    private const PERSON_FIELD = [
        'employee' => 'EmployeeID',
        'courier' => 'EmployeeID',
        'mediator' => 'FilledBy',
        'verifier' => 'VerifyBy',
        'approver' => 'ApprBy',
    ];

    public function __construct(?string $expenseConn = null, ?int $yearId = null, ?int $companyId = null, ?string $hrimsConn = null)
    {
        $this->expenseConn = $expenseConn ?? session('expense_connection', DatabaseSwitcher::getExpenseConnection());
        $this->hrimsConn = $hrimsConn ?? session('hrims_connection', 'hrims');
        $this->yearId = $yearId ?? \App\Support\CurrentYear::id();
        $this->companyId = $companyId ?? (int) session('company_id', 1);
    }

    /** M-06: the person responsible for a stage of this claim (0 when not recorded). */
    private function ratedPersonId(string $role, string $stageKey, object $claim): int
    {
        if ($role === 'finance') {
            return (int) ($stageKey === 'finance_to_payment' ? ($claim->Fin_PayBy ?: $claim->FinancedBy) : ($claim->FinancedBy ?: $claim->Fin_AppBy));
        }

        return (int) ($claim->{self::PERSON_FIELD[$role] ?? 'EmployeeID'} ?? 0);
    }

    /**
     * M-06: work out the ratings and store them with the rated person, replacing earlier results for those
     * months. Used by the screen and by the nightly claims:rate run. Returns [claims rated, ratings stored].
     */
    public function storeRatings(array $months, ?int $byUserId = null): array
    {
        $db = DB::connection($this->expenseConn);
        $today = now()->toDateString();
        $claims = 0;
        $stored = 0;
        foreach (array_values(array_unique(array_map('intval', $months))) as $month) {
            // one month at a time keeps memory flat on a full year
            $data = $this->calculateRatings(['months' => [$month]]);
            if (isset($data['error'])) {
                throw new \RuntimeException($data['error']);
            }
            $rows = [];
            foreach ($data['claims'] as $claim) {
                foreach ($claim['ratings'] as $stageKey => $r) {
                    $rows[] = [
                        'company_id' => $this->companyId, 'year_id' => $this->yearId, 'month' => $claim['month'],
                        'employee_id' => $claim['employee_id'], 'emp_code' => $claim['emp_code'],
                        'employee_name' => $claim['employee_name'], 'department_name' => $claim['department'],
                        'stage_key' => $stageKey, 'stage_label' => $r['stage_label'], 'rated_role' => $r['role'],
                        'rated_person_id' => $r['person_id'] ?: null, 'rated_person_name' => $r['person_name'],
                        'days_taken' => $r['days'], 'stars' => $r['stars'], 'from_date' => $r['from'], 'to_date' => $r['to'],
                        'rule_id' => $r['rule_id'], 'calculated_date' => $today, 'calculated_by' => $byUserId,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
            $claims += $data['total_claims'];
            unset($data);
            $db->transaction(function () use ($db, $month, $rows) {
                $db->table('claim_rating_results')->where('company_id', $this->companyId)->where('year_id', $this->yearId)
                    ->where('month', $month)->delete();
                foreach (array_chunk($rows, 1000) as $chunk) {
                    $db->table('claim_rating_results')->insert($chunk);
                }
            });
            $stored += count($rows);
            unset($rows);
        }
        \App\Models\ClaimRatingSetting::updateOrCreate(['company_id' => $this->companyId], ['last_calculated_date' => $today, 'last_calculated_by' => $byUserId]);

        return [$claims, $stored];
    }

    /**
     * Calculate ratings for all claims — now at INDIVIDUAL CLAIM level from expenseclaims table
     * Each row in expenseclaims = 1 claim
     * monthexpensefinal used for later stages (submit, courier, verify, approve, finance, payment)
     */
    public function calculateRatings(array $filters = []): array
    {
        $rules = ClaimRatingRule::getRulesForCompany($this->companyId);
        if ($rules->isEmpty()) {
            return ['error' => 'No rating rules defined for this company. Go to /admin/claim-rating to define rules.'];
        }

        $claimsTable = "y{$this->yearId}_expenseclaims";
        $mefTable = MonthExpenseFinal::tableName($this->yearId);
        $hrimsDb = DB::connection($this->hrimsConn)->getDatabaseName();
        $months = $filters['months'] ?? [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        $isVVNR = ($this->companyId == 11);

        // Get individual claims from expenseclaims table
        $query = DB::connection($this->expenseConn)
            ->table("$claimsTable as ec")
            ->join("{$hrimsDb}.hrm_employee as e", 'ec.CrBy', '=', 'e.EmployeeID')
            ->join("{$hrimsDb}.hrm_employee_general as eg", 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as d", 'eg.DepartmentId', '=', 'd.id')
            ->leftJoin("$mefTable as mef", function ($join) {
                $join->on('ec.CrBy', '=', 'mef.EmployeeID')
                    ->on('ec.ClaimMonth', '=', 'mef.Month')
                    ->on('ec.ClaimYearId', '=', 'mef.YearId');
            })
            ->where('ec.ClaimYearId', $this->yearId)
            ->where('e.CompanyId', $this->companyId)
            ->whereNotIn('ec.ClaimStatus', ['Deactivate', 'Draft'])
            ->whereIn('ec.ClaimMonth', $months);

        if (!empty($filters['function_id'])) {
            $query->where('eg.EmpFunction', $filters['function_id']);
        }
        if (!empty($filters['department_id'])) {
            $query->where('eg.DepartmentId', $filters['department_id']);
        }

        $claims = $query->select([
            'ec.ExpId',
            'ec.CrBy as EmployeeID',
            'ec.ClaimMonth as Month',
            'ec.BillDate',
            'ec.CrDate',
            'ec.FilledDate',
            'ec.ClaimId',
            'ec.ClaimStatus',
            'ec.FilledTAmt',
            'ec.VerifyDate',
            'ec.ApprDate',
            'ec.FinancedDate',
            'ec.FilledBy',
            'ec.VerifyBy',
            'ec.ApprBy',
            'ec.FinancedBy',
            'mef.Fin_AppBy',
            'mef.Fin_PayBy',
            'e.EmpCode',
            DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
            'd.department_name',
            // From monthexpensefinal (for later stages)
            'mef.DateOfSubmit',
            'mef.CNIssueDate',
            'mef.RecevingDate',
            'mef.Verified_Date',
            'mef.Approved_Date',
            'mef.Finance_Date',
            'mef.Fin_PayDate',
            'mef.Fin_AppDate',
        ])->get();

        // Count claims per employee per month (for min_claims check)
        $claimCounts = [];
        foreach ($claims as $claim) {
            $key = $claim->EmployeeID . '_' . $claim->Month;
            $claimCounts[$key] = ($claimCounts[$key] ?? 0) + 1;
        }

        // Calculate ratings per individual claim
        $results = [];
        $roleSummary = [];
        $debugSkipped = 0;
        $personIds = [];

        foreach ($claims as $claim) {
            $key = $claim->EmployeeID . '_' . $claim->Month;
            $empClaimCount = $claimCounts[$key] ?? 0;
            $claimRatings = [];

            // Determine the claim's submission date for effective_date comparison
            $claimSubmitDate = !empty($claim->DateOfSubmit) && \App\Support\ZeroDate::has($claim->DateOfSubmit)
                ? $claim->DateOfSubmit
                : (!empty($claim->FilledDate) && \App\Support\ZeroDate::has($claim->FilledDate) ? $claim->FilledDate : $claim->CrDate);

            foreach ($rules as $rule) {
                // Skip rule if claim was submitted before the rule's effective date
                if (!empty($rule->effective_date) && \App\Support\ZeroDate::has($rule->effective_date)) {
                    if (strtotime($claimSubmitDate) < strtotime($rule->effective_date)) {
                        $debugSkipped++;
                        continue;
                    }
                }

                // Check min_claims threshold
                if ($rule->min_claims > 0 && $empClaimCount < $rule->min_claims) {
                    $debugSkipped++;
                    continue; // Skip — employee doesn't have enough claims
                }

                $span = $this->calculateDaysForStage($rule->stage_key, $claim, null, $isVVNR);
                // M-06: no rating when a date is missing, or the stage took longer than a year (a wrong date)
                if ($span === null || $span[0] > self::MAX_DAYS) {
                    $debugSkipped++;
                    continue;
                }
                [$days, $from, $to] = $span;

                $stars = $rule->calculateRating($days);
                $personId = $this->ratedPersonId($rule->responsible_role, $rule->stage_key, $claim);
                $claimRatings[$rule->stage_key] = [
                    'days' => $days,
                    'stars' => $stars,
                    'role' => $rule->responsible_role,
                    'stage_label' => $rule->stage_label,
                    'person_id' => $personId,
                    'person_name' => null,
                    'from' => $from,
                    'to' => $to,
                    'rule_id' => $rule->id ?? null,
                ];
                $personIds[$personId] = true;

                $role = $rule->responsible_role;
                if (!isset($roleSummary[$role])) {
                    $roleSummary[$role] = ['total_stars' => 0, 'count' => 0, 'by_star' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
                }
                $roleSummary[$role]['total_stars'] += $stars;
                $roleSummary[$role]['count']++;
                $roleSummary[$role]['by_star'][$stars]++;
            }

            if (!empty($claimRatings)) {
                $results[] = [
                    'exp_id' => $claim->ExpId,
                    'employee_id' => $claim->EmployeeID,
                    'emp_code' => $claim->EmpCode,
                    'employee_name' => $claim->employee_name,
                    'department' => $claim->department_name,
                    'month' => $claim->Month,
                    'claim_count' => $empClaimCount,
                    'ratings' => $claimRatings,
                ];
            }
        }

        // M-06: names of the rated people (verifier, approver, finance, data entry) from HRIMS
        $ids = array_values(array_filter(array_keys($personIds)));
        $names = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::connection($this->hrimsConn)->table('hrm_employee')->whereIn('EmployeeID', $chunk)
                ->get(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname']) as $e) {
                $names[(int) $e->EmployeeID] = trim(preg_replace('/\s+/', ' ', "{$e->Fname} {$e->Sname} {$e->Lname}")).($e->EmpCode ? " ({$e->EmpCode})" : '');
            }
        }
        foreach ($results as &$res) {
            foreach ($res['ratings'] as &$r) {
                $r['person_name'] = $names[$r['person_id']] ?? null;
            }
            unset($r);
        }
        unset($res);

        // Calculate averages per role
        foreach ($roleSummary as $role => &$summary) {
            $summary['avg_stars'] = $summary['count'] > 0
                ? round($summary['total_stars'] / $summary['count'], 2)
                : 0;
        }

        return [
            'claims' => $results,
            'role_summary' => $roleSummary,
            'rules' => $rules->toArray(),
            'total_claims' => count($results),
            'debug' => [
                'total_claims_queried' => $claims->count(),
                'total_skipped_stages' => $debugSkipped,
                'rules_count' => $rules->count(),
            ],
        ];
    }

    /**
     * Calculate days between stages for a specific claim
     * Now uses individual claim fields from expenseclaims + monthexpensefinal
     */
    /** @return array{0: int, 1: string, 2: string}|null days, from date, to date */
    private function calculateDaysForStage(string $stageKey, object $claim, ?object $dates, bool $isVVNR): ?array
    {
        $fromDate = null;
        $toDate = null;

        switch ($stageKey) {
            // ── Early stages (from expenseclaims table directly) ──
            case 'bill_to_upload':
                $fromDate = $claim->BillDate;
                $toDate = $claim->CrDate;
                break;
            case 'upload_to_punch':
                $fromDate = $claim->CrDate;
                $toDate = $claim->FilledDate ?? $claim->CrDate;
                break;
            case 'punch_to_submit':
                // Special: day of month when submitted
                $submitDate = $claim->DateOfSubmit ?? null;
                if (empty($submitDate) || \App\Support\ZeroDate::none($submitDate)) return null;
                return [(int) date('j', strtotime($submitDate)), date('Y-m-d', strtotime($submitDate)), date('Y-m-d', strtotime($submitDate))];

            // ── VNR stages (from monthexpensefinal) ──
            case 'submit_to_courier_post':
                $fromDate = $claim->DateOfSubmit;
                $toDate = (!empty($claim->CNIssueDate) && \App\Support\ZeroDate::has($claim->CNIssueDate))
                    ? $claim->CNIssueDate : $claim->RecevingDate;
                break;
            case 'courier_post_to_receive':
                $fromDate = (!empty($claim->CNIssueDate) && \App\Support\ZeroDate::has($claim->CNIssueDate))
                    ? $claim->CNIssueDate : $claim->DateOfSubmit;
                $toDate = $claim->RecevingDate;
                break;
            case 'receive_to_verify':
                $fromDate = $claim->RecevingDate;
                $toDate = $claim->Verified_Date;
                break;
            case 'verify_to_approve':
                $fromDate = $claim->Verified_Date;
                $toDate = $claim->Approved_Date;
                break;
            case 'approve_to_finance':
                $fromDate = $claim->Approved_Date;
                $toDate = $claim->Finance_Date ?? $claim->Fin_AppDate;
                break;
            case 'finance_to_payment':
                $fromDate = $claim->Finance_Date ?? $claim->Fin_AppDate;
                $toDate = $claim->Fin_PayDate;
                break;

            // ── VVNR-specific stages ──
            case 'submit_to_approve':
                $fromDate = $claim->DateOfSubmit;
                $toDate = $claim->Approved_Date;
                break;
            case 'payment_to_courier_post':
                $fromDate = $claim->Fin_PayDate;
                $toDate = (!empty($claim->CNIssueDate) && \App\Support\ZeroDate::has($claim->CNIssueDate))
                    ? $claim->CNIssueDate : $claim->RecevingDate;
                break;
        }

        if (empty($fromDate) || \App\Support\ZeroDate::none($fromDate) || empty($toDate) || \App\Support\ZeroDate::none($toDate)) {
            return null;
        }

        $from = strtotime($fromDate);
        $to = strtotime($toDate);
        if (!$from || !$to || $to < $from) return null; // M-06: a stage cannot end before it starts

        return [(int) round(($to - $from) / 86400), date('Y-m-d', $from), date('Y-m-d', $to)];
    }

    /**
     * Get rating summary per employee (aggregated across all their claims and all stages)
     */
    public function getEmployeeRatingSummary(array $filters = []): array
    {
        $ratingData = $this->calculateRatings($filters);
        if (isset($ratingData['error'])) return $ratingData;

        $employeeSummary = [];

        foreach ($ratingData['claims'] as $claim) {
            $empId = $claim['employee_id'];
            if (!isset($employeeSummary[$empId])) {
                $employeeSummary[$empId] = [
                    'emp_code' => $claim['emp_code'],
                    'employee_name' => $claim['employee_name'],
                    'department' => $claim['department'],
                    'total_stars' => 0,
                    'total_ratings' => 0,
                    'claims_count' => 0,
                ];
            }

            $employeeSummary[$empId]['claims_count']++;
            // Include all stage ratings for this employee's claims
            foreach ($claim['ratings'] as $rating) {
                $employeeSummary[$empId]['total_stars'] += $rating['stars'];
                $employeeSummary[$empId]['total_ratings']++;
            }
        }

        // Calculate averages
        foreach ($employeeSummary as &$emp) {
            $emp['avg_rating'] = $emp['total_ratings'] > 0
                ? round($emp['total_stars'] / $emp['total_ratings'], 1)
                : 0;
        }

        // Sort by average rating descending
        uasort($employeeSummary, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);

        return array_values($employeeSummary);
    }
}
