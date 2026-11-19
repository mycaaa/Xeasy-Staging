<?php

namespace App\Services\Btc;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-01 (16 Nov 2026): what a user's BTC roles allow beyond permissions - entity (company) and department
 * scope, and the approval limit - plus the access history (btc_access_logs).
 *
 * A role's scope and limit are on BTC > Roles & Access (btc_role_settings). A user with several BTC roles gets the
 * widest of them: a role with no company / department list means all, a role with no limit means no limit.
 * Users with no BTC role keep today's behaviour (no extra scope, no limit).
 */
class BtcAccess
{
    private static array $cache = [];

    /** BTC role settings of the user's roles that are active. */
    private static function settings($user): array
    {
        if (! $user || ! Schema::hasTable('btc_role_settings')) {
            return [];
        }
        $key = (int) $user->id;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $roleIds = DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', get_class($user))->pluck('role_id')->all();

        return self::$cache[$key] = $roleIds
            ? DB::table('btc_role_settings')->whereIn('role_id', $roleIds)->where('is_active', 1)->get()->all()
            : [];
    }

    /** @return array{companies: int[]|null, departments: int[]|null} null = no restriction */
    public static function scope($user): array
    {
        if (! $user || $user->can('btc.admin')) {
            return ['companies' => null, 'departments' => null];
        }
        $rows = self::settings($user);
        if (! $rows) {
            return ['companies' => null, 'departments' => null];
        }
        $merge = function (string $col) use ($rows) {
            $all = [];
            foreach ($rows as $r) {
                $ids = array_filter(array_map('intval', explode(',', (string) $r->$col)));
                if (! $ids) {
                    return null; // one role without a list = everything
                }
                $all = array_merge($all, $ids);
            }

            return array_values(array_unique($all));
        };

        return ['companies' => $merge('company_ids'), 'departments' => $merge('department_ids')];
    }

    /** Restrict a btc_cases query to the user's entity / department scope. */
    public static function applyScope($query, $user, string $prefix = '')
    {
        $s = self::scope($user);
        if ($s['companies'] !== null) {
            $query->whereIn($prefix.'company_id', $s['companies']);
        }
        if ($s['departments'] !== null) {
            $query->whereIn($prefix.'department_id', $s['departments']);
        }
        // BTC-REQ-18 (17 Nov 2026): dashboard, register and 'All cases' follow the user's role - the booking admin sees the
        // admin bookings, the advance desk the advance requests; Finance-side desks, audit and BTC admin see every request
        if ($routes = self::roleRoutes($user)) {
            $query->whereIn($prefix.'route', $routes);
        }

        return $query;
    }

    /** null = every route; else the routes this user's desks handle. */
    public static function roleRoutes($user): ?array
    {
        if (! $user) {
            return null;
        }
        foreach (['btc.admin', 'btc.audit', 'btc.access', 'btc.dms', 'btc.bill_upload', 'btc.verifier', 'btc.fin_approver', 'btc.finance', 'btc.checker', 'btc.treasury'] as $p) {
            if ($user->can($p)) {
                return null;
            }
        }
        $r = [];
        if ($user->can('btc.booking')) {
            $r[] = 'admin_booking';
        }
        if ($user->can('btc.advance')) {
            $r[] = 'employee_advance';
        }

        return $r ?: null;
    }

    /** Highest approval limit of the user's BTC roles that set one; null = no limit. */
    public static function approvalLimit($user): ?float
    {
        if (! $user || $user->can('btc.admin')) {
            return null;
        }
        $limits = array_filter(array_map(fn ($r) => $r->approval_limit === null ? null : (float) $r->approval_limit, self::settings($user)), fn ($x) => $x !== null);

        return $limits ? max($limits) : null;
    }

    public static function log(string $action, array $data = []): void
    {
        if (! Schema::hasTable('btc_access_logs')) {
            return;
        }
        $u = auth()->user();
        DB::table('btc_access_logs')->insert([
            'action' => $action, 'role_id' => $data['role_id'] ?? null, 'role_name' => $data['role_name'] ?? null,
            'target_user_id' => $data['target_user_id'] ?? null, 'target_user' => $data['target_user'] ?? null,
            'details' => isset($data['details']) ? (is_string($data['details']) ? $data['details'] : json_encode($data['details'], JSON_UNESCAPED_UNICODE)) : null,
            'actor_id' => $u->id ?? null, 'actor_name' => $u->name ?? null, 'ip' => request()?->ip(), 'created_at' => now(),
        ]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
