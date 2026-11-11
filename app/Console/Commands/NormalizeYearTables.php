<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DB-CS-01 (10 Nov 2026), for the year-table consolidation: one character set (utf8mb4) and NULL instead of
 * zero dates in an existing year's tables.
 *   db:normalize-year --year=7            list what would change (tables not utf8mb4, date columns with zero dates)
 *   db:normalize-year --year=7 --apply    convert the tables, make the date columns NULL DEFAULT NULL and set
 *                                         0000-00-00 values to NULL (copy of each table kept as <table>_bak_<date>)
 * Run outside office hours: every table of the year is rebuilt. Code that compares dates with '0000-00-00'
 * must be checked first (the new app's checks use empty()/whereNull alongside; the old PHP pages do not).
 */
class NormalizeYearTables extends Command
{
    protected $signature = 'db:normalize-year {--year= : YearId} {--company=1} {--apply} {--no-backup}';

    protected $description = 'utf8mb4 and NULL dates for one year of claim tables';

    public static function zeroDateColumns($db, string $table): array
    {
        return $db->select("SELECT COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n, COLUMN_DEFAULT d FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE IN ('date', 'datetime', 'timestamp')
              AND (IS_NULLABLE = 'NO' OR COLUMN_DEFAULT LIKE '0000-00-00%')", [$table]);
    }

    public function handle(): int
    {
        $y = (int) $this->option('year');
        if ($y < 1) {
            $this->error('Give --year=N.');

            return self::FAILURE;
        }
        $db = DB::connection(\App\Helpers\DatabaseSwitcher::getExpenseConnection((int) $this->option('company')));
        $tables = $db->select("SELECT TABLE_NAME t, TABLE_COLLATION c, TABLE_ROWS r FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ?", ["y{$y}\\_%"]);
        $plan = [];
        $failed = [];
        foreach ($tables as $t) {
            if (str_contains($t->t, '_bak_')) {
                continue;
            }
            $cs = ! str_starts_with((string) $t->c, 'utf8mb4');
            $cols = self::zeroDateColumns($db, $t->t);
            $zero = 0;
            foreach ($cols as $c) {
                $zero += (int) ($db->selectOne("SELECT COUNT(*) n FROM `{$t->t}` WHERE `{$c->c}` < '1000-01-01' AND `{$c->c}` IS NOT NULL")->n ?? 0);
            }
            if ($cs || $cols) {
                $plan[] = [$t->t, $t->c, $cs ? 'convert to utf8mb4' : '-', count($cols), $zero, (int) $t->r];
            }
            if ($this->option('apply') && ($cs || $cols)) {
                // one table at a time; a failure is reported and the rest still run (re-run picks up what is left)
                try {
                    if (! $this->option('no-backup')) {
                        $bak = substr($t->t, 0, 40).'_bak_'.date('Ymd');
                        $db->statement("CREATE TABLE IF NOT EXISTS `{$bak}` AS SELECT * FROM `{$t->t}`");
                    }
                    $db->statement("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");
                    foreach ($cols as $c) {
                        $db->statement("ALTER TABLE `{$t->t}` MODIFY `{$c->c}` {$c->t} NULL DEFAULT NULL");
                        $db->statement("UPDATE `{$t->t}` SET `{$c->c}` = NULL WHERE `{$c->c}` < '1000-01-01'");
                    }
                    if ($cs) {
                        // wide tables (many VARCHARs) pass the 8,126-byte row limit at 4 bytes a character: store long
                        // columns off-page first (DYNAMIC), then convert
                        $db->statement("ALTER TABLE `{$t->t}` ROW_FORMAT=DYNAMIC");
                        $db->statement("ALTER TABLE `{$t->t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    $this->line("done {$t->t}");
                } catch (\Throwable $e) {
                    $failed[] = $t->t;
                    $this->error("FAILED {$t->t}: ".mb_substr($e->getMessage(), 0, 200));
                }
            }
        }
        $this->table(['Table', 'Collation', 'Character set', 'Date columns', 'Zero dates', 'Rows'], $plan);
        $this->info(count($plan).' tables '.($this->option('apply') ? 'normalised'.($failed ? ', '.count($failed).' failed: '.implode(', ', $failed) : '').'.' : 'would change. Add --apply at the consolidation.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
