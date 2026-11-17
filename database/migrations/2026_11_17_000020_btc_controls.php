<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-CTRL-01 (17 Nov 2026, "BTC requirements" items 4, 5, 7, 11, 12):
 *  - btc_cases: accounting dimensions (function, vertical, region, activity, crop), narration, JV no. and TDS JV
 *    no., and the Finance maker the case is assigned to;
 *  - btc_payments: who authorised a payment entry (treasury maker / checker);
 *  - btc_exception_reviews: an exception marked reviewed, with the remark;
 *  - btc_settings / btc_setting_logs: BTC settings changed on the BTC Settings page;
 *  - permissions btc.exceptions, btc.audit, btc.export, btc.configure, btc.treasury_checker; roles "BTC Treasury
 *    Checker" and "BTC Internal Audit"; menus Exception Queue and BTC Settings.
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        $cols = [
            'dim_function' => fn (Blueprint $t) => $t->string('dim_function', 80)->nullable(),
            'dim_vertical' => fn (Blueprint $t) => $t->string('dim_vertical', 80)->nullable(),
            'dim_region' => fn (Blueprint $t) => $t->string('dim_region', 80)->nullable(),
            'dim_activity' => fn (Blueprint $t) => $t->string('dim_activity', 120)->nullable(),
            'dim_crop' => fn (Blueprint $t) => $t->string('dim_crop', 80)->nullable(),
            'narration' => fn (Blueprint $t) => $t->string('narration', 500)->nullable(),
            'jv_no' => fn (Blueprint $t) => $t->string('jv_no', 40)->nullable(),
            'tds_jv_no' => fn (Blueprint $t) => $t->string('tds_jv_no', 40)->nullable(),
            'assigned_maker_id' => fn (Blueprint $t) => $t->unsignedBigInteger('assigned_maker_id')->nullable()->index(),
        ];
        foreach ($cols as $c => $def) {
            if (! Schema::hasColumn('btc_cases', $c)) {
                Schema::table('btc_cases', fn (Blueprint $t) => $def($t));
            }
        }
        if (Schema::hasTable('btc_payments')) {
            foreach (['authorised_by' => fn (Blueprint $t) => $t->unsignedBigInteger('authorised_by')->nullable(),
                'authorised_at' => fn (Blueprint $t) => $t->dateTime('authorised_at')->nullable()] as $c => $def) {
                if (! Schema::hasColumn('btc_payments', $c)) {
                    Schema::table('btc_payments', fn (Blueprint $t) => $def($t));
                }
            }
        }
        if (! Schema::hasTable('btc_exception_reviews')) {
            Schema::create('btc_exception_reviews', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('btc_case_id');
                $t->string('type', 30);
                $t->string('remark', 500);
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->string('reviewer_name', 150)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['btc_case_id', 'type']);
            });
        }
        if (! Schema::hasTable('btc_settings')) {
            Schema::create('btc_settings', function (Blueprint $t) {
                $t->string('key', 80)->primary();
                $t->text('value')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        if (! Schema::hasTable('btc_setting_logs')) {
            Schema::create('btc_setting_logs', function (Blueprint $t) {
                $t->id();
                $t->string('key', 80);
                $t->text('old_value')->nullable();
                $t->text('new_value')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('user_name', 150)->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        $this->permissions();
        $this->menus();
    }

    private function permissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $groupId = Schema::hasTable('permission_groups') ? DB::table('permission_groups')->where('name', 'like', '%Finance%')->value('id') : null;
        $new = [
            'btc.exceptions' => 'Validation & Exception Queue',
            'btc.audit' => 'Internal Audit: read every BTC record and report (no changes)',
            'btc.export' => 'Download BTC reports (Excel)',
            'btc.configure' => 'BTC settings and masters',
            'btc.treasury_checker' => 'Treasury checker: authorise payments entered by another person',
        ];
        $id = [];
        foreach (array_merge(['btc.register', 'btc.finance', 'btc.checker', 'btc.verifier', 'btc.dashboard', 'btc.admin', 'btc.access'], array_keys($new)) as $name) {
            $pid = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $pid && isset($new[$name])) {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                foreach (['permission_key' => 'BTC: '.$new[$name], 'permission_group_id' => $groupId, 'status' => 1] as $c => $v) {
                    if (Schema::hasColumn('permissions', $c)) {
                        $row[$c] = $v;
                    }
                }
                $pid = DB::table('permissions')->insertGetId($row);
            }
            $id[$name] = $pid;
        }
        $give = function (int $roleId, array $perms) use ($id) {
            foreach ($perms as $p) {
                if (! empty($id[$p])) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id[$p], 'role_id' => $roleId]);
                }
            }
        };
        $rolesWith = fn (string $perm) => empty($id[$perm]) ? [] : DB::table('role_has_permissions')->where('permission_id', $id[$perm])->pluck('role_id')->all();
        // existing behaviour kept: whoever downloads the register today keeps the download; Finance desks get the queue
        foreach ($rolesWith('btc.register') as $r) {
            $give($r, ['btc.export']);
        }
        foreach (array_unique(array_merge($rolesWith('btc.finance'), $rolesWith('btc.checker'), $rolesWith('btc.verifier'))) as $r) {
            $give($r, ['btc.exceptions']);
        }
        foreach (array_unique(array_merge($rolesWith('btc.admin'), $rolesWith('btc.access'))) as $r) {
            $give($r, array_keys($new));
        }
        $roles = [
            'BTC Treasury Checker' => ['Payment authorisation (not own entries)', ['btc.treasury_checker', 'btc.register']],
            'BTC Internal Audit' => ['Read-only BTC records and reports', ['btc.audit', 'btc.register', 'btc.dashboard', 'btc.export', 'btc.exceptions']],
        ];
        foreach ($roles as $name => [$desc, $perms]) {
            $rid = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $rid) {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('roles', 'status')) {
                    $row['status'] = 1;
                }
                $rid = DB::table('roles')->insertGetId($row);
                $give($rid, $perms);
            }
            if (Schema::hasTable('btc_role_settings') && ! DB::table('btc_role_settings')->where('role_id', $rid)->exists()) {
                DB::table('btc_role_settings')->insert(['role_id' => $rid, 'description' => $desc, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if (Schema::hasTable('btc_role_settings') && ($pu = DB::table('roles')->where('name', 'BTC Payment User')->value('id'))) {
            DB::table('btc_role_settings')->where('role_id', $pu)->update(['description' => 'Treasury maker: payment entry, UTR (a Treasury Checker authorises)', 'updated_at' => now()]);
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
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
        foreach ([['Exception Queue', 'btc/exceptions', 'btc.exceptions', 8, 'ri-error-warning-line'], ['BTC Settings', 'btc/settings', 'btc.configure', 10, 'ri-settings-3-line']] as [$title, $url, $perm, $order, $icon]) {
            $set = ['title' => $title, 'url' => $url, 'parent_id' => $group, 'permission_name' => $perm, 'order' => $order, 'icon' => $icon,
                'company_access_id' => '1,3,11', 'status' => 1, 'updated_at' => now()];
            $mid = $db->table('menus')->whereIn('url', [$url, '/'.$url])->value('id');
            $mid ? $db->table('menus')->where('id', $mid)->update($only($set)) : $db->table('menus')->insert($only($set + ['data_key' => str_replace('/', '-', $url), 'created_at' => now()]));
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
