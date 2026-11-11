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
        'advance_paid' => [['advance_requested'], 'btc.finance', 'advance_paid'],
        'booked' => [['advance_paid'], null, 'booked'],
        'submit_bill' => [['booked', 'advance_paid', 'returned'], null, 'bill_submitted'],
        'dms_process' => [['bill_submitted'], 'btc.dms', 'dms_processed'],
        'finance_verify' => [['dms_processed'], 'btc.finance', 'finance_verified'],
        'check' => [['finance_verified'], 'btc.checker', 'checked'],
        'send_dept_approval' => [['checked'], 'btc.treasury', 'with_treasury'],
        'dept_approve' => [['with_treasury'], 'dept_approver', 'dept_approved'],
        'pay' => [['dept_approved'], 'btc.treasury', 'paid'],
        'close' => [['paid'], 'btc.finance', 'closed'],
        'return' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury'], 'any_desk', 'returned'],
        'receive_document' => [['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid'], 'btc.dms|btc.finance', null],
        'cancel' => [['draft', 'pending_manager', 'advance_requested', 'booked', 'returned'], null, 'cancelled'],
    ];

    public static function next(string $route, string $after): string
    {
        // after submit / manager approval
        if ($after === 'submit') {
            return ['admin_booking' => 'booked', 'employee_advance' => 'pending_manager', 'direct_bill' => 'bill_submitted'][$route] ?? 'booked';
        }

        return $after;
    }

    /** Which actions this user may take on this case now. */
    public static function allowed(BtcCase $c, $user): array
    {
        $out = [];
        foreach (self::ACTIONS as $action => [$from, $perm]) {
            if (in_array($c->status, $from, true) && self::may($c, $user, $perm, $action)) {
                $out[] = $action;
            }
        }

        return $out;
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
            return $can('btc.dms') || $can('btc.finance') || $can('btc.checker') || $can('btc.treasury');
        }
        foreach (explode('|', $perm) as $p) {
            if ($can($p)) {
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
        $old = $c->status;
        $remark = trim((string) ($d['remark'] ?? ''));
        $now = now();

        switch ($action) {
            case 'submit':
                $to = self::next($c->route, 'submit');
                if ($c->route === 'employee_advance') {
                    if (! $c->manager_id) {
                        throw new RuntimeException('No reporting manager found for the approval.');
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
                $to = (float) $c->advance_amount > 0 ? 'advance_requested' : 'booked';
                $c->manager_action_at = $now;
                $c->manager_remark = $remark ?: 'Approved';
                break;
            case 'manager_reject':
                if ($remark === '') {
                    throw new RuntimeException('Give the reason for rejecting.');
                }
                $c->manager_action_at = $now;
                $c->manager_remark = $remark;
                break;
            case 'advance_paid':
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
                $c->dept_approved_at = $now;
                $c->dept_remark = $remark ?: 'Approved';
                break;
            case 'pay':
                $amt = round((float) ($d['paid_amount'] ?? 0), 2);
                $utr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['utr_number'] ?? '')));
                if ($amt <= 0) {
                    throw new RuntimeException('Enter the amount paid.');
                }
                if ($amt > round((float) $c->net_payable, 2) + 0.009) {
                    throw new RuntimeException('Paid amount '.number_format($amt, 2).' is more than the net payable '.number_format((float) $c->net_payable, 2).'.');
                }
                if (strlen($utr) < 6) {
                    throw new RuntimeException('Enter the UTR / bank reference of the payment.');
                }
                $c->paid_amount = $amt;
                $c->paid_on = $d['paid_on'] ?? $now->toDateString();
                $c->utr_number = $utr;
                break;
            case 'close':
                if ($pending = $c->pendingDocuments()) {
                    throw new RuntimeException('Still waiting for: '.implode(', ', $pending).'.');
                }
                $c->closed_at = $now;
                break;
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

        DB::transaction(function () use ($c, $to, $old, $action, $remark, $user) {
            $c->status = $to ?? $old;
            $c->save();
            BtcCaseLog::create([
                'btc_case_id' => $c->id, 'action' => $action, 'from_status' => $old, 'to_status' => $c->status,
                'remark' => $remark ?: null, 'user_id' => $user->id ?? null, 'user_name' => $user->name ?? null,
            ]);
        });

        return $c->fresh();
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

    private function financeVerify(BtcCase $c, array $d, $user): void
    {
        $rules = config("btc.categories.{$c->category}", []);
        if ($c->boarding_pass_required && ! $c->boarding_pass_received_on) {
            throw new RuntimeException('Air travel: the expense is booked only after the boarding pass / proof of travel is received.');
        }
        if (empty($rules['scan_ok']) && ! $c->original_received_on) {
            throw new RuntimeException('This category needs the original bill before verification.');
        }
        if (empty($d['duplicate_checked'])) {
            throw new RuntimeException('Confirm the duplicate-bill check in Focus.');
        }
        $dups = self::duplicates($c);
        if ($dups->isNotEmpty() && empty($d['duplicate_override'])) {
            throw new RuntimeException('Possible duplicate of '.$dups->pluck('case_no')->implode(', ').'. Tick "not a duplicate" with a remark to go on.');
        }
        $taxable = (float) ($d['taxable_amount'] ?? $c->taxable_amount ?? $c->bill_amount);
        $section = (string) ($d['tds_section'] ?? 'none');
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
        $adv = min((float) ($d['advance_adjusted'] ?? $c->advance_amount), (float) $c->advance_amount);
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
        $c->duplicate_checked = true;
        $c->finance_by = $user->id;
        $c->finance_at = now();
        $c->finance_remark = $d['remark'] ?? null;
    }
}
