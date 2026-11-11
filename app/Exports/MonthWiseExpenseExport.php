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

class MonthWiseExpenseExport implements WithMultipleSheets, Export, Responsable
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
        $this->expenseData = $expenseData;
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
                new MonthWiseExpenseSheet(
                    'Month Wise Expense Report',
                    $this->employeeData,
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
                $sheets[] = new MonthWiseExpenseSheet(
                    $monthName,
                    $this->employeeData,
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

class MonthWiseExpenseSheet implements FromCollection, WithEvents, WithTitle
{
    protected $sheetTitle;
    protected $employeeData;
    protected $expenseData;
    protected $activeMonths;
    protected $isSingleSheet;
    protected $selectedColumn;
    protected $columnConfig;

    public function __construct(string $sheetTitle, Collection $employeeData, Collection $expenseData, array $activeMonths, bool $isSingleSheet, $selectedColumn = 'all')
    {
        $this->sheetTitle = $sheetTitle;
        $this->employeeData = $employeeData;
        $this->expenseData = $expenseData;
        $this->activeMonths = $activeMonths;
        $this->isSingleSheet = $isSingleSheet;
        $this->selectedColumn = $selectedColumn;

        // Define column configurations
        $this->columnConfig = [
            'Filled' => ['field' => 'FilledTAmt', 'label' => 'Filled'],
            'Verified' => ['field' => 'VerifyTAmt', 'label' => 'Verified'],
            'Approved' => ['field' => 'ApprTAmt', 'label' => 'Approved'],
            'Financed' => ['field' => 'FinancedTAmt', 'label' => 'Financed'],
        ];
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

                if ($this->isSingleSheet) {
                    $this->renderSingleSheetLayout($sheet, $curRow);
                } else {
                    $this->renderMultipleSheetLayout($sheet, $curRow);
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

    private function renderSingleSheetLayout($sheet, $curRow)
    {
        $columnsToShow = $this->getColumnsToShow();
        $baseColumns = 6; // S No., Emp Code, Name, Status, Dept, Grade
        $columnsPerMonth = count($columnsToShow);
        $totalColumns = $columnsPerMonth;
        $monthColumns = count($this->activeMonths) * $columnsPerMonth;
        $allColumns = $baseColumns + $monthColumns + $totalColumns;
        $lastColumn = $this->getColumnLetter($allColumns);

        // === HEADER ROW 1 ===
        $headers = [
            "S No.",
            "Employee Code",
            "Employee Name",
            "Status",
            "Department",
            "Grade",
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$curRow}", $header);
            $col++;
        }

        // Month headers
        foreach ($this->activeMonths as $monthNum => $monthName) {
            $startCol = $col;
            $endCol = $this->getColumnLetter($this->getColumnNumber($col) + $columnsPerMonth - 1);
            $sheet->setCellValue("{$startCol}{$curRow}", $monthName);
            if ($startCol !== $endCol) {
                $sheet->mergeCells("{$startCol}{$curRow}:{$endCol}{$curRow}");
            }
            $col = $this->getColumnLetter($this->getColumnNumber($endCol) + 1);
        }

        // Total header
        $totalStartCol = $col;
        $totalEndCol = $this->getColumnLetter($this->getColumnNumber($col) + $columnsPerMonth - 1);
        $sheet->setCellValue("{$totalStartCol}{$curRow}", "Total");
        if ($totalStartCol !== $totalEndCol) {
            $sheet->mergeCells("{$totalStartCol}{$curRow}:{$totalEndCol}{$curRow}");
        }

        // Merge base columns vertically
        for ($i = 1; $i <= $baseColumns; $i++) {
            $colLetter = $this->getColumnLetter($i);
            $sheet->mergeCells("{$colLetter}{$curRow}:{$colLetter}" . ($curRow + 1));
        }

        $this->headerStyle($sheet, "A{$curRow}:{$lastColumn}{$curRow}");
        $curRow++;

        // === HEADER ROW 2 ===
        $col = 'A';
        for ($i = 0; $i < $baseColumns; $i++) {
            $col++;
        }

        foreach ($this->activeMonths as $monthNum => $monthName) {
            foreach ($columnsToShow as $colKey => $colConfig) {
                $sheet->setCellValue("{$col}{$curRow}", $colConfig['label']);
                $col++;
            }
        }

        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue("{$col}{$curRow}", $colConfig['label']);
            $col++;
        }

        $this->subHeaderStyle($sheet, "A{$curRow}:{$lastColumn}{$curRow}");
        $curRow++;

        // === DATA ROWS ===
        $expenseByEmployee = $this->expenseData->groupBy('CrBy');
        $columnTotals = [];
        foreach ($this->activeMonths as $monthNum => $name) {
            $columnTotals[$monthNum] = [];
            foreach ($columnsToShow as $colKey => $colConfig) {
                $columnTotals[$monthNum][$colKey] = 0;
            }
        }

        // Deduplicate employees — monthFinalStage returns one row per employee per month
        $uniqueEmployees = $this->employeeData->unique('EmployeeId');

        $index = 1;
        foreach ($uniqueEmployees as $employee) {
            $employeeId = $employee->EmployeeId;

            $empStatus = getStsUser($employeeId);
            $isDeactivated = in_array($empStatus, ['D', 'R']);

            $rowColor = $isDeactivated ? "FFFFC7CE" : ($index % 2 === 0 ? "FFE7E6E6" : "FFFFFFFF");

            $data = [
                $index,
                \App\Helpers\CommonHelper::formatEmployeeCode($employee->employee_code ?? ""),
                $employee->employee_name ?? "",
                $empStatus,
                $employee->department_name ?? "",
                $employee->grade_name ?? "",
            ];

            $rowTotal = [];
            foreach ($columnsToShow as $colKey => $colConfig) {
                $rowTotal[$colKey] = 0;
            }

            $employeeExpenses = $expenseByEmployee->get($employeeId, collect());
            $expensesByMonth = $employeeExpenses->groupBy('ClaimMonth');

            foreach ($this->activeMonths as $monthNum => $monthName) {
                $monthExpenses = $expensesByMonth->get($monthNum, collect());

                foreach ($columnsToShow as $colKey => $colConfig) {
                    $amount = $monthExpenses->sum($colConfig['field']);
                    $data[] = $this->fmt($amount);
                    $rowTotal[$colKey] += $amount;
                    $columnTotals[$monthNum][$colKey] += $amount;
                }
            }

            // Total columns
            foreach ($columnsToShow as $colKey => $colConfig) {
                $data[] = $this->fmt($rowTotal[$colKey]);
            }

            // Write data
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue("{$col}{$curRow}", $value);
                $col++;
            }

            // Alignment
            foreach (['A', 'B', 'D', 'F'] as $c) {
                $sheet->getStyle("{$c}{$curRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $startAmountCol = $this->getColumnLetter($baseColumns + 1);
            $sheet->getStyle("{$startAmountCol}{$curRow}:{$lastColumn}{$curRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Row styling
            $sheet->getStyle("A{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // Bold total columns
            $sheet->getStyle("{$totalStartCol}{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $isDeactivated ? 'FFFFC7CE' : 'FFFDE9D9']],
            ]);

            $curRow++;
            $index++;
        }

        // === GRAND TOTAL ROW ===
        $sheet->setCellValue("A{$curRow}", "Grand Total");
        $sheet->mergeCells("A{$curRow}:F{$curRow}");

        $col = $this->getColumnLetter($baseColumns + 1);
        $grandTotal = [];
        foreach ($columnsToShow as $colKey => $colConfig) {
            $grandTotal[$colKey] = 0;
        }

        foreach ($this->activeMonths as $monthNum => $name) {
            foreach ($columnsToShow as $colKey => $colConfig) {
                $sheet->setCellValue("{$col}{$curRow}", $this->fmt($columnTotals[$monthNum][$colKey]));
                $grandTotal[$colKey] += $columnTotals[$monthNum][$colKey];
                $col++;
            }
        }

        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue("{$col}{$curRow}", $this->fmt($grandTotal[$colKey]));
            $col++;
        }

        $sheet->getStyle("A{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Auto-size columns
        for ($i = 1; $i <= $allColumns; $i++) {
            $colLetter = $this->getColumnLetter($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();
    }

    private function renderMultipleSheetLayout($sheet, $curRow)
    {
        $columnsToShow = $this->getColumnsToShow();
        $baseColumns = 6;
        $columnsPerMonth = count($columnsToShow);
        $allColumns = $baseColumns + $columnsPerMonth;
        $lastColumn = $this->getColumnLetter($allColumns);

        // === HEADER ROW ===
        $headers = [
            "S No.",
            "Employee Code",
            "Employee Name",
            "Status",
            "Department",
            "Grade",
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$curRow}", $header);
            $col++;
        }

        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue("{$col}{$curRow}", $colConfig['label']);
            $col++;
        }

        $this->headerStyle($sheet, "A{$curRow}:{$lastColumn}{$curRow}");
        $curRow++;

        // === DATA ROWS ===
        $expenseByEmployee = $this->expenseData->groupBy('CrBy');
        $columnTotals = [];
        foreach ($columnsToShow as $colKey => $colConfig) {
            $columnTotals[$colKey] = 0;
        }

        // Deduplicate employees — monthFinalStage returns one row per employee per month
        $uniqueEmployees = $this->employeeData->unique('EmployeeId');

        $index = 1;
        foreach ($uniqueEmployees as $employee) {
            $employeeId = $employee->EmployeeId;

            $empStatus = getStsUser($employeeId);
            $isDeactivated = in_array($empStatus, ['D', 'R']);

            $rowColor = $isDeactivated ? "FFFFC7CE" : ($index % 2 === 0 ? "FFE7E6E6" : "FFFFFFFF");

            $data = [
                $index,
                \App\Helpers\CommonHelper::formatEmployeeCode($employee->employee_code ?? ""),
                $employee->employee_name ?? "",
                $empStatus,
                $employee->department_name ?? "",
                $employee->grade_name ?? "",
            ];

            $employeeExpenses = $expenseByEmployee->get($employeeId, collect());

            foreach ($columnsToShow as $colKey => $colConfig) {
                $amount = $employeeExpenses->sum($colConfig['field']);
                $data[] = $this->fmt($amount);
                $columnTotals[$colKey] += $amount;
            }

            // Write data
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue("{$col}{$curRow}", $value);
                $col++;
            }

            // Alignment
            foreach (['A', 'B', 'D', 'F'] as $c) {
                $sheet->getStyle("{$c}{$curRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $startAmountCol = $this->getColumnLetter($baseColumns + 1);
            $sheet->getStyle("{$startAmountCol}{$curRow}:{$lastColumn}{$curRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Row styling
            $sheet->getStyle("A{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $curRow++;
            $index++;
        }

        // === TOTAL ROW ===
        $sheet->setCellValue("A{$curRow}", "Total");
        $sheet->mergeCells("A{$curRow}:F{$curRow}");

        $col = $this->getColumnLetter($baseColumns + 1);
        foreach ($columnsToShow as $colKey => $colConfig) {
            $sheet->setCellValue("{$col}{$curRow}", $this->fmt($columnTotals[$colKey]));
            $col++;
        }

        $sheet->getStyle("A{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Auto-size columns
        for ($i = 1; $i <= $allColumns; $i++) {
            $colLetter = $this->getColumnLetter($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
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

    private function getColumnLetter($columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $columnNumber = intval(($columnNumber - $temp - 1) / 26);
        }
        return $letter;
    }

    private function getColumnNumber($columnLetter): int
    {
        $number = 0;
        $length = strlen($columnLetter);
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($columnLetter[$i]) - ord('A') + 1);
        }
        return $number;
    }
}
