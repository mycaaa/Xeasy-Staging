<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VVNR-LOG-01 / VVNR-COURIER-01 (Finance mails 19-31 Oct): menu entries for VVNR.
 *  - "New Email Logs" (email-send-log) was VNR only; VVNR users now see it (it reads the VVNR log).
 *  - "Not Showing Report" (not-showing-report) had no menu; added under Reports > Claim Reports for VVNR.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }
        foreach (DB::table('menus')->where('url', 'email-send-log')->get() as $m) {
            $ids = array_filter(array_map('trim', explode(',', (string) $m->company_access_id)));
            if (! in_array('11', $ids, true)) {
                $ids[] = '11';
                DB::table('menus')->where('id', $m->id)->update(['company_access_id' => implode(',', $ids), 'updated_at' => now()]);
            }
        }
        if (! DB::table('menus')->where('url', 'not-showing-report')->exists()) {
            $parent = DB::table('menus')->where('title', 'Claim Reports')->value('id') ?: DB::table('menus')->where('title', 'Reports')->whereNull('parent_id')->value('id');
            DB::table('menus')->insert([
                'title' => 'Not Showing Report',
                'url' => 'not-showing-report',
                'icon' => 'ri-file-search-line',
                'parent_id' => $parent,
                'permission_name' => 'Section Reports',
                'company_access_id' => '11',
                'order' => 30,
                'data_key' => 'not-showing-report',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'not-showing-report')->where('company_access_id', '11')->delete();
    }
};
