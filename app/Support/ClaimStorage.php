<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * S3-FOLDER-01 (13 Nov 2026): claim files are stored per company:
 *   VNR (company 1) -> xeasy/   VVNR (11) -> xeasy_vvnr/   any other company -> xeasy/
 * (settable: CLAIM_FILES_PREFIX_VNR / _VVNR / _DEFAULT). Files stored before the change stay where they are
 * (Expense/ for VNR and Nursery, VVNR_Expense/ for VVNR). A file asked for under one of these folders that is not
 * there is looked for under the others, so old files keep opening and screens that still build the old path find
 * the new files. Paths below the folder are unchanged: {folder}/{yearId}/{employeeId}/{file}.
 */
class ClaimStorage
{
    private static array $resolved = [];

    public static function company(?int $companyId = null): int
    {
        if ($companyId) {
            return $companyId;
        }
        try {
            return (int) (request()?->input('ComId') ?: session('company_id') ?: 1);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /** Folder new files of a company go to. */
    public static function prefix(?int $companyId = null): string
    {
        $c = self::company($companyId);
        $map = (array) config('filesystems.claim_prefixes', []);

        return trim((string) ($map[$c] ?? config('filesystems.claim_prefix_default', 'xeasy')), '/') ?: 'xeasy';
    }

    /** Folder the company's files were stored in before the change. */
    public static function legacyPrefix(?int $companyId = null): string
    {
        return self::company($companyId) === 11 ? 'VVNR_Expense' : 'Expense';
    }

    /** Every folder claim files may be in: the company's new and old folder first. */
    public static function prefixes(?int $companyId = null): array
    {
        $all = array_merge([self::prefix($companyId), self::legacyPrefix($companyId)], array_values((array) config('filesystems.claim_prefixes', [])),
            [(string) config('filesystems.claim_prefix_default', 'xeasy'), 'Expense', 'VVNR_Expense']);

        return array_values(array_unique(array_filter(array_map(fn ($p) => trim((string) $p, '/'), $all))));
    }

    /** {folder}/{yearId}/{empId}/{file} for a new file. */
    public static function path(int $yearId, int $empId, string $name, ?int $companyId = null): string
    {
        return self::prefix($companyId)."/{$yearId}/{$empId}/{$name}";
    }

    /** The same path under the other folders (empty when the path is not under a claim folder). */
    public static function alternatives(string $path): array
    {
        $path = ltrim($path, '/');
        foreach (self::prefixes() as $p) {
            if (str_starts_with($path, $p.'/')) {
                $rest = substr($path, strlen($p) + 1);

                return array_values(array_filter(array_map(fn ($q) => $q.'/'.$rest, self::prefixes()), fn ($x) => $x !== $path));
            }
        }

        return [];
    }

    /** Where the file actually is (the path itself when it exists or nothing else does). */
    public static function resolve(string $path, string $disk = 's3'): string
    {
        if ($path === '' || ! config('filesystems.claim_resolve', true)) {
            return $path;
        }
        $key = $disk.'|'.$path;
        if (isset(self::$resolved[$key])) {
            return self::$resolved[$key];
        }
        $alts = self::alternatives($path);
        if (! $alts) {
            return self::$resolved[$key] = $path;
        }
        try {
            $d = Storage::disk($disk);
            if ($d->exists($path)) {
                return self::$resolved[$key] = $path;
            }
            foreach ($alts as $a) {
                if ($d->exists($a)) {
                    return self::$resolved[$key] = $a;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ClaimStorage resolve: '.$e->getMessage());
        }

        return self::$resolved[$key] = $path;
    }
}
