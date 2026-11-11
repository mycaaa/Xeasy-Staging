<?php

namespace App\Exports\dashboard;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ExpenseDepartmentWiseReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $departmentTotals;
    protected $yearId;
    protected $previousYearId;
    protected $totalAllDepartments;

    public function __construct(Collection $departmentTotals, ?int $yearId, array $totalAllDepartments)
    {
        $this->departmentTotals = $departmentTotals;
        $this->yearId = is_int($yearId) ? $yearId : (int) now()->year;
        $this->previousYearId = $this->yearId - 1;
        $this->totalAllDepartments = $totalAllDepartments;
    }

    public function collection(): Enumerable
    {
        $data = $this->departmentTotals->map(function ($item) {
            return [
                'department_name' => $item->department_name,
                "TotalFinancedTAmt_Y{$this->yearId}" => $item->{"TotalFinancedTAmt_Y{$this->yearId}"} ?? 0,
                "TotalFinancedTAmt_Y{$this->previousYearId}" => $item->{"TotalFinancedTAmt_Y{$this->previousYearId}"} ?? 0,
                'VariationPercentage' => ($item->VariationPercentage ?? 0) . '%',
            ];
        });

        $data->push([]);
        $data->push([
            'department_name' => 'Total',
            "TotalFinancedTAmt_Y{$this->yearId}" => $this->totalAllDepartments["TotalFinancedTAmt_Y{$this->yearId}"] ?? 0,
            "TotalFinancedTAmt_Y{$this->previousYearId}" => $this->totalAllDepartments["TotalFinancedTAmt_Y{$this->previousYearId}"] ?? 0,
            'VariationPercentage' => ($this->totalAllDepartments['VariationPercentage'] ?? 0) . '%',
        ]);

        return $data;
    }

    public function headings(): array
    {
        return [["Expense Department Wise Report"], ['Department', "Current Year Total (₹)", "Previous Year Total (₹)", 'Variation %']];
    }

    public function title(): string
    {
        return "Department Wise Report";
    }

    public function styles(Worksheet $sheet): ?array
    {
        $baseRows = $this->departmentTotals->count() + 2;
        $totalRow = $baseRows + 1;
        $highestColumn = 'D';

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
            2 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1F4E78'],
                ],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ],
            "A{$totalRow}:{$highestColumn}{$totalRow}" => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFE0'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = 'D';
                $highestRow = $sheet->getHighestRow();
                $baseRows = $this->departmentTotals->count() + 2;
                $totalRow = $baseRows + 1;

                $sheet->mergeCells("A1:{$highestColumn}1");

                $contentRange = "A2:{$highestColumn}" . ($totalRow - 1);
                $sheet
                    ->getStyle($contentRange)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $totalRange = "A{$totalRow}:{$highestColumn}{$totalRow}";
                $style = $sheet->getStyle($totalRange);
                $style
                    ->getBorders()
                    ->getTop()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $style
                    ->getBorders()
                    ->getLeft()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $style
                    ->getBorders()
                    ->getRight()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $style
                    ->getBorders()
                    ->getInside()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $style
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $style
                    ->getBorders()
                    ->getOutline()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);

                $sheet->setAutoFilter("A2:{$highestColumn}2");

                $colIndex = Coordinate::columnIndexFromString($highestColumn);
                for ($i = 1; $i <= $colIndex; $i++) {
                    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
                }
            },
        ];
    }
}
