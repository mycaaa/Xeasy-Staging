<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-ROLES-01 (13 Nov 2026): the BTC desks were given only to the Admin role. Each team now gets its own desk:
 *   Mediator (punching team) -> btc.dms        (bills submitted: DMS processing, boarding passes for all)
 *   Finance                  -> btc.finance    (finance queue, pending originals / boarding passes, vendor links)
 *   Payment                  -> btc.checker    (checker step before treasury)
 *   Treasury                 -> btc.treasury   (treasury queue)
 * Admin keeps all of them (and btc.admin). Only adds grants; safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $grants = ['Mediator' => 'btc.dms', 'Finance' => 'btc.finance', 'Payment' => 'btc.checker', 'Treasury' => 'btc.treasury'];
        foreach ($grants as $role => $perm) {
            $roleId = DB::table('roles')->where('name', $role)->value('id');
            $permId = DB::table('permissions')->where('name', $perm)->value('id');
            if ($roleId && $permId && ! DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $permId)->exists()) {
                DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permId]);
            }
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
        \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
    }

    public function down(): void
    {
        foreach (['Mediator' => 'btc.dms', 'Finance' => 'btc.finance', 'Payment' => 'btc.checker', 'Treasury' => 'btc.treasury'] as $role => $perm) {
            $roleId = DB::table('roles')->where('name', $role)->value('id');
            $permId = DB::table('permissions')->where('name', $perm)->value('id');
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $permId)->delete();
        }
    }
};
