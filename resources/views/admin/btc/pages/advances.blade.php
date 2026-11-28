{{-- BTC-REQ-03 page 6: Advance Request & Processing --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => $n === null ? '-' : number_format((float) $n, 2); @endphp
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => 'Advance Request & Processing', 'icon' => 'ri-hand-coin-line', 'sub' => 'The amount requested is not the amount paid: each advance in its separate figures'])
    <div class="xe-seg mb-2">
        @foreach (['to_pay' => 'To pay', 'outstanding' => 'Outstanding', 'settled' => 'Settled', 'all' => 'All'] as $k => $l)
            <a href="{{ route('btc.advances', ['show' => $k]) }}" class="{{ $show === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
        @endforeach
    </div>
    {{-- BTC-GRID-01 (28 Nov 2026): the advance (asked / approved / paid) and its settlement in grouped columns --}}
    <section class="xe-section"><div class="btc-grid-wrap">
        <table class="btc-grid" data-pager>
            <thead>
                <tr class="btc-grp"><th class="btc-grp-none" colspan="3"></th><th class="gs" colspan="4">Advance ₹</th><th class="gs" colspan="5">Settlement ₹</th><th class="btc-grp-none gs" colspan="2"></th></tr>
                <tr><th>Request ID</th><th>Employee / vendor</th><th class="num">Quotation ₹</th>
                    <th class="num gs">Requested</th><th class="num">Approved</th><th class="num">Paid</th><th>Payment ref</th>
                    <th class="num gs">TDS</th><th class="num">Outstanding</th><th class="num">Adjusted</th><th class="num">Refund</th><th class="num">Balance</th>
                    <th class="gs">Status</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($rows as [$c, $a])
                <tr>
                    <td class="fw-semibold nw">{{ $c->case_no }}</td>
                    <td>{{ $c->emp_name }}<span class="sub">{{ $c->vendor_name }}</span></td>
                    <td class="num">{{ $inr($c->quotation_amount) }}</td>
                    <td class="num gs">{{ $inr($a['requested']) }}</td>
                    <td class="num">{{ $inr($a['approved']) }}</td>
                    <td class="num {{ $a['paid'] > 0 ? '' : 'zero' }}">{{ $inr($a['paid']) }}<span class="sub">{{ optional($c->advance_paid_on)->format('d-m-Y') }}</span></td>
                    <td class="small">{{ $c->advance_payment_ref ?: '-' }}@if ($c->advance_activity_id)<span class="sub">ADV {{ $c->advance_activity_id }}</span>@endif</td>
                    <td class="num gs">{{ $inr($a['tds']) }}</td>
                    <td class="num">{{ $inr($a['outstanding']) }}</td>
                    <td class="num">{{ $inr($a['adjusted']) }}</td>
                    <td class="num">{{ $inr($a['refund']) }}</td>
                    <td class="num {{ $a['balance'] > 0.5 ? 'due' : 'zero' }}">{{ $inr($a['balance']) }}</td>
                    <td class="gs nw">{{ $c->statusPill() }}</td>
                    <td><a class="xe-btn xe-btn-blue" href="{{ route('btc.index', ['case' => $c->id]) }}">{{ $c->status === 'advance_requested' ? 'Pay' : 'Open' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="14" class="text-center text-muted py-3">Nothing here.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot><tr><td colspan="3" class="text-end">Total</td>
                    @foreach (['requested', 'approved', 'paid'] as $k)<td class="num{{ $k === 'requested' ? ' gs' : '' }}">{{ $inr($totals[$k]) }}</td>@endforeach
                    <td></td>
                    @foreach (['tds', 'outstanding', 'adjusted', 'refund', 'balance'] as $k)<td class="num{{ $k === 'tds' ? ' gs' : '' }}">{{ $inr($totals[$k]) }}</td>@endforeach
                    <td colspan="2" class="gs"></td></tr></tfoot>
            @endif
        </table>
    </div></section>
</div></div>
@endsection
