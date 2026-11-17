<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-EXC-01 (17 Nov 2026, BTC requirement item 12 "Validation & Exception Queue"): every check that should stop
 * or question a BTC case, worked out per case. Finance clears an exception with a remark (btc_exception_reviews);
 * a cleared exception stays listed as reviewed, with who and why.
 */
class BtcExceptions
{
    public const TYPES = [
        'duplicate' => 'Possible duplicate bill',
        'paid_twice' => 'Also in the employee\'s own expense claim',
        'policy' => 'Hotel rate above the employee\'s eligibility',
        'attendance' => 'Employee on leave / absent on the service dates',
        'mismatch' => 'Bill does not match the request',
        'gstin' => 'GSTIN missing or not valid',
        'documents' => 'Original bill / boarding pass overdue',
        'advance' => 'Advance not settled',
        'tds' => 'Vendor\'s yearly total crosses the TDS threshold, no TDS',
        'hold' => 'On financial hold',
    ];

    private const LEAVE = ['A', 'CL', 'EL', 'SL', 'PL', 'ML', 'LWP', 'FL'];

    /** @return array<int, array{type:string,label:string,detail:string}> */
    public static function forCase(BtcCase $c): array
    {
        $out = [];
        $add = function (string $type, string $detail) use (&$out) {
            $out[] = ['type' => $type, 'label' => self::TYPES[$type], 'detail' => $detail];
        };
        $inr = fn ($n) => 'Rs '.number_format((float) $n);
        $safe = function (callable $f) {
            try {
                return $f();
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        };

        if ($c->bill_no || $c->bill_amount) {
            $d = BtcWorkflow::duplicates($c);
            if ($d->isNotEmpty()) {
                $add('duplicate', 'Same bill / amount and date as '.$d->pluck('case_no')->implode(', '));
            }
        }
        if ($ov = $safe(fn () => BtcWorkflow::claimOverlaps($c))) {
            $add('paid_twice', collect($ov)->map(fn ($o) => $o['type'].' '.$o['date'].' '.$inr($o['amount']).' ('.$o['status'].', ExpId '.$o['exp_id'].')')->implode('; '));
        }
        if ($p = $safe(fn () => self::policy($c))) {
            $add('policy', $p);
        }
        if ($a = $safe(fn () => self::attendance($c))) {
            $add('attendance', $a);
        }
        // the bill against what was approved / booked
        $basis = (float) ($c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount);
        $pct = (int) config('btc.mismatch_pct', 10);
        if ($basis > 0 && (float) $c->bill_amount > $basis * (1 + $pct / 100)) {
            $add('mismatch', 'Bill '.$inr($c->bill_amount).' against '.($c->booking_amount ? 'booked' : ($c->quotation_amount ? 'quoted' : 'estimated')).' '.$inr($basis).' (+'.round(((float) $c->bill_amount / $basis - 1) * 100).'%)');
        }
        if (Schema::hasTable('btc_invoices') && ($n = DB::table('btc_invoices')->where('suggested_case_id', $c->id)->where('match_status', 'mismatch')->value('mismatch_note'))) {
            $add('mismatch', 'Nova invoice marked as not matching: '.$n);
        }
        if ((float) $c->gst_amount > 0 && ! $c->vendor_gstin) {
            $add('gstin', 'GST '.$inr($c->gst_amount).' on the bill but no vendor GSTIN');
        } elseif ($c->vendor_gstin && ! BtcLedger::gstinValid($c->vendor_gstin)) {
            $add('gstin', $c->vendor_gstin.' fails the GSTIN format / check digit');
        }
        $days = (int) config('btc.follow_up_days', 7);
        if ($c->bill_submitted_at && $c->bill_submitted_at->lt(now()->subDays($days)) && ($pd = $c->pendingDocuments())) {
            $add('documents', implode(', ', $pd).' not received '.$c->bill_submitted_at->diffInDays(now()).' days after the bill');
        }
        $f = $c->advanceFigures();
        $settle = (int) config('btc.advance_settle_days', 30);
        if ($c->advance_paid_on && ! $c->bill_submitted_at && $f['balance'] > 0.5 && $c->advance_paid_on->lt(now()->subDays($settle))) {
            $add('advance', $inr($f['balance']).' paid '.$c->advance_paid_on->format('d-m-Y').', no bill after '.$c->advance_paid_on->diffInDays(now()).' days');
        }
        if ($c->finance_at && ($c->tds_section === 'none' || ! $c->tds_section) && ($t = $safe(fn () => self::tds($c)))) {
            $add('tds', $t);
        }
        if ($c->on_hold ?? false) {
            $add('hold', (string) $c->hold_reason);
        }

        return $out;
    }

    /** Hotel: rate per night against the employee's lodging eligibility for the city's tier (unknown city = C). */
    private static function policy(BtcCase $c): ?string
    {
        if ($c->category !== 'hotel' || ! $c->employee_id) {
            return null;
        }
        $amount = (float) ($c->taxable_amount ?: $c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount);
        if ($amount <= 0) {
            return null;
        }
        $nights = ($c->service_from && $c->service_to) ? max(1, (int) $c->service_from->diffInDays($c->service_to)) : 1;
        $caps = app(\App\Services\ClaimRules\EligibilityCaps::class);
        $tier = $c->location ? ($caps->cityTier('expense', ['city_category_select' => '0', 'other_city_name' => $c->location]) ?: 'C') : 'C';
        $limit = $caps->lodgingLimit((int) $c->employee_id, $tier);
        $rate = $amount / $nights;
        if ($limit <= 0 || $rate <= $limit + 0.5) {
            return null;
        }

        return 'Rs '.number_format($rate).' a night for '.$nights.' night(s) at '.($c->location ?: 'unknown city').' (tier '.$tier.'), eligibility Rs '.number_format($limit);
    }

    /** HR attendance on the service dates: leave or absent while the company paid for the travel / stay. */
    private static function attendance(BtcCase $c): ?string
    {
        $from = $c->service_from ?: $c->travel_date;
        $to = $c->service_to ?: $from;
        if (! $from || ! $c->employee_id || $from->gt(now())) {
            return null;
        }
        $rows = DB::connection('hrims')->table('hrm_employee_attendance')->where('EmployeeID', $c->employee_id)
            ->whereBetween('AttDate', [$from->toDateString(), $to->toDateString()])->whereIn('AttValue', self::LEAVE)
            ->orderBy('AttDate')->limit(10)->get(['AttDate', 'AttValue']);

        return $rows->isEmpty() ? null : $rows->map(fn ($r) => substr((string) $r->AttDate, 0, 10).' '.$r->AttValue)->implode(', ');
    }

    private static function tds(BtcCase $c): ?string
    {
        $nature = config('btc.tds_nature_by_category.'.$c->category);
        $rule = $nature ? config("btc.tds_natures.{$nature}") : null;
        if (! $rule || ($rule['section'] ?? 'none') === 'none') {
            return null;
        }
        $yt = BtcLedger::vendorYearTotal($c);
        $total = $yt['total'] + (float) ($c->taxable_amount ?: $c->bill_amount);
        if ($total <= (float) ($rule['threshold'][1] ?? PHP_FLOAT_MAX) && (float) ($c->taxable_amount ?: $c->bill_amount) <= (float) ($rule['threshold'][0] ?? PHP_FLOAT_MAX)) {
            return null;
        }

        return 'Vendor total FY '.$yt['fy'].' Rs '.number_format($total).' ('.($yt['count'] + 1).' bills), '.$rule['section'].' threshold Rs '.number_format($rule['threshold'][0]).' / Rs '.number_format($rule['threshold'][1]).' a year';
    }

    /** The queue: open cases with at least one exception, newest first; reviewed ones carry the review. */
    public static function queue(\Illuminate\Database\Eloquent\Builder $cases): Collection
    {
        $list = $cases->whereNotIn('status', ['draft', 'cancelled', 'rejected', 'closed'])->orderByDesc('id')->limit(500)->get();
        $reviews = Schema::hasTable('btc_exception_reviews') ? DB::table('btc_exception_reviews')->whereIn('btc_case_id', $list->pluck('id'))->get()->groupBy('btc_case_id') : collect();
        $rows = collect();
        foreach ($list as $c) {
            foreach (self::forCase($c) as $e) {
                $r = optional($reviews->get($c->id))->firstWhere('type', $e['type']);
                $rows->push($e + ['case' => $c, 'review' => $r]);
            }
        }

        return $rows;
    }
}
