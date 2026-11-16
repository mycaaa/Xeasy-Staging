<?php

namespace App\Http\Controllers;

use App\Services\ClaimScore\ClaimScoreService;
use App\Services\ClaimScore\FinanceForensics;
use App\Support\ReportingTree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLAIM-SCORE-01..03: My Claim Score (every employee), My Team (managers), Finance & Forensics (Finance) and
 * Claim Score Settings (Super Admin). Each page receives only what its viewer may see: an employee gets their own
 * figures and their department leaderboard (names as the visibility setting allows), a manager gets the people below
 * them, Finance gets the board.
 */
class ClaimScoreController extends Controller
{
    private function svc(): ClaimScoreService
    {
        return ClaimScoreService::forSession();
    }

    private function isAdmin(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasRole('Super Admin') || $u->can('claim-score.settings'));
    }

    private function myEmployeeId(): int
    {
        return (int) (auth()->user()->employee_id ?? 0);
    }

    private function month(Request $r, ClaimScoreService $s): int
    {
        $m = (int) $r->query('month', 0);

        return in_array($m, $s->months(), true) ? $m : $s->defaultMonth();
    }

    private function monthOptions(ClaimScoreService $s): array
    {
        return array_map(fn ($m) => ['m' => $m, 'label' => ClaimScoreService::MONTH_NAMES[$m].($s->isOpen($m) ? ' (open)' : '')], array_reverse($s->months()));
    }

    private function common(ClaimScoreService $s, int $month): array
    {
        $S = $s->settings();
        $levels = array_map(fn ($l) => ['n' => $l[0], 'min' => $S['levels'][$l[0]] ?? $l[1], 'c' => $l[2], 'd' => $l[3]], ClaimScoreService::LEVELS);

        return ['month' => $month, 'monthName' => ClaimScoreService::MONTH_NAMES[$month], 'open' => $s->isOpen($month),
            'months' => $this->monthOptions($s), 'levels' => $levels, 'minClaims' => $S['min_claims'],
            'pillars' => array_map(fn ($k, $p) => ['k' => $k, 'name' => $p[0], 'w' => $S['weights'][$k] ?? 0, 'c' => $p[2]], array_keys(ClaimScoreService::PILLARS), ClaimScoreService::PILLARS)];
    }

    private function pil(array $r): array
    {
        return array_map(fn ($v) => $v === null ? null : (int) round($v), $r['pil']);
    }

    // ------------------------------------------------------------------ My Claim Score
    public function me(Request $request)
    {
        $s = $this->svc();
        $month = $this->month($request, $s);
        $emp = $this->myEmployeeId();
        $viewAs = null;
        if ($this->isAdmin() && $request->filled('emp')) {
            $emp = (int) $request->query('emp');
            $viewAs = $emp;
        }

        return view('claim-score.me', ['payload' => $this->mePayload($s, $month, $emp), 'isAdmin' => $this->isAdmin(), 'viewAs' => $viewAs,
            'people' => $this->isAdmin() ? collect($s->raw())->map(fn ($e) => ['id' => $e['p']['id'], 'nm' => $e['p']['nm'], 'c' => $e['p']['c']])->sortBy('nm')->values() : []]);
    }

    public function mePayload(ClaimScoreService $s, int $month, int $emp): array
    {
        $S = $s->settings();
        $b = $s->board($month);
        $r = $b['rows'][$emp] ?? null;
        $p = $s->raw()[$emp]['p'] ?? null;
        $out = $this->common($s, $month) + ['person' => $p ? $p + ['mgrName' => $this->name($p['mg'])] : null, 'row' => null];
        if (! $r) {
            return $out;
        }
        $pm = $s->prevMonth($month);
        $pb = $pm ? $s->board($pm) : null;
        $pr = $pb['rows'][$emp] ?? null;
        $grpIds = $b['groups'][$r['group']] ?? [];
        $grp = array_map(fn ($id) => $b['rows'][$id], $grpIds);
        $avg = $best = [];
        foreach (ClaimScoreService::PILLARS as $k => $x) {
            $v = array_values(array_filter(array_map(fn ($g) => $g['pil'][$k], $grp), fn ($z) => $z !== null));
            $avg[$k] = $v ? (int) round(array_sum($v) / count($v)) : null;
            $best[$k] = $v ? (int) round(max($v)) : null;
        }
        $named = fn ($g) => $g['id'] === $emp || $S['visibility'] === 'all' || ($S['visibility'] === 'top' && ($g['rank'] ?? 99) <= 10);
        $board = [];
        foreach ($grp as $g) {
            $prevRank = $pb['rows'][$g['id']]['rank'] ?? null;
            $board[] = ['me' => $g['id'] === $emp, 'rank' => $g['rank'], 'nm' => $named($g) ? $g['p']['nm'] : null, 'ini' => $named($g) ? $g['p']['nm'] : '?',
                'g' => $g['p']['g'], 'score' => $g['score'], 'pil' => $this->pil($g), 'mv' => $prevRank ? $prevRank - $g['rank'] : null, 'hue' => $g['id'] % 6 + 1];
        }
        $hist = [];
        foreach ($s->months() as $m) {
            if (array_search($m, $s->months(), true) > array_search($month, $s->months(), true)) {
                break;
            }
            $bb = $s->board($m);
            $mine = $bb['rows'][$emp] ?? null;
            $gg = array_map(fn ($id) => $bb['rows'][$id]['score'], $bb['groups'][$r['group']] ?? []);
            $hist[] = ['m' => ClaimScoreService::MONTH_NAMES[$m].($s->isOpen($m) ? '*' : ''), 'me' => $mine['score'] ?? null, 'avg' => $gg ? (int) round(array_sum($gg) / count($gg)) : null];
        }
        $lv = $s->level($r['score']);
        $out['row'] = ['score' => $r['score'], 'level' => $lv, 'claims' => $r['x']['n'], 'ranked' => $r['ranked'], 'streak' => $r['streak'],
            'group' => $r['group'], 'rank' => $r['rank'] ?? null, 'of' => $r['of'] ?? count($grpIds), 'drank' => $r['drank'] ?? null, 'dof' => $r['dof'] ?? null,
            'crank' => $r['crank'] ?? null, 'cof' => $b['companyCount'], 'pil' => $this->pil($r), 'avg' => $avg, 'best' => $best,
            'prevScore' => $pr['score'] ?? null, 'prevRank' => $pr['rank'] ?? null, 'prevMonth' => $pm ? ClaimScoreService::MONTH_NAMES[$pm] : null,
            'missions' => $s->missions($r), 'badges' => $s->badges($r), 'hist' => $hist, 'board' => $board, 'visibility' => $S['visibility']];

        return $out;
    }

    private array $names = [];

    private function name(?int $id): string
    {
        if (! $id) {
            return '';
        }
        if (! isset($this->names[$id])) {
            $p = DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $id)->first(['Fname', 'Sname', 'Lname']);
            $this->names[$id] = $p ? trim(preg_replace('/\s+/', ' ', "$p->Fname $p->Sname $p->Lname")) : '';
        }

        return $this->names[$id];
    }

    /** Small summary for the Dashboard card (null when the person has no claims). */
    public static function card(): ?array
    {
        try {
            $s = ClaimScoreService::forSession();
            $m = $s->defaultMonth();
            $emp = (int) (auth()->user()->employee_id ?? 0);
            $r = $s->board($m)['rows'][$emp] ?? null;
            if (! $r) {
                return null;
            }

            return ['score' => $r['score'], 'level' => $s->level($r['score']), 'month' => ClaimScoreService::MONTH_NAMES[$m],
                'drank' => $r['drank'] ?? null, 'dof' => $r['dof'] ?? null, 'dept' => $r['p']['d'], 'mission' => $s->missions($r, 1)[0] ?? null];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ------------------------------------------------------------------ My Team
    public function team(Request $request)
    {
        $s = $this->svc();
        $month = $this->month($request, $s);
        $mgr = $this->myEmployeeId();
        if ($this->isAdmin() && $request->filled('mgr')) {
            $mgr = (int) $request->query('mgr');
        }
        $scope = $request->query('scope') === 'all' ? 'all' : 'direct';

        return view('claim-score.team', ['payload' => $this->teamPayload($s, $month, $mgr, $scope), 'isAdmin' => $this->isAdmin(), 'scope' => $scope, 'mgr' => $mgr]);
    }

    public function teamPayload(ClaimScoreService $s, int $month, int $mgr, string $scope): array
    {
        $b = $s->board($month);
        $pm = $s->prevMonth($month);
        $pb = $pm ? $s->board($pm) : null;
        $ids = $s->teamIds($mgr, $scope === 'all');
        $direct = array_flip(ReportingTree::directReports($mgr, true));
        $rows = [];
        foreach ($ids as $id) {
            if (! ($r = $b['rows'][$id] ?? null)) {
                continue;
            }
            $spark = [];
            foreach ($s->months() as $m) {
                if (array_search($m, $s->months(), true) > array_search($month, $s->months(), true)) {
                    break;
                }
                $spark[] = $s->board($m)['rows'][$id]['score'] ?? null;
            }
            $prev = $pb['rows'][$id]['score'] ?? null;
            $mi = $s->missions($r, 1)[0] ?? null;
            $rows[] = ['id' => $id, 'nm' => $r['p']['nm'], 'g' => $r['p']['g'], 'd' => $r['p']['d'], 'claims' => $r['x']['n'], 'hue' => $id % 6 + 1,
                'via' => isset($direct[$id]) ? '' : $this->name($r['p']['mg']), 'ranked' => $r['ranked'], 'drank' => $r['drank'] ?? null, 'dof' => $r['dof'] ?? null,
                'crank' => $r['crank'] ?? null, 'score' => $r['score'], 'level' => $s->level($r['score']), 'pil' => $this->pil($r), 'prev' => $prev,
                'spark' => $spark, 'issue' => $mi, 'drop' => $prev !== null ? $prev - $r['score'] : 0];
        }
        usort($rows, fn ($a, $c) => $c['score'] <=> $a['score']);
        $rk = array_values(array_filter($rows, fn ($x) => $x['ranked']));
        $avg = $rk ? (int) round(array_sum(array_column($rk, 'score')) / count($rk)) : 0;
        $prevRk = array_values(array_filter(array_map(fn ($id) => $pb['rows'][$id] ?? null, $ids), fn ($x) => $x && $x['ranked']));
        $pavg = $prevRk ? (int) round(array_sum(array_column($prevRk, 'score')) / count($prevRk)) : null;
        $mgrDept = $s->raw()[$mgr]['p']['d'] ?? (DB::connection('hrims')->table('hrm_employee_general as g')->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')->where('g.EmployeeID', $mgr)->value('d.department_name') ?: '');
        $pillAvg = function (array $set) {
            $o = [];
            foreach (ClaimScoreService::PILLARS as $k => $x) {
                $v = array_values(array_filter(array_map(fn ($g) => $g['pil'][$k] ?? null, $set), fn ($z) => $z !== null));
                $o[$k] = $v ? (int) round(array_sum($v) / count($v)) : 0;
            }

            return $o;
        };
        $co = array_values(array_filter($b['rows'], fn ($x) => $x['ranked']));
        $dept = array_values(array_filter($co, fn ($x) => $x['p']['d'] === $mgrDept));
        // race: average of each manager's direct team (3+ ranked)
        $t = [];
        foreach ($co as $x) {
            if ($m = $x['p']['mg']) {
                $t[$m][] = $x['score'];
            }
        }
        $race = [];
        foreach ($t as $m => $sc) {
            if (count($sc) >= 3) {
                $race[] = ['id' => $m, 'avg' => (int) round(array_sum($sc) / count($sc)), 'n' => count($sc)];
            }
        }
        usort($race, fn ($a, $c) => $c['avg'] <=> $a['avg']);
        $pos = array_search($mgr, array_column($race, 'id'), true);
        $show = array_slice($race, 0, 12);
        if ($pos !== false && $pos >= 12) {
            $show[] = $race[$pos];
        }
        foreach ($show as &$x) {
            $x['nm'] = '#'.(array_search($x['id'], array_column($race, 'id'), true) + 1).' '.$this->name($x['id']);
            $x['me'] = $x['id'] === $mgr;
        }
        $mix = [];
        foreach (ClaimScoreService::LEVELS as $l) {
            $mix[] = count(array_filter($rk, fn ($x) => $x['level']['n'] === $l[0]));
        }

        return $this->common($s, $month) + ['mgrName' => $this->name($mgr), 'mgrDept' => $mgrDept, 'scope' => $scope, 'rows' => $rows,
            'avg' => $avg, 'avgLevel' => $s->level($avg), 'pavg' => $pavg, 'ranked' => count($rk), 'gold' => count(array_filter($rk, fn ($x) => $x['score'] >= ($s->settings()['levels']['Gold'] ?? 800))),
            'attn' => count(array_filter($rows, fn ($x) => $x['score'] < ($s->settings()['levels']['Silver'] ?? 650))), 'mix' => $mix,
            'cmp' => ['team' => $pillAvg(array_map(fn ($x) => ['pil' => $x['pil']], $rk)), 'dept' => $pillAvg($dept), 'company' => $pillAvg($co)],
            'race' => $show, 'teamRank' => $pos === false ? null : $pos + 1, 'teams' => count($race), 'cof' => $b['companyCount']];
    }

    // ------------------------------------------------------------------ Finance & Forensics
    public function finance(Request $request)
    {
        $f = new FinanceForensics((int) session('company_id', 1));
        $data = $f->data($request->boolean('refresh'));
        $cases = Schema::connection('expense')->hasTable('fin_case_reviews')
            ? DB::connection('expense')->table('fin_case_reviews as r')->where('r.company_id', (int) session('company_id', 1))->where('r.year_id', $f->yearId)
                ->leftJoin('users as u', 'u.id', '=', 'r.updated_by')->get(['r.check_key', 'r.status', 'r.remark', 'u.name as by', 'r.updated_at'])->keyBy('check_key')
            : collect();

        return view('claim-score.finance', ['fin' => $data, 'cases' => $cases]);
    }

    public function financeCase(Request $request)
    {
        $data = $request->validate(['key' => 'required|string|max:40', 'status' => 'required|in:open,checked,recovered,ok', 'remark' => 'nullable|string|max:500']);
        $co = (int) session('company_id', 1);
        $y = \App\Support\CurrentYear::id();
        DB::connection('expense')->table('fin_case_reviews')->updateOrInsert(['company_id' => $co, 'year_id' => $y, 'check_key' => $data['key']],
            ['status' => $data['status'], 'remark' => $data['remark'] ?? null, 'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);

        return response()->json(['ok' => true, 'by' => auth()->user()->name, 'at' => now()->format('d-m-Y H:i')]);
    }

    // ------------------------------------------------------------------ Settings (Super Admin)
    public function settings(Request $request)
    {
        $s = $this->svc();
        $month = $s->defaultMonth();
        $b = $s->board($month);
        $co = array_values(array_filter($b['rows'], fn ($x) => $x['ranked']));
        usort($co, fn ($a, $c) => $c['score'] <=> $a['score']);
        $mix = [];
        foreach (ClaimScoreService::LEVELS as $l) {
            $mix[$l[0]] = count(array_filter($co, fn ($x) => $s->level($x['score'])['n'] === $l[0]));
        }
        $logs = Schema::connection('expense')->hasTable('claim_score_setting_logs')
            ? DB::connection('expense')->table('claim_score_setting_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->where('l.company_id', (int) session('company_id', 1))->orderByDesc('l.id')->limit(15)->get(['l.created_at', 'l.changes', 'u.name'])
            : collect();

        return view('claim-score.settings', ['S' => $s->settings(), 'month' => ClaimScoreService::MONTH_NAMES[$month], 'mix' => $mix,
            'top' => array_slice($co, 0, 10), 'avg' => $co ? (int) round(array_sum(array_column($co, 'score')) / count($co)) : 0, 'ranked' => count($co), 'logs' => $logs, 'svc' => $s]);
    }

    public function saveSettings(Request $request)
    {
        $v = $request->validate([
            'weights' => 'required|array', 'weights.*' => 'integer|min:0|max:100',
            'params' => 'nullable|array', 'peer' => 'required|in:dept,deptband,band,company',
            'min_claims' => 'required|integer|min:0|max:100', 'visibility' => 'required|in:top,all,none',
            'dup_penalty' => 'required|integer|min:0|max:500', 'levels' => 'required|array', 'levels.*' => 'integer|min:0|max:1000',
        ]);
        if (array_sum($v['weights']) !== 100) {
            return back()->withInput()->with('error', 'The four weights must add up to 100 (now '.array_sum($v['weights']).').');
        }
        $new = ['weights' => array_intersect_key(array_map('intval', $v['weights']), ClaimScoreService::PILLARS),
            'params' => array_map(fn ($k) => isset($v['params'][$k]), array_combine(array_keys(ClaimScoreService::PARAMS), array_keys(ClaimScoreService::PARAMS))),
            'peer' => $v['peer'], 'min_claims' => (int) $v['min_claims'], 'visibility' => $v['visibility'], 'dup_penalty' => (int) $v['dup_penalty'],
            'levels' => array_intersect_key(array_map('intval', $v['levels']), ['Platinum' => 1, 'Gold' => 1, 'Silver' => 1, 'Bronze' => 1])];
        $co = (int) session('company_id', 1);
        $old = $this->svc()->settings();
        $db = DB::connection('expense');
        $db->table('claim_score_settings')->updateOrInsert(['company_id' => $co], ['settings' => json_encode($new), 'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
        $diff = [];
        foreach ($new as $k => $val) {
            if (json_encode($old[$k] ?? null) !== json_encode($val)) {
                $diff[] = $k.': '.json_encode($old[$k] ?? null).' → '.json_encode($val);
            }
        }
        $db->table('claim_score_setting_logs')->insert(['company_id' => $co, 'user_id' => auth()->id(), 'changes' => $diff ? implode("\n", $diff) : 'no change', 'created_at' => now()]);

        return redirect()->route('claim-score.settings')->with('success', 'Claim score settings saved. Scores recalculate with them from now on.');
    }
}
