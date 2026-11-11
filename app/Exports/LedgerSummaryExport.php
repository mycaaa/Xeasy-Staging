<?php

namespace App\Exports;

use App\Exports\Concerns\WithGeneratedTimestamp;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class LedgerSummaryExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    use WithGeneratedTimestamp;
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    // public function collection()
    // {
    //     $expConn = session('expense_connection', 'expense');
    //     $companyId = session('company_id', 1);

    //     $query = DB::table('hrims.hrm_employee as e')
    //         ->leftJoin($expConn . '.employee_ledger_balances as lb', 'e.EmployeeID', '=', 'lb.employee_id')
    //         ->select(
    //             'e.EmployeeID as employee_id',
    //             'e.EmpStatus as status',
    //             DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,'')) as employee_name"),
    //             DB::raw("COALESCE(e.EmpCode, e.EmpCode_New, e.ECode) as display_code")
    //         )
    //         ->where('e.CompanyId', $companyId);

    //     if ($this->request->filled('status_filter')) {
    //         if ($this->request->status_filter == 'active') {
    //             $query->where('e.EmpStatus', 'A');
    //         } elseif ($this->request->status_filter == 'inactive') {
    //             $query->where('e.EmpStatus', '!=', 'A');
    //         }
    //     }

    //     if ($this->request->filled('search')) {
    //         $search = $this->request->search;
    //         $query->where(function ($q) use ($search) {
    //             $q->where(DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))"), 'LIKE', "%{$search}%")
    //                 ->orWhere('e.EmpCode', 'LIKE', "%{$search}%")
    //                 ->orWhere('e.EmpCode_New', 'LIKE', "%{$search}%");
    //         });
    //     }

    //     $records = $query->orderBy('e.Fname')->get();

    //     // Get all year IDs for debit calculation
    //     $allYearIds = DB::connection($expConn)
    //         ->table('financialyear')
    //         ->orderBy('YearId')
    //         ->pluck('YearId')
    //         ->toArray();

    //     $data = [];
    //     $i = 1;

    //     foreach ($records as $record) {
    //         $empId = $record->employee_id;

    //         // ─── Compute balance using same logic as computeEmployeeLedgerBalance ───
    //         $lb = DB::connection($expConn)
    //             ->table('employee_ledger_balances')
    //             ->where('employee_id', $empId)
    //             ->orderByDesc('id')
    //             ->first();

    //         $totalImprest = 0;
    //         $runningBalance = 0;

    //         if ($lb) {
    //             for ($s = 1; $s <= 2; $s++) {
    //                 $totalImprest += (float) ($lb->{"fixed_imprest_amt_$s"} ?? 0);
    //             }
    //             for ($s = 1; $s <= 4; $s++) {
    //                 $amt = (float) ($lb->{"advance_amt_$s"} ?? 0);
    //                 if ($amt > 0) {
    //                     $runningBalance += $amt;
    //                 }
    //             }
    //         }

    //         // Credit: Paid advances
    //         $paidRows = DB::connection($expConn)
    //             ->table('advance_requests as ar')
    //             ->join('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
    //             ->join('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
    //             ->where('are.employee_id', $empId)
    //             ->whereIn('ara.payment_status', ['Paid', 'Unsettled'])
    //             ->whereNull('ar.deleted_at')
    //             ->whereNull('are.deleted_at')
    //             ->whereNull('ara.deleted_at')
    //             ->select(DB::raw('COALESCE(ara.finance_approved_amount, ara.payment_amount, ara.final_amount, ara.amount) as amount'))
    //             ->get();

    //         foreach ($paidRows as $row) {
    //             $amt = (float) ($row->amount ?? 0);
    //             if ($amt > 0) {
    //                 $runningBalance += $amt;
    //             }
    //         }

    //         // Debit: Adjustments from monthexpensefinal (all years)
    //         foreach ($allYearIds as $yearId) {
    //             $mefTable = 'y' . $yearId . '_monthexpensefinal';
    //             try {
    //                 $rows = DB::connection($expConn)
    //                     ->table($mefTable)
    //                     ->where('EmployeeID', $empId)
    //                     ->whereIn('payment_status', ['ConfirmedPayment', 'InstructedPayment', 'Unsettled'])
    //                     ->orderByDesc('Month')
    //                     ->orderByDesc('id')
    //                     ->get();
    //             } catch (\Exception $e) {
    //                 continue;
    //             }

    //             foreach ($rows as $row) {
    //                 $jsonData = null;
    //                 if (!empty($row->carried_balance_json)) {
    //                     $jsonData = json_decode($row->carried_balance_json, true);
    //                     if (is_string($jsonData)) {
    //                         $jsonData = json_decode($jsonData, true);
    //                     }
    //                 }

    //                 $allAdvances = is_array($jsonData) ? array_merge(
    //                     $jsonData['ledger'] ?? [],
    //                     $jsonData['paid'] ?? [],
    //                     $jsonData['expense'] ?? [],
    //                     $jsonData['carried'] ?? []
    //                 ) : [];

    //                 if (!empty($allAdvances)) {
    //                     $hasRemaining = false;
    //                     foreach ($allAdvances as $adv) {
    //                         if ((float) ($adv['remaining_amount'] ?? 0) > 0) {
    //                             $hasRemaining = true;
    //                             break;
    //                         }
    //                     }
    //                     if ($hasRemaining) {
    //                         $runningBalance -= (float) ($row->total_adjustment ?? 0);
    //                     }
    //                 } elseif (!empty($row->total_adjustment) && $row->total_adjustment > 0) {
    //                     $runningBalance -= (float) $row->total_adjustment;
    //                 }
    //             }
    //         }

    //         $totalBalance = $totalImprest + $runningBalance;

    //         // Format employee code
    //         $codes = collect(preg_split('/\r\n|\r|\n/', $record->display_code))
    //             ->filter()
    //             ->map(function ($code) {
    //                 $code = trim($code);
    //                 return is_numeric($code) ? 'E' . str_pad($code, 4, '0', STR_PAD_LEFT) : $code;
    //             })->implode(', ');

    //         $data[] = [
    //             $i++,
    //             $record->employee_name,
    //             $codes,
    //             $totalImprest,
    //             $runningBalance,
    //             $totalBalance,
    //             $record->status,
    //         ];
    //     }

    //     return collect($data);
    // }

