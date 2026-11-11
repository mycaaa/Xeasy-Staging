<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\S3Service;
use Illuminate\Http\Request;

class S3TransferController extends Controller
{
    protected $s3Service;

    public function __construct(S3Service $s3Service)
    {
        $this->s3Service = $s3Service;
    }

    /**
     * Display the S3 Transfer Utility Page.
     */
    public function index(Request $request)
    {
        $bucketName = config('filesystems.disks.s3.bucket') ?: env('AWS_BUCKET', 'developerinvnr.bkt');
        $region = config('filesystems.disks.s3.region') ?: env('AWS_DEFAULT_REGION', 'ap-south-1');

        return view('admin.s3_transfer.index', compact('bucketName', 'region'));
    }

    /**
     * Handle S3 Transfer Request (Single or Batch).
     */
    public function process(Request $request)
    {
        $mode = $request->input('mode', 'single');
        $actionType = $request->input('action_type', 'copy');
        $isMove = ($actionType === 'move');

        if ($mode === 'batch') {
            $request->validate([
                'batch_text' => 'required|string',
            ]);

            $lines = explode("\n", str_replace("\r", "", $request->input('batch_text')));
            $results = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Format can be: "source => target" OR "source | target" OR "source,target" OR space separated
                $parts = [];
                if (str_contains($line, '=>')) {
                    $parts = explode('=>', $line, 2);
                } elseif (str_contains($line, '|')) {
                    $parts = explode('|', $line, 2);
                } elseif (str_contains($line, ',')) {
                    $parts = explode(',', $line, 2);
                } else {
                    // Try splitting by whitespace if 2 URLs present
                    $parts = preg_split('/\s+/', $line, 2);
                }

                if (count($parts) === 2) {
                    $src = trim($parts[0]);
                    $tgt = trim($parts[1]);
                    $res = $this->s3Service->transferPath($src, $tgt, $isMove);
                    $results[] = [
                        'line' => $line,
                        'source' => $src,
                        'target' => $tgt,
                        'result' => $res,
                    ];

                    if (!empty($res['success'])) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                } else {
                    $results[] = [
                        'line' => $line,
                        'source' => $line,
                        'target' => '',
                        'result' => ['success' => false, 'message' => 'Invalid line format. Expected "Source => Target".'],
                    ];
                    $failCount++;
                }
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'mode' => 'batch',
                    'success_count' => $successCount,
                    'fail_count' => $failCount,
                    'results' => $results,
                ]);
            }

            return back()->with('batch_results', [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ]);
        }

        // Single File or Folder Transfer Mode
        $request->validate([
            'source_path' => 'required|string',
            'target_path' => 'required|string',
        ]);

        $source = $request->input('source_path');
        $target = $request->input('target_path');

        $result = $this->s3Service->transferPath($source, $target, $isMove);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return back()->with('success_result', $result);
        }

        return back()->withInput()->with('error_message', $result['message']);
    }

    /**
     * AJAX Endpoint for Interactive S3 Explorer with Chunk / Pagination Loading.
     */
    public function explore(Request $request)
    {
        $prefix = (string) ($request->input('prefix') ?? '');
        $limit = (int) ($request->input('limit', 50));
        $continuationToken = $request->input('continuation_token');
        $forceRefresh = filter_var($request->input('refresh', false), FILTER_VALIDATE_BOOLEAN);

        $data = $this->s3Service->listContentsPaginated($prefix, $limit, $continuationToken, $forceRefresh);

        return response()->json($data);
    }

    /**
     * AJAX Endpoint: Delete File or Folder.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'is_folder' => 'nullable|boolean',
        ]);

        $path = $request->input('path');
        $isFolder = filter_var($request->input('is_folder', false), FILTER_VALIDATE_BOOLEAN);

        if ($isFolder) {
            $result = $this->s3Service->deleteFolder($path);
        } else {
            $result = $this->s3Service->deleteFile($path);
        }

        return response()->json($result);
    }

    /**
     * AJAX Endpoint: Rename File or Folder.
     */
    public function rename(Request $request)
    {
        $request->validate([
            'old_path' => 'required|string',
            'new_name' => 'required|string',
            'is_folder' => 'nullable|boolean',
        ]);

        $oldPath = $request->input('old_path');
        $newName = $request->input('new_name');
        $isFolder = filter_var($request->input('is_folder', false), FILTER_VALIDATE_BOOLEAN);

        $result = $this->s3Service->renameItem($oldPath, $newName, $isFolder);

        return response()->json($result);
    }

    /**
     * AJAX Endpoint: Create New Folder.
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'prefix' => 'nullable|string',
            'folder_name' => 'required|string|max:100',
        ]);

        $prefix = (string) ($request->input('prefix') ?? '');
        $folderName = $request->input('folder_name');

        $result = $this->s3Service->createFolder($prefix, $folderName);

        return response()->json($result);
    }

    /**
     * AJAX Endpoint: Upload Files to Current S3 Folder.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'prefix' => 'nullable|string',
            'files' => 'required|array',
            'files.*' => 'file|max:51200', // Max 50MB per file
        ]);

        $prefix = (string) ($request->input('prefix') ?? '');
        $files = $request->file('files', []);

        $result = $this->s3Service->uploadToPrefix($prefix, $files);

        return response()->json($result);
    }

    /**
     * AJAX Endpoint: Bulk Delete Files and Folders.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
        ]);

        $items = $request->input('items', []);
        $result = $this->s3Service->bulkDelete($items);

        return response()->json($result);
    }
}
