<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HARDCOPY-PAY-01 (Finance decision 7 Nov 2026): a month is not paid while the PREVIOUS month's
 * hard copy (bills) has not reached HO. "Received" = RecevingDate set on that month's row.
 * A previous month needs a hard copy only when the employee submitted it with an amount.
 * Both companies (VVNR pays before verification, but the hard copy rule still applies).
 */
class HardcopyPaymentGate
{
    /** Month numbers run Apr (4) .. Mar (3); April's previous month is March of the year before. */
    public static function previous(int $yearId, int $month): array
    {
        return $month === 4 ? [$yearId - 1, 3] : [$yearId, $month === 1 ? 12 : $month - 1];
    }

    /**
     * Null when the month may be paid, otherwise the message to show.
     */
    public static function blockReason(string $conn, int $yearId, int $employeeId, int $month): ?string
    {
        if (! config('claim_rules.hardcopy_before_payment', true)) {
            return null;
        }
        [$py, $pm] = self::previous($yearId, $month);
        $table = "y{$py}_monthexpensefinal";
        if (! Schema::connection($conn)->hasTable($table)) {
            return null;
        }
        $cols = array_flip(Schema::connection($conn)->getColumnListing($table));
        $row = DB::connection($conn)->table($table)->where('EmployeeID', $employeeId)->where('Month', $pm)
            ->when(isset($cols['YearId']), fn ($q) => $q->where('YearId', $py))->first();
        if (! $row || ! self::needsHardcopy($conn, $py, $employeeId, $pm, $row)) {
            return null;
        }
        if (self::dateSet($row->RecevingDate ?? null)) {
            return null;
        }
        $name = date('M Y', mktime(0, 0, 0, $pm, 1, self::calendarYear($conn, $py, $pm)));

        return "Payment is on hold: the hard copy of {$name} has not been received at HO yet. Ask DMS to mark it received (or the employee to submit it), then approve this payment.";
    }

    private static function needsHardcopy(string $conn, int $yearId, int $employeeId, int $month, object $row): bool
    {
        $submitted = self::dateSet($row->DateOfSubmit ?? null) || in_array((string) ($row->Status ?? ''), ['Closed', 'Submitted'], true);
        if (! $submitted) {
            return false;
        }
        $claims = "y{$yearId}_expenseclaims";
        if (! Schema::connection($conn)->hasTable($claims)) {
            return true;
        }

        return DB::connection($conn)->table($claims)->where('CrBy', $employeeId)->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)->where('FilledTAmt', '>', 0)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->exists();
    }

    private static function dateSet($d): bool
    {
        return ! empty($d) && ! str_starts_with((string) $d, '0000-00-00');
    }

    private static function calendarYear(string $conn, int $yearId, int $month): int
    {
        try {
            $from = DB::connection($conn)->table('financialyear')->where('YearId', $yearId)->value('FromDate');
            $start = $from ? (int) substr((string) $from, 0, 4) : 2018 + $yearId;
        } catch (\Throwable $e) {
            $start = 2018 + $yearId;
        }

        return $month >= 4 ? $start : $start + 1;
    }
}
