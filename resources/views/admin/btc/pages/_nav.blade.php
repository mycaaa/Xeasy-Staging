{{-- BTC-REQ-03 (16 Nov 2026): BTC menu architecture - one bar of pages, each shown only with its permission.
     BTC-UI-01 (26 Nov 2026, testing point 22): the pages in groups; the bill pipeline as numbered steps with the bills
     waiting at each (the same queues as before - only the presentation changed) --}}
@php
    $u = auth()->user();
    $can = fn ($p) => $u && ($u->can('btc.admin') || collect(explode('|', $p))->contains(fn ($x) => $u->can($x)));
    $groups = [
        'Requests' => [
            ['btc.dashboard', 'Dashboard', 'ri-dashboard-3-line', null],   // BTC-DASH-ROLE-01: everyone, figures by role
            ['btc.index', 'BTC Request', 'ri-file-list-3-line', null],   // BTC-REQ-14
            ['btc.team', 'My Team BTC', 'ri-team-line', 'team'],   // BTC-TEAM-01: shown to reporting managers
            ['btc.register', 'Request Register', 'ri-list-check-2', 'btc.register|btc.audit'],
            ['btc.exceptions', 'Exception Queue', 'ri-error-warning-line', 'btc.exceptions|btc.audit|btc.finance|btc.checker|btc.bill_upload|btc.verifier|btc.fin_approver'],   // BTC-EXC-01
        ],
        'Booking & advances' => [
            ['btc.booking', 'Admin Booking Queue', 'ri-hotel-line', 'btc.booking'],
            ['btc.advances', 'Advances', 'ri-hand-coin-line', 'btc.advance|btc.finance'],
            ['btc.advance-approval', 'Finance Advance Approval', 'ri-hand-coin-line', 'btc.fin_approver|btc.advance|btc.audit'],
        ],
        'Setup' => [
            ['btc.access', 'Roles & Access', 'ri-shield-user-line', 'btc.access'],
            ['btc.settings', 'BTC Settings', 'ri-settings-3-line', 'btc.configure'],   // BTC-CONF-01
        ],
    ];
    // BTC-V2 / BTC-SCAN-01 / BTC-FLOW-18: Scanner -> Classification -> Punch Screen (Focus Export) -> Finance level
    // verification -> Payment approval -> Treasury; the number shows the bills waiting at the step
    $pipe = [
        ['btc.scanner', 'Scanner', 'btc.dms|btc.bill_upload|btc.finance', []],
        ['btc.classification', 'Classification', 'btc.dms|btc.bill_upload|btc.finance', ['scanned']],
        ['btc.punch', 'Punch Screen', 'btc.bill_upload|btc.finance', ['bill_submitted']],
        ['btc.verifier', 'Finance Verification', 'btc.verifier|btc.audit', ['extracted']],
        ['btc.approval', 'Payment Approval', 'btc.fin_approver|btc.audit', ['focus_done', 'finance_verified']],
        ['btc.treasury', 'Treasury Payment', 'btc.treasury|btc.audit', ['fin_approved']],
    ];
    $pipe = array_values(array_filter($pipe, fn ($p) => $can($p[2])));
    $waiting = [];
    if ($pipe) {
        try {
            $waiting = \App\Models\BtcCase::where('company_id', (int) session('company_id', 1))->where('on_hold', false)
                ->whereIn('status', collect($pipe)->pluck(3)->flatten()->all())->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
        } catch (\Throwable $e) {
            $waiting = [];
        }
    }
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/btc-ui.css') }}?v=20261126c">
<div class="xe-topbar">
    <div>
        <h1 class="xe-title"><i class="{{ $icon ?? 'ri-bill-line' }}"></i>{{ $title }}</h1>
        @isset($sub)<div class="xe-sub">{{ $sub }}</div>@endisset
    </div>
    @isset($actionsView)<div class="xe-actions">@include($actionsView)</div>@endisset
</div>
<nav class="btc-nav" aria-label="BTC pages">
    @foreach ($groups as $gName => $pages)
        @php
            $shown = array_filter($pages, fn ($p) => $p[3] === null || ($p[3] === 'team' ? \App\Http\Controllers\admin\BtcPagesController::hasTeam() || $can('btc.admin') : $can($p[3])));
        @endphp
        @if ($shown)
            <div class="btc-nav-group">
                <span class="btc-nav-name">{{ $gName }}</span>
                <div class="btc-nav-row">
                    @foreach ($shown as [$route, $label, $ic])
                        <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'active' : '' }}" @if (request()->routeIs($route)) aria-current="page" @endif><i class="{{ $ic }}"></i>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        @endif
        @if ($loop->first && $pipe)
            <div class="btc-nav-group">
                <span class="btc-nav-name">Bill to payment</span>
                <div class="btc-pipe">
                    @foreach ($pipe as $i => [$route, $label, , $sts])
                        @php $n = array_sum(array_map(fn ($s) => (int) ($waiting[$s] ?? 0), $sts)); @endphp
                        <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'active' : '' }}" @if (request()->routeIs($route)) aria-current="page" @endif
                           title="{{ $sts ? $n.' at this step' : '' }}"><span class="btc-step-no">{{ $i + 1 }}</span>{{ $label }}@if ($n)<span class="btc-step-n" aria-label="{{ $n }} at this step">{{ $n }}</span>@endif</a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
    @unless (request()->routeIs('btc.index'))   {{-- the BTC Request page has its own "New BTC case" button --}}
    <a href="{{ route('btc.index') }}#new" class="xe-btn xe-btn-main btc-nav-new"><i class="ri-add-line"></i>New BTC Requirement</a>
    @endunless
</nav>
@if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if (isset($errors) && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
