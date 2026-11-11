<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class FocusPaymentExport implements \App\Exports\Concerns\NoReportStamp, FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    protected $payments;

    protected $yearId;

    protected $recordIds;

    protected $fyCache = [];

    public function __construct($payments, $yearId, &$recordIds)
    {
        $this->payments = $payments;
        $this->yearId = $yearId;
        $this->recordIds = &$recordIds;
    }

    public function collection(): Enumerable
    {
        return collect($this->payments);
    }

    public function headings(): array
    {
        return [
            'DocNo', 'Date', 'Time', 'CashBankAC', 'TDS JVNo', 'Narration', 'Cheque No',
            'Transactiontype Code', 'Activity', 'Category', 'Region', 'Crop', 'Farm',
            'Business Entity', 'Cost Center', 'Department', 'PMT Category', 'Business Unit',
            'Location', 'State', 'Function', 'FC-Vertical', 'Sub Department', 'Zone',
            'Account', 'Amount', 'Reference', 'Remarks', 'TDS Bill Amount', 'TDS', 'BRSUser',
        ];
    }

    public function map(mixed $payment): array
    {
        // Get employee details
        $employee = DB::connection('hrims')->selectOne('
            SELECT EmpCode, Fname, Sname, Lname
            FROM hrm_employee
            WHERE EmployeeID = ?
        ', [$payment->EmployeeID]);

        $employeeCode = $employee ? $employee->EmpCode : '0000';
        $fname = $employee ? $employee->Fname : '';
        $sname = $employee ? $employee->Sname : '';
        $lname = $employee ? ($employee->Lname ?: 'Unknown') : 'Unknown';

        // Get month name
        $monthName = $this->getMonthName($payment->Month);

        // Calculate year based on financial year
        $conn = session('expense_connection') ?: 'expense';
        $targetYearId = $payment->YearId ?? $payment->ClaimYearId ?? $this->yearId ?? session('year_id');

        $cacheKey = "{$conn}_{$targetYearId}";
        if (!isset($this->fyCache[$cacheKey])) {
            $fyStartYear = null;
            if (!empty($targetYearId)) {
                try {
                    $fyRow = DB::connection($conn)->table('financialyear')->where('YearId', $targetYearId)->first();
                    if ($fyRow) {
                        if (!empty($fyRow->y1) && is_numeric($fyRow->y1)) {
                            $y1 = (int) $fyRow->y1;
                            $fyStartYear = ($y1 < 100) ? (2000 + $y1) : $y1;
                        } elseif (!empty($fyRow->FromDate) && \App\Support\ZeroDate::has($fyRow->FromDate)) {
                            $fyStartYear = (int) date('Y', strtotime($fyRow->FromDate));
                        } elseif (!empty($fyRow->Year)) {
                            if (preg_match('/^(\d{4})/', (string) $fyRow->Year, $m)) {
                                $fyStartYear = (int) $m[1];
                            } elseif (preg_match('/^(\d{2})[-\/]/', (string) $fyRow->Year, $m)) {
                                $fyStartYear = 2000 + (int) $m[1];
                            }
                        }
                    }
                } catch (\Exception $e) {}
            }

            if (!$fyStartYear) {
                $yearVal = session('year_value');
                if (!empty($yearVal)) {
                    if (preg_match('/^(\d{4})/', (string) $yearVal, $m)) {
                        $fyStartYear = (int) $m[1];
                    } elseif (preg_match('/^(\d{2})[-\/]/', (string) $yearVal, $m)) {
                        $fyStartYear = 2000 + (int) $m[1];
                    }
                }
            }

            if (!$fyStartYear) {
                $nowMonth = (int) date('n');
                $nowYear = (int) date('Y');
                $fyStartYear = ($nowMonth >= 4) ? $nowYear : ($nowYear - 1);
            }

            $this->fyCache[$cacheKey] = $fyStartYear;
        }

        $fyStartYear = $this->fyCache[$cacheKey];
        $year = ((int) $payment->Month >= 4) ? $fyStartYear : ($fyStartYear + 1);
        $shortYear = substr((string) $year, -2);

        // Build data
        $employeeName = trim($fname.' '.$sname.' '.$lname);
        if (empty($employeeName) || $employeeName === 'Unknown') {
            $employeeName = 'Employee';
        }

        // $partyAC = 'E' . str_pad($employeeCode, 4, '0', STR_PAD_LEFT);
        if (str_starts_with(strtolower($employeeCode), 'v')) {
            $partyAC = $employeeCode;
        } else {
            $partyAC = 'E'.str_pad($employeeCode, 4, '0', STR_PAD_LEFT);
        }
        $narration = "Expense reimbursement for {$monthName}-{$shortYear}";

        // Collect record ID for updating
        $this->recordIds[] = $payment->id;

        return [
            '',  // DocNo
            date('d-m-Y'),  // Date
            date('H:i:s'),  // Time
            'BANK-26',  // CashBankAC
            '',  // TDSJVNo
            $narration,  // sNarration
            '',  // sChequeNo
            'NEFT',  // TransactiontypeCode
            'N/A',  // Activity
            'N/A',  // Category
            'N/A',  // Region
            'All Crop',  // Crop
            'N/A',  // Farm
            '120',  // Business Entity
            'N/A',  // Cost Center
            'FIN',  // Department
            'Staff Expense reimbursement',  // PMT Category
            'N/A',  // Business Unit
            'RAIPUR',  // Location
            '22',  // State
            'BSF',  // Function
            'CM',  // FC-Vertical
            'SUB_DEPT_FIN_124',  // Sub Department
            '',  // Zone
            $partyAC,  // Account
            number_format($payment->payment_amount, 2, '.', ''),  // Amount
            '',  // Reference
            "Payment for {$employeeName}",  // sRemarks
            '0.00',  // TDSBillAmount
            '0.00',  // TDS
            '',  // sBRSUser
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => '4472C4']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getStyle('A1:AE1')->getAlignment()->setHorizontal('center');
                $event->sheet->getStyle('A:AE')->getAlignment()->setVertical('center');

                // Set column widths
                $event->sheet->getColumnDimension('A')->setWidth(10);
                $event->sheet->getColumnDimension('B')->setWidth(15);
                $event->sheet->getColumnDimension('C')->setWidth(10);
                $event->sheet->getColumnDimension('D')->setWidth(15);
                $event->sheet->getColumnDimension('F')->setWidth(30);
                $event->sheet->getColumnDimension('R')->setWidth(20);
                $event->sheet->getColumnDimension('Y')->setWidth(15);
                $event->sheet->getColumnDimension('Z')->setWidth(15);
                $event->sheet->getColumnDimension('AA')->setWidth(30);




            },
        ];
    }

    private function getMonthName($monthNumber)
    {
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        return $months[$monthNumber] ?? 'Jan';
    }
}
