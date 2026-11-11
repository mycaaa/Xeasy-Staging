<?php

namespace App\Exports\dashboard;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class DepartmentExpenseComparisonExport implements FromCollection, WithHeadings, WithStyles, WithCustomStartCell
{
    protected $departmentTotals;
    protected $yearId;
    protected $previousYearId;

    public function __construct(Collection $departmentTotals, $yearId, $previousYearId)
    {
        $this->departmentTotals = $departmentTotals;
        $this->yearId = $yearId;
        $this->previousYearId = $previousYearId;
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function collection(): Enumerable
    {
        $rows = $this->departmentTotals->map(function ($item) {
            return [
                'Department' => $item->department_name,
                'Department Code' => $item->department_code,
                'Current Year Total' => $item->{"TotalFinancedTAmt_Y{$this->yearId}"},
                'Previous Year Total' => $item->{"TotalFinancedTAmt_Y{$this->previousYearId}"},
                'Variation (%)' => $item->VariationPercentage,
            ];
        });
        $totalCurrent = $this->departmentTotals->sum("TotalFinancedTAmt_Y{$this->yearId}");
        $totalPrevious = $this->departmentTotals->sum("TotalFinancedTAmt_Y{$this->previousYearId}");
        $variation = $totalPrevious == 0 ? null : round((($totalCurrent - $totalPrevious) / $totalPrevious) * 100, 2);
        $rows->push([
            'Department' => 'Total',
            'Department Code' => '',
            'Current Year Total' => $totalCurrent,
            'Previous Year Total' => $totalPrevious,
            'Variation (%)' => $variation,
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return ['Department', 'Department Code', "Year {$this->yearId} Total", "Year {$this->previousYearId} Total", 'Variation (%)'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', "Department Expense Comparison Report (Year {$this->yearId} vs Year {$this->previousYearId})");
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getStyle('A2:E2')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->setAutoFilter('A2:E2');

        $lastRow = $this->departmentTotals->count() + 3;
        $sheet->getStyle("A3:E{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getStyle("A{$lastRow}:E{$lastRow}")->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFE0'],
            ],
        ]);

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }
}
