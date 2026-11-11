<?php

namespace App\Exports\Reports;

use App\Models\ClaimActionLog;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Enumerable;

class ClaimActionLogExport implements FromCollection, ShouldAutoSize, WithHeadings, WithEvents
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            '#', 'Employee', 'Month', 'Exp ID',
            'Action', 'Done By Role', 'Done By',
            'Reason', 'From Status', 'To Status',
            'Return Count', 'Approved Date', 'Courier Receiving Date', 'Payment Date',
            'Date & Time',
        ];
    }

    public function collection(): Enumerable
    {
        $query = ClaimActionLog::query()
            ->where('company_id', session('company_id', 1));

        if ($this->request->filled('action'))      $query->where('action',       $this->request->action);
        if ($this->request->filled('month'))       $query->where('claim_month',  $this->request->month);
        if ($this->request->filled('year_id'))     $query->where('year_id',      $this->request->year_id);
        if ($this->request->filled('date_from'))   $query->whereDate('action_at', '>=', $this->request->date_from);
        if ($this->request->filled('date_to'))     $query->whereDate('action_at', '<=', $this->request->date_to);

        // Organogram Filters
        $organogramFilters = \App\Helpers\OrganogramFilterHelper::extractFilters($this->request);
        if (!empty(array_filter($organogramFilters))) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $orgEmpQuery = DB::connection($hrimsConn)->table('hrm_employee_general as eg')->select('eg.EmployeeID');
            \App\Helpers\OrganogramFilterHelper::applyToQuery($orgEmpQuery, $organogramFilters, 'eg', 'eg.EmployeeID', $hrimsDb);
            $orgEmpIds = $orgEmpQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('employee_id', !empty($orgEmpIds) ? $orgEmpIds : [-1]);
        }

        if ($this->request->filled('employee') || $this->request->filled('emp_code') || $this->request->filled('emp_name')) {
            $hrimsConn = session('hrims_connection', 'hrims');
            $empQuery = DB::connection($hrimsConn)->table('hrm_employee');

            if ($this->request->filled('employee')) {
                $term = trim($this->request->employee);
                $empQuery->where(function ($q) use ($term) {
                    $q->where('EmpCode', 'LIKE', "%{$term}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$term}%")
                      ->orWhere('ECode', 'LIKE', "%{$term}%")
                      ->orWhere('Fname', 'LIKE', "%{$term}%")
                      ->orWhere('Lname', 'LIKE', "%{$term}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$term}%"]);
                });
            }

            if ($this->request->filled('emp_code')) {
                $code = trim($this->request->emp_code);
                $empQuery->where(function ($q) use ($code) {
                    $q->where('EmpCode', 'LIKE', "%{$code}%")
                      ->orWhere('EmpCode_New', 'LIKE', "%{$code}%")
                      ->orWhere('ECode', 'LIKE', "%{$code}%");
                });
            }

            if ($this->request->filled('emp_name')) {
                $name = trim($this->request->emp_name);
                $empQuery->where(function ($q) use ($name) {
                    $q->where('Fname', 'LIKE', "%{$name}%")
                      ->orWhere('Lname', 'LIKE', "%{$name}%")
                      ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$name}%"]);
                });
            }

            $matchingIds = $empQuery->pluck('EmployeeID')->toArray();
            $query->whereIn('employee_id', !empty($matchingIds) ? $matchingIds : [-1]);
        }

        $logs = $query->orderByDesc('action_at')->get();

        // Batch employee names
        $empIds = $logs->pluck('employee_id')->unique()->filter()->toArray();
        $empNames = [];
        if (!empty($empIds)) {
            try {
                $rows = DB::connection(session('hrims_connection', 'hrims'))
                    ->table('hrm_employee')
                    ->whereIn('EmployeeID', $empIds)
                    ->select('EmployeeID',
                        DB::raw("CONCAT(COALESCE(Fname,''),' ',COALESCE(Sname,''),' ',COALESCE(Lname,'')) as emp_name"),
                        DB::raw("COALESCE(EmpCode, EmpCode_New, ECode) as emp_code"))
                    ->get()->keyBy('EmployeeID');
                foreach ($rows as $id => $r) {
                    $empNames[$id] = trim($r->emp_name) . ' (' . $r->emp_code . ')';
                }
            } catch (\Exception $e) {}
        }

        $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                       7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

        $roleLabels = [
            ClaimActionLog::ROLE_VERIFIER => 'Verifier',
            ClaimActionLog::ROLE_APPROVER => 'Approver',
            ClaimActionLog::ROLE_ADMIN    => 'Admin',
            ClaimActionLog::ROLE_EMPLOYEE => 'Employee',
        ];

        // Batch-fetch monthexpensefinal details
        $mefData = [];
        if ($logs->isNotEmpty()) {
            $expenseConn = session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
            $yearGroups = $logs->groupBy('year_id');
            foreach ($yearGroups as $yId => $grpLogs) {
                $yId = (int) $yId;
                if ($yId <= 0) continue;
                $empIdsForYear = $grpLogs->pluck('employee_id')->unique()->filter()->values()->toArray();
                $monthsForYear = $grpLogs->pluck('claim_month')->unique()->filter()->values()->toArray();
                if (!empty($empIdsForYear) && !empty($monthsForYear)) {
                    $finalTable = "y{$yId}_monthexpensefinal";
                    try {
                        $tableExists = DB::connection($expenseConn)->selectOne(
                            "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                            [$finalTable]
                        );
                        if ($tableExists && $tableExists->cnt > 0) {
                            $mefRows = DB::connection($expenseConn)
                                ->table($finalTable)
                                ->whereIn('EmployeeID', $empIdsForYear)
                                ->whereIn('Month', $monthsForYear)
                                ->get(['EmployeeID', 'Month', 'Approved_Date', 'RecevingDate', 'Fin_PayDate', 'payment_date']);
                            foreach ($mefRows as $mr) {
                                $mefData[$yId . '_' . $mr->EmployeeID . '_' . $mr->Month] = $mr;
                            }
                        }
                    } catch (\Exception $e) {}
                }
            }
        }

        $formatDate = function ($dt) {
            if (empty($dt) || \App\Support\ZeroDate::none($dt) || \App\Support\ZeroDate::none($dt)) {
                return '—';
            }
            try {
                return \Carbon\Carbon::parse($dt)->format('d-M-Y');
            } catch (\Exception $e) {
                return '—';
            }
        };

        $i = 1;
        return $logs->map(function ($log) use (&$i, $empNames, $monthNames, $roleLabels, $mefData, $formatDate) {
            $mefKey = $log->year_id . '_' . $log->employee_id . '_' . $log->claim_month;
            $mef = $mefData[$mefKey] ?? null;

            $approvedDate = ($mef && !empty($mef->Approved_Date) && \App\Support\ZeroDate::has($mef->Approved_Date))
                ? $formatDate($mef->Approved_Date)
                : '—';

            $courierRecvDate = ($mef && !empty($mef->RecevingDate) && \App\Support\ZeroDate::has($mef->RecevingDate))
                ? $formatDate($mef->RecevingDate)
                : '—';

            $paymentDate = ($mef && !empty($mef->Fin_PayDate) && \App\Support\ZeroDate::has($mef->Fin_PayDate))
                ? $formatDate($mef->Fin_PayDate)
                : (($mef && !empty($mef->payment_date) && \App\Support\ZeroDate::has($mef->payment_date))
                    ? $formatDate($mef->payment_date)
                    : '—');

            return [
                $i++,
                $empNames[$log->employee_id] ?? 'Emp #' . $log->employee_id,
                ($monthNames[$log->claim_month] ?? $log->claim_month) . ' ' . $log->year_id,
                $log->exp_id ?? '—',
                ucfirst($log->action),
                $roleLabels[$log->action_by_role] ?? $log->action_by_role,
                $log->action_by_name ?: 'User #' . $log->action_by,
                $log->reason ?? '—',
                $log->from_status ?? '—',
                $log->to_status ?? '—',
                $log->return_count,
                $approvedDate,
                $courierRecvDate,
                $paymentDate,
                \Carbon\Carbon::parse($log->action_at)->format('d-M-Y H:i'),
            ];
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Header style
                $sheet->getStyle('A1:O1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '15648C']],
                    'alignment' => ['horizontal' => 'center'],
                ]);

                // Borders
                $sheet->getStyle("A1:O{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle('thin');

                // Alternate row colors
                for ($r = 2; $r <= $lastRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:O{$r}")->getFill()
                            ->setFillType('solid')->getStartColor()->setRGB('F0F8FF');
                    }
                }
            },
        ];
    }
}
