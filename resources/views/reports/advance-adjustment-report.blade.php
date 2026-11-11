@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Top Title & Action Bar --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="ri-wallet-3-line me-2 text-primary"></i>Advance Request & Adjustment Report
                </h4>
                <p class="text-muted mb-0" style="font-size:13px;">
                    Track all advance requests, disbursement statuses, adjustments against expense claims, and outstanding balances.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Ledger Summary Button --}}
                <a href="{{ route('expense-payments.ledger-summary') }}" target="_blank" class="btn btn-sm btn-info text-white shadow-sm" title="View Company-Wide Employee Ledger Summary">
                    <i class="ri-book-2-line me-1"></i> Ledger Summary
                </a>

                {{-- More Filters Drawer Button --}}
                <button type="button" class="btn btn-sm btn-outline-primary position-relative shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                    <i class="ri-equalizer-line me-1"></i> More Filters
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ !empty($filters['function_ids']) || !empty($filters['vertical_ids']) || !empty($filters['department_ids']) || !empty($filters['sub_department_ids']) || !empty($filters['bu_ids']) || !empty($filters['zone_ids']) || !empty($filters['region_ids']) || !empty($filters['territory_ids']) || !empty($filters['hq_ids']) ? '' : 'd-none' }}" id="organogramFilterCountBadge">
                        {{ count(array_filter([
                            !empty($filters['function_ids']), !empty($filters['vertical_ids']), !empty($filters['department_ids']),
                            !empty($filters['sub_department_ids']), !empty($filters['bu_ids']), !empty($filters['zone_ids']),
                            !empty($filters['region_ids']), !empty($filters['territory_ids']), !empty($filters['hq_ids'])
                        ])) }}
                    </span>
                </button>

                {{-- Excel Export Button --}}
                <a href="{{ route('reports.advance-adjustment.export', request()->query()) }}" class="btn btn-sm btn-success shadow-sm" title="Download Complete Filtered Report as Excel .xlsx">
                    <i class="ri-file-excel-2-line me-1"></i> Export Excel
                </a>
            </div>
        </div>
    </div>

    {{-- Organogram Drawer --}}
    @include('partials.organogram-filters-drawer')

    {{-- Employee Ledger Reconciliation Banner (Shown when searching an employee) --}}
    @if(!empty($employeeLedgerSummary))
        <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 py-2 px-3">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-xs flex-shrink-0">
                    <span class="avatar-title bg-info text-white rounded-circle fs-6">
                        <i class="ri-scales-3-line"></i>
                    </span>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark">
                        Authoritative Ledger Reconciliation: {{ $employeeLedgerSummary['employee_name'] }} 
                        <span class="badge bg-primary ms-1">Emp Code: {{ $employeeLedgerSummary['emp_code'] }}</span>
                    </h6>
                    <div class="text-muted small" style="font-size:11.5px;">
                        Fixed Imprest: <strong class="text-info">₹{{ number_format($employeeLedgerSummary['imprest'], 2) }}</strong> • 
                        Advance Balance: <strong class="text-primary">₹{{ number_format($employeeLedgerSummary['adv_balance'], 2) }}</strong> • 
                        Total Net Ledger Balance: <strong class="text-success">₹{{ number_format($employeeLedgerSummary['net_balance'], 2) }}</strong>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-1">
                <a href="{{ route('expense-payments.ledger-summary') }}?search={{ $employeeLedgerSummary['emp_code'] }}" target="_blank" class="btn btn-sm btn-info text-white shadow-sm" style="font-size:11px;">
                    <i class="ri-book-2-line me-1"></i> Full Ledger Summary
                </a>
                <a href="{{ url('/account-summary') }}?e={{ $employeeLedgerSummary['employee_id'] }}&y={{ $filters['year_id'] ?? 8 }}" target="_blank" class="btn btn-sm btn-outline-primary bg-white shadow-sm" style="font-size:11px;">
                    <i class="ri-file-list-3-line me-1"></i> Complete Account Summary
                </a>
            </div>
        </div>
    @endif

    {{-- KPI Summary Cards (Interactive Drill-Down) --}}
    <div class="row g-2 mb-3">
        {{-- Total Disbursed --}}
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-primary border-4 shadow-sm h-100 mb-0 kpi-card"
                 onclick="applyQuickStatusFilter('Paid', 'all')"
                 style="cursor:pointer;"
                 title="Click to view all Paid Advances">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted mb-1" style="font-size:11px;">Total Disbursed Advances</p>
                            <h4 class="mb-0 fw-bold text-primary">₹{{ number_format($kpis['total_disbursed_amt'] ?? 0, 0) }}</h4>
                            <small class="text-muted" style="font-size:11px;">
                                {{ number_format($kpis['paid_advances_count'] ?? 0) }} paid advance(s)
                            </small>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3">
                                <i class="ri-money-dollar-circle-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Adjusted --}}
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-success border-4 shadow-sm h-100 mb-0 kpi-card"
                 onclick="applyQuickStatusFilter('', 'fully_adjusted')"
                 style="cursor:pointer;"
                 title="Click to view Fully Adjusted Advances">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted mb-1" style="font-size:11px;">Total Amount Adjusted</p>
                            <h4 class="mb-0 fw-bold text-success">₹{{ number_format($kpis['total_adjusted_amt'] ?? 0, 0) }}</h4>
                            <small class="text-success fw-semibold" style="font-size:11px;">
                                <i class="ri-checkbox-circle-line me-1"></i>{{ $kpis['adjusted_percentage'] ?? 0 }}% settled against claims
                            </small>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                <i class="ri-check-double-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Unadjusted Balance --}}
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-danger border-4 shadow-sm h-100 mb-0 kpi-card"
                 onclick="applyQuickStatusFilter('', 'unadjusted')"
                 style="cursor:pointer;"
                 title="Click to view Completely Unadjusted Advances">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted mb-1" style="font-size:11px;">Total Unadjusted Balance</p>
                            <h4 class="mb-0 fw-bold text-danger">₹{{ number_format($kpis['total_unadjusted_amt'] ?? 0, 0) }}</h4>
                            <small class="text-danger fw-semibold" style="font-size:11px;">
                                <i class="ri-time-line me-1"></i>{{ $kpis['unadjusted_percentage'] ?? 0 }}% remaining to be adjusted
                            </small>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3">
                                <i class="ri-alert-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Breakdown Counts & Aging Alert --}}
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-warning border-4 shadow-sm h-100 mb-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-success" style="font-size:10px; cursor:pointer;" onclick="applyQuickStatusFilter('', 'fully_adjusted')" title="Click to filter Fully Adjusted">
                            {{ $kpis['fully_adjusted_count'] ?? 0 }} Fully Adjusted
                        </span>
                        <span class="badge bg-warning text-dark" style="font-size:10px; cursor:pointer;" onclick="applyQuickStatusFilter('', 'partially_adjusted')" title="Click to filter Partially Adjusted">
                            {{ $kpis['partially_adjusted_count'] ?? 0 }} Partial
                        </span>
                        <span class="badge bg-danger" style="font-size:10px; cursor:pointer;" onclick="applyQuickStatusFilter('', 'unadjusted')" title="Click to filter Completely Unadjusted">
                            {{ $kpis['unadjusted_count'] ?? 0 }} Unadjusted
                        </span>
                    </div>
                    <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center">
                        <span class="text-danger fw-bold" style="font-size:11px;">
                            <i class="ri-alarm-warning-line me-1"></i>Over 2 Months Old:
                        </span>
                        <span class="badge bg-danger-subtle text-danger border border-danger fw-bold"
                              style="font-size:11px; cursor:pointer;"
                              onclick="applyQuickStatusFilter('', 'over_2_months')"
                              title="Click to view advances older than 60 days">
                            {{ $kpis['over_2_months_count'] ?? 0 }} records ⚠️
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Filter Panel --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body p-3 bg-light rounded-3">
            <form method="GET" action="{{ route('reports.advance-adjustment') }}" id="reportFilterForm">
                {{-- Container for organogram hidden inputs --}}
                <div id="organogramHiddenInputs">
                    @foreach(['function_ids', 'vertical_ids', 'department_ids', 'sub_department_ids', 'bu_ids', 'zone_ids', 'region_ids', 'territory_ids', 'hq_ids', 'policy_ids'] as $fKey)
                        @if(!empty($filters[$fKey]))
                            @foreach((array)$filters[$fKey] as $fVal)
                                <input type="hidden" name="{{ $fKey }}[]" value="{{ $fVal }}" class="organogram-hidden-input">
                            @endforeach
                        @endif
                    @endforeach
                </div>

                <div class="row g-2 align-items-end">

                    {{-- Financial Year --}}
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Financial Year</label>
                        <select name="year_id" id="filterYearId" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" {{ ($filters['year_id'] === null) ? 'selected' : '' }}>-- All Years --</option>
                            @foreach($financialYears as $fy)
                                <option value="{{ $fy->YearId }}" {{ ($filters['year_id'] == $fy->YearId) ? 'selected' : '' }}>
                                    {{ $fy->Year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Request Month (Select2 Multi-Select) --}}
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Request Month</label>
                        @php
                            $monthList = [
                                4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
                                10 => 'Oct', 11 => 'Nov', 12 => 'Dec', 1 => 'Jan', 2 => 'Feb', 3 => 'Mar'
                            ];
                        @endphp
                        <select name="months[]" class="form-select form-select-sm" multiple id="monthMultiSelect" data-placeholder="All Months">
                            @foreach($monthList as $mNum => $mLabel)
                                <option value="{{ $mNum }}" {{ in_array($mNum, $filters['months'] ?? []) ? 'selected' : '' }}>
                                    {{ $mLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Adjustment Status --}}
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Adjustment Status</label>
                        <select name="adjustment_status" id="filterAdjStatus" class="form-select form-select-sm">
                            <option value="all" {{ ($filters['adjustment_status'] === 'all') ? 'selected' : '' }}>All Statuses</option>
                            <option value="fully_adjusted" {{ ($filters['adjustment_status'] === 'fully_adjusted') ? 'selected' : '' }}>Fully Adjusted</option>
                            <option value="partially_adjusted" {{ ($filters['adjustment_status'] === 'partially_adjusted') ? 'selected' : '' }}>Partially Adjusted</option>
                            <option value="unadjusted" {{ ($filters['adjustment_status'] === 'unadjusted') ? 'selected' : '' }}>Completely Unadjusted</option>
                            <option value="over_2_months" {{ ($filters['adjustment_status'] === 'over_2_months') ? 'selected' : '' }}>⚠️ Over 2 Months Old (>60d)</option>
                            <option value="pending_payment" {{ ($filters['adjustment_status'] === 'pending_payment') ? 'selected' : '' }}>Pending Payment</option>
                        </select>
                    </div>

                    {{-- Payment Status --}}
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Payment Status</label>
                        <select name="payment_status" id="filterPayStatus" class="form-select form-select-sm">
                            <option value="all" {{ ($filters['payment_status'] === 'all') ? 'selected' : '' }}>All</option>
                            <option value="Paid" {{ ($filters['payment_status'] === 'Paid') ? 'selected' : '' }}>Paid</option>
                            <option value="Unsettled" {{ ($filters['payment_status'] === 'Unsettled') ? 'selected' : '' }}>Unsettled</option>
                            <option value="Pending" {{ ($filters['payment_status'] === 'Pending') ? 'selected' : '' }}>Pending / Unpaid</option>
                        </select>
                    </div>

                    {{-- Activity Type --}}
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Activity Type</label>
                        <select name="activity_type_id" class="form-select form-select-sm">
                            <option value="">All Activity Types</option>
                            @foreach($activityTypes as $at)
                                <option value="{{ $at->id }}" {{ in_array($at->id, (array)($filters['activity_type_ids'] ?? [])) ? 'selected' : '' }}>
                                    {{ $at->type_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Advance Category (Regular vs Imprest) --}}
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Advance Type</label>
                        <select name="is_imprest" class="form-select form-select-sm">
                            <option value="all" {{ ($filters['is_imprest'] === 'all') ? 'selected' : '' }}>All Advances</option>
                            <option value="regular" {{ ($filters['is_imprest'] === 'regular') ? 'selected' : '' }}>Activity Advances (Regular)</option>
                            <option value="imprest" {{ ($filters['is_imprest'] === 'imprest') ? 'selected' : '' }}>Fixed Imprest Advances</option>
                        </select>
                    </div>

                    {{-- Search Input --}}
                    <div class="col-md-4 col-sm-12 mt-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Search Employee / Purpose / UTR / Act ID</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by Emp Code, Name, Purpose, UTR..." aria-label="Search by Emp Code, Name, Purpose, UTR" value="{{ $filters['search'] ?? '' }}">
                        </div>
                    </div>

                    {{-- Payment Date Range --}}
                    <div class="col-md-2 col-sm-6 mt-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Payment Date From</label>
                        <input type="date" name="pay_date_from" class="form-control form-control-sm" value="{{ $filters['pay_date_from'] ?? '' }}">
                    </div>

                    <div class="col-md-2 col-sm-6 mt-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Payment Date To</label>
                        <input type="date" name="pay_date_to" class="form-control form-control-sm" value="{{ $filters['pay_date_to'] ?? '' }}">
                    </div>

                    {{-- Per Page --}}
                    <div class="col-md-1 col-sm-4 mt-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Show</label>
                        <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="25" {{ ($filters['per_page'] == 25) ? 'selected' : '' }}>25</option>
                            <option value="50" {{ ($filters['per_page'] == 50) ? 'selected' : '' }}>50</option>
                            <option value="100" {{ ($filters['per_page'] == 100) ? 'selected' : '' }}>100</option>
                            <option value="250" {{ ($filters['per_page'] == 250) ? 'selected' : '' }}>250</option>
                            <option value="all" {{ ($filters['per_page'] === 'all') ? 'selected' : '' }}>All</option>
                        </select>
                    </div>

                    {{-- Submit & Reset Buttons --}}
                    <div class="col-md-3 col-sm-8 mt-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 shadow-sm">
                            <i class="ri-filter-2-line me-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('reports.advance-adjustment') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-refresh-line"></i> Reset
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- Data Table Card --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="ri-list-check-2 me-1 text-primary"></i> Advance Activities & Adjustment Records
            </h6>
            <div class="text-muted small">
                Showing <strong>{{ $records->firstItem() ?? 0 }}</strong> to <strong>{{ $records->lastItem() ?? 0 }}</strong> of <strong>{{ $records->total() }}</strong> records
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped align-middle mb-0" style="font-size:11.5px;">
                    <thead class="table-light text-center" style="font-size:11px; white-space:nowrap;">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="width: 90px;">Req / Act ID</th>
                            <th>Employee Details</th>
                            <th>Advance Details</th>
                            <th>Purpose / Activity</th>
                            <th style="width: 105px;">Paid Amount</th>
                            <th style="width: 105px;">Adjusted Amount</th>
                            <th style="width: 105px;">Unadjusted Balance</th>
                            <th style="width: 120px;">Adjustment Status</th>
                            <th>Adjustment Details</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $pageTotalPaid = 0;
                            $pageTotalAdj  = 0;
                            $pageTotalRem  = 0;
                        @endphp
                        @forelse($records as $index => $row)
                            @php
                                $pageTotalPaid += (float) ($row->computed_paid_amt ?? 0);
                                $pageTotalAdj  += (float) ($row->computed_adjusted_amt ?? 0);
                                $pageTotalRem  += (float) ($row->computed_remaining_balance ?? 0);

                                $monthNames = [
                                    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
                                    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                                ];
                                $reqMonthName = isset($monthNames[$row->request_month]) ? $monthNames[$row->request_month] : 'M-'.$row->request_month;
                                // ADV-HL-01: used up in claims, but no adjustment recorded on the advance
                                $needsAdjEntry = strtolower(trim($row->payment_status ?? '')) === 'paid'
                                    && (float) ($row->reconciled_remaining ?? 1) <= 0 && (float) ($row->reconciled_paid ?? $row->payment_amount ?? 0) > 0
                                    && empty($row->stored_adjustment_date);
                            @endphp
                            <tr @if($needsAdjEntry) class="table-warning" title="Fully used in claims but no adjustment date recorded: payment section to record the adjustment" @endif>
                                {{-- Index --}}
                                <td class="text-center text-muted">
                                    {{ ($records->firstItem() ?? 1) + $index }}
                                </td>

                                {{-- IDs --}}
                                <td class="text-center">
                                    <div class="fw-bold text-primary">Act #{{ $row->activity_id }}</div>
                                    <div class="text-muted small" style="font-size:10px;">Req #{{ $row->request_id }}</div>
                                </td>

                                {{-- Employee Details --}}
                                <td>
                                    <div class="fw-bold text-dark">{{ $row->emp_name }}</div>
                                    <div class="text-muted small" style="font-size:10px;">
                                        <span class="badge bg-light text-dark border me-1">{{ $row->emp_code }}</span>
                                        @if(!empty($row->hq_name))
                                            <span><i class="ri-map-pin-line me-1"></i>{{ $row->hq_name }}</span>
                                        @endif
                                        @if(!empty($row->department_name))
                                            <span class="ms-1 text-secondary">({{ $row->department_name }})</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Advance Details --}}
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 160px;" title="{{ $row->activity_type ?? $row->activity_name }}">
                                        {{ $row->activity_type ?? $row->activity_name ?? 'Advance' }}
                                    </div>
                                    <div class="text-muted small" style="font-size:10px;">
                                        <span>FY: {{ $row->financial_year ?? '2026-2027' }}</span> • 
                                        <span>Month: {{ $reqMonthName }}</span>
                                    </div>
                                    @if(!empty($row->is_imprest_flag))
                                        <span class="badge bg-info-subtle text-info border border-info" style="font-size:9.5px; padding:1px 4px;">Imprest</span>
                                    @endif
                                </td>

                                {{-- Purpose & Disbursement Info --}}
                                <td>
                                    <div class="text-truncate" style="max-width: 220px;" title="{{ $row->purpose }}">
                                        {{ $row->purpose ?: '-' }}
                                    </div>
                                    <div class="text-muted small" style="font-size:10px;">
                                        @if(!empty($row->payment_date))
                                            <span>Disbursed: {{ date('d M Y', strtotime($row->payment_date)) }}</span>
                                            @if(!empty($row->utr_number))
                                                • <span title="UTR / Cheque: {{ $row->utr_number }}">UTR: {{ Str::limit($row->utr_number, 12) }}</span>
                                            @endif
                                        @else
                                            <span class="text-warning">Payment Pending</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Paid Amount --}}
                                <td class="text-end">
                                    <div class="fw-bold text-dark">
                                        ₹{{ number_format(($row->payment_status === 'Paid') ? ($row->computed_paid_amount ?? $row->computed_paid_amt ?? 0) : ($row->payment_amount ?: $row->requested_amount ?: 0), 2) }}
                                    </div>
                                    <span class="badge {{ ($row->payment_status ?? '') === 'Paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}" style="font-size:9.5px;">
                                        {{ $row->payment_status ?? 'Pending' }}
                                    </span>
                                </td>

                                {{-- Adjusted Amount --}}
                                <td class="text-end">
                                    @php
                                        $adjDisplay = (float)($row->computed_adjusted_amount ?? $row->computed_adjusted_amt ?? 0);
                                        $remDisplay = (float)($row->computed_remaining_balance ?? 0);
                                        $paidDisplay = (float)($row->computed_paid_amount ?? $row->computed_paid_amt ?? 0);
                                    @endphp
                                    @if($adjDisplay > 0)
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold"
                                              style="font-size:11px; cursor:pointer;"
                                              onclick="showAdjustmentModal({{ json_encode([
                                                  'act_id' => $row->activity_id,
                                                  'req_id' => $row->request_id,
                                                  'emp_name' => $row->emp_name,
                                                  'emp_code' => $row->emp_code,
                                                  'paid_amt' => number_format($paidDisplay, 2),
                                                  'adj_amt' => number_format($adjDisplay, 2),
                                                  'rem_bal' => number_format($remDisplay, 2),
                                                  'adj_month' => !empty($row->adjusted_against_month) ? (isset($monthNames[$row->adjusted_against_month]) ? $monthNames[$row->adjusted_against_month] : 'M-'.$row->adjusted_against_month) : null,
                                                  'adj_date' => !empty($row->adjustment_date) ? date('d M Y, h:i A', strtotime($row->adjustment_date)) : null,
                                                  'remarks' => $row->adjustment_remarks ?? $row->payment_remark ?? '',
                                                  'utr' => $row->utr_number ?? '',
                                                  'purpose' => $row->purpose ?? ''
                                              ]) }})"
                                              title="Click to see adjustment breakdown">
                                            ₹{{ number_format($adjDisplay, 2) }} <i class="ri-information-line ms-1"></i>
                                        </span>
                                    @else
                                        <span class="text-muted">₹0</span>
                                    @endif
                                </td>

                                {{-- Unadjusted Balance --}}
                                <td class="text-end">
                                    @if(($row->payment_status ?? '') === 'Paid')
                                        <span class="fw-bold {{ $remDisplay > 0 ? 'text-danger' : 'text-success' }}">
                                            ₹{{ number_format($remDisplay, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted small" title="Payment Pending — funds not yet disbursed">
                                            -
                                        </span>
                                    @endif
                                </td>

                                {{-- Adjustment Status Badge & Age --}}
                                <td class="text-center">
                                    @if(!empty($row->is_over_2_months))
                                        <span class="badge bg-danger text-white border border-danger fw-bold shadow-sm d-inline-block mb-1" style="font-size:10px;">
                                            <i class="ri-error-warning-line me-1"></i>Over 2 Months
                                        </span>
                                    @else
                                        <span class="badge {{ $row->status_badge_class ?? $row->adjustment_status_badge ?? 'bg-secondary' }} fw-semibold d-inline-block mb-1" style="font-size:10px;">
                                            {{ $row->status_label ?? $row->adjustment_status_label ?? 'Pending' }}
                                        </span>
                                    @endif

                                    @if(!empty($row->age_in_days) && $remDisplay > 0)
                                        <div class="text-muted small" style="font-size:9.5px;">
                                            Age: <strong>{{ $row->age_in_days ?? $row->age_days }}</strong> days
                                        </div>
                                    @endif
                                </td>

                                {{-- Adjustment Details --}}
                                <td>
                                    @if($row->computed_adjusted_amt > 0)
                                        @if($row->adjusted_against_month)
                                            <div class="fw-semibold text-primary" style="font-size:10.5px;">
                                                <i class="ri-arrow-right-circle-line me-1"></i>Adj in Month: {{ isset($monthNames[$row->adjusted_against_month]) ? $monthNames[$row->adjusted_against_month] : 'M-'.$row->adjusted_against_month }}
                                            </div>
                                        @endif
                                        @if($row->adjustment_date)
                                            <div class="text-muted small" style="font-size:10px;">
                                                Date: {{ date('d M Y', strtotime($row->adjustment_date)) }}
                                            </div>
                                        @endif
                                        @if($needsAdjEntry)
                                            <span class="badge bg-warning text-dark" style="font-size:10px;">Adjustment not recorded - payment section</span>
                                        @endif
                                        @if(!empty($row->adjustment_remarks))
                                            <div class="text-muted fst-italic text-truncate" style="max-width:180px;" title="{{ $row->adjustment_remarks }}">
                                                {{ $row->adjustment_remarks }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="text-center" style="white-space:nowrap;">
                                    <div class="d-flex flex-column gap-1 align-items-center">
                                        {{-- Account Summary (Loads complete year without month-bounding) --}}
                                        <a href="{{ url('/account-summary') }}?e={{ $row->employee_id }}&y={{ $row->year_id ?? 8 }}"
                                           target="_blank"
                                           class="btn btn-xs btn-outline-primary w-100"
                                           title="Open Complete Employee Account Summary across all months"
                                           style="font-size:10px; padding:2px 6px;">
                                            <i class="ri-file-list-3-line me-1"></i> Account Summary
                                        </a>

                                        {{-- Ledger Summary --}}
                                        <a href="{{ route('expense-payments.ledger-summary') }}?search={{ $row->emp_code }}"
                                           target="_blank"
                                           class="btn btn-xs btn-outline-info w-100"
                                           title="Open Employee Ledger Summary in new tab"
                                           style="font-size:10px; padding:2px 6px;">
                                            <i class="ri-book-2-line me-1"></i> Ledger Summary
                                        </a>

                                        {{-- Quick Ledger Modal Button --}}
                                        <button type="button"
                                                class="btn btn-xs btn-soft-secondary w-100 btn-quick-ledger"
                                                data-emp-id="{{ $row->employee_id }}"
                                                data-emp-name="{{ $row->emp_name }}"
                                                data-emp-code="{{ $row->emp_code }}"
                                                title="Quick preview ledger balances in modal"
                                                style="font-size:9.5px; padding:1px 5px;">
                                            <i class="ri-eye-line me-1"></i> Quick View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="ri-inbox-line fs-1 text-muted d-block mb-1"></i>
                                    No advance records found matching the selected filter criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($records->count() > 0)
                        <tfoot class="table-light fw-bold" style="font-size:11px;">
                            <tr class="border-top border-2">
                                <td colspan="5" class="text-end">Page Subtotal ({{ $records->count() }} records):</td>
                                <td class="text-end text-dark">₹{{ number_format($pageTotalPaid, 2) }}</td>
                                <td class="text-end text-success">₹{{ number_format($pageTotalAdj, 2) }}</td>
                                <td class="text-end text-danger">₹{{ number_format($pageTotalRem, 2) }}</td>
                                <td colspan="3" class="text-muted small">
                                    Showing page {{ $records->currentPage() }} of {{ $records->lastPage() }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Pagination Footer --}}
        @if($records instanceof \Illuminate\Pagination\LengthAwarePaginator && $records->hasPages())
            <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} entries
                </div>
                <div>
                    {{ $records->appends(request()->query())->links('pagination::bootstrap-4') }}
                </div>
            </div>
        @endif
    </div>

</div>
</div>

{{-- Adjustment Details Modal --}}
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-labelledby="adjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title text-white mb-0" id="adjustmentModalLabel">
                    <i class="ri-check-double-line me-1"></i> Advance Settlement & Adjustment Details
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" style="font-size:12px;">
                <table class="table table-sm table-bordered mb-0">
                    <tbody>
                        <tr>
                            <th class="bg-light" style="width: 40%;">Advance Reference</th>
                            <td><span id="modalAdvRef" class="fw-bold text-primary"></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Employee</th>
                            <td><span id="modalEmpInfo" class="fw-semibold"></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Advance Purpose</th>
                            <td id="modalPurpose"></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Original Paid Amount</th>
                            <td><span id="modalPaidAmt" class="fw-bold text-dark"></span></td>
                        </tr>
                        <tr class="table-success">
                            <th class="fw-bold text-success">Total Amount Adjusted</th>
                            <td><span id="modalAdjAmt" class="fw-bold text-success fs-6"></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Remaining Unadjusted</th>
                            <td><span id="modalRemBal" class="fw-bold text-danger"></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Adjusted Against Month</th>
                            <td><span id="modalAdjMonth" class="badge bg-primary"></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Adjustment Date</th>
                            <td id="modalAdjDate"></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Adjustment Remarks</th>
                            <td id="modalRemarks" class="fst-italic"></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Bank UTR / Cheque</th>
                            <td id="modalUtr"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer py-1 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Quick Ledger Summary Modal --}}
