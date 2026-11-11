<?php
namespace App\Exports;

use App\Helpers\CommonHelper;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Enumerable;

class AccessRunningKmExport implements FromCollection, WithEvents, WithTitle, Responsable
{
    use Exportable;

    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Excess Running KM';
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function ($event) {
                $sheet = $event->sheet->getDelegate();
                $curRow = 1;
                $headers = ['S No.', 'Month', 'Employee Code', 'Employee Name', 'Vertical', 'Vehicle Type', 'Total KM', 'KM Limit', 'Mobile No', 'Reporting Name', 'Reporting Contact', 'Status', 'ODO Approve Final Submit', 'HOD Name'];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$curRow}", $header);
                    $col++;
                }
                $this->headerStyle($sheet, "A{$curRow}:N{$curRow}");
                $curRow++;
                $index = 1;
                $sortedData = $this->data->sortBy(['EmpCode', 'Month']);
                $groupedData = $sortedData->groupBy('EmpCode');
                foreach ($groupedData as $empData) {
                    foreach ($empData->sortBy('Month') as $row) {
                        $rowColor = $index % 2 === 0 ? 'FFE7E6E6' : 'FFFFFFFF';
                        $monthName = \Carbon\Carbon::create(null, $row->Month, 1)->format('F');
                        $workType = $row->WType == 2 ? '2 Wheeler' : ($row->WType == 4 ? '4 Wheeler' : $row->WType);
                        $rowData = [
                            $index,
                            $monthName,
                            \App\Helpers\CommonHelper::formatEmployeeCode($row->EmpCode ?? ''),
                            $row->EmployeeName ?? '',
                            $row->vertical_name ?? '',
                            $workType,
                            $row->TotalKm ?? 0,
                            $row->monthly_km_limit ?? 0,
                            $row->MobileNo_Vnr ?? '',
                            $row->ReportingName ?? '',
                            $row->ReportingContactNo ?? '',
                            $row->Status ?? '',
                            $row->Odo_Approve_Final_Submit ?? '',
                            $row->HodName ?? '',
                        ];
                        $col = 'A';
                        foreach ($rowData as $value) {
                            $sheet->setCellValue("{$col}{$curRow}", $value);
                            $col++;
                        }
                        foreach (['A', 'B', 'C', 'G', 'H', 'L', 'M'] as $c) {
                            $sheet->getStyle("{$c}{$curRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                        $sheet->getStyle("A{$curRow}:N{$curRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowColor]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        ]);
                        $curRow++;
                        $index++;
                    }
                }
                foreach (range('A', 'N') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
                $sheet->calculateColumnWidths();
            },
        ];
    }

    private function headerStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);
    }
}
