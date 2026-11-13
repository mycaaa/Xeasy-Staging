@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        @include('partials.organogram-filters-drawer')
        <div class="row mb-3">
            <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold" style="color:#15648C;">
                        <i class="ri-bar-chart-box-line me-2"></i>Claim Stage Report Dashboard
                    </h4>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="{{ route('reports.claim.dashboard.print') }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Print-ready view">
                            <i class="ri-printer-line me-1"></i>Print View
                        </a>
                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-danger" id="btnPdfStageSummary" title="Download PDF">
                            <i class="ri-file-pdf-line me-1"></i>PDF
                        </a>
                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportFullReport" title="Download all reports in one Excel">
                            <i class="ri-file-excel-line me-1"></i>Full Export
                        </a>
                        <span class="text-muted" style="font-size:12px;">
                            As on {{ now()->format('d-m-Y h:i A') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:12px;">Function</label>
                        <select class="form-select form-select-sm" id="filterFunction">
                            <option value="">All Functions</option>
                            @foreach($functions as $fn)
                                <option value="{{ $fn->id }}">{{ $fn->function_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:12px;">Department</label>
                        <select class="form-select form-select-sm" id="filterDepartment">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:12px;">Months Range</label>
                        <div class="d-flex gap-1">
                            <select class="form-select form-select-sm" id="filterMonthFrom">
                                <option value="4">Apr</option>
                                <option value="5">May</option>
                                <option value="6">Jun</option>
                                <option value="7">Jul</option>
                                <option value="8">Aug</option>
                                <option value="9">Sep</option>
                                <option value="10">Oct</option>
                                <option value="11">Nov</option>
                                <option value="12">Dec</option>
                                <option value="1">Jan</option>
                                <option value="2">Feb</option>
                                <option value="3">Mar</option>
                            </select>
                            <select class="form-select form-select-sm" id="filterMonthTo">
                                <option value="4">Apr</option>
                                <option value="5">May</option>
                                <option value="6">Jun</option>
                                <option value="7">Jul</option>
                                <option value="8">Aug</option>
                                <option value="9">Sep</option>
                                <option value="10">Oct</option>
                                <option value="11">Nov</option>
                                <option value="12">Dec</option>
                                <option value="1">Jan</option>
                                <option value="2">Feb</option>
                                <option value="3" selected>Mar</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm flex-grow-1" id="btnApplyFilter">
                                <i class="ri-filter-line me-1"></i> Apply Filter
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                <i class="ri-equalizer-line me-1"></i> More
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="activeFilterCount">0</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="row mb-3" id="kpiCards">
            <div class="col-md-2">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted" style="font-size:11px;">Total Claims</div>
                        <h4 class="mb-0 fw-bold text-primary" id="kpiTotalClaims">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted" style="font-size:11px;">Total Paid</div>
                        <h4 class="mb-0 fw-bold text-success" id="kpiTotalPaid">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted" style="font-size:11px;">% Complete</div>
                        <h4 class="mb-0 fw-bold text-info" id="kpiPercentComplete">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted" style="font-size:11px;">Pending Verify</div>
                        <h4 class="mb-0 fw-bold text-warning" id="kpiPendingVerify">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted" style="font-size:11px;">Pending Approval</div>
                        <h4 class="mb-0 fw-bold text-danger" id="kpiPendingApproval">-</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted" style="font-size:11px;">Pending Payment</div>
                        <h4 class="mb-0 fw-bold" style="color:#6f42c1;" id="kpiPendingPayment">-</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs" id="reportTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tabStageSummary">
                    <i class="ri-table-line me-1"></i>Stage Summary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabPendingDetail">
                    <i class="ri-list-check-2 me-1"></i>Pending Detail
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabDeptSummary">
                    <i class="ri-building-line me-1"></i>Dept. Summary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabSubmission">
                    <i class="ri-bar-chart-2-line me-1"></i>Submission Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabTAT">
                    <i class="ri-time-line me-1"></i>TAT Analysis
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabCourier">
                    <i class="ri-mail-send-line me-1"></i>Courier Status
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabManagement">
                    <i class="ri-user-star-line me-1"></i>Mgmt. Claims
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabImprest">
                    <i class="ri-money-dollar-circle-line me-1"></i>Imprest
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabEmployeeWiseTAT">
                    <i class="ri-user-line me-1"></i>Employee-wise TAT
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabClaimWiseTAT">
                    <i class="ri-file-list-line me-1"></i>Claim-wise TAT
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabSubmissionReturn">
                    <i class="ri-arrow-go-back-line me-1"></i>Submission Return
                </a>
            </li>
        </ul>

        <div class="tab-content mt-0">

            {{-- Tab 1: Stage Summary Table --}}
            <div class="tab-pane fade show active" id="tabStageSummary">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-end mb-2">
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportStageSummary">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle text-center mb-0" id="stageSummaryTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>Claim Status</th>
                                        <th id="thMonths"></th>
                                    </tr>
                                </thead>
                                <tbody id="stageSummaryBody">
                                    <tr><td colspan="13" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 2: Pending Detail --}}
            <div class="tab-pane fade" id="tabPendingDetail">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between mb-2">
                            <select class="form-select form-select-sm d-inline-block" style="width:250px;" id="pendingStageSelect">
                                <option value="open">Total open, final submission not done</option>
                                @if(session('company_id') == 11)
                                    <option value="pending_approval">Submitted, pending approval</option>
                                    <option value="pending_payment">Approved, pending payment</option>
                                    <option value="paid_pending_courier">Paid, pending courier</option>
                                    <option value="pending_verification">Courier received, pending verification</option>
                                @else
                                    <option value="submitted_no_courier">Submitted, courier not sent</option>
                                    <option value="pending_verification">Received, pending verification</option>
                                    <option value="pending_approval">Verified, pending manager approval</option>
                                    <option value="pending_finance">Approved, pending finance</option>
                                    <option value="pending_payment">Finance processed, pending payment</option>
                                @endif
                                <option value="paid">Paid (completed)</option>
                                <option value="no_claim">No Claim Employee List</option>
                            </select>
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportPendingDetail">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0" id="pendingDetailTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>EmpCode</th>
                                        <th>Employee Name</th>
                                        <th>Status</th>
                                        <th>Department</th>
                                        <th>Function</th>
                                        <th>Month</th>
                                        <th>Claims</th>
                                        <th>Amount</th>
                                        <th>Submit Date</th>
                                        <th>Courier Post Date</th>
                                    </tr>
                                </thead>
                                <tbody id="pendingDetailBody">
                                    <tr><td colspan="11" class="text-center py-3">Select a stage to view details</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 3: Department Summary --}}
            <div class="tab-pane fade" id="tabDeptSummary">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="mb-2">
                            <select class="form-select form-select-sm d-inline-block" style="width:250px;" id="deptStageSelect">
                                <option value="submitted_no_courier">Submitted, courier not sent</option>
                                <option value="pending_verification">Pending verification</option>
                                <option value="pending_approval">Pending approval</option>
                                <option value="pending_payment">Pending payment</option>
                            </select>
                        </div>
                        <div class="table-responsive" id="deptSummaryContainer">
                            <p class="text-center text-muted py-3">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 4: Submission Report --}}
            <div class="tab-pane fade" id="tabSubmission">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body">
                        <div id="submissionChart" style="min-height:350px;"></div>
                    </div>
                </div>
            </div>

            {{-- Tab 5: TAT Analysis --}}
            <div class="tab-pane fade" id="tabTAT">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-2">
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportTAT">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </a>
                        </div>
                        <div id="tatChart" style="min-height:350px;"></div>
                    </div>
                </div>
            </div>

            {{-- Tab 7: Courier Posting Status --}}
            <div class="tab-pane fade" id="tabCourier">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="table-responsive" id="courierStatusContainer">
                            <p class="text-center text-muted py-3">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 8: Management Claim Status --}}
            <div class="tab-pane fade" id="tabManagement">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0" id="mgmtTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>EmpCode</th>
                                        <th>Employee Name</th>
                                        <th>Grade</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Month</th>
                                        <th>Submission Date</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="mgmtBody">
                                    <tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 9: Imprest Promotions --}}
            <div class="tab-pane fade" id="tabImprest">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0" id="imprestTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>EC</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Grade</th>
                                        <th>Amount</th>
                                        <th>Released Date</th>
                                    </tr>
                                </thead>
                                <tbody id="imprestBody">
                                    <tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 10: Employee-wise TAT Analysis --}}
            <div class="tab-pane fade" id="tabEmployeeWiseTAT">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" id="empWiseTATSearch" placeholder="Search employee..." aria-label="Search employee" style="width: 200px;">
                                <select class="form-select form-select-sm" id="empWiseTATSort" style="width: 150px;">
                                    <option value="tat-desc">Sort: TAT (High to Low)</option>
                                    <option value="tat-asc">Sort: TAT (Low to High)</option>
                                    <option value="claims-desc">Sort: Claims (High to Low)</option>
                                    <option value="claims-asc">Sort: Claims (Low to High)</option>
                                    <option value="name-asc">Sort: Name (A to Z)</option>
                                </select>
                            </div>
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportEmployeeWiseTAT">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-3" id="employeeWiseTATTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>EC</th>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Function</th>
                                        <th>Total Claims</th>
                                        <th>Avg Claim Amount</th>
                                        <th id="tat-header-1">TAT 1</th>
                                        <th id="tat-header-2">TAT 2</th>
                                        <th id="tat-header-3">TAT 3</th>
                                        <th id="tat-header-4">TAT 4</th>
                                        <th id="tat-header-5">TAT 5</th>
                                        <th id="tat-header-total">Total TAT</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeWiseTATBody">
                                    <tr><td colspan="13" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted" id="empWiseTATInfo">Showing 0 of 0 records</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="empWiseTATPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 11: Claim-wise TAT Analysis --}}
            <div class="tab-pane fade" id="tabClaimWiseTAT">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" id="claimWiseTATSearch" placeholder="Search claim type..." aria-label="Search claim type" style="width: 200px;">
                                <select class="form-select form-select-sm" id="claimWiseTATSort" style="width: 150px;">
                                    <option value="tat-desc">Sort: TAT (High to Low)</option>
                                    <option value="tat-asc">Sort: TAT (Low to High)</option>
                                    <option value="claims-desc">Sort: Claims (High to Low)</option>
                                    <option value="claims-asc">Sort: Claims (Low to High)</option>
                                    <option value="amount-desc">Sort: Amount (High to Low)</option>
                                </select>
                            </div>
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportClaimWiseTAT">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-3" id="claimWiseTATTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>Claim Type</th>
                                        <th>Total Claims</th>
                                        <th>Avg Claim Amount</th>
                                        <th>Total Amount</th>
                                        <th id="claim-tat-header-1">TAT 1</th>
                                        <th id="claim-tat-header-2">TAT 2</th>
                                        <th id="claim-tat-header-3">TAT 3</th>
                                        <th id="claim-tat-header-4">TAT 4</th>
                                        <th id="claim-tat-header-5">TAT 5</th>
                                        <th id="claim-tat-header-total">Total TAT</th>
                                    </tr>
                                </thead>
                                <tbody id="claimWiseTATBody">
                                    <tr><td colspan="11" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted" id="claimWiseTATInfo">Showing 0 of 0 records</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="claimWiseTATPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab 12: Submission Return Report --}}
            <div class="tab-pane fade" id="tabSubmissionReturn">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-end mb-2">
                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" id="btnExportSubmissionReturn">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0" id="submissionReturnTable">
                                <thead style="background:#dde6ff;color:#1e3a8a;">
                                    <tr>
                                        <th>#</th>
                                        <th>Employee Code</th>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Function</th>
                                        <th>Month</th>
                                        <th>Status</th>
                                        <th>Submission Date</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="submissionReturnBody">
                                    <tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted" id="submissionReturnInfo">Showing 0 of 0 records</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="submissionReturnPagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Stage Detail Modal --}}
