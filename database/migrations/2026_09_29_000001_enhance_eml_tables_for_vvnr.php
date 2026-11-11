<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTION = 'expense_vvnr';

    /**
     * Run the migrations on the VVNR database (expense_vvnr).
     */
    public function up(): void
    {
        $conn = self::CONNECTION;

        // 1. Email Templates Table on VVNR DB
        if (!Schema::connection($conn)->hasTable('eml_email_templates')) {
            Schema::connection($conn)->create('eml_email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('subject', 255);
                $table->longText('body_html');
                $table->text('body_text')->nullable();
                $table->string('category', 50)->default('expense');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // 2. Notification Triggers & Schedules Table on VVNR DB
        if (!Schema::connection($conn)->hasTable('eml_notification')) {
            Schema::connection($conn)->create('eml_notification', function (Blueprint $table) {
                $table->id();
                $table->string('notification_type', 50)->index();
                $table->string('stage', 50)->nullable();
                $table->integer('send_day')->default(1)->comment('Day of month (1-31)');
                $table->unsignedBigInteger('email_template_id')->nullable();
                $table->text('department_ids')->nullable()->comment('JSON array of target department IDs, null for all');
                $table->text('grade_ids')->nullable()->comment('JSON array of target grade IDs, null for all');
                $table->boolean('is_active')->default(true);
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        } else {
            Schema::connection($conn)->table('eml_notification', function (Blueprint $table) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('eml_notification', 'department_ids')) {
                    $table->text('department_ids')->nullable()->after('email_template_id');
                }
                if (!Schema::connection($conn)->hasColumn('eml_notification', 'grade_ids')) {
                    $table->text('grade_ids')->nullable()->after('department_ids');
                }
            });
        }

        // 3. Recipient Email Control Table on VVNR DB
        if (!Schema::connection($conn)->hasTable('eml_email_control')) {
            Schema::connection($conn)->create('eml_email_control', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('employee_id')->unique();
                $table->string('emp_code', 50)->nullable();
                $table->string('employee_name', 200)->nullable();
                $table->string('department', 150)->nullable();
                $table->string('email', 200)->nullable();
                $table->boolean('is_active')->default(true)->comment('Master switch: allow / block all');
                $table->boolean('receive_submission_reminder')->default(true);
                $table->boolean('receive_receiving_pending')->default(true);
                $table->boolean('receive_escalation')->default(true);
                $table->boolean('receive_approval_notification')->default(true);
                $table->boolean('receive_payment_notification')->default(true);
                $table->string('reason', 500)->nullable()->comment('Reason for exemption / block');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // 4. Email Send Log Table on VVNR DB
        if (!Schema::connection($conn)->hasTable('eml_send_log')) {
            Schema::connection($conn)->create('eml_send_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('template_name', 100)->nullable();
                $table->string('notification_type', 50)->index();
                $table->string('mail_category', 50)->default('expense');
                $table->string('stage', 50)->nullable();
                $table->string('subject', 255);
                $table->longText('body_html')->nullable();
                $table->text('body_text')->nullable();
                $table->unsignedInteger('recipient_employee_id')->nullable()->index();
                $table->string('recipient_emp_code', 50)->nullable()->index();
                $table->string('recipient_name', 200)->nullable();
                $table->string('recipient_email', 200)->index();
                $table->string('cc_emails', 500)->nullable();
                $table->string('bcc_emails', 500)->nullable();
                $table->unsignedInteger('sender_employee_id')->nullable();
                $table->string('sender_emp_code', 50)->nullable();
                $table->string('sender_name', 200)->nullable();
                $table->string('sent_for_month', 10)->nullable();
                $table->integer('year_id')->nullable();
                $table->integer('company_id')->default(11);
                $table->string('ref_type', 50)->nullable();
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->boolean('is_sent')->default(false)->index();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        // 5. Seed default VVNR templates and trigger rules onto VVNR DB
        $this->seedVvnrDefaults($conn);
    }

    /**
     * Reverse the migrations on the VVNR database.
     */
    public function down(): void
    {
        $conn = self::CONNECTION;
        Schema::connection($conn)->dropIfExists('eml_send_log');
        Schema::connection($conn)->dropIfExists('eml_email_control');
        Schema::connection($conn)->dropIfExists('eml_notification');
        Schema::connection($conn)->dropIfExists('eml_email_templates');
    }

    private function seedVvnrDefaults(string $conn): void
    {
        $existing = DB::connection($conn)->table('eml_email_templates')->where('name', 'vvnr_submission_reminder')->first();
        if (!$existing) {
            $templateSubmissionId = DB::connection($conn)->table('eml_email_templates')->insertGetId([
                'name'        => 'vvnr_submission_reminder',
                'subject'     => 'VVNR: Monthly Expense Claim Submission Reminder - {{month}}',
                'body_html'   => '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;">
                    <div style="background-color: #2b5c8f; color: #fff; padding: 15px; border-radius: 5px 5px 0 0;">
                        <h2 style="margin: 0; font-size: 18px;">VVNR Expense Claim Submission Reminder</h2>
                    </div>
                    <div style="border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px; background: #fff;">
                        <p>Dear <strong>{{employee_name}}</strong> ({{emp_code}}),</p>
                        <p>This is a gentle reminder to submit your monthly expense claims for <strong>{{month}}</strong> in Xeasy portal.</p>
                        <p>Please note that non-submitted expenses after the deadline may lead to delay in verification and payment processing.</p>
                        <div style="background: #f8f9fa; border-left: 4px solid #2b5c8f; padding: 12px; margin: 15px 0;">
                            <strong>Expense Month:</strong> {{month}}<br>
                            <strong>Company:</strong> VVNR<br>
                            <strong>Portal:</strong> <a href="{{login_url}}" style="color: #2b5c8f; text-decoration: underline;">Open Xeasy Portal</a>
                        </div>
                        <p>Please log in and submit your claim at the earliest.</p>
                        <br>
                        <p style="margin-bottom: 0;">Regards,<br><strong>VVNR Accounts & Admin Team</strong></p>
                    </div>
                </div>',
                'body_text'   => 'Dear {{employee_name}}, this is a reminder to submit your monthly expense claim for {{month}} in Xeasy portal.',
                'is_active'   => 1,
                'category'    => 'expense',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $templateCourierId = DB::connection($conn)->table('eml_email_templates')->insertGetId([
                'name'        => 'vvnr_pending_courier',
                'subject'     => 'VVNR: Physical Documents / Courier Receipt Pending - {{month}}',
                'body_html'   => '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;">
                    <div style="background-color: #d97724; color: #fff; padding: 15px; border-radius: 5px 5px 0 0;">
                        <h2 style="margin: 0; font-size: 18px;">VVNR Courier Receipt Pending</h2>
                    </div>
                    <div style="border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px; background: #fff;">
                        <p>Dear <strong>{{employee_name}}</strong> ({{emp_code}}),</p>
                        <p>Your submitted expense claim for the month of <strong>{{month}}</strong> has not yet been physically received by the verification team.</p>
                        <div style="background: #fff8f0; border-left: 4px solid #d97724; padding: 12px; margin: 15px 0;">
                            <strong>Claim Month:</strong> {{month}}<br>
                            <strong>Status:</strong> Physical Hardcopy / Courier Pending<br>
                            <strong>Action Required:</strong> Dispatch your physical bills & vouchers with claim summary or update courier details in Xeasy.
                        </div>
                        <p>Please ensure documents are dispatched immediately to avoid settlement delays.</p>
                        <br>
                        <p style="margin-bottom: 0;">Regards,<br><strong>VVNR Audit & Verification Team</strong></p>
                    </div>
                </div>',
                'body_text'   => 'Dear {{employee_name}}, physical bills for your {{month}} claim are pending courier receipt. Please dispatch them immediately.',
                'is_active'   => 1,
                'category'    => 'expense',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $templateEscalationId = DB::connection($conn)->table('eml_email_templates')->insertGetId([
                'name'        => 'vvnr_courier_escalation',
                'subject'     => 'VVNR Escalation: Pending Physical Documents for your Team - {{month}}',
                'body_html'   => '<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;">
                    <div style="background-color: #c0392b; color: #fff; padding: 15px; border-radius: 5px 5px 0 0;">
                        <h2 style="margin: 0; font-size: 18px;">VVNR Escalation: Team Courier Documents Pending</h2>
                    </div>
                    <div style="border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px; background: #fff;">
                        <p>Dear <strong>{{manager_name}}</strong>,</p>
                        <p>The following team members under your reporting have not yet submitted their physical expense claim documents for <strong>{{month}}</strong>:</p>
                        <div style="margin: 15px 0;">
                            {{employee_table}}
                        </div>
                        <p>Kindly follow up with the concerned team members to have their physical documents sent to head office.</p>
                        <br>
                        <p style="margin-bottom: 0;">Regards,<br><strong>VVNR Finance & Audit Team</strong></p>
                    </div>
                </div>',
                'body_text'   => 'Dear Manager, physical claim documents are pending for members of your team for month {{month}}.',
                'is_active'   => 1,
                'category'    => 'expense',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Default trigger rules on VVNR DB
            DB::connection($conn)->table('eml_notification')->insert([
                [
                    'notification_type' => 'submission_reminder',
                    'stage'             => 'non_submitted',
                    'send_day'          => 7,
                    'email_template_id' => $templateSubmissionId,
                    'is_active'         => 1,
                    'description'       => 'VVNR Non-submitted claims monthly reminder (Day 7)',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ],
                [
                    'notification_type' => 'pending_hardcopy',
                    'stage'             => 'submitted_not_received',
                    'send_day'          => 15,
                    'email_template_id' => $templateCourierId,
                    'is_active'         => 1,
                    'description'       => 'VVNR Courier document pending reminder (Day 15)',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ],
                [
                    'notification_type' => 'pending_hardcopy_escalation',
                    'stage'             => 'escalation_to_manager',
                    'send_day'          => 22,
                    'email_template_id' => $templateEscalationId,
                    'is_active'         => 1,
                    'description'       => 'VVNR Courier overdue escalation to reporting managers (Day 22)',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ],
            ]);
        }
    }
};
