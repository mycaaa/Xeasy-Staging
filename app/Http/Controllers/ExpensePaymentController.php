<?php

namespace App\Http\Controllers;

use App\Exports\FocusPaymentExport;
use App\Exports\LedgerSummaryExport;
use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExpensePaymentController extends Controller
{
    protected $perPage = 1000;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Export pending payments to Excel
     */
    public function exportExcel(Request $request)
    {
        // Get the same data as your index method for pending payments
        $chkval = $request->get('chkval', 1);
        $month = $request->get('v', 0);
        $employeeSearch = $request->get('employee_search', '');
        $yearId = session('year_id');

        // Prepare data array for pendingPayments method
        $data = [
            'chkval' => $chkval,
            'v' => $month,
            'yearId' => $yearId,
            'mmord' => $request->get('Mmord', 0),
            'moveCondition' => '1=1', // For export, we don't need move condition
            'employee_name' => $request->get('employee_name', ''),
            'employee_code' => $request->get('employee_code', ''),
            'employee_search' => $employeeSearch,
            'finance_date_from' => $request->get('finance_date_from', ''),
            'finance_date_to' => $request->get('finance_date_to', ''),
            'min_amount' => $request->get('min_amount', ''),
            'max_amount' => $request->get('max_amount', ''),
            'doc_no' => $request->get('doc_no', ''),
            'move_focus_sheet' => $request->get('move_focus_sheet', ''),
        ];

        // Get all records without pagination for export
        $records = $this->getPendingRecordsForExport($request, $data);

        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pending Payments');

        $isCompany11 = (session('company_id') == 11);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(6);   // S.No
        $sheet->getColumnDimension('B')->setWidth(30);  // Claimant
        $sheet->getColumnDimension('C')->setWidth(12);  // EmpCode
        $sheet->getColumnDimension('D')->setWidth(10);  // Grade
        $sheet->getColumnDimension('E')->setWidth(10);  // Status
        $sheet->getColumnDimension('F')->setWidth(12);  // Month
        $sheet->getColumnDimension('G')->setWidth(12);  // Total Claims
        $sheet->getColumnDimension('H')->setWidth(8);   // Print
        $sheet->getColumnDimension('I')->setWidth(20);  // Activity Finance
        $sheet->getColumnDimension('J')->setWidth(10);  // DA
        $sheet->getColumnDimension('K')->setWidth(12);  // Expense
        $sheet->getColumnDimension('L')->setWidth(12);  // Total Finance
        $sheet->getColumnDimension('M')->setWidth(35);  // Activity Opr Balance
        $sheet->getColumnDimension('N')->setWidth(18);  // Activity Adv Adjustment
        $sheet->getColumnDimension('O')->setWidth(18);  // Expense Adv Adjustment
        $sheet->getColumnDimension('P')->setWidth(12);  // Total Adj
        $sheet->getColumnDimension('Q')->setWidth(30);  // Activity Balance
        $sheet->getColumnDimension('R')->setWidth(15);  // Actual Expense
        $sheet->getColumnDimension('S')->setWidth(20);  // Payable Amount
        $sheet->getColumnDimension('T')->setWidth(12);  // Fixed Imprest
        $sheet->getColumnDimension('U')->setWidth(15);  // Account Summary
        $sheet->getColumnDimension('V')->setWidth(12);  // Action
        if ($isCompany11) {
            $sheet->getColumnDimension('W')->setWidth(24);  // Courier Status (Prev Month Received)
            $sheet->getColumnDimension('X')->setWidth(26);  // Prev Month Verified
        }

        // ========== CREATE HEADER ROWS (Matching your table structure) ==========

        // First header row
        $sheet->setCellValue('A1', 'S.No');
        $sheet->setCellValue('B1', 'Claimant');
        $sheet->setCellValue('C1', 'EmpCode');
        $sheet->setCellValue('D1', 'Grade');
        $sheet->setCellValue('E1', 'Status');
        $sheet->setCellValue('F1', 'Month');
        $sheet->setCellValue('G1', 'Total Claims');
        $sheet->setCellValue('H1', 'Print');
        $sheet->setCellValue('I1', 'Finance Amount');
        $sheet->setCellValue('J1', '');
        $sheet->setCellValue('K1', '');
        $sheet->setCellValue('L1', '');
        $sheet->setCellValue('M1', 'Activity Opr Balance');
        $sheet->setCellValue('N1', 'Advance Adjustment');
        $sheet->setCellValue('O1', '');
        $sheet->setCellValue('P1', 'Total Adj');
        $sheet->setCellValue('Q1', 'Activity Balance');
        // B-04 business rule kept unchanged: DA (claims 19/20) is left out of these columns when grade id <= 67.
        // Why (and where that DA is settled) is not documented in the code; it needs finance confirmation.
        $sheet->setCellValue('R1', 'Actual Expense (excl. DA if grade id <= 67)');
        $sheet->setCellValue('S1', 'Payable Amount (excl. DA if grade id <= 67)');
        $sheet->setCellValue('T1', 'Fixed Imprest');
        $sheet->setCellValue('U1', 'Account Summary');
        $sheet->setCellValue('V1', 'Action');

        if ($isCompany11) {
            $sheet->setCellValue('W1', 'Courier Status');
            $sheet->mergeCells('W1:W2');
            $sheet->setCellValue('X1', 'Prev Month Verified');
            $sheet->mergeCells('X1:X2');
        }

        // Second header row (subheaders)
        $sheet->setCellValue('I2', 'Activity');
        $sheet->setCellValue('J2', 'DA');
        $sheet->setCellValue('K2', 'Expense (net of DA if grade id <= 67)');
        $sheet->setCellValue('L2', 'Total');
        $sheet->setCellValue('N2', 'Activity Adv');
        $sheet->setCellValue('O2', 'Expense Adv');

        // Merge cells for main headers
        $sheet->mergeCells('I1:L1');
        $sheet->mergeCells('N1:O1');

        // Style header rows
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $headerRange = $isCompany11 ? 'A1:X2' : 'A1:V2';
        $sheet->getStyle($headerRange)->applyFromArray($headerStyle);
        $sheet->getStyle($headerRange)->getAlignment()->setWrapText(true);

        // Set row heights for headers
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(25);

        // ========== DATA ROWS ==========
        $row = 3;
        $i = 1;

        foreach ($records as $record) {
            $employeeId = $record['EmployeeID'] ?? ($record['employee_id'] ?? 0);
            $monthNum = $record['Month'] ?? ($record['month'] ?? 1);

            // Get all calculations matching your view
            $employeeStatus = getStsUser($employeeId);
            $financialAmounts = getFinancialAmounts($employeeId, $monthNum, session('year_id'));
            $gradeId = getGradeId($employeeId);

            $finalActivityAmount = 0;
            $finalExpenseAmount = 0;
            $finalDAAmount = 0;

            foreach ($financialAmounts as $fa) {
                $finalActivityAmount += $fa->FinalActivityAmount ?? 0;
                $finalExpenseAmount += $fa->FinalExpenseAmount ?? 0;
                $finalDAAmount += $fa->FinalDAAmount ?? 0;
            }

            $netExpense = $gradeId > 67 ? $finalExpenseAmount : $finalExpenseAmount - $finalDAAmount;
            $grandTotal = $finalActivityAmount + $netExpense + $finalDAAmount;
            $difference = $finalActivityAmount + $netExpense;

            // Get advances
            $activityAdvances = getCurrentMonthActivityAdvances($employeeId, $monthNum);
            $expenseAdvances = getCurrentMonthExpenseAdvances($employeeId, $monthNum);
            $ledgerData = getEmployeeLedgerBalance($employeeId);
            $carriedData = getCarriedAdvancesforadjustment($employeeId, $monthNum);
            $carriedAdvances = $carriedData['advances'];

            if (function_exists('isImprestAdvanceRecord')) {
                $activityAdvances = array_values(array_filter($activityAdvances, function ($adv) {
                    return !isImprestAdvanceRecord($adv);
                }));
                $expenseAdvances = array_values(array_filter($expenseAdvances, function ($adv) {
                    return !isImprestAdvanceRecord($adv);
                }));
                $carriedAdvances = array_values(array_filter($carriedAdvances, function ($adv) {
                    return !isImprestAdvanceRecord($adv);
                }));
            }

            $totalActivityAdvance = 0;
            foreach ($activityAdvances as $actAdv) {
                $totalActivityAdvance += $actAdv['advance_amount'] ?? 0;
            }

            $totalExpenseAdvance = 0;
            foreach ($expenseAdvances as $exp) {
                $totalExpenseAdvance += $exp['advance_amount'] ?? 0;
            }

            $totalCarriedAdvance = 0;
            foreach ($carriedAdvances as $car) {
                $totalCarriedAdvance += $car['amount'] ?? 0;
            }

            $totalAdjustmentAvailable = $totalActivityAdvance + $totalExpenseAdvance + $totalCarriedAdvance;
            $payableAfterAdjustment = max(0, $difference - $totalAdjustmentAvailable);

            // Format activity finance as text (multi-line)
            $activityFinanceText = '';
            $activityFinancedData = getActivityFinancedAmounts($employeeId, $monthNum, session('year_id'));
            foreach ($activityFinancedData as $actFin) {
                $activityFinanceText .= number_format($actFin['total_financed']).' - '.$actFin['ClaimName']."\n";
            }

            // Format activity balance text (multi-line)
            $activityBalanceText = '';
            if (is_array($ledgerData)) {
                foreach ($ledgerData as $key => $value) {
                    if (strpos($key, 'advance_amt_') === 0 && $value > 0) {
                        $activityBalanceText .= '₹'.number_format($value)."\n";
                    }
                }
            }

            // Format activity opr balance text (from your view's partial)
            $activityOprBalanceText = $this->formatActivityOprBalanceForExport($employeeId, $ledgerData, $carriedAdvances, $expenseAdvances);

            // Set cell values
            $sheet->setCellValue('A'.$row, $i);
            $sheet->setCellValue('B'.$row, getUser($employeeId));
            $sheet->setCellValue('C'.$row, getCode($employeeId));
            $sheet->setCellValue('D'.$row, getGrade($employeeId));
            $sheet->setCellValue('E'.$row, $employeeStatus);
            $sheet->setCellValue('F'.$row, date('F', mktime(0, 0, 0, $monthNum, 1)));
            $sheet->setCellValue('G'.$row, $record['Total_Claim'] ?? ($record['total_claim'] ?? 0));
            $sheet->setCellValue('H'.$row, 'One / Two'); // Print options
            $sheet->setCellValue('I'.$row, $activityFinanceText);
            $sheet->setCellValue('J'.$row, $finalDAAmount);
            $sheet->setCellValue('K'.$row, $netExpense);
            $sheet->setCellValue('L'.$row, $grandTotal);
            $sheet->setCellValue('M'.$row, $activityOprBalanceText);
            $sheet->setCellValue('N'.$row, $totalActivityAdvance);
            $sheet->setCellValue('O'.$row, $totalExpenseAdvance);
            $sheet->setCellValue('P'.$row, $totalAdjustmentAvailable);
            $sheet->setCellValue('Q'.$row, $activityBalanceText);
            $sheet->setCellValue('R'.$row, $difference);
            $sheet->setCellValue('S'.$row, $payableAfterAdjustment);
            $sheet->setCellValue('T'.$row, ''); // Fixed imprest
            $sheet->setCellValue('U'.$row, 'View');
            $sheet->setCellValue('V'.$row, 'Approve');

            if ($isCompany11) {
                // Determine Previous Month and Year
                $prevMonth = $monthNum > 1 ? ($monthNum - 1) : 12;
                $prevYearId = $monthNum > 1 ? $yearId : ($yearId - 1);
                $prevMef = null;
                try {
                    $prevMef = DB::connection(session('expense_connection'))
                        ->table("y{$prevYearId}_monthexpensefinal")
                        ->where('EmployeeID', $employeeId)
                        ->where('Month', $prevMonth)
                        ->where('YearId', $prevYearId)
                        ->first();
                } catch (\Throwable $e) {}

                // Column W: Courier Status (Matches the courier_status filter on screen: previous month's RecevingDate)
                $isCourierAvailable = ($prevMef && !empty($prevMef->RecevingDate) && $prevMef->RecevingDate != '' && \App\Support\ZeroDate::has($prevMef->RecevingDate));
                $courierText = $isCourierAvailable ? 'Courier Available' : 'Courier Not Available';
                $sheet->setCellValue('W'.$row, $courierText);
                $sheet->getStyle('W'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Column X: Prev Month Verified (Matches the prev_month_verified filter on screen: previous month's Verified_Amount > 0 and Verified_Date)
                $isPrevVerified = ($prevMef && !empty($prevMef->Verified_Date) && $prevMef->Verified_Date != '' && \App\Support\ZeroDate::has($prevMef->Verified_Date) && ($prevMef->Verified_Amount ?? 0) > 0);
                $prevVerifiedText = $isPrevVerified ? 'Verified' : 'Not Verified';
                if ($isPrevVerified && !empty($prevMef->Verified_Amount)) {
                    $prevVerifiedText .= ' (₹' . number_format($prevMef->Verified_Amount) . ')';
                }
                $sheet->setCellValue('X'.$row, $prevVerifiedText);
                $sheet->getStyle('X'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Apply number formatting for numeric columns
            $numericColumns = ['G', 'J', 'K', 'L', 'N', 'O', 'P', 'R', 'S'];
            foreach ($numericColumns as $col) {
                if ($sheet->getCell($col.$row)->getValue() !== '') {
                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode('#,##0');
                }
            }

            // Enable text wrapping for multi-line cells
            $sheet->getStyle('I'.$row)->getAlignment()->setWrapText(true);
            $sheet->getStyle('M'.$row)->getAlignment()->setWrapText(true);
            $sheet->getStyle('Q'.$row)->getAlignment()->setWrapText(true);

            // Set vertical alignment to top for multi-line cells
            $sheet->getStyle('I'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle('M'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle('Q'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            $row++;
            $i++;
        }

        // Auto-size rows for better readability
        for ($r = 3; $r <= $row - 1; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(-1);
        }

        // Freeze the header row
        $sheet->freezePane('A3');

        // Set headers for download
        $filename = 'expense_payments_pending_'.date('Y-m-d_His').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename.'"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: cache, must-revalidate');

        \App\Support\ReportStamp::workbook($spreadsheet); // STAMP-01

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Format activity opr balance for export (matching your partial view)
     */
    private function formatActivityOprBalanceForExport($employeeId, $ledgerData, $carriedAdvances, $expenseAdvances)
    {
        $text = '';

        // Add ledger balances
        if (is_array($ledgerData)) {
            for ($i = 1; $i <= 4; $i++) {
                $amt = $ledgerData['advance_amt_'.$i] ?? 0;
                $purpose = $ledgerData['advance_purpose_'.$i] ?? '';
                $date = $ledgerData['advance_date_'.$i] ?? '';

                if ($amt > 0) {
                    $text .= '₹'.number_format($amt);
                    if (! empty($purpose)) {
                        $text .= ' - '.$purpose;
                    }
                    if (! empty($date) && \App\Support\ZeroDate::has($date)) {
                        $text .= ' ('.date('d-m-Y', strtotime($date)).')';
                    }
                    $text .= "\n";
                }
            }
        }

        // Add carried advances
        foreach ($carriedAdvances as $carried) {
            if (isset($carried['amount']) && $carried['amount'] > 0) {
                $text .= '₹'.number_format($carried['amount']);
                if (! empty($carried['purpose'])) {
                    $text .= ' - '.$carried['purpose'];
                }
                if (! empty($carried['date']) && \App\Support\ZeroDate::has($carried['date'])) {
                    $text .= ' ('.date('d-m-Y', strtotime($carried['date'])).')';
                }
                $text .= "\n";
            }
        }

        // Add expense advances
        foreach ($expenseAdvances as $exp) {
            if (function_exists('isImprestAdvanceRecord') && isImprestAdvanceRecord($exp)) {
                continue;
            }
            $amt = $exp['advance_amount'] ?? 0;
            if ($amt > 0) {
                $text .= '₹'.number_format($amt).' - Expense Advance';
                if (! empty($exp['payment_date']) && \App\Support\ZeroDate::has($exp['payment_date'])) {
                    $text .= ' ('.date('d-m-Y', strtotime($exp['payment_date'])).')';
                }
                $text .= "\n";
            }
        }

        return rtrim($text, "\n");
    }

    /**
     * Get pending records for export (without pagination)
     */
    private function getPendingRecordsForExport(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        if (session('company_id') == 11) {
            $query = "SELECT * FROM {$tableName} t1
        WHERE YearId = ?
        AND Status = 'Closed'
        AND Total_Claim > 0
        AND Approved_Date != '0000-00-00'
        AND payment_status = 'Pending'
        AND (Fin_PayAmt = '' OR Fin_PayAmt IS NULL)
        AND (Fin_PayOption = '' OR Fin_PayOption IS NULL)
        AND Fin_PayBy = 0";
        } else {
            $query = "SELECT * FROM {$tableName} t1
        WHERE YearId = ?
        AND Status = 'Closed'
        AND Total_Claim > 0
        AND Finance_Amount > 0
        AND Finance_Date != '0000-00-00'
        AND Fin_AppBy > 0
        AND Fin_AppDate != '0000-00-00'
        AND Fin_AppDate != ''
        AND Fin_AppDate != '1970-01-01'
        AND payment_status = 'Pending'
        AND (Fin_PayAmt = '' OR Fin_PayAmt IS NULL)
        AND (Fin_PayOption = '' OR Fin_PayOption IS NULL)
        AND Fin_PayBy = 0";
        }

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND Month = ?';
            $bindings[] = $month;
        }

        // Move condition
        if ($moveCondition != '1=1') {
            $query .= " AND Move_FocuseSheet = 'N'";
        }

        // Employee name filter
        if ($request->filled('employee_name')) {
            $employeeName = $request->employee_name;
            $query .= " AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) LIKE ?
            OR Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ?
        )";
            $bindings = array_merge($bindings, array_fill(0, 4, "%{$employeeName}%"));
        }

        // Employee code filter
        if ($request->filled('employee_code')) {
            $employeeCode = $request->employee_code;
            $query .= ' AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE EmpCode LIKE ? OR EmpCode_New LIKE ? OR ECode LIKE ?
        )';
            $bindings = array_merge($bindings, array_fill(0, 3, "%{$employeeCode}%"));
        }

        // Combined search
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= " AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) LIKE ?
            OR Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ?
            OR EmpCode LIKE ? OR EmpCode_New LIKE ? OR ECode LIKE ?
        )";
            $bindings = array_merge($bindings, array_fill(0, 7, "%{$search}%"));
        }

        // Finance date filters
        if ($request->filled('finance_date_from')) {
            $query .= ' AND Finance_Date >= ?';
            $bindings[] = $request->finance_date_from;
        }

        if ($request->filled('finance_date_to')) {
            $query .= ' AND Finance_Date <= ?';
            $bindings[] = $request->finance_date_to;
        }

        // Amount filters
        if ($request->filled('min_amount')) {
            $query .= ' AND Finance_Amount >= ?';
            $bindings[] = $request->min_amount;
        }

        if ($request->filled('max_amount')) {
            $query .= ' AND Finance_Amount <= ?';
            $bindings[] = $request->max_amount;
        }

        // Document number filter
        if ($request->filled('doc_no')) {
            $query .= ' AND DocNo LIKE ?';
            $bindings[] = "%{$request->doc_no}%";
        }

        // Company 11 Courier Status Filter (Matches screen filter exactly)
        if (session('company_id') == 11 && $request->filled('courier_status')) {
            $existsCondition = "
            SELECT 1 FROM {$tableName} t2
            WHERE t2.EmployeeID = t1.EmployeeID
            AND (
                (t1.Month > 1 AND t2.Month = t1.Month - 1 AND t2.YearId = t1.YearId)
                OR
                (t1.Month = 1 AND t2.Month = 12 AND t2.YearId = t1.YearId - 1)
            )
            AND (t2.RecevingDate IS NOT NULL AND t2.RecevingDate != '' AND t2.RecevingDate != '0000-00-00')
        ";

            if ($request->courier_status == 'available') {
                $query .= " AND EXISTS ($existsCondition)";
            }

            if ($request->courier_status == 'not_available') {
                $query .= " AND NOT EXISTS ($existsCondition)";
            }
        }

        // Company 11 Previous Month Verified Filter (Matches screen filter exactly)
        if (session('company_id') == 11 && $request->filled('prev_month_verified')) {
            $verifiedCondition = "
            SELECT 1 FROM {$tableName} t3
            WHERE t3.EmployeeID = t1.EmployeeID
            AND (
                (t1.Month > 1 AND t3.Month = t1.Month - 1 AND t3.YearId = t1.YearId)
                OR
                (t1.Month = 1 AND t3.Month = 12 AND t3.YearId = t1.YearId - 1)
            )
            AND t3.Verified_Amount > 0
            AND t3.Verified_Date IS NOT NULL
            AND t3.Verified_Date != ''
            AND t3.Verified_Date != '0000-00-00'
        ";

            if ($request->prev_month_verified == 'verified') {
                $query .= " AND EXISTS ($verifiedCondition)";
            }

            if ($request->prev_month_verified == 'not_verified') {
                $query .= " AND NOT EXISTS ($verifiedCondition)";
            }
        }

        // Move Focus Sheet filter
        if ($request->filled('move_focus_sheet')) {
            $query .= ' AND Move_FocuseSheet = ?';
            $bindings[] = $request->move_focus_sheet;
        }

        // Approved Date filter
        if ($request->filled('approved_date')) {
            $query .= ' AND DATE(Approved_Date) = ?';
            $bindings[] = $request->approved_date;
        }

        $query .= ' ORDER BY Month ASC, EmployeeID ASC';

        // Get all records (no limit for export)
        $records = $connection->select($query, $bindings);

        // Convert to array and add employee details
        $recordsArray = array_map(function ($record) {
            $recordArray = (array) $record;

            // Fetch employee details
            $employee = DB::connection('hrims')->selectOne('
            SELECT Fname, Sname, Lname, EmpCode, EmpCode_New, ECode
            FROM hrm_employee
            WHERE EmployeeID = ?
        ', [$recordArray['EmployeeID']]);

            if ($employee) {
                $recordArray['employee_name'] = trim(
                    ($employee->Fname ?? '').' '
                        .($employee->Sname ?? '').' '
                        .($employee->Lname ?? '')
                );
                $recordArray['employee_code'] = $employee->EmpCode ?? $employee->EmpCode_New ?? $employee->ECode ?? '';
            } else {
                $recordArray['employee_name'] = '';
                $recordArray['employee_code'] = '';
            }

            return $recordArray;
        }, $records);

        return $recordsArray;
    }

    public function displayRecords(Request $request)
    {
        $chkval = $request->get('chkval', 1);
        $month = $request->get('v', 0);
        $yearId = session('year_id');
        $mmord = $request->get('Mmord', 0);

        $moveCondition = $mmord == 1 ? "Move_FocuseSheet='N'" : '1=1';

        $data = [
            'chkval' => $chkval,
            'v' => $month,
            'yearId' => $yearId,
            'mmord' => $mmord,
            'moveCondition' => $moveCondition,
            // Add all filter parameters to data array
            'employee_name' => $request->get('employee_name', ''),
            'employee_code' => $request->get('employee_code', ''),
            'employee_search' => $request->get('employee_search', ''),
            'finance_date_from' => $request->get('finance_date_from', ''),
            'finance_date_to' => $request->get('finance_date_to', ''),
            'approved_date' => $request->get('approved_date', ''),
            'min_amount' => $request->get('min_amount', ''),
            'max_amount' => $request->get('max_amount', ''),
            'doc_no' => $request->get('doc_no', ''),
            'move_focus_sheet' => $request->get('move_focus_sheet', ''),
            'per_page' => $request->get('per_page', 12),
        ];

        switch ($chkval) {
            case 1:
                return $this->pendingPayments($request, $data);
            case 2:
                return $this->approvedPayments($request, $data);
            case 3:
                return $this->instructedPayments($request, $data);
            case 4:
                return $this->confirmedPayments($request, $data);
            case 5:
                return $this->unsettledPayments($request, $data);
            case 6:
                return $this->holdedPayments($request, $data);
            default:
                return $this->pendingPayments($request, $data);
        }
    }

    /**
     * Pending Payments (chkval=1)
     */
    protected function pendingPayments(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        // ✅ BASE QUERY (REMOVED EXISTS FROM HERE)
        if (session('company_id') == 11) {
            $query = "SELECT * FROM {$tableName} t1
        WHERE YearId = ?
        AND Status = 'Closed'
        AND Total_Claim > 0
        AND Approved_Date != '0000-00-00'
        AND payment_status = 'Pending'
        AND (Fin_PayAmt = '' OR Fin_PayAmt IS NULL)
        AND (Fin_PayOption = '' OR Fin_PayOption IS NULL)
        AND Fin_PayBy = 0";
        } else {
            $query = "SELECT * FROM {$tableName}
        WHERE YearId = ?
        AND Status = 'Closed'
        AND Total_Claim > 0
        AND Finance_Amount > 0
        AND Finance_Date != '0000-00-00'
        AND Fin_AppBy > 0
        AND Fin_AppDate != '0000-00-00'
        AND Fin_AppDate != ''
        AND Fin_AppDate != '1970-01-01'
        AND payment_status = 'Pending'
        AND (Fin_PayAmt = '' OR Fin_PayAmt IS NULL)
        AND (Fin_PayOption = '' OR Fin_PayOption IS NULL)
        AND Fin_PayBy = 0";
        }

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND Month = ?';
            $bindings[] = $month;
        }

        // Move condition
        if ($moveCondition != '1=1') {
            $query .= " AND Move_FocuseSheet = 'N'";
        }

        // Employee name filter
        if ($request->filled('employee_name')) {
            $employeeName = $request->employee_name;
            $query .= " AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) LIKE ?
            OR Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ?
        )";
            $bindings = array_merge($bindings, array_fill(0, 4, "%{$employeeName}%"));
        }

        // Employee code filter
        if ($request->filled('employee_code')) {
            $employeeCode = $request->employee_code;
            $query .= ' AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE EmpCode LIKE ? OR EmpCode_New LIKE ? OR ECode LIKE ?
        )';
            $bindings = array_merge($bindings, array_fill(0, 3, "%{$employeeCode}%"));
        }

        // Combined search
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= " AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) LIKE ?
            OR Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ?
            OR EmpCode LIKE ? OR EmpCode_New LIKE ? OR ECode LIKE ?
        )";
            $bindings = array_merge($bindings, array_fill(0, 7, "%{$search}%"));
        }

        // Finance date filters
        if ($request->filled('finance_date_from')) {
            $query .= ' AND Finance_Date >= ?';
            $bindings[] = $request->finance_date_from;
        }

        if ($request->filled('finance_date_to')) {
            $query .= ' AND Finance_Date <= ?';
            $bindings[] = $request->finance_date_to;
        }

        // Amount filters
        if ($request->filled('min_amount')) {
            $query .= ' AND Finance_Amount >= ?';
            $bindings[] = $request->min_amount;
        }

        if ($request->filled('max_amount')) {
            $query .= ' AND Finance_Amount <= ?';
            $bindings[] = $request->max_amount;
        }

        // Document number filter
        if ($request->filled('doc_no')) {
            $query .= ' AND DocNo LIKE ?';
            $bindings[] = "%{$request->doc_no}%";
        }

        // ✅ COURIER FILTER (EXISTS / NOT EXISTS)
        if (session('company_id') == 11 && $request->filled('courier_status')) {

            $existsCondition = "
            SELECT 1 FROM {$tableName} t2
            WHERE t2.EmployeeID = t1.EmployeeID
            AND (
                (t1.Month > 1 AND t2.Month = t1.Month - 1 AND t2.YearId = t1.YearId)
                OR
                (t1.Month = 1 AND t2.Month = 12 AND t2.YearId = t1.YearId - 1)
            )
        
            AND (t2.RecevingDate IS NOT NULL AND t2.RecevingDate != '' AND t2.RecevingDate != '0000-00-00')
        ";

            if ($request->courier_status == 'available') {
                $query .= " AND EXISTS ($existsCondition)";
            }

            if ($request->courier_status == 'not_available') {
                $query .= " AND NOT EXISTS ($existsCondition)";
            }
        }

        // ✅ PREVIOUS MONTH VERIFIED FILTER
        if (session('company_id') == 11 && $request->filled('prev_month_verified')) {

            $verifiedCondition = "
            SELECT 1 FROM {$tableName} t3
            WHERE t3.EmployeeID = t1.EmployeeID
            AND (
                (t1.Month > 1 AND t3.Month = t1.Month - 1 AND t3.YearId = t1.YearId)
                OR
                (t1.Month = 1 AND t3.Month = 12 AND t3.YearId = t1.YearId - 1)
            )
            AND t3.Verified_Amount > 0
            AND t3.Verified_Date IS NOT NULL
            AND t3.Verified_Date != ''
            AND t3.Verified_Date != '0000-00-00'
        ";

            if ($request->prev_month_verified == 'verified') {
                $query .= " AND EXISTS ($verifiedCondition)";
            }

            if ($request->prev_month_verified == 'not_verified') {
                $query .= " AND NOT EXISTS ($verifiedCondition)";
            }
        }

        // Move Focus Sheet filter
        if ($request->filled('move_focus_sheet')) {
            $query .= ' AND Move_FocuseSheet = ?';
            $bindings[] = $request->move_focus_sheet;
        }

        // Approved Date filter
        if ($request->filled('approved_date')) {
            $query .= ' AND DATE(Approved_Date) = ?';
            $bindings[] = $request->approved_date;
        }

        $query .= ' ORDER BY Month ASC, EmployeeID ASC';

        // Fetch distinct Approved_Date values for the dropdown (unfiltered by approved_date)
        $approvedDatesQuery = "SELECT DISTINCT DATE(Approved_Date) as approved_date
            FROM {$tableName}
            WHERE YearId = ?
            AND Status = 'Closed'
            AND Total_Claim > 0
            AND Approved_Date != '0000-00-00'
            AND payment_status = 'Pending'
            AND (Fin_PayAmt = '' OR Fin_PayAmt IS NULL)
            AND (Fin_PayOption = '' OR Fin_PayOption IS NULL)
            AND Fin_PayBy = 0
            ORDER BY Approved_Date DESC";
        $approvedDates = $connection->select($approvedDatesQuery, [$yearId]);
        $approvedDates = array_map(fn($r) => $r->approved_date, $approvedDates);

        // Count
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $perPage = $request->get('per_page', 12);
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $query .= ' LIMIT '.(int) $offset.', '.(int) $perPage;

        $records = $connection->select($query, $bindings);

        // Employee details
        $recordsArray = array_map(function ($record) {
            $recordArray = (array) $record;

            $employee = DB::connection('hrims')->selectOne('
            SELECT Fname, Sname, Lname, EmpCode, EmpCode_New, ECode
            FROM hrm_employee
            WHERE EmployeeID = ?
        ', [$recordArray['EmployeeID']]);

            if ($employee) {
                $recordArray['employee_name'] = trim(
                    ($employee->Fname ?? '').' '.
                    ($employee->Sname ?? '').' '.
                    ($employee->Lname ?? '')
                );
                $recordArray['employee_code'] = $employee->EmpCode ?? $employee->EmpCode_New ?? $employee->ECode ?? '';
            } else {
                $recordArray['employee_name'] = '';
                $recordArray['employee_code'] = '';
            }

            return $recordArray;
        }, $records);

        $paginatedRecords = new LengthAwarePaginator(
            $recordsArray,
            $totalRecords,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('expense-payments.pending', compact('paginatedRecords', 'data', 'approvedDates'));
    }

    /**
     * Approved Payments (chkval=2)
     */
    /**
     * Approved Payments (chkval=2)
     */
    protected function approvedPayments(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        if (session('company_id') == '11') {
            $query = "SELECT * FROM {$tableName} t1
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Approved_Amount > 0
                  AND Approved_Date != '0000-00-00'
                  AND payment_status = 'Approved'
                  AND payment_approved_date != '0000-00-00')
              )";
        } else {
            $query = "SELECT * FROM {$tableName} t1
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Finance_Amount > 0
                  AND Finance_Date != '0000-00-00'
                  AND Fin_AppBy > 0
                  AND Fin_AppDate != '0000-00-00'
                  AND Fin_AppDate != ''
                  AND Fin_AppDate != '1970-01-01'
                  AND payment_status = 'Approved'
                  AND is_focus_exported != 1
                  AND payment_approved_date != '0000-00-00')
              )";
        }

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND Month = ?';
            $bindings[] = $month;
        }

        // Move condition
        if ($moveCondition != '1=1') {
            $query .= " AND Move_FocuseSheet = 'N'";
        }

        // Employee search filter
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= " AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) LIKE ?
            OR Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ?
            OR EmpCode LIKE ? OR EmpCode_New LIKE ? OR ECode LIKE ?
        )";
            $bindings = array_merge($bindings, array_fill(0, 7, "%{$search}%"));
        }

        // ✅ COURIER FILTER (EXISTS / NOT EXISTS) - Add this for Company 11
        if (session('company_id') == 11 && $request->filled('courier_status')) {
            $existsCondition = "
            SELECT 1 FROM {$tableName} t2
            WHERE t2.EmployeeID = t1.EmployeeID
            AND (
                (t1.Month > 1 AND t2.Month = t1.Month - 1 AND t2.YearId = t1.YearId)
                OR
                (t1.Month = 1 AND t2.Month = 12 AND t2.YearId = t1.YearId - 1)
            )
            AND (t2.DocNo IS NOT NULL AND t2.DocNo != '')
            AND (t2.PostDate IS NOT NULL AND t2.PostDate != '' AND t2.PostDate != '0000-00-00')
        ";

            if ($request->courier_status == 'available') {
                $query .= " AND EXISTS ($existsCondition)";
            }

            if ($request->courier_status == 'not_available') {
                $query .= " AND NOT EXISTS ($existsCondition)";
            }
        }

        $query .= ' ORDER BY payment_approved_date DESC, Month ASC, EmployeeID ASC';

        // Get total records count
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $this->perPage;

        $query .= ' LIMIT '.(int) $offset.', '.(int) $this->perPage;

        $records = $connection->select($query, $bindings);
        $records = array_map(function ($record) {
            return (array) $record;
        }, $records);

        return view('expense-payments.approved', compact('records', 'data', 'totalRecords', 'page'));
    }

    protected function holdedPayments(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        if (session('company_id') == '11') {
            $query = "SELECT * FROM {$tableName} t1
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Approved_Amount > 0
                  AND Approved_Date != '0000-00-00'
                   AND payment_status IN ('Approved','InstructedPayment')
                  AND is_exported = 2
                  AND payment_approved_date != '0000-00-00')
              )";
        } else {
            $query = "SELECT * FROM {$tableName} t1
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Finance_Amount > 0
                  AND Finance_Date != '0000-00-00'
                  AND Fin_AppBy > 0
                  AND Fin_AppDate != '0000-00-00'
                  AND Fin_AppDate != ''
                  AND Fin_AppDate != '1970-01-01'
                  AND payment_status IN ('Approved','InstructedPayment')
                  AND is_exported = 2
                  AND payment_approved_date != '0000-00-00')
              )";
        }

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND Month = ?';
            $bindings[] = $month;
        }

        // Move condition
        if ($moveCondition != '1=1') {
            $query .= " AND Move_FocuseSheet = 'N'";
        }

        // Employee search filter
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= " AND EmployeeID IN (
            SELECT EmployeeID FROM hrims.hrm_employee
            WHERE CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) LIKE ?
            OR Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ?
            OR EmpCode LIKE ? OR EmpCode_New LIKE ? OR ECode LIKE ?
        )";
            $bindings = array_merge($bindings, array_fill(0, 7, "%{$search}%"));
        }

        // ✅ COURIER FILTER (EXISTS / NOT EXISTS) - Add this for Company 11
        if (session('company_id') == 11 && $request->filled('courier_status')) {
            $existsCondition = "
            SELECT 1 FROM {$tableName} t2
            WHERE t2.EmployeeID = t1.EmployeeID
            AND (
                (t1.Month > 1 AND t2.Month = t1.Month - 1 AND t2.YearId = t1.YearId)
                OR
                (t1.Month = 1 AND t2.Month = 12 AND t2.YearId = t1.YearId - 1)
            )
            AND (t2.DocNo IS NOT NULL AND t2.DocNo != '')
            AND (t2.PostDate IS NOT NULL AND t2.PostDate != '' AND t2.PostDate != '0000-00-00')
        ";

            if ($request->courier_status == 'available') {
                $query .= " AND EXISTS ($existsCondition)";
            }

            if ($request->courier_status == 'not_available') {
                $query .= " AND NOT EXISTS ($existsCondition)";
            }
        }

        $query .= ' ORDER BY payment_approved_date DESC, Month ASC, EmployeeID ASC';

        // Get total records count
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $this->perPage;

        $query .= ' LIMIT '.(int) $offset.', '.(int) $this->perPage;

        $records = $connection->select($query, $bindings);
        $records = array_map(function ($record) {
            return (array) $record;
        }, $records);

        return view('expense-payments.holded', compact('records', 'data', 'totalRecords', 'page'));
    }

    public function releaseSelected(Request $request)
    {
        try {
            $rows = json_decode($request->input('rows', '[]'), true);

            if (empty($rows)) {
                return response()->json(['error' => 'No rows selected'], 400);
            }

            $yearId = session('year_id');
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            $ids = array_map('intval', array_column($rows, 'id'));

            // LV-C05: the approver of a payment may not release it (maker-checker)
            if ($this->makerCheckerBlocks($tableName, fn ($q) => $q->whereIn('id', $ids))) {
                return response()->json(['error' => 'You approved one or more of these payments; another person must release them.'], 403);
            }
            $pclBefore = \App\Support\PaymentChangeLog::snapshot(session('expense_connection'), $tableName, $ids); // PAY-LOG-01
            DB::connection(session('expense_connection'))
                ->table($tableName)
                ->whereIn('id', $ids)
                ->update(array_merge([
                    'payment_status' => 'InstructedPayment',
                    'payment_instructed_date' => now(),
                    'is_exported' => 0,
                ], $this->instructedByColumn($tableName)));
            \App\Support\PaymentChangeLog::diff(session('expense_connection'), $tableName, $pclBefore, 'month_final', 'release_selected');

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('Release Error: '.$e->getMessage());

            return response()->json(['error' => 'Release failed'], 500);
        }
    }



