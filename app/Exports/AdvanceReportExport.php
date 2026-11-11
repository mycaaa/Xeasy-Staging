<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Export;

class AdvanceReportExport implements WithMultipleSheets, Export, Responsable
{
    use Exportable;

    protected Collection $data;
    protected string $reportType;
    protected array $months;

    public const COLOR_HEADER = '1F4E78';
    public const COLOR_ALTERNATE_EVEN = 'FFE7E6E6';
    public const COLOR_ALTERNATE_ODD = 'FFFFFFFF';
    public const COLOR_SUMMARY_HEADER = '1F4E78';

    public const REPORT_TITLES = [
        'monthly_advance' => 'Monthly Advance Report',
        'employee_wise_advance' => 'Employee Wise Advance Report',
    ];

    public const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public function __construct(Collection $data, string $reportType, array $months)
    {
        $this->data = $data;
        $this->reportType = $reportType;
        $this->months = $months;
    }

    public function sheets(): array
    {
        $title = self::REPORT_TITLES[$this->reportType] ?? 'Advance Report';

        if ($this->reportType === 'monthly_advance') {
            $sheets  = [new AdvanceSummarySheet($this->data, $this->reportType)];
            $grouped = $this->data->groupBy('month');

            // When no months were selected (date-range-only query), derive months
            // from the actual data so every month present gets its own sheet.
            $months = !empty($this->months)
                ? $this->months
                : $grouped->keys()->sort()->values()->toArray();

            foreach ($months as $month) {
                $rows = $grouped->get($month, collect());
                if ($rows->isEmpty()) {
                    continue;
                }
                $monthName = self::MONTH_NAMES[$month] ?? "Month {$month}";
                $sheets[]  = new AdvanceDataSheet($rows, $this->reportType, $monthName, $monthName);
            }

            // Maatwebsite requires at least 2 sheets — add a fallback if only Summary exists
            if (count($sheets) === 1) {
                $sheets[] = new AdvanceDataSheet(collect(), $this->reportType, $title);
            }

            return $sheets;
        }

        return [
            new AdvanceSummarySheet($this->data, $this->reportType),
            new AdvanceDataSheet($this->data, $this->reportType, $title),
        ];
    }
}

// =============================================================================
// Summary Sheet
// =============================================================================

class AdvanceSummarySheet implements
    \Maatwebsite\Excel\Concerns\FromCollection,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithEvents
{
    private Collection $data;
    private string $reportType;

    public function __construct(Collection $data, string $reportType)
    {
        $this->data = $data;
        $this->reportType = $reportType;
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = 1;

                $reportTitle = AdvanceReportExport::REPORT_TITLES[$this->reportType] ?? 'Advance Report';

                // Title
                $sheet->setCellValue("A{$row}", $reportTitle . ' – Summary');
                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => AdvanceReportExport::COLOR_HEADER]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(30);
                $row += 2;

                // Stats header
                $sheet->setCellValue("A{$row}", 'Metric');
                $sheet->setCellValue("B{$row}", 'Value');
                $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => AdvanceReportExport::COLOR_HEADER]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $row++;

                $stats = [
                    'Total Requests' => $this->data->pluck('request_id')->unique()->count(),
                    'Total Employees' => $this->data->pluck('employee_id')->unique()->count(),
                    'Total Activities' => $this->data->count(),
                    'Total Requested Amount' => round((float) ($this->data->sum('requested_amount')), 2),
                    'Total Approved Amount' => round((float) ($this->data->sum('finance_approved_amount')), 2),
                    'Total Paid Amount' => round((float) ($this->data->where('payment_status', 'Paid')->sum('payment_amount')), 2),
                ];

                foreach ($stats as $label => $value) {
                    $sheet->setCellValue("A{$row}", $label);
                    $sheet->setCellValue("B{$row}", $value);
                    $fillColor = $row % 2 === 0 ? AdvanceReportExport::COLOR_ALTERNATE_EVEN : AdvanceReportExport::COLOR_ALTERNATE_ODD;
                    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                    ]);
                    $row++;
                }

                $row += 2;

                // Status breakdown header
                $sheet->setCellValue("A{$row}", 'Approval Status Breakdown');
                $sheet->mergeCells("A{$row}:C{$row}");
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => AdvanceReportExport::COLOR_SUMMARY_HEADER]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $row++;

                foreach (['Status', 'Count', 'Total Amount'] as $i => $h) {
                    $sheet->setCellValue(chr(65 + $i) . $row, $h);
                }
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => AdvanceReportExport::COLOR_HEADER]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $row++;

                foreach ($this->data->groupBy('approval_status') as $status => $rows) {
                    $sheet->setCellValue("A{$row}", $status ?: 'Pending');
                    $sheet->setCellValue("B{$row}", $rows->count());
                    $sheet->setCellValue("C{$row}", round((float) ($rows->sum('requested_amount')), 2));
                    $fillColor = $row % 2 === 0 ? AdvanceReportExport::COLOR_ALTERNATE_EVEN : AdvanceReportExport::COLOR_ALTERNATE_ODD;
                    $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                    ]);
                    $row++;
                }

                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}

