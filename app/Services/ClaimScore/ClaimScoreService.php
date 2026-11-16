<?php

namespace App\Services\ClaimScore;

use App\Support\ReportingTree;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLAIM-SCORE-01: monthly claim score (0-1000) per employee from steps the employee controls - timeliness,
 * accuracy, compliance and consistency - with department and company ranks. Raw monthly figures come from the
 * claims, month totals, nightly star ratings, action log and flags; weights, parameters and ranking rules come from
 * claim_score_settings (Super Admin). Verifier cuts are not scored (they are on the Finance & Forensics board).
 */
class ClaimScoreService
{
    public const PILLARS = [
        'time' => ['Timeliness', 40, '--p1'],
        'acc' => ['Accuracy', 30, '--p2'],
        'comp' => ['Compliance', 20, '--p3'],
        'cons' => ['Consistency', 10, '--p4'],
    ];

    /** key => [pillar, name, rule, source, available] */
    public const PARAMS = [
        't1' => ['time', 'Bill to upload', 'Star rule: 5★ same day … 1★ after 7 days', 'claim_rating_results · bill_to_upload', true],
        't2' => ['time', 'Punched to month submitted', '5★ within 5 days … 1★ after 12 days', 'claim_rating_results · punch_to_submit', true],
        't3' => ['time', 'Submitted to hard copy posted', '5★ same day … 1★ after 4 days', 'claim_rating_results · submit_to_courier_post', true],
        't4' => ['time', 'Month submitted by the deadline', '100 if submitted by the last submission day, else 0', 'monthexpensefinal.DateOfSubmit + submission_settings', true],
        'a3' => ['acc', 'Returned or reverted', '−5 marks for each 1% of claims returned', 'claim_action_logs (return, revert)', true],
        'c1' => ['acc', 'Punched details accepted', '−20 marks per bill whose punched details were disputed', 'expenseclaims.FilledOkay = 2', true],
        'c2' => ['comp', 'No duplicate bills', '−25 marks per duplicate flag', 'bill_duplicate_flags', true],
        'c3' => ['comp', 'Clean odometer', '−20 marks per continuity flag', 'odo_continuity_flags', true],
        'c4' => ['comp', 'No late bills', '−10 marks per bill uploaded after the deadline', 'expenseclaims.CrDate vs deadline', true],
        'c5' => ['comp', 'Within eligibility', '−15 marks per claim above the policy limit', 'not stored per claim yet', false],
        's1' => ['cons', 'On-time streak', 'Months in a row submitted by the deadline; 6 = full marks', 'claim_scores history', true],
    ];

    public const LEVELS = [
        ['Platinum', 900, '--plat', 'On time and clean nearly every month.'],
        ['Gold', 800, '--gold', 'Reliable; a few slow uploads or small issues.'],
        ['Silver', 650, '--silver', 'Mostly fine; one area pulls the score down.'],
        ['Bronze', 500, '--bronze', 'Several habits to fix.'],
        ['Needs attention', 0, '--low', 'Late or error-prone claims; the manager is told.'],
    ];

    public const MONTH_NAMES = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private array $boards = [];

    private ?array $raw = null;

    public function __construct(public int $companyId = 1, public ?int $yearId = null)
    {
        $this->yearId = $yearId ?: \App\Support\CurrentYear::id();
    }

    public static function forSession(): self
    {
        return new self((int) session('company_id', 1));
    }

    // ---------------------------------------------------------------- settings
    public static function defaults(): array
    {
        return [
            'weights' => array_map(fn ($p) => $p[1], self::PILLARS),
            'params' => array_map(fn ($p) => $p[4], self::PARAMS),
            'peer' => 'dept', 'min_claims' => 5, 'visibility' => 'top', 'dup_penalty' => 50,
            'levels' => ['Platinum' => 900, 'Gold' => 800, 'Silver' => 650, 'Bronze' => 500],
        ];
    }

    public function settings(): array
    {
        $s = self::defaults();
        if (Schema::connection('expense')->hasTable('claim_score_settings')) {
            $row = DB::connection('expense')->table('claim_score_settings')->where('company_id', $this->companyId)->value('settings');
            if ($row && is_array($saved = json_decode($row, true))) {
                $s = array_replace_recursive($s, $saved);
            }
        }
        foreach (self::PARAMS as $k => $p) {
            if (! $p[4]) {
                $s['params'][$k] = false;
            }
        }

        return $s;
    }

