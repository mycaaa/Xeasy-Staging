<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CITY-01: aligns the city master (city_category) with Travel Policy 8.2, Table 2 (city
 * categories), for the cities the audit found held in the wrong tier (POL-04).
 * Lodging claims take their tier from this master from now on, so this sets the tier
 * for FUTURE claims; past claims are not changed.
 *
 * Every change is written to city_category_logs. Dry run by default.
 *
 *   php artisan city-tiers:apply-policy                 # show what would change
 *   php artisan city-tiers:apply-policy --apply         # change the master
 *   php artisan city-tiers:apply-policy --connection=expense_vvnr --apply
 */
class ApplyPolicyCityTiers extends Command
{
    protected $signature = 'city-tiers:apply-policy {--connection= : expense DB connection (default: expense)} {--apply : write the changes} {--full : every city not in policy A or B becomes C}';

    protected $description = 'Set city tiers in city_category per Travel Policy Table 2 (cities found wrong by the audit)';

    /**
     * Travel Policy Table 2 (https://vnrdev.in/HR_Mannual/travel-policy/, read 5 Nov 2026).
     * Canonical name => other spellings found or likely in the master (lower-case).
     * Union territories are held under the UT name (decision 10 Nov 2026), with their towns as
     * aliases. Every city not in A or B is C. The approved list itself is applied by migration
     * 2026_11_10_000010 (client's city_category_update_query); this command only checks/aligns.
     */
    private const POLICY = [
        'A' => [
            'Hyderabad' => ['secunderabad', 'hydearabad'], 'Mumbai' => ['bombay', 'navi mumbai'], 'Ahmedabad' => ['ahmadabad', 'ahemdabad', 'amdavad'],
            'Bangalore' => ['bengaluru', 'banglore', 'bengalore'], 'Pune' => ['poona'], 'Chennai' => ['madras'],
            'Delhi' => ['new delhi'], 'Gurgaon' => ['gurugram'], 'Indore' => [], 'Amaravati' => [],
            'Itanagar' => [], 'Dispur' => [], 'Patna' => [], 'Raipur' => [], 'Panaji' => ['panjim'],
            'Gandhinagar' => [], 'Chandigarh' => [], 'Shimla' => [], 'Ranchi' => [],
            'Thiruvananthapuram' => ['trivandrum'], 'Bhopal' => [], 'Imphal' => [], 'Shillong' => [],
            'Aizawl' => ['aizawal', 'aizwal'], 'Kohima' => [], 'Bhubaneswar' => ['bhubaneshwar'], 'Jaipur' => [],
            'Gangtok' => [], 'Agartala' => [], 'Lucknow' => ['lucknow`'], 'Dehradun' => ['dehra dun'],
            'Kolkata' => ['kolkatta', 'calcutta'], 'Vijayawada' => ['vijaywada'],
            'Visakhapatnam' => ['vishakhapatnam', 'vizag', 'vishakapatnam', 'visakhhapatnam', 'vishkapatnam'],
            'Andaman and Nicobar' => ['port blair'], 'Lakshadweep' => ['kavaratti'],
            'Puducherry' => ['pondicherry'], 'Guwahati' => ['gauhati'],
            'Jammu and Kashmir' => ['jammu', 'srinagar'], 'Ladakh' => ['leh', 'kargil'],
            'Dadra Nagar Haveli Daman Diu' => ['silvassa', 'daman', 'nani daman', 'diu', 'haveli'],
        ],
        'B' => [
            'Rajkot' => [], 'Surat' => [], 'Vadodara' => ['vadodora', 'baroda'], 'Faridabad' => [],
            'Mysore' => ['mysuru'], 'Nagpur' => [], 'Gwalior' => [],
            'Sambhaji Nagar' => ['chhatrapati sambhaji nagar', 'sambhajinagar', 'aurangabad'],
            'Ludhiana' => [], 'Coimbatore' => [], 'Ooty' => ['udhagamandalam'], 'Allahabad' => ['prayagraj'],
            'Kanpur' => [], 'Varanasi' => ['banaras', 'benares'], 'Noida' => ['greater noida'], 'Agra' => [],
            'Ghaziabad' => ['gaziabad'], 'Nainital' => [], 'Darjeeling' => [], 'Siliguri' => ['silliguri'], 'Bokaro' => ['bokaro steel city'],
            'Hazaribag' => ['hazaribagh', 'bokaro, hazaribag'], 'Jamshedpur' => [], 'Bhagalpur' => ['bhgalpur'],
            'Muzaffarpur' => ['muzzafarpur', 'muzzarpur'], 'Bilaspur' => ['bilas-pur'], 'Guntur' => ['guntoor', 'gunture'],
            'Kurnool' => ['karnool'], 'Warangal' => [], 'Akola' => [], 'Nashik' => ['nasik'], 'Dhanbad' => ['dhan-bad'],
            'Tatanagar' => [], 'Purnea' => ['purnia'], 'Gaya' => [], 'Puri' => [], 'Rourkela' => [],
            'Sambalpur' => [], 'Amritsar' => [], 'Bareilly' => ['barielly', 'bareilley'], 'Gorakhpur' => [],
        ],
    ];

