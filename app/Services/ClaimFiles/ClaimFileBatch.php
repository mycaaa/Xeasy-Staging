<?php

namespace App\Services\ClaimFiles;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * UAT R-01: all-or-nothing storage of the files that belong to one claim save.
 *
 * Save paths store every file through one batch before (or inside the same
 * transaction as) the claim write. A file that cannot be stored throws
 * ClaimFileException, and the caller returns its normal error response. If the
 * DB write fails afterwards, discard() deletes the files already stored, so a
 * failed save leaves neither a claim row nor an orphan file.
 */
class ClaimFileBatch
{
    /** @var array<int, array{field: string, name: string, path: string, preexisting: bool}> */
    private array $stored = [];

    public function __construct(private string $disk = 's3')
    {
    }

    /**
     * Store one uploaded file at the mobile app's path Expense/{yearId}/{empId}/{name}
     * (same formula as S3Service::generateS3Path). Returns the stored file name.
     */
    public function putMobile($file, int $yearId, int $empId, string $name, string $field = ''): string
    {
        $this->put("Expense/{$yearId}/{$empId}/{$name}", $file, $field, $name);

        return $name;
    }

    /** Short random suffix for generated file names (avoids same-second name collisions). */
    public static function uniqueSuffix(): string
    {
        return bin2hex(random_bytes(3));
    }

