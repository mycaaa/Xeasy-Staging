<?php

namespace App\Services\Matrix;

use App\Helpers\DatabaseSwitcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatrixPaymentPayloadBuilder
{
    /**
     * Build instruction payload for a list of reimbursement records
     *
     * @param iterable $records
     * @param int $yearId
     * @param int $companyId
     * @param string|null $batchRef
     * @return array Array of instruction dictionaries
     */
    public function buildReimbursementInstructions($records, int $yearId, int $companyId = 1, ?string $batchRef = null): array
    {
        $instructions = [];
        $batchRef = $batchRef ?: 'PAY_BATCH_REIMB_' . now()->format('Ymd_His');

        foreach ($records as $payment) {
            $amount = (float) ($payment->payment_amount ?? 0);
            if ($amount <= 0) {
                // Per spec: Send ONLY amounts above zero
                continue;
            }

            $employeeId = $payment->EmployeeID;
            $employee = DB::connection('hrims')->selectOne('
                SELECT EmpCode, Fname, Sname, Lname
                FROM hrm_employee
                WHERE EmployeeID = ?
            ', [$employeeId]);

            $empCode = $employee ? (string) $employee->EmpCode : '0000';
            $fname = $employee->Fname ?? '';
            $sname = $employee->Sname ?? '';
            $lname = $employee->Lname ?? '';
            $employeeName = trim("{$fname} {$sname} {$lname}") ?: 'Employee';

            // Payee Focus Code: starts with 'v'/'V', or 'E' + 4-digit code
            $payeeFocusCode = $this->formatFocusCode($empCode);

            // Bank details from hrm_employee_general
            $bankDetails = DB::connection('hrims')->selectOne('
                SELECT AccountNo, BankName, BankIfscCode
                FROM hrm_employee_general
                WHERE EmployeeID = ?
                LIMIT 1
            ', [$employeeId]);

            $accountNo = $bankDetails ? trim((string) $bankDetails->AccountNo) : null;
            $ifsc = $bankDetails ? strtoupper(trim((string) $bankDetails->BankIfscCode)) : null;
            $bankName = $bankDetails ? trim((string) $bankDetails->BankName) : null;

            // Month & Financial Year calculation
            $monthNum = (int) ($payment->Month ?? 1);
            $monthName = $this->getMonthName($monthNum);
            $fyStartYear = $this->resolveFinancialYearStart($yearId, $companyId);
            $calYear = ($monthNum >= 4) ? $fyStartYear : ($fyStartYear + 1);
            $shortYear = substr((string) $calYear, -2);
            $monthRef = "{$monthName}-{$shortYear}";

            $narration = "Expense reimbursement for {$monthRef}";
            $sourceRef = "REIMB-{$yearId}-{$payment->id}";

            $focusConfig = config('matrix.focus', []);

            $focus = [
                'business_entity' => (int) ($focusConfig['business_entity'] ?? 120),
                'department' => (string) ($focusConfig['department'] ?? 'FIN'),
                'cost_center' => 'N/A',
                'pmt_category' => 'Staff Expense reimbursement',
                'location' => (string) ($focusConfig['location'] ?? 'RAIPUR'),
                'state' => (string) ($focusConfig['state'] ?? '22'),
                'function' => (string) ($focusConfig['function'] ?? 'BSF'),
                'fc_vertical' => (string) ($focusConfig['fc_vertical'] ?? 'CM'),
                'sub_department' => (string) ($focusConfig['sub_department'] ?? 'SUB_DEPT_FIN_124'),
                'activity' => 'N/A',
                'category' => 'N/A',
                'region' => 'N/A',
                'crop' => 'All Crop',
                'farm' => 'N/A',
                'business_unit' => 'N/A',
                'zone' => '',
                'narration' => $narration,
                'remarks' => "Payment for {$employeeName}",
                'reference' => '',
                'doc_no' => (string) ($focusConfig['doc_no'] ?? ''),
                'date' => date('d-m-Y'),
                'time' => date('H:i:s'),
                'cash_bank_ac' => (string) ($focusConfig['cash_bank_ac'] ?? 'BANK-26'),
                'cheque_no' => (string) ($focusConfig['cheque_no'] ?? ''),
                'transaction_type_code' => 'NEFT',
                'brs_user' => (string) ($focusConfig['brs_user'] ?? ''),
                'tds_bill_amount' => 0,
                'tds' => 0,
                'tds_jv_no' => '',
                'account' => $payeeFocusCode,
            ];

            $instructions[] = [
                'source_ref' => $sourceRef,
                'payee_focus_code' => $payeeFocusCode,
                'payee_name' => $employeeName,
                'payee_type' => 'employee',
                'beneficiary_account_number' => $accountNo,
                'beneficiary_ifsc' => $ifsc,
                'beneficiary_bank_name' => $bankName,
                'instructed_at' => now()->format('Y-m-d H:i:s'),
                'amount' => round($amount, 2),
                'payment_mode' => 'neft',
                'invoice_refs' => [$monthRef],
                'narration' => $narration,
                'batch_ref' => $batchRef,
                'focus' => $focus,
                // Internal metadata retained for local bookkeeping
                '_local_record_id' => $payment->id,
                '_local_year_id' => $yearId,
                '_local_company_id' => $companyId,
                '_local_type' => 'reimbursement',
            ];
        }

        return $instructions;
    }

    /**
     * Build instruction payload for a list of advance request activities
     *
     * @param iterable $activities
     * @param int $companyId
     * @param string|null $batchRef
     * @return array
     */
    public function buildAdvanceInstructions($activities, int $companyId = 1, ?string $batchRef = null): array
    {
        $instructions = [];
        $batchRef = $batchRef ?: 'PAY_BATCH_ADV_' . now()->format('Ymd_His');

        foreach ($activities as $activity) {
            $amount = (float) ($activity->payment_amount ?? $activity->finance_approved_amount ?? $activity->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $employeeId = $activity->employee->employee_id ?? null;
            if (!$employeeId && isset($activity->EmployeeID)) {
                $employeeId = $activity->EmployeeID;
            }

            $employee = DB::connection('hrims')->selectOne('
                SELECT EmpCode, Fname, Sname, Lname
                FROM hrm_employee
                WHERE EmployeeID = ?
            ', [$employeeId]);

            $empCode = $employee ? (string) $employee->EmpCode : '0000';
            $fname = $employee->Fname ?? '';
            $sname = $employee->Sname ?? '';
            $lname = $employee->Lname ?? '';
            $employeeName = trim("{$fname} {$sname} {$lname}") ?: 'Employee';

            $payeeFocusCode = $this->formatFocusCode($empCode);

            // Bank details
            $bankDetails = DB::connection('hrims')->selectOne('
                SELECT AccountNo, BankName, BankIfscCode
                FROM hrm_employee_general
                WHERE EmployeeID = ?
                LIMIT 1
            ', [$employeeId]);

            $accountNo = $bankDetails ? trim((string) $bankDetails->AccountNo) : null;
            $ifsc = $bankDetails ? strtoupper(trim((string) $bankDetails->BankIfscCode)) : null;
            $bankName = $bankDetails ? trim((string) $bankDetails->BankName) : null;

            // Activity name
            $activityName = $activity->activityType->name ?? $activity->activity_name ?? 'Advance Payment';
            if ($activityName === 'Advance Payment' && !empty($activity->activity_type_name)) {
                $activityName = $activity->activity_type_name;
            }

            // Crops
            $crops = $this->formatCrops($activity);

            // Narration
            $currentMonth = date('n');
            $monthName = $this->getMonthName($currentMonth);
            $yearShort = date('y');
            $narration = "Advance for {$activityName} {$monthName}-{$yearShort}";

            $sourceRef = "ADV-{$activity->id}";
            $focusConfig = config('matrix.focus', []);

            $focus = [
                'business_entity' => (int) ($focusConfig['business_entity'] ?? 120),
                'department' => (string) ($focusConfig['department'] ?? 'FIN'),
                'cost_center' => 'N/A',
                'pmt_category' => 'Staff Expense reimbursement',
                'location' => (string) ($focusConfig['location'] ?? 'RAIPUR'),
                'state' => (string) ($focusConfig['state'] ?? '22'),
                'function' => (string) ($focusConfig['function'] ?? 'BSF'),
                'fc_vertical' => (string) ($focusConfig['fc_vertical'] ?? 'CM'),
                'sub_department' => (string) ($focusConfig['sub_department'] ?? 'SUB_DEPT_FIN_124'),
                'activity' => $activityName,
                'category' => 'N/A',
                'region' => 'N/A',
                'crop' => $crops ?: 'All Crop',
                'farm' => 'N/A',
                'business_unit' => 'N/A',
                'zone' => '',
                'narration' => $narration,
                'remarks' => '', // Empty for advances per spec
                'reference' => '',
                'doc_no' => (string) ($focusConfig['doc_no'] ?? ''),
                'date' => date('d-m-Y'),
                'time' => date('H:i:s'),
                'cash_bank_ac' => (string) ($focusConfig['cash_bank_ac'] ?? 'BANK-26'),
                'cheque_no' => (string) ($focusConfig['cheque_no'] ?? ''),
                'transaction_type_code' => 'NEFT',
                'brs_user' => (string) ($focusConfig['brs_user'] ?? ''),
                'tds_bill_amount' => 0,
                'tds' => 0,
                'tds_jv_no' => '',
                'account' => $payeeFocusCode,
            ];

            $instructions[] = [
                'source_ref' => $sourceRef,
                'payee_focus_code' => $payeeFocusCode,
                'payee_name' => $employeeName,
                'payee_type' => 'employee',
                'beneficiary_account_number' => $accountNo,
                'beneficiary_ifsc' => $ifsc,
                'beneficiary_bank_name' => $bankName,
                'instructed_at' => now()->format('Y-m-d H:i:s'),
                'amount' => round($amount, 2),
                'payment_mode' => 'neft',
                'invoice_refs' => ["ADV-{$activity->id}"],
                'narration' => $narration,
                'batch_ref' => $batchRef,
                'focus' => $focus,
                // Internal metadata
                '_local_record_id' => $activity->id,
                '_local_year_id' => session('year_id', null),
                '_local_company_id' => $companyId,
                '_local_type' => 'advance',
            ];
        }

        return $instructions;
    }

    /**
     * Format Focus Party Code:
     * If starts with 'V' or 'v', returns as is.
     * Otherwise 'E' + 4-digit code padded with 0 (e.g. E0429).
     */
    public function formatFocusCode(string $empCode): string
    {
        $code = trim($empCode);
        if (str_starts_with(strtolower($code), 'v')) {
            return $code;
        }

        return 'E' . str_pad($code, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Format Crops list for advance focus payload
     */
    protected function formatCrops($activity): string
    {
        if (!empty($activity->crop_names)) {
            return is_array($activity->crop_names) ? implode(', ', $activity->crop_names) : (string) $activity->crop_names;
        }
        if (!empty($activity->crop)) {
            return (string) $activity->crop;
        }
        return 'All Crop';
    }

    /**
     * Resolve the starting 4-digit calendar year for a given financial year ID.
     * In the Indian fiscal calendar (Apr - Mar), FY 2026-27 starts in Apr 2026.
     */
    public function resolveFinancialYearStart(int $yearId, int $companyId = 1): int
    {
        try {
            $conn = DatabaseSwitcher::getExpenseConnection($companyId);
            $fyRow = DB::connection($conn)
                ->table('financialyear')
                ->where('YearId', $yearId)
                ->first();

            if ($fyRow) {
                if (!empty($fyRow->y1) && is_numeric($fyRow->y1)) {
                    return (int) $fyRow->y1;
                }
                if (!empty($fyRow->FromDate) && \App\Support\ZeroDate::has($fyRow->FromDate)) {
                    return (int) date('Y', strtotime($fyRow->FromDate));
                }
                if (!empty($fyRow->Year) && preg_match('/^(\d{4})/', (string) $fyRow->Year, $m)) {
                    return (int) $m[1];
                }
            }
        } catch (\Exception $e) {
            // fallback below
        }

        // If yearId itself is 4 digits e.g. 2026
        if ($yearId >= 2000 && $yearId <= 2100) {
            return $yearId;
        }

        // Fallback: check session('year_value') e.g. "2026-2027" or "2026-27"
        $yearVal = session('year_value');
        if (!empty($yearVal) && preg_match('/^(\d{4})/', (string) $yearVal, $m)) {
            return (int) $m[1];
        }

        // Default to current calendar year / fiscal year
        $nowMonth = (int) date('n');
        $nowYear = (int) date('Y');
        return ($nowMonth >= 4) ? $nowYear : ($nowYear - 1);
    }

    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];
        return $months[$month] ?? 'Jan';
    }
}
