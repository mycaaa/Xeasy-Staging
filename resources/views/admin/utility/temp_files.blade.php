@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Directory Selector Tabs -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $directory == 'temp' ? 'active' : '' }}"
                                    href="{{ route('temp_files', ['directory' => 'temp']) }}">
                                    <i class="bx bx-folder"></i> Temp Directory
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $directory == 'uploads' ? 'active' : '' }}"
                                    href="{{ route('temp_files', ['directory' => 'uploads']) }}">
                                    <i class="bx bx-folder-open"></i> Uploads Directory
                                </a>
                            </li>
                        </ul>

                        <!-- Search and Filter Bar -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchInput"
                                        placeholder="Search files by name, extension, or size..." aria-label="Search files by name, extension, or size">
                                </div>
                            </div>
                            <div class="col-md-8 text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <button type="button" class="btn btn-info btn-sm" onclick="location.reload()">
                                        <i class="bx bx-refresh"></i> Refresh
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="deleteAllBtn"
                                        {{ $totalFiles == 0 ? 'disabled' : '' }}>
                                        <i class="bx bx-trash"></i> Delete All
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div class="d-flex gap-3 align-items-center mb-3 flex-wrap">
                            <div class="text-muted">
                                <i class="bx bx-file"></i> Total Files: <strong id="totalFilesCount">{{ $totalFiles }}</strong>
                            </div>
                            <div class="text-muted">
                                <i class="bx bx-data"></i> Total Size: <strong>{{ $totalSizeFormatted }}</strong>
                            </div>
                            <div class="text-muted d-none" id="searchResultInfo">
                                <i class="bx bx-filter"></i> Showing: <strong id="filteredCount">{{ $totalFiles }}</strong> file(s)
                            </div>
                        </div>

                        <!-- Bulk Actions Toolbar (Hidden by default) -->
                        <div class="alert alert-info d-none mb-3" id="bulkActionsToolbar">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><span id="selectedCount">0</span> file(s) selected</strong>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success btn-sm" id="bulkDownloadBtn">
                                        <i class="bx bx-download"></i> Download Selected
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn">
                                        <i class="bx bx-trash"></i> Delete Selected
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" id="clearSelectionBtn">
                                        <i class="bx bx-x"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive table-card">
                            <table class="table align-middle" id="data-table">
                                <thead>
                                    <tr>
                                        <th width="50" class="text-center">
                                            <input type="checkbox" id="checkAll" class="form-check-input">
                                        </th>
                                        <th>S.No</th>
                                        <th>File</th>
                                        <th>Created At</th>
                                        <th>Size</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="filesTableBody">
                                    @forelse ($files as $index => $file)
                                    <tr class="file-row"
                                        data-filename="{{ strtolower($file['name']) }}"
                                        data-extension="{{ strtolower($file['extension'] ?? '') }}"
                                        data-size="{{ strtolower($file['size']) }}">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input file-checkbox"
                                                value="{{ $file['name'] }}" data-size="{{ $file['size_bytes'] }}">
                                        </td>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bx bx-file-blank text-primary"></i>
                                                <span class="file-name">{{ $file['name'] }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $file['created'] }}</td>
                                        <td class="text-center">{{ $file['size'] }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ strtoupper($file['extension'] ?? 'N/A') }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('temp-files.download', $file['name']) }}?directory={{ $directory }}"
                                                    class="btn btn-primary btn-sm" title="Download">
                                                    <i class="bx bx-download"></i>
                                                </a>
                                                <form action="{{ route('temp-files.delete') }}" method="POST"
                                                    style="display:inline;" class="delete-single-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="filename" value="{{ $file['name'] }}">
                                                    <input type="hidden" name="directory" value="{{ $directory }}">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr id="noFilesRow">
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bx bx-inbox" style="font-size: 48px;"></i>
                                                <p class="mt-2 mb-0">No files found in {{ $directory }} directory</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Form -->
    <form id="bulkDeleteForm" action="{{ route('temp-files.bulk-delete') }}" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="directory" value="{{ $directory }}">
        <div id="bulkDeleteFilenamesContainer"></div>
    </form>

    <!-- Bulk Download Form -->
    <form id="bulkDownloadForm" action="{{ route('temp-files.bulk-download') }}" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="directory" value="{{ $directory }}">
        <div id="bulkDownloadFilenamesContainer"></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        const currentDirectory = '{{ $directory }}';

        // Search functionality
        $('#searchInput').on('keyup', function() {
            filterFiles();
        });

        $('#clearSearchBtn').on('click', function() {
            $('#searchInput').val('');
            filterFiles();
        });

        function filterFiles() {
            const searchTerm = $('#searchInput').val().toLowerCase().trim();
            let visibleCount = 0;

            // Hide the "no files" row if it exists (for empty directory)
            $('#noFilesRow').hide();

            $('.file-row').each(function() {
                const row = $(this);
                const filename = row.data('filename') || '';
                const extension = row.data('extension') || '';
                const size = row.data('size') || '';

                if (searchTerm === '') {
                    row.show();
                    visibleCount++;
                } else {
                    // Search in filename, extension, or size
                    if (filename.includes(searchTerm) ||
                        extension.includes(searchTerm) ||
                        size.includes(searchTerm)) {
                        row.show();
                        visibleCount++;
                    } else {
                        row.hide();
                    }
                }
            });

            // Update filtered count
            if (searchTerm !== '') {
                $('#searchResultInfo').removeClass('d-none');
                $('#filteredCount').text(visibleCount);
            } else {
                $('#searchResultInfo').addClass('d-none');
                // Show noFilesRow again if no files exist and no search
                if ($('.file-row').length === 0) {
                    $('#noFilesRow').show();
                }
            }

            // Show/hide no results message
            if (visibleCount === 0 && searchTerm !== '') {
                if ($('#noResultsRow').length === 0) {
                    $('#filesTableBody').append(
                        '<tr id="noResultsRow">' +
                        '<td colspan="7" class="text-center py-4">' +
                        '<div class="text-muted">' +
                        '<i class="bx bx-search" style="font-size: 48px;"></i>' +
                        '<p class="mt-2 mb-0">No files match your search: "' + searchTerm + '"</p>' +
                        '</div>' +
                        '</td>' +
                        '</tr>'
                    );
                }
            } else {
                $('#noResultsRow').remove();
            }

            // Update checkboxes after filtering
            updateCheckAllState();
            updateBulkActions();
        }

        // Select/Deselect All functionality
        $('#checkAll').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.file-checkbox:visible').prop('checked', isChecked);
            updateBulkActions();
        });

        // Individual checkbox change
        $(document).on('change', '.file-checkbox', function() {
            updateCheckAllState();
            updateBulkActions();
        });

        // Update check all checkbox state
        function updateCheckAllState() {
            const visibleCheckboxes = $('.file-checkbox:visible');
            const totalCheckboxes = visibleCheckboxes.length;
            const checkedCheckboxes = visibleCheckboxes.filter(':checked').length;
            $('#checkAll').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
        }

        // Update bulk actions toolbar
        function updateBulkActions() {
            const selectedFiles = $('.file-checkbox:checked');
            const count = selectedFiles.length;
            const selectedSize = selectedFiles.toArray().reduce((total, checkbox) => {
                return total + parseInt($(checkbox).data('size') || 0);
            }, 0);

            $('#selectedCount').text(count);

            if (count > 0) {
                $('#bulkActionsToolbar').removeClass('d-none');
                $('#bulkDeleteBtn, #bulkDownloadBtn').prop('disabled', false);
            } else {
                $('#bulkActionsToolbar').addClass('d-none');
                $('#bulkDeleteBtn, #bulkDownloadBtn').prop('disabled', true);
            }
        }

        // Clear selection
        $('#clearSelectionBtn').on('click', function() {
            $('.file-checkbox, #checkAll').prop('checked', false);
            updateBulkActions();
        });

        // Bulk Delete
        $('#bulkDeleteBtn').on('click', function() {
            const selectedFiles = $('.file-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedFiles.length === 0) {
                toastr.warning('Please select at least one file to delete.');
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete ${selectedFiles.length} file(s). This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete them!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Clear previous inputs
                    $('#bulkDeleteFilenamesContainer').empty();
                    // Add hidden inputs for each filename
                    selectedFiles.forEach(function(filename) {
                        $('#bulkDeleteFilenamesContainer').append(
                            $('<input>', {
                                type: 'hidden',
                                name: 'filenames[]',
                                value: filename
                            })
                        );
                    });
                    $('#bulkDeleteForm').submit();
                }
            });
        });

        // Bulk Download
        $('#bulkDownloadBtn').on('click', function() {
            const selectedFiles = $('.file-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedFiles.length === 0) {
                toastr.warning('Please select at least one file to download.');
                return;
            }

            // Clear previous inputs
            $('#bulkDownloadFilenamesContainer').empty();
            // Add hidden inputs for each filename
            selectedFiles.forEach(function(filename) {
                $('#bulkDownloadFilenamesContainer').append(
                    $('<input>', {
                        type: 'hidden',
                        name: 'filenames[]',
                        value: filename
                    })
                );
            });
            $('#bulkDownloadForm').submit();
        });

        // Delete All
        $('#deleteAllBtn').on('click', function() {
            const totalFiles = {{ $totalFiles }};

            Swal.fire({
                title: 'Delete All Files?',
                text: `You are about to delete all ${totalFiles} file(s) from ${currentDirectory} directory. This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete all!',
                cancelButtonText: 'Cancel',
                input: 'checkbox',
                inputPlaceholder: 'I understand this action is irreversible'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Create a form and submit it
                    const form = $('<form>', {
                        'method': 'POST',
                        'action': '{{ route('temp-files.delete-all') }}'
                    });
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': '{{ csrf_token() }}'
                    }));
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'confirm',
                        'value': '1'
                    }));
                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'directory',
                        'value': currentDirectory
                    }));
                    $('body').append(form);
                    form.submit();
                } else if (result.isConfirmed && !result.value) {
                    toastr.warning('Please confirm that you understand this action is irreversible.');
                }
            });
        });

        // Single delete confirmation
        $(document).on('submit', '.delete-single-form', function(e) {
            e.preventDefault();
            const form = $(this);
            const filename = form.find('input[name="filename"]').val();

            Swal.fire({
                title: 'Delete File?',
                text: `Are you sure you want to delete "${filename}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.off('submit').submit();
                }
            });
        });

        // Initialize
        updateBulkActions();
    });
</script>
@endpush