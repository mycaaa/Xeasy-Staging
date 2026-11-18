{{-- BTC-READER-01 (18 Nov 2026): Invoice Reader - vendor invoices read in Xeasy, checked and linked to their request --}}
@extends('layouts.app')
@section('content')
@php $amt = fn ($n) => $n === null ? '' : number_format((float) $n, 2, '.', ''); @endphp
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'Invoice Reader', 'icon' => 'ri-scan-2-line',
        'sub' => 'Upload the vendor invoices: Xeasy reads them, suggests the request, you check the figures and link. A booked request then moves to DMS with its bill.'])

    @unless ($ready)
        <div class="alert alert-warning small">Run the BTC database update first (Admin &gt; DB setup), then reload this page.</div>
    @else
    <section class="xe-section mb-3"><div class="p-3">
        <form method="post" action="{{ route('btc.reader.upload') }}" enctype="multipart/form-data" class="row g-2 align-items-end" id="rdUpload">@csrf
            <div class="col-md-5"><label class="form-label small mb-1" for="rdFiles">Invoices (PDF or photo, up to 10 at a time)</label>
                <input type="file" id="rdFiles" name="files[]" multiple required accept=".pdf,image/*" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small mb-1" for="rdCat">Kind of bill</label>
                <select id="rdCat" name="category" class="form-select form-select-sm">
                    @foreach ($categories as $k => $c)<option value="{{ $k }}" @selected($k === 'hotel')>{{ $c['label'] }}</option>@endforeach
                </select></div>
            <div class="col-md-2"><button class="xe-btn xe-btn-blue w-100" id="rdGo"><i class="ri-scan-2-line"></i> Read invoices</button></div>
            <div class="col-12 small text-muted">Reading takes a few seconds per invoice. The kind of bill helps the reader find the right fields.</div>
        </form>
    </div></section>

    <form method="get" class="d-flex flex-wrap gap-2 mb-2 align-items-center">
        <div class="btn-group btn-group-sm" role="group" aria-label="Show">
            @foreach (['open' => 'To check / link', 'linked' => 'Linked', 'all' => 'All'] as $k => $l)
                <a href="{{ route('btc.reader', ['show' => $k]) }}" class="btn {{ $show === $k ? 'btn-primary' : 'btn-outline-primary' }}">{{ $l }}</a>
            @endforeach
        </div>
        <input type="hidden" name="show" value="{{ $show }}">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:280px" placeholder="Invoice no., vendor, GSTIN, file" aria-label="Search">
        <button class="xe-btn xe-btn-plain">Search</button>
    </form>

    @forelse ($rows as $r)
        @php $linked = $r->match_status === 'matched'; @endphp
        <section class="xe-section mb-2"><div class="p-2 px-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div>
                    <span class="fw-semibold">{{ $r->nova_document_id }}</span>
                    <a href="{{ route('btc.reader.doc', $r->id) }}" target="_blank" rel="noopener" class="small ms-2"><i class="ri-file-text-line"></i> {{ \Illuminate\Support\Str::limit($r->file_name, 40) }}</a>
                    <span class="small text-muted ms-2">{{ config('btc.categories.'.$r->category.'.label', $r->category) }} · {{ \Carbon\Carbon::parse($r->created_at)->format('d-m-Y H:i') }}</span>
                </div>
                <div class="small">
                    @if ($linked)
                        <span class="badge bg-success">Linked</span> <a href="{{ route('btc.index', ['case' => $r->btc_case_id]) }}">{{ $caseNos[$r->btc_case_id] ?? '#'.$r->btc_case_id }}</a>
                    @elseif ($r->match_status === 'mismatch')
                        <span class="badge bg-secondary">Set aside</span> <span class="text-muted">{{ $r->mismatch_note }}</span>
                    @elseif ($r->extract_status === 'read')
                        <span class="badge bg-info text-dark">Read - check and link</span>
                    @elseif ($r->extract_status === 'partial')
                        <span class="badge bg-warning text-dark">Partly read</span> <span class="text-muted">{{ $r->extract_error }}</span>
                    @else
                        <span class="badge bg-danger">Not read</span> <span class="text-muted">{{ $r->extract_error }}</span>
                    @endif
                </div>
            </div>
            <form method="post" action="{{ route('btc.reader.save', $r->id) }}" class="row g-2 align-items-end">@csrf
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Invoice no.</label><input name="invoice_no" class="form-control form-control-sm" value="{{ $r->invoice_no }}" maxlength="60" @disabled($linked) required></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Invoice date</label><input type="date" name="invoice_date" class="form-control form-control-sm" value="{{ $r->invoice_date ? substr($r->invoice_date, 0, 10) : '' }}" max="{{ now()->toDateString() }}" @disabled($linked) required></div>
                <div class="col-12 col-md-3"><label class="form-label small mb-0">Vendor</label><input name="vendor_name" class="form-control form-control-sm" value="{{ $r->vendor_name }}" maxlength="200" @disabled($linked)></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">GSTIN</label><input name="vendor_gstin" class="form-control form-control-sm text-uppercase" value="{{ $r->vendor_gstin }}" maxlength="15" @disabled($linked)></div>
                <div class="col-6 col-md-1"><label class="form-label small mb-0">Taxable ₹</label><input type="number" step="0.01" min="0" name="taxable_amount" class="form-control form-control-sm rd-tx" value="{{ $amt($r->taxable_amount) }}" @disabled($linked)></div>
                <div class="col-6 col-md-1"><label class="form-label small mb-0">GST ₹</label><input type="number" step="0.01" min="0" name="tax_amount" class="form-control form-control-sm rd-gst" value="{{ $amt($r->tax_amount) }}" @disabled($linked)></div>
                <div class="col-6 col-md-1"><label class="form-label small mb-0">Total ₹</label><input type="number" step="0.01" min="1" name="gross_amount" class="form-control form-control-sm fw-semibold" value="{{ $amt($r->gross_amount) }}" @disabled($linked) required></div>
                @unless ($linked)
                    <div class="col-12 col-md-5">
                        <label class="form-label small mb-0">Link to request</label>
                        <input name="case_no" class="form-control form-control-sm" list="rdSug{{ $r->id }}" value="{{ old('case_no') }}" placeholder="BTC/26/00025 or 25" maxlength="30" aria-label="Request to link">
                        <datalist id="rdSug{{ $r->id }}">@foreach ($suggest[$r->id] ?? [] as $s)<option value="{{ $s['case']->case_no }}">{{ $s['score'] }}% · {{ $s['case']->emp_name }} · {{ $s['case']->vendor_name }} · {{ $s['why'] }}</option>@endforeach</datalist>
                        @if (! empty($suggest[$r->id]))
                            <div class="small mt-1">Suggested:
                                @foreach ($suggest[$r->id] as $s)
                                    <button type="button" class="btn btn-link btn-sm p-0 me-2 rd-pick" data-case="{{ $s['case']->case_no }}" title="{{ $s['why'] }}">
                                        <span class="badge {{ $s['score'] >= 70 ? 'bg-success' : ($s['score'] >= 40 ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $s['score'] }}%</span>
                                        {{ $s['case']->case_no }} · {{ \Illuminate\Support\Str::limit($s['case']->emp_name, 18) }} · {{ $s['case']->statusLabel() }}</button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="col-12 col-md-7 d-flex flex-wrap gap-2 justify-content-md-end">
                        <button class="xe-btn xe-btn-green" name="do" value="link"><i class="ri-link"></i> Save &amp; link</button>
                        <button class="xe-btn xe-btn-plain" name="do" value="save" formnovalidate>Save only</button>
                        <button class="xe-btn xe-btn-plain" name="do" value="reread" formnovalidate title="Read the file again">Read again</button>
                        <button class="xe-btn xe-btn-amber rd-discard" name="do" value="discard" formnovalidate title="Not a BTC invoice / duplicate upload">Set aside</button>
                        <input type="hidden" name="note" value="">
                    </div>
                @endunless
            </form>
        </div></section>
    @empty
        <div class="text-center text-muted py-4">No invoices here. Upload invoices above.</div>
    @endforelse
    @endunless
</div></div>
<script>
$(function () {
    $('#rdUpload').on('submit', function () { $('#rdGo').prop('disabled', true).html('<i class="ri-loader-4-line"></i> Reading…'); });
    $(document).on('click', '.rd-pick', function () { $(this).closest('form').find('[name=case_no]').val($(this).data('case')).trigger('focus'); });
    $(document).on('click', '.rd-discard', function (e) {
        const n = window.prompt('Why set this invoice aside (e.g. duplicate upload, not a BTC bill)?');
        if (!n) { e.preventDefault(); return; }
        $(this).closest('form').find('[name=note]').val(n);
    });
});
</script>
@endsection
