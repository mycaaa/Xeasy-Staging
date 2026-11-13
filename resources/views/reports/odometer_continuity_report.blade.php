@extends('layouts.app')

@push('styles')
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
<style>
    .continuity-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    .continuity-table th {
        background: #dde6ff;
        color: #1e3a8a;
        padding: 9px 8px;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #0d4f70;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    .continuity-table td {
        padding: 7px 8px;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
        font-size: 12px;
    }
    .continuity-table tbody tr:hover {
        background-color: #f1f5f9 !important;
    }

    /* Employee Color Palettes (Soft Tinting for Rows) */
    .emp-row-0 { background-color: #ffffff; }
    .emp-row-1 { background-color: #f8fafc; }
    .emp-row-2 { background-color: #f0fdf4; }
    .emp-row-3 { background-color: #fefce8; }
    .emp-row-4 { background-color: #f0f9ff; }
    .emp-row-5 { background-color: #faf5ff; }
    .emp-row-6 { background-color: #fff1f2; }
    .emp-row-7 { background-color: #f0fdfa; }

    /* Gap Severity Borders & Highlights */
    .row-gap-danger {
        background-color: #fff1f2 !important;
        border-left: 4px solid #ef4444 !important;
    }
    .row-gap-warning {
        background-color: #fffbeb !important;
        border-left: 4px solid #f59e0b !important;
    }
    .row-gap-overlap {
        background-color: #f5f3ff !important;
        border-left: 4px solid #8b5cf6 !important;
    }
    .row-continuous {
        border-left: 4px solid #10b981 !important;
    }
    .row-initial {
        border-left: 4px solid #3b82f6 !important;
    }

    /* Badges */
    .badge-status {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
        white-space: nowrap;
    }
    .badge-continuous {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .badge-warning-gap {
        background-color: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .badge-danger-gap {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .badge-overlap {
        background-color: #ede9fe;
        color: #5b21b6;
        border: 1px solid #ddd6fe;
    }
    .badge-initial {
        background-color: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    /* Month Divider Row */
    .month-header-row td {
        background: #e2e8f0 !important;
        font-weight: 700;
        font-size: 12px;
        color: #334155;
        padding: 6px 12px;
        border-top: 2px solid #cbd5e1;
        border-bottom: 2px solid #cbd5e1;
    }

    /* KPI Cards */
    .kpi-card {
        border-radius: 8px;
        padding: 12px 14px;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        background: #fff;
        cursor: pointer;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }
    .kpi-title {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .kpi-value {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
    }

    /* Photo Thumbnails */
    .odo-thumb {
        width: 42px;
        height: 42px;
        object-fit: cover;
        border-radius: 6px;
        cursor: pointer;
        border: 1px solid #cbd5e1;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .odo-thumb:hover {
        transform: scale(1.15);
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        z-index: 10;
        position: relative;
    }
    .no-img-badge {
        display: inline-block;
        font-size: 10px;
        padding: 2px 6px;
        background: #f1f5f9;
        color: #94a3b8;
        border-radius: 4px;
        border: 1px dashed #cbd5e1;
    }

    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--multiple {
        min-height: 34px;
        border-color: #cbd5e1;
        font-size: 12px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        font-size: 11px;
        background: #dde6ff;
        color: #1e3a8a;
        border: none;
        border-radius: 3px;
        padding: 1px 6px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #fff;
        margin-right: 4px;
    }
    .offcanvas-header.bg-theme {
        background: #dde6ff;
        color: #1e3a8a;
    }
    .offcanvas-header.bg-theme .btn-close {
        filter: invert(1);
    }
</style>
@endpush

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between p-3 rounded shadow-sm" style="background:#fff;border:1px solid #e2e8f0;">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-route-line"></i>
                            </span>
                        </div>
                        <div>
                            <h4 class="mb-0 font-size-18 fw-bold text-dark">Odometer Continuity Report</h4>
                            <p class="text-muted mb-0 font-size-12">Track sequential odometer continuity: verifies that each employee's daily opening reading matches the previous closing reading.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <!-- Filter Drawer Toggle Button -->
                        <button class="btn btn-outline-primary btn-sm position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                            <i class="ri-filter-3-line me-1"></i> More Filters
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="organogramFilterCountBadge" style="display:none;">0</span>
                        </button>
                        <!-- Excel Export Button -->
                        <button type="button" class="btn btn-success btn-sm" id="btnExport">
                            <i class="ri-file-excel-2-line me-1"></i> Export Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <!-- KPI Summary Row -->
    <div class="row g-2 mb-3">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card border-primary-subtle" id="kpiCardAll" title="Click to show all records">
                <div class="kpi-title"><i class="ri-file-list-3-line text-primary me-1"></i> Total Claims</div>
                <div class="kpi-value text-primary" id="kpiTotalClaims">0</div>
                <small class="text-muted" id="kpiUniqueEmployees">0 Employees</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card border-success-subtle" id="kpiCardContinuous" title="Click to filter only continuous entries">
                <div class="kpi-title"><i class="ri-checkbox-circle-line text-success me-1"></i> Continuous (0 Gap)</div>
                <div class="kpi-value text-success" id="kpiContinuousCount">0</div>
                <small class="text-muted" style="font-size:11px;">100% Sequence Match</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card border-danger-subtle" id="kpiCardGaps" title="Click to filter only gap entries">
                <div class="kpi-title"><i class="ri-error-warning-line text-danger me-1"></i> Gaps (> 0 KM)</div>
                <div class="kpi-value text-danger" id="kpiGapCount">0</div>
                <small class="text-muted" id="kpiTotalGapKm">0 KM Unaccounted</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card border-warning-subtle" id="kpiCardOverlap" title="Click to filter overlap/rollback entries">
                <div class="kpi-title"><i class="ri-arrow-go-back-line text-purple me-1"></i> Overlaps (< 0 KM)</div>
                <div class="kpi-value text-purple" id="kpiOverlapCount">0</div>
                <small class="text-muted" style="font-size:11px;">Opening < Prev Close</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card border-info-subtle" id="kpiCardInitial" title="Initial entries in selected range">
                <div class="kpi-title"><i class="ri-flag-line text-info me-1"></i> Initial Entries</div>
                <div class="kpi-value text-info" id="kpiInitialCount">0</div>
                <small class="text-muted" style="font-size:11px;">First in range</small>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-card border-secondary-subtle">
                <div class="kpi-title"><i class="ri-speed-up-line text-dark me-1"></i> Total Claimed KM</div>
                <div class="kpi-value text-dark" id="kpiTotalClaimedKm">0 KM</div>
                <small class="text-muted" style="font-size:11px;">Approved / Submitted</small>
            </div>
        </div>
    </div>

    <!-- Main Card & Filter Toolbar -->
    <div class="card shadow-sm border">
        <div class="card-header bg-white py-2 border-bottom">
            <!-- Row 1: Date Range, Presets & Action Buttons -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 border-bottom">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- Date Presets -->
                    <div class="btn-group btn-group-sm" role="group" id="datePresets">
                        <button type="button" class="btn btn-outline-primary" data-preset="today">Today</button>
                        <button type="button" class="btn btn-outline-primary" data-preset="yesterday">Yesterday</button>
                        <button type="button" class="btn btn-outline-primary" data-preset="last7">Last 7 Days</button>
                        <button type="button" class="btn btn-outline-primary active" data-preset="thisMonth">This Month</button>
                        <button type="button" class="btn btn-outline-primary" data-preset="lastMonth">Last Month</button>
                        <button type="button" class="btn btn-outline-primary" data-preset="last90">Last 90 Days</button>
                    </div>

                    <!-- Date Range Inputs -->
                    <div class="d-flex align-items-center gap-1">
                        <input type="date" id="f_from_date" class="form-control form-control-sm" style="width:135px;" title="From Date">
                        <span class="text-muted font-size-12">to</span>
                        <input type="date" id="f_to_date" class="form-control form-control-sm" style="width:135px;" title="To Date">
                    </div>
                </div>

                <!-- Apply & Clear Buttons -->
                <div class="d-flex align-items-center gap-1 ms-auto">
                    <button type="button" class="btn btn-primary btn-sm px-3" id="btnApply">
                        <i class="ri-check-line me-1"></i> Apply
                    </button>
                    <button type="button" class="btn btn-light btn-sm text-secondary" id="btnClear" title="Reset Filters">
                        <i class="ri-restart-line"></i>
                    </button>
                </div>
            </div>

            <!-- Row 2: Quick Filters & Search -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- Continuity Status Quick Filter -->
                    <select id="f_continuity_status" class="form-select form-select-sm" style="width:190px;">
                        <option value="all">All Continuity Status</option>
                        <option value="only_gaps">🔴 Any Gap (Gap ≠ 0)</option>
                        <option value="positive_gap">⚠️ Positive Gap (> 0 KM)</option>
                        <option value="continuous">🟢 Continuous (0 KM Gap)</option>
                        <option value="overlap">🟣 Overlap / Rollback (< 0 KM)</option>
                    </select>

                    <!-- Gap KM Range Filter -->
                    <div class="d-flex align-items-center gap-1 bg-light px-2 py-1 rounded border">
                        <span class="text-muted font-size-11 fw-semibold">Gap KM:</span>
                        <input type="number" step="0.1" id="f_min_gap" class="form-control form-control-sm text-center" style="width:75px;" placeholder="Min" aria-label="Min">
                        <span class="text-muted font-size-11">-</span>
                        <input type="number" step="0.1" id="f_max_gap" class="form-control form-control-sm text-center" style="width:75px;" placeholder="Max" aria-label="Max">
                    </div>

                    <!-- Vehicle Type -->
                    <select id="f_vehicle_type" class="form-select form-select-sm" style="width:110px;">
                        <option value="">All Vehicles</option>
                        <option value="2W">2W</option>
                        <option value="4W">4W</option>
                    </select>

                    <!-- Search Input -->
                    <div class="input-group input-group-sm" style="width:230px;">
                        <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                        <input type="text" id="f_search" class="form-control" placeholder="Search name/code/ID..." aria-label="Search name/code/ID">
                    </div>
                </div>

                <!-- Page Size Dropdown -->
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted font-size-12">Show</span>
                    <select id="pageSizeSelect" class="form-select form-select-sm" style="width:75px;">
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="-1">All</option>
                    </select>
                    <span class="text-muted font-size-12">entries</span>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 620px; overflow-y: auto;">
                <table class="continuity-table table-hover mb-0" id="continuityTable">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th style="width: 90px;">Exp ID</th>
                            <th>Employee</th>
                            <th>Department / HQ</th>
                            <th style="width: 100px;">Travel Date</th>
                            <th style="width: 85px;">Vehicle</th>
                            <th style="width: 110px;" class="text-end">Opening KM</th>
                            <th style="width: 110px;" class="text-end">Closing KM</th>
                            <th style="width: 100px;" class="text-end">Claimed KM</th>
                            <th style="width: 140px;" class="text-end">Prev Closing KM</th>
                            <th style="width: 110px;" class="text-end">Gap (KM)</th>
                            <th style="width: 140px;" class="text-center">Continuity Status</th>
                            <th style="width: 80px;" class="text-center">Photos</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="14" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Calculating employee odometer continuity and analyzing reading gaps...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination & Summary Footer -->
        <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between py-2 border-top gap-2">
            <div class="text-muted font-size-12" id="pageInfoText">
                Showing 0 to 0 of 0 claim records
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-end" id="paginationList">
                    <!-- Dynamic Pagination -->
                </ul>
            </nav>
        </div>
    </div>
</div>
</div>

@include('partials.organogram-filters-drawer')

<!-- Modal for Viewing Photos -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2 bg-light">
                <h6 class="modal-title font-size-14 fw-bold" id="imageModalTitle">Odometer Reading Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3 bg-dark">
                <img src="" id="imageModalImg" class="img-fluid rounded" style="max-height: 520px;" alt="Odometer Reading">
            </div>
            <div class="modal-footer py-2 justify-content-between bg-light">
                <span class="text-muted font-size-12" id="imageModalCaption"></span>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="modalOpenFullBtn" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="ri-external-link-line me-1"></i> Open Full Image
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 dropdowns inside offcanvas drawer
    function initDrawerSelect2() {
        $('.select2').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    dropdownParent: $('#filterDrawer'),
                    width: '100%',
                    closeOnSelect: false
                });
            }
        });
    }
    initDrawerSelect2();
    $('#filterDrawer').on('shown.bs.offcanvas', function() {
        initDrawerSelect2();
    });

    let allReportRows = [];
    let currentPage = 1;
    let pageSize = 25;

    // Set Default Date Range (This Month)
    setDatePreset('thisMonth');

    // Date Preset Handler
    $('#datePresets button').on('click', function() {
        $('#datePresets button').removeClass('active btn-primary').addClass('btn-outline-primary');
        $(this).addClass('active btn-primary').removeClass('btn-outline-primary');
        const preset = $(this).data('preset');
        setDatePreset(preset);
        fetchReportData();
    });

    function setDatePreset(preset) {
        const today = new Date();
        let from, to;

        if (preset === 'today') {
            from = to = formatDate(today);
        } else if (preset === 'yesterday') {
            const yest = new Date(today);
            yest.setDate(yest.getDate() - 1);
            from = to = formatDate(yest);
        } else if (preset === 'last7') {
            to = formatDate(today);
            const d7 = new Date(today);
            d7.setDate(d7.getDate() - 6);
            from = formatDate(d7);
        } else if (preset === 'thisMonth') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            from = formatDate(firstDay);
            to = formatDate(today);
        } else if (preset === 'lastMonth') {
            const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
            from = formatDate(firstDay);
            to = formatDate(lastDay);
        } else if (preset === 'last90') {
            to = formatDate(today);
            const d90 = new Date(today);
            d90.setDate(d90.getDate() - 89);
            from = formatDate(d90);
        }

        $('#f_from_date').val(from);
        $('#f_to_date').val(to);
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    // Collect Filter Parameters
    function getFilterParams() {
        const organogram = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
        return {
            _token: "{{ csrf_token() }}",
            from_date: $('#f_from_date').val(),
            to_date: $('#f_to_date').val(),
            continuity_status: $('#f_continuity_status').val(),
            min_gap: $('#f_min_gap').val(),
            max_gap: $('#f_max_gap').val(),
            vehicle_type: $('#f_vehicle_type').val(),
            search: $('#f_search').val(),
            year_id: $('#f_year_id').val(),
            month: $('#f_month').val(),
            claim_status: $('#f_claim_status').val(),
            min_km: $('#f_min_km').val(),
            max_km: $('#f_max_km').val(),
            ...organogram
        };
    }

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        fetchReportData();
    });

    function updateActiveFilterBadge() {
        let count = 0;
        if ($('#f_continuity_status').val() !== 'all') count++;
        if ($('#f_min_gap').val() || $('#f_max_gap').val()) count++;
        if ($('#f_vehicle_type').val()) count++;
        if ($('#f_departments').val() && $('#f_departments').val().length > 0) count++;
        if ($('#f_designations').val() && $('#f_designations').val().length > 0) count++;
        if ($('#f_headquarters').val() && $('#f_headquarters').val().length > 0) count++;
        if ($('#f_verticals').val() && $('#f_verticals').val().length > 0) count++;
        if ($('#f_employees').val() && $('#f_employees').val().length > 0) count++;
        if ($('#f_claim_status').val() !== 'all') count++;
        if ($('#f_min_km').val() || $('#f_max_km').val()) count++;

        if (count > 0) {
            $('#activeFilterBadge').text(count).show();
        } else {
            $('#activeFilterBadge').hide();
        }
    }

    // Fetch and render report data via AJAX
    function fetchReportData() {
        $('#tableBody').html(`
            <tr>
                <td colspan="14" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Calculating employee odometer continuity and analyzing reading gaps...
                </td>
            </tr>
        `);

        updateActiveFilterBadge();
        const params = getFilterParams();

        $.ajax({
            url: "{{ route('reports.odometer-continuity.data') }}",
            type: "POST",
            data: params,
            success: function(res) {
                if (res.success) {
                    renderSummary(res.summary);
                    allReportRows = res.data || [];
                    currentPage = 1;
                    renderTablePage();
                } else {
                    showError(res.message || 'Error loading report data');
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || xhr.statusText || 'Server communication error';
                showError(msg);
            }
        });
    }

    function renderSummary(summary) {
        if (!summary) return;
        $('#kpiTotalClaims').text(Number(summary.total_claims || 0).toLocaleString());
        $('#kpiUniqueEmployees').text((summary.total_employees || 0) + ' Employees');
        $('#kpiContinuousCount').text(Number(summary.continuous_count || 0).toLocaleString());
        $('#kpiGapCount').text(Number(summary.gap_count || 0).toLocaleString());
        $('#kpiTotalGapKm').text(Number(summary.total_gap_km || 0).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' KM Unaccounted');
        $('#kpiOverlapCount').text(Number(summary.overlap_count || 0).toLocaleString());
        $('#kpiInitialCount').text(Number(summary.initial_count || 0).toLocaleString());
        $('#kpiTotalClaimedKm').text(Number(summary.total_claimed_km || 0).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' KM');
    }

    function renderTablePage() {
        const totalRecords = allReportRows.length;
        if (totalRecords === 0) {
            $('#tableBody').html(`
                <tr>
                    <td colspan="14" class="text-center py-5 text-muted">
                        <i class="ri-inbox-line fs-36 d-block mb-2 text-secondary"></i>
                        No odometer claim records found matching your filter criteria.
                    </td>
                </tr>
            `);
            $('#pageInfoText').text('Showing 0 to 0 of 0 claim records');
            $('#paginationList').empty();
            return;
        }

        let pageRows = [];
        let startIndex = 0;
        let endIndex = totalRecords;

        if (pageSize === -1) {
            pageRows = allReportRows;
            $('#pageInfoText').text(`Showing 1 to ${totalRecords} of ${totalRecords} claim records`);
        } else {
            const totalPages = Math.ceil(totalRecords / pageSize) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            startIndex = (currentPage - 1) * pageSize;
            endIndex = Math.min(startIndex + pageSize, totalRecords);
            pageRows = allReportRows.slice(startIndex, endIndex);

            $('#pageInfoText').text(`Showing ${startIndex + 1} to ${endIndex} of ${totalRecords} claim records`);
        }

        renderTableRows(pageRows, startIndex);
        renderPagination(totalRecords);
    }

    function renderPagination(totalRecords) {
        if (pageSize === -1 || totalRecords <= pageSize) {
            $('#paginationList').empty();
            return;
        }

        const totalPages = Math.ceil(totalRecords / pageSize);
        let html = '';

        // First & Prev
        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        html += `
            <li class="page-item ${prevDisabled}">
                <a class="page-link page-link-btn" href="#" data-page="1" title="First Page">«</a>
            </li>
            <li class="page-item ${prevDisabled}">
                <a class="page-link page-link-btn" href="#" data-page="${currentPage - 1}" title="Previous Page">‹</a>
            </li>
        `;

        // Page Numbers Window
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link page-link-btn" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link page-link-btn" href="#" data-page="${i}">${i}</a></li>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link page-link-btn" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Next & Last
        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        html += `
            <li class="page-item ${nextDisabled}">
                <a class="page-link page-link-btn" href="#" data-page="${currentPage + 1}" title="Next Page">›</a>
            </li>
            <li class="page-item ${nextDisabled}">
                <a class="page-link page-link-btn" href="#" data-page="${totalPages}" title="Last Page">»</a>
            </li>
        `;

        $('#paginationList').html(html);
    }

    function renderTableRows(rows, startIndex) {
        let html = '';
        let lastMonth = null;

        rows.forEach(function(row, idx) {
            const sn = startIndex + idx + 1;

            // Month Divider Row
            if (row.month_name && row.month_name !== lastMonth) {
                html += `
                    <tr class="month-header-row">
                        <td colspan="14">
                            <i class="ri-calendar-event-line text-primary me-1"></i> ${row.month_name}
                        </td>
                    </tr>
                `;
                lastMonth = row.month_name;
            }

            // Severity border & highlight
            let severityClass = '';
            if (row.status === 'gap') {
                severityClass = (row.gap_km > 50) ? 'row-gap-danger' : 'row-gap-warning';
            } else if (row.status === 'overlap') {
                severityClass = 'row-gap-overlap';
            } else if (row.status === 'continuous') {
                severityClass = 'row-continuous';
            } else {
                severityClass = 'row-initial';
            }

            // Employee row tint
            const empTintClass = row.emp_color_class ? ' ' + row.emp_color_class.replace('emp-color-', 'emp-row-') : '';

            // Previous Closing format with previous date and Exp ID
            let prevCloseText = '-';
            if (row.prev_closing_km !== null) {
                const prevVal = Number(row.prev_closing_km).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1});
                const prevDateText = row.prev_date ? row.prev_date : '';
                const prevExpText = row.prev_exp_id ? `#${row.prev_exp_id}` : '';
                prevCloseText = `
                    <div class="fw-semibold text-dark font-size-12">${prevVal} KM</div>
                    ${prevDateText ? `<div class="text-muted font-size-10 text-nowrap"><i class="ri-history-line me-1"></i>${prevDateText}${prevExpText ? ` <span class="badge bg-light text-secondary border font-size-9 px-1 py-0">${prevExpText}</span>` : ''}</div>` : ''}
                `;
            } else {
                prevCloseText = '<span class="text-muted font-size-11 fst-italic">First Entry</span>';
            }

            // Gap format with comparison summary tooltip
            let gapText = '-';
            if (row.prev_closing_km !== null) {
                const gapNum = Number(row.gap_km);
                const compareTooltip = row.comparison_detail ? `title="${row.comparison_detail.replace(/"/g, '&quot;')}"` : '';
                if (gapNum === 0) {
                    gapText = `<div ${compareTooltip} style="cursor:help;"><span class="text-success fw-bold">0.0 KM</span><div class="text-muted font-size-10">Match</div></div>`;
                } else if (gapNum > 0) {
                    gapText = `<div ${compareTooltip} style="cursor:help;"><span class="text-danger fw-bold">+${gapNum.toFixed(1)} KM</span><div class="text-danger font-size-10">Unaccounted</div></div>`;
                } else {
                    gapText = `<div ${compareTooltip} style="cursor:help;"><span class="text-purple fw-bold">${gapNum.toFixed(1)} KM</span><div class="text-purple font-size-10">Overlap</div></div>`;
                }
            }

            // Status Badge
            let badgeHtml = '';
            if (row.status === 'continuous') {
                badgeHtml = '<span class="badge-status badge-continuous"><i class="ri-checkbox-circle-fill me-1"></i>Continuous</span>';
            } else if (row.status === 'gap') {
                if (row.gap_km > 50) {
                    badgeHtml = `<span class="badge-status badge-danger-gap"><i class="ri-error-warning-fill me-1"></i>+${row.gap_km.toFixed(1)} KM Gap</span>`;
                } else {
                    badgeHtml = `<span class="badge-status badge-warning-gap"><i class="ri-alert-fill me-1"></i>+${row.gap_km.toFixed(1)} KM Gap</span>`;
                }
            } else if (row.status === 'overlap') {
                badgeHtml = `<span class="badge-status badge-overlap"><i class="ri-arrow-go-back-fill me-1"></i>${row.gap_km.toFixed(1)} KM Overlap</span>`;
            } else {
                badgeHtml = '<span class="badge-status badge-initial"><i class="ri-flag-fill me-1"></i>Initial</span>';
            }

            // Photos Thumbnail
            let photosHtml = '';
            if (row.has_open_img && row.open_img) {
                photosHtml += `
                    <div class="d-inline-block position-relative me-1" title="Opening Odometer: ${row.open_km} KM">
                        <img src="${row.open_img}" class="odo-thumb btn-view-photo"
                             data-img="${row.open_img}"
                             data-backup="${row.backup_open_img || ''}"
                             data-caption="Opening Odometer: ${Number(row.open_km).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})} KM (${row.bill_date})"
                             data-title="Opening Odometer - Exp #${row.exp_id}"
                             alt="Opening Photo"
                             onerror="if(!this.dataset.tried && '${row.backup_open_img}'){this.dataset.tried=1;this.src='${row.backup_open_img}';}else{this.onerror=null;this.src='https://placehold.co/80x80?text=No+Photo';}">
                        <span class="position-absolute bottom-0 start-0 bg-dark bg-opacity-75 text-white font-size-9 px-1 rounded-bottom" style="font-size:9px;line-height:1.2;pointer-events:none;">Open</span>
                    </div>
                `;
            }
            if (row.has_close_img && row.close_img) {
                photosHtml += `
                    <div class="d-inline-block position-relative" title="Closing Odometer: ${row.close_km} KM">
                        <img src="${row.close_img}" class="odo-thumb btn-view-photo"
                             data-img="${row.close_img}"
                             data-backup="${row.backup_close_img || ''}"
                             data-caption="Closing Odometer: ${Number(row.close_km).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})} KM (${row.bill_date})"
                             data-title="Closing Odometer - Exp #${row.exp_id}"
                             alt="Closing Photo"
                             onerror="if(!this.dataset.tried && '${row.backup_close_img}'){this.dataset.tried=1;this.src='${row.backup_close_img}';}else{this.onerror=null;this.src='https://placehold.co/80x80?text=No+Photo';}">
                        <span class="position-absolute bottom-0 start-0 bg-dark bg-opacity-75 text-white font-size-9 px-1 rounded-bottom" style="font-size:9px;line-height:1.2;pointer-events:none;">Close</span>
                    </div>
                `;
            }
            if (!photosHtml) {
                photosHtml = '<span class="no-img-badge">No Photo</span>';
            }

            // Vehicle badge (2W / 4W only)
            const vBadgeClass = row.vehicle_type === '4W'
                ? 'bg-primary-subtle text-primary border border-primary-subtle'
                : 'bg-info-subtle text-info border border-info-subtle';

            html += `
                <tr class="${severityClass}${empTintClass}">
                    <td class="text-center text-muted">${sn}</td>
                    <td class="fw-semibold font-size-11">
                        <span class="badge bg-light text-dark border">#${row.exp_id}</span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark font-size-12">${row.emp_name}</div>
                        <div class="text-muted font-size-11">${row.emp_code}</div>
                    </td>
                    <td>
                        <div class="text-dark font-size-11">${row.department}</div>
                        <div class="text-muted font-size-10">${row.headquarter}</div>
                    </td>
                    <td class="text-nowrap">
                        <div class="fw-semibold text-dark font-size-12">${row.bill_date}</div>
                        <div class="text-muted font-size-10">${row.day_of_week}</div>
                    </td>
                    <td class="text-center">
                        <span class="badge ${vBadgeClass} font-size-11 fw-semibold">${row.vehicle_type}</span>
                    </td>
                    <td class="text-end fw-semibold text-primary font-size-12">
                        ${Number(row.open_km).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}
                    </td>
                    <td class="text-end fw-semibold text-dark font-size-12">
                        ${Number(row.close_km).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}
                    </td>
                    <td class="text-end fw-bold text-success font-size-12">
                        ${Number(row.claimed_km).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}
                    </td>
                    <td class="text-end font-size-12 bg-light-subtle" title="${(row.comparison_detail || '').replace(/"/g, '&quot;')}" style="cursor:help;">
                        ${prevCloseText}
                    </td>
                    <td class="text-end font-size-12">
                        ${gapText}
                    </td>
                    <td class="text-center">
                        ${badgeHtml}
                    </td>
                    <td class="text-center text-nowrap">
                        ${photosHtml}
                    </td>
                    <td class="font-size-11 text-muted" style="max-width: 160px; white-space: normal;">
                        ${row.remarks || '-'}
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
    }

    // Pagination Click Handler
    $(document).on('click', '.page-link-btn', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (!isNaN(page) && page !== currentPage) {
            currentPage = page;
            renderTablePage();
            $('html, body').animate({
                scrollTop: $("#continuityTable").offset().top - 80
            }, 200);
        }
    });

    // Page Size Change
    $('#pageSizeSelect').on('change', function() {
        pageSize = parseInt($(this).val());
        currentPage = 1;
        renderTablePage();
    });

    // Prevent drawer form submit on Enter key
    $('#drawerFilterForm').on('submit', function(e) {
        e.preventDefault();
        fetchReportData();
    });

    // Filter Change Handlers
    $('#btnApply, #btnDrawerApply').on('click', function() {
        fetchReportData();
    });

    $('#f_continuity_status, #f_vehicle_type').on('change', function() {
        fetchReportData();
    });

    // Debounced Search
    let searchTimer = null;
    $('#f_search, #f_min_gap, #f_max_gap').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            fetchReportData();
        }, 400);
    });

    // Clear Filters
    $('#btnClear, #btnDrawerReset').on('click', function() {
        $('#f_continuity_status').val('all');
        $('#f_min_gap').val('');
        $('#f_max_gap').val('');
        $('#f_min_km').val('');
        $('#f_max_km').val('');
        $('#f_vehicle_type').val('');
        $('#f_search').val('');
        $('#f_month').val('');
        $('#f_claim_status').val('all');
        $('.select2').val(null).trigger('change');
        setDatePreset('thisMonth');
        $('#datePresets button').removeClass('active btn-primary').addClass('btn-outline-primary');
        $('#datePresets button[data-preset="thisMonth"]').addClass('active btn-primary').removeClass('btn-outline-primary');
        fetchReportData();
    });

    // KPI Card Quick Filters
    $('#kpiCardAll').on('click', function() {
        $('#f_continuity_status').val('all');
        fetchReportData();
    });
    $('#kpiCardContinuous').on('click', function() {
        $('#f_continuity_status').val('continuous');
        fetchReportData();
    });
    $('#kpiCardGaps').on('click', function() {
        $('#f_continuity_status').val('only_gaps');
        fetchReportData();
    });
    $('#kpiCardOverlap').on('click', function() {
        $('#f_continuity_status').val('overlap');
        fetchReportData();
    });

    // View Photos Modal
    $(document).on('click', '.btn-view-photo', function() {
        const currentSrc = $(this).attr('src') || $(this).data('img');
        const caption = $(this).data('caption') || '';
        const title = $(this).data('title') || 'Odometer Reading Photo';
        const backupUrl = $(this).data('backup') || '';

        $('#imageModalTitle').text(title);
        $('#imageModalCaption').text(caption);
        $('#imageModalImg').attr('src', currentSrc);
        $('#modalOpenFullBtn').attr('href', currentSrc);

        $('#imageModalImg').off('error').on('error', function() {
            if (backupUrl && $(this).attr('src') !== backupUrl) {
                $(this).attr('src', backupUrl);
                $('#modalOpenFullBtn').attr('href', backupUrl);
            }
        });

        new bootstrap.Modal(document.getElementById('imageModal')).show();
    });

    // Excel Export Handler
    $('#btnExport').on('click', function() {
        const params = getFilterParams();
        delete params._token;
        const queryString = $.param(params);
        window.location.href = "{{ route('reports.odometer-continuity.export') }}?" + queryString;
    });

    function showError(message) {
        $('#tableBody').html(`
            <tr>
                <td colspan="14" class="text-center py-4 text-danger">
                    <i class="ri-error-warning-line fs-24 d-block mb-1"></i>
                    ${message}
                </td>
            </tr>
        `);
    }

    // Initial Load
    fetchReportData();
});
</script>
@include('partials.organogram-filters-js')
@endpush
