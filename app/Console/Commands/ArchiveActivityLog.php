<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * D-07: activity_log was 56% of the database (1.6 million rows, 1.6 GB) and grew ~180k rows a month.
 *
 * Rows older than --days (default 180) are written to compressed files, one per month
 * (storage/app/archive/activity_log/activity_log_YYYY-MM.jsonl.gz, one JSON row per line), checked,
 * and only then removed from the table. Nothing is lost: the files are kept with the other archives
 * for the 8-year retention period (RET-01) and can be read back with zcat. --apply writes;
 * without it the command only counts.
 */
class ArchiveActivityLog extends Command
{
    protected $signature = 'logs:archive-activity {--days=180} {--apply} {--chunk=5000} {--max=500000 : rows per run}';

    protected $description = 'Move activity_log rows older than N days into monthly compressed files';

    public function handle(): int
    {
        $db = DB::connection('expense');
        $cut = now()->subDays(max(30, (int) $this->option('days')))->startOfMonth()->toDateTimeString();
        $total = (int) $db->table('activity_log')->where('created_at', '<', $cut)->count();
        $this->line("activity_log rows before {$cut}: {$total}");
        if (! $this->option('apply') || ! $total) {
            return self::SUCCESS;
        }
        $disk = Storage::disk('local');
        $dir = 'archive/activity_log';
        $disk->makeDirectory($dir);
        $done = 0;
        $max = (int) $this->option('max');
        while ($done < $max) {
            $rows = $db->table('activity_log')->where('created_at', '<', $cut)->orderBy('id')->limit((int) $this->option('chunk'))->get();
            if ($rows->isEmpty()) {
                break;
            }
            foreach ($rows->groupBy(fn ($r) => substr((string) $r->created_at, 0, 7)) as $month => $set) {
                $path = $disk->path("{$dir}/activity_log_{$month}.jsonl.gz");
                $gz = gzopen($path, 'ab9');
                $bytes = 0;
                foreach ($set as $r) {
                    $bytes += gzwrite($gz, json_encode($r, JSON_UNESCAPED_UNICODE)."\n");
                }
                gzclose($gz);
                if ($bytes <= 0 || ! is_file($path)) {
                    $this->error("Could not write {$path}; nothing removed.");

                    return self::FAILURE;
                }
            }
            $db->table('activity_log')->whereIn('id', $rows->pluck('id'))->delete();
            $done += $rows->count();
        }
        $this->info("Archived and removed {$done} rows (files in storage/app/{$dir}).");

        return self::SUCCESS;
    }
}
