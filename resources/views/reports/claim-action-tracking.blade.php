@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="ri-user-follow-line me-2 text-primary"></i>Claim Action Tracking & Audit Report
                </h4>
                <p class="text-muted mb-0" style="font-size:13px;">
                    Comprehensive tracking of who claimed, filled, verified, approved, and financed claims with turnaround time (TAT) and employee-wise audit
                </p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                    <i class="ri-filter-3-line me-1"></i> Organogram Filters
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">
                        0
                    </span>
                </button>
                <a href="{{ route('reports.claim-action-log.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-file-list-3-line me-1"></i> Return & Submit Log
                </a>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ri-download-2-line me-1"></i> Export Excel
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <a class="dropdown-item" href="javascript:void(0);" onclick="triggerExport('claim_wise')">
                                <i class="ri-file-excel-line text-success me-2"></i> Export Claim-Wise Audit
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="javascript:void(0);" onclick="triggerExport('employee_summary')">
                                <i class="ri-file-user-line text-primary me-2"></i> Export Employee Monthly Summary
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @include('partials.organogram-filters-drawer')

    {{-- KPI Summary Cards --}}
    <div class="row g-2 mb-3" id="summaryCards">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-primary border-4 p-2 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase" style="font-size:10px; font-weight:600;">Total Claims</div>
                        <div class="fw-bold fs-5 text-primary" id="kpiTotalClaims">—</div>
                        <div class="text-muted" style="font-size:11px;" id="kpiTotalClaimedAmt">₹0</div>
                    </div>
                    <div class="rounded-circle bg-light p-2 text-primary">
                        <i class="ri-file-list-3-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-info border-4 p-2 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase" style="font-size:10px; font-weight:600;">Filled</div>
                        <div class="fw-bold fs-5 text-info" id="kpiFilledCount">—</div>
                        <div class="text-muted" style="font-size:11px;" id="kpiFilledAmt">₹0</div>
                    </div>
                    <div class="rounded-circle bg-light p-2 text-info">
                        <i class="ri-edit-2-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-warning border-4 p-2 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase" style="font-size:10px; font-weight:600;">Verified</div>
                        <div class="fw-bold fs-5 text-warning" id="kpiVerifiedCount">—</div>
                        <div class="text-muted" style="font-size:11px;" id="kpiVerifiedAmt">₹0</div>
                    </div>
                    <div class="rounded-circle bg-light p-2 text-warning">
                        <i class="ri-checkbox-circle-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-success border-4 p-2 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase" style="font-size:10px; font-weight:600;">Approved</div>
                        <div class="fw-bold fs-5 text-success" id="kpiApprovedCount">—</div>
                        <div class="text-muted" style="font-size:11px;" id="kpiApprovedAmt">₹0</div>
                    </div>
                    <div class="rounded-circle bg-light p-2 text-success">
                        <i class="ri-shield-check-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-dark border-4 p-2 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase" style="font-size:10px; font-weight:600;">Financed</div>
                        <div class="fw-bold fs-5 text-dark" id="kpiFinancedCount">—</div>
                        <div class="text-muted" style="font-size:11px;" id="kpiFinancedAmt">₹0</div>
                    </div>
                    <div class="rounded-circle bg-light p-2 text-dark">
                        <i class="ri-bank-card-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-secondary border-4 p-2 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase" style="font-size:10px; font-weight:600;">Unique People</div>
                        <div class="fw-bold fs-6 text-muted">
                            <span title="Claimants" class="text-primary" id="kpiClaimants">0</span> Claimants
                        </div>
                        <div class="text-muted" style="font-size:10px;">
                            <span class="text-warning" id="kpiVerifiers">0</span> Verifiers | <span class="text-success" id="kpiApprovers">0</span> Approvers
                        </div>
                    </div>
                    <div class="rounded-circle bg-light p-2 text-secondary">
                        <i class="ri-team-line fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-sm-4">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Financial Year</label>
                    <select class="form-select form-select-sm" id="filterYearId">
                        @foreach($financialYears as $fy)
                            <option value="{{ $fy->YearId }}" {{ $fy->YearId == $currentYearId ? 'selected' : '' }}>
                                {{ $fy->Year }} {{ $fy->Status === 'Active' ? '(Active)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Claim Month</label>
                    <select class="form-select form-select-sm" id="filterMonth">
                        <option value="">All Months</option>
                        @foreach($monthNames as $mNum => $mLabel)
                            <option value="{{ $mNum }}">{{ $mLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Stage / Status</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All Stages</option>
                        <option value="Draft">Draft</option>
                        <option value="Submitted">Submitted (Pending Fill)</option>
                        <option value="Filled">Filled</option>
                        <option value="Pending_Verification">Pending Verification</option>
                        <option value="Verified">Verified</option>
                        <option value="Pending_Approval">Pending Approval</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending_Finance">Pending Finance</option>
                        <option value="Financed">Financed</option>
                        <option value="Returned">Returned Claims</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Claim Type</label>
                    <select class="form-select form-select-sm" id="filterClaimType">
                        <option value="">All Types</option>
                        @foreach($claimTypes as $ct)
                            <option value="{{ $ct->ClaimId }}">{{ $ct->ClaimName }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Claim Date From</label>
                    <input type="date" class="form-control form-control-sm" id="filterDateFrom">
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Claim Date To</label>
                    <input type="date" class="form-control form-control-sm" id="filterDateTo">
                </div>

                <div class="col-md-4 col-sm-12">
                    <label class="form-label mb-1 fw-semibold" style="font-size:11px;">Employee (Claimant Code or Name)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="ri-user-search-line"></i></span>
                        <input type="text" class="form-control form-control-sm" id="filterEmployee" placeholder="Search by EmpCode, ECode or Name..." aria-label="Search by EmpCode, ECode or Name">
                    </div>
                </div>

                <div class="col-md-8 col-sm-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="btnReset">
                        <i class="ri-restart-line me-1"></i> Reset
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4 fw-semibold" id="btnApplyFilter">
                        <i class="ri-search-line me-1"></i> Filter Records
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Tabs Navigation --}}
    <ul class="nav nav-tabs nav-tabs-custom mb-3" id="trackingTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-semibold" id="claimWiseTab" data-bs-toggle="tab" data-bs-target="#claimWisePane" type="button" role="tab">
                <i class="ri-list-check-2 me-1"></i> Claim-Wise Action Audit
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold" id="employeeSummaryTab" data-bs-toggle="tab" data-bs-target="#employeeSummaryPane" type="button" role="tab">
                <i class="ri-user-star-line me-1"></i> Employee-Wise Monthly Summary
            </button>
        </li>
    </ul>

    {{-- Tab Contents --}}
    <div class="tab-content">

        {{-- TAB 1: Claim-Wise Audit --}}
        <div class="tab-pane fade show active" id="claimWisePane" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0" id="claimWiseTable" style="width:100%; font-size:11px;">
                            <thead class="table-light text-center" style="font-size:10px; background-color:#15648C; color:white;">
                                <tr>
                                    <th style="background-color:#15648C; color:white; width:40px;">#</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Claimant</th>
                                    <th style="background-color:#15648C; color:white; width:110px;">Claim Details</th>
                                    <th style="background-color:#15648C; color:white; width:70px;">Amount</th>
                                    <th style="background-color:#15648C; color:white; width:75px;">Status</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Who Filled</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Who Verified</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Who Approved</th>
                                    <th style="background-color:#15648C; color:white; width:95px;">Approved Date</th>
                                    <th style="background-color:#15648C; color:white; width:95px;">Courier Recv Date</th>
                                    <th style="background-color:#15648C; color:white; width:95px;">Payment Date</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Who Financed</th>
                                    <th style="background-color:#15648C; color:white; width:65px;">Total TAT</th>
                                    <th style="background-color:#15648C; color:white; width:60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: Employee-Wise Summary --}}
        <div class="tab-pane fade" id="employeeSummaryPane" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0" id="employeeSummaryTable" style="width:100%; font-size:11px;">
                            <thead class="table-light text-center" style="font-size:10px; background-color:#15648C; color:white;">
                                <tr>
                                    <th style="background-color:#15648C; color:white; width:40px;">#</th>
                                    <th style="background-color:#15648C; color:white; width:140px;">Employee</th>
                                    <th style="background-color:#15648C; color:white; width:70px;">Month</th>
                                    <th style="background-color:#15648C; color:white; width:55px;">Claims</th>
                                    <th style="background-color:#15648C; color:white; width:80px;">Claimed Amt</th>
                                    <th style="background-color:#15648C; color:white; width:80px;">Verified Amt</th>
                                    <th style="background-color:#15648C; color:white; width:80px;">Approved Amt</th>
                                    <th style="background-color:#15648C; color:white; width:80px;">Financed Amt</th>
                                    <th style="background-color:#15648C; color:white; width:75px;">Status</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Verifier</th>
                                    <th style="background-color:#15648C; color:white; width:120px;">Approver</th>
                                    <th style="background-color:#15648C; color:white; width:95px;">Approved Date</th>
                                    <th style="background-color:#15648C; color:white; width:95px;">Courier Recv Date</th>
                                    <th style="background-color:#15648C; color:white; width:95px;">Payment Date</th>
                                    <th style="background-color:#15648C; color:white; width:130px;">Finance / Paid</th>
                                    <th style="background-color:#15648C; color:white; width:65px;">Total TAT</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
</div>

{{-- Visual Audit Timeline Modal --}}
<div class="modal fade" id="actionTimelineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header text-white" style="background-color:#15648C;">
                <h5 class="modal-title fw-bold" id="timelineModalTitle">
                    <i class="ri-history-line me-2"></i>Claim Action Audit Progression
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="timelineModalBody">
                <!-- Dynamically populated -->
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .nav-tabs-custom .nav-link {
        color: #495057;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 8px 16px;
    }
    .nav-tabs-custom .nav-link.active {
        color: #15648C;
        border-bottom: 3px solid #15648C;
        background: transparent;
    }
    .tat-badge {
        font-size: 9px;
        padding: 2px 5px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
    }
    .tat-fast { background-color: #d1e7dd; color: #0f5132; }
    .tat-medium { background-color: #fff3cd; color: #664d03; }
    .tat-slow { background-color: #f8d7da; color: #842029; }
    .timeline-step {
        position: relative;
        padding-left: 35px;
        margin-bottom: 20px;
    }
    .timeline-step:last-child {
        margin-bottom: 0;
    }
    .timeline-step::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 24px;
        bottom: -15px;
        width: 2px;
        background-color: #e9ecef;
    }
    .timeline-step:last-child::before {
        display: none;
    }
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        color: white;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    let claimWiseTable = null;
    let employeeSummaryTable = null;
    let activeExportType = 'claim_wise';

    function getFilterParams() {
        return {
            _token: '{{ csrf_token() }}',
            year_id: $('#filterYearId').val(),
            month: $('#filterMonth').val(),
            status: $('#filterStatus').val(),
            claim_type_id: $('#filterClaimType').val(),
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val(),
            employee: $('#filterEmployee').val(),
            // Organogram Filters
            company: $('#orgCompany').val() || '',
            department: $('#orgDepartment').val() || '',
            vertical: $('#orgVertical').val() || '',
            region: $('#orgRegion').val() || '',
            zone: $('#orgZone').val() || '',
            bu: $('#orgBu').val() || '',
            state: $('#orgState').val() || '',
            hq: $('#orgHq').val() || '',
        };
    }

    function loadSummaryKPIs() {
        $.ajax({
            url: '{{ route("reports.claim-action-tracking.summary") }}',
            method: 'GET',
            data: getFilterParams(),
            success: function(res) {
                if (res.success && res.summary) {
                    const s = res.summary;
                    $('#kpiTotalClaims').text(s.total_claims.toLocaleString('en-IN'));
                    $('#kpiTotalClaimedAmt').text('₹' + s.total_claimed_amt.toLocaleString('en-IN', {maximumFractionDigits: 0}));

                    $('#kpiFilledCount').text(s.filled_count.toLocaleString('en-IN'));
                    $('#kpiFilledAmt').text('₹' + s.filled_amt.toLocaleString('en-IN', {maximumFractionDigits: 0}));

                    $('#kpiVerifiedCount').text(s.verified_count.toLocaleString('en-IN'));
                    $('#kpiVerifiedAmt').text('₹' + s.verified_amt.toLocaleString('en-IN', {maximumFractionDigits: 0}));

                    $('#kpiApprovedCount').text(s.approved_count.toLocaleString('en-IN'));
                    $('#kpiApprovedAmt').text('₹' + s.approved_amt.toLocaleString('en-IN', {maximumFractionDigits: 0}));

                    $('#kpiFinancedCount').text(s.financed_count.toLocaleString('en-IN'));
                    $('#kpiFinancedAmt').text('₹' + s.financed_amt.toLocaleString('en-IN', {maximumFractionDigits: 0}));

                    $('#kpiClaimants').text(s.unique_claimants);
                    $('#kpiVerifiers').text(s.unique_verifiers);
                    $('#kpiApprovers').text(s.unique_approvers);
                }
            }
        });
    }

    function initClaimWiseTable() {
        if (claimWiseTable) {
            claimWiseTable.ajax.reload();
            return;
        }

        claimWiseTable = $('#claimWiseTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'desc']],
            ajax: {
                url: '{{ route("reports.claim-action-tracking.data") }}',
                type: 'POST',
                data: function(d) {
                    return $.extend({}, d, getFilterParams());
                }
            },
            columns: [
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div>
                            <strong class="text-primary">${row.claimant_name}</strong>
                            <div class="text-muted" style="font-size:10px;">${row.claimant_code}</div>
                            <div style="font-size:10px; color:#666;">${row.department}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div>
                            <strong>#${row.exp_id}</strong> (${row.claim_month})<br>
                            <span class="badge bg-light text-dark border" style="font-size:9px;">${row.claim_type}</span><br>
                            <span style="font-size:9px; color:#777;">Bill: ${row.bill_date}</span>
                        </div>`;
                    }
                },
                {
                    data: 'amount',
                    render: function(val) {
                        return `<strong class="text-dark">₹${val.toLocaleString('en-IN')}</strong>`;
                    },
                    className: 'text-end'
                },
                {
                    data: 'status',
                    render: function(status) {
                        let color = 'secondary';
                        if (status === 'Approved') color = 'success';
                        else if (status === 'Verified') color = 'warning';
                        else if (status === 'Financed') color = 'info';
                        else if (status === 'Filled') color = 'primary';
                        else if (status === 'Draft') color = 'dark';
                        return `<span class="badge bg-${color}" style="font-size:9px;">${status}</span>`;
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.filled_by_name || row.filled_by_name === '-') {
                            return '<span class="text-muted">-</span>';
                        }
                        const tat = (row.tat_fill !== null) ? `<span class="tat-badge ${getTatClass(row.tat_fill)}">${row.tat_fill}d TAT</span>` : '';
                        return `<div>
                            <strong>${row.filled_by_name}</strong>
                            <div class="text-muted" style="font-size:10px;">${row.filled_by_code}</div>
                            <div style="font-size:9px; color:#555;">${row.filled_date}</div>
                            <div style="font-size:9px;">₹${row.filled_amt.toLocaleString('en-IN')} ${tat}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.verify_by_name || row.verify_by_name === '-') {
                            return '<span class="text-muted">-</span>';
                        }
                        const tat = (row.tat_verify !== null) ? `<span class="tat-badge ${getTatClass(row.tat_verify)}">${row.tat_verify}d TAT</span>` : '';
                        return `<div>
                            <strong class="text-warning-emphasis">${row.verify_by_name}</strong>
                            <div class="text-muted" style="font-size:10px;">${row.verify_by_code}</div>
                            <div style="font-size:9px; color:#555;">${row.verify_date}</div>
                            <div style="font-size:9px;">₹${row.verify_amt.toLocaleString('en-IN')} ${tat}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.appr_by_name || row.appr_by_name === '-') {
                            return '<span class="text-muted">-</span>';
                        }
                        const tat = (row.tat_approve !== null) ? `<span class="tat-badge ${getTatClass(row.tat_approve)}">${row.tat_approve}d TAT</span>` : '';
                        return `<div>
                            <strong class="text-success">${row.appr_by_name}</strong>
                            <div class="text-muted" style="font-size:10px;">${row.appr_by_code}</div>
                            <div style="font-size:9px; color:#555;">${row.appr_date}</div>
                            <div style="font-size:9px;">₹${row.appr_amt.toLocaleString('en-IN')} ${tat}</div>
                        </div>`;
                    }
                },
                {
                    data: 'approved_date',
                    render: function(val) {
                        return (val && val !== '-') ? `<span class="badge bg-success-subtle text-success border border-success" style="font-size:9px;">${val}</span>` : '<span class="text-muted">-</span>';
                    },
                    className: 'text-center'
                },
                {
                    data: 'courier_receiving_date',
                    render: function(val) {
                        return (val && val !== '-') ? `<span class="badge bg-info-subtle text-info border border-info" style="font-size:9px;">${val}</span>` : '<span class="text-muted">-</span>';
                    },
                    className: 'text-center'
                },
                {
                    data: 'payment_date',
                    render: function(val) {
                        return (val && val !== '-') ? `<span class="badge bg-light text-dark border" style="font-size:9px;">${val}</span>` : '<span class="text-muted">-</span>';
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.financed_by_name || row.financed_by_name === '-') {
                            return '<span class="text-muted">-</span>';
                        }
                        const tat = (row.tat_finance !== null) ? `<span class="tat-badge ${getTatClass(row.tat_finance)}">${row.tat_finance}d TAT</span>` : '';
                        return `<div>
                            <strong class="text-dark">${row.financed_by_name}</strong>
                            <div class="text-muted" style="font-size:10px;">${row.financed_by_code}</div>
                            <div style="font-size:9px; color:#555;">${row.financed_date}</div>
                            <div style="font-size:9px;">₹${row.financed_amt.toLocaleString('en-IN')} ${tat}</div>
                        </div>`;
                    }
                },
                {
                    data: 'overall_tat_days',
                    render: function(tat) {
                        if (tat === null || tat === undefined || tat === '-') return '<span class="text-muted">-</span>';
                        return `<span class="tat-badge ${getTatClass(tat)}" style="font-size:10px;">${tat} Days</span>`;
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<button type="button" class="btn btn-sm btn-outline-info p-1 py-0 btn-timeline" style="font-size:10px;">
                            <i class="ri-history-line"></i> Audit
                        </button>`;
                    },
                    className: 'text-center'
                }
            ]
        });

        // Click on audit button
        $('#claimWiseTable tbody').on('click', '.btn-timeline', function() {
            const data = claimWiseTable.row($(this).closest('tr')).data();
            showAuditTimeline(data);
        });
    }

    function initEmployeeSummaryTable() {
        if (employeeSummaryTable) {
            employeeSummaryTable.ajax.reload();
            return;
        }

        employeeSummaryTable = $('#employeeSummaryTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            ajax: {
                url: '{{ route("reports.claim-action-tracking.employee-summary") }}',
                type: 'GET',
                data: function(d) {
                    return $.extend({}, d, getFilterParams());
                }
            },
            columns: [
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div>
                            <strong class="text-primary">${row.employee_name}</strong>
                            <div class="text-muted" style="font-size:10px;">${row.employee_code}</div>
                            <div style="font-size:10px; color:#666;">${row.department}</div>
                        </div>`;
                    }
                },
                { data: 'month_name', className: 'text-center fw-semibold' },
                { data: 'total_claims', className: 'text-center fw-bold' },
                {
                    data: 'claimed_amt',
                    render: function(val) { return `₹${val.toLocaleString('en-IN')}`; },
                    className: 'text-end fw-bold text-dark'
                },
                {
                    data: 'verified_amt',
                    render: function(val) { return `₹${val.toLocaleString('en-IN')}`; },
                    className: 'text-end text-warning-emphasis'
                },
                {
                    data: 'approved_amt',
                    render: function(val) { return `₹${val.toLocaleString('en-IN')}`; },
                    className: 'text-end text-success'
                },
                {
                    data: 'financed_amt',
                    render: function(val) { return `₹${val.toLocaleString('en-IN')}`; },
                    className: 'text-end text-dark'
                },
                {
                    data: 'month_status',
                    render: function(status) {
                        let color = 'secondary';
                        if (status === 'Closed' || status === 'Approved') color = 'success';
                        else if (status === 'Verified') color = 'warning';
                        else if (status === 'Submitted') color = 'primary';
                        return `<span class="badge bg-${color}" style="font-size:9px;">${status}</span>`;
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.verifier_name || row.verifier_name === '-') return '-';
                        return `<div>
                            <strong>${row.verifier_name}</strong>
                            <div class="text-muted" style="font-size:9px;">${row.verify_date}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.approver_name || row.approver_name === '-') return '-';
                        return `<div>
                            <strong class="text-success">${row.approver_name}</strong>
                            <div class="text-muted" style="font-size:9px;">${row.appr_date}</div>
                        </div>`;
                    }
                },
                {
                    data: 'approved_date',
                    render: function(val) {
                        return (val && val !== '-') ? `<span class="badge bg-success-subtle text-success border border-success" style="font-size:9px;">${val}</span>` : '<span class="text-muted">-</span>';
                    },
                    className: 'text-center'
                },
                {
                    data: 'courier_receiving_date',
                    render: function(val) {
                        return (val && val !== '-') ? `<span class="badge bg-info-subtle text-info border border-info" style="font-size:9px;">${val}</span>` : '<span class="text-muted">-</span>';
                    },
                    className: 'text-center'
                },
                {
                    data: 'payment_date',
                    render: function(val) {
                        return (val && val !== '-') ? `<span class="badge bg-light text-dark border" style="font-size:9px;">${val}</span>` : '<span class="text-muted">-</span>';
                    },
                    className: 'text-center'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.fin_app_name || row.fin_app_name === '-') return '-';
                        const paidInfo = (row.pay_date && row.pay_date !== '-') ? `<div class="text-success" style="font-size:9px;">Paid: ${row.pay_date}</div>` : '';
                        return `<div>
                            <strong>${row.fin_app_name}</strong>
                            <div class="text-muted" style="font-size:9px;">App: ${row.finance_date}</div>
                            ${paidInfo}
                        </div>`;
                    }
                },
                {
                    data: 'overall_tat_days',
                    render: function(tat) {
                        if (tat === null || tat === undefined || tat === '-') return '<span class="text-muted">-</span>';
                        return `<span class="tat-badge ${getTatClass(tat)}" style="font-size:10px;">${tat} Days</span>`;
                    },
                    className: 'text-center'
                }
            ]
        });
    }

    function getTatClass(days) {
        if (days <= 3) return 'tat-fast';
        if (days <= 7) return 'tat-medium';
        return 'tat-slow';
    }

    function showAuditTimeline(claim) {
        $('#timelineModalTitle').html(`<i class="ri-history-line me-2"></i>Action Audit Timeline — Claim #${claim.exp_id} (${$('<div>').text(String(claim.claimant_name || '')).html()})`);
        
        let html = `
            <div class="d-flex justify-content-between p-2 mb-3 bg-light rounded border">
                <div><strong>Claimant:</strong> ${claim.claimant_name} (${claim.claimant_code})</div>
                <div><strong>Type:</strong> ${claim.claim_type}</div>
                <div><strong>Claimed:</strong> ₹${claim.amount.toLocaleString('en-IN')}</div>
                <div><strong>Status:</strong> <span class="badge bg-primary">${claim.status}</span></div>
            </div>
            
            <div class="timeline-container ps-2">
                <!-- Step 1: Claim Created -->
                <div class="timeline-step">
                    <div class="timeline-icon bg-primary"><i class="ri-upload-cloud-line"></i></div>
                    <div class="fw-bold text-primary">1. Claim Uploaded / Created</div>
                    <div class="text-muted" style="font-size:11px;">By: <strong>${claim.claimant_name}</strong> (${claim.claimant_code}) on ${claim.cr_date}</div>
                    <div class="mt-1" style="font-size:11px;">Claimed Amount: <strong>₹${claim.amount.toLocaleString('en-IN')}</strong> | Bill Date: ${claim.bill_date}</div>
                    ${claim.remark ? `<div class="text-muted fst-italic" style="font-size:11px;">Remark: "${claim.remark}"</div>` : ''}
                </div>

                <!-- Step 2: Filled -->
                <div class="timeline-step">
                    <div class="timeline-icon ${claim.filled_by_name !== '-' ? 'bg-info' : 'bg-secondary'}"><i class="ri-edit-2-line"></i></div>
                    <div class="fw-bold ${claim.filled_by_name !== '-' ? 'text-info' : 'text-muted'}">2. Filling Stage</div>
                    ${claim.filled_by_name !== '-' ? `
                        <div class="text-muted" style="font-size:11px;">By: <strong>${claim.filled_by_name}</strong> (${claim.filled_by_code}) on ${claim.filled_date}</div>
                        <div class="mt-1" style="font-size:11px;">Filled Amount: <strong>₹${claim.filled_amt.toLocaleString('en-IN')}</strong> ${claim.tat_fill !== null ? `<span class="tat-badge ${getTatClass(claim.tat_fill)} ms-2">${claim.tat_fill} days from claim</span>` : ''}</div>
                        ${claim.filled_remark ? `<div class="text-muted fst-italic" style="font-size:11px;">Remark: "${claim.filled_remark}"</div>` : ''}
                    ` : '<div class="text-muted" style="font-size:11px;">Pending Filling</div>'}
                </div>

                <!-- Step 3: Verified -->
                <div class="timeline-step">
                    <div class="timeline-icon ${claim.verify_by_name !== '-' ? 'bg-warning' : 'bg-secondary'}"><i class="ri-checkbox-circle-line"></i></div>
                    <div class="fw-bold ${claim.verify_by_name !== '-' ? 'text-warning-emphasis' : 'text-muted'}">3. Verification Stage (Reporting Manager)</div>
                    ${claim.verify_by_name !== '-' ? `
                        <div class="text-muted" style="font-size:11px;">By: <strong>${claim.verify_by_name}</strong> (${claim.verify_by_code}) on ${claim.verify_date}</div>
                        <div class="mt-1" style="font-size:11px;">Verified Amount: <strong>₹${claim.verify_amt.toLocaleString('en-IN')}</strong> ${claim.tat_verify !== null ? `<span class="tat-badge ${getTatClass(claim.tat_verify)} ms-2">${claim.tat_verify} days from filling</span>` : ''}</div>
                        ${claim.verify_remark ? `<div class="text-muted fst-italic" style="font-size:11px;">Remark: "${claim.verify_remark}"</div>` : ''}
                    ` : '<div class="text-muted" style="font-size:11px;">Pending Verification</div>'}
                </div>

                <!-- Step 4: Approved -->
                <div class="timeline-step">
                    <div class="timeline-icon ${claim.appr_by_name !== '-' ? 'bg-success' : 'bg-secondary'}"><i class="ri-shield-check-line"></i></div>
                    <div class="fw-bold ${claim.appr_by_name !== '-' ? 'text-success' : 'text-muted'}">4. Approval Stage (HOD / Level 2)</div>
                    ${claim.appr_by_name !== '-' ? `
                        <div class="text-muted" style="font-size:11px;">By: <strong>${claim.appr_by_name}</strong> (${claim.appr_by_code}) on ${claim.appr_date}</div>
                        <div class="mt-1" style="font-size:11px;">Approved Amount: <strong>₹${claim.appr_amt.toLocaleString('en-IN')}</strong> ${claim.tat_approve !== null ? `<span class="tat-badge ${getTatClass(claim.tat_approve)} ms-2">${claim.tat_approve} days from verification</span>` : ''}</div>
                        ${claim.appr_remark ? `<div class="text-muted fst-italic" style="font-size:11px;">Remark: "${claim.appr_remark}"</div>` : ''}
                    ` : '<div class="text-muted" style="font-size:11px;">Pending Approval</div>'}
                </div>

                <!-- Step 5: Finance & Payment -->
                <div class="timeline-step">
                    <div class="timeline-icon ${claim.financed_by_name !== '-' ? 'bg-dark' : 'bg-secondary'}"><i class="ri-bank-card-line"></i></div>
                    <div class="fw-bold ${claim.financed_by_name !== '-' ? 'text-dark' : 'text-muted'}">5. Finance Approval & Settlement</div>
                    ${claim.financed_by_name !== '-' ? `
                        <div class="text-muted" style="font-size:11px;">By: <strong>${claim.financed_by_name}</strong> (${claim.financed_by_code}) on ${claim.financed_date}</div>
                        <div class="mt-1" style="font-size:11px;">Financed Amount: <strong>₹${claim.financed_amt.toLocaleString('en-IN')}</strong> ${claim.tat_finance !== null ? `<span class="tat-badge ${getTatClass(claim.tat_finance)} ms-2">${claim.tat_finance} days from approval</span>` : ''}</div>
                        ${claim.financed_remark ? `<div class="text-muted fst-italic" style="font-size:11px;">Remark: "${claim.financed_remark}"</div>` : ''}
                    ` : '<div class="text-muted" style="font-size:11px;">Pending Finance Settlement</div>'}
                </div>
            </div>
        `;

        $('#timelineModalBody').html(html);
        $('#actionTimelineModal').modal('show');
    }

    // Trigger tab table load
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        if (e.target.id === 'employeeSummaryTab') {
            activeExportType = 'employee_summary';
            initEmployeeSummaryTable();
        } else {
            activeExportType = 'claim_wise';
            initClaimWiseTable();
        }
    });

    // Initial load
    initClaimWiseTable();
    loadSummaryKPIs();

    // Filters event
    $('#btnApplyFilter').on('click', function() {
        if (claimWiseTable) claimWiseTable.ajax.reload();
        if (employeeSummaryTable) employeeSummaryTable.ajax.reload();
        loadSummaryKPIs();
    });

    $('#btnReset').on('click', function() {
        $('#filterMonth').val('');
        $('#filterStatus').val('');
        $('#filterClaimType').val('');
        $('#filterDateFrom').val('');
        $('#filterDateTo').val('');
        $('#filterEmployee').val('');
        if (claimWiseTable) claimWiseTable.ajax.reload();
        if (employeeSummaryTable) employeeSummaryTable.ajax.reload();
        loadSummaryKPIs();
    });

    window.triggerExport = function(type) {
        const params = getFilterParams();
        params.export_type = type || activeExportType;
        const queryStr = $.param(params);
        window.location.href = '{{ route("reports.claim-action-tracking.export") }}?' + queryStr;
    };
});
</script>
@endpush
