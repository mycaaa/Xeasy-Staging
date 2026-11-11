@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                    <h4 class="mb-sm-0 text-primary font-size-18">
                        <i class="ri-cloud-transfer-line align-middle me-1"></i> AWS S3 File & Folder Transfer Utility
                    </h4>
                    <div class="page-title-right d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary fs-12 px-3 py-2 border border-primary-subtle rounded-pill">
                            <i class="ri-database-2-line align-middle me-1"></i> Bucket: <strong>{{ $bucketName }}</strong>
                        </span>
                        <span class="badge bg-info-subtle text-info fs-12 px-3 py-2 border border-info-subtle rounded-pill">
                            <i class="ri-earth-line align-middle me-1"></i> Region: <strong>{{ $region }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        @if(session('error_message'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ri-error-warning-line me-2 align-middle fs-16"></i> {{ session('error_message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('success_result'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ri-checkbox-circle-line me-2 align-middle fs-16"></i> {{ session('success_result')['message'] }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Main Card with Navigation Tabs -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light-subtle border-bottom">
                <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0" id="s3Tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" id="single-transfer-tab" data-bs-toggle="tab" href="#single-transfer" role="tab">
                            <i class="ri-swap-line me-1 align-middle"></i> Quick Transfer (File or Folder)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" id="batch-transfer-tab" data-bs-toggle="tab" href="#batch-transfer" role="tab">
                            <i class="ri-list-check-2 me-1 align-middle"></i> Bulk / Batch Transfer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" id="explorer-tab" data-bs-toggle="tab" href="#s3-explorer" role="tab" onclick="loadS3Explorer('')">
                            <i class="ri-folder-open-line me-1 align-middle"></i> S3 Folder Explorer
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content text-muted">
                    
                    <!-- TAB 1: QUICK TRANSFER -->
                    <div class="tab-pane active" id="single-transfer" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <form id="singleTransferForm" method="POST" action="{{ route('admin.s3-transfer.process') }}">
                                    @csrf
                                    <input type="hidden" name="mode" value="single">

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label for="source_path" class="form-label fw-bold text-dark">
                                                Source S3 URL or Key Path <span class="text-danger">*</span>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnFillExample">
                                                <i class="ri-magic-line me-1"></i> Fill Example Paths
                                            </button>
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted"><i class="ri-upload-cloud-2-line"></i></span>
                                            <input type="text" class="form-label form-control font-monospace" id="source_path" name="source_path" 
                                                placeholder="e.g. https://s3.ap-south-1.amazonaws.com/developerinvnr.bkt/Expense/6/101574/Img_101574_120826125710_1.jpg OR Expense/6/101574/" required>
                                        </div>
                                        <small class="form-text text-muted">
                                            You can paste full S3 HTTPS URL or relative key path (e.g. <code>Expense/6/101574/</code> or a single file path).
                                        </small>
                                    </div>

                                    <div class="mb-4">
                                        <label for="target_path" class="form-label fw-bold text-dark">
                                            Destination S3 URL or Key Path <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted"><i class="ri-download-cloud-2-line"></i></span>
                                            <input type="text" class="form-control font-monospace" id="target_path" name="target_path" 
                                                placeholder="e.g. https://s3.ap-south-1.amazonaws.com/developerinvnr.bkt/VVNR_Expense/6/101768/ClsImg_101768_140826101000914.jpg OR VVNR_Expense/6/101768/" required>
                                        </div>
                                        <small class="form-text text-muted">
                                            Destination can be a new filename or a destination folder prefix like <code>VVNR_Expense/6/101768/</code>.
                                        </small>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-dark">Transfer Action</label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check form-radio-primary">
                                                    <input class="form-check-input" type="radio" name="action_type" id="action_copy" value="copy" checked>
                                                    <label class="form-check-label fw-semibold" for="action_copy">
                                                        <i class="ri-file-copy-line text-primary me-1"></i> Copy (Keep original file)
                                                    </label>
                                                </div>
                                                <div class="form-check form-radio-danger">
                                                    <input class="form-check-input" type="radio" name="action_type" id="action_move" value="move">
                                                    <label class="form-check-label fw-semibold text-danger" for="action_move">
                                                        <i class="ri-scissors-cut-line me-1"></i> Move (Delete original after copy)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary px-4 py-2" id="btnSubmitTransfer">
                                            <i class="ri-send-plane-fill me-1 align-middle"></i> Start Transfer Process
                                        </button>
                                        <button type="reset" class="btn btn-light px-3 py-2">
                                            <i class="ri-refresh-line me-1 align-middle"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Right Info Sidebar -->
                            <div class="col-lg-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h5 class="card-title text-dark mb-3">
                                            <i class="ri-information-line text-info me-1"></i> Transfer Guidelines
                                        </h5>
                                        <ul class="list-unstyled mb-0 fs-13 text-secondary">
                                            <li class="mb-2">
                                                <i class="ri-checkbox-circle-fill text-success me-1"></i> <strong>S3 Server-Side Copy:</strong> Transfer operations take place inside AWS infrastructure instantly without downloading data locally.
                                            </li>
                                            <li class="mb-2">
                                                <i class="ri-checkbox-circle-fill text-success me-1"></i> <strong>Single File Copy:</strong> Provide exact file URL/path or destination folder.
                                            </li>
                                            <li class="mb-2">
                                                <i class="ri-checkbox-circle-fill text-success me-1"></i> <strong>Entire Folder Transfer:</strong> If source is a folder (e.g., <code>Expense/6/101574/</code>), all internal files will be recursively copied to destination folder (e.g. <code>VVNR_Expense/6/101768/</code>).
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Result Section -->
                        <div id="transferResultContainer" class="mt-4 d-none">
                            <div class="card border border-success-subtle shadow-none">
                                <div class="card-header bg-success-subtle border-success-subtle d-flex justify-content-between align-items-center">
                                    <h6 class="card-title text-success mb-0">
                                        <i class="ri-checkbox-circle-fill me-1"></i> Transfer Result Summary
                                    </h6>
                                    <span id="resultCountBadge" class="badge bg-success text-white"></span>
                                </div>
                                <div class="card-body">
                                    <p id="resultMessage" class="fw-medium text-dark mb-3"></p>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Source Key</th>
                                                    <th>Destination Key</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="resultFilesTableBody">
                                                <!-- Dynamic Rows -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: BATCH / BULK TRANSFER -->
                    <div class="tab-pane" id="batch-transfer" role="tabpanel">
                        <form id="batchTransferForm" method="POST" action="{{ route('admin.s3-transfer.process') }}">
                            @csrf
                            <input type="hidden" name="mode" value="batch">

                            <div class="mb-3">
                                <label for="batch_text" class="form-label fw-bold text-dark">
                                    Multiple S3 Transfers (One per line) <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control font-monospace" id="batch_text" name="batch_text" rows="8" 
                                    placeholder="Format: Source_URL => Target_URL&#10;Example:&#10;https://s3.ap-south-1.amazonaws.com/developerinvnr.bkt/Expense/6/101574/Img_101574_120826125710_1.jpg => https://s3.ap-south-1.amazonaws.com/developerinvnr.bkt/VVNR_Expense/6/101768/ClsImg_101768_140826101000914.jpg&#10;Expense/6/101574/ => VVNR_Expense/6/101574/" required></textarea>
                                <small class="form-text text-muted">
                                    Supported separators: <code>=&gt;</code> or <code>|</code> or <code>,</code>.
                                </small>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Batch Action</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check form-radio-primary">
                                            <input class="form-check-input" type="radio" name="action_type" id="batch_action_copy" value="copy" checked>
                                            <label class="form-check-label fw-semibold" for="batch_action_copy">Copy Files</label>
                                        </div>
                                        <div class="form-check form-radio-danger">
                                            <input class="form-check-input" type="radio" name="action_type" id="batch_action_move" value="move">
                                            <label class="form-check-label fw-semibold text-danger" for="batch_action_move">Move Files</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success px-4 py-2" id="btnSubmitBatch">
                                <i class="ri-play-list-add-line me-1 align-middle"></i> Execute Batch Transfers
                            </button>
                        </form>

                        <div id="batchResultContainer" class="mt-4 d-none">
                            <div class="card border border-primary-subtle shadow-none">
                                <div class="card-header bg-primary-subtle border-primary-subtle">
                                    <h6 class="card-title text-primary mb-0">Batch Transfer Results</h6>
                                </div>
                                <div class="card-body">
                                    <div id="batchSummaryText" class="mb-3 fw-bold"></div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Line</th>
                                                    <th>Source</th>
                                                    <th>Target</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="batchResultTableBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: S3 EXPLORER -->
                    <div class="tab-pane" id="s3-explorer" role="tabpanel">
                        
                        <!-- Toolbar -->
                        <div class="card bg-light-subtle border mb-3">
                            <div class="card-body p-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <!-- Navigation & Breadcrumbs -->
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary" onclick="loadS3Explorer('')" title="Root Folder">
                                            <i class="ri-home-4-line me-1"></i> Root
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnGoUp" onclick="goUpOneLevel()" title="Go Up One Level">
                                            <i class="ri-arrow-up-line me-1"></i> Up
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="refreshCurrentFolder(true)" title="Force Refresh (Bypass Cache)">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </div>

                                    <span class="text-muted">/</span>

                                    <nav aria-label="breadcrumb">
                                        <ol class="breadcrumb mb-0" id="explorerBreadcrumbs">
                                            <li class="breadcrumb-item active fw-semibold text-primary">Root</li>
                                        </ol>
                                    </nav>
                                </div>

                                <!-- Action Buttons & Search -->
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="text-muted fs-12 fw-semibold">Chunk:</span>
                                        <select id="explorerChunkLimit" class="form-select form-select-sm" style="width: 105px;" onchange="onChunkLimitChange()" title="Items per page / chunk">
                                            <option value="25">25 / chunk</option>
                                            <option value="50" selected>50 / chunk</option>
                                            <option value="100">100 / chunk</option>
                                            <option value="200">200 / chunk</option>
                                        </select>
                                    </div>

                                    <div class="input-group input-group-sm" style="width: 180px;">
                                        <span class="input-group-text bg-white"><i class="ri-filter-3-line"></i></span>
                                        <input type="text" class="form-control" id="explorerFilterTable" placeholder="Filter in view..." aria-label="Filter in view">
                                    </div>

                                    <div class="input-group input-group-sm" style="width: 220px;">
                                        <input type="text" class="form-control font-monospace" id="explorerSearchPrefix" placeholder="Go to path (e.g. Expense/)" aria-label="Go to path (e.g. Expense/)">
                                        <button type="button" class="btn btn-primary" onclick="loadS3Explorer($('#explorerSearchPrefix').val())">
                                            <i class="ri-arrow-right-line"></i> Go
                                        </button>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-success" onclick="openCreateFolderModal()">
                                        <i class="ri-folder-add-line me-1"></i> New Folder
                                    </button>

                                    <button type="button" class="btn btn-sm btn-primary" onclick="openUploadModal()">
                                        <i class="ri-upload-2-line me-1"></i> Upload Files
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bulk Actions Banner (Hidden until items selected) -->
                        <div id="bulkActionsBar" class="alert alert-warning py-2 px-3 mb-3 d-none align-items-center justify-content-between shadow-sm">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ri-checkbox-circle-fill text-warning fs-18"></i>
                                <span class="fw-semibold text-dark" id="bulkSelectedText">0 items selected</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-danger px-3" onclick="confirmBulkDelete()">
                                    <i class="ri-delete-bin-line me-1"></i> Bulk Delete Selected
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearExplorerSelections()">
                                    <i class="ri-close-line me-1"></i> Clear
                                </button>
                            </div>
                        </div>

                        <!-- Explorer Table -->
                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-hover align-middle mb-0" id="explorerTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 35px;" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="checkAllExplorer">
                                        </th>
                                        <th style="width: 45px;">Type</th>
                                        <th>Name / Key Path</th>
                                        <th style="width: 120px;">Size</th>
                                        <th style="width: 180px;">Last Modified</th>
                                        <th style="width: 250px;" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="explorerTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading S3 Explorer...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination / Chunk Loading Bar -->
                        <div id="explorerPaginationBar" class="card bg-light-subtle border mt-2 mb-2 p-2">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="fs-13 text-secondary" id="explorerChunkStats">
                                    <span id="chunkSummaryBadge" class="badge bg-primary-subtle text-primary fs-12 px-2 py-1">
                                        <i class="ri-folder-info-line me-1"></i> Chunk: <span id="chunkSizeText">50</span> items
                                    </span>
                                    <span class="ms-2" id="chunkStatusText">Showing 0 loaded items.</span>
                                </div>
                                <div class="d-flex align-items-center gap-2" id="chunkActionControls">
                                    <button type="button" class="btn btn-outline-primary btn-sm px-4 fw-semibold shadow-sm d-none" id="btnLoadMoreExplorer" onclick="loadNextChunk()">
                                        <i class="ri-arrow-down-line me-1"></i> Load Next Chunk (<span id="loadMoreCountBadge">50</span> items)
                                    </button>
                                    <span id="chunkAllLoadedNotice" class="badge bg-success-subtle text-success fs-12 px-3 py-2 border border-success-subtle rounded-pill">
                                        <i class="ri-check-double-line me-1"></i> All items loaded for this folder
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Info bar -->
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1 text-muted fs-12">
                            <span id="folderStatsText">0 folders, 0 files</span>
                            <span>Current Prefix: <code class="text-primary font-monospace" id="currentPrefixLabel">/</code></span>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL: Upload Files -->
<div class="modal fade" id="s3UploadModal" tabindex="-1" aria-labelledby="s3UploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fs-15 text-white" id="s3UploadModalLabel">
                    <i class="ri-upload-cloud-line me-1"></i> Upload Files to S3
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="s3UploadForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="prefix" id="s3UploadPrefixInput" value="">
                <div class="modal-body">
                    <div class="alert alert-info py-2 fs-13 mb-3">
                        <i class="ri-folder-shared-line me-1"></i> Uploading to folder: 
                        <strong class="font-monospace text-dark" id="s3UploadTargetDisplay">Root</strong>
                    </div>

                    <div class="mb-3">
                        <label for="s3UploadFileInput" class="form-label fw-bold">Select File(s) to Upload <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="s3UploadFileInput" name="files[]" multiple required>
                        <small class="text-muted">You can select multiple files. Max 50MB per file.</small>
                    </div>

                    <div id="uploadFileListContainer" class="d-none">
                        <h6 class="fs-13 fw-bold mb-2">Files to be uploaded:</h6>
                        <ul class="list-group list-group-flush border rounded fs-12 mb-3" id="uploadSelectedFilesList" style="max-height: 180px; overflow-y: auto;"></ul>
                    </div>

                    <div class="progress d-none mb-2" id="uploadProgressBarContainer" style="height: 18px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" id="uploadProgressBar">0%</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="btnConfirmUpload">
                        <i class="ri-check-line me-1"></i> Confirm & Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: File Preview -->
<div class="modal fade" id="s3PreviewModal" tabindex="-1" aria-labelledby="s3PreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 bg-dark text-white">
                <h5 class="modal-title fs-14 text-white font-monospace text-truncate" id="previewModalTitle">File Preview</h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="previewDownloadBtn" class="btn btn-sm btn-outline-light py-0 px-2 fs-12" target="_blank" download>
                        <i class="ri-download-2-line me-1"></i> Download
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-2 text-center bg-light" id="previewModalBody" style="min-height: 350px; max-height: 75vh; overflow: auto;">
                <div class="spinner-border text-primary my-5" role="status"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Quick Transfer / Move Item -->
<div class="modal fade" id="s3TransferModal" tabindex="-1" aria-labelledby="s3TransferModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fs-15 text-white" id="s3TransferModalLabel">
                    <i class="ri-swap-line me-1"></i> Transfer / Move Item
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="s3ModalTransferForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Source Path:</label>
                        <input type="text" class="form-control font-monospace bg-light" id="modalTransferSource" name="source_path" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Destination Path or Folder <span class="text-danger">*</span>:</label>
                        <input type="text" class="form-control font-monospace" id="modalTransferTarget" name="target_path" placeholder="e.g. VVNR_Expense/6/101768/" aria-label="e.g. VVNR_Expense/6/101768/" required>
                        <small class="text-muted">Specify the target folder prefix or full target file path.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">Action Type:</label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-radio-primary">
                                <input class="form-check-input" type="radio" name="modal_action_type" id="modal_action_copy" value="copy" checked>
                                <label class="form-check-label fw-semibold" for="modal_action_copy">
                                    <i class="ri-file-copy-line text-primary me-1"></i> Copy (Preserve original)
                                </label>
                            </div>
                            <div class="form-check form-radio-danger">
                                <input class="form-check-input" type="radio" name="modal_action_type" id="modal_action_move" value="move">
                                <label class="form-check-label fw-semibold text-danger" for="modal_action_move">
                                    <i class="ri-scissors-cut-line me-1"></i> Move (Delete original)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="btnConfirmModalTransfer">
                        <i class="ri-check-line me-1"></i> Execute Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.min.css') }}">
<style>
    .explorer-row:hover {
        background-color: #f0f7ff !important;
    }
    .explorer-path-copy:hover {
        color: #00175f !important;
    }
    .file-icon-img { color: #0284c7; }
    .file-icon-pdf { color: #dc2626; }
    .file-icon-excel { color: #16a34a; }
    .file-icon-zip { color: #ea580c; }
    .file-icon-code { color: #7c3aed; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
<script>
var currentExplorerPrefix = '';
var currentFolderItems = { directories: [], files: [] };
var currentNextContinuationToken = null;
var currentIsTruncated = false;
var isLoadingMoreChunk = false;

$(document).ready(function() {
    // Fill example button handler
    $('#btnFillExample').on('click', function() {
        $('#source_path').val('https://s3.ap-south-1.amazonaws.com/developerinvnr.bkt/Expense/6/101574/Img_101574_120826125710_1.jpg');
        $('#target_path').val('https://s3.ap-south-1.amazonaws.com/developerinvnr.bkt/VVNR_Expense/6/101768/ClsImg_101768_140826101000914.jpg');
    });

    // Single Transfer Form with REQUIRED Confirmation
    $('#singleTransferForm').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        var src = $('#source_path').val().trim();
        var tgt = $('#target_path').val().trim();
        var action = $('input[name="action_type"]:checked').val();
        var isMove = (action === 'move');

        Swal.fire({
            title: isMove ? 'Confirm MOVE Operation?' : 'Confirm COPY Operation?',
            html: `
                <div class="text-start fs-13">
                    <p class="mb-1"><strong>Source:</strong> <code class="text-dark d-block text-break">${src}</code></p>
                    <p class="mb-2"><strong>Destination:</strong> <code class="text-success d-block text-break">${tgt}</code></p>
                    <div class="alert ${isMove ? 'alert-danger' : 'alert-info'} py-2 mb-0">
                        <i class="${isMove ? 'ri-error-warning-line' : 'ri-information-line'} me-1"></i>
                        ${isMove ? '<strong>Warning:</strong> Original file(s) will be <strong>DELETED</strong> after successful copy.' : 'Original file(s) will be preserved.'}
                    </div>
                </div>
            `,
            icon: isMove ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: isMove ? '<i class="ri-scissors-cut-line me-1"></i> Confirm Move' : '<i class="ri-file-copy-line me-1"></i> Confirm Copy',
            confirmButtonColor: isMove ? '#d33' : '#00175f',
            cancelButtonColor: '#6c757d',
            focusCancel: isMove
        }).then((result) => {
            if (!result.isConfirmed) return;

            var $btn = $('#btnSubmitTransfer');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Processing Transfer...');
            $('#transferResultContainer').addClass('d-none');

            $.ajax({
                url: $(form).attr('action'),
                type: 'POST',
                data: $(form).serialize(),
                dataType: 'json',
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="ri-send-plane-fill me-1 align-middle"></i> Start Transfer Process');
                    
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Transfer Completed',
                            text: res.message,
                            timer: 2500,
                            showConfirmButton: false
                        });

                        $('#transferResultContainer').removeClass('d-none');
                        $('#resultMessage').text(res.message);
                        $('#resultCountBadge').text(res.files_transferred + ' File(s)');

                        var tbody = $('#resultFilesTableBody');
                        tbody.empty();

                        if (res.transferred_files && res.transferred_files.length > 0) {
                            $.each(res.transferred_files, function(i, f) {
                                tbody.append(`
                                    <tr>
                                        <td>${i + 1}</td>
                                        <td><code class="text-dark">${f.source}</code></td>
                                        <td><code class="text-success">${f.target}</code></td>
                                        <td>
                                            <a href="${f.url}" target="_blank" class="btn btn-sm btn-soft-primary py-0 px-2 fs-12">
                                                <i class="ri-external-link-line me-1"></i> View File
                                            </a>
                                        </td>
                                    </tr>
                                `);
                            });
                        }
                    } else {
                        Swal.fire('Transfer Failed', res.message || 'Operation failed.', 'error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="ri-send-plane-fill me-1 align-middle"></i> Start Transfer Process');
                    var err = xhr.responseJSON ? xhr.responseJSON.message : 'Transfer request failed.';
                    Swal.fire('Transfer Error', err, 'error');
                }
            });
        });
    });

    // Batch Transfer Form with REQUIRED Confirmation
    $('#batchTransferForm').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        var text = $('#batch_text').val().trim();
        var action = $('input[name="batch_action_type"]:checked').val();
        var isMove = (action === 'move');
        var linesCount = text.split('\n').filter(function(l) { return l.trim() && !l.trim().startsWith('#'); }).length;

        if (linesCount === 0) {
            Swal.fire('Empty Batch', 'Please enter at least one source => target mapping.', 'warning');
            return;
        }

        Swal.fire({
            title: isMove ? 'Confirm Batch MOVE Operation?' : 'Confirm Batch COPY Operation?',
            html: `
                <div class="text-start fs-13">
                    <p>You have entered <strong>${linesCount}</strong> transfer instructions.</p>
                    <div class="alert ${isMove ? 'alert-danger' : 'alert-info'} py-2 mb-0">
                        <i class="${isMove ? 'ri-error-warning-line' : 'ri-information-line'} me-1"></i>
                        Action: <strong>${isMove ? 'MOVE (Original files will be deleted)' : 'COPY (Original files preserved)'}</strong>
                    </div>
                </div>
            `,
            icon: isMove ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="ri-play-list-add-line me-1"></i> Execute Batch',
            confirmButtonColor: isMove ? '#d33' : '#00175f',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (!result.isConfirmed) return;

            var $btn = $('#btnSubmitBatch');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing Batch...');
            $('#batchResultContainer').addClass('d-none');

            $.ajax({
                url: $(form).attr('action'),
                type: 'POST',
                data: $(form).serialize() + '&action_type=' + action,
                dataType: 'json',
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="ri-play-list-add-line me-1 align-middle"></i> Execute Batch Transfers');
                    $('#batchResultContainer').removeClass('d-none');
                    $('#batchSummaryText').html(`Processed Batch: <span class="text-success">${res.success_count} Passed</span>, <span class="text-danger">${res.fail_count} Failed</span>`);

                    var tbody = $('#batchResultTableBody');
                    tbody.empty();

                    if (res.results) {
                        $.each(res.results, function(i, item) {
                            var statusBadge = item.result.success 
                                ? '<span class="badge bg-success-subtle text-success">Success</span>' 
                                : `<span class="badge bg-danger-subtle text-danger" title="${item.result.message}">Failed</span>`;
                            
                            tbody.append(`
                                <tr>
                                    <td>${i + 1}</td>
                                    <td><code>${item.source}</code></td>
                                    <td><code>${item.target}</code></td>
                                    <td>${statusBadge} ${item.result.message ? '<small class="d-block text-muted">' + item.result.message + '</small>' : ''}</td>
                                </tr>
                            `);
                        });
                    }

                    Swal.fire({
                        icon: res.fail_count > 0 ? 'warning' : 'success',
                        title: 'Batch Complete',
                        text: `Processed ${res.success_count} successful, ${res.fail_count} failed.`
                    });
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="ri-play-list-add-line me-1 align-middle"></i> Execute Batch Transfers');
                    Swal.fire('Batch Error', 'Batch request failed.', 'error');
                }
            });
        });
    });

    // Check all explorer items checkbox
    $('#checkAllExplorer').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.explorer-item-check:visible').prop('checked', isChecked);
        updateBulkActionBar();
    });

    // Individual item check change
    $(document).on('change', '.explorer-item-check', function() {
        updateBulkActionBar();
    });

    // Real-time table filter input
    $('#explorerFilterTable').on('input', function() {
        var term = $(this).val().toLowerCase().trim();
        renderExplorerRows(term);
    });

    // Upload file input change (preview file list)
    $('#s3UploadFileInput').on('change', function() {
        var files = this.files;
        var list = $('#uploadSelectedFilesList');
        list.empty();

        if (files && files.length > 0) {
            $('#uploadFileListContainer').removeClass('d-none');
            var totalSize = 0;
            $.each(files, function(i, file) {
                totalSize += file.size;
                list.append(`
                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                        <span class="text-truncate" style="max-width: 450px;"><i class="ri-file-3-line text-primary me-1"></i> ${file.name}</span>
                        <span class="badge bg-light text-dark">${formatBytes(file.size)}</span>
                    </li>
                `);
            });
            list.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 bg-light fw-bold">
                    <span>Total (${files.length} file(s)):</span>
                    <span>${formatBytes(totalSize)}</span>
                </li>
            `);
        } else {
            $('#uploadFileListContainer').addClass('d-none');
        }
    });

    // Upload Form Submit with REQUIRED Confirmation
    $('#s3UploadForm').on('submit', function(e) {
        e.preventDefault();
        var filesInput = document.getElementById('s3UploadFileInput');
        if (!filesInput.files || filesInput.files.length === 0) {
            Swal.fire('No Files Selected', 'Please choose at least one file to upload.', 'warning');
            return;
        }

        var count = filesInput.files.length;
        var targetPrefix = currentExplorerPrefix || 'Root (/)';

        Swal.fire({
            title: 'Confirm File Upload?',
            html: `Upload <strong>${count} file(s)</strong> directly to:<br><code class="text-primary fs-14 mt-1 d-block font-monospace">${targetPrefix}</code>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="ri-upload-2-line me-1"></i> Yes, Upload Now',
            confirmButtonColor: '#00175f',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (!result.isConfirmed) return;

            var formData = new FormData(document.getElementById('s3UploadForm'));
            formData.set('prefix', currentExplorerPrefix);

            var $btn = $('#btnConfirmUpload');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Uploading...');
            $('#uploadProgressBarContainer').removeClass('d-none');
            $('#uploadProgressBar').css('width', '0%').text('0%');

            $.ajax({
                url: "{{ route('admin.s3-transfer.upload') }}",
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            $('#uploadProgressBar').css('width', percentComplete + '%').text(percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i> Confirm & Upload');
                    $('#uploadProgressBarContainer').addClass('d-none');

                    if (res.success) {
                        $('#s3UploadModal').modal('hide');
                        $('#s3UploadForm')[0].reset();
                        $('#uploadFileListContainer').addClass('d-none');

                        Swal.fire({
                            icon: 'success',
                            title: 'Upload Successful',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        });

                        refreshCurrentFolder();
                    } else {
                        Swal.fire('Upload Failed', res.message || 'Error uploading files.', 'error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i> Confirm & Upload');
                    $('#uploadProgressBarContainer').addClass('d-none');
                    var err = xhr.responseJSON ? xhr.responseJSON.message : 'Upload request failed.';
                    Swal.fire('Upload Error', err, 'error');
                }
            });
        });
    });

    // Quick Transfer Modal Form Submit with Confirmation
    $('#s3ModalTransferForm').on('submit', function(e) {
        e.preventDefault();
        var src = $('#modalTransferSource').val().trim();
        var tgt = $('#modalTransferTarget').val().trim();
        var action = $('input[name="modal_action_type"]:checked').val();
        var isMove = (action === 'move');

        Swal.fire({
            title: isMove ? 'Confirm Move Operation?' : 'Confirm Copy Operation?',
            html: `
                <div class="text-start fs-13">
                    <p class="mb-1"><strong>Source:</strong> <code class="text-dark d-block text-break">${src}</code></p>
                    <p class="mb-2"><strong>Destination:</strong> <code class="text-success d-block text-break">${tgt}</code></p>
                    <div class="alert ${isMove ? 'alert-danger' : 'alert-info'} py-2 mb-0">
                        ${isMove ? '<strong>Warning:</strong> Original file/folder will be deleted after transfer.' : 'Original item will be kept intact.'}
                    </div>
                </div>
            `,
            icon: isMove ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: isMove ? '<i class="ri-scissors-cut-line me-1"></i> Confirm Move' : '<i class="ri-file-copy-line me-1"></i> Confirm Copy',
            confirmButtonColor: isMove ? '#d33' : '#00175f',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (!result.isConfirmed) return;

            var $btn = $('#btnConfirmModalTransfer');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

            $.ajax({
                url: "{{ route('admin.s3-transfer.process') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    mode: 'single',
                    source_path: src,
                    target_path: tgt,
                    action_type: action
                },
                dataType: 'json',
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i> Execute Transfer');
                    if (res.success) {
                        $('#s3TransferModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Transfer Complete',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        refreshCurrentFolder();
                    } else {
                        Swal.fire('Transfer Failed', res.message, 'error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i> Execute Transfer');
                    var err = xhr.responseJSON ? xhr.responseJSON.message : 'Transfer request failed.';
                    Swal.fire('Transfer Error', err, 'error');
                }
            });
        });
    });
});

