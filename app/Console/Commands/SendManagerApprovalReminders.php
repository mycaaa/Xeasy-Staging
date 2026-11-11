<?php

namespace App\Console\Commands;

use App\Services\Notifications\ManagerApprovalReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * APPR-REM-01: remind reporting managers of claims waiting for approval 3+ days after submission
 * (scheduled daily in bootstrap/app.php).
 */
class SendManagerApprovalReminders extends Command
{
    protected $signature = 'notifications:approval-reminder {--company=* : 1 = VNR, 11 = VVNR (default: config)} {--dry-run : List who would be reminded, send and log nothing}';

    protected $description = 'Remind reporting managers of expense months waiting for their approval (from 3 days after submission, every 3 days)';

    public function handle(ManagerApprovalReminderService $service): int
    {
        $companies = array_map('intval', $this->option('company') ?: (array) config('xeasy_notifications.approval_reminder.companies', [1]));
        foreach ($companies as $companyId) {
            try {
                $s = $service->run($companyId, (bool) $this->option('dry-run'));
                $this->info(sprintf('[%s] company %d (year %d): %d month(s) waiting, %d manager(s), %d %s, %d skipped',
                    now()->format('Y-m-d H:i:s'), $companyId, $s['year_id'], $s['months'], $s['managers'], $s['sent'], $s['dry_run'] ? 'would be sent' : 'sent', $s['skipped']));
                Log::info('notifications:approval-reminder', array_diff_key($s, ['recipients' => 1]));
            } catch (\Throwable $e) {
                $this->error("company {$companyId}: ".$e->getMessage());
                Log::error('notifications:approval-reminder company '.$companyId.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
