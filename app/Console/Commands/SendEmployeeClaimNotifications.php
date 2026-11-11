<?php

namespace App\Console\Commands;

use App\Services\Notifications\EmployeeClaimNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Tracker #29: employee mails for deductions, month approval / finance and payment.
 */
class SendEmployeeClaimNotifications extends Command
{
    protected $signature = 'notifications:employee-claim-updates {--dry-run : Count the mails without sending or logging}';

    protected $description = 'Mail employees about claim deductions, month approval/finance status and confirmed payments';

    public function handle(EmployeeClaimNotificationService $service): int
    {
        $stats = $service->run((bool) $this->option('dry-run'));
        $this->info('['.now()->format('Y-m-d H:i:s').'] '.($this->option('dry-run') ? 'would send: ' : 'queued: ').json_encode($stats));
        Log::info('notifications:employee-claim-updates', $stats);

        return self::SUCCESS;
    }
}
