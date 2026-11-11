@extends('layouts.app')

@section('content')
@php
    $tabReq = request('tab', $activeTab ?? 'transfers');
    $isTransActive = in_array($tabReq, ['transfers', 'transfer', '']);
    $isJoinActive  = in_array($tabReq, ['joinings', 'joining']);
    $isImpActive   = in_array($tabReq, ['imprest', 'missing']);
    $isPromActive  = in_array($tabReq, ['promotions', 'promotion', 'promoted']);

    $promotionsList = $promotions ?? [];

    // Extract unique departments & companies for filtering
    $allDepts = collect(array_merge($transfers, $joinings, $missing_imprest, $promotionsList))
        ->pluck('department_name')
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $allCompanies = collect(array_merge($transfers, $joinings, $missing_imprest, $promotionsList))
        ->pluck('company_name')
        ->filter()
        ->unique()
        ->sort()
        ->values();
@endphp

<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1 fw-bold text-dark">System Alerts & Notifications</h4>
                        <p class="text-muted mb-0 fs-13">Monitor VVNR to VNR transfers, recent new joinings, grade promotions (Last 90 Days), and missing fixed imprest records</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="{{ url('/dashboard') }}" class="btn btn-sm btn-soft-secondary">
                            <i class="ri-arrow-left-line me-1"></i>Back to Dashboard
                        </a>
                        <a href="{{ route('admin.system-notifications.index') }}" class="btn btn-sm btn-primary">
                            <i class="ri-refresh-line me-1"></i>Refresh Alerts
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="row mb-4">

            {{-- Transferred (VVNR → VNR) --}}
            <div class="col-md-3">
                <div class="card card-animate border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-2">
                                    <i class="ri-swap-box-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">VVNR → VNR Transfers</p>
                                <h4 class="mb-0 fw-bold text-primary">{{ $vvnr_transfers_count }}</h4>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="text-muted fs-12">Transfers in Last 90 Days</span>
                            <a href="#transfers-tab" class="btn btn-link p-0 fs-12 text-primary text-decoration-none nav-tab-trigger" data-target="#transfers-tab">
                                View Details <i class="ri-arrow-right-line ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- New Joinings --}}
            <div class="col-md-3">
                <div class="card card-animate border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-2">
                                    <i class="ri-user-add-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">New Joinings</p>
                                <h4 class="mb-0 fw-bold text-success">{{ $new_joinings_count }}</h4>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="text-muted fs-12">Joined in Last 90 Days</span>
                            <a href="#joinings-tab" class="btn btn-link p-0 fs-12 text-success text-decoration-none nav-tab-trigger" data-target="#joinings-tab">
                                View Details <i class="ri-arrow-right-line ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Promoted Employees --}}
            <div class="col-md-3">
                <div class="card card-animate border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded-circle fs-2">
                                    <i class="ri-award-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Promotions</p>
                                <h4 class="mb-0 fw-bold text-info">{{ $promotions_count ?? count($promotionsList) }}</h4>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="text-muted fs-12">Promotions in Last 90 Days</span>
                            <a href="#promotions-tab" class="btn btn-link p-0 fs-12 text-info text-decoration-none nav-tab-trigger" data-target="#promotions-tab">
                                View Details <i class="ri-arrow-right-line ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Missing Imprest --}}
            <div class="col-md-3">
                <div class="card card-animate border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-2">
                                    <i class="ri-alert-line"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Missing Imprest</p>
                                <h4 class="mb-0 fw-bold text-warning">{{ $missing_imprest_count }}</h4>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="text-muted fs-12">Eligible Grade/Dept Missing Record</span>
                            <a href="#imprest-tab" class="btn btn-link p-0 fs-12 text-warning text-decoration-none nav-tab-trigger" data-target="#imprest-tab">
                                View Details <i class="ri-arrow-right-line ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Filters Bar --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label fs-12 text-uppercase text-muted mb-1 fw-bold">Search Filter</label>
                        <div class="search-box">
                            <input type="text" id="globalAlertSearch" class="form-control form-control-sm search" placeholder="Search by name, code, department..." aria-label="Search by name, code, department">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-12 text-uppercase text-muted mb-1 fw-bold">Department Filter</label>
                        <select id="departmentFilterSelect" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            @foreach($allDepts as $dName)
                                <option value="{{ $dName }}">{{ $dName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-12 text-uppercase text-muted mb-1 fw-bold">Company Filter</label>
                        <select id="companyFilterSelect" class="form-select form-select-sm">
                            <option value="">All Companies</option>
                            <option value="VNR">VNR Seeds</option>
                            <option value="VVNR">VVNR</option>
                            @foreach($allCompanies as $cName)
                                @if(!in_array($cName, ['VNR', 'VVNR']))
                                    <option value="{{ $cName }}">{{ $cName }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Tabbed Section --}}
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom p-3">
                        <ul class="nav nav-tabs nav-tabs-custom nav-primary card-header-tabs border-bottom-0" role="tablist" id="alertDetailsTabs">
                            <li class="nav-item">
                                <a class="nav-link {{ $isTransActive ? 'active' : '' }}" data-bs-toggle="tab" href="#transfers-tab" role="tab">
                                    <i class="ri-swap-box-line me-1 align-bottom"></i> VVNR → VNR Transfers (Last 90 Days)
                                    <span class="badge bg-primary-subtle text-primary ms-1 fs-11">{{ $vvnr_transfers_count }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $isJoinActive ? 'active' : '' }}" data-bs-toggle="tab" href="#joinings-tab" role="tab">
                                    <i class="ri-user-add-line me-1 align-bottom"></i> New Joinings (Last 90 Days)
                                    <span class="badge bg-success-subtle text-success ms-1 fs-11">{{ $new_joinings_count }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $isPromActive ? 'active' : '' }}" data-bs-toggle="tab" href="#promotions-tab" role="tab">
                                    <i class="ri-award-line me-1 align-bottom"></i> Grade Promotions (Last 90 Days)
                                    <span class="badge bg-info-subtle text-info ms-1 fs-11">{{ $promotions_count ?? count($promotionsList) }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $isImpActive ? 'active' : '' }}" data-bs-toggle="tab" href="#imprest-tab" role="tab">
                                    <i class="ri-alert-line me-1 align-bottom"></i> Missing Imprest Alerts
                                    <span class="badge bg-warning-subtle text-warning ms-1 fs-11">{{ $missing_imprest_count }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-3">
                        <div class="tab-content">

                            {{-- TAB 1: VVNR to VNR Transfers --}}
                            <div class="tab-pane {{ $isTransActive ? 'active' : '' }}" id="transfers-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle table-nowrap mb-0 alert-datatable" id="transfersTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Emp Code</th>
                                                <th>Employee Name</th>
                                                <th>Previous Company</th>
                                                <th>Current Company</th>
                                                <th>Transfer Date</th>
                                                <th>Department</th>
                                                <th>Designation</th>
                                                <th>Reporting Manager</th>
                                                <th>Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($transfers as $index => $t)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td><span class="fw-bold font-monospace text-primary">{{ $t['emp_code'] ?? '-' }}</span></td>
                                                <td>
                                                    <div class="fw-semibold">{{ $t['emp_name'] ?? '-' }}</div>
                                                </td>
                                                <td><span class="badge bg-warning-subtle text-warning">VVNR (Company 11)</span></td>
                                                <td><span class="badge bg-success-subtle text-success">VNR (Company 1)</span></td>
                                                <td>{{ !empty($t['transfer_date']) && \App\Support\ZeroDate::has($t['transfer_date']) ? date('d M Y', strtotime($t['transfer_date'])) : '-' }}</td>
                                                <td>{{ $t['department_name'] ?? '-' }}</td>
                                                <td>{{ $t['designation_name'] ?? '-' }}</td>
                                                <td>{{ $t['reporting_name'] ?? '-' }}</td>
                                                <td>
                                                    <small class="text-muted d-block"><i class="ri-phone-line me-1"></i>{{ $t['mobile'] ?? '-' }}</small>
                                                    <small class="text-muted d-block"><i class="ri-mail-line me-1"></i>{{ $t['email'] ?? '-' }}</small>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="10" class="text-center py-4 text-muted">
                                                    <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                                    No VVNR to VNR transfers recorded in the last 90 days.
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- TAB 2: New Joinings --}}
                            <div class="tab-pane {{ $isJoinActive ? 'active' : '' }}" id="joinings-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle table-nowrap mb-0 alert-datatable" id="joiningsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Emp Code</th>
                                                <th>Employee Name</th>
                                                <th>Company</th>
                                                <th>Grade</th>
                                                <th>Department</th>
                                                <th>Designation</th>
                                                <th>Headquarter / Territory</th>
                                                <th>Joining Date</th>
                                                <th>Reporting Manager</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($joinings as $index => $j)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td><span class="fw-bold font-monospace text-success">{{ $j['emp_code'] ?? '-' }}</span></td>
                                                <td>
                                                    <div class="fw-semibold">{{ $j['emp_name'] ?? '-' }}</div>
                                                    <small class="text-muted">{{ $j['email'] ?? '' }}</small>
                                                </td>
                                                <td><span class="badge bg-primary-subtle text-primary">{{ $j['company_name'] ?? 'VNR' }}</span></td>
                                                <td><span class="badge bg-info-subtle text-info">{{ $j['grade_name'] ?? '-' }}</span></td>
                                                <td>{{ $j['department_name'] ?? '-' }}</td>
                                                <td>{{ $j['designation_name'] ?? '-' }}</td>
                                                <td>{{ $j['hq_name'] ?? '-' }}</td>
                                                <td>
                                                    <span class="fw-semibold text-dark">
                                                        {{ !empty($j['DateJoining']) && \App\Support\ZeroDate::has($j['DateJoining']) ? date('d M Y', strtotime($j['DateJoining'])) : '-' }}
                                                    </span>
                                                </td>
                                                <td>{{ $j['reporting_name'] ?? '-' }}</td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="10" class="text-center py-4 text-muted">
                                                    <i class="ri-user-follow-line fs-24 d-block mb-1"></i>
                                                    No new joinings recorded in the last 90 days.
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- TAB 3: Grade Promotions --}}
                            <div class="tab-pane {{ $isPromActive ? 'active' : '' }}" id="promotions-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle table-nowrap mb-0 alert-datatable" id="promotionsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Emp Code</th>
                                                <th>Employee Name</th>
                                                <th>Company</th>
                                                <th>Current / Previous Grade</th>
                                                <th>Promoted New Grade</th>
                                                <th>Department</th>
                                                <th>Designation</th>
                                                <th>Reporting Manager</th>
                                                <th>Promotion Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($promotionsList as $index => $p)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td><span class="fw-bold font-monospace text-info">{{ $p['emp_code'] ?? '-' }}</span></td>
                                                <td>
                                                    <div class="fw-semibold">{{ $p['emp_name'] ?? '-' }}</div>
                                                    <small class="text-muted">{{ $p['email'] ?? '' }}</small>
                                                </td>
                                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $p['company_name'] ?? 'VNR' }}</span></td>
                                                <td><span class="badge bg-warning-subtle text-warning font-monospace">{{ $p['previous_grade'] ?? '-' }}</span></td>
                                                <td><span class="badge bg-success-subtle text-success font-monospace fs-12"><i class="ri-arrow-up-circle-line me-1"></i>{{ $p['new_grade'] ?? '-' }}</span></td>
                                                <td>{{ $p['department_name'] ?? '-' }}</td>
                                                <td>{{ $p['designation_name'] ?? '-' }}</td>
                                                <td>{{ $p['reporting_name'] ?? '-' }}</td>
                                                <td>{{ !empty($p['promotion_date']) ? date('d M Y', strtotime($p['promotion_date'])) : 'Recent' }}</td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="10" class="text-center py-4 text-muted">
                                                    <i class="ri-award-line fs-24 d-block mb-1"></i>
                                                    No grade promotions recorded in the last 90 days.
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- TAB 4: Missing Imprest --}}
                            <div class="tab-pane {{ $isImpActive ? 'active' : '' }}" id="imprest-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle table-nowrap mb-0 alert-datatable" id="imprestTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Emp Code</th>
                                                <th>Employee Name</th>
                                                <th>Company</th>
                                                <th>Grade</th>
                                                <th>Department</th>
                                                <th>Headquarter</th>
                                                <th>Date Joining</th>
                                                <th>Eligible Imprest (₹)</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($missing_imprest as $index => $m)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td><span class="fw-bold font-monospace text-warning">{{ $m['emp_code'] ?? '-' }}</span></td>
                                                <td>
                                                    <div class="fw-semibold">{{ $m['emp_name'] ?? '-' }}</div>
                                                    <small class="text-muted">Rep: {{ $m['reporting_name'] ?? '-' }}</small>
                                                </td>
                                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $m['company_name'] ?? 'VNR' }}</span></td>
                                                <td><span class="badge bg-warning-subtle text-warning font-monospace">{{ $m['grade_name'] ?? '-' }}</span></td>
                                                <td>{{ $m['department_name'] ?? '-' }}</td>
                                                <td>{{ $m['hq_name'] ?? '-' }}</td>
                                                <td>{{ !empty($m['DateJoining']) && \App\Support\ZeroDate::has($m['DateJoining']) ? date('d M Y', strtotime($m['DateJoining'])) : '-' }}</td>
                                                <td><span class="fw-bold text-success">₹{{ number_format($m['eligible_amount'] ?? 0) }}</span></td>
                                                <td>
                                                    @if(in_array(strtolower($m['imprest_status'] ?? ''), ['rejected', 'reject', 'missing_reject']))
                                                        <span class="badge bg-danger text-white px-2 py-1"><i class="ri-close-circle-line me-1"></i>Rejected</span>
                                                        @if(!empty($m['imprest_remarks']))
                                                            <small class="d-block text-muted mt-1 fs-11"><i class="ri-information-line me-1"></i>{{ $m['imprest_remarks'] }}</small>
                                                        @endif
                                                    @elseif(in_array(strtolower($m['imprest_status'] ?? ''), ['approved', 'released']))
                                                        <span class="badge bg-success-subtle text-success px-2 py-1"><i class="ri-checkbox-circle-line me-1"></i>Approved</span>
                                                    @elseif(($m['imprest_status'] ?? '') === 'pending')
                                                        <span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="ri-time-line me-1"></i>Pending Approval</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="ri-alert-line me-1"></i>No Ledger Imprest</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ url('/imprest-automation/missing-imprest') }}" class="btn btn-sm btn-soft-warning">
                                                        <i class="ri-add-circle-line me-1"></i>Fix Imprest
                                                    </a>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="11" class="text-center py-4 text-muted">
                                                    <i class="ri-checkbox-circle-line fs-24 d-block text-success mb-1"></i>
                                                    All eligible employees have fixed imprest entries recorded!
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

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
$(document).ready(function() {
    // Initialize DataTables on all 4 tables with pagination and search
    var dtTransfers = $('#transfersTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        ordering: true,
        responsive: true,
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search transfers..."
        }
    });

    var dtJoinings = $('#joiningsTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        ordering: true,
        responsive: true,
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search new joinings..."
        }
    });

    var dtPromotions = $('#promotionsTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        ordering: true,
        responsive: true,
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search promotions..."
        }
    });

    var dtImprest = $('#imprestTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        ordering: true,
        responsive: true,
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search missing imprest..."
        }
    });

    // Custom Filters
    $('#globalAlertSearch').on('keyup change', function() {
        var val = $(this).val();
        dtTransfers.search(val).draw();
        dtJoinings.search(val).draw();
        dtPromotions.search(val).draw();
        dtImprest.search(val).draw();
    });

    $('#departmentFilterSelect').on('change', function() {
        var val = $(this).val();
        dtTransfers.column(6).search(val ? '^' + val + '$' : '', true, false).draw();
        dtJoinings.column(5).search(val ? '^' + val + '$' : '', true, false).draw();
        dtPromotions.column(6).search(val ? '^' + val + '$' : '', true, false).draw();
        dtImprest.column(5).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('#companyFilterSelect').on('change', function() {
        var val = $(this).val();
        dtTransfers.search(val).draw();
        dtJoinings.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
        dtPromotions.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
        dtImprest.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    // Tab click triggers
    $('.nav-tab-trigger').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('target');
        if (targetTab) {
            $('#alertDetailsTabs a[href="' + targetTab + '"]').tab('show');
            $('html, body').animate({
                scrollTop: $('#alertDetailsTabs').offset().top - 100
            }, 300);
        }
    });

    // Hash handling on load
    var hash = window.location.hash;
    if (hash && $('#alertDetailsTabs a[href="' + hash + '"]').length) {
        $('#alertDetailsTabs a[href="' + hash + '"]').tab('show');
    }
});
</script>
@endpush
