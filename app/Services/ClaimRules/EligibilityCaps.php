<?php

namespace App\Services\ClaimRules;

use App\Services\Claims\StageCap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Policy caps checked on the punch / verifier save (ClaimViewController::uploadClaimFiles):
 *
 *  - Lodging: amount <= per-night limit for the city tier (A/B/C, from the
 *    company city master) x nights. The per-night limits are the employee's
 *    HRIMS eligibility (hrm_employee_eligibility.Lodging_CategoryA/B/C, which
 *    HR fills from the grade master).
 *  - Annual km (2W / 4W) by grade: YTD km + this claim <= cap. HRIMS holds only
 *    the rate per km and some monthly/day limits, not an annual cap, so the caps
 *    come from config claim_rules.km_cap and the rule is off until HR gives them.
 *
 * Above a cap the save is refused unless a reason is given (override_reason or
 * the verify remark). An override is logged in claim_amount_change_logs; the
 * claim stays out of auto-verify (EligibilityValidationService flags it as
 * exceeding), so it goes to manual verification and approval.
 */
class EligibilityCaps
{
    /** @return string|null error message, or null when allowed */
    public function check(string $conn, int $yearId, int $cgId, int $claimId, int $expId, int $empId, array $form, $amount, ?string $reason): ?string
    {
        if ($cgId !== 1 || $empId <= 0) {
            return null;
        }
        if ($claimId === 1 && config('claim_rules.lodging_cap.enabled', true)) {
            return $this->lodging($conn, $yearId, $expId, $empId, $form, (float) $amount, $reason);
        }
        if ($claimId === 7 && config('claim_rules.km_cap.enabled', false)) {
            return $this->monthlyKm($conn, $yearId, $expId, $empId, $form, $reason)
                ?? $this->annualKm($conn, $yearId, $expId, $empId, $form, $reason);
        }

        return null;
    }

    /**
     * LODGING-VERIFY-01 (13 Nov 2026): the lodging limit is checked when the claim is VERIFIED, not at data
     * punching (punching has no remark field). Used by the verifier's Verify / bulk Verify: the city tier and
     * nights come from the punched lodging details; the verified amount above the limit needs the verify remark.
     */
    public function lodgingAtVerify(string $conn, int $yearId, int $expId, int $empId, int $claimId, float $amount, ?string $reason): ?string
    {
        if ($claimId !== 1 || $empId <= 0 || $expId <= 0 || ! config('claim_rules.lodging_cap.enabled', true)) {
            return null;
        }
        try {
            $g = DB::connection($conn)->table(\App\Models\G1ExpenseFillData::tableName($yearId))->where('ExpId', $expId)
                ->first(['CityCategory', 'StayDuration', 'Arrival', 'Departure']);
        } catch (\Throwable $e) {
            return null;
        }
        $tier = strtoupper(trim((string) ($g->CityCategory ?? '')));
        if (! $g || ! in_array($tier, ['A', 'B', 'C'], true)) {
            return null;
        }
        $form = ['stay_duration' => $g->StayDuration, 'arrival_datetime' => $g->Arrival, 'departure_datetime' => $g->Departure];

        return $this->lodging($conn, $yearId, $expId, $empId, $form, $amount, $reason, $tier);
    }

    public function lodging(string $conn, int $yearId, int $expId, int $empId, array $form, float $amount, ?string $reason, ?string $tierOverride = null): ?string
    {
        $tier = $tierOverride ?? $this->cityTier($conn, $form);
        if ($tier === null || $amount <= 0) {
            return null; // no tier yet: the save itself asks for the city
        }
        $perNight = $this->lodgingLimit($empId, $tier);
        if ($perNight <= 0) {
            return null; // no limit set for this employee/tier
        }
        $nights = $this->nights($form);
        $limit = $perNight * $nights;
        $tol = (float) config('claim_rules.stage_caps.tolerance', 1);
        if ($amount <= $limit + $tol) {
            return null;
        }
        $what = "Lodging Rs ".$this->fmt($amount)." is above the eligible Rs ".$this->fmt($limit)
            ." (city category {$tier}: Rs ".$this->fmt($perNight)." x {$nights} night".($nights > 1 ? 's' : '').")";

        return $this->decide($conn, $yearId, $expId, $empId, 'lodging_cap_override', $limit, $amount, $reason, $what);
    }

