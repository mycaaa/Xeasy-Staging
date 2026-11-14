<?php

namespace App\Services\Advance;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;

/**
 * ADV-CONTEXT-01 (14 Nov 2026): what the requester and the approver should see about an employee before an advance is
 * submitted or approved - the ledger balance, the open (paid, not yet settled) advances, a link to the A/c Summary
 * and, for an activity advance, the employee's last advance for the same activity, crop and variety.
 */
class AdvanceContext
{
    /**
     * @param  int[]  $crops
     * @param  int[]  $varieties
     */
    public static function forEmployee(int $employeeId, int $companyId, int $yearId, ?int $activityNameId = null,
        array $crops = [], array $varieties = [], ?int $excludeRequestId = null): array
    {
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $out = ['employee_id' => $employeeId, 'ledger_balance' => null, 'ledger_as_of' => null,
            'open_advances' => 0.0, 'open_advance_count' => 0,
            'acc_summary_url' => url('/account-summary').'?'.http_build_query(['e' => $employeeId, 'y' => $yearId]),
            'last_activity_advance' => null];

        try {
            $row = DB::connection($conn)->table('employee_ledger_balances')->where('employee_id', $employeeId)
                ->whereNull('deleted_at')->orderByDesc('year_id')->orderByDesc('id')->first(['balance', 'updated_at', 'year_id']);
            if ($row) {
                $out['ledger_balance'] = round((float) $row->balance, 2);
                $out['ledger_as_of'] = $row->updated_at ? substr((string) $row->updated_at, 0, 10) : null;
            }
        } catch (\Throwable $e) {
        }

        try {
            $paid = DB::table('advance_request_activities as a')
                ->join('advance_request_employees as e', 'e.id', '=', 'a.advance_request_employee_id')
                ->where('e.employee_id', $employeeId)->whereNull('a.deleted_at')->whereNull('e.deleted_at')
                ->where('a.payment_amount', '>', 0)
                ->get(['a.payment_amount', 'a.adjustment_amount', 'a.remaining_balance']);
            foreach ($paid as $p) {
                $open = self::remaining($p);
                if ($open > 0.5) {
                    $out['open_advances'] += $open;
                    $out['open_advance_count']++;
                }
            }
            $out['open_advances'] = round($out['open_advances'], 2);
        } catch (\Throwable $e) {
        }

        if ($activityNameId) {
            $out['last_activity_advance'] = self::lastActivityAdvance($employeeId, $activityNameId, $crops, $varieties, $excludeRequestId);
        }

        return $out;
    }

    /** Paid and not yet adjusted against expenses. */
    private static function remaining(object $a): float
    {
        $paid = (float) ($a->payment_amount ?? 0);
        if ($a->remaining_balance !== null && $a->remaining_balance !== '') {
            return max(0.0, (float) $a->remaining_balance);
        }

        return max(0.0, $paid - (float) ($a->adjustment_amount ?? 0));
    }

    /** Ids from a JSON list / array / comma list. @return int[] */
    private static function ids($v): array
    {
        if (is_string($v)) {
            $d = json_decode($v, true);
            $v = is_array($d) ? $d : explode(',', $v);
        }

        return array_values(array_filter(array_map('intval', (array) $v)));
    }

    public static function lastActivityAdvance(int $employeeId, int $activityNameId, array $crops, array $varieties, ?int $excludeRequestId): ?array
    {
        $crops = self::ids($crops);
        $varieties = self::ids($varieties);
        try {
            $rows = DB::table('advance_requests as r')
                ->join('advance_request_employees as e', 'e.advance_request_id', '=', 'r.id')
                ->join('advance_request_activities as a', 'a.advance_request_employee_id', '=', 'e.id')
                ->where('e.employee_id', $employeeId)->where('r.activity_name_id', $activityNameId)
                ->whereNull('r.deleted_at')->whereNull('e.deleted_at')->whereNull('a.deleted_at')
                ->whereNotIn('r.current_status', ['Draft', 'Withdrawn'])
                ->when($excludeRequestId, fn ($q) => $q->where('r.id', '!=', $excludeRequestId))
                ->orderByDesc('r.created_at')->orderByDesc('a.id')->limit(50)
                ->get(['r.id as request_id', 'r.created_at', 'r.last_submitted_at', 'r.month', 'r.current_status', 'r.submission_no',
                    'a.id as activity_id', 'a.activity_crops_id', 'a.activity_variety_id', 'a.amount', 'a.approval_status',
                    'a.finance_approved_amount', 'a.finance_status', 'a.payment_amount', 'a.payment_date', 'a.adjustment_amount',
                    'a.remaining_balance', 'a.adjusted_against_month', 'a.final_status']);
        } catch (\Throwable $e) {
            return null;
        }
        foreach ($rows as $r) {
            $rc = self::ids($r->activity_crops_id);
            $rv = self::ids($r->activity_variety_id);
            if ($crops && ! array_intersect($crops, $rc)) {
                continue;
            }
            if ($varieties && ! array_intersect($varieties, $rv)) {
                continue;
            }
            $approved = (float) ($r->finance_approved_amount ?? 0);
            if ($approved <= 0) {
                $approved = (float) (DB::table('advance_activity_approvals')->where('advance_request_activity_id', $r->activity_id)
                    ->where('approver_status', 'Approved')->orderByDesc('approval_level')->value('approver_amount') ?? 0);
            }
            $paid = (float) ($r->payment_amount ?? 0);
            $open = self::remaining($r);
            if ($paid <= 0) {
                $settle = 'Not paid yet ('.str_replace('_', ' ', (string) ($r->approval_status ?: $r->current_status)).')';
            } elseif ($open <= 0.5) {
                $settle = 'Settled'.($r->adjusted_against_month ? ' against '.$r->adjusted_against_month : '');
            } elseif ((float) ($r->adjustment_amount ?? 0) > 0) {
                $settle = 'Partly settled - Rs '.number_format($open, 0).' open';
            } else {
                $settle = 'Not settled - Rs '.number_format($open, 0).' open';
            }
            $names = function (array $ids, string $table, string $col) {
                try {
                    return $ids ? DB::connection('hrims')->table($table)->whereIn('id', $ids)->pluck($col)->all() : [];
                } catch (\Throwable $e) {
                    return [];
                }
            };

            return [
                'request_id' => (int) $r->request_id,
                'date' => substr((string) ($r->last_submitted_at ?: $r->created_at), 0, 10),
                'requested_amount' => round((float) $r->amount, 2),
                'approved_amount' => round($approved, 2),
                'paid_amount' => round($paid, 2),
                'paid_on' => $r->payment_date ? substr((string) $r->payment_date, 0, 10) : null,
                'settlement' => $settle,
                'crops' => $names($rc, 'core_crops', 'crop_name'),
                'varieties' => $names($rv, 'core_varieties', 'variety_name'),
            ];
        }

        return null;
    }
}
