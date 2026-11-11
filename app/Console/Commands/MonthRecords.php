<?php

namespace App\Console\Commands;

use App\Services\Claims\MonthRecord;
use Illuminate\Console\Command;

/**
 * MONTH-REC-01: list (and with --apply add) the missing employee-month rows for claims of a year.
 *   claims:month-records --year=8            list
 *   claims:month-records --year=8 --apply    add the rows (Status 'Open')
 * Bills dated outside the year are listed but get no row (they belong to another year).
 */
class MonthRecords extends Command
{
    protected $signature = 'claims:month-records {--year= : YearId, default the current year} {--company=1} {--apply}';

    protected $description = 'Claims without their employee-month row: list, or add the rows';

    public function handle(): int
    {
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection((int) $this->option('company'));
        $year = (int) ($this->option('year') ?: \App\Support\CurrentYear::id($conn));
        $rows = MonthRecord::missing($conn, $year);
        $out = [];
        $added = 0;
        foreach ($rows as $r) {
            $in = MonthRecord::inYear($conn, $year, (string) $r->BillDate);
            $act = $in ? ($this->option('apply') ? MonthRecord::ensureRow($conn, $year, (int) $r->CrBy, (int) $r->ClaimMonth) : 'would add') : 'bill outside the year - no row';
            $added += $act === 'created' ? 1 : 0;
            $out[] = [$r->ExpId, $r->CrBy, $r->ClaimMonth, $r->BillDate, $r->ClaimStatus, $r->FilledBy, $act];
        }
        $this->table(['ExpId', 'Employee', 'Month', 'Bill date', 'Status', 'Filled by', 'Action'], $out);
        $this->info(count($out).' claims without a month row'.($this->option('apply') ? "; {$added} month rows added." : '. Add --apply to add the rows.'));

        return self::SUCCESS;
    }
}
