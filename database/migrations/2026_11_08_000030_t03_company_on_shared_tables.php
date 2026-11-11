<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-03: shared tables in the VNR expense database hold other group companies' rows with no company
 * tag (bank import digi_next_payment_file, unsettled_payments, claim_action_logs). A company_id
 * column is added and filled from the employee's company in HRIMS (hrm_employee.CompanyId);
 * new rows get it from the session company (PaymentVerificationController, bank import).
 */
return new class extends Migration
{
    private array $tables = ['digi_next_payment_file', 'unsettled_payments', 'claim_action_logs'];

    public function up(): void
    {
        $conn = $this->getConnection();
        foreach ($this->tables as $t) {
            if (! Schema::connection($conn)->hasTable($t) || Schema::connection($conn)->hasColumn($t, 'company_id')) {
                continue;
            }
            Schema::connection($conn)->table($t, function (Blueprint $b) {
                $b->unsignedSmallInteger('company_id')->nullable()->index();
            });
        }
        // fill from HRIMS by employee id
        try {
            $map = DB::connection('hrims')->table('hrm_employee')->pluck('CompanyId', 'EmployeeID');
        } catch (\Throwable $e) {
            \Log::warning('T-03: HRIMS not reachable, company_id left empty: '.$e->getMessage());

            return;
        }
        foreach ($this->tables as $t) {
            if (! Schema::connection($conn)->hasTable($t)) {
                continue;
            }
            $col = Schema::connection($conn)->hasColumn($t, 'employee_id') ? 'employee_id' : (Schema::connection($conn)->hasColumn($t, 'EmployeeID') ? 'EmployeeID' : null);
            if (! $col) {
                continue;
            }
            $ids = DB::connection($conn)->table($t)->whereNull('company_id')->where($col, '>', 0)->distinct()->pluck($col);
            foreach ($ids->chunk(500) as $chunk) {
                foreach ($chunk as $emp) {
                    if (isset($map[(int) $emp])) {
                        DB::connection($conn)->table($t)->whereNull('company_id')->where($col, $emp)->update(['company_id' => (int) $map[(int) $emp]]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
    }
};
