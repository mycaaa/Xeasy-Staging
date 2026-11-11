<?php

namespace App\Services\Retention;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RET-01: retention status of each financial year (config/retention.php).
 *   active    the open year
 *   closed    ended, under 4 years ago: normal closed-year rules
 *   archived  ended more than 4 years ago: read-only (no claim, punch, status or payment change),
 *             still viewable; archive copy made by `records:archive`
 * keep_until = year end + 8 years. Xeasy never deletes a year.
 */
class RecordRetention
{
    public static function years(string $conn): array
    {
        $out = [];
        foreach (DB::connection($conn)->table('financialyear')->orderByDesc('YearId')->get(['YearId', 'Year', 'FromDate', 'Todate', 'Status']) as $y) {
            $out[(int) $y->YearId] = self::describe($y);
        }

        return $out;
    }

    public static function describe(object $y): array
    {
        $end = Carbon::parse($y->Todate);
        $archiveFrom = $end->copy()->addYears((int) config('retention.archive_after_years', 4));
        $keepUntil = $end->copy()->addYears((int) config('retention.keep_years', 8));
        $today = now('Asia/Kolkata')->startOfDay();
        $status = $today->lte($end) ? 'active' : ($today->gt($archiveFrom) ? 'archived' : 'closed');

        return ['year_id' => (int) $y->YearId, 'year' => (string) $y->Year, 'from' => (string) $y->FromDate, 'to' => (string) $y->Todate,
            'status' => $status, 'archive_from' => $archiveFrom->toDateString(), 'keep_until' => $keepUntil->toDateString()];
    }

    public static function isArchived(string $conn, int $yearId): bool
    {
        static $cache = [];
        $k = $conn.'|'.$yearId;
        if (! array_key_exists($k, $cache)) {
            try {
                $y = DB::connection($conn)->table('financialyear')->where('YearId', $yearId)->first(['YearId', 'Year', 'FromDate', 'Todate', 'Status']);
                $cache[$k] = $y ? self::describe($y)['status'] === 'archived' : false;
            } catch (\Throwable $e) {
                $cache[$k] = false;
            }
        }

        return $cache[$k];
    }

    /** Throws when the year is archived (read-only). */
    public static function assertWritable(string $conn, int $yearId, string $what = 'This change'): void
    {
        if ($yearId > 0 && self::isArchived($conn, $yearId)) {
            throw new ArchivedYearException("{$what} is not allowed: financial year ".self::label($conn, $yearId).' is archived (read-only). Archived records are kept unchanged for the 8-year retention period.');
        }
    }

    public static function label(string $conn, int $yearId): string
    {
        return (string) (DB::connection($conn)->table('financialyear')->where('YearId', $yearId)->value('Year') ?: $yearId);
    }

    /** Claims, bill files and month records per year (for the retention screen). */
    public static function counts(string $conn, int $yearId): array
    {
        $c = fn ($t) => Schema::connection($conn)->hasTable($t) ? (int) DB::connection($conn)->table($t)->count() : null;

        return ['claims' => $c("y{$yearId}_expenseclaims"), 'files' => $c("y{$yearId}_claimuploads"), 'months' => $c("y{$yearId}_monthexpensefinal")];
    }
}
