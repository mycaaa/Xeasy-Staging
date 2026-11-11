@extends('layouts.app')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex gap-2 flex-wrap">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-car-line"></i> Employees with Multiple Vehicles
                            </h4>
                            {{-- <div class="dropdown card-header-dropdown">
                                @php $selectedVehicleType = request('vehicle_type'); @endphp
                                <input type="hidden" id="vehicle_type_filter" value="{{ $selectedVehicleType ?? '' }}" />
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="fw-semibold fs-12">Vehicle Type: </span>
                                    <span class="text-muted">
                                        {{ $selectedVehicleType ? ucfirst($selectedVehicleType) : 'All' }}
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ url()->current() }}">All Types</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?vehicle_type=2 Wheeler">2 Wheeler</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?vehicle_type=4 Wheeler">4 Wheeler</a>
                                </div>
                            </div> --}}
                            {{-- <div class="ms-auto">
                                <button type="button" class="btn btn-sm btn-primary" id="exportButton">
                                    <i class="ri-download-2-fill"></i> Export
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" id="refreshButton">
                                    <i class="ri-refresh-line"></i> Refresh
                                </button>
                            </div> --}}
                        </div>

                        {{-- Extra Filters --}}
                        <div class="card-body border-bottom pb-2 pt-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Employee ID/Code</label>
                                    <input type="text" id="filterEmployeeId" class="form-control form-control-sm"
                                           style="min-width:150px;" placeholder="Search Employee" aria-label="Search Employee">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Vehicle Type</label>
                                    <select id="filterVehicleType" class="form-select form-select-sm" style="min-width:140px;">
                                        <option value="">All Types</option>
                                        <option value="2 Wheeler">2 Wheeler</option>
                                        <option value="4 Wheeler">4 Wheeler</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Min Vehicles</label>
                                    <input type="number" id="filterMinVehicles" class="form-control form-control-sm"
                                           style="width:100px;" placeholder="Min" aria-label="Min" value="2">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Registration No</label>
                                    <input type="text" id="filterRegNo" class="form-control form-control-sm"
                                           style="min-width:120px;" placeholder="Search Reg No" aria-label="Search Reg No">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Model/Brand</label>
                                    <input type="text" id="filterModelBrand" class="form-control form-control-sm"
                                           style="min-width:120px;" placeholder="Model or Brand" aria-label="Model or Brand">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearFilters">
                                        <i class="ri-close-line"></i> Clear
                                    </button>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                        <i class="ri-filter-3-line"></i> More Filters
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">0</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body pb-3 pt-0">
                            <div class="table-responsive">
                                <table id="vehiclesTable"
                                    class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                    style="width: 100%;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="5%">Sn</th>
                                            <th width="15%">Employee Code</th>
                                            <th width="20%">Employee Name</th>
                                            <th width="10%">Vehicle Type</th>
                                            <th width="15%">Registration No</th>
                                            <th width="15%">Model Name</th>
                                            <th width="10%">Brand</th>
                                            <th width="10%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($records as $index => $item)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    <strong>{{ $item->employee_code ?? $item->EmployeeID }}</strong>
                                                </td>
                                                <td>{{ $item->employee_name ?? '-' }}</td>
                                                <td>
                                                    <span class="badge {{ $item->vehicle_type == '4 Wheeler' ? 'bg-info' : 'bg-success' }}">
                                                        <i class="ri-car-line"></i> {{ $item->vehicle_type ?? '-' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <code>{{ $item->registration_no ?? '-' }}</code>
                                                </td>
                                                <td>{{ $item->model_name ?? '-' }}</td>
                                                <td>{{ $item->brand ?? '-' }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-employee-vehicles"
                                                        data-employee-id="{{ $item->EmployeeID }}"
                                                        data-employee-name="{{ $item->employee_name }}"
                                                        data-employee-code="{{ $item->employee_code }}">
                                                        <i class="ri-eye-line"></i> View All
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">
                                                    No employees with multiple vehicles found.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="8" class="text-end">
                                                <strong>Total Employees: {{ $totalEmployees ?? $records->unique('EmployeeID')->count() }}</strong> |
                                                <strong>Total Vehicles: {{ $totalVehicles ?? $records->count() }}</strong>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Vehicles Modal -->
    <div class="modal fade" id="employeeVehiclesModal" tabindex="-1" aria-labelledby="employeeVehiclesLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="employeeVehiclesLabel">
                        <i class="ri-car-line"></i> Employee Vehicles Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3" id="employeeInfo">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong><i class="ri-user-line"></i> Employee:</strong>
                                <span id="modalEmployeeName">-</span>
                                (<span id="modalEmployeeCode">-</span>)
                                <br>
                                <strong><i class="ri-car-line"></i> Total Vehicles:</strong>
                                <span id="modalTotalVehicles">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm" id="vehicleDetailTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Vehicle Type</th>
                                    <th>Registration No</th>
                                    <th>Model Name</th>
                                    <th>Brand</th>
                                    <th>Fuel Type</th>
                                    <th>Manufacturing Year</th>
                                    <th>Insurance Valid Upto</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        Loading vehicles...
                                    </td>
                                </tr>
                            </tbody>
                         </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line"></i> Close
                    </button>
                    <button type="button" class="btn btn-success" id="exportEmployeeVehicles">
                        <i class="ri-download-2-line"></i> Export This Employee
                    </button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.organogram-filters-drawer')
@endsection

@push('styles')
<style>
    .badge i {
        font-size: 12px;
        margin-right: 3px;
    }
    code {
        background-color: #f8f9fa;
        padding: 2px 5px;
        border-radius: 4px;
        font-size: 12px;
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
    .vehicle-status-badge {
        font-size: 11px;
        padding: 3px 8px;
    }
</style>
@endpush

@push('scripts')
@include('partials.organogram-filters-js')
<script>
    $(document).ready(function() {
        let vehiclesTable;

        // Initialize DataTable
        function initDataTable() {
            if ($.fn.DataTable.isDataTable("#vehiclesTable")) {
                $("#vehiclesTable").DataTable().destroy();
            }

            vehiclesTable = $("#vehiclesTable").DataTable({
                responsive: true,
                ordering: true,
                searching: true,
                paging: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[1, 'asc']],
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

        initDataTable();

        // Custom filtering
        $.fn.dataTable.ext.search.push(function(settings, data) {
            if (settings.nTable.id !== 'vehiclesTable') return true;

            // Get filter values
            var employeeSearch = $("#filterEmployeeId").val().trim().toLowerCase();
            var vehicleType = $("#filterVehicleType").val();
            var minVehicles = parseInt($("#filterMinVehicles").val()) || 0;
            var regNo = $("#filterRegNo").val().trim().toLowerCase();
            var modelBrand = $("#filterModelBrand").val().trim().toLowerCase();

            // Get row data
            var rowEmployeeCode = (data[1] || '').toLowerCase();
            var rowEmployeeName = (data[2] || '').toLowerCase();
            var rowVehicleType = (data[3] || '').toLowerCase();
            var rowRegNo = (data[4] || '').toLowerCase();
            var rowModel = (data[5] || '').toLowerCase();
            var rowBrand = (data[6] || '').toLowerCase();

            // Apply filters
            if (employeeSearch && !rowEmployeeCode.includes(employeeSearch) && !rowEmployeeName.includes(employeeSearch)) {
                return false;
            }

            if (vehicleType && !rowVehicleType.includes(vehicleType.toLowerCase())) {
                return false;
            }

            if (regNo && !rowRegNo.includes(regNo)) {
                return false;
            }

            if (modelBrand && !rowModel.includes(modelBrand) && !rowBrand.includes(modelBrand)) {
                return false;
            }

            // For min vehicles filter, we need to check grouped data
            // This will be handled by AJAX reload for better performance
            return true;
        });

        // Filter event handlers
        $("#filterEmployeeId, #filterVehicleType, #filterRegNo, #filterModelBrand").on("keyup change", function() {
            vehiclesTable.draw();
        });

        $("#filterMinVehicles").on("change", function() {
            // Reload with new min vehicles filter
            loadVehicleData();
        });

        $("#clearFilters").on("click", function() {
            $("#filterEmployeeId").val('');
            $("#filterVehicleType").val('');
            $("#filterMinVehicles").val('2');
            $("#filterRegNo").val('');
            $("#filterModelBrand").val('');
            vehiclesTable.draw();
            loadVehicleData();
        });

        // Function to load vehicle data via AJAX
        function loadVehicleData() {
            var minVehicles = $("#filterMinVehicles").val() || 2;
            var vehicleType = $("#filterVehicleType").val();
            var employeeId = $("#filterEmployeeId").val();

            $("#vehiclesTable tbody").html(`
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading data...
                    </td>
                </tr>
            `);

            var organogramFilters = typeof window.getOrganogramFilterValues === 'function' ? window.getOrganogramFilterValues() : {};
            var requestData = $.extend({
                min_vehicles: minVehicles,
                vehicle_type: vehicleType,
                employee_search: employeeId,
                include_employee_details: true
            }, organogramFilters);

            $.ajax({
                url: "{{ route('api.employees.multiple.vehicles') }}",
                method: 'GET',
                data: requestData,
                success: function(response) {
                    if (response.status === 'success' && response.data) {
                        updateTableData(response.data);
                    } else {
                        showError("No data found");
                    }
                },
                error: function(xhr) {
                    showError("Failed to load data: " + (xhr.responseJSON?.message || "Unknown error"));
                }
            });
        }

        $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
            loadVehicleData();
        });

        function updateTableData(employees) {
            var tbody = $("#vehiclesTable tbody");
            tbody.empty();

            if (!employees || employees.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center text-muted">No employees with multiple vehicles found.</td></tr>');
                $("#vehiclesTable").DataTable().draw();
                return;
            }

            var sn = 1;
            employees.forEach(function(employee) {
                if (employee.vehicles && employee.vehicles.length > 0) {
                    employee.vehicles.forEach(function(vehicle) {
                        var row = `
                            <tr>
                                <td>${sn++}</td>
                                <td><strong>${employee.employee_code || employee.employee_id}</strong></td>
                                <td>${employee.employee_name || '-'}</td>
                                <td>
                                    <span class="badge ${vehicle.vehicle_type == '4 Wheeler' ? 'bg-info' : 'bg-success'}">
                                        <i class="ri-car-line"></i> ${vehicle.vehicle_type || '-'}
                                    </span>
                                </td>
                                <td><code>${vehicle.registration_no || '-'}</code></td>
                                <td>${vehicle.model_name || '-'}</td>
                                <td>${vehicle.brand || '-'}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info view-employee-vehicles"
                                        data-employee-id="${employee.employee_id}"
                                        data-employee-name="${employee.employee_name || ''}"
                                        data-employee-code="${employee.employee_code || ''}">
                                        <i class="ri-eye-line"></i> View All
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                }
            });

            // Update footer totals
            var totalEmployees = employees.length;
            var totalVehicles = employees.reduce((sum, emp) => sum + (emp.vehicles?.length || 0), 0);
            $("#vehiclesTable tfoot td").html(`
                <strong>Total Employees: ${totalEmployees}</strong> |
                <strong>Total Vehicles: ${totalVehicles}</strong>
            `);

            vehiclesTable.draw();
        }

        function showError(message) {
            $("#vehiclesTable tbody").html(`<tr><td colspan="8" class="text-center text-danger">${message}</td></tr>`);
            vehiclesTable.draw();
        }

        // View employee vehicles
        $(document).on("click", ".view-employee-vehicles", function() {
            const employeeId = $(this).data("employee-id");
            const employeeName = $(this).data("employee-name");
            const employeeCode = $(this).data("employee-code");

            $("#modalEmployeeName").text(employeeName || employeeId);
            $("#modalEmployeeCode").text(employeeCode || 'N/A');
            $("#employeeVehiclesModal").modal("show");

            const tbody = $("#vehicleDetailTable tbody");
            tbody.html(`<tr><td colspan="9" class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading vehicles...</td></tr>`);

            $.ajax({
                url: `/admin/api/employee/${employeeId}/vehicles`,
                method: 'GET',
                success: function(response) {
                    if (response.status === 'success' && response.data) {
                        displayEmployeeVehicles(response.data);
                    } else {
                        tbody.html(`<tr><td colspan="9" class="text-center text-danger">No vehicles found</td></tr>`);
                    }
                },
                error: function() {
                    tbody.html(`<tr><td colspan="9" class="text-center text-danger">Failed to load vehicle details</td></tr>`);
                }
            });
        });

        function displayEmployeeVehicles(data) {
            const vehicles = data.vehicles || [];
            $("#modalTotalVehicles").text(vehicles.length);

            const tbody = $("#vehicleDetailTable tbody");
            tbody.empty();

            if (vehicles.length === 0) {
                tbody.html(`<tr><td colspan="9" class="text-center text-muted">No vehicles registered</td></tr>`);
                return;
            }

            vehicles.forEach((vehicle, index) => {
                const isActive = vehicle.is_active == 1;
                const row = `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <span class="badge ${vehicle.vehicle_type == '4 Wheeler' ? 'bg-info' : 'bg-success'}">
                                ${vehicle.vehicle_type || '-'}
                            </span>
                        </td>
                        <td><code>${vehicle.registration_no || '-'}</code></td>
                        <td>${vehicle.model_name || '-'}</td>
                        <td>${vehicle.brand || '-'}</td>
                        <td>${vehicle.fuel_type || '-'}</td>
                        <td>${vehicle.manufacturing_year || '-'}</td>
                        <td>${vehicle.insurance_valid_upto ? new Date(vehicle.insurance_valid_upto).toLocaleDateString() : '-'}</td>
                        <td>
                            <span class="badge ${isActive ? 'bg-success' : 'bg-danger'} vehicle-status-badge">
                                ${isActive ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        // Export functionality
        $("#exportButton").on("click", function() {
            var minVehicles = $("#filterMinVehicles").val() || 2;
            var vehicleType = $("#filterVehicleType").val();
            var url = "{{ url('export/employees-multiple-vehicles') }}";
            var params = [];
            if (minVehicles) params.push(`min_vehicles=${minVehicles}`);
            if (vehicleType) params.push(`vehicle_type=${vehicleType}`);
            if (params.length) url += "?" + params.join("&");
            window.location.href = url;
        });

        $("#exportEmployeeVehicles").on("click", function() {
            var employeeId = $("#modalEmployeeName").text();
            var url = "{{ url('export/employee-vehicles') }}/" + employeeId;
            window.location.href = url;
        });

        $("#refreshButton").on("click", function() {
            loadVehicleData();
        });

        // Initial load
        loadVehicleData();
    });
</script>
@endpush
