<?php

namespace App\Console\Commands;

use App\Helpers\DatabaseSwitcher;
use App\Services\Claims\MonthRow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MONTH-ROW-02 (15 Nov 2026): one-time backfill for months that have bills but no month record (they show as Open on the
 * Dashboard but cannot be submitted). The open-month list and the month submit create the record on their own
 * (MONTH-ROW-01); this command does it for everyone at once. Existing month records are never changed.
 *
 *   php artisan claims:ensure-month-rows --company=1 --year=8 --dry-run
 */
class EnsureMonthRows extends Command
{
    protected $signature = 'claims:ensure-month-rows {--company=1} {--year=} {--dry-run}';

    protected $description = 'Create the missing Open month record for months that have bills (never changes existing records)';

    public function handle(): int
    {
        $company = (int) $this->option('company');
        $year = (int) ($this->option('year') ?: \App\Support\CurrentYear::id());
        $conn = DatabaseSwitcher::getExpenseConnection($company);
        $missing = DB::connection($conn)->select(
            "SELECT DISTINCT c.CrBy emp, c.ClaimMonth m FROM y{$year}_expenseclaims c
             LEFT JOIN y{$year}_monthexpensefinal f ON f.EmployeeID=c.CrBy AND f.Month=c.ClaimMonth AND f.YearId={$year}
             WHERE c.ClaimYearId={$year} AND c.ClaimStatus NOT IN ('Deactivate','Draft') AND c.ClaimMonth>0 AND f.EmployeeID IS NULL
             ORDER BY c.CrBy, c.ClaimMonth");
        $this->info(count($missing).' month(s) with bills and no month record (company '.$company.', year '.$year.')');
        foreach ($missing as $r) {
            $this->line("  employee {$r->emp}, month {$r->m}");
        }
        if ($this->option('dry-run') || ! $missing) {
            return self::SUCCESS;
        }
        $made = 0;
        foreach (collect($missing)->groupBy('emp') as $emp => $rows) {
            foreach ($rows as $r) {
                $made += MonthRow::ensure($conn, $year, (int) $emp, (int) $r->m);
            }
        }
        $this->info("Created {$made} Open month record(s).");

        return self::SUCCESS;
    }
}
