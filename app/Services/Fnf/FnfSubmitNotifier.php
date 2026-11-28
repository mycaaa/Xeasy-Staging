<?php

namespace App\Services\Fnf;

use App\Services\EmailLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Part\TextPart;

/**
 * FNF-MAIL-01 (28 Nov 2026, testing point 48): when an employee who is leaving (resignation or separation date in HR)
 * submits a month's claim, the month goes to the reporting manager for approval as for anyone else, and the manager is
 * told at once by mail that it is part of the Full & Final settlement. Never stops the submission: any failure is logged.
 */
class FnfSubmitNotifier
{
    public const NOTIFICATION_TYPE = 'fnf_submit_manager';

    /** @return array{sent:bool, reason:string} */
    public static function notify(int $companyId, int $yearId, int $empId, int $month, float $amount = 0.0): array
    {
        try {
            $hr = DB::connection(\App\Helpers\DatabaseSwitcher::getHrimsConnection($companyId) ?: 'hrims');
            $e = $hr->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->where('e.EmployeeID', $empId)->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'e.DateOfResignation', 'e.DateOfSepration', 'g.RepEmployeeID']);
            $set = fn ($d) => $d && (string) $d > '2000-01-01';
            if (! $e || (! $set($e->DateOfResignation ?? null) && ! $set($e->DateOfSepration ?? null))) {
                return ['sent' => false, 'reason' => 'not leaving'];
            }
            if (! (int) $e->RepEmployeeID || (int) $e->RepEmployeeID === $empId) {
                return ['sent' => false, 'reason' => 'no reporting manager'];
            }
            $m = $hr->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->where('e.EmployeeID', (int) $e->RepEmployeeID)->where('e.EmpStatus', 'A')->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.EmailId_Vnr']);
            $email = trim((string) ($m->EmailId_Vnr ?? ''));
            if (! $m || $email === '') {
                return ['sent' => false, 'reason' => 'manager has no mail address'];
            }
            $name = fn ($p) => trim(preg_replace('/\s+/', ' ', ($p->Fname ?? '').' '.($p->Sname ?? '').' '.($p->Lname ?? '')));
            $mon = date('F', mktime(0, 0, 0, $month, 1));
            $lwd = $set($e->DateOfSepration ?? null) ? date('d/m/Y', strtotime((string) $e->DateOfSepration)) : null;
            $co = $companyId === 11 ? 'VNR Vegetable Nursery (VVNR)' : 'VNR Seeds';
            $subject = "Xeasy: F&F - {$e->EmpCode} {$name($e)} submitted the {$mon} claim for your approval";
            $body = '<p>Dear '.e($name($m)).',</p>'
                .'<p>'.e($e->EmpCode.' - '.$name($e)).' ('.$co.')'.($lwd ? ', whose last working day is <b>'.$lwd.'</b>,' : ', who is leaving the company,')
                .' has submitted the expense claim for <b>'.$mon.'</b>'.($amount > 0 ? ' (Rs '.number_format($amount, 2).')' : '').'.</p>'
                .'<p>This claim is part of the <b>Full &amp; Final settlement</b>. Please log in to Xeasy and approve or return it (Approvals) at the earliest, so that Finance can complete the F&amp;F.</p>'
                .'<p>Regards,<br>Xeasy (automatic mail)</p>';
            $active = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
            if ($active) {
                Mail::send([], [], fn ($msg) => $msg->to($email, $name($m))->subject($subject)->setBody(new TextPart($body, 'utf-8', 'html')));
            }
            app(EmailLogService::class)->log([
                'notification_type' => self::NOTIFICATION_TYPE, 'mail_category' => 'expense', 'stage' => 'fnf_approval_pending',
                'subject' => $subject, 'body_html' => $body,
                'recipient_employee_id' => (int) $m->EmployeeID, 'recipient_emp_code' => $m->EmpCode, 'recipient_name' => $name($m), 'recipient_email' => $email,
                'sent_for_month' => now()->format('Y-m'), 'year_id' => $yearId, 'company_id' => $companyId,
                'ref_type' => 'fnf_month_submit', 'ref_id' => $empId,
                'is_sent' => $active, 'sent_at' => $active ? now() : null,
            ]);

            return ['sent' => $active, 'reason' => $active ? 'sent' : 'logged (mail sending is off)'];
        } catch (\Throwable $ex) {
            Log::warning('FNF-MAIL-01: manager mail failed', ['employee' => $empId, 'month' => $month, 'error' => $ex->getMessage()]);

            return ['sent' => false, 'reason' => 'error: '.$ex->getMessage()];
        }
    }
}
