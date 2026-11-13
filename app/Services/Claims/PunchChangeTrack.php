<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PUNCH-TRACK-01 (7 Nov 2026): what the data team changed when punching a claim.
 *
 * The punch save (ClaimViewController::uploadClaimFiles) takes a snapshot of the claim row and its
 * punched detail rows (y{Y}_g{cg}_expensefilldata) before saving, and another after. Every field that
 * changed is written to punch_change_logs (on the claim's own database): old value, new value, who,
 * when. "first_punch" = the claim's own fields changed when the detail was punched for the first time
 * (e.g. the head, bill date or amount the employee uploaded); "re_punch" = an already punched claim was
 * edited. A logging failure never stops the save.
 */
class PunchChangeTrack
{
    public const TABLE = 'punch_change_logs';

    private const CLAIM_FIELDS = ['ClaimId', 'ClaimMonth', 'BillDate', 'FilledTAmt', 'VerifyTAmt', 'odomtr_opening', 'odomtr_closing', 'TotKm', 'RatePerKM', 'ClaimStatus'];

    private const SKIP = ['did', 'id', 'ExpId', 'CrBy', 'CrDate', 'SysDate', 'created_at', 'updated_at', 'UpdatedBy', 'UpdatedDate', 'YearId', 'CompanyId'];

    public static function snapshot(string $conn, int $yearId, int $cgId, int $expId): ?array
    {
        try {
            $db = DB::connection($conn);
            $claim = $db->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->first();
            $fillTable = "y{$yearId}_g{$cgId}_expensefilldata";
            $fill = Schema::connection($conn)->hasTable($fillTable)
                ? $db->table($fillTable)->where('ExpId', $expId)->get()->map(fn ($r) => (array) $r)->sortBy(fn ($r) => $r['did'] ?? 0)->values()->all()
                : [];

            return ['claim' => $claim ? (array) $claim : [], 'fill' => $fill];
        } catch (\Throwable $e) {
            Log::warning('PunchChangeTrack snapshot failed: '.$e->getMessage());

            return null;
        }
    }

    public static function record(string $conn, int $yearId, int $cgId, int $expId, ?array $before, int $companyId): int
    {
        if ($before === null) {
            return 0;
        }
        try {
            $after = self::snapshot($conn, $yearId, $cgId, $expId);
            if ($after === null) {
                return 0;
            }
            $mode = $before['fill'] === [] ? 'first_punch' : 're_punch';
            $changes = [];
            foreach (self::CLAIM_FIELDS as $f) {
                if (array_key_exists($f, $after['claim']) && ! self::same($before['claim'][$f] ?? null, $after['claim'][$f])) {
                    $changes[] = ['Claim: '.$f, $before['claim'][$f] ?? null, $after['claim'][$f]];
                }
            }
            if ($mode === 're_punch') {
                $n = max(count($before['fill']), count($after['fill']));
                for ($i = 0; $i < $n; $i++) {
                    $b = $before['fill'][$i] ?? [];
                    $a = $after['fill'][$i] ?? [];
                    $label = $n > 1 ? 'Detail line '.($i + 1).': ' : 'Detail: ';
                    if ($b === [] || $a === []) {
                        $changes[] = [$label.'line', $b === [] ? null : 'present', $a === [] ? 'removed' : 'added'];
                        continue;
                    }
                    foreach ($a as $col => $v) {
                        if (in_array($col, self::SKIP, true) || ! array_key_exists($col, $b)) {
                            continue;
                        }
                        if (! self::same($b[$col], $v)) {
                            $changes[] = [$label.$col, $b[$col], $v];
                        }
                    }
                }
            }
            if ($changes === []) {
                return 0;
            }
            self::ensureTable($conn);
            $user = auth()->user();
            $now = now();
            $rows = array_map(fn ($c) => [
                'company_id' => $companyId, 'year_id' => $yearId, 'exp_id' => $expId,
                'employee_id' => (int) ($after['claim']['CrBy'] ?? 0), 'claim_id' => (int) ($after['claim']['ClaimId'] ?? 0),
                'claim_month' => (int) ($after['claim']['ClaimMonth'] ?? 0), 'field' => mb_substr($c[0], 0, 100),
                'old_value' => $c[1] === null ? null : mb_substr((string) $c[1], 0, 2000),
                'new_value' => $c[2] === null ? null : mb_substr((string) $c[2], 0, 2000),
                'mode' => $mode, 'changed_by' => $user->id ?? null,
                'changed_by_name' => mb_substr(trim((string) (session('emp_name') ?: ($user->name ?? ''))), 0, 150) ?: null,
                'created_at' => $now,
            ], $changes);
            DB::connection($conn)->table(self::TABLE)->insert($rows);

            return count($rows);
        } catch (\Throwable $e) {
            Log::warning('PunchChangeTrack record failed: '.$e->getMessage());

            return 0;
        }
    }

    private static function same($a, $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.005;
        }

        return trim((string) $a) === trim((string) $b);
    }

    public static function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        \App\Support\SafeTable::create($conn, self::TABLE, function ($t) {
            $t->id();
            $t->unsignedTinyInteger('company_id')->default(1);
            $t->unsignedSmallInteger('year_id');
            $t->unsignedBigInteger('exp_id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedInteger('claim_id')->default(0);
            $t->unsignedTinyInteger('claim_month')->default(0);
            $t->string('field', 100);
            $t->text('old_value')->nullable();
            $t->text('new_value')->nullable();
            $t->string('mode', 20);
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->string('changed_by_name', 150)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['year_id', 'created_at']);
            $t->index(['employee_id']);
            $t->index(['exp_id']);
        });
    }
}
