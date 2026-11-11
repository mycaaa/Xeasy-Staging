<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-03 (6 Nov 2026): record-level access. A logged-in user may see an employee's claims,
 * drafts, months, prints and ledger only if:
 *   - it is their own record, or
 *   - they are above that employee in the reporting chain (HRIMS hrm_employee_general.RepEmployeeID:
 *     reporting manager, manager's manager and upwards, up to `record_access.chain_levels`), or
 *   - they approved that employee's claims this year or last (ApprBy), or
 *   - they hold one of the staff permissions in `record_access.staff_permissions`
 *     (verifier, data punching, finance, payment, treasury, reports; Super Admin passes via Gate::before).
 * Before, the employee id (?ei=, ?e=, ?emp=, ...) or claim id in the URL was trusted as given.
 */
class RecordAccess
{
    /** @var array<int,int[]> chain cache: employee id => ids above them */
    private static array $chains = [];

    /** @var array<string,int|null> claim owner cache */
    private static array $owners = [];

    public static function selfId($user = null): int
    {
        $sid = (int) session('employee_id');
        if ($sid > 0) {
            return $sid;
        }
        $user = $user ?: auth()->user();

        return (int) ($user->employee_id ?? 0);
    }

    public static function isStaff($user): bool
    {
        foreach ((array) config('record_access.staff_permissions', []) as $perm) {
            try {
                if ($user->can($perm)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // unknown permission name: ignore
            }
        }

        return false;
    }

    /** Ids of the people above $employeeId in the reporting chain. */
    public static function managersOf(int $employeeId): array
    {
        if (isset(self::$chains[$employeeId])) {
            return self::$chains[$employeeId];
        }
        $ids = [];
        $current = $employeeId;
        $levels = (int) config('record_access.chain_levels', 6);
        try {
            for ($i = 0; $i < $levels; $i++) {
                $rep = (int) DB::connection('hrims')->table('hrm_employee_general')
                    ->where('EmployeeID', $current)->value('RepEmployeeID');
                if ($rep <= 0 || $rep === $current || in_array($rep, $ids, true) || $rep === $employeeId) {
                    break;
                }
                $ids[] = $rep;
                $current = $rep;
            }
        } catch (\Throwable $e) {
            // HRIMS unreachable: no chain (staff and own access still work)
        }

        return self::$chains[$employeeId] = $ids;
    }

    /** True if the user has at least one direct report (used for the old approver screens). */
    public static function hasReports($user = null): bool
    {
        $self = self::selfId($user);
        if ($self <= 0) {
            return false;
        }
        try {
            return DB::connection('hrims')->table('hrm_employee_general as g')
                ->join('hrm_employee as e', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->where('g.RepEmployeeID', $self)->where('e.EmpStatus', 'A')->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * True if $self approved at least one claim of $employeeId (ApprBy on the claim) in the
     * session year or the year before. About 1 in 10 approvals were made by someone who is no
     * longer in the claimant's reporting chain (reporting changed later, or the old approver
     * mapping), and those approvers still need to open the claims they approved.
     */
    public static function approvedFor(int $self, int $employeeId): bool
    {
        if ($self <= 0 || $employeeId <= 0) {
            return false;
        }
        $connection = session('expense_connection') ?: 'expense';
        $year = (int) (session('year_id') ?: 0);
        foreach (array_filter([$year, $year - 1]) as $y) {
            try {
                if (DB::connection($connection)->table("y{$y}_expenseclaims")
                    ->where('CrBy', $employeeId)->where('ApprBy', $self)->exists()) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return false;
    }

    /** An approver: has direct reports, or has approved claims this year (old approver mapping). */
    public static function isApprover($user = null): bool
    {
        if (self::hasReports($user)) {
            return true;
        }
        $self = self::selfId($user);
        $connection = session('expense_connection') ?: 'expense';
        $year = (int) (session('year_id') ?: 0);
        try {
            return $self > 0 && $year > 0 && DB::connection($connection)->table("y{$year}_expenseclaims")
                ->where('ApprBy', $self)->where('CrBy', '!=', $self)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function canViewEmployee($user, $employeeId): bool
    {
        $employeeId = (int) $employeeId;
        if ($employeeId <= 0) {
            return true; // no employee named: the action falls back to the logged-in employee
        }
        if (! $user) {
            return false;
        }
        $self = self::selfId($user);
        if ($self > 0 && $self === $employeeId) {
            return true;
        }
        if ((int) ($user->employee_id ?? 0) === $employeeId) {
            return true;
        }
        if (self::isStaff($user)) {
            return true;
        }

        return $self > 0 && (in_array($self, self::managersOf($employeeId), true) || self::approvedFor($self, $employeeId));
    }

    /**
     * Owner (CrBy) of an expense claim. Looks in the given year first, then the session year,
     * then every other year table on that company's expense connection. Null = not found.
     */
    public static function claimOwner($expId, ?int $yearId = null, ?string $connection = null): ?int
    {
        $expId = (int) $expId;
        if ($expId <= 0) {
            return null;
        }
        $connection = $connection ?: (session('expense_connection') ?: 'expense');
        $key = "{$connection}|{$expId}|".(int) $yearId;
        if (array_key_exists($key, self::$owners)) {
            return self::$owners[$key];
        }
        $years = array_values(array_unique(array_filter([(int) $yearId, (int) session('year_id')])));
        for ($y = 15; $y >= 1; $y--) {
            if (! in_array($y, $years, true)) {
                $years[] = $y;
            }
        }
        $owner = null;
        foreach ($years as $y) {
            $table = "y{$y}_expenseclaims";
            try {
                if (! Schema::connection($connection)->hasTable($table)) {
                    continue;
                }
                $crBy = DB::connection($connection)->table($table)->where('ExpId', $expId)->value('CrBy');
                if ($crBy !== null) {
                    $owner = (int) $crBy;
                    break;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return self::$owners[$key] = $owner;
    }

    public static function canViewClaim($user, $expId, ?int $yearId = null, ?int $companyId = null): bool
    {
        if ((int) $expId <= 0) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if (self::isStaff($user)) {
            return true;
        }
        $connection = null;
        if ($companyId) {
            try {
                $connection = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
            } catch (\Throwable $e) {
                $connection = null;
            }
        }
        $owner = self::claimOwner($expId, $yearId, $connection);
        if ($owner === null) {
            return true; // no such claim: nothing to show, the action answers "not found"
        }

        return self::canViewEmployee($user, $owner);
    }
}
