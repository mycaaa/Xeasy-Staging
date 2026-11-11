<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseSwitcher;
use App\Services\ClaimRules\ClaimSubmissionValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * HARDCOPY-01 (design of 5 Nov 2026): "Submit Hardcopy Bills". The employee records how the hard
 * copy of a submitted month went to HO: in person to DMS (who received it), by courier / post
 * (date, service, tracking number, receipt) or through the reporting manager (date, RM from the
 * employee master, acknowledgement). It fills the month's courier fields (PostDate / DocateNo /
 * Agency) that the verifier and the courier reminders already read, and keeps every save in
 * hardcopy_submissions. Receipt at HO is still confirmed separately (RecevingDate).
 */
class HardcopySubmissionController extends Controller
{
    public const TABLE = 'hardcopy_submissions';

    public const MODES = ['dms' => 'Submitted in person to DMS', 'courier' => 'Courier / Post', 'manager' => 'Reporting Manager'];

    public const PROVIDERS = ['India Post (Speed Post)', 'India Post (Registered Post)', 'DTDC', 'Blue Dart', 'Delhivery', 'Professional Couriers', 'Trackon', 'Shree Maruti', 'Other'];

    private function ctx(): array
    {
        $emp = (int) session('employee_id');
        abort_if($emp <= 0, 403, 'No employee linked to this login.');
        $conn = (string) session('expense_connection');
        $year = (int) session('year_id');
        $co = (int) session('company_id', 1);

        return [$emp, $conn, $year, $co];
    }

    public function index()
    {
        [$emp, $conn, $year, $co] = $this->ctx();
        $months = DB::connection($conn)->table("y{$year}_monthexpensefinal")
            ->where('EmployeeID', $emp)->where('YearId', $year)
            ->where(fn ($q) => $q->where('Status', 'Closed')->orWhere(fn ($w) => $w->whereNotNull('DateOfSubmit')->where('DateOfSubmit', '>', '2000-01-01')))
            ->orderByDesc('Month')->get(['Month', 'Status', 'DateOfSubmit', 'PostDate', 'DocateNo', 'Agency', 'RecevingDate']);
        $history = Schema::connection($conn)->hasTable(self::TABLE)
            ? DB::connection($conn)->table(self::TABLE)->where('employee_id', $emp)->where('year_id', $year)->orderByDesc('id')->limit(50)->get()
            : collect();
        $rm = $this->manager($emp, $co);

        return view('claims.hardcopy-submit', ['months' => $months, 'history' => $history, 'rm' => $rm, 'modes' => self::MODES, 'providers' => self::PROVIDERS]);
    }

