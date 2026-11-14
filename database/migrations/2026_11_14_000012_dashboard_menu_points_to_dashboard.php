<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MENU-DASH-01 (14 Nov 2026): the side menu's first item, "Dashboard", opened "home" - the company Expense
 * Comparison page - not the Dashboard people get after signing in (My Claims, Approvals, Verification, Finance).
 * From Expense Comparison, the Dashboard item (and the logo, which the collapsed menu drew it under) kept people on
 * the same page. This makes "Dashboard" open the sign-in Dashboard for everyone, and keeps the old page in the menu
 * as "Expense Comparison" for those who had it. Safe to run again.
 */
return new class extends Migration
{
    private string $conn = 'expense';

    public function up(): void
    {
        $s = Schema::connection($this->conn);
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection($this->conn);
        $cols = array_flip($s->getColumnListing('menus'));
        $only = fn (array $r) => array_intersect_key($r, $cols);

        // the old item keeps its permission and position, under its real name
        $db->table('menus')->whereNull('parent_id')->whereIn('url', ['home', '/home'])->where('title', 'Dashboard')
            ->update($only(['title' => 'Expense Comparison', 'icon' => 'ri-bar-chart-2-line', 'updated_at' => now()]));

        $set = ['title' => 'Dashboard', 'url' => 'dashboard', 'icon' => 'ri-dashboard-2-line', 'parent_id' => null,
            'permission_name' => null, 'company_access_id' => '1,3,11', 'order' => 0, 'status' => 1, 'updated_at' => now()];
        $ids = $db->table('menus')->whereNull('parent_id')->whereIn('url', ['dashboard', '/dashboard'])->orderBy('id')->pluck('id')->all();
        if ($ids) {
            $db->table('menus')->where('id', array_shift($ids))->update($only($set));
            if ($ids) {
                $db->table('menus')->whereIn('id', $ids)->update($only(['status' => 0, 'updated_at' => now()]));
            }
        } else {
            $db->table('menus')->insert($only($set + ['data_key' => 'main-dashboard', 'created_at' => now()]));
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // menu rows are left in place
    }
};
