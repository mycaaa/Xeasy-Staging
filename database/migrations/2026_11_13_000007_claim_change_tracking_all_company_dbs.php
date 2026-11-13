<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TRACK-02 (13 Nov 2026): Verify Running KM failed on the test host with "Claim change tracking is not set up (run
 * migration 2026_11_05_000007)". That migration only looked at a fixed list of connection names, so a company
 * database reached under another name (or a claims table added later) never got the change log / original-amount
 * columns, and the migration counted as done. This one does the same set-up for each company's own expense
 * connection (DatabaseSwitcher, companies 1 / 3 / 11) and every y*_expenseclaims table. Re-runnable; a database that
 * cannot be written (read-only copy) is skipped with a message.
 */
return new class extends Migration
{
    private array $columns = [
        'OrigFilledTAmt' => 'INT NULL', 'OrigOdoOpening' => 'BIGINT NULL', 'OrigOdoClosing' => 'BIGINT NULL',
        'OrigTotKm' => 'DECIMAL(7,2) NULL', 'OrigRatePerKM' => 'DECIMAL(4,2) NULL',
        'VerifierEditBy' => 'INT NULL', 'VerifierEditAt' => 'DATETIME NULL',
    ];

    public function up(): void
    {
        $conns = [];
        foreach ([1, 3, 11] as $company) {
            try {
                $conns[] = \App\Helpers\DatabaseSwitcher::getExpenseConnection($company);
            } catch (\Throwable $e) {
            }
        }
        $seen = [];
        foreach (array_unique(array_filter($conns)) as $conn) {
            try {
                $db = DB::connection($conn);
                $key = $db->getConfig('host').'|'.$db->getConfig('port').'|'.$db->getDatabaseName();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $s = Schema::connection($conn);
                if (! $s->hasTable('claim_amount_change_logs')) {
                    $s->create('claim_amount_change_logs', function (Blueprint $t) {
                        $t->id();
                        $t->unsignedInteger('company_id')->nullable();
                        $t->unsignedSmallInteger('year_id');
                        $t->unsignedBigInteger('exp_id')->index();
                        $t->unsignedBigInteger('employee_id')->nullable();
                        $t->string('source', 40);
                        $t->string('field', 40);
                        $t->string('old_value', 50)->nullable();
                        $t->string('new_value', 50)->nullable();
                        $t->string('remark', 500)->nullable();
                        $t->unsignedBigInteger('changed_by')->nullable();
                        $t->timestamp('created_at')->useCurrent();
                    });
                }
                $tables = array_map(fn ($r) => $r->t, $db->select(
                    "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_name REGEXP '^y[0-9]+_expenseclaims$'",
                    [$db->getDatabaseName()]
                ));
                $mode = $db->selectOne('SELECT @@SESSION.sql_mode AS m')->m ?? '';
                $db->statement("SET SESSION sql_mode = ''");
                $added = 0;
                try {
                    foreach ($tables as $table) {
                        $add = [];
                        foreach ($this->columns as $col => $type) {
                            if (! $s->hasColumn($table, $col)) {
                                $add[] = "ADD COLUMN `{$col}` {$type}";
                            }
                        }
                        if ($add) {
                            $db->statement("ALTER TABLE `{$table}` ".implode(', ', $add));
                            $added++;
                        }
                    }
                } finally {
                    $db->statement('SET SESSION sql_mode = ?', [$mode]);
                }
                echo "  {$conn} ({$db->getDatabaseName()}): change log ready, ".count($tables)." claims table(s), {$added} updated\n";
            } catch (\Throwable $e) {
                Log::warning("TRACK-02 on {$conn}: ".$e->getMessage());
                echo "  skipped {$conn}: ".mb_substr($e->getMessage(), 0, 140)."\n";
            }
        }
    }

    public function down(): void
    {
        // the change history is kept
    }
};
