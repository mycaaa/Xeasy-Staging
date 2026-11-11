<?php

namespace App\Services\Payments;

use App\Support\PaymentUtr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UTR-02 (Finance sheet, 05 Nov): months that were marked paid with ₹0 or with no UTR.
 * Finance gives each one a reason (PaymentUtr::EXEMPT_REASONS). F&F-held months are closed by
 * linking the F&F settlement (date, amount, UTR, voucher): its UTR becomes the month's UTR and the
 * month is confirmed. da_payroll / advance_adjusted months no longer count as "paid without UTR".
 */
class UtrExemption
{
    public function __construct(private string $connection, private int $yearId)
    {
    }

    public static function forSession(): self
    {
        return new self((string) (session('expense_connection') ?: 'expense'), (int) session('year_id'));
    }

    public function table(): string
    {
        return "y{$this->yearId}_monthexpensefinal";
    }

    public function ready(): bool
    {
        return Schema::connection($this->connection)->hasColumn($this->table(), 'utr_exempt_reason');
    }

    /**
     * Months marked paid (ConfirmedPayment, or a pay date recorded) whose paid amount is ₹0 or whose
     * UTR is missing. $includeSettled also returns months whose reason means no UTR is due, and F&F
     * months already linked, so the screen can show them as done.
     */
    public function paidWithoutUtr(bool $includeSettled = true): array
    {
        $rows = DB::connection($this->connection)->table($this->table())
            ->where(function ($q) {
                $q->where('payment_status', 'ConfirmedPayment')->orWhere('Fin_PayDate', '>', '2000-01-01');
            })
            ->where(function ($q) {
                $q->whereRaw('COALESCE(Fin_PayAmt,0) = 0')
                    ->orWhereRaw("COALESCE(TRIM(utr_number),'') IN ('','0','-','NULL','NA','N/A')")
                    ->orWhereNotNull('utr_exempt_reason');
            })
            ->orderBy('EmployeeID')->orderBy('Month')
            ->get();

        $names = $this->employeeNames($rows->pluck('EmployeeID')->unique()->all());
        $out = [];
        foreach ($rows as $r) {
            $r->emp = $names[$r->EmployeeID] ?? null;
            $r->utr_still_owed = PaymentUtr::missingForMonth($r) || ((float) $r->Fin_PayAmt == 0.0 && $r->utr_exempt_reason === null);
            $r->settled = ! $r->utr_still_owed;
            if (! $includeSettled && $r->settled) {
                continue;
            }
            // a month with a real UTR and amount, and no reason, is not part of this list
            if ($r->utr_exempt_reason === null && ! $r->utr_still_owed) {
                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /** paidWithoutUtr() grouped by reason ('' = no reason yet). */
    public function grouped(): array
    {
        $g = ['' => []] + array_fill_keys(array_keys(PaymentUtr::EXEMPT_REASONS), []);
        foreach ($this->paidWithoutUtr() as $r) {
            $g[(string) $r->utr_exempt_reason][] = $r;
        }

        return $g;
    }

    public function find(int $employeeId, int $month)
    {
        return DB::connection($this->connection)->table($this->table())
            ->where('EmployeeID', $employeeId)->where('Month', $month)->first();
    }

    /** Set or clear the reason. Clearing an F&F reason is refused once the F&F settlement is linked. */
    public function setReason(int $employeeId, int $month, ?string $reason, ?int $userId = null, ?string $reference = null): void
    {
        if ($reason !== null && ! array_key_exists($reason, PaymentUtr::EXEMPT_REASONS)) {
            throw new \InvalidArgumentException("Unknown reason {$reason}.");
        }
        $row = $this->find($employeeId, $month);
        if (! $row) {
            throw new \RuntimeException("No month record for employee {$employeeId}, month {$month}.");
        }
        if ($row->utr_exempt_reason === 'ff_hold' && $reason !== 'ff_hold' && $row->ff_utr) {
            throw new \RuntimeException('The F&F settlement is already linked to this month; the reason cannot change.');
        }
        $upd = ['utr_exempt_reason' => $reason];
        if ($reason === 'same_bank_ref') {
            $ref = PaymentUtr::clean($reference) ?? PaymentUtr::clean($row->bank_reference ?? null);
            if ($ref === null) {
                throw new \InvalidArgumentException('Enter the bank reference number of the same-bank transfer.');
            }
            $upd['bank_reference'] = mb_substr($ref, 0, 30);
        }
        $pclBefore = \App\Support\PaymentChangeLog::snapshot($this->connection, $this->table(), [$row->id]); // PAY-LOG-01
        DB::connection($this->connection)->table($this->table())
            ->where('EmployeeID', $employeeId)->where('Month', $month)
            ->update($upd);
        \App\Support\PaymentChangeLog::diff($this->connection, $this->table(), $pclBefore, 'month_final', 'utr_reason');
        Log::info('UTR-02 reason set', ['conn' => $this->connection, 'year' => $this->yearId, 'emp' => $employeeId, 'month' => $month, 'from' => $row->utr_exempt_reason, 'to' => $reason, 'by' => $userId]);
    }

    /**
     * Link the F&F settlement that paid the F&F-held months.
     * FF-02 (Finance decision 7 Nov 2026):
     *  - one entry covers ALL held months of the employee (not one per month);
     *  - each month then shows as paid: paid amount = the month's payable amount (when nothing was
     *    paid yet) and paid date = the F&F date; the F&F UTR becomes the month's UTR; the month is
     *    ConfirmedPayment. The F&F total is kept in ff_settlement_amount on every linked month.
     *
     * @return int  months linked
     */
    public function linkFfSettlement(int $employeeId, int $month, array $d, ?int $userId = null): int
    {
        $row = $this->find($employeeId, $month);
        if (! $row) {
            throw new \RuntimeException("No month record for employee {$employeeId}, month {$month}.");
        }
        if ($row->utr_exempt_reason !== 'ff_hold') {
            throw new \RuntimeException('Only months held for F&F can be linked to an F&F settlement.');
        }
        $utr = PaymentUtr::assert($d['ff_utr'] ?? null, 'F&F settlement');
        $date = Carbon::parse($d['ff_settlement_date'])->toDateString();
        if ($date > now()->toDateString()) {
            throw new \RuntimeException('The F&F settlement date cannot be in the future.');
        }
        $amount = round((float) ($d['ff_settlement_amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Enter the F&F settlement amount.');
        }

        $db = DB::connection($this->connection);
        $months = $db->table($this->table())->where('EmployeeID', $employeeId)->where('utr_exempt_reason', 'ff_hold')
            ->where(function ($q) {
                $q->whereNull('ff_utr')->orWhere('ff_utr', '');
            })->orderBy('Month')->get();
        if ($months->isEmpty()) {
            $months = collect([$row]);
        }
        $cols = array_flip(Schema::connection($this->connection)->getColumnListing($this->table()));
        $pclBefore = \App\Support\PaymentChangeLog::snapshot($this->connection, $this->table(), $months->pluck('id')->all()); // PAY-LOG-01
        $db->transaction(function () use ($db, $months, $cols, $date, $amount, $utr, $d, $userId) {
            foreach ($months as $m) {
                $upd = [
                    'ff_settlement_date' => $date,
                    'ff_settlement_amount' => $amount,
                    'ff_utr' => $utr,
                    'ff_voucher_no' => trim((string) ($d['ff_voucher_no'] ?? '')) ?: null,
                    'ff_linked_by' => $userId,
                    'ff_linked_at' => now(),
                    'payment_status' => 'ConfirmedPayment',
                ];
                if (PaymentUtr::clean($m->utr_number) === null) {
                    $upd['utr_number'] = $utr;
                }
                if (empty($m->payment_confirmed_date) || $m->payment_status !== 'ConfirmedPayment') {
                    $upd['payment_confirmed_date'] = $date;
                }
                if (isset($cols['payment_date'])) {
                    $upd['payment_date'] = $date;
                }
                if (isset($cols['Fin_PayDate'])) {
                    $upd['Fin_PayDate'] = $date;
                }
                $payable = (float) ($m->payment_amount ?? 0) > 0 ? (float) $m->payment_amount : (float) ($m->Finance_Amount ?? 0);
                if (isset($cols['Fin_PayAmt']) && (float) ($m->Fin_PayAmt ?? 0) <= 0 && $payable > 0) {
                    $upd['Fin_PayAmt'] = $payable;
                }
                if (isset($cols['is_exported']) && (int) ($m->is_exported ?? 0) === 2) {
                    $upd['is_exported'] = 0;
                }
                $db->table($this->table())->where('id', $m->id)->update(array_intersect_key($upd, $cols));
            }
        });
        \App\Support\PaymentChangeLog::diff($this->connection, $this->table(), $pclBefore, 'month_final', 'ff_link', 'Voucher '.(trim((string) ($d['ff_voucher_no'] ?? '')) ?: '-').'; months '.$months->pluck('Month')->implode(','));
        Log::info('UTR-02 F&F linked', ['conn' => $this->connection, 'year' => $this->yearId, 'emp' => $employeeId, 'months' => $months->pluck('Month')->all(), 'utr' => $utr, 'amount' => $amount, 'by' => $userId]);

        return $months->count();
    }

    private function validDate($d): ?string
    {
        return ($d && (string) $d > '2000-01-01') ? (string) $d : null;
    }

    private function employeeNames(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        try {
            $out = [];
            foreach (array_chunk($ids, 1000) as $chunk) {
                foreach (DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $chunk)
                    ->get(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname', 'EmpStatus']) as $e) {
                    $out[$e->EmployeeID] = (object) [
                        'code' => $e->EmpCode,
                        'name' => trim(preg_replace('/\s+/', ' ', "{$e->Fname} {$e->Sname} {$e->Lname}")),
                        'status' => $e->EmpStatus === 'A' ? 'Active' : ($e->EmpStatus === 'D' ? 'Left' : (string) $e->EmpStatus),
                    ];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('UTR-02: employee names not read: '.$e->getMessage());

            return [];
        }
    }
}
