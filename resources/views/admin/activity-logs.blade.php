@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold" style="color:#15648C;">
                        <i class="ri-history-line me-2"></i>Activity Log Report
                    </h4>
                    <button class="btn btn-sm btn-outline-success" id="btnExport">
                        <i class="ri-file-excel-2-line me-1"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row mb-3" id="statsCards">
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-body text-center py-3">
                        <i class="ri-pulse-line" style="font-size:22px;color:#405189;"></i>
                        <div class="fw-bold mt-1" style="font-size:11px;color:#666;">Total</div>
                        <div class="fw-bold" style="font-size:20px;color:#405189;" id="statTotal">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-body text-center py-3">
                        <i class="ri-eye-line" style="font-size:22px;color:#0ab39c;"></i>
                        <div class="fw-bold mt-1" style="font-size:11px;color:#666;">Viewed</div>
                        <div class="fw-bold" style="font-size:20px;color:#0ab39c;" id="statViewed">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-body text-center py-3">
                        <i class="ri-add-circle-line" style="font-size:22px;color:#3577f1;"></i>
                        <div class="fw-bold mt-1" style="font-size:11px;color:#666;">Created</div>
                        <div class="fw-bold" style="font-size:20px;color:#3577f1;" id="statCreated">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-body text-center py-3">
                        <i class="ri-edit-line" style="font-size:22px;color:#f7b84b;"></i>
                        <div class="fw-bold mt-1" style="font-size:11px;color:#666;">Updated</div>
                        <div class="fw-bold" style="font-size:20px;color:#f7b84b;" id="statUpdated">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-body text-center py-3">
                        <i class="ri-delete-bin-line" style="font-size:22px;color:#f06548;"></i>
                        <div class="fw-bold mt-1" style="font-size:11px;color:#666;">Deleted</div>
                        <div class="fw-bold" style="font-size:20px;color:#f06548;" id="statDeleted">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-body text-center py-3">
                        <i class="ri-team-line" style="font-size:22px;color:#299cdb;"></i>
                        <div class="fw-bold mt-1" style="font-size:11px;color:#666;">Users</div>
                        <div class="fw-bold" style="font-size:20px;color:#299cdb;" id="statUsers">0</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row align-items-end g-2">
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Date From</label>
                        <input type="date" class="form-control form-control-sm" id="filterDateFrom" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Date To</label>
                        <input type="date" class="form-control form-control-sm" id="filterDateTo" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">User</label>
                        <select class="form-select form-select-sm" id="filterUser">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->employee_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Module</label>
                        <select class="form-select form-select-sm" id="filterModule">
                            <option value="">All Modules</option>
                            @foreach($modules as $module)
                                <option value="{{ $module }}">{{ $module }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Event</label>
                        <select class="form-select form-select-sm" id="filterEvent">
                            <option value="">All Events</option>
                            @foreach($events as $event)
                                <option value="{{ $event }}">{{ ucfirst($event) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Per Page</label>
                        <select class="form-select form-select-sm" id="filterPerPage">
                            <option value="50">50</option>
                            <option value="100" selected>100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-primary btn-sm w-100 mt-3" id="btnLoadLogs">
                            <i class="ri-search-line me-1"></i>Search
                        </button>
                    </div>
                </div>
                {{-- Advanced Filters --}}
                <div class="collapse mt-2" id="advancedFilters">
                    <div class="row align-items-end g-2">
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-1" style="font-size:11px;">Subject Type</label>
                            <input type="text" class="form-control form-control-sm" id="filterSubjectType" placeholder="e.g. EmailTemplate, User..." aria-label="e.g. EmailTemplate, User">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold mb-1" style="font-size:11px;">Search</label>
                            <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Search description, properties, user..." aria-label="Search description, properties, user">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary btn-sm w-100" id="btnResetFilters">
                                <i class="ri-refresh-line me-1"></i>Reset
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-1">
                    <a class="text-muted small" data-bs-toggle="collapse" href="#advancedFilters" style="font-size:10px;text-decoration:none;">
                        <i class="ri-arrow-down-s-line"></i> Advanced Filters
                    </a>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tabLogs"><i class="ri-list-check me-1"></i>Activity Logs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabModuleSummary"><i class="ri-pie-chart-line me-1"></i>Module Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabUserSummary"><i class="ri-user-star-line me-1"></i>User Summary</a>
            </li>
        </ul>

        <div class="tab-content mt-0">
            {{-- Activity Logs Tab --}}
            <div class="tab-pane fade show active" id="tabLogs">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted" id="logCount">Showing 0 records</small>
                            <nav id="logPagination"></nav>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0" style="font-size:11px;">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>Date & Time</th>
                                        <th>User</th>
                                        <th>Event</th>
                                        <th>Module</th>
                                        <th>Description</th>
                                        <th>Subject / Employee</th>
                                        <th class="text-center">Changes</th>
                                        <th class="text-center">Details</th>
                                    </tr>
                                </thead>
                                <tbody id="logsBody">
                                    <tr><td colspan="9" class="text-center py-4 text-muted">Click "Search" to load activity logs</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Module Summary Tab --}}
            <div class="tab-pane fade" id="tabModuleSummary">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>Module (Log Name)</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Viewed</th>
                                        <th class="text-center">Created</th>
                                        <th class="text-center">Updated</th>
                                        <th class="text-center">Deleted</th>
                                    </tr>
                                </thead>
                                <tbody id="moduleSummaryBody">
                                    <tr><td colspan="7" class="text-center py-4 text-muted">Click "Search" to load</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- User Summary Tab --}}
            <div class="tab-pane fade" id="tabUserSummary">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>User</th>
                                        <th>Employee ID</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Created</th>
                                        <th class="text-center">Updated</th>
                                        <th class="text-center">Deleted</th>
                                        <th>Last Activity</th>
                                    </tr>
                                </thead>
                                <tbody id="userSummaryBody">
                                    <tr><td colspan="8" class="text-center py-4 text-muted">Click "Search" to load</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                <h5 class="modal-title text-white"><i class="ri-information-line me-1"></i>Activity Detail</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const eventColors = { viewed: '#0ab39c', created: '#3577f1', updated: '#f7b84b', deleted: '#f06548' };
