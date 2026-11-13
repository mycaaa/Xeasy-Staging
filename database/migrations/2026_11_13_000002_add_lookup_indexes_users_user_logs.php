<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PERF-03: users.employee_id is the join key for logins, approvers and most reports but had no index;
// user_logs is read by date range and by user. Plain (non-unique) indexes; online build.
return new class extends Migration
{
    private array $wanted = [
        'users' => ['users_employee_id_index' => 'employee_id'],
        'user_logs' => ['user_logs_timestamp_index' => 'timestamp', 'user_logs_user_id_index' => 'user_id'],
    ];

    private function has(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `$table`"))->contains(fn ($i) => $i->Key_name === $index);
    }

    public function up(): void
    {
        foreach ($this->wanted as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $column) {
                if (Schema::hasColumn($table, $column) && ! $this->has($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($column, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->wanted as $table => $indexes) {
            foreach ($indexes as $name => $column) {
                if (Schema::hasTable($table) && $this->has($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
