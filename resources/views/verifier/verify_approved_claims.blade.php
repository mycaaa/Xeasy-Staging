@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header d-flex justify-content-between align-items-center" id="dataPunchSection">
                            <x-verifier-screen-tab />
                            <div class="d-flex align-items-center gap-2">
                                <div class="form-check d-flex align-items-center mb-0">
                                    <input class="form-check-input me-1" type="radio" name="courier_status" id="optAll"
                                        value="all" checked>
                                    <label class="form-check-label" for="optAll">All</label>
                                </div>
                                <div class="form-check d-flex align-items-center mb-0">
                                    <input class="form-check-input me-1" type="radio" name="courier_status" id="optReceived"
                                        value="received">
                                    <label class="form-check-label" for="optReceived">Courier Received</label>
                                </div>
                                <div class="form-check d-flex align-items-center mb-0">
                                    <input class="form-check-input me-1" type="radio" name="courier_status"
                                        id="optNotReceived" value="not_received">
                                    <label class="form-check-label" for="optNotReceived">Not Received</label>
                                </div>
                                @if(in_array((int)session('company_id'), [11, 2], true))
                                <div class="form-check d-flex align-items-center mb-0">
                                    <input class="form-check-input me-1" type="radio" name="courier_status"
                                        id="optDmsReceived" value="dms_received">
                                    <label class="form-check-label" for="optDmsReceived">DMS Received</label>
                                </div>
                                @endif
                                <div>
                                    <input type="text" id="verifiedDateRange" class="form-control"
                                        placeholder="Verified Date Range" aria-label="Verified Date Range" style="width:120px;">
                                </div>
                                <div>
                                    <select class="select2" id="selDepartment" name="department" style="width:120px"
                                        data-placeholder="Select Department">
                                        <option value=""></option>
                                    </select>
                                </div>
                                <div>
                                    <select class="select2" id="selMonth" name="month" style="width:130px"
                                        data-placeholder="Select Month">
                                        <option value=""></option>
                                    </select>
                                </div>
                                <div>
                                    <select class="select2" id="selEmployee" name="employee" style="width:150px"
                                        data-placeholder="Select Employee">
                                        <option value=""></option>
                                    </select>
                                </div>
                                @php
                                    if (session('company_id') == 11) {
                                @endphp
                                <div>
                                    <a class="btn btn-sm btn-success release-btn" onclick="exportSelectedPaymentsOnly()">
                                        <i class="fa fa-btn" aria-hidden="true"></i>
                                        <span style="color:#FFFFFF;">Focus Export</span>
                                    </a>
                                </div>
                                @php
                                    }

                                @endphp



                            </div>
                        </div>
                        <div class="card-body pb-3 pt-2">

                            <table style="margin-top: 15px" id="ApprovedClaimsTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        @if(session('company_id') == 11)
                                            <th style="width: 50px">
                                                <input type="checkbox" id="checkAll">
                                            </th>
                                        @endif
                                        <th>Sn</th>
                                        <th>Code</th>
                                        <th>Claimant</th>
                                        <th>Check</th>
                                        <th>Month</th>
                                        <th>Submission Date</th>
                                        <th>Claims</th>
                                        <th>Claim Amount</th>
                                        <th>Verify Amount</th>
                                        <th>Verify Date</th>
                                        <th>Actions</th>
                                        <th>Courier Detail</th>
                                    </tr>
                                </thead>
                                @can('verify_approved_list')
                                    <tbody>
                                    </tbody>
                                @else
                                    <tbody>
                                        <tr>
                                            <td colspan="12" class="text-center text-danger fw-bold py-3">
                                                You do not have permission to view approved claims.
                                            </td>
                                        </tr>
                                    </tbody>
                                @endcan
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="courierModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="progress-container" style="display: none">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header">
                    <h5 class="modal-title">Courier Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="courierModalBody">
                    <div class="text-center">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="viewClaimsModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="w-100">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div>
                                <h5 class="modal-title mb-0" id="viewClaimsTitle">
                                    <span id="employeeClaimInfo" class="text-primary fw-bold"></span>
                                    <a href="javascript:void(0);"
                                       id="btnViewEmpKm"
                                       onclick="openViewEmpKm()"
                                       class="text-success ms-2"
                                       title="View Running KM"
                                       style="display:none;">
                                        <i class="ri-roadster-line" style="font-size:18px;vertical-align:middle;"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       id="btnViewAttd"
                                       onclick="openViewAttd()"
                                       class="text-primary ms-2"
                                       title="Attendance Calendar"
                                       style="display:none;">
                                        <i class="ri-eye-line" style="font-size:18px;vertical-align:middle;"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       id="btnViewElig"
                                       onclick="openViewElig()"
                                       class="text-info ms-2"
                                       title="Open Eligibility"
                                       style="display:none;">
                                        <i class="ri-file-list-3-line" style="font-size:18px;vertical-align:middle;"></i>
                                    </a>
                                </h5>
                                <div id="employeeMetaInfo" class="d-flex align-items-center gap-2 mt-1" style="font-size:12px;"></div>
                            </div>
                            <div id="claimSummaryHeader"
                                class="p-1 bg-info bg-opacity-50 rounded small d-flex flex-wrap align-items-center gap-2"
                                style="display:none; max-width: 60%;background-color: rgb(41 156 219 / 16%) !important;">
                                <div><strong>Total Claimed:</strong> <span id="h_totalClaimedAmt">₹0</span></div>
                                <div><strong>Total Verified:</strong> <span id="h_totalVerifiedAmt">₹0</span></div>
                                <div><strong>Submission:</strong> <span id="h_submissionDate">N/A</span></div>
                                <div><strong>Last Claim:</strong> <span id="h_lastClaimMonth">N/A</span>/<span
                                        id="h_lastMonthClaimAmount">₹0</span></div>
                                <div><strong>Courier:</strong> <span id="h_courierDetail">N/A</span></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="claimsTableContainer">
                        <div class="text-center py-5">
                            <i class="fa fa-spinner fa-spin fa-3x"></i>
                            <p class="mt-3">Loading claims...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.view_claim_detail />
    @include('components.modals.employee-running-km')
