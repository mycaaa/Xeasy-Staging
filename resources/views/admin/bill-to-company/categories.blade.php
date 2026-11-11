@extends('layouts.app')

@section('title', 'Bill to Company — Categories')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page header --}}
        <div class="row mb-3">
            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#1e3a5f;">
                        <i class="ri-folder-settings-line me-1 text-primary"></i> Bill to Company — Categories
                    </h5>
                    <p class="text-muted mb-0" style="font-size:12px;">Manage bill categories used across the Bill to Company module</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ url('bill-to-company') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-arrow-left-line me-1"></i> Back
                    </a>
                    <button class="btn btn-sm btn-primary" id="btnAddCategory">
                        <i class="ri-add-line me-1"></i> + Add Category
                    </button>
                </div>
            </div>
        </div>

        {{-- Table card --}}
        <div class="card">
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table id="categoriesTable" class="table table-hover table-bordered align-middle" style="width:100%;font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-center" style="width:100px;">Bills</th>
                                <th class="text-center" style="width:100px;">Status</th>
                                <th class="text-center" style="width:130px;">Created</th>
                                <th class="text-center" style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesBody">
                            <tr><td colspan="7" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary"></div> Loading…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     ADD / EDIT MODAL (single modal reused for both)
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header" style="background:#405189;">
                <h5 class="modal-title text-white" id="categoryModalLabel">
                    <i class="ri-folder-add-line me-1"></i> <span id="modalTitleText">Add Category</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="categoryForm" novalidate>
                @csrf
                <input type="hidden" id="categoryId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="catName">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="catName" name="name"
                            maxlength="100" placeholder="e.g. Travel" required>
                        <div class="invalid-feedback" id="catNameError"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="catDescription">Description</label>
                        <input type="text" class="form-control" id="catDescription" name="description"
                            maxlength="255" placeholder="Optional description…">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="catIsActive" name="is_active" checked>
                        <label class="form-check-label" for="catIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnCategorySave" class="btn btn-primary btn-sm">
                        <i class="ri-save-line me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const CSRF     = $('meta[name="csrf-token"]').attr('content');
    const BASE_CAT = '{{ url("bill-to-company/categories") }}';

    // ── Load table ─────────────────────────────────────────────────────────
    function loadCategories() {
        $.get(BASE_CAT + '/data', function (res) {
            if (!res.success) return;
            const rows = res.data;
            if (!rows.length) {
                $('#categoriesBody').html('<tr><td colspan="7" class="text-center py-4 text-muted">No categories found.</td></tr>');
                return;
            }
            let html = '';
            rows.forEach(function (cat, i) {
                const badge = cat.is_active
                    ? '<span class="badge bg-success-subtle text-success">Active</span>'
                    : '<span class="badge bg-danger-subtle text-danger">Inactive</span>';
                const toggleLabel = cat.is_active ? 'Deactivate' : 'Activate';
                const toggleIcon  = cat.is_active ? 'ri-toggle-fill text-success' : 'ri-toggle-line text-secondary';

                html += `<tr>
                    <td class="text-center text-muted">${i + 1}</td>
                    <td class="fw-semibold">${cat.name}</td>
                    <td>${cat.description || '<span class="text-muted">—</span>'}</td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border">${cat.bills_count}</span>
                    </td>
                    <td class="text-center">${badge}</td>
                    <td class="text-center text-muted" style="font-size:11px;">${cat.created_at || '—'}</td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-primary p-1 btn-edit"
                                data-id="${cat.id}"
                                data-name="${(cat.name || '').replace(/"/g, '&quot;')}"
                                data-description="${(cat.description || '').replace(/"/g, '&quot;')}"
                                data-is-active="${cat.is_active ? 1 : 0}"
                                title="Edit">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary p-1 btn-toggle"
                                data-id="${cat.id}" title="${toggleLabel}">
                                <i class="${toggleIcon}"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger p-1 btn-delete"
                                data-id="${cat.id}" data-name="${(cat.name || '').replace(/"/g, '&quot;')}" title="Delete">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            $('#categoriesBody').html(html);
        });
    }

    loadCategories();

    // ── Open Add modal ─────────────────────────────────────────────────────
    $('#btnAddCategory').on('click', function () {
        $('#categoryId').val('');
        $('#modalTitleText').text('Add Category');
        $('#categoryForm')[0].reset();
        $('#catIsActive').prop('checked', true);
        $('#catName').removeClass('is-invalid');
        $('#categoryModal').modal('show');
    });

    // ── Open Edit modal ────────────────────────────────────────────────────
    $(document).on('click', '.btn-edit', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        const desc = $(this).data('description');
        const act  = $(this).data('is-active');

        $('#categoryId').val(id);
        $('#modalTitleText').text('Edit Category');
        $('#catName').val(name).removeClass('is-invalid');
        $('#catDescription').val(desc);
        $('#catIsActive').prop('checked', act == 1);
        $('#categoryModal').modal('show');
    });

    // ── Save (add / update) ────────────────────────────────────────────────
    $('#categoryForm').on('submit', function (e) {
        e.preventDefault();
        const id   = $('#categoryId').val();
        const name = $('#catName').val().trim();

        if (!name) {
            $('#catName').addClass('is-invalid');
            $('#catNameError').text('Name is required.');
            return;
        }
        $('#catName').removeClass('is-invalid');

        const data = {
            name:        name,
            description: $('#catDescription').val().trim(),
            is_active:   $('#catIsActive').is(':checked') ? 1 : 0,
        };

        const isEdit  = !!id;
        const url     = isEdit ? (BASE_CAT + '/' + id) : BASE_CAT;
        const method  = isEdit ? 'PUT' : 'POST';

        const btn = $('#btnCategorySave').prop('disabled', true).text('Saving…');

        $.ajax({
            url      : url,
            method   : method,
            headers  : { 'X-CSRF-TOKEN': CSRF },
            data     : data,
            success  : function (res) {
                if (res.success) {
                    $.toast({ heading: 'Success', text: res.message, icon: 'success', position: 'top-right' });
                    $('#categoryModal').modal('hide');
                    loadCategories();
                } else {
                    $.toast({ heading: 'Error', text: res.message || 'Save failed.', icon: 'error', position: 'top-right' });
                }
            },
            error    : function (xhr) {
                const errs = xhr.responseJSON?.errors;
                if (errs?.name) {
                    $('#catName').addClass('is-invalid');
                    $('#catNameError').text(errs.name[0]);
                } else {
                    const msg = xhr.responseJSON?.message || 'Something went wrong.';
                    $.toast({ heading: 'Error', text: msg, icon: 'error', position: 'top-right' });
                }
            },
            complete : function () {
                btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i> Save');
            },
        });
    });

    // ── Toggle ─────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        $.ajax({
            url     : BASE_CAT + '/toggle/' + id,
            method  : 'POST',
            headers : { 'X-CSRF-TOKEN': CSRF },
            success : function (res) {
                if (res.success) {
                    $.toast({ heading: 'Updated', text: res.message, icon: 'success', position: 'top-right' });
                    loadCategories();
                }
            },
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-delete', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        if (!confirm(`Delete category "${name}"? This cannot be undone.`)) return;
        $.ajax({
            url     : BASE_CAT + '/' + id,
            method  : 'DELETE',
            headers : { 'X-CSRF-TOKEN': CSRF },
            success : function (res) {
                if (res.success) {
                    $.toast({ heading: 'Deleted', text: res.message, icon: 'success', position: 'top-right' });
                    loadCategories();
                } else {
                    $.toast({ heading: 'Cannot Delete', text: res.message, icon: 'warning', position: 'top-right' });
                }
            },
            error   : function (xhr) {
                const msg = xhr.responseJSON?.message || 'Delete failed.';
                $.toast({ heading: 'Error', text: msg, icon: 'error', position: 'top-right' });
            },
        });
    });
});
</script>
@endpush
