{{-- BTC-REQ-03 page 5: Admin Booking Queue --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2); @endphp
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => 'Admin Booking Queue', 'icon' => 'ri-hotel-line', 'sub' => 'Requests with "Admin Booking Required": choose the vendor / hotel, confirm the booking, change or cancel it, mark the activity completed and record that the physical bill was handed over to DMS'])
    <div class="xe-seg mb-2">
        @foreach (['to_book' => 'To book', 'booked' => 'Booked', 'completed' => 'Activity completed (bill to DMS)', 'to_dms' => 'Bill handed to DMS', 'cancelled' => 'Cancelled'] as $k => $l)
            <a href="{{ route('btc.booking', ['show' => $k]) }}" class="{{ $show === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
        @endforeach
    </div>
    {{-- BTC-GRID-01 (28 Nov 2026): what is asked (journey / stay / activity) and what was booked, in grouped columns --}}
    <section class="xe-section"><div class="btc-grid-wrap">
        <table class="btc-grid">
            <thead>
                <tr class="btc-grp"><th class="btc-grp-none" colspan="3"></th><th class="gs" colspan="3">Request</th><th class="gs" colspan="4">Booking</th><th class="btc-grp-none gs" colspan="2"></th></tr>
                <tr><th>Request ID</th><th>Employee</th><th>Category</th>
                    <th class="gs">Purpose</th><th>Journey / dates</th><th class="num">Estimated ₹</th>
                    <th class="gs">Vendor</th><th>Reference</th><th class="num">Amount ₹</th><th>Booked on</th>
                    <th class="gs">Status</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $c)
                @php
                    $trip = in_array($c->category, (array) config('btc.trip_categories', []), true) && $c->travel_from;
                    $d = fn ($x) => $x ? \Carbon\Carbon::parse($x)->format('d-m-Y') : '';
                @endphp
                <tr>
                    <td class="fw-semibold nw">{{ $c->case_no }}</td>
                    <td>{{ $c->emp_code }} - {{ $c->emp_name }}<span class="sub">{{ $c->department_name }}</span></td>
                    <td>{{ $c->categoryLabel() }}@if ($c->pax_count > 1)<span class="sub">{{ $c->pax_count }} pax</span>@endif</td>
                    <td class="gs">{{ $c->purpose }}<span class="sub">{{ $c->location }}</span></td>
                    @if ($trip)
                        <td>{{ $c->travel_from }} → {{ $c->travel_to }}<span class="sub nw">{{ $d($c->travel_date) }}{{ $c->trip_type === 'round_trip' && $c->return_date ? ' · return '.$d($c->return_date) : '' }}{{ $c->pickup_time ?? null ? ' · pickup '.$c->pickup_time : '' }}{{ $c->vehicle_type ?? null ? ' · '.$c->vehicle_type : '' }}</span></td>
                    @else
                        <td class="nw">{{ $d($c->service_from) }}{{ $c->service_to ? ' – '.$d($c->service_to) : '' }}<span class="sub">{{ $c->category === 'hotel' ? 'stay' : 'activity' }}</span></td>
                    @endif
                    <td class="num">{{ $inr($c->estimated_amount ?: $c->quotation_amount) }}</td>
                    <td class="gs">{{ $c->vendor_name ?: '-' }}</td>
                    <td class="nw">{{ $c->booking_ref ?: '-' }}@if ($c->pnr)<span class="sub">PNR {{ $c->pnr }}{{ $c->return_pnr ?? null ? ' / '.$c->return_pnr : '' }}</span>@endif</td>
                    <td class="num {{ (float) $c->booking_amount > 0 ? '' : 'zero' }}">{{ $c->booking_amount !== null ? $inr($c->booking_amount) : '-' }}</td>
                    <td class="nw">{{ optional($c->booking_date)->format('d-m-Y') ?: '-' }}</td>
                    <td class="gs">{{ $c->statusPill() }}<span class="sub">{{ $c->activity_completed_on ? 'done '.$c->activity_completed_on->format('d-m-Y') : '' }}{{ $c->booking_status === 'modified' ? ' · changed' : '' }}{{ $c->bill_forwarded_dms_on ? ' · bill to DMS '.\Carbon\Carbon::parse($c->bill_forwarded_dms_on)->format('d-m-Y') : '' }}</span></td>
                    <td><a class="xe-btn xe-btn-blue" href="{{ route('btc.index', ['case' => $c->id]) }}">{{ $show === 'to_book' ? 'Book' : 'Open' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-3">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    @include('admin.btc.pages._pager')
</div></div>
@endsection
