<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BtcCase;
use App\Models\BtcCaseFile;
use App\Models\BtcCaseLog;
use App\Services\Btc\BtcWorkflow;
use App\Services\Btc\InvoiceReader;
use App\Services\Btc\NovaInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * BTC-READER-01 (18 Nov 2026): Invoice Reader - vendor invoices are uploaded and read in Xeasy (no wait for Nova).
 *
 *  1. upload one or more invoices (PDF / photo) -> each is read (invoice no., date, vendor, GSTIN, taxable, GST, total);
 *  2. the best matching BTC request is suggested (same scoring as the Nova matching);
 *  3. the user checks the figures and links: the file becomes the case's bill, the bill fields are filled and a booked
 *     case moves on to DMS (bill submitted). The invoice also shows in the Invoice Inbox / Matching pages (source Xeasy).
 * Also used by the case window: "Read invoice" fills the bill fields from the chosen or attached bill.
 */
class BtcReaderController extends Controller
{
    private const PERMS = ['btc.dms', 'btc.invoices', 'btc.booking', 'btc.verifier', 'btc.finance'];

    private function need(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->can('btc.admin') || collect(self::PERMS)->contains(fn ($p) => $u->can($p))), 403, 'You do not have access to the Invoice Reader.');
    }

    private function ready(): bool
    {
        return Schema::hasTable('btc_invoices') && Schema::hasColumn('btc_invoices', 'file_path');
    }

    public function index(Request $request)
    {
        $this->need();
        $co = (int) session('company_id', 1);
        $rows = collect();
        $suggest = [];
        $caseNos = collect();
        if ($this->ready()) {
            $q = DB::table('btc_invoices')->where('company_id', $co)->where('source', 'xeasy');
            $show = in_array($request->input('show'), ['open', 'linked', 'all'], true) ? $request->input('show') : 'open';
            if ($show === 'open') {
                $q->where('match_status', '!=', 'matched');
            } elseif ($show === 'linked') {
                $q->where('match_status', 'matched');
            }
            if ($s = trim((string) $request->input('q'))) {
                $q->where(fn ($w) => $w->where('invoice_no', 'like', "%{$s}%")->orWhere('vendor_name', 'like', "%{$s}%")->orWhere('vendor_gstin', 'like', "%{$s}%")->orWhere('file_name', 'like', "%{$s}%"));
            }
            $rows = $q->orderByDesc('id')->limit(200)->get();
            $nova = new NovaInbox;
            foreach ($rows->where('match_status', '!=', 'matched')->take(40) as $r) {
                $suggest[$r->id] = $nova->suggest($r, 3);
            }
            $caseNos = BtcCase::whereIn('id', $rows->pluck('btc_case_id')->filter()->all() ?: [0])->pluck('case_no', 'id');
        }

        return view('admin.btc.pages.reader', ['rows' => $rows, 'suggest' => $suggest, 'caseNos' => $caseNos, 'ready' => $this->ready(),
            'show' => $request->input('show', 'open'), 'categories' => config('btc.categories')]);
    }

    /** Upload and read up to 10 invoices. */
    public function upload(Request $request)
    {
        $this->need();
        abort_unless($this->ready(), 503, 'Run the BTC database update first (Invoice Reader).');
        $request->validate(['files' => 'required|array|max:10', 'files.*' => 'file|max:10240', 'category' => 'nullable|string|max:20'],
            ['files.required' => 'Choose the invoice file(s).', 'files.max' => 'At most 10 invoices at a time.']);
        $cat = array_key_exists((string) $request->input('category'), config('btc.categories')) ? $request->input('category') : 'other';
        $co = (int) session('company_id', 1);
        $reader = new InvoiceReader;
        $nova = new NovaInbox;
        $ok = 0;
        $bad = [];
        foreach ($request->file('files', []) as $f) {
            try {
                $ext = \App\Support\SafeUpload::check($f, ['pdf', 'jpg', 'png', 'webp', 'heic'], 10240);
            } catch (\RuntimeException $e) {
                $bad[] = $f->getClientOriginalName().': '.$e->getMessage();
                continue;
            }
            $path = $f->storeAs('btc/inbox/'.now()->format('Ym'), Str::random(24).'.'.$ext, 'local');
            $id = DB::table('btc_invoices')->insertGetId([
                'company_id' => $co, 'source' => 'xeasy', 'file_path' => $path, 'file_name' => mb_substr($f->getClientOriginalName(), 0, 255),
                'category' => $cat, 'received_on' => now()->toDateString(), 'nova_status' => 'transferred', 'import_status' => 'imported',
                'match_status' => 'unmatched', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('btc_invoices')->where('id', $id)->update(['nova_document_id' => 'XE-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
            $this->readInto($id, $reader);
            $inv = DB::table('btc_invoices')->where('id', $id)->first();
            if ($best = $nova->suggest($inv, 1)[0] ?? null) {
                DB::table('btc_invoices')->where('id', $id)->update(['match_status' => 'suggested', 'suggested_case_id' => $best['case']->id, 'match_confidence' => $best['score']]);
            }
            if ($inv->extract_status === 'failed') {
                $bad[] = $f->getClientOriginalName().': '.$inv->extract_error;
            } else {
                $ok++;
            }
        }

        return redirect()->route('btc.reader')->with($bad ? 'error' : 'success', trim(($ok ? "{$ok} invoice(s) read. Check the figures and link each to its request. " : '').($bad ? 'Not read: '.implode('; ', $bad).' (enter those by hand below).' : '')));
    }

    /** (Re-)read the stored file of an invoice row into its fields. */
    private function readInto(int $id, InvoiceReader $reader): void
    {
        $inv = DB::table('btc_invoices')->where('id', $id)->first();
        $r = $reader->read($inv->file_path, $inv->category ?: 'other');
        $f = $r['fields'];
        $complete = ! empty($f['bill_no']) && ! empty($f['bill_amount']);
        DB::table('btc_invoices')->where('id', $id)->update([
            'invoice_no' => $f['bill_no'] ?? $inv->invoice_no, 'invoice_date' => $f['bill_date'] ?? $inv->invoice_date,
            'vendor_name' => $f['vendor_name'] ?? $inv->vendor_name, 'vendor_gstin' => $f['vendor_gstin'] ?? $inv->vendor_gstin,
            'taxable_amount' => $f['taxable_amount'] ?? $inv->taxable_amount, 'tax_amount' => $f['gst_amount'] ?? $inv->tax_amount,
            'gross_amount' => $f['bill_amount'] ?? $inv->gross_amount,
            'extract_status' => ! $r['ok'] ? 'failed' : ($complete ? 'read' : 'partial'),
            'extract_error' => $r['ok'] ? ($complete ? null : 'Invoice no. or total not found - please fill in.') : mb_substr((string) $r['error'], 0, 300),
            'payload' => json_encode(['reader' => $r['raw'], 'fields' => $f], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /** Save the checked figures; with a request no., link the invoice and move the case on. */
    public function save(Request $request, int $id)
    {
        $this->need();
        $inv = DB::table('btc_invoices')->where('id', $id)->where('source', 'xeasy')->where('company_id', (int) session('company_id', 1))->first();
        abort_unless($inv, 404);
        if ($request->input('do') === 'reread') {
            $this->readInto($id, new InvoiceReader);

            return back()->with('success', 'Read again.');
        }
        if ($request->input('do') === 'discard') {
            abort_if($inv->match_status === 'matched', 422, 'A linked invoice cannot be discarded.');
            DB::table('btc_invoices')->where('id', $id)->update(['match_status' => 'mismatch', 'mismatch_note' => 'Discarded in Invoice Reader: '.mb_substr((string) $request->input('note', ''), 0, 400), 'updated_at' => now()]);

            return back()->with('success', 'Invoice set aside.');
        }
        $v = $request->validate([
            'invoice_no' => 'required|string|max:60', 'invoice_date' => 'required|date|before_or_equal:today',
            'vendor_name' => 'nullable|string|max:200', 'vendor_gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/i'],
            'taxable_amount' => 'nullable|numeric|min:0|max:9999999.99', 'tax_amount' => 'nullable|numeric|min:0|max:9999999.99',
            'gross_amount' => 'required|numeric|min:1|max:9999999.99', 'case_no' => 'nullable|string|max:30',
        ], ['vendor_gstin.regex' => 'The GSTIN is not in the right format.']);
        $v += ['vendor_name' => null, 'vendor_gstin' => null, 'taxable_amount' => null, 'tax_amount' => null, 'case_no' => null];
        if ($v['taxable_amount'] !== null && $v['tax_amount'] !== null && abs((float) $v['taxable_amount'] + (float) $v['tax_amount'] - (float) $v['gross_amount']) > 1) {
            return back()->withErrors(['reader' => 'Taxable value plus GST should equal the total.'])->withInput();
        }
        DB::table('btc_invoices')->where('id', $id)->update([
            'invoice_no' => trim($v['invoice_no']), 'invoice_date' => $v['invoice_date'], 'vendor_name' => $v['vendor_name'],
            'vendor_gstin' => $v['vendor_gstin'] ? strtoupper($v['vendor_gstin']) : null, 'taxable_amount' => $v['taxable_amount'] ?? null,
            'tax_amount' => $v['tax_amount'] ?? null, 'gross_amount' => $v['gross_amount'], 'updated_at' => now(),
        ]);
        $caseNo = trim((string) ($v['case_no'] ?? ''));
        if ($caseNo === '') {
            return back()->with('success', 'Saved. Choose the request to link it to.');
        }
        $c = BtcCase::where('company_id', (int) session('company_id', 1))->where(fn ($q) => ctype_digit($caseNo) ? $q->where('case_no', 'like', '%/'.str_pad(ltrim($caseNo, '0'), 5, '0', STR_PAD_LEFT)) : $q->where('case_no', strtoupper($caseNo)))->first();
        if (! $c) {
            return back()->withErrors(['reader' => "No BTC request {$caseNo}."])->withInput();
        }
        if (in_array($c->status, ['cancelled', 'rejected', 'closed', 'draft'], true)) {
            return back()->withErrors(['reader' => "{$c->case_no} is {$c->statusLabel()}; an invoice cannot be linked."])->withInput();
        }
        if ($other = DB::table('btc_invoices')->where('btc_case_id', $c->id)->where('match_status', 'matched')->where('id', '!=', $id)->value('invoice_no')) {
            return back()->withErrors(['reader' => "{$c->case_no} already has invoice {$other} linked."])->withInput();
        }
        $user = auth()->user();
        $inv = DB::table('btc_invoices')->where('id', $id)->first();
        $msg = '';
        DB::transaction(function () use ($inv, $c, $user, &$msg) {
            (new NovaInbox)->link($inv->id, $c->id, $user, 100, 'Read in Invoice Reader by '.($user->name ?? 'user'));
            $c->refresh();
            // the invoice's figures are the bill (the reader is the source now, not a guess)
            foreach (['bill_no' => 'invoice_no', 'bill_date' => 'invoice_date', 'bill_amount' => 'gross_amount'] as $ck => $ik) {
                $c->{$ck} = $inv->{$ik};
            }
            $c->taxable_amount = $inv->taxable_amount ?? $inv->gross_amount;
            $c->gst_amount = $inv->tax_amount ?? 0;
            if (! $c->vendor_name && $inv->vendor_name) {
                $c->vendor_name = $inv->vendor_name;
            }
            $c->save();
            if (! $c->files()->where('kind', 'bill')->exists() && $inv->file_path && Storage::disk('local')->exists($inv->file_path)) {
                $to = "btc/{$c->company_id}/{$c->id}/bill_reader_".now()->format('YmdHis').'.'.pathinfo($inv->file_path, PATHINFO_EXTENSION);
                Storage::disk('local')->copy($inv->file_path, $to);
                BtcCaseFile::create(['btc_case_id' => $c->id, 'kind' => 'bill', 'file_path' => $to, 'file_name' => $inv->file_name ?: basename($to),
                    'mime' => Storage::disk('local')->mimeType($to), 'uploaded_by' => $user->id ?? null]);
            }
        });
        // a booked / advance-paid case moves on to DMS with this bill (when the user may submit the bill)
        $c->refresh();
        if (in_array($c->status, ['booked', 'advance_paid', 'returned'], true) && in_array('submit_bill', BtcWorkflow::allowed($c, $user), true)) {
            try {
                (new BtcWorkflow)->act($c, 'submit_bill', ['bill_no' => $c->bill_no, 'bill_date' => \Carbon\Carbon::parse($c->bill_date)->format('Y-m-d'),
                    'taxable_amount' => $c->taxable_amount, 'gst_amount' => $c->gst_amount, 'bill_amount' => $c->bill_amount,
                    'submitted_via' => 'invoice_reader', 'remark' => 'Bill read in Invoice Reader'], $user);
                $msg = ' The request moved to DMS (bill submitted).';
            } catch (\RuntimeException $e) {
                $msg = ' The request did not move on: '.$e->getMessage();
            }
        } elseif ($c->status === 'bill_submitted') {
            $msg = ' DMS can now process it.';
        }

        return back()->with('success', "Invoice {$inv->invoice_no} linked to {$c->case_no}.".$msg);
    }

    /** Case window: read the chosen bill file (upload) or the case's attached bill; returns the fields to fill. */
    public function read(Request $request)
    {
        $user = auth()->user();
        $c = $request->filled('case_id') ? BtcCase::findOrFail((int) $request->input('case_id')) : null;
        if ($c) {
            app(BtcController::class)->authorizeView($c, $user);
        } else {
            $this->need();
        }
        $cat = $c->category ?? (array_key_exists((string) $request->input('category'), config('btc.categories')) ? $request->input('category') : 'other');
        $tmp = null;
        if ($request->hasFile('file')) {
            try {
                $ext = \App\Support\SafeUpload::check($request->file('file'), ['pdf', 'jpg', 'png', 'webp', 'heic'], 10240);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            $path = $tmp = $request->file('file')->storeAs('btc/inbox/tmp', Str::random(24).'.'.$ext, 'local');
        } else {
            abort_unless($c, 422, 'Choose the bill file.');
            $f = $c->files()->where('kind', 'bill')->orderByDesc('id')->first();
            if (! $f) {
                return response()->json(['success' => false, 'message' => 'No bill attached to this request yet.'], 422);
            }
            $path = $f->file_path;
        }
        $r = (new InvoiceReader)->read($path, $cat);
        if ($tmp) {
            Storage::disk('local')->delete($tmp);
        }

        return response()->json(['success' => $r['ok'], 'fields' => $r['fields'], 'message' => $r['error']]);
    }

    /** The stored invoice file (Xeasy users with access). */
    public function file(int $id)
    {
        $this->need();
        $inv = DB::table('btc_invoices')->where('id', $id)->where('source', 'xeasy')->first();
        abort_unless($inv && $inv->file_path && Storage::disk('local')->exists($inv->file_path), 404);

        return Storage::disk('local')->response($inv->file_path, $inv->file_name);
    }

    /** Signed 15-minute link for the reading service (no login; route btc.reader.file in web.php, 'signed' middleware). */
    public function signedFile(Request $request)
    {
        try {
            $path = Crypt::decryptString((string) $request->query('f'));
        } catch (\Throwable $e) {
            abort(404);
        }
        abort_unless(str_starts_with($path, 'btc/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
