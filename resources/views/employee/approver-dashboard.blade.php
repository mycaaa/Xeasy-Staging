@extends('layouts.app')

@section('content')
@php
    $metrics      = $dashboardData['top_metrics'];
    $inbox        = $dashboardData['approval_inbox'];
    $tatData      = $dashboardData['stagewise_tat'];
    $statusData   = $dashboardData['claims_by_status'];
    $expOverview  = $dashboardData['expense_overview'];
    $expTrend     = $dashboardData['expense_trend'];
    $odometer     = $dashboardData['odometer_analysis'];
    $alerts       = $dashboardData['compliance_alerts'];
    $teamSummary  = $dashboardData['team_claims_summary'];
    $insights     = $dashboardData['top_insights'];
    $sla          = $dashboardData['sla_performance'];

    $currentMonthLabel = now()->format('M Y');
    $prevMonthLabel    = now()->subMonth()->format("M 'y");

    $approverRoute = session('reporting_vess') == 'Y'
        ? route('vvnr.approver')
        : route('vnr.approver');

    $statusColors = [
        'pending_approval' => '#f59e0b',
        'approved'         => '#22c55e',
        'returned'         => '#ef4444',
        'in_progress'      => '#3b82f6',
        'paid'             => '#8b5cf6',
    ];
    $statusTotal = array_sum(array_values($statusData));

    // Expense overview totals
    $expTotal = array_sum(array_column((array)$expOverview, 'total_amount'));
    $expChartColors = ['#3b82f6','#f97316','#22c55e','#a855f7','#06b6d4','#ef4444','#8b5cf6','#14b8a6'];

    // Trend chart data
    $trendMonthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $trendLabels   = array_map(fn($r) => $trendMonthNames[(int)($r->Month ?? 0)] ?? '', (array)$expTrend);
    $trendAmounts  = array_map(fn($r) => round((float)($r->ytd_expenses ?? 0)), (array)$expTrend);
    $trendCounts   = array_map(fn($r) => (int)($r->no_of_claims ?? 0), (array)$expTrend);

    // Odometer
    $reimbursableKm = max(0, ($odometer['total_kms'] ?? 0) - ($odometer['non_reimbursable_kms'] ?? 0));
    $reimbPct = ($odometer['total_kms'] ?? 0) > 0
        ? round($reimbursableKm / $odometer['total_kms'] * 100)
        : 0;
@endphp

<div class="page-content">
<div class="container-fluid">

