<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Enumerable;

class SubmissionWiseExport implements FromCollection, WithEvents, WithTitle, Responsable
{
    use Exportable;

    protected $data;
    protected $months;
    protected $filename = "Submission_Wise_Report.xlsx";

    public function __construct(Collection $data)
    {
        $this->data = $data;

        $this->months = $data
            ->pluck('Month')
            ->unique()
            ->sort()
            ->values();
    }

    public function title(): string
    {
        return "Submission Wise Report";
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $currentRow = 1;

                $groupedByEmployee = $this->data->groupBy('EmployeeId');

                $baseColumns = 11;
                $monthColumns = $this->months->count();
                $totalColumns = $baseColumns + $monthColumns;
                $lastColumn = $this->getColumnLetter($totalColumns);

                $sheet->setCellValue("A{$currentRow}", "Month Wise Current Stage Identifier Report");
                $sheet->mergeCells("A{$currentRow}:{$lastColumn}{$currentRow}");
                $this->applyTitleStyle($sheet, "A{$currentRow}:{$lastColumn}{$currentRow}");
                $currentRow++;

                $headers = ["S No.", "Employee Code", "Employee Name", "Emp Status", "Joining Date", "Resignation Date", "Sepration Date", "Vertical", "Department", "Sub-Department", "Grade"];

                foreach ($this->months as $month) {
                    $headers[] = $this->monthName($month);
                }

                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$currentRow}", $header);
                    $col++;
                }
                $this->applyHeaderStyle($sheet, "A{$currentRow}:{$lastColumn}{$currentRow}");
                $currentRow++;

                $index = 1;

                foreach ($groupedByEmployee as $employeeRows) {
                    $firstRow = $employeeRows->first();
                    $empStatus = getStsUser($firstRow->EmployeeId ?? null);
                    $isDeactivated = in_array($empStatus, ['D', 'R']);
                    if ($isDeactivated) {
                        $rowColor = "FFF8D7DA";
                    } else {
                        $rowColor = $index % 2 === 0 ? "FFE7E6E6" : "FFFFFFFF";
                    }

                    $data = [
                        $index,
                        \App\Helpers\CommonHelper::formatEmployeeCode($firstRow->employee_code ?? ""),
                        $firstRow->employee_name ?? "",
                        $empStatus,
                        $this->formatDate($firstRow->DateJoining),
                        $this->formatDate($firstRow->DateOfResignation),
                        $this->formatDate($firstRow->DateOfSepration),
                        $firstRow->vertical_name ?? "",
                        $firstRow->department_name ?? "",
                        $firstRow->sub_department_name ?? "",
                        $firstRow->grade_name ?? "",
                    ];

                    foreach ($this->months as $month) {
                        $monthData = $employeeRows->firstWhere('Month', $month);
                        $data[] = $monthData ? $this->getStageAbbreviation($this->detectStage($monthData)) : "-";
                    }

                    $col = 'A';
                    $colIndex = 1;

                    foreach ($data as $value) {
                        $sheet->setCellValue("{$col}{$currentRow}", $value);

                        $centerColumns = [1, 2, 4, 5, 6, 7, 11];

                        if (in_array($colIndex, $centerColumns)) {
                            $sheet
                                ->getStyle("{$col}{$currentRow}")
                                ->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        } else {
                            $sheet
                                ->getStyle("{$col}{$currentRow}")
                                ->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        }

                        $col++;
                        $colIndex++;
                    }

                    $monthStartCol = $this->getColumnLetter(12);

                    $sheet
                        ->getStyle("{$monthStartCol}{$currentRow}:{$lastColumn}{$currentRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("A{$currentRow}:{$lastColumn}{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowColor]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    $currentRow++;
                    $index++;
                }

                for ($i = 1; $i <= $totalColumns; $i++) {
                    $sheet->getColumnDimension($this->getColumnLetter($i))->setAutoSize(true);
                }
            },
        ];
    }

    private function formatDate($date)
    {
        if (empty($date) || \App\Support\ZeroDate::none($date)) {
            return "";
        }
        return \Carbon\Carbon::parse($date)->format('d-m-Y');
    }

    private function applyTitleStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function applyHeaderStyle($sheet, $range)
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
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function detectStage($row): string
    {
        if (empty($row->DateOfSubmit) || \App\Support\ZeroDate::none($row->DateOfSubmit)) {
            return "Pending Final Submission";
        }
        if (empty($row->PostDate) || \App\Support\ZeroDate::none($row->PostDate)) {
            return "Submission Done - Courier Pending";
        }
        if (empty($row->RecevingDate) || \App\Support\ZeroDate::none($row->RecevingDate)) {
            return "Received - Verification Pending";
        }
        if (empty($row->Verified_Date) || \App\Support\ZeroDate::none($row->Verified_Date)) {
            return "Verified - Manager Approval Pending";
        }
        if (empty($row->Approved_Date) || \App\Support\ZeroDate::none($row->Approved_Date)) {
            return "Approved - Payment Pending";
        }
        if (empty($row->Finance_Date) || \App\Support\ZeroDate::none($row->Finance_Date)) {
            return "Finance Processing Pending";
        }
        if (empty($row->Fin_PayDate) || \App\Support\ZeroDate::none($row->Fin_PayDate)) {
            return "Payment Pending";
        }
        return "Paid";
    }

    private function getStageAbbreviation($stage): string
    {
        $abbreviations = [
            "Pending Final Submission" => "Pending Submission",
            "Submission Done - Courier Pending" => "Courier Pending",
            "Received - Verification Pending" => "Verification Pending",
            "Verified - Manager Approval Pending" => "Approval Pending",
            "Approved - Payment Pending" => "Payment Pending",
            "Finance Processing Pending" => "Finance Processing",
            "Payment Pending" => "Payment Pending",
            "Paid" => "Paid",
        ];
        return $abbreviations[$stage] ?? $stage;
    }

    private function monthName($m): string
    {
        return \DateTime::createFromFormat('!m', $m)->format('F');
    }

    private function getColumnLetter($columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $columnNumber = ($columnNumber - $temp - 1) / 26;
        }
        return $letter;
    }
}
