<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UTR-02 (Finance sheet, 05 Nov): months marked paid with ₹0 / no UTR get a reason, and F&F-held
 * months get the F&F settlement that finally paid them.
 *   utr_exempt_reason  ff_hold | da_payroll | advance_adjusted
 *   ff_settlement_date, ff_settlement_amount, ff_utr, ff_voucher_no, ff_linked_by, ff_linked_at
 * Added to every y*_monthexpensefinal on the VNR ('expense') and VVNR ('expense_vvnr') connections.
 * A connection that cannot be reached or altered (read-only VVNR copy on a test server) is logged and skipped.
 * Also adds the "No-UTR Reasons" menu under Payment (permission payment_section).
 */
return new class extends Migration
{
    private array $connections = ['expense', 'expense_vvnr'];

    public function up(): void
    {
        foreach ($this->connections as $conn) {
            try {
                $years = DB::connection($conn)->table('financialyear')->pluck('YearId');
            } catch (\Throwable $e) {
                Log::warning("UTR-02 migration: {$conn} not reachable: ".$e->getMessage());

                continue;
            }
            foreach ($years as $y) {
                $table = "y{$y}_monthexpensefinal";
                try {
                    if (! Schema::connection($conn)->hasTable($table) || Schema::connection($conn)->hasColumn($table, 'utr_exempt_reason')) {
                        continue;
                    }
                    Schema::connection($conn)->table($table, function ($t) {
                        $t->string('utr_exempt_reason', 30)->nullable();
                        $t->date('ff_settlement_date')->nullable();
                        $t->decimal('ff_settlement_amount', 12, 2)->nullable();
                        $t->string('ff_utr', 60)->nullable();
                        $t->string('ff_voucher_no', 60)->nullable();
                        $t->unsignedBigInteger('ff_linked_by')->nullable();
                        $t->dateTime('ff_linked_at')->nullable();
                    });
                } catch (\Throwable $e) {
                    Log::warning("UTR-02 migration: {$conn}.{$table} not changed: ".$e->getMessage());
                }
            }
        }

        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'expense-payments/utr-exemptions')->exists()) {
            $parent = DB::table('menus')->where('title', 'Payment')->whereNull('parent_id')->value('id');
            $row = [
                'title' => 'No-UTR Reasons',
                'url' => 'expense-payments/utr-exemptions',
                'icon' => 'ri-bank-line',
                'parent_id' => $parent,
                'permission_name' => 'payment_section',
                'company_access_id' => '1,11',
                'order' => 90,
                'data_key' => 'utr-exemptions',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $cols = Schema::getColumnListing('menus');
            DB::table('menus')->insert(array_intersect_key($row, array_flip($cols)));
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'expense-payments/utr-exemptions')->delete();
        // keep the columns: they hold Finance's reasons and F&F links
    }
};
