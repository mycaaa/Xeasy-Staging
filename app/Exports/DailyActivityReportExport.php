<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use App\Models\ExpenseClaim;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyActivityReportExport implements FromArray, WithEvents, WithHeadings, WithStyles, WithTitle
{
    protected $fromDate;

    protected $toDate;

    protected $filters;

    protected $data;

    protected $totals;

    public function __construct(string $fromDate, string $toDate, array $filters = [])
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->filters = $filters;
        $this->totals = [
            'total_upload' => 0,
            'punching' => 0,
            'verified' => 0,
            'approved' => 0,
            'financed' => 0,
        ];
        $this->fetchData();
    }

    protected function fetchData()
    {
        $year_id = \App\Support\CurrentYear::id();
        $table = ExpenseClaim::tableName($year_id);
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();
        $conn = session('expense_connection') ?: config('database.default');

        $baseQuery = DB::connection($conn)->table("{$table} as e")
            ->where('e.ClaimStatus', '!=', 'Deactivate')
            ->whereNotIn('e.ClaimId', [19, 20, 21]);

        if (!empty($this->filters) && !empty(array_filter($this->filters))) {
            $baseQuery->join("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', DB::raw('COALESCE(NULLIF(e.CrBy, 0), e.FilledBy)'));
            OrganogramFilterHelper::applyToQuery($baseQuery, $this->filters, 'eg', 'eg.EmployeeID', $hrimsDb);
        }

        $uploadQuery = (clone $baseQuery)->selectRaw('DATE(e.CrDate) AS ActionDate, COUNT(*) AS TotalUpload, 0 AS Punching, 0 AS Verified, 0 AS Approved, 0 AS Financed')->where('e.CrDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.CrDate)'), [$this->fromDate, $this->toDate])->groupBy(DB::raw('DATE(e.CrDate)'));
        $punchingQuery = (clone $baseQuery)->selectRaw('DATE(e.FilledDate) AS ActionDate, 0 AS TotalUpload, COUNT(*) AS Punching, 0 AS Verified, 0 AS Approved, 0 AS Financed')->where('e.FilledDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.FilledDate)'), [$this->fromDate, $this->toDate])->groupBy(DB::raw('DATE(e.FilledDate)'));
        $verifiedQuery = (clone $baseQuery)->selectRaw('DATE(e.VerifyDate) AS ActionDate, 0 AS TotalUpload, 0 AS Punching, COUNT(*) AS Verified, 0 AS Approved, 0 AS Financed')->where('e.VerifyDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.VerifyDate)'), [$this->fromDate, $this->toDate])->groupBy(DB::raw('DATE(e.VerifyDate)'));
        $approvedQuery = (clone $baseQuery)->selectRaw('DATE(e.ApprDate) AS ActionDate, 0 AS TotalUpload, 0 AS Punching, 0 AS Verified, COUNT(*) AS Approved, 0 AS Financed')->where('e.ApprDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.ApprDate)'), [$this->fromDate, $this->toDate])->groupBy(DB::raw('DATE(e.ApprDate)'));
        $financedQuery = (clone $baseQuery)->selectRaw('DATE(e.FinancedDate) AS ActionDate, 0 AS TotalUpload, 0 AS Punching, 0 AS Verified, 0 AS Approved, COUNT(*) AS Financed')->where('e.FinancedDate', '!=', '0000-00-00')->whereBetween(DB::raw('DATE(e.FinancedDate)'), [$this->fromDate, $this->toDate])->groupBy(DB::raw('DATE(e.FinancedDate)'));

        $unionQuery = $uploadQuery->unionAll($punchingQuery)->unionAll($verifiedQuery)->unionAll($approvedQuery)->unionAll($financedQuery);

        $this->data = DB::connection($conn)->table(DB::raw("({$unionQuery->toSql()}) as sub"))
            ->mergeBindings($unionQuery)
            ->selectRaw('ActionDate, SUM(TotalUpload) AS TotalUpload, SUM(Punching) AS Punching, SUM(Verified) AS Verified, SUM(Approved) AS Approved, SUM(Financed) AS Financed')
            ->groupBy('ActionDate')
            ->orderBy('ActionDate')
            ->get();

        // Calculate totals
        foreach ($this->data as $row) {
            $this->totals['total_upload'] += $row->TotalUpload;
            $this->totals['punching'] += $row->Punching;
            $this->totals['verified'] += $row->Verified;
            $this->totals['approved'] += $row->Approved;
            $this->totals['financed'] += $row->Financed;
        }
    }

    public function array(): array
    {
        return $this->data->map(function ($row) {
            return [
                $row->ActionDate,
                $row->TotalUpload,
                $row->Punching,
                $row->Verified,
                $row->Approved,
                $row->Financed,
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return [
            'Action Date',
            'Total Upload',
            'Punching',
            'Verified',
            'Approved',
            'Financed',
        ];
    }

    public function title(): string
    {
        return 'Daily Activity';
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastColumn = 'F'; // 6 columns: A to F
        $lastRow = $sheet->getHighestRow(); // After data is written

        // Insert rows for title and summary (7 rows: 1 for title, 5 for summary, 1 blank)
        $sheet->insertNewRowBefore(1, 7);

        // 1. Add Report Title (Row 1)
        $title = 'Daily Activity Report - '.date('M Y', strtotime($this->fromDate)).' to '.date('M Y', strtotime($this->toDate));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['argb' => 'FF001868'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFFFFF'],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(40);

        // 2. Add Summary Section (Rows 2 to 6)
        $summaryData = [
            ['Total Uploads', $this->totals['total_upload']],
            ['Total Punching', $this->totals['punching']],
            ['Total Verified', $this->totals['verified']],
            ['Total Approved', $this->totals['approved']],
            ['Total Financed', $this->totals['financed']],
        ];

        foreach ($summaryData as $index => $row) {
            $rowNum = $index + 2; // Rows 2 to 6
            $sheet->setCellValue("A{$rowNum}", $row[0]);
            $sheet->setCellValue("B{$rowNum}", $row[1]);
        }

        $sheet->getStyle('A2:B6')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FF000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE6F0FA'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => 'left',
                'vertical' => 'center',
            ],
        ]);
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        foreach (range(2, 6) as $rowNum) {
            $sheet->getRowDimension($rowNum)->setRowHeight(20);
        }

        // Leave Row 7 blank for spacing
        $sheet->getRowDimension(7)->setRowHeight(10);

        // Style the column header row (now Row 8)
        $sheet->getStyle("A8:{$lastColumn}8")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF299CDB'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Apply borders to data rows (rows 9 to lastRow) if data exists
        if ($lastRow > 8) {
            $sheet->getStyle("A9:{$lastColumn}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }

        // Set column widths to auto-size for remaining columns
        foreach (range('C', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Set row height for headers
        $sheet->getRowDimension(8)->setRowHeight(25);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastColumn = 'F';
                $totalRow = $lastRow + 1;

                // Add total row
                $sheet->setCellValue("A{$totalRow}", 'Total');
                $sheet->setCellValue("B{$totalRow}", $this->totals['total_upload']);
                $sheet->setCellValue("C{$totalRow}", $this->totals['punching']);
                $sheet->setCellValue("D{$totalRow}", $this->totals['verified']);
                $sheet->setCellValue("E{$totalRow}", $this->totals['approved']);
                $sheet->setCellValue("F{$totalRow}", $this->totals['financed']);

                // Style total row
                $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => ['argb' => '1F4E78'],
                    ],
                    'alignment' => ['horizontal' => 'right'],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