@endsection
@push('styles')
    <style>
        #ApprovedClaimsTable thead th {
            text-align: center !important;
        }
    </style>
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script>
        function openPopUpWindow(url) {
            window.open(
                url,
                "_blank",
                "toolbar=no,scrollbars=yes,resizable=yes,top=80,left=200,width=800,height=450"
            );
        }

        // Global wrappers — read currentViewEmpId/Month from window scope (set below in ready())
        function openViewAttd() {
            var empId = window._currentViewEmpId;
            var month = window._currentViewMonth;
            var year  = window._currentViewYear || new Date().getFullYear();
            if (!empId) return;
            openPopUpWindow(
                'https://vnrseeds.co.in/public/api/Att_records?empid=' + empId +
                '&Month=' + month + '&Year=' + year
            );
        }

        function openViewElig() {
            var empId = window._currentViewEmpId;
            if (!empId) return;
            openPopUpWindow('https://vnrseeds.co.in/public/api/Eligibility_records?empid=' + empId);
        }

        function openViewEmpKm() {
            var empId = window._currentViewEmpId;
            if (!empId) return;
            openPopUpWindow('{{ url("employee-running-km-view") }}/' + empId);
        }
        $(document).ready(function () {

            if ($.fn.select2) {
                $(".select2").select2({
                    allowClear: true,
                    placeholder: function () {
                        return $(this).data("placeholder");
                    },
                });
            }

            setTimeout(function () {
                var infoIcon = document.getElementById("colorInfo");
                if (infoIcon && typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                    new bootstrap.Popover(infoIcon, {
                        html: true,
                        sanitize: false,
                    });
                }
            }, 500);

            function initApprovedClaimsTable() {
                const companyId = {{ session('company_id') ?? 0 }};
                const isCompany11 = (companyId === 11);
                let dateRange = $("#verifiedDateRange").val();

                if ($.fn.DataTable.isDataTable("#ApprovedClaimsTable")) {
                    $("#ApprovedClaimsTable").DataTable().destroy();
                }

                // Build columns array dynamically
                let columns = [];

                // Add checkbox column first if company is 11
                if (isCompany11) {
                    columns.push({
                        data: "checkbox",
                        name: "checkbox",
                        orderable: false,
                        searchable: false,
                        className: "text-center",
                        render: function (data, type, row) {
                            // If data is already provided from server, use it
                            if (data && data !== '') {
                                return data;
                            }
                            // Otherwise return a clean checkbox HTML
                            return '<input type="checkbox" class="row-check" value="">';
                        }
                    });
                }

                // Add remaining columns
                columns.push(
                    { data: "DT_RowIndex", name: "DT_RowIndex", className: "text-center", orderable: false, searchable: false },
                    { data: "EmpCode", name: "EmpCode", className: "text-center", orderable: true },
                    { data: "Name", name: "Name", orderable: true },
                    { data: "Check", name: "Check", className: "text-center", orderable: false, searchable: false },
                    { data: "Month", name: "Month", className: "text-center", orderable: true },
                    { data: "DateOfSubmit", name: "DateOfSubmit", className: "text-center", orderable: true },
                    { data: "Total_Claim", name: "Total_Claim", className: "text-center", orderable: true },
                    { data: "Claim_Amount", name: "Claim_Amount", className: "text-center", orderable: true },
                    { data: "Verified_Amount", name: "Verified_Amount", className: "text-center", orderable: true },
                    { data: "Verified_Date", name: "Verified_Date", className: "text-center", orderable: true },
                    { data: "Actions", name: "Actions", orderable: false, searchable: false, className: "text-center" },
                    { data: "Courier_Detail", name: "Courier_Detail", className: "text-center", orderable: false, searchable: false }
                );

                $("#ApprovedClaimsTable").DataTable({
                    processing: true,
                    serverSide: true,
                    ordering: true,
                    order: [],
                    ajax: {
                        url: "{{ route('verify.approved.claims.data') }}",
                        type: "POST",
                        data: function (d) {
                            d._token = "{{ csrf_token() }}";
                            d.courier_status = $("input[name='courier_status']:checked").val() || 'all';
                            d.department = $("#selDepartment").val() || '';
                            d.month = $("#selMonth").val() || '';
                            d.employee = $("#selEmployee").val() || '';
                            if (dateRange) {
                                let dates = dateRange.includes(" to ")
                                    ? dateRange.split(" to ")
                                    : dateRange.split(" - ");
                                d.from_date = dates[0] || '';
                                d.to_date = dates[1] || '';
                            } else {
                                d.from_date = '';
                                d.to_date = '';
                            }

                        },
                        dataSrc: function (json) {
                            if (json && json.months && json.employees && json.departments) {
                                updateFilterDropdowns(json.months, json.employees, json.departments);
                            }
                            return json.data || [];
                        },
                        error: function (xhr, error, thrown) {
                            console.error('DataTables error:', error);
                        }
                    },
                    columns: columns,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: false,
                    searching: true,
                    info: true,
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                    drawCallback: function () {
                        if (isCompany11) {
                            // Reinitialize select all after each draw
                            $('#checkAll').off('change').on('change', function () {
                                $('.row-check').prop('checked', $(this).prop('checked'));
                            });

                            // Update select all checkbox when individual checkboxes change
                            $('.row-check').off('change').on('change', function () {
                                const totalCheckboxes = $('.row-check').length;
                                const checkedCheckboxes = $('.row-check:checked').length;
                                $('#checkAll').prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
                            });
                        }
                    }
                });
            }

            $("#verifiedDateRange").on("change", function () {
                initApprovedClaimsTable();
            });
            // Function to get selected rows
            function getSelectedRows() {
                let selectedRows = [];
                $('.row-check:checked').each(function () {
                    selectedRows.push({
                        id: $(this).val(),
                        employee_id: $(this).data('emp'),
                        amount: $(this).data('amount'),
                        month: $(this).data('month')
                    });
                });
                return selectedRows;
            }


            function updateFilterDropdowns(months, employees, departments) {
                const currentDepartment = $("#selDepartment").val();
                const currentMonth = $("#selMonth").val();
                const currentEmployee = $("#selEmployee").val();

                $("#selDepartment").empty().append('<option value=""></option>');
                if (departments && departments.length) {
                    departments.forEach(function (dept) {
                        $("#selDepartment").append('<option value="' + dept.value + '" ' + (
                            currentDepartment ==
                                dept.value ? "selected" : "") + '>' + dept.text + '</option>');
                    });
                }

                $("#selMonth").empty().append('<option value=""></option>');
                if (months && months.length) {
                    months.forEach(function (month) {
                        $("#selMonth").append('<option value="' + month.value + '" ' + (currentMonth ==
                            month.value ? "selected" : "") + '>' + month.text + '</option>');
                    });
                }

                $("#selEmployee").empty().append('<option value=""></option>');
                if (employees && employees.length) {
                    employees.forEach(function (emp) {
                        $("#selEmployee").append('<option value="' + emp.value + '" ' + (currentEmployee ==
                            emp.value ? "selected" : "") + '>' + emp.text + '</option>');
                    });
                }

                $("#selDepartment, #selMonth, #selEmployee").trigger("change.select2");
            }

            initApprovedClaimsTable();

            $("input[name='courier_status']").on("change", function () {
                $("#selDepartment").val("").trigger("change.select2");
                $("#selMonth").val("").trigger("change.select2");
                $("#selEmployee").val("").trigger("change.select2");
                initApprovedClaimsTable();
            });

            $("#selDepartment, #selMonth, #selEmployee").on("change", function () {
                initApprovedClaimsTable();
            });

            let claimsDataTable = null;

            window.openCourierDetail = function (empId, month, status, el) {

                const button = el;
                const modalSelector = el.getAttribute("data-bs-target");

                $(modalSelector).modal("show");
                $("#courierModalBody").html(
                    '<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');

                $.ajax({
                    url: "{{ route('verify.pending.courier.detail') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        empid: empId,
                        month: month
                    },
                    beforeSend: function () {
                        startLoader({
                            currentTarget: button
                        });
                    },
                    success: function (res) {
                        if (res.success) {
                            displayCourierData(res.data);
                        } else {
                            $("#courierModalBody").html('<div class="alert alert-warning">' + res
                                .message + '</div>');
                        }
                    },
                    error: function (xhr) {
                        let errorMsg = xhr.responseJSON?.message || "Error loading courier details";
                        $("#courierModalBody").html('<div class="alert alert-danger">' + errorMsg +
                            '</div>');
                    },
                    complete: function () {
                        endLoader({
                            currentTarget: button
                        });
                    }
                });
            };

            window.eHome = function (empId, month) {
                // handled by .ehome-btn → eHomeModal in main.js
            };

            window.revertClaim = function (empId, month, yearId, companyId) {
                Swal.fire({
                    title: 'Revert Claim to Pending?',
                    text: 'Please provide a reason to revert this claim back to the Pending tab:',
                    input: 'textarea',
                    inputPlaceholder: 'Enter reason for reverting claim...',
                    inputAttributes: {
                        rows: 3,
                        maxlength: 500
                    },
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Revert to Pending',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => {
                        if (!value || !value.trim()) {
                            return 'Reason is required to revert the claim!';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const reason = (result.value || '').trim();
                        $.ajax({
                            url: "{{ route('verify.approved.claim.revert') }}",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                empid: empId,
                                month: month,
                                reason: reason,
                                company_id: companyId || "{{ session('company_id') }}"
                            },
                            success: function (res) {
                                if (res.success) {
                                    showAlert("success", "ri-checkbox-circle-line", res.message);
                                    if ($.fn.DataTable.isDataTable("#ApprovedClaimsTable")) {
                                        $("#ApprovedClaimsTable").DataTable().ajax.reload(null, false);
                                    }
                                } else {
                                    showAlert("danger", "ri-close-circle-line", res.message);
                                }
                            },
                            error: function (xhr) {
                                let errorMsg = 'Failed to revert claim';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                showAlert("danger", "ri-close-circle-line", errorMsg);
                            }
                        });
                    }
                });
            };

            window.viewClaim = function (empId, month, dateOfSubmit, lastClaimMonth, lastMonthClaimAmount,
                courierDetail) {

                // Mirror to window scope so global onclick functions can access them
                window._currentViewEmpId = empId;
                window._currentViewMonth = month;

                $("#viewClaimsModal").modal("show");
                $("#claimsTableContainer").html(
                    '<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x"></i><p class="mt-3">Loading claims...</p></div>'
                );
                $("#claimSummaryHeader").hide();
                $("#btnViewEmpKm").hide();
                $("#btnViewAttd").hide();
                $("#btnViewElig").hide();

                $.ajax({
                    url: "{{ route('verify.pending.get.claims') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        empid: empId,
                        month: month
                    },
                    success: function (res) {
                        if (res.success) {
                            displayClaimsTable(res.data, empId, month);
                            $("#employeeClaimInfo").text(
                                `${res.employee.Name} (EC-${res.employee.EmpCode}) - ${res.monthName}`
                            );
                            const empStatus = (res.employee.EmpStatus || 'A').toUpperCase();
                            const isResigned = (empStatus === 'R');
                            const isDeactive = (empStatus === 'D');

                            let statusBadge = '<span class="badge bg-success-subtle text-success border border-success" style="font-size:10px; font-weight:600;">Active</span>';
                            if (isResigned) {
                                statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning" style="font-size:10px; font-weight:600;">Resigned</span>';
                            } else if (isDeactive) {
                                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger" style="font-size:10px; font-weight:600;">Deactive</span>';
                            }

                            let empDetails = [];
                            if (res.employee.department_name) empDetails.push(`Dept: ${res.employee.department_name}`);
                            if (res.employee.grade_name) empDetails.push(`Grade: ${res.employee.grade_name}`);
                            if (res.employee.designation_name) empDetails.push(`Desig: ${res.employee.designation_name}`);
                            if (isDeactive || isResigned) {
                                if (res.employee.formatted_resignation_date) {
                                    empDetails.push(`<span class="text-danger fw-semibold">Resigned: ${res.employee.formatted_resignation_date}</span>`);
                                }
                                if (res.employee.formatted_separation_date) {
                                    if (!res.employee.formatted_resignation_date || res.employee.formatted_resignation_date !== res.employee.formatted_separation_date) {
                                        empDetails.push(`<span class="text-danger fw-semibold">Separation: ${res.employee.formatted_separation_date}</span>`);
                                    }
                                }
                            }
                            const empDetailsStr = empDetails.length ? `<span class="text-muted fw-normal">(${empDetails.join(' • ')})</span>` : '';

                            $("#employeeMetaInfo").html(`${statusBadge} ${empDetailsStr}`);

                            // Extract calendar year from first valid BillDate in claims
                            var billYear = new Date().getFullYear();
                            if (res.data && res.data.length) {
                                for (var ci = 0; ci < res.data.length; ci++) {
                                    var bd = res.data[ci].BillDate;
                                    if (bd && bd !== '0000-00-00' && bd !== '1970-01-01') {
                                        var parsed = new Date(bd);
                                        if (!isNaN(parsed.getTime())) {
                                            billYear = parsed.getFullYear();
                                            break;
                                        }
                                    }
                                }
                            }
                            window._currentViewYear = billYear;

                            // Show the buttons now that we have the employee ID
                            $("#btnViewEmpKm").show();
                            $("#btnViewAttd").show();
                            $("#btnViewElig").show();

                            $("#h_totalClaimedAmt").text("₹" + parseFloat(res.totals.filled)
                                .toFixed(0));
                            $("#h_totalVerifiedAmt").text("₹" + parseFloat(res.totals.verified)
                                .toFixed(0));
                            $("#h_submissionDate").text(dateOfSubmit ? dateOfSubmit : "N/A");
                            $("#h_lastClaimMonth").text(lastClaimMonth ? lastClaimMonth : "N/A");
                            $("#h_lastMonthClaimAmount").text("₹" + (lastMonthClaimAmount || 0));
                            $("#h_courierDetail").text(courierDetail || "N/A");
                            $("#claimSummaryHeader").show();
                        } else {
                            $("#claimsTableContainer").html('<div class="alert alert-warning">' +
                                res.message + '</div>');
                        }
                    },
                    error: function () {
                        $("#claimsTableContainer").html(
                            '<div class="alert alert-danger">Error loading claims</div>');
                    }
                });
            };

            const isVVNR = {{ session('company_id') == 11 ? 'true' : 'false' }};

            function displayClaimsTable(claims, empId, month) {
                let html = `
                                                            <div class="table-responsive" style="max-height: 85vh; overflow-y: auto;">
                                                                <table class="table table-bordered table-hover" id="claimsDataTable">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th style="width:40px;align-content: center;">Sn</th>
                                                                            <th style="width:60px;align-content: center;">ExpId</th>
                                                                            <th style="width:100px;align-content: center;">Remark</th>
                                                                            <th style="width:80px;align-content: center;">File</th>
                                                                            <th style="width:80px;align-content: center;">Driver</th>
                                                                            <th style="align-content: center;">Claim Type</th>
                                                                            <th style="width:100px;align-content: center;">Upload Date</th>
                                                                            <th style="width:100px;align-content: center;">Bill Date</th>
                                                                            <th style="width:100px;align-content: center;">Claimed Amt</th>
                                                                            ${isVVNR ? `
                                                                                <th style="width:100px;align-content: center;">Approver Amt</th>
                                                                                <th style="width:150px;align-content: center;">Approver Remark</th>
                                                                            ` : ''}
                                                                            <th style="width:100px;align-content: center;">Verified Amt</th>
                                                                            <th style="width:150px;align-content: center;">Verifier Remark</th>
                                                                            <th style="width:100px;align-content: center;">Status</th>
                                                                            <th style="width:100px;align-content: center;">Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>`;

                if (claims.length === 0) {
                    html += `<tr><td colspan="${isVVNR ? 15 : 13}" class="text-center">No claims found</td></tr>`;
                } else {
                    claims.forEach((claim, index) => {
                        let bgColor = '#FFFFFF';
                        if (isVVNR) {
                            if (claim.VerifyTAmt && claim.ApprTAmt != claim.VerifyTAmt) {
                                bgColor = '#ffff002e';
                            }
                        } else {
                            if (claim.FilledTAmt != claim.VerifyTAmt) {
                                bgColor = '#ffff002e';
                            }
                        }

                        html += `<tr style="background-color:${bgColor};">
                                                                    <td class="text-center">${index + 1}</td>
                                                                    <td class="text-center"><a class="view-claim text-primary text-decoration-underline" href="#" data-claim-id="${claim.ClaimId}" data-empid="${claim.CrBy}" data-cgid="${claim.cgid}" data-expid="${claim.ExpId}">${claim.ExpId}</a></td>
                                                                    <td>${claim.DateEntryRemark || ''}</td>
                                                                    <td class="text-center">${claim.file_preview}</td>
                                                                    <td class="text-center">${claim.with_driver}</td>
                                                                    <td><a class="view-claim text-primary text-decoration-underline" href="#" data-claim-id="${claim.ClaimId}" data-empid="${claim.CrBy}" data-cgid="${claim.cgid}" data-expid="${claim.ExpId}">${claim.ClaimName}</a>${claim.km_details ? '<br>' + claim.km_details : ''}</td>
                                                                    <td class="text-center">${formatDate(claim.CrDate)}</td>
                                                                    <td class="text-center">${formatDate(claim.BillDate)}</td>
                                                                    <td class="text-end claimed-amt">₹${parseFloat(claim.FilledTAmt).toFixed(0)}</td>
                                                                    ${isVVNR ? `
                                                                        <td class="text-end approver-amt">₹${parseFloat(claim.ApprTAmt || 0).toFixed(0)}</td>
                                                                        <td class="approver-remark">${claim.ApprTRemark || ''}</td>
                                                                    ` : ''}
                                                                    <td class="text-end verified-amt-cell">₹${parseFloat(claim.VerifyTAmt).toFixed(0)}</td>
                                                                    <td>
                                                                        ${claim.VerifyTRemark || ''}
                                                                    </td>
                                                                    <td class="text-center status-col">
                                                                        <span class="badge bg-${claim.ClaimStatus === 'Verified' ? 'success' : 'warning'}">${claim.ClaimStatus}</span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                       -
                                                                    </td>
                                                                </tr>`;
                    });
                }

                html += `
                                                                    </tbody>
                                                                    <tfoot class="table-light fw-bold">
                                                                        <tr>
                                                                            <td colspan="8" class="text-end">Total:</td>
                                                                            <td class="text-end" id="footerClaimedTotal"></td>
                                                                            ${isVVNR ? `
                                                                                <td class="text-end" id="footerApprovedTotal"></td>
                                                                                <td></td>
                                                                            ` : ''}
                                                                            <td class="text-end" id="footerVerifiedTotal"></td>
                                                                            <td colspan="3"></td>
                                                                        </tr>
                                                                    </tfoot>
                                                                </table>
                                                            </div>`;
                $("#claimsTableContainer").html(html);
                updateFooterTotals();
            }

            function updateFooterTotals() {
                let claimedTotal = 0;
                let approvedTotal = 0;
                let verifiedTotal = 0;

                $("#claimsDataTable tbody tr").each(function () {
                    if ($(this).find('td[colspan]').length > 0) return;
                    let claimedText = $(this).find(".claimed-amt").text().replace('₹', '').replace(/,/g, '');
                    let apprText = $(this).find(".approver-amt").text().replace('₹', '').replace(/,/g, '');
                    let verifiedText = $(this).find(".verified-amt-cell").text().replace('₹', '').replace(/,/g, '');

                    claimedTotal += parseFloat(claimedText) || 0;
                    approvedTotal += parseFloat(apprText) || 0;
                    verifiedTotal += parseFloat(verifiedText) || 0;
                });

                $("#footerClaimedTotal").text('₹' + claimedTotal.toFixed(0));
                if (isVVNR) {
                    $("#footerApprovedTotal").text('₹' + approvedTotal.toFixed(0));
                }
                $("#footerVerifiedTotal").text('₹' + verifiedTotal.toFixed(0));
            }

            function formatDate(d) {
                if (!d || d === "0000-00-00") return "";

                let parts = d.split("-");
                return parts[2] + "-" + parts[1] + "-" + parts[0];

            }

            function displayCourierData(data) {

                let postDate = data.PostDate && data.PostDate !== '0000-00-00' ?
                    formatDate(data.PostDate) :
                    "-";

                let receivingDateDisplay = data.RecevingDate && data.RecevingDate !== '0000-00-00' ?
                    formatDate(data.RecevingDate) :
                    "-";

                let courierDateDisplay = data.courier_date && data.courier_date !== '0000-00-00' ?
                    formatDate(data.courier_date) :
                    "-";

                let html = '<div class="mb-3">';
                html += `
                                                                <small class="text-info text-center d-block">
                                                                    <strong>Note:</strong> This claim has been verified. Courier details are read-only and cannot be modified.
                                                                </small>`;
                html += '</div>';

                html += '<div class="row g-3">';

                html += '<div class="col-12 mt-2">';
                html += '<label class="form-label">Post Date</label>';
                html += '<input type="text" class="form-control" value="' + postDate + '" readonly>';
                html += '</div>';

                html += '<div class="col-12 mt-2">';
                html += '<label class="form-label">Docket Number</label>';
                html += '<input type="text" class="form-control" value="' + (data.DocateNo || '-') + '" readonly>';
                html += '</div>';

                html += '<div class="col-12 mt-2">';
                html += '<label class="form-label">Agency</label>';
                html += '<input type="text" class="form-control" value="' + (data.Agency || '-') + '" readonly>';
                html += '</div>';

                if (isVVNR) {
                    html += '<div class="col-12 mt-2">';
                    html += '<label class="form-label">Courier Date (Received by DMS)</label>';
                    html += '<input type="text" class="form-control" value="' + courierDateDisplay + '" readonly>';
                    html += '</div>';

                    html += '<div class="col-12 mt-2">';
                    html += '<label class="form-label">Receive Date for Verification (Received by Finance)</label>';
                    html += '<input type="text" class="form-control" value="' + receivingDateDisplay + '" readonly>';
                    html += '</div>';
                } else {
                    html += '<div class="col-12 mt-2">';
                    html += '<label class="form-label">Receiving Date</label>';
                    html += '<input type="text" class="form-control" value="' + receivingDateDisplay + '" readonly>';
                    html += '</div>';
                }

                html += '<div class="col-12 mt-2">';
                html += '<label class="form-label">Remark</label>';
                html += '<textarea class="form-control" rows="3" readonly>' + (data.DocRmk || '-') + '</textarea>';
                html += '</div>';

                html += '</div>';

                $("#courierModalBody").html(html);
            }
        });




        function exportSelectedPaymentsOnly() {
            let rows = getSelectedRows();

            if (rows.length === 0) {
                alert('Please select at least one record');
                return;
            }

            if (!confirm('This will only generate Focus Export without releasing payments. Continue?')) {
                return;
            }

            const selectedMonth = $('#selMonth').val();
            const selectedUsers = rows.map(r => r.employee_id);

            // determine months
            let monthsToExport = [];

            if (selectedMonth && selectedMonth != '') {
                monthsToExport = [parseInt(selectedMonth)];
            } else {
                const uniqueMonths = [...new Set(rows.map(r => r.month))];
                monthsToExport = uniqueMonths.map(m => parseInt(m));
            }

            if (monthsToExport.length === 0) {
                alert('Unable to determine month for export.');
                return;
            }

            // Show loading state
            const $btn = $('.release-btn');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin"></i> Generating Export...');


            // Generate Export
            $.ajax({
                url: '{{ url("/export-claim-stage-data") }}',
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                data: JSON.stringify({
                    yearId: '{{ session("year_id") }}',
                    company_id: '{{ session("company_id") }}',
                    monthSelect: monthsToExport,
                    userSelect: selectedUsers,
                    rows: rows,
                    exportOnly: true
                }),
                success: function (exportResponse) {
                    if (exportResponse.status === 'success' && exportResponse.file_url) {
                        window.open(exportResponse.file_url, '_blank');
                        alert('Focus export generated & downloaded successfully.');
                    } else {
                        alert('Export failed: ' + (exportResponse.message || 'Unknown error'));
                    }
                    resetButton();
                },
                error: function (xhr) {
                    let errorMsg = 'Export failed.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg += ' ' + xhr.responseJSON.message;
                    }
                    alert(errorMsg);
                    resetButton();
                }
            });

            function resetButton() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        }

        // Helper function to get selected rows
        function getSelectedRows() {
            let selectedRows = [];
            $('.row-check:checked').each(function () {
                selectedRows.push({
                    id: $(this).val(),
                    employee_id: $(this).data('emp'),
                    amount: $(this).data('amount'),
                    month: $(this).data('month')
                });
            });
            return selectedRows;
        }




        flatpickr("#verifiedDateRange", {
            mode: "range",
            dateFormat: "Y-m-d"
        });

    </script>
    <script src="{{ asset('custom/js/pages/claim.js') }}?v={{ time() }}"></script>
@endpush
