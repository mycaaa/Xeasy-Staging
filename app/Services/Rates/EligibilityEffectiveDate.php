<?php

namespace App\Services\Rates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RATE-EFF-01 (HR decision 7 Nov 2026): when an employee's vehicle or vehicle policy changes in the
 * middle of a month, the new rate applies from the date of the change, not to the whole month.
 *
 * Each HRIMS eligibility row (hrm_employee_eligibility) is effective from the day it was created
 * (EligCreatedDate), unless HR sets another effective date for it here (eligibility_effective_dates,
 * in the expense database - HRIMS is not changed). A bill uses the eligibility row in force on the
 * bill date; when no row is in force yet, the employee's active row is used as before.
 */
class EligibilityEffectiveDate
{
    public const TABLE = 'eligibility_effective_dates';

    public static function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        Schema::connection($conn)->create(self::TABLE, function ($t) {
            $t->unsignedBigInteger('eligibility_id')->primary();
            $t->unsignedBigInteger('employee_id');
            $t->date('effective_from');
            $t->unsignedBigInteger('set_by')->nullable();
            $t->string('remark', 255)->nullable();
            $t->timestamp('updated_at')->useCurrent();
            $t->index('employee_id');
        });
    }

    /** @return array<int, string> eligibility_id => Y-m-d (HR overrides for one employee) */
    public static function overrides(string $conn, int $employeeId): array
    {
        try {
            if (! Schema::connection($conn)->hasTable(self::TABLE)) {
                return [];
            }

            return DB::connection($conn)->table(self::TABLE)->where('employee_id', $employeeId)->pluck('effective_from', 'eligibility_id')
                ->map(fn ($d) => substr((string) $d, 0, 10))->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** The eligibility row in force on the bill date (id), or null. */
    public static function rowFor(string $hrimsConn, string $expConn, int $employeeId, string $billDate): ?int
    {
        $rows = DB::connection($hrimsConn)->table('hrm_employee_eligibility')->where('EmployeeID', $employeeId)
            ->get(['EligibilityId', 'EligCreatedDate']);
        if ($rows->count() < 2) {
            return null;
        }
        $ov = self::overrides($expConn, $employeeId);
        $bill = date('Y-m-d', strtotime($billDate));
        $best = null;
        foreach ($rows as $r) {
            $eff = $ov[(int) $r->EligibilityId] ?? substr((string) $r->EligCreatedDate, 0, 10);
            if ($eff === '' || $eff < '2000-01-01' || $eff > $bill) {
                continue;
            }
            if ($best === null || $eff > $best[1] || ($eff === $best[1] && (int) $r->EligibilityId > $best[0])) {
                $best = [(int) $r->EligibilityId, $eff];
            }
        }

        return $best[0] ?? null;
    }

    public static function set(string $conn, int $eligibilityId, int $employeeId, string $date, ?int $by, ?string $remark): void
    {
        self::ensureTable($conn);
        DB::connection($conn)->table(self::TABLE)->updateOrInsert(
            ['eligibility_id' => $eligibilityId],
            ['employee_id' => $employeeId, 'effective_from' => date('Y-m-d', strtotime($date)), 'set_by' => $by, 'remark' => $remark ? mb_substr($remark, 0, 255) : null, 'updated_at' => now()]
        );
    }
}
