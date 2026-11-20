<?php

namespace App\Console\Commands;

use App\Services\Btc\CoreVendors;
use Illuminate\Console\Command;

/** BTC-VENDOR-CORE-01 (20 Nov 2026): copy the BTC vendor list from the core application (daily). */
class BtcVendorSync extends Command
{
    protected $signature = 'btc:vendors-sync';

    protected $description = 'Copy the BTC vendor list from the core application (core.vnrin.in)';

    public function handle(): int
    {
        $r = CoreVendors::sync();
        $this->line($r['message']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
