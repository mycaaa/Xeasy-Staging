<?php

namespace App\Services\Focus;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FOCUS-RECON-01 (10 Nov 2026): compares one employee's Focus ledger with what Xeasy says that ledger should hold.
 *
 * Xeasy side, for the period of the Focus file (normally 1 April to the print date):
 *  - Opening = the employee's Xeasy closing balance of the previous year (fixed imprest + advances not adjusted,
 *    employee_ledger_balances.closing_balance) less every previous-year month that was approved but not yet paid
 *    when the period starts (Focus books those expenses in the old year and the payment in the new one).
 *  - Debits (paid to the employee): advances and imprest paid in the period (advance_request_activities), and
 *    reimbursements paid in the period for this year's and last year's months (monthexpensefinal payment amount).
 *  - Credits (expense booked): for each month of this year that Finance has approved, the financed amount per
 *    claim head (DA excluded; DA is not in the employee ledger), mapped to the Focus account and sub-ledger
 *    through fc_claim_focus_mapping. Activity heads (claim group 7) are compared per account, because their
 *    sub-ledger split is decided per bill line.
 *
 * Matching: debits by amount and nearest date (then same kind and month with a different amount);
 * credits by expense month + Focus account + sub-ledger. Everything left is listed as "only in Focus" or
 * "only in Xeasy"; the bridge adds every difference and must equal the closing difference.
 */
class FocusLedgerReconciler
{
    private const DA_CLAIMS = [19, 20];

    public function __construct(private string $conn, private int $companyId)
    {
    }

    public function reconcile(array $section, ?string $from, ?string $to): array
    {
        $notes = [];
        $emp = $this->employee($section['emp_code']);
        if (! $emp) {
            return ['error' => 'Employee code '.($section['emp_code'] ?: '?').' from the ledger ("'.$section['account'].'") was not found in HRMS for this company.', 'section' => $section];
        }
        $from = $from ?: min(array_column($section['lines'], 'date') ?: [date('Y-04-01')]);
        $to = $to ?: max(array_column($section['lines'], 'date') ?: [date('Y-m-d')]);
        $yearId = $this->yearFor($from);
        $prevYearId = $yearId - 1;
        $fyStartYear = (int) substr($this->fyFrom($yearId) ?: $from, 0, 4);

        // ---------- Xeasy side ----------
        $x = $this->xeasyEntries((int) $emp->EmployeeID, $yearId, $prevYearId, $from, $to, $fyStartYear);

        // ---------- debits ----------
        $fDebits = array_values(array_filter($section['lines'], fn ($l) => $l['debit'] > 0));
        $xDebits = array_values(array_filter($x['entries'], fn ($e) => $e['side'] === 'Dr'));
        $debitRows = $this->matchDebits($fDebits, $xDebits, $fyStartYear);

        // ---------- credits ----------
        $fCredits = array_values(array_filter($section['lines'], fn ($l) => $l['credit'] > 0));
        $xCredits = array_values(array_filter($x['entries'], fn ($e) => $e['side'] === 'Cr'));
        $creditRows = $this->matchCredits($fCredits, $xCredits, $x['activity_accounts'], $fyStartYear, $x['doc_nos']);

        // ---------- totals, bridge ----------
        $fOpen = (float) $section['opening'];
        $fDr = array_sum(array_column($fDebits, 'debit'));
        $fCr = array_sum(array_column($fCredits, 'credit'));
        $fClose = round($fOpen + $fDr - $fCr, 2);
        $xOpen = $x['opening'];
        $xDr = array_sum(array_column($xDebits, 'amount'));
        $xCr = array_sum(array_column($xCredits, 'amount'));
        $xClose = round($xOpen + $xDr - $xCr, 2);

        $bridge = [];
        if (abs($fOpen - $xOpen) >= 0.5) {
            $bridge[] = ['what' => 'Opening balance', 'detail' => 'Focus '.$this->fmt($fOpen).' vs Xeasy '.$this->fmt($xOpen).'. '.$x['opening_note'], 'effect' => round($fOpen - $xOpen, 2)];
        }
        foreach ($debitRows as $r) {
            if (abs($r['diff']) >= 0.5) {
                $bridge[] = ['what' => 'Debit: '.$r['status'], 'detail' => $r['label'], 'effect' => round($r['diff'], 2)];
            }
        }
        foreach ($creditRows as $r) {
            if (abs($r['diff']) >= 0.5) {
                $bridge[] = ['what' => 'Credit: '.$r['status'], 'detail' => $r['label'], 'effect' => round(-$r['diff'], 2)];
            }
        }
        $bridgeTotal = round(array_sum(array_column($bridge, 'effect')), 2);

        if ($section['closing'] !== null && abs($section['closing'] - $fClose) >= 0.5) {
            $notes[] = 'The closing balance printed by Focus ('.$this->fmt($section['closing']).') is not opening + debits - credits of its own lines ('.$this->fmt($fClose).'): check the file is complete.';
        }

        return [
            'employee' => ['id' => (int) $emp->EmployeeID, 'code' => $emp->EmpCode, 'name' => trim($emp->Fname.' '.$emp->Sname.' '.$emp->Lname), 'status' => $emp->EmpStatus],
            'account' => $section['account'], 'from' => $from, 'to' => $to, 'year_id' => $yearId,
            'summary' => [
                'focus' => ['opening' => $fOpen, 'debit' => round($fDr, 2), 'credit' => round($fCr, 2), 'closing' => $fClose],
                'xeasy' => ['opening' => $xOpen, 'debit' => round($xDr, 2), 'credit' => round($xCr, 2), 'closing' => $xClose],
            ],
            'difference' => round($fClose - $xClose, 2),
            'bridge' => $bridge, 'bridge_total' => $bridgeTotal,
            'debits' => $debitRows, 'credits' => $creditRows,
            'months' => $this->monthBalances($section, $x['entries'], $fOpen, $xOpen, $from, $to),
            'notes' => array_values(array_unique(array_merge($notes, $x['notes']))),
        ];
    }

