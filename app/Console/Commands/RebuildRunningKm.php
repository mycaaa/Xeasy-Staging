<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * R-01 (10 Nov 2026): the old approve-to-finance action (homeajax.php) filled runningkm's two-wheeler
 * columns from the four-wheeler claims (WType=4 in both queries), so 2W km in the team/HR reports is wrong
 * for y6-y8. The code is fixed; this rebuilds the stored rows from the claims.
 *
 *   runningkm:rebuild --year=8            list the employee-months that would change
 *   runningkm:rebuild --year=8 --apply    rewrite them (a copy of the table is kept as runningkm_bak_<date>)
 *
 * Only the 2W columns are rewritten unless --with-4w is given (4W was computed correctly).
 * Same rule as before: approved running-km claims (ClaimId 7, FilledOkay, not Draft/Deactivate, ApprBy set),
 * km = TotKm, amount = ApprTAmt, per vehicle type (WType 2 / 4).
 */
class RebuildRunningKm extends Command
{
    protected $signature = 'runningkm:rebuild {--year=* : YearId(s)} {--company=1} {--with-4w : also rewrite the 4W columns (they were computed correctly)} {--apply}';

    protected $description = 'Rebuild runningkm 2W/4W km and amounts from the approved running-km claims';

    private const MON = [1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec'];

    public function handle(): int
    {
        $company = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($company);
        $db = DB::connection($conn);
        $years = array_map('intval', $this->option('year') ?: [\App\Support\CurrentYear::id($conn)]);
        if ($this->option('apply')) {
            $bak = 'runningkm_bak_'.date('Ymd_His');
            $db->statement("CREATE TABLE `{$bak}` AS SELECT * FROM runningkm");
            $this->line("Copy of runningkm kept as {$bak}.");
        }
        $changedRows = 0;
        $changedCells = 0;
        foreach ($years as $y) {
            $t = "y{$y}_expenseclaims";
            $agg = $db->select("SELECT CrBy e, ClaimMonth m, WType w, SUM(TotKm) km, SUM(ApprTAmt) amt FROM `{$t}`
                WHERE ClaimId = 7 AND WType IN (2, 4) AND ClaimYearId = ? AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                  AND ApprBy > 0 AND ApprDate > '2010-01-01' AND TotKm > 0 AND ClaimMonth BETWEEN 1 AND 12
                GROUP BY CrBy, ClaimMonth, WType", [$y]);
            $want = [];
            foreach ($agg as $a) {
                $c = self::MON[(int) $a->m].'_'.((int) $a->w).'w';
                $want[(int) $a->e][$c.'_km'] = (int) $a->km;
                $want[(int) $a->e][$c.'_amt'] = (int) $a->amt;
            }
            $have = $db->table('runningkm')->where('yearid', $y)->where('comid', $company)->get()->keyBy('empid');
            $rows = [];
            foreach ($want as $emp => $cols) {
                $cur = $have[$emp] ?? null;
                $set = [];
                foreach (self::MON as $mn) {
                    foreach ($this->option('with-4w') ? [2, 4] : [2] as $w) {
                        foreach (['km', 'amt'] as $k) {
                            $col = "{$mn}_{$w}w_{$k}";
                            $new = (int) ($cols[$col] ?? 0);
                            $old = (int) ($cur->{$col} ?? 0);
                            if ($new !== $old) {
                                $set[$col] = $new;
                            }
                        }
                    }
                }
                if (! $set) {
                    continue;
                }
                $changedRows++;
                $changedCells += count($set);
                $rows[] = [$y, $emp, $cur ? 'update' : 'insert', count($set), implode(', ', array_slice(array_map(fn ($c) => $c.' '.((int) ($cur->{$c} ?? 0)).'->'.$set[$c], array_keys($set)), 0, 4))];
                if ($this->option('apply')) {
                    if ($cur) {
                        $db->table('runningkm')->where('empid', $emp)->where('yearid', $y)->where('comid', $company)->update($set);
                    } else {
                        $db->table('runningkm')->insert(['empid' => $emp, 'comid' => $company, 'yearid' => $y] + $set);
                    }
                }
            }
            $this->table(['Year', 'Employee', 'Action', 'Cells', 'Examples (old->new)'], array_slice($rows, 0, 30));
            if (count($rows) > 30) {
                $this->line('... and '.(count($rows) - 30).' more for year '.$y);
            }
        }
        $this->info("{$changedRows} employee-years, {$changedCells} values ".($this->option('apply') ? 'rewritten.' : 'would change. Add --apply to rewrite them.'));

        return self::SUCCESS;
    }
}
