<?php

namespace App\Http\Controllers;

use App\Models\BtcCase;
use App\Models\BtcCaseFile;
use App\Models\BtcCaseLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * BOARDING-01 (Main tracker 52, 7 Nov 2026): vendor boarding-pass portal.
 *
 * Finance creates a link for a travel vendor from the BTC screen. The vendor opens it without an
 * Xeasy login and sees only its own air-travel BTC cases still waiting for a boarding pass
 * (case no, traveller, PNR, sector, travel date) and uploads one file per case. The upload is saved
 * as the case's boarding pass, the received date is set and the case log records "vendor portal".
 * Links are random, stored only as a hash, expire (default 30 days) and can be revoked.
 */
class VendorBoardingPassController extends Controller
{
    public const TABLE = 'vendor_portal_links';

    // ---- Finance side (login + btc.finance / btc.admin) ----------------------------------------

    public function links()
    {
        $this->financeOnly();
        $tableOk = $this->ensureTable();
        $co = (int) session('company_id', 1);
        $pending = BtcCase::where('company_id', $co)->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->selectRaw("COALESCE(vendor_id, 0) AS vendor_id, COALESCE(NULLIF(vendor_name,''), '(no vendor)') AS vendor_name, COUNT(*) AS cases, MIN(travel_date) AS oldest")
            ->groupBy('vendor_id', 'vendor_name')->orderByDesc('cases')->get();
        $links = $tableOk ? DB::table(self::TABLE)->where('company_id', $co)->orderByDesc('id')->limit(100)->get() : collect();

        // 16 Nov #5: a link can be created for any vendor (before only for vendors with boarding passes pending).
        // Travel agencies first, then vendors already used on BTC air bookings, then the rest of the vendor master.
        $vendors = collect();
        try {
            $used = BtcCase::where('company_id', $co)->where('category', 'air')->whereNotNull('vendor_name')->pluck('vendor_name')->map(fn ($n) => strtolower(trim($n)))->flip();
            $vendors = DB::table('vendor_list')->where('status', '1')->whereNotNull('vendor_name')->orderBy('vendor_name')
                ->get(['id', 'vendor_name', 'vendor_type'])
                ->map(fn ($v) => (object) ['id' => (int) $v->id, 'name' => trim(preg_replace('/\s+/', ' ', (string) $v->vendor_name)),
                    'travel' => (bool) preg_match('/agen|travel|air/i', (string) $v->vendor_type.' '.$v->vendor_name)])
                ->reject(fn ($v) => in_array(strtolower($v->name), ['', 'na', 'n/a', 'other', 'others', '-', 'nil', 'none', 'test'], true))
                ->unique(fn ($v) => strtolower($v->name))
                ->sortBy(fn ($v) => [isset($used[strtolower($v->name)]) || $v->travel ? 0 : 1, strtolower($v->name)])->values();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('admin.btc.vendor-links', ['pending' => $pending, 'links' => $links, 'newUrl' => session('vendor_link_url'),
            'setupMissing' => ! $tableOk, 'vendors' => $vendors]);
    }

    public function create(Request $request)
    {
        $this->financeOnly();
        if (! $this->ensureTable()) {
            return back()->with('error', 'Vendor links are not set up on this server yet (IT: run deploy/deploy.sh).');
        }
        $v = $request->validate(['vendor_id' => 'nullable|integer', 'vendor_name' => 'required|string|max:200', 'days' => 'nullable|integer|between:1,90']);
        $token = Str::random(48);
        DB::table(self::TABLE)->insert([
            'company_id' => (int) session('company_id', 1), 'vendor_id' => (int) ($v['vendor_id'] ?? 0) ?: null, 'vendor_name' => $v['vendor_name'],
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays((int) ($v['days'] ?? 30)),
            'created_by' => auth()->id(), 'created_by_name' => auth()->user()->name ?? null, 'created_at' => now(),
        ]);

        return redirect()->route('btc.vendor.links')->with('vendor_link_url', route('vendor.boarding.show', $token))
            ->with('success', 'Link created for '.$v['vendor_name'].'. Copy it now: it is shown only once.');
    }

    public function revoke(int $id)
    {
        $this->financeOnly();
        DB::table(self::TABLE)->where('id', $id)->where('company_id', (int) session('company_id', 1))->update(['revoked_at' => now()]);

        return redirect()->route('btc.vendor.links')->with('success', 'Link revoked.');
    }

    private function financeOnly(): void
    {
        abort_unless(auth()->check() && (auth()->user()->can('btc.finance') || auth()->user()->can('btc.admin')), 403);
    }

    // ---- Vendor side (no login; the link is the credential) ------------------------------------

    public function show(string $token)
    {
        $link = $this->link($token);

        // BTC-VENDOR-01 (17 Nov 2026): the vendor also sends invoices and follows payment here
        $mine = $this->vendorCases($link)->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->orderByDesc('id')->limit(300)->get();
        $billable = $mine->filter(fn ($c) => in_array($c->status, ['booked', 'advance_paid', 'returned'], true) && (! $c->bill_no || $c->status === 'returned'))->values();
        $billed = $mine->filter(fn ($c) => $c->bill_no)->values();
        $pays = \Illuminate\Support\Facades\Schema::hasTable('btc_payments')
            ? DB::table('btc_payments')->whereIn('btc_case_id', $billed->pluck('id'))->where('status', 'success')->orderByDesc('id')->get()->groupBy('btc_case_id') : collect();

        return view('vendor-portal.boarding-pass', ['link' => $link, 'cases' => $this->cases($link), 'token' => $token,
            'billable' => $billable, 'billed' => $billed, 'pays' => $pays, 'bulk' => session('bulk_result')]);
    }

    /** BOARDING-04: many boarding passes at once, matched by PNR / BTC number in the file name. */
    public function bulk(Request $request, string $token)
    {
        $link = $this->link($token);
        $request->validate(['files' => 'required|array|max:100', 'files.*' => 'file|max:5120'], ['files.required' => 'Choose the boarding pass files or folder.']);
        $res = \App\Services\Btc\BtcBoardingBulk::upload($this->cases($link), $request->file('files', []), 'Vendor: '.$link->vendor_name, null, 'vendor portal link #'.$link->id.', IP '.$request->ip());
        DB::table(self::TABLE)->where('id', $link->id)->update(['last_used_at' => now()]);

        return back()->with('bulk_result', $res)->with('success', collect($res)->where('ok', true)->count().' of '.count($res).' file(s) saved.');
    }

    /** BTC-VENDOR-01: the vendor sends the invoice for a booking; it goes to DMS as the bill. */
    public function invoice(Request $request, string $token, int $caseId)
    {
        $link = $this->link($token);
        $v = $request->validate([
            'invoice_no' => 'required|string|max:60', 'invoice_date' => 'required|date|before_or_equal:today',
            'taxable_amount' => 'required|numeric|min:0|max:9999999.99', 'gst_amount' => 'nullable|numeric|min:0|max:9999999.99',
            'bill_amount' => 'required|numeric|min:1|max:9999999.99', 'gstin' => 'nullable|string|max:20',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ], ['file.required' => 'Attach the invoice (PDF or photo).']);
        $case = $this->vendorCases($link)->whereKey($caseId)->whereIn('status', ['booked', 'advance_paid', 'returned'])->first();
        if (! $case || ($case->bill_no && $case->status !== 'returned')) {
            return back()->withErrors(['invoice' => 'This booking is not waiting for an invoice.']);
        }
        if (abs((float) $v['taxable_amount'] + (float) ($v['gst_amount'] ?? 0) - (float) $v['bill_amount']) > 1) {
            return back()->withErrors(['invoice' => 'Taxable value plus GST should equal the invoice total.']);
        }
        $no = trim($v['invoice_no']);
        $dup = BtcCase::where('id', '!=', $case->id)->where('bill_no', $no)->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(fn ($q) => $link->vendor_id ? $q->where('vendor_id', $link->vendor_id) : $q->where('vendor_name', $link->vendor_name))->value('case_no');
        if ($dup) {
            return back()->withErrors(['invoice' => "Invoice {$no} is already on booking {$dup}."]);
        }
        $f = $request->file('file');
        $path = $f->storeAs("btc/{$case->company_id}/{$case->id}", 'bill_vendor_'.now()->format('YmdHis').'.'.strtolower($f->getClientOriginalExtension()), 'local');
        DB::transaction(function () use ($case, $v, $no, $f, $path, $link, $request) {
            $old = $case->status;
            BtcCaseFile::create(['btc_case_id' => $case->id, 'kind' => 'bill', 'file_path' => $path, 'file_name' => mb_substr($f->getClientOriginalName(), 0, 255),
                'mime' => $f->getMimeType(), 'uploaded_by' => null]);
            $case->fill(['bill_no' => $no, 'bill_date' => $v['invoice_date'], 'taxable_amount' => $v['taxable_amount'], 'gst_amount' => $v['gst_amount'] ?? 0,
                'bill_amount' => $v['bill_amount'], 'bill_submitted_at' => now(), 'bill_submitted_by' => null, 'submitted_via' => 'vendor_portal', 'status' => 'bill_submitted']);
            if (! $case->vendor_gstin && ! empty($v['gstin'])) {
                $case->vendor_gstin = strtoupper(trim($v['gstin']));
            }
            $case->save();
            if (Schema::hasTable('btc_invoices')) {
                DB::table('btc_invoices')->insert(['company_id' => $case->company_id, 'source' => 'vendor', 'invoice_no' => $no, 'invoice_date' => $v['invoice_date'],
                    'vendor_name' => $case->vendor_name, 'vendor_gstin' => $case->vendor_gstin, 'taxable_amount' => $v['taxable_amount'], 'tax_amount' => $v['gst_amount'] ?? 0,
                    'gross_amount' => $v['bill_amount'], 'received_on' => now()->toDateString(), 'nova_status' => 'received', 'import_status' => 'imported',
                    'btc_case_id' => $case->id, 'match_status' => 'matched', 'match_confidence' => 100, 'matched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            BtcCaseLog::create(['btc_case_id' => $case->id, 'action' => 'vendor_invoice', 'from_status' => $old, 'to_status' => 'bill_submitted',
                'remark' => "Invoice {$no} Rs ".number_format((float) $v['bill_amount'], 2).' sent by the vendor through the portal (link #'.$link->id.', IP '.$request->ip().')',
                'user_id' => null, 'user_name' => mb_substr('Vendor: '.$link->vendor_name, 0, 150)]);
            DB::table(self::TABLE)->where('id', $link->id)->update(['last_used_at' => now()]);
        });
        try {
            \App\Services\Btc\BtcNotifier::handoff($case->fresh(), 'booked', 'vendor_invoice');
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Invoice {$no} received for {$case->case_no}. Thank you.");
    }

    private function vendorCases(object $link)
    {
        return BtcCase::where('company_id', $link->company_id)
            ->where(fn ($q) => $link->vendor_id ? $q->where('vendor_id', $link->vendor_id) : $q->where('vendor_name', $link->vendor_name));
    }

    public function upload(Request $request, string $token, int $caseId)
    {
        $link = $this->link($token);
        $request->validate(['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png'], ['file.required' => 'Choose the boarding pass file.']);
        $case = $this->cases($link)->firstWhere('id', $caseId);
        if (! $case) {
            return back()->withErrors(['file' => 'This case is not open for upload (already received, or not one of your bookings).']);
        }
        $f = $request->file('file');
        $path = $f->storeAs("btc/{$case->company_id}/{$case->id}", 'boarding_pass_vendor_'.now()->format('YmdHis').'.'.strtolower($f->getClientOriginalExtension()), 'local');
        DB::transaction(function () use ($case, $f, $path, $link, $request) {
            BtcCaseFile::create(['btc_case_id' => $case->id, 'kind' => 'boarding_pass', 'file_path' => $path,
                'file_name' => mb_substr($f->getClientOriginalName(), 0, 255), 'mime' => $f->getMimeType(), 'uploaded_by' => null]);
            $case->boarding_pass_received_on = now()->toDateString();
            $case->save();
            BtcCaseLog::create(['btc_case_id' => $case->id, 'action' => 'vendor_boarding_pass', 'from_status' => $case->status, 'to_status' => $case->status,
                'remark' => 'Boarding pass uploaded through the vendor portal (link #'.$link->id.', IP '.$request->ip().')', 'user_id' => null,
                'user_name' => mb_substr('Vendor: '.$link->vendor_name, 0, 150)]);
            DB::table(self::TABLE)->where('id', $link->id)->update(['last_used_at' => now()]);
        });

        return back()->with('success', 'Boarding pass received for case '.$case->case_no.'. Thank you.');
    }

    private function link(string $token): object
    {
        abort_unless(Schema::hasTable(self::TABLE) && strlen($token) >= 40, 404);
        $l = DB::table(self::TABLE)->where('token_hash', hash('sha256', $token))->first();
        abort_unless($l && ! $l->revoked_at && now()->lt($l->expires_at), 404, 'This link is not valid any more. Please ask VNR Finance for a new link.');

        return $l;
    }

    private function cases(object $link)
    {
        return BtcCase::where('company_id', $link->company_id)->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->where(fn ($q) => $link->vendor_id ? $q->where('vendor_id', $link->vendor_id) : $q->where('vendor_name', $link->vendor_name))
            ->orderBy('travel_date')->get();
    }

    /** BOARDING-03: true when the links table is there (created by migration 2026_11_15_000015, or here as before). */
    private function ensureTable(): bool
    {
        if (Schema::hasTable(self::TABLE)) {
            return true;
        }
        try {
            $this->createTable();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Vendor boarding-pass links table could not be created: '.$e->getMessage());

            return false;
        }

        return Schema::hasTable(self::TABLE);
    }

    private function createTable(): void
    {
        Schema::create(self::TABLE, function ($t) {
            $t->id();
            $t->unsignedTinyInteger('company_id')->default(1);
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('vendor_name', 200);
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }
}
