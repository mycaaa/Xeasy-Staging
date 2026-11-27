{{-- BTC-V2 (19 Nov 2026): Automatic Data Extraction / Finance Verifier 1 / Finance Approval - queues of the revised workflow --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    @if (! empty($focusExport))
        <div class="d-flex gap-2 mb-2">
            <a class="xe-btn xe-btn-green" href="{{ route('btc.export.focus') }}"><i class="ri-file-excel-2-line"></i> Export Focus entries not yet exported</a>
            <span class="small text-muted align-self-center">The export file follows the existing Focus JV format; each case records when it was exported.</span>
        </div>
    @endif
    @if (! empty($punchSheet))
        {{-- BTC-FLOW-18 (25 Nov 2026, testing point 18): the Focus import sheet of the bills exported on this screen --}}
        {{-- BTC-DAYFILE-01 (27 Nov 2026, testing point 35): one consolidated file per day (all vendors, employees, bills) --}}
        @php
            $dayQ = \App\Models\BtcCase::where('company_id', (int) session('company_id', 1))->whereDate('focus_exported_at', now()->toDateString())->whereNotIn('status', ['cancelled', 'rejected']);
            $dayAll = (clone $dayQ)->count();
            $dayNew = \Illuminate\Support\Facades\Schema::hasColumn('btc_cases', 'focus_file_at') ? (clone $dayQ)->whereNull('focus_file_at')->count() : $dayAll;
        @endphp
        <form method="get" action="{{ route('btc.treasury.focus', 'jv') }}" class="d-flex flex-wrap gap-2 align-items-end mb-2 p-2 border rounded bg-light">
            <div class="small fw-semibold w-100">Today's Focus export: {{ $dayAll }} bill{{ $dayAll === 1 ? '' : 's' }} ({{ $dayNew }} not yet downloaded)</div>
            <div><label class="form-label small mb-1" for="psDay">Bills exported on</label><input type="date" id="psDay" name="exported_on" value="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="new_only" value="1" id="psNew" checked><label class="form-check-label small" for="psNew">Only bills not in an earlier download</label></div>
            <button class="xe-btn xe-btn-green"><i class="ri-file-excel-2-line"></i> Download the day's consolidated file</button>
            <span class="small text-muted">Each Focus Export adds the bill to the day's list; one file holds every bill of the day (vendor JV, employee JV and the check sheet). Each bill keeps its own DocNo.</span>
        </form>
    @endif
    @if (! empty($pick) && $rows->count())
        {{-- BTC-PAY-02 (20 Nov 2026, tracker tab 6 d): payment instruction to Treasury for the ticked bills --}}
        <form method="post" action="{{ route('btc.approval.instruct') }}" id="paForm" class="d-flex flex-wrap gap-2 align-items-end mb-2">@csrf
            <div><label class="form-label small mb-1" for="paRef">Payment instruction ref. (optional)</label><input id="paRef" name="pay_instruction_ref" maxlength="60" class="form-control form-control-sm"></div>
            <div><label class="form-label small mb-1" for="paRemark">Remark</label><input id="paRemark" name="remark" maxlength="500" class="form-control form-control-sm"></div>
            <button class="xe-btn xe-btn-blue" id="paGo" disabled>Give payment instruction for ticked bills</button>
            <span class="small text-muted">Treasury pays only instructed bills.</span>
        </form>
    @endif
    @include('admin.btc.pages._queue-table')
</div></div>
@include('admin.btc._casewin')
@endsection
@push('scripts')
<script>
$(function () {
    $(document).on('click', '[data-open]', function (e) { e.stopPropagation(); window.BtcCase.open($(this).data('open')); });
    $(document).on('btc:changed', function () { $('#btcCaseModal').one('hidden.bs.modal', () => location.reload()); });
    const sync = () => $('#paGo').prop('disabled', !$('.qt-pick:checked').length);
    $('#qtAll').on('change', function () { $('.qt-pick').prop('checked', this.checked); sync(); });
    $(document).on('change', '.qt-pick', sync);
    $('#paForm').on('submit', function () { $('#paGo').prop('disabled', true).text('Saving…'); });
    const open = new URLSearchParams(location.search).get('case');
    if (open) window.BtcCase.open(open);
});
</script>
@endpush
