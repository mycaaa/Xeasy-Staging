<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * PERF-10 (13 Nov 2026): the slowest screens from storage/logs/slow-requests.log (written by MeasureRequestTime).
 *
 *   php artisan perf:slow-pages              (last 7 days, top 25 by total time spent waiting)
 *   php artisan perf:slow-pages --days=1 --sql   (also the slowest query of each)
 */
class PerfSlowPages extends Command
{
    protected $signature = 'perf:slow-pages {--days=7} {--top=25} {--sql : show the slowest query of each screen}';

    protected $description = 'List the slowest pages and data requests from the slow-request log';

    public function handle(): int
    {
        $file = storage_path('logs/slow-requests.log');
        if (! is_file($file)) {
            $this->info('No slow requests recorded yet (storage/logs/slow-requests.log is created by the first request over '.config('app.perf_slow_ms', 1500).' ms).');

            return self::SUCCESS;
        }
        $since = now()->subDays((int) $this->option('days'))->toDateTimeString();
        $by = [];
        $fh = fopen($file, 'r');
        while (($line = fgets($fh)) !== false) {
            $r = json_decode($line, true);
            if (! $r || ($r['at'] ?? '') < $since) {
                continue;
            }
            $key = $r['method'].' '.($r['route'] ?: preg_replace('#/\d+#', '/{id}', $r['path']));
            $b = $by[$key] ??= ['n' => 0, 'sum' => 0, 'max' => 0, 'db' => 0, 'q' => 0, 'sql' => null, 'sqlms' => 0];
            $b['n']++;
            $b['sum'] += $r['ms'];
            $b['max'] = max($b['max'], $r['ms']);
            $b['db'] += $r['db_ms'];
            $b['q'] = max($b['q'], $r['queries']);
            foreach ($r['slowest'] ?? [] as [$qms, $conn, $sql]) {
                if ($qms > $b['sqlms']) {
                    $b['sqlms'] = $qms;
                    $b['sql'] = "{$qms} ms {$conn}: {$sql}";
                }
            }
            $by[$key] = $b;
        }
        fclose($fh);
        uasort($by, fn ($a, $b) => $b['sum'] <=> $a['sum']);
        $rows = [];
        foreach (array_slice($by, 0, (int) $this->option('top'), true) as $k => $b) {
            $rows[] = [$k, $b['n'], (int) ($b['sum'] / $b['n']), $b['max'], (int) (100 * $b['db'] / max(1, $b['sum'])).'%', $b['q']];
            if ($this->option('sql') && $b['sql']) {
                $rows[] = ['   '.mb_substr($b['sql'], 0, 150), '', '', '', '', ''];
            }
        }
        $this->table(['Screen / request', 'Slow hits', 'Avg ms', 'Max ms', 'Database share', 'Max queries'], $rows);

        return self::SUCCESS;
    }
}
