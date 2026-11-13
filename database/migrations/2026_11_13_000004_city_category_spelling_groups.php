<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CITY-03 (13 Nov 2026): misspelled city names are grouped under the correct city instead of showing as separate
 * entries in the city drop-downs. A grouped row keeps its id and name (old claims still point at it) and gets
 * alias_of = the correct city's ccid; drop-downs list only rows with alias_of empty.
 *
 * Grouped here:
 *  1. names that differ only in case, spaces or punctuation ("Lucknow`", "Kolkata ", "Gomti Nagar"/"Gomtinagar"),
 *     when every row of the group has the same tier;
 *  2. a reviewed list of plain misspellings of policy (A / B) cities. Look-alike names of DIFFERENT cities
 *     (Raipur / Jaipur, Satna / Patna, Amravati / Amaravati ...) are deliberately not grouped.
 * Anything else is grouped from Settings > City Categories ("Same city as"). Every change is logged.
 * Re-runnable; rows are matched by ccid and name.
 */
return new class extends Migration
{
    /** variant ccid => [variant name as held, correct ccid, correct name] */
    private const REVIEWED = [
        2627 => ['Ahemdabad', 2, 'Ahmedabad'], 1633 => ['Amdavad', 2, 'Ahmedabad'],
        533 => ['Vishakapatnam', 7, 'Visakhapatnam'], 3269 => ['Visakhhapatnam', 7, 'Visakhapatnam'],
        3839 => ['Vishakhapatnam', 7, 'Visakhapatnam'], 4127 => ['Vishkapatnam', 7, 'Visakhapatnam'],
        2096 => ['Hydearabad', 8, 'Hyderabad'],
        2906 => ['Bangalpre', 19, 'Bangalore'], 3098 => ['Bengalore', 19, 'Bangalore'], 3678 => ['Banglore', 19, 'Bangalore'],
        2538 => ['THIRUVANANTHAPUR', 20, 'Thiruvananthapuram'],
        317 => ['LUCKNOQ', 33, 'Lucknow'],
        3663 => ['Mysoreq', 41, 'Mysore'],
        2151 => ['Konpur', 50, 'Kanpur'],
        982 => ['Gaziabad', 54, 'Ghaziabad'],
        438 => ['Silliguri', 57, 'Siliguri'],
        58 => ['Hazaribag', 258, 'Hazaribagh'],
        2541 => ['Bhgalpur', 60, 'Bhagalpur'],
        61 => ['Muzzarpur', 2539, 'Muzzafarpur'],
        137 => ['Karnool', 64, 'Kurnool'],
        431 => ['NAshik', 68, 'Nasik'],
        1635 => ['purniA', 71, 'Purnea'], 4162 => ['putnea', 71, 'Purnea'],
        2499 => ['Prayajraj', 209, 'Prayagraj'],
        249 => ['Bareilley', 1297, 'bareilly'],
        671 => ['Gunture', 3978, 'Guntur'],
        2022 => ['jambhaji Nagar', 682, 'Sambhajinagar'], 4095 => ['Sambhanjinagar', 682, 'Sambhajinagar'],
        2209 => ['Chatrapati Sambhanjinagar', 1216, 'Chhatrapati Sambhaji Nagar'],
        3273 => ['Dehi', 3, 'Delhi'],
        810 => ['banaras', 51, 'Varanasi'],
    ];

    public function up(): void
    {
        $conn = $this->getConnection();
        $schema = Schema::connection($conn);
        if (! $schema->hasTable('city_category')) {
            return;
        }
        if (! $schema->hasColumn('city_category', 'alias_of')) {
            $schema->table('city_category', function (Blueprint $t) {
                $t->unsignedInteger('alias_of')->nullable()->index();
            });
        }
        $db = DB::connection($conn);
        $rows = $db->table('city_category')->get(['ccid', 'city_name', 'city_category', 'alias_of'])->keyBy('ccid');
        $logs = $schema->hasTable('city_category_logs');
        $done = 0;

        $group = function (int $variant, int $target, string $why) use ($db, &$rows, $logs, &$done) {
            $v = $rows[$variant] ?? null;
            $t = $rows[$target] ?? null;
            if (! $v || ! $t || $variant === $target || $v->alias_of || $t->alias_of) {
                return;
            }
            $db->table('city_category')->where('ccid', $variant)->update(['alias_of' => $target]);
            $rows[$variant]->alias_of = $target;
            $done++;
            if ($logs) {
                $db->table('city_category_logs')->insert([
                    'ccid' => $variant, 'action' => 'updated', 'city_name' => $v->city_name, 'city_category' => $v->city_category,
                    'old_data' => json_encode(['alias_of' => null]),
                    'new_data' => json_encode(['alias_of' => $target, 'same_city_as' => $t->city_name]),
                    'changes_summary' => "Grouped under '{$t->city_name}' (#{$target}): {$why} (CITY-03)",
                    'action_by' => 0, 'action_by_name' => 'migration 2026_11_13_000004', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        };

        // 1. same name apart from case / spaces / punctuation, same tier
        $norm = fn ($s) => preg_replace('/[^a-z]/', '', mb_strtolower((string) $s));
        $clean = fn ($s) => (bool) preg_match('/^[A-Za-z][A-Za-z .]*[A-Za-z]$/', trim((string) $s)) && trim((string) $s) === (string) $s && ! str_contains((string) $s, '  ');
        foreach ($rows->groupBy(fn ($r) => $norm($r->city_name)) as $key => $g) {
            if ($key === '' || $g->count() < 2 || $g->pluck('city_category')->map(fn ($c) => strtoupper(trim((string) $c)))->unique()->count() > 1) {
                continue;
            }
            $keep = $g->sortBy('ccid')->first(fn ($r) => $clean($r->city_name)) ?? $g->sortBy('ccid')->first();
            foreach ($g as $r) {
                if ($r->ccid !== $keep->ccid) {
                    $group((int) $r->ccid, (int) $keep->ccid, 'same name apart from case / spaces / punctuation');
                }
            }
        }

        // 2. reviewed misspellings
        foreach (self::REVIEWED as $variant => [$vName, $target, $tName]) {
            $v = $rows[$variant] ?? null;
            $t = $rows[$target] ?? null;
            if ($v && $t && $norm($v->city_name) === $norm($vName) && $norm($t->city_name) === $norm($tName)) {
                // the correct city may itself be a grouped row (case variant): point at its group head
                $head = $t->alias_of ? (int) $t->alias_of : (int) $t->ccid;
                $group((int) $variant, $head, 'misspelling of '.$tName);
            }
        }

        echo "  city_category: {$done} misspelled / duplicate rows grouped\n";
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());
        if ($schema->hasTable('city_category') && $schema->hasColumn('city_category', 'alias_of')) {
            $schema->table('city_category', fn (Blueprint $t) => $t->dropColumn('alias_of'));
        }
    }
};