    public function annualKm(string $conn, int $yearId, int $expId, int $empId, array $form, ?string $reason): ?string
    {
        $km = (float) ($form['km_running'] ?? 0);
        $wheel = (int) ($form['vehicle'] ?? 0);
        if ($km <= 0 || ! in_array($wheel, [2, 4], true)) {
            return null;
        }
        $grade = $this->grade($empId);
        $caps = (array) config('claim_rules.km_cap.annual_'.$wheel.'w', []);
        $cap = (float) ($caps[$grade] ?? $caps['*'] ?? 0);
        if ($cap <= 0) {
            return null;
        }
        $used = (float) DB::connection($conn)->table("y{$yearId}_expenseclaims")
            ->where('ClaimId', 7)->where('CrBy', $empId)->where('WType', $wheel)
            ->where('ExpId', '!=', $expId)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->sum('TotKm');
        if ($used + $km <= $cap) {
            return null;
        }
        $what = "This trip ({$this->fmt($km)} km) takes the {$wheel}-wheeler km for this year to "
            .$this->fmt($used + $km)." km, above the annual limit of ".$this->fmt($cap)." km for grade {$grade}";

        return $this->decide($conn, $yearId, $expId, $empId, 'km_cap_override', $cap, $used + $km, $reason, $what);
    }

    /**
     * KM-CAP-01: monthly running km by department and grade (config claim_rules.km_cap.monthly).
     * approval "reason": above the limit the save needs a reason (logged, manual approval).
     * approval "hod": the save goes through and the claim waits for the HOD's approval (KmCapApproval).
     */
    public function monthlyKm(string $conn, int $yearId, int $expId, int $empId, array $form, ?string $reason): ?string
    {
        $km = (float) ($form['km_running'] ?? 0);
        $wheel = (int) ($form['vehicle'] ?? 0);
        $rules = (array) config('claim_rules.km_cap.monthly', []);
        if ($km <= 0 || ! in_array($wheel, [2, 4], true) || ! $rules) {
            return null;
        }
        [$dept, $grade] = $this->deptGrade($empId);
        $rule = null;
        foreach ($rules as $r) {
            $depts = array_map('mb_strtolower', (array) ($r['departments'] ?? []));
            $grades = array_map('strtoupper', (array) ($r['grades'] ?? []));
            if (in_array(mb_strtolower($dept), $depts, true) && (! $grades || in_array(strtoupper($grade), $grades, true))) {
                $rule = $r;
                break;
            }
        }
        if (! $rule) {
            return null;
        }
        $db = DB::connection($conn);
        $month = (int) $db->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->value('ClaimMonth');
        if ($month < 1) {
            $d = \App\Services\ClaimRules\ClaimSubmissionValidator::parseDate((string) ($form['bill_date'] ?? $form['BillDate'] ?? ''));
            $month = $d ? (int) substr($d, 5, 2) : 0;
        }
        if ($month < 1) {
            return null;
        }
        $used = $db->table("y{$yearId}_expenseclaims")->where('ClaimId', 7)->where('CrBy', $empId)->where('ClaimMonth', $month)
            ->where('ExpId', '!=', $expId)->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->selectRaw('WType, SUM(TotKm) AS s')->groupBy('WType')->pluck('s', 'WType');
        $four = (float) ($used[4] ?? 0) + ($wheel === 4 ? $km : 0);
        $total = (float) $used->sum() + $km;
        $over = [];
        if (! empty($rule['max_4w']) && $four > (float) $rule['max_4w']) {
            $over[] = 'four-wheeler '.$this->fmt($four).' km this month, above '.$this->fmt((float) $rule['max_4w']).' km';
        }
        if (! empty($rule['max_total']) && $total > (float) $rule['max_total']) {
            $over[] = ($rule['max_4w'] ?? null ? 'two- and four-wheeler together ' : '').$this->fmt($total).' km this month, above '.$this->fmt((float) $rule['max_total']).' km';
        }
        $limit = (float) ($rule['max_total'] ?? $rule['max_4w'] ?? 0);
        if (($rule['approval'] ?? 'reason') === 'hod') {
            if ($over) {
                KmCapApproval::request($conn, (int) session('company_id', 1), $yearId, $expId, $empId, $month, (string) $rule['name'], $total, $limit);
                $this->log($conn, $yearId, $expId, $empId, 'km_cap_hod', $limit, $total, 'hod_approval_requested', (string) $reason);
            } elseif (Schema::connection($conn)->hasTable(KmCapApproval::TABLE)) {
                DB::connection($conn)->table(KmCapApproval::TABLE)->where('year_id', $yearId)->where('exp_id', $expId)->where('status', 'pending')
                    ->update(['status' => 'not_needed', 'updated_at' => now()]);
            }

            return null; // saved; approval waits for the HOD
        }
        if (! $over) {
            return null;
        }
        $what = "Running km limit ({$rule['name']}): ".implode('; ', $over);

        return $this->decide($conn, $yearId, $expId, $empId, 'km_cap_override', $limit, $total, $reason, $what);
    }

    /** [department name, grade] from HRIMS */
    private function deptGrade(int $empId): array
    {
        try {
            $r = DB::connection('hrims')->selectOne('SELECT d.department_name d, gr.GradeValue g FROM hrm_employee_general g LEFT JOIN core_departments d ON d.id = g.DepartmentId LEFT JOIN hrm_grade gr ON gr.GradeId = g.GradeId WHERE g.EmployeeID = ?', [$empId]);

            return [trim((string) ($r->d ?? '')), trim((string) ($r->g ?? ''))];
        } catch (\Throwable $e) {
            return ['', ''];
        }
    }

