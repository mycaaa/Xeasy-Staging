<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Enumerable;

class RunningKmExport implements FromCollection, WithEvents, WithTitle, Responsable
{
    use Exportable;

    protected $data;
    protected $filename = "Running_KM_Report.xlsx";

    // Month order for display
    protected $monthOrder = [
        'apr' => 'April',
        'may' => 'May',
        'jun' => 'June',
        'jul' => 'July',
        'aug' => 'August',
        'sep' => 'September',
        'oct' => 'October',
        'nov' => 'November',
        'dec' => 'December',
        'jan' => 'January',
        'feb' => 'February',
        'mar' => 'March',
    ];

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return "Running KM Report";
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $currentRow = 1;

                // Calculate total columns: 10 base + (12 months × 2 columns per month) + 2 total columns
                $baseColumns = 10;
                $monthColumns = count($this->monthOrder) * 2; // 2W KM, 4W KM only
                $totalRowColumns = 2; // Total 2W, Total 4W
                $totalColumns = $baseColumns + $monthColumns + $totalRowColumns;
                $lastColumn = $this->getColumnLetter($totalColumns);

                // Header Row with merged cells
                $headers = [
                    "S No.",
                    "Employee Code",
                    "Employee Name",
                    "Status",
                    "Date of Joining",
                    "Date of Resignation",
                    "Date of Separation",
                    "Vertical",
                    "Department",
                    "Grade",
                ];

                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$currentRow}", $header);
                    $col++;
                }

                // Month names spanning 2 columns each (2W, 4W)
                foreach ($this->monthOrder as $monthKey => $monthName) {
                    $startCol = $col;
                    $endCol = $this->getColumnLetter($this->getColumnNumber($col) + 1);
                    
                    $sheet->setCellValue("{$startCol}{$currentRow}", $monthName);
                    $sheet->mergeCells("{$startCol}{$currentRow}:{$endCol}{$currentRow}");
                    
                    $col = $this->getColumnLetter($this->getColumnNumber($endCol) + 1);
                }

                // Add "Total" header spanning 2 columns
                $startCol = $col;
                $endCol = $this->getColumnLetter($this->getColumnNumber($col) + 1);
                $sheet->setCellValue("{$startCol}{$currentRow}", "Total");
                $sheet->mergeCells("{$startCol}{$currentRow}:{$endCol}{$currentRow}");

                $this->applyHeaderStyle($sheet, "A{$currentRow}:{$lastColumn}{$currentRow}");
                $currentRow++;

                // Sub-headers Row (2W, 4W under each month)
                $col = 'A';
                
                // Empty cells for base columns
                for ($i = 0; $i < $baseColumns; $i++) {
                    $sheet->setCellValue("{$col}{$currentRow}", "");
                    $col++;
                }

                // Add sub-headers for each month (2W, 4W)
                foreach ($this->monthOrder as $monthKey => $monthName) {
                    $sheet->setCellValue("{$col}{$currentRow}", "2W");
                    $col++;
                    $sheet->setCellValue("{$col}{$currentRow}", "4W");
                    $col++;
                }

                // Add total sub-headers
                $sheet->setCellValue("{$col}{$currentRow}", "2W");
                $col++;
                $sheet->setCellValue("{$col}{$currentRow}", "4W");

                $this->applySubHeaderStyle($sheet, "A{$currentRow}:{$lastColumn}{$currentRow}");
                $currentRow++;

                // Data Rows
                $index = 1;
                $columnTotals2W = array_fill_keys(array_keys($this->monthOrder), 0);
                $columnTotals4W = array_fill_keys(array_keys($this->monthOrder), 0);
                
                foreach ($this->data as $row) {
                    // Check if employee is deactivated using centralised helper
                    $empStatus = getStsUser($row->EmployeeID ?? $row->EmployeeId ?? null);
                    $isDeactivated = in_array($empStatus, ['D', 'R']);

                    $rowColor = $isDeactivated ? "FFFFC7CE" : ($index % 2 === 0 ? "FFE7E6E6" : "FFFFFFFF");

                    $data = [
                        $index,
                        \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code ?? ""),
                        $row->employee_name ?? "",
                        $empStatus,
                        $this->formatDate($row->DateJoining),
                        $this->formatDate($row->DateOfResignation),
                        $this->formatDate($row->DateOfSepration),
                        $row->vertical_name ?? "",
                        $row->department_name ?? "",
                        $row->grade_name ?? "",
                    ];

                    // Calculate row totals
                    $rowTotal2W = 0;
                    $rowTotal4W = 0;

                    // Add month data (only KM, no amounts)
                    foreach ($this->monthOrder as $monthKey => $monthName) {
                        $km2w = $row->{$monthKey . '_2w_km'} ?? 0;
                        $km4w = $row->{$monthKey . '_4w_km'} ?? 0;
                        
                        $data[] = $this->formatNumber($km2w);
                        $data[] = $this->formatNumber($km4w);
                        
                        $rowTotal2W += $km2w;
                        $rowTotal4W += $km4w;
                        
                        $columnTotals2W[$monthKey] += $km2w;
                        $columnTotals4W[$monthKey] += $km4w;
                    }

                    // Add row totals
                    $data[] = $this->formatNumber($rowTotal2W);
                    $data[] = $this->formatNumber($rowTotal4W);

                    $col = 'A';
                    foreach ($data as $value) {
                        $sheet->setCellValue("{$col}{$currentRow}", $value);
                        $col++;
                    }

                    // Center align specific columns
                    $centerCols = ['A', 'B', 'D', 'E', 'F', 'G'];
                    foreach ($centerCols as $c) {
                        $sheet
                            ->getStyle("{$c}{$currentRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Right align all month data columns (numbers)
                    for ($i = $baseColumns + 1; $i <= $totalColumns; $i++) {
                        $colLetter = $this->getColumnLetter($i);
                        $sheet
                            ->getStyle("{$colLetter}{$currentRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Apply row styling
                    $sheet->getStyle("A{$currentRow}:{$lastColumn}{$currentRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $rowColor],
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                        ],
                    ]);

                    // Bold the total columns
                    $totalStartCol = $this->getColumnLetter($baseColumns + $monthColumns + 1);
                    $totalEndCol = $this->getColumnLetter($totalColumns);
                    $sheet->getStyle("{$totalStartCol}{$currentRow}:{$totalEndCol}{$currentRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $isDeactivated ? 'FFFFC7CE' : 'FFFDE9D9'],
                        ],
                    ]);

                    $currentRow++;
                    $index++;
                }

                // Grand Total Row
                $sheet->setCellValue("A{$currentRow}", "Grand Total");
                $sheet->mergeCells("A{$currentRow}:J{$currentRow}");
                
                $col = $this->getColumnLetter($baseColumns + 1);
                $grandTotal2W = 0;
                $grandTotal4W = 0;
                
                foreach ($this->monthOrder as $monthKey => $monthName) {
                    $sheet->setCellValue("{$col}{$currentRow}", $this->formatNumber($columnTotals2W[$monthKey]));
                    $col++;
                    $sheet->setCellValue("{$col}{$currentRow}", $this->formatNumber($columnTotals4W[$monthKey]));
                    $col++;
                    
                    $grandTotal2W += $columnTotals2W[$monthKey];
                    $grandTotal4W += $columnTotals4W[$monthKey];
                }
                
                // Overall grand total
                $sheet->setCellValue("{$col}{$currentRow}", $this->formatNumber($grandTotal2W));
                $col++;
                $sheet->setCellValue("{$col}{$currentRow}", $this->formatNumber($grandTotal4W));
                
                // Style grand total row
                $sheet->getStyle("A{$currentRow}:{$lastColumn}{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => Color::COLOR_WHITE],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Auto-size columns
                for ($i = 1; $i <= $totalColumns; $i++) {
                    $colLetter = $this->getColumnLetter($i);
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }
                $sheet->calculateColumnWidths();
            },
        ];
    }

    private function applyTitleStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function applySubHeaderStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '2E5C8A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function applyHeaderStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function formatDate($date): string
    {
        return $date && \App\Support\ZeroDate::has($date) ? \Carbon\Carbon::parse($date)->format('d-m-Y') : "";
    }

    private function formatNumber($value): string
    {
        if (empty($value) || $value == 0) {
            return "";
        }
        return number_format($value, 2);
    }

    private function getColumnLetter($columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $columnNumber = ($columnNumber - $temp - 1) / 26;
        }
        return $letter;
    }

    private function getColumnNumber($columnLetter): int
    {
        $number = 0;
        $length = strlen($columnLetter);
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($columnLetter[$i]) - ord('A') + 1);
        }
        return $number;
    }
}