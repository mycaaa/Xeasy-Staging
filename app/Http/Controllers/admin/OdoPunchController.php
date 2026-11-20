<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use App\Services\Claims\OdoManualPunch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ODO-PUNCH-01 (20 Nov 2026, tracker tab "Decision for You" #3): odometer readings the employee changed by hand.
 * The punching team compares the photos with the readings, confirms or corrects them, and the claim can then be
 * verified. Readings the system read from the photos and the employee left alone never come here.
 */
class OdoPunchController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(OdoManualPunch::ready(), 503, 'Run the database update first (odometer readings to punch).');
        $companyId = (int) session('company_id', 1);
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $yearId = (int) session('year_id');
        $tab = $request->input('tab') === 'done' ? 'done' : 'pending';
        $rows = DB::connection(OdoManualPunch::home())->table('odo_manual_readings')->where('conn', $conn)->where('year_id', $yearId)
            ->when($tab === 'pending', fn ($q) => $q->where('status', 'pending'), fn ($q) => $q->whereIn('status', ['punched', 'not_needed']))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $claims = DB::connection($conn)->table("y{$yearId}_expenseclaims")->whereIn('ExpId', $rows->pluck('exp_id')->all())
            ->get(['ExpId', 'CrBy', 'BillDate', 'odomtr_opening', 'odomtr_closing', 'TotKm', 'opening_filepath', 'closing_filepath', 'ClaimStatus', 'RatePerKM', 'FilledTAmt'])->keyBy('ExpId');
        $emps = DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $rows->pluck('emp_id')->unique()->all())
            ->get(['EmployeeID', 'EmpCode', DB::raw("TRIM(CONCAT(Fname, ' ', COALESCE(Sname,''), ' ', Lname)) as name")])->keyBy('EmployeeID');
        $basePath = \App\Support\ClaimStorage::prefix($companyId);
        $s3 = app(\App\Services\S3Service::class);
        $img = function ($f, $emp) use ($basePath, $yearId, $s3) {
            if (! $f) {
                return null;
            }
            if (filter_var($f, FILTER_VALIDATE_URL)) {
                return $f;
            }
            try {
                return $s3->getFileViewUrl("{$basePath}/{$yearId}/{$emp}/".ltrim($f, '/'));
            } catch (\Throwable $e) {
                return null;
            }
        };
        $counts = [
            'pending' => DB::connection(OdoManualPunch::home())->table('odo_manual_readings')->where('conn', $conn)->where('year_id', $yearId)->where('status', 'pending')->count(),
        ];

        return view('admin.odo-punch', compact('rows', 'claims', 'emps', 'img', 'tab', 'counts'));
    }

    public function save(Request $request, int $id)
    {
        abort_unless(OdoManualPunch::ready(), 503);
        $v = $request->validate(['opening' => 'required|integer|min:0|max:9999999', 'closing' => 'required|integer|min:0|max:9999999', 'remark' => 'nullable|string|max:255']);
        abort_if($v['closing'] < $v['opening'], 422, 'Closing cannot be less than opening.');
        $companyId = (int) session('company_id', 1);
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $home = DB::connection(OdoManualPunch::home());
        $row = $home->table('odo_manual_readings')->where('id', $id)->where('conn', $conn)->where('status', 'pending')->first();
        if (! $row) {
            return back()->with('error', 'This reading is no longer waiting for punching.');
        }
        $table = "y{$row->year_id}_expenseclaims";
        $db = DB::connection($conn);
        $claim = $db->table($table)->where('ExpId', $row->exp_id)->first();
        if (! $claim || $claim->ClaimStatus === 'Deactivate') {
            $home->table('odo_manual_readings')->where('id', $id)->update(['status' => 'not_needed', 'remark' => 'Claim removed', 'updated_at' => now()]);

            return back()->with('error', 'The claim was removed by the employee.');
        }
        $me = (int) (session('employee_id') ?? session('EmployeeID') ?? 0) ?: null;
        $km = (int) $v['closing'] - (int) $v['opening'];
        $upd = ['odomtr_opening' => (int) $v['opening'], 'odomtr_closing' => (int) $v['closing'], 'TotKm' => $km];
        if ((float) ($claim->RatePerKM ?? 0) > 0) {
            $upd['FilledTAmt'] = round($km * (float) $claim->RatePerKM, 2);
        }
        $changed = (int) $claim->odomtr_opening !== (int) $v['opening'] || (int) $claim->odomtr_closing !== (int) $v['closing'];
        $db->beginTransaction();
        try {
            if ($changed) {
                (new \App\Services\Claims\ClaimChangeTracker($db, (int) $row->year_id))->record((int) $row->exp_id, $claim, $upd, 'odo_manual_punch', $v['remark'] ?? 'Odometer punched from the photo', (int) $claim->CrBy, $me);
                $db->table($table)->where('ExpId', $row->exp_id)->update($upd);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            report($e);

            return back()->with('error', 'Not saved: '.$e->getMessage());
        }
        $home->table('odo_manual_readings')->where('id', $id)->update(['status' => 'punched', 'punched_by' => auth()->id(), 'punched_at' => now(),
            'punched_opening' => (int) $v['opening'], 'punched_closing' => (int) $v['closing'], 'remark' => $v['remark'] ?? null, 'updated_at' => now()]);

        return back()->with('success', 'Punched: claim '.$row->exp_id.($changed ? " - readings corrected to {$v['opening']} / {$v['closing']} ({$km} km)" : ' - readings confirmed').'. It can now be verified.');
    }
}
