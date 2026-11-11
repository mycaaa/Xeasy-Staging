<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Rates\EligibilityEffectiveDate;
use App\Services\Reports\VehicleRateGaps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VEH-RATE-REPORT-01: vehicle rates / vehicle documents HR has to update (screen, Excel, weekly mail).
 * RATE-EFF-01: recent eligibility changes and the date each one takes effect from (HR can change it).
 */
class VehicleRateGapsController extends Controller
{
    public const HEAD = ['Emp code', 'Employee', 'Department', 'Grade', 'Vehicle policy', '2W rate', '4W rate', '2W claims', '4W claims', 'Claims at ₹0 rate', 'Last bill', 'What to update'];

    public function index(Request $request)
    {
        $rows = (new VehicleRateGaps())->rows((int) session('company_id', 1));
        if ($f = $request->input('only')) {
            $rows = array_values(array_filter($rows, fn ($r) => $f === 'rate' ? $r['rate_missing'] : ! $r['rate_missing']));
        }

        // VEH-RATE-ALERT-01: log of the zero-rate mails to HR, newest first
        $alertMails = \Illuminate\Support\Facades\Schema::hasTable('vehicle_rate_alert_mails')
            ? \Illuminate\Support\Facades\DB::table('vehicle_rate_alert_mails as m')
                ->leftJoin('vehicle_rate_alerts as a', 'a.id', '=', 'm.alert_id')
                ->where('m.company_id', (int) session('company_id', 1))->orderByDesc('m.sent_at')->orderByDesc('m.id')->limit(300)
                ->get(['m.sent_at', 'm.emp_code', 'm.employee_name', 'm.issue', 'm.kind', 'm.status', 'm.to_list', 'm.cc_list', 'm.error', 'a.status as alert_status', 'a.send_count'])
            : collect();

        return view('reports.vehicle-rate-gaps', ['rows' => $rows, 'changes' => $this->recentChanges(), 'alertMails' => $alertMails]);
    }

    public function export(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(self::excel((new VehicleRateGaps())->rows((int) session('company_id', 1))), 'Vehicle_Rates_To_Update_'.now()->format('Ymd_His').'.xlsx');
    }

    public static function excel(array $rows)
    {
        $data = array_map(fn ($r) => [$r['emp_code'], $r['name'], $r['department'], $r['grade'], $r['policy'], $r['rate_2w'] ?: '', $r['rate_4w'] ?: '', $r['claims_2w'], $r['claims_4w'], $r['claims_at_zero'], $r['last_bill'], $r['issues']], $rows);

        return new class($data, self::HEAD) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\ShouldAutoSize
        {
            public function __construct(private array $rows, private array $head) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->head;
            }
        };
    }

    /** Eligibility rows created in the last 120 days for employees with more than one row, newest first. */
    private function recentChanges()
    {
        $hr = DB::connection(\App\Helpers\DatabaseSwitcher::getHrimsConnection((int) session('company_id', 1)));
        $rows = $hr->table('hrm_employee_eligibility as el')->join('hrm_employee as e', 'e.EmployeeID', '=', 'el.EmployeeID')
            ->where('el.EligCreatedDate', '>=', now()->subDays(120)->toDateString())->where('e.EmpStatus', 'A')
            ->whereIn('el.EmployeeID', fn ($q) => $q->from('hrm_employee_eligibility')->select('EmployeeID')->groupBy('EmployeeID')->havingRaw('COUNT(*) > 1'))
            ->orderByDesc('el.EligCreatedDate')->orderByDesc('el.EligibilityId')->limit(300)
            ->get(['el.EligibilityId', 'el.EmployeeID', 'e.EmpCode', DB::raw("TRIM(CONCAT_WS(' ', e.Fname, NULLIF(e.Sname,''), e.Lname)) AS name"), 'el.VehiclePolicy', 'el.Travel_TwoWeeKM', 'el.Travel_FourWeeKM', 'el.EligCreatedDate', 'el.Status']);
        $conn = (string) (session('expense_connection') ?: 'expense');
        $ov = [];
        foreach ($rows->pluck('EmployeeID')->unique() as $emp) {
            $ov += EligibilityEffectiveDate::overrides($conn, (int) $emp);
        }
        foreach ($rows as $r) {
            $r->effective_from = $ov[(int) $r->EligibilityId] ?? substr((string) $r->EligCreatedDate, 0, 10);
            $r->overridden = isset($ov[(int) $r->EligibilityId]);
        }

        return $rows;
    }

    public function setEffective(Request $request)
    {
        $v = $request->validate(['eligibility_id' => 'required|integer', 'effective_from' => 'required|date', 'remark' => 'nullable|string|max:255']);
        $hr = DB::connection(\App\Helpers\DatabaseSwitcher::getHrimsConnection((int) session('company_id', 1)));
        $row = $hr->table('hrm_employee_eligibility')->where('EligibilityId', $v['eligibility_id'])->first(['EligibilityId', 'EmployeeID']);
        abort_unless($row, 404);
        EligibilityEffectiveDate::set((string) (session('expense_connection') ?: 'expense'), (int) $row->EligibilityId, (int) $row->EmployeeID, $v['effective_from'], auth()->id(), $v['remark'] ?? null);

        return back()->with('success', 'Effective date saved. Claims are re-rated when they are punched or verified again.');
    }
}
