<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BtcCase;
use App\Services\Btc\BtcAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-01..09 (16 Nov 2026, sheet tab "BTC requirements"): the BTC menu pages beside the case screen -
 * dashboard, request register, admin booking queue, advance processing, Nova invoice inbox and matching,
 * integration monitoring, and roles & access. Each page needs its btc.* permission (btc.admin opens all);
 * lists follow the entity / department scope of the user's BTC roles and show the latest first.
 */
class BtcPagesController extends Controller
{
    private function need(string $perms): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->can('btc.admin') || collect(explode('|', $perms))->contains(fn ($p) => $u->can($p))), 403, 'You do not have access to this BTC page.');
    }

    private function cases()
    {
        return BtcAccess::applyScope(BtcCase::query()->where('company_id', (int) session('company_id', 1)), auth()->user());
    }

    private function ready(): bool
    {
        return Schema::hasTable('btc_cases') && Schema::hasColumn('btc_cases', 'booking_status') && Schema::hasTable('btc_invoices');
    }

    private function notReady()
    {
        return view('admin.btc.pages.not-ready');
    }

    // ---- 1. Dashboard ------------------------------------------------------------------------------------------
    public function dashboard()
    {
        $this->need('btc.dashboard|btc.audit');
        if (! $this->ready()) {
            return $this->notReady();
        }
        $all = $this->cases()->whereNotIn('status', ['draft'])->get();
        $live = $all->whereNotIn('status', ['cancelled', 'rejected']);
        $value = fn ($c) => (float) ($c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount);
        $unpaid = $live->filter(fn ($c) => $c->bill_no && ! in_array($c->status, ['paid', 'closed'], true));
        $adv = $live->map(fn ($c) => $c->advanceFigures());
        $age = fn ($d) => $d ? now()->diffInDays($d) : 0;
        $bucket = fn ($days) => $days <= 15 ? '0-15 days' : ($days <= 30 ? '16-30 days' : ($days <= 60 ? '31-60 days' : 'Over 60 days'));
        $open = $live->whereNotIn('status', ['paid', 'closed']);
        $ageing = collect(['0-15 days', '16-30 days', '31-60 days', 'Over 60 days'])->mapWithKeys(fn ($b) => [$b => ['count' => 0, 'value' => 0.0]])->all();
        foreach ($open as $c) {
            $b = $bucket($age($c->created_at));
            $ageing[$b]['count']++;
            $ageing[$b]['value'] += $value($c);
        }
        // BTC-V2 (19 Nov 2026): exceptions of the revised workflow (no Nova)
        $since = fn ($c) => $age($c->updated_at);
        $exceptions = [
            ['Returned for correction', $live->where('status', 'returned')->count(), route('btc.register', ['status' => 'returned'])],
            ['Booking requested over 3 days ago', $live->where('status', 'booking_requested')->filter(fn ($c) => $age($c->created_at) > 3)->count(), route('btc.booking')],
            ['Advance paid over 30 days, no bill', $live->whereIn('status', ['advance_paid', 'booked'])->filter(fn ($c) => $c->advance_paid_on && $age($c->advance_paid_on) > 30 && ! $c->bill_no)->count(), route('btc.advances', ['show' => 'outstanding'])],
            ['Physical bill with DMS over 2 days', $live->where('status', 'bill_with_dms')->filter(fn ($c) => $since($c) > 2)->count(), route('btc.register', ['status' => 'bill_with_dms'])],
            ['Bill with Finance, not scanned over 2 days', $live->where('status', 'bill_with_finance')->filter(fn ($c) => $since($c) > 2)->count(), route('btc.scanner', ['tab' => 'waiting'])],
            ['Scanned, not classified over 1 day', $live->where('status', 'scanned')->filter(fn ($c) => $since($c) > 1)->count(), route('btc.classification')],   // BTC-SCAN-01
            ['Invoice not read (to punch by hand)', $live->where('status', 'bill_submitted')->where('extract_status', 'failed')->count(), route('btc.punch')],
            ['Advance waiting for Finance approval / instruction over 2 days', $live->where('status', 'advance_requested')->filter(fn ($c) => $since($c) > 2 && ($c->adv_stage ?? null) !== 'instructed')->count(), route('btc.advance-approval')],
            ['Paid, documents still pending', $live->where('status', 'paid')->filter(fn ($c) => $c->pendingDocuments())->count(), route('btc.register', ['status' => 'paid'])],
        ];
        $group = function ($key) use ($live, $value) {
            return $live->groupBy($key)->map(fn ($g, $k) => [
                'name' => $k ?: '-', 'count' => $g->count(), 'value' => $g->sum($value),
                'paid' => $g->sum(fn ($c) => (float) $c->paid_amount),
                'pending' => $g->filter(fn ($c) => $c->bill_no && ! in_array($c->status, ['paid', 'closed'], true))->sum(fn ($c) => (float) ($c->net_payable ?: $c->bill_amount)),
            ])->sortByDesc('value')->values();
        };

        return view('admin.btc.pages.dashboard', [
            'kpi' => [
                'total_value' => $live->sum($value), 'requests' => $live->count(),
                'invoices' => $live->filter(fn ($c) => $c->bill_no)->count(),
                'pending_amount' => $unpaid->sum(fn ($c) => (float) ($c->net_payable ?: $c->bill_amount)),
                'paid_amount' => $live->sum(fn ($c) => (float) $c->paid_amount),
                'advance_outstanding' => $adv->sum(fn ($a) => max(0, $a['balance'])),
                'advance_cases' => $adv->filter(fn ($a) => $a['balance'] > 0.5)->count(),
            ],
            'ageing' => $ageing, 'exceptions' => $exceptions,
            'byDept' => $group('department_name')->take(15), 'byVendor' => $group('vendor_name')->take(15),
            // BTC-V2: every stage of the revised workflow, in order (with its count)
            'byStatus' => collect(array_keys(config('btc.statuses')))->mapWithKeys(fn ($st) => [$st => $all->where('status', $st)->count()])
                ->filter(fn ($n, $st) => $n > 0 || ! in_array($st, ['dms_processed', 'checked', 'with_treasury', 'dept_approved'], true)),
        ]);
    }

    // ---- 4. Request register -----------------------------------------------------------------------------------
    public function register(Request $request)
    {
        $this->need('btc.register|btc.audit');
        if (! $this->ready()) {
            return $this->notReady();
        }
        $q = $this->cases();
        foreach (['status', 'route', 'category', 'department_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('case_no', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")->orWhere('emp_name', 'like', "%{$s}%")
                ->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('bill_no', 'like', "%{$s}%")->orWhere('booking_ref', 'like', "%{$s}%")->orWhere('nova_document_id', 'like', "%{$s}%"));
        }
        $rows = $q->orderByDesc('id')->paginate(50)->withQueryString();
        $depts = $this->cases()->whereNotNull('department_id')->distinct()->orderBy('department_name')->pluck('department_name', 'department_id');

        return view('admin.btc.pages.register', compact('rows', 'depts'));
    }

    public function registerExport(Request $request)
    {
        $this->need('btc.register|btc.audit');
        $this->need('btc.export');   // BTC-PERM-02 (17 Nov 2026): downloading is its own permission

        return $this->registerDownload($this->cases(), 'btc_request_register_');
    }

    private function registerDownload($query, string $prefix)
    {
        $rows = $query->orderByDesc('id')->limit(20000)->get()->map(function ($c) {
            $a = $c->advanceFigures();

            return [$c->case_no, config("btc.route_choices.{$c->route}.0", $c->route), $c->emp_code, $c->emp_name, $c->department_name, $c->categoryLabel(),
                $c->statusLabel(), $c->pendingWith(), $c->booking_ref, $c->booking_amount, $a['requested'], $a['approved'], $a['paid'], $a['tds'], $a['adjusted'], $a['refund'], $a['balance'],
                $c->bill_no, $c->nova_document_id, $c->bill_amount, $c->net_payable, $c->paid_amount, $c->utr_number, optional($c->created_at)->format('d-m-Y'), optional($c->updated_at)->format('d-m-Y')];
        })->all();
        $head = ['Request ID', 'Origin route', 'Emp code', 'Owner', 'Department', 'Category', 'Status', 'Pending with', 'Booking ref', 'Booking amount',
            'Advance requested', 'Advance approved', 'Advance paid', 'TDS on advance', 'Adjusted against invoice', 'Refund received', 'Advance balance',
            'Bill no.', 'Nova document', 'Bill amount', 'Net payable', 'Paid', 'UTR', 'Created', 'Updated'];

        return \Maatwebsite\Excel\Facades\Excel::download(new class($rows, $head) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows, private array $head) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->head;
            }
        }, $prefix.now()->format('Ymd_His').'.xlsx');
    }

    // ---- BTC-EXC-01 (17 Nov 2026): Validation & Exception Queue (requirement item 12) ---------------------------------
    private function exceptionRows(Request $request)
    {
        $rows = \App\Services\Btc\BtcExceptions::queue($this->cases());
        if ($t = $request->input('type')) {
            $rows = $rows->where('type', $t);
        }
        if ($request->input('show', 'open') === 'open') {
            $rows = $rows->filter(fn ($r) => ! $r['review']);
        }

        return $rows->values();
    }

    public function exceptions(Request $request)
    {
        $this->need('btc.exceptions|btc.audit|btc.finance|btc.checker|btc.bill_upload|btc.verifier|btc.fin_approver');
        if (! $this->ready()) {
            return $this->notReady();
        }
        $all = \App\Services\Btc\BtcExceptions::queue($this->cases());
        $counts = collect(\App\Services\Btc\BtcExceptions::TYPES)->map(fn ($l, $k) => ['label' => $l,
            'open' => $all->where('type', $k)->filter(fn ($r) => ! $r['review'])->count(), 'reviewed' => $all->where('type', $k)->filter(fn ($r) => (bool) $r['review'])->count()]);
        $u = auth()->user();

        return view('admin.btc.pages.exceptions', ['rows' => $this->exceptionRows($request), 'counts' => $counts,
            'canReview' => $u->can('btc.admin') || $u->can('btc.finance') || $u->can('btc.checker'), 'canExport' => $u->can('btc.admin') || $u->can('btc.export')]);
    }

    public function exceptionsExport(Request $request)
    {
        $this->need('btc.exceptions|btc.audit|btc.finance|btc.checker|btc.bill_upload|btc.verifier|btc.fin_approver');
        $this->need('btc.export');
        $rows = $this->exceptionRows($request)->map(fn ($r) => [$r['case']->case_no, $r['case']->emp_code.' - '.$r['case']->emp_name, $r['case']->vendor_name,
            $r['case']->statusLabel(), $r['label'], $r['detail'], $r['review'] ? 'Reviewed: '.$r['review']->remark.' ('.$r['review']->reviewer_name.', '.substr((string) $r['review']->created_at, 0, 10).')' : 'Open'])->all();
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Request ID', 'Employee', 'Vendor', 'Stage', 'Exception', 'Detail', 'Review'];
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'btc_exceptions_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exceptionReview(Request $request)
    {
        $u = auth()->user();
        abort_unless($u->can('btc.admin') || $u->can('btc.finance') || $u->can('btc.checker'), 403);
        $v = $request->validate(['case_id' => 'required|integer', 'type' => 'required|in:'.implode(',', array_keys(\App\Services\Btc\BtcExceptions::TYPES)),
            'remark' => 'required|string|min:5|max:500']);
        $c = $this->cases()->findOrFail($v['case_id']);
        DB::table('btc_exception_reviews')->updateOrInsert(['btc_case_id' => $c->id, 'type' => $v['type']],
            ['remark' => $v['remark'], 'reviewed_by' => $u->id, 'reviewer_name' => $u->name, 'created_at' => now()]);
        \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'exception_reviewed', 'from_status' => $c->status, 'to_status' => $c->status,
            'remark' => \App\Services\Btc\BtcExceptions::TYPES[$v['type']].': '.$v['remark'], 'user_id' => $u->id, 'user_name' => $u->name]);

        return back()->with('success', $c->case_no.': exception marked reviewed.');
    }

    // ---- BTC-CONF-01 (17 Nov 2026): BTC Settings (requirement items 4/5 "Configure") -----------------------------------
    public function settings()
    {
        $this->need('btc.configure');
        $fields = \App\Support\BtcSettings::fields();
        $values = collect($fields)->map(fn ($f, $k) => config('btc.'.$k));
        $logs = \Illuminate\Support\Facades\Schema::hasTable('btc_setting_logs') ? DB::table('btc_setting_logs')->orderByDesc('id')->limit(50)->get() : collect();

        return view('admin.btc.pages.settings', ['fields' => $fields, 'values' => $values, 'logs' => $logs,
            'natures' => collect(config('btc.tds_natures'))->map(fn ($n) => $n['label'])]);
    }

    /** BTC-VENDOR-CORE-01 (20 Nov 2026): copy the vendor list from the core application now. */
    public function vendorsSync()
    {
        $this->need('btc.configure');
        $r = \App\Services\Btc\CoreVendors::sync();

        return back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    /** BTC-FOCUS-02: the Focus ledger codes as Excel (also the template to edit and upload). */
    public function focusLedgersDownload()
    {
        $this->need('btc.configure|btc.treasury|btc.verifier|btc.fin_approver');
        $book = \App\Services\Btc\FocusLedgers::workbook();
        $name = 'BTC_Focus_Ledgers_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function focusLedgersUpload(Request $request)
    {
        $this->need('btc.configure');
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);
        $u = auth()->user();
        $r = \App\Services\Btc\FocusLedgers::import($request->file('file'), $u->id ?? null, (string) ($u->name ?? ''));

        return back()->with($r['ok'] ? 'success' : 'error', $r['message'])->with('focus_errors', $r['errors'] ?? []);
    }

    public function settingsSave(Request $request)
    {
        $this->need('btc.configure');
        if (! \Illuminate\Support\Facades\Schema::hasTable('btc_settings')) {
            return back()->with('error', 'Run the database change first (BTC controls).');
        }
        $changed = \App\Support\BtcSettings::save($request->all(), auth()->user());

        return back()->with('success', $changed ? 'Saved: '.implode(', ', $changed) : 'Nothing changed.');
    }

    // ---- BTC-TEAM-01 (17 Nov 2026): My Team BTC -------------------------------------------------------------------
    // A reporting manager sees the BTC requests of the people below him (direct reports, or all levels below) as a
    // register with stage and who it is pending with, plus a summary by team member and by stage. Read-only: the
    // manager opens a request to see it; approvals stay where they are (My approvals). Admin may look at any manager.

    /** [managerId, scope, team employee ids, direct report ids, is admin] for the current user, or abort 403. */
    private function teamScope(Request $request): array
    {
        $u = auth()->user();
        $isAdmin = $u->can('btc.admin');
        $mgr = $isAdmin && $request->filled('mgr') ? (int) $request->input('mgr') : (int) ($u->employee_id ?? 0);
        $direct = \App\Support\ReportingTree::directReports($mgr);
        abort_unless($mgr > 0 && ($direct || $isAdmin), 403, 'Nobody reports to you, so there is no team BTC view.');
        $scope = $request->input('scope') === 'all' ? 'all' : 'direct';
        $ids = $scope === 'all' ? \App\Support\ReportingTree::descendants($mgr) : $direct;

        return [$mgr, $scope, $ids ?: [0], $direct, $isAdmin];
    }

    private function teamCases(array $ids)
    {
        return BtcCase::query()->where('company_id', (int) session('company_id', 1))->whereIn('employee_id', $ids)->where('status', '!=', 'draft');
    }

    public static function hasTeam(): bool
    {
        $id = (int) (auth()->user()->employee_id ?? 0);

        return $id > 0 && (bool) \App\Support\ReportingTree::directReports($id);
    }

    /**
     * BTC-CARD-01 (17 Nov 2026): figures for the BTC flash card on the employee Dashboard - my own requests, what waits
     * for my approval, and (for a reporting manager) the team. Null when there is nothing to show or BTC is not set up.
     */
    public static function card(): ?array
    {
        try {
            $u = auth()->user();
            $emp = (int) ($u->employee_id ?? 0);
            if ($emp <= 0 || ! Schema::hasTable('btc_cases')) {
                return null;
            }
            $co = (int) session('company_id', 1);
            $done = ['paid', 'closed', 'cancelled', 'rejected', 'draft'];
            $value = fn ($c) => (float) ($c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount);
            $mine = BtcCase::where('company_id', $co)->where(fn ($w) => $w->where('employee_id', $emp)->orWhere('created_by', $u->id))->where('status', '!=', 'draft')->get();
            $myOpen = $mine->whereNotIn('status', $done);
            $approve = BtcCase::where('company_id', $co)->where('status', 'pending_manager')->where('manager_id', $emp)->count();   // BTC-V2: no HOD step
            $team = null;
            if ($direct = \App\Support\ReportingTree::directReports($emp)) {
                $all = BtcCase::where('company_id', $co)->whereIn('employee_id', \App\Support\ReportingTree::descendants($emp))->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->get();
                $open = $all->whereNotIn('status', ['paid', 'closed']);
                $team = ['requests' => $all->count(), 'open' => $open->count(), 'value' => $open->sum($value),
                    'returned' => $all->where('status', 'returned')->count(),
                    'billed' => $all->filter(fn ($c) => $c->bill_no && ! in_array($c->status, ['paid', 'closed'], true))->sum(fn ($c) => (float) ($c->net_payable ?: $c->bill_amount)),
                    'people' => $all->pluck('employee_id')->unique()->count()];
            }
            if (! $mine->count() && ! $approve && ! ($team['requests'] ?? 0)) {
                return null;
            }
            $next = $myOpen->sortByDesc('id')->first();

            return ['my_open' => $myOpen->count(), 'my_value' => $myOpen->sum($value), 'my_total' => $mine->count(),
                'returned' => $mine->where('status', 'returned')->count(),
                'adv_balance' => $mine->whereNotIn('status', ['cancelled', 'rejected'])->sum(fn ($c) => max(0, $c->advanceFigures()['balance'])),
                'approve' => $approve, 'team' => $team,
                'latest' => $next ? ['no' => $next->case_no, 'id' => $next->id, 'stage' => $next->statusLabel(), 'with' => $next->pendingWith()] : null];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function team(Request $request)
    {
        if (! $this->ready()) {
            return $this->notReady();
        }
        [$mgr, $scope, $ids, $direct, $isAdmin] = $this->teamScope($request);
        $value = fn ($c) => (float) ($c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount);
        $all = $this->teamCases($ids)->get();
        $live = $all->whereNotIn('status', ['cancelled', 'rejected']);
        $open = $live->whereNotIn('status', ['paid', 'closed']);
        $billed = fn ($c) => $c->bill_no && ! in_array($c->status, ['paid', 'closed'], true);

        // whose branch a person sits in (the direct report above him), for the all-levels view
        $directSet = array_flip($direct);
        $branch = function (int $emp) use ($directSet, $mgr) {
            if (isset($directSet[$emp])) {
                return $emp;
            }
            foreach (\App\Support\ReportingTree::ancestors($emp) as $a) {
                if (isset($directSet[$a])) {
                    return $a;
                }
                if ($a === $mgr) {
                    break;
                }
            }

            return $emp;
        };
        $names = DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', array_merge($ids, $direct, [$mgr]))
            ->selectRaw("EmployeeID, CONCAT(EmpCode, ' - ', Fname, ' ', Lname) AS n")->pluck('n', 'EmployeeID');

        $summary = $all->groupBy(fn ($c) => $scope === 'all' ? $branch((int) $c->employee_id) : (int) $c->employee_id)
            ->map(function ($g, $k) use ($names, $value, $billed, $scope, $directSet) {
                $lv = $g->whereNotIn('status', ['cancelled', 'rejected']);

                return ['id' => $k, 'name' => $names[$k] ?? ('Employee '.$k), 'people' => $g->pluck('employee_id')->unique()->count(),
                    'requests' => $lv->count(), 'open' => $lv->whereNotIn('status', ['paid', 'closed'])->count(),
                    'with_manager' => $lv->where('status', 'pending_manager')->count(),
                    'returned' => $lv->where('status', 'returned')->count(),
                    'value' => $lv->sum($value), 'paid' => $lv->sum(fn ($c) => (float) $c->paid_amount),
                    'pending' => $lv->filter($billed)->sum(fn ($c) => (float) ($c->net_payable ?: $c->bill_amount)),
                    'adv_balance' => $lv->sum(fn ($c) => max(0, $c->advanceFigures()['balance'])),
                    'closed_out' => $g->whereIn('status', ['cancelled', 'rejected'])->count()];
            })->sortByDesc('value')->values();

        $stages = $all->groupBy('status')->map(fn ($g, $k) => ['key' => $k, 'label' => config("btc.statuses.{$k}", $k), 'count' => $g->count(),
            'value' => $g->sum($value), 'with' => $g->first()->pendingWith()])->sortBy(fn ($x) => array_search($x['key'], array_keys(config('btc.statuses'))))->values();

        $kpi = ['requests' => $live->count(), 'value' => $live->sum($value), 'open' => $open->count(),
            'with_manager' => $live->where('status', 'pending_manager')->count(),
            'pending' => $live->filter($billed)->sum(fn ($c) => (float) ($c->net_payable ?: $c->bill_amount)),
            'paid' => $live->sum(fn ($c) => (float) $c->paid_amount),
            'adv_balance' => $live->sum(fn ($c) => max(0, $c->advanceFigures()['balance'])),
            'old' => $open->filter(fn ($c) => $c->created_at && now()->diffInDays($c->created_at) > 30)->count()];

        $q = $this->teamCases($ids);
        foreach (['status', 'route', 'category'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('member')) {
            $m = (int) $request->input('member');
            $q->whereIn('employee_id', $scope === 'all' ? array_values(array_filter($ids, fn ($e) => $branch((int) $e) === $m)) ?: [0] : [$m]);
        }
        if ($request->input('show') === 'open') {
            $q->whereNotIn('status', ['paid', 'closed', 'cancelled', 'rejected']);
        }
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('case_no', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")->orWhere('emp_name', 'like', "%{$s}%")
                ->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('bill_no', 'like', "%{$s}%")->orWhere('booking_ref', 'like', "%{$s}%"));
        }
        $rows = $q->orderByDesc('id')->paginate(50)->withQueryString();
        $via = fn ($c) => $scope === 'all' && ! isset($directSet[(int) $c->employee_id]) ? ($names[$branch((int) $c->employee_id)] ?? '') : '';

        return view('admin.btc.pages.team', compact('rows', 'summary', 'stages', 'kpi', 'scope', 'mgr', 'isAdmin', 'via') + [
            'mgrName' => $names[$mgr] ?? '', 'teamSize' => count(array_filter($ids)), 'withCases' => $all->pluck('employee_id')->unique()->count(),
        ]);
    }

    public function teamExport(Request $request)
    {
        [$mgr, $scope, $ids] = $this->teamScope($request);

        return $this->registerDownload($this->teamCases($ids), 'btc_team_requests_');
    }

    // ---- 5. Admin booking queue --------------------------------------------------------------------------------
    public function booking(Request $request)
    {
        $this->need('btc.booking');
        if (! $this->ready()) {
            return $this->notReady();
        }
        // BTC-V2 (19 Nov 2026): also the bills the Admin handed over to DMS (physical bill handover status)
        $show = in_array($request->input('show'), ['to_book', 'booked', 'completed', 'to_dms', 'cancelled'], true) ? $request->input('show') : 'to_book';
        $base = fn () => $this->cases()->where('route', 'admin_booking');
        $counts = [
            'to_book' => $base()->where('status', 'booking_requested')->count(),
            'booked' => $base()->where('status', 'booked')->whereNull('activity_completed_on')->count(),
            'completed' => $base()->where('status', 'booked')->whereNotNull('activity_completed_on')->count(),
            'to_dms' => $base()->whereIn('status', ['bill_with_dms', 'bill_with_finance'])->count(),
            'cancelled' => $base()->where('booking_status', 'cancelled')->count(),
        ];
        $q = $base();
        match ($show) {
            'to_book' => $q->where('status', 'booking_requested'),
            'booked' => $q->where('status', 'booked')->whereNull('activity_completed_on'),
            'completed' => $q->where('status', 'booked')->whereNotNull('activity_completed_on'),
            'to_dms' => $q->whereIn('status', ['bill_with_dms', 'bill_with_finance']),
            'cancelled' => $q->where('booking_status', 'cancelled'),
        };
        $rows = $q->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.btc.pages.booking', compact('rows', 'show', 'counts'));
    }

    // ---- 6. Advance request & processing -----------------------------------------------------------------------
    public function advances(Request $request)
    {
        $this->need('btc.advance|btc.finance');
        if (! $this->ready()) {
            return $this->notReady();
        }
        $show = in_array($request->input('show'), ['to_pay', 'outstanding', 'settled', 'all'], true) ? $request->input('show') : 'to_pay';
        $all = $this->cases()->where('advance_amount', '>', 0)->whereNotIn('status', ['draft', 'rejected'])->orderByDesc('id')->get()
            ->map(fn ($c) => [$c, $c->advanceFigures()]);
        $sets = [
            'to_pay' => $all->filter(fn ($x) => $x[0]->status === 'advance_requested'),
            'outstanding' => $all->filter(fn ($x) => $x[1]['balance'] > 0.5),
            'settled' => $all->filter(fn ($x) => $x[1]['paid'] > 0 && $x[1]['balance'] <= 0.5),
            'all' => $all,
        ];
        $rows = $sets[$show]->values();
        $counts = array_map(fn ($s) => $s->count(), $sets);
        $tot = ['requested', 'approved', 'paid', 'tds', 'outstanding', 'adjusted', 'refund', 'balance'];
        $totals = array_combine($tot, array_map(fn ($k) => $rows->sum(fn ($x) => (float) $x[1][$k]), $tot));

        return view('admin.btc.pages.advances', compact('rows', 'show', 'counts', 'totals'));
    }

    // ---- Roles & access ---------------------------------------------------------------------------------------
    public function access(Request $request)
    {
        $this->need('btc.access');
        if (! Schema::hasTable('btc_role_settings')) {
            return $this->notReady();
        }
        $btcPerms = array_keys(array_merge(...array_values(config('btc.permission_catalog'))));
        $roles = DB::table('roles as r')->leftJoin('btc_role_settings as s', 's.role_id', '=', 'r.id')
            ->where('r.guard_name', 'web')
            ->where(fn ($w) => $w->where('r.name', 'like', 'BTC %')->orWhereNotNull('s.id'))
            ->orderBy('r.name')->get(['r.id', 'r.name', 's.description', 's.company_ids', 's.department_ids', 's.approval_limit', 's.is_active', 's.saved_permissions']);
        $permByRole = DB::table('role_has_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereIn('rp.role_id', $roles->pluck('id')->all() ?: [0])->whereIn('p.name', $btcPerms)->get(['rp.role_id', 'p.name'])->groupBy('role_id')
            ->map(fn ($g) => $g->pluck('name')->all());
        $userCount = DB::table('model_has_roles')->whereIn('role_id', $roles->pluck('id')->all() ?: [0])->selectRaw('role_id, COUNT(*) n')->groupBy('role_id')->pluck('n', 'role_id');
        $departments = DB::connection('hrims')->table('core_departments')->orderBy('department_name')->pluck('department_name', 'id');

        $users = collect();
        if ($s = trim((string) $request->input('user'))) {
            // BTC-REQ-17 (17 Nov 2026): employee code or name (not the internal employee id)
            $byCode = DB::connection('hrims')->table('hrm_employee')->where(fn ($w) => $w->where('EmpCode', $s)
                ->orWhereRaw("CONCAT_WS(' ', Fname, Sname, Lname) LIKE ?", ["%{$s}%"]))->limit(200)->pluck('EmployeeID')->all();
            $users = \App\Models\User::where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhereIn('employee_id', $byCode ?: [0]))->orderBy('name')->limit(30)->get();
        }
        $codes = $users->isEmpty() ? collect() : DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $users->pluck('employee_id')->filter()->all() ?: [0])->pluck('EmpCode', 'EmployeeID');
        $userRoles = $users->isEmpty() ? collect() : DB::table('model_has_roles')->whereIn('model_id', $users->pluck('id')->all())
            ->where('model_type', \App\Models\User::class)->whereIn('role_id', $roles->pluck('id')->all() ?: [0])->get()->groupBy('model_id')->map(fn ($g) => $g->pluck('role_id')->all());

        $history = DB::table('btc_access_logs')->orderByDesc('id')->limit(200)->get();
        $activityUser = $request->input('activity_user');
        $activity = $activityUser ? DB::table('btc_case_logs as l')->leftJoin('btc_cases as c', 'c.id', '=', 'l.btc_case_id')
            ->where('l.user_id', (int) $activityUser)->orderByDesc('l.id')->limit(200)->get(['l.created_at', 'l.action', 'l.to_status', 'l.remark', 'c.case_no']) : collect();

        return view('admin.btc.pages.access', [
            'roles' => $roles, 'permByRole' => $permByRole, 'userCount' => $userCount, 'catalog' => config('btc.permission_catalog'),
            'departments' => $departments, 'users' => $users, 'codes' => $codes, 'userRoles' => $userRoles, 'history' => $history, 'activity' => $activity,
            'activityUser' => $activityUser, 'tab' => $request->input('tab', 'roles'),
            'companies' => [1 => 'VNR Seeds', 11 => 'VVNR', 3 => 'Nursery'],
        ]);
    }

    public function accessSaveRole(Request $request)
    {
        $this->need('btc.access');
        $btcPerms = array_keys(array_merge(...array_values(config('btc.permission_catalog'))));
        $v = $request->validate([
            'role_id' => 'nullable|integer',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:300',
            'permissions' => 'array', 'permissions.*' => 'in:'.implode(',', $btcPerms),
            'companies' => 'array', 'companies.*' => 'integer',
            'departments' => 'array', 'departments.*' => 'integer',
            'approval_limit' => 'nullable|numeric|min:0|max:9999999999.99',
        ]);
        $name = trim($v['name']);
        if (! str_starts_with($name, 'BTC ')) {
            $name = 'BTC '.$name;   // BTC roles are kept apart from the other Xeasy roles
        }
        $roleId = (int) ($v['role_id'] ?? 0);
        if (DB::table('roles')->where('name', $name)->where('guard_name', 'web')->where('id', '!=', $roleId)->exists()) {
            return back()->with('error', "A role named {$name} already exists.")->withInput();
        }
        $perms = array_values($v['permissions'] ?? []);
        $before = null;
        DB::transaction(function () use (&$roleId, $name, $v, $perms, $btcPerms, &$before) {
            if ($roleId) {
                $role = DB::table('roles')->where('id', $roleId)->first();
                abort_unless($role, 404);
                $before = ['name' => $role->name, 'permissions' => DB::table('role_has_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->where('rp.role_id', $roleId)->whereIn('p.name', $btcPerms)->pluck('p.name')->all(),
                    'settings' => DB::table('btc_role_settings')->where('role_id', $roleId)->first()];
                DB::table('roles')->where('id', $roleId)->update(['name' => $name, 'updated_at' => now()]);
            } else {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('roles', 'status')) {
                    $row['status'] = 1;
                }
                $roleId = (int) DB::table('roles')->insertGetId($row);
            }
            $active = (bool) (DB::table('btc_role_settings')->where('role_id', $roleId)->value('is_active') ?? true);
            $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $perms ?: ['-'])->pluck('id', 'name');
            $btcIds = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $btcPerms)->pluck('id')->all();
            if ($active) {
                DB::table('role_has_permissions')->where('role_id', $roleId)->whereIn('permission_id', $btcIds ?: [0])->delete();
                foreach ($ids as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $pid]);
                }
            }
            DB::table('btc_role_settings')->updateOrInsert(['role_id' => $roleId], [
                'description' => $v['description'] ?? null,
                'company_ids' => implode(',', array_map('intval', $v['companies'] ?? [])) ?: null,
                'department_ids' => implode(',', array_map('intval', $v['departments'] ?? [])) ?: null,
                'approval_limit' => isset($v['approval_limit']) && $v['approval_limit'] !== '' ? $v['approval_limit'] : null,
                'saved_permissions' => $active ? null : implode(',', $perms),
                'updated_at' => now(), 'created_at' => now(),
            ]);
        });
        $this->flushPerms();
        BtcAccess::log($before ? 'role_updated' : 'role_created', ['role_id' => $roleId, 'role_name' => $name, 'details' => [
            'permissions' => $perms, 'companies' => $v['companies'] ?? [], 'departments' => $v['departments'] ?? [], 'approval_limit' => $v['approval_limit'] ?? null,
            'before' => $before ? ['name' => $before['name'], 'permissions' => $before['permissions'], 'approval_limit' => $before['settings']->approval_limit ?? null] : null]]);

        return redirect()->route('btc.access')->with('success', "{$name} saved.");
    }

    public function accessToggleRole(int $roleId)
    {
        $this->need('btc.access');
        $role = DB::table('roles')->where('id', $roleId)->first();
        abort_unless($role, 404);
        $set = DB::table('btc_role_settings')->where('role_id', $roleId)->first();
        $btcPerms = array_keys(array_merge(...array_values(config('btc.permission_catalog'))));
        $btcIds = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $btcPerms)->pluck('id', 'name');
        $nowActive = ! ($set->is_active ?? true);
        DB::transaction(function () use ($roleId, $set, $btcIds, $nowActive) {
            if ($nowActive) {
                foreach (array_filter(explode(',', (string) ($set->saved_permissions ?? ''))) as $p) {
                    if (isset($btcIds[$p])) {
                        DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $btcIds[$p]]);
                    }
                }
                $saved = null;
            } else {
                $saved = DB::table('role_has_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->where('rp.role_id', $roleId)->whereIn('p.id', $btcIds->values()->all() ?: [0])->pluck('p.name')->implode(',');
                DB::table('role_has_permissions')->where('role_id', $roleId)->whereIn('permission_id', $btcIds->values()->all() ?: [0])->delete();
            }
            DB::table('btc_role_settings')->updateOrInsert(['role_id' => $roleId], ['is_active' => $nowActive, 'saved_permissions' => $saved, 'updated_at' => now()]);
        });
        $this->flushPerms();
        BtcAccess::log($nowActive ? 'role_activated' : 'role_deactivated', ['role_id' => $roleId, 'role_name' => $role->name]);

        return back()->with('success', $role->name.($nowActive ? ' activated.' : ' deactivated: its users keep the role but get none of its BTC permissions.'));
    }

    public function accessAssign(Request $request)
    {
        $this->need('btc.access');
        $v = $request->validate(['user_id' => 'required|integer', 'role_id' => 'required|integer', 'op' => 'required|in:assign,revoke']);
        $user = \App\Models\User::findOrFail($v['user_id']);
        $role = DB::table('roles')->where('id', $v['role_id'])->first();
        abort_unless($role && (str_starts_with($role->name, 'BTC ') || DB::table('btc_role_settings')->where('role_id', $role->id)->exists()), 404);
        if ($v['op'] === 'assign') {
            DB::table('model_has_roles')->insertOrIgnore(['role_id' => $role->id, 'model_type' => \App\Models\User::class, 'model_id' => $user->id]);
        } else {
            DB::table('model_has_roles')->where(['role_id' => $role->id, 'model_type' => \App\Models\User::class, 'model_id' => $user->id])->delete();
        }
        $this->flushPerms();
        BtcAccess::log($v['op'] === 'assign' ? 'user_assigned' : 'user_revoked', ['role_id' => $role->id, 'role_name' => $role->name,
            'target_user_id' => $user->id, 'target_user' => trim($user->name.' (code '.(DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $user->employee_id)->value('EmpCode') ?? '-').')')]);

        return back()->with('success', ($v['op'] === 'assign' ? 'Assigned ' : 'Removed ').$role->name.($v['op'] === 'assign' ? ' to ' : ' from ').$user->name.'.');
    }

    private function flushPerms(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
        BtcAccess::flush();
    }
}
