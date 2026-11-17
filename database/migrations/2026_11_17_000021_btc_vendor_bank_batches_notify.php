<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-BANK-01 / BTC-BATCH-01 / BTC-NOTIFY-01 (17 Nov 2026):
 *  - btc_vendor_banks: a vendor's bank account for BTC payments, entered by one person and verified by another;
 *    the account number is stored encrypted, only the last 4 digits in clear;
 *  - btc_payment_batches + btc_payments.batch_id: the bank bulk-payment file and the bank's response (UTR) import;
 *  - btc_notifications: every hand-off / escalation mail (to whom, when, error);
 *  - menus: Vendor bank accounts, Payment batches.
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('btc_cases')) {
            return;
        }
        if (! Schema::hasTable('btc_vendor_banks')) {
            Schema::create('btc_vendor_banks', function (Blueprint $t) {
                $t->id();
                $t->unsignedTinyInteger('company_id')->default(1);
                $t->unsignedBigInteger('vendor_id')->nullable()->index();
                $t->string('vendor_name', 200);
                $t->string('vendor_gstin', 20)->nullable()->index();
                $t->string('account_name', 150);
                $t->text('account_no_enc');
                $t->string('account_last4', 4);
                $t->string('ifsc', 11);
                $t->string('bank_name', 120)->nullable();
                $t->string('status', 12)->default('pending');    // pending | verified | rejected | replaced
                $t->string('remark', 300)->nullable();
                $t->unsignedBigInteger('entered_by')->nullable();
                $t->string('entered_by_name', 150)->nullable();
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->string('verified_by_name', 150)->nullable();
                $t->dateTime('verified_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('btc_payment_batches')) {
            Schema::create('btc_payment_batches', function (Blueprint $t) {
                $t->id();
                $t->unsignedTinyInteger('company_id')->default(1);
                $t->string('batch_no', 30)->unique();
                $t->unsignedInteger('cases')->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->string('status', 20)->default('file_made');  // file_made | response_imported
                $t->unsignedBigInteger('created_by')->nullable();
                $t->string('created_by_name', 150)->nullable();
                $t->dateTime('response_at')->nullable();
                $t->string('response_summary', 300)->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('btc_payments')) {
            foreach (['batch_id' => fn (Blueprint $t) => $t->unsignedBigInteger('batch_id')->nullable()->index(),
                'bank_txn_ref' => fn (Blueprint $t) => $t->string('bank_txn_ref', 40)->nullable()->index()] as $c => $def) {
                if (! Schema::hasColumn('btc_payments', $c)) {
                    Schema::table('btc_payments', fn (Blueprint $t) => $def($t));
                }
            }
        }
        if (! Schema::hasTable('btc_notifications')) {
            Schema::create('btc_notifications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('btc_case_id')->index();
                $t->string('kind', 12);                          // handoff | escalation
                $t->string('stage', 30);
                $t->string('to_email', 150)->nullable();
                $t->string('to_name', 150)->nullable();
                $t->string('subject', 250)->nullable();
                $t->string('error', 300)->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        $s = Schema::connection('expense');
        if ($s->hasTable('menus')) {
            $db = DB::connection('expense');
            $cols = array_flip($s->getColumnListing('menus'));
            $only = fn (array $r) => array_intersect_key($r, $cols);
            if ($group = $db->table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id')) {
                foreach ([['Payment Batches', 'btc/payment-batches', 'btc.treasury', 7, 'ri-bank-line'], ['Vendor Bank Accounts', 'btc/vendor-banks', 'btc.treasury', 7, 'ri-bank-card-line']] as [$title, $url, $perm, $order, $icon]) {
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
        }
    }

    public function down(): void
    {
    }
};
