<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * ACCESS-HOD-01 (decided 10 Nov 2026): the HOD advance report is for HODs only.
 * Allowed: the named all-company viewers, an HOD (head of their department: reporting manager
 * outside the department or none), a BU head, Super Admin and staff with a reports / finance
 * permission. Everyone else is refused, and the report always runs for the logged-in employee
 * (a staff user may pick another employee).
 */
class HodAccess
{
    public const ALL_LEVEL = [6, 7, 51, 89, 461, 2035, 224];

    public static function allowed($user = null): bool
    {
        $user = $user ?: auth()->user();
        $via = (string) (request()?->attributes->get('api_auth_via') ?? '');
        if (! $user && $via === 'internal_key') {
            return true; // server-to-server relay
        }
        if ($user && RecordAccess::isStaff($user)) {
            return true;
        }
        $self = $user ? RecordAccess::selfId($user) : (int) (request()?->attributes->get('api_auth_empid') ?? 0); // app token
        if ($self <= 0) {
            return false;
        }
        if (in_array($self, self::ALL_LEVEL, true)) {
            return true;
        }
        $g = DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $self)
            ->first(['DepartmentId', 'BUId', 'ZoneId', 'RegionId', 'TerrId']);
        if (! $g) {
            return false;
        }
        if ((int) $g->BUId > 0 && (int) $g->ZoneId === 0 && (int) $g->RegionId === 0 && (int) $g->TerrId === 0) {
            return true; // BU head
        }

        return \App\Services\Odometer\BackdateApproval::isHod($self, (int) $g->DepartmentId);
    }

    /** Employee id the report runs for. */
    public static function subject($requested, $user = null): int
    {
        $user = $user ?: auth()->user();
        if (! $user) {
            $emp = (int) (request()?->attributes->get('api_auth_empid') ?? 0);

            return $emp > 0 ? $emp : (int) $requested;
        }

        return RecordAccess::isStaff($user) && (int) $requested > 0 ? (int) $requested : RecordAccess::selfId($user);
    }
}
