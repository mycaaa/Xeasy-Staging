<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class PaymentStagesSummaryExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $data;

    protected $withEmployee;

    public function __construct($data, $withEmployee)
    {
        $this->data = $data;
        $this->withEmployee = $withEmployee;
    }

    public function collection(): Enumerable
    {
        return $this->data instanceof Enumerable ? $this->data : collect($this->data);
    }

    public function headings(): array
    {
        $headings = [
            'Month',
            'Filled Amount',
            'Verified Amount',
            'Approved Amount',
            'Financed Amount',
            'Advance',
            'Paid',
            'Adv + Paid',
        ];

        if ($this->withEmployee) {
            array_unshift($headings, 'Employee Code', 'Employee Name');
        } else {
            array_unshift($headings, 'S.No');
        }

        return $headings;
    }

    public function map(mixed $row): array
    {
        static $serialNumber = 1;

        // Fix property names here - use the correct property names from your query
        $advPlusPaid = $row->adv_plus_paid ?? ($row->advance_amount + $row->paid_amount);

        if ($this->withEmployee) {
            return [
                \App\Helpers\CommonHelper::formatEmployeeCode($row->EmpCode ?? 'N/A'),
                $row->EmployeeName ?? 'N/A',
                $row->Month ?? 'N/A',
                (float) ($row->filled_amount ?? 0),      // Changed from 'filled'
                (float) ($row->verified_amount ?? 0),    // Changed from 'verified'
                (float) ($row->approved_amount ?? 0),    // Changed from 'approved'
                (float) ($row->financed_amount ?? 0),    // Changed from 'financed'
                (float) ($row->advance_amount ?? 0),     // Changed from 'advance'
                (float) ($row->paid_amount ?? 0),        // Changed from 'paid'
                (float) $advPlusPaid,
            ];
        } else {
            $rowData = [
                $serialNumber++,
                $row->month ?? 'N/A',
                (float) ($row->filled_amount ?? 0),      // Changed from 'filled'
                (float) ($row->verified_amount ?? 0),    // Changed from 'verified'
                (float) ($row->approved_amount ?? 0),    // Changed from 'approved'
                (float) ($row->financed_amount ?? 0),    // Changed from 'financed'
                (float) ($row->advance_amount ?? 0),     // Changed from 'advance'
                (float) ($row->paid_amount ?? 0),        // Changed from 'paid'
                (float) $advPlusPaid,
            ];

            if ($serialNumber > $this->data->count()) {
                $serialNumber = 1;
            }

            return $rowData;
        }
    }

    public function styles(Worksheet $sheet): ?array
    {
        // Style the header row
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4eac6d'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Highlight Adv+Paid column
        $lastColumn = $this->withEmployee ? 'J' : 'I';
        $sheet->getStyle($lastColumn.'2:'.$lastColumn.$sheet->getHighestRow())->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF3CD'],
            ],
            'font' => [
                'bold' => true,
            ],
        ]);

        // Amounts are written as numbers (they used to be number_format() strings, i.e. text cells
        // that Excel could not SUM); display them with thousands separators.
        $amountFrom = $this->withEmployee ? 'D' : 'C';
        $sheet->getStyle($amountFrom.'2:'.$lastColumn.$sheet->getHighestRow())
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // Style amount columns to be right-aligned
        $sheet->getStyle('A2:'.$lastColumn.$sheet->getHighestRow())
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Style the first column(s) to be left-aligned
        $firstColumn = $this->withEmployee ? 'C' : 'B';
        $sheet->getStyle('A2:'.$firstColumn.$sheet->getHighestRow())
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Add borders to all cells
        $sheet->getStyle('A1:'.$lastColumn.$sheet->getHighestRow())
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        return [];
    }
}
