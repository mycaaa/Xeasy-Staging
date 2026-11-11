<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker #36: Imprest approve / deny by authorised Finance users, with an
 * action history.
 *  - imprest_request_actions: one row per approve / reject / release action
 *    (request id, action, actor, amount, remark, timestamps), next to every
 *    imprest_requests table (each company DB that has one).
 *  - imprest_requests.rejected_by / rejected_at: who rejected (was not stored).
 *  - permission 'imprest.approve' (guard web) granted to the Finance and
 *    Treasury roles (Super Admin passes through Gate::before).
 *
 * Idempotent; down() drops only what this migration added. The `expense` DB
 * has no `migrations` table, so run it on its own:
 *   php artisan migrate --path=database/migrations/2026_11_05_000002_create_imprest_request_actions_and_permission.php
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new', 'expense_nursery'];

    private array $roles = ['Finance', 'Treasury'];

    public function up(): void
    {
        foreach ($this->imprestConnections() as $conn) {
            try {
                $schema = Schema::connection($conn);
                if (! $schema->hasTable('imprest_request_actions')) {
                    $schema->create('imprest_request_actions', function (Blueprint $table) {
                        $table->id();
                        $table->unsignedBigInteger('imprest_request_id')->nullable()->index();
                        $table->unsignedBigInteger('employee_id')->index();
                        $table->string('action', 30);              // approved | rejected | released | release_rejected
                        $table->string('from_status', 20)->nullable();
                        $table->string('to_status', 20);
                        $table->decimal('amount', 12, 2)->default(0);
                        $table->text('remarks')->nullable();
                        $table->unsignedBigInteger('actor_user_id')->nullable();
                        $table->unsignedBigInteger('actor_employee_id')->nullable();
                        $table->string('actor_name', 150)->nullable();
                        $table->string('ip_address', 45)->nullable();
                        $table->timestamps();
                    });
                }
                if (! $schema->hasColumn('imprest_requests', 'rejected_by')) {
                    $schema->table('imprest_requests', function (Blueprint $table) {
                        $table->string('rejected_by', 100)->nullable()->after('approved_at');
                        $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                    });
                }
            } catch (\Throwable $e) {
                // Fail loudly: a half-applied schema change must not be reported as migrated.
                Log::error("Migration imprest_request_actions on {$conn}: ".$e->getMessage());
                throw $e;
            }
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        foreach ($this->imprestConnections() as $conn) {
            try {
                $schema = Schema::connection($conn);
                // Keep the audit trail: drop the history table / rejector columns only while empty.
                if ($schema->hasTable('imprest_request_actions')) {
                    if (DB::connection($conn)->table('imprest_request_actions')->exists()) {
                        Log::warning("Rollback kept imprest_request_actions on {$conn}: it holds action history.");
                    } else {
                        $schema->drop('imprest_request_actions');
                    }
                }
                $rejectorUsed = $schema->hasColumn('imprest_requests', 'rejected_by')
                    && DB::connection($conn)->table('imprest_requests')->whereNotNull('rejected_by')->exists();
                if ($rejectorUsed) {
                    Log::warning("Rollback kept imprest_requests.rejected_by/rejected_at on {$conn}: they hold data.");
                } elseif ($schema->hasColumn('imprest_requests', 'rejected_by')) {
                    $schema->table('imprest_requests', function (Blueprint $table) {
                        $table->dropColumn(['rejected_by', 'rejected_at']);
                    });
                }
            } catch (\Throwable $e) {
                Log::warning("Rollback imprest_request_actions on {$conn}: ".$e->getMessage());
            }
        }
        $permissionId = DB::table('permissions')->where('name', 'imprest.approve')->where('guard_name', 'web')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        $this->forgetPermissionCache();
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $groupId = DB::table('permission_groups')->where('name', 'Payment')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'imprest.approve')->where('guard_name', 'web')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'imprest.approve',
                'permission_key' => 'Imprest Approve / Deny',
                'permission_group_id' => $groupId,
                'status' => 1,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $roleIds = DB::table('roles')->whereIn('name', $this->roles)->where('guard_name', 'web')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // cache store not available while migrating: permissions reload on next request after cache TTL
        }
    }

    /** Distinct connections whose database has imprest_requests. */
    private function imprestConnections(): array
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
                if (! Schema::connection($conn)->hasTable('imprest_requests')) {
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
