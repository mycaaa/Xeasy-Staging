<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use App\Models\BtcCaseLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BTC-01 / BTC-V2: stage rules of the Bill To Company process (config/btc.php).
 *
 * BTC-V2 (19 Nov 2026, requirement sheet item 24) - revised workflow, all in Xeasy (no Nova, DMS only for the paper):
 *  A admin_booking:    draft -> (pending_manager ->) booking_requested -> booked -> bill_with_dms (Admin hands the
 *                      physical bill to DMS) -> bill_with_finance (DMS hands it to Finance) -> bill_submitted
 *  B employee_advance: draft -> pending_manager -> (advance_requested -> advance_paid ->) booked -> bill_submitted
 *                      (the employee uploads the bill against the same request)
 *  C direct_bill:      created by Bill Upload by Finance (vendor / e-mail / other department bill) -> bill_submitted
 *  then: bill_submitted (data extraction, the read fields corrected) -> extracted (Finance Verifier 1: verify / return /
 *  reject) -> finance_verified (Focus entry by the verifier, exported) -> focus_done (Finance Approval: approve /
 *  return / reject / hold) -> fin_approved (Treasury payment) -> paid -> closed.
 *
 * Controls: duplicate invoice and own-claim overlap checked at Verifier 1; air boarding pass / original bill rules at
 * the Focus entry (switchable); Finance Approver is not the Verifier 1 of the case; approval limit applies; payment
 * needs a UTR and cannot exceed the net payable; closure only when every condition is met (closureGaps).
 */
class BtcWorkflow
{
    /** stages in which Finance works on the bill */
    public const WORK = ['bill_submitted', 'extracted', 'finance_verified', 'focus_done', 'fin_approved', 'paid'];

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
        // BTC-V2: Admin records that the physical bill was handed over to DMS; DMS records receiving it and handing it to Finance
        'forward_to_dms' => [['booked'], 'btc.booking', 'bill_with_dms'],
        'dms_receive' => [['bill_with_dms'], 'btc.dms', 'bill_with_finance'],
        'complete_activity' => [['booked'], 'owner|btc.booking', null],
        // BTC-REQ-06: Finance Advance pays the advance; a refund from the vendor is recorded separately
        'advance_paid' => [['advance_requested'], 'btc.finance|btc.advance', 'advance_paid'],
        'record_refund' => [['advance_paid', 'booked', 'bill_submitted', 'extracted', 'finance_verified', 'focus_done', 'fin_approved', 'paid', 'cancelled'], 'btc.finance|btc.advance', null],
        'booked' => [['advance_paid'], null, 'booked'],
        // BTC-V2: the employee uploads the soft copy against his own request; Finance uploads through Bill Upload by Finance
        'submit_bill' => [['booked', 'advance_paid', 'returned'], null, 'bill_submitted'],
        'finance_upload' => [['booked', 'advance_paid', 'bill_with_dms', 'bill_with_finance', 'returned'], 'btc.bill_upload|btc.finance', 'bill_submitted'],
        // BTC-V2 Step 2: the read fields corrected and sent to Finance Verifier 1
        'save_extraction' => [['bill_submitted'], 'btc.bill_upload|btc.finance', null],
        'confirm_extraction' => [['bill_submitted'], 'btc.bill_upload|btc.finance', 'extracted'],
        // BTC-V2: Finance Verifier 1, then the Focus entry on the same screen
        'verify1' => [['extracted'], 'btc.verifier', 'finance_verified'],
        'verify1_return' => [['extracted'], 'btc.verifier', 'bill_submitted'],
        'verify1_reject' => [['extracted'], 'btc.verifier', 'rejected'],
        'focus_entry' => [['finance_verified'], 'btc.verifier', 'focus_done'],
        // BTC-V2: Finance Approval (final), then Treasury
        'fin_approve' => [['focus_done'], 'btc.fin_approver', 'fin_approved'],
        'fin_return' => [['focus_done'], 'btc.fin_approver', null],
        'fin_reject' => [['focus_done'], 'btc.fin_approver', 'rejected'],
        'pay' => [['fin_approved'], 'btc.treasury', 'paid'],
        // BTC-PAY-01 (17 Nov 2026): a failed / returned payment is reversed with its reason (back to Treasury)
        'payment_failed' => [['fin_approved', 'paid'], 'btc.treasury', 'fin_approved'],
        'close' => [['paid'], 'btc.finance|btc.fin_approver|btc.treasury', 'closed'],
        // BTC-CLOSE-01: documents formally waived by an authorised person; a financial hold blocks approval, payment, closure
        'waive_document' => [self::WORK, 'btc.checker|btc.fin_approver|btc.admin', null],
        'hold' => [self::WORK, 'btc.verifier|btc.fin_approver|btc.checker|btc.admin', null],
        'release_hold' => [self::WORK, 'btc.fin_approver|btc.checker|btc.admin', null],
        // back to the requester for a better bill (a bill he uploaded is unreadable / wrong)
        'return' => [['bill_submitted', 'extracted'], 'btc.bill_upload|btc.finance|btc.verifier', 'returned'],
        'receive_document' => [['booked', 'bill_with_dms', 'bill_with_finance', 'bill_submitted', 'extracted', 'finance_verified', 'focus_done', 'fin_approved', 'paid'], 'btc.dms|btc.bill_upload|btc.finance|btc.verifier', null],
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
            'pay', 'close', 'fin_approve' => ! $held,
            'payment_failed' => (float) $c->paid_amount > 0,
            // a bill uploaded by Finance has no owner to return it to
            'return' => (bool) $c->employee_id && $c->route !== 'direct_bill',
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

