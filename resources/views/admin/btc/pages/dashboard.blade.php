{{-- BTC-REQ-03 page 1: BTC Dashboard --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => '₹'.number_format((float) $n, 0); @endphp
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => 'BTC Dashboard', 'icon' => 'ri-dashboard-3-line', 'sub' => 'Live BTC requests you are allowed to see'])
    <div class="alert alert-light py-2 small mb-3"><i class="ri-eye-line me-1"></i>Showing: <b>{{ $scope['label'] }}</b></div>

    <div class="row g-2 mb-3">
        @foreach ([
            ['Total BTC value', $inr($kpi['total_value']), $kpi['requests'].' requests'],
            ['Invoices', number_format($kpi['invoices']), 'requests with a bill'],
            ['Pending amount', $inr($kpi['pending_amount']), 'billed, not yet paid'],
            ['Paid amount', $inr($kpi['paid_amount']), 'paid to vendors'],
            ['Advances outstanding', $inr($kpi['advance_outstanding']), $kpi['advance_cases'].' requests'],
        ] as [$l, $v, $s])
            <div class="col-6 col-md"><div class="btc-kpi"><div class="l">{{ $l }}</div><div class="v">{{ $v }}</div><div class="l">{{ $s }}</div></div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="xe-section">
                <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-time-line"></i>Ageing of open requests</span></div>
                <div class="xe-section-body p-0"><table class="table table-sm mb-0 btc-small">
                    <thead><tr><th>Age (since raised)</th><th class="text-end">Requests</th><th class="text-end">Value</th></tr></thead>
                    <tbody>@foreach ($ageing as $b => $x)<tr><td>{{ $b }}</td><td class="text-end">{{ $x['count'] }}</td><td class="text-end">{{ $inr($x['value']) }}</td></tr>@endforeach</tbody>
                </table></div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="xe-section">
                <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-error-warning-line"></i>Exceptions</span></div>
                <div class="xe-section-body p-0"><table class="table table-sm mb-0 btc-small">
                    <tbody>@foreach ($exceptions as [$l, $n, $url])
                        <tr><td>{{ $l }}</td><td class="text-end">@if ($n)@if ($links)<a href="{{ $url }}" class="fw-semibold text-danger">{{ $n }}</a>@else<span class="fw-semibold text-danger">{{ $n }}</span>@endif@else<span class="text-muted">0</span>@endif</td></tr>
                    @endforeach</tbody>
                </table></div>
            </section>
        </div>
        @foreach (['Department-wise' => $byDept, 'Vendor-wise' => $byVendor] as $title => $list)
            <div class="col-lg-6">
                <section class="xe-section">
                    <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-bar-chart-horizontal-line"></i>{{ $title }} (top 15 by value)</span></div>
                    <div class="xe-section-body p-0"><table class="table table-sm mb-0 btc-small">
                        <thead><tr><th>{{ $title === 'Department-wise' ? 'Department' : 'Vendor' }}</th><th class="text-end">Requests</th><th class="text-end">Value</th><th class="text-end">Paid</th><th class="text-end">Pending</th></tr></thead>
                        <tbody>
                        @forelse ($list as $r)
                            <tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['count'] }}</td><td class="text-end">{{ $inr($r['value']) }}</td><td class="text-end">{{ $inr($r['paid']) }}</td><td class="text-end">{{ $inr($r['pending']) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No BTC requests yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </section>
            </div>
        @endforeach
        <div class="col-12">
            <section class="xe-section">
                <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-flow-chart"></i>Requests by stage</span></div>
                <div class="xe-section-body btc-stagebar">
                    @forelse ($byStatus as $st => $n)
                        {{-- BTC-UI-01: each stage as its status pill with the count --}}
                        @php $pill = '<span class="btc-pill btc-pill--'.e(config("btc.status_tones.$st", 'stop')).'">'.e(config("btc.statuses.$st", $st)).'</span><span class="btc-stage-n">'.(int) $n.'</span>'; @endphp
                        @if ($links)<a href="{{ route('btc.register', ['status' => $st]) }}">{!! $pill !!}</a>@else<span class="btc-stage">{!! $pill !!}</span>@endif
                    @empty
                        <span class="text-muted small">No BTC requests yet.</span>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    <div class="small text-muted">{{ \App\Support\ReportStamp::text() }}</div>
</div></div>
@endsection
