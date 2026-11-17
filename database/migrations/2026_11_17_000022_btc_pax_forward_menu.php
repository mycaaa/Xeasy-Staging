<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-14/20/21 (17 Nov 2026, BTC requirement sheet items 14, 20, 21):
 *  - btc_cases: number of pax, the employees travelling / attending (code + name), guests, and the date the BTC
 *    admin forwarded the bill to DMS;
 *  - menu "My BTC Requests" renamed "BTC Request".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('btc_cases')) {
            foreach ([
                'pax_count' => fn (Blueprint $t) => $t->unsignedSmallInteger('pax_count')->nullable(),
                'travellers' => fn (Blueprint $t) => $t->text('travellers')->nullable(),      // JSON [{id, code, name}]
                'guests' => fn (Blueprint $t) => $t->string('guests', 500)->nullable(),
                'bill_forwarded_dms_on' => fn (Blueprint $t) => $t->date('bill_forwarded_dms_on')->nullable(),
            ] as $c => $def) {
                if (! Schema::hasColumn('btc_cases', $c)) {
                    Schema::table('btc_cases', fn (Blueprint $t) => $def($t));
                }
            }
        }
        if (Schema::connection('expense')->hasTable('menus')) {
            DB::connection('expense')->table('menus')->where('title', 'My BTC Requests')->update(['title' => 'BTC Request', 'updated_at' => now()]);
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
