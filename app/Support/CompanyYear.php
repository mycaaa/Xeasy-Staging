<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * VVNR-18NOV-05 (18 Nov 2026): each company database numbers its financial years differently (VNR 8 = 2026-27,
 * VVNR 6 = 2026-27). Screens that show another company's data than the login's (a VNR manager whose team is in
 * VVNR) must use that company's own number for the same financial year - by dates, not by label or number.
 */
class CompanyYear
{
    /** The target company's YearId for the same financial year as $yearId in $fromConn (by start date). */
    public static function same(string $fromConn, int $yearId, string $toConn): int
    {
        if ($fromConn === $toConn) {
            return $yearId;
        }
        try {
            $from = DB::connection($fromConn)->table('financialyear')->where('YearId', $yearId)->value('FromDate');
            $date = $from ? date('Y-m-d', strtotime($from.' +1 day')) : now('Asia/Kolkata')->toDateString();

            return self::containing($toConn, $date) ?: $yearId;
        } catch (\Throwable $e) {
            return $yearId;
        }
    }

    /** YearId in $conn whose financial year contains $date (today when null); 0 when none. */
    public static function containing(string $conn, ?string $date = null): int
    {
        $date = $date ?: now('Asia/Kolkata')->toDateString();
        try {
            return (int) DB::connection($conn)->table('financialyear')->where('FromDate', '<=', $date)->where('Todate', '>=', $date)->orderByDesc('YearId')->value('YearId');
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
