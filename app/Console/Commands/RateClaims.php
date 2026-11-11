<?php

namespace App\Console\Commands;

use App\Services\Reports\ClaimRatingService;
use Illuminate\Console\Command;

/**
 * M-06: the claim rating (SLA) results stopped on 27 Jun 2026 and rated no individual. This run stores the
 * ratings for the year's months so far, each stage rated against the person responsible for it (claimant,
 * data entry, verifier, approver, finance); stages with a missing or wrong date (over a year) are left out.
 */
class RateClaims extends Command
{
    protected $signature = 'claims:rate {--company=1} {--year=} {--months= : comma list, default every month of the year so far}';

    protected $description = 'Store claim stage ratings with the rated person (M-06)';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $year = (int) ($this->option('year') ?: \App\Support\CurrentYear::id($conn));
        $months = $this->option('months')
            ? array_map('intval', explode(',', (string) $this->option('months')))
            : $this->monthsSoFar();
        $hrims = 'hrims'; // both companies read employees from HRIMS
        try {
            [$claims, $stored] = (new ClaimRatingService($conn, $year, $co, $hrims))->storeRatings($months);
        } catch (\Throwable $e) {
            $this->warn("company {$co}: ".$e->getMessage());

            return self::SUCCESS;
        }
        $this->info("company {$co} year {$year} months ".implode(',', $months).": {$stored} ratings for {$claims} claims");

        return self::SUCCESS;
    }

    /** Financial-year months from April up to the current month. */
    private function monthsSoFar(): array
    {
        $out = [];
        $m = 4;
        $now = (int) now()->month;
        while (true) {
            $out[] = $m;
            if ($m === $now) {
                break;
            }
            $m = $m === 12 ? 1 : $m + 1;
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }
}
