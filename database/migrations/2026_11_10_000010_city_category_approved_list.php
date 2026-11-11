<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POL-04 / CITY-02 (tracker "You" point 4, answered 10 Nov 2026: missing union territories by UT name).
 *
 *  1. The client's database/sql/city_category_update_query.sql exactly as written (spelling fixes,
 *     Jammu -> "Jammu and Kashmir" A, variants moved to A/B, UT rows to A, missing A entries added
 *     under the UT name: Andaman and Nicobar, Lakshadweep, Puducherry, Ladakh,
 *     Dadra Nagar Haveli Daman Diu, plus Amaravati).
 *  2. Further misspellings of policy cities found in the list (still in C) moved to their tier;
 *     the name is kept, as the client's query does for its own variant rows.
 *  3. Every changed or added row is written to city_category_logs.
 * Rows are matched by ccid and current value, so the migration can run again. Expense (VNR) only.
 */
return new class extends Migration
{
    /** ccid => [name as held, tier] - misspellings of A/B cities not in the client's query */
    private const VARIANTS = [
        317 => ['LUCKNOQ', 'A'], 1633 => ['Amdavad', 'A'], 2096 => ['Hydearabad', 'A'], 2538 => ['THIRUVANANTHAPUR', 'A'],
        2627 => ['Ahemdabad', 'A'], 2906 => ['Bangalpre', 'A'], 3098 => ['Bengalore', 'A'], 3269 => ['Visakhhapatnam', 'A'],
        3678 => ['Banglore', 'A'], 4127 => ['Vishkapatnam', 'A'],
        810 => ['banaras', 'B'], 982 => ['Gaziabad', 'B'], 1635 => ['purniA', 'B'], 2209 => ['Chatrapati Sambhanjinagar', 'B'],
        2499 => ['Prayajraj', 'B'], 2541 => ['Bhgalpur', 'B'], 3663 => ['Mysoreq', 'B'], 4095 => ['Sambhanjinagar', 'B'],
    ];

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        if (! Schema::connection($this->getConnection())->hasTable('city_category')) {
            return;
        }
        $before = $db->table('city_category')->get(['ccid', 'city_name', 'city_category'])->keyBy('ccid');

        $sql = (string) file_get_contents(database_path('sql/city_category_update_query.sql'));
        $sql = preg_replace('/^\s*(START TRANSACTION|COMMIT)\s*;\s*$/mi', '', $sql);
        $db->transaction(function () use ($db, $sql) {
            $db->unprepared($sql);
            foreach (self::VARIANTS as $ccid => [$name, $tier]) {
                $db->table('city_category')->where('ccid', $ccid)->where('city_name', 'like', $name.'%')
                    ->where('city_category', 'C')->update(['city_category' => $tier]);
            }
        });

        if (! Schema::connection($this->getConnection())->hasTable('city_category_logs')) {
            return;
        }
        $after = $db->table('city_category')->get(['ccid', 'city_name', 'city_category']);
        foreach ($after as $r) {
            $old = $before[$r->ccid] ?? null;
            if ($old && $old->city_name === $r->city_name && $old->city_category === $r->city_category) {
                continue;
            }
            $db->table('city_category_logs')->insert([
                'ccid' => $r->ccid, 'action' => $old ? 'updated' : 'created', 'city_name' => $r->city_name, 'city_category' => $r->city_category,
                'old_data' => json_encode(['city_name' => $old->city_name ?? null, 'city_category' => $old->city_category ?? null]),
                'new_data' => json_encode(['city_name' => $r->city_name, 'city_category' => $r->city_category]),
                'changes_summary' => isset(self::VARIANTS[$r->ccid]) ? 'Approved city list: spelling variant moved to its tier (CITY-02)' : 'Approved city list (city_category_update_query, CITY-02)',
                'action_by' => 0, 'action_by_name' => 'migration 2026_11_10_000010', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
    }
};
