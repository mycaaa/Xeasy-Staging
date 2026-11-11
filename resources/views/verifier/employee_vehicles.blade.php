@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="card-title mb-0">
                                <i class="ri-roadster-line me-1 text-primary"></i> Employee Vehicles
                            </h4>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="status" id="statusAll"
                                            value="">
                                        <label class="form-check-label fs-12" for="statusAll">All</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="status" id="statusActive"
                                            value="A" checked>
                                        <label class="form-check-label fs-12 text-success fw-medium" for="statusActive">Active</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="status" id="statusInactive"
                                            value="D">
                                        <label class="form-check-label fs-12 text-danger fw-medium" for="statusInactive">Inactive</label>
                                    </div>
                                </div>
                                <div>
                                    <select class="select2" id="selDepartment" name="department" style="width:180px"
                                        data-placeholder="All Departments">
                                        <option value=""></option>
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <select class="select2" id="selPolicy" name="policy" style="width:200px"
                                        data-placeholder="All Policies">
                                        <option value=""></option>
                                        @foreach ($eligibility_policy as $pol)
                                            <option value="{{ $pol->PolicyId }}">{{ $pol->PolicyName }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Column / Options Selector Dropdown --}}
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill d-flex align-items-center gap-1"
                                        type="button" id="columnToggleDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        aria-expanded="false">
                                        <i class="ri-table-line text-primary"></i> Columns
                                        <span class="badge bg-primary-subtle text-primary rounded-pill ms-1" id="visibleColCount">14</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg" style="width: 330px; max-height: 480px; overflow-y: auto;">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                            <h6 class="mb-0 fs-13 fw-semibold text-dark">
                                                <i class="ri-checkbox-multiple-line me-1 text-primary"></i> Visible Columns
                                            </h6>
                                            <div class="d-flex gap-2">
                                                <a href="javascript:void(0)" class="fs-11 text-primary fw-medium" id="btnSelectAllCols">Select All</a>
                                                <span class="text-muted">|</span>
                                                <a href="javascript:void(0)" class="fs-11 text-muted" id="btnResetCols">Reset</a>
                                            </div>
                                        </div>

                                        {{-- Groups of checkboxes --}}
                                        @php
                                            $groupedCols = [];
                                            foreach ($columnsConfig as $k => $c) {
                                                $groupedCols[$c['group']][$k] = $c;
                                            }
                                        @endphp

                                        @foreach ($groupedCols as $groupName => $cols)
                                            <div class="mb-2">
                                                <div class="text-uppercase fs-11 fw-bold text-muted bg-light px-2 py-1 rounded mb-1">
                                                    {{ $groupName }}
                                                </div>
                                                <div class="px-1">
                                                    @foreach ($cols as $colKey => $col)
                                                        @if ($colKey !== 'sr_no')
                                                            <div class="form-check form-check-sm mb-1">
                                                                <input class="form-check-input col-toggle-chk" type="checkbox"
                                                                    id="col_chk_{{ $colKey }}" value="{{ $colKey }}"
                                                                    data-col="{{ $colKey }}"
                                                                    {{ $col['default'] ? 'checked' : '' }}>
                                                                <label class="form-check-label fs-12 text-secondary" for="col_chk_{{ $colKey }}">
                                                                    {{ $col['label'] }}
                                                                </label>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Export Button (opens export options modal) --}}
                                <button type="button" class="btn btn-success btn-sm btn-label waves-effect waves-light rounded-pill"
                                    id="openExportModalBtn">
                                    Export Excel
                                    <i class="ri-file-excel-line label-icon align-middle rounded-pill fs-15 me-2"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body pb-3 pt-2">
                            <div class="table-responsive">
                                <table id="vehicleTable" class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                    style="width:100%">
                                    <thead class="table-light">
                                        <tr class="text-center">
                                            <th class="text-center align-middle">Sr. No</th>
                                            <th class="text-center">EC</th>
                                            <th class="text-center">Emp Name</th>
                                            <th class="text-center">Department</th>
                                            <th class="text-center">Grade</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Purchase Date</th>
                                            <th class="text-center">Old / New</th>
                                            <th class="text-center">Policy Entry Date</th>
                                            <th class="text-center">Reg Date</th>
                                            <th class="text-center">Vehicle Age</th>
                                            <th class="text-center">Vehicle Type</th>
                                            <th class="text-center">Reg No</th>
                                            <th class="text-center">Brand & Model</th>
                                            <th class="text-center">Fuel Type</th>
                                            <th class="text-center">Current Vehicle Value</th>
                                            <th class="text-center">Policy Name</th>
                                            <th class="text-center">Plan</th>
                                            <th class="text-center">Flat 2W</th>
                                            <th class="text-center">Flat 4W</th>
                                            <th class="text-center">Limit 2W KM</th>
                                            <th class="text-center">Limit 4W KM</th>
                                            <th class="text-center">Pol Vehicle Value</th>
                                            <th class="text-center">Policy Tenure</th>
                                            <th class="text-center">Max KM / Month</th>
                                            <th class="text-center">Current Rate</th>
                                            <th class="text-center">Rate Reason / Rule</th>
                                            <th class="text-center">With Driver</th>
                                            <th class="text-center">Advance Company</th>
                                            <th class="text-center">Doc Summary</th>
                                            <th class="text-center">RC File</th>
                                            <th class="text-center">Insurance</th>
                                            <th class="text-center">DL File</th>
                                            <th class="text-center">Photo</th>
                                            <th class="text-center">Odometer</th>
                                            <th class="text-center">Bill File</th>
                                            <th class="text-center align-middle">Action</th>
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

    {{-- Export Options Modal --}}
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-15 text-white">
                        <i class="ri-file-excel-2-line me-1"></i> Export Employee Vehicles to Excel
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="exportForm" method="POST" action="{{ route('employee.vehicles.export') }}">
                    @csrf
                    <input type="hidden" name="department" id="exportDepartment">
                    <input type="hidden" name="policy" id="exportPolicy">
                    <input type="hidden" name="status" id="exportStatus">

                    <div class="modal-body p-3">
                        <div class="alert alert-info py-2 px-3 fs-12 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <i class="ri-information-line me-1"></i>
                                Select the fields you want to include in the exported Excel spreadsheet.
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" id="exportSelectAll">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" id="exportDeselectAll">Deselect All</button>
                                <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 fs-11" id="exportMatchTable">Match Table View</button>
                            </div>
                        </div>

                        <div class="row g-3">
                            @foreach ($groupedCols as $groupName => $cols)
                                <div class="col-md-6">
                                    <div class="card border mb-0 h-100 shadow-none">
                                        <div class="card-header bg-light py-1 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-semibold fs-12 text-dark">{{ $groupName }}</span>
                                            <a href="javascript:void(0)" class="fs-11 text-primary group-select-toggle" data-group="{{ Str::slug($groupName) }}">Toggle</a>
                                        </div>
                                        <div class="card-body p-2 group-chk-container" id="group_{{ Str::slug($groupName) }}">
                                            @foreach ($cols as $colKey => $col)
                                                <div class="form-check form-check-sm mb-1 ms-2">
                                                    <input class="form-check-input export-col-chk" type="checkbox"
                                                        name="columns[]" id="export_col_{{ $colKey }}" value="{{ $colKey }}"
                                                        {{ $col['default'] ? 'checked' : '' }}>
                                                    <label class="form-check-label fs-12" for="export_col_{{ $colKey }}">
                                                        {{ $col['label'] }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm btn-label waves-effect waves-light rounded-pill">
                            <i class="ri-download-2-line label-icon align-middle rounded-pill fs-15 me-2"></i>
                            Download Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Upgraded Policy Details Modal --}}
    <div class="modal fade" id="policyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-15 text-white">
                        <i class="ri-file-list-3-line me-1"></i> Employee Vehicle Policy & Eligibility Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3" id="policyModalBody">
                    <div class="text-center py-5">
                        <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                        <p class="mt-2 text-muted">Loading complete policy details...</p>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Documents Modal --}}
    <div class="modal fade" id="docsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header mb-2">
                    <div>
                        <h5 class="modal-title"><i class="ri-file-text-line text-info me-1"></i> Vehicle Documents</h5>
                        <small class="text-muted" id="employeeInfo"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-1">
                    <ul class="nav nav-tabs nav-border-top nav-border-top-primary" id="docsTab" role="tablist">
                    </ul>
                    <div class="tab-content p-3" id="docsTabContent">
                        <div id="docsLoading" class="text-center py-5">
                            <i class="fa fa-spinner fa-spin fa-3x text-success"></i>
                            <p class="mt-3">Loading documents...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        #vehicleTable thead th {
            text-align: center !important;
            vertical-align: middle !important;
            font-size: 12px;
            white-space: nowrap;
            padding: 8px 10px;
        }
        #vehicleTable tbody td {
            font-size: 12px;
            vertical-align: middle !important;
            white-space: nowrap;
        }
        .col-toggle-chk:checked + label {
            font-weight: 500;
            color: #1F4E78 !important;
        }
        .policy-section-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
    </style>
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('vendor/cdn/moment@2.29.4/min/moment.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            let table = null;

            // Column key to DataTable column index mapping
            const colIndexMap = {
                'sr_no': 0,
                'emp_code': 1,
                'emp_name': 2,
                'department': 3,
                'grade': 4,
                'emp_status': 5,
                'purchase_date': 6,
                'oldnew': 7,
                'policyentrydate': 8,
                'registration_date': 9,
                'vehicle_age': 10,
                'vehicle_type': 11,
                'reg_no': 12,
                'brand_model': 13,
                'fuel_type': 14,
                'vehicle_price': 15,
                'policy_name': 16,
                'plan': 17,
                'flat_2w': 18,
                'flat_4w': 19,
                'limit_2w_km': 20,
                'limit_4w_km': 21,
                'policy_vehicle_value': 22,
                'policy_tenure': 23,
                'max_km_month': 24,
                'reimbursement_rate': 25,
                'rate_reason': 26,
                'with_driver': 27,
                'advance_com': 28,
                'doc_status': 29,
                'rc_check': 30,
                'ins_check': 31,
                'dl_check': 32,
                'img_check': 33,
                'odo_check': 34,
                'bill_check': 35,
                'view': 36
            };

            const defaultVisibleCols = [
                'sr_no', 'emp_code', 'emp_name', 'grade', 'emp_status',
                'oldnew', 'policyentrydate', 'registration_date', 'vehicle_age',
                'vehicle_type', 'reg_no', 'vehicle_price', 'policy_name',
                'plan', 'flat_2w', 'flat_4w', 'reimbursement_rate', 'rate_reason',
                'doc_status', 'view'
            ];

            // Get saved visible columns from localStorage or default
            function getSavedVisibleCols() {
                try {
                    const saved = localStorage.getItem('employee_vehicles_visible_cols_v2');
                    if (saved) {
                        return JSON.parse(saved);
                    }
                } catch (e) {}
                return defaultVisibleCols;
            }

            function saveVisibleCols(cols) {
                try {
                    localStorage.setItem('employee_vehicles_visible_cols_v2', JSON.stringify(cols));
                } catch (e) {}
            }

            $(".select2").select2({
                allowClear: true,
                placeholder: function() {
                    return $(this).data("placeholder");
                },
            });

            function updateVisibleCountBadge() {
                let count = $(".col-toggle-chk:checked").length + 2; // + Sr.No and View
                $("#visibleColCount").text(count);
            }

            function initVehicleTable() {
                if ($.fn.DataTable.isDataTable("#vehicleTable")) {
                    $("#vehicleTable").DataTable().clear().destroy();
                }

                table = $("#vehicleTable").DataTable({
                    processing: true,
                    serverSide: true,
                    ordering: false,
                    scrollX: true,
                    ajax: {
                        url: "{{ route('employee.vehicles.data') }}",
                        type: "POST",
                        data: function(d) {
                            d._token = "{{ csrf_token() }}";
                            d.department = $("#selDepartment").val();
                            d.policy = $("#selPolicy").val();
                            d.status = $("input[name='status']:checked").val();
                        }
                    },
                    columns: [
                        // 0: Sr. No
                        {
                            data: null,
                            className: "text-center",
                            searchable: false,
                            orderable: false,
                            render: function(data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        // 1: EC
                        { data: "EmpCode", className: "text-center" },
                        // 2: Name
                        { data: "Name", className: "text-start fw-medium" },
                        // 3: Department
                        { data: "department_name", className: "text-start", defaultContent: "" },
                        // 4: Grade
                        { data: "grade_name", className: "text-center", defaultContent: "" },
                        // 5: Status
                        {
                            data: "EmpStatus",
                            className: "text-center",
                            render: function(data) {
                                return data === 'A'
                                    ? '<span class="badge bg-success-subtle text-success">Active</span>'
                                    : '<span class="badge bg-danger-subtle text-danger">Inactive</span>';
                            }
                        },
                        // 6: Purchase Date
                        {
                            data: "purchase_date",
                            className: "text-center",
                            render: d => d ? moment(d).format('DD-MM-YYYY') : ''
                        },
                        // 7: Old / New
                        { data: "oldnew", className: "text-center", defaultContent: "" },
                        // 8: Policy Entry Date
                        {
                            data: "policyentrydate",
                            className: "text-center",
                            render: d => d ? moment(d).format('DD-MM-YYYY') : ''
                        },
                        // 9: Registration Date
                        {
                            data: "registration_date",
                            className: "text-center",
                            render: d => d ? moment(d).format('DD-MM-YYYY') : ''
                        },
                        // 10: Vehicle Age
                        { data: "vehicle_age", className: "text-center", defaultContent: "" },
                        // 11: Vehicle Type
                        { data: "vehicle_type", className: "text-center", defaultContent: "" },
                        // 12: Reg No
                        {
                            data: "registration_no",
                            className: "text-center",
                            render: d => d ? `<code>${d}</code>` : ''
                        },
                        // 13: Brand & Model
                        { data: "brand_model", className: "text-start", defaultContent: "" },
                        // 14: Fuel Type
                        {
                            data: "fuel_type",
                            className: "text-center",
                            render: d => d ? d.charAt(0).toUpperCase() + d.slice(1) : ''
                        },
                        // 15: Vehicle Value
                        { data: "vehicle_price_formatted", className: "text-end", defaultContent: "" },
                        // 16: Policy Name
                        { data: "PolicyName", className: "text-start", defaultContent: "" },
                        // 17: Plan
                        { data: "Plan", className: "text-center", defaultContent: "" },
                        // 18: Flat 2W
                        { data: "flat_2w", className: "text-center", defaultContent: "" },
                        // 19: Flat 4W
                        { data: "flat_4w", className: "text-center", defaultContent: "" },
                        // 20: Limit 2W KM
                        { data: "limit_2w_km", className: "text-center", defaultContent: "" },
                        // 21: Limit 4W KM
                        { data: "limit_4w_km", className: "text-center", defaultContent: "" },
                        // 22: Policy Vehicle Value
                        { data: "policy_vehicle_value", className: "text-end", defaultContent: "" },
                        // 23: Policy Tenure
                        { data: "policy_tenure", className: "text-center", defaultContent: "" },
                        // 24: Max KM / Month
                        { data: "max_km_month", className: "text-center", defaultContent: "" },
                        // 25: Current Rate
                        { data: "reimbursement_rate", className: "text-center", defaultContent: "" },
                        // 26: Rate Reason / Rule
                        { data: "rate_reason", className: "text-center", defaultContent: "" },
                        // 27: With Driver
                        { data: "WithDriver", className: "text-center", defaultContent: "" },
                        // 28: Advance Company
                        { data: "AdvanceCom", className: "text-center", defaultContent: "" },
                        // 29: Doc Summary
                        { data: "doc_status", className: "text-center", orderable: false, searchable: false },
                        // 30: RC Check
                        { data: "rc_check", className: "text-center", orderable: false, searchable: false },
                        // 31: Ins Check
                        { data: "ins_check", className: "text-center", orderable: false, searchable: false },
                        // 32: DL Check
                        { data: "dl_check", className: "text-center", orderable: false, searchable: false },
                        // 33: Photo Check
                        { data: "img_check", className: "text-center", orderable: false, searchable: false },
                        // 34: Odo Check
                        { data: "odo_check", className: "text-center", orderable: false, searchable: false },
                        // 35: Bill Check
                        { data: "bill_check", className: "text-center", orderable: false, searchable: false },
                        // 36: View / Action
                        { data: "view", className: "text-center", searchable: false, orderable: false }
                    ],
                    pageLength: 10,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, "All"]
                    ],
                    searching: true,
                    info: true,
                    dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
                    drawCallback: function() {
                        $('[data-bs-toggle="tooltip"]').tooltip();
                    },
                    initComplete: function() {
                        applyColumnVisibility();
                    }
                });
            }

            // Apply column visibility based on checkboxes
            function applyColumnVisibility() {
                if (!table) return;

                const savedCols = getSavedVisibleCols();

                // Update checkboxes to match
                $(".col-toggle-chk").each(function() {
                    const colKey = $(this).val();
                    const isVisible = savedCols.includes(colKey);
                    $(this).prop('checked', isVisible);
                    const idx = colIndexMap[colKey];
                    if (idx !== undefined) {
                        table.column(idx).visible(isVisible, false);
                    }
                });

                // Sr. No and View are always visible
                table.column(colIndexMap['sr_no']).visible(true, false);
                table.column(colIndexMap['view']).visible(true, false);

                table.columns.adjust().draw(false);
                updateVisibleCountBadge();
            }

            // Handle individual column toggle checkbox change
            $(document).on("change", ".col-toggle-chk", function() {
                const colKey = $(this).val();
                const isChecked = $(this).is(":checked");
                const idx = colIndexMap[colKey];

                if (idx !== undefined && table) {
                    table.column(idx).visible(isChecked, false);
                    table.columns.adjust().draw(false);
                }

                // Save current state
                let currentCols = ['sr_no', 'view'];
                $(".col-toggle-chk:checked").each(function() {
                    currentCols.push($(this).val());
                });
                saveVisibleCols(currentCols);
                updateVisibleCountBadge();
            });

            // "Select All" columns in dropdown
            $("#btnSelectAllCols").on("click", function() {
                $(".col-toggle-chk").prop("checked", true);
                let currentCols = ['sr_no', 'view'];
                $(".col-toggle-chk").each(function() {
                    const colKey = $(this).val();
                    currentCols.push(colKey);
                    const idx = colIndexMap[colKey];
                    if (idx !== undefined && table) {
                        table.column(idx).visible(true, false);
                    }
                });
                table.columns.adjust().draw(false);
                saveVisibleCols(currentCols);
                updateVisibleCountBadge();
            });

            // "Reset" columns to default
            $("#btnResetCols").on("click", function() {
                saveVisibleCols(defaultVisibleCols);
                applyColumnVisibility();
            });

            initVehicleTable();

            $("#selDepartment, #selPolicy").on("change", function() {
                initVehicleTable();
            });

            $("input[name='status']").on("change", function() {
                initVehicleTable();
            });

            // Open Export Modal
            $("#openExportModalBtn").on("click", function() {
                $("#exportDepartment").val($("#selDepartment").val());
                $("#exportPolicy").val($("#selPolicy").val());
                $("#exportStatus").val($("input[name='status']:checked").val());
                $("#exportModal").modal("show");
            });

            // Export Modal - Select All
            $("#exportSelectAll").on("click", function() {
                $(".export-col-chk").prop("checked", true);
            });

            // Export Modal - Deselect All
            $("#exportDeselectAll").on("click", function() {
                $(".export-col-chk").prop("checked", false);
            });

            // Export Modal - Match Current Table View
            $("#exportMatchTable").on("click", function() {
                $(".export-col-chk").each(function() {
                    const colKey = $(this).val();
                    const isTableVisible = $(`#col_chk_${colKey}`).is(":checked");
                    $(this).prop("checked", isTableVisible);
                });
            });

            // Group toggle in export modal
            $(".group-select-toggle").on("click", function() {
                const groupSlug = $(this).data("group");
                const chks = $(`#group_${groupSlug} .export-col-chk`);
                const allChecked = chks.filter(":checked").length === chks.length;
                chks.prop("checked", !allChecked);
            });

            // View Policy Details click handler
            $(document).on("click", ".view-policy", function() {
                let empId = $(this).data("empid") || $(this).attr("data-empid");
                let vehicleId = $(this).data("vehicleid") || $(this).attr("data-vehicleid");
                $("#policyModal").modal("show");
                $("#policyModalBody").html(
                    '<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x text-primary"></i><p class="mt-2 text-muted">Loading policy details...</p></div>'
                );

                $.ajax({
                    url: "{{ route('employee.vehicles.policy.details') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        empid: empId,
                        vehicleid: vehicleId
                    },
                    success: function(res) {
                        if (res.success) {
                            const emp = res.employee;
                            const ra = res.rate_analysis || {};

                            let ageStatusBadge = '';
                            if (ra.is_over_max_age) {
                                ageStatusBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="ri-alert-line me-1"></i>Exceeds ${ra.max_age_limit}-Yr Policy Limit</span>`;
                            } else {
                                ageStatusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ri-checkbox-circle-line me-1"></i>Within ${ra.max_age_limit}-Yr Policy Limit</span>`;
                            }

                            let valueStatusBadge = '';
                            if (ra.is_value_downgraded) {
                                valueStatusBadge = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="ri-alert-line me-1"></i>Grade Adjusted</span>`;
                            } else if (ra.policy_vehicle_value) {
                                valueStatusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ri-check-line me-1"></i>Eligible</span>`;
                            } else {
                                valueStatusBadge = `<span class="badge bg-light text-muted">-</span>`;
                            }

                            let html = `
                                <!-- Valuation & Rate Analysis Summary Card -->
                                <div class="card border ${ra.is_value_downgraded ? 'border-warning-subtle' : 'border-primary-subtle'} shadow-sm mb-3">
                                    <div class="card-header ${ra.is_value_downgraded ? 'bg-warning-subtle' : 'bg-primary-subtle'} py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h6 class="mb-0 fs-13 fw-bold ${ra.is_value_downgraded ? 'text-warning-emphasis' : 'text-primary'}">
                                            <i class="ri-dashboard-3-line me-1"></i> Vehicle Age, Valuation & Rate Calculation Analysis
                                        </h6>
                                        <div>
                                            ${ra.is_value_downgraded ? `
                                                <span class="badge bg-warning text-dark fs-12 px-2 py-1 shadow-sm">
                                                    <i class="ri-arrow-down-circle-line me-1"></i>Applied Rate: ${ra.current_rate_formatted || 'N/A'}
                                                    <span class="opacity-75 ms-1">(Assigned: <span class="text-decoration-line-through">${ra.assigned_rate_formatted || ''}</span>)</span>
                                                </span>
                                            ` : `
                                                <span class="badge bg-primary text-white fs-12 px-2 py-1">
                                                    Current Rate: ${ra.current_rate_formatted || 'N/A'}
                                                </span>
                                            `}
                                        </div>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row g-3">
                                            <div class="col-md-3 col-6">
                                                <div class="border rounded p-2 bg-light h-100">
                                                    <small class="text-muted d-block fs-11 text-uppercase fw-semibold">Current Vehicle Value</small>
                                                    <span class="fs-15 fw-bold text-dark">${ra.vehicle_value_formatted || 'N/A'}</span>
                                                    <div class="mt-1 d-flex justify-content-between align-items-center">
                                                        <small class="text-muted fs-11">Policy Cap: ${ra.policy_vehicle_value_formatted || 'N/A'}</small>
                                                        ${valueStatusBadge}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <div class="border rounded p-2 bg-light h-100">
                                                    <small class="text-muted d-block fs-11 text-uppercase fw-semibold">Vehicle Age</small>
                                                    <span class="fs-15 fw-bold text-dark">${ra.vehicle_age_text || 'N/A'}</span>
                                                    <div class="mt-1">
                                                        <small class="text-muted fs-11">Reg: ${emp.registration_date || 'N/A'} (${ra.oldnew || 'new'})</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <div class="border rounded p-2 bg-light h-100">
                                                    <small class="text-muted d-block fs-11 text-uppercase fw-semibold">Tenure Threshold</small>
                                                    <span class="fs-15 fw-bold text-dark">${ra.max_age_limit || 7} Years Max Limit</span>
                                                    <div class="mt-1">
                                                        ${ageStatusBadge}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <div class="border rounded p-2 bg-light h-100">
                                                    <small class="text-muted d-block fs-11 text-uppercase fw-semibold">Applied Rate</small>
                                                    <div class="d-flex align-items-baseline gap-1">
                                                        <span class="fs-15 fw-bold text-primary">${ra.current_rate_formatted || 'N/A'}</span>
                                                    </div>
                                                    <div class="mt-1">
                                                        ${ra.is_value_downgraded ? `
                                                            <span class="badge bg-warning-subtle text-warning-emphasis fs-10 border border-warning-subtle" title="Assigned Grade Rate">
                                                                Assigned: <span class="text-decoration-line-through">${ra.assigned_rate_formatted}</span>
                                                            </span>
                                                        ` : `
                                                            <span class="badge bg-info-subtle text-info fs-11">${ra.rate_type || 'Standard'}</span>
                                                        `}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="alert ${ra.is_value_downgraded ? 'alert-warning bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'alert-info bg-info-subtle text-info-emphasis border-0'} py-2 px-3 mb-0 fs-12">
                                                    <strong><i class="${ra.is_value_downgraded ? 'ri-error-warning-line' : 'ri-information-line'} me-1"></i> Rate Calculation & Explanation:</strong>
                                                    ${ra.rate_reason || 'Standard rate configured in policy.'}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <!-- Employee & Vehicle Profile -->
                                    <div class="col-md-6">
                                        <div class="policy-section-card p-3 h-100">
                                            <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary">
                                                <i class="ri-user-star-line me-1"></i> Employee & Vehicle Profile
                                            </h6>
                                            <table class="table table-sm table-borderless mb-0 fs-12">
                                                <tr>
                                                    <td class="text-muted" width="40%">Employee Name:</td>
                                                    <td class="fw-semibold">${emp.name}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Employee Code:</td>
                                                    <td class="fw-semibold">${emp.emp_code}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Department:</td>
                                                    <td>${emp.department || '-'}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Grade:</td>
                                                    <td><span class="badge bg-light text-dark border">${emp.grade}</span></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Registration No:</td>
                                                    <td><code>${emp.reg_no}</code></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Brand & Model:</td>
                                                    <td>${emp.brand} ${emp.model ? '- ' + emp.model : ''}</td>
                                                </tr>
                                                ${emp.model_no && emp.model_no !== 'N/A' ? `
                                                <tr>
                                                    <td class="text-muted">Model / Variant:</td>
                                                    <td>${emp.model_no}</td>
                                                </tr>
                                                ` : ''}
                                                <tr>
                                                    <td class="text-muted">Fuel Type:</td>
                                                    <td>${emp.fuel_type}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Current Vehicle Value:</td>
                                                    <td><strong class="text-dark">${(emp.vehiclevalue && emp.vehiclevalue !== 'N/A') ? emp.vehiclevalue : (ra.vehicle_value_formatted || 'N/A')}</strong></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Policy Cap / Price:</td>
                                                    <td><span class="text-primary fw-medium">${(emp.price && emp.price !== 'N/A') ? emp.price : (ra.policy_vehicle_value_formatted || 'N/A')}</span></td>
                                                </tr>
                                                ${emp.bill_no && emp.bill_no !== 'N/A' ? `
                                                <tr>
                                                    <td class="text-muted">Bill / Invoice No:</td>
                                                    <td><code>${emp.bill_no}</code></td>
                                                </tr>
                                                ` : ''}
                                                <tr>
                                                    <td class="text-muted">Purchase Date:</td>
                                                    <td>${emp.purchase_date}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Policy & Eligibility Configuration -->
                                    <div class="col-md-6">
                                        <div class="policy-section-card p-3 h-100">
                                            <h6 class="border-bottom pb-2 mb-3 fw-bold text-success">
                                                <i class="ri-shield-check-line me-1"></i> Policy & Eligibility Configuration
                                            </h6>
                                            <table class="table table-sm table-borderless mb-0 fs-12">
                                                <tr>
                                                    <td class="text-muted" width="40%">Policy Name:</td>
                                                    <td class="fw-semibold text-primary">${emp.policy_name}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Vehicle Type:</td>
                                                    <td><span class="badge bg-info-subtle text-info">${emp.vehicle_type}</span></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Plan:</td>
                                                    <td>${emp.plan}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Policy Entry Date:</td>
                                                    <td>${emp.date_of_entry}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Flat 2W Rate:</td>
                                                    <td>${emp.flat_2w ? '₹ ' + emp.flat_2w + ' / KM' : 'N/A'}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Flat 4W Rate:</td>
                                                    <td>${emp.flat_4w ? '₹ ' + emp.flat_4w + ' / KM' : 'N/A'}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Monthly Limit 2W KM:</td>
                                                    <td>${emp.limit_2w_km || 'N/A'}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Monthly Limit 4W KM:</td>
                                                    <td>${emp.limit_4w_km || 'N/A'}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">With Driver:</td>
                                                    <td>${emp.with_driver || 'N/A'}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">Advance Company:</td>
                                                    <td>${emp.advance_com || 'N/A'}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Document Submission Checklist -->
                                <div class="card border mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 fs-13 fw-semibold text-dark">
                                            <i class="ri-attachment-2 me-1 text-primary"></i> Vehicle Document Submission Checklist
                                        </h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="row g-2">
                            `;

                            for (const [docName, isUploaded] of Object.entries(res.documents)) {
                                const badgeCls = isUploaded ? 'bg-success text-white' : 'bg-danger text-white';
                                const icon = isUploaded ? 'ri-check-line' : 'ri-close-line';
                                const statusText = isUploaded ? 'Uploaded' : 'Missing';
                                html += `
                                    <div class="col-md-2 col-4 text-center">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="fs-12 fw-medium text-truncate" title="${docName}">${docName}</div>
                                            <span class="badge ${badgeCls} mt-1 fs-11">
                                                <i class="${icon}"></i> ${statusText}
                                            </span>
                                        </div>
                                    </div>
                                `;
                            }

                            html += `
                                        </div>
                                    </div>
                                </div>

                                <!-- General Policy Rules -->
                                <div class="card border mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 fs-13 fw-semibold text-dark">
                                            <i class="ri-scales-3-line me-1 text-info"></i> General Policy Rules & Limits
                                        </h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-striped table-hover mb-0 fs-12">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="text-align: left;" width="65%">Policy Field</th>
                                                    <th class="text-center" width="35%">Configured Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                            `;

                            if (res.general_fields && res.general_fields.length > 0) {
                                res.general_fields.forEach(function(field) {
                                    html += `
                                        <tr>
                                            <td style="text-align: left;" class="fw-medium">${field.field_name}</td>
                                            <td class="text-center fw-semibold text-primary">${field.field_value}</td>
                                        </tr>
                                    `;
                                });
                            } else {
                                html += `
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">No general policy fields found</td>
                                    </tr>
                                `;
                            }

                            html += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            `;

                            // Rate Slabs section if applicable
                            if (res.rate_slabs && res.rate_slabs.length > 0) {
                                html += `
                                    <div class="card border mb-0">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="mb-0 fs-13 fw-semibold text-dark">
                                                <i class="ri-percent-line me-1 text-warning"></i> Reimbursement Rates & Slabs
                                            </h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <table class="table table-bordered table-striped table-hover mb-0 fs-12">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="text-align: left;" width="65%">Slab Description</th>
                                                        <th class="text-center" width="35%">Rate Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                `;

                                res.rate_slabs.forEach(function(field) {
                                    html += `
                                        <tr>
                                            <td style="text-align: left;" class="fw-medium">${field.field_name}</td>
                                            <td class="text-center fw-semibold text-success">${field.field_value}</td>
                                        </tr>
                                    `;
                                });

                                html += `
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                `;
                            }

                            $("#policyModalBody").html(html);
                        } else {
                            $("#policyModalBody").html(
                                `<div class="alert alert-warning">${res.message}</div>`
                            );
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Error loading policy details';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        $("#policyModalBody").html(
                            `<div class="alert alert-danger">${errorMsg}</div>`
                        );
                    }
                });
            });

            // View Documents click handler
            $(document).on("click", ".view-docs", function() {
                let empId = $(this).data("empid") || $(this).attr("data-empid");
                let vehicleId = $(this).data("vehicleid") || $(this).attr("data-vehicleid");

                $("#docsModal").modal("show");
                $("#docsTab").html("");
                $("#docsTabContent").html(`
                    <div id="docsLoading" class="text-center py-5">
                        <i class="fa fa-spinner fa-spin fa-3x text-success"></i>
                        <p class="mt-3">Loading documents...</p>
                    </div>
                `);

                $.ajax({
                    url: "{{ route('employee.vehicles.documents') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        empid: empId,
                        vehicleid: vehicleId
                    },
                    success: function(res) {
                        if (!res.success) {
                            $("#docsTabContent").html(
                                `<div class="alert alert-warning text-center">No documents found</div>`
                            );
                            return;
                        }

                        $("#employeeInfo").html(
                            `${res.employee.emp_code} - ${res.employee.emp_name}`
                        );

                        let tabsHtml = "";
                        let contentHtml = "";

                        res.available.forEach((doc, i) => {
                            let active = i === 0 ? "active" : "";
                            let show = i === 0 ? "show active" : "";

                            tabsHtml += `
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link ${active}" id="tab-${i}" data-bs-toggle="tab"
                                        data-bs-target="#content-${i}" type="button" role="tab">
                                        ${doc.label}
                                    </button>
                                </li>
                            `;

                            if (doc.is_image) {
                                contentHtml += `
                                    <div class="tab-pane fade ${show}" id="content-${i}" role="tabpanel">
                                        <div class="text-center">
                                            <img alt="Vehicle document" src="${doc.url}" class="img-fluid rounded border" style="max-height:350px;">
                                        </div>
                                        <div class="mt-3 text-center">
                                            <a href="${doc.url}" target="_blank" class="btn btn-primary btn-sm">
                                                <i class="ri-eye-line me-1"></i> View Full Image
                                            </a>
                                        </div>
                                    </div>
                                `;
                            } else {
                                contentHtml += `
                                    <div class="tab-pane fade ${show}" id="content-${i}" role="tabpanel">
                                        <div class="text-center py-4">
                                            <i class="ri-file-text-line ri-4x text-primary mb-3"></i>
                                            <p class="fs-13 fw-medium">${doc.label}</p>
                                            <a href="${doc.url}" target="_blank" class="btn btn-primary btn-sm">
                                                <i class="ri-eye-line me-1"></i> View Document
                                            </a>
                                        </div>
                                    </div>
                                `;
                            }
                        });

                        if (res.not_available.length > 0) {
                            tabsHtml += `
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link text-danger" id="tab-na" data-bs-toggle="tab"
                                        data-bs-target="#content-na" type="button" role="tab">
                                        Missing (${res.not_available.length})
                                    </button>
                                </li>
                            `;
                            let missingList = "";
                            res.not_available.forEach((label) => {
                                missingList += `<li class="text-danger">${label}</li>`;
                            });
                            contentHtml += `
                                <div class="tab-pane fade" id="content-na" role="tabpanel">
                                    <div class="alert alert-danger-subtle p-3">
                                        <h6 class="text-danger fw-bold mb-2">Missing Documents</h6>
                                        <ul class="mb-0">${missingList}</ul>
                                    </div>
                                </div>
                            `;
                        }

                        $("#docsTab").html(tabsHtml);
                        $("#docsTabContent").html(contentHtml);
                    },
                    error: function() {
                        $("#docsTabContent").html(
                            `<div class="alert alert-danger">Error loading documents</div>`
                        );
                    }
                });
            });
        });
    </script>
@endpush

