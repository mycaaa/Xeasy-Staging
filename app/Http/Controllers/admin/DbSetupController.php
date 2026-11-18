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

    /**
     * @return array<int,array{name:string,file:bool,ran:bool,missing:string[]}>
     *
     * "ran" comes from the migrations table. On xeasy that table was copied from another database, so it lists
     * changes whose tables are not there (btc_cases ...). So for every change marked as run, the tables it creates
     * and the columns it adds behind a hasColumn() guard are checked too; anything not there is listed in "missing"
     * and the change is run again (those steps are guarded, so a second run only adds what is missing).
     */
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

        return array_map(function ($n) use ($ran) {
            $path = database_path("migrations/{$n}.php");
            $file = is_file($path);
            $isRan = isset($ran[$n]);

            return ['name' => $n, 'file' => $file, 'ran' => $isRan, 'missing' => ($file && $isRan) ? self::missingObjects($path) : []];
        }, $names);
    }

    /** Tables / guarded columns a migration file creates that are not in the main database. */
    private static function missingObjects(string $path): array
    {
        $src = (string) @file_get_contents($path);
        // changes for other databases only (VVNR, Nursery) are not checked here
        if (preg_match("/connection\\(\\s*'(expense_vvnr|expense_nursery|hrims)'/", $src) && ! preg_match("/connection\\(\\s*'(expense|mysql)'/", $src)
            && ! preg_match('/Schema::(create|table|hasTable|hasColumn)\\(/', $src) && ! preg_match('/connection\\(\\s*\\$/', $src)) {
            return [];
        }
        $missing = [];
        try {
            $s = Schema::connection('expense');
            preg_match_all("/create\\(\\s*'([A-Za-z0-9_]+)'/", $src, $m);
            foreach (array_unique($m[1]) as $t) {
                if (! $s->hasTable($t)) {
                    $missing[] = $t;
                }
            }
            preg_match_all("/hasColumn\\(\\s*'([A-Za-z0-9_]+)'\\s*,\\s*'([A-Za-z0-9_]+)'/", $src, $m, PREG_SET_ORDER);
            foreach ($m as [, $t, $c]) {
                if ($s->hasTable($t) && ! $s->hasColumn($t, $c)) {
                    $missing[] = "{$t}.{$c}";
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return array_values(array_unique($missing));
    }

    public function index(Request $request)
    {
        $this->guard();
        if ($request->query('diag') === 'company') {
            return response()->json(self::diagnose((string) $request->query('conn', 'expense_vvnr')), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        $rows = self::status();

        return view('admin.db-setup', [
            'rows' => $rows,
            'pending' => count(array_filter($rows, fn ($r) => $r['file'] && (! $r['ran'] || $r['missing']))),
            'results' => session('db_setup_results', []),
        ]);
    }

    /**
     * DIAG-01 (18 Nov 2026): read-only check for a company database (VVNR / Nursery) - which year tables exist, their
     * columns, and the latest dashboard / claim errors in the log - so a screen that shows blanks for one company can
     * be traced without shell access. Super Admin only (route), no writes.
     */
    private static function diagnose(string $conn): array
    {
        abort_unless(in_array($conn, ['expense', 'expense_vvnr', 'expense_nursery'], true), 404);
        $out = ['connection' => $conn, 'session' => ['company_id' => session('company_id'), 'year_id' => session('year_id'), 'expense_connection' => session('expense_connection')]];
        try {
            $db = DB::connection($conn);
            $out['database'] = $db->getDatabaseName();
            $out['years'] = $db->table('financialyear')->orderByDesc('YearId')->limit(4)->get(['YearId', 'FromDate', 'Todate']);
            $tables = collect($db->select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0]);
            foreach ($out['years'] as $y) {
                foreach (['expenseclaims', 'monthexpensefinal'] as $t) {
                    $name = "y{$y->YearId}_{$t}";
                    $out['tables'][$name] = $tables->contains($name) ? Schema::connection($conn)->getColumnListing($name) : 'MISSING';
                }
            }
            $out['tables']['claimtype'] = $tables->contains('claimtype') ? 'ok' : 'MISSING';
        } catch (\Throwable $e) {
            $out['error'] = mb_substr($e->getMessage(), 0, 400);
        }
        $log = storage_path('logs/laravel.log');
        $lines = [];
        if (is_file($log)) {
            $fh = fopen($log, 'r');
            fseek($fh, max(0, filesize($log) - 3000000));
            while (($l = fgets($fh)) !== false) {
                if (preg_match('/Dashboard data error|Team (all )?expenses|running km|teamExp|runKm|SQLSTATE/i', $l)) {
                    $lines[] = mb_substr(trim($l), 0, 500);
                }
            }
            fclose($fh);
        }
        $out['log'] = array_slice($lines, -25);

        return $out;
    }

    public function run(Request $request)
    {
        $this->guard();
        $results = [];
        foreach (self::status() as $r) {
            if (! $r['file'] || ($r['ran'] && ! $r['missing'])) {
                continue;
            }
            try {
                if ($r['ran']) {
                    // recorded as run but its tables / columns are not here: run it again (its steps are guarded)
                    DB::table('migrations')->where('migration', $r['name'])->delete();
                }
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
