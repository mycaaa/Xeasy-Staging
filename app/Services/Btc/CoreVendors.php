<?php

namespace App\Services\Btc;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-VENDOR-CORE-01 (20 Nov 2026, BTC requirement sheet item 29): the BTC vendor list comes from the core
 * application (core.vnrin.in/api/btc_vendor, header "api-key"). The list is copied into btc_core_vendors (daily and
 * from BTC Settings > "Sync vendors now"); the forms read the copy, so a slow or unreachable core never blocks a
 * request. Bank account, Aadhaar, mobile and e-mail are not kept (BTC makes no bank file).
 * Vendor ids on the forms: "C<core id>" for a core vendor; a number for an old vendor_list row (old cases).
 */
class CoreVendors
{
    public static function ready(): bool
    {
        return Schema::hasTable('btc_core_vendors');
    }

    public static function active(): bool
    {
        return self::ready() && DB::table('btc_core_vendors')->where('active', 1)->exists();
    }

    /** @return array{ok:bool, message:string, added?:int, updated?:int, off?:int} */
    public static function sync(): array
    {
        if (! self::ready()) {
            return ['ok' => false, 'message' => 'Run the BTC database update first.'];
        }
        $url = (string) config('btc.core_vendors.url');
        $key = (string) config('btc.core_vendors.key');
        if ($url === '' || $key === '') {
            return ['ok' => false, 'message' => 'The core vendor API key is not set (BTC_CORE_VENDOR_KEY in .env).'];
        }
        try {
            $res = Http::timeout(60)->withHeaders(['api-key' => $key, 'Accept' => 'application/json'])
                ->withOptions(['verify' => \App\Support\TlsVerify::option()])->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'The core application could not be reached: '.mb_substr($e->getMessage(), 0, 150)];
        }
        $list = $res->json('list');
        if (! $res->successful() || ! is_array($list)) {
            return ['ok' => false, 'message' => 'The core application refused the request (HTTP '.$res->status().').'];
        }
        $now = now();
        $seen = [];
        $added = $updated = 0;
        foreach ($list as $v) {
            if (! is_array($v) || empty($v['id']) || trim((string) ($v['name'] ?? '')) === '') {
                continue;
            }
            $gstin = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) ($v['gstin'] ?? '')));
            $row = [
                'code' => mb_substr((string) ($v['code'] ?? ''), 0, 40),
                'name' => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $v['name'])), 0, 250),
                'category' => mb_substr((string) ($v['category'] ?? ''), 0, 100),
                'parent_name' => mb_substr((string) ($v['parent_name'] ?? ''), 0, 100),
                'gstin' => preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin) ? $gstin : null,
                'pan' => mb_substr(strtoupper((string) ($v['pan_number'] ?? '')), 0, 10) ?: null,
                'city' => mb_substr((string) ($v['city'] ?? ''), 0, 100) ?: null,
                'state_name' => mb_substr((string) ($v['state_name'] ?? ''), 0, 100) ?: null,
                'msme_number' => mb_substr((string) ($v['msme_number'] ?? ''), 0, 40) ?: null,
                'active' => 1, 'synced_at' => $now, 'updated_at' => $now,
            ];
            $id = (int) $v['id'];
            $seen[] = $id;
            if (DB::table('btc_core_vendors')->where('id', $id)->exists()) {
                DB::table('btc_core_vendors')->where('id', $id)->update($row);
                $updated++;
            } else {
                DB::table('btc_core_vendors')->insert($row + ['id' => $id, 'created_at' => $now]);
                $added++;
            }
        }
        if (! $seen) {
            return ['ok' => false, 'message' => 'The core application sent an empty vendor list; the current list is kept.'];
        }
        $off = DB::table('btc_core_vendors')->whereNotIn('id', $seen)->where('active', 1)->update(['active' => 0, 'updated_at' => $now]);

        return ['ok' => true, 'message' => "Vendors synced from the core application: {$added} new, {$updated} updated, {$off} no longer listed.", 'added' => $added, 'updated' => $updated, 'off' => $off];
    }

    /** BTC categories a core vendor category serves (config btc.core_vendors.category_map); [] = shown for every category. */
    public static function typesOf(string $coreCategory): array
    {
        return (array) (config('btc.core_vendors.category_map')[$coreCategory] ?? []);
    }

    /** Vendors for the BTC forms: [{id, code, name, gst, types}] */
    public static function forForm(): array
    {
        return DB::table('btc_core_vendors')->where('active', 1)->orderBy('name')->get(['id', 'code', 'name', 'gstin', 'category', 'city'])
            ->filter(fn ($r) => ! in_array($r->category, (array) config('btc.core_vendors.not_btc', []), true))
            ->map(fn ($r) => ['id' => 'C'.$r->id, 'code' => $r->code, 'name' => $r->name, 'gst' => (string) $r->gstin, 'types' => self::typesOf((string) $r->category)])
            ->values()->all();
    }

    /** A form vendor id ("C123" core, or an old vendor_list id) to [vendor_id, vendor_code, name, gstin]; null if unknown. */
    public static function find($key): ?array
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }
        if (preg_match('/^C(\d+)$/i', $key, $m)) {
            $r = self::ready() ? DB::table('btc_core_vendors')->where('id', (int) $m[1])->first(['code', 'name', 'gstin']) : null;

            return $r ? ['vendor_id' => null, 'vendor_code' => $r->code, 'name' => $r->name, 'gstin' => $r->gstin] : null;
        }
        if (ctype_digit($key)) {
            $r = DB::table('vendor_list')->where('id', (int) $key)->first(['id', 'vendor_name', 'gst_no']);

            return $r ? ['vendor_id' => (int) $r->id, 'vendor_code' => null, 'name' => $r->vendor_name, 'gstin' => $r->gst_no] : null;
        }

        return null;
    }
}
