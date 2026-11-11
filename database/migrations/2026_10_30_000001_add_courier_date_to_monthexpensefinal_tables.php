<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new'];

        foreach ($connections as $conn) {
            try {
                // Ensure legacy zero dates don't fail ALTER TABLE
                DB::connection($conn)->statement("SET SESSION sql_mode = ''");

                $tables = DB::connection($conn)->select("SHOW TABLES LIKE '%monthexpensefinal%'");
                foreach ($tables as $tRow) {
                    $table = array_values((array) $tRow)[0];
                    if (!Schema::connection($conn)->hasColumn($table, 'courier_date')) {
                        DB::connection($conn)->statement("ALTER TABLE `{$table}` ADD `courier_date` DATE NULL DEFAULT NULL AFTER `RecevingDate`");
                    }
                }
            } catch (\Exception $e) {
                // Log and continue if a connection is not configured in this environment
                \Illuminate\Support\Facades\Log::warning("Migration add_courier_date warning on {$conn}: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new'];

        foreach ($connections as $conn) {
            try {
                DB::connection($conn)->statement("SET SESSION sql_mode = ''");
                $tables = DB::connection($conn)->select("SHOW TABLES LIKE '%monthexpensefinal%'");
                foreach ($tables as $tRow) {
                    $table = array_values((array) $tRow)[0];
                    if (Schema::connection($conn)->hasColumn($table, 'courier_date')) {
                        DB::connection($conn)->statement("ALTER TABLE `{$table}` DROP COLUMN `courier_date`");
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
};
