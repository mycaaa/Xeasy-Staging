<?php

namespace App\Exports;

use App\Helpers\CommonHelper;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
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
use Maatwebsite\Excel\Concerns\Export;

class EmployeeClaimTypeWiseExpenseExport implements WithMultipleSheets, Export, Responsable
{
    use Exportable;

    protected $employeeData;
    protected $expenseData;
    protected $activeMonths = [];
    protected $selectedColumn;

    public function __construct(Collection $employeeData, Collection $expenseData, $selectedColumn = 'all')
    {
        $this->employeeData   = $employeeData->unique('EmployeeId')->values();
        $this->expenseData    = $expenseData;
        $this->selectedColumn = $selectedColumn;

        $this->activeMonths = $expenseData
            ->pluck('ClaimMonth')
            ->unique()
            ->sort()
            ->mapWithKeys(fn($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])
            ->toArray();
    }

    public function sheets(): array
    {
        return [
            new EmployeeClaimTypeSheet(
                'Employee Claim Type Wise',
                $this->employeeData,
                $this->expenseData,
                $this->activeMonths,
                $this->selectedColumn
            ),
        ];
    }
}

class EmployeeClaimTypeSheet implements FromCollection, WithEvents, WithTitle
{
    protected $sheetTitle;
    protected $employeeData;
    protected $expenseData;
    protected $activeMonths;
    protected $selectedColumn;
    protected $columnConfig;

    // Base info columns before claim-type columns
    const BASE_COLS = ['S No.', 'Emp Code', 'Employee Name', 'Status', 'Function', 'Vertical', 'Department', 'Sub Dept', 'Grade'];

    public function __construct(string $sheetTitle, Collection $employeeData, Collection $expenseData, array $activeMonths, $selectedColumn = 'all')
    {
        $this->sheetTitle     = $sheetTitle;
        $this->employeeData   = $employeeData;
        $this->expenseData    = $expenseData;
        $this->activeMonths   = $activeMonths;
        $this->selectedColumn = $selectedColumn;

        $this->columnConfig = [
            'Filled'   => ['field' => 'FilledTAmt',   'label' => 'Filled'],
            'Verified' => ['field' => 'VerifyTAmt',   'label' => 'Verified'],
            'Approved' => ['field' => 'ApprTAmt',     'label' => 'Approved'],
            'Financed' => ['field' => 'FinancedTAmt', 'label' => 'Financed'],
        ];
    }

    public function title(): string { return $this->sheetTitle; }

