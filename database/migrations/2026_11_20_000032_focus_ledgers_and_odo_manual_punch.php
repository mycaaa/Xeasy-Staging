<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 20 Nov 2026, tracker tab "Decision for You":
 *  - BTC-FOCUS-02 (item 7): btc_focus_ledgers / btc_focus_heads - Focus ledger codes kept by Finance through Excel,
 *    seeded with the codes written in the tracker
 *  - ODO-PUNCH-01 (item 3): odo_manual_readings - odometer readings changed by the employee wait for the punching
 *    team; menu "Odometer readings changed" next to Data Punch
 * Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_focus_ledgers')) {
            Schema::create('btc_focus_ledgers', function (Blueprint $t) {
                $t->id();
                $t->string('activity_type', 80)->default('');      // blank = travel / stay / vehicle rows
                $t->string('expense_type', 60);
                $t->string('ledger_head', 150)->nullable();
                $t->string('sub_ledger', 150)->nullable();
                $t->string('vnr_ledger', 40)->nullable();
                $t->string('vnr_sub', 40)->nullable();
                $t->string('vvnr_ledger', 40)->nullable();
                $t->string('vvnr_sub', 40)->nullable();
                $t->timestamps();
                $t->unique(['activity_type', 'expense_type']);
            });
        }
        if (! Schema::hasTable('btc_focus_heads')) {
            Schema::create('btc_focus_heads', function (Blueprint $t) {
                $t->id();
                $t->string('head', 40)->unique();
                $t->string('focus_code', 80);
                $t->timestamps();
            });
        }
        \App\Services\Btc\FocusLedgers::forget();
        \App\Services\Btc\FocusLedgers::seed();

        if (! Schema::hasTable('odo_manual_readings')) {
            Schema::create('odo_manual_readings', function (Blueprint $t) {
                $t->id();
                $t->string('conn', 40);
                $t->unsignedInteger('year_id');
                $t->unsignedBigInteger('exp_id');
                $t->unsignedBigInteger('emp_id');
                $t->date('bill_date')->nullable();
                $t->integer('ocr_opening')->nullable();
                $t->integer('ocr_closing')->nullable();
                $t->integer('entered_opening')->nullable();
                $t->integer('entered_closing')->nullable();
                $t->string('reason', 255)->nullable();
                $t->string('status', 20)->default('pending')->index();   // pending | punched | not_needed
                $t->unsignedBigInteger('punched_by')->nullable();
                $t->dateTime('punched_at')->nullable();
                $t->integer('punched_opening')->nullable();
                $t->integer('punched_closing')->nullable();
                $t->string('remark', 255)->nullable();
                $t->timestamps();
                $t->unique(['conn', 'year_id', 'exp_id']);
            });
        }

        $s = Schema::connection('expense');
        if ($s->hasTable('menus')) {
            $db = DB::connection('expense');
            $cols = array_flip($s->getColumnListing('menus'));
            $only = fn (array $r) => array_intersect_key($r, $cols);
            $sib = $db->table('menus')->whereIn('url', ['data-punch/uploaded', '/data-punch/uploaded'])->first()
                ?? $db->table('menus')->where('url', 'like', '%data-punch/%')->where('url', 'not like', '%odometer-changed%')->orderBy('id')->first();
            if ($sib) {
                $set = ['title' => 'Odometer readings changed', 'url' => 'data-punch/odometer-changed', 'parent_id' => $sib->parent_id ?? null,
                    'permission_name' => $sib->permission_name ?? 'section_data_punching', 'order' => (int) ($sib->order ?? 10) + 1,
                    'icon' => 'ri-dashboard-3-line', 'status' => 1, 'updated_at' => now()];
                if (isset($cols['company_access_id'])) {
                    $set['company_access_id'] = $sib->company_access_id ?? '1,3,11';
                }
                $id = $db->table('menus')->where('url', 'like', '%data-punch/odometer-changed%')->value('id');
                $id ? $db->table('menus')->where('id', $id)->update($only($set)) : $db->table('menus')->insert($only($set + ['data_key' => 'odo_manual_punch', 'created_at' => now()]));
            }
            try {
                \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        // additive only
    }
};
