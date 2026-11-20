{{-- FNF-01 (20 Nov 2026, tracker tab "FNF_Xeasy 20 Nov", sections 7-8): FNF - employee-wise Finance clearance --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => number_format((float) $n, 2); @endphp
<style>
    .fnf-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .fnf-pay { color: #047857; } .fnf-rec { color: #b91c1c; }
    .fnf-work td, .fnf-work th { padding: 4px 8px; font-size: 13px; }
    .fnf-work tr.tot td { font-weight: 600; border-top: 2px solid #cbd5e1; }
</style>
<div class="page-content"><div class="container-fluid xe-screen">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h4 class="mb-0 me-auto"><i class="ri-user-unfollow-line me-1"></i>FNF - Finance clearance</h4>
        <a class="xe-btn xe-btn-plain" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="ri-file-excel-2-line"></i>Excel (CSV)</a>
    </div>
    <p class="text-muted small mb-2">Employees whose HR clearance is done in ESS and whose Finance clearance is pending. Final balance = expense / payment due (payment hold + eligible expenses) - ledger balance held by the employee (advance, imprest) + HR recovery / payment. Positive: pay the employee; negative: recover.</p>
    @unless ($hrTable)
        <div class="alert alert-warning py-2 small">The ESS separation table (HRIMS hrm_employee_separation) is not on this server, so HR clearance cannot be checked here: employees with a separation date in the last 180 days are listed instead.</div>
    @endunless
    <div class="xe-seg mb-2">
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'pending']) }}" class="{{ $tab === 'pending' ? 'active' : '' }}">Pending Finance clearance</a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'cleared']) }}" class="{{ $tab === 'cleared' ? 'active' : '' }}">Finance clearance done</a>
    </div>
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Last working day</th><th class="fnf-num">Ledger balance (held)</th><th class="fnf-num">Payment hold</th><th class="fnf-num">Eligible expense</th><th class="fnf-num">Expense balance</th><th class="fnf-num">HR recovery / payment</th><th class="fnf-num">Final balance</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                @php($w = $r['w'])
                <tr>
                    <td><span class="fw-semibold">{{ $r['e']->EmpCode }}</span> {{ $r['e']->name }}<div class="small text-muted">{{ $r['e']->department_name }}@if (\App\Support\ZeroDate::has($r['e']->DateOfResignation)) · resigned {{ date('d-m-Y', strtotime($r['e']->DateOfResignation)) }}@endif</div></td>
                    <td class="text-nowrap">{{ \App\Support\ZeroDate::has($r['e']->DateOfSepration) ? date('d-m-Y', strtotime($r['e']->DateOfSepration)) : '-' }}</td>
                    <td class="fnf-num">{{ $inr($w['ledger_balance']) }}</td>
                    <td class="fnf-num">{{ $inr($w['hold_amount']) }}</td>
                    <td class="fnf-num">{{ $inr($w['expense_current'] + $w['expense_previous'] + $w['expense_other']) }}@if ($w['waiting'] > 0)<div class="small text-warning">{{ $inr($w['waiting']) }} awaiting hard copy</div>@endif</td>
                    <td class="fnf-num">{{ $inr($w['expense_total']) }}</td>
                    <td class="fnf-num">{{ $r['hr'] ? $inr($r['hr']) : '-' }}</td>
                    <td class="fnf-num fw-semibold {{ $r['final'] >= 0 ? 'fnf-pay' : 'fnf-rec' }}">{{ $r['final'] >= 0 ? 'Pay' : 'Recover' }} {{ $inr(abs($r['final'])) }}</td>
                    <td><button type="button" class="xe-btn xe-btn-plain" data-fnf="{{ $r['e']->EmployeeID }}">Working</button></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ $tab === 'pending' ? 'No employee is waiting for Finance clearance.' : 'No Finance clearance done yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
</div></div>

<div class="modal fade" id="fnfModal" tabindex="-1" aria-labelledby="fnfTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header py-2"><h6 class="modal-title" id="fnfTitle">FNF working</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body" id="fnfBody"></div>
    </div></div>
