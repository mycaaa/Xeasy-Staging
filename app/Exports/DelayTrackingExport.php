<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class DelayTrackingExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $query;
    protected $index = 0;
    protected $delayCategory;

    public function __construct($query, $delayCategory = 'all')
    {
        $this->query = $query;
        $this->delayCategory = $delayCategory;
    }

    public function collection(): Enumerable
    {
        $data = $this->query->get();
        
        if ($this->delayCategory === 'all') {
            return $data;
        }
        
        return $data->filter(function ($row) {
            // All delay categories use receiving to paid date delay
            $receivingToPaidDelay = $this->getReceivingToPaidDelay($row);
            
            // If delay is -1, it means invalid dates (payment not made yet), exclude from all categories
            if ($receivingToPaidDelay === -1) {
                return false;
            }
            
            switch ($this->delayCategory) {
                case 'same_day':
                    return $receivingToPaidDelay === 0;
                case '1_3_days':
                    return $receivingToPaidDelay >= 1 && $receivingToPaidDelay <= 3;
                case '4_7_days':
                    return $receivingToPaidDelay >= 4 && $receivingToPaidDelay <= 7;
                case 'over_7_days':
                    return $receivingToPaidDelay > 7;
                default:
                    return true;
            }
        });
    }
    
    private function getMaxDelayForRow($row)
    {
        $delays = [];
        
        // Calculate all delays for this row
        $diffDays = fn($start, $end) => $start &&
            $end &&
            \App\Support\ZeroDate::has($start) &&
            \App\Support\ZeroDate::has($end) &&
            $this->isValidDate($start) &&
            $this->isValidDate($end)
            ? (new \DateTime($end))->diff(new \DateTime($start))->days
            : 0;
            
        $delays[] = $this->calculateSubmitDelay($row->Month, $row->DateOfSubmit);
        $delays[] = $diffDays($row->DateOfSubmit, $row->PostDate);
        $delays[] = $diffDays($row->PostDate, $row->RecevingDate);
        $delays[] = $diffDays($row->RecevingDate, $row->Verified_Date);
        $delays[] = $diffDays($row->Verified_Date, $row->Approved_Date);
        $delays[] = $diffDays($row->Approved_Date, $row->Finance_Date);
        $delays[] = $diffDays($row->Finance_Date, $row->Fin_PayDate);
        $delays[] = $diffDays($row->RecevingDate, $row->Fin_PayDate);
        
        return max($delays);
    }
    
    private function getReceivingToPaidDelay($row)
    {
        // If either date is invalid, return -1 to indicate no valid delay calculation
        if (!$this->isValidDate($row->RecevingDate) || !$this->isValidDate($row->Fin_PayDate)) {
            return -1;
        }
        
        $diffDays = fn($start, $end) => $start &&
            $end &&
            \App\Support\ZeroDate::has($start) &&
            \App\Support\ZeroDate::has($end) &&
            $this->isValidDate($start) &&
            $this->isValidDate($end)
            ? (new \DateTime($end))->diff(new \DateTime($start))->days
            : -1;
            
        return $diffDays($row->RecevingDate, $row->Fin_PayDate);
    }

    public function headings(): array
    {
        return [
            'S No.',
            'Employee Name',
            'Employee Status',
            'Employee Code',
            'Date of Sepration',
            'Function',
            'Vertical',
            'Department',
            'Sub-Department',
            'Grade',
            'Month',
            'Submit Date',
            'Submit Delay (Days)',
            'Courier Post Date',
            'Courier Post Delay (Days)',
            'Courier Receiving Date',
            'Courier Receiving Delay (Days)',
            'Verify Date',
            'Verify Delay (Days)',
            'Approved Date',
            'Approved Delay (Days)',
            'Finance Date',
            'Finance Delay (Days)',
            'Paid Date',
            'Paid Delay (Days)',
            'Receiving to Paid Delay (Days)',
        ];
    }

    public function map(mixed $row): array
    {
        $this->index++;
        $diffDays = fn($start, $end) => $start && $end && \App\Support\ZeroDate::has($start) && \App\Support\ZeroDate::has($end) && $this->isValidDate($start) && $this->isValidDate($end) ? (new \DateTime($end))->diff(new \DateTime($start))->days : 0;
        return [
            $this->index,
            $row->employee_name ?? '',
            getEmployeeStatus(null, $row->EmployeeID ?? $row->employee_id ?? null),
            \App\Helpers\CommonHelper::formatEmployeeCode($row->employee_code ?? ''),
            $this->formatDate($row->DateOfSepration),
            $row->function_name ?? '',
            $row->vertical_name ?? '',
            $row->department_name ?? '',
            $row->sub_department_name ?? '',
            $row->grade_name ?? '',
            $this->getMonthName($row->Month),
            $this->formatDate($row->DateOfSubmit),
            $this->calculateSubmitDelay($row->Month, $row->DateOfSubmit),
            $this->formatDate($row->PostDate),
            $diffDays($row->DateOfSubmit, $row->PostDate),
            $this->formatDate($row->RecevingDate),
            $diffDays($row->PostDate, $row->RecevingDate),
            $this->formatDate($row->Verified_Date),
            $diffDays($row->RecevingDate, $row->Verified_Date),
            $this->formatDate($row->Approved_Date),
            $diffDays($row->Verified_Date, $row->Approved_Date),
            $this->formatDate($row->Finance_Date),
            $diffDays($row->Approved_Date, $row->Finance_Date),
            $this->formatDate($row->Fin_PayDate),
            $diffDays($row->Finance_Date, $row->Fin_PayDate),
            $diffDays($row->RecevingDate, $row->Fin_PayDate),
        ];
    }

    private function getMonthName($value)
    {
        if (!$value) {
            return '';
        }
        if (is_numeric($value)) {
            return date('F', mktime(0, 0, 0, (int) $value, 1));
        }
        if (preg_match('/^\d{4}-\d{2}/', $value)) {
            $month = (int) date('m', strtotime($value));
            return date('F', mktime(0, 0, 0, $month, 1));
        }
        return ucfirst(strtolower($value));
    }

    private function isValidDate($date)
    {
        if (!$date || \App\Support\ZeroDate::none($date) || $date === '1970-01-01') {
            return false;
        }
        if (preg_match('/\b000[0-9]\b/', $date)) {
            return false;
        }
        try {
            $dateTime = new \DateTime($date);
            $year = (int) $dateTime->format('Y');
            if ($year < 1900 || $year > 2100) {
                return false;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatDate($date)
    {
        if (!$this->isValidDate($date)) {
            return '';
        }
        try {
            return date('d-m-Y', strtotime($date));
        } catch (\Exception $e) {
            return '';
        }
    }

    private function calculateSubmitDelay($month, $submitDate)
    {
        if (!$this->isValidDate($submitDate)) {
            return 0;
        }
        try {
            $submit = new \DateTime($submitDate);
            $submitMonth = (int) $submit->format('m');
            $submitYear = (int) $submit->format('Y');
            $claimYear = $submitMonth < $month ? $submitYear - 1 : $submitYear;
            $cutoff = new \DateTime("{$claimYear}-{$month}-15");
            $cutoff->modify('+1 month');
            if ($submit <= $cutoff) {
                return 0;
            }
            $days = $submit->diff($cutoff)->days;
            return max(0, $days);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $sheet->freezePane('D2');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F4E78'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        for ($row = 2; $row <= $lastRow; $row++) {
            $dateOfSep = $sheet->getCell("E{$row}")->getValue();
            if (!empty($dateOfSep)) {
                $fillColor = 'FFFFC7CE';
            } else {
                $fillColor = $row % 2 === 0 ? 'FFF5F5F5' : 'FFFFFFFF';
            }
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $fillColor],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }
        $centerColumns = ['A', 'C', 'D', 'E'];
        foreach ($centerColumns as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle("J2:Z{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
