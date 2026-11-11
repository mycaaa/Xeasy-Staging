<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Export;

class ClaimStagesExport implements WithMultipleSheets, Export, Responsable
{
    use Exportable;

    protected $data;
    protected $noClaimData;
    protected $stageFilter;
    protected $filename = '{Company}_Claim_Stages_Report.xlsx';

    // VNR (Company 1) - Full workflow
    public const STAGES_VNR = [
        'Pending Submission',
        'Submitted - Pending Courier',
        'Courier Completed - Pending Verification',
        'Verified - Pending Approval',
        'Approved - Pending Finance',
        'Finance Processed - Pending Payment',
        'Paid'
    ];

    // VVNR (Company 11) - Updated workflow as per requirement
    public const STAGES_VVNR = [
        'Pending Submission',
        'Submitted - Pending Approval',
        'Approved - Pending Payment',
        'Paid - Pending Courier',
        'Courier Completed - Pending Verification',
        'Verified - Completed'
    ];

    public const STAGE_DATE_MAP_VNR = [
        'Pending Submission'                          => ['column' => 'Expected Submit Date', 'field' => null],
        'Submitted - Pending Courier'                 => ['column' => 'Submit Date',           'field' => 'DateOfSubmit'],
        'Courier Completed - Pending Verification'    => ['column' => 'Courier Receiving Date','field' => 'RecevingDate'],
        'Verified - Pending Approval'                 => ['column' => 'Verify Date',           'field' => 'Verified_Date'],
        'Approved - Pending Finance'                  => ['column' => 'Approved Date',         'field' => 'Approved_Date'],
        'Finance Processed - Pending Payment'         => ['column' => 'Finance Date',          'field' => 'Finance_Date'],
        'Paid'                                        => ['column' => 'Paid Date',             'field' => 'Fin_PayDate'],
    ];

    // Updated VVNR stage date mapping
    public const STAGE_DATE_MAP_VVNR = [
        'Pending Submission'                       => ['column' => 'Expected Submit Date', 'field' => null],
        'Submitted - Pending Approval'             => ['column' => 'Submit Date',           'field' => 'DateOfSubmit'],
        'Approved - Pending Payment'               => ['column' => 'Approved Date',         'field' => 'Approved_Date'],
        'Paid - Pending Courier'                   => ['column' => 'Paid Date',             'field' => 'Fin_PayDate'],
        'Courier Completed - Pending Verification' => ['column' => 'Courier Receiving Date','field' => 'RecevingDate'],
        'Verified - Completed'                     => ['column' => 'Verify Date',           'field' => 'Verified_Date'],
    ];

    public const COLOR_ALTERNATE_EVEN  = 'FFE7E6E6';
    public const COLOR_ALTERNATE_ODD   = 'FFFFFFFF';
    public const COLOR_SEPARATION      = 'FFFFC7CE';  // Light Red  – Employee with separation date
    public const COLOR_DUPLICATE       = 'FFFF6B6B';  // Bright Red – Duplicate employee entries
    public const COLOR_HEADER          = '1F4E78';    // Dark Blue  – Headers
    public const COLOR_SUMMARY_HEADER  = '2E8B57';    // Sea Green  – Summary headers
    public const COLOR_WARNING         = 'FFFFE4B5';  // Light Yellow – Warning/attention needed

    // Company names for sheet title
    public const COMPANY_NAMES = [
        1 => 'VNR',
        11 => 'VVNR'
    ];