public function updateHoldRemark(Request $request)
{
    try {
        $request->validate([
            'id' => 'required|integer',
            'remark' => 'nullable|string|max:500'
        ]);

        $payment = ExpensePayment::find($request->id);

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment record not found'
            ], 404);
        }

        $payment->hold_remark = $request->remark;
        $payment->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Remark saved successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save remark: ' . $e->getMessage()
        ], 500);
    }
}



    /**
     * Export Only - No release, just generate export file
     */
    public function exportOnly(Request $request)
    {
        try {
            $rows = json_decode($request->input('rows', '[]'), true);
            $actionType = $request->input('action_type', 'export_only');
            $yearId = session('year_id');

            \Log::info('Export Only Called', [
                'action_type' => $actionType,
                'rows_count' => count($rows),
                'rows' => $rows,
            ]);

            if (empty($rows)) {
                return redirect()->back()->with('error', 'No records selected for export');
            }

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            // For release_export, check for 'InstructedPayment' status
            // For export_only, check for 'Approved' status
            // $statusForExport = ($actionType === 'release_export') ? 'InstructedPayment' : 'Approved';

            // Get the record IDs
            $rowIds = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($rowIds), '?'));

            $query = "SELECT mef.id, mef.EmployeeID, mef.Month, mef.payment_amount, mef.payment_status