// Load S3 Explorer Folder with Native AWS Chunking & File Cache
function loadS3Explorer(prefix, append, continuationToken, forceRefresh) {
    prefix = prefix || '';
    if (prefix && !prefix.endsWith('/')) {
        prefix += '/';
    }
    append = !!append;
    continuationToken = continuationToken || null;
    forceRefresh = !!forceRefresh;

    var limit = parseInt($('#explorerChunkLimit').val()) || 50;
    currentExplorerPrefix = prefix;
    $('#explorerSearchPrefix').val(prefix);
    $('#currentPrefixLabel').text(prefix || '/');

    var tbody = $('#explorerTableBody');

    if (!append) {
        currentNextContinuationToken = null;
        currentIsTruncated = false;
        currentFolderItems = { directories: [], files: [] };
        clearExplorerSelections();
        tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading S3 items (Chunk limit: ' + limit + ')...</td></tr>');
        $('#btnLoadMoreExplorer').addClass('d-none');
        $('#chunkAllLoadedNotice').addClass('d-none');
        $('#chunkStatusText').text('Loading items...');
    }

    $.ajax({
        url: "{{ route('admin.s3-transfer.explore') }}",
        type: 'POST',
        data: {
            _token: "{{ csrf_token() }}",
            prefix: prefix,
            limit: limit,
            continuation_token: continuationToken,
            refresh: forceRefresh ? 1 : 0
        },
        dataType: 'json',
        success: function(res) {
            isLoadingMoreChunk = false;
            $('#btnLoadMoreExplorer').prop('disabled', false).html('<i class="ri-arrow-down-line me-1"></i> Load Next Chunk (<span id="loadMoreCountBadge">' + limit + '</span> items)');

            if (!res.success) {
                if (!append) {
                    tbody.html(`<tr><td colspan="6" class="text-center text-danger py-4"><i class="ri-error-warning-line me-1"></i> ${res.message || 'Error loading folder.'}</td></tr>`);
                } else {
                    Swal.fire('Chunk Load Error', res.message || 'Could not load next chunk.', 'error');
                }
                return;
            }

            currentIsTruncated = !!res.is_truncated;
            currentNextContinuationToken = res.next_token || null;

            if (append) {
                currentFolderItems.directories = mergeUniqueItems(currentFolderItems.directories, res.directories || []);
                currentFolderItems.files = mergeUniqueItems(currentFolderItems.files, res.files || []);
            } else {
                currentFolderItems = {
                    directories: res.directories || [],
                    files: res.files || []
                };
                // Update Breadcrumbs
                renderBreadcrumbs(res.current_prefix);
            }

            // Render Rows
            renderExplorerRows($('#explorerFilterTable').val());

            // Update Chunk Bar
            updateChunkPaginationUI(res);
        },
        error: function(xhr) {
            isLoadingMoreChunk = false;
            $('#btnLoadMoreExplorer').prop('disabled', false).html('<i class="ri-arrow-down-line me-1"></i> Load Next Chunk (<span id="loadMoreCountBadge">' + limit + '</span> items)');
            if (!append) {
                tbody.html('<tr><td colspan="6" class="text-center text-danger py-4"><i class="ri-wifi-off-line me-1"></i> Failed to connect to S3.</td></tr>');
            } else {
                Swal.fire('Connection Error', 'Failed to load next chunk from S3.', 'error');
            }
        }
    });
}

