<?php

namespace App\Http\Controllers\Api\Advance;

use App\Http\Controllers\Controller;
use App\Services\Advance\AdvanceContext;
use App\Services\Advance\FxEligibility;
use App\Support\RecordAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 14 Nov 2026 advance changes:
 *  ADV-CONTEXT-01  employee-context: ledger balance, open advances, A/c Summary link and (activity advance) the last
 *                  advance for the same activity / crop / variety, for the request form and the approve dialog;
 *  ADV-FX-ELIG-01  fx-eligibility: which employees may request in a foreign currency;
 *  ADV-WITHDRAW-01 withdraw: the requester withdraws a request line that is still waiting for approval.
 */
class AdvanceExtrasController extends Controller
{
    /** Acting employee: the web login, else the app token (ApiAuthenticate sets it). */
    private function self(Request $request): int
    {
        if (auth()->check()) {
            return RecordAccess::selfId(auth()->user());
        }

        return (int) $request->attributes->get('api_auth_empid', 0);
    }

    private function company(Request $request): int
    {
        return (int) ($request->input('company_id') ?: session('company_id') ?: 1);
    }

    /** The viewer may see this employee's advances: self, staff, someone above them, or an approver of their advances. */
    private function maySee(int $self, int $employeeId): bool
    {
        if ($self > 0 && $self === $employeeId) {
            return true;
        }
        $user = auth()->user();
        if ($user && RecordAccess::canViewEmployee($user, $employeeId)) {
            return true;
        }
        if ($self > 0 && in_array($self, RecordAccess::managersOf($employeeId), true)) {
            return true;
        }
        try {
            return $self > 0 && DB::table('advance_activity_approvals as ap')
                ->join('advance_request_activities as a', 'a.id', '=', 'ap.advance_request_activity_id')
                ->join('advance_request_employees as e', 'e.id', '=', 'a.advance_request_employee_id')
                ->where('ap.approver_id', $self)->where('e.employee_id', $employeeId)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** GET /api/advance/employee-context?employee_ids[]=..&activity_name_id=&crops[]=&varieties[]=&exclude_request_id= */
    public function context(Request $request)
    {
        $self = $this->self($request);
        if ($self <= 0) {
            return response()->json(['success' => false, 'message' => 'Please sign in again.'], 401);
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map("intval", (array) $request->input("employee_ids", []))))), 0, 100);
        $company = $this->company($request);
        $yearId = (int) ($request->input('year_id') ?: session('year_id') ?: 0);
        $out = [];
        foreach ($ids as $id) {
            if (! $this->maySee($self, $id)) {
                continue;
            }
            $out[] = AdvanceContext::forEmployee($id, $company, $yearId, (int) $request->input('activity_name_id') ?: null,
                (array) $request->input('crops', []), (array) $request->input('varieties', []),
                (int) $request->input('exclude_request_id') ?: null);
        }

        return response()->json(['success' => true, 'data' => $out]);
    }

    /** GET /api/advance/fx-eligibility?employee_ids[]=.. (the caller is always included) */
    public function fxEligibility(Request $request)
    {
        $self = $this->self($request);
        if ($self <= 0) {
            return response()->json(['success' => false, 'message' => 'Please sign in again.'], 401);
        }
        $ids = array_values(array_unique(array_merge([$self], array_map('intval', (array) $request->input('employee_ids', [])))));
        $map = FxEligibility::check(array_slice($ids, 0, 200), $this->company($request));

        return response()->json(['success' => true, 'data' => ['self' => (bool) ($map[$self] ?? false), 'employees' => $map]]);
    }

    /** POST /api/advance/withdraw/{lineId}  (lineId = advance_request_employees.id) */
    public function withdraw(Request $request, int $lineId)
    {
        $self = $this->self($request);
        $line = DB::table('advance_request_employees')->where('id', $lineId)->whereNull('deleted_at')->first();
        $req = $line ? DB::table('advance_requests')->where('id', $line->advance_request_id)->whereNull('deleted_at')->first() : null;
        if (! $req) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if ($self <= 0 || (int) $req->created_by !== $self) {
            return response()->json(['success' => false, 'message' => 'Only the person who raised the request can withdraw it.'], 403);
        }
        if (! in_array($req->current_status, ['Submitted', 'In_Approval'], true)) {
            return response()->json(['success' => false, 'message' => 'Only a request waiting for approval can be withdrawn (it is '.str_replace('_', ' ', $req->current_status).').'], 422);
        }
        $acts = DB::table('advance_request_activities')->where('advance_request_employee_id', $lineId)->whereNull('deleted_at')->get(['id', 'approval_status', 'finance_status', 'payment_amount']);
        if ($acts->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nothing to withdraw.'], 422);
        }
        foreach ($acts as $a) {
            $fin = strtolower(trim((string) $a->finance_status));
            if (strcasecmp((string) $a->approval_status, 'Pending') !== 0 || ($fin !== '' && $fin !== 'pending') || (float) $a->payment_amount > 0) {
                return response()->json(['success' => false, 'message' => 'This request has already been acted on, so it can no longer be withdrawn.'], 422);
            }
        }
        // pending approval at the next stage (earlier levels may have approved); not once it is finally approved,
        // rejected or with Finance
        $actIds = $acts->pluck('id')->all();
        $remark = trim((string) $request->input('remark', '')) ?: 'Withdrawn by the requester';

        DB::transaction(function () use ($req, $actIds, $self, $remark, $lineId) {
            DB::table('advance_request_activities')->whereIn('id', $actIds)->update(['approval_status' => 'Withdrawn', 'updated_at' => now()]);
            DB::table('advance_activity_approvals')->whereIn('advance_request_activity_id', $actIds)->where('submission_no', $req->submission_no)
                ->where('approver_status', 'Pending')->update(['approver_status' => 'Withdrawn', 'approver_remark' => mb_substr($remark, 0, 250), 'approver_date' => now(), 'updated_at' => now()]);
            $left = DB::table('advance_request_activities as a')->join('advance_request_employees as e', 'e.id', '=', 'a.advance_request_employee_id')
                ->where('e.advance_request_id', $req->id)->whereNull('a.deleted_at')->whereNull('e.deleted_at')
                ->where('a.approval_status', '!=', 'Withdrawn')->exists();
            if (! $left) {
                DB::table('advance_requests')->where('id', $req->id)->update(['current_status' => 'Withdrawn', 'updated_by' => $self, 'updated_at' => now()]);
            }
            try {
                DB::table('advance_request_actions')->insert(['advance_request_id' => $req->id, 'submission_no' => $req->submission_no, 'action_type' => 'Withdraw',
                    'action_by' => $self, 'remarks' => mb_substr($remark.' (line '.$lineId.')', 0, 250), 'action_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('Advance withdraw not logged: '.$e->getMessage());
            }
        });

        return response()->json(['success' => true, 'message' => 'Request withdrawn.']);
    }
}
