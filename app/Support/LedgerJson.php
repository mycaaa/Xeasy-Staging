<?php

namespace App\Support;

/**
 * Helpers for employee_ledger_balances.remaining_balances_json.
 *
 * approveClaimMonth() used to call json_encode() on a value that was already a
 * JSON string, so the column holds a JSON *string literal* such as
 * "{\"ledger\":[],\"paid\":[],\"expense\":[],\"carried\":[]}" instead of the object.
 * decode() accepts both shapes (and any depth of re-encoding), encode() always
 * writes the object encoded exactly once.
 */
class LedgerJson
{
    public const CATEGORIES = ['ledger', 'paid', 'expense', 'carried'];

    /**
     * Decode a remaining-balances value into an array, unwrapping double encoding.
     * Returns [] for null / empty / invalid input.
     */
    public static function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = $value;
        // Unwrap at most a few layers of string-in-string encoding.
        for ($i = 0; $i < 5 && is_string($decoded); $i++) {
            $next = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Legacy writers sometimes stored addslashes()'d JSON.
                $next = json_decode(stripslashes($decoded), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [];
                }
            }
            $decoded = $next;
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode once. Null is returned for an empty value so the column stays NULL.
     */
    public static function encode($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $arr = self::decode($value);
        if ($arr === []) {
            return '{}';
        }

        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    /**
     * True when the stored value is a JSON string literal wrapping JSON (double-encoded).
     */
    public static function isDoubleEncoded($value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }
        $first = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_string($first);
    }

    /**
     * Sum of remaining_amount over all categories.
     */
    public static function totalRemaining($value): float
    {
        $total = 0.0;
        foreach (self::decode($value) as $items) {
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $total += (float) ($item['remaining_amount'] ?? 0);
                }
            }
        }

        return round($total, 2);
    }

    /**
     * Closing balance written to employee_ledger_balances.closing_balance:
     * fixed imprest held + advances still outstanding after the approval.
     * (opening + advances paid - adjustments; the remaining JSON is that running result.)
     */
    public static function closingBalance($ledgerRow, $remaining): float
    {
        $row = (array) $ledgerRow;
        $imprest = (float) ($row['fixed_imprest_amt_1'] ?? 0) + (float) ($row['fixed_imprest_amt_2'] ?? 0);

        return round($imprest + self::totalRemaining($remaining), 2);
    }
}
