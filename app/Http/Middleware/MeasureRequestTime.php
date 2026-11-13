<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PERF-10 (13 Nov 2026): measures every page and data request, so the slow screens can be found from real use
 * instead of guesses.
 *  - Adds a Server-Timing header (total, database time, number of queries); the browser's Network tab shows it
 *    under "Timing".
 *  - Writes requests slower than PERF_SLOW_MS (default 1500 ms) to storage/logs/slow-requests.log, one JSON line
 *    with the path, route, time, query count and the slowest queries (SQL text only, never the values).
 *  - "php artisan perf:slow-pages" lists the slowest screens from that file.
 */
class MeasureRequestTime
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('app.perf_measure', true)) {
            return $next($request);
        }
        $start = microtime(true);
        $queries = 0;
        $dbMs = 0.0;
        $slowest = [];
        DB::listen(function (QueryExecuted $q) use (&$queries, &$dbMs, &$slowest) {
            $queries++;
            $dbMs += $q->time;
            if ($q->time >= 50) {
                $slowest[] = [round($q->time), $q->connectionName, mb_substr(preg_replace('/\s+/', ' ', $q->sql), 0, 300)];
            }
        });

        $response = $next($request);

        $ms = (microtime(true) - $start) * 1000;
        try {
            $response->headers->set('Server-Timing', sprintf('app;dur=%.0f, db;dur=%.0f;desc="%d queries"', $ms, $dbMs, $queries));
        } catch (\Throwable $e) {
            // streamed / binary responses
        }
        if ($ms >= (int) config('app.perf_slow_ms', 1500)) {
            usort($slowest, fn ($a, $b) => $b[0] <=> $a[0]);
            $line = json_encode([
                'at' => now()->toDateTimeString(),
                'ms' => (int) $ms,
                'db_ms' => (int) $dbMs,
                'queries' => $queries,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route' => $request->route()?->getName(),
                'user' => auth()->id(),
                'company' => session('company_id'),
                'slowest' => array_slice($slowest, 0, 5),
            ], JSON_UNESCAPED_SLASHES);
            try {
                file_put_contents(storage_path('logs/slow-requests.log'), $line."\n", FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
            }
        }

        return $response;
    }
}
