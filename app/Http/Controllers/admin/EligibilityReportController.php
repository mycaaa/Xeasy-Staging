<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ELIG-REPORT-01 (7 Nov 2026): every employee's travel and expense eligibility from HRIMS on one
 * screen (lodging by city category, DA, own-vehicle rates and limits, travel class, mobile),
 * with filters and an Excel export. Read-only; the latest active eligibility row is used.
 */
class EligibilityReportController extends Controller
{
    public const COLUMNS = [
        'EmpCode' => 'Emp code', 'Name' => 'Employee', 'Department' => 'Department', 'Grade' => 'Grade', 'HQ' => 'HQ', 'Status' => 'Status',
        'Lodging_CategoryA' => 'Lodging A (per night)', 'Lodging_CategoryB' => 'Lodging B (per night)', 'Lodging_CategoryC' => 'Lodging C (per night)',
        'DA_Outside_Hq' => 'DA outside HQ', 'DA_Outside_HqWithNightH' => 'DA outside HQ with night halt', 'DA_Outside_HqWithOutNightH' => 'DA outside HQ without night halt', 'DA_Inside_Hq' => 'DA at HQ',
        'Travel_TwoWeeKM' => '2W rate (per km)', 'Travel_TwoWeeLimitPerMonth' => '2W km limit / month', 'Travel_FourWeeKM' => '4W rate (per km)', 'Travel_FourWeeLimitPerMonth' => '4W km limit / month',
        'VehiclePolicy' => 'Vehicle policy', 'Mode_Travel_Outside_Hq' => 'Travel mode outside HQ', 'TravelClass_Outside_Hq' => 'Travel class outside HQ',
        'Flight_Allow' => 'Flight allowed', 'Flight_Class' => 'Flight class', 'Train_Class' => 'Train class', 'Mobile_Exp_Rem_Rs' => 'Mobile (Rs)',
        'DateOfEntryPolicy' => 'Policy from', 'EligYearId' => 'Eligibility year', 'EligCreatedDate' => 'Eligibility updated on',
    ];

    private function query(Request $request)
    {
        $co = (int) session('company_id', 1);
        $hr = DB::connection(DatabaseSwitcher::getHrimsConnection($co));
        $latest = $hr->table('hrm_employee_eligibility')->where('Status', 'A')->groupBy('EmployeeID')->select('EmployeeID', DB::raw('MAX(EligibilityId) AS mid'));
        $q = $hr->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('hrm_grade as gr', 'gr.GradeId', '=', 'g.GradeId')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
            ->leftJoinSub($latest, 'm', 'm.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('hrm_employee_eligibility as el', 'el.EligibilityId', '=', 'm.mid')
            ->select(array_merge([
                'e.EmployeeID', 'e.EmpCode', DB::raw("TRIM(CONCAT_WS(' ', e.Fname, NULLIF(e.Sname,''), e.Lname)) AS Name"),
                'd.department_name AS Department', 'gr.GradeValue AS Grade', DB::raw("COALESCE(NULLIF(g.HQ,''), g.HqqName) AS HQ"),
                DB::raw("CASE e.EmpStatus WHEN 'A' THEN 'Active' WHEN 'R' THEN 'Resigned' WHEN 'D' THEN 'Deactive' ELSE e.EmpStatus END AS Status"),
            ], array_map(fn ($c) => "el.{$c}", array_slice(array_keys(self::COLUMNS), 6))));
        $status = $request->input('status', 'A');
        if ($status !== 'all') {
            $q->where('e.EmpStatus', $status);
        }
        if ($d = (int) $request->input('department')) {
            $q->where('g.DepartmentId', $d);
        }
        if ($g = (int) $request->input('grade')) {
            $q->where('g.GradeId', $g);
        }
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('e.EmpCode', 'like', "%{$s}%")->orWhere('e.Fname', 'like', "%{$s}%")->orWhere('e.Lname', 'like', "%{$s}%"));
        }
        if ($request->input('missing') === '1') {
            $q->where(fn ($w) => $w->whereNull('el.EligibilityId')->orWhere('el.Lodging_CategoryA', 0)->orWhereNull('el.Lodging_CategoryA'));
        }

        return [$hr, $q->orderBy('d.department_name')->orderBy('e.EmpCode')];
    }

    public function index(Request $request)
    {
        [$hr, $q] = $this->query($request);
        $rows = $q->paginate(100)->withQueryString();
        $departments = $hr->table('core_departments')->where('is_active', 1)->orderBy('department_name')->pluck('department_name', 'id');
        $grades = $hr->table('hrm_grade')->where('GradeStatus', 'A')->orderBy('GradeValue')->pluck('GradeValue', 'GradeId');
        $columns = self::COLUMNS;

        return view('reports.eligibility-report', compact('rows', 'departments', 'grades', 'columns'));
    }

    public function export(Request $request)
    {
        [, $q] = $this->query($request);
        $keys = array_keys(self::COLUMNS);
        $data = $q->get()->map(fn ($r) => array_map(fn ($k) => $r->{$k} ?? '', $keys))->all();
        $export = new class($data, array_values(self::COLUMNS)) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\ShouldAutoSize
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

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'Employee_Eligibility_'.now()->format('Ymd_His').'.xlsx');
    }
}