{{-- ── Header ──────────────────────────────────────────────── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Reporting Manager Dashboard</h4>
                <p class="text-muted mb-0" style="font-size:13px;">Overview your team's claims, ensure timely approvals and control expenses</p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                {{-- Closed Month Filter Dropdown --}}
                @php
                    $monthFullNames = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    $closedMonths = $dashboardData['closed_months'] ?? [];
                    $selectedM = $dashboardData['selected_month'] ?? null;
                @endphp
                <div class="d-flex align-items-center bg-white border rounded px-2 py-1 shadow-sm">
                    <i class="ri-calendar-event-line text-primary me-2 fs-16"></i>
                    <label for="approverMonthSelect" class="form-label mb-0 fs-12 text-muted me-2 fw-semibold">Month:</label>
                    <select id="approverMonthSelect" class="form-select form-select-sm border-0 fs-12 font-monospace fw-bold text-primary" style="width: auto; min-width: 140px; cursor: pointer;" onchange="window.location.href='{{ url()->current() }}?month=' + this.value;">
                        @if(!empty($closedMonths))
                            @foreach($closedMonths as $m)
                                <option value="{{ $m }}" {{ (int)$selectedM === (int)$m ? 'selected' : '' }}>
                                    {{ $monthFullNames[$m] ?? "Month {$m}" }}
                                </option>
                            @endforeach
                        @else
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ (int)$selectedM === (int)$m ? 'selected' : '' }}>
                                    {{ $monthFullNames[$m] }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <a href="{{ route('approver.index') }}" class="btn btn-sm btn-soft-secondary">
                    <i class="ri-arrow-left-line me-1"></i>Back to Claims
                </a>
                <a href="{{ $approverRoute }}" class="btn btn-sm btn-soft-success">
                    <i class="ri-check-double-line me-1"></i>Approve Claims
                    @if(($metrics['claims_pending']['current'] ?? 0) > 0)
                    <span class="badge bg-success ms-1">{{ $metrics['claims_pending']['current'] }}</span>
                    @endif
                </a>
                <a href="{{ url('/home') }}" class="btn btn-sm btn-primary">
                    <i class="ri-download-line me-1"></i>Download Report
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 1: 6 Stat Cards ──────────────────────────────────── --}}
<div class="row g-2 mb-3">
    @php
    $statCards = [
        ['label'=>'Claims Pending Approval','value'=>$metrics['claims_pending']['current'],'sub'=>'₹'.number_format($metrics['claims_pending']['amount']??0),'icon'=>'ri-hourglass-line','ic'=>'#f97316','ibg'=>'#fff7ed','change'=>$metrics['claims_pending']['change_percent']??0,'change_type'=>'bad'],
        ['label'=>'Claims Approved (MTD)',   'value'=>$metrics['claims_approved_mtd']['current'],'sub'=>'₹'.number_format($metrics['claims_approved_mtd']['amount']??0).' (Prev: ₹'.number_format($metrics['claims_approved_mtd']['previous_amount']??0).')','icon'=>'ri-checkbox-circle-line','ic'=>'#22c55e','ibg'=>'#f0fdf4','change'=>$metrics['claims_approved_mtd']['change_percent']??0,'change_type'=>'good'],
        ['label'=>'Claims Returned',         'value'=>$metrics['claims_returned']['current'],'sub'=>'Prev: '.($metrics['claims_returned']['previous']??0),'icon'=>'ri-arrow-go-back-line','ic'=>'#f43f5e','ibg'=>'#fff1f2','change'=>$metrics['claims_returned']['change_percent']??0,'change_type'=>'bad'],
        ['label'=>'Avg. Approval TAT',       'value'=>($metrics['avg_approval_tat']['current']??0).' Days','sub'=>'vs prev '.($metrics['avg_approval_tat']['previous']??0).'d','icon'=>'ri-timer-flash-line','ic'=>'#3b82f6','ibg'=>'#eff6ff','change'=>$metrics['avg_approval_tat']['change_days']??0,'change_type'=>'bad','unit'=>'d'],
        ['label'=>'Team Expenses (MTD)',      'value'=>'₹'.number_format($metrics['team_expenses_mtd']['current']??0,0),'sub'=>'Prev: ₹'.number_format($metrics['team_expenses_mtd']['previous']??0,0),'icon'=>'ri-wallet-3-line','ic'=>'#a855f7','ibg'=>'#faf5ff','change'=>$metrics['team_expenses_mtd']['change_percent']??0,'change_type'=>'good'],
        ['label'=>'Budget Utilization',       'value'=>($metrics['budget_utilization']['current']??0).'%','sub'=>'Target '.$metrics['budget_utilization']['target'].'%','icon'=>'ri-pie-chart-line','ic'=>'#0ea5e9','ibg'=>'#f0f9ff','change'=>$metrics['budget_utilization']['change_percent']??0,'change_type'=>'good'],
    ];
    @endphp
    @foreach($statCards as $sc)
    <div class="col">
        <div class="card appr-stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
                <div class="appr-stat-icon" style="background:{{ $sc['ibg'] }};">
                    <i class="{{ $sc['icon'] }}" style="color:{{ $sc['ic'] }};font-size:20px;"></i>
                </div>
                <div class="flex-grow-1 min-width-0">
                    <div class="appr-stat-label">{{ $sc['label'] }}</div>
                    <div class="appr-stat-value">{{ $sc['value'] }}</div>
                    <div class="appr-stat-sub d-flex align-items-center gap-1 flex-wrap">
                        @if($sc['sub'])<span class="text-muted" style="font-size:10px;">{{ $sc['sub'] }}</span>@endif
                        @if(($sc['change'] ?? 0) != 0)
                        @php
                            $chv = $sc['change']; $unit = $sc['unit'] ?? '%';
                            $isGood = ($sc['change_type'] === 'good') ? $chv > 0 : $chv < 0;
                            $cls = $isGood ? 'text-success' : 'text-danger';
                            $arr = $chv > 0 ? 'ri-arrow-up-s-fill' : 'ri-arrow-down-s-fill';
                        @endphp
                        <span class="{{ $cls }}" style="font-size:10px;font-weight:600;"><i class="{{ $arr }}"></i>{{ abs($chv) }}{{ $unit }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── ROW 2: Approval Inbox (col-8) | Claims by Status (col-4) ── --}}
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h5 class="card-title mb-0 fs-14">
                    <i class="ri-inbox-line me-1"></i>Approval Inbox
                    <span class="badge bg-warning-subtle text-warning ms-1">{{ count($inbox) }}</span>
                </h5>
                <a href="{{ $approverRoute }}" class="btn btn-sm btn-soft-primary">View All <i class="ri-arrow-right-s-line"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
                    <table class="table table-hover table-sm align-middle mb-0" style="font-size:11px;">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th>Employee</th>
                                <th>Month</th>
                                <th class="text-end">Amount (₹)</th>
                                <th>Submitted</th>
                                <th>Verified On</th>
                                <th>Age</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inbox as $item)
                            @php
                                $submitDate = !empty($item->DateOfSubmit) && \App\Support\ZeroDate::has($item->DateOfSubmit) ? $item->DateOfSubmit : null;
                                $verifyDate = !empty($item->Verified_Date) && \App\Support\ZeroDate::has($item->Verified_Date) ? $item->Verified_Date : null;
                                $ageDays = $verifyDate ? (int)floor((time() - strtotime($verifyDate)) / 86400) : 0;
                                $monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#4f46e5;flex-shrink:0;">
                                            {{ strtoupper(substr($item->employee_name ?? 'E', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-medium" style="font-size:11px;">{{ $item->employee_name ?? '—' }}</div>
                                            <small class="text-muted" style="font-size:9px;">{{ $item->EmpCode ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $monthNames[(int)($item->Month ?? 0)] ?? '—' }}</td>
                                <td class="text-end fw-medium">{{ number_format($item->amount ?? 0) }}</td>
                                <td style="font-size:10px;">{{ $submitDate ? \Carbon\Carbon::parse($submitDate)->format('d-M-Y') : '—' }}</td>
                                <td style="font-size:10px;">{{ $verifyDate ? \Carbon\Carbon::parse($verifyDate)->format('d-M-Y') : '—' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $ageDays > 3 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }}" style="font-size:9px;">{{ $ageDays }}d</span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ $approverRoute }}" class="btn btn-soft-primary btn-xs" style="padding:2px 6px;font-size:10px;" title="View">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">
                                <i class="ri-check-double-line text-success me-1"></i>No pending approvals
                            </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer py-1 text-center">
                <a href="{{ $approverRoute }}" class="text-primary fw-medium" style="font-size:11px;">View All Pending <i class="ri-arrow-right-line"></i></a>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">
                    <i class="ri-pie-chart-line me-1"></i>Claims by Status
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
            </div>
            <div class="card-body py-2 px-3">
                @if($statusTotal > 0)
                <div class="row align-items-center">
                    <div class="col-5">
                        <div style="position:relative;height:150px;">
                            <canvas id="apprClaimsByStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="col-7">
                        @foreach(['pending_approval'=>'Pending','approved'=>'Approved','returned'=>'Returned','in_progress'=>'In Progress','paid'=>'Paid'] as $key=>$lbl)
                        @php $cnt = $statusData[$key] ?? 0; $pct = $statusTotal > 0 ? round($cnt/$statusTotal*100) : 0; @endphp
                        @if($cnt > 0)
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center gap-1">
                                <span style="width:8px;height:8px;border-radius:2px;background:{{ $statusColors[$key] ?? '#94a3b8' }};display:inline-block;flex-shrink:0;"></span>
                                <span style="font-size:10px;">{{ $lbl }}</span>
                            </div>
                            <span style="font-size:10px;font-weight:600;">{{ $cnt }} ({{ $pct }}%)</span>
                        </div>
                        @endif
                        @endforeach
                        <div class="border-top pt-1 mt-1 d-flex justify-content-between">
                            <span style="font-size:10px;font-weight:600;">Total:</span>
                            <span style="font-size:10px;font-weight:700;">{{ $statusTotal }}</span>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-4" style="font-size:12px;">
                    <i class="ri-bar-chart-line d-block mb-1" style="font-size:28px;"></i>No data for {{ $currentMonthLabel }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 3: Stagewise TAT ─────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">
                    <i class="ri-git-branch-line me-1 text-primary"></i>Stagewise TAT Analysis
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
            </div>
            <div class="card-body py-3 px-4">
                @php $stages = $tatData['stages'] ?? []; @endphp
                @if(count($stages) && ($tatData['total_current'] ?? 0) > 0)
                {{-- Icon row --}}
                <div class="d-flex align-items-center justify-content-center mb-3 gap-0 flex-wrap">
                    @foreach($stages as $stage)
                    <div class="text-center" style="min-width:110px;">
                        <div class="appr-tat-circle mx-auto mb-1" style="background:{{ $stage['bg'] }};border:2px solid {{ $stage['color'] }};">
                            <i class="{{ $stage['icon'] }}" style="font-size:22px;color:{{ $stage['color'] }};"></i>
                        </div>
                        <div style="font-size:11px;font-weight:600;color:#374151;">{{ $stage['label'] }}</div>
                    </div>
                    @if(!$loop->last)
                    <div style="color:#cbd5e1;font-size:20px;padding-bottom:20px;">›</div>
                    @endif
                    @endforeach
                </div>
                {{-- Detail cards row --}}
                <div class="d-flex align-items-stretch justify-content-center gap-2 mb-3 flex-wrap">
                    @foreach($stages as $stage)
                    <div class="appr-tat-card">
                        <div style="font-size:10px;color:#94a3b8;margin-bottom:2px;">Avg. TAT</div>
                        <div style="font-size:20px;font-weight:700;color:{{ $stage['color'] }};line-height:1.1;">{{ $stage['current'] }}</div>
                        <div style="font-size:10px;color:#94a3b8;margin-bottom:3px;">Days</div>
                    </div>
                    @endforeach
                </div>
                {{-- Total bar --}}
                <div class="appr-tat-total">
                    <i class="ri-timer-flash-line me-2" style="color:#1e40af;font-size:15px;"></i>
                    <span class="fw-semibold" style="color:#1e40af;font-size:13px;">
                        Total Claim Cycle TAT: <strong style="font-size:16px;">{{ $tatData['total_current'] }} Days</strong>
                    </span>
                </div>
                @else
                <div class="text-center text-muted py-3" style="font-size:12px;">
                    <i class="ri-git-branch-line d-block mb-1" style="font-size:28px;"></i>No TAT data for {{ $currentMonthLabel }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 4: Expense Overview | Expense Trend ─────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">Expense Overview
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
            </div>
            <div class="card-body py-2 px-3">
                @if(count($expOverview) && $expTotal > 0)
                <div class="row align-items-center">
                    <div class="col-5">
                        <div style="position:relative;height:150px;">
                            <canvas id="apprExpOverviewChart"></canvas>
                        </div>
                    </div>
                    <div class="col-7">
                        @foreach($expOverview as $idx => $exp)
                        @php
                            $amt  = (float)($exp->total_amount ?? 0);
                            $pct  = $expTotal > 0 ? round($amt / $expTotal * 100) : 0;
                            $col  = $expChartColors[$idx % count($expChartColors)];
                        @endphp
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center gap-1">
                                <span style="width:8px;height:8px;border-radius:2px;background:{{ $col }};display:inline-block;flex-shrink:0;"></span>
                                <span style="font-size:10px;">{{ $exp->ClaimName ?? 'Other' }}</span>
                            </div>
                            <span style="font-size:10px;font-weight:600;">₹{{ number_format($amt) }} ({{ $pct }}%)</span>
                        </div>
                        @endforeach
                        <div class="border-top pt-1 mt-1 d-flex justify-content-between">
                            <span style="font-size:10px;font-weight:600;">Total:</span>
                            <span style="font-size:10px;font-weight:700;">₹{{ number_format($expTotal) }}</span>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-4" style="font-size:12px;">
                    <i class="ri-pie-chart-line d-block mb-1" style="font-size:28px;"></i>No expense data
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">Expense Trend
                    <span class="text-muted fw-normal" style="font-size:11px;">(Last 6 Months)</span>
                </h5>
            </div>
            <div class="card-body py-2 px-3">
                @if(count($expTrend) && array_sum($trendAmounts) > 0)
                <div id="apprExpTrendChart" style="height:150px;"></div>
                <div class="row text-center mt-2 pt-2 border-top">
                    <div class="col-4">
                        <div style="font-size:10px;font-weight:600;">YTD Expenses</div>
                        <div class="text-muted" style="font-size:10px;">₹{{ number_format(array_sum($trendAmounts)) }}</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:10px;font-weight:600;">YTD Claims</div>
                        <div class="text-muted" style="font-size:10px;">{{ array_sum($trendCounts) }}</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:10px;font-weight:600;">Avg. Amount</div>
                        <div class="text-muted" style="font-size:10px;">₹{{ array_sum($trendCounts) > 0 ? number_format(round(array_sum($trendAmounts)/array_sum($trendCounts))) : '—' }}</div>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-4" style="font-size:12px;">
                    <i class="ri-line-chart-line d-block mb-1" style="font-size:28px;"></i>No trend data
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 5: Team Summary | Compliance Alerts ─────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header py-2 d-flex align-items-center">
                <h5 class="card-title mb-0 fs-14 flex-grow-1">
                    <i class="ri-team-line me-1"></i>Team Claims Summary
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
                <a href="{{ $approverRoute }}" class="text-primary" style="font-size:11px;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:11px;">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th class="text-center">Submitted</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center">Returned</th>
                                <th class="text-center">Paid</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamSummary as $ts)
                            <tr>
                                <td class="fw-medium">{{ $ts->employee_name ?? '—' }}</td>
                                <td class="text-center">{{ $ts->submitted ?? 0 ?: '—' }}</td>
                                <td class="text-center">
                                    @if(($ts->pending_approval ?? 0) > 0)
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:9px;">{{ $ts->pending_approval }}</span>
                                    @else—@endif
                                </td>
                                <td class="text-center">
                                    @if(($ts->approved ?? 0) > 0)
                                    <span class="badge bg-success-subtle text-success" style="font-size:9px;">{{ $ts->approved }}</span>
                                    @else—@endif
                                </td>
                                <td class="text-center">
                                    @if(($ts->returned ?? 0) > 0)
                                    <span class="badge bg-danger-subtle text-danger" style="font-size:9px;">{{ $ts->returned }}</span>
                                    @else—@endif
                                </td>
                                <td class="text-center">
                                    @if(($ts->paid ?? 0) > 0)
                                    <span class="badge bg-info-subtle text-info" style="font-size:9px;">{{ $ts->paid }}</span>
                                    @else—@endif
                                </td>
                                <td class="text-end fw-medium">₹{{ number_format($ts->amount_fy ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No team data for {{ $currentMonthLabel }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header py-2 d-flex align-items-center">
                <h5 class="card-title mb-0 fs-14 flex-grow-1">
                    <i class="ri-alarm-warning-line me-1 text-warning"></i>Compliance Alerts
                    @if(count($alerts) > 0)
                    <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:9px;">{{ count($alerts) }}</span>
                    @endif
                </h5>
            </div>
            <div class="card-body py-2 px-3">
                @forelse($alerts as $alert)
                @php $aIcons = ['danger'=>'ri-error-warning-line','warning'=>'ri-alert-line','info'=>'ri-information-line','success'=>'ri-checkbox-circle-line']; @endphp
                <div class="d-flex align-items-start mb-2">
                    <i class="{{ $aIcons[$alert['type']] ?? 'ri-information-line' }} text-{{ $alert['type'] }} me-2 mt-1 flex-shrink-0" style="font-size:13px;"></i>
                    <span style="font-size:11px;color:#374151;line-height:1.4;">{{ $alert['message'] ?? '' }}</span>
                </div>
                @empty
                <div class="text-center text-muted py-3" style="font-size:11px;">
                    <i class="ri-checkbox-circle-line text-success d-block mb-1" style="font-size:24px;"></i>No compliance issues
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 6: Top Insights | SLA Performance ───────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">
                    <i class="ri-lightbulb-flash-line me-1 text-warning"></i>Top Insights
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
            </div>
            <div class="card-body py-2 px-3">
                @forelse($insights as $idx => $insight)
                @php $iIcons = ['info'=>'ri-information-line','warning'=>'ri-alert-line','success'=>'ri-checkbox-circle-line','danger'=>'ri-error-warning-line']; @endphp
                <div class="d-flex align-items-start mb-2">
                    <span class="badge bg-primary-subtle text-primary me-2 flex-shrink-0" style="font-size:8px;padding:3px 6px;margin-top:2px;">{{ $idx + 1 }}</span>
                    <p class="text-muted mb-0" style="font-size:11px;line-height:1.4;">{{ $insight['message'] ?? '' }}</p>
                </div>
                @empty
                <p class="text-muted mb-0" style="font-size:11px;">No insights available.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">
                    <i class="ri-speed-up-line me-1 text-primary"></i>SLA Performance
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
            </div>
            <div class="card-body py-3 px-4">
                @php
                    $slaMet      = $sla['sla_met'] ?? 0;
                    $slaBreached = $sla['sla_breached'] ?? 0;
                    $slaPct      = $sla['sla_percentage'] ?? 0;
                    $slaTarget   = $sla['sla_target'] ?? 90;
                    $slaTotal    = $slaMet + $slaBreached;
                    $slaMetPct   = $slaTotal > 0 ? round($slaMet/$slaTotal*100) : 0;
                    $slaBreachPct = $slaTotal > 0 ? round($slaBreached/$slaTotal*100) : 0;
                @endphp
                <div class="d-flex align-items-center gap-4">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:11px;">SLA Met</span>
                            <span style="font-size:11px;font-weight:700;color:#22c55e;">{{ $slaMetPct }}%</span>
                        </div>
                        <div class="progress mb-3" style="height:8px;">
                            <div class="progress-bar bg-success" style="width:{{ $slaMetPct }}%;"></div>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:11px;">SLA Breached</span>
                            <span style="font-size:11px;font-weight:700;color:#ef4444;">{{ $slaBreachPct }}%</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-danger" style="width:{{ $slaBreachPct }}%;"></div>
                        </div>
                    </div>
                    <div class="text-center" style="min-width:80px;">
                        <div style="font-size:10px;color:#94a3b8;">Target</div>
                        <div style="font-size:28px;font-weight:700;color:#3b82f6;">{{ $slaTarget }}%</div>
                        <div class="{{ $slaMetPct >= $slaTarget ? 'text-success' : 'text-danger' }}" style="font-size:10px;font-weight:600;">
                            {{ $slaMetPct >= $slaTarget ? '✓ On Track' : '✗ Below Target' }}
                        </div>
                    </div>
                </div>
                <div class="row text-center mt-3 pt-2 border-top">
                    <div class="col-4">
                        <div style="font-size:18px;font-weight:700;color:#22c55e;">{{ $slaMet }}</div>
                        <div style="font-size:10px;color:#6b7280;">Met</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:18px;font-weight:700;color:#ef4444;">{{ $slaBreached }}</div>
                        <div style="font-size:10px;color:#6b7280;">Breached</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:18px;font-weight:700;color:#3b82f6;">{{ $slaTotal }}</div>
                        <div style="font-size:10px;color:#6b7280;">Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 7: Odometer Analysis ─────────────────────────────── --}}
@if(($odometer['total_kms'] ?? 0) > 0)
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="card-title mb-0 fs-14">
                    <i class="ri-speed-line me-1 text-primary"></i>Odometer Analysis
                    <span class="text-muted fw-normal" style="font-size:11px;">({{ $currentMonthLabel }})</span>
                </h5>
            </div>
            <div class="card-body py-2 px-4">
                <div class="row text-center g-3">
                    <div class="col-md-3">
                        <div style="font-size:10px;color:#94a3b8;">Total KMs Driven</div>
                        <div style="font-size:22px;font-weight:700;color:#1e293b;">{{ number_format($odometer['total_kms']) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">KMs</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:10px;color:#94a3b8;">Reimbursable KMs</div>
                        <div style="font-size:22px;font-weight:700;color:#22c55e;">{{ number_format($reimbursableKm) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">({{ $reimbPct }}%)</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:10px;color:#94a3b8;">Opening Reading</div>
                        <div style="font-size:22px;font-weight:700;color:#1e293b;">{{ number_format($odometer['opening_reading']) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">KMs</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:10px;color:#94a3b8;">Closing Reading</div>
                        <div style="font-size:22px;font-weight:700;color:#1e293b;">{{ number_format($odometer['closing_reading']) }}</div>
                        <div style="font-size:10px;color:#94a3b8;">KMs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

</div>{{-- /container-fluid --}}
</div>{{-- /page-content --}}

<style>
/* ── Stat Cards ──────────────────────────────────────────────── */
.appr-stat-card { border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:box-shadow .2s; }
.appr-stat-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
.appr-stat-card .card-body { padding:12px 14px !important; }
.appr-stat-icon { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.appr-stat-label { font-size:10px;color:#6b7280;font-weight:500;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.appr-stat-value { font-size:20px;font-weight:700;color:#111827;line-height:1.2;margin-bottom:2px; }
.appr-stat-sub { min-height:14px; }

/* ── TAT ─────────────────────────────────────────────────────── */
.appr-tat-circle { width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center; }
.appr-tat-card { flex:1;min-width:120px;max-width:200px;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;background:#fff; }
.appr-tat-total { background:linear-gradient(90deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:8px;padding:10px 20px;display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:6px; }

/* ── Cards general ───────────────────────────────────────────── */
.fs-14 { font-size:14px !important; }
.card-header { padding:8px 14px !important;background:#fafbfc;border-bottom:1px solid #f0f0f0; }
.card-footer { padding:5px 14px !important;background:#fafbfc;border-top:1px solid #f0f0f0; }
.card { border-radius:10px;border:1px solid #e8eaed;box-shadow:0 1px 3px rgba(0,0,0,.05); }
.progress { border-radius:4px;background:#f3f4f6; }
.avatar-xs { width:32px;height:32px; }
.avatar-title { width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-weight:600; }
.bg-soft-primary { background:#cfe2ff; }
.bg-soft-info { background:#cfe2ff; }
.min-width-0 { min-width:0; }
</style>

@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/chart.js@4.4.0/dist/chart.umd.js') }}"></script>
@php
    $jsStatusLabels = array_map(fn($k) => ['pending_approval'=>'Pending','approved'=>'Approved','returned'=>'Returned','in_progress'=>'In Progress','paid'=>'Paid'][$k]??$k, array_keys(array_filter($statusData, fn($v)=>$v>0)));
    $jsStatusSeries = array_values(array_filter($statusData, fn($v)=>$v>0));
    $jsStatusColors = array_values(array_filter(array_map(fn($k)=>$statusColors[$k]??null, array_keys($statusData)), fn($v,$k)=>($statusData[array_keys($statusData)[$k]]??0)>0, ARRAY_FILTER_USE_BOTH));
    $jsExpLabels = array_map(fn($r)=>$r->ClaimName??'Other', (array)$expOverview);
    $jsExpSeries = array_map(fn($r)=>round((float)($r->total_amount??0)), (array)$expOverview);
@endphp
<script>
(function(){
    'use strict';

    // Claims by Status donut
    @if($statusTotal > 0)
    (function(){
        var ctx = document.getElementById('apprClaimsByStatusChart');
        if (!ctx || typeof Chart === 'undefined') return;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($jsStatusLabels) !!},
                datasets: [{ data: {!! json_encode($jsStatusSeries) !!}, backgroundColor: {!! json_encode(array_values($jsStatusColors)) !!}, borderWidth: 2, borderColor: '#fff' }]
            },
            options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label:function(c){ var t=c.dataset.data.reduce(function(a,b){return a+b;},0); return ' '+c.label+': '+c.parsed+' ('+(t>0?Math.round(c.parsed/t*100):0)+'%)'; } } } } }
        });
    })();
    @endif

    // Expense Overview donut
    @if(count($expOverview) && $expTotal > 0)
    (function(){
        var ctx = document.getElementById('apprExpOverviewChart');
        if (!ctx || typeof Chart === 'undefined') return;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($jsExpLabels) !!},
                datasets: [{ data: {!! json_encode($jsExpSeries) !!}, backgroundColor: {!! json_encode($expChartColors) !!}, borderWidth: 2, borderColor: '#fff' }]
            },
            options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label:function(c){ return ' '+c.label+': ₹'+c.parsed.toLocaleString('en-IN'); } } } } }
        });
    })();
    @endif

    // Expense Trend (ApexCharts)
    @if(count($expTrend) && array_sum($trendAmounts) > 0)
    (function(){
        var el = document.getElementById('apprExpTrendChart');
        if (!el || typeof ApexCharts === 'undefined') return;
        new ApexCharts(el, {
            series: [
                { name:'Amount (₹)', type:'column', data: {!! json_encode($trendAmounts) !!} },
                { name:'No. of Claims', type:'line',  data: {!! json_encode($trendCounts) !!} }
            ],
            chart: { height:150, type:'line', toolbar:{show:false}, fontFamily:'inherit' },
            stroke: { width:[0,2], curve:'smooth' },
            plotOptions: { bar:{ borderRadius:3, columnWidth:'55%' } },
            colors: ['#3577f1','#f06548'],
            xaxis: { categories: {!! json_encode($trendLabels) !!}, labels:{ style:{fontSize:'9px'} } },
            yaxis: [
                { labels:{ formatter:function(v){ return '₹'+(v/1000).toFixed(0)+'K'; }, style:{fontSize:'9px'} } },
                { opposite:true, labels:{ formatter:function(v){ return v; }, style:{fontSize:'9px'} } }
            ],
            dataLabels: { enabled:false },
            legend: { show:false },
            grid: { padding:{top:0,bottom:0} }
        }).render();
    })();
    @endif
})();
</script>
@endpush
