<?php

namespace App\Services\Notifications;

use App\Mail\EmployeeClaimUpdateNotification;
use App\Models\FinancialYear;
use App\Services\EmailLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tracker #29: tell employees, by mail, when
 *  (a) a claim is verified or approved for less than claimed (amount + remark),
 *  (b) their month is approved / cleared by Finance,
 *  (c) the payment is confirmed (amount, date, UTR).
 *
 * Runs as a scheduled scan (notifications:employee-claim-updates) over the
 * active year's tables instead of hooking each save path: verification,
 * approval and payment are written from many controllers, the mobile API and
 * the Matrix bank sync, and a scan catches all of them. Each event is sent
 * once, keyed in eml_stage_email_logs; only events dated within lookback_days
 * are picked up, so switching the feature on does not mail the history.
 */
class EmployeeClaimNotificationService
{
    /** @return array<string,int> counts per kind (+ skipped) */
    public function run(bool $dryRun = false): array
    {
        $stats = ['deduction' => 0, 'month_approved' => 0, 'month_financed' => 0, 'payment_confirmed' => 0, 'skipped' => 0];
        if (! config('xeasy_notifications.employee.enabled', true)) {
            return $stats;
        }
        $yearId = (int) FinancialYear::where('Status', 'Active')->value('YearId');
        if ($yearId <= 0) {
            return $stats;
        }
        $from = Carbon::now('Asia/Kolkata')->subDays(max(0, (int) config('xeasy_notifications.employee.lookback_days', 2)))->toDateString();

        $this->deductions($yearId, $from, $dryRun, $stats);
        $this->monthEvents($yearId, $from, $dryRun, $stats);

        return $stats;
    }

    private function deductions(int $yearId, string $from, bool $dryRun, array &$stats): void
    {
        $rows = DB::table("y{$yearId}_expenseclaims as e")
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'e.ClaimId')
            ->where('e.ClaimStatus', '!=', 'Deactivate')
            ->where(function ($q) use ($from) {
                $q->where(fn ($v) => $v->where('e.VerifyDate', '>=', $from)->whereColumn('e.VerifyTAmt', '<', 'e.FilledTAmt'))
                  ->orWhere(fn ($a) => $a->where('e.ApprDate', '>=', $from)->whereColumn('e.ApprTAmt', '<', 'e.VerifyTAmt'));
            })
            ->select('e.ExpId', 'e.CrBy', 'e.BillDate', 'e.FilledTAmt', 'e.VerifyTAmt', 'e.VerifyTRemark', 'e.VerifyDate',
                'e.ApprTAmt', 'e.ApprTRemark', 'e.ApprDate', 'ct.ClaimName')
            ->get();

        $byEmployeeStage = [];
        foreach ($rows as $r) {
            if ($r->VerifyDate >= $from && (float) $r->VerifyTAmt < (float) $r->FilledTAmt) {
                $byEmployeeStage[$r->CrBy]['verification'][] = ['row' => $r, 'from' => (float) $r->FilledTAmt, 'to' => (float) $r->VerifyTAmt, 'remark' => trim((string) $r->VerifyTRemark)];
            }
            if ($r->ApprDate >= $from && (float) $r->ApprTAmt < (float) $r->VerifyTAmt) {
                $byEmployeeStage[$r->CrBy]['approval'][] = ['row' => $r, 'from' => (float) $r->VerifyTAmt, 'to' => (float) $r->ApprTAmt, 'remark' => trim((string) $r->ApprTRemark)];
            }
        }

