<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class OdometerTrackingExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithStyles
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Odometer vs Tracking KM';
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
            'Tracking KM',
            'Difference (KM)',
            'Discrepancy Status',
            'Claim Amount (Rs)',
            'Remark'
        ];
    }

    public function collection(): Enumerable
    {
        $data = [];
        $sn = 1;

        foreach ($this->rows as $row) {
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
                $row['opening_km'] ?? 0,
                $row['closing_km'] ?? 0,
                $row['claimed_km'] ?? 0,
                $row['tracking_km'] !== null ? $row['tracking_km'] : 'N/A',
                $row['difference_km'] !== null ? $row['difference_km'] : 'N/A',
                strtoupper(str_replace('_', ' ', $row['status'] ?? '')),
                $row['amount'] ?? 0,
                $row['remark'] ?? '',
            ];
        }

        return collect($data);
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF15648C'],
                ],
            ],
        ];
    }
}
