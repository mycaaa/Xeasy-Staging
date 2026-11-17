<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BtcCase;
use App\Services\Btc\BtcAccess;
use App\Services\Btc\NovaInbox;
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
        $this->need('btc.dashboard');
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
        $companyId = (int) session('company_id', 1);
        $counts = NovaInbox::counts($companyId);
        $exceptions = [
            ['Returned for correction', $live->where('status', 'returned')->count(), route('btc.register', ['status' => 'returned'])],
            ['Booking requested over 3 days ago', $live->where('status', 'booking_requested')->filter(fn ($c) => $age($c->created_at) > 3)->count(), route('btc.booking')],
            ['Advance paid over 30 days, no bill', $live->whereIn('status', ['advance_paid', 'booked'])->filter(fn ($c) => $c->advance_paid_on && $age($c->advance_paid_on) > 30 && ! $c->bill_no)->count(), route('btc.advances', ['show' => 'outstanding'])],
            ['Paid, documents still pending', $live->where('status', 'paid')->filter(fn ($c) => $c->pendingDocuments())->count(), route('btc.register', ['status' => 'paid'])],
            ['Invoices not imported', $counts['import_failed'], route('btc.invoices', ['state' => 'import_failed'])],
            ['Invoices not matched to a request', $counts['unmatched'], route('btc.matching')],
            ['Invoice not received by Nova', $counts['not_at_nova'], route('btc.invoices', ['state' => 'not_at_nova'])],
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
            'byStatus' => $live->groupBy('status')->map->count()->sortDesc(),
        ]);
    }

    // ---- 4. Request register -----------------------------------------------------------------------------------
    public function register(Request $request)
    {
        $this->need('btc.register');
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
        $this->need('btc.register');

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
        $show = in_array($request->input('show'), ['to_book', 'booked', 'completed', 'cancelled'], true) ? $request->input('show') : 'to_book';
        $base = fn () => $this->cases()->where('route', 'admin_booking');
        $counts = [
            'to_book' => $base()->where('status', 'booking_requested')->count(),
            'booked' => $base()->where('status', 'booked')->whereNull('activity_completed_on')->count(),
            'completed' => $base()->whereNotNull('activity_completed_on')->whereNotIn('status', ['cancelled'])->count(),
            'cancelled' => $base()->where('booking_status', 'cancelled')->count(),
        ];
        $q = $base();
        match ($show) {
            'to_book' => $q->where('status', 'booking_requested'),
            'booked' => $q->where('status', 'booked')->whereNull('activity_completed_on'),
            'completed' => $q->whereNotNull('activity_completed_on')->whereNotIn('status', ['cancelled']),
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

    // ---- 7 / 8. Nova invoice inbox and request / invoice matching ------------------------------------------------
    public function invoices(Request $request, bool $matching = false)
    {
        $this->need($matching ? 'btc.invoices|btc.verifier' : 'btc.invoices');
        if (! $this->ready()) {
            return $this->notReady();
        }
        $co = (int) session('company_id', 1);
        $counts = NovaInbox::counts($co);
        $state = $matching ? 'unmatched' : (array_key_exists((string) $request->input('state'), NovaInbox::STATES) ? $request->input('state') : 'unmatched');
        $cases = collect();
        $rows = collect();
        if ($state === 'not_at_nova') {
            $cases = NovaInbox::notAtNova($co);
        } else {
            $q = DB::table('btc_invoices')->where('company_id', $co);
            match ($state) {
                'received' => $q->where('nova_status', 'received'),
                'digitised' => $q->where('nova_status', 'digitised'),
                'import_failed' => $q->where('nova_status', 'transferred')->where('import_status', 'failed'),
                'unmatched' => $q->where('nova_status', 'transferred')->where('import_status', 'imported')->where('match_status', '!=', 'matched'),
                'linked' => $q->where('match_status', 'matched'),
            };
            if ($s = trim((string) $request->input('q'))) {
                $q->where(fn ($w) => $w->where('nova_document_id', 'like', "%{$s}%")->orWhere('invoice_no', 'like', "%{$s}%")
                    ->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('vendor_gstin', 'like', "%{$s}%"));
            }
            $rows = $q->orderByDesc('received_on')->orderByDesc('id')->limit(500)->get();
        }
        $caseNos = BtcCase::whereIn('id', $rows->pluck('btc_case_id')->merge($rows->pluck('suggested_case_id'))->filter()->unique()->all() ?: [0])->pluck('case_no', 'id');
        $suggestions = [];
        if ($state === 'unmatched') {
            $inbox = app(NovaInbox::class);
            foreach ($rows->take(100) as $r) {
                $suggestions[$r->id] = collect($inbox->suggest($r, 3))->map(fn ($x) => ['id' => $x['case']->id, 'case_no' => $x['case']->case_no,
                    'label' => $x['case']->case_no.' · '.$x['case']->emp_name.' · '.$x['case']->vendor_name.' · Rs '.number_format((float) ($x['case']->bill_amount ?: $x['case']->booking_amount ?: $x['case']->quotation_amount ?: $x['case']->estimated_amount)),
                    'score' => $x['score'], 'why' => $x['why']])->all();
            }
        }

        return view('admin.btc.pages.invoices', compact('counts', 'state', 'rows', 'cases', 'caseNos', 'suggestions', 'matching'));
    }

    public function matching(Request $request)
    {
        return $this->invoices($request, true);
    }

    public function invoiceImport(Request $request)
    {
        $this->need('btc.invoices');
        $request->validate(['file' => 'required|file|max:10240|mimes:csv,txt'], ['file.required' => 'Choose the CSV export from Nova.']);
        $h = fopen($request->file('file')->getRealPath(), 'r');
        $head = null;
        $records = [];
        while (($line = fgetcsv($h)) !== false) {
            if ($head === null) {
                $head = array_map(fn ($x) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $x)), $line);
                continue;
            }
            if (count(array_filter($line, fn ($x) => trim((string) $x) !== '')) === 0) {
                continue;
            }
            $records[] = array_combine($head, array_pad(array_slice($line, 0, count($head)), count($head), ''));
        }
        fclose($h);
        if (! $records) {
            return back()->with('error', 'The file has no rows under its header line.');
        }
        $r = app(NovaInbox::class)->ingest($records, 'csv_import', null, (int) session('company_id', 1));

        return back()->with('success', "Imported {$r['imported']}, updated {$r['updated']}, duplicates {$r['duplicates']}, failed {$r['failed']} of {$r['received']}.");
    }

    public function invoiceLink(Request $request, int $id)
    {
        $this->need('btc.invoices|btc.verifier');
        $v = $request->validate(['case_id' => 'nullable|integer', 'case_no' => 'nullable|string|max:40']);
        $case = ! empty($v['case_id']) ? BtcCase::find($v['case_id']) : (! empty($v['case_no']) ? BtcCase::where('case_no', trim($v['case_no']))->first() : null);
        if (! $case || (int) $case->company_id !== (int) session('company_id', 1)) {
            return back()->with('error', 'Request not found in this company.');
        }
        $inv = DB::table('btc_invoices')->where('id', $id)->first();
        abort_unless($inv, 404);
        if (DB::table('btc_invoices')->where('btc_case_id', $case->id)->where('match_status', 'matched')->where('id', '!=', $id)->exists()) {
            return back()->with('error', "{$case->case_no} already has a Nova invoice linked. Unlink it first if this one replaces it.");
        }
        $score = collect(app(NovaInbox::class)->suggest($inv, 10))->firstWhere('case.id', $case->id)['score'] ?? null;
        app(NovaInbox::class)->link($id, $case->id, auth()->user(), $score, 'Matched by '.(auth()->user()->name ?? 'user'));

        return back()->with('success', "Invoice {$inv->invoice_no} linked to {$case->case_no}.");
    }

    public function invoiceUnlink(Request $request, int $id)
    {
        $this->need('btc.invoices|btc.verifier');
        $v = $request->validate(['note' => 'required|string|max:500'], ['note.required' => 'Write what does not match.']);
        app(NovaInbox::class)->unlink($id, auth()->user(), $v['note']);

        return back()->with('success', 'Marked as mismatch; the invoice is back in the matching list.');
    }

    // ---- 9. Integration monitoring -----------------------------------------------------------------------------
    public function integration()
    {
        $this->need('btc.integration');
        if (! $this->ready()) {
            return $this->notReady();
        }
        $logs = DB::table('btc_integration_logs')->orderByDesc('id')->limit(200)->get(['id', 'channel', 'status', 'received', 'imported', 'updated', 'duplicates', 'failed', 'error', 'ip', 'created_at']);
        $failedNow = DB::table('btc_invoices')->where('import_status', 'failed')->count();
        $lastOk = DB::table('btc_integration_logs')->where('channel', 'nova_api')->where('status', 'success')->max('created_at');
        $missingDocs = DB::table('btc_invoices')->where('nova_status', 'transferred')->whereNull('document_url')->orderByDesc('id')->limit(50)->get();

        return view('admin.btc.pages.integration', ['logs' => $logs, 'failedNow' => $failedNow, 'lastOk' => $lastOk, 'missingDocs' => $missingDocs,
            'apiOn' => (bool) config('btc.nova.api_key'), 'endpoint' => url('/api/btc/nova/invoices')]);
    }

    public function integrationRetry(Request $request)
    {
        $this->need('btc.integration');
        $r = app(NovaInbox::class)->retry($request->filled('log_id') ? (int) $request->input('log_id') : null);

        return back()->with('success', $r['received'] ? "Retried {$r['received']}: imported/updated ".($r['imported'] + $r['updated']).", still failing {$r['failed']}." : 'Nothing to retry.');
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
            // name, e-mail, employee id or employee code
            $byCode = DB::connection('hrims')->table('hrm_employee')->where('EmpCode', $s)->pluck('EmployeeID')->all();
            $users = \App\Models\User::where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
                ->orWhere('employee_id', $s)->orWhereIn('employee_id', $byCode ?: [0]))->orderBy('name')->limit(30)->get();
        }
        $userRoles = $users->isEmpty() ? collect() : DB::table('model_has_roles')->whereIn('model_id', $users->pluck('id')->all())
            ->where('model_type', \App\Models\User::class)->whereIn('role_id', $roles->pluck('id')->all() ?: [0])->get()->groupBy('model_id')->map(fn ($g) => $g->pluck('role_id')->all());

        $history = DB::table('btc_access_logs')->orderByDesc('id')->limit(200)->get();
        $activityUser = $request->input('activity_user');
        $activity = $activityUser ? DB::table('btc_case_logs as l')->leftJoin('btc_cases as c', 'c.id', '=', 'l.btc_case_id')
            ->where('l.user_id', (int) $activityUser)->orderByDesc('l.id')->limit(200)->get(['l.created_at', 'l.action', 'l.to_status', 'l.remark', 'c.case_no']) : collect();

        return view('admin.btc.pages.access', [
            'roles' => $roles, 'permByRole' => $permByRole, 'userCount' => $userCount, 'catalog' => config('btc.permission_catalog'),
            'departments' => $departments, 'users' => $users, 'userRoles' => $userRoles, 'history' => $history, 'activity' => $activity,
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
            'target_user_id' => $user->id, 'target_user' => trim($user->name.' (emp '.($user->employee_id ?? '-').')')]);

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
