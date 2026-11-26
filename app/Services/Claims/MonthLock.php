<?php

namespace App\Services\Claims;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MONTH-LOCK-01 (26 Nov 2026, testing point 26): once an employee has submitted the claim of a month (the month row
 * is Closed), nothing more can be added to that month - no bill, activity, DA or odometer reading. A bill or an
 * activity dated in a submitted month goes into the current month's claim instead (it keeps its own bill date); a DA
 * or an odometer reading of a submitted month is refused. Before this, an activity uploaded after the month was
 * submitted joined that month at Finance Approval without punching, verification or manager approval (employee 1531).
 */
class MonthLock
{
    /** Has the employee submitted the claim of this month? */
    public static function closed(string $conn, int $yearId, int $empId, int $month): bool
    {
        if ($empId <= 0 || $month < 1 || $month > 12 || $yearId <= 0) {
            return false;
        }
        try {
            return DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $empId)->where('Month', $month)
                ->where('YearId', $yearId)->where('Status', 'Closed')->exists();
        } catch (\Throwable $e) {
            Log::warning('MONTH-LOCK-01: month check failed', ['e' => $e->getMessage()]);

            return false;
        }
    }

    /** "Oct" for 10. */
    public static function name(int $month): string
    {
        return Carbon::create(2000, max(1, min(12, $month)), 1)->format('M');
    }

    /**
     * The claim month for a bill or an activity: its own month, or the current month when its own is submitted.
     *
     * @return array{0:int|null, 1:string|null} [month, message]; month null = refused (message says why)
     */
    public static function billMonth(string $conn, int $yearId, int $empId, string $billDate): array
    {
        $own = (int) date('n', strtotime($billDate));
        if (! self::closed($conn, $yearId, $empId, $own)) {
            return [$own, null];
        }
        $now = now('Asia/Kolkata');
        $cur = (int) $now->format('n');
        $fyStart = (int) $now->format('n') >= 4 ? (int) $now->format('Y') : (int) $now->format('Y') - 1;
        $sameYear = (int) app(\App\Services\ClaimRules\ClaimSubmissionValidator::class)->fyStartYear($yearId, $conn) === $fyStart;
        if (! $sameYear || $cur === $own || self::closed($conn, $yearId, $empId, $cur)) {
            return [null, 'Your '.self::name($own).' claim is already submitted, so this bill cannot be added to it'
                .($sameYear && $cur !== $own ? ' and the '.self::name($cur).' claim is submitted too' : '').'. Please contact Finance.'];
        }

        return [$cur, 'Your '.self::name($own).' claim is already submitted: this bill is added to your '.self::name($cur).' claim.'];
    }

    /** A DA or an odometer reading of a submitted month is refused. */
    public static function refuseDated(string $conn, int $yearId, int $empId, string $date, string $what): ?string
    {
        $m = (int) date('n', strtotime($date));

        return self::closed($conn, $yearId, $empId, $m)
            ? 'Your '.self::name($m).' claim is already submitted; '.$what.' of '.self::name($m).' cannot be added any more.' : null;
    }
}
