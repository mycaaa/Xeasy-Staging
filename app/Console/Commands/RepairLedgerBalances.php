<?php

namespace App\Console\Commands;

use App\Support\LedgerJson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent repair of employee_ledger_balances:
 *  1. remaining_balances_json stored double-encoded (a JSON string literal wrapping the object)
 *     is rewritten encoded once;
 *  2. closing_balance (never written before) is set on each employee's latest ledger row to the
 *     balance computed by the Ledger Summary engine: imprest + opening advances + advances paid
 *     - claim adjustments.
 * Running it twice changes nothing the second time.
 *
 * Safety: all changes are computed first; then (unless --dry-run) the table is copied to
 * employee_ledger_balances_bak_<timestamp> (skip with --no-backup) and every update is applied in
 * one transaction, so a failure leaves the table exactly as it was. Only remaining_balances_json
 * and closing_balance are written; updated_at is preserved.
 */
class RepairLedgerBalances extends Command
{
    protected $signature = 'ledger:repair-balances
                            {--connection=expense : Expense DB connection}
                            {--company=1 : Company id (sets the session context used by the engine)}
                            {--year= : Session year id (default: latest in financialyear)}
                            {--skip-closing : Only normalise the JSON column}
                            {--no-backup : Do not copy the table before writing}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Normalise double-encoded remaining_balances_json and back-fill closing_balance in employee_ledger_balances';

    public function handle(): int
    {
        $conn = (string) $this->option('connection');
        $dry = (bool) $this->option('dry-run');
        $db = DB::connection($conn);
        $yearId = $this->option('year') ?: $db->table('financialyear')->max('YearId');
        session(['expense_connection' => $conn, 'company_id' => (int) $this->option('company'), 'year_id' => (int) $yearId]);

        $rows = $db->table('employee_ledger_balances')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'employee_id', 'remaining_balances_json', 'closing_balance']);

        // 1. JSON normalisation (computed, not yet written)
        $jsonUpdates = [];
        foreach ($rows as $r) {
            if (LedgerJson::isDoubleEncoded($r->remaining_balances_json)) {
                $jsonUpdates[$r->id] = LedgerJson::encode($r->remaining_balances_json);
            }
        }

        // 2. closing_balance back-fill on the latest row of each employee (computed, not yet written)
        $closingUpdates = [];
        $latestCount = 0;
        $total = 0.0;
        if (! $this->option('skip-closing')) {
            $latest = $rows->sortByDesc('id')->unique('employee_id');
            $latestCount = $latest->count();
            $balances = app(\App\Http\Controllers\ExpensePaymentController::class)
                ->computeBatchEmployeeLedgerBalances($latest->pluck('employee_id')->all());

            foreach ($latest as $r) {
                $closing = (float) ($balances[$r->employee_id]['total_balance'] ?? 0);
                $total += $closing;
                if (abs($closing - (float) $r->closing_balance) >= 0.01) {
                    $closingUpdates[$r->id] = $closing;
                }
            }
        }

        $prefix = $dry ? '[dry-run] ' : '';
        if (! $dry && (count($jsonUpdates) || count($closingUpdates))) {
            if (! $this->option('no-backup')) {
                // CREATE TABLE ... SELECT commits implicitly, so it runs before the transaction.
                $backup = 'employee_ledger_balances_bak_'.date('YmdHis');
                $db->statement("CREATE TABLE `{$backup}` AS SELECT * FROM employee_ledger_balances");
                $this->info("backup written: {$backup}");
            }

            $db->transaction(function () use ($db, $jsonUpdates, $closingUpdates) {
                foreach ($jsonUpdates as $id => $json) {
                    $db->table('employee_ledger_balances')->where('id', $id)
                        ->update(['remaining_balances_json' => $json, 'updated_at' => DB::raw('updated_at')]);
                }
                foreach ($closingUpdates as $id => $closing) {
                    $db->table('employee_ledger_balances')->where('id', $id)
                        ->update(['closing_balance' => $closing, 'updated_at' => DB::raw('updated_at')]);
                }
            });
        }

        $this->info($prefix.'remaining_balances_json normalised: '.count($jsonUpdates)." of {$rows->count()} rows");
        if (! $this->option('skip-closing')) {
            $this->info($prefix.'closing_balance updated: '.count($closingUpdates)." of {$latestCount} employees; total closing = ".number_format($total, 2));
        }

        return self::SUCCESS;
    }
}
