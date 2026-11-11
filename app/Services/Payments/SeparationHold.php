<?php

namespace App\Services\Payments;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * FF-01 (Finance decision 7 Nov 2026): once HR enters an employee's separation date, their unpaid
 * expense months go on payment hold automatically ("payable with F&F"). They are then paid through
 * the F&F settlement, linked on Expense Payments > No-UTR Reasons (UtrExemption::linkFfSettlement).
 *
 * hold(): marks one employee's unpaid months; blockReason(): what payment approval shows.
 * The daily sweep (payments:separation-hold) runs hold() for everyone with a separation date.
 */
class SeparationHold
{
    public const REMARK = 'Payment hold - separation date entered; payable with F&F (auto generated)';

    public function __construct(private string $connection, private int $yearId, private int $companyId = 1)
    {
    }

    /** Separation date (Y-m-d) of the employee, or null. */
    public function separationDate(int $employeeId): ?string
    {
        try {
            $d = DB::connection($this->hrims())->table('hrm_employee')->where('EmployeeID', $employeeId)->value('DateOfSepration');
        } catch (\Throwable $e) {
            return null;
        }

        return ($d && (string) $d > '2000-01-01') ? substr((string) $d, 0, 10) : null;
    }

    /** Null when the month may be paid, else the message (and the month is put on hold). */
    public function blockReason(int $employeeId, int $month): ?string
    {
        if (! config('claim_rules.separation_payment_hold', true)) {
            return null;
        }
        $sep = $this->separationDate($employeeId);
        if (! $sep) {
            return null;
        }
        $this->hold($employeeId, $sep);

        return 'Payment is on hold: the employee\'s separation date ('.date('d-m-Y', strtotime($sep)).') is entered, so this month is payable with the F&F settlement. Link the F&F on Expense Payments > No-UTR Reasons once it is paid.';
    }

    /** Put the employee's unpaid months on F&F hold. Returns how many were changed. */
    public function hold(int $employeeId, ?string $sep = null): int
    {
        $sep = $sep ?: $this->separationDate($employeeId);
        if (! $sep) {
            return 0;
        }
        $table = "y{$this->yearId}_monthexpensefinal";
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return 0;
        }
        $cols = array_flip(Schema::connection($this->connection)->getColumnListing($table));
        $rows = DB::connection($this->connection)->table($table)->where('EmployeeID', $employeeId)
            ->whereIn('payment_status', ['Pending', 'Approved'])
            ->where(function ($q) {
                $q->whereNull('Fin_PayDate')->orWhere('Fin_PayDate', '<', '2000-01-02');
            })
            ->get();
        $n = 0;
        foreach ($rows as $r) {
            $upd = [];
            if (isset($cols['is_exported']) && (int) ($r->is_exported ?? 0) !== 2) {
                $upd['is_exported'] = 2;
            }
            if (isset($cols['hold_remark']) && trim((string) ($r->hold_remark ?? '')) === '') {
                $upd['hold_remark'] = self::REMARK.' ('.date('d-m-Y', strtotime($sep)).')';
            }
            if (isset($cols['utr_exempt_reason']) && $r->utr_exempt_reason === null) {
                $upd['utr_exempt_reason'] = 'ff_hold';
            }
            if (! $upd) {
                continue;
            }
            $before = \App\Support\PaymentChangeLog::snapshot($this->connection, $table, [$r->id]);
            DB::connection($this->connection)->table($table)->where('id', $r->id)->update($upd);
            \App\Support\PaymentChangeLog::diff($this->connection, $table, $before, 'month_final', 'separation_hold', 'Separation date '.$sep);
            $n++;
        }
        if ($n) {
            Log::info('FF-01 separation hold', ['conn' => $this->connection, 'year' => $this->yearId, 'emp' => $employeeId, 'months' => $n, 'separation' => $sep]);
        }

        return $n;
    }

    /** Daily sweep: everyone with a separation date and unpaid months. */
    public function sweep(): array
    {
        $table = "y{$this->yearId}_monthexpensefinal";
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return ['employees' => 0, 'months' => 0];
        }
        $emps = DB::connection($this->connection)->table($table)->whereIn('payment_status', ['Pending', 'Approved'])
            ->distinct()->pluck('EmployeeID')->map(fn ($x) => (int) $x)->all();
        $separated = [];
        foreach (array_chunk($emps, 1000) as $chunk) {
            foreach (DB::connection($this->hrims())->table('hrm_employee')->whereIn('EmployeeID', $chunk)->where('DateOfSepration', '>', '2000-01-01')->get(['EmployeeID', 'DateOfSepration']) as $e) {
                $separated[(int) $e->EmployeeID] = substr((string) $e->DateOfSepration, 0, 10);
            }
        }
        $months = 0;
        foreach ($separated as $emp => $sep) {
            $months += $this->hold($emp, $sep);
        }

        return ['employees' => count($separated), 'months' => $months];
    }

    private function hrims(): string
    {
        return (string) (session('hrims_connection') ?: DatabaseSwitcher::getHrimsConnection($this->companyId) ?: 'hrims');
    }
}