<div class="modal fade" id="quickLedgerModal" tabindex="-1" aria-labelledby="quickLedgerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-info text-white py-2">
                <h6 class="modal-title text-white mb-0" id="quickLedgerModalLabel">
                    <i class="ri-book-2-line me-1"></i> Employee Ledger Summary & Balances
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" id="quickLedgerModalBody" style="font-size:12px;">
                <div class="text-center py-4" id="ledgerLoadingSpinner">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted mt-2">Fetching ledger details from HRIMS & Expense DB...</div>
                </div>

                <div id="ledgerContent" style="display:none;">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <div class="text-muted small">Net Ledger Balance</div>
                                <div class="fs-6 fw-bold text-primary" id="qlNetBalance">₹0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <div class="text-muted small">Fixed Imprest</div>
                                <div class="fs-6 fw-bold text-info" id="qlTotalImprest">₹0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <div class="text-muted small">Paid Advances</div>
                                <div class="fs-6 fw-bold text-dark" id="qlTotalPaid">₹0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <div class="text-muted small">Carried Advances</div>
                                <div class="fs-6 fw-bold text-danger" id="qlTotalCarried">₹0</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2 text-dark"><i class="ri-list-check me-1"></i> Ledger Entries Breakdown</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="font-size:11px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Particulars</th>
                                    <th class="text-end">Original Amount</th>
                                    <th class="text-end">Adjustable Balance</th>
                                </tr>
                            </thead>
                            <tbody id="qlEntriesTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <div>
                    <a href="#" id="qlFullLedgerBtn" target="_blank" class="btn btn-sm btn-outline-info">
                        <i class="ri-external-link-line me-1"></i> Full Ledger Summary Page
                    </a>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* Prevent Laravel unconstrained SVG pagination arrows */
    .pagination svg,
    nav[role="navigation"] svg,
    .page-item svg {
        width: 14px !important;
        height: 14px !important;
        max-width: 14px !important;
        max-height: 14px !important;
        display: inline-block !important;
        vertical-align: middle !important;
    }
    .pagination {
        margin-bottom: 0 !important;
    }
    .page-item .page-link {
        font-size: 12px;
        padding: 4px 10px;
    }

    /* Styling for Drawer and Select2 */
    #additionalFiltersCanvas {
        width: 540px;
        max-width: 95vw;
    }
    #additionalFiltersCanvas .select2-container {
        width: 100% !important;
    }
    #additionalFiltersCanvas .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 6px;
        min-height: 38px;
        background-color: #ffffff;
        padding: 2px 4px;
    }
    #additionalFiltersCanvas .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #00175f;
        box-shadow: 0 0 0 0.15rem rgba(0, 23, 95, 0.15);
    }
    #additionalFiltersCanvas .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #e0e7ff;
        border: 1px solid #c7d2fe;
        color: #1e1b4b;
        border-radius: 4px;
        padding: 1px 7px;
        font-size: 11px;
        font-weight: 500;
        margin-top: 4px;
    }
    #additionalFiltersCanvas .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #4338ca;
        margin-right: 4px;
    }
    #additionalFiltersCanvas .select2-dropdown {
        border-radius: 6px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        border-color: #cbd5e1;
        z-index: 1065 !important;
    }

    /* Month MultiSelect styling */
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 4px;
        min-height: 31px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #00175f;
        color: #ffffff;
        border: none;
        border-radius: 3px;
        padding: 0 5px;
        font-size: 10.5px;
        margin-top: 3px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #ffffff;
        margin-right: 4px;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 6px 16px rgba(0,0,0,0.1) !important;
    }
