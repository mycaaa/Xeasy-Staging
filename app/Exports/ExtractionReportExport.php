<?php

namespace App\Exports;

use App\Exports\Concerns\WithGeneratedTimestamp;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Enumerable;

class ExtractionReportExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles,
    WithColumnWidths,
    WithTitle,
    WithEvents
{
    use WithGeneratedTimestamp;
    protected $data;
    protected $rowNumber = 0;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Return collection of data
     */
    public function collection(): Enumerable
    {
        return collect($this->data);
    }

    /**
     * Define column headings
     */
    public function headings(): array
    {
        return [
            'S.No',
            'Extract ID',
            'Employee Name',
            'Employee Status',
            'Employee Code',
            'Claim Type',
            'Claim Code',
            'Bill Date',
            'Claim Month',
            'Amount (₹)',
            'Claim Status',
            'Filled Date',
            'Temp Punch ID',
            'Remarks'
        ];
    }

    /**
     * Map data to columns
     */
    public function map(mixed $row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row->ExpId ?? '',
            $this->getEmployeeName($row),
            getEmployeeStatus(null, $row->EmployeeID ?? $row->CrBy ?? null),
            \App\Helpers\CommonHelper::formatEmployeeCode($row->EmpCode ?? ''),
            $row->ClaimName ?? '',
            $row->ClaimCode ?? '',
            $this->formatDate($row->BillDate),
            $this->formatMonth($row->ClaimMonth),
            $this->formatAmount($row->FilledTAmt),
            $row->ClaimStatus ?? '',
            $this->formatDate($row->FilledDate),
            $row->temp_punch_id ?? '',
            $row->Remark ?? ''
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet): ?array
    {
        $lastRow = $this->rowNumber + 1;

        // Style for header row (Row 1)
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Auto-fit row height for header
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Apply borders to all data cells
        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        // Center align S.No, Extract ID, Employee Status, Employee Code, Claim Status
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("K2:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Right align amount column
        $sheet->getStyle("J2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Center align date columns
        $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("L2:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add alternating row colors (zebra striping)
        for ($i = 2; $i <= $lastRow; $i++) {
            if ($i % 2 == 0) {
                $sheet->getStyle("A{$i}:N{$i}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2']
                    ]
                ]);
            }
        }

        // Wrap text for remarks column
        $sheet->getStyle("N2:N{$lastRow}")->getAlignment()->setWrapText(true);

        return [];
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 8,   // S.No
            'B' => 12,  // Extract ID
            'C' => 30,  // Employee Name
            'D' => 15,  // Employee Status
            'E' => 15,  // Employee Code
            'F' => 25,  // Claim Type
            'G' => 12,  // Claim Code
            'H' => 15,  // Bill Date
            'I' => 15,  // Claim Month
            'J' => 15,  // Amount
            'K' => 12,  // Claim Status
            'L' => 15,  // Filled Date
            'M' => 15,  // Temp Punch ID
            'N' => 35,  // Remarks
        ];
    }

    /**
     * Set worksheet title
     */
    public function title(): string
    {
        return 'Extraction Report';
    }

    /**
     * Register events for additional formatting
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->insertTimestampRow($sheet);
                $lastRow = $this->rowNumber + 1;

                // Add total row
                $totalRow = $lastRow + 1;
                
                $sheet->setCellValue("A{$totalRow}", '');
                $sheet->setCellValue("B{$totalRow}", '');
                $sheet->setCellValue("C{$totalRow}", '');
                $sheet->setCellValue("D{$totalRow}", '');
                $sheet->setCellValue("E{$totalRow}", '');
                $sheet->setCellValue("F{$totalRow}", '');
                $sheet->setCellValue("G{$totalRow}", '');
                $sheet->setCellValue("H{$totalRow}", '');
                $sheet->setCellValue("I{$totalRow}", 'TOTAL:');
                $sheet->setCellValue("J{$totalRow}", "=SUM(J2:J{$lastRow})");

                // Style total row
                $sheet->getStyle("I{$totalRow}:J{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E7E6E6']
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN
                        ]
                    ]
                ]);

                // Right align total amount
                $sheet->getStyle("J{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("I{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Format amount as number with 2 decimal places
                $sheet->getStyle("J2:J{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                // Freeze header row
                $sheet->freezePane('A2');

                // Auto-filter for header row
                $sheet->setAutoFilter("A1:N{$lastRow}");
            },
        ];
    }

    /**
     * Helper: Get employee name from concatenated field or construct it
     */
    private function getEmployeeName($row)
    {
        if (isset($row->employee_name)) {
            // Remove employee code from name if present in brackets
            return preg_replace('/\s*\([^)]*\)$/', '', $row->employee_name);
        }
        
        // Fallback: construct from parts if available
        $parts = array_filter([
            $row->Fname ?? '',
            $row->Sname ?? '',
            $row->Lname ?? ''
        ]);
        
        return implode(' ', $parts) ?: 'N/A';
    }

    /**
     * Helper: Format date
     */
    private function formatDate($date)
    {
        if (!$date || \App\Support\ZeroDate::none($date) || \App\Support\ZeroDate::none($date)) {
            return '';
        }
        
        try {
            return date('d-M-Y', strtotime($date));
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Helper: Format month
     */
    private function formatMonth($month)
    {
        if (!$month || !is_numeric($month)) {
            return '';
        }
        
        try {
            return date('F Y', mktime(0, 0, 0, intval($month), 1));
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Helper: Format amount
     */
    private function formatAmount($amount)
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        
        return floatval($amount);
    }
}