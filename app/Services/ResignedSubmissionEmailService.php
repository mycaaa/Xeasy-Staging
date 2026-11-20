<?php

namespace App\Services;

use App\Helpers\DatabaseSwitcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResignedSubmissionEmailService
{
    public const NOTIFICATION_TYPE = 'resigned_final_submission';
    // FNF-01 (20 Nov 2026, tracker tab "FNF_Xeasy 20 Nov", sections 1-6): daily reminder from 7 days before the last
    // working day (separation date) up to that day, only to employees who have expense claims, with the status of
    // their latest claim months. New template name, so the new text is created next to the old one.
    public const DEFAULT_SUBJECT   = 'Complete your expense claims in Xeasy before your last working day';
    public const TEMPLATE_NAME     = 'Separation Expense Reminder (FNF)';

    protected EmailLogService $emailLogService;

    public function __construct(EmailLogService $emailLogService)
    {
        $this->emailLogService = $emailLogService;
    }

    /**
     * Get or initialize the automation & reminder schedule settings.
     */
    public function getSettings(): object
    {
        $settings = DB::table('eml_resigned_reminder_settings')->where('id', 1)->first();

        if (!$settings) {
            $defaultData = [
                'id'                          => 1,
                'is_active'                   => 1,
                'send_time'                   => '09:00',
                'frequency'                   => 'daily',
                'min_days_between_reminders'  => 1,
                'max_reminders_per_employee'  => 8,
                'target_companies'            => json_encode([1, 2, 3, 4, 11]),
                'days_before_separation'      => 7,
                'query_email'                 => 'query@vnrseeds.com',
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ];
            DB::table('eml_resigned_reminder_settings')->insert($defaultData);
            $settings = (object) $defaultData;
        }

        if (is_string($settings->target_companies)) {
            $settings->target_companies = json_decode($settings->target_companies, true) ?: [];
        }

        return $settings;
    }

    /**
     * Update automation & reminder schedule settings.
     */
    public function updateSettings(array $data, ?int $userId = null): bool
    {
        $targetCompanies = isset($data['target_companies'])
            ? (is_array($data['target_companies']) ? json_encode(array_map('intval', $data['target_companies'])) : $data['target_companies'])
            : json_encode([1, 2, 3, 4, 11]);

        return (bool) DB::table('eml_resigned_reminder_settings')
            ->where('id', 1)
            ->update([
                'is_active'                  => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
                'send_time'                  => $data['send_time'] ?? '09:00',
                'frequency'                  => $data['frequency'] ?? 'daily',
                'min_days_between_reminders' => (int) ($data['min_days_between_reminders'] ?? 3),
                'max_reminders_per_employee' => (int) ($data['max_reminders_per_employee'] ?? 5),
                'target_companies'           => $targetCompanies,
                'days_before_separation'     => (int) ($data['days_before_separation'] ?? 60),
                'query_email'                => trim($data['query_email'] ?? 'query@vnrseeds.com'),
                'updated_by'                 => $userId,
                'updated_at'                 => now(),
            ]);
    }

    /**
     * Get or initialize the email template from eml_email_templates.
     */
    public function getTemplate(): object
    {
        $template = DB::table('eml_email_templates')
            ->where('name', self::TEMPLATE_NAME)
            ->first();

        if (!$template) {
            $templateId = DB::table('eml_email_templates')->insertGetId([
                'name'        => self::TEMPLATE_NAME,
                'subject'     => self::DEFAULT_SUBJECT,
                'category'    => 'Notifications',
                'body_html'   => $this->getDefaultTemplateHtml(),
                'body_text'   => $this->getDefaultTemplateText(),
                'is_active'   => 1,
                'created_by'  => 1,
                'updated_by'  => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $template = DB::table('eml_email_templates')->where('id', $templateId)->first();
        }

        return $template;
    }

    /**
     * Update the template in eml_email_templates.
     */
    public function updateTemplate(string $subject, string $bodyHtml, ?string $bodyText = null, ?int $userId = null): bool
    {
        $existing = $this->getTemplate();

        return (bool) DB::table('eml_email_templates')
            ->where('id', $existing->id)
            ->update([
                'subject'    => trim($subject) ?: self::DEFAULT_SUBJECT,
                'body_html'  => $bodyHtml,
                'body_text'  => $bodyText ?: strip_tags($bodyHtml),
                'updated_by' => $userId ?: (auth()->id() ?: 1),
                'updated_at' => now(),
            ]);
    }

    /**
     * Fetch eligible resigned employees across ALL companies (or filtered by specific company).
     *
     * @param int|null $employeeId Optional specific employee ID
     * @param string|null $search Optional keyword search
     * @param int|null $companyId Optional single company ID filter (null = all companies)
     * @param array|null $companyIds Optional company IDs filter list
     * @param bool $onlyPendingClaims When true, strictly returns only employees with unsubmitted claims
     * @return Collection
     */
    public function getEligibleResignedEmployees(?int $employeeId = null, ?string $search = null, ?int $companyId = null, ?array $companyIds = null, bool $onlyPendingClaims = true): Collection
    {
        $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];

        $query = DB::connection('hrims')->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin('core_departments as d', 'eg.DepartmentId', '=', 'd.id')
            ->where(function ($q) use ($blankDates) {
                $q->where('e.EmpStatus', 'R')
                    ->orWhere(function ($q2) use ($blankDates) {
                        $q2->whereNotNull('e.DateOfResignation')
                            ->whereNotIn('e.DateOfResignation', $blankDates)
                            ->where('e.DateOfResignation', '>', '2000-01-01');
                    });
            })
            ->where(function ($q) use ($blankDates) {
                // Keep active/resigned or those with upcoming separation
                $q->where('e.EmpStatus', '!=', 'D')
                    ->orWhereNull('e.EmpStatus')
                    ->orWhere(function ($q2) use ($blankDates) {
                        $q2->whereNotNull('e.DateOfSepration')
                            ->whereNotIn('e.DateOfSepration', $blankDates)
                            ->where('e.DateOfSepration', '>=', DB::raw('CURDATE()'));
                    });
            });

        // Company filter if explicitly specified
        if ($companyId) {
            $query->where('e.CompanyId', $companyId);
        } elseif (!empty($companyIds)) {
            $query->whereIn('e.CompanyId', $companyIds);
        }

        if ($employeeId) {
            $query->where('e.EmployeeID', $employeeId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('e.EmpCode', 'like', "%{$search}%")
                    ->orWhere('e.Fname', 'like', "%{$search}%")
                    ->orWhere('e.Lname', 'like', "%{$search}%")
                    ->orWhere(DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))"), 'like', "%{$search}%")
                    ->orWhere('eg.EmailId_Vnr', 'like', "%{$search}%");
            });
        }

        $employees = $query->select([
            'e.EmployeeID',
            'e.EmpCode',
            'e.CompanyId',
            DB::raw("TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) as employee_name"),
            'e.Fname as first_name',
            'e.EmpStatus',
            'e.DateOfResignation',
            'e.DateOfSepration',
            'eg.EmailId_Vnr as email',
            'd.department_name',
        ])
            ->orderBy('e.DateOfResignation', 'desc')
            ->get();

        if ($employees->isEmpty()) {
            return collect();
        }

        // Attach reminder tracking data from eml_resigned_reminders
        $reminderData = DB::table('eml_resigned_reminders')
            ->whereIn('employee_id', $employees->pluck('EmployeeID')->all())
            ->get()
            ->keyBy('employee_id');

        $mapped = $employees->map(function ($emp) use ($reminderData) {
            $track = $reminderData->get($emp->EmployeeID);
            $emp->reminder_count     = (int) ($track->reminder_count ?? 0);
            $emp->total_sent_count   = (int) ($track->total_sent_count ?? 0);
            $emp->last_sent_at       = $track->last_sent_at ?? null;
            $emp->last_trigger_type  = $track->last_trigger_type ?? null;
            $emp->last_status        = $track->last_status ?? 'pending';
            $emp->last_error         = $track->last_error ?? null;
            $emp->company_name       = DatabaseSwitcher::getCompanyName($emp->CompanyId);
            return $emp;
        });

        // Attach pending expense claims detection
        $withClaims = $this->attachPendingClaimsInfo($mapped);

        // FNF-01: no claims = no reminder (any expense claim in the active years counts)
        if ($onlyPendingClaims) {
            return $withClaims->filter(function ($emp) {
                return !empty($emp->has_claims);
            })->values();
        }

        return $withClaims;
    }

    /**
     * Compute and attach pending expense claims status for given employees.
     * Evaluates active financial years (e.g. y7, y8) and determines if claims exist that have not been final submitted.
     */
    public function attachPendingClaimsInfo(Collection $employees): Collection
    {
        if ($employees->isEmpty()) {
            return $employees;
        }

        $activeYears = \App\Models\FinancialYear::where('Status', 'Active')->pluck('YearId')->toArray();
        if (empty($activeYears)) {
            $activeYears = [7, 8];
        }

        foreach ($employees as $emp) {
            $conn        = DatabaseSwitcher::getExpenseConnection($emp->CompanyId);
            $totalClaims = 0;
            $totalAmount = 0.0;
            $monthsList  = [];

            foreach ($activeYears as $yId) {
                $claimsTbl = "y{$yId}_expenseclaims";
                $mefTbl    = "y{$yId}_monthexpensefinal";

                try {
                    $rows = DB::connection($conn)->select("
                        SELECT ec.ClaimMonth, COUNT(ec.ExpId) as claim_count, SUM(COALESCE(ec.FilledTAmt, 0)) as total_claimed
                        FROM {$claimsTbl} ec
                        LEFT JOIN {$mefTbl} mef ON ec.crby = mef.EmployeeID AND ec.ClaimMonth = mef.Month
                        WHERE ec.crby = ?
                          AND ec.ClaimStatus != 'Deactivate'
                          AND (mef.id IS NULL OR mef.Status = 'Open' OR mef.DateOfSubmit IS NULL OR mef.DateOfSubmit = '0000-00-00')
                        GROUP BY ec.ClaimMonth
                    ", [$emp->EmployeeID]);

                    foreach ($rows as $r) {
                        $totalClaims += (int) $r->claim_count;
                        $totalAmount += (float) $r->total_claimed;
                        $monthName = date('F', mktime(0, 0, 0, (int) $r->ClaimMonth, 10));
                        $monthsList[] = "{$monthName} ({$r->claim_count} claims - ₹" . number_format($r->total_claimed) . ")";
                    }
                } catch (\Throwable $e) {
                    // Silently ignore if table or column doesn't exist for this FY
                }
            }

            // FNF-01: any claim at all, and the status of the latest claim months for the mail
            $emp->has_claims = $totalClaims > 0;
            foreach ($activeYears as $yId) {
                try {
                    if (!$emp->has_claims && DB::connection($conn)->table("y{$yId}_expenseclaims")->where('CrBy', $emp->EmployeeID)->where('ClaimStatus', '!=', 'Deactivate')->exists()) {
                        $emp->has_claims = true;
                    }
                } catch (\Throwable $e) {
                }
            }
            $emp->claim_status_rows = $emp->has_claims ? $this->claimStatusRows($emp, $conn) : [];
            $emp->has_pending_claims   = ($totalClaims > 0);
            $emp->pending_claim_count  = $totalClaims;
            $emp->pending_claim_amount = $totalAmount;
            $emp->pending_months_text  = !empty($monthsList) ? implode(', ', $monthsList) : 'None';
        }

        return $employees;
    }

    /**
     * Render email HTML with dynamic variables substituted.
     *
     * Supported variables:
     *  - {{employee_name}}
     *  - {{emp_code}}
     *  - {{company_name}}
     *  - {{department}}
     *  - {{resignation_date}}
     *  - {{separation_date}}
     *  - {{last_working_day}}
     *  - {{query_email}}
     */
    public function renderEmailHtml(string|object $employeeOrName, ?string $customHtml = null): string
    {
        $employeeData = $this->resolveEmployeeVariables($employeeOrName);
        $templateHtml = $customHtml ?: ($this->getTemplate()->body_html ?: $this->getDefaultTemplateHtml());
        // FNF-01: the claim status table is HTML built here (escaped cell by cell), put in as it is
        $table = is_object($employeeOrName) ? $this->claimStatusTableHtml($employeeOrName) : '';
        $templateHtml = preg_replace('/\{\{\s*claim_status_table\s*\}\}/i', str_replace(['\\', '$'], ['\\\\', '\\$'], $table), $templateHtml);

        return $this->interpolateVariables($templateHtml, $employeeData);
    }

    /**
     * Render plain-text email with dynamic variables substituted.
     */
    public function renderEmailText(string|object $employeeOrName, ?string $customText = null): string
    {
        $employeeData = $this->resolveEmployeeVariables($employeeOrName);
        $templateText = $customText ?: ($this->getTemplate()->body_text ?: $this->getDefaultTemplateText());
        $employeeData['claim_status_table'] = is_object($employeeOrName) ? $this->claimStatusText($employeeOrName) : '';

        return $this->interpolateVariables($templateText, $employeeData);
    }

    /**
     * Helper to resolve standard variable dictionary.
     */
    protected function resolveEmployeeVariables(string|object $employeeOrName): array
    {
        $settings = $this->getSettings();
        $queryEmail = $settings->query_email ?: 'query@vnrseeds.com';

        if (is_string($employeeOrName)) {
            return [
                'employee_name'    => $employeeOrName,
                'emp_code'         => '1234',
                'company_name'     => 'VNR Seeds Private Limited',
                'department'       => 'General',
                'resignation_date' => Carbon::now()->format('d-M-Y'),
                'separation_date'  => Carbon::now()->addDays(30)->format('d-M-Y'),
                'last_working_day' => Carbon::now()->addDays(30)->format('d-M-Y'),
                'query_email'      => $queryEmail,
            ];
        }

        $emp = $employeeOrName;
        $dor = ($emp->DateOfResignation && \App\Support\ZeroDate::has($emp->DateOfResignation))
            ? Carbon::parse($emp->DateOfResignation)->format('d-M-Y') : 'N/A';
        $dos = ($emp->DateOfSepration && \App\Support\ZeroDate::has($emp->DateOfSepration))
            ? Carbon::parse($emp->DateOfSepration)->format('d-M-Y') : 'N/A';

        return [
            'employee_name'          => $emp->employee_name ?? 'Employee',
            'emp_code'               => $emp->EmpCode ?? '',
            'company_name'           => $emp->company_name ?? DatabaseSwitcher::getCompanyName($emp->CompanyId ?? 1),
            'department'             => $emp->department_name ?? 'General',
            'resignation_date'       => $dor,
            'separation_date'        => $dos,
            'last_working_day'       => $dos,
            'query_email'            => $queryEmail,
            'pending_claims_count'   => $emp->pending_claim_count ?? 0,
            'pending_claims_amount'  => '₹' . number_format($emp->pending_claim_amount ?? 0),
            'pending_months'         => $emp->pending_months_text ?? '',
        ];
    }

    /**
     * FNF-01: status of the employee's latest claim months (two months before the last working day up to its month),
     * read from Xeasy at the time of sending.
     *
     * @return array<int,array{month:string,status:string,claims:int}>
     */
    public function claimStatusRows(object $emp, ?string $conn = null): array
    {
        $conn = $conn ?: DatabaseSwitcher::getExpenseConnection($emp->CompanyId ?? 1);
        $lwd = ($emp->DateOfSepration ?? null) && (string) $emp->DateOfSepration > '2000-01-01' ? Carbon::parse($emp->DateOfSepration) : now();
        $rows = [];
        for ($i = 2; $i >= 0; $i--) {
            $d = $lwd->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $y = \App\Support\CompanyYear::containing($conn, $d->toDateString());
            if (!$y) {
                continue;
            }
            try {
                $mef = DB::connection($conn)->table("y{$y}_monthexpensefinal")->where('EmployeeID', $emp->EmployeeID)->where('Month', $d->month)->first();
                $n = DB::connection($conn)->table("y{$y}_expenseclaims")->where('CrBy', $emp->EmployeeID)->where('ClaimMonth', $d->month)->where('ClaimStatus', '!=', 'Deactivate')->count();
            } catch (\Throwable $e) {
                continue;
            }
            if (!$mef && $n === 0 && $i > 0) {
                continue;   // earlier month with nothing: not shown
            }
            $rows[] = ['month' => $d->format('F Y'), 'status' => \App\Services\Fnf\FnfCalculator::monthStatus($mef, $n), 'claims' => $n];
        }

        return array_reverse($rows);   // latest first
    }

    /** FNF-01: claim status table (HTML) for the mail. */
    public function claimStatusTableHtml(object $emp): string
    {
        $rows = $emp->claim_status_rows ?? $this->claimStatusRows($emp);
        if (!$rows) {
            return '';
        }
        $tr = '';
        foreach ($rows as $r) {
            $warn = in_array($r['status'], ['Not submitted', 'Submitted - hard copy not received'], true);
            $tr .= '<tr><td style="padding:6px 10px;border:1px solid #e5e7eb;">'.e($r['month']).'</td><td style="padding:6px 10px;border:1px solid #e5e7eb;'.($warn ? 'color:#b91c1c;font-weight:600;' : '').'">'.e($r['status']).'</td></tr>';
        }

        return '<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:14px;margin:6px 0 18px 0;">'
            .'<tr><th style="padding:6px 10px;border:1px solid #e5e7eb;background:#f8fafc;text-align:left;">Claim month</th><th style="padding:6px 10px;border:1px solid #e5e7eb;background:#f8fafc;text-align:left;">Claim status</th></tr>'
            .$tr.'</table>';
    }

    /** FNF-01: claim status as plain text for the text part of the mail. */
    public function claimStatusText(object $emp): string
    {
        return collect($emp->claim_status_rows ?? $this->claimStatusRows($emp))->map(fn ($r) => str_pad($r['month'], 18).$r['status'])->implode("\n");
    }

    /**
     * FNF-01: whether the daily job sends today - from (last working day - days_before_separation) up to the last
     * working day, once a day. Null = send, else the reason it is skipped.
     */
    public function scheduleSkipReason(object $emp, object $settings): ?string
    {
        $lwd = ($emp->DateOfSepration ?? null) && (string) $emp->DateOfSepration > '2000-01-01' ? Carbon::parse($emp->DateOfSepration)->startOfDay() : null;
        if (!$lwd) {
            return 'No last working day entered';
        }
        $start = $lwd->copy()->subDays(max(0, (int) ($settings->days_before_separation ?? 7)));
        $today = now()->startOfDay();
        if ($today->lt($start)) {
            return 'Reminders start on '.$start->format('d-m-Y');
        }
        if ($today->gt($lwd)) {
            return 'Last working day '.$lwd->format('d-m-Y').' has passed';
        }
        if (!empty($emp->last_sent_at) && Carbon::parse($emp->last_sent_at)->isSameDay(now())) {
            return 'Already sent today';
        }

        return null;
    }

    /**
     * Interpolate mustache style variables (supports {{var}} and {{ var }}).
     */
    protected function interpolateVariables(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $escapedValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $template = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i', $escapedValue, $template);
        }
        return $template;
    }

    /**
     * Send email reminder to a single resigned employee.
     */
    public function sendReminder(object|int $employee, ?string $overrideEmail = null, bool $isCron = false, ?string $subject = null): array
    {
        if (is_int($employee)) {
            $employeeObj = $this->getEligibleResignedEmployees($employee)->first();
            if (!$employeeObj) {
                return [
                    'success'     => false,
                    'message'     => "Employee ID {$employee} not found or not eligible as resigned.",
                    'employee_id' => $employee,
                ];
            }
            $employee = $employeeObj;
        }

        $recipientEmail = trim($overrideEmail ?: ($employee->email ?? ''));
        $employeeName   = trim($employee->employee_name ?? 'Employee');

        // Strictly enforce condition: only send notification to those who have claims pending for submission
        if (!isset($employee->has_pending_claims)) {
            $tempColl = $this->attachPendingClaimsInfo(collect([$employee]));
            $employee = $tempColl->first();
        }

        if (empty($employee->has_claims)) {   // FNF-01: no claims = no reminder
            return [
                'success'       => false,
                'skipped'       => true,
                'message'       => "Employee {$employeeName} (ID: {$employee->EmployeeID}) has no expense claims in Xeasy. Notification skipped.",
                'employee_id'   => $employee->EmployeeID,
                'employee_name' => $employeeName,
                'email'         => $recipientEmail,
            ];
        }
        
        $templateObj    = $this->getTemplate();
        $subject        = $subject ?: ($templateObj->subject ?: self::DEFAULT_SUBJECT);

        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $errMsg = "Invalid or missing recipient email address: '{$recipientEmail}'";
            $this->recordFailure($employee, $subject, $recipientEmail, $errMsg, $isCron);
            return [
                'success'       => false,
                'message'       => $errMsg,
                'employee_id'   => $employee->EmployeeID,
                'employee_name' => $employeeName,
                'email'         => $recipientEmail,
            ];
        }

        $bodyHtml = $this->renderEmailHtml($employee);
        $bodyText = $this->renderEmailText($employee);

        try {
            $isMailActive = filter_var(config('mail.active', env('MAIL_ACTIVE', true)), FILTER_VALIDATE_BOOLEAN);

            if ($isMailActive) {
                Mail::html($bodyHtml, function ($message) use ($recipientEmail, $subject, $employeeName) {
                    $message->to($recipientEmail, $employeeName)
                        ->subject($subject);
                });
            } else {
                Log::info("ResignedSubmissionEmailService: MAIL_ACTIVE is false, email simulated for {$recipientEmail}");
            }

            // Update reminder tracking record in eml_resigned_reminders
            $newReminderCount = $this->recordSuccess($employee, $recipientEmail, $isCron);

            // Log to eml_send_log
            $this->emailLogService->logSent([
                'manual' => true, // MAILLOG-01: sent from the screen by a person; System when the daily job runs
                'notification_type'     => self::NOTIFICATION_TYPE,
                'mail_category'         => 'expense',
                'stage'                 => (string) $newReminderCount,
                'subject'               => $subject,
                'body_html'             => $bodyHtml,
                'body_text'             => $bodyText,
                'recipient_employee_id' => $employee->EmployeeID,
                'recipient_emp_code'    => $employee->EmpCode ?? null,
                'recipient_name'        => $employeeName,
                'recipient_email'       => $recipientEmail,
                'company_id'            => $employee->CompanyId ?? 1,
                'ref_type'              => 'resigned_reminder',
                'ref_id'                => $employee->EmployeeID,
            ]);

            // Legacy log table (eml_stage_email_logs)
            try {
                DB::table('eml_stage_email_logs')->insert([
                    'employee_id'       => $employee->EmployeeID,
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'stage'             => (string) $newReminderCount,
                    'sent_for_month'    => now()->format('Y-m'),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } catch (\Throwable $e) {
                // Silently skip if dupe or connection error
            }

            return [
                'success'        => true,
                'message'        => "Reminder successfully sent to {$employeeName} ({$recipientEmail}).",
                'employee_id'    => $employee->EmployeeID,
                'employee_name'  => $employeeName,
                'email'          => $recipientEmail,
                'reminder_count' => $newReminderCount,
                'trigger_type'   => $isCron ? 'cron' : 'api',
            ];

        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            Log::error("ResignedSubmissionEmailService error sending to {$recipientEmail}: {$errMsg}");

            $this->recordFailure($employee, $subject, $recipientEmail, $errMsg, $isCron, $bodyHtml, $bodyText);

            return [
                'success'       => false,
                'message'       => "Failed to send email: {$errMsg}",
                'employee_id'   => $employee->EmployeeID,
                'employee_name' => $employeeName,
                'email'         => $recipientEmail,
                'error'         => $errMsg,
            ];
        }
    }

    /**
     * Send reminders in batch (used by Cron or bulk API).
     */
    public function sendBatch(array $employeeIds = [], ?string $overrideEmail = null, bool $isCron = false, ?int $limit = null, ?int $companyId = null): array
    {
        $settings = $this->getSettings();

        // If triggered by cron, check if cron automation is active in settings
        if ($isCron && empty($settings->is_active)) {
            return [
                'total'        => 0,
                'sent_count'   => 0,
                'failed_count' => 0,
                'skipped_count'=> 0,
                'message'      => 'Automation is currently disabled in Resigned Reminder Settings.',
                'results'      => [],
            ];
        }

        // Strictly query only resigned employees who have pending claims for final submission
        $query = $this->getEligibleResignedEmployees(null, null, $companyId, null, onlyPendingClaims: true);

        if (!empty($employeeIds)) {
            $query = $query->whereIn('EmployeeID', $employeeIds);
        }

        // Apply target companies from settings if cron
        if ($isCron && !empty($settings->target_companies) && is_array($settings->target_companies)) {
            $query = $query->whereIn('CompanyId', $settings->target_companies);
        }

        if ($limit && $limit > 0) {
            $query = $query->take($limit);
        }

        $results      = [];
        $sentCount    = 0;
        $failedCount  = 0;
        $skippedCount = 0;

        foreach ($query as $emp) {
            // FNF-01: the daily job sends from 7 days before the last working day up to that day, once a day
            if ($isCron && ($why = $this->scheduleSkipReason($emp, $settings))) {
                $skippedCount++;
                $results[] = ['success' => false, 'skipped' => true, 'message' => 'Skipped: '.$why, 'employee_id' => $emp->EmployeeID, 'employee_name' => $emp->employee_name];
                continue;
            }

            $res = $this->sendReminder($emp, $overrideEmail, $isCron);
            $results[] = $res;
            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'total'         => count($query),
            'sent_count'    => $sentCount,
            'failed_count'  => $failedCount,
            'skipped_count' => $skippedCount,
            'trigger_type'  => $isCron ? 'cron' : 'api',
            'results'       => $results,
        ];
    }

    /**
     * Record successful send and update reminder counts in eml_resigned_reminders.
     */
    protected function recordSuccess(object $employee, string $recipientEmail, bool $isCron): int
    {
        $existing = DB::table('eml_resigned_reminders')
            ->where('employee_id', $employee->EmployeeID)
            ->first();

        $currentReminderCount = (int) ($existing->reminder_count ?? 0);
        $newReminderCount     = $isCron ? ($currentReminderCount + 1) : $currentReminderCount;
        $totalSent            = (int) ($existing->total_sent_count ?? 0) + 1;

        $data = [
            'employee_id'         => $employee->EmployeeID,
            'emp_code'            => $employee->EmpCode ?? null,
            'employee_name'       => $employee->employee_name ?? null,
            'email'               => $recipientEmail,
            'department_name'     => $employee->department_name ?? null,
            'date_of_resignation' => \App\Support\ZeroDate::has($employee->DateOfResignation ?? null) ? $employee->DateOfResignation : null,   // ZERO-DATE-02
            'date_of_separation'  => \App\Support\ZeroDate::has($employee->DateOfSepration ?? null) ? $employee->DateOfSepration : null,
            'reminder_count'      => $newReminderCount,
            'total_sent_count'    => $totalSent,
            'last_sent_at'        => now(),
            'last_trigger_type'   => $isCron ? 'cron' : 'api',
            'last_status'         => 'sent',
            'last_error'          => null,
            'updated_at'          => now(),
        ];

        if ($existing) {
            DB::table('eml_resigned_reminders')
                ->where('employee_id', $employee->EmployeeID)
                ->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('eml_resigned_reminders')->insert($data);
        }

        return $newReminderCount;
    }

    /**
     * Record failure in eml_resigned_reminders and eml_send_log.
     */
    protected function recordFailure(object $employee, string $subject, string $recipientEmail, string $error, bool $isCron, ?string $bodyHtml = null, ?string $bodyText = null): void
    {
        $existing = DB::table('eml_resigned_reminders')
            ->where('employee_id', $employee->EmployeeID)
            ->first();

        $data = [
            'employee_id'         => $employee->EmployeeID,
            'emp_code'            => $employee->EmpCode ?? null,
            'employee_name'       => $employee->employee_name ?? null,
            'email'               => $recipientEmail,
            'department_name'     => $employee->department_name ?? null,
            'date_of_resignation' => \App\Support\ZeroDate::has($employee->DateOfResignation ?? null) ? $employee->DateOfResignation : null,   // ZERO-DATE-02
            'date_of_separation'  => \App\Support\ZeroDate::has($employee->DateOfSepration ?? null) ? $employee->DateOfSepration : null,
            'last_trigger_type'   => $isCron ? 'cron' : 'api',
            'last_status'         => 'failed',
            'last_error'          => substr($error, 0, 500),
            'updated_at'          => now(),
        ];

        if ($existing) {
            DB::table('eml_resigned_reminders')
                ->where('employee_id', $employee->EmployeeID)
                ->update($data);
        } else {
            $data['reminder_count']   = 0;
            $data['total_sent_count'] = 0;
            $data['created_at']       = now();
            DB::table('eml_resigned_reminders')->insert($data);
        }

        $this->emailLogService->logFailed([
            'manual' => true,
            'notification_type'     => self::NOTIFICATION_TYPE,
            'mail_category'         => 'expense',
            'stage'                 => (string) ($existing->reminder_count ?? 0),
            'subject'               => $subject,
            'body_html'             => $bodyHtml,
            'body_text'             => $bodyText,
            'recipient_employee_id' => $employee->EmployeeID,
            'recipient_emp_code'    => $employee->EmpCode ?? null,
            'recipient_name'        => $employee->employee_name ?? null,
            'recipient_email'       => $recipientEmail,
            'company_id'            => $employee->CompanyId ?? 1,
            'ref_type'              => 'resigned_reminder',
            'ref_id'                => $employee->EmployeeID,
        ], $error);
    }

    /**
     * Default template HTML layout.
     */
    public function getDefaultTemplateHtml(): string
    {
        // FNF-01 (20 Nov 2026): text of tracker tab "FNF_Xeasy 20 Nov", section 4, with the claim status of section 5
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Expense claims before your last working day</title></head>
<body style="margin:0;padding:20px;font-family:Arial,Helvetica,sans-serif;background-color:#f4f6f8;color:#1f2937;line-height:1.55;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
<tr><td style="padding:22px 28px 6px 28px;font-size:14px;">
<p style="margin:0 0 14px 0;">Dear {{employee_name}},</p>
<p style="margin:0 0 14px 0;">As per our records, your last working day with the organisation is <strong>{{separation_date}}</strong>.</p>
<p style="margin:0 0 10px 0;">We request you to review the status of your latest expense claims in Xeasy and ensure that all pending expenses are submitted before your separation.</p>
{{claim_status_table}}
<p style="margin:0 0 6px 0;">Please ensure the following:</p>
<ol style="margin:0 0 14px 0;padding-left:20px;">
<li style="margin-bottom:6px;">Submit all your pending expense claims, including the current month's expenses, on or before your Last Working Day.</li>
<li style="margin-bottom:6px;">Submit the corresponding hard copies of all expense claims to the Finance Department on your Last Working Day.</li>
<li>Ensure that all submitted claims are duly approved and the required supporting documents are provided.</li>
</ol>
<p style="margin:0 0 6px 0;"><strong>Important - FNF Settlement:</strong></p>
<p style="margin:0 0 10px 0;">Please note that any pending or unsubmitted expenses may not be considered as part of your Full &amp; Final (FNF) settlement.</p>
<p style="margin:0 0 18px 0;">You are therefore requested to complete all expense-related formalities within the stipulated timeline to avoid any delay or omission in your FNF settlement.</p>
<p style="margin:0 0 18px 0;">For any clarification, please write to <a href="mailto:{{query_email}}" style="color:#0369a1;">{{query_email}}</a>.</p>
<p style="margin:0;">Regards,<br><strong>Finance &amp; Accounts Department</strong><br>{{company_name}}</p>
</td></tr>
<tr><td style="padding:14px 28px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">This is an automated reminder from Xeasy. Employee code {{emp_code}}.</td></tr>
</table></td></tr></table>
</body>
</html>
HTML;
    }

    /**
     * Default template plain text.
     */
    public function getDefaultTemplateText(): string
    {
        return <<<TEXT
Dear {{employee_name}},

As per our records, your last working day with the organisation is {{separation_date}}.

We request you to review the status of your latest expense claims in Xeasy and ensure that all pending expenses are submitted before your separation.

Claim month       Claim status
{{claim_status_table}}

Please ensure the following:

1. Submit all your pending expense claims, including the current month's expenses, on or before your Last Working Day.
2. Submit the corresponding hard copies of all expense claims to the Finance Department on your Last Working Day.
3. Ensure that all submitted claims are duly approved and the required supporting documents are provided.

Important - FNF Settlement:

Please note that any pending or unsubmitted expenses may not be considered as part of your Full & Final (FNF) settlement.

You are therefore requested to complete all expense-related formalities within the stipulated timeline to avoid any delay or omission in your FNF settlement.

Regards,
Finance & Accounts Department
{{company_name}}
TEXT;
    }
}
