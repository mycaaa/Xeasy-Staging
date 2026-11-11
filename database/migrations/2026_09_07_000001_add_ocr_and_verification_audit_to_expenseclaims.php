<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connections = array_filter(array_unique([
            'expense',
            'expense_vnr',
            'expense_nursery',
            config('database.default', 'mysql'),
            'mysql',
        ]), fn ($conn) => config("database.connections.{$conn}") !== null);

        foreach ($connections as $conn) {
            try {
                $rawTables = DB::connection($conn)->select("SHOW TABLES LIKE 'y%_expenseclaims'");
            } catch (\Exception $e) {
                Log::warning("Migration add_ocr_and_verification_audit skipped connection {$conn}: " . $e->getMessage());
                continue;
            }
            foreach ($rawTables as $tableObj) {
                $tableName = array_values((array) $tableObj)[0];
                // 11 Nov 2026: one table at a time (an old year without odomtr_opening stopped every later year,
                // y8 included), and a column goes after its neighbour only when that neighbour exists
                try {
                    $s = Schema::connection($conn);
                    $cols = [
                        ['is_manually_edited', 'ClaimStatus', fn ($t) => $t->boolean('is_manually_edited')->default(false)],
                        ['verification_source', 'is_manually_edited', fn ($t) => $t->string('verification_source', 50)->nullable()],
                        ['verification_reason', 'verification_source', fn ($t) => $t->string('verification_reason', 255)->nullable()],
                        ['ocr_opening', 'odomtr_opening', fn ($t) => $t->bigInteger('ocr_opening')->nullable()],
                        ['ocr_closing', 'odomtr_closing', fn ($t) => $t->bigInteger('ocr_closing')->nullable()],
                        ['ocr_amount', 'FilledTAmt', fn ($t) => $t->decimal('ocr_amount', 12, 2)->nullable()],
                        ['ocr_status', 'verification_reason', fn ($t) => $t->string('ocr_status', 50)->nullable()],
                    ];
                    foreach ($cols as [$col, $after, $def]) {
                        if ($s->hasColumn($tableName, $col)) {
                            continue;
                        }
                        $s->table($tableName, function (Blueprint $table) use ($s, $tableName, $after, $def) {
                            $c = $def($table);
                            if ($s->hasColumn($tableName, $after)) {
                                $c->after($after);
                            }
                        });
                    }
                } catch (\Exception $e) {
                    Log::warning("Migration add_ocr_and_verification_audit failed on {$conn}.{$tableName}: " . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        $connections = array_filter(array_unique([
            'expense',
            'expense_vnr',
            'expense_nursery',
            config('database.default', 'mysql'),
            'mysql',
        ]), fn ($conn) => config("database.connections.{$conn}") !== null);

        foreach ($connections as $conn) {
            try {
                $rawTables = DB::connection($conn)->select("SHOW TABLES LIKE 'y%_expenseclaims'");
                foreach ($rawTables as $tableObj) {
                    $tableName = array_values((array) $tableObj)[0];

                    Schema::connection($conn)->table($tableName, function (Blueprint $table) use ($conn, $tableName) {
                        $colsToDrop = [];
                        foreach (['is_manually_edited', 'verification_source', 'verification_reason', 'ocr_opening', 'ocr_closing', 'ocr_amount', 'ocr_status'] as $col) {
                            if (Schema::connection($conn)->hasColumn($tableName, $col)) {
                                $colsToDrop[] = $col;
                            }
                        }
                        if (!empty($colsToDrop)) {
                            $table->dropColumn($colsToDrop);
                        }
                    });
                }
            } catch (\Exception $e) {
                Log::warning("Migration rollback add_ocr_and_verification_audit failed on connection {$conn}: " . $e->getMessage());
            }
        }
    }
};
