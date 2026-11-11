<?php

namespace App\Services\Advance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * FX-53: one place for foreign-currency advance rules.
 * `amount` on an activity is always INR; for a foreign currency it is fx_amount × fx_rate.
 */
class ForeignCurrencyAdvance
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = (bool) config('foreign_currency.enabled', true)
                    && Schema::hasColumn('advance_request_activities', 'currency');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public static function isForeign(?string $currency): bool
    {
        $c = strtoupper(trim((string) $currency));

        return $c !== '' && $c !== 'INR';
    }

    /** Latest rate Finance entered for the currency within the validity window, or null. */
    public static function currentRate(string $currency, ?string $onDate = null): ?object
    {
        $onDate = $onDate ?: now('Asia/Kolkata')->toDateString();
        $from = \Carbon\Carbon::parse($onDate)->subDays((int) config('foreign_currency.rate_valid_days', 7))->toDateString();

        return DB::table('advance_fx_rates')->where('currency', strtoupper($currency))
            ->whereBetween('rate_date', [$from, $onDate])->orderByDesc('rate_date')->first();
    }

    /**
     * Fill INR amount from the foreign amount and rate. Called on every save of an activity.
     * Throws when a foreign currency is chosen without a usable amount or rate.
     */
    public static function normalize(array $a): array
    {
        if (! self::isForeign($a['currency'] ?? null)) {
            if (array_key_exists('currency', $a)) {
                $a['currency'] = 'INR';
                $a['fx_amount'] = $a['fx_rate'] = $a['fx_rate_date'] = null;
            }

            return $a;
        }
        $cur = strtoupper(trim((string) $a['currency']));
        if (! in_array($cur, (array) config('foreign_currency.currencies', []), true)) {
            throw new RuntimeException("Currency {$cur} is not allowed for advances");
        }
        $fx = (float) ($a['fx_amount'] ?? 0);
        if ($fx <= 0) {
            throw new RuntimeException("Enter the amount in {$cur}");
        }
        $rate = (float) ($a['fx_rate'] ?? 0);
        if ($rate <= 0) {
            $r = self::currentRate($cur);
            if (! $r) {
                throw new RuntimeException("No {$cur} rate set by Finance in the last ".config('foreign_currency.rate_valid_days', 7).' days; enter the rate');
            }
            $rate = (float) $r->rate;
            $a['fx_rate_date'] = $r->rate_date;
        }
        $a['currency'] = $cur;
        $a['fx_amount'] = round($fx, 2);
        $a['fx_rate'] = round($rate, 4);
        $a['fx_rate_date'] = $a['fx_rate_date'] ?? now('Asia/Kolkata')->toDateString();
        $a['amount'] = round($fx * $rate, 2);

        return $a;
    }

    /** INR to approve when Finance pays at the actual rate; checks the tolerance. */
    public static function paidInr(object $activity, float $paidRate): float
    {
        $inr = round((float) $activity->fx_amount * $paidRate, 2);
        $cap = (float) $activity->amount * (1 + (float) config('foreign_currency.paid_rate_tolerance_pct', 5) / 100);
        if ($inr > round($cap, 2)) {
            throw new RuntimeException("At {$paidRate} the INR amount {$inr} is more than "
                .config('foreign_currency.paid_rate_tolerance_pct', 5).'% above the requested '.$activity->amount);
        }

        return $inr;
    }

    public static function label(object|array $a): ?string
    {
        $a = (object) $a;
        if (! self::isForeign($a->currency ?? null)) {
            return null;
        }
        $rate = $a->fx_paid_rate ?? null ?: $a->fx_rate;

        return sprintf('%s %s @ %s', $a->currency, number_format((float) $a->fx_amount, 2), rtrim(rtrim(number_format((float) $rate, 4, '.', ''), '0'), '.'));
    }
}
