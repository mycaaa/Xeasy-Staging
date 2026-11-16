<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-01..09 (16 Nov 2026, sheet tab "BTC requirements"):
 *  - btc_cases: estimated amount, admin booking details, the separate advance figures (requested / approved /
 *    actually paid / TDS / adjusted / refund) and the Nova document link;
 *  - btc_invoices: invoices received from Nova (DMS), their Nova / import / match state;
 *  - btc_integration_logs: every Nova call or CSV import (success, failure, duplicates, retry);
 *  - btc_role_settings: entity / department scope and approval limit of each BTC role;
 *  - btc_access_logs: who changed which BTC role, permission or user assignment;
 *  - screen-wise permissions and the 11 recommended BTC roles; the BTC menu pages.
 * Idempotent: only adds what is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return; // the BTC tables come first (2026_11_05_000014)
        }
        $cols = [
            'estimated_amount' => fn (Blueprint $t) => $t->decimal('estimated_amount', 12, 2)->nullable()->after('quotation_amount'),
            'booking_status' => fn (Blueprint $t) => $t->string('booking_status', 20)->nullable(),     // requested | confirmed | modified | cancelled
            'booking_ref' => fn (Blueprint $t) => $t->string('booking_ref', 80)->nullable(),
            'booking_amount' => fn (Blueprint $t) => $t->decimal('booking_amount', 12, 2)->nullable(),
            'booking_date' => fn (Blueprint $t) => $t->date('booking_date')->nullable(),
            'booking_by' => fn (Blueprint $t) => $t->unsignedBigInteger('booking_by')->nullable(),
            'booking_remark' => fn (Blueprint $t) => $t->string('booking_remark', 500)->nullable(),
            'activity_completed_on' => fn (Blueprint $t) => $t->date('activity_completed_on')->nullable(),
            'advance_approved' => fn (Blueprint $t) => $t->decimal('advance_approved', 12, 2)->nullable(),
            'advance_paid_amount' => fn (Blueprint $t) => $t->decimal('advance_paid_amount', 12, 2)->nullable(),
            'advance_payment_ref' => fn (Blueprint $t) => $t->string('advance_payment_ref', 60)->nullable(),
            'advance_refund' => fn (Blueprint $t) => $t->decimal('advance_refund', 12, 2)->default(0),
            'advance_refund_on' => fn (Blueprint $t) => $t->date('advance_refund_on')->nullable(),
            'nova_document_id' => fn (Blueprint $t) => $t->string('nova_document_id', 80)->nullable()->index(),
        ];
        foreach ($cols as $name => $add) {
            if (! Schema::hasColumn('btc_cases', $name)) {
                Schema::table('btc_cases', $add);
            }
        }

        if (! Schema::hasTable('btc_invoices')) {
            Schema::create('btc_invoices', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('company_id')->default(1)->index();
                $t->string('source', 10)->default('nova');                 // nova (API) | csv | manual
                $t->string('nova_document_id', 80)->nullable()->unique();
                $t->string('invoice_no', 60)->nullable()->index();
                $t->date('invoice_date')->nullable();
                $t->string('vendor_name', 200)->nullable();
                $t->string('vendor_gstin', 20)->nullable()->index();
                $t->decimal('taxable_amount', 12, 2)->nullable();
                $t->decimal('tax_amount', 12, 2)->nullable();
                $t->decimal('gross_amount', 12, 2)->nullable();
                $t->string('document_url', 500)->nullable();
                $t->date('received_on')->nullable();                       // date Nova received the invoice
                $t->string('nova_status', 20)->default('transferred');     // received | digitised | transferred
                $t->string('import_status', 20)->default('imported');      // imported | failed
                $t->string('import_error', 500)->nullable();
                $t->unsignedBigInteger('btc_case_id')->nullable()->index();
                $t->string('match_status', 20)->default('unmatched');      // unmatched | suggested | matched | mismatch
                $t->unsignedTinyInteger('match_confidence')->nullable();
                $t->unsignedBigInteger('suggested_case_id')->nullable();
                $t->string('mismatch_note', 500)->nullable();
                $t->unsignedBigInteger('matched_by')->nullable();
                $t->timestamp('matched_at')->nullable();
                $t->json('payload')->nullable();
                $t->unsignedBigInteger('log_id')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('btc_integration_logs')) {
            Schema::create('btc_integration_logs', function (Blueprint $t) {
                $t->id();
                $t->string('channel', 20);                                 // nova_api | csv_import | retry
                $t->string('status', 20);                                  // success | partial | failure
                $t->unsignedInteger('received')->default(0);
                $t->unsignedInteger('imported')->default(0);
                $t->unsignedInteger('updated')->default(0);
                $t->unsignedInteger('duplicates')->default(0);
                $t->unsignedInteger('failed')->default(0);
                $t->string('error', 1000)->nullable();
                $t->longText('request_body')->nullable();
                $t->string('ip', 45)->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('btc_role_settings')) {
            Schema::create('btc_role_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('role_id')->unique();
                $t->string('description', 300)->nullable();
                $t->string('company_ids', 100)->nullable();               // "1,11"; empty = all companies
                $t->text('department_ids')->nullable();                     // "12,15"; empty = all departments
                $t->decimal('approval_limit', 12, 2)->nullable();           // empty = no limit
                $t->boolean('is_active')->default(true);
                $t->text('saved_permissions')->nullable();                  // permissions kept while the role is deactivated
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('btc_access_logs')) {
            Schema::create('btc_access_logs', function (Blueprint $t) {
                $t->id();
                $t->string('action', 40);                                  // role_created | role_updated | role_deactivated | user_assigned | user_revoked ...
                $t->unsignedBigInteger('role_id')->nullable();
                $t->string('role_name', 100)->nullable();
                $t->unsignedBigInteger('target_user_id')->nullable();
                $t->string('target_user', 150)->nullable();
                $t->text('details')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('actor_name', 150)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }

        $this->permissionsAndRoles();
        $this->menus();
    }

    private function permissionsAndRoles(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $groupId = Schema::hasTable('permission_groups') ? DB::table('permission_groups')->where('name', 'like', '%Finance%')->value('id') : null;
        $catalog = array_merge(...array_values(config('btc.permission_catalog')));
        $permIds = [];
        foreach ($catalog as $name => $label) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $id) {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                foreach (['permission_key' => 'BTC: '.$label, 'permission_group_id' => $groupId, 'status' => 1] as $c => $v) {
                    if (Schema::hasColumn('permissions', $c)) {
                        $row[$c] = $v;
                    }
                }
                $id = DB::table('permissions')->insertGetId($row);
            }
            $permIds[$name] = $id;
        }
        foreach (config('btc.roles') as $role => [$desc, $perms]) {
            $id = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');
            if (! $id) {
                $row = ['name' => $role, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('roles', 'status')) {
                    $row['status'] = 1;
                }
                $id = DB::table('roles')->insertGetId($row);
                foreach ($perms as $p) {
                    if (isset($permIds[$p])) {
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permIds[$p], 'role_id' => $id]);
                    }
                }
            }
            if (! DB::table('btc_role_settings')->where('role_id', $id)->exists()) {
                DB::table('btc_role_settings')->insert(['role_id' => $id, 'description' => $desc, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // the existing Admin role keeps full BTC control
        if ($admin = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id')) {
            foreach ($permIds as $pid) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $admin]);
            }
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    private function menus(): void
    {
        $conn = 'expense';
        $s = Schema::connection($conn);
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection($conn);
        $cols = array_flip($s->getColumnListing('menus'));
        $only = fn (array $r) => array_intersect_key($r, $cols);
        $group = $db->table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id');
        if (! $group) {
            return;
        }
        $items = [
            ['BTC Dashboard', 'btc/dashboard', 'btc.dashboard', 0, 'ri-dashboard-3-line'],
            ['BTC Request Register', 'btc/register', 'btc.register', 4, 'ri-list-check-2'],
            ['Admin Booking Queue', 'btc/booking', 'btc.booking', 5, 'ri-hotel-line'],
            ['Advance Request & Processing', 'btc/advances', 'btc.advance', 6, 'ri-hand-coin-line'],
            ['Nova Invoice Inbox', 'btc/invoices', 'btc.invoices', 7, 'ri-inbox-archive-line'],
            ['Integration Monitoring', 'btc/integration', 'btc.integration', 8, 'ri-pulse-line'],
            ['BTC Roles & Access', 'btc/access', 'btc.access', 9, 'ri-shield-user-line'],
        ];
        foreach ($items as [$title, $url, $perm, $order, $icon]) {
            $set = ['title' => $title, 'url' => $url, 'parent_id' => $group, 'permission_name' => $perm, 'order' => $order, 'icon' => $icon,
                'company_access_id' => '1,3,11', 'status' => 1, 'updated_at' => now()];
            $id = $db->table('menus')->whereIn('url', [$url, '/'.$url])->orderBy('id')->value('id');
            if ($id) {
                $db->table('menus')->where('id', $id)->update($only($set));
            } else {
                $db->table('menus')->insert($only($set + ['data_key' => str_replace('/', '-', $url), 'created_at' => now()]));
            }
        }
        // "BTC Cases" is the employee's "My BTC Requests" page
        $db->table('menus')->whereIn('url', ['btc', '/btc'])->where('parent_id', $group)->update($only(['title' => 'My BTC Requests', 'updated_at' => now()]));
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // kept: tables hold invoice, booking and access history
    }
};