function mergeUniqueItems(existing, incoming) {
    var seen = new Set(existing.map(function(item) { return item.path; }));
    var result = existing.slice();
    for (var i = 0; i < incoming.length; i++) {
        if (!seen.has(incoming[i].path)) {
            seen.add(incoming[i].path);
            result.push(incoming[i]);
        }
    }
    return result;
}

function updateChunkPaginationUI(res) {
    var limit = parseInt($('#explorerChunkLimit').val()) || 50;
    var totalDirs = currentFolderItems.directories.length;
    var totalFiles = currentFolderItems.files.length;
    var totalLoaded = totalDirs + totalFiles;

    $('#chunkSizeText').text(limit);
    $('#loadMoreCountBadge').text(limit);

    if (currentIsTruncated && currentNextContinuationToken) {
        $('#btnLoadMoreExplorer').removeClass('d-none').prop('disabled', false).html(`<i class="ri-arrow-down-line me-1"></i> Load Next Chunk (${limit} items)`);
        $('#chunkAllLoadedNotice').addClass('d-none');
        $('#chunkStatusText').html(`Showing <strong>${totalLoaded}</strong> loaded items so far. <span class="text-primary fw-semibold"><i class="ri-information-line"></i> More items exist in this folder</span>`);
    } else {
        $('#btnLoadMoreExplorer').addClass('d-none');
        if (totalLoaded > 0) {
            $('#chunkAllLoadedNotice').removeClass('d-none');
            $('#chunkStatusText').html(`All <strong>${totalLoaded}</strong> items loaded.`);
        } else {
            $('#chunkAllLoadedNotice').addClass('d-none');
            $('#chunkStatusText').html('Folder is empty.');
        }
    }

    if (res && res.from_cache) {
        $('#chunkSummaryBadge').html(`<i class="ri-flashlight-line text-warning me-1"></i> Instant Cache (${limit} / chunk)`);
    } else {
        $('#chunkSummaryBadge').html(`<i class="ri-cloud-line me-1"></i> S3 Live (${limit} / chunk)`);
    }
}

