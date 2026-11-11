<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseClaim;
use App\Services\Claims\ClaimTransition;
use App\Services\Odometer\BackdateApproval as BA;
use Illuminate\Support\Facades\DB;
use Storage;

/**
 * Back-dated odometer approval (app: sales_odometer_approval; VNR and nursery approver screens).
 *
 * ODO-BD-02 (decision 6 Nov 2026): two steps. The claimant's reporting manager approves first, then
 * the BU head / HOD of the claimant's department (odo_backdate_settings.approval_type) gives the
 * final approval that releases the claim. Either step can reject, with a remark. The database is the
 * approver's company (VNR / VVNR / nursery). See App\Services\Odometer\BackdateApproval.
 */
class OdometerBackdateController extends Controller
{
    private const PENDING_LABEL = ['rm' => 'Pending with RM', 'final' => 'Pending with BU head/HOD'];

    /** Back-dated claims this approver can see: own RM team, plus BU / department if final approver. */
    private function candidates(int $yearId, int $employeeId)
    {
        $conn = BA::connFor($employeeId);
        $hrims = BA::hrimsDb($employeeId);
        $table = ExpenseClaim::tableName($yearId);
        $months = "y{$yearId}_monthexpensefinal";
        $hasRm = BA::hasRmColumns($conn, $table);

        // claimants who report to me (RM step) and, when I am a BU head / HOD, my BU or department (final step)
        $me = BA::general($employeeId);
        $team = DB::connection('hrims')->table('hrm_employee_general')->where('RepEmployeeID', $employeeId)->pluck('EmployeeID')->all();
        // ODO-BD-04: also the teams of my reports who are no longer available (their claims come up to me)
        $frontier = array_values(array_filter(array_map('intval', $team), fn ($id) => ! BA::available($id)));
        for ($depth = 0; $depth < 5 && $frontier; $depth++) {
            $more = DB::connection('hrims')->table('hrm_employee_general')->whereIn('RepEmployeeID', $frontier)->pluck('EmployeeID')->map(fn ($x) => (int) $x)->all();
            $more = array_values(array_diff($more, $team, [$employeeId]));
            $team = array_merge($team, $more);
            $frontier = array_values(array_filter($more, fn ($id) => ! BA::available($id)));
        }
        $finalSet = [];
        if ($me && $me->DepartmentId && ($s = BA::setting($conn, (int) $me->DepartmentId)) && (int) $s->is_active === 1) {
            if ($s->approval_type === 'bu_level' && BA::isBuGeneralManager($employeeId, (int) $me->BUId)) {
                $finalSet = DB::connection('hrims')->table('hrm_employee_general')->where('BUId', $me->BUId)->pluck('EmployeeID')->all();
            } elseif ($s->approval_type === 'hod_level' && BA::isHod($employeeId, (int) $me->DepartmentId)) {
                $finalSet = DB::connection('hrims')->table('hrm_employee_general')->where('DepartmentId', $me->DepartmentId)->pluck('EmployeeID')->all();
            }
        }
        $ids = array_values(array_unique(array_map('intval', array_merge($team, $finalSet))));
        if (! $ids || ! \Illuminate\Support\Facades\Schema::connection($conn)->hasTable('odo_backdate_settings')) {
            return [collect(), $conn, $hasRm];
        }

        $cols = ['e.CrBy', 'e.ExpId', 'e.ClaimYearId', 'e.BillDate', 'e.CrDate', 'e.WType', 'e.opening_filepath', 'e.closing_filepath', 'e.odomtr_opening', 'e.odomtr_closing', 'e.TotKm', 'e.RatePerKM', 'e.ClaimStatus', 'e.ClaimAtStep', 'e.Backdate_Odometer_Status',
            DB::raw('DATEDIFF(e.CrDate, e.BillDate) AS days_difference'), DB::raw("CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) AS employee_name"), 'emp.EmpCode', 'mef.Status as monthexpense_status', 'mef.Month as monthexpense_month', 's.approval_type'];
        if ($hasRm) {
            array_push($cols, 'e.Backdate_RM_Status', 'e.Backdate_RM_By', 'e.Backdate_RM_Date', 'e.Backdate_RM_Remark', 'e.Backdate_Final_Remark');
        }

        // each claimant's own department setting decides (effective date, verticals, allowed delay)
        $rows = DB::connection($conn)->table("{$table} as e")
            ->join("{$hrims}.hrm_employee as emp", 'emp.EmployeeID', '=', 'e.CrBy')
            ->join("{$hrims}.hrm_employee_general as gen", 'gen.EmployeeID', '=', 'e.CrBy')
            ->join('odo_backdate_settings as s', 's.department_id', '=', 'gen.DepartmentId')
            ->join("{$months} as mef", function ($join) {
                $join->on('mef.EmployeeID', '=', 'e.CrBy')->on('mef.Month', '=', 'e.ClaimMonth')->where('mef.Status', '=', 'Open');
            })
            ->where('s.is_active', 1)->whereNotNull('s.effective_date')
            ->whereRaw('DATE(e.CrDate) >= s.effective_date')
            ->whereRaw('(COALESCE(s.verticals, \'\') = \'\' OR FIND_IN_SET(gen.EmpVertical, s.verticals) > 0)')
            ->whereRaw('(COALESCE(s.delayed_day, 0) = 0 OR DATEDIFF(e.CrDate, e.BillDate) > s.delayed_day)')
            ->where('e.ClaimId', 7)->whereColumn('e.BillDate', '!=', 'e.CrDate')->whereNotNull('e.BillDate')
            ->whereIn('e.CrBy', $ids)
            ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivat'])
            ->whereNotNull('e.Backdate_Odometer_Status')
            ->get($cols);

        $teamSet = array_flip(array_map('intval', $team));
        $finalSetF = array_flip(array_map('intval', $finalSet));
        $rows = $rows->map(function ($r) use ($teamSet, $finalSetF, $hasRm, $employeeId, $conn) {
            $r->step = $r->Backdate_Odometer_Status === 'P' ? BA::stepOf($r, $hasRm) : null;
            $isRm = isset($teamSet[(int) $r->CrBy]) && BA::rmOf((int) $r->CrBy) === (int) $employeeId;
            $isFinal = isset($finalSetF[(int) $r->CrBy]) || ($r->step === 'final' && BA::isFinalApproverFor($conn, $employeeId, (int) $r->CrBy));
            $r->can_act = $r->Backdate_Odometer_Status === 'P' && (($r->step === 'rm' && $isRm) || ($r->step === 'final' && $isFinal));
            // show a row to the RM (own team) and to the final approver (BU / department)
            $r->visible = $isRm || $isFinal;

            return $r;
        })->filter(fn ($r) => $r->visible)->values();

        return [$rows, $conn, $hasRm];
    }

