<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-PAY-01 / BTC-CLOSE-01 (17 Nov 2026, "BTC requirements" items 16 and 17):
 *  - btc_payments: every payment (or failed payment) of a case - amount, date, mode, UTR, bank reference, status -
 *    so a case can be paid in parts and a failed payment is reversed with its reason;
 *  - btc_cases: payment mode / bank reference / payment status, the document waiver (who, when, why), and a
 *    financial hold (who, when, why) that blocks payment and closure until released.
 * Existing paid cases get one payment row from their paid amount. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        $cols = [
            'payment_mode' => fn (Blueprint $t) => $t->string('payment_mode', 20)->nullable(),
            'payment_bank_ref' => fn (Blueprint $t) => $t->string('payment_bank_ref', 60)->nullable(),
            'payment_status' => fn (Blueprint $t) => $t->string('payment_status', 12)->nullable(),     // partial | full
            'doc_waived_by' => fn (Blueprint $t) => $t->unsignedBigInteger('doc_waived_by')->nullable(),
            'doc_waived_at' => fn (Blueprint $t) => $t->dateTime('doc_waived_at')->nullable(),
            'doc_waived_kinds' => fn (Blueprint $t) => $t->string('doc_waived_kinds', 60)->nullable(),  // original,boarding_pass
            'doc_waiver_reason' => fn (Blueprint $t) => $t->string('doc_waiver_reason', 300)->nullable(),
            'on_hold' => fn (Blueprint $t) => $t->boolean('on_hold')->default(false),
            'hold_reason' => fn (Blueprint $t) => $t->string('hold_reason', 300)->nullable(),
            'hold_by' => fn (Blueprint $t) => $t->unsignedBigInteger('hold_by')->nullable(),
            'hold_at' => fn (Blueprint $t) => $t->dateTime('hold_at')->nullable(),
        ];
        foreach ($cols as $c => $def) {
            if (! Schema::hasColumn('btc_cases', $c)) {
                Schema::table('btc_cases', fn (Blueprint $t) => $def($t));
            }
        }
        if (! Schema::hasTable('btc_payments')) {
            Schema::create('btc_payments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('btc_case_id')->index();
                $t->decimal('amount', 12, 2);
                $t->date('paid_on')->nullable();
                $t->string('mode', 20)->nullable();          // NEFT | RTGS | IMPS | UPI | Cheque | Other
                $t->string('utr_number', 40)->nullable();
                $t->string('bank_ref', 60)->nullable();
                $t->string('status', 12)->default('success'); // success | failed
                $t->string('failed_reason', 300)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('failed_by')->nullable();
                $t->dateTime('failed_at')->nullable();
                $t->timestamps();
            });
            // existing paid cases: one payment row each
            foreach (DB::table('btc_cases')->where('paid_amount', '>', 0)->get(['id', 'paid_amount', 'paid_on', 'utr_number', 'updated_at']) as $c) {
                DB::table('btc_payments')->insert(['btc_case_id' => $c->id, 'amount' => $c->paid_amount, 'paid_on' => $c->paid_on, 'utr_number' => $c->utr_number,
                    'status' => 'success', 'created_at' => $c->updated_at ?? now(), 'updated_at' => now()]);
                DB::table('btc_cases')->where('id', $c->id)->whereNull('payment_status')->update(['payment_status' => 'full']);
            }
        }
    }

    public function down(): void
    {
        // additive; nothing removed
    }
};
