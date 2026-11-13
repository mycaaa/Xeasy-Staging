@extends('layouts.app')

@push('styles')
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
<style>
    .comparison-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    .comparison-table th {
        background: #dde6ff;
        color: #1e3a8a;
        padding: 8px 6px;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #0d4f70;
        white-space: nowrap;
    }
    .comparison-table td {
        padding: 6px 6px;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
        font-size: 12px;
    }
    .comparison-table tbody tr:hover {
        background: #f8fafc;
    }
    .row-high-diff {
        background-color: #fff1f2 !important;
        border-left: 4px solid #e11d48 !important;
    }
    .row-high-diff:hover {
        background-color: #ffe4e6 !important;
    }
    .row-moderate-diff {
        background-color: #fffbeb !important;
        border-left: 4px solid #f59e0b !important;
    }
    .row-no-tracking {
        background-color: #f8fafc !important;
        border-left: 4px solid #94a3b8 !important;
    }
    .kpi-card {
        border-radius: 8px;
        padding: 12px 16px;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        background: #fff;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
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
    .odo-thumb {
        width: 46px;
        height: 46px;
        object-fit: cover;
        border-radius: 6px;
        cursor: pointer;
        border: 1px solid #cbd5e1;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .odo-thumb:hover {
        transform: scale(1.15);
        box-shadow: 0 4px 10px rgba(0,0,0,0.18);
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
                            <h4 class="mb-0 font-size-18 fw-bold text-dark">Odometer vs Geolocation Tracking Report</h4>
                            <p class="text-muted mb-0 font-size-12">Comparison of Odometer Readings (Claim ID 7) against Geolocation Tracking Data</p>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefresh">
                            <i class="ri-refresh-line me-1"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="btnExport">
                            <i class="ri-file-excel-2-line me-1"></i> Export Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="row g-3 mb-3" id="kpiContainer">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card border-primary-subtle">
                    <div class="kpi-title"><i class="ri-file-list-3-line text-primary me-1"></i> Total Claims</div>
                    <div class="kpi-value text-primary" id="kpiTotalClaims">0</div>
                    <small class="text-muted" style="font-size:11px;">Claim ID 7 entries</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card border-info-subtle">
                    <div class="kpi-title"><i class="ri-dashboard-line text-info me-1"></i> Claimed KM</div>
                    <div class="kpi-value text-info" id="kpiClaimedKm">0.00</div>
                    <small class="text-muted" style="font-size:11px;">Odometer Distance</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card border-success-subtle">
                    <div class="kpi-title"><i class="ri-map-pin-user-line text-success me-1"></i> Tracked KM</div>
                    <div class="kpi-value text-success" id="kpiTrackedKm">0.00</div>
                    <small class="text-muted" style="font-size:11px;">GPS Tracking Distance</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card border-warning-subtle">
                    <div class="kpi-title"><i class="ri-scales-3-line text-warning me-1"></i> Net Difference</div>
                    <div class="kpi-value text-warning" id="kpiNetDiff">0.00</div>
                    <small class="text-muted" style="font-size:11px;">Claimed − Tracked</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card border-danger-subtle" role="button" id="btnFilterHighDiff" title="Click to filter High Discrepancy">
                    <div class="kpi-title"><i class="ri-alarm-warning-line text-danger me-1"></i> High Discrepancy</div>
                    <div class="kpi-value text-danger" id="kpiHighDiff">0</div>
                    <small class="text-danger fw-semibold" style="font-size:11px;">Diff > 15 KM (Alert)</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card border-secondary-subtle" role="button" id="btnFilterNoTracking" title="Click to filter No Tracking">
                    <div class="kpi-title"><i class="ri-signal-wifi-off-line text-secondary me-1"></i> No Tracking Data</div>
                    <div class="kpi-value text-secondary" id="kpiNoTracking">0</div>
                    <small class="text-muted" style="font-size:11px;">0 GPS Sessions</small>
                </div>
            </div>
        </div>

        <!-- Main Card & Filters -->
        <div class="card shadow-sm border">
            <div class="card-header bg-white py-2 border-bottom">
                <!-- Toolbar Row 1: Date Presets, Date Range & Actions -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 border-bottom">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Date Presets -->
                        <div class="btn-group btn-group-sm" role="group" id="datePresets">
                            <button type="button" class="btn btn-outline-primary" data-preset="today">Today</button>
                            <button type="button" class="btn btn-outline-primary" data-preset="yesterday">Yesterday</button>
                            <button type="button" class="btn btn-outline-primary" data-preset="last7">Last 7 Days</button>
                            <button type="button" class="btn btn-primary active" data-preset="thisMonth">This Month</button>
                            <button type="button" class="btn btn-outline-primary" data-preset="lastMonth">Last Month</button>
                        </div>

                        <!-- Date Range Inputs -->
                        <div class="d-flex align-items-center gap-1">
                            <input type="date" id="f_from_date" class="form-control form-control-sm" style="width:135px;" title="From Date">
                            <span class="text-muted font-size-12">to</span>
                            <input type="date" id="f_to_date" class="form-control form-control-sm" style="width:135px;" title="To Date">
                        </div>
                    </div>

                    <!-- Search & Clear Buttons -->
                    <div class="d-flex align-items-center gap-1 ms-auto">
                        <button type="button" class="btn btn-primary btn-sm px-3" id="btnApply">
                            <i class="ri-check-line me-1"></i> Apply
                        </button>
                        <button type="button" class="btn btn-light btn-sm text-secondary" id="btnClear" title="Clear Filters">
                            <i class="ri-restart-line"></i>
                        </button>
                    </div>
                </div>

                <!-- Toolbar Row 2: Filters, Search & Per Page -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Discrepancy Filter -->
                        <select id="f_difference_filter" class="form-select form-select-sm" style="width:170px;">
                            <option value="all">All Discrepancies</option>
                            <option value="high">🔴 High Diff (> 15 KM)</option>
                            <option value="moderate">🟡 Moderate (5 - 15 KM)</option>
                            <option value="match">🟢 Matched (≤ 5 KM)</option>
                            <option value="no_tracking">⚪ No Tracking Data</option>
                            <option value="zero_km">⚫ Zero KM / No Travel</option>
                            <option value="claimed_higher">Claimed > Tracked</option>
                            <option value="tracking_higher">Tracked > Claimed</option>
                        </select>

                        <!-- Vehicle Type -->
                        <select id="f_vehicle_type" class="form-select form-select-sm" style="width:120px;">
                            <option value="">All Vehicles</option>
                            <option value="2W">2-Wheeler</option>
                            <option value="4W">4-Wheeler</option>
                        </select>

                        <!-- Search Input -->
                        <div class="input-group input-group-sm" style="width:210px;">
                            <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                            <input type="text" id="f_search" class="form-control" placeholder="Search name/code/ID..." aria-label="Search name/code/ID">
                        </div>

                        <!-- More Filters Toggle -->
                        <button type="button" class="btn btn-outline-primary btn-sm position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                            <i class="ri-equalizer-line me-1"></i> More Filters
                            <span id="organogramFilterCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
                        </button>
                    </div>

                    <!-- Per Page Option -->
                    <div class="d-flex align-items-center gap-1 ms-auto">
                        <span class="text-muted font-size-12">Show:</span>
                        <select id="f_per_page" class="form-select form-select-sm" style="width:75px;">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="-1">All</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Table Body -->
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 650px;">
                    <table class="table table-hover comparison-table mb-0" id="comparisonTable">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width:40px;">SN</th>
                                <th style="width:220px;text-align:left;">Employee Details</th>
                                <th style="width:100px;">Travel Date</th>
                                <th style="width:85px;">Exp ID</th>
                                <th style="width:65px;">Type</th>
                                <th style="width:130px;">Opening KM & Photo</th>
                                <th style="width:130px;">Closing KM & Photo</th>
                                <th style="width:95px;">Claimed KM</th>
                                <th style="width:115px;">Tracked KM</th>
                                <th style="width:105px;">Difference</th>
                                <th style="width:60px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Loading Odometer & Tracking comparison data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table Footer info & Pagination -->
            <div class="card-footer bg-white py-2 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="text-muted font-size-12">
                        <span id="pageInfoText">Showing 0 to 0 of 0 claim records</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-muted font-size-12">Show:</span>
                        <select id="f_per_page_bottom" class="form-select form-select-sm" style="width:75px;font-size:12px;padding:2px 8px;">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="-1">All</option>
                        </select>
                    </div>
                </div>

                <div class="d-none d-lg-flex align-items-center gap-3 font-size-12">
                    <span class="d-flex align-items-center gap-1"><span class="badge bg-danger rounded-circle p-1"> </span> Alert (> 15 KM)</span>
                    <span class="d-flex align-items-center gap-1"><span class="badge bg-warning rounded-circle p-1"> </span> Moderate (5-15 KM)</span>
                    <span class="d-flex align-items-center gap-1"><span class="badge bg-success rounded-circle p-1"> </span> Match (≤ 5 KM)</span>
                    <span class="d-flex align-items-center gap-1"><span class="badge bg-secondary rounded-circle p-1"> </span> No Tracking</span>
                    <span class="d-flex align-items-center gap-1"><span class="badge bg-light text-muted border rounded-circle p-1"> </span> Zero KM</span>
                </div>

                <!-- Pagination Nav Controls -->
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0" id="paginationList">
                        <!-- Populated by JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>

@include('partials.organogram-filters-drawer')

<!-- Modal: Image Preview (Side-by-Side Opening & Closing) -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-size-14 text-white" id="imageModalLabel">
                    <i class="ri-image-line me-1"></i> Odometer Photos Inspection
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                    <div>
                        <span class="fw-bold text-dark" id="modalEmpDetails">Employee Name (Code)</span>
                        <span class="text-muted ms-2" id="modalTravelDate">Date</span>
                    </div>
                    <div>
                        <span class="badge bg-primary px-2 py-1 font-size-12" id="modalClaimedKm">Claimed: 0 KM</span>
                        <span class="badge bg-info px-2 py-1 font-size-12 ms-1" id="modalTrackedKm">Tracked: 0 KM</span>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Opening Photo -->
                    <div class="col-md-6">
                        <div class="card h-100 shadow-none border">
                            <div class="card-header bg-white py-1 px-3 d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-primary font-size-12"><i class="ri-login-box-line me-1"></i> Opening Odometer</span>
                                <span class="badge bg-primary-subtle text-primary" id="modalOpenReading">0 KM</span>
                            </div>
                            <div class="card-body p-2 text-center bg-white d-flex align-items-center justify-content-center" style="min-height:300px;">
                                <img src="" id="modalOpenImg" class="img-fluid rounded" style="max-height:340px;object-fit:contain;" alt="Opening Reading Photo">
                                <div id="modalOpenNoImg" class="text-muted p-4" style="display:none;">
                                    <i class="ri-image-line fs-36 text-secondary d-block mb-2"></i>
                                    No Opening Image Uploaded
                                </div>
                            </div>
                            <div class="card-footer bg-white py-1 px-2 text-end">
                                <a href="#" id="modalOpenFullBtn" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ri-external-link-line me-1"></i> Open Full View
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Closing Photo -->
                    <div class="col-md-6">
                        <div class="card h-100 shadow-none border">
                            <div class="card-header bg-white py-1 px-3 d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-success font-size-12"><i class="ri-logout-box-line me-1"></i> Closing Odometer</span>
                                <span class="badge bg-success-subtle text-success" id="modalCloseReading">0 KM</span>
                            </div>
                            <div class="card-body p-2 text-center bg-white d-flex align-items-center justify-content-center" style="min-height:300px;">
                                <img src="" id="modalCloseImg" class="img-fluid rounded" style="max-height:340px;object-fit:contain;" alt="Closing Reading Photo">
                                <div id="modalCloseNoImg" class="text-muted p-4" style="display:none;">
                                    <i class="ri-image-line fs-36 text-secondary d-block mb-2"></i>
                                    No Closing Image Uploaded
                                </div>
                            </div>
                            <div class="card-footer bg-white py-1 px-2 text-end">
                                <a href="#" id="modalCloseFullBtn" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ri-external-link-line me-1"></i> Open Full View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tracking Sessions Inspection -->
<div class="modal fade" id="trackingDetailsModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-theme text-white py-2">
                <h5 class="modal-title font-size-14 text-white" id="trackingModalLabel">
                    <i class="ri-map-pin-range-line me-1"></i> Geolocation Tracking Session Logs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong id="trackModalEmp">Employee</strong> | Date: <span id="trackModalDate">Date</span>
                    </div>
                    <div>
                        Total Distance Tracked: <strong id="trackModalTotalDist" class="text-primary">0.00 KM</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm font-size-12 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Session #</th>
                                <th>Started At</th>
                                <th>Ended At</th>
                                <th>Distance (KM)</th>
                                <th>Moving Time</th>
                                <th>Stopped Time</th>
                                <th>Status</th>
                                <th>Battery</th>
                            </tr>
                        </thead>
                        <tbody id="trackSessionsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-3 text-muted">Loading session logs...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Stops section -->
                <div class="mt-4" id="stopsContainer" style="display:none;">
                    <h6 class="fw-bold font-size-12 text-dark mb-2"><i class="ri-stop-circle-line text-warning me-1"></i> Recorded Stops & Locations</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm font-size-11 mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Arrival</th>
                                    <th>Departure</th>
                                    <th>Duration</th>
                                    <th>Address / Coordinates</th>
                                </tr>
                            </thead>
                            <tbody id="trackStopsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 dropdowns
    $('#f_departments, #f_designations, #f_headquarters, #f_verticals, #f_functions, #f_employees').select2({
        placeholder: "Select options...",
        allowClear: true,
        closeOnSelect: false
    });

    // Pagination State
    let allReportRows = [];
    let currentPage = 1;
    let pageSize = 25;

    // Per Page Change Handlers (sync top & bottom)
    $('#f_per_page, #f_per_page_bottom').on('change', function() {
        pageSize = parseInt($(this).val());
        $('#f_per_page, #f_per_page_bottom').val(pageSize);
        currentPage = 1;
        renderTablePage();
    });

    // Pagination Click Handler
    $(document).on('click', '.page-link-btn', function(e) {
        e.preventDefault();
        const targetPage = parseInt($(this).data('page'));
        if (!isNaN(targetPage) && targetPage >= 1 && targetPage !== currentPage) {
            currentPage = targetPage;
            renderTablePage();
            $('html, body').animate({ scrollTop: $('#comparisonTable').offset().top - 140 }, 150);
        }
    });

    // Set Default Date Range: This Month
    setDatePreset('thisMonth');

    // Load initial data
    fetchReportData();

    // Preset Buttons Click Handler
    $('#datePresets button').on('click', function() {
        $('#datePresets button').removeClass('btn-primary active').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        const preset = $(this).data('preset');
        setDatePreset(preset);
        fetchReportData();
    });

    // Apply Button
    $('#btnApply, #btnApplyDrawer').on('click', function() {
        updateActiveFilterBadge();
        fetchReportData();
    });

    // Refresh Button
    $('#btnRefresh').on('click', function() {
        fetchReportData();
    });

    // Quick Discrepancy KPI Cards Clicks
    $('#btnFilterHighDiff').on('click', function() {
        $('#f_difference_filter').val('high');
        fetchReportData();
    });
    $('#btnFilterNoTracking').on('click', function() {
        $('#f_difference_filter').val('no_tracking');
        fetchReportData();
    });

    // Filter Change Handlers
    $('#f_difference_filter, #f_vehicle_type').on('change', function() {
        fetchReportData();
    });

    // Enter in Search box
    $('#f_search').on('keyup', function(e) {
        if (e.key === 'Enter') {
            fetchReportData();
        }
    });

    // Clear Filters
    $('#btnClear, #btnClearDrawer').on('click', function() {
        $('#f_search').val('');
        $('#f_difference_filter').val('all');
        $('#f_vehicle_type').val('');
        $('#f_month').val('');
        $('#f_claim_status').val('all');
        $('#f_diff_threshold').val(15);
        if (typeof window.resetOrganogramFilters === 'function') {
            window.resetOrganogramFilters();
        }
        setDatePreset('thisMonth');
        $('#datePresets button').removeClass('btn-primary active').addClass('btn-outline-primary');
        $('#datePresets button[data-preset="thisMonth"]').removeClass('btn-outline-primary').addClass('btn-primary active');
        fetchReportData();
    });

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        fetchReportData();
    });

    // Export Button
    $('#btnExport').on('click', function() {
        const params = getFilterParams();
        const queryString = $.param(params);
        window.location.href = "{{ route('reports.odometer-tracking.export') }}?" + queryString;
    });

    // Date Preset Helper
    function setDatePreset(preset) {
        const today = new Date();
        let fromDate = new Date();
        let toDate = new Date();

        if (preset === 'today') {
            // today is today
        } else if (preset === 'yesterday') {
            fromDate.setDate(today.getDate() - 1);
            toDate.setDate(today.getDate() - 1);
        } else if (preset === 'last7') {
            fromDate.setDate(today.getDate() - 6);
        } else if (preset === 'thisMonth') {
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = today;
        } else if (preset === 'lastMonth') {
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            toDate = new Date(today.getFullYear(), today.getMonth(), 0);
        }

        $('#f_from_date').val(formatDate(fromDate));
        $('#f_to_date').val(formatDate(toDate));
    }

    function formatDate(d) {
        const month = '' + (d.getMonth() + 1);
        const day = '' + d.getDate();
        const year = d.getFullYear();
        return [year, month.padStart(2, '0'), day.padStart(2, '0')].join('-');
    }

    // Collect Filter Parameters
    function getFilterParams() {
        const organogram = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
        return {
            _token: "{{ csrf_token() }}",
            from_date: $('#f_from_date').val(),
            to_date: $('#f_to_date').val(),
            difference_filter: $('#f_difference_filter').val(),
            vehicle_type: $('#f_vehicle_type').val(),
            search: $('#f_search').val(),
            year_id: $('#f_year_id').val(),
            month: $('#f_month').val(),
            diff_threshold: $('#f_diff_threshold').val() || 15,
            claim_status: $('#f_claim_status').val(),
            ...organogram
        };
    }

    function updateActiveFilterBadge() {
        let count = 0;
        if ($('#f_difference_filter').val() !== 'all') count++;
        if ($('#f_vehicle_type').val()) count++;
        if ($('#f_departments').val() && $('#f_departments').val().length > 0) count++;
        if ($('#f_designations').val() && $('#f_designations').val().length > 0) count++;
        if ($('#f_headquarters').val() && $('#f_headquarters').val().length > 0) count++;
        if ($('#f_verticals').val() && $('#f_verticals').val().length > 0) count++;
        if ($('#f_employees').val() && $('#f_employees').val().length > 0) count++;
        if ($('#f_claim_status').val() !== 'all') count++;

        if (count > 0) {
            $('#activeFilterBadge').text(count).show();
        } else {
            $('#activeFilterBadge').hide();
        }
    }

    // Fetch and render report data
    function fetchReportData() {
        $('#tableBody').html(`
            <tr>
                <td colspan="12" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Comparing Odometer and Geolocation Tracking data...
                </td>
            </tr>
        `);

        const params = getFilterParams();

        $.ajax({
            url: "{{ route('reports.odometer-tracking.data') }}",
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
        $('#kpiClaimedKm').text(Number(summary.total_claimed_km || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' KM');
        $('#kpiTrackedKm').text(Number(summary.total_tracked_km || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' KM');

        const netDiff = Number(summary.total_difference_km || 0);
        const netSign = netDiff > 0 ? '+' : '';
        $('#kpiNetDiff').text(netSign + netDiff.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' KM');

        if (netDiff > 0) {
            $('#kpiNetDiff').removeClass('text-success').addClass('text-danger');
        } else {
            $('#kpiNetDiff').removeClass('text-danger').addClass('text-success');
        }

        $('#kpiHighDiff').text(Number(summary.high_discrepancy_count || 0).toLocaleString());
        $('#kpiNoTracking').text(Number(summary.no_tracking_count || 0).toLocaleString());
    }

    function renderTablePage() {
        const totalRecords = allReportRows.length;
        $('#recordCount').text(totalRecords);

        if (totalRecords === 0) {
            $('#tableBody').html(`
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        <i class="ri-inbox-line fs-36 d-block mb-2 text-secondary"></i>
                        No Claim ID 7 vehicle claims found matching your filter criteria.
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
        rows.forEach(function(row, idx) {
            const index = startIndex + idx;
            let rowClass = '';
            let statusBadge = '';

            if (row.status === 'high') {
                rowClass = 'row-high-diff';
                statusBadge = `<span class="badge bg-danger text-white"><i class="ri-alert-line me-1"></i> High Diff</span>`;
            } else if (row.status === 'moderate') {
                rowClass = 'row-moderate-diff';
                statusBadge = `<span class="badge bg-warning text-dark"><i class="ri-error-warning-line me-1"></i> Moderate</span>`;
            } else if (row.status === 'match') {
                statusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ri-check-line me-1"></i> Matched</span>`;
            } else if (row.status === 'zero_km') {
                statusBadge = `<span class="badge bg-light text-muted border"><i class="ri-indeterminate-circle-line me-1"></i> Zero KM</span>`;
            } else {
                rowClass = 'row-no-tracking';
                statusBadge = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="ri-signal-wifi-off-line me-1"></i> No Tracking</span>`;
            }

            // Difference text & color
            let diffHtml = '';
            if (row.status === 'zero_km') {
                diffHtml = `<span class="text-muted fw-semibold">0.00 KM</span>`;
            } else if (row.status === 'no_tracking') {
                const diffVal = Number(row.difference_km || row.claimed_km || 0);
                diffHtml = `<span class="text-secondary fw-semibold">+${diffVal.toFixed(2)} KM</span><br><small class="text-muted">(No GPS)</small>`;
            } else if (row.difference_km !== null) {
                const diffVal = Number(row.difference_km);
                const sign = diffVal > 0 ? '+' : '';
                const absVal = Math.abs(diffVal);
                const diffColor = absVal > 15 ? 'text-danger fw-bold' : (absVal > 5 ? 'text-warning fw-bold' : 'text-success fw-bold');
                const pctText = row.diff_percentage !== null ? `<br><small class="text-muted">(${row.diff_percentage}%)</small>` : '';
                diffHtml = `<span class="${diffColor}">${sign}${diffVal.toFixed(2)} KM</span>${pctText}`;
            } else {
                diffHtml = `<span class="text-muted">N/A</span>`;
            }

            // Tracking KM text
            let trackHtml = '';
            if (row.tracking_km !== null) {
                trackHtml = `
                    <div class="fw-semibold text-dark">${Number(row.tracking_km).toFixed(2)} KM</div>
                    <button type="button" class="btn btn-link btn-sm p-0 text-primary font-size-11 btn-view-sessions"
                            data-empid="${row.emp_id}" data-date="${row.bill_date}" data-empname="${escapeHtml(row.emp_name)}" data-dist="${row.tracking_km}">
                        <i class="ri-route-line"></i> ${row.sessions_count} session(s)
                    </button>
                `;
            } else {
                trackHtml = `<span class="badge bg-light text-muted border">0.00 KM</span>`;
            }

            // Opening photo thumb
            let openImgHtml = '';
            if (row.opening_image_url) {
                openImgHtml = `
                    <div class="d-flex align-items-center gap-1 justify-content-center">
                        <img src="${row.opening_image_url}" class="odo-thumb btn-inspect-photo"
                             data-row='${JSON.stringify(row).replace(/'/g, "&apos;")}'
                             alt="Opening Photo"
                             onerror="if(!this.dataset.tried){this.dataset.tried=1;this.src='https://vnragro.com/File_Bkp/xeasyfile/${row.year_id || 8}/${row.emp_id}/${encodeURIComponent(row.opening_image)}';}else{this.src='https://placehold.co/80x80?text=No+Photo';}">
                    </div>
                `;
            } else {
                openImgHtml = `<span class="no-img-badge"><i class="ri-image-line"></i> No Photo</span>`;
            }

            // Closing photo thumb
            let closeImgHtml = '';
            if (row.closing_image_url) {
                closeImgHtml = `
                    <div class="d-flex align-items-center gap-1 justify-content-center">
                        <img src="${row.closing_image_url}" class="odo-thumb btn-inspect-photo"
                             data-row='${JSON.stringify(row).replace(/'/g, "&apos;")}'
                             alt="Closing Photo"
                             onerror="if(!this.dataset.tried){this.dataset.tried=1;this.src='https://vnragro.com/File_Bkp/xeasyfile/${row.year_id || 8}/${row.emp_id}/${encodeURIComponent(row.closing_image)}';}else{this.src='https://placehold.co/80x80?text=No+Photo';}">
                    </div>
                `;
            } else {
                closeImgHtml = `<span class="no-img-badge"><i class="ri-image-line"></i> No Photo</span>`;
            }

            // Vehicle badge
            const vBadgeClass = row.vehicle_type === '4W' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info';

            html += `
                <tr class="${rowClass}">
                    <td class="text-center fw-semibold text-muted">${index + 1}</td>
                    <td>
                        <div class="fw-bold text-dark font-size-13">${escapeHtml(row.emp_name)}</div>
                        <div class="text-muted font-size-11">
                            <span class="badge bg-light text-dark border me-1">${escapeHtml(row.emp_code)}</span>
                            ${escapeHtml(row.department)}
                        </div>
                        <div class="text-muted font-size-10">
                            <i class="ri-user-star-line"></i> ${escapeHtml(row.designation)}
                            ${row.headquarter ? ' | <i class="ri-map-pin-line"></i> ' + escapeHtml(row.headquarter) : ''}
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="fw-medium">${row.bill_date_formatted}</div>
                    </td>
                    <td class="text-center">
                        <div class="fw-semibold">#${row.exp_id}</div>
                        <span class="badge bg-light text-secondary border font-size-10">${row.claim_status}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge ${vBadgeClass}">${row.vehicle_type}</span>
                    </td>
                    <td class="text-center">
                        <div class="fw-bold text-dark mb-1">${Number(row.opening_km).toLocaleString()} KM</div>
                        ${openImgHtml}
                    </td>
                    <td class="text-center">
                        <div class="fw-bold text-dark mb-1">${Number(row.closing_km).toLocaleString()} KM</div>
                        ${closeImgHtml}
                    </td>
                    <td class="text-center">
                        <div class="fw-bold text-primary font-size-13">${Number(row.claimed_km).toFixed(2)} KM</div>
                        <small class="text-muted">₹${Number(row.amount).toLocaleString()}</small>
                    </td>
                    <td class="text-center">
                        ${trackHtml}
                    </td>
                    <td class="text-center">
                        ${diffHtml}
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary p-1 btn-inspect-photo"
                                data-row='${JSON.stringify(row).replace(/'/g, "&apos;")}' title="Inspect Photos & Comparison">
                            <i class="ri-eye-line font-size-14"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
    }

    function showError(msg) {
        $('#tableBody').html(`
            <tr>
                <td colspan="11" class="text-center py-4 text-danger">
                    <i class="ri-error-warning-line fs-24 d-block mb-1"></i>
                    ${escapeHtml(msg)}
                </td>
            </tr>
        `);
    }

    // Modal Inspection Click Handler
    $(document).on('click', '.btn-inspect-photo', function() {
        const rowData = $(this).data('row');
        if (!rowData) return;

        $('#modalEmpDetails').text(`${rowData.emp_name} (${rowData.emp_code})`);
        $('#modalTravelDate').text(`Travel Date: ${rowData.bill_date_formatted}`);
        $('#modalClaimedKm').text(`Claimed: ${Number(rowData.claimed_km).toFixed(2)} KM`);

        if (rowData.tracking_km !== null) {
            $('#modalTrackedKm').text(`Tracked: ${Number(rowData.tracking_km).toFixed(2)} KM`).show();
        } else {
            $('#modalTrackedKm').text(`No Tracking Data`).show();
        }

        $('#modalOpenReading').text(`${Number(rowData.opening_km).toLocaleString()} KM`);
        $('#modalCloseReading').text(`${Number(rowData.closing_km).toLocaleString()} KM`);

        if (rowData.opening_image_url) {
            $('#modalOpenImg').attr('src', rowData.opening_image_url).show();
            $('#modalOpenNoImg').hide();
            $('#modalOpenFullBtn').attr('href', rowData.opening_image_url).show();
        } else {
            $('#modalOpenImg').hide();
            $('#modalOpenNoImg').show();
            $('#modalOpenFullBtn').hide();
        }

        if (rowData.closing_image_url) {
            $('#modalCloseImg').attr('src', rowData.closing_image_url).show();
            $('#modalCloseNoImg').hide();
            $('#modalCloseFullBtn').attr('href', rowData.closing_image_url).show();
        } else {
            $('#modalCloseImg').hide();
            $('#modalCloseNoImg').show();
            $('#modalCloseFullBtn').hide();
        }

        $('#imagePreviewModal').modal('show');
    });

    // Modal Tracking Sessions Click Handler
    $(document).on('click', '.btn-view-sessions', function() {
        const empId = $(this).data('empid');
        const date = $(this).data('date');
        const empName = $(this).data('empname');
        const totalDist = $(this).data('dist');

        $('#trackModalEmp').text(empName);
        $('#trackModalDate').text(date);
        $('#trackModalTotalDist').text(Number(totalDist).toFixed(2) + ' KM');
        $('#trackSessionsTableBody').html('<tr><td colspan="8" class="text-center py-3">Loading sessions...</td></tr>');
        $('#stopsContainer').hide();
        $('#trackingDetailsModal').modal('show');

        const url = "{{ url('admin/odometer-tracking-report/session-details') }}/" + empId + "/" + date;

        $.ajax({
            url: url,
            type: "GET",
            success: function(res) {
                if (res.success && res.sessions && res.sessions.length > 0) {
                    let sHtml = '';
                    res.sessions.forEach(function(s, idx) {
                        const start = s.started_at ? s.started_at.substring(11, 19) : 'N/A';
                        const end = s.ended_at ? s.ended_at.substring(11, 19) : 'N/A';
                        const movingMins = Math.round((s.moving_seconds || 0) / 60);
                        const stopMins = Math.round((s.stopped_seconds || 0) / 60);

                        sHtml += `
                            <tr>
                                <td class="text-center fw-semibold">#${idx + 1}</td>
                                <td>${start}</td>
                                <td>${end}</td>
                                <td class="fw-bold text-primary">${Number(s.total_distance_km || 0).toFixed(2)} KM</td>
                                <td>${movingMins} mins</td>
                                <td>${stopMins} mins</td>
                                <td><span class="badge bg-light text-dark border">${s.status || 'N/A'}</span></td>
                                <td>${s.start_battery || 0}% → ${s.end_battery || 0}%</td>
                            </tr>
                        `;
                    });
                    $('#trackSessionsTableBody').html(sHtml);

                    // Render stops if present
                    if (res.stops && res.stops.length > 0) {
                        let stpHtml = '';
                        res.stops.forEach(function(stp, idx) {
                            const arr = stp.arrival_at ? stp.arrival_at.substring(11, 19) : 'N/A';
                            const dep = stp.departure_at ? stp.departure_at.substring(11, 19) : 'N/A';
                            const dur = Math.round((stp.duration_seconds || 0) / 60);
                            const addr = stp.address || `${stp.latitude}, ${stp.longitude}`;

                            stpHtml += `
                                <tr>
                                    <td class="text-center">${idx + 1}</td>
                                    <td>${arr}</td>
                                    <td>${dep}</td>
                                    <td>${dur} mins</td>
                                    <td>${escapeHtml(addr)}</td>
                                </tr>
                            `;
                        });
                        $('#trackStopsTableBody').html(stpHtml);
                        $('#stopsContainer').show();
                    }
                } else {
                    $('#trackSessionsTableBody').html('<tr><td colspan="8" class="text-center py-3 text-muted">No individual session records found for this date.</td></tr>');
                }
            },
            error: function(xhr) {
                $('#trackSessionsTableBody').html('<tr><td colspan="8" class="text-center py-3 text-danger">Failed to load tracking session details.</td></tr>');
            }
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
</script>
@include('partials.organogram-filters-js')
@endpush