    private function decide(string $conn, int $yearId, int $expId, int $empId, string $field, float $limit, float $value, ?string $reason, string $what): ?string
    {
        $mode = (string) config('claim_rules.lodging_cap.mode', 'enforce');
        $ok = StageCap::hasReason($reason);
        if ($ok || $mode === 'log') {
            $this->log($conn, $yearId, $expId, $empId, $field, $limit, $value, $ok ? 'allowed_with_reason' : 'log_only', (string) $reason);

            return null;
        }

        return $what.'. Please enter the reason in the verified remark, or reduce the verified amount.';
    }

    /** City tier A/B/C from the company city master (same rule as the save: unknown city = C). */
    public function cityTier(string $conn, array $form): ?string
    {
        $cityId = $form['city_category_select'] ?? null;
        if ($cityId === null || $cityId === '') {
            return null;
        }
        if ((string) $cityId === '0') {
            $name = trim((string) ($form['other_city_name'] ?? ''));
            if ($name === '') {
                return null;
            }
            $row = DB::connection($conn)->table('city_category')->whereRaw('LOWER(TRIM(city_name)) = ?', [mb_strtolower($name)])->orderBy('ccid')->first(['city_category']);

            return strtoupper(trim((string) ($row->city_category ?? 'C'))) ?: 'C';
        }
        $row = DB::connection($conn)->table('city_category')->where('ccid', (int) $cityId)->first();
        if ($row && ! empty($row->alias_of)) { // CITY-03: a grouped misspelling takes the correct city's tier
            $row = DB::connection($conn)->table('city_category')->where('ccid', (int) $row->alias_of)->first() ?: $row;
        }
        $t = strtoupper(trim((string) ($row->city_category ?? '')));

        return in_array($t, ['A', 'B', 'C'], true) ? $t : null;
    }

    public function lodgingLimit(int $empId, string $tier): float
    {
        try {
            $e = DB::connection('hrims')->selectOne("SELECT Lodging_CategoryA, Lodging_CategoryB, Lodging_CategoryC FROM hrm_employee_eligibility WHERE EmployeeID = ? AND Status = 'A' ORDER BY EligibilityId DESC LIMIT 1", [$empId]);
        } catch (\Throwable $ex) {
            Log::warning('EligibilityCaps: HRIMS eligibility read failed: '.$ex->getMessage());

            return 0;
        }
        $v = $e->{'Lodging_Category'.$tier} ?? null;

        return is_numeric($v) ? (float) $v : (float) preg_replace('/[^0-9.]/', '', (string) $v);
    }

    public function nights(array $form): int
    {
        $d = $form['stay_duration'] ?? null;
        if (is_numeric($d) && (float) $d > 0) {
            return max(1, (int) ceil((float) $d));
        }
        try {
            $arr = $form['arrival_datetime'] ?? $form['arrival'] ?? null;
            $dep = $form['departure_datetime'] ?? $form['departure'] ?? null;
            if (! empty($arr) && ! empty($dep)) {
                $a = \Carbon\Carbon::parse(str_replace('/', '-', $arr));
                $b = \Carbon\Carbon::parse(str_replace('/', '-', $dep));

                return max(1, (int) $a->startOfDay()->diffInDays($b->startOfDay()));
            }
        } catch (\Throwable $e) {
        }

        return 1;
    }

    private function grade(int $empId): string
    {
        try {
            $r = DB::connection('hrims')->selectOne('SELECT gr.GradeValue FROM hrm_employee_general g JOIN hrm_grade gr ON gr.GradeId = g.GradeId WHERE g.EmployeeID = ?', [$empId]);

            return (string) ($r->GradeValue ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function log(string $conn, int $yearId, int $expId, int $empId, string $field, float $limit, float $value, string $mode, string $reason): void
    {
        try {
            $c = Schema::connection($conn)->hasTable('claim_amount_change_logs') ? $conn : config('database.default');
            $cols = array_flip(Schema::connection($c)->getColumnListing('claim_amount_change_logs'));
            DB::connection($c)->table('claim_amount_change_logs')->insert(array_intersect_key([
                'year_id' => $yearId, 'exp_id' => $expId, 'employee_id' => $empId,
                'source' => 'punch_save', 'field' => $field,
                'old_value' => $limit, 'new_value' => $value,
                'remark' => mb_substr($reason, 0, 500), 'mode' => $mode,
                'changed_by' => (int) (session('employee_id') ?? auth()->id() ?? 0),
                'company_id' => (int) session('company_id', 0),
                'created_at' => now(),
            ], $cols));
        } catch (\Throwable $e) {
            Log::warning('EligibilityCaps: log failed: '.$e->getMessage());
        }
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
