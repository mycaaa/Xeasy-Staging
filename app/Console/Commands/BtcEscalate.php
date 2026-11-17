<?php

namespace App\Console\Commands;

use App\Services\Btc\BtcNotifier;
use Illuminate\Console\Command;

/** BTC-NOTIFY-01 (17 Nov 2026): daily mail for BTC cases past their stage time limit (BTC Settings). */
class BtcEscalate extends Command
{
    protected $signature = 'btc:escalate';

    protected $description = 'Mail the people (and their manager / the Finance head) for BTC cases past the stage time limit';

    public function handle(): int
    {
        if (! BtcNotifier::enabled()) {
            $this->info('BTC mails are switched off (BTC Settings).');

            return self::SUCCESS;
        }
        $this->info('Escalated: '.BtcNotifier::escalate());

        return self::SUCCESS;
    }
}
