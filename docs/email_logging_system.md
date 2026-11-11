# Email Logging System

## Overview

The complete email logging system captures **every outgoing email** with all relevant context. Every `Mail::to()` call is now followed by a log entry that stores who received it, what the subject was, the body content, which employee triggered it, what it relates to (expense claim, advance request), and whether it was successfully sent.

---

## Database Structure

### Table: `eml_send_log`

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint | Primary key |
| **Template** | | |
| `template_id` | bigint | FK to `eml_email_templates` |
| `template_name` | varchar(200) | Template name snapshot |
| **Categorization** | | |
| `notification_type` | varchar(100) | e.g., `expense_submission`, `claim_approved`, `advance_request` |
| `mail_category` | varchar(50) | `expense`, `advance`, `payment`, `general` |
| `stage` | varchar(50) | Stage/escalation level (1, 2, `pending_verification`, etc.) |
| **Content** | | |
| `subject` | varchar(500) | Actual email subject |
| `body_html` | text | Full HTML body sent |
| `body_text` | text | Plain text version |
| **Recipient** | | |
| `recipient_employee_id` | int | Employee who received the email |
| `recipient_emp_code` | varchar(50) | Employee code |
| `recipient_name` | varchar(200) | Full name |
| `recipient_email` | varchar(200) | Email address |
| `cc_emails` | text | Comma-separated CC addresses |
| `bcc_emails` | text | Comma-separated BCC addresses |
| **Sender/Trigger** | | |
| `sender_employee_id` | bigint | Who triggered the send (logged-in user) |
| `sender_emp_code` | varchar(50) | Sender employee code |
| `sender_name` | varchar(200) | Sender full name |
| **Context** | | |
| `sent_for_month` | varchar(7) | `YYYY-MM` (e.g., `2025-03`) |
| `year_id` | tinyint | Financial year ID |
| `company_id` | tinyint | `1` = VNR, `11` = VVNR |
| **Reference** | | |
| `ref_type` | varchar(50) | `expense_claim`, `advance_request` |
| `ref_id` | bigint | ExpId, advance_request.id, etc. |
| **Status** | | |
| `is_sent` | boolean | `true` = actually sent, `false` = logged only (MAIL_ACTIVE=false) |
| `sent_at` | timestamp | When email was sent |
| `failed_at` | timestamp | If send threw an exception |
| `error_message` | text | Exception message if failed |
| `created_at`, `updated_at` | timestamps | Standard |

---

## Service: `EmailLogService`

### Location
`app/Services/EmailLogService.php`

### Usage

```php
// Basic usage — right after Mail::to()->send()
app(EmailLogService::class)->log([
    'notification_type' => 'expense_submission',
    'subject'           => $template->subject,
    'recipient_email'   => $employee->email,
    'recipient_employee_id' => $employee->EmployeeId,
    'is_sent'           => true,
]);

// Shortcut for successful sends
app(EmailLogService::class)->logSent([
    'notification_type'     => 'claim_approved',
    'subject'               => $subject,
    'recipient_employee_id' => $empId,
    'recipient_email'       => $email,
    'ref_type'              => 'expense_claim',
    'ref_id'                => $expId,
]);

// Log a failed send
app(EmailLogService::class)->logFailed([
    'notification_type' => 'advance_request',
    'recipient_email'   => $email,
], $exception->getMessage());
```

### Methods

| Method | Purpose |
|---|---|
| `log(array $data)` | Log any email (sent or not) |
| `logSent(array $data)` | Shortcut: log a successful send |
| `logFailed(array $data, string $error)` | Shortcut: log a failed send |

---

## Model: `EmlSendLog`

**Location:** `app/Models/EmlSendLog.php`

```php
// Query sent emails
$sentEmails = EmlSendLog::sent()
    ->where('notification_type', 'expense_submission')
    ->where('sent_for_month', '2025-03')
    ->get();

// Query failed emails
$failedEmails = EmlSendLog::failed()
    ->where('recipient_employee_id', 12345)
    ->get();
```

---

## Updated Controllers

### 1. `NotificationController`
All 3 scheduled email sends (escalation, pending_hardcopy, expense_submission) now log to `eml_send_log`.

- **Legacy log preserved:** Still writes to `eml_stage_email_logs` for backward compatibility.
- **New log added:** Writes to `eml_send_log` with full details.

### 2. `VerifierController::sendApproverNotification()`
Logs claim approval notifications sent to approvers.

- **On success:** logs to `eml_send_log` with `is_sent=true`
- **On exception:** logs to `eml_send_log` with `is_sent=false`, `error_message` populated

### 3. `AdvanceApprovalController`
4 different email sends now logged:
- Finance submission (to `srexe1.accounts@vnrseeds.com` with CC)
- Employee approval email
- Status update to requester (approval/rejection/revert)
- Next level approver notification

### 4. `AdvanceRequestController`
2 email sends now logged:
- Approver notification on submission
- Employee notification on submission

---

## Notification Types (by category)

### Expense
- `expense_submission` — Reminder to submit claims
- `pending_hardcopy` — Hardcopy not received
- `pending_hardcopy_escalation` — Escalated to manager
- `claim_approval_notification` — Sent to approver when verifier submits

### Advance
- `advance_submission` — Advance request submitted to approver
- `advance_employee_submission` — Confirmation to employee that request was submitted
- `advance_approval` — Approval notification to employee
- `advance_status_update` — Status update to requester (approval/rejection/revert)
- `advance_next_level_approval` — Next level approver notification
- `advance_finance_submission` — Finance team notification

### Payment
- (Not yet implemented — add when payment emails are sent)

---

## Migration

**File:** `database/migrations/2025_01_22_100000_create_eml_send_log_table.php`

**Run on server:**
```bash
php artisan migrate
```

---

## Backward Compatibility

The legacy `eml_stage_email_logs` table is **still being written to** by `NotificationController` for backward compatibility. Once confirmed that `eml_send_log` is working as expected in production, the legacy inserts can be removed.

---

## Reports & Queries

### Example: All emails sent to an employee
```sql
SELECT * FROM eml_send_log 
WHERE recipient_employee_id = 12345 
ORDER BY sent_at DESC;
```

### Example: Failed emails in the last 7 days
```sql
SELECT * FROM eml_send_log 
WHERE is_sent = 0 
AND failed_at >= NOW() - INTERVAL 7 DAY 
ORDER BY failed_at DESC;
```

### Example: Advance emails for a specific request
```sql
SELECT * FROM eml_send_log 
WHERE ref_type = 'advance_request' 
AND ref_id = 123 
ORDER BY created_at;
```

### Example: Monthly email volume by type
```sql
SELECT 
    notification_type, 
    COUNT(*) as total_sent,
    SUM(CASE WHEN is_sent = 1 THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN is_sent = 0 THEN 1 ELSE 0 END) as failed
FROM eml_send_log
WHERE sent_for_month = '2025-03'
GROUP BY notification_type
ORDER BY total_sent DESC;
```

---

## Future Enhancements

1. **Payment notification emails** — When implemented, add logging
2. **Email resend feature** — Use `eml_send_log` to retry failed sends
3. **Email analytics dashboard** — Volume, success rate, top recipients
4. **Deprecate `eml_stage_email_logs`** — After confirming stability in production
5. **Attachment tracking** — Add `attachments` JSON column if needed
