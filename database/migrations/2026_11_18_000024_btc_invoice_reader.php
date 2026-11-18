<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-READER-01 (18 Nov 2026): invoices are read in Xeasy itself (upload -> fields read -> link to the request) instead of
 * waiting for Nova. btc_invoices keeps the uploaded file and how the reading went; menu "Invoice Reader".
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('btc_invoices')) {
            foreach ([
                'file_path' => fn (Blueprint $t) => $t->string('file_path', 300)->nullable(),
                'file_name' => fn (Blueprint $t) => $t->string('file_name', 255)->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category', 20)->nullable(),
                'extract_status' => fn (Blueprint $t) => $t->string('extract_status', 20)->nullable(),   // read | partial | failed
                'extract_error' => fn (Blueprint $t) => $t->string('extract_error', 300)->nullable(),
                'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
            ] as $c => $def) {
                if (! Schema::hasColumn('btc_invoices', $c)) {
                    Schema::table('btc_invoices', fn (Blueprint $t) => $def($t));
                }
            }
        }
        $s = Schema::connection('expense');
        if ($s->hasTable('menus')) {
            $db = DB::connection('expense');
            $cols = array_flip($s->getColumnListing('menus'));
            $only = fn (array $r) => array_intersect_key($r, $cols);
            if ($group = $db->table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id')) {
                $url = 'btc/invoice-reader';
                $set = ['title' => 'Invoice Reader', 'url' => $url, 'parent_id' => $group, 'permission_name' => 'btc.dms', 'order' => 6, 'icon' => 'ri-scan-2-line',
                    'company_access_id' => '1,3,11', 'status' => 1, 'updated_at' => now()];
                $mid = $db->table('menus')->whereIn('url', [$url, '/'.$url])->value('id');
                $mid ? $db->table('menus')->where('id', $mid)->update($only($set)) : $db->table('menus')->insert($only($set + ['data_key' => 'btc-invoice-reader', 'created_at' => now()]));
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
