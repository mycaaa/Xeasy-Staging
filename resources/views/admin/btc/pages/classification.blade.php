{{-- BTC-SCAN-01 (19 Nov 2026, tracker tab "Data Punching Form & Finance approval screens", 2): Classification -
     (A) front screen: the scanned bills; (B) classification form: the bill type (Hotel, HRV, Air, Train, Bus, Meals,
     Meeting expense) and whom it is for, or the BTC request it belongs to --}}
@extends('layouts.app')
@section('content')
@php $inr = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2); @endphp
<style>
    .cl-types { display: flex; flex-wrap: wrap; gap: 6px; }
    .cl-types label { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 14px; cursor: pointer; font-size: 13px; background: #fff; }
    .cl-types input { position: absolute; opacity: 0; }
    .cl-types input:checked + span { font-weight: 600; }
    .cl-types label:has(input:checked) { border-color: #2563eb; background: #eff6ff; color: #1e3a8a; }
    .cl-types label:has(input:focus-visible) { outline: 2px solid #2563eb; outline-offset: 2px; }
</style>
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => $title, 'icon' => $icon, 'sub' => $sub])
    <div class="xe-seg mb-2">
        @foreach ($tabs as $k => $l)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $l }} <span class="badge bg-light text-dark">{{ $counts[$k] }}</span></a>
        @endforeach
        <form method="get" class="ms-auto d-flex gap-1"><input type="hidden" name="tab" value="{{ $tab }}"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="BTC ID" aria-label="Search"><button class="xe-btn xe-btn-plain">Search</button></form>
    </div>
    <section class="xe-section"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Scan</th><th>Scanned</th><th>Received from</th><th>Scanned by</th><th>Type</th><th>For</th><th>Stage</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $c)
                @php $bf = $c->files()->where('kind', 'bill')->orderByDesc('id')->first(); @endphp
                <tr>
                    <td class="fw-semibold text-nowrap">{{ $c->case_no }}<div class="small text-muted">{{ $bf ? \Illuminate\Support\Str::limit($bf->file_name, 30) : 'no file' }}</div></td>
                    <td class="small text-nowrap">{{ optional($c->bill_uploaded_at ?? $c->created_at)->format('d-m-Y H:i') }}</td>
                    <td class="small">{{ config('btc.bill_sources.'.$c->bill_source, $c->bill_source) }}</td>
                    <td class="small">{{ $c->bill_uploaded_by ? \App\Models\User::whereKey($c->bill_uploaded_by)->value('name') : '' }}</td>
                    <td class="small">{{ $c->status === 'scanned' ? '-' : $c->categoryLabel() }}</td>
                    <td class="small">{{ trim($c->emp_code.' '.$c->emp_name) ?: ($c->department_name ?: '-') }}</td>
                    <td class="small">{{ $c->statusLabel() }}</td>
                    <td class="text-nowrap">
                        @if ($c->status === 'scanned')
                            <button type="button" class="xe-btn xe-btn-blue" data-classify="{{ $c->id }}" data-no="{{ $c->case_no }}" data-file="{{ $bf ? route('btc.file', $bf->id) : '' }}" data-pdf="{{ $bf && str_ends_with(strtolower($bf->file_name), '.pdf') ? 1 : 0 }}">Classify</button>
                        @endif
                        <button type="button" class="xe-btn xe-btn-plain" data-open="{{ $c->id }}">Open</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No scanned bill waiting. Scan bills on the <a href="{{ route('btc.scanner') }}">Scanner</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></section>
    <div class="mt-2">{{ $rows->links() }}</div>
</div></div>

