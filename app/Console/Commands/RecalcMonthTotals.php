<?php

namespace App\Console\Commands;

use App\Services\Claims\MonthTotalRecalculator;
use App\Support\CurrentYear;
use Illuminate\Console\Command;

/** MONTH-TOTAL-01: month totals vs their claims (dry run unless --apply). */
class RecalcMonthTotals extends Command
{
    protected $signature = 'claims:recalc-month-totals {--company=1 : 1 = VNR, 11 = VVNR, 3 = Nursery} {--year= : YearId (default: the year containing today)}
        {--apply : write the corrections (backup + log)} {--include-paid : also correct paid months (never Fin_PayAmt; never VVNR verified on a paid month)}
        {--report= : write every mismatch to this CSV path}';

    protected $description = 'Recalculate Claim/Verified/Approved/Finance month totals from the claims (dry run by default)';

    public function handle(MonthTotalRecalculator $svc): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $year = (int) ($this->option('year') ?: CurrentYear::id($conn));
        $res = $svc->run($co, $year, (bool) $this->option('apply'), (bool) $this->option('include-paid'));
        $mm = collect($res['mismatches']);
        $this->info("company {$co} year {$year}: {$res['months']} closed months, {$mm->pluck('employee_id')->map(fn ($e, $i) => $e.'-'.$mm[$i]['month'])->unique()->count()} with a mismatch, {$mm->count()} field mismatches");
        foreach ($mm->groupBy('field') as $f => $g) {
            $this->line(sprintf('  %-16s %4d  (unpaid %d, paid %d)  diff %s', $f, $g->count(), $g->where('paid', 'No')->count(), $g->where('paid', 'Yes')->count(), number_format($g->sum('difference'), 2)));
        }
        $this->line('  rows '.($this->option('apply') ? 'changed: '.$res['rows_changed'].' ('.$res['fields_changed'].' fields), backup '.$res['backup'] : 'that would change: '.$res['rows_to_change']));
        if ($path = $this->option('report')) {
            $f = fopen($path, 'w');
            fputcsv($f, ['Emp code', 'Month', 'Field', 'Month value', 'Claims sum', 'Difference', 'Paid?', 'Paid on', 'Paid amount', 'Likely cause', 'Action']);
            foreach ($mm->sortBy([['paid', 'asc'], ['month', 'asc']]) as $x) {
                fputcsv($f, [$x['emp_code'], $x['month'], $x['field'], $x['month_value'], $x['claims_sum'], $x['difference'], $x['paid'], $x['paid_on'], $x['paid_amount'], $x['cause'], $x['action']]);
            }
            fputcsv($f, []);
            fputcsv($f, [\App\Support\ReportStamp::text()]);
            fclose($f);
            $this->line("  report: {$path}");
        }

        return self::SUCCESS;
    }
}
