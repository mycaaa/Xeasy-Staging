# UtilityController Documentation

## Overview

The `UtilityController` provides file management utilities for the Xeasy expense management system. It handles temporary file operations including viewing, downloading, deleting, and bulk operations for files stored in designated directories. This controller is essential for managing uploaded files and temporary storage cleanup.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/UtilityController.php`

## Dependencies

- `Illuminate\Support\Facades\Storage` - File storage operations

## Methods

### temp_files(Request $request)

**Purpose**: Displays the temporary files management interface with file listing and statistics.

**Route**: `GET /admin/utility/temp-files`

**Parameters**:
- `directory` (optional) - Directory to view (temp/uploads, defaults to temp)

**Returns**: View `admin.utility.temp_files` with file data and statistics

**Business Logic**:
1. **Directory Validation**: Ensures only allowed directories are accessed
2. **Directory Creation**: Creates directory if it doesn't exist
3. **File Processing**: Retrieves file information including size, date, and extension
4. **Statistics Calculation**: Computes total files, total size, and formatted sizes
5. **Sorting**: Orders files by creation date (newest first)

**File Information Structure**:
```php
[
    "name" => $filename,
    "created" => "2024-01-15 10:30:00",
    "size" => "1.5 MB",
    "size_bytes" => 1572864,
    "path" => "temp/filename.pdf",
    "extension" => "pdf"
]
```

**Frontend Integration**:
```javascript
// Initialize file management interface
function initializeFileManagement() {
    initializeFileSelection();
    initializeBulkActions();
    initializeFilePreview();
}

// File selection functionality
function initializeFileSelection() {
    const selectAllCheckbox = document.getElementById('select-all');
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    
    selectAllCheckbox.addEventListener('change', function() {
        fileCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActionButtons();
    });
    
    fileCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkActionButtons();
            updateSelectAllState();
        });
    });
}

