<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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

class VerifyDuplicateOdometerExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithStyles, WithEvents
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Duplicate Odometer Readings';
    }

    public function headings(): array
    {
        return [
            'Sn',
            'Emp Code',
            'Emp Name',
            'Department',
            'ExpId',
            'Bill Date',
            'Upload Date',
            'Opening Reading',
            'Closing Reading',
            'Total KM',
            'Dup. Type',
            'Verify Status',
        ];
    }

    public function collection(): Enumerable
    {
        $data = [];
        $sn = 1;

        foreach ($this->rows as $r) {
            $data[] = [
                $sn++,
                $r['EmpCode'] ?? '',
                $r['employee_name'] ?? '',
                $r['department_name'] ?? '',
                $r['ExpId'] ?? '',
                $r['BillDate'] ?? '',
                $r['CrDate'] ?? '',
                $r['odomtr_opening'] ?? '',
                $r['odomtr_closing'] ?? '',
                $r['TotKm'] ?? 0,
                $r['duplicate_type'] ?? '',
                ($r['v_verify'] ?? '') === 'Y' ? 'Verified' : 'Pending',
            ];
        }

        return collect($data);
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF15648C'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");

                $centerCols = ['A', 'B', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $leftCols = ['C', 'D'];
                foreach ($leftCols as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD0D5DD'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
