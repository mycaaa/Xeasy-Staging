<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tracker #19 / #21 / #35
 *  - y*_g1_expensefilldata.PaxDetails_Other varchar(100) -> TEXT: group-meal
 *    other-participant JSON was cut at 100 characters (29+ rows since Aug-2026).
 *  - y*_g1_expensefilldata.PaxDetails varchar(200) -> VARCHAR(1000): employee
 *    codes / Team Meeting participant text.
 *  - y*_g7_expensefilldata: participant_names TEXT, participant_count INT,
 *    participant_department VARCHAR(150) for the activity participant list.
 *
 * Additive and idempotent: safe to re-run, skips tables/columns already in
 * the target state. down() only narrows a column when no stored value is
 * longer than the old size, and only drops a g7 column when it holds no
 * data, so a rollback never loses data.
 *
 * The `expense` DB has no `migrations` table, so run this file on its own:
 *   php artisan migrate --path=database/migrations/2026_11_05_000001_widen_participant_columns_and_add_activity_participants.php
 */
return new class extends Migration
{
    /** Company expense DB connections (same list as 2026_10_30_000001); duplicates by database are skipped. */
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new', 'expense_nursery'];

    private array $g7Columns = [
        'participant_names' => 'TEXT NULL DEFAULT NULL',
        'participant_count' => 'INT NULL DEFAULT NULL',
        'participant_department' => 'VARCHAR(150) NULL DEFAULT NULL',
    ];

    public function up(): void
    {
        foreach ($this->databases() as $conn => $database) {
            try {
                DB::connection($conn)->statement("SET SESSION sql_mode = ''");
                foreach ($this->tables($conn, $database, '_g1_expensefilldata') as $table) {
                    $this->modifyIfNarrower($conn, $database, $table, 'PaxDetails_Other', 'text', 'TEXT');
                    $this->modifyIfNarrower($conn, $database, $table, 'PaxDetails', 1000, 'VARCHAR(1000)');
                }
                foreach ($this->tables($conn, $database, '_g7_expensefilldata') as $table) {
                    foreach ($this->g7Columns as $column => $definition) {
                        if (! $this->columnType($conn, $database, $table, $column)) {
                            DB::connection($conn)->statement("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fail loudly: a half-applied schema change must not be reported as migrated.
                Log::error("Migration widen_participant_columns on {$conn}: ".$e->getMessage());
                throw $e;
            }
        }
    }

    public function down(): void
    {
        foreach ($this->databases() as $conn => $database) {
            try {
                DB::connection($conn)->statement("SET SESSION sql_mode = ''");
                foreach ($this->tables($conn, $database, '_g1_expensefilldata') as $table) {
                    $this->narrowIfFits($conn, $database, $table, 'PaxDetails_Other', 100);
                    $this->narrowIfFits($conn, $database, $table, 'PaxDetails', 200);
                }
                foreach ($this->tables($conn, $database, '_g7_expensefilldata') as $table) {
                    foreach (array_keys($this->g7Columns) as $column) {
                        if (! $this->columnType($conn, $database, $table, $column)) {
                            continue;
                        }
                        $used = DB::connection($conn)->table($table)->whereNotNull($column)->where($column, '<>', '')->exists();
                        if ($used) {
                            Log::warning("Rollback kept {$table}.{$column}: it holds data.");
                            continue;
                        }
                        DB::connection($conn)->statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Rollback widen_participant_columns on {$conn}: ".$e->getMessage());
            }
        }
    }

    /** connection => database name, one connection per distinct host/database. */
    private function databases(): array
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
                DB::connection($conn)->getPdo();
            } catch (\Throwable $e) {
                continue; // not reachable in this environment
            }
            $seen[$key] = true;
            $result[$conn] = $config['database'];
        }

        return $result;
    }

    /** Year-wise tables y<id><suffix> in the connection's database. */
    private function tables(string $conn, string $database, string $suffix): array
    {
        $rows = DB::connection($conn)->select(
            "SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME REGEXP ?",
            [$database, '^y[0-9]+'.preg_quote($suffix).'$']
        );

        return array_map(fn ($r) => $r->t, $rows);
    }

    private function columnType(string $conn, string $database, string $table, string $column): ?object
    {
        return DB::connection($conn)->selectOne(
            'SELECT DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS len, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS dflt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, $column]
        );
    }

    /**
     * NULL / NOT NULL and DEFAULT of the existing column, so widening keeps them
     * (y6 has NOT NULL DEFAULT '', y7 DEFAULT '', y8 DEFAULT NULL).
     */
    private function nullAndDefault(string $conn, object $info): string
    {
        $sql = strtoupper((string) $info->nullable) === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $info->dflt;
        if ($default === null) {
            return $sql;
        }
        if (strtoupper((string) $default) === 'NULL') {
            return $sql.' DEFAULT NULL';
        }
        // MariaDB 10.2+ reports literals already quoted ('' or 'x'); MySQL reports them bare.
        $literal = str_starts_with((string) $default, "'") ? (string) $default : DB::connection($conn)->getPdo()->quote((string) $default);

        return $sql.' DEFAULT '.$literal;
    }

    /** Widen a column to $target ('text' or a varchar length) unless it is already that wide. */
    private function modifyIfNarrower(string $conn, string $database, string $table, string $column, $target, string $definition): void
    {
        $info = $this->columnType($conn, $database, $table, $column);
        if (! $info) {
            return;
        }
        $isText = in_array(strtolower($info->type), ['text', 'mediumtext', 'longtext'], true);
        if ($isText || ($target !== 'text' && (int) $info->len >= (int) $target)) {
            return;
        }
        DB::connection($conn)->statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}".$this->nullAndDefault($conn, $info));
    }

    private function narrowIfFits(string $conn, string $database, string $table, string $column, int $length): void
    {
        $info = $this->columnType($conn, $database, $table, $column);
        if (! $info) {
            return;
        }
        $longest = (int) DB::connection($conn)->table($table)->max(DB::raw("CHAR_LENGTH(`{$column}`)"));
        if ($longest > $length) {
            Log::warning("Rollback kept {$table}.{$column} wide: longest value is {$longest} characters.");

            return;
        }
        DB::connection($conn)->statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR({$length})".$this->nullAndDefault($conn, $info));
    }
};