FROM {$tableName} mef
WHERE mef.YearId = ?
AND mef.id IN ({$placeholders})
AND mef.payment_status IN ('Approved', 'InstructedPayment')
ORDER BY mef.Month ASC, mef.EmployeeID ASC";
            // LV-C10: only months approved or released for payment can go into a bank / Focus file
            // (the status filter was commented out, so any month id from the browser was exported)

            $params = array_merge([$yearId], array_map('intval', $rowIds));

            $payments = DB::connection(session('expense_connection'))->select($query, $params);

            if (empty($payments)) {
                $errorMsg = ($actionType === 'release_export')
                    ? 'No released payment records found for export. Records may not have been released yet. Please wait a moment and try again.'
                    : 'No approved payment records found for export.';

                return redirect()->back()->with('error', $errorMsg);
            }

            // Create an array to hold record IDs (will be passed by reference)
            $recordIds = [];

            // Generate Excel file using the existing class
            $timestamp = now()->format('Ymd_His');
            $fileType = ($actionType === 'release_export') ? 'Release_Export' : 'Export_Only';
            $filename = "Focus_Payment_{$fileType}_{$timestamp}.xlsx";

            // Pass $recordIds by reference to the export class
            $export = new \App\Exports\FocusPaymentExport($payments, $yearId, $recordIds);
            \App\Support\FocusExportLog::record($actionType === 'release_export' ? 'payment_release' : 'payment_export_only', $payments, $request->except(['_token']), $filename); // FOCUS-LOG-01

            // For release_export, we DON'T want to mark as exported here
            // That should be done separately if needed
            if ($actionType !== 'release_export') {
                session(['export_record_ids' => $recordIds]);
                session(['export_table_name' => $tableName]);
            }

            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            \Log::error('Export Only Error: '.$e->getMessage());
            \Log::error('Export Only Trace: '.$e->getTraceAsString());

            return redirect()->back()->with('error', 'Export failed: '.$e->getMessage());
        }
    }

    /**
     * Export Combined - Export all selected records in a single file
     */
    public function exportCombined(Request $request)
    {
        try {
            $rows = json_decode($request->input('rows', '[]'), true);
            $yearId = session('year_id');

            if (empty($rows)) {
                return redirect()->back()->with('error', 'No records selected for export');
            }

            \Log::info('Export Combined Called', [
                'row_count' => count($rows),
                'rows' => $rows,
            ]);

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            // Get the record IDs
            $rowIds = array_column($rows, 'id');

            // Get payments - they could be either 'Approved' or 'InstructedPayment'
            $payments = DB::connection(session('expense_connection'))
                ->table($tableName)
                ->where('YearId', $yearId)
                ->whereIn('payment_status', ['Approved', 'InstructedPayment'])
                ->whereIn('id', $rowIds)
                ->orderBy('Month')
                ->orderBy('EmployeeID')
                ->get();

            if ($payments->isEmpty()) {
                \Log::warning('No payments found for combined export', [
                    'ids' => $rowIds,
                ]);

                return redirect()->back()->with('error', 'No payment records found for export');
            }

            \Log::info('Payments found for combined export', ['count' => $payments->count()]);

            // Create an array to hold record IDs (passed by reference to FocusPaymentExport)
            $recordIds = [];

            // Generate Excel file
            $timestamp = now()->format('Ymd_His');
            $filename = "Focus_Payment_Combined_Export_{$timestamp}.xlsx";

            // Create export instance
            $export = new \App\Exports\FocusPaymentExport($payments, $yearId, $recordIds);
            \App\Support\FocusExportLog::record('payment_combined', $payments, $request->except(['_token']), $filename); // FOCUS-LOG-01

            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            \Log::error('Combined Export Error: '.$e->getMessage());
            \Log::error('Combined Export Trace: '.$e->getTraceAsString());

            return redirect()->back()->with('error', 'Export failed: '.$e->getMessage());
        }
    }

    public function instructedtreasury(Request $request)
    {

        $chkval = $request->get('chkval', 3);
        $month = $request->get('v', 0);
        $yearId = session('year_id');
        $mmord = $request->get('Mmord', 0);

        $moveCondition = $mmord == 1 ? "Move_FocuseSheet='N'" : '1=1';

        $data = [
            'chkval' => $chkval,
            'v' => $month,
            'yearId' => $yearId,
            'mmord' => $mmord,
            'moveCondition' => $moveCondition,
            // Add all filter parameters to data array
            'employee_name' => $request->get('employee_name', ''),
            'employee_code' => $request->get('employee_code', ''),
            'employee_search' => $request->get('employee_search', ''),
            'finance_date_from' => $request->get('finance_date_from', ''),
            'finance_date_to' => $request->get('finance_date_to', ''),
            'min_amount' => $request->get('min_amount', ''),
            'max_amount' => $request->get('max_amount', ''),
            'doc_no' => $request->get('doc_no', ''),
            'move_focus_sheet' => $request->get('move_focus_sheet', ''),
            'per_page' => $request->get('per_page', 12),
        ];

        return $this->instructedPaymentsForTreasury($request, $data);
    }

    /**
     * Instructed Payments (chkval=3)
     */
    protected function instructedPayments(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];
        $exportFilter = $request->get('export_filter');

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        if (session('company_id') == 11) {
            $query = "SELECT * FROM {$tableName}
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Approved_Amount > 0
                  AND Approved_Date != '0000-00-00'
                  AND payment_status = 'InstructedPayment'
                  AND payment_instructed_date != '0000-00-00')
              )";
        } else {

            $query = "SELECT * FROM {$tableName}
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Finance_Amount > 0
                  AND Finance_Date != '0000-00-00'
                  AND Fin_AppBy > 0
                  AND Fin_AppDate != '0000-00-00'
                  AND Fin_AppDate != ''
                  AND Fin_AppDate != '1970-01-01'
                  AND payment_status = 'InstructedPayment'
                  AND payment_instructed_date != '0000-00-00')
              )";
        }

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND Month = ?';
            $bindings[] = $month;
        }

        // Move condition
        if ($moveCondition != '1=1') {
            $query .= " AND Move_FocuseSheet = 'N'";
        }

        // ✅ Export / Hold Filter
        if ($exportFilter !== null && $exportFilter !== '') {
            $query .= ' AND is_exported = ?';
            $bindings[] = $exportFilter;
        }

        $query .= ' ORDER BY payment_approved_date DESC, Month ASC, EmployeeID ASC';
        // dd($query);
        // Count
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $this->perPage;

        $query .= ' LIMIT '.(int) $offset.', '.(int) $this->perPage;

        $records = $connection->select($query, $bindings);
        $records = array_map(fn ($record) => (array) $record, $records);

        return view('expense-payments.instructed', compact('records', 'data', 'totalRecords', 'page'));
    }

    protected function instructedPaymentsForTreasury(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];
        $exportFilter = $request->get('export_filter');

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        if (session('company_id') == 11) {
            $query = "SELECT * FROM {$tableName}
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Approved_Amount > 0
                  AND Approved_Date != '0000-00-00'
                  AND payment_status = 'InstructedPayment'
                --   AND is_exported = 0
                  AND payment_instructed_date != '0000-00-00')
              )";
        } else {

            $query = "SELECT * FROM {$tableName}
              WHERE YearId = ?
              AND (
                  (Status = 'Closed'
                  AND Total_Claim > 0
                  AND Finance_Amount > 0
                  AND Finance_Date != '0000-00-00'
                  AND Fin_AppBy > 0
                  AND Fin_AppDate != '0000-00-00'
                  AND Fin_AppDate != ''
                  AND Fin_AppDate != '1970-01-01'
                --   AND is_exported = 0
                  AND payment_status = 'InstructedPayment'
                  AND payment_instructed_date != '0000-00-00')
              )";
        }

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND Month = ?';
            $bindings[] = $month;
        }

        // Move condition
        if ($moveCondition != '1=1') {
            $query .= " AND Move_FocuseSheet = 'N'";
        }

        // ✅ Export / Hold Filter
        if ($exportFilter !== null && $exportFilter !== '') {
            $query .= ' AND is_exported = ?';
            $bindings[] = $exportFilter;
        } else {
            $query .= ' AND is_exported = ?';
            $bindings[] = 0;
        }

        $query .= ' ORDER BY payment_approved_date DESC, Month ASC, EmployeeID ASC';
        // dd($query);
        // Count
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $this->perPage;

        $query .= ' LIMIT '.(int) $offset.', '.(int) $this->perPage;

        $records = $connection->select($query, $bindings);
        $records = array_map(fn ($record) => (array) $record, $records);

        return view('treasury.instructed', compact('records', 'data', 'totalRecords', 'page'));
    }

    protected function confirmedPayments(Request $request, $data)
    {
        $yearId = $data['yearId'];
        $month = $data['v'];
        $moveCondition = $data['moveCondition'];

        $connection = DB::connection(session('expense_connection'));
        $tableName = 'y'.$yearId.'_monthexpensefinal';

        $query = "SELECT
            t.*
          FROM {$tableName} t
          WHERE t.YearId = ?
          AND t.Status = 'Closed'
          AND t.Total_Claim > 0
          AND t.Finance_Amount > 0
          AND t.Finance_Date != '0000-00-00'
          AND t.Fin_AppBy > 0
          AND t.Fin_AppDate NOT IN ('0000-00-00', '', '1970-01-01')
          AND (t.Fin_PayAmt != '' OR t.payment_status = 'ConfirmedPayment')";

        $bindings = [$yearId];

        // Month filter
        if ($month > 0) {
            $query .= ' AND t.Month = ?';
            $bindings[] = $month;
        }

        // Move Focus Sheet filter
        if ($moveCondition != '1=1') {
            $query .= " AND t.Move_FocuseSheet = 'N'";
        }

        // Employee search filter (name or code)
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= ' AND t.EmployeeID IN (SELECT EmployeeID FROM hrims.hrm_employee WHERE Fname LIKE ? OR Sname LIKE ? OR Lname LIKE ? OR EmpCode LIKE ?)';
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
        }

        // Payment date range filter
        if ($request->filled('payment_date_from')) {
            $dateFrom = $request->payment_date_from;
            $query .= ' AND (t.payment_confirmed_date >= ? )';
            $bindings[] = $dateFrom;
        }

        if ($request->filled('payment_date_to')) {
            $dateTo = $request->payment_date_to;
            $query .= ' AND (t.payment_confirmed_date <= ? )';
            $bindings[] = $dateTo;
        }

        // Amount range filter
        if ($request->filled('min_amount')) {
            $minAmount = $request->min_amount;
            $query .= ' AND (t.payment_amount >= ?)';
            $bindings[] = $minAmount;
        }

        if ($request->filled('max_amount')) {
            $maxAmount = $request->max_amount;
            $query .= ' AND (t.payment_amount <= ?)';
            $bindings[] = $maxAmount;
        }

        // UTR number filter
        if ($request->filled('utr_no')) {
            $utrNo = $request->utr_no;
            $query .= ' AND t.rbi_utr_reference_number LIKE ?';
            $bindings[] = "%{$utrNo}%";
        }

        // Document number filter
        if ($request->filled('doc_no')) {
            $docNo = $request->doc_no;
            $query .= ' AND t.DocNo LIKE ?';
            $bindings[] = "%{$docNo}%";
        }

        // Payment status filter
        if ($request->filled('payment_status')) {
            $paymentStatus = $request->payment_status;
            $query .= ' AND t.payment_status = ?';
            $bindings[] = $paymentStatus;
        }

        // Move Focus Sheet filter
        if ($request->filled('move_focus_sheet')) {
            $moveFocusSheet = $request->move_focus_sheet;
            $query .= ' AND t.Move_FocuseSheet = ?';
            $bindings[] = $moveFocusSheet;
        }

        $query .= " ORDER BY COALESCE(NULLIF(t.payment_confirmed_date, '0000-00-00'),
              NULLIF(t.payment_instructed_date, '0000-00-00'),
              NULLIF(t.Fin_PayDate, '0000-00-00')) DESC,
              t.Month ASC, t.EmployeeID ASC";

        // Count query
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $page = $request->get('page', 1);
        $offset = ($page - 1) * 15;

        $query .= ' LIMIT '.(int) $offset.', 15';

        $records = $connection->select($query, $bindings);

        // Add bank details to each record
        $records = array_map(function ($record) {
            $recordArray = (array) $record;

            // Fetch bank details using helper function
            $bankDetails = $this->getEmployeeBankDetails($recordArray['EmployeeID'] ?? 0);
            // dd($recordArray['EmployeeID']);
            // dd($bankDetails);
            // Add bank details to the record
            $recordArray['beneficiary_account_number'] = $bankDetails['account_number'];
            $recordArray['beneficiary_name'] = getUser($recordArray['EmployeeID']);
            $recordArray['bank_name'] = $bankDetails['bank_name'];
            $recordArray['ifsc_code'] = $bankDetails['ifsc_code'];

            return $recordArray;
        }, $records);

        // dd($records);
        return view('expense-payments.confirmed', compact('records', 'data', 'totalRecords', 'page'));
    }

    /** Unsettled Payments (chkval=5) */

    /**
     * Unsettled Payments (chkval=5)
     */
    protected function unsettledPayments(Request $request, $data)
    {
        $month = $data['v'];
        $yearId = session('year_id');

        $connection = DB::connection(session('expense_connection'));

        $query = "
        SELECT
            ep.*,
            eg.AccountNo as account_no,
            e.Fname,
            e.Sname,
            e.Lname,
            e.EmpCode
        FROM y{$yearId}_monthexpensefinal ep
        LEFT JOIN hrims.hrm_employee_general eg
            ON eg.EmployeeID = ep.EmployeeID
        LEFT JOIN hrims.hrm_employee e
            ON e.EmployeeID = ep.EmployeeID
        WHERE ep.payment_status = 'Unsettled'
    ";

        $bindings = [];

        // Month filter
        if ($month > 0) {
            $query .= ' AND MONTH(ep.created_at) = ?';
            $bindings[] = $month;
        }

        // Employee search filter (name or code)
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= ' AND (e.Fname LIKE ? OR e.Sname LIKE ? OR e.Lname LIKE ? OR e.EmpCode LIKE ? OR e.EmpCode_New LIKE ?)';
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
        }

        // Unsettled date range filter
        if ($request->filled('unsettled_date_from')) {
            $dateFrom = $request->unsettled_date_from;
            $query .= ' AND ep.payment_unsettled_date >= ?';
            $bindings[] = $dateFrom;
        }

        if ($request->filled('unsettled_date_to')) {
            $dateTo = $request->unsettled_date_to;
            $query .= ' AND ep.payment_unsettled_date <= ?';
            $bindings[] = $dateTo;
        }

        // Amount range filter
        if ($request->filled('min_amount')) {
            $minAmount = $request->min_amount;
            $query .= ' AND ep.payment_amount >= ?';
            $bindings[] = $minAmount;
        }

        if ($request->filled('max_amount')) {
            $maxAmount = $request->max_amount;
            $query .= ' AND ep.payment_amount <= ?';
            $bindings[] = $maxAmount;
        }

        // Discrepancy amount filter (difference between payment_amount and Finance_Amount)
        if ($request->filled('min_discrepancy')) {
            $minDiscrepancy = $request->min_discrepancy;
            $query .= ' AND (ep.payment_amount - ep.Finance_Amount) >= ?';
            $bindings[] = $minDiscrepancy;
        }

        if ($request->filled('max_discrepancy')) {
            $maxDiscrepancy = $request->max_discrepancy;
            $query .= ' AND (ep.payment_amount - ep.Finance_Amount) <= ?';
            $bindings[] = $maxDiscrepancy;
        }

        // Reason search filter
        if ($request->filled('reason_search')) {
            $reasonSearch = $request->reason_search;
            $query .= ' AND ep.payment_unsettled_reason LIKE ?';
            $bindings[] = "%{$reasonSearch}%";
        }

        // Adjustment amount filter
        if ($request->filled('has_adjustment')) {
            if ($request->has_adjustment == 'yes') {
                $query .= ' AND (ep.total_adjustment > 0 OR ep.total_activity_adjustment > 0 OR ep.total_expense_adjustment > 0)';
            } elseif ($request->has_adjustment == 'no') {
                $query .= ' AND (ep.total_adjustment IS NULL OR ep.total_adjustment = 0)
                        AND (ep.total_activity_adjustment IS NULL OR ep.total_activity_adjustment = 0)
                        AND (ep.total_expense_adjustment IS NULL OR ep.total_expense_adjustment = 0)';
            }
        }

        // Sorting
        $sortField = $request->get('sort_field', 'payment_unsettled_date');
        $sortOrder = $request->get('sort_order', 'DESC');

        $allowedSortFields = [
            'payment_amount',
            'Finance_Amount',
            'payment_unsettled_date',
            'total_adjustment',
            'employee_name',
            'EmpCode',
        ];

        if (in_array($sortField, $allowedSortFields)) {
            if ($sortField == 'employee_name') {
                $query .= " ORDER BY e.Fname $sortOrder, e.Sname $sortOrder, e.Lname $sortOrder";
            } elseif ($sortField == 'EmpCode') {
                $query .= " ORDER BY e.EmpCode $sortOrder";
            } else {
                $query .= " ORDER BY ep.$sortField $sortOrder";
            }
        } else {
            $query .= ' ORDER BY ep.payment_unsettled_date DESC, ep.id DESC';
        }

        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $totalRecords = $connection->selectOne($countQuery, $bindings)->total;

        // Pagination
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $query .= " LIMIT {$offset}, {$perPage}";

        $records = $connection->select($query, $bindings);

        // Convert + format data
        $records = array_map(function ($record) {
            $record = (array) $record;

            // Full Name
            $record['employee_name'] = trim(
                ($record['Fname'] ?? '').' '
                    .($record['Sname'] ?? '').' '
                    .($record['Lname'] ?? '')
            );

            // Employee Code
            $record['employee_code'] = $record['EmpCode'] ?? '-';

            // Calculate discrepancy
            $record['discrepancy'] = ($record['payment_amount'] ?? 0) - ($record['Finance_Amount'] ?? 0);

            return $record;
        }, $records);

        // Get summary statistics
        $summary = $this->getUnsettledSummary($yearId, $month, $request);

        return view('expense-payments.unsettled', compact('records', 'data', 'totalRecords', 'page', 'summary', 'perPage'));
    }

    /**
     * Get summary statistics for unsettled payments
     */
    private function getUnsettledSummary($yearId, $month, $request)
    {
        $connection = DB::connection(session('expense_connection'));
        $query = "
        SELECT
            COUNT(*) as total_records,
            COALESCE(SUM(ep.payment_amount), 0) as total_payment_amount,
            COALESCE(SUM(ep.Finance_Amount), 0) as total_finance_amount,
            COALESCE(SUM(ep.payment_amount - ep.Finance_Amount), 0) as total_discrepancy,
            COALESCE(SUM(ep.total_adjustment), 0) as total_adjustment,
            COUNT(CASE WHEN ep.total_adjustment > 0 THEN 1 END) as records_with_adjustment
        FROM y{$yearId}_monthexpensefinal ep
        LEFT JOIN hrims.hrm_employee e ON e.EmployeeID = ep.EmployeeID
        WHERE ep.payment_status = 'Unsettled'
    ";

        $bindings = [];

        // Apply same filters as main query for summary
        if ($month > 0) {
            $query .= ' AND MONTH(ep.created_at) = ?';
            $bindings[] = $month;
        }

        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query .= ' AND (e.Fname LIKE ? OR e.Sname LIKE ? OR e.Lname LIKE ? OR e.EmpCode LIKE ?)';
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
        }

        if ($request->filled('unsettled_date_from')) {
            $query .= ' AND ep.payment_unsettled_date >= ?';
            $bindings[] = $request->unsettled_date_from;
        }

        if ($request->filled('unsettled_date_to')) {
            $query .= ' AND ep.payment_unsettled_date <= ?';
            $bindings[] = $request->unsettled_date_to;
        }

        $result = $connection->selectOne($query, $bindings);

        return (array) $result;
    }

    /**
     * Get employee bank details
     */
    public function getEmployeeBankDetails($employeeId)
    {
        try {
            $bankDetails = DB::connection('hrims')->selectOne('
            SELECT
                AccountNo as account_number,
                BankName as bank_name,
                BankIfscCode as ifsc_code
            FROM hrm_employee_general
            WHERE EmployeeID = ?
            LIMIT 1
        ', [$employeeId]);

            if ($bankDetails) {
                return [
                    'account_number' => $bankDetails->account_number ?? '',
                    'account_name' => '',  // Not available in table
                    'bank_name' => $bankDetails->bank_name ?? '',
                    'ifsc_code' => $bankDetails->ifsc_code ?? '',
                ];
            }

            return [
                'account_number' => '',
                'account_name' => '',
                'bank_name' => '',
                'ifsc_code' => '',
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching bank details: '.$e->getMessage());

            return [
                'account_number' => '',
                'account_name' => '',
                'bank_name' => '',
                'ifsc_code' => '',
            ];
        }
    }

    /**
     * Get employee full name
     */
    public function getUser($employeeId)
    {
        $result = DB::connection('hrims')->selectOne('SELECT Fname, Sname, Lname FROM hrm_employee WHERE EmployeeID = ?', [$employeeId]);

        if ($result) {
            return trim($result->Fname.' '.$result->Sname.' '.$result->Lname);
        }

        return '';
    }

    /**
     * Get employee code
     */
    public function getCode($employeeId)
    {
        $result = DB::connection('hrims')->selectOne('SELECT EmpCode FROM hrm_employee WHERE EmployeeID = ?', [$employeeId]);

        return $result ? $result->EmpCode : '';
    }

    /**
     * Get employee status (with separation check)
     */
    // getStsUser() is defined as a global helper in EmployeeHelper.php


    /**
     * Get employee grade
     */
    public function getGrade($employeeId)
    {
        $result = DB::connection('hrims')->selectOne('
            SELECT gr.grade_name
            FROM hrm_employee_general g
            INNER JOIN core_grades gr ON g.GradeId = gr.id
            WHERE g.EmployeeID = ?
        ', [$employeeId]);

        return $result ? $result->grade_name : '';
    }

    /**
     * Get employee ledger balance
     */
    public function getEmployeeLedgerBalance($employeeId)
    {
        try {
            $result = DB::connection(session('expense_connection'))->selectOne('
                SELECT * FROM employee_ledger_balances
                WHERE employee_id = ?
                ORDER BY id DESC
                LIMIT 1
            ', [$employeeId]);

            if (! $result) {
                return [];
            }

            $r = (array) $result;

            // Map DB fields to legacy keys expected by Blade/old PHP
            return [
                'balance' => $r['balance'] ?? 0,
                'advance_amt_1' => $r['advance_amt_1'] ?? ($r['advance_amount_1'] ?? 0),
                'advance_purpose_1' => $r['advance_purpose_1'] ?? '',
                'advance_month_1' => $r['advance_month_1'] ?? '',
                'advance_activity_id_1' => $r['advance_activity_id_1'] ?? 0,
                'advance_date_1' => $r['advance_date_1'] ?? '',
                'advance_amt_2' => $r['advance_amt_2'] ?? ($r['advance_amount_2'] ?? 0),
                'advance_purpose_2' => $r['advance_purpose_2'] ?? '',
                'advance_month_2' => $r['advance_month_2'] ?? '',
                'advance_activity_id_2' => $r['advance_activity_id_2'] ?? 0,
                'advance_date_2' => $r['advance_date_2'] ?? '',
                'advance_amt_3' => $r['advance_amt_3'] ?? ($r['advance_amount_3'] ?? 0),
                'advance_purpose_3' => $r['advance_purpose_3'] ?? '',
                'advance_month_3' => $r['advance_month_3'] ?? '',
                'advance_activity_id_3' => $r['advance_activity_id_3'] ?? 0,
                'advance_date_3' => $r['advance_date_3'] ?? '',
                'advance_amt_4' => $r['advance_amt_4'] ?? ($r['advance_amount_4'] ?? 0),
                'advance_purpose_4' => $r['advance_purpose_4'] ?? '',
                'advance_month_4' => $r['advance_month_4'] ?? '',
                'advance_activity_id_4' => $r['advance_activity_id_4'] ?? 0,
                'advance_date_4' => $r['advance_date_4'] ?? '',
                'fixed_imprest_amt_1' => $r['fixed_imprest_amt_1'] ?? 0,
                'fixed_imprest_date_1' => $r['fixed_imprest_date_1'] ?? '',
                'fixed_imprest_amt_2' => $r['fixed_imprest_amt_2'] ?? 0,
                'fixed_imprest_date_2' => $r['fixed_imprest_date_2'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('getEmployeeLedgerBalance error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get current month activity advances
     */
    public function getCurrentMonthActivityAdvances($employeeId, $month)
    {
        return getCurrentMonthActivityAdvances($employeeId, $month);
    }

    /**
     * Get current month expense advances
     */
    public function getCurrentMonthExpenseAdvances($employeeId, $month)
    {
        return getCurrentMonthExpenseAdvances($employeeId, $month);
    }

    /**
     * Get all paid advances for employee
     */
    public function getAllPaidAdvances($employeeId, $month = null)
    {
        if ($month !== null) {
            return getAllPaidAdvances($employeeId, $month);
        }

        $conn = session('expense_connection');
        $imprestExclusion = function_exists('buildAdvanceImprestExclusionSql')
            ? buildAdvanceImprestExclusionSql($conn, 'ar', 'ara', 'art')
            : '';

        $sql = "
            SELECT
                ara.id,
                art.type_code,
                art.type_name,
                COALESCE(ara.is_imprest, ar.is_imprest, 0) as is_imprest,
                ara.purpose,
                ara.payment_remark as remark,
                ar.created_by,
                ar.req_app_submit_id,
                COALESCE(ara.payment_amount, ara.final_amount, ara.amount) AS advance_amount,
                ara.payment_date,
                ar.month,
                ar.activity_name_id,
                ct.ClaimName as activity_name
            FROM advance_request_activities ara
            INNER JOIN advance_request_employees are ON are.id = ara.advance_request_employee_id
            INNER JOIN advance_requests ar ON ar.id = are.advance_request_id
            INNER JOIN advance_request_types art ON art.id = ar.advance_type_id
            LEFT JOIN claimtype ct ON ct.ClaimId = ar.activity_name_id
            WHERE ara.payment_status = 'Paid'
            AND art.is_active = 1
            {$imprestExclusion}
            AND ar.deleted_at IS NULL
            AND ara.deleted_at IS NULL
            AND (are.employee_id = ?)
            ORDER BY ar.month ASC, ara.payment_date ASC
        ";

        $results = DB::connection($conn)->select($sql, [$employeeId]);
        if (empty($results) && $conn !== 'expense') {
            try {
                $results = DB::connection('expense')->select($sql, [$employeeId]);
            } catch (\Throwable $e) {}
        }

        $filtered = [];
        foreach ($results as $record) {
            $row = (array) $record;
            $row['is_imprest'] = $row['is_imprest'] ?? 0;
            if (function_exists('isImprestAdvanceRecord')) {
                if (!isImprestAdvanceRecord($row)) {
                    $filtered[] = $row;
                }
            } else {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * Approve claim month (payment approval) - Updated to match core PHP
     */
    public function approveClaimMonth(Request $request)
    {
        try {
            DB::beginTransaction();

            $month = $request->input('month');
            $employeeId = $request->input('employee_id');
            $paymentAmount = $request->input('payment_amount', 0);
            $ledgerBalanceAtApproval = $request->input('ledger_balance_at_approval', 0);
            $actualExpense = $request->input('actual_expense_amount', 0);
            $yearId = session('year_id');
            $userId = Auth::id();

            // Validate inputs
            if ($employeeId <= 0 || $month <= 0 || $month > 12) {
                return response('error: Invalid employee ID or month');
            }

            if ($paymentAmount < 0) {
                return response('error: Payment amount cannot be negative');
            }

            // RET-01: no payment change in an archived year
            if (\App\Services\Retention\RecordRetention::isArchived((string) session('expense_connection'), (int) $yearId)) {
                DB::rollBack();

                return response('error: This financial year is archived (read-only).');
            }

            // FF-01: separated employee -> payable with F&F (the month is put on hold)
            $sepHold = new \App\Services\Payments\SeparationHold((string) session('expense_connection'), (int) $yearId, (int) session('company_id', 1));
            if (config('claim_rules.separation_payment_hold', true) && $sepHold->separationDate((int) $employeeId)) {
                DB::rollBack(); // the hold below must stay
                $sepMsg = $sepHold->blockReason((int) $employeeId, (int) $month);

                return response('error: '.$sepMsg);
            }

            // HARDCOPY-PAY-01: no payment while the previous month's hard copy has not reached HO
            if ($hcMsg = \App\Services\Claims\HardcopyPaymentGate::blockReason((string) session('expense_connection'), (int) $yearId, (int) $employeeId, (int) $month)) {
                DB::rollBack();

                return response('error: '.$hcMsg);
            }

            // Get JSON adjustments from request
            $activityAdjustmentsJson = $request->input('activity_adjustments', '{}');
            $expenseAdjustmentsJson = $request->input('expense_adjustments', '{}');
            $remainingJson = $request->input('remaining_balances', '{}');

            // Carried adjustments
            $carriedActivityAdjustment = $request->input('carried_activity_adjustment', 0);
            $carriedExpenseAdjustment = $request->input('carried_expense_adjustment', 0);

            // Totals
            $totalActivityAdjustment = $request->input('total_activity_adjustment', 0);
            $totalExpenseAdjustment = $request->input('total_expense_adjustment', 0);
            $totalAdjustment = $request->input('total_adjustment', 0);
            $otherAdjustment = $request->input('other_adjustment', 0);
            $otherAdjustmentRemark = $request->input('other_adjustment_remark', '');

            $totalAdvancePayment = $totalActivityAdjustment + $totalExpenseAdjustment;

            // LV-C04: work the figures out on the server; refuse when the screen's figures differ
            $actLinesIn = json_decode(stripslashes((string) $activityAdjustmentsJson), true);
            $expLinesIn = json_decode(stripslashes((string) $expenseAdjustmentsJson), true);
            [$figErrors, $fig] = \App\Services\Payments\PaymentFigures::check(
                (string) session('expense_connection'), (int) $employeeId, (int) $month, (int) $yearId,
                is_array($actLinesIn) ? $actLinesIn : [], is_array($expLinesIn) ? $expLinesIn : [], (float) $otherAdjustment,
                $request->only(['payment_amount', 'actual_expense_amount', 'total_activity_adjustment', 'total_expense_adjustment', 'total_adjustment'])
            );
            if ($figErrors) {
                DB::rollBack();
                Log::warning('LV-C04 payment figures refused', ['emp' => $employeeId, 'month' => $month, 'year' => $yearId, 'errors' => $figErrors]);

                return response('error: '.implode(' ', $figErrors));
            }
            $paymentAmount = $fig['payment_amount'];
            $actualExpense = $fig['actual_expense_amount'];
            $totalActivityAdjustment = $fig['total_activity_adjustment'];
            $totalExpenseAdjustment = $fig['total_expense_adjustment'];
            $carriedActivityAdjustment = $fig['carried_activity_adjustment'];
            $carriedExpenseAdjustment = $fig['carried_expense_adjustment'];
            $totalAdjustment = $fig['total_adjustment'];
            $totalAdvancePayment = $fig['advance_payment_amount'];

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            // Check if table exists
            $tableExists = DB::connection(session('expense_connection'))->selectOne('
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = ?
            ', [$tableName]);

            if (! $tableExists || $tableExists->count == 0) {
                DB::rollBack();

                return response()->json(['success' => false, 'error' => 'Table '.$tableName.' not found'], 404);
            }

            // Update month expense final - matching core PHP fields exactly
            $sql = "UPDATE {$tableName}
                    SET payment_status = 'Approved',
                        payment_amount = ?,
                        ledger_balance_at_approval = ?,
                        carried_balance_json = ?,
                        adjust_activity_amount = ?,
                        adjust_expense_amount = ?,
                        activity_adjustments_json = ?,
                        expense_adjustments_json = ?,
                        carried_activity_adjustment = ?,
                        carried_expense_adjustment = ?,
                        total_activity_adjustment = ?,
                        total_expense_adjustment = ?,
                        other_adjustment = ?,
                        other_adjustment_remark = ?,
                        total_adjustment = ?,
                        advance_payment_amount = ?,
                        actual_expense_amount = ?,
                        payment_approved_date = NOW()";

            if ($userId > 0) {
                $sql .= ', payment_approved_by = ?';
            }

            $sql .= " WHERE EmployeeID = ? AND Month = ? AND YearId = ? AND payment_status = 'Pending'";

            $bindings = [
                $paymentAmount,
                $ledgerBalanceAtApproval,
                $remainingJson,
                $totalActivityAdjustment,
                $totalExpenseAdjustment,
                $activityAdjustmentsJson,
                $expenseAdjustmentsJson,
                $carriedActivityAdjustment,
                $carriedExpenseAdjustment,
                $totalActivityAdjustment,
                $totalExpenseAdjustment,
                $otherAdjustment,
                $otherAdjustmentRemark,
                $totalAdjustment,
                $totalAdvancePayment,
                $actualExpense,
            ];

            if ($userId > 0) {
                $bindings[] = $userId;
            }

            $bindings[] = $employeeId;
            $bindings[] = $month;
            $bindings[] = $yearId;

            $pclIds = DB::connection(session('expense_connection'))->table($tableName)->where('EmployeeID', $employeeId)->where('Month', $month)->where('YearId', $yearId)->where('payment_status', 'Pending')->pluck('id')->all();
            $pclBefore = \App\Support\PaymentChangeLog::snapshot(session('expense_connection'), $tableName, $pclIds); // PAY-LOG-01
            $updated = DB::connection(session('expense_connection'))->update($sql, $bindings);

            if (! $updated) {
                DB::rollBack();

                return response('error: No pending record found to approve.');
            }
            \App\Support\PaymentChangeLog::diff(session('expense_connection'), $tableName, $pclBefore, 'month_final', 'approve_payment', $otherAdjustmentRemark ?: null);

            // ==================== STORE ADVANCE ADJUSTMENTS (LV-C04: added to what was adjusted before) ====================
            foreach ([[$actLinesIn, 'Adjusted during payment approval'], [$expLinesIn, 'Expense advance adjusted during payment approval']] as [$lines, $remark]) {
                foreach ((is_array($lines) ? $lines : []) as $adjustmentData) {
                    $adjustmentAmount = (float) ($adjustmentData['amount'] ?? 0);
                    $advanceActivityId = $adjustmentData['advance_activity_id'] ?? 0;
                    if ($adjustmentAmount > 0 && is_numeric($advanceActivityId) && (int) $advanceActivityId > 0) {
                        \App\Services\Payments\PaymentFigures::applyAdjustment((string) session('expense_connection'), (int) $advanceActivityId, $adjustmentAmount, (int) $userId, (int) $month, $remark);
                    }
                }
            }

            $existingLedger = DB::connection(session('expense_connection'))->selectOne('
                       SELECT *
                       FROM employee_ledger_balances
                       WHERE year_id = ? AND employee_id = ? AND deleted_at IS NULL
                       LIMIT 1
                   ', [$yearId, $employeeId]);

            // The request already carries a JSON string. Decode it (tolerating a
            // legacy double-encoded value) and store it encoded exactly once.
            $remainingBalances = \App\Support\LedgerJson::decode($remainingJson);

            $totalRemaining = 0.0;

            foreach ($remainingBalances as $category => $items) {
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    $totalRemaining += (float) ($item['remaining_amount'] ?? 0);
                }
            }

            if ($existingLedger) {
                // closing = fixed imprest + (opening advances + advances paid - adjustments), computed
                // by the same engine as the Ledger Summary after this month's deductions were saved.
                try {
                    $closingBalance = $this->computeEmployeeLedgerBalance($employeeId)['total_balance'];
                } catch (\Throwable $balEx) {
                    Log::warning('closing_balance fallback for employee '.$employeeId.': '.$balEx->getMessage());
                    $closingBalance = \App\Support\LedgerJson::closingBalance($existingLedger, $remainingBalances);
                }

                DB::connection(session('expense_connection'))->update('
            UPDATE employee_ledger_balances
            SET
                remaining_balances_json = ?,
                closing_balance = ?,
                updated_at = NOW()
            WHERE id = ?
        ', [
                    \App\Support\LedgerJson::encode($remainingBalances),
                    $closingBalance,
                    $existingLedger->id,
                ]);
            }

            DB::commit();

            return response('success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve Claim Error: '.$e->getMessage());

            return response('error: Database update failed - '.$e->getMessage());
        }
    }

    /**
     * Release multiple payments - Updated to match core PHP
     */
    public function releasePayments(Request $request)
    {
        try {
            DB::beginTransaction();

            $rows = $request->input('rows', []);
            $released = 0;
            $failed = 0;
            $yearId = session('year_id');
            $sameUser = [];

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            foreach ($rows as $row) {
                try {
                    $empId = (int) $row['employee_id'];
                    $month = (int) $row['month'];
                    $amount = (float) $row['amount'];

                    if ($amount < 0) {
                        $failed++;

                        continue;
                    }

                    // LV-C05: the approver of this payment may not release it (maker-checker)
                    if ($this->makerCheckerBlocks($tableName, fn ($q) => $q->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->where('payment_status', 'Approved'))) {
                        $failed++;
                        $sameUser[] = "{$empId}/{$month}";

                        continue;
                    }
                    $pclBefore = \App\Support\PaymentChangeLog::snapshot(session('expense_connection'), $tableName, DB::connection(session('expense_connection'))->table($tableName)->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->where('payment_status', 'Approved')->pluck('id')->all()); // PAY-LOG-01
                    $updated = DB::connection(session('expense_connection'))->update("
                        UPDATE {$tableName}
                        SET payment_status = 'InstructedPayment',
                            payment_instructed_date = NOW()".$this->instructedBySql()."
                        WHERE EmployeeID = ?
                        AND Month = ?
                        AND YearId = ?
                        AND payment_amount >= 0
                        AND payment_status = 'Approved'
                    ", array_merge($this->instructedByBinding(), [$empId, $month, $yearId]));
                    \App\Support\PaymentChangeLog::diff(session('expense_connection'), $tableName, $pclBefore, 'month_final', 'release_payments');

                    if ($updated) {
                        $released++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'released' => $released,
                'failed' => $failed,
                'total' => count($rows),
                'refused_same_approver' => $sameUser,
                'message' => $sameUser ? 'Not released (you approved these, another person must release): '.implode(', ', $sameUser) : null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /** LV-C05: true when maker-checker is on and the current user approved any of the scoped rows. */
    private function makerCheckerBlocks(string $tableName, callable $scope): bool
    {
        if (! config('payment_controls.maker_checker', true) || $this->isSuperAdmin()) {
            return false;
        }
        $q = DB::connection(session('expense_connection'))->table($tableName);
        $scope($q);
        $hit = $q->where('payment_approved_by', (int) Auth::id())->exists();
        if ($hit && config('payment_controls.maker_checker_mode', 'enforce') === 'log') {
            Log::warning('Maker-checker (log only): same user approved and released', ['user' => Auth::id(), 'table' => $tableName]);

            return false;
        }
        if ($hit) {
            Log::warning('Payment release refused: same user approved', ['user' => Auth::id(), 'table' => $tableName]);
        }

        return $hit;
    }

    private function hasInstructedBy(string $tableName): bool
    {
        static $cache = [];
        $key = session('expense_connection').'|'.$tableName;

        return $cache[$key] ??= \Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->hasColumn($tableName, 'payment_instructed_by');
    }

    private function instructedByColumn(string $tableName): array
    {
        return $this->hasInstructedBy($tableName) ? ['payment_instructed_by' => (int) Auth::id()] : [];
    }

    private function instructedBySql(): string
    {
        $t = 'y'.session('year_id').'_monthexpensefinal';

        return $this->hasInstructedBy($t) ? ', payment_instructed_by = ?' : '';
    }

    private function instructedByBinding(): array
    {
        $t = 'y'.session('year_id').'_monthexpensefinal';

        return $this->hasInstructedBy($t) ? [(int) Auth::id()] : [];
    }

    /**
     * Check if user is Super Admin
     */
    public function isSuperAdmin($user = null): bool
    {
        if (function_exists('isSuperAdmin')) {
            return isSuperAdmin($user);
        }

        $user = $user ?: Auth::user();
        if (!$user) {
            return false;
        }

        if ((int)$user->id === 1) {
            return true;
        }

        if (session('user_type') === 'Super Admin' || session('user_type') === 'super_admin') {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin'])) {
            return true;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('Super Admin') || $user->hasRole('super_admin'))) {
            return true;
        }

        if (method_exists($user, 'can') && $user->can('super_admin')) {
            return true;
        }

        return false;
    }

    /**
     * Revert payment stage back to previous stage (only up to Instructed).
     * Accessible ONLY by Super Admin.
     * Maintained audit log with reason in activity_log and expense_payment_revert_logs.
     */
    public function revertPaymentStage(Request $request)
    {
        if (!$this->isSuperAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Revert action is restricted to Super Admin only.',
            ], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $ids = $request->input('ids');
        $reason = trim($request->input('reason'));
        $yearId = session('year_id');
        $companyId = session('company_id', 1);
        $expenseConn = session('expense_connection') ?: DatabaseSwitcher::getExpenseConnection($companyId);
        $tableName = 'y'.$yearId.'_monthexpensefinal';
        $user = Auth::user();
        $userId = $user ? $user->id : 0;
        $userName = $user ? $user->name : 'Super Admin';
        $userEmpCode = session('emp_code') ?? ($user->employee?->EmpCode ?? null);

        $this->ensureRevertLogsTableExists();

        DB::beginTransaction();

        $revertedCount = 0;
        $errors = [];

        try {
            foreach ($ids as $id) {
                $record = DB::connection($expenseConn)->table($tableName)->where('id', $id)->first();

                if (!$record) {
                    $errors[] = "Record #{$id} not found.";
                    continue;
                }
                $pclBefore = \App\Support\PaymentChangeLog::snapshot($expenseConn, $tableName, [$id]); // PAY-LOG-01

                $currentStatus = $record->payment_status;
                $empId = $record->EmployeeID;
                $month = $record->Month;
                $amount = (float)($record->payment_amount ?? $record->Finance_Amount ?? $record->Approved_Amount ?? 0);

                if ($currentStatus === 'ConfirmedPayment') {
                    $errors[] = "Record #{$id} (Emp: {$empId}, Month: {$month}) cannot be reverted. Reversion is only permitted up to Instructed stage.";
                    continue;
                }

                if ($currentStatus === 'Pending') {
                    $errors[] = "Record #{$id} is already in Pending stage.";
                    continue;
                }

                $newStatus = '';

                if ($currentStatus === 'Approved') {
                    // Revert Approved -> Pending
                    $newStatus = 'Pending';

                    // Rollback advance adjustments in advance_request_activities
                    $this->rollbackAdvanceAdjustments($expenseConn, $record);

                    // Revert record in monthexpensefinal
                    DB::connection($expenseConn)->table($tableName)
                        ->where('id', $id)
                        ->update([
                            'payment_status' => 'Pending',
                            'payment_approved_date' => null,
                            'payment_approved_by' => 0,
                            'payment_amount' => 0,
                            'ledger_balance_at_approval' => null,
                            'carried_balance_json' => null,
                            'adjust_activity_amount' => 0,
                            'adjust_expense_amount' => 0,
                            'activity_adjustments_json' => null,
                            'expense_adjustments_json' => null,
                            'carried_activity_adjustment' => 0,
                            'carried_expense_adjustment' => 0,
                            'total_activity_adjustment' => 0,
                            'total_expense_adjustment' => 0,
                            'other_adjustment' => 0,
                            'other_adjustment_remark' => null,
                            'total_adjustment' => 0,
                            'advance_payment_amount' => 0,
                            'actual_expense_amount' => 0,
                            'is_exported' => 0,
                            'hold_remark' => null,
                            'payment_instructed_date' => null,
                        ]);

                } elseif ($currentStatus === 'InstructedPayment') {
                    // Revert Instructed -> Approved
                    $newStatus = 'Approved';

                    DB::connection($expenseConn)->table($tableName)
                        ->where('id', $id)
                        ->update([
                            'payment_status' => 'Approved',
                            'payment_instructed_date' => null,
                            'is_exported' => 0,
                            'hold_remark' => null,
                        ]);
                } else {
                    $errors[] = "Record #{$id} has unsupported status '{$currentStatus}' for reversion.";
                    continue;
                }

                \App\Support\PaymentChangeLog::diff($expenseConn, $tableName, $pclBefore, 'month_final', 'revert_payment_stage', $reason);
                // Log into activity_log
                try {
                    DB::table('activity_log')->insert([
                        'log_name' => 'expense_payment',
                        'description' => "Expense payment for Employee #{$empId} (Month: {$month}, Year: {$yearId}) reverted from {$currentStatus} to {$newStatus} by {$userName}. Reason: {$reason}",
                        'subject_type' => 'ExpensePayment',
                        'subject_id' => $id,
                        'event' => 'revert_payment_stage',
                        'causer_type' => 'App\\Models\\User',
                        'causer_id' => $userId,
                        'properties' => json_encode([
                            'record_id' => $id,
                            'employee_id' => $empId,
                            'month' => $month,
                            'year_id' => $yearId,
                            'amount' => $amount,
                            'previous_status' => $currentStatus,
                            'new_status' => $newStatus,
                            'reason' => $reason,
                            'reverted_by_id' => $userId,
                            'reverted_by_name' => $userName,
                            'reverted_at' => now()->toDateTimeString(),
                            'company_id' => $companyId,
                        ]),
                        'batch_uuid' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $logEx) {
                    Log::error('revertPaymentStage activity_log error: ' . $logEx->getMessage());
                }

                // Log into expense_payment_revert_logs
                try {
                    DB::table('expense_payment_revert_logs')->insert([
                        'company_id' => $companyId,
                        'year_id' => $yearId,
                        'record_id' => $id,
                        'employee_id' => $empId,
                        'claim_month' => $month,
                        'amount' => $amount,
                        'from_stage' => $currentStatus,
                        'to_stage' => $newStatus,
                        'reason' => $reason,
                        'reverted_by' => $userId,
                        'reverted_by_name' => $userName,
                        'reverted_by_code' => $userEmpCode,
                        'ip_address' => $request->ip(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $revLogEx) {
                    Log::error('revertPaymentStage expense_payment_revert_logs error: ' . $revLogEx->getMessage());
                }

                $revertedCount++;
            }

            if ($revertedCount === 0 && !empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => implode(' ', $errors),
                ], 422);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Successfully reverted {$revertedCount} payment record(s) to previous stage." . (!empty($errors) ? ' Warning: ' . implode(' ', $errors) : ''),
                'reverted_count' => $revertedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('revertPaymentStage exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to revert payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rollback advance adjustments when reverting from Approved to Pending
     */
    protected function rollbackAdvanceAdjustments($expenseConn, $record)
    {
        try {
            $advActivityIds = [];
            $monthAmounts = []; // LV-C04: what THIS month adjusted per advance line, undone by subtraction

            foreach (['activity_adjustments_json', 'expense_adjustments_json'] as $jsonCol) {
                if (empty($record->{$jsonCol})) {
                    continue;
                }
                $data = json_decode(stripslashes((string) $record->{$jsonCol}), true);
                if (! is_array($data)) {
                    $data = json_decode((string) $record->{$jsonCol}, true);
                }
                foreach ((is_array($data) ? $data : []) as $item) {
                    $aid = $item['advance_activity_id'] ?? 0;
                    if (is_numeric($aid) && (int) $aid > 0) {
                        $monthAmounts[(int) $aid] = ($monthAmounts[(int) $aid] ?? 0) + (float) ($item['amount'] ?? 0);
                    }
                }
            }
            foreach ($monthAmounts as $aid => $amt) {
                \App\Services\Payments\PaymentFigures::undoAdjustment($expenseConn, $aid, $amt);
            }

            // Also check advances adjusted against this employee & month
            $empCode = null;
            try {
                $empCode = DB::connection(session('hrims_connection', 'hrims'))
                    ->table('hrm_employee')
                    ->where('EmployeeID', $record->EmployeeID)
                    ->value('EmpCode');
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                if (\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable('advance_request_activities') &&
                    \Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable('advance_request_employees')) {
                    $query = DB::connection($expenseConn)->table('advance_request_activities as ara')
                        ->join('advance_request_employees as are', 'are.id', '=', 'ara.advance_request_employee_id')
                        ->where('ara.adjusted_against_month', $record->Month)
                        ->where(function($q) use ($record, $empCode) {
                            $q->where('are.employee_id', $record->EmployeeID);
                            if ($empCode) {
                                $q->orWhere('are.employee_id', $empCode);
                            }
                        });

                    $extraIds = $query->pluck('ara.id')->toArray();
                    if (!empty($extraIds)) {
                        $advActivityIds = array_unique(array_merge($advActivityIds, $extraIds));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Rollback advance adjustment month lookup failed: ' . $e->getMessage());
            }

            // Lines found only through adjusted_against_month (no amount in this month's JSON): old behaviour
            $advActivityIds = array_values(array_diff($advActivityIds, array_keys($monthAmounts)));
            if (!empty($advActivityIds)) {
                $advActivities = DB::connection($expenseConn)->table('advance_request_activities')
                    ->whereIn('id', $advActivityIds)
                    ->get();

                foreach ($advActivities as $adv) {
                    $origAmt = $adv->payment_amount ?? $adv->final_amount ?? $adv->amount ?? 0;
                    DB::connection($expenseConn)->table('advance_request_activities')
                        ->where('id', $adv->id)
                        ->update([
                            'adjustment_amount' => 0,
                            'remaining_balance' => $origAmt,
                            'adjusted_against_month' => null,
                            'adjustment_date' => null,
                            'adjustment_by' => null,
                            'adjustment_remarks' => null,
                        ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('rollbackAdvanceAdjustments error: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the revert logs table exists
     */
    protected function ensureRevertLogsTableExists()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('expense_payment_revert_logs')) {
                \Illuminate\Support\Facades\Schema::create('expense_payment_revert_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedInteger('company_id')->default(1);
                    $table->unsignedSmallInteger('year_id');
                    $table->unsignedBigInteger('record_id');
                    $table->unsignedInteger('employee_id');
                    $table->unsignedTinyInteger('claim_month');
                    $table->decimal('amount', 12, 2)->default(0);
                    $table->string('from_stage', 50);
                    $table->string('to_stage', 50);
                    $table->text('reason');
                    $table->unsignedBigInteger('reverted_by');
                    $table->string('reverted_by_name', 150)->nullable();
                    $table->string('reverted_by_code', 50)->nullable();
                    $table->string('ip_address', 45)->nullable();
                    $table->timestamps();

                    $table->index(['employee_id', 'claim_month', 'year_id'], 'idx_revert_emp_month_year');
                    $table->index('record_id', 'idx_revert_record_id');
                    $table->index('reverted_by', 'idx_revert_by');
                    $table->index('created_at', 'idx_revert_created_at');
                });
            }
        } catch (\Exception $e) {
            Log::warning('ensureRevertLogsTableExists failed: ' . $e->getMessage());
        }
    }

    /**
     * Get recent revert logs for Super Admin
     */
    public function getRevertLogs(Request $request)
    {
        if (!$this->isSuperAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $yearId = session('year_id');
        $companyId = session('company_id', 1);
        $recordId = $request->get('record_id');
        $employeeId = $request->get('employee_id');

        try {
            $this->ensureRevertLogsTableExists();

            $query = DB::table('expense_payment_revert_logs')
                ->where('year_id', $yearId)
                ->where('company_id', $companyId)
                ->orderBy('id', 'desc');

            if ($recordId) {
                $query->where('record_id', $recordId);
            }

            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            $logs = $query->limit(50)->get();

            // Enrich with employee names
            $empIds = $logs->pluck('employee_id')->unique()->toArray();
            $empNames = [];
            $empCodes = [];

            if (!empty($empIds)) {
                $employees = DB::connection(session('hrims_connection', 'hrims'))
                    ->table('hrm_employee')
                    ->whereIn('EmployeeID', $empIds)
                    ->select('EmployeeID', 'EmpCode', DB::raw("CONCAT(COALESCE(Fname,''), ' ', COALESCE(Sname, ''), ' ', COALESCE(Lname,'')) as EmpName"))
                    ->get();

                foreach ($employees as $emp) {
                    $empNames[$emp->EmployeeID] = trim($emp->EmpName);
                    $empCodes[$emp->EmployeeID] = $emp->EmpCode;
                }
            }

            $formattedLogs = $logs->map(function ($log) use ($empNames, $empCodes) {
                return [
                    'id' => $log->id,
                    'record_id' => $log->record_id,
                    'employee_id' => $log->employee_id,
                    'employee_name' => $empNames[$log->employee_id] ?? ('Emp #' . $log->employee_id),
                    'employee_code' => $empCodes[$log->employee_id] ?? '',
                    'month_name' => date('F', mktime(0, 0, 0, $log->claim_month, 1)),
                    'amount' => number_format($log->amount, 2),
                    'from_stage' => $log->from_stage,
                    'to_stage' => $log->to_stage,
                    'reason' => $log->reason,
                    'reverted_by_name' => $log->reverted_by_name ?: ('User #' . $log->reverted_by),
                    'reverted_at' => date('d-m-Y H:i', strtotime($log->created_at)),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedLogs,
            ]);

        } catch (\Exception $e) {
            Log::error('getRevertLogs error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Release multiple payments - Updated to match core PHP
     */
    // public function releasePayments(Request $request)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $rows = $request->input('rows', []);
    //         $released = 0;
    //         $failed = 0;
    //         $yearId = session('year_id');

    //         // Log for debugging
    //         \Log::info('Release Payments Called', ['rows' => $rows, 'yearId' => $yearId]);

    //         if (empty($rows)) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'No rows provided for release',
    //             ], 400);
    //         }

    //         $connection = DB::connection(session('expense_connection'));
    //         $tableName = 'y'.$yearId.'_monthexpensefinal';

    //         foreach ($rows as $row) {
    //             try {
    //                 $empId = (int) $row['employee_id'];
    //                 $month = (int) $row['month'];
    //                 $amount = (float) $row['amount'];

    //                 if ($amount < 0) {
    //                     $failed++;
    //                     \Log::warning('Negative amount for release', ['empId' => $empId, 'month' => $month, 'amount' => $amount]);

    //                     continue;
    //                 }

    //                 // First check if record exists and is in Approved status
    //                 $checkRecord = DB::connection(session('expense_connection'))->selectOne("
    //                 SELECT id, payment_status, payment_amount
    //                 FROM {$tableName}
    //                 WHERE EmployeeID = ?
    //                 AND Month = ?
    //                 AND YearId = ?
    //                 AND payment_status = 'Approved'
    //             ", [$empId, $month, $yearId]);

    //                 if (! $checkRecord) {
    //                     $failed++;
    //                     \Log::warning('No Approved record found for release', ['empId' => $empId, 'month' => $month]);

    //                     continue;
    //                 }

    //                 // Update the record
    //                 $updated = DB::connection(session('expense_connection'))->update("
    //                 UPDATE {$tableName}
    //                 SET payment_status = 'InstructedPayment',
    //                     payment_instructed_date = NOW(),
    //                     updated_at = NOW()
    //                 WHERE EmployeeID = ?
    //                 AND Month = ?
    //                 AND YearId = ?
    //                 AND payment_status = 'Approved'
    //             ", [$empId, $month, $yearId]);

    //                 if ($updated) {
    //                     $released++;
    //                     \Log::info('Successfully released payment', ['empId' => $empId, 'month' => $month, 'amount' => $amount]);
    //                 } else {
    //                     $failed++;
    //                     \Log::warning('Update returned 0 rows affected', ['empId' => $empId, 'month' => $month]);
    //                 }
    //             } catch (\Exception $e) {
    //                 $failed++;
    //                 \Log::error('Release error for row: '.json_encode($row).' - '.$e->getMessage());
    //             }
    //         }

    //         DB::commit();

    //         \Log::info('Release completed', ['released' => $released, 'failed' => $failed, 'total' => count($rows)]);

    //         return response()->json([
    //             'status' => 'success',
    //             'released' => $released,
    //             'failed' => $failed,
    //             'total' => count($rows),
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Release Payments Exception: '.$e->getMessage());
    //         \Log::error('Release Payments Trace: '.$e->getTraceAsString());

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /**
     * Get unsettled payment details - Updated to match core PHP
     */
    public function getUnsettledPaymentDetails(Request $request)
    {
        $id = $request->input('id');
        $yearId = session('year_id');

        $payment = DB::connection(session('expense_connection'))->selectOne('
            SELECT *
            FROM y'.$yearId."_monthexpensefinal
            WHERE id = ? AND payment_status = 'Unsettled'
            LIMIT 1
        ", [$id]);

        if ($payment) {
            // Use db_amount if available, otherwise excel_amount (core PHP logic)
            $amount = $payment->db_amount !== null ? $payment->db_amount : $payment->excel_amount;

            return response()->json([
                'success' => true,
                'transaction_refno' => $payment->transaction_refno,
                'beneficiary_name' => $payment->beneficiary_name,
                'account_no' => $payment->account_no,
                'reason' => $payment->reason,
                'amount' => $amount,
                'employee_id' => $payment->employee_id,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Record not found']);
    }

    /**
     * Get available payment records for employee - Updated to match core PHP
     */
    public function getAvailablePaymentRecords(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $yearId = ($request->filled('year_id') ? (int) $request->input('year_id') : null);
        $month = $request->input('month', 0);
        $accountNo = $request->input('accountNo');

        if (empty($accountNo)) {
            return response()->json([
                'success' => false,
                'message' => 'Employee account number is empty',
                'records' => [],
                'count' => 0,
            ]);
        }

        // Query digi_next_payment_file table as in core PHP
        $records = DB::connection(session('expense_connection'))->select("
            SELECT id, transaction_refno, beneficiary_account_number,
                   beneficiary_name, amount, value_date, rbi_utr_reference_number,
                   transaction_status, match_status, import_date
            FROM digi_next_payment_file
            WHERE beneficiary_account_number = ?
            AND match_status = 'PENDING'
            AND is_processed = 0
            ORDER BY value_date DESC, created_at DESC
        ", [$accountNo]);

        $formattedRecords = [];
        foreach ($records as $row) {
            $formattedRecords[] = [
                'id' => $row->id,
                'transaction_refno' => $row->transaction_refno,
                'beneficiary_account_number' => $row->beneficiary_account_number,
                'beneficiary_name' => $row->beneficiary_name,
                'amount' => (int) $row->amount,
                'value_date' => ! empty($row->value_date) && \App\Support\ZeroDate::has($row->value_date)
                    ? date('d-m-Y', strtotime($row->value_date))
                    : '',
                'rbi_utr_reference_number' => $row->rbi_utr_reference_number,
                'transaction_status' => $row->transaction_status,
                'match_status' => $row->match_status,
                'import_date' => $row->import_date,
            ];
        }

        return response()->json([
            'success' => true,
            'records' => $formattedRecords,
            'count' => count($formattedRecords),
            'account_no' => $accountNo,
        ]);
    }

    /**
     * Generate Focus payment export - Updated to match core PHP
     */
    public function generateFocusExport(Request $request)
    {
        try {
            $selectedMonth = $request->input('month', 0);
            $yearId = session('year_id');
            $chkval = $request->input('chkval', 3);

            // Allow 0 (all months)
            if ($selectedMonth < 0 || $selectedMonth > 12) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid month selected',
                ]);
            }

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            // Base query
            $query = "
            SELECT mef.id, mef.EmployeeID, mef.Month, mef.payment_amount,
                   mef.payment_instructed_date
            FROM {$tableName} mef
            WHERE mef.YearId = ?
            AND mef.payment_status = 'InstructedPayment'
            AND (mef.is_exported IS NULL OR mef.is_exported = 0)
        ";

            $params = [$yearId];

            // Apply month filter only if month > 0
            if ($selectedMonth > 0) {
                $query .= ' AND mef.Month = ?';
                $params[] = $selectedMonth;
            }

            $query .= ' ORDER BY mef.payment_instructed_date DESC, mef.EmployeeID ASC';

            $payments = DB::connection(session('expense_connection'))->select($query, $params);

            if (empty($payments)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No payment records found',
                ]);
            }

            $recordIds = [];
            foreach ($payments as $payment) {
                $recordIds[] = $payment->id;
            }

            $timestamp = now()->format('Ymd_His');
            $filename = "Focus_Payment_Export_{$timestamp}.xlsx";

            session(['export_record_ids' => $recordIds]);
            session(['export_table_name' => $tableName]);
            \App\Support\FocusExportLog::record('payment_focus', $payments, $request->except(['_token']), $filename); // FOCUS-LOG-01

            return Excel::download(
                new FocusPaymentExport($payments, $yearId, $recordIds),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Focus Export Error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    // Add this method to mark records as exported
    public function markAsExported(Request $request)
    {
        try {
            $recordIds = session('export_record_ids', []);
            $tableName = session('export_table_name', '');

            if (! empty($recordIds) && ! empty($tableName)) {
                $ids = implode(',', array_map('intval', $recordIds));
                $connection = DB::connection(session('expense_connection'));
                $pclBefore = \App\Support\PaymentChangeLog::snapshot(session('expense_connection'), $tableName, $recordIds); // PAY-LOG-01

                // Update all exported records with base fields
                $connection->update("
                    UPDATE {$tableName}
                    SET is_exported = 1,
                        payment_instructed_date = NOW(),
                        exported_date = NOW(),
                        exported_by = ?
                    WHERE id IN ({$ids})
                ", [Auth::id()]);

                // For records with payment_amount = 0, also mark as ConfirmedPayment
                // (LV-C05: not by the person who approved them - those stay for another user to confirm)
                $mc = (bool) config('payment_controls.maker_checker', true) && ! $this->isSuperAdmin()
                    && config('payment_controls.maker_checker_mode', 'enforce') !== 'log';
                $connection->update("
                    UPDATE {$tableName}
                    SET payment_status = 'ConfirmedPayment',
                        payment_date = NOW(),
                        Fin_PayDate = NOW()
                    WHERE id IN ({$ids})
                    AND (payment_amount IS NULL OR payment_amount = 0)".($mc ? ' AND (payment_approved_by IS NULL OR payment_approved_by <> ?)' : '')."
                ", $mc ? [(int) Auth::id()] : []);

                \App\Support\PaymentChangeLog::diff(session('expense_connection'), $tableName, $pclBefore, 'month_final', 'mark_exported');

                // Clear session
                session()->forget(['export_record_ids', 'export_table_name']);

                return response()->json(['status' => 'success']);
            }

            return response()->json(['status' => 'error', 'message' => 'No records to update']);
        } catch (\Exception $e) {
            Log::error('Mark Exported Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function markAsHold(Request $request)
    {
        try {
            $yearId = session('year_id');

            $request->validate([
                'id' => 'required|integer',
                'status' => 'required|in:0,2',
                'remark' => 'nullable|string|max:500',
            ]);

            $id = $request->id;
            $status = $request->status;
            $remark = $request->remark;

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            $updateData = [
                'is_exported' => $status,
            ];

            if ($status == 2 && ! empty($remark)) {
                $updateData['hold_remark'] = $remark;

            } elseif ($status == 0) {
                $updateData['hold_remark'] = null;

            }

            DB::connection(session('expense_connection'))
                ->table($tableName)
                ->where('id', $id)
                ->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => $status == 2 ? 'Marked as Hold' : 'Unhold Successful',
            ]);
        } catch (\Exception $e) {
            Log::error('Hold Toggle Error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Helper function to get month name
     */
    private function getMonthName($monthNumber)
    {
        $months = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ];

        return $months[$monthNumber] ?? 'Jan';
    }

    /**
     * Update remark
     */
    public function updateRemark(Request $request)
    {
        try {
            $id = $request->input('id');
            $remark = $request->input('finPayRemark');
            $yearId = session('year_id');

            $connection = DB::connection(session('expense_connection'));
            $tableName = 'y'.$yearId.'_monthexpensefinal';

            $updated = DB::connection(session('expense_connection'))->update("
                UPDATE {$tableName}
                SET Fin_PayRemark = ?
                WHERE id = ?
            ", [$remark, $id]);

            return response()->json([
                'success' => true,
                'message' => 'Remark updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update other adjustment amount and remark.
     * This is stored separately and does NOT affect ledger summary.
     */
    public function updateOtherAdjustment(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'other_adjustment' => 'nullable|numeric',
                'other_adjustment_remark' => 'nullable|string|max:500',
            ]);

            $id = $request->input('id');
            $amount = $request->input('other_adjustment', 0);
            $remark = $request->input('other_adjustment_remark', '');
            $yearId = session('year_id');

            $tableName = 'y' . $yearId . '_monthexpensefinal';

            DB::connection(session('expense_connection'))->update("
                UPDATE {$tableName}
                SET other_adjustment = ?, other_adjustment_remark = ?
                WHERE id = ?
            ", [$amount, $remark, $id]);

            return response()->json([
                'success' => true,
                'message' => 'Other adjustment saved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company ID for current user
     */
    public function getUserCompanyId()
    {
        $userId = Auth::id();

        $result = DB::connection('hrims')->selectOne('
            SELECT CompanyId FROM hrm_user WHERE EmployeeID = ?
        ', [$userId]);

        return $result ? $result->CompanyId : null;
    }

    /**
     * Export pay details (placeholder for future implementation)
     */
    public function exportPayDetails(Request $request)
    {
        // Implement export functionality as needed
    }

    /**
     * Import pay details (placeholder for future implementation)
     */
    public function importPayDetails(Request $request)
    {
        // Implement import functionality as needed
    }

    /**
     * Handle all payment actions (single entry point like original PHP)
     */
    public function handleActions(Request $request)
    {
        $action = $request->input('action');

        switch ($action) {
            case 'approve_claim_month':
                return $this->approveClaimMonth($request);
            case 'release_payment':
                return $this->releasePayments($request);
            case 'get_unsettled_payment_details':
                return $this->getUnsettledPaymentDetails($request);
            case 'get_available_payment_records':
                return $this->getAvailablePaymentRecords($request);
            case 'generate_focus_payment_export':
                return $this->generateFocusExport($request);
            default:
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid request or no rows selected',
                ]);
        }
    }

    public function ledgerSummary(Request $request)
    {
        try {
            $company_id = session('company_id', 1);
            $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();
            $filters = OrganogramFilterHelper::extractFilters($request);

            // Aggregate ledger balances across ALL years per employee (not just current year).
            // We GROUP BY employee_id so each employee appears exactly once, with totals
            // summed across all their ledger records.
            $query = DB::table($hrimsDb.'.hrm_employee as e')
                ->leftJoin(DatabaseSwitcher::getExpenseDatabaseName().'.employee_ledger_balances as lb', 'e.EmployeeID', '=', 'lb.employee_id')
                ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
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
                    // Imprest — take the latest non-null values across all years
                    DB::raw('MAX(lb.fixed_imprest_amt_1) as fixed_imprest_amt_1'),
                    DB::raw('MAX(lb.fixed_imprest_date_1) as fixed_imprest_date_1'),
                    DB::raw('MAX(lb.fixed_imprest_amt_2) as fixed_imprest_amt_2'),
                    DB::raw('MAX(lb.fixed_imprest_date_2) as fixed_imprest_date_2'),
                    // Advance slots — take latest non-null values
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
                    // Financial totals — sum across all years
                    DB::raw('SUM(COALESCE(lb.dr, 0)) as total_debit'),
                    DB::raw('SUM(COALESCE(lb.cr, 0)) as total_credit'),
                    DB::raw('SUM(COALESCE(lb.balance, 0)) as current_balance'),
                    DB::raw('SUM(COALESCE(lb.closing_balance, 0)) as closing_balance'),
                    // Latest remark and json
                    DB::raw('MAX(lb.remaining_balances_json) as remaining_balances_json'),
                    DB::raw('MAX(lb.remark) as remark'),
                    DB::raw('MAX(lb.updated_at) as last_updated')
                )
                ->where('e.EmpStatus', '!=', 'D')
                ->where('e.CompanyId', '=', $company_id)
                ->where('e.EmpCode', '>', '0');

            // Apply 10-level organogram filters
            OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'e.EmployeeID', $hrimsDb);

            $query->groupBy(
                    'e.EmployeeID', 'e.Fname', 'e.Sname', 'e.Lname',
                    'e.EmpCode', 'e.EmpCode_New', 'e.ECode',
                    'e.EmpType', 'e.EmpStatus', 'e.CompanyId'
                );

            // Apply filters
            if ($request->filled('status_filter')) {
                if ($request->status_filter == 'active') {
                    $query->where('e.EmpStatus', 'A');
                } elseif ($request->status_filter == 'inactive') {
                    $query->where('e.EmpStatus', '!=', 'A');
                }
            }

            // Company filter
            if ($request->filled('company_id')) {
                $query->where('e.CompanyId', $request->company_id);
            }

            // Employee type filter
            if ($request->filled('emp_type')) {
                $query->where('e.EmpType', $request->emp_type);
            }

            // Search by name or code
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q
                        ->where(DB::raw("CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))"), 'LIKE', "%{$search}%")
                        ->orWhere('e.EmpCode', 'LIKE', "%{$search}%")
                        ->orWhere('e.EmpCode_New', 'LIKE', "%{$search}%")
                        ->orWhere('e.ECode', 'LIKE', "%{$search}%");
                });
            }

            // Balance filters: on the balance actually shown (computed ledger total), not on the
            // imported opening `balance` / never-written `closing_balance` columns.
            if ($request->filled('balance_filter')) {
                $this->applyComputedBalanceFilter($query, (string) $request->balance_filter);
            }

            // Advanced filter for imprest/advance
            if ($request->filled('has_imprest')) {
                if ($request->has_imprest == 'yes') {
                    $query->where(function ($q) {
                        $q->whereRaw('COALESCE(lb.fixed_imprest_amt_1,0) > 0');
                    });
                } elseif ($request->has_imprest == 'no') {
                    $query->where(function ($q) {
                        $q->whereRaw('COALESCE(lb.fixed_imprest_amt_1,0) = 0');
                    });
                }
            }

            // if ($request->filled('has_advance')) {
            //     if ($request->has_advance == 'yes') {
            //         $query->where(function ($q) {
            //             $q->whereRaw('COALESCE(lb.advance_amt_1,0) > 0');
            //         });
            //     } elseif ($request->has_advance == 'no') {
            //         $query->where(function ($q) {
            //             $q->whereRaw('COALESCE(lb.advance_amt_1,0) = 0');
            //         });
            //     }
            // }

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
                    $query
                        ->orderBy('e.Fname', $sortOrder)
                        ->orderBy('e.Sname', $sortOrder)
                        ->orderBy('e.Lname', $sortOrder);
                    break;
            }

            $perPage = $request->get('per_page', 20);
            $ledgerRecords = $query->paginate($perPage);

            $totalRecords = $ledgerRecords->total();

            // Calculate summary statistics
            $summary = $this->getLedgerSummary($request);

            // Get filter options
            $companies = DB::table('hrims.hrm_employee')
                ->whereNotNull('CompanyId')
                ->distinct()
                ->orderBy('CompanyId')
                ->pluck('CompanyId');

            $employeeTypes = DB::table('hrims.hrm_employee')
                ->whereNotNull('EmpType')
                ->distinct()
                ->orderBy('EmpType')
                ->pluck('EmpType');

            // Pre-compute balances for each employee using the same logic as account summary / advance report
            $empIds = $ledgerRecords->pluck('employee_id')->filter()->unique()->values()->all();
            $employeeBalances = [];
            try {
                $employeeBalances = $this->computeBatchEmployeeLedgerBalances($empIds);
            } catch (\Throwable $balEx) {
                Log::error('Ledger Summary balance computation error: ' . $balEx->getMessage() . ' in ' . $balEx->getFile() . ':' . $balEx->getLine());
            }

            $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);

            return view('reports.ledger-summary', array_merge($organogramOptions, compact(
                'ledgerRecords',
                'totalRecords',
                'summary',
                'companies',
                'employeeTypes',
                'employeeBalances',
                'filters'
            )));
        } catch (\Throwable $e) {
            Log::error('Ledger Summary Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            return response()->view('reports.ledger-summary', [
                'ledgerRecords'    => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'totalRecords'     => 0,
                'summary'          => (object) [
                    'total_employees' => 0, 'active_employees' => 0, 'inactive_employees' => 0,
                    'total_balance' => 0, 'total_closing_balance' => 0, 'total_debit' => 0, 'total_credit' => 0,
                    'positive_balance_count' => 0, 'negative_balance_count' => 0, 'zero_balance_count' => 0,
                    'employees_with_imprest' => 0, 'employees_with_advance' => 0,
                    'total_imprest_amount' => 0, 'total_advance_amount' => 0,
                ],
                'companies'        => collect(),
                'employeeTypes'    => collect(),
                'employeeBalances' => [],
                'filters'          => $filters ?? [],
                'errorMessage'     => 'Error loading ledger summary: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Restrict an hrm_employee (alias e) query to employees whose computed ledger total
     * (imprest + opening + advances paid - adjustments) is positive / negative / zero.
     */
    public function applyComputedBalanceFilter($query, string $filter): void
    {
        if (! in_array($filter, ['positive', 'negative', 'zero'], true)) {
            return;
        }
        $candidateIds = (clone $query)->reorder()->select('e.EmployeeID')->pluck('EmployeeID')->filter()->unique()->values()->all();
        $balances = $this->computeBatchEmployeeLedgerBalances($candidateIds);
        $keep = [];
        foreach ($candidateIds as $id) {
            $total = (float) ($balances[$id]['total_balance'] ?? 0);
            if (($filter === 'positive' && $total > 0.005)
                || ($filter === 'negative' && $total < -0.005)
                || ($filter === 'zero' && abs($total) <= 0.005)) {
                $keep[] = $id;
            }
        }
        $query->whereIn('e.EmployeeID', $keep ?: [0]);
    }

    /**
     * Get summary statistics for ledger
     */
    private function getLedgerSummary($request)
    {
        $company_id = session('company_id', 1);
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();
        $filters = OrganogramFilterHelper::extractFilters($request);

        // Aggregate across ALL years — no year_id filter
        $query = DB::table($hrimsDb.'.hrm_employee as e')
            ->leftJoin(DatabaseSwitcher::getExpenseDatabaseName().'.employee_ledger_balances as lb', 'e.EmployeeID', '=', 'lb.employee_id')
            ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
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
            );

        // Apply same filters as main query
        if ($request->filled('status_filter')) {
            if ($request->status_filter == 'active') {
                $query->where('e.EmpStatus', 'A');
            } elseif ($request->status_filter == 'inactive') {
                $query->where('e.EmpStatus', '!=', 'A');
            }
        }

        if ($request->filled('company_id')) {
            $query->where('e.CompanyId', $request->company_id);
        } else {
            $query->where('e.CompanyId', $company_id);
        }

        // Apply 10-level organogram filters
        OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'e.EmployeeID', $hrimsDb);

        return $query->first();
    }

    /**
     * Compute batch employee ledger balances using the authoritative AdvanceAdjustmentReportController reconciliation.
     * Mirrors AccountSummaryController and AdvanceAdjustmentReportController 100%.
     *
     * Returns: array of [employee_id => ['total_imprest' => float, 'advance_balance' => float, 'total_balance' => float]]
     */
    public function computeBatchEmployeeLedgerBalances(array $employeeIds, ?string $asOfDate = null): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $expConn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();

        // 1. Fixed imprest from the latest (non-deleted) ledger row of each employee
        $imprests = DB::connection($expConn)
            ->table('employee_ledger_balances')
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('deleted_at')
            ->select('employee_id', 'fixed_imprest_amt_1', 'fixed_imprest_date_1', 'fixed_imprest_amt_2', 'fixed_imprest_date_2')
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id')
            ->keyBy('employee_id');

        // 2+3. Opening ledger advances + paid/unsettled advances of all years, reconciled against
        //      claim deductions by the shared engine (AdvanceAdjustmentReportController).
        $aar = app(\App\Http\Controllers\AdvanceAdjustmentReportController::class);
        $reconciledAdvances = $aar->reconcileEmployees($employeeIds, $expConn, $asOfDate);

        $advancesByEmp = $reconciledAdvances->groupBy('employee_id');

        $result = [];
        foreach ($employeeIds as $empId) {
            $totalImprest = 0.0;
            if (isset($imprests[$empId])) {
                $rec = $imprests[$empId];
                for ($i = 1; $i <= 2; $i++) {
                    $amt = (float) ($rec->{"fixed_imprest_amt_$i"} ?? 0);
                    $d = $rec->{"fixed_imprest_date_$i"} ?? null;
                    if ($amt > 0 && (! $asOfDate || $d === null || \App\Support\ZeroDate::none($d) || $d <= $asOfDate)) {
                        $totalImprest += $amt;
                    }
                }
            }

            $opening = 0.0;
            $credits = 0.0;
            $debits = 0.0;
            $advBal = 0.0;
            if ($advancesByEmp->has($empId)) {
                foreach ($advancesByEmp[$empId] as $adv) {
                    $paid = (float) ($adv->reconciled_paid ?? 0);
                    if (! empty($adv->is_ledger_opening)) {
                        $opening += $paid;
                    } else {
                        $credits += $paid;
                    }
                    $debits += (float) ($adv->reconciled_adjusted ?? 0);
                    $advBal += (float) ($adv->reconciled_remaining ?? 0);
                }
            }

            $result[$empId] = [
                'total_imprest'    => $totalImprest,
                'advance_balance'  => round($advBal, 2),
                'total_balance'    => round($totalImprest + $advBal, 2),
                // Ledger identity: opening_advance + advances_paid - adjustments = advance_balance
                'opening_advance'  => round($opening, 2),
                'advances_paid'    => round($credits, 2),
                'adjustments'      => round($debits, 2),
            ];
        }

        return $result;
    }

    /**
     * Compute real-time ledger balance for a single employee.
     * Matches AccountSummaryController and AdvanceAdjustmentReportController 100%.
     */
    public function computeEmployeeLedgerBalance($employeeId): array
    {
        $batch = $this->computeBatchEmployeeLedgerBalances([(int) $employeeId]);

        return $batch[(int) $employeeId] ?? [
            'total_imprest'   => 0.0,
            'advance_balance' => 0.0,
            'total_balance'   => 0.0,
        ];
    }

    /**
     * Get detailed ledger for a specific employee
     */
    public function getEmployeeLedgerDetails($employeeId)
    {
        try {
            $expConn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();

            /*-------------------------------------
            | 1. Employee Basic Info
            -------------------------------------*/
            $employee = DB::table('hrims.hrm_employee')
                ->select('EmployeeID', 'EmpCode', 'Fname', 'Lname')
                ->where('EmployeeID', $employeeId)
                ->first();

            if (! $employee) {
                return response()->json(['error' => 'Employee not found'], 404);
            }

            /*-------------------------------------
            | 2. Ledger Balance (Opening Imprest & Advances)
            -------------------------------------*/
            $ledgerBalance = DB::connection($expConn)
                ->table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->orderByDesc('id')
                ->first();

            $imprests = [];
            if ($ledgerBalance) {
                for ($i = 1; $i <= 2; $i++) {
                    $amt = (float) ($ledgerBalance->{"fixed_imprest_amt_$i"} ?? 0);
                    if ($amt > 0) {
                        $imprests[] = [
                            'source'          => 'imprest',
                            'ledger_id'       => $ledgerBalance->id,
                            'slot'            => $i,
                            'date'            => $ledgerBalance->{"fixed_imprest_date_$i"},
                            'purpose'         => 'Fixed Imprest',
                            'original_amount' => $amt,
                            'remaining_amount'=> $amt,
                            'adjustable'      => false,
                        ];
                    }
                }
            }

            /*-------------------------------------
            | 3. Opening ledger advances + paid advances and their reconciled deductions
            |    (same engine as the Ledger Summary list, so the detail total equals the list total)
            -------------------------------------*/
            $aar = app(\App\Http\Controllers\AdvanceAdjustmentReportController::class);
            $reconciledAdvances = $aar->reconcileEmployees([(int) $employeeId], $expConn);

            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $ledgerEntries = array_merge([], $imprests);
            $totalPaid = 0.0;
            $totalAdjusted = 0.0;
            $totalUnadjusted = 0.0;
            $displayAdvances = [];

            // Pre-fetch all claim records for this employee across financial years
            $empClaimsMap = [];
            try {
                $allYearIds = DB::connection($expConn)->table('financialyear')->pluck('YearId')->toArray();
                if (empty($allYearIds)) {
                    $allYearIds = range(1, 10);
                }
                foreach ($allYearIds as $y) {
                    try {
                        $mRows = DB::connection($expConn)
                            ->table("y{$y}_monthexpensefinal")
                            ->where('EmployeeID', $employeeId)
                            ->select([
                                'id', 'Month', 'YearId', 'Finance_Amount', 'Finance_Date', 'Fin_PayDate',
                                'Fin_PayRemark', 'activity_adjustments_json', 'expense_adjustments_json'
                            ])
                            ->get();
                        foreach ($mRows as $mr) {
                            $empClaimsMap[$mr->YearId . '_' . $mr->Month] = $mr;
                            if (!isset($empClaimsMap[(int)$mr->Month])) {
                                $empClaimsMap[(int)$mr->Month] = $mr;
                            }
                        }
                    } catch (\Throwable $e) {
                        // skip if table doesn't exist for older year
                    }
                }
            } catch (\Throwable $e) {
                // skip if financialyear query fails
            }

            foreach ($reconciledAdvances as $adv) {
                $isPaid = strtolower(trim($adv->payment_status ?? '')) === 'paid';
                $pAmt = (float) ($adv->reconciled_paid ?? ($adv->payment_amount ?: $adv->amount));
                $aAmt = (float) ($adv->reconciled_adjusted ?? ($adv->adjustment_amount ?? 0));
                $rBal = (float) ($adv->reconciled_remaining ?? (($adv->remaining_balance !== null && $adv->remaining_balance !== '')
                    ? (float) $adv->remaining_balance
                    : max(0.0, $pAmt - $aAmt)));

                if ($isPaid) {
                    $totalPaid += $pAmt;
                    $totalAdjusted += $aAmt;
                    $totalUnadjusted += $rBal;
                }

                $pDate = $adv->payment_date ?: ($adv->created_at ? substr($adv->created_at, 0, 10) : null);
                $ageDays = 0;
                if ($pDate && \App\Support\ZeroDate::has($pDate)) {
                    try {
                        $ageDays = max(0, (int) floor(\Carbon\Carbon::parse($pDate)->diffInDays(now())));
                    } catch (\Throwable $t) {
                        $ageDays = 0;
                    }
                }
                $isOver2Months = ($ageDays > 60 && $rBal > 0 && $isPaid);

                if (!$isPaid) {
                    $statusLabel = 'Pending Payment';
                    $statusBadge = 'bg-secondary';
                } elseif ($rBal <= 0 || ($aAmt >= $pAmt && $pAmt > 0)) {
                    $statusLabel = 'Fully Adjusted';
                    $statusBadge = 'bg-success';
                } elseif ($aAmt > 0 && $rBal > 0) {
                    $statusLabel = 'Partially Adjusted';
                    $statusBadge = 'bg-warning text-dark';
                } else {
                    $statusLabel = 'Completely Unadjusted';
                    $statusBadge = 'bg-danger';
                }

                $settlements = [];
                if (!empty($adv->claim_settlements) && is_array($adv->claim_settlements)) {
                    foreach ($adv->claim_settlements as $cs) {
                        $mNum = (int) ($cs['month'] ?? 0);
                        $mName = $monthNames[$mNum] ?? ('Month ' . $mNum);
                        $csDateStr = ($cs['date'] && \App\Support\ZeroDate::has($cs['date'])) 
                            ? \Carbon\Carbon::parse($cs['date'])->format('d M Y') 
                            : ($pDate ? \Carbon\Carbon::parse($pDate)->format('d M Y') : '-');
                        $settlements[] = [
                            'month_name' => $mName,
                            'year'       => $cs['year'] ?? '',
                            'amount'     => (float) ($cs['adjusted'] ?? 0),
                            'date'       => $csDateStr,
                            'badge'      => "Month: {$mName}",
                        ];
                    }
                }

                $displayAdvances[] = [
                    'activity_id'        => $adv->activity_id,
                    'request_id'         => $adv->request_id ?? '-',
                    'activity_type'      => $adv->activity_type ?? 'Activity Advance',
                    'is_imprest'         => !empty($adv->is_imprest),
                    'is_ledger_opening'  => !empty($adv->is_ledger_opening),
                    'payment_date'       => ($pDate && \App\Support\ZeroDate::has($pDate)) ? \Carbon\Carbon::parse($pDate)->format('d M Y') : 'N/A',
                    'payment_status'     => $adv->payment_status ?? 'Pending',
                    'age_days'           => $ageDays,
                    'is_over_2_months'   => $isOver2Months,
                    'paid_amount'        => $pAmt,
                    'adjusted_amount'    => $aAmt,
                    'unadjusted_balance' => $rBal,
                    'status_label'       => $statusLabel,
                    'status_badge'       => $statusBadge,
                    'settlements'        => $settlements,
                    'purpose'            => $adv->purpose ?? '-',
                    'adjustment_remarks' => $adv->adjustment_remarks ?? '',
                    'utr_number'         => $adv->utr_number ?? '-',
                ];

                // Credit: Advance Disbursed
                $ledgerEntries[] = [
                    'source'          => !empty($adv->is_ledger_opening) ? 'ledger_advance' : 'paid_advance',
                    'activity_id'     => $adv->activity_id,
                    'date'            => $pDate,
                    'purpose'         => $adv->purpose ?: ('Advance #' . $adv->activity_id),
                    'original_amount' => $pAmt,
                    'remaining_amount'=> $rBal,
                    'adjustable'      => true,
                ];

                // Debit(s): Actual claim adjustments reconciled against this advance
                if (!empty($adv->claim_settlements) && is_array($adv->claim_settlements)) {
                    foreach ($adv->claim_settlements as $cs) {
                        $mNum = (int) ($cs['month'] ?? 0);
                        $mYear = (int) ($cs['year'] ?? 0);
                        $mName = $monthNames[$mNum] ?? ('Month ' . $mNum);
                        $csDate = ($cs['date'] && \App\Support\ZeroDate::has($cs['date'])) ? $cs['date'] : $pDate;

                        // Match claim from $empClaimsMap
                        $claimObj = $empClaimsMap[$mYear . '_' . $mNum] ?? ($empClaimsMap[$mNum] ?? null);

                        $finAmt = $claimObj ? (float) ($claimObj->Finance_Amount ?? 0) : (float) ($cs['finance_amount'] ?? 0);
                        $finDate = null;
                        if ($claimObj && !empty($claimObj->Finance_Date) && \App\Support\ZeroDate::has($claimObj->Finance_Date)) {
                            $finDate = $claimObj->Finance_Date;
                        } elseif ($claimObj && !empty($claimObj->Fin_PayDate) && \App\Support\ZeroDate::has($claimObj->Fin_PayDate)) {
                            $finDate = $claimObj->Fin_PayDate;
                        } elseif (!empty($cs['finance_date'])) {
                            $finDate = $cs['finance_date'];
                        }

                        // Remark lookup:
                        // 1. Specific remark from claim's activity_adjustments_json for this activity
                        // 2. Claim Fin_PayRemark
                        // 3. Advance adjustment_remarks
                        $remText = '';
                        if ($claimObj && !empty($claimObj->activity_adjustments_json)) {
                            $actJson = json_decode($claimObj->activity_adjustments_json, true);
                            if (is_array($actJson)) {
                                foreach ($actJson as $adjItem) {
                                    $tgtId = (string) ($adjItem['advance_activity_id'] ?? '');
                                    if ($tgtId === (string) $adv->activity_id) {
                                        $remText = $adjItem['remarks'] ?? ($adjItem['remark'] ?? '');
                                        break;
                                    }
                                }
                            }
                        }
                        if (empty($remText) && $claimObj && !empty($claimObj->Fin_PayRemark)) {
                            $remText = $claimObj->Fin_PayRemark;
                        }
                        if (empty($remText)) {
                            $remText = $cs['remarks'] ?? ($adv->adjustment_remarks ?? '');
                        }

                        $ledgerEntries[] = [
                            'source'           => 'adjustment',
                            'source_table_id'  => $adv->activity_id,
                            'advance_id'       => $adv->activity_id,
                            'date'             => $csDate,
                            'month'            => $mNum,
                            'category'         => 'adjustment',
                            'purpose'          => "Claim Adj: {$mName} (FY " . ($cs['year'] ?? '') . ") against Adv #{$adv->activity_id}",
                            'original_amount'  => 0,
                            'remaining_amount' => (float) ($cs['remaining'] ?? 0),
                            'total_adjustment' => (float) ($cs['adjusted'] ?? 0),
                            'finance_amount'   => $finAmt,
                            'finance_date'     => $finDate,
                            'finance_remarks'  => $remText,
                            'adjustable'       => false,
                        ];
                    }
                }
            }

            // Sort chronologically by date: on tie, advance credit comes before deduction debit
            usort($ledgerEntries, function ($a, $b) {
                $da = strtotime($a['date'] ?? '1970-01-01');
                $db = strtotime($b['date'] ?? '1970-01-01');
                if ($da === $db) {
                    if ($a['source'] === 'imprest') return -1;
                    if ($b['source'] === 'imprest') return 1;
                    $credit = ['ledger_advance', 'paid_advance'];
                    if (in_array($a['source'], $credit, true) && $b['source'] === 'adjustment') return -1;
                    if ($a['source'] === 'adjustment' && in_array($b['source'], $credit, true)) return 1;
                    return 0;
                }
                return $da <=> $db;
            });

            $totalImprest = array_sum(array_column($imprests, 'original_amount'));

            return response()->json([
                'employee' => [
                    'id'   => $employee->EmployeeID,
                    'code' => $employee->EmpCode,
                    'name' => trim($employee->Fname . ' ' . $employee->Lname),
                ],
                'summary' => [
                    'total_paid'            => $totalPaid,
                    'total_adjusted'        => $totalAdjusted,
                    'total_unadjusted'      => $totalUnadjusted,
                    'total_imprest'         => $totalImprest,
                    'total_balance'         => $totalImprest + $totalUnadjusted,
                ],
                'advances'       => $displayAdvances,
                'ledger_entries' => $ledgerEntries,
                'urls' => [
                    'account_summary' => url("/account-summary?e={$employee->EmployeeID}&y=8"),
                    'advance_report'  => url("/advance-adjustment-report?search={$employee->EmpCode}"),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Employee Ledger Details Error: '.$e->getMessage());

            return response()->json([
                'error'   => 'Error fetching details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /** Export ledger summary to CSV */

    /**
     * Update employee ledger balance
     */
    public function updateLedgerBalance(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer',
                'balance_type' => 'required|in:debit,credit',
                'amount' => 'required|numeric|min:0.01',
                'remarks' => 'required|string',
            ]);

            $employeeId = $request->employee_id;
            // year_id is a financialyear.YearId (7, 8 ...), never a calendar year such as 2026
            $yearId = session('year_id') ?: DB::table('financialyear')->max('YearId');
            $amount = $request->amount;
            $balanceType = $request->balance_type;

            // Get current balance
            $currentBalance = DB::table('employee_ledger_balances')
                ->where('employee_id', $employeeId)
                ->where('year_id', $yearId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            DB::beginTransaction();

            if ($currentBalance) {
                // Update existing record
                $updateData = [
                    'remark' => $request->remarks,
                    'updated_at' => now(),
                ];

                if ($balanceType == 'debit') {
                    $updateData['dr'] = ($currentBalance->dr ?? 0) + $amount;
                    $updateData['balance'] = ($currentBalance->balance ?? 0) + $amount;
                } else {
                    $updateData['cr'] = ($currentBalance->cr ?? 0) + $amount;
                    $updateData['balance'] = ($currentBalance->balance ?? 0) - $amount;
                }

                DB::table('employee_ledger_balances')
                    ->where('id', $currentBalance->id)
                    ->update($updateData);
            } else {
                // Create new record
                $insertData = [
                    'employee_id' => $employeeId,
                    'year_id' => $yearId,
                    'company_id' => session('company_id', auth()->user()->company_id ?? 1),
                    'remark' => $request->remarks,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($balanceType == 'debit') {
                    $insertData['dr'] = $amount;
                    $insertData['balance'] = $amount;
                } else {
                    $insertData['cr'] = $amount;
                    $insertData['balance'] = -$amount;
                }

                DB::table('employee_ledger_balances')->insert($insertData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ledger balance updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update Ledger Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating balance: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportLedgerSummary(Request $request)
    {
        return Excel::download(
            new LedgerSummaryExport($request),
            'ledger_summary_'.date('Ymd_His').'.xlsx'
        );
    }

    // =========================================================================
    // AS-OF-DATE SNAPSHOT
    // =========================================================================

    /**
     * Return JSON: each employee's balance calculated using only transactions
     * whose date is <= as_of_date.
     *
     * Logic mirrors getEmployeeLedgerBalanceSummary() exactly, but with date
     * cutoffs applied per transaction type.
     */
    public function ledgerSummaryAsOfDate(Request $request)
    {
        try {
            $request->validate(['as_of_date' => 'required|date']);
            $asOfDate  = $request->as_of_date;           // Y-m-d
            $search    = $request->get('search', '');
            $perPage   = (int) $request->get('per_page', 20);
            $page      = (int) $request->get('page', 1);
            $expConn   = session('expense_connection') ?: 'expense';
            $companyId = session('company_id', 1);
            $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName();
            $filters   = OrganogramFilterHelper::extractFilters($request);

            // ── Employee list ─────────────────────────────────────────────────
            $empQuery = DB::table($hrimsDb.'.hrm_employee as e')
                ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->where('e.EmpStatus', '!=', 'D')
                ->where('e.CompanyId', '=', $companyId)
                ->where('e.EmpCode', '>', '0')
                ->select(
                    'e.EmployeeID as employee_id',
                    DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,'')) as employee_name"),
                    DB::raw("COALESCE(e.EmpCode, e.EmpCode_New, e.ECode) as display_code"),
                    'e.EmpStatus'
                );

            OrganogramFilterHelper::applyToQuery($empQuery, $filters, 'eg', 'e.EmployeeID', $hrimsDb);

            $empQuery->orderBy('e.Fname')->orderBy('e.Sname');

            if ($search) {
                $empQuery->where(function ($q) use ($search) {
                    $q->where(DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))"), 'LIKE', "%{$search}%")
                      ->orWhere('e.EmpCode', 'LIKE', "%{$search}%")
                      ->orWhere('e.EmpCode_New', 'LIKE', "%{$search}%")
                      ->orWhere('e.ECode', 'LIKE', "%{$search}%");
                });
            }

            $total     = $empQuery->count();
            $employees = (clone $empQuery)->forPage($page, $perPage)->get();

            $allYearIds = DB::connection($expConn)
                ->table('financialyear')
                ->orderBy('YearId')
                ->pluck('YearId')
                ->toArray();

            // Balances come from the same reconciliation engine as the current Ledger Summary,
            // limited to advances paid / deductions settled on or before the date.
            $asOfBalances = $this->computeBatchEmployeeLedgerBalances($employees->pluck('employee_id')->all(), $asOfDate);

            $rows = [];

            foreach ($employees as $emp) {
                $empId = $emp->employee_id;

                // ── 1. Opening ledger record ──────────────────────────────────
                $lb = DB::connection($expConn)
                    ->table('employee_ledger_balances')
                    ->where('employee_id', $empId)
                    ->orderByDesc('id')
                    ->first();

                $totalImprest   = 0;
                $runningBalance = 0;
                $advanceDetails = [];

                if ($lb) {
                    // Fixed imprest — include if imprest date <= as_of_date (or no date)
                    for ($i = 1; $i <= 2; $i++) {
                        $amt = (float) ($lb->{"fixed_imprest_amt_$i"} ?? 0);
                        $d   = $lb->{"fixed_imprest_date_$i"} ?? null;
                        if ($amt > 0 && ($d === null || \App\Support\ZeroDate::none($d) || $d <= $asOfDate)) {
                            $totalImprest += $amt;
                        }
                    }
                    // Opening advance slots — include if advance date <= as_of_date (or no date)
                    for ($i = 1; $i <= 4; $i++) {
                        $amt  = (float) ($lb->{"advance_amt_$i"} ?? 0);
                        $d    = $lb->{"advance_date_$i"} ?? null;
                        $purp = $lb->{"advance_purpose_$i"} ?? '';
                        if ($amt > 0 && ($d === null || \App\Support\ZeroDate::none($d) || $d <= $asOfDate)) {
                            $runningBalance += $amt;
                            $advanceDetails[] = ['type' => 'Opening Advance', 'date' => $d, 'purpose' => $purp, 'amount' => $amt];
                        }
                    }
                }

                // ── 2. Paid advances paid ON OR BEFORE as_of_date (credit, excluding imprest) ───
                $paidRows = DB::connection($expConn)
                    ->table('advance_requests as ar')
                    ->join('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
                    ->join('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
                    ->where('are.employee_id', $empId)
                    ->whereIn('ara.payment_status', ['Paid', 'Unsettled'])
                    ->where(function ($q) use ($asOfDate) {
                        $q->whereNull('ara.payment_date')
                          ->orWhere('ara.payment_date', '0000-00-00')
                          ->orWhere('ara.payment_date', '<=', $asOfDate);
                    })
                    ->where(function ($q) {
                        $q->where('ar.is_imprest', 0)->orWhereNull('ar.is_imprest');
                    })
                    ->where(function ($q) {
                        $q->where('ara.is_imprest', 0)->orWhereNull('ara.is_imprest');
                    })
                    ->where(function ($q) {
                        $q->where('ara.purpose', 'NOT LIKE', '%imprest%')->orWhereNull('ara.purpose');
                    })
                    ->whereNull('ar.deleted_at')
                    ->whereNull('are.deleted_at')
                    ->whereNull('ara.deleted_at')
                    ->select(
                        'ara.payment_date', 'ara.purpose',
                        DB::raw('COALESCE(ara.finance_approved_amount, ara.payment_amount, ara.final_amount, ara.amount) as amount')
                    )
                    ->get();

                foreach ($paidRows as $pr) {
                    $amt = (float) ($pr->amount ?? 0);
                    if ($amt > 0) {
                        $runningBalance += $amt;
                        $advanceDetails[] = ['type' => 'Paid Advance', 'date' => $pr->payment_date, 'purpose' => $pr->purpose ?? '', 'amount' => $amt];
                    }
                }

                $bal = $asOfBalances[$empId] ?? ['total_imprest' => 0.0, 'advance_balance' => 0.0, 'total_balance' => 0.0];

                $rows[] = [
                    'employee_id'     => $empId,
                    'employee_name'   => $emp->employee_name,
                    'display_code'    => $emp->display_code,
                    'emp_status'      => $emp->EmpStatus,
                    'imprest_total'   => $bal['total_imprest'],
                    'advance_total'   => $bal['advance_balance'],
                    'total_balance'   => $bal['total_balance'],
                    'advance_details' => $advanceDetails,
                ];
            }

            return response()->json([
                'as_of_date'   => $asOfDate,
                'data'         => $rows,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Exception $e) {
            Log::error('ledgerSummaryAsOfDate error: '.$e->getMessage().' '.$e->getFile().':'.$e->getLine());
            return response()->json(['error' => 'Server error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export as-of-date snapshot to Excel.
     * Uses the same calculation logic as ledgerSummaryAsOfDate.
     */
    public function exportLedgerSummaryAsOfDate(Request $request)
    {
        try {
            // LEDGER-DATE-01: no date given = today (was a 500 from the validation)
            if (! $request->filled('as_of_date')) {
                $request->merge(['as_of_date' => now()->toDateString()]);
            }
            $request->validate(['as_of_date' => 'date']);
            $asOfDate  = $request->as_of_date;
            $search    = $request->get('search', '');
            $expConn   = session('expense_connection') ?: 'expense';
            $companyId = session('company_id', 1);
            $hrimsDb   = DatabaseSwitcher::getHrimsDatabaseName();
            $filters   = OrganogramFilterHelper::extractFilters($request);

            $empQuery = DB::table($hrimsDb.'.hrm_employee as e')
                ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->where('e.EmpStatus', '!=', 'D')
                ->where('e.CompanyId', '=', $companyId)
                ->where('e.EmpCode', '>', '0')
                ->select(
                    'e.EmployeeID as employee_id',
                    DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,'')) as employee_name"),
                    DB::raw("COALESCE(e.EmpCode, e.EmpCode_New, e.ECode) as display_code"),
                    'e.EmpStatus'
                );

            OrganogramFilterHelper::applyToQuery($empQuery, $filters, 'eg', 'e.EmployeeID', $hrimsDb);

            $empQuery->orderBy('e.Fname')->orderBy('e.Sname');

            if ($search) {
                $empQuery->where(function ($q) use ($search) {
                    $q->where(DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))"), 'LIKE', "%{$search}%")
                      ->orWhere('e.EmpCode', 'LIKE', "%{$search}%");
                });
            }

            $employees  = $empQuery->get();
            $asOfBalances = $this->computeBatchEmployeeLedgerBalances($employees->pluck('employee_id')->all(), $asOfDate);

            $allRows = [];
            $i       = 1;

            foreach ($employees as $emp) {
                $empId = $emp->employee_id;

                $bal = $asOfBalances[$empId] ?? ['total_imprest' => 0.0, 'advance_balance' => 0.0, 'total_balance' => 0.0];
                $totalImprest   = $bal['total_imprest'];
                $runningBalance = $bal['advance_balance'];
                $totalBalance   = $bal['total_balance'];

                $codes = collect(preg_split('/\r\n|\r|\n/', $emp->display_code))
                    ->filter()
                    ->map(function ($c) {
                        $c = trim($c);
                        return is_numeric($c) ? 'E'.str_pad($c, 4, '0', STR_PAD_LEFT) : $c;
                    })->implode(', ');

                $allRows[] = [$i++, $emp->employee_name, $codes, $totalImprest, $runningBalance, $totalBalance, $emp->EmpStatus];
            }

            // ── Build Excel ───────────────────────────────────────────────────
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Balance as of '.date('d-M-Y', strtotime($asOfDate)));

            $headers = ['S.No', 'Employee Name', 'Employee Code', 'Imprest Amount', 'Advance Balance', 'Total Balance', 'Status'];
            $sheet->fromArray([$headers], null, 'A1');
            if (! empty($allRows)) {
                $sheet->fromArray($allRows, null, 'A2');
            }

            $lastRow = count($allRows) + 1;

            $sheet->getStyle('A1:G1')->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle('A1:G'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle('thin');
            $sheet->getStyle('D2:F'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('D2:F'.$lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Title rows
            $sheet->insertNewRowBefore(1, 2);
            $sheet->setCellValue('A1', 'Employee Ledger Balance — As of '.date('d-M-Y', strtotime($asOfDate)));
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->applyFromArray([
                'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '15648C']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->setCellValue('A2', 'Generated on: '.date('d-M-Y H:i:s').'   (Records: '.count($allRows).')');
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $filename = 'ledger_balance_as_of_'.date('Ymd', strtotime($asOfDate)).'_'.date('His').'.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');

            \App\Support\ReportStamp::workbook($spreadsheet); // STAMP-01
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            Log::error('exportLedgerSummaryAsOfDate error: '.$e->getMessage());
            abort(500, 'Export failed: '.$e->getMessage());
        }
    }
}