    public function level(int $score): array
    {
        $th = $this->settings()['levels'];
        foreach (self::LEVELS as [$n, $min, $c, $d]) {
            $m = $th[$n] ?? $min;
            if ($score >= $m) {
                return ['n' => $n, 'min' => $m, 'c' => $c, 'd' => $d];
            }
        }

        return ['n' => 'Needs attention', 'min' => 0, 'c' => '--low', 'd' => ''];
    }

    // ---------------------------------------------------------------- months
    /** FY months up to the current one, in order (Apr..Mar). */
    public function months(): array
    {
        $cur = (int) date('n');
        $out = [];
        foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3] as $m) {
            $out[] = $m;
            if ($m === $cur) {
                break;
            }
        }

        return $out;
    }

    public function dueDate(int $m): string
    {
        $day = 15;
        try {
            $day = (int) (DB::connection('expense')->table('submission_settings')->where('company_id', $this->companyId)->where('is_active', 1)->value('last_submission_day') ?: 15);
        } catch (\Throwable $e) {
        }
        $start = $this->fyStartYear();
        $y = $m >= 4 ? $start : $start + 1;

        return date('Y-m-d', mktime(0, 0, 0, $m + 1, $day, $y));
    }

    private function fyStartYear(): int
    {
        try {
            $from = DB::connection('expense')->table('financialyear')->where('YearId', $this->yearId)->value('FromDate');
            if ($from) {
                return (int) substr((string) $from, 0, 4);
            }
        } catch (\Throwable $e) {
        }

        return (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
    }

    /** A month whose deadline has not passed yet is open (provisional). */
    public function isOpen(int $m): bool
    {
        return $this->dueDate($m) >= date('Y-m-d');
    }

    /** Latest month whose deadline has passed (the default month to show). */
    public function defaultMonth(): int
    {
        $ms = $this->months();
        foreach (array_reverse($ms) as $m) {
            if (! $this->isOpen($m)) {
                return $m;
            }
        }

        return end($ms);
    }

    // ---------------------------------------------------------------- raw data
    /** employee id => ['p' => person, 'm' => [month => metrics]]; cached for a few hours. */
    public function raw(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }
        $key = "claimscore:raw:{$this->companyId}:{$this->yearId}:".date('YmdH').intdiv((int) date('i'), 30);

        return $this->raw = Cache::remember($key, 1800, fn () => $this->buildRaw());
    }

    public function buildRaw(): array
    {
        $d = DB::connection('expense');
        $hdb = DB::connection('hrims')->getDatabaseName();
        $y = $this->yearId;
        $ec = "y{$y}_expenseclaims";
        $mf = "y{$y}_monthexpensefinal";
        $months = $this->months();
        $due = [];
        foreach ($months as $m) {
            $due[$m] = $this->dueDate($m);
        }
        $E = [];
        $lateCase = 'CASE '.implode(' ', array_map(fn ($m) => "WHEN c.ClaimMonth={$m} THEN '{$due[$m]}'", $months)).' END';
        $rows = $d->table("$ec as c")->join("$hdb.hrm_employee as e", 'e.EmployeeID', '=', 'c.CrBy')
            ->where('c.ClaimYearId', $y)->where('e.CompanyId', $this->companyId)
            ->whereNotIn('c.ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->whereIn('c.ClaimMonth', $months)
            ->selectRaw("c.CrBy e, c.ClaimMonth m, COUNT(*) n, SUM(IFNULL(c.FilledOkay,0)=2) inc, SUM(c.ClaimId<>7 AND DATE(c.CrDate) > ($lateCase)) late")
            ->groupBy('c.CrBy', 'c.ClaimMonth')->get();
        foreach ($rows as $r) {
            $E[$r->e][$r->m] = ['n' => (int) $r->n, 'inc' => (int) $r->inc, 'late' => (int) $r->late];
        }
        $map = ['bill_to_upload' => 't1', 'punch_to_submit' => 't2', 'submit_to_courier_post' => 't3'];
        foreach ($d->table('claim_rating_results')->where('company_id', $this->companyId)->where('year_id', $y)->whereIn('month', $months)
            ->whereIn('stage_key', array_keys($map))->selectRaw('employee_id e, month m, stage_key k, ROUND(AVG(stars),2) s')
            ->groupBy('employee_id', 'month', 'stage_key')->get() as $r) {
            if (isset($E[$r->e][$r->m])) {
                $E[$r->e][$r->m][$map[$r->k]] = (float) $r->s;
            }
        }
        foreach ($d->table($mf)->where('YearId', $y)->whereIn('Month', $months)->get(['EmployeeID', 'Month', 'DateOfSubmit']) as $r) {
            if (! isset($E[$r->EmployeeID][$r->Month])) {
                continue;
            }
            $sub = ($r->DateOfSubmit && $r->DateOfSubmit > '2000-01-01') ? substr($r->DateOfSubmit, 0, 10) : null;
            $E[$r->EmployeeID][$r->Month]['onTime'] = $sub ? (int) ($sub <= $due[$r->Month]) : ($due[$r->Month] >= date('Y-m-d') ? null : 0);
        }
        if (Schema::connection('expense')->hasTable('claim_action_logs')) {
            foreach ($d->table('claim_action_logs')->where('year_id', $y)->whereIn('claim_month', $months)->whereIn('action', ['return', 'revert'])
                ->selectRaw('employee_id e, claim_month m, COUNT(*) c')->groupBy('employee_id', 'claim_month')->get() as $r) {
                if (isset($E[$r->e][$r->m])) {
                    $E[$r->e][$r->m]['ret'] = (int) $r->c;
                }
            }
        }
        foreach (['bill_duplicate_flags' => 'dup', 'odo_continuity_flags' => 'odo'] as $t => $k) {
            if (! Schema::connection('expense')->hasTable($t)) {
                continue;
            }
            foreach ($d->table("$t as f")->join("$ec as c", 'c.ExpId', '=', 'f.exp_id')->where('f.year_id', $y)
                ->selectRaw('c.CrBy e, c.ClaimMonth m, COUNT(*) c')->groupBy('c.CrBy', 'c.ClaimMonth')->get() as $r) {
                if (isset($E[$r->e][$r->m])) {
                    $E[$r->e][$r->m][$k] = (int) $r->c;
                }
            }
        }
        $out = [];
        foreach (array_chunk(array_keys($E), 500) as $ch) {
            foreach (DB::connection('hrims')->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_departments as dp', 'dp.id', '=', 'g.DepartmentId')->leftJoin('core_grades as gr', 'gr.id', '=', 'g.GradeId')
                ->whereIn('e.EmployeeID', $ch)->where('e.EmpStatus', 'A')
                ->get(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.RepEmployeeID', 'dp.department_name', 'gr.grade_name']) as $p) {
                $out[(int) $p->EmployeeID] = ['p' => ['id' => (int) $p->EmployeeID, 'c' => (string) $p->EmpCode,
                    'nm' => trim(preg_replace('/\s+/', ' ', "$p->Fname $p->Sname $p->Lname")), 'd' => $p->department_name ?: 'Other',
                    'g' => $p->grade_name ?: '-', 'mg' => (int) $p->RepEmployeeID], 'm' => $E[$p->EmployeeID]];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- scoring
    private function streak(array $m, int $month): int
    {
        $order = $this->months();
        $i = array_search($month, $order, true);
        $s = 0;
        for (; $i !== false && $i >= 0; $i--) {
            if (($m[$order[$i]]['onTime'] ?? null) === 1) {
                $s++;
            } else {
                break;
            }
        }

        return $s;
    }

    private function scoreOne(array $e, int $month, array $S): ?array
    {
        $x = $e['m'][$month] ?? null;
        if (! $x || empty($x['n'])) {
            return null;
        }
        $g = fn ($k) => (float) ($x[$k] ?? 0);
        $c = fn ($v) => max(0, min(100, $v));
        $star = fn ($s) => $c(($s - 1) / 4 * 100);
        $open = $this->isOpen($month);
        $prev = $this->months()[max(0, array_search($month, $this->months(), true) - 1)];
        $streak = $this->streak($e['m'], $open ? $prev : $month);
        $mk = [
            't1' => isset($x['t1']) ? $star($x['t1']) : null, 't2' => isset($x['t2']) ? $star($x['t2']) : null,
            't3' => isset($x['t3']) ? $star($x['t3']) : null,
            't4' => ! $open && array_key_exists('onTime', $x) && $x['onTime'] !== null ? ($x['onTime'] ? 100 : 0) : null,
            'a3' => $c(100 - $g('ret') / $x['n'] * 100 * 5), 'c1' => $c(100 - $g('inc') * 20),
            'c2' => $c(100 - $g('dup') * 25), 'c3' => $c(100 - $g('odo') * 20), 'c4' => $c(100 - $g('late') * 10), 'c5' => null,
            's1' => $c(min($streak, 6) / 6 * 100),
        ];
        $pil = [];
        $tot = 0;
        $ws = 0;
        foreach (self::PILLARS as $pk => $p) {
            $v = [];
            foreach (self::PARAMS as $k => $q) {
                if ($q[0] === $pk && ! empty($S['params'][$k]) && $mk[$k] !== null) {
                    $v[] = $mk[$k];
                }
            }
            $pil[$pk] = $v ? array_sum($v) / count($v) : null;
            if ($pil[$pk] !== null) {
                $tot += $pil[$pk] * ($S['weights'][$pk] ?? 0);
                $ws += $S['weights'][$pk] ?? 0;
            }
        }
        $score = (int) max(0, round(($ws ? $tot / $ws * 10 : 0) - $g('dup') * $S['dup_penalty']));

        return ['id' => $e['p']['id'], 'p' => $e['p'], 'x' => $x, 'mk' => $mk, 'pil' => $pil, 'score' => $score,
            'ranked' => $x['n'] >= $S['min_claims'], 'streak' => $streak];
    }

    public function bandOf(string $g): string
    {
        $c = substr($g, 0, 1);
        $n = (int) substr($g, 1);

        return match ($c) {
            'J' => $n <= 2 ? 'J1–J2' : 'J3–J4',
            'M' => $n <= 2 ? 'M1–M2' : 'M3–M5',
            default => 'Senior',
        };
    }

    public function groupKey(array $p, ?array $S = null): string
    {
        $S ??= $this->settings();

        return match ($S['peer']) {
            'company' => 'Whole company',
            'band' => 'Grade '.$this->bandOf($p['g']),
            'deptband' => $p['d'].' · '.$this->bandOf($p['g']),
            default => $p['d'],
        };
    }

    /** All scored rows of a month with group, department and company ranks. */
    public function board(int $month): array
    {
        if (isset($this->boards[$month])) {
            return $this->boards[$month];
        }
        $S = $this->settings();
        $rows = [];
        foreach ($this->raw() as $e) {
            if ($r = $this->scoreOne($e, $month, $S)) {
                $r['group'] = $this->groupKey($e['p'], $S);
                $rows[$r['id']] = $r;
            }
        }
        $ranked = array_filter($rows, fn ($r) => $r['ranked']);
        uasort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);
        $i = 0;
        $groups = [];
        $depts = [];
        foreach ($ranked as $id => $r) {
            $rows[$id]['crank'] = ++$i;
            $groups[$r['group']][] = $id;
            $depts[$r['p']['d']][] = $id;
        }
        foreach ([['group', $groups, 'rank', 'of'], ['dept', $depts, 'drank', 'dof']] as [, $set, $rk, $of]) {
            foreach ($set as $ids) {
                foreach ($ids as $j => $id) {
                    $rows[$id][$rk] = $j + 1;
                    $rows[$id][$of] = count($ids);
                }
            }
        }

        return $this->boards[$month] = ['rows' => $rows, 'companyCount' => $i, 'groups' => $groups, 'depts' => $depts];
    }

    public function prevMonth(int $m): ?int
    {
        $ms = $this->months();
        $i = array_search($m, $ms, true);

        return $i ? $ms[$i - 1] : null;
    }

    // ---------------------------------------------------------------- missions & badges
    public const MISSION_TEXT = [
        't1' => ['Upload bills on the bill date', 'Your upload speed is %s★. Same-day uploads give 5★.'],
        't2' => ['Submit the month within 5 days of punching', 'Now %s★ for this step.'],
        't3' => ['Post the hard copy the day you submit', 'Courier speed is %s★; most people lose points here.'],
        't4' => ['Submit by the deadline', 'This month was not submitted by the deadline.'],
        'a3' => ['No returned claims', '%s claim(s) returned for correction.'],
        'c1' => ['Settle punching queries with the mediator', '%s bill(s) disputed after punching.'],
        'c3' => ['Keep odometer readings continuous', '%s odometer flag(s).'],
        'c4' => ['Upload every bill before the deadline', '%s bill(s) came after the deadline.'],
        's1' => ['Build an on-time streak', 'Current streak %s month(s); six in a row gives full marks.'],
    ];

    public function missions(array $r, int $limit = 3): array
    {
        $S = $this->settings();
        $wsum = 0;
        foreach (self::PILLARS as $pk => $p) {
            $wsum += $r['pil'][$pk] !== null ? ($S['weights'][$pk] ?? 0) : 0;
        }
        $wsum = $wsum ?: 1;
        $val = ['t1' => $r['x']['t1'] ?? null, 't2' => $r['x']['t2'] ?? null, 't3' => $r['x']['t3'] ?? null, 't4' => null,
            'a3' => $r['x']['ret'] ?? 0, 'c1' => $r['x']['inc'] ?? 0, 'c3' => $r['x']['odo'] ?? 0, 'c4' => $r['x']['late'] ?? 0, 's1' => $r['streak']];
        $out = [];
        foreach (self::PARAMS as $k => $q) {
            $v = $r['mk'][$k];
            if (empty($S['params'][$k]) || $v === null || $v >= 99 || ! isset(self::MISSION_TEXT[$k])) {
                continue;
            }
            $n = count(array_filter(array_keys(self::PARAMS), fn ($z) => self::PARAMS[$z][0] === $q[0] && ! empty($S['params'][$z]) && $r['mk'][$z] !== null));
            $gain = (int) round((100 - $v) / max(1, $n) * ($S['weights'][$q[0]] ?? 0) / $wsum * 10);
            if ($gain >= 3) {
                $x = $val[$k];
                $out[] = ['k' => $k, 'title' => self::MISSION_TEXT[$k][0], 'desc' => sprintf(self::MISSION_TEXT[$k][1], is_float($x) ? number_format($x, 1) : $x),
                    'gain' => $gain, 'v' => (int) round($v)];
            }
        }
        usort($out, fn ($a, $b) => $b['gain'] <=> $a['gain']);

        return array_slice($out, 0, $limit);
    }

    public function badges(array $r): array
    {
        $x = $r['x'];
        $top = ! empty($r['rank']) && $r['rank'] <= max(1, (int) ceil(($r['of'] ?? 1) * .1));
        $list = [
            ['Same-day uploader', 'Upload speed 4.6★ or better', ($x['t1'] ?? 0) >= 4.6, ($x['t1'] ?? 1) / 4.6],
            ['Quick courier', 'Hard copy speed 4.5★ or better', ($x['t3'] ?? 0) >= 4.5, ($x['t3'] ?? 1) / 4.5],
            ['Right first time', 'No claim returned, no punching dispute', empty($x['ret']) && empty($x['inc']), ($r['pil']['acc'] ?? 0) / 100],
            ['Clean record', 'No late bill, duplicate or odometer flag', empty($x['late']) && empty($x['odo']) && empty($x['dup']), ($r['pil']['comp'] ?? 0) / 100],
            ['Three on time', '3 months in a row by the deadline', $r['streak'] >= 3, $r['streak'] / 3],
            ['Six on time', '6 months in a row by the deadline', $r['streak'] >= 6, $r['streak'] / 6],
            ['Top 10%', 'In the top tenth of your department', $top, ! empty($r['rank']) ? min(1, ceil(($r['of'] ?? 1) * .1) / $r['rank']) : 0],
        ];

        return array_map(fn ($b) => ['n' => $b[0], 'd' => $b[1], 'on' => (bool) $b[2], 'pct' => (int) round(min(1, max(0, $b[3])) * 100)], $list);
    }

    // ---------------------------------------------------------------- team
    public function teamIds(int $managerId, bool $all): array
    {
        return $all ? ReportingTree::descendants($managerId, true) : ReportingTree::directReports($managerId, true);
    }

    // ---------------------------------------------------------------- snapshot
    /** Store one month's scores (nightly). Returns rows stored. */
    public function store(int $month): int
    {
        $b = $this->board($month);
        $db = DB::connection('expense');
        $now = now();
        $rows = [];
        foreach ($b['rows'] as $r) {
            $rows[] = ['company_id' => $this->companyId, 'year_id' => $this->yearId, 'month' => $month, 'employee_id' => $r['id'],
                'emp_code' => $r['p']['c'], 'employee_name' => $r['p']['nm'], 'department' => $r['p']['d'], 'grade' => $r['p']['g'],
                'manager_id' => $r['p']['mg'] ?: null, 'claims' => $r['x']['n'], 'score' => $r['score'], 'level' => $this->level($r['score'])['n'],
                'pillars' => json_encode($r['pil']), 'marks' => json_encode($r['mk']), 'figures' => json_encode($r['x']),
                'dept_rank' => $r['drank'] ?? null, 'dept_of' => $r['dof'] ?? null, 'company_rank' => $r['crank'] ?? null,
                'company_of' => $b['companyCount'], 'streak' => $r['streak'], 'provisional' => $this->isOpen($month) ? 1 : 0,
                'calculated_at' => $now];
        }
        $db->transaction(function () use ($db, $month, $rows) {
            $db->table('claim_scores')->where('company_id', $this->companyId)->where('year_id', $this->yearId)->where('month', $month)->delete();
            foreach (array_chunk($rows, 500) as $ch) {
                $db->table('claim_scores')->insert($ch);
            }
        });

        return count($rows);
    }
}
