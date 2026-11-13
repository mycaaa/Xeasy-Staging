<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * S3 file service - wraps Laravel's Storage facade for S3 operations.
 * Used by the Mobile API controllers for file uploads and URL generation.
 */
class S3Service
{
    protected $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('s3');
    }

    /**
     * Upload a file to S3.
     *
     * @param array $file ['name' => string, 'tmp_name' => string, 'size' => int, 'type' => string]
     * @param int $yearId
     * @param int $empId
     * @return array ['success' => bool, 'message' => string, 'url' => string|null]
     */
    public function uploadFile(array $file, int $yearId, int $empId): array
    {
        try {
            $s3Path = $this->generateS3Path($yearId, $empId, $file['name']);
            $result = $this->disk->put($s3Path, file_get_contents($file['tmp_name']));

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'url' => $this->getFileViewUrl($s3Path),
                ];
            }

            return ['success' => false, 'message' => 'Upload failed', 'url' => null];
        } catch (\Exception $e) {
            Log::error('S3Service uploadFile error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'url' => null];
        }
    }

    /**
     * Check if a file exists on S3.
     */
    public function fileExists(string $path): bool
    {
        try {
            $path = \App\Support\ClaimStorage::resolve($path); // S3-FOLDER-01: new or old company folder

            return $this->disk->exists($path);
        } catch (\Exception $e) {
            Log::warning('S3Service fileExists error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a public URL for viewing a file.
     * Uses permanent public URL instead of temporary presigned URL
     * to avoid expiry issues in mobile apps.
     */
    public function getFileViewUrl(string $path): string
    {
        try {
            $path = \App\Support\ClaimStorage::resolve($path); // S3-FOLDER-01: new or old company folder
            // S3-SEC-01: a private bucket (AWS_PRIVATE_BUCKET=true, public access blocked) gets a signed link that
            // expires; the open URL is kept only for the bucket that is still public
            if (config('filesystems.disks.s3.driver') === 's3' && config('filesystems.disks.s3.private_bucket')) {
                return $this->disk->temporaryUrl($path, now()->addMinutes((int) config('filesystems.disks.s3.url_minutes', 60)));
            }
            return $this->disk->url($path);
        } catch (\Exception $e) {
            Log::warning('S3Service getFileViewUrl error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Generate S3 path for a file.
     */
    public function generateS3Path(int $yearId, int $empId, string $fileName): string
    {
        return \App\Support\ClaimStorage::path($yearId, $empId, $fileName); // S3-FOLDER-01
    }

    /**
     * Delete a file from S3.
     */
    public function deleteFile(string $path): array
    {
        try {
            $result = $this->disk->delete($path);
            return ['success' => $result, 'message' => $result ? 'Deleted' : 'Delete failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Parse S3 key from full S3 URL or relative key string.
     */
    public function parseS3Key(?string $input = ''): string
    {
        $input = trim((string) ($input ?? ''));
        if (empty($input)) {
            return '';
        }

        // If full HTTP/HTTPS URL
        if (preg_match('~^https?://~i', $input)) {
            $parsed = parse_url($input);
            $path = ltrim($parsed['path'] ?? '', '/');

            // Handle bucket path style: s3.amazonaws.com/bucket-name/key or s3.region.amazonaws.com/bucket-name/key
            $bucket = config('filesystems.disks.s3.bucket') ?: env('AWS_BUCKET');
            if (!empty($bucket) && str_starts_with($path, $bucket . '/')) {
                $path = substr($path, strlen($bucket) + 1);
            }

            return ltrim($path, '/');
        }

        return ltrim($input, '/');
    }

    /**
     * Copy or Move a file or an entire folder on S3.
     *
     * @param string $sourceInput Full S3 URL or S3 key path
     * @param string $targetInput Full S3 URL or S3 key path
     * @param bool $isMove True for move, false for copy
     * @return array
     */
    public function transferPath(string $sourceInput, string $targetInput, bool $isMove = false): array
    {
        try {
            $sourceKey = $this->parseS3Key($sourceInput);
            $targetKey = $this->parseS3Key($targetInput);

            if (empty($sourceKey)) {
                return ['success' => false, 'message' => 'Source S3 path/URL is required.'];
            }

            if (empty($targetKey)) {
                return ['success' => false, 'message' => 'Target S3 path/URL is required.'];
            }

            if ($sourceKey === $targetKey) {
                return ['success' => false, 'message' => 'Source and Target paths cannot be identical.'];
            }

            // Case 1: Source is a single existing file
            if ($this->disk->exists($sourceKey)) {
                // If target looks like a folder (ends with / or has no filename extension)
                if (str_ends_with($targetKey, '/') || pathinfo($targetKey, PATHINFO_EXTENSION) === '') {
                    $finalTargetKey = rtrim($targetKey, '/') . '/' . basename($sourceKey);
                } else {
                    $finalTargetKey = $targetKey;
                }

                $copied = $this->copyFileWithFallback($sourceKey, $finalTargetKey);
                if (!$copied) {
                    return ['success' => false, 'message' => "Failed to copy {$sourceKey} to {$finalTargetKey}."];
                }

                if ($isMove) {
                    $this->disk->delete($sourceKey);
                }

                return [
                    'success' => true,
                    'is_folder' => false,
                    'message' => ($isMove ? 'Moved' : 'Copied') . ' file successfully.',
                    'source_key' => $sourceKey,
                    'target_key' => $finalTargetKey,
                    'target_url' => $this->disk->url($finalTargetKey),
                    'files_transferred' => 1,
                    'transferred_files' => [
                        [
                            'source' => $sourceKey,
                            'target' => $finalTargetKey,
                            'url' => $this->disk->url($finalTargetKey),
                        ]
                    ]
                ];
            }

            // Case 2: Source is a folder (prefix)
            $sourcePrefix = rtrim($sourceKey, '/') . '/';
            $targetPrefix = rtrim($targetKey, '/') . '/';

            $allFiles = $this->disk->allFiles($sourcePrefix);

            if (empty($allFiles)) {
                // Check if sourceKey without trailing slash lists files
                $allFiles = $this->disk->allFiles(rtrim($sourceKey, '/'));
            }

            if (empty($allFiles)) {
                return [
                    'success' => false,
                    'message' => "No files or folder found at source path: '{$sourceKey}'."
                ];
            }

            $transferredFiles = [];
            $failedFiles = [];

            foreach ($allFiles as $file) {
                // Calculate relative path inside source folder
                $relative = $file;
                if (str_starts_with($file, $sourcePrefix)) {
                    $relative = substr($file, strlen($sourcePrefix));
                } elseif (str_starts_with($file, rtrim($sourcePrefix, '/'))) {
                    $relative = ltrim(substr($file, strlen(rtrim($sourcePrefix, '/'))), '/');
                }

                $destKey = $targetPrefix . $relative;

                $copied = $this->copyFileWithFallback($file, $destKey);
                if ($copied) {
                    if ($isMove) {
                        $this->disk->delete($file);
                    }

                    $transferredFiles[] = [
                        'source' => $file,
                        'target' => $destKey,
                        'url' => $this->disk->url($destKey),
                    ];
                } else {
                    $failedFiles[] = $file;
                }
            }

            $count = count($transferredFiles);
            $actionWord = $isMove ? 'Moved' : 'Copied';

            return [
                'success' => true,
                'is_folder' => true,
                'message' => "{$actionWord} {$count} file(s) from folder successfully.",
                'source_prefix' => $sourcePrefix,
                'target_prefix' => $targetPrefix,
                'target_url' => $this->disk->url($targetPrefix),
                'files_transferred' => $count,
                'transferred_files' => $transferredFiles,
                'failed_files' => $failedFiles,
            ];
        } catch (\Exception $e) {
            Log::error('S3Service transferPath error: ' . $e->getMessage(), [
                'source' => $sourceInput,
                'target' => $targetInput,
                'isMove' => $isMove
            ]);

            return ['success' => false, 'message' => 'S3 Error: ' . $e->getMessage()];
        }
    }

    /**
     * List subfolders and files in a given S3 prefix with native AWS pagination / chunking.
     * Uses listObjectsV2 with MaxKeys and ContinuationToken for lightning-fast sub-second responses.
     */
    public function listContentsPaginated(?string $prefix = '', int $limit = 50, ?string $continuationToken = null, bool $forceRefresh = false): array
    {
        try {
            $prefix = $this->parseS3Key($prefix);
            if (!empty($prefix) && !str_ends_with($prefix, '/')) {
                $prefix .= '/';
            }

            $limit = max(10, min(500, $limit ?: 50));
            $tokenKey = md5($continuationToken ?? 'first_chunk');
            $cacheKey = 's3_chunk_' . md5($prefix) . "_{$limit}_{$tokenKey}";

            try {
                if (!$forceRefresh && Cache::store('file')->has($cacheKey)) {
                    $cached = Cache::store('file')->get($cacheKey);
                    if (is_array($cached) && !empty($cached['success'])) {
                        $cached['from_cache'] = true;
                        return $cached;
                    }
                }
            } catch (\Throwable $cacheEx) {
                // If cache store is temporarily unreachable, proceed to query S3 directly
            }

            $bucket = (string) config('filesystems.disks.s3.bucket') /* S3-SEC-01: no fallback to the live bucket */;
            $client = $this->disk->getClient();

            $params = [
                'Bucket' => $bucket,
                'Prefix' => $prefix,
                'Delimiter' => '/',
                'MaxKeys' => $limit,
            ];

            if (!empty($continuationToken)) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $res = $client->listObjectsV2($params);

            $dirList = [];
            if (!empty($res['CommonPrefixes'])) {
                foreach ($res['CommonPrefixes'] as $cp) {
                    $dirPath = rtrim($cp['Prefix'], '/');
                    $dirList[] = [
                        'name' => basename($dirPath),
                        'path' => $dirPath,
                        'type' => 'directory',
                    ];
                }
            }

            $fileList = [];
            if (!empty($res['Contents'])) {
                foreach ($res['Contents'] as $item) {
                    $key = $item['Key'];
                    // Exclude the folder directory marker itself
                    if ($key === $prefix || str_ends_with($key, '/')) {
                        continue;
                    }

                    $name = basename($key);
                    if ($name === '.keep') {
                        continue;
                    }

                    $lastModified = '-';
                    if (isset($item['LastModified'])) {
                        $lastModified = $item['LastModified'] instanceof \DateTimeInterface
                            ? $item['LastModified']->format('Y-m-d H:i:s')
                            : (string) $item['LastModified'];
                    }

                    $fileList[] = [
                        'name' => $name,
                        'path' => $key,
                        'size' => (int) ($item['Size'] ?? 0),
                        'last_modified' => $lastModified,
                        'url' => $this->disk->url($key),
                        'type' => 'file',
                    ];
                }
            }

            // Sort directories and files in this chunk
            usort($dirList, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            usort($fileList, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            $isTruncated = !empty($res['IsTruncated']);
            $nextToken = $isTruncated ? ($res['NextContinuationToken'] ?? null) : null;

            $output = [
                'success' => true,
                'current_prefix' => $prefix,
                'directories' => $dirList,
                'files' => $fileList,
                'is_truncated' => $isTruncated,
                'next_token' => $nextToken,
                'chunk_size' => $limit,
                'from_cache' => false,
            ];

            // Cache each chunk for 3 minutes for instant back/forth browsing
            try {
                Cache::store('file')->put($cacheKey, $output, now()->addMinutes(3));
            } catch (\Throwable $cacheEx) {
                // If cache store is temporarily unreachable, do not fail
            }

            return $output;
        } catch (\Exception $e) {
            Log::error('S3Service listContentsPaginated error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'directories' => [],
                'files' => [],
                'is_truncated' => false,
                'next_token' => null,
            ];
        }
    }

    /**
     * Backward-compatible listContents wrapper.
     */
    public function listContents(?string $prefix = ''): array
    {
        return $this->listContentsPaginated($prefix, 100);
    }

    /**
     * Delete an entire folder and all its contents recursively from S3.
     */
    public function deleteFolder(string $folderInput): array
    {
        try {
            $folderKey = $this->parseS3Key($folderInput);
            if (empty($folderKey)) {
                return ['success' => false, 'message' => 'Folder path is required.'];
            }

            $prefix = rtrim($folderKey, '/') . '/';
            $allFiles = $this->disk->allFiles($prefix);
            $deletedCount = 0;

            if (!empty($allFiles)) {
                $this->disk->delete($allFiles);
                $deletedCount = count($allFiles);
            }

            // Also remove directory markers or placeholders
            $this->disk->deleteDirectory($prefix);
            $this->disk->delete($prefix);
            $this->disk->delete($prefix . '.keep');

            return [
                'success' => true,
                'message' => "Folder '{$prefix}' deleted successfully ({$deletedCount} file(s) removed).",
                'deleted_count' => $deletedCount,
            ];
        } catch (\Exception $e) {
            Log::error('S3Service deleteFolder error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete folder: ' . $e->getMessage()];
        }
    }

    /**
     * Rename a file or folder on S3.
     */
    public function renameItem(string $oldPath, string $newName, bool $isFolder = false): array
    {
        try {
            $oldKey = $this->parseS3Key($oldPath);
            if (empty($oldKey)) {
                return ['success' => false, 'message' => 'Source path is required.'];
            }

            $newName = trim($newName, " /\\");
            if (empty($newName)) {
                return ['success' => false, 'message' => 'New name cannot be empty.'];
            }

            if (preg_match('/[<>:"|?*]/', $newName)) {
                return ['success' => false, 'message' => 'Name contains invalid characters.'];
            }

            if ($isFolder) {
                $oldPrefix = rtrim($oldKey, '/');
                $parent = dirname($oldPrefix);
                $newPrefix = ($parent && $parent !== '.' ? $parent . '/' : '') . $newName . '/';

                if ($oldPrefix . '/' === $newPrefix) {
                    return ['success' => false, 'message' => 'New folder name is identical to current name.'];
                }

                return $this->transferPath($oldPrefix . '/', $newPrefix, true);
            } else {
                $parent = dirname($oldKey);
                $newKey = ($parent && $parent !== '.' ? $parent . '/' : '') . $newName;

                if ($oldKey === $newKey) {
                    return ['success' => false, 'message' => 'New file name is identical to current name.'];
                }

                return $this->transferPath($oldKey, $newKey, true);
            }
        } catch (\Exception $e) {
            Log::error('S3Service renameItem error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to rename: ' . $e->getMessage()];
        }
    }

    /**
     * Create a new folder on S3.
     */
    public function createFolder(string $currentPrefix, string $folderName): array
    {
        try {
            $currentPrefix = $this->parseS3Key($currentPrefix);
            $folderName = trim($folderName, " /\\");

            if (empty($folderName)) {
                return ['success' => false, 'message' => 'Folder name is required.'];
            }

            if (preg_match('/[<>:"|?*]/', $folderName)) {
                return ['success' => false, 'message' => 'Folder name contains invalid characters.'];
            }

            $newFolderKey = ($currentPrefix ? rtrim($currentPrefix, '/') . '/' : '') . $folderName . '/';

            // Create placeholder .keep file in S3 to persist empty folder
            $this->disk->put($newFolderKey . '.keep', '');

            return [
                'success' => true,
                'message' => "Folder '{$folderName}' created successfully.",
                'path' => $newFolderKey,
            ];
        } catch (\Exception $e) {
            Log::error('S3Service createFolder error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create folder: ' . $e->getMessage()];
        }
    }

    /**
     * Upload files to a specific S3 folder prefix.
     */
    public function uploadToPrefix(string $prefix, array $files): array
    {
        try {
            $prefix = $this->parseS3Key($prefix);
            if (!empty($prefix) && !str_ends_with($prefix, '/')) {
                $prefix .= '/';
            }

            $uploaded = [];
            $failed = [];

            foreach ($files as $file) {
                if (!$file->isValid()) {
                    $failed[] = $file->getClientOriginalName() . ' (Invalid file)';
                    continue;
                }

                $fileName = $file->getClientOriginalName();
                $targetKey = $prefix . $fileName;

                $stream = fopen($file->getRealPath(), 'r+');
                $result = $this->disk->put($targetKey, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($result) {
                    $uploaded[] = [
                        'name' => $fileName,
                        'path' => $targetKey,
                        'url' => $this->disk->url($targetKey),
                    ];
                } else {
                    $failed[] = $fileName;
                }
            }

            $successCount = count($uploaded);
            $failCount = count($failed);

            return [
                'success' => $successCount > 0,
                'message' => "Uploaded {$successCount} file(s) successfully" . ($failCount > 0 ? ", {$failCount} failed." : "."),
                'uploaded' => $uploaded,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            Log::error('S3Service uploadToPrefix error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Bulk delete files and folders.
     */
    public function bulkDelete(array $items): array
    {
        try {
            $deletedFiles = 0;
            $deletedFolders = 0;
            $failed = [];

            foreach ($items as $item) {
                $path = is_array($item) ? ($item['path'] ?? '') : (string) $item;
                $isFolder = is_array($item) ? (!empty($item['is_folder'])) : str_ends_with($path, '/');

                if ($isFolder) {
                    $res = $this->deleteFolder($path);
                    if ($res['success']) {
                        $deletedFolders++;
                    } else {
                        $failed[] = $path;
                    }
                } else {
                    $res = $this->deleteFile($path);
                    if ($res['success']) {
                        $deletedFiles++;
                    } else {
                        $failed[] = $path;
                    }
                }
            }

            $total = $deletedFiles + $deletedFolders;
            return [
                'success' => $total > 0 || empty($failed),
                'message' => "Deleted {$deletedFiles} file(s) and {$deletedFolders} folder(s)." . (!empty($failed) ? " " . count($failed) . " failed." : ""),
                'deleted_files' => $deletedFiles,
                'deleted_folders' => $deletedFolders,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            Log::error('S3Service bulkDelete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Bulk delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * Helper to safely copy a file on S3 with get/put stream fallback.
     */
    private function copyFileWithFallback(string $sourceKey, string $targetKey): bool
    {
        try {
            if ($this->disk->copy($sourceKey, $targetKey)) {
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning("S3 direct copy failed for {$sourceKey} -> {$targetKey}: " . $e->getMessage());
        }

        // Fallback: Read content from source and write to target
        try {
            $content = $this->disk->get($sourceKey);
            if ($content !== false && $content !== null) {
                return (bool) $this->disk->put($targetKey, $content);
            }
        } catch (\Throwable $exFallback) {
            Log::error("S3 get/put fallback copy failed for {$sourceKey} -> {$targetKey}: " . $exFallback->getMessage());
        }

        return false;
    }
}

