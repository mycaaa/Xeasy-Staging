@extends('layouts.app')

@section('title', 'Bill To Company (BTC)')

@section('content')
{{-- BTC-01: Bill To Company process (config/btc.php) --}}
<style>
    .btc-tabs .nav-link { font-size: 12px; padding: 6px 12px; }
    .btc-table td, .btc-table th { font-size: 12px; vertical-align: middle; }
    .btc-flow { font-size: 11px; color: #6b7280; }
    .btc-step { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; margin: 2px 0; }
    .btc-step.done { background: #dcfce7; } .btc-step.now { background: #405189; color: #fff; }
    .btc-log { font-size: 11px; border-left: 2px solid #c7d2fe; padding-left: 8px; margin-bottom: 6px; }
    .btc-label { font-size: 11px; color: #6b7280; margin-bottom: 0; }
    .btc-val { font-size: 13px; }
</style>
<div class="page-content">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <div>
                <h5 class="fw-bold mb-0" style="color:#1e3a5f;"><i class="ri-bill-line me-1 text-primary"></i> Bill To Company (BTC)</h5>
                <div class="btc-flow">Requirement → Admin booking / quotation + manager approval + advance → service → final bill → DMS → Finance verification &amp; JV → Checker → Treasury → Department approval → Payment → Closure</div>
            </div>
            <div class="d-flex gap-2">
                @can('btc.finance')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.export.boarding') }}"><i class="ri-plane-line"></i> Boarding passes pending</a>
                    @if (auth()->user()->can('btc.finance') || auth()->user()->can('btc.admin'))
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('btc.vendor.links') }}"><i class="ri-links-line"></i> Vendor portal links</a>
                    @endif
                    <a class="btn btn-sm btn-outline-success" href="{{ route('btc.export.focus') }}"><i class="ri-file-excel-2-line"></i> Focus JV (new)</a>
                @endcan
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#btcNewModal"><i class="ri-add-line"></i> New BTC case</button>
            </div>
        </div>
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <ul class="nav nav-pills btc-tabs" id="btcTabs">
                    @foreach ($tabs as $k => $label)
                        <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#" data-tab="{{ $k }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
                <input type="text" class="form-control form-control-sm" id="btcSearch" placeholder="Search case, employee, vendor, bill, PNR" aria-label="Search case, employee, vendor, bill, PNR" style="width:280px;">
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-hover btc-table mb-0">
                        <thead class="table-light"><tr><th>Case</th><th>Route</th><th>Category</th><th>Employee</th><th>Vendor</th><th>Purpose</th><th>Bill</th><th class="text-end">Amount</th><th class="text-end">Net payable</th><th>Status</th><th>Pending docs</th><th>Updated</th></tr></thead>
                        <tbody id="btcRows"><tr><td colspan="12" class="text-center text-muted">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- New case --}}
