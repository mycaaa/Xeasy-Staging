<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

/**
 * LV-C04: payment approval figures are worked out on the server.
 *
 * Before, approveClaimMonth stored the payment amount, the "actual expense" and every adjustment
 * total exactly as the browser posted them, and each advance line's adjustment was SET to this
 * month's amount (a second partial adjustment overwrote the first; an adjustment above the
 * advance went negative).
 *
 * check() recomputes from the database:
 *   base     = financed activity + expense amounts for the month (DA left out below grade 68),
 *              the same rule the Pending Payments screen uses;
 *   lines    = each advance line adjusted, capped at what is still open on that advance;
 *   totals   = activity + expense lines (+ carried) + other adjustment;
 *   payment  = max(0, base - total adjustment).
 * A posted figure that differs from the server's by more than ₹1 is refused with the reason.
 */
class PaymentFigures
{
    public const TOLERANCE = 1.0;

    /** Payable base for the month (activity + expense, DA excluded below grade 68). */
    public static function base(int $employeeId, int $month, int $yearId): float
    {
        $act = 0.0;
        $exp = 0.0;
        $da = 0.0;
        foreach ((array) getFinancialAmounts($employeeId, $month, $yearId) as $fa) {
            $act += (float) ($fa->FinalActivityAmount ?? 0);
            $exp += (float) ($fa->FinalExpenseAmount ?? 0);
            $da += (float) ($fa->FinalDAAmount ?? 0);
        }
        $grade = (int) getGradeId($employeeId);
        $net = $grade > 67 ? $exp : $exp - $da;

        return round($act + $net, 2);
    }

    /** Amount still open on one advance line (payment minus what was already adjusted). */
    public static function openOnAdvance(string $conn, int $advanceActivityId): ?array
    {
        $a = DB::connection($conn)->table('advance_request_activities')->where('id', $advanceActivityId)
            ->first(['id', 'payment_amount', 'adjustment_amount']);
        if (! $a) {
            return null;
        }
        $paid = (float) ($a->payment_amount ?? 0);
        $done = (float) ($a->adjustment_amount ?? 0);

        return ['paid' => $paid, 'adjusted' => $done, 'open' => round(max(0, $paid - $done), 2)];
    }

    /**
     * Validate what the browser posted. Returns [errors[], figures[]]; figures are the values to store.
     *
     * @param  array  $activityLines  decoded activity_adjustments_json
     * @param  array  $expenseLines   decoded expense_adjustments_json
     */
    public static function check(string $conn, int $employeeId, int $month, int $yearId, array $activityLines, array $expenseLines, float $otherAdj, array $posted): array
    {
        $errors = [];
        $base = self::base($employeeId, $month, $yearId);

        $sum = function (array $lines, string $label) use ($conn, &$errors) {
            $total = 0.0;
            $carried = 0.0;
            $seen = [];
            foreach ($lines as $key => $l) {
                $amt = round((float) ($l['amount'] ?? 0), 2);
                if ($amt < 0) {
                    $errors[] = "{$label} adjustment {$key} is negative.";

                    continue;
                }
                if ($amt == 0) {
                    continue;
                }
                $advId = $l['advance_activity_id'] ?? 0;
                if (is_numeric($advId) && (int) $advId > 0) {
                    $advId = (int) $advId;
                    $seen[$advId] = ($seen[$advId] ?? 0) + $amt;
                    $open = self::openOnAdvance($conn, $advId);
                    if (! $open) {
                        $errors[] = "Advance line {$advId} does not exist.";
                    } elseif ($seen[$advId] > $open['open'] + self::TOLERANCE) {
                        $errors[] = 'Advance line '.$advId.': adjusting ₹'.number_format($seen[$advId], 2).' but only ₹'.number_format($open['open'], 2).' is still open on it.';
                    }
                } else {
                    $carried += $amt; // carried / old balance lines have no advance id
                }
                $total += $amt;
            }

            return [round($total, 2), round($carried, 2)];
        };

        [$actTotal, $actCarried] = $sum($activityLines, 'Activity');
        [$expTotal, $expCarried] = $sum($expenseLines, 'Expense');
        if ($otherAdj < 0) {
            $errors[] = 'Other adjustment cannot be negative.';
        }
        $totalAdj = round($actTotal + $expTotal + $otherAdj, 2);
        $payment = round(max(0, $base - $totalAdj), 2);

        $cmp = [
            'actual_expense_amount' => [$base, 'Actual expense (without DA)'],
            'total_activity_adjustment' => [$actTotal, 'Activity adjustment total'],
            'total_expense_adjustment' => [$expTotal, 'Expense adjustment total'],
            'total_adjustment' => [$totalAdj, 'Total adjustment'],
            'payment_amount' => [$payment, 'Payment amount'],
        ];
        foreach ($cmp as $k => [$server, $label]) {
            if (array_key_exists($k, $posted) && $posted[$k] !== null && $posted[$k] !== '' && abs((float) $posted[$k] - $server) > self::TOLERANCE) {
                $errors[] = "{$label} on screen ₹".number_format((float) $posted[$k], 2).' does not match the server figure ₹'.number_format($server, 2).'. Reload the page and try again.';
            }
        }

        return [$errors, [
            'actual_expense_amount' => $base,
            'total_activity_adjustment' => $actTotal,
            'total_expense_adjustment' => $expTotal,
            'carried_activity_adjustment' => $actCarried,
            'carried_expense_adjustment' => $expCarried,
            'total_adjustment' => $totalAdj,
            'advance_payment_amount' => round($actTotal + $expTotal, 2),
            'payment_amount' => $payment,
        ]];
    }

    /** Add this month's adjustment to an advance line (accumulates; never above the amount paid). */
    public static function applyAdjustment(string $conn, int $advanceActivityId, float $amount, int $userId, int $month, string $remark): void
    {
        $open = self::openOnAdvance($conn, $advanceActivityId);
        if (! $open) {
            return;
        }
        $newAdjusted = round(min($open['paid'], $open['adjusted'] + $amount), 2);
        DB::connection($conn)->table('advance_request_activities')->where('id', $advanceActivityId)->update([
            'adjustment_amount' => $newAdjusted,
            'adjustment_date' => now(),
            'adjustment_by' => $userId,
            'remaining_balance' => round($open['paid'] - $newAdjusted, 2),
            'adjusted_against_month' => $month,
            'adjustment_remarks' => $remark,
        ]);
    }

    /** Undo one month's adjustment on an advance line (revert of a payment approval). */
    public static function undoAdjustment(string $conn, int $advanceActivityId, float $amount): void
    {
        $open = self::openOnAdvance($conn, $advanceActivityId);
        if (! $open) {
            return;
        }
        $newAdjusted = round(max(0, $open['adjusted'] - $amount), 2);
        $upd = ['adjustment_amount' => $newAdjusted, 'remaining_balance' => round($open['paid'] - $newAdjusted, 2)];
        if ($newAdjusted <= 0) {
            $upd += ['adjusted_against_month' => null, 'adjustment_date' => null, 'adjustment_by' => null, 'adjustment_remarks' => null];
        }
        DB::connection($conn)->table('advance_request_activities')->where('id', $advanceActivityId)->update($upd);
    }
}
