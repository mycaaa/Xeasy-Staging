<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BTC-01: one Bill To Company case (requirement → bill → verification → payment → closure). */
class BtcCase extends Model
{
    protected $table = 'btc_cases';

    protected $guarded = ['id'];

    /** BTC-CANCEL-01: who cancelled (false = not looked up yet). */
    protected string|null|false $cancelledByMemo = false;

    protected $casts = [
        'service_from' => 'date', 'service_to' => 'date', 'bill_date' => 'date', 'travel_date' => 'date', 'return_date' => 'date',
        'advance_paid_on' => 'date', 'original_received_on' => 'date', 'boarding_pass_received_on' => 'date', 'paid_on' => 'date',
        'manager_action_at' => 'datetime', 'bill_submitted_at' => 'datetime', 'dms_at' => 'datetime', 'finance_at' => 'datetime',
        'checker_at' => 'datetime', 'treasury_at' => 'datetime', 'dept_approved_at' => 'datetime', 'closed_at' => 'datetime',
        'focus_exported_at' => 'datetime',
        'pay_instructed_at' => 'datetime', 'focus_ejv_at' => 'datetime',   // BTC-PAY-02 / BTC-FOCUS-01
        'booking_date' => 'date', 'activity_completed_on' => 'date', 'advance_refund_on' => 'date',
        'doc_waived_at' => 'datetime', 'hold_at' => 'datetime', 'on_hold' => 'boolean',
        'dms_received_on' => 'date', 'dms_to_finance_on' => 'date', 'bill_uploaded_at' => 'datetime', 'verify1_at' => 'datetime', 'fin_approved_at' => 'datetime',   // BTC-V2
        'adv_fin_at' => 'datetime', 'adv_instructed_at' => 'datetime',   // BTC-SCAN-01
        'new_vendor' => 'boolean', 'original_required' => 'boolean', 'boarding_pass_required' => 'boolean', 'duplicate_checked' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(BtcCaseFile::class, 'btc_case_id');
    }

    public function logs()
    {
        return $this->hasMany(BtcCaseLog::class, 'btc_case_id')->orderByDesc('id');
    }

    public function statusLabel(): string
    {
        // BTC-CANCEL-01 (25 Nov 2026, testing point 16): a cancelled request says who cancelled it, on every screen
        if ($this->status === 'cancelled' && ($by = $this->cancelledBy())) {
            return 'Cancelled by '.$by;
        }

        return config("btc.statuses.{$this->status}", $this->status);
    }

    /** BTC-UI-01: colour family of the status pill (wait / admin / fin / done / back / stop / draft). */
    public function statusTone(): string
    {
        return $this->on_hold ? 'back' : (string) config('btc.status_tones.'.$this->status, 'stop');
    }