<div class="modal fade" id="btcNewModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form id="btcNewForm" enctype="multipart/form-data">
            <div class="modal-header py-2"><h6 class="modal-title">New BTC case</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-6"><label class="btc-label">Route</label>
                        <select class="form-select form-select-sm" name="route" id="btcRoute">@foreach ($routes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="btc-label">Category</label>
                        <select class="form-select form-select-sm" name="category" id="btcCategory">@foreach ($categories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    @if ($canAdmin)
                        <div class="col-md-6"><label class="btc-label">Employee (Admin raising for someone)</label>
                            <select class="form-select form-select-sm" name="employee_id" id="btcEmployee"></select></div>
                    @endif
                    <div class="col-md-6"><label class="btc-label">Location</label><input class="form-control form-control-sm" name="location"></div>
                    <div class="col-12"><label class="btc-label">Purpose / business requirement</label><input class="form-control form-control-sm" name="purpose" required maxlength="500"></div>
                    <div class="col-md-3"><label class="btc-label">Service from</label><input type="date" class="form-control form-control-sm" name="service_from"></div>
                    <div class="col-md-3"><label class="btc-label">Service to</label><input type="date" class="form-control form-control-sm" name="service_to"></div>
                    <div class="col-md-6"><label class="btc-label">Vendor</label>
                        <select class="form-select form-select-sm" name="vendor_id" id="btcVendor"><option value="">— new / not listed —</option>@foreach ($vendors as $vd)<option value="{{ $vd->id }}">{{ $vd->vendor_name }}{{ $vd->gst_no ? ' · '.$vd->gst_no : '' }}</option>@endforeach</select></div>
                    <div class="col-md-6 btc-newvendor"><label class="btc-label">New vendor name (register in Vendor list too)</label><input class="form-control form-control-sm" name="vendor_name"></div>
                    <div class="col-md-6 btc-newvendor"><label class="btc-label">New vendor GSTIN</label><input class="form-control form-control-sm" name="vendor_gstin" maxlength="20"></div>
                    <div class="col-md-4 btc-b"><label class="btc-label">Quotation amount (₹)</label><input type="number" step="0.01" class="form-control form-control-sm" name="quotation_amount"></div>
                    <div class="col-md-4 btc-b"><label class="btc-label">Advance needed (₹)</label><input type="number" step="0.01" class="form-control form-control-sm" name="advance_amount"></div>
                    <div class="col-md-4 btc-air"><label class="btc-label">PNR</label><input class="form-control form-control-sm" name="pnr" maxlength="20"></div>
                    <div class="col-md-4 btc-air"><label class="btc-label">Sector</label><input class="form-control form-control-sm" name="travel_sector" placeholder="RPR-DEL" aria-label="RPR-DEL"></div>
                    <div class="col-md-4 btc-air"><label class="btc-label">Travel date</label><input type="date" class="form-control form-control-sm" name="travel_date"></div>
                    <div class="col-12 btc-c"><hr class="my-1"><div class="btc-label fw-semibold">Final bill</div></div>
                    <div class="col-md-3 btc-c"><label class="btc-label">Bill no.</label><input class="form-control form-control-sm" name="bill_no"></div>
                    <div class="col-md-3 btc-c"><label class="btc-label">Bill date</label><input type="date" class="form-control form-control-sm" name="bill_date" max="{{ now()->toDateString() }}"></div>
                    <div class="col-md-2 btc-c"><label class="btc-label">Taxable ₹</label><input type="number" step="0.01" class="form-control form-control-sm" name="taxable_amount"></div>
                    <div class="col-md-2 btc-c"><label class="btc-label">GST ₹</label><input type="number" step="0.01" class="form-control form-control-sm" name="gst_amount"></div>
                    <div class="col-md-2 btc-c"><label class="btc-label">Bill total ₹</label><input type="number" step="0.01" class="form-control form-control-sm" name="bill_amount"></div>
                    <div class="col-12"><label class="btc-label">Attachments</label>
                        <div id="btcFiles"></div>
                        <button type="button" class="btn btn-sm btn-link px-0" id="btcAddFile"><i class="ri-attachment-2"></i> Add file</button></div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-light" data-submit="0">Save draft</button>
                <button type="button" class="btn btn-sm btn-primary" data-submit="1">Submit</button>
            </div>
        </form>
    </div></div>
</div>

{{-- Case detail / actions --}}
<div class="modal fade" id="btcCaseModal" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header py-2"><h6 class="modal-title" id="btcCaseTitle">Case</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="btcCaseBody"></div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const STATUS = @json($statuses);
    const TDS = @json($tdsSections);
    const NATURES = @json($tdsNatures ?? []); // BTC-TDS-01
    const NATURE_BY_CAT = @json($tdsNatureByCategory ?? []);
    const fmt = v => (v === null || v === undefined || v === '') ? '' : Number(v).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = s => $('<div>').text(s ?? '').html();
    let tab = $('#btcTabs .nav-link.active').data('tab') || 'mine', timer;

    function load() {
        $.get('{{ route('btc.data') }}', {tab: tab, search: $('#btcSearch').val()}, function (r) {
            const rows = (r.data || []).map(c => `<tr role="button" data-id="${c.id}">
                <td class="fw-semibold">${esc(c.case_no)}</td><td>${esc(c.route)}</td><td>${esc(c.category)}</td><td>${esc(c.employee)}</td>
                <td>${esc(c.vendor)}</td><td>${esc(c.purpose)}</td><td>${esc(c.bill)}</td><td class="text-end">${fmt(c.amount)}</td>
                <td class="text-end">${fmt(c.net)}</td><td><span class="badge bg-primary-subtle text-primary">${esc(c.status_label)}</span></td>
                <td class="text-danger">${esc(c.pending)}</td><td>${esc(c.updated)}</td></tr>`).join('');
            $('#btcRows').html(rows || '<tr><td colspan="12" class="text-center text-muted">No cases</td></tr>');
        });
    }
    $('#btcTabs').on('click', '.nav-link', function (e) { e.preventDefault(); $('#btcTabs .nav-link').removeClass('active'); $(this).addClass('active'); tab = $(this).data('tab'); load(); });
    $('#btcSearch').on('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
    load();

    // new case form
    function fileRow(kind) {
        return `<div class="d-flex gap-2 mb-1"><select class="form-select form-select-sm" name="file_kinds[]" style="width:170px">
            ${['quotation','approval','bill','supporting','boarding_pass'].map(k => `<option value="${k}" ${k===kind?'selected':''}>${k.replace('_',' ')}</option>`).join('')}</select>
            <input type="file" class="form-control form-control-sm" name="files[]" accept=".pdf,.jpg,.jpeg,.png"></div>`;
    }
    function shape() {
        const r = $('#btcRoute').val(), c = $('#btcCategory').val();
        $('.btc-b').toggle(r === 'employee_advance'); $('.btc-c').toggle(r === 'direct_bill');
        $('.btc-air').toggle(c === 'air'); $('.btc-newvendor').toggle(!$('#btcVendor').val());
        $('#btcFiles').html(fileRow(r === 'employee_advance' ? 'quotation' : (r === 'direct_bill' ? 'bill' : 'supporting')));
    }
    $('#btcRoute, #btcCategory, #btcVendor').on('change', shape); shape();
    $('#btcAddFile').on('click', () => $('#btcFiles').append(fileRow('supporting')));
    if ($('#btcEmployee').length) {
        $('#btcEmployee').select2({dropdownParent: $('#btcNewModal'), placeholder: 'Myself', allowClear: true, ajax: {url: '{{ route('btc.approvers') }}', data: p => ({q: p.term}), processResults: d => ({results: d})}});
    }
    $('#btcNewForm [data-submit]').on('click', function () {
        const fd = new FormData($('#btcNewForm')[0]); fd.append('submit', $(this).data('submit'));
        $.ajax({url: '{{ route('btc.store') }}', method: 'POST', data: fd, processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
            .done(r => { Swal.fire('Saved', r.message, r.message.startsWith('Saved as draft') ? 'warning' : 'success'); bootstrap.Modal.getInstance(document.getElementById('btcNewModal')).hide(); $('#btcNewForm')[0].reset(); shape(); load(); })
            .fail(x => Swal.fire('Not saved', (x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {}).flat().join('\n'))) || 'Error', 'error'));
    });

    // case detail
    const FLOW = ['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid', 'closed'];
    const kv = (l, v) => v ? `<div class="col-md-3"><p class="btc-label">${l}</p><div class="btc-val">${v}</div></div>` : '';
    function actionForm(a, d) {
        const c = d.case, f = [];
        const inp = (n, l, t = 'text', v = '') => `<div class="col-md-3"><label class="btc-label">${l}</label><input class="form-control form-control-sm" type="${t}" name="${n}" value="${esc(v)}"></div>`;
        const rmk = (req) => `<div class="col-md-6"><label class="btc-label">Remark${req ? ' (required)' : ''}</label><input class="form-control form-control-sm" name="remark"></div>`;
        const file = (k, l) => `<div class="col-md-4"><label class="btc-label">${l}</label><input type="hidden" name="file_kinds[]" value="${k}"><input type="file" class="form-control form-control-sm" name="files[]" accept=".pdf,.jpg,.jpeg,.png"></div>`;
        switch (a) {
            case 'submit': f.push(rmk(false)); break;
            case 'manager_approve': case 'dept_approve': f.push(rmk(false)); break;
            case 'manager_reject': case 'cancel': f.push(rmk(true)); break;
            case 'advance_paid': f.push(inp('advance_paid_on', 'Advance paid on', 'date'), inp('advance_activity_id', 'Xeasy advance no. (if raised there)', 'number'), inp('tds_on_advance', 'TDS deducted on advance ₹', 'number', 0)); break;
            case 'booked': f.push(rmk(false)); break;
            case 'submit_bill': f.push(inp('bill_no', 'Bill no.', 'text', c.bill_no), inp('bill_date', 'Bill date', 'date', (c.bill_date || '').substring(0, 10)), inp('taxable_amount', 'Taxable ₹', 'number', c.taxable_amount), inp('gst_amount', 'GST ₹', 'number', c.gst_amount), inp('bill_amount', 'Bill total ₹', 'number', c.bill_amount), file('bill', 'Final bill'), file('supporting', 'Supporting')); if (c.category === 'air') f.push(inp('pnr', 'PNR', 'text', c.pnr), inp('travel_sector', 'Sector', 'text', c.travel_sector), inp('travel_date', 'Travel date', 'date', (c.travel_date || '').substring(0, 10)), file('boarding_pass', 'Boarding pass (if available)')); break;
            case 'dms_process': f.push(inp('dms_doc_no', 'DMS / Nova doc no.', 'text', c.dms_doc_no), `<div class="col-md-3 pt-3"><label><input type="checkbox" name="original_received" value="1"> Original bill received</label></div>`); break;
            case 'finance_verify': {
                const dup = (d.duplicates || []).length ? `<div class="col-12 text-danger small">Possible duplicate: ${d.duplicates.map(x => esc(x.case_no + ' (' + x.bill_no + ', ₹' + fmt(x.bill_amount) + ')')).join(', ')} <label class="ms-2"><input type="checkbox" name="duplicate_override" value="1"> not a duplicate</label></div>` : '';
                f.push(inp('expense_ledger', 'Expense ledger', 'text', c.expense_ledger || d.default_ledger), inp('cost_centre', 'Cost centre', 'text', c.cost_centre || c.department_name), inp('taxable_amount', 'Taxable ₹', 'number', c.taxable_amount || c.bill_amount),
                    `<div class="col-md-5"><label class="btc-label">Nature of payment (decides TDS)</label><select class="form-select form-select-sm" name="tds_nature" onchange="(function(s){const n=NATURES[s.value]; const r=s.form.querySelector('[name=tds_rate]'); if(n&&r){ const pan=(${JSON.stringify(c.vendor_gstin || '')}).toUpperCase(); const ind=pan.length===15&&['P','H'].includes(pan[5]); r.value=n.rate[ind?0:1]; }})(this)">${Object.entries(NATURES).map(([k, n]) => `<option value="${k}" ${k === (c.tds_nature || NATURE_BY_CAT[c.category] || 'none') ? 'selected' : ''}>${n.label}</option>`).join('')}</select></div>`,
                    inp('tds_rate', 'TDS rate %', 'number', c.tds_rate || 0), inp('advance_adjusted', 'Advance to set off ₹ (paid ₹' + fmt(c.advance_amount) + ', TDS on it ₹' + fmt(c.tds_on_advance) + ')', 'number', c.advance_amount || 0),
                    `<div class="col-md-3 pt-3"><label><input type="checkbox" name="duplicate_checked" value="1"> Duplicate checked in Focus</label></div>`, dup, rmk(false));
                break;
            }
            case 'check': f.push(inp('focus_voucher_no', 'Focus voucher no.', 'text', c.focus_voucher_no), rmk(false)); break;
            case 'send_dept_approval': f.push(`<div class="col-md-6"><label class="btc-label">Department head / authorised approver</label><select class="form-select form-select-sm" name="dept_approver_id" id="btcApprover"></select></div>`, rmk(false)); break;
            case 'pay': f.push(inp('paid_amount', 'Paid ₹ (net payable ₹' + fmt(c.net_payable) + ')', 'number', c.net_payable), inp('paid_on', 'Paid on', 'date'), inp('utr_number', 'UTR / bank ref'), file('payment_proof', 'Payment advice')); break;
            case 'close': f.push(rmk(false)); break;
            case 'return': f.push(`<div class="col-md-3"><label class="btc-label">Return to</label><select class="form-select form-select-sm" name="return_to"><option value="bill_submitted">Employee / Admin (bill)</option><option value="dms_processed">DMS</option><option value="finance_verified">Finance</option></select></div>`, rmk(true)); break;
            case 'receive_document': f.push(`<div class="col-md-3"><label class="btc-label">Document</label><select class="form-select form-select-sm" name="document"><option value="original">Original bill</option><option value="boarding_pass">Boarding pass</option></select></div>`, inp('received_on', 'Received on', 'date'), file('original', 'Scan (optional)')); break;
        }
        return f.join('');
    }
    const LABEL = {submit: 'Submit', manager_approve: 'Approve', manager_reject: 'Reject', advance_paid: 'Advance paid', booked: 'Mark booked', submit_bill: 'Submit bill', dms_process: 'DMS processed', finance_verify: 'Verify & prepare JV', check: 'Checked', send_dept_approval: 'Send for department approval', dept_approve: 'Approve payment', pay: 'Record payment', close: 'Close', return: 'Return for correction', receive_document: 'Document received', cancel: 'Cancel case'};

    function openCase(id) {
        $.get('{{ url('btc/case') }}/' + id, function (d) {
            const c = d.case, at = FLOW.indexOf(c.status);
            $('#btcCaseTitle').text(c.case_no + ' · ' + d.status_label);
            const flow = FLOW.map((s, i) => `<span class="btc-step ${i < at ? 'done' : (i === at ? 'now' : '')}">${STATUS[s]}</span>`).join(' › ');
            const files = d.files.map(f => `<a class="me-3" target="_blank" href="${f.url}"><i class="ri-file-line"></i> ${esc(f.kind.replace('_', ' '))}: ${esc(f.name)}</a>`).join('') || '<span class="text-muted">none</span>';
            const logs = d.logs.map(l => `<div class="btc-log"><b>${esc(l.at)}</b> ${esc(l.by)} · ${esc(l.action.replace(/_/g, ' '))} → ${esc(l.to)}${l.remark ? '<br>' + esc(l.remark) : ''}</div>`).join('');
            const acts = d.actions.map(a => `<button class="btn btn-sm ${['return', 'manager_reject', 'cancel'].includes(a) ? 'btn-outline-danger' : 'btn-outline-primary'} me-1 mb-1" data-act="${a}">${LABEL[a] || a}</button>`).join('');
            $('#btcCaseBody').html(`<div class="mb-2">${flow}</div>
                <div class="row g-2 mb-2">${kv('Route', esc(d.route_label))}${kv('Category', esc(d.category_label))}${kv('Employee', esc(c.emp_code + ' - ' + c.emp_name))}${kv('Department', esc(c.department_name))}
                ${kv('Purpose', esc(c.purpose))}${kv('Vendor', esc(c.vendor_name) + (c.new_vendor ? ' (new)' : ''))}${kv('Service', esc((c.service_from || '').substring(0, 10) + ' – ' + (c.service_to || '').substring(0, 10)))}${kv('Manager', esc(d.manager))}
                ${kv('Quotation ₹', fmt(c.quotation_amount))}${kv('Advance ₹', fmt(c.advance_amount))}${kv('Bill', esc((c.bill_no || '') + ' ' + (c.bill_date || '').substring(0, 10)))}${kv('Bill ₹', fmt(c.bill_amount))}
                ${kv('TDS ₹', fmt(c.tds_amount))}${kv('Net payable ₹', fmt(c.net_payable))}${kv('PNR / sector', esc((c.pnr || '') + ' ' + (c.travel_sector || '')))}${kv('Dept approver', esc(d.dept_approver))}
                ${kv('Paid', c.paid_amount ? '₹' + fmt(c.paid_amount) + ' on ' + esc((c.paid_on || '').substring(0, 10)) + ' · ' + esc(c.utr_number) : '')}${kv('Waiting for', esc(d.pending.join(', ')))}</div>
                <div class="mb-2"><p class="btc-label">Files</p>${files}</div>
                <div class="mb-2">${acts || '<span class="text-muted small">No step for you at this stage.</span>'}</div>
                <form id="btcActForm" class="row g-2 mb-3" style="display:none" enctype="multipart/form-data"></form>
                <p class="btc-label">History</p>${logs}`);
            $('#btcCaseBody [data-act]').on('click', function () {
                const a = $(this).data('act');
                $('#btcActForm').html(actionForm(a, d) + `<div class="col-12"><input type="hidden" name="action" value="${a}"><button type="button" class="btn btn-sm btn-primary" id="btcDo">${LABEL[a]}</button></div>`).show();
                if (a === 'send_dept_approval') $('#btcApprover').select2({dropdownParent: $('#btcCaseModal'), ajax: {url: '{{ route('btc.approvers') }}', data: p => ({q: p.term}), processResults: r => ({results: r})}});
                $('#btcDo').on('click', function () {
                    $.ajax({url: '{{ url('btc/case') }}/' + c.id + '/act', method: 'POST', data: new FormData($('#btcActForm')[0]), processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
                        .done(r => { Swal.fire('Done', r.message, 'success'); openCase(c.id); load(); })
                        .fail(x => Swal.fire('Not done', (x.responseJSON && x.responseJSON.message) || 'Error', 'warning'));
                });
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('btcCaseModal')).show();
        });
    }
    $('#btcRows').on('click', 'tr[data-id]', function () { openCase($(this).data('id')); });
});
</script>
@endpush
