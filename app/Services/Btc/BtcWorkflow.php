<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use App\Models\BtcCaseLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BTC-01: stage rules of the Bill To Company process (config/btc.php).
 *
 *  A admin_booking:    draft → booked → bill_submitted → dms_processed → finance_verified → checked
 *                      → with_treasury → dept_approved → paid → closed
 *  B employee_advance: draft → pending_manager → (advance_requested → advance_paid →) booked → bill_submitted → …
 *  C direct_bill:      draft → bill_submitted → …
 *  Any working stage can be returned for correction to an earlier stage; a returned case goes back there.
 *
 * Controls built in (from the note):
 *  - air travel: the expense is not verified until the boarding pass / proof of travel is in;
 *  - hired vehicle: needs the original bill before verification; hotel may go on a scan, original followed up;
 *  - Finance checks for a duplicate bill (same vendor + bill no, or same vendor + amount + date) and records it;
 *  - TDS: net of TDS already deducted on the advance; the advance already paid is set off in the net payable;
 *  - maker-checker: the checker cannot be the person who verified;
 *  - Treasury gets the department head's approval before paying; payment needs a UTR and cannot exceed the net payable;
 *  - closure only when paid and every pending original / boarding pass is in.
 */
class BtcWorkflow
{
    /** action => [allowed from statuses, permission (null = case owner / named approver), to status] */
    public const ACTIONS = [
        'submit' => [['draft'], null, null],
        'manager_approve' => [['pending_manager'], 'manager', null],
        'manager_reject' => [['pending_manager'], 'manager', 'rejected'],
        // BTC-REQ-05 (16 Nov 2026): admin booking queue - the BTC admin confirms, changes or cancels the booking
        'confirm_booking' => [['booking_requested'], 'btc.booking', 'booked'],
        'modify_booking' => [['booked'], 'btc.booking', null],
        'cancel_booking' => [['booking_requested', 'booked'], 'btc.booking', 'cancelled'],
        // BTC-REQ-19 (17 Nov 2026): the BTC admin changes the vendor before / after booking (reason logged)
        'change_vendor' => [['booking_requested', 'booked'], 'btc.booking', null],
        // BTC-REQ-21: the BTC admin forwards the vendor's bill to DMS (date); DMS records the bill details
        'forward_to_dms' => [['booked'], 'btc.booking', 'bill_submitted'],
        'complete_activity' => [['booked'], 'owner|btc.booking', null],
        // BTC-REQ-06: Finance Advance pays the advance; a refund from the vendor is recorded separately
        'advance_paid' => [['advance_requested'], 'btc.finance|btc.advance', 'advance_paid'],
        'record_refund' => [['advance_paid', 'booked', 'bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid', 'cancelled'], 'btc.finance|btc.advance', null],
        'booked' => [['advance_paid'], null, 'booked'],
        'submit_bill' => [['booked', 'advance_paid', 'returned'], null, 'bill_submitted'],
        'dms_process' => [['bill_submitted'], 'btc.dms|btc.verifier', 'dms_processed'],
        'finance_verify' => [['dms_processed'], 'btc.finance', 'finance_verified'],
        'check' => [['finance_verified'], 'btc.checker', 'checked'],
        'send_dept_approval' => [['checked'], 'btc.treasury', 'with_treasury'],
        'dept_approve' => [['with_treasury'], 'dept_approver', 'dept_approved'],
        'pay' => [['dept_approved'], 'btc.treasury', 'paid'],
        // BTC-PAY-01 (17 Nov 2026): a failed / returned payment is reversed with its reason (back to payment-ready)
        'payment_failed' => [['dept_approved', 'paid'], 'btc.treasury', 'dept_approved'],
        // BTC-TRY-01 (17 Nov 2026): Treasury maker enters, Treasury checker (another person) authorises or rejects
        'authorise_payment' => [['dept_approved'], 'btc.treasury_checker', null],
        'reject_payment_entry' => [['dept_approved'], 'btc.treasury_checker', null],
        // BTC-FIN-02: the Finance head assigns the case to a named Finance maker
        'assign_maker' => [['bill_submitted', 'dms_processed', 'returned'], 'btc.checker', null],
        'close' => [['paid'], 'btc.finance', 'closed'],
        // BTC-CLOSE-01: documents formally waived by an authorised person; a financial hold blocks payment and closure
        'waive_document' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid'], 'btc.checker|btc.admin', null],
        'hold' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid'], 'btc.finance|btc.checker|btc.admin', null],
        'release_hold' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid'], 'btc.checker|btc.admin', null],
        // BTC-HOD-01: the department head may return (to Finance / DMS / requester) or reject, not only approve
        'dept_return' => [['with_treasury'], 'dept_approver', 'returned'],
        'dept_reject' => [['with_treasury'], 'dept_approver', 'rejected'],
        'return' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury'], 'any_desk', 'returned'],
        'receive_document' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid'], 'btc.dms|btc.finance|btc.verifier', null],
        'cancel' => [['draft', 'pending_manager', 'booking_requested', 'advance_requested', 'booked', 'returned'], null, 'cancelled'],
    ];

    public static function next(string $route, string $after): string
    {
        // after submit / manager approval
        if ($after === 'submit') {
            // BTC-REQ-03: an admin booking now goes to the admin booking queue (was "booked" at once, with no booking details)
            return ['admin_booking' => 'booking_requested', 'employee_advance' => 'pending_manager', 'direct_bill' => 'bill_submitted'][$route] ?? 'booking_requested';
        }

        return $after;
    }

