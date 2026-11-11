<?php

namespace App\Exports;

use App\Helpers\CommonHelper;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;
use Illuminate\Support\Enumerable;

class PaymentExportSheet implements \App\Exports\Concerns\NoReportStamp, FromCollection, WithEvents, WithTitle
{
    protected string $sheetTitle;
    protected Collection $paymentData;

    public function __construct(string $sheetTitle, Collection $paymentData)
    {
        $this->sheetTitle = $sheetTitle;
        $this->paymentData = $paymentData;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {
                $this->renderSheet($event->sheet->getDelegate());
            },
        ];
    }

    private function renderSheet($sheet)
    {
        $curRow = 1;
        $totalColumns = 31;  // Reduced from 32 after removing Finance Advance Amount
        $lastColumn = $this->getColumnLetter($totalColumns);

        // Complete headers based on the format you provided
        $headers = [
            'DocNo',
            'Date',
            'Time',
            'CashBankAC',
            'TDS JVNo',
            'Narration',
            'Cheque No',
            'Transactiontype Code',
            'Activity',
            'Category',
            'Region',
            'Crop',
            'Farm',
            'Business Entity',
            'Cost Center',
            'Department',
            'PMT Category',
            'Business Unit',
            'Location',
            'State',
            'Function',
            'FC-Vertical',
            'Sub Department',
            'Zone',
            'Account',
            'Amount',
            'Reference',
            'Remarks',
            'TDS Bill Amount',
            'TDS',
            'BRSUser'
        ];

        // Set headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$curRow}", $header);
            $col++;
        }
        $this->headerStyle($sheet, "A{$curRow}:{$lastColumn}{$curRow}");
        $curRow++;

        // Process payment data
        $index = 1;

        foreach ($this->paymentData as $payment) {
            $data = [
                $this->generateDocNo($payment, $index),
                $this->formatDate($payment->exported_date ?? now()),
                $this->formatTime($payment->exported_date ??  now()),
                $payment->cash_bank_ac ?? 'BANK-26',
                '',  // TDSJVNo - empty
                $this->generateNarration($payment),
                '',  // sChequeNo - empty
                'NEFT',  // TransactiontypeCode
                $payment->activity_name ?? 'N/A',
                'N/A',  // Category
                'N/A',  // Region
                $this->getCrop($payment),
                'N/A',  // Farm - empty
                '120',  // Business Entity - fixed value
                'N/A',  // Cost Center
                'FIN',  // Department
                'Staff Expense reimbursement',  // PMT Category
                'N/A',  // Business Unit
                'RAIPUR',  // Location
                '22',  // State
                'BSF',  // Function
                'CM',  // FC-Vertical
                'SUB_DEPT_FIN_124',  // Sub Department
                '',  // Zone - empty
                $this->generatePartyAC($payment),
                $payment->amount ?? $payment->final_amount ?? 0,
                '',  // Reference
                $this->getRemarks($payment),  // sRemarks
                0,  // TDSBillAmount
                0,  // TDS
                '',  // sBRSUser - empty
            ];

            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue("{$col}{$curRow}", $value);
                $col++;
            }

            // Alternate row colors
            $bg = ($index % 2 === 0) ? 'FFE7E6E6' : 'FFFFFFFF';
            $sheet->getStyle("A{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // Set date format for Date column (B) and Time column (C)
            $sheet->getStyle("B{$curRow}")->getNumberFormat()->setFormatCode('DD-MM-YYYY');
            $sheet->getStyle("C{$curRow}")->getNumberFormat()->setFormatCode('HH:MM:SS');

            $curRow++;
            $index++;
        }

        // Auto-size columns
        for ($i = 1; $i <= $totalColumns; $i++) {
            $sheet->getColumnDimension($this->getColumnLetter($i))->setAutoSize(true);
        }
    }

    private function generateDocNo($payment, $index)
    {
        return '';
    }

    private function formatDate($dateTime)
    {
        $carbon = $dateTime ? Carbon::parse($dateTime) : now();
        return Date::dateTimeToExcel($carbon);
    }

    private function formatTime($dateTime)
    {
        $carbon = $dateTime ? Carbon::parse($dateTime) : now();
        return Date::dateTimeToExcel($carbon);
    }

