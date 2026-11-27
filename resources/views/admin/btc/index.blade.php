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
    <div class="container-fluid xe-screen btc-ui btc-req">
        {{-- BTC-UI-01 (26 Nov 2026, testing point 22): the same page bar and BTC menu as every BTC screen; the buttons stay --}}
        @include('admin.btc.pages._nav', ['title' => 'BTC Request', 'icon' => 'ri-bill-line', 'actionsView' => 'admin.btc._index-actions',
            'sub' => $showFlow ? 'Requirement → admin booking or advance → bill in Xeasy → punch and Focus export → Finance verification → Finance approval → Treasury payment → closure' : 'Your Bill To Company requests and the ones waiting for your approval'])
        {{-- BTC-MYREQ-01 (27 Nov 2026, testing point 32): summary cards (click to filter), one filter bar, a short list with
             one-word status / department / payment; route, category, booking, advance and invoice are in View details --}}
        <div class="btc-sum" id="btcSum" role="group" aria-label="Filter by summary">
            <button type="button" class="btc-sum-card active" data-sum="all"><span class="btc-sum-n" data-n="all">0</span><span class="btc-sum-l">Total requests</span></button>
            <button type="button" class="btc-sum-card btc-sum--wait" data-sum="open"><span class="btc-sum-n" data-n="open">0</span><span class="btc-sum-l">Pending</span></button>
            <button type="button" class="btc-sum-card btc-sum--done" data-sum="Completed"><span class="btc-sum-n" data-n="Completed">0</span><span class="btc-sum-l">Completed</span></button>
            <button type="button" class="btc-sum-card btc-sum--back" data-sum="Rejected"><span class="btc-sum-n" data-n="Rejected">0</span><span class="btc-sum-l">Rejected</span></button>
        </div>
        <div class="card btc-req-card">
            @if (count($tabs) > 1)
            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <ul class="nav nav-pills btc-tabs xe-seg" id="btcTabs">
                    @foreach ($tabs as $k => $label)
                        <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#" data-tab="{{ $k }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
                <select class="form-select form-select-sm" id="btcApFilter" style="width:170px;display:none" aria-label="Approvals to show">
                    <option value="pending">Pending with me</option><option value="approved">Approved by me</option><option value="rejected">Rejected / returned by me</option><option value="all">All</option>
                </select>
            </div>
            @else
                <ul class="d-none" id="btcTabs">@foreach ($tabs as $k => $label)<li><a class="nav-link active" href="#" data-tab="{{ $k }}">{{ $label }}</a></li>@endforeach</ul>
            @endif
            <form class="btc-filters" id="btcFilters" autocomplete="off">
                <div class="btc-f btc-f--search"><label for="btcSearch">Search</label>
                    <div class="btc-search"><i class="ri-search-line" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" id="btcSearch" placeholder="Case ID, vendor, bill no., PNR"></div></div>
                <div class="btc-f"><label for="btcFStatus">Status</label><select class="form-select form-select-sm" id="btcFStatus"><option value="">All</option>
                    @foreach (['Draft', 'Pending', 'Returned', 'Verified', 'Completed', 'Rejected', 'Cancelled'] as $st)<option>{{ $st }}</option>@endforeach</select></div>
                <div class="btc-f"><label for="btcFWith">Pending with</label><select class="form-select form-select-sm" id="btcFWith"><option value="">All</option></select></div>
                <div class="btc-f"><label for="btcFCat">Category</label><select class="form-select form-select-sm" id="btcFCat"><option value="">All</option>
                    @foreach (config('btc.categories') as $ck => $cv)<option value="{{ $ck }}">{{ $cv['label'] ?? $ck }}</option>@endforeach</select></div>
                <div class="btc-f"><label for="btcFFrom">Raised from</label><input type="date" class="form-control form-control-sm" id="btcFFrom"></div>
                <div class="btc-f"><label for="btcFTo">to</label><input type="date" class="form-control form-control-sm" id="btcFTo"></div>
                <div class="btc-f btc-f--btns"><button type="submit" class="btn btn-sm btn-primary"><i class="ri-search-line"></i> Search</button>
                    <button type="button" class="btn btn-sm btn-light" id="btcReset">Reset</button></div>
            </form>
            <div class="btc-req-scroll">
                <table class="table table-sm table-hover btc-table btc-req-table mb-0">
                    <thead><tr><th style="width:9rem">Case ID</th><th class="btc-emp-col" style="width:11rem">Employee</th><th>Vendor</th><th style="width:13rem">Purpose</th><th class="text-end" style="width:8rem">Amount (₹)</th><th style="width:8.5rem">Status</th><th style="width:8rem">Pending with</th><th style="width:10rem">Payment</th><th style="width:8rem">Last updated</th><th class="text-end" style="width:5rem">Action</th></tr></thead>
                    <tbody id="btcRows"><tr><td colspan="10" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                </table>
            </div>
            <div class="btc-pager">
                <span id="btcPageInfo" aria-live="polite"></span>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0" for="btcPageSize">Rows</label>
                    <select class="form-select form-select-sm" id="btcPageSize" style="width:auto"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
                    <nav aria-label="Pages"><ul class="pagination pagination-sm mb-0" id="btcPages"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- New case --}}
