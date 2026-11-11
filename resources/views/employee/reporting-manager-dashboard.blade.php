@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">

            {{-- Dashboard Header --}}
            <div class="row mb-3 pb-1">
                <div class="col-12">
                    <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                        <div class="flex-grow-1">
                            <h4 class="fs-16 mb-1">Reporting Manager Dashboard</h4>
                            <p class="text-muted mb-0">
                                Welcome, <strong>{{ session('emp_name', 'User') }}</strong>
                                <span class="text-muted ms-2">|</span>
                                <span class="ms-2">{{ session('department_name', '') }}</span>
                            </p>
                        </div>
                        <div class="mt-3 mt-lg-0">
                            <span class="badge bg-info-subtle text-info fs-12">
                                <i class="ri-user-follow-line me-1"></i>Reporting Manager
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ url('/reporting-manager/approvals') }}" class="btn btn-primary btn-label waves-effect waves-light">
                            <i class="ri-arrow-right-circle-line label-icon align-middle fs-16 me-2"></i>Go to Approvals
                        </a>
                        <a href="{{ url('/reporting-manager/team') }}" class="btn btn-soft-primary waves-effect waves-light">
                            <i class="ri-team-line me-1"></i>View Team
                        </a>
                    </div>
                </div>
            </div>

            {{-- Stats Cards Row --}}
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3 material-shadow">
                                        <i class="ri-time-line align-middle"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Pending Approval</p>
                                    <h4 class="mb-0">{{ $reportingStats->pending_approval ?? 0 }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3 material-shadow">
                                        <i class="ri-check-double-fill align-middle"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Claims Approved</p>
                                    <h4 class="mb-0">{{ $reportingStats->approved ?? 0 }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3 material-shadow">
                                        <i class="ri-close-circle-fill align-middle"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Claims Returned</p>
                                    <h4 class="mb-0">{{ $reportingStats->returned ?? 0 }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3 material-shadow">
                                        <i class="ri-money-dollar-circle-line align-middle"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">Team Expenses</p>
                                    <h4 class="mb-0">₹ {{ number_format($reportingStats->team_expenses ?? 0, 0) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Main Content Row --}}
            <div class="row">
                {{-- Left: Charts & Tables --}}
                <div class="col-xl-8">
                    {{-- Claims by Status --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Claims by Status</h4>
                        </div>
                        <div class="card-body">
                            <div id="claims-status-chart" class="apex-charts" dir="ltr" style="height: 300px;"></div>
                        </div>
                    </div>

                    {{-- Approval Inbox --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-inbox-line me-1"></i>Approval Inbox
                            </h4>
                            <button class="btn btn-sm btn-soft-primary" id="refreshApprovalInbox">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Claim ID</th>
                                            <th>Amount</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="approvalInboxTable">
                                        @forelse($pendingApprovals ?? [] as $approval)
                                            <tr>
                                                <td>{{ $approval->employee_name ?? '-' }}</td>
                                                <td><span class="badge bg-light text-dark">{{ $approval->claim_id ?? '-' }}</span></td>
                                                <td>₹ {{ number_format($approval->amount ?? 0, 2) }}</td>
                                                <td class="text-center"><span class="badge bg-warning-subtle text-warning">Pending</span></td>
                                                <td class="text-center">
                                                    <a href="#" class="btn btn-icon btn-sm btn-primary waves-effect waves-light" title="Review">
                                                        <i class="ri-eye-line"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">No pending approvals</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Team Claims Summary --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-team-line me-1"></i>Team Claims Summary
                            </h4>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th class="text-center">Total Claims</th>
                                            <th class="text-center">Pending</th>
                                            <th class="text-center">Approved</th>
                                            <th class="text-center">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($teamClaimsSummary ?? [] as $team)
                                            <tr>
                                                <td>{{ $team->employee_name ?? '-' }}</td>
                                                <td class="text-center">{{ $team->total_claims ?? 0 }}</td>
                                                <td class="text-center"><span class="badge bg-warning-subtle text-warning">{{ $team->pending ?? 0 }}</span></td>
                                                <td class="text-center"><span class="badge bg-success-subtle text-success">{{ $team->approved ?? 0 }}</span></td>
                                                <td class="text-center">₹ {{ number_format($team->total_amount ?? 0, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">No team members found</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Sidebar --}}
                <div class="col-xl-4">
                    {{-- Expense Overview --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Expense Overview</h4>
                        </div>
                        <div class="card-body">
                            <div id="expense-overview-chart" class="apex-charts" dir="ltr" style="height: 250px;"></div>
                        </div>
                    </div>

                    {{-- Expense Trend --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Expense Trend (Last 6 Months)</h4>
                        </div>
                        <div class="card-body">
                            <div id="expense-trend-chart" class="apex-charts" dir="ltr" style="height: 250px;"></div>
                        </div>
                    </div>

                    {{-- SLA Performance --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">SLA Performance</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="mb-2">
                                            <div id="sla-on-time" class="apex-charts" style="height: 120px;"></div>
                                        </div>
                                        <p class="text-muted mb-0 fs-13">On Time</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="mb-2">
                                            <div id="sla-delayed" class="apex-charts" style="height: 120px;"></div>
                                        </div>
                                        <p class="text-muted mb-0 fs-13">Delayed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Compliance Alerts --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-alert-line me-1"></i>Compliance Alerts
                            </h4>
                            <a href="#" class="link-primary fs-13">View All</a>
                        </div>
                        <div class="card-body">
                            @forelse($complianceAlerts ?? [] as $alert)
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-shrink-0">
                                        @if($alert->severity === 'high')
                                            <span class="badge bg-danger-subtle rounded-circle">
                                                <i class="ri-alert-fill text-danger"></i>
                                            </span>
                                        @elseif($alert->severity === 'medium')
                                            <span class="badge bg-warning-subtle rounded-circle">
                                                <i class="ri-alert-line text-warning"></i>
                                            </span>
                                        @else
                                            <span class="badge bg-info-subtle rounded-circle">
                                                <i class="ri-information-line text-info"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1 fs-13">{{ $alert->title ?? 'Alert' }}</h6>
                                        <p class="text-muted mb-0 fs-12">{{ $alert->message ?? '-' }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted text-center py-3 mb-0">No alerts</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Top Insights --}}
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-lightbulb-line me-1"></i>Top Insights
                            </h4>
                        </div>
                        <div class="card-body">
                            @forelse($topInsights ?? [] as $insight)
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="avatar-title bg-light rounded-circle fs-5">
                                            <i class="ri-star-line text-warning"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <p class="text-muted mb-0 fs-13">{{ $insight->message ?? '-' }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted text-center py-3 mb-0">No insights available</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart Initialization Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Claims by Status Chart
            const claimsStatusOptions = {
                series: {{ json_encode($claimsStatusData['series'] ?? [45, 30, 20, 5]) }},
                labels: {{ json_encode($claimsStatusData['labels'] ?? ['Pending', 'Approved', 'Returned', 'Paid']) }},
                chart: {
                    type: 'donut',
                    height: 300,
                },
                colors: ['#FFC107', '#28A745', '#DC3545', '#17A2B8'],
                legend: {
                    position: 'bottom',
                },
                dataLabels: {
                    enabled: true,
                },
            };
            new ApexCharts(document.querySelector('#claims-status-chart'), claimsStatusOptions).render();

            // Expense Overview Chart
            const expenseOverviewOptions = {
                series: {{ json_encode($expenseOverviewData['series'] ?? [30, 25, 20, 15, 10]) }},
                labels: {{ json_encode($expenseOverviewData['labels'] ?? ['Travel', 'Hotel', 'Meals', 'Transport', 'Others']) }},
                chart: {
                    type: 'pie',
                    height: 250,
                },
                colors: ['#007BFF', '#28A745', '#FFC107', '#DC3545', '#6C757D'],
                legend: {
                    position: 'bottom',
                },
            };
            new ApexCharts(document.querySelector('#expense-overview-chart'), expenseOverviewOptions).render();

            // Expense Trend Chart
            const expenseTrendOptions = {
                series: [
                    {
                        name: 'Total Expenses',
                        data: {{ json_encode($expenseTrendData['data'] ?? [5000, 6000, 5500, 7000, 6500, 7500]) }},
                    },
                ],
                xaxis: {
                    categories: {{ json_encode($expenseTrendData['months'] ?? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']) }},
                },
                chart: {
                    type: 'line',
                    height: 250,
                },
                colors: ['#007BFF'],
                stroke: {
                    curve: 'smooth',
                },
            };
            new ApexCharts(document.querySelector('#expense-trend-chart'), expenseTrendOptions).render();

            // SLA On Time Chart
            const slaOnTimeOptions = {
                series: [{{ $slaPerformance['on_time'] ?? 85 }}],
                chart: {
                    type: 'radialBar',
                    height: 120,
                },
                plotOptions: {
                    radialBar: {
                        hollow: {
                            size: '50%',
                        },
                        dataLabels: {
                            name: {
                                show: false,
                            },
                            value: {
                                show: true,
                                fontSize: '18px',
                                fontWeight: 600,
                            },
                        },
                    },
                },
                colors: ['#28A745'],
            };
            new ApexCharts(document.querySelector('#sla-on-time'), slaOnTimeOptions).render();

            // SLA Delayed Chart
            const slaDelayedOptions = {
                series: [{{ $slaPerformance['delayed'] ?? 15 }}],
                chart: {
                    type: 'radialBar',
                    height: 120,
                },
                plotOptions: {
                    radialBar: {
                        hollow: {
                            size: '50%',
                        },
                        dataLabels: {
                            name: {
                                show: false,
                            },
                            value: {
                                show: true,
                                fontSize: '18px',
                                fontWeight: 600,
                            },
                        },
                    },
                },
                colors: ['#DC3545'],
            };
            new ApexCharts(document.querySelector('#sla-delayed'), slaDelayedOptions).render();
        });
    </script>
@endsection
