<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLAIM-SCORE-01..03 (16 Nov 2026): claim score settings + change log, monthly score snapshot, Finance & Forensics
 * case reviews; permissions finance.forensics and claim-score.settings; menus Claim Score (My Claim Score, My Team),
 * Payment > Finance & Forensics, Settings > Claim Score Settings; Duplicate Entries moves under Payment.
 * Idempotent: only adds what is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('expense');
        if (! $s->hasTable('claim_score_settings')) {
            $s->create('claim_score_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('company_id')->unique();
                $t->text('settings');
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
            });
        }
        if (! $s->hasTable('claim_score_setting_logs')) {
            $s->create('claim_score_setting_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('company_id')->index();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->text('changes')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! $s->hasTable('claim_scores')) {
            $s->create('claim_scores', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('company_id');
                $t->unsignedSmallInteger('year_id');
                $t->unsignedTinyInteger('month');
                $t->unsignedBigInteger('employee_id');
                $t->string('emp_code', 30)->nullable();
                $t->string('employee_name', 150)->nullable();
                $t->string('department', 120)->nullable();
                $t->string('grade', 20)->nullable();
                $t->unsignedBigInteger('manager_id')->nullable()->index();
                $t->unsignedInteger('claims')->default(0);
                $t->unsignedSmallInteger('score')->default(0);
                $t->string('level', 30)->nullable();
                $t->text('pillars')->nullable();
                $t->text('marks')->nullable();
                $t->text('figures')->nullable();
                $t->unsignedInteger('dept_rank')->nullable();
                $t->unsignedInteger('dept_of')->nullable();
                $t->unsignedInteger('company_rank')->nullable();
                $t->unsignedInteger('company_of')->nullable();
                $t->unsignedTinyInteger('streak')->default(0);
                $t->boolean('provisional')->default(false);
                $t->timestamp('calculated_at')->nullable();
                $t->unique(['company_id', 'year_id', 'month', 'employee_id'], 'claim_scores_unique');
                $t->index(['company_id', 'year_id', 'employee_id']);
            });
        }
        if (! $s->hasTable('fin_case_reviews')) {
            $s->create('fin_case_reviews', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('company_id');
                $t->unsignedSmallInteger('year_id');
                $t->string('check_key', 40);
                $t->string('status', 20)->default('open');
                $t->string('remark', 500)->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'year_id', 'check_key']);
            });
        }
        $this->permissions();
        $this->menus();
    }

    private function permissions(): void
    {
        $db = DB::connection();
        if (! Schema::hasTable('permissions')) {
            return;
        }
        $ids = [];
        foreach (['finance.forensics' => 'Claim Score: Finance & Forensics board', 'claim-score.settings' => 'Claim Score: settings'] as $name => $label) {
            $id = $db->table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $id) {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                foreach (['permission_key' => $label, 'status' => 1] as $c => $v) {
                    if (Schema::hasColumn('permissions', $c)) {
                        $row[$c] = $v;
                    }
                }
                $id = $db->table('permissions')->insertGetId($row);
            }
            $ids[$name] = $id;
        }
        // Finance board: roles that already hold the Finance or Payment Section permission, and Admin
        $finRoles = $db->table('role_has_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereIn('p.name', ['Finance', 'Payment Section'])->pluck('rp.role_id')->all();
        $adminRoles = $db->table('roles')->whereIn('name', ['Admin', 'Super Admin'])->pluck('id')->all();
        foreach (array_unique(array_merge($finRoles, $adminRoles)) as $rid) {
            $db->table('role_has_permissions')->insertOrIgnore(['permission_id' => $ids['finance.forensics'], 'role_id' => $rid]);
        }
        foreach ($adminRoles as $rid) {
            $db->table('role_has_permissions')->insertOrIgnore(['permission_id' => $ids['claim-score.settings'], 'role_id' => $rid]);
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
        $put = function (string $title, string $url, ?int $parent, string $perm, int $order, string $icon) use ($db, $only) {
            $set = ['title' => $title, 'url' => $url, 'parent_id' => $parent, 'permission_name' => $perm, 'order' => $order, 'icon' => $icon,
                'company_access_id' => '1,3,11', 'status' => 1, 'updated_at' => now()];
            $id = $db->table('menus')->whereIn('url', [$url, '/'.$url])->orderBy('id')->value('id');
            if ($id) {
                $db->table('menus')->where('id', $id)->update($only($set));

                return $id;
            }

            return $db->table('menus')->insertGetId($only($set + ['data_key' => str_replace('/', '-', $url), 'created_at' => now()]));
        };
        $dashOrder = (int) ($db->table('menus')->where('url', 'dashboard')->whereNull('parent_id')->value('order') ?? 0);
        $group = $db->table('menus')->where('title', 'Claim Score')->whereNull('parent_id')->value('id');
        if (! $group) {
            $group = $db->table('menus')->insertGetId($only(['title' => 'Claim Score', 'url' => '#', 'parent_id' => null, 'permission_name' => '', 'order' => $dashOrder,
                'icon' => 'ri-trophy-line', 'company_access_id' => '1,3,11', 'status' => 1, 'data_key' => 'claim-score-group', 'created_at' => now(), 'updated_at' => now()]));
        }
        $put('My Claim Score', 'claim-score', $group, '', 1, 'ri-medal-line');
        $put('My Team', 'claim-score/team', $group, '', 2, 'ri-team-line');
        if ($pay = $db->table('menus')->where('title', 'Payment')->whereNull('parent_id')->value('id')) {
            $put('Finance & Forensics', 'claim-score/finance', $pay, 'finance.forensics', 0, 'ri-shield-check-line');
            // Duplicate Entries is a finance check, not a setting
            $db->table('menus')->whereIn('url', ['admin/duplicate-expense', '/admin/duplicate-expense'])->update($only(['parent_id' => $pay, 'title' => 'Duplicate Bills', 'order' => 1, 'updated_at' => now()]));
        }
        if ($set = $db->table('menus')->where('title', 'Settings')->whereNull('parent_id')->value('id')) {
            $after = (int) ($db->table('menus')->whereIn('url', ['admin/claim-rating', '/admin/claim-rating'])->value('order') ?? 0);
            $put('Claim Score Settings', 'claim-score/settings', $set, 'claim-score.settings', $after, 'ri-equalizer-line');
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // kept: score history and case reviews
    }
};
