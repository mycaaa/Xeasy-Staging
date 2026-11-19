{{-- BTC-SCAN-01 (19 Nov 2026, tracker tab "Data Punching Form & Finance approval screens", 5): Finance Advance
     Approval - a. Pending, b. Approved, c. Instructed --}}
@extends('layouts.app')
@section('content')
@php
    $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2);
    $u = auth()->user();
    $canAct = $u->can('btc.admin') || ($tab === 'pending' ? $u->can('btc.fin_approver') : ($tab === 'approved' && ($u->can('btc.fin_approver') || $u->can('btc.advance'))));
@endphp
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    <div class="xe-seg mb-2">
        @foreach ($tabs as $k => $l)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
        @endforeach
        <form method="get" class="ms-auto d-flex gap-1"><input type="hidden" name="tab" value="{{ $tab }}"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="BTC ID, vendor, employee" aria-label="Search"><button class="xe-btn xe-btn-plain">Search</button></form>
    </div>
    <form method="post" action="{{ route('btc.advance-approval.bulk') }}" id="aaForm">@csrf
        <input type="hidden" name="action" value="{{ $tab === 'pending' ? 'adv_fin_approve' : 'adv_instruct' }}">
        @if ($canAct && $rows->count())
            <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
                @if ($tab === 'approved')
                    <div><label class="form-label small mb-1" for="aaRef">Payment instruction ref. (optional)</label><input id="aaRef" name="adv_instruction_ref" maxlength="60" class="form-control form-control-sm"></div>
                @endif
                <div><label class="form-label small mb-1" for="aaRemark">Remark</label><input id="aaRemark" name="remark" maxlength="500" class="form-control form-control-sm"></div>
                <button class="xe-btn xe-btn-blue" id="aaGo" disabled>{{ $tab === 'pending' ? 'Approve ticked advances' : 'Instruct payment for ticked' }}</button>
                <span class="small text-muted">To reject, open the request.</span>
            </div>
        @endif
        <section class="xe-section"><div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead><tr>@if ($canAct)<th><input type="checkbox" id="aaAll" aria-label="Tick all"></th>@endif<th>BTC ID</th><th>Employee / department</th><th>Category · purpose</th><th>Vendor</th><th class="text-end">Quotation</th><th class="text-end">Advance</th><th>Manager approval</th><th>Finance approval</th><th>Instruction</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $c)
                    <tr>
                        @if ($canAct)<td><input type="checkbox" name="ids[]" value="{{ $c->id }}" class="aa-pick" aria-label="Tick {{ $c->case_no }}"></td>@endif
                        <td class="fw-semibold text-nowrap">{{ $c->case_no }}</td>
                        <td>{{ trim($c->emp_code.' '.$c->emp_name) }}<div class="small text-muted">{{ $c->department_name }}</div></td>
                        <td class="small">{{ $c->categoryLabel() }}<div class="text-muted">{{ \Illuminate\Support\Str::limit($c->purpose, 50) }}</div></td>
                        <td class="small">{{ $c->vendor_name ?: '-' }}</td>
                        <td class="text-end">{{ $inr($c->quotation_amount) }}</td>
                        <td class="text-end fw-semibold">{{ $inr($c->advance_approved ?? $c->advance_amount) }}</td>
                        <td class="small">{{ optional($c->manager_action_at)->format('d-m-Y') }}</td>
                        <td class="small">@if ($c->adv_fin_at){{ \App\Models\User::whereKey($c->adv_fin_by)->value('name') }}<div class="text-muted">{{ $c->adv_fin_at->format('d-m-Y H:i') }}</div>@else - @endif</td>
                        <td class="small">@if ($c->adv_instructed_at){{ $c->adv_instructed_at->format('d-m-Y H:i') }}<div class="text-muted">{{ $c->adv_instruction_ref }}</div>@if ($c->advance_paid_amount)<span class="badge bg-success">Paid {{ $inr($c->advance_paid_amount) }}</span>@else<span class="badge bg-warning text-dark">To pay</span>@endif @else - @endif</td>
                        <td><button type="button" class="xe-btn xe-btn-plain" data-open="{{ $c->id }}">Open</button></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">Nothing here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></section>
    </form>
    <div class="mt-2">{{ $rows->links() }}</div>
</div></div>
@include('admin.btc._casewin')
@endsection
@push('scripts')
<script>
$(function () {
    const sync = () => $('#aaGo').prop('disabled', !$('.aa-pick:checked').length);
    $('#aaAll').on('change', function () { $('.aa-pick').prop('checked', this.checked); sync(); });
    $(document).on('change', '.aa-pick', sync);
    $('#aaForm').on('submit', function () { $('#aaGo').prop('disabled', true).text('Saving…'); });
    $(document).on('click', '[data-open]', function (e) { e.stopPropagation(); window.BtcCase.open($(this).data('open')); });
    $(document).on('btc:changed', function () { $('#btcCaseModal').one('hidden.bs.modal', () => location.reload()); });
});
</script>
@endpush