<div class="modal fade" id="stageDetailModal" tabindex="-1" aria-labelledby="stageDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                <h5 class="modal-title text-white" id="stageDetailModalLabel">
                    <i class="ri-list-check-2 me-1"></i>Stage Detail
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                    <div>
                        <span class="badge bg-primary fs-12" id="stageDetailBadge">Stage</span>
                        <span class="badge bg-info fs-12 ms-1" id="stageDetailMonthBadge">Month</span>
                        <span class="text-muted ms-2 fs-12" id="stageDetailCount"></span>
                    </div>
                    <button class="btn btn-sm btn-outline-success" id="btnExportStageModal">
                        <i class="ri-file-excel-line me-1"></i>Export Excel
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover align-middle mb-0" id="stageDetailModalTable">
                        <thead style="background:#f0f4f8;">
                            <tr>
                                <th>#</th>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Function</th>
                                <th>Department</th>
                                <th>Month</th>
                                <th>Submit Date</th>
                                <th>Receiving Date</th>
                                <th>Verified Date</th>
                                <th>Approved Date</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody id="stageDetailModalBody">
                            <tr><td colspan="11" class="text-center py-4 text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/apexcharts@8.7.0/dist/apexcharts.min.js') }}"></script>
<link rel="stylesheet" href="{{ asset('vendor/cdn/datatables.net-bs5@1.13.7/css/dataTables.bootstrap5.min.css') }}">
<script src="{{ asset('vendor/cdn/datatables.net@1.13.7/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-bs5@1.13.7/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const isVVNR = {{ session('company_id') == 11 ? 'true' : 'false' }};
let submissionChart = null, tatChart = null;

function getFilters() {
    const from = parseInt($('#filterMonthFrom').val());
    const to = parseInt($('#filterMonthTo').val());

    // Build months in financial year order (Apr=4 to Mar=3)
    const fyOrder = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
    const fromIdx = fyOrder.indexOf(from);
    const toIdx = fyOrder.indexOf(to);
    const months = [];

    if (fromIdx <= toIdx) {
        for (let i = fromIdx; i <= toIdx; i++) months.push(fyOrder[i]);
    } else {
        // Wrap around (shouldn't happen with proper FY selection, but handle it)
        for (let i = fromIdx; i < fyOrder.length; i++) months.push(fyOrder[i]);
        for (let i = 0; i <= toIdx; i++) months.push(fyOrder[i]);
    }

    const organogram = window.getOrganogramFilterValues ? window.getOrganogramFilterValues() : {};
    const topFn = $('#filterFunction').val();
    const topDept = $('#filterDepartment').val();

    let fnIds = organogram.function_ids || [];
    if (topFn && !fnIds.includes(topFn)) {
        fnIds = [...fnIds, topFn];
    }

    let deptIds = organogram.department_ids || [];
    if (topDept && !deptIds.includes(topDept)) {
        deptIds = [...deptIds, topDept];
    }

    return {
        ...organogram,
        months: months,
        function_id: topFn || '',
        department_id: topDept || '',
        function_ids: fnIds,
        department_ids: deptIds,
    };
}