function loadNextChunk() {
    if (!currentNextContinuationToken || isLoadingMoreChunk) return;
    isLoadingMoreChunk = true;
    $('#btnLoadMoreExplorer').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Loading Next Chunk...');
    loadS3Explorer(currentExplorerPrefix, true, currentNextContinuationToken, false);
}

function refreshCurrentFolder(forceRefresh) {
    if (typeof forceRefresh === 'undefined') forceRefresh = true;
    currentNextContinuationToken = null;
    currentIsTruncated = false;
    loadS3Explorer(currentExplorerPrefix, false, null, forceRefresh);
}

function onChunkLimitChange() {
    currentNextContinuationToken = null;
    currentIsTruncated = false;
    loadS3Explorer(currentExplorerPrefix, false, null, false);
}

function goUpOneLevel() {
    if (!currentExplorerPrefix) return;
    var clean = currentExplorerPrefix.replace(/\/+$/, '');
    var lastSlash = clean.lastIndexOf('/');
    if (lastSlash === -1) {
        loadS3Explorer('');
    } else {
        loadS3Explorer(clean.substring(0, lastSlash + 1));
    }
}

function renderBreadcrumbs(prefix) {
    var crumbs = $('#explorerBreadcrumbs');
    crumbs.empty();
    crumbs.append('<li class="breadcrumb-item"><a href="javascript:void(0)" onclick="loadS3Explorer(\'\')" class="text-primary text-decoration-none">Root</a></li>');

    if (prefix) {
        var parts = prefix.split('/').filter(Boolean);
        var accumulated = '';
        $.each(parts, function(idx, p) {
            accumulated += p + '/';
            var isLast = (idx === parts.length - 1);
            if (isLast) {
                crumbs.append(`<li class="breadcrumb-item active fw-bold text-dark">${p}</li>`);
            } else {
                var targetAcc = accumulated;
                crumbs.append(`<li class="breadcrumb-item"><a href="javascript:void(0)" onclick="loadS3Explorer('${targetAcc}')" class="text-primary text-decoration-none">${p}</a></li>`);
            }
        });
    }
}

