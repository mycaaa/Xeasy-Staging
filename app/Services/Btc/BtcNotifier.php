<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-NOTIFY-01 (17 Nov 2026): mail at each hand-off (to whoever acts next) and a daily escalation when a stage runs
 * past its time limit (to the person who should act, copied to their reporting manager / the Finance head).
 * Off until switched on in BTC Settings; a test address can receive every mail instead of the real people.
 */
class BtcNotifier
{
    /** stage => [who acts (label), default days allowed] */
    public const STAGES = [
        'pending_manager' => ['Reporting manager approval', 2],
        'booking_requested' => ['BTC admin booking', 2],
        'advance_requested' => ['Finance Advance Approval and payment', 2],
        'scanned' => ['Classification of the scanned bill', 1],   // BTC-SCAN-01
        'advance_paid' => ['Requester: upload the bill', 15],
        'booked' => ['Admin: bill to DMS / requester: upload the bill', 15],
        'returned' => ['Requester: correction', 3],
        // BTC-V2 (19 Nov 2026): revised workflow
        'bill_with_dms' => ['DMS: receive the physical bill, hand to Finance', 2],
        'bill_with_finance' => ['Finance: scan and upload the bill', 2],
        'bill_submitted' => ['Punch Screen', 2],
        'extracted' => ['Finance Verifier 1', 2],
        'finance_verified' => ['Finance Verifier 1: Focus entry', 2],
        'focus_done' => ['Finance Approval', 2],
        'fin_approved' => ['Treasury payment', 3],
        'paid' => ['Finance closure', 15],
    ];

    public static function enabled(): bool
    {
        return (bool) config('btc.notify.enabled', false) && Schema::hasTable('btc_notifications');
    }

    public static function slaDays(string $stage): int
    {
        return (int) config("btc.sla.{$stage}", self::STAGES[$stage][1] ?? 3);
    }

    /** When the case entered its current stage. */
    public static function since(BtcCase $c): \Carbon\Carbon
    {
        $at = DB::table('btc_case_logs')->where('btc_case_id', $c->id)->where('to_status', $c->status)->where('from_status', '!=', $c->status)->max('created_at');

        return \Carbon\Carbon::parse($at ?: $c->updated_at ?: $c->created_at);
    }

