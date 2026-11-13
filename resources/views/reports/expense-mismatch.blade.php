@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold" style="color:#15648C;">
                        <i class="ri-file-search-line me-2"></i>Expense Mismatch Report (G7 vs Details)
                    </h4>
                    <span class="text-muted" style="font-size:12px;">
                        Compares G7 Activity Fill Data with Expense Claim Details
                    </span>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <form id="filterForm">
                    <div class="row align-items-end g-2">
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-1" style="font-size:12px;">Employee</label>
                            <select class="form-select form-select-sm" id="filterEmployee" name="employee_id">
                                <option value="">All Employees</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->EmployeeID }}">
                                        {{ $emp->EmpCode }} - {{ trim($emp->Fname . ' ' . ($emp->Sname ?? '') . ' ' . ($emp->Lname ?? '')) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold mb-1" style="font-size:12px;">Month</label>
                            <select class="form-select form-select-sm" id="filterMonth" name="month">
                                <option value="">All Months</option>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold mb-1" style="font-size:12px;">Claim Type</label>
                            <select class="form-select form-select-sm" id="filterClaimType" name="claim_type_id">
                                <option value="">All Claim Types</option>
                                @foreach($claimTypes as $ct)
                                    <option value="{{ $ct->ClaimId }}">{{ $ct->ClaimName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                <i class="ri-equalizer-line me-1"></i> More Filters
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="organogramFilterCountBadge" style="display:none;">0</span>
                            </button>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-primary btn-sm w-100" id="btnSearch">
                                <i class="ri-search-line me-1"></i> Search
                            </button>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-secondary btn-sm w-100" id="btnReset">
                                <i class="ri-refresh-line me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @include('partials.organogram-filters-drawer')

        {{-- Summary --}}
        <div class="row mb-3" id="summaryRow" style="display:none;">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body py-2">
                        <p class="text-muted mb-0" style="font-size:11px;">Total Mismatched Claims</p>
                        <h5 class="mb-0 text-danger" id="totalMismatched">0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body py-2">
                        <p class="text-muted mb-0" style="font-size:11px;">Total Mismatch Heads</p>
                        <h5 class="mb-0 text-warning" id="totalHeads">0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body py-2">
                        <p class="text-muted mb-0" style="font-size:11px;">Showing</p>
                        <h5 class="mb-0 text-info" id="showingInfo">0</h5>
                    </div>
                </div>
            </div>
        </div>

        {{-- Results Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div id="loadingIndicator" style="display:none; text-align:center; padding:40px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Checking for mismatches...</p>
                </div>
                <div id="noData" style="text-align:center; padding:40px;">
                    <p class="text-muted">No mismatches found. Select filters and click Search.</p>
                </div>
                <div class="table-responsive" id="resultsContainer" style="display:none;">
                    <table class="table table-bordered table-sm table-hover mb-0" id="mismatchTable">
                        <thead style="background-color:#dde6ff; color:#1e3a8a; font-size:11px;">
                            <tr>
                                <th>#</th>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Month</th>
                                <th>Claim Type</th>
                                <th>ExpId</th>
                                <th>Bill Date</th>
                                <th>Filled Amt</th>
                                <th>Status</th>
                                <th>Mismatches</th>
                                <th>Mismatch Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="mismatchBody">
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div id="paginationContainer" style="display:none; padding:10px 15px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div style="font-size:12px; color:#666;" id="paginationInfo"></div>
                        <div id="paginationLinks"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#dde6ff; color:#1e3a8a;">
                <h6 class="modal-title" id="detailModalLabel">
                    <i class="ri-file-list-3-line me-1"></i> Mismatch Detail - ExpId: <span id="modalExpId"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="detailLoading" style="text-align:center; padding:20px;">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div class="table-responsive" id="detailContainer" style="display:none;">
                    <div class="mb-2 p-2" id="detailMeta" style="background:#f0f8ff; border-radius:4px; font-size:11px;"></div>
                    <table class="table table-bordered table-sm" id="detailTable">
                        <thead style="background-color:#f8f9fa; font-size:10px;">
                            <tr>
                                <th rowspan="2" style="vertical-align:middle;">Expense Head</th>
                                <th colspan="4" class="text-center" style="background:#e3f2fd;">G7 Data</th>
                                <th colspan="4" class="text-center" style="background:#fce4ec;">Detail Data</th>
                                <th rowspan="2" style="vertical-align:middle;">Status</th>
                                <th rowspan="2" style="vertical-align:middle;">Fix</th>
                            </tr>
                            <tr>
                                <th class="text-end" style="background:#e3f2fd;">Claim</th>
                                <th class="text-end" style="background:#e3f2fd;">Verify</th>
                                <th class="text-end" style="background:#e3f2fd;">Approver</th>
                                <th class="text-end" style="background:#e3f2fd;">Finance</th>
                                <th class="text-end" style="background:#fce4ec;">Claim</th>
                                <th class="text-end" style="background:#fce4ec;">Verify</th>
                                <th class="text-end" style="background:#fce4ec;">Approver</th>
                                <th class="text-end" style="background:#fce4ec;">Finance</th>
                            </tr>
                        </thead>
                        <tbody id="detailBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {

    var currentPage = 1;
    var perPage = 20;

    // Search button
    $('#btnSearch').on('click', function() {
        currentPage = 1;
        loadData();
    });

    // Reset button
    $('#btnReset').on('click', function() {
        $('#filterForm')[0].reset();
        if (typeof window.resetOrganogramFilters === 'function') {
            window.resetOrganogramFilters();
        }
        $('#resultsContainer').hide();
        $('#summaryRow').hide();
        $('#paginationContainer').hide();
        $('#noData').show();
    });

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        currentPage = 1;
        loadData();
    });

    function loadData() {
        var organogram = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
        var params = $.extend({
            employee_id: $('#filterEmployee').val(),
            month: $('#filterMonth').val(),
            claim_type_id: $('#filterClaimType').val(),
            page: currentPage,
            per_page: perPage,
        }, organogram);

        $('#loadingIndicator').show();
        $('#resultsContainer').hide();
        $('#noData').hide();
        $('#summaryRow').hide();
        $('#paginationContainer').hide();

        $.ajax({
            url: '{{ route("admin.expense-mismatch.data") }}',
            method: 'GET',
            data: params,
            success: function(response) {
                $('#loadingIndicator').hide();

                if (response.success && response.data.length > 0) {
                    renderTable(response.data, response.pagination);
                    $('#summaryRow').show();
                    $('#totalMismatched').text(response.pagination.total);
                    $('#showingInfo').text(response.pagination.from + '-' + response.pagination.to + ' of ' + response.pagination.total);

                    var totalHeads = 0;
                    response.data.forEach(function(item) {
                        totalHeads += item.mismatch_count;
                    });
                    $('#totalHeads').text(totalHeads + ' (this page)');
                    $('#resultsContainer').show();
                    renderPagination(response.pagination);
                } else if (response.success && response.pagination && response.pagination.total === 0) {
                    $('#noData').html('<p class="text-success"><i class="ri-check-double-line"></i> No mismatches found! All data is consistent.</p>').show();
                } else {
                    $('#noData').html('<p class="text-muted">No mismatches found. Select filters and click Search.</p>').show();
                }
            },
            error: function(xhr) {
                $('#loadingIndicator').hide();
                $('#noData').html('<p class="text-danger">Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong') + '</p>').show();
            }
        });
    }

    function renderTable(data, pagination) {
        var html = '';
        var startIndex = pagination.from;
        data.forEach(function(item, index) {
            var mismatchDetails = '';
            item.mismatches.forEach(function(m) {
                var diffClass = m.difference > 0 ? 'text-danger' : 'text-success';
                mismatchDetails += '<div style="font-size:10px; margin-bottom:2px;">' +
                    '<strong>' + m.head_name + ':</strong> ' +
                    'G7=₹' + numberFormat(m.g7_amount) + ' | Detail=₹' + numberFormat(m.detail_amount) +
                    ' <span class="' + diffClass + '">(' + (m.difference > 0 ? '+' : '') + numberFormat(m.difference) + ')</span>' +
                    '</div>';
            });

            var statusBadge = '';
            switch(item.claim_status) {
                case 'Approved': statusBadge = '<span class="badge bg-success">Approved</span>'; break;
                case 'Verify': statusBadge = '<span class="badge bg-warning">Verified</span>'; break;
                case 'Pending': statusBadge = '<span class="badge bg-secondary">Pending</span>'; break;
                default: statusBadge = '<span class="badge bg-info">' + item.claim_status + '</span>';
            }

            html += '<tr>' +
                '<td>' + (startIndex + index) + '</td>' +
                '<td>' + item.employee_code + '</td>' +
                '<td style="font-size:11px;">' + item.employee_name + '</td>' +
                '<td>' + item.month_name + '</td>' +
                '<td>' + item.claim_type + '</td>' +
                '<td><strong>' + item.exp_id + '</strong></td>' +
                '<td>' + (item.bill_date || '-') + '</td>' +
                '<td class="text-end">₹' + numberFormat(item.filled_amount) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="text-center"><span class="badge bg-danger">' + item.mismatch_count + '</span></td>' +
                '<td style="max-width:300px;">' + mismatchDetails + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-outline-primary btn-detail" data-exp-id="' + item.exp_id + '" title="View Detail & Fix">' +
                        '<i class="ri-eye-line"></i>' +
                    '</button>' +
                '</td>' +
                '</tr>';
        });

        $('#mismatchBody').html(html);
    }

    function renderPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#paginationContainer').hide();
            return;
        }

        var info = 'Showing ' + pagination.from + ' to ' + pagination.to + ' of ' + pagination.total + ' records';
        $('#paginationInfo').text(info);

        var links = '';
        // Previous
        if (pagination.current_page > 1) {
            links += '<button class="btn btn-sm btn-outline-primary me-1 page-btn" data-page="' + (pagination.current_page - 1) + '">&laquo; Prev</button>';
        }

        // Page numbers
        var startPage = Math.max(1, pagination.current_page - 2);
        var endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        for (var i = startPage; i <= endPage; i++) {
            var activeClass = i === pagination.current_page ? 'btn-primary' : 'btn-outline-primary';
            links += '<button class="btn btn-sm ' + activeClass + ' me-1 page-btn" data-page="' + i + '">' + i + '</button>';
        }

        // Next
        if (pagination.current_page < pagination.total_pages) {
            links += '<button class="btn btn-sm btn-outline-primary me-1 page-btn" data-page="' + (pagination.current_page + 1) + '">Next &raquo;</button>';
        }

        $('#paginationLinks').html(links);
        $('#paginationContainer').show();
    }

    // Pagination click
    $(document).on('click', '.page-btn', function() {
        currentPage = $(this).data('page');
        loadData();
        // Scroll to top
        $('html, body').animate({ scrollTop: $('#mismatchTable').offset().top - 100 }, 200);
    });

    // Detail modal
    $(document).on('click', '.btn-detail', function() {
        var expId = $(this).data('exp-id');
        $('#modalExpId').text(expId);
        $('#detailLoading').show();
        $('#detailContainer').hide();
        $('#detailModal').modal('show');

        $.ajax({
            url: '{{ route("admin.expense-mismatch.detail") }}',
            method: 'GET',
            data: { exp_id: expId },
            success: function(response) {
                $('#detailLoading').hide();
                if (response.success) {
                    // Show ExpId, crop and variety info
                    var metaHtml = '<strong>ExpId:</strong> ' + expId + ' &nbsp;|&nbsp; ';
                    if (response.crop_list) {
                        metaHtml += '<strong>Crops:</strong> ' + response.crop_list + ' &nbsp;|&nbsp; ';
                    }
                    if (response.variety_list) {
                        metaHtml += '<strong>Varieties:</strong> ' + response.variety_list;
                    }
                    if (!response.crop_list && !response.variety_list) {
                        metaHtml += '<span class="text-muted">No crop/variety data</span>';
                    }
                    $('#detailMeta').html(metaHtml);

                    renderDetail(response.data, expId);
                    $('#detailContainer').show();
                }
            },
            error: function() {
                $('#detailLoading').html('<p class="text-danger">Failed to load details</p>');
            }
        });
    });

    function renderDetail(data, expId) {
        var html = '';
        data.forEach(function(item) {
            var rowClass = item.is_mismatch ? 'table-danger' : '';
            var statusIcon = item.is_mismatch
                ? '<span class="badge bg-danger">Mismatch</span>'
                : '<span class="badge bg-success">Match</span>';

            var fixButtons = '';
            if (item.is_mismatch) {
                fixButtons = '<div class="btn-group-vertical btn-group-sm">' +
                    '<button class="btn btn-outline-success btn-fix btn-sm" data-exp-id="' + expId + '" data-column="' + item.column_name + '" data-fix-type="g7_to_detail" title="Set G7 = Detail" style="font-size:9px;padding:2px 4px;">G7←Detail</button>' +
                    '<button class="btn btn-outline-warning btn-fix btn-sm" data-exp-id="' + expId + '" data-column="' + item.column_name + '" data-fix-type="detail_to_g7" title="Set Detail = G7" style="font-size:9px;padding:2px 4px;">Detail←G7</button>' +
                    '</div>';
            }

            // G7 row
            html += '<tr class="' + rowClass + '" style="font-size:11px;">' +
                '<td><strong>' + item.head_name + '</strong>';

            // Show detail rows if any
            if (item.detail_rows && item.detail_rows.length > 0) {
                html += '<div style="font-size:9px;color:#666;margin-top:3px;">';
                item.detail_rows.forEach(function(dr, idx) {
                    html += '<div style="border-top:1px dashed #ddd;padding-top:2px;margin-top:2px;">' +
                        'Row ' + (idx+1) + ': ' + dr.title + ' (₹' + numberFormat(dr.amount) + ')' +
                        (dr.remark ? ' - <em>' + dr.remark + '</em>' : '') +
                        '</div>';
                });
                html += '</div>';
            }

            html += '</td>' +
                '<td class="text-end' + (item.claim_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.g7_claim) + '</td>' +
                '<td class="text-end' + (item.verify_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.g7_verify) + '</td>' +
                '<td class="text-end' + (item.approver_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.g7_approver) + '</td>' +
                '<td class="text-end' + (item.finance_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.g7_finance) + '</td>' +
                '<td class="text-end' + (item.claim_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.detail_claim) + '</td>' +
                '<td class="text-end' + (item.verify_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.detail_verify) + '</td>' +
                '<td class="text-end' + (item.approver_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.detail_approver) + '</td>' +
                '<td class="text-end' + (item.finance_diff != 0 ? ' text-danger fw-bold' : '') + '">₹' + numberFormat(item.detail_finance) + '</td>' +
                '<td>' + statusIcon + '</td>' +
                '<td>' + fixButtons + '</td>' +
                '</tr>';
        });

        $('#detailBody').html(html);
    }

    // Fix button
    $(document).on('click', '.btn-fix', function() {
        var $btn = $(this);
        var expId = $btn.data('exp-id');
        var columnName = $btn.data('column');
        var fixType = $btn.data('fix-type');

        var confirmMsg = fixType === 'g7_to_detail'
            ? 'Update G7 value to match Detail amount?'
            : 'Update Detail amount to match G7 value?';

        if (!confirm(confirmMsg)) return;

        $btn.prop('disabled', true).html('<i class="ri-loader-4-line"></i>');

        $.ajax({
            url: '{{ route("admin.expense-mismatch.fix") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                exp_id: expId,
                column_name: columnName,
                fix_type: fixType,
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    // Reload detail
                    $('.btn-detail[data-exp-id="' + expId + '"]').click();
                } else {
                    alert('Error: ' + response.message);
                    $btn.prop('disabled', false).html(fixType === 'g7_to_detail' ? '<i class="ri-arrow-left-line"></i> G7←Detail' : 'Detail←G7 <i class="ri-arrow-right-line"></i>');
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Fix failed'));
                $btn.prop('disabled', false).html(fixType === 'g7_to_detail' ? '<i class="ri-arrow-left-line"></i> G7←Detail' : 'Detail←G7 <i class="ri-arrow-right-line"></i>');
            }
        });
    });

    function numberFormat(num) {
        if (!num && num !== 0) return '0';
        return parseInt(num).toLocaleString('en-IN');
    }
});
</script>
@include('partials.organogram-filters-js')
@endpush
