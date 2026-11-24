<?php

namespace App\Console\Commands;

use App\Models\BtcCase;
use App\Models\BtcCaseLog;
use App\Services\Btc\BtcExtraction;
use Illuminate\Console\Command;

/**
 * BTC-READER-03 (24 Nov 2026, testing point 9): bills on the Punch Screen that the reader could not read (e.g. the
 * "403" of 18-24 Nov) are read again on their own, so the team does not type them. Only empty fields are filled; a
 * bill is tried at most 3 times.
 */
class BtcRereadFailed extends Command
{
    protected $signature = 'btc:reread-failed {--limit=20}';

    protected $description = 'Read again the BTC bills on the Punch Screen that could not be read';

    public function handle(): int
    {
        if (! config('btc.reader.enabled', true)) {
            return self::SUCCESS;
        }
        $done = 0;
        $cases = BtcCase::where('status', 'bill_submitted')->where('extract_status', 'failed')->orderBy('id')->limit((int) $this->option('limit'))->get();
        foreach ($cases as $c) {
            $tries = BtcCaseLog::where('btc_case_id', $c->id)->where('action', 'extraction')->where('remark', 'like', 'Invoice not read (automatic)%')->count();
            if ($tries >= 3) {
                continue;
            }
            $r = BtcExtraction::run($c);
            BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'extraction', 'from_status' => $c->status, 'to_status' => $c->status,
                'remark' => $r['ok'] ? 'Invoice read again (automatic)' : 'Invoice not read (automatic): '.$r['error'], 'user_id' => null, 'user_name' => 'System']);
            $done += $r['ok'] ? 1 : 0;
        }
        $this->info("Read {$done} of {$cases->count()} bill(s).");

        return self::SUCCESS;
    }
}
