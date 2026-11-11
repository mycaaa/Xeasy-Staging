<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;

class PaymentExport implements \App\Exports\Concerns\NoReportStamp, WithMultipleSheets, Export, Responsable
{
    use Exportable;

    protected Collection $paymentData;

    public function __construct(Collection $paymentData)
    {
        $this->paymentData = $paymentData;
    }

    public function sheets(): array
    {
        return [
            new PaymentExportSheet('Payment Export', $this->paymentData)
        ];
    }
}