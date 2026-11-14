<?php

namespace App\Services\ClaimRules;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SUBMIT-BTN-01 (13 Nov 2026): one rule for when an employee may submit a month, used by the open-month lists
 * (web, dashboard and app) and by the submit itself. The lists used a different rule ("last month only, until the
 * last submission day"), so a back-dated month whose bills were all approved and verified never showed the button.
 *
 * May submit when:
 *  - the month has ended;
 *  - (bills still with the punching team no longer block it - SUBMIT-PENDING-01: the submit warns, and they are
 *    left out of the claim);
 *  - it is before the last submission day, or the admin allowed a late submission (SubPermission), or the month's
 *    late bills were approved (Late Bill Approval) - that approval is the permission to claim them.
 */
class MonthSubmit
{
    /** @return array{allowed: bool, reason: ?string} */
    public static function state(string $conn, int $companyId, int $yearId, int $empId, int $month, ?string $subPermission = null): array
    {
        $v = app(ClaimSubmissionValidator::class);
        $start = $v->fyStartYear($yearId, $conn);
        if ($start > 0 && $month >= 1 && $month <= 12) {
            $calYear = $month >= 4 ? $start : $start + 1;
            $end = Carbon::create($calYear, $month, 1, 0, 0, 0, 'Asia/Kolkata')->endOfMonth();
            if (now('Asia/Kolkata')->lessThanOrEqualTo($end)) {
                return ['allowed' => false, 'reason' => 'You can submit '.$end->format('F').' after the month ends.'];
            }
        }

        $db = DB::connection($conn);
        $base = fn () => $db->table("y{$yearId}_expenseclaims")->where('CrBy', $empId)->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft']);
        // SUBMIT-PENDING-01 (14 Nov 2026): bills still with the punching team no longer block the button; the submit
        // itself warns and asks the employee to confirm (LegacyProxyController::handleClaimSubmit, Code 101)
        $warning = null;
        if (config('claim_process.submit_requires_punched', true)) {
            $pending = $base()->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('ClaimStatus', ['Submitted', 'Open', ''])
                ->where(fn ($q) => $q->whereNull('FilledOkay')->orWhere('FilledOkay', '!=', 2)))->orWhere('FilledOkay', 2))->count();
            if ($pending) {
                $warning = "{$pending} bill(s) are still pending for punching; they will not be part of this claim.";
            }
        }

        if (self::lateAllowed($conn, $yearId, $empId, $month, $subPermission)) {
            return ['allowed' => true, 'reason' => $warning];
        }
        $cut = ClaimSubmissionValidator::first($v->submissionCutoff($companyId, $yearId, $month, $subPermission, $conn));

        return $cut === null ? ['allowed' => true, 'reason' => $warning] : ['allowed' => false, 'reason' => $cut];
    }

    /** Admin allowed late submission, or the month has late bills approved through Late Bill Approval. */
    public static function lateAllowed(string $conn, int $yearId, int $empId, int $month, ?string $subPermission): bool
    {
        if (strtoupper((string) $subPermission) === 'Y') {
            return true;
        }
        try {
            return DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('CrBy', $empId)->where('ClaimMonth', $month)
                ->where('ClaimYearId', $yearId)->where('ClaimId', '!=', 7)->where('Backdate_Odometer_Status', 'A')
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
