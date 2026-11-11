<?php

namespace App\Exports\dashboard;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class ClaimExceptionExport implements FromCollection, WithHeadings, WithStyles, WithCustomStartCell
{
    protected $records;

    public function __construct(Collection $records)
    {
        $this->records = $records->map(fn($item) => (array) $item);
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function collection(): Enumerable
    {
        $rows = collect();

        foreach ($this->records as $index => $record) {
            $rows->push([
                'Sn' => $index + 1,
                'Employee' => \App\Helpers\CommonHelper::formatEmployeeCode($record['employee_code'] ?? '') . ' - ' . ($record['employee_name'] ?? ''),
                'Emp Status' => getEmployeeStatus(null, $record['employee_id'] ?? $record['EmployeeID'] ?? null),
                'Dept' => $record['department_name'] ?? '',
                'Month' => isset($record['month']) && is_numeric($record['month'])
                    ? date('F', mktime(0, 0, 0, $record['month'], 1))
                    : ($record['month'] ?? ''),
                'Status' => $record['status'] ?? '',
                'Submit Dt' => isset($record['date_of_submit']) ? date('d-m-Y', strtotime($record['date_of_submit'])) : '',
                'Final Amt' => $record['final_claim_amount'] ?? '',
                'Final Cnt' => $record['final_claim_count'] ?? '',
                'Detail Amt' => $record['detail_claim_amount'] ?? '',
                'Detail Cnt' => $record['detail_claim_count'] ?? '',
                'Diff Amt' => $record['difference_amount'] ?? '',
                'Diff Cnt' => $record['difference_count'] ?? '',
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Sn',
            'Employee',
            'Emp Status',
            'Dept',
            'Month',
            'Status',
            'Submit Dt',
            'Final Amt',
            'Final Cnt',
            'Detail Amt',
            'Detail Cnt',
            'Diff Amt',
            'Diff Cnt',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        // Title
        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', 'Claim Exception Report');

        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Header Row
        $sheet->getStyle('A2:M2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->setAutoFilter('A2:M2');

        // Borders for all data rows
        $totalRow = $this->records->count() + 2;
        $sheet->getStyle("A3:M{$totalRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Apply zebra pattern
        $startRow = 3;
        foreach ($this->records as $index => $record) {
            $row = $startRow + $index;
            $fillColor = ($row % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor],
                ],
            ]);
        }

        // Auto column sizing
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
