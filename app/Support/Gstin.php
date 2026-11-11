<?php

namespace App\Support;

/**
 * GST-01 (tracker #6): offline GSTIN check - format, state code and the check digit
 * (GSTN mod-36 algorithm). A live lookup on the GST portal needs a GSP account; this
 * check catches mistyped and made-up numbers without one.
 */
class Gstin
{
    private const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '28' => 'Andhra Pradesh (old)',
        '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
        '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh',
        '38' => 'Ladakh', '97' => 'Other Territory', '99' => 'Centre Jurisdiction',
    ];

    public static function normalize(?string $value): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $value));
    }

    /** @return array{status:string, message:?string, state:?string, gstin:string} */
    public static function check(?string $value): array
    {
        $g = self::normalize($value);
        if ($g === '') {
            return ['status' => 'missing', 'message' => null, 'state' => null, 'gstin' => ''];
        }
        if (! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g)) {
            return ['status' => 'invalid_format', 'message' => "GSTIN {$g} is not in the 15-character GSTIN format (e.g. 22AAAAA0000A1Z5).", 'state' => null, 'gstin' => $g];
        }
        $state = self::STATES[substr($g, 0, 2)] ?? null;
        if ($state === null) {
            return ['status' => 'invalid_state', 'message' => "GSTIN {$g} starts with an unknown state code ".substr($g, 0, 2).'.', 'state' => null, 'gstin' => $g];
        }
        if (self::checkDigit(substr($g, 0, 14)) !== $g[14]) {
            return ['status' => 'invalid_checksum', 'message' => "GSTIN {$g} fails the check digit - please re-check it on the bill.", 'state' => $state, 'gstin' => $g];
        }

        return ['status' => 'valid', 'message' => null, 'state' => $state, 'gstin' => $g];
    }

    public static function checkDigit(string $first14): string
    {
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $v = strpos(self::CHARS, $first14[$i]);
            $p = $v * (($i % 2) + 1);
            $sum += intdiv($p, 36) + ($p % 36);
        }

        return self::CHARS[(36 - ($sum % 36)) % 36];
    }
}
