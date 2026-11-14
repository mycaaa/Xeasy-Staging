<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MONTH-ROW-01 (14 Nov 2026): every month an employee has bills in needs its row in yN_monthexpensefinal (Status Open
 * until submitted). Bills uploaded through some web paths did not create it, so the month showed as Open on the
 * Dashboard but was missing from Open months (no Submit button) and the submit had no row to close. A month with bills
 * and no row at all gets an Open row; months that already have a row (open or closed) are never touched.
 */
class MonthRow
{
    public static function ensure(string $conn, int $yearId, int $empId, ?int $month = null): int
    {
        if ($empId <= 0 || $yearId <= 0) {
            return 0;
        }
        try {
            $db = DB::connection($conn);
            $q = $db->table("y{$yearId}_expenseclaims")->where('CrBy', $empId)->where('ClaimYearId', $yearId)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->where('ClaimMonth', '>', 0);
            if ($month) {
                $q->where('ClaimMonth', $month);
            }
            $months = $q->distinct()->pluck('ClaimMonth')->map(fn ($m) => (int) $m)->all();
            if (! $months) {
                return 0;
            }
            $have = $db->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $empId)->where('YearId', $yearId)
                ->whereIn('Month', $months)->pluck('Month')->map(fn ($m) => (int) $m)->all();
            $made = 0;
            foreach (array_diff($months, $have) as $m) {
                $db->table("y{$yearId}_monthexpensefinal")->insert(['EmployeeID' => $empId, 'Month' => $m, 'YearId' => $yearId, 'Status' => 'Open', 'Crdate' => now()->toDateString()]);
                $made++;
                Log::info('MONTH-ROW-01: missing month row created as Open', ['emp' => $empId, 'month' => $m, 'year' => $yearId, 'conn' => $conn]);
            }

            return $made;
        } catch (\Throwable $e) {
            Log::warning('MONTH-ROW-01: '.$e->getMessage());

            return 0;
        }
    }
}
