<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * FOCUS-LOG-01 (audit X-03, LV-C10): one append-only row per Focus export, claim or payment:
 * who, when, company, year, filters, rows, total and a sha256 of the file (stored exports) or of
 * the exported rows (streamed downloads). Logging never blocks an export.
 */
class FocusExportLog
{
    /** @param iterable $rows exported rows (collection or array) */
    public static function record(string $type, $rows, array $filters = [], ?string $fileName = null, ?string $publicPath = null): void
    {
        try {
            $list = collect($rows)->map(fn ($r) => (array) $r)->values();
            $hashOf = 'rows';
            $hash = hash('sha256', json_encode($list->all()));
            foreach ([\App\Support\PrivateExport::DISK, 'public'] as $disk) { // FILES-01: exports are private now
                if ($publicPath && Storage::disk($disk)->exists($publicPath)) {
                    $hash = hash('sha256', Storage::disk($disk)->get($publicPath));
                    $hashOf = 'file';
                    break;
                }
            }
            $user = Auth::user();
            DB::table('focus_export_logs')->insert([
                'company_id' => (int) session('company_id') ?: null,
                'year_id' => (int) session('year_id') ?: null,
                'export_type' => substr($type, 0, 40),
                'file_name' => $fileName ? substr($fileName, 0, 190) : null,
                'row_count' => $list->count(),
                'total_amount' => self::total($list),
                'content_hash' => $hash,
                'hash_of' => $hashOf,
                'filters' => json_encode(array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== [])),
                'exported_by' => $user?->id,
                'exported_by_name' => $user ? substr((string) ($user->name ?? ''), 0, 120) : null,
                'ip' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('FOCUS-LOG-01 not written: '.$e->getMessage());
        }
    }

    /** Sum of the first amount-like column present in the rows. */
    private static function total($list): ?float
    {
        $first = $list->first();
        if (! $first) {
            return 0.0;
        }
        foreach (['payment_amount', 'Amount', 'amount', 'Debit', 'debit', 'FinancedTAmt', 'ApprTAmt', 'total_amount', 'Fin_PayAmt'] as $k) {
            if (array_key_exists($k, $first)) {
                return round((float) $list->sum(fn ($r) => (float) ($r[$k] ?? 0)), 2);
            }
        }
        foreach (array_keys($first) as $k) {
            if (preg_match('/amount|amt$/i', $k)) {
                return round((float) $list->sum(fn ($r) => (float) ($r[$k] ?? 0)), 2);
            }
        }

        return null;
    }
}
