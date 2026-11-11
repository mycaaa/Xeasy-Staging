<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GST-01 (tracker #6): GSTIN on hotel/lodging bills, checked on entry (format, state code,
 * check digit) and shown to the verifier. Adds GSTIN + GSTIN_Status to every y*_g1_expensefilldata.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET SESSION sql_mode=''");
        foreach ($this->tables() as $t) {
            if (! Schema::hasColumn($t, 'GSTIN')) {
                DB::statement("ALTER TABLE `{$t}` ADD COLUMN `GSTIN` VARCHAR(15) NULL, ADD COLUMN `GSTIN_Status` VARCHAR(20) NULL");
            }
        }
    }

    public function down(): void
    {
        DB::statement("SET SESSION sql_mode=''");
        foreach ($this->tables() as $t) {
            if (Schema::hasColumn($t, 'GSTIN')) {
                DB::statement("ALTER TABLE `{$t}` DROP COLUMN `GSTIN`, DROP COLUMN `GSTIN_Status`");
            }
        }
    }

    private function tables(): array
    {
        $db = DB::getDatabaseName();

        return collect(DB::select("SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME REGEXP '^y[0-9]+_g1_expensefilldata$'", [$db]))
            ->pluck('t')->all();
    }
};
