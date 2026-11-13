<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\ClaimRules\LateClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LATE-PUNCH-01 (13 Nov 2026): Late Bill Approval. Bills uploaded after their month's due date wait here until the
 * GM (VNR / Nursery) or an admin (all companies; VVNR admin only) approves them for data punching, or rejects them.
 */
class LateClaimApprovalController extends Controller
{
    private function ctx(): array
    {
        return [
            (string) session('expense_connection'),
            (int) session('year_id'),
            (int) session('company_id', 1),
            (int) \App\Support\RecordAccess::selfId(auth()->user()),
        ];
    }

    /** Late, not yet punched bills of the year (newest first), with what the viewer may do. */
    private function rows(string $show)
    {
        [$conn, $yearId, $companyId, $me] = $this->ctx();
        $user = auth()->user();
        $table = "y{$yearId}_expenseclaims";
        $due = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($d = LateClaim::dueDate($companyId, $yearId, $m, $conn)) {
                $due[$m] = $d;
            }
        }
        if (! $due) {
            return collect();
        }
        $q = DB::connection($conn)->table("{$table} as e")->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'e.ClaimId')
            ->where('e.ClaimYearId', $yearId)->where('e.ClaimId', '!=', 7)
            ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivat'])
            ->where(function ($w) use ($due) {
                foreach ($due as $m => $d) {
                    $w->orWhere(fn ($x) => $x->where('e.ClaimMonth', $m)->whereRaw('DATE(e.CrDate) > ?', [$d->toDateString()]));
                }
            });
        if ($show === 'pending') {
            $q->whereIn('e.ClaimStatus', ['Submitted', 'Draft', 'Open', ''])
                ->where(fn ($w) => $w->whereNull('e.Backdate_Odometer_Status')->orWhereNotIn('e.Backdate_Odometer_Status', ['A', 'R']));
        } else {
            $q->whereIn('e.Backdate_Odometer_Status', ['A', 'R']);
        }
        $remarkCol = \Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($table, 'Backdate_Final_Remark') ? 'e.Backdate_Final_Remark' : DB::raw('NULL as Backdate_Final_Remark');
        $rows = $q->orderByDesc('e.CrDate')->orderByDesc('e.ExpId')->limit(1000)
            ->get(['e.ExpId', 'e.CrBy', 'e.ClaimId', 'ct.ClaimName', 'e.BillDate', 'e.CrDate', 'e.ClaimMonth', 'e.FilledTAmt', 'e.ClaimStatus',
                'e.Backdate_Odometer_Status', 'e.Backdate_Odometer_By', 'e.Backdate_Odometer_Date', $remarkCol]);

        $may = [];
        $rows = $rows->filter(function ($r) use (&$may, $user, $me, $companyId, $conn) {
            $k = (int) $r->CrBy;

            return $may[$k] ??= LateClaim::canApprove($user, $me, $k, $companyId, $conn);
        })->values();

        $ids = $rows->pluck('CrBy')->merge($rows->pluck('Backdate_Odometer_By'))->filter()->unique()->all();
        $names = $ids ? DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $ids)
            ->get(['EmployeeID', 'EmpCode', DB::raw("TRIM(CONCAT(Fname, ' ', COALESCE(Lname, ''))) as n")])->keyBy('EmployeeID') : collect();

        return $rows->map(function ($r) use ($names, $due) {
            $e = $names[$r->CrBy] ?? null;
            $r->employee = $e ? "{$e->EmpCode} - {$e->n}" : (string) $r->CrBy;
            $r->decided_by = ($b = $names[$r->Backdate_Odometer_By] ?? null) ? $b->n : '';
            $r->due = isset($due[(int) $r->ClaimMonth]) ? $due[(int) $r->ClaimMonth]->format('d/m/Y') : '';

            return $r;
        });
    }

    public function index(Request $request)
    {
        $show = $request->input('show') === 'decided' ? 'decided' : 'pending';
        $rows = $this->rows($show);
        $companyId = (int) session('company_id', 1);
        $isAdmin = LateClaim::isAdmin(auth()->user());

        return view('admin.late_claims', compact('rows', 'show', 'companyId', 'isAdmin'));
    }

    public function decide(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'decision' => 'required|in:approve,reject', 'remark' => 'nullable|string|max:500']);
        [$conn, $yearId, $companyId, $me] = $this->ctx();
        $remark = trim((string) $request->input('remark', ''));
        if ($request->decision === 'reject' && mb_strlen($remark) < 3) {
            return back()->withErrors('Please enter the reason to reject.');
        }
        $user = auth()->user();
        $done = 0;
        $skipped = 0;
        foreach ($this->rows('pending')->whereIn('ExpId', array_map('intval', $request->ids)) as $r) {
            if (! LateClaim::canApprove($user, $me, (int) $r->CrBy, $companyId, $conn)) {
                $skipped++;
                continue;
            }
            LateClaim::decide($conn, $yearId, (int) $r->ExpId, $request->decision === 'approve' ? 'A' : 'R', $me ?: (int) $user->id, $remark);
            $done++;
        }
        $verb = $request->decision === 'approve' ? 'approved for punching' : 'rejected';

        return back()->with('success', "{$done} bill(s) {$verb}.".($skipped ? " {$skipped} skipped (not yours to approve)." : ''));
    }
}
