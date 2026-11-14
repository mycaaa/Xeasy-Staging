{{-- Replace the entire blade view --}}
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
                                    <select class="select2" id="selDepartment" name="department" style="width:130px"
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
                                    <select class="select2" id="selEmployee" name="employee" style="width:250px"
                                        data-placeholder="Select Employee">
                                        <option value=""></option>
                                    </select>
                                </div>
                                @php
                                    $canAccessAutoVerify = (
                                        (string)session('emp_code') === '1740' ||
                                        (string)session('employee_details.EmpCode') === '1740' ||
                                        (string)(Auth::user()?->employee?->EmpCode ?? '') === '1740' ||
                                        (string)(Auth::user()?->employee?->EmpCode_New ?? '') === '1740' ||
                                        (string)(Auth::user()?->employee_id ?? '') === '1740' ||
                                        (string)(Auth::user()?->employee_id ?? '') === '2081'
                                    );
                                @endphp
                                @if($canAccessAutoVerify)
                                <div class="d-flex align-items-center gap-2">
                                    <a href="{{ route('admin.settings.auto-verify') }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" title="Configure Auto-Verification Rules & Capping Policies">
                                        <i class="ri-settings-3-line"></i> Rules & Settings
                                    </a>
                                    <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-1" id="btnAutoVerifyModalOpen" title="Run rule-based auto-verification on compliant claims">
                                        <i class="ri-flashlight-line"></i> Auto-Verify
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="card-body pb-3 pt-2">
                            <table style="margin-top: 15px" id="pendingClaimsTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sn</th>
                                        <th>Code</th>
                                        <th>Claimant</th>
                                        <th>Check</th>
                                        <th>Month</th>
                                        <th>Submission Date</th>
                                        <th>Claims</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                        <th>Courier Detail</th>
                                        <th>Last Claim</th>
                                    </tr>
                                </thead>
                                @can('verify_pending_list')
                                    <tbody>
                                    </tbody>
                                @else
                                    <tbody>
                                        <tr>
                                            <td colspan="12" class="text-center text-danger fw-bold py-3">
                                                You do not have permission to view pending claims.
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

    @if($canAccessAutoVerify)
    <!-- Automated Verification Modal -->
    <div class="modal fade" id="autoVerifyModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="ri-flashlight-line text-warning"></i> Automated Claim Verification
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('admin.settings.auto-verify') }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Configure Rules">
                            <i class="ri-settings-3-line"></i> Rules
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div id="autoVerifyLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Analyzing pending claims against policy limits, GPS distance & OCR...</p>
                    </div>
                    <div id="autoVerifyContent" style="display:none;">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="card border border-success mb-0">
                                    <div class="card-body text-center p-3">
                                        <h6 class="text-success mb-1">🟢 Clean (Auto-Approved)</h6>
                                        <h4 class="mb-0 text-success" id="avCleanCount">0</h4>
                                        <small class="text-muted" id="avCleanAmt">₹0.00</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border border-warning mb-0">
                                    <div class="card-body text-center p-3">
                                        <h6 class="text-warning mb-1">🟡 Policy-Capped</h6>
                                        <h4 class="mb-0 text-warning" id="avCappedCount">0</h4>
                                        <small class="text-muted" id="avCappedAmt">₹0.00</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border border-danger mb-0">
                                    <div class="card-body text-center p-3">
                                        <h6 class="text-danger mb-1">🔴 Requires Review</h6>
                                        <h4 class="mb-0 text-danger" id="avFlaggedCount">0</h4>
                                        <small class="text-muted">Anomalies / Mismatches</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info py-2" id="avSummaryNote">
                            Ready to auto-verify compliant claims based on your active filters.
                        </div>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Exp ID</th>
                                        <th>Emp ID</th>
                                        <th>Claim ID</th>
                                        <th>Claimed</th>
                                        <th>Verified</th>
                                        <th>Status</th>
                                        <th>Verdict Reason</th>
                                    </tr>
                                </thead>
                                <tbody id="avSampleTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="btnExecuteAutoVerify" disabled>
                        <i class="ri-check-double-line"></i> Confirm & Execute Auto-Verify
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                <div class="modal-footer" id="courierModalFooter" style="display:none;">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                            id="btnSaveCourier">
                            <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                <span class="loader" style="display: none;"></span>
                            </i>
                            Save Changes
                        </button>
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
                            <div class="d-flex align-items-center gap-2">
                                <div id="claimSummaryHeader"
                                    class="p-1 bg-info bg-opacity-50 rounded small d-flex flex-wrap align-items-center gap-1"
                                    style="display:none; max-width: 100%;background-color: rgb(41 156 219 / 16%) !important;">
                                    <div><strong>Total:</strong> <span id="h_totalClaimCount">0</span></div>
                                    <div><strong>Verified:</strong> <span id="h_totalVerifiedCount">0</span></div>
                                    <div><strong>Pending:</strong> <span id="h_totalVerifyPendingCount">0</span></div>
                                    <div><strong>Total Claimed:</strong> <span id="h_totalClaimedAmt">₹0</span></div>
                                    @if(session('company_id') == 11)
                                        <div><strong>Total Approved:</strong> <span id="h_totalApprovedAmt">₹0</span></div>
                                    @endif
                                    <div><strong>Total Verified:</strong> <span id="h_totalVerifiedAmt">₹0</span></div>
                                    <div><strong>Submission:</strong> <span id="h_submissionDate">N/A</span></div>
                                    <div><strong>Last Claim:</strong> <span id="h_lastClaimMonth">N/A</span>/<span
                                            id="h_lastMonthClaimAmount">₹0</span></div>
                                    <div><strong>Courier:</strong> <span id="h_courierDetail">N/A</span></div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" id="btnSubmitToApprover"
                                    style="display:none;">
                                    <i class="ri-send-plane-line"></i> Final Submit
                                </button>
                            </div>


                        </div>

                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn p-0 border-0 bg-transparent text-muted" id="btnRefreshData"
                            title="Refresh data">
                            <i class="ri-refresh-line fs-5" style="color: #070404;font-weight: 500;"></i>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                </div>
                <div class="d-flex gap-3 align-items-center mt-2 ms-5">

                    <div class="d-flex align-items-center">
                        <span
                            style="width:12px;height:12px;background:#FFFFFF;border:1px solid #ccc;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                        Normal
                    </div>

                    <div class="d-flex align-items-center">
                        <span
                            style="width:12px;height:12px;background:#ffff002e;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                        Amount Mismatch
                    </div>

                    <div class="d-flex align-items-center">
                        <span
                            style="width:12px;height:12px;background:#FEE7F5;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                        Leave/A/OD Employee
                    </div>

                    <div class="d-flex align-items-center">
                        <span
                            style="width:12px;height:12px;background:#F8A363;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                        Exceeds Eligibility (Lodging)
                    </div>

                    <div class="d-flex align-items-center">
                        <span
                            style="width:12px;height:12px;background:#FF7F7F;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                        Duration Mismatch (Lodging)
                    </div>

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
        #pendingClaimsTable thead th {
            text-align: center !important;
        }

        #claimsDataTable thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 5;
        }

        #claimsTableContainer table tbody tr td {
            align-content: center;
        }

        .highlight-row {
            background-color: #fff3cd !important;
            border: 2px solid #ffc107 !important;
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
            const isVVNR = {{ session('company_id') == 11 ? 'true' : 'false' }};

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




            window.verifyAllClaims = function (empId, month) {
                Swal.fire({
                    title: 'Verify All Claims?',
                    text: 'Are you sure you want to verify all pending claims for this employee?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Verify All',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Verifying...',
                            text: 'Please wait while all claims are being verified',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: "{{ route('verify.pending.verify.all') }}", // You need to create this route
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                empid: empId,
                                month: month
                            },
                            success: function (res) {
                                if (res.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: res.message || 'All claims verified successfully',
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    });

                                    // Reload the main table
                                    if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                                        $("#pendingClaimsTable").DataTable().ajax.reload(null, false);
                                    }

                                    // If view claims modal is open, refresh it
                                    if (currentViewEmpId == empId && currentViewMonth == month) {
                                        viewClaim(empId, month);
                                    }
                                } else {
                                    Swal.fire('Error', res.message || 'Failed to verify claims', 'error');
                                }
                            },
                            error: function (xhr) {
                                let errorMsg = 'Failed to verify claims';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                Swal.fire('Error', errorMsg, 'error');
                            }
                        });
                    }
                });
            };



            function initPendingClaimsTable() {
                if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                    $("#pendingClaimsTable").DataTable().destroy();
                }

                $("#pendingClaimsTable").DataTable({
                    processing: true,
                    serverSide: true,
                    ordering: false,
                    ajax: {
                        url: "{{ route('verify.pending.claims.data') }}",
                        type: "POST",
                        data: function (d) {
                            d._token = "{{ csrf_token() }}";
                            d.courier_status = $("input[name='courier_status']:checked").val() || 'all';
                            d.department = $("#selDepartment").val() || '';
                            d.month = $("#selMonth").val() || '';
                            d.employee = $("#selEmployee").val() || '';
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
                    columns: [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex",
                        className: "text-center",
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: "EmpCode",
                        name: "EmpCode",
                        className: "text-center"
                    },
                    {
                        data: "Name",
                        name: "Name"
                    },
                    {
                        data: "Check",
                        name: "Check",
                        className: "text-center",
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: "Month",
                        name: "Month",
                        className: "text-center"
                    },
                    {
                        data: "DateOfSubmit",
                        name: "DateOfSubmit",
                        className: "text-center"
                    },
                    {
                        data: "Total_Claim",
                        name: "Total_Claim",
                        className: "text-center"
                    },
                    {
                        data: "Claim_Amount",
                        name: "Claim_Amount",
                        className: "text-center"
                    },
                    {
                        data: "Actions",
                        name: "Actions",
                        orderable: false,
                        searchable: false,
                        className: "text-center"
                    },

                    {
                        data: "Courier_Detail",
                        name: "Courier_Detail",
                        className: "text-center",
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: "Last_Claim_Info",
                        name: "Last_Claim_Info",
                        className: "text-center",
                        orderable: false,
                        searchable: false
                    }
                    ],
                    pageLength: 10,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, "All"]
                    ],
                    responsive: false,
                    searching: true,
                    info: true,
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                    buttons: [
                        {
                            text: '<i class="ri-check-double-fill"></i> Verify All',
                            className: 'btn btn-success',
                            action: function (e, dt, node, config) {
                                verifyAllClaims();
                            }
                        }
                    ]

                });
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

            initPendingClaimsTable();

            $("input[name='courier_status']").on("change", function () {
                $("#selDepartment").val("").trigger("change.select2");
                $("#selMonth").val("").trigger("change.select2");
                $("#selEmployee").val("").trigger("change.select2");
                initPendingClaimsTable();
            });

            $("#selDepartment, #selMonth, #selEmployee").on("change", function () {
                initPendingClaimsTable();
            });

            let currentCourierEmpId = null;
            let currentCourierMonth = null;
            let currentViewEmpId = null;
            let currentViewMonth = null;
            let claimsDataTable = null;

            window.openCourierDetail = function (empId, month, status, el) {

                const button = el;
                const modalSelector = el.getAttribute("data-bs-target");
                const modalId = modalSelector.replace("#", "");
                currentCourierEmpId = empId;
                currentCourierMonth = month;

                $(modalSelector).modal("show");
                $("#courierModalBody").html(
                    '<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
                $("#courierModalFooter").hide();

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
                            displayCourierData(res.data, res.isEditable);
                            initReceivingDatePicker();
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

            window.returnClaim = function (empId, month) {
                Swal.fire({
                    title: 'Return Claim?',
                    text: 'Please provide a reason to return and reopen this claim for the employee:',
                    input: 'textarea',
                    inputPlaceholder: 'Enter reason for returning claim...',
                    inputAttributes: {
                        rows: 3,
                        maxlength: 500
                    },
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Return It',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => {
                        if (!value || !value.trim()) {
                            return 'Reason is required to return the claim!';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const reason = (result.value || '').trim();
                        $.ajax({
                            url: "{{ route('verify.pending.claim.return') }}",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                empid: empId,
                                month: month,
                                reason: reason
                            },
                            success: function (res) {
                                if (res.success) {
                                    showAlert("success", "ri-checkbox-circle-line", res
                                        .message);
                                    if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                                        $("#pendingClaimsTable").DataTable().ajax.reload(
                                            null, false);
                                    }
                                } else {
                                    showAlert("danger", "ri-close-circle-line", res
                                        .message);
                                }
                            },
                            error: function (xhr) {
                                let errorMsg = 'Failed to return claim';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                showAlert("danger", "ri-close-circle-line", errorMsg);
                            }
                        });
                    }
                });
            };

            window.submitToApp = function (empId, month) {
                Swal.fire({
                    title: 'Submit to Approver?',
                    text: 'Are you sure you want to submit this claim to approver?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Submit It',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('verify.pending.claim.submit') }}",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                empid: empId,
                                month: month,
                                year_id: "{{ session('year_id', 7) }}"
                            },
                            success: function (res) {
                                if (res.success) {
                                    showAlert("success", "ri-checkbox-circle-line", res
                                        .message);
                                    if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                                        $("#pendingClaimsTable").DataTable().ajax.reload(
                                            null, false);
                                    }
                                } else {
                                    showAlert("danger", "ri-close-circle-line", res
                                        .message);
                                }
                            },
                            error: function (xhr) {
                                let errorMsg = 'Failed to submit claim';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                showAlert("danger", "ri-close-circle-line", errorMsg);
                            }
                        });
                    }
                });
            };


            window.eHome = function (empId, month, yearId = null, companyId = null) {
                // handled by .ehome-btn click → eHomeModal in main.js
            };


            window.viewClaim = function (empId, month, dateOfSubmit, lastClaimMonth, lastMonthClaimAmount,
                courierDetail) {

                console.log('viewClaim called with empId:', empId, 'month:', month);
                console.log('sessionStorage scrollToExpId before:', sessionStorage.getItem('scrollToExpId'));

                currentViewEmpId = empId;
                currentViewMonth = month;
                // Mirror to window scope so global onclick functions can access them
                window._currentViewEmpId = empId;
                window._currentViewMonth = month;

                $("#viewClaimsModal").modal("show");
                $("#claimsTableContainer").html(
                    '<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x"></i><p class="mt-3">Loading claims...</p></div>'
                );
                $("#claimSummaryHeader").hide();
                $("#btnSubmitToApprover").hide();

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
                            // Show the Running KM icon button now that we have the employee ID
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
                            var displayText = "N/A";
                            if (courierDetail && courierDetail.indexOf("-0001") === -1) {
                                var dateObj = new Date(courierDetail);
                                if (!isNaN(dateObj.getTime())) {
                                    var day = String(dateObj.getDate()).padStart(2, '0');
                                    var month = String(dateObj.getMonth() + 1).padStart(2,
                                        '0');
                                    var year = dateObj.getFullYear();
                                    displayText = day + '-' + month + '-' + year;
                                }
                            }
                            $("#h_courierDetail").text(displayText);
                            $("#claimSummaryHeader").show();
                            checkAndShowSubmitButton();
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
                                                                                <th style="width:100px;align-content: center;">Claim Type</th>
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
                                                                                <th style="width:100px;align-content: center;">
                                            <button type="button" class="btn btn-success btn-sm verify-all-modal-btn" style="white-space: nowrap;">
                                                <i class="ri-check-double-fill"></i> Verify All
                                            </button>
                                        </th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>`;

                // LEGEND-01 (tracker #31): meaning of the row colours used below
                const legendHtml = `<div class="d-flex flex-wrap gap-3 align-items-center mb-2" style="font-size:11px;">
                    <strong>Colour legend:</strong>
                    <span><span class="d-inline-block align-middle me-1 border" style="width:12px;height:12px;background:#ffff002e;"></span>${isVVNR ? 'approved differs from verified' : 'claimed differs from verified'}</span>
                    <span><span class="d-inline-block align-middle me-1 border" style="width:12px;height:12px;background:#FEE7F5;box-shadow:inset 3px 0 0 #d63384;"></span>employee on leave / absent on bill date</span>
                    <span><span class="d-inline-block align-middle me-1 border" style="width:12px;height:12px;background:#F8A363;"></span>lodging above city-tier limit</span>
                    <span><span class="d-inline-block align-middle me-1 border" style="width:12px;height:12px;background:#FF7F7F;"></span>lodging stay duration mismatch or over 15 days</span>
                </div>`;
                html = legendHtml + html;
                if (claims.length === 0) {
                    html += `<tr><td colspan="${isVVNR ? 15 : 13}" class="text-center">No claims found</td></tr>`;
                } else {
                    console.log('Creating table rows for', claims.length, 'claims');
                    claims.forEach((claim, index) => {
                        console.log('Creating row for ExpId:', claim.ExpId);
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
                        // LEAVE-01: highlight only real absence / leave (not blank, holiday, week-off or on-duty),
                        // keep it visible even when a lodging colour applies, and default an unverified
                        // claim on such a day to 0 (still editable).
                        const attCode = String(claim.emp_att_status || '').trim().toUpperCase();
                        const leaveCodes = {!! json_encode(array_values((array) config('claim_rules.leave_codes'))) !!};
                        const onLeave = attCode !== '' && leaveCodes.includes(attCode);
                        if (onLeave) {
                            bgColor = '#FEE7F5';
                        }

                        if (claim.ClaimId == 1) {
                            let stayDays = Math.max(1, Math.ceil(parseFloat(claim.StayDuration) || 1));
                            if ((claim.CityCategory == 'A' && claim.FilledTAmt > claim.emp_elig.Lodging_CategoryA * stayDays) || (claim.CityCategory == 'B' && claim.FilledTAmt > claim.emp_elig.Lodging_CategoryB * stayDays) || (claim.CityCategory == 'C' && claim.FilledTAmt > claim.emp_elig.Lodging_CategoryC * stayDays)) {
                                bgColor = '#F8A363';
                            }

                            // Duration mismatch check: compare StayDuration with actual days between Arrival & Departure
                            if (claim.Arrival && claim.Departure && claim.Arrival !== '0000-00-00' && claim.Departure !== '0000-00-00') {
                                let arrDate = new Date(claim.Arrival);
                                let depDate = new Date(claim.Departure);
                                if (!isNaN(arrDate.getTime()) && !isNaN(depDate.getTime())) {
                                    let actualDays = (depDate - arrDate) / (1000 * 60 * 60 * 24);
                                    actualDays = Math.max(0, Math.round(actualDays * 100) / 100);
                                    let claimedDuration = parseFloat(claim.StayDuration) || 0;
                                    // Highlight if duration mismatch (difference > 0.5 day) or duration > 15 days
                                    if (Math.abs(actualDays - claimedDuration) > 0.5 || claimedDuration > 15) {
                                        bgColor = '#FF7F7F'; // Red-ish highlight for duration issue
                                    }
                                }
                            }
                        }

                        const notYetVerified = !(parseInt(claim.VerifyBy || 0) > 0) || !claim.VerifyDate || String(claim.VerifyDate).startsWith('0000');
                        // GST-01: GSTIN check result on bill rows
                        let gstBadge = '';
                        if (claim.GSTIN_Status === 'valid') {
                            gstBadge = `<br><span class="badge bg-success-subtle text-success" title="${claim.GSTIN}">GSTIN valid</span>`;
                        } else if (claim.GSTIN_Status && claim.GSTIN_Status !== 'missing') {
                            gstBadge = `<br><span class="badge bg-danger-subtle text-danger" title="${claim.GSTIN || ''}">GSTIN invalid</span>`;
                        } else if (claim.ClaimId == 1 && parseFloat(claim.FilledTAmt || 0) > {{ (float) config('claim_rules.gstin.flag_missing_above', 1000) }}) {
                            gstBadge = `<br><span class="badge bg-warning-subtle text-warning">No GSTIN</span>`;
                        }
                        // TATKAL-02 (tracker #2): booking date vs journey date. Tatkal / Premium Tatkal open one
                        // day before the journey, so a ticket booked 0-1 day ahead is very likely Tatkal.
                        let railBadge = '';
                        if (claim.ClaimId == 14 && claim.RailBookingDate && claim.RailJourneyDate && !String(claim.RailBookingDate).startsWith('0000') && !String(claim.RailJourneyDate).startsWith('0000')) {
                            const bd = new Date(String(claim.RailBookingDate).substring(0, 10)), jd = new Date(String(claim.RailJourneyDate).substring(0, 10));
                            const ahead = Math.round((jd - bd) / 86400000);
                            const q = String(claim.RailQuota || '').toUpperCase();
                            const isTatkal = q.includes('TATKAL') || q === 'TQ' || q === 'PT' || q === 'CK';
                            if (!isNaN(ahead) && ahead >= 0 && ahead <= 1 && !isTatkal) {
                                railBadge = `<br><span class="badge bg-warning-subtle text-warning" title="Booked ${ahead} day(s) before the journey">Likely Tatkal - category ${q || 'not given'}</span>`;
                            } else if (!isNaN(ahead) && ahead >= 2 && isTatkal) {
                                railBadge = `<br><span class="badge bg-danger-subtle text-danger" title="Tatkal can only be booked one day before the journey">Marked ${q} but booked ${ahead} days ahead</span>`;
                            } else if (isTatkal) {
                                railBadge = `<br><span class="badge bg-info-subtle text-info">${q}</span>`;
                            }
                        }
                        const leaveBadge = onLeave ? `<br><span class="badge bg-danger-subtle text-danger" title="Attendance on bill date">On leave / absent (${attCode})</span>` : '';
                        html += `<tr style="background-color:${bgColor};${onLeave ? 'box-shadow: inset 4px 0 0 #d63384;' : ''}" data-expid="${claim.ExpId}" ${onLeave ? 'data-on-leave="1"' : ''}>
                                                                        <td class="text-center">${index + 1}</td>
                                                                        <td class="text-center"><a class="view-claim text-primary text-decoration-underline" href="#" data-claim-id="${claim.ClaimId}" data-empid="${claim.CrBy}" data-cgid="${claim.cgid}" data-expid="${claim.ExpId}">${claim.ExpId}</a></td>
                                                                        <td>${claim.DateEntryRemark || ''}</td>
                                                                        <td class="text-center">${claim.file_preview}</td>
                                                                        <td class="text-center">${claim.with_driver}</td>
                                                                        <td><a class="view-claim text-primary text-decoration-underline" href="#" data-claim-id="${claim.ClaimId}" data-empid="${claim.CrBy}" data-cgid="${claim.cgid}" data-expid="${claim.ExpId}">${claim.ClaimName}</a>${claim.km_details ? '<br>' + claim.km_details : ''}</td>
                                                                        <td class="text-center">${formatDate(claim.CrDate)}</td>
                                                                        <td class="text-center">${formatDate(claim.BillDate)}${leaveBadge}${gstBadge}${railBadge}</td>
                                                                        <td class="text-end claimed-amt">₹${parseFloat(claim.FilledTAmt).toFixed(0)}</td>
                                                                        ${isVVNR ? `
                                                                            <td class="text-end approver-amt">₹${parseFloat(claim.ApprTAmt || 0).toFixed(0)}</td>
                                                                            <td class="approver-remark">${claim.ApprTRemark || ''}</td>
                                                                        ` : ''}
                                                                        <td>
                                                                            <input type="number" 
                                                                                step="any"
                                                                                min="0"
                                                                                class="form-control form-control-sm verify-amt text-end"
                                                                                data-expid="${claim.ExpId}"
                                                                                data-max="${isVVNR && claim.ApprTAmt !== null && claim.ApprTAmt !== undefined ? claim.ApprTAmt : claim.FilledTAmt}"
                                                                                data-claimed="${claim.FilledTAmt}"
                                                                                value="${(onLeave && notYetVerified && claim.cgid != 7) ? 0 : (claim.VerifyTAmt !== null && claim.VerifyTAmt !== undefined && claim.VerifyTAmt !== '' ? claim.VerifyTAmt : (isVVNR ? (claim.ApprTAmt ?? '') : ''))}"
                                                                                ${claim.cgid == 7 ? 'readonly' : ''}>
                                                                        </td>
                                                                        <td>
                                                                            <textarea class="form-control form-control-sm verify-remark"
                                                                                    data-expid="${claim.ExpId}"
                                                                                    rows="1">${claim.VerifyTRemark || ((onLeave && notYetVerified && claim.cgid != 7) ? 'Employee on leave / absent on bill date (' + attCode + ')' : '')}</textarea>
                                                                        </td>
                                                                        <td class="text-center status-col">
                                                                            <span class="badge bg-${claim.ClaimStatus === 'Verified' ? 'success' : 'warning'}">${claim.ClaimStatus}</span>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <a href="javascript:void(0);" class="badge bg-primary text-white text-decoration-none verify-single-btn"
                                                                                    data-expid="${claim.ExpId}">
                                                                                <i class="ri-check-double-fill"></i> Verify
                                                                            </a>
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
                if (window.vpCapCheckAll) { window.vpCapCheckAll(); }
                console.log('Claims table HTML updated, calling updateFooterTotals');
                updateFooterTotals();

                // Check if we need to scroll to a specific ExpId
                console.log('Calling scrollToExpIdIfNeeded from displayClaimsTable');
                scrollToExpIdIfNeeded();


                // Add click handler for the verify all button in modal
                $(document).off('click', '.verify-all-modal-btn').on('click', '.verify-all-modal-btn', function () {
                    verifyAllModalClaims(empId, month);
                });
            }




            function verifyAllModalClaims(empId, month) {
                // Get all unverified rows
                const unverifiedRows = $("#claimsDataTable tbody tr").filter(function () {
                    return $(this).find(".badge.bg-warning").length > 0;
                });

                if (unverifiedRows.length === 0) {
                    Swal.fire({
                        title: 'No Pending Claims',
                        text: 'All claims in this list are already verified.',
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Verify All Claims?',
                    text: `You are about to verify ${unverifiedRows.length} pending claim(s) in this list. Are you sure?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Verify All',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Verifying...',
                            text: 'Please wait while all claims are being verified',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Collect all ExpIds to verify
                        const expIds = [];
                        unverifiedRows.each(function () {
                            const expId = $(this).find('.verify-single-btn').data('expid');
                            if (expId) {
                                expIds.push(expId);
                            }
                        });

                        // Process verification for each claim
                        let verifiedCount = 0;
                        let failedCount = 0;
                        let promises = [];

                        expIds.forEach(expId => {
                            const verifyAmt = parseFloat($(`.verify-amt[data-expid="${expId}"]`).val()) || 0;
                            const maxAmt = parseFloat($(`.verify-amt[data-expid="${expId}"]`).data("max")) || 0;
                            const verifyRemark = ($(`.verify-remark[data-expid="${expId}"]`).val() || '').trim();

                            // VERIFY-CAP-UI-01: never above the claimed amount
                            const claimedAmt = parseFloat($(`.verify-amt[data-expid="${expId}"]`).data("claimed")) || 0;
                            if (claimedAmt > 0 && verifyAmt > claimedAmt + 0.009) {
                                failedCount++;
                                return;
                            }
                            // Check if remark is required (when amount differs)
                            if (verifyAmt !== maxAmt && verifyRemark === '') {
                                failedCount++;
                                return;
                            }

                            const promise = $.ajax({
                                url: "{{ route('verify.pending.verify.single') }}",
                                type: "POST",
                                data: {
                                    _token: "{{ csrf_token() }}",
                                    expId: expId,
                                    verifyAmt: verifyAmt,
                                    verifyRemark: verifyRemark
                                }
                            }).then(function (res) {
                                if (res.success) {
                                    verifiedCount++;
                                } else {
                                    failedCount++;
                                }
                            }).fail(function () {
                                failedCount++;
                            });

                            promises.push(promise);
                        });

                        // Wait for all verifications to complete
                        Promise.all(promises).then(() => {
                            Swal.close();

                            if (verifiedCount > 0) {
                                Swal.fire({
                                    title: 'Verification Complete',
                                    html: `Successfully verified: ${verifiedCount}<br>Failed: ${failedCount}`,
                                    icon: verifiedCount > 0 ? 'success' : 'error',
                                    confirmButtonText: 'OK'
                                });

                                // Refresh the modal data
                                if (empId && month) {
                                    viewClaim(empId, month);
                                }

                                // Reload the main table
                                if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                                    $("#pendingClaimsTable").DataTable().ajax.reload(null, false);
                                }
                            } else {
                                Swal.fire('Error', 'Failed to verify claims. Please check each claim individually.', 'error');
                            }
                        });
                    }
                });
            }

            function updateFooterTotals() {
                let claimedTotal = 0;
                let approvedTotal = 0;
                let verifiedOnlyTotal = 0;
                let totalClaimCount = 0;
                let totalVerifiedCount = 0;
                let totalVerifyPendingCount = 0;

                $("#claimsDataTable tbody tr").each(function () {
                    let $row = $(this);

                    // Skip if this is the "no claims found" row
                    if ($row.find('td[colspan]').length > 0) {
                        return;
                    }

                    let claimedAmt = parseFloat(
                        $row.find(".claimed-amt").text().replace('₹', '').replace(/,/g, '')
                    ) || 0;

                    let apprAmt = parseFloat(
                        $row.find(".approver-amt").text().replace('₹', '').replace(/,/g, '')
                    ) || 0;

                    let verifiedAmt = parseFloat(
                        $row.find(".verify-amt").val()
                    ) || 0;

                    let remark = $row.find(".verify-remark").val()?.trim() || '';
                    let isVerified = $row.find(".badge.bg-success").length > 0;

                    // Count totals
                    totalClaimCount++;

                    if (isVerified) {
                        totalVerifiedCount++;
                        verifiedOnlyTotal += verifiedAmt;
                    } else {
                        totalVerifyPendingCount++;
                    }

                    // Totals (all rows)
                    claimedTotal += claimedAmt;
                    approvedTotal += apprAmt;

                    // Auto highlight diff rows with missing remark
                    let expectedAmt = isVVNR ? (apprAmt > 0 ? apprAmt : claimedAmt) : claimedAmt;
                    if (isVerified && expectedAmt !== verifiedAmt && remark === '') {
                        $row.addClass('diff-row');
                    } else {
                        $row.removeClass('diff-row');
                    }
                });

                // Update footer totals
                $("#footerClaimedTotal").text('₹' + claimedTotal.toFixed(0));
                if (isVVNR) {
                    $("#footerApprovedTotal").text('₹' + approvedTotal.toFixed(0));
                }
                $("#footerVerifiedTotal").text('₹' + verifiedOnlyTotal.toFixed(0));

                // Update header counts and amounts
                $("#h_totalClaimCount").text(totalClaimCount);
                $("#h_totalVerifiedCount").text(totalVerifiedCount);
                $("#h_totalVerifyPendingCount").text(totalVerifyPendingCount);
                if (isVVNR) {
                    $("#h_totalApprovedAmt").text('₹' + approvedTotal.toFixed(0));
                }
                $("#h_totalVerifiedAmt").text('₹' + verifiedOnlyTotal.toFixed(0));

                // Check if submit button should be shown
                checkAndShowSubmitButton();
            }

            function checkAndShowSubmitButton() {
                let allVerified = true;
                let hasRows = false;

                $("#claimsDataTable tbody tr").each(function () {
                    let $row = $(this);


                    if ($row.find('td[colspan]').length > 0) {
                        return;
                    }

                    hasRows = true;
                    let isVerified = $row.find(".badge.bg-success").length > 0;

                    if (!isVerified) {
                        allVerified = false;
                        return false;
                    }
                });

                if (hasRows && allVerified) {
                    $("#btnSubmitToApprover").show();
                } else {
                    $("#btnSubmitToApprover").hide();
                }
            }

            // VERIFY-CAP-UI-01 (14 Nov 2026): flag a verified amount above the claimed amount as it is typed and keep that
            // row's Verify disabled until it is corrected; the server check on Verify stays as it is
            function vpCapCheck($inp) {
                const v = parseFloat($inp.val()), c = parseFloat($inp.data('claimed'));
                const $row = $inp.closest('tr'), $btn = $row.find('.verify-single-btn');
                const over = !isNaN(v) && !isNaN(c) && c > 0 && v > c + 0.009;
                $inp.toggleClass('is-invalid', over).attr('aria-invalid', over ? 'true' : 'false');
                $row.find('.vp-cap-msg').remove();
                if (over) {
                    $inp.after('<div class="vp-cap-msg invalid-feedback d-block" style="font-size:10px;white-space:normal;">More than the claimed ₹' + c.toLocaleString('en-IN') + '</div>');
                }
                $btn.toggleClass('disabled bg-secondary', over).toggleClass('bg-primary', !over)
                    .attr('aria-disabled', over ? 'true' : 'false')
                    .attr('title', over ? 'The verified amount is more than the claimed amount. Correct it to verify.' : '')
                    .css('pointer-events', over ? 'none' : '');
                return over;
            }
            window.vpCapCheckAll = function () { $('.verify-amt').each(function () { vpCapCheck($(this)); }); };
            $(document).on("input change", ".verify-amt", function () {
                vpCapCheck($(this));
                updateFooterTotals();
            });
            $(document).ajaxComplete(function () { setTimeout(window.vpCapCheckAll, 0); });

            $(document).on("click", ".verify-single-btn", function () {
                let $btn = $(this);
                let expId = $btn.data("expid");

                let verifyAmt = parseFloat($(`.verify-amt[data-expid="${expId}"]`).val());
                let maxAmt = parseFloat($(`.verify-amt[data-expid="${expId}"]`).data("max"));
                let verifyRemark = ($(`.verify-remark[data-expid="${expId}"]`).val() || '').trim();

                if (vpCapCheck($(`.verify-amt[data-expid="${expId}"]`))) {
                    Swal.fire('Amount Exceeded', `The verified amount for ExpId ${expId} is more than the claimed amount. Correct it before verifying.`, 'warning');
                    return;
                }
                if (isNaN(verifyAmt) || verifyAmt < 0) {
                    Swal.fire(
                        'Invalid Amount',
                        `Please enter a valid verified amount for ExpId: ${expId}`,
                        'error'
                    );
                    return;
                }

                // if (verifyAmt > maxAmt) {
                //     Swal.fire(
                //         'Amount Exceeded',
                //         `Verified amount cannot be greater than claimed amount for ExpId: ${expId}`,
                //         'error'
                //     );
                //     return;
                // }

                if (verifyAmt !== maxAmt && verifyRemark === '') {
                    let $row = $btn.closest('tr');
                    $row.addClass('diff-row');

                    let diffLabel = isVVNR ? 'approved' : 'claimed';
                    Swal.fire(
                        'Remark Required',
                        `Please enter verifier remark for ExpId: ${expId} because verified amount differs from ${diffLabel} amount.`,
                        'warning'
                    );
                    return;
                }

                let originalHtml = $btn.html();
                $btn.prop("disabled", true)
                    .html('<i class="ri-loader-4-line ri-spin"></i> Verifying');

                $.ajax({
                    url: "{{ route('verify.pending.verify.single') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        expId: expId,
                        verifyAmt: verifyAmt,
                        verifyRemark: verifyRemark
                    },
                    success: function (res) {
                        if (res.success) {
                            let $row = $btn.closest('tr');
                            $row.css('background-color', '#E8FFE8');
                            $row.find('.status-col')
                                .html('<span class="badge bg-success">Verified</span>');

                            updateFooterTotals();

                            if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                                $("#pendingClaimsTable").DataTable().ajax.reload(null, false);
                            }
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function (xhr) {
                        let errorMsg = 'Failed to verify claim';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        Swal.fire(
                            'Error',
                            `ExpId: ${expId} — ${errorMsg}`,
                            'error'
                        );
                    },
                    complete: function () {
                        $btn.prop("disabled", false).html(originalHtml);
                    }
                });
            });


            $("#btnSubmitToApprover").on("click", function () {
                if (!currentViewEmpId || !currentViewMonth) {
                    Swal.fire('Error', 'Invalid employee or month data', 'error');
                    return;
                }


                let allVerified = true;
                let unverifiedCount = 0;

                $("#claimsDataTable tbody tr").each(function () {
                    let $row = $(this);


                    if ($row.find('td[colspan]').length > 0) {
                        return;
                    }

                    let isVerified = $row.find(".badge.bg-success").length > 0;

                    if (!isVerified) {
                        allVerified = false;
                        unverifiedCount++;
                    }
                });

                if (!allVerified) {
                    Swal.fire({
                        title: 'Verification Incomplete',
                        text: `${unverifiedCount} claim(s) are still pending verification. Please verify all claims before submitting to approver.`,
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Submit to Approver?',
                    text: 'Are you sure you want to submit this claim to approver? All claims have been verified.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Submit It',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        let $btn = $("#btnSubmitToApprover");
                        let originalHtml = $btn.html();

                        $btn.prop("disabled", true)
                            .html('<i class="ri-loader-4-line ri-spin"></i> Submitting...');

                        $.ajax({
                            url: "{{ route('verify.pending.claim.submit') }}",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                empid: currentViewEmpId,
                                month: currentViewMonth,
                                year_id: "{{ session('year_id', 7) }}"
                            },
                            success: function (res) {
                                if (res.success) {
                                    showAlert("success", "ri-checkbox-circle-line", res
                                        .message);
                                    $("#viewClaimsModal").modal("hide");

                                    if ($.fn.DataTable.isDataTable(
                                        "#pendingClaimsTable")) {
                                        $("#pendingClaimsTable").DataTable().ajax
                                            .reload(null, false);
                                    }
                                } else {
                                    showAlert("danger", "ri-close-circle-line", res
                                        .message);
                                }
                            },
                            error: function (xhr) {
                                let errorMsg = 'Failed to submit claim';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                showAlert("danger", "ri-close-circle-line", errorMsg);
                            },
                            complete: function () {
                                $btn.prop("disabled", false).html(originalHtml);
                            }
                        });
                    }
                });
            });

            window.viewFullImage = function (url) {
                /*$("#modalViewImage").attr("src", url);
                $("#imageViewModal").modal("show");*/
            };

            function formatDate(d) {
                if (!d || d === "0000-00-00") return "";
                let parts = d.split("-");
                return parts[2] + "-" + parts[1] + "-" + parts[0];
            }

            function formatDateInput(d) {
                if (!d || d === "0000-00-00") return "";
                return d;
            }

            function displayCourierData(data, isEditable) {

                let postDate = data.PostDate && data.PostDate !== '0000-00-00' ?
                    formatDate(data.PostDate) :
                    "-";

                let receivingDateInput = formatDateInput(data.RecevingDate);
                let receivingDateDisplay = data.RecevingDate && data.RecevingDate !== '0000-00-00' ?
                    formatDate(data.RecevingDate) :
                    "-";

                let courierDateInput = formatDateInput(data.courier_date);
                let courierDateDisplay = data.courier_date && data.courier_date !== '0000-00-00' ?
                    formatDate(data.courier_date) :
                    "-";

                let html = '<div class="row g-3">';

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

                if (isEditable) {

                    if (isVVNR) {
                        html += '<div class="col-12 mt-2">';
                        html += '<label class="form-label">Courier Date (Received by DMS)</label>';
                        html += '<input type="date" class="form-control" id="inputCourierDate" value="' +
                            courierDateInput + '">';
                        html += '</div>';

                        html += '<div class="col-12 mt-2">';
                        html += '<label class="form-label">Receive Date for Verification (Received by Finance)</label>';
                        html += '<input type="date" class="form-control" id="inputReceivingDate" value="' +
                            receivingDateInput + '">';
                        html += '</div>';
                    } else {
                        html += '<div class="col-12 mt-2">';
                        html += '<label class="form-label">Receiving Date <span class="text-danger">*</span></label>';
                        html += '<input type="date" class="form-control" id="inputReceivingDate" value="' +
                            receivingDateInput + '">';
                        html += '</div>';
                    }

                    html += '<div class="col-12 mt-2">';
                    html += '<label class="form-label">Remark</label>';
                    html += '<textarea class="form-control" id="inputDocRmk" rows="3">' + (data.DocRmk || '') +
                        '</textarea>';
                    html += '</div>';

                    $("#courierModalFooter").show();

                } else {

                    if (isVVNR) {
                        html += '<div class="col-12 mt-2">';
                        html += '<label class="form-label">Courier Date (Received by DMS)</label>';
                        html += '<input type="text" class="form-control" value="' + courierDateDisplay +
                            '" readonly>';
                        html += '</div>';

                        html += '<div class="col-12 mt-2">';
                        html += '<label class="form-label">Receive Date for Verification (Received by Finance)</label>';
                        html += '<input type="text" class="form-control" value="' + receivingDateDisplay +
                            '" readonly>';
                        html += '</div>';
                    } else {
                        html += '<div class="col-12 mt-2">';
                        html += '<label class="form-label">Receiving Date</label>';
                        html += '<input type="text" class="form-control" value="' + receivingDateDisplay +
                            '" readonly>';
                        html += '</div>';
                    }

                    html += '<div class="col-12 mt-2">';
                    html += '<label class="form-label">Remark</label>';
                    html += '<textarea class="form-control" rows="3" readonly>' + (data.DocRmk || '-') +
                        '</textarea>';
                    html += '</div>';

                    $("#courierModalFooter").hide();
                }

                html += '</div>';

                $("#courierModalBody").html(html);
            }

            function initReceivingDatePicker() {

                const today = new Date().toISOString().split("T")[0];

                function addCustomButtons(fp) {
                    const container = document.createElement("div");
                    container.className = "flatpickr-extra-buttons";

                    const lastBtn = document.createElement("button");
                    lastBtn.type = "button";
                    lastBtn.className = "fp-btn fp-last-btn";
                    lastBtn.innerHTML = '<i class="ri-calendar-check-line"></i>';
                    lastBtn.onclick = () => {
                        fp.setDate(today, true);
                    };
                    const clearBtn = document.createElement("button");
                    clearBtn.type = "button";
                    clearBtn.className = "fp-btn fp-clear-btn";
                    clearBtn.innerHTML = '<i class="ri-close-circle-line"></i>';
                    clearBtn.onclick = () => {
                        fp.clear();
                        fp.close();
                    };

                    container.appendChild(lastBtn);
                    container.appendChild(clearBtn);

                    fp.calendarContainer.appendChild(container);
                }

                flatpickr("#inputReceivingDate", {
                    dateFormat: "Y-m-d",
                    maxDate: today,
                    onReady: function () {
                        addCustomButtons(this);
                    }
                });

                if (isVVNR && document.getElementById("inputCourierDate")) {
                    flatpickr("#inputCourierDate", {
                        dateFormat: "Y-m-d",
                        maxDate: today,
                        onReady: function () {
                            addCustomButtons(this);
                        }
                    });
                }
            }

            $("#btnSaveCourier").on("click", function () {

                const button = this;
                let receivingDate = $("#inputReceivingDate").val();
                let courierDate = isVVNR ? $("#inputCourierDate").val() : null;
                let docRmk = $("#inputDocRmk").val();

                if (isVVNR) {
                    if (!receivingDate && !courierDate && !docRmk) {
                        showAlert("warning", "ri-error-warning-line", "Please enter at least Courier Date, Receiving Date or Remark");
                        return;
                    }
                } else {
                    if (!receivingDate) {
                        showAlert("warning", "ri-error-warning-line", "Please select receiving date");
                        return;
                    }
                }

                let postData = {
                    _token: "{{ csrf_token() }}",
                    empid: currentCourierEmpId,
                    month: currentCourierMonth,
                    receiving_date: receivingDate,
                    doc_rmk: docRmk
                };
                if (isVVNR) {
                    postData.courier_date = courierDate;
                }

                $.ajax({
                    url: "{{ route('verify.pending.courier.update') }}",
                    type: "POST",
                    data: postData,
                    beforeSend: function () {
                        startLoader({
                            currentTarget: button
                        });
                    },
                    success: function (res) {
                        if (res.success) {
                            showAlert("success", "ri-checkbox-circle-line", res.message);
                            $("#courierModal").modal("hide");

                            if ($.fn.DataTable.isDataTable("#pendingClaimsTable")) {
                                $("#pendingClaimsTable").DataTable().ajax.reload(null, false);
                            }
                        } else {
                            showAlert("danger", "ri-close-circle-line", res.message);
                        }
                    },
                    error: function (xhr) {
                        let errorMsg = xhr.responseJSON?.message ??
                            "Error updating courier details";
                        showAlert("danger", "ri-close-circle-line", errorMsg);
                    },
                    complete: function () {
                        endLoader({
                            currentTarget: button
                        });
                    }
                });
            });

            $(document).off("click", ".zoom-verify-img").on("click", ".zoom-verify-img", function () {
                $(".zoom-verify-img").not(this).removeClass("zoomed");
                $(this).toggleClass("zoomed");
            });

            $('#viewClaimsModal').on('hidden.bs.modal', function () {
                $("#claimsTableContainer").html(
                    '<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x"></i><p class="mt-3">Loading claims...</p></div>'
                );
                $("#claimSummaryHeader").hide();
                $("#btnSubmitToApprover").hide();
                $("#employeeClaimInfo").html('');
                $("#employeeMetaInfo").html('');
                $("#btnViewEmpKm").hide();
                $("#btnViewAttd").hide();
                $("#btnViewElig").hide();
                $("#h_totalClaimCount").text("0");
                $("#h_totalVerifiedCount").text("0");
                $("#h_totalVerifyPendingCount").text("0");
                $("#h_totalClaimedAmt").text("₹0");
                $("#h_totalApprovedAmt").text("₹0");
                $("#h_totalVerifiedAmt").text("₹0");
                $("#h_submissionDate").text("N/A");
                $("#h_lastClaimMonth").text("N/A");
                $("#h_lastMonthClaimAmount").text("₹0");
                $("#h_courierDetail").text("N/A");
                currentViewEmpId = null;
                currentViewMonth = null;
                window._currentViewEmpId = null;
                window._currentViewMonth = null;
                window._currentViewYear = null;
            });

            function scrollToExpIdIfNeeded() {
                const targetExpId = sessionStorage.getItem('scrollToExpId');
                console.log('scrollToExpIdIfNeeded called, targetExpId:', targetExpId);

                if (targetExpId) {
                    // Clear the stored ExpId
                    sessionStorage.removeItem('scrollToExpId');

                    // Find the row with the target ExpId
                    const targetRow = $(`#claimsDataTable tbody tr[data-expid="${targetExpId}"]`);
                    console.log('Found target row:', targetRow.length, 'rows');
                    console.log('All rows with data-expid:', $('#claimsDataTable tbody tr[data-expid]').length);
                    console.log('All ExpIds in table:', $('#claimsDataTable tbody tr[data-expid]').map(function () {
                        return $(this).attr('data-expid');
                    }).get());

                    if (targetRow.length > 0) {
                        // Get the scrollable container
                        const scrollContainer = $('.table-responsive');
                        console.log('Scroll container found:', scrollContainer.length);

                        // Calculate the position to scroll to
                        const containerTop = scrollContainer.offset().top;
                        const rowTop = targetRow.offset().top;
                        const currentScrollTop = scrollContainer.scrollTop();
                        const targetScrollTop = currentScrollTop + (rowTop - containerTop) -
                            100; // 100px offset from top

                        console.log('Scroll positions - containerTop:', containerTop, 'rowTop:', rowTop,
                            'currentScrollTop:', currentScrollTop, 'targetScrollTop:', targetScrollTop);

                        // Smooth scroll to the target row
                        scrollContainer.animate({
                            scrollTop: targetScrollTop
                        }, 800, 'swing', function () {
                            console.log('Scroll animation completed, highlighting row');
                            // Highlight the row after scrolling
                            highlightRow(targetRow);
                        });
                    } else {
                        console.log('Target row not found for ExpId:', targetExpId);
                    }
                } else {
                    console.log('No targetExpId found in sessionStorage');
                }
            }

            function highlightRow(row) {
                console.log('highlightRow called for row:', row);
                // Add highlight class with animation
                row.addClass('highlight-row');
                console.log('Added highlight-row class');

                // Remove highlight after 3 seconds
                setTimeout(function () {
                    row.removeClass('highlight-row');
                    console.log('Removed highlight-row class after 3 seconds');
                }, 5000);
            }

            $('#cancelBtn').on('click', function () {
                var expid = $(this).data('expid') || $('#claimDetailModal').attr('data-modal-expid');
                console.log('cancelBtn clicked, expid:', expid);

                if (expid && $('#viewClaimsModal').hasClass('show')) {
                    // Store expid for scrolling
                    sessionStorage.setItem('scrollToExpId', expid);

                    // Refresh only the specific row data via AJAX
                    refreshSingleClaimRow(expid);
                }
            });

            function refreshSingleClaimRow(expId) {
                if (!currentViewEmpId || !currentViewMonth) return;

                $.ajax({
                    url: "{{ route('verify.pending.get.single.claim') }}", // You'll need to create this route
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        empid: currentViewEmpId,
                        month: currentViewMonth,
                        expid: expId
                    },
                    success: function (res) {
                        if (res.success && res.claim) {
                            // Update the specific row in the table
                            updateClaimRowInTable(res.claim);
                            // Update footer totals
                            updateFooterTotals();
                            // Scroll to the updated row
                            setTimeout(function () {
                                scrollToExpIdIfNeeded();
                            }, 100);
                        }
                    },
                    error: function (xhr) {
                        console.error('Failed to refresh claim row:', xhr);
                        // Fallback to scroll without refresh
                        setTimeout(function () {
                            scrollToExpIdIfNeeded();
                        }, 100);
                    }
                });
            }

            function updateClaimRowInTable(claim) {
                const $row = $(`#claimsDataTable tbody tr[data-expid="${claim.ExpId}"]`);
                if ($row.length === 0) return;

                // Update verified amount
                $row.find('.verify-amt').val(claim.VerifyTAmt);

                // Update verifier remark
                $row.find('.verify-remark').val(claim.VerifyTRemark || '');

                // Update status badge
                const statusBadge = claim.ClaimStatus === 'Verified' ?
                    '<span class="badge bg-success">Verified</span>' :
                    '<span class="badge bg-warning">Pending</span>';
                $row.find('.status-col').html(statusBadge);

                // Update row background color
                let bgColor = '#FFFFFF';
                if (claim.FilledTAmt != claim.VerifyTAmt) {
                    bgColor = '#ffff002e';
                } else if (claim.ClaimStatus === 'Verified') {
                    bgColor = '#E8FFE8';
                }
                $row.css('background-color', bgColor);
            }

            // refresh button listener
            $(document).on('click', '#btnRefreshData', function () {
                console.log('Refresh button clicked');
                if (currentViewEmpId && currentViewMonth) {
                    viewClaim(currentViewEmpId, currentViewMonth);
                }
            });

            // Auto-Verify Modal handler
            $('#btnAutoVerifyModalOpen').on('click', function () {
                const month = $('#selMonth').val();
                const empId = $('#selEmployee').val();
                const deptId = $('#selDepartment').val();

                $('#autoVerifyModal').modal('show');
                $('#autoVerifyLoading').show();
                $('#autoVerifyContent').hide();
                $('#btnExecuteAutoVerify').prop('disabled', true);

                $.ajax({
                    url: "{{ route('verify.pending.auto-verify.preview') }}",
                    type: "GET",
                    data: {
                        month: month,
                        employee_id: empId,
                        department_id: deptId
                    },
                    success: function (res) {
                        $('#autoVerifyLoading').hide();
                        $('#autoVerifyContent').show();

                        if (res.success && res.summary) {
                            const s = res.summary;
                            $('#avCleanCount').text(s.approved || 0);
                            $('#avCleanAmt').text('₹' + Number(s.approved_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
                            $('#avCappedCount').text(s.adjusted || 0);
                            $('#avCappedAmt').text('₹' + Number(s.adjusted_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
                            $('#avFlaggedCount').text(s.flagged || 0);

                            const totalActionable = (s.approved || 0) + (s.adjusted || 0);
                            if (totalActionable > 0) {
                                $('#btnExecuteAutoVerify').prop('disabled', false);
                                $('#avSummaryNote').html(`<b>${totalActionable}</b> claims ready for auto-verification. ${(s.flagged || 0)} claims will remain for manual inspection.`);
                            } else {
                                $('#btnExecuteAutoVerify').prop('disabled', true);
                                $('#avSummaryNote').html(`No eligible claims found matching current filters.`);
                            }

                            // Populate sample rows
                            let rowsHtml = '';
                            (s.claims || []).forEach(function (c) {
                                const badge = c.status === 'approved' ? '<span class="badge bg-success">Approved</span>' :
                                             (c.status === 'adjusted' ? '<span class="badge bg-warning">Adjusted</span>' :
                                             '<span class="badge bg-danger">Flagged</span>');
                                rowsHtml += `
                                    <tr>
                                        <td>${c.exp_id}</td>
                                        <td>${c.employee_id}</td>
                                        <td>${c.claim_id}</td>
                                        <td>₹${Number(c.claimed_amount).toFixed(2)}</td>
                                        <td>₹${Number(c.verified_amount).toFixed(2)}</td>
                                        <td>${badge}</td>
                                        <td><small>${c.reason}</small></td>
                                    </tr>
                                `;
                            });
                            $('#avSampleTableBody').html(rowsHtml || '<tr><td colspan="7" class="text-center text-muted">No pending claims evaluated</td></tr>');
                        }
                    },
                    error: function (xhr) {
                        $('#autoVerifyLoading').hide();
                        $('#autoVerifyContent').show();
                        $('#avSummaryNote').html('<span class="text-danger">Failed to load preview: ' + (xhr.responseJSON?.message || 'Server error') + '</span>');
                    }
                });
            });

            // Execute Batch Auto-Verification
            $('#btnExecuteAutoVerify').on('click', function () {
                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Processing...');

                $.ajax({
                    url: "{{ route('verify.pending.auto-verify.batch') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        month: $('#selMonth').val(),
                        employee_id: $('#selEmployee').val(),
                        department_id: $('#selDepartment').val()
                    },
                    success: function (res) {
                        $('#autoVerifyModal').modal('hide');
                        $btn.prop('disabled', false).html('<i class="ri-check-double-line"></i> Confirm & Execute Auto-Verify');
                        if (res.success) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Auto-Verification Complete',
                                    text: res.message
                                });
                            } else {
                                alert(res.message);
                            }
                            if ($.fn.DataTable.isDataTable('#pendingClaimsTable')) {
                                $('#pendingClaimsTable').DataTable().ajax.reload(null, false);
                            }
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html('<i class="ri-check-double-line"></i> Confirm & Execute Auto-Verify');
                        alert('Failed to execute auto-verification: ' + (xhr.responseJSON?.message || 'Server error'));
                    }
                });
            });

        });
    </script>
    <script src="{{ asset('custom/js/pages/claim.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('custom/js/pages/eligibility_panel.js') }}"></script>
@endpush