</style>
@endpush

@push('scripts')
{{-- Include Reusable Organogram Cascading JavaScript --}}
@include('partials.organogram-filters-js')

<script>
// Quick status filter helper
function applyQuickStatusFilter(paymentStatus, adjStatus) {
    if (paymentStatus) {
        document.getElementById('filterPayStatus').value = paymentStatus;
    }
    if (adjStatus) {
        document.getElementById('filterAdjStatus').value = adjStatus;
    }
    document.getElementById('reportFilterForm').submit();
}

// Show adjustment details in modal
function showAdjustmentModal(data) {
    document.getElementById('modalAdvRef').innerText = 'Act #' + data.act_id + ' (Req #' + data.req_id + ')';
    document.getElementById('modalEmpInfo').innerText = data.emp_name + ' (' + data.emp_code + ')';
    document.getElementById('modalPurpose').innerText = data.purpose || '-';
    document.getElementById('modalPaidAmt').innerText = '₹' + data.paid_amt;
    document.getElementById('modalAdjAmt').innerText = '₹' + data.adj_amt;
    document.getElementById('modalRemBal').innerText = '₹' + data.rem_bal;
    document.getElementById('modalAdjMonth').innerText = data.adj_month ? 'Month: ' + data.adj_month : 'Not specified';
    document.getElementById('modalAdjDate').innerText = data.adj_date || 'N/A';
    document.getElementById('modalRemarks').innerText = data.remarks || 'No remarks available';
    document.getElementById('modalUtr').innerText = data.utr || '-';

    var myModal = new bootstrap.Modal(document.getElementById('adjustmentModal'));
    myModal.show();
}

