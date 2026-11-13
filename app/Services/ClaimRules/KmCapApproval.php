<?php

namespace App\Services\ClaimRules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KM-CAP-01 (HR decision 10 Nov 2026): Breeding / Breeding Support running km above the monthly limit
 * (1,200 km) needs the HOD's approval. One row per claim in km_cap_approvals (pending / approved / rejected).
 * The HOD is the highest person in the employee's reporting chain who is still in the employee's department
 * (if that is the employee, the employee's manager). Until the HOD approves, the claim cannot be approved
 * (ClaimTransition refuses the Approved step).
 */
class KmCapApproval
{
    public const TABLE = 'km_cap_approvals';

    public static function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        \App\Support\SafeTable::create($conn, self::TABLE, function ($t) {
            $t->id();
            $t->unsignedInteger('company_id')->default(1);
            $t->unsignedInteger('year_id');
            $t->unsignedBigInteger('exp_id');
            $t->unsignedInteger('employee_id')->index();
            $t->unsignedTinyInteger('month');
            $t->string('rule', 60);
            $t->decimal('month_km', 10, 2);
            $t->decimal('limit_km', 10, 2);
            $t->unsignedInteger('hod_id')->nullable()->index();
            $t->string('status', 12)->default('pending')->index();
            $t->string('remark', 500)->nullable();
            $t->unsignedInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['year_id', 'exp_id']);
        });
    }

    /** Record (or refresh) the need for HOD approval for one claim. */
    public static function request(string $conn, int $companyId, int $yearId, int $expId, int $empId, int $month, string $rule, float $monthKm, float $limit): void
    {
        self::ensureTable($conn);
        $db = DB::connection($conn);
        $cur = $db->table(self::TABLE)->where('year_id', $yearId)->where('exp_id', $expId)->first();
        $hod = self::hodFor($empId);
        if ($cur) {
            // a decision stands unless the km went up since
            $reopen = $cur->status !== 'pending' && $monthKm > (float) $cur->month_km + 0.5;
            $db->table(self::TABLE)->where('id', $cur->id)->update([
                'month_km' => $monthKm, 'limit_km' => $limit, 'hod_id' => $hod, 'rule' => $rule, 'updated_at' => now(),
            ] + ($reopen ? ['status' => 'pending', 'decided_by' => null, 'decided_at' => null] : []));

            return;
        }
        $db->table(self::TABLE)->insert([
            'company_id' => $companyId, 'year_id' => $yearId, 'exp_id' => $expId, 'employee_id' => $empId, 'month' => $month,
            'rule' => $rule, 'month_km' => $monthKm, 'limit_km' => $limit, 'hod_id' => $hod, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Claims (ExpId => status) that may not be approved yet: pending or rejected HOD approval. */
    public static function blocking($db, int $yearId, iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            if ((int) ($r->ClaimId ?? 0) === 7) {
                $ids[] = (int) $r->ExpId;
            }
        }
        if (! $ids || ! Schema::connection($db->getName())->hasTable(self::TABLE)) {
            return [];
        }

        return $db->table(self::TABLE)->where('year_id', $yearId)->whereIn('exp_id', $ids)->whereIn('status', ['pending', 'rejected'])
            ->pluck('status', 'exp_id')->map(fn ($s) => (string) $s)->all();
    }

    /** The department head for an employee (see class note). */
    public static function hodFor(int $empId): ?int
    {
        $gen = fn ($id) => DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $id)->first(['EmployeeID', 'DepartmentId', 'RepEmployeeID']);
        try {
            $me = $gen($empId);
            if (! $me) {
                return null;
            }
            $dept = (int) $me->DepartmentId;
            $cur = $me;
            $seen = [$empId => true];
            for ($i = 0; $i < 10; $i++) {
                $rep = (int) $cur->RepEmployeeID;
                if ($rep <= 0 || isset($seen[$rep])) {
                    break;
                }
                $r = $gen($rep);
                if (! $r || (int) $r->DepartmentId !== $dept) {
                    break;
                }
                $seen[$rep] = true;
                $cur = $r;
            }
            $hod = (int) $cur->EmployeeID;

            return $hod === $empId ? ((int) $me->RepEmployeeID ?: null) : $hod;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