// Update bulk action button states
function updateBulkActionButtons() {
    const selectedFiles = document.querySelectorAll('.file-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const bulkDownloadBtn = document.getElementById('bulk-download-btn');
    
    const hasSelection = selectedFiles.length > 0;
    bulkDeleteBtn.disabled = !hasSelection;
    bulkDownloadBtn.disabled = !hasSelection;
    
    if (hasSelection) {
        bulkDeleteBtn.textContent = `Delete Selected (${selectedFiles.length})`;
        bulkDownloadBtn.textContent = `Download Selected (${selectedFiles.length})`;
    } else {
        bulkDeleteBtn.textContent = 'Delete Selected';
        bulkDownloadBtn.textContent = 'Download Selected';
    }
}
```
---

### delete(Request $request)

**Purpose**: Deletes a single file from the specified directory.

**Route**: `POST /admin/utility/delete`

**Parameters**:
- `filename` (required) - Name of file to delete
- `directory` (optional) - Directory containing the file (temp/uploads)

**Returns**: Redirect with success/error message

**Business Logic**:
1. **Directory Validation**: Validates and sanitizes directory parameter
2. **File Existence Check**: Verifies file exists before deletion
3. **File Deletion**: Removes file from storage
4. **Response Handling**: Redirects with appropriate success/error message

**Frontend Integration**:
```javascript
// Delete single file
function deleteFile(filename, directory = 'temp') {
    const confirmDelete = confirm(`Are you sure you want to delete "${filename}"?`);
    
    if (!confirmDelete) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/utility/delete';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfInput);
    
    // Add filename
    const filenameInput = document.createElement('input');
    filenameInput.type = 'hidden';
    filenameInput.name = 'filename';
    filenameInput.value = filename;
    form.appendChild(filenameInput);
    
    // Add directory
    const directoryInput = document.createElement('input');
    directoryInput.type = 'hidden';
    directoryInput.name = 'directory';
    directoryInput.value = directory;
    form.appendChild(directoryInput);
    
    document.body.appendChild(form);
    form.submit();
}
```

---

### bulkDelete(Request $request)

**Purpose**: Deletes multiple selected files in a single operation.

**Route**: `POST /admin/utility/bulk-delete`

**Parameters**:
- `filenames` (required|array) - Array of filenames to delete
- `directory` (optional) - Directory containing the files

**Returns**: Redirect with operation summary

**Business Logic**:
1. **Input Validation**: Validates filenames array and directory parameter
2. **Batch Processing**: Iterates through files and attempts deletion
3. **Success Tracking**: Counts successful and failed deletions
4. **Summary Response**: Provides detailed operation summary

**Validation Rules**:
```php
$request->validate([
    'filenames' => 'required|array',
    'filenames.*' => 'string',
    'directory' => 'nullable|string|in:temp,uploads',
]);
```

**Frontend Integration**:
```javascript
// Bulk delete selected files
function bulkDeleteFiles() {
    const selectedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select files to delete');
        return;
    }
    
    const filenames = Array.from(selectedCheckboxes).map(cb => cb.value);
    const confirmMessage = `Are you sure you want to delete ${filenames.length} selected file(s)?`;
    
    if (!confirm(confirmMessage)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/utility/bulk-delete';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfInput);
    
    // Add filenames
    filenames.forEach(filename => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filenames[]';
        input.value = filename;
        form.appendChild(input);
    });
    
    // Add directory
    const directoryInput = document.createElement('input');
    directoryInput.type = 'hidden';
    directoryInput.name = 'directory';
    directoryInput.value = getCurrentDirectory();
    form.appendChild(directoryInput);
    
    document.body.appendChild(form);
    form.submit();
}
```

---

### deleteAll(Request $request)

**Purpose**: Deletes all files in the specified directory with confirmation.

**Route**: `POST /admin/utility/delete-all`

**Parameters**:
- `confirm` (required) - Confirmation flag (must be "1")
- `directory` (optional) - Directory to clear

**Returns**: Redirect with deletion summary

**Business Logic**:
1. **Confirmation Validation**: Ensures user has confirmed the action
2. **Directory Processing**: Gets all files in the specified directory
3. **Mass Deletion**: Deletes all files in the directory
4. **Count Tracking**: Tracks number of files deleted

**Frontend Integration**:
```javascript
// Delete all files with confirmation
function deleteAllFiles() {
    const directory = getCurrentDirectory();
    const fileCount = document.querySelectorAll('.file-row').length;
    
    if (fileCount === 0) {
        alert('No files to delete');
        return;
    }
    
    const confirmMessage = `Are you sure you want to delete ALL ${fileCount} files in the ${directory} directory? This action cannot be undone.`;
    
    if (!confirm(confirmMessage)) return;
    
    // Double confirmation for safety
    const doubleConfirm = confirm('This will permanently delete all files. Are you absolutely sure?');
    if (!doubleConfirm) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/utility/delete-all';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfInput);
    
    // Add confirmation
    const confirmInput = document.createElement('input');
    confirmInput.type = 'hidden';
    confirmInput.name = 'confirm';
    confirmInput.value = '1';
    form.appendChild(confirmInput);
    
    // Add directory
    const directoryInput = document.createElement('input');
    directoryInput.type = 'hidden';
    directoryInput.name = 'directory';
    directoryInput.value = directory;
    form.appendChild(directoryInput);
    
    document.body.appendChild(form);
    form.submit();
}
```

---

### download(Request $request, $filename)

**Purpose**: Downloads a single file from the specified directory.

**Route**: `GET /admin/utility/download/{filename}`

**Parameters**:
- `filename` (required) - Name of file to download
- `directory` (optional) - Directory containing the file

**Returns**: File download response or redirect with error

**Business Logic**:
1. **File Path Construction**: Builds complete file path
2. **Existence Verification**: Checks if file exists
3. **Download Response**: Serves file for download with original filename
4. **Error Handling**: Redirects with error if file not found

---

### bulkDownload(Request $request)

**Purpose**: Downloads multiple files as a ZIP archive or single file if only one selected.

**Route**: `POST /admin/utility/bulk-download`

**Parameters**:
- `filenames` (required|array|min:1) - Array of filenames to download
- `directory` (optional) - Directory containing the files

**Returns**: File download response or redirect with error

**Business Logic**:
1. **Single File Optimization**: Downloads single file directly if only one selected
2. **ZIP Creation**: Creates ZIP archive for multiple files
3. **File Addition**: Adds existing files to ZIP archive
4. **Cleanup**: Automatically deletes ZIP file after download

**ZIP Archive Creation**:
```php
$zip = new \ZipArchive();
$zipFileName = $directory . "_files_" . time() . ".zip";
$zipPath = Storage::disk("public")->path($directoryPath . "/" . $zipFileName);

