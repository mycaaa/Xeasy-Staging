{{-- BTC-V2: a queue of BTC cases (newest first); a row opens the shared case window (invoice beside the data) --}}
@php $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2); @endphp
<div class="xe-seg mb-2">
    @foreach ($tabs as $k => $l)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
    @endforeach
    <form method="get" class="ms-auto d-flex gap-1"><input type="hidden" name="tab" value="{{ $tab }}"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="BTC ID, vendor, invoice no., employee" aria-label="Search"><button class="xe-btn xe-btn-plain">Search</button></form>
</div>
<section class="xe-section"><div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>BTC ID</th><th>Vendor</th><th>Invoice</th><th class="text-end">Invoice amount</th><th class="text-end">GST</th><th class="text-end">Net payable</th><th>Employee / department</th><th>Source · uploaded</th><th>Data</th><th>Stage</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $c)
            <tr role="button" data-open="{{ $c->id }}">
                <td class="fw-semibold text-nowrap">{{ $c->case_no }}@if ($c->on_hold)<div><span class="badge bg-danger">On hold</span></div>@endif</td>
                <td>{{ $c->vendor_name ?: '-' }}<div class="small text-muted">{{ $c->vendor_gstin }}</div></td>
                <td class="text-nowrap">{{ $c->bill_no ?: '-' }}<div class="small text-muted">{{ optional($c->bill_date)->format('d-m-Y') }}</div></td>
                <td class="text-end">{{ $inr($c->bill_amount) }}</td>
                <td class="text-end">{{ $inr($c->gst_amount) }}</td>
                <td class="text-end">{{ $inr($c->net_payable) }}</td>
                <td>{{ trim($c->emp_code.' '.$c->emp_name) ?: '-' }}<div class="small text-muted">{{ $c->department_name }}</div></td>
                <td class="small">{{ config('btc.bill_sources.'.$c->bill_source, $c->bill_source) }}<div class="text-muted">{{ optional($c->bill_uploaded_at)->format('d-m-Y H:i') }}</div></td>
                <td class="small">
                    @switch($c->extract_status)
                        @case('read')<span class="badge bg-success">Read</span>@break
                        @case('corrected')<span class="badge bg-info text-dark">Corrected</span>@break
                        @case('partial')<span class="badge bg-warning text-dark">Partly read</span>@break
                        @case('failed')<span class="badge bg-danger">Not read</span>@break
                        @case('pending')<span class="badge bg-secondary">Being read</span>@break
                        @default -
                    @endswitch
                    @if (in_array($c->status, ['bill_submitted'], true) && $c->extract_note)<div class="text-muted">{{ \Illuminate\Support\Str::limit($c->extract_note, 60) }}</div>@endif
                </td>
                <td class="small">{{ $c->statusLabel() }}</td>
                <td class="small text-nowrap">{{ optional($c->updated_at)->format('d-m-Y H:i') }}</td>
                <td><button type="button" class="xe-btn xe-btn-blue" data-open="{{ $c->id }}">Open</button></td>
            </tr>
        @empty
            <tr><td colspan="12" class="text-center text-muted py-4">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></section>
<div class="mt-2">{{ $rows->links() }}</div>
