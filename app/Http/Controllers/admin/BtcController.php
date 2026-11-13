<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BtcCase;
use App\Models\BtcCaseFile;
use App\Services\Btc\BtcWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** BTC-01: Bill To Company process screens and actions (config/btc.php, App\Services\Btc\BtcWorkflow). */
class BtcController extends Controller
{
    /** Queue tabs: key => [label, statuses, who sees it] */
    private function tabs(): array
    {
        return [
            'mine' => ['My cases', null, null],
            'approvals' => ['My approvals', ['pending_manager', 'with_treasury'], null],
            'dms' => ['DMS', ['bill_submitted'], 'btc.dms'],
            'finance' => ['Finance', ['dms_processed', 'advance_requested', 'paid'], 'btc.finance'],
            'checker' => ['Checker', ['finance_verified'], 'btc.checker'],
            'treasury' => ['Treasury', ['checked', 'dept_approved'], 'btc.treasury'],
            'documents' => ['Pending originals / boarding passes', null, 'btc.finance'],
            'all' => ['All cases', null, 'btc.admin'],
        ];
    }

    public function index()
    {
        $user = auth()->user();
        $tabs = collect($this->tabs())->filter(fn ($t) => $t[2] === null || $user->can($t[2]) || $user->can('btc.admin'))
            ->map(fn ($t) => $t[0])->all();

        return view('admin.btc.index', [
            'tabs' => $tabs,
            'routes' => config('btc.routes'),
            'categories' => collect(config('btc.categories'))->map(fn ($c) => $c['label'])->all(),
            'statuses' => config('btc.statuses'),
            'tdsSections' => config('btc.tds_sections'),
            'tdsNatures' => collect(config('btc.tds_natures'))->map(fn ($n) => ['label' => $n['label'], 'rate' => $n['rate']])->all(),
            'tdsNatureByCategory' => config('btc.tds_nature_by_category'),
            'vendors' => DB::table('vendor_list')->where('status', '1')->orderBy('vendor_name')->limit(2000)->get(['id', 'vendor_name', 'gst_no']),
            'canAdmin' => $user->can('btc.admin'),
        ]);
    }

