<?php

namespace App\Services\Btc;

use App\Support\ReportingTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-DASH-ROLE-01 (23 Nov 2026, "Decision for You" testing point 2): what the BTC dashboard may show a user.
 *
 *  - Super Admin (roles "Super Admin" / "BTC Super Admin"): the whole company.
 *  - Admin (any other admin / BTC desk role): the departments of that responsibility - the department lists of the
 *    user's BTC roles (BTC > Roles & Access); a role with no list gives only the user's own department. Never the
 *    whole company.
 *  - Reporting manager: own requests plus every direct and indirect reportee's.
 *  - Everyone else: own requests only.
 * Levels add up (an admin who is also a manager sees the departments and the team). Applied in the query, so a
 * user cannot see a request outside the scope by changing the address.
 */
class BtcDashboardScope
{
    public const SUPER_ROLES = ['Super Admin', 'super_admin', 'BTC Super Admin'];

    public const ADMIN_ROLES = ['Admin', 'admin', 'BTC Admin', 'Finance'];

    public const DESK_PERMS = ['btc.admin', 'btc.audit', 'btc.access', 'btc.dms', 'btc.bill_upload', 'btc.verifier',
        'btc.fin_approver', 'btc.finance', 'btc.checker', 'btc.treasury', 'btc.booking', 'btc.advance', 'btc.dashboard'];

    /**
     * @return array{level: string, label: string, departments: int[]|null, employees: int[]|null, user_id: int}
     *   departments / employees null = no restriction (company level)
     */
    public static function for($user): array
    {
        $uid = (int) ($user->id ?? 0);
        $emp = (int) ($user->employee_id ?? 0);
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::SUPER_ROLES)) {
            return ['level' => 'company', 'label' => 'Whole company (Super Admin)', 'departments' => null, 'employees' => null, 'user_id' => $uid];
        }

        $team = $emp > 0 ? ReportingTree::descendants($emp) : [];
        $employees = array_values(array_unique(array_filter(array_merge([$emp], $team))));

        $departments = [];
        if (self::isAdmin($user)) {
            $departments = self::roleDepartments($user);
            if (! $departments && ($own = self::ownDepartment($emp))) {
                $departments = [$own];
            }
        }

        if ($departments) {
            $names = self::departmentNames($departments);
            $label = 'Department'.(count($departments) > 1 ? 's' : '').': '.implode(', ', $names).($team ? ' + your team' : '');
            $level = 'department';
        } elseif ($team) {
            $label = 'You and your team ('.count($team).' reportee'.(count($team) > 1 ? 's' : '').', all levels)';
            $level = 'team';
        } else {
            $label = 'Your own requests';
            $level = 'own';
        }

        return ['level' => $level, 'label' => $label, 'departments' => $departments, 'employees' => $employees, 'user_id' => $uid];
    }

    /** Restrict a btc_cases query to the scope. */
    public static function apply($query, array $scope)
    {
        if ($scope['level'] === 'company') {
            return $query;
        }

        return $query->where(function ($w) use ($scope) {
            $w->whereIn('employee_id', $scope['employees'] ?: [0])->orWhere('created_by', $scope['user_id'] ?: -1);
            if ($scope['departments']) {
                $w->orWhereIn('department_id', $scope['departments']);
            }
        });
    }

    public static function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }
        foreach (self::DESK_PERMS as $p) {
            if ($user->can($p)) {
                return true;
            }
        }

        return false;
    }

    /** Department ids from the department lists of the user's active BTC roles (roles without a list add none). */
    private static function roleDepartments($user): array
    {
        if (! Schema::hasTable('btc_role_settings')) {
            return [];
        }
        $roleIds = DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', get_class($user))->pluck('role_id')->all();
        if (! $roleIds) {
            return [];
        }
        $ids = [];
        foreach (DB::table('btc_role_settings')->whereIn('role_id', $roleIds)->where('is_active', 1)->pluck('department_ids') as $list) {
            $ids = array_merge($ids, array_filter(array_map('intval', explode(',', (string) $list))));
        }

        return array_values(array_unique($ids));
    }

    private static function ownDepartment(int $emp): ?int
    {
        if ($emp <= 0) {
            return null;
        }
        try {
            $d = DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $emp)->value('DepartmentId');

            return $d ? (int) $d : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function departmentNames(array $ids): array
    {
        try {
            $map = DB::connection('hrims')->table('core_departments')->whereIn('id', $ids)->pluck('department_name', 'id');
        } catch (\Throwable $e) {
            $map = collect();
        }

        return array_map(fn ($id) => $map[$id] ?? ('#'.$id), $ids);
    }
}
