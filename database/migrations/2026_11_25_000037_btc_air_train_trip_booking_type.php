<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-TRIP-01 (25 Nov 2026, testing points 20 and 21): Air / Train requests - travel from / to, one-way or round trip
 * with the return date, and the booking type the reporting manager chooses at approval (corporate / normal).
 * Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        Schema::table('btc_cases', function (Blueprint $t) {
            if (! Schema::hasColumn('btc_cases', 'travel_from')) {
                $t->string('travel_from', 100)->nullable();
            }
            if (! Schema::hasColumn('btc_cases', 'travel_to')) {
                $t->string('travel_to', 100)->nullable();
            }
            if (! Schema::hasColumn('btc_cases', 'trip_type')) {
                $t->string('trip_type', 12)->nullable();   // one_way | round_trip
            }
            if (! Schema::hasColumn('btc_cases', 'return_date')) {
                $t->date('return_date')->nullable();
            }
            if (! Schema::hasColumn('btc_cases', 'booking_type')) {
                $t->string('booking_type', 12)->nullable();   // corporate | normal (manager, at approval)
            }
        });
    }

    public function down(): void
    {
        // additive only
    }
};