function renderExplorerRows(filterText) {
    var tbody = $('#explorerTableBody');
    tbody.empty();
    filterText = (filterText || '').toLowerCase().trim();

    var dirs = currentFolderItems.directories;
    var files = currentFolderItems.files;

    if (filterText) {
        dirs = dirs.filter(d => d.name.toLowerCase().includes(filterText) || d.path.toLowerCase().includes(filterText));
        files = files.filter(f => f.name.toLowerCase().includes(filterText) || f.path.toLowerCase().includes(filterText));
    }

    var totalDirs = dirs.length;
    var totalFiles = files.length;
    $('#folderStatsText').text(`${totalDirs} folder(s), ${totalFiles} file(s)`);

    if (totalDirs === 0 && totalFiles === 0) {
        tbody.html('<tr><td colspan="6" class="text-center text-muted py-5"><i class="ri-inbox-line fs-28 d-block mb-1 text-secondary"></i> Folder is empty.</td></tr>');
        return;
    }

    // Render Directories
    $.each(dirs, function(i, d) {
        var safePath = escapeHtml(d.path);
        var safeName = escapeHtml(d.name);
        tbody.append(`
            <tr class="explorer-row">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input explorer-item-check" data-path="${safePath}/" data-name="${safeName}" data-is-folder="1">
                </td>
                <td><i class="ri-folder-fill text-warning fs-18"></i></td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <a href="javascript:void(0)" onclick="loadS3Explorer('${safePath}/')" class="fw-bold text-dark text-decoration-none">
                            ${safeName}/
                        </a>
                        <button type="button" class="btn btn-link btn-sm p-0 text-muted explorer-path-copy" onclick="copyToClipboard('${safePath}/')" title="Copy Path">
                            <i class="ri-file-copy-line fs-13"></i>
                        </button>
                    </div>
                </td>
                <td><span class="text-muted fs-12">-</span></td>
                <td><span class="text-muted fs-12">-</span></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary py-0 px-2 fs-12" onclick="loadS3Explorer('${safePath}/')" title="Open Folder">
                            <i class="ri-folder-open-line"></i> Open
                        </button>
                        <button class="btn btn-outline-secondary py-0 px-2 fs-12" onclick="promptRenameFolder('${safePath}/', '${safeName}')" title="Rename Folder">
                            <i class="ri-edit-line"></i> Rename
                        </button>
                        <button class="btn btn-outline-info py-0 px-2 fs-12" onclick="openTransferModal('${safePath}/', true)" title="Transfer / Move Folder">
                            <i class="ri-swap-line"></i> Transfer
                        </button>
                        <button class="btn btn-outline-danger py-0 px-2 fs-12" onclick="confirmDeleteFolder('${safePath}/', '${safeName}')" title="Delete Folder">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `);
    });

    // Render Files
    $.each(files, function(i, f) {
        var safePath = escapeHtml(f.path);
        var safeName = escapeHtml(f.name);
        var safeUrl = escapeHtml(f.url);
        var sizeFormatted = formatBytes(f.size);
        var iconClass = getFileIconClass(f.name);

        tbody.append(`
            <tr class="explorer-row">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input explorer-item-check" data-path="${safePath}" data-name="${safeName}" data-is-folder="0">
                </td>
                <td><i class="${iconClass} fs-18"></i></td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <code class="text-dark fw-semibold">${safeName}</code>
                        <button type="button" class="btn btn-link btn-sm p-0 text-muted explorer-path-copy" onclick="copyToClipboard('${safePath}')" title="Copy Path">
                            <i class="ri-file-copy-line fs-13"></i>
                        </button>
                    </div>
                </td>
                <td><span class="badge bg-light text-dark fs-11">${sizeFormatted}</span></td>
                <td><small class="text-muted fs-12">${f.last_modified}</small></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-soft-info py-0 px-2 fs-12" onclick="previewFile('${safeUrl}', '${safeName}')" title="Preview File">
                            <i class="ri-eye-line"></i>
                        </button>
                        <a href="${safeUrl}" target="_blank" download class="btn btn-soft-secondary py-0 px-2 fs-12" title="Download File">
                            <i class="ri-download-2-line"></i>
                        </a>
                        <button class="btn btn-soft-primary py-0 px-2 fs-12" onclick="promptRenameFile('${safePath}', '${safeName}')" title="Rename File">
                            <i class="ri-edit-line"></i>
                        </button>
                        <button class="btn btn-soft-primary py-0 px-2 fs-12" onclick="openTransferModal('${safePath}', false)" title="Transfer / Move File">
                            <i class="ri-swap-line"></i>
                        </button>
                        <button class="btn btn-soft-danger py-0 px-2 fs-12" onclick="confirmDeleteFile('${safePath}', '${safeName}')" title="Delete File">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `);
    });
}

