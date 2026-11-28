{{-- BTC-V2: a queue of BTC cases (newest first); a row opens the shared case window (invoice beside the data) --}}
@php $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2); @endphp
<div class="xe-seg mb-2">
    @foreach ($tabs as $k => $l)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
    @endforeach
    <form method="get" class="ms-auto d-flex gap-1"><input type="hidden" name="tab" value="{{ $tab }}"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="BTC ID, vendor, invoice no., employee" aria-label="Search"><button class="xe-btn xe-btn-plain">Search</button></form>
</div>
{{-- BTC-GRID-01 (28 Nov 2026): amounts grouped, each figure in its own right-aligned column; advance and bill payment
     in separate columns; the bill's source and reading in one column --}}
@php $nAmt = 3 + (empty($advCols) ? 0 : 3); @endphp
<section class="xe-section"><div class="btc-grid-wrap">
    <table class="btc-grid">
        <thead>
            <tr class="btc-grp">
                <th class="btc-grp-none" colspan="{{ 4 + (empty($pick) ? 0 : 1) }}"></th>
                <th class="gs" colspan="{{ $nAmt }}">Amounts ₹</th>
                @if (! empty($payCols))<th class="gs" colspan="2">Payment</th>@endif
                <th class="btc-grp-none gs" colspan="4"></th>
            </tr>
            <tr>@if (! empty($pick))<th><input type="checkbox" id="qtAll" aria-label="Tick all"></th>@endif
                <th>BTC ID</th><th>Employee</th><th>Vendor</th><th>Invoice no.</th>
                <th class="num gs">Invoice</th><th class="num">GST</th>
                @if (! empty($advCols))<th class="num">TDS</th><th class="num">Advance paid</th><th class="num">Adjusted</th>@endif
                <th class="num">Net payable</th>
                @if (! empty($payCols))<th class="gs">Advance</th><th>Bill payment</th>@endif
                <th class="gs">Bill</th><th>Stage</th><th>Updated</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($rows as $c)
            @php $af = $c->advanceFigures(); @endphp
            <tr role="button" data-open="{{ $c->id }}">
                @if (! empty($pick))<td onclick="event.stopPropagation()"><input type="checkbox" form="paForm" name="ids[]" value="{{ $c->id }}" class="qt-pick" aria-label="Tick {{ $c->case_no }}"></td>@endif
                <td class="fw-semibold nw">{{ $c->case_no }}@if ($c->on_hold)<span class="sub"><span class="badge bg-danger">On hold</span></span>@endif</td>
                <td>{{ trim($c->emp_code.' '.$c->emp_name) ?: '-' }}<span class="sub">{{ $c->department_name }}</span></td>
                <td>{{ $c->vendor_name ?: '-' }}<span class="sub">{{ $c->vendor_gstin }}</span></td>
                <td class="nw">{{ $c->bill_no ?: '-' }}<span class="sub">{{ optional($c->bill_date)->format('d-m-Y') }}</span></td>
                <td class="num gs {{ (float) $c->bill_amount > 0 ? '' : 'zero' }}">{{ (float) $c->bill_amount > 0 ? $inr($c->bill_amount) : '-' }}</td>
                <td class="num {{ (float) $c->gst_amount > 0 ? '' : 'zero' }}">{{ (float) $c->gst_amount > 0 ? $inr($c->gst_amount) : '-' }}</td>
                @if (! empty($advCols))
                    <td class="num {{ (float) $c->tds_amount > 0 ? '' : 'zero' }}">{{ (float) $c->tds_amount > 0 ? $inr($c->tds_amount) : '-' }}</td>
                    <td class="num {{ $af['paid'] > 0 ? '' : 'zero' }}">{{ $af['paid'] > 0 ? $inr($af['paid']) : '-' }}</td>
                    <td class="num {{ (float) $c->advance_adjusted > 0 ? '' : 'zero' }}">{{ (float) $c->advance_adjusted > 0 ? $inr($c->advance_adjusted) : '-' }}@if ($af['balance'] > 0.5 && in_array($c->status, ['focus_done', 'fin_approved'], true))<span class="sub due">{{ $inr($af['balance']) }} to settle</span>@endif</td>
                @endif
                <td class="num">@if ((float) $c->net_payable < 0)<span class="due">Recover {{ $inr(abs((float) $c->net_payable)) }}</span>@elseif ((float) $c->net_payable > 0){{ $inr($c->net_payable) }}@else<span class="zero">-</span>@endif</td>
                @if (! empty($payCols))
                    {{-- BTC-PAY-02 / BTC-MATRIX-01 / BTC-ADV-TREAS-01: advance and bill payment, each in its own column --}}
                    @php($lp = \App\Services\Btc\BtcMatrixVerify::ready() ? \Illuminate\Support\Facades\DB::table('btc_payments')->where('btc_case_id', $c->id)->where('status', 'success')->orderByDesc('id')->first() : null)
                    <td class="gs small">
                        @if ($c->status === 'advance_requested' && ($c->adv_stage ?? null) === 'instructed')<div class="fw-semibold nw">{{ $inr($c->advance_approved ?? $c->advance_amount) }} to release</div><span class="sub">instructed {{ optional($c->adv_instructed_at)->format('d-m-Y') }}@if ($c->adv_instruction_ref ?? null) ({{ $c->adv_instruction_ref }})@endif</span>
                        @elseif ((float) ($c->advance_paid_amount ?? 0) > 0)<div class="nw">{{ $inr($c->advance_paid_amount) }} released</div><span class="sub">@if ($c->advance_paid_on){{ \Carbon\Carbon::parse($c->advance_paid_on)->format('d-m-Y') }}@endif {{ $c->advance_payment_ref }}</span>
                        @else<span class="zero">-</span>@endif
                    </td>
                    <td class="small">
                        @if ((float) $c->paid_amount > 0)<div class="nw ok fw-semibold">Paid {{ $inr($c->paid_amount) }}@if ($c->payment_status === 'partial') <span class="badge bg-warning text-dark">part</span>@endif</div><span class="sub">{{ $c->utr_number }}</span>
                        @elseif (! empty($c->pay_instructed_at))<div class="nw">Instructed {{ $c->pay_instructed_at->format('d-m-Y') }}</div>@if ($c->pay_instruction_ref)<span class="sub">{{ $c->pay_instruction_ref }}</span>@endif
                        @elseif ($c->status === 'fin_approved')<div class="text-warning nw">Instruction awaited</div>
                        @else<span class="zero">-</span>@endif
                        @if ($lp)
                            <div class="mt-1">@switch($lp->matrix_status)
                                @case('verified')<span class="badge bg-success">Matrix matched</span>@break
                                @case('mismatch')<span class="badge bg-danger" title="{{ $lp->matrix_message }}">Matrix: not matched</span>@break
                                @case('sent')<span class="badge bg-info text-dark">Sent to Matrix</span>@break
                                @case('failed')<span class="badge bg-warning text-dark" title="{{ $lp->matrix_message }}">Matrix: not sent</span>@break
                                @case('no_account')<span class="badge bg-warning text-dark" title="{{ $lp->matrix_message }}">No bank a/c for Matrix</span>@break
                                @default<span class="badge bg-light text-dark">Matrix: waiting</span>
                            @endswitch
                            @if ($lp->matrix_status !== 'verified' && (auth()->user()->can('btc.treasury') || auth()->user()->can('btc.admin')))
                                <form method="post" action="{{ route('btc.treasury.matrix') }}" class="d-inline" onclick="event.stopPropagation()">@csrf<input type="hidden" name="payment_id" value="{{ $lp->id }}"><button class="btn btn-link btn-sm p-0 ms-1" title="Send this payment to Matrix (verify)">{{ $lp->matrix_status ? 'Send again' : 'Send' }}</button></form>
                            @endif</div>
                        @endif
                        @if (! empty($c->focus_jv_doc))<span class="sub">{{ $c->focus_jv_doc }}@if (! empty($c->focus_ejv_doc)) · {{ $c->focus_ejv_doc }}@endif</span>@endif
                    </td>
                @endif
                <td class="gs small" style="min-width:150px">
                    <div class="nw">{{ config('btc.bill_sources.'.$c->bill_source, $c->bill_source) ?: '-' }}
                    @switch($c->extract_status)
                        @case('read')<span class="badge bg-success">Read</span>@break
                        @case('corrected')<span class="badge bg-info text-dark">Corrected</span>@break
                        @case('partial')<span class="badge bg-warning text-dark">Partly read</span>@break
                        @case('failed')<span class="badge bg-danger">Not read</span>@break
                        @case('pending')<span class="badge bg-secondary">Being read</span>@break
                    @endswitch</div>
                    <span class="sub">{{ optional($c->bill_uploaded_at)->format('d-m-Y H:i') }}</span>
                    @if ($ps = $c->punchSummary())<span class="sub" style="max-width:240px">{{ $ps }}</span>@endif
                    @if (in_array($c->status, ['bill_submitted'], true) && $c->extract_note)<span class="sub">{{ \Illuminate\Support\Str::limit($c->extract_note, 60) }}</span>@endif
                </td>
                <td class="nw">{{ $c->statusPill() }}</td>
                <td class="small nw">{{ optional($c->updated_at)->format('d-m-Y') }}<span class="sub">{{ optional($c->updated_at)->format('H:i') }}</span></td>
                <td><button type="button" class="xe-btn xe-btn-blue" data-open="{{ $c->id }}">Open</button></td>
            </tr>
        @empty
            <tr><td colspan="{{ 8 + $nAmt + (empty($payCols) ? 0 : 2) + (empty($pick) ? 0 : 1) }}" class="text-center text-muted py-4">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></section>
@include('admin.btc.pages._pager')
