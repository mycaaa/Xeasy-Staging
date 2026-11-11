<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LV-C05 (audit, 6 Nov 2026): record who released each month's payment to the bank
 * (payment_instructed_by), next to payment_approved_by, for the maker-checker rule.
 * Tolerant: a database the login cannot alter (VVNR copy on staging) is skipped and logged.
 *
 *   php artisan migrate --path=database/migrations/2026_11_06_000018_add_payment_instructed_by.php
 */
return new class extends Migration
{
    public function up(): void
    {
        $done = [];
        foreach (['expense', 'expense_vvnr', 'expense_nursery'] as $conn) {
            try {
                $db = DB::connection($conn);
                if (isset($done[$db->getDatabaseName()])) {
                    continue;
                }
                $done[$db->getDatabaseName()] = true;
                $tables = array_map(fn ($r) => array_values((array) $r)[0], $db->select("SHOW TABLES LIKE 'y%\\_monthexpensefinal'"));
                $mode = $db->selectOne('SELECT @@SESSION.sql_mode AS m')->m ?? '';
                $db->statement("SET SESSION sql_mode = ''");
                foreach ($tables as $t) {
                    if (! preg_match('/^y\d+_monthexpensefinal$/', $t) || Schema::connection($conn)->hasColumn($t, 'payment_instructed_by')) {
                        continue;
                    }
                    try {
                        $after = Schema::connection($conn)->hasColumn($t, 'payment_instructed_date') ? ' AFTER `payment_instructed_date`' : '';
                        $db->statement("ALTER TABLE `{$t}` ADD COLUMN `payment_instructed_by` INT NULL{$after}");
                    } catch (\Throwable $e) {
                        Log::warning("000018: {$conn}.{$t} not altered: ".$e->getMessage());
                    }
                }
                $db->statement('SET SESSION sql_mode = ?', [$mode]);
            } catch (\Throwable $e) {
                Log::warning("000018 skipped on {$conn}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
    }
};
