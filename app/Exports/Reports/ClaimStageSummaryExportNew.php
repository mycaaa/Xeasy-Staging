<?php

namespace App\Exports\Reports;

use App\Services\Reports\ClaimStageReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\Export;

class ClaimStageSummaryExportNew implements WithMultipleSheets, Export
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        $service = new ClaimStageReportService();
        $data = $service->getMonthlyStageSummary($this->filters);

        $fyOrder = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        $requestedMonths = (array) ($this->filters['months'] ?? range(1, 12));
        $months = array_values(array_intersect($fyOrder, $requestedMonths));
        $monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        $company = session('company_id') == 11 ? 'VVNR' : 'VNR Seeds Pvt Ltd';
        $functionName = 'All Functions';
        $departmentName = 'All Departments';
        if (!empty($this->filters['function_id'])) {
            $fn = \App\Models\CoreFunctions::find($this->filters['function_id']);
            $functionName = $fn->function_name ?? 'All Functions';
        }
        if (!empty($this->filters['department_id'])) {
            $dept = \App\Models\CoreDepartments::find($this->filters['department_id']);
            $departmentName = $dept->department_name ?? 'All Departments';
        }

        // Define stages
        $stages = [
            'open' => 'Open-Pending Submit',
            'submitted_no_courier' => 'Submitted-Courier Not Sent',
            'pending_verification' => 'Pending Verification',
            'pending_approval' => 'Pending Approval',
            'pending_finance' => 'Pending Finance',
            'pending_payment' => 'Pending Payment',
            'paid' => 'Paid',
            'no_claim' => 'No Claim Employee List',
        ];

        // Build sheet name map: stage-month -> sheet name (for hyperlinks)
        $sheetNameMap = [];
        foreach ($months as $m) {
            // Each month gets one sheet with all stages combined
            $sheetNameMap["month_{$m}"] = $monthNames[$m];
            // For hyperlinks from specific stage rows
            foreach ($stages as $stageKey => $stageLabel) {
                $sheetNameMap["{$stageKey}_{$m}"] = $monthNames[$m];
            }
        }

        $sheets = [];

        // Sheet 1: Summary with hyperlinks
        $sheets[] = new ClaimStageSummarySheet($data, $months, $company, $functionName, $departmentName, $sheetNameMap);

        // Detail sheets: one per MONTH containing ALL stages
        foreach ($months as $m) {
            $monthData = [];
            foreach ($stages as $stageKey => $stageLabel) {
                $stageData = $service->getStagePendingDetail($stageKey, [
                    'months' => [$m],
                    'function_id' => $this->filters['function_id'] ?? null,
                    'department_id' => $this->filters['department_id'] ?? null,
                ]);
                if (!empty($stageData)) {
                    $monthData[$stageLabel] = $stageData;
                }
            }
            $sheets[] = new MonthDetailSheet($monthData, $monthNames[$m]);
        }

        return $sheets;
    }
}

/**
 * Summary Sheet with hyperlinks to per-month detail sheets
 */
