<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * FILES-01 (UAT-03): claim-stage exports (Focus export included) were written to the public disk
 * (storage/app/public/temp, served as /storage/temp/<name>), so anyone with the link, or anyone
 * guessing the time-stamped name, could download employee pay data without logging in. They are now
 * written to the private disk below and downloaded through /exports/download/<name>, which needs a
 * login plus a reports/finance permission (config/route_access.php). Old /storage/temp/<name> links
 * go through the same check (see routes/backend.php).
 */
class PrivateExport
{
    public const DISK = 'private_exports';

    /** Only plain file names: no folders, no traversal. */
    public const NAME = '[A-Za-z0-9][A-Za-z0-9_.\-]{0,190}';

    public static function url(string $path): string
    {
        return url('exports/download/'.rawurlencode(basename($path)));
    }

    /** Disk and path of a stored export, private first, then a file left on the public disk. */
    public static function locate(string $file): ?array
    {
        $file = basename($file);
        if (! preg_match('#^'.self::NAME.'$#', $file)) {
            return null;
        }
        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists('temp/'.$file)) {
                return [$disk, 'temp/'.$file];
            }
        }

        return null;
    }

    /** "temp/<name>" from a stored path, an /exports/download/ URL or an old /storage/temp/ URL. */
    public static function pathFrom(?string $pathOrUrl): ?string
    {
        if (! $pathOrUrl) {
            return null;
        }
        $p = parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl;
        $name = basename(rawurldecode($p));

        return preg_match('#^'.self::NAME.'$#', $name) ? 'temp/'.$name : null;
    }
}
