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
            ['sources' => config('btc.bill_sources'), 'categories' => collect((array) config('btc.classification'))->mapWithKeys(fn ($k) => [$k => config("btc.categories.{$k}.label", $k)])->all(),
                'preselect' => $request->filled('case') ? BtcCase::find((int) $request->input('case')) : null]);
    }

    /** Open BTC requests a bill can be uploaded against (search by request no., employee, vendor). */
    public function uploadCases(Request $request)
    {
        $this->need('btc.bill_upload|btc.finance|btc.dms');   // BTC-SCAN-01: Scanner / Classification (DMS) too
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
            'category' => 'required_without:case_id|nullable|in:'.implode(',', (array) config('btc.classification')),
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
            .($c->status === 'extracted' ? 'The invoice was read completely and is with Finance Verifier 1.' : 'Punch it on the Punch Screen ('.($c->extract_note ?: 'check the read data').').');
        if ($d = BtcWorkflow::duplicates($c)->pluck('case_no')->implode(', ')) {
            $msg .= " Possible duplicate of {$d} - see the Exception Queue.";
        }

        return redirect()->route($c->status === 'extracted' ? 'btc.bill-upload' : 'btc.punch', $c->status === 'extracted' ? ['tab' => 'mine'] : ['case' => $c->id])->with('success', $msg);
    }

    // ---- BTC-SCAN-01 (19 Nov 2026, tracker tab "Data Punching Form & Finance approval screens") ------------------
    // Scanner -> Classification -> Punch Screen -> Finance level verification; Finance Advance Approval.

    /** Scanner: DMS / Finance scan the bills (several at a time); a bill against a known request goes straight to the Punch Screen. */
    public function scanner(Request $request)
    {
        $this->need('btc.dms|btc.bill_upload|btc.finance');
        $me = auth()->id();

        return $this->queue('scanner', 'Scanner', 'ri-scan-line',
            'Scan the bills received (physical bills from DMS, vendors, e-mail, other departments). Several bills can be scanned at once; each is then given its type on Classification. A bill for a known BTC request can be scanned against it.',
            [
                'today' => ['Scanned today', fn ($q) => $q->whereNotNull('bill_uploaded_at')->whereDate('bill_uploaded_at', now()->toDateString())],
                'toclassify' => ['To classify', fn ($q) => $q->where('status', 'scanned')],
                'waiting' => ['Physical bills with Finance', fn ($q) => $q->where('status', 'bill_with_finance')],
                'with_dms' => ['Physical bills with DMS', fn ($q) => $q->where('status', 'bill_with_dms')],
                'mine' => ['Scanned by me', fn ($q) => $q->where('bill_uploaded_by', $me)],
            ], (string) $request->input('tab', 'today'), $request,
            ['sources' => config('btc.bill_sources'), 'preselect' => $request->filled('case') ? BtcCase::find((int) $request->input('case')) : null]);
    }

    public function scannerStore(Request $request)
    {
        $this->need('btc.dms|btc.bill_upload|btc.finance');
        abort_unless($this->ready() && Schema::hasColumn('btc_cases', 'punch_json'), 503, 'Run the BTC database update first.');
        $v = $request->validate([
            'case_id' => 'nullable|integer',
            'bill_source' => 'required|in:'.implode(',', array_keys(config('btc.bill_sources'))),
            'bills' => 'required|array|min:1|max:25', 'bills.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png',
            'supporting' => 'nullable|array|max:10', 'supporting.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png',
            'remark' => 'nullable|string|max:500',
        ], ['bills.required' => 'Choose the scanned bill(s).', 'bills.*.mimes' => 'A bill must be a PDF, JPG or PNG.', 'bills.*.max' => 'A bill file cannot be more than 10 MB.']);
        $user = auth()->user();
        $wf = app(BtcWorkflow::class);
        $bills = (array) $request->file('bills', []);
        if (! empty($v['case_id'])) {
            if (count($bills) > 1) {
                return back()->withErrors(['bills' => 'Against a BTC request scan one bill (add the rest as supporting documents).'])->withInput();
            }
            $c = $this->cases()->findOrFail((int) $v['case_id']);
            if (! in_array('finance_upload', BtcWorkflow::allowed($c, $user), true)) {
                return back()->withErrors(['bills' => "{$c->case_no} is \"{$c->statusLabel()}\"; a bill cannot be scanned against it now."])->withInput();
            }
            $this->storeFile($c, $bills[0], 'bill', 0, $user);
            foreach ((array) $request->file('supporting', []) as $i => $f) {
                $this->storeFile($c, $f, 'supporting', $i + 1, $user);
            }
            try {
                $c = $wf->act($c->fresh(), 'finance_upload', ['bill_source' => $v['bill_source'], 'remark' => trim('Scanned. '.($v['remark'] ?? ''))], $user);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['bills' => $c->case_no.': '.$e->getMessage()]);
            }

            return redirect()->route('btc.scanner', ['tab' => 'today'])->with('success', "Bill scanned against {$c->case_no}; it is on the Punch Screen ({$c->categoryLabel()}).");
        }
        // bills with no request: one scanned case each, to be classified
        $made = [];
        foreach ($bills as $i => $file) {
            $c = DB::transaction(function () use ($v, $user) {
                $c = BtcCase::create([
                    'case_no' => 'TMP'.uniqid(), 'company_id' => (int) session('company_id', 1), 'route' => 'direct_bill', 'category' => 'other',
                    'employee_id' => 0, 'purpose' => 'Scanned bill - to classify', 'status' => 'scanned', 'created_by' => $user->id,
                    'gst_amount' => 0, 'advance_amount' => 0, 'bill_source' => $v['bill_source'], 'bill_uploaded_by' => $user->id, 'bill_uploaded_at' => now(),
                    'boarding_pass_required' => false, 'original_required' => false,
                ]);
                $c->case_no = sprintf('BTC/%s/%05d', now('Asia/Kolkata')->format('y'), $c->id);
                $c->save();
                \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'scanned', 'from_status' => null, 'to_status' => 'scanned',
                    'remark' => trim('Bill scanned ('.config('btc.bill_sources.'.$v['bill_source']).'). '.($v['remark'] ?? '')), 'user_id' => $user->id, 'user_name' => $user->name]);

                return $c;
            });
            $this->storeFile($c, $file, 'bill', 0, $user);
            if ($i === 0) {
                foreach ((array) $request->file('supporting', []) as $j => $f) {
                    $this->storeFile($c, $f, 'supporting', $j + 1, $user);
                }
            }
            $made[] = $c->case_no;
        }

        return redirect()->route('btc.scanner', ['tab' => 'toclassify'])->with('success', count($made).' bill(s) scanned: '.implode(', ', $made).'. Give each its type on Classification.');
    }

    private function storeFile(BtcCase $c, $file, string $kind, int $i, $user): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("btc/{$c->company_id}/{$c->id}", $kind.'_'.now()->format('YmdHis').'_'.$i.'.'.$ext, 'local');
        BtcCaseFile::create(['btc_case_id' => $c->id, 'kind' => $kind, 'file_path' => $path, 'file_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(), 'uploaded_by' => $user->id]);
    }

    /** Classification: front screen of scanned bills; the form gives the bill type (Hotel, HRV, Air, Train, Bus, Meals, Meeting). */
    public function classification(Request $request)
    {
        $this->need('btc.dms|btc.bill_upload|btc.finance');

        return $this->queue('classification', 'Classification', 'ri-price-tag-3-line',
            'Open a scanned bill, choose its type (Hotel, HRV, Air, Train, Bus, Meals, Meeting expense) and whom it is for, or link it to the BTC request it belongs to. The bill is then read and goes to the Punch Screen.',
            [
                'todo' => ['To classify', fn ($q) => $q->where('status', 'scanned')],
                'done' => ['Classified', fn ($q) => $q->whereExists(fn ($l) => $l->from('btc_case_logs')->whereColumn('btc_case_logs.btc_case_id', 'btc_cases.id')->where('btc_case_logs.action', 'classify'))],
            ], (string) $request->input('tab', 'todo'), $request,
            ['types' => collect((array) config('btc.classification'))->mapWithKeys(fn ($k) => [$k => config("btc.categories.{$k}.label", $k)])->all(),
                'purposes' => (array) config('btc.purposes', [])]);
    }

    /**
     * BTC-CLASSIFY-02 (24 Nov 2026, testing point 9): the scanned bill is read before anyone types - the readers of the
     * bill types are asked together, the type that reads the most fields is proposed, and the employee (by code, else
     * by full name) and an open BTC request of that employee are looked up. The person only checks and corrects.
     * The answer is kept on the scan, so it is read once.
     */
    public function suggest(int $id)
    {
        $this->need('btc.dms|btc.bill_upload|btc.finance');
        $scan = $this->cases()->where('status', 'scanned')->findOrFail($id);
        $prev = json_decode((string) $scan->extract_json, true);
        if (is_array($prev) && ! empty($prev['suggest'])) {
            return response()->json(['success' => true] + $prev['suggest']);
        }
        $f = $scan->files()->where('kind', 'bill')->orderByDesc('id')->first();
        if (! $f) {
            return response()->json(['success' => false, 'message' => 'No scanned file on this entry.']);
        }
        $types = ['hotel', 'air', 'train', 'vehicle', 'meals'];
        $link = \App\Services\Btc\InvoiceReader::fileUrl($f->file_path);
        try {
            $res = \Illuminate\Support\Facades\Http::pool(fn ($pool) => array_map(fn ($t) => $pool->as($t)->timeout((int) config('btc.reader.timeout', 60))
                ->withOptions(['verify' => \App\Support\TlsVerify::option()])
                ->post(\App\Services\Btc\InvoiceReader::endpoint($t), ['fileUrl' => $link]), $types));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'The bill reader could not be reached; classify by hand.']);
        }
        $best = null;
        $bestScore = 0;
        $reads = [];
        foreach ($types as $t) {
            $r = $res[$t] ?? null;
            $data = ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) ? (array) ($r->json('data') ?? []) : [];
            $data = array_filter($data, fn ($v) => is_scalar($v) && trim((string) $v) !== '' && ! in_array(strtolower(trim((string) $v)), ['0', 'n/a', 'na', 'null', '-'], true));
            $reads[$t] = $data;
            // the fields that only a bill of this type has decide it (hotel: check-in; air: PNR / flight; ...)
            $key = ['hotel' => ['Chk. In Date', 'Chk. Out Date', 'Hotel Name', 'Room Type', 'Number of Rooms'], 'air' => ['PNR Number', 'Airline', 'Flight Number', 'Ticket Number'],
                'train' => ['Train Number', 'Train Name', 'PNR Number', 'Coach'], 'vehicle' => ['Vehicle No', 'Start Reading', 'Closing Reading', 'Total KM', 'Per KM Rate'],
                'meals' => ['Particulars', 'Hotel Name']][$t];
            $score = count($data) + 3 * count(array_intersect_key($data, array_flip($key)));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$t, $score];
            }
        }
        if (! $best) {
            return response()->json(['success' => false, 'message' => 'The bill could not be read; classify by hand.']);
        }
        $read = $reads[$best];
        // the employee: an employee code on the bill, else a full name that matches one active employee
        $emp = null;
        $h = DB::connection('hrims');
        foreach (['Emp Code', 'Employee Code', 'Emp. Code'] as $k) {
            if (! $emp && ! empty($read[$k]) && ($code = preg_replace('/\D/', '', (string) $read[$k]))) {
                $emp = $h->table('hrm_employee')->where('EmpCode', ltrim($code, '0'))->orderByRaw("EmpStatus = 'A' DESC")->first(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname']);
            }
        }
        foreach (['Employee', 'Employee Name', 'Passenger', 'Passenger Name', 'Billing Name', 'Billing Person', 'Guest Name'] as $k) {
            if ($emp || empty($read[$k])) {
                continue;
            }
            $name = strtoupper(trim(preg_replace('/^(MR|MRS|MS|DR|SHRI)\.?\s+/i', '', preg_replace('/\s+/', ' ', (string) $read[$k]))));
            $parts = explode(' ', $name);
            if (count($parts) < 2) {
                continue;
            }
            $hits = $h->table('hrm_employee')->where('EmpStatus', 'A')->whereRaw('UPPER(Fname) = ?', [$parts[0]])->whereRaw('UPPER(Lname) = ?', [end($parts)])->limit(2)->get(['EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname']);
            if ($hits->count() === 1) {
                $emp = $hits->first();
            }
        }
        $empOut = $emp ? ['id' => (int) $emp->EmployeeID, 'text' => $emp->EmpCode.' - '.trim(preg_replace('/\s+/', ' ', $emp->Fname.' '.$emp->Sname.' '.$emp->Lname))] : null;
        // an open BTC request of that employee waiting for its bill (same type first)
        $linkOut = null;
        if ($emp) {
            $req = $this->cases()->whereIn('status', BtcWorkflow::ACTIONS['finance_upload'][0])->where('employee_id', (int) $emp->EmployeeID)
                ->orderByRaw('category = ? DESC', [$best])->orderByDesc('id')->first();
            if ($req) {
                $linkOut = ['id' => $req->id, 'text' => $req->case_no.' · '.trim($req->emp_code.' '.$req->emp_name).' · '.$req->categoryLabel().($req->vendor_name ? ' · '.$req->vendor_name : '').' · '.$req->statusLabel()];
            }
        }
        $out = ['category' => $best, 'category_label' => config("btc.categories.{$best}.label", $best), 'employee' => $empOut, 'link' => $linkOut,
            'read' => array_slice(array_map(fn ($v) => mb_substr((string) $v, 0, 120), $read), 0, 30, true)];
        $j = is_array($prev) ? $prev : [];
        $j['suggest'] = $out;
        $scan->extract_json = json_encode($j);
        $scan->save();

        return response()->json(['success' => true] + $out);
    }

    public function classify(Request $request, int $id)
    {
        $this->need('btc.dms|btc.bill_upload|btc.finance');
        $v = $request->validate([
            'category' => 'required|in:'.implode(',', (array) config('btc.classification')),
            'link_case_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'department_name' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:500',
            'remark' => 'nullable|string|max:500',
        ], ['category.required' => 'Choose the bill type.']);
        $user = auth()->user();
        $wf = app(BtcWorkflow::class);
        $scan = $this->cases()->where('status', 'scanned')->findOrFail($id);
        $label = config("btc.categories.{$v['category']}.label");
        if (! empty($v['link_case_id'])) {
            // the bill belongs to an open BTC request: its files move to that request, the scanned entry is closed
            $c = $this->cases()->findOrFail((int) $v['link_case_id']);
            if (! in_array('finance_upload', BtcWorkflow::allowed($c, $user), true)) {
                return response()->json(['success' => false, 'message' => "{$c->case_no} is \"{$c->statusLabel()}\"; the bill cannot be added to it now."], 422);
            }
            DB::transaction(function () use ($scan, $c, $user) {
                BtcCaseFile::where('btc_case_id', $scan->id)->update(['btc_case_id' => $c->id]);
                $scan->status = 'cancelled';
                $scan->save();
                \App\Models\BtcCaseLog::create(['btc_case_id' => $scan->id, 'action' => 'classify', 'from_status' => 'scanned', 'to_status' => 'cancelled',
                    'remark' => "Bill belongs to {$c->case_no}; moved there", 'user_id' => $user->id, 'user_name' => $user->name]);
            });
            if ($c->category !== $v['category']) {
                $c->category = $v['category'];
                $c->save();
            }
            try {
                $c = $wf->act($c->fresh(), 'finance_upload', ['bill_source' => $scan->bill_source ?: 'dms', 'remark' => trim("Scanned bill {$scan->case_no} classified as {$label}. ".($v['remark'] ?? ''))], $user);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $c->case_no.': '.$e->getMessage()], 422);
            }

            return response()->json(['success' => true, 'message' => "Bill added to {$c->case_no} ({$label}); it is on the Punch Screen.", 'id' => $c->id]);
        }
        $emp = ! empty($v['employee_id']) ? DB::connection('hrims')->table('hrm_employee as e')->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')->where('e.EmployeeID', (int) $v['employee_id'])
            ->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'g.DepartmentId', 'd.department_name']) : null;
        if (! $emp && trim((string) ($v['department_name'] ?? '')) === '') {
            return response()->json(['success' => false, 'message' => 'Choose the employee the bill is for, or type the department (or link it to its BTC request).'], 422);
        }
        if (trim((string) ($v['purpose'] ?? '')) === '') {
            return response()->json(['success' => false, 'message' => 'Choose the purpose.'], 422);
        }
        $cat = config("btc.categories.{$v['category']}");
        $scan->fill([
            'category' => $v['category'], 'purpose' => trim($v['purpose']),
            'employee_id' => $emp->EmployeeID ?? 0, 'emp_code' => $emp->EmpCode ?? null,
            'emp_name' => $emp ? trim(preg_replace('/\s+/', ' ', $emp->Fname.' '.$emp->Sname.' '.$emp->Lname)) : null,
            'department_id' => $emp->DepartmentId ?? null, 'department_name' => $emp->department_name ?? trim((string) $v['department_name']),
            'boarding_pass_required' => (bool) ($cat['boarding_pass'] ?? false), 'original_required' => (bool) ($cat['original_required'] ?? false),
        ])->save();
        \App\Models\BtcCaseLog::create(['btc_case_id' => $scan->id, 'action' => 'classify', 'from_status' => 'scanned', 'to_status' => 'scanned',
            'remark' => trim("Classified as {$label}. ".($v['remark'] ?? '')), 'user_id' => $user->id, 'user_name' => $user->name]);
        try {
            $c = $wf->act($scan->fresh(), 'finance_upload', ['bill_source' => $scan->bill_source ?: 'dms', 'remark' => 'Classified - bill read for the Punch Screen'], $user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $scan->case_no.': '.$e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "{$c->case_no} classified as {$label}; it is on the Punch Screen".($c->extract_note ? " ({$c->extract_note})" : '').'.', 'id' => $c->id]);
    }

    /** Punch Screen: front screen of classified bills by type; the case window shows the punch form of the type. */
    public function punch(Request $request)
    {
        $this->need('btc.bill_upload|btc.finance');
        $form = fn (array $cats) => fn ($q) => $q->where('status', 'bill_submitted')->whereIn('category', $cats);

        return $this->queue('queue', 'Punch Screen', 'ri-keyboard-box-line',
            'Bills to punch. Open a bill: the punch form of its type (hired vehicle, air / rail / bus, hotel, meals, team meeting) is filled from the bill reader. Check each field with the bill, choose the employee(s) and the activity, then Focus Export: the JV import rows get their DocNo and the bill goes to Finance level verification.',
            [
                'all' => ['All to punch', fn ($q) => $q->where('status', 'bill_submitted')],
                'vehicle' => ['Hired vehicle', $form(['vehicle'])],
                'air' => ['Air / Rail / Bus', $form(['air', 'train', 'bus'])],
                'hotel' => ['Hotel / Lodging', $form(['hotel'])],
                'meals' => ['Meals', $form(['meals'])],
                'meeting' => ['Team meeting / other', $form(['meeting', 'other'])],
                'returned' => ['Returned by verification', fn ($q) => $q->where('status', 'bill_submitted')->whereExists(fn ($l) => $l->from('btc_case_logs')->whereColumn('btc_case_logs.btc_case_id', 'btc_cases.id')->where('btc_case_logs.action', 'verify1_return'))],
                'sent' => ['Sent to verification', fn ($q) => $q->where('status', 'extracted')],
            ], (string) $request->input('tab', 'all'), $request, ['open' => (int) $request->input('case'), 'punchSheet' => \App\Services\Btc\BtcFocusExport::ready()]);
    }

    /** Finance Advance Approval: pending -> approved -> instructed (then Treasury releases it - BTC-ADV-TREAS-01). */
    public function advanceApproval(Request $request)
    {
        $this->need('btc.fin_approver|btc.advance|btc.audit');
        abort_unless($this->ready() && Schema::hasColumn('btc_cases', 'adv_stage'), 503, 'Run the BTC database update first.');
        $adv = fn ($q) => $q->where('status', 'advance_requested');
        $paid = fn ($q) => $q->where('advance_amount', '>', 0)->where(fn ($w) => $w->where('advance_paid_amount', '>', 0)->orWhereNotNull('advance_paid_on'));

        return $this->queue('advance-approval', 'Finance Advance Approval', 'ri-hand-coin-line',
            'Advances approved by the reporting manager wait here for Finance: approve (or reject), then give the payment instruction. Instructed advances go to Treasury Payment > Advances to release, where Treasury pays them.',
            [
                'pending' => ['Pending', fn ($q) => $adv($q)->where(fn ($w) => $w->whereNull('adv_stage')->orWhereIn('adv_stage', ['', 'pending']))],
                'approved' => ['Approved', fn ($q) => $adv($q)->where('adv_stage', 'approved')],
                'instructed' => ['Instructed', fn ($q) => $adv($q)->where('adv_stage', 'instructed')],
                // BTC-SCAN-02 (20 Nov 2026, tracker tab 5 d-f): confirmed = advance paid; unsettled = paid, not yet
                // adjusted against the bill or refunded; rejected by Finance
                'confirmed' => ['Confirmed (paid)', fn ($q) => $paid($q)],
                'unsettled' => ['Unsettled', fn ($q) => $paid($q)->whereNotIn('status', ['closed', 'cancelled'])
                    ->whereRaw('(COALESCE(advance_paid_amount, advance_amount) + COALESCE(tds_on_advance, 0) - COALESCE(advance_adjusted, 0) - COALESCE(advance_refund, 0)) > 0.5')],
                'rejected' => ['Rejected', fn ($q) => $q->where('adv_stage', 'rejected')],
            ], (string) $request->input('tab', 'pending'), $request);
    }

    /** Approve / instruct the ticked advances in one go (each through the workflow). */
    public function advanceBulk(Request $request)
    {
        $this->need('btc.fin_approver|btc.advance');
        $v = $request->validate(['action' => 'required|in:adv_fin_approve,adv_instruct', 'ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer',
            'adv_instruction_ref' => 'nullable|string|max:60', 'remark' => 'nullable|string|max:500'], ['ids.required' => 'Tick the advances.']);
        $user = auth()->user();
        $wf = app(BtcWorkflow::class);
        $ok = [];
        $bad = [];
        foreach ($this->cases()->whereIn('id', $v['ids'])->get() as $c) {
            try {
                $wf->act($c, $v['action'], ['remark' => $v['remark'] ?? '', 'adv_instruction_ref' => $v['adv_instruction_ref'] ?? null], $user);
                $ok[] = $c->case_no;
            } catch (\RuntimeException $e) {
                $bad[] = $c->case_no.': '.$e->getMessage();
            }
        }
        $back = redirect()->route('btc.advance-approval', ['tab' => $v['action'] === 'adv_fin_approve' ? 'pending' : 'approved']);
        $done = ($v['action'] === 'adv_fin_approve' ? 'Approved: ' : 'Instructed: ').implode(', ', $ok);

        return $bad ? $back->with('success', $ok ? $done : null)->withErrors(['adv' => implode(' | ', $bad)]) : $back->with('success', $done.'.');
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

        return $this->queue('queue', 'Finance Level Verification', 'ri-checkbox-multiple-line',
            'Check every punched field with the received hard copy of the bill: verify when all match, return for correction if a field differs, or reject. After verifying, prepare the Focus entry here and export it.',
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
        $free = fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0);
        $ins = Schema::hasColumn('btc_cases', 'pay_instructed_at');
        // advance paid on the case and not yet adjusted against the bill or refunded
        $advOpen = '(COALESCE(advance_paid_amount, CASE WHEN advance_paid_on IS NOT NULL THEN advance_amount ELSE 0 END) + COALESCE(tds_on_advance, 0)'
            .' - COALESCE(advance_adjusted, 0) - COALESCE(advance_refund, 0)) > 0.5 AND COALESCE(advance_paid_amount, CASE WHEN advance_paid_on IS NOT NULL THEN advance_amount ELSE 0 END) > 0';

        // BTC-SCAN-02 (20 Nov 2026, tracker tab 6): Payment approval & advance adjustment after the bill; BTC-PAY-02
        // (tab 6 d-f): approved -> payment instruction to Treasury -> confirmed (paid); unsettled = part paid or an
        // advance still to adjust / recover
        return $this->queue('queue', 'Payment Approval & Advance Adjustment', 'ri-shield-check-line',
            'After the bill is verified: check the advance paid and adjusted against the bill, the TDS and the net payable (or the amount to recover), then approve, return, reject or hold. Approved bills are paid by Treasury after the payment instruction.',
            [
                'pending' => ['Pending', fn ($q) => $q->where('status', 'focus_done')->where($free)],
                'approved' => ['Approved', fn ($q) => $q->where('status', 'fin_approved')->where($free)->when($ins, fn ($w) => $w->whereNull('pay_instructed_at'))],
                'hold' => ['Held', fn ($q) => $q->whereIn('status', ['focus_done', 'fin_approved'])->where('on_hold', 1)],
                'instructed' => ['Instructed', fn ($q) => $ins ? $q->where('status', 'fin_approved')->whereNotNull('pay_instructed_at')->where($free) : $q->whereRaw('1 = 0')],
                'confirmed' => ['Confirmed (paid)', fn ($q) => $q->whereIn('status', ['paid', 'closed'])->whereNotNull('paid_on')],
                // "Decision for You" 9: a payment Matrix did not match is unsettled too
                'unsettled' => ['Unsettled', fn ($q) => $q->whereIn('status', ['fin_approved', 'paid', 'closed'])
                    ->where(fn ($w) => $w->where(fn ($p) => $p->where('status', 'fin_approved')->where('paid_amount', '>', 0))->orWhereRaw($advOpen)
                        ->when(\App\Services\Btc\BtcMatrixVerify::ready(), fn ($m) => $m->orWhereExists(fn ($e) => $e->from('btc_payments')->whereColumn('btc_payments.btc_case_id', 'btc_cases.id')
                            ->where('btc_payments.status', 'success')->where('btc_payments.matrix_status', 'mismatch'))))],
            ], (string) $request->input('tab', 'pending'), $request, ['advCols' => true, 'payCols' => true,
                'pick' => $ins && $request->input('tab') === 'approved' && (auth()->user()->can('btc.fin_approver') || auth()->user()->can('btc.admin'))]);
    }

    /** BTC-PAY-02: payment instruction to Treasury for the ticked approved bills. */
    public function approvalInstruct(Request $request)
    {
        $this->need('btc.fin_approver');
        $v = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer', 'pay_instruction_ref' => 'nullable|string|max:60',
            'remark' => 'nullable|string|max:500'], ['ids.required' => 'Tick the bills.']);
        $user = auth()->user();
        $wf = app(BtcWorkflow::class);
        $ok = [];
        $bad = [];
        foreach ($this->cases()->whereIn('id', $v['ids'])->get() as $c) {
            try {
                $wf->act($c, 'pay_instruct', ['remark' => $v['remark'] ?? '', 'pay_instruction_ref' => $v['pay_instruction_ref'] ?? null], $user);
                $ok[] = $c->case_no;
            } catch (\RuntimeException $e) {
                $bad[] = $c->case_no.': '.$e->getMessage();
            }
        }
        $back = redirect()->route('btc.approval', ['tab' => 'approved']);
        $done = 'Payment instruction given: '.implode(', ', $ok);

        return $bad ? $back->with('success', $ok ? $done : null)->withErrors(['pay' => implode(' | ', $bad)]) : $back->with('success', $done.'.');
    }

    // ---- Treasury Payment ---------------------------------------------------------------------------------------
    public function treasury(Request $request)
    {
        $this->need('btc.treasury|btc.audit');
        $free = fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0);
        $need = BtcWorkflow::instructionRequired();
        $tabs = [
            'topay' => ['To pay', fn ($q) => $q->where('status', 'fin_approved')->where($free)->when($need, fn ($w) => $w->whereNotNull('pay_instructed_at'))],
        ];
        if ($need) {
            $tabs['waiting'] = ['Approved, instruction awaited', fn ($q) => $q->where('status', 'fin_approved')->where($free)->whereNull('pay_instructed_at')];
        }
        $tabs['hold'] = ['On hold', fn ($q) => $q->where('status', 'fin_approved')->where('on_hold', 1)];
        $tabs['paid'] = ['Paid', fn ($q) => $q->whereIn('status', ['paid', 'closed'])->whereNotNull('paid_on')];
        // BTC-ADV-TREAS-01 (24 Nov 2026, testing point 11): advances Finance approved and instructed come to Treasury,
        // which releases them (Open > Advance paid: amount, UTR, date)
        if (Schema::hasColumn('btc_cases', 'adv_stage')) {
            $tabs['advances'] = ['Advances to release', fn ($q) => $q->where('status', 'advance_requested')->where('adv_stage', 'instructed')];
            $tabs['adv_paid'] = ['Advances released', fn ($q) => $q->whereNotNull('advance_paid_on')->where('advance_paid_amount', '>', 0)];
        }
        $focus = \App\Services\Btc\BtcFocusExport::ready();
        $mx = \App\Services\Btc\BtcMatrixVerify::ready();

        return $this->queue('treasury', 'Treasury Payment', 'ri-bank-line',
            'Bills with Finance\'s payment instruction, and advances Finance has instructed. Pay in the bank, then record the payment and UTR here (bills: one or several; advances: Open > Advance paid). Export the Focus files from here; recorded payments are confirmed to Matrix.',
            $tabs, (string) $request->input('tab', 'topay'), $request, ['modes' => config('btc.payment_modes'), 'payCols' => true,
                'focus' => $focus ? [
                    'jv' => BtcCase::where('company_id', (int) session('company_id', 1))->whereIn('status', ['fin_approved', 'paid', 'closed'])->whereNotNull('finance_at')->whereNull('focus_jv_doc')
                        ->when($need, fn ($w) => $w->whereNotNull('pay_instructed_at'))->count(),
                    'ejv' => BtcCase::where('company_id', (int) session('company_id', 1))->whereNotIn('status', ['cancelled', 'rejected'])->whereNotNull('focus_jv_doc')->whereNull('focus_ejv_doc')->count(),   // BTC-JV-34
                    'bank' => DB::table('btc_payments as p')->join('btc_cases as c', 'c.id', '=', 'p.btc_case_id')->where('c.company_id', (int) session('company_id', 1))
                        ->where('p.status', 'success')->whereNull('p.focus_doc')->count(),
                ] : null,
                'matrix' => $mx ? ['on' => \App\Services\Btc\BtcMatrixVerify::enabled(), 'waiting' => \App\Services\Btc\BtcMatrixVerify::pending()->count(),
                    'counts' => DB::table('btc_payments')->where('status', 'success')->whereNotNull('matrix_status')->groupBy('matrix_status')->selectRaw('matrix_status, COUNT(*) n')->pluck('n', 'matrix_status')->all()] : null,
            ]);
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
        // BTC-MATRIX-01: confirm the new payments to Matrix after the page is answered (only when switched on)
        if ($ok && \App\Services\Btc\BtcMatrixVerify::ready() && \App\Services\Btc\BtcMatrixVerify::enabled()) {
            dispatch(fn () => app(\App\Services\Btc\BtcMatrixVerify::class)->run(null, false, 200))->afterResponse();
        }

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

    /**
     * BTC-FOCUS-01 (20 Nov 2026, tracker items 7 and 8): Focus import files from the Treasury screen.
     *  jv   vendor JV for instructed bills not yet exported; ejv employee JV; bank = payments recorded, not exported.
     * ?check=1 gives the check sheet only (no document numbers used). Each file has the import sheet in the exact
     * Focus column order and a "Check" sheet: pass / fail per bill with the reason and the values fetched.
     */
    public function focusFile(Request $request, string $kind)
    {
        // BTC-FLOW-18 (25 Nov 2026, testing point 18): the Focus import sheet of bills exported on the Punch Screen (their
        // DocNo stays; nothing new is numbered) - by bill (ids) or by export day
        $again = $kind === 'jv' && ($request->filled('ids') || $request->filled('exported_on'));
        $this->need($again ? 'btc.bill_upload|btc.finance|btc.verifier|btc.treasury|btc.fin_approver|btc.audit' : 'btc.treasury|btc.fin_approver|btc.verifier|btc.audit');
        abort_unless(\App\Services\Btc\BtcFocusExport::ready(), 503, 'Run the BTC database update first.');
        // only Treasury (or BTC admin) takes document numbers; everyone else gets the check sheet
        $assign = ! $request->boolean('check') && (auth()->user()->can('btc.treasury') || auth()->user()->can('btc.admin'));
        $x = new \App\Services\Btc\BtcFocusExport();
        $company = (int) session('company_id', 1);
        $base = BtcCase::where('company_id', $company)->orderBy('id');
        if ($again) {
            $ids = array_filter(array_map('intval', explode(',', (string) $request->input('ids'))));
            $cases = $this->cases()->whereNotNull('focus_jv_doc')->whereNotIn('status', ['cancelled', 'rejected'])
                ->when($ids, fn ($w) => $w->whereIn('id', $ids))
                ->when(! $ids, fn ($w) => $w->whereDate('focus_exported_at', $request->date('exported_on') ?? now()))->orderBy('id')->limit(500)->get();
            $out = $x->vendorJv($cases, false);
            // BTC-JV-34 (testing point 34): the employee JV rows of the same bills (made at the same Focus Export)
            $ejvOut = $x->employeeJv($cases->filter(fn ($c) => $c->focus_ejv_doc), false);
            $out['check'] = array_merge($out['check'], $ejvOut['check']);
            $assign = true;   // the rows carry their own DocNo
            $cols = \App\Services\Btc\BtcFocusExport::JV_COLS;
            $name = 'Focus_import_BTC';
        } elseif ($kind === 'jv') {
            $cases = $base->whereIn('status', ['fin_approved', 'paid', 'closed'])->whereNotNull('finance_at')->whereNull('focus_jv_doc')
                ->when(BtcWorkflow::instructionRequired(), fn ($w) => $w->whereNotNull('pay_instructed_at'))->limit(500)->get();
            $out = $x->vendorJv($cases, $assign);
            $cols = \App\Services\Btc\BtcFocusExport::JV_COLS;
            $name = 'Jv_import_BTC';
        } elseif ($kind === 'ejv') {
            // BTC-JV-34: the employee JV is made at Focus Export on the Punch Screen; here only bills exported before that
            // change (vendor JV given, no employee JV yet) - at any stage
            $cases = $base->whereNotIn('status', ['cancelled', 'rejected'])->whereNotNull('focus_jv_doc')->whereNull('focus_ejv_doc')->limit(500)->get();
            $out = $x->employeeJv($cases, $assign);
            $cols = \App\Services\Btc\BtcFocusExport::EJV_COLS;
            $name = 'Employee_jv_import_BTC';
        } else {
            $pays = DB::table('btc_payments as p')->join('btc_cases as c', 'c.id', '=', 'p.btc_case_id')->where('c.company_id', $company)
                ->where('p.status', 'success')->whereNull('p.focus_doc')->orderBy('p.id')->limit(500)->get(['p.*']);
            $out = $x->bankPayments($pays, $assign);
            $cols = \App\Services\Btc\BtcFocusExport::BANK_COLS;
            $name = 'Bank_payment_import_BTC';
        }
        $check = array_map(fn ($r) => [$r['type'], $r['case'], $r['result'], $r['problems'], $r['employee'], $r['entity'], $r['vendor'], $r['ledger'], $r['sub'], $r['dims'], $r['amount']], $out['check']);
        $dateCols = [];
        $fmts = \App\Services\Btc\BtcFocusExport::DATE_FORMATS[$kind] ?? [];
        foreach ($cols as $i => $h) {
            if (isset($fmts[$h])) {
                $dateCols[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1)] = $fmts[$h];
            }
        }
        $sheet = fn (string $title, array $head, array $rows, array $fmt = []) => new class($title, $head, $rows, $fmt) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle, \Maatwebsite\Excel\Concerns\WithColumnFormatting
        {
            public function __construct(private string $t, private array $h, private array $r, private array $f) {}

            public function array(): array
            {
                return $this->r;
            }

            public function headings(): array
            {
                return $this->h;
            }

            public function title(): string
            {
                return $this->t;
            }

            public function columnFormats(): array
            {
                return $this->f;
            }
        };
        $sheets = [];
        if ($assign) {
            $sheets[] = $sheet(['jv' => 'Sheet2', 'ejv' => 'Sheet1', 'bank' => 'Payment Export'][$kind], $cols, $out['rows'], $dateCols);
        }
        if ($again) {
            $sheets = [$sheet('Sheet2', $cols, $out['rows'], $dateCols)];
            // BTC-JV-34: the employee JV import (Sheet1, as in Finance's sample) beside the vendor JV
            $ejvDates = [];
            foreach (\App\Services\Btc\BtcFocusExport::EJV_COLS as $i => $h) {
                if (isset(\App\Services\Btc\BtcFocusExport::DATE_FORMATS['ejv'][$h])) {
                    $ejvDates[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1)] = \App\Services\Btc\BtcFocusExport::DATE_FORMATS['ejv'][$h];
                }
            }
            if (! empty($ejvOut['rows'])) {
                $sheets[] = $sheet('Sheet1', \App\Services\Btc\BtcFocusExport::EJV_COLS, $ejvOut['rows'], $ejvDates);
            }
        }
        $sheets[] = $sheet('Check', ['File', 'BTC ID', 'Result', 'What is wrong / pending', 'Employee(s)', 'Entity', 'Vendor (code)', 'Ledger', 'Sub ledger / cost center',
            'Dept / sub dept / BU / region / zone / function / vertical / location / state', 'Amount'], $check);
        $book = new class($sheets) implements \Maatwebsite\Excel\Concerns\Export, \Maatwebsite\Excel\Concerns\WithMultipleSheets
        {
            public function __construct(private array $s) {}

            public function sheets(): array
            {
                return $this->s;
            }
        };
        $pass = collect($out['check'])->where('result', 'Pass')->count();
        \Illuminate\Support\Facades\Log::info('btc focus file', ['kind' => $kind, 'assign' => $assign, 'pass' => $pass, 'fail' => count($out['check']) - $pass, 'by' => auth()->id()]);

        return \Maatwebsite\Excel\Facades\Excel::download($book, ($assign ? $name : $name.'_check').'_'.now()->format('Ymd_His').'.xlsx');
    }

    /**
     * BTC-MATRIX-01: send the recorded payments to Matrix now (or check what would be sent while it is off).
     * "Decision for You" 9: one payment (verify) or all waiting (verify-bulk); a payment already sent can be sent again.
     */
    public function matrixSend(Request $request)
    {
        $this->need('btc.treasury');
        $svc = app(\App\Services\Btc\BtcMatrixVerify::class);
        $one = (int) $request->input('payment_id', 0);
        if ($one > 0) {
            $ok = DB::table('btc_payments as p')->join('btc_cases as c', 'c.id', '=', 'p.btc_case_id')->where('p.id', $one)
                ->where('c.company_id', (int) session('company_id', 1))->where('p.status', 'success')->exists();
            abort_unless($ok, 404);
            DB::table('btc_payments')->where('id', $one)->whereIn('matrix_status', ['sent', 'mismatch', 'failed', 'no_account'])->update(['matrix_status' => null, 'matrix_tries' => 0]);
        }
        $r = $svc->run($one > 0 ? [$one] : null, ! \App\Services\Btc\BtcMatrixVerify::enabled(), 500);

        return redirect()->route('btc.treasury', ['tab' => 'paid'])->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
