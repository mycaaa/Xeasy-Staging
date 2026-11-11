<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SubmissionReturnExport implements FromArray, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    protected array $data;
    protected string $company;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->company = session('company_id') == 11 ? 'VVNR' : 'VNR Seeds Pvt Ltd';
    }

    public function title(): string
    {
        return 'Submission Returns';
    }

    public function array(): array
    {
        $result = [];
        $result[] = ["SUBMISSION RETURN REPORT - {$this->company}", '', '', '', '', '', '', '', '', '', '', ''];
        $result[] = ["Generated: " . now()->format('d-m-Y h:i A'), '', '', '', '', '', '', '', '', '', '', ''];
        $result[] = ['', '', '', '', '', '', '', '', '', '', '', '']; // blank row

        $result[] = [
            '#', 'Employee Code', 'Employee Name', 'Emp Status', 'Department', 'Function',
            'Month', 'Claim Type',  'Return Status', 'Submission Date', 'Remarks'
        ];

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $idx = 1;

        foreach ($this->data as $row) {
            $rowData = is_object($row) ? (array) $row : $row;

            $month = isset($rowData['Month']) ? ($monthNames[$rowData['Month']] ?? '-') : '-';
            $submissionDate = '';
            if (isset($rowData['created_at'])) {
                $date = new \DateTime($rowData['created_at']);
                $submissionDate = $date->format('d-m-Y');
            }

            $result[] = [
                $idx,
                $rowData['emp_code'] ?? '-',
                $rowData['employee_name'] ?? '-',
                getEmployeeStatus(null, $rowData['EmployeeID'] ?? $rowData['employee_id'] ?? null),
                $rowData['department_name'] ?? '-',
                $rowData['function_name'] ?? '-',
                $month,
                $rowData['claim_type_name'] ?? '-',

                'Return Submitted',
                $submissionDate,
                $rowData['remarks'] ?? '-',
            ];
            $idx++;
        }

        return $result;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '15648C']]],
            2 => ['font' => ['size' => 9, 'color' => ['rgb' => '888888']]],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Merge header rows
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");

                // Borders and alignment on data
                $sheet->getStyle("A4:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                ]);

                // Right-align amount column
                $sheet->getStyle("H5:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
