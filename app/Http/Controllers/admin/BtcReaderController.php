<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BtcCase;
use App\Services\Btc\InvoiceReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * BTC-READER-01 (18 Nov 2026) / BTC-V2 (19 Nov 2026): reading an invoice in Xeasy. The upload screen and the invoice
 * rows were replaced by Bill Upload by Finance and Automatic Data Extraction (BtcV2Controller); what stays here is the
 * "Read invoice" button of the case window and the signed link the reading service fetches the file from.
 */
class BtcReaderController extends Controller
{
    private const PERMS = ['btc.bill_upload', 'btc.finance', 'btc.verifier', 'btc.booking'];

    private function need(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->can('btc.admin') || collect(self::PERMS)->contains(fn ($p) => $u->can($p))), 403, 'You do not have access to the invoice reader.');
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

    /** Signed 15-minute link for the reading service (no login; route btc.reader.file in web.php, 'signed' middleware). */
    public function signedFile(Request $request)
    {
        try {
            $path = \App\Services\Btc\InvoiceReader::untoken((string) $request->query('f'));   // BTC-READER-02
        } catch (\Throwable $e) {
            abort(404);
        }
        abort_unless(str_starts_with($path, 'btc/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
