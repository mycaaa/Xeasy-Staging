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

class MonthClaimTypeWiseExpenseExport implements WithMultipleSheets, Export, Responsable
{
    use Exportable;

    protected $employeeData;
    protected $expenseData;
    protected $activeMonths = [];
    protected $isSingleSheet;
    protected $selectedColumn;

    public function __construct(Collection $employeeData, Collection $expenseData, bool $isSingleSheet = true, $selectedColumn = 'all')
    {
        $this->employeeData = $employeeData;
        $this->expenseData  = $expenseData;
        $this->isSingleSheet = $isSingleSheet;
        $this->selectedColumn = $selectedColumn;

        $this->activeMonths = $this->expenseData
            ->pluck('ClaimMonth')
            ->unique()
            ->sort()
            ->mapWithKeys(fn($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])
            ->toArray();
    }

    public function sheets(): array
    {
        if ($this->isSingleSheet) {
            // Single sheet with all months
            return [
                new MonthClaimTypeSheet(
                    'Claim Type Wise Expense Report',
                    $this->expenseData,
                    $this->activeMonths,
                    true, // single sheet mode
                    $this->selectedColumn
                )
            ];
        } else {
            // Multiple sheets - one per month
            $sheets = [];
            foreach ($this->activeMonths as $month => $monthName) {
                $monthExpenseData = $this->expenseData->where('ClaimMonth', $month);
                $sheets[] = new MonthClaimTypeSheet(
                    $monthName,
                    $monthExpenseData,
                    [$month => $monthName],
                    false, // multiple sheet mode
                    $this->selectedColumn
                );
            }
            return $sheets;
        }
    }
}

class MonthClaimTypeSheet implements FromCollection, WithEvents, WithTitle
{
    protected $sheetTitle;
    protected $expenseData;
    protected $activeMonths;
    protected $isSingleSheet;
    protected $selectedColumn;
    protected $columnConfig;