<div class="modal fade" id="btcNewModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form id="btcNewForm" enctype="multipart/form-data" autocomplete="off">
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
                    <div class="col-md-6 btc-notrip"><label class="btc-label">Location</label><input class="form-control form-control-sm" name="location"></div>
                    {{-- BTC-TRIP-01 (25 Nov 2026, testing points 20 / 21): Air / Train - from / to and one-way or round trip --}}
                    <div class="col-md-3 btc-trip"><label class="btc-label" for="btcFrom">Travel from <span class="text-danger">*</span></label><input class="form-control form-control-sm" name="travel_from" id="btcFrom" maxlength="100" placeholder="e.g. Raipur"></div>
                    <div class="col-md-3 btc-trip"><label class="btc-label" for="btcTo">Travel to <span class="text-danger">*</span></label><input class="form-control form-control-sm" name="travel_to" id="btcTo" maxlength="100" placeholder="e.g. Delhi"></div>
                    <div class="col-md-6"><label class="btc-label" for="btcPurpose">Purpose / business requirement</label>
                        <select class="form-select form-select-sm" name="purpose" id="btcPurpose" required><option value="">— choose —</option>@foreach ($purposes as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></div>
                    <div class="col-md-3 btc-notrip"><label class="btc-label">Activity date (from)</label><input type="date" class="form-control form-control-sm" name="service_from"></div>
                    <div class="col-md-3 btc-notrip"><label class="btc-label">Activity date (to)</label><input type="date" class="form-control form-control-sm" name="service_to"></div>
                    <div class="col-md-4 btc-trip"><label class="btc-label">Trip type</label><div>
                        <label class="me-3"><input type="radio" class="form-check-input me-1" name="trip_type" value="one_way" checked> One-way</label>
                        <label><input type="radio" class="form-check-input me-1" name="trip_type" value="round_trip"> Round-trip</label></div></div>
                    <div class="col-md-3 btc-trip"><label class="btc-label" for="btcGo">Travel date (onward) <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="travel_date" id="btcGo"></div>
                    <div class="col-md-3 btc-trip btc-return"><label class="btc-label" for="btcRet">Return travel date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="return_date" id="btcRet"></div>
                    {{-- BTC-REQ-20 (17 Nov 2026): who goes - number of pax, employees (code / name) and guests --}}
                    {{-- BTC-BUS-01 (26 Nov 2026, testing point 27): Bus - booking for Self / Team + Guest, passengers, class by eligibility --}}
                    <div class="col-md-3 btc-bus"><label class="btc-label" for="btcBookFor">Booking for <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="booking_for" id="btcBookFor"><option value="">— choose —</option><option value="self">Self</option><option value="team">Team + Guest</option></select></div>
                    <div class="col-md-6 btc-bus btc-self"><label class="btc-label">Passenger (you)</label><div class="small fw-semibold pt-1" id="btcSelfInfo">—</div></div>
                    <div class="col-md-2 btc-bus btc-team"><label class="btc-label" for="btcBusPax">Total PAX <span class="text-danger">*</span></label><input type="number" min="1" max="50" class="form-control form-control-sm" id="btcBusPax" placeholder="e.g. 4"></div>
                    <div class="col-12 btc-bus btc-team"><div id="btcPassengers"></div></div>
                    <div class="col-md-3 btc-bus"><label class="btc-label" for="btcClass">Travel class <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="travel_class" id="btcClass"><option value="">— choose —</option></select><div class="form-text" id="btcClassHint"></div></div>
                    {{-- BTC-PAX-01 (26 Nov 2026, testing point 24): No. of pax as a list to pick from (it was a bare number box) --}}
                    <div class="col-md-2 btc-nobus"><label class="btc-label" for="btcPax">No. of pax</label><select class="form-select form-select-sm" name="pax_count" id="btcPax">@for ($i = 1; $i <= 100; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
                    <div class="col-md-6 btc-nobus"><label class="btc-label">Employees (code or name)</label><select class="form-select form-select-sm" name="travellers[]" id="btcTravellers" multiple></select></div>
                    <div class="col-md-4 btc-nobus"><label class="btc-label" for="btcGuests">Guests (names, comma-separated)</label><input class="form-control form-control-sm" name="guests" id="btcGuests" maxlength="500" placeholder="e.g. Dealer Ram Kumar, Farmer Suresh"></div>
                    <div class="col-md-3 btc-nota"><label class="btc-label">Estimated amount (₹)</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="estimated_amount"></div>
                    <div class="col-md-6 btc-nota"><label class="btc-label">Vendor</label>
                        <select class="form-select form-select-sm" name="vendor_id" id="btcVendor"><option value="">— new / not listed —</option></select>
                        <div class="form-text" id="btcVendorHint"></div></div>
                    <div class="col-md-6 btc-newvendor btc-nota"><label class="btc-label">New vendor name (<a href="https://connect.vnrin.in/register" target="_blank" rel="noopener">for registration click here</a>)</label><input class="form-control form-control-sm" name="vendor_name"></div>
                    <div class="col-md-6 btc-newvendor btc-nota"><label class="btc-label">New vendor GSTIN</label><input class="form-control form-control-sm" name="vendor_gstin" maxlength="20"></div>
                    <div class="col-md-4 btc-b"><label class="btc-label">Quotation amount (₹)</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="quotation_amount"></div>
                    <div class="col-md-4 btc-b"><label class="btc-label">Advance required (₹)</label><input type="number" step="0.01" min="0" max="9999999.99" class="form-control form-control-sm" name="advance_amount"></div>
                    <div class="col-md-4 btc-air btc-pnr"><label class="btc-label">PNR</label><input class="form-control form-control-sm" name="pnr" maxlength="20"></div>

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
    const BUS_CATS = @json((array) config('btc.passenger_categories', ['bus'])), BUS_RANK = @json((array) config('btc.bus_classes', []));
    const ME = @json((int) (auth()->user()->employee_id ?? 0));
    let busElig = {};   // EmployeeID -> {code, name, eligible}
    const BTC_TONES = @json((array) config('btc.status_tones'));   // BTC-UI-01: colour family of the status pill
$(function () {
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const STATUS = @json($statuses);
    const TDS = @json($tdsSections);
    const NATURES = @json($tdsNatures ?? []); // BTC-TDS-01
    const NATURE_BY_CAT = @json($tdsNatureByCategory ?? []);
    const fmt = v => (v === null || v === undefined || v === '') ? '' : Number(v).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = s => $('<div>').text(s ?? '').html();
    let tab = $('#btcTabs .nav-link.active').data('tab') || 'mine', timer;

    // BTC-MYREQ-01 (27 Nov 2026, testing point 32): the list is fetched once per tab / search; status, department,
    // category, dates, summary card and pages are applied here
    const SHORT_TONES = @json((array) config('btc.short_tones'));
    let all = [], page = 1, sum = 'all';
    const OPEN = ['Draft', 'Pending', 'Returned', 'Verified'];
    const own = () => tab === 'mine' || tab === 'approvals';   // the requester / manager view: one-word labels
    function load() {
        $('#btcRows').html('<tr><td colspan="10" class="text-center text-muted py-4">Loading…</td></tr>');
        $.get('{{ route('btc.data') }}', {tab: tab, search: $('#btcSearch').val(), ap: $('#btcApFilter').val()}, function (r) {
            all = r.data || [];
            $('#btcApFilter').toggle(tab === 'approvals');
            $('.btc-emp-col').toggle(tab !== 'mine');   // BTC-REQ-10: own requests - no employee column
            const withs = [...new Set(all.map(c => own() ? c.pending_dept : c.pending_with).filter(Boolean))].sort(), keep = $('#btcFWith').val();
            $('#btcFWith').html('<option value="">All</option>' + withs.map(w => `<option ${w === keep ? 'selected' : ''}>${esc(w)}</option>`).join(''));
            page = 1; draw();
        });
    }
    function filtered(skipSum) {
        const st = $('#btcFStatus').val(), w = $('#btcFWith').val(), cat = $('#btcFCat').val(), f = $('#btcFFrom').val(), t = $('#btcFTo').val();
        return all.filter(c => (!st || c.short_status === st) && (!w || (own() ? c.pending_dept : c.pending_with) === w) && (!cat || c.category_key === cat)
            && (!f || (c.raised || '') >= f) && (!t || (c.raised || '') <= t)
            && (skipSum || sum === 'all' || (sum === 'open' ? OPEN.includes(c.short_status) : c.short_status === sum)));
    }
    function draw() {
        const base = filtered(true);
        $('[data-n="all"]').text(base.length);
        $('[data-n="open"]').text(base.filter(c => OPEN.includes(c.short_status)).length);
        ['Completed', 'Rejected'].forEach(k => $(`[data-n="${k}"]`).text(base.filter(c => c.short_status === k).length));
        const list = filtered(false), size = Number($('#btcPageSize').val()) || 25, pages = Math.max(1, Math.ceil(list.length / size));
        page = Math.min(page, pages);
        const rows = list.slice((page - 1) * size, page * size).map(c => {
            const label = own() ? c.short_status : c.status_label, tone = own() ? (SHORT_TONES[c.short_status] || 'wait') : (BTC_TONES[c.status] || 'stop');
            const withTxt = own() ? c.pending_dept : c.pending_with;
            const pay = c.payment_state ? `<span class="btc-pay btc-pay--${c.payment_state.replace(/\s/g, '').toLowerCase()}">${esc(c.payment_state)}</span>` : '<span class="text-muted">—</span>';
            return `<tr role="button" data-id="${c.id}">
                <td class="fw-semibold text-nowrap">${esc(c.case_no)}</td><td class="btc-emp-col text-truncate" title="${esc(c.employee)}">${esc(c.employee)}</td>
                <td><div class="btc-vendor text-truncate" title="${esc(c.vendor)}">${esc(c.vendor) || '<span class="text-muted">—</span>'}</div>${c.vendor_place ? '<div class="btc-sub text-truncate">' + esc(c.vendor_place) + '</div>' : ''}</td>
                <td class="text-truncate" title="${esc(c.purpose)}">${esc(c.purpose)}</td>
                <td class="text-end btc-num">${fmt(c.amount) || '<span class="text-muted">—</span>'}</td>
                <td><span class="btc-pill btc-pill--${tone}">${esc(label)}</span>${!own() && c.pending ? '<div class="btc-pending">' + esc(c.pending) + '</div>' : ''}</td>
                <td>${withTxt ? esc(withTxt) : '<span class="text-muted">—</span>'}</td>
                <td>${pay}${c.advance_short ? '<div class="btc-sub">' + esc(c.advance_short) + '</div>' : ''}</td>
                <td class="text-nowrap btc-sub-d">${esc(c.updated)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary btc-view" aria-label="View details of ${esc(c.case_no)}">View</button></td></tr>`;
        }).join('');
        $('#btcRows').html(rows || `<tr><td colspan="10" class="text-center text-muted py-4">${all.length ? 'No requests match these filters.' : (tab === 'mine' ? 'You have no BTC requests yet. Use “New BTC case” to raise one.' : 'No cases')}</td></tr>`);
        $('.btc-emp-col').toggle(tab !== 'mine');
        $('#btcPageInfo').text(list.length ? `Showing ${(page - 1) * size + 1}–${Math.min(page * size, list.length)} of ${list.length}` : '');
        const li = (p, l, dis, act) => `<li class="page-item ${dis ? 'disabled' : ''} ${act ? 'active' : ''}"><a class="page-link" href="#" data-p="${p}">${l}</a></li>`;
        let pg = li(page - 1, '‹', page === 1);
        for (let p = 1; p <= pages; p++) if (p === 1 || p === pages || Math.abs(p - page) <= 1) pg += li(p, p, false, p === page); else if (Math.abs(p - page) === 2) pg += li(page, '…', true);
        $('#btcPages').html(pages > 1 ? pg + li(page + 1, '›', page === pages) : '');
        $('#btcSum .btc-sum-card').removeClass('active').filter(`[data-sum="${sum}"]`).addClass('active');
    }
    $('#btcPages').on('click', '.page-link', function (e) { e.preventDefault(); const p = Number($(this).data('p')); if (p >= 1) { page = p; draw(); } });
    $('#btcPageSize, #btcFStatus, #btcFWith, #btcFCat, #btcFFrom, #btcFTo').on('change', () => { page = 1; draw(); });
    $('#btcSum').on('click', '.btc-sum-card', function () { sum = $(this).data('sum'); page = 1; draw(); });
    $('#btcFilters').on('submit', function (e) { e.preventDefault(); load(); });
    $('#btcReset').on('click', function () { $('#btcFilters')[0].reset(); sum = 'all'; load(); });
    $('#btcApFilter').on('change', load);   // BTC-REQ-15
    $('#btcTabs').on('click', '.nav-link', function (e) { e.preventDefault(); $('#btcTabs .nav-link').removeClass('active'); $(this).addClass('active'); tab = $(this).data('tab'); load(); });
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
        // BTC-TRIP-01: Air / Train - from / to, trip type and journey dates in place of location and activity dates
        const trip = @json((array) config('btc.trip_categories', ['air', 'train'])).includes(c);
        $('.btc-trip').toggle(trip); $('.btc-notrip').toggle(!trip);
        if (trip) $('[name=location],[name=service_from],[name=service_to]').val(''); else $('[name=travel_from],[name=travel_to],[name=travel_date],[name=return_date]').val('');
        const rt = trip && $('[name=trip_type]:checked').val() === 'round_trip'; $('.btc-return').toggle(rt); if (!rt) $('[name=return_date]').val('');
        // BTC-BUS-01: Bus - booking for, passengers and class in place of pax / employees / guests
        const bus = BUS_CATS.includes(c), fr = $('#btcBookFor').val();
        $('.btc-bus').toggle(bus); $('.btc-nobus').toggle(!bus);
        $('.btc-self').toggle(bus && fr === 'self'); $('.btc-team').toggle(bus && fr === 'team');
        if (bus) busClass();
        // BTC-REQ-09/11 (17 Nov 2026): admin booking - no estimate / vendor fields (the admin books)
        $('.btc-nota').toggle(r !== 'admin_booking');
        if (r === 'admin_booking') { $('#btcVendor').val('').trigger('change.select2'); $('[name=estimated_amount],[name=vendor_name],[name=vendor_gstin]').val(''); }
        // BTC-REQ-25: category shown as "Air" for both request types
        // BTC-REQ-25: PNR only for Air with "Advance Required" (the employee has the ticket); hidden for Admin Booking
        const pnr = c === 'air' && r === 'employee_advance'; $('.btc-pnr').toggle(pnr); if (!pnr) $('[name=pnr]').val('');
        $('#btcFiles').html(fileRow(r === 'employee_advance' ? 'quotation' : (r === 'direct_bill' ? 'bill' : 'supporting')));
    }
    $('#btcCategory, #btcVendor').on('change', shape); $(document).on('change', '[name=trip_type]', shape); shape();
    $('#btcGo').on('change', function () { $('#btcRet').attr('min', this.value); });
    // ---- BTC-BUS-01 (testing point 27): passengers and eligibility-based class ----
    const eligOf = ids => ids.length ? $.getJSON('{{ route('btc.bus-eligibility') }}', {ids: ids.join(',')}).then(r => { (r || []).forEach(x => busElig[x.employee_id] = x); }) : $.Deferred().resolve();
    function busPassengers() {
        if ($('#btcBookFor').val() === 'self') return [{type: 'employee', id: ME}];
        return $('#btcPassengers .btc-px').map(function () { const t = $(this).find('.btc-pxt').val(); return {type: t, id: Number($(this).find('.btc-pxe').val() || 0), name: $(this).find('.btc-pxg').val()}; }).get();
    }
    function busClass() {
        const px = busPassengers(), emps = px.filter(p => p.type === 'employee' && p.id);
        let max = 'AC';
        emps.forEach(p => { const e = (busElig[p.id] || {}).eligible || 'Non-AC'; if ((BUS_RANK[e] || 0) < (BUS_RANK[max] || 0)) max = e; });
        const cur = $('#btcClass').val();
        $('#btcClass').html('<option value="">— choose —</option>' + Object.keys(BUS_RANK).filter(k => BUS_RANK[k] <= BUS_RANK[max]).map(k => `<option ${k === cur ? 'selected' : ''}>${k}</option>`).join(''));
        $('#btcClassHint').text(emps.length ? 'Eligible up to ' + max + (emps.length > 1 ? ' (lowest of the employees)' : '') + '. If no seat is available, Admin may propose a higher class for your manager to approve.' : '');
        $('#btcPassengers .btc-px').each(function () { const id = Number($(this).find('.btc-pxe').val() || 0), t = $(this).find('.btc-pxt').val(); $(this).find('.btc-pxel').text(t === 'guest' ? 'Guest policy' : (busElig[id] ? busElig[id].eligible : '-')); });
    }
    function pxRow(i) {
        return `<div class="row g-2 align-items-end btc-px mb-1"><div class="col-auto"><span class="btc-label">PAX ${i + 1}</span></div>
            <div class="col-md-2"><select class="form-select form-select-sm btc-pxt" name="passengers[${i}][type]" aria-label="Passenger ${i + 1} type"><option value="employee">Employee</option><option value="guest">Guest</option></select></div>
            <div class="col-md-5 btc-pxew"><select class="form-select form-select-sm btc-pxe" name="passengers[${i}][employee_id]" aria-label="Passenger ${i + 1} employee"></select></div>
            <div class="col-md-5 btc-pxgw" style="display:none"><input class="form-control form-control-sm btc-pxg" name="passengers[${i}][name]" maxlength="100" placeholder="Guest name" aria-label="Passenger ${i + 1} guest name"></div>
            <div class="col-md-3 small">Eligible: <b class="btc-pxel">-</b></div></div>`;
    }
    $('#btcBookFor').on('change', function () {
        if (this.value === 'self') eligOf([ME]).then(() => { const e = busElig[ME] || {}; $('#btcSelfInfo').text((e.code || '') + ' – ' + (e.name || '') + ' · eligible: ' + (e.eligible || '-') + ' · PAX 1'); busClass(); });
        shape();
    });
    $('#btcBusPax').on('change', function () {
        const n = Math.max(0, Math.min(50, Number(this.value) || 0)), $w = $('#btcPassengers'), have = $w.find('.btc-px').length;
        for (let i = have; i < n; i++) {
            $w.append(pxRow(i));
            $w.find('.btc-pxe').last().select2({dropdownParent: $('#btcNewModal'), width: '100%', placeholder: 'Employee code or name', minimumInputLength: 2,
                ajax: {url: '{{ route('btc.approvers') }}', delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}});
        }
        $w.find('.btc-px').slice(n).remove();
        busClass();
    });
    $(document).on('change', '#btcPassengers .btc-pxt', function () { const g = this.value === 'guest', $r = $(this).closest('.btc-px'); $r.find('.btc-pxew').toggle(!g); $r.find('.btc-pxgw').toggle(g); if (g) $r.find('.btc-pxe').val(null).trigger('change'); busClass(); });
    $(document).on('change', '#btcPassengers .btc-pxe', function () { const id = Number(this.value || 0); (id && !busElig[id] ? eligOf([id]) : $.Deferred().resolve()).then(busClass); });
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
    // BTC-NEW-BLANK-01 (20 Nov 2026, tracker tab "20-11-2026" #1): every new case starts from a blank form - nothing is
    // carried over from the case saved before, a draft, or a form closed without saving
    let btcSaving = false, btcToken = '';
    const newToken = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(36).slice(2);
    function freshForm() {
        btcToken = newToken();   // BTC-DUP-01: a new form, a new token
        const f = $('#btcNewForm')[0];
        f.reset();
        $('#btcVendor, #btcTravellers').val(null).trigger('change');
        $('#btcNewForm').find('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), textarea').val('');
        $('#btcPax').val('1');
        $('#btcPassengers').empty(); $('#btcSelfInfo').text('—'); $('#btcBookFor').val('');   // BTC-BUS-01
        $('#btcNewForm select:not(#btcVendor):not(#btcTravellers)').each(function () { this.selectedIndex = 0; $(this).trigger('change.select2'); });
        const $r = $('.btc-route-radio'); $r.prop('checked', false); $r.first().prop('checked', true);
        $('#btcRoute').val($r.first().val() || $('#btcRoute').attr('value'));
        $('#btcNewForm [data-submit]').prop('disabled', false);
        shape();
    }
    $('#btcNewModal').on('show.bs.modal', freshForm);
    $('#btcNewForm [data-submit]').on('click', function () {
        if (!checkAmounts($('#btcNewForm'))) return;
        if (!$('#btcPurpose').val()) { Swal.fire('Purpose needed', 'Choose the purpose of the requirement.', 'warning'); return; }
        if (String($('#btcPurpose').val()).startsWith('Other') && !$('#btcRemarks').val().trim()) { Swal.fire('Remarks needed', 'Write the purpose in the remarks.', 'warning'); return; }
        if ($('.btc-bus:visible').length) {   // BTC-BUS-01
            const fr = $('#btcBookFor').val();
            if (!fr) { Swal.fire('Required', 'Choose Booking for: Self or Team + Guest.', 'warning'); return; }
            if (fr === 'team') {
                const n = Number($('#btcBusPax').val() || 0), rows = $('#btcPassengers .btc-px');
                if (!n || rows.length !== n) { Swal.fire('Required', 'Enter the total PAX and each passenger.', 'warning'); return; }
                const bad = rows.filter(function () { return $(this).find('.btc-pxt').val() === 'guest' ? !$(this).find('.btc-pxg').val().trim() : !$(this).find('.btc-pxe').val(); });
                if (bad.length) { Swal.fire('Required', 'Choose the employee or type the guest name for every passenger.', 'warning'); return; }
            }
            if (!$('#btcClass').val()) { Swal.fire('Required', 'Choose the travel class.', 'warning'); return; }
            $('#btcPax').val(String(fr === 'self' ? 1 : Number($('#btcBusPax').val())));
        }
        if ($('.btc-trip:visible').length) {   // BTC-TRIP-01
            const miss = [['#btcFrom', 'Travel from'], ['#btcTo', 'Travel to'], ['#btcGo', 'Travel date (onward)']].concat($('.btc-return:visible').length ? [['#btcRet', 'Return travel date']] : []).filter(([s]) => !String($(s).val() || '').trim()).map(([, l]) => l);
            if (miss.length) { Swal.fire('Required', 'Fill in: ' + miss.join(', ') + '.', 'warning'); return; }
        }
        // BTC-DUP-01 (26 Nov 2026, testing point 25): one request per submission - the buttons are locked while saving and
        // the form carries a one-time token the server accepts once (a double click or a repeated post makes no copy)
        if (btcSaving) return;
        btcSaving = true;
        const $btns = $('#btcNewForm [data-submit]').prop('disabled', true);
        const fd = new FormData($('#btcNewForm')[0]); fd.append('submit', $(this).data('submit')); fd.append('submit_token', btcToken);
        $.ajax({url: '{{ route('btc.store') }}', method: 'POST', data: fd, processData: false, contentType: false, headers: {'X-CSRF-TOKEN': CSRF}})
            .done(r => { Swal.fire('Saved', r.message, r.message.startsWith('Saved as draft') ? 'warning' : 'success'); bootstrap.Modal.getInstance(document.getElementById('btcNewModal')).hide(); freshForm(); load(); })
            .fail(x => Swal.fire('Not saved', (x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {}).flat().join('\n'))) || 'Error', 'error'))
            .always(() => { btcSaving = false; $btns.prop('disabled', false); });
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