function loadStageSummary(callback) {
    const filters = getFilters();
    $('#stageSummaryBody').html('<tr><td colspan="' + (filters.months.length + 1) + '" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading Stage Summary...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.stage.summary") }}', filters, function(res) {
        if (!res.success) {
            $('#stageSummaryBody').html('<tr><td colspan="' + (filters.months.length + 1) + '" class="text-center py-4 text-danger">Failed to load stage summary</td></tr>');
            if (typeof callback === 'function') callback();
            return;
        }
        const data = res.data;
        // Use the filter months order (FY order) instead of sorting numerically
        const months = filters.months;

        // Update header
        let thHtml = '<th>Claim Status</th>';
        months.forEach(m => { thHtml += '<th>' + monthNames[m-1] + '</th>'; });
        $('#stageSummaryTable thead tr').html(thHtml);

        // Build rows
        // Build rows based on workflow
        let rows;
        if (isVVNR) {
            // VVNR: Claim → Submit → Approve → Payment → Courier → Verify
            rows = [
                { label: 'Total Claimant', key: 'total_claimant', cls: '' },
                { label: 'Less: No claims submitted', key: 'no_claims_submitted', cls: '' },
                { label: '<strong>Total Claims</strong>', key: 'total_claims', cls: 'fw-bold' },
                { label: 'Total Completed (Verified)', key: 'total_paid', cls: '' },
                { label: '<strong>% Complete</strong>', key: 'percent_complete', cls: 'fw-bold', suffix: '%' },
                { label: '', key: null, cls: 'separator' },
                { label: 'Total open, final submission not done', key: 'open_final_submission', cls: 'text-primary' },
                { label: 'Submitted, pending approval', key: 'pending_approval', cls: 'text-primary' },
                { label: 'Approved, pending payment', key: 'pending_payment', cls: 'text-primary' },
                { label: 'Paid, pending courier', key: 'paid_pending_courier', cls: 'text-primary' },
                { label: 'Courier received, pending verification', key: 'pending_verification', cls: 'text-primary' },
            ];
        } else {
            // VNR/Nursery: Claim → Submit → Courier → Verify → Approve → Finance → Payment
            rows = [
                { label: 'Total Claimant', key: 'total_claimant', cls: '' },
                { label: 'Less: No claims submitted', key: 'no_claims_submitted', cls: '' },
                { label: '<strong>Total Claims</strong>', key: 'total_claims', cls: 'fw-bold' },
                { label: 'Total Paid', key: 'total_paid', cls: '' },
                { label: '<strong>% Complete</strong>', key: 'percent_complete', cls: 'fw-bold', suffix: '%' },
                { label: '', key: null, cls: 'separator' },
                { label: 'Total open, final submission not done', key: 'open_final_submission', cls: 'text-primary' },
                { label: 'Total submitted, courier not sent', key: 'submitted_courier_not_sent', cls: 'text-primary' },
                { label: 'Total received, pending verification', key: 'pending_verification', cls: 'text-primary' },
                { label: 'Total verified, pending manager approval', key: 'pending_approval', cls: 'text-primary' },
                { label: 'Total approved, pending finance', key: 'pending_finance', cls: 'text-primary' },
                { label: 'Total finance processed, pending payment', key: 'pending_payment', cls: 'text-primary' },
            ];
        }

        let html = '';
        let totalClaims = 0, totalPaid = 0, pendVerify = 0, pendAppr = 0, pendPay = 0;

        rows.forEach(function(row) {
            if (row.key === null) {
                html += '<tr><td colspan="' + (months.length + 1) + '" style="height:8px;background:#f8f9fa;"></td></tr>';
                return;
            }
            html += '<tr>';
            html += '<td class="text-start ' + row.cls + '">' + row.label + '</td>';
            months.forEach(function(m) {
                const val = data[m] ? data[m][row.key] : 0;
                const display = row.suffix ? val + row.suffix : val;

                // Make stage detail rows clickable (the blue rows + no_claims_submitted)
                const clickableKeys = ['open_final_submission', 'submitted_courier_not_sent', 'pending_verification', 'pending_approval', 'pending_finance', 'pending_payment', 'paid_pending_courier', 'total_paid', 'no_claims_submitted'];
                if (clickableKeys.includes(row.key) && val > 0) {
                    // Map row.key to pendingStageSelect value
                    const stageMap = {
                        'open_final_submission': 'open',
                        'submitted_courier_not_sent': 'submitted_no_courier',
                        'pending_verification': 'pending_verification',
                        'pending_approval': 'pending_approval',
                        'pending_finance': 'pending_finance',
                        'pending_payment': 'pending_payment',
                        'paid_pending_courier': 'paid_pending_courier',
                        'total_paid': 'paid',
                        'no_claims_submitted': 'no_claim',
                    };
                    const stageVal = stageMap[row.key] || row.key;
                    html += '<td class="' + row.cls + '"><a href="javascript:void(0)" class="stage-detail-link fw-bold text-decoration-underline" data-stage="' + stageVal + '" data-month="' + m + '" style="cursor:pointer;">' + display + '</a></td>';
                } else {
                    html += '<td class="' + row.cls + '">' + display + '</td>';
                }

                // Accumulate KPIs
                if (row.key === 'total_claims') totalClaims += val;
                if (row.key === 'total_paid') totalPaid += val;
                if (row.key === 'pending_verification') pendVerify += val;
                if (row.key === 'pending_approval') pendAppr += val;
                if (row.key === 'pending_payment') pendPay += val;
            });
            html += '</tr>';
        });

        $('#stageSummaryBody').html(html);

        // Update KPIs
        const pct = totalClaims > 0 ? ((totalPaid / totalClaims) * 100).toFixed(1) + '%' : '0%';
        $('#kpiTotalClaims').text(totalClaims);
        $('#kpiTotalPaid').text(totalPaid);
        $('#kpiPercentComplete').text(pct);
        $('#kpiPendingVerify').text(pendVerify);
        $('#kpiPendingApproval').text(pendAppr);
        $('#kpiPendingPayment').text(pendPay);

        if (typeof callback === 'function') callback();
    }).fail(function() {
        $('#stageSummaryBody').html('<tr><td colspan="' + (filters.months.length + 1) + '" class="text-center py-4 text-danger">Server error while loading stage summary</td></tr>');
        if (typeof callback === 'function') callback();
    });
}

let pendingDataTable = null;

function loadPendingDetail() {
    const filters = getFilters();
    filters.stage = $('#pendingStageSelect').val();

    // Destroy existing DataTable if any
    if (pendingDataTable) {
        pendingDataTable.destroy();
        pendingDataTable = null;
    }
    $('#pendingDetailBody').html('<tr><td colspan="11" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.stage.pending.detail") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#pendingDetailBody').html('<tr><td colspan="11" class="text-center py-3 text-muted">No records found</td></tr>');
            return;
        }
        let html = '';
        res.data.forEach(function(item, i) {
            const statusBadge = item.EmpStatus === 'D'
                ? '<span class="badge bg-danger">Deactive</span>'
                : '<span class="badge bg-success">Active</span>';
            html += '<tr>' +
                '<td>' + (i+1) + '</td>' +
                '<td>' + (item.EmpCode || '') + '</td>' +
                '<td>' + (item.employee_name || '') + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + (item.department_name || '-') + '</td>' +
                '<td>' + (item.function_name || '-') + '</td>' +
                '<td>' + monthNames[(item.Month || 1) - 1] + '</td>' +
                '<td>' + (item.Total_Claim || 0) + '</td>' +
                '<td>₹' + (item.Claim_Amount || 0) + '</td>' +
                '<td>' + (item.DateOfSubmit && item.DateOfSubmit !== '0000-00-00' ? item.DateOfSubmit : '-') + '</td>' +
                '<td>' + (item.PostDate && item.PostDate !== '0000-00-00' ? item.PostDate : '-') + '</td>' +
                '</tr>';
        });
        $('#pendingDetailBody').html(html);

        // Initialize DataTable
        pendingDataTable = $('#pendingDetailTable').DataTable({
            pageLength: 25,
            order: [[6, 'asc']],
            language: { search: 'Search:', lengthMenu: 'Show _MENU_' },
            dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
        });
    });
}

function loadDeptSummary() {
    const filters = getFilters();
    filters.stage = $('#deptStageSelect').val();
    $('#deptSummaryContainer').html('<p class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</p>');

    $.get('{{ route("reports.claim.dashboard.department.stage.summary") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#deptSummaryContainer').html('<p class="text-center text-muted py-3">No data available</p>');
            return;
        }
        // Build pivot table
        const pivot = {};
        const allMonths = new Set();
        res.data.forEach(function(row) {
            const fn = row.function_name || 'Other';
            const dept = row.department_name || 'Other';
            const m = row.Month;
            allMonths.add(m);
            if (!pivot[fn]) pivot[fn] = {};
            if (!pivot[fn][dept]) pivot[fn][dept] = {};
            pivot[fn][dept][m] = (pivot[fn][dept][m] || 0) + row.count;
        });
        // Sort months in FY order
        const fyOrder = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        const sortedMonths = fyOrder.filter(m => allMonths.has(m));

        let html = '<table class="table table-bordered table-sm align-middle text-center mb-0">';
        html += '<thead style="background:#dde6ff;color:#1e3a8a;"><tr><th>Function</th><th>Department</th>';
        sortedMonths.forEach(m => { html += '<th>' + monthNames[m-1] + '</th>'; });
        html += '<th>Grand Total</th></tr></thead><tbody>';

        let grandTotal = 0;
        Object.keys(pivot).sort().forEach(function(fn) {
            let fnTotal = 0;
            const depts = Object.keys(pivot[fn]).sort();
            depts.forEach(function(dept, di) {
                let rowTotal = 0;
                html += '<tr>';
                html += di === 0 ? '<td rowspan="' + depts.length + '" class="fw-bold text-start">' + fn + '</td>' : '';
                html += '<td class="text-start">' + dept + '</td>';
                sortedMonths.forEach(function(m) {
                    const val = pivot[fn][dept][m] || '';
                    rowTotal += (pivot[fn][dept][m] || 0);
                    html += '<td>' + (val || '') + '</td>';
                });
                fnTotal += rowTotal;
                html += '<td class="fw-bold">' + rowTotal + '</td></tr>';
            });
            html += '<tr style="background:#e8f4fd;"><td colspan="2" class="fw-bold text-start">' + fn + ' Total</td>';
            sortedMonths.forEach(function(m) {
                let mTotal = 0;
                Object.keys(pivot[fn]).forEach(d => { mTotal += (pivot[fn][d][m] || 0); });
                html += '<td class="fw-bold">' + (mTotal || '') + '</td>';
            });
            html += '<td class="fw-bold">' + fnTotal + '</td></tr>';
            grandTotal += fnTotal;
        });

        html += '<tr style="background:#d4edda;"><td colspan="2" class="fw-bold text-center text-primary">Grand Total</td>';
        sortedMonths.forEach(function(m) {
            let mGrand = 0;
            Object.keys(pivot).forEach(fn => { Object.keys(pivot[fn]).forEach(d => { mGrand += (pivot[fn][d][m] || 0); }); });
            html += '<td class="fw-bold">' + mGrand + '</td>';
        });
        html += '<td class="fw-bold text-primary">' + grandTotal + '</td></tr>';
        html += '</tbody></table>';

        $('#deptSummaryContainer').html(html);
    });
}