    public function odoBackdateSubmission($yearId, $employeeId)
    {
        [$records] = $this->candidates((int) $yearId, (int) $employeeId);
        if ($records->isEmpty()) {
            return $this->jsonSuccess([], 'No back-dated odometer claims for you.');
        }
        $grouped = $records->groupBy('CrBy')->map(function ($items) {
            $first = $items->first();
            $statusCounts = [
                'Pending' => $items->where('can_act', true)->count(),
                'Approved' => $items->where('Backdate_Odometer_Status', 'A')->count(),
                'Rejected' => $items->where('Backdate_Odometer_Status', 'R')->count(),
                'Waiting' => $items->where('Backdate_Odometer_Status', 'P')->where('can_act', false)->count(),
            ];

            return ['approval_type' => $first->approval_type, 'month' => $first->monthexpense_month ?? null, 'employee_name' => $first->employee_name ?? null, 'EmpCode' => $first->EmpCode ?? null, 'total_records' => $items->count(), 'status_count' => $statusCounts, 'items' => $items->map(function ($item) {
                $status = match ($item->Backdate_Odometer_Status) {
                    // the app shows the approve / reject buttons only for 'Pending'
                    'P' => $item->can_act ? 'Pending' : self::PENDING_LABEL[$item->step ?? 'final'],
                    'A' => 'Approved',
                    'R' => 'Rejected',
                    default => '',
                };

                return ['ExpId' => $item->ExpId, 'BillDate' => $item->BillDate, 'CrDate' => $item->CrDate, 'WType' => $item->WType, 'odomtr_opening' => $item->odomtr_opening, 'odomtr_closing' => $item->odomtr_closing,
                    'opening_filepath' => Storage::disk('s3')->url("Expense/{$item->ClaimYearId}/{$item->CrBy}/{$item->opening_filepath}"), 'closing_filepath' => Storage::disk('s3')->url("Expense/{$item->ClaimYearId}/{$item->CrBy}/{$item->closing_filepath}"),
                    'TotKm' => $item->TotKm, 'RatePerKM' => $item->RatePerKM, 'ClaimStatus' => $item->ClaimStatus, 'days_difference' => $item->days_difference, 'Backdate_Odometer_Status' => $status,
                    'approval_step' => $item->step, 'rm_status' => $item->Backdate_RM_Status ?? null, 'rm_remark' => $item->Backdate_RM_Remark ?? null, 'final_remark' => $item->Backdate_Final_Remark ?? null];
            })->toArray()];
        });

        return $this->jsonSuccess($grouped, 'Odometer backdate submission retrieved successfully.');
    }

