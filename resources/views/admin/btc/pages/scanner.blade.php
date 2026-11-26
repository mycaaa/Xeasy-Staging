{{-- BTC-SCAN-01 (19 Nov 2026, tracker tab "Data Punching Form & Finance approval screens", 1): Scanner --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    <section class="xe-section mb-3"><div class="p-3">
        <form method="post" action="{{ route('btc.scanner.store') }}" enctype="multipart/form-data" class="row g-2" id="scForm">@csrf
            <div class="col-md-5">
                <label class="form-label small mb-1" for="scBills">Scanned bills (PDF / photo, up to 25 at a time)</label>
                <input type="file" id="scBills" name="bills[]" multiple required accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" aria-describedby="scBillsHelp">
                <div id="scBillsHelp" class="small text-muted">Each file is one bill. <span id="scCount"></span></div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="scSource">Bill received from</label>
                <select id="scSource" name="bill_source" class="form-select form-select-sm">
                    @foreach ($sources as $k => $l)@continue($k === 'employee')<option value="{{ $k }}" @selected(old('bill_source', 'dms') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1" for="scCase">BTC request (optional, one bill)</label>
                <select id="scCase" name="case_id" class="form-select form-select-sm">
                    @if ($preselect)<option value="{{ $preselect->id }}" selected>{{ $preselect->case_no }} · {{ trim($preselect->emp_code.' '.$preselect->emp_name) }} · {{ $preselect->statusLabel() }}</option>@endif
                </select>
            </div>
            <div class="col-md-5"><label class="form-label small mb-1" for="scSup">Supporting documents (optional)</label><input type="file" id="scSup" name="supporting[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm"></div>
            <div class="col-md-5"><label class="form-label small mb-1" for="scRemark">Remark</label><input id="scRemark" name="remark" maxlength="500" value="{{ old('remark') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="xe-btn xe-btn-blue w-100" id="scGo"><i class="ri-scan-line"></i> Save scans</button></div>
            <div class="col-12 small text-muted">Scanned by, date and time, and the source are recorded; the scan is kept as it is. Bills without a request go to Classification; a bill scanned against its request goes straight to the Punch Screen.</div>
        </form>
    </div></section>
    @include('admin.btc.pages._queue-table')
</div></div>
@include('admin.btc._casewin')
@endsection
@push('scripts')
<script>
$(function () {
    $('#scCase').select2({width: '100%', allowClear: true, placeholder: 'Request no., employee or vendor',
        ajax: {url: @json(route('btc.bill-upload.cases')), delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}});
    $('#scBills').on('change', function () { const n = this.files.length; $('#scCount').text(n ? n + ' bill(s) chosen' : ''); });
    $('#scForm').on('submit', function (e) {
        if ($('#scCase').val() && $('#scBills')[0].files.length > 1) { e.preventDefault(); Swal.fire('One bill', 'Against a BTC request scan one bill; add the others as supporting documents.', 'warning'); return; }
        $('#scGo').prop('disabled', true).html('<i class="ri-loader-4-line"></i> Saving…');
    });
    $(document).on('click', '[data-open]', function (e) { e.stopPropagation(); window.BtcCase.open($(this).data('open')); });
    $(document).on('btc:changed', function () { $('#btcCaseModal').one('hidden.bs.modal', () => location.reload()); });
});
</script>
@endpush