    /** Store one uploaded file at $path. Throws ClaimFileException on any failure. */
    public function put(string $path, $file, string $field = '', ?string $name = null): string
    {
        $name = $name ?? basename($path);
        $label = $field !== '' ? $field : 'attachment';
        foreach ($this->stored as $s) {
            if ($s['path'] === $path) {
                // Two attachments of one save would land on the same object and the first would be lost.
                throw new ClaimFileException("Two attachments have the same file name ({$name}). Rename one and try again.");
            }
        }
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw new ClaimFileException("The {$label} file could not be read. Please attach it again.");
        }
        // LV-S10: claim attachments must really be an image, PDF or spreadsheet (read from the
        // content), at most 15 MB, and the stored name may not carry a script extension.
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (\App\Support\SafeUpload::isBlockedName($name) || ! preg_match('/^[A-Za-z0-9._-]+$/', $name)
            || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'gif', 'pdf', 'xlsx', 'xls', 'csv'], true)) {
            throw new ClaimFileException("The {$label} file type is not allowed. Attach a photo, PDF or Excel file.");
        }
        try {
            \App\Support\SafeUpload::check($file, ['jpg', 'png', 'webp', 'heic', 'gif', 'pdf', 'xlsx', 'xls', 'csv'], 15360);
        } catch (\RuntimeException $e) {
            throw new ClaimFileException("The {$label} file was refused: ".$e->getMessage());
        }
        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new ClaimFileException("The {$label} file could not be read. Please attach it again.");
        }
        // An object that already existed (e.g. an activity file stored under its original name by an
        // earlier claim) is never deleted by discard(): it may belong to another claim.
        try {
            $preexisting = Storage::disk($this->disk)->exists($path);
        } catch (\Throwable $e) {
            $preexisting = true;
        }
        try {
            $ok = Storage::disk($this->disk)->put($path, $contents);
        } catch (\Throwable $e) {
            Log::error('Claim file store failed', ['path' => $path, 'error' => $e->getMessage()]);
            $ok = false;
        }
        if (! $ok) {
            // STORE-WHY-01: with 'throw' => false the disk only says false. Retry once through the driver, which throws
            // with the storage service's own reason (access denied, no such bucket, wrong region, folder not writable).
            $driverError = null;
            try {
                Storage::disk($this->disk)->getDriver()->write($path, $contents);
                $ok = true;
            } catch (\Throwable $e) {
                $prev = $e->getPrevious();
                $driverError = trim($e->getMessage().($prev ? ' | '.$prev->getMessage() : ''));
                if ($prev instanceof \Aws\Exception\AwsException) {
                    $driverError = ($prev->getAwsErrorCode() ?: 'AWS error').': '.($prev->getAwsErrorMessage() ?: $prev->getMessage());
                }
            }
        }
        if (! $ok) {
            // FILES-PERM-01: record why (folder permissions / disk space / bucket) so the server log shows the cause
            $cfg = (array) config("filesystems.disks.{$this->disk}");
            $why = ($cfg['driver'] ?? '') === 'local'
                ? \App\Support\FileStoreDiagnosis::localProblem((string) ($cfg['root'] ?? ''), $path)
                : 'bucket '.($cfg['bucket'] ?? '(none)').' region '.($cfg['region'] ?? '?').(empty($cfg['key']) ? ' - no AWS key set' : '');
            $ref = strtoupper(substr(sha1(uniqid('', true)), 0, 8));
            Log::error('Claim file store returned false', ['ref' => $ref, 'path' => $path, 'disk' => $this->disk, 'driver' => $cfg['driver'] ?? '?', 'reason' => $why, 'storage_error' => $driverError]);
            $user = auth()->user();
            $admin = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin']);
            $detail = $admin ? ' Reason: '.mb_substr((string) ($driverError ?: $why), 0, 220).'.' : '';
            throw new ClaimFileException("File upload failed: the claim was not saved (ref {$ref}).{$detail} Please try again in a few minutes; if it repeats, send the ref to the Xeasy team.");
        }
        $this->stored[] = ['field' => $field, 'name' => $name, 'path' => $path, 'preexisting' => $preexisting];
        UploadFingerprint::record($path, $name, $contents); // FILE-FP-01

        return $name;
    }

    /**
     * Store every file in $files (UploadedFile, array of them, or null) for one field.
     * Nulls (no file chosen) are skipped. Returns the stored names in order.
     *
     * @param  callable(UploadedFile, int): string  $pathFor  path for the n-th file (1-based)
     */
    public function putAll($files, string $field, callable $pathFor): array
    {
        $names = [];
        $n = 0;
        foreach (is_array($files) ? $files : [$files] as $file) {
            if ($file === null) {
                continue;
            }
            $n++;
            $path = $pathFor($file, $n);
            $names[] = $this->put($path, $file, $field);
        }

        return $names;
    }

    /** Stored file names, optionally for one field only. */
    public function names(?string $field = null): array
    {
        return array_values(array_map(fn ($s) => $s['name'], array_filter($this->stored, fn ($s) => $field === null || $s['field'] === $field)));
    }

    /** Number of files stored, optionally for one field. */
    public function count(?string $field = null): int
    {
        return count($this->names($field));
    }

    /** Stored-file counts per field (input for the #16 / #17 / #21 rules). */
    public function countsByField(): array
    {
        $counts = [];
        foreach ($this->stored as $s) {
            $counts[$s['field']] = ($counts[$s['field']] ?? 0) + 1;
        }

        return $counts;
    }

    /** Delete every file stored by this batch (the save failed). Returns how many were deleted. */
    public function discard(): int
    {
        $deleted = 0;
        foreach ($this->stored as $s) {
            if ($s['preexisting']) {
                Log::warning('Claim file cleanup: kept an object that existed before this save', ['path' => $s['path']]);
                continue;
            }
            try {
                if (Storage::disk($this->disk)->delete($s['path'])) {
                    $deleted++;
                } else {
                    Log::warning('Claim file cleanup: delete returned false', ['path' => $s['path']]);
                }
            } catch (\Throwable $e) {
                Log::warning('Claim file cleanup failed', ['path' => $s['path'], 'error' => $e->getMessage()]);
            }
        }
        $this->stored = [];

        return $deleted;
    }

    /** The save succeeded: keep the files. */
    public function keep(): void
    {
        $this->stored = [];
    }

    /**
     * Run $write in one DB transaction on $connection. If it throws, the
     * transaction rolls back, the stored files are deleted and the exception is
     * rethrown. If it returns, the files are kept and its result is returned.
     */
    public function commitWith(?string $connection, callable $write)
    {
        try {
            $result = DB::connection($connection)->transaction(fn () => $write($this));
        } catch (\Throwable $e) {
            $this->discard();
            throw $e;
        }
        $this->keep();

        return $result;
    }
}
