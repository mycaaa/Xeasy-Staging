<?php

namespace App\Console\Commands;

use App\Http\Controllers\admin\VvnrEmailNotificationController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VVNR-HIER-01 (Finance mail 19 Oct): monthly hierarchy-wise summary to each reporting manager's
 * manager (total employees, submission / approval / hard-copy pending, by manager). The summary and
 * the mail already existed as a screen action; this runs it every month for the previous month.
 */
class SendVvnrHierarchySummary extends Command
{
    protected $signature = 'notifications:vvnr-hierarchy-summary {--month= : 1-12, default previous month} {--dry-run : Count recipients, send nothing}';

    protected $description = 'Monthly VVNR hierarchy-wise expense summary to managers\' managers';

    public function handle(): int
    {
        $month = (int) ($this->option('month') ?: now('Asia/Kolkata')->subMonthNoOverflow()->month);
        $request = Request::create('/vvnr/hierarchy-summary', 'POST', ['month' => $month, 'dry_run' => $this->option('dry-run') ? 1 : 0]);
        $response = app(VvnrEmailNotificationController::class)->dispatchHierarchyMonthlySummary($request);
        $body = json_decode($response->getContent(), true) ?: [];
        $this->info('['.now()->format('Y-m-d H:i:s').'] VVNR hierarchy summary, month '.$month.': '.json_encode($body['data'] ?? $body));
        Log::info('notifications:vvnr-hierarchy-summary', ['month' => $month] + ($body['data'] ?? []));

        return self::SUCCESS;
    }
}
