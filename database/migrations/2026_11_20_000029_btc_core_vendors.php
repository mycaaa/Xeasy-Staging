<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-VENDOR-CORE-01 (20 Nov 2026, BTC requirement sheet item 29): copy of the core application's BTC vendor list
 * (no bank or personal details) and the core vendor code on each BTC case; Finance Approval menu renamed
 * 'Payment Approval & Advance Adjustment' (tracker tab 6). Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_core_vendors')) {
            Schema::create('btc_core_vendors', function (Blueprint $t) {
                $t->unsignedBigInteger('id')->primary();   // id in the core application
                $t->string('code', 40)->nullable()->index();
                $t->string('name', 250);
                $t->string('category', 100)->nullable()->index();
                $t->string('parent_name', 100)->nullable();
                $t->string('gstin', 20)->nullable();
                $t->string('pan', 10)->nullable();
                $t->string('city', 100)->nullable();
                $t->string('state_name', 100)->nullable();
                $t->string('msme_number', 40)->nullable();
                $t->boolean('active')->default(true)->index();
                $t->dateTime('synced_at')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('btc_cases') && ! Schema::hasColumn('btc_cases', 'vendor_code')) {
            Schema::table('btc_cases', fn (Blueprint $t) => $t->string('vendor_code', 40)->nullable()->after('vendor_id'));
        }
        // BTC-SCAN-02 (tracker tab 6): the Finance Approval screen is "Payment Approval & Advance Adjustment"
        if (Schema::connection('expense')->hasTable('menus')) {
            \Illuminate\Support\Facades\DB::connection('expense')->table('menus')->whereIn('url', ['btc/approval', '/btc/approval'])
                ->update(['title' => 'Payment Approval & Advance Adjustment']);
            try {
                \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
    }
};