function updateBulkActionBar() {
    var checked = $('.explorer-item-check:checked');
    var count = checked.length;

    if (count > 0) {
        $('#bulkActionsBar').removeClass('d-none').addClass('d-flex');
        $('#bulkSelectedText').text(`${count} item(s) selected`);
    } else {
        $('#bulkActionsBar').addClass('d-none').removeClass('d-flex');
        $('#checkAllExplorer').prop('checked', false);
    }
}

function clearExplorerSelections() {
    $('.explorer-item-check').prop('checked', false);
    $('#checkAllExplorer').prop('checked', false);
    updateBulkActionBar();
}

// 1. DELETE FILE with REQUIRED Confirmation
function confirmDeleteFile(filePath, fileName) {
    Swal.fire({
        title: 'Delete File?',
        html: `
            <div class="text-start fs-13">
                <p>Are you sure you want to permanently delete this file from AWS S3?</p>
                <div class="p-2 bg-light border rounded font-monospace text-danger text-break mb-2">${filePath}</div>
                <div class="alert alert-danger py-1 fs-12 mb-0">
                    <i class="ri-error-warning-line me-1"></i> This action cannot be undone.
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-delete-bin-line me-1"></i> Yes, Delete Permanently',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        focusCancel: true
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.showLoading();
        $.ajax({
            url: "{{ route('admin.s3-transfer.delete') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                path: filePath,
                is_folder: false
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: res.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                    refreshCurrentFolder();
                } else {
                    Swal.fire('Delete Failed', res.message, 'error');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Delete request failed.';
                Swal.fire('Delete Error', err, 'error');
            }
        });
    });
}

// 2. DELETE FOLDER with REQUIRED Confirmation
function confirmDeleteFolder(folderPath, folderName) {
    Swal.fire({
        title: 'Delete Entire Folder?',
        html: `
            <div class="text-start fs-13">
                <p class="text-danger fw-bold mb-1"><i class="ri-alert-line me-1"></i> HIGH-IMPACT DESTRUCTIVE ACTION</p>
                <p>You are about to permanently delete folder <strong>'${folderName}'</strong> and <strong>ALL FILES & SUBFOLDERS INSIDE IT</strong> from S3!</p>
                <div class="p-2 bg-light border border-danger rounded font-monospace text-danger text-break mb-2">${folderPath}</div>
                <div class="alert alert-danger py-2 fs-12 mb-0">
                    <i class="ri-error-warning-line me-1"></i> <strong>All contents will be permanently destroyed.</strong>
                </div>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-error-warning-line me-1"></i> Yes, Delete Entire Folder',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        focusCancel: true
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.showLoading();
        $.ajax({
            url: "{{ route('admin.s3-transfer.delete') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                path: folderPath,
                is_folder: true
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Folder Deleted',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    refreshCurrentFolder();
                } else {
                    Swal.fire('Delete Failed', res.message, 'error');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Delete request failed.';
                Swal.fire('Delete Error', err, 'error');
            }
        });
    });
}

