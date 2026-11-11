<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-01: full bank account numbers sat in clear in the bank-import table (digi_next_payment_file)
 * and in unsettled_payments. Once a bank row is matched (or an unsettled case is resolved) the full
 * number is no longer needed: matching and duplicate checks use the transaction reference.
 *
 * This keeps only the last 4 digits (e.g. XXXXXXXX1234) on matched rows and resolved unsettled
 * rows, and clears the Aadhaar column (it only ever held 3-character placeholders).
 * Rows still waiting to be matched keep the full number. Runs daily; --apply writes, otherwise counts.
 */
class MaskBankData extends Command
{
    protected $signature = 'payments:mask-bank-data {--apply : write the changes (default: count only)}';

    protected $description = 'Keep only the last 4 digits of bank account numbers once a bank row is matched';

    public const MASK_SQL = "CONCAT(REPEAT('X', GREATEST(CHAR_LENGTH(%1\$s) - 4, 0)), RIGHT(%1\$s, 4))";

    public static function mask(?string $acc): ?string
    {
        $acc = trim((string) $acc);
        if ($acc === '' || strlen($acc) <= 4) {
            return $acc === '' ? null : $acc;
        }

        return str_repeat('X', strlen($acc) - 4).substr($acc, -4);
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $db = DB::connection(); // shared table on the default connection
        $total = 0;

        if (Schema::hasTable('digi_next_payment_file')) {
            foreach (['beneficiary_account_number', 'debit_account_number'] as $col) {
                if (! Schema::hasColumn('digi_next_payment_file', $col)) {
                    continue;
                }
                $q = $db->table('digi_next_payment_file')->where('match_status', 'MATCHED')
                    ->whereRaw("{$col} REGEXP '[0-9]{5}'");
                $n = (clone $q)->count();
                if ($apply && $n) {
                    $q->update([$col => DB::raw(sprintf(self::MASK_SQL, $col))]);
                }
                $this->line("digi_next_payment_file.{$col}: {$n} matched rows ".($apply ? 'masked' : 'to mask'));
                $total += $n;
            }
            if (Schema::hasColumn('digi_next_payment_file', 'beneficiary_aadhaar_number')) {
                $q = $db->table('digi_next_payment_file')->whereNotNull('beneficiary_aadhaar_number')->where('beneficiary_aadhaar_number', '!=', '');
                $n = (clone $q)->count();
                if ($apply && $n) {
                    $q->update(['beneficiary_aadhaar_number' => null]);
                }
                $this->line("digi_next_payment_file.beneficiary_aadhaar_number: {$n} ".($apply ? 'cleared' : 'to clear'));
            }
        }

        if (Schema::hasTable('unsettled_payments') && Schema::hasColumn('unsettled_payments', 'account_no')) {
            $q = $db->table('unsettled_payments')->whereRaw("account_no REGEXP '[0-9]{5}'")
                ->when(Schema::hasColumn('unsettled_payments', 'status'), fn ($x) => $x->where('status', '!=', 'Unsettled'));
            $n = (clone $q)->count();
            if ($apply && $n) {
                $q->update(['account_no' => DB::raw(sprintf(self::MASK_SQL, 'account_no'))]);
            }
            $this->line("unsettled_payments.account_no: {$n} resolved rows ".($apply ? 'masked' : 'to mask'));
            $total += $n;
        }

        $this->info(($apply ? 'Masked ' : 'Would mask ').$total.' account numbers.');

        return self::SUCCESS;
    }
}
