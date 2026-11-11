<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ACCESS-01 (decided 10 Nov 2026): the Admin role gets the Settings and Focus-mapping rights
 * (seection_settings, settings_focus_export and the claim-Focus-mapping list/new/modify rights);
 * REM-SET-01: "Reminder Settings" menu under Settings. Re-runnable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $admin = DB::table('roles')->where('name', 'Admin')->value('id');
            if ($admin) {
                $perms = DB::table('permissions')->whereIn('name', ['seection_settings', 'settings_focus_export', 'list_claim_focus_mapping', 'new_claim_focus_mapping', 'modify_claim_focus_mapping'])->pluck('id');
                foreach ($perms as $p) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $p, 'role_id' => $admin]);
                }
            }
            if (Schema::hasTable('cache')) {
                DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
            }
        }
        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'reminder-settings')->exists()) {
            $cols = array_flip(Schema::getColumnListing('menus'));
            $settings = DB::table('menus')->where('title', 'Settings')->whereNull('parent_id')->value('id');
            DB::table('menus')->insert(array_intersect_key([
                'title' => 'Reminder Settings', 'url' => 'reminder-settings', 'icon' => 'ri-alarm-line', 'parent_id' => $settings,
                'permission_name' => 'seection_settings', 'company_access_id' => '1,3,11', 'order' => 90, 'data_key' => 'reminder-settings',
                'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ], $cols));
        }
    }

    public function down(): void
    {
    }
};
