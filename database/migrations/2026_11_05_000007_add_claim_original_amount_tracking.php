<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audit fix VER-20: verifier changes must not lose what the employee claimed.
 *  - y*_expenseclaims get "original claim" columns, filled once, the first time a verifier
 *    changes a running-km claim: OrigFilledTAmt, OrigOdoOpening, OrigOdoClosing, OrigTotKm,
 *    OrigRatePerKM, plus VerifierEditBy / VerifierEditAt (last verifier change).
 *  - claim_amount_change_logs: one row per changed field per verifier action (full history).
 *
 *   php artisan migrate --path=database/migrations/2026_11_05_000007_add_claim_original_amount_tracking.php
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new', 'expense_nursery'];

    private array $columns = [
        'OrigFilledTAmt' => 'INT NULL',
        'OrigOdoOpening' => 'BIGINT NULL',
        'OrigOdoClosing' => 'BIGINT NULL',
        'OrigTotKm' => 'DECIMAL(7,2) NULL',
        'OrigRatePerKM' => 'DECIMAL(4,2) NULL',
        'VerifierEditBy' => 'INT NULL',
        'VerifierEditAt' => 'DATETIME NULL',
    ];

    public function up(): void
    {
        foreach ($this->expenseConnections() as $conn) {
            try {
                if (! Schema::connection($conn)->hasTable('claim_amount_change_logs')) {
                    Schema::connection($conn)->create('claim_amount_change_logs', function (Blueprint $table) {
                        $table->id();
                        $table->unsignedInteger('company_id')->nullable();
                        $table->unsignedSmallInteger('year_id');
                        $table->unsignedBigInteger('exp_id')->index();
                        $table->unsignedBigInteger('employee_id')->nullable();
                        $table->string('source', 40);          // running_km_update | running_km_verify
                        $table->string('field', 40);           // FilledTAmt, VerifyTAmt, odomtr_opening, ...
                        $table->string('old_value', 50)->nullable();
                        $table->string('new_value', 50)->nullable();
                        $table->string('remark', 500)->nullable();
                        $table->unsignedBigInteger('changed_by')->nullable();
                        $table->timestamp('created_at')->useCurrent();
                    });
                }
                // Legacy claim tables carry zero-date defaults (e.g. BillDate '0000-00-00'), which a
                // strict sql_mode rejects on ALTER. Relax it for this session only.
                $mode = DB::connection($conn)->selectOne('SELECT @@SESSION.sql_mode AS m')->m ?? '';
                DB::connection($conn)->statement("SET SESSION sql_mode = ''");
                try {
                    foreach ($this->claimTables($conn) as $table) {
                        $add = [];
                        foreach ($this->columns as $col => $type) {
                            if (! Schema::connection($conn)->hasColumn($table, $col)) {
                                $add[] = "ADD COLUMN `{$col}` {$type}";
                            }
                        }
                        if ($add) {
                            DB::connection($conn)->statement("ALTER TABLE `{$table}` ".implode(', ', $add));
                        }
                    }
                } finally {
                    DB::connection($conn)->statement('SET SESSION sql_mode = ?', [$mode]);
                }
            } catch (\Throwable $e) {
                Log::error("Migration claim original amount tracking on {$conn}: ".$e->getMessage());
                throw $e;
            }
        }
    }

    public function down(): void
    {
        foreach ($this->expenseConnections() as $conn) {
            try {
                // Keep history and originals if anything was recorded.
                if (Schema::connection($conn)->hasTable('claim_amount_change_logs')
                    && DB::connection($conn)->table('claim_amount_change_logs')->exists()) {
                    continue;
                }
                Schema::connection($conn)->dropIfExists('claim_amount_change_logs');
                DB::connection($conn)->statement("SET SESSION sql_mode = ''");
                foreach ($this->claimTables($conn) as $table) {
                    foreach (array_keys($this->columns) as $col) {
                        if (Schema::connection($conn)->hasColumn($table, $col)) {
                            DB::connection($conn)->statement("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Rollback claim original amount tracking on {$conn}: ".$e->getMessage());
            }
        }
    }

    private function claimTables(string $conn): array
    {
        $db = DB::connection($conn)->getDatabaseName();
        return collect(DB::connection($conn)->select(
            "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_name REGEXP '^y[0-9]+_expenseclaims$'",
            [$db]
        ))->pluck('t')->all();
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