const eventIcons = { viewed: 'ri-eye-line', created: 'ri-add-circle-line', updated: 'ri-edit-line', deleted: 'ri-delete-bin-line' };
let currentPage = 1;
let lastLogs = [];

function getFilters() {
    return {
        date_from:    $('#filterDateFrom').val(),
        date_to:      $('#filterDateTo').val(),
        user_id:      $('#filterUser').val(),
        module:       $('#filterModule').val(),
        event:        $('#filterEvent').val(),
        subject_type: $('#filterSubjectType').val(),
        search:       $('#filterSearch').val(),
        page:         currentPage,
        per_page:     $('#filterPerPage').val() || 100
    };
}

function loadLogs() {
    const filters = getFilters();
    $('#logsBody').html('<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ url("activity-log-data") }}', filters, function(res) {
        if (!res.success || !res.data.logs.length) {
            $('#logsBody').html('<tr><td colspan="9" class="text-center py-3 text-muted">No activity logs found.</td></tr>');
            updateStats({ total_actions:0, total_viewed:0, total_created:0, total_updated:0, total_deleted:0, unique_users:0 });
            $('#logCount').text('Showing 0 records');
            $('#logPagination').html('');
            return;
        }

        const logs = res.data.logs;
        lastLogs = logs;
        const pagination = res.data.pagination;
        updateStats(res.data.stats);

        let html = '';
        const offset = (pagination.current_page - 1) * pagination.per_page;
        logs.forEach(function(log, i) {
            const displayEvent = log.derived_event || log.event || 'view';
            const color = eventColors[displayEvent] || '#666';
            const icon = eventIcons[displayEvent] || 'ri-question-line';
            const dt = log.created_at ? new Date(log.created_at).toLocaleString('en-IN', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '-';
            const subjectShort = log.subject_type ? log.subject_type.split('\\').pop() : '-';

            // Extract key info from properties
            let props = log.properties || {};
            let infoSnippet = '';
            if (props.url) {
                const urlPath = props.url.replace(/https?:\/\/[^\/]+/, '').substring(0, 40);
                infoSnippet += '<div style="font-size:9px;color:#666;"><i class="ri-link me-1"></i>' + urlPath + '</div>';
            }
            if (props.request_data && typeof props.request_data === 'object') {
                const keys = Object.keys(props.request_data).filter(k => !['_token','columns','draw','start','length','search'].includes(k));
                if (keys.length > 0) {
                    const snippet = keys.slice(0, 3).map(k => '<span class="text-primary">' + k + '</span>=' + String(props.request_data[k] || '').substring(0, 15)).join(', ');
                    infoSnippet += '<div style="font-size:9px;">' + snippet + (keys.length > 3 ? '...' : '') + '</div>';
                }
            }
            if (props.old && props.attributes) {
                const changedKeys = Object.keys(props.attributes).slice(0, 3).join(', ');
                infoSnippet += '<div style="font-size:9px;color:#f7b84b;"><i class="ri-edit-line me-1"></i>Changed: ' + changedKeys + '</div>';
            }
            if (props.ip_address) {
                infoSnippet += '<div style="font-size:9px;color:#999;"><i class="ri-computer-line me-1"></i>' + props.ip_address + '</div>';
            }

            html += '<tr class="log-row" data-log-idx="' + i + '">';
            html += '<td class="text-center">' + (offset + i + 1) + '</td>';
            html += '<td style="white-space:nowrap;">' + dt + '</td>';
            html += '<td><strong>' + (log.user_name || '-') + '</strong><br><small class="text-muted">' + (log.employee_id || '') + '</small></td>';
            html += '<td><span class="badge" style="background:' + color + ';font-size:9px;"><i class="' + icon + ' me-1"></i>' + displayEvent + '</span></td>';
            html += '<td><span class="badge bg-light text-dark" style="font-size:9px;">' + (log.log_name || '-') + '</span></td>';
            html += '<td>' + (log.description || '-') + '<br>' + infoSnippet + '</td>';

            // Subject + affected employee
            let subjectCell = '<small class="text-muted">' + subjectShort + (log.subject_id ? ' #' + log.subject_id : '') + '</small>';
            if (log.subject_employee_name) {
                subjectCell += '<br><span style="font-size:10px;font-weight:600;color:#1e3a5f;">'
                    + (log.subject_emp_code ? log.subject_emp_code + ' — ' : '')
                    + log.subject_employee_name + '</span>';
            }
            html += '<td>' + subjectCell + '</td>';

            // Changed fields summary in list
            const diffCount = (log.diff || []).length;
            const changesCell = diffCount > 0
                ? '<span class="badge bg-warning-subtle text-warning" style="font-size:9px;">'
                    + diffCount + ' field' + (diffCount > 1 ? 's' : '') + '</span>'
                : '<span class="text-muted" style="font-size:9px;">—</span>';
            html += '<td class="text-center">' + changesCell + '</td>';

            html += '<td class="text-center"><button class="btn btn-sm btn-outline-info btn-view-detail" data-log-idx="' + i + '" title="View full details"><i class="ri-eye-line"></i></button></td>';
            html += '</tr>';        });
        $('#logsBody').html(html);
        $('#logCount').text('Showing ' + logs.length + ' of ' + pagination.total + ' records');
        renderPagination(pagination);
    });

    // Module summary
    $.get('{{ url("activity-log-module-summary") }}', filters, function(res) {
        if (!res.success || !res.data.length) { $('#moduleSummaryBody').html('<tr><td colspan="7" class="text-center py-3 text-muted">No data</td></tr>'); return; }
        let html = '';
        res.data.forEach(function(m, i) {
            html += '<tr><td>' + (i+1) + '</td><td class="fw-semibold">' + (m.log_name || '-') + '</td>';
            html += '<td class="text-center fw-bold">' + m.total + '</td>';
            html += '<td class="text-center">' + m.viewed + '</td>';
            html += '<td class="text-center text-primary">' + m.created + '</td>';
            html += '<td class="text-center text-warning">' + m.updated + '</td>';
            html += '<td class="text-center text-danger">' + m.deleted + '</td></tr>';
        });
        $('#moduleSummaryBody').html(html);
    });

    // User summary
    $.get('{{ url("activity-log-user-summary") }}', filters, function(res) {
        if (!res.success || !res.data.length) { $('#userSummaryBody').html('<tr><td colspan="8" class="text-center py-3 text-muted">No data</td></tr>'); return; }
        let html = '';
        res.data.forEach(function(u, i) {
            const lastAct = u.last_activity ? new Date(u.last_activity).toLocaleString('en-IN', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' }) : '-';
            html += '<tr><td>' + (i+1) + '</td><td class="fw-semibold">' + (u.user_name || '-') + '</td>';
            html += '<td>' + (u.employee_id || '-') + '</td>';
            html += '<td class="text-center fw-bold">' + u.total + '</td>';
            html += '<td class="text-center text-primary">' + u.created + '</td>';
            html += '<td class="text-center text-warning">' + u.updated + '</td>';
            html += '<td class="text-center text-danger">' + u.deleted + '</td>';
            html += '<td>' + lastAct + '</td></tr>';
        });
        $('#userSummaryBody').html(html);
    });
}

function updateStats(stats) {
    $('#statTotal').text(stats.total_actions || 0);
    $('#statViewed').text(stats.total_viewed || 0);
    $('#statCreated').text(stats.total_created || 0);
    $('#statUpdated').text(stats.total_updated || 0);
    $('#statDeleted').text(stats.total_deleted || 0);
    $('#statUsers').text(stats.unique_users || 0);
}

function renderPagination(p) {
    if (p.last_page <= 1) { $('#logPagination').html(''); return; }
    let html = '<ul class="pagination pagination-sm mb-0">';
    html += '<li class="page-item ' + (p.current_page === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (p.current_page - 1) + '">&lsaquo;</a></li>';
    const start = Math.max(1, p.current_page - 2);
    const end = Math.min(p.last_page, p.current_page + 2);
    for (let i = start; i <= end; i++) {
        html += '<li class="page-item ' + (i === p.current_page ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
    }
    html += '<li class="page-item ' + (p.current_page === p.last_page ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (p.current_page + 1) + '">&rsaquo;</a></li>';
    html += '</ul>';
    $('#logPagination').html(html);
}

// View detail
$(document).on('click', '.btn-view-detail', function() {
    const idx = $(this).data('log-idx');
    const log = lastLogs[idx];
    if (!log) return;

    const props = log.properties || {};
    const displayEvent = log.derived_event || log.event || 'view';
    const eventColor = eventColors[displayEvent] || '#666';
    const dt = log.created_at ? new Date(log.created_at).toLocaleString('en-IN', {
        day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'
    }) : '-';

    let html = '';

    // ── Who did what, when ─────────────────────────────────────────────────
    html += '<div class="row g-2 mb-3">';

    // Performer (causer)
    html += '<div class="col-md-6">';
    html += '<div class="p-2 rounded" style="background:#f0f4ff;border:1px solid #c7d4f0;">';
    html += '<div style="font-size:10px;text-transform:uppercase;color:#666;font-weight:600;">Performed By</div>';
    html += '<div class="fw-bold" style="font-size:13px;color:#1e3a5f;">' + (log.user_name || 'System') + '</div>';
    if (log.employee_id) html += '<div style="font-size:11px;color:#555;"><i class="ri-id-card-line me-1"></i>Emp ID: ' + log.employee_id + '</div>';
    html += '<div style="font-size:11px;color:#777;"><i class="ri-time-line me-1"></i>' + dt + '</div>';
    html += '</div></div>';

    // Affected employee (subject)
    if (log.subject_employee_id) {
        html += '<div class="col-md-6">';
        html += '<div class="p-2 rounded" style="background:#fff8e1;border:1px solid #ffe082;">';
        html += '<div style="font-size:10px;text-transform:uppercase;color:#666;font-weight:600;">Affected Employee</div>';
        html += '<div class="fw-bold" style="font-size:13px;color:#1e3a5f;">' + (log.subject_employee_name || 'Unknown') + '</div>';
        html += '<div style="font-size:11px;color:#555;"><i class="ri-id-card-line me-1"></i>';
        if (log.subject_emp_code) html += 'Code: ' + log.subject_emp_code + ' &nbsp;·&nbsp; ';
        html += 'ID: ' + log.subject_employee_id + '</div>';
        html += '</div></div>';
    } else {
        html += '<div class="col-md-6">';
        html += '<div class="p-2 rounded" style="background:#f8f9fa;border:1px solid #dee2e6;">';
        html += '<div style="font-size:10px;text-transform:uppercase;color:#666;font-weight:600;">Record</div>';
        html += '<div class="fw-bold" style="font-size:13px;">' + (log.subject_type ? log.subject_type.split('\\').pop() : '-') + (log.subject_id ? ' #' + log.subject_id : '') + '</div>';
        html += '</div></div>';
    }
    html += '</div>';

    // ── Event + Module badges ──────────────────────────────────────────────
    html += '<div class="d-flex gap-2 mb-3 flex-wrap">';
    html += '<span class="badge" style="background:' + eventColor + ';font-size:11px;padding:5px 10px;"><i class="' + (eventIcons[displayEvent]||'ri-question-line') + ' me-1"></i>' + displayEvent + '</span>';
    if (log.event === null) {
        html += '<span class="badge bg-light text-secondary border" style="font-size:10px;padding:3px 7px;"><i class="ri-information-line me-1"></i>derived</span>';
    }
    html += '<span class="badge bg-secondary" style="font-size:11px;padding:5px 10px;"><i class="ri-folder-line me-1"></i>' + (log.log_name || '-') + '</span>';
    if (log.description) html += '<span class="text-muted" style="font-size:12px;align-self:center;">' + log.description + '</span>';
    html += '</div>';

    // ── Before / After diff (the main content) ────────────────────────────
    const diff = log.diff || [];
    if (diff.length > 0) {
        const isSnapshot = diff.some(function(d) { return d.type === 'snapshot' || d.type === 'info'; });
        const isCreated  = log.event === 'created';
        const isDeleted  = log.event === 'deleted';

        html += '<div class="mb-3">';
        html += '<div class="fw-bold mb-2" style="font-size:12px;color:#1e3a5f;border-bottom:2px solid #405189;padding-bottom:4px;">';
        html += '<i class="ri-git-diff-line me-1"></i>';
        if (isSnapshot)     html += 'Expense Update — Full Snapshot';
        else if (isCreated) html += 'Record Created — Fields Set';
        else if (isDeleted) html += 'Record Deleted — Values at Deletion';
        else                html += 'Changes (' + diff.length + ' field' + (diff.length > 1 ? 's' : '') + ' changed)';
        html += '</div>';

        html += '<div class="table-responsive">';
        html += '<table class="table table-sm table-bordered mb-0" style="font-size:11px;">';

        if (isSnapshot) {
            // 2-column snapshot view
            html += '<thead><tr style="background:#dde6ff;color:#1e3a8a;"><th style="width:40%;">Field</th><th>Value</th></tr></thead><tbody>';
            diff.forEach(function(row) {
                const rowBg     = row.type === 'info' ? 'background:#fff8e1;' : '';
                const valDisplay = (row.new !== null && row.new !== undefined && row.new !== '')
                    ? String(row.new) : '<span class="text-muted fst-italic">—</span>';
                html += '<tr style="' + rowBg + '">';
                html += '<td class="fw-semibold" style="background:#f8f9fa;">' + row.field + '</td>';
                html += '<td>' + valDisplay + '</td>';
                html += '</tr>';
            });
        } else {
            // 3-column before/after diff
            html += '<thead><tr style="background:#dde6ff;color:#1e3a8a;">';
            html += '<th style="width:30%;">Field</th>';
            if (!isCreated && !isDeleted) {
                html += '<th style="width:35%;background:#fde2e2; color: #9b1c1c;">Before</th>';
                html += '<th style="width:35%;background:#d7f3e6; color: #065f46;">After</th>';
            } else if (isCreated) {
                html += '<th>Value</th>';
            } else {
                html += '<th>Value (at deletion)</th>';
            }
            html += '</tr></thead><tbody>';

            diff.forEach(function(row) {
                const fieldLabel = row.field.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
                const oldDisplay = row.old !== null && row.old !== undefined ? String(row.old) : '<span class="text-muted fst-italic">—</span>';
                const newDisplay = row.new !== null && row.new !== undefined ? String(row.new) : '<span class="text-muted fst-italic">—</span>';

                html += '<tr>';
                html += '<td class="fw-semibold" style="background:#f8f9fa;">' + fieldLabel + '</td>';
                if (!isCreated && !isDeleted) {
                    const changed = String(row.old) !== String(row.new);
                    html += '<td style="' + (changed ? 'background:#fff5f5;' : '') + '">' + oldDisplay + '</td>';
                    html += '<td style="' + (changed ? 'background:#f0fff4;font-weight:600;' : '') + '">' + newDisplay + '</td>';
                } else if (isCreated) {
                    html += '<td style="background:#f0fff4;">' + newDisplay + '</td>';
                } else {
                    html += '<td style="background:#fff5f5;">' + oldDisplay + '</td>';
                }
                html += '</tr>';
            });
        }

        html += '</tbody></table></div></div>';

    } else {
        // No diff data — show raw properties
        html += '<div class="alert alert-light border" style="font-size:11px;">No field-level change data available for this log entry.</div>';
    }

    // ── Request context (collapsed) ───────────────────────────────────────
    const hasContext = props.url || props.ip_address || props.method || props.request_data;
    if (hasContext) {
        html += '<div class="mt-2">';
        html += '<a class="text-muted" style="font-size:11px;text-decoration:none;cursor:pointer;" data-bs-toggle="collapse" href="#reqContext_' + log.id + '">';
        html += '<i class="ri-arrow-down-s-line"></i> Request Context</a>';
        html += '<div class="collapse" id="reqContext_' + log.id + '">';
        html += '<div class="mt-1 p-2 rounded" style="background:#f8f9fa;border:1px solid #dee2e6;font-size:10px;">';

        if (props.url)        html += '<div><strong>URL:</strong> <code style="word-break:break-all;">' + props.url + '</code></div>';
        if (props.method)     html += '<div><strong>Method:</strong> <span class="badge bg-dark">' + props.method + '</span></div>';
        if (props.ip_address) html += '<div><strong>IP:</strong> ' + props.ip_address + '</div>';
        if (props.route_name) html += '<div><strong>Route:</strong> ' + props.route_name + '</div>';

        if (props.request_data && typeof props.request_data === 'object' && Object.keys(props.request_data).length) {
            const skipKeys = ['_token','columns','draw','start','length','search'];
            const reqFiltered = Object.entries(props.request_data).filter(([k]) => !skipKeys.includes(k));
            if (reqFiltered.length) {
                html += '<div class="mt-1"><strong>Request Data:</strong></div>';
                html += '<table class="table table-sm table-bordered mt-1" style="font-size:10px;"><tbody>';
                reqFiltered.forEach(([k, v]) => {
                    let val = typeof v === 'object' ? JSON.stringify(v) : String(v ?? '');
                    if (val.length > 120) val = val.substring(0, 120) + '…';
                    html += '<tr><td class="fw-semibold" style="width:35%;">' + k + '</td><td>' + val + '</td></tr>';
                });
                html += '</tbody></table>';
            }
        }

        if (props.user_agent) html += '<div class="mt-1 text-muted">' + props.user_agent + '</div>';
        html += '</div></div></div>';
    }

    $('#detailModalBody').html(html);
    $('#detailModal').modal('show');
});

// Pagination click
$(document).on('click', '#logPagination .page-link', function(e) {
    e.preventDefault();
    const page = $(this).data('page');
    if (page && page > 0) { currentPage = page; loadLogs(); }
});

// Reset filters
$('#btnResetFilters').on('click', function() {
    $('#filterDateFrom').val('{{ date("Y-m-d") }}');
    $('#filterDateTo').val('{{ date("Y-m-d") }}');
    $('#filterUser, #filterModule, #filterEvent').val('');
    $('#filterSubjectType, #filterSearch').val('');
    currentPage = 1;
    loadLogs();
});

// Export CSV — server-side, respects all active filters
$('#btnExport').on('click', function() {
    const filters = getFilters();
    const params = new URLSearchParams({
        date_from:    filters.date_from    || '',
        date_to:      filters.date_to      || '',
        user_id:      filters.user_id      || '',
        module:       filters.module       || '',
        event:        filters.event        || '',
        subject_type: filters.subject_type || '',
        search:       filters.search       || '',
    });
    // Remove empty params
    [...params.keys()].forEach(k => { if (!params.get(k)) params.delete(k); });
    window.location.href = '{{ url("activity-log-export") }}?' + params.toString();
});

$(document).ready(function() {
    $('#btnLoadLogs').on('click', function() { currentPage = 1; loadLogs(); });
    loadLogs();
});
</script>
@endpush
