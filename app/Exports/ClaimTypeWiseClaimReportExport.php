<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Facades\DB;
use App\Exports\ClaimReportExport;
use Maatwebsite\Excel\Concerns\Export;

class ClaimTypeWiseClaimReportExport implements WithMultipleSheets, Export
{
    protected $query;
    protected $filters;
    protected $columns;
    protected $protectSheets;
    protected $table;
    protected $verifyAmtVersions;
    protected $apprAmtVersions;

    public function __construct($query, array $filters, array $columns, bool $protectSheets = false, $table, array $verifyAmtVersions = ['current'], array $apprAmtVersions = ['current'])
    {
        $this->query = $query;
        $this->filters = $filters;
        $this->columns = $columns;
        $this->protectSheets = $protectSheets;
        $this->table = $table;
        $this->verifyAmtVersions = $verifyAmtVersions;
        $this->apprAmtVersions   = $apprAmtVersions;
    }

    public function sheets(): array
    {
        $sheets = [];
        $conn = session('expense_connection') ?: 'expense';
        $claimTypes = DB::connection($conn)->table('claimtype')
            ->select('ClaimId', 'ClaimName')
            ->where('ClaimStatus', 'A')
            ->orderBy('ClaimName')
            ->get()
            ->mapWithKeys(fn($item) => [$item->ClaimId => $item->ClaimName])
            ->toArray();

        if (empty($claimTypes)) {
            $sheets[] = new ClaimReportExport(
                $this->query,
                $this->filters,
                $this->columns,
                'No Data',
                $this->protectSheets,
                $this->table,
                $this->verifyAmtVersions,
                $this->apprAmtVersions
            );
            return $sheets;
        }

        foreach ($claimTypes as $claimId => $claimName) {
            $claimQuery = (clone $this->query)->where('claimtype.ClaimId', $claimId);

            $count = (clone $claimQuery)->count(DB::raw("DISTINCT {$this->table}.ExpId"));

            if ($count > 0) {
                $claimFilters = array_merge($this->filters, ['claim_type_ids' => [$claimId]]);
                $sheets[] = new ClaimReportExport(
                    $claimQuery,
                    $claimFilters,
                    $this->columns,
                    $claimName ?? 'Claim Type ' . $claimId,
                    $this->protectSheets,
                    $this->table,
                    $this->verifyAmtVersions,
                    $this->apprAmtVersions
                );
            }
        }

        if (empty($sheets)) {
            $sheets[] = new ClaimReportExport(
                $this->query,
                $this->filters,
                $this->columns,
                'No Data',
                $this->protectSheets,
                $this->table,
                $this->verifyAmtVersions,
                $this->apprAmtVersions
            );
        }

        return $sheets;
    }
}
