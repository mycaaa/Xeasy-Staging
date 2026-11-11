<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * C-07 / D-08: the new financial year's tables were built by hand each April and drifted
 * (missing columns and indexes, ids restarting).
 *
 *   year:rollover --from=8 --to=9            show what would be created
 *   year:rollover --from=8 --to=9 --apply    create every y9_* table LIKE its y8_* table
 *
 * --schema-file writes the year tables' structure (no data) to database/schema/year_tables.sql, the
 * one schema source kept in git; --apply then also checks the new tables against it.
 * D-08: money columns of the new claim tables (FilledTAmt, VerifyTAmt, ApprTAmt, FinancedTAmt and
 * the month totals) become decimal(12,2) unless --keep-money-types is given. Existing years are not
 * touched. AUTO_INCREMENT of the new claims table starts above the old one so ExpIds do not repeat
 * across years.
 * DB-CS-01 (10 Nov 2026): the new tables are converted to utf8mb4 (one character set; --keep-charset to skip).
 * With --null-dates their DATE/DATETIME columns become NULL DEFAULT NULL instead of a 0000-00-00 default; use it
 * at the year-table consolidation, after the code that compares dates with '0000-00-00' has been checked
 * (db:normalize-year does the same for an existing year, with its data).
 */
class YearRollover extends Command
{
    protected $signature = 'year:rollover {--from= : current YearId} {--to= : new YearId} {--company=1}
        {--apply : create the tables} {--schema-file : write database/schema/year_tables.sql from the --from year}
        {--keep-money-types : keep int/float money columns}
        {--keep-charset : keep the old character set (default: the new tables are utf8mb4)}
        {--null-dates : date columns of the new tables default to NULL instead of 0000-00-00}';

    protected $description = 'Create the next financial year tables from the current year (structure only)';

    private const MONEY = [
        'expenseclaims' => ['FilledTAmt', 'VerifyTAmt', 'ApprTAmt', 'FinancedTAmt'],
        'monthexpensefinal' => ['Claim_Amount', 'Verified_Amount', 'Approved_Amount', 'Finance_Amount', 'Total_Claim'],
    ];

    public function handle(): int
    {
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection((int) $this->option('company'));
        $db = DB::connection($conn);
        if ($from < 1 || $to !== $from + 1) {
            $this->error('Give --from=N and --to=N+1.');

            return self::FAILURE;
        }
        $tables = collect($db->select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0])
            ->filter(fn ($t) => str_starts_with($t, "y{$from}_"))->values();
        if ($tables->isEmpty()) {
            $this->error("No y{$from}_ tables.");

            return self::FAILURE;
        }

        if ($this->option('schema-file')) {
            $sql = "-- Year-table structure taken from y{$from}_ on ".now()->toDateString()." (C-07). Replace y{$from}_ with the new year.\n\n";
            foreach ($tables as $t) {
                $create = (array) $db->selectOne("SHOW CREATE TABLE `{$t}`");
                $sql .= preg_replace('/ AUTO_INCREMENT=\d+/', '', end($create)).";\n\n";
            }
            File::ensureDirectoryExists(database_path('schema'));
            File::put(database_path('schema/year_tables.sql'), $sql);
            $this->info('Wrote database/schema/year_tables.sql ('.$tables->count().' tables).');
        }

        $plan = [];
        foreach ($tables as $t) {
            $new = "y{$to}_".substr($t, strlen("y{$from}_"));
            $exists = (bool) $db->selectOne('SELECT 1 x FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$new]);
            $plan[] = [$t, $new, $exists ? 'exists - skipped' : 'create'];
        }
        $this->table(['From', 'New', 'Action'], $plan);
        if (! $this->option('apply')) {
            $this->line('Dry run. Add --apply to create the tables.');

            return self::SUCCESS;
        }

        foreach ($plan as [$t, $new, $act]) {
            if ($act !== 'create') {
                continue;
            }
            $db->statement("CREATE TABLE `{$new}` LIKE `{$t}`");
            $suffix = substr($t, strlen("y{$from}_"));
            if (! $this->option('keep-money-types') && isset(self::MONEY[$suffix])) {
                foreach (self::MONEY[$suffix] as $col) {
                    $c = $db->selectOne('SELECT DATA_TYPE d, IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$new, $col]);
                    if ($c && in_array(strtolower($c->d), ['int', 'bigint', 'float', 'double', 'varchar', 'smallint', 'mediumint'], true)) {
                        $db->statement("ALTER TABLE `{$new}` MODIFY `{$col}` DECIMAL(12,2) ".($c->n === 'YES' ? 'NULL' : 'NOT NULL DEFAULT 0'));
                    }
                }
            }
            if (! $this->option('keep-charset')) {
                $db->statement("ALTER TABLE `{$new}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            if ($this->option('null-dates')) {
                foreach (\App\Console\Commands\NormalizeYearTables::zeroDateColumns($db, $new) as $c) {
                    $db->statement("ALTER TABLE `{$new}` MODIFY `{$c->c}` {$c->t} NULL DEFAULT NULL");
                }
            }
            if ($suffix === 'expenseclaims') {
                $next = (int) ($db->selectOne("SELECT COALESCE(MAX(ExpId), 0) + 1000 n FROM `{$t}`")->n ?? 1);
                $db->statement("ALTER TABLE `{$new}` AUTO_INCREMENT = {$next}");
            }
            $this->line("created {$new}");
        }
        $this->info('Done. Add the financialyear row and claim limits for the new year as usual.');

        return self::SUCCESS;
    }
}
