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
        $this->ensureTable();
        $co = (int) session('company_id', 1);
        $pending = BtcCase::where('company_id', $co)->where('boarding_pass_required', 1)->whereNull('boarding_pass_received_on')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->selectRaw("COALESCE(vendor_id, 0) AS vendor_id, COALESCE(NULLIF(vendor_name,''), '(no vendor)') AS vendor_name, COUNT(*) AS cases, MIN(travel_date) AS oldest")
            ->groupBy('vendor_id', 'vendor_name')->orderByDesc('cases')->get();
        $links = DB::table(self::TABLE)->where('company_id', $co)->orderByDesc('id')->limit(100)->get();

        return view('admin.btc.vendor-links', ['pending' => $pending, 'links' => $links, 'newUrl' => session('vendor_link_url')]);
    }

    public function create(Request $request)
    {
        $this->financeOnly();
        $this->ensureTable();
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

        return view('vendor-portal.boarding-pass', ['link' => $link, 'cases' => $this->cases($link), 'token' => $token]);
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

    private function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
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
