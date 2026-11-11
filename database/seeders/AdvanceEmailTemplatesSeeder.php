<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdvanceEmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'advance_submission',
                'subject' => 'New Advance Request Submitted - {{request_no}}',
                'body_html' => '
                    <h3>New Advance Request Submitted</h3>
                    <p>Dear Approver,</p>
                    <p>A new advance request has been submitted and requires your approval:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Submitted Date:</strong> {{submitted_date}}</li>
                        <li><strong>Current Level:</strong> {{current_level}}</li>
                    </ul>
                    <p>Please review and take appropriate action.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'New advance request {{request_no}} for amount ₹{{amount}} has been submitted and requires your approval.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_approved',
                'subject' => 'Advance Request Approved - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Approved</h3>
                    <p>Dear User,</p>
                    <p>Your advance request has been approved:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Original Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Status:</strong> {{status}}</li>
                    </ul>
                    <p>Your request is now proceeding to the next approval level.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Your advance request {{request_no}} has been approved and is proceeding to next level.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_rejected',
                'subject' => 'Advance Request Rejected - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Rejected</h3>
                    <p>Dear User,</p>
                    <p>Your advance request has been rejected:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Status:</strong> {{status}}</li>
                    </ul>
                    <p>Please contact your approver for more details.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Your advance request {{request_no}} has been rejected. Please contact your approver for details.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_reverted',
                'subject' => 'Advance Request Reverted - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Reverted</h3>
                    <p>Dear User,</p>
                    <p>Your advance request has been reverted for modifications:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Status:</strong> {{status}}</li>
                    </ul>
                    <p>Please make necessary changes and resubmit your request.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Your advance request {{request_no}} has been reverted. Please make changes and resubmit.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_pending_approval',
                'subject' => 'Advance Request Pending Your Approval - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Pending Approval</h3>
                    <p>Dear Approver,</p>
                    <p>An advance request is pending your approval:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Current Level:</strong> {{current_level}}</li>
                        <li><strong>Submission No:</strong> {{submission_no}}</li>
                    </ul>
                    <p>Please review and take appropriate action.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Advance request {{request_no}} is pending your approval. Please review and take action.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_finance_approved',
                'subject' => 'Advance Request Finance Approved - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Finance Approved</h3>
                    <p>Dear User,</p>
                    <p>Your advance request has been approved by Finance:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Original Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Status:</strong> Finance Approved</li>
                    </ul>
                    <p>Your advance will be processed for payment.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Your advance request {{request_no}} has been approved by Finance and will be processed for payment.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_finance_rejected',
                'subject' => 'Advance Request Finance Rejected - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Finance Rejected</h3>
                    <p>Dear User,</p>
                    <p>Your advance request has been rejected by Finance:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Status:</strong> Finance Rejected</li>
                    </ul>
                    <p>Please contact Finance department for more details.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Your advance request {{request_no}} has been rejected by Finance. Please contact Finance for details.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'advance_finance_reverted',
                'subject' => 'Advance Request Finance Reverted - {{request_no}}',
                'body_html' => '
                    <h3>Advance Request Finance Reverted</h3>
                    <p>Dear Approver,</p>
                    <p>An advance request has been reverted by Finance back to your level:</p>
                    <ul>
                        <li><strong>Request No:</strong> {{request_no}}</li>
                        <li><strong>Amount:</strong> ₹{{amount}}</li>
                        <li><strong>Purpose:</strong> {{purpose}}</li>
                        <li><strong>Status:</strong> Reverted from Finance</li>
                    </ul>
                    <p>Please review and take appropriate action.</p>
                    <p>Best regards,<br>Advance Management System</p>
                ',
                'body_text' => 'Advance request {{request_no}} has been reverted by Finance back to your approval level.',
                'is_active' => true,
                'category' => 'advance',
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('eml_email_templates')->insert(array_merge($template, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}