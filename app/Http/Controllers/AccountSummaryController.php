<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountSummaryController extends Controller
{
    public function index(Request $request)
    {
        $employeeId   = (int) $request->get('e');
        $currentMonth = (int) $request->get('m', date('n'));
        $conn         = session('expense_connection') ?: 'expense';
        $yearId       = (int) $request->get('y', session('year_id') ?: 8);

        if (!session()->has('year_id') || $request->has('y')) {
            session(['year_id' => $yearId]);
        }
        if (!session()->has('expense_connection')) {
            session(['expense_connection' => $conn]);
        }

        if ($employeeId == 0) {
            abort(400, 'Employee ID is required');
        }

        /* ================= MONTH EXPENSE FINAL ================= */
        $expenseData = DB::connection($conn)
            ->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', $employeeId)
            ->orderByDesc('DateOfSubmit')
            ->get();

        /* ================= LEDGER BALANCE ================= */
        $ledger = DB::connection($conn)
            ->table('employee_ledger_balances')
            ->where('employee_id', $employeeId)
            ->where('year_id', $yearId)
            ->orderByDesc('id')
            ->first();

        if (!$ledger) {
            $ledger = DB::connection($conn)
                ->table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->orderByDesc('year_id')
                ->orderByDesc('id')
                ->first();
        }

        $currentLedgerBalance = (float) ($ledger->balance ?? 0);

        /* ================= FIXED IMPREST ================= */
        $fixedImprestRows = DB::connection($conn)
            ->table('employee_ledger_balances')
            ->where('employee_id', $employeeId)
            ->where('year_id', $yearId)
            ->where(function ($q) {
                $q->where('fixed_imprest_amt_1', '>', 0)
                  ->orWhere('fixed_imprest_amt_2', '>', 0);
            })
            ->orderByDesc('fixed_imprest_date_1')
            ->get();

        if ($fixedImprestRows->isEmpty()) {
            $fixedImprestRows = DB::connection($conn)
                ->table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->where(function ($q) {
                    $q->where('fixed_imprest_amt_1', '>', 0)
                      ->orWhere('fixed_imprest_amt_2', '>', 0);
                })
                ->orderByDesc('year_id')
                ->orderByDesc('id')
                ->get();
        }

        /* ================= DA PER MONTH ================= */
        $daDataByMonth = [];
        for ($month = 1; $month <= 12; $month++) {
            $da = DB::connection($conn)
                ->table("y{$yearId}_expenseclaims as ec")
                ->where('ec.ClaimMonth', $month)
                ->where('ec.CrBy', $employeeId)
                ->where('ec.ClaimStatus', '!=', 'Deactivate')
                ->where('ec.FinancedTAmt', '>', 0)
                ->whereIn('ec.ClaimId', [19, 20])
                ->sum('ec.FinancedTAmt');
            
            $daDataByMonth[$month] = $da ? (float) $da : 0;
        }

        /* ================= ALL ADVANCES FOR THIS EMPLOYEE ================= */
        $employeeAdvances = DB::connection($conn)
            ->table('advance_request_activities as ara')
            ->join('advance_request_employees as are', 'ara.advance_request_employee_id', '=', 'are.id')
            ->join('advance_requests as ar', 'are.advance_request_id', '=', 'ar.id')
            ->leftJoin('adv_activity_types as aat', 'ara.activity_type_id', '=', 'aat.id')
            ->where('are.employee_id', $employeeId)
            ->whereIn('ara.payment_status', ['Paid', 'Unsettled'])
            // Exclude fixed imprest advances
            ->where(function ($q) {
                $q->where('ara.is_imprest', 0)->orWhereNull('ara.is_imprest');
            })
            ->where(function ($q) {
                $q->where('ar.is_imprest', 0)->orWhereNull('ar.is_imprest');
            })
            ->where(function ($q) {
                $q->where('ara.purpose', 'NOT LIKE', '%imprest%')
                  ->orWhereNull('ara.purpose');
            })
            ->whereNull('ar.deleted_at')
            ->whereNull('are.deleted_at')
            ->whereNull('ara.deleted_at')
            ->select([
                'ara.id as activity_id',
                'are.advance_request_id as request_id',
                'are.employee_id',
                'ara.amount',
                'ara.amount as requested_amount',
                'ara.payment_amount',
                'ara.payment_date',
                'ara.payment_status',
                'ara.adjustment_amount',
                'ara.adjustment_date',
                'ara.adjustment_remarks',
                'ara.remaining_balance',
                'ara.adjusted_against_month',
                'ara.purpose',
                'ara.utr_number',
                'ara.is_imprest',
                'ar.is_imprest as is_req_imprest',
                'aat.type_name as activity_type',
                'ar.month as request_month',
                'ar.year_id',
                'ara.created_at',
                'ara.created_at as activity_created_at',
            ])
            ->orderBy(DB::raw("COALESCE(ara.payment_date, ara.created_at)"))
            ->orderBy('ara.id')
            ->get();

        // Infer financial year_id accurately from payment date
        foreach ($employeeAdvances as $adv) {
            $d = $adv->payment_date ?: ($adv->created_at ? substr($adv->created_at, 0, 10) : null);
            if ($d && \App\Support\ZeroDate::has($d)) {
                $cYear = (int) date('Y', strtotime($d));
                $cMonth = (int) date('n', strtotime($d));
                $fyStart = ($cMonth >= 4) ? $cYear : ($cYear - 1);
                $adv->year_id = max(1, $fyStart - 2018);
            } elseif (empty($adv->year_id)) {
                $adv->year_id = $yearId;
            }
        }

        // Reconcile with claim deductions using the Golden Reference AdvanceAdjustmentReportController
        $aarController = app(\App\Http\Controllers\AdvanceAdjustmentReportController::class);
        $employeeAdvances = $aarController->reconcileWithClaims($employeeAdvances, $conn);

        // Apply reconciled values back to $employeeAdvances and calculate KPI totals
        $totalAdvancesPaid = 0.0;
        $totalAdvancesAdjusted = 0.0;
        $totalUnadjustedAdvance = 0.0;
        $advancesByMonth = [];
        $displayAdvances = [];

        foreach ($employeeAdvances as $adv) {
            $adv->is_ledger_opening = $adv->is_ledger_opening ?? 0;
            $adv->adjustment_amount = $adv->reconciled_adjusted ?? ($adv->adjustment_amount ?? 0);
            $adv->remaining_balance = $adv->reconciled_remaining ?? ($adv->remaining_balance ?? 0);
            $adv->adjustments = [];
            if (!empty($adv->claim_settlements) && is_array($adv->claim_settlements)) {
                foreach ($adv->claim_settlements as $cs) {
                    $adv->adjustments[] = [
                        'year' => (int) ($cs['year'] ?? 0),
                        'month' => (int) ($cs['month'] ?? 0),
                        'amount' => (float) ($cs['adjusted'] ?? 0),
                    ];
                }
            }

            $isPaid = strtolower(trim($adv->payment_status ?? '')) === 'paid';
            $pAmt = (float) ($adv->reconciled_paid ?? ($adv->payment_amount ?: $adv->amount));
            $aAmt = (float) ($adv->reconciled_adjusted ?? ($adv->adjustment_amount ?? 0));
            $rBal = (float) ($adv->reconciled_remaining ?? (($adv->remaining_balance !== null && $adv->remaining_balance !== '')
                ? (float) $adv->remaining_balance
                : max(0.0, $pAmt - $aAmt)));

            if (empty($adv->is_ledger_opening) && $isPaid) {
                $totalAdvancesPaid += $pAmt;
                $totalAdvancesAdjusted += $aAmt;
                $totalUnadjustedAdvance += $rBal;
            }

            $displayAdvances[] = $adv;

            // Exclude opening ledger balances from advancesByMonth (they appear in Opening Balance / Carried)
            // AND ensure advance belongs to THIS financial year and is PAID!
            $isThisYear = (int) ($adv->year_id ?? 0) === (int) $yearId;
            if (empty($adv->is_ledger_opening) && $isPaid && $isThisYear) {
                $pDate = $adv->payment_date ?: ($adv->created_at ? substr($adv->created_at, 0, 10) : null);
                $m = $pDate ? (int) date('n', strtotime($pDate)) : (int) $adv->request_month;
                if ($m > 0) {
                    if (!isset($advancesByMonth[$m])) {
                        $advancesByMonth[$m] = [];
                    }
                    $advancesByMonth[$m][] = $adv;
                }
            }
        }

        $employeeAdvances = collect($displayAdvances);

        return view('expense-payments.account-summary', compact(
            'expenseData',
            'daDataByMonth',
            'fixedImprestRows',
            'currentLedgerBalance',
            'employeeId',
            'yearId',
            'employeeAdvances',
            'advancesByMonth',
            'totalAdvancesPaid',
            'totalAdvancesAdjusted',
            'totalUnadjustedAdvance'
        ));
    }
}