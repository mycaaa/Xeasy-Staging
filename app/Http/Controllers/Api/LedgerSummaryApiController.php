<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerSummaryApiController extends Controller
{
    /**
     * Get ledger summary for all employees (paginated).
     * 
     * GET /api/ledger-summary
     * 
     * Query params:
     *   - search        : filter by employee name or code
     *   - status_filter : 'active' | 'inactive'
     *   - company_id    : filter by company
     *   - emp_type      : filter by employee type
     *   - balance_filter: 'positive' | 'negative' | 'zero'
     *   - sort_field    : 'employee_code' | 'balance' | 'closing_balance' | 'employee_name'
     *   - sort_order    : 'asc' | 'desc'
     *   - per_page      : records per page (default 20)
     *   - page          : page number
     */
    public function index(Request $request)
    {
        try {
            $expConn   = session('expense_connection') ?: 'expense';
            $companyId = session('company_id', 1);

            $query = DB::table('hrims.hrm_employee as e')
                ->leftJoin(\App\Helpers\DatabaseSwitcher::getExpenseDatabaseName($expConn).'.employee_ledger_balances as lb', 'e.EmployeeID', '=', 'lb.employee_id')
                ->select(
                    'e.EmployeeID as employee_id',
                    'e.Fname',
                    'e.Sname',
                    'e.Lname',
                    'e.EmpCode',
                    'e.EmpCode_New',
                    'e.ECode',
                    'e.EmpType',
                    'e.EmpStatus',
                    'e.CompanyId',
                    DB::raw("CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as employee_name"),
                    DB::raw('COALESCE(e.EmpCode, e.EmpCode_New, e.ECode) as display_code'),
                    DB::raw('MAX(lb.fixed_imprest_amt_1) as fixed_imprest_amt_1'),
                    DB::raw('MAX(lb.fixed_imprest_date_1) as fixed_imprest_date_1'),
                    DB::raw('MAX(lb.fixed_imprest_amt_2) as fixed_imprest_amt_2'),
                    DB::raw('MAX(lb.fixed_imprest_date_2) as fixed_imprest_date_2'),
                    DB::raw('MAX(lb.advance_amt_1) as advance_amt_1'),
                    DB::raw('MAX(lb.advance_date_1) as advance_date_1'),
                    DB::raw('MAX(lb.advance_month_1) as advance_month_1'),
                    DB::raw('MAX(lb.advance_purpose_1) as advance_purpose_1'),
                    DB::raw('MAX(lb.advance_amt_2) as advance_amt_2'),
                    DB::raw('MAX(lb.advance_date_2) as advance_date_2'),
                    DB::raw('MAX(lb.advance_month_2) as advance_month_2'),
                    DB::raw('MAX(lb.advance_purpose_2) as advance_purpose_2'),
                    DB::raw('MAX(lb.advance_amt_3) as advance_amt_3'),
                    DB::raw('MAX(lb.advance_date_3) as advance_date_3'),
                    DB::raw('MAX(lb.advance_month_3) as advance_month_3'),
                    DB::raw('MAX(lb.advance_purpose_3) as advance_purpose_3'),
                    DB::raw('MAX(lb.advance_amt_4) as advance_amt_4'),
                    DB::raw('MAX(lb.advance_date_4) as advance_date_4'),
                    DB::raw('MAX(lb.advance_month_4) as advance_month_4'),
                    DB::raw('MAX(lb.advance_purpose_4) as advance_purpose_4'),
                    DB::raw('SUM(COALESCE(lb.dr, 0)) as total_debit'),
                    DB::raw('SUM(COALESCE(lb.cr, 0)) as total_credit'),
                    DB::raw('SUM(COALESCE(lb.balance, 0)) as current_balance'),
                    DB::raw('SUM(COALESCE(lb.closing_balance, 0)) as closing_balance'),
                    DB::raw('MAX(lb.remaining_balances_json) as remaining_balances_json'),
                    DB::raw('MAX(lb.remark) as remark'),
                    DB::raw('MAX(lb.updated_at) as last_updated')
                )
                ->where('e.EmpStatus', '!=', 'D')
                ->where('e.CompanyId', '=', $companyId)
                ->where('e.EmpCode', '>', '0')
                ->groupBy(
                    'e.EmployeeID', 'e.Fname', 'e.Sname', 'e.Lname',
                    'e.EmpCode', 'e.EmpCode_New', 'e.ECode',
                    'e.EmpType', 'e.EmpStatus', 'e.CompanyId'
                );

            // Filters
            if ($request->filled('status_filter')) {
                if ($request->status_filter == 'active') {
                    $query->where('e.EmpStatus', 'A');
                } elseif ($request->status_filter == 'inactive') {
                    $query->where('e.EmpStatus', '!=', 'A');
                }
            }

            if ($request->filled('company_id')) {
                $query->where('e.CompanyId', $request->company_id);
            }

            if ($request->filled('emp_type')) {
                $query->where('e.EmpType', $request->emp_type);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw("CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))"), 'LIKE', "%{$search}%")
                      ->orWhere('e.EmpCode', 'LIKE', "%{$search}%")
                      ->orWhere('e.EmpCode_New', 'LIKE', "%{$search}%")
                      ->orWhere('e.ECode', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('balance_filter')) {
                // Filter on the computed balance returned as computed_balance (same as the web screen).
                app(\App\Http\Controllers\ExpensePaymentController::class)
                    ->applyComputedBalanceFilter($query, (string) $request->balance_filter);
            }

            // Sorting
            $sortField = $request->get('sort_field', 'EmpCode');
            $sortOrder = $request->get('sort_order', 'asc');

            switch ($sortField) {
                case 'employee_code':
                    $query->orderBy('e.EmpCode', $sortOrder);
                    break;
                case 'balance':
                    $query->orderBy('lb.balance', $sortOrder);
                    break;
                case 'closing_balance':
                    $query->orderBy('lb.closing_balance', $sortOrder);
                    break;
                case 'employee_name':
                default:
                    $query->orderBy('e.Fname', $sortOrder)
                          ->orderBy('e.Sname', $sortOrder)
                          ->orderBy('e.Lname', $sortOrder);
                    break;
            }

            $perPage = $request->get('per_page', 20);
            $ledgerRecords = $query->paginate($perPage);

            // Compute real balance for each employee matching account summary / advance report
            $empIds = collect($ledgerRecords->items())->pluck('employee_id')->filter()->unique()->values()->all();
            $controller = app(\App\Http\Controllers\ExpensePaymentController::class);
            $balancesMap = $controller->computeBatchEmployeeLedgerBalances($empIds);

            $data = collect($ledgerRecords->items())->map(function ($record) use ($balancesMap) {
                $balance = $balancesMap[$record->employee_id] ?? [
                    'total_imprest'   => 0.0,
                    'advance_balance' => 0.0,
                    'total_balance'   => 0.0,
                ];

                return [
                    'employee_id'   => $record->employee_id,
                    'employee_name' => $record->employee_name,
                    'display_code'  => $record->display_code,
                    'emp_type'      => $record->EmpType,
                    'emp_status'    => $record->EmpStatus,
                    'fixed_imprest' => [
                        'amt_1'  => $record->fixed_imprest_amt_1,
                        'date_1' => $record->fixed_imprest_date_1,
                        'amt_2'  => $record->fixed_imprest_amt_2,
                        'date_2' => $record->fixed_imprest_date_2,
                    ],
                    'advances' => [
                        ['amount' => $record->advance_amt_1, 'date' => $record->advance_date_1, 'month' => $record->advance_month_1, 'purpose' => $record->advance_purpose_1],
                        ['amount' => $record->advance_amt_2, 'date' => $record->advance_date_2, 'month' => $record->advance_month_2, 'purpose' => $record->advance_purpose_2],
                        ['amount' => $record->advance_amt_3, 'date' => $record->advance_date_3, 'month' => $record->advance_month_3, 'purpose' => $record->advance_purpose_3],
                        ['amount' => $record->advance_amt_4, 'date' => $record->advance_date_4, 'month' => $record->advance_month_4, 'purpose' => $record->advance_purpose_4],
                    ],
                    'total_debit'      => $record->total_debit,
                    'total_credit'     => $record->total_credit,
                    'current_balance'  => $record->current_balance,
                    'closing_balance'  => $record->closing_balance,
                    'computed_balance' => $balance,
                    'remark'           => $record->remark,
                    'last_updated'     => $record->last_updated,
                ];
            });

            // Summary statistics
            $summary = $this->getLedgerSummaryStats($request, $expConn, $companyId);

            return response()->json([
                'success' => true,
                'data'    => $data,
                'summary' => $summary,
                'pagination' => [
                    'total'        => $ledgerRecords->total(),
                    'per_page'     => $ledgerRecords->perPage(),
                    'current_page' => $ledgerRecords->currentPage(),
                    'last_page'    => $ledgerRecords->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Ledger Summary API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'   => 'Error fetching ledger summary',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed ledger data for a specific employee.
     * 
     * GET /api/ledger-summary/employee/{employeeId}
     */
    public function employeeDetails($employeeId)
    {
        try {
            $controller = app(\App\Http\Controllers\ExpensePaymentController::class);
            $response = $controller->getEmployeeLedgerDetails($employeeId);
            $data = $response->getData(true);

            if (isset($data['error'])) {
                return response()->json([
                    'success' => false,
                    'error'   => $data['error'],
                    'message' => $data['message'] ?? '',
                ], $response->status());
            }

            $computedBalance = $this->computeEmployeeLedgerBalance($employeeId);
            $data['success'] = true;
            $data['computed_balance'] = $computedBalance;

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Employee Ledger Details API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'   => 'Error fetching employee details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compute real-time ledger balance for an employee.
     */
    private function computeEmployeeLedgerBalance($employeeId): array
    {
        $balances = app(\App\Http\Controllers\ExpensePaymentController::class)->computeBatchEmployeeLedgerBalances([$employeeId]);

        return $balances[$employeeId] ?? [
            'total_imprest'   => 0.0,
            'advance_balance' => 0.0,
            'total_balance'   => 0.0,
        ];
    }

    /**
     * Get summary statistics for ledger data.
     */
    private function getLedgerSummaryStats(Request $request, string $expConn, $companyId)
    {
        $query = DB::table('hrims.hrm_employee as e')
            ->leftJoin(\App\Helpers\DatabaseSwitcher::getExpenseDatabaseName($expConn).'.employee_ledger_balances as lb', 'e.EmployeeID', '=', 'lb.employee_id')
            ->select(
                DB::raw('COUNT(DISTINCT e.EmployeeID) as total_employees'),
                DB::raw('SUM(CASE WHEN e.EmpStatus = "A" THEN 1 ELSE 0 END) as active_employees'),
                DB::raw('SUM(CASE WHEN e.EmpStatus != "A" THEN 1 ELSE 0 END) as inactive_employees'),
                DB::raw('COALESCE(SUM(lb.balance), 0) as total_balance'),
                DB::raw('COALESCE(SUM(lb.closing_balance), 0) as total_closing_balance'),
                DB::raw('COALESCE(SUM(lb.dr), 0) as total_debit'),
                DB::raw('COALESCE(SUM(lb.cr), 0) as total_credit'),
                DB::raw('SUM(CASE WHEN COALESCE(lb.balance, 0) > 0 THEN 1 ELSE 0 END) as positive_balance_count'),
                DB::raw('SUM(CASE WHEN COALESCE(lb.balance, 0) < 0 THEN 1 ELSE 0 END) as negative_balance_count'),
                DB::raw('SUM(CASE WHEN COALESCE(lb.balance, 0) = 0 THEN 1 ELSE 0 END) as zero_balance_count'),
                DB::raw('SUM(CASE WHEN COALESCE(lb.fixed_imprest_amt_1, 0) > 0 THEN 1 ELSE 0 END) as employees_with_imprest'),
                DB::raw('SUM(CASE WHEN COALESCE(lb.advance_amt_1, 0) > 0 THEN 1 ELSE 0 END) as employees_with_advance'),
                DB::raw('COALESCE(SUM(lb.fixed_imprest_amt_1), 0) as total_imprest_amount'),
                DB::raw('COALESCE(SUM(lb.advance_amt_1), 0) as total_advance_amount')
            )
            ->where('e.EmpStatus', '!=', 'D')
            ->where('e.CompanyId', '=', $companyId)
            ->where('e.EmpCode', '>', '0');

        if ($request->filled('status_filter')) {
            if ($request->status_filter == 'active') {
                $query->where('e.EmpStatus', 'A');
            } elseif ($request->status_filter == 'inactive') {
                $query->where('e.EmpStatus', '!=', 'A');
            }
        }

        if ($request->filled('company_id')) {
            $query->where('e.CompanyId', $request->company_id);
        }

        return $query->first();
    }
}
