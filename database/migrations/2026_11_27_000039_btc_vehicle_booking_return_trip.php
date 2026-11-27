<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-VEH-01 / BTC-RET-01 (27 Nov 2026, testing points 36, 38, 39): Hired vehicle requests (pickup time, Sedan / SUV,
 * the booking desk's availability check, the vehicle actually booked with the reason when different, vehicle and
 * driver details), the return journey of an Air / Train round-trip booking (its own PNR / reference and sector) and when a bill went into a downloaded daily Focus file (point 35).
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
            'pickup_time' => fn (Blueprint $t) => $t->string('pickup_time', 5)->nullable(),                 // HH:MM
            'vehicle_type' => fn (Blueprint $t) => $t->string('vehicle_type', 20)->nullable(),               // Sedan | SUV asked for
            'vehicle_available' => fn (Blueprint $t) => $t->string('vehicle_available', 3)->nullable(),      // Y | N
            'booked_vehicle_type' => fn (Blueprint $t) => $t->string('booked_vehicle_type', 20)->nullable(),
            'alt_vehicle_reason' => fn (Blueprint $t) => $t->string('alt_vehicle_reason', 500)->nullable(),
            'vehicle_no' => fn (Blueprint $t) => $t->string('vehicle_no', 40)->nullable(),
            'driver_name' => fn (Blueprint $t) => $t->string('driver_name', 100)->nullable(),
            'driver_phone' => fn (Blueprint $t) => $t->string('driver_phone', 20)->nullable(),
            'return_booking_ref' => fn (Blueprint $t) => $t->string('return_booking_ref', 80)->nullable(),
            'return_pnr' => fn (Blueprint $t) => $t->string('return_pnr', 40)->nullable(),
            'return_sector' => fn (Blueprint $t) => $t->string('return_sector', 120)->nullable(),
            // BTC-DAYFILE-01 (testing point 35): when the bill was last put in a downloaded day file
            'focus_file_at' => fn (Blueprint $t) => $t->timestamp('focus_file_at')->nullable(),
        ];
        foreach ($cols as $name => $add) {
            if (! Schema::hasColumn('btc_cases', $name)) {
                Schema::table('btc_cases', fn (Blueprint $t) => $add($t));
            }
        }
    }

    public function down(): void
    {
        // additive only
    }
};
