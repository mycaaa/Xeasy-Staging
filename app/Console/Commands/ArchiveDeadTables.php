<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * D-11 (10 Nov 2026): dead, demo and empty tables in the expense schema.
 *
 *   db:archive-dead-tables                 list the candidates and why (no change)
 *   db:archive-dead-tables --apply         copy each one to storage/app/db_archive/{schema}/{table}.sql.gz, then drop it
 *
 * A table is a candidate when no file of this app (app, config, routes, database, resources) names it and it is
 * (11 Nov: nor any installed package under vendor/) and it is either a "_old" copy, an un-prefixed demo temp_punch_ table, or empty. Year tables (y{N}_...) are never taken:
 * they are created by the year roll-over and read by year number. --only=name,name limits the run.
 */
class ArchiveDeadTables extends Command
{
    protected $signature = 'db:archive-dead-tables {--company=1} {--apply} {--only= : comma list of tables}';

    protected $description = 'Archive and drop dead, demo and empty tables (D-11)';

    public function handle(): int
    {
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection((int) $this->option('company'));
        $db = DB::connection($conn);
        $schema = $db->getDatabaseName();
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $code = $this->codeText();
        $plan = [];
        foreach ($db->select('SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$schema, 'BASE TABLE']) as $r) {
            $t = $r->t;
            if (($only && ! in_array($t, $only, true)) || preg_match('/^y\d+_/', $t) || in_array($t, ['migrations', 'jobs', 'failed_jobs', 'cache', 'cache_locks', 'sessions', 'job_batches', 'password_reset_tokens'], true)) {
                continue;
            }
            if (preg_match('/\b'.preg_quote($t, '/').'\b/i', $code) || $this->namedInPackages($t)) {
                continue; // named somewhere in the app or in an installed package (e.g. laravel-sent-emails)
            }
            $rows = (int) $db->selectOne("SELECT COUNT(*) n FROM `{$t}`")->n;
            $why = match (true) {
                (bool) preg_match('/_old\d*$|_bkp|_backup/i', $t) => 'old copy',
                (bool) preg_match('/^temp_punch_/i', $t) => 'demo temp table (no year prefix)',
                $rows === 0 => 'empty',
                default => null,
            };
            if ($why) {
                $plan[] = [$t, $rows, $why];
            }
        }
        $this->table(['Table', 'Rows', 'Why'], $plan);
        if (! $this->option('apply')) {
            $this->line(count($plan).' candidates. Dry run: add --apply to archive and drop them.');

            return self::SUCCESS;
        }
        $dir = storage_path("app/db_archive/{$schema}");
        File::ensureDirectoryExists($dir);
        foreach ($plan as [$t]) {
            $file = "{$dir}/{$t}_".now()->format('Ymd_His').'.sql.gz';
            $gz = gzopen($file, 'w9');
            $create = (array) $db->selectOne("SHOW CREATE TABLE `{$t}`");
            gzwrite($gz, "-- D-11 archive of {$schema}.{$t}, ".now()->toDateTimeString()."\n".end($create).";\n");
            $pdo = $db->getPdo();
            foreach ($db->table($t)->cursor() as $row) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                gzwrite($gz, "INSERT INTO `{$t}` VALUES (".implode(',', $vals).");\n");
            }
            gzclose($gz);
            $db->statement("DROP TABLE `{$t}`");
            $this->line("archived to {$file} and dropped {$t}");
        }

        return self::SUCCESS;
    }

    /** A package model or migration under vendor/ names the table (11 Nov 2026: sent_emails_attachments was taken as empty). */
    private function namedInPackages(string $table): bool
    {
        $out = [];
        exec('grep -rlqw --include=*.php '.escapeshellarg($table).' '.escapeshellarg(base_path('vendor')).' 2>/dev/null', $out, $rc);

        return $rc === 0;
    }

    /** All PHP and Blade text of the app, to see whether a table is named anywhere. */
    private function codeText(): string
    {
        $out = '';
        foreach (['app', 'config', 'routes', 'database', 'resources/views'] as $d) {
            foreach (File::allFiles(base_path($d)) as $f) {
                if (in_array($f->getExtension(), ['php', 'sql'], true)) {
                    $out .= ' '.File::get($f->getPathname());
                }
            }
        }
        // this command names table patterns, not tables
        return str_replace(File::get(__FILE__), '', $out);
    }
}
