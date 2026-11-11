<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** KM-CAP-01: approvals table and the "Km Limit Approvals" menu for HODs (under the Approver menu, no permission). */
return new class extends Migration
{
    public function up(): void
    {
        \App\Services\ClaimRules\KmCapApproval::ensureTable($this->getConnection() ?: config('database.default'));
        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'km-cap-approvals')->exists()) {
            $cols = array_flip(Schema::getColumnListing('menus'));
            DB::table('menus')->insert(array_intersect_key([
                'title' => 'Km Limit Approvals', 'url' => 'km-cap-approvals', 'icon' => 'ri-steering-2-line', 'parent_id' => null,
                'permission_name' => null, 'company_access_id' => '1,3,11', 'order' => 7, 'data_key' => 'km-cap-approvals',
                'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ], $cols));
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'km-cap-approvals')->delete();
    }
};
