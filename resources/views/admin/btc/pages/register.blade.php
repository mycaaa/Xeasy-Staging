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
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Request ID</th><th>Origin route</th><th>Owner</th><th>Category</th><th>Status</th><th>Pending with</th><th>Booking</th><th class="text-end">Advance req / paid / balance</th><th>Invoice</th><th class="text-end">Amount</th><th>Raised</th></tr></thead>
            <tbody>
            @forelse ($rows as $c)
                @php $a = $c->advanceFigures(); @endphp
                <tr>
                    <td><a href="{{ route('btc.index', ['case' => $c->id]) }}" class="fw-semibold">{{ $c->case_no }}</a></td>
                    <td>{{ config("btc.route_choices.{$c->route}.0", $c->route) }}</td>
                    <td>{{ $c->emp_code }} - {{ $c->emp_name }}<div class="small text-muted">{{ $c->department_name }}</div></td>
                    <td>{{ $c->categoryLabel() }}</td>
                    <td>{{ $c->statusPill() }}</td>
                    <td>{{ $c->pendingWith() }}</td>
                    <td>{{ $c->booking_ref ? $c->booking_ref.' · '.$inr($c->booking_amount) : ($c->route === 'admin_booking' ? ($c->booking_status ?: '-') : '-') }}</td>
                    <td class="text-end">{{ $a['requested'] > 0 ? $inr($a['requested']).' / '.$inr($a['paid']).' / '.$inr($a['balance']) : '-' }}</td>
                    <td>{{ $c->bill_no ?: '-' }}@if ($c->nova_document_id)<div class="small text-muted">Nova {{ $c->nova_document_id }}</div>@endif</td>
                    <td class="text-end">{{ $inr($c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount) }}</td>
                    <td class="text-nowrap">{{ optional($c->created_at)->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted py-3">No requests match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    {{ $rows->links() }}
</div></div>
@endsection
