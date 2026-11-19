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
                @if ($showFlow)<div class="btc-flow">Requirement → Admin booking (physical bill → DMS → Finance) / quotation + manager approval + advance → bill uploaded in Xeasy → automatic data extraction → Finance Verifier 1 + Focus entry → Finance Approval → Treasury payment → Closure</div>@endif
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @php $u = auth()->user(); @endphp
                @if ($u->can('btc.dashboard') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.dashboard') }}"><i class="ri-dashboard-3-line"></i> Dashboard</a>@endif
                @if (\App\Http\Controllers\admin\BtcPagesController::hasTeam() || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-primary" href="{{ route('btc.team') }}"><i class="ri-team-line"></i> My team</a>@endif
                @if ($u->can('btc.register') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.register') }}"><i class="ri-list-check-2"></i> Register</a>@endif
                @if ($u->can('btc.booking') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.booking') }}"><i class="ri-hotel-line"></i> Booking queue</a>@endif
                @if ($u->can('btc.advance') || $u->can('btc.finance') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.advances') }}"><i class="ri-hand-coin-line"></i> Advances</a>@endif
                @if ($u->can('btc.bill_upload') || $u->can('btc.finance') || $u->can('btc.dms') || $u->can('btc.admin'))<a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.scanner') }}"><i class="ri-scan-line"></i> Scanner</a>@endif
                @can('btc.finance')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('btc.export.boarding') }}"><i class="ri-plane-line"></i> Boarding passes pending</a>
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
                    {{-- BTC-REQ-25 (19 Nov 2026): no "Employee (Admin raising for someone)" field; purpose from a list --}}
                    <div class="col-md-6"><label class="btc-label">Location</label><input class="form-control form-control-sm" name="location"></div>
                    <div class="col-md-6"><label class="btc-label" for="btcPurpose">Purpose / business requirement</label>
                        <select class="form-select form-select-sm" name="purpose" id="btcPurpose" required><option value="">— choose —</option>@foreach ($purposes as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></div>
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
                    <div class="col-12"><label class="btc-label" for="btcRemarks">Remarks <span class="text-muted" id="btcRemarksHint"></span></label>
                        <textarea class="form-control form-control-sm" name="request_remarks" id="btcRemarks" rows="2" maxlength="1000"></textarea></div>
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

@include('admin.btc._casewin')
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
        // BTC-REQ-09/11 (17 Nov 2026): admin booking - no estimate / vendor fields (the admin books)
        $('.btc-nota').toggle(r !== 'admin_booking');
        if (r === 'admin_booking') { $('#btcVendor').val('').trigger('change.select2'); $('[name=estimated_amount],[name=vendor_name],[name=vendor_gstin]').val(''); }
        // BTC-REQ-25: category shown as "Air" for both request types
        // BTC-REQ-25: PNR only for Air with "Advance Required" (the employee has the ticket); hidden for Admin Booking
        const pnr = c === 'air' && r === 'employee_advance'; $('.btc-pnr').toggle(pnr); if (!pnr) $('[name=pnr]').val('');
        $('#btcFiles').html(fileRow(r === 'employee_advance' ? 'quotation' : (r === 'direct_bill' ? 'bill' : 'supporting')));
    }
    $('#btcCategory, #btcVendor').on('change', shape); shape();
    $('#btcPurpose').on('change', function () { const o = String(this.value).startsWith('Other'); $('#btcRemarks').prop('required', o); $('#btcRemarksHint').text(o ? '(required - write the purpose)' : ''); });
    $('#btcAddFile').on('click', () => $('#btcFiles').append(fileRow('supporting')));
    // BTC-REQ-20: employees by code or name
    $('#btcTravellers').select2({dropdownParent: $('#btcNewModal'), width: '100%', placeholder: 'Type employee code or name', ajax: {url: '{{ route('btc.approvers') }}', delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}, minimumInputLength: 2});
    $('#btcTravellers, #btcGuests').on('change', function () {
        const n = ($('#btcTravellers').val() || []).length + $('#btcGuests').val().split(',').filter(x => x.trim()).length;
        if (n > Number($('#btcPax').val() || 0)) $('#btcPax').val(n);
    });
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
    $(document).on('input', '#btcNewForm input[type=number]', function () {
        $(this).attr({min: 0, max: BTC_AMT_MAX});
        const $f = $(this).closest('form'); const ok = checkAmounts($f);
        $f.find('[data-submit]').prop('disabled', !ok);
    });
    $('#btcNewForm [data-submit]').on('click', function () {
        if (!checkAmounts($('#btcNewForm'))) return;
        if (!$('#btcPurpose').val()) { Swal.fire('Purpose needed', 'Choose the purpose of the requirement.', 'warning'); return; }
        if (String($('#btcPurpose').val()).startsWith('Other') && !$('#btcRemarks').val().trim()) { Swal.fire('Remarks needed', 'Write the purpose in the remarks.', 'warning'); return; }
        const fd = new FormData($('#btcNewForm')[0]); fd.append('submit', $(this).data('submit'));
        $.ajax({url: '{{ route('btc.store') }}', method: 'POST', data: fd, processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
            .done(r => { Swal.fire('Saved', r.message, r.message.startsWith('Saved as draft') ? 'warning' : 'success'); bootstrap.Modal.getInstance(document.getElementById('btcNewModal')).hide(); $('#btcNewForm')[0].reset(); shape(); load(); })
            .fail(x => Swal.fire('Not saved', (x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {}).flat().join('\n'))) || 'Error', 'error'));
    });

    // BTC-V2: the case window is shared with the Finance screens (admin.btc._casewin)
    const openCase = id => window.BtcCase.open(id);
    $(document).on('btc:changed', load);
    $('#btcRows').on('click', 'tr[data-id]', function () { openCase($(this).data('id')); });
    // BTC-REQ-03: the register / queues / inbox open a case here (?case=ID); "New BTC Requirement" opens the form (#new)
    const qs = new URLSearchParams(location.search);
    if (qs.get('case')) openCase(qs.get('case'));
    if (qs.get('tab')) $('#btcTabs .nav-link[data-tab="' + qs.get('tab').replace(/[^a-z_]/g, '') + '"]').trigger('click');   // BTC-CARD-01: dashboard links
    if (location.hash === '#new') bootstrap.Modal.getOrCreateInstance(document.getElementById('btcNewModal')).show();
});
</script>
@endpush