// 3. RENAME FILE with REQUIRED Confirmation
function promptRenameFile(filePath, currentName) {
    Swal.fire({
        title: 'Rename File',
        html: `Current file: <code class="text-dark d-block mb-2 font-monospace">${currentName}</code>`,
        input: 'text',
        inputValue: currentName,
        inputLabel: 'Enter new file name (including extension):',
        inputPlaceholder: 'e.g. document_v2.pdf',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-check-line me-1"></i> Rename File',
        confirmButtonColor: '#00175f',
        cancelButtonColor: '#6c757d',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'File name cannot be empty!';
            }
            if (value.trim() === currentName) {
                return 'New name must be different from current name!';
            }
            if (/[<>:"|?*\\\/]/.test(value)) {
                return 'Name cannot contain special characters like / \\ < > : " | ? *';
            }
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        var newName = result.value.trim();

        Swal.showLoading();
        $.ajax({
            url: "{{ route('admin.s3-transfer.rename') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                old_path: filePath,
                new_name: newName,
                is_folder: false
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Renamed',
                        text: res.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                    refreshCurrentFolder();
                } else {
                    Swal.fire('Rename Failed', res.message, 'error');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Rename request failed.';
                Swal.fire('Rename Error', err, 'error');
            }
        });
    });
}

// 4. RENAME FOLDER with REQUIRED Confirmation
function promptRenameFolder(folderPath, currentName) {
    Swal.fire({
        title: 'Rename Folder',
        html: `Current folder: <code class="text-dark d-block mb-2 font-monospace">${currentName}/</code>`,
        input: 'text',
        inputValue: currentName,
        inputLabel: 'Enter new folder name:',
        inputPlaceholder: 'e.g. Archived_2026',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-check-line me-1"></i> Rename Folder',
        confirmButtonColor: '#00175f',
        cancelButtonColor: '#6c757d',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'Folder name cannot be empty!';
            }
            if (value.trim() === currentName) {
                return 'New name must be different from current name!';
            }
            if (/[<>:"|?*\\\/]/.test(value)) {
                return 'Name cannot contain special characters like / \\ < > : " | ? *';
            }
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        var newName = result.value.trim();

        Swal.showLoading();
        $.ajax({
            url: "{{ route('admin.s3-transfer.rename') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                old_path: folderPath,
                new_name: newName,
                is_folder: true
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Folder Renamed',
                        text: res.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                    refreshCurrentFolder();
                } else {
                    Swal.fire('Rename Failed', res.message, 'error');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Rename request failed.';
                Swal.fire('Rename Error', err, 'error');
            }
        });
    });
}