    /** Which actions this user may take on this case now. */
    public static function allowed(BtcCase $c, $user): array
    {
        $out = [];
        foreach (self::ACTIONS as $action => [$from, $perm]) {
            if (in_array($c->status, $from, true) && self::may($c, $user, $perm, $action) && self::fits($c, $action)) {
                if ($action === 'authorise_payment' && (int) (self::pendingPayment($c)->created_by ?? 0) === (int) ($user->id ?? 0)) {
                    continue;   // BTC-TRY-01: not your own entry
                }
                $out[] = $action;
            }
        }

        return $out;
    }

    /** BTC-PAY-01 / BTC-CLOSE-01: actions that depend on the case's state beyond its status. */
    private static function fits(BtcCase $c, string $action): bool
    {
        $held = (bool) ($c->on_hold ?? false);

        return match ($action) {
            'hold' => ! $held,
            'release_hold' => $held,
            'pay' => ! $held && self::pendingPayment($c) === null,
            'close', 'dept_approve', 'send_dept_approval', 'check' => ! $held,
            'payment_failed' => (float) $c->paid_amount > 0,
            'authorise_payment', 'reject_payment_entry' => ! $held && self::pendingPayment($c) !== null,
            'waive_document' => (bool) $c->pendingDocuments(),
            default => true,
        };
    }

