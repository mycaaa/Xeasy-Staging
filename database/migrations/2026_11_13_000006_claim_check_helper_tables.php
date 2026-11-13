<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * DDL-TX-01 (13 Nov 2026): create the helper tables of the claim checks at deploy time, in every company's expense
 * database, so no check ever creates one in the middle of a claim save (MySQL commits the save's transaction on
 * CREATE TABLE: "There is no active transaction" on the first odometer upload of a new server).
 * Each table is created only when missing; a database that cannot be written (read-only on a test copy) is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conns = [];
        foreach ([1, 3, 11] as $company) {
            try {
                $conns[] = \App\Helpers\DatabaseSwitcher::getExpenseConnection($company);
            } catch (\Throwable $e) {
            }
        }
        foreach (array_unique(array_filter($conns)) as $conn) {
            foreach ([
                fn () => \App\Services\Claims\OdometerContinuity::ensureTable($conn),
                fn () => app(\App\Services\Claims\DuplicateOdometer::class)->ensureLogTable($conn),
                fn () => \App\Services\Claims\PunchChangeTrack::ensureTable($conn),
                fn () => \App\Services\Claims\BillDuplicate::ensureTable($conn),
                fn () => \App\Services\Claims\UpiProof::ensureTable($conn),
                fn () => \App\Services\ClaimRules\KmCapApproval::ensureTable($conn),
                fn () => \App\Services\ClaimFiles\UploadFingerprint::ensureTable($conn),
            ] as $make) {
                try {
                    $make();
                } catch (\Throwable $e) {
                    Log::warning("claim helper table not created on {$conn}: ".$e->getMessage());
                    echo "  skipped on {$conn}: ".mb_substr($e->getMessage(), 0, 120)."\n";
                }
            }
            echo "  helper tables checked on {$conn}\n";
        }
    }

    public function down(): void
    {
        // the tables hold check results; they are not dropped
    }
};
