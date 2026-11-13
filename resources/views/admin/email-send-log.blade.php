@extends('layouts.app')

@section('styles')
<style>
    .card-stat { border-left: 4px solid; border-radius: 6px; }
    .card-stat.sent    { border-color: #198754; }
    .card-stat.failed  { border-color: #dc3545; }
    .card-stat.notsent { border-color: #ffc107; }
    .card-stat.total   { border-color: #0d6efd; }
    .card-stat .stat-num { font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .card-stat .stat-lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #6c757d; }
    #logTable th { background: #dde6ff; color: #1e3a8a; font-size: 11px; white-space: nowrap; vertical-align: middle; }
    #logTable td { font-size: 12px; vertical-align: middle; }
    #logTable tbody tr:hover { background: #f0f7ff; }
    .filter-card .form-label { font-size: 11px; font-weight: 600; margin-bottom: 2px; }
    .filter-card .form-select, .filter-card .form-control { font-size: 12px; }
    .badge { font-size: 10px; }
    .btn-xs { padding: 2px 7px; font-size: 11px; }
    .by-type-row { font-size: 12px; border-bottom: 1px solid #f0f0f0; padding: 4px 0; }
    .by-type-row:last-child { border-bottom: none; }
    .detail-label { font-size: 11px; font-weight: 600; color: #6c757d; text-transform: uppercase; }
    .detail-val   { font-size: 13px; }
    #emailBodyFrame { width: 100%; min-height: 400px; border: 1px solid #dee2e6; border-radius: 6px; }
</style>
@endsection

@section('content')
<div class="page-content">
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0 fw-bold" style="color:#15648C;">
                <i class="ri-mail-send-line me-2"></i>Email Send Log
            </h4>
            <p class="text-muted mb-0" style="font-size:12px;">
                Complete audit trail of every email sent by the system
            </p>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary" id="btnRefresh">
                <i class="ri-refresh-line me-1"></i>Refresh
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-3" id="statsRow">
        <div class="col-6 col-md-3">
            <div class="card card-stat total p-3">
                <div class="stat-num text-primary" id="statTotal">—</div>
                <div class="stat-lbl mt-1">Total Emails</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-stat sent p-3">
                <div class="stat-num text-success" id="statSent">—</div>
                <div class="stat-lbl mt-1">Successfully Sent</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-stat failed p-3">
                <div class="stat-num text-danger" id="statFailed">—</div>
                <div class="stat-lbl mt-1">Failed</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-stat notsent p-3">
                <div class="stat-num text-warning" id="statNotSent">—</div>
                <div class="stat-lbl mt-1">Not Sent (Mail Inactive)</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3 filter-card">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">

                <div class="col-6 col-md-2">
                    <label class="form-label">Notification Type</label>
                    <select class="form-select form-select-sm" id="fType">
                        <option value="">All Types</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Category</label>
                    <select class="form-select form-select-sm" id="fCategory">
                        <option value="">All Categories</option>
                        <option value="expense">Expense</option>
                        <option value="advance">Advance</option>
                        <option value="payment">Payment</option>
                        <option value="general">General</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="fStatus">
                        <option value="">All</option>
                        <option value="1">Sent</option>
                        <option value="0">Not Sent / Failed</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Company</label>
                    <select class="form-select form-select-sm" id="fCompany">
                        <option value="">All</option>
                        <option value="1">VNR</option>
                        <option value="11">VVNR</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Month</label>
                    <select class="form-select form-select-sm" id="fMonth">
                        <option value="">All Months</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Ref. Type</label>
                    <select class="form-select form-select-sm" id="fRefType">
                        <option value="">All</option>
                        <option value="expense_claim">Expense Claim</option>
                        <option value="advance_request">Advance Request</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Emp Code</label>
                    <input type="text" class="form-control form-control-sm" id="fEmpCode" placeholder="e.g. E0234" aria-label="e.g. E0234">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Recipient Email</label>
                    <input type="text" class="form-control form-control-sm" id="fEmail" placeholder="@vnrseeds.com" aria-label="@vnrseeds.com">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control form-control-sm" id="fDateFrom">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control form-control-sm" id="fDateTo">
                </div>

                <div class="col-12 col-md-4 d-flex gap-2 align-items-end">
                    <button class="btn btn-sm btn-primary" id="btnApply">
                        <i class="ri-filter-line me-1"></i>Apply
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnReset">
                        <i class="ri-refresh-line me-1"></i>Reset
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- Main Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="logTable" class="table table-sm table-bordered mb-0 w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Subject</th>
                            <th>Recipient</th>
                            <th>CC</th>
                            <th>Stage</th>
                            <th>Month</th>
                            <th>Company</th>
                            <th>Reference</th>
                            <th>Triggered By</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>{{-- /container-fluid --}}
</div>{{-- /page-content --}}


{{-- ── Detail Modal ────────────────────────────────────────────── --}}
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:#dde6ff; color: #1e3a8a;">
                <h5 class="modal-title text-white">
                    <i class="ri-mail-open-line me-2"></i>Email Detail
                    <span class="badge bg-white text-dark ms-2 fw-normal" id="detailId"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

@endsection


@push('scripts')
<script>
$(function () {

    // ── Load filter dropdowns ──────────────────────────────────────
    $.get('{{ route("email.send.log.filter.options") }}', function (res) {
        res.types.forEach(t => $('#fType').append(`<option value="${t}">${t}</option>`));
        res.months.forEach(m => $('#fMonth').append(`<option value="${m}">${m}</option>`));
    });

    // ── DataTable ─────────────────────────────────────────────────
    const table = $('#logTable').DataTable({
        processing : true,
        serverSide : true,
        ajax: {
            url: '{{ route("email.send.log.data") }}',
            data: function (d) {
                d.notification_type  = $('#fType').val();
                d.mail_category      = $('#fCategory').val();
                d.is_sent            = $('#fStatus').val();
                d.company_id         = $('#fCompany').val();
                d.sent_for_month     = $('#fMonth').val();
                d.ref_type           = $('#fRefType').val();
                d.emp_code           = $('#fEmpCode').val();
                d.recipient_email    = $('#fEmail').val();
                d.date_from          = $('#fDateFrom').val();
                d.date_to            = $('#fDateTo').val();
            }
        },
        columns: [
            { data: 'id',                orderable: true,  searchable: false, className: 'text-center text-muted', width: '50px' },
            { data: 'created_at',        orderable: true,  className: 'text-center', width: '115px' },
            { data: 'notification_type', orderable: false, className: 'text-center' },
            { data: 'mail_category',     orderable: false, className: 'text-center' },
            { data: 'subject',           orderable: false },
            { data: 'recipient',         orderable: false },
            { data: 'cc',                orderable: false, className: 'text-center' },
            { data: 'stage',             orderable: false, className: 'text-center' },
            { data: 'sent_for_month',    orderable: false, className: 'text-center' },
            { data: 'company',           orderable: false, className: 'text-center' },
            { data: 'ref',               orderable: false, className: 'text-center' },
            { data: 'sender',            orderable: false },
            { data: 'status',            orderable: false, className: 'text-center' },
            { data: 'actions',           orderable: false, className: 'text-center', width: '60px' },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: '<"row align-items-center mb-2"<"col-auto"l><"col"f>><"row"<"col"tr>><"row align-items-center mt-2"<"col-md-5"i><"col-md-7"p>>',
        language: { processing: '<div class="spinner-border spinner-border-sm text-primary"></div> Loading...' },
    });

    // ── Apply / Reset filters ─────────────────────────────────────
    $('#btnApply').on('click', function () {
        table.ajax.reload();
        loadStats();
    });

    $('#btnReset').on('click', function () {
        $('#fType,#fCategory,#fStatus,#fCompany,#fMonth,#fRefType').val('');
        $('#fEmpCode,#fEmail,#fDateFrom,#fDateTo').val('');
        table.ajax.reload();
        loadStats();
    });

    $('#btnRefresh').on('click', function () {
        table.ajax.reload();
        loadStats();
    });

    // ── Stats cards ───────────────────────────────────────────────
    function loadStats() {
        $.get('{{ route("email.send.log.stats") }}', {
            date_from: $('#fDateFrom').val(),
            date_to:   $('#fDateTo').val(),
            company_id: $('#fCompany').val(),
        }, function (res) {
            $('#statTotal').text(res.total.toLocaleString());
            $('#statSent').text(res.sent.toLocaleString());
            $('#statFailed').text(res.failed.toLocaleString());
            $('#statNotSent').text(res.not_sent.toLocaleString());
        });
    }
    loadStats();

    // ── Detail modal ──────────────────────────────────────────────
    $('#logTable').on('click', '.btn-view-detail', function () {
        const id = $(this).data('id');
        $('#detailId').text('#' + id);
        $('#detailBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        $('#detailModal').modal('show');

        $.get('{{ route("email.send.log.detail") }}', { id: id, company_id: $('#fCompany').val() }, function (res) {
            if (!res.success) { $('#detailBody').html('<div class="alert alert-danger">Not found.</div>'); return; }
            const d = res.data;

            const statusBadge = d.is_sent
                ? '<span class="badge bg-success">Sent</span>'
                : (d.failed_at
                    ? '<span class="badge bg-danger">Failed</span>'
                    : '<span class="badge bg-warning text-dark">Not Sent</span>');

            let errorHtml = '';
            if (d.error_message) {
                errorHtml = `<div class="alert alert-danger mt-3 mb-0 p-2" style="font-size:12px;">
                    <strong><i class="ri-error-warning-line me-1"></i>Error:</strong> ${escHtml(d.error_message)}
                </div>`;
            }

            $('#detailBody').html(`
            <div class="row g-3">

                {{-- Left: Meta info --}}
                <div class="col-md-5">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body p-3">

                            <h6 class="fw-bold mb-3" style="color:#15648C;"><i class="ri-information-line me-1"></i>Email Info</h6>

                            <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                                <tbody>
                                    <tr><td class="detail-label" style="width:38%">Log ID</td><td class="detail-val">#${d.id}</td></tr>
                                    <tr><td class="detail-label">Type</td>
                                        <td><span class="badge bg-dark-subtle text-dark">${escHtml(d.notification_type)}</span></td></tr>
                                    <tr><td class="detail-label">Category</td><td class="detail-val">${escHtml(d.mail_category ?? '—')}</td></tr>
                                    <tr><td class="detail-label">Template</td><td class="detail-val">${escHtml(d.template_name ?? '—')}${d.template_id ? ' <small class="text-muted">(#' + d.template_id + ')</small>' : ''}</td></tr>
                                    <tr><td class="detail-label">Stage</td><td class="detail-val">${escHtml(d.stage ?? '—')}</td></tr>
                                    <tr><td class="detail-label">Month</td><td class="detail-val">${escHtml(d.sent_for_month ?? '—')}</td></tr>
                                    <tr><td class="detail-label">Company</td><td class="detail-val">${d.company_id == 11 ? '<span class="badge bg-warning-subtle text-warning">VVNR</span>' : (d.company_id ? '<span class="badge bg-primary-subtle text-primary">VNR</span>' : '—')}</td></tr>
                                    <tr><td class="detail-label">Ref. Type</td><td class="detail-val">${escHtml(d.ref_type ?? '—')}</td></tr>
                                    <tr><td class="detail-label">Ref. ID</td><td class="detail-val">${d.ref_id ? '#' + d.ref_id : '—'}</td></tr>
                                    <tr><td class="detail-label">Status</td><td>${statusBadge}</td></tr>
                                    <tr><td class="detail-label">Sent At</td><td class="detail-val">${d.sent_at ? fmtDate(d.sent_at) : '—'}</td></tr>
                                    <tr><td class="detail-label">Failed At</td><td class="detail-val">${d.failed_at ? '<span class="text-danger">' + fmtDate(d.failed_at) + '</span>' : '—'}</td></tr>
                                    <tr><td class="detail-label">Logged At</td><td class="detail-val">${fmtDate(d.created_at)}</td></tr>
                                </tbody>
                            </table>

                            <hr class="my-2">
                            <h6 class="fw-bold mb-2" style="color:#15648C;font-size:12px;"><i class="ri-user-line me-1"></i>Recipient</h6>
                            <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                                <tbody>
                                    <tr><td class="detail-label" style="width:38%">Name</td><td class="detail-val">${escHtml(d.recipient_name ?? '—')}</td></tr>
                                    <tr><td class="detail-label">Emp Code</td><td class="detail-val">${escHtml(d.recipient_emp_code ?? '—')}</td></tr>
                                    <tr><td class="detail-label">Email</td><td class="detail-val">${escHtml(d.recipient_email ?? '—')}</td></tr>
                                    <tr><td class="detail-label">CC</td><td class="detail-val" style="word-break:break-all;">${escHtml(d.cc_emails ?? '—')}</td></tr>
                                    <tr><td class="detail-label">BCC</td><td class="detail-val">${escHtml(d.bcc_emails ?? '—')}</td></tr>
                                </tbody>
                            </table>

                            <hr class="my-2">
                            <h6 class="fw-bold mb-2" style="color:#15648C;font-size:12px;"><i class="ri-user-settings-line me-1"></i>Triggered By</h6>
                            <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                                <tbody>
                                    <tr><td class="detail-label" style="width:38%">Name</td><td class="detail-val">${escHtml(d.sender_name ?? 'System / Scheduled')}</td></tr>
                                    <tr><td class="detail-label">Emp Code</td><td class="detail-val">${escHtml(d.sender_emp_code ?? '—')}</td></tr>
                                </tbody>
                            </table>

                            ${errorHtml}
                        </div>
                    </div>
                </div>

                {{-- Right: Subject + Body --}}
                <div class="col-md-7">
                    <div class="mb-2">
                        <div class="detail-label">Subject</div>
                        <div class="p-2 bg-light border rounded" style="font-size:13px;font-weight:600;">${escHtml(d.subject)}</div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="detail-label">Email Body</div>
                            <div class="form-check form-switch mb-0" style="font-size:11px;">
                                <input class="form-check-input" type="checkbox" id="toggleBodyView" checked>
                                <label class="form-check-label" for="toggleBodyView">Rendered HTML</label>
                            </div>
                        </div>
                        <iframe id="emailBodyFrame" srcdoc="" style="display:block;"></iframe>
                        <pre id="emailBodyRaw" class="p-2 bg-light border rounded" style="display:none;font-size:11px;max-height:400px;overflow:auto;white-space:pre-wrap;"></pre>
                    </div>
                </div>

            </div>
            `);

            // Set iframe content
            const bodyHtml = d.body_html || '<p style="color:#999;font-style:italic;">No HTML body stored.</p>';
            document.getElementById('emailBodyFrame').srcdoc = bodyHtml;
            document.getElementById('emailBodyRaw').textContent = bodyHtml;

            // Toggle raw / rendered
            $('#toggleBodyView').on('change', function () {
                if (this.checked) {
                    $('#emailBodyFrame').show();
                    $('#emailBodyRaw').hide();
                } else {
                    $('#emailBodyFrame').hide();
                    $('#emailBodyRaw').show();
                }
            });
        }).fail(function () {
            $('#detailBody').html('<div class="alert alert-danger">Failed to load detail.</div>');
        });
    });

    // ── Helpers ────────────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtDate(str) {
        if (!str) return '—';
        try {
            const d = new Date(str);
            return d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        } catch(e) { return str; }
    }

});
</script>
@endpush
