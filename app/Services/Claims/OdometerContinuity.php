<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M-02: odometer readings that go backwards or jump are flagged.
 *
 * For a running-km claim the opening reading is compared with the employee's previous closing
 * reading on the same vehicle type (the last earlier running-km claim, this year or, in April,
 * the year before):
 *   overlap   opening below the previous closing by up to 1,000 km -> those km were claimed before
 *   backward  opening below the previous closing by more than 1,000 km -> reading or vehicle changed
 *   long day  more than 500 km in one day
 * Nothing is blocked (a new vehicle or a meter change can be genuine). The upload gets a warning,
 * the claim view shows a red panel for the verifier, and odo_continuity_flags keeps the record.
 */
class OdometerContinuity
{
    public const TABLE = 'odo_continuity_flags';

    public static function previous(string $conn, int $yearId, object $c): ?object
    {
        foreach ([$yearId, $yearId - 1] as $y) {
            $t = "y{$y}_expenseclaims";
            if ($y < 1 || ! Schema::connection($conn)->hasTable($t)) {
                continue;
            }
            $row = DB::connection($conn)->table($t)
                ->where('ClaimId', 7)->where('CrBy', $c->CrBy)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])
                ->where('odomtr_closing', '>', 0)
                ->when(! empty($c->WType), fn ($q) => $q->where('WType', $c->WType))
                ->where(function ($q) use ($c, $y, $yearId) {
                    $q->where('BillDate', '<', $c->BillDate);
                    if ($y === $yearId) {
                        $q->orWhere(fn ($q2) => $q2->where('BillDate', $c->BillDate)->where('ExpId', '<', $c->ExpId));
                    }
                })
                ->orderByDesc('BillDate')->orderByDesc('ExpId')
                ->first(['ExpId', 'BillDate', 'odomtr_opening', 'odomtr_closing', 'WType']);
            if ($row) {
                $row->year_id = $y;

                return $row;
            }
        }

        return null;
    }

    /** @return array<int, array{kind:string, text:string, prev:?object}> */
    public static function issues(string $conn, int $yearId, int $expId): array
    {
        $t = "y{$yearId}_expenseclaims";
        if (! Schema::connection($conn)->hasTable($t)) {
            return [];
        }
        $c = DB::connection($conn)->table($t)->where('ExpId', $expId)->where('ClaimId', 7)
            ->first(['ExpId', 'CrBy', 'BillDate', 'WType', 'odomtr_opening', 'odomtr_closing', 'TotKm']);
        if (! $c || empty($c->BillDate)) {
            return [];
        }
        $out = [];
        $open = (float) ($c->odomtr_opening ?? 0);
        $prev = $open > 0 ? self::previous($conn, $yearId, $c) : null;
        if ($prev && $open < (float) $prev->odomtr_closing) {
            $gap = round((float) $prev->odomtr_closing - $open);
            $when = date('d-m-Y', strtotime((string) $prev->BillDate));
            $out[] = $gap <= (int) config('claim_rules.odo_overlap_km', 1000)
                ? ['kind' => 'overlap', 'prev' => $prev, 'text' => "Opening reading {$open} is {$gap} km below the closing reading {$prev->odomtr_closing} of {$when} (ExpId {$prev->ExpId}): these km were already claimed."]
                : ['kind' => 'backward', 'prev' => $prev, 'text' => "Opening reading {$open} is {$gap} km below the closing reading {$prev->odomtr_closing} of {$when} (ExpId {$prev->ExpId}): check for a meter change or another vehicle."];
        }
        $km = (float) ($c->TotKm ?? 0);
        if ($km <= 0 && (float) ($c->odomtr_closing ?? 0) > $open && $open > 0) {
            $km = (float) $c->odomtr_closing - $open;
        }
        $maxDay = (int) config('claim_rules.odo_day_km_flag', 500);
        if ($km > $maxDay) {
            $out[] = ['kind' => 'long_day', 'prev' => null, 'text' => "{$km} km in one day (more than {$maxDay} km)."];
        }

        return $out;
    }

    /** Record the issues for a claim (after an upload); returns the warning text or null. */
    public static function flag(string $conn, int $yearId, int $expId): ?string
    {
        try {
            $issues = self::issues($conn, $yearId, $expId);
            self::ensureTable($conn);
            DB::connection($conn)->table(self::TABLE)->where('year_id', $yearId)->where('exp_id', $expId)->delete();
            foreach ($issues as $i) {
                DB::connection($conn)->table(self::TABLE)->insert([
                    'year_id' => $yearId, 'exp_id' => $expId, 'kind' => $i['kind'],
                    'prev_exp_id' => $i['prev']->ExpId ?? null, 'detail' => mb_substr($i['text'], 0, 250), 'flagged_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $issues ? 'Odometer check: '.implode(' ', array_column($issues, 'text')).' The claim is saved and shown to the verifier.' : null;
    }

    public static function panelHtml(string $conn, int $yearId, int $expId): string
    {
        try {
            $issues = self::issues($conn, $yearId, $expId);
        } catch (\Throwable $e) {
            return '';
        }
        if (! $issues) {
            return '';
        }
        $li = implode('', array_map(fn ($i) => '<li>'.e($i['text']).'</li>', $issues));

        return '<div class="alert alert-danger py-2 small mb-2" id="odo-continuity-alert"><strong>Odometer continuity:</strong><ul class="mb-0">'.$li.'</ul></div>';
    }

    public static function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        Schema::connection($conn)->create(self::TABLE, function ($t) {
            $t->id();
            $t->unsignedSmallInteger('year_id');
            $t->unsignedBigInteger('exp_id');
            $t->string('kind', 12);
            $t->unsignedBigInteger('prev_exp_id')->nullable();
            $t->string('detail', 255)->nullable();
            $t->timestamp('flagged_at')->useCurrent();
            $t->index(['year_id', 'exp_id']);
        });
    }
}