    public function __construct(string $sheetTitle, Collection $expenseData, array $activeMonths, bool $isSingleSheet, $selectedColumn = 'all')
    {
        $this->sheetTitle = $sheetTitle;
        $this->expenseData = $expenseData;
        $this->activeMonths = $activeMonths;
        $this->isSingleSheet = $isSingleSheet;
        $this->selectedColumn = $selectedColumn;

        // Define column configurations
        if(session('company_id') ==11){
             $this->columnConfig = [
            'Filled' => ['field' => 'FilledTAmt', 'label' => 'Filled'],
            'Approved' => ['field' => 'ApprTAmt', 'label' => 'Approved'],
            // 'Financed' => ['field' => 'FinancedTAmt', 'label' => 'Financed'],
              'Verified' => ['field' => 'VerifyTAmt', 'label' => 'Verified'],
        ];
        }else{
  $this->columnConfig = [
            'Filled' => ['field' => 'FilledTAmt', 'label' => 'Filled'],
            'Verified' => ['field' => 'VerifyTAmt', 'label' => 'Verified'],
            'Approved' => ['field' => 'ApprTAmt', 'label' => 'Approved'],
            'Financed' => ['field' => 'FinancedTAmt', 'label' => 'Financed'],
        ];
        }

    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {
                $sheet = $event->sheet->getDelegate();
                $curRow = 1;

                $claimTypes = $this->expenseData->pluck('ClaimTypeName')->unique()->sort()->values()->toArray();

                if ($this->isSingleSheet) {
                    // Single sheet layout: Claim Type | Month1 | Month2 | ... | Total
                    $this->renderSingleSheetLayout($sheet, $claimTypes, $curRow);
                } else {
                    // Multiple sheet layout: Claim Type | Amount
                    $this->renderMultipleSheetLayout($sheet, $claimTypes, $curRow);
                }
            },
        ];
    }

    private function getColumnsToShow()
    {
        // Handle array input (from multi-select)
        $column = is_array($this->selectedColumn)
            ? (!empty($this->selectedColumn) ? $this->selectedColumn[0] : 'all')
            : $this->selectedColumn;

        // Normalize 'all' case
        if ($column === 'all') {
            return $this->columnConfig;
        }

        // Validate column exists
        if (!isset($this->columnConfig[$column])) {
            return $this->columnConfig; // Default to all if invalid
        }

        return [$column => $this->columnConfig[$column]];
    }

    private function renderSingleSheetLayout($sheet, $claimTypes, $curRow)
    {
        $columnsToShow = $this->getColumnsToShow();

        // Calculate totals grouped by claim type, month, and column
        $totals = [];
        foreach ($claimTypes as $type) {
            $totals[$type] = [];
            foreach ($this->activeMonths as $m => $name) {
                $totals[$type][$m] = [];
                foreach ($columnsToShow as $colKey => $colConfig) {
                    $totals[$type][$m][$colKey] = $this->expenseData
                        ->where('ClaimTypeName', $type)
                        ->where('ClaimMonth', $m)
                        ->sum($colConfig['field']);
                }
            }
            // Calculate row totals
            $totals[$type]['TOTAL'] = [];
            foreach ($columnsToShow as $colKey => $colConfig) {
                $totals[$type]['TOTAL'][$colKey] = 0;
                foreach ($this->activeMonths as $m => $name) {
                    $totals[$type]['TOTAL'][$colKey] += $totals[$type][$m][$colKey];
                }
            }
        }

        $colNum = 1; // track column as integer

        /* ---------- HEADER ROW 1 - Months ---------- */
        $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, 'Claim Type');
        $colNum++;

        $totalStartColNum = 0;
        foreach ($this->activeMonths as $m => $name) {
            $startColNum = $colNum;
            $endColNum   = $colNum + count($columnsToShow) - 1;
            $startCol    = $this->getColumnLetter($startColNum);
            $endCol      = $this->getColumnLetter($endColNum);
            $sheet->setCellValue("{$startCol}{$curRow}", $name);
            if ($startCol !== $endCol) {
                $sheet->mergeCells("{$startCol}{$curRow}:{$endCol}{$curRow}");
            }
            $colNum = $endColNum + 1;
        }

        // Total header
        $totalStartColNum = $colNum;
        $totalEndColNum   = $colNum + count($columnsToShow) - 1;
        $totalStartCol    = $this->getColumnLetter($totalStartColNum);
        $totalEndCol      = $this->getColumnLetter($totalEndColNum);
        $sheet->setCellValue("{$totalStartCol}{$curRow}", 'Total');
        if ($totalStartCol !== $totalEndCol) {
            $sheet->mergeCells("{$totalStartCol}{$curRow}:{$totalEndCol}{$curRow}");
        }
        $colNum = $totalEndColNum + 1;

        $lastCol = $this->getColumnLetter($totalEndColNum);

        // Merge Claim Type cell vertically
        $sheet->mergeCells("A{$curRow}:A" . ($curRow + 1));

        $this->headerStyle($sheet, "A{$curRow}:{$lastCol}{$curRow}");
        $curRow++;

        /* ---------- HEADER ROW 2 - Stage Names ---------- */
        $colNum = 2; // start after Claim Type column
        foreach ($this->activeMonths as $m => $name) {
            foreach ($columnsToShow as $colKey => $colConfig) {
                $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $colConfig['label']);
                $colNum++;
            }
        }
        // Total stage names
        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $colConfig['label']);
            $colNum++;
        }

        $this->subHeaderStyle($sheet, "B{$curRow}:{$lastCol}{$curRow}");
        $curRow++;

        /* ---------- DATA ROWS ---------- */
        $idx = 1;
        foreach ($claimTypes as $type) {
            $rowClr = $idx % 2 === 0 ? 'FFE7E6E6' : 'FFFFFFFF';

            $sheet->setCellValue("A{$curRow}", $type);
            $colNum = 2;

            foreach ($this->activeMonths as $m => $name) {
                foreach ($columnsToShow as $colKey => $colConfig) {
                    $amt = $totals[$type][$m][$colKey];
                    $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $this->fmt($amt));
                    $colNum++;
                }
            }

            // Total columns
            foreach ($columnsToShow as $colKey => $colConfig) {
                $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $this->fmt($totals[$type]['TOTAL'][$colKey]));
                $colNum++;
            }

            // Style the row
            $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowClr]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Bold total columns
            $sheet->getStyle("{$totalStartCol}{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFDE9D9']],
            ]);

            $curRow++;
            $idx++;
        }

        /* ---------- GRAND TOTAL ROW ---------- */
        $sheet->setCellValue("A{$curRow}", 'Grand Total');
        $colNum = 2;
        $grandTotals = [];

        foreach ($this->activeMonths as $m => $name) {
            foreach ($columnsToShow as $colKey => $colConfig) {
                $monthColTotal = 0;
                foreach ($claimTypes as $type) {
                    $monthColTotal += $totals[$type][$m][$colKey];
                }
                $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $this->fmt($monthColTotal));
                $grandTotals[$colKey] = ($grandTotals[$colKey] ?? 0) + $monthColTotal;
                $colNum++;
            }
        }

        // Grand total for each column
        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $this->fmt($grandTotals[$colKey] ?? 0));
            $colNum++;
        }

        $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Auto-size columns
        $totalCols = 1 + (count($this->activeMonths) * count($columnsToShow)) + count($columnsToShow);
        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimension($this->getColumnLetter($i))->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();
    }

    private function renderMultipleSheetLayout($sheet, $claimTypes, $curRow)
    {
        $columnsToShow = $this->getColumnsToShow();

        // Calculate totals for each claim type and column
        $totals = [];
        foreach ($claimTypes as $type) {
            $totals[$type] = [];
            foreach ($columnsToShow as $colKey => $colConfig) {
                $totals[$type][$colKey] = $this->expenseData
                    ->where('ClaimTypeName', $type)
                    ->sum($colConfig['field']);
            }
        }

        /* ---------- HEADER ROW ---------- */
        $sheet->setCellValue("A{$curRow}", 'Claim Type');
        $colNum = 2;
        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $colConfig['label']);
            $colNum++;
        }
        $lastCol = $this->getColumnLetter($colNum - 1);

        $this->headerStyle($sheet, "A{$curRow}:{$lastCol}{$curRow}");
        $curRow++;

        /* ---------- DATA ROWS ---------- */
        $idx = 1;
        foreach ($claimTypes as $type) {
            $rowClr = $idx % 2 === 0 ? 'FFE7E6E6' : 'FFFFFFFF';

            $sheet->setCellValue("A{$curRow}", $type);
            $colNum = 2;

            foreach ($columnsToShow as $colKey => $colConfig) {
                $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $this->fmt($totals[$type][$colKey]));
                $colNum++;
            }

            // Style the row
            $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowClr]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $curRow++;
            $idx++;
        }

        /* ---------- TOTAL ROW ---------- */
        $sheet->setCellValue("A{$curRow}", 'Total');
        $colNum = 2;

        foreach ($columnsToShow as $colKey => $colConfig) {
            $columnTotal = 0;
            foreach ($claimTypes as $type) {
                $columnTotal += $totals[$type][$colKey];
            }
            $sheet->setCellValue($this->getColumnLetter($colNum) . $curRow, $this->fmt($columnTotal));
            $colNum++;
        }

        $sheet->getStyle("A{$curRow}:{$lastCol}{$curRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Auto-size columns
        $totalCols = 1 + count($columnsToShow);
        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimension($this->getColumnLetter($i))->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();
    }

    private function headerStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function subHeaderStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '2E5C8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function fmt($number): string
    {
        return $number && $number != 0 ? (new CommonHelper())->InrCurrencyFormat($number, 0) : '';
    }

    private function getColumnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $columnNumber = intval(($columnNumber - $temp - 1) / 26);
        }
        return $letter;
    }
}
