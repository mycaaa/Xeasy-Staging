<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FNF-01 (20 Nov 2026, tracker tab "FNF_Xeasy 20 Nov"):
 *  - fnf_clearances: Finance clearance of each separated employee (HR recovery / payment entered by Finance, the
 *    working kept when the clearance is done);
 *  - menu "FNF - Finance Clearance" next to the payment screens;
 *  - separation expense reminder (sections 1-6): daily from 7 days before the last working day up to that day.
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnf_clearances')) {
            Schema::create('fnf_clearances', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('company_id')->default(1);
                $t->unsignedBigInteger('employee_id');
                $t->string('emp_code', 20)->nullable();
                $t->string('emp_name', 150)->nullable();
                $t->date('separation_date')->nullable();
                $t->decimal('hr_amount', 14, 2)->default(0);   // + payable to the employee, - recoverable (as updated by HR)
                $t->string('hr_remark', 500)->nullable();
                $t->decimal('ledger_balance', 14, 2)->nullable();
                $t->decimal('hold_amount', 14, 2)->nullable();
                $t->decimal('expense_total', 14, 2)->nullable();
                $t->decimal('final_balance', 14, 2)->nullable();
                $t->string('status', 20)->default('pending');   // pending | cleared
                $t->longText('working_json')->nullable();
                $t->string('clear_remark', 600)->nullable();
                $t->unsignedBigInteger('cleared_by')->nullable();
                $t->dateTime('cleared_at')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'employee_id']);
            });
        }

        // separation reminder: daily, from 7 days before the last working day (eml_resigned_reminder_settings)
        if (Schema::hasTable('eml_resigned_reminder_settings')) {
            $set = ['days_before_separation' => 7, 'min_days_between_reminders' => 1, 'max_reminders_per_employee' => 8, 'frequency' => 'daily', 'updated_at' => now()];
            DB::table('eml_resigned_reminder_settings')->where('id', 1)->exists()
                ? DB::table('eml_resigned_reminder_settings')->where('id', 1)->update($set)
                : DB::table('eml_resigned_reminder_settings')->insert($set + ['id' => 1, 'is_active' => 1, 'send_time' => '09:00',
                    'target_companies' => json_encode([1, 2, 3, 4, 11]), 'query_email' => 'query@vnrseeds.com', 'created_at' => now()]);
        }

        $s = Schema::connection('expense');
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection('expense');
        $cols = array_flip($s->getColumnListing('menus'));
        $only = fn (array $r) => array_intersect_key($r, $cols);
        $sib = $db->table('menus')->whereIn('url', ['expense-payments/instructed', '/expense-payments/instructed', 'expense-payments', '/expense-payments'])->orderBy('id')->first();
        $set = ['title' => 'FNF - Finance Clearance', 'url' => 'fnf', 'parent_id' => $sib->parent_id ?? null, 'permission_name' => $sib->permission_name ?? 'payment_section',
            'order' => (int) ($sib->order ?? 90) + 20, 'icon' => 'ri-user-unfollow-line', 'status' => 1, 'updated_at' => now()];
        if (isset($cols['company_access_id'])) {
            $set['company_access_id'] = $sib->company_access_id ?? '1,3,11';
        }
        $id = $db->table('menus')->whereIn('url', ['fnf', '/fnf'])->value('id');
        $id ? $db->table('menus')->where('id', $id)->update($only($set)) : $db->table('menus')->insert($only($set + ['data_key' => 'fnf', 'created_at' => now()]));
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
