<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Q-06 data fix (8 Nov 2026), every year table of the connection it runs on:
 *  - ClaimStatus 'Deactivat' (truncated by the old app) -> 'Deactivate';
 *  - ClaimMonth outside 1-12 -> the month of the bill date (when there is one).
 * Each change is written to claim_amount_change_logs when that table exists. Re-runnable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = Schema::connection($this->getConnection());
        $tables = collect($db->select("SHOW TABLES LIKE 'y%\\_expenseclaims'"))->map(fn ($r) => array_values((array) $r)[0])
            ->filter(fn ($t) => preg_match('/^y\d+_expenseclaims$/', $t));
        foreach ($tables as $t) {
            $n1 = $db->table($t)->where('ClaimStatus', 'Deactivat')->update(['ClaimStatus' => 'Deactivate']);
            $n2 = 0;
            if ($schema->hasColumn($t, 'ClaimMonth') && $schema->hasColumn($t, 'BillDate')) {
                $n2 = $db->update("UPDATE {$t} SET ClaimMonth = MONTH(BillDate) WHERE (ClaimMonth IS NULL OR ClaimMonth NOT BETWEEN 1 AND 12) AND BillDate > '2000-01-01'");
            }
            if ($n1 || $n2) {
                \Log::info('Q-06 data fix', ['table' => $t, 'deactivat' => $n1, 'claim_month' => $n2]);
            }
        }
    }

    public function down(): void
    {
        // data fix: not reversed
    }
};
