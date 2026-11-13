@extends('layouts.app')

@section('title', 'Bill to Company')

@section('content')
<style>
    .table-hover tbody tr:hover { background-color: #e9f3ff !important; }
    .preview-thumb { max-height: 60px; max-width: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #dee2e6; }
    .badge-category { font-size: 11px; padding: 4px 8px; }
    #uploadDropzone { border: 2px dashed #405189; border-radius: 8px; background: #f8f9ff; transition: background .2s; cursor: pointer; }
    #uploadDropzone.drag-over { background: #e1e8ff; }
    #filePreviewWrap img, #filePreviewWrap iframe { max-width: 100%; border-radius: 6px; }
</style>

<div class="page-content">
    <div class="container-fluid">

        {{-- Page header --}}
        <div class="row mb-3">
            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#1e3a5f;">
                        <i class="ri-file-upload-line me-1 text-primary"></i> Bill to Company
                    </h5>
                    <p class="text-muted mb-0" style="font-size:12px;">
                        Upload and manage company bills for employees
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ url('bill-to-company/categories') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-folder-settings-line me-1"></i> Manage Categories
                    </a>
                    <button class="btn btn-primary btn-sm" id="btnOpenUpload">
                        <i class="ri-upload-2-line me-1"></i> Upload Bill
                    </button>
                </div>
            </div>
        </div>

        {{-- Filter card --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label mb-1" style="font-size:12px;">Employee</label>
                        <select id="filterEmployee" class="form-select form-select-sm select2-employee" style="width:100%;">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label mb-1" style="font-size:12px;">Category</label>
                        <select id="filterCategory" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label mb-1" style="font-size:12px;">Date From</label>
                        <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label mb-1" style="font-size:12px;">Date To</label>
                        <input type="date" id="filterDateTo" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3 col-sm-12 d-flex gap-1">
                        <button id="btnApplyFilter" class="btn btn-sm btn-primary flex-fill">
                            <i class="ri-filter-line me-1"></i> Apply
                        </button>
                        <button id="btnResetFilter" class="btn btn-sm btn-secondary flex-fill">
                            <i class="ri-restart-line me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Data Table --}}
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="billsTable" class="table table-hover table-bordered align-middle nowrap" style="width:100%;">
                        <thead class="table-light">
                            <tr>
                                <th style="font-size:12px;">Employee</th>
                                <th style="font-size:12px;">Bill Title</th>
                                <th style="font-size:12px;">Category</th>
                                <th style="font-size:12px;">Amount</th>
                                <th style="font-size:12px;">Bill Date</th>
                                <th style="font-size:12px;">File</th>
                                <th style="font-size:12px;">Remarks</th>
                                <th style="font-size:12px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     UPLOAD MODAL
═══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#dde6ff; color: #1e3a8a;">
                <h5 class="modal-title text-white" id="uploadModalLabel">
                    <i class="ri-file-upload-line me-1"></i> Upload Bill to Company
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Employee select (Select2 remote search) --}}
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="font-size:13px;">
                                Employee <span class="text-danger">*</span>
                            </label>
                            <select name="employee_id" id="modalEmployee" class="form-select select2-employee-modal" style="width:100%;" required>
                                <option value="">Search by name or code…</option>
                            </select>
                            <input type="hidden" name="emp_code" id="hidEmpCode">
                            <input type="hidden" name="emp_name" id="hidEmpName">
                        </div>

                        {{-- Bill Title --}}
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" style="font-size:13px;">
                                Bill Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="bill_title" id="billTitle" class="form-control form-control-sm"
                                placeholder="e.g. Annual Medical Insurance Premium" aria-label="e.g. Annual Medical Insurance Premium" maxlength="255" required>
                        </div>

                        {{-- Category --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:13px;">Category</label>
                            <div class="d-flex gap-1">
                                <select name="bill_category" id="billCategory" class="form-select form-select-sm flex-fill">
                                    <option value="">Select Category</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnQuickAddCategory" title="Add new category">
                                    <i class="ri-add-line"></i>
                                </button>
                            </div>
                            {{-- Quick-add inline form --}}
                            <div id="quickCatForm" class="mt-1 d-none">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="quickCatName" placeholder="New category name…" aria-label="New category name…" maxlength="100">
                                    <button class="btn btn-success" type="button" id="btnQuickCatSave">
                                        <i class="ri-check-line"></i>
                                    </button>
                                    <button class="btn btn-secondary" type="button" id="btnQuickCatCancel">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Amount + Date --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:13px;">Bill Amount (₹)</label>
                            <input type="number" name="bill_amount" id="billAmount" class="form-control form-control-sm"
                                placeholder="0.00" aria-label="0.00" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:13px;">Bill Date</label>
                            <input type="date" name="bill_date" id="billDate" class="form-control form-control-sm">
                        </div>

                        {{-- Remarks --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:13px;">Remarks</label>
                            <textarea name="remarks" id="billRemarks" class="form-control form-control-sm" rows="2"
                                placeholder="Optional remarks…" aria-label="Optional remarks…" maxlength="1000"></textarea>
                        </div>

                        {{-- File Dropzone --}}
                        <div class="col-md-12">
                            <label class="form-label fw-semibold" style="font-size:13px;">
                                Bill File <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">(JPG, PNG, PDF — max 10 MB)</small>
                            </label>
                            <div id="uploadDropzone" class="p-4 text-center" onclick="document.getElementById('billFile').click();">
                                <i class="ri-cloud-upload-line" style="font-size:32px;color:#405189;"></i>
                                <p class="mb-0 mt-1" style="font-size:13px;color:#405189;">
                                    Drag &amp; drop here or <strong>click to browse</strong>
                                </p>
                                <small class="text-muted">Accepted: jpg, jpeg, png, gif, webp, pdf</small>
                                <input type="file" name="bill_file" id="billFile" class="d-none"
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
                            </div>
                            <div id="filePreviewWrap" class="mt-2 d-none"></div>
                            <div id="fileNameDisplay" class="mt-1 text-muted" style="font-size:12px;"></div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnUploadSubmit" class="btn btn-primary btn-sm">
                        <i class="ri-save-line me-1"></i> Upload Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     VIEW / PREVIEW MODAL
═══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:#dde6ff; color: #1e3a8a;">
                <h5 class="modal-title text-white" id="viewModalLabel">
                    <i class="ri-eye-line me-1"></i> Bill Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer">
                <a id="viewDownloadBtn" href="#" target="_blank" class="btn btn-sm btn-success">
                    <i class="ri-download-line me-1"></i> Download
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Remarks are now edited inline inside the view modal — no separate modal needed --}}
@endsection

@push('scripts')
<script>
$(function () {

    const CSRF        = $('meta[name="csrf-token"]').attr('content');
    const BASE        = '{{ url("bill-to-company") }}';
    const EMP_SEARCH  = '{{ route("bill-to-company.employees.search") }}';
    const CAT_LIST    = '{{ url("bill-to-company/categories/list") }}';
    const CAT_STORE   = '{{ url("bill-to-company/categories") }}';

    // ── Load dynamic categories into selects ──────────────────────────────
    function loadCategoryOptions(selectId, selectedVal) {
        $.get(CAT_LIST, function (cats) {
            const $sel = $('#' + selectId);
            const current = selectedVal !== undefined ? selectedVal : $sel.val();
            // Keep the first "All / Select" option, remove the rest
            $sel.find('option:not(:first)').remove();
            cats.forEach(function (cat) {
                $sel.append(new Option(cat.name, cat.name, false, cat.name === current));
            });
        });
    }

    loadCategoryOptions('filterCategory');
    loadCategoryOptions('billCategory');

    // ── DataTable ──────────────────────────────────────────────────────────
    const table = $('#billsTable').DataTable({
        processing : true,
        serverSide : false,
        ajax: {
            url  : BASE + '/data',
            type : 'GET',
            data : function (d) {
                d.employee_id = $('#filterEmployee').val();
                d.category    = $('#filterCategory').val();
                d.date_from   = $('#filterDateFrom').val();
                d.date_to     = $('#filterDateTo').val();
            },
            dataSrc: 'data',
        },
        columns: [
            { data: 'emp_code', render: (d, t, r) =>
                `<div style="font-size:12px;"><strong>${d}</strong><br><span class="text-muted">${r.emp_name}</span></div>` },
            { data: 'bill_title', render: d => `<span style="font-size:12px;">${d}</span>` },
            { data: 'bill_category', render: d =>
                d && d !== '—'
                    ? `<span class="badge bg-info-subtle text-info badge-category">${d}</span>`
                    : '<span class="text-muted" style="font-size:11px;">—</span>' },
            { data: 'bill_amount', render: d =>
                d && d !== '—' ? `<span style="font-size:12px;">₹${d}</span>` : '—' },
            { data: 'bill_date', render: d =>
                `<span style="font-size:12px;">${d}</span>` },
            { data: null, orderable: false, render: (d, t, r) => {
                if (r.is_image) {
                    return `<img alt="Bill or document image" src="${r.file_url}" class="preview-thumb" data-id="${r.id}" title="${r.file_name}">`;
                }
                if (r.is_pdf) {
                    return `<span class="badge bg-danger-subtle text-danger" style="cursor:pointer;" data-id="${r.id}">
                                <i class="ri-file-pdf-line"></i> ${r.file_name}
                            </span>`;
                }
                return `<span style="font-size:11px;">${r.file_name}</span>`;
            }},
            { data: 'remarks', render: d =>
                d ? `<span style="font-size:11px;">${d}</span>` : '<span class="text-muted" style="font-size:11px;">—</span>' },
            { data: null, orderable: false, render: (d, t, r) =>
                `<div class="d-flex gap-1 justify-content-center">
                    <button class="btn btn-sm btn-outline-primary p-1 btn-view" data-id="${r.id}" title="View">
                        <i class="ri-eye-line"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning p-1 btn-edit-remarks" data-id="${r.id}" title="Edit Remarks">
                        <i class="ri-edit-line"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger p-1 btn-delete" data-id="${r.id}" title="Delete">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>` },
        ],
        order: [[4, 'desc']],
        pageLength: 25,
        responsive: true,
        language: { emptyTable: 'No bills found.' },
    });

    // ── Filter ─────────────────────────────────────────────────────────────
    $('#btnApplyFilter').on('click', () => table.ajax.reload());
    $('#btnResetFilter').on('click', () => {
        $('#filterEmployee').val(null).trigger('change');
        $('#filterCategory, #filterDateFrom, #filterDateTo').val('');
        table.ajax.reload();
    });

    // ── Select2 — filter employee ──────────────────────────────────────────
    initEmpSelect2($('#filterEmployee'), true);

    // ── Reload category options when upload modal opens ────────────────────
    $('#btnOpenUpload').on('click', () => {
        resetUploadForm();
        loadCategoryOptions('billCategory');
        $('#uploadModal').modal('show');
    });

    // ── Quick-add category inline ──────────────────────────────────────────
    $('#btnQuickAddCategory').on('click', function () {
        $('#quickCatForm').toggleClass('d-none');
        if (!$('#quickCatForm').hasClass('d-none')) {
            $('#quickCatName').val('').focus();
        }
    });

    $('#btnQuickCatCancel').on('click', function () {
        $('#quickCatForm').addClass('d-none');
        $('#quickCatName').val('');
    });

    $('#btnQuickCatSave').on('click', function () {
        const name = $('#quickCatName').val().trim();
        if (!name) {
            $.toast({ heading: 'Validation', text: 'Category name is required.', icon: 'warning', position: 'top-right' });
            return;
        }
        const btn = $(this).prop('disabled', true);
        $.ajax({
            url     : CAT_STORE,
            method  : 'POST',
            headers : { 'X-CSRF-TOKEN': CSRF },
            data    : { name: name, is_active: 1 },
            success : function (res) {
                if (res.success) {
                    $.toast({ heading: 'Created', text: 'Category "' + name + '" added.', icon: 'success', position: 'top-right' });
                    // Reload categories and auto-select the new one
                    loadCategoryOptions('billCategory', name);
                    loadCategoryOptions('filterCategory');
                    $('#quickCatForm').addClass('d-none');
                    $('#quickCatName').val('');
                } else {
                    $.toast({ heading: 'Error', text: res.message || 'Failed.', icon: 'error', position: 'top-right' });
                }
            },
            error   : function (xhr) {
                const errs = xhr.responseJSON?.errors;
                const msg  = errs ? Object.values(errs).flat().join(' ') : (xhr.responseJSON?.message || 'Error.');
                $.toast({ heading: 'Error', text: msg, icon: 'error', position: 'top-right' });
            },
            complete: function () { btn.prop('disabled', false); },
        });
    });

    // ── File dropzone drag/drop ────────────────────────────────────────────
    const dz = document.getElementById('uploadDropzone');
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('drag-over');
        const f = e.dataTransfer.files[0];
        if (f) setFile(f);
    });

    $('#billFile').on('change', function () {
        if (this.files[0]) setFile(this.files[0]);
    });

    function setFile(f) {
        const input = document.getElementById('billFile');
        const dt = new DataTransfer();
        dt.items.add(f);
        input.files = dt.files;
        $('#fileNameDisplay').text('Selected: ' + f.name);
        const ext = f.name.split('.').pop().toLowerCase();
        const wrap = $('#filePreviewWrap').removeClass('d-none').html('');
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            const url = URL.createObjectURL(f);
            wrap.html(`<img alt="Bill or document image" src="${url}" style="max-height:150px;border-radius:6px;border:1px solid #dee2e6;">`);
        } else if (ext === 'pdf') {
            wrap.html(`<span class="badge bg-danger-subtle text-danger fs-12"><i class="ri-file-pdf-line"></i> PDF selected: ${f.name}</span>`);
        }
    }

    // ── Upload form submit ─────────────────────────────────────────────────
    $('#uploadForm').on('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const btn = $('#btnUploadSubmit').prop('disabled', true).text('Uploading…');

        $.ajax({
            url      : BASE,
            method   : 'POST',
            data     : fd,
            processData: false,
            contentType: false,
            headers  : { 'X-CSRF-TOKEN': CSRF },
            success  : res => {
                if (res.success) {
                    $.toast({ heading: 'Success', text: res.message, icon: 'success', position: 'top-right' });
                    $('#uploadModal').modal('hide');
                    table.ajax.reload();
                } else {
                    $.toast({ heading: 'Error', text: res.message || 'Upload failed.', icon: 'error', position: 'top-right' });
                }
            },
            error    : xhr => {
                const errs = xhr.responseJSON?.errors;
                const msg  = errs ? Object.values(errs).flat().join(' | ') : 'Something went wrong.';
                $.toast({ heading: 'Validation Error', text: msg, icon: 'error', position: 'top-right' });
            },
            complete : () => btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i> Upload Bill'),
        });
    });

    // ── Select2 — upload modal employee ───────────────────────────────────
    initEmpSelect2($('#modalEmployee'), false);
    $('#modalEmployee').on('select2:select', function (e) {
        const d = e.params.data;
        $('#hidEmpCode').val(d.emp_code || '');
        $('#hidEmpName').val(d.emp_name || '');
    });

    function initEmpSelect2(el, allowClear) {
        el.select2({
            placeholder      : 'Search employee…',
            allowClear       : allowClear,
            minimumInputLength: 1,
            dropdownParent   : el.closest('.modal').length ? el.closest('.modal') : document.body,
            ajax: {
                url          : EMP_SEARCH,
                dataType     : 'json',
                delay        : 300,
                data         : params => ({ q: params.term }),
                processResults: data => ({ results: data.results }),
                cache        : true,
            },
        });
    }

    // ── Shared function: open view modal, optionally jump to edit remarks ─
    function openViewModal(id, autoEditRemarks) {
        $('#viewModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>');
        $('#viewModal').modal('show');
        $.get(BASE + '/' + id, res => {
            if (!res.success) return;
            const b = res.bill;

            const imageExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
            const ext = (b.file_type || '').toLowerCase();
            let preview = '';

            if (imageExts.includes(ext)) {
                preview = `
                    <a href="${b.file_url}" target="_blank" title="Open full size">
                        <img alt="Bill or document image" src="${b.file_url}" class="img-fluid rounded shadow-sm"
                             style="width:100%;max-height:500px;object-fit:contain;cursor:zoom-in;border:1px solid #dee2e6;">
                    </a>
                    <div class="mt-1 text-center"><small class="text-muted"><i class="ri-zoom-in-line me-1"></i>Click to open full size</small></div>`;
            } else if (ext === 'pdf') {
                preview = `
                    <div style="height:500px;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;">
                        <iframe src="${b.file_url}" width="100%" height="100%" style="border:none;" title="${b.file_name}">
                            <p>PDF cannot be displayed inline. <a href="${b.file_url}" target="_blank">Open PDF</a></p>
                        </iframe>
                    </div>`;
            } else {
                const iconMap = {'doc':'ri-file-word-line','docx':'ri-file-word-line','xls':'ri-file-excel-line','xlsx':'ri-file-excel-line','txt':'ri-file-text-line','zip':'ri-file-zip-line'};
                const icon = iconMap[ext] || 'ri-file-line';
                preview = `
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5">
                        <i class="${icon}" style="font-size:72px;color:#405189;"></i>
                        <p class="mt-2 mb-3 fw-semibold text-center">${b.file_name}</p>
                        <a href="${b.file_url}" target="_blank" class="btn btn-sm btn-primary">
                            <i class="ri-download-line me-1"></i> Download File
                        </a>
                    </div>`;
            }

            $('#viewDownloadBtn').attr('href', b.file_url);
            $('#viewModal').data('bill-id', b.id);  // store for inline remarks save
            $('#viewModalBody').html(`
                <div class="row g-0" style="min-height:500px;">

                    <div class="col-md-7" style="background:#f8f9fa;border-right:1px solid #dee2e6;border-radius:6px 0 0 6px;padding:16px;display:flex;flex-direction:column;justify-content:center;">
                        ${preview}
                    </div>

                    <div class="col-md-5" style="padding:20px;">
                        <h6 class="fw-bold mb-3" style="color:#1e3a5f;border-bottom:2px solid #405189;padding-bottom:6px;">
                            <i class="ri-file-info-line me-1 text-primary"></i> Bill Details
                        </h6>

                        <div class="mb-3">
                            <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">Employee</div>
                            <div class="fw-semibold" style="font-size:13px;">${b.emp_code} — ${b.emp_name}</div>
                        </div>

                        <div class="mb-3">
                            <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">Bill Title</div>
                            <div style="font-size:13px;">${b.bill_title}</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">Category</div>
                                <div style="font-size:13px;">${b.bill_category ? '<span class="badge bg-info-subtle text-info">' + b.bill_category + '</span>' : '—'}</div>
                            </div>
                            <div class="col-6">
                                <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">Bill Date</div>
                                <div style="font-size:13px;">${b.bill_date || '—'}</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">Amount</div>
                            <div class="fw-bold" style="font-size:18px;color:#1e3a5f;">${b.bill_amount ? '₹' + parseFloat(b.bill_amount).toLocaleString('en-IN', {minimumFractionDigits:2}) : '—'}</div>
                        </div>

                        <div class="mb-3">
                            <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">File</div>
                            <div style="font-size:12px;color:#555;word-break:break-all;">
                                <i class="ri-file-line me-1"></i>${b.file_name}
                            </div>
                        </div>

                        <div class="mb-3" id="remarksSection">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div style="font-size:10px;text-transform:uppercase;color:#888;font-weight:600;letter-spacing:.5px;">Remarks</div>
                                <button type="button" class="btn btn-link btn-sm p-0 text-primary" id="btnInlineEditRemarks" title="Edit remarks" style="font-size:11px;">
                                    <i class="ri-edit-line me-1"></i>Edit
                                </button>
                            </div>
                            {{-- Read mode --}}
                            <div id="remarksReadView" style="font-size:12px;background:#fff8e1;border-left:3px solid #f7b84b;padding:6px 10px;border-radius:4px;min-height:32px;">
                                ${b.remarks || '<span class="text-muted fst-italic">No remarks</span>'}
                            </div>
                            {{-- Edit mode (hidden by default) --}}
                            <div id="remarksEditView" class="d-none mt-1">
                                <textarea id="inlineRemarksText" class="form-control form-control-sm" rows="3"
                                    maxlength="1000" placeholder="Enter remarks…" aria-label="Enter remarks…">${b.remarks || ''}</textarea>
                                <div class="d-flex gap-1 mt-1">
                                    <button type="button" class="btn btn-sm btn-success flex-fill" id="btnInlineRemarksSave">
                                        <i class="ri-check-line me-1"></i>Save
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" id="btnInlineRemarksCancel">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto pt-2" style="border-top:1px solid #f0f0f0;">
                            <div style="font-size:10px;color:#aaa;"><i class="ri-time-line me-1"></i>Uploaded: ${b.created_at}</div>
                        </div>
                    </div>

                </div>
            `);

            // If opened via "Edit Remarks" button, auto-switch to edit mode
            if (autoEditRemarks) {
                $('#remarksReadView').addClass('d-none');
                $('#remarksEditView').removeClass('d-none');
                $('#btnInlineEditRemarks').addClass('d-none');
                $('#inlineRemarksText').focus();
            }
        });
    }

    // ── View bill (eye button / thumbnail) ────────────────────────────────
    $(document).on('click', '.btn-view, .preview-thumb', function (e) {
        const id = $(this).data('id');
        if (!id) return;
        e.stopPropagation();
        openViewModal(id, false);
    });

    // ── Edit remarks button — opens view modal straight into edit mode ────
    $(document).on('click', '.btn-edit-remarks', function (e) {
        const id = $(this).data('id');
        if (!id) return;
        e.stopPropagation();
        openViewModal(id, true);
    });

    // ── Inline remarks edit inside view modal ────────────────────────────
    $(document).on('click', '#btnInlineEditRemarks', function () {
        $('#remarksReadView').addClass('d-none');
        $('#remarksEditView').removeClass('d-none');
        $('#inlineRemarksText').focus();
        $(this).addClass('d-none');
    });

    $(document).on('click', '#btnInlineRemarksCancel', function () {
        $('#remarksEditView').addClass('d-none');
        $('#remarksReadView').removeClass('d-none');
        $('#btnInlineEditRemarks').removeClass('d-none');
    });

    $(document).on('click', '#btnInlineRemarksSave', function () {
        const id      = $('#viewModal').data('bill-id');
        const remarks = $('#inlineRemarksText').val();
        const btn     = $(this).prop('disabled', true).html('<i class="ri-loader-line me-1"></i>Saving…');

        $.ajax({
            url     : BASE + '/' + id + '/remarks',
            method  : 'POST',
            headers : { 'X-CSRF-TOKEN': CSRF },
            data    : { remarks },
            success : res => {
                if (res.success) {
                    // Update the read view text and switch back to read mode
                    $('#remarksReadView').html(remarks ? $('<div>').text(String(remarks)).html() : '<span class="text-muted fst-italic">No remarks</span>'); // S-09
                    $('#remarksEditView').addClass('d-none');
                    $('#remarksReadView').removeClass('d-none');
                    $('#btnInlineEditRemarks').removeClass('d-none');
                    $.toast({ heading: 'Saved', text: res.message, icon: 'success', position: 'top-right' });
                    table.ajax.reload(null, false);
                }
            },
            error: () => $.toast({ heading: 'Error', text: 'Could not save remarks.', icon: 'error', position: 'top-right' }),
            complete: () => btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i>Save'),
        });
    });

    // ── Delete ────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Delete this bill? This action cannot be undone.')) return;
        $.ajax({
            url     : BASE + '/' + id,
            method  : 'DELETE',
            headers : { 'X-CSRF-TOKEN': CSRF },
            success : res => {
                if (res.success) {
                    $.toast({ heading: 'Deleted', text: res.message, icon: 'success', position: 'top-right' });
                    table.ajax.reload(null, false);
                }
            },
        });
    });

    // ── Helpers ───────────────────────────────────────────────────────────
    function resetUploadForm() {
        $('#uploadForm')[0].reset();
        $('#modalEmployee').val(null).trigger('change');
        $('#hidEmpCode, #hidEmpName').val('');
        $('#filePreviewWrap').addClass('d-none').html('');
        $('#fileNameDisplay').text('');
    }
});
</script>
@endpush
