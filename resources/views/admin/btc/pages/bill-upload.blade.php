{{-- BTC-V2 (19 Nov 2026, sheet item 24): Bill Upload by Finance --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    <section class="xe-section mb-3"><div class="p-3">
        <form method="post" action="{{ route('btc.bill-upload.store') }}" enctype="multipart/form-data" class="row g-2" id="buForm">@csrf
            <div class="col-md-6">
                <label class="form-label small mb-1" for="buCase">BTC request</label>
                <select id="buCase" name="case_id" class="form-select form-select-sm" aria-describedby="buCaseHelp">
                    @if ($preselect)<option value="{{ $preselect->id }}" selected>{{ $preselect->case_no }} · {{ trim($preselect->emp_code.' '.$preselect->emp_name) }} · {{ $preselect->statusLabel() }}</option>@endif
                </select>
                <div id="buCaseHelp" class="small text-muted">Search the request no., employee or vendor. Leave empty for a bill with no BTC request: a new BTC case is created.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="buSource">Bill received from</label>
                <select id="buSource" name="bill_source" class="form-select form-select-sm">
                    @foreach ($sources as $k => $l)@continue($k === 'employee')<option value="{{ $k }}" @selected(old('bill_source', 'dms') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="buBill">Scanned invoice (PDF / photo)</label>
                <input type="file" id="buBill" name="bill" required accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm">
            </div>
            <div class="col-12 row g-2 m-0 p-0" id="buNew">
                <div class="col-12 small fw-semibold mt-2">New BTC case (no request)</div>
                <div class="col-md-2"><label class="form-label small mb-1" for="buCat">Category</label>
                    <select id="buCat" name="category" class="form-select form-select-sm">@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small mb-1" for="buEmp">For employee (optional)</label><select id="buEmp" name="employee_id" class="form-select form-select-sm"></select></div>
                <div class="col-md-3"><label class="form-label small mb-1" for="buDept">or department</label><input id="buDept" name="department_name" value="{{ old('department_name') }}" maxlength="150" class="form-control form-control-sm" placeholder="if not for one employee"></div>
                <div class="col-md-4"><label class="form-label small mb-1" for="buPurpose">Purpose</label><input id="buPurpose" name="purpose" value="{{ old('purpose') }}" maxlength="500" class="form-control form-control-sm" placeholder="e.g. Dealer meet hall rent, Raipur"></div>
            </div>
            <div class="col-md-6"><label class="form-label small mb-1" for="buSup">Supporting documents (any number)</label><input type="file" id="buSup" name="supporting[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm"></div>
            <div class="col-md-4"><label class="form-label small mb-1" for="buRemark">Remark</label><input id="buRemark" name="remark" maxlength="500" value="{{ old('remark') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="xe-btn xe-btn-blue w-100" id="buGo"><i class="ri-upload-cloud-2-line"></i> Upload and read</button></div>
            <div class="col-12 small text-muted">Uploaded by, date and time, and the source are recorded; the invoice is kept as uploaded. A possible duplicate invoice is flagged before verification.</div>
        </form>
    </div></section>
    @include('admin.btc.pages._queue-table')
</div></div>
@include('admin.btc._casewin')
@endsection
@push('scripts')
<script>
$(function () {
    const $case = $('#buCase');
    $case.select2({width: '100%', allowClear: true, placeholder: 'Type the request no., employee or vendor',
        ajax: {url: @json(route('btc.bill-upload.cases')), delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}});
    $('#buEmp').select2({width: '100%', allowClear: true, placeholder: 'Employee code or name', minimumInputLength: 2,
        ajax: {url: @json(route('btc.approvers')), delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}});
    const shape = () => $('#buNew').toggle(!$case.val());
    $case.on('change', shape); shape();
    $('#buForm').on('submit', function () { $('#buGo').prop('disabled', true).html('<i class="ri-loader-4-line"></i> Uploading and reading…'); });
    $(document).on('click', '[data-open]', function (e) { e.stopPropagation(); window.BtcCase.open($(this).data('open')); });
    $(document).on('btc:changed', function () { $('#btcCaseModal').one('hidden.bs.modal', () => location.reload()); });
});
</script>
@endpush
