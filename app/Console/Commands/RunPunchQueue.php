<?php

namespace App\Console\Commands;

use App\Services\Claims\PunchQueueMover;
use Illuminate\Console\Command;

/** M-08: move punched (OCR / offline) data into submitted claims; retries failed rows. */
class RunPunchQueue extends Command
{
    protected $signature = 'punch:run-queue {--company=1} {--year=} {--id= : one queue row}';

    protected $description = 'Process tbl_extraction_queue (punch data -> claim fill data)';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $year = (int) ($this->option('year') ?: \App\Support\CurrentYear::id($conn));
        try {
            $r = (new PunchQueueMover($conn, $year))->run($this->option('id') ? (int) $this->option('id') : null);
        } catch (\Throwable $e) {
            $this->warn("company {$co}: ".$e->getMessage());

            return self::SUCCESS;
        }
        $this->info("company {$co} year {$year}: {$r['moved']} moved, {$r['failed']} failed, {$r['stuck']} need attention");
        foreach (array_slice($r['messages'], 0, 50) as $m) {
            $this->line('  '.$m);
        }

        return self::SUCCESS;
    }
}
