<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * DB-SETUP-01 (16 Nov 2026, "16 Nov" tab #1 and #2): xeasy takes new code from git automatically but never runs
 * the database changes that come with it, so pages built on new tables (BTC submit, boarding passes ...) answered
 * "Server Error". This page shows a Super Admin which of the database changes listed in deploy/migrations.txt are
 * missing on this server and runs only those, one by one, each with --path (the same thing deploy/deploy.sh does).
 * Nothing else in database/migrations runs. Off when APP_ENV is production unless DB_SETUP_PAGE=true.
 */
class DbSetupController extends Controller
{
    private function guard(): void
    {
        abort_if(app()->environment('production') && ! filter_var(env('DB_SETUP_PAGE', false), FILTER_VALIDATE_BOOLEAN), 404);
    }

    /** @return array<int,array{name:string,file:bool,ran:bool}> */
    public static function status(): array
    {
        $list = base_path('deploy/migrations.txt');
        $names = [];
        foreach (is_file($list) ? file($list, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] !== '#') {
                $names[] = preg_replace('/\.php$/', '', $line);
            }
        }
        $ran = [];
        try {
            if (Schema::hasTable('migrations')) {
                $ran = array_flip(DB::table('migrations')->whereIn('migration', $names ?: ['-'])->pluck('migration')->all());
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return array_map(fn ($n) => ['name' => $n, 'file' => is_file(database_path("migrations/{$n}.php")), 'ran' => isset($ran[$n])], $names);
    }

    public function index()
    {
        $this->guard();
        $rows = self::status();

        return view('admin.db-setup', [
            'rows' => $rows,
            'pending' => count(array_filter($rows, fn ($r) => ! $r['ran'] && $r['file'])),
            'results' => session('db_setup_results', []),
        ]);
    }

    public function run(Request $request)
    {
        $this->guard();
        $results = [];
        foreach (self::status() as $r) {
            if ($r['ran'] || ! $r['file']) {
                continue;
            }
            try {
                $code = Artisan::call('migrate', ['--path' => "database/migrations/{$r['name']}.php", '--force' => true]);
                $out = trim(Artisan::output());
                $results[] = ['name' => $r['name'], 'ok' => $code === 0, 'output' => mb_substr($out, -600)];
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['name' => $r['name'], 'ok' => false, 'output' => mb_substr($e->getMessage(), 0, 600)];
            }
            Log::info('db-setup: migration run', ['migration' => $r['name'], 'ok' => end($results)['ok'], 'by' => auth()->id()]);
            if (! end($results)['ok']) {
                break; // stop at the first failure, as deploy.sh does; later changes may depend on it
            }
        }
        foreach (['route:clear', 'view:clear', 'config:clear'] as $c) {
            try {
                Artisan::call($c);
            } catch (\Throwable $e) {
            }
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        return redirect()->route('admin.db-setup')->with('db_setup_results', $results ?: [['name' => '-', 'ok' => true, 'output' => 'Nothing to run: the database is up to date.']]);
    }
}
