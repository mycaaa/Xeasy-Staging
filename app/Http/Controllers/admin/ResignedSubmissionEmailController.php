<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use App\Services\ResignedSubmissionEmailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResignedSubmissionEmailController extends Controller
{
    protected ResignedSubmissionEmailService $service;

    public function __construct(ResignedSubmissionEmailService $service)
    {
        $this->service = $service;
    }

    /**
     * Testing and management page view for resigned submission reminder emails.
     */
    public function testPage(Request $request): View
    {
        $companyFilter = $request->filled('company_id') ? (int) $request->input('company_id') : null;
        $pendingOnly   = $request->has('pending_only') ? $request->boolean('pending_only') : true;

        $employees = $this->service->getEligibleResignedEmployees(null, null, $companyFilter, null, $pendingOnly);
        $settings  = $this->service->getSettings();
        $template  = $this->service->getTemplate();

        $allResignedCount   = $this->service->getEligibleResignedEmployees(null, null, $companyFilter, null, false)->count();
        $pendingClaimsCount = $this->service->getEligibleResignedEmployees(null, null, $companyFilter, null, true)->count();
        $noPendingCount     = $allResignedCount - $pendingClaimsCount;

        $companies = [
            1  => 'VNR Seeds Pvt Ltd',
            2  => 'VNR Group Concern',
            3  => 'VNR Nursery Pvt Ltd',
            4  => 'Teamlease Services Ltd',
            11 => 'VVNR',
        ];

        $stats = [
            'total_resigned'       => $employees->count(),
            'all_resigned_count'   => $allResignedCount,
            'pending_claims_count' => $pendingClaimsCount,
            'no_pending_count'     => $noPendingCount,
            'total_sent'           => DB::table('eml_send_log')->where('notification_type', ResignedSubmissionEmailService::NOTIFICATION_TYPE)->where('is_sent', 1)->count(),
            'cron_reminders'       => (int) DB::table('eml_resigned_reminders')->sum('reminder_count'),
            'pending_reminders'    => $employees->where('reminder_count', 0)->count(),
        ];

        $firstEmployee = $employees->first();
        $samplePreview = $firstEmployee
            ? $this->service->renderEmailHtml($firstEmployee)
            : $this->service->renderEmailHtml('Sample Employee');

        return view('admin.notifications.resigned_submission_test', [
            'employees'          => $employees,
            'stats'              => $stats,
            'settings'           => $settings,
            'template'           => $template,
            'companies'          => $companies,
            'selectedCompany'    => $companyFilter,
            'pendingOnly'        => $pendingOnly,
            'samplePreview'      => $samplePreview,
            'firstEmployee'      => $firstEmployee,
            'defaultSubject'     => $template->subject ?: ResignedSubmissionEmailService::DEFAULT_SUBJECT,
        ]);
    }

    /**
     * API / Ajax: Get live preview of email template with dynamic employee variables.
     */
    public function preview(Request $request): JsonResponse
    {
        $employeeId   = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;
        $employeeName = $request->input('employee_name');
        $customHtml   = $request->input('body_html');

        $empObj = null;
        if ($employeeId) {
            $empObj = $this->service->getEligibleResignedEmployees($employeeId)->first();
        }

        $target = $empObj ?: ($employeeName ?: 'Employee Name');

        $html = $this->service->renderEmailHtml($target, $customHtml);
        $text = $this->service->renderEmailText($target);

        return response()->json([
            'success'       => true,
            'subject'       => $request->input('subject', $this->service->getTemplate()->subject),
            'employee_name' => is_object($target) ? $target->employee_name : $target,
            'html'          => $html,
            'text'          => $text,
        ]);
    }

    /**
     * API / Ajax: Send reminder email to single or all eligible employees across all companies.
     */
    public function send(Request $request): JsonResponse
    {
        $employeeId = $request->filled('employee_id') && $request->input('employee_id') !== 'all'
            ? (int) $request->input('employee_id')
            : null;
        $companyId  = $request->filled('company_id') && $request->input('company_id') !== 'all'
            ? (int) $request->input('company_id')
            : null;
        $testEmail  = $request->filled('test_email') ? trim($request->input('test_email')) : null;
        $isCron     = $request->boolean('is_cron', false);
        $subject    = $request->input('subject');

        if ($testEmail && !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid test email address provided: '{$testEmail}'",
            ], 422);
        }

        // Single Employee Send
        if ($employeeId) {
            $employee = $this->service->getEligibleResignedEmployees($employeeId, null, null, null, onlyPendingClaims: false)->first();
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => "Resigned employee with ID {$employeeId} was not found or is ineligible.",
                ], 404);
            }

            if (empty($employee->has_pending_claims)) {
                return response()->json([
                    'success' => false,
                    'message' => "Employee '{$employee->employee_name}' (ID: {$employeeId}) does NOT have any pending claims for final submission. Notification not required.",
                ], 422);
            }

            $result = $this->service->sendReminder($employee, $testEmail, $isCron, $subject);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data'    => $result,
            ], $result['success'] ? 200 : 500);
        }

        // Batch Send to All Eligible (across all companies or single selected company)
        $limit       = $request->filled('limit') ? (int) $request->input('limit') : null;
        $batchResult = $this->service->sendBatch([], $testEmail, $isCron, $limit, $companyId);

        return response()->json([
            'success' => $batchResult['sent_count'] > 0 || $batchResult['total'] === 0,
            'message' => "Processed {$batchResult['total']} employees. Sent: {$batchResult['sent_count']}, Skipped: {$batchResult['skipped_count']}, Failed: {$batchResult['failed_count']}.",
            'data'    => $batchResult,
        ]);
    }

    /**
     * API: Update Email Template (Subject, HTML body, plain text).
     */
    public function updateTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'subject'   => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        $subject   = $request->input('subject');
        $bodyHtml  = $request->input('body_html');
        $bodyText  = $request->input('body_text');
        $userId    = Auth::id();

        $saved = $this->service->updateTemplate($subject, $bodyHtml, $bodyText, $userId);

        return response()->json([
            'success' => $saved,
            'message' => $saved ? 'Email template updated successfully.' : 'Failed to update email template.',
        ]);
    }

    /**
     * API: Update Automation & Reminder Schedule Settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = [
            'is_active'                  => $request->boolean('is_active'),
            'send_time'                  => $request->input('send_time', '09:00'),
            'frequency'                  => $request->input('frequency', 'daily'),
            'min_days_between_reminders' => (int) $request->input('min_days_between_reminders', 3),
            'max_reminders_per_employee' => (int) $request->input('max_reminders_per_employee', 5),
            'target_companies'           => $request->input('target_companies', [1, 2, 3, 4, 11]),
            'days_before_separation'     => (int) $request->input('days_before_separation', 60),
            'query_email'                => trim($request->input('query_email', 'query@vnrseeds.com')),
        ];

        $userId = Auth::id();
        $saved  = $this->service->updateSettings($data, $userId);

        return response()->json([
            'success' => $saved,
            'message' => $saved ? 'Reminder automation settings saved successfully.' : 'Failed to update settings.',
            'data'    => $this->service->getSettings(),
        ]);
    }

    /**
     * API: Get eligible resigned employees list with optional company and search filters.
     */
    public function getEmployees(Request $request): JsonResponse
    {
        $search      = $request->input('search');
        $companyId   = $request->filled('company_id') && $request->input('company_id') !== 'all'
            ? (int) $request->input('company_id')
            : null;
        $pendingOnly = $request->has('pending_only') ? $request->boolean('pending_only') : true;

        $employees = $this->service->getEligibleResignedEmployees(null, $search, $companyId, null, $pendingOnly);

        return response()->json([
            'success'   => true,
            'total'     => $employees->count(),
            'employees' => $employees,
        ]);
    }

    /**
     * API: Get email logs with comprehensive filtering options.
     */
    public function getLogs(Request $request): JsonResponse
    {
        $query = DB::table('eml_send_log')
            ->where('notification_type', ResignedSubmissionEmailService::NOTIFICATION_TYPE);

        // Filter: Company
        if ($request->filled('company_id') && $request->input('company_id') !== 'all') {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        // Filter: Status (sent / failed)
        if ($request->filled('status') && $request->input('status') !== 'all') {
            if ($request->input('status') === 'sent') {
                $query->where('is_sent', 1);
            } elseif ($request->input('status') === 'failed') {
                $query->where('is_sent', 0);
            }
        }

        // Filter: Date Range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        // Filter: Search Keyword
        if ($request->filled('search')) {
            $kw = trim($request->input('search'));
            $query->where(function ($q) use ($kw) {
                $q->where('recipient_name', 'like', "%{$kw}%")
                    ->orWhere('recipient_emp_code', 'like', "%{$kw}%")
                    ->orWhere('recipient_email', 'like', "%{$kw}%")
                    ->orWhere('subject', 'like', "%{$kw}%");
            });
        }

        $limit = $request->input('limit', 50);
        $logs  = $query->orderBy('id', 'desc')->limit($limit)->get();

        // Attach company names
        $logs->transform(function ($item) {
            $item->company_name = DatabaseSwitcher::getCompanyName($item->company_id ?? 1);
            $item->created_formatted = Carbon::parse($item->created_at)->format('d-M-Y H:i');
            return $item;
        });

        return response()->json([
            'success' => true,
            'total'   => $logs->count(),
            'logs'    => $logs,
        ]);
    }

    /**
     * API: Get detailed HTML body for a single send log entry.
     */
    public function getLogDetail(int $id): JsonResponse
    {
        $log = DB::table('eml_send_log')->where('id', $id)->first();

        if (!$log) {
            return response()->json(['success' => false, 'message' => 'Log entry not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'log'     => [
                'id'              => $log->id,
                'recipient_name'  => $log->recipient_name,
                'recipient_email' => $log->recipient_email,
                'recipient_emp_code' => $log->recipient_emp_code,
                'company_name'    => DatabaseSwitcher::getCompanyName($log->company_id ?? 1),
                'subject'         => $log->subject,
                'body_html'       => $log->body_html,
                'is_sent'         => (bool) $log->is_sent,
                'error_message'   => $log->error_message,
                'created_at'      => Carbon::parse($log->created_at)->format('d-M-Y H:i:s'),
            ],
        ]);
    }

    /**
     * Download comprehensive API documentation in Markdown (.md) format.
     */
    public function downloadDocs(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $baseUrl = url('/');
        $markdown = <<<MARKDOWN
# Xeasy - Resigned Employees Final Expense Submission Reminder API & Cron Documentation

## 1. Overview
Automated system to notify resigned employees to perform final expense claims submission in Xeasy before their last working day.
Supports multi-company operations, rich HTML corporate templates, automated scheduled cron execution, delivery tracking, and detailed send logging.

### Key Business Condition:
> **Strict Filtering:** Reminders are ONLY sent and listed for resigned employees who have **unsubmitted expense claims pending for final submission**. Resigned employees with 0 pending claims are automatically skipped.

---

## 2. API Endpoints Reference

### 2.1 Send Reminder Email (Single or Batch)
- **Method:** `POST`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/send`
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`

#### Request Body Parameters:
| Field | Type | Required | Default | Description |
|---|---|---|---|---|
| `employee_id` | integer | Optional | `null` | Target employee ID. If omitted or set to null/"all", runs batch send for eligible employees. |
| `company_id` | integer | Optional | `null` | Filter by company (1=VNR Seeds, 2=Group Concern, 3=Nursery, 4=Teamlease, 11=VVNR). If omitted, processes all companies. |
| `test_email` | string | Optional | `null` | Override recipient email address (safely test delivery without notifying the employee). |
| `is_cron` | boolean | Optional | `false` | If `true`, increments `reminder_count` in `eml_resigned_reminders`. |
| `subject` | string | Optional | `null` | Custom email subject. Defaults to configured template subject. |
| `limit` | integer | Optional | `null` | Maximum employees to process in batch mode. |

#### cURL Examples:

**Single Employee Test Send:**
```bash
curl -X POST "{$baseUrl}/api/notifications/resigned-submission/send" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "employee_id": 1644,
    "test_email": "query@vnrseeds.com",
    "is_cron": true
  }'
```

**Batch Send Across All Companies (Live):**
```bash
curl -X POST "{$baseUrl}/api/notifications/resigned-submission/send" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "is_cron": true
  }'
```

#### Response Example (Success):
```json
{
  "success": true,
  "message": "Reminder successfully sent to Ajeet Singh (ajeetsingh.vspl@gmail.com).",
  "data": {
    "success": true,
    "message": "Reminder successfully sent to Ajeet Singh (ajeetsingh.vspl@gmail.com).",
    "employee_id": 1644,
    "employee_name": "Ajeet Singh",
    "email": "ajeetsingh.vspl@gmail.com",
    "reminder_count": 1,
    "trigger_type": "cron"
  }
}
```

---

### 2.2 Live Email Preview
- **Method:** `GET`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/preview`
- **Query Parameters:**
  - `employee_id` (int, optional): Populate variables for a specific employee.
  - `employee_name` (string, optional): Preview with custom name.
  - `subject` (string, optional): Subject line.

---

### 2.3 List Eligible Resigned Employees
- **Method:** `GET`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/employees`
- **Query Parameters:**
  - `company_id` (int, optional): Filter by company ID.
  - `search` (string, optional): Search keyword (name, code, email).
  - `pending_only` (bool, optional, default `true`): Only return employees with pending claims.

---

### 2.4 Query Email Send Logs
- **Method:** `GET`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/logs`
- **Query Parameters:**
  - `company_id` (int, optional)
  - `status` (`sent` | `failed` | `all`, optional)
  - `from_date` (`YYYY-MM-DD`, optional)
  - `to_date` (`YYYY-MM-DD`, optional)
  - `search` (string, optional)
  - `limit` (int, optional, default 50)

---

### 2.5 View Sent Email Content
- **Method:** `GET`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/logs/{id}`

---

### 2.6 Update Email Template
- **Method:** `POST`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/template`
- **Request Body:**
```json
{
  "subject": "Final Submission of Expense Claims in Xeasy Before Last Working Day",
  "body_html": "<p>Dear {{employee_name}}, ...</p>"
}
```

---

### 2.7 Update Automation Settings
- **Method:** `POST`
- **URL:** `{$baseUrl}/api/notifications/resigned-submission/settings`
- **Request Body:**
```json
{
  "is_active": true,
  "send_time": "09:00",
  "frequency": "daily",
  "min_days_between_reminders": 3,
  "max_reminders_per_employee": 5,
  "target_companies": [1, 2, 3, 4, 11],
  "query_email": "query@vnrseeds.com"
}
```

---

## 3. Scheduled Cron Execution on Server

### 3.1 Do I have to add cron on the server for execution?
- **YES**, automated reminders require the server cron to trigger Laravel's scheduler.
- **IMPORTANT:** If your server ALREADY runs the standard Laravel cron (`php artisan schedule:run`), then **NO new crons are required!**
  The command `notifications:resigned-submission` is already scheduled in `bootstrap/app.php` to run daily at 09:00 AM.

### 3.2 If Laravel Scheduler is NOT yet configured on Server:
Add this single entry to your server's crontab (`crontab -e` on Linux):
```bash
* * * * * cd /path/to/xeasy_laravel && php artisan schedule:run >> /dev/null 2>&1
```

### 3.3 Direct Standalone Cron Entry (Alternative):
If you prefer a direct cron entry solely for this reminder command (e.g. daily at 09:00 AM):
```bash
0 9 * * * cd /path/to/xeasy_laravel && php artisan notifications:resigned-submission >> /path/to/xeasy_laravel/storage/logs/resigned_cron.log 2>&1
```

### 3.4 CLI Command Options & Flags:
```bash
# Test execution without sending emails or incrementing reminder counts:
php artisan notifications:resigned-submission --dry-run

# Run and route all emails to admin/test address:
php artisan notifications:resigned-submission --test-email=query@vnrseeds.com

# Target a specific employee:
php artisan notifications:resigned-submission --employee_id=1644

# Target a specific company (e.g. Company 1 = VNR Seeds):
php artisan notifications:resigned-submission --company_id=1

# Force execution bypassing minimum interval or max reminders limit:
php artisan notifications:resigned-submission --force
```

---

## 4. Database Tables Reference
- `eml_resigned_reminders`: Tracks reminder count (`reminder_count`), total sent, last sent timestamp, last status.
- `eml_resigned_reminder_settings`: Configures schedule, minimum days interval (3), max hits (5), allowed companies, support query email.
- `eml_email_templates`: Stores editable corporate email template (`subject`, `body_html`, `body_text`).
- `eml_send_log`: Comprehensive audit log recording recipient, timestamp, status, full HTML body.
MARKDOWN;

        $fileName = 'xeasy_resigned_expense_reminder_api_docs.md';

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, $fileName, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
