<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker #29: employee notification mails are queued (QUEUE_CONNECTION=database
 * by default) but the `expense` DB has no jobs / failed_jobs tables. Standard
 * Laravel 12 queue tables, created only when missing.
 *
 *   php artisan migrate --path=database/migrations/2026_11_05_000004_create_queue_tables_if_missing.php
 *   then run a worker: php artisan queue:work --queue=default --tries=3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Drop only empty tables, so pending or failed mails are never lost.
        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            if (Schema::hasTable($table) && ! \Illuminate\Support\Facades\DB::table($table)->exists()) {
                Schema::drop($table);
            }
        }
    }
};
