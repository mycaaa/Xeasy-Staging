<?php

namespace App\Exports;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

class HeadWiseClaimReport implements FromQuery, WithChunkReading, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
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

    /**
     * In-memory cache for batch pre-fetched breakdown records
     */
    protected $g7Cache = [];
    protected $detailsCache = [];

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
        $this->apprAmtVersions   = $apprAmtVersions;
    }

    /**
     * Return the underlying query. Laravel-Excel 4 types query() as
     * Query\Builder|Eloquent\Builder|Relation, so the chunk-intercepting proxy
     * used under 3.1 is no longer allowed; the per-chunk breakdown prefetch
     * now runs in prepareRows(), which Sheet::appendRows() calls once per
     * chunk before map().
     */
    public function query(): QueryBuilder|EloquentBuilder|Relation
    {
        return $this->query;
    }

    /**
     * Called by Laravel-Excel with each query chunk before the rows are mapped.
     */
    public function prepareRows($rows)
    {
        $this->prefetchBreakdowns($rows);

        return $rows;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function headings(): array
    {
        $headingsMap = [
            'emp_name' => 'Employee Name',
            'emp_status' => 'Employee Status',
            'emp_code' => 'Employee Code',
            'grade' => 'Grade',
            'exp_id' => 'Exp Id',
            'claim_type' => 'Claim Type',
            'expense_head' => 'Expense Head',
            'claim_status' => 'Claim Status',
            'month' => 'Month',
            'upload_date' => 'Upload Date',
            'bill_date' => 'Bill Date',
            'FilledAmt' => 'Filled Amount',
            'VerifyTRemark' => 'Verify Remark',
            'VerifyDate' => 'Verify Date',
            'ApprTRemark' => 'Approval Remark',
            'ApprDate' => 'Approval Date',
            'FinancedTRemark' => 'Finance Remark',
            'FinancedDate' => 'Finance Date',
            'claim_id' => 'Claim ID',
            'function' => 'Function',
            'vertical' => 'Vertical',
            'department' => 'Department',
            'sub_department' => 'Sub Department',
            'policy' => 'Policy',
            'vehicle_type' => 'Vehicle Type',
            'FilledDate' => 'Filled Date',
            'FilledByName' => 'Filled By',
            'odomtr_opening' => 'Odometer Opening',
            'odomtr_closing' => 'Odometer Closing',
            'TotKm' => 'Total KM',
            'WType' => 'Wheeler Type',
            'RatePerKM' => 'Rate Per KM',
            'activity_category' => 'Activity Category',
            'activity_type' => 'Activity Type',
            'traill_no' => 'Trial No.',
            'crop' => 'Crop',
            'variety' => 'Variety',
        ];

        $headings = [];
        foreach ($this->columns as $column) {
            if ($column === 'VerifyAmt') {
                if (in_array('current', $this->verifyAmtVersions) && in_array('previous', $this->verifyAmtVersions)) {
                    $headings[] = 'Verified Amount';
                    $headings[] = 'Verified Amount (Pre-Verification)';
                } elseif (in_array('previous', $this->verifyAmtVersions)) {
                    $headings[] = 'Verified Amount (Pre-Verification)';
                } else {
                    $headings[] = 'Verified Amount';
                }
            } elseif ($column === 'ApprAmt') {
                if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                    $headings[] = 'Approved Amount';
                    $headings[] = 'Approved Amount (Pre-Approval)';
                } elseif (in_array('previous', $this->apprAmtVersions)) {
                    $headings[] = 'Approved Amount (Pre-Approval)';
                } else {
                    $headings[] = 'Approved Amount';
                }
            } elseif ($column === 'FinancedTAmt') {
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
        
        // Ensure expense_head is always included
        if (!in_array('Expense Head', $headings)) {
            $claimTypeIndex = array_search('Claim Type', $headings);
            if ($claimTypeIndex !== false) {
                array_splice($headings, $claimTypeIndex + 1, 0, 'Expense Head');
            } else {
                $headings[] = 'Expense Head';
            }
        }

        return $headings;
    }

    protected function getG7HeadMap(): array
    {
        return [
            'godown_rent' => 'Godown Rent',
            'snacks' => 'Snacks',
            'conveyance_for_meeting_participants' => 'Conveyance (For meeting participants)',
            'business_entertainment' => 'Business Entertainment',
            'product_list' => 'Product List',
            'hrv_employee_team' => 'HRV (Employee / Team)',
            'food' => 'Food',
            'gifts' => 'Gifts',
            'hall_tent_chair_sound_system_projector' => 'Hall / Tent / Chair / Sound System / Projector',
            'misc_labour_chuna_rope_etc' => 'Misc (Labour, Chuna, rope etc)',
            'merchandise_cost_branding_transport' => 'Merchandise cost (Branding & Transport)',
            'hired_vehicle' => 'Hired Vehicle',
            'other' => 'Other',
            'no_of_farmers' => 'No. of Farmers',
            'dealerstrade_partners' => 'Dealers/Trade Partners',
            'seed_purchase' => 'Seed Purchase',
            'labour_payment' => 'Labour Payment',
            'fertilizers_pesticides' => 'Fertilizers & Pesticides',
            'loading_unloading' => 'Loading & Unloading',
            'distribution_charges' => 'Distribution charges',
            'crop_vertical' => 'Crop Vertical',
        ];
    }

    protected function getYearPrefix(): string
    {
        if (preg_match('/^(y\d+)_/i', (string) $this->table, $matches)) {
            return $matches[1];
        }
        return 'y' . (session('year_id') ?: '7');
    }

    protected static $tableExistsCache = [];

    protected function tableExists(string $conn, string $table): bool
    {
        $cacheKey = "{$conn}.{$table}";
        if (!isset(self::$tableExistsCache[$cacheKey])) {
            try {
                self::$tableExistsCache[$cacheKey] = \Illuminate\Support\Facades\Schema::connection($conn)->hasTable($table);
            } catch (\Throwable $e) {
                self::$tableExistsCache[$cacheKey] = false;
            }
        }
        return self::$tableExistsCache[$cacheKey];
    }

    protected function getExpenseConnection(): string
    {
        return session('expense_connection') ?: 'expense';
    }

    /**
     * Batch pre-fetch breakdowns for all claims in the current chunk
     */
    protected function prefetchBreakdowns($chunk): void
    {
        $this->g7Cache = [];
        $this->detailsCache = [];

        $allExpIds = [];
        $g7ExpIds = [];

        foreach ($chunk as $row) {
            if (!empty($row->ExpId)) {
                $allExpIds[] = $row->ExpId;
                // Pre-initialize both caches with null / empty array for every ExpId in this chunk
                $this->g7Cache[$row->ExpId] = null;
                $this->detailsCache[$row->ExpId] = [];

                // Only query G7 fill data for claims that could be G7 claims (ClaimId == 7)
                if (isset($row->ClaimId) && (int) $row->ClaimId === 7) {
                    $g7ExpIds[] = $row->ExpId;
                }
            }
        }

        if (empty($allExpIds)) {
            return;
        }

        $conn = $this->getExpenseConnection();
        $yearPrefix = $this->getYearPrefix();
        $g7Table = "{$yearPrefix}_g7_expensefilldata";
        $detailsTable = "{$yearPrefix}_expenseclaimsdetails";

        // 1. Batch fetch G7 rows for claims in this chunk ONLY if table exists and G7 claims exist
        if (!empty($g7ExpIds) && $this->tableExists($conn, $g7Table)) {
            try {
                $g7Rows = DB::connection($conn)->table($g7Table)
                    ->whereIn('ExpId', $g7ExpIds)
                    ->get();
                foreach ($g7Rows as $g7Row) {
                    $this->g7Cache[$g7Row->ExpId] = $g7Row;
                }
            } catch (\Throwable $e) {}
        }

        // 2. Batch fetch expenseclaimsdetails for claims in this chunk ONLY if table exists
        if (!empty($allExpIds) && $this->tableExists($conn, $detailsTable)) {
            try {
                $detailRows = DB::connection($conn)->table($detailsTable)
                    ->whereIn('ExpId', $allExpIds)
                    ->get();
                foreach ($detailRows as $detail) {
                    $this->detailsCache[$detail->ExpId][] = $detail;
                }
            } catch (\Throwable $e) {}
        }
    }

    protected function getClaimBreakdown($row): array
    {
        $conn = $this->getExpenseConnection();
        $yearPrefix = $this->getYearPrefix();
        $g7Table = "{$yearPrefix}_g7_expensefilldata";
        $detailsTable = "{$yearPrefix}_expenseclaimsdetails";

        // 1. Check G7 fill data (from prefetch cache, fallback to query ONLY if missing from cache and table exists)
        $g7Row = $this->g7Cache[$row->ExpId] ?? null;
        if (!$g7Row && !array_key_exists($row->ExpId, $this->g7Cache) && isset($row->ClaimId) && (int) $row->ClaimId === 7 && $this->tableExists($conn, $g7Table)) {
            try {
                $g7Row = DB::connection($conn)->table($g7Table)->where('ExpId', $row->ExpId)->first();
                $this->g7Cache[$row->ExpId] = $g7Row;
            } catch (\Throwable $e) {
                $this->g7Cache[$row->ExpId] = null;
            }
        }

        if ($g7Row) {
            $g7HeadMap = $this->getG7HeadMap();
            $g7Heads = [];
            foreach ($g7HeadMap as $col => $label) {
                if (isset($g7Row->{$col}) && is_numeric($g7Row->{$col}) && floatval($g7Row->{$col}) > 0) {
                    $g7Heads[] = [
                        'expense_head' => $label,
                        'FilledAmt' => floatval($g7Row->{$col}),
                        'VerifyAmt' => isset($g7Row->{"ver_{$col}"}) && is_numeric($g7Row->{"ver_{$col}"}) ? floatval($g7Row->{"ver_{$col}"}) : floatval($g7Row->{$col}),
                        'VerifyTRemark' => $g7Row->{"ver_remark_{$col}"} ?? $row->VerifyTRemark ?? '',
                        'ApprAmt' => isset($g7Row->{"app_{$col}"}) && is_numeric($g7Row->{"app_{$col}"}) ? floatval($g7Row->{"app_{$col}"}) : floatval($g7Row->{$col}),
                        'ApprTRemark' => $g7Row->{"app_remark_{$col}"} ?? $row->ApprTRemark ?? '',
                        'FinancedTAmt' => isset($g7Row->{"pay_{$col}"}) && is_numeric($g7Row->{"pay_{$col}"}) ? floatval($g7Row->{"pay_{$col}"}) : floatval($g7Row->{$col}),
                        'FinancedTRemark' => $row->FinancedTRemark ?? '',
                    ];
                }
            }
            if (!empty($g7Heads)) {
                return $g7Heads;
            }
        }

        // 2. Check details table (from prefetch cache, fallback to query ONLY if missing from cache and table exists)
        $details = $this->detailsCache[$row->ExpId] ?? null;
        if ($details === null && !array_key_exists($row->ExpId, $this->detailsCache) && $this->tableExists($conn, $detailsTable)) {
            try {
                $details = DB::connection($conn)->table($detailsTable)->where('ExpId', $row->ExpId)->get();
                $this->detailsCache[$row->ExpId] = $details;
            } catch (\Throwable $e) {
                $this->detailsCache[$row->ExpId] = [];
            }
        }

        if (!empty($details)) {
            $detailHeads = [];
            foreach ($details as $detail) {
                $detailHeads[] = [
                    'expense_head' => $detail->Title ?? '',
                    'FilledAmt' => floatval($detail->Amount ?? 0),
                    'VerifyAmt' => isset($detail->VerifierEditAmount) && is_numeric($detail->VerifierEditAmount) ? floatval($detail->VerifierEditAmount) : floatval($detail->Amount ?? 0),
                    'VerifyTRemark' => $detail->VerifierRemark ?? $row->VerifyTRemark ?? '',
                    'ApprAmt' => isset($detail->ApproverEditAmount) && is_numeric($detail->ApproverEditAmount) ? floatval($detail->ApproverEditAmount) : floatval($detail->Amount ?? 0),
                    'ApprTRemark' => $detail->ApproverRemark ?? $row->ApprTRemark ?? '',
                    'FinancedTAmt' => isset($detail->FinanceEditAmount) && is_numeric($detail->FinanceEditAmount) ? floatval($detail->FinanceEditAmount) : floatval($detail->Amount ?? 0),
                    'FinancedTRemark' => $detail->FinanceRemark ?? $row->FinancedTRemark ?? '',
                ];
            }
            if (!empty($detailHeads)) {
                return $detailHeads;
            }
        }

        // 3. Fallback: single row using main claim values
        return [
            [
                'expense_head' => $row->expense_head ?? $row->claim_type_name ?? '',
                'FilledAmt' => floatval($row->FilledTAmt ?? 0),
                'VerifyAmt' => floatval($row->VerifyTAmt ?? 0),
                'VerifyTRemark' => $row->VerifyTRemark ?? '',
                'ApprAmt' => floatval($row->ApprTAmt ?? 0),
                'ApprTRemark' => $row->ApprTRemark ?? '',
                'FinancedTAmt' => floatval($row->FinancedTAmt ?? 0),
                'FinancedTRemark' => $row->FinancedTRemark ?? '',
            ]
        ];
    }

    /**
     * Super fast date formatter without object instantiation overhead
     */
    protected function formatDate($date): string
    {
        if (empty($date) || \App\Support\ZeroDate::none($date) || \App\Support\ZeroDate::none($date)) {
            return '';
        }
        $time = strtotime($date);
        return $time ? date('d-m-Y', $time) : '';
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
        $this->rowNumber++;

        $breakdown = $this->getClaimBreakdown($row);
        $rows = [];
        $headings = $this->headings();
        $expenseHeadIndex = array_search('Expense Head', $headings);

        $empStatus = $row->emp_status ?? (isset($row->EmpStatus) ? ($row->EmpStatus === 'A' ? 'Active' : ($row->EmpStatus === 'R' ? 'Resigned' : 'Deactive')) : 'Active');

        foreach ($breakdown as $item) {
            $data = [];
            $expenseHeadIncluded = false;
            $currentIndex = 0;
            $dataForTotals = [];

            foreach ($this->columns as $column) {
                // Special handling for multi-version amounts to match headings()
                if ($column === 'VerifyAmt') {
                    $currVal = floatval($item['VerifyAmt'] ?? 0);
                    $prevVal = floatval($item['FilledAmt'] ?? 0);
                    if (in_array('current', $this->verifyAmtVersions) && in_array('previous', $this->verifyAmtVersions)) {
                        $data[] = $currVal;
                        $dataForTotals[$currentIndex++] = $currVal;
                        $data[] = $prevVal;
                        $dataForTotals[$currentIndex++] = $prevVal;
                    } elseif (in_array('previous', $this->verifyAmtVersions)) {
                        $data[] = $prevVal;
                        $dataForTotals[$currentIndex++] = $prevVal;
                    } else {
                        $data[] = $currVal;
                        $dataForTotals[$currentIndex++] = $currVal;
                    }
                    continue;
                }

                if ($column === 'ApprAmt') {
                    $currVal = floatval($item['ApprAmt'] ?? 0);
                    $prevVal = floatval($item['VerifyAmt'] ?? 0);
                    if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                        $data[] = $currVal;
                        $dataForTotals[$currentIndex++] = $currVal;
                        $data[] = $prevVal;
                        $dataForTotals[$currentIndex++] = $prevVal;
                    } elseif (in_array('previous', $this->apprAmtVersions)) {
                        $data[] = $prevVal;
                        $dataForTotals[$currentIndex++] = $prevVal;
                    } else {
                        $data[] = $currVal;
                        $dataForTotals[$currentIndex++] = $currVal;
                    }
                    continue;
                }

                if ($column === 'FinancedTAmt') {
                    $currVal = floatval($item['FinancedTAmt'] ?? 0);
                    $prevVal = floatval($item['ApprAmt'] ?? 0);
                    if (in_array('current', $this->apprAmtVersions) && in_array('previous', $this->apprAmtVersions)) {
                        $data[] = $currVal;
                        $dataForTotals[$currentIndex++] = $currVal;
                        $data[] = $prevVal;
                        $dataForTotals[$currentIndex++] = $prevVal;
                    } elseif (in_array('previous', $this->apprAmtVersions)) {
                        $data[] = $prevVal;
                        $dataForTotals[$currentIndex++] = $prevVal;
                    } else {
                        $data[] = $currVal;
                        $dataForTotals[$currentIndex++] = $currVal;
                    }
                    continue;
                }

                $value = match ($column) {
                    'exp_id' => $row->ExpId ?? '',
                    'claim_id' => $row->ClaimId ?? '',
                    'claim_type' => $row->claim_type_name ?? '',
                    'claim_status' => $row->ClaimStatus ?? '',
                    'emp_name' => $row->employee_name ?? '',
                    'emp_status' => $empStatus,
                    'emp_code' => \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code ?? ''),
                    'grade' => $row->grade ?? '',
                    'function' => $row->function_name ?? '',
                    'vertical' => $row->vertical_name ?? '',
                    'department' => $row->department_name ?? '',
                    'sub_department' => $row->sub_department_name ?? '',
                    'policy' => $row->policy_name ?? '',
                    'vehicle_type' => $row->vehicle_type ?? '',
                    'month' => $monthMap[$row->ClaimMonth] ?? '',
                    'upload_date' => $this->formatDate($row->CrDate ?? null),
                    'bill_date' => $this->formatDate($row->BillDate ?? null),
                    'FilledAmt' => floatval($item['FilledAmt'] ?? 0),
                    'FilledDate' => $this->formatDate($row->FilledDate ?? null),
                    'FilledByName' => $row->FilledByName ?? '',
                    'odomtr_opening' => $row->odomtr_opening ?? '',
                    'odomtr_closing' => $row->odomtr_closing ?? '',
                    'TotKm' => floatval($row->TotKm ?? 0),
                    'WType' => $wheelerMap[$row->WType] ?? '',
                    'RatePerKM' => floatval($row->RatePerKM ?? 0),
                    'activity_category' => $row->activity_category ?? '',
                    'activity_type' => $row->activity_type ?? '',
                    'traill_no' => $row->traill_no ?? '',
                    'crop' => $row->crop ?? '',
                    'variety' => $row->variety ?? '',
                    'VerifyTRemark' => $item['VerifyTRemark'] ?? $row->VerifyTRemark ?? '',
                    'VerifyDate' => $this->formatDate($row->VerifyDate ?? null),
                    'ApprTRemark' => $item['ApprTRemark'] ?? $row->ApprTRemark ?? '',
                    'ApprDate' => $this->formatDate($row->ApprDate ?? null),
                    'FinancedTRemark' => $item['FinancedTRemark'] ?? $row->FinancedTRemark ?? '',
                    'FinancedDate' => $this->formatDate($row->FinancedDate ?? null),
                    'expense_head' => $item['expense_head'] ?? '',
                    default => '',
                };

                if ($column === 'expense_head') {
                    $expenseHeadIncluded = true;
                }

                if (in_array($column, ['FilledAmt', 'TotKm', 'RatePerKM']) && is_numeric($value)) {
                    $dataForTotals[$currentIndex] = $value;
                }

                $data[] = $value;
                $currentIndex++;
            }

            if (!$expenseHeadIncluded && $expenseHeadIndex !== false) {
                array_splice($data, $expenseHeadIndex, 0, $item['expense_head'] ?? '');
                $newDataForTotals = [];
                foreach ($dataForTotals as $index => $value) {
                    if ($index < $expenseHeadIndex) {
                        $newDataForTotals[$index] = $value;
                    } else {
                        $newDataForTotals[$index + 1] = $value;
                    }
                }
                $dataForTotals = $newDataForTotals;
                $dataForTotals[$expenseHeadIndex] = 0;
            }

            foreach ($dataForTotals as $index => $value) {
                $this->totals[$index] = ($this->totals[$index] ?? 0) + $value;
            }

            $rows[] = $data;
        }

        return $rows;
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
            'ApprAmt' => 'e.ApprTAmt',
            'ApprTRemark' => 'e.ApprTRemark',
            'ApprDate' => 'e.ApprDate',
            'FinancedTAmt' => 'e.FinancedTAmt',
            'FinancedTRemark' => 'e.FinancedTRemark',
            'FinancedDate' => 'e.FinancedDate',
            'expense_head' => 'e.expense_head',
        ];

        $selectedColumns = array_filter(
            array_map(fn($column) => $columnMap[$column] ?? null, $this->columns),
            fn($value) => !is_null($value)
        );

        return $selectedColumns;
    }

    /**
     * Calculate actual column count (accounting for dual-version columns)
     */
    private function getActualColumnCount(): int
    {
        return count($this->headings());
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

        // Set clean fixed column widths instead of calculating font metrics on 500,000 rows
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
                $lastRow = $sheet->getHighestRow();
                $actualColumnCount = $this->getActualColumnCount();
                $lastColumn = Coordinate::stringFromColumnIndex($actualColumnCount);
                $totalRow = $lastRow + 1;

                // Set total row values
                $sheet->setCellValue("A{$totalRow}", 'Total');
                $headings = $this->headings();
                foreach ($this->totals as $index => $total) {
                    if ($index < count($headings)) {
                        $columnName = array_key_exists($index, $headings) ? $headings[$index] : '';
                        if (in_array($columnName, [
                            'Filled Amount', 'Total KM', 'Rate Per KM',
                            'Verified Amount', 'Approved Amount', 'Financed Amount',
                            'Verified Amount (Pre-Verification)', 'Approved Amount (Pre-Approval)', 'Financed Amount (Pre-Finance)'
                        ])) {
                            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                            $sheet->setCellValue("{$columnLetter}{$totalRow}", $total);
                        }
                    }
                }

                // Style total row with light yellow background
                $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => 'right'],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFFE082'], // Light yellow
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);

                // Protect sheet if enabled
                if ($this->protectSheets) {
                    $sheet->getProtection()->setSheet(true);
                    $sheet->getProtection()->setPassword('xai2025');
                }
            },
        ];
    }
}