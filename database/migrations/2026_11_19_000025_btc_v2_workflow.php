<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-V2 (19 Nov 2026, BTC requirement sheet item 24): revised BTC workflow in Xeasy.
 *  - btc_cases: Finance data format (CGST / SGST / IGST, other charges, particulars, period), bill source and upload
 *    stamp, extraction result, DMS receipt / handover dates, Verifier 1 and Finance Approval stamps;
 *  - cases in old stages move to the new ones (DMS processed -> Verifier 1; checked / with Treasury -> Finance
 *    Approval; department approved -> Treasury payment);
 *  - permissions btc.bill_upload, btc.fin_approver and roles "BTC Bill Upload (Finance)", "BTC Finance Approver";
 *    Finance Head gets Finance Approval, Finance Verifier gets Bill Upload;
 *  - menus: Nova Invoice Inbox, Request / Invoice Matching, Integration Monitoring, Vendor Bank Accounts and Vendor
 *    boarding-pass links switched off; Invoice Reader -> Automatic Data Extraction; Payment Batches -> Treasury
 *    Payment; new Bill Upload by Finance, Finance Verifier 1, Finance Approval.
 * Idempotent and additive (old columns and tables are kept for history).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        foreach ([
            'cgst_amount' => fn (Blueprint $t) => $t->decimal('cgst_amount', 12, 2)->nullable(),
            'sgst_amount' => fn (Blueprint $t) => $t->decimal('sgst_amount', 12, 2)->nullable(),
            'igst_amount' => fn (Blueprint $t) => $t->decimal('igst_amount', 12, 2)->nullable(),
            'other_charges' => fn (Blueprint $t) => $t->decimal('other_charges', 12, 2)->nullable(),
            'bill_particulars' => fn (Blueprint $t) => $t->string('bill_particulars', 500)->nullable(),
            'bill_period' => fn (Blueprint $t) => $t->string('bill_period', 100)->nullable(),
            'bill_source' => fn (Blueprint $t) => $t->string('bill_source', 20)->nullable(),
            'bill_uploaded_by' => fn (Blueprint $t) => $t->unsignedBigInteger('bill_uploaded_by')->nullable(),
            'bill_uploaded_at' => fn (Blueprint $t) => $t->dateTime('bill_uploaded_at')->nullable(),
            'extract_status' => fn (Blueprint $t) => $t->string('extract_status', 20)->nullable(),   // pending | read | partial | failed | corrected
            'extract_note' => fn (Blueprint $t) => $t->string('extract_note', 300)->nullable(),
            'extract_json' => fn (Blueprint $t) => $t->text('extract_json')->nullable(),
            'dms_received_on' => fn (Blueprint $t) => $t->date('dms_received_on')->nullable(),
            'dms_to_finance_on' => fn (Blueprint $t) => $t->date('dms_to_finance_on')->nullable(),
            'verify1_by' => fn (Blueprint $t) => $t->unsignedBigInteger('verify1_by')->nullable(),
            'verify1_at' => fn (Blueprint $t) => $t->dateTime('verify1_at')->nullable(),
            'verify1_remark' => fn (Blueprint $t) => $t->string('verify1_remark', 500)->nullable(),
            'fin_approved_by' => fn (Blueprint $t) => $t->unsignedBigInteger('fin_approved_by')->nullable(),
            'fin_approved_at' => fn (Blueprint $t) => $t->dateTime('fin_approved_at')->nullable(),
            'fin_approve_remark' => fn (Blueprint $t) => $t->string('fin_approve_remark', 500)->nullable(),
        ] as $c => $def) {
            if (! Schema::hasColumn('btc_cases', $c)) {
                Schema::table('btc_cases', fn (Blueprint $t) => $def($t));
            }
        }

        // cases in old stages continue in the revised workflow
        foreach (['dms_processed' => 'extracted', 'checked' => 'focus_done', 'with_treasury' => 'focus_done', 'dept_approved' => 'fin_approved'] as $from => $to) {
            foreach (DB::table('btc_cases')->where('status', $from)->pluck('id') as $id) {
                DB::table('btc_cases')->where('id', $id)->update(['status' => $to, 'updated_at' => now()]);
                DB::table('btc_case_logs')->insert(['btc_case_id' => $id, 'action' => 'workflow_v2', 'from_status' => $from, 'to_status' => $to,
                    'remark' => 'Moved to the revised BTC workflow (19 Nov 2026)', 'user_name' => 'System', 'created_at' => now()]);
            }
        }
        if (Schema::hasTable('btc_payments')) {   // no Treasury checker any more: an entry still waiting is applied by Treasury again
            DB::table('btc_payments')->where('status', 'pending')->update(['status' => 'rejected', 'failed_reason' => 'Treasury checker step removed (19 Nov 2026) - enter the payment again', 'updated_at' => now()]);
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
        $ids = [];
        foreach (array_merge(...array_values(config('btc.permission_catalog'))) as $name => $label) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $id) {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                foreach (['permission_key' => 'BTC: '.$label, 'permission_group_id' => $groupId, 'status' => 1] as $c => $v) {
                    if (Schema::hasColumn('permissions', $c)) {
                        $row[$c] = $v;
                    }
                }
                $id = DB::table('permissions')->insertGetId($row);
            } elseif (Schema::hasColumn('permissions', 'permission_key')) {
                DB::table('permissions')->where('id', $id)->update(['permission_key' => 'BTC: '.$label]);
            }
            $ids[$name] = $id;
        }
        foreach (config('btc.roles') as $role => [$desc, $perms]) {
            $rid = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');
            $new = ! $rid;
            if ($new) {
                $row = ['name' => $role, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('roles', 'status')) {
                    $row['status'] = 1;
                }
                $rid = DB::table('roles')->insertGetId($row);
            }
            // a new role gets its permissions; an existing role only the two new ones (the rest stay as the Super Admin set them)
            foreach ($perms as $p) {
                if (isset($ids[$p]) && ($new || in_array($p, ['btc.bill_upload', 'btc.fin_approver'], true))) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $ids[$p], 'role_id' => $rid]);
                }
            }
            if (Schema::hasTable('btc_role_settings')) {
                DB::table('btc_role_settings')->where('role_id', $rid)->exists()
                    ? DB::table('btc_role_settings')->where('role_id', $rid)->update(['description' => $desc, 'updated_at' => now()])
                    : DB::table('btc_role_settings')->insert(['role_id' => $rid, 'description' => $desc, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if ($admin = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id')) {
            foreach (['btc.bill_upload', 'btc.fin_approver'] as $p) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $ids[$p], 'role_id' => $admin]);
            }
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
        // removed screens: switched off (kept for history)
        $db->table('menus')->where('parent_id', $group)->whereIn('url', ['btc/invoices', '/btc/invoices', 'btc/matching', '/btc/matching', 'btc/integration', '/btc/integration',
            'btc/vendor-banks', '/btc/vendor-banks', 'btc/vendor-links', '/btc/vendor-links'])->update($only(['status' => 0, 'updated_at' => now()]));
        $items = [
            ['Bill Upload by Finance', 'btc/bill-upload', 'btc.bill_upload', 6, 'ri-upload-cloud-2-line', ['btc/invoices']],
            ['Automatic Data Extraction', 'btc/extraction', 'btc.bill_upload', 7, 'ri-scan-2-line', ['btc/invoice-reader']],
            ['Finance Verifier 1', 'btc/verifier', 'btc.verifier', 8, 'ri-checkbox-multiple-line', []],
            ['Finance Approval', 'btc/approval', 'btc.fin_approver', 9, 'ri-shield-check-line', []],
            ['Treasury Payment', 'btc/treasury', 'btc.treasury', 10, 'ri-bank-line', ['btc/payment-batches']],
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
