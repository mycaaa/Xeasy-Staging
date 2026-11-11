@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-filter-3-line align-middle me-1"></i>
                            Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="filterForm">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Date Range</label>
                                    <input type="text"
                                        class="form-control"
                                        id="dateRange"
                                        name="date_range"
                                        data-provider="flatpickr"
                                        data-date-format="Y-m-d"
                                        data-range-date="true"
                                        placeholder="Select date range" aria-label="Select date range">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Claim Type</label>
                                    <select class="form-select" id="claimType" name="claim_type">
                                        <option value="">All Claims</option>
                                        @foreach($claimTypes ?? [] as $claim)
                                        <option value="{{ $claim->id }}">{{ $claim->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Employee</label>
                                    <input type="text" class="form-control" id="employee" name="employee" placeholder="Employee name or ID" aria-label="Employee name or ID">
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-2">
                                    <button type="button" id="applyFilters" class="btn btn-primary flex-grow-1">
                                        <i class="ri-search-line me-1"></i>
                                        Search
                                    </button>
                                    <button type="button" class="btn btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                        <i class="ri-filter-3-line"></i>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">
                                            0
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @include('partials.organogram-filters-drawer')
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="card-title mb-0 flex-grow-1">
                            <i class="ri-file-list-3-line align-middle me-1"></i>
                            Extraction Results
                        </h5>
                        <div class="flex-shrink-0">
                            <button type="button" id="exportExcel" class="btn btn-success btn-sm me-2" disabled>
                                <i class="ri-file-excel-line me-1"></i>
                                Export Excel
                            </button>
                            <button type="button" id="exportCSV" class="btn btn-info btn-sm me-2" disabled>
                                <i class="ri-file-text-line me-1"></i>
                                Export CSV
                            </button>
                            <button type="button" id="refreshData" class="btn btn-secondary btn-sm">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="extractionTable" class="table table-bordered table-hover table-striped align-middle" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>S.No</th>
                                        <th>Extract ID</th>
                                        <th>Employee</th>
                                        <th>Claim Type</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
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
</div>
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extraction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailModalContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('styles')

<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script>
    $(document).ready(function() {
        let dataTable;
        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [
                new Date(new Date().getFullYear(), new Date().getMonth(), 1),
                new Date()
            ]
        });

        function initDataTable() {
            if ($.fn.DataTable.isDataTable('#extractionTable')) {
                $('#extractionTable').DataTable().destroy();
            }

            dataTable = $('#extractionTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('reports.extract.data') }}",
                    type: 'POST',
                    data: function(d) {
                        d._token = '{{ csrf_token() }}';
                        d.date_range = $('#dateRange').val();
                        d.claim_type = $('#claimType').val();
                        d.status = $('#status').val();
                        d.employee = $('#employee').val();
                        d.department = $('#department').val();
                        d.amount_range = $('#amountRange').val();
                        if (typeof window.getOrganogramFilterValues === 'function') {
                            $.extend(d, window.getOrganogramFilterValues());
                        }
                    },
                    dataSrc: function(json) {
                        updateSummaryStats(json.summary);
                        return json.data;
                    }
                },
                columns: [{
                        data: null,
                        searchable: false,
                        orderable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {
                        data: 'extract_id'
                    },
                    {
                        data: 'employee_name'
                    },
                    {
                        data: 'claim_type'
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'amount',
                        render: function(data) {
                            return '₹' + parseFloat(data).toLocaleString('en-IN');
                        }
                    },
                    {
                        data: 'status',
                        render: function(data) {
                            const badges = {
                                'pending': 'warning',
                                'approved': 'success',
                                'rejected': 'danger'
                            };
                            return `<span class="badge bg-${badges[data] || 'secondary'}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            return `
                               <button class="btn btn-sm btn-primary view-details" data-id="${row.id}">
                                   <i class="ri-eye-line"></i>
                               </button>
                           `;
                        }
                    }
                ],
                order: [
                    [1, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                language: {
                    emptyTable: "No extraction records found. Please adjust your filters.",
                    processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>'
                }
            });
            dataTable.on('draw', function() {
                const hasData = dataTable.data().count() > 0;
                $('#exportExcel, #exportCSV').prop('disabled', !hasData);
            });
        }

        function updateSummaryStats(summary) {
            if (summary) {
                $('#totalRecords').text(summary.total_records || 0);
                $('#totalAmount').text('₹' + parseFloat(summary.total_amount || 0).toLocaleString('en-IN'));
                $('#approvedCount').text(summary.approved_count || 0);
                $('#pendingCount').text(summary.pending_count || 0);
                $('#summaryStats').slideDown();
            }
        }
        $("#claimType").select2({
            width: "100%",
            placeholder: "Select Claim Type",
            allowClear: true,
            multiple: false
        });
        $('#applyFilters').on('click', function() {
            dataTable.ajax.reload();
        });
        $('#refreshData').on('click', function() {
            dataTable.ajax.reload(null, false);
        });
        $('#exportExcel').on('click', function() {
            const params = new URLSearchParams({
                date_range: $('#dateRange').val() || '',
                claim_type: $('#claimType').val() || '',
                status: $('#status').val() || '',
                employee: $('#employee').val() || '',
                department: $('#department').val() || '',
                amount_range: $('#amountRange').val() || '',
                format: 'excel'
            });

            if (typeof window.getOrganogramFilterValues === 'function') {
                const organogramVals = window.getOrganogramFilterValues();
                Object.keys(organogramVals).forEach(function (k) {
                    const val = organogramVals[k];
                    if (Array.isArray(val)) {
                        val.forEach(item => params.append(k + '[]', item));
                    } else if (val !== null && val !== undefined && val !== '') {
                        params.append(k, val);
                    }
                });
            }

            window.location.href = "{{ route('reports.extract.export') }}?" + params.toString();
        });
        $('#exportCSV').on('click', function() {
            const params = new URLSearchParams({
                date_range: $('#dateRange').val() || '',
                claim_type: $('#claimType').val() || '',
                status: $('#status').val() || '',
                employee: $('#employee').val() || '',
                department: $('#department').val() || '',
                amount_range: $('#amountRange').val() || '',
                format: 'csv'
            });

            if (typeof window.getOrganogramFilterValues === 'function') {
                const organogramVals = window.getOrganogramFilterValues();
                Object.keys(organogramVals).forEach(function (k) {
                    const val = organogramVals[k];
                    if (Array.isArray(val)) {
                        val.forEach(item => params.append(k + '[]', item));
                    } else if (val !== null && val !== undefined && val !== '') {
                        params.append(k, val);
                    }
                });
            }

            window.location.href = "{{ route('reports.extract.export') }}?" + params.toString();
        });

        $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
            dataTable.ajax.reload();
        });

        $(document).on('click', '.view-details', function() {
            const id = $(this).data('id');
            $('#detailModal').modal('show');
            $('#detailModalContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>');

            $.ajax({
                url: "{{ route('reports.extract.details', ':id') }}".replace(':id', id),
                type: 'GET',
                success: function(response) {
                    $('#detailModalContent').html(response);
                },
                error: function() {
                    $('#detailModalContent').html('<div class="alert alert-danger">Failed to load details</div>');
                }
            });
        });
        initDataTable();
    });
</script>
@include('partials.organogram-filters-js')
@endpush