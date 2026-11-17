{{-- BTC-REQ-03 (16 Nov 2026): BTC menu architecture - one bar of pages, each shown only with its permission --}}
@php
    $u = auth()->user();
    $can = fn ($p) => $u && ($u->can('btc.admin') || collect(explode('|', $p))->contains(fn ($x) => $u->can($x)));
    $pages = [
        ['btc.dashboard', 'BTC Dashboard', 'ri-dashboard-3-line', 'btc.dashboard|btc.audit'],
        ['btc.index', 'BTC Request', 'ri-file-list-3-line', null],   // BTC-REQ-14
        ['btc.team', 'My Team BTC', 'ri-team-line', 'team'],   // BTC-TEAM-01: shown to reporting managers
        ['btc.register', 'Request Register', 'ri-list-check-2', 'btc.register|btc.audit'],
        ['btc.exceptions', 'Exception Queue', 'ri-error-warning-line', 'btc.exceptions|btc.audit|btc.finance|btc.checker'],   // BTC-EXC-01
        ['btc.booking', 'Admin Booking Queue', 'ri-hotel-line', 'btc.booking'],
        ['btc.advances', 'Advances', 'ri-hand-coin-line', 'btc.advance|btc.finance'],
        ['btc.batches', 'Payment Batches', 'ri-bank-line', 'btc.treasury|btc.treasury_checker|btc.audit'],          // BTC-BATCH-01
        ['btc.vendor-banks', 'Vendor Bank Accounts', 'ri-bank-card-line', 'btc.treasury|btc.treasury_checker|btc.finance|btc.audit'],   // BTC-BANK-01
        ['btc.invoices', 'Nova Invoice Inbox', 'ri-inbox-archive-line', 'btc.invoices'],
        ['btc.matching', 'Request / Invoice Matching', 'ri-git-merge-line', 'btc.invoices|btc.verifier'],
        ['btc.integration', 'Integration Monitoring', 'ri-pulse-line', 'btc.integration'],
        ['btc.access', 'Roles & Access', 'ri-shield-user-line', 'btc.access'],
        ['btc.settings', 'BTC Settings', 'ri-settings-3-line', 'btc.configure'],   // BTC-CONF-01
    ];
@endphp
<div class="xe-topbar">
    <div>
        <h1 class="xe-title"><i class="{{ $icon ?? 'ri-bill-line' }}"></i>{{ $title }}</h1>
        @isset($sub)<div class="xe-sub">{{ $sub }}</div>@endisset
    </div>
    @isset($actionsView)<div class="xe-actions">@include($actionsView)</div>@endisset
</div>
<nav class="xe-seg mb-3" aria-label="BTC pages">
    @foreach ($pages as [$route, $label, $ic, $perm])
        @if ($perm === null || ($perm === 'team' ? \App\Http\Controllers\admin\BtcPagesController::hasTeam() || $can('btc.admin') : $can($perm)))
            <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'active' : '' }}"><i class="{{ $ic }}"></i>{{ $label }}</a>
        @endif
    @endforeach
    <a href="{{ route('btc.index') }}#new" class="text-primary"><i class="ri-add-line"></i>New BTC Requirement</a>
</nav>
@if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if (isset($errors) && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
