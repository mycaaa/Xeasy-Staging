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
        Schema::create('advance_request_master_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_request_master_id')->nullable();
            $table->unsignedBigInteger('advance_type_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('action'); // 'created', 'updated', 'activated', 'deactivated'
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('changes_summary')->nullable();
            $table->unsignedBigInteger('changed_by');
            $table->timestamps();
            
            $table->foreign('advance_request_master_id')->references('id')->on('advance_request_masters')->onDelete('cascade');
            $table->foreign('advance_type_id')->references('id')->on('advance_request_types')->onDelete('cascade');
            // Note: changed_by references hrm_employee but we skip foreign key due to cross-database constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_request_master_logs');
    }
};
