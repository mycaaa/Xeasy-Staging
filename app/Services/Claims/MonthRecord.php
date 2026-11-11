<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;

/**
 * MONTH-REC-01 (10 Nov 2026): a claim must have its employee-month row in y{year}_monthexpensefinal,
 * or it is missing from the month screens, the submit/approve/pay flow and the month totals.
 *
 * Found: bills uploaded from the mobile app ("Upload bills") are saved without a bill date or month;
 * data punching fills them in later in the new app, and that save had its month-row code commented out.
 * 74 claims of FY 2026-27 on the test copy have no month row; most were punched this way.
 *
 * ensure() adds the row (Status 'Open') when the claim has a month and its bill date falls inside the
 * year; a bill from another financial year gets no row in this year (the bill-date rule refuses it).
 */
class MonthRecord
{
    /** @return string|null 'created', 'exists' or null when the claim has no usable month */
    public static function ensure(string $conn, int $yearId, int $expId): ?string
    {
        $db = DB::connection($conn);
        $c = $db->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->first(['CrBy', 'ClaimMonth', 'BillDate', 'ClaimStatus']);
        if (! $c || (int) $c->CrBy <= 0 || (int) $c->ClaimMonth < 1 || (int) $c->ClaimMonth > 12 || in_array($c->ClaimStatus, ['Deactivate', 'Draft'], true)) {
            return null;
        }
        if (! self::inYear($conn, $yearId, (string) $c->BillDate)) {
            return null;
        }

        return self::ensureRow($conn, $yearId, (int) $c->CrBy, (int) $c->ClaimMonth);
    }

    public static function ensureRow(string $conn, int $yearId, int $empId, int $month): string
    {
        $t = "y{$yearId}_monthexpensefinal";
        $db = DB::connection($conn);
        if ($db->table($t)->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->exists()) {
            return 'exists';
        }
        // INSERT IGNORE: the D-05 unique key (EmployeeID, Month, YearId) stops a second row when two saves race
        $db->statement("INSERT IGNORE INTO `{$t}` (EmployeeID, Month, YearId, Status, Crdate) VALUES (?, ?, ?, 'Open', ?)", [$empId, $month, $yearId, now()->toDateString()]);

        return 'created';
    }

    public static function inYear(string $conn, int $yearId, string $billDate): bool
    {
        if ($billDate === '' || str_starts_with($billDate, '0000')) {
            return false;
        }
        $fy = DB::connection($conn)->table('financialyear')->where('YearId', $yearId)->first();
        if (! $fy) {
            return true;
        }
        $from = (string) ($fy->FromDate ?? $fy->StartDate ?? '');
        $to = (string) ($fy->Todate ?? $fy->ToDate ?? $fy->EndDate ?? '');

        return ($from === '' || $billDate >= substr($from, 0, 10)) && ($to === '' || $billDate <= substr($to, 0, 10));
    }

    /** Claims of the year with a month but no month row (in-year bills, not Draft/Deactivate). */
    public static function missing(string $conn, int $yearId)
    {
        return DB::connection($conn)->table("y{$yearId}_expenseclaims as c")
            ->leftJoin("y{$yearId}_monthexpensefinal as m", function ($j) use ($yearId) {
                $j->on('m.EmployeeID', '=', 'c.CrBy')->on('m.Month', '=', 'c.ClaimMonth')->where('m.YearId', '=', $yearId);
            })
            ->whereNull('m.EmployeeID')->where('c.CrBy', '>', 0)->whereBetween('c.ClaimMonth', [1, 12])
            ->whereNotIn('c.ClaimStatus', ['Deactivate', 'Draft'])
            ->orderByDesc('c.ExpId')
            ->get(['c.ExpId', 'c.CrBy', 'c.ClaimMonth', 'c.BillDate', 'c.ClaimStatus', 'c.ClaimId', 'c.FilledBy', 'c.CrDate']);
    }
}
