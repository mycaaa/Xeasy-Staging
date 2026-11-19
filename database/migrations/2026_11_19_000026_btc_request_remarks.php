<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-25 (19 Nov 2026, BTC requirement sheet item 25): remarks on the new-case form (below the attachments);
 * required when the purpose is "Other". Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('btc_cases') && ! Schema::hasColumn('btc_cases', 'request_remarks')) {
            Schema::table('btc_cases', fn (Blueprint $t) => $t->string('request_remarks', 1000)->nullable());
        }
    }

    public function down(): void
    {
    }
};
