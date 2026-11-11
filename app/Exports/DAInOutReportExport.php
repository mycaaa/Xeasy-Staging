<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class DAInOutReportExport implements FromCollection, WithTitle, WithEvents
{
    protected $data;
    protected $companyId;
    protected $dateRange;

    public function __construct($data, $companyId = null, $dateRange = null)
    {
        $this->data = $data;
        $this->companyId = $companyId ?? session('company_id', 1);
        $this->dateRange = $dateRange;
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function title(): string
    {
        return 'DA In Out Report';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $currentRow = 1;

                // Company name
                $companyName = $this->companyId == 1 ? 'VNR Seeds Pvt. Ltd.' : 'VVNR Seeds Pvt. Ltd.';

                // Report Title
                $sheet->setCellValue("A{$currentRow}", $companyName);
                $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['argb' => 'FF1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(25);
                $currentRow++;

                // Report Name
                $sheet->setCellValue("A{$currentRow}", 'DA In/Out Report');
                $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['argb' => 'FF000000'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(22);
                $currentRow++;

                // Date Range
                if ($this->dateRange) {
                    $dates = explode(' to ', $this->dateRange);
                    $startDate = isset($dates[0]) ? date('d-m-Y', strtotime($dates[0])) : '';
                    $endDate = isset($dates[1]) ? date('d-m-Y', strtotime($dates[1])) : $startDate;

                    $sheet->setCellValue("A{$currentRow}", "Period: {$startDate} to {$endDate}");
                    $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
                    $sheet->getStyle("A{$currentRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'color' => ['argb' => 'FF666666'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($currentRow)->setRowHeight(18);
                    $currentRow++;
                }

                // Generated Date
                $sheet->setCellValue("A{$currentRow}", 'Generated on: ' . now()->format('d-m-Y h:i A'));
                $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['argb' => 'FF666666'],
                        'italic' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $currentRow++;

                // Empty row for spacing
                $currentRow++;

                // Column Headers
                $isVvnr = ((int) $this->companyId === 11);
                $amountColumnName = $isVvnr ? 'Total Verified Amount' : 'Total Financed Amount';
                $dateColumnName = $isVvnr ? 'Verified Date' : 'Pay Date';
                $headers = [
                    'Sr. No.',
                    'Employee Name',
                    'Employee Code',
                    'Status',
                    'Department',
                    'Designation',
                    'Claim Month',
                    $amountColumnName,
                    $dateColumnName,
                ];

                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$currentRow}", $header);
                    $col++;
                }

                // Header styling
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                        'size' => 12,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(20);
                $currentRow++;

                // Data rows
                $dataStartRow = $currentRow;
                foreach ($this->data as $index => $row) {
                    // Determine status based on DateOfResignation
                     $status = 'A';

                    if (                        \App\Support\ZeroDate::has($row->DateOfResignation) &&
                        $row->DateOfResignation != '1970-01-01'
                    ) {
                        $status = 'D';
                    }

                    $sheet->setCellValue("A{$currentRow}", $index + 1);
                    $sheet->setCellValue("B{$currentRow}", $row->EmployeeName ?? '');
                    $sheet->setCellValue("C{$currentRow}", \App\Helpers\CommonHelper::formatEmployeeCode($row->EmpCode ?? ''));
                    $sheet->setCellValue("D{$currentRow}", $status);
                    $sheet->setCellValue("E{$currentRow}", $row->department_name ?? '');
                    $sheet->setCellValue("F{$currentRow}", $row->designation_name ?? '');
                    $sheet->setCellValue("G{$currentRow}", $row->ClaimMonth ? \DateTime::createFromFormat('!m', $row->ClaimMonth)->format('F') : '');
                    $sheet->setCellValue("H{$currentRow}", round((float) ($row->TotalVerifiedAmount ?? 0), 2));

                    // Format and display PayDate
                    $payDate = '';

                    if (!empty($row->PayDate) && \App\Support\ZeroDate::has($row->PayDate)) {
                        $payDate = date('d-m-Y', strtotime($row->PayDate));
                    } elseif (!empty($row->payment_date) && \App\Support\ZeroDate::has($row->payment_date) && \App\Support\ZeroDate::has($row->payment_date)) {
                        $payDate = date('d-m-Y', strtotime($row->payment_date));
                    }
                    $sheet->setCellValue("I{$currentRow}", $payDate);

                    // Apply striped row design
                    $fillColor = ($currentRow % 2 == 0) ? 'FFF2F2F2' : 'FFFFFFFF';

                    $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $fillColor],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFD3D3D3'],
                            ],
                        ],
                    ]);

                    $currentRow++;
                }

                $lastRow = $currentRow - 1;

                // Center align specific columns
                if ($lastRow >= $dataStartRow) {
                    $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$dataStartRow}:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$dataStartRow}:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$dataStartRow}:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("I{$dataStartRow}:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Auto-size columns
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Add summary row
                $currentRow++;
                $sheet->setCellValue("A{$currentRow}", 'Total Records:');
                $sheet->setCellValue("B{$currentRow}", $this->data->count());
                $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE7E6E6'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Calculate total amount
                $totalAmount = $this->data->sum('TotalVerifiedAmount');
                $sheet->setCellValue("H{$currentRow}", round((float) ($totalAmount), 2));
                $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
