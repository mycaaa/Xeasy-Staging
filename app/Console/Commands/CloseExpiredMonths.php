<?php

namespace App\Console\Commands;

use App\Helpers\DatabaseSwitcher;
use App\Models\MonthExpenseFinal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseExpiredMonths extends Command
{
    protected $signature = 'claims:close-expired-months
                            {--months=3 : Number of months after which to close}
                            {--company= : Specific company ID (default: all companies)}
                            {--year= : Specific year ID (default: current session year)}
                            {--dry-run : Show what would be closed without actually closing}';

    protected $description = 'Close months in monthexpensefinal table that are older than 3 months (configurable). Sets Status to Closed and SubPermission to N.';

    // Company configurations
    private const COMPANIES = [
        1 => ['name' => 'VNR', 'connection' => 'expense'],
        3 => ['name' => 'Nursery', 'connection' => 'expense_nursery'],
        11 => ['name' => 'VVNR', 'connection' => 'expense_vvnr'],
    ];

    public function handle(): int
    {
        $monthsThreshold = (int) $this->option('months');
        $specificCompany = $this->option('company');
        $specificYear = $this->option('year');
        $isDryRun = $this->option('dry-run');

        $this->info("=== Close Expired Months ===");
        $this->info("Threshold: {$monthsThreshold} months");
        $this->info("Date: " . now()->format('d-M-Y H:i'));
        if ($isDryRun) {
            $this->warn("*** DRY RUN — No changes will be made ***");
        }
        $this->newLine();

        // Calculate cutoff: current month minus threshold
        $now = Carbon::now();
        $cutoffMonth = $now->copy()->subMonths($monthsThreshold)->month;
        $cutoffYear = $now->copy()->subMonths($monthsThreshold)->year;

        // Determine which financial year months to close
        // Financial year: Apr (4) to Mar (3)
        // If current month is July (7), and threshold is 3, cutoff is April (4)
        // So months 4 and earlier (in the same FY) should be closed
        $this->info("Current: {$now->format('M Y')} | Cutoff: Month {$cutoffMonth} ({$now->copy()->subMonths($monthsThreshold)->format('M Y')})");
        $this->newLine();

        $companies = $specificCompany
            ? [$specificCompany => self::COMPANIES[$specificCompany] ?? null]
            : self::COMPANIES;

        $totalClosed = 0;

        foreach ($companies as $companyId => $config) {
            if (!$config) {
                $this->error("Invalid company ID: {$companyId}");
                continue;
            }

            $this->info("Processing: {$config['name']} (Company ID: {$companyId})");

            // Determine year_id
            $yearId = $specificYear;
            if (!$yearId) {
                // Default: use session year or calculate from current date
                $yearId = session('year_id', $this->calculateYearId($companyId));
            }

            $connection = $config['connection'];
            $table = MonthExpenseFinal::tableName((int) $yearId);

            try {
                // Determine year IDs to check — current year and all previous years
                $currentYearId = (int) $yearId;
                $yearsToCheck = [];
                for ($y = 1; $y <= $currentYearId; $y++) {
                    $yearsToCheck[] = $y;
                }

                $companyTotal = 0;

                foreach ($yearsToCheck as $checkYearId) {
                    $table = MonthExpenseFinal::tableName($checkYearId);

                    // Check if table exists
                    try {
                        $tableExists = DB::connection($connection)->getSchemaBuilder()->hasTable($table);
                        if (!$tableExists) continue;
                    } catch (\Exception $e) {
                        continue;
                    }

                    // For previous years: close ALL open months (entire year is old)
                    // For current year: close only months older than threshold
                    if ($checkYearId < $currentYearId) {
                        // Previous year — close everything that's still open
                        $openCount = DB::connection($connection)
                            ->table($table)
                            ->where('Status', '!=', 'Closed')
                            ->count();

                        if ($openCount === 0) continue;

                        $this->line("  Year {$checkYearId} ({$table}): {$openCount} open records");

                        if ($isDryRun) {
                            $this->warn("    [DRY RUN] Would close {$openCount} records (entire previous year)");
                        } else {
                            $today = $now->toDateString();

                            // Close with DateOfSubmit already set
                            $c1 = DB::connection($connection)->table($table)
                                ->where('Status', '!=', 'Closed')
                                ->where('DateOfSubmit', '!=', '0000-00-00')
                                ->whereNotNull('DateOfSubmit')
                                ->update(['Status' => 'Closed', 'SubPermission' => 'N']);

                            // Close without DateOfSubmit — set it to today
                            $c2 = DB::connection($connection)->table($table)
                                ->where('Status', '!=', 'Closed')
                                ->where(function ($q) {
                                    $q->whereNull('DateOfSubmit')->orWhere('DateOfSubmit', '0000-00-00');
                                })
                                ->update(['Status' => 'Closed', 'SubPermission' => 'N', 'DateOfSubmit' => $today]);

                            $closed = $c1 + $c2;
                            $companyTotal += $closed;
                            $this->info("    ✓ Closed {$closed} records (prev year, {$c2} auto-submitted)");
                            Log::info("CloseExpiredMonths: Year {$checkYearId} - Closed {$closed} for {$config['name']}");
                        }
                    } else {
                        // Current year — close only months older than threshold
                        $monthsToClose = $this->getMonthsToClose($monthsThreshold, $now);

                        if (empty($monthsToClose)) continue;

                        $openCount = DB::connection($connection)->table($table)
                            ->where('Status', '!=', 'Closed')
                            ->where('YearId', $checkYearId)
                            ->whereIn('Month', $monthsToClose)
                            ->count();

                        if ($openCount === 0) {
                            $this->line("  Year {$checkYearId} ({$table}): No open records for months " . implode(',', $monthsToClose));
                            continue;
                        }

                        $this->line("  Year {$checkYearId} ({$table}): {$openCount} open records in months " . implode(',', $monthsToClose));

                        if ($isDryRun) {
                            $this->warn("    [DRY RUN] Would close {$openCount} records");
                            $samples = DB::connection($connection)->table($table)
                                ->where('Status', '!=', 'Closed')
                                ->where('YearId', $checkYearId)
                                ->whereIn('Month', $monthsToClose)
                                ->select('EmployeeID', 'Month', 'Status', 'DateOfSubmit')
                                ->limit(5)->get();
                            $this->table(
                                ['EmployeeID', 'Month', 'Status', 'DateOfSubmit'],
                                $samples->map(fn($s) => [$s->EmployeeID, $s->Month, $s->Status, $s->DateOfSubmit])->toArray()
                            );
                        } else {
                            $today = $now->toDateString();

                            // Close with DateOfSubmit already set
                            $c1 = DB::connection($connection)->table($table)
                                ->where('Status', '!=', 'Closed')
                                ->where('YearId', $checkYearId)
                                ->whereIn('Month', $monthsToClose)
                                ->where('DateOfSubmit', '!=', '0000-00-00')
                                ->whereNotNull('DateOfSubmit')
                                ->update(['Status' => 'Closed', 'SubPermission' => 'N']);

                            // Close without DateOfSubmit — set it to today
                            $c2 = DB::connection($connection)->table($table)
                                ->where('Status', '!=', 'Closed')
                                ->where('YearId', $checkYearId)
                                ->whereIn('Month', $monthsToClose)
                                ->where(function ($q) {
                                    $q->whereNull('DateOfSubmit')->orWhere('DateOfSubmit', '0000-00-00');
                                })
                                ->update(['Status' => 'Closed', 'SubPermission' => 'N', 'DateOfSubmit' => $today]);

                            $closed = $c1 + $c2;
                            $companyTotal += $closed;
                            $this->info("    ✓ Closed {$closed} records ({$c2} auto-submitted with date {$today})");
                            Log::info("CloseExpiredMonths: Year {$checkYearId} - Closed {$closed} for {$config['name']} (Months: " . implode(',', $monthsToClose) . ", Auto-submitted: {$c2})");
                        }
                    }
                }

                $totalClosed += $companyTotal;
                $this->info("  Company total: {$companyTotal}");

            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}");
                Log::error("CloseExpiredMonths error for {$config['name']}: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->newLine();
        if ($isDryRun) {
            $this->warn("DRY RUN complete. No changes made.");
        } else {
            $this->info("=== Done! Total closed: {$totalClosed} records ===");
        }

        return Command::SUCCESS;
    }

    /**
     * Get months that should be closed based on threshold
     * E.g., if current month is July (7) and threshold is 3, close months 1,2,3,4 (Jan-Apr)
     */
    private function getMonthsToClose(int $threshold, Carbon $now): array
    {
        $months = [];
        for ($i = $threshold; $i <= 12; $i++) {
            $checkDate = $now->copy()->subMonths($i);
            $months[] = $checkDate->month;
        }
        return array_unique($months);
    }

    /**
     * Calculate year_id based on company
     * VNR/Nursery: year_id matches session
     * VVNR: year_id = session_year - 2
     */
    private function calculateYearId(int $companyId): int
    {
        $baseYearId = \App\Support\CurrentYear::id();
        if ($companyId == 11) {
            return $baseYearId - 2;
        }
        return $baseYearId;
    }
}
