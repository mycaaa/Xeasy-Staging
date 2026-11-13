<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * APPR-VER-01 (13 Nov 2026): in the VNR process (VNR Seeds, Nursery) the reporting manager sees and
 * opens a team member's claims only after the verifier has verified that month. VVNR approves first
 * and verifies after payment, so it is not affected. Switch: claim_rules.approver_after_verifier.
 */
class ApproverAfterVerifier
{
    public const MESSAGE = 'Claims of this month open for the reporting manager after the verifier has verified them.';

    public static function applies(int $companyId): bool
    {
        return (bool) config('claim_rules.approver_after_verifier', true)
            && config("claim_transitions.process.{$companyId}", 'vnr') === 'vnr';
    }

    /** SQL condition: the month row (monthexpensefinal) is verified. Same test as the approver's submit check. */
    public static function verifiedSql(string $alias = ''): string
    {
        $a = $alias !== '' ? $alias . '.' : '';
        return "({$a}Verified_Amount > 0 OR ({$a}Verified_Date IS NOT NULL AND {$a}Verified_Date > '1970-01-01'))";
    }

    public static function monthVerified(string $conn, int $yearId, int $employeeId, int $month): bool
    {
        return DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', $employeeId)->where('Month', $month)->where('YearId', $yearId)
            ->whereRaw(self::verifiedSql())
            ->exists();
    }

    /**
     * Whether $viewer may open $claimantId's claims of $month: always for the claimant and for staff
     * (verifier, finance ...); for anyone else (the reporting manager) only once the month is verified.
     */
    public static function mayOpen(int $companyId, string $conn, int $yearId, int $viewer, int $claimantId, int $month, $user = null): bool
    {
        if (! self::applies($companyId) || $viewer <= 0 || $viewer === $claimantId) {
            return true;
        }
        if ($user) {
            // the verification and payment teams work on unverified claims; report access alone does not count
            foreach (['section_verifier', 'verifier_verifier_screen', 'section_data_punching', 'finance', 'payment_section', 'treasury'] as $perm) {
                try {
                    if ($user->can($perm)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        return self::monthVerified($conn, $yearId, $claimantId, $month);
    }
}
