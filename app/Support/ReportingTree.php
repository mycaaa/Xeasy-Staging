<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * HIER-LOOP-01 (16 Nov 2026): one safe way to walk the reporting chain (HRIMS hrm_employee_general.RepEmployeeID).
 *
 * Before, each screen walked the chain itself, one query per person or per level, and some walks had no guard:
 *  - employee 461 reports to himself, so a recursive walk from him called itself for ever (HOD advance report);
 *  - a chain that loops (A reports to B, B to A - possible for a moment while HR changes reporting) did the same;
 *  - "my team" lists used WHERE RepEmployeeID = me, which for a person who reports to himself lists himself;
 *  - one query per person under a manager: a management list walked the whole company once per manager.
 *
 * Here the whole map (about 5,000 rows, two numbers and a status each) is read once per request in ONE query and
 * every walk runs in memory with a visited set and a depth limit, so it always ends and costs no further queries.
 */
class ReportingTree
{
    /** Longest chain walked (the real one is 7 levels). */
    public const MAX_DEPTH = 25;

    /** @var array<int,int>|null employee => reporting manager */
    private static ?array $rep = null;

    /** @var array<int,string> employee => EmpStatus */
    private static array $status = [];

    /** @var array<int,int[]> manager => direct reports (never the manager himself) */
    private static array $children = [];

    private static int $loadedAt = 0;

    private static function load(): void
    {
        // a long-running worker re-reads the map every 5 minutes so HR changes are picked up
        if (self::$rep !== null && time() - self::$loadedAt < 300) {
            return;
        }
        self::flush();
        self::$loadedAt = time();
        self::$rep = [];
        try {
            $rows = DB::connection('hrims')->table('hrm_employee_general as g')
                ->leftJoin('hrm_employee as e', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->get(['g.EmployeeID', 'g.RepEmployeeID', 'e.EmpStatus']);
        } catch (\Throwable $e) {
            report($e);

            return; // HRIMS unreachable: empty tree (no chain, no team)
        }
        foreach ($rows as $r) {
            $id = (int) $r->EmployeeID;
            $m = (int) $r->RepEmployeeID;
            self::$rep[$id] = $m;
            self::$status[$id] = (string) $r->EmpStatus;
            if ($m > 0 && $m !== $id) {
                self::$children[$m][] = $id;
            }
        }
    }

    /** Forget the map (tests, long-running commands after HR changes). */
    public static function flush(): void
    {
        self::$rep = null;
        self::$status = [];
        self::$children = [];
    }

    /** Reporting manager, 0 when none or the employee reports to himself. */
    public static function managerOf(int $employeeId): int
    {
        self::load();
        $m = self::$rep[$employeeId] ?? 0;

        return $m === $employeeId ? 0 : $m;
    }

    public static function isActive(int $employeeId): bool
    {
        self::load();

        return (self::$status[$employeeId] ?? '') === 'A';
    }

    /**
     * People above the employee, nearest first: manager, manager's manager ... Stops at the top, at a loop
     * (a person already met, or the employee himself) and after $levels.
     *
     * @return int[]
     */
    public static function ancestors(int $employeeId, int $levels = self::MAX_DEPTH): array
    {
        self::load();
        $out = [];
        $seen = [$employeeId => true];
        $cur = $employeeId;
        for ($i = 0; $i < min($levels, self::MAX_DEPTH); $i++) {
            $m = self::$rep[$cur] ?? 0;
            if ($m <= 0 || isset($seen[$m])) {
                break;
            }
            $out[] = $m;
            $seen[$m] = true;
            $cur = $m;
        }

        return $out;
    }

    /**
     * Direct reports (never the manager himself).
     *
     * @return int[]
     */
    public static function directReports(int $managerId, bool $activeOnly = false): array
    {
        self::load();
        $ids = self::$children[$managerId] ?? [];

        return $activeOnly ? array_values(array_filter($ids, fn ($id) => (self::$status[$id] ?? '') === 'A')) : $ids;
    }

    /**
     * Everybody below the manager, at any depth (not the manager). Breadth first, each person once, so a loop in
     * the data cannot repeat or hang the walk. $activeOnly keeps only active people in the result; the walk still
     * passes through an inactive manager so the people under him are not lost.
     *
     * @return int[]
     */
    public static function descendants(int $managerId, bool $activeOnly = false, int $levels = self::MAX_DEPTH): array
    {
        self::load();
        $seen = [$managerId => true];
        $out = [];
        $frontier = [$managerId];
        for ($d = 0; $d < min($levels, self::MAX_DEPTH) && $frontier; $d++) {
            $next = [];
            foreach ($frontier as $m) {
                foreach (self::$children[$m] ?? [] as $c) {
                    if (isset($seen[$c])) {
                        continue;
                    }
                    $seen[$c] = true;
                    $next[] = $c;
                    if (! $activeOnly || (self::$status[$c] ?? '') === 'A') {
                        $out[] = $c;
                    }
                }
            }
            $frontier = $next;
        }

        return $out;
    }

    /** True if $upper is above $employeeId in the chain (within $levels). */
    public static function isAbove(int $upper, int $employeeId, int $levels = self::MAX_DEPTH): bool
    {
        return $upper > 0 && in_array($upper, self::ancestors($employeeId, $levels), true);
    }

    /**
     * Chains that loop back on themselves (A -> B -> A), for the data check; a person reporting to himself is the
     * top of the tree and is not listed.
     *
     * @return array<int,int[]>
     */
    public static function loops(): array
    {
        self::load();
        $found = [];
        foreach (array_keys(self::$rep) as $start) {
            $pos = [$start => 0];
            $path = [$start];
            $cur = $start;
            for ($i = 1; $i <= self::MAX_DEPTH + 1; $i++) {
                $m = self::$rep[$cur] ?? 0;
                if ($m <= 0 || $m === $cur) {
                    break;
                }
                if (isset($pos[$m])) {
                    $loop = array_slice($path, $pos[$m]);
                    sort($loop);
                    $found[implode('-', $loop)] = $loop;
                    break;
                }
                $pos[$m] = $i;
                $path[] = $m;
                $cur = $m;
            }
        }

        return array_values($found);
    }
}