(function($) {
    'use strict';

    $(document).ready(function() {
        // 1. Initialize Request Month Select2
        if ($('#monthMultiSelect').length) {
            $('#monthMultiSelect').select2({
                placeholder: "All Months",
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });
        }

        // 2. Initialize Organogram Select2 Inside Offcanvas Drawer
        const organogramSelectors = [
            '#functionSelect', '#verticalSelect', '#departmentSelect', '#subDepartmentSelect',
            '#buSelect', '#zoneSelect', '#regionSelect', '#territorySelect', '#hqSelect', '#policySelect'
        ];

        function initDrawerSelect2() {
            organogramSelectors.forEach(function(sel) {
                const $el = $(sel);
                if ($el.length && !$el.hasClass('select2-hidden-accessible')) {
                    $el.select2({
                        dropdownParent: $('#additionalFiltersCanvas'),
                        placeholder: $el.data('placeholder') || 'Select options',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false
                    });
                }
            });
        }

        initDrawerSelect2();

        // Also re-init when offcanvas is shown to prevent zero-width calculation bugs
        $('#additionalFiltersCanvas').on('shown.bs.offcanvas', function () {
            initDrawerSelect2();
        });

        // 3. Pre-select active filters in drawer
        const organogramPreselected = {
            '#functionSelect': @json($filters['function_ids'] ?? []),
            '#verticalSelect': @json($filters['vertical_ids'] ?? []),
            '#departmentSelect': @json($filters['department_ids'] ?? []),
            '#subDepartmentSelect': @json($filters['sub_department_ids'] ?? []),
            '#buSelect': @json($filters['bu_ids'] ?? []),
            '#zoneSelect': @json($filters['zone_ids'] ?? []),
            '#regionSelect': @json($filters['region_ids'] ?? []),
            '#territorySelect': @json($filters['territory_ids'] ?? []),
            '#hqSelect': @json($filters['hq_ids'] ?? []),
            '#policySelect': @json($filters['policy_ids'] ?? [])
        };

        for (const [selector, vals] of Object.entries(organogramPreselected)) {
            if (Array.isArray(vals) && vals.length > 0) {
                $(selector).val(vals).trigger('change.select2');
            }
        }

        // 4. Handle Drawer Apply Filter button click
        window.onOrganogramFilterApply = function(vals) {
            const form = document.getElementById('reportFilterForm');
            const hiddenContainer = document.getElementById('organogramHiddenInputs');
            hiddenContainer.innerHTML = '';

            for (const [key, arr] of Object.entries(vals)) {
                if (Array.isArray(arr) && arr.length > 0) {
                    arr.forEach(function(val) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key + '[]';
                        input.value = val;
                        input.className = 'organogram-hidden-input';
                        hiddenContainer.appendChild(input);
                    });
                }
            }
            form.submit();
        };

        // 5. Handle Drawer Reset Filter button click
        window.onOrganogramFilterReset = function() {
            const hiddenContainer = document.getElementById('organogramHiddenInputs');
            hiddenContainer.innerHTML = '';
            document.getElementById('reportFilterForm').submit();
        };

        // 6. Quick Ledger Modal Handler via AJAX
        $(document).on('click', '.btn-quick-ledger', function() {
            const empId = $(this).data('emp-id');
            const empName = $(this).data('emp-name');
            const empCode = $(this).data('emp-code');

            $('#quickLedgerModalLabel').html('<i class="ri-book-2-line me-1"></i> Ledger Summary: ' + empName + ' (' + empCode + ')');
            $('#qlFullLedgerBtn').attr('href', "{{ route('expense-payments.ledger-summary') }}?search=" + encodeURIComponent(empCode));

            $('#ledgerLoadingSpinner').show();
            $('#ledgerContent').hide();

            var ledgerModal = new bootstrap.Modal(document.getElementById('quickLedgerModal'));
            ledgerModal.show();

            $.ajax({
                url: "{{ url('/get-employee-details') }}/" + empId,
                type: 'GET',
                dataType: 'json',
                success: function(resp) {
                    $('#ledgerLoadingSpinner').hide();
                    $('#ledgerContent').show();

                    const summary = resp.summary || {};
                    $('#qlNetBalance').text('₹' + Number(summary.net_balance || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
                    $('#qlTotalImprest').text('₹' + Number(summary.total_imprest || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
                    $('#qlTotalPaid').text('₹' + Number(summary.total_paid_advances || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
                    $('#qlTotalCarried').text('₹' + Number(summary.total_carried_advance || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));

                    const entries = resp.ledger_entries || [];
                    let rowsHtml = '';
                    if (entries.length > 0) {
                        entries.forEach(function(entry, idx) {
                            rowsHtml += `<tr>
                                <td class="text-center">${idx + 1}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">${entry.source || 'Advance'}</span></td>
                                <td>${entry.date || '-'}</td>
                                <td>${entry.purpose || entry.remarks || '-'}</td>
                                <td class="text-end fw-semibold">₹${Number(entry.original_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                                <td class="text-end fw-bold text-primary">₹${Number(entry.remaining_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                            </tr>`;
                        });
                    } else {
                        rowsHtml = `<tr><td colspan="6" class="text-center text-muted py-2">No active ledger advance entries found.</td></tr>`;
                    }
                    $('#qlEntriesTableBody').html(rowsHtml);
                },
                error: function() {
                    $('#ledgerLoadingSpinner').hide();
                    $('#ledgerContent').show();
                    $('#qlEntriesTableBody').html(`<tr><td colspan="6" class="text-center text-danger py-2">Could not load ledger entries. Please check network or visit full ledger page.</td></tr>`);
                }
            });
        });

    });
})(jQuery);
</script>
@endpush
