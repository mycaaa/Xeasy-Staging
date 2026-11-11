<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** F-02 / F-06 / F-08: menu entry for the Finance review flags screen, next to Focus Ledger vs Xeasy. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'finance-review-flags')->exists()) {
            $cols = array_flip(Schema::getColumnListing('menus'));
            $parent = DB::table('menus')->where('url', 'expense-payments/ledger-summary')->value('parent_id');
            DB::table('menus')->insert(array_intersect_key([
                'title' => 'Finance Review Flags', 'url' => 'finance-review-flags', 'icon' => 'ri-flag-line', 'parent_id' => $parent,
                'permission_name' => 'Ledger Summary', 'company_access_id' => '1,3,11', 'order' => 24, 'data_key' => 'finance-review-flags',
                'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ], $cols));
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'finance-review-flags')->delete();
    }
};