// =============================================================================
// Single Data Sheet (used for all three report types)
// =============================================================================

class AdvanceDataSheet implements
    \Maatwebsite\Excel\Concerns\FromCollection,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithEvents
{
    private Collection $data;
    private string $reportType;
    private string $title;
    private ?string $sheetName = null;

    private const HEADERS = [
        'S No.',
        'Month',
        'Year',
        'Emp Code',
        'Employee Name',
        'Function',
        'Department',
        'Sub-Department',
        'Vertical',
        'Raised By',
        'Activity Name',
        'Advance Type',
        'Activity Type',
        'Purpose',
        'Claim Amount',
        'Submitted Date',
        'Submitted Time',
        'Requested Amount',
        'Approval Status',
        'Approval Date',
        'Approval Time',
        'Approved By',
        'Finance Approved Amount',
        'Finance Status',
        'Finance Date',
        'Payment Amount',
        'Payment Status',
        'Payment Date',
    ];

    public function __construct(Collection $data, string $reportType, string $title, ?string $sheetName = null)
    {
        $this->data       = $data;
        $this->reportType = $reportType;
        $this->title      = $title;
        $this->sheetName  = $sheetName;
    }

    public function title(): string
    {
        if ($this->sheetName) {
            return $this->sheetName;
        }
        $map = [
            'monthly_advance'       => 'Monthly Advance',
            'employee_wise_advance' => 'Employee Wise',
        ];
        return $map[$this->reportType] ?? 'Data';
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        $totalCols = count(self::HEADERS);
        $lastCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $totalCols) {
                $sheet = $event->sheet->getDelegate();
                $row = 1;

                // Sheet title row
                $sheet->setCellValue("A{$row}", $this->title);
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => AdvanceReportExport::COLOR_SUMMARY_HEADER]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(24);
                $row++;

                // Column headers
                $col = 'A';
                foreach (self::HEADERS as $header) {
                    $sheet->setCellValue("{$col}{$row}", $header);
                    $col++;
                }
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => AdvanceReportExport::COLOR_HEADER]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(20);
                $row++;

                // Sort data based on report type
                $sorted = match ($this->reportType) {
                    'monthly_advance' => $this->data->sortBy(['month', 'emp_code']),
                    'employee_wise_advance' => $this->data->sortBy(['emp_code', 'month']),
                    default => $this->data,
                };

                // Data rows
                foreach ($sorted->values() as $i => $record) {
                    $monthName = AdvanceReportExport::MONTH_NAMES[$record->month] ?? $record->month;

                    $rowData = [
                        $i + 1,
                        $monthName,
                        $record->financial_year ?? '',
                        \App\Helpers\CommonHelper::formatEmployeeCode($record->emp_code ?? ''),
                        trim($record->emp_name),
                        $record->function_name ?? '',
                        $record->department_name ?? '',
                        $record->sub_department_name ?? '',
                        $record->vertical_name ?? '',
                        trim($record->raised_by_name ?? ''),
                        $record->activity_name ?? '',
                        $record->advance_type_name ?? '',
                        $record->activity_type_name ?? '',
                        $record->purpose ?? '',
                        $record->claim_amount ? round((float) ($record->claim_amount), 2) : '',
                        // Submitted Date & Time (from ar.last_submitted_at)
                        $record->submitted_at ? \Carbon\Carbon::parse($record->submitted_at)->format('d-m-Y') : '',
                        $record->submitted_at ? \Carbon\Carbon::parse($record->submitted_at)->format('H:i:s') : '',
                        round((float) ($record->requested_amount ?? 0), 2),
                        $record->approval_status ?? 'Pending',
                        // Approval Date & Time
                        $record->approval_date     ? \Carbon\Carbon::parse($record->approval_date)->format('d-m-Y')     : '',
                        $record->approval_datetime ? \Carbon\Carbon::parse($record->approval_datetime)->format('H:i:s') : '',
                        trim($record->approved_by_name ?? ''),
                        $record->finance_approved_amount ? round((float) ($record->finance_approved_amount), 2) : '',
                        $record->finance_status ?? '',
                        $record->finance_date ? \Carbon\Carbon::parse($record->finance_date)->format('d-m-Y') : '',
                        $record->payment_amount ? round((float) ($record->payment_amount), 2) : '',
                        $record->payment_status ?? '',
                        $record->payment_date ? \Carbon\Carbon::parse($record->payment_date)->format('d-m-Y') : '',
                    ];

                    $col = 'A';
                    foreach ($rowData as $value) {
                        $sheet->setCellValue("{$col}{$row}", $value);
                        $col++;
                    }

                    $fillColor = $row % 2 === 0 ? AdvanceReportExport::COLOR_ALTERNATE_EVEN : AdvanceReportExport::COLOR_ALTERNATE_ODD;
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                    ]);
                    $row++;
                }

                // No data fallback
                if ($this->data->isEmpty()) {
                    $sheet->setCellValue("A{$row}", 'No data available for the selected filters.');
                    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                }

                // Auto-size columns
                for ($c = 1; $c <= $totalCols; $c++) {
                    $sheet->getColumnDimension(
                        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c)
                    )->setAutoSize(true);
                }
            },
        ];
    }
}
