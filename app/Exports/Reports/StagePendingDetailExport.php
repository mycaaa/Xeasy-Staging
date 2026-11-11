<?php

namespace App\Exports\Reports;

use App\Services\Reports\ClaimStageReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StagePendingDetailExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected string $stage;
    protected array $filters;
    protected array $data;

    private const STAGE_LABELS = [
        'open' => 'Total open, final submission not done',
        'submitted_no_courier' => 'Total submitted, courier not sent',
        'pending_verification' => 'Total received, pending verification',
        'pending_approval' => 'Total verified, pending manager approval',
        'pending_payment' => 'Total approved, pending payment',
    ];

    public function __construct(string $stage, array $filters = [])
    {
        $this->stage = $stage;
        $this->filters = $filters;
        $service = new ClaimStageReportService();
        $this->data = $service->getStagePendingDetail($stage, $filters);
    }

    public function title(): string
    {
        $label = self::STAGE_LABELS[$this->stage] ?? 'Pending Detail';
        // Truncate to 31 chars (Excel sheet name limit)
        return substr($label, 0, 31);
    }

    public function headings(): array
    {
        return [
            'S.No',
            'EmpCode',
            'Employee Name',
            'A/D',
            'Department',
            'Function',
            'Month',
            'Total Claims',
            'Claim Amount',
            'Submit Date',
            'Courier Send Date',
            'Courier Date',
            'Verified Date',
            'Approved Date',
            'Finance Date',
            'Paid Date',
            'Remarks',
        ];
    }

    public function array(): array
    {
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

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

        $stageLabel = self::STAGE_LABELS[$this->stage] ?? $this->stage;

        // Header rows
        $result = [];
        $result[] = array_merge(["Stage Wise Pending Claims - {$company}"], array_fill(0, 16, ''));
        $result[] = array_merge(["Stage: {$stageLabel}"], array_fill(0, 16, ''));
        $result[] = array_merge(["Function: {$functionName} | Department: {$departmentName}"], array_fill(0, 16, ''));
        $result[] = array_merge(["Generated: " . now()->format('d-m-Y h:i A') . " | Total Records: " . count($this->data)], array_fill(0, 16, ''));
        $result[] = array_fill(0, 17, ''); // blank row

        // Column headers
        $result[] = $this->headings();

        // Data rows
        foreach ($this->data as $i => $item) {
            $item = (object) $item;
            $month = (int) ($item->Month ?? 0);
            $result[] = [
                $i + 1,
                $item->EmpCode ?? '',
                $item->employee_name ?? '',
                getEmployeeStatus(null, $item->EmployeeID ?? null),
                $item->department_name ?? '',
                $item->function_name ?? '',
                ($monthNames[$month] ?? '') . '-' . date('y'),
                $item->Total_Claim ?? 0,
                $item->Claim_Amount ?? 0,
                $this->formatDate($item->DateOfSubmit ?? ''),
                $this->formatDate($item->PostDate ?? ''),
                $this->formatDate($item->RecevingDate ?? ''),
                $this->formatDate($item->Verified_Date ?? ''),
                $this->formatDate($item->Approved_Date ?? ''),
                $this->formatDate($item->Finance_Date ?? ''),
                $this->formatDate($item->Fin_PayDate ?? ''),
                $item->DocRmk ?? '',
            ];
        }

        return $result;
    }

    private function formatDate(string $date): string
    {
        if (empty($date) || \App\Support\ZeroDate::none($date)) return '';
        try {
            return date('d-m-Y', strtotime($date));
        } catch (\Exception $e) {
            return $date;
        }
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        // Highlight separated employees (data starts at row 7)
        for ($row = 7; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("D{$row}")->getValue();
            if ($status === 'D') {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFEAEA');
            }
        }

        return [
            // Title rows
            1 => ['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '15648C']]],
            2 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'DC3545']]],
            3 => ['font' => ['size' => 10, 'color' => ['rgb' => '555555']]],
            4 => ['font' => ['size' => 9, 'color' => ['rgb' => '888888']]],
            // Column headers (row 6)
            6 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "A6:{$lastCol}{$lastRow}" => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'font' => ['size' => 10],
            ],
        ];
    }
}
