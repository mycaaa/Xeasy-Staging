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
}
