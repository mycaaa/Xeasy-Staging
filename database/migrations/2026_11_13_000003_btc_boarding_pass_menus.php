<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BOARDING-02 / MENU-01 (13 Nov 2026): a "Bill To Company" group in the side menu for everyone (the screens
 * themselves limit what each person sees), holding BTC cases, Boarding Pass (company-booked air tickets)
 * and, for Finance, the vendor boarding-pass links. The BTC entry used to sit under Payment only, so most
 * users (and the quick menu search) never saw it; on a database without it, it is created here.
 * Also puts Dashboard first in the menu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }
        $cols = array_flip(Schema::getColumnListing('menus'));
        $row = fn (array $r) => array_intersect_key($r + ['company_access_id' => '1,3,11', 'status' => 1, 'created_at' => now(), 'updated_at' => now()], $cols);

        $groupId = DB::table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id');
        if (! $groupId) {
            $groupId = DB::table('menus')->insertGetId($row([
                'title' => 'Bill To Company', 'url' => '#', 'icon' => 'ri-bill-line', 'parent_id' => null,
                'permission_name' => null, 'order' => 9, 'data_key' => 'bill-to-company-group',
            ]));
        }

        $put = function (string $title, string $url, ?string $perm, int $order, string $icon) use ($groupId, $row) {
            $id = DB::table('menus')->where('url', $url)->value('id');
            if ($id) {
                DB::table('menus')->where('id', $id)->update(['title' => $title, 'parent_id' => $groupId, 'permission_name' => $perm, 'order' => $order, 'icon' => $icon, 'status' => 1, 'updated_at' => now()]);
            } else {
                DB::table('menus')->insert($row(['title' => $title, 'url' => $url, 'icon' => $icon, 'parent_id' => $groupId,
                    'permission_name' => $perm, 'order' => $order, 'data_key' => $url]));
            }
        };
        $put('BTC Cases', 'btc', null, 1, 'ri-file-list-3-line');
        $put('Boarding Pass', 'btc/boarding-passes', null, 2, 'ri-plane-line');
        $put('Vendor Boarding-Pass Links', 'btc/vendor-links', 'btc.finance', 3, 'ri-links-line');

        // Dashboard first; Treasury next to Payment
        DB::table('menus')->where('url', 'home')->whereNull('parent_id')->update(['order' => 0]);
        DB::table('menus')->where('title', 'Treasury')->whereNull('parent_id')->where('order', 0)->update(['order' => 12]);
    }

    public function down(): void
    {
        $groupId = DB::table('menus')->where('title', 'Bill To Company')->whereNull('parent_id')->value('id');
        $payment = DB::table('menus')->where('title', 'Payment')->whereNull('parent_id')->value('id');
        DB::table('menus')->where('url', 'btc')->update(['title' => 'Bill To Company (BTC)', 'parent_id' => $payment, 'order' => 40]);
        DB::table('menus')->whereIn('url', ['btc/boarding-passes', 'btc/vendor-links'])->delete();
        if ($groupId) {
            DB::table('menus')->where('id', $groupId)->delete();
        }
        DB::table('menus')->where('url', 'home')->whereNull('parent_id')->update(['order' => 5]);
        DB::table('menus')->where('title', 'Treasury')->whereNull('parent_id')->where('order', 12)->update(['order' => 0]);
    }
};
