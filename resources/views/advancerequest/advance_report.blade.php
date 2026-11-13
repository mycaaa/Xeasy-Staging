@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $sessiondata = session()->all();
    $yearId = session("year_id", session("FYearId", 0));
    $company_id = session("company_id", session("CompanyId", 0));
    $employeeId = $user->employee_id ?? session('EmployeeID', session('employee_id', ''));
    $apiBaseUrl = url('api');
@endphp

<div class="page-content">
    <div class="container-fluid">
        <!-- Authentication Data (Hidden) -->
        <div id="authData" 
            data-employee-id="{{ $employeeId }}"
            data-employee-name="{{ $user->name ?? '' }}"
            data-employee-code="{{ $user->employee_code ?? '' }}"
            data-department="{{ $user->department ?? '' }}"
            data-company-id="{{ $company_id }}"
            data-fyear-id="{{ $yearId }}"
            data-user-id="{{ $user->id ?? 0 }}"
            data-api-base-url="{{ $apiBaseUrl }}"
            data-csrf-token="{{ csrf_token() }}"
            style="display: none;">
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0 mb-3">
                    <!-- Page Header -->
                    <div class="card-header bg-white border-bottom py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="history.back()" title="Back">
                                    <i class="ri-arrow-left-line align-middle me-1"></i> Back
                                </button>
                                <h5 class="card-title mb-0 d-flex align-items-center gap-2" style="font-size: 15px; font-weight: 600; color: #155997;">
                                    <i class="ri-file-chart-line align-middle fs-18"></i>
                                    Advance Report
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="userLevelBadge" style="font-size: 11px;">
                                        Loading level...
                                    </span>
                                </h5>
                            </div>
                            
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ url('/advance-requests') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 12px; padding: 3px 10px;">
                                    <i class="ri-list-check-2 align-middle me-1"></i> My Advances
                                </a>
                                <a href="{{ url('/advance-requests/advanceApprovals') }}" class="btn btn-outline-primary btn-sm" style="font-size: 12px; padding: 3px 10px;">
                                    <i class="ri-checkbox-circle-line align-middle me-1"></i> Approvals
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" id="btnHeaderRefresh" style="font-size: 12px; padding: 3px 10px;" title="Refresh Data">
                                    <i class="ri-refresh-line align-middle me-1"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-3">
                        <div id="Message" class="alert alert-danger" style="display: none;"></div>

                        <!-- Filter Section -->
                        <div class="filter-section p-3 rounded mb-3" style="background-color: #f8f9fa; border: 1px solid #e9ecef;">
                            <div class="row g-2 mb-2">
                                <div class="col-md-3" id="buFilterCol">
                                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 600;">Business Unit</label>
                                    <select id="buFilter" class="form-select form-select-sm">
                                        <option value="">All Business Units</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="zoneFilterCol">
                                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 600;">Zone</label>
                                    <select id="zoneFilter" class="form-select form-select-sm">
                                        <option value="">All Zones</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="regionFilterCol">
                                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 600;">Region</label>
                                    <select id="regionFilter" class="form-select form-select-sm">
                                        <option value="">All Regions</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="territoryFilterCol">
                                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 600;">Territory</label>
                                    <select id="territoryFilter" class="form-select form-select-sm">
                                        <option value="">All Territories</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 600;">Employee</label>
                                    <select id="employeeFilter" class="form-select form-select-sm">
                                        <option value="">All Employees</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 600;">Month</label>
                                    <select id="monthFilter" class="form-select form-select-sm">
                                        <option value="">All Months</option>
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                                <div class="col-md-5 d-flex gap-2">
                                    <button id="applyFilters" class="btn btn-primary btn-sm flex-fill" style="background-color: #155997; border-color: #155997;">
                                        <i class="ri-filter-3-line align-middle me-1"></i> Apply Filters
                                    </button>
                                    <button id="resetFilters" class="btn btn-outline-secondary btn-sm" title="Reset all filters">
                                        <i class="ri-refresh-line align-middle me-1"></i> Reset
                                    </button>
                                    <button id="btnExportExcel" class="btn btn-success btn-sm" title="Export Table to Excel">
                                        <i class="ri-file-excel-2-line align-middle me-1"></i> Excel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row g-3 mb-3" id="statsCards" style="display: none;">
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h6>Total Paid Amount</h6>
                                    <div class="value" id="grandTotal">₹0</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card" style="background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);">
                                    <h6>Total Employees</h6>
                                    <div class="value" id="totalEmployees">0</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card" style="background: linear-gradient(135deg, #ff9800 0%, #e65100 100%);">
                                    <h6>Total Requests</h6>
                                    <div class="value" id="totalRequests">0</div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity View Matrix Table -->
                        <div class="table-responsive">
                            <table id="activityTable" class="table table-bordered table-hover align-middle mb-0" style="width:100%">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="text-center" style="vertical-align: middle; width: 45px;">Sr.No</th>
                                        <th rowspan="2" style="vertical-align: middle; min-width: 180px;">Advance Type</th>
                                        <th colspan="12" class="text-center py-1">
                                            Months <small class="text-white-50 fw-normal">(Click any amount to view detailed breakdown)</small>
                                        </th>
                                        <th rowspan="2" class="text-center" style="vertical-align: middle; min-width: 90px;">Total</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">Jan</th>
                                        <th class="text-center">Feb</th>
                                        <th class="text-center">Mar</th>
                                        <th class="text-center">Apr</th>
                                        <th class="text-center">May</th>
                                        <th class="text-center">Jun</th>
                                        <th class="text-center">Jul</th>
                                        <th class="text-center">Aug</th>
                                        <th class="text-center">Sep</th>
                                        <th class="text-center">Oct</th>
                                        <th class="text-center">Nov</th>
                                        <th class="text-center">Dec</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="15" class="text-center text-muted py-4">
                                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                            Loading report data...
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr style="font-weight: 700; background-color: #155997; color: white;">
                                        <td colspan="2" class="text-end" style="background-color: #155997; color: white;">Grand Total:</td>
                                        <td class="text-center" id="totalJan" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalFeb" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalMar" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalApr" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalMay" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalJun" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalJul" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalAug" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalSep" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalOct" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalNov" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="totalDec" style="background-color: #155997; color: white;">₹0</td>
                                        <td class="text-center" id="grandTotalFooter" style="background-color: #0d3d66; color: #fff; font-weight: 800;">₹0</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css') }}">
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('vendor/cdn/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css') }}" rel="stylesheet" />
<style>
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.45);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .stats-card {
        background: linear-gradient(135deg, #155997 0%, #0d3d66 100%);
        color: white;
        padding: 14px 16px;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    .stats-card h6 {
        font-size: 0.72rem;
        opacity: 0.9;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stats-card .value {
        font-size: 1.4rem;
        font-weight: 700;
    }

    #activityTable {
        font-size: 0.75rem;
    }

    #activityTable thead th {
        background-color: #155997;
        color: #ffffff;
        font-weight: 600;
        font-size: 0.72rem;
        padding: 7px 6px;
        border: 1px solid #0d3d66;
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
    }

    #activityTable tbody td {
        padding: 6px 8px;
        border: 1px solid #e9ecef;
        font-size: 0.75rem;
        vertical-align: middle;
    }

    #activityTable tfoot td {
        border: 1px solid #0d3d66;
        padding: 8px 6px;
        font-weight: 700;
        font-size: 0.75rem;
    }

    .month-cell {
        cursor: pointer;
        transition: background-color 0.15s, transform 0.15s;
        text-align: center;
        min-width: 65px;
        white-space: nowrap;
    }

    .month-cell.has-data {
        background-color: #f0f7ff;
        font-weight: 600;
        color: #155997;
    }

    .month-cell.has-data:hover {
        background-color: #dbeafe !important;
        color: #0b4074 !important;
        text-decoration: underline;
    }

    .month-cell.no-data {
        color: #adb5bd;
        text-align: center;
    }

    .activity-name-cell {
        font-weight: 600;
        color: #155997;
        min-width: 170px;
    }

    .total-cell {
        font-weight: 700;
        color: #155997;
        background-color: #f8fafc;
        text-align: center;
        min-width: 80px;
    }

    .status-badge {
        font-size: 0.65rem;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
        display: inline-block;
    }

    .status-submitted, .status-pending, .status-in_approval {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-approved, .status-finance_approved {
        background-color: #dcfce7;
        color: #166534;
    }

    .status-rejected {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-finance {
        background-color: #e0f2fe;
        color: #075985;
    }

    .status-payment, .status-paid {
        background-color: #f3e8ff;
        color: #6b21a8;
    }

    .amount-display {
        font-weight: 600;
        color: #155997;
    }

    .crop-tag, .variety-tag {
        display: inline-block;
        background-color: #e0f2fe;
        color: #0369a1;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 0.65rem;
        margin: 1px;
        white-space: nowrap;
    }

    .variety-tag {
        background-color: #f3e8ff;
        color: #7e22ce;
    }

    .detail-label {
        font-weight: 600;
        color: #64748b;
        font-size: 0.7rem;
    }

    .select2-container--bootstrap-5 .select2-selection {
        font-size: 0.8rem;
        min-height: calc(1.5em + 0.5rem + 2px);
    }

    .select2-container--bootstrap-5 .select2-selection--single {
        padding: 0.25rem 0.5rem;
    }

    .select2-container {
        width: 100% !important;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/xlsx@0.18.5/dist/xlsx.full.min.js') }}"></script>
<script>
    // ==========================================
    // GLOBAL STATE & VARIABLES
    // ==========================================
    const authData = document.getElementById('authData');
    const EmployeeID = authData ? authData.dataset.employeeId : '';
    const yearId = authData ? parseInt(authData.dataset.fyearId) || 0 : 0;
    const comId = authData ? parseInt(authData.dataset.companyId) || 0 : 0;
    
    let consolidatedData = {};
    let activityMonthMatrix = {}; // { activityId: { activity_name, months: { 0..11: {month_name, amount, data} }, total } }
    let employeeLevel = null; // bu, zone, region, territory, all, none
    let employeeHierarchy = null;

    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // ==========================================
    // UI HELPERS
    // ==========================================
    function showLoading() {
        document.getElementById("loadingOverlay").style.display = "flex";
    }

    function hideLoading() {
        document.getElementById("loadingOverlay").style.display = "none";
    }

    function showMessage(message, type = 'danger') {
        const messageDiv = $("#Message");
        messageDiv.removeClass('alert-success alert-danger alert-warning alert-info')
            .addClass(`alert-${type}`)
            .html(`<i class="ri-alert-line me-1"></i> ${message}`)
            .show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideMessage() {
        $("#Message").hide();
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }

    function formatAmount(amount) {
        const num = parseFloat(amount) || 0;
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(num);
    }

    function getStatusBadgeClass(status) {
        if (!status) return 'status-pending';
        const statusLower = status.toLowerCase().replace(/\s+/g, '_');
        return `status-${statusLower}`;
    }

    function getApprovalBorderColor(status) {
        if (!status) return '#6c757d';
        switch (status.toLowerCase()) {
            case 'approved':
                return '#28a745';
            case 'pending':
                return '#ffc107';
            case 'rejected':
                return '#dc3545';
            default:
                return '#6c757d';
        }
    }

    // ==========================================
    // INITIALIZATION ON READY
    // ==========================================
    $(document).ready(function() {
        console.log('Advance Report initialized');
        console.log('EmployeeID:', EmployeeID, 'YearID:', yearId, 'CompanyID:', comId);

        $('#btnHeaderRefresh').on('click', function() {
            fetchActivityReport();
        });

        checkEmployeeLevel();
    });

    // ==========================================
    // CHECK EMPLOYEE LEVEL & ACCESS
    // ==========================================
    function checkEmployeeLevel() {
        showLoading();
        $.ajax({
            url: '/api/advance/hod/employee-level',
            method: 'GET',
            data: {
                employee_id: EmployeeID
            },
            success: function(response) {
                if (response.success && response.data) {
                    employeeLevel = (response.data.access_level || '').toLowerCase();
                    employeeHierarchy = response.data;

                    const badgeText = response.data.level_description || `Level: ${employeeLevel.toUpperCase()}`;
                    $('#userLevelBadge').text(badgeText);

                    // Initialize filters based on access level
                    initializeFiltersBasedOnLevel(employeeLevel);

                    // Fetch initial report
                    fetchActivityReport();
                } else {
                    hideLoading();
                    $('#userLevelBadge').text('Access Level: Default');
                    initializeFiltersBasedOnLevel('all');
                    fetchActivityReport();
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Error checking employee level:', error);
                $('#userLevelBadge').text('Access Level: Default');
                initializeFiltersBasedOnLevel('all');
                fetchActivityReport();
            }
        });
    }

    // ==========================================
    // INITIALIZE FILTERS BASED ON ACCESS LEVEL
    // ==========================================
    function initializeFiltersBasedOnLevel(level) {
        console.log('Initializing filters for level:', level);

        // Hide all hierarchy filters initially
        $('#buFilterCol').hide();
        $('#zoneFilterCol').hide();
        $('#regionFilterCol').hide();
        $('#territoryFilterCol').hide();

        // Month filter
        $('#monthFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Months',
            allowClear: true,
            minimumResultsForSearch: -1
        });

        // Employee filter (AJAX team members)
        $('#employeeFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Employees',
            allowClear: true,
            ajax: {
                url: '/api/advance/hod/team',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        employee_id: EmployeeID,
                        search: params.term || '',
                        limit: 20
                    };
                },
                processResults: function(response) {
                    if (response.success && response.data && response.data.team_members) {
                        return {
                            results: response.data.team_members.map(emp => ({
                                id: emp.EmployeeID,
                                text: `${emp.EmployeeName} (${emp.EmployeeCode || ''})`
                            }))
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 0
        });

        // Hierarchy filters according to level
        switch (level) {
            case 'territory':
                $('#territoryFilterCol').show();
                initializeTerritoryDropdown(null, null, null);
                break;

            case 'region':
                $('#regionFilterCol').show();
                $('#territoryFilterCol').show();
                initializeRegionDropdown(null, null);
                initializeTerritoryDropdown(null, null, null);
                setupCascadingForRegionLevel();
                break;

            case 'zone':
                $('#zoneFilterCol').show();
                $('#regionFilterCol').show();
                $('#territoryFilterCol').show();
                initializeZoneDropdown(null);
                initializeRegionDropdown(null, null);
                initializeTerritoryDropdown(null, null, null);
                setupCascadingForZoneLevel();
                break;

            case 'bu':
            case 'all':
            default:
                $('#buFilterCol').show();
                $('#zoneFilterCol').show();
                $('#regionFilterCol').show();
                $('#territoryFilterCol').show();
                initializeBusinessUnitDropdown();
                initializeZoneDropdown(null);
                initializeRegionDropdown(null, null);
                initializeTerritoryDropdown(null, null, null);
                setupCascadingForAllLevels();
                break;
        }

        setupFilterButtons();
    }

    // ==========================================
    // CASCADING SETUP
    // ==========================================
    function setupCascadingForRegionLevel() {
        $('#regionFilter').off('change').on('change', function() {
            const selectedRegion = $(this).val();
            $('#territoryFilter').val(null).trigger('change');
            initializeTerritoryDropdown(null, null, selectedRegion || null);
        });
    }

    function setupCascadingForZoneLevel() {
        $('#zoneFilter').off('change').on('change', function() {
            const selectedZone = $(this).val();
            $('#regionFilter').val(null).trigger('change');
            $('#territoryFilter').val(null).trigger('change');
            initializeRegionDropdown(null, selectedZone || null);
            initializeTerritoryDropdown(null, selectedZone || null, null);
        });

        $('#regionFilter').off('change').on('change', function() {
            const selectedRegion = $(this).val();
            const selectedZone = $('#zoneFilter').val();
            $('#territoryFilter').val(null).trigger('change');
            initializeTerritoryDropdown(null, selectedZone || null, selectedRegion || null);
        });
    }

    function setupCascadingForAllLevels() {
        $('#buFilter').off('change').on('change', function() {
            const selectedBU = $(this).val();
            $('#zoneFilter').val(null).trigger('change');
            $('#regionFilter').val(null).trigger('change');
            $('#territoryFilter').val(null).trigger('change');
            initializeZoneDropdown(selectedBU || null);
            initializeRegionDropdown(selectedBU || null, null);
            initializeTerritoryDropdown(selectedBU || null, null, null);
        });

        $('#zoneFilter').off('change').on('change', function() {
            const selectedZone = $(this).val();
            const selectedBU = $('#buFilter').val();
            $('#regionFilter').val(null).trigger('change');
            $('#territoryFilter').val(null).trigger('change');
            initializeRegionDropdown(selectedBU || null, selectedZone || null);
            initializeTerritoryDropdown(selectedBU || null, selectedZone || null, null);
        });

        $('#regionFilter').off('change').on('change', function() {
            const selectedRegion = $(this).val();
            const selectedBU = $('#buFilter').val();
            const selectedZone = $('#zoneFilter').val();
            $('#territoryFilter').val(null).trigger('change');
            initializeTerritoryDropdown(selectedBU || null, selectedZone || null, selectedRegion || null);
        });
    }

    // ==========================================
    // DROPDOWN INITIALIZERS
    // ==========================================
    function initializeBusinessUnitDropdown() {
        if ($('#buFilter').hasClass('select2-hidden-accessible')) {
            $('#buFilter').select2('destroy');
        }

        $('#buFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Business Units',
            allowClear: true,
            ajax: {
                url: '/api/advance/hod/business-units',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        employee_id: EmployeeID,
                        search: params.term || '',
                        limit: 15
                    };
                },
                processResults: function(response) {
                    if (response.success && response.data) {
                        return {
                            results: response.data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 0
        });
    }

    function initializeZoneDropdown(businessUnitId) {
        if ($('#zoneFilter').hasClass('select2-hidden-accessible')) {
            $('#zoneFilter').select2('destroy');
        }

        $('#zoneFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Zones',
            allowClear: true,
            ajax: {
                url: '/api/advance/hod/zones',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    const req = {
                        employee_id: EmployeeID,
                        search: params.term || '',
                        limit: 20
                    };
                    if (businessUnitId) req.business_unit_id = businessUnitId;
                    return req;
                },
                processResults: function(response) {
                    if (response.success && response.data) {
                        return {
                            results: response.data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 0
        });
    }

    function initializeRegionDropdown(businessUnitId, zoneId) {
        if ($('#regionFilter').hasClass('select2-hidden-accessible')) {
            $('#regionFilter').select2('destroy');
        }

        $('#regionFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Regions',
            allowClear: true,
            ajax: {
                url: '/api/advance/hod/regions',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    const req = {
                        employee_id: EmployeeID,
                        search: params.term || '',
                        limit: 20
                    };
                    if (zoneId) req.zone_id = zoneId;
                    else if (businessUnitId) req.business_unit_id = businessUnitId;
                    return req;
                },
                processResults: function(response) {
                    if (response.success && response.data) {
                        return {
                            results: response.data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 0
        });
    }

    function initializeTerritoryDropdown(businessUnitId = null, zoneId = null, regionId = null) {
        if ($('#territoryFilter').hasClass('select2-hidden-accessible')) {
            $('#territoryFilter').select2('destroy');
        }

        $('#territoryFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Territories',
            allowClear: true,
            ajax: {
                url: '/api/advance/hod/territories',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    let req = {
                        employee_id: EmployeeID,
                        search: params.term || '',
                        limit: 20
                    };
                    if (regionId) req.region_id = regionId;
                    if (zoneId && !regionId) req.zone_id = zoneId;
                    if (businessUnitId && !zoneId && !regionId) req.business_unit_id = businessUnitId;
                    return req;
                },
                processResults: function(response) {
                    if (response.success && response.data) {
                        return {
                            results: response.data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 0
        });
    }

    // ==========================================
    // FILTER BUTTONS
    // ==========================================
    function setupFilterButtons() {
        $('#applyFilters').off('click').on('click', function() {
            hideMessage();
            fetchActivityReport();
        });

        $('#resetFilters').off('click').on('click', function() {
            $('#employeeFilter').val(null).trigger('change');
            $('#monthFilter').val(null).trigger('change');
            $('#buFilter').val(null).trigger('change');
            $('#zoneFilter').val(null).trigger('change');
            $('#regionFilter').val(null).trigger('change');
            $('#territoryFilter').val(null).trigger('change');

            initializeFiltersBasedOnLevel(employeeLevel);
            hideMessage();
            fetchActivityReport();
        });

        $('#btnExportExcel').off('click').on('click', function() {
            exportToExcel();
        });
    }

    // ==========================================
    // FETCH CONSOLIDATED REPORT
    // ==========================================
    function fetchActivityReport() {
        showLoading();

        const params = {
            employee_id: EmployeeID,
            year_id: yearId
        };

        const selectedMonth = $('#monthFilter').val();
        const selectedEmployee = $('#employeeFilter').val();
        const selectedBU = $('#buFilter').val();
        const selectedZone = $('#zoneFilter').val();
        const selectedRegion = $('#regionFilter').val();
        const selectedTerritory = $('#territoryFilter').val();

        if (selectedMonth) params.month = selectedMonth;
        if (selectedEmployee) params.specific_employee_id = selectedEmployee;
        if (selectedBU) params.bu_id = selectedBU;
        if (selectedZone) params.zone_id = selectedZone;
        if (selectedRegion) params.region_id = selectedRegion;
        if (selectedTerritory) params.terr_id = selectedTerritory;

        $.ajax({
            url: '/api/advance/hod/consolidated-report',
            method: 'GET',
            data: params,
            success: function(response) {
                try {
                    hideLoading();

                    let actualData = null;
                    let grandTotal = 0;

                    if (response.success) {
                        if (response.data && typeof response.data === 'object' && 'grand_total' in response.data && 'data' in response.data) {
                            actualData = response.data.data;
                            grandTotal = response.data.grand_total || 0;
                        } else if (response.data && typeof response.data === 'object') {
                            actualData = response.data;
                            grandTotal = response.grand_total || 0;
                        }

                        if (actualData && Object.keys(actualData).length > 0) {
                            consolidatedData = actualData;
                            updateStats(grandTotal);
                            processConsolidatedData(actualData);
                            renderActivityTable();
                            hideMessage();
                        } else {
                            consolidatedData = {};
                            activityMonthMatrix = {};
                            updateStats(0);
                            renderActivityTable();
                        }
                    } else {
                        consolidatedData = {};
                        activityMonthMatrix = {};
                        updateStats(0);
                        renderActivityTable();
                    }
                } catch (error) {
                    console.error('Error processing consolidated report:', error);
                    showMessage('Error processing report data: ' + error.message, 'danger');
                    hideLoading();
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Error fetching consolidated report:', error);
                showMessage('Error fetching report: ' + (xhr.responseJSON?.message || error), 'danger');
                consolidatedData = {};
                activityMonthMatrix = {};
                updateStats(0);
                renderActivityTable();
            }
        });
    }

    // ==========================================
    // PROCESS DATA INTO MATRIX
    // ==========================================
    function processConsolidatedData(data) {
        activityMonthMatrix = {};

        Object.keys(data).forEach(activityId => {
            const activity = data[activityId];
            const activityName = activity.activity_name || 'Expense';

            if (!activityMonthMatrix[activityId]) {
                activityMonthMatrix[activityId] = {
                    activity_name: activityName,
                    months: {},
                    total: 0
                };
            }

            const monthKeys = Object.keys(activity.months || {});
            monthKeys.forEach(monthName => {
                const monthData = activity.months[monthName];
                const monthIndex = monthNames.indexOf(monthName);

                if (monthIndex !== -1) {
                    activityMonthMatrix[activityId].months[monthIndex] = {
                        month_name: monthName,
                        amount: monthData.month_total || 0,
                        data: monthData
                    };
                    activityMonthMatrix[activityId].total += (monthData.month_total || 0);
                }
            });
        });
    }

    // ==========================================
    // RENDER ACTIVITY TABLE
    // ==========================================
    function renderActivityTable() {
        const tbody = $('#activityTable tbody');
        tbody.empty();

        const monthTotals = Array(12).fill(0);
        let grandTotal = 0;
        let srNo = 1;

        const activityIds = Object.keys(activityMonthMatrix);

        if (activityIds.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="15" class="text-center text-muted py-4">
                        <i class="ri-information-line me-1 fs-16"></i> No paid advances found for the selected criteria.
                    </td>
                </tr>
            `);

            // Reset footer totals
            for (let i = 0; i < 12; i++) {
                $(`#total${monthNames[i]}`).text('₹0');
            }
            $('#grandTotalFooter').text('₹0');
            return;
        }

        activityIds.forEach(activityId => {
            const activity = activityMonthMatrix[activityId];
            const row = $('<tr></tr>');

            // Sr.No
            row.append(`<td class="text-center">${srNo++}</td>`);

            // Activity Name
            row.append(`<td class="activity-name-cell">${activity.activity_name}</td>`);

            // Month columns (Jan to Dec)
            for (let i = 0; i < 12; i++) {
                const monthData = activity.months[i];
                if (monthData && monthData.amount > 0) {
                    const formatted = formatAmount(monthData.amount);
                    row.append(`
                        <td class="month-cell has-data" 
                            data-activity-id="${activityId}" 
                            data-month-index="${i}"
                            data-month-name="${monthData.month_name}"
                            onclick="showMonthDetails('${activityId}', ${i}, '${monthData.month_name}')"
                            title="Click to view details for ${activity.activity_name} (${monthData.month_name})">
                            ${formatted}
                        </td>
                    `);
                    monthTotals[i] += monthData.amount;
                } else {
                    row.append(`<td class="month-cell no-data">-</td>`);
                }
            }

            // Total column
            row.append(`<td class="total-cell text-center">${formatAmount(activity.total)}</td>`);
            grandTotal += activity.total;

            tbody.append(row);
        });

        // Update footer totals
        for (let i = 0; i < 12; i++) {
            $(`#total${monthNames[i]}`).text(formatAmount(monthTotals[i]));
        }
        $('#grandTotalFooter').text(formatAmount(grandTotal));
    }

    // ==========================================
    // UPDATE STATS CARDS
    // ==========================================
    function updateStats(grandTotal) {
        $('#grandTotal').text(formatAmount(grandTotal));

        let totalEmployees = new Set();
        let totalRequests = 0;

        Object.keys(consolidatedData).forEach(activityId => {
            const activity = consolidatedData[activityId];
            Object.keys(activity.months || {}).forEach(monthName => {
                const monthData = activity.months[monthName];
                (monthData.submitted_by || []).forEach(submitter => {
                    (submitter.employees || []).forEach(employee => {
                        totalEmployees.add(employee.employee_id);
                        totalRequests += (employee.advance_requests || []).length;
                    });
                });
            });
        });

        $('#totalEmployees').text(totalEmployees.size);
        $('#totalRequests').text(totalRequests);

        if (grandTotal > 0 || totalRequests > 0) {
            $('#statsCards').slideDown();
        } else {
            $('#statsCards').hide();
        }
    }

    // ==========================================
    // SHOW MONTH DETAILS MODAL
    // ==========================================
    function showMonthDetails(activityId, monthIndex, monthName) {
        const activity = consolidatedData[activityId];
        if (!activity || !activity.months || !activity.months[monthName]) {
            showMessage('No details found for this month', 'warning');
            return;
        }

        const monthData = activity.months[monthName];
        const activityName = activity.activity_name;

        let modalHtml = `
            <div class="modal fade" id="monthDetailModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header py-2 px-3 text-white" style="background-color: #155997;">
                            <h5 class="modal-title fs-14 fw-semibold text-white d-flex align-items-center gap-2">
                                <i class="ri-calendar-event-line fs-16"></i> ${activityName} - ${monthName}
                                <span class="badge bg-light text-primary ms-2">${formatAmount(monthData.month_total)}</span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-3" style="font-size: 0.8rem; background-color: #f8fafc;">
        `;

        (monthData.submitted_by || []).forEach((submitter, submitterIndex) => {
            const submitterAccordionId = `submitter-${submitterIndex}`;

            modalHtml += `
                <div class="submitter-section mb-3 shadow-sm rounded overflow-hidden border">
                    <div class="submitter-header p-2 px-3 text-white" style="background: linear-gradient(135deg, #155997 0%, #0d3d66 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong style="font-size: 0.85rem;">
                                    <i class="ri-user-star-line me-1"></i> Raised By: ${submitter.submitted_by_name} 
                                    ${submitter.submitted_by_code ? `(${submitter.submitted_by_code})` : ''}
                                </strong>
                            </div>
                            <div>
                                <span class="badge bg-white text-dark fw-bold px-2 py-1">${formatAmount(submitter.submitted_by_total)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion accordion-flush" id="${submitterAccordionId}">
            `;

            (submitter.employees || []).forEach((employee, empIndex) => {
                const employeeId = `emp-${submitterIndex}-${empIndex}`;
                const collapseId = `collapse-${employeeId}`;
                const totalRequests = (employee.advance_requests || []).length;

                // Hierarchy badges
                let hierarchyBadges = '';
                if (employee.hierarchy) {
                    const h = employee.hierarchy;
                    if (h.business_unit && h.business_unit.name) hierarchyBadges += `<span class="badge bg-light text-secondary border me-1" style="font-size: 0.65rem;">${h.business_unit.name}</span>`;
                    if (h.zone && h.zone.name) hierarchyBadges += `<span class="badge bg-light text-secondary border me-1" style="font-size: 0.65rem;">${h.zone.name}</span>`;
                    if (h.region && h.region.name) hierarchyBadges += `<span class="badge bg-light text-secondary border me-1" style="font-size: 0.65rem;">${h.region.name}</span>`;
                    if (h.territory && h.territory.name) hierarchyBadges += `<span class="badge bg-light text-secondary border me-1" style="font-size: 0.65rem;">${h.territory.name}</span>`;
                }

                modalHtml += `
                    <div class="accordion-item border-bottom">
                        <h2 class="accordion-header" id="heading-${employeeId}">
                            <button class="accordion-button collapsed py-2 px-3" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}"
                                    style="font-size: 0.8rem; background-color: #fff;">
                                <div class="w-100 me-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <strong style="color: #155997;">${employee.employee_name} (${employee.employee_code})</strong>
                                            <div style="font-size: 0.7rem; color: #6c757d; margin-top: 1px;">
                                                ${employee.designation || 'N/A'} • ${employee.department || 'N/A'}
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-info-subtle text-info border border-info-subtle me-1">${totalRequests} Req</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold">${formatAmount(employee.employee_total)}</span>
                                            ${hierarchyBadges ? `<div class="mt-1">${hierarchyBadges}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="heading-${employeeId}" data-bs-parent="#${submitterAccordionId}">
                            <div class="accordion-body p-2 bg-white">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.72rem;">
                                        <thead class="table-light text-dark">
                                            <tr>
                                                <th style="width: 10%;">Req ID / Date</th>
                                                <th style="width: 18%;">Activity</th>
                                                <th style="width: 12%;">Amount</th>
                                                <th style="width: 15%;">Crops</th>
                                                <th style="width: 20%;">Approvals</th>
                                                <th style="width: 12%;">Finance</th>
                                                <th style="width: 13%;">Payment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                `;

                (employee.advance_requests || []).forEach((request) => {
                    (request.activities || []).forEach((activityDetail) => {
                        modalHtml += `
                            <tr>
                                <td>
                                    <strong class="text-primary">${request.request_id}</strong>
                                    <div style="font-size: 0.65rem; color: #6c757d;">
                                        ${formatDate(request.submitted_at)}
                                    </div>
                                </td>
                                <td>
                                    <strong>${activityDetail.activity_type}</strong>
                                </td>
                                <td>
                                    <span class="amount-display">${formatAmount(activityDetail.amount)}</span>
                                </td>
                                <td>
                                    ${activityDetail.crops && activityDetail.crops.length > 0 ? 
                                        activityDetail.crops.map(crop => `<span class="crop-tag">${crop}</span>`).join(' ') : 
                                        '<span class="text-muted">-</span>'}
                                </td>
                                <td>
                                    ${activityDetail.approvals && activityDetail.approvals.length > 0 ? `
                                        <div>
                                            ${activityDetail.approvals.map((approval, idx) => `
                                                <div style="padding: 2px 0; border-bottom: ${idx < activityDetail.approvals.length - 1 ? '1px dashed #e9ecef' : 'none'};">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong>${approval.approver_name}</strong>
                                                            <div>
                                                                <span class="status-badge ${getStatusBadgeClass(approval.approver_status)}">
                                                                    L${approval.approval_level}: ${approval.approver_status}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            ${approval.approver_amount ? `<div style="font-weight: 600;">${formatAmount(approval.approver_amount)}</div>` : ''}
                                                            ${approval.approver_date ? `<small class="text-muted">${formatDate(approval.approver_date)}</small>` : ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<span class="text-muted">-</span>'}
                                </td>
                                <td>
                                    ${activityDetail.finance && activityDetail.finance.status ? `
                                        <span class="status-badge ${getStatusBadgeClass(activityDetail.finance.status)}">
                                            ${activityDetail.finance.status}
                                        </span>
                                        ${activityDetail.finance.approved_amount ? `
                                            <div style="font-weight: 600; margin-top: 2px;">${formatAmount(activityDetail.finance.approved_amount)}</div>
                                        ` : ''}
                                        ${activityDetail.finance.date ? `
                                            <div class="text-muted" style="font-size: 0.65rem;">${formatDate(activityDetail.finance.date)}</div>
                                        ` : ''}
                                    ` : '<span class="text-muted">-</span>'}
                                </td>
                                <td>
                                    ${activityDetail.payment && activityDetail.payment.status ? `
                                        <span class="status-badge ${getStatusBadgeClass(activityDetail.payment.status)}">
                                            ${activityDetail.payment.status}
                                        </span>
                                        ${activityDetail.payment.amount ? `
                                            <div style="font-weight: 600; margin-top: 2px;">${formatAmount(activityDetail.payment.amount)}</div>
                                        ` : ''}
                                        ${activityDetail.payment.date ? `
                                            <div class="text-muted" style="font-size: 0.65rem;">${formatDate(activityDetail.payment.date)}</div>
                                        ` : ''}
                                    ` : '<span class="text-muted">-</span>'}
                                </td>
                            </tr>
                        `;
                    });
                });

                modalHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            modalHtml += `
                    </div>
                </div>
            `;
        });

        modalHtml += `
                        </div>
                        <div class="modal-footer py-2 px-3">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#monthDetailModal').remove();
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('monthDetailModal'));
        modal.show();
    }

    // ==========================================
    // EXPORT TABLE TO EXCEL
    // ==========================================
    function exportToExcel() {
        try {
            const table = document.getElementById("activityTable");
            if (!table) {
                showMessage('Table not found for export', 'warning');
                return;
            }

            const wb = XLSX.utils.table_to_book(table, { sheet: "Advance Report" });
            const now = new Date();
            const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '');
            XLSX.writeFile(wb, `Advance_Report_${dateStr}.xlsx`);
        } catch (error) {
            console.error('Export Excel error:', error);
            showMessage('Error exporting to Excel: ' + error.message, 'danger');
        }
    }
</script>
@endpush

