<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-09 (11 Nov 2026): "no date" is stored as NULL now, not 0000-00-00. The mobile app in the stores was built on the
 * old answers, where the old date columns (PascalCase names with "Date": BillDate, CrDate, Fin_PayDate, VerifDate,
 * DateOfSubmit, ...) came back as "0000-00-00". For the mobile API's legacy answers this puts "0000-00-00" back in
 * those keys when they are null, so the app sees exactly what it saw before. New snake_case columns (payment_*_date,
 * ff_*) were always null and are left alone. The v2 envelope (X-Xeasy-Response: v2, LV-U03) gets real nulls.
 */
class LegacyZeroDates
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $v2 = strtolower((string) ($request->header('X-Xeasy-Response') ?: $request->query('response', ''))) === 'v2';
        if ($v2 || ! $response instanceof JsonResponse || ! config('claim_rules.legacy_zero_dates', true)) {
            return $response;
        }
        $data = $response->getData(true);
        if (is_array($data)) {
            $response->setData(self::fill($data));
        }

        return $response;
    }

    public static function fill(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = self::fill($v);
            } elseif ($v === null && is_string($k) && preg_match('/^(Date|[A-Z][A-Za-z0-9_]*Date)/', $k)) {
                $a[$k] = '0000-00-00';
            }
        }

        return $a;
    }
}
