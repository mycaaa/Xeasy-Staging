<?php

namespace App\Exports;

use App\Exports\Concerns\WithGeneratedTimestamp;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Enumerable;

class PaymentsReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    use WithGeneratedTimestamp;
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection(): Enumerable
    {
        return $this->data instanceof Enumerable ? $this->data : collect($this->data);
    }

    public function headings(): array
    {
        return [
            'Voucher No',
            'Employee ID',
            'Employee Name',
            'Employee Code',
            'Month',
            'Total Claims',
            'Claim Amount',
            'Verified Amount',
            'Approved Amount',
            'Finance Amount',
            'Payment Amount',
            'Payment Date',
            'Payment Date (Alt)',
            'Payment Status',
            'Payment Mode',
            'Paid By',
            'Payment Remark',
            'UTR Number',
            'Bank Reference',
            'Actual Expense',
            'Total Adjustment',
            'Advance Payment',
            'Payment Approved Date',
            'Payment Instructed Date',
            'Payment Confirmed Date',
            'Unsettled Date',
            'Unsettled Reason'
        ];
    }

    public function map(mixed $row): array
    {
        return [
            $row->{'Voucher No'},
            $row->{'Employee ID'},
            $row->{'Employee Name'},
            $row->{'Employee Code'},
            $row->Month,
            $row->{'Total Claims'},
            $row->{'Claim Amount'},
            $row->{'Verified Amount'},
            $row->{'Approved Amount'},
            $row->{'Finance Amount'},
            $row->{'Payment Amount'},
            $row->{'Payment Date'},
            $row->{'Payment Date (Alt)'},
            $row->{'Payment Status'},
            $row->{'Payment Mode'},
            $row->{'Paid By'},
            $row->{'Payment Remark'},
            $row->{'UTR Number'},
            $row->{'Bank Reference'},
            $row->{'Actual Expense'},
            $row->{'Total Adjustment'},
            $row->{'Advance Payment'},
            $row->{'Payment Approved Date'},
            $row->{'Payment Instructed Date'},
            $row->{'Payment Confirmed Date'},
            $row->{'Unsettled Date'},
            $row->{'Unsettled Reason'},
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ]
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->insertTimestampRow($e->sheet->getDelegate()),
        ];
    }
}
