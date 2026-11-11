<?php

namespace App\Console\Commands;

use App\Models\AdvanceRequestActivity;
use App\Models\AdvanceExportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillExportLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:backfill-logs {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill export logs for activities that were exported before the logging system was implemented';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Finding activities with exports but no log entries...');

        // Find activities that have been exported but have no log entries
        $activities = AdvanceRequestActivity::where('is_exported', true)
            ->where('export_count', '>', 0)
            ->whereDoesntHave('exportLogs')
            ->with('employee')
            ->get();

        if ($activities->isEmpty()) {
            $this->info('No activities found that need backfilling.');
            return 0;
        }

        $this->info("Found {$activities->count()} activities to backfill");

        $bar = $this->output->createProgressBar($activities->count());
        $bar->start();

        $backfilledCount = 0;
        $errors = [];

        foreach ($activities as $activity) {
            try {
                // Create log entries based on export_count
                $exportCount = $activity->export_count;
                
                for ($i = 0; $i < $exportCount; $i++) {
                    // Calculate the export date for this log entry
                    if ($i == 0) {
                        // First export
                        $exportedAt = $activity->first_export_date 
                            ? $activity->first_export_date->format('Y-m-d H:i:s')
                            : $activity->created_at->format('Y-m-d H:i:s');
                    } elseif ($i == $exportCount - 1) {
                        // Last export
                        $exportedAt = $activity->last_export_date 
                            ? $activity->last_export_date->format('Y-m-d H:i:s')
                            : $activity->updated_at->format('Y-m-d H:i:s');
                    } else {
                        // Middle exports - estimate between first and last
                        if ($activity->first_export_date && $activity->last_export_date) {
                            $firstDate = $activity->first_export_date->timestamp;
                            $lastDate = $activity->last_export_date->timestamp;
                            $interval = ($lastDate - $firstDate) / $exportCount;
                            $estimatedTime = $firstDate + ($interval * ($i + 1));
                            $exportedAt = date('Y-m-d H:i:s', $estimatedTime);
                        } else {
                            $exportedAt = $activity->updated_at->format('Y-m-d H:i:s');
                        }
                    }

                    // Generate a batch ID for historical export
                    $batchId = 'BACKFILL_' . date('Ymd', strtotime($exportedAt)) . '_' . $activity->id . '_' . ($i + 1);

                    $logData = [
                        'activity_id' => $activity->id,
                        'exported_by' => $activity->exported_by,
                        'export_batch_id' => $batchId,
                        'export_amount' => $activity->final_amount ?? $activity->finance_approved_amount ?? $activity->amount,
                        'export_type' => 'payment',
                        'export_status' => 'success',
                        'export_remarks' => 'Backfilled log entry for historical export',
                        'file_name' => 'historical_export_' . date('Y_m_d', strtotime($exportedAt)) . '.xlsx',
                        'total_activities_in_export' => 1,
                        'exported_at' => $exportedAt,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    if (!$dryRun) {
                        AdvanceExportLog::create($logData);
                    }
                }

                $backfilledCount++;
                
            } catch (\Exception $e) {
                $errors[] = "Activity {$activity->id}: " . $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("DRY RUN: Would have backfilled {$backfilledCount} activities");
        } else {
            $this->info("Successfully backfilled {$backfilledCount} activities");
        }

        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        return 0;
    }
}
