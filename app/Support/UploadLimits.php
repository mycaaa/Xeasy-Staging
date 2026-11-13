<?php

namespace App\Support;

/** UPLOAD-SIZE-01: PHP's upload limits in bytes (post_max_size / upload_max_filesize), 0 = unknown / no limit. */
class UploadLimits
{
    public static function postMaxBytes(): int
    {
        return self::bytes((string) ini_get('post_max_size'));
    }

    public static function fileMaxBytes(): int
    {
        return self::bytes((string) ini_get('upload_max_filesize'));
    }

    public static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '0' || $v === '-1') {
            return 0;
        }
        $n = (float) $v;
        $u = strtolower(substr($v, -1));

        return (int) match ($u) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }
}
