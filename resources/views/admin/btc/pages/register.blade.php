{{-- BTC-REQ-03 page 4: BTC Request Register --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2); @endphp
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => 'BTC Request Register', 'icon' => 'ri-list-check-2', 'sub' => 'Every request with its unique ID, origin route, owner, stage and who it is pending with - latest first'])
    <form method="get" class="row g-2 align-items-end mb-2">
        <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Request ID, employee, vendor, bill, booking, Nova doc" aria-label="Search"></div>
        <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="Status"><option value="">All stages</option>@foreach (config('btc.statuses') as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="route" class="form-select form-select-sm" aria-label="Route"><option value="">All routes</option>@foreach (config('btc.route_choices') as $k => $l)<option value="{{ $k }}" @selected(request('route') === $k)>{{ $l[0] }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="department_id" class="form-select form-select-sm" aria-label="Department"><option value="">All departments</option>@foreach ($depts as $id => $n)<option value="{{ $id }}" @selected((string) request('department_id') === (string) $id)>{{ $n }}</option>@endforeach</select></div>
        <div class="col-md-3 d-flex gap-2"><button class="xe-btn xe-btn-blue">Filter</button>@if (auth()->user()->can('btc.export') || auth()->user()->can('btc.admin'))<a class="xe-btn xe-btn-green" href="{{ route('btc.register.export', request()->query()) }}"><i class="ri-file-excel-2-line"></i>Excel</a>@endif</div>
    </form>
    {{-- BTC-GRID-01 (28 Nov 2026): advance requested / paid / balance, booking and invoice each in their own columns --}}
    <section class="xe-section"><div class="btc-grid-wrap">
        <table class="btc-grid">
            <thead>
                <tr class="btc-grp"><th class="btc-grp-none" colspan="5"></th><th class="gs" colspan="2">Booking</th><th class="gs" colspan="3">Advance ₹</th><th class="gs" colspan="2">Invoice</th><th class="btc-grp-none gs"></th></tr>
                <tr><th>Request ID</th><th>Owner</th><th>Category</th><th>Status</th><th>Pending with</th>
                    <th class="gs">Reference</th><th class="num">Amount ₹</th>
                    <th class="num gs">Requested</th><th class="num">Paid</th><th class="num">Balance</th>
                    <th class="gs">No.</th><th class="num">Amount ₹</th><th class="gs">Raised</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $c)
                @php $a = $c->advanceFigures(); $amt = $c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount; @endphp
                <tr>
                    <td class="nw"><a href="{{ route('btc.index', ['case' => $c->id]) }}" class="fw-semibold">{{ $c->case_no }}</a></td>
                    <td>{{ $c->emp_code }} - {{ $c->emp_name }}<span class="sub">{{ $c->department_name }}</span></td>
                    <td>{{ $c->categoryLabel() }}<span class="sub">{{ config("btc.route_choices.{$c->route}.0", $c->route) }}</span></td>
                    <td class="nw">{{ $c->statusPill() }}</td>
                    <td>{{ in_array($c->status, ['closed', 'cancelled', 'rejected'], true) ? '-' : $c->pendingWith() }}</td>
                    <td class="gs">{{ $c->booking_ref ?: ($c->route === 'admin_booking' ? ($c->booking_status ? ucfirst($c->booking_status) : 'Not booked') : '-') }}</td>
                    <td class="num {{ (float) $c->booking_amount > 0 ? '' : 'zero' }}">{{ (float) $c->booking_amount > 0 ? $inr($c->booking_amount) : '-' }}</td>
                    @if ($a['requested'] > 0)
                        <td class="num gs">{{ $inr($a['requested']) }}</td>
                        <td class="num {{ $a['paid'] > 0 ? '' : 'zero' }}">{{ $a['paid'] > 0 ? $inr($a['paid']) : '-' }}</td>
                        <td class="num {{ $a['balance'] > 0.5 ? 'due' : 'zero' }}">{{ $a['balance'] > 0.5 ? $inr($a['balance']) : '-' }}</td>
                    @else
                        <td class="num gs zero">-</td><td class="num zero">-</td><td class="num zero">-</td>
                    @endif
                    <td class="gs nw">{{ $c->bill_no ?: '-' }}@if ($c->nova_document_id)<span class="sub">Nova {{ $c->nova_document_id }}</span>@endif</td>
                    <td class="num {{ $amt ? '' : 'zero' }}">{{ $amt ? $inr($amt) : '-' }}@if (! $c->bill_amount && $amt)<span class="sub">{{ $c->booking_amount ? 'booking' : ($c->quotation_amount ? 'quotation' : 'estimate') }}</span>@endif</td>
                    <td class="gs nw">{{ optional($c->created_at)->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="text-center text-muted py-3">No requests match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    @include('admin.btc.pages._pager')
</div></div>
@endsection
