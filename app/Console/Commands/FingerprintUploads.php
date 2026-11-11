<?php

namespace App\Console\Commands;

use App\Services\ClaimFiles\UploadFingerprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FILE-FP-01: fingerprint claim attachments that have none yet (older uploads, mobile API uploads) and list
 * files that are attached to more than one live claim.
 *   uploads:fingerprint --year=8 [--limit=5000] [--report]
 */
class FingerprintUploads extends Command
{
    protected $signature = 'uploads:fingerprint {--year= : YearId, default the current year} {--company=1} {--limit=5000} {--report : only list reused files}';

    protected $description = 'SHA-256 of claim attachments; list identical files on different claims';

    public function handle(): int
    {
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection((int) $this->option('company'));
        $year = (int) ($this->option('year') ?: \App\Support\CurrentYear::id($conn));
        UploadFingerprint::ensureTable();
        if (! $this->option('report')) {
            $done = DB::table(UploadFingerprint::TABLE)->where('year_id', $year)->pluck('file_name')->flip();
            $rows = DB::connection($conn)->table("y{$year}_claimuploads as u")->join("y{$year}_expenseclaims as e", 'e.ExpId', '=', 'u.ExpId')
                ->orderByDesc('u.ExpId')->limit((int) $this->option('limit') * 2)->get(['u.FileName', 'e.CrBy']);
            $n = $miss = 0;
            foreach ($rows as $r) {
                if ($n >= (int) $this->option('limit') || isset($done[$r->FileName]) || ! $r->FileName) {
                    continue;
                }
                $got = UploadFingerprint::fetch($year, (int) $r->CrBy, (string) $r->FileName);
                if (! $got) {
                    $miss++;
                    continue;
                }
                UploadFingerprint::record($got[0], (string) $r->FileName, $got[1], 'backfill');
                $n++;
            }
            $this->line("{$n} files fingerprinted, {$miss} not found in storage.");
        }
        $dups = DB::table(UploadFingerprint::TABLE)->where('year_id', $year)->select('sha256', DB::raw('COUNT(*) n'), DB::raw('GROUP_CONCAT(file_name) files'))
            ->groupBy('sha256')->having('n', '>', 1)->orderByDesc('n')->limit(200)->get();
        $out = [];
        foreach ($dups as $d) {
            $names = explode(',', $d->files);
            $claims = DB::connection($conn)->table("y{$year}_claimuploads as u")->join("y{$year}_expenseclaims as e", 'e.ExpId', '=', 'u.ExpId')
                ->whereIn('u.FileName', $names)->where('e.ClaimStatus', '!=', 'Deactivate')->distinct()->get(['e.ExpId', 'e.CrBy', 'e.ClaimStatus']);
            if ($claims->pluck('ExpId')->unique()->count() > 1) {
                $out[] = [substr($d->sha256, 0, 12), $claims->map(fn ($c) => "{$c->ExpId} (emp {$c->CrBy}, {$c->ClaimStatus})")->implode('; ')];
            }
        }
        $this->table(['File fingerprint', 'Claims with the identical file'], $out);
        $this->info(count($out).' files attached to more than one live claim.');

        return self::SUCCESS;
    }
}
