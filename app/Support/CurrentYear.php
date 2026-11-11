<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * STAGE-01: the financial year to use when the session has none.
 *
 * Reports used session('year_id', 7), i.e. FY2025-26 for VNR, whenever the session had
 * no year. The fallback is now the company's financialyear row that contains today's
 * date (YearIds differ per company: VNR 8 = 2026-27, VVNR 6 = 2026-27).
 */
class CurrentYear
{
    public static function id(?string $connection = null): int
    {
        $fromSession = (int) session('year_id', 0);
        if ($fromSession > 0) {
            return $fromSession;
        }
        $connection = $connection ?: session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
        try {
            $today = now('Asia/Kolkata')->toDateString();
            $id = DB::connection($connection)->table('financialyear')
                ->where('FromDate', '<=', $today)->where('Todate', '>=', $today)
                ->orderByDesc('YearId')->value('YearId');
            if ($id) {
                return (int) $id;
            }
            $id = DB::connection($connection)->table('financialyear')->where('Status', 'Active')->orderByDesc('YearId')->value('YearId');
            if ($id) {
                return (int) $id;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        try {
            return (int) (DB::connection($connection)->table('financialyear')->max('YearId') ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
