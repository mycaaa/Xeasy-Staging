<?php

namespace App\Console\Commands;

use App\Services\Btc\BtcMatrixVerify;
use Illuminate\Console\Command;

/** BTC-MATRIX-01 (20 Nov 2026, tracker item 9): send recorded BTC payments to Matrix for confirmation. */
class BtcMatrixVerifyCommand extends Command
{
    protected $signature = 'btc:matrix-verify {--dry-run : only count what would be sent} {--limit=500}';

    protected $description = 'Send BTC payments recorded by Treasury to Matrix (payment/verify-bulk)';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! BtcMatrixVerify::enabled()) {
            $this->line('Matrix payment confirmation is off (BTC_MATRIX_VERIFY_ENABLED / BTC_MATRIX_VERIFY_KEY).');

            return self::SUCCESS;
        }
        $r = app(BtcMatrixVerify::class)->run(null, (bool) $this->option('dry-run'), (int) $this->option('limit'));
        $this->line($r['message']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
