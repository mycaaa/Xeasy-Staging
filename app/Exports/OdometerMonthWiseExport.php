<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Export;

class OdometerMonthWiseExport implements WithMultipleSheets, Export
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $sheets = [];

        // Group data by month
        $grouped = [];
        foreach ($this->data as $row) {
            $m = (int)$row['month'];
            $grouped[$m][] = $row;
        }

        // Sort by month
        ksort($grouped);

        foreach ($grouped as $monthNum => $rows) {
            $monthName = $months[$monthNum] ?? 'Month ' . $monthNum;
            $sheets[] = new OdometerMonthSheet($rows, $monthName);
        }

        // Add a summary sheet at the end
        $sheets[] = new OdometerMonthSummarySheet($grouped, $months);

        return $sheets;
    }
}

class OdometerMonthSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;
    protected $monthName;

    public function __construct(array $data, string $monthName)
    {
        $this->data = $data;
        $this->monthName = $monthName;
    }

    public function title(): string
    {
        return $this->monthName;
    }

    public function collection(): Enumerable
    {
        return collect($this->data)->map(function ($row, $i) {
            return [
                $i + 1,
                $row['emp_code'] ?? '',
                $row['employee_name'] ?? '',
                getEmployeeStatus(null, $row['EmployeeID'] ?? $row['employee_id'] ?? null),
                $row['department_name'] ?? '',
                $row['vertical_name'] ?? '',
                $row['total_claims'] ?? 0,
                $row['total_km'] ?? 0,
                round($row['avg_km'] ?? 0, 2),
                $row['km_2w'] ?? 0,
                $row['km_4w'] ?? 0,
                $row['total_amount'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return ['#', 'Emp Code', 'Employee Name', 'Status', 'Department', 'Vertical', 'Claims', 'Total KM', 'Avg KM', '2W KM', '4W KM', 'Amount'];
    }
}

class OdometerMonthSummarySheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $grouped;
    protected $months;

    public function __construct(array $grouped, array $months)
    {
        $this->grouped = $grouped;
        $this->months = $months;
    }

    public function title(): string
    {
        return 'Month Summary';
    }

    public function collection(): Enumerable
    {
        $rows = [];
        foreach ($this->grouped as $monthNum => $data) {
            $totalClaims = array_sum(array_column($data, 'total_claims'));
            $totalKm = array_sum(array_column($data, 'total_km'));
            $km2w = array_sum(array_column($data, 'km_2w'));
            $km4w = array_sum(array_column($data, 'km_4w'));
            $totalAmount = array_sum(array_column($data, 'total_amount'));
            $employees = count($data);
            $avgKm = $totalClaims > 0 ? round($totalKm / $totalClaims, 2) : 0;

            $rows[] = [
                $this->months[$monthNum] ?? 'Month ' . $monthNum,
                $employees,
                $totalClaims,
                $totalKm,
                $avgKm,
                $km2w,
                $km4w,
                $totalAmount,
            ];
        }
        return collect($rows);
    }

    public function headings(): array
    {
        return ['Month', 'Employees', 'Total Claims', 'Total KM', 'Avg KM/Claim', '2W KM', '4W KM', 'Total Amount'];
    }
}
