<?php

namespace App\Helpers;

use Carbon\Carbon;

class CommonHelper
{
    /**
     * Convert date from DD-MM-YYYY (or many other formats) to YYYY-MM-DD
     */
    public static function toDatabaseFormat($date)
    {
        if (!$date)
            return null;

        $date = trim((string) $date);

        if (
            $date === '' ||
            $date === '0' ||
            \App\Support\ZeroDate::none($date) ||
            \App\Support\ZeroDate::none($date) ||
            str_starts_with($date, '0000-00-00') ||
            str_starts_with($date, '1970-01-01') ||
            $date === '30-11--0001'
        ) {
            return null;
        }

        // If already in YYYY-MM-DD (or YYYY-MM-DD HH:MM:SS), return date part
        if (preg_match('/^\d{4}-\d{2}-\d{2}(\s.*)?$/', $date)) {
            $sub = substr($date, 0, 10);
            if (\App\Support\ZeroDate::none($sub) || $sub === '1970-01-01') {
                return null;
            }
            return $sub;
        }

        // Try common input formats
        $formats = [
            'd-m-Y',
            'd/m/Y',
            'd-m-Y H:i:s',
            'd/m/Y H:i:s',
            'd-m-Y H:i',
            'Y-m-d',
            'Y/m/d',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $date);
                if ($dt) {
                    if ($dt->year <= 1970) {
                        return null;
                    }
                    return $dt->format('Y-m-d');
                }
            } catch (\Exception $e) {
                // try next format
            }
        }

        // Fallback: try Carbon::parse (will throw if unparsable)
        try {
            $dt = Carbon::parse($date);
            if ($dt->year <= 1970) {
                return null;
            }
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            // If still unparseable return null
            return null;
        }
    }

    /**
     * Convert date from YYYY-MM-DD to DD-MM-YYYY
     */
    public static function toDisplayFormat($date)
    {
        if (!$date)
            return null;
        try {
            $date = trim((string) $date);
            if (
                $date === '' ||
                $date === '0' ||
                \App\Support\ZeroDate::none($date) ||
                \App\Support\ZeroDate::none($date) ||
                str_starts_with($date, '0000-00-00') ||
                str_starts_with($date, '1970-01-01') ||
                $date === '30-11--0001'
            ) {
                return null;
            }
            // Already in d-m-Y format — return as-is
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
                return $date;
            }
            // Y-m-d or Y-m-d H:i:s
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                $sub = substr($date, 0, 10);
                if (\App\Support\ZeroDate::none($sub) || $sub === '1970-01-01') {
                    return null;
                }
                return Carbon::createFromFormat('Y-m-d', $sub)->format('d-m-Y');
            }
            // d/m/Y
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                return Carbon::createFromFormat('d/m/Y', $date)->format('d-m-Y');
            }
            $parsed = Carbon::parse($date);
            if ($parsed->year <= 1970) {
                return null;
            }
            return $parsed->format('d-m-Y');
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function currentDisplayDate()
    {
        try {
            return Carbon::now()->format('d-m-Y');
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getMonth($date)
    {
        if (!$date)
            return null;

        try {
            // Use your existing standardized converter
            $dbDate = self::toDatabaseFormat($date);

            if (!$dbDate)
                return null;

            return Carbon::parse($dbDate)->format('m');  // 01–12
        } catch (\Exception $e) {
            return null;
        }
    }

    function InrCurrencyFormat($number, $decimals = 0)
    {
        if ($number === null) {
            return '';
        }

        $isNegative = $number < 0;
        $number = abs($number);

        $parts = explode('.', number_format($number, $decimals, '.', ''));
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? $parts[1] : str_repeat('0', $decimals);

        $lastThree = substr($integerPart, -3);
        $remaining = substr($integerPart, 0, -3);

        if ($remaining != '') {
            $lastThree = ',' . $lastThree;
        }

        $formatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining) . $lastThree;

        if ($decimals > 0) {
            $formatted .= '.' . $decimalPart;
        }

        return ($isNegative ? '-' : '') . $formatted;
    }

    /**
     * Get the current company's expense database connection
     */
    public static function getExpenseConnection()
    {
        return DatabaseSwitcher::getExpenseConnection();
    }

    /**
     * Get the current company's HRIMS database connection
     */
    public static function getHrimsConnection()
    {
        return DatabaseSwitcher::getHrimsConnection();
    }

    /**
     * Get the current company name
     */
    public static function getCurrentCompanyName()
    {
        $companyId = session('company_id', 1);
        return DatabaseSwitcher::getCompanyName($companyId);
    }

    /**
     * Format an employee code for display in reports.
     *
     * Rules:
     *  - Numeric only  → prefix 'E' + zero-pad to 4 digits  (e.g. 123 → E0123, 1234 → E1234)
     *  - Starts with V/v → return as-is
     *  - Anything else → return as-is
     *
     * @param  string|null  $code  Raw employee code from the database
     * @return string
     */
    public static function formatEmployeeCode(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        if (ctype_digit($code)) {
            return 'E' . str_pad($code, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    /**
     * Parse other participants from various formats (JSON array/object, semicolon/colon string, array)
     * into a standardized list of arrays: [['category' => '...', 'name' => '...', 'details' => '...']]
     */
    public static function parseOtherParticipants($rawOther): array
    {
        if (empty($rawOther)) {
            return [];
        }

        $jsonDec = is_array($rawOther) ? $rawOther : null;
        $rawOtherStr = is_string($rawOther) ? trim($rawOther) : '';

        if ($rawOtherStr === '' || $rawOtherStr === '[]' || $rawOtherStr === '{}') {
            return [];
        }

        // Unpack multi-level encoding/escaping
        for ($i = 0; $i < 5; $i++) {
            if (is_array($jsonDec)) {
                break;
            }
            if (is_string($rawOtherStr)) {
                $rawOtherStr = trim($rawOtherStr);
                // Decode HTML entities
                $rawOtherStr = html_entity_decode($rawOtherStr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Remove wrapping quotes if it wraps JSON array or object
                if (preg_match('/^["\'](.*)["\']$/s', $rawOtherStr, $m)) {
                    $cand = trim(stripslashes($m[1]));
                    if (str_starts_with($cand, '[') || str_starts_with($cand, '{')) {
                        $rawOtherStr = $cand;
                    }
                }
                $dec = json_decode($rawOtherStr, true);
                if (!is_array($dec)) {
                    $dec = json_decode(stripslashes($rawOtherStr), true);
                }
                if (is_array($dec)) {
                    $jsonDec = $dec;
                    break;
                } elseif (is_string($dec) && $dec !== $rawOtherStr) {
                    $rawOtherStr = $dec;
                } else {
                    break;
                }
            }
        }

        $list = [];
        if (is_array($jsonDec)) {
            if (isset($jsonDec['name']) || isset($jsonDec['participant_name'])) {
                $jsonDec = [$jsonDec];
            }
            foreach ($jsonDec as $item) {
                if (is_array($item)) {
                    $cntName = trim($item['name'] ?? $item['participant_name'] ?? '');
                    $cat     = trim($item['category'] ?? $item['role'] ?? 'Guest');
                    $details = trim($item['details'] ?? $item['remarks'] ?? $item['org'] ?? '');
                    if ($cntName !== '' || $cat !== '') {
                        $list[] = [
                            'name'     => $cntName,
                            'category' => $cat ?: 'Guest',
                            'details'  => $details
                        ];
                    }
                } elseif (is_string($item) && trim($item) !== '') {
                    $list[] = [
                        'name'     => trim($item),
                        'category' => 'Guest',
                        'details'  => ''
                    ];
                }
            }
        } else {
            // Fallback for non-JSON string formats (e.g. Semicolon/colon delimited: "Dealer:Ramesh:9876543210;Retailer:Suresh")
            $rawString = trim((string)$rawOtherStr);

            // If string contains JSON-like tokens, do not split by comma/colon as plain text
            if (str_contains($rawString, '"name"') || str_contains($rawString, '{') || str_contains($rawString, '}')) {
                if (preg_match_all('/"name"\s*:\s*"([^"]+)"/i', $rawString, $nameMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($nameMatches[1] as $idx => $match) {
                        $name = $match[0];
                        $offset = $match[1];
                        $chunk = substr($rawString, max(0, $offset - 50), 300);
                        $cat = 'Guest';
                        if (preg_match('/"category"\s*:\s*"([^"]+)"/i', $chunk, $catM)) {
                            $cat = $catM[1];
                        }
                        $details = '';
                        if (preg_match('/"details"\s*:\s*"([^"]*)"/i', $chunk, $detM)) {
                            $details = $detM[1];
                        }
                        if (!empty($name)) {
                            $list[] = [
                                'name'     => $name,
                                'category' => $cat ?: 'Guest',
                                'details'  => $details
                            ];
                        }
                    }
                }
            } else {
                $delimiter = str_contains($rawString, ';') ? ';' : (str_contains($rawString, '|') ? '|' : (str_contains($rawString, ',') && !str_contains($rawString, ':') ? ',' : ';'));
                $entries = explode($delimiter, $rawString);

                $masterCategories = ['Retailer', 'Dealer', 'Distributor', 'Consultant', 'Management', 'Guest', 'Other Employee not available in the list'];
                try {
                    if (class_exists(\App\Models\OtherParticipantCategory::class)) {
                        $dbCats = \App\Models\OtherParticipantCategory::active()->pluck('name')->toArray();
                        if (!empty($dbCats)) {
                            $masterCategories = $dbCats;
                        }
                    }
                } catch (\Throwable $e) {}

                foreach ($entries as $oPart) {
                    $oPart = trim($oPart);
                    if (empty($oPart)) continue;
                    if (str_contains($oPart, ':')) {
                        $parts = explode(':', $oPart);
                        $first = trim($parts[0] ?? '');
                        $second = trim($parts[1] ?? '');
                        $third = trim($parts[2] ?? '');

                        $isFirstCategory = in_array(strtolower($first), array_map('strtolower', $masterCategories));
                        if ($isFirstCategory) {
                            $cat = $first;
                            $cntName = $second;
                            $details = $third;
                        } else {
                            $isSecondCategory = in_array(strtolower($second), array_map('strtolower', $masterCategories));
                            if ($isSecondCategory) {
                                $cntName = $first;
                                $cat = $second;
                                $details = $third;
                            } else {
                                $cat = 'Guest';
                                $cntName = $first;
                                $details = $second . ($third ? " - {$third}" : '');
                            }
                        }
                        if (!empty($cntName)) {
                            $list[] = [
                                'name'     => $cntName,
                                'category' => $cat ?: 'Guest',
                                'details'  => $details
                            ];
                        }
                    } else {
                        $list[] = [
                            'name'     => $oPart,
                            'category' => 'Guest',
                            'details'  => ''
                        ];
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Normalize other participants to standard JSON string: '[{"name":"...","category":"...","details":"..."}]'
     */
    public static function normalizeOtherParticipantsJson($rawOther): string
    {
        $parsed = self::parseOtherParticipants($rawOther);
        return !empty($parsed) ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : '';
    }
}
