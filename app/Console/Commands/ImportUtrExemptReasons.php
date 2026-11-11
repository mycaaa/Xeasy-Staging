<?php

namespace App\Console\Commands;

use App\Services\Payments\UtrExemption;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * UTR-02 one-off: read Finance's returned sheet (tab-separated: Emp ID, ..., Claim month "May 2026",
 * ..., Remark) and set utr_exempt_reason on the VNR month records. Dry run unless --apply.
 *   php artisan payments:import-utr-reasons /path/sheet.txt            (dry run)
 *   php artisan payments:import-utr-reasons /path/sheet.txt --apply
 */
class ImportUtrExemptReasons extends Command
{
    protected $signature = 'payments:import-utr-reasons {file} {--apply} {--connection=expense} {--year=8 : YearId (8 = FY2026-27 on VNR)}';

    protected $description = 'UTR-02: set no-UTR reasons from Finance\'s sheet (dry run unless --apply)';

    private const REMARKS = [
        'payment hold due to payable with f&f' => 'ff_hold',
        'payment not made, da claims only' => 'da_payroll',
        'payment not made, adjusted with advance' => 'advance_adjusted',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Cannot read {$file}");

            return self::FAILURE;
        }
        $yearId = (int) $this->option('year');
        $svc = new UtrExemption((string) $this->option('connection'), $yearId);
        if (! $svc->ready()) {
            $this->error("{$svc->table()} has no utr_exempt_reason column; run migration 000015 first.");

            return self::FAILURE;
        }
        [$fyStart, $fyEnd] = $this->fyRange($yearId);

        $fh = fopen($file, 'r');
        $head = fgetcsv($fh, 0, "\t");
        $head = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $head);
        $ix = array_flip($head);
        foreach (['Emp ID', 'Claim month', 'Remark'] as $need) {
            if (! isset($ix[$need])) {
                $this->error("Column '{$need}' not found in the sheet header.");

                return self::FAILURE;
            }
        }

        $apply = (bool) $this->option('apply');
        $count = ['set' => [], 'unchanged' => 0, 'no_remark' => 0, 'unknown_remark' => 0, 'not_found' => 0, 'other_year' => 0, 'blocked' => 0];
        $line = 1;
        while (($row = fgetcsv($fh, 0, "\t")) !== false) {
            $line++;
            $emp = trim((string) ($row[$ix['Emp ID']] ?? ''));
            $monthTxt = trim((string) ($row[$ix['Claim month']] ?? ''));
            if (! ctype_digit($emp) || $monthTxt === '') {
                continue; // blank, total or note lines
            }
            $remark = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row[$ix['Remark']] ?? ''))));
            if ($remark === '') {
                $count['no_remark']++;
                $this->line("  line {$line}: {$emp} {$monthTxt} - no remark, left as is");

                continue;
            }
            $reason = self::REMARKS[$remark] ?? null;
            if (! $reason) {
                $count['unknown_remark']++;
                $this->warn("  line {$line}: {$emp} {$monthTxt} - remark not recognised: {$remark}");

                continue;
            }
            try {
                $d = Carbon::createFromFormat('!M Y', $monthTxt);
            } catch (\Throwable $e) {
                $count['unknown_remark']++;
                $this->warn("  line {$line}: {$emp} - month not understood: {$monthTxt}");

                continue;
            }
            if ($d->lt($fyStart) || $d->gt($fyEnd)) {
                $count['other_year']++;
                $this->warn("  line {$line}: {$emp} {$monthTxt} - outside YearId {$yearId}");

                continue;
            }
            $m = (int) $d->month;
            $rec = $svc->find((int) $emp, $m);
            if (! $rec) {
                $count['not_found']++;
                $this->warn("  line {$line}: {$emp} {$monthTxt} - no month record");

                continue;
            }
            if ($rec->utr_exempt_reason === $reason) {
                $count['unchanged']++;

                continue;
            }
            if ($rec->utr_exempt_reason === 'ff_hold' && $rec->ff_utr) {
                $count['blocked']++;
                $this->warn("  line {$line}: {$emp} {$monthTxt} - F&F already linked, not changed");

                continue;
            }
            $count['set'][$reason] = ($count['set'][$reason] ?? 0) + 1;
            $this->line(sprintf('  %s %s -> %s%s', $emp, $d->format('M Y'), $reason, $rec->utr_exempt_reason ? " (was {$rec->utr_exempt_reason})" : ''));
            if ($apply) {
                $svc->setReason((int) $emp, $m, $reason, null);
            }
        }
        fclose($fh);

        $this->newLine();
        $this->info(($apply ? 'Applied' : 'Dry run (use --apply to write)').' on '.$this->option('connection').'.'.$svc->table());
        foreach ($count['set'] as $r => $n) {
            $this->line("  {$r}: {$n}");
        }
        $this->line('  total set: '.array_sum($count['set']));
        foreach (['unchanged', 'no_remark', 'unknown_remark', 'not_found', 'other_year', 'blocked'] as $k) {
            $this->line("  {$k}: {$count[$k]}");
        }

        return self::SUCCESS;
    }

    private function fyRange(int $yearId): array
    {
        $y = \Illuminate\Support\Facades\DB::connection((string) $this->option('connection'))
            ->table('financialyear')->where('YearId', $yearId)->first();
        $from = $y->FromDate ?? $y->from_date ?? null;
        $to = $y->ToDate ?? $y->to_date ?? null;
        if ($from && $to) {
            return [Carbon::parse($from)->startOfMonth(), Carbon::parse($to)->endOfMonth()];
        }

        return [Carbon::create(2026, 4, 1), Carbon::create(2027, 3, 31)];
    }
}
