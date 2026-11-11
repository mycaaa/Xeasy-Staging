<?php

namespace App\Services;

use App\Models\ClaimActionLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClaimActionLogService
{
    /**
     * Log a month-level return (Verifier or Admin returns a whole month to employee).
     */
    public static function logMonthReturn(
        int    $employeeId,
        int    $month,
        string $fromStatus = 'Submitted',
        string $toStatus   = 'Open',
        string $reason     = '',
        string $role       = ClaimActionLog::ROLE_VERIFIER
    ): void {
        $yearId    = (int) session('year_id');
        $companyId = (int) session('company_id', 1);
        $userId    = Auth::id() ?? 0;
        $userName  = Auth::user()?->name ?? '';

        $returnCount = ClaimActionLog::returnCount($employeeId, $month, $yearId) + 1;

        ClaimActionLog::create([
            'company_id'     => $companyId,
            'year_id'        => $yearId,
            'exp_id'         => null,
            'employee_id'    => $employeeId,
            'claim_month'    => $month,
            'action'         => ClaimActionLog::ACTION_RETURN,
            'action_by_role' => $role,
            'action_by'      => $userId,
            'action_by_name' => $userName,
            'reason'         => $reason,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'return_count'   => $returnCount,
            'action_at'      => now(),
        ]);
    }

    /**
     * Log a single-claim-level return (Report/Verifier returns one ExpId).
     */
    public static function logClaimReturn(
        int    $expId,
        int    $employeeId,
        int    $month,
        string $fromStatus = 'Verified',
        string $toStatus   = 'Submitted',
        string $reason     = '',
        string $role       = ClaimActionLog::ROLE_VERIFIER
    ): void {
        $yearId    = (int) session('year_id');
        $companyId = (int) session('company_id', 1);
        $userId    = Auth::id() ?? 0;
        $userName  = Auth::user()?->name ?? '';

        $returnCount = ClaimActionLog::returnCount($employeeId, $month, $yearId, $expId) + 1;

        ClaimActionLog::create([
            'company_id'     => $companyId,
            'year_id'        => $yearId,
            'exp_id'         => $expId,
            'employee_id'    => $employeeId,
            'claim_month'    => $month,
            'action'         => ClaimActionLog::ACTION_RETURN,
            'action_by_role' => $role,
            'action_by'      => $userId,
            'action_by_name' => $userName,
            'reason'         => $reason,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'return_count'   => $returnCount,
            'action_at'      => now(),
        ]);
    }

    /**
     * Log a submit action (Verifier submits to Approver).
     */
    public static function logSubmit(
        int    $employeeId,
        int    $month,
        string $fromStatus = 'Verified',
        string $toStatus   = 'Approved',
        string $role       = ClaimActionLog::ROLE_VERIFIER
    ): void {
        $yearId    = (int) session('year_id');
        $companyId = (int) session('company_id', 1);
        $userId    = Auth::id() ?? 0;
        $userName  = Auth::user()?->name ?? '';

        // Determine if this is a first submit or a resubmit
        $previousReturns = ClaimActionLog::returnCount($employeeId, $month, $yearId);
        $action = $previousReturns > 0
            ? ClaimActionLog::ACTION_RESUBMIT
            : ClaimActionLog::ACTION_SUBMIT;

        ClaimActionLog::create([
            'company_id'     => $companyId,
            'year_id'        => $yearId,
            'exp_id'         => null,
            'employee_id'    => $employeeId,
            'claim_month'    => $month,
            'action'         => $action,
            'action_by_role' => $role,
            'action_by'      => $userId,
            'action_by_name' => $userName,
            'reason'         => null,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'return_count'   => $previousReturns,
            'action_at'      => now(),
        ]);
    }

    /**
     * Log an allow-submission action (Admin/Verifier permits submission for a closed/restricted month).
     */
    public static function logAllowSubmit(
        int    $employeeId,
        int    $month,
        string $reason = '',
        string $role   = ClaimActionLog::ROLE_ADMIN
    ): void {
        $yearId    = (int) session('year_id');
        $companyId = (int) session('company_id', 1);
        $userId    = Auth::id() ?? 0;
        $userName  = Auth::user()?->name ?? '';

        ClaimActionLog::create([
            'company_id'     => $companyId,
            'year_id'        => $yearId,
            'exp_id'         => null,
            'employee_id'    => $employeeId,
            'claim_month'    => $month,
            'action'         => ClaimActionLog::ACTION_ALLOW_SUBMIT,
            'action_by_role' => $role,
            'action_by'      => $userId,
            'action_by_name' => $userName,
            'reason'         => $reason,
            'from_status'    => 'N',
            'to_status'      => 'Y',
            'return_count'   => 0,
            'action_at'      => now(),
        ]);
    }

    /**
     * Log a month-level revert (Verifier reverts a verified/approved month back to pending).
     */
    public static function logMonthRevert(
        int    $employeeId,
        int    $month,
        string $fromStatus = 'Approved',
        string $toStatus   = 'Pending',
        string $reason     = '',
        string $role       = ClaimActionLog::ROLE_VERIFIER
    ): void {
        $yearId    = (int) session('year_id');
        $companyId = (int) session('company_id', 1);
        $userId    = Auth::id() ?? 0;
        $userName  = Auth::user()?->name ?? '';

        ClaimActionLog::create([
            'company_id'     => $companyId,
            'year_id'        => $yearId,
            'exp_id'         => null,
            'employee_id'    => $employeeId,
            'claim_month'    => $month,
            'action'         => ClaimActionLog::ACTION_REVERT,
            'action_by_role' => $role,
            'action_by'      => $userId,
            'action_by_name' => $userName,
            'reason'         => $reason,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'return_count'   => 0,
            'action_at'      => now(),
        ]);
    }
}