// Helper function for Indian Date and Time Format (DD-MM-YYYY hh:mm:ss AM/PM)
function getIndianTimestamp(forFileName = false) {
    const now = new Date();
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();

    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12;
    hours = hours ? String(hours).padStart(2, '0') : '12';

    if (forFileName) {
        return `${day}-${month}-${year}_${hours}-${minutes}-${seconds}_${ampm}`;
    }
    return `${day}-${month}-${year} ${hours}:${minutes}:${seconds} ${ampm}`;
}

// Automatically format ApexCharts export dropdown menu with Indian Date and Time
$(document).on('click', '.apexcharts-menu-icon', function () {
    const $toolbar = $(this).closest('.apexcharts-toolbar');
    const $menu = $toolbar.find('.apexcharts-menu');
    const displayTimestamp = getIndianTimestamp(false);

    setTimeout(function() {
        if ($menu.find('.menu-timestamp-header').length === 0) {
            $menu.prepend(`
                <div class="apexcharts-menu-item menu-timestamp-header" style="font-size: 11px; color: #15648C; background-color: #f0f4f8; font-weight: 600; text-align: center; border-bottom: 1px solid #dee2e6; pointer-events: none; padding: 6px 8px; cursor: default;">
                    <i class="ri-calendar-line me-1"></i>${displayTimestamp}
                </div>
            `);
        } else {
            $menu.find('.menu-timestamp-header').html(`<i class="ri-calendar-line me-1"></i>${displayTimestamp}`);
        }

        // Add timestamp in titles of download items
        $menu.find('.exportSVG').attr('title', `Download SVG (${displayTimestamp})`);
        $menu.find('.exportPNG').attr('title', `Download PNG (${displayTimestamp})`);
        $menu.find('.exportCSV').attr('title', `Download CSV (${displayTimestamp})`);
    }, 10);
});

// Update download filenames & ensure timestamp is stamped onto the SVG/PNG graphic before export
$(document).on('click', '.apexcharts-menu-icon, .exportSVG, .exportPNG', function () {
    const currentFileTimestamp = getIndianTimestamp(true);
    const displayTimestamp = getIndianTimestamp(false);

    if (typeof submissionChart !== 'undefined' && submissionChart && submissionChart.w && submissionChart.w.config) {
        const exp = submissionChart.w.config.chart.toolbar.export;
        if (exp) {
            exp.png.filename = 'Claim_Submission_Timeline_' + currentFileTimestamp;
            exp.svg.filename = 'Claim_Submission_Timeline_' + currentFileTimestamp;
            exp.csv.filename = 'Claim_Submission_Timeline_' + currentFileTimestamp;
        }
        $('#submissionChart .apexcharts-subtitle-text').text('Generated on: ' + displayTimestamp);
    }
    if (typeof tatChart !== 'undefined' && tatChart && tatChart.w && tatChart.w.config) {
        const exp = tatChart.w.config.chart.toolbar.export;
        if (exp) {
            const title = (tatChart.w.config.title && tatChart.w.config.title.text) ? tatChart.w.config.title.text.replace(/[^a-zA-Z0-9_-]/g, '_') : 'TAT_Analysis';
            exp.png.filename = title + '_' + currentFileTimestamp;
            exp.svg.filename = title + '_' + currentFileTimestamp;
            exp.csv.filename = title + '_' + currentFileTimestamp;
        }
        $('#tatChart .apexcharts-subtitle-text').text('(Avg. Days between stages) • Generated on: ' + displayTimestamp);
    }
});

// Custom CSV download to inject Indian timestamp directly INSIDE the downloaded CSV file
$(document).on('click', '.exportCSV', function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    const $card = $(this).closest('.card-body');
    let chartInstance = null;
    let defaultTitle = 'Chart_Report';

    if ($card.find('#submissionChart').length) {
        chartInstance = submissionChart;
        defaultTitle = 'Claim_Submission_Timeline';
    } else if ($card.find('#tatChart').length) {
        chartInstance = tatChart;
        defaultTitle = 'TAT_Monthly_Analysis';
    }

    if (chartInstance && chartInstance.w && chartInstance.w.config) {
        const title = (chartInstance.w.config.title && chartInstance.w.config.title.text) ? chartInstance.w.config.title.text : defaultTitle;
        const displayTs = getIndianTimestamp(false);
        const fileTs = getIndianTimestamp(true);
        const categories = chartInstance.w.config.xaxis.categories || [];
        const series = chartInstance.w.config.series || [];

        let csv = `"${title}"\r\n`;
        csv += `"Generated on (IST): ${displayTs}"\r\n\r\n`;

        const headers = ['Category', ...series.map(s => `"${(s.name || '').replace(/"/g, '""')}"`)];
        csv += headers.join(',') + '\r\n';

        for (let i = 0; i < categories.length; i++) {
            const rawCat = categories[i];
            const catName = Array.isArray(rawCat) ? rawCat[0] : rawCat;
            const row = [`"${catName}"`];
            for (let s = 0; s < series.length; s++) {
                row.push(series[s].data && series[s].data[i] !== undefined ? series[s].data[i] : 0);
            }
            csv += row.join(',') + '\r\n';
        }

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${defaultTitle}_${fileTs}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
});

function loadSubmissionChart() {
    const filters = getFilters();
    $.get('{{ route("reports.claim.dashboard.submission.timeline") }}', filters, function(res) {
        if (!res.success) return;
        const data = res.data;
        // Use FY order from filters
        const months = filters.months;
        const categories = months.map(m => {
            const mData = data[m] || {};
            const sum = (Number(mData.between_1_7) || 0) +
                        (Number(mData.between_8_15) || 0) +
                        (Number(mData.after_15th_next_month) || 0) +
                        (Number(mData.after_2_months) || 0);
            return [monthNames[m - 1], `Total: ${sum}`];
        });

        const series = [
            { name: 'Between 1-7', data: months.map(m => data[m].between_1_7) },
            { name: 'Between 8-15', data: months.map(m => data[m].between_8_15) },
            { name: 'After 15th next month', data: months.map(m => data[m].after_15th_next_month) },
            { name: 'After 2 months', data: months.map(m => data[m].after_2_months) },
        ];

        const chartTs = getIndianTimestamp(true);
        const displayTs = getIndianTimestamp(false);
        if (submissionChart) submissionChart.destroy();
        submissionChart = new ApexCharts(document.querySelector('#submissionChart'), {
            chart: {
                type: 'bar',
                height: 380,
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false,
                    },
                    export: {
                        csv: {
                            filename: 'Claim_Submission_Timeline_' + chartTs,
                        },
                        svg: {
                            filename: 'Claim_Submission_Timeline_' + chartTs,
                        },
                        png: {
                            filename: 'Claim_Submission_Timeline_' + chartTs,
                        }
                    }
                }
            },
            series: series,
            xaxis: {
                categories: categories,
                labels: {
                    rotate: 0,
                    trim: false,
                    style: {
                        fontSize: '11px',
                        fontWeight: 600,
                    }
                }
            },
            tooltip: {
                x: {
                    formatter: function(val) {
                        return Array.isArray(val) ? val.join(' - ') : val;
                    }
                }
            },
            plotOptions: { bar: { columnWidth: '60%', dataLabels: { position: 'top' } } },
            dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600, colors: ['#000000'] } },
            colors: ['#ffc107', '#0dcaf0', '#fd7e14', '#dc3545'],
            title: { text: 'Claim Submission Timeline', align: 'center', style: { fontSize: '16px', color: '#15648C' } },
            subtitle: { text: 'Generated on: ' + displayTs, align: 'center', style: { fontSize: '11px', color: '#666' } },
            legend: { position: 'top' },
        });
        submissionChart.render();
    });
}

