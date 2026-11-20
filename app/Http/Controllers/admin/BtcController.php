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
            'mine' => ['My requests', null, null],   // BTC-REQ-14: menu reads 'BTC Request'
            'approvals' => ['My approvals', ['pending_manager'], null],
            'booking' => ['Booking queue', ['booking_requested', 'booked'], 'btc.booking'],
            // BTC-V2 (19 Nov 2026): the revised queues (each also has its own screen in the BTC menu)
            'dms' => ['DMS: physical bills', ['bill_with_dms'], 'btc.dms'],
            'upload' => ['Finance: to upload', ['bill_with_finance'], 'btc.bill_upload|btc.finance'],
            'extraction' => ['Punch Screen', ['scanned', 'bill_submitted'], 'btc.bill_upload|btc.finance'],
            'verifier' => ['Finance level verification', ['extracted', 'finance_verified'], 'btc.verifier'],
            'approval' => ['Finance Approval', ['focus_done'], 'btc.fin_approver'],
            'payments' => ['Treasury payment', ['fin_approved'], 'btc.treasury'],
            'finance' => ['Advance / closure', ['advance_requested', 'paid'], 'btc.finance|btc.advance'],
            'documents' => ['Pending originals / boarding passes', null, 'btc.finance|btc.bill_upload|btc.verifier'],
            'all' => ['All cases', null, 'btc.admin|btc.register|btc.audit'],
        ];
    }

    /** BTC-V2: what the shared case window (admin.btc._casewin) needs, for the Finance screens. */
    public function caseWindowData(): array
    {
        $user = auth()->user();

        return [
            'statuses' => config('btc.statuses'),
            'tdsSections' => config('btc.tds_sections'),
            'tdsNatures' => collect(config('btc.tds_natures'))->map(fn ($n) => ['label' => $n['label'], 'rate' => $n['rate']])->all(),
            'tdsNatureByCategory' => config('btc.tds_nature_by_category'),
            'vendors' => $this->formVendors(),
            'vendorTypesByCategory' => $this->vendorTypes(),
            'showFlow' => true,
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
            'routes' => array_intersect_key(config('btc.routes'), array_flip((array) config('btc.employee_routes', ['employee_advance']))),   // BTC-REQ-23: no 'final bill after the activity' option, for anyone
            'routeChoices' => config('btc.route_choices', []),   // BTC-REQ-03
            'categories' => collect(config('btc.categories'))->filter(fn ($c) => $c['new_case'] ?? true)->map(fn ($c) => $c['label'])->all(),   // BTC-REQ-25
            'purposes' => (array) config('btc.purposes', []),
            'statuses' => config('btc.statuses'),
            'tdsSections' => config('btc.tds_sections'),
            'tdsNatures' => collect(config('btc.tds_natures'))->map(fn ($n) => ['label' => $n['label'], 'rate' => $n['rate']])->all(),
            'tdsNatureByCategory' => config('btc.tds_nature_by_category'),
            'vendors' => $this->formVendors(),
            'vendorTypesByCategory' => $this->vendorTypes(),
            'canAdmin' => $user->can('btc.admin'),
            // BTC-REQ-08/16/19 (17 Nov 2026): the process flow (header line and the stage bar) only for the Finance side
            'showFlow' => collect(['btc.admin', 'btc.audit', 'btc.dms', 'btc.bill_upload', 'btc.verifier', 'btc.fin_approver', 'btc.finance', 'btc.checker', 'btc.treasury'])->contains(fn ($p) => $user->can($p)),
            'billSources' => config('btc.bill_sources'),
            'extractFields' => config('btc.extraction.fields'),
        ]);
    }

    /** BTC category => vendor types shown for it (core list: the BTC category itself; old list: config). */
    private function vendorTypes(): array
    {
        if (\App\Services\Btc\CoreVendors::active()) {
            $served = collect((array) config('btc.core_vendors.category_map'))->flatten()->unique()->all();

            return collect(array_keys(config('btc.categories')))->mapWithKeys(fn ($k) => [$k => in_array($k, $served, true) ? [$k] : []])->all();
        }

        return config('btc.vendor_types_by_category', []);
    }

    /**
     * BTC-FORM-02 (15 Nov 2026): vendor list for the form - one entry per vendor name and GSTIN (the list repeated
     * the same hotel up to 12 times), placeholder names (NA, OTHER, -) left out, each with its type so the form
     * shows only vendors of the chosen category. A name with several GSTINs keeps one entry per GSTIN.
     */
    private function formVendors(): array
    {
        // BTC-VENDOR-CORE-01 (20 Nov 2026): the core application's vendor list once it has been synced
        if (\App\Services\Btc\CoreVendors::active()) {
            return \App\Services\Btc\CoreVendors::forForm();
        }

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
            // BTC-REQ-15 (17 Nov 2026): pending with me, or what I approved / rejected / returned, or all of these
            $pending = fn ($w) => $w->where(fn ($a) => $a->where('status', 'pending_manager')->where('manager_id', $empId));
            $did = fn (array $acts) => fn ($w) => $w->whereIn('id', DB::table('btc_case_logs')->where('user_id', $user->id)->whereIn('action', $acts)->pluck('btc_case_id'));
            $ap = (string) $request->input('ap', 'pending');
            match ($ap) {
                'approved' => $q->where($did(['manager_approve', 'dept_approve'])),
                'rejected' => $q->where($did(['manager_reject', 'dept_reject', 'dept_return'])),
                'all' => $q->where(fn ($w) => $w->where($pending)->orWhere($did(['manager_approve', 'dept_approve', 'manager_reject', 'dept_reject', 'dept_return']))),   // old HOD steps kept for history
                default => $q->where($pending),
            };
        } elseif ($tab === 'documents') {
            $q->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->where(fn ($w) => $w
                ->where(fn ($a) => $a->where('original_required', 1)->whereNull('original_received_on')->whereNotNull('bill_submitted_at'))
                ->orWhere(fn ($a) => $a->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')));
        } elseif ($tab === 'payments') {
            $q->where('status', 'fin_approved')->where(fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0));
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
            'vendor_id' => 'nullable|string|max:20',   // BTC-VENDOR-CORE-01: "C<core id>" or an old vendor_list id
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
            'pax_count' => 'nullable|integer|min:1|max:500',          // BTC-REQ-20
            'travellers' => 'nullable|array|max:100', 'travellers.*' => 'integer',
            'guests' => 'nullable|string|max:500',
            'request_remarks' => 'nullable|string|max:1000',          // BTC-REQ-25
        ], ['*.max' => 'The amount cannot be more than ₹99,99,999.99.', '*.decimal' => 'Use at most two decimals.']);
        if (! empty($v['quotation_amount']) && (float) ($v['advance_amount'] ?? 0) > (float) $v['quotation_amount']) {
            return response()->json(['success' => false, 'message' => 'Advance needed cannot be more than the quotation amount.'], 422);   // BTC-AMT-01
        }
        // BTC-REQ-25 (19 Nov 2026): only the categories of the form for a new case; "Other" purpose needs the remarks;
        // PNR only for Air with Advance Required
        if (! (config("btc.categories.{$v['category']}.new_case") ?? true)) {
            return response()->json(['success' => false, 'message' => 'Choose a category from the list.'], 422);
        }
        if (str_starts_with((string) $v['purpose'], 'Other') && trim((string) ($v['request_remarks'] ?? '')) === '') {
            return response()->json(['success' => false, 'message' => 'Purpose "Other": write the purpose in the remarks.'], 422);
        }
        if (! ($v['category'] === 'air' && $v['route'] === 'employee_advance')) {
            $v['pnr'] = null;
        }
        $user = auth()->user();
        if (! in_array($v['route'], (array) config('btc.employee_routes', ['employee_advance']), true)) {   // BTC-REQ-23 (18 Nov 2026): also for the BTC admin
            return response()->json(['success' => false, 'message' => 'Choose "Admin Booking Required" or "Advance Required".'], 422);   // BTC-FORM-01 / BTC-REQ-03
        }
        // Admin may raise for any employee; others for themselves
        $empId = ($user->can('btc.admin') && ! empty($v['employee_id'])) ? (int) $v['employee_id'] : (int) $user->employee_id;
        $emp = DB::connection('hrims')->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')->where('e.EmployeeID', $empId)
            ->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.DepartmentId', 'd.department_name', 'g.RepEmployeeID']);
        if (! $emp) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 422);
        }
        $v['vendor_code'] = null;
        if (! empty($v['vendor_id'])) {
            $vendor = \App\Services\Btc\CoreVendors::find($v['vendor_id']);
            $v['vendor_name'] = $vendor['name'] ?? $v['vendor_name'] ?? null;
            $v['vendor_gstin'] = $vendor['gstin'] ?? $v['vendor_gstin'] ?? null;
            $v['vendor_code'] = $vendor['vendor_code'] ?? null;
            $v['vendor_id'] = $vendor['vendor_id'] ?? null;
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
                ...(\Illuminate\Support\Facades\Schema::hasColumn('btc_cases', 'vendor_code') ? ['vendor_code' => $v['vendor_code'] ?? null] : []),
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
            ] + (\Illuminate\Support\Facades\Schema::hasColumn('btc_cases', 'pax_count') ? [
                'pax_count' => $v['pax_count'] ?? null, 'guests' => trim((string) ($v['guests'] ?? '')) ?: null,
                'travellers' => ! empty($v['travellers']) ? json_encode(DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $v['travellers'])
                    ->get(['EmployeeID as id', 'EmpCode as code', DB::raw("TRIM(CONCAT_WS(' ', Fname, Sname, Lname)) as name")])) : null,
            ] : []) + (\Illuminate\Support\Facades\Schema::hasColumn('btc_cases', 'request_remarks') ? ['request_remarks' => trim((string) ($v['request_remarks'] ?? '')) ?: null] : []));
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
            'duplicates' => array_intersect(['verify1', 'confirm_extraction'], BtcWorkflow::allowed($c, $user)) ? BtcWorkflow::duplicates($c) : [],
            // BTC-PAY-01 / CLOSE-01 / DUP-02 / TDS-02 / GST-01 (17 Nov 2026)
            'payments' => \Illuminate\Support\Facades\Schema::hasTable('btc_payments') ? $c->payments()->get()->map(fn ($p) => ['id' => $p->id, 'amount' => (float) $p->amount,
                'on' => optional($p->paid_on)->format('d/m/Y'), 'mode' => config("btc.payment_modes.{$p->mode}", $p->mode), 'utr' => $p->utr_number, 'bank_ref' => $p->bank_ref,
                'status' => $p->status, 'failed_reason' => $p->failed_reason]) : [],
            'payment_modes' => config('btc.payment_modes'),
            'closure_gaps' => in_array($c->status, ['paid', 'fin_approved'], true) ? $c->closureGaps() : [],
            // BTC-V2: the read invoice data, the bill file for side-by-side comparison, who uploaded / verified / approved
            'extraction' => ['status' => $c->extract_status, 'note' => $c->extract_note, 'read' => json_decode((string) $c->extract_json, true)['fields'] ?? null,
                'missing' => \App\Services\Btc\BtcExtraction::missing($c), 'breakup' => \App\Services\Btc\BtcExtraction::breakupError($c)],
            'bill_file' => ($bf = $c->files->where('kind', 'bill')->sortByDesc('id')->first()) ? ['url' => route('btc.file', $bf->id), 'name' => $bf->file_name, 'pdf' => str_ends_with(strtolower($bf->file_name), '.pdf')] : null,
            'stamps' => ['uploaded' => $this->stamp($c->bill_uploaded_by, $c->bill_uploaded_at), 'source' => config('btc.bill_sources.'.$c->bill_source, $c->bill_source),
                'verify1' => $this->stamp($c->verify1_by, $c->verify1_at), 'focus' => $this->stamp($c->finance_by, $c->finance_at), 'approved' => $this->stamp($c->fin_approved_by, $c->fin_approved_at),
                'dms' => $c->dms_received_on ? 'received '.$c->dms_received_on->format('d-m-Y').($c->dms_to_finance_on ? ', to Finance '.$c->dms_to_finance_on->format('d-m-Y') : '') : null],
            'extract_fields' => config('btc.extraction.fields'), 'bill_sources' => config('btc.bill_sources'),
            // BTC-SCAN-01: the punch form of the bill type (as on the bill), the field checks of Finance level verification,
            // the Finance Advance Approval stage
            'punch' => $this->punchData($c),
            'verify_checks' => json_decode((string) ($c->verify1_checks ?? ''), true),
            'adv' => ['stage' => $c->adv_stage ?? null, 'approved' => $this->stamp($c->adv_fin_by ?? null, $c->adv_fin_at ?? null), 'instructed' => $this->stamp($c->adv_instructed_by ?? null, $c->adv_instructed_at ?? null), 'ref' => $c->adv_instruction_ref ?? null],
            'claim_overlaps' => $user->can('btc.finance') || $user->can('btc.checker') || $user->can('btc.admin') || $user->can('btc.verifier') ? BtcWorkflow::claimOverlaps($c) : [],
            'vendor_year' => $c->bill_no ? \App\Services\Btc\BtcLedger::vendorYearTotal($c) : null,
            'gstin_ok' => $c->vendor_gstin ? \App\Services\Btc\BtcLedger::gstinValid($c->vendor_gstin) : null,
            'overdue_advances' => BtcWorkflow::overdueAdvances((int) $c->employee_id, (int) $c->id)->pluck('case_no'),
            // BTC-EXC-01 / FIN-02 / TRY-01 (17 Nov 2026)
            'exceptions' => $this->canAny($user, 'btc.finance|btc.checker|btc.verifier|btc.exceptions|btc.audit|btc.treasury') ? \App\Services\Btc\BtcExceptions::forCase($c) : [],
            'journal' => $c->finance_at ? \App\Services\Btc\BtcLedger::journal($c) : [],
            'dims' => array_intersect(['focus_entry'], BtcWorkflow::allowed($c, $user)) ? \App\Services\Btc\BtcLedger::dimensions($c) : null,
            'files' => $c->files->map(fn ($f) => ['id' => $f->id, 'kind' => $f->kind, 'name' => $f->file_name, 'url' => route('btc.file', $f->id)]),
            'logs' => $c->logs->map(fn ($l) => ['at' => optional($l->created_at)->format('d/m/Y H:i'), 'by' => $l->user_name, 'action' => $l->action,
                'to' => config("btc.statuses.{$l->to_status}", $l->to_status), 'remark' => $l->remark]),
            'default_ledger' => config("btc.focus.expense_ledgers.{$c->category}"),
            'advance' => $c->advanceFigures(), 'pending_with' => $c->pendingWith(), 'stages' => $c->stageSummary(),

        ]);
    }

    /** BTC-V2: "name, dd-mm-yyyy hh:mm" for who did a step. */
    private function stamp($userId, $at): ?string
    {
        if (! $at) {
            return null;
        }

        return trim(($userId ? (\App\Models\User::whereKey($userId)->value('name') ?? '') : '').', '.\Carbon\Carbon::parse($at)->format('d-m-Y H:i'), ', ');
    }

    public function act(Request $request, int $id)
    {
        $c = BtcCase::findOrFail($id);
        $user = auth()->user();
        $this->authorizeView($c, $user);
        // BTC-AMT-01 (15 Nov 2026): amounts must fit the amount columns (up to 99,99,999.99) with at most two
        // decimals - an over-long number gave "Server Error"
        $request->validate(array_fill_keys(['quotation_amount', 'advance_amount', 'taxable_amount', 'gst_amount', 'bill_amount', 'paid_amount', 'advance_adjusted', 'tds_on_advance',
            'cgst_amount', 'sgst_amount', 'igst_amount', 'other_charges',
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
        abort_unless(auth()->user()->can('btc.finance') || auth()->user()->can('btc.verifier') || auth()->user()->can('btc.admin'), 403);   // BTC-V2: from the Verifier 1 screen
        $q = BtcCase::where('company_id', (int) session('company_id', 1))->whereNotNull('finance_at')->whereIn('status', ['focus_done', 'fin_approved', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid', 'closed']);
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

    public function saveFiles(Request $request, BtcCase $c): void
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

    /** BTC-SCAN-01: punch form of the case's bill type with the values punched, else as read from the bill. */
    private function punchData(BtcCase $c): ?array
    {
        $form = config('btc.punch_forms.'.config('btc.punch_form_of.'.$c->category, 'meeting'));
        if (! $form) {
            return null;
        }
        $saved = json_decode((string) ($c->punch_json ?? ''), true);
        $raw = (array) (json_decode((string) $c->extract_json, true)['raw'] ?? []);
        $vals = [];
        foreach (array_keys($form['fields']) as $k) {
            $v = is_array($saved) && array_key_exists($k, $saved) ? $saved[$k] : ($raw[$k] ?? null);
            if ($v === null && $k === 'Train Number') {
                $v = $raw['Bus Number'] ?? null;
            }
            $vals[$k] = is_scalar($v) ? (string) $v : '';
        }

        return ['title' => $form['title'], 'fields' => $form['fields'], 'values' => $vals, 'numbers' => config('btc.punch_numbers'), 'saved' => is_array($saved)];
    }

    public function authorizeView(BtcCase $c, $user): void
    {
        $empId = (int) ($user->employee_id ?? 0);
        // BTC-REQ-01: the new BTC desks (booking, advance, verifier, invoices, register) open cases too
        $desk = collect(['btc.dms', 'btc.finance', 'btc.bill_upload', 'btc.verifier', 'btc.fin_approver', 'btc.checker', 'btc.treasury', 'btc.audit', 'btc.exceptions', 'btc.admin', 'btc.booking', 'btc.advance', 'btc.verifier', 'btc.invoices', 'btc.register'])->contains(fn ($p) => $user->can($p));
        // BTC-TEAM-01: a manager anywhere above the requester may open the request (read; actions stay by role)
        // a case with no employee (scanned bill, department bill) is employee_id 0: a login with no employee is not its owner
        abort_unless($desk || ($empId > 0 && in_array($empId, [(int) $c->employee_id, (int) $c->manager_id, (int) $c->dept_approver_id], true)) || (int) $c->created_by === (int) $user->id
            || \App\Support\ReportingTree::isAbove($empId, (int) $c->employee_id), 403);
    }
}
