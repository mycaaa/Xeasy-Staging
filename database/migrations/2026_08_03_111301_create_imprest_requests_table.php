<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-001: Imprest Automation
 * Tracks auto-generated imprest requests for new joiners and promotions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('imprest_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('emp_code', 30)->nullable();
            $table->string('emp_name', 150)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('grade', 30)->nullable();
            $table->string('trigger_type', 30);      // 'new_joiner' | 'promotion'
            $table->string('previous_grade', 30)->nullable();  // for promotion
            $table->decimal('eligible_amount', 12, 2);         // e.g. 20000 or 40000
            $table->decimal('previous_amount', 12, 2)->default(0); // for promotion delta
            $table->decimal('request_amount', 12, 2);           // amount to release
            $table->string('status', 20)->default('pending');   // pending|approved|rejected
            $table->text('remarks')->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('status');
            $table->index('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('imprest_requests');
    }
};