    // ------------------------------------------------------------------ Xeasy entries

    private function xeasyEntries(int $empId, int $yearId, int $prevYearId, string $from, string $to, int $fyStartYear): array
    {
        $db = DB::connection($this->conn);
        $entries = [];
        $docNos = [];
        $local = [];

        // opening
        $base = null;
        if (Schema::connection($this->conn)->hasTable('employee_ledger_balances')) {
            $base = $db->table('employee_ledger_balances')->where('employee_id', $empId)->where('year_id', $prevYearId)->orderByDesc('id')->value('closing_balance');
        }
        $openingNote = '';
        if ($base === null) {
            $base = 0;
            $openingNote = 'No Xeasy ledger balance for the previous year (employee_ledger_balances): Xeasy opening starts from 0.';
            $local[] = $openingNote;
        }
        $opening = (float) $base;
        $prevPayable = [];
        foreach ($this->months($prevYearId, $empId) as $m) {
            if ((float) $m->Finance_Amount <= 0) {
                continue;
            }
            $paidOn = $this->paidOn($m);
            if ($paidOn === null || $paidOn >= $from) {
                $amt = $this->payable($m);
                if ($amt > 0) {
                    $opening -= $amt;
                    $prevPayable[] = $this->monthName((int) $m->Month).' (previous year) '.$this->fmt($amt);
                }
            }
        }
        if ($prevPayable) {
            $openingNote .= ' Xeasy opening = previous-year closing '.$this->fmt((float) $base).' less approved months still unpaid on '.$from.': '.implode(', ', $prevPayable).'.';
        }

        // debits: reimbursements paid in the period (this year and last year months)
        foreach ([$prevYearId => 'previous year', $yearId => ''] as $y => $tag) {
            foreach ($this->months($y, $empId) as $m) {
                $paidOn = $this->paidOn($m);
                $amt = $this->paidAmount($m);
                if ($paidOn !== null && $paidOn >= $from && $paidOn <= $to && $amt > 0) {
                    $entries[] = ['side' => 'Dr', 'kind' => 'reimbursement', 'date' => $paidOn, 'amount' => $amt,
                        'year_id' => $y, 'fy_start' => $y === $yearId ? $fyStartYear : $fyStartYear - 1, 'month' => (int) $m->Month, 'ref' => 'Doc '.$m->DocNo,
                        'label' => 'Reimbursement for '.$this->monthName((int) $m->Month).($tag ? " ($tag)" : '').' - '.($m->payment_status ?: 'paid').($m->utr_number ? ', UTR '.$m->utr_number : '')];
                }
                if ($y === $yearId && $m->DocNo) {
                    $docNos[(int) $m->Month] = (string) $m->DocNo;
                }
            }
        }

        // debits: advances / imprest paid in the period
        if (Schema::connection($this->conn)->hasTable('advance_request_activities')) {
            $adv = $db->table('advance_request_activities as ara')
                ->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')
                ->leftJoin('adv_activity_types as aat', 'ara.activity_type_id', '=', 'aat.id')
                ->where('are.employee_id', $empId)->whereIn('ara.payment_status', ['Paid', 'Unsettled'])
                ->whereNull('ara.deleted_at')->whereNull('are.deleted_at')
                ->whereBetween('ara.payment_date', [$from, $to])
                ->get(['ara.id', 'ara.amount', 'ara.payment_amount', 'ara.payment_date', 'ara.purpose', 'ara.utr_number', 'ara.is_imprest', 'aat.type_name']);
            foreach ($adv as $a) {
                $amt = (float) ($a->payment_amount ?: $a->amount);
                if ($amt <= 0) {
                    continue;
                }
                $entries[] = ['side' => 'Dr', 'kind' => 'advance', 'date' => substr((string) $a->payment_date, 0, 10), 'amount' => round($amt, 2),
                    'year_id' => $yearId, 'month' => (int) date('n', strtotime($a->payment_date)), 'ref' => 'Advance #'.$a->id,
                    'label' => ($a->is_imprest ? 'Imprest' : 'Advance').' #'.$a->id.($a->type_name ? ' ('.$a->type_name.')' : '').($a->utr_number ? ', UTR '.$a->utr_number : '')];
            }
        }

        // credits: expense booked per month and head (this year's months approved by Finance)
        $map = $this->focusMap();
        $activityAccounts = [];
        $amountCol = $this->companyId === 11 ? 'ApprTAmt' : 'FinancedTAmt';
        foreach ($this->months($yearId, $empId) as $m) {
            if ((float) $m->Finance_Amount <= 0) {
                continue;
            }
            $jvDate = substr((string) ($m->Finance_Date ?: $m->Fin_AppDate ?: $m->focus_exported_date ?: $m->payment_date), 0, 10);
            if ($jvDate === '' || $jvDate > $to) {
                continue;
            }
            $lines = $db->table("y{$yearId}_expenseclaims as c")->join('claimtype as t', 't.ClaimId', '=', 'c.ClaimId')
                ->where('c.CrBy', $empId)->where('c.ClaimMonth', (int) $m->Month)->where('c.ClaimStatus', '!=', 'Deactivate')
                ->where("c.{$amountCol}", '>', 0)->whereNotIn('c.ClaimId', self::DA_CLAIMS)
                ->groupBy('c.ClaimId', 't.cgId', 't.ClaimName', 'c.WType')
                ->get(['c.ClaimId', 't.cgId', 't.ClaimName', 'c.WType', DB::raw("SUM(c.{$amountCol}) as amt"), DB::raw('COUNT(*) as n')]);
            foreach ($lines as $l) {
                $fm = $map[(int) $l->ClaimId.'|'.(int) $l->WType] ?? $map[(int) $l->ClaimId.'|'] ?? null;
                $acct = $fm->focus_ledger_code ?? '?';
                $sub = (int) $l->cgId === 7 ? '*' : ($fm->focus_name_subl ?? '?');
                if ((int) $l->cgId === 7) {
                    $activityAccounts[$acct] = true;
                }
                $entries[] = ['side' => 'Cr', 'kind' => 'expense', 'date' => $jvDate, 'amount' => round((float) $l->amt, 2),
                    'year_id' => $yearId, 'month' => (int) $m->Month, 'account' => $acct, 'sub_ledger' => $sub,
                    'ref' => 'Doc '.$m->DocNo, 'label' => $l->ClaimName.' ('.$l->n.' bills)', 'claim_id' => (int) $l->ClaimId,
                    'head' => $fm->focus_name ?? $l->ClaimName];
            }
        }

        return ['opening' => round($opening, 2), 'opening_note' => trim($openingNote), 'entries' => $entries,
            'activity_accounts' => array_keys($activityAccounts), 'doc_nos' => $docNos, 'notes' => $local];
    }

