<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ODO-BD-01: VVNR had no odo_backdate_settings table, so the back-dated odometer rule could not be
 * set up or applied for VVNR. Same structure as VNR's table; VVNR admins set the departments.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            if (Schema::connection('expense_vvnr')->hasTable('odo_backdate_settings')) {
                return;
            }
            Schema::connection('expense_vvnr')->create('odo_backdate_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('department_id')->unique();
                $t->boolean('is_active')->default(false);
                $t->enum('approval_type', ['bu_level', 'hod_level'])->nullable();
                $t->date('effective_date')->nullable();
                $t->integer('delayed_day')->default(0);
                $t->string('verticals', 50)->nullable();
                $t->timestamps();
            });
        } catch (\Throwable $e) {
            Log::warning('VVNR odo_backdate_settings not created: '.$e->getMessage());
        }
    }

    public function down(): void
    {
    }
};
