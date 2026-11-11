<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Matrix Payment Instructions Table
        if (!Schema::hasTable('matrix_payment_instructions')) {
            Schema::create('matrix_payment_instructions', function (Blueprint $table) {
                $table->id();
                
                // Stable unique idempotency key: REIMB-{yearId}-{id} or ADV-{id}
                $table->string('source_ref', 100)->unique();
                $table->enum('payment_type', ['reimbursement', 'advance'])->index();
                
                // Local resolution mapping
                $table->unsignedInteger('company_id')->default(1);
                $table->unsignedInteger('year_id')->nullable();
                $table->unsignedBigInteger('record_id'); // monthexpensefinal.id or advance_request_activities.id
                $table->string('batch_ref', 100)->nullable()->index();
                
                // Payee details
                $table->string('payee_focus_code', 50); // E0429 or V...
                $table->string('payee_name', 255)->nullable();
                $table->string('payee_type', 50)->default('employee');
                $table->string('beneficiary_account_number', 50)->nullable();
                $table->string('beneficiary_ifsc', 20)->nullable();
                $table->string('beneficiary_bank_name', 150)->nullable();
                
                // Amount & Mode
                $table->decimal('amount', 12, 2);
                $table->string('payment_mode', 50)->default('neft');
                $table->string('narration', 500)->nullable();
                $table->timestamp('instructed_at')->nullable();
                
                // Matrix response / state
                $table->unsignedBigInteger('matrix_id')->nullable(); // Matrix's internal integer ID
                $table->string('instruction_no', 100)->nullable()->index(); // e.g. PI-202610-000045
                $table->string('matrix_status', 50)->default('created')->index();
                // Matrix statuses: created, pending_approval, approved, batched, released, on_hold, paid, failed, rejected, cancelled
                $table->text('status_reason')->nullable();
                $table->string('utr_number', 100)->nullable()->index();
                $table->date('paid_on')->nullable();
                
                // Payloads for audit & debug
                $table->json('focus_payload')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->index(['payment_type', 'record_id', 'year_id'], 'idx_mpi_local_lookup');
            });
        }

        // 2. Matrix Sync State / Cursor Table
        if (!Schema::hasTable('matrix_sync_states')) {
            Schema::create('matrix_sync_states', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique(); // e.g. payment_updates_next_since
                $table->text('value')->nullable(); // e.g. ISO-8601 timestamp
                $table->string('status', 50)->default('idle'); // idle, running, error
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matrix_payment_instructions');
        Schema::dropIfExists('matrix_sync_states');
    }
};