    public function __construct(Collection $data, ?Collection $noClaimData = null, ?string $stageFilter = 'all')
    {
        $this->data        = $data;
        $this->noClaimData = $noClaimData ?? collect();
        $this->stageFilter = $stageFilter ?? 'all';
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    public static function getStages(): array
    {
        $companyId = session('company_id', 1);
        return $companyId == 11 ? self::STAGES_VVNR : self::STAGES_VNR;
    }

    public static function getStageDateMap(): array
    {
        $companyId = session('company_id', 1);
        return $companyId == 11 ? self::STAGE_DATE_MAP_VVNR : self::STAGE_DATE_MAP_VNR;
    }

    public static function getCompanyName(): string
    {
        $companyId = session('company_id', 1);
        return self::COMPANY_NAMES[$companyId] ?? 'Company';
    }

    // -------------------------------------------------------------------------
    // Sheets
    // -------------------------------------------------------------------------

    public function sheets(): array
    {
        $sheets = [];

        // Summary sheet always first
        $sheets[] = new ClaimStageSummarySheet($this->data, $this->noClaimData, $this->stageFilter);

        foreach ($this->data as $month => $rows) {
            $rowsCollection  = collect($rows);
            $stageGroups     = $this->groupByStages($rowsCollection);
            $noClaimForMonth = $this->getNoClaimForMonth($month);

            if (empty($stageGroups) && $noClaimForMonth->isEmpty()) {
                continue;
            }

            $sheets[] = new ClaimStageSheet($month, $stageGroups, $noClaimForMonth);
        }

        // Prevent "no sheets" error
        if (count($sheets) === 1) {
            $sheets[] = new EmptyClaimSheet();
        }

        return $sheets;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function groupByStages(Collection $rows): array
    {
        $stageGroups = [];
        $stages      = self::getStages();

        foreach ($stages as $stage) {
            if ($this->stageFilter !== 'all' && $this->stageFilter !== $stage) {
                continue;
            }

            $filtered = $rows->filter(fn($row) => self::detectStage($row) === $stage);

            if ($filtered->isNotEmpty()) {
                // ---------------------------------------------------------------
                // Sort: active employees first, deactivated (separated) last.
                // Within each group keep original order.
                // ---------------------------------------------------------------
                $stageGroups[$stage] = $filtered
                    ->sortBy(fn($row) => self::isDeactivated($row) ? 1 : 0)
                    ->values();
            }
        }

        return $stageGroups;
    }

    private function getNoClaimForMonth(string $month): Collection
    {
        if ($this->stageFilter !== 'all' && $this->stageFilter !== 'No Claim Employee List') {
            return collect();
        }

        return $this->noClaimData
            ->filter(fn($row) => (string) $row->Month === (string) $month)
            // Deactivated last in no-claim list as well
            ->sortBy(fn($row) => self::isDeactivated($row) ? 1 : 0)
            ->values();
    }

    // -------------------------------------------------------------------------
    // Stage detection
    // -------------------------------------------------------------------------

    public static function detectStage($row): string
    {
        $companyId = session('company_id', 1);
        return $companyId == 11
            ? self::detectStageVVNR($row)
            : self::detectStageVNR($row);
    }

    /**
     * VNR (Company 1) – full workflow.
     * Hierarchical: each record appears in the HIGHEST completed stage only.
     */
    private static function detectStageVNR($row): string
    {
        if (\App\Support\Paid::is($row)) return 'Paid'; // PAID-01
        if (!self::isEmptyDate($row->Finance_Date   ?? null)) return 'Finance Processed - Pending Payment';
        if (!self::isEmptyDate($row->Approved_Date  ?? null)) return 'Approved - Pending Finance';
        if (!self::isEmptyDate($row->Verified_Date  ?? null)) return 'Verified - Pending Approval';
        if (!self::isEmptyDate($row->RecevingDate   ?? null)) return 'Courier Completed - Pending Verification';

        if (!self::isEmptyDate($row->DateOfSubmit ?? null) && ($row->Status ?? null) === 'Closed') {
            return 'Submitted - Pending Courier';
        }

        return 'Pending Submission';
    }

    /**
     * VVNR (Company 11) – updated workflow.
     * Flow: Submit → Approve → Pay → Courier Receive → Verify → Complete
     *
     * We check from the LAST stage backwards so a record always lands in
     * the highest stage it has actually reached.
     */
    private static function detectStageVVNR($row): string
    {
        // Stage 6 – Verification done → fully complete
        if (!self::isEmptyDate($row->Verified_Date ?? null)) {
            return 'Verified - Completed';
        }

        // Stage 5 – Courier received (RecevingDate filled) → waiting for verification
        // Only valid here if payment was already done (normal flow).
        // If RecevingDate is filled but Fin_PayDate is empty it is an out-of-order
        // data entry; we still show it as "Courier Completed - Pending Verification"
        // because physically the courier HAS arrived and verification is what's next.
        if (!self::isEmptyDate($row->RecevingDate ?? null)) {
            return 'Courier Completed - Pending Verification';
        }

        // Stage 4 – Payment done, courier not yet received → waiting for courier
        if (\App\Support\Paid::is($row)) { // PAID-01
            return 'Paid - Pending Courier';
        }

        // Stage 3 – Approved, payment not done → waiting for payment
        if (!self::isEmptyDate($row->Approved_Date ?? null) &&
            !self::isEmptyDate($row->DateOfSubmit  ?? null)) {
            return 'Approved - Pending Payment';
        }

        // Stage 2 – Submitted (month closed), not yet approved
        if (!self::isEmptyDate($row->DateOfSubmit ?? null) &&
            ($row->Status ?? null) === 'Closed') {
            return 'Submitted - Pending Approval';
        }

        // Stage 1 – Nothing done yet
        return 'Pending Submission';
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Returns TRUE when the employee has a separation / deactivation date.
     */
    public static function isDeactivated($row): bool
    {
        $date = $row->DateOfSepration ?? null;
        if (empty($date)) return false;

        $invalidDates = ['0000-00-00', '1970-01-01'];
        return !in_array($date, $invalidDates, true);
    }

    private static function isEmptyDate(?string $date): bool
    {
        return empty($date) || \App\Support\ZeroDate::none($date);
    }
}

// =============================================================================
// ClaimStageSummarySheet
// =============================================================================

class ClaimStageSummarySheet implements
    \Maatwebsite\Excel\Concerns\FromCollection,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithEvents
{
    private $data;
    private $noClaimData;
    private $stageFilter;

    public function __construct($data, $noClaimData, $stageFilter)
    {
        $this->data        = $data;
        $this->noClaimData = $noClaimData;
        $this->stageFilter = $stageFilter;
    }

    public function title(): string { return "Summary"; }

    public function collection(): Enumerable { return collect([]); }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $currentRow = 1;
                $companyName = ClaimStagesExport::getCompanyName();

                // Main title
                $sheet->setCellValue("A{$currentRow}", "{$companyName} - Claim Stages Report Summary");
                $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16, 'color' => ['argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_HEADER]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(30);
                $currentRow += 3;

                $currentRow = $this->writeUnifiedSummary($sheet, $currentRow);

                foreach (range('A', 'D') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    private function writeUnifiedSummary($sheet, $currentRow)
    {
        // ---- Totals ----
        $totalClaims = 0;
        $stageCounts = [];

        foreach ($this->data as $month => $rows) {
            foreach ($rows as $row) {
                $stage = ClaimStagesExport::detectStage($row);
                $stageCounts[$stage] = ($stageCounts[$stage] ?? 0) + 1;
                $totalClaims++;
            }
        }

        // Count unique employees in noClaimData (not rows per month)
        $noClaimUniqueEmployees = 0;
        $noClaimEmployeeCodes = [];
        
        foreach ($this->noClaimData as $row) {
            $empCode = $row->employee_code ?? null;
            if ($empCode && !in_array($empCode, $noClaimEmployeeCodes)) {
                $noClaimEmployeeCodes[] = $empCode;
                $noClaimUniqueEmployees++;
            }
        }
        
        if ($noClaimUniqueEmployees > 0) {
            $stageCounts['No Claim Employee List'] = $noClaimUniqueEmployees;
            $totalClaims += $noClaimUniqueEmployees;
        }

        // ---- Duplicate check ----
        $employeeStages = [];
        $duplicateCount = 0;

        foreach ($this->data as $month => $rows) {
            foreach ($rows as $row) {
                $empKey = ($row->employee_code ?? '') . '|' . $month;
                if (isset($employeeStages[$empKey])) {
                    $duplicateCount++;
                } else {
                    $employeeStages[$empKey] = ClaimStagesExport::detectStage($row);
                }
            }
        }

        // ---- Table header ----
        $sheet->setCellValue("A{$currentRow}", 'Stage');
        $sheet->setCellValue("B{$currentRow}", 'Count');
        $sheet->setCellValue("C{$currentRow}", 'Percentage');
        $sheet->setCellValue("D{$currentRow}", 'Description');

        $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_HEADER]],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $stageDescriptions = [
            'Pending Submission'                       => 'Claims not yet submitted by employee',
            'Submitted - Pending Courier'              => 'Claims submitted, waiting for courier process (VNR)',
            'Courier Completed - Pending Verification' => 'Courier process completed, waiting for verification',
            'Verified - Pending Approval'              => 'Claims verified, waiting for manager approval (VNR)',
            'Approved - Pending Finance'               => 'Claims approved, waiting for finance processing (VNR)',
            'Finance Processed - Pending Payment'      => 'Claims processed by finance, waiting for payment (VNR)',
            'Paid'                                     => 'Claims fully completed with payment (VNR)',
            'Submitted - Pending Approval'             => 'Claims submitted, waiting for manager approval (VVNR)',
            'Approved - Pending Payment'               => 'Claims approved, waiting for payment processing (VVNR)',
            'Paid - Pending Courier'                   => 'Claims paid, waiting for courier process (VVNR)',
            'Verified - Completed'                     => 'Claims fully completed with final verification (VVNR)',
            'No Claim Employee List'                   => 'Employees with no claims submitted for selected period',
        ];

        // ---- Stage rows ----
        foreach (ClaimStagesExport::getStages() as $stage) {
            $count = $stageCounts[$stage] ?? 0;
            if ($count > 0 || $this->stageFilter === 'all') {
                $percentage = $totalClaims > 0 ? round(($count / $totalClaims) * 100, 1) : 0;
                $sheet->setCellValue("A{$currentRow}", $stage);
                $sheet->setCellValue("B{$currentRow}", $count);
                $sheet->setCellValue("C{$currentRow}", $percentage . '%');
                $sheet->setCellValue("D{$currentRow}", $stageDescriptions[$stage] ?? '');
                $fillColor = $currentRow % 2 === 0 ? ClaimStagesExport::COLOR_ALTERNATE_EVEN : ClaimStagesExport::COLOR_ALTERNATE_ODD;
                $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
                ]);
                $currentRow++;
            }
        }

        // ---- No Claim row (unique employees with no claims) ----
        if (isset($stageCounts['No Claim Employee List'])) {
            $count      = $stageCounts['No Claim Employee List'];
            $percentage = $totalClaims > 0 ? round(($count / $totalClaims) * 100, 1) : 0;
            $sheet->setCellValue("A{$currentRow}", 'No Claim Employee List');
            $sheet->setCellValue("B{$currentRow}", $count);
            $sheet->setCellValue("C{$currentRow}", $percentage . '%');
            $sheet->setCellValue("D{$currentRow}", $stageDescriptions['No Claim Employee List']);
            $fillColor = $currentRow % 2 === 0 ? ClaimStagesExport::COLOR_ALTERNATE_EVEN : ClaimStagesExport::COLOR_ALTERNATE_ODD;
            $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
            ]);
            $currentRow++;
        }

