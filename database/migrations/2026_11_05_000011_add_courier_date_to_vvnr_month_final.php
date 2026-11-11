<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * VVNR-COURIER-01 (Finance mail 30 Oct): VVNR keeps two dates per submitted month:
 *   courier_date  = documents received by the DMS team ("Courier Date")
 *   RecevingDate  = hard copy received by Finance for verification (unchanged)
 * The screens and the Not Showing report used to add the column at run time (ALTER TABLE from a
 * page request). This migration adds it once, to every VVNR y*_monthexpensefinal table.
 * Run on the VVNR connection:  php artisan migrate --path=database/migrations/2026_11_05_000011_add_courier_date_to_vvnr_month_final.php
 */
return new class extends Migration
{
    private string $conn = 'expense_vvnr';

    public function up(): void
    {
        try {
            $years = DB::connection($this->conn)->table('financialyear')->pluck('YearId');
        } catch (\Throwable $e) {
            Log::warning('VVNR courier_date migration: VVNR database not reachable: '.$e->getMessage());

            return;
        }
        foreach ($years as $y) {
            $table = "y{$y}_monthexpensefinal";
            if (! Schema::connection($this->conn)->hasTable($table) || Schema::connection($this->conn)->hasColumn($table, 'courier_date')) {
                continue;
            }
            try {
                Schema::connection($this->conn)->table($table, function ($t) {
                    $t->date('courier_date')->nullable()->after('RecevingDate');
                });
            } catch (\Throwable $e) {
                // e.g. a read-only copy of VVNR on a test server
                Log::warning("VVNR courier_date migration: {$table} not changed: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // keep the dates
    }
};
