<?php

namespace App\Services\Fnf;

use App\Helpers\DatabaseSwitcher;
use App\Support\CompanyYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FNF-01 (20 Nov 2026, tracker tab "FNF_Xeasy 20 Nov", sections 7-8): employee-wise Finance clearance working for the
 * Full & Final settlement.
 *
 *   Final balance = - ledger balance (advance / imprest the employee still holds = recoverable)
 *                   + expense balance (payment hold + eligible pending expenses = payable)
 *                   + HR recovery / payment (as entered: + payable to the employee, - recoverable)
 *   Positive = payable to the employee, negative = to recover from the employee.
 *
 * Expense balance (section 8):
 *  Rule 1  months on payment hold (is_exported = 2, the hold made after resignation) - their payable amount;
 *  Rule 2  months not yet approved for payment, by claim:
 *     A. odometer claims of up to 6 days in a month: eligible without the courier date;
 *     B. odometer claims of more days (the sheet says "more than 10"; 7-10 days are treated the same): eligible only
 *        when the approver has approved them;
 *     C. all other claims: eligible only when the hard copy is received (month's receiving date). Up to 3 days after
 *        the last working day they are shown as "waiting for hard copy" and not counted; after that they are left out.
 *     Amount of a claim: approved, else verified, else claimed. Denied / deactivated claims are not counted.
 *  Split: current month (month of the last working day), previous month, other pending months.
 */
class FnfCalculator
{
    public const ODO_CLAIM_ID = 7;

    public const ODO_FREE_DAYS = 6;      // rule A

    public const HARD_COPY_WAIT_DAYS = 3; // rule C

    /** @return array working for one employee (see keys at the end) */
    public static function forEmployee(object $emp, ?float $ledgerBalance = null): array
    {
        $companyId = (int) ($emp->CompanyId ?? 1);
        $conn = DatabaseSwitcher::getExpenseConnection($companyId) ?: 'expense';
        $lwd = self::day($emp->DateOfSepration ?? null) ?: now()->toDateString();
        $lwdC = Carbon::parse($lwd);
        $curM = (int) $lwdC->month;
        $prevM = (int) $lwdC->copy()->subMonthNoOverflow()->month;
        $waitUntil = $lwdC->copy()->addDays(self::HARD_COPY_WAIT_DAYS);

        $years = array_values(array_unique(array_filter([
            CompanyYear::containing($conn, $lwd),
            CompanyYear::containing($conn, $lwdC->copy()->subMonthNoOverflow()->toDateString()),
            CompanyYear::containing($conn, $lwdC->copy()->subYear()->toDateString()),
        ])));

        $hold = [];
        $months = [];
        foreach ($years as $y) {
            $mefT = "y{$y}_monthexpensefinal";
            $ecT = "y{$y}_expenseclaims";
            if (! Schema::connection($conn)->hasTable($mefT) || ! Schema::connection($conn)->hasTable($ecT)) {
                continue;
            }
            $mefs = DB::connection($conn)->table($mefT)->where('EmployeeID', (int) $emp->EmployeeID)->get()->keyBy(fn ($r) => (int) $r->Month);
            $claimMonths = DB::connection($conn)->table($ecT)->where('CrBy', (int) $emp->EmployeeID)->where('ClaimStatus', '!=', 'Deactivate')
                ->distinct()->pluck('ClaimMonth')->map(fn ($m) => (int) $m)->all();
            foreach (array_unique(array_merge($mefs->keys()->all(), $claimMonths)) as $m) {
                if ($m < 1 || $m > 12) {
                    continue;
                }
                $mef = $mefs->get($m);
                $label = self::monthLabel($conn, $y, $m);
                // already paid (or settled without payment) - nothing due
                if ($mef && \App\Support\Paid::is($mef)) {
                    continue;
                }
                // Rule 1: on payment hold
                if ($mef && (int) ($mef->is_exported ?? 0) === 2) {
                    $amt = (float) ($mef->payment_amount ?: $mef->actual_expense_amount ?: $mef->Finance_Amount ?: $mef->Approved_Amount ?: 0);
                    $hold[] = ['year' => $y, 'month' => $m, 'label' => $label, 'amount' => round($amt, 2), 'remark' => (string) ($mef->hold_remark ?? '')];
                    continue;
                }
                // approved for payment (instructed / pending at Treasury) but not held: counted as payable, shown apart
                $approved = $mef && in_array((string) ($mef->payment_status ?? ''), ['Approved', 'Instructed', 'Pending'], true)
                    && ((float) ($mef->Finance_Amount ?? 0) > 0 || (float) ($mef->Approved_Amount ?? 0) > 0) && \App\Support\ZeroDate::has($mef->Approved_Date ?? null);
                $months[] = self::monthWorking($conn, $ecT, $emp, $y, $m, $label, $mef, $approved, $lwd, $waitUntil)
                    + ['bucket' => $m === $curM && $y === ($years[0] ?? $y) ? 'current' : ($m === $prevM ? 'previous' : 'other')];
            }
        }
        usort($hold, fn ($a, $b) => [$b['year'], $b['month']] <=> [$a['year'], $a['month']]);
        usort($months, fn ($a, $b) => [$b['year'], $b['month']] <=> [$a['year'], $a['month']]);

        $sum = fn (string $bucket) => round(collect($months)->where('bucket', $bucket)->sum('eligible'), 2);
        $holdAmt = round(collect($hold)->sum('amount'), 2);
        $cur = $sum('current');
        $prev = $sum('previous');
        $other = $sum('other');
        $expense = round($holdAmt + $cur + $prev + $other, 2);
        $ledger = round((float) ($ledgerBalance ?? self::ledgerBalance((int) $emp->EmployeeID, $conn)), 2);

        return [
            'ledger_balance' => $ledger,            // held by the employee (recoverable)
            'hold_amount' => $holdAmt,
            'hold' => $hold,
            'expense_current' => $cur,
            'expense_previous' => $prev,
            'expense_other' => $other,
            'expense_total' => $expense,           // Rule 3 line 6
            'months' => $months,
            'waiting' => round(collect($months)->sum('waiting'), 2),
            'excluded' => round(collect($months)->sum('excluded'), 2),
            'lwd' => $lwd,
            'hard_copy_until' => $waitUntil->toDateString(),
        ];
    }

    /** Final balance: + payable to the employee, - to recover. */
    public static function finalBalance(float $ledger, float $expense, float $hr): float
    {
        return round($expense - $ledger + $hr, 2);
    }

    private static function monthWorking(string $conn, string $ecT, object $emp, int $y, int $m, string $label, ?object $mef, bool $approved, string $lwd, Carbon $waitUntil): array
    {
        $claims = DB::connection($conn)->table($ecT)->where('CrBy', (int) $emp->EmployeeID)->where('ClaimMonth', $m)
            ->where('ClaimStatus', '!=', 'Deactivate')->where(fn ($q) => $q->whereNull('FilledOkay')->orWhere('FilledOkay', '!=', 2))
            ->get(['ExpId', 'ClaimId', 'BillDate', 'FilledTAmt', 'VerifyTAmt', 'ApprTAmt', 'ApprBy', 'App_Odometer', 'ClaimStatus']);
        $amt = function ($c) {
            foreach (['ApprTAmt', 'VerifyTAmt', 'FilledTAmt'] as $k) {
                if ((float) ($c->{$k} ?? 0) > 0) {
                    return (float) $c->{$k};
                }
            }

            return 0.0;
        };
        $received = $mef && \App\Support\ZeroDate::has($mef->RecevingDate ?? null);
        $mgrApproved = ($mef && \App\Support\ZeroDate::has($mef->Approved_Date ?? null));
        $odo = $claims->where('ClaimId', self::ODO_CLAIM_ID);
        $other = $claims->where('ClaimId', '!=', self::ODO_CLAIM_ID);
        $odoDays = $odo->pluck('BillDate')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->count();
        $odoAmt = round($odo->sum($amt), 2);
        $otherAmt = round($other->sum($amt), 2);
        $odoApproved = $mgrApproved || ($odo->count() > 0 && $odo->every(fn ($c) => (int) ($c->ApprBy ?? 0) > 0 && (float) ($c->ApprTAmt ?? 0) > 0));

        $eligible = 0.0;
        $waiting = 0.0;
        $excluded = 0.0;
        $notes = [];
        if ($approved) {
            $eligible = (float) ($mef->Finance_Amount ?: $mef->Approved_Amount);
            $notes[] = 'Approved for payment, not paid';
        } else {
            if ($odoAmt > 0) {
                if ($odoDays <= self::ODO_FREE_DAYS) {
                    $eligible += $odoAmt;
                    $notes[] = "Odometer {$odoDays} day(s): eligible without courier (rule A)";
                } elseif ($odoApproved) {
                    $eligible += $odoAmt;
                    $notes[] = "Odometer {$odoDays} days: approved by the approver (rule B)";
                } else {
                    $excluded += $odoAmt;
                    $notes[] = "Odometer {$odoDays} days: needs the approver's approval (rule B)";
                }
            }
            if ($otherAmt > 0) {
                if ($received) {
                    $eligible += $otherAmt;
                    $notes[] = 'Other claims: hard copy received (rule C)';
                } elseif (now()->lte($waitUntil->copy()->endOfDay())) {
                    $waiting += $otherAmt;
                    $notes[] = 'Other claims: waiting for the hard copy until '.$waitUntil->format('d-m-Y').' (rule C)';
                } else {
                    $excluded += $otherAmt;
                    $notes[] = 'Other claims: hard copy not received in '.self::HARD_COPY_WAIT_DAYS.' days - left out (rule C)';
                }
            }
        }

        return [
            'year' => $y, 'month' => $m, 'label' => $label,
            'status' => self::monthStatus($mef, $claims->count()),
            'claims' => $claims->count(), 'odo_days' => $odoDays, 'odo_amount' => $odoAmt, 'other_amount' => $otherAmt,
            'eligible' => round($eligible, 2), 'waiting' => round($waiting, 2), 'excluded' => round($excluded, 2),
            'notes' => $notes,
        ];
    }

    /** Plain status of an employee's month (also used in the separation reminder mail). */
    public static function monthStatus(?object $mef, int $claimCount = 0): string
    {
        if ($mef && \App\Support\Paid::is($mef)) {
            return 'Paid';
        }
        if ($mef && (int) ($mef->is_exported ?? 0) === 2) {
            return 'Approved - payment on hold (F&F)';
        }
        $has = fn ($k) => $mef && \App\Support\ZeroDate::has($mef->{$k} ?? null);
        if ($has('Finance_Date')) {
            return 'Approved - payment pending';
        }
        if ($has('Approved_Date')) {
            return 'Approved - pending with Finance';
        }
        if ($has('Verified_Date')) {
            return 'Pending with Manager';
        }
        if ($has('RecevingDate')) {
            return 'Pending verification';
        }
        if ($has('DateOfSubmit') && ($mef->Status ?? '') === 'Closed') {
            return 'Submitted - hard copy not received';
        }

        return $claimCount > 0 ? 'Not submitted' : 'No claims yet';
    }

    public static function ledgerBalance(int $employeeId, string $conn): float
    {
        try {
            $prev = session('expense_connection');
            session(['expense_connection' => $conn]);
            $b = app(\App\Http\Controllers\ExpensePaymentController::class)->computeBatchEmployeeLedgerBalances([$employeeId]);
            session(['expense_connection' => $prev]);

            return (float) ($b[$employeeId]['total_balance'] ?? 0);
        } catch (\Throwable $e) {
            report($e);

            return 0.0;
        }
    }

    private static function monthLabel(string $conn, int $y, int $m): string
    {
        static $fy = [];
        $key = "{$conn}:{$y}";
        if (! isset($fy[$key])) {
            $fy[$key] = (string) DB::connection($conn)->table('financialyear')->where('YearId', $y)->value('FromDate');
        }
        $start = $fy[$key] ? (int) substr($fy[$key], 0, 4) : (int) date('Y');

        return date('F', mktime(0, 0, 0, $m, 1)).' '.($m >= 4 ? $start : $start + 1);
    }

    private static function day($v): ?string
    {
        return ($v && (string) $v > '2000-01-01') ? substr((string) $v, 0, 10) : null;
    }
}
