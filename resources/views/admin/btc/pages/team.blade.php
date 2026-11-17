{{-- BTC-TEAM-01 (17 Nov 2026): My Team BTC - a reporting manager's register and summary of the team's BTC requests --}}
@extends('layouts.app')
@section('content')
@php
    $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 0);
    $q = fn (array $extra) => route('btc.team', array_merge(request()->except('page'), $extra));
@endphp
<style>
    .btc-kpi { border: 1px solid #e8edf3; border-radius: 12px; padding: 10px 14px; background: #fff; height: 100%; }
    .btc-kpi .v { font-size: 1.2rem; font-weight: 800; color: #1e293b; font-variant-numeric: tabular-nums; }
    .btc-kpi .l { font-size: .72rem; color: #64748b; }
    .btc-small td, .btc-small th { font-size: .78rem; }
    .btc-scope a { border: 1.5px solid #2563eb; color: #2563eb; border-radius: 999px; padding: 4px 14px; font-size: .8rem; font-weight: 600; text-decoration: none; }
    .btc-scope a.on { background: #2563eb; color: #fff; }
    .btc-scope a:not(.on):hover { background: #eff6ff; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
</style>
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'My Team BTC', 'icon' => 'ri-team-line',
        'sub' => ($mgrName ? $mgrName.' · ' : '').'BTC requests of the people who report to you, with stage and who each is waiting for - latest first'])

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btc-scope d-flex gap-2" role="group" aria-label="Team">
            <a href="{{ $q(['scope' => 'direct', 'member' => null]) }}" class="{{ $scope === 'direct' ? 'on' : '' }}" aria-current="{{ $scope === 'direct' ? 'true' : 'false' }}"><i class="ri-user-follow-line"></i> Direct reports</a>
            <a href="{{ $q(['scope' => 'all', 'member' => null]) }}" class="{{ $scope === 'all' ? 'on' : '' }}" aria-current="{{ $scope === 'all' ? 'true' : 'false' }}"><i class="ri-organization-chart"></i> All levels below</a>
        </div>
        <span class="small text-muted">{{ $teamSize }} people in this view · {{ $withCases }} with BTC requests</span>
        @if ($isAdmin)
            <form method="get" class="d-flex gap-1 ms-auto"><input type="hidden" name="scope" value="{{ $scope }}"><input type="number" name="mgr" value="{{ $mgr }}" class="form-control form-control-sm" style="width:120px" aria-label="Manager employee ID" title="Manager employee ID (admin)"><button class="xe-btn xe-btn-blue">Show</button></form>
        @endif
    </div>

    <div class="row g-2 mb-3">
        @foreach ([
            ['Requests', number_format($kpi['requests']), '₹'.$inr($kpi['value']).' value'],
            ['Open', number_format($kpi['open']), $kpi['old'].' raised over 30 days ago'],
            ['Waiting for you / manager', number_format($kpi['with_manager']), 'manager approval'],
            ['Billed, not paid', '₹'.$inr($kpi['pending']), 'with Finance'],
            ['Paid', '₹'.$inr($kpi['paid']), 'to vendors'],
            ['Advance balance', '₹'.$inr($kpi['adv_balance']), 'held by vendors'],
        ] as [$l, $v, $s])
            <div class="col-6 col-md-4 col-xl-2"><div class="btc-kpi"><div class="l">{{ $l }}</div><div class="v">{{ $v }}</div><div class="l">{{ $s }}</div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <section class="xe-section">
                <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-team-line"></i>Summary by {{ $scope === 'all' ? 'direct report and the people below them' : 'team member' }}</span></div>
                <div class="xe-section-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0 btc-small align-middle">
                    <thead><tr><th>{{ $scope === 'all' ? 'Branch (direct report)' : 'Team member' }}</th>@if ($scope === 'all')<th class="num">People</th>@endif<th class="num">Requests</th><th class="num">Open</th><th class="num">With manager</th><th class="num">Returned</th><th class="num">Value ₹</th><th class="num">Billed, not paid ₹</th><th class="num">Paid ₹</th><th class="num">Advance balance ₹</th></tr></thead>
                    <tbody>
                    @forelse ($summary as $r)
                        <tr>
                            <td><a href="{{ $q(['member' => $r['id']]) }}" title="Show only these requests">{{ $r['name'] }}</a></td>
                            @if ($scope === 'all')<td class="num">{{ $r['people'] }}</td>@endif
                            <td class="num">{{ $r['requests'] }}@if ($r['closed_out'])<div class="small text-muted">+{{ $r['closed_out'] }} cancelled</div>@endif</td>
                            <td class="num">{{ $r['open'] }}</td>
                            <td class="num">{!! $r['with_manager'] ? '<b>'.$r['with_manager'].'</b>' : '0' !!}</td>
                            <td class="num">{!! $r['returned'] ? '<span class="text-danger fw-semibold">'.$r['returned'].'</span>' : '0' !!}</td>
                            <td class="num">{{ $inr($r['value']) }}</td>
                            <td class="num">{{ $inr($r['pending']) }}</td>
                            <td class="num">{{ $inr($r['paid']) }}</td>
                            <td class="num">{{ $inr($r['adv_balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-3">Nobody {{ $scope === 'all' ? 'below you' : 'reporting directly to you' }} has raised a BTC request.@if ($scope === 'direct') <a href="{{ $q(['scope' => 'all']) }}">Show all levels below</a>@endif</td></tr>
                    @endforelse
                    </tbody>
                </table></div></div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="xe-section">
                <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-route-line"></i>By stage</span></div>
                <div class="xe-section-body p-0"><table class="table table-sm table-hover mb-0 btc-small">
                    <thead><tr><th>Stage</th><th>Waiting for</th><th class="num">Requests</th><th class="num">Value ₹</th></tr></thead>
                    <tbody>
                    @forelse ($stages as $st)
                        <tr><td><a href="{{ $q(['status' => $st['key']]) }}">{{ $st['label'] }}</a></td><td class="text-muted">{{ in_array($st['key'], ['paid', 'closed', 'cancelled', 'rejected'], true) ? '-' : $st['with'] }}</td><td class="num">{{ $st['count'] }}</td><td class="num">{{ $inr($st['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No requests.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>
    </div>

    <form method="get" class="row g-2 align-items-end mb-2">
        <input type="hidden" name="scope" value="{{ $scope }}">@if ($isAdmin)<input type="hidden" name="mgr" value="{{ $mgr }}">@endif
        <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Request ID, employee, vendor, bill, booking" aria-label="Search"></div>
        <div class="col-md-2"><select name="member" class="form-select form-select-sm" aria-label="Team member"><option value="">Whole team</option>@foreach ($summary as $r)<option value="{{ $r['id'] }}" @selected((string) request('member') === (string) $r['id'])>{{ $r['name'] }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="Stage"><option value="">All stages</option>@foreach (config('btc.statuses') as $k => $l)@continue($k === 'draft')<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="show" class="form-select form-select-sm" aria-label="Open or all"><option value="">Open and finished</option><option value="open" @selected(request('show') === 'open')>Open only</option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="xe-btn xe-btn-blue">Filter</button><a class="xe-btn" href="{{ route('btc.team', ['scope' => $scope] + ($isAdmin ? ['mgr' => $mgr] : [])) }}">Clear</a><a class="xe-btn xe-btn-green" href="{{ route('btc.team.export', ['scope' => $scope] + ($isAdmin ? ['mgr' => $mgr] : [])) }}"><i class="ri-file-excel-2-line"></i>Excel</a></div>
    </form>
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Request ID</th><th>Requester</th><th>Category / route</th><th>Status</th><th>Pending with</th><th>Vendor</th><th class="num">Advance req / paid / balance</th><th>Invoice</th><th class="num">Amount ₹</th><th>Raised</th></tr></thead>
            <tbody>
            @forelse ($rows as $c)
                @php $a = $c->advanceFigures(); $v = $via($c); @endphp
                <tr>
                    <td><a href="{{ route('btc.index', ['case' => $c->id]) }}" class="fw-semibold">{{ $c->case_no }}</a></td>
                    <td>{{ $c->emp_code }} - {{ $c->emp_name }}<div class="small text-muted">{{ $c->department_name }}{{ $v ? ' · via '.$v : '' }}</div></td>
                    <td>{{ $c->categoryLabel() }}<div class="small text-muted">{{ config("btc.route_choices.{$c->route}.0", $c->route) }}</div></td>
                    <td><span class="badge {{ in_array($c->status, ['returned', 'rejected'], true) ? 'bg-danger-subtle text-danger' : (in_array($c->status, ['paid', 'closed'], true) ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary') }}">{{ $c->statusLabel() }}</span></td>
                    <td>{{ in_array($c->status, ['closed', 'cancelled', 'rejected'], true) ? '-' : $c->pendingWith() }}</td>
                    <td>{{ $c->vendor_name ?: '-' }}</td>
                    <td class="num">{{ $a['requested'] > 0 ? $inr($a['requested']).' / '.$inr($a['paid']).' / '.$inr($a['balance']) : '-' }}</td>
                    <td>{{ $c->bill_no ?: '-' }}</td>
                    <td class="num">{{ $inr($c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount) }}</td>
                    <td class="text-nowrap">{{ optional($c->created_at)->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-3">No requests match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    {{ $rows->links() }}
</div></div>
@endsection
