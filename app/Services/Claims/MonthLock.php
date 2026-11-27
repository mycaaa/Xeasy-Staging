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
     * The claim month for a bill or an activity: its own month while that month is not submitted.
     * MONTH-LOCK-02 (27 Nov 2026, testing point 33): a submitted month is locked for any further bill, whatever stage
     * the claim is at - the bill is refused (it used to go into the current month's claim, where it showed in Draft and
     * Data Punching but never reached the verifier with the submitted claim). Finance (Super Admin / Admin) reopens the
     * month (Employee page > Return) when a bill really has to be added; then uploading works as usual.
     *
     * @return array{0:int|null, 1:string|null} [month, message]; month null = refused (message says why)
     */
    public static function billMonth(string $conn, int $yearId, int $empId, string $billDate): array
    {
        $own = (int) date('n', strtotime($billDate));
        if (! self::closed($conn, $yearId, $empId, $own)) {
            return [$own, null];
        }

        return [null, self::lockedMessage($own)];
    }

    /** MONTH-LOCK-02: what the employee is told when a bill of a submitted month is refused. */
    public static function lockedMessage(int $month): string
    {
        return 'Your '.self::name($month).' claim is already submitted, so no more bills can be added to '.self::name($month)
            .'. If a bill has to be added, ask Finance (Super Admin / Admin) to reopen '.self::name($month).'.';
    }

    /** MONTH-LOCK-02: the submitted months of an employee in a year (for the upload form). */
    public static function closedMonths(string $conn, int $yearId, int $empId): array
    {
        if ($empId <= 0 || $yearId <= 0) {
            return [];
        }
        try {
            return DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $empId)->where('YearId', $yearId)
                ->where('Status', 'Closed')->pluck('Month')->map(fn ($m) => (int) $m)->unique()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** MONTH-LOCK-02: the claim month of a bill (ClaimMonth, else its bill date). */
    public static function claimMonth(string $conn, int $yearId, int $expId): int
    {
        try {
            $r = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->first(['ClaimMonth', 'BillDate']);
        } catch (\Throwable $e) {
            return 0;
        }

        return $r ? ((int) $r->ClaimMonth ?: (int) date('n', strtotime((string) $r->BillDate))) : 0;
    }

    /** A DA or an odometer reading of a submitted month is refused. */
    public static function refuseDated(string $conn, int $yearId, int $empId, string $date, string $what): ?string
    {
        $m = (int) date('n', strtotime($date));

        return self::closed($conn, $yearId, $empId, $m)
            ? 'Your '.self::name($m).' claim is already submitted; '.$what.' of '.self::name($m).' cannot be added any more.' : null;
    }
}