    public function data(Request $request)
    {
        $user = auth()->user();
        $tab = (string) $request->input('tab', 'mine');
        $def = $this->tabs()[$tab] ?? $this->tabs()['mine'];
        if ($def[2] && ! $user->can($def[2]) && ! $user->can('btc.admin')) {
            abort(403);
        }
        $empId = (int) ($user->employee_id ?? 0);
        $q = BtcCase::query()->where('company_id', (int) session('company_id', 1));
        if ($tab === 'mine') {
            $q->where(fn ($w) => $w->where('employee_id', $empId)->orWhere('created_by', $user->id));
        } elseif ($tab === 'approvals') {
            $q->where(fn ($w) => $w->where(fn ($a) => $a->where('status', 'pending_manager')->where('manager_id', $empId))
                ->orWhere(fn ($a) => $a->where('status', 'with_treasury')->where('dept_approver_id', $empId)));
        } elseif ($tab === 'documents') {
            $q->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->where(fn ($w) => $w
                ->where(fn ($a) => $a->where('original_required', 1)->whereNull('original_received_on')->whereNotNull('bill_submitted_at'))
                ->orWhere(fn ($a) => $a->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')));
        } elseif ($def[1]) {
            $q->whereIn('status', $def[1]);
        }
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn ($w) => $w->where('case_no', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")->orWhere('emp_name', 'like', "%{$s}%")
                ->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('bill_no', 'like', "%{$s}%")->orWhere('pnr', 'like', "%{$s}%"));
        }

        return response()->json(['data' => $q->orderByDesc('id')->limit(1000)->get()->map(fn ($c) => [
            'id' => $c->id, 'case_no' => $c->case_no, 'route' => config("btc.routes.{$c->route}"), 'category' => $c->categoryLabel(),
            'employee' => trim($c->emp_code.' - '.$c->emp_name), 'vendor' => $c->vendor_name, 'purpose' => $c->purpose,
            'bill' => $c->bill_no ? $c->bill_no.' / '.optional($c->bill_date)->format('d/m/Y') : '',
            'amount' => $c->bill_amount ?? $c->quotation_amount, 'net' => $c->net_payable, 'status' => $c->status, 'status_label' => $c->statusLabel(),
            'pending' => implode(', ', $c->pendingDocuments()), 'updated' => $c->updated_at?->format('d/m/Y H:i'),
        ])]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'route' => 'required|in:'.implode(',', array_keys(config('btc.routes'))),
            'category' => 'required|in:'.implode(',', array_keys(config('btc.categories'))),
            'employee_id' => 'nullable|integer',
            'purpose' => 'required|string|max:500',
            'location' => 'nullable|string|max:150',
            'service_from' => 'nullable|date',
            'service_to' => 'nullable|date|after_or_equal:service_from',
            'vendor_id' => 'nullable|integer',
            'vendor_name' => 'nullable|string|max:200',
            'vendor_gstin' => 'nullable|string|max:20',
            'new_vendor' => 'nullable|boolean',
            'quotation_amount' => 'nullable|numeric|min:0',
            'advance_amount' => 'nullable|numeric|min:0',
            'pnr' => 'nullable|string|max:20',
            'travel_sector' => 'nullable|string|max:120',
            'travel_date' => 'nullable|date',
            'bill_no' => 'nullable|string|max:60',
            'bill_date' => 'nullable|date|before_or_equal:today',
            'taxable_amount' => 'nullable|numeric|min:0',
            'gst_amount' => 'nullable|numeric|min:0',
            'bill_amount' => 'nullable|numeric|min:0',
            'files.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png',
            'file_kinds.*' => 'nullable|in:quotation,approval,bill,supporting,boarding_pass',
            'submit' => 'nullable|boolean',
        ]);
        $user = auth()->user();
        // Admin may raise for any employee; others for themselves
        $empId = ($user->can('btc.admin') && ! empty($v['employee_id'])) ? (int) $v['employee_id'] : (int) $user->employee_id;
        $emp = DB::connection('hrims')->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')->where('e.EmployeeID', $empId)
            ->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.DepartmentId', 'd.department_name', 'g.RepEmployeeID']);
        if (! $emp) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 422);
        }
        if (! empty($v['vendor_id'])) {
            $vendor = DB::table('vendor_list')->where('id', $v['vendor_id'])->first(['vendor_name', 'gst_no']);
            $v['vendor_name'] = $vendor->vendor_name ?? $v['vendor_name'] ?? null;
            $v['vendor_gstin'] = $vendor->gst_no ?? $v['vendor_gstin'] ?? null;
        }
        $cat = config("btc.categories.{$v['category']}");
        $case = DB::transaction(function () use ($v, $emp, $user, $cat) {
            $companyId = (int) session('company_id', 1);
            $c = BtcCase::create([
                'case_no' => 'TMP'.uniqid(), 'company_id' => $companyId, 'route' => $v['route'], 'category' => $v['category'],
                'employee_id' => $emp->EmployeeID, 'emp_code' => $emp->EmpCode,
                'emp_name' => trim(preg_replace('/\s+/', ' ', $emp->Fname.' '.$emp->Sname.' '.$emp->Lname)),
                'department_id' => $emp->DepartmentId, 'department_name' => $emp->department_name,
                'purpose' => $v['purpose'], 'location' => $v['location'] ?? null,
                'service_from' => $v['service_from'] ?? null, 'service_to' => $v['service_to'] ?? null,
                'vendor_id' => $v['vendor_id'] ?? null, 'vendor_name' => $v['vendor_name'] ?? null, 'vendor_gstin' => $v['vendor_gstin'] ?? null,
                'new_vendor' => (bool) ($v['new_vendor'] ?? false), 'quotation_amount' => $v['quotation_amount'] ?? null,
                'advance_amount' => $v['route'] === 'employee_advance' ? (float) ($v['advance_amount'] ?? 0) : 0,
                'manager_id' => $v['route'] === 'employee_advance' ? ($emp->RepEmployeeID ?: null) : null,
                'pnr' => $v['pnr'] ?? null, 'travel_sector' => $v['travel_sector'] ?? null, 'travel_date' => $v['travel_date'] ?? null,
                'boarding_pass_required' => (bool) $cat['boarding_pass'], 'original_required' => (bool) $cat['original_required'],
                'bill_no' => $v['bill_no'] ?? null, 'bill_date' => $v['bill_date'] ?? null, 'taxable_amount' => $v['taxable_amount'] ?? null,
                'gst_amount' => $v['gst_amount'] ?? 0, 'bill_amount' => $v['bill_amount'] ?? null,
                'status' => 'draft', 'created_by' => $user->id,
            ]);
            $c->case_no = sprintf('BTC/%s/%05d', now('Asia/Kolkata')->format('y'), $c->id);
            $c->save();

            return $c;
        });
        $this->saveFiles($request, $case);
        if (! empty($v['submit'])) {
            try {
                $case = app(BtcWorkflow::class)->act($case, 'submit', [], $user);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => true, 'id' => $case->id, 'case_no' => $case->case_no, 'message' => 'Saved as draft: '.$e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'id' => $case->id, 'case_no' => $case->case_no, 'message' => "{$case->case_no}: ".$case->statusLabel()]);
    }

    public function show(int $id)
    {
        $c = BtcCase::with(['files', 'logs'])->findOrFail($id);
        $user = auth()->user();
        $this->authorizeView($c, $user);
        $name = fn ($eid) => $eid ? DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $eid)->selectRaw("CONCAT(EmpCode,' - ',Fname,' ',Lname) n")->value('n') : null;

        return response()->json([
            'case' => $c, 'status_label' => $c->statusLabel(), 'category_label' => $c->categoryLabel(),
            'route_label' => config("btc.routes.{$c->route}"), 'manager' => $name($c->manager_id), 'dept_approver' => $name($c->dept_approver_id),
            'pending' => $c->pendingDocuments(), 'actions' => BtcWorkflow::allowed($c, $user),
            'duplicates' => in_array('finance_verify', BtcWorkflow::allowed($c, $user), true) ? BtcWorkflow::duplicates($c) : [],
            'files' => $c->files->map(fn ($f) => ['id' => $f->id, 'kind' => $f->kind, 'name' => $f->file_name, 'url' => route('btc.file', $f->id)]),
            'logs' => $c->logs->map(fn ($l) => ['at' => optional($l->created_at)->format('d/m/Y H:i'), 'by' => $l->user_name, 'action' => $l->action,
                'to' => config("btc.statuses.{$l->to_status}", $l->to_status), 'remark' => $l->remark]),
            'default_ledger' => config("btc.focus.expense_ledgers.{$c->category}"),
        ]);
    }

    public function act(Request $request, int $id)
    {
        $c = BtcCase::findOrFail($id);
        $user = auth()->user();
        $this->authorizeView($c, $user);
        $this->saveFiles($request, $c);
        try {
            $c = app(BtcWorkflow::class)->act($c, (string) $request->input('action'), $request->except(['_token', 'files', 'file_kinds']), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "{$c->case_no}: ".$c->statusLabel(), 'status' => $c->status]);
    }

    public function file(int $fileId)
    {
        $f = BtcCaseFile::findOrFail($fileId);
        $this->authorizeView(BtcCase::findOrFail($f->btc_case_id), auth()->user());

        return Storage::disk('local')->response($f->file_path, $f->file_name);
    }

    /** Focus JV import lines for verified / checked cases not yet exported (or a chosen set). */
    public function focusExport(Request $request)
    {
        abort_unless(auth()->user()->can('btc.finance') || auth()->user()->can('btc.admin'), 403);
        $q = BtcCase::where('company_id', (int) session('company_id', 1))->whereIn('status', ['finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid', 'closed']);
        if ($ids = array_filter(array_map('intval', (array) $request->input('ids', [])))) {
            $q->whereIn('id', $ids);
        } else {
            $q->whereNull('focus_exported_at');
        }
        $cases = $q->orderBy('id')->get();
        $f = config('btc.focus');
        $rows = [];
        foreach ($cases as $c) {
            $narr = "{$c->case_no} {$c->categoryLabel()} {$c->vendor_name} bill {$c->bill_no} dt ".optional($c->bill_date)->format('d-m-Y')." for {$c->emp_code} {$c->emp_name}";
            $date = optional($c->finance_at)->format('d/m/Y');
            $line = fn ($ledger, $sub, $dr, $cr) => [$c->case_no, $date, $ledger, $sub, $c->cost_centre, $dr ? round($dr, 2) : '', $cr ? round($cr, 2) : '', $c->tds_section && $c->tds_section !== 'none' ? $c->tds_section : '', $narr];
            // 1. bill through Employee-BTC to the vendor
            $empLedger = \App\Services\Btc\BtcLedger::employeeLedger($c->emp_code, $c->emp_name); // BTC-LEDGER-01
            $rows[] = $line($empLedger, $c->emp_code.' '.$c->emp_name, (float) $c->bill_amount, 0);
            $rows[] = $line($c->vendor_name ?: 'Vendor', $c->vendor_gstin, 0, (float) $c->bill_amount - (float) $c->tds_amount);
            if ((float) $c->tds_amount > 0) {
                $rows[] = $line($f['tds_ledger'], $c->tds_section, 0, (float) $c->tds_amount);
            }
            // 2. expense recognised against Employee-BTC (GST input separately)
            $rows[] = $line($c->expense_ledger, $c->department_name, (float) $c->bill_amount - (float) $c->gst_amount, 0);
            if ((float) $c->gst_amount > 0) {
                $rows[] = $line($f['gst_input_ledger'], $c->vendor_gstin, (float) $c->gst_amount, 0);
            }
            $rows[] = $line($empLedger, $c->emp_code.' '.$c->emp_name, 0, (float) $c->bill_amount);
            // 3. advance already paid set off against the vendor
            if ((float) $c->advance_adjusted > 0) {
                $rows[] = $line($c->vendor_name ?: 'Vendor', $c->vendor_gstin, (float) $c->advance_adjusted, 0);
                $rows[] = $line($f['vendor_advance_ledger'], $c->emp_code, 0, (float) $c->advance_adjusted);
            }
        }
        BtcCase::whereIn('id', $cases->pluck('id'))->whereNull('focus_exported_at')->update(['focus_exported_at' => now()]);
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Voucher ref', 'Date', 'Ledger', 'Sub-ledger', 'Cost centre', 'Debit', 'Credit', 'TDS section', 'Narration'];
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'BTC_Focus_JV_'.now()->format('Ymd_His').'.xlsx');
    }

    /** Air-travel cases still waiting for a boarding pass: the list Finance shares with the travel vendor. */
    public function boardingPassExport()
    {
        abort_unless(auth()->user()->can('btc.finance') || auth()->user()->can('btc.admin'), 403);
        $rows = BtcCase::where('company_id', (int) session('company_id', 1))->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->orderBy('travel_date')->get()
            ->map(fn ($c) => [$c->case_no, $c->vendor_name, $c->pnr, $c->travel_sector, optional($c->travel_date)->format('d/m/Y'), $c->emp_code, $c->emp_name,
                $c->bill_no, $c->bill_amount, $c->travel_date ? now()->diffInDays($c->travel_date) : ''])->all();
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Case', 'Travel vendor', 'PNR', 'Sector', 'Travel date', 'Emp code', 'Employee', 'Bill no', 'Amount', 'Days since travel'];
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'BTC_Boarding_Pass_Pending_'.now()->format('Ymd').'.xlsx');
    }

    /**
     * BOARDING-02 (13 Nov 2026): boarding passes for air tickets booked directly by the company (BTC cases).
     * Desk users (DMS / Finance / admin) see every company booking; anyone else sees their own trips.
     * Newest travel date first; filter pending / received / all.
     */
    public function boardingPasses(Request $request)
    {
        $user = auth()->user();
        $desk = collect(['btc.dms', 'btc.finance', 'btc.admin'])->contains(fn ($p) => $user->can($p));
        $show = in_array($request->input('show'), ['pending', 'received', 'all'], true) ? $request->input('show') : 'pending';
        $q = BtcCase::where('company_id', (int) session('company_id', 1))->where('boarding_pass_required', 1)
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft']);
        if (! $desk) {
            $q->where('employee_id', (int) ($user->employee_id ?? 0));
        }
        $counts = ['pending' => (clone $q)->whereNull('boarding_pass_received_on')->count(), 'received' => (clone $q)->whereNotNull('boarding_pass_received_on')->count()];
        if ($show === 'pending') {
            $q->whereNull('boarding_pass_received_on');
        } elseif ($show === 'received') {
            $q->whereNotNull('boarding_pass_received_on');
        }
        if ($term = trim((string) $request->input('q'))) {
            $q->where(function ($w) use ($term) {
                foreach (['case_no', 'pnr', 'travel_sector', 'emp_name', 'emp_code', 'vendor_name'] as $col) {
                    $w->orWhere($col, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }
        $cases = $q->with(['files' => fn ($f) => $f->where('kind', 'boarding_pass')])
            ->orderByDesc('travel_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.btc.boarding-passes', compact('cases', 'desk', 'show', 'counts'));
    }

    /** BOARDING-02: upload a boarding pass for one booking (the traveller, or the DMS / Finance desk); marks it received. */
    public function boardingPassUpload(Request $request, int $id)
    {
        $c = BtcCase::findOrFail($id);
        $user = auth()->user();
        $this->authorizeView($c, $user);
        abort_unless((int) $c->boarding_pass_required === 1, 404);
        $request->validate(['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png'], ['file.required' => 'Choose the boarding pass file (PDF or photo).']);
        $f = $request->file('file');
        $path = $f->storeAs("btc/{$c->company_id}/{$c->id}", 'boarding_pass_'.now()->format('YmdHis').'.'.strtolower($f->getClientOriginalExtension()), 'local');
        DB::transaction(function () use ($c, $f, $path, $user) {
            BtcCaseFile::create(['btc_case_id' => $c->id, 'kind' => 'boarding_pass', 'file_path' => $path,
                'file_name' => mb_substr($f->getClientOriginalName(), 0, 255), 'mime' => $f->getMimeType(), 'uploaded_by' => $user->id]);
            $first = ! $c->boarding_pass_received_on;
            if ($first) {
                $c->boarding_pass_received_on = now()->toDateString();
                $c->save();
            }
            \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'boarding_pass', 'from_status' => $c->status, 'to_status' => $c->status,
                'remark' => $first ? 'Boarding pass uploaded' : 'Another boarding pass file added', 'user_id' => $user->id, 'user_name' => mb_substr((string) $user->name, 0, 150)]);
        });

        return back()->with('success', "Boarding pass saved for {$c->case_no}.");
    }

    public function approverSearch(Request $request)
    {
        $s = trim((string) $request->input('q'));

        return DB::connection('hrims')->table('hrm_employee')->where('EmpStatus', 'A')
            ->where(fn ($w) => $w->where('EmpCode', 'like', "%{$s}%")->orWhere('Fname', 'like', "%{$s}%")->orWhere('Lname', 'like', "%{$s}%"))
            ->limit(20)->get(['EmployeeID as id', DB::raw("CONCAT(EmpCode,' - ',Fname,' ',Lname) as text")]);
    }

    private function saveFiles(Request $request, BtcCase $c): void
    {
        $kinds = (array) $request->input('file_kinds', []);
        foreach ((array) $request->file('files', []) as $i => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                continue;
            }
            $kind = in_array($kinds[$i] ?? '', ['quotation', 'approval', 'bill', 'supporting', 'original', 'boarding_pass', 'payment_proof'], true) ? $kinds[$i] : 'supporting';
            $path = $file->storeAs("btc/{$c->company_id}/{$c->id}", $kind.'_'.now()->format('YmdHis').'_'.$i.'.'.$ext, 'local');
            BtcCaseFile::create(['btc_case_id' => $c->id, 'kind' => $kind, 'file_path' => $path, 'file_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(), 'uploaded_by' => auth()->id()]);
        }
    }

    private function authorizeView(BtcCase $c, $user): void
    {
        $empId = (int) ($user->employee_id ?? 0);
        $desk = collect(['btc.dms', 'btc.finance', 'btc.checker', 'btc.treasury', 'btc.admin'])->contains(fn ($p) => $user->can($p));
        abort_unless($desk || in_array($empId, [(int) $c->employee_id, (int) $c->manager_id, (int) $c->dept_approver_id], true) || (int) $c->created_by === (int) $user->id, 403);
    }
}