function loadTATChart() {
    const filters = getFilters();
    $.get('{{ route("reports.claim.dashboard.tat.analysis") }}', filters, function(res) {
        if (!res.success) return;
        const data = res.data;
        // Use FY order from filters
        const months = filters.months;
        const categories = months.map(m => monthNames[m-1]);

        // Detect workflow from first month's data
        const firstMonth = data[months[0]] || {};
        const isVVNR = (firstMonth.workflow === 'vvnr');

        let series, colors, chartTitle;

        if (isVVNR) {
            // VVNR: Submit → Approve → Pay → Courier → Verify
            series = [
                { name: 'Submit to Approve', data: months.map(m => data[m].submit_to_approve || 0) },
                { name: 'Approve to Pay', data: months.map(m => data[m].approve_to_pay || 0) },
                { name: 'Pay to Courier', data: months.map(m => data[m].pay_to_courier || 0) },
                { name: 'Courier to Receive', data: months.map(m => data[m].courier_to_receive || 0) },
                { name: 'Receive to Verify', data: months.map(m => data[m].receive_to_verify || 0) },
                { name: 'Total (Submit to Verify)', data: months.map(m => data[m].total_tat || 0) },
            ];
            colors = ['#E8A0A0', '#E06060', '#7BC47F', '#F5D76E', '#D4A5D4', '#7FB3D4'];
            chartTitle = 'TAT - MONTHLY ANALYSIS (VVNR)';
        } else {
            // VNR: Submit → Courier → Receive → Verify → Approve → Pay
            series = [
                { name: 'Submit to Courier posting', data: months.map(m => data[m].submit_to_courier || 0) },
                { name: 'Post to receive', data: months.map(m => data[m].post_to_receive || 0) },
                { name: 'Receive to verify', data: months.map(m => data[m].receive_to_verify || 0) },
                { name: 'Verify to approve', data: months.map(m => data[m].verify_to_approve || 0) },
                { name: 'Approve to pay', data: months.map(m => data[m].approve_to_pay || 0) },
                { name: 'Courier Receipt to pay', data: months.map(m => data[m].courier_receipt_to_pay || 0) },
            ];
            colors = ['#E8A0A0', '#7BC47F', '#F5D76E', '#D4A5D4', '#E06060', '#7FB3D4'];
            chartTitle = 'TAT - MONTHLY ANALYSIS (VNR)';
        }

        const chartTs = getIndianTimestamp(true);
        const displayTs = getIndianTimestamp(false);
        const safeTitle = (chartTitle || 'TAT_Analysis').replace(/[^a-zA-Z0-9_-]/g, '_');

        if (tatChart) tatChart.destroy();
        tatChart = new ApexCharts(document.querySelector('#tatChart'), {
            chart: {
                type: 'bar',
                height: 380,
                stacked: true,
                stackType: 'normal',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false,
                    },
                    export: {
                        csv: {
                            filename: safeTitle + '_' + chartTs,
                        },
                        svg: {
                            filename: safeTitle + '_' + chartTs,
                        },
                        png: {
                            filename: safeTitle + '_' + chartTs,
                        }
                    }
                }
            },
            series: series,
            xaxis: { categories: categories },
            yaxis: { title: { text: 'Month' } },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '60%',
                    dataLabels: {
                        total: {
                            enabled: true,
                            style: { fontSize: '12px', fontWeight: 700, color: '#000000' },
                            formatter: function(val, opts) {
                                const dpIdx = (opts && typeof opts.dataPointIndex === 'number') ? opts.dataPointIndex : -1;
                                if (dpIdx >= 0 && dpIdx < months.length) {
                                    const m = months[dpIdx];
                                    const mData = data[m] || {};
                                    if (isVVNR) {
                                        const sum = (Number(mData.submit_to_approve) || 0) +
                                                    (Number(mData.approve_to_pay) || 0) +
                                                    (Number(mData.pay_to_courier) || 0) +
                                                    (Number(mData.courier_to_receive) || 0) +
                                                    (Number(mData.receive_to_verify) || 0);
                                        return sum > 0 ? sum : '';
                                    } else {
                                        const sum = (Number(mData.submit_to_courier) || 0) +
                                                    (Number(mData.post_to_receive) || 0) +
                                                    (Number(mData.receive_to_verify) || 0) +
                                                    (Number(mData.verify_to_approve) || 0) +
                                                    (Number(mData.approve_to_pay) || 0);
                                        return sum > 0 ? sum : '';
                                    }
                                }
                                return val > 0 ? val : '';
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 600, colors: ['#000000'] }, formatter: function(val) { return val > 0 ? val : ''; } },
            colors: colors,
            title: { text: chartTitle, align: 'center', style: { fontSize: '18px', color: '#15648C', fontWeight: 'bold' } },
            subtitle: { text: '(Avg. Days between stages) • Generated on: ' + displayTs, align: 'center', style: { fontSize: '11px', color: '#666' } },
            legend: { position: 'top', horizontalAlign: 'center', fontSize: '12px' },
            tooltip: { y: { formatter: function(val) { return val + ' days'; } } },
        });
        tatChart.render();
    });
}

$(document).ready(function() {
    loadStageSummary();

    function reloadActiveTab() {
        const target = $('#reportTabs .nav-link.active').attr('href');
        if (target === '#tabSubmission') loadSubmissionChart();
        else if (target === '#tabTAT') loadTATChart();
        else if (target === '#tabPendingDetail') loadPendingDetail();
        else if (target === '#tabDeptSummary') loadDeptSummary();
        else if (target === '#tabCourier') loadCourierStatus();
        else if (target === '#tabManagement') loadManagementStatus();
        else if (target === '#tabImprest') loadImprestPromotions();
        else if (target === '#tabEmployeeWiseTAT') loadEmployeeWiseTATAnalysis();
        else if (target === '#tabClaimWiseTAT') loadClaimWiseTATAnalysis();
        else if (target === '#tabSubmissionReturn') loadSubmissionReturnReport();
    }

    $('#btnApplyFilter').on('click', function() {
        const $btn = $(this);
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');

        // Destroy cached charts so they recreate with new filter data
        if (submissionChart) { submissionChart.destroy(); submissionChart = null; }
        if (tatChart) { tatChart.destroy(); tatChart = null; }

        loadStageSummary(function() {
            $btn.prop('disabled', false).html(origHtml);
        });

        // Also refresh the current active tab if not Stage Summary
        const activeTab = $('#reportTabs .nav-link.active').attr('href');
        if (activeTab && activeTab !== '#tabStageSummary') {
            reloadActiveTab();
        }
    });

    $('#pendingStageSelect').on('change', loadPendingDetail);
    $('#deptStageSelect').on('change', loadDeptSummary);

    // Click on stage numbers in summary table → open modal with detail for that stage + month
    $(document).on('click', '.stage-detail-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const stage = $(this).attr('data-stage');
        const month = $(this).attr('data-month');
        
        console.log('Stage Detail Click:', stage, month);
        
        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const stageLabels = {
            'open': 'Pending Submission',
            'submitted_no_courier': 'Submitted - Pending Courier',
            'pending_verification': 'Courier Completed - Pending Verification',
            'pending_approval': 'Verified - Pending Approval',
            'pending_finance': 'Approved - Pending Finance',
            'pending_payment': 'Finance Processed - Pending Payment',
            'paid_pending_courier': 'Paid - Pending Courier',
            'paid': 'Paid',
            'no_claim': 'No Claim Employee List',
        };

        $('#stageDetailBadge').text(stageLabels[stage] || stage);
        $('#stageDetailMonthBadge').text(monthNames[parseInt(month) - 1] || '');
        $('#stageDetailCount').text('Loading...');
        
        // Destroy existing DataTable before replacing content
        if ($.fn.DataTable.isDataTable('#stageDetailModalTable')) {
            $('#stageDetailModalTable').DataTable().clear().destroy();
        }
        $('#stageDetailModalBody').html('<tr><td colspan="11" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');
        $('#stageDetailModal').modal('show');

        // Store current filter for export
        $('#btnExportStageModal').data('stage', stage).data('month', month);

        // Build URL manually to avoid jQuery serialization issues
        var url = '{{ route("reports.claim.dashboard.stage.pending.detail") }}';
        url += '?stage=' + encodeURIComponent(stage);
        url += '&months%5B%5D=' + encodeURIComponent(month);
        var fnId = $('#filterFunction').val();
        var deptId = $('#filterDepartment').val();
        if (fnId) url += '&function_id=' + encodeURIComponent(fnId);
        if (deptId) url += '&department_id=' + encodeURIComponent(deptId);
        if (window.getOrganogramFilterQueryParams) {
            url += window.getOrganogramFilterQueryParams();
        }
        
        console.log('Request URL:', url);

        $.get(url, function(res) {
            console.log('Response:', res);
            if (!res.success || !res.data || !res.data.length) {
                $('#stageDetailModalBody').html('<tr><td colspan="11" class="text-center py-4 text-muted">No records found</td></tr>');
                $('#stageDetailCount').text('0 records');
                return;
            }
            let html = '';
            const fmtDt = function(d) { return (d && d !== '0000-00-00' && d !== null) ? d : '-'; };
            res.data.forEach(function(row, i) {
                html += '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td>' + (row.EmpCode || '-') + '</td>' +
                    '<td>' + (row.employee_name || '-') + '</td>' +
                    '<td>' + (row.function_name || '-') + '</td>' +
                    '<td>' + (row.department_name || '-') + '</td>' +
                    '<td>' + (row.Month ? monthNames[parseInt(row.Month) - 1] : '-') + '</td>' +
                    '<td>' + fmtDt(row.DateOfSubmit) + '</td>' +
                    '<td>' + fmtDt(row.RecevingDate) + '</td>' +
                    '<td>' + fmtDt(row.Verified_Date) + '</td>' +
                    '<td>' + fmtDt(row.Approved_Date) + '</td>' +
                    '<td>' + fmtDt(row.Fin_PayDate) + '</td>' +
                    '</tr>';
            });
            $('#stageDetailModalBody').html(html);
            $('#stageDetailCount').text(res.data.length + ' records');

            // Init DataTable on modal table
            if ($.fn.DataTable.isDataTable('#stageDetailModalTable')) {
                $('#stageDetailModalTable').DataTable().destroy();
            }
            $('#stageDetailModalTable').DataTable({
                pageLength: 25,
                order: [[1, 'asc']],
                destroy: true,
            });
        }).fail(function(xhr) {
            console.log('Error:', xhr.responseText);
            $('#stageDetailModalBody').html('<tr><td colspan="11" class="text-center py-4 text-danger">Failed to load data</td></tr>');
            $('#stageDetailCount').text('Error');
        });
    });

    // Export from modal — same stage + month
    $('#btnExportStageModal').on('click', function() {
        const stage = $(this).data('stage');
        const month = $(this).data('month');

        // Build export URL
        let url = '{{ route("reports.claim.dashboard.stage.pending.detail") }}';
        url += '?export=excel&stage=' + stage + '&months[]=' + month;
        const fnId = $('#filterFunction').val();
        const deptId = $('#filterDepartment').val();
        if (fnId) url += '&function_id=' + fnId;
        if (deptId) url += '&department_id=' + deptId;
        if (window.getOrganogramFilterQueryParams) {
            url += window.getOrganogramFilterQueryParams();
        }

        window.location.href = url;
    });

    // Load tab content when tab is shown
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        const target = $(e.target).attr('href');
        if (target === '#tabStageSummary') loadStageSummary();
        else if (target === '#tabSubmission') loadSubmissionChart();
        else if (target === '#tabTAT') loadTATChart();
        else if (target === '#tabPendingDetail') loadPendingDetail();
        else if (target === '#tabDeptSummary') loadDeptSummary();
        else if (target === '#tabCourier') loadCourierStatus();
        else if (target === '#tabManagement') loadManagementStatus();
        else if (target === '#tabImprest') loadImprestPromotions();
        else if (target === '#tabEmployeeWiseTAT') loadEmployeeWiseTATAnalysis();
        else if (target === '#tabClaimWiseTAT') loadClaimWiseTATAnalysis();
        else if (target === '#tabSubmissionReturn') loadSubmissionReturnReport();
    });

