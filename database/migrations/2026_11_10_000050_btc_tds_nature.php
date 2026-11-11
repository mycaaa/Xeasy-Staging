<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BTC-TDS-01: the nature of payment chosen by Finance on each BTC case (decides the TDS section and rate). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('btc_cases') && ! Schema::hasColumn('btc_cases', 'tds_nature')) {
            Schema::table('btc_cases', function (Blueprint $t) {
                $t->string('tds_nature', 30)->nullable()->after('tds_section');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('btc_cases', 'tds_nature')) {
            Schema::table('btc_cases', fn (Blueprint $t) => $t->dropColumn('tds_nature'));
        }
    }
};
