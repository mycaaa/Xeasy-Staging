<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finance review flags (10 Nov 2026), the recurring form of three audit indicators. They are review lists,
 * not findings against anyone: a flag means "look at this", and the thresholds sit in config/claim_rules.php
 * (review_flags) so Finance can set them.
 *
 *  F-02 peer outliers: monthly claimed spend per employee (DA and own vehicle excluded, they are fixed or
 *       computed) and the monthly own-vehicle amount, each compared with the employee's peers in the same
 *       department and grade. Above Q3 + 3 x IQR of the peer group is an outlier month; the excess above that
 *       fence is the amount to review. Employees with enough outlier months or excess are listed.
 *  F-06 bulk back-fill: a claimant uploading many non-vehicle claims in one clock hour, most of them with old
 *       bills; and claims keyed or uploaded for others by accounts holding the Treasury or Super Admin role.
 *  F-08 round numbers: employees with markedly more round amounts (ending in 00) than their own mix of heads
 *       would give (z-score against the company-wide share per head).
 */
class FinanceReviewFlags
{
    private const DA = [19, 20];
    private const OWN_VEHICLE = 7;

    public function __construct(private string $conn, private int $yearId, private string $hrimsConn = 'hrims')
    {
    }

    private function cfg(string $key, $default)
    {
        return config("claim_rules.review_flags.{$key}", $default);
    }

    private function hrimsDb(): string
    {
        return DB::connection($this->hrimsConn)->getDatabaseName();
    }

