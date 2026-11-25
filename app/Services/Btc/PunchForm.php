<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use RuntimeException;

/**
 * BTC-PUNCH-02 (25 Nov 2026, testing point 17): the punch form of a bill type in the layout of the 19 Nov spec -
 * header fields, then repeatable tables (journey legs, rooms, line items) with their totals.
 *
 * punch_json keeps the header fields by their key (as before), the tables under "_t" and the Focus inputs of the
 * Punch Screen under "_mode" / "_narration_remark". Older punches (flat Air / Rail / Bus fields) open as one leg.
 */
class PunchForm
{
    public static function key(BtcCase $c): string
    {
        return (string) config('btc.punch_form_of.'.$c->category, 'meeting');
    }

    public static function def(BtcCase $c): ?array
    {
        return config('btc.punch_forms.'.self::key($c));
    }

    public static function saved(BtcCase $c): ?array
    {
        $s = json_decode((string) ($c->punch_json ?? ''), true);

        return is_array($s) ? $s : null;
    }

    public static function raw(BtcCase $c): array
    {
        return (array) (json_decode((string) ($c->extract_json ?? ''), true)['raw'] ?? []);
    }

    private static function norm(string $k): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($k));
    }

    /** A value of the reader's output by any of its spellings (case / spaces / punctuation ignored). */
    private static function pick(array $raw, array $keys)
    {
        $idx = [];
        foreach ($raw as $k => $v) {
            $idx[self::norm((string) $k)] = $v;
        }
        foreach ($keys as $k) {
            $v = $idx[self::norm($k)] ?? null;
            if (is_scalar($v) && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return null;
    }

    /** Rows of a table from the reader's output: the first list of records sharing at least two columns. */
    private static function rowsFromRaw(array $raw, array $cols): array
    {
        $want = [];
        foreach ($cols as $k => $l) {
            $want[self::norm($k)] = $k;
            $want[self::norm($l)] = $k;
        }
        foreach ($raw as $v) {
            if (! is_array($v) || ! $v || ! array_is_list($v) || ! is_array($v[0] ?? null)) {
                continue;
            }
            $hits = count(array_intersect(array_map(fn ($k) => self::norm((string) $k), array_keys($v[0])), array_keys($want)));
            if ($hits < 2) {
                continue;
            }
            $rows = [];
            foreach (array_slice($v, 0, 50) as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $row = array_fill_keys(array_keys($cols), '');
                foreach ($r as $k => $x) {
                    $col = $want[self::norm((string) $k)] ?? null;
                    if ($col && is_scalar($x)) {
                        $row[$col] = trim((string) $x);
                    }
                }
                $rows[] = $row;
            }

            return $rows;
        }

        return [];
    }

    /**
     * The form with its values: punched, else as read from the bill.
     * @return array{title:string, form:string, fields:array, values:array, tables:array, saved:bool, manual:array, mode:string, narration_remark:string}|null
     */
    public static function data(BtcCase $c): ?array
    {
        $def = self::def($c);
        if (! $def) {
            return null;
        }
        $saved = self::saved($c);
        $raw = self::raw($c);
        $vals = [];
        foreach ($def['fields'] as $k => $l) {
            $v = is_array($saved) && array_key_exists($k, $saved) ? $saved[$k] : (self::pick($raw, [$k, $l]) ?? null);
            if ($v === null && $k === 'Train Number') {
                $v = $raw['Bus Number'] ?? null;
            }
            $vals[$k] = is_scalar($v) ? trim((string) $v) : '';
        }
        // header fields that were renamed: older punches / readers
        $alias = ['Invoice No' => ['Bill No', 'Invoice Number', 'Bill Number'], 'Invoice Date' => ['Bill Date', 'Bill date'], 'Hotel Address' => ['Address'],
            'Total Fare' => ['Total Amount', 'Invoice Total', 'Grand Total']];
        foreach ($alias as $k => $alts) {
            if (array_key_exists($k, $vals) && $vals[$k] === '') {
                $vals[$k] = (string) (self::pick(is_array($saved) ? $saved : [], $alts) ?? self::pick($raw, $alts) ?? '');
            }
        }
        if (self::key($c) === 'vehicle' && $vals['Other Charges'] === '' && is_numeric($vals['Total Amount'] ?? '') && is_numeric($vals['Total KM'] ?? '') && is_numeric($vals['Per KM Rate'] ?? '')) {
            $vals['Other Charges'] = (string) round((float) $vals['Total Amount'] - (float) $vals['Total KM'] * (float) $vals['Per KM Rate'], 2);
        }
        $tables = [];
        foreach ((array) ($def['tables'] ?? []) as $t => $td) {
            $rows = is_array($saved['_t'][$t] ?? null) ? $saved['_t'][$t] : self::rowsFromRaw($raw, $td['cols']);
            if (! $rows && $t === 'legs') {   // the flat fields (older punch or reader) as the first leg
                $src = is_array($saved) ? $saved + $raw : $raw;
                $leg = [];
                foreach ($td['cols'] as $k => $l) {
                    $from = config('btc.punch_leg_from_flat.'.$k, $k);
                    $leg[$k] = (string) (self::pick($src, [$from, $k, $l]) ?? '');
                }
                if ($leg['Mode'] === '') {
                    $leg['Mode'] = ['air' => 'Air', 'train' => 'Train', 'bus' => 'Bus'][$c->category] ?? '';
                }
                $rows = [$leg];
            }
            if (! $rows && $t === 'rooms') {
                $room = array_fill_keys(array_keys($td['cols']), '');
                $room['Room No'] = (string) (self::pick($raw, ['Room No', 'Room Number', 'Room No.']) ?? ($saved['Room No'] ?? ''));
                $room['Room Type'] = (string) (self::pick(is_array($saved) ? $saved + $raw : $raw, ['Room Type']) ?? '');
                $room['Employees'] = (string) $c->emp_code;
                $rows = [$room];
            }
            if (! $rows && $t === 'items' && self::key($c) === 'hotel') {
                $src = is_array($saved) ? $saved + $raw : $raw;
                $rows = [['Particulars' => 'Room Charges', 'Room Type' => (string) (self::pick($src, ['Room Type']) ?? ''), 'Meal Plan' => '', 'Date' => '',
                    'Amount' => (string) (self::pick($src, ['Amount', 'Rate']) ?? ''), 'Other' => (string) (self::pick($src, ['Other Charges (+)']) ?? ''),
                    'Discount' => (string) (self::pick($src, ['Discount (-)']) ?? ''), 'GST %' => (string) (self::pick($src, ['GST (%)', 'GST Rate']) ?? ''), 'GST Type' => '',
                    'Grand Total' => (string) (self::pick($src, ['Grand Total']) ?? '')]];
            }
            $tables[$t] = array_values(array_map(fn ($r) => array_merge(array_fill_keys(array_keys($td['cols']), ''), array_intersect_key(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', (array) $r), $td['cols'])), $rows));
        }

        return ['title' => $def['title'], 'form' => self::key($c), 'fields' => $def['fields'], 'values' => $vals, 'tables' => $tables,
            'table_defs' => (array) ($def['tables'] ?? []), 'manual' => (array) ($def['manual'] ?? []), 'numbers' => (array) config('btc.punch_numbers'),
            'saved' => is_array($saved), 'mode' => (string) ($saved['_mode'] ?? config('btc.narration_mode.'.$c->category, 'exp.')),
            'narration_remark' => (string) ($saved['_narration_remark'] ?? '')];
    }

    private static function num($v): float
    {
        return (float) str_replace(',', '', (string) $v);
    }

    /**
     * Take the posted punch form: header fields, tables (totals worked out), the Focus narration inputs. Returns the
     * flat values (header fields plus the derived bill fields the rest of BTC reads); throws on a bad number.
     */
    public static function take(BtcCase $c, array $in): array
    {
        $def = self::def($c);
        if (! $def) {
            return [];
        }
        $nums = (array) config('btc.punch_numbers');
        $isNum = fn ($v) => is_numeric(str_replace(',', '', $v));
        $out = [];
        foreach (array_keys($def['fields']) as $k) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v !== '' && in_array($k, $nums, true) && ! $isNum($v)) {
                throw new RuntimeException(($def['fields'][$k] ?? $k).' must be a number.');
            }
            $out[$k] = mb_substr($v, 0, 300);
        }
        $tables = [];
        foreach ((array) ($def['tables'] ?? []) as $t => $td) {
            $rows = [];
            foreach (array_values((array) ($in['_t'][$t] ?? [])) as $i => $r) {
                $row = [];
                foreach (array_keys($td['cols']) as $k) {
                    $v = trim((string) ($r[$k] ?? ''));
                    if ($v !== '' && in_array($k, (array) ($td['numbers'] ?? []), true) && ! $isNum($v)) {
                        throw new RuntimeException(($td['title']).' '.($i + 1).': '.$td['cols'][$k].' must be a number.');
                    }
                    $row[$k] = mb_substr($v, 0, 300);
                }
                if (! array_filter($row, fn ($v) => $v !== '')) {
                    continue;   // an empty row added and left blank
                }
                if (! empty($td['total'])) {   // the row total from its parts when not typed
                    $sum = 0.0;
                    foreach ((array) ($td['plus'] ?? []) as $k) {
                        $sum += self::num($row[$k] ?? 0);
                    }
                    foreach ((array) ($td['minus'] ?? []) as $k) {
                        $sum -= self::num($row[$k] ?? 0);
                    }
                    if (! empty($td['gst'])) {
                        $sum *= 1 + self::num($row[$td['gst']] ?? 0) / 100;
                    }
                    if (($row[$td['total']] ?? '') === '' && ($td['plus'] ?? null)) {
                        $row[$td['total']] = (string) round($sum, 2);
                    }
                }
                $rows[] = $row;
            }
            $tables[$t] = array_slice($rows, 0, 50);
        }
        $key = self::key($c);
        // the bill figures the rest of BTC reads (lists, Finance data, narration) from the tables
        if ($key === 'air' && ($legs = $tables['legs'] ?? [])) {
            $first = $legs[0];
            foreach (['Mode', 'PNR Number', 'Date of Booking', 'Journey Date', 'Airline', 'Ticket Number', 'Journey From', 'Journey Upto', 'Travel Class'] as $k) {
                $out[$k] = $first[$k] ?? '';
            }
            $out['Journey Upto'] = end($legs)['Journey Upto'] ?? $out['Journey Upto'];
            $out['Base Fare'] = (string) round(array_sum(array_map(fn ($l) => self::num($l['Base Fare'] ?? 0), $legs)), 2);
            $out['GST'] = (string) round(array_sum(array_map(fn ($l) => self::num($l['GST'] ?? 0), $legs)) + self::num($out['Service Charge GST'] ?? 0), 2);
            $legsTotal = array_sum(array_map(fn ($l) => self::num($l['Leg Total'] ?? 0), $legs));
            $overall = round($legsTotal + self::num($out['Service Charge'] ?? 0) + self::num($out['Service Charge GST'] ?? 0), 2);
            if (($out['Total Fare'] ?? '') === '') {
                $out['Total Fare'] = (string) $overall;
            } elseif (abs(self::num($out['Total Fare']) - $overall) > 1) {
                throw new RuntimeException('Overall total of the legs and service charge (Rs '.number_format($overall, 2).') is not the invoice total (Rs '.number_format(self::num($out['Total Fare']), 2).'). Check each leg - one leg\'s figures must not be repeated on another.');
            }
            $out['Bill No'] = $out['Invoice No'] ?? '';
        }
        if ($key === 'hotel' && ($items = $tables['items'] ?? [])) {
            $sum = round(array_sum(array_map(fn ($r) => self::num($r['Grand Total'] ?? 0), $items)), 2);
            if (($out['Grand Total'] ?? '') === '') {
                $out['Grand Total'] = (string) $sum;
            }
            $out['Room Type'] = $items[0]['Room Type'] ?? '';
            $out['Amount'] = (string) round(array_sum(array_map(fn ($r) => self::num($r['Amount'] ?? 0), $items)), 2);
        }
        if ($key === 'hotel' && ($rooms = $tables['rooms'] ?? [])) {
            $out['Room No'] = implode(', ', array_filter(array_column($rooms, 'Room No')));
            if (($out['Number of Rooms'] ?? '') === '') {
                $out['Number of Rooms'] = (string) count($rooms);
            }
        }
        if ($key === 'meals' && ($items = $tables['items'] ?? [])) {
            $sum = round(array_sum(array_map(fn ($r) => self::num($r['Total Amt'] ?? 0), $items)) - self::num($out['Additional Discount'] ?? 0), 2);
            if (($out['Amount'] ?? '') === '') {
                $out['Amount'] = (string) $sum;
            }
        }
        if ($key === 'vehicle' && ($out['Other Charges'] ?? '') === '' && $isNum($out['Total Amount'] ?? '') && $isNum($out['Total KM'] ?? '') && $isNum($out['Per KM Rate'] ?? '')) {
            $out['Other Charges'] = (string) round(self::num($out['Total Amount']) - self::num($out['Total KM']) * self::num($out['Per KM Rate']), 2);
        }
        $store = $out + ['_t' => $tables];
        foreach (['_mode' => 60, '_narration_remark' => 200] as $k => $len) {
            if (array_key_exists($k, $in)) {
                $store[$k] = mb_substr(trim((string) $in[$k]), 0, $len);
            } elseif (($old = self::saved($c)) && isset($old[$k])) {
                $store[$k] = $old[$k];
            }
        }
        $c->punch_json = json_encode($store, JSON_UNESCAPED_UNICODE);

        return $out;
    }

    /** Employee codes named in the tables (legs / rooms), for the employees of the bill. */
    public static function tableEmployees(BtcCase $c): array
    {
        $s = self::saved($c);
        $codes = [];
        foreach ((array) ($s['_t'] ?? []) as $rows) {
            foreach ((array) $rows as $r) {
                foreach (preg_split('/[\s,;]+/', strtoupper((string) ($r['Employees'] ?? ''))) as $x) {
                    $x = preg_replace('/^E0*/', '', $x);
                    if ($x !== '') {
                        $codes[$x] = true;
                    }
                }
            }
        }

        return array_keys($codes);
    }
}
