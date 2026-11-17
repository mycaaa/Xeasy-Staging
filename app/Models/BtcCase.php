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
        if ($this->original_required && ! $this->original_received_on) {
            $p[] = 'original bill';
        }
        if ($this->boarding_pass_required && ! $this->boarding_pass_received_on) {
            $p[] = 'boarding pass';
        }

        return $p;
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
            'advance_paid', 'booked' => $this->route === 'admin_booking' && ! $this->activity_completed_on ? 'Requester (activity / bill)' : 'Requester (final bill)',
            'returned' => 'Requester (correction)',
            'bill_submitted' => 'BTC Verifier / DMS',
            'dms_processed' => 'Finance Verifier',
            'finance_verified' => 'Finance Head (checker)',
            'checked' => 'Payment User (send for approval)',
            'with_treasury' => 'Authorised Approver',
            'dept_approved' => 'Payment User (payment)',
            'paid' => $this->pendingDocuments() ? 'Documents: '.implode(', ', $this->pendingDocuments()) : 'Finance (closure)',
            default => '-',
        };
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
            (float) $this->paid_amount > 0 => 'Paid '.number_format((float) $this->paid_amount).' · '.$this->utr_number,
            in_array($this->status, ['checked', 'with_treasury', 'dept_approved'], true) => 'In payment',
            default => '-',
        };

        return ['booking' => $booking, 'advance' => $advance, 'invoice' => $invoice, 'payment' => $payment];
    }
}
