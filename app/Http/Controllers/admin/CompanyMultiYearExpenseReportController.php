<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CompanyMultiYearExpenseReportController extends Controller
{
    /**
     * Supported companies and their connections.
     */
    private const COMPANIES = [
        1 => [
            'id' => 1,
            'name' => 'VNR Seeds Pvt Ltd',
            'short' => 'VNR',
            'conn' => 'expense',
            'badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
        ],
        11 => [
            'id' => 11,
            'name' => 'VVNR',
            'short' => 'VVNR',
            'conn' => 'expense_vvnr',
            'badge' => 'bg-info-subtle text-info border border-info-subtle',
        ],
        3 => [
            'id' => 3,
            'name' => 'VNR Nursery Pvt Ltd',
            'short' => 'Nursery',
            'conn' => 'expense_nursery',
            'badge' => 'bg-success-subtle text-success border border-success-subtle',
        ],
    ];

    /**
     * Display the Company Multi-Year Expense Report page.
     */
    public function index()
    {
        $masterConn = 'expense';
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();

        // 1. Available Financial Years from master table
        $years = DB::connection($masterConn)->table('financialyear')
            ->orderBy('YearId', 'DESC')
            ->get(['YearId', 'Year']);

        // Default base year: session or latest active
        $defaultYearId = \App\Support\CurrentYear::id();

        // 2. Departments from HRIMS
        $departments = DB::connection('hrims')->table('core_departments')
            ->where('is_active', 1)
            ->orderBy('department_name', 'ASC')
            ->get(['id', 'department_name']);

        // 3. Claim Types from master table
        $claimTypes = DB::connection($masterConn)->table('claimtype')
            ->whereIn('ClaimStatus', ['A', 'B', 'D'])
            ->orderBy('ClaimName', 'ASC')
            ->get(['ClaimId', 'ClaimName', 'ClaimCode']);

        // 4. Companies list for filter
        $companies = [
            ['id' => 'all', 'name' => 'All Companies', 'short' => 'ALL'],
            ['id' => 1, 'name' => 'VNR Seeds Pvt Ltd', 'short' => 'VNR'],
            ['id' => 11, 'name' => 'VVNR', 'short' => 'VVNR'],
            ['id' => 3, 'name' => 'VNR Nursery Pvt Ltd', 'short' => 'Nursery'],
        ];

        return view('admin.reports.company-multi-year-expense-report', compact(
            'years',
            'defaultYearId',
            'departments',
            'claimTypes',
            'companies'
        ));
    }

    /**
     * Fetch aggregated multi-year expense data via AJAX.
     */
    public function getData(Request $request)
    {
        try {
            $companyId   = $request->input('company_id', 'all');
            $baseYearId  = (int) $request->input('base_year_id', \App\Support\CurrentYear::id());
            $yearRange   = $request->input('year_range', '3'); // 1, 2, 3, 5, or 'all'
            $deptId      = $request->input('department_id');
            $claimTypeId = $request->input('claim_type_id');
            $amountType  = $request->input('amount_type', 'settled'); // 'settled' or 'claimed'

            // Generate cache key for high performance
            $cacheKey = "comp_multiyear_rpt_" . md5(serialize($request->all()));

            $result = Cache::remember($cacheKey, 300, function () use (
                $companyId,
                $baseYearId,
                $yearRange,
                $deptId,
                $claimTypeId,
                $amountType
            ) {
                return $this->computeReportData(
                    $companyId,
                    $baseYearId,
                    $yearRange,
                    $deptId,
                    $claimTypeId,
                    $amountType
                );
            });

            return response()->json(array_merge(['success' => true], $result));
        } catch (\Exception $e) {
            Log::error('CompanyMultiYearReport getData error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load report data: ' . $e->getMessage(),
                'data' => [],
                'years' => [],
                'col_totals' => [],
                'grand_total' => 0,
                'summary' => [
                    'total_expense' => 0,
                    'total_companies' => 0,
                    'total_departments' => 0,
                    'total_claim_types' => 0,
                    'total_records' => 0,
                ],
            ], 500);
        }
    }

    /**
     * Compute and aggregate the report dataset across companies and financial years.
     */
    private function computeReportData(
        $companyFilter,
        int $baseYearId,
        $yearRange,
        $deptFilter,
        $claimTypeFilter,
        string $amountType
    ): array {
        $masterConn = 'expense';
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();

        // 1. Resolve Financial Years Range
        $allYears = DB::connection($masterConn)->table('financialyear')
            ->orderBy('YearId', 'ASC')
            ->get(['YearId', 'Year'])
            ->toArray();

        // Find index of base year
        $baseIndex = -1;
        foreach ($allYears as $idx => $y) {
            if ((int) $y->YearId === $baseYearId) {
                $baseIndex = $idx;
                break;
            }
        }

        if ($baseIndex === -1 && !empty($allYears)) {
            $baseIndex = count($allYears) - 1; // default to latest
        }

        // Determine slice length
        $numYears = 3;
        if ($yearRange === '1' || $yearRange === 1) {
            $numYears = 1;
        } elseif ($yearRange === '2' || $yearRange === 2) {
            $numYears = 2;
        } elseif ($yearRange === '3' || $yearRange === 3) {
            $numYears = 3;
        } elseif ($yearRange === '5' || $yearRange === 5) {
            $numYears = 5;
        } elseif ($yearRange === 'all') {
            $numYears = count($allYears);
        }

        $startIndex = max(0, $baseIndex - $numYears + 1);
        $sliceLength = $baseIndex - $startIndex + 1;
        $selectedYearObjs = array_slice($allYears, $startIndex, $sliceLength);

        $selectedYears = [];
        foreach ($selectedYearObjs as $y) {
            $selectedYears[] = [
                'year_id' => (int) $y->YearId,
                'year'    => $y->Year,
                'label'   => $this->formatYearLabel($y->Year),
                'key'     => 'fy_' . $y->YearId,
            ];
        }

        // 2. Determine target companies
        $targetCompanies = [];
        if ($companyFilter === 'all' || empty($companyFilter)) {
            $targetCompanies = self::COMPANIES;
        } else {
            $cid = (int) $companyFilter;
            if (isset(self::COMPANIES[$cid])) {
                $targetCompanies[$cid] = self::COMPANIES[$cid];
            } else {
                $targetCompanies = self::COMPANIES;
            }
        }

        // 3. Matrix storage: [ company_dept_claim => row_data ]
        $matrix = [];
        $colTotals = [];
        foreach ($selectedYears as $sy) {
            $colTotals[$sy['key']] = 0;
        }

        // 4. Query each company and each financial year
        foreach ($targetCompanies as $cid => $company) {
            $connName = $company['conn'];

            // Test if connection works
            try {
                DB::connection($connName)->getPdo();
            } catch (\Exception $connEx) {
                Log::warning("CompanyMultiYearReport: DB connection failed for {$company['name']} ({$connName}): " . $connEx->getMessage());
                continue;
            }

            foreach ($selectedYears as $sy) {
                $masterYearStr = $sy['year'];
                $masterYearId  = $sy['year_id'];
                $yearKey       = $sy['key'];

                // Resolve company local YearId
                $localYearId = $this->getCompanyYearId($connName, $cid, $masterYearStr, $masterYearId);
                $tableName   = "y{$localYearId}_expenseclaims";

                // Check if table exists
                try {
                    $tableExists = DB::connection($connName)->select("SHOW TABLES LIKE '{$tableName}'");
                    if (empty($tableExists)) {
                        continue;
                    }
                } catch (\Exception $tblEx) {
                    continue;
                }

                // Choose amount column expression and status condition
                if ($amountType === 'claimed') {
                    $amountExpr   = "COALESCE(ec.FilledTAmt, 0)";
                    $statusClause = "ec.ClaimStatus NOT IN ('Deactivate', 'Draft')";
                } else {
                    if ($cid == 11) {
                        // VVNR: Verified amount is final settlement; claim status is Verified / Financed
                        $amountExpr   = "COALESCE(ec.VerifyTAmt, 0)";
                        $statusClause = "ec.ClaimStatus IN ('Verified', 'Financed')";
                    } else {
                        // VNR & Nursery: Financed amount is final settlement; claim status is Financed
                        $amountExpr   = "COALESCE(ec.FinancedTAmt, 0)";
                        $statusClause = "ec.ClaimStatus = 'Financed'";
                    }
                }

                // Build WHERE conditions
                $whereClauses = [
                    $statusClause,
                    "(ec.FilledBy > 0 OR ec.FilledOkay = 1 OR ec.FilledTAmt > 0 OR ec.CrBy > 0)",
                ];
                $bindings = [];

                if (!empty($deptFilter)) {
                    $deptIds = is_array($deptFilter)
                        ? array_values(array_filter(array_map('intval', $deptFilter)))
                        : array_values(array_filter(array_map('intval', explode(',', (string) $deptFilter))));

                    if (!empty($deptIds)) {
                        $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
                        $whereClauses[] = "g.DepartmentId IN ({$placeholders})";
                        foreach ($deptIds as $dId) {
                            $bindings[] = $dId;
                        }
                    }
                }

                if (!empty($claimTypeFilter)) {
                    $claimTypeIds = is_array($claimTypeFilter)
                        ? array_values(array_filter(array_map('intval', $claimTypeFilter)))
                        : array_values(array_filter(array_map('intval', explode(',', (string) $claimTypeFilter))));

                    if (!empty($claimTypeIds)) {
                        $placeholders = implode(',', array_fill(0, count($claimTypeIds), '?'));
                        $whereClauses[] = "ec.ClaimId IN ({$placeholders})";
                        foreach ($claimTypeIds as $cId) {
                            $bindings[] = $cId;
                        }
                    }
                }

                $whereSql = implode(' AND ', $whereClauses);

                $sql = "
                    SELECT 
                        COALESCE(dept.id, 0) AS department_id,
                        COALESCE(NULLIF(TRIM(dept.department_name), ''), 'General / Unassigned') AS department_name,
                        ec.ClaimId AS claim_type_id,
                        COALESCE(NULLIF(TRIM(ct.ClaimName), ''), 'Other Expense') AS claim_type_name,
                        COALESCE(SUM({$amountExpr}), 0) AS total_amount,
                        COUNT(ec.ExpId) AS claim_count
                    FROM {$tableName} ec
                    LEFT JOIN claimtype ct ON ec.ClaimId = ct.ClaimId
                    INNER JOIN {$hrimsDb}.hrm_employee e ON ec.CrBy = e.EmployeeID
                    LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                    LEFT JOIN {$hrimsDb}.core_departments dept ON g.DepartmentId = dept.id
                    WHERE {$whereSql}
                    GROUP BY dept.id, dept.department_name, ec.ClaimId, ct.ClaimName
                ";

                try {
                    $rows = DB::connection($connName)->select($sql, $bindings);

                    foreach ($rows as $r) {
                        $deptIdVal      = (int) $r->department_id;
                        $deptNameVal    = trim($r->department_name);
                        $claimTypeIdVal = (int) $r->claim_type_id;
                        $claimTypeName  = trim($r->claim_type_name);
                        $amtVal         = (float) $r->total_amount;
                        $countVal       = (int) $r->claim_count;

                        $rowKey = "{$cid}_{$deptIdVal}_{$claimTypeIdVal}";

                        if (!isset($matrix[$rowKey])) {
                            // Initialize new row
                            $yearsData = [];
                            $countsData = [];
                            foreach ($selectedYears as $sYInit) {
                                $yearsData[$sYInit['key']] = 0.0;
                                $countsData[$sYInit['key']] = 0;
                            }

                            $matrix[$rowKey] = [
                                'key'             => $rowKey,
                                'company_id'      => $cid,
                                'company_name'    => $company['name'],
                                'company_short'   => $company['short'],
                                'company_badge'   => $company['badge'],
                                'department_id'   => $deptIdVal,
                                'department_name' => $deptNameVal,
                                'claim_type_id'   => $claimTypeIdVal,
                                'claim_type_name' => $claimTypeName,
                                'years'           => $yearsData,
                                'claim_counts'    => $countsData,
                                'total_amount'    => 0.0,
                                'avg_amount'      => 0.0,
                            ];
                        }

                        $matrix[$rowKey]['years'][$yearKey]        += $amtVal;
                        $matrix[$rowKey]['claim_counts'][$yearKey] += $countVal;
                    }
                } catch (\Exception $qEx) {
                    Log::error("CompanyMultiYearReport: query error in {$connName}.{$tableName}: " . $qEx->getMessage());
                }
            }
        }

        // 5. Calculate row totals and column totals
        $formattedData = [];
        $uniqueCompanies   = [];
        $uniqueDepartments = [];
        $uniqueClaimTypes  = [];
        $grandTotal        = 0.0;
        $numSelectedYears  = count($selectedYears);

        foreach ($matrix as $rowKey => $row) {
            $rowSum = 0.0;
            foreach ($selectedYears as $sy) {
                $yKey = $sy['key'];
                $val = (float) ($row['years'][$yKey] ?? 0.0);
                $rowSum += $val;
                $colTotals[$yKey] += $val;
            }

            // Exclude rows where total expenditure across all selected years is 0
            if ($rowSum <= 0.001) {
                continue;
            }

            $row['total_amount'] = round($rowSum, 2);
            $row['avg_amount']   = $numSelectedYears > 0 ? round($rowSum / $numSelectedYears, 2) : 0.0;

            $grandTotal += $rowSum;

            $uniqueCompanies[$row['company_id']]       = true;
            $uniqueDepartments[$row['department_name']] = true;
            $uniqueClaimTypes[$row['claim_type_name']] = true;

            $formattedData[] = $row;
        }

        // Sort rows by company name, department name, then row total DESC
        usort($formattedData, function ($a, $b) {
            $compCmp = strcmp($a['company_name'], $b['company_name']);
            if ($compCmp !== 0) {
                return $compCmp;
            }
            $deptCmp = strcmp($a['department_name'], $b['department_name']);
            if ($deptCmp !== 0) {
                return $deptCmp;
            }
            return $b['total_amount'] <=> $a['total_amount'];
        });

        // Calculate contribution percentage for each row based on grand total
        foreach ($formattedData as &$fRow) {
            $fRow['contribution_pct'] = $grandTotal > 0.001
                ? round(($fRow['total_amount'] / $grandTotal) * 100, 2)
                : 0.0;
        }
        unset($fRow);

        // Round column totals
        foreach ($colTotals as $k => $v) {
            $colTotals[$k] = round($v, 2);
        }

        return [
            'years'       => $selectedYears,
            'data'        => $formattedData,
            'col_totals'  => $colTotals,
            'grand_total' => round($grandTotal, 2),
            'summary'     => [
                'total_expense'     => round($grandTotal, 2),
                'total_companies'   => count($uniqueCompanies),
                'total_departments' => count($uniqueDepartments),
                'total_claim_types' => count($uniqueClaimTypes),
                'total_records'     => count($formattedData),
            ],
        ];
    }

    /**
     * Map master financial year string to company local YearId.
     */
    private function getCompanyYearId(string $expenseConn, int $companyId, string $masterYearStr, int $masterYearId): int
    {
        try {
            $matchedId = DB::connection($expenseConn)
                ->table('financialyear')
                ->where('Year', $masterYearStr)
                ->value('YearId');
            if ($matchedId) {
                return (int) $matchedId;
            }
        } catch (\Exception $e) {
            // Ignore error and fall back
        }

        if ($companyId == 11) {
            return max(1, $masterYearId - 2);
        }

        return $masterYearId;
    }

    /**
     * Format Financial Year string into standard 'FY YYYY-YY' format.
     */
    private function formatYearLabel(string $year): string
    {
        $year = trim($year);
        $parts = explode('-', $year);
        if (count($parts) === 2) {
            $y1 = trim($parts[0]);
            $y2 = trim($parts[1]);
            if (strlen($y2) === 4) {
                $y2 = substr($y2, -2);
            }
            return "FY {$y1}-{$y2}";
        }
        return "FY {$year}";
    }

    /**
     * Export the multi-year expense report to Excel (.xlsx) or CSV.
     */
    public function export(Request $request)
    {
        $companyId   = $request->input('company_id', 'all');
        $baseYearId  = (int) $request->input('base_year_id', \App\Support\CurrentYear::id());
        $yearRange   = $request->input('year_range', '3');
        $deptId      = $request->input('department_id');
        $claimTypeId = $request->input('claim_type_id');
        $amountType  = $request->input('amount_type', 'settled');
        $format      = strtolower($request->input('format', 'excel'));

        $report = $this->computeReportData(
            $companyId,
            $baseYearId,
            $yearRange,
            $deptId,
            $claimTypeId,
            $amountType
        );

        $years     = $report['years'];
        $data      = $report['data'];
        $colTotals = $report['col_totals'];
        $grandTot  = $report['grand_total'];
        $timestamp = date('d-m-Y_His');

        if ($format === 'csv') {
            return $this->exportCsv($years, $data, $colTotals, $grandTot, $timestamp);
        }

        return $this->exportExcel($years, $data, $colTotals, $grandTot, $timestamp, $amountType);
    }

    /**
     * Export report as styled Excel spreadsheet.
     */
    private function exportExcel(array $years, array $data, array $colTotals, float $grandTot, string $timestamp, string $amountType)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Multi-Year Expense Report');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

        // Title Block
        $sheet->setCellValue('A1', 'COMPANY & DEPARTMENT WISE MULTI-YEAR EXPENSE COMPARISON REPORT');
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('15648C');

        $amtLabel = ($amountType === 'claimed') ? 'Claimed Amount (FilledTAmt)' : 'Final Settled Amount (Financed / Verified)';
        $sheet->setCellValue('A2', 'Generated On: ' . date('d-M-Y H:i A') . ' | Basis: ' . $amtLabel);
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

        // Headers
        $headers = [
            1 => '#',
            2 => 'Company',
            3 => 'Department',
            4 => 'Claim Type',
        ];

        $colIdx = 5;
        foreach ($years as $y) {
            $headers[$colIdx] = $y['label'];
            $colIdx++;
        }
        $headers[$colIdx] = 'Total Amount';
        $colIdx++;
        $headers[$colIdx] = 'Annual Average';
        $colIdx++;
        $headers[$colIdx] = 'Contribution %';

        $totalCols = $colIdx;
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        // Write Headers at row 4
        foreach ($headers as $cIdx => $hdr) {
            $colLet = Coordinate::stringFromColumnIndex($cIdx);
            $sheet->setCellValue("{$colLet}4", $hdr);
        }

        // Header Styling
        $sheet->getStyle("A4:{$lastColLetter}4")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '15648C'], // Primary Blue
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '0E4663'],
                ],
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(28);

        // Fill Data Rows
        $currentRow = 5;
        $rowNum = 1;

        foreach ($data as $row) {
            $sheet->setCellValue("A{$currentRow}", $rowNum);
            $sheet->setCellValue("B{$currentRow}", $row['company_name']);
            $sheet->setCellValue("C{$currentRow}", $row['department_name']);
            $sheet->setCellValue("D{$currentRow}", $row['claim_type_name']);

            $colIdx = 5;
            foreach ($years as $y) {
                $colLet = Coordinate::stringFromColumnIndex($colIdx);
                $val = (float) ($row['years'][$y['key']] ?? 0);
                $sheet->setCellValue("{$colLet}{$currentRow}", $val);
                $sheet->getStyle("{$colLet}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $colIdx++;
            }

            // Total & Avg & Contribution %
            $colLetTot = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetTot}{$currentRow}", (float) $row['total_amount']);
            $sheet->getStyle("{$colLetTot}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$colLetTot}{$currentRow}")->getFont()->setBold(true);

            $colIdx++;
            $colLetAvg = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetAvg}{$currentRow}", (float) $row['avg_amount']);
            $sheet->getStyle("{$colLetAvg}{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            $colIdx++;
            $colLetPct = Coordinate::stringFromColumnIndex($colIdx);
            $pctDecimal = ((float) ($row['contribution_pct'] ?? 0)) / 100;
            $sheet->setCellValue("{$colLetPct}{$currentRow}", $pctDecimal);
            $sheet->getStyle("{$colLetPct}{$currentRow}")->getNumberFormat()->setFormatCode('0.00%');

            // Zebra Striping
            if ($currentRow % 2 === 0) {
                $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $currentRow++;
            $rowNum++;
        }

        // Totals Row
        $totRow = $currentRow;
        $sheet->setCellValue("A{$totRow}", '');
        $sheet->setCellValue("B{$totRow}", 'TOTAL');
        $sheet->setCellValue("C{$totRow}", '-');
        $sheet->setCellValue("D{$totRow}", '-');

        $colIdx = 5;
        foreach ($years as $y) {
            $colLet = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLet}{$totRow}", (float) ($colTotals[$y['key']] ?? 0));
            $sheet->getStyle("{$colLet}{$totRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $colIdx++;
        }

        $colLetTot = Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue("{$colLetTot}{$totRow}", (float) $grandTot);
        $sheet->getStyle("{$colLetTot}{$totRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        $colIdx++;
        $colLetAvg = Coordinate::stringFromColumnIndex($colIdx);
        $avgGrand = count($years) > 0 ? ($grandTot / count($years)) : 0;
        $sheet->setCellValue("{$colLetAvg}{$totRow}", (float) $avgGrand);
        $sheet->getStyle("{$colLetAvg}{$totRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        $colIdx++;
        $colLetPct = Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue("{$colLetPct}{$totRow}", 1.00);
        $sheet->getStyle("{$colLetPct}{$totRow}")->getNumberFormat()->setFormatCode('0.00%');

        // Style Total Row
        $sheet->getStyle("A{$totRow}:{$lastColLetter}{$totRow}")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => '0F172A'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E293B']],
            ],
        ]);

        // Auto-size columns
        for ($i = 1; $i <= $totalCols; $i++) {
            $colLet = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLet)->setAutoSize(true);
        }

        $fileName = "Company_Multi_Year_Expense_Report_{$timestamp}.xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        header('Cache-Control: max-age=0');

        \App\Support\ReportStamp::workbook($spreadsheet); // STAMP-01

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Export report as CSV file.
     */
    private function exportCsv(array $years, array $data, array $colTotals, float $grandTot, string $timestamp)
    {
        $fileName = "Company_Multi_Year_Expense_Report_{$timestamp}.csv";

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Header row
        $headerRow = ['#', 'Company', 'Department', 'Claim Type'];
        foreach ($years as $y) {
            $headerRow[] = $y['label'];
        }
        $headerRow[] = 'Total Amount';
        $headerRow[] = 'Annual Average';
        $headerRow[] = 'Contribution %';
        fputcsv($output, $headerRow);

        $rowNum = 1;
        foreach ($data as $row) {
            $line = [
                $rowNum,
                $row['company_name'],
                $row['department_name'],
                $row['claim_type_name'],
            ];
            foreach ($years as $y) {
                $line[] = (float) ($row['years'][$y['key']] ?? 0);
            }
            $line[] = (float) $row['total_amount'];
            $line[] = (float) $row['avg_amount'];
            $line[] = number_format((float) ($row['contribution_pct'] ?? 0), 2) . '%';
            fputcsv($output, $line);
            $rowNum++;
        }

        // Totals Row
        $totLine = ['', 'TOTAL', '-', '-'];
        foreach ($years as $y) {
            $totLine[] = (float) ($colTotals[$y['key']] ?? 0);
        }
        $totLine[] = (float) $grandTot;
        $totLine[] = count($years) > 0 ? round($grandTot / count($years), 2) : 0;
        $totLine[] = '100.00%';
        fputcsv($output, $totLine);

        fputcsv($output, []); fputcsv($output, \App\Support\ReportStamp::csvRow()); // STAMP-01
        fclose($output);
        exit;
    }
}
