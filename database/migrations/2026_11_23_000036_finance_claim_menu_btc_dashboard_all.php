<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 23 Nov 2026, "Decision for You" testing points 1 and 2.
 *  - Point 1: the /claims page is the Finance claim page (Pending / Approved Claims (Finance)). The 20 Nov page
 *    cleanup kept one menu item for it under the title "Claims", so Finance could not find "Finance Claim". The item
 *    is titled "Finance Claim" again (same permissions, same place in the menu).
 *  - Point 2 (BTC-DASH-ROLE-01): the BTC Dashboard opens for everyone; its figures follow the user's role and reporting
 *    line, so its menu item no longer needs the btc.dashboard permission.
 * Idempotent; rows are updated, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('expense');
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection('expense');
        $hasUpd = $s->hasColumn('menus', 'updated_at');
        $set = fn (array $r) => $hasUpd ? $r + ['updated_at' => now()] : $r;

        $claims = $db->table('menus')->whereIn('url', ['claims', '/claims'])->where('status', 1)->orderBy('id')->first();
        if ($claims) {
            $db->table('menus')->where('id', $claims->id)->update($set(['title' => 'Finance Claim', 'icon' => 'ri-bank-line', 'data_key' => 'finance-claim']));
        }

        $db->table('menus')->whereIn('url', ['btc/dashboard', '/btc/dashboard'])->update($set(['permission_name' => null, 'permission_id' => null]));

        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