if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
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
```

**Frontend Integration**:
```javascript
// Bulk download selected files
function bulkDownloadFiles() {
    const selectedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select files to download');
        return;
    }
    
    const filenames = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/utility/bulk-download';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfInput);
    
    // Add filenames
    filenames.forEach(filename => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filenames[]';
        input.value = filename;
        form.appendChild(input);
    });
    
    // Add directory
    const directoryInput = document.createElement('input');
    directoryInput.type = 'hidden';
    directoryInput.name = 'directory';
    directoryInput.value = getCurrentDirectory();
    form.appendChild(directoryInput);
    
    document.body.appendChild(form);
    form.submit();
}
```
## Helper Methods

### getDirectoryPath(string $directory)

**Purpose**: Validates and sanitizes directory parameter to prevent unauthorized access.

**Parameters**:
- `directory` (string) - Directory name to validate

**Returns**: Validated directory name

**Security Logic**:
```php
private function getDirectoryPath(string $directory): string
{
    $allowedDirectories = ["temp", "uploads"];
    $directory = in_array($directory, $allowedDirectories)
        ? $directory
        : "temp";

    return $directory;
}
```

---

### formatSize($size)

**Purpose**: Formats file size in bytes to human-readable format.

**Parameters**:
- `size` (int) - File size in bytes

**Returns**: Formatted size string (B, KB, MB, GB)

**Formatting Logic**:
```php
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
```

## Security Considerations

### Directory Access Control
- **Whitelist Validation**: Only allows access to predefined directories (temp, uploads)
- **Path Traversal Prevention**: Prevents access to unauthorized directories
- **Default Fallback**: Falls back to safe default directory if invalid directory specified

### File Operation Security
- **Existence Verification**: Always checks file existence before operations
- **Safe File Handling**: Uses Laravel's Storage facade for secure file operations
- **Input Validation**: Validates all file operation parameters

### Download Security
- **Filename Sanitization**: Ensures safe filename handling
- **Temporary File Cleanup**: Automatically cleans up temporary ZIP files
- **Access Control**: Restricts access to authorized directories only

## Performance Optimizations

### File Processing
```php
// Efficient file information collection
$files = collect(Storage::disk("public")->files($directoryPath))
    ->map(function ($filePath) {
        // Process file information
        return [
            "name" => basename($filePath),
            "created" => date("Y-m-d H:i:s", Storage::disk("public")->lastModified($filePath)),
            "size" => $this->formatSize(Storage::disk("public")->size($filePath)),
            // ... other properties
        ];
    })
    ->sortByDesc("created")
    ->values();
```

### Memory Management
- **Streaming Downloads**: Uses Laravel's download response for efficient file serving
- **Batch Processing**: Processes files in batches for bulk operations
- **Resource Cleanup**: Automatically cleans up temporary resources

### Caching Strategy
- **File Metadata Caching**: Could cache file metadata for frequently accessed directories
- **Directory Listing Caching**: Could cache directory listings for better performance

## Configuration

### Storage Configuration
```php
// config/filesystems.php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
],
```

### Directory Structure
```
storage/app/public/
├── temp/           # Temporary files
├── uploads/        # Uploaded files
└── ...
```

## Integration Examples

### File Upload Integration
```php
// Integration with file upload functionality
class FileUploadService
{
    public function uploadToTemp($file)
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('temp', $filename, 'public');
        
