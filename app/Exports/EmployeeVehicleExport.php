<?php

namespace App\Exports;

use App\Http\Controllers\admin\EmployeeVehicleController;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class EmployeeVehicleExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $query;
    protected $selectedColumns;
    protected $activeDefinitions;
    protected $policyTableCache = [];

    public function __construct($query, array $selectedColumns = [])
    {
        $this->query = $query;
        $allDefs = $this->getColumnDefinitions();

        if (empty($selectedColumns)) {
            $selectedColumns = array_keys(array_filter($allDefs, function ($def) {
                return $def['default'] ?? false;
            }));
        }

        // Filter and order selected column definitions
        $this->selectedColumns = $selectedColumns;
        $this->activeDefinitions = [];
        foreach ($selectedColumns as $colKey) {
            if (isset($allDefs[$colKey])) {
                $this->activeDefinitions[$colKey] = $allDefs[$colKey];
            }
        }

        // If still empty fallback to all
        if (empty($this->activeDefinitions)) {
            $this->activeDefinitions = $allDefs;
            $this->selectedColumns = array_keys($allDefs);
        }
    }

    protected function getColumnDefinitions(): array
    {
        return [
            // Basic Info
            'sr_no' => [
                'group' => 'Employee Info',
                'heading' => 'Sr. No',
                'default' => true,
                'value' => fn($row, $sr, $m) => $sr,
            ],
            'emp_code' => [
                'group' => 'Employee Info',
                'heading' => 'EC',
                'default' => true,
                'value' => fn($row, $sr, $m) => \App\Helpers\CommonHelper::formatEmployeeCode($row->EmpCode),
            ],
            'emp_name' => [
                'group' => 'Employee Info',
                'heading' => 'Emp Name',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->Name,
            ],
            'department' => [
                'group' => 'Employee Info',
                'heading' => 'Department',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->department_name ?? '',
            ],
            'grade' => [
                'group' => 'Employee Info',
                'heading' => 'Grade',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->grade_name ?? '',
            ],
            'emp_status' => [
                'group' => 'Employee Info',
                'heading' => 'Status',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->EmpStatus === 'A' ? 'Active' : 'Inactive',
            ],

            // Vehicle Info
            'purchase_date' => [
                'group' => 'Vehicle Info',
                'heading' => 'Purchase Date',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->purchase_date ? date('d-m-Y', strtotime($row->purchase_date)) : '',
            ],
            'oldnew' => [
                'group' => 'Vehicle Info',
                'heading' => 'Old / New',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->oldnew ?? '',
            ],
            'policyentrydate' => [
                'group' => 'Vehicle Info',
                'heading' => 'Policy Entry Date',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->policyentrydate ? date('d-m-Y', strtotime($row->policyentrydate)) : '',
            ],
            'registration_date' => [
                'group' => 'Vehicle Info',
                'heading' => 'Reg Date',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->registration_date ? date('d-m-Y', strtotime($row->registration_date)) : '',
            ],
            'vehicle_age' => [
                'group' => 'Vehicle Info',
                'heading' => 'Vehicle Age',
                'default' => true,
                'value' => fn($row, $sr, $m) => $m['calc']['vehicle_age_text'] ?? '',
            ],
            'vehicle_type' => [
                'group' => 'Vehicle Info',
                'heading' => 'Vehicle Type',
                'default' => true,
                'value' => fn($row, $sr, $m) => in_array($row->VehiclePolicy, [1, 2, 4, 5, 6, 7, 11, 12, 13]) ? '4 Wheeler' : ($row->VehiclePolicy > 0 ? '2 Wheeler' : ''),
            ],
            'reg_no' => [
                'group' => 'Vehicle Info',
                'heading' => 'Registration No',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->registration_no ?? '',
            ],
            'brand_model' => [
                'group' => 'Vehicle Info',
                'heading' => 'Brand & Model',
                'default' => false,
                'value' => fn($row, $sr, $m) => implode(' - ', array_filter([$row->brand ?? '', $row->model_name ?? ''])),
            ],
            'fuel_type' => [
                'group' => 'Vehicle Info',
                'heading' => 'Fuel Type',
                'default' => false,
                'value' => fn($row, $sr, $m) => ucfirst($row->fuel_type ?? ''),
            ],
            'vehicle_price' => [
                'group' => 'Vehicle Info',
                'heading' => 'Current Vehicle Value',
                'default' => true,
                'value' => fn($row, $sr, $m) => $m['calc']['vehicle_value_formatted'] ?? (((float)($row->vehiclevalue ?? 0) > 0) ? '₹' . number_format((float)$row->vehiclevalue) : ($row->vehicle_price ? '₹' . number_format((float)$row->vehicle_price) : '')),
            ],

            // Policy Details & Limits
            'policy_name' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Policy Name',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->PolicyName ?? '',
            ],
            'plan' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Plan',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->Plan ?? '',
            ],
            'flat_2w' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Flat 2W',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->flat_2w ?? '',
            ],
            'flat_4w' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Flat 4W',
                'default' => true,
                'value' => fn($row, $sr, $m) => $row->flat_4w ?? '',
            ],
            'limit_2w_km' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Limit 2W KM/M',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->limit_2w_km ?? '',
            ],
            'limit_4w_km' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Limit 4W KM/M',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->limit_4w_km ?? '',
            ],
            'policy_vehicle_value' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Pol Vehicle Value',
                'default' => false,
                'value' => fn($row, $sr, $m) => $m['value'] ?? '',
            ],
            'policy_tenure' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Policy Tenure',
                'default' => false,
                'value' => fn($row, $sr, $m) => $m['tenure'] ?? '',
            ],
            'max_km_month' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Max KM / Month',
                'default' => false,
                'value' => fn($row, $sr, $m) => $m['max_km'] ?? '',
            ],
            'reimbursement_rate' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Current Rate',
                'default' => true,
                'value' => function ($row, $sr, $m) {
                    if (!empty($m['calc'])) {
                        $calc = $m['calc'];
                        if (!empty($calc['is_value_downgraded']) && $calc['assigned_rate'] > $calc['current_rate']) {
                            return ($calc['current_rate_formatted'] ?? '') . ' (Assigned: ' . ($calc['assigned_rate_formatted'] ?? '') . ')';
                        }
                        return $calc['current_rate_formatted'] ?? ($m['rate'] ?? '');
                    }
                    return $m['rate'] ?? '';
                },
            ],
            'rate_reason' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Rate Reason / Rule',
                'default' => true,
                'value' => fn($row, $sr, $m) => $m['calc']['rate_reason'] ?? '',
            ],
            'with_driver' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'With Driver',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->WithDriver ?? '',
            ],
            'advance_com' => [
                'group' => 'Policy Details & Limits',
                'heading' => 'Advance Company',
                'default' => false,
                'value' => fn($row, $sr, $m) => $row->AdvanceCom ?? '',
            ],

            // Document Check
            'doc_status' => [
                'group' => 'Document Check',
                'heading' => 'Doc Summary',
                'default' => true,
                'value' => function ($row) {
                    $docs = [
                        !empty($row->rc_file),
                        !empty($row->insurance),
                        !empty($row->dl_file),
                        !empty($row->vehicle_image),
                        !empty($row->odo_meter),
                        !empty($row->bill_file),
                    ];
                    return count(array_filter($docs)) . ' / 6';
                },
            ],
            'rc_check' => [
                'group' => 'Document Check',
                'heading' => 'RC File',
                'default' => false,
                'value' => fn($row) => !empty($row->rc_file) ? 'Available' : 'Missing',
            ],
            'ins_check' => [
                'group' => 'Document Check',
                'heading' => 'Insurance',
                'default' => false,
                'value' => fn($row) => !empty($row->insurance) ? 'Available' : 'Missing',
            ],
            'dl_check' => [
                'group' => 'Document Check',
                'heading' => 'DL File',
                'default' => false,
                'value' => fn($row) => !empty($row->dl_file) ? 'Available' : 'Missing',
            ],
            'img_check' => [
                'group' => 'Document Check',
                'heading' => 'Vehicle Photo',
                'default' => false,
                'value' => fn($row) => !empty($row->vehicle_image) ? 'Available' : 'Missing',
            ],
            'odo_check' => [
                'group' => 'Document Check',
                'heading' => 'Odometer Photo',
                'default' => false,
                'value' => fn($row) => !empty($row->odo_meter) ? 'Available' : 'Missing',
            ],
            'bill_check' => [
                'group' => 'Document Check',
                'heading' => 'Purchase Bill',
                'default' => false,
                'value' => fn($row) => !empty($row->bill_file) ? 'Available' : 'Missing',
            ],
        ];
    }

    protected function getPolicyMetrics($row, $policyId, $gradeId): array
    {
        if ($policyId && !isset($this->policyTableCache[$policyId])) {
            $policyTable = "hrm_master_eligibility_policy_tbl{$policyId}";
            $tableExists = DB::connection('hrims')->select("SHOW TABLES LIKE '{$policyTable}'");
            if (!empty($tableExists)) {
                $this->policyTableCache[$policyId] = DB::connection('hrims')->table($policyTable)->get()->keyBy('GradeId');
            } else {
                $this->policyTableCache[$policyId] = collect();
            }
        }

        $policyData = $this->policyTableCache[$policyId] ?? collect();
        $calc = EmployeeVehicleController::calculateRateAndExplanation($row, $policyData);

        $rule = $policyData[$gradeId] ?? null;
        $rawVal = $rule ? ($rule->Fn2 ?? $rule->Fn14 ?? null) : null;
        $val = ($rawVal && is_numeric($rawVal)) ? number_format((float)$rawVal) : ($rawVal ?? '');
        $tenure = $rule->Fn3 ?? '';
        $maxKm = $rule->Fn8 ?? $rule->Fn9 ?? $rule->Fn32 ?? '';
        $rate = $rule->Fn6 ?? '';

        return [
            'value' => $val,
            'tenure' => $tenure,
            'max_km' => $maxKm,
            'rate' => $rate,
            'calc' => $calc,
        ];
    }

    public function collection(): Enumerable
    {
        $data = $this->query->orderBy('e.EmpCode', 'asc')->get();
        $sr = 1;

        return $data->map(function ($row) use (&$sr) {
            $metrics = null;
            $rowObj = [];

            // Only fetch metrics if any policy metric column is active
            $needsMetrics = isset($this->activeDefinitions['policy_vehicle_value'])
                || isset($this->activeDefinitions['policy_tenure'])
                || isset($this->activeDefinitions['max_km_month'])
                || isset($this->activeDefinitions['reimbursement_rate'])
                || isset($this->activeDefinitions['vehicle_age'])
                || isset($this->activeDefinitions['rate_reason']);

            if ($needsMetrics) {
                $metrics = $this->getPolicyMetrics($row, $row->VehiclePolicy, $row->grade_id);
            }

            foreach ($this->activeDefinitions as $colKey => $def) {
                $closure = $def['value'];
                $rowObj[$colKey] = $closure($row, $sr, $metrics);
            }

            $sr++;
            return $rowObj;
        });
    }

    public function headings(): array
    {
        $row1 = [];
        $row2 = [];

        foreach ($this->activeDefinitions as $def) {
            $row1[] = $def['group'];
            $row2[] = $def['heading'];
        }

        return [$row1, $row2];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastRow = $sheet->getHighestRow();
        $colCount = count($this->activeDefinitions);
        $lastColLetter = Coordinate::stringFromColumnIndex($colCount);

        // Group merging in row 1
        $groupSpans = [];
        $colIndex = 1;
        foreach ($this->activeDefinitions as $def) {
            $group = $def['group'];
            if (!isset($groupSpans[$group])) {
                $groupSpans[$group] = ['start' => $colIndex, 'end' => $colIndex];
            } else {
                $groupSpans[$group]['end'] = $colIndex;
            }
            $colIndex++;
        }

        foreach ($groupSpans as $group => $span) {
            $startLetter = Coordinate::stringFromColumnIndex($span['start']);
            $endLetter = Coordinate::stringFromColumnIndex($span['end']);
            if ($span['start'] < $span['end']) {
                $sheet->mergeCells("{$startLetter}1:{$endLetter}1");
            }
        }

        // Style the double header rows (Rows 1 & 2)
        $sheet->getStyle("A1:{$lastColLetter}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(26);

        // Data Rows Styling
        for ($row = 3; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
                ]);
            }
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Total Records Summary Row
        $totalRow = $lastRow + 1;
        $sheet->mergeCells("A{$totalRow}:{$lastColLetter}{$totalRow}");
        $recordCount = max(0, $lastRow - 2);
        $sheet->setCellValue("A{$totalRow}", "Total Records: {$recordCount}");
        $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE082']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getRowDimension($totalRow)->setRowHeight(24);

        return [];
    }
}

