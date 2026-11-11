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

class EmployeeRunningKmExport implements FromCollection, WithHeadings, WithStyles, WithCustomStartCell
{
    protected $employees;

    public function __construct(Collection $employees)
    {
        
        
        $this->employees = $employees->map(fn($item) => (array) $item);
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function collection(): Enumerable
    {
        $rows = collect();

        foreach ($this->employees as $employee) {
            $overall = $employee['travel_logs']['overall_totals'] ?? [
                'total_4w_km' => 0,
            ];

            $rows->push([
                'Employee ID'             => $employee['EmployeeID'] ?? '',
                'Employee Code'           => $employee['emp_code'] ?? '',
                'Employee Name'           => $employee['employee_name'] ?? '',
                'Employee Status'         => getEmployeeStatus(null, $employee['EmployeeID'] ?? null),
                'Department Name'         => $employee['department_name'] ?? '',
                'Department Code'         => $employee['department_code'] ?? '',
                'Grade Name'              => $employee['grade_name'] ?? '',
                'Policy Name'             => $employee['PolicyName'] ?? '',
                'Policy Entry Date'       => $employee['PolicyEntryDate'] ?? '',
                'Policy Age'              => $employee['policy_age'] ?? '',
                'Policy Vehicle Value'    => $employee['as_per_pol_vehiclevalue'] ?? '',
                'Current Vehicle Value'   => $employee['curr_vehiclevalue'] ?? '',
                'Policy (Km/Month)'       => $employee['as_year_time'] ?? '',
                'Total 4W KM'             => $overall['total_4w_km'] ?? 0,
                'Policy Rate'             => $employee['PolicyRate'] ?? '',
                'Current Rate'      => $employee['api_curr_rate'] ?? '',
                'After Implementation Rate' => $employee['after_imp_rate'] ?? '',

            ]);
        }

        
        
        $total4W = $rows->sum('Total 4W KM');

        
        
        $rows->push([]);
        $rows->push([
            'Employee ID'             => '',
            'Employee Code'           => 'Total',
            'Employee Name'           => '',
            'Employee Status'         => '',
            'Department Name'         => '',
            'Department Code'         => '',
            'Grade Name'              => '',
            'Policy Name'             => '',
            'Policy Entry Date'       => '',
            'Policy Age'              => '',
            'Policy Vehicle Value'    => '',
            'Current Vehicle Value'   => '',
            'Policy (Km/Month)'       => '',
            'Total 4W KM'             => $total4W,
            'Policy Rate'             => '',
            'Current Rate'      => '',
            'After Implementation Rate' => '',
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Employee Code',
            'Employee Name',
            'Employee Status',
            'Department Name',
            'Department Code',
            'Grade Name',
            'Policy Name',
            'Policy Entry Date',
            'Policy Age',
            'Policy Vehicle Value',
            'Current Vehicle Value',
            'Policy (Km/Month)',
            'Total 4W KM',
            'Policy Rate',
            'Current Rate',
            'After Implementation Rate',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        
        
        $sheet->mergeCells('A1:Q1');
        $sheet->setCellValue('A1', 'Employee Running KM Report');

        $sheet->getStyle('A1:Q1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        
        
        $sheet->getStyle('A2:Q2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->setAutoFilter('A2:Q2');

        
        
        $totalRow = $this->employees->count() + 3;
        $sheet->getStyle("A3:Q{$totalRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        
        
        $sheet->getStyle("A{$totalRow}:Q{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFE0']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        
        
        $startRow = 3;
        foreach ($this->employees as $index => $employee) {
            $curr = $employee['api_curr_rate'] ?? null;
            $after = $employee['after_imp_rate'] ?? null;

            if ($curr !== null && $after !== null && $curr != $after) {
                $row = $startRow + $index;

                $sheet->getStyle("A{$row}:Q{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF3CD'], 
                        
                    ],
                ]);
            }
        }

        
        
        foreach (range('A', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
