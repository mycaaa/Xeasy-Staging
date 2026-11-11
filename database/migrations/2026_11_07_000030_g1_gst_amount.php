<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GST-02 (Finance decision 7 Nov 2026): capture the GST amount (and invoice number) of every bill.
 * Adds GST_Amount to each year's bill detail table (y{N}_g1_expensefilldata) that lacks it.
 * Invoice (invoice number) already exists on that table.
 */
return new class extends Migration
{
    protected $connection = 'expense';

    public function up(): void
    {
        $db = DB::connection($this->connection)->getDatabaseName();
        $tables = DB::connection($this->connection)->select("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_name REGEXP '^y[0-9]+_g1_expensefilldata$'", [$db]);
        foreach ($tables as $row) {
            $t = $row->t;
            if (! Schema::connection($this->connection)->hasColumn($t, 'GST_Amount')) {
                Schema::connection($this->connection)->table($t, fn ($table) => $table->decimal('GST_Amount', 12, 2)->nullable()->after('GST'));
            }
            if (! Schema::connection($this->connection)->hasColumn($t, 'Invoice')) {
                Schema::connection($this->connection)->table($t, fn ($table) => $table->string('Invoice', 30)->nullable());
            }
        }
    }

    public function down(): void
    {
    }
};
