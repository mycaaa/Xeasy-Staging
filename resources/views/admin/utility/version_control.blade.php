@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 fw-bold"><i class="bx bx-git-repo-forked text-primary me-2"></i>Version Control Dashboard</h4>
                    <p class="text-muted mb-0" style="font-size:13px;">Manage Git branches, inspect commit history, stage changes, view diffs, revert commits, and safely rollback.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-info" id="refreshStatusBtn" onclick="window.location.href='{{ route('version-control.index') }}?t=' + Date.now()">
                        <i class="bx bx-refresh"></i> Refresh Status
                    </button>
                    <a href="{{ route('version-control.diagnostics') }}" class="btn btn-sm btn-warning">
                        <i class="bx bx-wrench"></i> Diagnostics
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Git Status & Info -->
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0 fw-semibold"><i class="bx bx-git-branch text-primary"></i> Repository Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:12px;">Current Branch</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" id="branchSelect">
                                    @foreach($branches as $branch)
                                    <option value="{{ $branch['name'] }}" {{ $branch['name'] == $currentBranch ? 'selected' : '' }}>
                                        {{ $branch['name'] }} @if($branch['current']) (Current) @endif
                                    </option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-primary" type="button" id="checkoutBtn">
                                    <i class="bx bx-refresh"></i> Switch
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:12px;">Repository URL</label>
                            <input type="text" class="form-control form-control-sm" value="{{ $config['repository_url'] }}" readonly>
                            @if(empty($config['repository_url']))
                            <div class="alert alert-warning mt-2 mb-0 py-1 px-2" style="font-size:11px;">
                                <i class="bx bx-info-circle"></i> Set <code>GIT_REPOSITORY_URL</code> in .env file
                            </div>
                            @elseif(!$isGitRepo)
                            <div class="alert alert-danger mt-2 mb-0 py-1 px-2" style="font-size:11px;">
                                <i class="bx bx-error"></i> Not a Git repository. Run 'git init' first.
                            </div>
                            @endif
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="fetchBtn">
                                <i class="bx bx-download"></i> Fetch Remote
                            </button>
                        </div>

                        @if(isset($status['ahead']) || isset($status['behind']))
                        <div class="mt-3">
                            @if($status['ahead'] > 0)
                            <div class="alert alert-info mb-2 py-1 px-2" style="font-size:12px;">
                                <i class="bx bx-up-arrow"></i> <strong>{{ $status['ahead'] }}</strong> commit(s) ahead of remote
                            </div>
                            @endif
                            @if($status['behind'] > 0)
                            <div class="alert alert-warning mb-2 py-1 px-2" style="font-size:12px;">
                                <i class="bx bx-down-arrow"></i> <strong>{{ $status['behind'] }}</strong> commit(s) behind remote
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Git Operations -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#basic-ops" role="tab">
                                    <i class="bx bx-code-alt"></i> Basic Operations
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#branch-ops" role="tab">
                                    <i class="bx bx-git-branch"></i> Branches
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#advanced-ops" role="tab">
                                    <i class="bx bx-git-merge"></i> Stash & Tag
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Basic Operations Tab -->
                            <div class="tab-pane fade show active" id="basic-ops" role="tabpanel">
                                <!-- Add Files -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Stage Files</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="addFilesInput" placeholder="Enter file paths or '.' for all" aria-label="Enter file paths or '.' for all" value=".">
                                        <button class="btn btn-success" type="button" id="addBtn">
                                            <i class="bx bx-plus"></i> Stage (git add)
                                        </button>
                                    </div>
                                </div>

                                <!-- Commit -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Commit Changes</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="commitMessageInput" placeholder="Enter descriptive commit message" aria-label="Enter descriptive commit message" required>
                                        <button class="btn btn-primary" type="button" id="commitBtn">
                                            <i class="bx bx-check"></i> Commit
                                        </button>
                                    </div>
                                </div>

                                <!-- Push & Pull -->
                                <div class="row mb-3 g-2">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold mb-1" style="font-size:12px;">Push to Remote</label>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-success flex-fill" type="button" id="pushBtn">
                                                <i class="bx bx-up-arrow"></i> Push
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" type="button" id="forcePushBtn" title="Force Push">
                                                <i class="bx bx-up-arrow-circle"></i> Force
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold mb-1" style="font-size:12px;">Pull from Remote</label>
                                        <button class="btn btn-sm btn-info w-100" type="button" id="pullBtn">
                                            <i class="bx bx-down-arrow"></i> Pull (git pull)
                                        </button>
                                    </div>
                                </div>

                                <!-- View Changes -->
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="viewDiffBtn">
                                        <i class="bx bx-file-blank"></i> View All Uncommitted Diffs
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" type="button" id="viewConflictsBtn">
                                        <i class="bx bx-error-alt"></i> Check Conflicts
                                    </button>
                                </div>
                            </div>

                            <!-- Branch Operations Tab -->
                            <div class="tab-pane fade" id="branch-ops" role="tabpanel">
                                <!-- Create Branch -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Create New Branch</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="newBranchName" placeholder="feature/new-branch-name" aria-label="feature/new-branch-name">
                                        <button class="btn btn-primary" type="button" id="createBranchBtn">
                                            <i class="bx bx-plus"></i> Create & Checkout
                                        </button>
                                    </div>
                                </div>

                                <!-- Merge Branch -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Merge Branch into {{ $currentBranch }}</label>
                                    <div class="input-group input-group-sm">
                                        <select class="form-select" id="mergeBranchSelect">
                                            @foreach($branches as $branch)
                                            @if($branch['name'] != $currentBranch)
                                            <option value="{{ $branch['name'] }}">{{ $branch['name'] }}</option>
                                            @endif
                                            @endforeach
                                        </select>
                                        <button class="btn btn-warning" type="button" id="mergeBtn">
                                            <i class="bx bx-git-merge"></i> Merge
                                        </button>
                                    </div>
                                </div>

                                <!-- Delete Branch -->
                                <div class="mb-2">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Delete Local Branch</label>
                                    <div class="input-group input-group-sm">
                                        <select class="form-select" id="deleteBranchSelect">
                                            @foreach($branches as $branch)
                                            @if($branch['name'] != $currentBranch && !$branch['remote'])
                                            <option value="{{ $branch['name'] }}">{{ $branch['name'] }}</option>
                                            @endif
                                            @endforeach
                                        </select>
                                        <button class="btn btn-danger" type="button" id="deleteBranchBtn">
                                            <i class="bx bx-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Operations Tab -->
                            <div class="tab-pane fade" id="advanced-ops" role="tabpanel">
                                <!-- Stash Operations -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Stash Operations</label>
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <button type="button" class="btn btn-outline-primary" id="stashSaveBtn">
                                            <i class="bx bx-save"></i> Save Stash
                                        </button>
                                        <button type="button" class="btn btn-outline-info" id="stashListBtn">
                                            <i class="bx bx-list-ul"></i> List Stashes
                                        </button>
                                        <button type="button" class="btn btn-outline-success" id="stashPopBtn">
                                            <i class="bx bx-up-arrow-alt"></i> Pop Stash
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" id="stashClearBtn">
                                            <i class="bx bx-trash"></i> Clear All
                                        </button>
                                    </div>
                                </div>

                                <!-- Tag Operations -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Tag Management</label>
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" class="form-control" id="tagName" placeholder="Tag name (e.g., v1.0.0)" aria-label="Tag name (e.g., v1.0.0)">
                                        <input type="text" class="form-control" id="tagMessage" placeholder="Tag message (optional)" aria-label="Tag message (optional)">
                                        <button class="btn btn-primary" type="button" id="createTagBtn">
                                            <i class="bx bx-purchase-tag"></i> Create Tag
                                        </button>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="listTagsBtn">
                                        <i class="bx bx-list-ul"></i> List All Tags
                                    </button>
                                </div>

                                <!-- Initialize/Clone -->
                                @if(!$isGitRepo)
                                <div class="mb-2">
                                    <label class="form-label fw-semibold mb-1" style="font-size:12px;">Repository Setup</label>
                                    <button class="btn btn-sm btn-primary w-100" type="button" id="initRepoBtn">
                                        <i class="bx bx-git-repo-forked"></i> Initialize Git Repository
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Changed Files Section -->
        <div class="row mt-1">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 fw-semibold"><i class="bx bx-file text-primary"></i> Working Directory Changes</h6>
                        @if($status['has_changes'] && !empty($status['files']))
                        <span class="badge bg-warning text-dark">{{ count($status['files']) }} changed file(s)</span>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div id="changedFilesContainer">
                            @if($status['has_changes'] && !empty($status['files']))
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="120">Status</th>
                                            <th>Filename</th>
                                            <th width="240" class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($status['files'] as $file)
                                        <tr>
                                            <td>
                                                <span class="badge bg-{{ $file['staged'] ? 'success' : ($file['status'] == '??' ? 'secondary' : 'warning') }}">
                                                    {{ $file['staged'] ? 'Staged (' . $file['status'] . ')' : ($file['status'] == '??' ? 'Untracked' : 'Modified') }}
                                                </span>
                                            </td>
                                            <td><code class="text-dark">{{ $file['filename'] }}</code></td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info diff-file-btn" data-file="{{ $file['filename'] }}" title="View Diff for this file">
                                                        <i class="bx bx-show"></i> Diff
                                                    </button>
                                                    <button class="btn btn-outline-success add-file-btn" data-file="{{ $file['filename'] }}" title="Stage file">
                                                        <i class="bx bx-plus"></i> Stage
                                                    </button>
                                                    <button class="btn btn-outline-danger discard-file-btn" data-file="{{ $file['filename'] }}" title="Discard changes in this file">
                                                        <i class="bx bx-trash"></i> Discard
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="text-center text-muted py-4">
                                <i class="bx bx-check-circle text-success" style="font-size: 40px;"></i>
                                <p class="mt-2 mb-0 fw-semibold text-dark">Working directory is clean</p>
                                <small class="text-muted">No uncommitted changes detected</small>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Commits Section -->
        <div class="row mt-1">
            <div class="col-lg-12">
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="card-title mb-0 fw-semibold"><i class="bx bx-history text-primary"></i> Commit History</h6>
                            <span class="badge bg-primary" id="commitCountBadge">{{ count($logs) }} Commits</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text"><i class="bx bx-search"></i></span>
                                <input type="text" class="form-control" id="commitFilterInput" placeholder="Filter message, author, hash..." aria-label="Filter message, author, hash">
                            </div>
                            <select class="form-select form-select-sm" id="commitLimitSelect" style="width: 100px;">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="reloadLogsBtn" title="Reload Commits">
                                <i class="bx bx-refresh"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="commitsContainer">
                            @if(!empty($logs))
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle mb-0" id="commitsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="110">Hash</th>
                                            <th width="180">Author</th>
                                            <th width="160">Date</th>
                                            <th>Commit Message</th>
                                            <th width="220" class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($logs as $log)
                                        <tr class="commit-row" data-search="{{ strtolower($log['hash'] . ' ' . $log['author'] . ' ' . $log['message']) }}">
                                            <td>
                                                <div class="d-flex align-items-center gap-1">
                                                    <code class="fw-bold text-primary">{{ substr($log['hash'], 0, 7) }}</code>
                                                    <button class="btn btn-link btn-sm p-0 text-muted copy-hash-btn" data-hash="{{ $log['hash'] }}" title="Copy full hash">
                                                        <i class="bx bx-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark" style="font-size:13px;">{{ $log['author'] }}</span>
                                            </td>
                                            <td>
                                                <span class="text-muted" style="font-size:12px;">{{ date('d-M-Y H:i', strtotime($log['date'])) }}</span>
                                            </td>
                                            <td>
                                                <div class="text-dark" style="font-size:13px; max-width: 600px; white-space: normal;">
                                                    {{ $log['message'] }}
                                                </div>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group btn-group-sm">
                                                    {{-- View Diff / Details --}}
                                                    <button type="button" class="btn btn-outline-info view-commit-btn" data-hash="{{ $log['hash'] }}" title="View Commit Diff & Details">
                                                        <i class="bx bx-show"></i> Diff
                                                    </button>

                                                    {{-- Revert Commit --}}
                                                    <button type="button" class="btn btn-outline-warning revert-commit-btn" data-hash="{{ $log['hash'] }}" data-message="{{ $log['message'] }}" title="Revert this commit (git revert)">
                                                        <i class="bx bx-undo"></i> Revert
                                                    </button>

                                                    {{-- Rollback / Reset Options --}}
                                                    <button type="button" class="btn btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Rollback Options">
                                                        <i class="bx bx-reset"></i> Rollback
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow" style="font-size:12px;">
                                                        <li><h6 class="dropdown-header text-uppercase">Reset to this Commit</h6></li>
                                                        <li>
                                                            <a class="dropdown-item reset-commit-item text-warning" href="javascript:void(0);" data-hash="{{ $log['hash'] }}" data-type="soft">
                                                                <i class="bx bx-undo me-1"></i> <strong>Soft Reset</strong> (keep changes staged)
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item reset-commit-item text-danger" href="javascript:void(0);" data-hash="{{ $log['hash'] }}" data-type="hard">
                                                                <i class="bx bx-trash me-1"></i> <strong>Hard Reset</strong> (discard all newer changes)
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item checkout-commit-item text-primary" href="javascript:void(0);" data-hash="{{ $log['hash'] }}">
                                                                <i class="bx bx-git-repo-forked me-1"></i> Checkout Commit (detached)
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="text-center text-muted py-4">
                                <p class="mb-0">No commits found</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Universal Output & Diff Modal -->
        <div class="modal fade" id="outputModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="outputModalTitle">Git Output</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="copyOutputBtn" title="Copy to clipboard">
                                <i class="bx bx-copy me-1"></i> Copy
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body p-0">
                        <div id="diffContainer" class="p-3 bg-dark text-light font-monospace" style="font-size:12px; line-height:1.5; max-height: 600px; overflow-y: auto; white-space: pre-wrap;"></div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <div id="modalCustomActions"></div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        const csrfToken = '{{ csrf_token() }}';
        let isLoading = false;

        function showLoading(button) {
            isLoading = true;
            const originalHtml = $(button).html();
            $(button).data('original-html', originalHtml);
            $(button).prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Processing...');
        }

        function hideLoading(button) {
            isLoading = false;
            const originalHtml = $(button).data('original-html');
            if (originalHtml) {
                $(button).html(originalHtml);
            }
            $(button).prop('disabled', false);
        }

        function formatDiff(rawText) {
            if (!rawText) return '<span class="text-muted">Empty output</span>';
            const lines = rawText.split('\n');
            let formatted = '';
            lines.forEach(function(line) {
                let escaped = $('<div>').text(line).html();
                if (line.startsWith('+') && !line.startsWith('+++')) {
                    formatted += `<div style="background: rgba(46, 160, 67, 0.2); color: #7ee787;">${escaped}</div>`;
                } else if (line.startsWith('-') && !line.startsWith('---')) {
                    formatted += `<div style="background: rgba(248, 81, 73, 0.2); color: #ffa198;">${escaped}</div>`;
                } else if (line.startsWith('@@')) {
                    formatted += `<div style="background: rgba(56, 139, 253, 0.2); color: #79c0ff; font-weight: bold;">${escaped}</div>`;
                } else if (line.startsWith('commit ') || line.startsWith('Author:') || line.startsWith('Date:')) {
                    formatted += `<div style="color: #d2a8ff; font-weight: bold;">${escaped}</div>`;
                } else {
                    formatted += `<div>${escaped}</div>`;
                }
            });
            return formatted;
        }

        function showOutput(title, content, isError = false, customActionsHtml = '') {
            $('#outputModalTitle').text(title);
            $('#diffContainer').html(formatDiff(content));
            $('#modalCustomActions').html(customActionsHtml);
            $('#outputModal').modal('show');
        }

        $('#copyOutputBtn').on('click', function() {
            const raw = $('#diffContainer').text();
            navigator.clipboard.writeText(raw).then(() => {
                showAlert("success", "ri-checkbox-circle-line", "Copied to clipboard!");
            });
        });

        function executeGitOperation(url, data, button, successMessage) {
            if (isLoading) return;
            showLoading(button);

            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    ...data,
                    _token: csrfToken
                },
                success: function(response) {
                    hideLoading(button);
                    if (response.status === 200) {
                        showAlert("success", "ri-checkbox-circle-line", response.message || successMessage);
                        if (response.output) {
                            showOutput(successMessage, response.output);
                        }
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showAlert("error", "ri-close-circle-line", response.message || 'Operation failed');
                        if (response.error) {
                            showOutput('Error', response.error, true);
                        }
                    }
                },
                error: function(xhr) {
                    hideLoading(button);
                    const errorMsg = xhr.responseJSON?.message || 'Operation failed';
                    showAlert("error", "ri-close-circle-line", errorMsg);
                    if (xhr.responseJSON?.error) {
                        showOutput('Error', xhr.responseJSON.error, true);
                    }
                }
            });
        }

        // ==================== COMMIT HISTORY ACTIONS ====================

        // Filter commits in realtime
        $('#commitFilterInput').on('keyup', function() {
            const val = $(this).val().toLowerCase().trim();
            let count = 0;
            $('.commit-row').each(function() {
                const search = $(this).data('search') || '';
                if (!val || search.indexOf(val) > -1) {
                    $(this).show();
                    count++;
                } else {
                    $(this).hide();
                }
            });
            $('#commitCountBadge').text(count + ' Commits');
        });

        // Copy commit hash
        $(document).on('click', '.copy-hash-btn', function() {
            const hash = $(this).data('hash');
            navigator.clipboard.writeText(hash).then(() => {
                showAlert("success", "ri-checkbox-circle-line", `Commit hash ${hash.substring(0, 7)} copied!`);
            });
        });

        // View Commit Diff & Details
        $(document).on('click', '.view-commit-btn', function() {
            const hash = $(this).data('hash');
            const btn = $(this);
            showLoading(btn);

            $.ajax({
                url: '{{ route("version-control.show") }}',
                method: 'GET',
                data: { commit: hash },
                success: function(res) {
                    hideLoading(btn);
                    if (res.status === 200) {
                        const customBtn = `
                            <button type="button" class="btn btn-warning btn-sm" onclick="triggerRevertFromModal('${hash}')">
                                <i class="bx bx-undo me-1"></i> Revert This Commit
                            </button>
                        `;
                        showOutput(`Commit ${hash.substring(0, 7)} Details & Diff`, res.data.output, false, customBtn);
                    } else {
                        showAlert("error", "ri-close-circle-line", res.message || 'Failed to load commit details');
                    }
                },
                error: function() {
                    hideLoading(btn);
                    showAlert("error", "ri-close-circle-line", 'Error loading commit details');
                }
            });
        });

        // Revert Commit (with Confirmation)
        $(document).on('click', '.revert-commit-btn', function() {
            const hash = $(this).data('hash');
            const msg = $(this).data('message') || '';
            const btn = $(this);

            Swal.fire({
                title: 'Revert Commit?',
                html: `<p>Are you sure you want to revert commit <code>${hash.substring(0, 7)}</code>?</p><div class="alert alert-secondary py-1 px-2 text-start small"><strong>Message:</strong> ${msg}</div><p class="small text-muted mb-0">This creates a new commit that inverts the changes of this commit.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Revert Commit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.revert") }}',
                        { commit: hash },
                        btn,
                        `Commit ${hash.substring(0, 7)} reverted successfully`
                    );
                }
            });
        });

        window.triggerRevertFromModal = function(hash) {
            $('#outputModal').modal('hide');
            $(`.revert-commit-btn[data-hash="${hash}"]`).trigger('click');
        };

        // Reset to specific commit (Soft / Hard)
        $(document).on('click', '.reset-commit-item', function() {
            const hash = $(this).data('hash');
            const type = $(this).data('type'); // 'soft' or 'hard'

            if (type === 'hard') {
                Swal.fire({
                    title: 'HARD Reset to Commit?',
                    html: `<p class="text-danger fw-bold">DANGER: This will permanently discard all uncommitted changes and newer commits!</p><p>Repository HEAD will be forcefully set to: <code>${hash.substring(0, 7)}</code></p>`,
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Hard Reset',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeGitOperation(
                            '{{ route("version-control.reset") }}',
                            { type: 'hard', commit: hash },
                            $('#refreshStatusBtn'),
                            `Hard reset to ${hash.substring(0, 7)} completed`
                        );
                    }
                });
            } else {
                Swal.fire({
                    title: 'Soft Reset to Commit?',
                    html: `<p>Reset HEAD to <code>${hash.substring(0, 7)}</code>?</p><p class="small text-muted mb-0">All newer changes will remain staged in your working directory.</p>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Soft Reset',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeGitOperation(
                            '{{ route("version-control.reset") }}',
                            { type: 'soft', commit: hash },
                            $('#refreshStatusBtn'),
                            `Soft reset to ${hash.substring(0, 7)} completed`
                        );
                    }
                });
            }
        });

        // Checkout detached commit
        $(document).on('click', '.checkout-commit-item', function() {
            const hash = $(this).data('hash');
            Swal.fire({
                title: 'Checkout Commit?',
                html: `<p>Checkout commit <code>${hash.substring(0, 7)}</code> in detached HEAD state?</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Checkout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.checkout") }}',
                        { branch: hash },
                        $('#refreshStatusBtn'),
                        `Checked out ${hash.substring(0, 7)}`
                    );
                }
            });
        });

        // Reload commits with selected limit
        $('#commitLimitSelect, #reloadLogsBtn').on('change click', function() {
            const limit = $('#commitLimitSelect').val();
            showLoading($('#reloadLogsBtn'));

            $.ajax({
                url: '{{ route("version-control.log") }}',
                method: 'GET',
                data: { limit: limit },
                success: function(res) {
                    hideLoading($('#reloadLogsBtn'));
                    if (res.status === 200 && res.data) {
                        let html = `
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle mb-0" id="commitsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="110">Hash</th>
                                            <th width="180">Author</th>
                                            <th width="160">Date</th>
                                            <th>Commit Message</th>
                                            <th width="220" class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        res.data.forEach(function(log) {
                            const dateFormatted = new Date(log.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                            html += `
                                <tr class="commit-row" data-search="${(log.hash + ' ' + log.author + ' ' + log.message).toLowerCase()}">
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <code class="fw-bold text-primary">${log.hash.substring(0, 7)}</code>
                                            <button class="btn btn-link btn-sm p-0 text-muted copy-hash-btn" data-hash="${log.hash}" title="Copy full hash">
                                                <i class="bx bx-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td><span class="fw-semibold text-dark" style="font-size:13px;">${log.author}</span></td>
                                    <td><span class="text-muted" style="font-size:12px;">${dateFormatted}</span></td>
                                    <td><div class="text-dark" style="font-size:13px; max-width: 600px; white-space: normal;">${log.message}</div></td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info view-commit-btn" data-hash="${log.hash}" title="View Commit Diff & Details">
                                                <i class="bx bx-show"></i> Diff
                                            </button>
                                            <button type="button" class="btn btn-outline-warning revert-commit-btn" data-hash="${log.hash}" data-message="${log.message}" title="Revert this commit">
                                                <i class="bx bx-undo"></i> Revert
                                            </button>
                                            <button type="button" class="btn btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bx bx-reset"></i> Rollback
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow" style="font-size:12px;">
                                                <li><h6 class="dropdown-header text-uppercase">Reset to this Commit</h6></li>
                                                <li><a class="dropdown-item reset-commit-item text-warning" href="javascript:void(0);" data-hash="${log.hash}" data-type="soft"><i class="bx bx-undo me-1"></i> <strong>Soft Reset</strong></a></li>
                                                <li><a class="dropdown-item reset-commit-item text-danger" href="javascript:void(0);" data-hash="${log.hash}" data-type="hard"><i class="bx bx-trash me-1"></i> <strong>Hard Reset</strong></a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item checkout-commit-item text-primary" href="javascript:void(0);" data-hash="${log.hash}"><i class="bx bx-git-repo-forked me-1"></i> Checkout Commit</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table></div>`;
                        $('#commitsContainer').html(html);
                        $('#commitCountBadge').text(res.data.length + ' Commits');
                    }
                },
                error: function() {
                    hideLoading($('#reloadLogsBtn'));
                }
            });
        });

        // ==================== CHANGED FILES ACTIONS ====================

        // View single file diff
        $(document).on('click', '.diff-file-btn', function() {
            const file = $(this).data('file');
            const btn = $(this);
            showLoading(btn);

            $.ajax({
                url: '{{ route("version-control.diff") }}',
                method: 'GET',
                data: { file: file },
                success: function(res) {
                    hideLoading(btn);
                    if (res.status === 200) {
                        if (res.data.has_changes) {
                            showOutput(`Diff: ${file}`, res.data.diff);
                        } else {
                            showAlert("info", "ri-information-line", 'No changes in this file');
                        }
                    }
                },
                error: function() {
                    hideLoading(btn);
                    showAlert("error", "ri-close-circle-line", 'Failed to get file diff');
                }
            });
        });

        // Discard single file changes
        $(document).on('click', '.discard-file-btn', function() {
            const file = $(this).data('file');
            const btn = $(this);

            Swal.fire({
                title: 'Discard Changes?',
                html: `<p>Discard all uncommitted changes in: <br><code>${file}</code>?</p><p class="text-danger small mb-0">This action cannot be undone.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Discard',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.discard") }}',
                        { file: file },
                        btn,
                        `Changes discarded in ${file}`
                    );
                }
            });
        });

        // Stage single file
        $(document).on('click', '.add-file-btn', function() {
            const file = $(this).data('file');
            executeGitOperation(
                '{{ route("version-control.add") }}',
                { files: file },
                $(this),
                `File '${file}' staged`
            );
        });

        // ==================== BASIC GIT OPERATIONS ====================

        // Stage files
        $('#addBtn').on('click', function() {
            const files = $('#addFilesInput').val() || '.';
            executeGitOperation(
                '{{ route("version-control.add") }}',
                { files: files },
                $(this),
                'Files staged successfully'
            );
        });

        // Commit changes
        $('#commitBtn').on('click', function() {
            const message = $('#commitMessageInput').val().trim();
            if (!message) {
                showAlert("warning", "ri-error-warning-line", 'Please enter a commit message');
                return;
            }
            executeGitOperation(
                '{{ route("version-control.commit") }}',
                { message: message },
                $(this),
                'Changes committed successfully'
            );
        });

        // Push
        $('#pushBtn').on('click', function() {
            executeGitOperation(
                '{{ route("version-control.push") }}',
                { force: false },
                $(this),
                'Push completed successfully'
            );
        });

        // Force Push
        $('#forcePushBtn').on('click', function() {
            Swal.fire({
                title: 'Force Push?',
                text: 'Force push may overwrite remote changes. Are you sure?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, force push',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.push") }}',
                        { force: true },
                        $('#forcePushBtn'),
                        'Force push completed successfully'
                    );
                }
            });
        });

        // Pull
        $('#pullBtn').on('click', function() {
            executeGitOperation(
                '{{ route("version-control.pull") }}',
                {},
                $(this),
                'Pull completed successfully'
            );
        });

        // Fetch
        $('#fetchBtn').on('click', function() {
            executeGitOperation(
                '{{ route("version-control.fetch") }}',
                {},
                $(this),
                'Fetch completed successfully'
            );
        });

        // Checkout branch
        $('#checkoutBtn').on('click', function() {
            const branch = $('#branchSelect').val();
            Swal.fire({
                title: 'Switch Branch?',
                text: `Switch to branch '${branch}'?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, switch',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.checkout") }}',
                        { branch: branch },
                        $(this),
                        'Switched branch successfully'
                    );
                }
            });
        });

        // View All Diff
        $('#viewDiffBtn').on('click', function() {
            showLoading($(this));
            $.ajax({
                url: '{{ route("version-control.diff") }}',
                method: 'GET',
                success: function(response) {
                    hideLoading($('#viewDiffBtn'));
                    if (response.status === 200) {
                        if (response.data.has_changes) {
                            showOutput('Uncommitted Git Diff', response.data.diff);
                        } else {
                            showAlert("info", "ri-information-line", 'No uncommitted changes');
                        }
                    }
                },
                error: function() {
                    hideLoading($('#viewDiffBtn'));
                    showAlert("error", "ri-close-circle-line", 'Failed to get diff');
                }
            });
        });

        // Check Conflicts
        $('#viewConflictsBtn').on('click', function() {
            showLoading($(this));
            $.ajax({
                url: '{{ route("version-control.conflicts") }}',
                method: 'GET',
                success: function(response) {
                    hideLoading($('#viewConflictsBtn'));
                    if (response.status === 200) {
                        if (response.data.has_conflicts) {
                            let conflictOutput = 'Conflicting files:\n\n';
                            response.data.files.forEach(function(file) {
                                conflictOutput += `- ${file}\n`;
                            });
                            showOutput('Merge Conflicts', conflictOutput, true);
                        } else {
                            showAlert("success", "ri-checkbox-circle-line", 'No conflicts found');
                        }
                    }
                },
                error: function() {
                    hideLoading($('#viewConflictsBtn'));
                    showAlert("error", "ri-close-circle-line", 'Failed to check conflicts');
                }
            });
        });

        // Create Branch
        $('#createBranchBtn').on('click', function() {
            const branchName = $('#newBranchName').val().trim();
            if (!branchName) {
                showAlert("warning", "ri-error-warning-line", 'Please enter a branch name');
                return;
            }
            executeGitOperation(
                '{{ route("version-control.create-branch") }}',
                { branch_name: branchName, checkout: true },
                $(this),
                'Branch created successfully'
            );
        });

        // Merge Branch
        $('#mergeBtn').on('click', function() {
            const branchName = $('#mergeBranchSelect').val();
            if (!branchName) {
                showAlert("warning", "ri-error-warning-line", 'Please select a branch to merge');
                return;
            }
            Swal.fire({
                title: 'Merge Branch?',
                text: `Merge '${branchName}' into current branch?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, merge',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.merge") }}',
                        { branch_name: branchName, no_ff: false },
                        $('#mergeBtn'),
                        'Branch merged successfully'
                    );
                }
            });
        });

        // Delete Branch
        $('#deleteBranchBtn').on('click', function() {
            const branchName = $('#deleteBranchSelect').val();
            if (!branchName) {
                showAlert("warning", "ri-error-warning-line", 'Please select a branch to delete');
                return;
            }
            Swal.fire({
                title: 'Delete Branch?',
                text: `Delete branch '${branchName}'? This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.delete-branch") }}',
                        { branch_name: branchName, force: false },
                        $('#deleteBranchBtn'),
                        'Branch deleted successfully'
                    );
                }
            });
        });

        // Stash Save
        $('#stashSaveBtn').on('click', function() {
            Swal.fire({
                title: 'Stash Changes',
                input: 'text',
                inputLabel: 'Stash Message',
                inputPlaceholder: 'Enter stash message (optional)',
                showCancelButton: true,
                confirmButtonText: 'Save Stash',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.stash") }}',
                        { action: 'save', message: result.value || 'WIP' },
                        $('#stashSaveBtn'),
                        'Changes stashed successfully'
                    );
                }
            });
        });

        // Stash List
        $('#stashListBtn').on('click', function() {
            showLoading($(this));
            $.ajax({
                url: '{{ route("version-control.stash") }}',
                method: 'POST',
                data: { action: 'list', _token: csrfToken },
                success: function(response) {
                    hideLoading($('#stashListBtn'));
                    if (response.status === 200) {
                        showOutput('Git Stashes', response.output || 'No stashes found');
                    }
                },
                error: function() {
                    hideLoading($('#stashListBtn'));
                    showAlert("error", "ri-close-circle-line", 'Failed to list stashes');
                }
            });
        });

        // Stash Pop
        $('#stashPopBtn').on('click', function() {
            executeGitOperation(
                '{{ route("version-control.stash") }}',
                { action: 'pop' },
                $(this),
                'Stash popped successfully'
            );
        });

        // Stash Clear
        $('#stashClearBtn').on('click', function() {
            Swal.fire({
                title: 'Clear All Stashes?',
                text: 'This will delete all saved stashes permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, clear all',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeGitOperation(
                        '{{ route("version-control.stash") }}',
                        { action: 'clear' },
                        $('#stashClearBtn'),
                        'All stashes cleared'
                    );
                }
            });
        });

        // Create Tag
        $('#createTagBtn').on('click', function() {
            const tagName = $('#tagName').val().trim();
            const tagMessage = $('#tagMessage').val().trim();
            if (!tagName) {
                showAlert("warning", "ri-error-warning-line", 'Please enter a tag name');
                return;
            }
            executeGitOperation(
                '{{ route("version-control.tag") }}',
                { action: 'create', tag_name: tagName, message: tagMessage },
                $(this),
                `Tag '${tagName}' created successfully`
            );
        });

        // List Tags
        $('#listTagsBtn').on('click', function() {
            showLoading($(this));
            $.ajax({
                url: '{{ route("version-control.tag") }}',
                method: 'POST',
                data: { action: 'list', _token: csrfToken },
                success: function(response) {
                    hideLoading($('#listTagsBtn'));
                    if (response.status === 200) {
                        showOutput('Git Tags', response.output || 'No tags found');
                    }
                },
                error: function() {
                    hideLoading($('#listTagsBtn'));
                    showAlert("error", "ri-close-circle-line", 'Failed to list tags');
                }
            });
        });

        // Init Repo
        $('#initRepoBtn').on('click', function() {
            executeGitOperation(
                '{{ route("version-control.init") }}',
                {},
                $(this),
                'Git repository initialized successfully'
            );
        });
    });
</script>
@endpush