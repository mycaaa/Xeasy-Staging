<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Export;

class OdometerAnalysisExport implements WithMultipleSheets, Export
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        return [
            new OdometerSummarySheet($this->data['summary']),
            new OdometerEmployeeWiseSheet($this->data['employee_wise']),
            new OdometerTwoWheelerSheet($this->data['two_wheeler']),
            new OdometerFourWheelerSheet($this->data['four_wheeler']),
            new OdometerDepartmentWiseSheet($this->data['department_wise']),
            new OdometerVerticalWiseSheet($this->data['vertical_wise']),
        ];
    }
}

class OdometerSummarySheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function collection(): Enumerable
    {
        return collect([
            ['Total Claims', $this->data['total_claims'] ?? 0],
            ['Total KM', $this->data['total_km'] ?? 0],
            ['Average KM per Claim', round($this->data['avg_km'] ?? 0, 2)],
            ['Max KM', $this->data['max_km'] ?? 0],
            ['Min KM', $this->data['min_km'] ?? 0],
            ['2-Wheeler Claims', $this->data['claims_2w'] ?? 0],
            ['2-Wheeler KM', $this->data['total_km_2w'] ?? 0],
            ['4-Wheeler Claims', $this->data['claims_4w'] ?? 0],
            ['4-Wheeler KM', $this->data['total_km_4w'] ?? 0],
            ['Total Amount', $this->data['total_amount'] ?? 0],
        ]);
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }
}

class OdometerEmployeeWiseSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Employee Wise';
    }

    public function collection(): Enumerable
    {
        return collect($this->data)->map(function ($row) {
            return [
                $row['emp_code'] ?? '',
                $row['employee_name'] ?? '',
                getEmployeeStatus(null, $row['EmployeeID'] ?? $row['employee_id'] ?? null),
                $row['department_name'] ?? '',
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
        return ['Emp Code', 'Employee Name', 'Status', 'Department', 'Total Claims', 'Total KM', 'Avg KM', '2W KM', '4W KM', 'Amount'];
    }
}

class OdometerTwoWheelerSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return '2-Wheeler';
    }

    public function collection(): Enumerable
    {
        return collect($this->data)->map(function ($row) {
            return [
                $row['emp_code'] ?? '',
                $row['employee_name'] ?? '',
                getEmployeeStatus(null, $row['EmployeeID'] ?? $row['employee_id'] ?? null),
                $row['department_name'] ?? '',
                $row['total_claims'] ?? 0,
                $row['total_km'] ?? 0,
                round($row['avg_km'] ?? 0, 2),
                round($row['avg_rate_per_km'] ?? 0, 2),
                $row['total_amount'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return ['Emp Code', 'Employee Name', 'Status', 'Department', 'Claims', 'Total KM', 'Avg KM', 'Rate/KM', 'Amount'];
    }
}

class OdometerFourWheelerSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return '4-Wheeler';
    }

    public function collection(): Enumerable
    {
        return collect($this->data)->map(function ($row) {
            return [
                $row['emp_code'] ?? '',
                $row['employee_name'] ?? '',
                getEmployeeStatus(null, $row['EmployeeID'] ?? $row['employee_id'] ?? null),
                $row['department_name'] ?? '',
                $row['total_claims'] ?? 0,
                $row['total_km'] ?? 0,
                round($row['avg_km'] ?? 0, 2),
                round($row['avg_rate_per_km'] ?? 0, 2),
                $row['total_amount'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return ['Emp Code', 'Employee Name', 'Status', 'Department', 'Claims', 'Total KM', 'Avg KM', 'Rate/KM', 'Amount'];
    }
}

class OdometerDepartmentWiseSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Department Wise';
    }

    public function collection(): Enumerable
    {
        return collect($this->data)->map(function ($row) {
            return [
                $row['department_name'] ?? '',
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
        return ['Department', 'Total Claims', 'Total KM', 'Avg KM', '2W KM', '4W KM', 'Amount'];
    }
}

class OdometerVerticalWiseSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Vertical Wise';
    }

    public function collection(): Enumerable
    {
        return collect($this->data)->map(function ($row) {
            return [
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
        return ['Vertical', 'Total Claims', 'Total KM', 'Avg KM', '2W KM', '4W KM', 'Amount'];
    }
}