    public function handle(): int
    {
        $conn = $this->option('connection') ?: 'expense';
        $apply = (bool) $this->option('apply');
        $db = DB::connection($conn);
        if (! Schema::connection($conn)->hasTable('city_category')) {
            $this->error("No city_category table on {$conn}");
            return self::FAILURE;
        }
        $hasLogs = Schema::connection($conn)->hasTable('city_category_logs');

        $rows = $db->table('city_category')->get(['ccid', 'city_name', 'city_category']);
        $byName = [];
        foreach ($rows as $r) {
            $byName[mb_strtolower(trim((string) $r->city_name))][] = $r;
        }

        $changes = [];
        $policyTier = [];   // lower-case name => tier
        foreach (self::POLICY as $tier => $cities) {
            foreach ($cities as $canonical => $aliases) {
                foreach (array_merge([mb_strtolower($canonical)], $aliases) as $n) {
                    $policyTier[$n] = $tier;
                }
            }
        }
        $full = (bool) $this->option('full');
        foreach ($byName as $name => $list) {
            $tier = $policyTier[$name] ?? ($full ? 'C' : null);
            if ($tier === null) {
                continue;
            }
            foreach ($list as $r) {
                $trimmed = trim((string) $r->city_name);
                if ($r->city_category !== $tier || $trimmed !== $r->city_name) {
                    $changes[] = ['update', $r->ccid, $r->city_name, $trimmed, $r->city_category, $tier];
                }
            }
        }
        foreach (self::POLICY as $tier => $cities) {
            foreach ($cities as $canonical => $aliases) {
                $present = false;
                foreach (array_merge([mb_strtolower($canonical)], $aliases) as $n) {
                    $present = $present || ! empty($byName[$n]);
                }
                if (! $present) {
                    $changes[] = ['add', null, null, $canonical, null, $tier];
                }
            }
        }

        if ($changes === []) {
            $this->info("{$conn}: city master already matches the policy list.");
            return self::SUCCESS;
        }
        $this->table(['Action', 'ccid', 'City (as held)', 'City (new)', 'Old tier', 'Policy tier'], $changes);
        if (! $apply) {
            $this->warn('Dry run: nothing changed. Re-run with --apply.');
            return self::SUCCESS;
        }

        $db->transaction(function () use ($db, $changes, $hasLogs) {
            foreach ($changes as [$action, $ccid, $oldName, $newName, $oldTier, $tier]) {
                if ($action === 'update') {
                    $db->table('city_category')->where('ccid', $ccid)->update(['city_name' => $newName, 'city_category' => $tier]);
                } else {
                    $ccid = $db->table('city_category')->insertGetId(['city_name' => $newName, 'city_category' => $tier, 'crby' => 0]);
                }
                if ($hasLogs) {
                    $db->table('city_category_logs')->insert([
                        'ccid' => $ccid,
                        'action' => $action === 'update' ? 'updated' : 'created',
                        'city_name' => $newName,
                        'city_category' => $tier,
                        'old_data' => json_encode(['city_name' => $oldName, 'city_category' => $oldTier]),
                        'new_data' => json_encode(['city_name' => $newName, 'city_category' => $tier]),
                        'changes_summary' => 'Travel Policy Table 2 alignment (audit CITY-01 / POL-04'.($this->option('full') ? ', full classification' : '').')',
                        'action_by' => 0,
                        'action_by_name' => 'city-tiers:apply-policy',
                        'ip_address' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
        $this->info(count($changes).' change(s) written on '.$conn.'.');

        return self::SUCCESS;
    }
}
