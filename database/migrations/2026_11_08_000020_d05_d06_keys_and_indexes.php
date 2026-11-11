<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * D-05 / D-06 (8 Nov 2026), on the expense connection it runs on, for the current and previous year:
 *  - D-05: unique key (EmployeeID, Month, YearId) on y{year}_monthexpensefinal, only when the table
 *    has no duplicate rows for it (a table with duplicates is left as it is and logged);
 *  - D-06: dashboard indexes on y{year}_expenseclaims: (VerifyBy, ClaimStatus), (ApprBy, ClaimStatus),
 *    (FilledBy, FilledDate), (CrBy, ClaimMonth), when not already there;
 *  - D-06: exact duplicate indexes (same columns in the same order as another index of the table,
 *    or as the primary key) are dropped from every table of the schema; unique and primary keys stay.
 * Each statement is checked first, so the migration can run again. Run outside office hours on live:
 * the ALTERs rebuild the indexes of the year tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $years = collect($db->select("SHOW TABLES LIKE 'y%\\_expenseclaims'"))->map(fn ($r) => (int) preg_replace('/\D/', '', explode('_', array_values((array) $r)[0])[0]))->filter()->sort()->values();
        $recent = $years->slice(-2)->all();

        foreach ($recent as $y) {
            $m = "y{$y}_monthexpensefinal";
            if ($this->tableExists($db, $m) && ! $this->hasIndexOn($db, $m, ['EmployeeID', 'Month', 'YearId'], true)) {
                $dups = (int) ($db->selectOne("SELECT COUNT(*) n FROM (SELECT 1 FROM {$m} GROUP BY EmployeeID, Month, YearId HAVING COUNT(*) > 1) x")->n ?? 0);
                if ($dups === 0) {
                    $db->statement("ALTER TABLE {$m} ADD UNIQUE KEY uq_emp_month_year (EmployeeID, Month, YearId)");
                } else {
                    Log::warning("D-05: {$m} has {$dups} duplicate employee-months; unique key not added");
                }
            }
            $c = "y{$y}_expenseclaims";
            foreach ([['VerifyBy', 'ClaimStatus'], ['ApprBy', 'ClaimStatus'], ['FilledBy', 'FilledDate'], ['CrBy', 'ClaimMonth']] as $cols) {
                if ($this->tableExists($db, $c) && $this->columnsExist($db, $c, $cols) && ! $this->hasIndexOn($db, $c, $cols)) {
                    $db->statement("ALTER TABLE {$c} ADD INDEX idx_".strtolower(implode('_', $cols)).' ('.implode(', ', $cols).')');
                }
            }
        }

        // exact duplicate indexes
        $rows = $db->select("SELECT TABLE_NAME t, INDEX_NAME i, NON_UNIQUE nu, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE");
        foreach (collect($rows)->groupBy('t') as $table => $idx) {
            foreach ($idx->groupBy('cols') as $cols => $same) {
                if ($same->count() < 2) {
                    continue;
                }
                // keep PRIMARY, then a unique index, then the first by name
                $keep = $same->sortBy(fn ($r) => [$r->i === 'PRIMARY' ? 0 : ((int) $r->nu === 0 ? 1 : 2), $r->i])->first();
                foreach ($same as $r) {
                    if ($r->i === $keep->i || $r->i === 'PRIMARY' || (int) $r->nu === 0) {
                        continue;
                    }
                    try {
                        $db->statement("ALTER TABLE `{$table}` DROP INDEX `{$r->i}`");
                        Log::info("D-06: dropped duplicate index {$table}.{$r->i} ({$cols}), kept {$keep->i}");
                    } catch (\Throwable $e) {
                        Log::warning("D-06: could not drop {$table}.{$r->i}: ".$e->getMessage());
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // indexes: not restored
    }

    private function tableExists($db, string $t): bool
    {
        return (bool) $db->selectOne('SELECT 1 x FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t]);
    }

    private function columnsExist($db, string $t, array $cols): bool
    {
        $have = collect($db->select('SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t]))->pluck('c')->map(fn ($c) => strtolower($c))->all();

        return ! array_diff(array_map('strtolower', $cols), $have);
    }

    private function hasIndexOn($db, string $t, array $cols, bool $uniqueOnly = false): bool
    {
        $rows = $db->select('SELECT INDEX_NAME i, NON_UNIQUE nu, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? GROUP BY INDEX_NAME, NON_UNIQUE', [$t]);
        $want = strtolower(implode(',', $cols));
        foreach ($rows as $r) {
            if ($uniqueOnly && (int) $r->nu !== 0) {
                continue;
            }
            // an index that starts with the same columns serves the same queries
            if (str_starts_with(strtolower($r->cols).',', $want.',')) {
                return true;
            }
        }

        return false;
    }
};