<div class="modal fade" id="clModal" tabindex="-1" aria-labelledby="clTitle">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down"><div class="modal-content">
        <div class="modal-header py-2"><h6 class="modal-title" id="clTitle">Classify</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-lg-6" id="clScan"></div>
            <div class="col-lg-6">
                <form id="clForm" class="row g-2">
                    <div class="col-12"><div class="btc-label mb-1" id="clTypeLabel">Bill type</div>
                        <div class="cl-types" role="radiogroup" aria-labelledby="clTypeLabel">
                            @foreach ($types as $k => $l)<label><input type="radio" name="category" value="{{ $k }}"><span>{{ $l }}</span></label>@endforeach
                        </div></div>
                    <div class="col-12"><label class="btc-label" for="clLink">Belongs to a BTC request? (optional)</label><select id="clLink" name="link_case_id" class="form-select form-select-sm"></select>
                        <div class="small text-muted">Choose the request if the bill is for one already raised; otherwise say whom it is for below.</div></div>
                    <div class="col-12 row g-2 m-0 p-0" id="clNew">
                        <div class="col-md-6"><label class="btc-label" for="clEmp">For employee</label><select id="clEmp" name="employee_id" class="form-select form-select-sm"></select></div>
                        <div class="col-md-6"><label class="btc-label" for="clDept">or department</label><input id="clDept" name="department_name" maxlength="150" class="form-control form-control-sm"></div>
                        <div class="col-12"><label class="btc-label" for="clPurpose">Purpose</label><select id="clPurpose" name="purpose" class="form-select form-select-sm"><option value="">— choose —</option>@foreach ($purposes as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select></div>
                    </div>
                    <div class="col-12" id="clRead"></div>
                    <div class="col-12"><label class="btc-label" for="clRemark">Remark</label><input id="clRemark" name="remark" maxlength="500" class="form-control form-control-sm"></div>
                    <div class="col-12 d-flex gap-2"><button type="button" class="btn btn-sm btn-primary" id="clSave">Save and send to Punch Screen</button><span class="small text-muted align-self-center" id="clMsg"></span></div>
                </form>
            </div>
        </div></div>
    </div></div>
</div>
@include('admin.btc._casewin')
@endsection
@push('scripts')
<script>
$(function () {
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    const $m = $('#clModal'), esc = s => $('<div>').text(s ?? '').html();
    $('#clLink').select2({dropdownParent: $m, width: '100%', allowClear: true, placeholder: 'Request no., employee or vendor',
        ajax: {url: @json(route('btc.bill-upload.cases')), delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}});
    $('#clEmp').select2({dropdownParent: $m, width: '100%', allowClear: true, placeholder: 'Employee code or name', minimumInputLength: 2,
        ajax: {url: @json(route('btc.approvers')), delay: 250, data: p => ({q: p.term}), processResults: d => ({results: d})}});
    $('#clLink').on('change', () => $('#clNew').toggle(!$('#clLink').val()));
    let id = null;
    $(document).on('click', '[data-classify]', function () {
        const b = $(this).data(); id = b.classify;
        $('#clTitle').text('Classify ' + b.no);
        $('#clForm')[0].reset(); $('#clLink, #clEmp').val(null).trigger('change'); $('#clMsg').text('');
        $('#clScan').html(b.file ? `<p class="btc-label">Scanned bill · <a target="_blank" rel="noopener" href="${b.file}">open in a new tab</a></p>` + (Number(b.pdf) ? `<iframe src="${b.file}" title="Scanned bill" style="width:100%;height:70vh;border:1px solid #e5e7eb;border-radius:6px"></iframe>` : `<img src="${b.file}" alt="Scanned bill" style="max-width:100%;border:1px solid #e5e7eb;border-radius:6px">`) : '<div class="text-muted">No file</div>');
        bootstrap.Modal.getOrCreateInstance($m[0]).show();
        // BTC-CLASSIFY-02 (24 Nov 2026): read the bill first and fill the form; the person checks and corrects
        $('#clRead').html('<div class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Reading the bill to fill the form…</div>');
        const cid = id;
        $.ajax({url: @json(url('btc/classification')) + '/' + cid + '/suggest', method: 'POST', headers: {'X-CSRF-TOKEN': CSRF}})
            .done(r => {
                if (cid !== id) return;
                if (!r.success) { $('#clRead').html('<div class="small text-warning-emphasis">' + esc(r.message) + '</div>'); return; }
                $('#clForm [name=category][value="' + r.category + '"]').prop('checked', true);
                if (r.link) { $('#clLink').append(new Option(r.link.text, r.link.id, true, true)).trigger('change'); }
                else if (r.employee) { $('#clEmp').append(new Option(r.employee.text, r.employee.id, true, true)).trigger('change'); }
                const rows = Object.entries(r.read || {}).map(([k, v]) => `<tr><td class="text-muted text-nowrap">${esc(k)}</td><td>${esc(v)}</td></tr>`).join('');
                $('#clRead').html(`<div class="border rounded p-2 small"><div class="fw-semibold mb-1">Filled from the bill - check and correct</div>
                    <div>Type: <b>${esc(r.category_label)}</b>${r.link ? ' · request <b>' + esc(r.link.text.split(' · ')[0]) + '</b>' : ''}${r.employee ? ' · employee <b>' + esc(r.employee.text) + '</b>' : ' · employee not found on the bill - choose him'}</div>
                    <details class="mt-1"><summary>What was read (${Object.keys(r.read || {}).length} fields)</summary><table class="table table-sm mb-0">${rows}</table></details></div>`);
            })
            .fail(() => { if (cid === id) $('#clRead').html('<div class="small text-warning-emphasis">The bill could not be read; classify by hand.</div>'); });
    });
    $('#clSave').on('click', function () {
        if (!$('#clForm [name=category]:checked').length) { $('#clMsg').addClass('text-danger').text('Choose the bill type.'); return; }
        const $b = $(this).prop('disabled', true); $('#clMsg').removeClass('text-danger').text('Saving and reading the bill…');
        $.ajax({url: @json(url('btc/classification')) + '/' + id, method: 'POST', data: $('#clForm').serialize(), headers: {'X-CSRF-TOKEN': CSRF}})
            .done(r => { bootstrap.Modal.getInstance($m[0]).hide(); Swal.fire('Classified', r.message, 'success').then(() => location.reload()); })
            .fail(x => $('#clMsg').addClass('text-danger').text((x.responseJSON && (x.responseJSON.message || Object.values(x.responseJSON.errors || {}).flat().join(' '))) || 'Not saved.'))
            .always(() => $b.prop('disabled', false));
    });
    $(document).on('click', '[data-open]', function (e) { e.stopPropagation(); window.BtcCase.open($(this).data('open')); });
    $(document).on('btc:changed', function () { $('#btcCaseModal').one('hidden.bs.modal', () => location.reload()); });
});
</script>
@endpush
