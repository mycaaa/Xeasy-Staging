<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existing = DB::table('menus')->where('url', 'not-showing-report')->first();
        if (!$existing) {
            DB::table('menus')->insert([
                'title' => 'Not Showing Report',
                'url' => 'not-showing-report',
                'icon' => 'ri-file-search-line',
                'parent_id' => 7,
                'permission_name' => 'not_showing_report',
                'company_access_id' => '11',
                'order' => 10,
                'data_key' => 'not-showing-report',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('url', 'not-showing-report')->delete();
    }
};
