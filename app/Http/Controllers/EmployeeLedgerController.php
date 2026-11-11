<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class EmployeeLedgerController extends Controller
{
    public function import(Request $request)
    {
        try {
            $request->validate(['excel_file' => 'required|file|mimes:xlsx,xls,csv', 'duplicate_action' => 'required|in:skip,overwrite']);
            $file = $request->file('excel_file');
            $duplicateAction = $request->input('duplicate_action', 'skip');
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            array_shift($rows);
            $results = ['total_processed' => 0, 'successful_imports' => 0, 'skipped_duplicates' => 0, 'overwritten_records' => 0, 'validation_errors' => 0, 'errors' => []];
            DB::beginTransaction();
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $results['total_processed']++;
                if (empty(array_filter($row))) {
                    continue;
                }
                try {
                    $processResult = $this->processRow($row, $rowNumber, $duplicateAction);
                    if ($processResult['success']) {
                        if ($processResult['action'] === 'imported') {
                            $results['successful_imports']++;
                        } elseif ($processResult['action'] === 'skipped') {
                            $results['skipped_duplicates']++;
                        } elseif ($processResult['action'] === 'overwritten') {
                            $results['overwritten_records']++;
                        }
                    } else {
                        $results['validation_errors']++;
                        $results['errors'][] = ['row' => $rowNumber, 'employee_code' => $row[1] ?? 'N/A', 'message' => $processResult['message']];
                    }
                } catch (Exception $e) {
                    $results['validation_errors']++;
                    $results['errors'][] = ['row' => $rowNumber, 'employee_code' => $row[1] ?? 'N/A', 'message' => 'Processing error: ' . $e->getMessage()];
                }
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Import completed successfully', ...$results]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Employee ledger import failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    private function processRow($row, $rowNumber, $duplicateAction)
    {
        $employeeCode = $row[0] ?? '';
        $particulars = $row[1] ?? '';
        $function = $row[2] ?? '';
        $department = $row[3] ?? '';
        $subDepartment = $row[4] ?? '';
        $grade = $row[5] ?? '';
        $status = $row[6] ?? '';
        $drYtd = $row[7] ?? '';
        $crYtd = $row[8] ?? '';
        $balance = $row[9] ?? '';
        $imprest1 = $row[10] ?? '';
        $imprest1Date = $row[11] ?? '';
        $imprest2 = $row[12] ?? '';
        $imprest2Date = $row[13] ?? '';
        $actExp = $row[14] ?? '';
        $advance1 = $row[15] ?? '';
        $advance1Date = $row[16] ?? '';
        $advance1Month = $row[17] ?? '';
        $advance1Purpose = $row[18] ?? '';
        $advance2 = $row[19] ?? '';
        $advance2Date = $row[20] ?? '';
        $advance2Month = $row[21] ?? '';
        $advance2Purpose = $row[22] ?? '';
        $advance3 = $row[23] ?? '';
        $advance3Date = $row[24] ?? '';
        $advance3Month = $row[25] ?? '';
        $advance3Purpose = $row[26] ?? '';
        $advance4 = $row[27] ?? '';
        $advance4Date = $row[28] ?? '';
        $advance4Month = $row[29] ?? '';
        $advance4Purpose = $row[30] ?? '';

        if (empty($employeeCode)) {
            return ['success' => false, 'message' => 'Missing required field: Employee Code'];
        }

        $employeeId = $this->getEmployeeId($employeeCode);
        if (!$employeeId) {
            return ['success' => false, 'message' => 'Employee not found: ' . $employeeCode];
        }

        // Parse amounts
        $drAmount = $this->parseAmount($drYtd);
        $crAmount = $this->parseAmount($crYtd);
        $balanceAmount = $this->parseAmount($balance);
        $imprest1Amount = $this->parseAmount($imprest1);
        $imprest2Amount = $this->parseAmount($imprest2);
        $advance1Amount = $this->parseAmount($advance1);
        $advance2Amount = $this->parseAmount($advance2);
        $advance3Amount = $this->parseAmount($advance3);
        $advance4Amount = $this->parseAmount($advance4);

        if ($drAmount === false || $crAmount === false || $balanceAmount === false) {
            return ['success' => false, 'message' => 'Invalid amount format'];
        }

        // Parse dates
        $imprest1DateParsed = null;
        if (!empty($imprest1Date)) {
            try {
                $imprest1DateParsed = $this->parseDate($imprest1Date);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Invalid Imprest 1 date format: ' . $imprest1Date];
            }
        }

        $imprest2DateParsed = null;
        if (!empty($imprest2Date)) {
            try {
                $imprest2DateParsed = $this->parseDate($imprest2Date);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Invalid Imprest 2 date format: ' . $imprest2Date];
            }
        }

        $advance1DateParsed = null;
        if (!empty($advance1Date)) {
            try {
                $advance1DateParsed = $this->parseDate($advance1Date);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Invalid Advance 1 date format: ' . $advance1Date];
            }
        }

        $advance2DateParsed = null;
        if (!empty($advance2Date)) {
            try {
                $advance2DateParsed = $this->parseDate($advance2Date);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Invalid Advance 2 date format: ' . $advance2Date];
            }
        }

        $advance3DateParsed = null;
        if (!empty($advance3Date)) {
            try {
                $advance3DateParsed = $this->parseDate($advance3Date);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Invalid Advance 3 date format: ' . $advance3Date];
            }
        }

        $advance4DateParsed = null;
        if (!empty($advance4Date)) {
            try {
                $advance4DateParsed = $this->parseDate($advance4Date);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Invalid Advance 4 date format: ' . $advance4Date];
            }
        }

        // Check for existing record
        $existingRecord = DB::table('employee_ledger_balances')
            ->where('employee_id', $employeeId)
            ->where('advance_month_1', $advance1Month)
            ->first();

        if ($existingRecord) {
            if ($duplicateAction === 'skip') {
                return ['success' => true, 'action' => 'skipped', 'message' => 'Duplicate entry skipped'];
            } elseif ($duplicateAction === 'overwrite') {
                DB::table('employee_ledger_balances')
                    ->where('id', $existingRecord->id)
                    ->update([
                        'dr' => $drAmount,
                        'cr' => $crAmount,
                        'balance' => $balanceAmount,
                        'fixed_imprest_amt_1' => $imprest1Amount,
                        'fixed_imprest_date_1' => $imprest1DateParsed,
                        'fixed_imprest_amt_2' => $imprest2Amount,
                        'fixed_imprest_date_2' => $imprest2DateParsed,
                        'advance_amt_1' => $advance1Amount,
                        'advance_date_1' => $advance1DateParsed,
                        'advance_month_1' => $advance1Month,
                        'advance_purpose_1' => $advance1Purpose,
                        'advance_amt_2' => $advance2Amount,
                        'advance_date_2' => $advance2DateParsed,
                        'advance_month_2' => $advance2Month,
                        'advance_purpose_2' => $advance2Purpose,
                        'advance_amt_3' => $advance3Amount,
                        'advance_date_3' => $advance3DateParsed,
                        'advance_month_3' => $advance3Month,
                        'advance_purpose_3' => $advance3Purpose,
                        'advance_amt_4' => $advance4Amount,
                        'advance_date_4' => $advance4DateParsed,
                        'advance_month_4' => $advance4Month,
                        'advance_purpose_4' => $advance4Purpose,
                        'remark' => $particulars,
                        'updated_at' => now()
                    ]);
                return ['success' => true, 'action' => 'overwritten', 'message' => 'Record overwritten successfully'];
            }
        }

        // Insert new record
        DB::table('employee_ledger_balances')->insert([
            'employee_id' => $employeeId,
            'company_id' => auth()->user()->company_id ?? 1,
            'year_id' => 7,
            'dr' => $drAmount,
            'cr' => $crAmount,
            'balance' => $balanceAmount,
            'fixed_imprest_amt_1' => $imprest1Amount,
            'fixed_imprest_date_1' => $imprest1DateParsed,
            'fixed_imprest_amt_2' => $imprest2Amount,
            'fixed_imprest_date_2' => $imprest2DateParsed,
            'advance_amt_1' => $advance1Amount,
            'advance_date_1' => $advance1DateParsed,
            'advance_month_1' => $advance1Month ?: '',
            'advance_purpose_1' => $advance1Purpose,
            'advance_amt_2' => $advance2Amount,
            'advance_date_2' => $advance2DateParsed,
            'advance_month_2' => $advance2Month ?: '',
            'advance_purpose_2' => $advance2Purpose,
            'advance_amt_3' => $advance3Amount,
            'advance_date_3' => $advance3DateParsed,
            'advance_month_3' => $advance3Month ?: '',
            'advance_purpose_3' => $advance3Purpose,
            'advance_amt_4' => $advance4Amount,
            'advance_date_4' => $advance4DateParsed,
            'advance_month_4' => $advance4Month ?: '',
            'advance_purpose_4' => $advance4Purpose,
            'remark' => $particulars,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return ['success' => true, 'action' => 'imported', 'message' => 'Record imported successfully'];
    }

    private function parseDate($dateStr)
    {
        // Ignore null, empty, or whitespace
        if (empty($dateStr) || trim($dateStr) === '') {
            return null;  // or return '' if you prefer empty string
        }

        $formats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateStr);

                // Ensure the date matches the format exactly
                if ($date && $date->format($format) === $dateStr) {
                    return $date->format('Y-m-d');
                }
            } catch (Exception $e) {
                continue;
            }
        }

        // If still not parsed, return null instead of throwing error
        return null;
    }

    private function parseAmount($amountStr)
    {
        $cleaned = str_replace([',', ' '], '', $amountStr);
        if (is_numeric($cleaned)) {
            return (float) $cleaned;
        }
        return false;
    }

    private function getEmployeeId($employeeCode)
    {
        $cleanCode = ltrim($employeeCode, 'VE0');
        $conn = DB::connection('hrims');
        if (Schema::connection('hrims')->hasTable('hrm_employee')) {
            $query = $conn->table('hrm_employee')->where('EmpCode', $cleanCode);
            if (strpos($employeeCode, 'V') === 0) {
                $query->where('VCode', 'V');
            }
            $employee = $query->select('EmployeeID')->first();
            return $employee ? $employee->EmployeeID : null;
        }
        return null;
    }

    public function getFinancialYears()
    {
        try {
            $years = collect();
            if (Schema::hasTable('financial_year')) {
                if (Schema::hasColumn('financial_year', 'YearId') && Schema::hasColumn('financial_year', 'Year')) {
                    $years = DB::table('financial_year')->select('YearId as id', 'Year as name')->orderBy('Year', 'desc')->get();
                } elseif (Schema::hasColumn('financial_year', 'id') && Schema::hasColumn('financial_year', 'name')) {
                    $years = DB::table('financial_year')->select('id', 'name')->orderBy('name', 'desc')->get();
                }
            }
            if ($years->isEmpty()) {
                $years = collect([['id' => 1, 'name' => '2025-26'], ['id' => 2, 'name' => '2024-25'], ['id' => 3, 'name' => '2023-24']]);
            }
            return response()->json($years);
        } catch (\Exception $e) {
            Log::error('Error fetching financial years: ' . $e->getMessage());
            $defaultYears = [['id' => 1, 'name' => '2025-26'], ['id' => 2, 'name' => '2024-25'], ['id' => 3, 'name' => '2023-24']];
            return response()->json($defaultYears);
        }
    }

    public function downloadTemplate()
    {
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="employee_ledger_import_template.csv"'];
        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Code', 'Particulars', 'Functions', 'Department', 'Sub Department', 'Grade', 'Status', 'Dr (YTD)', 'Cr (YTD)', 'Total', 'Imprest 1', 'Date of Payment', 'Imprest 2', 'Date of Payment', 'Act/Exp', 'Advance 1', 'Date of Payment', 'Month', 'Purpose', 'Advance 2', 'Date of Payment', 'Month', 'Purpose', 'Advance 3', 'Date of Payment', 'Month', 'Purpose', 'Advance 4', 'Date of Payment', 'Month', 'Purpose']);
            fputcsv($file, ['E845', 'Opening Balance', 'Sales', 'Marketing', 'Digital', 'J4', 'Active', '50000', '30000', '20000', '40000', '17-03-2025', '20000', '01-04-2025', 'Actual', '15000', '10-01-2026', 'January', 'Travel advance', '10000', '15-02-2026', 'February', 'Project expense', '5000', '20-03-2026', 'March', 'Training', '8000', '25-04-2026', 'April', 'Conference']);
            fputcsv($file, ['E1734', 'Monthly Entry', 'Operations', 'IT', 'Development', 'J1', 'Active', '75000', '50000', '25000', '30000', '05-01-2026', '', '', 'Expected', '20000', '20-01-2026', 'January', 'Equipment purchase', '', '', '', '', '', '', '', '', '', '', '', '']);
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function getData(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', '');
            $employeeId = $request->input('employee_id', '');

            $query = DB::table('employee_ledger_balances as elb')
                ->join('hrims.hrm_employee as emp', 'elb.employee_id', '=', 'emp.EmployeeID')
                ->select([
                    'elb.id',
                    'elb.employee_id',
                    'emp.EmpCode',
                    DB::raw("CONCAT(emp.Fname, ' ', emp.Sname, ' ', emp.Lname) as employee_name"),
                    'elb.dr',
                    'elb.cr',
                    'elb.balance',
                    'elb.fixed_imprest_amt_1',
                    'elb.fixed_imprest_date_1',
                    'elb.fixed_imprest_amt_2',
                    'elb.fixed_imprest_date_2',
                    'elb.advance_amt_1',
                    'elb.advance_date_1',
                    'elb.advance_month_1',
                    'elb.advance_purpose_1',
                    'elb.advance_amt_2',
                    'elb.advance_date_2',
                    'elb.advance_month_2',
                    'elb.advance_purpose_2',
                    'elb.advance_amt_3',
                    'elb.advance_date_3',
                    'elb.advance_month_3',
                    'elb.advance_purpose_3',
                    'elb.advance_amt_4',
                    'elb.advance_date_4',
                    'elb.advance_month_4',
                    'elb.advance_purpose_4',
                    'elb.remark',
                    'elb.created_at'
                ])
                ->orderBy('elb.created_at', 'desc');

            if (!empty($employeeId)) {
                $query->where('elb.employee_id', $employeeId);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('emp.EmpCode', 'like', "%{$search}%")
                      ->orWhere('emp.Fname', 'like', "%{$search}%")
                      ->orWhere('emp.Sname', 'like', "%{$search}%")
                      ->orWhere('emp.Lname', 'like', "%{$search}%")
                      ->orWhere('elb.advance_month_1', 'like', "%{$search}%")
                      ->orWhere('elb.advance_month_2', 'like', "%{$search}%")
                      ->orWhere('elb.advance_month_3', 'like', "%{$search}%")
                      ->orWhere('elb.advance_month_4', 'like', "%{$search}%")
                      ->orWhere('elb.remark', 'like', "%{$search}%");
                });
            }

            $total = $query->count();
            $data = $query
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $lastPage = ceil($total / $perPage);

            return response()->json([
                'data' => $data,
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching ledger data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function show($id)
    {
        try {
            $record = DB::table('employee_ledger_balances as elb')
                ->join('hrims.hrm_employee as emp', 'elb.employee_id', '=', 'emp.EmployeeID')
                ->select([
                    'elb.*',
                    'emp.EmpCode',
                    DB::raw("CONCAT(emp.Fname, ' ', emp.Sname, ' ', emp.Lname) as employee_name")
                ])
                ->where('elb.id', $id)
                ->first();

            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $record]);
        } catch (Exception $e) {
            Log::error('Error fetching ledger record: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch record'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer',
                'dr' => 'nullable|numeric',
                'cr' => 'nullable|numeric',
                'balance' => 'required|numeric',
                'fixed_imprest_amt_1' => 'nullable|numeric|min:0',
                'fixed_imprest_date_1' => 'nullable|date',
                'fixed_imprest_amt_2' => 'nullable|numeric|min:0',
                'fixed_imprest_date_2' => 'nullable|date',
                'advance_amt_1' => 'nullable|numeric',
                'advance_date_1' => 'nullable|date',
                'advance_month_1' => 'nullable|string|max:50',
                'advance_purpose_1' => 'nullable|string',
                'advance_amt_2' => 'nullable|numeric',
                'advance_date_2' => 'nullable|date',
                'advance_month_2' => 'nullable|string|max:50',
                'advance_purpose_2' => 'nullable|string',
                'advance_amt_3' => 'nullable|numeric',
                'advance_date_3' => 'nullable|date',
                'advance_month_3' => 'nullable|string|max:50',
                'advance_purpose_3' => 'nullable|string',
                'advance_amt_4' => 'nullable|numeric',
                'advance_date_4' => 'nullable|date',
                'advance_month_4' => 'nullable|string|max:50',
                'advance_purpose_4' => 'nullable|string',
                'remark' => 'nullable|string'
            ]);

            $id = DB::table('employee_ledger_balances')->insertGetId([
                'employee_id' => $request->employee_id,
                'company_id' => auth()->user()->company_id ?? 1,
                'year_id' => 7,
                'dr' => $request->dr,
                'cr' => $request->cr,
                'balance' => $request->balance,
                'fixed_imprest_amt_1' => $request->fixed_imprest_amt_1,
                'fixed_imprest_date_1' => $request->fixed_imprest_date_1,
                'fixed_imprest_amt_2' => $request->fixed_imprest_amt_2,
                'fixed_imprest_date_2' => $request->fixed_imprest_date_2,
                'advance_amt_1' => $request->advance_amt_1,
                'advance_date_1' => $request->advance_date_1,
                'advance_month_1' => $request->advance_month_1,
                'advance_purpose_1' => $request->advance_purpose_1,
                'advance_amt_2' => $request->advance_amt_2,
                'advance_date_2' => $request->advance_date_2,
                'advance_month_2' => $request->advance_month_2,
                'advance_purpose_2' => $request->advance_purpose_2,
                'advance_amt_3' => $request->advance_amt_3,
                'advance_date_3' => $request->advance_date_3,
                'advance_month_3' => $request->advance_month_3,
                'advance_purpose_3' => $request->advance_purpose_3,
                'advance_amt_4' => $request->advance_amt_4,
                'advance_date_4' => $request->advance_date_4,
                'advance_month_4' => $request->advance_month_4,
                'advance_purpose_4' => $request->advance_purpose_4,
                'remark' => $request->remark,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Record created successfully',
                'id' => $id
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error creating ledger record: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create record'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer',
                'dr' => 'nullable|numeric',
                'cr' => 'nullable|numeric',
                'balance' => 'required|numeric',
                'fixed_imprest_amt_1' => 'nullable|numeric|min:0',
                'fixed_imprest_date_1' => 'nullable|date',
                'fixed_imprest_amt_2' => 'nullable|numeric|min:0',
                'fixed_imprest_date_2' => 'nullable|date',
                'advance_amt_1' => 'nullable|numeric',
                'advance_date_1' => 'nullable|date',
                'advance_month_1' => 'nullable|string',
                'advance_purpose_1' => 'nullable|string',
                'advance_amt_2' => 'nullable|numeric',
                'advance_date_2' => 'nullable|date',
                'advance_month_2' => 'nullable|string',
                'advance_purpose_2' => 'nullable|string',
                'advance_amt_3' => 'nullable|numeric',
                'advance_date_3' => 'nullable|date',
                'advance_month_3' => 'nullable|string',
                'advance_purpose_3' => 'nullable|string',
                'advance_amt_4' => 'nullable|numeric',
                'advance_date_4' => 'nullable|date',
                'advance_month_4' => 'nullable|string',
                'advance_purpose_4' => 'nullable|string',
                'remark' => 'nullable|string'
            ]);

            $pclBefore = \App\Support\PaymentChangeLog::snapshot(config('database.default'), 'employee_ledger_balances', [(int) $id]); // PAY-LOG-01
            $updated = DB::table('employee_ledger_balances')
                ->where('id', $id)
                ->update([
                    'employee_id' => $request->employee_id,
                    'dr' => $request->dr,
                    'cr' => $request->cr,
                    'balance' => $request->balance,
                    'fixed_imprest_amt_1' => $request->fixed_imprest_amt_1,
                    'fixed_imprest_date_1' => $request->fixed_imprest_date_1,
                    'fixed_imprest_amt_2' => $request->fixed_imprest_amt_2,
                    'fixed_imprest_date_2' => $request->fixed_imprest_date_2,
                    'advance_amt_1' => $request->advance_amt_1,
                    'advance_date_1' => $request->advance_date_1,
                    'advance_month_1' => $request->advance_month_1,
                    'advance_purpose_1' => $request->advance_purpose_1,
                    'advance_amt_2' => $request->advance_amt_2,
                    'advance_date_2' => $request->advance_date_2,
                    'advance_month_2' => $request->advance_month_2,
                    'advance_purpose_2' => $request->advance_purpose_2,
                    'advance_amt_3' => $request->advance_amt_3,
                    'advance_date_3' => $request->advance_date_3,
                    'advance_month_3' => $request->advance_month_3,
                    'advance_purpose_3' => $request->advance_purpose_3,
                    'advance_amt_4' => $request->advance_amt_4,
                    'advance_date_4' => $request->advance_date_4,
                    'advance_month_4' => $request->advance_month_4,
                    'advance_purpose_4' => $request->advance_purpose_4,
                    'remark' => $request->remark,
                    'updated_at' => now()
                ]);

            \App\Support\PaymentChangeLog::diff(config('database.default'), 'employee_ledger_balances', $pclBefore, 'ledger', 'ledger_edit', $request->remark);
            if ($updated) {
                return response()->json(['success' => true, 'message' => 'Record updated successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error updating ledger record: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update record'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $pclRow = DB::table('employee_ledger_balances')->where('id', $id)->first(); // PAY-LOG-01
            $deleted = DB::table('employee_ledger_balances')
                ->where('id', $id)
                ->delete();
            if ($deleted && $pclRow) {
                \App\Support\PaymentChangeLog::record('ledger', 'employee_ledger_balances', (int) $id, 'row_deleted', json_encode(['dr' => $pclRow->dr ?? null, 'cr' => $pclRow->cr ?? null, 'balance' => $pclRow->balance ?? null]), null, 'ledger_delete', null, $pclRow->employee_id ?? null);
            }

            if ($deleted) {
                return response()->json(['success' => true, 'message' => 'Record deleted successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }
        } catch (Exception $e) {
            Log::error('Error deleting ledger record: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete record'], 500);
        }
    }

    public function getEmployees(Request $request)
    {
        try {
            $search = $request->input('search', '');

            $query = DB::connection('hrims')
                ->table('hrm_employee')
                ->select([
                    'EmployeeID as id',
                    'EmpCode',
                    DB::raw("CONCAT(Fname, ' ', Sname, ' ', Lname) as name")
                ]);

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('EmpCode', 'like', "%{$search}%")
                        ->orWhere('Fname', 'like', "%{$search}%")
                        ->orWhere('Sname', 'like', "%{$search}%")
                        ->orWhere('Lname', 'like', "%{$search}%");
                });
            }

            $employees = $query->limit(50)->get();

            return response()->json(['success' => true, 'data' => $employees]);
        } catch (Exception $e) {
            Log::error('Error fetching employees: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch employees'], 500);
        }
    }
}
