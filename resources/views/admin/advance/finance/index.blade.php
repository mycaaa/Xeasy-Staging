@extends('layouts.app')

@section('title', 'Finance - Advance Requests')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0 flex-grow-1">Finance - Pending Advance Requests</h4>
                        </div>
                        <div class="card-body">
                            <table id="financeAdvanceTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>S.No</th>
                                        <th>Request ID</th>
                                        <th>Activity Name</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Total Amount</th>
                                        <th>Activities Count</th>
                                        <th>Submitted Date</th>
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

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Finance Request</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="approveForm">
                    <div class="modal-body">
                        <input type="hidden" id="approve_request_id" name="request_id">
                        <input type="hidden" id="approve_employee_id" name="employee_id">

                        <div class="alert alert-info">
                            <strong>Employee:</strong> <span id="approve_employee_name"></span> (<span
                                id="approve_employee_code"></span>)
                        </div>

                        <div id="activitiesContainer">
                            <!-- Activities will be loaded here -->
                        </div>

                        {{-- FX-53: foreign-currency advance paid at the actual rate --}}
                        <div class="form-group" id="fxPaidRateGroup" style="display:none;">
                            <label for="fx_paid_rate">Paid at rate (₹ per <span id="fxCurrency"></span>)</label>
                            <input type="number" class="form-control" id="fx_paid_rate" name="fx_paid_rate" step="0.0001" min="0">
                            <input type="hidden" id="fx_activity_id" name="activity_id" disabled>
                            <small class="form-text text-muted">Requested at <span id="fxReqRate"></span>. Enter the card-loading / bank rate; the INR amount is worked out from the foreign amount.</small>
                        </div>

                        <div class="form-group">
                            <label for="finance_approved_amount">Total Finance Approved Amount</label>
                            <input type="number" class="form-control" id="finance_approved_amount"
                                name="finance_approved_amount" step="0.01" min="0">
                            <small class="form-text text-muted">Leave empty to approve full amount</small>
                        </div>

                        <div class="form-group">
                            <label for="finance_remark">Finance Remarks</label>
                            <textarea class="form-control" id="finance_remark" name="finance_remark" rows="3"
                                placeholder="Enter finance remarks (optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Finance Request</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" id="reject_request_id" name="request_id">
                        <input type="hidden" id="reject_employee_id" name="employee_id">

                        <div class="alert alert-info">
                            <strong>Employee:</strong> <span id="reject_employee_name"></span> (<span
                                id="reject_employee_code"></span>)
                        </div>

                        <div class="form-group">
                            <label for="reject_remarks">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reject_remarks" name="remarks" rows="3" placeholder="Enter rejection reason"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Revert Modal -->
    <div class="modal fade" id="revertModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Revert Finance Request</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="revertForm">
                    <div class="modal-body">
                        <input type="hidden" id="revert_request_id" name="request_id">
                        <input type="hidden" id="revert_employee_id" name="employee_id">

                        <div class="alert alert-info">
                            <strong>Employee:</strong> <span id="revert_employee_name"></span> (<span
                                id="revert_employee_code"></span>)
                        </div>

                        <div class="form-group">
                            <label for="revert_remarks">Revert Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="revert_remarks" name="remarks" rows="3" placeholder="Enter revert reason"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Revert</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let financeData = [];

            // Function to load finance data from API
            function loadFinanceData() {
                $.ajax({
                    url: "/api/advance/finance/pending",
                    type: 'GET',
                    beforeSend: function() {
                        $('#financeAdvanceTable tbody').html(
                            '<tr><td colspan="10" class="text-center">Loading...</td></tr>');
                    },
                    success: function(response) {
                        if (response.success) {
                            financeData = response.data;
                            initializeDataTable();
                        } else {
                            toastr.error(response.message || 'Failed to load finance data');
                            $('#financeAdvanceTable tbody').html(
                                '<tr><td colspan="10" class="text-center">No data available</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading finance data:', xhr);
                        toastr.error('Failed to load finance data');
                        $('#financeAdvanceTable tbody').html(
                            '<tr><td colspan="10" class="text-center">Error loading data</td></tr>');
                    }
                });
            }

            // Function to initialize DataTable with loaded data
            function initializeDataTable() {
                // Prepare data for DataTable
                let tableData = [];

                financeData.forEach(function(employee, index) {
                    let reqData = employee.req_data;

                    tableData.push({
                        DT_RowIndex: index + 1,
                        request_id: reqData.id,
                        activity_name: reqData.activity_name,
                        employee_name: employee.employee_name,
                        employee_code: employee.employee_code,
                        department: employee.department,
                        total_amount: employee.total_finance_pending_amount,
                        activities_count: employee.finance_pending_activities_count,
                        submitted_date: reqData.last_submitted_at ? new Date(reqData
                            .last_submitted_at).toLocaleDateString() : 'N/A',
                        status: employee.finance_level_info,
                        employee_data: employee // Store full employee data for modals
                    });
                });

                // Initialize DataTable
                var table = $('#financeAdvanceTable').DataTable({
                    data: tableData,
                    destroy: true, // Allow reinitialization
                    columns: [{
                            data: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'request_id'
                        },
                        {
                            data: 'activity_name'
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return row.employee_name + '<br><small class="text-muted">' + row
                                    .employee_code + '</small>';
                            }
                        },
                        {
                            data: 'department'
                        },
                        {
                            data: 'total_amount',
                            render: function(data, type, row) {
                                return '₹' + parseFloat(data).toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                });
                            }
                        },
                        {
                            data: 'activities_count'
                        },
                        {
                            data: 'submitted_date'
                        },
                        {
                            data: 'status',
                            render: function(data, type, row) {
                                return '<span class="badge badge-warning">' + data + '</span>';
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                let actions = '<div class="btn-group" role="group">';
                                actions +=
                                    '<button type="button" class="btn btn-sm btn-success approve-btn" data-employee-id="' +
                                    row.employee_data.employee_id + '" data-request-id="' + row
                                    .request_id + '" title="Approve">';
                                actions += '<i class="fas fa-check"></i> Approve</button>';
                                actions +=
                                    '<button type="button" class="btn btn-sm btn-danger reject-btn" data-employee-id="' +
                                    row.employee_data.employee_id + '" data-request-id="' + row
                                    .request_id + '" title="Reject">';
                                actions += '<i class="fas fa-times"></i> Reject</button>';
                                actions +=
                                    '<button type="button" class="btn btn-sm btn-warning revert-btn" data-employee-id="' +
                                    row.employee_data.employee_id + '" data-request-id="' + row
                                    .request_id + '" title="Revert">';
                                actions += '<i class="fas fa-undo"></i> Revert</button>';
                                actions += '</div>';
                                return actions;
                            }
                        }
                    ],
                    order: [
                        [7, 'asc']
                    ], // Order by submitted date
                    pageLength: 25,
                    responsive: true,
                    language: {
                        emptyTable: "No finance requests pending"
                    }
                });
            }

            // Load data on page load
            loadFinanceData();

            // Approve button click
            $(document).on('click', '.approve-btn', function() {
                var employeeId = $(this).data('employee-id');
                var requestId = $(this).data('request-id');

                // Find the employee data
                var employeeData = financeData.find(function(emp) {
                    return emp.employee_id == employeeId && emp.req_data.id == requestId;
                });

                if (!employeeData) {
                    toastr.error('Employee data not found');
                    return;
                }

                $('#approve_request_id').val(requestId);
                $('#approve_employee_id').val(employeeId);
                $('#approve_employee_name').text(employeeData.employee_name);
                $('#approve_employee_code').text(employeeData.employee_code);
                $('#finance_approved_amount').val(employeeData.total_finance_pending_amount);

                // Build activities display
                var activitiesHtml = '<h6>Activities to Approve:</h6>';
                activitiesHtml += '<div class="table-responsive">';
                activitiesHtml += '<table class="table table-sm table-bordered">';
                activitiesHtml +=
                    '<thead><tr><th>Activity Type</th><th>Crops</th><th>Purpose</th><th>Amount</th></tr></thead>';
                activitiesHtml += '<tbody>';

                employeeData.activities.forEach(function(activity) {
                    activitiesHtml += '<tr>';
                    activitiesHtml += '<td>' + activity.activity_type_name + '</td>';
                    activitiesHtml += '<td>' + (activity.crop_names.join(', ') || 'N/A') + '</td>';
                    activitiesHtml += '<td>' + (activity.purpose || 'N/A') + '</td>';
                    activitiesHtml += '<td>₹' + parseFloat(activity.amount).toLocaleString(
                    'en-IN', {
                        minimumFractionDigits: 2
                    }) + (activity.fx_label ? '<br><small class="text-info">' + $('<div>').text(activity.fx_label).html() + '</small>' : '') + '</td>';
                    activitiesHtml += '</tr>';
                });

                activitiesHtml += '</tbody></table></div>';

                // FX-53: one foreign-currency activity -> Finance can enter the rate actually paid
                var fxActs = employeeData.activities.filter(function(a) { return a.currency && a.currency !== 'INR' && parseFloat(a.fx_amount) > 0; });
                $('#fx_paid_rate').val('');
                if (fxActs.length === 1 && employeeData.activities.length === 1) {
                    var fa = fxActs[0];
                    $('#fxCurrency').text(fa.currency);
                    $('#fxReqRate').text(fa.fx_rate);
                    $('#fx_activity_id').val(fa.id).prop('disabled', false);
                    $('#fx_paid_rate').off('input.fx').on('input.fx', function() {
                        var r = parseFloat(this.value);
                        if (r > 0) { $('#finance_approved_amount').val((parseFloat(fa.fx_amount) * r).toFixed(2)); }
                    });
                    $('#fxPaidRateGroup').show();
                } else {
                    $('#fx_activity_id').val('').prop('disabled', true);
                    $('#fxPaidRateGroup').hide();
                }

                $('#activitiesContainer').html(activitiesHtml);
                $('#approveModal').modal('show');
            });

            // Reject button click
            $(document).on('click', '.reject-btn', function() {
                var employeeId = $(this).data('employee-id');
                var requestId = $(this).data('request-id');

                // Find the employee data
                var employeeData = financeData.find(function(emp) {
                    return emp.employee_id == employeeId && emp.req_data.id == requestId;
                });

                if (!employeeData) {
                    toastr.error('Employee data not found');
                    return;
                }

                $('#reject_request_id').val(requestId);
                $('#reject_employee_id').val(employeeId);
                $('#reject_employee_name').text(employeeData.employee_name);
                $('#reject_employee_code').text(employeeData.employee_code);
                $('#rejectModal').modal('show');
            });

            // Revert button click
            $(document).on('click', '.revert-btn', function() {
                var employeeId = $(this).data('employee-id');
                var requestId = $(this).data('request-id');

                // Find the employee data
                var employeeData = financeData.find(function(emp) {
                    return emp.employee_id == employeeId && emp.req_data.id == requestId;
                });

                if (!employeeData) {
                    toastr.error('Employee data not found');
                    return;
                }

                $('#revert_request_id').val(requestId);
                $('#revert_employee_id').val(employeeId);
                $('#revert_employee_name').text(employeeData.employee_name);
                $('#revert_employee_code').text(employeeData.employee_code);
                $('#revertModal').modal('show');
            });

            // Handle approve form submission
            $('#approveForm').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: "/api/advance/finance/approve",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#approveModal').modal('hide');
                            loadFinanceData(); // Reload data
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        var errorMessage = 'An error occurred while processing the request';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            var errors = xhr.responseJSON.errors;
                            errorMessage = Object.keys(errors).map(function(key) {
                                return errors[key][0];
                            }).join(', ');
                        }
                        toastr.error(errorMessage);
                    }
                });
            });

            // Handle reject form submission
            $('#rejectForm').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: "/api/advance/finance/reject",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#rejectModal').modal('hide');
                            loadFinanceData(); // Reload data
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        var errorMessage = 'An error occurred while processing the request';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            var errors = xhr.responseJSON.errors;
                            errorMessage = Object.keys(errors).map(function(key) {
                                return errors[key][0];
                            }).join(', ');
                        }
                        toastr.error(errorMessage);
                    }
                });
            });

            // Handle revert form submission
            $('#revertForm').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: "/api/advance/finance/revert",
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#revertModal').modal('hide');
                            loadFinanceData(); // Reload data
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        var errorMessage = 'An error occurred while processing the request';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            var errors = xhr.responseJSON.errors;
                            errorMessage = Object.keys(errors).map(function(key) {
                                return errors[key][0];
                            }).join(', ');
                        }
                        toastr.error(errorMessage);
                    }
                });
            });

            // Clear forms when modals are hidden
            $('#approveModal, #rejectModal, #revertModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $('#activitiesContainer').empty();
            });

            // Refresh button
            $(document).on('click', '#refreshBtn', function() {
                loadFinanceData();
            });
        });
    </script>
@endpush
