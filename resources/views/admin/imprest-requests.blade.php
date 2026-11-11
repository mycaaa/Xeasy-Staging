@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1 fw-bold"><i class="ri-coins-line me-2 text-primary"></i>Imprest Automation Requests</h4>
            <p class="text-muted mb-0" style="font-size:13px;">REQ-001 · Auto-generated imprest requests for new joiners and grade promotions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary rounded-pill" id="runManualBtn">
                <i class="ri-refresh-line me-1"></i>Run Check Now
            </button>
            <a href="{{ route('system.requirements') }}#req-1" class="btn btn-sm btn-light rounded-pill">
                <i class="ri-file-list-3-line me-1"></i>View Requirement
            </a>
        </div>
    </div>

    {{-- Status Summary --}}
    <div class="row g-3 mb-3">
        @foreach(['pending' => ['warning','ri-time-line'], 'approved' => ['success','ri-check-double-line'], 'rejected' => ['danger','ri-close-circle-line']] as $s => [$color, $icon])
        <div class="col-auto">
            <a href="{{ route('imprest.index', ['status' => $s]) }}" class="card border-0 shadow-sm text-decoration-none d-block px-4 py-2 {{ $status === $s ? 'border border-'.$color : '' }}" style="border-radius:10px;">
                <div class="d-flex align-items-center gap-2">
                    <i class="{{ $icon }} text-{{ $color }} fs-5"></i>
                    <div>
                        <div class="fw-bold text-dark">{{ $counts[$s] ?? 0 }}</div>
                        <div class="text-muted" style="font-size:11px;">{{ ucfirst($s) }}</div>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $val => $label)
        <a href="{{ route('imprest.index', array_merge(request()->query(), ['status' => $val])) }}"
            class="btn btn-sm rounded-pill {{ $status === $val ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ $label }}
        </a>
        @endforeach
        <span class="vr mx-1"></span>
        @foreach([''=>'All Triggers','new_joiner'=>'New Joiner','promotion'=>'Promotion'] as $val => $label)
        <a href="{{ route('imprest.index', array_merge(request()->query(), ['trigger' => $val])) }}"
            class="btn btn-sm rounded-pill {{ ($trigger ?? '') === $val ? 'btn-info text-white' : 'btn-outline-secondary' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Trigger</th>
                            <th>Grade</th>
                            <th class="text-end">Eligible</th>
                            <th class="text-end">Previous</th>
                            <th class="text-end fw-bold">Request Amt</th>
                            <th>Status</th>
                            <th>Raised On</th>
                            <th>Remarks</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr id="row-{{ $req->id }}">
                            <td class="ps-3 text-muted">{{ $req->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $req->emp_name }}</div>
                                <div class="text-muted" style="font-size:11px;">{{ $req->emp_code }}</div>
                            </td>
                            <td>{{ $req->department ?? '—' }}</td>
                            <td>
                                @if($req->trigger_type === 'new_joiner')
                                <span class="badge bg-info-subtle text-info"><i class="ri-user-add-line me-1"></i>New Joiner</span>
                                @else
                                <span class="badge bg-primary-subtle text-primary"><i class="ri-arrow-up-circle-line me-1"></i>Promotion
                                    @if($req->previous_grade) ({{ $req->previous_grade }} → {{ $req->grade }}) @endif
                                </span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ $req->grade }}</span></td>
                            <td class="text-end">₹{{ number_format($req->eligible_amount) }}</td>
                            <td class="text-end text-muted">{{ $req->previous_amount > 0 ? '₹'.number_format($req->previous_amount) : '—' }}</td>
                            <td class="text-end fw-bold text-success">₹{{ number_format($req->request_amount) }}</td>
                            <td>
                                @if($req->status === 'pending')
                                <span class="badge bg-warning text-dark">Pending</span>
                                @elseif($req->status === 'approved')
                                <span class="badge bg-success">Approved</span>
                                @else
                                <span class="badge bg-danger">Rejected</span>
                                @endif
                            </td>
                            <td style="font-size:11px;">{{ \Carbon\Carbon::parse($req->created_at)->format('d M Y') }}</td>
                            <td style="font-size:11px;max-width:160px;">{{ $req->remarks ?? '—' }}</td>
                            <td class="text-center">
                                @if($req->status === 'pending' && auth()->user()?->can('imprest.approve'))
                                <button class="btn btn-sm btn-success rounded-pill px-3 me-1 approve-btn"
                                    data-id="{{ $req->id }}" data-name="{{ $req->emp_name }}"
                                    data-amount="{{ number_format($req->request_amount) }}">
                                    <i class="ri-check-line"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3 reject-btn"
                                    data-id="{{ $req->id }}" data-name="{{ $req->emp_name }}">
                                    <i class="ri-close-line"></i>
                                </button>
                                @elseif($req->status === 'pending')
                                <span class="text-muted" style="font-size:11px;">Awaiting Finance</span>
                                @else
                                <span class="text-muted" style="font-size:11px;">
                                    @if($req->status === 'rejected')
                                    {{ $req->rejected_by ?? '' }}
                                    @if(!empty($req->rejected_at))
                                    <br>{{ \Carbon\Carbon::parse($req->rejected_at)->format('d M Y') }}
                                    @endif
                                    @else
                                    {{ $req->approved_by ?? '' }}
                                    @if($req->approved_at)
                                    <br>{{ \Carbon\Carbon::parse($req->approved_at)->format('d M Y') }}
                                    @endif
                                    @endif
                                </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="12" class="text-center py-5 text-muted">
                            <i class="ri-inbox-line d-block fs-1 mb-2"></i>No imprest requests found.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
        <div class="card-footer bg-white py-2">{{ $requests->links() }}</div>
        @endif
    </div>

