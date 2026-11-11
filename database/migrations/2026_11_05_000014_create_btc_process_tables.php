<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-01: Bill To Company process (config/btc.php).
 *  btc_cases       one row per BTC requirement / bill, all companies (company_id)
 *  btc_case_files  quotation, bill, supporting, original, boarding pass, payment proof
 *  btc_case_logs   every stage change with who / when / remark
 *  permissions     btc.dms, btc.finance, btc.checker, btc.treasury, btc.admin
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            Schema::create('btc_cases', function (Blueprint $t) {
                $t->id();
                $t->string('case_no', 30)->unique();
                $t->unsignedTinyInteger('company_id')->default(1);
                $t->string('route', 20);                       // admin_booking | employee_advance | direct_bill
                $t->string('category', 20);                    // hotel | vehicle | air | train | meeting | other
                $t->unsignedBigInteger('employee_id');
                $t->string('emp_code', 20)->nullable();
                $t->string('emp_name', 150)->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->string('department_name', 150)->nullable();
                $t->string('purpose', 500);
                $t->string('location', 150)->nullable();
                $t->date('service_from')->nullable();
                $t->date('service_to')->nullable();
                $t->unsignedBigInteger('vendor_id')->nullable();
                $t->string('vendor_name', 200)->nullable();
                $t->string('vendor_gstin', 20)->nullable();
                $t->boolean('new_vendor')->default(false);
                $t->decimal('quotation_amount', 12, 2)->nullable();
                // manager approval (route B)
                $t->unsignedBigInteger('manager_id')->nullable();
                $t->timestamp('manager_action_at')->nullable();
                $t->string('manager_remark', 500)->nullable();
                // advance (route B; links to the Xeasy advance when raised there)
                $t->decimal('advance_amount', 12, 2)->default(0);
                $t->unsignedBigInteger('advance_activity_id')->nullable();
                $t->date('advance_paid_on')->nullable();
                $t->decimal('tds_on_advance', 12, 2)->default(0);
                // bill
                $t->string('bill_no', 60)->nullable();
                $t->date('bill_date')->nullable();
                $t->decimal('taxable_amount', 12, 2)->nullable();
                $t->decimal('gst_amount', 12, 2)->default(0);
                $t->decimal('bill_amount', 12, 2)->nullable();
                $t->unsignedBigInteger('bill_submitted_by')->nullable();
                $t->timestamp('bill_submitted_at')->nullable();
                $t->string('submitted_via', 20)->nullable();   // admin | employee | vendor | dms
                // DMS
                $t->unsignedBigInteger('dms_by')->nullable();
                $t->timestamp('dms_at')->nullable();
                $t->string('dms_doc_no', 60)->nullable();
                $t->boolean('original_required')->default(true);
                $t->date('original_received_on')->nullable();
                // air travel
                $t->string('pnr', 20)->nullable();
                $t->string('travel_sector', 120)->nullable();
                $t->date('travel_date')->nullable();
                $t->boolean('boarding_pass_required')->default(false);
                $t->date('boarding_pass_received_on')->nullable();
                // Finance verification & JV
                $t->string('expense_ledger', 120)->nullable();
                $t->string('cost_centre', 120)->nullable();
                $t->string('tds_section', 10)->nullable();
                $t->decimal('tds_rate', 5, 2)->default(0);
                $t->decimal('tds_amount', 12, 2)->default(0);
                $t->decimal('advance_adjusted', 12, 2)->default(0);
                $t->decimal('net_payable', 12, 2)->nullable();
                $t->boolean('duplicate_checked')->default(false);
                $t->unsignedBigInteger('finance_by')->nullable();
                $t->timestamp('finance_at')->nullable();
                $t->string('finance_remark', 500)->nullable();
                $t->string('focus_voucher_no', 60)->nullable();
                $t->timestamp('focus_exported_at')->nullable();
                // checker, Treasury, department approval, payment
                $t->unsignedBigInteger('checker_by')->nullable();
                $t->timestamp('checker_at')->nullable();
                $t->unsignedBigInteger('treasury_by')->nullable();
                $t->timestamp('treasury_at')->nullable();
                $t->unsignedBigInteger('dept_approver_id')->nullable();
                $t->timestamp('dept_approved_at')->nullable();
                $t->string('dept_remark', 500)->nullable();
                $t->decimal('paid_amount', 12, 2)->nullable();
                $t->date('paid_on')->nullable();
                $t->string('utr_number', 40)->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->string('status', 30)->default('draft')->index();
                $t->string('return_to', 30)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['company_id', 'status']);
                $t->index(['employee_id']);
                $t->index(['vendor_id', 'bill_no']);
            });
        }
        if (! Schema::hasTable('btc_case_files')) {
            Schema::create('btc_case_files', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('btc_case_id')->index();
                $t->string('kind', 30);   // quotation | approval | bill | supporting | original | boarding_pass | payment_proof
                $t->string('file_path', 500);
                $t->string('file_name', 255);
                $t->string('mime', 80)->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('btc_case_logs')) {
            Schema::create('btc_case_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('btc_case_id')->index();
                $t->string('action', 40);
                $t->string('from_status', 30)->nullable();
                $t->string('to_status', 30)->nullable();
                $t->string('remark', 1000)->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('user_name', 150)->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $groupId = DB::table('permission_groups')->where('name', 'like', '%Finance%')->value('id');
        $map = [
            'btc.dms' => 'BTC: DMS processing',
            'btc.finance' => 'BTC: Finance verification and JV',
            'btc.checker' => 'BTC: Finance checker',
            'btc.treasury' => 'BTC: Treasury and payment',
            'btc.admin' => 'BTC: Admin booking and all cases',
        ];
        $adminRole = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id');
        foreach ($map as $name => $label) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $id) {
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                foreach (['permission_key' => $label, 'permission_group_id' => $groupId, 'status' => 1] as $c => $v) {
                    if (Schema::hasColumn('permissions', $c)) {
                        $row[$c] = $v;
                    }
                }
                $id = DB::table('permissions')->insertGetId($row);
            }
            if ($adminRole) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $adminRole]);
            }
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        if (Schema::hasTable('menus') && ! DB::table('menus')->where('url', 'btc')->exists()) {
            $parent = DB::table('menus')->where('title', 'Payment')->whereNull('parent_id')->value('id');
            DB::table('menus')->insert([
                'title' => 'Bill To Company (BTC)', 'url' => 'btc', 'icon' => 'ri-bill-line', 'parent_id' => $parent,
                'permission_name' => null, 'company_access_id' => '1,3,11', 'order' => 40, 'data_key' => 'btc', 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('btc_case_logs');
        Schema::dropIfExists('btc_case_files');
        Schema::dropIfExists('btc_cases');
    }
};
