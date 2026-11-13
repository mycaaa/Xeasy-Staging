<?php

namespace App\Support;

/**
 * FILES-PERM-01 (13 Nov 2026): when a claim file cannot be stored on the server (CLAIM_FILES_LOCAL=true), say why:
 * the first folder on the way that is missing and cannot be created, or is not writable by the PHP user.
 */
class FileStoreDiagnosis
{
    public static function localProblem(string $root, string $subPath = ''): ?string
    {
        $user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid()) : get_current_user();
        $dir = rtrim($root, '/').($subPath !== '' ? '/'.trim(dirname($subPath.'/x'), '/') : '');
        // walk up to the deepest folder that exists
        $probe = $dir;
        while ($probe !== '' && $probe !== '/' && ! is_dir($probe)) {
            $probe = dirname($probe);
        }
        if (! is_writable($probe)) {
            $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(@fileowner($probe))['name'] ?? '?') : '?';
            $group = function_exists('posix_getgrgid') ? (posix_getgrgid(@filegroup($probe))['name'] ?? '?') : '?';
            $perm = substr(sprintf('%o', @fileperms($probe)), -4);

            return "folder {$probe} is not writable by PHP user {$user} (owner {$owner}, group {$group}, mode {$perm})";
        }
        $free = @disk_free_space($probe);
        if ($free !== false && $free < 20 * 1024 * 1024) {
            return "disk almost full at {$probe} (".round($free / 1048576).' MB free)';
        }

        return null;
    }
}
