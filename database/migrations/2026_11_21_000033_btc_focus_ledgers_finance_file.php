<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-FOCUS-04 (21 Nov 2026): Finance's New_Focus_Ledgers_.xlsx. Adds Type of Activity -> Expense Group and loads
 * the file's ledger / sub-ledger codes and activity heads, unless Finance has already uploaded its own file in
 * BTC Settings. Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_focus_activity_types')) {
            Schema::create('btc_focus_activity_types', function (Blueprint $t) {
                $t->id();
                $t->string('activity_type', 80)->unique();
                $t->string('expense_group', 80);
                $t->timestamps();
            });
        }
        \App\Services\Btc\FocusLedgers::forget();
        \App\Services\Btc\FocusLedgers::loadFinanceFile();
    }

    public function down(): void
    {
        // additive only
    }
};
