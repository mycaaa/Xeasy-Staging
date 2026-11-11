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
use Carbon\Carbon;
use Illuminate\Support\Enumerable;

class ExpenseMonthWiseReportExport implements FromCollection, WithHeadings, WithStyles, WithCustomStartCell
{
    protected $monthlyTotals;
    protected $totalAllMonths;

    public function __construct(Collection $monthlyTotals, array $totalAllMonths)
    {
        $this->monthlyTotals = $monthlyTotals;
        $this->totalAllMonths = $totalAllMonths;
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function collection(): Enumerable
    {
        $data = $this->monthlyTotals->map(function ($item) {
            return [
                'Claim Month' => Carbon::create()->month($item->ClaimMonth)->format('F'),
                'Filled Total' => $item->FilledTotal,
                'Verified Total' => $item->VerifiedTotal,
                'Approved Total' => $item->ApprovedTotal,
                'Financed Total' => $item->FinancedTotal,
            ];
        });

        $data->push([]);
        $data->push([
            'Claim Month' => 'Total',
            'Filled Total' => $this->totalAllMonths['filled'],
            'Verified Total' => $this->totalAllMonths['verified'],
            'Approved Total' => $this->totalAllMonths['approved'],
            'Financed Total' => $this->totalAllMonths['financed'],
        ]);

        return $data;
    }

    public function headings(): array
    {
        return ['Claim Month', 'Filled Total', 'Verified Total', 'Approved Total', 'Financed Total'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'Expense Month Wise Report');
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

        $totalRow = $this->monthlyTotals->count() + 3;
        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFE0'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $lastRow = $totalRow;
        $sheet->getStyle("A3:E{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }
}
