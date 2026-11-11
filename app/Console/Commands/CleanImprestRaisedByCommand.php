<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to clean currently raised imprest advance records:
 * Sets created_by = 0, req_app_submit_id = 0, approver_id = 0, and action_by = 0
 * so that they do not appear as "raised by" or "approved by" any individual approver/employee.
 */
class CleanImprestRaisedByCommand extends Command
{
    protected $signature = 'imprest:clean-raised-by {--connection= : Specific database connection to run on}';
    protected $description = 'Clean raised_by and approver IDs for all Imprest Auto Advance records so they show as System Generated';

    public function handle(): int
    {
        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Cleaning Imprest Auto Advance raised_by and approver IDs...');

        $connections = $this->option('connection')
            ? [$this->option('connection')]
            : array_filter(array_unique([
                config('database.default', 'mysql'),
                'mysql',
                'expense',
                'expense_app',
            ]), fn ($c) => config("database.connections.{$c}") !== null);

        $totalUpdatedRequests = 0;

        foreach ($connections as $conn) {
            try {
                if (!Schema::connection($conn)->hasTable('advance_requests')) {
                    continue;
                }

                $this->line("Processing connection: <comment>{$conn}</comment>");

                // 1. Find all advance request IDs that are marked as imprest or have imprest activities
                $imprestRequestIds = DB::connection($conn)->table('advance_requests as ar')
                    ->leftJoin('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
                    ->leftJoin('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
                    ->where(function ($q) {
                        $q->where('ar.is_imprest', 1)
                          ->orWhere('ara.is_imprest', 1)
                          ->orWhere('ara.purpose', 'like', '%imprest%');
                    })
                    ->distinct()
                    ->pluck('ar.id')
                    ->toArray();

                if (empty($imprestRequestIds)) {
                    $this->line("  No imprest advance records found on {$conn}.");
                    continue;
                }

                $this->line("  Found <info>" . count($imprestRequestIds) . "</info> imprest advance request(s) on {$conn}.");

                // 2. Update advance_requests
                $reqUpdateData = [];
                if (Schema::connection($conn)->hasColumn('advance_requests', 'is_imprest')) {
                    $reqUpdateData['is_imprest'] = 1;
                }
                if (Schema::connection($conn)->hasColumn('advance_requests', 'created_by')) {
                    $reqUpdateData['created_by'] = 0;
                }
                if (Schema::connection($conn)->hasColumn('advance_requests', 'updated_by')) {
                    $reqUpdateData['updated_by'] = 0;
                }
                if (Schema::connection($conn)->hasColumn('advance_requests', 'req_app_submit_id')) {
                    $reqUpdateData['req_app_submit_id'] = 0;
                }
                $reqUpdated = 0;
                if (!empty($reqUpdateData)) {
                    $reqUpdated = DB::connection($conn)->table('advance_requests')
                        ->whereIn('id', $imprestRequestIds)
                        ->update($reqUpdateData);
                }

                // 3. Update advance_request_employees
                $empUpdateData = [];
                if (Schema::connection($conn)->hasColumn('advance_request_employees', 'created_by')) {
                    $empUpdateData['created_by'] = 0;
                }
                if (Schema::connection($conn)->hasColumn('advance_request_employees', 'updated_by')) {
                    $empUpdateData['updated_by'] = 0;
                }
                if (!empty($empUpdateData)) {
                    DB::connection($conn)->table('advance_request_employees')
                        ->whereIn('advance_request_id', $imprestRequestIds)
                        ->update($empUpdateData);
                }

                // 4. Update advance_request_activities
                $empIds = DB::connection($conn)->table('advance_request_employees')
                    ->whereIn('advance_request_id', $imprestRequestIds)
                    ->pluck('id')
                    ->toArray();

                if (!empty($empIds) && Schema::connection($conn)->hasTable('advance_request_activities')) {
                    $actUpdateData = [];
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'is_imprest')) {
                        $actUpdateData['is_imprest'] = 1;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'created_by')) {
                        $actUpdateData['created_by'] = 0;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'updated_by')) {
                        $actUpdateData['updated_by'] = 0;
                    }
                    if (!empty($actUpdateData)) {
                        DB::connection($conn)->table('advance_request_activities')
                            ->whereIn('advance_request_employee_id', $empIds)
                            ->update($actUpdateData);
                    }

                    $actIds = DB::connection($conn)->table('advance_request_activities')
                        ->whereIn('advance_request_employee_id', $empIds)
                        ->pluck('id')
                        ->toArray();

                    // 5. Update advance_activity_approvals
                    if (!empty($actIds) && Schema::connection($conn)->hasTable('advance_activity_approvals')) {
                        $apprUpdateData = [];
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'approver_id')) {
                            $apprUpdateData['approver_id'] = 0;
                        }
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'approver_remark')) {
                            $apprUpdateData['approver_remark'] = DB::raw("CASE WHEN approver_remark IS NULL OR approver_remark = '' THEN 'System Generated Auto-Approved for Imprest' ELSE approver_remark END");
                        }
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'created_by')) {
                            $apprUpdateData['created_by'] = 0;
                        }
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'updated_by')) {
                            $apprUpdateData['updated_by'] = 0;
                        }
                        if (!empty($apprUpdateData)) {
                            DB::connection($conn)->table('advance_activity_approvals')
                                ->whereIn('advance_request_activity_id', $actIds)
                                ->update($apprUpdateData);
                        }
                    }
                }

                // 6. Update advance_request_actions
                if (Schema::connection($conn)->hasTable('advance_request_actions')) {
                    $actionUpdateData = [];
                    if (Schema::connection($conn)->hasColumn('advance_request_actions', 'action_by')) {
                        $actionUpdateData['action_by'] = 0;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_actions', 'created_by')) {
                        $actionUpdateData['created_by'] = 0;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_actions', 'updated_by')) {
                        $actionUpdateData['updated_by'] = 0;
                    }
                    if (!empty($actionUpdateData)) {
                        DB::connection($conn)->table('advance_request_actions')
                            ->whereIn('advance_request_id', $imprestRequestIds)
                            ->update($actionUpdateData);
                    }
                }

                $totalUpdatedRequests += $reqUpdated;
                $this->info("  Successfully updated {$reqUpdated} advance request(s) on {$conn}.");
            } catch (\Exception $e) {
                $this->error("  Error processing {$conn}: " . $e->getMessage());
                Log::error("CleanImprestRaisedByCommand error on {$conn}: " . $e->getMessage());
            }
        }

        $this->info("All done! Total imprest advance requests cleaned: {$totalUpdatedRequests}.");
        return self::SUCCESS;
    }
}