function loadCourierStatus() {
    const filters = getFilters();
    $('#courierStatusContainer').html('<p class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</p>');

    $.get('{{ route("reports.claim.dashboard.courier.posting.status") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#courierStatusContainer').html('<p class="text-center text-muted py-3">No data available</p>');
            return;
        }

        // Build pivot: Function → Department → {no, yes, total}
        const pivot = {};
        res.data.forEach(function(row) {
            const fn = row.function_name || 'Other';
            const dept = row.department_name || 'Other';
            if (!pivot[fn]) pivot[fn] = { depts: {}, totalNo: 0, totalYes: 0, totalGrand: 0 };
            pivot[fn].depts[dept] = { no: parseInt(row.courier_no), yes: parseInt(row.courier_yes), total: parseInt(row.grand_total) };
            pivot[fn].totalNo += parseInt(row.courier_no);
            pivot[fn].totalYes += parseInt(row.courier_yes);
            pivot[fn].totalGrand += parseInt(row.grand_total);
        });

        let html = '<table class="table table-bordered table-sm align-middle text-center mb-0">';
        html += '<thead><tr style="background:#dde6ff;color:#1e3a8a;">';
        html += '<th rowspan="2">Function name</th><th rowspan="2">Department</th>';
        html += '<th colspan="2">Posting date</th><th rowspan="2">Grand Total</th><th colspan="2">%</th>';
        html += '</tr><tr style="background:#dde6ff;color:#1e3a8a;"><th>No</th><th>Yes</th><th>No</th><th>Yes</th></tr></thead><tbody>';

        let grandNo = 0, grandYes = 0, grandTotal = 0;

        Object.keys(pivot).sort().forEach(function(fn) {
            const fnData = pivot[fn];
            const depts = Object.keys(fnData.depts).sort();
            const pctNo = fnData.totalGrand > 0 ? Math.round((fnData.totalNo / fnData.totalGrand) * 100) : 0;
            const pctYes = fnData.totalGrand > 0 ? Math.round((fnData.totalYes / fnData.totalGrand) * 100) : 0;

            // Function total row (pink background)
            html += '<tr style="background:#FFE4E1;font-weight:bold;">';
            html += '<td class="text-start">' + fn + '</td><td></td>';
            html += '<td>' + fnData.totalNo + '</td><td>' + fnData.totalYes + '</td>';
            html += '<td>' + fnData.totalGrand + '</td>';
            html += '<td>' + pctNo + '%</td><td>' + pctYes + '%</td></tr>';

            // Department rows
            depts.forEach(function(dept) {
                const d = fnData.depts[dept];
                html += '<tr><td></td><td class="text-start">' + dept + '</td>';
                html += '<td>' + d.no + '</td><td>' + d.yes + '</td>';
                html += '<td>' + d.total + '</td><td></td><td></td></tr>';
            });

            grandNo += fnData.totalNo;
            grandYes += fnData.totalYes;
            grandTotal += fnData.totalGrand;
        });

        const gPctNo = grandTotal > 0 ? Math.round((grandNo / grandTotal) * 100) : 0;
        const gPctYes = grandTotal > 0 ? Math.round((grandYes / grandTotal) * 100) : 0;
        html += '<tr style="background:#d4edda;font-weight:bold;"><td colspan="2" class="text-center text-primary">Grand Total</td>';
        html += '<td>' + grandNo + '</td><td>' + grandYes + '</td><td>' + grandTotal + '</td>';
        html += '<td>' + gPctNo + '%</td><td>' + gPctYes + '%</td></tr>';
        html += '</tbody></table>';

        $('#courierStatusContainer').html(html);
    });
}

function loadManagementStatus() {
    const filters = getFilters();
    $('#mgmtBody').html('<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.management.claim.status") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#mgmtBody').html('<tr><td colspan="9" class="text-center py-3 text-muted">No management claims pending</td></tr>');
            return;
        }
        let html = '';
        res.data.forEach(function(item, i) {
            const statusColors = {
                'Pending Payment': 'bg-warning text-dark',
                'Pending Approval': 'bg-info text-dark',
                'Pending Verification': 'bg-secondary text-white',
                'Courier not received': 'bg-danger text-white',
            };
            const badgeClass = statusColors[item.pending_status] || 'bg-secondary';
            html += '<tr>' +
                '<td>' + (i+1) + '</td>' +
                '<td>' + (item.EmpCode || '') + '</td>' +
                '<td>' + (item.employee_name || '') + '</td>' +
                '<td>' + (item.grade_name || '-') + '</td>' +
                '<td>' + (item.department_name || '-') + '</td>' +
                '<td><span class="badge ' + badgeClass + '">' + (item.pending_status || '-') + '</span></td>' +
                '<td>' + monthNames[(item.Month || 1) - 1] + '</td>' +
                '<td>' + (item.DateOfSubmit && item.DateOfSubmit !== '0000-00-00' ? item.DateOfSubmit : '-') + '</td>' +
                '<td>₹' + (item.Claim_Amount || 0) + '</td>' +
                '</tr>';
        });
        $('#mgmtBody').html(html);
    });
}

function loadImprestPromotions() {
    const filters = getFilters();
    $('#imprestBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.imprest.promotions") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#imprestBody').html('<tr><td colspan="7" class="text-center py-3 text-muted">No imprest records found</td></tr>');
            return;
        }
        let html = '';
        res.data.forEach(function(item, i) {
            html += '<tr>' +
                '<td>' + (i+1) + '</td>' +
                '<td>' + (item.EmpCode || '') + '</td>' +
                '<td>' + (item.employee_name || '') + '</td>' +
                '<td>' + (item.department_name || '-') + '</td>' +
                '<td>' + (item.grade_name || '-') + '</td>' +
                '<td>₹' + parseFloat(item.amount || 0).toLocaleString() + '</td>' +
                '<td>' + (item.released_date && item.released_date !== '0000-00-00' ? item.released_date : '-') + '</td>' +
                '</tr>';
        });
        $('#imprestBody').html(html);
    });
}

let empWiseTATAllData = [];
let empWiseTATCurrentPage = 1;
const empWiseTATPageSize = 10;

function loadEmployeeWiseTATAnalysis() {
    const filters = getFilters();
    $('#employeeWiseTATBody').html('<tr><td colspan="13" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.employee.wise.tat") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#employeeWiseTATBody').html('<tr><td colspan="13" class="text-center py-3 text-muted">No data available</td></tr>');
            $('#empWiseTATInfo').text('Showing 0 of 0 records');
            return;
        }

        // Store all data for filtering and sorting
        empWiseTATAllData = res.data;
        empWiseTATCurrentPage = 1;

        // Update headers based on workflow
        const firstRow = res.data[0] || {};
        const isVVNR = (firstRow.submit_to_approve !== undefined);
        if (isVVNR) {
            $('#tat-header-1').text('Submit→Approve');
            $('#tat-header-2').text('Approve→Pay');
            $('#tat-header-3').text('Pay→Courier');
            $('#tat-header-4').text('Courier→Receive');
            $('#tat-header-5').text('Receive→Verify');
        } else {
            $('#tat-header-1').text('Submit→Courier');
            $('#tat-header-2').text('Post→Receive');
            $('#tat-header-3').text('Receive→Verify');
            $('#tat-header-4').text('Verify→Approve');
            $('#tat-header-5').text('Approve→Pay');
        }

        renderEmployeeWiseTATPage();
    });
}

