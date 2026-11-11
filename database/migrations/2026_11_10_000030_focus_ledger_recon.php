<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** FOCUS-RECON-01: log of Focus ledger comparisons, and the menu entry next to Ledger Summary. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('focus_ledger_recon_logs')) {
            Schema::create('focus_ledger_recon_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->unsignedInteger('company_id')->default(1);
                $t->unsignedInteger('employee_id')->index();
                $t->string('emp_code', 20)->nullable();
                $t->string('file_name', 190)->nullable();
                $t->date('period_from')->nullable();
                $t->date('period_to')->nullable();
                $t->decimal('focus_closing', 14, 2)->default(0);
                $t->decimal('xeasy_closing', 14, 2)->default(0);
                $t->decimal('difference', 14, 2)->default(0);
                $t->unsignedInteger('items')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }
        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'focus-ledger-recon')->exists()) {
            $cols = array_flip(Schema::getColumnListing('menus'));
            $parent = DB::table('menus')->where('url', 'expense-payments/ledger-summary')->value('parent_id');
            DB::table('menus')->insert(array_intersect_key([
                'title' => 'Focus Ledger vs Xeasy', 'url' => 'focus-ledger-recon', 'icon' => 'ri-scales-3-line', 'parent_id' => $parent,
                'permission_name' => 'Ledger Summary', 'company_access_id' => '1,3,11', 'order' => 23, 'data_key' => 'focus-ledger-recon',
                'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ], $cols));
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url', 'focus-ledger-recon')->delete();
        Schema::dropIfExists('focus_ledger_recon_logs');
    }
};
