<?php

namespace App\Services\Imprest;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UAT E-01: one imprest request raises at most one system auto advance.
 *
 *  - The release / approve runs in one transaction that first locks the
 *    employee's imprest_requests rows (SELECT ... FOR UPDATE) and re-reads the
 *    status under that lock, so a second concurrent release waits and then sees
 *    the request already approved.
 *  - imprest_requests(employee_id, grade) is unique (migration
 *    2026_11_05_000006), so two releases racing on an employee with no request
 *    row yet cannot both insert one: the loser gets a duplicate-key error (or an
 *    InnoDB deadlock on the gap lock), which the controller turns into 409.
 *  - imprest_auto_advances(imprest_request_id) is unique: the auto advance is
 *    reserved for the request before it is created, inside the same transaction.
 */
class ImprestReleaseGuard
{
    public const LINK_TABLE = 'imprest_auto_advances';

    public const REQUEST_UNIQUE_INDEX = 'imprest_requests_employee_grade_unique';

    /** Schema of the link table (used by the migration and the tests). */
    public static function defineLinkTable(Blueprint $table): void
    {
        $table->id();
        $table->unsignedBigInteger('imprest_request_id')->unique();
        $table->unsignedBigInteger('employee_id')->index();
        $table->unsignedBigInteger('advance_request_id')->nullable()->index();
        $table->timestamps();
    }

    public static function normaliseGrade(?string $grade): string
    {
        return strtoupper(str_replace(['-', ' '], '', (string) $grade));
    }

    /**
     * The connection to run the release transaction on. The advance models
     * (AdvanceRequest & co.) use the default connection; when the imprest
     * connection points at the same database, the default connection is used
     * for the imprest rows too, so imprest request, action row, link row and
     * auto advance commit or roll back together.
     */
    public static function transactionConnection(string $conn): string
    {
        $default = (string) config('database.default');
        if ($conn === $default) {
            return $conn;
        }
        $a = config("database.connections.{$conn}");
        $b = config("database.connections.{$default}");
        if (! is_array($a) || ! is_array($b)) {
            return $conn;
        }
        foreach (['driver', 'host', 'port', 'database'] as $key) {
            if ((string) ($a[$key] ?? '') !== (string) ($b[$key] ?? '')) {
                return $conn;
            }
        }

        return $default;
    }

    public function linkTableExists(string $conn): bool
    {
        try {
            return Schema::connection($conn)->hasTable(self::LINK_TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Lock all imprest_requests rows of the employee (must run inside a
     * transaction) and return the one for this grade, matching the grade
     * exactly or after normalisation (J-1 == J1), lowest id first.
     */
    public function lockRequestForGrade(string $conn, int $employeeId, ?string $grade): ?object
    {
        $rows = DB::connection($conn)->table('imprest_requests')
            ->where('employee_id', $employeeId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $clean = self::normaliseGrade($grade);
        foreach ($rows as $row) {
            if ((string) $row->grade === (string) $grade || self::normaliseGrade($row->grade) === $clean) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Reserve the single auto advance of an imprest request. Returns false
     * when the request already has one (another release got there first).
     */
    public function reserve(string $conn, int $imprestRequestId, int $employeeId): bool
    {
        return DB::connection($conn)->table(self::LINK_TABLE)->insertOrIgnore([
            'imprest_request_id' => $imprestRequestId,
            'employee_id' => $employeeId,
            'advance_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function attach(string $conn, int $imprestRequestId, int $advanceRequestId): void
    {
        DB::connection($conn)->table(self::LINK_TABLE)
            ->where('imprest_request_id', $imprestRequestId)
            ->update(['advance_request_id' => $advanceRequestId, 'updated_at' => now()]);
    }

    /**
     * A lost race: duplicate key (the other release inserted the same
     * request), deadlock on the gap lock, or a lock wait timeout.
     */
    public static function isConcurrencyConflict(\Throwable $e): bool
    {
        if (! $e instanceof QueryException && ! $e instanceof \PDOException) {
            return false;
        }
        $info = $e->errorInfo ?? null;
        $sqlState = (string) ($info[0] ?? $e->getCode());
        $driverCode = (int) ($info[1] ?? 0);

        return in_array($driverCode, [1062, 1213, 1205], true)  // MySQL/MariaDB duplicate key, deadlock, lock wait timeout
            || $sqlState === '40001'                            // serialization failure / deadlock
            || ($sqlState === '23000' && stripos($e->getMessage(), 'unique') !== false); // SQLite UNIQUE constraint
    }
}