        return [
            'filename' => $filename,
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType()
        ];
    }
    
    public function moveFromTempToUploads($filename)
    {
        $tempPath = 'temp/' . $filename;
        $uploadPath = 'uploads/' . $filename;
        
        if (Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->move($tempPath, $uploadPath);
            return true;
        }
        
        return false;
    }
}
```

### Cleanup Service Integration
```php
// Automated cleanup service
class FileCleanupService
{
    public function cleanupOldTempFiles($olderThanDays = 7)
    {
        $utilityController = new UtilityController();
        $cutoffTime = now()->subDays($olderThanDays)->timestamp;
        
        $files = Storage::disk('public')->files('temp');
        $deletedCount = 0;
        
        foreach ($files as $filePath) {
            $lastModified = Storage::disk('public')->lastModified($filePath);
            
            if ($lastModified < $cutoffTime) {
                Storage::disk('public')->delete($filePath);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // File operations
    Storage::disk('public')->delete($filePath);
} catch (\Exception $e) {
    Log::error('Utility Controller File Operation Error', [
        'method' => 'delete',
        'file_path' => $filePath,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return redirect()->back()->with('error', 'Failed to delete file: ' . $e->getMessage());
}
```

### Monitoring and Alerts
- **Storage Usage Monitoring**: Track storage usage and alert when limits approached
- **File Operation Tracking**: Monitor file operations for unusual patterns
- **Performance Monitoring**: Track file operation performance and response times

## Frontend Complete Integration

### Complete JavaScript Implementation
```javascript
class FileManager {
    constructor() {
        this.selectedFiles = new Set();
        this.currentDirectory = 'temp';
        this.init();
    }
    
    init() {
        this.initializeEventListeners();
        this.updateUI();
    }
    
    initializeEventListeners() {
        // Select all functionality
        document.getElementById('select-all')?.addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });
        
        // Individual file selection
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.toggleFileSelection(e.target.value, e.target.checked);
            });
        });
        
        // Bulk action buttons
        document.getElementById('bulk-delete-btn')?.addEventListener('click', () => {
            this.bulkDelete();
        });
        
        document.getElementById('bulk-download-btn')?.addEventListener('click', () => {
            this.bulkDownload();
        });
        
        document.getElementById('delete-all-btn')?.addEventListener('click', () => {
            this.deleteAll();
        });
    }
    
    toggleSelectAll(checked) {
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
            this.toggleFileSelection(checkbox.value, checked);
        });
    }
    
    toggleFileSelection(filename, selected) {
        if (selected) {
            this.selectedFiles.add(filename);
        } else {
            this.selectedFiles.delete(filename);
        }
        this.updateUI();
    }
    
    updateUI() {
        const selectedCount = this.selectedFiles.size;
        const totalFiles = document.querySelectorAll('.file-checkbox').length;
        
        // Update bulk action buttons
        const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
        const bulkDownloadBtn = document.getElementById('bulk-download-btn');
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = selectedCount === 0;
            bulkDeleteBtn.textContent = selectedCount > 0 
                ? `Delete Selected (${selectedCount})` 
                : 'Delete Selected';
        }
        
        if (bulkDownloadBtn) {
            bulkDownloadBtn.disabled = selectedCount === 0;
            bulkDownloadBtn.textContent = selectedCount > 0 
                ? `Download Selected (${selectedCount})` 
                : 'Download Selected';
        }
        
        // Update select all checkbox state
        const selectAllCheckbox = document.getElementById('select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalFiles;
            selectAllCheckbox.checked = selectedCount === totalFiles && totalFiles > 0;
        }
    }
    
    bulkDelete() {
        if (this.selectedFiles.size === 0) return;
        
        const confirmMessage = `Are you sure you want to delete ${this.selectedFiles.size} selected file(s)?`;
        if (!confirm(confirmMessage)) return;
        
        this.submitForm('/admin/utility/bulk-delete', {
            filenames: Array.from(this.selectedFiles),
            directory: this.currentDirectory
        });
    }
    
    bulkDownload() {
        if (this.selectedFiles.size === 0) return;
        
        this.submitForm('/admin/utility/bulk-download', {
            filenames: Array.from(this.selectedFiles),
            directory: this.currentDirectory
        });
    }
    
    deleteAll() {
        const fileCount = document.querySelectorAll('.file-row').length;
        if (fileCount === 0) {
            alert('No files to delete');
            return;
        }
        
        const confirmMessage = `Are you sure you want to delete ALL ${fileCount} files? This action cannot be undone.`;
        if (!confirm(confirmMessage)) return;
        
        const doubleConfirm = confirm('This will permanently delete all files. Are you absolutely sure?');
        if (!doubleConfirm) return;
        
        this.submitForm('/admin/utility/delete-all', {
            confirm: '1',
            directory: this.currentDirectory
        });
    }
    
    submitForm(action, data) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrfInput);
        
        // Add data
        Object.entries(data).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach(item => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `${key}[]`;
                    input.value = item;
                    form.appendChild(input);
                });
            } else {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize file manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new FileManager();
});
```

This UtilityController provides comprehensive file management functionality, enabling efficient handling of temporary files and uploads while maintaining security, performance, and user-friendly bulk operations throughout the expense management system.