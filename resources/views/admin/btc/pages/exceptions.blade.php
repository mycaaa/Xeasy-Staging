{{-- BTC-EXC-01 (17 Nov 2026): Validation & Exception Queue (BTC requirement item 12) --}}
@extends('layouts.app')
@section('content')
@php($q = fn (array $x) => route('btc.exceptions', array_merge(request()->except('page'), $x)))
<style>
    .btc-exc-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
    .btc-exc-tiles a { display: block; border: 1px solid #e8edf3; border-radius: 12px; padding: 8px 12px; background: #fff; color: #1e293b; text-decoration: none; }
    .btc-exc-tiles a.on { border-color: #2563eb; box-shadow: 0 0 0 2px #dbeafe; }
    .btc-exc-tiles a.hot b { color: #b91c1c; }
    .btc-exc-tiles b { font-size: 1.25rem; font-variant-numeric: tabular-nums; }
    .btc-exc-tiles span { display: block; font-size: .75rem; color: #64748b; }
</style>
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => 'Exception Queue', 'icon' => 'ri-error-warning-line',
        'sub' => 'Open BTC cases that need a second look - clear each with a remark, or fix the case. Latest first.'])
    @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

    <div class="btc-exc-tiles mb-3">
        <a href="{{ $q(['type' => null]) }}" class="{{ request('type') ? '' : 'on' }}"><b>{{ $counts->sum('open') }}</b><span>All open exceptions</span></a>
        @foreach ($counts as $k => $c)
            <a href="{{ $q(['type' => $k]) }}" class="{{ request('type') === $k ? 'on' : '' }} {{ $c['open'] ? 'hot' : '' }}"><b>{{ $c['open'] }}</b><span>{{ $c['label'] }}@if ($c['reviewed']) · {{ $c['reviewed'] }} reviewed @endif</span></a>
        @endforeach
    </div>

    <div class="d-flex gap-2 mb-2 align-items-center">
        <div class="btn-group btn-group-sm" role="group" aria-label="Show">
            <a class="btn {{ request('show', 'open') === 'open' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $q(['show' => 'open']) }}">Open</a>
            <a class="btn {{ request('show') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $q(['show' => 'all']) }}">Open and reviewed</a>
        </div>
        @if ($canExport)<a class="xe-btn xe-btn-green ms-auto" href="{{ route('btc.exceptions.export', request()->query()) }}"><i class="ri-file-excel-2-line"></i>Excel</a>@endif
    </div>

    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Request ID</th><th>Employee</th><th>Stage</th><th>Exception</th><th>Detail</th><th>Review</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                @php($c = $r['case'])
                <tr>
                    <td><a class="fw-semibold" href="{{ route('btc.index', ['case' => $c->id]) }}">{{ $c->case_no }}</a><div class="small text-muted">{{ $c->categoryLabel() }}</div></td>
                    <td>{{ $c->emp_code }} - {{ $c->emp_name }}<div class="small text-muted">{{ $c->vendor_name }}</div></td>
                    <td>{{ $c->statusPill() }}</td>
                    <td><span class="badge bg-danger-subtle text-danger">{{ $r['label'] }}</span></td>
                    <td class="small">{{ $r['detail'] }}</td>
                    <td class="small" style="min-width:240px">
                        @if ($r['review'])
                            <span class="text-success">Reviewed</span> - {{ $r['review']->remark }}<div class="text-muted">{{ $r['review']->reviewer_name }}, {{ substr((string) $r['review']->created_at, 0, 10) }}</div>
                        @elseif ($canReview)
                            <form method="post" action="{{ route('btc.exceptions.review') }}" class="d-flex gap-1">@csrf
                                <input type="hidden" name="case_id" value="{{ $c->id }}"><input type="hidden" name="type" value="{{ $r['type'] }}">
                                <input name="remark" class="form-control form-control-sm" placeholder="Why it is in order" required minlength="5" aria-label="Review remark">
                                <button class="btn btn-sm btn-outline-success">Clear</button>
                            </form>
                        @else
                            <span class="text-muted">Open</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No {{ request('show', 'open') === 'open' ? 'open ' : '' }}exceptions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
</div></div>
@endsection
