<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class EmployeeReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    protected Collection $employees;

    public function __construct(Collection $employees)
    {
        $this->employees = $employees;
    }

    public function collection(): Enumerable
    {
        $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01', null];

        return $this->employees->map(function ($emp, $i) use ($blankDates) {
            $status = getEmployeeStatusLabel(null, $emp->EmployeeID ?? null);

            $doj = ($emp->date_joining && !in_array($emp->date_joining, $blankDates))
                ? \Carbon\Carbon::parse($emp->date_joining)->format('d-M-Y') : '';

            $dor = ($emp->DateOfResignation && !in_array($emp->DateOfResignation, $blankDates))
                ? \Carbon\Carbon::parse($emp->DateOfResignation)->format('d-M-Y') : '';

            $dos = ($emp->DateOfSepration && !in_array($emp->DateOfSepration, $blankDates))
                ? \Carbon\Carbon::parse($emp->DateOfSepration)->format('d-M-Y') : '';

            return [
                $i + 1,
                trim($emp->emp_code ?? ''),
                trim($emp->emp_name ?? ''),
                $status,
                $emp->department_name  ?? '',
                $emp->grade_name       ?? '',
                $emp->designation_name ?? '',
                $emp->vertical_name    ?? '',
                $emp->hq_name          ?? '',
                $emp->zone_name        ?? '',
                $emp->region_name      ?? '',
                $emp->state_name       ?? '',
                $emp->mobile           ?? '',
                $emp->email            ?? '',
                $emp->reporting_name   ?? '',
                $doj,
                $dor,
                $dos,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'S.No', 'Emp Code', 'Employee Name', 'Status',
            'Department', 'Grade', 'Designation', 'Vertical',
            'HQ / Territory', 'Zone', 'Region', 'State / Branch',
            'Mobile', 'Email', 'Reporting Manager',
            'Date of Joining', 'Date of Resignation', 'Date of Separation',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '15648C']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $sheet->getHighestRow();
                $lastCol  = $sheet->getHighestColumn();

                // Borders
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle('thin');

                // Freeze header
                $sheet->freezePane('A2');

                // Alternate row colours
                for ($r = 2; $r <= $lastRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFill()->setFillType('solid')
                            ->getStartColor()->setRGB('F3F7FA');
                    }
                }
            },
        ];
    }
}
