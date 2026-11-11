<?php

namespace App\Services\Notifications;

use App\Services\EmailLogService;
use App\Support\CurrentYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Part\TextPart;

/**
 * APPR-REM-01 (tracker, 27 Oct; decision 5 Nov 2026): reporting-manager approval reminder.
 *
 * Until now the manager heard about a pending approval only once (when the verifier verified the
 * month, VNR) or on a fixed day of the month. This reminder goes to the reporting manager when a
 * submitted month is still waiting for his approval 3 days after the employee submitted it, and
 * again every 3 days until he approves. One mail per manager per run, listing every employee-month
 * waiting for him. Logged in the mail log with "Triggered By" = System.
 *
 * Waiting for approval:
 *   VNR  (1)  verified, not yet approved   (submit -> receive -> verify -> approve)
 *   VVNR (11) submitted, not yet approved  (submit -> approve -> pay -> courier -> verify)
 */
class ManagerApprovalReminderService
{
    public const NOTIFICATION_TYPE = 'approval_reminder_manager';

    /** @return array{company:int, year_id:int, months:int, managers:int, sent:int, skipped:int, dry_run:bool, recipients:array} */
    public function run(int $companyId, bool $dryRun = false): array
    {
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $yearId = CurrentYear::id($conn);
        $stats = ['company' => $companyId, 'year_id' => $yearId, 'months' => 0, 'managers' => 0, 'sent' => 0, 'skipped' => 0, 'dry_run' => $dryRun, 'recipients' => []];
        if (! $yearId) {
            return $stats;
        }
        $today = Carbon::now('Asia/Kolkata')->startOfDay();
        $startAfter = max(0, (int) config('xeasy_notifications.approval_reminder.start_after_days', 3));
        $every = max(1, (int) config('xeasy_notifications.approval_reminder.repeat_every_days', 3));
        $mailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);

        $rows = $this->pending($conn, $companyId, $yearId, $today->copy()->subDays($startAfter));
        $stats['months'] = $rows->count();
        $byManager = $rows->groupBy('manager_id');
        $stats['managers'] = $byManager->count();

