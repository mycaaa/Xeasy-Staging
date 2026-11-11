<?php

namespace App\Console\Commands;

use App\Services\Retention\RecordRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * RET-01: make the archive copy of an archived financial year. Every y{N}_* table of the year is written
 * row by row to compressed JSON-lines files with a manifest (row counts and SHA-256 per file). The data
 * stays in the database and in Xeasy (read-only); nothing is deleted. Re-running replaces the copy.
 */
class ArchiveRecords extends Command
{
    protected $signature = 'records:archive {--company=1} {--year= : YearId; default every archived year} {--apply : write the archive (otherwise list only)}';

    protected $description = 'Archive copy of financial years older than the archive age (records are kept, read-only)';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $years = RecordRetention::years($conn);
        $targets = $this->option('year') ? array_filter($years, fn ($y) => $y['year_id'] === (int) $this->option('year')) : array_filter($years, fn ($y) => $y['status'] === 'archived');
        if (! $targets) {
            $this->info('No archived year to copy.');

            return self::SUCCESS;
        }
        $db = DB::connection($conn);
        $dbName = $db->getDatabaseName();
        $disk = Storage::disk(config('retention.archive_disk', 'local'));
        foreach ($targets as $y) {
            if ($y['status'] !== 'archived') {
                $this->warn("{$y['year']} is {$y['status']}, not archived yet (from {$y['archive_from']}); skipped.");
                continue;
            }
            $tables = array_column($db->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE ? ORDER BY table_name', [$dbName, "y{$y['year_id']}\\_%"]), 't');
            $dir = trim(config('retention.archive_path', 'archive'), '/')."/company{$co}/".str_replace('/', '-', $y['year']);
            $this->line("{$y['year']}: ".count($tables).' tables -> '.$dir.($this->option('apply') ? '' : ' (dry run)'));
            if (! $this->option('apply')) {
                continue;
            }
            $manifest = ['company' => $co, 'year_id' => $y['year_id'], 'year' => $y['year'], 'made_at' => now()->toIso8601String(), 'keep_until' => $y['keep_until'], 'tables' => []];
            foreach ($tables as $t) {
                $tmp = tempnam(sys_get_temp_dir(), 'arc');
                $gz = gzopen($tmp, 'wb6');
                $rows = 0;
                $key = Schema::connection($conn)->getColumnListing($t)[0] ?? null;
                $q = $db->table($t);
                foreach (($key ? $q->orderBy($key) : $q)->cursor() as $r) {
                    gzwrite($gz, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                    $rows++;
                }
                gzclose($gz);
                $path = "{$dir}/{$t}.jsonl.gz";
                $disk->put($path, fopen($tmp, 'rb'));
                $manifest['tables'][$t] = ['rows' => $rows, 'sha256' => hash_file('sha256', $tmp), 'file' => $path];
                @unlink($tmp);
                $this->line(sprintf('  %-40s %9d rows', $t, $rows));
            }
            $disk->put("{$dir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
            $this->ensureLog($conn);
            $db->table('record_archives')->updateOrInsert(['company_id' => $co, 'year_id' => $y['year_id']], [
                'year' => $y['year'], 'path' => $dir, 'tables' => count($tables), 'rows' => array_sum(array_column($manifest['tables'], 'rows')),
                'manifest_sha256' => hash('sha256', json_encode($manifest)), 'keep_until' => $y['keep_until'], 'archived_at' => now(),
            ]);
            $this->info("  archive copy done: ".array_sum(array_column($manifest['tables'], 'rows')).' rows');
        }

        return self::SUCCESS;
    }

    private function ensureLog(string $conn): void
    {
        if (Schema::connection($conn)->hasTable('record_archives')) {
            return;
        }
        Schema::connection($conn)->create('record_archives', function ($t) {
            $t->id();
            $t->unsignedSmallInteger('company_id');
            $t->unsignedSmallInteger('year_id');
            $t->string('year', 20);
            $t->string('path', 255);
            $t->unsignedInteger('tables');
            $t->unsignedBigInteger('rows');
            $t->string('manifest_sha256', 64);
            $t->date('keep_until');
            $t->timestamp('archived_at')->useCurrent();
            $t->unique(['company_id', 'year_id']);
        });
    }
}
