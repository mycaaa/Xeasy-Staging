<?php

namespace App\Exports\Reports;

use App\Services\Reports\ClaimStageReportService;
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

class TATAnalysisExport implements FromArray, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    protected array $filters;
    protected array $data;
    protected string $company;
    protected string $functionName;
    protected string $departmentName;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $service = new ClaimStageReportService();
        $this->data = $service->getTATAnalysis($filters);
        $this->company = session('company_id') == 11 ? 'VVNR' : 'VNR Seeds Pvt Ltd';

        $this->functionName = 'All Functions';
        $this->departmentName = 'All Departments';
        if (!empty($filters['function_id'])) {
            $fn = \App\Models\CoreFunctions::find($filters['function_id']);
            $this->functionName = $fn->function_name ?? 'All Functions';
        }
        if (!empty($filters['department_id'])) {
            $dept = \App\Models\CoreDepartments::find($filters['department_id']);
            $this->departmentName = $dept->department_name ?? 'All Departments';
        }
    }

    public function title(): string
    {
        return 'TAT Analysis';
    }

    public function array(): array
    {
        $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        // Header rows
        $result = [];
        $result[] = ["TAT - MONTHLY ANALYSIS - {$this->company}", '', '', '', '', ''];
        $result[] = ["Function: {$this->functionName} | Department: {$this->departmentName}", '', '', '', '', ''];
        $result[] = ["Generated: " . now()->format('d-m-Y h:i A'), '', '', '', '', ''];
        $result[] = ['', '', '', '', '', '']; // blank row

        // Column headers
        $result[] = [
            'Month',
            'Submit to Courier posting (days)',
            'Post to receive (days)',
            'Receive to verify (days)',
            'Verify to approve (days)',
            'Approve to pay (days)',
            'Courier Receipt to pay (days)',
        ];

        // Data
        foreach ($this->data as $month => $row) {
            $result[] = [
                $monthNames[$month] ?? "Month {$month}",
                $row['submit_to_courier'] ?? '-',
                $row['post_to_receive'] ?? '-',
                $row['receive_to_verify'] ?? '-',
                $row['verify_to_approve'] ?? '-',
                $row['approve_to_pay'] ?? '-',
                $row['courier_receipt_to_pay'] ?? '-',
            ];
        }

        return $result;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '15648C']]],
            2 => ['font' => ['size' => 10, 'color' => ['rgb' => '555555']]],
            3 => ['font' => ['size' => 9, 'color' => ['rgb' => '888888']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
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
                $sheet->mergeCells("A3:{$lastCol}3");

                // Borders on data
                $sheet->getStyle("A5:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Color-code TAT values (bright colors)
                for ($row = 6; $row <= $lastRow; $row++) {
                    for ($col = 'B'; $col <= $lastCol; $col++) {
                        $val = (int) $sheet->getCell("{$col}{$row}")->getValue();
                        if ($val > 7) {
                            // Bright red for SLA breach
                            $sheet->getStyle("{$col}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FF4444');
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB('FFFFFF');
                        } elseif ($val > 3) {
                            // Bright orange/yellow for warning
                            $sheet->getStyle("{$col}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FF9800');
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB('FFFFFF');
                        } elseif ($val > 0) {
                            // Bright green for good
                            $sheet->getStyle("{$col}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('4CAF50');
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB('FFFFFF');
                        }
                    }
                }
            },
        ];
    }
}
