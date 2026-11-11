<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REP-32 (Finance, 5 Nov): menu entry for the VVNR stepwise pending payment report,
 * next to "Not Showing Report" under Reports > Claim Reports, VVNR only (company 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus') || DB::table('menus')->where('url', 'vvnr-pending-payment')->exists()) {
            return;
        }
        $parent = DB::table('menus')->where('url', 'not-showing-report')->value('parent_id')
            ?: (DB::table('menus')->where('title', 'Claim Reports')->value('id') ?: DB::table('menus')->where('title', 'Reports')->whereNull('parent_id')->value('id'));
        DB::table('menus')->insert([
            'title' => 'Stepwise Pending Payment',
            'url' => 'vvnr-pending-payment',
            'icon' => 'ri-route-line',
            'parent_id' => $parent,
            'permission_name' => 'Section Reports',
            'company_access_id' => '11',
            'order' => 31,
            'data_key' => 'vvnr-pending-payment',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'vvnr-pending-payment')->where('company_access_id', '11')->delete();
    }
};
