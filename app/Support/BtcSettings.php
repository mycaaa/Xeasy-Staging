<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-CONF-01 (17 Nov 2026, BTC requirement items 4/5 "Configure" / "Master Management"): BTC settings that Finance
 * may change without a program change. Saved in btc_settings (key -> value) and laid over config('btc') when the
 * app boots. Every change is logged in btc_setting_logs. Only users with btc.configure (or BTC admin) may save.
 */
class BtcSettings
{
    public const TABLE = 'btc_settings';

    /** key (under config btc.) => [label, type, group, help] */
    public static function fields(): array
    {
        $f = [
            'advance_settle_days' => ['Advance must be settled by a bill within (days)', 'int', 'Controls', 'After this a new advance for the same employee is refused'],
            'follow_up_days' => ['Chase missing original / boarding pass after (days)', 'int', 'Controls', ''],
            'mismatch_pct' => ['Bill above the approved / booked amount by more than (%)', 'int', 'Controls', 'Flagged as request mismatch in the Exception Queue'],
            'focus.employee_btc_ledger' => ['Employee BTC ledger name pattern', 'text', 'Focus ledgers', '{first_name} = employee first name'],
            'focus.tds_ledger' => ['TDS ledger', 'text', 'Focus ledgers', ''],
            'focus.gst_input_ledger' => ['GST input ledger', 'text', 'Focus ledgers', ''],
            'focus.vendor_advance_ledger' => ['Vendor advance ledger', 'text', 'Focus ledgers', ''],
        ];
        // BTC-FLOW-01 / BTC-READER-01 (18 Nov 2026)
        $f['boarding_pass_before_verify'] = ['Air: boarding pass needed before the Focus entry', 'bool', 'Controls', 'Off = Finance goes on; the boarding pass is chased and still needed to close'];
        $f['reader.enabled'] = ['Read uploaded invoices automatically', 'bool', 'Data extraction', 'Uses the same bill-reading service as the claim screens'];
        $f['reader.public_base'] = ['Internet address of Xeasy for the reader (if different)', 'text', 'Data extraction', 'e.g. https://xeasy.vnrseeds.co.in - leave empty normally'];
        // BTC-V2 (19 Nov 2026): the Finance data format - which read fields must be filled before Finance Verifier 1
        $f['extraction.company_state'] = ['Company GSTIN state code (same state = CGST + SGST, else IGST)', 'text', 'Data extraction', 'e.g. 22 for Chhattisgarh'];
        foreach (config('btc.extraction.fields', []) as $k => $label) {
            $f["extraction.must.{$k}"] = ['Required before Verifier 1: '.$label, 'bool', 'Data extraction', ''];
        }
        $f['notify.enabled'] = ['Send hand-off and overdue mails', 'bool', 'Mails', 'To whoever acts next, and daily for stages past their time limit'];
        $f['notify.redirect_to'] = ['Test mode: send every mail to this address instead', 'text', 'Mails', 'Leave empty to mail the real people'];
        foreach (\App\Services\Btc\BtcNotifier::STAGES as $k => [$who, $days]) {
            $f["sla.{$k}"] = ['Days allowed: '.$who, 'int', 'Time limits (escalation)', 'Default '.$days];
        }
        foreach (config('btc.categories', []) as $k => $c) {
            $f["focus.expense_ledgers.{$k}"] = ['Expense ledger: '.$c['label'], 'text', 'Focus ledgers', ''];
            $f["categories.{$k}.scan_ok"] = ['Focus entry on a scan: '.$c['label'], 'bool', 'Controls', 'Off = the original bill must be in before the Focus entry (it is always needed to close)'];
            $f["tds_nature_by_category.{$k}"] = ['Default TDS nature: '.$c['label'], 'nature', 'TDS', ''];
        }

        return $f;
    }

    public static function load(): void
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return;
            }
            $fields = self::fields();
            foreach (DB::table(self::TABLE)->pluck('value', 'key') as $k => $v) {
                if (isset($fields[$k])) {
                    config(['btc.'.$k => self::cast($v, $fields[$k][1])]);
                }
            }
        } catch (\Throwable $e) {
            // table not reachable: keep the file defaults
        }
    }

    public static function cast($v, string $type)
    {
        return match ($type) {
            'bool' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
            'int' => max(0, (int) $v),
            'nature' => array_key_exists((string) $v, config('btc.tds_natures', [])) ? (string) $v : 'none',
            default => trim((string) $v),
        };
    }

    public static function save(array $input, $user): array
    {
        $changed = [];
        foreach (self::fields() as $k => [$label, $type]) {
            $form = str_replace('.', '__', $k);
            if ($type !== 'bool' && ! array_key_exists($form, $input)) {
                continue;
            }
            $raw = $type === 'bool' ? (! empty($input[$form]) ? '1' : '0') : (string) $input[$form];
            $new = (string) (is_bool($c = self::cast($raw, $type)) ? (int) $c : $c);
            $old = config('btc.'.$k);
            $oldS = is_bool($old) ? (string) (int) $old : (string) $old;
            if ($new === $oldS || ($type === 'text' && $new === '' && ! in_array($k, ['notify.redirect_to', 'reader.public_base'], true))) {
                continue;
            }
            DB::table(self::TABLE)->updateOrInsert(['key' => $k], ['value' => $new, 'updated_by' => $user->id ?? null, 'updated_at' => now()]);
            DB::table('btc_setting_logs')->insert(['key' => $k, 'old_value' => $oldS, 'new_value' => $new, 'user_id' => $user->id ?? null,
                'user_name' => $user->name ?? null, 'created_at' => now()]);
            config(['btc.'.$k => self::cast($new, $type)]);
            $changed[] = $label;
        }

        return $changed;
    }
}
