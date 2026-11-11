<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * B-01 / B-02 / B-03 / LV-C07 (audit, 6 Nov 2026): claim_amount_change_logs becomes the one
 * change history for claim amounts AND status changes. Adds who acted in which role and how:
 *   actor_role (claimant, approver, punching, verifier, mediator, finance, system),
 *   mode (manual, bulk, auto, app), actor_employee_id.
 * Run after 2026_11_05_000007 (which creates the table). Tolerant: a database the login cannot
 * alter (VVNR copy on staging) is skipped and logged; run it again there at go-live.
 *
 *   php artisan migrate --path=database/migrations/2026_11_06_000017_add_actor_role_to_claim_change_logs.php
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr'];

    public function up(): void
    {
        $done = [];
        foreach ($this->connections as $conn) {
            try {
                $db = \Illuminate\Support\Facades\DB::connection($conn)->getDatabaseName();
                if (isset($done[$db])) {
                    continue;
                }
                $done[$db] = true;
                $s = Schema::connection($conn);
                if (! $s->hasTable('claim_amount_change_logs')) {
                    Log::warning("000017: claim_amount_change_logs missing on {$conn} (run 000007 first)");
                    continue;
                }
                $s->table('claim_amount_change_logs', function (Blueprint $t) use ($s) {
                    if (! $s->hasColumn('claim_amount_change_logs', 'actor_role')) {
                        $t->string('actor_role', 20)->nullable()->after('changed_by');
                    }
                    if (! $s->hasColumn('claim_amount_change_logs', 'mode')) {
                        $t->string('mode', 20)->nullable()->after('changed_by');
                    }
                    if (! $s->hasColumn('claim_amount_change_logs', 'actor_employee_id')) {
                        $t->unsignedBigInteger('actor_employee_id')->nullable()->after('changed_by');
                    }
                });
                try {
                    $s->table('claim_amount_change_logs', fn (Blueprint $t) => $t->index(['field', 'created_at'], 'cacl_field_created'));
                } catch (\Throwable $e) {
                    // index exists
                }
            } catch (\Throwable $e) {
                Log::warning("000017 skipped on {$conn}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
    }
};
