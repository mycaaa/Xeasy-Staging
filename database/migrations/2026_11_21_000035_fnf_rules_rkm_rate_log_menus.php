<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 21 Nov 2026, tracker tab "Decision for You", testing points 3a, 3b and 5:
 *  - fnf_rules: F&F rules with effective dates (Payment > F&F Rules)
 *  - running_km_rate_logs: running-km rate set by hand, with reason, user and address
 *  - menus under Payment, below "FNF - Finance Clearance": "F&F Rules" and "F&F Reminder Mails"
 * Additive and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fnf_rules')) {
            Schema::create('fnf_rules', function (Blueprint $t) {
                $t->id();
                $t->string('rule_key', 40)->index();
                $t->integer('value');
                $t->date('effective_from');
                $t->date('effective_to')->nullable();
                $t->string('remark', 300)->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->string('updated_by_name', 150)->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('running_km_rate_logs')) {
            Schema::create('running_km_rate_logs', function (Blueprint $t) {
                $t->id();
                $t->string('conn', 40);
                $t->unsignedInteger('year_id');
                $t->unsignedBigInteger('exp_id');
                $t->unsignedBigInteger('emp_id')->nullable();
                $t->string('action', 10);   // manual | auto
                $t->decimal('old_rate', 8, 2)->nullable();
                $t->decimal('new_rate', 8, 2);
                $t->decimal('eligible_rate', 8, 2)->nullable();
                $t->decimal('km', 10, 2)->nullable();
                $t->decimal('old_amount', 12, 2)->nullable();
                $t->decimal('new_amount', 12, 2)->nullable();
                $t->string('reason', 300);
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('user_name', 150)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
                $t->index(['conn', 'year_id', 'exp_id']);
            });
        }

        $s = Schema::connection('expense');
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection('expense');
        $cols = array_flip($s->getColumnListing('menus'));
        $only = fn (array $r) => array_intersect_key($r, $cols);
        $fnf = $db->table('menus')->where('url', 'fnf')->first();
        if (! $fnf) {
            return;
        }
        $order = (int) ($fnf->order ?? 50);
        // keep the two new items right below "FNF - Finance Clearance"
        $db->table('menus')->where('parent_id', $fnf->parent_id)->where('order', '>', $order)->whereNotIn('url', ['fnf/rules', 'resigned-submission-reminder'])
            ->update(['order' => DB::raw('`order` + 2')]);
        foreach ([['F&F Rules', 'fnf/rules', 'ri-scales-3-line', 'fnf_rules', $order + 1, $fnf->permission_name],
            ['F&F Reminder Mails', 'resigned-submission-reminder', 'ri-mail-send-line', 'fnf_reminder_mails', $order + 2, 'Payment Section|Section Settings']] as [$title, $url, $icon, $key, $ord, $perm]) {
            $set = ['title' => $title, 'url' => $url, 'parent_id' => $fnf->parent_id, 'permission_name' => $perm, 'order' => $ord, 'icon' => $icon, 'status' => 1, 'updated_at' => now()];
            if (isset($cols['company_access_id'])) {
                $set['company_access_id'] = $fnf->company_access_id ?? '1,3,11';
            }
            $id = $db->table('menus')->where('url', $url)->where('parent_id', $fnf->parent_id)->value('id');
            $id ? $db->table('menus')->where('id', $id)->update($only($set)) : $db->table('menus')->insert($only($set + ['data_key' => $key, 'created_at' => now()]));
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // additive only
    }
};
