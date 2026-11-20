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
            $this->info('No resigned employee with expense claims found.');
            return self::SUCCESS;
        }

        $scopeMsg = $isAllResigned ? 'all eligible resigned employees' : 'resigned employees with expense claims';
        $this->line("Found {$employees->count()} {$scopeMsg} across selected companies.");

        $sentCount    = 0;
        $failedCount  = 0;
        $skippedCount = 0;
        $rows         = [];

        foreach ($employees as $emp) {
            $currentReminderCount = (int) ($emp->reminder_count ?? 0);
            $nextReminderCount    = $currentReminderCount + 1;
            $targetEmail          = $testEmail ?: ($emp->email ?? 'No Email');

            // FNF-01 (20 Nov 2026): daily from 7 days before the last working day up to that day, once a day
            if (!$isForce && ($why = $service->scheduleSkipReason($emp, $settings))) {
                $skippedCount++;
                $rows[] = [$emp->EmployeeID, $emp->EmpCode, $emp->employee_name, $emp->company_name, $targetEmail, $currentReminderCount, '<comment>SKIPPED ('.$why.')</comment>'];
                continue;
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
