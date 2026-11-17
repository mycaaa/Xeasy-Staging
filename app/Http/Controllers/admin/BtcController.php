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
            'mine' => ['My BTC Requests', null, null],
            'approvals' => ['My approvals', ['pending_manager', 'with_treasury'], null],
            'booking' => ['Booking queue', ['booking_requested'], 'btc.booking'],
            'dms' => ['DMS', ['bill_submitted'], 'btc.dms|btc.verifier'],
            'finance' => ['Finance', ['dms_processed', 'advance_requested', 'paid'], 'btc.finance|btc.advance'],
            'checker' => ['Checker', ['finance_verified'], 'btc.checker'],
            // BTC-REQ-15 (17 Nov 2026): Treasury sends checked cases for HOD approval; the payment queue holds only
            // payment-ready (HOD-approved, not on hold) cases
            'treasury' => ['Treasury: send for HOD approval', ['checked'], 'btc.treasury'],
            'payments' => ['Payment-ready', ['dept_approved'], 'btc.treasury'],
            'authorise' => ['Payments to authorise', ['dept_approved'], 'btc.treasury_checker'],   // BTC-TRY-01
            'documents' => ['Pending originals / boarding passes', null, 'btc.finance'],
            'all' => ['All cases', null, 'btc.admin|btc.register|btc.audit'],
        ];
    }

    public function index()
    {
        $user = auth()->user();
        $tabs = collect($this->tabs())->filter(fn ($t) => $t[2] === null || $this->canAny($user, $t[2]))
            ->map(fn ($t) => $t[0])->all();

        return view('admin.btc.index', [
            'tabs' => $tabs,
            // BTC-FORM-01 (15 Nov 2026): an employee raises only "B - employee-arranged with advance"; admin-arranged
            // bookings (A) and final-bill-after-activity (C) are raised by the BTC admin
            'routes' => $user->can('btc.admin') ? config('btc.routes') : array_intersect_key(config('btc.routes'), array_flip((array) config('btc.employee_routes', ['employee_advance']))),
            'routeChoices' => config('btc.route_choices', []),   // BTC-REQ-03
            'categories' => collect(config('btc.categories'))->map(fn ($c) => $c['label'])->all(),
            'statuses' => config('btc.statuses'),
            'tdsSections' => config('btc.tds_sections'),
            'tdsNatures' => collect(config('btc.tds_natures'))->map(fn ($n) => ['label' => $n['label'], 'rate' => $n['rate']])->all(),
            'tdsNatureByCategory' => config('btc.tds_nature_by_category'),
            'vendors' => $this->formVendors(),
            'vendorTypesByCategory' => config('btc.vendor_types_by_category', []),
            'canAdmin' => $user->can('btc.admin'),
        ]);
    }

    /**
     * BTC-FORM-02 (15 Nov 2026): vendor list for the form - one entry per vendor name and GSTIN (the list repeated
     * the same hotel up to 12 times), placeholder names (NA, OTHER, -) left out, each with its type so the form
     * shows only vendors of the chosen category. A name with several GSTINs keeps one entry per GSTIN.
     */
    private function formVendors(): array
    {
        $rows = DB::table('vendor_list')->where('status', '1')->whereNotNull('vendor_name')
            ->orderBy('vendor_name')->orderBy('id')->get(['id', 'vendor_name', 'gst_no', 'vendor_type']);
        $junk = ['', 'na', 'n/a', 'other', 'others', '-', '--', '.', 'nil', 'none', 'test'];
        $out = [];
        foreach ($rows as $r) {
            $name = trim(preg_replace('/\s+/', ' ', (string) $r->vendor_name));
            if (in_array(strtolower($name), $junk, true)) {
                continue;
            }
            $gst = strtoupper(trim((string) $r->gst_no));
            $key = strtolower($name).'|'.$gst;
            $type = strtolower(trim((string) $r->vendor_type));
            if (! isset($out[$key])) {
                $out[$key] = ['id' => (int) $r->id, 'name' => $name, 'gst' => $gst, 'types' => []];
            }
            if ($type !== '' && ! in_array($type, $out[$key]['types'], true)) {
                $out[$key]['types'][] = $type;
            }
        }

        return array_values($out);
    }

    public function data(Request $request)
    {
        $user = auth()->user();
        $tab = (string) $request->input('tab', 'mine');
        $def = $this->tabs()[$tab] ?? $this->tabs()['mine'];
        if ($def[2] && ! $this->canAny($user, $def[2])) {
            abort(403);
        }
        $empId = (int) ($user->employee_id ?? 0);
        $q = BtcCase::query()->where('company_id', (int) session('company_id', 1));
        if (! in_array($tab, ['mine', 'approvals'], true)) {
            \App\Services\Btc\BtcAccess::applyScope($q, $user);   // BTC-REQ-01: entity / department scope of the user's BTC roles
        }
        if ($tab === 'mine') {
            $q->where(fn ($w) => $w->where('employee_id', $empId)->orWhere('created_by', $user->id));
        } elseif ($tab === 'approvals') {
            $q->where(fn ($w) => $w->where(fn ($a) => $a->where('status', 'pending_manager')->where('manager_id', $empId))
                ->orWhere(fn ($a) => $a->where('status', 'with_treasury')->where('dept_approver_id', $empId)));
        } elseif ($tab === 'documents') {
            $q->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->where(fn ($w) => $w
                ->where(fn ($a) => $a->where('original_required', 1)->whereNull('original_received_on')->whereNotNull('bill_submitted_at'))
                ->orWhere(fn ($a) => $a->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')));
        } elseif ($tab === 'payments') {
            $q->where('status', 'dept_approved')->where(fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0))
                ->whereNotExists(fn ($p) => $p->from('btc_payments')->whereColumn('btc_payments.btc_case_id', 'btc_cases.id')->where('btc_payments.status', 'pending'));
        } elseif ($tab === 'authorise') {
            $q->where('status', 'dept_approved')->whereExists(fn ($p) => $p->from('btc_payments')->whereColumn('btc_payments.btc_case_id', 'btc_cases.id')->where('btc_payments.status', 'pending'));
        } elseif ($tab === 'finance' && ! $this->canAny($user, 'btc.checker') && \Illuminate\Support\Facades\Schema::hasColumn('btc_cases', 'assigned_maker_id')) {
            // BTC-FIN-02: a Finance maker sees his assigned cases and the unassigned ones
            $q->whereIn('status', $def[1])->where(fn ($w) => $w->whereNull('assigned_maker_id')->orWhere('assigned_maker_id', $user->id));
        } elseif ($def[1]) {
            $q->whereIn('status', $def[1]);
        }
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn ($w) => $w->where('case_no', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")->orWhere('emp_name', 'like', "%{$s}%")
                ->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('bill_no', 'like', "%{$s}%")->orWhere('pnr', 'like', "%{$s}%"));
        }

        return response()->json(['data' => $q->orderByDesc('id')->limit(1000)->get()->map(fn ($c) => [
            'id' => $c->id, 'case_no' => $c->case_no, 'route' => config("btc.route_short.{$c->route}", config("btc.routes.{$c->route}")), 'category' => $c->categoryLabel(),
            'employee' => trim($c->emp_code.' - '.$c->emp_name), 'vendor' => $c->vendor_name, 'purpose' => $c->purpose,
            'bill' => $c->bill_no ? $c->bill_no.' / '.optional($c->bill_date)->format('d/m/Y') : '',
            'amount' => $c->bill_amount ?? $c->quotation_amount, 'net' => $c->net_payable, 'status' => $c->status, 'status_label' => $c->statusLabel(),
            'pending' => implode(', ', $c->waitingFor()), 'updated' => $c->updated_at?->format('d/m/Y H:i'),
            'pending_with' => $c->pendingWith(), 'stages' => $c->stageSummary(),   // BTC-REQ-02 / 04
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
            'quotation_amount' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2',   // BTC-AMT-01
            'estimated_amount' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2',   // BTC-REQ-03
            'advance_amount' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2',
            'pnr' => 'nullable|string|max:20',
            'travel_sector' => 'nullable|string|max:120',
            'travel_date' => 'nullable|date',
            'bill_no' => 'nullable|string|max:60',
            'bill_date' => 'nullable|date|before_or_equal:today',
            'taxable_amount' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2',
            'gst_amount' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2',
            'bill_amount' => 'nullable|numeric|min:0|max:9999999.99|decimal:0,2',
            'files.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png',
            'file_kinds.*' => 'nullable|in:quotation,approval,bill,supporting,boarding_pass',
            'submit' => 'nullable|boolean',
        ], ['*.max' => 'The amount cannot be more than ₹99,99,999.99.', '*.decimal' => 'Use at most two decimals.']);
        if (! empty($v['quotation_amount']) && (float) ($v['advance_amount'] ?? 0) > (float) $v['quotation_amount']) {
            return response()->json(['success' => false, 'message' => 'Advance needed cannot be more than the quotation amount.'], 422);   // BTC-AMT-01
        }
        $user = auth()->user();
        if (! $user->can('btc.admin') && ! in_array($v['route'], (array) config('btc.employee_routes', ['employee_advance']), true)) {
            return response()->json(['success' => false, 'message' => 'Choose "Admin Booking Required" or "Advance Required". A bill after the activity is raised by the BTC admin.'], 422);   // BTC-FORM-01 / BTC-REQ-03
        }
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
                'estimated_amount' => $v['estimated_amount'] ?? ($v['quotation_amount'] ?? null),
                'advance_amount' => $v['route'] === 'employee_advance' ? (float) ($v['advance_amount'] ?? 0) : 0,
                // HIER-LOOP-01: never himself. BTC-REQ-03: an admin booking asked by the employee is approved by the manager too
                'manager_id' => in_array($v['route'], ['employee_advance', 'admin_booking'], true) ? (\App\Support\ReportingTree::managerOf((int) $emp->EmployeeID) ?: null) : null,
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
            'pending' => $c->waitingFor(), 'actions' => BtcWorkflow::allowed($c, $user),
            'duplicates' => in_array('finance_verify', BtcWorkflow::allowed($c, $user), true) ? BtcWorkflow::duplicates($c) : [],
            // BTC-PAY-01 / CLOSE-01 / DUP-02 / TDS-02 / GST-01 (17 Nov 2026)
            'payments' => \Illuminate\Support\Facades\Schema::hasTable('btc_payments') ? $c->payments()->get()->map(fn ($p) => ['id' => $p->id, 'amount' => (float) $p->amount,
                'on' => optional($p->paid_on)->format('d/m/Y'), 'mode' => config("btc.payment_modes.{$p->mode}", $p->mode), 'utr' => $p->utr_number, 'bank_ref' => $p->bank_ref,
                'status' => $p->status, 'failed_reason' => $p->failed_reason]) : [],
            'payment_modes' => config('btc.payment_modes'),
            'closure_gaps' => in_array($c->status, ['paid', 'dept_approved'], true) ? $c->closureGaps() : [],
            'claim_overlaps' => $user->can('btc.finance') || $user->can('btc.checker') || $user->can('btc.admin') || $user->can('btc.verifier') ? BtcWorkflow::claimOverlaps($c) : [],
            'vendor_year' => $c->bill_no ? \App\Services\Btc\BtcLedger::vendorYearTotal($c) : null,
            'gstin_ok' => $c->vendor_gstin ? \App\Services\Btc\BtcLedger::gstinValid($c->vendor_gstin) : null,
            'overdue_advances' => BtcWorkflow::overdueAdvances((int) $c->employee_id, (int) $c->id)->pluck('case_no'),
            // BTC-EXC-01 / FIN-02 / TRY-01 (17 Nov 2026)
            'exceptions' => $this->canAny($user, 'btc.finance|btc.checker|btc.verifier|btc.exceptions|btc.audit|btc.treasury') ? \App\Services\Btc\BtcExceptions::forCase($c) : [],
            'journal' => $c->finance_at ? \App\Services\Btc\BtcLedger::journal($c) : [],
            'dims' => array_intersect(['finance_verify'], BtcWorkflow::allowed($c, $user)) ? \App\Services\Btc\BtcLedger::dimensions($c) : null,
            'pending_payment' => BtcWorkflow::pendingPayment($c),
            'vendor_bank' => $this->canAny($user, 'btc.treasury|btc.treasury_checker|btc.finance|btc.audit') ? (\App\Services\Btc\BtcBank::masked(\App\Services\Btc\BtcBank::forCase($c)) ?? 'not verified') : null,
            'maker' => $c->assigned_maker_id ? \App\Models\User::whereKey($c->assigned_maker_id)->value('name') : null,
            'finance_users' => in_array('assign_maker', BtcWorkflow::allowed($c, $user), true)
                ? \App\Models\User::permission('btc.finance')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values() : [],
            'files' => $c->files->map(fn ($f) => ['id' => $f->id, 'kind' => $f->kind, 'name' => $f->file_name, 'url' => route('btc.file', $f->id)]),
            'logs' => $c->logs->map(fn ($l) => ['at' => optional($l->created_at)->format('d/m/Y H:i'), 'by' => $l->user_name, 'action' => $l->action,
                'to' => config("btc.statuses.{$l->to_status}", $l->to_status), 'remark' => $l->remark]),
            'default_ledger' => config("btc.focus.expense_ledgers.{$c->category}"),
            'advance' => $c->advanceFigures(), 'pending_with' => $c->pendingWith(), 'stages' => $c->stageSummary(),
            'invoices' => \Illuminate\Support\Facades\Schema::hasTable('btc_invoices') ? DB::table('btc_invoices')->where('btc_case_id', $c->id)->get(['id', 'nova_document_id', 'invoice_no', 'gross_amount', 'document_url']) : [],
        ]);
    }

    public function act(Request $request, int $id)
    {
        $c = BtcCase::findOrFail($id);
        $user = auth()->user();
        $this->authorizeView($c, $user);
        // BTC-AMT-01 (15 Nov 2026): amounts must fit the amount columns (up to 99,99,999.99) with at most two
        // decimals - an over-long number gave "Server Error"
        $request->validate(array_fill_keys(['quotation_amount', 'advance_amount', 'taxable_amount', 'gst_amount', 'bill_amount', 'paid_amount', 'advance_adjusted', 'tds_on_advance',
            'advance_approved', 'advance_paid_amount', 'advance_refund', 'booking_amount'],
            'nullable|numeric|min:0|max:9999999.99|decimal:0,2'), ['*.max' => 'The amount cannot be more than ₹99,99,999.99.', '*.decimal' => 'Use at most two decimals.']);
        $this->saveFiles($request, $c);
        try {
            $c = app(BtcWorkflow::class)->act($c, (string) $request->input('action'), $request->except(['_token', 'files', 'file_kinds']), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "{$c->case_no}: ".$c->statusLabel(), 'status' => $c->status]);
    }

    /** BTC-FIN-02 (17 Nov 2026): Dr/Cr preview of the Finance form, nothing saved. */
    public function preview(Request $request, int $id)
    {
        $c = BtcCase::findOrFail($id);
        $user = auth()->user();
        $this->authorizeView($c, $user);
        abort_unless($this->canAny($user, 'btc.finance|btc.checker'), 403);
        try {
            return response()->json(['success' => true] + app(BtcWorkflow::class)->preview($c, $request->except(['_token', 'files', 'file_kinds', 'action']), $user));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
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
            // BTC-FIN-02 (17 Nov 2026): the same lines as the on-screen preview, with the accounting dimensions
            $narr = $c->narration ?: "{$c->case_no} {$c->categoryLabel()} {$c->vendor_name} bill {$c->bill_no} dt ".optional($c->bill_date)->format('d-m-Y')." for {$c->emp_code} {$c->emp_name}";
            $date = optional($c->finance_at)->format('d/m/Y');
            foreach (\App\Services\Btc\BtcLedger::journal($c) as $l) {
                $rows[] = [$c->jv_no ?: $c->case_no, $date, $l['ledger'], $l['sub'], $l['cost_centre'], $c->department_name, $c->dim_function, $c->dim_vertical, $c->dim_region,
                    $c->dim_activity, $c->dim_crop, $l['dr'] ?: '', $l['cr'] ?: '', $l['tds'], $c->tds_jv_no, $narr];
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
                return ['Voucher ref / JV no.', 'Date', 'Ledger', 'Sub-ledger', 'Cost centre', 'Department', 'Function', 'Vertical', 'Region', 'Activity', 'Crop', 'Debit', 'Credit', 'TDS section', 'TDS JV no.', 'Narration'];
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

        // 16 Nov #4: an "Upload boarding pass" button for any open air booking (the list above only offers upload on
        // pending rows, so with nothing pending there was no way to upload)
        $uq = BtcCase::where('company_id', (int) session('company_id', 1))->where('boarding_pass_required', 1)
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft']);
        if (! $desk) {
            $uq->where('employee_id', (int) ($user->employee_id ?? 0));
        }
        $uploadCases = $uq->orderByRaw('boarding_pass_received_on IS NOT NULL')->orderByDesc('travel_date')->orderByDesc('id')->limit(500)
            ->get(['id', 'case_no', 'emp_name', 'emp_code', 'pnr', 'travel_sector', 'travel_date', 'boarding_pass_received_on']);

        return view('admin.btc.boarding-passes', compact('cases', 'desk', 'show', 'counts', 'uploadCases'));
    }

    /** BOARDING-04 (17 Nov 2026): many boarding passes / a folder at once, matched by PNR or BTC number in the file name. */
    public function boardingPassBulk(Request $request)
    {
        $user = auth()->user();
        $request->validate(['files' => 'required|array|max:200', 'files.*' => 'file|max:5120'], ['files.required' => 'Choose the files or the folder.']);
        $desk = collect(['btc.dms', 'btc.finance', 'btc.admin'])->contains(fn ($p) => $user->can($p));
        $q = BtcCase::where('company_id', (int) session('company_id', 1))->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft']);
        if (! $desk) {
            $q->where('employee_id', (int) ($user->employee_id ?? 0));
        }
        $res = \App\Services\Btc\BtcBoardingBulk::upload($q->get(), $request->file('files', []), (string) $user->name, $user->id, 'Xeasy');

        return back()->with('bulk_result', $res)->with('success', collect($res)->where('ok', true)->count().' of '.count($res).' file(s) saved.');
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

    /** "btc.dms|btc.verifier": any of them, or btc.admin. */
    private function canAny($user, string $perms): bool
    {
        return $user->can('btc.admin') || collect(explode('|', $perms))->contains(fn ($p) => $user->can($p));
    }

    private function authorizeView(BtcCase $c, $user): void
    {
        $empId = (int) ($user->employee_id ?? 0);
        // BTC-REQ-01: the new BTC desks (booking, advance, verifier, invoices, register) open cases too
        $desk = collect(['btc.dms', 'btc.finance', 'btc.checker', 'btc.treasury', 'btc.treasury_checker', 'btc.audit', 'btc.exceptions', 'btc.admin', 'btc.booking', 'btc.advance', 'btc.verifier', 'btc.invoices', 'btc.register'])->contains(fn ($p) => $user->can($p));
        // BTC-TEAM-01: a manager anywhere above the requester may open the request (read; actions stay by role)
        abort_unless($desk || in_array($empId, [(int) $c->employee_id, (int) $c->manager_id, (int) $c->dept_approver_id], true) || (int) $c->created_by === (int) $user->id
            || \App\Support\ReportingTree::isAbove($empId, (int) $c->employee_id), 403);
    }
}
