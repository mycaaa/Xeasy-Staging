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

class ExpenseClaimTypeWiseReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $claimTypeTotals;

    public function __construct(Collection $claimTypeTotals)
    {
        $this->claimTypeTotals = $claimTypeTotals;
    }

    public function collection(): Enumerable
    {
        $data = $this->claimTypeTotals->map(function ($item) {
            return (array) $item;
        });

        if (!$data->isEmpty() && $data->first()['ClaimName'] !== 'No Data') {
            $totals = [
                'ClaimName' => 'Total',
                'ClaimCode' => '',
                'FilledTotal' => $data->sum(function ($item) {
                    return (float) $item['FilledTotal'];
                }),
                'VerifiedTotal' => $data->sum(function ($item) {
                    return (float) $item['VerifiedTotal'];
                }),
                'ApprovedTotal' => $data->sum(function ($item) {
                    return (float) $item['ApprovedTotal'];
                }),
                'FinancedTotal' => $data->sum(function ($item) {
                    return (float) $item['FinancedTotal'];
                }),
            ];
            $data->push($totals);
        } elseif ($data->isEmpty()) {
            $data = collect([['ClaimName' => 'No Data', 'ClaimCode' => '', 'FilledTotal' => 0, 'VerifiedTotal' => 0, 'ApprovedTotal' => 0, 'FinancedTotal' => 0]]);
        }

        return $data;
    }

    public function headings(): array
    {
        return [["Expense Claim Type Wise Report"], ['Claim Name', 'Claim Code', 'Filled Total', 'Verified Total', 'Approved Total', 'Financed Total']];
    }

    public function title(): string
    {
        return "Claim Type Wise Report";
    }

    public function styles(Worksheet $sheet): ?array
    {
        $baseRows = $this->claimTypeTotals->count() + 2;
        $totalRow = $baseRows + ($this->claimTypeTotals->isEmpty() || $this->claimTypeTotals->first()->ClaimName !== 'No Data' ? 1 : 0);
        $highestColumn = 'F';

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
                $highestColumn = 'F';
                $highestRow = $sheet->getHighestRow();
                $baseRows = $this->claimTypeTotals->count() + 2;
                $totalRow = $baseRows + ($this->claimTypeTotals->isEmpty() || $this->claimTypeTotals->first()->ClaimName !== 'No Data' ? 1 : 0);

                $sheet->mergeCells("A1:{$highestColumn}1");

                if ($totalRow <= $highestRow) {
                    $contentRange = "A2:{$highestColumn}" . ($totalRow - 1);
                    $sheet
                        ->getStyle($contentRange)
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                } else {
                    $sheet
                        ->getStyle("A2:{$highestColumn}{$highestRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                }

                if ($totalRow <= $highestRow) {
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
                }
                $sheet->setAutoFilter("A2:{$highestColumn}2");
                $colIndex = Coordinate::columnIndexFromString($highestColumn);
                for ($i = 1; $i <= $colIndex; $i++) {
                    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
                }
            },
        ];
    }
}
