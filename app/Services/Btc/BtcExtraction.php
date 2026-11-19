<?php

namespace App\Services\Btc;

use App\Models\BtcCase;

/**
 * BTC-V2 Step 2 (19 Nov 2026, BTC requirement sheet item 24): Automatic Data Extraction.
 *
 * The bill attached to the case is read by the invoice reader (InvoiceReader) and put into the Finance data format
 * (config btc.extraction.fields): vendor, GSTIN, invoice no. / date, particulars, taxable, CGST / SGST / IGST, other
 * charges, total, service period. The reader gives the total GST; it is split by the vendor's GSTIN state - same state
 * as the company = CGST + SGST, else IGST. Fields already filled (typed or corrected) are not overwritten. What was read
 * is kept on the case (extract_json) so the user can compare it with the original invoice.
 */
class BtcExtraction
{
    public const AMOUNTS = ['taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'other_charges', 'bill_amount'];

    /** @return array{ok:bool, error:?string, fields:array} */
    public static function run(BtcCase $c, bool $overwrite = false): array
    {
        $file = $c->files()->where('kind', 'bill')->orderByDesc('id')->first();
        if (! $file) {
            return ['ok' => false, 'error' => 'no bill attached', 'fields' => []];
        }
        $r = (new InvoiceReader)->read($file->file_path, $c->category ?: 'other');
        $f = $r['fields'];
        if ($r['ok']) {
            $gst = (float) ($f['gst_amount'] ?? 0);
            if ($gst > 0) {
                $state = substr((string) ($f['vendor_gstin'] ?? $c->vendor_gstin ?? ''), 0, 2);
                if ($state !== '' && $state !== (string) config('btc.extraction.company_state', '22')) {
                    $f['igst_amount'] = round($gst, 2);
                    $f['cgst_amount'] = $f['sgst_amount'] = 0;
                } else {
                    $f['cgst_amount'] = round($gst / 2, 2);
                    $f['sgst_amount'] = round($gst - $f['cgst_amount'], 2);
                    $f['igst_amount'] = 0;
                }
            }
            if (! empty($f['bill_amount']) && ! empty($f['taxable_amount'])) {
                $other = round((float) $f['bill_amount'] - (float) $f['taxable_amount'] - $gst, 2);
                $f['other_charges'] = $other > 0.5 ? $other : 0;
            }
            $particulars = trim((string) collect($r['raw'] ?? [])->only(['Particulars', 'Description', 'Remark', 'Service Description'])->filter(fn ($v) => is_scalar($v))->implode(' '));
            if ($particulars === '' && is_array($r['raw']['Expenses'] ?? null)) {
                $particulars = collect($r['raw']['Expenses'])->pluck('Particulars')->filter()->implode(', ');
            }
            if ($particulars !== '') {
                $f['bill_particulars'] = mb_substr($particulars, 0, 500);
            }
            if (! empty($f['travel_date']) || $c->service_from) {
                $f['bill_period'] = $c->service_from ? $c->service_from->format('d-m-Y').($c->service_to && ! $c->service_to->eq($c->service_from) ? ' to '.$c->service_to->format('d-m-Y') : '') : $f['travel_date'];
            }
            foreach (array_keys(config('btc.extraction.fields')) as $k) {
                if (! array_key_exists($k, $f)) {
                    continue;
                }
                $empty = $c->{$k} === null || $c->{$k} === '' || (in_array($k, self::AMOUNTS, true) && (float) $c->{$k} == 0.0);
                if ($overwrite || $empty) {
                    $c->{$k} = $f[$k];
                }
            }
            foreach (['pnr', 'travel_sector', 'travel_date'] as $k) {
                if (! empty($f[$k]) && ! $c->{$k}) {
                    $c->{$k} = $f[$k];
                }
            }
            $c->gst_amount = round((float) $c->cgst_amount + (float) $c->sgst_amount + (float) $c->igst_amount, 2);
        }
        $c->extract_status = $r['ok'] ? (self::missing($c) ? 'partial' : 'read') : 'failed';
        $c->extract_note = $r['ok'] ? (self::missing($c) ? 'Not found: '.implode(', ', self::missing($c)) : null) : mb_substr((string) $r['error'], 0, 300);
        $c->extract_json = json_encode(['at' => now()->toDateTimeString(), 'fields' => $f, 'raw' => $r['raw'] ?? []], JSON_UNESCAPED_UNICODE);
        $c->save();

        return ['ok' => $r['ok'], 'error' => $r['error'], 'fields' => $f];
    }

    /** Labels of the required fields still empty. */
    public static function missing(BtcCase $c): array
    {
        $out = [];
        $default = (array) config('btc.extraction.required');
        $req = collect(array_keys((array) config('btc.extraction.fields')))->filter(fn ($k) => (bool) config("btc.extraction.must.{$k}", in_array($k, $default, true)));
        foreach ($req as $k) {
            $v = $c->{$k};
            if ($v === null || $v === '' || (in_array($k, self::AMOUNTS, true) && (float) $v <= 0)) {
                $out[] = config("btc.extraction.fields.{$k}", $k);
            }
        }

        return $out;
    }

    /** Taxable + CGST + SGST + IGST + other charges must make the total (within Re 1). */
    public static function breakupError(BtcCase $c): ?string
    {
        if ((float) $c->bill_amount <= 0 || (float) $c->taxable_amount <= 0) {
            return null;
        }
        $sum = round((float) $c->taxable_amount + (float) $c->cgst_amount + (float) $c->sgst_amount + (float) $c->igst_amount + (float) $c->other_charges, 2);
        if (abs($sum - (float) $c->bill_amount) > 1) {
            return 'Taxable + GST + other charges (Rs '.number_format($sum, 2).') do not add up to the invoice total (Rs '.number_format((float) $c->bill_amount, 2).').';
        }
        if ((float) $c->igst_amount > 0 && ((float) $c->cgst_amount > 0 || (float) $c->sgst_amount > 0)) {
            return 'An invoice has either IGST or CGST + SGST, not both.';
        }

        return null;
    }
}