        foreach ($byManager as $managerId => $items) {
            $mgr = $items->first();
            if (empty($mgr->manager_email) || $this->sentWithin((int) $managerId, $companyId, $every, $today)) {
                $stats['skipped']++;
                continue;
            }
            $subject = 'Xeasy: '.$items->count().' expense claim'.($items->count() > 1 ? 's' : '').' waiting for your approval';
            $body = $this->body($mgr->manager_name, $items, $companyId);
            $stats['recipients'][] = ['manager' => $mgr->manager_code, 'months' => $items->count()];
            if ($dryRun) {
                $stats['sent']++;
                continue;
            }
            try {
                if ($mailActive) {
                    Mail::send([], [], function ($m) use ($mgr, $subject, $body) {
                        $m->to($mgr->manager_email, $mgr->manager_name)->subject($subject)->setBody(new TextPart($body, 'utf-8', 'html'));
                    });
                }
                DB::table('eml_stage_email_logs')->insert([
                    'employee_id' => $managerId,
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'stage' => $companyId,
                    'sent_for_month' => $today->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(EmailLogService::class)->log([
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'mail_category' => 'expense',
                    'stage' => 'approval_pending',
                    'subject' => $subject,
                    'body_html' => $body,
                    'recipient_employee_id' => $managerId,
                    'recipient_emp_code' => $mgr->manager_code,
                    'recipient_name' => $mgr->manager_name,
                    'recipient_email' => $mgr->manager_email,
                    'sent_for_month' => $today->format('Y-m'),
                    'year_id' => $yearId,
                    'company_id' => $companyId,
                    'ref_type' => 'approval_reminder',
                    'ref_id' => $managerId,
                    'is_sent' => $mailActive,
                    'sent_at' => $mailActive ? now() : null,
                ]);
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['skipped']++;
                Log::error('ManagerApprovalReminderService: mail to manager '.$managerId.' failed: '.$e->getMessage());
            }
        }

        return $stats;
    }

    /** Employee-months waiting for the reporting manager, submitted on or before $submittedBy. */
    public function pending(string $conn, int $companyId, int $yearId, Carbon $submittedBy)
    {
        $empty = fn ($col) => fn ($q) => $q->whereNull($col)->orWhere($col, '0000-00-00')->orWhere($col, '');
        $q = DB::connection($conn)->table("y{$yearId}_monthexpensefinal as m")
            ->where('m.YearId', $yearId)
            ->whereNotNull('m.DateOfSubmit')->where('m.DateOfSubmit', '>', '0000-00-00')
            ->whereDate('m.DateOfSubmit', '<=', $submittedBy->toDateString())
            // REM-SET-01: stop reminding after the set age
            ->when((int) config('xeasy_notifications.approval_reminder.stop_after_days', 90) > 0, fn ($x) => $x->whereDate('m.DateOfSubmit', '>=', now('Asia/Kolkata')->subDays((int) config('xeasy_notifications.approval_reminder.stop_after_days', 90))->toDateString()))
            ->where($empty('m.Approved_Date'));
        if ($companyId == 11) {
            $q->where($empty('m.Fin_PayDate'));
        } else {
            $q->whereNotNull('m.Verified_Date')->where('m.Verified_Date', '>', '0000-00-00');
        }
        $months = $q->get(['m.EmployeeID', 'm.Month', 'm.DateOfSubmit', 'm.Verified_Date', 'm.Claim_Amount', 'm.Verified_Amount']);
        if ($months->isEmpty()) {
            return collect();
        }

        $hr = DB::connection('hrims');
        $emps = $hr->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->whereIn('e.EmployeeID', $months->pluck('EmployeeID')->unique()->all())
            ->get(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.RepEmployeeID'])->keyBy('EmployeeID');
        $mgrs = $hr->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->whereIn('e.EmployeeID', $emps->pluck('RepEmployeeID')->filter()->unique()->all())
            ->where('e.EmpStatus', 'A')
            ->get(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.EmailId_Vnr'])->keyBy('EmployeeID');
        $name = fn ($p) => trim(preg_replace('/\s+/', ' ', ($p->Fname ?? '').' '.($p->Sname ?? '').' '.($p->Lname ?? '')));

        return $months->map(function ($m) use ($emps, $mgrs, $name) {
            $e = $emps[$m->EmployeeID] ?? null;
            $g = $e ? ($mgrs[$e->RepEmployeeID] ?? null) : null;
            if (! $e || ! $g) {
                return null;
            }

            return (object) [
                'employee_id' => (int) $m->EmployeeID, 'emp_code' => $e->EmpCode, 'emp_name' => $name($e),
                'month' => (int) $m->Month, 'submitted' => $m->DateOfSubmit, 'amount' => (float) ($m->Verified_Amount ?: $m->Claim_Amount),
                'manager_id' => (int) $g->EmployeeID, 'manager_code' => $g->EmpCode, 'manager_name' => $name($g), 'manager_email' => trim((string) $g->EmailId_Vnr),
            ];
        })->filter()->values();
    }

    private function sentWithin(int $managerId, int $companyId, int $days, Carbon $today): bool
    {
        return DB::table('eml_stage_email_logs')
            ->where('employee_id', $managerId)->where('notification_type', self::NOTIFICATION_TYPE)->where('stage', $companyId)
            ->where('sent_for_month', '>', $today->copy()->subDays($days)->toDateString())
            ->exists();
    }

    private function body(string $managerName, $items, int $companyId): string
    {
        $rows = '';
        foreach ($items->sortBy('submitted') as $i) {
            $days = Carbon::parse($i->submitted)->startOfDay()->diffInDays(Carbon::now('Asia/Kolkata')->startOfDay());
            $rows .= '<tr><td style="padding:4px 8px;border:1px solid #ddd">'.e($i->emp_code.' - '.$i->emp_name).'</td>'
                .'<td style="padding:4px 8px;border:1px solid #ddd">'.Carbon::createFromFormat('!m', $i->month)->format('F').'</td>'
                .'<td style="padding:4px 8px;border:1px solid #ddd">'.Carbon::parse($i->submitted)->format('d/m/Y').'</td>'
                .'<td style="padding:4px 8px;border:1px solid #ddd;text-align:right">'.(int) $days.'</td>'
                .'<td style="padding:4px 8px;border:1px solid #ddd;text-align:right">'.number_format($i->amount, 2).'</td></tr>';
        }
        $co = $companyId == 11 ? 'VNR Vegetable Nursery (VVNR)' : 'VNR Seeds';

        return '<p>Dear '.e($managerName).',</p>'
            .'<p>The following expense claims of your team ('.$co.') are waiting for your approval in Xeasy:</p>'
            .'<table style="border-collapse:collapse;font-size:13px"><tr style="background:#f3f4f6">'
            .'<th style="padding:4px 8px;border:1px solid #ddd">Employee</th><th style="padding:4px 8px;border:1px solid #ddd">Month</th>'
            .'<th style="padding:4px 8px;border:1px solid #ddd">Submitted on</th><th style="padding:4px 8px;border:1px solid #ddd">Days waiting</th>'
            .'<th style="padding:4px 8px;border:1px solid #ddd">Amount (Rs)</th></tr>'.$rows.'</table>'
            .'<p>Please log in to Xeasy and approve or return them.</p><p>Regards,<br>Xeasy (automatic reminder)</p>';
    }
}