public function collection(): Enumerable
{
    $expConn = session('expense_connection', 'expense');
    $companyId = session('company_id', 1);
    $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

    // No join to employee_ledger_balances here: nothing is selected from it and an employee with a
    // ledger row in more than one year was exported once per row. Balances come from the engine below.
    $query = DB::table($hrimsDb.'.hrm_employee as e')
        ->leftJoin($hrimsDb.'.hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
        ->select(
            'e.EmployeeID as employee_id',
            'e.EmpStatus as status',
            'e.DateOfSepration as date_of_sepration',
            'e.DateOfResignation as date_of_resignation',
            DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,'')) as employee_name"),
            DB::raw("COALESCE(e.EmpCode, e.EmpCode_New, e.ECode) as display_code")
        )
        ->where('e.CompanyId', $companyId);

    if ($this->request->filled('search')) {
        $search = $this->request->search;
        $query->where(function ($q) use ($search) {
            $q->where(DB::raw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))"), 'LIKE', "%{$search}%")
                ->orWhere('e.EmpCode', 'LIKE', "%{$search}%")
                ->orWhere('e.EmpCode_New', 'LIKE', "%{$search}%");
        });
    }

    // Apply 10-level organogram filters
    $filters = \App\Helpers\OrganogramFilterHelper::extractFilters($this->request);
    \App\Helpers\OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'e.EmployeeID', $hrimsDb);

    $records = $query->orderBy('e.Fname')->get();

    // Pre-compute batch balances matching account summary / advance report
    $empIds = $records->pluck('employee_id')->filter()->unique()->values()->all();
    $controller = app(\App\Http\Controllers\ExpensePaymentController::class);
    $balancesMap = $controller->computeBatchEmployeeLedgerBalances($empIds);

    $data = [];
    $i = 1;

    foreach ($records as $record) {
        $empId = $record->employee_id;

        // ── Determine status using helper function ──────────
        $effectiveStatus = getEmployeeStatus(null, $record->employee_id ?? null);

        $bal = $balancesMap[$empId] ?? [
            'total_imprest'   => 0.0,
            'advance_balance' => 0.0,
            'total_balance'   => 0.0,
        ];

        $totalImprest   = $bal['total_imprest'];
        $runningBalance = $bal['advance_balance'];
        $totalBalance   = $bal['total_balance'];

        // Format employee code
        $codes = collect(preg_split('/\r\n|\r|\n/', $record->display_code))
            ->filter()
            ->map(function ($code) {
                $code = trim($code);
                return is_numeric($code) ? 'E' . str_pad($code, 4, '0', STR_PAD_LEFT) : $code;
            })->implode(', ');

        $data[] = [
            $i++,
            $record->employee_name,
            $effectiveStatus,
            $codes,
            $totalImprest,
            $runningBalance,
            $totalBalance,
        ];
    }

    return collect($data);
}

    public function headings(): array
    {
        return [
            'S.No',
            'Employee Name',
            'Status',
            'Employee Code',
            'Imprest Amount',
            'Advance Balance',
            'Total Balance',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->insertTimestampRow($sheet);
                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                // Header background
                $sheet->getStyle('A2:G2')->getFill()->applyFromArray([
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '15648C'],
                ]);

                // Borders
                $sheet->getStyle('A1:'.$lastColumn.$lastRow)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle('thin');

                // Center numeric columns
                $sheet->getStyle('E2:G'.$lastRow)
                    ->getAlignment()
                    ->setHorizontal('center');

                // Number format
                $sheet->getStyle('E2:G'.$lastRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            },
        ];
    }
}
