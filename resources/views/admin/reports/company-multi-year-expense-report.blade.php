@extends('layouts.app')
@section('title', 'Company Multi-Year Expense Report')

@push('styles')
<style>
    :root {
        --rpt-primary: #15648C;
        --rpt-primary-dark: #0e4663;
        --rpt-primary-light: #e8f4fa;
        --rpt-surface: #ffffff;
        --rpt-border: #e2e8f0;
    }

    /* KPI Summary Cards */
    .kpi-stat-card {
        border-radius: 12px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
        background: #fff;
    }
    .kpi-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .kpi-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 4px;
    }
    .kpi-stat-primary::before { background: linear-gradient(180deg, #15648C, #0e4663); }
    .kpi-stat-success::before { background: linear-gradient(180deg, #10b981, #059669); }
    .kpi-stat-info::before    { background: linear-gradient(180deg, #0284c7, #0369a1); }
    .kpi-stat-purple::before  { background: linear-gradient(180deg, #8b5cf6, #6d28d9); }

    .kpi-icon-bubble {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    /* Filter Card */
    .filter-card {
        border-radius: 12px;
        border: 1px solid var(--rpt-border);
        box-shadow: 0 4px 16px rgba(0,0,0,0.03);
        background: #ffffff;
    }

    /* Select2 Multi-Select Customization */
    .select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--multiple {
        min-height: 31px;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 1px 4px;
        background-color: #fff;
        font-size: 12px;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.2rem rgba(21, 100, 140, 0.15);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #e8f4fa;
        border: 1px solid #b8d9f0;
        color: #15648C;
        border-radius: 4px;
        padding: 1px 6px;
        font-size: 11px;
        font-weight: 500;
        margin: 2px 2px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #15648C;
        margin-right: 4px;
        font-weight: bold;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #dc3545;
    }
    .select2-container--default .select2-search--inline .select2-search__field {
        margin: 2px 0;
        padding: 0 4px;
        font-size: 12px;
        height: 24px;
    }
    .select2-dropdown {
        border-color: #cbd5e1;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        font-size: 12px;
        z-index: 1055;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--rpt-primary);
        color: #fff;
    }
    .select-action-link {
        font-size: 11px;
        cursor: pointer;
        text-decoration: none;
        user-select: none;
    }
    .select-action-link:hover {
        text-decoration: underline;
    }

    /* Preset Year Range Buttons */
    .btn-range-preset {
        font-weight: 600;
        font-size: 12px;
        padding: 5px 12px;
        border-radius: 6px;
        transition: all 0.15s ease;
    }
    .btn-range-preset.active {
        background-color: var(--rpt-primary) !important;
        border-color: var(--rpt-primary) !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(21, 100, 140, 0.35);
    }

    /* Data Table Card */
    .table-container-card {
        border-radius: 12px;
        border: 1px solid var(--rpt-border);
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        background: #fff;
    }
    .table-container-card .card-header {
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 1rem 1.25rem;
    }

    /* Styled Multi-Year Table */
    .multiyear-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        font-size: 12.5px;
    }
    .multiyear-table thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 12px 14px;
        border-bottom: 2px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 5;
        white-space: nowrap;
    }
    .multiyear-table thead th.fy-col-header {
        background: #eef7fc;
        color: #15648C;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
    }
    .multiyear-table thead th.total-col-header {
        background: #ecfdf5;
        color: #065f46;
    }
    .multiyear-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .multiyear-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .multiyear-table tfoot th {
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 700;
        padding: 12px 14px;
        border-top: 2px solid #cbd5e1;
        border-bottom: 2px solid #94a3b8;
    }

    /* Currency Numbers */
    .num-val {
        font-variant-numeric: tabular-nums;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }
    .fy-cell {
        background-color: rgba(238, 247, 252, 0.25);
        border-left: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
    }
    .total-cell {
        background-color: rgba(236, 253, 245, 0.4);
        font-weight: 700;
        color: #047857;
    }

    /* Loading Overlay */
    .report-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(2px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 50;
        border-radius: 12px;
    }
</style>
@endpush

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header & Actions --}}
        <div class="row align-items-center mb-3">
            <div class="col-md-6 col-12">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon-bubble bg-primary-subtle text-primary">
                        <i class="ri-calendar-line fs-3"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold text-dark">Company Multi-Year Expense Report</h4>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 small text-muted">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('expense-type-report') }}" class="text-decoration-none text-muted">Reports</a></li>
                                <li class="breadcrumb-item active text-primary fw-medium">Multi-Year Comparison</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12 text-md-end mt-2 mt-md-0">
                <div class="d-inline-flex gap-2 flex-wrap">
                    <a href="{{ route('expense-tree-report') }}" class="btn btn-sm btn-outline-info">
                        <i class="ri-node-tree me-1"></i>Tree Report
                    </a>
                    <a href="{{ route('expense-type-report') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-bar-chart-2-line me-1"></i>Type Report
                    </a>
                    <a href="{{ route('expense-type-report.manager-grouped') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-group-line me-1"></i>Manager Grouped
                    </a>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-success" id="btnExportExcel" onclick="triggerExport('excel')">
                            <i class="ri-file-excel-2-line me-1"></i>Export Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerExport('excel')">
                                    <i class="ri-file-excel-2-line text-success me-2"></i>Formatted Excel (.xlsx)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerExport('csv')">
                                    <i class="ri-file-list-3-line text-primary me-2"></i>Raw CSV File (.csv)
                                </a>
                            </li>
                        </ul>
                    </div>
                    <button class="btn btn-sm btn-outline-dark" onclick="window.print()" title="Print page">
                        <i class="ri-printer-line"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- Filter Control Card --}}
        <div class="card filter-card mb-3">
            <div class="card-body p-3">
                <form id="multiYearFilterForm">
                    <div class="row g-2 align-items-end">

                        {{-- Company Selector --}}
                        <div class="col-xl-3 col-md-6 col-sm-12">
                            <label class="form-label small fw-bold mb-1 text-muted">
                                <i class="ri-building-line me-1 text-primary"></i>Company
                            </label>
                            <select id="filterCompany" name="company_id" class="form-select form-select-sm">
                                @foreach($companies as $comp)
                                    <option value="{{ $comp['id'] }}">{{ $comp['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Base Financial Year --}}
                        <div class="col-xl-3 col-md-6 col-sm-12">
                            <label class="form-label small fw-bold mb-1 text-muted">
                                <i class="ri-calendar-event-line me-1 text-primary"></i>Base Financial Year
                            </label>
                            <select id="filterBaseYear" name="base_year_id" class="form-select form-select-sm">
                                @foreach($years as $yr)
                                    <option value="{{ $yr->YearId }}" {{ $yr->YearId == $defaultYearId ? 'selected' : '' }}>
                                        {{ $yr->Year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Department Filter (Multi-Select) --}}
                        <div class="col-xl-3 col-md-6 col-sm-12">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label small fw-bold mb-0 text-muted">
                                    <i class="ri-node-tree me-1 text-primary"></i>Department
                                </label>
                                <div class="d-flex gap-2">
                                    <a href="javascript:void(0)" class="select-action-link text-primary" id="btnSelectAllDept" title="Select All Departments">All</a>
                                    <span class="text-muted opacity-50" style="font-size: 10px;">|</span>
                                    <a href="javascript:void(0)" class="select-action-link text-muted" id="btnClearDept" title="Clear Department selection">Clear</a>
                                </div>
                            </div>
                            <select id="filterDepartment" name="department_id[]" class="form-select form-select-sm select2-dept" multiple="multiple" data-placeholder="All Departments (or select)">
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Claim Type Filter (Multi-Select) --}}
                        <div class="col-xl-3 col-md-6 col-sm-12">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label small fw-bold mb-0 text-muted">
                                    <i class="ri-price-tag-3-line me-1 text-primary"></i>Claim Type
                                </label>
                                <div class="d-flex gap-2">
                                    <a href="javascript:void(0)" class="select-action-link text-primary" id="btnSelectAllClaim" title="Select All Claim Types">All</a>
                                    <span class="text-muted opacity-50" style="font-size: 10px;">|</span>
                                    <a href="javascript:void(0)" class="select-action-link text-muted" id="btnClearClaim" title="Clear Claim Type selection">Clear</a>
                                </div>
                            </div>
                            <select id="filterClaimType" name="claim_type_id[]" class="form-select form-select-sm select2-claim" multiple="multiple" data-placeholder="All Claim Types (or select)">
                                @foreach($claimTypes as $ct)
                                    <option value="{{ $ct->ClaimId }}">{{ $ct->ClaimName }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Amount Type Toggle, Action Buttons & Comparison Horizon --}}
                        <div class="col-12 mt-2 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
                            {{-- Amount Basis --}}
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <span class="small fw-semibold text-muted">Amount Basis:</span>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="amount_type" id="amtSettled" value="settled" checked>
                                    <label class="form-check-label small fw-medium" for="amtSettled">
                                        Final Settled Amount <span class="text-muted">(Financed / Verified)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="amount_type" id="amtClaimed" value="claimed">
                                    <label class="form-check-label small fw-medium" for="amtClaimed">
                                        Claimed Amount <span class="text-muted">(FilledTAmt)</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Actions Group & Comparison Horizon --}}
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-primary px-3" id="btnApplyFilter" onclick="loadReportData()">
                                    <i class="ri-filter-3-line me-1"></i>Apply Filters
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="btnResetFilter" onclick="resetFilters()">
                                    <i class="ri-refresh-line me-1"></i>Reset
                                </button>

                                <div class="vr my-1 mx-2 text-muted opacity-25 d-none d-md-block" style="height: 24px;"></div>

                                {{-- Comparison Horizon (Individual Process - triggers immediately on button click) --}}
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-semibold text-muted text-nowrap">
                                        <i class="ri-history-line me-1 text-primary"></i>Comparison Horizon:
                                    </span>
                                    <div class="btn-group" role="group" aria-label="Comparison Horizon Preset">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-range-preset" data-range="1">1 Year</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-range-preset" data-range="2">2 Years</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-range-preset active" data-range="3">3 Years</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-range-preset" data-range="5">5 Years</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-range-preset" data-range="all">All</button>
                                    </div>
                                    <input type="hidden" id="filterYearRange" name="year_range" value="3">
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        {{-- KPI Summary Stats Cards --}}
        <div class="row g-3 mb-3" id="kpiBar">
            {{-- Card 1: Total Expenditure --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card kpi-stat-card kpi-stat-primary p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Total Expenditure</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1 num-val" id="kpiTotalExpense">₹ 0</h3>
                            <span class="text-primary small fw-medium mt-1 d-block" id="kpiPeriodSubtext">
                                <i class="ri-calendar-check-line me-1"></i>Selected Period Total
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-primary-subtle text-primary">
                            <i class="ri-money-rupee-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Companies Count --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card kpi-stat-card kpi-stat-info p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Active Companies</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1 num-val" id="kpiTotalCompanies">0</h3>
                            <span class="text-muted small mt-1 d-block">
                                <i class="ri-building-2-line me-1"></i>Entity Distribution
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-info-subtle text-info">
                            <i class="ri-community-line"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Departments Count --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card kpi-stat-card kpi-stat-purple p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Active Departments</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1 num-val" id="kpiTotalDepartments">0</h3>
                            <span class="text-muted small mt-1 d-block">
                                <i class="ri-node-tree me-1"></i>Functional Units
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-purple-subtle text-purple" style="background:#f3e8ff;color:#7e22ce;">
                            <i class="ri-team-line"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 4: Claim Types Count --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card kpi-stat-card kpi-stat-success p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Expense Categories</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1 num-val" id="kpiTotalClaimTypes">0</h3>
                            <span class="text-success small fw-medium mt-1 d-block">
                                <i class="ri-price-tag-3-line me-1"></i>Active Claim Types
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-success-subtle text-success">
                            <i class="ri-pie-chart-2-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Table Card --}}
        <div class="card table-container-card position-relative">
            {{-- Loading Spinner Overlay --}}
            <div id="reportLoadingOverlay" class="report-loading-overlay" style="display: none;">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h6 class="mt-3 text-dark fw-bold">Aggregating Multi-Year Data...</h6>
                <p class="text-muted small mb-0">Querying company expense ledgers across financial years</p>
            </div>

            {{-- Table Toolbar Header --}}
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark fs-6">
                        <i class="ri-table-line text-primary me-1"></i>Expense Breakdown Matrix
                    </span>
                    <span class="badge bg-light text-muted border" id="rowCountBadge">0 records</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
                        <input type="text" id="tableSearchBox" class="form-control border-start-0" placeholder="Search in results..." aria-label="Search in results">
                    </div>
                </div>
            </div>

            {{-- Table View --}}
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 650px;">
                    <table class="table multiyear-table align-middle mb-0" id="multiYearTable">
                        <thead id="tableHead">
                            {{-- Generated Dynamically --}}
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="ri-loader-4-line ri-spin fs-1 text-primary mb-2 d-inline-block"></i>
                                        <p class="mb-0 fw-medium">Loading report data...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot id="tableFoot">
                            {{-- Totals Row Generated Dynamically --}}
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Table Pagination Footer --}}
            <div class="card-footer bg-white border-top py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="tablePaginationFooter">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">Show:</span>
                    <select id="pageSizeSelector" class="form-select form-select-sm" style="width: 80px;">
                        <option value="15" selected>15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="-1">All</option>
                    </select>
                    <span class="small text-muted ms-2" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                </div>
                <div id="paginationNav">
                    {{-- Pagination buttons generated dynamically --}}
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    const DATA_URL   = "{{ route('company-multi-year-expense-report.data') }}";
    const EXPORT_URL = "{{ route('company-multi-year-expense-report.export') }}";

    // Application State
    let rawReportData = {
        years: [],
        data: [],
        col_totals: {},
        grand_total: 0,
        summary: {}
    };
    let filteredRows = [];
    let currentPage = 1;
    let pageSize = 15;
    let sortColumn = 'total_amount';
    let sortDirection = 'desc';

    $(document).ready(function () {
        // Initialize Select2 for multiple selects
        if (typeof $.fn.select2 !== 'undefined') {
            $('#filterDepartment').select2({
                placeholder: 'All Departments (or select)',
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });

            $('#filterClaimType').select2({
                placeholder: 'All Claim Types (or select)',
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });
        }

        // Quick All / Clear actions for Department & Claim Type
        $('#btnSelectAllDept').on('click', function (e) {
            e.preventDefault();
            $('#filterDepartment > option').prop('selected', true);
            $('#filterDepartment').trigger('change');
        });
        $('#btnClearDept').on('click', function (e) {
            e.preventDefault();
            $('#filterDepartment').val(null).trigger('change');
        });

        $('#btnSelectAllClaim').on('click', function (e) {
            e.preventDefault();
            $('#filterClaimType > option').prop('selected', true);
            $('#filterClaimType').trigger('change');
        });
        $('#btnClearClaim').on('click', function (e) {
            e.preventDefault();
            $('#filterClaimType').val(null).trigger('change');
        });

        // Preset range buttons click (triggers immediately as an individual process)
        $(".btn-range-preset").on("click", function () {
            $(".btn-range-preset").removeClass("active");
            $(this).addClass("active");
            const range = $(this).data("range");
            $("#filterYearRange").val(range);
            loadReportData();
        });

        // Search box live filtering
        $("#tableSearchBox").on("input", function () {
            const query = $(this).val().toLowerCase().trim();
            applyClientFilter(query);
        });

        // Page size change
        $("#pageSizeSelector").on("change", function () {
            pageSize = parseInt($(this).val(), 10);
            currentPage = 1;
            renderTablePage();
        });

        // Initial Data Load
        loadReportData();
    });

    /**
     * Fetch Report Data via AJAX.
     */
    function loadReportData() {
        $("#reportLoadingOverlay").fadeIn(150);

        const deptVal = $("#filterDepartment").val();
        const claimVal = $("#filterClaimType").val();

        const params = {
            company_id: $("#filterCompany").val(),
            base_year_id: $("#filterBaseYear").val(),
            year_range: $("#filterYearRange").val(),
            department_id: deptVal || [],
            claim_type_id: claimVal || [],
            amount_type: $('input[name="amount_type"]:checked').val() || 'settled',
        };

        $.ajax({
            url: DATA_URL,
            type: "GET",
            data: params,
            success: function (res) {
                $("#reportLoadingOverlay").fadeOut(150);
                if (res.success) {
                    rawReportData = res;
                    updateKpiCards(res.summary, res.years);
                    renderTableHeader(res.years);
                    filteredRows = [...res.data];
                    applySorting();
                    currentPage = 1;
                    renderTablePage();
                    updateFooterTotals();
                } else {
                    showAlert("danger", res.message || "Failed to load report data.");
                }
            },
            error: function (xhr) {
                $("#reportLoadingOverlay").fadeOut(150);
                console.error("Multi-Year Report Error:", xhr);
                let msg = "An error occurred while loading data.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showAlert("danger", msg);
                $("#tableBody").html(`
                    <tr>
                        <td colspan="8" class="text-center py-4 text-danger">
                            <i class="ri-error-warning-line fs-2 d-block mb-2"></i>
                            <strong>Error loading report data</strong>
                            <p class="small text-muted mb-0">${escapeHtml(msg)}</p>
                        </td>
                    </tr>
                `);
            }
        });
    }

    /**
     * Update KPI Cards.
     */
    function updateKpiCards(summary, years) {
        if (!summary) return;
        $("#kpiTotalExpense").text(formatCurrency(summary.total_expense || 0));
        $("#kpiTotalCompanies").text(summary.total_companies || 0);
        $("#kpiTotalDepartments").text(summary.total_departments || 0);
        $("#kpiTotalClaimTypes").text(summary.total_claim_types || 0);

        if (years && years.length > 0) {
            const firstY = years[0].label;
            const lastY  = years[years.length - 1].label;
            if (years.length === 1) {
                $("#kpiPeriodSubtext").html(`<i class="ri-calendar-check-line me-1"></i>${firstY} Total`);
            } else {
                $("#kpiPeriodSubtext").html(`<i class="ri-calendar-check-line me-1"></i>${firstY} to ${lastY} (${years.length} FYs)`);
            }
        }
        $("#rowCountBadge").text(`${summary.total_records || 0} records`);
    }

    /**
     * Render Table Header dynamically based on selected FY columns.
     */
    function renderTableHeader(years) {
        let html = `<tr>
            <th style="width: 45px;" class="text-center">#</th>
            <th style="width: 140px;" class="sortable-th cursor-pointer" onclick="handleSort('company_name')">
                Company ${getSortIcon('company_name')}
            </th>
            <th class="sortable-th cursor-pointer" onclick="handleSort('department_name')">
                Department ${getSortIcon('department_name')}
            </th>
            <th class="sortable-th cursor-pointer" onclick="handleSort('claim_type_name')">
                Claim Type ${getSortIcon('claim_type_name')}
            </th>`;

        (years || []).forEach(y => {
            html += `<th class="text-end fy-col-header sortable-th cursor-pointer" onclick="handleSort('${y.key}')">
                ${escapeHtml(y.label)} ${getSortIcon(y.key)}
            </th>`;
        });

        html += `<th class="text-end total-col-header sortable-th cursor-pointer" onclick="handleSort('total_amount')">
                Total Amount ${getSortIcon('total_amount')}
            </th>
            <th class="text-end sortable-th cursor-pointer" onclick="handleSort('avg_amount')">
                Annual Avg ${getSortIcon('avg_amount')}
            </th>
            <th class="text-end sortable-th cursor-pointer" style="width: 120px;" onclick="handleSort('contribution_pct')">
                Contribution % ${getSortIcon('contribution_pct')}
            </th>
        </tr>`;

        $("#tableHead").html(html);
    }

    /**
     * Client-side search and filtering.
     */
    function applyClientFilter(query) {
        if (!query) {
            filteredRows = [...rawReportData.data];
        } else {
            filteredRows = rawReportData.data.filter(row => {
                const comp = (row.company_name || '').toLowerCase();
                const dept = (row.department_name || '').toLowerCase();
                const type = (row.claim_type_name || '').toLowerCase();
                return comp.includes(query) || dept.includes(query) || type.includes(query);
            });
        }
        applySorting();
        currentPage = 1;
        renderTablePage();
        updateFooterTotals();
    }

    /**
     * Sorting Handler.
     */
    function handleSort(col) {
        if (sortColumn === col) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = col;
            sortDirection = (col === 'company_name' || col === 'department_name' || col === 'claim_type_name') ? 'asc' : 'desc';
        }
        renderTableHeader(rawReportData.years);
        applySorting();
        renderTablePage();
    }

    function applySorting() {
        filteredRows.sort((a, b) => {
            let valA, valB;
            if (sortColumn.startsWith('fy_')) {
                valA = parseFloat(a.years[sortColumn] || 0);
                valB = parseFloat(b.years[sortColumn] || 0);
            } else if (sortColumn === 'total_amount' || sortColumn === 'avg_amount' || sortColumn === 'contribution_pct') {
                valA = parseFloat(a[sortColumn] || 0);
                valB = parseFloat(b[sortColumn] || 0);
            } else {
                valA = (a[sortColumn] || '').toString().toLowerCase();
                valB = (b[sortColumn] || '').toString().toLowerCase();
            }

            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    }

    function getSortIcon(col) {
        if (sortColumn !== col) return '<i class="ri-arrow-up-down-line opacity-25 ms-1"></i>';
        return sortDirection === 'asc' 
            ? '<i class="ri-arrow-up-line text-primary ms-1"></i>' 
            : '<i class="ri-arrow-down-line text-primary ms-1"></i>';
    }

    /**
     * Render the paginated rows into the table body.
     */
    function renderTablePage() {
        const totalRows = filteredRows.length;
        if (totalRows === 0) {
            const colSpan = (rawReportData.years ? rawReportData.years.length : 0) + 7;
            $("#tableBody").html(`
                <tr>
                    <td colspan="${colSpan}" class="text-center py-5 text-muted">
                        <i class="ri-inbox-line fs-1 d-block mb-2 text-muted opacity-50"></i>
                        <strong>No expense records found</strong>
                        <p class="small text-muted mb-0">Try changing your filters or choosing another financial year range.</p>
                    </td>
                </tr>
            `);
            $("#paginationInfo").text("Showing 0 to 0 of 0 entries");
            $("#paginationNav").html("");
            return;
        }

        let startIdx = 0;
        let endIdx = totalRows;
        if (pageSize > 0) {
            const totalPages = Math.ceil(totalRows / pageSize);
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;
            startIdx = (currentPage - 1) * pageSize;
            endIdx = Math.min(startIdx + pageSize, totalRows);
        }

        const pageRows = (pageSize > 0) ? filteredRows.slice(startIdx, endIdx) : filteredRows;
        let html = '';

        pageRows.forEach((row, idx) => {
            const rowNumber = startIdx + idx + 1;
            html += `<tr>
                <td class="text-center text-muted small">${rowNumber}</td>
                <td>
                    <span class="badge ${row.company_badge || 'bg-light text-dark'} fw-semibold py-1 px-2">
                        ${escapeHtml(row.company_short || row.company_name)}
                    </span>
                    <span class="small text-muted d-block mt-1" style="font-size: 11px;">
                        ${escapeHtml(row.company_name)}
                    </span>
                </td>
                <td>
                    <span class="fw-semibold text-dark">${escapeHtml(row.department_name)}</span>
                </td>
                <td>
                    <span class="text-secondary">${escapeHtml(row.claim_type_name)}</span>
                </td>`;

            (rawReportData.years || []).forEach(y => {
                const yrAmt = parseFloat(row.years[y.key] || 0);
                const count = row.claim_counts[y.key] || 0;
                const formatted = yrAmt > 0 ? formatCurrency(yrAmt) : '<span class="text-muted opacity-50">-</span>';
                const countBadge = count > 0 ? `<span class="badge bg-light text-secondary border ms-1" style="font-size:10px;">${count}</span>` : '';
                html += `<td class="text-end num-val fy-cell" title="${escapeHtml(y.label)}: ₹ ${formatNumber(yrAmt)} (${count} claims)">
                    ${formatted} ${countBadge}
                </td>`;
            });

            const totAmt = parseFloat(row.total_amount || 0);
            const avgAmt = parseFloat(row.avg_amount || 0);
            const pctVal = parseFloat(row.contribution_pct || 0);

            html += `<td class="text-end num-val total-cell">
                    ${formatCurrency(totAmt)}
                </td>
                <td class="text-end num-val text-muted">
                    ${formatCurrency(avgAmt)}
                </td>
                <td class="text-end num-val">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold px-2 py-1" style="font-size: 11px;">
                        ${pctVal.toFixed(2)}%
                    </span>
                </td>
            </tr>`;
        });

        $("#tableBody").html(html);
        $("#paginationInfo").text(`Showing ${startIdx + 1} to ${endIdx} of ${totalRows} entries`);
        renderPaginationButtons(totalRows);
    }

    /**
     * Render Dynamic Footer with Column Totals.
     */
    function updateFooterTotals() {
        const years = rawReportData.years || [];
        if (years.length === 0) {
            $("#tableFoot").html("");
            return;
        }

        // Calculate dynamic totals for the currently filtered set
        const totals = {};
        years.forEach(y => totals[y.key] = 0);
        let grandTot = 0;

        filteredRows.forEach(row => {
            years.forEach(y => {
                const val = parseFloat(row.years[y.key] || 0);
                totals[y.key] += val;
            });
            grandTot += parseFloat(row.total_amount || 0);
        });

        const avgGrand = years.length > 0 ? (grandTot / years.length) : 0;

        let footHtml = `<tr>
            <th colspan="4" class="text-start">
                <div class="d-flex align-items-center justify-content-between">
                    <span><i class="ri-calculator-line me-1 text-primary"></i>GRAND TOTAL (${filteredRows.length} Categories)</span>
                </div>
            </th>`;

        years.forEach(y => {
            const tot = totals[y.key] || 0;
            footHtml += `<th class="text-end num-val fy-col-header text-primary">
                ${formatCurrency(tot)}
            </th>`;
        });

        footHtml += `<th class="text-end num-val total-col-header text-success fs-6">
                ${formatCurrency(grandTot)}
            </th>
            <th class="text-end num-val text-dark">
                ${formatCurrency(avgGrand)}
            </th>
            <th class="text-end num-val text-primary fw-bold">
                100.00%
            </th>
        </tr>`;

        $("#tableFoot").html(footHtml);
    }

    /**
     * Render Pagination Navigation buttons.
     */
    function renderPaginationButtons(totalRows) {
        if (pageSize <= 0 || totalRows <= pageSize) {
            $("#paginationNav").html("");
            return;
        }

        const totalPages = Math.ceil(totalRows / pageSize);
        let html = '<ul class="pagination pagination-sm mb-0">';

        // Prev
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage - 1})"><i class="ri-arrow-left-s-line"></i></a>
        </li>`;

        let startP = Math.max(1, currentPage - 2);
        let endP = Math.min(totalPages, currentPage + 2);

        if (startP > 1) {
            html += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToPage(1)">1</a></li>`;
            if (startP > 2) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }

        for (let p = startP; p <= endP; p++) {
            html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="goToPage(${p})">${p}</a>
            </li>`;
        }

        if (endP < totalPages) {
            if (endP < totalPages - 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            html += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goToPage(${totalPages})">${totalPages}</a></li>`;
        }

        // Next
        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage + 1})"><i class="ri-arrow-right-s-line"></i></a>
        </li>`;

        html += '</ul>';
        $("#paginationNav").html(html);
    }

    function goToPage(page) {
        currentPage = page;
        renderTablePage();
    }

    /**
     * Trigger Excel or CSV export.
     */
    function triggerExport(format) {
        const deptVal = $("#filterDepartment").val();
        const claimVal = $("#filterClaimType").val();

        const params = {
            company_id: $("#filterCompany").val(),
            base_year_id: $("#filterBaseYear").val(),
            year_range: $("#filterYearRange").val(),
            department_id: deptVal || [],
            claim_type_id: claimVal || [],
            amount_type: $('input[name="amount_type"]:checked').val() || 'settled',
            format: format
        };

        const qs = $.param(params);
        window.location.href = `${EXPORT_URL}?${qs}`;
    }

    /**
     * Reset all filters to default.
     */
    function resetFilters() {
        $("#filterCompany").val('all');
        $("#filterBaseYear").val('{{ $defaultYearId }}');
        $("#filterYearRange").val('3');
        $(".btn-range-preset").removeClass("active");
        $(".btn-range-preset[data-range='3']").addClass("active");
        if (typeof $.fn.select2 !== 'undefined') {
            $("#filterDepartment").val(null).trigger('change');
            $("#filterClaimType").val(null).trigger('change');
        } else {
            $("#filterDepartment").val([]);
            $("#filterClaimType").val([]);
        }
        $("#amtSettled").prop('checked', true);
        $("#tableSearchBox").val('');
        loadReportData();
    }

    // Helper formatting functions
    function formatCurrency(val) {
        if (!val || val === 0) return '₹ 0';
        return '₹ ' + formatNumber(val);
    }

    function formatNumber(val) {
        val = parseFloat(val) || 0;
        return val.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(type, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type === 'danger' ? 'error' : type,
                title: type === 'danger' ? 'Error' : 'Notice',
                text: message,
                timer: 3500,
                showConfirmButton: false
            });
        } else {
            alert(message);
        }
    }
</script>
@endpush
