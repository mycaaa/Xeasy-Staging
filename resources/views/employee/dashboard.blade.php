@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
@php
    $requestedTab = request('tab', '');
    $requestedTab = ltrim($requestedTab, '#');
    if ($requestedTab === 'advance-tab') {
        $requestedTab = 'approver-tab';
    }
    $validDashboardTabs = ['claimant-tab', 'approver-tab', 'verifier-tab', 'finance-tab'];
    if (!in_array($requestedTab, $validDashboardTabs)) {
        $requestedTab = 'claimant-tab';
    }
@endphp

            {{-- Dashboard Header --}}
            <div class="row mb-3 pb-1">
                <div class="col-12">
                    <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                        <div class="flex-grow-1">
                            <h4 class="fs-16 mb-1">Dashboard</h4>
                            <p class="text-muted mb-0">
                                Welcome, <strong>{{ session('emp_name', 'User') }}</strong>
                                <span class="text-muted ms-2">|</span>
                                <span class="ms-2">{{ session('department_name', '') }}</span>
                            </p>
                        </div>
                        <div class="mt-3 mt-lg-0 d-flex align-items-center gap-2">
                            {{-- Closed Month Filter Dropdown --}}
                            @php
                                $monthFullNames = [
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                ];
                            @endphp
                            <div class="d-flex align-items-center bg-white border rounded px-2 py-1 shadow-sm">
                                <i class="ri-calendar-event-line text-primary me-2 fs-16"></i>
                                <label for="monthFilterSelect" class="form-label mb-0 fs-12 text-muted me-2 fw-semibold">Month:</label>
                                <select id="monthFilterSelect" class="form-select form-select-sm border-0 fs-12 font-monospace fw-bold text-primary" style="width: auto; min-width: 140px; cursor: pointer;" onchange="changeDashboardMonth(this.value);">
                                    @if(!empty($closedMonths))
                                        @foreach($closedMonths as $m)
                                            <option value="{{ $m }}" {{ (int) $displayMonth === (int) $m ? 'selected' : '' }}>
                                                {{ $monthFullNames[$m] ?? "Month {$m}" }}
                                            </option>
                                        @endforeach
                                    @else
                                        @foreach(range(1, 12) as $m)
                                            <option value="{{ $m }}" {{ (int) $displayMonth === (int) $m ? 'selected' : '' }}>
                                                {{ $monthFullNames[$m] }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            @if($isApprover)
                                <span class="badge bg-success-subtle text-success fs-12 me-1">
                                    <i class="ri-shield-check-line me-1"></i>Approver
                                </span>
                            @endif
                            @if($isVerifier)
                                <span class="badge bg-info-subtle text-info fs-12 me-1">
                                    <i class="ri-checkbox-circle-line me-1"></i>Verifier
                                </span>
                            @endif
                            @if($isFinance)
                                <span class="badge bg-warning-subtle text-warning fs-12 me-1">
                                    <i class="ri-money-dollar-circle-line me-1"></i>Finance
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs Navigation --}}
            <ul class="nav nav-tabs nav-tabs-custom nav-primary mb-3" id="mainDashboardTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $requestedTab === 'claimant-tab' ? 'active' : '' }}" data-bs-toggle="tab" href="#claimant-tab" role="tab">
                        <i class="ri-file-list-3-line me-1 align-bottom"></i> My Claims
                    </a>
                </li>
                @if($isApprover)
                    <li class="nav-item">
                        <a class="nav-link {{ $requestedTab === 'approver-tab' ? 'active' : '' }}" data-bs-toggle="tab" href="#approver-tab" role="tab">
                            <i class="ri-check-double-line me-1 align-bottom"></i> Approvals
                            @if(session('advance_module') == 'Y' && session('adv_approver') == 'Y')
                                <span class="badge bg-warning-subtle text-warning ms-1" id="advPendingBadge" style="font-size:10px;display:none;"></span>
                            @endif
                        </a>
                    </li>
                @endif
                @if($isVerifier)
                    <li class="nav-item">
                        <a class="nav-link {{ $requestedTab === 'verifier-tab' ? 'active' : '' }}" data-bs-toggle="tab" href="#verifier-tab" role="tab">
                            <i class="ri-eye-line me-1 align-bottom"></i> Verification
                        </a>
                    </li>
                @endif
                @if($isFinance)
                    <li class="nav-item">
                        <a class="nav-link {{ $requestedTab === 'finance-tab' ? 'active' : '' }}" data-bs-toggle="tab" href="#finance-tab" role="tab">
                            <i class="ri-hand-coin-line me-1 align-bottom"></i> Finance
                        </a>
                    </li>
                @endif
            </ul>

            {{-- Tab Content --}}
            <div class="tab-content">

                {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
                {{-- CLAIMANT TAB (Common for all users) --}}
                {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
                <div class="tab-pane {{ $requestedTab === 'claimant-tab' ? 'active show' : '' }}" id="claimant-tab" role="tabpanel">

                    {{-- Quick Actions Row (replacing old Go to Claimant / New Claim buttons) --}}
                    <div class="card mb-3">
                        <div class="card-body py-3">
                            <div class="d-flex gap-3 flex-wrap align-items-center">
                                <a href="{{ url('/new-claim') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-primary-subtle text-primary">
                                        <i class="ri-add-circle-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">New Claim</span>
                                </a>
                                {{-- <a href="{{ url('/claimant') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-info-subtle text-info">
                                        <i class="ri-upload-2-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">Upload Bills</span>
                                </a> --}}
                                @if(session('advance_module') == 'Y')
                                {{-- Apply Advance --}}
                                @if(session('adv_activity') == 'Y' || session('adv_expense') == 'Y' || session('adv_other') == 'Y')
                                <a href="{{ url('/advance-requests/create') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-warning-subtle text-warning">
                                        <i class="ri-hand-coin-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">Apply Advance</span>
                                </a>
                                @endif
                                {{-- My Advances --}}
                                <a href="{{ url('/advance-requests') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-info-subtle text-info">
                                        <i class="ri-file-list-3-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">My Advances</span>
                                </a>
                                {{-- Approve Advances --}}
                                @if(session('adv_approver') == 'Y')
                                <a href="{{ url('/advance-requests/advanceApprovals') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-success-subtle text-success">
                                        <i class="ri-checkbox-circle-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">Approve Advances</span>
                                </a>
                                @endif
                                {{-- Advance Report --}}
@if(\App\Http\Middleware\EnforceRouteAccess::allows(auth()->user(), 'advance-requests/report'))
                                <a href="{{ url('/advance-requests/report') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-primary-subtle text-primary">
                                        <i class="ri-file-chart-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">Advance Report</span>
                                </a>
@endif
                                {{-- Team Advance (Admin) --}}
                                @php
                                    $canAccessAdminAdvance = (session('emp_role') !== 'E') && (
                                        (Auth::user() && method_exists(Auth::user(), 'hasAnyRole') && Auth::user()->hasAnyRole(['Admin', 'Super Admin', 'admin', 'super_admin'])) ||
                                        (Auth::user() && method_exists(Auth::user(), 'can') && (Auth::user()->can('seection_settings') || Auth::user()->can('Section Settings')))
                                    );
                                @endphp
                                @if($canAccessAdminAdvance)
                                <a href="{{ url('/advance/admin/submit') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon" style="background:#f5f0ff;">
                                        <i class="ri-team-line fs-20" style="color:#7c3aed;"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">Team Advance</span>
                                </a>
                                @endif
                                @endif
                                {{-- My Claims --}}
                                <a href="{{ url('/claimant') }}" class="quick-action-btn text-center text-decoration-none">
                                    <div class="quick-action-icon bg-secondary-subtle text-secondary">
                                        <i class="ri-run-line fs-20"></i>
                                    </div>
                                    <span class="fs-11 text-muted mt-1 d-block">My Claims</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    {{-- Summary Cards Row (4 cards like Image 1) --}}
                    @php
                        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        $displayMonthName = $monthNames[$displayMonth] ?? '';
                        $compMonthName = $monthNames[$comparisonMonth] ?? '';
                        $amtDiff = $amountPaidMTD['amount'] - $amountPaidPrev['amount'];
                        $amtPercent = $amountPaidPrev['amount'] > 0 ? round(abs($amtDiff) / $amountPaidPrev['amount'] * 100) : 0;
                        $claimsDiff = $claimsPaidMTD - $claimsPaidPrev;
                        $tatTotal = $stagewiseTAT['total'] ?? 0;
                        $tatPrevTotal = $stagewiseTATPrev['total'] ?? 0;
                        $tatDiff = $tatTotal - $tatPrevTotal;
                        $kmDriven = $odometerSummary['total_km'] ?? 0;
                        $kmPrevMonthName = $monthNames[$kmPrevMonth ?? ($displayMonth ?? 10)] ?? '';
                        $kmCurrMonthName = $monthNames[$kmCurrMonth ?? ((int) now()->month)] ?? '';
                        $kmDrivenPrev = $odometerSummaryPrev['total_km'] ?? ($odometerSummary['total_km'] ?? 0);
                        $kmDrivenCurr = $odometerSummaryCurr['total_km'] ?? 0;
                        // DASH-EMPTY-01: the vehicle sections only for people with vehicle (odometer) claims this year
                        $hasVehicle = ($odometerTrend['ytd_driven'] ?? 0) > 0 || ($odometerSummary['total_km'] ?? 0) > 0
                            || $kmDrivenPrev > 0 || $kmDrivenCurr > 0 || ($odometerSummary['opening_reading'] ?? 0) > 0;
                        // DASH-EMPTY-01: the stage timeline only once a claim of the month has moved through a stage
                        $tatHasData = collect(['submitted', 'finance_verification', 'reporting_manager', 'finance_approval', 'payment', 'total'])
                            ->contains(fn ($k) => (float) ($stagewiseTAT[$k] ?? 0) > 0);
                    @endphp
                    <div class="row g-3 mb-3">
                        <div class="col-xl-3 col-6">
                            <div class="card card-animate overflow-hidden h-100 mb-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3">
                                                <i class="ri-money-dollar-circle-line"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="text-uppercase fw-semibold text-muted mb-1 fs-12">Amount Paid ({{ $displayMonthName }})</p>
                                            <h4 class="mb-0">₹ {{ number_format($amountPaidMTD['amount']) }}</h4>
                                            <p class="text-muted mb-0 fs-11">
                                                {{ $amountPaidMTD['claims'] }} Claims
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-6">
                            <div class="card card-animate overflow-hidden h-100 mb-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3">
                                                <i class="ri-file-list-3-line"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="text-uppercase fw-semibold text-muted mb-1 fs-12">Claims ({{ $displayMonthName }})</p>
                                            <h4 class="mb-0">
                                                {{ number_format($monthClaimsSummary['total'] ?? 0) }} <span class="fs-13 fw-normal text-muted">{{ \Illuminate\Support\Str::plural('Claim', (int) ($monthClaimsSummary['total'] ?? 0)) }} submitted</span>
                                            </h4>
                                            <p class="mb-0 fs-11 mt-1">
                                                @if(!empty($isVVNR))
                                                    <span class="text-warning fw-semibold">{{ $monthClaimsSummary['draft'] ?? 0 }} {{ \Illuminate\Support\Str::plural('Draft', (int) ($monthClaimsSummary['draft'] ?? 0)) }}</span>
                                                    <span class="text-muted">·</span>
                                                    <span class="text-primary fw-semibold">{{ $monthClaimsSummary['approved'] ?? 0 }} Approved</span>
                                                    @if(($monthClaimsSummary['verified'] ?? 0) > 0)
                                                        <span class="text-muted">·</span>
                                                        <span class="text-success fw-semibold">{{ $monthClaimsSummary['verified'] }} Verified</span>
                                                    @endif
                                                @else
                                                    <span class="text-warning fw-semibold">{{ $monthClaimsSummary['draft'] ?? 0 }} {{ \Illuminate\Support\Str::plural('Draft', (int) ($monthClaimsSummary['draft'] ?? 0)) }}</span>
                                                    <span class="text-muted">·</span>
                                                    <span class="text-success fw-semibold">{{ $monthClaimsSummary['verified'] ?? 0 }} Verified</span>
                                                    @if(($monthClaimsSummary['denied'] ?? 0) > 0)
                                                        <span class="text-muted">·</span>
                                                        <span class="text-danger fw-semibold">{{ $monthClaimsSummary['denied'] }} Denied</span>
                                                    @endif
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-12">
                            <div class="card card-animate overflow-hidden h-100 mb-0">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                                <i class="ri-roadster-line"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            @if(! $hasVehicle)
                                                <p class="text-uppercase fw-semibold text-muted mb-1 fs-12">Kms Driven</p>
                                                <p class="mb-0 text-muted fs-13">No vehicle (odometer) claims this year.</p>
                                            @else
                                            <div class="row align-items-center g-2">
                                                <div class="col-6 border-end pe-3">
                                                    <p class="text-uppercase fw-semibold text-muted mb-1 fs-12">
                                                        Kms Driven ({{ $kmPrevMonthName }})
                                                    </p>
                                                    <h4 class="mb-0 text-dark fw-bold">
                                                        {{ number_format($kmDrivenPrev) }} <span class="fs-13 fw-normal text-muted">Kms</span>
                                                    </h4>
                                                </div>
                                                <div class="col-6 ps-3">
                                                    <p class="text-uppercase fw-semibold text-muted mb-1 fs-12">
                                                        Kms Driven ({{ $kmCurrMonthName }})
                                                    </p>
                                                    <h4 class="mb-0 text-success fw-bold">
                                                        {{ number_format($kmDrivenCurr) }} <span class="fs-13 fw-normal text-muted">Kms</span>
                                                    </h4>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- Left: Stats & Charts --}}
                        <div class="col-xl-8">

                            {{-- Stagewise TAT Analysis (Pipeline View) --}}
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-git-branch-line me-1"></i>Stagewise TAT Analysis
                                        <span class="text-muted fs-12 fw-normal ms-1">({{ $monthNames[$displayMonth] ?? '' }} {{ date('Y') }})</span>
                                    </h4>
                                </div>
                                <div class="card-body">
                                    @if(! $tatHasData)
                                    <div class="text-center text-muted py-3">
                                        <i class="ri-time-line fs-24 d-block mb-1"></i>
                                        <p class="mb-0 fs-13">Turnaround times for {{ $monthNames[$displayMonth] ?? 'this month' }} appear once the month is submitted and moves through verification, approval and payment.</p>
                                    </div>
                                    @else
                                    <div class="tat-pipeline d-flex align-items-start justify-content-between flex-wrap">
                                        @php
                                            if (!empty($isVVNR)) {
                                                $stages = [
                                                    ['label' => 'Submitted', 'icon' => 'ri-mail-send-line', 'tat' => $stagewiseTAT['submitted'] ?? 0],
                                                    ['label' => 'Manager approval', 'icon' => 'ri-user-star-line', 'tat' => $stagewiseTAT['reporting_manager'] ?? 0],
                                                    ['label' => 'Finance Approval', 'icon' => 'ri-checkbox-circle-line', 'tat' => $stagewiseTAT['finance_approval'] ?? 0],
                                                    ['label' => 'Payment', 'icon' => 'ri-money-dollar-circle-line', 'tat' => $stagewiseTAT['payment'] ?? 0],
                                                    ['label' => 'Verification', 'icon' => 'ri-shield-check-line', 'tat' => $stagewiseTAT['finance_verification'] ?? 0],
                                                ];
                                            } else {
                                                $stages = [
                                                    ['label' => 'Submitted', 'icon' => 'ri-mail-send-line', 'tat' => $stagewiseTAT['submitted'] ?? 0],
                                                    ['label' => 'Verification', 'icon' => 'ri-shield-check-line', 'tat' => $stagewiseTAT['finance_verification'] ?? 0],
                                                    ['label' => 'Manager approval', 'icon' => 'ri-user-star-line', 'tat' => $stagewiseTAT['reporting_manager'] ?? 0],
                                                    ['label' => 'Finance Approval', 'icon' => 'ri-checkbox-circle-line', 'tat' => $stagewiseTAT['finance_approval'] ?? 0],
                                                    ['label' => 'Payment', 'icon' => 'ri-money-dollar-circle-line', 'tat' => $stagewiseTAT['payment'] ?? 0],
                                                ];
                                            }
                                        @endphp
                                        @foreach($stages as $idx => $stg)
                                            <div class="tat-stage text-center">
                                                <div class="tat-stage-icon mx-auto mb-2">
                                                    <i class="{{ $stg['icon'] }} fs-18"></i>
                                                </div>
                                                <div class="fw-semibold fs-12 mb-1">{{ $stg['label'] }}</div>
                                                <div class="text-muted fs-11">Avg. TAT</div>
                                                <div class="fw-bold fs-14 text-primary">{{ $stg['tat'] }} Days</div>
                                            </div>
                                            @if($idx < count($stages) - 1)
                                                <div class="tat-arrow align-self-center">
                                                    <i class="ri-arrow-right-line text-muted fs-18"></i>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    {{-- Total Claim Cycle TAT Banner --}}
                                    <div class="tat-total-banner mt-3 p-2 rounded-3 text-center" style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9);">
                                        <span class="fw-bold text-success">
                                            Total Claim Cycle TAT: {{ $stagewiseTAT['total'] }} Days
                                        </span>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- DRAFTS ROW (Matches Claimant View) --}}
                            <div class="card mb-3" id="dashboardDraftsCard">
                                <div class="card-header align-items-center d-flex py-2 px-3">
                                    <h4 class="card-title mb-0 flex-grow-1 d-flex align-items-center fs-14">
                                        <i class="ri-draft-line text-warning me-1"></i> Drafts
                                        <span class="badge bg-warning-subtle text-warning ms-2 fs-11" id="dashDraftCount">0</span>
                                    </h4>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ url('/claimant') }}" class="btn btn-sm btn-soft-primary py-1 px-2 fs-11" title="Open full claimant view">
                                            View in Claims <i class="ri-arrow-right-s-line"></i>
                                        </a>
                                        <button class="btn btn-sm btn-soft-primary py-1 px-2" id="refreshDashboardDrafts" title="Refresh Drafts">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-2">
                                    <div id="dashboardDraftsLoading" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <p class="text-muted mt-2 mb-0 fs-12">Loading drafts...</p>
                                    </div>
                                    <div id="dashboardDraftsList" class="claim-cards-row" style="display:none; padding: 6px 4px 12px; gap: 14px; overflow-x: auto;"></div>
                                </div>
                            </div>

                            {{-- EHOME-MAIN-01 (11 Nov 2026): the eHome view (employee info, drafts, open months with Submit
                                 and Print, closed months with courier, received, verified, approved and paid) for the employee --}}
                            <div class="card" id="myEhomeCard">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1"><i class="ri-home-4-line me-1"></i>My E-Home</h4>
                                    <button class="btn btn-sm btn-soft-primary" id="refreshMyEhome" title="Refresh" aria-label="Refresh My E-Home"><i class="ri-refresh-line"></i></button>
                                </div>
                                <div class="card-body p-1" id="myEhomeBody">
                                    <div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...</div>
                                </div>
                            </div>

                            {{-- MY CLAIMS - Mobile App Style (Month-wise) - KEPT SAME --}}
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-file-list-3-line me-1"></i>My Claims (Month-wise)
                                    </h4>
                                    <button class="btn btn-sm btn-soft-primary" id="refreshMyClaims">
                                        <i class="ri-refresh-line"></i>
                                    </button>
                                </div>
                                <div class="card-body p-2">
                                    <div id="myClaimsMonthContainer">
                                        <div class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            <p class="text-muted mt-2 mb-0">Loading claims...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- My Claims Table (Current Month) - Commented out
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-file-list-3-line me-1"></i>My Claims
                                        <span class="text-muted fs-12 fw-normal ms-1">({{ $monthNames[$currentMonth] ?? '' }} {{ date('Y') }})</span>
                                    </h4>
                                    <a href="{{ url('/claimant') }}" class="btn btn-sm btn-soft-primary">
                                        View All Claims <i class="ri-arrow-right-s-line"></i>
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    @if(count($myClaimsCurrentMonth) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm align-middle mb-0" style="font-size: 12px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Claim ID</th>
                                                    <th>Claim Date</th>
                                                    <th>Purpose / Activity</th>
                                                    <th class="text-end">Amount (₹)</th>
                                                    <th class="text-center">Current Stage</th>
                                                    <th class="text-center">Status</th>
                                                    <th class="text-center">TAT (Days)</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($myClaimsCurrentMonth as $claim)
                                                <tr>
                                                    <td class="fw-medium text-primary">CLM-{{ $claim['ExpId'] }}</td>
                                                    <td>{{ $claim['BillDate'] && \App\Support\ZeroDate::has($claim['BillDate']) ? \Carbon\Carbon::parse($claim['BillDate'])->format('d M Y') : '-' }}</td>
                                                    <td>{{ Str::limit($claim['Purpose'], 30) }}</td>
                                                    <td class="text-end fw-medium">{{ number_format($claim['Amount']) }}</td>
                                                    <td class="text-center">
                                                        <span class="badge bg-{{ $claim['StageColor'] }}-subtle text-{{ $claim['StageColor'] }} fs-10">{{ $claim['Stage'] }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-{{ $claim['StatusColor'] }}-subtle text-{{ $claim['StatusColor'] }} fs-10">{{ $claim['Status'] }}</span>
                                                    </td>
                                                    <td class="text-center">{{ $claim['TAT'] }}</td>
                                                    <td class="text-center">
                                                        <a href="#" class="view-claim text-primary"
                                                           data-expid="{{ $claim['ExpId'] }}"
                                                           data-claim-id="{{ $claim['ClaimId'] }}"
                                                           data-cgid="{{ $claim['ClaimId'] }}"
                                                           data-empid="{{ session('employee_id') }}">
                                                            <i class="ri-eye-line fs-16"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="ri-file-list-3-line fs-36"></i>
                                        <p class="mt-2 mb-0">No claims for this month</p>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            --}}
                        </div>

                        {{-- Right: Expense Overview, Odometer Summary, Top 10 Performers --}}
                        <div class="col-xl-4">

                            {{-- Expense Overview (Donut Chart) --}}
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-pie-chart-line me-1"></i>Expense Overview
                                        <span class="text-muted fs-12 fw-normal ms-1">({{ $monthNames[$displayMonth] ?? '' }} {{ date('Y') }})</span>
                                    </h4>
                                </div>
                                <div class="card-body">
                                    @php
                                        $expItems  = collect($expenseOverview['items'] ?? []);
                                        $expTotal  = $expenseOverview['total'] ?? 0;
                                        $expColors = ['#3b82f6','#22c55e','#f97316','#a855f7','#06b6d4','#6366f1','#ec4899','#14b8a6'];
                                    @endphp
                                    <div class="d-flex align-items-center gap-3">
                                        {{-- Doughnut: fixed-height wrapper --}}
                                        <div style="min-width:160px;max-width:160px;">
                                            <div style="position:relative;height:220px;max-height:220px;overflow:hidden;">
                                                <canvas id="expense-overview-chart"></canvas>
                                            </div>
                                        </div>
                                        {{-- Legend list --}}
                                        <div class="flex-grow-1">
                                            @forelse($expItems as $i => $item)
                                            @php
                                                $pct = $expTotal > 0 ? round(($item['amount'] / $expTotal) * 100) : 0;
                                                $col = $expColors[$i % count($expColors)];
                                            @endphp
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span style="width:12px;height:12px;border-radius:3px;background:{{ $col }};flex-shrink:0;display:inline-block;"></span>
                                                    <span style="font-size:12px;color:#374151;">{{ $item['name'] }}</span>
                                                </div>
                                                <div class="text-end">
                                                    <span style="font-size:12px;font-weight:600;color:#111827;">₹ {{ number_format($item['amount']) }}</span>
                                                    <span style="font-size:11px;color:#9ca3af;margin-left:4px;">({{ $pct }}%)</span>
                                                </div>
                                            </div>
                                            @empty
                                            <p class="text-muted text-center mb-0" style="font-size:12px;">No expense data</p>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="mt-3 pt-2 border-top">
                                        <span class="fw-bold" style="font-size:14px;">Total: ₹ {{ number_format($expTotal) }}</span>
                                    </div>
                                </div>
                            </div>

                            @if($hasVehicle) {{-- DASH-EMPTY-01 --}}
                            {{-- Odometer Summary --}}
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-dashboard-3-line me-1"></i>Odometer Summary
                                        <span class="text-muted fs-12 fw-normal ms-1">({{ $monthNames[$displayMonth] ?? '' }} {{ date('Y') }})</span>
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Opening Reading</span>
                                        <span class="fw-semibold">{{ number_format($odometerSummary['opening_reading']) }} Kms</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Closing Reading</span>
                                        <span class="fw-semibold">{{ number_format($odometerSummary['closing_reading']) }} Kms</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Total Kms Driven</span>
                                        <span class="fw-bold text-primary">{{ number_format($odometerSummary['total_km']) }} Kms</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Reimbursable Kms</span>
                                        <span class="fw-semibold text-success">{{ number_format($odometerSummary['reimbursable_km']) }} Kms</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Rate / Km</span>
                                        <span class="fw-semibold">₹ {{ number_format($odometerSummary['rate_per_km'], 2) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted fs-13">Amount Eligible</span>
                                        <span class="fw-bold text-info">₹ {{ number_format($odometerSummary['amount_eligible']) }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Odometer Trend Chart (Full Financial Year) --}}
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-line-chart-line me-1"></i>Odometer Trend
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div id="odometer-trend-chart" class="apex-charts" dir="ltr" style="height: 280px;"></div>
                                    <div class="row mt-3 text-center pt-3" style="background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%); border-radius: 8px; padding: 12px 0;">
                                        <div class="col-4">
                                            <div class="border-end">
                                                <i class="ri-roadster-line text-primary fs-18 mb-1 d-block"></i>
                                                <p class="text-muted mb-1 fs-11 text-uppercase text-truncate">FY Kms Driven</p>
                                                <h6 class="text-primary mb-0 fw-bold fs-13" id="fyKmsDriven">{{ number_format($odometerTrend['ytd_driven']) }} Kms</h6>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="border-end">
                                                <i class="ri-checkbox-circle-line text-success fs-18 mb-1 d-block"></i>
                                                <p class="text-muted mb-1 fs-11 text-uppercase text-truncate">FY Reimbursable</p>
                                                <h6 class="text-success mb-0 fw-bold fs-13" id="fyReimbursableKms">{{ number_format($odometerTrend['ytd_reimbursable']) }} Kms</h6>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <i class="ri-money-dollar-circle-line text-info fs-18 mb-1 d-block"></i>
                                            <p class="text-muted mb-1 fs-11 text-uppercase text-truncate">FY Eligible</p>
                                            <h6 class="text-info mb-0 fw-bold fs-13" id="fyAmountEligible">₹ {{ number_format($odometerTrend['ytd_amount']) }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @endif

                            {{-- Top 10 Performers (KEPT SAME) --}}
                            {{-- <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1">
                                        <i class="ri-star-fill text-warning me-1"></i>Top 10 Performers
                                    </h4>
                                    <div class="d-flex gap-1">
                                        <span class="badge bg-soft-info text-info fs-11">
                                            {{ $topRatings['month'] ?? '' }}
                                        </span>
                                        <span class="badge bg-soft-primary text-primary fs-11">
                                            Claimant
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    @if($topRatings['claimant']->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="ps-3" style="width:35px;">#</th>
                                                        <th>Employee</th>
                                                        <th class="text-center" style="width:90px;">Rating</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($topRatings['claimant'] as $index => $rating)
                                                        <tr>
                                                            <td class="ps-3">
                                                                @if($index == 0)
                                                                    <span class="badge rounded-pill bg-warning">1</span>
                                                                @elseif($index == 1)
                                                                    <span class="badge rounded-pill bg-secondary">2</span>
                                                                @elseif($index == 2)
                                                                    <span class="badge rounded-pill bg-danger-subtle text-danger">3</span>
                                                                @else
                                                                    <span class="text-muted">{{ $index + 1 }}</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <h6 class="fs-13 mb-0">{{ $rating->employee_name }}</h6>
                                                                <span class="text-muted fs-11">{{ $rating->department_name }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                @php $avgStars = round($rating->avg_rating, 1); @endphp
                                                                <span class="fw-semibold" style="color: {{ \App\Models\ClaimRatingRule::getStarColor(round($avgStars)) }}">
                                                                    {{ $avgStars }}
                                                                </span>
                                                                <i class="ri-star-fill" style="color: {{ \App\Models\ClaimRatingRule::getStarColor(round($avgStars)) }}"></i>
                                                                <br><small class="text-muted">({{ $rating->total_rated }})</small>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <i class="ri-star-line text-muted fs-36"></i>
                                            <p class="text-muted mb-0 mt-2">No rating data available</p>
                                        </div>
                                    @endif
                                </div>
                            </div> --}}

                            {{-- Report Summary Card --}}
                            {{-- <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0"><i class="ri-bar-chart-2-line text-primary me-1"></i>Report Summary</h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Total Claims (Year)</span>
                                        <span class="fw-semibold">{{ number_format($reportStats['total_claims_system'] ?? 0) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Total Claimed</span>
                                        <span class="fw-semibold text-primary">₹{{ number_format($claimantStats->total_amount, 0) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="text-muted fs-13">Approved</span>
                                        <span class="fw-semibold text-success">₹{{ number_format($claimantStats->approved_amount, 0) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted fs-13">Financed</span>
                                        <span class="fw-semibold text-info">₹{{ number_format($claimantStats->financed_amount, 0) }}</span>
                                    </div>
                                </div>
                            </div> --}}
                        </div>
                    </div>
                </div>
                {{-- END Claimant Tab --}}

                {{-- APPROVER TAB (Reporting Manager Dashboard) --}}
                @if($isApprover)
                <div class="tab-pane {{ $requestedTab === 'approver-tab' ? 'active show' : '' }}" id="approver-tab" role="tabpanel">
                    @include('employee.partials.approver-tab')

                    {{-- ADVANCE SECTION ON APPROVAL SCREEN --}}
                    @if(session('advance_module') == 'Y')
                    @php
                        $advActivity   = session('adv_activity')    === 'Y';
                        $advExpense    = session('adv_expense')      === 'Y';
                        $advOther      = session('adv_other')        === 'Y';
                        $advApprover   = session('adv_approver')     === 'Y';
                        $advActAdv     = session('adv_act_advance')  === 'Y';
                        $advHod        = session('adv_hod')           === 'Y';
                        $advExpAdv     = session('adv_exp_advance')  === 'Y';
                        $advOthAdv     = session('adv_oth_advance')  === 'Y';
                        $advSubForMe   = session('adv_submitted_for_me') === 'Y';
                    @endphp

                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0 fw-semibold" style="color:#0b1a33;">
                                <i class="ri-coin-line text-warning me-1"></i> Advance Management & Approvals
                            </h5>
                            <div class="d-flex gap-2">
                                @if($advApprover)
                                <a href="{{ url('/advance-requests/advanceApprovals') }}" class="btn btn-sm btn-success">
                                    <i class="ri-checkbox-circle-line me-1"></i> Approve Advances
                                </a>
                                @endif
                                @if($advActivity || $advExpense || $advOther)
                                <a href="{{ url('/advance-requests/create') }}" class="btn btn-sm btn-warning">
                                    <i class="ri-add-line me-1"></i> New Request
                                </a>
                                @endif
                                <a href="{{ url('/advance-requests') }}" class="btn btn-sm btn-soft-primary">
                                    <i class="ri-file-list-3-line me-1"></i> My Advances
                                </a>
                            </div>
                        </div>

                        {{-- Quick Action Buttons --}}
                        <div class="card mb-3">
                            <div class="card-body py-3">
                                <div class="d-flex gap-3 flex-wrap align-items-center">
                                    @if($advActivity || $advExpense || $advOther)
                                    <a href="{{ url('/advance-requests/create') }}" class="quick-action-btn text-center text-decoration-none">
                                        <div class="quick-action-icon bg-warning-subtle text-warning">
                                            <i class="ri-add-circle-line fs-20"></i>
                                        </div>
                                        <span class="fs-11 text-muted mt-1 d-block">New Request</span>
                                    </a>
                                    @endif
                                    <a href="{{ url('/advance-requests') }}" class="quick-action-btn text-center text-decoration-none">
                                        <div class="quick-action-icon bg-info-subtle text-info">
                                            <i class="ri-file-list-3-line fs-20"></i>
                                        </div>
                                        <span class="fs-11 text-muted mt-1 d-block">My Requests</span>
                                    </a>
                                    @if($advApprover)
                                    <a href="{{ url('/advance-requests/advanceApprovals') }}" class="quick-action-btn text-center text-decoration-none">
                                        <div class="quick-action-icon bg-success-subtle text-success">
                                            <i class="ri-checkbox-circle-line fs-20"></i>
                                        </div>
                                        <span class="fs-11 text-muted mt-1 d-block">Approvals</span>
                                    </a>
                                    @endif
@if(\App\Http\Middleware\EnforceRouteAccess::allows(auth()->user(), 'advance-requests/report'))
                                    <a href="{{ url('/advance-requests/report') }}" class="quick-action-btn text-center text-decoration-none">
                                        <div class="quick-action-icon bg-primary-subtle text-primary">
                                            <i class="ri-file-chart-line fs-20"></i>
                                        </div>
                                        <span class="fs-11 text-muted mt-1 d-block">Advance Report</span>
                                    </a>
@endif
                                    @if(!empty($canAccessAdminAdvance))
                                    <a href="{{ url('/advance/admin/submit') }}" class="quick-action-btn text-center text-decoration-none">
                                        <div class="quick-action-icon" style="background:#f5f0ff;">
                                            <i class="ri-team-line fs-20" style="color:#7c3aed;"></i>
                                        </div>
                                        <span class="fs-11 text-muted mt-1 d-block">Team Advance</span>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Access Type Cards --}}
                        <div class="row g-3 mb-3">
                            @if($advActivity || $advExpense || $advOther)
                            <div class="col-xl-4 col-6">
                                <div class="card h-100" style="border-left:4px solid #f59e0b;">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar-sm me-3 flex-shrink-0">
                                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3">
                                                    <i class="ri-hand-coin-line"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">Advance Access</h6>
                                                <p class="text-muted mb-0 fs-12">Types you can apply for</p>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if($advActivity)
                                            <span class="badge bg-primary-subtle text-primary fs-11"><i class="ri-calendar-event-line me-1"></i>Activity</span>
                                            @endif
                                            @if($advExpense)
                                            <span class="badge bg-info-subtle text-info fs-11"><i class="ri-money-dollar-circle-line me-1"></i>Expense</span>
                                            @endif
                                            @if($advOther)
                                            <span class="badge bg-secondary-subtle text-secondary fs-11"><i class="ri-file-text-line me-1"></i>Other</span>
                                            @endif
                                        </div>
                                        <div class="mt-3">
                                            <a href="{{ url('/advance-requests/create') }}" class="btn btn-sm btn-warning w-100">
                                                <i class="ri-add-line me-1"></i> New Advance Request
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- My Requests Summary (loaded via AJAX) --}}
                            <div class="col-xl-4 col-6">
                                <div class="card h-100" style="border-left:4px solid #3b82f6;">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar-sm me-3 flex-shrink-0">
                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3">
                                                    <i class="ri-file-list-3-line"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">My Requests</h6>
                                                <p class="text-muted mb-0 fs-12">Activity · Expense · Other</p>
                                            </div>
                                        </div>
                                        <div id="advMyRequestsSummary">
                                            <div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                                        </div>
                                        <a href="{{ url('/advance-requests') }}" class="btn btn-sm btn-soft-primary w-100 mt-2">
                                            <i class="ri-eye-line me-1"></i> View All Requests
                                        </a>
                                    </div>
                                </div>
                            </div>

                            @if($advApprover)
                            {{-- Pending Approvals --}}
                            <div class="col-xl-4 col-6">
                                <div class="card h-100" style="border-left:4px solid #22c55e;">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar-sm me-3 flex-shrink-0">
                                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                                    <i class="ri-checkbox-circle-line"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">Pending Approvals</h6>
                                                <p class="text-muted mb-0 fs-12">Team requests waiting for you</p>
                                            </div>
                                        </div>
                                        <div id="advPendingApprovalsSummary">
                                            <div class="text-center py-2"><div class="spinner-border spinner-border-sm text-success"></div></div>
                                        </div>
                                        <a href="{{ url('/advance-requests/advanceApprovals') }}" class="btn btn-sm btn-soft-success w-100 mt-2">
                                            <i class="ri-check-double-line me-1"></i> Go to Approvals
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Recent Activity Advance Requests Table --}}
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <h4 class="card-title mb-0 flex-grow-1">
                                    <i class="ri-history-line me-1"></i> Recent Advance Requests
                                </h4>
                                <a href="{{ url('/advance-requests') }}" class="btn btn-sm btn-soft-primary">
                                    View All <i class="ri-arrow-right-s-line"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div id="advRecentRequestsList">
                                    <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
                {{-- END Approver Tab --}}
                {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
                {{-- VERIFIER TAB --}}
                {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
                @if($isVerifier)
                <div class="tab-pane {{ $requestedTab === 'verifier-tab' ? 'active show' : '' }}" id="verifier-tab" role="tabpanel">
                    <div class="row">
                        {{-- Quick Actions --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="{{ url('/verify-pending') }}" class="btn btn-info btn-label waves-effect waves-light">
                                        <i class="ri-eye-line label-icon align-middle fs-16 me-2"></i>Verify Claims
                                    </a>
                                    <a href="{{ url('/verify-running-km') }}" class="btn btn-soft-info waves-effect waves-light">
                                        <i class="ri-road-map-line me-1"></i>Verify Running KM
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="row">
                                <div class="col-lg-3 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3 material-shadow">
                                                        <i class="ri-file-search-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Pending (all months)</p>
                                                    <h4 class="mb-0">{{ number_format((int) ($verifierStats->pending_verification ?? 0)) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3 material-shadow">
                                                        <i class="ri-checkbox-circle-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Verified By Me</p>
                                                    <h4 class="mb-0">{{ number_format((int) ($verifierStats->verified_by_me ?? 0)) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3 material-shadow">
                                                        <i class="ri-calendar-check-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Verified ({{ $displayMonthName }})</p>
                                                    <h4 class="mb-0">{{ number_format((int) ($verifierStats->verified_this_month ?? 0)) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3 material-shadow">
                                                        <i class="ri-money-dollar-circle-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Verified Amt</p>
                                                    <h4 class="mb-0">₹{{ number_format($verifierStats->verified_amount ?? 0, 0) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- DASH-EMPTY-01: the "Verification Progress" bar chart repeated the cards above on one scale
                                 (17,000 pending next to 1 verified), so it is no longer drawn --}}
                        </div>
                        <div class="col-xl-4">
                            {{-- Missing Fixed Imprest Alert Card --}}
                            @if($missingImprestList->isNotEmpty())
                            <div class="card border-danger" style="border-left:4px solid #ef4444 !important;">
                                <div class="card-header py-2 d-flex align-items-center" style="background:#fff5f5;">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-0 text-danger" style="font-size:13px;">
                                            <i class="ri-alert-line me-1"></i>Missing Fixed Imprest
                                            <span class="badge bg-danger ms-1" style="font-size:10px;">{{ $missingImprestList->count() }}</span>
                                        </h5>
                                        <p class="text-muted mb-0" style="font-size:10px;">
                                            Eligible employees (J1–M1 / Sales+PD) with no ledger entry
                                        </p>
                                    </div>
                                    <a href="{{ route('imprest.missing') }}"
                                       class="btn btn-danger btn-sm ms-2" style="font-size:11px;padding:3px 10px;">
                                        <i class="ri-external-link-line me-1"></i>Full Report
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
                                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:11px;">
                                            <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                                                <tr>
                                                    <th style="padding:5px 8px;">Employee</th>
                                                    <th style="padding:5px 8px;">Dept</th>
                                                    <th class="text-center" style="padding:5px 8px;">Grade</th>
                                                    <th class="text-end" style="padding:5px 8px;">Pending</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($missingImprestList->take(20) as $emp)
                                                <tr>
                                                    <td style="padding:4px 8px;">
                                                        <div class="fw-semibold d-flex align-items-center flex-wrap gap-1" style="color:#1e293b;">
                                                            {{ trim($emp->emp_name) }}
                                                            @if(!empty($emp->is_upgrade))
                                                                <span class="badge bg-info-subtle text-info" style="font-size:8px;padding:1px 4px;">Upgrade</span>
                                                            @endif
                                                        </div>
                                                        <div style="font-size:9px;color:#94a3b8;">{{ $emp->emp_code ?: '—' }}</div>
                                                    </td>
                                                    <td style="padding:4px 8px;color:#475569;font-size:10px;">
                                                        {{ Str::limit($emp->department_name, 12) }}
                                                    </td>
                                                    <td class="text-center" style="padding:4px 8px;">
                                                        @php
                                                            $gc = in_array(strtoupper($emp->grade_name), ['J1','J2'])
                                                                ? 'bg-info-subtle text-info'
                                                                : 'bg-warning-subtle text-warning';
                                                        @endphp
                                                        <span class="badge {{ $gc }}" style="font-size:9px;">{{ strtoupper($emp->grade_name) }}</span>
                                                    </td>
                                                    <td class="text-end fw-semibold" style="padding:4px 8px;color:#16a34a;font-size:11px;">
                                                        ₹{{ number_format($emp->request_amount ?? $emp->eligible_amount) }}
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @if($missingImprestList->count() > 20)
                                                <tr>
                                                    <td colspan="4" class="text-center py-2" style="font-size:10px;color:#64748b;">
                                                        + {{ $missingImprestList->count() - 20 }} more —
                                                        <a href="{{ route('imprest.missing') }}" class="text-danger fw-semibold">View All</a>
                                                    </td>
                                                </tr>
                                                @endif
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold" style="padding:5px 8px;font-size:11px;">Total Pending:</td>
                                                    <td class="text-end fw-bold text-danger" style="padding:5px 8px;font-size:11px;">
                                                        ₹{{ number_format($missingImprestList->sum('request_amount') ?: $missingImprestList->sum('eligible_amount')) }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="card border-success" style="border-left:4px solid #22c55e !important;">
                                <div class="card-body text-center py-4">
                                    <i class="ri-checkbox-circle-line text-success" style="font-size:36px;"></i>
                                    <h6 class="text-success mt-2 mb-1">All Eligible Employees</h6>
                                    <p class="text-muted mb-0 fs-12">Have Fixed Imprest entries in the ledger</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
                {{-- END Verifier Tab --}}

                {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
                {{-- FINANCE TAB --}}
                {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
                @if($isFinance)
                <div class="tab-pane {{ $requestedTab === 'finance-tab' ? 'active show' : '' }}" id="finance-tab" role="tabpanel">
                    <div class="row">
                        {{-- Quick Actions --}}
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="{{ url('/expense-payments') }}" class="btn btn-warning btn-label waves-effect waves-light">
                                        <i class="ri-money-dollar-circle-line label-icon align-middle fs-16 me-2"></i>Payment Section
                                    </a>
                                    <a href="{{ url('/finance/advance-approvals') }}" class="btn btn-outline-dark waves-effect waves-light">
                                        <i class="ri-hand-coin-line me-1"></i>Advance Approvals
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="row">
                                <div class="col-lg-4 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3 material-shadow">
                                                        <i class="ri-wallet-3-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Pending Finance (all months)</p>
                                                    <h4 class="mb-0">{{ number_format((int) ($financeStats->pending_finance ?? 0)) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3 material-shadow">
                                                        <i class="ri-hand-coin-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Financed By Me</p>
                                                    <h4 class="mb-0">{{ number_format((int) ($financeStats->financed_by_me ?? 0)) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-6">
                                    <div class="card card-animate">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3 material-shadow">
                                                        <i class="ri-bank-line align-middle"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Total Financed</p>
                                                    <h4 class="mb-0">₹{{ number_format($financeStats->financed_amount ?? 0, 0) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Finance Charts --}}
                            <div class="row">
                                <div class="col-xl-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-0">Finance Pipeline</h4>
                                        </div>
                                        <div class="card-body">
                                            <div id="finance-pipeline-chart" class="apex-charts" dir="ltr" style="height: 300px;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-0">Payment Trend</h4>
                                        </div>
                                        <div class="card-body">
                                            <div id="finance-trend-chart" class="apex-charts" dir="ltr" style="height: 300px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header align-items-center d-flex">
                                    <h4 class="card-title mb-0 flex-grow-1"><i class="ri-star-fill text-warning me-1"></i>Top 10 - Finance Rating</h4>
                                    <span class="badge bg-soft-info text-info fs-11">{{ $topRatings['month'] ?? '' }}</span>
                                </div>
                                <div class="card-body p-0">
                                    @if($topRatings['finance']->count() > 0)
                                        <div class="table-responsive" style="max-height: 350px;">
                                            <table class="table table-sm table-hover align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr><th class="ps-3">#</th><th>Employee</th><th class="text-center">Stars</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($topRatings['finance'] as $i => $r)
                                                        <tr>
                                                            <td class="ps-3">{{ $i + 1 }}</td>
                                                            <td><h6 class="fs-13 mb-0">{{ $r->employee_name }}</h6></td>
                                                            <td class="text-center">
                                                                <span class="fw-semibold" style="color: {{ \App\Models\ClaimRatingRule::getStarColor(round($r->avg_rating)) }}">{{ round($r->avg_rating, 1) }}</span>
                                                                <i class="ri-star-fill" style="color: {{ \App\Models\ClaimRatingRule::getStarColor(round($r->avg_rating)) }}"></i>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-4"><p class="text-muted mb-0">No data</p></div>
                                    @endif
                                </div>
                            </div>

                            <div class="card bg-warning-subtle">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="text-warning mb-2">Total Financed (Year)</h6>
                                            <h3 class="mb-0">₹{{ number_format($financeStats->financed_amount ?? 0, 0) }}</h3>
                                        </div>
                                        <div class="avatar-md">
                                            <span class="avatar-title bg-warning rounded-circle">
                                                <i class="ri-bank-fill fs-24 text-white"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                {{-- END Finance Tab --}}



            </div>
            {{-- END Tab Content --}}

        </div>
    </div>

    {{-- Claim Detail Modal (same as eHome) --}}
    <x-modal.view_claim_detail />

    {{-- Draft Detail Modal for Dashboard --}}
    <div class="modal fade" id="dashDraftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
                <div class="modal-header py-2 px-3 text-white" style="background: #dde6ff; color: #1e3a8a;">
                    <h5 class="modal-title fs-14 text-white d-flex align-items-center">
                        <i class="ri-file-text-line me-2"></i> Draft Bill Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="text-center p-2 rounded-3 bg-light border">
                                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Exp ID</div>
                                <div class="mt-1"><span class="mcv-exp-id fs-12" id="dash-dm-expid">#—</span></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 rounded-3 bg-light border">
                                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Type</div>
                                <div class="fw-bold mt-1 text-dark fs-12" id="dash-dm-type">—</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 rounded-3 bg-light border">
                                <div class="text-muted text-uppercase fw-semibold" style="font-size:10px;">Amount</div>
                                <div class="fw-bold text-primary mt-1 fs-12" id="dash-dm-amount">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 p-2 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                        <div class="fw-bold text-warning-emphasis mb-1" style="font-size:11px;">EMPLOYEE REMARK</div>
                        <p id="dash-dm-remark" class="mb-0 text-muted fs-12"></p>
                    </div>
                    <div class="mb-3">
                        <div class="fw-bold text-muted mb-2" style="font-size:11px;">ATTACHED DOCUMENTS</div>
                        <div id="dash-dm-files" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="mb-3">
                        <div class="fw-bold text-muted mb-2" style="font-size:11px;">REMARKS HISTORY</div>
                        <ul id="dash-dm-remarks-list" class="list-group list-group-flush border rounded-3" style="max-height: 150px; overflow-y: auto;"></ul>
                    </div>
                    <div class="p-2 rounded-3" style="background:#f0f9ff;border:1px solid #bae6fd;">
                        <div class="fw-bold text-primary mb-1" style="font-size:11px;">ADD NEW REMARK</div>
                        <div class="input-group input-group-sm">
                            <input type="text" id="dash-dm-new-remark" class="form-control" placeholder="Write a note or comment for this draft bill..." aria-label="Write a note or comment for this draft bill">
                            <button class="btn btn-primary" type="button" id="dashAddRemarkBtn"><i class="ri-send-plane-fill"></i> Add</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fullscreen Image Lightbox --}}
    <div id="dashLightbox" class="mcv-lightbox" onclick="closeDashLightbox()">
        <div class="mcv-lightbox-box" onclick="event.stopPropagation()">
            <button type="button" class="mcv-lightbox-close" onclick="closeDashLightbox()" title="Close">&times;</button>
            <img id="dashLightboxImg" class="mcv-lightbox-img" src="" alt="Enlarged Document">
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ URL::to('/') }}/assets/libs/apexcharts/apexcharts.min.js"></script>
<script src="{{ asset('vendor/cdn/chart.js@4.4.0/dist/chart.umd.js') }}"></script>
<script src="{{ asset('custom/js/pages/claim.js') }}?v={{ filemtime(public_path('custom/js/pages/claim.js')) }}"></script>
<script>
// ══════════════════════════════════════════════════════════════
// DASHBOARD VIEW-ONLY: disable all form controls in the claim modal
// ══════════════════════════════════════════════════════════════
(function() {
    function lockModalForViewOnly() {
        var modal = document.getElementById('claimDetailModal');
        if (!modal) return;
        // Disable all form inputs, selects, textareas, buttons inside the form
        $(modal).find('#dataEntryForm input, #dataEntryForm select, #dataEntryForm textarea, #dataEntryForm button').each(function() {
            $(this).prop('disabled', true).attr('readonly', true).css({'pointer-events':'none','opacity':'0.7'});
        });
        // Hide upload button
        $(modal).find('#claimImageUploadWrapper').hide();
        // Disable claim type dropdown
        $(modal).find('#sltClaimTypeList').prop('disabled', true).css({'pointer-events':'none','opacity':'0.8'});
        // Show view-only badge in header
        if (!$(modal).find('#viewOnlyBadge').length) {
            $(modal).find('.modal-title').append(' <span id="viewOnlyBadge" class="badge bg-secondary ms-2" style="font-size:10px;vertical-align:middle;">View Only</span>');
        }
    }

    // Lock on modal show
    $(document).on('shown.bs.modal', '#claimDetailModal', function() {
        lockModalForViewOnly();
    });

    // Re-lock after dynamic form content is loaded into #dataEntryForm
    var formObserver = new MutationObserver(function() {
        var modal = document.getElementById('claimDetailModal');
        if (modal && $(modal).hasClass('show')) {
            lockModalForViewOnly();
        }
    });
    $(document).ready(function() {
        var formEl = document.getElementById('dataEntryForm');
        if (formEl) {
            formObserver.observe(formEl, { childList: true, subtree: true });
        }
    });
})();

$(document).ready(function() {

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // CLAIMANT CHARTS (New Design)
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    // Expense Overview Donut Chart (Chart.js - canvas already in HTML)
    @php
        $expLabels = collect($expenseOverview['items'])->pluck('name')->toArray();
        $expSeries = collect($expenseOverview['items'])->pluck('amount')->toArray();
        $expColors = ['#3b82f6','#22c55e','#f97316','#a855f7','#06b6d4','#6366f1','#ec4899','#14b8a6'];
    @endphp
    @if(count($expenseOverview['items']) > 0)
    (function() {
        var cnv = document.getElementById('expense-overview-chart');
        if (!cnv) return;
        new Chart(cnv, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expLabels) !!},
                datasets: [{
                    data: {!! json_encode($expSeries) !!},
                    backgroundColor: {!! json_encode(array_slice($expColors, 0, count($expSeries))) !!},
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ₹' + ctx.parsed.toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    })();
    @else
    var expEl = document.getElementById('expense-overview-chart');
    if (expEl) expEl.closest('div').innerHTML = '<div class="text-center text-muted py-4"><i class="ri-pie-chart-line fs-36"></i><p class="mt-2 mb-0">No expense data this month</p></div>';
    @endif

    // Odometer Trend Chart (Full Financial Year - Bar + Line combo)
    @php
        $odoLabels = json_encode($odometerTrend['labels'] ?? []);
        $odoKmDriven = json_encode($odometerTrend['kms_driven'] ?? []);
        $odoReimbursable = json_encode($odometerTrend['reimbursable_kms'] ?? []);
    @endphp
    var odometerChart = null;
    function renderOdometerChart(labels, kmsDriven, reimbursable) {
        if (odometerChart) odometerChart.destroy();
        var odometerTrendOptions = {
            series: [
                { name: 'Kms Driven', type: 'column', data: kmsDriven },
                { name: 'Reimbursable Kms', type: 'line', data: reimbursable }
            ],
            chart: { type: 'line', height: 270, toolbar: { show: false } },
            stroke: { width: [0, 3], curve: 'smooth' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            fill: { opacity: [0.85, 1] },
            colors: ['#3577f1', '#45cb85'],
            xaxis: { categories: labels },
            yaxis: { title: { text: 'Kilometers', style: { fontSize: '12px' } } },
            legend: { position: 'top', fontSize: '12px' },
            grid: { borderColor: '#f1f1f1' },
            dataLabels: { enabled: false },
            tooltip: {
                shared: true,
                y: { formatter: function(val) { return val.toLocaleString('en-IN') + ' Kms'; } }
            }
        };
        if (!document.querySelector("#odometer-trend-chart")) { return; } // DASH-EMPTY-01: hidden when there are no vehicle claims
        odometerChart = new ApexCharts(document.querySelector("#odometer-trend-chart"), odometerTrendOptions);
        odometerChart.render();
    }
    @if(count($odometerTrend['labels'] ?? []) > 0)
    renderOdometerChart({!! $odoLabels !!}, {!! $odoKmDriven !!}, {!! $odoReimbursable !!});
    @endif

    // Year filter for Odometer Trend
    $('#odometerYearFilter').on('change', function() {
        var yearId = $(this).val();
        var $card = $(this).closest('.card');
        $card.find('.card-body').css('opacity', '0.5');
        $.get("{{ route('dashboard.odometer-trend') }}", { year_id: yearId }, function(data) {
            $card.find('.card-body').css('opacity', '1');
            if (data.labels && data.labels.length > 0) {
                renderOdometerChart(data.labels, data.kms_driven, data.reimbursable_kms);
            }
            $('#fyKmsDriven').text(Number(data.ytd_driven).toLocaleString('en-IN') + ' Kms');
            $('#fyReimbursableKms').text(Number(data.ytd_reimbursable).toLocaleString('en-IN') + ' Kms');
            $('#fyAmountEligible').text('₹ ' + Number(data.ytd_amount).toLocaleString('en-IN'));
        }).fail(function() {
            $card.find('.card-body').css('opacity', '1');
        });
    });

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // APPROVER CHARTS (Reporting Manager Dashboard)
    @if($isApprover && $rmDashData)
    @php
        $rmStatusLabels = array_keys($rmDashData['status_counts'] ?? []);
        $rmStatusSeries = array_values($rmDashData['status_counts'] ?? []);
        $rmExpTypes = ($rmDashData['expense_by_type'] ?? collect());
        $rmExpLabels = $rmExpTypes->map(fn($e) => $rmDashData['claim_names'][$e->ClaimId] ?? 'Other')->toArray();
        $rmExpSeries = $rmExpTypes->pluck('total')->map(fn($v) => round((float)$v))->toArray();
    @endphp
    // Claims by Status Donut
    // Claims by Status - Chart.js doughnut (canvas already in HTML)
    (function() {
        var cnv = document.getElementById('rm-claims-status-chart');
        if (!cnv) return;
        new Chart(cnv, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($rmStatusLabels) !!},
                datasets: [{
                    data: {!! json_encode($rmStatusSeries) !!},
                    backgroundColor: ['#f59e0b','#22c55e','#ef4444','#3b82f6','#06b6d4','#6b7280'].slice(0, {!! count($rmStatusSeries) !!}),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed;
                            }
                        }
                    }
                }
            }
        });
    })();
    // RM Expense Overview - Chart.js doughnut (canvas already in HTML)
    (function() {
        var cnv = document.getElementById('rm-expense-overview-chart');
        if (!cnv) return;
        new Chart(cnv, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_values($rmExpLabels)) !!},
                datasets: [{
                    data: {!! json_encode($rmExpSeries) !!},
                    backgroundColor: ['#3b82f6','#22c55e','#f97316','#a855f7','#06b6d4','#6366f1','#ec4899','#14b8a6'].slice(0, {!! count($rmExpSeries) !!}),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ₹' + ctx.parsed.toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    })();
    // Expense Trend Bar Chart
    if (document.querySelector("#rm-expense-trend-chart")) {
        new ApexCharts(document.querySelector("#rm-expense-trend-chart"), {
            series: [
                { name: 'Amount (₹)', type: 'column', data: {!! json_encode($rmDashData['trend_amounts'] ?? []) !!} },
                { name: 'No. of Claims', type: 'line', data: {!! json_encode($rmDashData['trend_counts'] ?? []) !!} }
            ],
            chart: { height: 220, type: 'line', toolbar: { show: false } },
            stroke: { width: [0, 3], curve: 'smooth' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            colors: ['#3577f1', '#f06548'],
            xaxis: { categories: {!! json_encode($rmDashData['trend_labels'] ?? []) !!} },
            yaxis: [
                { title: { text: 'Amount (₹)' }, labels: { formatter: function(v) { return '₹' + (v/1000).toFixed(0) + 'K'; } } },
                { opposite: true, title: { text: 'Claims' } }
            ],
            legend: { position: 'top' },
            dataLabels: { enabled: false }
        }).render();
    }
    @endif
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // VERIFIER CHARTS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    @if($isVerifier)
    var verifierProgressOptions = {
        series: [{
            name: 'Pending',
            data: [{{ $verifierStats->pending_verification ?? 0 }}]
        }, {
            name: 'Verified By Me',
            data: [{{ $verifierStats->verified_by_me ?? 0 }}]
        }, {
            name: 'This Month',
            data: [{{ $verifierStats->verified_this_month ?? 0 }}]
        }],
        chart: { type: 'bar', height: 290, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '50%' } },
        colors: ['#ffbf53', '#0ab39c', '#3577f1'],
        xaxis: { categories: ['Claims'] },
        legend: { position: 'top' }
    };
    if (document.querySelector("#verifier-progress-chart")) { new ApexCharts(document.querySelector("#verifier-progress-chart"), verifierProgressOptions).render(); }
    @endif

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // FINANCE CHARTS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    @if($isFinance)
    // Finance Pipeline Radial
    @php
        $pendingFin = $financeStats->pending_finance ?? 0;
        $financedByMe = $financeStats->financed_by_me ?? 0;
        $finTotal = $pendingFin + $financedByMe;
        $finPercent = $finTotal > 0 ? round(($financedByMe / $finTotal) * 100) : 0;
    @endphp
    var financePipelineOptions = {
        series: [{{ $finPercent }}],
        chart: { type: 'radialBar', height: 300 },
        plotOptions: {
            radialBar: {
                hollow: { size: '60%' },
                dataLabels: {
                    name: { fontSize: '14px' },
                    value: { fontSize: '20px', formatter: function(val) { return val + '%'; } }
                }
            }
        },
        labels: ['Processed'],
        colors: ['#45cb85']
    };
    new ApexCharts(document.querySelector("#finance-pipeline-chart"), financePipelineOptions).render();

    // Finance Trend
    @php $financeMonthlyJson = json_encode($financeMonthlyData ?? array_fill(0, 12, 0)); @endphp
    var financeTrendOptions = {
        series: [{
            name: 'Financed',
            data: {!! $financeMonthlyJson !!}
        }],
        chart: { type: 'area', height: 300, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        colors: ['#ffbf53'],
        xaxis: { categories: ['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'] },
        grid: { borderColor: '#f1f1f1' }
    };
    new ApexCharts(document.querySelector("#finance-trend-chart"), financeTrendOptions).render();
    @endif

    // ══════════════════════════════════════════════════════════
    // DASHBOARD DRAFTS ROW (Matches Claimant View)
    // ══════════════════════════════════════════════════════════
    loadDashboardDrafts();
    $('#refreshDashboardDrafts').on('click', function() {
        const btn = $(this);
        btn.find('i').addClass('spin-me');
        loadDashboardDrafts();
        setTimeout(function() { btn.find('i').removeClass('spin-me'); }, 800);
    });

    function loadDashboardDrafts() {
        $('#dashboardDraftsLoading').show();
        $('#dashboardDraftsList').hide().empty();

        $.ajax({
            url: "{{ route('claimant.draft.list') }}",
            method: 'GET',
            data: {
                ei: '{{ session("employee_id") }}',
                YearId: '{{ session("FYearId", session("year_id", 1)) }}',
                CompanyId: '{{ session("CompanyId", session("company_id", 1)) }}'
            },
            success: function(res) {
                $('#dashboardDraftsLoading').hide();
                const list = res.Draft_list || [];
                $('#dashDraftCount').text(list.length);

                if (!list.length) {
                    $('#dashboardDraftsList').html(`
                        <div class="text-center py-4 w-100">
                            <i class="ri-draft-line text-muted" style="font-size:28px;"></i>
                            <p class="text-muted mt-1 mb-0 fs-12">No draft bills uploaded</p>
                        </div>
                    `).show();
                    return;
                }

                list.forEach(renderDashboardDraft);
                $('#dashboardDraftsList').show();
            },
            error: function() {
                $('#dashboardDraftsLoading').hide();
                $('#dashboardDraftsList').html(`
                    <div class="alert alert-warning py-2 px-3 mb-0 fs-12 w-100">
                        <i class="ri-alert-line me-1"></i> Unable to load draft bills. <a href="javascript:void(0)" onclick="loadDashboardDrafts()" class="alert-link">Retry</a>
                    </div>
                `).show();
            }
        });
    }

    function renderDashboardDraft(it) {
        const rmJ  = JSON.stringify(it.remarks||[]).replace(/'/g,"&#39;");
        const imgJ = JSON.stringify({img1:it.Img_1,img2:it.Img_2,img3:it.Img_3,pdf1:it.pdf_1,pdf2:it.pdf_2}).replace(/'/g,"&#39;");

        // Primary media preview
        let topMediaHtml = '';
        const allFiles = [it.Img_1, it.Img_2, it.Img_3, it.pdf_1, it.pdf_2].filter(Boolean);
        if (!allFiles.length) {
            topMediaHtml = `<div class="draft-img-placeholder"><i class="ri-image-line"></i><span>No Image</span></div>`;
        } else {
            const primary = allFiles[0];
            const isPdf = String(primary).toLowerCase().includes('.pdf');
            if (isPdf) {
                topMediaHtml = `
                    <a href="${primary}" target="_blank" class="pdf-card-preview" onclick="event.stopPropagation();" title="Click to view PDF document">
                        <div class="pdf-icon-badge"><i class="ri-file-pdf-2-line"></i></div>
                        <span class="pdf-label">PDF Document</span>
                    </a>`;
            } else {
                topMediaHtml = `<img src="${primary}" alt="Bill" loading="lazy" onclick="openDashLightbox('${primary}')" class="zoom-img">`;
            }
        }

        // Extra thumbnails (img2, img3, etc.)
        let extraThumbs = '';
        if (allFiles.length > 1) {
            allFiles.slice(1).forEach(function(f) {
                if (String(f).toLowerCase().includes('.pdf')) {
                    extraThumbs += `<a href="${f}" target="_blank" class="badge bg-danger-subtle text-danger p-1 border me-1 text-decoration-none" title="Open PDF" onclick="event.stopPropagation();"><i class="ri-file-pdf-2-fill"></i></a>`;
                } else {
                    extraThumbs += `<img alt="Click to view" src="${f}" class="draft-extra-thumb" title="Click to view" onclick="openDashLightbox('${f}')">`;
                }
            });
        }

        const remarkText = it.DateEntryRemark || it.Remark || '';
        const remarkHtml = (remarkText && remarkText !== '—' && remarkText !== '0')
            ? `<div class="draft-remark" title="${remarkText.replace(/"/g,'&quot;')}"><i class="ri-chat-1-line"></i> ${remarkText}</div>`
            : '';

        const billDateStr = (it.BillDate && it.BillDate !== '0000-00-00')
            ? new Date(it.BillDate).toLocaleDateString('en-GB')
            : '—';
        const crDateStr = (it.CrDate && it.CrDate !== '0000-00-00')
            ? new Date(it.CrDate).toLocaleDateString('en-GB')
            : billDateStr;

        $('#dashboardDraftsList').append(`
        <div class="draft-bill-card" id="dashDraftCard-${it.ExpId}">
            <div class="draft-img-wrap">${topMediaHtml}</div>
            <div class="draft-accent-bar"></div>
            ${extraThumbs ? `<div class="draft-extra-thumbs">${extraThumbs}</div>` : ''}
            <div class="draft-info">
                <div class="dbh-top">
                    <span class="mcv-exp-id">#${it.ExpId}</span>
                    <span class="sbg sbg-draft">Draft</span>
                </div>
                <div class="draft-type mt-1" title="${it.ClaimName||''}">${it.ClaimName||'Expense Bill'}</div>
                <div class="draft-meta">Bill Date: ${billDateStr}</div>
                <div class="draft-amount mt-1">₹${Number(it.FilledAmt||it.FilledTAmt||0).toLocaleString('en-IN')} <small class="text-muted fw-normal" style="font-size:0.65rem;">(${crDateStr})</small></div>
                ${remarkHtml}
            </div>
            <div class="draft-bill-actions">
                <button class="dab dab-view dash-view-draft-btn"
                    data-exp-id="${it.ExpId}"
                    data-claimtype="${it.ClaimName||''}"
                    data-amount="${it.FilledAmt||it.FilledTAmt||0}"
                    data-emp-remark="${(it.DateEntryRemark||'').replace(/"/g,'&quot;')}"
                    data-remarks='${rmJ}'
                    data-images='${imgJ}'>
                    <i class="ri-eye-line"></i> View
                </button>
                <button class="dab dab-del dash-delete-draft-btn" data-exp-id="${it.ExpId}">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </div>
        </div>`);
    }

    // View Draft Modal Handler
    $(document).on('click', '.dash-view-draft-btn', function() {
        const imgs = JSON.parse($(this).attr('data-images')||'{}');
        const rmks = JSON.parse($(this).attr('data-remarks')||'[]');
        const expId = $(this).data('exp-id');

        $('#dash-dm-expid').text('#'+expId);
        $('#dash-dm-type').text($(this).data('claimtype'));
        $('#dash-dm-amount').text('₹' + Number($(this).data('amount') || 0).toLocaleString('en-IN'));
        $('#dash-dm-remark').text($(this).data('emp-remark')||'No remark added.');
        $('#dashAddRemarkBtn').attr('data-exp-id', expId);

        const fl = $('#dash-dm-files').empty();
        const allDraftFiles = [imgs.img1, imgs.img2, imgs.img3, imgs.pdf1, imgs.pdf2].filter(Boolean);
        allDraftFiles.forEach(function(f) {
            if (String(f).toLowerCase().includes('.pdf')) {
                fl.append(`<a href="${f}" target="_blank" class="badge bg-danger-subtle text-danger p-2 border border-danger-subtle text-decoration-none" title="Open PDF Document">
                    <i class="ri-file-pdf-2-fill me-1"></i> View PDF
                </a>`);
            } else {
                fl.append(`<div class="border rounded p-1 cursor-pointer" onclick="openDashLightbox('${f}')" title="Click to enlarge" style="width:50px;height:50px;overflow:hidden;display:inline-block;">
                    <img src="${f}" alt="doc" style="width:100%;height:100%;object-fit:cover;">
                </div>`);
            }
        });
        if (!fl.children().length) fl.html('<span class="text-muted fs-12">No documents uploaded.</span>');

        const rl = $('#dash-dm-remarks-list').empty();
        rmks.forEach(function(r) {
            rl.append(`<li class="list-group-item py-2 px-3">
                <small class="text-muted d-block fs-10">${r.remark_date||''}</small>
                <span class="fs-12">${r.remark||'—'}</span>
            </li>`);
        });
        if (!rmks.length) rl.append('<li class="list-group-item text-muted text-center py-2 fs-12">No remarks yet.</li>');

        $('#dash-dm-new-remark').val('');
        new bootstrap.Modal(document.getElementById('dashDraftModal')).show();
    });

    // Add remark handler
    $(document).on('click', '#dashAddRemarkBtn', function() {
        const id = $(this).attr('data-exp-id');
        const rm = $('#dash-dm-new-remark').val().trim();
        if (!rm) {
            alert('Please enter a remark.');
            return;
        }
        $.ajax({
            url: '{{ route("claimant.add.remark") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', exp_id: id, remark: rm },
            success: function(res) {
                if (res.Code === '200' || (res.msg||'').toLowerCase().includes('success')) {
                    $('#dash-dm-remarks-list').append(`<li class="list-group-item py-2 px-3">
                        <small class="text-muted d-block fs-10">Just Now</small>
                        <span class="fs-12">${rm}</span>
                    </li>`);
                    $('#dash-dm-new-remark').val('');
                } else {
                    alert(res.msg || 'Failed to add remark');
                }
            },
            error: function() {
                alert('Server error occurred while adding remark');
            }
        });
    });

    // Delete Draft Handler
    $(document).on('click', '.dash-delete-draft-btn', function() {
        const id = $(this).data('exp-id');
        const proceedDelete = function() {
            $.ajax({
                url: '{{ route("claimant.draft.delete") }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', exp_id: id },
                success: function(res) {
                    if (res.Code === '300' || (res.msg||'').toLowerCase().includes('deleted')) {
                        $(`#dashDraftCard-${id}`).fadeOut(300, function() {
                            $(this).remove();
                            const curCnt = Math.max(0, parseInt($('#dashDraftCount').text()) - 1);
                            $('#dashDraftCount').text(curCnt);
                            if (curCnt === 0) {
                                $('#dashboardDraftsList').html(`
                                    <div class="text-center py-4 w-100">
                                        <i class="ri-draft-line text-muted" style="font-size:28px;"></i>
                                        <p class="text-muted mt-1 mb-0 fs-12">No draft bills uploaded</p>
                                    </div>
                                `);
                            }
                        });
                    } else {
                        alert(res.msg || 'Delete failed');
                    }
                },
                error: function() {
                    alert('Server error occurred while deleting draft');
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Draft?',
                text: 'This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, Delete'
            }).then(function(r) {
                if (r.isConfirmed) proceedDelete();
            });
        } else {
            if (confirm('Delete this draft bill? This cannot be undone.')) {
                proceedDelete();
            }
        }
    });

    // Fullscreen lightbox functions
    window.openDashLightbox = function(src) {
        if (!src) return;
        $('#dashLightboxImg').attr('src', src);
        $('#dashLightbox').addClass('active');
        $('body').css('overflow', 'hidden');
    };

    window.closeDashLightbox = function() {
        $('#dashLightbox').removeClass('active');
        $('body').css('overflow', '');
    };

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // MY CLAIMS - Mobile App Style (Month-wise expansion tiles)
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    loadMyClaimsMonthWise();
    $('#refreshMyClaims').on('click', loadMyClaimsMonthWise);

    function loadMyClaimsMonthWise() {
        const container = $('#myClaimsMonthContainer');
        container.html(`
            <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <p class="text-muted mt-2 mb-0">Loading claims...</p>
            </div>
        `);

        $.get("{{ route('dashboard.my-claims') }}", function(response) {
            if (response.success && response.open_list.length > 0) {
                container.html(renderMonthTiles(response.open_list));
            } else {
                container.html(`
                    <div class="text-center py-4">
                        <i class="ri-file-list-3-line text-muted fs-36"></i>
                        <p class="text-muted mt-2 mb-0">No claims found for this year</p>
                    </div>
                `);
            }
        }).fail(function() {
            container.html(`
                <div class="alert alert-danger mb-0">
                    <i class="ri-error-warning-line me-1"></i>Failed to load claims. Please try again.
                </div>
            `);
        });
    }

    function getMonthName(monthNum) {
        const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[parseInt(monthNum)] || '';
    }

    function getMonthFullName(monthNum) {
        const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return months[parseInt(monthNum)] || '';
    }

    function formatDate(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return '-';
        const d = new Date(dateStr);
        return d.getDate() + '/' + (d.getMonth() + 1) + '/' + d.getFullYear();
    }

    function getStatusBadge(claim) {
        const rawStatus = (claim.ClaimStatus || '').trim();
        const filledOkay = claim.FilledOkay;
        if (filledOkay == '2') return '<span class="badge bg-danger-subtle text-danger fs-10">Denied</span>';
        if (rawStatus.toLowerCase() === 'submitted' || rawStatus.toLowerCase() === 'draft') return '<span class="badge bg-secondary-subtle text-secondary fs-10">Draft</span>';
        if (rawStatus.toLowerCase() === 'filled') return '<span class="badge bg-primary-subtle text-primary fs-10">Filled</span>';
        if (rawStatus.toLowerCase() === 'verified') return '<span class="badge bg-info-subtle text-info fs-10">Verified</span>';
        if (rawStatus.toLowerCase() === 'approved') return '<span class="badge bg-success-subtle text-success fs-10">Approved</span>';
        if (rawStatus.toLowerCase() === 'financed') return '<span class="badge bg-success-subtle text-success fs-10">Financed</span>';
        if (rawStatus.toLowerCase() === 'deactivate' || rawStatus.toLowerCase() === 'deactivat') return '<span class="badge bg-dark-subtle text-dark fs-10">Deactivated</span>';
        return '<span class="badge bg-light text-muted fs-10">' + (rawStatus || 'Draft') + '</span>';
    }

    function renderMonthTiles(openList) {
        let html = '';
        openList.forEach(function(monthData, idx) {
            const month = monthData.Month;
            const monthName = getMonthName(month);
            const monthFull = getMonthFullName(month);
            const totalClaims = monthData.TotalFilled_Claim_with_Amount.TotClaim;
            const filledAmt = monthData.TotalFilled_Claim_with_Amount.TotFilledAmt;
            const verifiedAmt = monthData.TotalVerify_Claim_with_Amount.TotVerifyAmt;
            const deniedClaims = monthData.TotalFilled_Claim_with_Amount.TotDeniedClaims;
            const verifiedClaims = monthData.TotalFilled_Claim_with_Amount.TotVerifiedClaims;
            const confirmedClaims = monthData.TotalFilled_Claim_with_Amount.TotConfirmedClaims || 0;
            const monthStatus = monthData.MonthStatus;
            const employeeId = monthData.EmployeeID || '{{ session("employee_id") }}';
            const isOpen = idx === 0; // First month expanded by default

            const statusBadge = monthStatus === 'Closed'
                ? '<span class="badge bg-secondary fs-10">Closed</span>'
                : (monthStatus === 'Submitted'
                    ? '<span class="badge bg-primary fs-10">Submitted</span>'
                    : '<span class="badge bg-success-subtle text-success fs-10">Open</span>');

            html += `
            <div class="month-tile mb-2" data-month="${month}">
                <div class="month-tile-header" data-bs-toggle="collapse" data-bs-target="#monthClaims${month}" aria-expanded="${isOpen}">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="month-stripe"></div>
                            <div class="month-name">${monthName}</div>
                            <div class="month-meta ms-2">
                                <span class="text-muted fs-11">Claims:</span>
                                <span class="fw-semibold text-primary fs-12">${totalClaims}</span>
                                <span class="text-muted fs-11 ms-2">Amt:</span>
                                <span class="fw-semibold text-primary fs-12">₹${Number(filledAmt).toLocaleString('en-IN')}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-soft-info view-month-detail-btn"
                                data-employee-id="${employeeId}"
                                data-month="${month}"
                                data-month-name="${monthFull}"
                                onclick="event.stopPropagation();"
                                title="View Full Details">
                                <i class="ri-eye-line"></i> Detail
                            </button>
                            <a href="/expense/print-details?e=${employeeId}&y={{ session('year_id') }}&m=${month}&nn=2&c={{ session('company_id') }}"
                               target="_blank" class="btn btn-sm btn-soft-secondary"
                               onclick="event.stopPropagation();" title="Print">
                                <i class="ri-printer-line"></i>
                            </a>
                            ${statusBadge}
                            <i class="ri-arrow-down-s-line fs-18 text-muted month-chevron"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mt-1 ms-4 ps-2">
                        <span class="text-muted fs-11">Verified Amt:</span>
                        <span class="fw-semibold text-success fs-11 ms-1">₹${Number(verifiedAmt).toLocaleString('en-IN')}</span>
                        <span class="text-muted fs-11 ms-3" title="Checked by the verifier">Verified:</span>
                        <span class="text-success fs-11 ms-1">${verifiedClaims}</span>
                        <span class="text-muted fs-11 ms-2" title="Punched figures confirmed by you, not yet verified">Confirmed:</span>
                        <span class="text-primary fs-11 ms-1">${confirmedClaims}</span>
                        <span class="text-muted fs-11 ms-2">Denied:</span>
                        <span class="text-danger fs-11 ms-1">${deniedClaims}</span>
                    </div>
                </div>
                <div class="collapse ${isOpen ? 'show' : ''}" id="monthClaims${month}">
                    ${monthStatus !== 'Open' ? `<div class="month-timeline-stepper d-flex align-items-start justify-content-center py-2 px-3 mb-1" style="background:#f8fdf9;border-top:1px solid #e9ecef;">
                        ${renderMonthStepper(monthData)}
                    </div>` : ''}
                    <div class="month-claims-scroll">
                        ${renderClaimCards(monthData.DayWiseDetails)}
                    </div>
                </div>
            </div>`;
        });
        return html;
    }

    function renderMonthStepper(monthData) {
        const claims = monthData.DayWiseDetails || [];
        const filledAmt = parseInt(monthData.TotalFilled_Claim_with_Amount.TotFilledAmt || 0);
        const verifiedAmt = parseInt(monthData.Verified_Amount || monthData.TotalVerify_Claim_with_Amount.TotVerifyAmt || 0);
        const approvedAmt = parseInt(monthData.Approved_Amount || 0);
        const financeAmt  = parseInt(monthData.Finance_Amount || 0);

        const submitDate   = monthData.DateOfSubmit || '';
        const verifiedDate = monthData.Verified_Date || '';
        const approvedDate = monthData.Approved_Date || '';
        const financeDate  = monthData.Finance_Date || '';

        const claimantDone = claims.length > 0 && submitDate && submitDate !== '0000-00-00';
        const verifierDone = verifiedDate && verifiedDate !== '0000-00-00';
        const approverDone = approvedDate && approvedDate !== '0000-00-00';
        const financeDone  = financeDate && financeDate !== '0000-00-00';

        function miniStep(label, icon, done, amt, date, isLast) {
            const color = done ? '#198754' : '#6c757d';
            const line = !isLast ? `<div style="flex:1;height:2px;max-width:40px;background:${done ? '#198754' : '#dee2e6'};margin:0 3px;align-self:center;"></div>` : '';
            const dateStr = done ? formatDate(date) : '-';
            return `<div style="display:flex;flex-direction:column;align-items:center;min-width:70px;">
                <div style="width:26px;height:26px;border-radius:50%;background:${done ? '#d1e7dd' : '#f8f9fa'};display:flex;align-items:center;justify-content:center;border:2px solid ${color};">
                    <i class="${icon}" style="color:${color};font-size:12px;"></i>
                </div>
                <div class="fw-bold" style="font-size:10px;color:${color};margin-top:2px;">${label}</div>
                <div style="font-size:10px;color:#7C50D9;font-weight:600;">${done ? '₹' + amt.toLocaleString('en-IN') : '-'}</div>
                <div style="font-size:9px;color:#6c757d;">${dateStr}</div>
            </div>${line}`;
        }

        return miniStep('Claimant', 'ri-user-line', claimantDone, filledAmt, submitDate, false) +
               miniStep('Verifier', 'ri-shield-check-line', verifierDone, verifiedAmt, verifiedDate, false) +
               miniStep('Approver', 'ri-checkbox-circle-line', approverDone, approvedAmt, approvedDate, false) +
               miniStep('Finance', 'ri-bank-line', financeDone, financeAmt, financeDate, true);
    }

    function renderClaimCards(claims) {
        if (!claims || claims.length === 0) {
            return '<div class="text-center text-muted py-3 fs-12">No claims in this month</div>';
        }

        let html = '<div class="claim-cards-row">';
        claims.forEach(function(claim) {
            const isOdometer = claim.ClaimId == '7';
            const amountDisplay = isOdometer
                ? `<span class="fw-bold text-primary fs-13">${parseInt(claim.TotKm || 0)} KM</span>`
                : `<span class="fw-bold text-primary fs-13">₹${Number(claim.FilledTAmt || 0).toLocaleString('en-IN')}</span>`;

            html += `
            <div class="claim-card">
                <div class="claim-card-header">
                    <span class="claim-type-name">${claim.ClaimName || 'Claim'}</span>
                    ${getStatusBadge(claim)}
                </div>
                <div class="claim-card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-11">${formatDate(claim.BillDate)}</span>
                        ${amountDisplay}
                    </div>
                </div>
                <div class="claim-card-footer">
                    <a href="#" class="view-claim text-decoration-none"
                       data-expid="${claim.ExpId}"
                       data-claim-id="${claim.ClaimId}"
                       data-cgid="${claim.ClaimId}"
                       data-empid="{{ session('employee_id') }}">
                        <i class="ri-eye-line fs-12 text-primary"></i>
                        <span class="text-primary fs-10 ms-1">View Details</span>
                    </a>
                </div>
            </div>`;
        });
        html += '</div>';
        return html;
    }

    function openClaimDetail(expId, claimId) {
        // Now handled by claim.js via .view-claim class
        // Trigger the view-claim click programmatically if needed
        $('<a href="#" class="view-claim" data-expid="'+expId+'" data-claim-id="'+claimId+'" data-cgid="'+claimId+'" data-empid="{{ session("employee_id") }}"></a>').appendTo('body').trigger('click').remove();
    }

    function renderUploadedFiles(files) {
        if (!files || files.length === 0) return '<p class="text-muted fs-12">No files uploaded</p>';
        let html = '';
        files.forEach(function(file) {
            html += `
            <div class="mb-2 text-center">
                <img alt="Bill or document image" src="${file.file_url}" class="img-fluid border rounded"
                     style="max-height:120px; object-fit:cover; cursor:pointer;"
                     onclick="window.open('${file.file_url}','_blank')">
                <br><small class="text-muted">${file.file_name || ''}</small>
            </div>`;
        });
        return html;
    }

    // Detail View button handler â€” opens the full claims table modal like eHome
    $(document).on('click', '.view-month-detail-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const employeeId = $(this).data('employee-id');
        const month = $(this).data('month');
        const monthName = $(this).data('month-name');
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i>');

        $.ajax({
            url: '{{ url("employee-all-claims") }}',
            type: 'GET',
            data: { month: month, crby: employeeId },
            dataType: 'json',
            success: function(response) {
                const claims = Array.isArray(response) ? response : [];
                if (claims.length === 0) {
                    alert('No claims found for ' + monthName);
                    btn.prop('disabled', false).html('<i class="ri-eye-line"></i> Detail');
                    return;
                }
                showMonthDetailModal(claims, monthName);
                btn.prop('disabled', false).html('<i class="ri-eye-line"></i> Detail');
            },
            error: function() {
                alert('Failed to load claim details');
                btn.prop('disabled', false).html('<i class="ri-eye-line"></i> Detail');
            }
        });
    });

    function showMonthDetailModal(claims, monthName) {
        // Calculate totals for the timeline
        let totalFilled = 0, totalVerified = 0, totalApproved = 0, totalFinanced = 0;
        let hasVerifyDate = false, hasApprDate = false, hasFinDate = false;
        let latestSubmitDate = '', latestVerifyDate = '', latestApprDate = '', latestFinDate = '';

        claims.forEach(function(c) {
            totalFilled   += parseFloat(c.FilledTAmt || 0);
            totalVerified += parseFloat(c.VerifyTAmt || 0);
            totalApproved += parseFloat(c.ApprTAmt || 0);
            totalFinanced += parseFloat(c.FinancedTAmt || 0);
            if (c.VerifyDate && c.VerifyDate !== '0000-00-00') { hasVerifyDate = true; latestVerifyDate = c.VerifyDate; }
            if (c.ApprDate && c.ApprDate !== '0000-00-00') { hasApprDate = true; latestApprDate = c.ApprDate; }
            if (c.FinancedDate && c.FinancedDate !== '0000-00-00') { hasFinDate = true; latestFinDate = c.FinancedDate; }
            if (c.CrDate && c.CrDate !== '0000-00-00') latestSubmitDate = c.CrDate;
        });

        // Build timeline stepper
        function stepIcon(label, done, amt, date) {
            const icons = { 'Claimant': 'ri-user-line', 'Verifier': 'ri-shield-check-line', 'Approver': 'ri-checkbox-circle-line', 'Finance': 'ri-bank-line' };
            const color = done ? '#198754' : '#6c757d';
            const icon = icons[label] || 'ri-time-line';
            return `<div style="display:flex;flex-direction:column;align-items:center;min-width:100px;">
                <div style="width:36px;height:36px;border-radius:50%;background:${done ? '#d1e7dd' : '#f8f9fa'};display:flex;align-items:center;justify-content:center;border:2px solid ${color};">
                    <i class="${icon}" style="color:${color};font-size:18px;"></i>
                </div>
                <div class="fw-bold mt-1" style="font-size:12px;color:${color};">${label}</div>
                <div style="font-size:12px;color:#7C50D9;font-weight:600;">${done ? '₹' + amt.toLocaleString('en-IN') : '-'}</div>
                <div style="font-size:10px;color:#6c757d;">${done ? formatDate(date) : '-'}</div>
            </div>`;
        }
        function stepLine(done) {
            return `<div style="flex:1;height:2px;background:${done ? '#198754' : '#dee2e6'};margin:0 6px;align-self:center;"></div>`;
        }

        const timelineStepper = `
            <div class="d-flex align-items-start justify-content-center py-3 px-4" style="background:#f8fdf9;border-bottom:1px solid #e9ecef;">
                ${stepIcon('Claimant', true, totalFilled, latestSubmitDate)}
                ${stepLine(hasVerifyDate)}
                ${stepIcon('Verifier', hasVerifyDate, totalVerified, latestVerifyDate)}
                ${stepLine(hasApprDate)}
                ${stepIcon('Approver', hasApprDate, totalApproved, latestApprDate)}
                ${stepLine(hasFinDate)}
                ${stepIcon('Finance', hasFinDate, totalFinanced, latestFinDate)}
            </div>`;

        let rows = '';
        claims.forEach(function(c, i) {
            rows += `<tr>
                <td>${i+1}</td>
                <td>${c.ExpId || ''}</td>
                <td>${c.ClaimName || ''}</td>
                <td>${formatDate(c.BillDate)}</td>
                <td>${formatDate(c.CrDate)}</td>
                <td class="text-end">${c.FilledTAmt ? Number(c.FilledTAmt).toLocaleString('en-IN') : '-'}</td>
                <td>${c.DateEntryRemark || ''}</td>
                <td class="text-end">${c.VerifyTAmt ? Number(c.VerifyTAmt).toLocaleString('en-IN') : '-'}</td>
                <td>${formatDate(c.VerifyDate)}</td>
                <td>${c.VerifyTRemark || ''}</td>
                <td class="text-end">${c.ApprTAmt ? Number(c.ApprTAmt).toLocaleString('en-IN') : '-'}</td>
                <td>${formatDate(c.ApprDate)}</td>
                <td>${c.ApprTRemark || ''}</td>
                <td class="text-end">${c.FinancedTAmt ? Number(c.FinancedTAmt).toLocaleString('en-IN') : '-'}</td>
                <td>${formatDate(c.FinancedDate)}</td>
                <td>${c.ClaimStatus || ''}</td>
            </tr>`;
        });

        const modalHtml = `
        <div class="modal fade" id="monthDetailModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0">
                    <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                        <h5 class="modal-title"><i class="ri-calendar-line me-1"></i> ${monthName} — All Claims (${claims.length})</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    ${timelineStepper}
                    <div class="modal-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm align-middle mb-0" style="font-size:11px;">
                                <thead style="background:#f0f4f8;">
                                    <tr>
                                        <th>#</th>
                                        <th>Exp ID</th>
                                        <th>Claim Type</th>
                                        <th>Bill Date</th>
                                        <th>Upload Date</th>
                                        <th class="text-end">Filled Amt</th>
                                        <th>Remark</th>
                                        <th class="text-end">Verified Amt</th>
                                        <th>Verify Date</th>
                                        <th>Verify Remark</th>
                                        <th class="text-end">Approved Amt</th>
                                        <th>Appr Date</th>
                                        <th>Appr Remark</th>
                                        <th class="text-end">Finance Amt</th>
                                        <th>Finance Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                                <tfoot style="background:#f4f6df;">
                                    <tr class="fw-bold">
                                        <td colspan="5" class="text-end">Totals:</td>
                                        <td class="text-end">₹${totalFilled.toLocaleString('en-IN')}</td>
                                        <td></td>
                                        <td class="text-end">₹${totalVerified.toLocaleString('en-IN')}</td>
                                        <td colspan="2"></td>
                                        <td class="text-end">₹${totalApproved.toLocaleString('en-IN')}</td>
                                        <td colspan="2"></td>
                                        <td class="text-end">₹${totalFinanced.toLocaleString('en-IN')}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

        $('#monthDetailModal').remove();
        $('body').append(modalHtml);
        $('#monthDetailModal').modal('show');
    }

    // ══════════════════════════════════════════════════════════════
    // ADVANCE DATA — lazy-load when Approver tab is shown
    // ══════════════════════════════════════════════════════════════
    @if(session('advance_module') == 'Y')
    let advanceTabLoaded = false;

    function triggerAdvanceTabLoad() {
        if (advanceTabLoaded) return;
        advanceTabLoaded = true;
        loadAdvanceMyRequests();
        @if(session('adv_approver') == 'Y')
        loadAdvancePendingApprovals();
        @endif
        loadAdvanceRecentList();
    }

    $('a[href="#approver-tab"]').on('shown.bs.tab', function () {
        triggerAdvanceTabLoad();
    });

    if ($('#approver-tab').hasClass('active') || window.location.hash === '#approver-tab') {
        triggerAdvanceTabLoad();
    }

    function fmtAdvStatus(status) {
        const map = {
            'pending':  ['bg-warning-subtle text-warning',  'Pending'],
            'approved': ['bg-success-subtle text-success',  'Approved'],
            'rejected': ['bg-danger-subtle text-danger',    'Rejected'],
            'reverted': ['bg-info-subtle text-info',        'Reverted'],
            'finance':  ['bg-primary-subtle text-primary',  'Finance'],
        };
        const key  = (status || '').toLowerCase();
        const pair = map[key] || ['bg-secondary-subtle text-secondary', status || '—'];
        return `<span class="badge ${pair[0]}" style="font-size:10px;">${pair[1]}</span>`;
    }

    function fmtAdvDate(d) {
        if (!d || d === '0000-00-00' || d === '1970-01-01') return '—';
        try { return new Date(d).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}); }
        catch(e){ return d; }
    }

    // DASH-DUP-01: the "my advances" list is read once and shared by the summary and the recent list
    function advMyList() { return window.__advMyList || (window.__advMyList = $.getJSON('{{ url("/api/advance") }}')); }
    function loadAdvanceMyRequests() {
        advMyList().done(function(res) {
            const rows = (res.data || res.requests || (Array.isArray(res) ? res : [])).slice(0, 5);
            if (!rows.length) {
                $('#advMyRequestsSummary').html('<p class="text-muted fs-12 mb-0 text-center">No requests found</p>');
                return;
            }
            let h = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" style="font-size:11px;"><thead class="table-light"><tr><th>Activity</th><th>Month</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>';
            rows.forEach(function(r) {
                const months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                const mo  = months[parseInt(r.month || r.Month || 0)] || '—';
                const act = r.activity_name || r.activityName || r.advance_type || '—';
                const amt = r.total_amount || r.amount || r.Amount || 0;
                h += `<tr><td>${act}</td><td>${mo}</td><td class="text-end">₹${Number(amt).toLocaleString('en-IN')}</td><td>${fmtAdvStatus(r.current_status || r.status)}</td></tr>`;
            });
            h += '</tbody></table></div>';
            $('#advMyRequestsSummary').html(h);
        }).fail(function() {
            $('#advMyRequestsSummary').html('<p class="text-danger fs-12 mb-0">Failed to load</p>');
        });
    }

    @if(session('adv_approver') == 'Y')
    function loadAdvancePendingApprovals() {
        $.getJSON('{{ url("/api/advance/approvals/pending") }}').done(function(res) {
            const rows = (res.data || res.requests || res || []);
            const count = rows.length;
            // Update badge
            if (count > 0) {
                $('#advPendingBadge').text(count).show();
                $('#advApproverChipBadge').text(count).show();
            }
            if (!count) {
                $('#advPendingApprovalsSummary').html('<p class="text-muted fs-12 mb-0 text-center"><i class="ri-check-double-line text-success me-1"></i>No pending approvals</p>');
                return;
            }
            let h = `<div class="d-flex align-items-center justify-content-between mb-2"><span class="fs-13 fw-semibold text-success">${count} request${count===1?'':'s'} waiting</span></div>`;
            h += '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" style="font-size:11px;"><thead class="table-light"><tr><th>Employee</th><th>Activity</th><th class="text-end">Amount</th><th>Submitted</th></tr></thead><tbody>';
            rows.slice(0, 5).forEach(function(r) {
                const emp  = r.employee_name || r.employeeName || '—';
                const act  = r.activity_name || r.activityName || '—';
                const amt  = r.total_amount  || r.amount || 0;
                const date = fmtAdvDate(r.last_submitted_at || r.created_at || '');
                h += `<tr><td>${emp}</td><td>${act}</td><td class="text-end">₹${Number(amt).toLocaleString('en-IN')}</td><td>${date}</td></tr>`;
            });
            h += '</tbody></table></div>';
            $('#advPendingApprovalsSummary').html(h);
        }).fail(function() {
            $('#advPendingApprovalsSummary').html('<p class="text-danger fs-12 mb-0">Failed to load</p>');
        });
    }
    @endif

    function loadAdvanceRecentList() {
        // Merge activity + expense requests, show most recent 8
        const reqs = [];
        const done = (r) => {
            const rows = (r.data || r.requests || (Array.isArray(r) ? r : []));
            rows.forEach(row => reqs.push(row));
        };
        $.when(
            advMyList().done(done),
            $.getJSON('{{ url("/api/advance/expense") }}').done(done)
        ).always(function() {
            reqs.sort((a,b) => new Date(b.created_at||0) - new Date(a.created_at||0));
            const top = reqs.slice(0, 8);
            if (!top.length) {
                $('#advRecentRequestsList').html('<div class="text-center py-4 text-muted"><i class="ri-inbox-line fs-36 d-block mb-2"></i>No advance requests yet</div>');
                return;
            }
            const months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            let h = '<div class="table-responsive"><table class="table table-hover table-sm align-middle mb-0" style="font-size:12px;"><thead class="table-light"><tr>'
                  + '<th>#</th><th>Type</th><th>Activity</th><th>Month</th><th class="text-end">Amount</th><th>Date</th><th>Status</th><th class="text-center">Action</th>'
                  + '</tr></thead><tbody>';
            top.forEach(function(r, i) {
                const type  = r.advance_type || r.type || 'Activity';
                const act   = r.activity_name || r.activityName || '—';
                const mo    = months[parseInt(r.month || r.Month || 0)] || '—';
                const amt   = r.total_amount || r.amount || r.Amount || 0;
                const date  = fmtAdvDate(r.created_at || r.advance_fill_date || '');
                const id    = r.id || r.request_id || '';
                h += `<tr>
                    <td class="text-center text-muted">${i+1}</td>
                    <td><span class="badge bg-light text-dark border" style="font-size:10px;">${type}</span></td>
                    <td>${act}</td>
                    <td>${mo}</td>
                    <td class="text-end fw-semibold">₹${Number(amt).toLocaleString('en-IN')}</td>
                    <td>${date}</td>
                    <td>${fmtAdvStatus(r.current_status || r.status)}</td>
                    <td class="text-center">
                        <a href="{{ url('/advance-requests') }}" class="btn btn-xs btn-soft-primary" style="font-size:10px;padding:2px 8px;">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
                </tr>`;
            });
            h += '</tbody></table></div>';
            $('#advRecentRequestsList').html(h);
        });
    }
    @endif
});
</script>

{{-- Mobile App Style Claims CSS --}}
<style>
/* Quick Action Buttons */
.quick-action-btn {
    min-width: 70px;
}
.quick-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    transition: all 0.2s;
}
.quick-action-btn:hover .quick-action-icon {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* TAT Pipeline Stages */
.tat-pipeline {
    gap: 4px;
}
.tat-stage {
    flex: 1;
    min-width: 100px;
}
.tat-stage-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #f0f4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e0e7ff;
    color: #405189;
}
.tat-arrow {
    display: flex;
    align-items: center;
    padding-top: 10px;
}
.tat-total-banner {
    border: 1px solid #c8e6c9;
}

.month-tile {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    transition: box-shadow 0.2s;
}
.month-tile:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.month-tile-header {
    padding: 10px 14px;
    cursor: pointer;
    background: #fff;
    border-bottom: 1px solid #f1f1f1;
    position: relative;
}
.month-tile-header[aria-expanded="true"] .month-chevron {
    transform: rotate(180deg);
}
.month-chevron {
    transition: transform 0.2s;
}
.month-stripe {
    width: 5px;
    height: 36px;
    background: #dde6ff;
    border-radius: 3px;
    margin-right: 10px; color: #1e3a8a;}
.month-tile-header[aria-expanded="true"] .month-stripe {
    background: #45cb85;
}
.month-name {
    font-weight: 700;
    font-size: 15px;
    color: #405189;
    min-width: 35px;
}
.month-claims-scroll {
    padding: 10px;
    background: #f8f9fa;
    overflow-x: auto;
}
.claim-cards-row {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 6px;
}
.claim-cards-row::-webkit-scrollbar {
    height: 5px;
}
.claim-cards-row::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 4px;
}
.claim-card {
    min-width: 180px;
    /* max-width: 200px; */
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.claim-card:hover {
    border-color: #405189;
    box-shadow: 0 3px 10px rgba(64, 81, 137, 0.15);
    transform: translateY(-2px);
}
.claim-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.claim-type-name {
    font-weight: 600;
    font-size: 12px;
    color: #333;
    line-height: 1.2;
    max-width: 110px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.claim-card-body {
    margin-bottom: 6px;
}
.claim-card-footer {
    border-top: 1px solid #f1f1f1;
    padding-top: 6px;
    text-align: center;
}
.claim-card-footer:hover {
    color: #405189;
}

/* ════════════════════════════════════════
   DRAFT BILL CARDS (Dashboard / Claimant)
   ════════════════════════════════════════ */
.draft-bill-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #fde68a;
    box-shadow: 0 2px 10px rgba(245,158,11,.08);
    transition: all .2s ease;
    flex: 0 0 215px;
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin: 1px;
}
.draft-bill-card:hover {
    box-shadow: 0 6px 24px rgba(245,158,11,.22);
    transform: translateY(-3px);
    border-color: #f59e0b;
}
.draft-img-wrap {
    width: 100%;
    height: 130px;
    background: #f1f5f9;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.draft-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .25s ease;
}
.draft-img-wrap:hover img {
    transform: scale(1.06);
}
.draft-img-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    color: #cbd5e1;
}
.draft-img-placeholder i { font-size: 2.2rem; }
.draft-img-placeholder span { font-size: .66rem; font-weight: 600; color: #94a3b8; }
.draft-accent-bar {
    height: 3px;
    background: #fdefd2;
    flex-shrink: 0; color: #7a4500;}
.draft-extra-thumbs {
    display: flex;
    gap: 5px;
    padding: 6px 12px 0;
}
.draft-extra-thumb {
    width: 40px;
    height: 32px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all .17s ease;
    flex-shrink: 0;
}
.draft-extra-thumb:hover {
    border-color: #15648C;
    transform: scale(1.12);
}
.draft-info {
    padding: 10px 14px 4px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    flex: 1;
}
.dbh-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.draft-type {
    font-size: .86rem;
    font-weight: 800;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.draft-meta { font-size: .69rem; color: #94a3b8; }
.draft-amount {
    font-size: .96rem;
    font-weight: 800;
    color: #2563eb;
    white-space: nowrap;
}
.draft-bill-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-top: auto;
    padding: 10px 12px 12px;
    border-top: 1px solid #fef3c7;
}
.dab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    border-radius: 8px;
    padding: 7px 14px;
    font-size: .76rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .17s ease;
    text-decoration: none;
}
.dab-view { background: #dbeafe; color: #1d4ed8; }
.dab-view:hover { background: #dde6ff; color: #1e3a8a; }
.dab-del  { background: #fee2e2; color: #dc2626; }
.dab-del:hover  { background: #fde2e2; color: #9b1c1c; }
.sbg {
    display: inline-block;
    border-radius: 5px;
    padding: 2px 8px;
    font-size: .66rem;
    font-weight: 700;
    white-space: nowrap;
}
.sbg-draft { background: #e0f2fe; color: #0284c7; }
.mcv-exp-id {
    font-weight: 800 !important;
    color: #0f172a !important;
    font-size: .76rem;
    letter-spacing: -.2px;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    white-space: nowrap;
}
.draft-remark {
    font-size: .68rem;
    color: #78350f;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 5px;
    padding: 3px 6px;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.25;
}
.pdf-card-preview {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(145deg, #fff1f2 0%, #ffe4e6 100%) !important;
    color: #e11d48 !important;
    text-decoration: none !important;
    padding: 12px !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease !important;
    cursor: pointer !important;
}
.pdf-card-preview:hover {
    background: linear-gradient(145deg, #ffe4e6 0%, #fecdd3 100%) !important;
    color: #be123c !important;
}
.pdf-card-preview .pdf-icon-badge {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(225, 29, 72, 0.16);
}
.pdf-card-preview .pdf-icon-badge i { font-size: 26px; color: #e11d48; }
.pdf-card-preview .pdf-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: #9f1239;
    text-align: center;
}
.mcv-lightbox {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(6px);
    z-index: 100050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.mcv-lightbox.active { display: flex !important; }
.mcv-lightbox-box {
    position: relative;
    max-width: 92vw;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: dashZoomIn .2s ease;
}
@keyframes dashZoomIn {
    from { opacity: 0; transform: scale(.92); }
    to { opacity: 1; transform: scale(1); }
}
.mcv-lightbox-img {
    max-width: 90vw;
    max-height: 86vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 16px 50px rgba(0,0,0,0.65);
    background: #fff;
}
.mcv-lightbox-close {
    position: absolute;
    top: -46px;
    right: -6px;
    background: rgba(255,255,255,0.22);
    color: #fff;
    border: none;
    font-size: 1.8rem;
    line-height: 1;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s, transform .2s;
}
.mcv-lightbox-close:hover {
    background: rgba(255,255,255,0.45);
    transform: scale(1.08);
}
.spin-me { animation: spin 0.8s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
@endpush

@push('scripts')
<script>
function changeDashboardMonth(monthVal) {
    var validTabs = ['#claimant-tab', '#approver-tab', '#verifier-tab', '#finance-tab'];
    var activeTab = $('#mainDashboardTabs a.active').attr('href') || localStorage.getItem('active_dashboard_tab') || '#claimant-tab';
    if (activeTab === '#advance-tab') {
        activeTab = '#approver-tab';
    }
    if (validTabs.indexOf(activeTab) === -1) {
        activeTab = '#claimant-tab';
    }
    var cleanTab = activeTab.replace('#', '');
    localStorage.setItem('active_dashboard_tab', activeTab);
    window.location.href = '{{ url()->current() }}?month=' + monthVal + '&tab=' + cleanTab + '#' + cleanTab;
}

$(document).ready(function() {
    var validTabs = ['#claimant-tab', '#approver-tab', '#verifier-tab', '#finance-tab'];
    if (localStorage.getItem('active_dashboard_tab') === '#advance-tab') {
        localStorage.setItem('active_dashboard_tab', '#approver-tab');
    }

    // Listen only to main dashboard tabs (ignoring topNavbar notification bell tabs)
    $('#mainDashboardTabs a[data-bs-toggle="tab"]').on('shown.bs.tab click', function (e) {
        var target = $(this).attr("href");
        if (target && validTabs.indexOf(target) !== -1) {
            localStorage.setItem('active_dashboard_tab', target);
            if (history.replaceState) {
                var cleanTab = target.replace('#', '');
                var url = new URL(window.location.href);
                url.searchParams.set('tab', cleanTab);
                url.hash = cleanTab;
                history.replaceState(null, null, url.toString());
            }
        }
    });
});
</script>
@endpush

@push('scripts')
<script>
// EHOME-MAIN-01: load the eHome view of the logged-in employee into the dashboard (same page as the admin's E-Home)
window.loadMyEhome = function () {
    var $b = $('#myEhomeBody');
    if (!$b.length) return;
    $b.html('<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...</div>');
    $.ajax({ url: '/expense/ehome-dashboard', method: 'GET', data: { year_id: '{{ (int) session('year_id') }}', company_id: '{{ (int) session('company_id') }}' },
        success: function (html) { $b.html(html); },
        error: function () { $b.html('<div class="text-center text-danger py-3">Could not load E-Home.</div>'); } });
};
$(function () { window.loadMyEhome(); $('#refreshMyEhome').on('click', window.loadMyEhome); });
</script>
@endpush
