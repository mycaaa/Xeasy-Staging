<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MENU-02 (13 Nov 2026): BTC and Boarding Pass (company-booked tickets) were still missing from the side menu.
 * The side menu shows only items whose company_access_id lists the current company; 000003 set that list only on
 * rows it created, so an existing BTC row kept its old list (or none), and it wrote through the default database
 * connection while the menu is read from "expense". This puts the group and its three items on the menu database,
 * active and open to companies 1, 3 and 11. Safe to run again.
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
        $common = ['company_access_id' => '1,3,11', 'status' => 1, 'updated_at' => now()];

        $groupId = $db->table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id');
        if ($groupId) {
            $db->table('menus')->where('id', $groupId)->update($only($common + ['url' => '#', 'icon' => 'ri-bill-line', 'permission_name' => null]));
        } else {
            $groupId = $db->table('menus')->insertGetId($only($common + ['title' => 'Bill To Company', 'url' => '#', 'icon' => 'ri-bill-line',
                'parent_id' => null, 'permission_name' => null, 'order' => 9, 'data_key' => 'bill-to-company-group', 'created_at' => now()]));
        }

        $items = [
            ['BTC Cases', ['btc', '/btc'], null, 1, 'ri-file-list-3-line'],
            ['Boarding Pass (Company-Booked Tickets)', ['btc/boarding-passes', '/btc/boarding-passes'], null, 2, 'ri-plane-line'],
            ['Vendor Boarding-Pass Links', ['btc/vendor-links', '/btc/vendor-links'], 'btc.finance', 3, 'ri-links-line'],
        ];
        foreach ($items as [$title, $urls, $perm, $order, $icon]) {
            $ids = $db->table('menus')->whereIn('url', $urls)->orderBy('id')->pluck('id')->all();
            $set = $common + ['title' => $title, 'url' => $urls[0], 'parent_id' => $groupId, 'permission_name' => $perm, 'order' => $order, 'icon' => $icon];
            if ($ids) {
                $db->table('menus')->where('id', array_shift($ids))->update($only($set));
                if ($ids) { // duplicates left by earlier runs
                    $db->table('menus')->whereIn('id', $ids)->update($only(['status' => 0, 'updated_at' => now()]));
                }
            } else {
                $db->table('menus')->insert($only($set + ['data_key' => $urls[0], 'created_at' => now()]));
            }
        }
    }

    public function down(): void
    {
        // menu rows are left in place
    }
};