    public function odoBackdateSubmissionBtnShow($yearId, $employeeId)
    {
        [$records] = $this->candidates((int) $yearId, (int) $employeeId);
        $pendingCount = $records->where('can_act', true)->count();

        return $this->jsonSuccess(['pending_count' => $pendingCount, 'show_button' => $pendingCount > 0], 'Odometer backdate pending count retrieved successfully.');
    }

    /** Load one claim with the fields the steps need. */
    private function claim(string $conn, int $yearId, int $expenseId, bool $hasRm): ?object
    {
        $cols = ['ExpId', 'CrBy', 'CrDate', 'ClaimStatus', 'ClaimAtStep', 'Backdate_Odometer_Status'];
        if ($hasRm) {
            $cols[] = 'Backdate_RM_Status';
        }

        return DB::connection($conn)->table(ExpenseClaim::tableName($yearId))->where('ExpId', $expenseId)->where('ClaimId', 7)->first($cols);
    }

    /**
     * Approve one claim for $approverId: the RM step, the final step, or both when the RM is also the
     * final approver. Returns [ok, message, step-done].
     */
    private function approveOne(string $conn, int $yearId, object $rec, int $approverId, bool $hasRm, string $mode): array
    {
        $table = ExpenseClaim::tableName($yearId);
        $step = BA::stepOf($rec, $hasRm);
        $claimant = (int) $rec->CrBy;
        $isFinal = BA::isFinalApproverFor($conn, $approverId, $claimant);
        if ($step === 'rm') {
            if (BA::rmOf($claimant) !== $approverId) {
                return [false, "This claim is waiting for the employee's reporting manager.", null];
            }
            try {
                ClaimTransition::guard($conn, $yearId, (int) $rec->ExpId, 'Filled', 'approver', 'backdate_odometer_rm', null, $mode, $approverId);
            } catch (\App\Services\Claims\ClaimTransitionDenied $e) {
                return [false, $e->getMessage(), null];
            }
            $upd = ['Backdate_RM_Status' => 'A', 'Backdate_RM_By' => $approverId, 'Backdate_RM_Date' => date('Y-m-d')];
            if (! $isFinal) {
                DB::connection($conn)->table($table)->where('ExpId', $rec->ExpId)->update($upd);
                BA::log('rm approved', ['exp' => $rec->ExpId, 'by' => $approverId, 'conn' => $conn]);

                return [true, 'Approved by the reporting manager; it now goes to the BU head/HOD.', 'rm'];
            }
            BA::log('rm is also the final approver: one approval counts for both', ['exp' => $rec->ExpId, 'by' => $approverId, 'conn' => $conn]);
            DB::connection($conn)->table($table)->where('ExpId', $rec->ExpId)->update($upd);
        } elseif (! $isFinal) {
            return [false, 'Only the BU head/HOD can give the final approval for this claim.', null];
        }
        // final step: releases the claim (B-01: approver, pre-verification status; LV-C08: step 3)
        try {
            ClaimTransition::guard($conn, $yearId, (int) $rec->ExpId, 'Filled', 'approver', 'backdate_odometer_approve', null, $mode, $approverId);
        } catch (\App\Services\Claims\ClaimTransitionDenied $e) {
            return [false, $e->getMessage(), null];
        }
        DB::connection($conn)->table($table)->where('ExpId', $rec->ExpId)->update([
            'Backdate_Odometer_Status' => 'A', 'Backdate_Odometer_By' => $approverId, 'Backdate_Odometer_Date' => date('Y-m-d'),
            'ClaimStatus' => 'Filled', 'ClaimAtStep' => DB::raw('GREATEST(COALESCE(ClaimAtStep, 0), 3)'),
        ]);
        BA::log('final approved', ['exp' => $rec->ExpId, 'by' => $approverId, 'conn' => $conn]);

        return [true, 'The backdated odometer has been approved successfully.', 'final'];
    }

