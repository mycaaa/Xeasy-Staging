<?php

namespace App\Console\Commands;

use App\Services\ResignedSubmissionEmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendResignedSubmissionReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:resigned-submission
                            {--employee_id= : Send reminder to a specific employee ID}
                            {--company_id= : Restrict to a specific company ID (1=VNR Seeds, 2=Group Concern, 3=Nursery, 11=VVNR)}
                            {--all-resigned : Process all resigned employees regardless of whether they have pending claims}
                            {--limit= : Limit the number of employees processed}
                            {--test-email= : Override recipient email address (useful for testing cron)}
                            {--force : Run even if automation is disabled in settings}
                            {--dry-run : Simulate execution without sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send final expense submission email reminders to resigned employees with pending claims';

    /**
     * Execute the console command.
     */
    public function handle(ResignedSubmissionEmailService $service): int
    {
        $employeeId    = $this->option('employee_id') ? (int) $this->option('employee_id') : null;
        $companyId     = $this->option('company_id')  ? (int) $this->option('company_id')  : null;
        $isAllResigned = (bool) $this->option('all-resigned');
        $limit         = $this->option('limit')       ? (int) $this->option('limit')       : null;
        $testEmail     = $this->option('test-email')  ?: null;
        $isForce       = (bool) $this->option('force');
        $isDryRun      = (bool) $this->option('dry-run');

        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Starting resigned submission reminders cron...');

        $settings = $service->getSettings();

        if (!$settings->is_active && !$isForce) {
            $this->warn('Resigned reminders automation is currently DISABLED in settings. Use --force to override.');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn('DRY RUN active — no emails will actually be dispatched and reminder counts will not increment.');
        }

        if ($testEmail) {
            $this->warn("Test email override active — emails will be routed to {$testEmail}");
        }

        // Target companies filter
        $targetCompanies = $companyId ? [$companyId] : ($settings->target_companies ?: []);

        // By default, strictly process only employees who have claims pending submission!
        $employees = $service->getEligibleResignedEmployees(
            $employeeId,
            null,
            null,
            !empty($targetCompanies) ? $targetCompanies : null,
            onlyPendingClaims: !$isAllResigned
        );

        if ($limit && $limit > 0) {
            $employees = $employees->take($limit);
        }

        if ($employees->isEmpty()) {
            $this->info('No eligible resigned employees with pending claims found.');
            return self::SUCCESS;
        }

        $scopeMsg = $isAllResigned ? 'all eligible resigned employees' : 'resigned employees WITH PENDING CLAIMS FOR SUBMISSION';
        $this->line("Found {$employees->count()} {$scopeMsg} across selected companies.");

        $sentCount    = 0;
        $failedCount  = 0;
        $skippedCount = 0;
        $rows         = [];

        foreach ($employees as $emp) {
            $currentReminderCount = (int) ($emp->reminder_count ?? 0);
            $nextReminderCount    = $currentReminderCount + 1;
            $targetEmail          = $testEmail ?: ($emp->email ?? 'No Email');

            // 1. Max reminders check
            if (!$isForce && $settings->max_reminders_per_employee > 0 && $currentReminderCount >= $settings->max_reminders_per_employee) {
                $skippedCount++;
                $rows[] = [
                    $emp->EmployeeID,
                    $emp->EmpCode,
                    $emp->employee_name,
                    $emp->company_name,
                    $targetEmail,
                    $currentReminderCount,
                    '<comment>SKIPPED (Max ' . $settings->max_reminders_per_employee . ' reached)</comment>',
                ];
                continue;
            }

            // 2. Minimum interval check (e.g., at least 3 days between consecutive reminders)
            if (!$isForce && !empty($emp->last_sent_at) && $settings->min_days_between_reminders > 0) {
                $daysSinceLastSent = Carbon::parse($emp->last_sent_at)->diffInDays(now());
                if ($daysSinceLastSent < $settings->min_days_between_reminders) {
                    $skippedCount++;
                    $rows[] = [
                        $emp->EmployeeID,
                        $emp->EmpCode,
                        $emp->employee_name,
                        $emp->company_name,
                        $targetEmail,
                        $currentReminderCount,
                        '<comment>SKIPPED (Sent ' . $daysSinceLastSent . 'd ago, wait ' . $settings->min_days_between_reminders . 'd)</comment>',
                    ];
                    continue;
                }
            }

            if ($isDryRun) {
                $rows[] = [
                    $emp->EmployeeID,
                    $emp->EmpCode,
                    $emp->employee_name,
                    $emp->company_name,
                    $targetEmail,
                    $currentReminderCount . ' (would be ' . $nextReminderCount . ')',
                    'DRY RUN (Simulated)',
                ];
                $sentCount++;
                continue;
            }

            $res = $service->sendReminder($emp, $testEmail, isCron: true);

            if ($res['success']) {
                $sentCount++;
                $rows[] = [
                    $emp->EmployeeID,
                    $emp->EmpCode,
                    $emp->employee_name,
                    $emp->company_name,
                    $res['email'],
                    $res['reminder_count'] ?? $nextReminderCount,
                    '<info>SENT</info>',
                ];
            } else {
                $failedCount++;
                $rows[] = [
                    $emp->EmployeeID,
                    $emp->EmpCode,
                    $emp->employee_name,
                    $emp->company_name,
                    $targetEmail,
                    $currentReminderCount,
                    '<error>FAILED: ' . substr($res['message'] ?? 'Error', 0, 30) . '</error>',
                ];
            }
        }

        $this->table(['ID', 'Code', 'Name', 'Company', 'Email', 'Reminder Count', 'Status'], $rows);

        $this->info("Completed. Sent: {$sentCount}, Skipped: {$skippedCount}, Failed: {$failedCount}.");
        Log::info('SendResignedSubmissionReminderCommand executed', [
            'total'        => $employees->count(),
            'sent'         => $sentCount,
            'skipped'      => $skippedCount,
            'failed'       => $failedCount,
            'dry_run'      => $isDryRun,
            'test_email'   => $testEmail,
            'employee_id'  => $employeeId,
            'company_id'   => $companyId,
        ]);

        return $failedCount > 0 && $sentCount === 0 ? self::FAILURE : self::SUCCESS;
    }
}
