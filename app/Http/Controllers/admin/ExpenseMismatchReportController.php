<?php

namespace App\Http\Controllers\admin;

use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseMismatchReportController extends Controller
{
    /**
     * Format expense head name to column name (same logic as formatColumnName in JS/PHP)
     */
    private function formatColumnName(string $label): string
    {
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', $label);
        $value = preg_replace('/\s+/', '_', trim($value));
        $value = preg_replace('/__+/', '_', $value);
        return strtolower($value);
    }

    /**
     * Show the mismatch report page
     */
    public function index()
    {
        $yearId = session('year_id');
        $companyId = session('company_id');

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        // Get employees
        $employees = DB::connection(session('hrims_connection'))
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->where('e.CompanyId', $companyId)
            ->select('e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname')
            ->orderBy('e.EmpCode')
            ->get();

        // Get claim types (activity types - cgId = 7)
        $claimTypes = DB::connection(session('expense_connection'))
            ->table('claimtype as ct')
            ->leftJoin('claimgroup as cg', 'cg.cgId', '=', 'ct.cgId')
            ->whereIn('ct.ClaimStatus', ['A', 'B'])
            ->where('ct.cgId', 7)
            ->orderBy('ct.ClaimName')
            ->get(['ct.ClaimId', 'ct.ClaimName', 'cg.cgName']);

        // Get expense head categories (active ones)
        $expenseHeads = DB::connection(session('expense_connection'))
            ->table('adv_expense_head_category')
            ->where('status', 1)
            ->orderBy('expense_head_name')
            ->get();

        return view('reports.expense-mismatch', array_merge($organogramOptions, compact('employees', 'claimTypes', 'expenseHeads')));
    }

    /**
     * Get mismatch data via AJAX with pagination
     */
    public function getData(Request $request)
    {
        try {
            $yearId = session('year_id');
            $month = $request->input('month');
            $employeeId = $request->input('employee_id');
            $claimTypeId = $request->input('claim_type_id');
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);

            $expenseTable = "y{$yearId}_expenseclaims";
            $detailsTable = "y{$yearId}_expenseclaimsdetails";
            $g7Table = "y{$yearId}_g7_expensefilldata";

            // Get active expense heads and their column names
            $expenseHeads = DB::connection(session('expense_connection'))
                ->table('adv_expense_head_category')
                ->where('status', 1)
                ->get();

            $headColumnMap = [];
            $excludeColumns = ['crop_list', 'variety_list'];
            foreach ($expenseHeads as $head) {
                $columnName = $this->formatColumnName($head->expense_head_name);
                if (!in_array($columnName, $excludeColumns)) {
                    $headColumnMap[$columnName] = $head->expense_head_name;
                }
            }

            $companyId = session('company_id');
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = config("database.connections.{$hrimsConn}.database", 'hrims');

            // Build query for expense claims with G7 data
            $query = DB::connection(session('expense_connection'))
                ->table("{$expenseTable} as ec")
                ->leftJoin("{$g7Table} as g7", 'g7.ExpId', '=', 'ec.ExpId')
                ->join('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')
                ->join("{$hrimsDb}.hrm_employee as e", 'e.EmployeeID', '=', 'ec.CrBy')
                ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->where('ec.ClaimStatus', '!=', 'Deactivate')
                ->where('ct.cgId', 7);

            if ($companyId) {
                $query->where('e.CompanyId', $companyId);
            }

            // Apply All Organogram & Policy Filters
            $organogramFilters = OrganogramFilterHelper::extractFilters($request);
            OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'e.EmployeeID', $hrimsDb);

            if ($month) {
                $query->where('ec.ClaimMonth', $month);
            }
            if ($employeeId) {
                $query->where('ec.CrBy', $employeeId);
            }
            if ($claimTypeId) {
                $query->where('ec.ClaimId', $claimTypeId);
            }

            $claims = $query->select(
                'ec.ExpId',
                'ec.CrBy',
                'ec.ClaimMonth',
                'ec.ClaimId',
                'ec.BillDate',
                'ec.FilledTAmt',
                'ec.ApprTAmt',
                'ec.FinancedTAmt',
                'ec.ClaimStatus',
                'ct.ClaimName',
                'g7.*'
            )->orderBy('ec.ClaimMonth')->orderBy('ec.CrBy')->get();

            $mismatches = [];

            // PERF-05: the bill lines and the names in one query per 1,000 claims instead of one per claim
            $detailsByExp = [];
            foreach ($claims->pluck('ExpId')->unique()->chunk(1000) as $chunk) {
                foreach (DB::connection(session('expense_connection'))->table($detailsTable)
                    ->whereIn('ExpId', $chunk->values()->all())->get(['ExpId', 'Title', 'Amount']) as $d) {
                    $detailsByExp[$d->ExpId][] = $d;
                }
            }
            $employeesById = [];

            foreach ($claims as $claim) {
                $details = $detailsByExp[$claim->ExpId] ?? [];

                $detailMap = [];
                foreach ($details as $detail) {
                    $titleKey = $this->formatColumnName($detail->Title);
                    if (!isset($detailMap[$titleKey])) {
                        $detailMap[$titleKey] = 0;
                    }
                    $detailMap[$titleKey] += (int) $detail->Amount;
                }

                $claimMismatches = [];
                foreach ($headColumnMap as $columnName => $headName) {
                    $g7Value = isset($claim->$columnName) ? (int) $claim->$columnName : 0;
                    $verCol = 'ver_' . $columnName;
                    $appCol = 'app_' . $columnName;
                    $payCol = 'pay_' . $columnName;
                    $g7Ver = isset($claim->$verCol) ? (int) $claim->$verCol : 0;
                    $g7App = isset($claim->$appCol) ? (int) $claim->$appCol : 0;
                    $g7Pay = isset($claim->$payCol) ? (int) $claim->$payCol : 0;

                    $detailValue = $detailMap[$columnName] ?? 0;

                    // Check mismatch: g7 claim vs detail claim amount
                    if (($g7Value > 0 || $detailValue > 0) && $g7Value != $detailValue) {
                        $claimMismatches[] = [
                            'head_name' => $headName,
                            'column_name' => $columnName,
                            'g7_amount' => $g7Value,
                            'g7_verify' => $g7Ver,
                            'g7_approver' => $g7App,
                            'g7_finance' => $g7Pay,
                            'detail_amount' => $detailValue,
                            'difference' => $g7Value - $detailValue,
                        ];
                    }
                }

                if (!empty($claimMismatches)) {
                    if (!array_key_exists($claim->CrBy, $employeesById)) {
                        $employeesById[$claim->CrBy] = DB::connection(session('hrims_connection'))
                            ->table('hrm_employee')
                            ->where('EmployeeID', $claim->CrBy)
                            ->first(['EmpCode', 'Fname', 'Sname', 'Lname']);
                    }
                    $employee = $employeesById[$claim->CrBy];

                    $mismatches[] = [
                        'exp_id' => $claim->ExpId,
                        'employee_id' => $claim->CrBy,
                        'employee_code' => $employee->EmpCode ?? '',
                        'employee_name' => $employee ? trim(($employee->Fname ?? '') . ' ' . ($employee->Sname ?? '') . ' ' . ($employee->Lname ?? '')) : '',
                        'month' => $claim->ClaimMonth,
                        'month_name' => date('F', mktime(0, 0, 0, $claim->ClaimMonth, 1)),
                        'claim_type' => $claim->ClaimName ?? '',
                        'bill_date' => $claim->BillDate,
                        'filled_amount' => $claim->FilledTAmt,
                        'approved_amount' => $claim->ApprTAmt,
                        'claim_status' => $claim->ClaimStatus,
                        'mismatches' => $claimMismatches,
                        'mismatch_count' => \count($claimMismatches),
                    ];
                }
            }

            // Pagination
            $totalRecords = \count($mismatches);
            $totalPages = (int) ceil($totalRecords / $perPage);
            $offset = ($page - 1) * $perPage;
            $paginatedData = array_slice($mismatches, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => $paginatedData,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalRecords,
                    'total_pages' => $totalPages,
                    'from' => $totalRecords > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $totalRecords),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('ExpenseMismatchReport error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detail view for a specific ExpId
     */
    public function getDetail(Request $request)
    {
        try {
            $expId = $request->input('exp_id');
            $yearId = session('year_id');

            $detailsTable = "y{$yearId}_expenseclaimsdetails";
            $g7Table = "y{$yearId}_g7_expensefilldata";

            $expenseHeads = DB::connection(session('expense_connection'))
                ->table('adv_expense_head_category')
                ->where('status', 1)
                ->get();

            $headColumnMap = [];
            $excludeColumns = ['crop_list', 'variety_list'];
            foreach ($expenseHeads as $head) {
                $columnName = $this->formatColumnName($head->expense_head_name);
                if (!in_array($columnName, $excludeColumns)) {
                    $headColumnMap[$columnName] = $head->expense_head_name;
                }
            }

            // Get G7 data
            $g7Data = DB::connection(session('expense_connection'))
                ->table($g7Table)
                ->where('ExpId', $expId)
                ->first();

            // Get expense details (row by row)
            $details = DB::connection(session('expense_connection'))
                ->table($detailsTable)
                ->where('ExpId', $expId)
                ->get();

            // Build comparison: G7 columns with prefixes
            $comparison = [];
            foreach ($headColumnMap as $columnName => $headName) {
                // G7 columns: base = claim amt, ver_ = verify, app_ = approver, pay_ = finance
                $claimAmt = ($g7Data && isset($g7Data->$columnName)) ? (int) $g7Data->$columnName : 0;
                $verCol = 'ver_' . $columnName;
                $appCol = 'app_' . $columnName;
                $payCol = 'pay_' . $columnName;
                $verAmt = ($g7Data && isset($g7Data->$verCol)) ? (int) $g7Data->$verCol : 0;
                $appAmt = ($g7Data && isset($g7Data->$appCol)) ? (int) $g7Data->$appCol : 0;
                $payAmt = ($g7Data && isset($g7Data->$payCol)) ? (int) $g7Data->$payCol : 0;

                // Get matching detail rows for this head
                $matchingDetails = [];
                $detailClaimTotal = 0;
                $detailVerifyTotal = 0;
                $detailApproverTotal = 0;
                $detailFinanceTotal = 0;

                foreach ($details as $detail) {
                    $titleKey = $this->formatColumnName($detail->Title);
                    if ($titleKey === $columnName) {
                        $matchingDetails[] = [
                            'ecdId' => $detail->ecdId,
                            'title' => $detail->Title,
                            'amount' => (int) $detail->Amount,
                            'remark' => $detail->Remark,
                            'verifier_amount' => (int) ($detail->VerifierEditAmount ?? 0),
                            'verifier_remark' => $detail->VerifierRemark ?? '',
                            'approver_amount' => (int) ($detail->ApproverEditAmount ?? 0),
                            'approver_remark' => $detail->ApproverRemark ?? '',
                            'finance_amount' => (int) ($detail->FinanceEditAmount ?? 0),
                            'finance_remark' => $detail->FinanceRemark ?? '',
                        ];
                        $detailClaimTotal += (int) $detail->Amount;
                        $detailVerifyTotal += (int) ($detail->VerifierEditAmount ?? 0);
                        $detailApproverTotal += (int) ($detail->ApproverEditAmount ?? 0);
                        $detailFinanceTotal += (int) ($detail->FinanceEditAmount ?? 0);
                    }
                }

                // Only show if any value > 0
                if ($claimAmt > 0 || $verAmt > 0 || $appAmt > 0 || $payAmt > 0 || $detailClaimTotal > 0) {
                    $comparison[] = [
                        'head_name' => $headName,
                        'column_name' => $columnName,
                        'g7_claim' => $claimAmt,
                        'g7_verify' => $verAmt,
                        'g7_approver' => $appAmt,
                        'g7_finance' => $payAmt,
                        'detail_claim' => $detailClaimTotal,
                        'detail_verify' => $detailVerifyTotal,
                        'detail_approver' => $detailApproverTotal,
                        'detail_finance' => $detailFinanceTotal,
                        'claim_diff' => $claimAmt - $detailClaimTotal,
                        'verify_diff' => $verAmt - $detailVerifyTotal,
                        'approver_diff' => $appAmt - $detailApproverTotal,
                        'finance_diff' => $payAmt - $detailFinanceTotal,
                        'is_mismatch' => ($claimAmt != $detailClaimTotal || $verAmt != $detailVerifyTotal || $appAmt != $detailApproverTotal || $payAmt != $detailFinanceTotal),
                        'detail_rows' => $matchingDetails,
                    ];
                }
            }

            // Get crop_list and variety_list from g7
            $cropList = ($g7Data && isset($g7Data->crop_list)) ? $g7Data->crop_list : '';
            $varietyList = ($g7Data && isset($g7Data->variety_list)) ? $g7Data->variety_list : '';

            // Resolve crop names
            $cropNames = [];
            if (!empty($cropList)) {
                $cropIds = array_filter(explode(',', $cropList));
                if (!empty($cropIds)) {
                    $crops = DB::connection(session('hrims_connection'))
                        ->table('core_crops')
                        ->whereIn('id', $cropIds)
                        ->pluck('crop_name')
                        ->toArray();
                    $cropNames = $crops;
                }
            }

            // Resolve variety names
            $varietyNames = [];
            if (!empty($varietyList)) {
                $varietyIds = array_filter(explode(',', $varietyList));
                if (!empty($varietyIds)) {
                    $varieties = DB::connection(session('hrims_connection'))
                        ->table('core_varieties')
                        ->whereIn('id', $varietyIds)
                        ->pluck('variety_name')
                        ->toArray();
                    $varietyNames = $varieties;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $comparison,
                'exp_id' => $expId,
                'crop_list' => implode(', ', $cropNames),
                'variety_list' => implode(', ', $varietyNames),
            ]);

        } catch (\Exception $e) {
            Log::error('ExpenseMismatchReport detail error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fix mismatch: Update g7 column to match the detail amount
     */
    public function fixMismatch(Request $request)
    {
        try {
            $expId = $request->input('exp_id');
            $columnName = $request->input('column_name');
            $fixType = $request->input('fix_type', 'g7_to_detail'); // g7_to_detail or detail_to_g7
            $yearId = session('year_id');

            $detailsTable = "y{$yearId}_expenseclaimsdetails";
            $g7Table = "y{$yearId}_g7_expensefilldata";

            // Get expense head name from column
            $expenseHeads = DB::connection(session('expense_connection'))
                ->table('adv_expense_head_category')
                ->where('status', 1)
                ->get();

            $headName = null;
            foreach ($expenseHeads as $head) {
                if ($this->formatColumnName($head->expense_head_name) === $columnName) {
                    $headName = $head->expense_head_name;
                    break;
                }
            }

            if (!$headName) {
                return response()->json(['success' => false, 'message' => 'Invalid column name'], 400);
            }

            if ($fixType === 'g7_to_detail') {
                // Update G7 to match the detail total amount
                $details = DB::connection(session('expense_connection'))
                    ->table($detailsTable)
                    ->where('ExpId', $expId)
                    ->get();

                $detailTotal = 0;
                foreach ($details as $detail) {
                    $titleKey = $this->formatColumnName($detail->Title);
                    if ($titleKey === $columnName) {
                        $detailTotal += (int) $detail->Amount;
                    }
                }

                // Update g7 column
                DB::connection(session('expense_connection'))
                    ->table($g7Table)
                    ->where('ExpId', $expId)
                    ->update([$columnName => $detailTotal]);

                return response()->json([
                    'success' => true,
                    'message' => "G7 '{$headName}' updated to ₹" . number_format($detailTotal) . " (matched to detail)",
                    'new_value' => $detailTotal,
                ]);

            } elseif ($fixType === 'detail_to_g7') {
                // Update detail amounts to match G7 value
                $g7Data = DB::connection(session('expense_connection'))
                    ->table($g7Table)
                    ->where('ExpId', $expId)
                    ->first();

                $g7Value = ($g7Data && isset($g7Data->$columnName)) ? (int) $g7Data->$columnName : 0;

                // Get existing details for this head
                $details = DB::connection(session('expense_connection'))
                    ->table($detailsTable)
                    ->where('ExpId', $expId)
                    ->get();

                $matchingDetails = [];
                foreach ($details as $detail) {
                    $titleKey = $this->formatColumnName($detail->Title);
                    if ($titleKey === $columnName) {
                        $matchingDetails[] = $detail;
                    }
                }

                if (\count($matchingDetails) === 1) {
                    // Single detail entry - update it directly
                    DB::connection(session('expense_connection'))
                        ->table($detailsTable)
                        ->where('ecdId', $matchingDetails[0]->ecdId)
                        ->update(['Amount' => $g7Value]);

                    return response()->json([
                        'success' => true,
                        'message' => "Detail '{$headName}' updated to ₹" . number_format($g7Value) . " (matched to G7)",
                        'new_value' => $g7Value,
                    ]);
                } elseif (\count($matchingDetails) === 0) {
                    // No detail entry exists - create one
                    DB::connection(session('expense_connection'))
                        ->table($detailsTable)
                        ->insert([
                            'ExpId' => $expId,
                            'Title' => $headName,
                            'Amount' => $g7Value,
                            'Remark' => 'Auto-created from G7 mismatch fix',
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Detail '{$headName}' created with ₹" . number_format($g7Value) . " (from G7)",
                        'new_value' => $g7Value,
                    ]);
                } else {
                    // Multiple detail entries - cannot auto-fix
                    return response()->json([
                        'success' => false,
                        'message' => "Multiple detail entries found for '{$headName}'. Please fix manually.",
                    ], 400);
                }
            }

            return response()->json(['success' => false, 'message' => 'Invalid fix type'], 400);

        } catch (\Exception $e) {
            Log::error('ExpenseMismatchReport fix error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fix failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
