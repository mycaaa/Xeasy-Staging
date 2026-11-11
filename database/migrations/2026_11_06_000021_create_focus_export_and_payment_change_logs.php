<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * FOCUS-LOG-01 / PAY-LOG-01 (audit LV-C07, X-03; 6 Nov 2026).
 * Both logs live in the main expense database for every company (company_id column), so the
 * read-only VVNR copy is never written. Rows are append-only (see the models).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('focus_export_logs')) {
            Schema::create('focus_export_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('company_id')->nullable();
                $t->unsignedSmallInteger('year_id')->nullable();
                $t->string('export_type', 40);            // claim_focus | claim_focus_final | payment_export_only | payment_release | payment_combined | payment_focus
                $t->string('file_name', 190)->nullable();
                $t->unsignedInteger('row_count')->default(0);
                $t->decimal('total_amount', 14, 2)->nullable();
                $t->char('content_hash', 64)->nullable();  // sha256 of the stored file, or of the exported rows
                $t->string('hash_of', 10)->default('rows'); // file | rows
                $t->json('filters')->nullable();
                $t->unsignedBigInteger('exported_by')->nullable();
                $t->string('exported_by_name', 120)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['company_id', 'year_id', 'created_at'], 'fel_company_year');
            });
        }
        if (! Schema::hasTable('payment_change_logs')) {
            Schema::create('payment_change_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('company_id')->nullable();
                $t->unsignedSmallInteger('year_id')->nullable();
                $t->string('entity', 40);                 // month_final | advance | ledger
                $t->string('table_name', 64)->nullable();
                $t->unsignedBigInteger('record_id')->nullable();
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->string('field', 40);
                $t->string('old_value', 120)->nullable();
                $t->string('new_value', 120)->nullable();
                $t->string('source', 60);
                $t->string('remark', 500)->nullable();
                $t->unsignedBigInteger('changed_by')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['entity', 'record_id'], 'pcl_entity_record');
                $t->index(['created_at'], 'pcl_created');
            });
        }

        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'focus-export-log')->exists()) {
            $parent = DB::table('menus')->where('title', 'Payment')->whereNull('parent_id')->value('id');
            $row = ['title' => 'Focus Export Log', 'url' => 'focus-export-log', 'icon' => 'ri-file-list-3-line', 'parent_id' => $parent,
                'permission_name' => 'payment_section', 'company_access_id' => '1,11', 'order' => 91, 'data_key' => 'focus-export-log',
                'status' => 1, 'created_at' => now(), 'updated_at' => now()];
            DB::table('menus')->insert(array_intersect_key($row, array_flip(Schema::getColumnListing('menus'))));
        }
    }

    public function down(): void
    {
        // append-only audit logs: never dropped by a rollback
    }
};