        return $full ? 'paid' : 'fin_approved';
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
        if (in_array($action, ['pay', 'close', 'fin_approve'], true) && ($c->on_hold ?? false)) {
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
                // BTC-V2: the physical bill handed over to DMS (date); the bill is scanned and uploaded later by Finance
                $on = $d['bill_forwarded_dms_on'] ?? null;
                if (! $on || ! strtotime($on) || $on > $now->toDateString()) {
                    throw new RuntimeException('Enter the date the physical bill was handed over to DMS (not a future date).');
                }
                $c->bill_forwarded_dms_on = $on;
                foreach (['bill_no', 'bill_amount'] as $k) {
                    if (! empty($d[$k])) {
                        $c->{$k} = $d[$k];
                    }
                }
                $remark = trim('Physical bill handed over to DMS on '.\Carbon\Carbon::parse($on)->format('d-m-Y').($c->bill_no ? ' (bill '.$c->bill_no.')' : '').($remark ? ' - '.$remark : ''));
                break;
            case 'dms_receive':
                // BTC-V2: DMS records the physical bill received and handed over to Finance
                $rec = $d['dms_received_on'] ?? $now->toDateString();
                $hand = $d['dms_to_finance_on'] ?? $now->toDateString();
                foreach ([$rec, $hand] as $x) {
                    if (! strtotime($x) || $x > $now->toDateString()) {
                        throw new RuntimeException('Enter the received and handed-over dates (not future dates).');
                    }
                }
                if ($hand < $rec) {
                    throw new RuntimeException('The bill cannot be handed to Finance before DMS received it.');
                }
                $c->dms_received_on = $rec;
                $c->dms_to_finance_on = $hand;
                $c->dms_by = $user->id;
                $c->dms_at = $now;
                $c->dms_doc_no = trim((string) ($d['dms_doc_no'] ?? '')) ?: $c->dms_doc_no;
                $c->original_received_on = $c->original_received_on ?: $rec;   // the physical bill is the original
                $remark = trim('Physical bill received by DMS '.\Carbon\Carbon::parse($rec)->format('d-m-Y').', handed to Finance '.\Carbon\Carbon::parse($hand)->format('d-m-Y').($c->dms_doc_no ? ' (DMS no. '.$c->dms_doc_no.')' : '').($remark ? ' - '.$remark : ''));
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
            case 'finance_upload':
                // BTC-V2: the soft copy is uploaded (by the employee against his request, or by Finance); the invoice is
                // then read automatically (after this step) - only the bill file is needed here
                foreach (['pnr', 'travel_sector', 'travel_date'] as $k) {
                    if (array_key_exists($k, $d) && $d[$k] !== null && $d[$k] !== '') {
                        $c->$k = $d[$k];
                    }
                }
                if (! $c->files()->where('kind', 'bill')->exists()) {
                    throw new RuntimeException('Attach the bill / invoice.');
                }
                $src = (string) ($d['bill_source'] ?? '');
                $c->bill_source = array_key_exists($src, config('btc.bill_sources')) ? $src : ($action === 'submit_bill' ? 'employee' : 'dms');
                $c->bill_uploaded_by = $user->id;
                $c->bill_uploaded_at = $now;
                $c->bill_submitted_at = $c->bill_submitted_at ?: $now;
                $c->bill_submitted_by = $c->bill_submitted_by ?: $user->id;
                $c->submitted_via = $action === 'submit_bill' ? 'employee' : 'finance_upload';
                $c->extract_status = 'pending';
                $c->return_to = null;
                $remark = trim('Bill uploaded ('.config('btc.bill_sources.'.$c->bill_source, $c->bill_source).')'.($remark ? ' - '.$remark : ''));
                break;
            case 'save_extraction':
            case 'confirm_extraction':
                $this->takeExtraction($c, $d);
                if ($action === 'confirm_extraction') {
                    if ($missing = BtcExtraction::missing($c)) {
                        throw new RuntimeException('Fill in: '.implode(', ', $missing).'.');
                    }
                    if ($err = BtcExtraction::breakupError($c)) {
                        throw new RuntimeException($err);
                    }
                    $c->extract_status = $c->extract_status === 'read' ? 'read' : 'corrected';
                    $remark = trim('Data checked, sent to Finance Verifier 1'.($remark ? ' - '.$remark : ''));
                } else {
                    $remark = 'Extracted data corrected'.($remark ? ' - '.$remark : '');
                }
                break;
            case 'verify1':
                if ($missing = BtcExtraction::missing($c)) {
                    throw new RuntimeException('Data incomplete ('.implode(', ', $missing).'): return it for correction.');
                }
                $dups = self::duplicates($c);
                if ($dups->isNotEmpty() && empty($d['duplicate_override'])) {
                    throw new RuntimeException('Possible duplicate of '.$dups->pluck('case_no')->implode(', ').'. Tick "not a duplicate" with a remark, or reject.');
                }
                if (($ov = self::claimOverlaps($c)) && empty($d['claim_overlap_override'])) {
                    throw new RuntimeException('The employee has also claimed '.collect($ov)->map(fn ($o) => $o['type'].' '.$o['date'].' Rs '.number_format($o['amount']))->implode(', ').' in his own expense claim for these dates. Tick "not the same expense" with a remark, or reject.');
                }
                if (($dups->isNotEmpty() || $ov) && $remark === '') {
                    throw new RuntimeException('Give a remark for passing the duplicate / own-claim check.');
                }
                $c->duplicate_checked = true;
                $c->verify1_by = $user->id;
                $c->verify1_at = $now;
                $c->verify1_remark = $remark ?: null;
                $remark = trim('Extracted data verified'.($remark ? ' - '.$remark : ''));
                break;
            case 'verify1_return':
            case 'verify1_reject':
            case 'fin_reject':
                if ($remark === '') {
                    throw new RuntimeException($action === 'verify1_return' ? 'Say what is to be corrected.' : 'Give the reason for rejecting.');
                }
                break;
            case 'focus_entry':
                // the Focus entry (ledger, TDS, advance set-off, dimensions, narration, JV no.) on the verifier's screen
                $this->financeVerify($c, $d + ['duplicate_checked' => 1, 'duplicate_override' => 1, 'claim_overlap_override' => 1], $user);
                $remark = trim('Focus entry prepared'.($c->jv_no ? ' (JV '.$c->jv_no.')' : '').', net payable Rs '.number_format((float) $c->net_payable, 2).($remark ? ' - '.$remark : ''));
                break;
            case 'fin_approve':
                if ((int) $c->verify1_by === (int) ($user->id ?? 0) && ! $user->can('btc.admin')) {
                    throw new RuntimeException('You verified this case at Verifier 1; another Finance Approver must approve it.');
                }
                $this->withinLimit($user, (float) $c->net_payable, 'approve this payment');
                $c->fin_approved_by = $user->id;
                $c->fin_approved_at = $now;
                $c->fin_approve_remark = $remark ?: 'Approved';
                break;
            case 'fin_return':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for returning.');
                }
                $to = ($d['return_to'] ?? '') === 'bill_submitted' ? 'bill_submitted' : 'finance_verified';
                $remark = 'Returned to '.($to === 'bill_submitted' ? 'data extraction' : 'Focus entry').' - '.$remark;
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
                $to = $this->applyPayment($c, $payRow);
                $payRow['authorised_by'] = $user->id ?? null;
                $payRow['authorised_at'] = $now;
                $remark = trim(($to === 'paid' ? 'Paid in full' : 'Part payment').' Rs '.number_format($amt, 2).' '.strtoupper($mode).' UTR '.$utr.($remark ? ' - '.$remark : ''));
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
            case 'close':
                // BTC-CLOSE-01 (17 Nov 2026): closed only when every applicable condition is met, not just because it is paid
                if ($open = $c->closureGaps()) {
                    throw new RuntimeException('Cannot close yet: '.implode('; ', $open).'.');
                }
                $c->closed_at = $now;
                break;
            case 'return':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for returning.');
                }
                $c->return_to = 'bill_submitted';
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

        // BTC-V2 Step 2: an uploaded bill is read at once; complete data with no duplicate goes on to Verifier 1
        if (in_array($action, ['submit_bill', 'finance_upload'], true)) {
            return $this->autoExtract($c->fresh(), $user);
        }

        return $c->fresh();
    }

    /** BTC-V2: read the uploaded invoice into the case; move it to Finance Verifier 1 when nothing is missing. */
    public function autoExtract(BtcCase $c, $user): BtcCase
    {
        $r = BtcExtraction::run($c);
        $c->refresh();
        $complete = $r['ok'] && ! BtcExtraction::missing($c) && ! BtcExtraction::breakupError($c);
        $dups = self::duplicates($c);
        $note = $r['ok'] ? ($complete ? 'Invoice read: all required fields found' : 'Invoice read partly: '.implode(', ', BtcExtraction::missing($c) ?: [BtcExtraction::breakupError($c)])) : 'Invoice not read: '.$r['error'];
        if ($dups->isNotEmpty()) {
            $note .= '. Possible duplicate of '.$dups->pluck('case_no')->implode(', ');
        }
        $next = $complete && $dups->isEmpty() ? 'extracted' : 'bill_submitted';
        DB::transaction(function () use ($c, $next, $note, $user) {
            $from = $c->status;
            $c->status = $next;
            $c->save();
            BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'extraction', 'from_status' => $from, 'to_status' => $next,
                'remark' => mb_substr($note.($next === 'extracted' ? ' - sent to Finance Verifier 1' : ' - to be completed on Automatic Data Extraction'), 0, 1000),
                'user_id' => null, 'user_name' => 'System']);
        });

        return $c->fresh();
    }

    /** BTC-V2: the extraction fields as corrected by the Finance user (only the Finance data format fields). */
    private function takeExtraction(BtcCase $c, array $d): void
    {
        foreach (array_keys(config('btc.extraction.fields')) as $k) {
            if (! array_key_exists($k, $d)) {
                continue;
            }
            $v = is_string($d[$k]) ? trim($d[$k]) : $d[$k];
            if (in_array($k, ['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'other_charges', 'bill_amount'], true)) {
                $v = $v === '' || $v === null ? null : round((float) $v, 2);
            } elseif ($k === 'vendor_gstin') {
                $v = $v ? strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $v)) : null;
                if ($v && ! preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $v)) {
                    throw new RuntimeException('The vendor GSTIN is not in the right format.');
                }
            } elseif ($k === 'bill_date') {
                if ($v && (! strtotime($v) || $v > now()->toDateString())) {
                    throw new RuntimeException('The invoice date cannot be in the future.');
                }
            } else {
                $v = $v === '' ? null : mb_substr((string) $v, 0, $k === 'bill_particulars' ? 500 : 200);
            }
            $c->{$k} = $v;
        }
        $c->gst_amount = round((float) $c->cgst_amount + (float) $c->sgst_amount + (float) $c->igst_amount, 2);
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
        if (! $preview && $c->boarding_pass_required && ! $c->boarding_pass_received_on && config('btc.boarding_pass_before_verify', true)) {   // BTC-FLOW-01: switchable
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
