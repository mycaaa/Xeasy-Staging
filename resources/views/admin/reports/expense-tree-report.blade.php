@extends('layouts.app')
@section('title', 'Expense Tree Report - Hierarchical Drilldown')

@push('styles')
<style>
    :root {
        --tree-primary: #15648C;
        --tree-primary-dark: #0e4663;
        --tree-primary-light: #e8f4fa;
        --lvl-function: #1e3a8a;
        --lvl-vertical: #4338ca;
        --lvl-department: #0f766e;
        --lvl-subdept: #059669;
        --lvl-bu: #0284c7;
        --lvl-zone: #16a34a;
        --lvl-region: #d97706;
        --lvl-territory: #ea580c;
        --lvl-hq: #7c3aed;
    }

    /* KPI Summary Cards */
    .tree-kpi-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
        background: #fff;
    }
    .tree-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.09);
    }
    .tree-kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 5px;
    }
    .kpi-finance::before  { background: #d7f3e6; color: #065f46;}
    .kpi-claims::before   { background: #dde6ff; color: #1e3a8a;}
    .kpi-approved::before { background: #fdefd2; color: #7a4500;}
    .kpi-scope::before    { background: #ede7fe; color: #4c1d95;}

    .kpi-icon-bubble {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    /* Tree Table Card */
    .tree-table-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        background: #fff;
    }
    .tree-table-card .card-header {
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 1rem 1.25rem;
    }

    .tree-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        font-size: 12px;
    }
    .tree-table thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        border-bottom: 2px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
        white-space: nowrap;
    }
    .tree-table thead th.text-end { text-align: right; }
    .tree-table thead th.text-center { text-align: center; }

    /* Tree Row Styling */
    .tree-row {
        cursor: pointer;
        transition: background-color 0.15s ease;
        border-bottom: 1px solid #f1f5f9;
    }
    .tree-row:hover {
        background-color: #f8fafc !important;
    }
    .tree-row.expanded {
        background-color: #fafcff;
    }
    .tree-row td {
        padding: 10px 14px;
        vertical-align: middle;
        border-top: 1px solid #f1f5f9;
    }

    /* Level Badges */
    .level-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 6px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        color: #fff;
    }
    .lvl-function   { background: var(--lvl-function); }
    .lvl-vertical   { background: var(--lvl-vertical); }
    .lvl-department { background: var(--lvl-department); }
    .lvl-subdept    { background: var(--lvl-subdept); }
    .lvl-bu         { background: var(--lvl-bu); }
    .lvl-zone       { background: var(--lvl-zone); }
    .lvl-region     { background: var(--lvl-region); }
    .lvl-territory  { background: var(--lvl-territory); }
    .lvl-hq         { background: var(--lvl-hq); }

    /* Hierarchy Sequence Legend */
    .hierarchy-legend-bar {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 12px;
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 11px;
    }
    .legend-chip {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 5px;
        color: #fff;
        text-transform: uppercase;
    }
    .legend-sep {
        color: #94a3b8;
        font-size: 10px;
    }

    /* Toggle Expand Icon */
    .tree-expander {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 4px;
        background: #f1f5f9;
        color: #475569;
        margin-right: 6px;
        transition: transform 0.2s ease, background-color 0.2s ease;
        font-size: 14px;
    }
    .tree-row.expanded > td .tree-expander {
        transform: rotate(90deg);
        background: #e0f2fe;
        color: #0369a1;
    }
    .tree-expander.empty-leaf {
        background: transparent !important;
        color: #cbd5e1;
        transform: none !important;
        cursor: default;
    }

    /* Tree Node Text & Highlight */
    .tree-node-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 12.5px;
    }
    .tree-node-title mark {
        background-color: #fef08a;
        padding: 0 2px;
        border-radius: 2px;
    }

    /* Numbers Styling */
    .num-pill {
        background: #f1f5f9;
        color: #334155;
        padding: 2px 8px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 11.5px;
        display: inline-block;
    }
    .amt-cell {
        font-family: 'Consolas', 'Segoe UI Mono', monospace;
        font-size: 12px;
        color: #334155;
        font-weight: 500;
    }
    .amt-finance {
        font-weight: 700;
        color: #059669;
        background: #ecfdf5;
        padding: 3px 8px;
        border-radius: 6px;
        border: 1px solid #a7f3d0;
        font-family: 'Consolas', 'Segoe UI Mono', monospace;
        font-size: 12px;
        display: inline-block;
    }

    /* Mini Progress Bar */
    .share-bar-container {
        width: 75px;
        height: 6px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-right: 6px;
    }
    .share-bar-fill {
        height: 100%;
        background: #dde6ff;
        border-radius: 10px; color: #1e3a8a;}

    /* Search Input */
    .tree-search-input {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        padding: 5px 12px 5px 32px;
        font-size: 12px;
        transition: all 0.2s ease;
    }
    .tree-search-input:focus {
        border-color: #15648C;
        box-shadow: 0 0 0 3px rgba(21, 100, 140, 0.15);
    }

    /* Depth Buttons */
    .depth-btn-group .btn {
        font-size: 11px;
        padding: 4px 8px;
        font-weight: 600;
    }

    /* Additional Filters Offcanvas */
    #additionalFiltersCanvas {
        width: 460px;
    }
    #additionalFiltersCanvas .offcanvas-header {
        background: #dde6ff;
        color: #1e3a8a;
        padding: 1rem 1.25rem;
    }
    #additionalFiltersCanvas .offcanvas-header .offcanvas-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 1.1rem;
    }
    #additionalFiltersCanvas .offcanvas-header .btn-close {
        filter: invert(1);
    }
    #additionalFiltersCanvas .select2-container {
        width: 100% !important;
    }
    #additionalFiltersCanvas .select2-container--default .select2-selection--multiple {
        border-color: #cbd5e1;
        border-radius: 6px;
        min-height: 38px;
    }
    #additionalFiltersCanvas .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #00175f;
    }
    .btn-apply-filters {
        background-color: #dde6ff;
        border-color: #00175f;
        color: #1e3a8a;
        font-weight: 600;
        padding: 0.6rem 1rem;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-apply-filters:hover {
        background-color: #dde6ff;
        border-color: #001047;
        color: #1e3a8a;
    }
</style>
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3 align-items-center">
            <div class="col-md-6 col-12">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white"
                         style="width: 44px; height: 44px; background: #dde6ff; box-shadow: 0 4px 12px rgba(21,100,140,0.25); color: #1e3a8a;">
                        <i class="ri-git-merge-line fs-4"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold text-dark">Expense Tree Report</h4>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 small text-muted">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('expense-type-report') }}" class="text-decoration-none text-muted">Reports</a></li>
                                <li class="breadcrumb-item active text-primary fw-medium">Hierarchical Tree</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12 text-md-end mt-2 mt-md-0">
                <div class="d-inline-flex gap-2 flex-wrap">
                    <a href="{{ route('expense-type-report') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-bar-chart-2-line me-1"></i>Expense Type Report
                    </a>
                    <a href="{{ route('expense-type-report.manager-grouped') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-group-line me-1"></i>Manager Grouped
                    </a>
                    <a href="{{ route('company-multi-year-expense-report') }}" class="btn btn-sm btn-outline-info">
                        <i class="ri-calendar-line me-1"></i>Multi-Year Report
                    </a>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-success" id="exportCsvBtn" onclick="exportData('excel')" disabled>
                            <i class="ri-file-excel-2-line me-1"></i>Export Month-wise Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" id="exportDropdownBtn" disabled>
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item py-2" href="javascript:void(0)" onclick="exportData('excel')">
                                    <i class="ri-file-excel-2-line text-success me-2"></i>Excel (.xlsx) - Month Headers & Stage Highlights
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="javascript:void(0)" onclick="exportData('csv')">
                                    <i class="ri-file-list-3-line text-primary me-2"></i>CSV - 2-Tier Month Headers & Stages
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

        {{-- KPI Summary Stats Cards (100% database driven) --}}
        <div class="row g-3 mb-3" id="kpiBar" style="display: none;">
            {{-- Card 1: Claimed Count --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card tree-kpi-card kpi-claims p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Claimed Count</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1" id="kpiTotalClaims">0</h3>
                            <span class="text-primary small fw-medium mt-1 d-block">
                                <i class="ri-file-list-3-fill me-1"></i>Total Claims Submitted
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-primary-subtle text-primary">
                            <i class="ri-file-list-3-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Claimed Amount --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card tree-kpi-card kpi-scope p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Claimed Amount</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1" id="kpiTotalClaimed">₹ 0</h3>
                            <span class="text-muted small mt-1 d-block">
                                <i class="ri-file-text-line me-1"></i>Total Amount Submitted
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-info-subtle text-info">
                            <i class="ri-file-text-line"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Approved Amount --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card tree-kpi-card kpi-approved p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Approved Amount</span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1" id="kpiTotalApproved">₹ 0</h3>
                            <span class="text-warning small fw-medium mt-1 d-block">
                                <i class="ri-check-double-line me-1"></i>Approved by Management
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-warning-subtle text-warning">
                            <i class="ri-check-double-line"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 4: Financed / Verified Amount (Final Amount) --}}
            <div class="col-xl-3 col-md-6 col-12">
                <div class="card tree-kpi-card kpi-finance p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase" id="kpiFinalLabel">
                                {{ !empty($isVVNR) ? 'Total Verified Amount' : 'Total Financed Amount' }}
                            </span>
                            <h3 class="mb-0 fw-bolder text-dark mt-1" id="kpiTotalFinal">₹ 0</h3>
                            <span class="text-success small fw-medium mt-1 d-block" id="kpiFinalSubtext">
                                <i class="ri-checkbox-circle-line me-1"></i>{{ !empty($isVVNR) ? 'Final Settled Amount (VVNR)' : 'Final Settled Amount (VNR)' }}
                            </span>
                        </div>
                        <div class="kpi-icon-bubble bg-success-subtle text-success">
                            <i class="ri-money-rupee-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tree Table Card --}}
        <div class="card tree-table-card">
            {{-- Toolbar Header: FY switch, live badge, search, Expand All, Collapse All --}}
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                        <i class="ri-node-tree text-primary fs-5"></i>
                        Hierarchical Expense Tree
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary fw-semibold px-2 py-1" id="treeStatusBadge">
                        Initializing...
                    </span>

                    {{-- Financial Year Dropdown --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2">
                        <label for="yearSelect" class="small text-muted fw-bold mb-0">FY:</label>
                        <select id="yearSelect" class="form-select form-select-sm fw-semibold" style="width: 135px; border-color:#cbd5e1;" onchange="loadTreeData()">
                            @foreach($years as $y)
                                <option value="{{ $y->YearId }}" {{ $y->YearId == ($currentYearId ?? session('year_id', 7)) ? 'selected' : '' }}>
                                    {{ $y->Year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Month Dropdown --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2">
                        <label for="monthSelect" class="small text-muted fw-bold mb-0">Month:</label>
                        <select id="monthSelect" class="form-select form-select-sm fw-semibold" style="width: 135px; border-color:#cbd5e1;" onchange="loadTreeData()">
                            <option value="">All Months</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                        </select>
                    </div>

                    {{-- More Filters Drawer Trigger --}}
                    <a class="text-primary position-relative d-flex align-items-center ms-2 text-decoration-none fw-semibold"
                       role="button" data-bs-toggle="offcanvas"
                       data-bs-target="#additionalFiltersCanvas" aria-controls="additionalFiltersCanvas" style="cursor: pointer;"
                       title="Open additional filters">
                        <i class="ri-equalizer-line fs-16 me-1"></i>
                        <span class="align-middle">More Filters</span>
                        <span id="activeFilterCount" class="badge bg-danger-subtle text-danger ms-1">0</span>
                    </a>
                </div>

                {{-- Action Controls: Search, Expand All, Collapse All, Refresh --}}
                <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                    {{-- Live Search Filter --}}
                    <div class="position-relative">
                        <i class="ri-search-line position-absolute text-muted" style="left:10px; top:8px; font-size:13px;"></i>
                        <input type="text" id="liveSearchInput" class="form-control form-control-sm tree-search-input"
                               placeholder="Search tree nodes..." aria-label="Search tree nodes" style="width: 200px;" oninput="filterTreeTable(this.value)">
                    </div>

                    <button class="btn btn-sm btn-outline-primary shadow-sm" onclick="expandAllNodes()" title="Expand all branches and child nodes">
                        <i class="ri-expand-up-down-line me-1"></i>Expand All
                    </button>
                    <button class="btn btn-sm btn-outline-secondary shadow-sm" onclick="collapseAllNodes()" title="Collapse all branches">
                        <i class="ri-collapse-diagonal-line me-1"></i>Collapse All
                    </button>
                    <button class="btn btn-sm btn-light border shadow-sm" onclick="loadTreeData()" title="Reload from Database">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                {{-- Loader Animation --}}
                <div id="treeLoader" class="text-center py-5" style="display: none;">
                    <div class="spinner-grow text-primary" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
                    <h6 class="mt-3 fw-bold text-dark">Aggregating Hierarchical Tree...</h6>
                    <p class="text-muted small">Computing rolled-up totals across functions, verticals, departments, subdepts, zones, territories, and locations</p>
                </div>

                {{-- Empty State --}}
                <div id="emptyTreeState" class="text-center py-5" style="display: none;">
                    <div class="avatar-lg mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                         style="background: #e0f2fe; width: 64px; height: 64px;">
                         <i class="ri-node-tree text-primary fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No records found</h5>
                    <p class="text-muted small mx-auto" style="max-width: 500px;">
                        No expense records were found matching the selected financial year.
                    </p>
                    <button class="btn btn-primary btn-sm px-4" onclick="loadTreeData()">
                        <i class="ri-refresh-line me-1"></i>Try Again
                    </button>
                </div>

                {{-- The Tree Table --}}
                <div id="treeTableWrapper" style="display: none; overflow-x: auto; max-height: 74vh; overflow-y: auto;">
                    <table class="tree-table mb-0" id="mainTreeTable">
                        <thead id="treeTableHead">
                            @if(!empty($isVVNR))
                            <tr>
                                <th style="min-width: 360px;">Organization Entity / Branch</th>
                                <th style="width: 120px;">Level</th>
                                <th class="text-center" style="width: 90px;">Claims</th>
                                <th class="text-end" style="width: 130px;">Claimed (₹)</th>
                                <th class="text-end" style="width: 130px;">Approved (₹)</th>
                                <th class="text-end" style="width: 150px; background: #e0f2fe; color: #0369a1;">Verified (Final) (₹)</th>
                                <th style="width: 130px;">Share %</th>
                                <th class="text-center" style="width: 80px;">Action</th>
                            </tr>
                            @else
                            <tr>
                                <th style="min-width: 360px;">Organization Entity / Branch</th>
                                <th style="width: 120px;">Level</th>
                                <th class="text-center" style="width: 90px;">Claims</th>
                                <th class="text-end" style="width: 130px;">Claimed (₹)</th>
                                <th class="text-end" style="width: 130px;">Verified (₹)</th>
                                <th class="text-end" style="width: 130px;">Approved (₹)</th>
                                <th class="text-end" style="width: 150px; background: #e0f2fe; color: #0369a1;">Financed (Final) (₹)</th>
                                <th style="width: 130px;">Share %</th>
                                <th class="text-center" style="width: 80px;">Action</th>
                            </tr>
                            @endif
                        </thead>
                        <tbody id="treeTableBody">
                            {{-- Populated dynamically --}}
                        </tbody>
                        <tfoot id="treeTableFoot" style="position: sticky; bottom: 0; background: #f8fafc; z-index: 9; border-top: 2px solid #cbd5e1;">
                            {{-- Populated dynamically --}}
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-light py-2 px-3 justify-content-between align-items-center text-muted small d-none" id="treeFooterBar">
                <span id="footerInfoText">Loaded branches</span>
                <span><i class="ri-information-line me-1 text-primary"></i>Click any row to expand/collapse its children. Use Expand All or Collapse All to explore the full tree.</span>
            </div>
        </div>

        {{-- Off-canvas Drawer: Reusable Organogram & Policy Filters --}}
        @include('partials.organogram-filters-drawer')
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
@include('partials.organogram-filters-js')
<script>
    // ── Routes & Global State ──────────────────────────────────────────────────
    const TREE_DATA_URL = "{{ route('expense-tree-report.tree-data') }}";
    const EXPORT_URL    = "{{ route('expense-tree-report.export') }}";

    let rawTreeData   = [];
    let grandTotals   = {};
    let openNodesSet  = new Set(); // Tracks paths that are expanded
    let isCurrentVVNR = {{ !empty($isVVNR) ? 'true' : 'false' }};

    // Auto-load report immediately on page load
    $(document).ready(function() {
        window.onOrganogramFilterApply = function() {
            loadTreeData();
        };

        window.onOrganogramFilterReset = function() {
            loadTreeData();
        };

        loadTreeData();
    });

    // ── Format Currency helper (Indian Rupee style) ──────────────────────────
    function formatINR(val) {
        val = parseFloat(val) || 0;
        return '₹ ' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatNumber(val) {
        val = parseInt(val) || 0;
        return val.toLocaleString('en-IN');
    }

    // ── Load Tree Data via AJAX ──────────────────────────────────────────────
    function loadTreeData() {
        const yearId = $('#yearSelect').val() || '{{ $currentYearId ?? session('year_id', 7) }}';
        const month  = $('#monthSelect').val() || '';

        // UI Loading State
        $('#treeLoader').show();
        $('#emptyTreeState').hide();
        $('#treeTableWrapper').hide();
        $('#treeFooterBar').addClass('d-none').removeClass('d-flex');
        $('#kpiBar').hide();
        $('#exportCsvBtn, #exportDropdownBtn').prop('disabled', true);
        $('#treeStatusBadge').removeClass('bg-success-subtle text-success bg-secondary-subtle text-secondary bg-danger-subtle text-danger')
                             .addClass('bg-warning-subtle text-warning').text('Computing Tree...');

        const basePayload = {
            _token: '{{ csrf_token() }}',
            year_id: yearId,
            month: month,
            company_id: '{{ $companyId ?? session('company_id', 1) }}'
        };
        const organogramPayload = typeof window.getOrganogramFilterValues === 'function'
            ? window.getOrganogramFilterValues()
            : {};
        const requestData = Object.assign({}, basePayload, organogramPayload);

        $.ajax({
            url: TREE_DATA_URL,
            type: 'POST',
            data: requestData,
            success: function(res) {
                if (res.success) {
                    isCurrentVVNR = (res.is_vvnr === true);
                    rawTreeData   = res.hierarchy || [];
                    grandTotals   = res.grand_totals || {};
                    openNodesSet.clear();

                    // Auto-expand ALL branches so full tree with all children from DB is immediately visible
                    function collectAllNodeKeys(nodes) {
                        nodes.forEach(node => {
                            openNodesSet.add(node.key);
                            if (node.children && node.children.length > 0) {
                                collectAllNodeKeys(node.children);
                            }
                        });
                    }
                    collectAllNodeKeys(rawTreeData);

                    renderKPIs(grandTotals, isCurrentVVNR);
                    renderTreeTable(isCurrentVVNR);

                    if (rawTreeData.length > 0) {
                        $('#exportCsvBtn, #exportDropdownBtn').prop('disabled', false);
                        $('#treeStatusBadge').removeClass('bg-warning-subtle text-warning bg-secondary-subtle text-secondary bg-danger-subtle text-danger')
                                             .addClass('bg-success-subtle text-success')
                                             .html(`<i class="ri-checkbox-circle-line me-1"></i>Live DB Data (${grandTotals.claims_count || 0} Claims)`);
                    } else {
                        $('#treeStatusBadge').removeClass('bg-warning-subtle text-warning bg-success-subtle text-success')
                                             .addClass('bg-secondary-subtle text-secondary')
                                             .html(`<i class="ri-information-line me-1"></i>No Records Found`);
                    }
                } else {
                    const errMsg = res.db_error || res.message || 'Failed to load tree data.';
                    $('#treeStatusBadge').removeClass('bg-warning-subtle text-warning bg-success-subtle text-success')
                                         .addClass('bg-danger-subtle text-danger')
                                         .html(`<i class="ri-alert-line me-1"></i>${escapeHtml(errMsg)}`);
                    $('#emptyTreeState').show();
                }
            },
            error: function(xhr, status, err) {
                console.error('AJAX Error:', xhr);
                const msg = xhr.responseJSON?.message || xhr.statusText || err || 'Server error';
                $('#treeStatusBadge').removeClass('bg-warning-subtle text-warning bg-success-subtle text-success')
                                     .addClass('bg-danger-subtle text-danger')
                                     .html(`<i class="ri-alert-line me-1"></i>Server Error (${xhr.status}): ${escapeHtml(msg)}`);
                $('#emptyTreeState').show();
            },
            complete: function() {
                $('#treeLoader').hide();
            }
        });
    }

    // ── Render Top KPI Cards ─────────────────────────────────────────────────
    // Sequence: 1) Claimed Count, 2) Claimed Amount, 3) Approved Amount, 4) Financed / Verified Amount
    function renderKPIs(gt, isVVNR = false) {
        $('#kpiTotalClaims').text(formatNumber(gt.claims_count));
        $('#kpiTotalClaimed').text(formatINR(gt.total_filled_amt));
        $('#kpiTotalApproved').text(formatINR(gt.total_appr_amt));

        if (isVVNR) {
            $('#kpiFinalLabel').text('Total Verified Amount');
            $('#kpiTotalFinal').text(formatINR(gt.total_verify_amt));
            $('#kpiFinalSubtext').html('<i class="ri-checkbox-circle-line me-1"></i>Final Settled Amount (VVNR)');
        } else {
            $('#kpiFinalLabel').text('Total Financed Amount');
            $('#kpiTotalFinal').text(formatINR(gt.total_finance_amt));
            $('#kpiFinalSubtext').html('<i class="ri-checkbox-circle-line me-1"></i>Final Settled Amount (VNR)');
        }

        renderTreeFoot(isVVNR, gt);
        $('#kpiBar').slideDown(200);
    }

    // ── Render Tree Table Header ─────────────────────────────────────────────
    function renderTreeHeader(isVVNR = false) {
        const thead = $('#treeTableHead');
        if (isVVNR) {
            thead.html(`
                <tr>
                    <th style="min-width: 360px;">Organization Entity / Branch</th>
                    <th style="width: 120px;">Level</th>
                    <th class="text-center" style="width: 90px;">Claims</th>
                    <th class="text-end" style="width: 130px;">Claimed (₹)</th>
                    <th class="text-end" style="width: 130px;">Approved (₹)</th>
                    <th class="text-end" style="width: 150px; background: #e0f2fe; color: #0369a1;">Verified (Final) (₹)</th>
                    <th style="width: 130px;">Share %</th>
                    <th class="text-center" style="width: 80px;">Action</th>
                </tr>
            `);
        } else {
            thead.html(`
                <tr>
                    <th style="min-width: 360px;">Organization Entity / Branch</th>
                    <th style="width: 120px;">Level</th>
                    <th class="text-center" style="width: 90px;">Claims</th>
                    <th class="text-end" style="width: 130px;">Claimed (₹)</th>
                    <th class="text-end" style="width: 130px;">Verified (₹)</th>
                    <th class="text-end" style="width: 130px;">Approved (₹)</th>
                    <th class="text-end" style="width: 150px; background: #e0f2fe; color: #0369a1;">Financed (Final) (₹)</th>
                    <th style="width: 130px;">Share %</th>
                    <th class="text-center" style="width: 80px;">Action</th>
                </tr>
            `);
        }
    }

    // ── Render Tree Table Footer ─────────────────────────────────────────────
    function renderTreeFoot(isVVNR = false, gt = {}) {
        const tfoot = $('#treeTableFoot');
        if (isVVNR) {
            tfoot.html(`
                <tr class="fw-bold">
                    <td><span class="fs-6 text-dark ms-2"><i class="ri-calculator-line me-1 text-primary"></i>GRAND TOTAL</span></td>
                    <td><span class="badge bg-dark">ALL</span></td>
                    <td class="text-center">${formatNumber(gt.claims_count)}</td>
                    <td class="text-end">${formatINR(gt.total_filled_amt)}</td>
                    <td class="text-end">${formatINR(gt.total_appr_amt)}</td>
                    <td class="text-end text-success fs-6 fw-bolder" style="background:#e0f2fe;">${formatINR(gt.total_verify_amt)}</td>
                    <td>100%</td>
                    <td></td>
                </tr>
            `);
        } else {
            tfoot.html(`
                <tr class="fw-bold">
                    <td><span class="fs-6 text-dark ms-2"><i class="ri-calculator-line me-1 text-primary"></i>GRAND TOTAL</span></td>
                    <td><span class="badge bg-dark">ALL</span></td>
                    <td class="text-center">${formatNumber(gt.claims_count)}</td>
                    <td class="text-end">${formatINR(gt.total_filled_amt)}</td>
                    <td class="text-end">${formatINR(gt.total_verify_amt)}</td>
                    <td class="text-end">${formatINR(gt.total_appr_amt)}</td>
                    <td class="text-end text-success fs-6 fw-bolder" style="background:#e0f2fe;">${formatINR(gt.total_finance_amt)}</td>
                    <td>100%</td>
                    <td></td>
                </tr>
            `);
        }
    }

    // ── Render Tree Table ────────────────────────────────────────────────────
    function renderTreeTable(isVVNR = false) {
        renderTreeHeader(isVVNR);
        const tbody = $('#treeTableBody');
        tbody.empty();

        const colspan = isVVNR ? 8 : 9;
        if (rawTreeData.length === 0) {
            tbody.append(`<tr><td colspan="${colspan}" class="text-center py-4 text-muted">No expense records found matching current criteria.</td></tr>`);
            $('#treeTableWrapper').show();
            $('#treeFooterBar').addClass('d-none').removeClass('d-flex');
            return;
        }

        let totalNodesCount = 0;

        // Recursive walker
        function traverseAndRender(nodes, depth = 0, isParentVisible = true) {
            nodes.forEach(node => {
                totalNodesCount++;
                const hasChildren = (node.children && node.children.length > 0);
                const isOpen = openNodesSet.has(node.key);
                const indentPx = depth * 24 + 10;

                let lvlClass = 'lvl-' + (node.level_type || 'function');
                let iconClass = getLevelIcon(node.level_type);
                let rowDisplay = isParentVisible ? '' : 'style="display:none;"';

                let stageCells = '';
                if (isVVNR) {
                    stageCells = `
                        <td class="text-center">
                            <span class="num-pill">${formatNumber(node.claims_count)}</span>
                        </td>
                        <td class="text-end amt-cell">${formatINR(node.total_filled_amt)}</td>
                        <td class="text-end amt-cell">${formatINR(node.total_appr_amt)}</td>
                        <td class="text-end" style="background: #f0fdf4;">
                            <span class="amt-finance">${formatINR(node.total_verify_amt)}</span>
                        </td>
                    `;
                } else {
                    stageCells = `
                        <td class="text-center">
                            <span class="num-pill">${formatNumber(node.claims_count)}</span>
                        </td>
                        <td class="text-end amt-cell">${formatINR(node.total_filled_amt)}</td>
                        <td class="text-end amt-cell">${formatINR(node.total_verify_amt)}</td>
                        <td class="text-end amt-cell">${formatINR(node.total_appr_amt)}</td>
                        <td class="text-end" style="background: #f0fdf4;">
                            <span class="amt-finance">${formatINR(node.total_finance_amt)}</span>
                        </td>
                    `;
                }

                let rowHtml = `
                    <tr class="tree-row tree-level-${depth} ${isOpen ? 'expanded' : ''}"
                        data-key="${escapeHtml(node.key)}"
                        data-depth="${depth}"
                        data-level-type="${node.level_type}"
                        data-id="${node.id || ''}"
                        data-name="${escapeHtml(node.name)}"
                        ${rowDisplay}
                        onclick="toggleNode('${escapeHtml(node.key)}', event)">
                        <td>
                            <div class="d-flex align-items-center" style="padding-left: ${indentPx}px;">
                                ${hasChildren
                                    ? `<span class="tree-expander"><i class="ri-arrow-right-s-line"></i></span>`
                                    : `<span class="tree-expander empty-leaf"><i class="ri-checkbox-blank-circle-fill" style="font-size:6px;"></i></span>`
                                }
                                <i class="${iconClass} me-2 text-muted fs-6"></i>
                                <span class="tree-node-title text-truncate" title="${escapeHtml(node.name)}">
                                    ${escapeHtml(node.name)}
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="level-badge ${lvlClass}">
                                ${escapeHtml(node.level_type)}
                            </span>
                        </td>
                        ${stageCells}
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="share-bar-container">
                                    <div class="share-bar-fill" style="width: ${Math.min(node.percent_of_parent, 100)}%;"></div>
                                </div>
                                <span class="small fw-semibold text-muted">${node.percent_of_parent}%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            ${hasChildren
                                ? `<button class="btn btn-sm btn-link text-primary p-0" title="${isOpen ? 'Collapse' : 'Expand'}">
                                      <i class="ri-${isOpen ? 'subtract-line' : 'add-line'} fs-5"></i>
                                   </button>`
                                : `<span class="text-muted small">—</span>`
                            }
                        </td>
                    </tr>
                `;

                tbody.append(rowHtml);

                if (hasChildren) {
                    traverseAndRender(node.children, depth + 1, isParentVisible && isOpen);
                }
            });
        }

        traverseAndRender(rawTreeData, 0, true);

        $('#treeTableWrapper').show();
        $('#treeFooterBar').removeClass('d-none').addClass('d-flex');
        $('#footerInfoText').text(`Showing ${totalNodesCount} branches across organizational hierarchy`);
    }

    // ── Toggle Single Node Expand / Collapse ─────────────────────────────────
    function toggleNode(nodeKey, event) {
        if (event && (event.target.closest('a') || event.target.tagName === 'INPUT')) {
            return;
        }

        if (openNodesSet.has(nodeKey)) {
            openNodesSet.delete(nodeKey);
        } else {
            openNodesSet.add(nodeKey);
        }

        updateRowVisibilities();
    }

    // ── Update row visibilities based on openNodesSet ─────────────────────────
    function updateRowVisibilities() {
        const rows = $('#treeTableBody tr.tree-row');

        rows.each(function() {
            const tr = $(this);
            const key = tr.attr('data-key');
            if (!key) return;

            const segments = key.split('/').filter(Boolean);
            let shouldBeVisible = true;

            let parentPath = '';
            for (let i = 0; i < segments.length - 1; i++) {
                parentPath += '/' + segments[i];
                if (!openNodesSet.has(parentPath)) {
                    shouldBeVisible = false;
                    break;
                }
            }

            if (shouldBeVisible) {
                tr.show();
            } else {
                tr.hide();
            }

            const isNodeOpen = openNodesSet.has(key);
            if (isNodeOpen) {
                tr.addClass('expanded');
                tr.find('.ri-add-line').removeClass('ri-add-line').addClass('ri-subtract-line');
            } else {
                tr.removeClass('expanded');
                tr.find('.ri-subtract-line').removeClass('ri-subtract-line').addClass('ri-add-line');
            }
        });
    }

    // ── Expand All Nodes ─────────────────────────────────────────────────────
    function expandAllNodes() {
        function addKeys(nodes) {
            nodes.forEach(node => {
                openNodesSet.add(node.key);
                if (node.children && node.children.length > 0) {
                    addKeys(node.children);
                }
            });
        }
        addKeys(rawTreeData);
        updateRowVisibilities();
    }

    // ── Collapse All Nodes ───────────────────────────────────────────────────
    function collapseAllNodes() {
        openNodesSet.clear();
        updateRowVisibilities();
    }

    // ── Live Filter Table by Text ────────────────────────────────────────────
    function filterTreeTable(query) {
        query = (query || '').trim().toLowerCase();
        const rows = $('#treeTableBody tr.tree-row');

        if (!query) {
            // Restore regular hierarchical visibility
            rows.find('.tree-node-title').each(function() {
                const titleSpan = $(this);
                titleSpan.html(titleSpan.text()); // Clear marks
            });
            updateRowVisibilities();
            return;
        }

        // Find all matching keys and their ancestor paths
        const matchedKeys = new Set();
        const ancestorKeys = new Set();

        rows.each(function() {
            const tr = $(this);
            const name = (tr.attr('data-name') || '').toLowerCase();
            const key  = tr.attr('data-key') || '';

            if (name.includes(query)) {
                matchedKeys.add(key);

                // Add all ancestors
                const segments = key.split('/').filter(Boolean);
                let path = '';
                for (let i = 0; i < segments.length - 1; i++) {
                    path += '/' + segments[i];
                    ancestorKeys.add(path);
                }
            }
        });

        // Show matching rows and their parents
        rows.each(function() {
            const tr = $(this);
            const key = tr.attr('data-key');
            const titleSpan = tr.find('.tree-node-title');
            const originalText = tr.attr('data-name') || '';

            if (matchedKeys.has(key) || ancestorKeys.has(key)) {
                tr.show();

                if (matchedKeys.has(key)) {
                    // Highlight matching text
                    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
                    const highlighted = originalText.replace(regex, '<mark>$1</mark>');
                    titleSpan.html(highlighted);
                } else {
                    titleSpan.text(originalText);
                }
            } else {
                tr.hide();
                titleSpan.text(originalText);
            }
        });
    }

    // ── Export CSV Handler ───────────────────────────────────────────────────
    function exportData(format = 'excel') {
        const yearId = $('#yearSelect').val() || '{{ $currentYearId ?? session('year_id', 7) }}';
        const month  = $('#monthSelect').val() || '';
        let url = `${EXPORT_URL}?year_id=${encodeURIComponent(yearId)}&company_id={{ $companyId ?? session('company_id', 1) }}&format=${encodeURIComponent(format)}`;
        if (month) {
            url += `&month=${encodeURIComponent(month)}`;
        }

        if (typeof window.getOrganogramFilterQueryParams === 'function') {
            const extraParams = window.getOrganogramFilterQueryParams();
            if (extraParams) {
                url += extraParams; // Starts with &
            }
        }

        window.location.href = url;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    function getLevelIcon(levelType) {
        switch (levelType) {
            case 'function':   return 'ri-building-line';
            case 'vertical':   return 'ri-stack-line';
            case 'department': return 'ri-folder-shared-line';
            case 'subdept':    return 'ri-folder-3-line';
            case 'bu':         return 'ri-briefcase-4-line';
            case 'zone':       return 'ri-map-pin-2-line';
            case 'region':     return 'ri-compass-3-line';
            case 'territory':  return 'ri-route-line';
            case 'hq':         return 'ri-government-line';
            default:           return 'ri-node-tree';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
</script>
@endpush
