<?php

namespace App\Exports;

use App\Exports\Concerns\WithGeneratedTimestamp;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ClaimReportExport implements FromQuery, WithChunkReading, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use WithGeneratedTimestamp;
    protected $query;
    protected $filters;
    protected $columns;
    protected $totals;
    protected $sheetName;
    protected $protectSheets;
    protected $table;
    protected $rowNumber;
    protected $verifyAmtVersions;
    protected $apprAmtVersions;

    public function __construct($query, array $filters, array $columns, string $sheetName, bool $protectSheets, $table, array $verifyAmtVersions = ['current'], array $apprAmtVersions = ['current'])
    {
        $this->query = $query;
        $this->filters = $filters;

        // Ensure emp_status is positioned immediately after emp_name
        if (!in_array('emp_status', $columns)) {
            $empNamePos = array_search('emp_name', $columns);
            if ($empNamePos !== false) {
                array_splice($columns, $empNamePos + 1, 0, 'emp_status');
            } else {
                $columns[] = 'emp_status';
            }
        } else {
            $statusKey = array_search('emp_status', $columns);
            unset($columns[$statusKey]);
            $columns = array_values($columns);
            $empNamePos = array_search('emp_name', $columns);
            if ($empNamePos !== false) {
                array_splice($columns, $empNamePos + 1, 0, 'emp_status');
            } else {
                $columns[] = 'emp_status';
            }
        }
        $this->columns = $columns;

        $this->totals = [];
        $this->sheetName = $sheetName;
        $this->protectSheets = $protectSheets;
        $this->table = $table;
        $this->rowNumber = 0;
        $this->verifyAmtVersions = $verifyAmtVersions;
        $this->apprAmtVersions = $apprAmtVersions;
    }

    public function query(): QueryBuilder|EloquentBuilder|Relation
    {

        return $this->query;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function headings(): array
    {
        $headingsMap = [
            'exp_id' => 'Exp Id',
            'claim_id' => 'Claim ID',
            'claim_type' => 'Claim Type',
            'claim_status' => 'Claim Status',
            'emp_name' => 'Employee Name',
            'emp_status' => 'Employee Status',
            'emp_code' => 'Employee Code',
            'grade' => 'Grade',
            'function' => 'Function',
            'vertical' => 'Vertical',
            'department' => 'Department',
            'sub_department' => 'Sub Department',
            'policy' => 'Policy',
            'vehicle_type' => 'Vehicle Type',
            'month' => 'Month',
            'upload_date' => 'Upload Date',
            'bill_date' => 'Bill Date',
            'FilledAmt' => 'Filled Amount',
            'FilledDate' => 'Filled Date',
            'FilledByName' => 'Filled By',
            'odomtr_opening' => 'Odometer Opening',
            'odomtr_closing' => 'Odometer Closing',
            'TotKm' => 'Total KM',
            'cumulative_km' => 'Cumulative KM',
            'WType' => 'Wheeler Type',
            'RatePerKM' => 'Rate Per KM',
            'activity_category' => 'Activity Category',
            'activity_type' => 'Activity Type',
            'traill_no' => 'Trial No.',
            'crop' => 'Crop',
            'variety' => 'Variety',
            'VerifyTRemark' => 'Verify Remark',
            'VerifyDate' => 'Verify Date',
            'VerifyByName' => 'Verified By',
            'ApprTRemark' => 'Approval Remark',
            'ApprDate' => 'Approval Date',
            'ApprByName' => 'Approved By',
            'FinancedTRemark' => 'Finance Remark',
            'FinancedDate' => 'Finance Date',
            'FinancedByName' => 'Financed By',
            'backdate_odometer_status' => 'Backdated Odometer Status',
            'backdate_odometer_by' => 'Backdated Odometer Approved By',
            'backdate_odometer_date' => 'Backdated Odometer Approved Date',
            'delayed_days' => 'Delayed Days',
            'access_running_status' => 'Excess Running Status',
            'access_running_by' => 'Excess Running Approved By',
            'access_running_date' => 'Excess Running Approved Date',
            'participant_pax' => 'PAX',
            'participants_employee' => 'Participants (Employees / Details)',
            'participants_other' => 'Other Participants',
            'participants_activity' => 'Activity Participants',
        ];

        $headings = [];
        foreach ($this->columns as $column) {
            if ($column === 'VerifyAmt') {
                // Handle VerifyAmt based on selected versions
                if (in_array('current', $this->verifyAmtVersions) && in_array('previous', $this->verifyAmtVersions)) {
                    $headings[] = 'Verified Amount';
                    $headings[] = 'Verified Amount (Pre-Verification)';
                } elseif (in_array('previous', $this->verifyAmtVersions)) {
                    $headings[] = 'Verified Amount (Pre-Verification)';
                } else {
                    $headings[] = 'Verified Amount';
                }
            } elseif ($column === 'ApprAmt') {
                // Handle ApprAmt based on selected versions
                if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                    $headings[] = 'Approved Amount';
                    $headings[] = 'Approved Amount (Pre-Approval)';
                } elseif (in_array('previous', $this->apprAmtVersions)) {
                    $headings[] = 'Approved Amount (Pre-Approval)';
                } else {
                    $headings[] = 'Approved Amount';
                }
            } elseif ($column === 'FinancedTAmt') {
                // Handle FinancedTAmt based on selected versions
                if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                    $headings[] = 'Financed Amount';
                    $headings[] = 'Financed Amount (Pre-Finance)';
                } elseif (in_array('previous', $this->apprAmtVersions)) {
                    $headings[] = 'Financed Amount (Pre-Finance)';
                } else {
                    $headings[] = 'Financed Amount';
                }
            } else {
                $headings[] = $headingsMap[$column] ?? $column;
            }
        }

        return $headings;
    }

    public function map(mixed $row): array
    {
        static $monthMap = [
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
        static $wheelerMap = [2 => '2 Wheeler', 4 => '4 Wheeler'];
        static $cumulativeKmTracker = [];

        $this->rowNumber++;

        $data = [];
        foreach ($this->columns as $index => $column) {
            // Special handling for VerifyAmt, ApprAmt, FinancedTAmt when both versions selected
            if ($column === 'VerifyAmt') {
                if (in_array('current', $this->verifyAmtVersions) && in_array('previous', $this->verifyAmtVersions)) {
                    // Add both current and previous
                    $data[] = $row->VerifyTAmt ?? 0;
                    $data[] = $row->FilledTAmt ?? 0;
                    // Track totals
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->VerifyTAmt ?? 0);
                    $this->totals[$index + 0.5] = ($this->totals[$index + 0.5] ?? 0) + ($row->FilledTAmt ?? 0);
                } elseif (in_array('previous', $this->verifyAmtVersions)) {
                    $data[] = $row->FilledTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->FilledTAmt ?? 0);
                } else {
                    $data[] = $row->VerifyTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->VerifyTAmt ?? 0);
                }
                continue;
            }

            if ($column === 'ApprAmt') {
                if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                    // Add both current and previous
                    $data[] = $row->ApprTAmt ?? 0;
                    $data[] = $row->VerifyTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->ApprTAmt ?? 0);
                    $this->totals[$index + 0.5] = ($this->totals[$index + 0.5] ?? 0) + ($row->VerifyTAmt ?? 0);
                } elseif (in_array('previous', $this->apprAmtVersions)) {
                    $data[] = $row->VerifyTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->VerifyTAmt ?? 0);
                } else {
                    $data[] = $row->ApprTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->ApprTAmt ?? 0);
                }
                continue;
            }

            if ($column === 'FinancedTAmt') {
                if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                    // Add both current and previous
                    $data[] = $row->FinancedTAmt ?? 0;
                    $data[] = $row->ApprTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->FinancedTAmt ?? 0);
                    $this->totals[$index + 0.5] = ($this->totals[$index + 0.5] ?? 0) + ($row->ApprTAmt ?? 0);
                } elseif (in_array('previous', $this->apprAmtVersions)) {
                    $data[] = $row->ApprTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->ApprTAmt ?? 0);
                } else {
                    $data[] = $row->FinancedTAmt ?? 0;
                    $this->totals[$index] = ($this->totals[$index] ?? 0) + ($row->FinancedTAmt ?? 0);
                }
                continue;
            }

            $value = match ($column) {
                'exp_id' => $row->ExpId ?? '',
                'claim_id' => $row->ClaimId ?? '',
                'claim_type' => $row->claim_type_name ?? '',
                'claim_status' => $row->ClaimStatus ?? '',
                'emp_name' => $row->employee_name ?? '',
                'emp_status' => !empty($row->emp_status) ? $row->emp_status : getEmployeeStatus($row->EmpStatus ?? null, $row->EmployeeID ?? $row->emp_id ?? null),
                'emp_code' => \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code ?? ''),
                'grade' => $row->grade ?? '',
                'function' => $row->function_name ?? '',
                'vertical' => $row->vertical_name ?? '',
                'department' => $row->department_name ?? '',
                'sub_department' => $row->sub_department_name ?? '',
                'policy' => $row->policy_name ?? '',
                'vehicle_type' => $row->vehicle_type ?? '',
                'month' => $monthMap[$row->ClaimMonth] ?? '',
                'upload_date' => $row->CrDate ? (new \DateTime($row->CrDate))->format('d-m-Y') : '',
                'bill_date' => $row->BillDate ? (new \DateTime($row->BillDate))->format('d-m-Y') : '',
                'FilledAmt' => $row->FilledTAmt ?? '',
                'FilledDate' => $row->FilledDate ? (new \DateTime($row->FilledDate))->format('d-m-Y') : '',
                'FilledByName' => $row->FilledByName ?? '',
                'odomtr_opening' => $row->odomtr_opening ?? '',
                'odomtr_closing' => $row->odomtr_closing ?? '',
                'TotKm' => $row->TotKm ?? 0,
                'cumulative_km' => $this->calculateCumulativeKm($row, $cumulativeKmTracker),
                'WType' => $wheelerMap[$row->WType] ?? '',
                'RatePerKM' => $row->RatePerKM ?? 0,
                'activity_category' => $row->activity_category ?? '',
                'activity_type' => $row->activity_type ?? '',
                'traill_no' => $row->traill_no ?? '',
                'crop' => $row->crop ?? '',
                'variety' => $row->variety ?? '',
                'VerifyTRemark' => $row->VerifyTRemark ?? '',
                'VerifyDate' => $row->VerifyDate ? (new \DateTime($row->VerifyDate))->format('d-m-Y') : '',
                'VerifyByName' => $row->VerifyByName ?? '',
                'ApprTRemark' => $row->ApprTRemark ?? '',
                'ApprDate' => $row->ApprDate ? (new \DateTime($row->ApprDate))->format('d-m-Y') : '',
                'ApprByName' => $row->ApprByName ?? '',
                'FinancedTRemark' => $row->FinancedTRemark ?? '',
                'FinancedDate' => $row->FinancedDate ? (new \DateTime($row->FinancedDate))->format('d-m-Y') : '',
                'FinancedByName' => $row->FinancedByName ?? '',
                'backdate_odometer_status' => $row->backdate_odometer_status ?? '',
                'backdate_odometer_by' => $row->backdate_odometer_by ?? '',
                'delayed_days' => ($row->CrDate && $row->BillDate) ? (new \DateTime($row->CrDate))->diff(new \DateTime($row->BillDate))->format('%r%a') : '',
                'backdate_odometer_date' => $row->backdate_odometer_date ? (new \DateTime($row->backdate_odometer_date))->format('d-m-Y') : '',
                'access_running_status' => $row->access_running_status ?? '',
                'access_running_by' => $row->access_running_by ?? '',
                'access_running_date' => $row->access_running_date ? (new \DateTime($row->access_running_date))->format('d-m-Y') : '',
                'participant_pax' => $row->participant_pax ?? '',
                'participants_employee' => $row->participants_employee ?? '',
                'participants_other' => $this->formatOtherParticipants($row->participants_other ?? ''),
                'participants_activity' => $row->participants_activity ?? '',
                default => '',
            };

            if (in_array($column, ['FilledAmt', 'TotKm', 'cumulative_km', 'RatePerKM']) && is_numeric($value)) {
                $this->totals[$index] = ($this->totals[$index] ?? 0) + $value;
            }

            $data[] = $value;
        }

        return $data;
    }

    /** "Name (Category)" list from the stored other-participant JSON / text. */
    private function formatOtherParticipants($raw): string
    {
        if (trim((string) $raw) === '') {
            return '';
        }
        $list = \App\Helpers\CommonHelper::parseOtherParticipants((string) $raw);

        return implode('; ', array_map(function ($p) {
            $name = is_array($p) ? trim((string) ($p['name'] ?? '')) : trim((string) $p);
            $category = is_array($p) ? trim((string) ($p['category'] ?? '')) : '';

            return $category !== '' ? "{$name} ({$category})" : $name;
        }, $list));
    }

    private function calculateCumulativeKm($row, &$cumulativeKmTracker)
    {
        // If cumulative_km is already calculated from database, use it
        if (isset($row->cumulative_km) && $row->cumulative_km > 0) {
            return $row->cumulative_km;
        }

        // For access_running filter, calculate cumulative KM manually
        $empCode = $row->employee_code ?? '';
        $month = $row->ClaimMonth ?? 0;
        $currentKm = $row->TotKm ?? 0;

        $key = $empCode . '_' . $month;

        if (!isset($cumulativeKmTracker[$key])) {
            $cumulativeKmTracker[$key] = 0;
        }

        $cumulativeKmTracker[$key] += $currentKm;

        return $cumulativeKmTracker[$key];
    }

    /**
     * Calculate actual column count (accounting for dual-version columns)
     */
    private function getActualColumnCount(): int
    {
        $count = count($this->columns);

        // Add extra columns for dual-version selections
        foreach ($this->columns as $column) {
            if (
                $column === 'VerifyAmt' &&
                in_array('current', $this->verifyAmtVersions) &&
                in_array('previous', $this->verifyAmtVersions)
            ) {
                $count++;
            }
            if (
                ($column === 'ApprAmt' || $column === 'FinancedTAmt') &&
                in_array('current', $this->apprAmtVersions) &&
                in_array('previous', $this->apprAmtVersions)
            ) {
                $count++;
            }
        }

        return $count;
    }

    public function getSelectedColumns()
    {
        $columnMap = [
            'exp_id' => 'e.ExpId',
            'claim_id' => 'e.ClaimId',
            'claim_type' => 'ct.ClaimName AS claim_type_name',
            'claim_status' => 'e.ClaimStatus',
            'emp_name' => "CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) AS employee_name",
            'emp_status' => 'emp.EmpStatus AS emp_status',
            'emp_code' => 'emp.EmpCode AS employee_code',
            'grade' => 'g.grade_name AS grade',
            'function' => 'f.function_name',
            'vertical' => 'v.vertical_name',
            'department' => 'd.department_name',
            'sub_department' => 'sd.sub_department_name',
            'policy' => 'p.PolicyName AS policy_name',
            'vehicle_type' => 'ee.VehicleType AS vehicle_type',
            'month' => 'e.ClaimMonth',
            'upload_date' => 'e.CrDate',
            'bill_date' => 'e.BillDate',
            'FilledAmt' => 'e.FilledTAmt',
            'FilledDate' => 'e.FilledDate',
            'odomtr_opening' => 'e.odomtr_opening',
            'odomtr_closing' => 'e.odomtr_closing',
            'TotKm' => 'e.TotKm',
            'cumulative_km' => 'e.cumulative_km',
            'WType' => 'e.WType',
            'RatePerKM' => 'e.RatePerKM',
            'activity_category' => 'adv_activity_categories.activity_category',
            'activity_type' => 'adv_activity_types.activity_type',
            'traill_no' => 'e.traill_no',
            'crop' => 'c.crop',
            'variety' => 'vr.variety',
            'VerifyAmt' => 'e.VerifyTAmt',
            'VerifyTRemark' => 'e.VerifyTRemark',
            'VerifyDate' => 'e.VerifyDate',
            'VerifyByName' => "CONCAT(verify_emp.Fname, ' ', COALESCE(verify_emp.Sname, ''), ' ', verify_emp.Lname) AS VerifyByName",
            'ApprAmt' => 'e.ApprTAmt',
            'ApprTRemark' => 'e.ApprTRemark',
            'ApprDate' => 'e.ApprDate',
            'ApprByName' => "CONCAT(appr_emp.Fname, ' ', COALESCE(appr_emp.Sname, ''), ' ', appr_emp.Lname) AS ApprByName",
            'FinancedTAmt' => 'e.FinancedTAmt',
            'FinancedTRemark' => 'e.FinancedTRemark',
            'FinancedDate' => 'e.FinancedDate',
            'FinancedByName' => "CONCAT(finance_emp.Fname, ' ', COALESCE(finance_emp.Sname, ''), ' ', finance_emp.Lname) AS FinancedByName",
            'backdate_odometer_status' => 'e.backdate_odometer_status',
            'backdate_odometer_date' => 'e.backdate_odometer_date',
            'access_running_status' => 'e.access_running_status',
            'access_running_by' => 'e.access_running_by',
            'access_running_date' => 'e.access_running_date'
        ];

        return array_filter(
            array_map(fn($column) => $columnMap[$column] ?? null, $this->columns),
            fn($value) => !is_null($value)
        );
    }

    public function styles(Worksheet $sheet): ?array
    {
        $actualColumnCount = $this->getActualColumnCount();
        $lastColumn = Coordinate::stringFromColumnIndex($actualColumnCount);

        // Header styling (single batch operation)
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(20);

        // Set clean fixed column widths instead of calculating font metrics on large datasets
        foreach (range(1, $actualColumnCount) as $colIndex) {
            $columnLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setWidth(18);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->insertTimestampRow($sheet);
                $lastRow = $sheet->getHighestRow();
                $actualColumnCount = $this->getActualColumnCount();
                $lastColumn = Coordinate::stringFromColumnIndex($actualColumnCount);
                $totalRow = $lastRow + 1;



                $sheet->setCellValue("A{$totalRow}", 'Total');

                // Place totals in the correct columns
                $colOffset = 1;
                foreach ($this->totals as $index => $total) {
                    // Handle fractional indexes for dual columns
                    if (is_float($index)) {
                        $colIndex = floor($index) + $colOffset + 1;
                    } else {
                        $colIndex = $index + $colOffset;
                    }
                    $columnLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue("{$columnLetter}{$totalRow}", $total);
                }




                $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => 'right'],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFFE082'],

                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);



                if ($this->protectSheets) {
                    $sheet->getProtection()->setSheet(true);
                    $sheet->getProtection()->setPassword('xai2025');
                }
            },
        ];
    }
}