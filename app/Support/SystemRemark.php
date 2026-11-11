<?php

namespace App\Support;

/**
 * REMARK-AUTO-01 (7 Nov 2026): a remark the system writes (auto-verification, duplicate odometer
 * clean-up, bank file confirmation ...) ends with "(auto generated)", so verifiers, approvers,
 * Finance and the employee can tell it from a remark a person typed.
 */
class SystemRemark
{
    public const SUFFIX = ' (auto generated)';

    public static function mark(?string $text, ?int $max = null): string
    {
        $t = trim((string) $text);
        if ($t === '') {
            $t = 'Updated by the system';
        }
        if (! self::isMarked($t)) {
            if ($max !== null && mb_strlen($t) + mb_strlen(self::SUFFIX) > $max) {
                $t = rtrim(mb_substr($t, 0, $max - mb_strlen(self::SUFFIX)));
            }
            $t .= self::SUFFIX;
        }

        return $t;
    }

    public static function isMarked(?string $text): bool
    {
        return str_ends_with(trim((string) $text), trim(self::SUFFIX));
    }
}
