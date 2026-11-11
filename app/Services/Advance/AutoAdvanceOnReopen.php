<?php

namespace App\Services\Advance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ADV-11 (tracker Adv 11): when a submitted month is returned / reopened, the auto advance raised
 * for that month ("Auto Advance based on expense percentage", mostly Production / Parent Seed)
 * no longer fits the claim. An unpaid one is withdrawn (soft-deleted, with the reason); a paid one
 * is left alone and logged so Finance can adjust it.
 */
class AutoAdvanceOnReopen
{
    public const PURPOSE = 'Auto Advance based on expense percentage';

    /** @return array{withdrawn:int, paid_kept:int} */
    public static function handle(int $employeeId, int $month, int $yearId, string $reason = ''): array
    {
        $out = ['withdrawn' => 0, 'paid_kept' => 0];
        try {
            $conn = session('expense_connection', 'expense');
            $rows = DB::connection($conn)->table('advance_request_activities as a')
                ->join('advance_request_employees as e', 'e.id', '=', 'a.advance_request_employee_id')
                ->join('advance_requests as r', 'r.id', '=', 'e.advance_request_id')
                ->where('e.employee_id', $employeeId)->where('r.month', $month)->where('r.year_id', $yearId)
                ->where('a.purpose', self::PURPOSE)->whereNull('a.deleted_at')
                ->get(['a.id', 'a.payment_status', 'a.amount']);
            foreach ($rows as $a) {
                if (in_array((string) $a->payment_status, ['Paid', 'Unsettled'], true)) {
                    $out['paid_kept']++;
                    Log::warning("ADV-11: month {$month} of employee {$employeeId} reopened; auto advance #{$a->id} (Rs {$a->amount}) is already paid and was kept");
                    continue;
                }
                DB::connection($conn)->table('advance_request_activities')->where('id', $a->id)
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
                $out['withdrawn']++;
                Log::info("ADV-11: auto advance #{$a->id} withdrawn, month {$month} of employee {$employeeId} reopened. {$reason}");
            }
        } catch (\Throwable $e) {
            Log::warning('ADV-11 auto advance on reopen: '.$e->getMessage());
        }

        return $out;
    }
}
