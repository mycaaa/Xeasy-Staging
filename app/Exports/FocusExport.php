<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;

class FocusExport implements \App\Exports\Concerns\NoReportStamp, WithMultipleSheets, Export, Responsable
{
    use Exportable;

    protected Collection $focusData;

    public function __construct(Collection $focusData)
    {
        $this->focusData = $focusData;
    }

    public function sheets(): array
    {
        return [
            new FocusExportSheet('Focus Export', $this->focusData)
        ];
    }
}
