@extends('layouts.app')
@section('content')
{{-- UAT-03: view was missing (SystemConfigurationController@index returned 500). --}}
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex" style="justify-content: space-between;">
                        <h4 class="card-title mb-0">System Configurations</h4>
                        <div style="display: flex;gap:8px;align-items: baseline;">
                            <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <span class="fw-semibold fs-12">Status: </span>
                                    <span class="text-muted">{{ $status === 'active' ? 'Active' : ($status === 'inactive' ? 'Inactive' : 'All') }} <i class="mdi mdi-chevron-down"></i></span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=all">All</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=active">Active</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=inactive">Inactive</a>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="addSysCfgBtn">
                                <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add New
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="sysCfgTable" class="table nowrap dt-responsive align-middle table-hover table-bordered" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th>S No.</th>
                                    <th style="text-align:left">Key</th>
                                    <th style="text-align:left">Value</th>
                                    <th>Type</th>
                                    <th style="text-align:left">Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($configurations as $key => $cfg)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td style="text-align:left">{{ $cfg->config_key }}</td>
                                    <td style="text-align:left">{{ $cfg->config_value }}</td>
                                    <td>{{ $cfg->config_type }}</td>
                                    <td style="text-align:left">{{ $cfg->description }}</td>
                                    <td>
                                        @if ($cfg->is_active)
                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                        @else
                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm edit-sys-cfg" data-id="{{ $cfg->id }}"><i class="ri-edit-2-fill"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sysCfgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 overflow-hidden">
            <div class="modal-header p-3">
                <h4 class="card-title mb-0" id="sysCfgLabel">System Configuration</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sysCfgForm">
                    <input type="hidden" id="cfg_id">
                    <div class="mb-3">
                        <label class="form-label" for="config_key">Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="config_key" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="config_value">Value <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="config_value" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="config_type">Type</label>
                        <select class="form-select" id="config_type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="boolean">boolean</option>
                            <option value="url">url</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" rows="2"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="cfg_is_active" checked>
                        <label class="form-check-label" for="cfg_is_active">Active</label>
                    </div>
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-primary rounded-pill" id="saveSysCfgBtn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var base = @json(url('system-configurations'));
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var modal = new bootstrap.Modal(document.getElementById('sysCfgModal'));
    $('#sysCfgTable').DataTable({ pageLength: 25, order: [[1, 'asc']] });
    function msg(ok, text) {
        if (typeof showAlert === 'function') { showAlert(ok ? 'success' : 'danger', ok ? 'ri-checkbox-circle-line' : 'ri-error-warning-line', text); }
        else { console.log(text); }
    }
    $('#addSysCfgBtn').on('click', function () {
        $('#sysCfgForm')[0].reset(); $('#cfg_id').val(''); $('#config_type').val('string'); $('#cfg_is_active').prop('checked', true); modal.show();
    });
    $(document).on('click', '.edit-sys-cfg', function () {
        var id = $(this).data('id');
        $.getJSON(base + '/' + encodeURIComponent(id) + '/edit', function (r) {
            var d = r.data || {};
            $('#cfg_id').val(d.id); $('#config_key').val(d.config_key); $('#config_value').val(d.config_value);
            $('#config_type').val(d.config_type || 'string'); $('#description').val(d.description || '');
            $('#cfg_is_active').prop('checked', !!Number(d.is_active)); modal.show();
        });
    });
    $('#saveSysCfgBtn').on('click', function () {
        var id = $('#cfg_id').val();
        $.ajax({
            url: id ? base + '/' + encodeURIComponent(id) : base,
            type: id ? 'PUT' : 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            dataType: 'json',
            data: {
                config_key: $('#config_key').val().trim(), config_value: $('#config_value').val().trim(),
                config_type: $('#config_type').val(), description: $('#description').val(),
                is_active: $('#cfg_is_active').is(':checked') ? 1 : 0
            },
            success: function (r) { msg(true, r.message || 'Saved'); setTimeout(function () { location.reload(); }, 1200); },
            error: function (x) {
                var e = (x.responseJSON && (x.responseJSON.message || JSON.stringify(x.responseJSON.errors || {}))) || 'Save failed';
                msg(false, e);
            }
        });
    });
});
</script>
@endpush