    private function rejectOne(string $conn, int $yearId, object $rec, int $approverId, bool $hasRm, string $remark): array
    {
        $table = ExpenseClaim::tableName($yearId);
        $step = BA::stepOf($rec, $hasRm);
        $claimant = (int) $rec->CrBy;
        if ($step === 'rm' && BA::rmOf($claimant) !== $approverId) {
            return [false, "This claim is waiting for the employee's reporting manager."];
        }
        if ($step === 'final' && ! BA::isFinalApproverFor($conn, $approverId, $claimant)) {
            return [false, 'Only the BU head/HOD can reject this claim at the final step.'];
        }
        $upd = ['Backdate_Odometer_Status' => 'R', 'Backdate_Odometer_By' => $approverId, 'Backdate_Odometer_Date' => date('Y-m-d')];
        if ($hasRm) {
            if ($step === 'rm') {
                $upd += ['Backdate_RM_Status' => 'R', 'Backdate_RM_By' => $approverId, 'Backdate_RM_Date' => date('Y-m-d'), 'Backdate_RM_Remark' => $remark];
            } else {
                $upd['Backdate_Final_Remark'] = $remark;
            }
        }
        DB::connection($conn)->table($table)->where('ExpId', $rec->ExpId)->update($upd);
        BA::log('rejected', ['exp' => $rec->ExpId, 'step' => $step, 'by' => $approverId, 'conn' => $conn]);

        return [true, 'The backdated odometer has been rejected successfully.'];
    }

    private function remark(): ?string
    {
        $r = trim((string) request('remark', ''));
        if ($r === '' && config('odo_backdate.reject_remark_required', true)) {
            return null;
        }

        return mb_substr($r, 0, 500);
    }

    public function odoBackdateApprove($yearId, $employeeId, $expenseId)
    {
        $conn = BA::connFor((int) $employeeId);
        $hasRm = BA::hasRmColumns($conn, ExpenseClaim::tableName((int) $yearId));
        $rec = $this->claim($conn, (int) $yearId, (int) $expenseId, $hasRm);
        if (! $rec) {
            return $this->jsonError('The expense claim could not be found. Please check the claim details.');
        }
        if ($rec->Backdate_Odometer_Status === 'A') {
            return $this->jsonError('The backdated odometer has already been approved.');
        }
        if ($rec->Backdate_Odometer_Status === 'R') {
            return $this->jsonError('The backdated odometer has already been rejected and cannot be approved.');
        }
        if ($rec->Backdate_Odometer_Status !== 'P') {
            return $this->jsonError('This claim is not waiting for back-date approval.');
        }
        [$ok, $msg] = $this->approveOne($conn, (int) $yearId, $rec, (int) $employeeId, $hasRm, 'app');

        return $ok ? $this->jsonSuccess($this->claim($conn, (int) $yearId, (int) $expenseId, $hasRm), $msg) : $this->jsonError($msg, 403);
    }

