<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-BUS-01 (26 Nov 2026, testing point 27): Bus requests - booking for Self / Team + Guest, passenger-wise details
 * with their eligible class, the class asked for (AC / Non-AC), and the booking desk's seat check, a higher class it
 * proposes with the reason, the manager's additional approval and the class finally booked. Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        $cols = [
            'booking_for' => fn (Blueprint $t) => $t->string('booking_for', 12)->nullable(),              // self | team
            'passengers_json' => fn (Blueprint $t) => $t->text('passengers_json')->nullable(),            // [{type, employee_id, code, name, eligible}]
            'travel_class' => fn (Blueprint $t) => $t->string('travel_class', 20)->nullable(),            // class asked for
            'seat_available' => fn (Blueprint $t) => $t->string('seat_available', 3)->nullable(),          // Y | N
            'proposed_class' => fn (Blueprint $t) => $t->string('proposed_class', 20)->nullable(),
            'class_reason' => fn (Blueprint $t) => $t->string('class_reason', 500)->nullable(),
            'class_approval' => fn (Blueprint $t) => $t->string('class_approval', 12)->nullable(),        // pending | approved | rejected
            'class_approved_by' => fn (Blueprint $t) => $t->unsignedBigInteger('class_approved_by')->nullable(),
            'class_approved_at' => fn (Blueprint $t) => $t->timestamp('class_approved_at')->nullable(),
            'class_remark' => fn (Blueprint $t) => $t->string('class_remark', 500)->nullable(),
            'booked_class' => fn (Blueprint $t) => $t->string('booked_class', 20)->nullable(),
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
