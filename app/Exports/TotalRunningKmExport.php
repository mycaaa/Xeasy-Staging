<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Exports\Concerns\WithGeneratedTimestamp;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Enumerable;

class TotalRunningKmExport implements FromCollection, WithEvents, WithTitle, Responsable
{
    use Exportable, WithGeneratedTimestamp;

    protected $data;
    protected $years;
    protected $filename = 'Total_Running_KM_All_Years.xlsx';

    public function __construct(Collection $data, array $years)
    {
        $this->data  = $data;
        $this->years = $years; // sorted array of year labels e.g. ['2022-23', '2023-24', ...]
    }

    public function title(): string
    {
        return 'Total Running KM';
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $curRow  = 1;

                // ── Base columns ──────────────────────────────────────────────
                $baseHeaders = [
                    'S.No.',
                    'Employee Code',
                    'Employee Name',
                    'Status',
                    'Function',
                    'Vertical',
                    'Department',
                    'Sub-Department',
                    'Grade',
                    'Vehicle Policy',
                ];
                $baseCount = count($baseHeaders);

                // ── Year columns: each year gets 3 cols (2W, 4W, Total) ───────
                $yearCount   = count($this->years);
                $colsPerYear = 3; // 2W KM | 4W KM | Total KM

                // ── Grand total columns ───────────────────────────────────────
                $grandCols = 3; // Grand 2W | Grand 4W | Grand Total

                $totalCols = $baseCount + ($yearCount * $colsPerYear) + $grandCols;
                $lastCol   = $this->colLetter($totalCols);

                // ── Row 1: Title ──────────────────────────────────────────────
                $sheet->setCellValue("A{$curRow}", 'Employee-Wise Total Running KM Report (All Years)');
                $sheet->mergeCells("A{$curRow}:{$lastCol}{$curRow}");
                $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($curRow)->setRowHeight(22);
                $curRow++;

                // ── Row 2: Main headers ───────────────────────────────────────
                $col = 1;
                foreach ($baseHeaders as $h) {
                    $sheet->setCellValue([$col, $curRow], $h);
                    $col++;
                }

                foreach ($this->years as $yr) {
                    $startCol = $col;
                    $endCol   = $col + $colsPerYear - 1;
                    $sheet->setCellValue([$startCol, $curRow], $yr);
                    $sheet->mergeCells([$startCol, $curRow, $endCol, $curRow]);
                    $col = $endCol + 1;
                }

                // Grand Total header
                $sheet->setCellValue([$col, $curRow], 'Grand Total');
                $sheet->mergeCells([$col, $curRow, $col + $grandCols - 1, $curRow]);

                $this->styleHeader($sheet, "A{$curRow}:{$lastCol}{$curRow}", '1F4E78');
                $curRow++;

                // ── Row 3: Sub-headers ────────────────────────────────────────
                $col = 1;
                for ($i = 0; $i < $baseCount; $i++) {
                    $sheet->setCellValue([$col, $curRow], '');
                    $col++;
                }
                foreach ($this->years as $yr) {
                    $sheet->setCellValue([$col, $curRow], '2W KM');
                    $sheet->setCellValue([$col + 1, $curRow], '4W KM');
                    $sheet->setCellValue([$col + 2, $curRow], 'Total KM');
                    $col += $colsPerYear;
                }
                $sheet->setCellValue([$col, $curRow], '2W KM');
                $sheet->setCellValue([$col + 1, $curRow], '4W KM');
                $sheet->setCellValue([$col + 2, $curRow], 'Total KM');

                $this->styleHeader($sheet, "A{$curRow}:{$lastCol}{$curRow}", '2E5C8A');
                $curRow++;

                // ── Data rows ─────────────────────────────────────────────────
                $idx          = 1;
                $grandTotal2W = 0;
                $grandTotal4W = 0;

                // Column-level totals per year
                $yearTotals2W = array_fill(0, $yearCount, 0);
                $yearTotals4W = array_fill(0, $yearCount, 0);

                foreach ($this->data as $row) {
                    $empStatus = getStsUser($row->EmployeeID ?? $row->EmployeeId ?? null);
                    $isDeactivated = in_array($empStatus, ['D', 'R']);
                    $rowBg = $isDeactivated ? 'FFFFC7CE' : ($idx % 2 === 0 ? 'FFE7E6E6' : 'FFFFFFFF');

                    $col = 1;
                    $sheet->setCellValue([$col++, $curRow], $idx);
                    $sheet->setCellValue([$col++, $curRow], \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code ?? ''));
                    $sheet->setCellValue([$col++, $curRow], $row->employee_name ?? '');
                    $sheet->setCellValue([$col++, $curRow], $empStatus);
                    $sheet->setCellValue([$col++, $curRow], $row->function_name ?? '');
                    $sheet->setCellValue([$col++, $curRow], $row->vertical_name ?? '');
                    $sheet->setCellValue([$col++, $curRow], $row->department_name ?? '');
                    $sheet->setCellValue([$col++, $curRow], $row->sub_department_name ?? '');
                    $sheet->setCellValue([$col++, $curRow], $row->grade_name ?? '');
                    $sheet->setCellValue([$col++, $curRow], $row->policy_name ?? '');

                    $row2W = 0;
                    $row4W = 0;

                    foreach ($this->years as $yi => $yr) {
                        $km2w = (float) ($row->{'km_2w_' . $yi} ?? 0);
                        $km4w = (float) ($row->{'km_4w_' . $yi} ?? 0);
                        $tot  = $km2w + $km4w;

                        $sheet->setCellValue([$col, $curRow], $km2w ?: '');
                        $sheet->setCellValue([$col + 1, $curRow], $km4w ?: '');
                        $sheet->setCellValue([$col + 2, $curRow], $tot  ?: '');
                        $col += $colsPerYear;

                        $row2W += $km2w;
                        $row4W += $km4w;
                        $yearTotals2W[$yi] += $km2w;
                        $yearTotals4W[$yi] += $km4w;
                    }

                    $sheet->setCellValue([$col, $curRow], $row2W ?: '');
                    $sheet->setCellValue([$col + 1, $curRow], $row4W ?: '');
                    $sheet->setCellValue([$col + 2, $curRow], ($row2W + $row4W) ?: '');

                    $grandTotal2W += $row2W;
                    $grandTotal4W += $row4W;

                    // Row style
                    $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowBg]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    // Center base cols
                    foreach ([1, 2, 4] as $c) {
                        $sheet->getStyle([$c, $curRow])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                    // Right-align numeric cols
                    for ($c = $baseCount + 1; $c <= $totalCols; $c++) {
                        $sheet->getStyle([$c, $curRow])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    // Bold grand total cols
                    $gtStartCol = $this->colLetter($baseCount + $yearCount * $colsPerYear + 1);
                    $sheet->getStyle("{$gtStartCol}{$curRow}:{$lastCol}{$curRow}")->getFont()->setBold(true);

                    $curRow++;
                    $idx++;
                }

                // ── Grand Total row ───────────────────────────────────────────
                $sheet->setCellValue("A{$curRow}", 'Grand Total');
                $sheet->mergeCells([1, $curRow, $baseCount, $curRow]);

                $col = $baseCount + 1;
                foreach ($this->years as $yi => $yr) {
                    $tot = $yearTotals2W[$yi] + $yearTotals4W[$yi];
                    $sheet->setCellValue([$col, $curRow], $yearTotals2W[$yi]);
                    $sheet->setCellValue([$col + 1, $curRow], $yearTotals4W[$yi]);
                    $sheet->setCellValue([$col + 2, $curRow], $tot);
                    $col += $colsPerYear;
                }
                $sheet->setCellValue([$col, $curRow], $grandTotal2W);
                $sheet->setCellValue([$col + 1, $curRow], $grandTotal4W);
                $sheet->setCellValue([$col + 2, $curRow], $grandTotal2W + $grandTotal4W);

                $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // ── Auto-size all columns ─────────────────────────────────────
                for ($c = 1; $c <= $totalCols; $c++) {
                    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
                }
                $sheet->calculateColumnWidths();

                // REQ-004: Append generation timestamp after last row
                $stampRow = $curRow + 1;
                $genBy    = session('emp_name', auth()->user()?->name ?? 'System');
                $sheet->setCellValue("A{$stampRow}", "Generated on: " . now()->format('d-m-Y H:i:s') . "  |  By: {$genBy}");
                $sheet->mergeCells("A{$stampRow}:{$lastCol}{$stampRow}");
                $sheet->getStyle("A{$stampRow}")->applyFromArray([
                    'font'      => ['italic' => true, 'size' => 8, 'color' => ['argb' => 'FF6B7280']],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                ]);
            },
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function styleHeader($sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $rem    = ($n - 1) % 26;
            $letter = chr($rem + 65) . $letter;
            $n      = (int)(($n - $rem - 1) / 26);
        }
        return $letter;
    }
}
