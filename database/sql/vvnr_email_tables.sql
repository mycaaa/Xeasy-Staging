-- =============================================================================
-- VVNR Dedicated Email Automation & Management Tables
-- Target Database: vnressus_expense (or your VVNR database)
-- =============================================================================

USE `vnressus_expense`;

-- 1. VVNR Email Templates Table
CREATE TABLE IF NOT EXISTS `eml_email_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` LONGTEXT NOT NULL,
  `body_text` TEXT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'expense',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. VVNR Notification Triggers & Schedules Table
CREATE TABLE IF NOT EXISTS `eml_notification` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `notification_type` VARCHAR(50) NOT NULL,
  `stage` VARCHAR(50) NULL,
  `send_day` INT NOT NULL DEFAULT 1 COMMENT 'Day of month (1-31)',
  `email_template_id` BIGINT UNSIGNED NULL,
  `department_ids` TEXT NULL COMMENT 'JSON/CSV array of target department IDs, null for all',
  `grade_ids` TEXT NULL COMMENT 'JSON/CSV array of target grade IDs, null for all',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_notif_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional Migration patch if table already created:
-- ALTER TABLE `eml_notification` ADD COLUMN `department_ids` TEXT NULL AFTER `email_template_id`;
-- ALTER TABLE `eml_notification` ADD COLUMN `grade_ids` TEXT NULL AFTER `department_ids`;