    /** BTC-TRY-01: a payment entered by the Treasury maker and not yet authorised. */
    public static function pendingPayment(BtcCase $c): ?object
    {
        try {
            return DB::table('btc_payments')->where('btc_case_id', $c->id)->where('status', 'pending')->orderByDesc('id')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** BTC-TRY-01: apply a payment (amount / mode / UTR) to the case; returns the new status. */
    private function applyPayment(BtcCase $c, object|array $p): string
    {
        $p = (object) $p;
        $c->paid_amount = round((float) $c->paid_amount + (float) $p->amount, 2);
        $c->paid_on = $p->paid_on;
        $c->utr_number = $p->utr_number;
        $c->payment_mode = $p->mode;
        $c->payment_bank_ref = $p->bank_ref;
        $full = $c->paid_amount >= round((float) $c->net_payable, 2) - 0.009;
        $c->payment_status = $full ? 'full' : 'partial';

        return $full ? 'paid' : 'dept_approved';
    }

    public static function may(BtcCase $c, $user, ?string $perm, string $action): bool
    {
        if (! $user) {
            return false;
        }
        $empId = (int) ($user->employee_id ?? 0);
        $can = fn ($p) => method_exists($user, 'can') && $user->can($p);
        if ($can('btc.admin')) {
            return true;
        }
        if ($perm === null) {
            $own = $empId > 0 && ($empId === (int) $c->employee_id || (int) $user->id === (int) $c->created_by);
            // a returned case can be corrected by the owner only if it was returned to the bill stage
            if ($action === 'submit_bill' && $c->status === 'returned') {
                return $own && in_array($c->return_to, [null, 'bill_submitted', 'booked'], true);
            }

            return $own;
        }
        if ($perm === 'manager') {
            return $empId > 0 && $empId === (int) $c->manager_id;
        }
        if ($perm === 'dept_approver') {
            return $empId > 0 && $empId === (int) $c->dept_approver_id;
        }
        if ($perm === 'any_desk') {
            return $can('btc.dms') || $can('btc.verifier') || $can('btc.finance') || $can('btc.checker') || $can('btc.treasury');
        }
        foreach (explode('|', $perm) as $p) {
            if ($p === 'owner' ? ($empId > 0 && ($empId === (int) $c->employee_id || (int) $user->id === (int) $c->created_by)) : $can($p)) {
                return true;
            }
        }

        return false;
    }

    public function act(BtcCase $c, string $action, array $d, $user): BtcCase
    {
        if (! isset(self::ACTIONS[$action])) {
            throw new RuntimeException('Unknown action.');
        }
        [$from, $perm, $to] = self::ACTIONS[$action];
        if (! in_array($c->status, $from, true)) {
            throw new RuntimeException('This step is not possible while the case is "'.$c->statusLabel().'".');
        }
        if (! self::may($c, $user, $perm, $action)) {
            throw new RuntimeException('You are not allowed to do this step on this case.');
        }
        if (in_array($action, ['pay', 'close', 'dept_approve', 'send_dept_approval', 'check'], true) && ($c->on_hold ?? false)) {
            throw new RuntimeException('The case is on financial hold ('.$c->hold_reason.'). Release the hold first.');
        }
        $old = $c->status;
        $payRow = null;      // BTC-PAY-01: payment row written with the status change
        $failRow = null;
        $remark = trim((string) ($d['remark'] ?? ''));
        $now = now();

        switch ($action) {
            case 'submit':
                $to = self::next($c->route, 'submit');
                // BTC-REQ-03: an employee's own admin-booking request is approved by the reporting manager first;
                // one raised by the BTC admin goes straight to the booking queue
                if ($c->route === 'admin_booking' && ! $user->can('btc.admin') && ! $user->can('btc.booking') && $c->manager_id) {
                    $to = 'pending_manager';
                }
                if ($c->route === 'admin_booking') {
                    $c->booking_status = 'requested';
                }
                if ($c->route === 'employee_advance') {
                    // BTC-REQ-13: quotation / advance amounts entered with the submit
                    foreach (['quotation_amount', 'advance_amount'] as $k) {
                        if (isset($d[$k]) && $d[$k] !== '') {
                            $c->{$k} = round((float) $d[$k], 2);
                        }
                    }
                    if ((float) $c->advance_amount > (float) $c->quotation_amount && (float) $c->quotation_amount > 0) {
                        throw new RuntimeException('Advance required cannot be more than the quotation amount.');
                    }
                    if (! $c->manager_id) {
                        throw new RuntimeException('No reporting manager found for the approval.');
                    }
                    // BTC-ADV-02: no new advance while an old one is unsettled beyond the settlement days
                    if ((float) $c->advance_amount > 0 && ! $user->can('btc.admin') && ($old = self::overdueAdvances((int) $c->employee_id, (int) $c->id))->isNotEmpty()) {
                        throw new RuntimeException('An earlier advance is not settled: '.$old->pluck('case_no')->implode(', ').' (paid over '.config('btc.advance_settle_days', 30).' days ago, no bill yet). Submit that bill first, or ask the BTC admin.');
                    }
                    if (! $c->files()->where('kind', 'quotation')->exists() || ! $c->quotation_amount) {
                        throw new RuntimeException('Attach the vendor quotation and its amount first.');
                    }
                }
                if ($c->route === 'direct_bill') {
                    $this->requireBill($c);
                    $c->bill_submitted_at = $now;
                    $c->bill_submitted_by = $user->id;
                }
                break;
            case 'manager_approve':
                $this->withinLimit($user, (float) ($c->quotation_amount ?: $c->estimated_amount ?: $c->advance_amount), 'approve this request');
                if ($c->route === 'admin_booking') {
                    $to = 'booking_requested';
                } else {
                    // BTC-REQ-06: the manager may approve less advance than requested
                    $req = (float) $c->advance_amount;
                    $appr = isset($d['advance_approved']) && $d['advance_approved'] !== '' ? round((float) $d['advance_approved'], 2) : $req;
                    if ($appr < 0 || $appr > $req + 0.009) {
                        throw new RuntimeException('Approved advance must be between 0 and the requested '.number_format($req, 2).'.');
                    }
                    $c->advance_approved = $req > 0 ? $appr : null;
                    $to = $appr > 0 ? 'advance_requested' : 'booked';
                }
                $c->manager_action_at = $now;
                $c->manager_remark = $remark ?: 'Approved';
                break;
            case 'confirm_booking':
            case 'modify_booking':
                $ref = trim((string) ($d['booking_ref'] ?? ''));
                $amt = round((float) ($d['booking_amount'] ?? 0), 2);
                if ($ref === '' || $amt <= 0) {
                    throw new RuntimeException('Enter the booking reference and the booking amount.');
                }
                if (! empty($d['vendor_id'])) {
                    $v = DB::table('vendor_list')->where('id', (int) $d['vendor_id'])->first(['id', 'vendor_name', 'gst_no']);
                    if ($v) {
                        $c->vendor_id = $v->id;
                        $c->vendor_name = $v->vendor_name;
                        $c->vendor_gstin = $v->gst_no;
                    }
                } elseif (! empty($d['vendor_name'])) {
                    $c->vendor_name = mb_substr(trim((string) $d['vendor_name']), 0, 200);
                }
                if (! $c->vendor_name) {
                    throw new RuntimeException('Choose the vendor / hotel booked.');
                }
                if ($action === 'modify_booking' && $remark === '') {
                    throw new RuntimeException('Give the reason for the change.');
                }
                $c->booking_ref = mb_substr($ref, 0, 80);
                $c->booking_amount = $amt;
                $c->booking_date = $d['booking_date'] ?? $now->toDateString();
                $c->booking_status = $action === 'confirm_booking' ? 'confirmed' : 'modified';
                $c->booking_by = $user->id;
                $c->booking_remark = $remark ?: null;
                foreach (['service_from', 'service_to', 'pnr', 'travel_sector', 'travel_date'] as $k) {
                    if (! empty($d[$k])) {
                        $c->$k = $d[$k];
                    }
                }
                $remark = trim(($action === 'confirm_booking' ? 'Booked' : 'Booking changed').": {$c->vendor_name}, ref {$c->booking_ref}, Rs ".number_format($amt, 2).($remark ? " - {$remark}" : ''));
                $to = $action === 'confirm_booking' ? 'booked' : $old;
                break;
            case 'cancel_booking':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for cancelling the booking.');
                }
                $c->booking_status = 'cancelled';
                break;
            case 'change_vendor':
                $oldV = $c->vendor_name;
                if (! empty($d['vendor_id']) && ($v = DB::table('vendor_list')->where('id', (int) $d['vendor_id'])->first(['id', 'vendor_name', 'gst_no']))) {
                    $c->vendor_id = $v->id;
                    $c->vendor_name = $v->vendor_name;
                    $c->vendor_gstin = $v->gst_no;
                    $c->new_vendor = false;
                } elseif (trim((string) ($d['vendor_name'] ?? '')) !== '') {
                    $c->vendor_id = null;
                    $c->vendor_name = mb_substr(trim((string) $d['vendor_name']), 0, 200);
                    $c->vendor_gstin = null;
                    $c->new_vendor = true;
                } else {
                    throw new RuntimeException('Choose the new vendor or type its name.');
                }
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for changing the vendor.');
                }
                $remark = 'Vendor changed from '.($oldV ?: '-').' to '.$c->vendor_name.' - '.$remark;
                $to = $old;
                break;
            case 'forward_to_dms':
                $on = $d['bill_forwarded_dms_on'] ?? null;
                if (! $on || ! strtotime($on) || $on > $now->toDateString()) {
                    throw new RuntimeException('Enter the date the bill was forwarded to DMS (not a future date).');
                }
                $c->bill_forwarded_dms_on = $on;
                foreach (['bill_no', 'bill_date', 'bill_amount'] as $k) {
                    if (! empty($d[$k])) {
                        $c->{$k} = $d[$k];
                    }
                }
                $c->bill_submitted_at = $now;
                $c->bill_submitted_by = $user->id;
                $c->submitted_via = 'admin_forwarded';
                $remark = trim('Bill forwarded to DMS on '.\Carbon\Carbon::parse($on)->format('d-m-Y').($c->bill_no ? ' (bill '.$c->bill_no.')' : '').($remark ? ' - '.$remark : ''));
                break;
            case 'complete_activity':
                $c->activity_completed_on = $d['activity_completed_on'] ?? $now->toDateString();
                $to = $old;
                break;
            case 'record_refund':
                $amt = round((float) ($d['advance_refund'] ?? 0), 2);
                $f = $c->advanceFigures();
                if ($amt <= 0) {
                    throw new RuntimeException('Enter the refund amount received.');
                }
                if ($amt > round($f['balance'], 2) + 0.009) {
                    throw new RuntimeException('Refund '.number_format($amt, 2).' is more than the advance still outstanding '.number_format($f['balance'], 2).'.');
                }
                $c->advance_refund = round((float) $c->advance_refund + $amt, 2);
                $c->advance_refund_on = $d['advance_refund_on'] ?? $now->toDateString();
                $remark = trim('Refund Rs '.number_format($amt, 2).' received'.($remark ? " - {$remark}" : ''));
                $to = $old;
                break;
            case 'manager_reject':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for rejecting.');
                }
                $c->manager_action_at = $now;
                $c->manager_remark = $remark;
                break;
            case 'advance_paid':
                // BTC-REQ-06: the amount actually paid, not the amount requested
                $limit = $c->advance_approved !== null ? (float) $c->advance_approved : (float) $c->advance_amount;
                $paid = isset($d['advance_paid_amount']) && $d['advance_paid_amount'] !== '' ? round((float) $d['advance_paid_amount'], 2) : $limit;
                if ($paid <= 0 || $paid > $limit + 0.009) {
                    throw new RuntimeException('Advance paid must be more than 0 and not more than the approved '.number_format($limit, 2).'.');
                }
                $c->advance_paid_amount = $paid;
                $c->advance_payment_ref = isset($d['advance_payment_ref']) ? mb_substr(trim((string) $d['advance_payment_ref']), 0, 60) : $c->advance_payment_ref;
                $c->advance_paid_on = $d['advance_paid_on'] ?? $now->toDateString();
                $c->advance_activity_id = $d['advance_activity_id'] ?? $c->advance_activity_id;
                $c->tds_on_advance = (float) ($d['tds_on_advance'] ?? 0);
                break;
            case 'submit_bill':
                foreach (['bill_no', 'bill_date', 'taxable_amount', 'gst_amount', 'bill_amount', 'pnr', 'travel_sector', 'travel_date'] as $k) {
                    if (array_key_exists($k, $d) && $d[$k] !== null && $d[$k] !== '') {
                        $c->$k = $d[$k];
                    }
                }
                $this->requireBill($c);
                $c->bill_submitted_at = $now;
                $c->bill_submitted_by = $user->id;
                $c->submitted_via = $d['submitted_via'] ?? ($user->can('btc.admin') ? 'admin' : 'employee');
                $c->return_to = null;
                break;
            case 'dms_process':
                foreach (['bill_no', 'bill_date', 'taxable_amount', 'gst_amount', 'bill_amount'] as $k) {   // BTC-REQ-21
                    if (array_key_exists($k, $d) && $d[$k] !== null && $d[$k] !== '') {
                        $c->{$k} = $d[$k];
                    }
                }
                if (! $c->bill_no || (float) $c->bill_amount <= 0) {
                    throw new RuntimeException('Enter the bill no., date and amount (the bill was forwarded without them).');
                }
                $c->dms_by = $user->id;
                $c->dms_at = $now;
                $c->dms_doc_no = $d['dms_doc_no'] ?? $c->dms_doc_no;
                if (! empty($d['original_received'])) {
                    $c->original_received_on = $now->toDateString();
                }
                break;
            case 'finance_verify':
                $this->financeVerify($c, $d, $user);
                break;
            case 'check':
                if ((int) $c->finance_by === (int) $user->id) {
                    throw new RuntimeException('The checker must be a different person from the one who verified.');
                }
                $c->checker_by = $user->id;
                $c->checker_at = $now;
                $c->focus_voucher_no = $d['focus_voucher_no'] ?? $c->focus_voucher_no;
                break;
            case 'send_dept_approval':
                $approver = (int) ($d['dept_approver_id'] ?? 0);
                if ($approver <= 0) {
                    throw new RuntimeException('Choose the department head who approves this payment.');
                }
                $c->dept_approver_id = $approver;
                $c->treasury_by = $user->id;
                $c->treasury_at = $now;
                break;
            case 'dept_approve':
                $this->withinLimit($user, (float) $c->net_payable, 'approve this payment');
                $c->dept_approved_at = $now;
                $c->dept_remark = $remark ?: 'Approved';
                break;
            case 'pay':
                // BTC-PAY-01 (17 Nov 2026): mode, UTR and bank reference; part payments add up to the net payable
                $amt = round((float) ($d['paid_amount'] ?? 0), 2);
                $utr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['utr_number'] ?? '')));
                $mode = (string) ($d['payment_mode'] ?? '');
                $already = round((float) $c->paid_amount, 2);
                $due = round((float) $c->net_payable - $already, 2);
                if ($amt <= 0) {
                    throw new RuntimeException('Enter the amount paid.');
                }
                if ($amt > $due + 0.009) {
                    throw new RuntimeException('Paid amount '.number_format($amt, 2).' is more than the amount still due '.number_format($due, 2).' (net payable '.number_format((float) $c->net_payable, 2).($already > 0 ? ', already paid '.number_format($already, 2) : '').').');
                }
                if (! in_array($mode, array_keys(config('btc.payment_modes')), true)) {
                    throw new RuntimeException('Choose the payment mode.');
                }
                if (strlen($utr) < 6) {
                    throw new RuntimeException($mode === 'cheque' ? 'Enter the cheque number.' : 'Enter the UTR of the payment.');
                }
                if ($dupe = DB::table('btc_payments')->where('utr_number', $utr)->whereIn('status', ['success', 'pending'])->where('btc_case_id', '!=', $c->id)->value('btc_case_id')) {
                    throw new RuntimeException('UTR '.$utr.' is already used on '.BtcCase::whereKey($dupe)->value('case_no').'.');
                }
                $paidOn = $d['paid_on'] ?? $now->toDateString();
                $bankRef = trim((string) ($d['payment_bank_ref'] ?? '')) ?: null;
                $payRow = ['btc_case_id' => $c->id, 'amount' => $amt, 'paid_on' => $paidOn, 'mode' => $mode, 'utr_number' => $utr, 'bank_ref' => $bankRef,
                    'status' => 'success', 'created_by' => $user->id ?? null, 'created_at' => $now, 'updated_at' => $now];
                if (config('btc.treasury_checker', true)) {
                    // BTC-TRY-01: entered by the maker, applied when a Treasury checker (another person) authorises it
                    $payRow['status'] = 'pending';
                    $to = $old;
                    $remark = trim('Payment entered Rs '.number_format($amt, 2).' '.strtoupper($mode).' UTR '.$utr.', waiting for Treasury checker'.($remark ? ' - '.$remark : ''));
                } else {
                    $to = $this->applyPayment($c, $payRow);
                    $payRow['authorised_by'] = $user->id ?? null;
                    $payRow['authorised_at'] = $now;
                    $remark = trim(($to === 'paid' ? 'Paid in full' : 'Part payment').' Rs '.number_format($amt, 2).' '.strtoupper($mode).' UTR '.$utr.($remark ? ' - '.$remark : ''));
                }
                break;
            case 'authorise_payment':
            case 'reject_payment_entry':
                $p = self::pendingPayment($c);
                if (! $p) {
                    throw new RuntimeException('No payment entry is waiting for authorisation.');
                }
                if ((int) $p->created_by === (int) ($user->id ?? 0)) {
                    throw new RuntimeException('The payment was entered by you; another Treasury checker must authorise it.');
                }
                if ($action === 'reject_payment_entry') {
                    if ($remark === '') {
                        throw new RuntimeException('Give the reason for rejecting the payment entry.');
                    }
                    $failRow = [$p->id, ['status' => 'rejected', 'failed_reason' => mb_substr($remark, 0, 300), 'failed_by' => $user->id ?? null, 'failed_at' => $now, 'updated_at' => $now]];
                    $remark = 'Payment entry rejected (Rs '.number_format((float) $p->amount, 2).' UTR '.$p->utr_number.'): '.$remark;
                    $to = $old;
                    break;
                }
                if (! $p->utr_number) {
                    throw new RuntimeException('No UTR yet for this payment: import the bank\'s response file first (Payment batches).');
                }
                if ((float) $p->amount > round((float) $c->net_payable - (float) $c->paid_amount, 2) + 0.009) {
                    throw new RuntimeException('The entry is more than the amount still due; reject it and enter again.');
                }
                $to = $this->applyPayment($c, $p);
                $failRow = [$p->id, ['status' => 'success', 'authorised_by' => $user->id ?? null, 'authorised_at' => $now, 'updated_at' => $now]];
                $remark = trim(($to === 'paid' ? 'Payment authorised, paid in full' : 'Part payment authorised').' Rs '.number_format((float) $p->amount, 2).' UTR '.$p->utr_number.($remark ? ' - '.$remark : ''));
                break;
            case 'assign_maker':
                $m = (int) ($d['assigned_maker_id'] ?? 0);
                $mu = $m ? \App\Models\User::find($m) : null;
                if (! $mu || ! ($mu->can('btc.finance') || $mu->can('btc.admin'))) {
                    throw new RuntimeException('Choose a Finance maker (a user with Finance verification access).');
                }
                $c->assigned_maker_id = $m;
                $remark = 'Assigned to '.$mu->name.($remark ? ' - '.$remark : '');
                $to = $old;
                break;
            case 'payment_failed':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason the payment failed / came back.');
                }
                $p = DB::table('btc_payments')->where('btc_case_id', $c->id)->where('status', 'success')
                    ->when(! empty($d['payment_id']), fn ($q) => $q->where('id', (int) $d['payment_id']))->orderByDesc('id')->first();
                if (! $p) {
                    throw new RuntimeException('No payment found to reverse.');
                }
                $c->paid_amount = max(0, round((float) $c->paid_amount - (float) $p->amount, 2));
                $last = DB::table('btc_payments')->where('btc_case_id', $c->id)->where('status', 'success')->where('id', '!=', $p->id)->orderByDesc('id')->first();
                $c->utr_number = $last->utr_number ?? null;
                $c->paid_on = $last->paid_on ?? null;
                $c->payment_mode = $last->mode ?? null;
                $c->payment_bank_ref = $last->bank_ref ?? null;
                $c->payment_status = $c->paid_amount > 0 ? 'partial' : null;
                $remark = 'Payment failed: Rs '.number_format((float) $p->amount, 2).' UTR '.$p->utr_number.' - '.$remark;
                $failRow = [$p->id, ['status' => 'failed', 'failed_reason' => mb_substr((string) $d['remark'], 0, 300), 'failed_by' => $user->id ?? null, 'failed_at' => $now, 'updated_at' => $now]];
                break;
            case 'waive_document':
                $kinds = array_values(array_intersect((array) ($d['waive'] ?? []), ['original', 'boarding_pass']));
                if (! $kinds) {
                    throw new RuntimeException('Choose the document(s) to waive.');
                }
                if (mb_strlen($remark) < 10) {
                    throw new RuntimeException('Give the reason for the waiver (at least 10 characters).');
                }
                $c->doc_waived_kinds = implode(',', array_unique(array_merge(array_filter(explode(',', (string) $c->doc_waived_kinds)), $kinds)));
                $c->doc_waived_by = $user->id ?? null;
                $c->doc_waived_at = $now;
                $c->doc_waiver_reason = mb_substr($remark, 0, 300);
                $remark = 'Waived: '.str_replace(['original', 'boarding_pass'], ['original bill', 'boarding pass'], implode(', ', $kinds)).' - '.$remark;
                $to = $old;
                break;
            case 'hold':
                if (mb_strlen($remark) < 5) {
                    throw new RuntimeException('Give the reason for the hold.');
                }
                $c->on_hold = true;
                $c->hold_reason = mb_substr($remark, 0, 300);
                $c->hold_by = $user->id ?? null;
                $c->hold_at = $now;
                $remark = 'Financial hold: '.$remark;
                $to = $old;
                break;
            case 'release_hold':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for releasing the hold.');
                }
                $c->on_hold = false;
                $remark = 'Hold released ('.$c->hold_reason.'): '.$remark;
                $to = $old;
                break;
            case 'dept_reject':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for rejecting.');
                }
                $c->dept_remark = $remark;
                break;
            case 'close':
                // BTC-CLOSE-01 (17 Nov 2026): closed only when every applicable condition is met, not just because it is paid
                if ($open = $c->closureGaps()) {
                    throw new RuntimeException('Cannot close yet: '.implode('; ', $open).'.');
                }
                $c->closed_at = $now;
                break;
            case 'dept_return':
                $d['return_to'] = $d['return_to'] ?? 'finance_verified';
                // no break: same as a desk return
            case 'return':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for returning.');
                }
                $back = (string) ($d['return_to'] ?? 'bill_submitted');
                $order = array_keys(config('btc.statuses'));
                if (! in_array($back, ['bill_submitted', 'dms_processed', 'finance_verified'], true) || array_search($back, $order, true) >= array_search($old, $order, true)) {
                    $back = 'bill_submitted';
                }
                $c->return_to = $back;
                // returned to DMS / Finance: that desk picks it up again directly
                $to = $back === 'bill_submitted' ? 'returned' : ($back === 'dms_processed' ? 'bill_submitted' : 'dms_processed');
                break;
            case 'receive_document':
                $kind = (string) ($d['document'] ?? '');
                if ($kind === 'original') {
                    $c->original_received_on = $d['received_on'] ?? $now->toDateString();
                } elseif ($kind === 'boarding_pass') {
                    $c->boarding_pass_received_on = $d['received_on'] ?? $now->toDateString();
                } else {
                    throw new RuntimeException('Choose the document received.');
                }
                $to = $old;
                break;
            case 'cancel':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for cancelling.');
                }
                break;
        }

        DB::transaction(function () use ($c, $to, $old, $action, $remark, $user, $payRow, $failRow) {
            $c->status = $to ?? $old;
            $c->save();
            if ($payRow) {
                DB::table('btc_payments')->insert($payRow);
            }
            if ($failRow) {
                DB::table('btc_payments')->where('id', $failRow[0])->update($failRow[1]);
            }
            BtcCaseLog::create([
                'btc_case_id' => $c->id, 'action' => $action, 'from_status' => $old, 'to_status' => $c->status,
                'remark' => $remark ?: null, 'user_id' => $user->id ?? null, 'user_name' => $user->name ?? null,
            ]);
        });

        // BTC-NOTIFY-01: mail whoever acts next (off unless switched on in BTC Settings)
        try {
            BtcNotifier::handoff($c->fresh(), $old, $action);
        } catch (\Throwable $e) {
            report($e);
        }

        return $c->fresh();
    }

    /** BTC-REQ-01: the approver's approval limit (BTC > Roles & Access); no limit set = any amount. */
    private function withinLimit($user, float $amount, string $what): void
    {
        $limit = BtcAccess::approvalLimit($user);
        if ($limit !== null && $amount > $limit + 0.009) {
            throw new RuntimeException("Rs ".number_format($amount, 2)." is above your approval limit of Rs ".number_format($limit, 2).", so you cannot {$what}. It needs an approver with a higher limit.");
        }
    }

    private function requireBill(BtcCase $c): void
    {
        if (! $c->bill_no || ! $c->bill_date || (float) $c->bill_amount <= 0) {
            throw new RuntimeException('Enter the bill number, bill date and bill amount.');
        }
        if ($c->bill_date->isFuture()) {
            throw new RuntimeException('The bill date cannot be in the future.');
        }
        if (! $c->files()->where('kind', 'bill')->exists()) {
            throw new RuntimeException('Attach the final bill.');
        }
    }

    /** Duplicate bill risk: same vendor + bill no, or same vendor + amount + date, in another live case. */
    /**
     * BTC-DUP-02 (17 Nov 2026): the same stay / ticket / vehicle / meeting claimed by the employee in his own Xeasy
     * expense claim for the same dates - paid twice if both go through.
     */
    public static function claimOverlaps(BtcCase $c): array
    {
        $types = ['hotel' => [1], 'air' => [2, 18], 'train' => [14], 'vehicle' => [4], 'meeting' => [13, 24, 25, 32, 33, 42, 45, 46]][$c->category] ?? [];
        $from = $c->service_from ?: $c->travel_date ?: $c->bill_date;
        $to = $c->service_to ?: $from;
        if (! $types || ! $from || ! $c->employee_id) {
            return [];
        }
        try {
            $db = DB::connection('expense');
            $a = $from->copy()->subDay()->toDateString();
            $b = $to->copy()->addDay()->toDateString();
            $years = $db->table('financialyear')->where('FromDate', '<=', $b)->where('Todate', '>=', $a)->pluck('YearId');
            $out = [];
            foreach ($years as $y) {
                if (! \Illuminate\Support\Facades\Schema::connection('expense')->hasTable("y{$y}_expenseclaims")) {
                    continue;
                }
                foreach ($db->table("y{$y}_expenseclaims as e")->leftJoin('claimtype as t', 't.ClaimId', '=', 'e.ClaimId')
                    ->where('e.CrBy', $c->employee_id)->whereIn('e.ClaimId', $types)->whereBetween('e.BillDate', [$a, $b])
                    ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Draft', 'Deny'])->limit(20)
                    ->get(['e.ExpId', 't.ClaimName', 'e.BillDate', 'e.FilledTAmt', 'e.ClaimStatus', 'e.ClaimMonth']) as $r) {
                    $out[] = ['exp_id' => $r->ExpId, 'type' => $r->ClaimName, 'date' => substr((string) $r->BillDate, 0, 10),
                        'amount' => (float) $r->FilledTAmt, 'status' => $r->ClaimStatus, 'month' => $r->ClaimMonth];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** BTC-ADV-02 (17 Nov 2026): the requester's advances paid long ago with no bill yet (block a new advance). */
    public static function overdueAdvances(int $employeeId, int $exceptId = 0): \Illuminate\Support\Collection
    {
        $days = (int) config('btc.advance_settle_days', 30);

        return BtcCase::where('employee_id', $employeeId)->where('id', '!=', $exceptId)->whereNotIn('status', ['cancelled', 'rejected', 'closed', 'draft'])
            ->whereNotNull('advance_paid_on')->whereDate('advance_paid_on', '<', now()->subDays($days)->toDateString())->whereNull('bill_submitted_at')
            ->get()->filter(fn ($x) => $x->advanceFigures()['balance'] > 0.5)->values();
    }

    public static function duplicates(BtcCase $c)
    {
        return BtcCase::where('id', '!=', $c->id)->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(function ($q) use ($c) {
                if ($c->bill_no) {
                    $q->orWhere(function ($w) use ($c) {
                        $w->where('bill_no', $c->bill_no)->where(function ($v) use ($c) {
                            $v->where('vendor_id', $c->vendor_id)->orWhere('vendor_name', $c->vendor_name);
                        });
                    });
                }
                if ($c->bill_amount && $c->bill_date) {
                    $q->orWhere(function ($w) use ($c) {
                        $w->where('bill_amount', $c->bill_amount)->whereDate('bill_date', $c->bill_date)
                            ->where(function ($v) use ($c) {
                                $v->where('vendor_id', $c->vendor_id)->orWhere('vendor_name', $c->vendor_name);
                            });
                    });
                }
            })
            ->get(['id', 'case_no', 'bill_no', 'bill_date', 'bill_amount', 'status']);
    }

    /** BTC-FIN-02: the accounting the Finance form would produce, without saving (Dr/Cr preview). */
    public function preview(BtcCase $c, array $d, $user): array
    {
        $copy = $c->replicate();
        $copy->id = $c->id;
        $copy->exists = false;
        $this->financeVerify($copy, $d + ['duplicate_checked' => 1, 'duplicate_override' => 1, 'claim_overlap_override' => 1, 'tds_threshold_override' => 1, '__preview' => 1], $user);

        return ['lines' => BtcLedger::journal($copy), 'tds' => (float) $copy->tds_amount, 'net' => (float) $copy->net_payable, 'section' => $copy->tds_section];
    }

    private function financeVerify(BtcCase $c, array $d, $user): void
    {
        $preview = ! empty($d['__preview']);
        $rules = config("btc.categories.{$c->category}", []);
        if (! $preview && $c->boarding_pass_required && ! $c->boarding_pass_received_on) {
            throw new RuntimeException('Air travel: the expense is booked only after the boarding pass / proof of travel is received.');
        }
        if (! $preview && empty($rules['scan_ok']) && ! $c->original_received_on) {
            throw new RuntimeException('This category needs the original bill before verification.');
        }
        // BTC-FIN-02: a case assigned to a named maker is verified by that maker (or the BTC admin / Finance head)
        if (! $preview && $c->assigned_maker_id && (int) $c->assigned_maker_id !== (int) ($user->id ?? 0) && ! $user->can('btc.admin') && ! $user->can('btc.checker')) {
            throw new RuntimeException('This case is assigned to another Finance maker.');
        }
        if (empty($d['duplicate_checked'])) {
            throw new RuntimeException('Confirm the duplicate-bill check in Focus.');
        }
        $dups = self::duplicates($c);
        if ($dups->isNotEmpty() && empty($d['duplicate_override'])) {
            throw new RuntimeException('Possible duplicate of '.$dups->pluck('case_no')->implode(', ').'. Tick "not a duplicate" with a remark to go on.');
        }
        if (($ov = self::claimOverlaps($c)) && empty($d['claim_overlap_override'])) {
            throw new RuntimeException('The employee has also claimed '.collect($ov)->map(fn ($o) => $o['type'].' '.$o['date'].' Rs '.number_format($o['amount']))->implode(', ').' in his own expense claim for these dates. Tick "not the same expense" with a remark to go on.');
        }
        $taxable = (float) ($d['taxable_amount'] ?? $c->taxable_amount ?? $c->bill_amount);
        $section = (string) ($d['tds_section'] ?? 'none');
        // BTC-TDS-02: a 'no TDS' choice while the vendor's yearly total crosses the category's TDS threshold
        $natureIn = (string) ($d['tds_nature'] ?? '');
        $catNature = config('btc.tds_nature_by_category.'.$c->category);
        $catRule = $catNature ? config("btc.tds_natures.{$catNature}") : null;
        if ($natureIn === 'none' && $catRule && ($catRule['section'] ?? 'none') !== 'none' && empty($d['tds_threshold_override'])) {
            $yt = BtcLedger::vendorYearTotal($c);
            if ($yt['total'] + $taxable > (float) ($catRule['threshold'][1] ?? PHP_FLOAT_MAX) || $taxable > (float) ($catRule['threshold'][0] ?? PHP_FLOAT_MAX)) {
                throw new RuntimeException('No TDS chosen, but this vendor has Rs '.number_format($yt['total'] + $taxable).' in FY '.$yt['fy'].' with this bill (single-bill limit Rs '.number_format($catRule['threshold'][0]).', yearly Rs '.number_format($catRule['threshold'][1]).' for '.$catRule['section'].'). Deduct TDS, or tick "TDS not applicable" with a remark.');
            }
        }
        $rate = $section === 'none' ? 0.0 : (float) ($d['tds_rate'] ?? 0);
        // BTC-TDS-01: the nature of the payment decides the section and the rate (Finance may still type a rate)
        $nature = (string) ($d['tds_nature'] ?? '');
        if ($nature !== '' && config("btc.tds_natures.{$nature}")) {
            [$section, $natRate] = BtcLedger::tdsFor($nature, $c->vendor_gstin, $taxable);
            $rate = $section === 'none' ? 0.0 : ((isset($d['tds_rate']) && $d['tds_rate'] !== '' && (float) $d['tds_rate'] > 0) ? (float) $d['tds_rate'] : $natRate);
            if (\Illuminate\Support\Facades\Schema::hasColumn($c->getTable(), 'tds_nature')) {
                $c->tds_nature = $nature;
            }
        }
        $tds = max(0, round($taxable * $rate / 100, 0) - (float) $c->tds_on_advance);
        // BTC-REQ-06: set off what was actually paid (with its TDS) less any refund, not the amount requested
        $f = $c->advanceFigures();
        $avail = max(0, $f['outstanding'] - $f['refund']);
        $adv = min((float) ($d['advance_adjusted'] ?? $avail), $avail);
        $net = round((float) $c->bill_amount - $tds - $adv, 2);
        if ($net < 0) {
            throw new RuntimeException('The advance is more than the bill: net payable would be '.number_format($net, 2).'. Recover the difference from the employee / vendor instead.');
        }
        $c->taxable_amount = $taxable;
        $c->expense_ledger = $d['expense_ledger'] ?? config("btc.focus.expense_ledgers.{$c->category}");
        $c->cost_centre = $d['cost_centre'] ?? $c->department_name;
        $c->tds_section = $section;
        $c->tds_rate = $rate;
        $c->tds_amount = $tds;
        $c->advance_adjusted = $adv;
        $c->net_payable = $net;
        // BTC-FIN-02 (17 Nov 2026): accounting dimensions, narration, JV numbers
        foreach (['dim_function', 'dim_vertical', 'dim_region', 'dim_activity', 'dim_crop', 'narration', 'jv_no', 'tds_jv_no'] as $k) {
            if (array_key_exists($k, $d) && \Illuminate\Support\Facades\Schema::hasColumn($c->getTable(), $k)) {
                $c->{$k} = trim((string) $d[$k]) ?: null;
            }
        }
        $c->duplicate_checked = true;
        $c->finance_by = $user->id;
        $c->finance_at = now();
        $c->finance_remark = $d['remark'] ?? null;
    }
}
