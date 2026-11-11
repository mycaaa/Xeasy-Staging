<?php

namespace App\Services\Odometer;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ODO-BD-02 (decision 6 Nov 2026): back-dated odometer approval in two steps.
 *
 *   step 'rm'    - the claimant's reporting manager (HRIMS RepEmployeeID) approves first;
 *   step 'final' - then the BU head (approval_type bu_level) or HOD (hod_level) of the claimant's
 *                  department, per odo_backdate_settings. Only the final approval releases the claim
 *                  (Backdate_Odometer_Status 'A'). Either step may reject, with a remark.
 *
 * The RM step is skipped (logged) when the claimant has no RM, the RM is the claimant, the claim was
 * entered before config('odo_backdate.two_step_from'), or the two-step switch is off. When the RM is
 * also the final approver, one approval counts for both (logged).
 *
 * The same screens and settings serve VNR, VVNR and nursery; the company (and so the database) is
 * taken from the approver's HRIMS record, because the app calls these routes without a session.
 */
class BackdateApproval
{
    /** Company of an employee (HRIMS hrm_employee.CompanyId), default VNR. */
    public static function companyOf(int $employeeId): int
    {
        if (session('company_id')) {
            return (int) session('company_id');
        }
        $c = (int) DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $employeeId)->value('CompanyId');

        return $c > 0 ? $c : 1;
    }

    public static function connFor(int $employeeId): string
    {
        return session('expense_connection') ?: DatabaseSwitcher::getExpenseConnection(self::companyOf($employeeId));
    }

    public static function hrimsDb(int $employeeId): string
    {
        return DatabaseSwitcher::getHrimsDatabaseName(self::companyOf($employeeId));
    }

    public static function hasRmColumns(string $conn, string $table): bool
    {
        static $cache = [];

        return $cache["{$conn}|{$table}"] ??= Schema::connection($conn)->hasColumn($table, 'Backdate_RM_Status');
    }

    /** HRIMS general row of an employee (DepartmentId, BUId, RepEmployeeID ...). */
    public static function general(int $employeeId): ?object
    {
        static $cache = [];

        return $cache[$employeeId] ??= DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $employeeId)
            ->first(['EmployeeID', 'DepartmentId', 'BUId', 'RepEmployeeID', 'TerrId', 'ZoneId', 'RegionId', 'EmpVertical']);
    }

    /**
     * The reporting manager who approves first.
     * ODO-BD-04 (HR decision 7 Nov 2026): when the immediate RM is not available (left, inactive or
     * not set on their record), the claim goes to the next level of the reporting hierarchy - the RM's
     * manager - and so on (up to 6 levels). 0 = nobody in the chain (then the BU head / HOD approves).
     */
    public static function rmOf(int $claimantId): int
    {
        $seen = [$claimantId => true];
        $next = (int) (self::general($claimantId)->RepEmployeeID ?? 0);
        for ($i = 0; $i < 6 && $next > 0 && ! isset($seen[$next]); $i++) {
            if (self::available($next)) {
                return $next;
            }
            $seen[$next] = true;
            $next = (int) (self::general($next)->RepEmployeeID ?? 0);
        }

        return 0;
    }

    /** Active employee with no separation date that has passed. */
    public static function available(int $employeeId): bool
    {
        static $cache = [];
        if (isset($cache[$employeeId])) {
            return $cache[$employeeId];
        }
        try {
            $e = DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $employeeId)->first(['EmpStatus', 'DateOfSepration']);
        } catch (\Throwable $x) {
            return $cache[$employeeId] = true;
        }
        $sep = $e ? (string) ($e->DateOfSepration ?? '') : '';

        return $cache[$employeeId] = $e && $e->EmpStatus === 'A' && ! ($sep > '2000-01-01' && $sep < date('Y-m-d'));
    }

    public static function setting(string $conn, ?int $departmentId): ?object
    {
        if (! $departmentId || ! Schema::connection($conn)->hasTable('odo_backdate_settings')) {
            return null;
        }

        return DB::connection($conn)->table('odo_backdate_settings')->where('department_id', $departmentId)->first();
    }

    /** Is $approverId the BU head / HOD who gives the final approval for $claimantId? */
    public static function isFinalApproverFor(string $conn, int $approverId, int $claimantId): bool
    {
        $g = self::general($claimantId);
        $s = $g ? self::setting($conn, (int) $g->DepartmentId) : null;
        if (! $g || ! $s) {
            return false;
        }
        if ($s->approval_type === 'bu_level') {
            return self::isBuGeneralManager($approverId, (int) $g->BUId);
        }
        if ($s->approval_type === 'hod_level') {
            return self::isHod($approverId, (int) $g->DepartmentId);
        }

        return false;
    }

    /** 'rm' or 'final' for a pending back-dated claim (row needs CrBy, CrDate and, if present, Backdate_RM_Status). */
    public static function stepOf(object $row, bool $hasRmColumns): string
    {
        if (! config('odo_backdate.two_step', true) || ! $hasRmColumns) {
            return 'final';
        }
        if (($row->Backdate_RM_Status ?? null) === 'A') {
            return 'final';
        }
        $from = config('odo_backdate.two_step_from');
        if ($from && ! empty($row->CrDate) && substr((string) $row->CrDate, 0, 10) < $from) {
            return 'final';   // already waiting with the BU head / HOD before the change
        }
        $rm = self::rmOf((int) $row->CrBy);
        if ($rm <= 0 || $rm === (int) $row->CrBy) {
            return 'final';
        }

        return 'rm';
    }

    public static function isBuGeneralManager(int $employeeId, int $buId): bool
    {
        if ($buId <= 0) {
            return false;
        }

        return DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $employeeId)
            ->where('TerrId', 0)->where('ZoneId', 0)->where('RegionId', 0)->where('BUId', $buId)->exists();
    }

    /** HOD = the person in the department whose reporting manager is outside the department (or none). */
    public static function isHod(int $employeeId, int $departmentId): bool
    {
        $me = self::general($employeeId);
        if (! $me || (int) $me->DepartmentId !== $departmentId) {
            return false;
        }
        $rep = (int) $me->RepEmployeeID;
        if ($rep <= 0 || $rep === $employeeId) {
            return true;
        }
        $repGeneral = self::general($rep);

        return ! $repGeneral || (int) $repGeneral->DepartmentId !== $departmentId;
    }

    public static function log(string $event, array $ctx): void
    {
        Log::info("odo-backdate: {$event}", $ctx);
    }
}
