<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eml_send_log', function (Blueprint $table) {
            $table->id();
            
            // Template information
            $table->unsignedBigInteger('template_id')->nullable()->comment('FK to eml_email_templates');
            $table->string('template_name', 200)->nullable();
            
            // Email categorization
            $table->string('notification_type', 100)->index()->comment('expense_submission, claim_approved, advance_request, etc.');
            $table->string('mail_category', 50)->nullable()->comment('expense, advance, payment');
            $table->string('stage', 50)->nullable()->comment('Stage or escalation level');
            
            // Email content
            $table->string('subject', 500);
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            
            // Recipient information
            $table->unsignedInteger('recipient_employee_id')->nullable()->index();
            $table->string('recipient_emp_code', 50)->nullable();
            $table->string('recipient_name', 200)->nullable();
            $table->string('recipient_email', 200);
            $table->text('cc_emails')->nullable()->comment('Comma-separated CC addresses');
            $table->text('bcc_emails')->nullable();
            
            // Sender/trigger information
            $table->unsignedBigInteger('sender_employee_id')->nullable()->comment('Who triggered the send');
            $table->string('sender_emp_code', 50)->nullable();
            $table->string('sender_name', 200)->nullable();
            
            // Context information
            $table->string('sent_for_month', 7)->nullable()->index()->comment('YYYY-MM format');
            $table->unsignedTinyInteger('year_id')->nullable()->index();
            $table->unsignedTinyInteger('company_id')->nullable()->index()->comment('1=VNR, 11=VVNR');
            
            // Reference to related records
            $table->string('ref_type', 50)->nullable()->comment('expense_claim, advance_request, etc.');
            $table->unsignedBigInteger('ref_id')->nullable()->comment('ExpId, advance_request.id, etc.');
            $table->index(['ref_type', 'ref_id']);
            
            // Send status
            $table->boolean('is_sent')->default(false)->index()->comment('Actually sent or just logged');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            
            // Metadata
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eml_send_log');
    }
};