</div>
@endsection
@push('scripts')
<script>
$(function () {
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const esc = s => $('<div>').text(s ?? '').html();
    const fmt = n => Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const d = s => s && s > '2000-01-01' ? String(s).substring(0, 10).split('-').reverse().join('-') : '-';
    let cur = null;
    function render(j) {
        const w = j.w, s = j.s || {}, done = s.status === 'cleared';
        const fin = (h) => Number(w.expense_total) - Number(w.ledger_balance) + Number(h || 0);
        const line = (n, t, a, src) => `<tr><td>${n}</td><td>${t}</td><td class="fnf-num">${a}</td><td class="small text-muted">${src}</td></tr>`;
        const months = (w.months || []).map(m => `<tr><td>${esc(m.label)}</td><td>${esc(m.status)}</td><td class="fnf-num">${m.claims}</td><td class="fnf-num">${m.odo_days || '-'}</td><td class="fnf-num">${fmt(m.eligible)}</td><td class="fnf-num">${m.waiting ? fmt(m.waiting) : '-'}</td><td class="fnf-num">${m.excluded ? fmt(m.excluded) : '-'}</td><td class="small">${(m.notes || []).map(esc).join('<br>')}</td></tr>`).join('');
        const holds = (w.hold || []).map(h => `<tr><td>${esc(h.label)}</td><td class="fnf-num">${fmt(h.amount)}</td><td class="small text-muted">${esc(h.remark)}</td></tr>`).join('');
        $('#fnfTitle').text(`FNF working - ${j.e.EmpCode} ${j.e.name}`);
        $('#fnfBody').html(`
            <div class="row g-3">
              <div class="col-lg-5">
                <div class="small text-muted mb-1">Last working day ${d(j.e.DateOfSepration)} · resigned ${d(j.e.DateOfResignation)}${done ? ' · <span class="text-success">Finance clearance done ' + d(s.cleared_at) + '</span>' : ''}</div>
                <table class="table table-sm fnf-work mb-2"><tr class="table-light"><th>#</th><th>Particulars</th><th class="fnf-num">Amount (₹)</th><th></th></tr>
                  ${line(1, 'Ledger balance (advance / imprest held)', '- ' + fmt(w.ledger_balance), 'System')}
                  ${line(2, 'Payment hold amount (after resignation)', fmt(w.hold_amount), 'System')}
                  ${line(3, 'Current month eligible expense', fmt(w.expense_current), 'Expense rules')}
                  ${line(4, 'Previous month pending eligible expense', fmt(w.expense_previous), 'Expense rules')}
                  ${line(5, 'Other eligible pending expenses', fmt(w.expense_other), 'Expense rules')}
                  <tr class="tot"><td>6</td><td>Total expense / payment adjustment (2 to 5)</td><td class="fnf-num">${fmt(w.expense_total)}</td><td></td></tr>
                  <tr><td>7</td><td>HR recovery (-) / payment (+)</td><td class="fnf-num"><input type="number" step="0.01" id="fnfHr" class="form-control form-control-sm text-end" value="${Number(j.hr || 0)}" ${done ? 'disabled' : ''} aria-label="HR recovery or payment"></td><td class="small text-muted">HR</td></tr>
                  <tr class="tot"><td>8</td><td>Final FNF balance</td><td class="fnf-num" id="fnfFinal"></td><td></td></tr>
                </table>
                <label class="small text-muted" for="fnfHrRemark">HR remark</label><input id="fnfHrRemark" class="form-control form-control-sm mb-2" maxlength="500" value="${esc(s.hr_remark || '')}" ${done ? 'disabled' : ''}>
                <label class="small text-muted" for="fnfRemark">Finance remark</label><input id="fnfRemark" class="form-control form-control-sm mb-2" maxlength="500" value="${esc(done ? (s.clear_remark || '') : '')}">
                <div class="d-flex gap-2">${done
                    ? '<button class="btn btn-sm btn-outline-secondary" data-act="reopen">Reopen</button>'
                    : '<button class="btn btn-sm btn-outline-primary" data-act="save">Save</button><button class="btn btn-sm btn-success" data-act="clear">Finance clearance done</button>'}</div>
                ${w.waiting > 0 ? `<div class="small text-warning mt-2">₹${fmt(w.waiting)} of claims wait for hard copies until ${d(w.hard_copy_until)}; clearance is possible after that date.</div>` : ''}
                ${w.excluded > 0 ? `<div class="small text-muted mt-1">₹${fmt(w.excluded)} left out by the expense rules (see the months).</div>` : ''}
              </div>
              <div class="col-lg-7">
                <p class="fw-semibold small mb-1">Payment hold (rule 1)</p>
                <table class="table table-sm fnf-work mb-3"><tr class="table-light"><th>Month</th><th class="fnf-num">Amount</th><th>Hold remark</th></tr>${holds || '<tr><td colspan="3" class="text-muted">No month on hold.</td></tr>'}</table>
                <p class="fw-semibold small mb-1">Months not yet approved for payment (rule 2)</p>
                <table class="table table-sm fnf-work"><tr class="table-light"><th>Month</th><th>Status</th><th class="fnf-num">Claims</th><th class="fnf-num">Odo days</th><th class="fnf-num">Eligible</th><th class="fnf-num">Waiting</th><th class="fnf-num">Left out</th><th>Rule</th></tr>${months || '<tr><td colspan="8" class="text-muted">None.</td></tr>'}</table>
              </div>
            </div>`);
        const upd = () => { const f = fin($('#fnfHr').val()); $('#fnfFinal').html(`<span class="${f >= 0 ? 'fnf-pay' : 'fnf-rec'}">${f >= 0 ? 'Pay' : 'Recover'} ${fmt(Math.abs(f))}</span>`); };
        $('#fnfHr').on('input', upd); upd();
    }
    $(document).on('click', '[data-fnf]', function () {
        cur = $(this).data('fnf');
        $('#fnfBody').html('<div class="text-muted">Working it out…</div>');
        bootstrap.Modal.getOrCreateInstance($('#fnfModal')[0]).show();
        $.getJSON(@json(url('fnf')) + '/' + cur).done(render).fail(x => $('#fnfBody').html('<div class="text-danger">' + esc((x.responseJSON && x.responseJSON.message) || 'Could not load.') + '</div>'));
    });
    $(document).on('click', '#fnfBody [data-act]', function () {
        const act = $(this).data('act');
        if (act === 'clear' && !confirm('Mark the Finance clearance done? The working is kept as it is now.')) return;
        $(this).prop('disabled', true);
        $.ajax({url: @json(url('fnf')) + '/' + cur, method: 'POST', headers: {'X-CSRF-TOKEN': CSRF},
            data: {action: act, hr_amount: $('#fnfHr').val(), hr_remark: $('#fnfHrRemark').val(), remark: $('#fnfRemark').val()}})
            .done(r => { Swal.fire('Done', r.message, 'success').then(() => location.reload()); })
            .fail(x => { Swal.fire('Not saved', (x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {}).flat().join(' '))) || 'Not saved.', 'warning'); $(this).prop('disabled', false); });
    });
});
</script>
@endpush