</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
<script>
const csrf = @json(csrf_token());

// Approve
$(document).on('click', '.approve-btn', function () {
    const id = $(this).data('id'), name = $(this).data('name'), amt = $(this).data('amount');
    Swal.fire({
        title: 'Approve Imprest Request',
        html: `<p>Approve <strong>₹${amt}</strong> imprest for <strong>${name}</strong>?</p>
               <textarea id="approveRemarks" class="form-control mt-2" placeholder="Remarks (optional)" aria-label="Remarks (optional)" rows="2"></textarea>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Approve',
        confirmButtonColor: '#0ab39c',
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(`/imprest-automation/${id}/approve`, {
            _token: csrf, remarks: $('#approveRemarks').val()
        }).done(res => {
            if (res.success) {
                $(`#row-${id} td:last`).html('<span class="badge bg-success">Approved</span>');
                $(`#row-${id} td:nth-child(9)`).html('<span class="badge bg-success">Approved</span>');
                Swal.fire({ icon: 'success', title: 'Approved!', timer: 1200, showConfirmButton: false });
            } else {
                Swal.fire('Error', res.message || 'Approval failed.', 'error');
            }
        }).fail(xhr => {
            Swal.fire('Error', xhr.responseJSON?.message || (xhr.status === 403 ? 'You are not authorised to approve imprest requests.' : 'Approval failed.'), 'error');
        });
    });
});

// Reject
$(document).on('click', '.reject-btn', function () {
    const id = $(this).data('id'), name = $(this).data('name');
    Swal.fire({
        title: 'Reject Imprest Request',
        html: `<p>Reject imprest request for <strong>${name}</strong>?</p>
               <textarea id="rejectRemarks" class="form-control mt-2" placeholder="Reason (required)" aria-label="Reason (required)" rows="2"></textarea>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Reject',
        confirmButtonColor: '#f06548',
        preConfirm: () => {
            if (!$('#rejectRemarks').val().trim()) {
                Swal.showValidationMessage('Reason is required');
            }
        }
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(`/imprest-automation/${id}/reject`, {
            _token: csrf, remarks: $('#rejectRemarks').val()
        }).done(res => {
            if (res.success) {
                $(`#row-${id} td:nth-child(9)`).html('<span class="badge bg-danger">Rejected</span>');
                $(`#row-${id} td:last`).html('<span class="text-muted" style="font-size:11px;">Rejected</span>');
                Swal.fire({ icon: 'success', title: 'Rejected', timer: 1200, showConfirmButton: false });
            } else {
                Swal.fire('Error', res.message || 'Rejection failed.', 'error');
            }
        }).fail(xhr => {
            Swal.fire('Error', xhr.responseJSON?.message || (xhr.status === 403 ? 'You are not authorised to reject imprest requests.' : 'Rejection failed.'), 'error');
        });
    });
});

// Run manual check
$('#runManualBtn').on('click', function () {
    const $btn = $(this).prop('disabled', true).html('<i class="ri-loader-4-line rotating me-1"></i>Running...');
    $.post('/imprest-automation/run-manual', { _token: csrf }).done(res => {
        Swal.fire({ icon: 'success', title: 'Check Complete',
            html: `<p>New Joiners: <strong>${res.results?.new_joiners}</strong></p>
                   <p>Promotions: <strong>${res.results?.promotions}</strong></p>`,
        }).then(() => location.reload());
    }).fail(() => {
        Swal.fire('Error', 'Failed to run check.', 'error');
    }).always(() => {
        $btn.prop('disabled', false).html('<i class="ri-refresh-line me-1"></i>Run Check Now');
    });
});
</script>
@endpush
