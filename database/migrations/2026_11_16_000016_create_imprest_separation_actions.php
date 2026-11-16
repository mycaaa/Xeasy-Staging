<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * IMP-SEP-01 (16 Nov 2026): imprest of employees who resigned / separated. Travel Imprest Policy 25.1: the imprest
 * is settled on resignation / separation. System Notifications > "Separation / Resignation" lists everyone with a
 * resignation or separation date who still holds imprest (or has an open imprest request); Finance records the
 * settlement there and the person leaves the list. One row per employee in imprest_separation_actions, next to every
 * imprest_requests table. Idempotent (the table is also created on first use, see ImprestAutomationController).
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'expense_nursery'];

    public function up(): void
    {
        $seen = [];
        foreach ($this->connections as $conn) {
            $cfg = config("database.connections.{$conn}");
            if (! is_array($cfg) || ($cfg['driver'] ?? '') !== 'mysql') {
                continue;
            }
            $key = ($cfg['host'] ?? '').'|'.($cfg['port'] ?? '').'|'.($cfg['database'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            try {
                if (! Schema::connection($conn)->hasTable('imprest_requests')) {
                    continue;
                }
                $seen[$key] = true;
                \App\Http\Controllers\admin\ImprestAutomationController::ensureSeparationTable($conn);
            } catch (\Throwable $e) {
                // database not reachable on this server (e.g. VVNR on the test server)
            }
        }
    }

    public function down(): void
    {
        // kept: it is the record of imprest settled on separation
    }
};
