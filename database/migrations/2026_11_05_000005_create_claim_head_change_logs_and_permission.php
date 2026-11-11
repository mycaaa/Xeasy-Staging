<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker #28: bulk claim head change for the Expense team.
 *  - claim_head_change_logs: one audit row per changed claim (old head, new
 *    head, actor, batch), in every company expense DB (where the claims live).
 *  - permission 'claim.bulk_change_head' (guard web) granted to the roles that
 *    hold the data-punching section today (Mediator) and to Admin.
 *
 *   php artisan migrate --path=database/migrations/2026_11_05_000005_create_claim_head_change_logs_and_permission.php
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new', 'expense_nursery'];

    public function up(): void
    {
        foreach ($this->expenseConnections() as $conn) {
            try {
                if (! Schema::connection($conn)->hasTable('claim_head_change_logs')) {
                    Schema::connection($conn)->create('claim_head_change_logs', function (Blueprint $table) {
                        $table->id();
                        $table->uuid('batch_id')->nullable()->index();
                        $table->unsignedInteger('company_id')->nullable();
                        $table->unsignedSmallInteger('year_id');
                        $table->unsignedBigInteger('exp_id')->index();
                        $table->unsignedBigInteger('employee_id')->nullable();
                        $table->unsignedInteger('old_claim_id');
                        $table->unsignedInteger('new_claim_id');
                        $table->string('claim_status', 20)->nullable();
                        $table->string('mode', 10)->default('bulk'); // bulk | single
                        $table->unsignedBigInteger('changed_by')->nullable();
                        $table->string('changed_by_name', 150)->nullable();
                        $table->string('remark', 500)->nullable();
                        $table->timestamp('created_at')->useCurrent();
                    });
                }
            } catch (\Throwable $e) {
                // Fail loudly: a half-applied schema change must not be reported as migrated.
                Log::error("Migration claim_head_change_logs on {$conn}: ".$e->getMessage());
                throw $e;
            }
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $groupId = DB::table('permission_groups')->where('name', 'Claims Management')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'claim.bulk_change_head')->where('guard_name', 'web')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'claim.bulk_change_head',
                'permission_key' => 'Bulk Claim Head Change',
                'permission_group_id' => $groupId,
                'status' => 1,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $punchPermissionId = DB::table('permissions')->where('name', 'section_data_punching')->value('id');
        $roleIds = DB::table('roles')->where('guard_name', 'web')
            ->where(function ($q) use ($punchPermissionId) {
                $q->where('name', 'Admin');
                if ($punchPermissionId) {
                    $q->orWhereIn('id', DB::table('role_has_permissions')->where('permission_id', $punchPermissionId)->pluck('role_id'));
                }
            })->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        foreach ($this->expenseConnections() as $conn) {
            try {
                // Keep the audit trail if any change was logged.
                if (Schema::connection($conn)->hasTable('claim_head_change_logs')
                    && ! DB::connection($conn)->table('claim_head_change_logs')->exists()) {
                    Schema::connection($conn)->drop('claim_head_change_logs');
                }
            } catch (\Throwable $e) {
                Log::warning("Rollback claim_head_change_logs on {$conn}: ".$e->getMessage());
            }
        }
        $permissionId = DB::table('permissions')->where('name', 'claim.bulk_change_head')->where('guard_name', 'web')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }

    /** Distinct reachable connections whose database holds claimtype (company expense DBs). */
    private function expenseConnections(): array
    {
        $seen = [];
        $result = [];
        foreach ($this->connections as $conn) {
            $config = config("database.connections.{$conn}");
            if (! is_array($config) || ($config['driver'] ?? '') !== 'mysql') {
                continue;
            }
            $key = ($config['host'] ?? '').'|'.($config['port'] ?? '').'|'.($config['database'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            try {
                if (! Schema::connection($conn)->hasTable('claimtype')) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $conn;
        }

        return $result;
    }
};