    // ------------------------------------------------------------------ matching

    private function matchDebits(array $f, array $x, int $fyStartYear): array
    {
        $rows = [];
        $used = [];
        $left = [];
        foreach ($f as $fl) {
            $best = null;
            foreach ($x as $k => $xe) {
                if (isset($used[$k]) || abs($xe['amount'] - $fl['debit']) >= 0.5) {
                    continue;
                }
                $d = abs(strtotime($xe['date']) - strtotime($fl['date']));
                if ($best === null || $d < $best[1]) {
                    $best = [$k, $d];
                }
            }
            if ($best) {
                $used[$best[0]] = true;
                $xe = $x[$best[0]];
                $days = (int) round($best[1] / 86400);
                $note = $days > 7 ? "dates {$days} days apart" : '';
                $note = trim($note.' '.$this->narrationCheck($fl, $xe, $fyStartYear));
                $rows[] = $this->debitRow($fl, $xe, 'Matched', $note);
            } else {
                $left[] = $fl;
            }
        }
        // same kind and month, different amount
        foreach ($left as $fl) {
            $pick = null;
            foreach ($x as $k => $xe) {
                if (isset($used[$k]) || $xe['kind'] !== $fl['kind']) {
                    continue;
                }
                $sameMonth = $fl['for_month'] && $xe['kind'] === 'reimbursement' ? $fl['for_month'] === $xe['month'] : abs(strtotime($xe['date']) - strtotime($fl['date'])) <= 7 * 86400;
                if ($sameMonth) {
                    $pick = $k;
                    break;
                }
            }
            if ($pick !== null) {
                $used[$pick] = true;
                $rows[] = $this->debitRow($fl, $x[$pick], 'Amount differs', '');
            } else {
                $rows[] = $this->debitRow($fl, null, 'Only in Focus', $fl['kind'] === 'reimbursement' && $fl['for_month'] ? 'no Xeasy payment for '.$this->monthName($fl['for_month']).' in this period' : '');
            }
        }
        foreach ($x as $k => $xe) {
            if (! isset($used[$k])) {
                $rows[] = $this->debitRow(null, $xe, 'Only in Xeasy', $xe['kind'] === 'reimbursement' ? 'payment recorded in Xeasy but not in the Focus ledger' : 'advance paid in Xeasy but not in the Focus ledger');
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date'])); // newest first

        return $rows;
    }

    private function debitRow(?array $fl, ?array $xe, string $status, string $note): array
    {
        $fa = $fl['debit'] ?? 0.0;
        $xa = $xe['amount'] ?? 0.0;

        return [
            'status' => $status, 'date' => $fl['date'] ?? $xe['date'],
            'focus_date' => $fl['date'] ?? null, 'focus_voucher' => $fl['voucher'] ?? null, 'focus_narration' => $fl['narration'] ?? null, 'focus_amount' => $fa,
            'xeasy_date' => $xe['date'] ?? null, 'xeasy_ref' => $xe['ref'] ?? null, 'xeasy_label' => $xe['label'] ?? null, 'xeasy_amount' => $xa,
            'diff' => round($fa - $xa, 2), 'note' => $note,
            'label' => implode(' | ', array_filter([
                $fl ? 'Focus '.$fl['date'].' '.$fl['voucher'].' "'.$fl['narration'].'" '.$this->fmt($fa) : null,
                $xe ? 'Xeasy '.$xe['date'].' '.$xe['label'].' '.$this->fmt($xa) : null,
            ])),
        ];
    }

    private function narrationCheck(array $fl, array $xe, int $fyStartYear): string
    {
        if ($xe['kind'] !== 'reimbursement' || ! $fl['for_month']) {
            return '';
        }
        $out = [];
        if ($fl['for_month'] !== $xe['month']) {
            $out[] = 'narration says '.$this->monthName($fl['for_month']).' but Xeasy paid '.$this->monthName($xe['month']);
        }
        if ($fl['for_fy_start'] && $fl['for_fy_start'] !== $xe['fy_start'] && $fl['for_month'] === $xe['month']) {
            $calYear = $xe['month'] >= 4 ? $xe['fy_start'] : $xe['fy_start'] + 1;
            $out[] = 'narration year is wrong (should be '.$this->monthName($xe['month']).'-'.substr((string) $calYear, 2).')';
        }

        return implode('; ', $out);
    }

    private function matchCredits(array $f, array $x, array $activityAccounts, int $fyStartYear, array $docNos): array
    {
        $groups = [];
        $key = function ($month, $acct, $sub) use ($activityAccounts) {
            $sub = in_array($acct, $activityAccounts, true) ? '*' : ($sub ?: '-');

            return sprintf('%02d', $month ?: 0).'|'.$acct.'|'.$sub;
        };
        foreach ($f as $fl) {
            $month = $fl['for_month'] ?: 0;
            $k = $key($month, $fl['account_code'], $fl['sub_code'] ?? $fl['sub_ledger']);
            $g = &$groups[$k];
            $g['focus'] = ($g['focus'] ?? 0) + $fl['credit'];
            $g['focus_lines'][] = $fl['date'].' '.$fl['voucher'].' '.$this->fmt($fl['credit']);
            $g['vouchers'][$fl['voucher']] = true;
            $g['head'] = $g['head'] ?? ($fl['sub_ledger'] && $fl['sub_ledger'] !== 'N/A' ? $fl['sub_ledger'] : $fl['account_name']);
            $g['month'] = $month;
            $g['account'] = $fl['account_code'];
            $g['date'] = max($g['date'] ?? '', $fl['date']);
            if ($fl['jv_doc_no'] && $month && isset($docNos[$month]) && ltrim($fl['jv_doc_no'], '0') !== ltrim($docNos[$month], '0')) {
                $g['notes']['doc'] = 'voucher '.$fl['voucher'].' does not carry the Xeasy document no. '.$docNos[$month].' of that month';
            }
            if (! $fl['for_month']) {
                $g['notes']['month'] = 'the narration does not say which month the expense is for';
            }
            unset($g);
        }
        foreach ($x as $xe) {
            $k = $key($xe['month'], $xe['account'], $xe['sub_ledger']);
            $g = &$groups[$k];
            $g['xeasy'] = ($g['xeasy'] ?? 0) + $xe['amount'];
            $g['xeasy_lines'][] = $xe['label'].' '.$this->fmt($xe['amount']);
            $g['head'] = $g['head'] ?? $xe['head'];
            $g['month'] = $xe['month'];
            $g['account'] = $xe['account'];
            $g['date'] = max($g['date'] ?? '', $xe['date']);
            $g['ref'] = $xe['ref'];
            unset($g);
        }
        $rows = [];
        foreach ($groups as $k => $g) {
            $fa = round($g['focus'] ?? 0, 2);
            $xa = round($g['xeasy'] ?? 0, 2);
            $status = ! isset($g['xeasy']) ? 'Only in Focus' : (! isset($g['focus']) ? 'Only in Xeasy' : (abs($fa - $xa) < 0.5 ? 'Matched' : 'Amount differs'));
            $month = (int) $g['month'];
            $rows[] = [
                'status' => $status, 'date' => $g['date'] ?? '', 'month' => $month ? $this->monthName($month) : '?',
                'month_num' => $month, 'account' => $g['account'], 'head' => $g['head'] ?? '',
                'vouchers' => implode(', ', array_keys($g['vouchers'] ?? [])), 'xeasy_ref' => $g['ref'] ?? '',
                'focus_amount' => $fa, 'xeasy_amount' => $xa, 'diff' => round($fa - $xa, 2),
                'focus_lines' => $g['focus_lines'] ?? [], 'xeasy_lines' => $g['xeasy_lines'] ?? [],
                'note' => implode('; ', $g['notes'] ?? []),
                'label' => ($month ? $this->monthName($month) : 'month ?').' '.($g['head'] ?? '').' ('.$g['account'].'): Focus '.$this->fmt($fa).', Xeasy '.$this->fmt($xa),
            ];
        }
        // newest month first (Apr..Mar order of the financial year)
        usort($rows, fn ($a, $b) => [$this->fyPos($b['month_num']), $a['account'], $a['head']] <=> [$this->fyPos($a['month_num']), $b['account'], $b['head']]);

        return $rows;
    }

    private function monthBalances(array $section, array $xEntries, float $fOpen, float $xOpen, string $from, string $to): array
    {
        $ends = [];
        $d = new \DateTime(substr($from, 0, 7).'-01');
        $end = new \DateTime($to);
        while ($d <= $end) {
            $e = (clone $d)->modify('last day of this month')->format('Y-m-d');
            $ends[] = min($e, $to);
            $d->modify('first day of next month');
        }
        $out = [];
        $prevDiff = round($fOpen - $xOpen, 2);
        foreach ($ends as $e) {
            $fb = $fOpen;
            foreach ($section['lines'] as $l) {
                if ($l['date'] <= $e) {
                    $fb += $l['debit'] - $l['credit'];
                }
            }
            $xb = $xOpen;
            foreach ($xEntries as $xe) {
                if ($xe['date'] <= $e) {
                    $xb += $xe['side'] === 'Dr' ? $xe['amount'] : -$xe['amount'];
                }
            }
            $diff = round($fb - $xb, 2);
            $out[] = ['month_end' => $e, 'focus' => round($fb, 2), 'xeasy' => round($xb, 2), 'diff' => $diff, 'changed' => abs($diff - $prevDiff) >= 0.5];
            $prevDiff = $diff;
        }

        return array_reverse($out); // newest first
    }

    // ------------------------------------------------------------------ helpers

    private function employee(?string $code)
    {
        if (! $code) {
            return null;
        }

        return DB::connection('hrims')->table('hrm_employee')->where('EmpCode', $code)->where('CompanyId', $this->companyId)
            ->orderByRaw("EmpStatus = 'A' DESC")->first(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname', 'EmpStatus']);
    }

    private function months(int $yearId, int $empId)
    {
        $t = "y{$yearId}_monthexpensefinal";
        if ($yearId < 1 || ! Schema::connection($this->conn)->hasTable($t)) {
            return collect();
        }

        return DB::connection($this->conn)->table($t)->where('EmployeeID', $empId)->orderBy('id')->get();
    }

    private function paidOn(object $m): ?string
    {
        foreach (['payment_date', 'Fin_PayDate', 'payment_instructed_date', 'payment_confirmed_date'] as $c) {
            $v = substr((string) ($m->{$c} ?? ''), 0, 10);
            if ($v !== '' && ! str_starts_with($v, '0000')) {
                return $v;
            }
        }

        return null;
    }

    private function paidAmount(object $m): float
    {
        return round((float) (($m->payment_amount ?? 0) ?: ($m->Fin_PayAmt ?? 0)), 2);
    }

    private function payable(object $m): float
    {
        $paid = $this->paidAmount($m);
        if ($paid > 0) {
            return $paid;
        }
        $da = (float) DB::connection($this->conn)->table('y'.(int) $m->YearId.'_expenseclaims')->where('CrBy', $m->EmployeeID)->where('ClaimMonth', $m->Month)
            ->where('ClaimStatus', '!=', 'Deactivate')->whereIn('ClaimId', self::DA_CLAIMS)->sum('FinancedTAmt');

        return round((float) $m->Finance_Amount - $da - (float) ($m->total_adjustment ?? 0) - (float) ($m->other_adjustment ?? 0), 2);
    }

    private function focusMap(): array
    {
        $out = [];
        if (! Schema::connection($this->conn)->hasTable('fc_claim_focus_mapping')) {
            return $out;
        }
        foreach (DB::connection($this->conn)->table('fc_claim_focus_mapping')->where('is_active', 1)->orderBy('id')->get() as $r) {
            $k = (int) $r->claim_id.'|'.($r->wtype === null ? '' : (int) $r->wtype);
            $out[$k] = $out[$k] ?? $r;
        }

        return $out;
    }

    private function yearFor(string $date): int
    {
        $id = DB::connection($this->conn)->table('financialyear')->where('FromDate', '<=', $date)->where('Todate', '>=', $date)->orderByDesc('YearId')->value('YearId');

        return (int) ($id ?: \App\Support\CurrentYear::id($this->conn));
    }

    private function fyFrom(int $yearId): ?string
    {
        return DB::connection($this->conn)->table('financialyear')->where('YearId', $yearId)->value('FromDate');
    }

    private function fyPos(int $m): int
    {
        return $m === 0 ? 99 : ($m >= 4 ? $m - 4 : $m + 8);
    }

    private function monthName(int $m): string
    {
        return $m >= 1 && $m <= 12 ? date('M', mktime(0, 0, 0, $m, 1)) : '?';
    }

    private function fmt(float $v): string
    {
        return ($v < 0 ? '-' : '').'Rs '.number_format(abs($v), 2);
    }
}
