<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAGE-CLEAN-03 (15 Nov 2026): working pages that had no menu item (Page cleanup tab, rows 29-48) get one, under
 * the menu they belong to, with the same permission the page itself needs (config/route_access.php or the
 * route's permission middleware) so nobody sees an item that answers "no permission". Detail / print pages
 * that open with an id stay without a menu item. Appended at the end of each parent menu. Safe to run again:
 * an item whose address is already in the menu is only re-activated.
 */
return new class extends Migration
{
    private string $conn = 'expense';

    /** parent data_key => [title, url, icon, permission, data_key] */
    private array $items = [
        'claim-reports' => [
            ['Expense Mismatch', 'admin/expense-mismatch-report', 'ri-error-warning-line', 'section_reports', 'expense-mismatch-report'],
            ['Claim Action Tracking', 'reports/claim-action-tracking', 'ri-route-line', 'section_reports', 'claim-action-tracking'],
            ['Claims Verification', 'reports/claims-verification', 'ri-checkbox-multiple-line', 'section_verifier', 'claims-verification-report'],
        ],
        'expense-reports' => [
            ['Hotel Price Variation', 'admin/hotel-price-variation-report', 'ri-hotel-line', 'section_reports', 'hotel-price-variation'],
            ['Advance Policy Check', 'advance-policy-check', 'ri-shield-check-line', 'section_reports', 'advance-policy-check'],
            ['Bill Extraction', 'reports/extract', 'ri-file-search-line', 'section_reports', 'bill-extraction'],
        ],
        'vehicle-reports' => [
            ['Vehicle Rate Gaps', 'vehicle-rate-gaps', 'ri-car-line', 'section_reports', 'vehicle-rate-gaps'],
        ],
        'verifier' => [
            ['Month Report', 'verifier/month-report', 'ri-calendar-check-line', 'section_verifier', 'verifier-month-report'],
        ],
        'settings' => [
            ['Submission Settings', 'admin/submission-settings', 'ri-settings-4-line', 'seection_settings', 'submission-settings'],
        ],
        'utilities' => [
            ['File Storage (S3)', 'admin/s3-transfer', 'ri-folder-cloud-line', 'section_utilities', 's3-transfer'],
        ],
        'payment' => [
            ['Upload Payment File', 'payments/import', 'ri-upload-cloud-line', 'payment_section', 'payments-import'],
            ['Records Retention', 'records-retention', 'ri-archive-line', 'payment_section', 'records-retention'],
        ],
    ];

    public function up(): void
    {
        $s = Schema::connection($this->conn);
        if (! $s->hasTable('menus')) {
            return;
        }
        $db = DB::connection($this->conn);
        $cols = array_flip($s->getColumnListing('menus'));
        $only = fn (array $r) => array_intersect_key($r, $cols);

        foreach ($this->items as $parentKey => $rows) {
            $parent = $db->table('menus')->where('data_key', $parentKey)->orderBy('id')->value('id');
            if (! $parent) {
                continue;
            }
            foreach ($rows as [$title, $url, $icon, $perm, $key]) {
                $existing = $db->table('menus')->whereIn('url', [$url, '/'.$url])->orderBy('id')->value('id');
                if ($existing) {
                    $db->table('menus')->where('id', $existing)->update($only(['status' => 1, 'updated_at' => now()]));
                    continue;
                }
                $order = (int) $db->table('menus')->where('parent_id', $parent)->max('order') + 1;
                $db->table('menus')->insert($only([
                    'title' => $title, 'url' => $url, 'icon' => $icon, 'parent_id' => $parent,
                    'permission_name' => $perm, 'company_access_id' => '1,3,11', 'order' => $order,
                    'data_key' => $key, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]));
            }
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('menu_permission_map');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // menu rows are left in place
    }
};
