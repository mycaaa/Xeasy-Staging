<?php

namespace App\Console\Commands;

use App\Services\Reports\VehicleRateAlerts;
use Illuminate\Console\Command;

/** VEH-RATE-ALERT-01: mail HR about active employees with a zero 2W / 4W rate; repeats every 3 days until fixed. */
class VehicleRateAlertMail extends Command
{
    protected $signature = 'vehicle-rates:alert {--company=1} {--dry-run}';

    protected $description = 'Mail HR (Super Admin in Cc) for each active employee whose vehicle rate is zero, every 3 days until it is entered';

    public function handle(): int
    {
        $r = (new VehicleRateAlerts())->run((int) $this->option('company'), (bool) $this->option('dry-run'));
        $this->info(($this->option('dry-run') ? 'DRY RUN ' : '')."first {$r['first']}, reminders {$r['reminder']}, waiting {$r['waiting']}, closed {$r['resolved']}");
        foreach ($r['mails'] as $m) {
            $this->line('  '.implode(' | ', $m));
        }

        return self::SUCCESS;
    }
}
