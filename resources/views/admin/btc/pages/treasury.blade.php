{{-- BTC-V2 (19 Nov 2026, sheet item 24): Treasury Payment (was Payment Batches) - after every Finance stage --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => number_format((float) $n, 2); @endphp
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    @if (! empty($focus) || ! empty($matrix))
        {{-- BTC-FOCUS-01 / BTC-MATRIX-01 (20 Nov 2026, tracker items 7, 8, 9) --}}
        <section class="xe-section mb-2"><div class="p-2 px-3 d-flex flex-wrap gap-3 align-items-center">
            @if (! empty($focus))
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="small fw-semibold">Focus import files</span>
                    @foreach (['jv' => 'JV - vendor bills', 'ejv' => 'Employee JV', 'bank' => 'Bank payment (release)'] as $k => $l)
                        <span class="d-inline-flex gap-1">
                            <a class="xe-btn xe-btn-green" href="{{ route('btc.treasury.focus', $k) }}" title="Gives the next document numbers to the bills that pass the checks"><i class="ri-file-excel-2-line"></i> {{ $l }} <span class="badge bg-light text-dark">{{ $focus[$k] }}</span></a>
                            <a class="xe-btn xe-btn-plain" href="{{ route('btc.treasury.focus', [$k, 'check' => 1]) }}" title="Checks only - no document numbers used">Check</a>
                        </span>
                    @endforeach
                </div>
            @endif
            @if (! empty($matrix))
                <div class="d-flex flex-wrap gap-2 align-items-center ms-md-auto small">
                    <span class="fw-semibold">Matrix payment confirmation</span>
                    @if ($matrix['on'])<span class="badge bg-success">On</span>@else<span class="badge bg-secondary" title="BTC_MATRIX_VERIFY_ENABLED and BTC_MATRIX_VERIFY_KEY in .env">Off</span>@endif
                    <span>waiting {{ $matrix['waiting'] }}</span>
                    @foreach (['verified' => 'matched', 'mismatch' => 'not matched', 'sent' => 'sent', 'failed' => 'failed', 'no_account' => 'no bank a/c'] as $k => $l)
                        @if (! empty($matrix['counts'][$k]))<span>· {{ $l }} {{ $matrix['counts'][$k] }}</span>@endif
                    @endforeach
                    @can('btc.treasury')
                        <form method="post" action="{{ route('btc.treasury.matrix') }}">@csrf<button class="xe-btn xe-btn-plain">{{ $matrix['on'] ? 'Send now' : 'Check what would be sent' }}</button></form>
                    @endcan
                </div>
            @endif
        </div></section>
    @endif
    @if ($tab === 'topay' && $rows->count() && (auth()->user()->can('btc.treasury') || auth()->user()->can('btc.admin')))
        <div class="xe-seg mb-2">@foreach ($tabs as $k => $l)<a href="{{ route('btc.treasury', ['tab' => $k]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>@endforeach</div>
        <form method="post" action="{{ route('btc.treasury.pay') }}" id="tpForm">@csrf
            <section class="xe-section mb-2"><div class="p-2 px-3 row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label small mb-1" for="tpOn">Paid on</label><input type="date" id="tpOn" name="paid_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="form-label small mb-1" for="tpMode">Mode</label><select id="tpMode" name="payment_mode" class="form-select form-select-sm" required>@foreach ($modes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small mb-1" for="tpRef">Bank reference (bulk transfer)</label><input id="tpRef" name="bank_ref" maxlength="60" class="form-control form-control-sm" placeholder="used when a row has no UTR"></div>
                <div class="col-md-5 d-flex gap-2 justify-content-md-end">
                    <a class="xe-btn xe-btn-plain" href="{{ route('btc.treasury.export') }}"><i class="ri-file-excel-2-line"></i> Excel of cases to pay</a>
                    <button class="xe-btn xe-btn-green" id="tpGo"><i class="ri-check-double-line"></i> Record payment for ticked cases</button>
                </div>
            </div></section>
            <section class="xe-section"><div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead><tr><th><input type="checkbox" id="tpAll" aria-label="Tick all"></th><th>BTC ID</th><th>Vendor</th><th>Invoice</th><th class="text-end">Invoice amount</th><th class="text-end">TDS</th><th class="text-end">Advance set off</th><th class="text-end">To pay ₹</th><th>UTR / cheque no.</th><th>Approved</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($rows as $i => $c)
                        @php $due = round((float) $c->net_payable - (float) $c->paid_amount, 2); @endphp
                        <tr>
                            <td><input type="checkbox" class="tp-pick" name="rows[{{ $i }}][pick]" value="1" aria-label="Pay {{ $c->case_no }}"><input type="hidden" name="rows[{{ $i }}][id]" value="{{ $c->id }}"></td>
                            <td class="fw-semibold text-nowrap">{{ $c->case_no }}</td>
                            <td>{{ $c->vendor_name }}<div class="small text-muted">{{ $c->vendor_gstin }}</div></td>
                            <td class="text-nowrap">{{ $c->bill_no }}<div class="small text-muted">{{ optional($c->bill_date)->format('d-m-Y') }}</div></td>
                            <td class="text-end">{{ $inr($c->bill_amount) }}</td>
                            <td class="text-end">{{ $inr($c->tds_amount) }}</td>
                            <td class="text-end">{{ $inr($c->advance_adjusted) }}</td>
                            <td class="text-end" style="width:130px"><input type="number" step="0.01" min="0.01" max="{{ $due }}" name="rows[{{ $i }}][amount]" value="{{ $due }}" class="form-control form-control-sm text-end" aria-label="Amount for {{ $c->case_no }}">@if ((float) $c->paid_amount > 0)<div class="small text-muted">paid {{ $inr($c->paid_amount) }}</div>@endif</td>
                            <td style="width:170px"><input name="rows[{{ $i }}][utr]" maxlength="40" class="form-control form-control-sm" aria-label="UTR for {{ $c->case_no }}"></td>
                            <td class="small text-nowrap">{{ optional($c->fin_approved_at)->format('d-m-Y') }}</td>
                            <td><button type="button" class="xe-btn xe-btn-blue" data-open="{{ $c->id }}">Open</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div></section>
            <div class="mt-2">{{ $rows->links() }}</div>
        </form>
    @else
        @include('admin.btc.pages._queue-table')
    @endif
</div></div>
@include('admin.btc._casewin')
@endsection
@push('scripts')
<script>
$(function () {
    $('#tpAll').on('change', function () { $('.tp-pick').prop('checked', this.checked); });
    $('#tpForm').on('submit', function (e) {
        if (!$('.tp-pick:checked').length) { e.preventDefault(); Swal.fire('Tick the cases paid', '', 'info'); return; }
        $('#tpGo').prop('disabled', true);
    });
    $(document).on('click', '[data-open]', function (e) { e.stopPropagation(); window.BtcCase.open($(this).data('open')); });
    $(document).on('btc:changed', function () { $('#btcCaseModal').one('hidden.bs.modal', () => location.reload()); });
});
</script>
@endpush
