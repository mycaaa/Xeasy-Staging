<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Export;

class ClaimDashboardFullExport implements WithMultipleSheets, Export
{
    use Exportable;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [
            new ClaimStageSummaryExportNew($this->filters),
            new StagePendingDetailExport('open', $this->filters),
            new StagePendingDetailExport('submitted_no_courier', $this->filters),
            new StagePendingDetailExport('pending_verification', $this->filters),
            new StagePendingDetailExport('pending_approval', $this->filters),
            new StagePendingDetailExport('pending_payment', $this->filters),
            new TATAnalysisExport($this->filters),
            new FnFStatusExport($this->filters),
        ];
    }
}
