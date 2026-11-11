<?php

use App\Services\Imprest\ImprestReleaseGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UAT E-01: stop a double click / two Finance users from raising two auto
 * advances for one missing-imprest release.
 *  - imprest_auto_advances: one row per imprest request that raised a system
 *    auto advance; imprest_request_id is UNIQUE, so a request can raise only one.
 *  - imprest_requests: UNIQUE (employee_id, grade), so two concurrent releases
 *    for an employee without a request row cannot both insert one.
 *
 * Additive and idempotent. If imprest_requests already holds duplicate
 * (employee_id, grade) rows on a connection, the unique key is NOT added there:
 * the duplicates are reported (request ids and counts, console + log) and the
 * migration carries on; the row lock in the controller still serialises
 * releases. Resolve the duplicates and run the migration again to add the key.
 *
 * The `expense` DB has no `migrations` table, so run it on its own:
 *   php artisan migrate --path=database/migrations/2026_11_05_000006_add_imprest_release_guards.php
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new', 'expense_nursery'];

    public function up(): void
    {
        foreach ($this->imprestConnections() as $conn) {
            try {
                $schema = Schema::connection($conn);
                if (! $schema->hasTable(ImprestReleaseGuard::LINK_TABLE)) {
                    $schema->create(ImprestReleaseGuard::LINK_TABLE, function ($table) {
                        ImprestReleaseGuard::defineLinkTable($table);
                    });
                }

                if (! $schema->hasIndex('imprest_requests', ImprestReleaseGuard::REQUEST_UNIQUE_INDEX)) {
                    $duplicates = $this->duplicateRequests($conn);
                    if ($duplicates === []) {
                        $schema->table('imprest_requests', function ($table) {
                            $table->unique(['employee_id', 'grade'], ImprestReleaseGuard::REQUEST_UNIQUE_INDEX);
                        });
                    } else {
                        $this->report("[{$conn}] imprest_requests has ".count($duplicates).' duplicate (employee_id, grade) group(s); unique key NOT added. Resolve and re-run. Groups (employee_id | rows | request ids): '
                            .implode('; ', array_map(fn ($d) => "{$d->employee_id} | {$d->n} | {$d->ids}", $duplicates)));
                    }
                }

                // Same grade spelled differently (J-1 / J1) is matched by the controller but not by the key: report only.
                $normalised = $this->normalisedDuplicates($conn);
                if ($normalised !== []) {
                    $this->report("[{$conn}] imprest_requests has ".count($normalised).' employee(s) with the same grade spelled differently (request ids): '
                        .implode('; ', array_map(fn ($d) => "{$d->employee_id}: {$d->ids}", $normalised)));
                }
            } catch (\Throwable $e) {
                // Fail loudly: a half-applied schema change must not be reported as migrated.
                Log::error("Migration imprest release guards on {$conn}: ".$e->getMessage());
                throw $e;
            }
        }
    }

    public function down(): void
    {
        foreach ($this->imprestConnections() as $conn) {
            try {
                $schema = Schema::connection($conn);
                if ($schema->hasIndex('imprest_requests', ImprestReleaseGuard::REQUEST_UNIQUE_INDEX)) {
                    $schema->table('imprest_requests', function ($table) {
                        $table->dropUnique(ImprestReleaseGuard::REQUEST_UNIQUE_INDEX);
                    });
                }
                if ($schema->hasTable(ImprestReleaseGuard::LINK_TABLE)) {
                    if (DB::connection($conn)->table(ImprestReleaseGuard::LINK_TABLE)->exists()) {
                        Log::warning("Rollback kept ".ImprestReleaseGuard::LINK_TABLE." on {$conn}: it links requests to auto advances.");
                    } else {
                        $schema->drop(ImprestReleaseGuard::LINK_TABLE);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Rollback imprest release guards on {$conn}: ".$e->getMessage());
            }
        }
    }

    /** @return array<int, object{employee_id:int, n:int, ids:string}> */
    private function duplicateRequests(string $conn): array
    {
        return DB::connection($conn)->table('imprest_requests')
            ->whereNotNull('grade')
            ->groupBy('employee_id', 'grade')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('employee_id, COUNT(*) AS n, GROUP_CONCAT(id ORDER BY id) AS ids')
            ->get()->all();
    }

    private function normalisedDuplicates(string $conn): array
    {
        return DB::connection($conn)->table('imprest_requests')
            ->whereNotNull('grade')
            ->groupBy('employee_id', DB::raw("REPLACE(REPLACE(UPPER(grade), '-', ''), ' ', '')"))
            ->havingRaw('COUNT(DISTINCT grade) > 1')
            ->selectRaw('employee_id, GROUP_CONCAT(id ORDER BY id) AS ids')
            ->get()->all();
    }

    private function report(string $message): void
    {
        Log::warning('Migration 2026_11_05_000006: '.$message);
        if (defined('STDERR')) {
            fwrite(STDERR, "  WARNING {$message}\n");
        }
    }

    /** Distinct connections whose database has imprest_requests (same rule as migration 2026_11_05_000002). */
    private function imprestConnections(): array
    {
        $seen = [];
        $result = [];
        foreach ($this->connections as $conn) {
            $config = config("database.connections.{$conn}");
            if (! is_array($config) || ($config['driver'] ?? '') !== 'mysql') {
                continue;
            }
            $key = ($config['host'] ?? '').'|'.($config['port'] ?? '').'|'.($config['database'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            try {
                if (! Schema::connection($conn)->hasTable('imprest_requests')) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $conn;
        }

        return $result;
    }
};
