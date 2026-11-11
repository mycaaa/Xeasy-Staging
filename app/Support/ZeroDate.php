<?php

namespace App\Support;

/**
 * D-09 (11 Nov 2026): "no date yet" was stored as 0000-00-00. From the year-table consolidation it is NULL, and old
 * rows keep the zero date until they are converted, so code must treat both (and an empty string) as "no date".
 *   ZeroDate::none($d)  true for null, '', 0000-00-00 and 0000-00-00 00:00:00
 *   ZeroDate::has($d)   the opposite
 */
final class ZeroDate
{
    public static function none($date): bool
    {
        if ($date === null || $date === false) {
            return true;
        }
        if ($date instanceof \DateTimeInterface) {
            return false;
        }
        $s = trim((string) $date);

        return $s === '' || str_starts_with($s, '0000-00-00');
    }

    public static function has($date): bool
    {
        return ! self::none($date);
    }
}
