<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;

/**
 * VERIFY-ZERO-01 (23 Nov 2026, "Decision for You" testing point 3): when a verifier changes a claim of a month that is
 * already verified (Verify Screen > Approved), the month's Verified Amount is worked out again from its claims, so the
 * month list and the claim screen show the same figure. The claimed amount of the month is never changed here.
 * Months not yet verified are left alone (their total is set when the verifier submits the month).
 */
class MonthVerifiedTotal
{
    public static function refreshForClaim(string $conn, int $yearId, int $expId): void
    {
        try {
            $c = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->first(['CrBy', 'ClaimMonth']);
            if ($c) {
                self::refresh($conn, $yearId, (int) $c->CrBy, (int) $c->ClaimMonth);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function refresh(string $conn, int $yearId, int $empId, int $month): void
    {
        $mf = "y{$yearId}_monthexpensefinal";
        $row = DB::connection($conn)->table($mf)->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->first(['id', 'Verified_Date', 'Verified_Amount']);
        if (! $row || ! \App\Support\ZeroDate::has($row->Verified_Date) || (string) $row->Verified_Date === '1970-01-01') {
            return;
        }
        $sum = (float) DB::connection($conn)->table("y{$yearId}_expenseclaims")
            ->where('ClaimYearId', $yearId)->where('CrBy', $empId)->where('ClaimMonth', $month)
            ->where('FilledOkay', 1)->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])->where('VerifyBy', '>', 0)
            ->sum('VerifyTAmt');
        if (abs($sum - (float) $row->Verified_Amount) > 0.009) {
            DB::connection($conn)->table($mf)->where('id', $row->id)->update(['Verified_Amount' => $sum]);
        }
    }
}
