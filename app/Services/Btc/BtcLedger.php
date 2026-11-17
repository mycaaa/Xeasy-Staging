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

    /**
     * BTC-FIN-02 (17 Nov 2026, requirement item 11): the Dr/Cr lines of a case for Focus (same as the JV export), with
     * the accounting dimensions. Used for the on-screen preview before posting and by the Focus export.
     * Each line: [ledger, sub_ledger, debit, credit, tds_section, cost_centre].
     */
    public static function journal(\App\Models\BtcCase $c): array
    {
        $f = config('btc.focus');
        $L = [];
        $line = function ($ledger, $sub, $dr, $cr) use (&$L, $c) {
            $L[] = ['ledger' => (string) $ledger, 'sub' => (string) $sub, 'dr' => round((float) $dr, 2), 'cr' => round((float) $cr, 2),
                'tds' => $c->tds_section && $c->tds_section !== 'none' ? $c->tds_section : '', 'cost_centre' => (string) $c->cost_centre];
        };
        $emp = self::employeeLedger($c->emp_code, $c->emp_name);
        $bill = (float) $c->bill_amount;
        // 1. bill through Employee-BTC to the vendor (TDS kept back)
        $line($emp, $c->emp_code.' '.$c->emp_name, $bill, 0);
        $line($c->vendor_name ?: 'Vendor', $c->vendor_gstin, 0, $bill - (float) $c->tds_amount);
        if ((float) $c->tds_amount > 0) {
            $line($f['tds_ledger'], $c->tds_section, 0, (float) $c->tds_amount);
        }
        // 2. expense against Employee-BTC (GST input separately)
        $line($c->expense_ledger ?: config("btc.focus.expense_ledgers.{$c->category}"), $c->department_name, $bill - (float) $c->gst_amount, 0);
        if ((float) $c->gst_amount > 0) {
            $line($f['gst_input_ledger'], $c->vendor_gstin, (float) $c->gst_amount, 0);
        }
        $line($emp, $c->emp_code.' '.$c->emp_name, 0, $bill);
        // 3. advance already paid set off against the vendor
        if ((float) $c->advance_adjusted > 0) {
            $line($c->vendor_name ?: 'Vendor', $c->vendor_gstin, (float) $c->advance_adjusted, 0);
            $line($f['vendor_advance_ledger'], $c->emp_code, 0, (float) $c->advance_adjusted);
        }

        return $L;
    }

    /** Dimension lists for the Finance form (HR masters) and the requester's own values as defaults. */
    public static function dimensions(\App\Models\BtcCase $c): array
    {
        $h = \Illuminate\Support\Facades\DB::connection('hrims');
        $opt = function ($table, $col) use ($h) {
            try {
                return $h->table($table)->when(\Illuminate\Support\Facades\Schema::connection('hrims')->hasColumn($table, 'is_active'), fn ($q) => $q->where('is_active', '1'))->orderBy($col)->pluck($col)->filter()->unique()->values()->all();
            } catch (\Throwable $e) {
                return [];
            }
        };
        $g = null;
        try {
            $g = $h->table('hrm_employee_general as g')->leftJoin('core_functions as f', 'f.id', '=', 'g.EmpFunction')->leftJoin('core_verticals as v', 'v.id', '=', 'g.EmpVertical')
                ->leftJoin('core_regions as r', 'r.id', '=', 'g.RegionId')->where('g.EmployeeID', $c->employee_id)->first(['f.function_name', 'v.vertical_name', 'r.region_name', 'g.CostCenter']);
        } catch (\Throwable $e) {
        }

        return [
            'options' => ['function' => $opt('core_functions', 'function_name'), 'vertical' => $opt('core_verticals', 'vertical_name'),
                'region' => $opt('core_regions', 'region_name'), 'crop' => $opt('core_crops', 'crop_name')],
            'defaults' => ['function' => $g->function_name ?? null, 'vertical' => $g->vertical_name ?? null, 'region' => $g->region_name ?? null],
        ];
    }
}
