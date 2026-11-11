<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ODO-BD-02 (decision 6 Nov 2026): two-step back-dated odometer approval (RM first, then BU head /
 * HOD). Adds the RM step and the remarks to every claim table that has Backdate_Odometer_Status,
 * on VNR, VVNR and nursery. Tolerant: a read-only copy (the VVNR copy on staging) is skipped and
 * logged; the same DDL is in the VVNR go-live SQL.
 */
return new class extends Migration
{
    private array $connections = ['expense', 'expense_vvnr', 'expense_nursery'];

    private array $columns = [
        'Backdate_RM_Status' => 'CHAR(1) NULL',
        'Backdate_RM_By' => 'INT NULL',
        'Backdate_RM_Date' => 'DATE NULL',
        'Backdate_RM_Remark' => 'VARCHAR(500) NULL',
        'Backdate_Final_Remark' => 'VARCHAR(500) NULL',
    ];

    public function up(): void
    {
        foreach ($this->connections as $conn) {
            try {
                if (! config("database.connections.{$conn}")) {
                    continue;
                }
                $db = DB::connection($conn);
                $tables = collect($db->select("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'Backdate_Odometer_Status' AND TABLE_NAME REGEXP '^y[0-9]+_expenseclaims$'"))->pluck('t');
                $mode = $db->selectOne('SELECT @@SESSION.sql_mode AS m')->m ?? '';
                $db->statement("SET SESSION sql_mode = ''");
                try {
                    foreach ($tables as $table) {
                        $add = [];
                        foreach ($this->columns as $col => $type) {
                            if (! Schema::connection($conn)->hasColumn($table, $col)) {
                                $add[] = "ADD COLUMN `{$col}` {$type}";
                            }
                        }
                        if ($add) {
                            $db->statement("ALTER TABLE `{$table}` ".implode(', ', $add));
                        }
                    }
                } finally {
                    $db->statement('SET SESSION sql_mode = ?', [$mode]);
                }
            } catch (\Throwable $e) {
                Log::warning("ODO-BD-02 columns not added on {$conn}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
    }
};
