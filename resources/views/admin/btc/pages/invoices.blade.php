{{-- BTC-REQ-03 pages 7 and 8: Nova Invoice Inbox, Request / Invoice Matching --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => $n === null ? '' : number_format((float) $n, 2); @endphp
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', $matching
        ? ['title' => 'Request / Invoice Matching', 'icon' => 'ri-git-merge-line', 'sub' => 'Imported invoices not yet linked to their request: suggested request with the matching confidence, manual match, mismatch resolution']
        : ['title' => 'Nova Invoice Inbox', 'icon' => 'ri-inbox-archive-line', 'sub' => 'Invoices received from Nova (DMS), in the six conditions below'])

    @if (! $matching)
        <div class="row g-2 mb-2">
            @foreach (\App\Services\Btc\NovaInbox::STATES as $k => $l)
                <div class="col-6 col-md-2">
                    <a href="{{ route('btc.invoices', ['state' => $k]) }}" class="d-block border rounded p-2 text-decoration-none h-100 {{ $state === $k ? 'border-primary bg-primary-subtle' : 'bg-white' }}">
                        <div class="small text-muted">{{ $loop->iteration }}. {{ $l }}</div>
                        <div class="fs-5 fw-bold {{ in_array($k, ['import_failed', 'not_at_nova'], true) && $counts[$k] ? 'text-danger' : 'text-dark' }}">{{ $counts[$k] }}</div>
                    </a>
                </div>
            @endforeach
        </div>
        <details class="mb-2"><summary class="small text-primary">Import a CSV export from Nova</summary>
            <form method="post" action="{{ route('btc.invoices.import') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center mt-2">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" class="form-control form-control-sm" style="max-width:320px" required aria-label="CSV file">
                <button class="xe-btn xe-btn-blue">Import</button>
                <span class="small text-muted">Columns: document_id, status (received / digitised / transferred), invoice_no, invoice_date, vendor_name, vendor_gstin, taxable_amount, tax_amount, gross_amount, document_url, received_on, request_id (optional)</span>
            </form>
        </details>
    @endif
    @if ($state !== 'not_at_nova')
        <form method="get" class="d-flex gap-2 mb-2">
            @unless ($matching)<input type="hidden" name="state" value="{{ $state }}">@endunless
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:320px" placeholder="Document ID, invoice no., vendor, GSTIN" aria-label="Search">
            <button class="xe-btn xe-btn-plain">Search</button>
        </form>
    @endif

    <section class="xe-section"><div class="table-responsive">
    @if ($state === 'not_at_nova')
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Request ID</th><th>Employee</th><th>Vendor</th><th>Activity ended</th><th class="text-end">Booking / quotation</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($cases as $c)
                <tr><td><a href="{{ route('btc.index', ['case' => $c->id]) }}" class="fw-semibold">{{ $c->case_no }}</a></td><td>{{ $c->emp_name }}</td><td>{{ $c->vendor_name }}</td>
                    <td>{{ optional($c->service_to ?: $c->service_from)->format('d-m-Y') }}</td><td class="text-end">{{ $inr($c->booking_amount ?: $c->quotation_amount) }}</td><td>{{ $c->statusLabel() }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No request is waiting for an invoice beyond {{ config('btc.nova.expected_after_days') }} days.</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Nova document</th><th>Invoice no. / date</th><th>Vendor / GSTIN</th><th class="text-end">Taxable</th><th class="text-end">Tax</th><th class="text-end">Gross</th><th>Received</th><th>Nova / import</th><th>Request</th><th style="min-width:280px"></th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->nova_document_id }}@if ($r->document_url)<div><a href="{{ $r->document_url }}" target="_blank" rel="noopener" class="small">document</a></div>@endif</td>
                    <td>{{ $r->invoice_no }}<div class="small text-muted">{{ $r->invoice_date ? \Carbon\Carbon::parse($r->invoice_date)->format('d-m-Y') : '' }}</div></td>
                    <td>{{ $r->vendor_name }}<div class="small text-muted">{{ $r->vendor_gstin }}</div></td>
                    <td class="text-end">{{ $inr($r->taxable_amount) }}</td><td class="text-end">{{ $inr($r->tax_amount) }}</td><td class="text-end fw-semibold">{{ $inr($r->gross_amount) }}</td>
                    <td class="text-nowrap">{{ $r->received_on ? \Carbon\Carbon::parse($r->received_on)->format('d-m-Y') : '' }}</td>
                    <td>{{ ucfirst($r->nova_status) }} / {{ $r->import_status }}@if ($r->import_error)<div class="small text-danger">{{ $r->import_error }}</div>@endif</td>
                    <td>
                        @if ($r->match_status === 'matched')
                            <a href="{{ route('btc.index', ['case' => $r->btc_case_id]) }}">{{ $caseNos[$r->btc_case_id] ?? '#'.$r->btc_case_id }}</a>@if ($r->match_confidence)<span class="small text-muted"> · {{ (int) $r->match_confidence }}%</span>@endif
                        @elseif ($r->match_status === 'mismatch')
                            <span class="text-danger small">Mismatch: {{ $r->mismatch_note }}</span>
                        @else
                            <span class="text-muted small">{{ $r->match_status }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($r->match_status === 'matched')
                            <form method="post" action="{{ route('btc.invoices.unlink', $r->id) }}" class="d-flex gap-1">@csrf
                                <input name="note" class="form-control form-control-sm" placeholder="What does not match" required maxlength="500" aria-label="Mismatch note">
                                <button class="xe-btn xe-btn-amber">Unlink</button></form>
                        @elseif ($r->nova_status === 'transferred' && $r->import_status === 'imported')
                            @foreach ($suggestions[$r->id] ?? [] as $s)
                                <form method="post" action="{{ route('btc.invoices.link', $r->id) }}" class="d-flex align-items-center gap-1 mb-1">@csrf
                                    <input type="hidden" name="case_id" value="{{ $s['id'] }}">
                                    <span class="badge {{ $s['score'] >= 70 ? 'bg-success' : ($s['score'] >= 40 ? 'bg-warning text-dark' : 'bg-secondary') }}" title="{{ $s['why'] }}">{{ $s['score'] }}%</span>
                                    <span class="small text-truncate" style="max-width:220px" title="{{ $s['label'] }} ({{ $s['why'] }})">{{ $s['label'] }}</span>
                                    <button class="xe-btn xe-btn-green">Match</button>
                                </form>
                            @endforeach
                            <form method="post" action="{{ route('btc.invoices.link', $r->id) }}" class="d-flex gap-1">@csrf
                                <input name="case_no" class="form-control form-control-sm" placeholder="BTC/26/00025" required maxlength="40" aria-label="Request ID">
                                <button class="xe-btn xe-btn-blue">Match manually</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-3">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif
    </div></section>
</div></div>
@endsection
