<?php

namespace App\Console\Commands;

use App\Services\Odometer\BackdatedOdometerRule;
use Illuminate\Console\Command;

/** ODO-BD-01: mark late-entered running-km claims for back-date approval (VNR and VVNR). */
class FlagBackdatedOdometer extends Command
{
    protected $signature = 'odometer:flag-backdated {--company=* : 1 = VNR, 11 = VVNR (default both)} {--dry-run : Count only}';

    protected $description = 'Mark running-km claims entered later than the allowed days (entry date - odometer date) as pending back-date approval';

    public function handle(BackdatedOdometerRule $rule): int
    {
        foreach (array_map('intval', $this->option('company') ?: [1, 11]) as $c) {
            try {
                $s = $rule->run($c, (bool) $this->option('dry-run'));
                $this->info(sprintf('company %d year %d: %d active department rule(s), %d claim(s) %s', $c, $s['year_id'], $s['departments'], $s['flagged'], $s['dry_run'] ? 'would be flagged' : 'flagged'));
            } catch (\Throwable $e) {
                $this->error("company {$c}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