        foreach ($byEmployeeStage as $employeeId => $stages) {
            foreach ($stages as $stage => $items) {
                $type = "employee_deduction_{$stage}";
                $new = array_values(array_filter($items, fn ($i) => ! $this->alreadySent((int) $employeeId, $type, (int) $i['row']->ExpId, "Y{$yearId}")));
                if (empty($new)) {
                    continue;
                }
                $lines = array_map(fn ($i) => [
                    'exp_id' => $i['row']->ExpId,
                    'claim' => $i['row']->ClaimName ?? 'Claim',
                    'bill_date' => $i['row']->BillDate ? Carbon::parse($i['row']->BillDate)->format('d-m-Y') : '',
                    'from' => $i['from'], 'to' => $i['to'], 'remark' => $i['remark'],
                ], $new);
                $keys = array_map(fn ($i) => (int) $i['row']->ExpId, $new);
                $this->deliver((int) $employeeId, 'deduction', ['stage' => $stage, 'lines' => $lines], $type, $keys, "Y{$yearId}", 'approval_notification', $dryRun, $stats);
            }
        }
    }

    private function monthEvents(int $yearId, string $from, bool $dryRun, array &$stats): void
    {
        $rows = DB::table("y{$yearId}_monthexpensefinal")
            ->where('YearId', $yearId)
            ->where(function ($q) use ($from) {
                $q->where('Approved_Date', '>=', $from)
                  ->orWhere('Finance_Date', '>=', $from)
                  ->orWhere(fn ($p) => $p->where('payment_status', 'ConfirmedPayment')->where('payment_confirmed_date', '>=', $from));
            })
            ->get();

        foreach ($rows as $m) {
            $month = Carbon::createFromFormat('!m', (int) $m->Month)->format('F');
            if ((string) $m->Approved_Date >= $from) {
                $this->deliverOnce((int) $m->EmployeeID, 'month_approved', ['monthName' => $month, 'amount' => $m->Approved_Amount], (int) $m->Month, $yearId, 'approval_notification', $dryRun, $stats);
            }
            if ((string) $m->Finance_Date >= $from) {
                $this->deliverOnce((int) $m->EmployeeID, 'month_financed', ['monthName' => $month, 'amount' => $m->Finance_Amount], (int) $m->Month, $yearId, 'approval_notification', $dryRun, $stats);
            }
            if ($m->payment_status === 'ConfirmedPayment' && (string) $m->payment_confirmed_date >= $from) {
                $amount = (float) ($m->payment_amount ?? 0) > 0 ? $m->payment_amount : $m->Fin_PayAmt;
                $date = $this->validDate($m->payment_date) ?? $this->validDate($m->Fin_PayDate) ?? $this->validDate($m->payment_confirmed_date);
                $this->deliverOnce((int) $m->EmployeeID, 'payment_confirmed', [
                    'monthName' => $month,
                    'amount' => $amount,
                    'date' => $date ? Carbon::parse($date)->format('d-m-Y') : null,
                    'utr' => trim((string) ($m->utr_number ?: $m->bank_reference)),
                    'remark' => trim((string) $m->Fin_PayRemark),
                ], (int) $m->Month, $yearId, 'payment_notification', $dryRun, $stats);
            }
        }
    }

    private function deliverOnce(int $employeeId, string $kind, array $data, int $month, int $yearId, string $controlType, bool $dryRun, array &$stats): void
    {
        $type = "employee_{$kind}";
        if ($this->alreadySent($employeeId, $type, $month, "Y{$yearId}")) {
            return;
        }
        $this->deliver($employeeId, $kind, $data, $type, [$month], "Y{$yearId}", $controlType, $dryRun, $stats);
    }

    /** Queue one mail and log one dedupe row per key. */
    private function deliver(int $employeeId, string $kind, array $data, string $type, array $keys, string $period, string $controlType, bool $dryRun, array &$stats): void
    {
        $user = DB::table('users')->where('employee_id', $employeeId)->select('name', 'email', 'emp_code')->first();
        if (! $user || empty($user->email) || ! $this->canReceive($employeeId, $controlType)) {
            $stats['skipped']++;

            return;
        }
        $stats[$kind]++;
        if ($dryRun) {
            return;
        }
        $data['employeeName'] = $user->name;
        $mailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
        try {
            $mailable = new EmployeeClaimUpdateNotification($kind, $data);
            if ($mailActive) {
                Mail::to($user->email, $user->name)->queue($mailable);
            }
            foreach ($keys as $key) {
                DB::table('eml_stage_email_logs')->insert([
                    'employee_id' => $employeeId, 'notification_type' => $type, 'stage' => $key,
                    'sent_for_month' => $period, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            app(EmailLogService::class)->log([
                'notification_type' => $type,
                'mail_category' => 'expense',
                'subject' => $mailable->build()->subject,
                'recipient_employee_id' => $employeeId,
                'recipient_emp_code' => $user->emp_code,
                'recipient_name' => $user->name,
                'recipient_email' => $user->email,
                'sent_for_month' => now()->format('Y-m'),
                'is_sent' => $mailActive,
            ]);
        } catch (\Throwable $e) {
            Log::error("EmployeeClaimNotificationService: {$kind} for employee {$employeeId} failed: ".$e->getMessage());
        }
    }

    private function alreadySent(int $employeeId, string $type, int $key, string $period): bool
    {
        return DB::table('eml_stage_email_logs')
            ->where('employee_id', $employeeId)->where('notification_type', $type)
            ->where('stage', $key)->where('sent_for_month', $period)
            ->exists();
    }

    /** Same switches as NotificationController::canReceiveEmail(). */
    private function canReceive(int $employeeId, string $controlType): bool
    {
        $control = DB::table('eml_email_control')->where('employee_id', $employeeId)->first();
        if (! $control) {
            return true;
        }
        $column = $controlType === 'payment_notification' ? 'receive_payment_notification' : 'receive_approval_notification';

        return (bool) $control->is_active && (bool) ($control->{$column} ?? true);
    }

    private function validDate($value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || str_starts_with($value, '0000-00-00')) ? null : substr($value, 0, 10);
    }
}
