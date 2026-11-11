<?php

namespace App\Console\Commands;

use App\Services\TransferNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * REQ-005 & REQ-006: Transfer Notification Command
 *
 * Runs daily (via cron) to detect employee transfers/deputations
 * and send automatic notifications.
 *
 * Schedule in app/Console/Kernel.php:
 *   $schedule->command('notifications:transfer')->dailyAt('08:00');
 */
class ProcessTransferNotifications extends Command
{
    protected $signature   = 'notifications:transfer {--dry-run : Show what would be sent without actually sending}';
    protected $description = 'REQ-005/006: Process employee transfer/deputation notifications';

    public function handle(TransferNotificationService $service): int
    {
        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Processing transfer notifications...');

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN mode — notifications will NOT be sent.');
        }

        // Without the log table every detection query fails and was reported as SUCCESS.
        if (! $service->logTableExists()) {
            $this->error('Table transfer_notifications is missing: run migration 2026_11_05_000003_ensure_transfer_notifications_table.');
            Log::error('ProcessTransferNotifications: transfer_notifications table missing.');

            return self::FAILURE;
        }

        try {
            $results = $service->processNewTransfers($dryRun);

            foreach ($results as $result) {
                $this->line('  → ' . $result);
            }

            // UAT E-10: a detection that could not run (e.g. HRMS hrm_employee_transfer_history missing)
            // is a failed run, not "0 found".
            $errors = $service->errors();
            if ($errors !== []) {
                $this->error(count($errors) . ' of ' . count($results) . ' detection step(s) failed; see above.');
                Log::error('ProcessTransferNotifications: detection failed.', ['errors' => $errors]);

                return self::FAILURE;
            }

            $this->info('Done.');
            Log::info('ProcessTransferNotifications completed.', ['results' => $results]);
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed: ' . $e->getMessage());
            Log::error('ProcessTransferNotifications failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