        // ---- Total row ----
        $sheet->setCellValue("A{$currentRow}", 'TOTAL');
        $sheet->setCellValue("B{$currentRow}", $totalClaims);
        $sheet->setCellValue("C{$currentRow}", '100%');
        $sheet->setCellValue("D{$currentRow}", 'All claims and employees');
        $sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray([
            'font'    => ['bold' => true, 'color' => ['argb' => \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_HEADER]],
        ]);
        $currentRow += 2;

        // ---- Data quality ----
        $qualityStatus = $duplicateCount === 0
            ? '✓ Data Quality: PASSED'
            : "⚠ Data Quality: {$duplicateCount} duplicates found";
        $qualityColor = $duplicateCount === 0 ? '008000' : 'FF0000';

        $sheet->setCellValue("A{$currentRow}", $qualityStatus);
        $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => $qualityColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        return $currentRow + 1;
    }
}

// =============================================================================
// EmptyClaimSheet
// =============================================================================

class EmptyClaimSheet implements
    \Maatwebsite\Excel\Concerns\FromCollection,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\WithEvents
{
    public function title(): string { return "No Data"; }
    public function collection(): Enumerable { return collect([]); }

    private function getHeaders(): array
    {
        $headers = [
            'S No.', 'Employee Code', 'Employee Name', 'Emp Status', 'Date of Separation',
            'Function', 'Vertical', 'Department', 'Sub-Department', 'Grade', 'Reporting To', 'Month',
            'L1 Manager Code', 'L1 Manager Name', 'L2 Manager Code', 'L2 Manager Name',
            'L3 Manager Code', 'L3 Manager Name',
            'Submit Date', 'Courier Send Date', 'Courier Receiving Date', 'Verify Date', 'Approved Date', 'Finance Date', 'Paid Date'
        ];
        if (session('company_id') != 11) {
            $headers[] = 'Last Mail Date';
            $headers[] = 'Mail Subject';
        }
        return $headers;
    }

    private function getLastCol(): string
    {
        return session('company_id') != 11 ? 'AA' : 'Y';
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $this->getLastCol();

                $col = 'A';
                foreach ($this->getHeaders() as $header) {
                    $sheet->setCellValue("{$col}1", $header);
                    $col++;
                }

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_HEADER]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->setCellValue('A2', 'No data available for the selected filters.');
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(30);

                foreach (range('A', $lastCol) as $c) {
                    $sheet->getColumnDimension($c)->setAutoSize(true);
                }
                $sheet->calculateColumnWidths();
            },
        ];
    }
}

