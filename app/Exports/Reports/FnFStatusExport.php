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

class FnFStatusExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected array $filters;
    protected array $data;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $service = new ClaimStageReportService();
        $this->data = $service->getFnFStatus($filters);
    }

    public function title(): string
    {
        $company = session('company_id') == 11 ? 'VVNR' : 'VNR';
        return "F&F Status - {$company}";
    }

    public function headings(): array
    {
        return [
            'S.No',
            'EC',
            'Name',
            'Emp Status',
            'Department',
            'Resignation Date',
            'Relieving Date',
            'F&F Status',
        ];
    }

    public function array(): array
    {
        $company = session('company_id') == 11 ? 'VVNR' : 'VNR Seeds Pvt Ltd';
        $departmentName = 'All Departments';
        if (!empty($this->filters['department_id'])) {
            $dept = \App\Models\CoreDepartments::find($this->filters['department_id']);
            $departmentName = $dept->department_name ?? 'All Departments';
        }

        // Header rows
        $result = [];
        $result[] = array_merge(["F&F Status - {$company}"], array_fill(0, 7, ''));
        $result[] = array_merge(["Department: {$departmentName}"], array_fill(0, 7, ''));
        $result[] = array_merge(["Generated: " . now()->format('d-m-Y h:i A') . " | Total: " . count($this->data)], array_fill(0, 7, ''));
        $result[] = array_fill(0, 8, ''); // blank row

        // Column headers
        $result[] = $this->headings();

        // Data
        foreach ($this->data as $i => $item) {
            $item = (object) $item;
            $relieving = ($item->relieving_date ?? '') !== '0000-00-00' ? ($item->relieving_date ?? '') : '';
            $status = $relieving ? 'Cleared' : 'Pending';

            $result[] = [
                $i + 1,
                $item->EmpCode ?? '',
                $item->employee_name ?? '',
                getEmployeeStatus(null, $item->EmployeeID ?? $item->employee_id ?? null),
                $item->department_name ?? '',
                $this->formatDate($item->resignation_date ?? ''),
                $this->formatDate($relieving),
                $status,
            ];
        }
        return $result;
    }

    private function formatDate(string $date): string
    {
        if (empty($date) || \App\Support\ZeroDate::none($date)) return '';
        try {
            return date('d-M-Y', strtotime($date));
        } catch (\Exception $e) {
            return $date;
        }
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        // Highlight pending rows (data starts at row 6)
        for ($row = 6; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("H{$row}")->getValue();
            if ($status === 'Pending') {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF3CD');
            }
        }

        return [
            1 => ['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '15648C']]],
            2 => ['font' => ['size' => 10, 'color' => ['rgb' => '555555']]],
            3 => ['font' => ['size' => 9, 'color' => ['rgb' => '888888']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15648C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "A5:{$lastCol}{$lastRow}" => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
        ];
    }
}
