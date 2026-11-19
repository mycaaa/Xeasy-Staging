<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BtcCase;
use App\Models\BtcCaseFile;
use App\Services\Btc\BtcAccess;
use App\Services\Btc\BtcExtraction;
use App\Services\Btc\BtcWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-V2 (19 Nov 2026, BTC requirement sheet item 24): the Finance screens of the revised BTC workflow.
 *
 *  Bill Upload by Finance     scanned bill (from DMS, vendor, e-mail, Admin, other department) against a BTC request,
 *                             or a new BTC case when there is none; supporting documents; source, who and when
 *  Automatic Data Extraction  bills read from the invoice; the user corrects the read fields and sends to Verifier 1
 *  Finance Verifier 1         extracted data checked against the original invoice: verify / return / reject; then the
 *                             Focus entry (ledger, TDS, dimensions, JV) and its export on the same screen
 *  Finance Approval           final approval: approve / return / reject / hold
 *  Treasury Payment           Finance-approved cases: record payment and UTR (one or many), Excel of what to pay
 * Every step itself runs through BtcWorkflow (same rules as the case window); the screens are queues of it.
 */
class BtcV2Controller extends Controller
{
    private function need(string $perms): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->can('btc.admin') || collect(explode('|', $perms))->contains(fn ($p) => $u->can($p))), 403, 'You do not have access to this BTC page.');
    }

    private function ready(): bool
    {
        return Schema::hasTable('btc_cases') && Schema::hasColumn('btc_cases', 'extract_status');
    }

    private function cases()
    {
        return BtcAccess::applyScope(BtcCase::query()->where('company_id', (int) session('company_id', 1)), auth()->user());
    }

    /** One queue page: the cases of the given stages, newest first, with the shared case window. */
    private function queue(string $view, string $title, string $icon, string $sub, array $tabs, string $tab, Request $request, array $extra = [])
    {
        if (! $this->ready()) {
            return view('admin.btc.pages.not-ready');
        }
        $tab = array_key_exists($tab, $tabs) ? $tab : array_key_first($tabs);
        $counts = collect($tabs)->map(fn ($t) => ($t[1])($this->cases())->count())->all();
        $q = ($tabs[$tab][1])($this->cases());
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('case_no', 'like', "%{$s}%")->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('bill_no', 'like', "%{$s}%")
                ->orWhere('emp_code', 'like', "%{$s}%")->orWhere('emp_name', 'like', "%{$s}%")->orWhere('vendor_gstin', 'like', "%{$s}%"));
        }
        $rows = $q->orderByDesc('updated_at')->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.btc.pages.'.$view, array_merge(app(BtcController::class)->caseWindowData(), [
            'rows' => $rows, 'tabs' => collect($tabs)->map(fn ($t) => $t[0])->all(), 'counts' => $counts, 'tab' => $tab,
            'title' => $title, 'icon' => $icon, 'sub' => $sub,
        ], $extra));
    }

    // ---- Bill Upload by Finance ---------------------------------------------------------------------------------
    public function upload(Request $request)
    {
        $this->need('btc.bill_upload|btc.finance');
        $me = auth()->id();

        return $this->queue('bill-upload', 'Bill Upload by Finance', 'ri-upload-cloud-2-line',
            'Scan and upload bills received from DMS, vendors, e-mail or other departments - against the BTC request, or as a new BTC case. The bill is then read automatically.',
            [
                'waiting' => ['Physical bills with Finance', fn ($q) => $q->where('status', 'bill_with_finance')],
                'with_dms' => ['Still with DMS', fn ($q) => $q->where('status', 'bill_with_dms')],
                'mine' => ['Uploaded by me', fn ($q) => $q->where('bill_uploaded_by', $me)->whereNotNull('bill_uploaded_at')],
                'all' => ['All uploads', fn ($q) => $q->whereNotNull('bill_uploaded_at')],
            ], (string) $request->input('tab', 'waiting'), $request,
            ['sources' => config('btc.bill_sources'), 'categories' => collect(config('btc.categories'))->map(fn ($c) => $c['label'])->all(),
                'preselect' => $request->filled('case') ? BtcCase::find((int) $request->input('case')) : null]);
    }

    /** Open BTC requests a bill can be uploaded against (search by request no., employee, vendor). */
    public function uploadCases(Request $request)
    {
        $this->need('btc.bill_upload|btc.finance');
        $s = trim((string) $request->input('q'));

        return $this->cases()->whereIn('status', BtcWorkflow::ACTIONS['finance_upload'][0])
            ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w->where('case_no', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")
                ->orWhere('emp_name', 'like', "%{$s}%")->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('booking_ref', 'like', "%{$s}%")))
            ->orderByDesc('id')->limit(25)->get()
            ->map(fn ($c) => ['id' => $c->id, 'text' => $c->case_no.' · '.trim($c->emp_code.' '.$c->emp_name).' · '.$c->categoryLabel().($c->vendor_name ? ' · '.$c->vendor_name : '').' · '.$c->statusLabel()]);
    }

    public function uploadStore(Request $request)
    {
        $this->need('btc.bill_upload|btc.finance');
        abort_unless($this->ready(), 503, 'Run the BTC database update first.');
        $v = $request->validate([
            'case_id' => 'nullable|integer',
            'bill_source' => 'required|in:'.implode(',', array_keys(config('btc.bill_sources'))),
            'bill' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'supporting' => 'nullable|array|max:10', 'supporting.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png',
            'category' => 'required_without:case_id|nullable|in:'.implode(',', array_keys(config('btc.categories'))),
            'employee_id' => 'nullable|integer',
            'department_name' => 'nullable|string|max:150',
            'purpose' => 'required_without:case_id|nullable|string|max:500',
            'remark' => 'nullable|string|max:500',
        ], ['bill.required' => 'Choose the scanned bill.', 'purpose.required_without' => 'Give the purpose of the bill (new BTC case).',
            'category.required_without' => 'Choose the category (new BTC case).']);
        $user = auth()->user();
        $wf = app(BtcWorkflow::class);
        $created = false;
        if (! empty($v['case_id'])) {
            $c = $this->cases()->findOrFail((int) $v['case_id']);
            if (! in_array('finance_upload', BtcWorkflow::allowed($c, $user), true)) {
                return back()->withErrors(['bill' => "{$c->case_no} is \"{$c->statusLabel()}\"; a bill cannot be uploaded against it now."])->withInput();
            }
        } else {
            // a bill with no BTC request: a new BTC case is created (route C, direct bill)
            $emp = ! empty($v['employee_id']) ? DB::connection('hrims')->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')->where('e.EmployeeID', (int) $v['employee_id'])
                ->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.DepartmentId', 'd.department_name']) : null;
            if (! $emp && trim((string) ($v['department_name'] ?? '')) === '') {
                return back()->withErrors(['bill' => 'For a new BTC case choose the employee it is for, or type the department.'])->withInput();
            }
            $cat = config('btc.categories.'.$v['category']);
            $c = DB::transaction(function () use ($v, $emp, $user, $cat) {
                $c = BtcCase::create([
                    'case_no' => 'TMP'.uniqid(), 'company_id' => (int) session('company_id', 1), 'route' => 'direct_bill', 'category' => $v['category'],
                    'employee_id' => $emp->EmployeeID ?? 0, 'emp_code' => $emp->EmpCode ?? null,
                    'emp_name' => $emp ? trim(preg_replace('/\s+/', ' ', $emp->Fname.' '.$emp->Sname.' '.$emp->Lname)) : null,
                    'department_id' => $emp->DepartmentId ?? null, 'department_name' => $emp->department_name ?? trim((string) $v['department_name']),
                    'purpose' => trim($v['purpose']), 'boarding_pass_required' => (bool) ($cat['boarding_pass'] ?? false), 'original_required' => (bool) ($cat['original_required'] ?? false),
                    'status' => 'bill_with_finance', 'created_by' => $user->id, 'gst_amount' => 0, 'advance_amount' => 0,
                ]);
                $c->case_no = sprintf('BTC/%s/%05d', now('Asia/Kolkata')->format('y'), $c->id);
                $c->save();
                \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'created', 'from_status' => null, 'to_status' => 'bill_with_finance',
                    'remark' => 'New BTC case created at Bill Upload by Finance (no earlier request)', 'user_id' => $user->id, 'user_name' => $user->name]);

                return $c;
            });
            $created = true;
        }
        // the scanned invoice and the supporting documents (kept as uploaded: the original for later reference)
        $store = function ($file, string $kind, int $i) use ($c, $user) {
            $ext = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs("btc/{$c->company_id}/{$c->id}", $kind.'_'.now()->format('YmdHis').'_'.$i.'.'.$ext, 'local');
            BtcCaseFile::create(['btc_case_id' => $c->id, 'kind' => $kind, 'file_path' => $path, 'file_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(), 'uploaded_by' => $user->id]);
        };
        $store($request->file('bill'), 'bill', 0);
        foreach ((array) $request->file('supporting', []) as $i => $f) {
            $store($f, 'supporting', $i + 1);
        }
        try {
            $c = $wf->act($c->fresh(), 'finance_upload', ['bill_source' => $v['bill_source'], 'remark' => $v['remark'] ?? ''], $user);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['bill' => $c->case_no.': '.$e->getMessage()]);
        }
        $msg = ($created ? "New BTC case {$c->case_no} created. " : "Bill uploaded against {$c->case_no}. ")
            .($c->status === 'extracted' ? 'The invoice was read completely and is with Finance Verifier 1.' : 'Check the read data on Automatic Data Extraction ('.($c->extract_note ?: 'to complete').').');
        if ($d = BtcWorkflow::duplicates($c)->pluck('case_no')->implode(', ')) {
            $msg .= " Possible duplicate of {$d} - see the Exception Queue.";
        }

        return redirect()->route($c->status === 'extracted' ? 'btc.bill-upload' : 'btc.extraction', $c->status === 'extracted' ? ['tab' => 'mine'] : ['case' => $c->id])->with('success', $msg);
    }

    // ---- Automatic Data Extraction ------------------------------------------------------------------------------
    public function extraction(Request $request)
    {
        $this->need('btc.bill_upload|btc.finance');

        return $this->queue('queue', 'Automatic Data Extraction', 'ri-scan-2-line',
            'Uploaded bills are read automatically into the Finance data format. Open a bill, compare with the original invoice, correct what was read wrongly and send it to Finance Verifier 1.',
            [
                'todo' => ['To check / complete', fn ($q) => $q->where('status', 'bill_submitted')],
                'failed' => ['Not read', fn ($q) => $q->where('status', 'bill_submitted')->where('extract_status', 'failed')],
                'returned' => ['Returned by Verifier 1', fn ($q) => $q->where('status', 'bill_submitted')->whereExists(fn ($l) => $l->from('btc_case_logs')->whereColumn('btc_case_logs.btc_case_id', 'btc_cases.id')->where('btc_case_logs.action', 'verify1_return'))],
                'sent' => ['Sent to Verifier 1', fn ($q) => $q->where('status', 'extracted')],
            ], (string) $request->input('tab', 'todo'), $request, ['open' => (int) $request->input('case')]);
    }

    /** Read the attached invoice again; only empty fields are filled. */
    public function reread(int $id)
    {
        $c = BtcCase::findOrFail($id);
        $u = auth()->user();
        app(BtcController::class)->authorizeView($c, $u);
        abort_unless(in_array('save_extraction', BtcWorkflow::allowed($c, $u), true), 403, 'The invoice can be read again only at the data extraction stage.');
        $r = BtcExtraction::run($c);
        \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'extraction', 'from_status' => $c->status, 'to_status' => $c->status,
            'remark' => $r['ok'] ? 'Invoice read again' : 'Invoice not read: '.$r['error'], 'user_id' => $u->id, 'user_name' => $u->name]);
        $c->refresh();

        return response()->json(['success' => $r['ok'], 'message' => $r['ok'] ? ($c->extract_note ?: 'All required fields found.') : $r['error']]);
    }

    // ---- Finance Verifier 1 (+ Focus entry) ---------------------------------------------------------------------
    public function verifier(Request $request)
    {
        $this->need('btc.verifier|btc.audit');

        return $this->queue('queue', 'Finance Verifier 1', 'ri-checkbox-multiple-line',
            'Compare the extracted data with the original invoice: verify, return to the Finance user for correction, or reject. After verifying, prepare the Focus entry here and export it.',
            [
                'verify' => ['To verify', fn ($q) => $q->where('status', 'extracted')],
                'focus' => ['Focus entry to prepare', fn ($q) => $q->where('status', 'finance_verified')],
                'export' => ['Focus entry not exported', fn ($q) => $q->whereIn('status', ['focus_done', 'fin_approved', 'paid'])->whereNotNull('finance_at')->whereNull('focus_exported_at')],
                'done' => ['Sent for approval', fn ($q) => $q->where('status', 'focus_done')],
            ], (string) $request->input('tab', 'verify'), $request, ['focusExport' => true]);
    }

    // ---- Finance Approval ---------------------------------------------------------------------------------------
    public function approval(Request $request)
    {
        $this->need('btc.fin_approver|btc.audit');

        return $this->queue('queue', 'Finance Approval', 'ri-shield-check-line',
            'Final approval before Treasury: invoice, extracted data, Focus entry and export. Approve, return, reject or hold.',
            [
                'pending' => ['To approve', fn ($q) => $q->where('status', 'focus_done')->where(fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0))],
                'hold' => ['On hold', fn ($q) => $q->whereIn('status', ['focus_done', 'fin_approved'])->where('on_hold', 1)],
                'approved' => ['Approved, with Treasury', fn ($q) => $q->where('status', 'fin_approved')],
            ], (string) $request->input('tab', 'pending'), $request);
    }

    // ---- Treasury Payment ---------------------------------------------------------------------------------------
    public function treasury(Request $request)
    {
        $this->need('btc.treasury|btc.audit');

        return $this->queue('treasury', 'Treasury Payment', 'ri-bank-line',
            'Finance-approved cases. Pay in the bank, then record the payment and UTR here (one case or several). Payment details are logged on each case.',
            [
                'topay' => ['To pay', fn ($q) => $q->where('status', 'fin_approved')->where(fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0))],
                'hold' => ['On hold', fn ($q) => $q->where('status', 'fin_approved')->where('on_hold', 1)],
                'paid' => ['Paid', fn ($q) => $q->whereIn('status', ['paid', 'closed'])->whereNotNull('paid_on')],
            ], (string) $request->input('tab', 'topay'), $request, ['modes' => config('btc.payment_modes')]);
    }

    /** Record payments for the ticked cases: one date and mode, a UTR per case (or one UTR for a bulk transfer). */
    public function treasuryPay(Request $request)
    {
        $this->need('btc.treasury');
        $v = $request->validate([
            'paid_on' => 'required|date|before_or_equal:today', 'payment_mode' => 'required|in:'.implode(',', array_keys(config('btc.payment_modes'))),
            'rows' => 'required|array|min:1|max:100', 'rows.*.id' => 'required|integer', 'rows.*.utr' => 'nullable|string|max:40',
            'rows.*.amount' => 'nullable|numeric|min:0|max:9999999.99', 'rows.*.pick' => 'nullable', 'bank_ref' => 'nullable|string|max:60',
        ], ['rows.required' => 'Tick the cases paid.']);
        $user = auth()->user();
        $wf = app(BtcWorkflow::class);
        $ok = [];
        $bad = [];
        $picked = collect($v['rows'])->filter(fn ($r) => ! empty($r['pick']));
        if ($picked->isEmpty()) {
            return back()->withErrors(['pay' => 'Tick the cases paid.']);
        }
        foreach ($picked as $r) {
            $c = $this->cases()->find((int) $r['id']);
            if (! $c) {
                continue;
            }
            $due = round((float) $c->net_payable - (float) $c->paid_amount, 2);
            try {
                // a bulk bank transfer: the same bank reference, the case no. makes each UTR entry unique
                $utr = trim((string) ($r['utr'] ?? '')) ?: (($v['bank_ref'] ?? '') !== '' ? $v['bank_ref'].substr(preg_replace('/\D/', '', $c->case_no), -5) : '');
                $wf->act($c, 'pay', ['paid_amount' => isset($r['amount']) && $r['amount'] !== '' ? $r['amount'] : $due, 'paid_on' => $v['paid_on'], 'payment_mode' => $v['payment_mode'],
                    'utr_number' => $utr, 'payment_bank_ref' => $v['bank_ref'] ?? null, 'remark' => 'Treasury Payment screen'], $user);
                $ok[] = $c->case_no;
            } catch (\RuntimeException $e) {
                $bad[] = $c->case_no.': '.$e->getMessage();
            }
        }
        $back = redirect()->route('btc.treasury');

        return $bad ? $back->with('success', $ok ? 'Paid: '.implode(', ', $ok) : null)->withErrors(['pay' => implode(' | ', $bad)]) : $back->with('success', 'Payment recorded: '.implode(', ', $ok).'.');
    }

    /** Excel of the Finance-approved cases to pay (Treasury pays in the bank from this list). */
    public function treasuryExport()
    {
        $this->need('btc.treasury|btc.audit');
        $rows = $this->cases()->where('status', 'fin_approved')->orderByDesc('fin_approved_at')->get()->map(fn ($c) => [
            $c->case_no, optional($c->fin_approved_at)->format('d-m-Y'), $c->vendor_name, $c->vendor_gstin, $c->bill_no, optional($c->bill_date)->format('d-m-Y'),
            (float) $c->bill_amount, (float) $c->tds_amount, (float) $c->advance_adjusted, (float) $c->net_payable, (float) $c->paid_amount,
            round((float) $c->net_payable - (float) $c->paid_amount, 2), trim($c->emp_code.' '.$c->emp_name), $c->department_name, $c->on_hold ? 'On hold: '.$c->hold_reason : '',
        ])->all();
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['BTC ID', 'Approved on', 'Vendor', 'GSTIN', 'Invoice no.', 'Invoice date', 'Invoice amount', 'TDS', 'Advance set off', 'Net payable', 'Paid', 'To pay', 'Employee', 'Department', 'Hold'];
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'BTC_Treasury_to_pay_'.now()->format('Ymd_His').'.xlsx');
    }
}