    public function store(Request $request)
    {
        [$emp, $conn, $year, $co] = $this->ctx();
        $mode = (string) $request->input('mode');
        $rules = ['mode' => 'required|in:dms,courier,manager', 'month' => 'required|integer|between:1,12', 'file' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png'];
        $rules += match ($mode) {
            'dms' => ['handed_to' => 'required|string|max:120', 'handover_date' => 'nullable|date'],
            'courier' => ['dispatch_date' => 'required|date', 'provider' => 'required|string|max:80', 'provider_other' => 'nullable|required_if:provider,Other|string|max:80', 'tracking_no' => 'required|string|max:60'],
            'manager' => ['handover_date' => 'required|date'],
            default => [],
        };
        $v = $request->validate($rules, [
            'handed_to.required' => 'Enter the name of the person who received the bills.',
            'tracking_no.required' => 'Enter the tracking / consignment number.',
            'provider_other.required_if' => 'Enter the name of the courier / postal service.',
        ]);
        $month = (int) $v['month'];
        $table = "y{$year}_monthexpensefinal";
        $row = DB::connection($conn)->table($table)->where('EmployeeID', $emp)->where('Month', $month)->where('YearId', $year)->first();
        if (! $row) {
            return back()->withErrors(['month' => 'This month has no submitted claim.'])->withInput();
        }
        if (! empty($row->RecevingDate) && \App\Support\ZeroDate::has($row->RecevingDate)) {
            return back()->withErrors(['month' => 'HO has already received the hard copy for this month on '.\Carbon\Carbon::parse($row->RecevingDate)->format('d-m-Y').'.'])->withInput();
        }
        $date = $mode === 'courier' ? $v['dispatch_date'] : ($v['handover_date'] ?? now()->toDateString());
        $err = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($date, $emp, $month, $year, $conn));
        if ($err !== null) {
            return back()->withErrors([$mode === 'courier' ? 'dispatch_date' : 'handover_date' => $err])->withInput();
        }
        $rm = $this->manager($emp, $co);
        $provider = $mode === 'courier' ? ($v['provider'] === 'Other' ? trim((string) $v['provider_other']) : $v['provider']) : null;
        [$docket, $agency] = match ($mode) {
            'dms' => [mb_substr('Handed to '.$v['handed_to'], 0, 50), 'In person to DMS'],
            'courier' => [$v['tracking_no'], $provider],
            'manager' => [mb_substr('Via RM '.($rm['code'] ?? ''), 0, 50), 'Through reporting manager'],
        };
        $path = null;
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $path = $f->storeAs("hardcopy/{$co}/{$year}/{$emp}", "m{$month}_{$mode}_".now()->format('YmdHis').'.'.strtolower($f->getClientOriginalExtension()), 'local');
        }
        $this->ensureTable($conn);
        DB::connection($conn)->transaction(function () use ($conn, $table, $emp, $month, $year, $co, $mode, $v, $date, $provider, $docket, $agency, $path, $rm) {
            DB::connection($conn)->table(self::TABLE)->insert([
                'company_id' => $co, 'year_id' => $year, 'employee_id' => $emp, 'month' => $month, 'mode' => $mode,
                'handed_to' => $mode === 'dms' ? $v['handed_to'] : null, 'submitted_on' => \Carbon\Carbon::parse($date)->toDateString(),
                'provider' => $provider, 'tracking_no' => $mode === 'courier' ? $v['tracking_no'] : null,
                'manager_id' => $mode === 'manager' ? ($rm['id'] ?? null) : null, 'manager_name' => $mode === 'manager' ? ($rm['name'] ?? null) : null,
                'file_path' => $path, 'created_by' => auth()->id(), 'created_at' => now(),
            ]);
            DB::connection($conn)->table($table)->where('EmployeeID', $emp)->where('Month', $month)->where('YearId', $year)
                ->update(['PostDate' => \Carbon\Carbon::parse($date)->toDateString(), 'DocateNo' => $docket, 'Agency' => mb_substr((string) $agency, 0, 50)]);
        });
        Log::info('Hard copy submission recorded', ['emp' => $emp, 'year' => $year, 'month' => $month, 'mode' => $mode]);

