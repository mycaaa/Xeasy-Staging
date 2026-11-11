<?php

namespace Tests\Unit;

use App\Services\Imprest\ImprestReleaseGuard;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * UAT E-01. Runs on the phpunit sqlite :memory: connection. SQLite has no
 * row locks (lockForUpdate compiles to nothing), so true concurrency is not
 * exercised here; what is tested is that every way a second release can lose
 * the race ends in a "concurrency conflict" (409) and never in a second
 * request row or a second auto advance. The MariaDB two-session check is in
 * work/uat_defect_fixes.md.
 */
class ImprestReleaseGuardTest extends TestCase
{
    private string $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = (string) config('database.default');
        $schema = Schema::connection($this->conn);
        $schema->dropIfExists('imprest_requests');
        $schema->dropIfExists(ImprestReleaseGuard::LINK_TABLE);
        $schema->create('imprest_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('grade', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['employee_id', 'grade'], ImprestReleaseGuard::REQUEST_UNIQUE_INDEX);
        });
        $schema->create(ImprestReleaseGuard::LINK_TABLE, function (Blueprint $table) {
            ImprestReleaseGuard::defineLinkTable($table);
        });
    }

    public function test_a_request_reserves_its_auto_advance_only_once(): void
    {
        $guard = new ImprestReleaseGuard();
        $this->assertTrue($guard->reserve($this->conn, 7, 1001));
        $this->assertFalse($guard->reserve($this->conn, 7, 1001), 'second release of the same request must not create a second advance');
        $this->assertTrue($guard->reserve($this->conn, 8, 1001), 'a different request (e.g. grade upgrade) may raise its own advance');

        $guard->attach($this->conn, 7, 555);
        $this->assertSame(555, (int) DB::table(ImprestReleaseGuard::LINK_TABLE)->where('imprest_request_id', 7)->value('advance_request_id'));
        $this->assertSame(1, DB::table(ImprestReleaseGuard::LINK_TABLE)->where('imprest_request_id', 7)->count());
    }

    public function test_second_insert_for_same_employee_and_grade_is_a_concurrency_conflict(): void
    {
        DB::table('imprest_requests')->insert(['employee_id' => 1001, 'grade' => 'J1', 'status' => 'approved']);
        try {
            DB::table('imprest_requests')->insert(['employee_id' => 1001, 'grade' => 'J1', 'status' => 'approved']);
            $this->fail('duplicate (employee_id, grade) was accepted');
        } catch (QueryException $e) {
            $this->assertTrue(ImprestReleaseGuard::isConcurrencyConflict($e));
        }
        $this->assertSame(1, DB::table('imprest_requests')->where('employee_id', 1001)->count());
    }

    public function test_loser_inside_a_transaction_rolls_back_everything(): void
    {
        DB::table('imprest_requests')->insert(['employee_id' => 1001, 'grade' => 'J1', 'status' => 'approved']);
        $guard = new ImprestReleaseGuard();
        try {
            DB::connection($this->conn)->transaction(function () use ($guard) {
                $guard->reserve($this->conn, 99, 1001);           // would-be link row
                DB::table('imprest_requests')->insert(['employee_id' => 1001, 'grade' => 'J1', 'status' => 'approved']);
            });
            $this->fail('expected a conflict');
        } catch (QueryException $e) {
            $this->assertTrue(ImprestReleaseGuard::isConcurrencyConflict($e));
        }
        $this->assertSame(0, DB::table(ImprestReleaseGuard::LINK_TABLE)->where('imprest_request_id', 99)->count(), 'link row must roll back with the failed release');
    }

    public function test_mysql_duplicate_deadlock_and_lock_wait_count_as_conflicts(): void
    {
        foreach ([['23000', 1062], ['40001', 1213], ['HY000', 1205]] as [$state, $code]) {
            $this->assertTrue(ImprestReleaseGuard::isConcurrencyConflict($this->queryException($state, $code, 'x')), "{$state}/{$code}");
        }
        $this->assertFalse(ImprestReleaseGuard::isConcurrencyConflict($this->queryException('42S02', 1146, "Table 'imprest_requests' doesn't exist")));
        $this->assertFalse(ImprestReleaseGuard::isConcurrencyConflict(new \RuntimeException('other')));
    }

    public function test_lock_finds_request_by_exact_or_normalised_grade(): void
    {
        DB::table('imprest_requests')->insert(['employee_id' => 1001, 'grade' => 'J-1', 'status' => 'rejected']);
        DB::table('imprest_requests')->insert(['employee_id' => 1002, 'grade' => 'M1', 'status' => 'approved']);
        $guard = new ImprestReleaseGuard();

        $row = DB::transaction(fn () => $guard->lockRequestForGrade($this->conn, 1001, 'J1'));
        $this->assertNotNull($row);
        $this->assertSame('rejected', $row->status);
        $this->assertNull(DB::transaction(fn () => $guard->lockRequestForGrade($this->conn, 1001, 'J2')));
        $this->assertSame('approved', DB::transaction(fn () => $guard->lockRequestForGrade($this->conn, 1002, 'M1'))->status);
    }

    public function test_transaction_connection_uses_default_when_same_database(): void
    {
        $default = (string) config('database.default');
        config(['database.connections.same_db' => config("database.connections.{$default}")]);
        config(['database.connections.other_db' => array_merge(config("database.connections.{$default}"), ['database' => 'elsewhere'])]);

        $this->assertSame($default, ImprestReleaseGuard::transactionConnection('same_db'));
        $this->assertSame('other_db', ImprestReleaseGuard::transactionConnection('other_db'));
        $this->assertSame('missing_conn', ImprestReleaseGuard::transactionConnection('missing_conn'));
    }

    private function queryException(string $state, int $code, string $message): QueryException
    {
        $pdo = new \PDOException("SQLSTATE[{$state}]: {$message}");
        $pdo->errorInfo = [$state, $code, $message];

        return new QueryException('mysql', 'insert ...', [], $pdo);
    }
}
