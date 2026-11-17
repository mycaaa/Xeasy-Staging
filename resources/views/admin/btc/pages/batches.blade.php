{{-- BTC-BATCH-01 (17 Nov 2026): bank bulk-payment file, the bank's response (UTRs), checker authorisation --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'Payment Batches', 'icon' => 'ri-bank-line',
        'sub' => '1. Pick payment-ready cases and make the bank file  2. Upload it to the bank  3. Import the bank\'s response (UTRs)  4. Treasury checker authorises'])
    @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
    @if (session('notes') && count(session('notes')))<div class="alert alert-warning py-2 small"><ul class="mb-0">@foreach (session('notes') as $n)<li>{{ $n }}</li>@endforeach</ul></div>@endif
    @if (! $ready)<div class="alert alert-warning">Run the database change first (Database setup).</div>@endif
    @if ($ready && ! $debit)<div class="alert alert-warning py-2">Set the company debit account number on <a href="{{ route('btc.settings') }}">BTC Settings</a> before making a batch.</div>@endif

    @if ($ready && $canMake)
        <section class="xe-section mb-3">
            <div class="xe-section-hdr"><span class="xe-section-title">Payment-ready cases</span><span class="small text-muted">HOD approved, not on hold, no payment entered yet</span></div>
            <form method="post" action="{{ route('btc.batches.make') }}">@csrf
                <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
                    <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" id="bAll" aria-label="Select all"></th><th>Request ID</th><th>Vendor</th><th>Bank account</th><th class="text-end">Due ₹</th></tr></thead>
                    <tbody>
                    @forelse ($candidates as $x)
                        <tr class="{{ $x->blocked ? 'text-muted' : '' }}">
                            <td>@if (! $x->blocked)<input type="checkbox" class="form-check-input b-pick" name="ids[]" value="{{ $x->case->id }}" data-amt="{{ $x->due }}" aria-label="Pick {{ $x->case->case_no }}">@endif</td>
                            <td><a href="{{ route('btc.index', ['case' => $x->case->id]) }}">{{ $x->case->case_no }}</a><div class="small text-muted">{{ $x->case->emp_name }}</div></td>
                            <td>{{ $x->case->vendor_name }}<div class="small text-muted">bill {{ $x->case->bill_no }}</div></td>
                            <td class="small">{!! $x->bank ? e(\App\Services\Btc\BtcBank::masked($x->bank)) : '<span class="text-danger">'.e($x->blocked).'</span> <a href="'.route('btc.vendor-banks').'">add</a>' !!}</td>
                            <td class="text-end">{{ number_format($x->due, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No payment-ready cases.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="d-flex flex-wrap gap-2 align-items-center p-2">
                    <label class="small" for="bDate">Value date</label><input id="bDate" type="date" name="value_date" class="form-control form-control-sm" style="max-width:170px" value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}">
                    <button class="xe-btn xe-btn-blue"><i class="ri-file-list-3-line"></i>Make bank file</button>
                    <span class="small text-muted" id="bSum"></span>
                </div>
            </form>
        </section>
        <section class="xe-section mb-3">
            <div class="xe-section-hdr"><span class="xe-section-title">Import the bank's response</span><span class="small text-muted">Excel / CSV with Transaction Ref No, Transaction Status, UTR (RBI UTR Reference Number), Amount, Error Description</span></div>
            <div class="xe-section-body">
                <form method="post" action="{{ route('btc.batches.response') }}" enctype="multipart/form-data" class="d-flex gap-2">@csrf
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" style="max-width:360px" required aria-label="Bank response file">
                    <button class="xe-btn xe-btn-blue"><i class="ri-upload-2-line"></i>Import</button>
                </form>
            </div>
        </section>
    @endif

    <section class="xe-section">
        <div class="xe-section-hdr"><span class="xe-section-title">Batches</span></div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Batch</th><th>Made by</th><th class="text-end">Cases</th><th class="text-end">Total ₹</th><th>Bank response</th><th>Waiting for checker</th><th></th></tr></thead>
            <tbody>
            @forelse ($batches as $b)
                @php($p = $pending->get($b->id))
                <tr>
                    <td class="fw-semibold">{{ $b->batch_no }}<div class="small text-muted">{{ substr((string) $b->created_at, 0, 16) }}</div></td>
                    <td class="small">{{ $b->created_by_name }}</td>
                    <td class="text-end">{{ $b->cases }}</td><td class="text-end">{{ number_format((float) $b->total, 2) }}</td>
                    <td class="small">{{ $b->response_summary ?: 'not imported yet' }}</td>
                    <td class="small">{{ $p ? $p->n.' entr'.($p->n == 1 ? 'y' : 'ies').', '.$p->with_utr.' with UTR' : '-' }}</td>
                    <td class="text-nowrap">
                        @if ($canMake)<a class="btn btn-sm btn-outline-success" href="{{ route('btc.batches.file', $b->id) }}"><i class="ri-file-excel-2-line"></i> Bank file</a>@endif
                        @if ($canAuthorise && $p && $p->with_utr > 0 && (int) $b->created_by !== (int) auth()->id())
                            <form method="post" action="{{ route('btc.batches.authorise', $b->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Authorise {{ $p->with_utr }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No batches yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div></div>
@push('scripts')
<script>
(function () {
    const sum = () => { const p = [...document.querySelectorAll('.b-pick:checked')]; const t = p.reduce((s, x) => s + Number(x.dataset.amt), 0);
        const el = document.getElementById('bSum'); if (el) el.textContent = p.length ? p.length + ' case(s), Rs ' + t.toLocaleString('en-IN', {minimumFractionDigits: 2}) : ''; };
    document.getElementById('bAll')?.addEventListener('change', e => { document.querySelectorAll('.b-pick').forEach(x => x.checked = e.target.checked); sum(); });
    document.querySelectorAll('.b-pick').forEach(x => x.addEventListener('change', sum));
})();
</script>
@endpush
@endsection
