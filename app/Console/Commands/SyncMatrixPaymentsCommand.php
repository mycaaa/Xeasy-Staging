<?php

namespace App\Console\Commands;

use App\Services\Matrix\MatrixPaymentManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMatrixPaymentsCommand extends Command
{
    protected $signature = 'matrix:sync-updates {--limit=200 : Max records per page}';

    protected $description = 'Poll Matrix for payment outcome updates and sync to Xeasy local records';

    public function handle(MatrixPaymentManager $manager): int
    {
        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Polling Matrix for payment updates...');

        try {
            $result = $manager->syncUpdates();

            if ($result['success']) {
                $this->info("Sync successful! Processed: {$result['total_processed']}, Updated: {$result['updated_instructions']}");
                $this->line("Next since cursor: " . ($result['next_since'] ?? 'N/A'));
                return self::SUCCESS;
            }

            $this->error("Sync failed: " . ($result['message'] ?? 'Unknown error'));
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Exception during Matrix sync: " . $e->getMessage());
            Log::error('Matrix sync command exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
