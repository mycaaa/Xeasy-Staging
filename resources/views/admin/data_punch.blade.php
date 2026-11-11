@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex" id="dataPunchSection">
                            <h4 class="card-title mb-0 flex-grow-1">Data Punched</h4>
                            <div class="status-buttons">
                                <a href="{{ url('data-punch/uploaded') }}"
                                    class="status-btn uploaded {{ $status === 'uploaded' ? 'active' : '' }}">
                                    <i class="ri-upload-cloud-2-line"></i>
                                    <span>Uploaded</span>
                                    <span>({{ $uploadedCount }})</span>
                                </a>
                                <a href="{{ url('data-punch/filled') }}"
                                    class="status-btn filled {{ $status === 'filled' ? 'active' : '' }}">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span>Filled</span>
                                    <span>({{ $filledCount }})</span>
                                </a>
                                <a href="{{ url('data-punch/denied') }}"
                                    class="status-btn denied {{ $status === 'denied' ? 'active' : '' }}">
                                    <i class="ri-close-circle-line"></i>
                                    <span>Denied</span>
                                    <span>({{ $deniedCount }})</span>
                                </a>
                                <a href="{{ url('data-punch/draft') }}"
                                    class="status-btn draft {{ $status === 'draft' ? 'active' : '' }}">
                                    <i class="ri-edit-circle-line"></i>
                                    <span>Draft</span>
                                    <span>({{ $draftCount }})</span>
                                </a>
                                <a href="{{ url('data-punch/hold') }}"
                                    class="status-btn hold {{ $status === 'hold' ? 'active' : '' }}">
                                    <i class="ri-pause-circle-line"></i>
                                    <span>Hold</span>
                                    <span>({{ $holdCount }})</span>
                                </a>
                                @if ($isAdminView)
                                <a href="{{ url('data-punch/deactivated') }}"
                                    class="status-btn deactivated {{ $status === 'deactivated' ? 'active' : '' }}">
                                    <i class="ri-forbid-line"></i>
                                    <span>Deactivated</span>
                                    <span id="deactivatedTabCount">({{ $deactivatedCount }})</span>
                                </a>
                                @endif
                            </div>
                            <div class="dropdowns">
                                @if ($status !== 'deactivated')
                                {{-- ODO-27: also list claims already punched (Filled), of any month --}}
                                <div class="form-check form-switch d-flex align-items-center me-2 mb-0" title="Also list claims that are already punched (Filled), of any month">
                                    <input class="form-check-input" type="checkbox" id="showPunched" {{ request()->boolean('show_punched') ? 'checked' : '' }}>
                                    <label class="form-check-label small ms-1" for="showPunched">Show punched</label>
                                </div>
                                @endif
                                <select class="select2" id="selDate" name="date" style="width: 130px"
                                    data-placeholder="Select Date">
                                    <option value=""></option>
                                    @foreach ($availableDates as $date)
                                        <option value="{{ $date }}">
                                            {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                <select class="select2" id="selEmployee" name="emp" style="width: 250px"
                                    data-placeholder="Select Employee">
                                    <option value=""></option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->EmployeeID }}">
                                            {{ $employee->EmpCode }} - {{ $employee->EmpName }}
                                        </option>
                                    @endforeach
                                </select>
                                <button
                                    class="btn btn-primary btn-sm d-flex align-items-center justify-content-center"
                                    style="width: 32px; height: 32px;" type="button" data-bs-toggle="offcanvas"
                                    data-bs-target="#filterOffcanvas">
                                    <i class="ri-filter-3-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body pb-3 pt-2">
                            @if ($canBulkChangeHead ?? false)
                                {{-- Tracker #28: bulk claim head change --}}
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2" id="bulkHeadBar">
                                    <span class="text-muted small"><span id="bulkSelectedCount">0</span> selected</span>
                                    <select class="form-select form-select-sm" id="bulkNewHead" style="width: 240px;">
                                        <option value="">Change head to...</option>
                                        @foreach ($bulkHeadTypes as $ct)
                                            <option value="{{ $ct->ClaimId }}">{{ $ct->ClaimName }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" class="form-control form-control-sm" id="bulkHeadRemark" maxlength="500" placeholder="Reason (optional)" aria-label="Reason (optional)" style="width: 240px;">
                                    <button type="button" class="btn btn-sm btn-warning" id="bulkChangeHeadBtn" disabled>
                                        <i class="ri-exchange-line me-1"></i>Change head of selected
                                    </button>
                                </div>
                            @endif
                            <table style="margin-top: 15px" id="claimReportTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        @if ($canBulkChangeHead ?? false)
                                            <th scope="col" style="width:3%; text-align:center;"><input type="checkbox" class="form-check-input" id="bulkSelectAll" title="Select all on this page"></th>
                                        @endif
                                        <th scope="col" style="width:5%; text-align:center;">Sn</th>
                                        <th scope="col" style="width:5%; text-align:center;">Exp Id</th>
                                        <th scope="col" style="width:20%; text-align:center;">Employee Name</th>
                                        <th scope="col" style="width:8%; text-align:center;">Bill Date</th>
                                        <th scope="col" style="width:10%; text-align:center;">Claim Type</th>
                                        @if ($status === 'hold')
                                            <th scope="col" style="width:8%; text-align:center;">Filled Amount</th>
                                        @endif
                                        <th scope="col" style="width:44%; text-align:center;">Claimant Remark</th>
                                        <th scope="col" style="width:10%; text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.view_claim_detail />

    {{-- Deactivate Claim Modal --}}
    <div class="modal fade" id="deactivateClaimModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="deactivateClaimModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deactivateClaimModalLabel">
                        <i class="ri-forbid-line me-1"></i> Deactivate Claim
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2">You are about to <strong class="text-danger">deactivate</strong> Exp ID: <strong id="deactivateExpIdLabel">-</strong>. This action cannot be undone.</p>
                    <div class="mb-3">
                        <label for="deactivateReason" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea id="deactivateReason" class="form-control" rows="3" placeholder="Enter reason for deactivation..." maxlength="500"></textarea>
                        <div class="invalid-feedback">Please enter a reason for deactivation.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeactivateClaimBtn">
                        <i class="ri-forbid-line me-1"></i>Deactivate
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Revert Deactivated Claim Confirm Modal --}}
    <div class="modal fade" id="revertDeactivatedModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="revertDeactivatedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="revertDeactivatedModalLabel">
                        <i class="ri-arrow-go-back-line me-1"></i> Revert Deactivated Claim
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to revert Exp ID <strong id="revertDeactivatedExpIdLabel">-</strong> back to <strong class="text-success">Uploaded</strong> status?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="confirmRevertDeactivatedBtn">
                        <i class="ri-arrow-go-back-line me-1"></i>Yes, Revert
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Offcanvas --}}
    <div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title text-white" id="filterOffcanvasLabel">Additional Filters</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Claim Type</label>
                <select class="form-select select2-offcanvas" id="selClaimType" multiple data-placeholder="All Claim Types">
                    <option value=""></option>
                    @foreach ($claimTypes as $ct)
                        <option value="{{ $ct->ClaimId }}">{{ $ct->ClaimName }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="offcanvas-footer border-top p-3 d-flex gap-2">
            <button class="btn btn-primary w-50" id="applyFilters"><i class="ri-check-line me-1"></i>Apply</button>
            <button class="btn btn-outline-secondary w-50" id="clearFilters"><i
                    class="ri-refresh-line me-1"></i>Clear</button>
        </div>
    </div>
@endsection
@push('styles')
    <style>
        #claimReportTable thead th {
            text-align: center !important;
        }
    </style>
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/pax_details_editor.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                allowClear: true,
                placeholder: function() {
                    return $(this).data('placeholder');
                }
            });
            const canBulkChangeHead = @json((bool) ($canBulkChangeHead ?? false));
            const bulkSelected = new Set();
            const punchColumns = [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'ExpId',
                        name: 'ec.ExpId',
                        className: 'text-center'
                    },
                    {
                        data: 'employee',
                        name: 'employee',
                        orderable: true,
                        searchable: true,
                        className: 'text-start'
                    },
                    {
                        data: 'BillDate',
                        name: 'ec.BillDate',
                        className: 'text-center'
                    },
                    {
                        data: 'claim_name',
                        name: 'ct.ClaimName',
                        className: 'text-center'
                    },
                    @if ($status === 'hold')
                    {
                        // HOLD-01: filled (punched) amount on the Hold tab
                        data: 'FilledTAmt',
                        name: 'ec.FilledTAmt',
                        className: 'text-end',
                        render: function(data, type, row) {
                            const v = Number(data || row.Amount || 0);
                            return v > 0 ? '₹' + v.toLocaleString('en-IN') : '<span class="text-muted">Not punched yet</span>';
                        }
                    },
                    @endif
                    {
                        data: 'DateEntryRemark',
                        name: 'ec.DateEntryRemark',
                        render: function(data, type, row) {
                            return '<textarea rows="1" readonly class="form-control form-control-sm">' +
                                (data || '') + '</textarea>';
                        }
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }
            ];
            if (canBulkChangeHead) {
                punchColumns.unshift({
                    data: 'ExpId',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return '<input type="checkbox" class="form-check-input bulk-row-check" value="' + data + '"' + (bulkSelected.has(String(data)) ? ' checked' : '') + '>';
                    }
                });
            }
            var table = $("#claimReportTable").DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('data.punch.data', ['status' => $status]) }}",
                    type: 'POST',
                    data: function(d) {
                        d._token = "{{ csrf_token() }}";
                        d.date = $('#selDate').val();
                        d.emp = $('#selEmployee').val();
                        d.show_punched = $('#showPunched').is(':checked') ? 1 : 0;
                        d.claim_type = $('#selClaimType').val() || [];
                    }
                },
                columns: punchColumns,
                createdRow: function(row, data, dataIndex) {
                    $(row).attr('data-row-id', data.ExpId);
                },

                ordering: true,
                searching: true,
                paging: true,
                info: true,
                lengthChange: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
            });

            // Tracker #28: bulk claim head change
            function refreshBulkBar() {
                $('#bulkSelectedCount').text(bulkSelected.size);
                $('#bulkChangeHeadBtn').prop('disabled', bulkSelected.size === 0 || !$('#bulkNewHead').val());
            }
            $(document).on('change', '.bulk-row-check', function() {
                const id = String($(this).val());
                this.checked ? bulkSelected.add(id) : bulkSelected.delete(id);
                refreshBulkBar();
            });
            $(document).on('change', '#bulkSelectAll', function() {
                const checked = this.checked;
                $('#claimReportTable .bulk-row-check').each(function() {
                    this.checked = checked;
                    const id = String($(this).val());
                    checked ? bulkSelected.add(id) : bulkSelected.delete(id);
                });
                refreshBulkBar();
            });
            $('#claimReportTable').on('draw.dt', function() {
                $('#bulkSelectAll').prop('checked', false);
            });
            $(document).on('change', '#bulkNewHead', refreshBulkBar);
            $(document).on('click', '#bulkChangeHeadBtn', function() {
                const newHead = $('#bulkNewHead').val();
                const headName = $('#bulkNewHead option:selected').text();
                if (!newHead || bulkSelected.size === 0) return;
                if (!confirm(`Change the head of ${bulkSelected.size} claim(s) to "${headName}"?`)) return;
                const $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: "{{ ($canBulkChangeHead ?? false) ? route('data.punch.bulk.change.head') : '' }}",
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        expids: Array.from(bulkSelected),
                        new_claim_id: newHead,
                        remark: $('#bulkHeadRemark').val()
                    },
                    success: function(res) {
                        alert(res.message || 'Claim head changed.');
                        bulkSelected.clear();
                        $('#bulkHeadRemark').val('');
                        refreshBulkBar();
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || (xhr.status === 403 ? 'You are not allowed to change claim heads in bulk.' : 'Bulk head change failed.'));
                        refreshBulkBar();
                    }
                });
            });


            function updateFilterOptions() {
                const date = $('#selDate').val();
                const emp = $('#selEmployee').val();
                $.ajax({
                    url: "{{ route('data.punch.filter.options', ['status' => $status]) }}",
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        date: date,
                        emp: emp,
                        show_punched: $('#showPunched').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        const currentDate = $('#selDate').val();
                        $('#selDate').empty().append('<option value="">Select Date</option>');
                        response.dates.forEach(function(item) {
                            const selected = currentDate == item.value ? 'selected' : '';
                            $('#selDate').append(
                                `<option value="${item.value}" ${selected}>${item.text}</option>`
                            );
                        });
                        const currentEmp = $('#selEmployee').val();
                        $('#selEmployee').empty().append('<option value="">Select Employee</option>');
                        response.employees.forEach(function(item) {
                            const selected = currentEmp == item.value ? 'selected' : '';
                            $('#selEmployee').append(
                                `<option value="${item.value}" ${selected}>${item.text}</option>`
                            );
                        });
                        $('#selDate, #selEmployee').trigger('change.select2');
                    }
                });
            }
            $('#selDate').on('change', function() {
                updateFilterOptions();
                table.ajax.reload();
            });
            $('#selEmployee').on('change', function() {
                updateFilterOptions();
                table.ajax.reload();
            });
            $('#showPunched').on('change', function() {
                updateFilterOptions();
                table.ajax.reload();
            });

            // Offcanvas select2
            $('#selClaimType').select2({
                allowClear: true,
                placeholder: 'All Claim Types',
                dropdownParent: $('#filterOffcanvas')
            });

            $('#applyFilters').on('click', function() {
                table.ajax.reload();
                bootstrap.Offcanvas.getInstance(document.getElementById('filterOffcanvas'))?.hide();
            });

            $('#clearFilters').on('click', function() {
                $('#selClaimType').val(null).trigger('change');
                table.ajax.reload();
            });
        });
    </script>
    <script src="{{ asset('custom/js/pages/claim.js') }}?v={{ time() }}"></script>
    <script>
        (function () {
            var deactivateExpId = null;
            var deactivateClaimId = null;

            // Triggered from DataTable row button
            $(document).on('click', '.deactivate-claim', function (e) {
                e.preventDefault();
                deactivateExpId = $(this).data('expid');
                deactivateClaimId = $(this).data('claim-id');
                $('#deactivateExpIdLabel').text(deactivateExpId);
                $('#deactivateReason').val('').removeClass('is-invalid');
                new bootstrap.Modal(document.getElementById('deactivateClaimModal')).show();
            });

            // Triggered from the claim detail modal footer button
            $(document).on('click', '#modalDeactivateClaimBtn', function () {
                deactivateExpId = $('#claimDetailModal').attr('data-modal-expid');
                deactivateClaimId = $('#sltClaimTypeList').val() || $('#claimDetailModal').attr('data-modal-claimid');
                $('#deactivateExpIdLabel').text(deactivateExpId);
                $('#deactivateReason').val('').removeClass('is-invalid');
                $('#claimDetailModal').modal('hide');
                new bootstrap.Modal(document.getElementById('deactivateClaimModal')).show();
            });

            // Triggered from the claim detail modal footer button (deactivated page)
            $(document).on('click', '#modalRevertDeactivatedBtn', function () {
                revertExpId = $('#claimDetailModal').attr('data-modal-expid');
                revertClaimId = $('#sltClaimTypeList').val() || $('#claimDetailModal').attr('data-modal-claimid');
                $('#revertDeactivatedExpIdLabel').text(revertExpId);
                $('#claimDetailModal').modal('hide');
                new bootstrap.Modal(document.getElementById('revertDeactivatedModal')).show();
            });

            // Confirm deactivation
            $('#confirmDeactivateClaimBtn').on('click', function () {
                var reason = $('#deactivateReason').val().trim();
                if (!reason) {
                    $('#deactivateReason').addClass('is-invalid');
                    return;
                }
                $('#deactivateReason').removeClass('is-invalid');

                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="ri-loader-2-line spinner"></i> Deactivating...');

                $.ajax({
                    url: "{{ route('data.punch.deactivate') }}",
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        expid: deactivateExpId,
                        claimid: deactivateClaimId,
                        reason: reason,
                    },
                    success: function (res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(document.getElementById('deactivateClaimModal')).hide();
                            // Remove row from DataTable
                            var row = $('tr[data-row-id="' + deactivateExpId + '"]');
                            row.fadeOut(300, function () { row.remove(); });
                            // Update badge counts
                            if (res.data.uploadedCount !== undefined) $('.status-btn.uploaded span').last().text('(' + res.data.uploadedCount + ')');
                            if (res.data.filledCount !== undefined) $('.status-btn.filled span').last().text('(' + res.data.filledCount + ')');
                            if (res.data.holdCount !== undefined) $('.status-btn.hold span').last().text('(' + res.data.holdCount + ')');
                            if (res.data.draftCount !== undefined) $('.status-btn.draft span').last().text('(' + res.data.draftCount + ')');
                            if (res.data.deniedCount !== undefined) $('.status-btn.denied span').last().text('(' + res.data.deniedCount + ')');
                            if (typeof showAlert === 'function') {
                                showAlert('success', 'ri-forbid-line', res.message || 'Claim deactivated successfully.');
                            }
                        } else {
                            if (typeof showAlert === 'function') {
                                showAlert('danger', 'ri-error-warning-line', res.message || 'Failed to deactivate claim.');
                            } else {
                                alert(res.message || 'Failed to deactivate claim.');
                            }
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while deactivating the claim.';
                        if (typeof showAlert === 'function') {
                            showAlert('danger', 'ri-error-warning-line', msg);
                        } else {
                            alert(msg);
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html('<i class="ri-forbid-line me-1"></i>Deactivate');
                        deactivateExpId = null;
                        deactivateClaimId = null;
                    }
                });
            });

            // Clear state when modal is dismissed
            document.getElementById('deactivateClaimModal').addEventListener('hidden.bs.modal', function () {
                $('#deactivateReason').val('').removeClass('is-invalid');
                deactivateExpId = null;
                deactivateClaimId = null;
            });

            // ---- Revert Deactivated Claim ----
            var revertExpId = null;
            var revertClaimId = null;

            $(document).on('click', '.revert-deactivated', function (e) {
                e.preventDefault();
                revertExpId = $(this).data('expid');
                revertClaimId = $(this).data('claim-id');
                $('#revertDeactivatedExpIdLabel').text(revertExpId);
                new bootstrap.Modal(document.getElementById('revertDeactivatedModal')).show();
            });

            $('#confirmRevertDeactivatedBtn').on('click', function () {
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="ri-loader-2-line spinner"></i> Reverting...');

                $.ajax({
                    url: "{{ route('data.punch.revert.deactivated') }}",
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        expid: revertExpId,
                        claimid: revertClaimId,
                    },
                    success: function (res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(document.getElementById('revertDeactivatedModal')).hide();
                            var row = $('tr[data-row-id="' + revertExpId + '"]');
                            row.fadeOut(300, function () { row.remove(); });
                            // Update badge counts
                            if (res.data.uploadedCount !== undefined) $('.status-btn.uploaded span').last().text('(' + res.data.uploadedCount + ')');
                            if (res.data.filledCount !== undefined) $('.status-btn.filled span').last().text('(' + res.data.filledCount + ')');
                            if (res.data.holdCount !== undefined) $('.status-btn.hold span').last().text('(' + res.data.holdCount + ')');
                            if (res.data.draftCount !== undefined) $('.status-btn.draft span').last().text('(' + res.data.draftCount + ')');
                            if (res.data.deniedCount !== undefined) $('.status-btn.denied span').last().text('(' + res.data.deniedCount + ')');
                            if (res.data.deactivatedCount !== undefined) {
                                $('.status-btn.deactivated span').last().text('(' + res.data.deactivatedCount + ')');
                                $('#deactivatedTabCount').text('(' + res.data.deactivatedCount + ')');
                            }
                            if (typeof showAlert === 'function') {
                                showAlert('success', 'ri-checkbox-circle-line', res.message || 'Claim reverted to Uploaded successfully.');
                            }
                        } else {
                            if (typeof showAlert === 'function') {
                                showAlert('danger', 'ri-error-warning-line', res.message || 'Failed to revert claim.');
                            } else {
                                alert(res.message || 'Failed to revert claim.');
                            }
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while reverting the claim.';
                        if (typeof showAlert === 'function') {
                            showAlert('danger', 'ri-error-warning-line', msg);
                        } else {
                            alert(msg);
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html('<i class="ri-arrow-go-back-line me-1"></i>Yes, Revert');
                        revertExpId = null;
                        revertClaimId = null;
                    }
                });
            });

            document.getElementById('revertDeactivatedModal').addEventListener('hidden.bs.modal', function () {
                revertExpId = null;
                revertClaimId = null;
            });
        })();
    </script>
@endpush
