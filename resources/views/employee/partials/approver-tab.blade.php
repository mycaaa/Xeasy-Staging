{{-- Reporting Manager Dashboard - Approver Tab (Blade) --}}
@php
    $rm = $rmDashData ?? [];
    $rmMonthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $rmDisplayMonth = $rm['display_month'] ?? ($displayMonth ?? (int)now()->month);
    $rmMonthLabel   = $rmMonthNames[$rmDisplayMonth] ?? '';
    $rmPrevMonthNum = $rmDisplayMonth > 1 ? $rmDisplayMonth - 1 : 12;
    $rmPrevMonth    = $rmMonthNames[$rmPrevMonthNum] ?? '';
    $rmYear         = date('Y');
    $rmPrevYear     = $rmPrevMonthNum > $rmDisplayMonth ? date('Y', strtotime('-1 year')) : $rmYear;
    $rmExpColors    = ['#3b82f6','#f97316','#22c55e','#a855f7','#06b6d4','#ef4444','#8b5cf6','#14b8a6'];

    // Check approver access per company
    $canAccessVnr     = true;
    $canAccessVvnr    = (session('reporting_vess') == 'Y' || session('company_id') == 11);
    $canAccessNursery = (session('reporting_nursery') == 'Y' || session('company_id') == 3);

    // Resolve current company default from session
    $approverPrefix = (session('company_id') == 11) ? 'vvnr' : ((session('company_id') == 3) ? 'nursery' : 'vnr');

    $companyApproverRoutes = [
        'vnr' => [
            'key'         => 'vnr',
            'name'        => 'VNR',
            'url'         => \Illuminate\Support\Facades\Route::has('vnr.approver') ? route('vnr.approver') : url('vnr/approver'),
            'pendingList' => \Illuminate\Support\Facades\Route::has('vnr.approver.pending.list') ? route('vnr.approver.pending.list') : url('vnr/approver/pending/list'),
            'pendingDay'  => \Illuminate\Support\Facades\Route::has('vnr.approver.pending.day') ? route('vnr.approver.pending.day') : url('vnr/approver/pending/day'),
            'approveClaim'=> \Illuminate\Support\Facades\Route::has('vnr.approver.claim.approve') ? route('vnr.approver.claim.approve') : url('vnr/approver/claim/approve'),
            'submitClaim' => \Illuminate\Support\Facades\Route::has('vnr.approver.claim.submit') ? route('vnr.approver.claim.submit') : url('vnr/approver/claim/submit'),
        ],
    ];

    if ($canAccessVvnr) {
        $companyApproverRoutes['vvnr'] = [
            'key'         => 'vvnr',
            'name'        => 'VVNR',
            'url'         => \Illuminate\Support\Facades\Route::has('vvnr.approver') ? route('vvnr.approver') : url('vvnr/approver'),
            'pendingList' => \Illuminate\Support\Facades\Route::has('vvnr.approver.pending.list') ? route('vvnr.approver.pending.list') : url('vvnr/approver/pending/list'),
            'pendingDay'  => \Illuminate\Support\Facades\Route::has('vvnr.approver.pending.day') ? route('vvnr.approver.pending.day') : url('vvnr/approver/pending/day'),
            'approveClaim'=> \Illuminate\Support\Facades\Route::has('vvnr.approver.claim.approve') ? route('vvnr.approver.claim.approve') : url('vvnr/approver/claim/approve'),
            'submitClaim' => \Illuminate\Support\Facades\Route::has('vvnr.approver.claim.submit') ? route('vvnr.approver.claim.submit') : url('vvnr/approver/claim/submit'),
        ];
    }

    if ($canAccessNursery) {
        $companyApproverRoutes['nursery'] = [
            'key'         => 'nursery',
            'name'        => 'NURSERY',
            'url'         => \Illuminate\Support\Facades\Route::has('nursery.approver') ? route('nursery.approver') : url('nursery/approver'),
            'pendingList' => \Illuminate\Support\Facades\Route::has('nursery.approver.pending.list') ? route('nursery.approver.pending.list') : url('nursery/approver/pending/list'),
            'pendingDay'  => \Illuminate\Support\Facades\Route::has('nursery.approver.pending.day') ? route('nursery.approver.pending.day') : url('nursery/approver/pending/day'),
            'approveClaim'=> \Illuminate\Support\Facades\Route::has('nursery.approver.claim.approve') ? route('nursery.approver.claim.approve') : url('nursery/approver/claim/approve'),
            'submitClaim' => \Illuminate\Support\Facades\Route::has('nursery.approver.claim.submit') ? route('nursery.approver.claim.submit') : url('nursery/approver/claim/submit'),
        ];
    }

    $currentCompany   = isset($companyApproverRoutes[$approverPrefix]) ? $approverPrefix : array_key_first($companyApproverRoutes);
    $rmApproverRoute  = $companyApproverRoutes[$currentCompany]['url'] ?? route('vnr.approver');
    $rmApproverRoutes = $companyApproverRoutes[$currentCompany];

    // TAT - controller returns 'team_tat' and 'team_tat_prev'
    $rmTat     = $rm['team_tat']      ?? null;
    $rmTatPrev = $rm['team_tat_prev'] ?? null;

    $rmTatTotal = 0;
    $rmTatHasData = false;
    if ($rmTat) {
        $rmTatTotal = round(array_sum([
            (float)($rmTat->submitted ?? 0),
            (float)($rmTat->reporting_manager ?? 0),
            (float)($rmTat->finance_verification ?? 0),
            (float)($rmTat->finance_approval ?? 0),
            (float)($rmTat->payment ?? 0),
        ]), 1);
        // Show card if at least one stage has data (payment may not be done yet)
        $rmTatHasData = ((float)($rmTat->submitted ?? 0) > 0)
            || ((float)($rmTat->finance_verification ?? 0) > 0)
            || ((float)($rmTat->reporting_manager ?? 0) > 0)
            || ((float)($rmTat->finance_approval ?? 0) > 0)
            || ((float)($rmTat->payment ?? 0) > 0);
    }

    $rmTatPrevTotal = 0;
    if ($rmTatPrev) {
        $rmTatPrevTotal = round(array_sum([
            (float)($rmTatPrev->submitted ?? 0),
            (float)($rmTatPrev->reporting_manager ?? 0),
            (float)($rmTatPrev->finance_verification ?? 0),
            (float)($rmTatPrev->finance_approval ?? 0),
            (float)($rmTatPrev->payment ?? 0),
        ]), 1);
    }
    $rmTatTotalDiff = round($rmTatTotal - $rmTatPrevTotal, 1);

    // TAT stage definitions
    $tatStages = [
        ['label' => 'Submitted',           'key' => 'submitted',           'icon' => 'ri-upload-cloud-2-line', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
        ['label' => 'Verification',        'key' => 'finance_verification', 'icon' => 'ri-shield-check-line',   'color' => '#f97316', 'bg' => '#fff7ed'],
        ['label' => 'Manager approval',    'key' => 'reporting_manager',    'icon' => 'ri-user-star-line',      'color' => '#a855f7', 'bg' => '#faf5ff'],
        ['label' => 'Finance Approval',    'key' => 'finance_approval',     'icon' => 'ri-thumb-up-line',       'color' => '#22c55e', 'bg' => '#f0fdf4'],
        ['label' => 'Payment',             'key' => 'payment',              'icon' => 'ri-bank-card-line',      'color' => '#06b6d4', 'bg' => '#ecfeff'],
    ];

    // Status counts
    $sCounts = $rm['status_counts'] ?? [];
    $statusMap = [
        'Pending Approval' => (int)($sCounts['Verified']   ?? 0),
        'Approved'         => (int)($sCounts['Approved']   ?? 0),
        'Returned'         => (int)($sCounts['Return']     ?? $sCounts['Returned'] ?? 0),
        'In Progress'      => (int)(($sCounts['Filled'] ?? 0) + ($sCounts['Submitted'] ?? 0)),
        'Paid'             => (int)($sCounts['Financed']   ?? 0),
    ];
    $sColorMap = [
        'Pending Approval' => '#f59e0b',
        'Approved'         => '#22c55e',
        'Returned'         => '#ef4444',
        'In Progress'      => '#3b82f6',
        'Paid'             => '#8b5cf6',
    ];
    $sTotal = array_sum($statusMap);

    // Expense data
    $rmExpItems  = $rm['expense_by_type'] ?? collect();
    $rmClaimNames = $rm['claim_names'] ?? [];
    $rmExpTotal  = $rmExpItems->sum('total');

    // Trend data
    $trendAmounts = $rm['trend_amounts'] ?? [];
    $trendCounts  = $rm['trend_counts']  ?? [];
    $trendLabels  = $rm['trend_labels']  ?? [];
    $ytdExpenses  = array_sum($trendAmounts);
    $ytdClaims    = array_sum($trendCounts);
    $avgClaim     = $ytdClaims > 0 ? round($ytdExpenses / $ytdClaims) : 0;

    // Team summary & employee names
    $teamSummary = $rm['team_summary'] ?? collect();
    $empNames    = $rm['emp_names']    ?? [];
    $teamSize    = $rm['team_size']    ?? 0;

    // Stat card real values
    $pendingCount  = $rm['pending_count']  ?? 0;
    $pendingAmount = $rm['pending_amount'] ?? 0;
    $approvedCount = $rm['approved_count'] ?? 0;
    $approvedAmount = $rm['approved_amount'] ?? 0;
    $returnedCount = $rm['returned_count'] ?? 0;
    $teamExpenses  = $rm['team_expenses']  ?? 0;
    // TEAM-BASIS-01 (14 Nov 2026): what each total counts, shown under the cards and in Top Insights
    $teamExpClaims   = (int) ($rm['team_expenses_claims'] ?? 0);
    $approvedBasis   = $rm['approved_basis'] ?? 'months';
    $approvedClaimed = (float) ($rm['approved_claimed'] ?? 0);
    $overCnt         = (int) ($rm['over_claimed_count'] ?? 0);
    $overClaimed     = (float) ($rm['over_claimed_claimed'] ?? 0);
    $overEntered     = (float) ($rm['over_claimed_entered'] ?? 0);
    $totalClaims   = $rm['total_claims']   ?? 0;

    // Prev month values for comparison
    $approvedCountPrev  = $rm['approved_count_prev']  ?? 0;
    $approvedAmountPrev = $rm['approved_amount_prev'] ?? 0;
    $returnedCountPrev  = $rm['returned_count_prev']  ?? 0;

    // Helper: always return a comparison, showing 0 when no data
    $rmStatChange = function($current, $previous, $unit = '%') use ($rmPrevMonth) {
        $current  = (float) $current;
        $previous = (float) $previous;
        if ($previous > 0) {
            $pct = round((($current - $previous) / $previous) * 100, 1);
            $val = ($unit === '%')
                ? ($pct >= 0 ? '+' : '') . $pct . '%'
                : ($pct >= 0 ? '+' : '') . $pct . '%';
        } else {
            $pct = 0;
            $val = '0%';
        }
        return [
            'val'  => $val,
            'type' => $pct <= 0 ? 'down' : 'up',
            'vs'   => 'vs ' . $rmPrevMonth,
            'prev' => $previous,
        ];
    };

    $approvedChange = $rmStatChange($approvedCount, $approvedCountPrev);
    $returnedChange = $rmStatChange($returnedCount, $returnedCountPrev);

    // TAT-CARD-01 (14 Nov 2026): the "Avg. Approval TAT" card showed the whole claim cycle (12 days, the same as
    // "Total Claim Cycle TAT" below) while the Manager approval stage said 0. The card now shows the manager's own
    // stage - verified to approved - and the change against last month on the same stage.
    $rmApprTat     = round((float) ($rmTat->reporting_manager ?? 0), 1);
    $rmApprTatPrev = round((float) ($rmTatPrev->reporting_manager ?? 0), 1);
    $tatDiffVal  = round($rmApprTat - $rmApprTatPrev, 1);
    $tatChange   = [
        'val'  => ($tatDiffVal <= 0 ? '▼ ' : '▲ ') . number_format(abs($tatDiffVal), 1) . ' Days',
        'type' => $tatDiffVal <= 0 ? 'down' : 'up',
        'vs'   => 'vs ' . $rmPrevMonth,
        'prev' => $rmApprTatPrev,
    ];

    // Stat cards - 3 primary redirectable cards
    $statCards = [
        ['label' => 'Claims Pending Approval', 'value' => $pendingCount,
         // QA-20.2: split ready for you vs still with the verification team
         'sub'   => (isset($rm['pending_ready']) ? ((int) $rm['pending_ready']) . ' ready for you · ' . ((int) ($rm['pending_with_verification'] ?? 0)) . ' with verification · ' : '') . '₹ ' . number_format($pendingAmount),
         'icon'  => 'ri-time-line', 'color' => 'warning', 'change' => null,
         'link'  => $rmApproverRoute . '?status=pending'],

        ['label' => 'Avg. Approval TAT',        'value' => number_format($rmApprTat, 1) . ' Days',
         'sub'   => 'Verified to your approval · full cycle ' . number_format($rmTatTotal, 1) . ' days',
         'icon'  => 'ri-git-branch-line', 'color' => 'primary', 'change' => $tatChange,
         'link'  => $rmApproverRoute],

        ['label' => 'Team Expenses (YTD)',       'value' => '₹ ' . number_format($teamExpenses),
         'sub'   => 'Claimed · ' . number_format($teamExpClaims) . ' bills this FY · ' . $teamSize . ' team members',
         'icon'  => 'ri-money-dollar-circle-line', 'color' => 'info', 'change' => null,
         'link'  => $rmApproverRoute],
    ];

    // Dynamic insights
    $insights = [];
    if ($rmTatTotal > 0) {
        $insights[] = "{$rmMonthLabel} {$rmYear}: your approval took {$rmApprTat} days on average after verification; the full claim cycle took {$rmTatTotal} days.";
    }
    if ($pendingCount > 0) {
        $insights[] = "{$pendingCount} " . ($pendingCount === 1 ? 'claim is' : 'claims are') . " pending your approval (₹ " . number_format($pendingAmount) . ").";
    }
    if ($approvedCount > 0) {
        $insights[] = $approvedBasis === 'months'
            ? "{$rmMonthLabel} claims of {$approvedCount} " . ($approvedCount === 1 ? 'employee' : 'employees') . " approved and submitted: ₹ " . number_format($approvedAmount) . " approved against ₹ " . number_format($approvedClaimed) . " claimed."
            : "{$rmMonthLabel}: {$approvedCount} " . ($approvedCount === 1 ? 'bill' : 'bills') . " approved so far (month not submitted yet): ₹ " . number_format($approvedAmount) . " approved against ₹ " . number_format($approvedClaimed) . " claimed.";
    }
    if ($returnedCount > 0) {
        $insights[] = "{$returnedCount} " . ($returnedCount === 1 ? 'claim was' : 'claims were') . " returned this month.";
    }
    if ($teamSize > 0) {
        $insights[] = "Your team has {$teamSize} active " . ($teamSize === 1 ? 'member' : 'members') . ". Claimed this financial year: ₹ " . number_format($teamExpenses) . " on " . number_format($teamExpClaims) . " bills.";
    }
    if (empty($insights)) {
        $insights[] = "No data available for {$rmMonthLabel} {$rmYear}.";
    }

    // Compliance alerts
    $complianceAlerts = [];
    if ($pendingCount > 5) {
        $complianceAlerts[] = ['type' => 'warning', 'msg' => "{$pendingCount} claims pending approval — action required."];
    }
    if ($returnedCount > 0) {
        $complianceAlerts[] = ['type' => 'info', 'msg' => "{$returnedCount} claim(s) returned this month — follow up with employees."];
    }
    if ($overCnt > 0) {
        $complianceAlerts[] = ['type' => 'danger', 'msg' => "{$overCnt} " . ($overCnt === 1 ? 'bill' : 'bills') . " of {$rmMonthLabel} carry an approved or verified amount above the claimed amount (₹ " . number_format($overEntered) . " entered against ₹ " . number_format($overClaimed) . " claimed). Correct them before you submit."];
    }
    if ($rmTatTotal > 7) {
        $complianceAlerts[] = ['type' => 'danger', 'msg' => "The full claim cycle took {$rmTatTotal} days on average (above 7 days)."];
    }
    foreach ($teamSummary as $ts) {
        $empPending = $ts->pending_approval ?? 0;
        if ($empPending >= 3) {
            $name = $empNames[$ts->CrBy] ?? ('Employee #' . $ts->CrBy);
            $complianceAlerts[] = ['type' => 'warning', 'msg' => "{$name} has {$empPending} claims pending approval."];
        }
    }
    if (empty($complianceAlerts)) {
        $complianceAlerts[] = ['type' => 'success', 'msg' => 'No compliance issues found for this period.'];
    }

    // Chart data
    $rmStatusLabels = array_keys(array_filter($statusMap, fn($v) => $v > 0));
    $rmStatusSeries = array_values(array_filter($statusMap, fn($v) => $v > 0));
    $rmStatusColors = array_values(array_filter(
        array_map(fn($k) => $sColorMap[$k] ?? null, array_keys($statusMap)),
        fn($v, $k) => ($statusMap[array_keys($statusMap)[$k]] ?? 0) > 0,
        ARRAY_FILTER_USE_BOTH
    ));
    $rmExpLabels = $rmExpItems->map(fn($e) => $rmClaimNames[$e->ClaimId] ?? 'Other')->values()->toArray();
    $rmExpSeries = $rmExpItems->pluck('total')->map(fn($v) => round((float)$v))->values()->toArray();
@endphp

<style>
.section-bar{padding:10px 16px;border-radius:8px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:space-between;}
.section-bar.pending-bar{background:linear-gradient(135deg,rgba(var(--vz-primary-rgb,64,81,137),.1),rgba(var(--vz-primary-rgb,64,81,137),.05));color:var(--vz-primary,#405189);}
.claim-card{border:none;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:12px;position:relative;transition:all .2s;background:#fff;}
.claim-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.1);transform:translateY(-1px);}
.claim-card .stripe{position:absolute;left:0;top:0;bottom:0;width:5px;}
.claim-card .stripe.primary{background:#dde6ff; color: #1e3a8a;}
.claim-card .stripe.success{background:#d7f3e6; color: #065f46;}
.claim-card .card-body{padding:14px 14px 14px 20px;}
.claim-card .emp-name{font-weight:700;font-size:14px;color:var(--vz-primary,#405189);}
.claim-card .meta{font-size:11.5px;color:#666;margin-top:2px;}
.claim-card .amount{font-weight:700;color:var(--vz-primary,#405189);font-size:15px;}
.claim-card .amount.success{color:var(--vz-success,#0ab39c);}
.pill-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;text-decoration:none;cursor:pointer;transition:all .15s;border:none;}
.pill-btn.att{background:rgba(var(--vz-primary-rgb,64,81,137),.1);color:var(--vz-primary,#405189);}
.pill-btn.att:hover{background:var(--vz-primary,#405189);color:#fff;}
.pill-btn.view{background:rgba(var(--vz-info-rgb,41,156,219),.1);color:var(--vz-info,#299cdb);}
.pill-btn.view:hover{background:var(--vz-info,#299cdb);color:#fff;}
.pill-btn.submit{background:rgba(var(--vz-success-rgb,10,179,156),.1);color:var(--vz-success,#0ab39c);}
.pill-btn.submit:hover{background:var(--vz-success,#0ab39c);color:#fff;}
.pill-btn.disabled-submit-btn{background:#e9ecef;color:#6c757d;cursor:not-allowed;}
.mini-stepper{display:flex;align-items:flex-start;padding:10px 0 4px;border-top:1px solid #f0f0f0;margin-top:10px;}
.mini-step{flex:1;text-align:center;position:relative;}
.mini-step:not(:last-child)::after{content:'';position:absolute;top:14px;left:55%;width:90%;height:2px;background:#e0e0e0;z-index:0;}
.mini-step.done:not(:last-child)::after{background:var(--vz-primary,#405189);}
.step-dot{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;position:relative;z-index:1;}
.step-dot.done{background:var(--vz-primary,#405189);color:#fff;}
.step-dot.wait{background:#f5f5f5;color:#aaa;border:2px solid var(--vz-primary,#405189);}
.mini-step .lbl{font-size:10px;font-weight:600;color:#555;margin-top:3px;}
.mini-step .val{font-size:10px;color:var(--vz-primary,#405189);font-weight:700;}
.mini-step .val-date{font-size:9px;color:#6b7280;font-weight:400;margin-top:1px;}
.empty-state{text-align:center;padding:30px 15px;color:#aaa;}
.empty-state i{font-size:40px;display:block;margin-bottom:8px;}
.empty-state p{font-size:13px;margin:0;}
.day-tbl th,.day-tbl td{font-size:12px;vertical-align:middle;white-space:nowrap;}
.day-tbl td.remark-col{white-space:normal !important;word-break:break-word;line-height:1.4;}
.day-tbl .amt-input{width:85px;}
.btn-pax-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;color:var(--vz-primary,#405189);background:rgba(var(--vz-primary-rgb,64,81,137),.08);border:1px solid rgba(var(--vz-primary-rgb,64,81,137),.2);cursor:pointer;transition:all .15s;line-height:1.3;}
.btn-pax-pill:hover{background:var(--vz-primary,#405189);color:#fff;border-color:var(--vz-primary,#405189);}
.pax-detail-row td{white-space:normal !important;}
.pax-sub-table th, .pax-sub-table td{padding:5px 8px;font-size:11.5px;vertical-align:middle;}
.rotating{animation:spin 1s linear infinite;display:inline-block;}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
.summary-stat-card{transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;}
.summary-stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08) !important;border-color:#cbd5e1 !important;}
.app-tab-bar{background:var(--vz-light,#f3f6f9);border-radius:25px;padding:3px;display:inline-flex;}
.app-tab{padding:6px 20px;border-radius:22px;font-size:13px;font-weight:600;color:#555;text-decoration:none;transition:all .2s;border:none;background:transparent;cursor:pointer;}
.app-tab.active{background:var(--vz-primary,#405189);color:#fff;box-shadow:0 2px 8px rgba(var(--vz-primary-rgb,64,81,137),.35);}
.app-tab:hover:not(.active){background:rgba(var(--vz-primary-rgb,64,81,137),.08);color:var(--vz-primary,#405189);}
</style>

{{-- ── Top Company Access Tabs ──────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted fw-semibold fs-12 text-uppercase me-1" style="letter-spacing:0.4px;">
            <i class="ri-building-line me-1"></i>Company:
        </span>
        <div class="app-tab-bar d-flex" role="tablist" id="approverCompanyTabs">
            @foreach($companyApproverRoutes as $cKey => $cData)
            <button type="button" 
                    class="app-tab approver-company-tab {{ $currentCompany === $cKey ? 'active' : '' }}" 
                    data-company="{{ $cKey }}" 
                    title="Switch to {{ $cData['name'] }} Approver">
                {{ $cData['name'] }}
            </button>
            @endforeach
        </div>
    </div>
    <div>
        <span class="badge bg-light text-muted border px-2 py-1" style="font-size:11px;" id="approverActiveCoBadge">
            Active: <strong class="text-primary" id="approverActiveCoName">{{ $companyApproverRoutes[$currentCompany]['name'] ?? 'VNR' }}</strong>
        </span>
    </div>
</div>

{{-- ── Welcome Section with Action Buttons on Right ──────────────── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="mb-0 fw-bold" style="color:#0b1a33;">Welcome back, {{ session('employee_name', 'Manager') }} 👋</h4>
                <p class="text-muted mb-0" style="font-size:13px;">Reporting Manager Dashboard · Oversee your team's claims, ensure timely approvals and control expenses</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ $companyApproverRoutes[$currentCompany]['url'] ?? route('vnr.approver') }}" id="btnApproveClaims" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:25px;border:2px solid var(--vz-primary,#405189);background:#fff;color:var(--vz-primary,#405189);font-size:12px;font-weight:600;text-decoration:none;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,.05);">
                    <i class="ri-check-double-line"></i>Approve Claims
                    <span class="badge bg-primary ms-1" id="btnApproveClaimsBadge" style="{{ $pendingCount > 0 ? '' : 'display:none;' }}">{{ $pendingCount }}</span>
                </a>
                @if(session('advance_module') == 'Y' && session('adv_approver') == 'Y')
                <a href="{{ url('/advance-requests/advanceApprovals') }}" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:25px;border:2px solid #059669;background:#fff;color:#059669;font-size:12px;font-weight:600;text-decoration:none;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,.05);">
                    <i class="ri-coin-line"></i>Approve Advances
                    <span class="badge bg-success ms-1" id="advApproverChipBadge" style="display:none;"></span>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 1: 3 Stat Cards (Clickable / Redirectable) ────────────────── --}}
<div class="row g-3 mb-3">
    @foreach($statCards as $card)
    <div class="col-xl-4 col-md-4 col-sm-12">
        <a href="{{ $card['link'] ?? $rmApproverRoute }}" style="text-decoration:none;display:block;color:inherit;">
            <div class="card h-100 summary-stat-card" style="border-radius:12px;border:1px solid #e8eaed;box-shadow:0 1px 3px rgba(0,0,0,.04);cursor:pointer;">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <div style="width:34px;height:34px;border-radius:8px;background:{{ $card['color'] == 'warning' ? '#fef3c7' : ($card['color'] == 'success' ? '#d1fae5' : ($card['color'] == 'danger' ? '#fee2e2' : ($card['color'] == 'primary' ? '#dbeafe' : '#ede9fe'))) }};display:flex;align-items:center;justify-content:center;margin-right:8px;flex-shrink:0;">
                                <i class="{{ $card['icon'] }}" style="font-size:16px;color:{{ $card['color'] == 'warning' ? '#d97706' : ($card['color'] == 'success' ? '#059669' : ($card['color'] == 'danger' ? '#dc2626' : ($card['color'] == 'primary' ? '#2563eb' : '#7c3aed'))) }};"></i>
                            </div>
                            <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;line-height:1.2;">{{ $card['label'] }}</div>
                        </div>
                        <i class="ri-arrow-right-up-line text-muted fs-14"></i>
                    </div>
                    <div style="font-size:22px;font-weight:700;color:#111827;line-height:1.2;">{{ $card['value'] }}</div>
                    <div class="d-flex align-items-center justify-content-between mt-1" style="min-height:18px;">
                        <div style="font-size:11px;color:#6b7280;">{{ $card['sub'] ?? '' }}</div>
                        <div style="font-size:10px;font-weight:600;">
                            @if($card['change'])
                            @php
                                $isPositiveGood = str_contains($card['label'], 'Approved');
                                $changeType     = $card['change']['type'];
                                $changeClass    = $isPositiveGood
                                    ? ($changeType === 'up'   ? 'text-success' : 'text-danger')
                                    : ($changeType === 'down' ? 'text-success' : 'text-danger');
                                $prevVal = $card['change']['prev'] ?? null;
                                $isZeroZero = ($prevVal !== null && (float)$prevVal == 0 && $card['change']['val'] === '0%');
                                if ($isZeroZero) $changeClass = '';
                            @endphp
                            <span class="{{ $changeClass }}" style="{{ $isZeroZero ? 'color:#9ca3af;' : '' }}">
                                {{ $card['change']['val'] }}
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>

{{-- ── ROW 2: Pending Claims (col-8) & Insights + Alerts (col-4) ── --}}
<div class="row g-3 mb-3">
    {{-- Left: Pending Claims (col-8) --}}
    <div class="col-xl-8 col-lg-8 col-md-12">
        <div class="card h-100" style="border-radius:12px;border:1px solid #e8eaed;box-shadow:0 1px 3px rgba(0,0,0,.04);">
            <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-time-line me-1" style="color:#f59e0b;"></i>Pending Claims
                    <span class="badge ms-1" id="rmPendingCount" style="background:#fef3c7;color:#92400e;font-size:10px;font-weight:600;">...</span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-ghost-secondary p-1" id="rmPendingReloadBtn" title="Refresh pending list" style="border-radius:50%;width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;">
                        <i class="ri-refresh-line fs-14"></i>
                    </button>
                    <a href="{{ $companyApproverRoutes[$currentCompany]['url'] ?? route('vnr.approver') }}" id="rmPendingViewAllLink" class="btn btn-sm" style="background:#e8f0fe;color:#1a56db;border-radius:30px;font-size:10px;padding:2px 12px;text-decoration:none;">
                        View All <i class="ri-arrow-right-s-line"></i>
                    </a>
                </div>
            </div>
            <div class="card-body py-3 px-3">
                <div id="rmSubmitHint" class="alert py-2 px-3 mb-3 border-0" style="display:none;font-size:12px;background:linear-gradient(135deg,#fff3cd,#ffeeba);color:#856404;border-radius:8px;">
                    <i class="ri-error-warning-line me-1"></i>
                    If there are no changes in the approved amount, kindly click on submit button directly.
                </div>
                <div id="rmPendingCards" style="max-height:600px;overflow-y:auto;padding-right:2px;">
                    <div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted ms-2">Loading pending claims...</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Insights & Alerts (col-4) --}}
    <div class="col-xl-4 col-lg-4 col-md-12 d-flex flex-column gap-3">
        {{-- Top Insights --}}
        <div class="card" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2 d-flex align-items-center" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0 flex-grow-1" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-lightbulb-flash-line me-1" style="color:#f59e0b;"></i>Top Insights
                    <span style="font-weight:400;font-size:10px;color:#6b7280;">({{ $rmMonthLabel }} {{ $rmYear }})</span>
                </h5>
            </div>
            <div class="card-body py-3 px-3">
                <div class="d-flex flex-column gap-2">
                    @foreach($insights as $idx => $insight)
                    <div class="d-flex align-items-start">
                        <span style="background:#eff6ff;color:#1a56db;font-size:10px;font-weight:700;padding:2px 8px;border-radius:30px;margin-right:8px;min-width:20px;text-align:center;">{{ $idx + 1 }}</span>
                        <p style="color:#374151;font-size:12px;line-height:1.4;margin:0;">{{ $insight }}</p>
                    </div>
                    @endforeach
                </div>
                {{-- TEAM-BASIS-01 (14 Nov 2026): how the totals are worked out --}}
                <details class="mt-3" style="font-size:11px;color:#5b6478;">
                    <summary style="cursor:pointer;font-weight:600;color:#405189;">How these totals are worked out</summary>
                    <ul class="mb-0 mt-2 ps-3" style="line-height:1.5;">
                        <li><b>Team Expenses (YTD)</b>: the claimed amount of every bill your team members have confirmed in this financial year, at any stage (drafts and deactivated bills left out). It does not use the approved amount.</li>
                        <li><b>Approved in {{ $rmMonthLabel }}</b>: {{ $approvedBasis === 'months' ? 'employee months of '.$rmMonthLabel.' you have approved and submitted, with their approved amount' : 'single bills of '.$rmMonthLabel.' you have approved so far, before the month is submitted, with the amount you entered' }}. It counts only {{ $rmMonthLabel }}, so it can be more than the year's claimed total if an approved amount is wrong.</li>
                        <li><b>Claims Pending Approval</b>: submitted months not approved yet, at the verified amount (the claimed amount where not verified).</li>
                    </ul>
                </details>
            </div>
        </div>

        {{-- Compliance Alerts --}}
        <div class="card" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2 d-flex align-items-center" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0 flex-grow-1" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-alarm-warning-line me-1" style="color:#f59e0b;"></i>Alerts
                    @if(count($complianceAlerts) > 0)
                    <span class="badge ms-1" style="background:#fecaca;color:#991b1b;font-size:10px;font-weight:500;padding:2px 8px;border-radius:12px;">{{ count($complianceAlerts) }}</span>
                    @endif
                </h5>
                <a href="{{ $rmApproverRoute }}" style="font-size:11px;color:#3b82f6;text-decoration:none;font-weight:500;">View All <i class="ri-arrow-right-s-line"></i></a>
            </div>
            <div class="card-body py-3 px-3">
                <div class="d-flex flex-column gap-2">
                    @foreach($complianceAlerts as $alert)
                    @php
                        $alertIconMap = ['warning'=>'ri-alert-line','danger'=>'ri-error-warning-line','info'=>'ri-information-line','success'=>'ri-checkbox-circle-line'];
                        $alertIcon = $alertIconMap[$alert['type']] ?? 'ri-information-line';
                        $alertColor = $alert['type'] == 'warning' ? '#d97706' : ($alert['type'] == 'danger' ? '#dc2626' : ($alert['type'] == 'success' ? '#16a34a' : '#2563eb'));
                    @endphp
                    <div class="d-flex align-items-start">
                        <i class="{{ $alertIcon }}" style="color:{{ $alertColor }};font-size:14px;margin-right:8px;margin-top:2px;flex-shrink:0;"></i>
                        <span style="font-size:12px;color:#374151;line-height:1.4;">{{ $alert['msg'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
         {{-- Claims by Status --}}
    {{-- <div class="col-xl-6 col-lg-6 col-md-12"> --}}
        <div class="card h-100" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-pie-chart-line me-1" style="color:#3b82f6;"></i>Claims by Status
                    <span style="font-weight:400;font-size:10px;color:#6b7280;">({{ $rmMonthLabel }} {{ $rmYear }})</span>
                </h5>
            </div>
            <div class="card-body py-3 px-3">
                @if($sTotal > 0)
                <div class="row align-items-center">
                    <div class="col-5">
                        <div style="position:relative;height:140px;max-height:140px;overflow:hidden;">
                            <canvas id="rm-claims-status-chart"></canvas>
                        </div>
                    </div>
                    <div class="col-7">
                        @foreach($statusMap as $label => $cnt)
                        @if($cnt > 0)
                        @php $pct = round(($cnt / $sTotal) * 100); $sCol = $sColorMap[$label] ?? '#94a3b8'; @endphp
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center gap-1">
                                <span style="width:8px;height:8px;border-radius:2px;background:{{ $sCol }};flex-shrink:0;display:inline-block;"></span>
                                <span style="font-size:10px;color:#374151;">{{ $label }}</span>
                            </div>
                            <span style="font-size:10px;font-weight:600;color:#111827;">{{ $cnt }} ({{ $pct }}%)</span>
                        </div>
                        @endif
                        @endforeach
                        <div class="border-top pt-1 mt-1 d-flex justify-content-between">
                            <span style="font-size:10px;font-weight:600;color:#374151;">Total Claims:</span>
                            <span style="font-size:10px;font-weight:700;color:#0b1a33;">{{ $sTotal }}</span>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-4" style="font-size:11px;">
                    <i class="ri-bar-chart-line d-block mb-1" style="font-size:24px;"></i>
                    No claim data for {{ $rmMonthLabel }} {{ $rmYear }}
                </div>
                @endif
            </div>
        </div>
    {{-- </div> --}}
    </div>
</div>

{{-- ── ROW 3: TAT Analysis ── --}}
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-git-branch-line me-1" style="color:#3b82f6;"></i>Stagewise TAT Analysis
                    <span style="font-weight:400;font-size:10px;color:#6b7280;">({{ $rmMonthLabel }} {{ $rmYear }})</span>
                    @if(!$rmTatHasData)
                    <span class="ms-2" style="font-size:9px;font-weight:500;color:#f97316;background:#fff7ed;border:1px solid #fed7aa;border-radius:20px;padding:1px 8px;">
                        <i class="ri-information-line me-1"></i>No submissions yet
                    </span>
                    @endif
                </h5>
            </div>
            <div class="card-body py-3 px-3">
                {{-- Always render the pipeline — show 0 with colour when no data --}}
                <div class="row align-items-center flex-nowrap">
                    @foreach($tatStages as $stage)
                    @php
                        $current  = $rmTat ? round((float)($rmTat->{$stage['key']} ?? 0), 1) : 0;
                        $previous = $rmTatPrev ? round((float)($rmTatPrev->{$stage['key']} ?? 0), 1) : 0;
                        $diff     = round($current - $previous, 1);
                        $hasPrev  = $rmTatPrev && $previous > 0;
                        $hasValue = $current > 0;
                    @endphp
                    <div class="col text-center">
                        {{-- Icon badge — always coloured --}}
                        <div class="d-flex justify-content-center mb-1">
                            <div style="width:38px;height:38px;border-radius:50%;background:{{ $stage['bg'] }};border:2px solid {{ $stage['color'] }};display:flex;align-items:center;justify-content:center;opacity:{{ $hasValue ? '1' : '0.55' }};">
                                <i class="{{ $stage['icon'] }}" style="font-size:16px;color:{{ $stage['color'] }};"></i>
                            </div>
                        </div>
                        {{-- Stage label --}}
                        <div style="font-size:9px;color:#6b7280;font-weight:500;line-height:1.2;margin-bottom:2px;">{{ $stage['label'] }}</div>
                        {{-- TAT value — always show number, greyed if zero --}}
                        <div style="font-size:17px;font-weight:700;color:{{ $hasValue ? $stage['color'] : '#94a3b8' }};line-height:1.1;">
                            {{ number_format($current, 1) }}
                        </div>
                        <div style="font-size:9px;color:#9ca3af;">Days</div>
                    </div>
                    @if(!$loop->last)
                    <div class="col-auto px-0" style="color:#cbd5e1;font-size:16px;line-height:1;">›</div>
                    @endif
                    @endforeach
                </div>
                {{-- Total banner — always visible --}}
                <div class="mt-3 d-flex align-items-center justify-content-center gap-3"
                     style="background:linear-gradient(90deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:8px;padding:8px 20px;">
                    <div class="d-flex align-items-center gap-1">
                        <i class="ri-timer-flash-line" style="color:#1e40af;font-size:14px;"></i>
                        <span style="font-weight:600;color:#1e40af;font-size:12px;">
                            Total Claim Cycle TAT:
                            <strong style="color:{{ $rmTatTotal > 0 ? '#0b1a33' : '#94a3b8' }};">
                                {{ number_format($rmTatTotal, 1) }} Days
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 4: Claims by Status | Expense Overview ─────────────── --}}
<div class="row g-3 mb-3">
   <div class="col-xl-7 col-lg-7 col-md-12">
        <div class="card h-100" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2 d-flex align-items-center justify-content-between" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-line-chart-line me-1" style="color:#3b82f6;"></i>Expense Trend
                    <span style="font-weight:400;font-size:10px;color:#6b7280;">(Last 6 Months)</span>
                </h5>
                <div class="d-flex align-items-center gap-3" style="font-size:10px;">
                    <div class="d-flex align-items-center gap-1">
                        <span style="width:8px;height:8px;border-radius:2px;background:#dde6ff;display:inline-block; color: #1e3a8a;"></span>
                        <span class="text-muted">Amount</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span style="width:8px;height:8px;border-radius:50%;background:#fdefd2;display:inline-block; color: #7a4500;"></span>
                        <span class="text-muted">Claims</span>
                    </div>
                </div>
            </div>
            <div class="card-body py-3 px-3">
                @if(!empty($trendAmounts) && array_sum($trendAmounts) > 0)
                <div id="rm-expense-trend-chart" style="height:140px;min-height:140px;"></div>
                <div class="row text-center mt-2 pt-2 border-top g-0">
                    <div class="col-4">
                        <div style="font-size:9px;color:#6b7280;">YTD Expenses</div>
                        <div style="font-size:11px;font-weight:700;color:#0b1a33;">₹ {{ number_format($ytdExpenses) }}</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:9px;color:#6b7280;">YTD Claims</div>
                        <div style="font-size:11px;font-weight:700;color:#0b1a33;">{{ number_format($ytdClaims) }}</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:9px;color:#6b7280;">Avg / Claim</div>
                        <div style="font-size:11px;font-weight:700;color:#0b1a33;">₹ {{ number_format($avgClaim) }}</div>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-4" style="font-size:11px;">
                    <i class="ri-line-chart-line d-block mb-1" style="font-size:24px;"></i>
                    No trend data available
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Expense Overview --}}
    <div class="col-xl-5 col-lg-5 col-md-12">
        <div class="card h-100" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-pie-chart-2-line me-1" style="color:#3b82f6;"></i>Expense Overview
                    <span style="font-weight:400;font-size:10px;color:#6b7280;">({{ $rmMonthLabel }} {{ $rmYear }})</span>
                </h5>
            </div>
            <div class="card-body py-3 px-3">
                @if($rmExpItems->isNotEmpty() && $rmExpTotal > 0)
                <div class="row align-items-center">
                    <div class="col-5">
                        <div style="position:relative;height:140px;max-height:140px;overflow:hidden;">
                            <canvas id="rm-expense-overview-chart"></canvas>
                        </div>
                    </div>
                    <div class="col-7">
                        @foreach($rmExpItems as $idx => $exp)
                        @php
                            $eName = $rmClaimNames[$exp->ClaimId] ?? 'Others';
                            $ePct  = round(($exp->total / $rmExpTotal) * 100);
                            $eCol  = $rmExpColors[$idx % count($rmExpColors)];
                        @endphp
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center gap-1">
                                <span style="width:8px;height:8px;border-radius:2px;background:{{ $eCol }};flex-shrink:0;display:inline-block;"></span>
                                <span style="font-size:10px;color:#374151;">{{ $eName }}</span>
                            </div>
                            <span style="font-size:10px;font-weight:600;color:#0b1a33;">₹ {{ number_format($exp->total) }} ({{ $ePct }}%)</span>
                        </div>
                        @endforeach
                        <div class="border-top pt-1 mt-1 d-flex justify-content-between">
                            <span style="font-size:11px;font-weight:700;color:#374151;">Total:</span>
                            <span style="font-size:11px;font-weight:700;color:#0b1a33;">₹ {{ number_format($rmExpTotal) }}</span>
                        </div>
                    </div>
                </div>
                @else
                <div class="text-center text-muted py-4" style="font-size:11px;">
                    <i class="ri-pie-chart-line d-block mb-1" style="font-size:24px;"></i>
                    No expense data for {{ $rmMonthLabel }} {{ $rmYear }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>


{{-- ── Team Claims Summary (Commented out as requested) ── --}}
{{--
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card" style="border-radius:12px;border:1px solid #e8eaed;">
            <div class="card-header py-2 d-flex align-items-center" style="background:#fafbfc;border-bottom:1px solid #f0f0f0;border-radius:12px 12px 0 0;">
                <h5 class="card-title mb-0 flex-grow-1" style="font-size:13px;font-weight:600;color:#1e293b;">
                    <i class="ri-team-line me-1" style="color:#3b82f6;"></i>Team Claims Summary
                    <span style="font-weight:400;font-size:10px;color:#6b7280;">({{ $rmMonthLabel }} {{ $rmYear }})</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:10px;">
                        <thead style="background:#f8fafc;">
                            <tr>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;">Employee</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:center;">Total</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:center;">Pending</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:center;">Approved</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:center;">Returned</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:center;">In Progress</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:center;">Paid</th>
                                <th style="padding:6px 8px;font-weight:600;color:#4b5563;border-bottom:1px solid #e5e7eb;text-align:right;">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamSummary as $ts)
                            <tr>
                                <td style="padding:4px 8px;font-weight:500;color:#1e293b;">{{ $empNames[$ts->CrBy] ?? ('Employee #' . $ts->CrBy) }}</td>
                                <td style="padding:4px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $ts->total_claims ?? 0 }}</td>
                                <td style="padding:4px 8px;text-align:center;">
                                    @if(($ts->pending_approval ?? 0) > 0)
                                    <span class="badge" style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:500;padding:2px 8px;border-radius:12px;">{{ $ts->pending_approval }}</span>
                                    @else—@endif
                                </td>
                                <td style="padding:4px 8px;text-align:center;">
                                    @if(($ts->approved ?? 0) > 0)
                                    <span class="badge" style="background:#d1fae5;color:#065f46;font-size:9px;font-weight:500;padding:2px 8px;border-radius:12px;">{{ $ts->approved }}</span>
                                    @else—@endif
                                </td>
                                <td style="padding:4px 8px;text-align:center;">
                                    @if(($ts->returned ?? 0) > 0)
                                    <span class="badge" style="background:#fecaca;color:#991b1b;font-size:9px;font-weight:500;padding:2px 8px;border-radius:12px;">{{ $ts->returned }}</span>
                                    @else—@endif
                                </td>
                                <td style="padding:4px 8px;text-align:center;color:#374151;">{{ $ts->in_progress ?? 0 ?: '—' }}</td>
                                <td style="padding:4px 8px;text-align:center;">
                                    @if(($ts->paid ?? 0) > 0)
                                    <span class="badge" style="background:#dbeafe;color:#1e40af;font-size:9px;font-weight:500;padding:2px 8px;border-radius:12px;">{{ $ts->paid }}</span>
                                    @else—@endif
                                </td>
                                <td style="padding:4px 8px;text-align:right;font-weight:600;color:#0b1a33;">₹ {{ number_format($ts->total_amount ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-3" style="font-size:11px;">No team data for {{ $rmMonthLabel }} {{ $rmYear }}</td></tr>
                            @endforelse
                        </tbody>
                        @if($teamSummary->isNotEmpty())
                        <tfoot style="background:#f8fafc;border-top:2px solid #e5e7eb;">
                            <tr>
                                <td style="padding:6px 8px;font-weight:700;color:#1e293b;">Total</td>
                                <td style="padding:6px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $teamSummary->sum('total_claims') }}</td>
                                <td style="padding:6px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $teamSummary->sum('pending_approval') ?: '—' }}</td>
                                <td style="padding:6px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $teamSummary->sum('approved') ?: '—' }}</td>
                                <td style="padding:6px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $teamSummary->sum('returned') ?: '—' }}</td>
                                <td style="padding:6px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $teamSummary->sum('in_progress') ?: '—' }}</td>
                                <td style="padding:6px 8px;text-align:center;font-weight:700;color:#0b1a33;">{{ $teamSummary->sum('paid') ?: '—' }}</td>
                                <td style="padding:6px 8px;text-align:right;font-weight:700;color:#0b1a33;">₹ {{ number_format($teamSummary->sum('total_amount')) }}</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            <div class="card-footer py-1 text-center" style="background:#fafbfc;border-top:1px solid #f0f0f0;border-radius:0 0 12px 12px;">
                <a href="{{ $rmApproverRoute }}" class="text-primary fw-medium" style="font-size:11px;text-decoration:none;">View Full Report <i class="ri-arrow-right-line"></i></a>
            </div>
        </div>
    </div>
</div>
--}}



{{-- Day Detail Modal for Approver Tab --}}
<div class="modal fade" id="rmDayModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2 bg-light">
                <h6 class="modal-title" id="rmDayModalTitle">Claims</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rmDayModalBody"></div>
        </div>
    </div>
</div>

{{-- ── Charts & Scripts ────────────────────────────────────────── --}}
@push('scripts')
<script src="{{ asset('custom/js/pages/approver-day-filters.js') }}?v={{ filemtime(public_path('custom/js/pages/approver-day-filters.js')) }}"></script>
<script>
(function () {
    'use strict';

    // ── Company-Aware Approver Routing & Pending Claims Logic ──
    const companyApproverData = @json($companyApproverRoutes);
    let activeCompany = @json($currentCompany);
    let activeRoutes = companyApproverData[activeCompany] || @json($rmApproverRoutes);
    const rmCsrf = "{{ csrf_token() }}";
    const rmPrefix = @json($approverPrefix);
    const rmM = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var rmReviewedEmployees = {};

    $(document).on('click', '.approver-company-tab', function(e){
        e.preventDefault();
        const comp = $(this).data('company');
        if (!comp || !companyApproverData[comp]) return;

        $('.approver-company-tab').removeClass('active');
        $(this).addClass('active');

        activeCompany = comp;
        activeRoutes = companyApproverData[comp];

        $('#approverActiveCoName').text(activeRoutes.name || comp.toUpperCase());

        if (activeRoutes.url) {
            $('#btnApproveClaims').attr('href', activeRoutes.url);
            $('#rmPendingViewAllLink').attr('href', activeRoutes.url);
            $('.summary-stat-card-link').attr('href', activeRoutes.url);
        }

        loadRmPending(comp);
    });

    $(document).on('click', '#rmPendingReloadBtn', function(e){
        e.preventDefault();
        const $icon = $(this).find('i');
        $icon.addClass('rotating');
        loadRmPending(activeCompany, function(){
            $icon.removeClass('rotating');
        });
    });

    function rmeName(i){const nm=i.EmpName;return(nm&&typeof nm==='object')?(nm.Ename||nm.ename||'N/A'):(nm||i.Ename||i.FullName||'N/A');}
    function rmn(v){return parseFloat(String(v||0).replace(/[^0-9.-]/g,''))||0;}
    function rmfa(v){return rmn(v).toLocaleString('en-IN',{maximumFractionDigits:0});}
    function rmvd(v){return v&&v!=='0000-00-00'&&v!=='null'&&v!==''&&v!=='1970-01-01'&&v!=='1970-01-01 00:00:00';}
    function rmfd(v){if(!rmvd(v))return'-';try{const d=new Date(String(v).replace(' ','T'));return isNaN(d.getTime())?'-':d.toLocaleDateString('en-GB');}catch(e){return'-';}}
    function rmfld(item,...keys){for(const k of keys){if(item[k]&&rmvd(item[k]))return item[k];}return '';}
    function rme(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);}

    function parseRmOtherPax(raw){
        if(!raw) return [];
        if(typeof raw === 'object' && Array.isArray(raw)) return raw;
        if(typeof raw !== 'string') return [];
        const trimmed = raw.trim();
        if(!trimmed) return [];
        try{
            const parsed = JSON.parse(trimmed);
            if(Array.isArray(parsed)) return parsed;
            if(typeof parsed === 'object') return [parsed];
        }catch(e){
            try{
                let rep = trimmed;
                if(!rep.endsWith(']')){
                    if(rep.lastIndexOf('}') !== -1){
                        rep = rep.substring(0, rep.lastIndexOf('}') + 1) + ']';
                        const p = JSON.parse(rep);
                        if(Array.isArray(p)) return p;
                    }
                }
            }catch(e2){}
            return [{name: trimmed, category: 'External', details: ''}];
        }
        return [];
    }

    // DASH-DUP-01: one request at a time; a second call within a few seconds (page load + tab shown) reuses the first
    let rmPendingXhr = null, rmPendingAt = 0, rmPendingKey = '';
    function loadRmPending(compKey, callback){
        compKey = compKey || activeCompany;
        if (typeof callback !== 'function' && compKey === rmPendingKey && Date.now() - rmPendingAt < 5000) { return; }
        if (rmPendingXhr && rmPendingXhr.abort) { rmPendingXhr.abort(); }
        rmPendingKey = compKey; rmPendingAt = Date.now();
        const routes = companyApproverData[compKey] || activeRoutes;
        const coName = routes.name || compKey.toUpperCase();
        if(!routes || !routes.pendingList) {
            if (typeof callback === 'function') callback();
            return;
        }

        $('#rmPendingCards').html('<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted ms-2">Loading pending claims for ' + coName + '...</span></div>');

        rmPendingXhr = $.getJSON(routes.pendingList);
        rmPendingXhr.done(function(res){
            let list = (res && res.Pending_list) ? res.Pending_list : [];
            list = Array.isArray(list) ? list : Object.values(list);
            $('#rmPendingCount').text(list.length);
            $('#rmSubmitHint').toggle(list.length > 0);
            if (list.length > 0) {
                $('#btnApproveClaimsBadge').text(list.length).show();
            } else {
                $('#btnApproveClaimsBadge').hide();
            }

            if(!list.length){
                $('#rmPendingCards').html('<div class="empty-state text-muted py-4 text-center"><i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i><p class="mb-1 fw-semibold text-dark">Nothing waiting for you</p><p class="mb-0 fs-12">No team claims of ' + coName + ' are waiting for your approval.' + (compKey === 'vvnr' ? '' : ' Claims appear here after the verifier has verified them.') + '</p></div>');
                if (typeof callback === 'function') callback();
                return;
            }
            try {
                let h = '';
                list.forEach(function(item){
                    const id = item.EmployeeID || '', nm = rmeName(item), mo = item.Month || '', yr = item.YearId || '';
                    const empCode = item.EmpCode || item.empcode || '';
                    const displayName = empCode ? (empCode + ' - ' + nm) : nm;
                    const amt = rmn(item.Claim_Amount || 0), vAmt = rmn(item.Verified_Amount || 0), aAmt = rmn(item.Approved_Amount || item.TotalApprovedAmt || 0);
                    const tc = item.Total_Claim || '0';
                    const diff = amt - vAmt;
                    const cour = item.RecevingDate || item.ReceivingDate || '';
                    const cv = rmvd(cour);
                    const hasVerifier = (vAmt > 0) || (item.Verified_Date && item.Verified_Date !== '0000-00-00') || (item.verification_status === 'Verified');

                    let vStatusBadge = '';
                    let statusRightHtml = '';
                    let submitBtnHtml = '';

                    if (!cv) {
                        vStatusBadge = '<span class="badge bg-warning text-dark ms-1" style="font-size:10px;"><i class="ri-truck-line me-1"></i>Post Pending</span>';
                        statusRightHtml = '<div style="font-size:11px;color:#d97706;font-weight:600"><i class="ri-truck-line"></i> Post Pending</div>';
                        submitBtnHtml = `<button type="button" class="pill-btn disabled-submit-btn ms-auto" data-reason="Post / Courier is pending" title="Post Pending - Cannot submit"><i class="ri-lock-line me-1"></i> Submit</button>`;
                    } else if (!hasVerifier) {
                        vStatusBadge = '<span class="badge bg-warning text-dark ms-1" style="font-size:10px;"><i class="ri-time-line me-1"></i>Verification Pending</span>';
                        statusRightHtml = '<div style="font-size:11px;color:#d97706;font-weight:600"><i class="ri-time-line"></i> Verification Pending</div>';
                        submitBtnHtml = `<button type="button" class="pill-btn disabled-submit-btn ms-auto" data-reason="Verification is pending" title="Verification Pending - Cannot submit"><i class="ri-lock-line me-1"></i> Submit</button>`;
                    } else {
                        vStatusBadge = '<span class="badge bg-success ms-1" style="font-size:10px;"><i class="ri-checkbox-circle-line me-1"></i>Verified</span>';
                        statusRightHtml = `<div style="font-size:11px;color:#198754;font-weight:600"><i class="ri-checkbox-circle-line"></i> Verified: ₹${rmfa(vAmt)}</div>`;
                        if (diff > 0) {
                            statusRightHtml += `<div style="font-size:11px;color:#dc3545">Diff: ₹${rmfa(diff)}</div>`;
                        }
                        submitBtnHtml = `<a class="pill-btn submit rm-submit-btn ms-auto" data-empid="${rme(id)}" data-month="${rme(mo)}" data-yearid="${rme(yr)}" data-verified="1"><i class="ri-check-double-line"></i> Submit</a>`;
                    }

                    h += `<div class="card claim-card mb-2" style="border:1px solid #e2e8f0;border-radius:10px;"><div class="stripe primary"></div><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="emp-name" style="font-size:14px;font-weight:700;color:var(--vz-primary,#405189);">${rme(displayName)} ${vStatusBadge}</div>
                            <div class="meta" style="font-size:12px;color:#64748b;margin-top:3px;"><i class="ri-calendar-line"></i> ${rme(rmM[+mo]||mo)} &nbsp;|&nbsp; Claims: <b>${rme(tc)}</b> &nbsp;|&nbsp; Courier: ${cv ? '<span class="text-success fw-semibold">'+rmfd(cour)+'</span>' : '<span class="text-warning">Pending</span>'}</div>
                        </div>
                        <div class="text-end">
                            <div class="amount" style="font-size:17px;font-weight:700;color:var(--vz-primary,#405189);">₹${rmfa(amt)}</div>
                            ${statusRightHtml}
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                        <a class="pill-btn att rm-att-btn" data-empid="${rme(id)}" data-month="${rme(mo)}"><i class="ri-calendar-check-line"></i> Att.</a>
                        <a class="pill-btn view rm-view-btn" data-empid="${rme(id)}" data-month="${rme(mo)}" data-yearid="${rme(yr)}" data-name="${rme(nm)}"><i class="ri-eye-line"></i> View</a>
                        ${submitBtnHtml}
                    </div>
                    <div class="mini-stepper">
                        <div class="mini-step done"><div class="step-dot done"><i class="ri-user-3-line"></i></div><div class="lbl">Claimant</div><div class="val">₹${rmfa(amt)}</div><div class="val-date">${rmfd(rmfld(item,'DateOfSubmit','dateofsubmit','SubmitDate','Crdate'))}</div></div>
                        <div class="mini-step ${cv?'done':''}"><div class="step-dot ${cv?'done':'wait'}"><i class="ri-truck-line"></i></div><div class="lbl">Post</div><div class="val">${cv?rmfd(cour):'-'}</div></div>
                        <div class="mini-step ${hasVerifier?'done':''}"><div class="step-dot ${hasVerifier?'done':'wait'}"><i class="ri-file-search-line"></i></div><div class="lbl">Verifier</div><div class="val">${hasVerifier?'₹'+rmfa(vAmt):'Pending'}</div><div class="val-date">${hasVerifier?rmfd(rmfld(item,'Verified_Date','verified_date','VerifiedDate','VerifDate','Verify_Date')):'-'}</div></div>
                        <div class="mini-step"><div class="step-dot wait"><i class="ri-shield-check-line"></i></div><div class="lbl">Approver</div><div class="val">${aAmt>0?'₹'+rmfa(aAmt):'Pending'}</div><div class="val-date">${aAmt>0?rmfd(rmfld(item,'Approved_Date','approved_date','ApprovedDate','ApprDate','Appr_Date')):'-'}</div></div>
                    </div></div></div>`;
                });
                $('#rmPendingCards').html(h);
            } catch(renderErr) {
                console.error('Error rendering pending claims:', renderErr);
                $('#rmPendingCards').html('<div class="empty-state text-muted py-4 text-center"><i class="ri-error-warning-line fs-24 text-danger d-block mb-2"></i><p>Error displaying pending claims</p></div>');
            }
            if (typeof callback === 'function') callback();
        }).fail(function(xhr, status, error){
            if (status === 'abort') { return; }
            console.error('Failed to load pending claims:', status, error);
            $('#rmPendingCount').text('0');
            $('#rmPendingCards').html('<div class="empty-state text-muted py-4 text-center"><i class="ri-error-warning-line fs-24 d-block mb-2 text-warning"></i><p>Failed to load pending claims for ' + coName + '</p></div>');
            if (typeof callback === 'function') callback();
        });
    }

    // Load once when the page is ready, and again when the tab is shown (skipped if just loaded)
    $(document).ready(function(){
        loadRmPending(activeCompany);
    });
    $(document).on('shown.bs.tab', 'a[href="#approver-tab"]', function(){
        loadRmPending(activeCompany);
    });

    // ── Claims by Status Donut ────────────────────────────────
    @if($sTotal > 0)
    (function() {
        try {
            var ctx = document.getElementById('rm-claims-status-chart');
            if (!ctx || typeof Chart === 'undefined') return;
            var existing = Chart.getChart(ctx);
            if (existing) existing.destroy();
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($rmStatusLabels) !!},
                    datasets: [{
                        data: {!! json_encode($rmStatusSeries) !!},
                        backgroundColor: {!! json_encode(array_values($rmStatusColors)) !!},
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
                                    var total = ctx.dataset.data.reduce(function(a,b){return a+b;}, 0);
                                    var pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                                    return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } catch(e) {
            console.warn('rm-claims-status-chart init warning:', e);
        }
    })();
    @endif

    // ── Expense Overview Donut ────────────────────────────────
    @if($rmExpItems->isNotEmpty() && $rmExpTotal > 0)
    (function() {
        try {
            var ctx = document.getElementById('rm-expense-overview-chart');
            if (!ctx || typeof Chart === 'undefined') return;
            var existing = Chart.getChart(ctx);
            if (existing) existing.destroy();
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($rmExpLabels) !!},
                    datasets: [{
                        data: {!! json_encode($rmExpSeries) !!},
                        backgroundColor: {!! json_encode($rmExpColors) !!},
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
                                    return ' ' + ctx.label + ': ₹' + ctx.parsed.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });
        } catch(e) {
            console.warn('rm-expense-overview-chart init warning:', e);
        }
    })();
    @endif

    // ── Expense Trend (ApexCharts) ────────────────────────────
    @if(!empty($trendAmounts) && array_sum($trendAmounts) > 0)
    (function() {
        try {
            var el = document.getElementById('rm-expense-trend-chart');
            if (!el || typeof ApexCharts === 'undefined') return;
            new ApexCharts(el, {
                series: [
                    { name: 'Amount (₹)', type: 'column', data: {!! json_encode(array_map('floatval', $trendAmounts)) !!} },
                    { name: 'No. of Claims', type: 'line', data: {!! json_encode(array_map('intval', $trendCounts)) !!} }
                ],
                chart: { height: 140, type: 'line', toolbar: { show: false }, fontFamily: 'inherit' },
                stroke: { width: [0, 2], curve: 'smooth' },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '50%' } },
                colors: ['#3b82f6', '#f59e0b'],
                xaxis: {
                    categories: {!! json_encode($trendLabels) !!},
                    labels: { style: { fontSize: '9px', colors: '#6b7280' } }
                },
                yaxis: [
                    { labels: { formatter: function(v) { return '₹' + (v / 1000).toFixed(0) + 'K'; }, style: { fontSize: '9px', colors: '#6b7280' } } },
                    { opposite: true, labels: { formatter: function(v) { return v; }, style: { fontSize: '9px', colors: '#6b7280' } } }
                ],
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { padding: { top: 0, bottom: 0 } }
            }).render();
        } catch(e) {
            console.warn('rm-expense-trend-chart init warning:', e);
        }
    })();
    @endif

    // ═══ VIEW DAY ═══
    $(document).on('click', '.rm-view-btn', function(ev){
        ev.preventDefault();
        const id = $(this).data('empid'), mo = $(this).data('month'), yr = $(this).data('yearid'), nm = $(this).data('name') || '';
        $('#rmDayModalTitle').text('Pending Claims - ' + nm + ' - ' + (rmM[+mo] || mo));
        $('#rmDayModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></div>');
        $('#rmDayModal').modal('show');
        const pendingDayUrl = (activeRoutes && activeRoutes.pendingDay) ? activeRoutes.pendingDay : (rmApproverRoutes ? rmApproverRoutes.pendingDay : null);
        if(!pendingDayUrl){
            $('#rmDayModalBody').html('<p class="text-center text-danger py-3">Pending day route not configured.</p>');
            return;
        }
        $.getJSON(pendingDayUrl, {empid: id, month: mo, yearid: yr}).done(function(res){
            renderRmDay(res.DayWiseDetails || [], id, mo, nm);
            aprExcludedNote('#rmDayModalBody', res.Excluded);
        }).fail(function(){
            $('#rmDayModalBody').html('<p class="text-center text-danger py-3">Failed to load claim details.</p>');
        });
    });


    // APPR-RECON-01 (14 Nov 2026): punched lines that are not in this list or its total, with the reason
    function aprExcludedNote(sel,list){
        $(sel).find('.apr-excluded').remove();
        if(!list||!list.length){return;}
        const esc=v=>$('<div>').text(v==null?'':String(v)).html();
        const amt=v=>(parseFloat(v)||0).toLocaleString('en-IN',{maximumFractionDigits:2});
        let tot=0;const items=list.map(x=>{const v=parseFloat(x.VerifyTAmt)||parseFloat(x.FilledTAmt)||0;tot+=v;return '<li>Exp #'+esc(x.ExpId)+' · '+esc(x.ClaimName||'Expense')+(x.BillDate?' · '+esc(String(x.BillDate).substr(0,10).split('-').reverse().join('/')):'')+' · ₹'+amt(v)+' – '+esc(x.Reason)+'</li>';}).join('');
        $(sel).append('<div class="apr-excluded alert alert-warning mt-2 mb-0 py-2 px-3" style="font-size:12px;"><strong>Not in this list or its total (₹'+amt(tot)+'):</strong><ul class="mb-0 ps-3">'+items+'</ul></div>');
    }
    function renderRmDay(list, cE, cM, empName){
        if(!list.length){
            $('#rmDayModalBody').html('<div class="empty-state py-4 text-center"><i class="ri-inbox-line fs-24 d-block mb-1"></i><p>No details found</p></div>');
            return;
        }
        let tC=0, tV=0, tA=0, rows='';
        const unapproved=[], overClaimed=[];
        list.forEach(function(d, i){
            const eid = d.ExpId || d.expId || '', empId = d.EmployeeID || d.employeeId || d.EId || cE, cn = d.ClaimName || d.claimName || 'Expense';
            const ca = rmn(d.FilledTAmt || d.Claim_Amount || 0), va = rmn(d.VerifyTAmt || d.Verified_Amount || 0);
            const rawAppr = rmn(d.ApprTAmt || d.Approved_Amount || 0);
            // APPR-CAP-02 (14 Nov 2026): the claimed amount is the ceiling; a verified figure above it is not offered
            const cap = ca > 0 ? ca : va;
            // APPR-ZERO-02: a line already approved at ₹0 keeps ₹0 (not the verified amount)
            const aprDone = !!(d.ApprDate && !/^0000|^$/.test(String(d.ApprDate)) && rmn(d.ApprBy) > 0);
            const aa = ((rawAppr > 0 || aprDone) ? rawAppr : (cap > 0 ? Math.min(va, cap) : va));
            const overCap = cap > 0 && aa > cap + 0.009;
            if(overCap){ overClaimed.push('#' + eid); }
            
            let diffText = '-';
            let diffClass = 'text-muted';
            if (va < ca) {
                const deducted = ca - va;
                diffText = '-' + rmfa(deducted);
                diffClass = 'text-danger fw-bold';
            } else if (va > ca) {
                const added = va - ca;
                diffText = '+' + rmfa(added);
                diffClass = 'text-success fw-bold';
            }

            const bd = d.BillDate || d.billDate || '', apd = d.ApprDate || '', isA = rmvd(apd);
            tC += ca; tV += va; tA += aa;
            if(!isA && aa !== va){ unapproved.push('#' + eid); }

            const imgs = [];
            ['Img_1','Img_2','Img_3','opening_filepath','closing_filepath'].forEach(function(k){ if(d[k]) imgs.push(d[k]); });
            const pdfFiles = [];
            ['pdf_1','pdf_2'].forEach(function(k){ if(d[k]) pdfFiles.push(d[k]); });
            let mediaHtml = '';
            if(imgs.length){ mediaHtml += imgs.map(function(u){ return `<a href="${u}" target="_blank"><img alt="Bill or document image" src="${u}" class="rounded border" style="height:35px;width:35px;object-fit:cover;margin-right:3px;" onerror="this.style.display='none'"></a>`; }).join(''); }
            if(pdfFiles.length){ mediaHtml += pdfFiles.map(function(u){ return `<a href="${u}" target="_blank" class="badge bg-danger-subtle text-danger" style="font-size:9px;margin-right:3px;"><i class="ri-file-pdf-line"></i></a>`; }).join(''); }
            if(!mediaHtml) mediaHtml = '<span class="text-muted" style="font-size:9px;">—</span>';

            const empRemark = String(d.DateEntryRemark || d.EmpRemark || d.Remark || '').trim();
            const empRmkContent = empRemark ? `<div style="font-size:11px;color:#475569;word-break:break-word;" title="${rme(empRemark)}">${rme(empRemark)}</div>` : '<span class="text-muted" style="font-size:11px;">—</span>';

            const vRmk = String(d.VerifyTRemark || d.VerifyRemark || d.verifier_remark || d.VerifierRemark || d.VerTRemark || '').trim();
            let vRmkContent = '';
            if (vRmk) {
                if (va < ca) {
                    vRmkContent = `<div class="p-1 px-2 rounded" style="background:#fef2f2;border-left:3px solid #ef4444;font-size:11px;color:#991b1b;word-break:break-word;" title="${rme(vRmk)}">
                        <i class="ri-error-warning-line me-1 text-danger"></i>${rme(vRmk)}
                    </div>`;
                } else {
                    vRmkContent = `<div style="font-size:11px;color:#334155;word-break:break-word;" title="${rme(vRmk)}">${rme(vRmk)}</div>`;
                }
            } else {
                vRmkContent = '<span class="text-muted" style="font-size:11px;">—</span>';
            }

            const hasPax = (rmn(d.NoOfPAX) > 0) || (d.PaxEmployeeDetails && d.PaxEmployeeDetails.length > 0) || (d.PaxDetails && String(d.PaxDetails).trim() !== '');
            let paxBadgeHtml = '';
            let paxRowHtml = '';

            if (hasPax) {
                const paxCount = rmn(d.NoOfPAX) || (d.PaxEmployeeDetails ? d.PaxEmployeeDetails.length : 0);
                paxBadgeHtml = `<div class="mt-1">
                    <button type="button" class="btn-pax-pill toggle-pax-btn" data-target="rm_pax_row_${rme(eid)}" title="Click to view participant details">
                        <i class="ri-group-line"></i><span>Group · PAX <b>${paxCount}</b>${(function(){ const ne = (d.PaxEmployeeDetails||[]).length, no = parseRmOtherPax(d.PaxDetails_Other).length; return (ne||no) ? ` <span class="fw-normal">(${ne} emp${no?` + ${no} other`:''})</span>` : ''; })()}</span><i class="ri-arrow-down-s-line pax-chevron"></i>
                    </button>
                </div>`;

                let empListHtml = '';
                if (d.PaxEmployeeDetails && d.PaxEmployeeDetails.length > 0) {
                    let empRows = '';
                    d.PaxEmployeeDetails.forEach(function(pEmp, pIdx) {
                        const empCode = pEmp.EmpCode || pEmp.EmpCode_New || pEmp.EmployeeID || '-';
                        const pEmpName = pEmp.EmployeeName || [pEmp.Fname, pEmp.Sname, pEmp.Lname].filter(Boolean).join(' ') || ('Employee #' + empCode);
                        const dept = pEmp.department_name || '-';
                        const grade = pEmp.grade_name || '-';
                        empRows += `<tr>
                            <td class="text-center text-muted" style="width:35px;">${pIdx + 1}</td>
                            <td style="width:90px;"><span class="badge bg-light text-dark border font-monospace">${rme(empCode)}</span></td>
                            <td class="fw-semibold text-dark">${rme(pEmpName)}</td>
                            <td><span class="text-secondary">${rme(dept)}</span></td>
                            <td class="text-center" style="width:65px;"><span class="badge bg-secondary-subtle text-secondary">${rme(grade)}</span></td>
                        </tr>`;
                    });
                    empListHtml = `<div class="table-responsive mb-1">
                        <table class="table table-sm table-bordered bg-white mb-0 pax-sub-table">
                            <thead class="table-light">
                                <tr class="text-muted" style="font-size:11px;">
                                    <th class="text-center" style="width:35px;">#</th>
                                    <th>Emp Code</th>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th class="text-center">Grade</th>
                                </tr>
                            </thead>
                            <tbody>${empRows}</tbody>
                        </table>
                    </div>`;
                } else if (d.PaxDetails && String(d.PaxDetails).trim() !== '') {
                    empListHtml = `<div class="text-muted small py-1"><i class="ri-information-line me-1"></i> Participant IDs: <span class="fw-semibold font-monospace">${rme(d.PaxDetails)}</span></div>`;
                }

                const otherList = parseRmOtherPax(d.PaxDetails_Other);
                let otherListHtml = '';
                if (otherList.length > 0) {
                    let otherRows = '';
                    otherList.forEach(function(oth, oIdx) {
                        otherRows += `<tr>
                            <td class="text-center text-muted" style="width:35px;">${oIdx + 1}</td>
                            <td class="fw-semibold text-dark">${rme(oth.name || '-')}</td>
                            <td style="width:130px;"><span class="badge bg-info-subtle text-info">${rme(oth.category || 'External')}</span></td>
                            <td class="text-muted" style="font-size:11px;">${rme(oth.details || '-')}</td>
                        </tr>`;
                    });
                    otherListHtml = `<div class="mt-2">
                        <div class="fw-semibold text-dark mb-1" style="font-size:11.5px;"><i class="ri-user-follow-line me-1 text-info"></i> Other / External Participants (${otherList.length})</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0 pax-sub-table">
                                <thead class="table-light">
                                    <tr class="text-muted" style="font-size:11px;">
                                        <th class="text-center" style="width:35px;">#</th>
                                        <th>Guest / Participant Name</th>
                                        <th>Category</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>${otherRows}</tbody>
                            </table>
                        </div>
                    </div>`;
                }

                paxRowHtml = `<tr id="rm_pax_row_${rme(eid)}" class="pax-detail-row" style="display:none;background:#f8fafc;">
                    <td colspan="14" class="p-3 border-top-0">
                        <div class="pax-card p-3 rounded-3 border bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                                <div>
                                    <span class="fw-bold text-primary" style="font-size:13px;">
                                        <i class="ri-restaurant-line me-1 text-warning"></i> PAX & Participants Details (Exp #${rme(eid)} - ${rme(cn)})
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Total PAX: <b>${paxCount}</b></span>
                                    ${d.PaxEmployeeDetails && d.PaxEmployeeDetails.length ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Employees: <b>${d.PaxEmployeeDetails.length}</b></span>` : ''}
                                    ${otherList.length ? `<span class="badge bg-info-subtle text-info border border-info-subtle">Other: <b>${otherList.length}</b></span>` : ''}
                                    <button type="button" class="btn btn-sm btn-light py-0 px-2 toggle-pax-btn text-muted" data-target="rm_pax_row_${rme(eid)}" title="Close">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                            ${empListHtml}
                            ${otherListHtml}
                        </div>
                    </td>
                </tr>`;
            }

            // APPR-FILTER-02: values the column filters read (claim type, Exp ID, date, amounts, group claim)
            const rowIso = (function(v){ const m = String(v||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[0] : ''; })(bd);
            rows += `<tr class="${overCap?'table-danger':''}" data-claim="${rme(cn)}" data-exp="${rme(eid)}" data-date="${rowIso}" data-claimed="${ca}" data-verified="${va}" data-group="${hasPax?1:0}"><td class="text-center"><input type="checkbox" class="form-check-input rm-apr-check" aria-label="Select claim ${rme(eid)}"></td><td class="text-center">${i+1}</td>
            <td>
                <a href="#" class="fw-semibold view-claim" data-expid="${rme(eid)}" data-empid="${rme(empId)}" data-claim-id="${rme(d.ClaimId||d.claimId||d.cgId||'')}" data-cgid="${rme(d.ClaimId||d.claimId||d.cgId||'')}">${rme(cn)}</a>
                <div class="text-muted" style="font-size:9px;">Exp #${rme(eid)}</div>
                ${paxBadgeHtml}
            </td>
            <td class="text-center">${rmfd(bd)}</td>
            <td>${mediaHtml}</td>
            <td class="text-end claim-claimed-cell">₹${rmfa(ca)}</td>
            <td class="text-end claim-verified-cell">₹${rmfa(va)}</td>
            <td class="text-end ${diffClass}">${diffText}</td>
            <td class="remark-col" style="min-width:140px;max-width:240px;">${vRmkContent}</td>
            <td class="text-end"><input type="number" class="form-control form-control-sm amt-input aa${overCap?' is-invalid':''}" value="${aa}" min="0" ${cap>0?`max="${cap}"`:''} title="At most the claimed amount (₹${rmfa(cap)})">${overCap?`<div class="invalid-feedback d-block" style="font-size:10px;">Above the claimed ₹${rmfa(cap)}. Lower it and approve again.</div>`:''}</td>
            <td><input type="text" class="form-control form-control-sm amt-input ar" value="${rme(d.ApprTRemark||d.ApprRemark||'')}" placeholder="Remark" aria-label="Remark"></td>
            <td class="remark-col" style="min-width:130px;max-width:220px;">${empRmkContent}</td>
            <td class="text-center claim-status-cell">${isA?'<span class="badge bg-success">Done</span>':'<span class="badge bg-warning text-dark">Pending</span>'}</td>
            <td class="text-center"><button class="btn btn-sm ${isA?'btn-outline-success':'btn-primary'} rounded-pill px-3 rm-approve-one" data-expid="${rme(eid)}" data-empid="${rme(empId)}" data-month="${rme(cM)}" title="${isA?'Update the approved amount (until final submit)':'Approve'}"><i class="${isA?'ri-refresh-line':'ri-check-line'}"></i></button></td></tr>` + paxRowHtml;
        });

        let diffTotalText = '-';
        let diffTotalClass = '';
        if (tV < tC) {
            diffTotalText = '-' + rmfa(tC - tV);
            diffTotalClass = 'text-danger fw-bold';
        } else if (tV > tC) {
            diffTotalText = '+' + rmfa(tV - tC);
            diffTotalClass = 'text-success fw-bold';
        }

        // APPR-BULK-01: tick claims (or all) and approve them in one go at the amounts shown
        $('#rmDayModalBody').html(`<div class="d-flex align-items-center gap-2 mb-2 px-1 apr-bulk-bar">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 rm-approve-selected" disabled><i class="ri-check-double-line me-1"></i>Approve selected (<span class="rm-apr-sel-count">0</span>)</button>
            <small class="text-muted">Tick the claims, or the box in the header for all. Until the final Submit you can change an approved amount and approve it again.</small>
        </div><div class="table-responsive"><table class="table table-bordered table-hover day-tbl mb-0">
            <thead class="table-light"><tr class="text-center align-middle" style="font-size:11.5px;">
                <th style="width:32px;"><input type="checkbox" class="form-check-input rm-apr-check-all" title="Select all" aria-label="Select all claims"></th>
                <th style="width:35px;">#</th>
                <th>Claim</th>
                <th style="width:85px;">Date</th>
                <th>Attachments</th>
                <th style="width:75px;">Claimed</th>
                <th style="width:75px;">Verified</th>
                <th style="width:70px;">Diff</th>
                <th style="min-width:140px;max-width:240px;">Verifier Remark</th>
                <th style="width:90px;">Appr. Amt</th>
                <th style="width:120px;">Appr. Remark</th>
                <th style="min-width:130px;max-width:220px;">Emp. Remark</th>
                <th style="width:65px;">Status</th>
                <th style="width:50px;">Action</th>
            </tr></thead>
            <tbody>${rows}</tbody>
            <tfoot class="table-light fw-bold"><tr>
                <td colspan="5" class="text-end">Total</td>
                <td class="text-end">₹${rmfa(tC)}</td>
                <td class="text-end">₹${rmfa(tV)}</td>
                <td class="text-end ${diffTotalClass}">${diffTotalText}</td>
                <td></td>
                <td class="text-end">₹${rmfa(tA)}</td>
                <td colspan="4"></td>
            </tr></tfoot>
        </table></div>`);

        // APPR-FILTER-01: column filters (full-screen view)
        if(window.xeasyApproverFilters){ window.xeasyApproverFilters.attach('#rmDayModalBody'); }

        const cardKey = cE + '_' + cM;
        rmReviewedEmployees[cardKey] = { name: empName || '', claimTotal: tC, verifyTotal: tV, approveTotal: tA, unapproved: unapproved, overClaimed: overClaimed };
    }

    // ── APPROVE ONE ──
    // returns a promise: true when approved, false when refused (message shown unless quiet)
    function rmApproveRow($r, quiet){
        const $b = $r.find('.rm-approve-one'), eid = $b.data('expid'), empId = $b.data('empid'), mo = $b.data('month');
        const amt = rmn($r.find('.aa').val()), rmk = $r.find('.ar').val() || '';
        const fail = function(t, m, focus){ if(!quiet){ Swal.fire(t, m, 'warning'); } if(focus){ $r.find(focus).focus(); } $r.addClass('table-danger'); return $.Deferred().resolve(false, '#' + eid + ': ' + m).promise(); };
        // APPR-ZERO-01 (14 Nov 2026): ₹0 is a valid approval (the claim is disallowed) - the same on the first approval and
        // on an update; it needs a remark when it differs from the verified amount (below). Blank or negative is refused.
        if($r.find('.aa').val().trim() === '' || isNaN(amt) || amt < 0){ return fail('Error', 'Enter the approved amount (0 or more).', '.aa'); }
        if(rmCapCheck($r.find('.aa'))){ return fail('Not allowed', 'The approved amount cannot be more than the claimed amount.', '.aa'); }
        const vAmt = rmn($r.find('.claim-verified-cell').text()), cAmt = rmn($r.find('.claim-claimed-cell').text());
        // APPR-CAP-01: the approver can keep or reduce, never increase (ceiling: verified, else claimed)
        // APPR-CAP-02: the ceiling is the claimed amount (the approver may raise a cut verified amount up to it, never above)
        const basis = cAmt > 0 ? cAmt : vAmt;
        if(basis > 0 && amt > basis + 0.009){ return fail('Not allowed', 'The approved amount (' + amt.toFixed(2) + ') cannot be more than the ' + (cAmt > 0 ? 'claimed' : 'verified') + ' amount (' + basis.toFixed(2) + ').', '.aa'); }
        if(amt !== vAmt && !rmk.trim()){ return fail('Remark Required', 'Since approved amount differs from verified, please enter a remark.', '.ar'); }
        const approveClaimUrl = (activeRoutes && activeRoutes.approveClaim) ? activeRoutes.approveClaim : (rmApproverRoutes ? rmApproverRoutes.approveClaim : null);
        if(!approveClaimUrl){ return fail('Error', 'Approve claim route not configured.'); }
        $r.removeClass('table-danger');
        $b.prop('disabled', true).html('<i class="ri-loader-4-line rotating"></i>');
        const d = $.Deferred();
        $.post(approveClaimUrl, { _token: rmCsrf, exp_id: eid, employee_id: empId, amount: amt, remark: rmk, month: mo }).done(function(res){
            if(res.Code == '300' || res.success){
                $r.find('.claim-status-cell').html('<span class="badge bg-success">Done</span>');
                $b.prop('disabled', false).removeClass('btn-primary').addClass('btn-outline-success').attr('title', 'Update the approved amount (until final submit)').html('<i class="ri-refresh-line"></i>');
                $r.find('.rm-apr-check').prop('checked', false);
                $r.find('.aa').removeClass('is-invalid'); $r.find('.aa').siblings('.invalid-feedback').remove();
                const cardKey = empId + '_' + mo;
                if(rmReviewedEmployees[cardKey]){
                    rmReviewedEmployees[cardKey].unapproved = rmReviewedEmployees[cardKey].unapproved.filter(x => x !== '#' + eid);
                    rmReviewedEmployees[cardKey].overClaimed = (rmReviewedEmployees[cardKey].overClaimed || []).filter(x => x !== '#' + eid);
                    rmReviewedEmployees[cardKey].approveTotal = rmn(res.TotalApprovedAmt) || rmReviewedEmployees[cardKey].approveTotal;
                }
                d.resolve(true);
            } else {
                if(!quiet){ Swal.fire('Failed', res.msg || 'Error', 'error'); }
                $b.prop('disabled', false).html('<i class="ri-check-line"></i>'); $r.addClass('table-danger');
                d.resolve(false, '#' + eid + ': ' + (res.msg || 'Error'));
            }
        }).fail(function(x){
            const j = x.responseJSON || {};
            if(!quiet){ Swal.fire('Error', j.msg || j.message || 'Network error', 'error'); }
            $b.prop('disabled', false).html('<i class="ri-check-line"></i>'); $r.addClass('table-danger');
            d.resolve(false, '#' + eid + ': ' + (j.msg || j.message || 'Network error'));
        });
        return d.promise();
    }
    // APPR-CAP-UI-01 (14 Nov 2026): flag an approved amount above the claimed amount beside Appr. Amt as it is typed and
    // keep the row's action disabled until it is corrected
    function rmCapCheck($inp){
        const $r = $inp.closest('tr'), v = parseFloat($inp.val()), c = rmn($r.find('.claim-claimed-cell').text()) || rmn($r.find('.claim-verified-cell').text());
        const over = !isNaN(v) && c > 0 && v > c + 0.009;
        $inp.toggleClass('is-invalid', over).attr('aria-invalid', over ? 'true' : 'false');
        $inp.siblings('.rm-cap-msg').remove();
        if(over){ $inp.after('<div class="rm-cap-msg invalid-feedback d-block" style="font-size:10px;">More than the claimed ₹' + rmfa(c) + '</div>'); }
        $r.find('.rm-approve-one').prop('disabled', over);
        return over;
    }
    $(document).on('input', '#rmDayModalBody .aa', function(){ rmCapCheck($(this)); });
    $(document).on('click', '.rm-approve-one', function(){
        rmApproveRow($(this).closest('tr'), false).done(function(ok){ if(ok){ Swal.fire({icon: 'success', title: 'Approved!', timer: 1000, showConfirmButton: false}); } });
    });
    // ── APPROVE SELECTED (APPR-BULK-01) ──
    function rmSelCount(){
        const c = $('#rmDayModalBody .rm-apr-check:checked').length, all = $('#rmDayModalBody tr:visible .rm-apr-check').length;
        $('#rmDayModalBody .rm-apr-sel-count').text(c);
        $('#rmDayModalBody .rm-approve-selected').prop('disabled', c === 0);
        $('#rmDayModalBody .rm-apr-check-all').prop('checked', all > 0 && c === all).prop('indeterminate', c > 0 && c < all);
    }
    $(document).on('change', '#rmDayModalBody .rm-apr-check-all', function(){ $('#rmDayModalBody .rm-apr-check').prop('checked', false); $('#rmDayModalBody tr:visible .rm-apr-check').prop('checked', this.checked); rmSelCount(); });
    $(document).on('change', '#rmDayModalBody .rm-apr-check', rmSelCount);
    $(document).on('click', '#rmDayModalBody .rm-approve-selected', function(){
        const rows = $('#rmDayModalBody .rm-apr-check:checked').closest('tr').toArray();
        if(!rows.length){ return; }
        $(this).prop('disabled', true);
        let ok = 0; const bad = [];
        (async function(){
            for(const tr of rows){
                const r = await new Promise(function(res){ rmApproveRow($(tr), true).done(function(a, b){ res([a, b]); }); });
                if(r[0]){ ok++; } else { bad.push(r[1]); }
            }
            rmSelCount();
            Swal.fire({icon: bad.length ? 'warning' : 'success', title: ok + ' of ' + rows.length + ' approved',
                html: bad.length ? '<div class="text-start" style="font-size:12px;"><p>Not approved (marked red):</p><ul>' + bad.map(m => '<li>' + rme(m) + '</li>').join('') + '</ul></div>' : ''});
        })();
    });

    // ── SUBMIT MONTH ──
    $(document).on('click', '.disabled-submit-btn', function(ev){
        ev.preventDefault();
        const reason = $(this).data('reason') || 'Claims are not verified';
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Submit',
            html: `<p>This month cannot be submitted because <strong>${rme(reason)}</strong>.</p><p class="text-muted" style="font-size:12px;">Only claims verified by the verifier can be approved and submitted.</p>`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#405189'
        });
    });

    $(document).on('click', '.rm-submit-btn', function(ev){
        ev.preventDefault();
        const id = $(this).data('empid'), mo = $(this).data('month'), yr = $(this).data('yearid'), $b = $(this);
        const cardKey = id + '_' + mo;

        const isVerified = $(this).data('verified') == 1 || $(this).data('verified') === true;
        if (!isVerified) {
            Swal.fire({
                icon: 'warning',
                title: 'Verification Required',
                html: '<p>This claim month cannot be submitted because it is <strong>not yet verified</strong> by the verifier.</p><p class="text-muted" style="font-size:12px;">Only verified claims can be approved and submitted.</p>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }

        if (!rmReviewedEmployees[cardKey]) {
            Swal.fire({
                icon: 'warning',
                title: 'Review Required',
                html: '<p>Please <strong>View</strong> and review the day-wise claims before submitting.</p><p class="text-muted" style="font-size:12px;">Click the "View" button first to review all claims.</p>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }

        // APPR-CAP-02: no final submit while an approved amount is above the claimed amount (the server checks it too)
        const overClaimed = rmReviewedEmployees[cardKey].overClaimed || [];
        if (overClaimed.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Approved amount above claimed',
                html: `<p>The approved amount cannot be more than the claimed amount. Lower the amount for these claims and approve them again:</p><p class="text-danger fw-bold">${rme(overClaimed.join(', '))}</p>`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }
        const unapproved = rmReviewedEmployees[cardKey].unapproved || [];
        if ((activeCompany === 'vnr' || rmPrefix === 'vnr') && unapproved.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Unapproved Claims',
                html: `<p>Please approve the following claims before final submit:</p><p class="text-danger fw-bold">${unapproved.join(', ')}</p>`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }

        const info = rmReviewedEmployees[cardKey];
        Swal.fire({
            title: 'Confirm Submit',
            html: `<div class="text-start" style="font-size:14px;">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="fw-bold">Employee:</td><td>${rme(info.name||'')}</td></tr>
                    <tr><td class="fw-bold">Month:</td><td>${rmM[+mo]||mo}</td></tr>
                    <tr><td class="fw-bold">Claimant Amt:</td><td>₹${rmfa(info.claimTotal||0)}</td></tr>
                    <tr><td class="fw-bold">Verified Amt:</td><td>₹${rmfa(info.verifyTotal||0)}</td></tr>
                    <tr><td class="fw-bold">Approved Amt:</td><td class="text-success fw-bold">₹${rmfa(info.approveTotal||0)}</td></tr>
                </table>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="ri-check-double-line"></i> Submit',
            confirmButtonColor: '#0ab39c',
            cancelButtonText: 'Cancel'
        }).then(function(r){
            if(!r.isConfirmed) return;
            const submitClaimUrl = (activeRoutes && activeRoutes.submitClaim) ? activeRoutes.submitClaim : (rmApproverRoutes ? rmApproverRoutes.submitClaim : null);
            if(!submitClaimUrl){
                Swal.fire('Error', 'Submit claim route not configured.', 'error');
                return;
            }
            $b.html('<i class="ri-loader-4-line rotating"></i>');
            $.post(submitClaimUrl, {
                _token: rmCsrf,
                emp_id: id,
                month: mo,
                year_id: yr
            }).done(function(res){
                if(res.Code == '300' || res.success){
                    Swal.fire({icon: 'success', title: 'Submitted!', timer: 1200, showConfirmButton: false});
                    loadRmPending(activeCompany);
                } else {
                    Swal.fire('Failed', res.msg || 'Error', 'error');
                    $b.html('<i class="ri-check-double-line"></i> Submit');
                }
            }).fail(function(){
                Swal.fire('Error', 'Network error', 'error');
                $b.html('<i class="ri-check-double-line"></i> Submit');
            });
        });
    });

    // ── ATTENDANCE ──
    $(document).on('click', '.rm-att-btn', function(ev){
        ev.preventDefault();
        window.open('https://vnrseeds.co.in/public/api/Att_records?empid=' + $(this).data('empid') + '&Month=' + $(this).data('month') + '&Year=' + new Date().getFullYear(), '_blank', 'width=900,height=500');
    });

    // ── PAX EXPANDER ──
    $(document).on('click', '.toggle-pax-btn', function(ev){
        ev.preventDefault();
        const targetId = $(this).data('target');
        const $row = $('#' + targetId);
        if($row.length){
            const isVisible = $row.is(':visible');
            $row.toggle(!isVisible);
            $(this).find('.pax-chevron').toggleClass('ri-arrow-down-s-line', isVisible).toggleClass('ri-arrow-up-s-line', !isVisible);
        }
    });

    // ── DISABLED SUBMIT BUTTON INFO ──
    $(document).on('click', '.disabled-submit-btn', function(ev){
        ev.preventDefault();
        const reason = $(this).data('reason') || 'Cannot submit';
        Swal.fire({
            icon: 'warning',
            title: 'Action Not Allowed',
            text: reason,
            confirmButtonText: 'OK',
            confirmButtonColor: '#405189'
        });
    });

})();
</script>
@endpush