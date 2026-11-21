<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-FOCUS-05 (21 Nov 2026, Finance): only the travel codes differ for VVNR; meeting and training rows use the
 * same ledger / sub-ledger codes as VNR. Fills the blank VVNR codes with the VNR ones. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_focus_ledgers')) {
            return;
        }
        DB::table('btc_focus_ledgers')->where(fn ($q) => $q->whereNull('vvnr_ledger')->orWhere('vvnr_ledger', ''))->whereNotNull('vnr_ledger')
            ->update(['vvnr_ledger' => DB::raw('vnr_ledger'), 'vvnr_sub' => DB::raw('vnr_sub'), 'updated_at' => now()]);
        \App\Services\Btc\FocusLedgers::forget();
    }

    public function down(): void
    {
        // additive only
    }
};