    /** BTC-UI-01: the status as a pill (label in words, colour by family). */
    public function statusPill(): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString('<span class="btc-pill btc-pill--'.e($this->statusTone()).'">'.e($this->statusLabel()).'</span>');
    }

    /** BTC-CANCEL-01: who cancelled the case (the last cancel step in its history). */
    public function cancelledBy(): ?string
    {
        if ($this->status !== 'cancelled' || ! $this->id) {
            return null;
        }
        if ($this->cancelledByMemo === false) {
            $this->cancelledByMemo = BtcCaseLog::where('btc_case_id', $this->id)->where('to_status', 'cancelled')
                ->whereIn('action', ['cancel', 'cancel_booking', 'discard_scan'])->orderByDesc('id')->value('user_name');
        }

        return $this->cancelledByMemo ?: null;
    }

    public function categoryLabel(): string
    {
        return config("btc.categories.{$this->category}.label", $this->category);
    }

    /**
     * BTC-FORMAT-01 (23 Nov 2026, testing point 7): the bill in its own prescribed format - the punch form of its type
     * (Hotel / Lodging, Air / Rail / Bus, Hired vehicle, Meals, Team meeting) with the punched values, else as read.
     * @return array{title: string, form: string, fields: array<string,string>, values: array<string,string>}|null
     */
    public function punchFormat(): ?array
    {
        return \App\Services\Btc\PunchForm::data($this);   // BTC-PUNCH-02: with the tables (legs, rooms, items)
    }

    /** BTC-FORMAT-01: the key fields of the bill's own format, for lists ("Hotel X · in 22-10 · out 23-10 · 2,000"). */
    public function punchSummary(): string
    {
        $p = $this->punchFormat();
        if (! $p) {
            return '';
        }
        $parts = [];
        foreach ((array) config('btc.punch_summary.'.$p['form'], []) as $k) {
            $v = $p['values'][$k] ?? '';
            if ($v !== '') {
                $parts[] = ($p['fields'][$k] ?? $k).': '.$v;
            }
        }

        foreach (array_slice((array) ($p['tables']['legs'] ?? []), 0, 3) as $l) {   // BTC-PUNCH-02: each journey leg
            $parts[] = trim(($l['Mode'] ?? '').' '.($l['Journey From'] ?? '').'-'.($l['Journey Upto'] ?? '').' '.($l['Journey Date'] ?? ''));
        }

        return $parts ? $p['title'].' - '.implode(' · ', $parts) : '';
    }

    /** Things still open after payment (closure is blocked until they are in). */
    public function pendingDocuments(): array
    {
        $p = [];
        $waived = array_filter(explode(',', (string) ($this->doc_waived_kinds ?? '')));   // BTC-CLOSE-01: formally waived
        if ($this->original_required && ! $this->original_received_on && ! in_array('original', $waived, true)) {
            $p[] = 'original bill';
        }
        if ($this->boarding_pass_required && ! $this->boarding_pass_received_on && ! in_array('boarding_pass', $waived, true)) {
            $p[] = 'boarding pass';
        }

        return $p;
    }

    public function payments()
    {
        return $this->hasMany(BtcPayment::class, 'btc_case_id')->orderByDesc('id');
    }

    /**
     * BTC-CLOSE-01 (17 Nov 2026, requirement item 17): what still stops closure. A case is not closed just because
     * it is paid: Finance verification and checking, HOD approval, Focus posting, payment reconciled with the net
     * payable, advance settled, documents received or waived, and no financial hold.
     */
    public function closureGaps(): array
    {
        $g = [];
        // BTC-V2 (19 Nov 2026): Verifier 1, Focus entry and Finance Approval (no checker / department head step any more)
        if (! $this->verify1_at && ! $this->checker_at) {
            $g[] = 'Finance Verifier 1 not done';
        }
        if (! $this->finance_at) {
            $g[] = 'Focus entry not prepared';
        }
        if (! $this->fin_approved_at && ! $this->dept_approved_at) {
            $g[] = 'Finance Approval not done';
        }
        if ((float) $this->net_payable > 0 && ! $this->focus_voucher_no && ! ($this->jv_no ?? null) && ! $this->focus_exported_at) {
            $g[] = 'Focus posting not confirmed (no Focus voucher no. / JV export)';
        }
        if (abs(round((float) $this->paid_amount - (float) $this->net_payable, 2)) > 0.009) {
            $g[] = 'payment not reconciled (paid Rs '.number_format((float) $this->paid_amount, 2).' vs net payable Rs '.number_format((float) $this->net_payable, 2).')';
        }
        $a = $this->advanceFigures();
        if ($a['outstanding'] > 0.009 && $a['balance'] > 0.5) {
            $g[] = 'advance balance Rs '.number_format($a['balance'], 2).' not adjusted or refunded';
        }
        if ($p = $this->pendingDocuments()) {
            $g[] = 'waiting for '.implode(', ', $p).' (receive or waive)';
        }
        if ($this->on_hold ?? false) {
            $g[] = 'financial hold: '.$this->hold_reason;
        }

        return $g;
    }

    /**
     * BTC-REQ-10 (17 Nov 2026): what the case is waiting for, as shown under the status. Before the bill stage the
     * original bill / boarding pass is not yet due: a draft advance request waits for its quotation, an admin booking
     * waiting for the manager shows nothing.
     */
    public function waitingFor(): array
    {
        if (in_array($this->status, ['cancelled', 'rejected', 'closed'], true)) {
            return [];
        }
        if (in_array($this->status, ['draft', 'returned'], true) && $this->route === 'employee_advance' && ! $this->bill_submitted_at) {
            return $this->quotation_amount && $this->files()->where('kind', 'quotation')->exists() ? [] : ['Quotation'];
        }
        if (! $this->bill_submitted_at) {
            return [];
        }

        return $this->pendingDocuments();
    }

    /**
     * BTC-REQ-06 (16 Nov 2026): the advance in separate figures - the amount requested is not the amount paid.
     *   requested  advance_amount            approved  advance_approved (manager; default = requested)
     *   paid       advance_paid_amount (actually paid by Finance; older cases: requested once paid)
     *   tds        tds_on_advance            outstanding = paid + TDS (what the vendor holds against this case)
     *   adjusted   advance_adjusted (set off against the invoice at Finance verification)
     *   refund     advance_refund            balance = outstanding - adjusted - refund
     */
    public function advanceFigures(): array
    {
        $req = (float) $this->advance_amount;
        $appr = $this->advance_approved !== null ? (float) $this->advance_approved : ($this->manager_action_at && $req > 0 ? $req : null);
        $paid = $this->advance_paid_amount !== null ? (float) $this->advance_paid_amount : ($this->advance_paid_on ? $req : 0.0);
        $tds = (float) $this->tds_on_advance;
        $out = $paid > 0 ? round($paid + $tds, 2) : 0.0;
        $adj = (float) $this->advance_adjusted;
        $ref = (float) $this->advance_refund;

        return ['requested' => $req, 'approved' => $appr, 'paid' => $paid, 'tds' => $tds, 'outstanding' => $out,
            'adjusted' => $adj, 'refund' => $ref, 'balance' => round($out - $adj - $ref, 2)];
    }

    /** BTC-REQ-04: who the case is waiting for now. */
    public function pendingWith(): string
    {
        return match ($this->status) {
            'draft' => 'Requester (draft)',
            'pending_manager' => 'Reporting manager',
            'booking_requested' => 'BTC Admin (booking)',
            'class_approval' => 'Reporting manager (higher class)',   // BTC-BUS-01
            // BTC-SCAN-01: Finance Advance Approval (pending -> approved -> instructed), then Finance Advance pays
            'advance_requested' => match ($this->adv_stage ?? null) {
                'instructed' => 'Finance Advance (pay - instructed)', 'approved' => 'Finance (payment instruction)', default => 'Finance Advance Approval',
            },
            'scanned' => 'Classification (bill type)',
            // BTC-V2 (19 Nov 2026): admin bookings - Admin hands the physical bill to DMS, DMS to Finance; advance cases -
            // the employee uploads the bill against his request
            'booked' => $this->route === 'admin_booking' ? 'BTC Admin (physical bill to DMS)' : 'Requester (upload the bill)',
            'advance_paid' => 'Requester (upload the bill)',
            'bill_with_dms' => 'DMS (receive the bill, hand to Finance)',
            'bill_with_finance' => 'Finance (scan and upload the bill)',
            'returned' => 'Requester (correction)',
            'bill_submitted' => 'Punch Screen (check the bill data)',
            'extracted' => 'Finance level verification',
            'finance_verified' => 'Finance Verifier 1 (Focus entry)',
            'focus_done' => 'Finance Approver',
            'fin_approved' => 'Treasury (payment)',
            'paid' => $this->pendingDocuments() ? 'Documents: '.implode(', ', $this->pendingDocuments()) : 'Finance (closure)',
            default => '-',
        };
    }

    /** BTC-MYREQ-01 (testing point 32): one-word stage for the requester's screen (config btc.short_status). */
    public function shortStatus(): string
    {
        return (string) config('btc.short_status.'.$this->status, 'Pending');
    }

    /** BTC-MYREQ-01: one-word department the case is with ('You' when the viewer is the requester); '' when finished. */
    public function pendingDept(bool $viewerIsRequester = false): string
    {
        if (in_array($this->status, ['closed', 'rejected', 'cancelled'], true)) {
            return '';
        }
        $d = $this->status === 'booked'
            ? ($this->route === 'admin_booking' ? 'Admin' : 'Requester')
            : (string) config('btc.pending_dept.'.$this->status, 'Finance');

        return $d === 'Requester' ? ($viewerIsRequester ? 'You' : 'Employee') : $d;
    }

    /**
     * BTC-MYREQ-01: payment in four words - Not Initiated / Processing / Paid / Failed ('' for a cancelled / rejected
     * request with nothing paid). $failed: the latest payment of the case came back.
     */
    public function paymentState(bool $failed = false): string
    {
        $paid = (float) $this->paid_amount;
        if ($failed && ! in_array($this->status, ['paid', 'closed'], true)) {
            return 'Failed';
        }
        if ($paid > 0 && ($this->payment_status === 'full' || in_array($this->status, ['paid', 'closed'], true))) {
            return 'Paid';
        }
        if ($paid > 0 || $this->status === 'fin_approved') {
            return 'Processing';
        }

        return in_array($this->status, ['rejected', 'cancelled'], true) ? '' : 'Not Initiated';
    }

    /** BTC-MYREQ-01: the advance in a few words ("Advance 5,000 paid"), '' when none was asked for. */
    public function advanceShort(): string
    {
        $a = $this->advanceFigures();
        if ($a['requested'] <= 0) {
            return '';
        }
        if ($a['paid'] > 0) {
            return 'Advance '.number_format($a['paid']).' paid';
        }

        return 'Advance '.number_format($a['requested']).(in_array($this->status, ['rejected', 'cancelled'], true) ? ' not paid' : ' requested');
    }

    /** BTC-FLOW-01: true when no active user holds the permission (the case would wait for nobody). */
    public static function nobodyHas(string $perm): bool
    {
        static $memo = [];
        if (! array_key_exists($perm, $memo)) {
            try {
                $memo[$perm] = ! \App\Models\User::permission($perm)->exists();
            } catch (\Throwable $e) {
                $memo[$perm] = false;
            }
        }

        return $memo[$perm];
    }

    /** BTC-REQ-02 (My BTC Requests): booking / advance / invoice / payment status in one line each. */
    public function stageSummary(): array
    {
        $a = $this->advanceFigures();
        $booking = match (true) {
            $this->route !== 'admin_booking' => $this->vendor_name ? 'Own arrangement' : '-',
            $this->booking_status === 'cancelled' => 'Cancelled',
            (bool) $this->booking_ref || in_array($this->booking_status, ['confirmed', 'modified'], true) => trim('Booked '.$this->booking_ref).($this->activity_completed_on ? ' · done' : ''),   // BTC-HOTEL-37: a hotel may have no reference
            in_array($this->status, ['draft', 'pending_manager', 'booking_requested'], true) => 'Waiting',
            default => '-',
        };
        $advance = $a['requested'] <= 0 ? '-' : match (true) {
            // BTC-CANCEL-01: a cancelled / rejected request is not "pending Finance approval" any more
            $this->status === 'cancelled' && $a['paid'] <= 0 => 'Cancelled'.(($by = $this->cancelledBy()) ? ' by '.$by : ''),
            $this->status === 'rejected' && $a['paid'] <= 0 => 'Rejected',
            $a['paid'] > 0 => 'Paid '.number_format($a['paid']).($a['balance'] > 0.5 && $this->status !== 'draft' ? ' · due '.number_format($a['balance']) : ''),
            $this->status === 'advance_requested' => match ($this->adv_stage ?? null) { 'instructed' => 'Instructed, not paid', 'approved' => 'Finance approved', default => 'Pending Finance approval' },
            $this->status === 'pending_manager' => 'Waiting approval',
            default => 'Requested '.number_format($a['requested']),
        };
        $invoice = match (true) {
            (bool) $this->bill_no => 'Bill '.$this->bill_no,
            $this->status === 'bill_with_dms' => 'Physical bill with DMS',
            $this->status === 'bill_with_finance' => 'With Finance to upload',
            $this->files()->where('kind', 'bill')->exists() => 'Uploaded, being read',
            default => 'Not received',
        };
        $payment = match (true) {
            ($this->on_hold ?? false) => 'On hold',
            (float) $this->paid_amount > 0 && $this->payment_status === 'partial' => 'Part paid '.number_format((float) $this->paid_amount).' of '.number_format((float) $this->net_payable),
            (float) $this->paid_amount > 0 => 'Paid '.number_format((float) $this->paid_amount).' · '.$this->utr_number,
            $this->status === 'fin_approved' => 'With Treasury',
            $this->status === 'focus_done' => 'Finance Approval',
            default => '-',
        };

        return ['booking' => $booking, 'advance' => $advance, 'invoice' => $invoice, 'payment' => $payment];
    }
}
