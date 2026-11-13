<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EMP-CONFIRM-01 (13 Nov 2026): the employee's check of the punched claim. The employee cannot change what the
 * punching team entered; they mark it OK (FilledOkay 1) or deny it with a remark (FilledOkay 2). A denied claim goes
 * back to the punching team (Denied tab); when the team saves it again it comes back to the employee (FilledOkay 0).
 */
class EmployeeConfirm
{
    /** Why the employee may not mark this claim OK / denied, or null. */
    public static function refuse(string $conn, int $yearId, object $claim, int $empId): ?string
    {
        if ((int) $claim->CrBy !== $empId) {
            return 'This is not your claim.';
        }
        if (($claim->ClaimStatus ?? '') !== 'Filled') {
            return 'This bill is not punched yet. You can mark it OK or deny it after the punching team has checked it.';
        }
        $closed = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $empId)
            ->where('Month', (int) $claim->ClaimMonth)->where('YearId', $yearId)->where('Status', 'Closed')->exists();

        return $closed ? 'This month is already submitted.' : null;
    }

    /** The punching team saved a denied claim again: the employee checks it again. */
    public static function backToEmployee(string $conn, int $yearId, int $expId): void
    {
        $n = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->where('FilledOkay', 2)
            ->update(['FilledOkay' => 0]);
        if ($n) {
            Log::info('EMP-CONFIRM-01: denied claim corrected by punching, back to the employee', ['exp' => $expId, 'by' => session('employee_id')]);
        }
    }

    /**
     * Has the punching team punched this claim? Then the employee may not change it (only OK / deny).
     * Punched = Filled and filled by someone other than the employee (staff), or checked on the punching screen.
     */
    public static function punchedByTeam(object $claim): bool
    {
        if (($claim->ClaimStatus ?? '') !== 'Filled') {
            return false;
        }
        // odometer: the punching check is Verify Running KM (it keeps the employee as FilledBy)
        if ((int) ($claim->ClaimId ?? 0) === 7) {
            return ($claim->v_verify ?? '') === 'Y';
        }

        return (int) ($claim->FilledBy ?? 0) > 0 && (int) $claim->FilledBy !== (int) $claim->CrBy;
    }
}