        return redirect()->route('hardcopy.index')->with('success', self::MODES[$mode].' details saved for '.date('F', mktime(0, 0, 0, $month, 1)).'.');
    }

    public function file(int $id)
    {
        [$emp, $conn] = $this->ctx();
        $r = DB::connection($conn)->table(self::TABLE)->where('id', $id)->first();
        abort_unless($r && $r->file_path, 404);
        $staff = collect(['section_reports', 'finance', 'payment_section', 'Verify Claim', 'verify_claim'])->contains(fn ($p) => auth()->user()->can($p));
        abort_unless((int) $r->employee_id === $emp || $staff, 403);
        abort_unless(Storage::disk('local')->exists($r->file_path), 404);

        return Storage::disk('local')->response($r->file_path);
    }

    /**
     * HC-SUMMARY-01 (11 Nov 2026): the summary sheet the page asks the employee to attach in front of the bills: one
     * A4 page for a submitted month with the employee, the bills in date order (as they are to be arranged), totals by
     * head and the dispatch details already recorded. Only the employee's own submitted months.
     */
    public function summary(Request $request)
    {
        [$emp, $conn, $year, $co] = $this->ctx();
        $month = (int) $request->query('month');
        abort_unless($month >= 1 && $month <= 12, 404);
        $m = DB::connection($conn)->table("y{$year}_monthexpensefinal")->where('EmployeeID', $emp)->where('YearId', $year)->where('Month', $month)
            ->first(['Month', 'Status', 'DateOfSubmit', 'Claim_Amount', 'PostDate', 'DocateNo', 'Agency', 'RecevingDate']);
        abort_unless($m && ($m->Status === 'Closed' || (! empty($m->DateOfSubmit) && $m->DateOfSubmit > '2000-01-01')), 404, 'This month is not submitted yet.');
        $lines = DB::connection($conn)->table("y{$year}_expenseclaims as c")
            ->leftJoin('claimtype as t', 't.ClaimId', '=', 'c.ClaimId')
            ->where('c.CrBy', $emp)->where('c.ClaimYearId', $year)->where('c.ClaimMonth', $month)
            ->whereNotIn('c.ClaimStatus', ['Deactivate', 'Draft'])
            ->orderBy('c.BillDate')->orderBy('c.ExpId')
            ->get(['c.ExpId', 'c.BillDate', 'c.ClaimId', 't.ClaimName', 'c.FilledTAmt', 'c.TotKm', 'c.Remark', 'c.DateEntryRemark']);
        $hr = DB::connection(DatabaseSwitcher::getHrimsConnection($co));
        $e = $hr->table('hrm_employee as e')->leftJoin('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
            ->where('e.EmployeeID', $emp)->first(['e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.HqqName', 'g.MobileNo_Vnr', 'd.department_name', 'g.Designation']);
        $fy = DB::connection($conn)->table('financialyear')->where('YearId', $year)->value('Year');
        $byHead = $lines->groupBy('ClaimName')->map(fn ($g) => ['n' => $g->count(), 'amt' => $g->sum('FilledTAmt')])->sortKeys();

        return view('claims.hardcopy-summary', [
            'm' => $m, 'lines' => $lines, 'byHead' => $byHead, 'fy' => $fy,
            'emp' => ['code' => $e->EmpCode ?? '', 'name' => trim(preg_replace('/\s+/', ' ', ($e->Fname ?? '').' '.($e->Sname ?? '').' '.($e->Lname ?? ''))),
                'dept' => $e->department_name ?? '', 'desig' => $e->Designation ?? '', 'hq' => $e->HqqName ?? '', 'mobile' => $e->MobileNo_Vnr ?? ''],
            'rm' => $this->manager($emp, $co), 'monthName' => date('F', mktime(0, 0, 0, $month, 1)),
        ]);
    }

    private function manager(int $emp, int $co): ?array
    {
        try {
            $hr = DB::connection(DatabaseSwitcher::getHrimsConnection($co));
            $id = (int) $hr->table('hrm_employee_general')->where('EmployeeID', $emp)->value('RepEmployeeID');
            if ($id <= 0) {
                return null;
            }
            $m = $hr->table('hrm_employee')->where('EmployeeID', $id)->first(['EmpCode', 'Fname', 'Sname', 'Lname']);

            return $m ? ['id' => $id, 'code' => $m->EmpCode, 'name' => trim(preg_replace('/\s+/', ' ', "{$m->Fname} {$m->Sname} {$m->Lname}"))] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        Schema::connection($conn)->create(self::TABLE, function ($t) {
            $t->id();
            $t->unsignedTinyInteger('company_id')->default(1);
            $t->unsignedSmallInteger('year_id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedTinyInteger('month');
            $t->string('mode', 10);
            $t->string('handed_to', 120)->nullable();
            $t->date('submitted_on');
            $t->string('provider', 80)->nullable();
            $t->string('tracking_no', 60)->nullable();
            $t->unsignedBigInteger('manager_id')->nullable();
            $t->string('manager_name', 150)->nullable();
            $t->string('file_path', 300)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['employee_id', 'year_id', 'month']);
        });
    }
}
