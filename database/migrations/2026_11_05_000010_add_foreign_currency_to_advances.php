<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FX-53 (tracker #53): foreign-currency advances.
 *  - advance_request_activities: currency (INR default), fx_amount, fx_rate, fx_rate_date,
 *    fx_paid_rate (rate Finance actually paid at), fx_paid_amount (foreign amount paid)
 *  - advance_fx_rates: rates Finance enters per currency and date (history kept)
 *
 * Runs on the default expense connection only (advance tables live there).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('advance_request_activities', 'currency')) {
            Schema::table('advance_request_activities', function (Blueprint $t) {
                $t->char('currency', 3)->default('INR')->after('amount');
                $t->decimal('fx_amount', 14, 2)->nullable()->after('currency');
                $t->decimal('fx_rate', 12, 4)->nullable()->after('fx_amount');
                $t->date('fx_rate_date')->nullable()->after('fx_rate');
                $t->decimal('fx_paid_rate', 12, 4)->nullable()->after('fx_rate_date');
                $t->decimal('fx_paid_amount', 14, 2)->nullable()->after('fx_paid_rate');
            });
        }
        if (! Schema::hasTable('advance_fx_rates')) {
            Schema::create('advance_fx_rates', function (Blueprint $t) {
                $t->id();
                $t->char('currency', 3);
                $t->date('rate_date');
                $t->decimal('rate', 12, 4);
                $t->string('source', 100)->nullable();
                $t->unsignedInteger('set_by')->nullable();
                $t->timestamps();
                $t->unique(['currency', 'rate_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_fx_rates');
        if (Schema::hasColumn('advance_request_activities', 'currency')) {
            Schema::table('advance_request_activities', function (Blueprint $t) {
                $t->dropColumn(['currency', 'fx_amount', 'fx_rate', 'fx_rate_date', 'fx_paid_rate', 'fx_paid_amount']);
            });
        }
    }
};
