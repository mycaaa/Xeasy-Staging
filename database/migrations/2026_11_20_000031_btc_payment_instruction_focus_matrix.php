<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 20 Nov 2026, tracker tab "Data Punching Form & Finance approval screens", items 6 d-f, 7, 8 and 9:
 *  - BTC-PAY-02: payment instruction to Treasury on each bill (cases already approved count as instructed)
 *  - BTC-FOCUS-01: Type of Expense / Activity for the JV, Focus document numbers (JV/BTC, EJV/BTC, BP/BTC per year)
 *  - BTC-MATRIX-01: Matrix payment confirmation status on each payment (only the last 4 digits of the account kept)
 * Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        $cols = [
            'pay_instructed_at' => fn (Blueprint $t) => $t->dateTime('pay_instructed_at')->nullable(),
            'pay_instructed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('pay_instructed_by')->nullable(),
            'pay_instruction_ref' => fn (Blueprint $t) => $t->string('pay_instruction_ref', 60)->nullable(),
            'focus_expense_type' => fn (Blueprint $t) => $t->string('focus_expense_type', 60)->nullable(),
            'focus_activity_type' => fn (Blueprint $t) => $t->string('focus_activity_type', 80)->nullable(),
            'focus_activity_head' => fn (Blueprint $t) => $t->string('focus_activity_head', 80)->nullable(),
            'focus_jv_doc' => fn (Blueprint $t) => $t->string('focus_jv_doc', 40)->nullable(),
            'focus_ejv_doc' => fn (Blueprint $t) => $t->string('focus_ejv_doc', 40)->nullable(),
            'focus_ejv_at' => fn (Blueprint $t) => $t->dateTime('focus_ejv_at')->nullable(),
        ];
        foreach ($cols as $c => $def) {
            if (! Schema::hasColumn('btc_cases', $c)) {
                Schema::table('btc_cases', $def);
            }
        }
        // bills already approved before this step existed are treated as instructed, so Treasury is not blocked
        DB::table('btc_cases')->where('status', 'fin_approved')->whereNull('pay_instructed_at')
            ->update(['pay_instructed_at' => DB::raw('COALESCE(fin_approved_at, NOW())'), 'pay_instruction_ref' => 'before 20-11-2026']);

        if (Schema::hasTable('btc_payments')) {
            $pc = [
                'matrix_status' => fn (Blueprint $t) => $t->string('matrix_status', 20)->nullable()->index(),   // null = not sent | sent | verified | mismatch | failed | no_account
                'matrix_sent_at' => fn (Blueprint $t) => $t->dateTime('matrix_sent_at')->nullable(),
                'matrix_message' => fn (Blueprint $t) => $t->string('matrix_message', 300)->nullable(),
                'matrix_account_last4' => fn (Blueprint $t) => $t->string('matrix_account_last4', 4)->nullable(),
                'matrix_tries' => fn (Blueprint $t) => $t->unsignedSmallInteger('matrix_tries')->default(0),
                // BTC-FOCUS-01 item 8: Focus bank payment (payment release) document per payment
                'focus_doc' => fn (Blueprint $t) => $t->string('focus_doc', 40)->nullable(),
                'focus_exported_at' => fn (Blueprint $t) => $t->dateTime('focus_exported_at')->nullable(),
            ];
            foreach ($pc as $c => $def) {
                if (! Schema::hasColumn('btc_payments', $c)) {
                    Schema::table('btc_payments', $def);
                }
            }
        }

        if (! Schema::hasTable('btc_doc_series')) {
            Schema::create('btc_doc_series', function (Blueprint $t) {
                $t->id();
                $t->unsignedTinyInteger('company_id')->default(1);
                $t->string('series', 30);        // JV/BTC/2026-27/
                $t->unsignedInteger('last_no')->default(0);
                $t->timestamps();
                $t->unique(['company_id', 'series']);
            });
        }
    }

    public function down(): void
    {
        // additive only
    }
};
