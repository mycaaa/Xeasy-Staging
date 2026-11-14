<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADV-MENU-01 (14 Nov 2026): employees had no way to raise an advance request on the web. The screens existed
 * (advance-requests: own requests with New Request; advance-requests/create) but the only menu items were the
 * admin ones under Settings (Advance Request master, New Advance Request on behalf of an employee). This adds
 * "Advance Request" under Employee, for everyone who can upload claims, in companies 1, 3 and 11, after Claimant
 * and Approver. Safe to run again.
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

        $parent = $db->table('menus')->where('title', 'Employee')->whereNull('parent_id')->value('id');
        if (! $parent) {
            return;
        }
        $set = ['title' => 'Advance Request', 'url' => 'advance-requests', 'icon' => 'ri-hand-coin-line', 'parent_id' => $parent,
            'permission_name' => 'Upload Claim', 'company_access_id' => '1,3,11', 'order' => 3, 'status' => 1, 'updated_at' => now()];
        $ids = $db->table('menus')->whereIn('url', ['advance-requests', '/advance-requests'])->orderBy('id')->pluck('id')->all();
        if (! $ids) {   // first run: make room after Claimant (1) and Approver (2)
            $db->table('menus')->where('parent_id', $parent)->where('order', '>=', 3)->increment('order');
        }
        if ($ids) {
            $db->table('menus')->where('id', array_shift($ids))->update($only($set));
            if ($ids) {
                $db->table('menus')->whereIn('id', $ids)->update($only(['status' => 0, 'updated_at' => now()]));
            }
        } else {
            $db->table('menus')->insert($only($set + ['data_key' => 'employee-advance-request', 'created_at' => now()]));
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
