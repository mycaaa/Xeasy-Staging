<?php

namespace App\Services\Btc;

use Illuminate\Support\Facades\DB;

/**
 * BTC-BUS-01 (26 Nov 2026, testing point 27): the bus class an employee may take (AC / Non-AC), from the eligibility
 * master (train / outside-HQ class), and the highest class a group may ask for (the lowest of its employees).
 */
class BusEligibility
{
    /** @return array<int, array{employee_id:int, code:string, name:string, eligible:string}> by EmployeeID */
    public static function of(array $employeeIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        if (! $ids) {
            return [];
        }
        $emp = DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $ids)
            ->get(['EmployeeID', 'EmpCode', 'Fname', 'Lname'])->keyBy('EmployeeID');
        $elig = DB::connection('hrims')->table('hrm_employee_eligibility')->whereIn('EmployeeID', $ids)->where('Status', 'A')
            ->orderByDesc('EligibilityId')->get(['EmployeeID', 'Train_Class', 'TravelClass_Outside_Hq'])->unique('EmployeeID')->keyBy('EmployeeID');
        $out = [];
        foreach ($ids as $id) {
            $e = $emp[$id] ?? null;
            if (! $e) {
                continue;
            }
            $cls = trim(($elig[$id]->Train_Class ?? '').' '.($elig[$id]->TravelClass_Outside_Hq ?? ''));
            $out[$id] = ['employee_id' => $id, 'code' => (string) $e->EmpCode, 'name' => trim($e->Fname.' '.$e->Lname),
                'eligible' => self::classFrom($cls)];
        }

        return $out;
    }

    public static function classFrom(string $text): string
    {
        $t = strtoupper($text);
        $t = str_replace(['NON AC', 'NON-AC', 'NONAC'], '', $t);

        return preg_match('/\bAC\b|AC-|ECONOMY\/AC/', $t) ? 'AC' : 'Non-AC';
    }

    public static function rank(?string $class): int
    {
        return (int) (config('btc.bus_classes')[$class] ?? 0);
    }

    /** The highest class every employee passenger may take ('AC' only if all may). */
    public static function groupMax(array $passengers): string
    {
        $max = 'AC';
        foreach ($passengers as $p) {
            if (($p['type'] ?? '') === 'employee' && self::rank($p['eligible'] ?? 'Non-AC') < self::rank($max)) {
                $max = $p['eligible'] ?? 'Non-AC';
            }
        }

        return $max;
    }
}
