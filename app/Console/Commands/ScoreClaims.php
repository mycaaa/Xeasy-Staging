<?php

namespace App\Console\Commands;

use App\Services\ClaimScore\ClaimScoreService;
use Illuminate\Console\Command;

/** CLAIM-SCORE-01: save each month's claim scores (nightly, after claims:rate) into claim_scores. */
class ScoreClaims extends Command
{
    protected $signature = 'claims:score {--company=1} {--year=} {--months= : comma list, default every month of the year so far}';

    protected $description = 'Calculate and store the monthly claim score of every employee';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $svc = new ClaimScoreService($co, $this->option('year') ? (int) $this->option('year') : null);
        $months = $this->option('months') ? array_map('intval', explode(',', $this->option('months'))) : $svc->months();
        $svc->raw();
        foreach ($months as $m) {
            $n = $svc->store($m);
            $this->line("company {$co} month {$m}: {$n} scores".($svc->isOpen($m) ? ' (open)' : ''));
        }

        return self::SUCCESS;
    }
}