    /** Employees: id => [code, name, department, grade]. */
    private function employees(array $ids): array
    {
        $out = [];
        $h = $this->hrimsDb();
        foreach (array_chunk(array_values(array_unique(array_filter($ids))), 1000) as $chunk) {
            $rows = DB::connection($this->hrimsConn)->table('hrm_employee as e')
                ->leftJoin('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
                ->leftJoin('core_grades as gr', 'gr.id', '=', 'g.GradeId')
                ->whereIn('e.EmployeeID', $chunk)
                ->get(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.DepartmentId', 'd.department_name', 'gr.grade_name']);
            foreach ($rows as $r) {
                $out[(int) $r->EmployeeID] = [
                    'code' => (string) $r->EmpCode,
                    'name' => trim(preg_replace('/\s+/', ' ', "{$r->Fname} {$r->Sname} {$r->Lname}")),
                    'dept_id' => (int) $r->DepartmentId,
                    'department' => $r->department_name ?: 'Not mapped',
                    'grade' => $r->grade_name ?: '-',
                ];
            }
        }

        return $out;
    }

    private function claims()
    {
        return DB::connection($this->conn)->table("y{$this->yearId}_expenseclaims as ec")
            ->where('ec.ClaimYearId', $this->yearId)
            ->whereNotIn('ec.ClaimStatus', ['Deactivate', 'Draft']);
    }

    /** Q1 and Q3 by linear interpolation. */
    private static function quartiles(array $v): array
    {
        sort($v);
        $q = function (float $p) use ($v) {
            $i = ($n = count($v)) > 1 ? $p * ($n - 1) : 0;
            $lo = (int) floor($i);
            $hi = min($lo + 1, count($v) - 1);

            return $v[$lo] + ($v[$hi] - $v[$lo]) * ($i - $lo);
        };

        return [$q(0.25), $q(0.75)];
    }

    /** F-02 */
    public function peerOutliers(): array
    {
        $minGroup = (int) $this->cfg('peer_min_group', 20);
        $minMonths = (int) $this->cfg('peer_min_outlier_months', 3);
        $minExcess = (float) $this->cfg('peer_min_excess', 10000);
        $rows = $this->claims()
            ->whereNotIn('ec.ClaimId', self::DA)
            ->groupBy('ec.CrBy', 'ec.ClaimMonth')
            ->selectRaw('ec.CrBy emp, ec.ClaimMonth m,
                SUM(CASE WHEN ec.ClaimId <> ? THEN ec.FilledTAmt ELSE 0 END) spend,
                SUM(CASE WHEN ec.ClaimId = ? THEN ec.FilledTAmt ELSE 0 END) vehicle', [self::OWN_VEHICLE, self::OWN_VEHICLE])
            ->get();
        $emps = $this->employees($rows->pluck('emp')->all());
        $metrics = ['spend' => 'Claimed spend (without DA and own vehicle)', 'vehicle' => 'Own-vehicle amount'];
        $groups = [];
        foreach ($rows as $r) {
            $e = $emps[(int) $r->emp] ?? null;
            $g = ($e['department'] ?? 'Not mapped').' | '.($e['grade'] ?? '-');
            foreach (array_keys($metrics) as $k) {
                if ((float) $r->{$k} > 0) {
                    $groups[$k][$g][] = $r;
                }
            }
        }
        $byEmp = [];
        foreach ($groups as $k => $gs) {
            foreach ($gs as $g => $list) {
                if (count($list) < $minGroup) {
                    continue;
                }
                [$q1, $q3] = self::quartiles(array_map(fn ($r) => (float) $r->{$k}, $list));
                $fence = $q3 + 3 * ($q3 - $q1);
                foreach ($list as $r) {
                    $v = (float) $r->{$k};
                    if ($v > $fence && $fence > 0) {
                        $id = (int) $r->emp;
                        $byEmp[$id]['months'][] = ['month' => (int) $r->m, 'metric' => $metrics[$k], 'amount' => $v, 'fence' => round($fence, 2), 'excess' => round($v - $fence, 2), 'group' => $g];
                        $byEmp[$id]['excess'] = ($byEmp[$id]['excess'] ?? 0) + $v - $fence;
                    }
                }
            }
        }
        $list = [];
        foreach ($byEmp as $id => $x) {
            $n = count(array_unique(array_map(fn ($m) => $m['month'], $x['months'])));
            if ($n >= $minMonths || $x['excess'] >= $minExcess) {
                $e = $emps[$id] ?? ['code' => '', 'name' => "Employee {$id}", 'department' => 'Not mapped', 'grade' => '-', 'dept_id' => 0];
                usort($x['months'], fn ($a, $b) => $b['excess'] <=> $a['excess']);
                $list[] = ['employee_id' => $id, 'code' => $e['code'], 'name' => $e['name'], 'department' => $e['department'], 'grade' => $e['grade'],
                    'outlier_months' => $n, 'excess' => round($x['excess'], 2), 'months' => $x['months'], 'unmapped' => ! $e['dept_id']];
            }
        }
        usort($list, fn ($a, $b) => $b['excess'] <=> $a['excess']);

        return ['rows' => $list, 'rule' => "Outlier month: above Q3 + 3 x IQR of the department-and-grade peer group (groups of {$minGroup}+ employee-months). Listed: {$minMonths}+ outlier months or ₹".number_format($minExcess).'+ above the fence. Staff not mapped to a department are compared only with each other.'];
    }

    /** F-06 */
    public function bulkBackfill(): array
    {
        $minClaims = (int) $this->cfg('backfill_min_claims', 15);
        $minOld = (int) $this->cfg('backfill_min_old', 10);
        $oldDays = (int) $this->cfg('backfill_old_days', 30);
        $sessions = [];
        $uploads = "y{$this->yearId}_claimuploads";
        if (Schema::connection($this->conn)->hasTable($uploads)) {
            $sub = DB::connection($this->conn)->table($uploads)->groupBy('ExpId')->selectRaw('ExpId, MIN(created_at) up');
            $rows = $this->claims()
                ->joinSub($sub, 'u', 'u.ExpId', '=', 'ec.ExpId')
                ->where('ec.ClaimId', '<>', self::OWN_VEHICLE)
                ->whereNotNull('u.up')
                ->groupBy('ec.CrBy', DB::raw("DATE_FORMAT(u.up, '%Y-%m-%d %H:00')"))
                ->selectRaw("ec.CrBy emp, DATE_FORMAT(u.up, '%Y-%m-%d %H:00') hr, COUNT(*) n,
                    SUM(ec.BillDate IS NOT NULL AND ec.BillDate > '1990-01-01' AND DATEDIFF(DATE(u.up), ec.BillDate) > ?) old,
                    SUM(ec.FilledTAmt) amt", [$oldDays])
                ->havingRaw('n >= ? AND old >= ?', [$minClaims, $minOld])
                ->get();
            $emps = $this->employees($rows->pluck('emp')->all());
            foreach ($rows as $r) {
                $e = $emps[(int) $r->emp] ?? ['code' => '', 'name' => "Employee {$r->emp}", 'department' => ''];
                $h = (int) substr($r->hr, 11, 2);
                $sessions[] = ['hour' => $r->hr, 'code' => $e['code'], 'name' => $e['name'], 'department' => $e['department'],
                    'claims' => (int) $r->n, 'old' => (int) $r->old, 'amount' => (float) $r->amt, 'night' => $h >= 23 || $h < 5];
            }
            usort($sessions, fn ($a, $b) => strcmp($b['hour'], $a['hour']));
        }

        // claims keyed for others by Treasury / Super Admin accounts
        $roles = (array) $this->cfg('backfill_watch_roles', ['Treasury', 'Super Admin']);
        $watch = DB::table('users as u')
            ->join('model_has_roles as m', function ($j) {
                $j->on('m.model_id', '=', 'u.id')->where('m.model_type', 'App\\Models\\User');
            })
            ->join('roles as r', 'r.id', '=', 'm.role_id')
            ->whereIn('r.name', $roles)->whereNotNull('u.employee_id')
            ->get(['u.employee_id', 'r.name'])->groupBy('employee_id')->map(fn ($g) => $g->pluck('name')->unique()->implode(', '));
        $keyed = [];
        if ($watch->isNotEmpty()) {
            $rows = $this->claims()->whereIn('ec.FilledBy', $watch->keys()->map(fn ($v) => (int) $v)->all())
                ->whereColumn('ec.FilledBy', '<>', 'ec.CrBy')
                ->groupBy('ec.FilledBy')
                ->selectRaw('ec.FilledBy who, COUNT(*) n, COUNT(DISTINCT ec.CrBy) claimants, SUM(ec.FilledTAmt) amt, MAX(ec.FilledDate) last')
                ->get();
            $emps = $this->employees($rows->pluck('who')->all());
            foreach ($rows as $r) {
                $e = $emps[(int) $r->who] ?? ['code' => '', 'name' => "Employee {$r->who}"];
                $keyed[] = ['code' => $e['code'], 'name' => $e['name'], 'roles' => $watch[$r->who] ?? '', 'claims' => (int) $r->n,
                    'claimants' => (int) $r->claimants, 'amount' => (float) $r->amt, 'last' => $r->last];
            }
            usort($keyed, fn ($a, $b) => $b['claims'] <=> $a['claims']);
        }

        return ['sessions' => $sessions, 'keyed' => $keyed,
            'rule' => "Session: one claimant, one clock hour, {$minClaims}+ non-vehicle claims uploaded of which {$minOld}+ have bills over {$oldDays} days old (upload time = first file of the claim). Keyed for others: claims entered by accounts with the ".implode(' or ', $roles).' role for another employee.'];
    }

    /** F-08 */
    public function roundNumbers(): array
    {
        $minLines = (int) $this->cfg('round_min_lines', 30);
        $minAmt = (float) $this->cfg('round_min_amount', 100);
        $zMin = (float) $this->cfg('round_min_z', 3);
        $ratio = (float) $this->cfg('round_min_ratio', 2);
        $base = $this->claims()->whereNotIn('ec.ClaimId', array_merge(self::DA, [self::OWN_VEHICLE]))->where('ec.FilledTAmt', '>=', $minAmt);
        $heads = (clone $base)->groupBy('ec.ClaimId')->selectRaw('ec.ClaimId h, COUNT(*) n, SUM(MOD(ec.FilledTAmt, 100) = 0) r')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->h => $r->n ? $r->r / $r->n : 0]);
        $rows = (clone $base)->groupBy('ec.CrBy', 'ec.ClaimId')
            ->selectRaw('ec.CrBy emp, ec.ClaimId h, COUNT(*) n, SUM(MOD(ec.FilledTAmt, 100) = 0) r, SUM(CASE WHEN MOD(ec.FilledTAmt, 100) = 0 THEN ec.FilledTAmt ELSE 0 END) ramt')
            ->get();
        $acc = [];
        foreach ($rows as $r) {
            $p = (float) ($heads[(int) $r->h] ?? 0);
            $a = &$acc[(int) $r->emp];
            $a['n'] = ($a['n'] ?? 0) + $r->n;
            $a['obs'] = ($a['obs'] ?? 0) + $r->r;
            $a['exp'] = ($a['exp'] ?? 0) + $r->n * $p;
            $a['var'] = ($a['var'] ?? 0) + $r->n * $p * (1 - $p);
            $a['amt'] = ($a['amt'] ?? 0) + $r->ramt;
            unset($a);
        }
        $flag = [];
        foreach ($acc as $id => $a) {
            if ($a['n'] < $minLines || $a['var'] <= 0) {
                continue;
            }
            $z = ($a['obs'] - $a['exp']) / sqrt($a['var']);
            if ($z > $zMin && $a['obs'] >= $ratio * $a['exp']) {
                $flag[$id] = ['lines' => (int) $a['n'], 'round' => (int) $a['obs'], 'expected' => round($a['exp'], 1), 'z' => round($z, 1), 'amount' => (float) $a['amt']];
            }
        }
        $emps = $this->employees(array_keys($flag));
        $list = [];
        foreach ($flag as $id => $f) {
            $e = $emps[$id] ?? ['code' => '', 'name' => "Employee {$id}", 'department' => ''];
            $list[] = $f + ['code' => $e['code'], 'name' => $e['name'], 'department' => $e['department']];
        }
        usort($list, fn ($a, $b) => $b['z'] <=> $a['z']);

        return ['rows' => $list, 'rule' => "Round amount: claimed ₹".number_format($minAmt)."+ ending in 00 (DA and own vehicle left out). Listed: {$minLines}+ lines, round amounts more than {$ratio} times what the employee's own mix of heads would give, z above {$zMin}. Lodging and activity heads are often round by tariff; the comparison is head by head."];
    }
}
