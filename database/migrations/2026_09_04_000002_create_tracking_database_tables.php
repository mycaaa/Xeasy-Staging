<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations on the 'tracking' database connection.
     */
    public function up(): void
    {
        $connection = 'tracking';

        if (!Schema::connection($connection)->hasTable('tracking_sessions')) {
            Schema::connection($connection)->create('tracking_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('employee_id')->index('idx_ts_emp_date');
                $table->date('tracking_date');
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->decimal('start_latitude', 10, 7)->nullable();
                $table->decimal('start_longitude', 10, 7)->nullable();
                $table->decimal('end_latitude', 10, 7)->nullable();
                $table->decimal('end_longitude', 10, 7)->nullable();
                $table->decimal('total_distance_km', 10, 3)->default(0.000);
                $table->unsignedInteger('moving_seconds')->default(0);
                $table->unsignedInteger('stopped_seconds')->default(0);
                $table->unsignedInteger('unclassified_seconds')->default(0);
                $table->unsignedInteger('locations_count')->default(0);
                $table->unsignedInteger('valid_locations_count')->default(0);
                $table->unsignedSmallInteger('stops_count')->default(0);
                $table->string('status', 20)->default('active');
                $table->string('ended_by', 20)->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->text('processing_error')->nullable();
                $table->unsignedTinyInteger('start_battery')->nullable();
                $table->unsignedTinyInteger('end_battery')->nullable();
                $table->decimal('start_accuracy', 8, 2)->nullable();
                $table->decimal('end_accuracy', 8, 2)->nullable();
                $table->string('device_info', 191)->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'started_at'], 'idx_ts_emp_started');
                $table->index(['tracking_date', 'status'], 'idx_ts_date_status');
            });
        }

        if (!Schema::connection($connection)->hasTable('tracking_locations')) {
            Schema::connection($connection)->create('tracking_locations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('uuid', 36)->unique('uniq_tl_uuid');
                $table->unsignedBigInteger('tracking_session_id');
                $table->integer('employee_id');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->decimal('speed', 8, 2)->nullable();
                $table->unsignedTinyInteger('battery')->nullable();
                $table->dateTime('recorded_at');
                $table->dateTime('received_at');
                $table->timestamp('created_at')->nullable();

                $table->index(['tracking_session_id', 'recorded_at'], 'idx_tl_session_time');
                $table->index(['employee_id', 'recorded_at'], 'idx_tl_emp_time');
            });
        }

        if (!Schema::connection($connection)->hasTable('tracking_stops')) {
            Schema::connection($connection)->create('tracking_stops', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tracking_session_id');
                $table->integer('employee_id');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->dateTime('arrival_at');
                $table->dateTime('departure_at');
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->unsignedSmallInteger('samples_count')->default(0);
                $table->unsignedSmallInteger('radius_meters')->default(0);
                $table->string('address', 500)->nullable();
                $table->dateTime('geocoded_at')->nullable();
                $table->string('geocode_status', 30)->nullable();
                $table->unsignedSmallInteger('sequence')->default(0);
                $table->timestamps();

                $table->index(['tracking_session_id', 'arrival_at'], 'idx_tst_session_arrival');
                $table->index(['employee_id', 'arrival_at'], 'idx_tst_emp_arrival');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = 'tracking';
        Schema::connection($connection)->dropIfExists('tracking_stops');
        Schema::connection($connection)->dropIfExists('tracking_locations');
        Schema::connection($connection)->dropIfExists('tracking_sessions');
    }
};
