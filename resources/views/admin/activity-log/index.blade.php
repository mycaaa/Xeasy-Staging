@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>System Activity Log
            </h5>
            <button class="btn btn-sm btn-outline-light" onclick="refreshLogs()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
        <div class="card-body">
            {{-- Filters --}}
            <div class="row g-2 mb-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Module</label>
                    <select id="filter-log-name" class="form-select form-select-sm">
                        <option value="">All Modules</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Action</label>
                    <select id="filter-event" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <option value="created">Created</option>
                        <option value="updated">Updated</option>
                        <option value="deleted">Deleted</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">User</label>
                    <select id="filter-user" class="form-select form-select-sm">
                        <option value="">All Users</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">From Date</label>
                    <input type="date" id="filter-date-from" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">To Date</label>
                    <input type="date" id="filter-date-to" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search..." aria-label="Search">
                </div>
            </div>

            {{-- Log Table --}}
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover" id="log-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:150px;">Date & Time</th>
                            <th style="width:120px;">User</th>
                            <th style="width:100px;">Action</th>
                            <th style="width:130px;">Module</th>
                            <th>Description</th>
                            <th style="width:80px;">Details</th>
                        </tr>
                    </thead>
                    <tbody id="log-tbody">
                        <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div id="log-info" class="text-muted small"></div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="log-pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Change Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detail-body" style="max-height:500px; overflow-y:auto;"></div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadFilters();
    loadLogs();

    // Filter event listeners
    document.getElementById('filter-log-name').addEventListener('change', () => { currentPage = 1; loadLogs(); });
    document.getElementById('filter-event').addEventListener('change', () => { currentPage = 1; loadLogs(); });
    document.getElementById('filter-user').addEventListener('change', () => { currentPage = 1; loadLogs(); });
    document.getElementById('filter-date-from').addEventListener('change', () => { currentPage = 1; loadLogs(); });
    document.getElementById('filter-date-to').addEventListener('change', () => { currentPage = 1; loadLogs(); });

    let searchTimeout;
    document.getElementById('filter-search').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadLogs(); }, 400);
    });
});

function loadFilters() {
    fetch("{{ route('admin.activity-log.filters') }}")
        .then(r => r.json())
        .then(data => {
            const logSelect = document.getElementById('filter-log-name');
            data.log_names.forEach(name => {
                logSelect.add(new Option(name, name)); // S-09
            });

            const userSelect = document.getElementById('filter-user');
            data.users.forEach(user => {
                userSelect.add(new Option(user.name, user.id)); // S-09
            });
        });
}

function loadLogs() {
    const params = new URLSearchParams({
        page: currentPage,
        per_page: 50,
        log_name: document.getElementById('filter-log-name').value,
        event: document.getElementById('filter-event').value,
        causer_id: document.getElementById('filter-user').value,
        date_from: document.getElementById('filter-date-from').value,
        date_to: document.getElementById('filter-date-to').value,
        search: document.getElementById('filter-search').value,
    });

    fetch("{{ route('admin.activity-log.data') }}?" + params.toString())
        .then(r => r.json())
        .then(data => {
            renderTable(data.data);
            renderPagination(data);
            document.getElementById('log-info').textContent =
                `Showing ${((data.current_page-1)*data.per_page)+1} - ${Math.min(data.current_page*data.per_page, data.total)} of ${data.total} entries`;
        });
}

function renderTable(logs) {
    const tbody = document.getElementById('log-tbody');
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No activity logs found.</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map((log, i) => {
        const eventBadge = {
            'created': '<span class="badge bg-success">Created</span>',
            'updated': '<span class="badge bg-warning text-dark">Updated</span>',
            'deleted': '<span class="badge bg-danger">Deleted</span>',
        }[log.event] || `<span class="badge bg-secondary">${log.event}</span>`;

        const date = new Date(log.created_at);
        const dateStr = date.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'})
                       + ' ' + date.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});

        return `<tr>
            <td class="text-center">${log.id}</td>
            <td><small>${dateStr}</small></td>
            <td><small>${log.causer_name}</small></td>
            <td>${eventBadge}</td>
            <td><small>${log.log_name || log.subject_type}</small></td>
            <td><small>${log.description}</small></td>
            <td class="text-center">
                <button class="btn btn-xs btn-outline-primary" onclick='showDetail(${JSON.stringify(log)})'>
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination(data) {
    const ul = document.getElementById('log-pagination');
    let html = '';
    const maxPages = Math.min(data.last_page, 10);

    if (data.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="goPage(${data.current_page-1})">«</a></li>`;
    }
    for (let i = 1; i <= maxPages; i++) {
        html += `<li class="page-item ${i===data.current_page?'active':''}"><a class="page-link" href="#" onclick="goPage(${i})">${i}</a></li>`;
    }
    if (data.current_page < data.last_page) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="goPage(${data.current_page+1})">»</a></li>`;
    }
    ul.innerHTML = html;
}

function goPage(page) {
    currentPage = page;
    loadLogs();
}

function refreshLogs() {
    loadLogs();
}

function showDetail(log) {
    const body = document.getElementById('detail-body');
    let html = `
        <table class="table table-sm table-bordered mb-3">
            <tr><th style="width:140px;">ID</th><td>${log.id}</td></tr>
            <tr><th>Event</th><td>${log.event}</td></tr>
            <tr><th>Module</th><td>${log.log_name}</td></tr>
            <tr><th>Model</th><td>${log.subject_type} #${log.subject_id}</td></tr>
            <tr><th>User</th><td>${log.causer_name} (ID: ${log.causer_id || 'N/A'})</td></tr>
            <tr><th>Date</th><td>${log.created_at}</td></tr>
            <tr><th>Description</th><td>${log.description}</td></tr>
        </table>
    `;

    if (log.properties && Object.keys(log.properties).length > 0) {
        if (log.properties.old && log.properties.attributes) {
            // Show diff for updates
            html += '<h6>Changes:</h6><table class="table table-sm table-bordered"><thead><tr><th>Field</th><th>Old Value</th><th>New Value</th></tr></thead><tbody>';
            const allKeys = [...new Set([...Object.keys(log.properties.old || {}), ...Object.keys(log.properties.attributes || {})])];
            allKeys.forEach(key => {
                const oldVal = log.properties.old?.[key] ?? '';
                const newVal = log.properties.attributes?.[key] ?? '';
                if (oldVal != newVal) {
                    html += `<tr><td><strong>${key}</strong></td><td class="text-danger">${oldVal}</td><td class="text-success">${newVal}</td></tr>`;
                }
            });
            html += '</tbody></table>';
        } else if (log.properties.attributes) {
            html += '<h6>Data:</h6><table class="table table-sm table-bordered"><tbody>';
            Object.entries(log.properties.attributes).forEach(([key, val]) => {
                html += `<tr><td><strong>${key}</strong></td><td>${val}</td></tr>`;
            });
            html += '</tbody></table>';
        } else {
            html += '<h6>Properties:</h6><pre class="bg-light p-2 rounded" style="font-size:12px;">' + JSON.stringify(log.properties, null, 2) + '</pre>';
        }
    }

    body.innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
@endsection
