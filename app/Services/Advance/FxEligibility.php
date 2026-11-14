<?php

namespace App\Services\Advance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADV-FX-ELIG-01 (14 Nov 2026): who may request an advance in a foreign currency. Set in Advance Configuration Setting
 * (all employees, or by department, grade or named employee, per company). Nobody is eligible until it is set.
 * The request form offers non-INR currencies only to eligible employees, and the submission is refused for any
 * employee on the request (self or team members) who is not eligible.
 */
class FxEligibility
{
    public static function setting(int $companyId): ?object
    {
        try {
            if (! Schema::hasTable('advance_fx_eligibility')) {
                return null;
            }

            return DB::table('advance_fx_eligibility')->where('company_id', $companyId)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{all:bool, departments:int[], grades:int[], employees:int[]} */
    public static function rules(int $companyId): array
    {
        $s = self::setting($companyId);
        $ids = fn ($v) => array_values(array_unique(array_map('intval', array_filter((array) json_decode((string) $v, true), 'is_numeric'))));

        return [
            'all' => (bool) ($s->all_employees ?? false),
            'departments' => $s ? $ids($s->department_ids) : [],
            'grades' => $s ? $ids($s->grade_ids) : [],
            'employees' => $s ? $ids($s->employee_ids) : [],
        ];
    }

    /** @param int[] $employeeIds  @return array<int,bool> employee id => eligible */
    public static function check(array $employeeIds, int $companyId): array
    {
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        $r = self::rules($companyId);
        $out = [];
        if (! $employeeIds) {
            return $out;
        }
        if ($r['all']) {
            return array_fill_keys($employeeIds, true);
        }
        $hr = [];
        if ($r['departments'] || $r['grades']) {
            try {
                $hr = DB::connection('hrims')->table('hrm_employee_general')->whereIn('EmployeeID', $employeeIds)
                    ->get(['EmployeeID', 'DepartmentId', 'GradeId'])->keyBy('EmployeeID');
            } catch (\Throwable $e) {
                $hr = collect();
            }
        }
        foreach ($employeeIds as $id) {
            $g = $hr[$id] ?? null;
            $out[$id] = in_array($id, $r['employees'], true)
                || ($g && in_array((int) $g->DepartmentId, $r['departments'], true))
                || ($g && in_array((int) $g->GradeId, $r['grades'], true));
        }

        return $out;
    }

    public static function eligible(int $employeeId, int $companyId): bool
    {
        return (bool) (self::check([$employeeId], $companyId)[$employeeId] ?? false);
    }
}
