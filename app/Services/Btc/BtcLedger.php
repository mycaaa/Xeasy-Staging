<?php

namespace App\Services\Btc;

use Illuminate\Support\Facades\DB;

/** BTC-LEDGER-01 / BTC-TDS-01 (Finance 10 Nov 2026): per-employee BTC ledger name and TDS by nature of payment. */
class BtcLedger
{
    private static array $firstNameCount = [];

    public static function employeeLedger(?string $empCode, ?string $empName): string
    {
        $pattern = (string) config('btc.focus.employee_btc_ledger', '{first_name}_BTC');
        if (! str_contains($pattern, '{')) {
            return $pattern; // one common ledger (old set-up)
        }
        $first = trim((string) strtok(trim((string) $empName), ' ')) ?: (string) $empCode;
        $first = ucfirst(strtolower($first));
        if (str_contains($pattern, '{first_name}') && self::sharedFirstName($first)) {
            $first .= ' '.$empCode;
        }

        return strtr($pattern, ['{first_name}' => $first, '{code}' => (string) $empCode, '{name}' => trim((string) $empName)]);
    }

    private static function sharedFirstName(string $first): bool
    {
        $k = strtolower($first);
        if (! array_key_exists($k, self::$firstNameCount)) {
            try {
                self::$firstNameCount[$k] = (int) DB::connection('hrims')->table('hrm_employee')->where('EmpStatus', 'A')->where('Fname', $first)->count();
            } catch (\Throwable $e) {
                self::$firstNameCount[$k] = 0;
            }
        }

        return self::$firstNameCount[$k] > 1;
    }

    /** [section, rate %] for a nature of payment and payee (PAN 4th letter P/H = individual / HUF). */
    public static function tdsFor(string $nature, ?string $panOrGstin, float $taxable): array
    {
        $n = config("btc.tds_natures.{$nature}") ?? config('btc.tds_natures.none');
        if (($n['section'] ?? 'none') === 'none') {
            return ['none', 0.0, false];
        }
        $pan = strtoupper(trim((string) $panOrGstin));
        if (strlen($pan) === 15) {
            $pan = substr($pan, 2, 10); // GSTIN carries the PAN at positions 3-12
        }
        $individual = strlen($pan) === 10 && in_array($pan[3], ['P', 'H'], true);
        $rate = (float) ($n['rate'][$individual ? 0 : 1] ?? 0);
        $belowThreshold = $taxable < (float) ($n['threshold'][0] ?? 0);

        return [(string) $n['section'], $rate, $belowThreshold];
    }

    /** BTC-GST-01 (17 Nov 2026): GSTIN format and check digit (15 characters, mod-36 checksum). */
    public static function gstinValid(?string $g): bool
    {
        $g = strtoupper(trim((string) $g));
        if (! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g)) {
            return false;
        }
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $v = strpos($chars, $g[$i]) * ($i % 2 ? 2 : 1);
            $sum += intdiv($v, 36) + $v % 36;
        }

        return $chars[(36 - $sum % 36) % 36] === $g[14];
    }

    /**
     * BTC-TDS-02 (17 Nov 2026): what this vendor has billed in the financial year of the bill (other cases), so the
     * yearly TDS threshold (e.g. contract work Rs 1,00,000 a year) is seen, not only the single bill.
     */
    public static function vendorYearTotal(\App\Models\BtcCase $c): array
    {
        $date = $c->bill_date ?: now();
        $fyStart = $date->month >= 4 ? $date->copy()->startOfYear()->addMonths(3) : $date->copy()->subYear()->startOfYear()->addMonths(3);
        $pan = strlen((string) $c->vendor_gstin) === 15 ? substr(strtoupper($c->vendor_gstin), 2, 10) : null;
        $rows = \App\Models\BtcCase::where('id', '!=', $c->id)->where('company_id', $c->company_id)->whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->whereNotNull('bill_date')->whereBetween('bill_date', [$fyStart->toDateString(), $fyStart->copy()->addYear()->subDay()->toDateString()])
            ->where(function ($q) use ($c, $pan) {
                if ($pan) {
                    $q->orWhereRaw('SUBSTRING(UPPER(vendor_gstin), 3, 10) = ?', [$pan]);
                }
                if ($c->vendor_id) {
                    $q->orWhere('vendor_id', $c->vendor_id);
                }
                if ($c->vendor_name) {
                    $q->orWhere('vendor_name', $c->vendor_name);
                }
            })->get(['taxable_amount', 'bill_amount']);

        return ['fy' => $fyStart->format('Y').'-'.$fyStart->copy()->addYear()->format('y'), 'count' => $rows->count(),
            'total' => round($rows->sum(fn ($r) => (float) ($r->taxable_amount ?: $r->bill_amount)), 2)];
    }
}
