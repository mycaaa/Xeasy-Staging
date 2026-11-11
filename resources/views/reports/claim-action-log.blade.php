@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Header --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold"><i class="ri-file-list-3-line me-2 text-primary"></i>Claim Return & Submit Log</h4>
                <p class="text-muted mb-0" style="font-size:13px;">Track every return and submission action — who did it, when, and how many times</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                    <i class="ri-filter-3-line me-1"></i> More Filters
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">
                        0
                    </span>
                </button>
                <a href="{{ route('reports.claim-action-tracking.index') }}" class="btn btn-sm btn-outline-info">
                    <i class="ri-user-follow-line me-1"></i> Full Action Tracking
                </a>
                <a href="{{ route('reports.claim-action-log.export') }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}"
                   class="btn btn-sm btn-success" id="exportBtn">
                    <i class="ri-download-2-line me-1"></i>Export Excel
                </a>
            </div>
        </div>
    </div>

    @include('partials.organogram-filters-drawer')

    {{-- Summary cards --}}
    <div class="row g-2 mb-3" id="summaryCards">
        <div class="col-sm-3">
            <div class="card border-start border-danger border-3 py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Total Returns</div>
                <div class="fw-bold fs-4 text-danger" id="cntReturn">—</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-start border-success border-3 py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Total Submits</div>
                <div class="fw-bold fs-4 text-success" id="cntSubmit">—</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-start border-warning border-3 py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Total Resubmits</div>
                <div class="fw-bold fs-4 text-warning" id="cntResubmit">—</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-start border-primary border-3 py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Unique Employees</div>
                <div class="fw-bold fs-4 text-primary" id="cntEmp">—</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1" style="font-size:12px;">Action</label>
                    <select class="form-select form-select-sm" id="filterAction">
                        <option value="">All Actions</option>
                        <option value="return">Return</option>
                        <option value="revert">Revert</option>
                        <option value="submit">Submit</option>
                        <option value="resubmit">Resubmit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" style="font-size:12px;">Month</label>
                    <select class="form-select form-select-sm" id="filterMonth">
                        <option value="">All Months</option>
                        @foreach($monthNames as $n => $name)
                            <option value="{{ $n }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" style="font-size:12px;">Date From</label>
                    <input type="date" class="form-control form-control-sm" id="filterDateFrom">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1" style="font-size:12px;">Date To</label>
                    <input type="date" class="form-control form-control-sm" id="filterDateTo">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1" style="font-size:12px;">Employee (Code / Name)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="ri-user-search-line"></i></span>
                        <input type="text" class="form-control form-control-sm" id="filterEmployee" placeholder="Type Employee Code or Name..." aria-label="Type Employee Code or Name">
                    </div>
                </div>
                <div class="col-md-12 d-flex justify-content-end gap-2 mt-2">
                    <button class="btn btn-sm btn-primary px-3" id="btnFilter">
                        <i class="ri-filter-line me-1"></i>Filter
                    </button>
                    <button class="btn btn-sm btn-outline-secondary px-3" id="btnReset">
                        <i class="ri-refresh-line me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-0" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetail">
                <i class="ri-list-check me-1"></i>Detailed Log
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSummary" id="btnSummaryTab">
                <i class="ri-bar-chart-line me-1"></i>Return Summary
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- DETAIL TAB --}}
        <div class="tab-pane fade show active" id="tabDetail">
            <div class="card border-top-0 rounded-0 rounded-bottom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="tblLog" style="font-size:12px;">
                            <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th class="th-sortable" data-sort="employee" style="cursor:pointer;user-select:none;">
                                        Employee <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="month" style="cursor:pointer;user-select:none;">
                                        Month <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="exp_id" style="cursor:pointer;user-select:none;">
                                        Exp ID <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="action" style="cursor:pointer;user-select:none;">
                                        Action <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="action_by_role" style="cursor:pointer;user-select:none;">
                                        By Role <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="action_by_name" style="cursor:pointer;user-select:none;">
                                        Done By <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="reason" style="cursor:pointer;user-select:none;">
                                        Reason <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="from_status" style="cursor:pointer;user-select:none;">
                                        From <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable" data-sort="to_status" style="cursor:pointer;user-select:none;">
                                        To <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th class="th-sortable text-center" data-sort="return_count" style="cursor:pointer;user-select:none;">
                                        Return # <i class="ri-arrow-up-down-line text-muted ms-1 sort-icon"></i>
                                    </th>
                                    <th style="white-space:nowrap;">Approved Date</th>
                                    <th style="white-space:nowrap;">Courier Recv Date</th>
                                    <th style="white-space:nowrap;">Payment Date</th>
                                    <th class="th-sortable" data-sort="action_at" style="cursor:pointer;user-select:none;white-space:nowrap;">
                                       Verified Date & Time <i class="ri-arrow-down-line text-primary ms-1 sort-icon"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="logBody">
                                <tr><td colspan="15" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Pagination Footer --}}
                <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted" style="font-size:12px;">Show</span>
                        <select class="form-select form-select-sm" id="perPageSelect" style="width: auto; font-size:12px;">
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="text-muted" style="font-size:12px;" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                    </div>
                    <div>
                        <ul class="pagination pagination-sm mb-0" id="paginationLinks">
                            <!-- Dynamic page buttons -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- SUMMARY TAB --}}
        <div class="tab-pane fade" id="tabSummary">
            <div class="card border-top-0 rounded-0 rounded-bottom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="tblSummary" style="font-size:12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Month</th>
                                    <th class="text-center">Total Returns</th>
                                    <th>Last Returned</th>
                                </tr>
                            </thead>
                            <tbody id="summaryBody">
                                <tr><td colspan="5" class="text-center py-4 text-muted">Click the Summary tab to load.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const detailUrl  = '{{ route("reports.claim-action-log.data") }}';
    const summaryUrl = '{{ route("reports.claim-action-log.summary") }}';
    const exportBase = '{{ route("reports.claim-action-log.export") }}';

    let summaryLoaded = false;
    let debounceTimer = null;

    let state = {
        page: 1,
        per_page: 15,
        sort_by: 'action_at',
        sort_dir: 'desc'
    };

    function getFilters() {
        let f = {
            action:     $('#filterAction').val(),
            month:      $('#filterMonth').val(),
            date_from:  $('#filterDateFrom').val(),
            date_to:    $('#filterDateTo').val(),
            employee:   $('#filterEmployee').val(),
            page:       state.page,
            per_page:   state.per_page,
            sort_by:    state.sort_by,
            sort_dir:   state.sort_dir
        };
        if (typeof window.getOrganogramFilterValues === 'function') {
            $.extend(f, window.getOrganogramFilterValues());
        }
        return f;
    }

    function loadDetail() {
        $('#logBody').html('<tr><td colspan="12" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading...</td></tr>');
        $.get(detailUrl, getFilters(), function (res) {
            const rows = res.data;
            updateCards(res.cnt_return || 0, res.cnt_submit || 0, res.cnt_resubmit || 0, res.cnt_emp || 0);

            if (!rows || !rows.length) {
                $('#logBody').html('<tr><td colspan="15" class="text-center py-4 text-muted">No records found.</td></tr>');
                $('#paginationInfo').text('Showing 0 to 0 of 0 entries');
                $('#paginationLinks').empty();
                return;
            }

            let html = '';
            const startIdx = (res.current_page - 1) * res.per_page;
            rows.forEach((r, i) => {
                const returnBadge = r.return_count > 1
                    ? `<span class="badge bg-danger">${r.return_count}</span>`
                    : `<span class="badge bg-secondary">${r.return_count}</span>`;

                html += `<tr>
                    <td>${startIdx + i + 1}</td>
                    <td>${r.employee}</td>
                    <td>${r.month}</td>
                    <td>${r.exp_id}</td>
                    <td>${r.action}</td>
                    <td>${r.action_by_role}</td>
                    <td>${r.action_by_name}</td>
                    <td class="text-muted" style="max-width:200px;white-space:normal;">${r.reason}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">${r.from_status}</span></td>
                    <td><span class="badge bg-primary-subtle text-primary">${r.to_status}</span></td>
                    <td class="text-center">${returnBadge}</td>
                    <td style="white-space:nowrap;">${r.approved_date}</td>
                    <td style="white-space:nowrap;">${r.courier_receiving_date}</td>
                    <td style="white-space:nowrap;">${r.payment_date}</td>
                    <td style="white-space:nowrap;">${r.action_at}</td>
                </tr>`;
            });

            $('#logBody').html(html);

            // Update Pagination Info
            $('#paginationInfo').text(`Showing ${res.from} to ${res.to} of ${res.total} entries`);
            renderPagination(res.current_page, res.last_page);
        }).fail(function () {
            $('#logBody').html('<tr><td colspan="15" class="text-center text-danger py-3">Failed to load data.</td></tr>');
        });
    }

    function renderPagination(currentPage, lastPage) {
        if (lastPage <= 1) {
            $('#paginationLinks').empty();
            return;
        }

        let html = '';
        
        // Prev button
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}">Prev</a>
        </li>`;

        let startPage = Math.max(1, currentPage - 2);
        let endPage   = Math.min(lastPage, currentPage + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${p}">${p}</a>
            </li>`;
        }

        if (endPage < lastPage) {
            if (endPage < lastPage - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${lastPage}">${lastPage}</a></li>`;
        }

        // Next button
        html += `<li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
        </li>`;

        $('#paginationLinks').html(html);
    }

    function updateSortIcons() {
        $('.th-sortable').each(function () {
            const col = $(this).data('sort');
            const icon = $(this).find('.sort-icon');
            if (col === state.sort_by) {
                icon.removeClass('ri-arrow-up-down-line text-muted')
                    .addClass(state.sort_dir === 'asc' ? 'ri-arrow-up-line text-primary' : 'ri-arrow-down-line text-primary');
            } else {
                icon.removeClass('ri-arrow-up-line ri-arrow-down-line text-primary')
                    .addClass('ri-arrow-up-down-line text-muted');
            }
        });
    }

    function loadSummary() {
        $('#summaryBody').html('<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading...</td></tr>');
        const params = {
            month: $('#filterMonth').val(),
            employee: $('#filterEmployee').val(),
        };
        $.get(summaryUrl, params, function (res) {
            const rows = res.data;
            if (!rows.length) {
                $('#summaryBody').html('<tr><td colspan="5" class="text-center text-muted py-3">No return records found.</td></tr>');
                return;
            }
            let html = '';
            rows.forEach((r, i) => {
                const cls = r.total_returns >= 3 ? 'text-danger fw-bold' : r.total_returns === 2 ? 'text-warning fw-semibold' : '';
                html += `<tr>
                    <td>${i + 1}</td>
                    <td>${r.employee}</td>
                    <td>${r.month}</td>
                    <td class="text-center ${cls}">${r.total_returns}</td>
                    <td>${r.last_return}</td>
                </tr>`;
            });
            $('#summaryBody').html(html);
        });
    }

    function updateCards(ret, sub, resub, emp) {
        $('#cntReturn').text(ret);
        $('#cntSubmit').text(sub);
        $('#cntResubmit').text(resub);
        $('#cntEmp').text(emp);
    }

    function buildExportUrl() {
        const f = getFilters();
        const p = new URLSearchParams();
        Object.keys(f).forEach(k => {
            const v = f[k];
            if (Array.isArray(v)) {
                v.forEach(item => p.append(k + '[]', item));
            } else if (v !== null && v !== undefined && v !== '') {
                p.append(k, v);
            }
        });
        return exportBase + (p.toString() ? '?' + p.toString() : '');
    }

    // Header Sort Event
    $(document).on('click', '.th-sortable', function () {
        const col = $(this).data('sort');
        if (state.sort_by === col) {
            state.sort_dir = state.sort_dir === 'asc' ? 'desc' : 'asc';
        } else {
            state.sort_by = col;
            state.sort_dir = 'asc';
        }
        state.page = 1;
        updateSortIcons();
        loadDetail();
        $('#exportBtn').attr('href', buildExportUrl());
    });

    // Pagination Link Event
    $(document).on('click', '#paginationLinks .page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && page !== state.page) {
            state.page = page;
            loadDetail();
        }
    });

    // Items Per Page Selection
    $('#perPageSelect').on('change', function () {
        state.per_page = parseInt($(this).val(), 10) || 15;
        state.page = 1;
        loadDetail();
    });

    // Trigger filtering live on keyup / input typing
    $('#filterEmployee').on('input keyup', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            state.page = 1;
            summaryLoaded = false;
            loadDetail();
            if ($('#tabSummary').hasClass('active')) {
                loadSummary();
                summaryLoaded = true;
            }
            $('#exportBtn').attr('href', buildExportUrl());
        }, 300);
    });

    // Trigger filtering on dropdown or date change
    $('#filterAction, #filterMonth, #filterDateFrom, #filterDateTo').on('change', function () {
        state.page = 1;
        summaryLoaded = false;
        loadDetail();
        if ($('#tabSummary').hasClass('active')) {
            loadSummary();
            summaryLoaded = true;
        }
        $('#exportBtn').attr('href', buildExportUrl());
    });

    // Load detail on page load
    loadDetail();

    // Load summary when tab clicked
    $('#btnSummaryTab').on('click', function () {
        if (!summaryLoaded) { loadSummary(); summaryLoaded = true; }
    });

    $('#btnFilter').on('click', function () {
        state.page = 1;
        summaryLoaded = false;
        loadDetail();
        if ($('#tabSummary').hasClass('active')) {
            loadSummary();
            summaryLoaded = true;
        }
        $('#exportBtn').attr('href', buildExportUrl());
    });

    $('#btnReset').on('click', function () {
        $('#filterAction, #filterMonth, #filterDateFrom, #filterDateTo, #filterEmployee').val('');
        if (typeof window.resetOrganogramFilters === 'function') {
            window.resetOrganogramFilters();
        }
        state.page = 1;
        state.per_page = 15;
        state.sort_by = 'action_at';
        state.sort_dir = 'desc';
        $('#perPageSelect').val('15');
        updateSortIcons();
        summaryLoaded = false;
        loadDetail();
        if ($('#tabSummary').hasClass('active')) {
            loadSummary();
            summaryLoaded = true;
        }
        $('#exportBtn').attr('href', exportBase);
    });

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        state.page = 1;
        summaryLoaded = false;
        loadDetail();
        if ($('#tabSummary').hasClass('active')) {
            loadSummary();
            summaryLoaded = true;
        }
        $('#exportBtn').attr('href', buildExportUrl());
    });
});
</script>
@include('partials.organogram-filters-js')
@endpush
