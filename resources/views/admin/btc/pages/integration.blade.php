{{-- BTC-REQ-03 page 9: Integration Monitoring --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'Integration Monitoring', 'icon' => 'ri-pulse-line', 'sub' => 'Nova (DMS) feed: every call and import, success or failure, duplicates, missing documents, retry'])
    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="border rounded p-2 bg-white h-100"><div class="small text-muted">Nova API</div>
            <div class="fw-semibold {{ $apiOn ? 'text-success' : 'text-danger' }}">{{ $apiOn ? 'Switched on' : 'Off - BTC_NOVA_API_KEY not set' }}</div>
            <div class="small text-muted text-break">POST {{ $endpoint }} · header X-Api-Key</div></div></div>
        <div class="col-md-4"><div class="border rounded p-2 bg-white h-100"><div class="small text-muted">Last successful Nova call</div>
            <div class="fw-semibold">{{ $lastOk ? \Carbon\Carbon::parse($lastOk)->format('d-m-Y H:i') : 'never' }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-2 bg-white h-100"><div class="small text-muted">Invoices failing import now</div>
            <div class="fw-semibold {{ $failedNow ? 'text-danger' : '' }}">{{ $failedNow }}</div>
            @if ($failedNow)<form method="post" action="{{ route('btc.integration.retry') }}" class="mt-1">@csrf<button class="xe-btn xe-btn-amber"><i class="ri-refresh-line"></i>Retry all failed</button></form>@endif
        </div></div>
    </div>
    <section class="xe-section">
        <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-history-line"></i>Sync history</span><span class="xe-count">{{ $logs->count() }}</span></div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Time</th><th>Channel</th><th>Result</th><th class="text-end">Received</th><th class="text-end">Imported</th><th class="text-end">Updated</th><th class="text-end">Duplicates</th><th class="text-end">Failed</th><th>Error</th><th></th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('d-m-Y H:i:s') }}</td>
                    <td>{{ ['nova_api' => 'Nova API', 'csv_import' => 'CSV import', 'retry' => 'Retry'][$l->channel] ?? $l->channel }}</td>
                    <td><span class="badge {{ $l->status === 'success' ? 'bg-success-subtle text-success' : ($l->status === 'partial' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') }}">{{ $l->status }}</span></td>
                    <td class="text-end">{{ $l->received }}</td><td class="text-end">{{ $l->imported }}</td><td class="text-end">{{ $l->updated }}</td><td class="text-end">{{ $l->duplicates }}</td>
                    <td class="text-end {{ $l->failed ? 'text-danger fw-semibold' : '' }}">{{ $l->failed }}</td>
                    <td class="small text-muted" style="max-width:360px">{{ $l->error }}</td>
                    <td>@if ($l->failed)<form method="post" action="{{ route('btc.integration.retry') }}">@csrf<input type="hidden" name="log_id" value="{{ $l->id }}"><button class="xe-btn xe-btn-plain">Retry</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-3">No Nova call or import yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="xe-section">
        <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-file-warning-line"></i>Transferred without a document</span><span class="xe-count">{{ $missingDocs->count() }}</span></div>
        <div class="xe-section-body">
            @forelse ($missingDocs as $m)<div class="small">{{ $m->nova_document_id }} · {{ $m->invoice_no }} · {{ $m->vendor_name }}</div>@empty<span class="small text-muted">None.</span>@endforelse
        </div>
    </section>
</div></div>
@endsection
