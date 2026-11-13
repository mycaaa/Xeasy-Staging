<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DDL-TX-01 (13 Nov 2026): create a helper table that may be missing, without breaking the caller's transaction.
 * MySQL commits the open transaction on CREATE TABLE, so a check that created its log table inside a claim save
 * ended that save's transaction and the save failed with "There is no active transaction" (first odometer upload
 * on a server where the table did not exist yet). Inside a transaction the table is created on a separate
 * connection to the same database, so the caller's transaction is untouched.
 */
class SafeTable
{
    public static function create(string $conn, string $table, Closure $blueprint): void
    {
        if (Schema::connection($conn)->hasTable($table)) {
            return;
        }
        $use = $conn;
        if (DB::connection($conn)->transactionLevel() > 0) {
            $use = $conn.'__ddl';
            if (! config("database.connections.{$use}")) {
                config(["database.connections.{$use}" => config("database.connections.{$conn}")]);
            }
        }
        try {
            Schema::connection($use)->create($table, $blueprint);
        } catch (\Illuminate\Database\QueryException $e) {
            // created meanwhile by a parallel request
            if (! Schema::connection($conn)->hasTable($table)) {
                throw $e;
            }
        }
    }
}