    /** [ [email, name], ... ] of the people who act on the case at its current stage. */
    public static function actors(BtcCase $c): array
    {
        $emp = function (?int $id) {
            if (! $id) {
                return [];
            }
            $u = User::where('employee_id', $id)->first(['email', 'name']);
            if ($u && $u->email) {
                return [[$u->email, $u->name]];
            }
            $e = DB::connection('hrims')->table('hrm_employee_general as g')->join('hrm_employee as e', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->where('g.EmployeeID', $id)->first(['g.EmailId_Vnr', 'e.Fname', 'e.Lname']);

            return $e && $e->EmailId_Vnr ? [[$e->EmailId_Vnr, trim($e->Fname.' '.$e->Lname)]] : [];
        };
        $perm = function (string $p) use ($c) {
            try {
                return User::permission($p)->whereNotNull('email')->get(['email', 'name'])->map(fn ($u) => [$u->email, $u->name])->all();
            } catch (\Throwable $e) {
                return [];
            }
        };
        $owner = fn () => $emp((int) $c->employee_id) ?: (($u = User::find($c->created_by)) && $u->email ? [[$u->email, $u->name]] : []);

        return match ($c->status) {
            'pending_manager' => $emp((int) $c->manager_id),
            'booking_requested' => $perm('btc.booking'),
            'advance_requested' => in_array($c->adv_stage ?? null, ['instructed'], true) || ! Schema::hasColumn('btc_cases', 'adv_stage') ? $perm('btc.advance') : $perm('btc.fin_approver'),
            'scanned' => array_merge($perm('btc.dms'), $perm('btc.bill_upload')),
            'booked' => $c->route === 'admin_booking' ? $perm('btc.booking') : $owner(),
            'advance_paid', 'returned', 'rejected', 'cancelled', 'closed' => $owner(),
            'bill_with_dms' => $perm('btc.dms'),
            'bill_with_finance', 'bill_submitted' => $perm('btc.bill_upload'),
            'extracted', 'finance_verified' => $perm('btc.verifier'),
            'focus_done' => $perm('btc.fin_approver'),
            'fin_approved' => $perm('btc.treasury'),
            'paid' => array_merge($owner(), $perm('btc.finance')),
            default => [],
        };
    }

    /** Called after every workflow step. */
    public static function handoff(BtcCase $c, string $from, string $action): void
    {
        if (! self::enabled() || $from === $c->status && ! in_array($action, ['pay', 'assign_maker'], true)) {
            return;
        }
        $what = config("btc.statuses.{$c->status}", $c->status);
        $subject = "BTC {$c->case_no}: {$what}";
        $lines = [
            "BTC request {$c->case_no} ({$c->categoryLabel()}, {$c->emp_code} {$c->emp_name}) is now: {$what}.",
            $c->status === 'paid' ? 'Paid Rs '.number_format((float) $c->paid_amount, 2).' (UTR '.$c->utr_number.').' : 'Waiting with: '.$c->pendingWith().'.',
            'Purpose: '.$c->purpose.($c->vendor_name ? ' · Vendor: '.$c->vendor_name : '').((float) ($c->bill_amount ?: $c->quotation_amount ?: $c->estimated_amount) > 0 ? ' · Rs '.number_format((float) ($c->bill_amount ?: $c->quotation_amount ?: $c->estimated_amount), 2) : ''),
            'Open: '.route('btc.index', ['case' => $c->id]),
        ];
        // hand-off mails go to named people (manager, department head, requester, assigned Finance maker); desk queues
        // (booking, DMS, Finance, checker, Treasury) see their lists on screen and get the daily overdue mail instead
        $named = in_array($c->status, ['pending_manager', 'class_approval', 'advance_paid', 'returned', 'rejected', 'cancelled', 'closed'], true)
            || ($c->status === 'booked' && $c->route !== 'admin_booking');
        $to = $named ? self::actors($c) : [];
        if ($c->status === 'paid') {   // the requester hears that the vendor is paid
            $to = self::actors(tap(clone $c, fn ($x) => $x->status = 'closed'));
        }
        foreach ($to as [$email, $name]) {
            self::send($c, 'handoff', $email, $name, $subject, $lines);
        }
    }

    /** Daily: cases past their stage time limit. Returns how many were escalated. */
    public static function escalate(): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $n = 0;
        foreach (BtcCase::whereIn('status', array_keys(self::STAGES))->get() as $c) {
            $since = self::since($c);
            $days = (int) $since->diffInDays(now());
            if ($days <= self::slaDays($c->status)) {
                continue;
            }
            if (DB::table('btc_notifications')->where('btc_case_id', $c->id)->where('kind', 'escalation')->where('stage', $c->status)->whereDate('created_at', today())->exists()) {
                continue;
            }
            $subject = "Overdue: BTC {$c->case_no} waiting {$days} days at '".self::STAGES[$c->status][0]."'";
            $lines = ["BTC request {$c->case_no} ({$c->emp_code} {$c->emp_name}, {$c->categoryLabel()}) has been at '".config("btc.statuses.{$c->status}")."' since ".$since->format('d-m-Y')." ({$days} days; allowed ".self::slaDays($c->status).').',
                'Waiting with: '.$c->pendingWith().'.', 'Open: '.route('btc.index', ['case' => $c->id])];
            $to = self::actors($c);
            // copy: the actor's reporting manager for people steps, the Finance head for desk steps
            $cc = in_array($c->status, ['pending_manager', 'advance_paid', 'booked', 'returned'], true)
                ? collect($to)->flatMap(function ($a) {
                    $u = User::where('email', $a[0])->first();
                    $m = $u && $u->employee_id ? \App\Support\ReportingTree::managerOf((int) $u->employee_id) : null;
                    $mu = $m ? User::where('employee_id', $m)->first() : null;

                    return $mu && $mu->email ? [[$mu->email, $mu->name]] : [];
                })->all()
                : User::permission('btc.fin_approver')->whereNotNull('email')->get(['email', 'name'])->map(fn ($u) => [$u->email, $u->name])->all();
            foreach (collect(array_merge($to, $cc))->unique(fn ($a) => strtolower($a[0])) as [$email, $name]) {
                self::send($c, 'escalation', $email, $name, $subject, $lines);
            }
            \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'escalated', 'from_status' => $c->status, 'to_status' => $c->status,
                'remark' => "Overdue {$days} days (allowed ".self::slaDays($c->status).'): mailed '.collect(array_merge($to, $cc))->pluck(1)->unique()->implode(', '), 'user_id' => null, 'user_name' => 'System']);
            $n++;
        }

        return $n;
    }

    private static function send(BtcCase $c, string $kind, string $email, ?string $name, string $subject, array $lines): void
    {
        $redirect = trim((string) config('btc.notify.redirect_to', ''));
        $to = $redirect !== '' ? $redirect : $email;
        $body = 'Dear '.($name ?: 'colleague').",\n\n".implode("\n", $lines).($redirect !== '' ? "\n\n[Test mode: meant for {$name} <{$email}>]" : '')."\n\nXeasy - Bill To Company";
        $err = null;
        try {
            Mail::raw($body, fn ($m) => $m->to($to, $name)->subject($subject));
        } catch (\Throwable $e) {
            $err = mb_substr($e->getMessage(), 0, 300);
            report($e);
        }
        DB::table('btc_notifications')->insert(['btc_case_id' => $c->id, 'kind' => $kind, 'stage' => $c->status, 'to_email' => $to, 'to_name' => $name,
            'subject' => mb_substr($subject, 0, 250), 'error' => $err, 'created_at' => now()]);
    }
}
