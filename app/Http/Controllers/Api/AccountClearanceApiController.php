<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ExpensePaymentController;
use App\Support\CurrentYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CLEAR-11 (tracker #11): account clearance for an employee leaving or moving
 * (full & final). One answer to "does Xeasy owe this employee, or the employee owe us?".
 *
 *   GET /api/account-clearance/{employeeId}?company_id=1
 *
 * Needs login like every /api route (SEC-01): web session, app token or X-Api-Key.
 *
 * Returns:
 *  - ledger: imprest held, advance balance, total balance (the same ledger engine as the
 *    Ledger Summary screen and /api/ledger-summary)
 *  - unpaid_months: claim months of the current year that are not yet paid, with stage and amounts
 *  - unsettled_advances: paid advances not yet adjusted
 *  - clearance: CLEAR when nothing is open and the balance is 0, else PENDING with the reasons
 */
class AccountClearanceApiController extends Controller
{
    public function show(Request $request, int $employeeId): JsonResponse
    {
        try {
            $companyId = (int) $request->input('company_id', session('company_id', 1));
            $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
            $yearId = (int) ($request->input('year_id') ?: CurrentYear::id($conn));
            $hrimsConn = session('hrims_connection', 'hrims');

            $emp = DB::connection($hrimsConn)->table('hrm_employee')->where('EmployeeID', $employeeId)
                ->first(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname', 'EmpStatus', 'CompanyId', 'DateOfSepration', 'DateOfResignation']);
            if (! $emp) {
                return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
            }

            $ledger = app(ExpensePaymentController::class)->computeBatchEmployeeLedgerBalances([$employeeId])[$employeeId]
                ?? ['total_imprest' => 0.0, 'advance_balance' => 0.0, 'total_balance' => 0.0];

            $months = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
                ->where('EmployeeID', $employeeId)->where('YearId', $yearId)
                ->orderBy('Month')
                ->get(['Month', 'Status', 'Claim_Amount', 'Verified_Amount', 'Approved_Amount', 'Finance_Amount', 'Fin_PayAmt', 'Fin_PayDate', 'DateOfSubmit']);
            $claimTotals = DB::connection($conn)->table("y{$yearId}_expenseclaims")
                ->where('CrBy', $employeeId)->where('ClaimStatus', 'not like', 'Deactiv%')
                ->groupBy('ClaimMonth')
                ->get([DB::raw('ClaimMonth as Month'), DB::raw('COUNT(*) as claims'), DB::raw('SUM(FilledTAmt) as claimed')])
                ->keyBy('Month');

            $empty = fn ($d) => empty($d) || str_starts_with((string) $d, '0000');
            $unpaid = [];
            foreach ($months as $m) {
                $claims = (int) ($claimTotals[$m->Month]->claims ?? 0);
                if ($claims === 0 || ! $empty($m->Fin_PayDate)) {
                    continue;
                }
                $unpaid[] = [
                    'month' => (int) $m->Month,
                    'status' => $m->Status,
                    'submitted' => ! $empty($m->DateOfSubmit),
                    'claims' => $claims,
                    'claimed_amount' => (float) ($claimTotals[$m->Month]->claimed ?? 0),
                    'approved_amount' => (float) ($m->Approved_Amount ?? 0),
                    'finance_amount' => (float) ($m->Finance_Amount ?? 0),
                ];
            }
            // claim months with no month record (STAGE-01) are open too
            foreach ($claimTotals as $month => $t) {
                if (! $months->contains('Month', $month) && (int) $month > 0) {
                    $unpaid[] = ['month' => (int) $month, 'status' => 'Open', 'submitted' => false, 'claims' => (int) $t->claims,
                        'claimed_amount' => (float) $t->claimed, 'approved_amount' => 0.0, 'finance_amount' => 0.0];
                }
            }

            $unsettled = [];
            try {
                $unsettled = DB::connection($conn)->table('advance_request_activities as ara')
                    ->join('advance_request_employees as are', 'are.id', '=', 'ara.advance_request_employee_id')
                    ->where('are.employee_id', $employeeId)
                    ->where('ara.payment_status', 'Paid')->whereNull('ara.deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('ara.remaining_balance')->orWhere('ara.remaining_balance', '>', 0);
                    })
                    ->whereNull('ara.adjustment_date')
                    ->orderBy('ara.payment_date')
                    ->get(['ara.id', 'ara.payment_amount', 'ara.payment_date', 'ara.remaining_balance', 'ara.is_imprest', 'ara.utr_number'])
                    ->map(fn ($r) => ['id' => $r->id, 'paid' => (float) $r->payment_amount, 'paid_on' => $r->payment_date,
                        'remaining' => (float) ($r->remaining_balance ?? $r->payment_amount), 'imprest' => (bool) $r->is_imprest, 'utr' => $r->utr_number])
                    ->values()->all();
            } catch (\Throwable $e) {
                Log::warning('Account clearance: advance tables not available: '.$e->getMessage());
            }

            $reasons = [];
            if ($unpaid) {
                $reasons[] = count($unpaid).' claim month(s) not yet paid';
            }
            if (abs((float) $ledger['total_balance']) >= 1) {
                $reasons[] = 'ledger balance '.number_format((float) $ledger['total_balance'], 2).' (imprest '.number_format((float) $ledger['total_imprest'], 2)
                    .', advances '.number_format((float) $ledger['advance_balance'], 2).')';
            }
            if ($unsettled) {
                $reasons[] = count($unsettled).' paid advance(s) not yet adjusted';
            }

            return response()->json([
                'success' => true,
                'employee' => [
                    'employee_id' => (int) $emp->EmployeeID,
                    'emp_code' => $emp->EmpCode,
                    'name' => trim(implode(' ', array_filter([$emp->Fname, $emp->Sname, $emp->Lname]))),
                    'status' => $emp->EmpStatus,
                    'company_id' => (int) $emp->CompanyId,
                    'separation_date' => $empty($emp->DateOfSepration) ? null : $emp->DateOfSepration,
                    'resignation_date' => $empty($emp->DateOfResignation) ? null : $emp->DateOfResignation,
                ],
                'year_id' => $yearId,
                'ledger' => [
                    'imprest_held' => round((float) $ledger['total_imprest'], 2),
                    'advance_balance' => round((float) $ledger['advance_balance'], 2),
                    'total_balance' => round((float) $ledger['total_balance'], 2),
                ],
                'unpaid_months' => array_values(collect($unpaid)->sortBy('month')->all()),
                'unsettled_advances' => $unsettled,
                'clearance' => ['status' => $reasons ? 'PENDING' : 'CLEAR', 'reasons' => $reasons],
                'as_of' => now('Asia/Kolkata')->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Account clearance failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not build the clearance'], 500);
        }
    }
}
