<?php

namespace App\Services\Reports;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;

/**
 * VEH-RATE-REPORT-01 (HR decision 7 Nov 2026): weekly list of ACTIVE employees whose own-vehicle rate
 * needs HR's attention, so HR can update it before more running-km claims are paid at ₹0:
 *   - claims running km (this year) on a two- / four-wheeler with no rate for that wheel type, or
 *   - has a vehicle policy but no rate for its wheel type;
 * and Own Your Car policy (12) without the vehicle value.
 * RC, insurance and licence are not checked here: HRIMS enables claim eligibility only after they are
 * uploaded (HR, 7 Nov 2026).
 * Inactive employees are left out (Finance: their rates need not be updated). Newest claim first.
 */
class VehicleRateGaps
{
    public function rows(int $companyId = 1, ?int $yearId = null): array
    {
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $hrConn = DatabaseSwitcher::getHrimsConnection($companyId);
        $yearId = $yearId ?: (int) (session('year_id') ?: \App\Support\CurrentYear::id($conn));
        $claims = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ClaimId', 7)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])
            ->groupBy('CrBy')
            ->select('CrBy', DB::raw("SUM(CASE WHEN WType IN ('2','2W') THEN 1 ELSE 0 END) AS c2"), DB::raw("SUM(CASE WHEN WType IN ('4','4W') THEN 1 ELSE 0 END) AS c4"),
                DB::raw('SUM(CASE WHEN COALESCE(RatePerKM,0) = 0 THEN 1 ELSE 0 END) AS zero_rate'), DB::raw('MAX(BillDate) AS last_bill'))
            ->get()->keyBy('CrBy');

        $hr = DB::connection($hrConn);
        $latest = $hr->table('hrm_employee_eligibility')->where('Status', 'A')->groupBy('EmployeeID')->select('EmployeeID', DB::raw('MAX(EligibilityId) AS mid'));
        $veh = $hr->table('hrm_employee_vehicle')->where('status', 'A')->groupBy('EmployeeID')->select('EmployeeID', DB::raw('MAX(id) AS vid'));
        $emps = $hr->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
            ->leftJoin('hrm_grade as gr', 'gr.GradeId', '=', 'g.GradeId')
            ->leftJoinSub($latest, 'm', 'm.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('hrm_employee_eligibility as el', 'el.EligibilityId', '=', 'm.mid')
            ->leftJoinSub($veh, 'vm', 'vm.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('hrm_employee_vehicle as v', 'v.id', '=', 'vm.vid')
            ->where('e.EmpStatus', 'A')
            ->select('e.EmployeeID', 'e.EmpCode', DB::raw("TRIM(CONCAT_WS(' ', e.Fname, NULLIF(e.Sname,''), e.Lname)) AS name"), 'd.department_name', 'gr.GradeValue AS grade',
                'el.Travel_TwoWeeKM', 'el.Travel_FourWeeKM', 'el.VehiclePolicy', 'v.id AS vehicle_id', 'v.vehicle_type', 'v.vehiclevalue', 'v.price')
            ->get();

        $rate = fn ($v) => (float) preg_replace('/[^0-9.]/', '', (string) $v);
        $out = [];
        foreach ($emps as $e) {
            $c = $claims[$e->EmployeeID] ?? null;
            $issues = [];
            $r2 = $rate($e->Travel_TwoWeeKM);
            $r4 = $rate($e->Travel_FourWeeKM);
            if ($c && (int) $c->c2 > 0 && $r2 <= 0) {
                $issues[] = 'No 2W rate (claims two-wheeler km)';
            }
            if ($c && (int) $c->c4 > 0 && $r4 <= 0) {
                $issues[] = 'No 4W rate (claims four-wheeler km)';
            }
            if (! $c && (int) $e->VehiclePolicy > 0 && $r2 <= 0 && $r4 <= 0) {
                $issues[] = 'Vehicle policy '.$e->VehiclePolicy.' but no rate';
            }
            if ((int) $e->VehiclePolicy === 12 && (float) $e->vehiclevalue <= 0 && (float) preg_replace('/[^0-9.]/', '', (string) $e->price) <= 0) {
                $issues[] = 'Own Your Car: vehicle value missing';
            }
            if (! $issues) {
                continue;
            }
            $out[] = [
                'emp_code' => (string) $e->EmpCode, 'name' => (string) $e->name, 'department' => (string) ($e->department_name ?? ''), 'grade' => (string) ($e->grade ?? ''),
                'policy' => (string) ($e->VehiclePolicy ?? ''), 'rate_2w' => $r2, 'rate_4w' => $r4,
                'claims_2w' => (int) ($c->c2 ?? 0), 'claims_4w' => (int) ($c->c4 ?? 0), 'claims_at_zero' => (int) ($c->zero_rate ?? 0),
                'last_bill' => (string) ($c->last_bill ?? ''), 'issues' => implode('; ', $issues),
                'rate_missing' => (bool) preg_grep('/rate/i', $issues),
            ];
        }
        usort($out, fn ($a, $b) => [$b['rate_missing'], $b['last_bill']] <=> [$a['rate_missing'], $a['last_bill']]);

        return $out;
    }
}
