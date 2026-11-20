<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAGE-CLEAN-04 (20 Nov 2026, tracker tab "Page cleanup" rows 88-92, decisions "Remove old" / "Remove any one"):
 *  - 88 old Bill to Company and 89 old Finance - Advance Requests: menu items (if any) switched off; the addresses
 *    redirect to BTC / Advance Approvals (routes/backend.php);
 *  - 90 one "Email Logs" item: Sent Mail Logs renamed Email Logs, New Email Logs switched off (both logs are tabs);
 *  - 91 one "Claims" item: the items opening /claims merged into the first, shown for any of their permissions
 *    ("a|b", MenuHelper);
 *  - 92 the settings item "Advance Request" (/advance-request-master) renamed "Advance Request Rules" (it is a
 *    different page from the employee's Advance Request, so it is renamed, not removed).
 * Idempotent; every change is logged in the migration output only, rows are switched off (status 0), never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('expense');
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection('expense');
        $url = fn (array $u) => array_merge($u, array_map(fn ($x) => '/'.ltrim($x, '/'), $u), array_map(fn ($x) => ltrim($x, '/'), $u));
        $hasUpd = $s->hasColumn('menus', 'updated_at');
        $set = fn (array $r) => $hasUpd ? $r + ['updated_at' => now()] : $r;

        // 88 / 89
        $db->table('menus')->whereIn('url', $url(['bill-to-company', 'bill-to-company/categories', 'advance/finance']))->update($set(['status' => 0]));

        // 90
        $db->table('menus')->whereIn('url', $url(['admin/mail-logs']))->update($set(['title' => 'Email Logs']));
        $db->table('menus')->whereIn('url', $url(['email-send-log']))->update($set(['status' => 0]));

        // 91
        $claims = $db->table('menus')->whereIn('url', $url(['claims']))->where('status', 1)->orderBy('id')->get(['id', 'permission_name']);
        if ($claims->count() > 1) {
            $perms = $claims->pluck('permission_name')->flatMap(fn ($p) => explode('|', (string) $p))->map(fn ($p) => trim($p))->filter()->unique()->values();
            $keep = $claims->first()->id;
            $db->table('menus')->where('id', $keep)->update($set(['title' => 'Claims', 'permission_name' => $perms->isEmpty() ? null : $perms->implode('|')]));
            $db->table('menus')->whereIn('id', $claims->pluck('id')->reject(fn ($id) => $id === $keep)->all())->update($set(['status' => 0]));
        }

        // 92
        $db->table('menus')->whereIn('url', $url(['advance-request-master']))->update($set(['title' => 'Advance Request Rules']));

        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