// =============================================================================
// ClaimStageSheet
// =============================================================================

class ClaimStageSheet implements
    \Maatwebsite\Excel\Concerns\FromCollection,
    \Maatwebsite\Excel\Concerns\WithEvents,
    \Maatwebsite\Excel\Concerns\WithTitle
{
    private $month;
    private $stageGroups;
    private $noClaimEmployees;

    private const HEADERS = [
        'S No.', 'Employee Code', 'Employee Name', 'Date of Separation',
        'Function', 'Vertical', 'Department', 'Sub-Department', 'Grade', 'Reporting To', 'Month',
        'L1 Manager Code', 'L1 Manager Name', 'L2 Manager Code', 'L2 Manager Name',
        'L3 Manager Code', 'L3 Manager Name',
        'Submit Date', 'Courier Send Date', 'Courier Receiving Date', 'Verify Date', 'Approved Date', 'Finance Date', 'Paid Date',
        'Last Mail Date', 'Mail Subject'
    ];

    // Columns that should be centre-aligned (A=SNo, B=EmpCode, D=DateSep, I=Grade, J=Reporting, K=Month, L=L1Code, N=L2Code, P=L3Code, R–X=dates, Y=mail date)
    private const CENTER_COLUMNS = ['A', 'B', 'D', 'I', 'K', 'L', 'N', 'P', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'];

    // Last column letter (26 columns total)
    private const LAST_COL = 'Z';

    public function __construct($month, $stageGroups, $noClaimEmployees)
    {
        $this->month            = $month;
        $this->stageGroups      = $stageGroups;
        $this->noClaimEmployees = $noClaimEmployees;
    }

    /**
     * Returns true if any row in this sheet has L1 Manager data.
     * When true, 'Reporting To' is suppressed from headers and data rows.
     */
    private function hasL1Data(): bool
    {
        foreach ($this->stageGroups as $rows) {
            foreach ($rows as $row) {
                if (!empty($row->level1_manager_code) || !empty($row->level1_manager_name)) {
                    return true;
                }
            }
        }
        foreach ($this->noClaimEmployees as $row) {
            if (!empty($row->level1_manager_code) || !empty($row->level1_manager_name)) {
                return true;
            }
        }
        return false;
    }

    private function getHeaders(): array
    {
        $showReportingTo = !$this->hasL1Data();

        $headers = ['S No.', 'Employee Code', 'Employee Name', 'Emp Status', 'Date of Separation',
            'Function', 'Vertical', 'Department', 'Sub-Department', 'Grade'];

        if ($showReportingTo) {
            $headers[] = 'Reporting To';
        }

        array_push($headers,
            'Month',
            'L1 Manager Code', 'L1 Manager Name',
            'L2 Manager Code', 'L2 Manager Name',
            'L3 Manager Code', 'L3 Manager Name',
            'Submit Date', 'Courier Send Date', 'Courier Receiving Date', 'Verify Date', 'Approved Date', 'Finance Date', 'Paid Date'
        );
        if (session('company_id') != 11) {
            $headers[] = 'Last Mail Date';
            $headers[] = 'Mail Subject';
        }
        return $headers;
    }

    private function getLastCol(): string
    {
        return session('company_id') != 11 ? 'AA' : 'Y';
    }

    public function title(): string
    {
        return \DateTime::createFromFormat('!m', $this->month)->format('F');
    }

    public function collection(): Enumerable { return collect([]); }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $currentRow = 1;

                foreach ($this->stageGroups as $stageName => $rows) {
                    $currentRow = $this->writeStageSection($sheet, $stageName, $rows, $currentRow);
                }

                if ($this->noClaimEmployees->isNotEmpty()) {
                    $currentRow = $this->writeNoClaimSection($sheet, $currentRow);
                }

                $lastColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($this->getLastCol());
                for ($colIndex = 1; $colIndex <= $lastColIndex; $colIndex++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->calculateColumnWidths();
            },
        ];
    }

    // -------------------------------------------------------------------------
    // Section writers
    // -------------------------------------------------------------------------

    private function writeStageSection(Worksheet $sheet, string $stageName, $rows, int $currentRow): int
    {
        // Stage title bar
        $sheet->setCellValue("A{$currentRow}", $stageName);
        $sheet->mergeCells("A{$currentRow}:" . $this->getLastCol() . "{$currentRow}");
        $this->applyTitleStyle($sheet, "A{$currentRow}:" . $this->getLastCol() . "{$currentRow}");
        $currentRow++;

        // All stages share the same fixed headers
        $currentRow = $this->writeHeaders($sheet, $this->getHeaders(), $currentRow);

        // -----------------------------------------------------------------------
        // Data rows – rows are already sorted (active first, deactivated last)
        // by groupByStages() in ClaimStagesExport.
        // -----------------------------------------------------------------------
        $duplicateTracker   = [];
        $activeIndex        = 0;
        $deactivatedIndex   = 0;
        $separatorWritten   = false;

        foreach ($rows as $row) {
            $isDeactivated = ClaimStagesExport::isDeactivated($row);

            // Write separator row before the first deactivated record
            if ($isDeactivated && !$separatorWritten) {
                $this->writeDeactivatedSeparator($sheet, $currentRow);
                $currentRow++;
                $separatorWritten = true;
            }

            // Determine running index (reset for deactivated block)
            if ($isDeactivated) {
                $deactivatedIndex++;
                $displayIndex = $deactivatedIndex;
            } else {
                $activeIndex++;
                $displayIndex = $activeIndex;
            }

            $key         = ($row->employee_code ?? '') . '|' . $this->month;
            $isDuplicate = isset($duplicateTracker[$key]);
            $duplicateTracker[$key] = true;

            $data     = $this->prepareRowData($row, $displayIndex);
            $rowColor = $this->determineRowColor($displayIndex, $row, $isDuplicate);

            $this->writeDataRow($sheet, $data, $currentRow, $rowColor);
            $currentRow++;
        }

        return $currentRow + 1; // blank gap between stages
    }

    private function writeNoClaimSection(Worksheet $sheet, int $currentRow): int
    {
        $sheet->setCellValue("A{$currentRow}", 'No Claim Employee List');
        $sheet->mergeCells("A{$currentRow}:" . $this->getLastCol() . "{$currentRow}");
        $this->applyTitleStyle($sheet, "A{$currentRow}:" . $this->getLastCol() . "{$currentRow}");
        $currentRow++;

        // No-claim rows have no dates — use base headers + Status in place of date columns
        $baseHeaders = ['S No.', 'Employee Code', 'Employee Name', 'Emp Status', 'Date of Separation',
             'Function', 'Vertical', 'Department', 'Sub-Department', 'Grade'];
        if (!$this->hasL1Data()) {
            $baseHeaders[] = 'Reporting To';
        }
        array_push($baseHeaders, 'Month',
             'L1 Manager Code', 'L1 Manager Name',
             'L2 Manager Code', 'L2 Manager Name',
             'L3 Manager Code', 'L3 Manager Name');
        $headers    = array_merge($baseHeaders, ['Status', '', '', '', '', '', '']);
        $currentRow = $this->writeHeaders($sheet, $headers, $currentRow);

        // noClaimEmployees is already sorted (active first, deactivated last)
        $activeIndex      = 0;
        $deactivatedIndex = 0;
        $separatorWritten = false;

        foreach ($this->noClaimEmployees as $row) {
            $isDeactivated = ClaimStagesExport::isDeactivated($row);

            if ($isDeactivated && !$separatorWritten) {
                $this->writeDeactivatedSeparator($sheet, $currentRow);
                $currentRow++;
                $separatorWritten = true;
            }

            if ($isDeactivated) {
                $deactivatedIndex++;
                $displayIndex = $deactivatedIndex;
            } else {
                $activeIndex++;
                $displayIndex = $activeIndex;
            }

            $data     = $this->prepareRowData($row, $displayIndex, true);
            $rowColor = $this->determineRowColor($displayIndex, $row, false);

            $this->writeDataRow($sheet, $data, $currentRow, $rowColor);
            $currentRow++;
        }

        return $currentRow + 1;
    }

    // -------------------------------------------------------------------------
    // Row / cell helpers
    // -------------------------------------------------------------------------

    /**
     * Writes a thin labelled separator row before the deactivated block.
     */
    private function writeDeactivatedSeparator(Worksheet $sheet, int $row): void
    {
        $sheet->setCellValue("A{$row}", 'Deactivated / Separated Employees');
        $sheet->mergeCells("A{$row}:" . $this->getLastCol() . "{$row}");
        $sheet->getStyle("A{$row}:" . $this->getLastCol() . "{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'italic' => true, 'color' => ['argb' => 'FF9C0006']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_SEPARATION]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
    }

    private function writeHeaders(Worksheet $sheet, array $headers, int $row): int
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $col++;
        }
        $this->applyHeaderStyle($sheet, "A{$row}:" . $this->getLastCol() . "{$row}");
        return $row + 1;
    }

    private function writeDataRow(Worksheet $sheet, array $data, int $row, string $color): void
    {
        $col = 'A';
        foreach ($data as $value) {
            $sheet->setCellValue("{$col}{$row}", $value);
            $col++;
        }

        foreach (self::CENTER_COLUMNS as $c) {
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $styleArray = [
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        if ($color === ClaimStagesExport::COLOR_DUPLICATE) {
            $styleArray['font']    = ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]];
            $styleArray['borders'] = ['allBorders' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => 'FF000000']]];
        }

        $sheet->getStyle("A{$row}:" . $this->getLastCol() . "{$row}")->applyFromArray($styleArray);
    }

    /**
     * Build the data array for one row.
     * $noClaim = true  → date columns are all blank (No Claim section)
     * $noClaim = false → date columns filled from the row object
     */
    private function prepareRowData($row, int $index, bool $noClaim = false): array
    {
        $empCode = \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code ?? '');

        $data = [
            $index,
            $empCode,
            $row->employee_name        ?? '',
            getEmployeeStatus(null, $row->EmployeeID ?? $row->EmployeeId ?? $row->employee_id ?? null),
            $this->formatDate($row->DateOfSepration ?? ''),
            $row->function_name        ?? '',
            $row->vertical_name        ?? '',
            $row->department_name      ?? '',
            $row->sub_department_name  ?? '',
            $row->grade_name           ?? '',
        ];

        // Only include Reporting To when no L1 Manager data exists
        if (!$this->hasL1Data()) {
            $data[] = $row->reporting_name ?? '';
        }

        array_push($data,
            $this->monthName($this->month),
            $row->level1_manager_code  ?? '',
            $row->level1_manager_name  ?? '',
            $row->level2_manager_code  ?? '',
            $row->level2_manager_name  ?? '',
            $row->level3_manager_code  ?? '',
            $row->level3_manager_name  ?? '',
        );

        if ($noClaim) {
            // No dates available — fill all 7 date columns with blank
            $data = array_merge($data, ['No Claim', '', '', '', '', '', '']);
        } else {
            // All process dates in workflow order
            $data[] = $this->formatDate($row->DateOfSubmit    ?? ''); // Submit Date
            $data[] = $this->formatDate($row->PostDate        ?? ''); // Courier Send Date
            $data[] = $this->formatDate($row->RecevingDate    ?? ''); // Courier Receiving Date
            $data[] = $this->formatDate($row->Verified_Date   ?? ''); // Verify Date
            $data[] = $this->formatDate($row->Approved_Date   ?? ''); // Approved Date
            $data[] = $this->formatDate($row->Finance_Date    ?? ''); // Finance Date
            $data[] = $this->formatDate($row->Fin_PayDate     ?? ''); // Paid Date
        }

        // Mail columns - only for non-VVNR companies
        if (session('company_id') != 11) {
            $data[] = $this->formatDate($row->last_mail_date ?? '');   // Last Mail Date
            $data[] = $row->last_mail_subject ?? '';                     // Mail Subject
        }

        return $data;
    }

    /**
     * Determines row background colour.
     * Priority: duplicate > deactivated/separated > alternating.
     */
    private function determineRowColor(int $index, $row, bool $isDuplicate): string
    {
        if ($isDuplicate) {
            return ClaimStagesExport::COLOR_DUPLICATE;
        }
        if (ClaimStagesExport::isDeactivated($row)) {
            return ClaimStagesExport::COLOR_SEPARATION;
        }
        return $index % 2 === 0
            ? ClaimStagesExport::COLOR_ALTERNATE_EVEN
            : ClaimStagesExport::COLOR_ALTERNATE_ODD;
    }

    // -------------------------------------------------------------------------
    // Style helpers
    // -------------------------------------------------------------------------

    private function applyTitleStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function applyHeaderStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => ClaimStagesExport::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    // -------------------------------------------------------------------------
    // Formatting helpers
    // -------------------------------------------------------------------------

    private function formatDate(?string $date): string
    {
        if (!$date || in_array($date, ['0000-00-00', '1970-01-01'], true)) return '';
        try {
            return \Carbon\Carbon::parse($date)->format('d-m-Y');
        } catch (\Exception $e) {
            return '';
        }
    }

    private function monthName(string $m): string
    {
        return \DateTime::createFromFormat('!m', $m)->format('F');
    }
}
