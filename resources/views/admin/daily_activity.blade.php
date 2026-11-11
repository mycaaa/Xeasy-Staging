@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Activity Tracking
                                Report</h4>
                            <input type="text" class="form-control" data-provider="flatpickr" data-date-format="Y-m-d"
                                data-range-date="true" id="dateRange" style="width: 200px;" placeholder="Select date range" aria-label="Select date range">
                            <button type="button" class="btn btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                <i class="ri-filter-3-line me-1"></i> More Filters
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">
                                    0
                                </span>
                            </button>
                            <div class="flex-shrink-0">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    data-bs-toggle="modal" data-bs-target="#exportModal">
                                    Export Report
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                </button>
                            </div>
                        </div>
                        @include('partials.organogram-filters-drawer')
                        <div class="card-body pb-3 pt-2">
                            <table style="margin-top: 15px" id="dailyActivityTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Action Date</th>
                                        <th>Total Upload</th>
                                        <th>Punching</th>
                                        <th>Verified</th>
                                        <th>Approved</th>
                                        <th>Financed</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Export Daily Activity Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Export the daily activity report for the selected date range?</p>
                    <p><strong>From:</strong> <span id="modalFromDate"></span></p>
                    <p><strong>To:</strong> <span id="modalToDate"></span></p>
                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                            id="confirmExport">
                            <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                <span class="loader" style="display: none;"></span>
                            </i>
                            Export to Excel
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/@simonwep/pickr/themes/classic.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/@simonwep/pickr/themes/monolith.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/@simonwep/pickr/themes/nano.min.css') }}" />
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('vendor/cdn/datatables.net-bs5@1.11.5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            flatpickr("#dateRange", {
                dateFormat: "Y-m-d",
                mode: "range",
                onChange: function (selectedDates) {
                    if (selectedDates.length === 2) {
                        $('#dailyActivityTable').DataTable().ajax.reload();
                    }
                }
            });
            var table = $('#dailyActivityTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '/daily-activity/data',
                    type: 'POST',
                    data: function (d) {
                        var dates = $('#dateRange').val().split(' to ');
                        d.fromDate = dates[0];
                        d.toDate = dates[1] || dates[0];
                        d._token = '{{ csrf_token() }}';
                        if (typeof window.getOrganogramFilterValues === 'function') {
                            $.extend(d, window.getOrganogramFilterValues());
                        }
                    },
                },
                columns: [
                    { data: 'ActionDate' },
                    { data: 'TotalUpload' },
                    { data: 'Punching' },
                    { data: 'Verified' },
                    { data: 'Approved' },
                    { data: 'Financed' },
                ],
                order: [[0, 'asc']],
            });

            $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
                $('#dailyActivityTable').DataTable().ajax.reload();
            });

            $('#exportModal').on('show.bs.modal', function () {
                var dates = $('#dateRange').val().split(' to ');
                var fromDate = dates[0];
                var toDate = dates[1] || dates[0];
                $('#modalFromDate').text(fromDate);
                $('#modalToDate').text(toDate);
            });
            $('#confirmExport').on('click', function () {
                var dates = $('#dateRange').val().split(' to ');
                var fromDate = dates[0];
                var toDate = dates[1] || dates[0];

                if (!fromDate || !toDate) {
                    alert('Please select a date range.');
                    return;
                }

                var form = $('<form>', {
                    'method': 'POST',
                    'action': '/daily-activity/export',
                    'target': '_blank'
                }).append(
                    $('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': '{{ csrf_token() }}'
                    }),
                    $('<input>', {
                        'type': 'hidden',
                        'name': 'fromDate',
                        'value': fromDate
                    }),
                    $('<input>', {
                        'type': 'hidden',
                        'name': 'toDate',
                        'value': toDate
                    })
                );

                if (typeof window.getOrganogramFilterValues === 'function') {
                    const organogramVals = window.getOrganogramFilterValues();
                    Object.keys(organogramVals).forEach(function (k) {
                        const val = organogramVals[k];
                        if (Array.isArray(val)) {
                            val.forEach(item => form.append($('<input>', { type: 'hidden', name: k + '[]', value: item })));
                        } else if (val !== null && val !== undefined && val !== '') {
                            form.append($('<input>', { type: 'hidden', name: k, value: val }));
                        }
                    });
                }

                form.appendTo('body').submit().remove();
                $('#exportModal').modal('hide');
            });
        }); 
    </script>
    @include('partials.organogram-filters-js')
@endpush