@extends('layouts.app')

@section('content')
{{-- REP-32: VVNR stepwise pending payment report --}}
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-route-line me-2 text-primary"></i>Stepwise Pending Payment (VVNR)</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item">Reports</li>
                            <li class="breadcrumb-item active">Stepwise Pending Payment</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-soft-primary border-bottom d-flex justify-content-between align-items-center py-2">
                <h6 class="card-title mb-0 text-primary"><i class="ri-filter-3-line me-1"></i> Claim &rarr; manager approval &rarr; payment &rarr; verification &rarr; adjustment in a later month</h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success" id="vpBtnExport"><i class="ri-file-excel-line me-1"></i> Export Excel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="vpBtnRefresh"><i class="ri-refresh-line me-1"></i> Refresh</button>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Financial Year</label>
                        <select class="form-select form-select-sm vp-filter" id="vpYear">
                            @foreach($financialYears as $fy)
                                <option value="{{ $fy->YearId }}" {{ $fy->YearId == $yearId ? 'selected' : '' }}>{{ $fy->Year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Month</label>
                        <select class="form-select form-select-sm vp-filter" id="vpMonth">
                            <option value="">All months</option>
                            @foreach($monthNames as $n => $m)<option value="{{ $n }}">{{ $m }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Department</label>
                        <select class="form-select form-select-sm vp-filter" id="vpDept">
                            <option value="">All departments</option>
                            @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->department_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Reporting manager</label>
                        <select class="form-select form-select-sm vp-filter" id="vpApprover">
                            <option value="">All managers</option>
                            @foreach($approvers as $a)<option value="{{ $a->EmployeeID }}">{{ $a->EmpCode }} - {{ trim($a->Fname.' '.($a->Sname ?? '').' '.$a->Lname) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Employee</label>
                        <select class="form-select form-select-sm vp-filter" id="vpEmployee">
                            <option value="">All employees</option>
                            @foreach($employees as $e)<option value="{{ $e->EmployeeID }}">{{ $e->EmpCode }} - {{ trim($e->Fname.' '.($e->Sname ?? '').' '.$e->Lname) }}{{ $e->EmpStatus !== 'A' ? ' (left)' : '' }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Step</label>
                        <select class="form-select form-select-sm vp-filter" id="vpStep">
                            <option value="">All pending steps</option>
                            @foreach($steps as $k => $s)<option value="{{ $k }}">{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input vp-filter" type="checkbox" id="vpClosed">
                            <label class="form-check-label small" for="vpClosed">Also show closed months (nothing pending)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3" id="vpStepCards"></div>
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-2">
                <div class="small fw-bold text-muted mb-1">Ageing of pending months (days at the current step)</div>
                <div class="row g-2" id="vpAgeCards"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="small text-muted mb-2">
                    Step 4 difference = verified amount &minus; approved amount (VVNR pays on the approved amount). Negative = paid in excess, to be recovered in a later payment.
                    A month whose difference was carried into a later payment is closed and shows "Adjusted in ...". Sorted newest month first.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle w-100" id="vpTable">
                        <thead>
                            <tr class="text-nowrap" style="background-color:#15648C;color:#fff;">
                                <th>Month</th><th>Employee</th><th>Department</th><th>Reporting manager</th><th>Step</th><th class="text-end">Days</th>
                                <th class="text-end">Step amount</th><th class="text-end">Claimed</th><th class="text-end">Approved</th><th class="text-end">Paid</th>
                                <th class="text-end">Verified</th><th class="text-end">Difference</th><th>Dates</th><th>Note</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <x-report-stamp />
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const STEP_COLOURS = {approval: 'warning', payment: 'primary', verification: 'info', adjustment: 'danger', hold: 'secondary', closed: 'success'};
    const inr = v => '₹' + Number(v || 0).toLocaleString('en-IN', {maximumFractionDigits: 0});
    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();
    let table = null;

    function params() {
        return {
            year_id: $('#vpYear').val(), month: $('#vpMonth').val(), department_id: $('#vpDept').val(),
            approver_id: $('#vpApprover').val(), employee_id: $('#vpEmployee').val(), step: $('#vpStep').val(),
            include_closed: $('#vpClosed').is(':checked') ? 1 : 0
        };
    }

    function cards(summary) {
        let h = '';
        $.each(summary.steps, function (k, s) {
            if (k === 'closed' && !$('#vpClosed').is(':checked')) return;
            h += '<div class="col"><div class="card border-0 shadow-sm mb-0 vp-card" role="button" data-step="' + k + '" title="Show only this step">'
               + '<div class="card-body py-2 border-start border-4 border-' + STEP_COLOURS[k] + '">'
               + '<div class="small text-muted">' + esc(s.label) + '</div>'
               + '<div class="fs-5 fw-bold">' + s.count + ' <span class="fs-6 fw-normal text-muted">months</span></div>'
               + '<div class="small fw-semibold">' + (k === 'adjustment' ? 'Excess paid ' + inr(s.excess) + ' · short ' + inr(s.short) : inr(s.amount)) + '</div></div></div></div>';
        });
        $('#vpStepCards').html(h);
        let a = '';
        $.each(summary.ageing, function (b, s) {
            a += '<div class="col-6 col-md-3"><div class="p-2 rounded bg-light border"><div class="small text-muted">' + b + ' days</div>'
               + '<div class="fw-bold">' + s.count + ' months · ' + inr(s.amount) + '</div></div></div>';
        });
        $('#vpAgeCards').html(a);
    }

    function load() {
        $.get("{{ route('reports.vvnr.pending.payment.data') }}", params()).done(function (res) {
            if (!res.success) { Swal.fire('Error', res.message || 'Could not load', 'error'); return; }
            cards(res.summary);
            const data = res.rows.map(r => [
                '<span class="d-none">' + String(r.fy_pos).padStart(2, '0') + '</span>' + r.month_name,
                '<div class="fw-semibold">' + esc(r.emp_code) + '</div><div class="small">' + esc(r.emp_name) + (r.emp_status === 'Left' ? ' <span class="badge bg-danger-subtle text-danger">left</span>' : '') + '</div>',
                esc(r.department), '<span class="small">' + esc(r.rm) + '</span>',
                '<span class="badge bg-' + STEP_COLOURS[r.step] + '-subtle text-' + STEP_COLOURS[r.step] + '">' + esc(r.step_label) + '</span>',
                r.step === 'closed' ? '' : r.age_days,
                '<span class="fw-semibold">' + inr(r.step_amount) + '</span>',
                inr(r.claimed), inr(r.approved), inr(r.paid), inr(r.verified),
                r.difference ? '<span class="' + (r.difference < 0 ? 'text-danger' : 'text-success') + '">' + inr(r.difference) + '</span>' : '',
                '<div class="small text-nowrap">Sub ' + esc(r.submitted_on) + (r.approved_on ? '<br>Appr ' + esc(r.approved_on) : '') + (r.paid_on ? '<br>Paid ' + esc(r.paid_on) : '') + (r.verified_on ? '<br>Ver ' + esc(r.verified_on) : '') + '</div>',
                '<span class="small">' + esc(r.note) + '</span>'
            ]);
            if (table) { table.clear().rows.add(data).draw(); return; }
            table = $('#vpTable').DataTable({
                data: data, pageLength: 50, order: [[0, 'desc'], [5, 'desc']],
                columnDefs: [{targets: [5, 6, 7, 8, 9, 10, 11], className: 'text-end'}],
                language: {emptyTable: 'Nothing pending for these filters.'}
            });
        }).fail(function (x) { Swal.fire('Error', x.responseJSON?.message || 'Could not load the report', 'error'); });
    }

    $(document).on('click', '.vp-card', function () { $('#vpStep').val($(this).data('step')); load(); });
    $('.vp-filter').on('change', load);
    $('#vpBtnRefresh').on('click', load);
    $('#vpBtnExport').on('click', function () { window.location.href = "{{ route('reports.vvnr.pending.payment.export') }}?" + $.param(params()); });
    load();
});
</script>
@endpush
