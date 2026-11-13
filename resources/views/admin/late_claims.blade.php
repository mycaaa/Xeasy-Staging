{{-- LATE-PUNCH-01 (13 Nov 2026): bills uploaded after their month's due date wait for the GM's / admin's approval before punching --}}
@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h4 class="card-title mb-0 flex-grow-1"><i class="ri-time-line me-1"></i>Late Bill Approval
                    <span class="text-muted fs-13 fw-normal">bills uploaded after the due date</span></h4>
                <div class="btn-group btn-group-sm" role="group" aria-label="Show">
                    <a href="{{ route('late.bills', ['show' => 'pending']) }}" class="btn {{ $show === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">Waiting</a>
                    <a href="{{ route('late.bills', ['show' => 'decided']) }}" class="btn {{ $show === 'decided' ? 'btn-primary' : 'btn-outline-primary' }}">Approved / rejected</a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    A bill can be uploaded any day of the financial year. If it is uploaded after its month's due date
                    (Settings &gt; Submission settings), data punching waits for approval:
                    {{ $companyId === 11 ? 'by an admin.' : 'by the GM (BU head / HOD) or an admin.' }}
                    @if (! $isAdmin) You see the bills of the employees you approve for. @endif
                </p>
                @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
                @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

                <form method="post" action="{{ route('late.bills.decide') }}" id="lateForm">
                    @csrf
                    @if ($show === 'pending' && $rows->count())
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <span class="small text-muted"><span id="lateSel">0</span> selected</span>
                            <input type="text" name="remark" class="form-control form-control-sm" style="max-width:360px" maxlength="500" placeholder="Remark (needed to reject)" aria-label="Remark">
                            <button type="submit" name="decision" value="approve" class="btn btn-sm btn-success" disabled data-need-sel><i class="ri-check-line me-1"></i>Approve for punching</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-sm btn-danger" disabled data-need-sel><i class="ri-close-line me-1"></i>Reject</button>
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    @if ($show === 'pending')<th style="width:32px"><input type="checkbox" class="form-check-input" id="lateAll" aria-label="Select all"></th>@endif
                                    <th>Exp Id</th><th>Employee</th><th>Claim</th><th>Bill date</th><th>Uploaded</th><th>Due date</th><th class="text-end">Amount</th>
                                    @if ($show === 'decided')<th>Decision</th><th>By / on</th><th>Remark</th>@else<th>Status</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $r)
                                    <tr>
                                        @if ($show === 'pending')<td><input type="checkbox" class="form-check-input late-row" name="ids[]" value="{{ $r->ExpId }}" aria-label="Select {{ $r->ExpId }}"></td>@endif
                                        <td>{{ $r->ExpId }}</td>
                                        <td>{{ $r->employee }}</td>
                                        <td>{{ $r->ClaimName }}</td>
                                        <td>{{ $r->BillDate ? date('d/m/Y', strtotime($r->BillDate)) : '' }}</td>
                                        <td>{{ $r->CrDate ? date('d/m/Y', strtotime($r->CrDate)) : '' }}</td>
                                        <td>{{ $r->due }}</td>
                                        <td class="text-end">{{ (float) $r->FilledTAmt > 0 ? '₹'.number_format((float) $r->FilledTAmt) : '' }}</td>
                                        @if ($show === 'decided')
                                            <td>{!! $r->Backdate_Odometer_Status === 'A' ? '<span class="badge bg-success">Approved</span>' : '<span class="badge bg-danger">Rejected</span>' !!}</td>
                                            <td>{{ $r->decided_by }}{{ $r->Backdate_Odometer_Date ? ' · '.date('d/m/Y', strtotime($r->Backdate_Odometer_Date)) : '' }}</td>
                                            <td>{{ $r->Backdate_Final_Remark }}</td>
                                        @else
                                            <td><span class="badge bg-warning">Waiting for approval</span></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted py-4">{{ $show === 'pending' ? 'No late bills waiting for you.' : 'No decisions yet.' }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const all = document.getElementById('lateAll');
    const rows = () => [...document.querySelectorAll('.late-row')];
    const sync = () => {
        const n = rows().filter(c => c.checked).length;
        const s = document.getElementById('lateSel'); if (s) s.textContent = n;
        document.querySelectorAll('[data-need-sel]').forEach(b => b.disabled = n === 0);
        if (all) all.checked = n > 0 && n === rows().length;
    };
    all && all.addEventListener('change', () => { rows().forEach(c => c.checked = all.checked); sync(); });
    document.addEventListener('change', e => { if (e.target.classList.contains('late-row')) sync(); });
})();
</script>
@endpush