    public function odoBackdateReject($yearId, $employeeId, $expenseId)
    {
        $remark = $this->remark();
        if ($remark === null) {
            return $this->jsonError('Please enter a remark to reject.', 422);
        }
        $conn = BA::connFor((int) $employeeId);
        $hasRm = BA::hasRmColumns($conn, ExpenseClaim::tableName((int) $yearId));
        $rec = $this->claim($conn, (int) $yearId, (int) $expenseId, $hasRm);
        if (! $rec) {
            return $this->jsonError('The expense claim could not be found. Please check the claim details.');
        }
        if ($rec->Backdate_Odometer_Status === 'R') {
            return $this->jsonError('The backdated odometer has already been rejected.');
        }
        if ($rec->Backdate_Odometer_Status === 'A') {
            return $this->jsonError('The backdated odometer has already been approved and cannot be rejected.');
        }
        [$ok, $msg] = $this->rejectOne($conn, (int) $yearId, $rec, (int) $employeeId, $hasRm, $remark);

        return $ok ? $this->jsonSuccess($this->claim($conn, (int) $yearId, (int) $expenseId, $hasRm), $msg) : $this->jsonError($msg, 403);
    }

    /** Pending back-dated claims of one claimant (bulk actions). */
    private function pendingOf(string $conn, int $yearId, int $claimant, bool $hasRm)
    {
        $cols = ['ExpId', 'CrBy', 'CrDate', 'ClaimStatus', 'ClaimAtStep', 'Backdate_Odometer_Status'];
        if ($hasRm) {
            $cols[] = 'Backdate_RM_Status';
        }

        return DB::connection($conn)->table(ExpenseClaim::tableName($yearId))->where('CrBy', $claimant)->where('ClaimId', 7)
            ->where('Backdate_Odometer_Status', 'P')->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat'])->where('ClaimAtStep', '<', 3)->get($cols);
    }

    public function odoBackdateBulkApproval($yearId, $employeeId, $empid)
    {
        $conn = BA::connFor((int) $employeeId);
        $hasRm = BA::hasRmColumns($conn, ExpenseClaim::tableName((int) $yearId));
        $rm = 0;
        $final = 0;
        foreach ($this->pendingOf($conn, (int) $yearId, (int) $empid, $hasRm) as $rec) {
            [$ok, , $done] = $this->approveOne($conn, (int) $yearId, $rec, (int) $employeeId, $hasRm, 'bulk');
            if ($ok) {
                $done === 'rm' ? $rm++ : $final++;
            }
        }
        $updated = $rm + $final;
        $msg = $updated === 0 ? 'No pending backdated odometer records were found for your approval.'
            : trim(($final ? "{$final} record(s) approved and released. " : '').($rm ? "{$rm} record(s) approved by the reporting manager and sent to the BU head/HOD." : ''));

        return $this->jsonSuccess(['updated_count' => $updated, 'released' => $final, 'sent_to_final' => $rm], $msg);
    }

    public function odoBackdateBulkRejection($yearId, $employeeId, $empid)
    {
        $remark = $this->remark();
        if ($remark === null) {
            return $this->jsonError('Please enter a remark to reject.', 422);
        }
        $conn = BA::connFor((int) $employeeId);
        $hasRm = BA::hasRmColumns($conn, ExpenseClaim::tableName((int) $yearId));
        $count = 0;
        foreach ($this->pendingOf($conn, (int) $yearId, (int) $empid, $hasRm) as $rec) {
            [$ok] = $this->rejectOne($conn, (int) $yearId, $rec, (int) $employeeId, $hasRm, $remark);
            $count += $ok ? 1 : 0;
        }

        return $this->jsonSuccess(['updated_count' => $count],
            $count > 0 ? "{$count} backdated odometer record(s) have been rejected successfully." : 'No pending backdated odometer records were found for your rejection.');
    }
}
