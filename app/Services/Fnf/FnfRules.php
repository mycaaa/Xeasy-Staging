<?php

namespace App\Services\Fnf;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FNF-RULES-01 (21 Nov 2026, "Decision for You" testing point 3b): the rules of the F&F expense working, kept with
 * effective dates and editable on Payment > F&F Rules. The working of an employee uses the rule values in force on
 * his last working day, so a change never alters workings of employees who left before it took effect. Until a
 * value is saved, each rule has the value the screen was built with (the defaults below), so nothing changes.
 */
class FnfRules
{
    /** key => [label, help, type (int|bool), default, min, max] */
    public const RULES = [
        'include_hold_months' => ['Rule 1 - Count months on payment hold', 'Months put on payment hold after resignation are added to the F&F amount.', 'bool', 1, 0, 1],
        'include_approved_unpaid' => ['Count months approved but not yet paid', 'Months approved for payment (not on hold, not paid) are counted at the approved amount.', 'bool', 1, 0, 1],
        'odo_free_days' => ['Rule A - Odometer days payable without courier date', 'In a month with up to this many odometer days, the odometer claims are eligible without the hard-copy courier date.', 'int', 6, 0, 31],
        'odo_needs_approval' => ['Rule B - More odometer days need the approver\'s approval', 'Above the Rule A days, odometer claims count only when the approver has approved them (1 = yes, 0 = no).', 'bool', 1, 0, 1],
        'hard_copy_wait_days' => ['Rule C - Days to wait for hard copies after the last working day', 'Other claims count only when the hard copy is received; they are shown as waiting for this many days after the last working day and then left out.', 'int', 3, 0, 60],
        'lookback_months' => ['Months before the last working day to look back', 'Unpaid months older than this before the last working day are not looked at.', 'int', 12, 1, 24],
    ];

    private static array $cache = [];

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('fnf_rules');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Value of a rule in force on a date (Y-m-d); the default when nothing was saved for that date. */
    public static function get(string $key, ?string $onDate = null)
    {
        $def = self::RULES[$key] ?? null;
        if (! $def) {
            return null;
        }
        $onDate = substr((string) ($onDate ?: now()->toDateString()), 0, 10);
        $ck = $key.'|'.$onDate;
        if (array_key_exists($ck, self::$cache)) {
            return self::$cache[$ck];
        }
        $v = $def[3];
        if (self::ready()) {
            $r = DB::table('fnf_rules')->where('rule_key', $key)->where('effective_from', '<=', $onDate)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $onDate))
                ->orderByDesc('effective_from')->orderByDesc('id')->first();
            if ($r) {
                $v = $r->value;
            }
        }

        return self::$cache[$ck] = $def[2] === 'bool' ? (bool) (int) $v : (int) $v;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** All rule values in force on a date, for the working ("rules used"). */
    public static function all(?string $onDate = null): array
    {
        $out = [];
        foreach (array_keys(self::RULES) as $k) {
            $out[$k] = self::get($k, $onDate);
        }

        return $out;
    }
}
