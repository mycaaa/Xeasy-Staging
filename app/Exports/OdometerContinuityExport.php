<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class OdometerContinuityExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithStyles
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Odometer Continuity Report';
    }

    public function headings(): array
    {
        return [
            'SN',
            'Exp ID',
            'Emp Code',
            'Employee Name',
            'Department',
            'Designation',
            'Headquarter',
            'Travel Date',
            'Vehicle Type',
            'Claim Status',
            'Opening KM',
            'Closing KM',
            'Claimed KM',
            'Previous Closing KM',
            'Previous Date',
            'Previous Exp ID',
            'Gap (KM)',
            'Continuity Status',
            'Comparison Summary',
            'Claim Amount (Rs)',
            'Remarks'
        ];
    }

    public function collection(): Enumerable
    {
        $data = [];
        $sn = 1;

        foreach ($this->rows as $row) {
            $prevClosing = $row['prev_closing_km'] !== null ? number_format($row['prev_closing_km'], 1) : 'First Entry';
            $prevDate = $row['prev_date'] ?? '-';
            $prevExpId = !empty($row['prev_exp_id']) ? '#' . $row['prev_exp_id'] : '-';
            $gap = $row['prev_closing_km'] !== null ? number_format($row['gap_km'], 1) : '-';

            $data[] = [
                $sn++,
                $row['exp_id'] ?? '',
                $row['emp_code'] ?? '',
                $row['emp_name'] ?? '',
                $row['department'] ?? '',
                $row['designation'] ?? '',
                $row['headquarter'] ?? '',
                $row['bill_date'] ?? '',
                $row['vehicle_type'] ?? '',
                $row['claim_status'] ?? '',
                number_format((float) ($row['open_km'] ?? 0), 1),
                number_format((float) ($row['close_km'] ?? 0), 1),
                number_format((float) ($row['claimed_km'] ?? 0), 1),
                $prevClosing,
                $prevDate,
                $prevExpId,
                $gap,
                $row['status_text'] ?? '',
                $row['comparison_detail'] ?? '',
                number_format((float) ($row['claimed_amount'] ?? 0), 2),
                $row['remarks'] ?? '',
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
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF15648C'],
                ],
            ],
        ];
    }
}