private function generatePartyAC($payment)
{
    $code = null;

    if (isset($payment->employee_code)) {
        $code = $payment->employee_code;
    } elseif (isset($payment->emp_code)) {
        $code = $payment->emp_code;
    }

    if ($code) {
        // If starts with 'v' or 'V', return as it is
        if (strtolower(substr($code, 0, 1)) === 'v') {
            return $code;
        }

        // Otherwise prefix with 'E' and pad
        return 'E' . str_pad($code, 4, '0', STR_PAD_LEFT);
    }

    return 'E0000';
}
    private function getMonthName($monthNumber)
    {
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        return $months[$monthNumber] ?? 'Jan';
    }

    private function getCrop($payment)
    {
        return $payment->crop ?? $payment->crop_name ?? 'All Crop';
    }

    private function getRemarks($payment)
    {
        return $payment->remarks ?? 'Advance payment processed';
    }

    private function generateNarration($payment)
    {
        // If narration is already set on payment object, return it
        if (!empty($payment->narration)) {
            return $payment->narration;
        }

        $purpose = strtolower(trim(($payment->purpose ?? '') . ' ' . ($payment->description ?? '') . ' ' . ($payment->remarks ?? '')));
        $isImprest = !empty($payment->is_imprest) || str_contains($purpose, 'imprest');

        $isPromotional = !empty($payment->is_promotional) || (
            $isImprest && (
                str_contains($purpose, 'promotion') ||
                str_contains($purpose, 'promotional') ||
                str_contains($purpose, 'grade upgrade') ||
                str_contains($purpose, 'grade promotion') ||
                str_contains($purpose, 'upgrade')
            )
        );

        if ($isPromotional) {
            return 'Additional Fixed imprest as per grade promotion';
        }

        if ($isImprest) {
            $fyString = $payment->financial_year_name ?? $payment->fy_string ?? null;
            if (!$fyString && !empty($payment->year_id)) {
                try {
                    $financialYear = \App\Models\FinancialYear::find($payment->year_id);
                    if ($financialYear) {
                        if (!empty($financialYear->y1) && !empty($financialYear->y2)) {
                            $fyString = 'FY ' . $financialYear->y1 . '-' . substr($financialYear->y2, -2);
                        } elseif (!empty($financialYear->Year)) {
                            $parts = explode('-', $financialYear->Year);
                            $fyString = count($parts) === 2 ? 'FY ' . $parts[0] . '-' . substr($parts[1], -2) : 'FY ' . $financialYear->Year;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback to date calculation
                }
            }

            if (!$fyString) {
                $dateVal = $payment->date ?? $payment->created_at ?? now();
                $carbon = \Carbon\Carbon::parse($dateVal);
                $yearVal = (int) $carbon->format('Y');
                $monthVal = (int) $carbon->format('n');
                $startYear = ($monthVal >= 4) ? $yearVal : $yearVal - 1;
                $endYear = $startYear + 1;
                $fyString = 'FY ' . $startYear . '-' . substr((string) $endYear, -2);
            }

            return "Fixed Travel Imprest {$fyString}";
        }

        $requestMonth = (int) ($payment->request_month ?? date('n'));
        $monthName = $this->getMonthName($requestMonth);

        $currentMonth = (int) date('n');
        $currentYear = (int) date('y');

        $year = $currentYear;

        if (isset($payment->advance_type_code)) {
            if ($payment->advance_type_code === 'Act') {
                $activityTypeName = $payment->activity_name ?? 'Activity';
                return "Advance for {$activityTypeName} {$monthName}-{$year}";
            } elseif ($payment->advance_type_code === 'Exp') {
                return "Advance against expenses {$monthName}-{$year}";
            } else {
                return "Other Advance payment {$monthName}-{$year}";
            }
        }

        return "Other Advance payment {$monthName}-{$year}";
    }

    private function headerStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function getColumnLetter($columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr(65 + $temp) . $letter;
            $columnNumber = intval(($columnNumber - $temp - 1) / 26);
        }
        return $letter;
    }
}
