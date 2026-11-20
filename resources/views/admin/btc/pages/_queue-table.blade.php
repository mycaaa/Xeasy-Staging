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
        <thead><tr>@if (! empty($pick))<th><input type="checkbox" id="qtAll" aria-label="Tick all"></th>@endif<th>BTC ID</th><th>Vendor</th><th>Invoice</th><th class="text-end">Invoice amount</th><th class="text-end">GST</th>@if (! empty($advCols))<th class="text-end">TDS</th><th class="text-end">Advance paid</th><th class="text-end">Adjusted</th>@endif<th class="text-end">Net payable</th>@if (! empty($payCols))<th>Instruction / payment</th>@endif<th>Employee / department</th><th>Source · uploaded</th><th>Data</th><th>Stage</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $c)
            <tr role="button" data-open="{{ $c->id }}">
                @if (! empty($pick))<td onclick="event.stopPropagation()"><input type="checkbox" form="paForm" name="ids[]" value="{{ $c->id }}" class="qt-pick" aria-label="Tick {{ $c->case_no }}"></td>@endif
                <td class="fw-semibold text-nowrap">{{ $c->case_no }}@if ($c->on_hold)<div><span class="badge bg-danger">On hold</span></div>@endif</td>
                <td>{{ $c->vendor_name ?: '-' }}<div class="small text-muted">{{ $c->vendor_gstin }}</div></td>
                <td class="text-nowrap">{{ $c->bill_no ?: '-' }}<div class="small text-muted">{{ optional($c->bill_date)->format('d-m-Y') }}</div></td>
                <td class="text-end">{{ $inr($c->bill_amount) }}</td>
                <td class="text-end">{{ $inr($c->gst_amount) }}</td>
                @if (! empty($advCols))
                    @php($af = $c->advanceFigures())
                    <td class="text-end">{{ $inr($c->tds_amount) }}</td>
                    <td class="text-end">{{ $af['paid'] > 0 ? $inr($af['paid']) : '-' }}</td>
                    <td class="text-end">{{ (float) $c->advance_adjusted > 0 ? $inr($c->advance_adjusted) : '-' }}@if ($af['balance'] > 0.5 && in_array($c->status, ['focus_done', 'fin_approved'], true))<div class="small text-danger">{{ $inr($af['balance']) }} still to settle</div>@endif</td>
                @endif
                <td class="text-end">@if ((float) $c->net_payable < 0)<span class="text-danger">Recover {{ $inr(abs((float) $c->net_payable)) }}</span>@else{{ $inr($c->net_payable) }}@endif</td>
                @if (! empty($payCols))
                    {{-- BTC-PAY-02 / BTC-MATRIX-01 (20 Nov 2026): instruction, payment and Matrix confirmation --}}
                    @php($lp = \App\Services\Btc\BtcMatrixVerify::ready() ? \Illuminate\Support\Facades\DB::table('btc_payments')->where('btc_case_id', $c->id)->where('status', 'success')->orderByDesc('id')->first() : null)
                    <td class="small text-nowrap">
                        @if (! empty($c->pay_instructed_at))<div>Instructed {{ $c->pay_instructed_at->format('d-m-Y') }}@if ($c->pay_instruction_ref) <span class="text-muted">({{ $c->pay_instruction_ref }})</span>@endif</div>@elseif ($c->status === 'fin_approved')<div class="text-warning">Instruction awaited</div>@endif
                        @if ((float) $c->paid_amount > 0)<div>Paid {{ $inr($c->paid_amount) }}@if ($c->payment_status === 'partial') <span class="badge bg-warning text-dark">part</span>@endif</div><div class="text-muted">{{ $c->utr_number }}</div>@endif
                        @if ($lp)
                            @switch($lp->matrix_status)
                                @case('verified')<span class="badge bg-success">Matrix matched</span>@break
                                @case('mismatch')<span class="badge bg-danger" title="{{ $lp->matrix_message }}">Matrix: not matched</span>@break
                                @case('sent')<span class="badge bg-info text-dark">Sent to Matrix</span>@break
                                @case('failed')<span class="badge bg-warning text-dark" title="{{ $lp->matrix_message }}">Matrix: not sent</span>@break
                                @case('no_account')<span class="badge bg-warning text-dark" title="{{ $lp->matrix_message }}">No bank a/c for Matrix</span>@break
                                @default<span class="badge bg-light text-dark">Matrix: waiting</span>
                            @endswitch
                        @endif
                        @if (! empty($c->focus_jv_doc))<div class="text-muted">{{ $c->focus_jv_doc }}@if (! empty($c->focus_ejv_doc)) · {{ $c->focus_ejv_doc }}@endif</div>@endif
                    </td>
                @endif
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
            <tr><td colspan="{{ (empty($advCols) ? 12 : 15) + (empty($payCols) ? 0 : 1) + (empty($pick) ? 0 : 1) }}" class="text-center text-muted py-4">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></section>
<div class="mt-2">{{ $rows->links() }}</div>
