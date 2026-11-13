<?php

namespace App\Support;

use App\Models\AdvanceActivityApproval;
use App\Models\AdvanceRequestActivity;

/**
 * ADV-AMT-02 (13 Nov 2026): the advance amount can be changed only by an approver while the
 * approval is pending at their own stage. After that it is fixed and carried to every later stage:
 * a later approver can only lower it, Finance approves exactly that amount (a foreign-currency
 * advance paid at the actual rate is the one exception), and the release pays the Finance amount.
 */
class AdvanceAmount
{
    public const TOLERANCE = 0.01;

    /** The amount now standing for the activity: the last approved approver amount, else the amount asked. */
    public static function carried(AdvanceRequestActivity $activity): float
    {
        $last = AdvanceActivityApproval::where('advance_request_activity_id', $activity->id)
            ->where('approver_status', AdvanceActivityApproval::STATUS_APPROVED)
            ->whereNotNull('approver_amount')
            ->where('approver_amount', '>', 0)
            ->orderByDesc('approval_level')->orderByDesc('id')
            ->value('approver_amount');

        return round((float) ($last ?? $activity->amount), 2);
    }

    public static function differs($a, $b): bool
    {
        return abs((float) $a - (float) $b) > self::TOLERANCE;
    }
}
