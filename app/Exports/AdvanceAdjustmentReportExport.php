<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class AdvanceAdjustmentReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    protected $records;
    protected $filters;
    protected $kpis;

    protected $monthNames = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];

    public function __construct($records, array $kpis = [], array $filters = [])
    {
        $this->records = collect($records);
        $this->kpis = $kpis;
        $this->filters = $filters;
    }

    public function collection(): Enumerable
    {
        return $this->records;
    }

    public function headings(): array
    {
        return [
            'S.No',
            'Req ID',
            'Act ID',
            'Financial Year',
            'Request Month',
            'Request Date',
            'Emp Code',
            'Employee Name',
            'Department',
            'Function',
            'Headquarter',
            'Advance Type',
            'Activity Type',
            'Purpose / Remarks',
            'Requested Amount (₹)',
            'Approved Amount (₹)',
            'Paid Amount (₹)',
            'Payment Date',
            'Payment Status',
            'UTR Number',
            'Adjusted Amount (₹)',
            'Unadjusted Balance (₹)',
            'Adjustment Status',
            'Adjusted Against Month',
            'Adjustment Date',
            'Adjustment Remarks',
            'Age (Days)',
            'Over 2 Months Alert'
        ];
    }

    public function map(mixed $row): array
    {
        static $index = 0;
        $index++;

        $reqAmt  = (float) ($row->requested_amount ?? 0);
        $appAmt  = (float) ($row->finance_approved_amount ?? $reqAmt);
        $paidAmt = (float) ($row->computed_paid_amount ?? ($row->payment_amount ?: $reqAmt));
        $adjAmt  = (float) ($row->computed_adjusted_amount ?? ($row->adjustment_amount ?? 0));
        $remBal  = (float) ($row->computed_remaining_balance ?? max(0.0, $paidAmt - $adjAmt));
        $adjStatus = $row->adjustment_status_label ?? 'Pending Payment';

        $paymentStatus = trim($row->payment_status ?? 'Pending');
        $ageDays = $row->age_days ?? ($pDate ? (int) floor(\Carbon\Carbon::parse($pDate)->diffInDays(now())) : 0);
        $isOver2Months = isset($row->is_over_2_months) ? $row->is_over_2_months : ($ageDays > 60 && $remBal > 0 && strtolower($paymentStatus) === 'paid');

        $reqMonthStr = !empty($row->request_month) ? ($this->monthNames[(int)$row->request_month] ?? $row->request_month) : '-';
        $adjMonthStr = !empty($row->adjusted_against_month) ? ($this->monthNames[(int)$row->adjusted_against_month] ?? $row->adjusted_against_month) : '-';

        return [
            $index,
            $row->request_id,
            $row->activity_id,
            $row->financial_year ?: '-',
            $reqMonthStr,
            !empty($row->requested_date) ? \Carbon\Carbon::parse($row->requested_date)->format('d M Y') : '-',
            $row->emp_code,
            $row->emp_name,
            $row->department_name ?: '-',
            $row->function_name ?: '-',
            $row->hq_name ?: '-',
            $row->advance_type ?: ($row->is_imprest ? 'Imprest' : 'Activity Advance'),
            $row->activity_type ?: 'Advance',
            $row->purpose ?: '-',
            $reqAmt,
            $appAmt,
            $paidAmt,
            !empty($row->payment_date) ? \Carbon\Carbon::parse($row->payment_date)->format('d M Y') : '-',
            $paymentStatus,
            $row->utr_number ?: '-',
            $adjAmt,
            $remBal,
            $adjStatus,
            $adjMonthStr,
            !empty($row->adjustment_date) ? \Carbon\Carbon::parse($row->adjustment_date)->format('d M Y') : '-',
            $row->adjustment_remarks ?: '-',
            $ageDays > 0 ? $ageDays : '-',
            $isOver2Months ? 'YES (>60 Days)' : 'No'
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '00175F'] // Theme primary dark blue
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $this->records->count() + 1; // 1 for header

                // Set row height for header
                $sheet->getRowDimension(1)->setRowHeight(28);

                // Auto format amount columns: O (15), P (16), Q (17), U (21), V (22)
                $amountCols = ['O', 'P', 'Q', 'U', 'V'];
                foreach ($amountCols as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$rowCount}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                    $sheet->getStyle("{$col}2:{$col}{$rowCount}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Align center columns: A, B, C, D, E, F, G, R, S, T, W, X, Y, AA, AB
                $centerCols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'R', 'S', 'T', 'W', 'X', 'Y', 'AA', 'AB'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$rowCount}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Append Totals row if rows exist
                if ($this->records->count() > 0) {
                    $totalRow = $rowCount + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->mergeCells("A{$totalRow}:N{$totalRow}");

                    $sheet->setCellValue("O{$totalRow}", "=SUM(O2:O{$rowCount})");
                    $sheet->setCellValue("P{$totalRow}", "=SUM(P2:P{$rowCount})");
                    $sheet->setCellValue("Q{$totalRow}", "=SUM(Q2:Q{$rowCount})");
                    $sheet->setCellValue("U{$totalRow}", "=SUM(U2:U{$rowCount})");
                    $sheet->setCellValue("V{$totalRow}", "=SUM(V2:V{$rowCount})");

                    $sheet->getStyle("A{$totalRow}:AB{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E2E8F0']
                        ],
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_THIN],
                            'bottom' => ['borderStyle' => Border::BORDER_DOUBLE]
                        ]
                    ]);

                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    foreach ($amountCols as $col) {
                        $sheet->getStyle("{$col}{$totalRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                        $sheet->getStyle("{$col}{$totalRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                // Apply borders across all cells
                $finalRow = $this->records->count() > 0 ? $rowCount + 1 : $rowCount;
                $sheet->getStyle("A1:AB{$finalRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1']
                        ]
                    ]
                ]);

                // Freeze top row
                $sheet->freezePane('A2');
            }
        ];
    }
}
