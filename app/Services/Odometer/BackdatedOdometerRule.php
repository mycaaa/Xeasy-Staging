<?php

namespace App\Services\Odometer;

use App\Support\CurrentYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ODO-BD-01 (VVNR back-dated odometer rule; decision 5 Nov 2026).
 *
 * Delay = entry date (CrDate) - odometer date (BillDate), in days. A running-km claim entered more
 * than the department's allowed days late (odo_backdate_settings.delayed_day), on or after the
 * rule's effective date, waits for back-date approval (Backdate_Odometer_Status = 'P'); the month's
 * final submission leaves it out until it is approved ('A').
 *
 * The VNR mobile path set 'P' when the claim was saved. VVNR claims are saved through VVNR's own
 * API, which never did, and VVNR had no settings table, so the rule never applied there. This
 * flags the claims from the database, whichever screen or app saved them, for both companies.
 */
class BackdatedOdometerRule
{
    /** @return array{company:int, year_id:int, departments:int, flagged:int, dry_run:bool} */
    public function run(int $companyId, bool $dryRun = false): array
    {
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $yearId = CurrentYear::id($conn);
        $stats = ['company' => $companyId, 'year_id' => $yearId, 'departments' => 0, 'flagged' => 0, 'dry_run' => $dryRun];
        if (! $yearId || ! Schema::connection($conn)->hasTable('odo_backdate_settings')) {
            return $stats;
        }
        $claims = "y{$yearId}_expenseclaims";
        $months = "y{$yearId}_monthexpensefinal";
        if (! Schema::connection($conn)->hasColumn($claims, 'Backdate_Odometer_Status')) {
            return $stats;
        }
        $hrims = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName($companyId);

        // LATE-PUNCH-02: only the departments named in config odo_backdate.departments (Sales)
        $names = array_filter(array_map('trim', explode(',', (string) config('odo_backdate.departments', 'Sales'))));
        $allowedDepts = [];
        try {
            $allowedDepts = DB::connection('hrims')->table('core_departments')->whereIn('department_name', $names)->pluck('id')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
        }
        foreach (DB::connection($conn)->table('odo_backdate_settings')->where('is_active', 1)->whereNotNull('effective_date')->get() as $s) {
            if ($names && ! in_array((int) $s->department_id, $allowedDepts, true)) {
                continue;
            }
            $stats['departments']++;
            $verticals = array_filter(array_map('trim', explode(',', (string) $s->verticals)));
            $q = DB::connection($conn)->table("{$claims} as e")
                ->join("{$hrims}.hrm_employee_general as g", 'g.EmployeeID', '=', 'e.CrBy')
                ->join("{$months} as m", function ($j) {
                    $j->on('m.EmployeeID', '=', 'e.CrBy')->on('m.Month', '=', 'e.ClaimMonth')->where('m.Status', '=', 'Open');
                })
                ->where('g.DepartmentId', $s->department_id)
                ->where('e.ClaimId', 7)
                ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivat'])
                ->whereNull('e.Backdate_Odometer_Status')
                ->whereNotNull('e.BillDate')->where('e.BillDate', '>', '0000-00-00')
                ->whereDate('e.CrDate', '>=', $s->effective_date)
                ->whereRaw('DATEDIFF(e.CrDate, e.BillDate) > ?', [(int) $s->delayed_day]);
            if ($verticals) {
                $q->whereIn('g.EmpVertical', $verticals);
            }
            $ids = $q->pluck('e.ExpId')->all();
            $stats['flagged'] += count($ids);
            if (! $dryRun && $ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::connection($conn)->table($claims)->whereIn('ExpId', $chunk)->whereNull('Backdate_Odometer_Status')
                        ->update(['Backdate_Odometer_Status' => 'P']);
                }
            }
        }
        Log::info('odometer:flag-backdated', $stats);

        return $stats;
    }
}
