@extends('layouts.app')

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Header --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 fw-bold">
                        <i class="ri-alert-line text-warning me-2"></i>Missing Fixed Imprest Report
                    </h4>
                    <p class="text-muted mb-0 fs-13">
                        Active employees in eligible grades (J1–M1) &amp; departments (Sales / PD / Plant Disease)
                        who have <strong>no fixed imprest</strong> recorded in the ledger.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('imprest.index') }}" class="btn btn-soft-secondary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i> Back to Imprest Requests
                    </a>
                    <button onclick="window.print()" class="btn btn-soft-primary btn-sm">
                        <i class="ri-printer-line me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card" style="border-left:4px solid #3b82f6;">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3">
                                <i class="ri-group-line"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Total Eligible</p>
                            <h4 class="mb-0">{{ $summary['total_eligible'] }}</h4>
                            <p class="text-muted mb-0 fs-11">Sales + PD employees in J1–M1</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card" style="border-left:4px solid #22c55e;">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">
                                <i class="ri-checkbox-circle-line"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Have Full Imprest</p>
                            <h4 class="mb-0">{{ $summary['with_imprest'] }}</h4>
                            <p class="text-muted mb-0 fs-11">Full ledger entry exists</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card" style="border-left:4px solid #ef4444;">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3">
                                <i class="ri-alert-line"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Missing / Shortfall</p>
                            <h4 class="mb-0 text-danger">{{ $summary['missing'] }}</h4>
                            <p class="text-muted mb-0 fs-11">
                                @if(!empty($summary['upgrade_count']))
                                    {{ $summary['upgrade_count'] }} grade upgrade(s)
                                @else
                                    Shortfall or no entry
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card" style="border-left:4px solid #f59e0b;">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3">
                                <i class="ri-money-rupee-circle-line"></i>
                            </span>
                        </div>
                        <div>
                            <p class="text-muted mb-0 fs-12 text-uppercase fw-semibold">Total Pending</p>
                            <h4 class="mb-0">₹ {{ number_format($summary['total_pending']) }}</h4>
                            <p class="text-muted mb-0 fs-11">Pending amount to be released</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
                <div>
                    <label class="form-label mb-1 fs-12 fw-semibold">Department</label>
                    <select name="department" class="form-select form-select-sm" style="min-width:160px;">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1 fs-12 fw-semibold">Grade</label>
                    <select name="grade" class="form-select form-select-sm" style="min-width:100px;">
                        <option value="">All Grades</option>
                        @foreach($grades as $g)
                        <option value="{{ $g }}" {{ request('grade') == $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1 fs-12 fw-semibold">Type</label>
                    <select name="type" class="form-select form-select-sm" style="min-width:140px;">
                        <option value="">All Types</option>
                        <option value="upgrade" {{ request('type') == 'upgrade' ? 'selected' : '' }}>Grade Upgrade (Delta)</option>
                        <option value="new" {{ request('type') == 'new' ? 'selected' : '' }}>New / No Imprest</option>
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1 fs-12 fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:130px;">
                        <option value="">All Statuses</option>
                        <option value="missing" {{ request('status') == 'missing' ? 'selected' : '' }}>Pending / Missing</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Released / Approved</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ri-filter-line me-1"></i> Filter
                    </button>
                    <a href="{{ route('imprest.missing') }}" class="btn btn-soft-secondary btn-sm ms-1">
                        <i class="ri-refresh-line me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-header d-flex align-items-center py-2">
            <h5 class="card-title mb-0 flex-grow-1">
                <i class="ri-table-line me-1 text-danger"></i>
                Employees Missing Fixed Imprest
                <span class="badge bg-danger-subtle text-danger ms-1">{{ $missing->count() }}</span>
            </h5>
        </div>
        <div class="card-body p-0">
            @if($missing->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="ri-checkbox-circle-line fs-1 text-success d-block mb-2"></i>
                <h5 class="text-success">All eligible employees have fixed imprest!</h5>
                <p class="mb-0 fs-13">No missing entries found for the selected filters.</p>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" style="font-size:13px;">
                    <thead class="table" style="background-color: #dde6ff; color: #1e3a8a;">
                        <tr>
                            <th class="text-center" style="width:45px;">#</th>
                            <th>Employee Name</th>
                            <th>Emp Code</th>
                            <th>Department</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Date of Joining</th>
                            <th class="text-center">Date of Promotion</th>
                            <th class="text-end">Eligible Amount</th>
                            <th class="text-end">Current Imprest</th>
                            <th class="text-end">Pending Release</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($missing->groupBy('department_name') as $dept => $emps)
                        {{-- Department group header --}}
                        <tr style="background:#f0f4ff;">
                            <td colspan="11" class="fw-bold py-2 px-3" style="font-size:12px;color:#1e3a8a;letter-spacing:0.5px;">
                                <i class="ri-building-line me-1"></i>
                                {{ strtoupper($dept) }}
                                <span class="badge bg-primary-subtle text-primary ms-2">{{ $emps->count() }} employees</span>
                                <span class="badge bg-warning-subtle text-warning ms-1">
                                    ₹ {{ number_format($emps->sum('request_amount')) }} pending
                                </span>
                            </td>
                        </tr>
                        @foreach($emps as $i => $emp)
                        <tr>
                            <td class="text-center text-muted">{{ $loop->parent->index * 100 + $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                    {{ trim($emp->emp_name) }}
                                    @if($emp->is_upgrade)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:10px;" title="Grade upgraded - partial imprest received">
                                            <i class="ri-arrow-up-circle-line me-1"></i>Upgrade
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:11px;">
                                    {{ $emp->emp_code ?: '—' }}
                                </span>
                            </td>
                            <td>{{ $emp->department_name }}</td>
                            <td class="text-center">
                                @php
                                    $cleanG = strtoupper(str_replace(['-', ' '], '', $emp->grade_name));
                                    $gradeColor = in_array($cleanG, ['J1','J2'])
                                        ? 'bg-info-subtle text-info'
                                        : 'bg-warning-subtle text-warning';
                                @endphp
                                <span class="badge {{ $gradeColor }} fs-11">{{ strtoupper($emp->grade_name) }}</span>
                            </td>
                            <td class="text-center" style="font-size:12px;">
                                {{ $emp->DateJoining && \App\Support\ZeroDate::has($emp->DateJoining)
                                    ? \Carbon\Carbon::parse($emp->DateJoining)->format('d M Y')
                                    : '—' }}
                            </td>
                            <td class="text-center" style="font-size:12px;">
                                @if($emp->promotion_date && $emp->promotion_date !== '-')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-size-11">
                                        <i class="ri-arrow-up-circle-line me-1"></i>{{ $emp->promotion_date }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end text-muted">
                                ₹ {{ number_format($emp->eligible_amount) }}
                            </td>
                            <td class="text-end">
                                @if($emp->current_imprest > 0)
                                    <span class="fw-medium text-dark">₹ {{ number_format($emp->current_imprest) }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-success">
                                ₹ {{ number_format($emp->request_amount) }}
                            </td>
                            <td class="text-center" id="action-td-{{ $emp->EmployeeID }}">
                                @if($emp->imprest_status === 'approved')
                                <span class="badge bg-success-subtle text-success fs-11 px-2 py-1">
                                    <i class="ri-checkbox-circle-line me-1"></i>Released
                                </span>
                                @elseif($emp->imprest_status === 'rejected')
                                <span class="badge bg-danger-subtle text-danger fs-11 px-2 py-1">
                                    <i class="ri-close-circle-line me-1"></i>Rejected
                                </span>
                                @if(!empty($emp->imprest_remarks))
                                    <div class="text-muted fs-11 mt-1 text-wrap text-center" style="max-width:180px;margin:0 auto;">
                                        <i class="ri-information-line me-1"></i>{{ $emp->imprest_remarks }}
                                    </div>
                                @endif
                                @else
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    @can('imprest.approve')
                                    <button class="btn btn-xs btn-success release-btn"
                                            data-id="{{ $emp->EmployeeID }}"
                                            data-name="{{ trim($emp->emp_name) }}"
                                            data-code="{{ $emp->emp_code }}"
                                            data-amount="{{ number_format($emp->request_amount) }}"
                                            data-eligible="{{ number_format($emp->eligible_amount) }}"
                                            data-current="{{ number_format($emp->current_imprest) }}"
                                            data-upgrade="{{ $emp->is_upgrade ? 1 : 0 }}"
                                            style="font-size:11px;padding:2px 8px;"
                                            title="Release Imprest & Apply Auto Advance">
                                        <i class="ri-send-plane-line me-1"></i>Release
                                    </button>
                                    <button class="btn btn-xs btn-outline-danger reject-btn"
                                            data-id="{{ $emp->EmployeeID }}"
                                            data-name="{{ trim($emp->emp_name) }}"
                                            style="font-size:11px;padding:2px 8px;"
                                            title="Reject Imprest">
                                        <i class="ri-close-line me-1"></i>Reject
                                    </button>
                                    @endcan
                                    <a href="{{ route('expense-payments.ledger-summary') }}?search={{ urlencode($emp->emp_code) }}"
                                        class="btn btn-xs btn-soft-secondary" style="font-size:11px;padding:2px 6px;"
                                        target="_blank" title="Open in Ledger Summary">
                                        <i class="ri-book-open-line"></i>
                                    </a>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="9" class="text-end py-2">Total Pending Release:</td>
                            <td class="text-end text-success fs-14">₹ {{ number_format($missing->sum('request_amount')) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .btn, .card-header a, form { display: none !important; }
    .page-content { padding: 0 !important; }
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
<script>
const csrf = @json(csrf_token());

// Release Missing Imprest & Apply Auto Advance
$(document).on('click', '.release-btn', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');
    const code = $(this).data('code');
    const amt = $(this).data('amount');
    const eligible = $(this).data('eligible');
    const current = $(this).data('current');
    const isUpgrade = $(this).data('upgrade') == 1;

    let bodyHtml = `<p class="mb-2">Release <strong>₹${amt}</strong> imprest for <strong>${name}</strong> (${code})?</p>`;
    if (isUpgrade) {
        bodyHtml = `
            <p class="mb-2">Release remaining shortfall of <strong>₹${amt}</strong> for <strong>${name}</strong> (${code})?</p>
            <div class="d-flex justify-content-between bg-light rounded p-2 mb-2 fs-12 border text-start">
                <div><span class="text-muted">Current Imprest:</span><br><strong>₹${current}</strong></div>
                <div><span class="text-muted">New Eligibility:</span><br><strong>₹${eligible}</strong></div>
                <div><span class="text-muted">Releasing Delta:</span><br><strong class="text-success">₹${amt}</strong></div>
            </div>
        `;
    }

    Swal.fire({
        title: isUpgrade ? 'Release Grade Upgrade Imprest' : 'Release Imprest & Apply Auto Advance',
        html: `${bodyHtml}
               <div class="alert alert-info py-2 px-3 fs-12 mb-2 text-start">
                   <i class="ri-information-line me-1"></i> Releasing this imprest will automatically apply for an <strong>Auto Advance</strong> request of <strong>₹${amt}</strong> submitted directly to Finance.
               </div>
               <textarea id="releaseRemarks" class="form-control" placeholder="Remarks (optional)" aria-label="Remarks (optional)" rows="2"></textarea>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-send-plane-line me-1"></i> Release & Apply Advance',
        confirmButtonColor: '#0ab39c',
        cancelButtonText: 'Cancel',
    }).then(r => {
        if (!r.isConfirmed) return;

        Swal.fire({
            title: 'Processing...',
            text: 'Releasing imprest and submitting Auto Advance...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post(`/imprest-automation/missing-imprest/${id}/release`, {
            _token: csrf,
            remarks: $('#releaseRemarks').val()
        }).done(res => {
            if (res.success) {
                $(`#action-td-${id}`).html(`
                    <span class="badge bg-success-subtle text-success fs-11 px-2 py-1">
                        <i class="ri-checkbox-circle-line me-1"></i>Released
                    </span>
                `);
                Swal.fire({
                    icon: 'success',
                    title: 'Released!',
                    text: res.message,
                    timer: 2500,
                    showConfirmButton: true
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to release imprest.', 'error');
            }
        }).fail(xhr => {
            const msg = xhr.responseJSON?.message || 'Server error occurred.';
            Swal.fire('Error', msg, 'error');
        });
    });
});

// Reject Missing Imprest
$(document).on('click', '.reject-btn', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    Swal.fire({
        title: 'Reject Imprest Request',
        html: `<p>Reject imprest release for <strong>${name}</strong>?</p>
               <textarea id="rejectRemarks" class="form-control mt-2" placeholder="Reason for rejection (required)" aria-label="Reason for rejection (required)" rows="2"></textarea>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Reject',
        confirmButtonColor: '#f06548',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const remarks = $('#rejectRemarks').val().trim();
            if (!remarks) {
                Swal.showValidationMessage('Reason for rejection is required');
                return false;
            }
            return remarks;
        }
    }).then(r => {
        if (!r.isConfirmed) return;

        Swal.fire({
            title: 'Processing...',
            text: 'Rejecting imprest request...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post(`/imprest-automation/missing-imprest/${id}/reject`, {
            _token: csrf,
            remarks: r.value
        }).done(res => {
            if (res.success) {
                $(`#action-td-${id}`).html(`
                    <span class="badge bg-danger-subtle text-danger fs-11 px-2 py-1">
                        <i class="ri-close-circle-line me-1"></i>Rejected
                    </span>
                `);
                Swal.fire({
                    icon: 'success',
                    title: 'Rejected',
                    text: res.message,
                    timer: 1800,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to reject imprest.', 'error');
            }
        }).fail(xhr => {
            const msg = xhr.responseJSON?.message || 'Server error occurred.';
            Swal.fire('Error', msg, 'error');
        });
    });
});
</script>
@endpush
