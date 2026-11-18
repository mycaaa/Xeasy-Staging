<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BTC-01: one Bill To Company case (requirement → bill → verification → payment → closure). */
class BtcCase extends Model
{
    protected $table = 'btc_cases';

    protected $guarded = ['id'];

    protected $casts = [
        'service_from' => 'date', 'service_to' => 'date', 'bill_date' => 'date', 'travel_date' => 'date',
        'advance_paid_on' => 'date', 'original_received_on' => 'date', 'boarding_pass_received_on' => 'date', 'paid_on' => 'date',
        'manager_action_at' => 'datetime', 'bill_submitted_at' => 'datetime', 'dms_at' => 'datetime', 'finance_at' => 'datetime',
        'checker_at' => 'datetime', 'treasury_at' => 'datetime', 'dept_approved_at' => 'datetime', 'closed_at' => 'datetime',
        'focus_exported_at' => 'datetime',
        'booking_date' => 'date', 'activity_completed_on' => 'date', 'advance_refund_on' => 'date',
        'doc_waived_at' => 'datetime', 'hold_at' => 'datetime', 'on_hold' => 'boolean',
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
        return config("btc.statuses.{$this->status}", $this->status);
    }

    public function categoryLabel(): string
    {
        return config("btc.categories.{$this->category}.label", $this->category);
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
        if (! $this->finance_at) {
            $g[] = 'Finance verification not done';
        }
        if (! $this->checker_at) {
            $g[] = 'Finance checker step not done';
        }
        if (! $this->dept_approved_at) {
            $g[] = 'department head approval not done';
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
            'advance_requested' => 'Finance Advance',
            // BTC-FLOW-01 (18 Nov 2026): in an admin booking the vendor bills the company, so the bill is with the BTC admin
            'advance_paid', 'booked' => $this->route === 'admin_booking' ? 'BTC Admin (vendor bill to DMS)' : 'Requester / DMS (final bill)',
            'returned' => 'Requester (correction)',
            'bill_submitted' => 'BTC Verifier / DMS',
            'dms_processed' => 'Finance Verifier'.($this->assigned_maker_id ? ' ('.(\App\Models\User::whereKey($this->assigned_maker_id)->value('name') ?? 'assigned').')' : ''),
            'finance_verified' => 'Finance Head (checker)',
            'checked' => 'Payment User (send for approval)',
            'with_treasury' => 'Authorised Approver',
            'dept_approved' => \App\Services\Btc\BtcWorkflow::pendingPayment($this)
                ? 'Treasury Checker (authorise payment)'.(self::nobodyHas('btc.treasury_checker') ? ' - NONE ASSIGNED' : '')
                : 'Payment User (payment)',
            'paid' => $this->pendingDocuments() ? 'Documents: '.implode(', ', $this->pendingDocuments()) : 'Finance (closure)',
            default => '-',
        };
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
            (bool) $this->booking_ref => 'Booked '.$this->booking_ref.($this->activity_completed_on ? ' · done' : ''),
            in_array($this->status, ['draft', 'pending_manager', 'booking_requested'], true) => 'Waiting',
            default => '-',
        };
        $advance = $a['requested'] <= 0 ? '-' : match (true) {
            $a['paid'] > 0 => 'Paid '.number_format($a['paid']).($a['balance'] > 0.5 && $this->status !== 'draft' ? ' · due '.number_format($a['balance']) : ''),
            $this->status === 'advance_requested' => 'Approved, not paid',
            $this->status === 'pending_manager' => 'Waiting approval',
            default => 'Requested '.number_format($a['requested']),
        };
        $invoice = match (true) {
            (bool) $this->bill_no => 'Bill '.$this->bill_no.($this->nova_document_id ? ' (Nova)' : ''),
            default => 'Not received',
        };
        $payment = match (true) {
            ($this->on_hold ?? false) => 'On hold',
            (float) $this->paid_amount > 0 && $this->payment_status === 'partial' => 'Part paid '.number_format((float) $this->paid_amount).' of '.number_format((float) $this->net_payable),
            (float) $this->paid_amount > 0 => 'Paid '.number_format((float) $this->paid_amount).' · '.$this->utr_number,
            $this->status === 'dept_approved' && \App\Services\Btc\BtcWorkflow::pendingPayment($this) => 'Entered, to authorise',   // BTC-TRY-01
            in_array($this->status, ['checked', 'with_treasury', 'dept_approved'], true) => 'In payment',
            default => '-',
        };

        return ['booking' => $booking, 'advance' => $advance, 'invoice' => $invoice, 'payment' => $payment];
    }
}