function filterAndSortEmployeeWiseTAT() {
    const searchText = $('#empWiseTATSearch').val().toLowerCase();
    const sortBy = $('#empWiseTATSort').val();

    // Filter data
    let filteredData = empWiseTATAllData.filter(item => {
        const text = (item.employee_name || '') + (item.EmpCode || '') + (item.department_name || '');
        return text.toLowerCase().includes(searchText);
    });

    // Sort data
    const isVVNR = (empWiseTATAllData[0] && empWiseTATAllData[0].submit_to_approve !== undefined);
    switch(sortBy) {
        case 'tat-asc':
            filteredData.sort((a, b) => {
                const aTat = isVVNR ? (a.total_tat || 0) : (a.courier_receipt_to_pay || 0);
                const bTat = isVVNR ? (b.total_tat || 0) : (b.courier_receipt_to_pay || 0);
                return aTat - bTat;
            });
            break;
        case 'claims-desc':
            filteredData.sort((a, b) => (b.total_claims || 0) - (a.total_claims || 0));
            break;
        case 'claims-asc':
            filteredData.sort((a, b) => (a.total_claims || 0) - (b.total_claims || 0));
            break;
        case 'name-asc':
            filteredData.sort((a, b) => (a.employee_name || '').localeCompare(b.employee_name || ''));
            break;
        case 'tat-desc':
        default:
            filteredData.sort((a, b) => {
                const aTat = isVVNR ? (a.total_tat || 0) : (a.courier_receipt_to_pay || 0);
                const bTat = isVVNR ? (b.total_tat || 0) : (b.courier_receipt_to_pay || 0);
                return bTat - aTat;
            });
    }

    empWiseTATAllData = filteredData;
    empWiseTATCurrentPage = 1;
    renderEmployeeWiseTATPage();
}

function renderEmployeeWiseTATPage() {
    const startIdx = (empWiseTATCurrentPage - 1) * empWiseTATPageSize;
    const endIdx = startIdx + empWiseTATPageSize;
    const pageData = empWiseTATAllData.slice(startIdx, endIdx);

    const isVVNR = (empWiseTATAllData[0] && empWiseTATAllData[0].submit_to_approve !== undefined);

    let html = '';
    pageData.forEach(function(item, idx) {
        const tat1 = isVVNR ? (item.submit_to_approve || 0) : (item.submit_to_courier || 0);
        const tat2 = isVVNR ? (item.approve_to_pay || 0) : (item.post_to_receive || 0);
        const tat3 = item.receive_to_verify || 0;
        const tat4 = isVVNR ? (item.courier_to_receive || 0) : (item.verify_to_approve || 0);
        const tat5 = isVVNR ? (item.receive_to_verify || 0) : (item.approve_to_pay || 0);
        const totalTat = isVVNR ? (item.total_tat || 0) : (item.courier_receipt_to_pay || 0);

        html += '<tr>' +
            '<td>' + (startIdx + idx + 1) + '</td>' +
            '<td>' + (item.EmpCode || '') + '</td>' +
            '<td>' + (item.employee_name || '') + '</td>' +
            '<td>' + (item.department_name || '-') + '</td>' +
            '<td>' + (item.function_name || '-') + '</td>' +
            '<td><span class="badge bg-primary">' + (item.total_claims || 0) + '</span></td>' +
            '<td>₹' + parseInt(item.avg_claim_amount || 0).toLocaleString() + '</td>' +
            '<td>' + tat1 + '</td>' +
            '<td>' + tat2 + '</td>' +
            '<td>' + tat3 + '</td>' +
            '<td>' + tat4 + '</td>' +
            '<td>' + tat5 + '</td>' +
            '<td class="fw-bold text-primary">' + totalTat + '</td>' +
            '</tr>';
    });

    if (html === '') {
        html = '<tr><td colspan="13" class="text-center py-3 text-muted">No matching records found</td></tr>';
    }

    $('#employeeWiseTATBody').html(html);
    $('#empWiseTATInfo').text(`Showing ${startIdx + 1} to ${Math.min(endIdx, empWiseTATAllData.length)} of ${empWiseTATAllData.length} records`);

    // Render pagination
    const totalPages = Math.ceil(empWiseTATAllData.length / empWiseTATPageSize);
    let paginationHtml = '';

    if (empWiseTATCurrentPage > 1) {
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:empWiseTATGoToPage(1)">First</a></li>';
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:empWiseTATGoToPage(' + (empWiseTATCurrentPage - 1) + ')">Previous</a></li>';
    }

    for (let i = Math.max(1, empWiseTATCurrentPage - 2); i <= Math.min(totalPages, empWiseTATCurrentPage + 2); i++) {
        if (i === empWiseTATCurrentPage) {
            paginationHtml += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
        } else {
            paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:empWiseTATGoToPage(' + i + ')">' + i + '</a></li>';
        }
    }

    if (empWiseTATCurrentPage < totalPages) {
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:empWiseTATGoToPage(' + (empWiseTATCurrentPage + 1) + ')">Next</a></li>';
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:empWiseTATGoToPage(' + totalPages + ')">Last</a></li>';
    }

    $('#empWiseTATPagination').html(paginationHtml);
}

function empWiseTATGoToPage(page) {
    empWiseTATCurrentPage = page;
    renderEmployeeWiseTATPage();
}

let claimWiseTATAllData = [];
let claimWiseTATCurrentPage = 1;
const claimWiseTATPageSize = 10;

function loadClaimWiseTATAnalysis() {
    const filters = getFilters();
    $('#claimWiseTATBody').html('<tr><td colspan="11" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.claim.wise.tat") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#claimWiseTATBody').html('<tr><td colspan="11" class="text-center py-3 text-muted">No data available</td></tr>');
            $('#claimWiseTATInfo').text('Showing 0 of 0 records');
            return;
        }

        // Store all data for filtering and sorting
        claimWiseTATAllData = res.data;
        claimWiseTATCurrentPage = 1;

        // Update headers based on workflow
        const firstRow = res.data[0] || {};
        const isVVNR = (firstRow.submit_to_approve !== undefined);
        if (isVVNR) {
            $('#claim-tat-header-1').text('Submit→Approve');
            $('#claim-tat-header-2').text('Approve→Pay');
            $('#claim-tat-header-3').text('Pay→Courier');
            $('#claim-tat-header-4').text('Courier→Receive');
            $('#claim-tat-header-5').text('Receive→Verify');
        } else {
            $('#claim-tat-header-1').text('Submit→Courier');
            $('#claim-tat-header-2').text('Post→Receive');
            $('#claim-tat-header-3').text('Receive→Verify');
            $('#claim-tat-header-4').text('Verify→Approve');
            $('#claim-tat-header-5').text('Approve→Pay');
        }

        renderClaimWiseTATPage();
    });
}

function filterAndSortClaimWiseTAT() {
    const searchText = $('#claimWiseTATSearch').val().toLowerCase();
    const sortBy = $('#claimWiseTATSort').val();

    // Filter data
    let filteredData = claimWiseTATAllData.filter(item => {
        const text = (item.claim_type || '');
        return text.toLowerCase().includes(searchText);
    });

    // Sort data
    const isVVNR = (claimWiseTATAllData[0] && claimWiseTATAllData[0].submit_to_approve !== undefined);
    switch(sortBy) {
        case 'tat-asc':
            filteredData.sort((a, b) => {
                const aTat = isVVNR ? (a.total_tat || 0) : (a.courier_receipt_to_pay || 0);
                const bTat = isVVNR ? (b.total_tat || 0) : (b.courier_receipt_to_pay || 0);
                return aTat - bTat;
            });
            break;
        case 'claims-desc':
            filteredData.sort((a, b) => (b.total_claims || 0) - (a.total_claims || 0));
            break;
        case 'claims-asc':
            filteredData.sort((a, b) => (a.total_claims || 0) - (b.total_claims || 0));
            break;
        case 'amount-desc':
            filteredData.sort((a, b) => (b.total_amount || 0) - (a.total_amount || 0));
            break;
        case 'tat-desc':
        default:
            filteredData.sort((a, b) => {
                const aTat = isVVNR ? (a.total_tat || 0) : (a.courier_receipt_to_pay || 0);
                const bTat = isVVNR ? (b.total_tat || 0) : (b.courier_receipt_to_pay || 0);
                return bTat - aTat;
            });
    }

    claimWiseTATAllData = filteredData;
    claimWiseTATCurrentPage = 1;
    renderClaimWiseTATPage();
}