class ClaimStageSummarySheet implements FromArray, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    private array $data;
    private array $months;
    private string $company;
    private string $functionName;
    private string $departmentName;
    private array $sheetNameMap;

    public function __construct(array $data, array $months, string $company, string $functionName, string $departmentName, array $sheetNameMap)
    {
        $this->data = $data;
        $this->months = $months;
        $this->company = $company;
        $this->functionName = $functionName;
        $this->departmentName = $departmentName;
        $this->sheetNameMap = $sheetNameMap;
    }

    public function title(): string { return 'Stage Summary'; }

    public function array(): array
    {
        $months = $this->months;
        $monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        $result = [];
        $result[] = array_merge(["CLAIM STAGE REPORT - {$this->company}"], array_fill(0, count($months), ''));
        $result[] = array_merge(["Function: {$this->functionName} | Department: {$this->departmentName}"], array_fill(0, count($months), ''));
        $result[] = array_merge(["Generated: " . now()->format('d-m-Y h:i A')], array_fill(0, count($months), ''));
        $result[] = array_fill(0, count($months) + 1, '');

        $headers = ['Claim Status'];
        foreach ($months as $m) { $headers[] = $monthNames[$m]; }
        $result[] = $headers;

        $rows = [
            ['label' => 'Total Claimant', 'key' => 'total_claimant'],
            ['label' => 'Less: No claims submitted', 'key' => 'no_claims_submitted'],
            ['label' => 'Total Claims', 'key' => 'total_claims'],
            ['label' => 'Total Paid', 'key' => 'total_paid'],
            ['label' => '% Complete', 'key' => 'percent_complete'],
            ['label' => '', 'key' => null],
            ['label' => 'Total open, final submission not done', 'key' => 'open_final_submission'],
            ['label' => 'Total submitted, courier not sent', 'key' => 'submitted_courier_not_sent'],
            ['label' => 'Total received, pending verification', 'key' => 'pending_verification'],
            ['label' => 'Total verified, pending manager approval', 'key' => 'pending_approval'],
            ['label' => 'Total approved, pending finance', 'key' => 'pending_finance'],
            ['label' => 'Total finance processed, pending payment', 'key' => 'pending_payment'],
        ];

        foreach ($rows as $row) {
            $line = [$row['label']];
            foreach ($months as $m) {
                if ($row['key'] === null) { $line[] = ''; }
                else {
                    $val = $this->data[$m][$row['key']] ?? 0;
                    $line[] = $row['key'] === 'percent_complete' ? $val . '%' : $val;
                }
            }
            $result[] = $line;
        }
        return $result;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '15648C']]],
            2 => ['font' => ['size' => 10, 'color' => ['rgb' => '555555']]],
            3 => ['font' => ['size' => 9, 'color' => ['rgb' => '888888']]],
            5 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            8 => ['font' => ['bold' => true]],
            10 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        $months = $this->months;
        $sheetNameMap = $this->sheetNameMap;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($months, $sheetNameMap) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle("A5:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Map data row keys to stage keys for hyperlinks
                // Rows start at 6 (after 4 header + 1 blank + 1 col header = row 5 is header)
                // Row 6=Total Claimant, 7=No claims, 8=Total Claims, 9=Total Paid, 10=%Complete, 11=blank
                // Row 12=open, 13=submitted_courier, 14=pending_verify, 15=pending_approval, 16=pending_finance, 17=pending_payment
                $rowToStage = [
                    7  => 'no_claim',
                    9  => 'paid',
                    12 => 'open',
                    13 => 'submitted_no_courier',
                    14 => 'pending_verification',
                    15 => 'pending_approval',
                    16 => 'pending_finance',
                    17 => 'pending_payment',
                ];

                foreach ($rowToStage as $rowNum => $stageKey) {
                    foreach ($months as $colIdx => $m) {
                        $col = $colIdx + 2; // column B = 2
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $cellRef = "{$colLetter}{$rowNum}";
                        $val = $sheet->getCell($cellRef)->getValue();

                        if ($val && is_numeric($val) && (int)$val > 0) {
                            $targetSheet = $sheetNameMap["{$stageKey}_{$m}"] ?? null;
                            if ($targetSheet) {
                                $numVal = (int)$val;
                                // Display the number as text, link to month sheet
                                $sheet->getCell($cellRef)->setValueExplicit((string)$numVal, DataType::TYPE_STRING);
                                $sheet->getCell($cellRef)->getHyperlink()
                                    ->setUrl("sheet://'{$targetSheet}'!A1")
                                    ->setTooltip("View {$targetSheet} details");
                                $sheet->getStyle($cellRef)->applyFromArray([
                                    'font' => ['color' => ['rgb' => '0000FF'], 'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE],
                                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                                ]);
                            }
                        }
                    }
                }
            },
        ];
    }
}

/**
 * Month Detail Sheet — all stages for a specific month, grouped by stage
 */
class MonthDetailSheet implements FromArray, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    private array $monthData; // ['Stage Label' => [rows...], ...]
    private string $monthName;

    public function __construct(array $monthData, string $monthName)
    {
        $this->monthData = $monthData;
        $this->monthName = $monthName;
    }

    public function title(): string { return $this->monthName; }

    public function array(): array
    {
        $monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $result = [];
        $result[] = ["Claims Detail — {$this->monthName}"];
        $result[] = [];

        foreach ($this->monthData as $stageLabel => $rows) {
            // Stage header
            $result[] = ["{$stageLabel} (" . count($rows) . " records)"];
            $result[] = ['#', 'Emp Code', 'Employee Name', 'Status', 'Function', 'Department', 'Submit Date', 'Receiving Date', 'Verified Date', 'Approved Date', 'Payment Date'];

            $i = 1;
            foreach ($rows as $row) {
                $r = is_array($row) ? $row : (array) $row;
                $result[] = [
                    $i++,
                    $r['EmpCode'] ?? $r['employee_code'] ?? '-',
                    $r['employee_name'] ?? '-',
                    getEmployeeStatus(null, $r['EmployeeID'] ?? $r['employee_id'] ?? null),
                    $r['function_name'] ?? '-',
                    $r['department_name'] ?? '-',
                    $this->fmtDate($r['DateOfSubmit'] ?? null),
                    $this->fmtDate($r['RecevingDate'] ?? null),
                    $this->fmtDate($r['Verified_Date'] ?? null),
                    $this->fmtDate($r['Approved_Date'] ?? null),
                    $this->fmtDate($r['Fin_PayDate'] ?? null),
                ];
            }
            $result[] = []; // blank row between stages
        }
        return $result;
    }

    private function fmtDate($d): string
    {
        return ($d && \App\Support\ZeroDate::has($d) && $d !== null) ? $d : '-';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '15648C']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = $sheet->getHighestColumn();

                // Style stage headers and data headers
                for ($row = 1; $row <= $lastRow; $row++) {
                    $cellA = $sheet->getCell("A{$row}")->getValue();
                    // Stage header rows (contain "records)")
                    if ($cellA && str_contains((string)$cellA, 'records)')) {
                        $sheet->getStyle("A{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '15648C']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']],
                        ]);
                    }
                    // Column header rows (start with #)
                    if ($cellA === '#') {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
                        ]);
                    }
                }
            },
        ];
    }
}
