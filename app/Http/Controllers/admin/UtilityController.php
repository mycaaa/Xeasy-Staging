<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UtilityController extends Controller
{
    private function getDirectoryPath(string $directory): string
    {
        $allowedDirectories = ["temp", "uploads"];
        $directory = in_array($directory, $allowedDirectories)
            ? $directory
            : "temp";

        return $directory;
    }

    public function temp_files(
        Request $request
    ): \Illuminate\Contracts\View\View {
        $directory = $request->get("directory", "temp");
        $directoryPath = $this->getDirectoryPath($directory);

        if (!Storage::disk("public")->exists($directoryPath)) {
            Storage::disk("public")->makeDirectory($directoryPath);
        }

        $files = collect(Storage::disk("public")->files($directoryPath))
            ->map(function ($filePath) {
                $filename = basename($filePath);

                return [
                    "name" => $filename,
                    "created" => date(
                        "Y-m-d H:i:s",
                        Storage::disk("public")->lastModified($filePath)
                    ),
                    "size" => $this->formatSize(
                        Storage::disk("public")->size($filePath)
                    ),
                    "size_bytes" => Storage::disk("public")->size($filePath),
                    "path" => $filePath,
                    "extension" => pathinfo($filename, PATHINFO_EXTENSION),
                ];
            })
            ->sortByDesc("created")
            ->values();

        $totalSize = $files->sum("size_bytes");
        $totalFiles = $files->count();
        $totalSizeFormatted = $this->formatSize($totalSize);

        return view(
            "admin.utility.temp_files",
            compact(
                "files",
                "totalSize",
                "totalSizeFormatted",
                "totalFiles",
                "directory"
            )
        );
    }

    public function delete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $directory = $request->get("directory", "temp");
        $directoryPath = $this->getDirectoryPath($directory);

        if ($request->has("filename")) {
            $filePath = $directoryPath . "/" . $request->filename;
            if (Storage::disk("public")->exists($filePath)) {
                Storage::disk("public")->delete($filePath);

                return redirect()
                    ->route("temp_files", ["directory" => $directory])
                    ->with("success", "File deleted successfully.");
            }
        }

        return redirect()
            ->route("temp_files", ["directory" => $directory])
            ->with("error", "File not found.");
    }

    public function bulkDelete(
        Request $request
    ): \Illuminate\Http\RedirectResponse {
        $request->validate([
            "filenames" => "required|array",
            "filenames.*" => "string",
            "directory" => "nullable|string|in:temp,uploads",
        ]);

        $directory = $request->get("directory", "temp");
        $directoryPath = $this->getDirectoryPath($directory);

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($request->filenames as $filename) {
            $filePath = $directoryPath . "/" . $filename;
            if (Storage::disk("public")->exists($filePath)) {
                Storage::disk("public")->delete($filePath);
                $deletedCount++;
            } else {
                $failedCount++;
            }
        }

        if ($deletedCount > 0) {
            $message = "{$deletedCount} file(s) deleted successfully.";
            if ($failedCount > 0) {
                $message .= " {$failedCount} file(s) not found.";
            }

            return redirect()
                ->route("temp_files", ["directory" => $directory])
                ->with("success", $message);
        }

        return redirect()
            ->route("temp_files", ["directory" => $directory])
            ->with("error", "No files were deleted.");
    }

    public function deleteAll(
        Request $request
    ): \Illuminate\Http\RedirectResponse {
        $request->validate([
            "confirm" => "required",
            "directory" => "nullable|string|in:temp,uploads",
        ]);

        if (!$request->has("confirm") || $request->confirm != "1") {
            $directory = $request->get("directory", "temp");

            return redirect()
                ->route("temp_files", ["directory" => $directory])
                ->with("error", "Deletion not confirmed.");
        }

        $directory = $request->get("directory", "temp");
        $directoryPath = $this->getDirectoryPath($directory);
        $files = Storage::disk("public")->files($directoryPath);
        $deletedCount = 0;

        foreach ($files as $filePath) {
            Storage::disk("public")->delete($filePath);
            $deletedCount++;
        }

        return redirect()
            ->route("temp_files", ["directory" => $directory])
            ->with(
                "success",
                "All {$deletedCount} file(s) deleted successfully."
            );
    }

    public function download(
        Request $request,
        $filename
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse {
        $directory = $request->get("directory", "temp");
        $directoryPath = $this->getDirectoryPath($directory);
        $filePath = $directoryPath . "/" . $filename;

        if (Storage::disk("public")->exists($filePath)) {
            $fullPath = Storage::disk("public")->path($filePath);
            return response()->download($fullPath, $filename);
        }

        return redirect()
            ->route("temp_files", ["directory" => $directory])
            ->with("error", "File not found.");
    }

    public function bulkDownload(
        Request $request
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse {
        $request->validate([
            "filenames" => "required|array|min:1",
            "filenames.*" => "string",
            "directory" => "nullable|string|in:temp,uploads",
        ]);

        $directory = $request->get("directory", "temp");
        $directoryPath = $this->getDirectoryPath($directory);

        if (count($request->filenames) === 1) {
            $filePath = $directoryPath . "/" . $request->filenames[0];
            if (Storage::disk("public")->exists($filePath)) {
                $fullPath = Storage::disk("public")->path($filePath);
                return response()->download($fullPath, $request->filenames[0]);
            }

            return redirect()
                ->route("temp_files", ["directory" => $directory])
                ->with("error", "File not found.");
        }

        $zip = new \ZipArchive();
        $zipFileName = $directory . "_files_" . time() . ".zip";
        $zipPath = Storage::disk("public")->path(
            $directoryPath . "/" . $zipFileName
        );

        if (!Storage::disk("public")->exists($directoryPath)) {
            Storage::disk("public")->makeDirectory($directoryPath);
        }

        if (
            $zip->open(
                $zipPath,
                \ZipArchive::CREATE | \ZipArchive::OVERWRITE
            ) === true
        ) {
            foreach ($request->filenames as $filename) {
                $filePath = $directoryPath . "/" . $filename;
                if (Storage::disk("public")->exists($filePath)) {
                    $fullPath = Storage::disk("public")->path($filePath);
                    $zip->addFile($fullPath, $filename);
                }
            }
            $zip->close();

            return response()
                ->download($zipPath, $zipFileName)
                ->deleteFileAfterSend(true);
        }

        return redirect()
            ->route("temp_files", ["directory" => $directory])
            ->with("error", "Failed to create zip archive.");
    }

    private function formatSize($size): string
    {
        if ($size < 1024) {
            return $size . " B";
        } elseif ($size < 1048576) {
            return round($size / 1024, 2) . " KB";
        } elseif ($size < 1073741824) {
            return round($size / 1048576, 2) . " MB";
        } else {
            return round($size / 1073741824, 2) . " GB";
        }
    }
}
