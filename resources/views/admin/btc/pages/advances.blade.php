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
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Request ID</th><th>Employee / vendor</th><th class="text-end">Quotation</th><th class="text-end">Requested</th><th class="text-end">Approved</th><th class="text-end">Actually paid</th><th>Payment ref / Advance ID</th><th class="text-end">TDS on advance</th><th class="text-end">Outstanding</th><th class="text-end">Adjusted against invoice</th><th class="text-end">Refund received</th><th class="text-end">Balance outstanding</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as [$c, $a])
                <tr>
                    <td class="fw-semibold">{{ $c->case_no }}</td>
                    <td>{{ $c->emp_name }}<div class="small text-muted">{{ $c->vendor_name }}</div></td>
                    <td class="text-end">{{ $inr($c->quotation_amount) }}</td>
                    <td class="text-end">{{ $inr($a['requested']) }}</td>
                    <td class="text-end">{{ $inr($a['approved']) }}</td>
                    <td class="text-end">{{ $inr($a['paid']) }}<div class="small text-muted">{{ optional($c->advance_paid_on)->format('d-m-Y') }}</div></td>
                    <td>{{ $c->advance_payment_ref ?: '-' }}{{ $c->advance_activity_id ? ' · ADV '.$c->advance_activity_id : '' }}</td>
                    <td class="text-end">{{ $inr($a['tds']) }}</td>
                    <td class="text-end">{{ $inr($a['outstanding']) }}</td>
                    <td class="text-end">{{ $inr($a['adjusted']) }}</td>
                    <td class="text-end">{{ $inr($a['refund']) }}</td>
                    <td class="text-end fw-semibold {{ $a['balance'] > 0.5 ? 'text-danger' : '' }}">{{ $inr($a['balance']) }}</td>
                    <td>{{ $c->statusPill() }}</td>
                    <td><a class="xe-btn xe-btn-blue" href="{{ route('btc.index', ['case' => $c->id]) }}">{{ $c->status === 'advance_requested' ? 'Pay' : 'Open' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="14" class="text-center text-muted py-3">Nothing here.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">Total</td>
                    @foreach (['requested', 'approved', 'paid'] as $k)<td class="text-end">{{ $inr($totals[$k]) }}</td>@endforeach
                    <td></td>
                    @foreach (['tds', 'outstanding', 'adjusted', 'refund', 'balance'] as $k)<td class="text-end">{{ $inr($totals[$k]) }}</td>@endforeach
                    <td colspan="2"></td></tr></tfoot>
            @endif
        </table>
    </div></section>
</div></div>
@endsection