// 5. CREATE NEW FOLDER with REQUIRED Confirmation
function openCreateFolderModal() {
    var parentName = currentExplorerPrefix || 'Root (/)';
    Swal.fire({
        title: 'Create New Folder',
        html: `Location: <code class="text-primary d-block mb-2 font-monospace">${parentName}</code>`,
        input: 'text',
        inputLabel: 'Folder Name:',
        inputPlaceholder: 'e.g. Invoices_2026',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-folder-add-line me-1"></i> Create Folder',
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6c757d',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'Folder name cannot be empty!';
            }
            if (/[<>:"|?*\\\/]/.test(value)) {
                return 'Folder name cannot contain special characters like / \\ < > : " | ? *';
            }
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        var folderName = result.value.trim();

        Swal.showLoading();
        $.ajax({
            url: "{{ route('admin.s3-transfer.create-folder') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                prefix: currentExplorerPrefix,
                folder_name: folderName
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Folder Created',
                        text: res.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                    refreshCurrentFolder();
                } else {
                    Swal.fire('Create Failed', res.message, 'error');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Create folder request failed.';
                Swal.fire('Error', err, 'error');
            }
        });
    });
}

// 6. BULK DELETE with REQUIRED Confirmation
function confirmBulkDelete() {
    var checked = $('.explorer-item-check:checked');
    var items = [];
    var folderCount = 0;
    var fileCount = 0;

    checked.each(function() {
        var path = $(this).data('path');
        var isFolder = ($(this).data('is-folder') == 1);
        items.push({ path: path, is_folder: isFolder });
        if (isFolder) folderCount++; else fileCount++;
    });

    if (items.length === 0) return;

    Swal.fire({
        title: `Delete ${items.length} Selected Item(s)?`,
        html: `
            <div class="text-start fs-13">
                <p>Are you sure you want to permanently delete the selected items from AWS S3?</p>
                <div class="alert alert-danger py-2 mb-2">
                    <i class="ri-alert-line me-1"></i> Selected: <strong>${fileCount} file(s)</strong> and <strong>${folderCount} folder(s)</strong>.
                    <br><small>All files inside selected folders will be permanently destroyed.</small>
                </div>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: `<i class="ri-delete-bin-line me-1"></i> Yes, Delete ${items.length} Items`,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        focusCancel: true
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.showLoading();
        $.ajax({
            url: "{{ route('admin.s3-transfer.bulk-delete') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                items: items
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Bulk Delete Complete',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    refreshCurrentFolder();
                } else {
                    Swal.fire('Bulk Delete Failed', res.message, 'error');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Bulk delete request failed.';
                Swal.fire('Error', err, 'error');
            }
        });
    });
}

// 7. PREVIEW FILE MODAL
function previewFile(url, fileName) {
    $('#previewModalTitle').text(fileName);
    $('#previewDownloadBtn').attr('href', url);

    var ext = fileName.split('.').pop().toLowerCase();
    var body = $('#previewModalBody');
    body.empty();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
        body.html(`<img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 70vh;" alt="${fileName}">`);
    } else if (ext === 'pdf') {
        body.html(`<iframe src="${url}" width="100%" height="550px" style="border:none;" class="rounded"></iframe>`);
    } else {
        body.html(`
            <div class="py-5 text-center">
                <i class="ri-file-3-line fs-48 text-muted d-block mb-3"></i>
                <h5>No inline preview available for .${ext} files</h5>
                <p class="text-muted fs-13">You can download or view this file directly in a new browser tab.</p>
                <a href="${url}" target="_blank" class="btn btn-primary btn-sm px-3 mt-2">
                    <i class="ri-external-link-line me-1"></i> Open in New Tab
                </a>
            </div>
        `);
    }

    $('#s3PreviewModal').modal('show');
}

// 8. OPEN UPLOAD MODAL
function openUploadModal() {
    $('#s3UploadTargetDisplay').text(currentExplorerPrefix || 'Root (/)');
    $('#s3UploadPrefixInput').val(currentExplorerPrefix);
    $('#s3UploadForm')[0].reset();
    $('#uploadFileListContainer').addClass('d-none');
    $('#uploadProgressBarContainer').addClass('d-none');
    $('#s3UploadModal').modal('show');
}

// 9. OPEN TRANSFER MODAL
function openTransferModal(path, isFolder) {
    $('#modalTransferSource').val(path);
    $('#modalTransferTarget').val('');
    $('#modal_action_copy').prop('checked', true);
    $('#s3TransferModal').modal('show');
    $('#modalTransferTarget').focus();
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Path copied to clipboard',
                showConfirmButton: false,
                timer: 1500
            });
        });
    } else {
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Path copied to clipboard',
            showConfirmButton: false,
            timer: 1500
        });
    }
}

function getFileIconClass(fileName) {
    var ext = (fileName || '').split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
        return 'ri-image-line file-icon-img';
    } else if (ext === 'pdf') {
        return 'ri-file-pdf-line file-icon-pdf';
    } else if (['xls', 'xlsx', 'csv'].includes(ext)) {
        return 'ri-file-excel-line file-icon-excel';
    } else if (['zip', 'rar', 'tar', 'gz', '7z'].includes(ext)) {
        return 'ri-file-zip-line file-icon-zip';
    } else if (['php', 'js', 'html', 'css', 'json', 'sql', 'xml'].includes(ext)) {
        return 'ri-file-code-line file-icon-code';
    }
    return 'ri-file-3-line text-primary';
}

function formatBytes(bytes, decimals = 2) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    return (text || '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
@endpush
