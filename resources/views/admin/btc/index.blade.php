@extends('layouts.app')

@section('title', 'Bill To Company (BTC)')

@section('content')
{{-- BTC-01: Bill To Company process (config/btc.php) --}}
<style>
    .btc-tabs .nav-link { font-size: 12px; padding: 6px 12px; }
    .btc-table td, .btc-table th { font-size: 12px; vertical-align: middle; }
    .btc-flow { font-size: 11px; color: #6b7280; }
    .btc-step { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; margin: 2px 0; }
    .btc-step.done { background: #dcfce7; } .btc-step.now { background: #dde6ff; color: #1e3a8a; }
    .btc-log { font-size: 11px; border-left: 2px solid #c7d2fe; padding-left: 8px; margin-bottom: 6px; }
    .btc-label { font-size: 11px; color: #6b7280; margin-bottom: 0; }
    .btc-val { font-size: 13px; }
</style>
<div class="page-content">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <div>
                <h5 class="fw-bold mb-0" style="color:#1e3a5f;"><i class="ri-bill-line me-1 text-primary"></i> Bill To Company (BTC)</h5>
                @if ($showFlow)<div class="btc-flow">Requirement → Admin booking / quotation + manager approval + advance → service → final bill → DMS → Finance verification &amp; JV → Checker → Treasury → Department approval → Payment → Closure</div>@endif
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @php $u = auth()->user(); @endphp
                @if ($u->can('btc.dashboard') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.dashboard') }}"><i class="ri-dashboard-3-line"></i> Dashboard</a>@endif
                @if (\App\Http\Controllers\admin\BtcPagesController::hasTeam() || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-primary" href="{{ route('btc.team') }}"><i class="ri-team-line"></i> My team</a>@endif
                @if ($u->can('btc.register') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.register') }}"><i class="ri-list-check-2"></i> Register</a>@endif
                @if ($u->can('btc.booking') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.booking') }}"><i class="ri-hotel-line"></i> Booking queue</a>@endif
                @if ($u->can('btc.advance') || $u->can('btc.finance') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.advances') }}"><i class="ri-hand-coin-line"></i> Advances</a>@endif
                @if ($u->can('btc.invoices') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.invoices') }}"><i class="ri-inbox-archive-line"></i> Nova inbox</a>@endif
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
                <select class="form-select form-select-sm" id="btcApFilter" style="width:170px;display:none" aria-label="Approvals to show">
                    <option value="pending">Pending with me</option><option value="approved">Approved by me</option><option value="rejected">Rejected / returned by me</option><option value="all">All</option>
                </select>
                <input type="text" class="form-control form-control-sm" id="btcSearch" placeholder="Search case, employee, vendor, bill, PNR" aria-label="Search case, employee, vendor, bill, PNR" style="width:280px;">
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-hover btc-table mb-0">
                        <thead class="table-light"><tr><th>Case</th><th>Route</th><th>Category</th><th class="btc-emp-col">Employee</th><th>Vendor</th><th>Purpose</th><th class="text-end">Amount</th><th>Status</th><th>Pending with</th><th>Booking</th><th>Advance</th><th>Invoice</th><th>Payment</th><th>Updated</th></tr></thead>
                        <tbody id="btcRows"><tr><td colspan="14" class="text-center text-muted">Loading…</td></tr></tbody>
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
                    {{-- BTC-REQ-03: two clear choices (C only for the BTC admin) --}}
                    <div class="col-12">
                        <label class="btc-label">Request type</label>
                        <div class="row g-2">
                            @foreach ($routes as $k => $l)
                                <div class="col-md-{{ count($routes) > 2 ? 4 : 6 }}">
                                    <label class="border rounded p-2 d-block h-100 btc-choice" style="cursor:pointer">
                                        <input type="radio" class="form-check-input me-1 btc-route-radio" name="route" value="{{ $k }}" {{ $loop->first ? 'checked' : '' }}>
                                        <strong>{{ $routeChoices[$k][0] ?? $l }}</strong>
                                        <div class="small text-muted mt-1">{{ $routeChoices[$k][1] ?? '' }}</div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <input type="hidden" id="btcRoute" value="{{ array_key_first($routes) }}">
                    </div>
                    <div class="col-12 small text-muted">Raised for: <strong id="btcFor">{{ auth()->user()->name }}</strong> · {{ session('company_name', 'VNR Seeds') }} · department from HR</div>
                    <div class="col-md-6"><label class="btc-label">Category</label>
                        <select class="form-select form-select-sm" name="category" id="btcCategory">@foreach ($categories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    @if ($canAdmin)
                        <div class="col-md-6"><label class="btc-label">Employee (Admin raising for someone)</label>
                            <select class="form-select form-select-sm" name="employee_id" id="btcEmployee"></select></div>
                    @endif
                    <div class="col-md-6"><label class="btc-label">Location</label><input class="form-control form-control-sm" name="location"></div>
                    <div class="col-12"><label class="btc-label">Purpose / business requirement</label><input class="form-control form-control-sm" name="purpose" required maxlength="500"></div>
                    <div class="col-md-3"><label class="btc-label">Activity date (from)</label><input type="date" class="form-control form-control-sm" name="service_from"></div>
                    <div class="col-md-3"><label class="btc-label">Activity date (to)</label><input type="date" class="form-control form-control-sm" name="service_to"></div>
                    {{-- BTC-REQ-20 (17 Nov 2026): who goes - number of pax, employees (code / name) and guests --}}
                    <div class="col-md-2"><label class="btc-label" for="btcPax">No. of pax</label><input type="number" min="1" max="500" class="form-control form-control-sm" name="pax_count" id="btcPax" value="1"></div>
                    <div class="col-md-6"><label class="btc-label">Employees (code or name)</label><select class="form-select form-select-sm" name="travellers[]" id="btcTravellers" multiple></select></div>
                    <div class="col-md-4"><label class="btc-label" for="btcGuests">Guests (names, comma-separated)</label><input class="form-control form-control-sm" name="guests" id="btcGuests" maxlength="500" placeholder="e.g. Dealer Ram Kumar, Farmer Suresh"></div>
                    <div class="col-md-3 btc-nota"><label class="btc-label">Estimated amount (₹)</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="estimated_amount"></div>
                    <div class="col-md-6 btc-nota"><label class="btc-label">Vendor</label>
                        <select class="form-select form-select-sm" name="vendor_id" id="btcVendor"><option value="">— new / not listed —</option></select>
                        <div class="form-text" id="btcVendorHint"></div></div>
                    <div class="col-md-6 btc-newvendor btc-nota"><label class="btc-label">New vendor name (<a href="https://connect.vnrin.in/register" target="_blank" rel="noopener">for registration click here</a>)</label><input class="form-control form-control-sm" name="vendor_name"></div>
                    <div class="col-md-6 btc-newvendor btc-nota"><label class="btc-label">New vendor GSTIN</label><input class="form-control form-control-sm" name="vendor_gstin" maxlength="20"></div>
                    <div class="col-md-4 btc-b"><label class="btc-label">Quotation amount (₹)</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="quotation_amount"></div>
                    <div class="col-md-4 btc-b"><label class="btc-label">Advance required (₹)</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="advance_amount"></div>
                    <div class="col-md-4 btc-air btc-pnr"><label class="btc-label">PNR</label><input class="form-control form-control-sm" name="pnr" maxlength="20"></div>
                    <div class="col-md-4 btc-air"><label class="btc-label">Sector</label><input class="form-control form-control-sm" name="travel_sector" placeholder="RPR-DEL" aria-label="RPR-DEL"></div>
                    <div class="col-md-4 btc-air"><label class="btc-label">Travel date</label><input type="date" class="form-control form-control-sm" name="travel_date"></div>
                    <div class="col-12 btc-c"><hr class="my-1"><div class="btc-label fw-semibold">Final bill</div></div>
                    <div class="col-md-3 btc-c"><label class="btc-label">Bill no.</label><input class="form-control form-control-sm" name="bill_no"></div>
                    <div class="col-md-3 btc-c"><label class="btc-label">Bill date</label><input type="date" class="form-control form-control-sm" name="bill_date" max="{{ now()->toDateString() }}"></div>
                    <div class="col-md-2 btc-c"><label class="btc-label">Taxable ₹</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="taxable_amount"></div>
                    <div class="col-md-2 btc-c"><label class="btc-label">GST ₹</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="gst_amount"></div>
                    <div class="col-md-2 btc-c"><label class="btc-label">Bill total ₹</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="bill_amount"></div>
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

<datalist id="btcVendorNames">@foreach ($vendors as $v)<option value="{{ $v['name'] }}"></option>@endforeach</datalist>

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
        $.get('{{ route('btc.data') }}', {tab: tab, search: $('#btcSearch').val(), ap: $('#btcApFilter').val()}, function (r) {
            const st = c => c.stages || {};
            const rows = (r.data || []).map(c => `<tr role="button" data-id="${c.id}">
                <td class="fw-semibold">${esc(c.case_no)}</td><td>${esc(c.route)}</td><td>${esc(c.category)}</td><td class="btc-emp-col">${esc(c.employee)}</td>
                <td>${esc(c.vendor)}</td><td>${esc(c.purpose)}</td><td class="text-end">${fmt(c.amount)}</td>
                <td><span class="badge bg-primary-subtle text-primary">${esc(c.status_label)}</span>${c.pending ? '<div class="text-danger small">' + esc(c.pending) + '</div>' : ''}</td>
                <td>${esc(c.pending_with)}</td><td>${esc(st(c).booking)}</td><td>${esc(st(c).advance)}</td><td>${esc(st(c).invoice)}</td><td>${esc(st(c).payment)}</td>
                <td>${esc(c.updated)}</td></tr>`).join('');
            $('#btcApFilter').toggle(tab === 'approvals');
            $('#btcRows').html(rows || '<tr><td colspan="14" class="text-center text-muted">No cases</td></tr>');
            $('.btc-emp-col').toggle(tab !== 'mine');   // BTC-REQ-10: own requests - no employee column
        });
    }
    $('#btcApFilter').on('change', load);   // BTC-REQ-15
    $('#btcTabs').on('click', '.nav-link', function (e) { e.preventDefault(); $('#btcTabs .nav-link').removeClass('active'); $(this).addClass('active'); tab = $(this).data('tab'); load(); });
    $('#btcSearch').on('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
    load();

    // new case form
    function fileRow(kind) {
        // BTC-REQ-09 (17 Nov 2026): an admin booking request attaches only supporting papers and approvals
        const r = $('.btc-route-radio:checked').val() || $('#btcRoute').val();
        const kinds = r === 'admin_booking' ? ['supporting', 'approval'] : ['quotation','approval','bill','supporting','boarding_pass'];
        return `<div class="d-flex gap-2 mb-1"><select class="form-select form-select-sm" name="file_kinds[]" style="width:170px">
            ${kinds.map(k => `<option value="${k}" ${k===kind?'selected':''}>${k.replace('_',' ')}</option>`).join('')}</select>
            <input type="file" class="form-control form-control-sm" name="files[]" accept=".pdf,.jpg,.jpeg,.png"></div>`;
    }
    // BTC-FORM-02: one entry per vendor, only vendors of the chosen category
    const BTC_VENDORS = @json($vendors);
    const AIR_LABEL = $('#btcCategory option[value=air]').text();
    const BTC_VENDOR_TYPES = @json($vendorTypesByCategory);
    function fillVendors() {
        const c = $('#btcCategory').val(), keep = $('#btcVendor').val();
        const want = BTC_VENDOR_TYPES[c] || [];
        const list = BTC_VENDORS.filter(v => !want.length || v.types.some(t => want.includes(t)));
        const dupName = {}; list.forEach(v => { dupName[v.name.toLowerCase()] = (dupName[v.name.toLowerCase()] || 0) + 1; });
        // BTC-VENDOR-SPEED-01 (17 Nov 2026): ~2,000 vendors were appended one <option> at a time (one DOM change each)
        // and could only be found by scrolling; now the list is built in one go and is searchable as you type
        const $s = $('#btcVendor');
        if ($s.data('select2')) $s.select2('destroy');
        $s.html('<option value="">— new / not listed —</option>' + list.map(v => '<option value="' + v.id + '">' + esc(v.name + (dupName[v.name.toLowerCase()] > 1 && v.gst ? ' · ' + v.gst : '')) + '</option>').join(''));
        $s.val(keep && list.some(v => String(v.id) === keep) ? keep : '');
        if ($.fn.select2) $s.select2({dropdownParent: $('#btcNewModal'), width: '100%', placeholder: 'Type to search the vendor', allowClear: true});
        $('#btcVendorHint').text(list.length + ' vendor(s) for this category - type to search');
    }
    $('#btcCategory').on('change', fillVendors); fillVendors();
    $(document).on('change', '.btc-route-radio', function () { $('#btcRoute').val(this.value); shape(); });
    function shape() {
        const r = $('.btc-route-radio:checked').val() || $('#btcRoute').val(), c = $('#btcCategory').val();
        $('.btc-choice').removeClass('border-primary bg-primary-subtle'); $('.btc-route-radio:checked').closest('.btc-choice').addClass('border-primary bg-primary-subtle');
        $('.btc-b').toggle(r === 'employee_advance'); $('.btc-c').toggle(r === 'direct_bill');
        $('.btc-air').toggle(c === 'air'); $('.btc-newvendor').toggle(!$('#btcVendor').val());
        // BTC-REQ-09/11 (17 Nov 2026): admin booking - no estimate / vendor fields (the admin books); advance request -
        // "Air fare", no PNR (the ticket is not booked yet)
        $('.btc-nota').toggle(r !== 'admin_booking');
        if (r === 'admin_booking') { $('#btcVendor').val('').trigger('change.select2'); $('[name=estimated_amount],[name=vendor_name],[name=vendor_gstin]').val(''); }
        $('#btcCategory option[value=air]').text(r === 'employee_advance' ? 'Air fare' : AIR_LABEL);
        $('.btc-pnr').toggle(r !== 'employee_advance'); if (r === 'employee_advance') $('[name=pnr]').val('');
        $('#btcFiles').html(fileRow(r === 'employee_advance' ? 'quotation' : (r === 'direct_bill' ? 'bill' : 'supporting')));
    }
    $('#btcCategory, #btcVendor').on('change', shape); shape();
    $('#btcEmployee').on('change', function () { $('#btcFor').text($(this).find(':selected').text() || '{{ auth()->user()->name }}'); });
    $('#btcAddFile').on('click', () => $('#btcFiles').append(fileRow('supporting')));
    // BTC-REQ-20: employees by code or name
    $('#btcTravellers').select2({dropdownParent: $('#btcNewModal'), width: '100%', placeholder: 'Type employee code or name', ajax: {url: '{{ route('btc.approvers') }}', delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}, minimumInputLength: 2});
    $('#btcTravellers, #btcGuests').on('change', function () {
        const n = ($('#btcTravellers').val() || []).length + $('#btcGuests').val().split(',').filter(x => x.trim()).length;
        if (n > Number($('#btcPax').val() || 0)) $('#btcPax').val(n);
    });
    if ($('#btcEmployee').length) {
        $('#btcEmployee').select2({dropdownParent: $('#btcNewModal'), placeholder: 'Myself', allowClear: true, ajax: {url: '{{ route('btc.approvers') }}', data: p => ({q: p.term}), processResults: d => ({results: d})}});
    }
    // BTC-AMT-01: amounts up to 99,99,999.99 with two decimals; advance not above the quotation
    const BTC_AMT_MAX = 9999999.99;
    function amtError(el) {
        const v = String(el.value || '').trim();
        if (v === '') return '';
        if (!/^\d+(\.\d{1,2})?$/.test(v)) return 'Enter an amount with up to two decimals.';
        if (parseFloat(v) > BTC_AMT_MAX) return 'Amount cannot be more than ₹99,99,999.99.';
        return '';
    }
    function checkAmounts($form) {
        let ok = true;
        $form.find('input[type=number]').filter((i, el) => /amount|paid|adjusted|tds_on/.test(el.name)).each(function () {
            let msg = amtError(this);
            if (!msg && this.name === 'advance_amount') {
                const q = parseFloat($form.find('[name=quotation_amount]').val() || '0'), adv = parseFloat(this.value || '0');
                if (q > 0 && adv > q) msg = 'Advance needed cannot be more than the quotation amount.';
            }
            $(this).toggleClass('is-invalid', !!msg).next('.invalid-feedback').remove();
            if (msg) { $(this).after('<div class="invalid-feedback">' + msg + '</div>'); ok = false; }
        });
        return ok;
    }
    $(document).on('input', '#btcNewForm input[type=number], #btcActForm input[type=number]', function () {
        $(this).attr({min: 0, max: BTC_AMT_MAX});
        const $f = $(this).closest('form'); const ok = checkAmounts($f);
        $f.find('[data-submit], #btcDo').prop('disabled', !ok);
    });
    $('#btcNewForm [data-submit]').on('click', function () {
        if (!checkAmounts($('#btcNewForm'))) return;
        const fd = new FormData($('#btcNewForm')[0]); fd.append('submit', $(this).data('submit'));
        $.ajax({url: '{{ route('btc.store') }}', method: 'POST', data: fd, processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
            .done(r => { Swal.fire('Saved', r.message, r.message.startsWith('Saved as draft') ? 'warning' : 'success'); bootstrap.Modal.getInstance(document.getElementById('btcNewModal')).hide(); $('#btcNewForm')[0].reset(); shape(); load(); })
            .fail(x => Swal.fire('Not saved', (x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {}).flat().join('\n'))) || 'Error', 'error'));
    });

    // case detail
    const SHOW_FLOW = @json($showFlow);   // BTC-REQ-08
    const FLOW = ['bill_submitted', 'dms_processed', 'finance_verified', 'checked', 'with_treasury', 'dept_approved', 'paid', 'closed'];
    const kv = (l, v) => v ? `<div class="col-md-3"><p class="btc-label">${l}</p><div class="btc-val">${v}</div></div>` : '';
    // BTC-FIN-02: Dr/Cr lines as a table
    const journalTable = (lines) => `<table class="table table-sm table-bordered small mb-0"><tr class="table-light"><th>Ledger</th><th>Sub-ledger</th><th class="text-end">Debit ₹</th><th class="text-end">Credit ₹</th><th>TDS</th></tr>${lines.map(l => `<tr><td>${esc(l.ledger)}</td><td>${esc(l.sub)}</td><td class="text-end">${l.dr ? fmt(l.dr) : ''}</td><td class="text-end">${l.cr ? fmt(l.cr) : ''}</td><td>${esc(l.tds)}</td></tr>`).join('')}<tr class="fw-semibold"><td colspan="2">Total</td><td class="text-end">${fmt(lines.reduce((s, l) => s + Number(l.dr || 0), 0))}</td><td class="text-end">${fmt(lines.reduce((s, l) => s + Number(l.cr || 0), 0))}</td><td></td></tr></table>`;
    // BTC-REQ-19 (17 Nov 2026): vendor chosen from the vendor list (searchable), or a new name typed
    const vendorPick = (c) => {
        const want = BTC_VENDOR_TYPES[c.category] || [];
        const list = BTC_VENDORS.filter(v => !want.length || v.types.some(t => want.includes(t)));
        return `<div class="col-md-5"><label class="btc-label">Vendor / hotel (from the vendor list)</label><select class="form-select form-select-sm btc-vpick" name="vendor_id"><option value="">— not listed: type the name —</option>${list.map(v => `<option value="${v.id}" ${String(v.id) === String(c.vendor_id) ? 'selected' : ''}>${esc(v.name + (v.gst ? ' · ' + v.gst : ''))}</option>`).join('')}</select></div>
            <div class="col-md-4"><label class="btc-label">or new vendor name (<a href="https://connect.vnrin.in/register" target="_blank" rel="noopener">register</a>)</label><input class="form-control form-control-sm" name="vendor_name" value="${c.vendor_id ? '' : esc(c.vendor_name || '')}"></div>`;
    };
    function actionForm(a, d) {
        const c = d.case, f = [];
        const dl = (k, l, v, opts) => `<div class="col-md-3"><label class="btc-label">${l}</label><input class="form-control form-control-sm" name="dim_${k}" value="${esc(v || '')}" list="btcDl_${k}"><datalist id="btcDl_${k}">${(opts || []).map(o => `<option value="${esc(o)}">`).join('')}</datalist></div>`;
        const inp = (n, l, t = 'text', v = '') => `<div class="col-md-3"><label class="btc-label">${l}</label><input class="form-control form-control-sm" type="${t}" name="${n}" value="${esc(v)}"></div>`;
        const rmk = (req) => `<div class="col-md-6"><label class="btc-label">Remark${req ? ' (required)' : ''}</label><input class="form-control form-control-sm" name="remark"></div>`;
        const file = (k, l) => `<div class="col-md-4"><label class="btc-label">${l}</label><input type="hidden" name="file_kinds[]" value="${k}"><input type="file" class="form-control form-control-sm" name="files[]" accept=".pdf,.jpg,.jpeg,.png"></div>`;
        switch (a) {
            case 'submit':
                // BTC-REQ-13 (17 Nov 2026): attach the quotation from the case itself
                if (c.route === 'employee_advance') f.push(inp('quotation_amount', 'Quotation amount ₹', 'number', c.quotation_amount), inp('advance_amount', 'Advance required ₹', 'number', c.advance_amount), file('quotation', 'Quotation' + ((d.files || []).some(x => x.kind === 'quotation') ? ' (one attached; add another)' : '')));
                else f.push(file('supporting', 'Attach a file (optional)'));
                f.push(rmk(false)); break;
            case 'manager_approve': if (c.route === 'employee_advance' && Number(c.advance_amount) > 0) f.push(inp('advance_approved', 'Advance approved ₹ (requested ₹' + fmt(c.advance_amount) + ')', 'number', c.advance_amount)); f.push(rmk(false)); break;
            case 'dept_approve': f.push(rmk(false)); break;
            case 'change_vendor': f.push(vendorPick(c), rmk(true)); break;   // BTC-REQ-19
            case 'confirm_booking': case 'modify_booking': f.push(vendorPick(c),
                inp('booking_ref', 'Booking reference', 'text', c.booking_ref), inp('booking_amount', 'Booking amount ₹', 'number', c.booking_amount || c.estimated_amount || c.quotation_amount), inp('booking_date', 'Booking date', 'date', (c.booking_date || '').substring(0, 10)),
                inp('service_from', 'Activity from', 'date', (c.service_from || '').substring(0, 10)), inp('service_to', 'Activity to', 'date', (c.service_to || '').substring(0, 10)),
                ...(c.category === 'air' ? [inp('pnr', 'PNR', 'text', c.pnr), inp('travel_sector', 'Sector', 'text', c.travel_sector), inp('travel_date', 'Travel date', 'date', (c.travel_date || '').substring(0, 10))] : []),
                file('supporting', 'Booking confirmation'), rmk(a === 'modify_booking')); break;
            case 'cancel_booking': f.push(rmk(true)); break;
            case 'forward_to_dms': f.push(inp('bill_forwarded_dms_on', 'Bill forwarded to DMS on', 'date', new Date().toISOString().substring(0, 10)), inp('bill_no', 'Bill no. (if known)', 'text', c.bill_no), inp('bill_amount', 'Bill total ₹ (if known)', 'number', c.bill_amount), rmk(false)); break;   // BTC-REQ-21
            case 'complete_activity': f.push(inp('activity_completed_on', 'Activity completed on', 'date'), rmk(false)); break;
            case 'record_refund': f.push(inp('advance_refund', 'Refund received ₹ (balance ₹' + fmt(d.advance.balance) + ')', 'number'), inp('advance_refund_on', 'Received on', 'date'), rmk(false)); break;
            case 'manager_reject': case 'cancel': f.push(rmk(true)); break;
            case 'advance_paid': f.push(inp('advance_paid_amount', 'Actual advance paid ₹ (approved ₹' + fmt(c.advance_approved ?? c.advance_amount) + ')', 'number', c.advance_approved ?? c.advance_amount), inp('advance_payment_ref', 'Payment reference / UTR'), inp('advance_paid_on', 'Advance paid on', 'date'), inp('advance_activity_id', 'Xeasy Advance ID (if raised there)', 'number'), inp('tds_on_advance', 'TDS deducted on advance ₹', 'number', 0)); break;
            case 'booked': f.push(rmk(false)); break;
            case 'submit_bill': f.push(inp('bill_no', 'Bill no.', 'text', c.bill_no), inp('bill_date', 'Bill date', 'date', (c.bill_date || '').substring(0, 10)), inp('taxable_amount', 'Taxable ₹', 'number', c.taxable_amount), inp('gst_amount', 'GST ₹', 'number', c.gst_amount), inp('bill_amount', 'Bill total ₹', 'number', c.bill_amount), file('bill', 'Final bill'), file('supporting', 'Supporting')); if (c.category === 'air') f.push(inp('pnr', 'PNR', 'text', c.pnr), inp('travel_sector', 'Sector', 'text', c.travel_sector), inp('travel_date', 'Travel date', 'date', (c.travel_date || '').substring(0, 10)), file('boarding_pass', 'Boarding pass (if available)')); break;
            case 'dms_process': if (!c.bill_no || !Number(c.bill_amount)) f.push(inp('bill_no', 'Bill no.', 'text', c.bill_no), inp('bill_date', 'Bill date', 'date', (c.bill_date || '').substring(0, 10)), inp('taxable_amount', 'Taxable ₹', 'number', c.taxable_amount), inp('gst_amount', 'GST ₹', 'number', c.gst_amount), inp('bill_amount', 'Bill total ₹', 'number', c.bill_amount));
                f.push(inp('dms_doc_no', 'DMS / Nova doc no.', 'text', c.dms_doc_no), `<div class="col-md-3 pt-3"><label><input type="checkbox" name="original_received" value="1"> Original bill received</label></div>`); break;
            case 'finance_verify': {
                const dup = (d.duplicates || []).length ? `<div class="col-12 text-danger small">Possible duplicate: ${d.duplicates.map(x => esc(x.case_no + ' (' + x.bill_no + ', ₹' + fmt(x.bill_amount) + ')')).join(', ')} <label class="ms-2"><input type="checkbox" name="duplicate_override" value="1"> not a duplicate</label></div>` : '';
                f.push(inp('expense_ledger', 'Expense ledger', 'text', c.expense_ledger || d.default_ledger), inp('cost_centre', 'Cost centre', 'text', c.cost_centre || c.department_name), inp('taxable_amount', 'Taxable ₹', 'number', c.taxable_amount || c.bill_amount),
                    `<div class="col-md-5"><label class="btc-label">Nature of payment (decides TDS)</label><select class="form-select form-select-sm" name="tds_nature" onchange="(function(s){const n=NATURES[s.value]; const r=s.form.querySelector('[name=tds_rate]'); if(n&&r){ const pan=(${JSON.stringify(c.vendor_gstin || '')}).toUpperCase(); const ind=pan.length===15&&['P','H'].includes(pan[5]); r.value=n.rate[ind?0:1]; }})(this)">${Object.entries(NATURES).map(([k, n]) => `<option value="${k}" ${k === (c.tds_nature || NATURE_BY_CAT[c.category] || 'none') ? 'selected' : ''}>${n.label}</option>`).join('')}</select></div>`,
                    inp('tds_rate', 'TDS rate %', 'number', c.tds_rate || 0), inp('advance_adjusted', 'Advance to set off ₹ (paid ₹' + fmt(c.advance_amount) + ', TDS on it ₹' + fmt(c.tds_on_advance) + ')', 'number', c.advance_amount || 0),
                    `<div class="col-md-3 pt-3"><label><input type="checkbox" name="duplicate_checked" value="1"> Duplicate checked in Focus</label></div>`, dup,
                    // BTC-DUP-02 / TDS-02 / GST-01 (17 Nov 2026)
                    (d.claim_overlaps || []).length ? `<div class="col-12 text-danger small">Also in the employee's own expense claim: ${d.claim_overlaps.map(o => esc(o.type + ' ' + o.date + ' ₹' + fmt(o.amount) + ' (' + o.status + ', ExpId ' + o.exp_id + ')')).join('; ')} <label class="ms-2"><input type="checkbox" name="claim_overlap_override" value="1"> not the same expense</label></div>` : '',
                    d.vendor_year ? `<div class="col-12 small text-muted">This vendor in FY ${esc(d.vendor_year.fy)} (other bills): ₹${fmt(d.vendor_year.total)} in ${d.vendor_year.count} bill(s). <label class="ms-2"><input type="checkbox" name="tds_threshold_override" value="1"> TDS not applicable (give remark)</label></div>` : '',
                    // BTC-FIN-02 (17 Nov 2026): accounting dimensions, narration, JV numbers and a Dr/Cr preview
                    ...(d.dims ? [dl('function', 'Function', c.dim_function || d.dims.defaults.function, d.dims.options.function), dl('vertical', 'Vertical', c.dim_vertical || d.dims.defaults.vertical, d.dims.options.vertical),
                        dl('region', 'Region', c.dim_region || d.dims.defaults.region, d.dims.options.region), dl('crop', 'Crop', c.dim_crop, d.dims.options.crop),
                        inp('dim_activity', 'Activity', 'text', c.dim_activity), inp('jv_no', 'JV no.', 'text', c.jv_no), inp('tds_jv_no', 'TDS JV no.', 'text', c.tds_jv_no),
                        `<div class="col-md-9"><label class="btc-label">Narration</label><input class="form-control form-control-sm" name="narration" maxlength="500" value="${esc(c.narration || '')}" placeholder="${esc(c.case_no + ' ' + (c.vendor_name || '') + ' bill ' + (c.bill_no || '') + ' for ' + c.emp_code + ' ' + c.emp_name)}"></div>`,
                        `<div class="col-12"><button type="button" class="btn btn-sm btn-outline-secondary" id="btcPreview"><i class="ri-eye-line"></i> Preview accounting (Dr / Cr)</button><div id="btcPreviewOut" class="mt-2"></div></div>`] : []),
                    d.gstin_ok === false ? `<div class="col-12 text-danger small">Vendor GSTIN ${esc(c.vendor_gstin)} is not a valid GSTIN (format / check digit) - check before claiming GST input.</div>` : '',
                    rmk(false));
                break;
            }
            case 'check': f.push(inp('focus_voucher_no', 'Focus voucher no.', 'text', c.focus_voucher_no), rmk(false)); break;
            case 'send_dept_approval': f.push(`<div class="col-md-6"><label class="btc-label">Department head / authorised approver</label><select class="form-select form-select-sm" name="dept_approver_id" id="btcApprover"></select></div>`, rmk(false)); break;
            case 'pay': {
                // BTC-PAY-01 (17 Nov 2026): mode, UTR, bank reference; a part payment leaves the rest due
                const due = Math.round((Number(c.net_payable || 0) - Number(c.paid_amount || 0)) * 100) / 100;
                f.push(inp('paid_amount', 'Amount paid now ₹ (due ₹' + fmt(due) + (Number(c.paid_amount) > 0 ? ', already paid ₹' + fmt(c.paid_amount) : '') + ')', 'number', due),
                    inp('paid_on', 'Paid on', 'date', new Date().toISOString().substring(0, 10)),
                    `<div class="col-md-3"><label class="btc-label">Mode</label><select class="form-select form-select-sm" name="payment_mode"><option value="">Choose</option>${Object.entries(d.payment_modes || {}).map(([k, l]) => `<option value="${k}">${esc(l)}</option>`).join('')}</select></div>`,
                    inp('utr_number', 'UTR / cheque no.'), inp('payment_bank_ref', 'Bank reference (optional)'), file('payment_proof', 'Payment advice'), rmk(false));
                break;
            }
            case 'payment_failed': f.push(`<div class="col-md-6"><label class="btc-label">Payment that failed / came back</label><select class="form-select form-select-sm" name="payment_id">${(d.payments || []).filter(p => p.status === 'success').map(p => `<option value="${p.id}">₹${fmt(p.amount)} · ${esc(p.on || '')} · ${esc(p.mode || '')} · UTR ${esc(p.utr || '')}</option>`).join('')}</select></div>`, rmk(true)); break;
            case 'waive_document': f.push(`<div class="col-md-6"><label class="btc-label">Waive (cannot be obtained)</label><div>${d.pending.map(p => { const k = p.startsWith('original') ? 'original' : 'boarding_pass'; return `<label class="me-3"><input type="checkbox" name="waive[]" value="${k}"> ${esc(p)}</label>`; }).join('')}</div></div>`, rmk(true)); break;
            case 'hold': case 'release_hold': case 'dept_reject': case 'reject_payment_entry': f.push(rmk(true)); break;
            case 'authorise_payment': { const p = d.pending_payment || {}; f.push(`<div class="col-12 small">Authorise ₹${fmt(p.amount)} · ${esc((p.mode || '').toUpperCase())} · UTR ${esc(p.utr_number || '')} · paid on ${esc((p.paid_on || '').substring(0, 10))}${p.bank_ref ? ' · bank ref ' + esc(p.bank_ref) : ''}</div>`, rmk(false)); break; }
            case 'assign_maker': f.push(`<div class="col-md-5"><label class="btc-label">Finance maker</label><select class="form-select form-select-sm" name="assigned_maker_id"><option value="">Choose</option>${(d.finance_users || []).map(u => `<option value="${u.id}" ${String(u.id) === String(c.assigned_maker_id) ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}</select></div>`, rmk(false)); break;
            case 'dept_return': f.push(`<div class="col-md-3"><label class="btc-label">Return to</label><select class="form-select form-select-sm" name="return_to"><option value="finance_verified">Finance (re-verify)</option><option value="dms_processed">DMS</option><option value="bill_submitted">Requester / Admin (bill)</option></select></div>`, rmk(true)); break;
            case 'close': f.push(rmk(false)); break;
            case 'return': f.push(`<div class="col-md-3"><label class="btc-label">Return to</label><select class="form-select form-select-sm" name="return_to"><option value="bill_submitted">Employee / Admin (bill)</option><option value="dms_processed">DMS</option><option value="finance_verified">Finance</option></select></div>`, rmk(true)); break;
            case 'receive_document': f.push(`<div class="col-md-3"><label class="btc-label">Document</label><select class="form-select form-select-sm" name="document"><option value="original">Original bill</option><option value="boarding_pass">Boarding pass</option></select></div>`, inp('received_on', 'Received on', 'date'), file('original', 'Scan (optional)')); break;
        }
        return f.join('');
    }
    const LABEL = {change_vendor: 'Change vendor', forward_to_dms: 'Bill forwarded to DMS', confirm_booking: 'Confirm booking', modify_booking: 'Change booking', cancel_booking: 'Cancel booking', complete_activity: 'Activity completed', record_refund: 'Record refund', submit: 'Submit', manager_approve: 'Approve', manager_reject: 'Reject', advance_paid: 'Advance paid', booked: 'Mark booked', submit_bill: 'Submit bill', dms_process: 'DMS processed', finance_verify: 'Verify & prepare JV', check: 'Checked', send_dept_approval: 'Send for department approval', dept_approve: 'Approve payment', pay: 'Record payment', close: 'Close', payment_failed: 'Payment failed / returned', authorise_payment: 'Authorise payment', reject_payment_entry: 'Reject payment entry', assign_maker: 'Assign Finance maker', waive_document: 'Waive document', hold: 'Put on financial hold', release_hold: 'Release hold', dept_return: 'Return', dept_reject: 'Reject', return: 'Return for correction', receive_document: 'Document received', cancel: 'Cancel case'};

    function openCase(id) {
        $.get('{{ url('btc/case') }}/' + id, function (d) {
            const c = d.case, at = FLOW.indexOf(c.status);
            $('#btcCaseTitle').text(c.case_no + ' · ' + d.status_label);
            const flow = FLOW.map((s, i) => `<span class="btc-step ${i < at ? 'done' : (i === at ? 'now' : '')}">${STATUS[s]}</span>`).join(' › ');
            const files = d.files.map(f => `<a class="me-3" target="_blank" href="${f.url}"><i class="ri-file-line"></i> ${esc(f.kind.replace('_', ' '))}: ${esc(f.name)}</a>`).join('') || '<span class="text-muted">none</span>';
            const logs = d.logs.map(l => `<div class="btc-log"><b>${esc(l.at)}</b> ${esc(l.by)} · ${esc(l.action.replace(/_/g, ' '))} → ${esc(l.to)}${l.remark ? '<br>' + esc(l.remark) : ''}</div>`).join('');
            const acts = d.actions.map(a => `<button class="btn btn-sm ${['return', 'manager_reject', 'cancel', 'dept_return', 'dept_reject', 'payment_failed', 'hold', 'reject_payment_entry'].includes(a) ? 'btn-outline-danger' : 'btn-outline-primary'} me-1 mb-1" data-act="${a}">${LABEL[a] || a}</button>`).join('');
            $('#btcCaseBody').html(`${SHOW_FLOW ? '<div class="mb-2">' + flow + '</div>' : ''}
                <div class="row g-2 mb-2">${kv('Route', esc(d.route_label))}${kv('Category', esc(d.category_label))}${kv('Employee', esc(c.emp_code + ' - ' + c.emp_name))}${kv('Department', esc(c.department_name))}
                ${kv('Purpose', esc(c.purpose))}${kv('Vendor', esc(c.vendor_name) + (c.new_vendor ? ' (new)' : ''))}${kv('Service', esc((c.service_from || '').substring(0, 10) + ' – ' + (c.service_to || '').substring(0, 10)))}${kv('Manager', esc(d.manager))}
                ${kv('Quotation ₹', fmt(c.quotation_amount))}${kv('Advance ₹', fmt(c.advance_amount))}${kv('Bill', esc((c.bill_no || '') + ' ' + (c.bill_date || '').substring(0, 10)))}${kv('Bill ₹', fmt(c.bill_amount))}
                ${kv('TDS ₹', fmt(c.tds_amount))}${kv('Net payable ₹', fmt(c.net_payable))}${kv('PNR / sector', esc((c.pnr || '') + ' ' + (c.travel_sector || '')))}${kv('Dept approver', esc(d.dept_approver))}
                ${kv('Paid', c.paid_amount ? '₹' + fmt(c.paid_amount) + ' on ' + esc((c.paid_on || '').substring(0, 10)) + ' · ' + esc(c.utr_number) : '')}${kv('Waiting for', esc(d.pending.join(', ')))}
                ${c.pax_count ? kv('Pax', esc(String(c.pax_count))) : ''}${c.travellers ? kv('Employees', esc((() => { try { return JSON.parse(c.travellers).map(t => t.code + ' ' + t.name).join(', '); } catch (e) { return ''; } })())) : ''}${c.guests ? kv('Guests', esc(c.guests)) : ''}${c.bill_forwarded_dms_on ? kv('Bill to DMS', esc(c.bill_forwarded_dms_on.substring(0, 10))) : ''}${kv('Pending with', esc(d.pending_with))}${kv('Estimated ₹', fmt(c.estimated_amount))}${kv('Booking', c.booking_ref ? esc(c.booking_ref + ' · ₹' + fmt(c.booking_amount) + ' · ' + (c.booking_date || '').substring(0, 10) + ' · ' + (c.booking_status || '')) : '')}${kv('Activity completed', esc((c.activity_completed_on || '').substring(0, 10)))}</div>
                ${Number(d.advance.requested) > 0 ? `<div class="mb-2"><p class="btc-label">Advance</p><table class="table table-sm table-bordered small mb-0"><tr class="table-light"><th>Requested</th><th>Approved</th><th>Actually paid</th><th>TDS on advance</th><th>Outstanding</th><th>Adjusted against invoice</th><th>Refund received</th><th>Balance outstanding</th></tr>
                    <tr><td>${fmt(d.advance.requested)}</td><td>${d.advance.approved === null ? '-' : fmt(d.advance.approved)}</td><td>${fmt(d.advance.paid)}${c.advance_payment_ref ? '<div class="text-muted">' + esc(c.advance_payment_ref) + '</div>' : ''}</td><td>${fmt(d.advance.tds)}</td><td>${fmt(d.advance.outstanding)}</td><td>${fmt(d.advance.adjusted)}</td><td>${fmt(d.advance.refund)}</td><td class="fw-semibold">${fmt(d.advance.balance)}</td></tr></table></div>` : ''}
                ${(d.invoices || []).length ? '<div class="mb-2"><p class="btc-label">Nova invoices</p>' + d.invoices.map(i => `<span class="me-3">${esc(i.nova_document_id || '')} · ${esc(i.invoice_no || '')} · ₹${fmt(i.gross_amount)} ${i.document_url ? '<a target="_blank" rel="noopener" href="' + esc(i.document_url) + '">document</a>' : ''}</span>`).join('') + '</div>' : ''}
                ${(d.exceptions || []).length ? `<div class="alert alert-warning py-1 px-2 small mb-2"><b>Exceptions:</b><ul class="mb-0">${d.exceptions.map(e => '<li><b>' + esc(e.label) + ':</b> ' + esc(e.detail) + '</li>').join('')}</ul><a href="{{ route('btc.exceptions') }}">Exception Queue</a></div>` : ''}
                ${d.pending_payment ? `<div class="alert alert-info py-1 px-2 small mb-2"><b>Payment entered, waiting for the Treasury checker:</b> ₹${fmt(d.pending_payment.amount)} · ${esc((d.pending_payment.mode || '').toUpperCase())} · UTR ${esc(d.pending_payment.utr_number || '')}</div>` : ''}
                ${d.maker ? `<div class="small text-muted mb-1">Finance maker: ${esc(d.maker)}</div>` : ''}
                ${d.vendor_bank ? `<div class="small mb-1 ${d.vendor_bank === 'not verified' ? 'text-danger' : 'text-muted'}">Vendor bank: ${esc(d.vendor_bank)}${d.vendor_bank === 'not verified' ? ' - <a href="{{ route('btc.vendor-banks') }}">add / verify</a>' : ''}</div>` : ''}
                ${(d.journal || []).length ? `<div class="mb-2"><p class="btc-label">Accounting (Focus JV${c.jv_no ? ' ' + esc(c.jv_no) : ''})</p>${journalTable(d.journal)}${[c.dim_function, c.dim_vertical, c.dim_region, c.dim_activity, c.dim_crop].filter(Boolean).length ? '<div class="small text-muted">' + esc([c.dim_function, c.dim_vertical, c.dim_region, c.dim_activity, c.dim_crop].filter(Boolean).join(' · ')) + '</div>' : ''}</div>` : ''}
                ${c.on_hold ? `<div class="alert alert-danger py-1 px-2 small mb-2"><b>Financial hold:</b> ${esc(c.hold_reason || '')} - payment, approval and closure are blocked until released.</div>` : ''}
                ${(d.claim_overlaps || []).length ? `<div class="alert alert-warning py-1 px-2 small mb-2"><b>Also claimed by the employee:</b> ${d.claim_overlaps.map(o => esc(o.type + ' ' + o.date + ' ₹' + fmt(o.amount) + ' (' + o.status + ')')).join('; ')}</div>` : ''}
                ${(d.overdue_advances || []).length ? `<div class="alert alert-warning py-1 px-2 small mb-2"><b>Unsettled earlier advance(s):</b> ${d.overdue_advances.map(esc).join(', ')}</div>` : ''}
                ${(d.payments || []).length ? `<div class="mb-2"><p class="btc-label">Payments</p><table class="table table-sm table-bordered small mb-0"><tr class="table-light"><th>Date</th><th>Mode</th><th>UTR</th><th>Bank ref</th><th class="text-end">Amount ₹</th><th>Status</th></tr>${d.payments.map(p => `<tr class="${p.status === 'failed' ? 'text-decoration-line-through text-muted' : ''}"><td>${esc(p.on || '')}</td><td>${esc(p.mode || '')}</td><td>${esc(p.utr || '')}</td><td>${esc(p.bank_ref || '')}</td><td class="text-end">${fmt(p.amount)}</td><td>${p.status === 'failed' ? 'Failed: ' + esc(p.failed_reason || '') : 'Paid'}</td></tr>`).join('')}</table></div>` : ''}
                ${(d.closure_gaps || []).length && c.status === 'paid' ? `<div class="alert alert-secondary py-1 px-2 small mb-2"><b>Before closing:</b><ul class="mb-0">${d.closure_gaps.map(g => '<li>' + esc(g) + '</li>').join('')}</ul></div>` : ''}
                ${c.doc_waived_kinds ? `<div class="small text-muted mb-2">Documents waived (${esc(c.doc_waived_kinds.replace('boarding_pass', 'boarding pass').replace('original', 'original bill'))}): ${esc(c.doc_waiver_reason || '')}</div>` : ''}
                <div class="mb-2"><p class="btc-label">Files</p>${files}</div>
                <div class="mb-2">${acts || '<span class="text-muted small">No step for you at this stage.</span>'}</div>
                <form id="btcActForm" class="row g-2 mb-3" style="display:none" enctype="multipart/form-data"></form>
                <p class="btc-label">History</p>${logs}`);
            $('#btcCaseBody [data-act]').on('click', function () {
                const a = $(this).data('act');
                $('#btcActForm').html(actionForm(a, d) + `<div class="col-12"><input type="hidden" name="action" value="${a}"><button type="button" class="btn btn-sm btn-primary" id="btcDo">${LABEL[a]}</button></div>`).show();
                $('#btcPreview').on('click', function () {   // BTC-FIN-02: nothing is saved
                    $.ajax({url: '{{ url('btc/case') }}/' + c.id + '/preview', method: 'POST', data: new FormData($('#btcActForm')[0]), processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
                        .done(r => $('#btcPreviewOut').html(journalTable(r.lines) + `<div class="small mt-1">TDS ₹${fmt(r.tds)} (${esc(r.section)}) · Net payable ₹${fmt(r.net)}</div>`))
                        .fail(x => $('#btcPreviewOut').html('<div class="text-danger small">' + esc((x.responseJSON && x.responseJSON.message) || 'Preview failed') + '</div>'));
                });
                $('#btcActForm .btc-vpick').each(function () { if ($.fn.select2) $(this).select2({dropdownParent: $('#btcCaseModal'), width: '100%', allowClear: true, placeholder: 'Type to search the vendor'}); });
                if (a === 'send_dept_approval') $('#btcApprover').select2({dropdownParent: $('#btcCaseModal'), ajax: {url: '{{ route('btc.approvers') }}', data: p => ({q: p.term}), processResults: r => ({results: r})}});
                $('#btcDo').on('click', function () {
                    if (!checkAmounts($('#btcActForm'))) return;   // BTC-AMT-01
                    $.ajax({url: '{{ url('btc/case') }}/' + c.id + '/act', method: 'POST', data: new FormData($('#btcActForm')[0]), processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
                        .done(r => { Swal.fire('Done', r.message, 'success'); openCase(c.id); load(); })
                        .fail(x => Swal.fire('Not done', (x.responseJSON && x.responseJSON.message) || 'Error', 'warning'));
                });
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('btcCaseModal')).show();
        });
    }
    $('#btcRows').on('click', 'tr[data-id]', function () { openCase($(this).data('id')); });
    // BTC-REQ-03: the register / queues / inbox open a case here (?case=ID); "New BTC Requirement" opens the form (#new)
    const qs = new URLSearchParams(location.search);
    if (qs.get('case')) openCase(qs.get('case'));
    if (qs.get('tab')) $('#btcTabs .nav-link[data-tab="' + qs.get('tab').replace(/[^a-z_]/g, '') + '"]').trigger('click');   // BTC-CARD-01: dashboard links
    if (location.hash === '#new') bootstrap.Modal.getOrCreateInstance(document.getElementById('btcNewModal')).show();
});
</script>
@endpush
