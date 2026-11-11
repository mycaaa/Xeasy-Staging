<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Enumerable;

class RunningKmMonthExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize
{
    protected $data;

    // MONTHS MAP (Apr → Mar)
    protected $months = [
        'apr',
        'may',
        'jun',
        'jul',
        'aug',
        'sep',
        'oct',
        'nov',
        'dec',
        'jan',
        'feb',
        'mar'
    ];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection(): Enumerable
    {
        $output = new Collection();

        foreach ($this->data as $row) {
            $newRow = [
                \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code),
                $row->employee_name,
                getEmployeeStatus(null, $row->EmployeeID ?? null),
            ];

            foreach ($this->months as $m) {
                $newRow[] = $row->{$m . '_2w_km'} ?? '';
                $newRow[] = $row->{$m . '_4w_km'} ?? '';
            }

            $output->push($newRow);
        }

        return $output;
    }

    public function headings(): array
    {
        $main = ['Emp Code', 'Employee Name', 'Status'];
        $sub = ['', '', ''];

        foreach (['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'] as $m) {
            $main[] = $m;
            $main[] = ''; // For merge
            $sub[] = '2W';
            $sub[] = '4W';
        }

        return [$main, $sub];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Merge month headers
                $startCol = 4; // D column index
                foreach (range(1, 12) as $i) {
                    $col1 = $startCol;
                    $col2 = $startCol + 1;
                    $event->sheet->mergeCells([$col1, 1, $col2, 1]);
                    $startCol += 2;
                }

                // Style headers bold center
                $event->sheet->getStyle('A1:Z2')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);
            }
        ];
    }
}
