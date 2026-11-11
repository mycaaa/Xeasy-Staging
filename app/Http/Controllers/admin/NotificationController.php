<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialYear;
use App\Services\EmailLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Part\TextPart;

class NotificationController extends Controller
{
    public function submissionReminder()
    {
        return view('admin.submission_reminder');
    }

    /**
     * Check if an employee is allowed to receive a specific email type.
     * Returns true if email should be sent, false if blocked.
     */
    private function canReceiveEmail(int $employeeId, string $emailType): bool
    {
        $control = DB::table('eml_email_control')
            ->where('employee_id', $employeeId)
            ->first();

        // No record means all emails are allowed (default)
        if (!$control) {
            return true;
        }

        // Master switch off = block all
        if (!$control->is_active) {
            return false;
        }

        // Check specific email type
        return match ($emailType) {
            'submission_reminder' => (bool) $control->receive_submission_reminder,
            'escalation' => (bool) $control->receive_escalation,
            'receiving_pending' => (bool) $control->receive_receiving_pending,
            'payment_notification' => (bool) $control->receive_payment_notification,
            'approval_notification' => (bool) $control->receive_approval_notification,
            default => true,
        };
    }

    public function sendHardCopyEscalationEmails(Request $request)
    {
        $isPreview   = $request->boolean('preview');
        $yearId      = FinancialYear::where('Status', 'Active')->value('YearId');

        if (! $yearId) {
            return response()->json(['success' => false, 'message' => 'Active financial year not found.'], 400);
        }

        $table       = "y{$yearId}_monthexpensefinal";
        $claimsTable = "y{$yearId}_expenseclaims";

        try {
            $notifications = DB::table('eml_notification')
                ->where('notification_type', 'pending_hardcopy_escalation')
                ->where('is_active', 1)
                ->get();

            if ($notifications->isEmpty()) {
                return $this->jsonSuccess([], 'No active escalation notifications found.');
            }

            $isMailActive  = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
            $today         = Carbon::now('Asia/Kolkata');
            $todayDate     = (int) $today->day;
            $previousMonth = $today->copy()->subMonth()->month;
            $previewRows   = collect();

            foreach ($notifications as $notification) {
                if ((int) $todayDate !== (int) $notification->send_day) {
                    continue;
                }

                $template = DB::table('eml_email_templates')
                    ->where('id', $notification->email_template_id)
                    ->where('is_active', 1)
                    ->first();

                if (! $template) {
                    continue;
                }

                $employees = DB::table("{$table} as m")
                    ->join('users as u', 'u.employee_id', '=', 'm.EmployeeId')
                    ->join("{$claimsTable} as e", function ($join) use ($previousMonth) {
                        $join->on('e.crby', '=', 'm.EmployeeId')
                             ->where('e.filledtamt', '>', 0)
                             ->where('e.claimstatus', '!=', 'Deactivate')
                             ->where('e.ClaimMonth', $previousMonth);
                    })
                    ->select('u.name', 'u.emp_code', 'u.email', 'm.EmployeeId', 'm.Month', 'm.Status', 'm.DateOfSubmit')
                    ->where('m.Month', $previousMonth)
                    ->where('m.Status', 'Closed')
                    ->where('m.DateOfSubmit', '!=', '0000-00-00')
                    ->where(function ($q) {
                        $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
                    })
                    ->where(function ($q) {
                        $q->where('m.PostDate', '0000-00-00')->orWhereNull('m.PostDate');
                    })
                    // Exclude claims that are already verified (have a valid VerifyDate)
                    ->where(function ($q) {
                        $q->where('e.VerifyDate', '0000-00-00')
                           ->orWhereNull('e.VerifyDate');
                    })
                    ->distinct()
                    ->get();

                if ($employees->isEmpty()) {
                    continue;
                }

                // Bulk fetch hrims data for all employees in one query
                $employeeIds = $employees->pluck('EmployeeId')->toArray();

                // Stage 1 → direct manager (RepEmployeeID)
                // Stage 2 → manager's manager (RepEmployeeID of RepEmployeeID)
                if ((int) $notification->stage === 2) {
                    $hrimsData = DB::connection('hrims')->table('hrm_employee_general as emp_g')
                        ->join('hrm_employee_general as mgr_g', 'mgr_g.EmployeeID', '=', 'emp_g.RepEmployeeID')
                        ->join('hrm_employee_general as gm_g', 'gm_g.EmployeeID', '=', 'mgr_g.RepEmployeeID')
                        ->join('hrm_employee as gm', 'gm.EmployeeID', '=', 'gm_g.EmployeeID')
                        ->whereIn('emp_g.EmployeeID', $employeeIds)
                        ->whereNotNull('gm_g.EmailId_Vnr')
                        ->where('gm_g.EmailId_Vnr', '!=', '')
                        ->selectRaw("
                            emp_g.EmployeeID,
                            gm_g.EmployeeID as RepEmployeeID,
                            gm_g.EmailId_Vnr as manager_email,
                            TRIM(CONCAT(gm.Fname, ' ', COALESCE(gm.Sname, ''), ' ', COALESCE(gm.Lname, ''))) as manager_name
                        ")
                        ->get()
                        ->keyBy('EmployeeID');
                } else {
                    $hrimsData = DB::connection('hrims')->table('hrm_employee_general as emp_g')
                        ->join('hrm_employee_general as mgr_g', 'mgr_g.EmployeeID', '=', 'emp_g.RepEmployeeID')
                        ->join('hrm_employee as mgr', 'mgr.EmployeeID', '=', 'mgr_g.EmployeeID')
                        ->whereIn('emp_g.EmployeeID', $employeeIds)
                        ->whereNotNull('mgr_g.EmailId_Vnr')
                        ->where('mgr_g.EmailId_Vnr', '!=', '')
                        ->selectRaw("
                            emp_g.EmployeeID,
                            emp_g.RepEmployeeID,
                            mgr_g.EmailId_Vnr as manager_email,
                            TRIM(CONCAT(mgr.Fname, ' ', COALESCE(mgr.Sname, ''), ' ', COALESCE(mgr.Lname, ''))) as manager_name
                        ")
                        ->get()
                        ->keyBy('EmployeeID');
                }

                // Group by reporting manager
                $managerGroups = [];

                foreach ($employees as $emp) {
                    $hrims = $hrimsData->get($emp->EmployeeId);
                    if (! $hrims) {
                        continue;
                    }

                    $repEmployeeId = $hrims->RepEmployeeID;
                    $managerGroups[$repEmployeeId]['manager_email'] = $hrims->manager_email;
                    $managerGroups[$repEmployeeId]['manager_name']  = $hrims->manager_name;
                    $managerGroups[$repEmployeeId]['employees'][]   = $emp;
                }

                foreach ($managerGroups as $repEmployeeId => $group) {
                    $alreadySent = DB::table('eml_stage_email_logs')
                        ->where('employee_id', $repEmployeeId)
                        ->where('notification_type', 'pending_hardcopy_escalation')
                        ->where('stage', $notification->stage)
                        ->where('sent_for_month', now()->format('Y-m'))
                        ->exists();

                    if ($isPreview) {
                        foreach ($group['employees'] as $emp) {
                            $previewRows->push([
                                'manager_email'          => $group['manager_email'],
                                'employee_name'          => $emp->name,
                                'employee_id'            => $emp->emp_code,
                                'month'                  => Carbon::createFromFormat('!m', $emp->Month)->format('F'),
                                'submission_date'        => ($emp->DateOfSubmit && \App\Support\ZeroDate::has($emp->DateOfSubmit))
                                    ? Carbon::parse($emp->DateOfSubmit)->format('d-M-Y') : '-',
                                'stage'                  => $notification->stage,
                                'email_template_subject' => $template->subject ?? '(No template)',
                                'conditions'             => [
                                    'today_date'   => $todayDate,
                                    'send_day'     => $notification->send_day,
                                    'already_sent' => $alreadySent
                                        ? '<span class="badge bg-teal-subtle text-teal badge-border">Yes</span>'
                                        : '<span class="badge bg-dark-subtle text-dark badge-border">No</span>',
                                ],
                            ]);
                        }
                        continue;
                    }

                    if ($alreadySent) {
                        continue;
                    }

                    // Check email control — escalation (check the manager)
                    if (!$this->canReceiveEmail($repEmployeeId, 'escalation')) {
                        continue;
                    }

                    // Build employee HTML table for email body
                    $repName = $group['manager_name'];

                    $rows = '';
                    foreach ($group['employees'] as $i => $emp) {
                        $submissionDate = ($emp->DateOfSubmit && \App\Support\ZeroDate::has($emp->DateOfSubmit))
                            ? Carbon::parse($emp->DateOfSubmit)->format('d-M-Y') : '-';
                        $monthName = Carbon::createFromFormat('!m', $emp->Month)->format('F');
                        $rows .= "<tr>
                            <td style='padding:6px 10px;border:1px solid #ddd;'>" . ($i + 1) . "</td>
                            <td style='padding:6px 10px;border:1px solid #ddd;'>{$emp->name}</td>
                            <td style='padding:6px 10px;border:1px solid #ddd;'>{$emp->emp_code}</td>
                            <td style='padding:6px 10px;border:1px solid #ddd;'>{$monthName}</td>
                            <td style='padding:6px 10px;border:1px solid #ddd;'>{$submissionDate}</td>
                            <td style='padding:6px 10px;border:1px solid #ddd;'>{$repName}</td>
                        </tr>";
                    }

                    $employeeTable = "<table style='border-collapse:collapse;width:100%;font-size:13px;'>
                        <thead><tr style='background:#f2f2f2;'>
                            <th style='padding:6px 10px;border:1px solid #ddd;'>S.No</th>
                            <th style='padding:6px 10px;border:1px solid #ddd;'>Name</th>
                            <th style='padding:6px 10px;border:1px solid #ddd;'>Code</th>
                            <th style='padding:6px 10px;border:1px solid #ddd;'>Month</th>
                            <th style='padding:6px 10px;border:1px solid #ddd;'>Submission Date</th>
                            <th style='padding:6px 10px;border:1px solid #ddd;'>Reporting Manager</th>
                        </tr></thead>
                        <tbody>{$rows}</tbody>
                    </table>";

                    $body = str_replace('{{employee_table}}', $employeeTable, $template->body_html);

                    if ($isMailActive) {
                        $managerEmail = $group['manager_email'];
                        Mail::send([], [], function ($message) use ($managerEmail, $template, $body) {
                            $message->to($managerEmail)
                                    ->subject($template->subject)
                                    ->setBody(new TextPart($body, 'utf-8', 'html'));
                        });
                    }

                    // Legacy log for backward compatibility
                    DB::table('eml_stage_email_logs')->insert([
                        'employee_id'       => $repEmployeeId,
                        'notification_type' => 'pending_hardcopy_escalation',
                        'stage'             => $notification->stage,
                        'sent_for_month'    => now()->format('Y-m'),
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    // New comprehensive log
                    app(EmailLogService::class)->log([
                        'notification_type'     => 'pending_hardcopy_escalation',
                        'mail_category'         => 'expense',
                        'stage'                 => $notification->stage,
                        'template_id'           => $notification->email_template_id,
                        'template_name'         => $template->name ?? 'Hardcopy Escalation',
                        'subject'               => $template->subject,
                        'body_html'             => $body,
                        'recipient_employee_id' => $repEmployeeId,
                        'recipient_name'        => $group['manager_name'],
                        'recipient_email'       => $group['manager_email'],
                        'sent_for_month'        => now()->format('Y-m'),
                        'is_sent'               => $isMailActive,
                    ]);

                    // sent_emails row is written by the laravel-sent-emails listener when the mail is actually sent (UAT E-08).
                }
            }

            if ($isPreview) {
                return $this->jsonSuccess($previewRows, 'Escalation preview fetched successfully.');
            }

            return $this->jsonSuccess([], 'Escalation emails processed successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Error sending escalation emails: ' . $e->getMessage(), 500);
        }
    }

    public function sendReceivingPendingEmails(Request $request)
    {
        $isPreview = $request->boolean('preview');

        $yearId = FinancialYear::where('Status', 'Active')->value('YearId');

        if (!$yearId) {
            return response()->json(['success' => false, 'message' => 'Active financial year not found.'], 400);
        }

        $table = "y{$yearId}_monthexpensefinal";
        $claimsTable = "y{$yearId}_expenseclaims";

        try {
            $notifications = DB::table('eml_notification')
                ->where('notification_type', 'pending_hardcopy')
                ->where('is_active', 1)
                ->get();

            if ($notifications->isEmpty()) {
                return $this->jsonSuccess([], 'No active notifications found for pending_hardcopy.');
            }

            // Tracker #3: the daily reminder (notifications:courier-reminder) replaces these
            // fixed-day reminders; escalation mails (sendHardCopyEscalationEmails) are unchanged.
            if (! $isPreview && config('xeasy_notifications.courier_reminder.enabled', true)) {
                return $this->jsonSuccess([], 'Daily courier reminder is enabled (notifications:courier-reminder); fixed-day hard-copy reminders skipped.');
            }

            $isMailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
            $today = Carbon::now('Asia/Kolkata');
            $todayDate = (int) $today->day;
            $previousMonth = $today->copy()->subMonth()->month;

            $allEmployees = collect();

            foreach ($notifications as $notification) {
                // Only process notifications scheduled for today
                if ((int) $todayDate !== (int) $notification->send_day) {
                    continue;
                }

                $template = DB::table('eml_email_templates')
                    ->where('id', $notification->email_template_id)
                    ->where('is_active', 1)
                    ->first();

                if (!$template) {
                    continue;
                }

                $dateCondition = true;

                $employees = DB::table("{$table} as m")
                    ->join('users as u', 'u.employee_id', '=', 'm.EmployeeId')
                    ->join("{$claimsTable} as e", function ($join) use ($previousMonth) {
                        $join
                            ->on('e.crby', '=', 'm.EmployeeId')
                            ->where('e.filledtamt', '>', 0)
                            ->where('e.claimstatus', '!=', 'Deactivate')
                            ->where('e.ClaimMonth', $previousMonth);
                    })
                    ->select('u.id', 'u.name', 'u.emp_code', 'u.email', 'm.EmployeeId', 'm.Month', 'm.Status',
                        'm.DateOfSubmit', 'm.RecevingDate', 'm.PostDate')
                    ->where('m.Month', $previousMonth)
                    ->where('m.DateOfSubmit', '!=', '0000-00-00')
                    ->where('m.Status', 'Closed')
                    ->where('m.RecevingDate', '0000-00-00')
                    ->where(function ($q) {
                        $q->where('m.PostDate', '0000-00-00')->orWhereNull('m.PostDate');
                    })
                    ->distinct()
                    ->get();

                foreach ($employees as $employee) {
                    $alreadySent = DB::table('eml_stage_email_logs')
                        ->where('employee_id', $employee->EmployeeId)
                        ->where('notification_type', 'pending_hardcopy')
                        ->where('stage', $notification->stage)
                        ->where('sent_for_month', now()->format('Y-m'))
                        ->exists();

                    if ($isPreview) {
                        $allEmployees->push([
                            'employee_id' => $employee->emp_code,
                            'employee_name' => $employee->name,
                            'email' => $employee->email,
                            'month' => Carbon::createFromFormat('!m', $employee->Month)->format('F'),
                            'status' => $employee->Status,
                            'stage' => $notification->stage,
                            'email_template_subject' => $template->subject ?? '(No template)',
                            'conditions' => [
                                'today_date' => $todayDate,
                                'send_day' => $notification->send_day,
                                'mail_active' => $isMailActive ? 'true' : 'false',
                                'date_condition' => $dateCondition ? 'true' : 'false',
                                'already_sent' => $alreadySent
                                    ? '<span class="badge bg-teal-subtle text-teal badge-border">Yes</span>'
                                    : '<span class="badge bg-dark-subtle text-dark badge-border">No</span>',
                            ],
                            'debugger' => [
                                'today_date' => $todayDate,
                                'send_day' => $notification->send_day,
                                'employee_id' => $employee->emp_code,
                                'previous_month' => $previousMonth,
                                'date_condition' => $dateCondition,
                                'status' => $employee->Status,
                            ],
                        ]);
                        continue;
                    }

                    if ($alreadySent || empty($employee->email)) {
                        continue;
                    }

                    // Check email control — submission reminder
                    if (!$this->canReceiveEmail($employee->EmployeeId, 'receiving_pending')) {
                        continue;
                    }

                    $month_name = Carbon::createFromFormat('!m', $employee->Month)->format('F');
                    $body = str_replace('{{month_name}}', $month_name, $template->body_html);

                    if ($isMailActive) {
                        Mail::send([], [], function ($message) use ($employee, $template, $body) {
                            $message
                                ->to($employee->email, $employee->name)
                                ->subject($template->subject)
                                ->setBody(new TextPart($body, 'utf-8', 'html'));
                        });
                    }

                    // Legacy log for backward compatibility
                    DB::table('eml_stage_email_logs')->insert([
                        'employee_id' => $employee->EmployeeId,
                        'notification_type' => 'pending_hardcopy',
                        'stage' => $notification->stage,
                        'sent_for_month' => now()->format('Y-m'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // New comprehensive log
                    app(EmailLogService::class)->log([
                        'notification_type'     => 'pending_hardcopy',
                        'mail_category'         => 'expense',
                        'stage'                 => $notification->stage,
                        'template_id'           => $notification->email_template_id,
                        'template_name'         => $template->name ?? 'Pending Hardcopy',
                        'subject'               => $template->subject,
                        'body_html'             => $body,
                        'recipient_employee_id' => $employee->EmployeeId,
                        'recipient_emp_code'    => $employee->emp_code,
                        'recipient_name'        => $employee->name,
                        'recipient_email'       => $employee->email,
                        'sent_for_month'        => now()->format('Y-m'),
                        'is_sent'               => $isMailActive,
                    ]);

                    // sent_emails row is written by the laravel-sent-emails listener when the mail is actually sent (UAT E-08).
                }
            }

            if ($isPreview) {
                return $this->jsonSuccess($allEmployees, 'Pending hard-copy employees fetched successfully.');
            }

            return $this->jsonSuccess([], 'Receiving pending emails processed successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Error sending receiving pending emails: ' . $e->getMessage(), 500);
        }
    }

    public function sendDynamicNonSubmittedEmails(Request $request)
    {
        $isPreview = $request->boolean('preview');

        $yearId = FinancialYear::where('Status', 'Active')->value('YearId');

        if (!$yearId) {
            return response()->json(['success' => false, 'message' => 'year_id is required.'], 400);
        }

        $table = "y{$yearId}_monthexpensefinal";
        $claimsTable = "y{$yearId}_expenseclaims";

        try {
            $notifications = DB::table('eml_notification')
                ->where('notification_type', 'expense_submission')
                ->where('is_active', 1)
                ->get();

            if ($notifications->isEmpty()) {
                return $this->jsonSuccess([], 'No active notifications found.');
            }

            $isMailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
            $today = Carbon::now('Asia/Kolkata');
            $todayDate = (int) $today->day;
            $previousMonth = $today->copy()->subMonth()->month;

            $allEmployees = collect();

            foreach ($notifications as $notification) {
                // Only process notifications scheduled for today
                if ((int) $todayDate !== (int) $notification->send_day) {
                    continue;
                }

                $template = DB::table('eml_email_templates')
                    ->where('id', $notification->email_template_id)
                    ->where('is_active', 1)
                    ->first();

                if (!$template) {
                    continue;
                }

                $employees = DB::table("{$table} as m")
                    ->join('users as u', 'u.employee_id', '=', 'm.EmployeeId')
                    ->join("{$claimsTable} as e", function ($join) use ($previousMonth) {
                        $join
                            ->on('e.crby', '=', 'm.EmployeeId')
                            ->where(function ($q) {
                                $q->where('e.filledtamt', '>', 0)->orWhere('e.FilledOkay', 1);
                            })
                            ->where('e.claimstatus', '!=', 'Deactivate')
                            ->where('e.ClaimMonth', $previousMonth);
                    })
                    ->select('u.id', 'u.name', 'u.emp_code', 'u.email', 'm.EmployeeId', 'm.Month', 'm.Status',
                        'm.DateOfSubmit', 'm.RecevingDate', 'm.Verified_Date',
                        'm.Approved_Date', 'm.Finance_Date', 'm.Fin_PayDate')
                    ->where('m.Month', $previousMonth)
                    ->where('m.Status', 'Open')
                    ->distinct()
                    ->get();

                foreach ($employees as $employee) {
                    $alreadySent = DB::table('eml_stage_email_logs')
                        ->where('employee_id', $employee->EmployeeId)
                        ->where('notification_type', 'expense_submission')
                        ->where('stage', $notification->stage)
                        ->where('sent_for_month', now()->format('Y-m'))
                        ->exists();

                    if ($isPreview) {
                        $allEmployees->push([
                            'employee_id' => $employee->emp_code,
                            'employee_name' => $employee->name,
                            'email' => $employee->email,
                            'month' => Carbon::createFromFormat('!m', $employee->Month)->format('F'),
                            'status' => $employee->Status,
                            'stage' => $notification->stage,
                            'email_template_subject' => $template->subject ?? '(No template)',
                            'conditions' => [
                                'today_date' => $todayDate,
                                'send_day' => $notification->send_day,
                                'mail_active' => $isMailActive ? 'true' : 'false',
                                'date_condition' => 'true',
                                'already_sent' => $alreadySent
                                    ? '<span class="badge bg-teal-subtle text-teal badge-border">Yes</span>'
                                    : '<span class="badge bg-dark-subtle text-dark badge-border">No</span>',
                            ],
                            'debugger' => [
                                'today_date' => $todayDate,
                                'send_day' => $notification->send_day,
                                'employee_id' => $employee->EmployeeId,
                                'previous_month' => $previousMonth,
                                'date_condition' => true,
                                'status' => $employee->Status,
                            ],
                        ]);
                        continue;
                    }

                    if ($alreadySent || empty($employee->email)) {
                        continue;
                    }

                    // Check email control — submission reminder
                    if (!$this->canReceiveEmail($employee->EmployeeId, 'submission_reminder')) {
                        continue;
                    }

                    $month_name = Carbon::createFromFormat('!m', $employee->Month)->format('F');
                    $body = str_replace('{{month_name}}', $month_name, $template->body_html);

                    if ($isMailActive) {
                        Mail::send([], [], function ($message) use ($employee, $template, $body) {
                            $message
                                ->to($employee->email, $employee->name)
                                ->subject($template->subject)
                                ->setBody(new TextPart($body, 'utf-8', 'html'));
                        });
                    }

                    // Legacy log for backward compatibility
                    DB::table('eml_stage_email_logs')->insert([
                        'employee_id' => $employee->EmployeeId,
                        'notification_type' => 'expense_submission',
                        'stage' => $notification->stage,
                        'sent_for_month' => now()->format('Y-m'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // New comprehensive log
                    app(EmailLogService::class)->log([
                        'notification_type'     => 'expense_submission',
                        'mail_category'         => 'expense',
                        'stage'                 => $notification->stage,
                        'template_id'           => $notification->email_template_id,
                        'template_name'         => $template->name ?? 'Expense Submission Reminder',
                        'subject'               => $template->subject,
                        'body_html'             => $body,
                        'recipient_employee_id' => $employee->EmployeeId,
                        'recipient_emp_code'    => $employee->emp_code,
                        'recipient_name'        => $employee->name,
                        'recipient_email'       => $employee->email,
                        'sent_for_month'        => now()->format('Y-m'),
                        'is_sent'               => $isMailActive,
                    ]);

                    // sent_emails row is written by the laravel-sent-emails listener when the mail is actually sent (UAT E-08).
                }
            }

            if ($isPreview) {
                return $this->jsonSuccess($allEmployees, 'Employees fetched successfully.');
            }

            return $this->jsonSuccess([], 'Emails processed successfully.');
        } catch (\Exception $e) {
            return $this->jsonError('Error sending emails: ' . $e->getMessage(), 500);
        }
    }
}
