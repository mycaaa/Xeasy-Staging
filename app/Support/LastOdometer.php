<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * HIER-LOOP-01 (16 Nov 2026): the latest odometer bill (closing reading > 0) of each team member in ONE query.
 * The team odometer screens ran one query per team member.
 */
class LastOdometer
{
    /**
     * @param  int[]  $employeeIds
     * @param  string[]  $cols  columns of the claims table
     * @return array<int,object> employee id => latest row
     */
    public static function forEmployees(string $conn, string $table, array $employeeIds, array $cols): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
        if (! $ids) {
            return [];
        }
        $in = implode(',', $ids);
        $sel = implode(', ', array_map(fn ($c) => 't.`'.str_replace('`', '', $c).'`', array_unique(array_merge(['CrBy'], $cols))));
        $key = "CONCAT(BillDate, '#', LPAD(ExpId, 12, '0'))";
        $rows = DB::connection($conn)->select("SELECT {$sel} FROM `{$table}` t
            JOIN (SELECT CrBy, MAX({$key}) k FROM `{$table}`
                  WHERE CrBy IN ({$in}) AND ClaimId = 7 AND ClaimStatus NOT IN ('Deactivate','Deactivat') AND odomtr_closing > 0
                  GROUP BY CrBy) x ON x.CrBy = t.CrBy AND CONCAT(t.BillDate, '#', LPAD(t.ExpId, 12, '0')) = x.k");
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->CrBy] = $r;
        }

        return $out;
    }
}
