{{-- resources/views/finance/advance-approvals.blade.php --}}
<script src="{{ asset('vendor/cdn/xlsx@0.18.5/dist/xlsx.full.min.js') }}"></script>
@extends('layouts.app')

@section('styles')
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            font-size: 0.8rem;
        }

        .finance-approvals {
            max-width: 100%;
            margin: 10px;
            background-color: #fff;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }

        .page-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .page-header h3 {
            font-size: 1.3rem;
            margin: 0;
            color: #155997;
            font-weight: 600;
        }

        /* DataTable Customizations */
        .table {
            font-size: 0.75rem;
            margin-bottom: 0;
        }

        .table th {
            background-color: #dde6ff;
            color: #1e3a8a;
            font-weight: 600;
            font-size: 0.7rem;
            padding: 6px 8px;
            border: none;
            white-space: nowrap;
        }

        .table td {
            padding: 4px 8px;
            vertical-align: middle;
            border-color: #e9ecef;
            font-size: 0.75rem;
        }

        .table-striped>tbody>tr:nth-of-type(odd)>td {
            background-color: #f8f9fa;
        }

        /* Status badges */
        .status-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-ready {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        /* Amount styling */
        .amount-display {
            font-weight: 600;
            color: #155997;
            font-size: 0.8rem;
        }

        .btn-detail {
            background-color: #dde6ff;
            color: #1e3a8a;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 0.65rem;
            cursor: pointer;
        }

        .btn-detail:hover {
            background-color: #dde6ff;
            color: #1e3a8a;
        }

        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay .spinner-border {
            width: 2rem;
            height: 2rem;
        }

        .employee-info {
            font-weight: 600;
            color: #155997;
        }

        .compact-row {
            line-height: 1.2;
        }

        @media (max-width: 768px) {
            .finance-approvals {
                margin: 5px;
                padding: 8px;
            }

            .page-header h3 {
                font-size: 1.1rem;
            }

            .table {
                font-size: 0.7rem;
            }

            .table th,
            .table td {
                padding: 3px 5px;
            }
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid p-4">
        <div class="finance-approvals mt-5">
            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-secondary btn-sm" onclick="history.back();">
                    <i class="fa fa-arrow-left"></i> Back
                </button>
                <h3>Ready for Payment</h3>
                <div style="width: 80px;"></div>
            </div>

            <!-- Alert Message -->
            <div id="Message" class="alert alert-danger" style="display: none;"></div>

            <!-- Instructed Payment Section -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                
                <div class="text-center">
                    <div id="paymentSummary" class="text-danger" style="font-size: 0.8rem;">
                        <!-- Payment summary will be populated here -->
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-info btn-sm" onclick="refreshPaymentData()">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="generateExportBtn" onclick="generateFocusExport()">
                        <i class="fa fa-file-excel-o"></i> Generate Focus Export
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="paymentTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Sr.No</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Employee</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Activity</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Month</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Amount</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Released Date</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Status</th>
                            <th style="font-size: 10px; vertical-align: middle; background-color: #dde6ff; color: #1e3a8a;">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    @include('finance.partials.details-modal')
@endsection

@push('scripts')
    <script>
        // ==========================================
        // GLOBAL VARIABLES
        // ==========================================
        const empId = {{ auth()->id() }};
        const yearId = {{ session('year_id', date('Y')) }};
        const API_BASE_URL = "{{ url('api') }}"; // runs in the browser: same host as the page (API_NEW_BASE_URL may be a server-internal address)
        const apiBaseUrl = `${API_BASE_URL}/advance/finance`;

        let paymentTable;
        let paymentRequests = [];

        // ==========================================
        // UTILITY FUNCTIONS
        // ==========================================
        function showLoading() {
            document.getElementById("loadingOverlay").style.display = "flex";
        }

        function hideLoading() {
            document.getElementById("loadingOverlay").style.display = "none";
        }

        function showMessage(message, type = 'danger') {
            const messageDiv = $("#Message");
            messageDiv.removeClass('alert-success alert-danger alert-warning')
                .addClass('alert-' + type)
                .html(message)
                .show();
            setTimeout(() => {
                messageDiv.fadeOut();
            }, 5000);
            window.scrollTo(0, 0);
        }

        function formatDate(dateString) {
            if (!dateString || dateString === '0' || dateString === '0000-00-00') {
                return '';
            }
            const date = new Date(dateString);
            if (isNaN(date.getTime())) {
                return '';
            }
            return date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function formatAmount(amount) {
            const numAmount = parseFloat(amount) || 0;
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(numAmount);
        }

        function getStatusBadgeClass(status) {
            return 'status-ready';
        }

        function getMonthName(monthNumber) {
            if (!monthNumber || monthNumber < 1 || monthNumber > 12) {
                return 'Unknown';
            }
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months[monthNumber - 1];
        }

        // ==========================================
        // API CALLS
        // ==========================================
        function fetchPaymentRequests() {
            showLoading();
            $.ajax({
                url: apiBaseUrl + '/released-payments',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { year_id: yearId, status: 'Ready for Payment', limit: 100 },
                success: function (response) {
                    hideLoading();
                    if (response.success && response.data && response.data.employees) {
                        paymentRequests = response.data.employees.map(employee => ({
                            id: employee.advance_request_id,
                            advance_request_id: employee.advance_request_id,
                            employee_id: employee.employee_id,
                            employee_name: employee.employee_name,
                            employee_code: employee.employee_code,
                            department: employee.department,
                            designation: employee.designation,
                            req_data: employee.req_data,
                            activities: employee.activities,
                            payment_status: 'Ready for Payment',
                            payment_released_date: employee.activities[0]?.payment_release_details?.released_date,
                            payment_batch_id: employee.activities[0]?.payment_release_details?.batch_id,
                            ledger_balance: employee.ledger_balance
                        }));
                        initializePaymentTable();
                    } else {
                        paymentRequests = [];
                        initializePaymentTable();
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();
                    console.error('Error fetching payment requests:', error);
                    showMessage('Error fetching payment requests: ' + error);
                    paymentRequests = [];
                    initializePaymentTable();
                }
            });
        }

        // ==========================================
        // DATATABLE FUNCTIONS
        // ==========================================
        function initializePaymentTable() {
            if (paymentTable) {
                paymentTable.destroy();
            }

            paymentTable = $('#paymentTable').DataTable({
                data: paymentRequests,
                responsive: true,
                pageLength: 25,
                order: [[5, 'desc']],
                columns: [
                    {
                        title: 'Sr.No', data: null, orderable: false, searchable: false, width: '40px',
                        render: function (data, type, row, meta) { return meta.row + 1; }
                    },
                    {
                        title: 'Employee', data: null, width: '120px',
                        render: function (data, type, row) {
                            const empName = row.employee_name || 'Unknown';
                            const empCode = row.employee_code || '-';
                            const department = row.department || '-';
                            const isDeactive = row.is_active === false || row.emp_status === 'D';
                            const badge = isDeactive ? '<span style="font-size:0.55rem;padding:1px 4px;border-radius:3px;background-color:#fde2e2;color:#9b1c1c;margin-left:4px;font-weight:600;">DEACTIVE</span>' : '';
                            const cls = isDeactive ? 'background-color:#fff0f0;border-left:3px solid #dc3545;' : '';
                            return '<div class="compact-row" style="padding:4px;' + cls + '">' +
                                '<div class="employee-info">' + empName + badge + '</div>' +
                                '<small class="text-muted">' + empCode + '</small>' +
                                '<small class="text-muted d-block">' + department + '</small>' +
                                '</div>';
                        }
                    },
                    {
                        title: 'Activity', data: null, width: '150px',
                        render: function (data, type, row) {
                            const activityName = (row.req_data && row.req_data.activity_name) || 'Unknown Activity';
                            const advanceTypeName = (row.req_data && row.req_data.advance_type_name) || '';
                            return '<div class="compact-row">' +
                                '<div style="font-weight: 600; font-size: 0.75rem;">' + activityName + '</div>' +
                                (advanceTypeName ? '<small class="text-muted d-block">' + advanceTypeName + '</small>' : '') +
                                '</div>';
                        }
                    },
                    {
                        title: 'Month', data: null, width: '60px',
                        render: function (data, type, row) {
                            const month = (row.req_data && row.req_data.month) || 0;
                            return getMonthName(month);
                        }
                    },
                    {
                        title: 'Amount', data: null, width: '80px',
                        render: function (data, type, row) {
                            let amount = 0;
                            if (row.activities && row.activities.length > 0) {
                                amount = row.activities.reduce((sum, activity) => {
                                    return sum + (parseFloat(activity.finance_approved_amount) || parseFloat(activity.amount) || 0);
                                }, 0);
                            }
                            return '<div class="amount-display">' + formatAmount(amount) + '</div>';
                        }
                    },
                    {
                        title: 'Released Date', data: 'payment_released_date', width: '80px',
                        render: function (data, type, row) {
                            return '<span style="font-size: 0.7rem;">' + (data ? formatDate(data) : '-') + '</span>';
                        }
                    },
                    {
                        title: 'Status', data: 'payment_status', width: '100px',
                        render: function (data, type, row) {
                            const status = data || 'Ready for Payment';
                            return '<span class="status-badge ' + getStatusBadgeClass(status) + '">' + status + '</span>';
                        }
                    },
                    {
                        title: 'Action', data: null, orderable: false, width: '60px',
                        render: function (data, type, row) {
                            return '<button class="btn btn-detail" onclick="showDetails(' + row.id + ')">' +
                                '<i class="fa fa-eye"></i> View' +
                                '</button>';
                        }
                    }
                ],
                language: {
                    emptyTable: "No payment requests found",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                    search: "Search requests:"
                }
            });

            updatePaymentSummary();
        }

        function updatePaymentSummary() {
            const summaryDiv = document.getElementById('paymentSummary');
            if (summaryDiv && paymentRequests.length > 0) {
                const totalAmount = paymentRequests.reduce((sum, req) => {
                    if (req.activities && req.activities.length > 0) {
                        return sum + req.activities.reduce((actSum, activity) => {
                            return actSum + (parseFloat(activity.finance_approved_amount) || parseFloat(activity.amount) || 0);
                        }, 0);
                    }
                    return sum + (parseFloat(req.total_approved_amount) || parseFloat(req.total_amount) || 0);
                }, 0);

                summaryDiv.innerHTML = '<i class="fa fa-info-circle"></i> ' +
                    paymentRequests.length + ' payment(s) ready | Total: ' + formatAmount(totalAmount);
            } else {
                summaryDiv.innerHTML = '<i class="fa fa-info-circle"></i> No payments ready for processing';
            }
        }

        function refreshPaymentData() {
            showMessage('Refreshing payment data...', 'info');
            fetchPaymentRequests();
        }

        function generateFocusExport() {
            const btn = $('#generateExportBtn');
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-hourglass-half"></i> Checking...');

            $.ajax({
                url: apiBaseUrl + '/export-payments',
                method: 'GET',
                data: { year_id: yearId },
                dataType: 'json',
                success: function (response) {
                    btn.prop('disabled', false).html(originalHtml);

                    if (response.success === false) {
                        let detailedMessage = response.message || 'No data available for export';

                        if (response.data && response.data.excluded_activities && response.data.excluded_activities.length > 0) {
                            detailedMessage += '<br><br><strong>Excluded Activities:</strong><br>';
                            detailedMessage += '<div style="margin-left: 20px; margin-top: 10px;">';
                            response.data.excluded_activities.forEach((activity, index) => {
                                detailedMessage += `<div style="margin-bottom: 8px;">`;
                                detailedMessage += `${index + 1}. <strong>${activity.employee_name}</strong> (${activity.employee_code}) - <strong>₹${activity.amount.toLocaleString('en-IN')}</strong><br>`;
                                detailedMessage += `</div>`;
                            });
                            detailedMessage += '</div>';
                        }
                        showMessage(detailedMessage, 'warning');
                    } else {
                        const exportUrl = apiBaseUrl + '/export-payments?year_id=' + yearId + '&format=excel';
                        window.open(exportUrl, '_blank');
                        showMessage('Export file is being generated...', 'success');
                    }
                },
                error: function (xhr, status, error) {
                    btn.prop('disabled', false).html(originalHtml);
                    showMessage('Error checking export data: ' + error, 'danger');
                }
            });
        }

        // ==========================================
        // DETAIL VIEW FUNCTION
        // ==========================================
        function showDetails(requestId) {
            const request = paymentRequests.find(req => req.id === requestId);

            if (!request) {
                showMessage('Request not found', 'warning');
                return;
            }

            // Build modal content
            const empName = request.employee_name || 'Unknown';
            const empCode = request.employee_code || '-';
            const department = request.department || '-';
            const designation = request.designation || '-';
            const activityName = (request.req_data && request.req_data.activity_name) || 'Unknown Activity';
            const advanceTypeName = (request.req_data && request.req_data.advance_type_name) || '';
            const month = getMonthName((request.req_data && request.req_data.month) || 0);

            let activitiesHtml = '';
            if (request.activities && request.activities.length > 0) {
                activitiesHtml = `
                    <h6 class="text-primary mb-3"><i class="fa fa-list-ul"></i> Activities (${request.activities.length})</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Activity Type</th>
                                    <th>Amount</th>
                                    <th>Crops</th>
                                    <th>Varieties</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                request.activities.forEach(activity => {
                    const crops = activity.crop_names ? activity.crop_names.join(', ') : 'No crops';
                    const varieties = activity.variety_names ? activity.variety_names.join(', ') : 'No varieties';
                    const purpose = activity.purpose || 'No purpose specified';
                    const status = activity.finance_status || 'Ready for Payment';
                    const amount = activity.finance_approved_amount || activity.amount || 0;

                    activitiesHtml += `
                        <tr>
                            <td>${activity.activity_type_name || 'Unknown'}</td>
                            <td class="text-end">${formatAmount(amount)}</td>
                            <td><small>${crops}</small></td>
                            <td><small>${varieties}</small></td>
                            <td><small>${purpose}</small></td>
                            <td><span class="status-badge status-ready">${status}</span></td>
                        </tr>
                    `;
                });

                activitiesHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
            }

            const modalHtml = `
                <div class="modal fade" id="detailModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header" style="background-color: #dde6ff; color: #1e3a8a;">
                                <h5 class="modal-title">
                                    <i class="fa fa-file-text-o"></i> Payment Request Details
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fa fa-user"></i> Employee Information</h6>
                                        <table class="table table-sm">
                                            <tr><td><strong>Name:</strong></td><td>${empName}</td></tr>
                                            <tr><td><strong>Code:</strong></td><td>${empCode}</td></tr>
                                            <tr><td><strong>Department:</strong></td><td>${department}</td></tr>
                                            <tr><td><strong>Designation:</strong></td><td>${designation}</td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3"><i class="fa fa-calendar"></i> Request Information</h6>
                                        <table class="table table-sm">
                                            <tr><td><strong>Activity:</strong></td><td>${activityName}</td></tr>
                                            <tr><td><strong>Advance Type:</strong></td><td>${advanceTypeName}</td></tr>
                                            <tr><td><strong>Month:</strong></td><td>${month}</td></tr>
                                            <tr><td><strong>Released Date:</strong></td><td>${formatDate(request.payment_released_date)}</td></tr>
                                        </table>
                                    </div>
                                </div>

                                ${activitiesHtml}

                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="text-primary mb-3"><i class="fa fa-credit-card"></i> Payment Information</h6>
                                        <table class="table table-sm">
                                            <tr><td><strong>Payment Status:</strong></td><td><span class="status-badge status-ready">${request.payment_status || 'Ready for Payment'}</span></td></tr>
                                            <tr><td><strong>Batch ID:</strong></td><td>${request.payment_batch_id || '-'}</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#detailModal').remove();
            $('body').append(modalHtml);
            $('#detailModal').modal('show');
        }

        // ==========================================
        // INITIALIZATION
        // ==========================================
        $(document).ready(function () {
            fetchPaymentRequests();
        });
    </script>
@endpush
