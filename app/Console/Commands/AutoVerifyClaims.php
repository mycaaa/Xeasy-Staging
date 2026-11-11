<?php

namespace App\Console\Commands;

use App\Models\FinancialYear;
use App\Services\AutoVerificationService;
use Illuminate\Console\Command;

class AutoVerifyClaims extends Command
{
    protected $signature = 'claims:auto-verify
                            {--company=1 : Company ID (1: VNR Seeds, 3: Nursery, 11: VVNR)}
                            {--year= : Financial Year ID (defaults to current active year)}
                            {--month= : Specific month (1-12)}
                            {--employee= : Specific Employee ID}
                            {--claim-type= : Specific Claim Type ID (e.g. 7 for Travel, 19 for DA)}
                            {--dry-run : Simulate auto-verification without updating database records}
                            {--auto-submit-month : Auto-submit months where 100% of claims are verified}';

    protected $description = 'Evaluate pending claims against policy limits, GPS telemetry, odometer, and OCR extractions, auto-verifying compliant claims.';

    public function handle(AutoVerificationService $autoVerifyService): int
    {
        $companyId = (int) $this->option('company');
        $yearId = $this->option('year');
        $month = $this->option('month');
        $employeeId = $this->option('employee');
        $claimType = $this->option('claim-type');
        $isDryRun = (bool) $this->option('dry-run');
        $autoSubmitMonth = (bool) $this->option('auto-submit-month');

        if (!$yearId) {
            // Find active or highest financial year
            $latestYear = FinancialYear::orderBy('YearId', 'desc')->first();
            $yearId = $latestYear ? (int) $latestYear->YearId : 7;
        } else {
            $yearId = (int) $yearId;
        }

        $this->info("==================================================");
        $this->info("       🚀 XEASY AUTOMATED CLAIM VERIFICATION      ");
        $this->info("==================================================");
        $this->line("Company ID   : {$companyId}");
        $this->line("Financial Year: ID {$yearId}");
        if ($month) $this->line("Month Filter : {$month}");
        if ($employeeId) $this->line("Employee ID  : {$employeeId}");
        if ($claimType) $this->line("Claim Type ID: {$claimType}");
        $this->line("Execution    : " . ($isDryRun ? "<comment>DRY-RUN (Simulation - no data changed)</comment>" : "<fg=green>LIVE (Database updates enabled)</fg=green>"));
        $this->newLine();

        $filters = array_filter([
            'month' => $month,
            'employee_id' => $employeeId,
            'claim_id' => $claimType,
        ]);

        $this->info("Evaluating pending claims...");
        $startTime = microtime(true);

        $result = $autoVerifyService->batchVerifyPending($companyId, $yearId, $filters, $isDryRun);

        if (!($result['success'] ?? false)) {
            $this->error("Auto-verification failed: " . ($result['message'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        $duration = round(microtime(true) - $startTime, 2);

        // Display Summary Table
        $this->newLine();
        $this->table(
            ['Category', 'Count', 'Total Amount', 'Action Taken'],
            [
                [
                    '<fg=green>🟢 Green (Auto-Approved)</fg=green>',
                    $result['approved'],
                    '₹' . number_format($result['approved_amount'], 2),
                    $isDryRun ? 'Would verify 100%' : 'Marked Verified (Step 4)',
                ],
                [
                    '<fg=yellow>🟡 Yellow (Policy-Capped)</fg=yellow>',
                    $result['adjusted'],
                    '₹' . number_format($result['adjusted_amount'], 2),
                    $isDryRun ? 'Would cap to policy' : 'Capped & Marked Verified',
                ],
                [
                    '<fg=red>🔴 Red (Flagged for Review)</fg=red>',
                    $result['flagged'],
                    'N/A',
                    'Remains in human verifier queue',
                ],
                [
                    '<fg=gray>⚪ Skipped (Already Verified)</fg=gray>',
                    $result['skipped'],
                    'N/A',
                    'No action needed',
                ],
            ]
        );

        $this->info(sprintf("Total Evaluated: %d claims in %s seconds.", $result['total_evaluated'], $duration));

        // Show detailed sample of evaluated claims
        if (!empty($result['claims'])) {
            $this->newLine();
            $this->info("Sample Breakdown (First " . count($result['claims']) . " claims):");

            $sampleRows = array_map(function ($c) {
                $statusFormatted = match ($c['status']) {
                    'approved' => '<fg=green>Approved</fg=green>',
                    'adjusted' => '<fg=yellow>Adjusted</fg=yellow>',
                    'flagged'  => '<fg=red>Flagged</fg=red>',
                    default    => $c['status'],
                };

                return [
                    $c['exp_id'],
                    $c['employee_id'],
                    $c['claim_id'],
                    $c['bill_date'],
                    '₹' . number_format($c['claimed_amount'], 2),
                    '₹' . number_format($c['verified_amount'], 2),
                    $statusFormatted,
                    \Illuminate\Support\Str::limit($c['reason'], 45),
                ];
            }, array_slice($result['claims'], 0, 15));

            $this->table(
                ['Exp ID', 'Emp ID', 'Claim ID', 'Bill Date', 'Claimed', 'Verified', 'Status', 'Verdict Reason'],
                $sampleRows
            );
        }

        // Optional Month-level auto-submission
        if ($autoSubmitMonth && !$isDryRun && $employeeId && $month) {
            $this->newLine();
            $this->info("Evaluating month voucher submission for Employee {$employeeId}, Month {$month}...");
            $monthResult = $autoVerifyService->verifyMonthVoucher((int) $employeeId, (int) $month, $yearId, $companyId, false);
            $this->line("Month Result: " . json_encode($monthResult));
        }

        $this->newLine();
        $this->info("Completed successfully.");
        return Command::SUCCESS;
    }
}
