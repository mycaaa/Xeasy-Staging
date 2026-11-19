{{-- BTC-V2 (19 Nov 2026): Automatic Data Extraction / Finance Verifier 1 / Finance Approval - queues of the revised workflow --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    @if (! empty($focusExport))
        <div class="d-flex gap-2 mb-2">
            <a class="xe-btn xe-btn-green" href="{{ route('btc.export.focus') }}"><i class="ri-file-excel-2-line"></i> Export Focus entries not yet exported</a>
            <span class="small text-muted align-self-center">The export file follows the existing Focus JV format; each case records when it was exported.</span>
        </div>
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
    const open = new URLSearchParams(location.search).get('case');
    if (open) window.BtcCase.open(open);
});
</script>
@endpush
