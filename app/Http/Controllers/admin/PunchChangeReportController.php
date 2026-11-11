<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Claims\PunchChangeTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** PUNCH-TRACK-01: report of what was changed while punching claims (newest first), with Excel export. */
class PunchChangeReportController extends Controller
{
    private function query(Request $request)
    {
        $conn = (string) session('expense_connection');
        if (! Schema::connection($conn)->hasTable(PunchChangeTrack::TABLE)) {
            PunchChangeTrack::ensureTable($conn);
        }
        $co = (int) session('company_id', 1);
        $hr = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName($co);
        $from = $request->input('from') ?: now()->subDays(30)->toDateString();
        $to = $request->input('to') ?: now()->toDateString();
        $q = DB::connection($conn)->table(PunchChangeTrack::TABLE.' as p')
            ->leftJoin("{$hr}.hrm_employee as e", 'e.EmployeeID', '=', 'p.employee_id')
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'p.claim_id')
            ->where('p.company_id', $co)
            ->whereBetween('p.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select('p.*', 'e.EmpCode', DB::raw("TRIM(CONCAT_WS(' ', e.Fname, e.Lname)) AS emp_name"), 'ct.ClaimName');
        if ($s = trim((string) $request->input('emp'))) {
            $q->where(fn ($w) => $w->where('e.EmpCode', $s)->orWhere('e.Fname', 'like', "%{$s}%"));
        }
        if ($b = trim((string) $request->input('by'))) {
            $q->where('p.changed_by_name', 'like', "%{$b}%");
        }
        if ($m = $request->input('mode')) {
            $q->where('p.mode', $m);
        }
        if ($e = (int) $request->input('expid')) {
            $q->where('p.exp_id', $e);
        }

        return [$q->orderByDesc('p.created_at')->orderByDesc('p.id'), $from, $to];
    }

    public function index(Request $request)
    {
        [$q, $from, $to] = $this->query($request);
        $rows = $q->paginate(100)->withQueryString();

        return view('reports.punch-change-report', compact('rows', 'from', 'to'));
    }

    public function export(Request $request)
    {
        [$q] = $this->query($request);
        $data = $q->limit(50000)->get()->map(fn ($r) => [
            \Carbon\Carbon::parse($r->created_at)->format('d-m-Y H:i'), $r->EmpCode, $r->emp_name, $r->exp_id, $r->ClaimName ?? $r->claim_id,
            $r->claim_month, $r->field, $r->old_value, $r->new_value, $r->mode === 'first_punch' ? 'First punch' : 'Re-punch', $r->changed_by_name,
        ])->all();
        $export = new class($data) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\ShouldAutoSize
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Changed on', 'Emp code', 'Employee', 'ExpId', 'Head', 'Month', 'Field', 'Old value', 'New value', 'Type', 'Changed by'];
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'Punching_Change_Track_'.now()->format('Ymd_His').'.xlsx');
    }
}
