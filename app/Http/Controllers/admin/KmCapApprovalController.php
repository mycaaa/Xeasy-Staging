<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\ClaimRules\KmCapApproval;
use App\Support\RecordAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** KM-CAP-01: the HOD approves (or not) running km above the department's monthly limit. */
class KmCapApprovalController extends Controller
{
    private function conn(): string
    {
        return (string) (session('expense_connection') ?: 'expense');
    }

    private function me(): int
    {
        return (int) (session('employee_id') ?: RecordAccess::selfId(auth()->user()));
    }

    public function index(Request $request)
    {
        $conn = $this->conn();
        KmCapApproval::ensureTable($conn);
        $staff = RecordAccess::isStaff(auth()->user());
        $status = (string) $request->query('status', 'pending');
        $rows = DB::connection($conn)->table(KmCapApproval::TABLE)
            ->when(! $staff, fn ($q) => $q->where('hod_id', $this->me()))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->where('company_id', (int) session('company_id', 1))
            ->orderByDesc('id')->limit(500)->get();
        $names = DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $rows->pluck('employee_id')->merge($rows->pluck('hod_id'))->filter()->unique()->all())
            ->get(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname'])->keyBy('EmployeeID');

        return view('admin.km-cap-approvals', compact('rows', 'names', 'staff', 'status'));
    }

    public function decide(Request $request, int $id)
    {
        $request->validate(['decision' => 'required|in:approved,rejected', 'remark' => 'nullable|string|max:500']);
        $conn = $this->conn();
        $row = DB::connection($conn)->table(KmCapApproval::TABLE)->where('id', $id)->first();
        abort_unless($row, 404);
        $staff = RecordAccess::isStaff(auth()->user());
        abort_unless($staff || (int) $row->hod_id === $this->me(), 403, 'Only the HOD of this employee may decide.');
        abort_if((int) $row->employee_id === $this->me(), 403, 'You cannot approve your own running km.');
        if ($request->input('decision') === 'rejected' && trim((string) $request->input('remark')) === '') {
            return back()->withErrors(['remark' => 'Please write why the km is not approved.']);
        }
        DB::connection($conn)->table(KmCapApproval::TABLE)->where('id', $id)->update([
            'status' => $request->input('decision'), 'remark' => $request->input('remark'),
            'decided_by' => $this->me(), 'decided_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Saved.');
    }
}
