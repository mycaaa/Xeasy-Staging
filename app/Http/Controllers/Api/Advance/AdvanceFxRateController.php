<?php

namespace App\Http\Controllers\Api\Advance;

use App\Http\Controllers\Controller;
use App\Services\Advance\ForeignCurrencyAdvance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * FX-53: exchange rates Finance sets for foreign-currency advances.
 *   GET  /api/advance/fx/rates                 currencies, current rate per currency, last 30 entries
 *   GET  /api/advance/fx/quote?currency=USD&fx_amount=500[&fx_rate=]   INR equivalent
 *   POST /api/advance/fx/rates {currency, rate, rate_date?, source?}  (Finance)
 */
class AdvanceFxRateController extends Controller
{
    public function index()
    {
        if (! ForeignCurrencyAdvance::ready()) {
            return response()->json(['success' => false, 'message' => 'Foreign-currency advances are not set up (run migration 000010)'], 503);
        }
        $current = [];
        foreach ((array) config('foreign_currency.currencies') as $c) {
            $r = ForeignCurrencyAdvance::currentRate($c);
            $current[] = ['currency' => $c, 'rate' => $r ? (float) $r->rate : null, 'rate_date' => $r->rate_date ?? null];
        }

        return response()->json(['success' => true, 'data' => [
            'currencies' => $current,
            'rate_valid_days' => (int) config('foreign_currency.rate_valid_days'),
            'history' => DB::table('advance_fx_rates')->orderByDesc('rate_date')->orderBy('currency')->limit(30)->get(),
        ]]);
    }

    public function quote(Request $request)
    {
        try {
            $n = ForeignCurrencyAdvance::normalize($request->only(['currency', 'fx_amount', 'fx_rate']));

            return response()->json(['success' => true, 'data' => $n + ['label' => ForeignCurrencyAdvance::label($n)]]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'currency' => 'required|string|size:3|in:'.implode(',', (array) config('foreign_currency.currencies')),
            'rate' => 'required|numeric|min:0.0001|max:100000',
            'rate_date' => 'nullable|date|before_or_equal:today',
            'source' => 'nullable|string|max:100',
        ]);
        $userId = (int) (Auth::guard('web')->id() ?: $request->input('user_id', 0));
        $date = $v['rate_date'] ?? now('Asia/Kolkata')->toDateString();
        DB::table('advance_fx_rates')->updateOrInsert(
            ['currency' => strtoupper($v['currency']), 'rate_date' => $date],
            ['rate' => round((float) $v['rate'], 4), 'source' => $v['source'] ?? null, 'set_by' => $userId ?: null, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['success' => true, 'message' => 'Rate saved', 'data' => ForeignCurrencyAdvance::currentRate($v['currency'], $date)]);
    }
}
