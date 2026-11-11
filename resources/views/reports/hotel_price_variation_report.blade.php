@extends('layouts.app')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex gap-2 flex-wrap">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-hotel-line"></i> Hotel Price Variation Report
                            </h4>
                            <div class="ms-auto">
                                <button type="button" class="btn btn-sm btn-primary" id="exportBtn">
                                    <i class="ri-download-2-fill"></i> Export
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" id="refreshBtn">
                                    <i class="ri-refresh-line"></i> Refresh
                                </button>
                            </div>
                        </div>

                        {{-- Filters Section --}}
                        <div class="card-body border-bottom pb-2 pt-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fs-12">Hotel Name</label>
                                    <input type="text" id="filterHotelName" class="form-control form-control-sm"
                                        placeholder="Search Hotel" aria-label="Search Hotel">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fs-12">City Name</label>
                                    <input type="text" id="filterCityName" class="form-control form-control-sm"
                                        placeholder="Search City" aria-label="Search City">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fs-12">From Date</label>
                                    <input type="date" id="filterFromDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fs-12">To Date</label>
                                    <input type="date" id="filterToDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fs-12">Min Price (₹)</label>
                                    <input type="number" id="filterMinPrice" class="form-control form-control-sm"
                                        placeholder="Min Amount" aria-label="Min Amount">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fs-12">Max Price (₹)</label>
                                    <input type="number" id="filterMaxPrice" class="form-control form-control-sm"
                                        placeholder="Max Amount" aria-label="Max Amount">
                                </div>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-md-3">
                                    <label class="form-label mb-1 fs-12">Employee</label>
                                    <select id="filterEmployee" class="form-select form-select-sm select2-multiple" multiple
                                        data-placeholder="Select Employees">
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->EmployeeID }}">
                                                {{ $employee->EmpCode }} - {{ $employee->Fname }} {{ $employee->Sname }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 fs-12">Function</label>
                                    <select id="filterFunction" class="form-select form-select-sm select2-multiple" multiple
                                        data-placeholder="Select Functions">
                                        @foreach($functions as $function)
                                            <option value="{{ $function->id }}">{{ $function->function_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 fs-12">Department</label>
                                    <select id="filterDepartment" class="form-select form-select-sm select2-multiple"
                                        multiple data-placeholder="Select Departments">
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->department_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 fs-12">Vertical</label>
                                    <select id="filterVertical" class="form-select form-select-sm select2-multiple" multiple
                                        data-placeholder="Select Verticals">
                                        @foreach($verticals as $vertical)
                                            <option value="{{ $vertical->id }}">{{ $vertical->vertical_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-md-12 text-end d-flex justify-content-end align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                        <i class="ri-equalizer-line me-1"></i> More Filters
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="organogramFilterCountBadge" style="display:none;">0</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearFilters">
                                        <i class="ri-close-line"></i> Clear All Filters
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" id="applyFilters">
                                        <i class="ri-search-line"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                        @include('partials.organogram-filters-drawer')

                        {{-- Summary Cards --}}
                        <div class="card-body pb-0">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-info bg-opacity-10">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Hotels with Price Variation</h6>
                                            <h3 class="mb-0" id="totalHotels">0</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning bg-opacity-10">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Total Claims</h6>
                                            <h3 class="mb-0" id="totalClaims">0</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success bg-opacity-10">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Avg Price Difference</h6>
                                            <h3 class="mb-0" id="avgPriceDiff">₹0</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-danger bg-opacity-10">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Max Price Difference</h6>
                                            <h3 class="mb-0" id="maxPriceDiff">₹0</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabs for Summary and Details --}}
                        <div class="card-body pb-3 pt-2">
                            <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="summary-tab" data-bs-toggle="tab"
                                        data-bs-target="#summary" type="button" role="tab">
                                        <i class="ri-bar-chart-line"></i> Summary View
                                    </button>
                                </li>
                                {{-- <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details"
                                        type="button" role="tab">
                                        <i class="ri-table-line"></i> Detailed View
                                    </button>
                                </li> --}}
                            </ul>

                            <div class="tab-content mt-3">

                                {{-- Summary Tab --}}
                                <div class="tab-pane fade show active" id="summary" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="summaryTable" class="table table-bordered table-hover nowrap w-100">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="30px"></th> {{-- Add this column for expand button --}}
                                                    <th>#</th>
                                                    <th>Hotel Name</th>
                                                    <th>City</th>
                                                    <th>Address</th>
                                                    <th>Unique Prices</th>
                                                    <th>Min Price</th>
                                                    <th>Max Price</th>
                                                    <th>Price Difference</th>
                                                    <th>Total Claims</th>
                                                    <th>Total Pax</th>
                                                    <th>First Bill Date</th>
                                                    <th>Last Bill Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                {{-- Details Tab --}}
                                <div class="tab-pane fade" id="details" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="detailsTable" class="table table-bordered table-hover nowrap w-100">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Exp ID</th>
                                                    <th>Hotel Name</th>
                                                    <th>City</th>
                                                    <th>Bill No</th>
                                                    <th>Bill Date</th>
                                                    <th>Arrival</th>
                                                    <th>Departure</th>
                                                    <th>Stay Days</th>
                                                    <th>Pax</th>
                                                    <th>Room Type</th>
                                                    <th>Financed Amount</th>
                                                    <th>Price Diff</th>
                                                    <th>Action</th>
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
            </div>
        </div>
    </div>

    {{-- Hotel Details Modal --}}
    <div class="modal fade" id="hotelDetailsModal" tabindex="-1" aria-labelledby="hotelDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="hotelDetailsLabel">
                        <i class="ri-hotel-line"></i> Hotel Booking Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3" id="hotelInfo">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong><i class="ri-hotel-line"></i> Hotel:</strong> <span id="modalHotelName">-</span><br>
                                <strong><i class="ri-map-pin-line"></i> Address:</strong> <span
                                    id="modalHotelAddress">-</span><br>
                                <strong><i class="ri-phone-line"></i> Contact:</strong> <span
                                    id="modalHotelContact">-</span><br>
                                <strong><i class="ri-mail-line"></i> Email:</strong> <span id="modalHotelEmail">-</span><br>
                                <strong><i class="ri-receipt-line"></i> Bill No:</strong> <span
                                    id="modalBillNo">-</span><br>
                                <strong><i class="ri-money-rupee-circle-line"></i> Financed Amount:</strong> <span
                                    id="modalFinancedAmt">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="ri-user-line"></i> Stay Details</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th>Arrival:</th>
                                            <td id="modalArrival">-</td>
                                        </tr>
                                        <tr>
                                            <th>Departure:</th>
                                            <td id="modalDeparture">-</td>
                                        </tr>
                                        <tr>
                                            <th>Stay Duration:</th>
                                            <td id="modalStayDuration">-</td>
                                        </tr>
                                        <tr>
                                            <th>No of Pax:</th>
                                            <td id="modalNoOfPax">-</td>
                                        </tr>
                                        <tr>
                                            <th>Room Rate Type:</th>
                                            <td id="modalRoomRateType">-</td>
                                        </tr>
                                        <tr>
                                            <th>Plan:</th>
                                            <td id="modalPlan">-</td>
                                        </tr>
                                        <tr>
                                            <th>GST:</th>
                                            <td id="modalGST">-</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="ri-group-line"></i> Employee Details</h6>
                                </div>
                                <div class="card-body">
                                    <div id="employeeDetailsList">
                                        <p class="text-muted">Loading employee details...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .badge i {
            font-size: 12px;
            margin-right: 3px;
        }

        .table tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }

        .btn-sm i {
            font-size: 14px;
        }

        .modal-xl {
            max-width: 1200px;
        }

        .price-diff-high {
            background-color: #ffebee;
            font-weight: bold;
            color: #c62828;
        }

        .card {
            transition: all 0.3s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link {
            font-weight: 500;
        }

        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 10px;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function () {
            let summaryTable, detailsTable;

            // Initialize Select2
            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function () {
                    return $(this).data('placeholder');
                }
            });

            // Initialize Summary DataTable
            function initSummaryTable() {
                if ($.fn.DataTable.isDataTable("#summaryTable")) {
                    $("#summaryTable").DataTable().destroy();
                }

                summaryTable = $("#summaryTable").DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.get.hotel.price.variation.summary') }}",
                        type: 'GET',
                        data: function (d) {
                            d.hotel_name = $("#filterHotelName").val();
                            d.city_name = $("#filterCityName").val();
                            d.from_date = $("#filterFromDate").val();
                            d.to_date = $("#filterToDate").val();
                            d.function_ids = $("#filterFunction").val();
                            d.department_ids = $("#filterDepartment").val();
                            d.vertical_ids = $("#filterVertical").val();
                            d.user_ids = $("#filterEmployee").val();
                            if (typeof window.getOrganogramFilterValues === 'function') {
                                $.extend(d, window.getOrganogramFilterValues());
                            }
                        },
                        dataSrc: function (json) {
                            // Update summary cards
                            updateSummaryCards(json.data);
                            return json.data;
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'HotelName', name: 'HotelName' },
                        { data: 'CityName', name: 'CityName' },
                        { data: 'HotelAddress', name: 'HotelAddress' },
                        { data: 'unique_prices', name: 'unique_prices' },
                        { data: 'min_price', name: 'min_price' },
                        { data: 'max_price', name: 'max_price' },
                        { data: 'price_difference', name: 'price_difference' },
                        { data: 'total_claims', name: 'total_claims' },
                        { data: 'total_pax', name: 'total_pax' },
                        { data: 'first_bill_date', name: 'first_bill_date' },
                        { data: 'last_bill_date', name: 'last_bill_date' },
                        { data: 'action', name: 'action', orderable: false, searchable: false }
                    ],
                    order: [[7, 'desc']],
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "No entries found",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        zeroRecords: "No matching records found"
                    }
                });
            }

            // Initialize Details DataTable
            // function initDetailsTable() {
            //     if ($.fn.DataTable.isDataTable("#detailsTable")) {
            //         $("#detailsTable").DataTable().destroy();
            //     }

            //     detailsTable = $("#detailsTable").DataTable({
            //         processing: true,
            //         serverSide: true,
            //         ajax: {
            //             url: "{{ route('admin.get.hotel.price.variation.data') }}",
            //             type: 'GET',
            //             data: function(d) {
            //                 d.hotel_name = $("#filterHotelName").val();
            //                 d.city_name = $("#filterCityName").val();
            //                 d.from_date = $("#filterFromDate").val();
            //                 d.to_date = $("#filterToDate").val();
            //                 d.min_price = $("#filterMinPrice").val();
            //                 d.max_price = $("#filterMaxPrice").val();
            //                 d.function_ids = $("#filterFunction").val();
            //                 d.department_ids = $("#filterDepartment").val();
            //                 d.vertical_ids = $("#filterVertical").val();
            //                 d.user_ids = $("#filterEmployee").val();
            //             }
            //         },
            //         columns: [
            //             { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            //             { data: 'ExpId', name: 'ExpId' },
            //             { data: 'HotelName', name: 'HotelName' },
            //             { data: 'CityName', name: 'CityName' },
            //             { data: 'BillNo', name: 'BillNo' },
            //             { data: 'BillDate', name: 'BillDate' },
            //             { data: 'Arrival', name: 'Arrival' },
            //             { data: 'Departure', name: 'Departure' },
            //             { data: 'StayDuration', name: 'StayDuration' },
            //             { data: 'NoOfPAX', name: 'NoOfPAX' },
            //             { data: 'RoomRateType', name: 'RoomRateType' },
            //             { data: 'FinancedTAmt', name: 'FinancedTAmt' },
            //             { data: 'price_difference', name: 'price_difference' },
            //             { data: 'action', name: 'action', orderable: false, searchable: false }
            //         ],
            //         order: [[2, 'asc'], [11, 'desc']],
            //         pageLength: 25,
            //         lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            //         responsive: true
            //     });
            // }

            // Update summary cards
            function updateSummaryCards(data) {
                let uniqueHotels = new Set();
                let totalClaims = 0;
                let totalPax = 0;
                let totalPriceDiff = 0;
                let maxPriceDiff = 0;

                data.forEach(item => {
                    uniqueHotels.add(item.HotelName + '|' + item.CityName);
                    totalClaims += parseInt(item.total_claims) || 0;
                    totalPax += parseInt(item.total_pax) || 0;
                    let diff = parseFloat(item.price_difference.replace(/[^0-9.-]/g, '')) || 0;
                    totalPriceDiff += diff;
                    if (diff > maxPriceDiff) maxPriceDiff = diff;
                });

                $("#totalHotels").text(uniqueHotels.size);
                $("#totalClaims").text(totalClaims);
                $("#avgPriceDiff").text('₹' + (totalPriceDiff / (data.length || 1)).toFixed(2));
                $("#maxPriceDiff").text('₹' + maxPriceDiff.toFixed(2));
            }

            // Apply filters
            $("#applyFilters").on("click", function () {
                if (summaryTable) summaryTable.ajax.reload();
                if (detailsTable) detailsTable.ajax.reload();
            });

            // Clear filters
            $("#clearFilters").on("click", function () {
                $("#filterHotelName").val('');
                $("#filterCityName").val('');
                $("#filterFromDate").val('');
                $("#filterToDate").val('');
                $("#filterMinPrice").val('');
                $("#filterMaxPrice").val('');
                $("#filterEmployee").val(null).trigger('change');
                $("#filterFunction").val(null).trigger('change');
                $("#filterDepartment").val(null).trigger('change');
                $("#filterVertical").val(null).trigger('change');

                if (typeof window.resetOrganogramFilters === 'function') {
                    window.resetOrganogramFilters();
                }

                if (summaryTable) summaryTable.ajax.reload();
                if (detailsTable) detailsTable.ajax.reload();
            });

            // Export
            $("#exportBtn").on("click", function () {
                let params = new URLSearchParams({
                    hotel_name: $("#filterHotelName").val() || '',
                    city_name: $("#filterCityName").val() || '',
                    from_date: $("#filterFromDate").val() || '',
                    to_date: $("#filterToDate").val() || '',
                    min_price: $("#filterMinPrice").val() || '',
                    max_price: $("#filterMaxPrice").val() || '',
                    function_ids: $("#filterFunction").val() || '',
                    department_ids: $("#filterDepartment").val() || '',
                    vertical_ids: $("#filterVertical").val() || '',
                    user_ids: $("#filterEmployee").val() || ''
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

                window.location.href = "{{ route('admin.export.hotel.price.variation') }}?" + params.toString();
            });

            $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
                if (summaryTable) summaryTable.ajax.reload();
                if (detailsTable) detailsTable.ajax.reload();
            });

            // Refresh
            $("#refreshBtn").on("click", function () {
                if (summaryTable) summaryTable.ajax.reload();
                if (detailsTable) detailsTable.ajax.reload();
            });

            // View hotel details
            $(document).on("click", ".view-hotel-details", function () {
                let expId = $(this).data("exp-id");

                $("#hotelDetailsModal").modal("show");
                $("#employeeDetailsList").html('<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</div>');

                $.ajax({
                    url: "/admin/get-hotel-details/" + expId,
                    method: 'GET',
                    success: function (response) {
                        if (response.status === 'success' && response.data) {
                            displayHotelDetails(response.data);
                        } else {
                            $("#employeeDetailsList").html('<p class="text-danger">Failed to load details</p>');
                        }
                    },
                    error: function () {
                        $("#employeeDetailsList").html('<p class="text-danger">Error loading details</p>');
                    }
                });
            });

            // View hotel variations
            $(document).on("click", ".view-hotel-variations", function () {
                let hotelName = $(this).data("hotel-name");
                let cityName = $(this).data("city-name");

                $("#filterHotelName").val(hotelName);
                $("#filterCityName").val(cityName);
                $("#details-tab").click();

                if (detailsTable) detailsTable.ajax.reload();
            });

            function displayHotelDetails(data) {
                $("#modalHotelName").text(data.HotelName || '-');
                $("#modalHotelAddress").text(data.HotelAddress || '-');
                $("#modalHotelContact").text(data.HotelContact || '-');
                $("#modalHotelEmail").text(data.HotelEmail || '-');
                $("#modalBillNo").text(data.BillNo || '-');
                $("#modalFinancedAmt").text('₹ ' + (parseFloat(data.FinancedTAmt) || 0).toFixed(2));
                $("#modalArrival").text(data.Arrival ? new Date(data.Arrival).toLocaleDateString() : '-');
                $("#modalDeparture").text(data.Departure ? new Date(data.Departure).toLocaleDateString() : '-');
                $("#modalStayDuration").text(data.StayDuration || '-');
                $("#modalNoOfPax").text(data.NoOfPAX || '-');
                $("#modalRoomRateType").text(data.RoomRateType || '-');
                $("#modalPlan").text(data.Plan || '-');
                $("#modalGST").text(data.GST || '-');

                // Display employee details
                if (data.EmployeeDetails && data.EmployeeDetails.length > 0) {
                    let html = '<table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Emp Code</th><th>Employee Name</th></tr></thead><tbody>';
                    data.EmployeeDetails.forEach(emp => {
                        let name = [emp.Fname, emp.Sname, emp.Lname].filter(n => n).join(' ');
                        html += `<tr><td>${emp.EmpCode || '-'}</td><td>${name || '-'}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    $("#employeeDetailsList").html(html);
                } else if (data.PaxDetails) {
                    $("#employeeDetailsList").html(`<p class="text-muted">Pax Details: ${data.PaxDetails}</p>`);
                } else {
                    $("#employeeDetailsList").html('<p class="text-muted">No employee details available</p>');
                }
            }

            // Initialize tables
            initSummaryTable();
            initDetailsTable();

            // Auto-refresh when tab changes
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                if ($(e.target).attr('href') === '#summary') {
                    if (summaryTable) summaryTable.columns.adjust().responsive.recalc();
                } else if ($(e.target).attr('href') === '#details') {
                    if (detailsTable) detailsTable.columns.adjust().responsive.recalc();
                }
            });
        });
    </script>
    @include('partials.organogram-filters-js')
@endpush
