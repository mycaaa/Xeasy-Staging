<?php

namespace App\Console\Commands;

use App\Services\Notifications\CourierReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Tracker #3: daily courier-details reminder (scheduled in bootstrap/app.php).
 */
class SendCourierDetailReminders extends Command
{
    protected $signature = 'notifications:courier-reminder {--dry-run : Count who would be reminded without sending or logging}';

    protected $description = 'Daily reminder to enter courier (hard-copy dispatch) details, from the day after month submission until entered';

    public function handle(CourierReminderService $service): int
    {
        $stats = $service->run((bool) $this->option('dry-run'));
        $this->info(sprintf('[%s] courier reminders: %d pending month(s), %d %s, %d skipped',
            now()->format('Y-m-d H:i:s'), $stats['candidates'], $stats['sent'], $stats['dry_run'] ? 'would be sent' : 'sent', $stats['skipped']));
        Log::info('notifications:courier-reminder', $stats);

        return self::SUCCESS;
    }
}
