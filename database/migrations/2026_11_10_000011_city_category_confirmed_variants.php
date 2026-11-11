<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CITY-02 follow-up (confirmed 10 Nov 2026): Secunderabad is Hyderabad (A), "Dehi" is Delhi (A),
 * "Konpur" is Kanpur (B), "putnea" is Purnea, Bihar (B). Name kept, tier set; logged. Re-runnable.
 */
return new class extends Migration
{
    private const ROWS = [297 => ['Secunderabad', 'A'], 3273 => ['Dehi', 'A'], 2151 => ['Konpur', 'B'], 4162 => ['putnea', 'B']];

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        if (! Schema::connection($this->getConnection())->hasTable('city_category')) {
            return;
        }
        $logs = Schema::connection($this->getConnection())->hasTable('city_category_logs');
        foreach (self::ROWS as $ccid => [$name, $tier]) {
            $r = $db->table('city_category')->where('ccid', $ccid)->where('city_name', 'like', $name.'%')->where('city_category', '!=', $tier)->first();
            if (! $r) {
                continue;
            }
            $db->table('city_category')->where('ccid', $ccid)->update(['city_category' => $tier]);
            if ($logs) {
                $db->table('city_category_logs')->insert([
                    'ccid' => $ccid, 'action' => 'updated', 'city_name' => $r->city_name, 'city_category' => $tier,
                    'old_data' => json_encode(['city_name' => $r->city_name, 'city_category' => $r->city_category]),
                    'new_data' => json_encode(['city_name' => $r->city_name, 'city_category' => $tier]),
                    'changes_summary' => 'Approved city list: variant confirmed 10 Nov 2026 (CITY-02)',
                    'action_by' => 0, 'action_by_name' => 'migration 2026_11_10_000011', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};
