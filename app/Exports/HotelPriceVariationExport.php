<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Enumerable;

class HotelPriceVariationExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
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
            'Exp ID',
            'Hotel Name',
            'City',
            'Hotel Address',
            'Contact',
            'Bill No',
            'Bill Date',
            'Arrival',
            'Departure',
            'Stay Duration',
            'No of Pax',
            'Pax Details',
            'Room Rate Type',
            'Financed Amount',
            'Claim Status'
        ];
    }

    public function map(mixed $row): array
    {
        return [
            $row->ExpId,
            $row->HotelName,
            $row->CityName,
            $row->HotelAddress,
            $row->HotelContact,
            $row->BillNo,
            $row->BillDate ? date('d-m-Y', strtotime($row->BillDate)) : '-',
            $row->Arrival ? date('d-m-Y', strtotime($row->Arrival)) : '-',
            $row->Departure ? date('d-m-Y', strtotime($row->Departure)) : '-',
            $row->StayDuration,
            $row->NoOfPAX,
            $row->PaxDetails,
            $row->RoomRateType,
            number_format($row->FinancedTAmt, 2),
            $row->ClaimStatus
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0']
                ]
            ]
        ];
    }
}
