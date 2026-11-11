<?php

namespace App\Services\Notifications;

use App\Models\FinancialYear;
use App\Services\EmailLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Tracker #3: from the day after an employee submits a month, remind them
 * daily to enter the courier (hard-copy dispatch) details, until PostDate is
 * entered or the hard copy is received. One mail per employee-month per day.
 */
class CourierReminderService
{
    public const NOTIFICATION_TYPE = 'pending_hardcopy_daily';

    /** @return array{candidates:int, sent:int, skipped:int, dry_run:bool} */
    public function run(bool $dryRun = false): array
    {
        $stats = ['candidates' => 0, 'sent' => 0, 'skipped' => 0, 'dry_run' => $dryRun];
        $yearId = FinancialYear::where('Status', 'Active')->value('YearId');
        $template = $this->template();
        if (! $yearId || ! $template) {
            Log::warning('CourierReminderService: no active financial year or reminder template.');

            return $stats;
        }

        $today = Carbon::now('Asia/Kolkata')->startOfDay();
        $mailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
        $maxDays = (int) config('xeasy_notifications.courier_reminder.max_days', 0);
        $startAfter = (int) config('xeasy_notifications.courier_reminder.start_after_days', 3);

        foreach ($this->pending($yearId, $today) as $row) {
            $stats['candidates']++;
            $submitted = Carbon::parse($row->DateOfSubmit, 'Asia/Kolkata')->startOfDay();
            // COURIER-03: no reminder until 3 days after submission
            if ($submitted->diffInDays($today) < $startAfter) {
                $stats['skipped']++;
                continue;
            }
            if ($maxDays > 0 && $submitted->diffInDays($today) > $maxDays) {
                $stats['skipped']++;
                continue;
            }
            if (empty($row->email) || ! $this->canReceive((int) $row->EmployeeID) || $this->alreadySentToday((int) $row->EmployeeID, (int) $row->Month, $today)) {
                $stats['skipped']++;
                continue;
            }
            if ($dryRun) {
                $stats['sent']++;
                continue;
            }

            $monthName = Carbon::createFromFormat('!m', (int) $row->Month)->format('F');
            $body = str_replace('{{month_name}}', $monthName, (string) $template->body_html);
            try {
                if ($mailActive) {
                    Mail::send([], [], function ($message) use ($row, $template, $body) {
                        $message->to($row->email, $row->name)
                            ->subject($template->subject)
                            ->setBody(new TextPart($body, 'utf-8', 'html'));
                    });
                }
                DB::table('eml_stage_email_logs')->insert([
                    'employee_id' => $row->EmployeeID,
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'stage' => (int) $row->Month,
                    'sent_for_month' => $today->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(EmailLogService::class)->log([
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'mail_category' => 'expense',
                    'stage' => (int) $row->Month,
                    'template_id' => $template->id,
                    'template_name' => $template->name ?? 'Pending Hard-Copy Courier Reminder',
                    'subject' => $template->subject,
                    'body_html' => $body,
                    'recipient_employee_id' => $row->EmployeeID,
                    'recipient_emp_code' => $row->emp_code,
                    'recipient_name' => $row->name,
                    'recipient_email' => $row->email,
                    'sent_for_month' => $today->format('Y-m'),
                    'is_sent' => $mailActive,
                ]);
                // sent_emails: the dcblogdev/laravel-sent-emails listener already stores every sent mail
                // (UAT E-08: the explicit Sentemail::create here logged each reminder twice).
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['skipped']++;
                Log::error('CourierReminderService: mail failed for employee '.$row->EmployeeID.': '.$e->getMessage());
            }
        }

        return $stats;
    }

    /** Submitted (Closed) months of the active year, submitted before today, courier date not entered. */
    private function pending(int $yearId, Carbon $today)
    {
        $table = "y{$yearId}_monthexpensefinal";
        $claimsTable = "y{$yearId}_expenseclaims";

        return DB::table("{$table} as m")
            ->join('users as u', 'u.employee_id', '=', 'm.EmployeeID')
            ->where('m.YearId', $yearId)
            ->where('m.Status', 'Closed')
            ->whereNotNull('m.DateOfSubmit')
            ->where('m.DateOfSubmit', '>', '0000-00-00')
            ->where('m.DateOfSubmit', '<', $today->toDateString())
            ->where(fn ($q) => $q->whereNull('m.PostDate')->orWhere('m.PostDate', '0000-00-00'))
            ->where(fn ($q) => $q->whereNull('m.RecevingDate')->orWhere('m.RecevingDate', '0000-00-00'))
            ->whereExists(function ($q) use ($claimsTable) {
                $q->select(DB::raw(1))->from("{$claimsTable} as e")
                    ->whereColumn('e.CrBy', 'm.EmployeeID')
                    ->whereColumn('e.ClaimMonth', 'm.Month')
                    ->where('e.FilledTAmt', '>', 0)
                    ->where('e.ClaimStatus', '!=', 'Deactivate');
            })
            ->select('m.EmployeeID', 'm.Month', 'm.DateOfSubmit', 'u.name', 'u.emp_code', 'u.email')
            ->distinct()
            ->get();
    }

    private function template(): ?object
    {
        $templateId = config('xeasy_notifications.courier_reminder.template_id')
            ?: DB::table('eml_notification')->where('notification_type', 'pending_hardcopy')->orderBy('stage')->value('email_template_id');

        return $templateId
            ? DB::table('eml_email_templates')->where('id', $templateId)->where('is_active', 1)->first()
            : null;
    }

    private function alreadySentToday(int $employeeId, int $month, Carbon $today): bool
    {
        return DB::table('eml_stage_email_logs')
            ->where('employee_id', $employeeId)
            ->where('notification_type', self::NOTIFICATION_TYPE)
            ->where('stage', $month)
            ->where('sent_for_month', $today->toDateString())
            ->exists();
    }

    /** Same rule as NotificationController::canReceiveEmail(..., 'receiving_pending'). */
    private function canReceive(int $employeeId): bool
    {
        $control = DB::table('eml_email_control')->where('employee_id', $employeeId)->first();
        if (! $control) {
            return true;
        }

        return (bool) $control->is_active && (bool) $control->receive_receiving_pending;
    }
}
