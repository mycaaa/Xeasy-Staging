<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-SCAN-01 (19 Nov 2026, tracker tab "Data Punching Form & Finance approval screens"):
 *  - btc_cases: punch form of the bill type (punch_json), Finance level verification field checks (verify1_checks),
 *    Finance Advance Approval stage pending -> approved -> instructed (adv_*);
 *  - menus: Scanner, Classification, Punch Screen (was Bill Upload by Finance / Automatic Data Extraction), Finance Level
 *    Verification (was Finance Verifier 1), Finance Advance Approval.
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('btc_cases')) {
            foreach ([
                'punch_json' => fn (Blueprint $t) => $t->text('punch_json')->nullable(),
                'verify1_checks' => fn (Blueprint $t) => $t->text('verify1_checks')->nullable(),
                'adv_stage' => fn (Blueprint $t) => $t->string('adv_stage', 20)->nullable(),          // null/pending | approved | instructed | rejected
                'adv_fin_by' => fn (Blueprint $t) => $t->unsignedBigInteger('adv_fin_by')->nullable(),
                'adv_fin_at' => fn (Blueprint $t) => $t->dateTime('adv_fin_at')->nullable(),
                'adv_fin_remark' => fn (Blueprint $t) => $t->string('adv_fin_remark', 500)->nullable(),
                'adv_instructed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('adv_instructed_by')->nullable(),
                'adv_instructed_at' => fn (Blueprint $t) => $t->dateTime('adv_instructed_at')->nullable(),
                'adv_instruction_ref' => fn (Blueprint $t) => $t->string('adv_instruction_ref', 60)->nullable(),
            ] as $c => $def) {
                if (! Schema::hasColumn('btc_cases', $c)) {
                    Schema::table('btc_cases', fn (Blueprint $t) => $def($t));
                }
            }
        }
        $this->menus();
    }

    private function menus(): void
    {
        $s = Schema::connection('expense');
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection('expense');
        $cols = array_flip($s->getColumnListing('menus'));
        $only = fn (array $r) => array_intersect_key($r, $cols);
        $group = $db->table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id');
        if (! $group) {
            return;
        }
        $items = [
            ['Scanner', 'btc/scanner', 'btc.dms', 6, 'ri-scan-line', ['btc/bill-upload']],
            ['Classification', 'btc/classification', 'btc.dms', 7, 'ri-price-tag-3-line', []],
            ['Punch Screen', 'btc/punch', 'btc.bill_upload', 8, 'ri-keyboard-box-line', ['btc/extraction', 'btc/invoice-reader']],
            ['Finance Level Verification', 'btc/verifier', 'btc.verifier', 9, 'ri-checkbox-multiple-line', []],
            ['Finance Approval', 'btc/approval', 'btc.fin_approver', 10, 'ri-shield-check-line', []],
            ['Finance Advance Approval', 'btc/advance-approval', 'btc.fin_approver', 11, 'ri-hand-coin-line', []],
            ['Treasury Payment', 'btc/treasury', 'btc.treasury', 12, 'ri-bank-line', ['btc/payment-batches']],
        ];
        foreach ($items as [$title, $url, $perm, $order, $icon, $old]) {
            $set = ['title' => $title, 'url' => $url, 'parent_id' => $group, 'permission_name' => $perm, 'order' => $order, 'icon' => $icon,
                'company_access_id' => '1,3,11', 'status' => 1, 'updated_at' => now()];
            $urls = array_merge([$url, '/'.$url], $old, array_map(fn ($u) => '/'.$u, $old));
            $id = $db->table('menus')->whereIn('url', $urls)->orderByRaw('url = ? desc', [$url])->orderBy('id')->value('id');
            $id ? $db->table('menus')->where('id', $id)->update($only($set)) : $db->table('menus')->insert($only($set + ['data_key' => 'btc-'.basename($url), 'created_at' => now()]));
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
