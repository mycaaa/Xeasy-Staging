<?php

namespace App\Console\Commands;

use App\Services\Payments\SeparationHold;
use App\Support\CurrentYear;
use Illuminate\Console\Command;

/** FF-01: put unpaid expense months of employees with a separation date on F&F hold. */
class SeparationHoldSweep extends Command
{
    protected $signature = 'payments:separation-hold {--company=1 : 1 = VNR, 11 = VVNR} {--year= : YearId (default: current year)}';

    protected $description = 'Hold unpaid expense months of separated employees (payable with F&F)';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $year = (int) ($this->option('year') ?: CurrentYear::id($conn));
        $r = (new SeparationHold($conn, $year, $co))->sweep();
        $this->info("company {$co} year {$year}: {$r['employees']} employees with a separation date, {$r['months']} months put on hold");

        return self::SUCCESS;
    }
}
