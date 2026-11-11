<?php

namespace App\Console\Commands;

use App\Services\ImprestAutomationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * REQ-001: Imprest Automation Command
 *
 * Detects new joiners and grade promotions, then auto-generates
 * imprest requests for Finance team approval.
 *
 * Schedule in app/Console/Kernel.php:
 *   $schedule->command('imprest:automate')->weeklyOn(1, '09:00');  // Every Monday
 */
class ProcessImprestAutomation extends Command
{
    protected $signature   = 'imprest:automate {--dry-run : Show what would be created without inserting}';
    protected $description = 'REQ-001: Auto-generate imprest requests for new joiners and grade promotions';

    public function handle(ImprestAutomationService $service): int
    {
        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Processing imprest automation...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no records will be inserted.');
        }

        try {
            $results = $service->processAll();

            $this->line('  New Joiners : ' . $results['new_joiners']);
            $this->line('  Promotions  : ' . $results['promotions']);

            $this->info('Done.');
            Log::info('ProcessImprestAutomation completed.', $results);
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed: ' . $e->getMessage());
            Log::error('ProcessImprestAutomation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
