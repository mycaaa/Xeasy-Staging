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

class ClaimWiseTATExport implements FromArray, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    protected array $filters;
    protected array $data;
    protected string $company;
    protected bool $isVVNR;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $this->isVVNR = session('company_id') == 11;
        $this->company = $this->isVVNR ? 'VVNR' : 'VNR Seeds Pvt Ltd';
        
        $service = new ClaimStageReportService();
        $this->data = $service->getClaimWiseTATAnalysis($filters);
    }

    public function title(): string
    {
        return 'Claim-wise TAT';
    }

    public function array(): array
    {
        $result = [];
        $result[] = ["CLAIM-WISE TAT ANALYSIS - {$this->company}", '', '', '', '', '', '', '', '', '', ''];
        $result[] = ["Generated: " . now()->format('d-m-Y h:i A'), '', '', '', '', '', '', '', '', '', ''];
        $result[] = ['', '', '', '', '', '', '', '', '', '', '']; // blank row

        if ($this->isVVNR) {
            $result[] = [
                'Claim Type', 'Total Claims', 'Avg Claim Amount', 'Total Amount',
                'Submit→Approve', 'Approve→Pay', 'Pay→Courier', 'Courier→Receive', 'Receive→Verify', 'Total TAT'
            ];
        } else {
            $result[] = [
                'Claim Type', 'Total Claims', 'Avg Claim Amount', 'Total Amount',
                'Submit→Courier', 'Post→Receive', 'Receive→Verify', 'Verify→Approve', 'Approve→Pay', 'Total TAT'
            ];
        }

        foreach ($this->data as $row) {
            // Convert object to array if needed
            $rowData = is_object($row) ? (array) $row : $row;

            if ($this->isVVNR) {
                $result[] = [
                    $rowData['claim_type'] ?? '-',
                    $rowData['total_claims'] ?? 0,
                    (int) ($rowData['avg_claim_amount'] ?? 0),
                    (int) ($rowData['total_amount'] ?? 0),
                    $rowData['submit_to_approve'] ?? 0,
                    $rowData['approve_to_pay'] ?? 0,
                    $rowData['pay_to_courier'] ?? 0,
                    $rowData['courier_to_receive'] ?? 0,
                    $rowData['receive_to_verify'] ?? 0,
                    $rowData['total_tat'] ?? 0,
                ];
            } else {
                $result[] = [
                    $rowData['claim_type'] ?? '-',
                    $rowData['total_claims'] ?? 0,
                    (int) ($rowData['avg_claim_amount'] ?? 0),
                    (int) ($rowData['total_amount'] ?? 0),
                    $rowData['submit_to_courier'] ?? 0,
                    $rowData['post_to_receive'] ?? 0,
                    $rowData['receive_to_verify'] ?? 0,
                    $rowData['verify_to_approve'] ?? 0,
                    $rowData['approve_to_pay'] ?? 0,
                    $rowData['courier_receipt_to_pay'] ?? 0,
                ];
            }
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
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Color-code TAT values
                $tatColStart = 'E'; // TAT columns start from column E
                $tatColEnd = $lastCol;

                for ($row = 5; $row <= $lastRow; $row++) {
                    for ($col = $tatColStart; $col <= $tatColEnd; $col++) {
                        $val = (int) $sheet->getCell("{$col}{$row}")->getValue();
                        if ($val > 7) {
                            $sheet->getStyle("{$col}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FF4444');
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB('FFFFFF');
                        } elseif ($val > 3) {
                            $sheet->getStyle("{$col}{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FF9800');
                            $sheet->getStyle("{$col}{$row}")->getFont()->getColor()->setRGB('FFFFFF');
                        } elseif ($val > 0) {
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
