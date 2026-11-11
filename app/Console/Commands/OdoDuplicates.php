<?php

namespace App\Console\Commands;

use App\Services\Claims\DuplicateOdometer;
use App\Services\Claims\MonthTotalRecalculator;
use App\Support\CurrentYear;
use Illuminate\Console\Command;

/** ODO-DUP-01: list (and with --apply drop) odometer claims saved twice for the same trip. */
class OdoDuplicates extends Command
{
    protected $signature = 'odo:duplicates {--company=1 : 1 = VNR, 11 = VVNR, 3 = Nursery} {--year= : YearId (default: current year)}
        {--employee= : EmployeeID} {--month= : claim month} {--apply : deactivate the extra claims of unpaid months and correct the month totals}
        {--report= : write every duplicate to this CSV path}';

    protected $description = 'Find odometer claims saved twice for the same trip (dry run by default)';

    public function handle(DuplicateOdometer $dup, MonthTotalRecalculator $recalc): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $year = (int) ($this->option('year') ?: CurrentYear::id($conn));
        $apply = (bool) $this->option('apply');
        $res = $dup->resolve($conn, $year, $this->option('employee') ? (int) $this->option('employee') : null,
            $this->option('month') ? (int) $this->option('month') : null, $apply, 'odo:duplicates');
        $r = collect($res['report']);
        $paid = $r->where('month_paid', 'Yes');
        $this->info("company {$co} year {$year}: {$res['groups']} trips saved more than once, {$r->count()} extra claims");
        $this->line(sprintf('  extra claimed %s | approved %s | financed %s', number_format($r->sum('claimed'), 2), number_format($r->sum('approved'), 2), number_format($r->sum('financed'), 2)));
        $this->line(sprintf('  in paid months: %d claims, financed %s (report only - Finance to check what was paid)', $paid->count(), number_format($paid->sum('financed'), 2)));
        $this->line('  '.($apply ? 'deactivated: '.$res['deactivated'] : 'would deactivate: '.$r->where('month_paid', 'No')->count()));
        if ($apply && $res['deactivated'] > 0) {
            $m = $recalc->run($co, $year, true, false);
            $this->line("  month totals corrected: {$m['rows_changed']} months ({$m['fields_changed']} fields), backup ".($m['backup'] ?? '-'));
        }
        if ($path = $this->option('report')) {
            $hr = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName($co);
            $codes = \Illuminate\Support\Facades\DB::connection($conn)->table("{$hr}.hrm_employee")->whereIn('EmployeeID', $r->pluck('employee_id')->unique())->pluck('EmpCode', 'EmployeeID');
            $f = fopen($path, 'w');
            fputcsv($f, ['Emp code', 'Bill date', 'Month', 'Kept ExpId', 'Duplicate ExpId', 'Km', 'Claimed', 'Verified', 'Approved', 'Financed', 'Month paid?', 'Action']);
            foreach ($r as $x) {
                fputcsv($f, [$codes[$x['employee_id']] ?? $x['employee_id'], $x['bill_date'], $x['month'], $x['kept_exp_id'], $x['duplicate_exp_id'], $x['km'], $x['claimed'], $x['verified'], $x['approved'], $x['financed'], $x['month_paid'], $x['action']]);
            }
            fputcsv($f, []);
            fputcsv($f, [\App\Support\ReportStamp::text()]);
            fclose($f);
            $this->line("  report: {$path}");
        }

        return self::SUCCESS;
    }
}