    public function collection(): Enumerable { return collect([]); }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {
                $this->render($event->sheet->getDelegate());
            },
        ];
    }

    private function getColumnsToShow(): array
    {
        $col = is_array($this->selectedColumn)
            ? (!empty($this->selectedColumn) ? $this->selectedColumn[0] : 'all')
            : $this->selectedColumn;

        if ($col === 'all' || !isset($this->columnConfig[$col])) {
            return $this->columnConfig;
        }
        return [$col => $this->columnConfig[$col]];
    }

    private function render($sheet): void
    {
        $columnsToShow = $this->getColumnsToShow();
        $baseCols      = count(self::BASE_COLS);

        // Derive claim types from expense data
        $claimTypes = $this->expenseData
            ->pluck('ClaimTypeName')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Total columns = base + (claimTypes × stageColumns) + grand-total stageColumns
        $claimColCount  = count($claimTypes) * count($columnsToShow);
        $totalColCount  = count($columnsToShow);
        $allColCount    = $baseCols + $claimColCount + $totalColCount;
        $lastCol        = $this->col($allColCount);

        $curRow = 1;

        /* ── HEADER ROW 1: base labels + claim-type groups + Total ── */
        foreach (self::BASE_COLS as $i => $label) {
            $c = $this->col($i + 1);
            $sheet->setCellValue("{$c}{$curRow}", $label);
            $sheet->mergeCells("{$c}{$curRow}:{$c}" . ($curRow + 1)); // merge vertically
        }

        $colNum = $baseCols + 1;
        foreach ($claimTypes as $type) {
            $startColNum = $colNum;
            $endColNum   = $colNum + count($columnsToShow) - 1;
            $startC      = $this->col($startColNum);
            $endC        = $this->col($endColNum);
            $sheet->setCellValue("{$startC}{$curRow}", $type);
            if ($startC !== $endC) {
                $sheet->mergeCells("{$startC}{$curRow}:{$endC}{$curRow}");
            }
            $colNum = $endColNum + 1;
        }

        // Grand Total header
        $totalStartColNum = $colNum;
        $totalEndColNum   = $colNum + count($columnsToShow) - 1;
        $totalStartC      = $this->col($totalStartColNum);
        $totalEndC        = $this->col($totalEndColNum);
        $sheet->setCellValue("{$totalStartC}{$curRow}", 'Grand Total');
        if ($totalStartC !== $totalEndC) {
            $sheet->mergeCells("{$totalStartC}{$curRow}:{$totalEndC}{$curRow}");
        }

        $this->styleHeader($sheet, "A{$curRow}:{$lastCol}{$curRow}");
        $curRow++;

        /* ── HEADER ROW 2: stage sub-labels ── */
        $colNum = $baseCols + 1;
        foreach ($claimTypes as $type) {
            foreach ($columnsToShow as $colConfig) {
                $sheet->setCellValue($this->col($colNum) . $curRow, $colConfig['label']);
                $colNum++;
            }
        }
        foreach ($columnsToShow as $colConfig) {
            $sheet->setCellValue($this->col($colNum) . $curRow, $colConfig['label']);
            $colNum++;
        }
        $this->styleSubHeader($sheet, $this->col($baseCols + 1) . "{$curRow}:{$lastCol}{$curRow}");
        $curRow++;

        /* ── DATA ROWS ── */
        $expByEmp = $this->expenseData->groupBy('CrBy');

        // Column grand totals
        $colGrandTotals = [];
        foreach ($claimTypes as $type) {
            foreach (array_keys($columnsToShow) as $key) {
                $colGrandTotals[$type][$key] = 0;
            }
        }
        $overallTotals = array_fill_keys(array_keys($columnsToShow), 0);

        $idx = 1;
        foreach ($this->employeeData as $emp) {
            $empId      = $emp->EmployeeId;
            $empExpense = $expByEmp->get($empId, collect());

            $empStatus = getStsUser($empId);
            $isDeactivated = in_array($empStatus, ['D', 'R']);

            $empRowColor   = $isDeactivated ? 'FFFFC7CE' : ($idx % 2 === 0 ? 'FFD9E1F2' : 'FFD9E1F2');
            $monthRowColor = $isDeactivated ? 'FFFFC7CE' : ($idx % 2 === 0 ? 'FFE7E6E6' : 'FFFFFFFF');

            // ── Employee summary row ──
            $empSummaryRow = $curRow;
            $baseData = [
                $idx,
                \App\Helpers\CommonHelper::formatEmployeeCode($emp->employee_code ?? ''),
                $emp->employee_name       ?? '',
                $empStatus,
                $emp->function_name       ?? '',
                $emp->vertical_name       ?? '',
                $emp->department_name     ?? '',
                $emp->sub_department_name ?? '',
                $emp->grade_name          ?? '',
            ];
            foreach ($baseData as $i => $val) {
                $sheet->setCellValue($this->col($i + 1) . $curRow, $val);
            }

            $colNum   = $baseCols + 1;
            $rowTotal = array_fill_keys(array_keys($columnsToShow), 0);

            foreach ($claimTypes as $type) {
                $typeExpenses = $empExpense->where('ClaimTypeName', $type);
                foreach ($columnsToShow as $key => $colConfig) {
                    $amt = $typeExpenses->sum($colConfig['field']);
                    $sheet->setCellValue($this->col($colNum) . $curRow, $this->fmt($amt));
                    $rowTotal[$key]              += $amt;
                    $colGrandTotals[$type][$key] += $amt;
                    $colNum++;
                }
            }
            foreach ($columnsToShow as $key => $colConfig) {
                $sheet->setCellValue($this->col($colNum) . $curRow, $this->fmt($rowTotal[$key]));
                $overallTotals[$key] += $rowTotal[$key];
                $colNum++;
            }

            $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                'font'    => ['bold' => true],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $empRowColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet->getStyle($totalStartC . $curRow . ':' . $lastCol . $curRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $isDeactivated ? 'FFFFC7CE' : 'FFFDE9D9']],
            ]);
            $curRow++;

            // ── Month breakdown rows ──
            // Get months that have data for this employee, sorted Apr-Mar (financial year order)
            $fyOrder = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
            $empMonths = $empExpense
                ->pluck('ClaimMonth')
                ->unique()
                ->sortBy(fn($m) => array_search((int)$m, $fyOrder))
                ->values()
                ->toArray();

            foreach ($empMonths as $month) {
                $monthName = \Carbon\Carbon::create(null, $month, 1)->format('M');

                // Leave S.No, Emp Code, Employee Name, Status, Function, Vertical, Department, Sub Dept blank
                // Put month name in Grade column (col 9 = index 8)
                for ($i = 0; $i < $baseCols; $i++) {
                    $val = '';
                    if ($i === 8) { // Grade column → show month name
                        $val = $monthName;
                    }
                    $sheet->setCellValue($this->col($i + 1) . $curRow, $val);
                }

                $colNum        = $baseCols + 1;
                $monthRowTotal = array_fill_keys(array_keys($columnsToShow), 0);

                foreach ($claimTypes as $type) {
                    $monthTypeExpenses = $empExpense
                        ->where('ClaimTypeName', $type)
                        ->where('ClaimMonth', $month);
                    foreach ($columnsToShow as $key => $colConfig) {
                        $amt = $monthTypeExpenses->sum($colConfig['field']);
                        $sheet->setCellValue($this->col($colNum) . $curRow, $this->fmt($amt));
                        $monthRowTotal[$key] += $amt;
                        $colNum++;
                    }
                }
                foreach ($columnsToShow as $key => $colConfig) {
                    $sheet->setCellValue($this->col($colNum) . $curRow, $this->fmt($monthRowTotal[$key]));
                    $colNum++;
                }

                $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $monthRowColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                // Style month name cell to match S.No column color
                $sheet->getStyle($this->col($baseCols) . $curRow)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'font'      => ['bold' => false, 'italic' => false, 'color' => ['argb' => 'FF000000']],
                ]);

                $curRow++;
            }

            $idx++;
        }

        /* ── GRAND TOTAL ROW ── */
        $sheet->setCellValue("A{$curRow}", 'Grand Total');
        $sheet->mergeCells("A{$curRow}:" . $this->col($baseCols) . $curRow);

        $colNum = $baseCols + 1;
        foreach ($claimTypes as $type) {
            foreach ($columnsToShow as $key => $colConfig) {
                $sheet->setCellValue($this->col($colNum) . $curRow, $this->fmt($colGrandTotals[$type][$key]));
                $colNum++;
            }
        }
        foreach ($columnsToShow as $key => $colConfig) {
            $sheet->setCellValue($this->col($colNum) . $curRow, $this->fmt($overallTotals[$key]));
            $colNum++;
        }

        $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Auto-size
        for ($i = 1; $i <= $allColCount; $i++) {
            $sheet->getColumnDimension($this->col($i))->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function styleSubHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '2E5C8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function fmt($number): string
    {
        return $number && $number != 0 ? (new CommonHelper())->InrCurrencyFormat($number, 0) : '';
    }

    private function col(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $rem    = ($n - 1) % 26;
            $letter = chr($rem + 65) . $letter;
            $n      = intval(($n - $rem - 1) / 26);
        }
        return $letter;
    }
}
