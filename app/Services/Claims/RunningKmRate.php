<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RKM-RATE-02 (21 Nov 2026, "Decision for You" testing point 5): the running-km rate comes from the employee's
 * eligibility (RKM-RATE-01). For exceptional cases a verifier may set the rate by hand, with a compulsory reason;
 * every change is logged here (who, when, from which address, old / new / eligible rate, old / new amount) and the
 * hand-set rate is then used when the readings are updated or verified, until it is set back to the eligible rate.
 */
class RunningKmRate
{
    public static function ready(): bool
    {
        try {
            return Schema::hasTable('running_km_rate_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The hand-set rate in force for the claim, or null when the eligible rate applies. */
    public static function override(string $conn, int $yearId, int $expId): ?array
    {
        if (! self::ready()) {
            return null;
        }
        $r = DB::table('running_km_rate_logs')->where('conn', $conn)->where('year_id', $yearId)->where('exp_id', $expId)->orderByDesc('id')->first();
        if (! $r || $r->action !== 'manual') {
            return null;
        }

        return ['rate' => (float) $r->new_rate, 'reason' => (string) $r->reason, 'by' => (string) $r->user_name, 'at' => (string) $r->created_at];
    }

    /** ExpIds (of these) with a hand-set rate in force. */
    public static function manualIds(string $conn, int $yearId, array $expIds): array
    {
        if (! self::ready() || ! $expIds) {
            return [];
        }
        $last = DB::table('running_km_rate_logs')->where('conn', $conn)->where('year_id', $yearId)->whereIn('exp_id', $expIds)
            ->selectRaw('exp_id, MAX(id) mid')->groupBy('exp_id')->pluck('mid')->all();

        return DB::table('running_km_rate_logs')->whereIn('id', $last ?: [0])->where('action', 'manual')->pluck('exp_id')->map(fn ($v) => (int) $v)->all();
    }

    public static function log(array $row): void
    {
        DB::table('running_km_rate_logs')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
    }

    public static function history(string $conn, int $yearId, int $expId)
    {
        return self::ready() ? DB::table('running_km_rate_logs')->where('conn', $conn)->where('year_id', $yearId)->where('exp_id', $expId)->orderByDesc('id')->get() : collect();
    }
}
