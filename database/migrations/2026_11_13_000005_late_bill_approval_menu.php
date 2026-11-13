<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LATE-PUNCH-01 (13 Nov 2026): "Late Bill Approval" in the side menu under Employee, for everyone (the screen
 * itself shows each GM / admin only the bills they approve).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus') || DB::table('menus')->where('url', 'late-bills')->exists()) {
            return;
        }
        $cols = array_flip(Schema::getColumnListing('menus'));
        $parent = DB::table('menus')->where('title', 'Employee')->whereNull('parent_id')->value('id');
        DB::table('menus')->insert(array_intersect_key([
            'title' => 'Late Bill Approval', 'url' => 'late-bills', 'icon' => 'ri-time-line', 'parent_id' => $parent,
            'permission_name' => null, 'order' => 6, 'data_key' => 'late-bills', 'company_access_id' => '1,3,11',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ], $cols));
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'late-bills')->delete();
    }
};