-- 3. VVNR Recipient Email Control Table (Opt-in / Opt-out permissions)
CREATE TABLE IF NOT EXISTS `eml_email_control` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED NOT NULL UNIQUE,
  `emp_code` VARCHAR(50) NULL,
  `employee_name` VARCHAR(200) NULL,
  `department` VARCHAR(150) NULL,
  `email` VARCHAR(200) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Master switch: 1=allow, 0=block all',
  `receive_submission_reminder` TINYINT(1) NOT NULL DEFAULT 1,
  `receive_receiving_pending` TINYINT(1) NOT NULL DEFAULT 1,
  `receive_escalation` TINYINT(1) NOT NULL DEFAULT 1,
  `receive_approval_notification` TINYINT(1) NOT NULL DEFAULT 1,
  `receive_payment_notification` TINYINT(1) NOT NULL DEFAULT 1,
  `reason` VARCHAR(500) NULL COMMENT 'Reason for exemption / blocking',
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_emp_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. VVNR Email Send Log Table (Audit trail)
CREATE TABLE IF NOT EXISTS `eml_send_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `template_id` BIGINT UNSIGNED NULL,
  `template_name` VARCHAR(100) NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `mail_category` VARCHAR(50) NOT NULL DEFAULT 'expense',
  `stage` VARCHAR(50) NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` LONGTEXT NULL,
  `body_text` TEXT NULL,
  `recipient_employee_id` INT UNSIGNED NULL,
  `recipient_emp_code` VARCHAR(50) NULL,
  `recipient_name` VARCHAR(200) NULL,
  `recipient_email` VARCHAR(200) NOT NULL,
  `cc_emails` VARCHAR(500) NULL,
  `bcc_emails` VARCHAR(500) NULL,
  `sender_employee_id` INT UNSIGNED NULL,
  `sender_emp_code` VARCHAR(50) NULL,
  `sender_name` VARCHAR(200) NULL,
  `sent_for_month` VARCHAR(10) NULL,
  `year_id` INT NULL,
  `company_id` INT NOT NULL DEFAULT 11,
  `ref_type` VARCHAR(50) NULL,
  `ref_id` BIGINT UNSIGNED NULL,
  `is_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `sent_at` TIMESTAMP NULL,
  `failed_at` TIMESTAMP NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_notif_type` (`notification_type`),
  INDEX `idx_recip_emp` (`recipient_employee_id`),
  INDEX `idx_recip_email` (`recipient_email`),
  INDEX `idx_is_sent` (`is_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. Seed Default VVNR Email Templates
-- =============================================================================

INSERT IGNORE INTO `eml_email_templates` (`id`, `name`, `subject`, `body_html`, `body_text`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'vvnr_submission_reminder', 'VVNR: Monthly Expense Claim Submission Reminder - {{month}}',
'<div style=\"font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;\">
  <div style=\"background-color: #2b5c8f; color: #fff; padding: 15px; border-radius: 5px 5px 0 0;\">
    <h2 style=\"margin: 0; font-size: 18px;\">VVNR Expense Claim Submission Reminder</h2>
  </div>
  <div style=\"border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px; background: #fff;\">
    <p>Dear <strong>{{employee_name}}</strong> ({{emp_code}}),</p>
    <p>This is a gentle reminder to submit your monthly expense claims for <strong>{{month}}</strong> in Xeasy portal.</p>
    <p>Please note that non-submitted expenses after the deadline may lead to delay in verification and payment processing.</p>
    <div style=\"background: #f8f9fa; border-left: 4px solid #2b5c8f; padding: 12px; margin: 15px 0;\">
      <strong>Expense Month:</strong> {{month}}<br>
      <strong>Company:</strong> VVNR<br>
      <strong>Portal:</strong> <a href=\"{{login_url}}\" style=\"color: #2b5c8f; text-decoration: underline;\">Open Xeasy Portal</a>
    </div>
    <p>Please log in and submit your claim at the earliest.</p>
    <br>
    <p style=\"margin-bottom: 0;\">Regards,<br><strong>VVNR Accounts & Admin Team</strong></p>
  </div>
</div>',
'Dear {{employee_name}}, this is a reminder to submit your monthly expense claim for {{month}} in Xeasy portal.',
'expense', 1, NOW(), NOW()),

(2, 'vvnr_pending_courier', 'VVNR: Physical Documents / Courier Receipt Pending - {{month}}',
'<div style=\"font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;\">
  <div style=\"background-color: #d97724; color: #fff; padding: 15px; border-radius: 5px 5px 0 0;\">
    <h2 style=\"margin: 0; font-size: 18px;\">VVNR Courier Receipt Pending</h2>
  </div>
  <div style=\"border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px; background: #fff;\">
    <p>Dear <strong>{{employee_name}}</strong> ({{emp_code}}),</p>
    <p>Your submitted expense claim for the month of <strong>{{month}}</strong> has not yet been physically received by the verification team.</p>
    <div style=\"background: #fff8f0; border-left: 4px solid #d97724; padding: 12px; margin: 15px 0;\">
      <strong>Claim Month:</strong> {{month}}<br>
      <strong>Status:</strong> Physical Hardcopy / Courier Pending<br>
      <strong>Action Required:</strong> Dispatch your physical bills & vouchers with claim summary or update courier details in Xeasy.
    </div>
    <p>Please ensure documents are dispatched immediately to avoid settlement delays.</p>
    <br>
    <p style=\"margin-bottom: 0;\">Regards,<br><strong>VVNR Audit & Verification Team</strong></p>
  </div>
</div>',
'Dear {{employee_name}}, physical bills for your {{month}} claim are pending courier receipt. Please dispatch them immediately.',
'expense', 1, NOW(), NOW()),

(3, 'vvnr_courier_escalation', 'VVNR Escalation: Pending Physical Documents for your Team - {{month}}',
'<div style=\"font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6;\">
  <div style=\"background-color: #c0392b; color: #fff; padding: 15px; border-radius: 5px 5px 0 0;\">
    <h2 style=\"margin: 0; font-size: 18px;\">VVNR Escalation: Team Courier Documents Pending</h2>
  </div>
  <div style=\"border: 1px solid #ddd; border-top: none; padding: 20px; border-radius: 0 0 5px 5px; background: #fff;\">
    <p>Dear <strong>{{manager_name}}</strong>,</p>
    <p>The following team members under your reporting have not yet submitted their physical expense claim documents for <strong>{{month}}</strong>:</p>
    <div style=\"margin: 15px 0;\">
      {{employee_table}}
    </div>
    <p>Kindly follow up with the concerned team members to have their physical documents sent to head office.</p>
    <br>
    <p style=\"margin-bottom: 0;\">Regards,<br><strong>VVNR Finance & Audit Team</strong></p>
  </div>
</div>',
'Dear Manager, physical claim documents are pending for members of your team for month {{month}}.',
'expense', 1, NOW(), NOW());

-- =============================================================================
-- 6. Seed Default VVNR Notification Triggers
-- =============================================================================

INSERT IGNORE INTO `eml_notification` (`id`, `notification_type`, `stage`, `send_day`, `email_template_id`, `is_active`, `description`, `created_at`, `updated_at`) VALUES
(1, 'submission_reminder', 'non_submitted', 7, 1, 1, 'VVNR Non-submitted claims monthly reminder (Day 7)', NOW(), NOW()),
(2, 'pending_hardcopy', 'submitted_not_received', 15, 2, 1, 'VVNR Courier document pending reminder (Day 15)', NOW(), NOW()),
(3, 'pending_hardcopy_escalation', 'escalation_to_manager', 22, 3, 1, 'VVNR Courier overdue escalation to reporting managers (Day 22)', NOW(), NOW());