function renderClaimWiseTATPage() {
    const startIdx = (claimWiseTATCurrentPage - 1) * claimWiseTATPageSize;
    const endIdx = startIdx + claimWiseTATPageSize;
    const pageData = claimWiseTATAllData.slice(startIdx, endIdx);

    const isVVNR = (claimWiseTATAllData[0] && claimWiseTATAllData[0].submit_to_approve !== undefined);

    let html = '';
    pageData.forEach(function(item, idx) {
        const tat1 = isVVNR ? (item.submit_to_approve || 0) : (item.submit_to_courier || 0);
        const tat2 = isVVNR ? (item.approve_to_pay || 0) : (item.post_to_receive || 0);
        const tat3 = item.receive_to_verify || 0;
        const tat4 = isVVNR ? (item.courier_to_receive || 0) : (item.verify_to_approve || 0);
        const tat5 = isVVNR ? (item.receive_to_verify || 0) : (item.approve_to_pay || 0);
        const totalTat = isVVNR ? (item.total_tat || 0) : (item.courier_receipt_to_pay || 0);

        html += '<tr>' +
            '<td>' + (startIdx + idx + 1) + '</td>' +
            '<td>' + (item.claim_type || '-') + '</td>' +
            '<td><span class="badge bg-info">' + (item.total_claims || 0) + '</span></td>' +
            '<td>₹' + parseInt(item.avg_claim_amount || 0).toLocaleString() + '</td>' +
            '<td>₹' + parseInt(item.total_amount || 0).toLocaleString() + '</td>' +
            '<td>' + tat1 + '</td>' +
            '<td>' + tat2 + '</td>' +
            '<td>' + tat3 + '</td>' +
            '<td>' + tat4 + '</td>' +
            '<td>' + tat5 + '</td>' +
            '<td class="fw-bold text-primary">' + totalTat + '</td>' +
            '</tr>';
    });

    if (html === '') {
        html = '<tr><td colspan="11" class="text-center py-3 text-muted">No matching records found</td></tr>';
    }

    $('#claimWiseTATBody').html(html);
    $('#claimWiseTATInfo').text(`Showing ${startIdx + 1} to ${Math.min(endIdx, claimWiseTATAllData.length)} of ${claimWiseTATAllData.length} records`);

    // Render pagination
    const totalPages = Math.ceil(claimWiseTATAllData.length / claimWiseTATPageSize);
    let paginationHtml = '';

    if (claimWiseTATCurrentPage > 1) {
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:claimWiseTATGoToPage(1)">First</a></li>';
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:claimWiseTATGoToPage(' + (claimWiseTATCurrentPage - 1) + ')">Previous</a></li>';
    }

    for (let i = Math.max(1, claimWiseTATCurrentPage - 2); i <= Math.min(totalPages, claimWiseTATCurrentPage + 2); i++) {
        if (i === claimWiseTATCurrentPage) {
            paginationHtml += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
        } else {
            paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:claimWiseTATGoToPage(' + i + ')">' + i + '</a></li>';
        }
    }

    if (claimWiseTATCurrentPage < totalPages) {
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:claimWiseTATGoToPage(' + (claimWiseTATCurrentPage + 1) + ')">Next</a></li>';
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:claimWiseTATGoToPage(' + totalPages + ')">Last</a></li>';
    }

    $('#claimWiseTATPagination').html(paginationHtml);
}

function claimWiseTATGoToPage(page) {
    claimWiseTATCurrentPage = page;
    renderClaimWiseTATPage();
}

let submissionReturnAllData = [];
let submissionReturnCurrentPage = 1;
const submissionReturnPageSize = 15;

window.loadSubmissionReturnReport = function() {
    const filters = getFilters();
    $('#submissionReturnBody').html('<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("reports.claim.dashboard.submission.return") }}', filters, function(res) {
        if (!res.success || !res.data.length) {
            $('#submissionReturnBody').html('<tr><td colspan="9" class="text-center py-3 text-muted">No data available</td></tr>');
            $('#submissionReturnInfo').text('Showing 0 of 0 records');
            return;
        }

        submissionReturnAllData = res.data;
        submissionReturnCurrentPage = 1;
        renderSubmissionReturnPage();
    });
}

window.renderSubmissionReturnPage = function() {
    const startIdx = (submissionReturnCurrentPage - 1) * submissionReturnPageSize;
    const endIdx = startIdx + submissionReturnPageSize;
    const pageData = submissionReturnAllData.slice(startIdx, endIdx);

    let html = '';
    pageData.forEach(function(item, idx) {
        const monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        html += '<tr>' +
            '<td>' + (startIdx + idx + 1) + '</td>' +
            '<td>' + (item.emp_code || '-') + '</td>' +
            '<td>' + (item.employee_name || '-') + '</td>' +
            '<td>' + (item.department_name || '-') + '</td>' +
            '<td>' + (item.function_name || '-') + '</td>' +
            '<td>' + monthNames[item.Month || 0] + '</td>' +
            '<td><span class="badge bg-warning">' + (item.status || 'Submission Returned') + '</span></td>' +
            '<td>' + (item.created_at ? new Date(item.created_at).toLocaleDateString('en-IN') : '-') + '</td>' +
            '<td><small>' + (item.remarks || '-') + '</small></td>' +
            '</tr>';
    });

    if (html === '') {
        html = '<tr><td colspan="9" class="text-center py-3 text-muted">No matching records found</td></tr>';
    }

    $('#submissionReturnBody').html(html);
    $('#submissionReturnInfo').text(`Showing ${startIdx + 1} to ${Math.min(endIdx, submissionReturnAllData.length)} of ${submissionReturnAllData.length} records`);

    // Render pagination
    const totalPages = Math.ceil(submissionReturnAllData.length / submissionReturnPageSize);
    let paginationHtml = '';

    if (submissionReturnCurrentPage > 1) {
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:submissionReturnGoToPage(1)">First</a></li>';
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:submissionReturnGoToPage(' + (submissionReturnCurrentPage - 1) + ')">Previous</a></li>';
    }

    for (let i = Math.max(1, submissionReturnCurrentPage - 2); i <= Math.min(totalPages, submissionReturnCurrentPage + 2); i++) {
        if (i === submissionReturnCurrentPage) {
            paginationHtml += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
        } else {
            paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:submissionReturnGoToPage(' + i + ')">' + i + '</a></li>';
        }
    }

    if (submissionReturnCurrentPage < totalPages) {
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:submissionReturnGoToPage(' + (submissionReturnCurrentPage + 1) + ')">Next</a></li>';
        paginationHtml += '<li class="page-item"><a class="page-link" href="javascript:submissionReturnGoToPage(' + totalPages + ')">Last</a></li>';
    }

    $('#submissionReturnPagination').html(paginationHtml);
}

window.submissionReturnGoToPage = function(page) {
    submissionReturnCurrentPage = page;
    renderSubmissionReturnPage();
};

// Event handlers for Employee-wise TAT search and sort
$('#empWiseTATSearch').on('keyup', function() {
    filterAndSortEmployeeWiseTAT();
});

$('#empWiseTATSort').on('change', function() {
    filterAndSortEmployeeWiseTAT();
});

// Event handlers for Claim-wise TAT search and sort
$('#claimWiseTATSearch').on('keyup', function() {
    filterAndSortClaimWiseTAT();
});

$('#claimWiseTATSort').on('change', function() {
    filterAndSortClaimWiseTAT();
});

    // Export buttons
    $('#btnExportStageSummary').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.stage.summary") }}?' + params;
    });

    $('#btnExportPendingDetail').on('click', function() {
        const filters = getFilters();
        filters.stage = $('#pendingStageSelect').val();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.pending.detail") }}?' + params;
    });

    $('#btnExportTAT').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.tat.analysis") }}?' + params;
    });

    $('#btnExportEmployeeWiseTAT').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.employee.wise.tat") }}?' + params;
    });

    $('#btnExportClaimWiseTAT').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.claim.wise.tat") }}?' + params;
    });

    $('#btnExportSubmissionReturn').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.submission.return") }}?' + params;
    });

    // PDF Export
    $('#btnPdfStageSummary').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.pdf.stage.summary") }}?' + params;
    });

    // Full Excel Export (all sheets)
    $('#btnExportFullReport').on('click', function() {
        const filters = getFilters();
        const params = window.getOrganogramFilterQueryParams ? window.getOrganogramFilterQueryParams(filters) : $.param(filters);
        window.location.href = '{{ route("reports.claim.dashboard.export.full") }}?' + params;
    });

    $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
        $('#btnApplyFilter').trigger('click');
    });
});
</script>
@include('partials.organogram-filters-js')
@endpush
