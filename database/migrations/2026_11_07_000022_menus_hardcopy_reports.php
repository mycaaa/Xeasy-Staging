<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** HARDCOPY-01, ELIG-REPORT-01, PUNCH-TRACK-01 (7 Nov 2026): menu entries. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }
        $cols = array_flip(Schema::getColumnListing('menus'));
        $add = function (string $title, string $url, ?int $parent, string $perm, int $order, string $icon) use ($cols) {
            if (DB::table('menus')->where('url', $url)->exists()) {
                return;
            }
            DB::table('menus')->insert(array_intersect_key([
                'title' => $title, 'url' => $url, 'icon' => $icon, 'parent_id' => $parent, 'permission_name' => $perm,
                'company_access_id' => '1,3,11', 'order' => $order, 'data_key' => $url, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ], $cols));
        };
        $claimantParent = DB::table('menus')->where('url', 'claimant')->value('parent_id');
        $add('Submit Hardcopy Bills', 'hardcopy-bills', $claimantParent, DB::table('menus')->where('url', 'claimant')->value('permission_name') ?? 'Upload Claim', 5, 'ri-mail-send-line');
        $empReports = DB::table('menus')->where('title', 'Employee Reports')->value('id') ?: DB::table('menus')->where('title', 'Reports')->whereNull('parent_id')->value('id');
        $add('Employee Eligibility Report', 'eligibility-report', $empReports, 'Section Reports', 20, 'ri-user-star-line');
        $claimReports = DB::table('menus')->where('title', 'Claim Reports')->value('id') ?: DB::table('menus')->where('title', 'Reports')->whereNull('parent_id')->value('id');
        $add('Punching Change Track', 'punching-change-report', $claimReports, 'Section Reports', 32, 'ri-history-line');
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('url', ['hardcopy-bills', 'eligibility-report', 'punching-change-report'])->delete();
    }
};
