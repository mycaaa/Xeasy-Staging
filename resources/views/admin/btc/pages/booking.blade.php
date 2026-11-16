{{-- BTC-REQ-03 page 5: Admin Booking Queue --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2); @endphp
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'Admin Booking Queue', 'icon' => 'ri-hotel-line', 'sub' => 'Requests with "Admin Booking Required": choose the vendor / hotel, confirm the booking, change or cancel it, and mark the activity completed'])
    <div class="xe-seg mb-2">
        @foreach (['to_book' => 'To book', 'booked' => 'Booked', 'completed' => 'Activity completed', 'cancelled' => 'Cancelled'] as $k => $l)
            <a href="{{ route('btc.booking', ['show' => $k]) }}" class="{{ $show === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
        @endforeach
    </div>
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Request ID</th><th>Employee</th><th>Category</th><th>Purpose / location</th><th>Activity dates</th><th class="text-end">Estimated</th><th>Vendor / hotel</th><th>Booking ref</th><th class="text-end">Booking amount</th><th>Booking date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->case_no }}</td>
                    <td>{{ $c->emp_code }} - {{ $c->emp_name }}<div class="small text-muted">{{ $c->department_name }}</div></td>
                    <td>{{ $c->categoryLabel() }}</td>
                    <td>{{ $c->purpose }}<div class="small text-muted">{{ $c->location }}</div></td>
                    <td class="text-nowrap">{{ optional($c->service_from)->format('d-m-Y') }}{{ $c->service_to ? ' – '.$c->service_to->format('d-m-Y') : '' }}</td>
                    <td class="text-end">{{ $inr($c->estimated_amount ?: $c->quotation_amount) }}</td>
                    <td>{{ $c->vendor_name ?: '-' }}</td>
                    <td>{{ $c->booking_ref ?: '-' }}</td>
                    <td class="text-end">{{ $inr($c->booking_amount) }}</td>
                    <td class="text-nowrap">{{ optional($c->booking_date)->format('d-m-Y') }}</td>
                    <td>{{ $c->statusLabel() }}{{ $c->activity_completed_on ? ' · done '.$c->activity_completed_on->format('d-m-Y') : '' }}{{ $c->booking_status === 'modified' ? ' · changed' : '' }}</td>
                    <td><a class="xe-btn xe-btn-blue" href="{{ route('btc.index', ['case' => $c->id]) }}">{{ $show === 'to_book' ? 'Book' : 'Open' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-3">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    {{ $rows->links() }}
</div></div>
@endsection
