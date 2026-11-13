@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $sessiondata = session()->all();
    $yearId = session("year_id");
    $company_id = session("company_id");
    
    // Define API endpoints - you can configure these in .env file
    $apiBaseUrl = url('api');
@endphp

<div class="page-content">
    <div class="container-fluid">
    <!-- Authentication Data (Hidden) -->
    <div id="authData" 
        data-employee-id="{{ $user->employee_id ?? '' }}"
        data-employee-name="{{ $user->name ?? '' }}"
        data-employee-code="{{ $user->employee_code ?? '' }}"
        data-department="{{ $user->department ?? '' }}"
        data-company-id="{{ $company_id ?? 0 }}"
        data-fyear-id="{{ $yearId ?? 0 }}"
        data-user-id="{{ $user->id ?? 0 }}"
        data-api-base-url="{{ $apiBaseUrl }}"
        data-csrf-token="{{ csrf_token() }}"
        style="display: none;">
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="advance-approvals">
        <!-- Header -->
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <button type="button" class="btn btn-secondary btn-sm" onclick="history.back();">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <h3>Advance Approvals</h3>
            <div>
                <a href="{{ url('/advance-requests/report') }}" class="btn btn-primary btn-sm">
                    <i class="ri-file-chart-line align-middle me-1"></i> Advance Report
                </a>
            </div>
        </div>

        <!-- Alert Message -->
        <div id="Message" class="alert alert-danger mb-3" style="display: none;"></div>

        <!-- Filters -->
        <div class="card mb-3" style="border:1px solid #e9ecef;">
            <div class="card-body py-2 px-3">
                <div class="row g-2 align-items-center">
                    <div class="col-auto">
                        <span style="font-size:0.75rem;font-weight:600;color:#155997;"><i class="bi bi-funnel"></i> Filters:</span>
                    </div>
                    <div class="col">
                        <input type="text" class="form-control form-control-sm dt-filter" id="filterEmployee" placeholder="Employee name/code" aria-label="Employee name/code" data-column="1">
                    </div>
                    <div class="col">
                        <input type="text" class="form-control form-control-sm dt-filter" id="filterActivity" placeholder="Activity" aria-label="Activity" data-column="2">
                    </div>
                    <div class="col">
                        <select class="form-select form-select-sm dt-filter-select" id="filterMonth" data-column="3">
                            <option value="">All Months</option>
                            <option value="Apr">Apr</option>
                            <option value="May">May</option>
                            <option value="Jun">Jun</option>
                            <option value="Jul">Jul</option>
                            <option value="Aug">Aug</option>
                            <option value="Sep">Sep</option>
                            <option value="Oct">Oct</option>
                            <option value="Nov">Nov</option>
                            <option value="Dec">Dec</option>
                            <option value="Jan">Jan</option>
                            <option value="Feb">Feb</option>
                            <option value="Mar">Mar</option>
                        </select>
                    </div>
                    <div class="col">
                        <input type="text" class="form-control form-control-sm dt-filter" id="filterCrop" placeholder="Crop" aria-label="Crop" data-column="4">
                    </div>
                    <div class="col">
                        <input type="text" class="form-control form-control-sm dt-filter" id="filterVariety" placeholder="Variety" aria-label="Variety" data-column="5">
                    </div>
                    <div class="col">
                        <input type="text" class="form-control form-control-sm dt-filter" id="filterPurpose" placeholder="Purpose" aria-label="Purpose" data-column="6">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" id="btnClearAdvFilter" title="Clear all filters">
                            <i class="ri-filter-off-line"></i> Clear
                        </button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" id="btnRefreshAll" title="Refresh data">
                            <i class="ri-refresh-line"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="approvalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                    <i class="bi bi-clock"></i> Pending Approvals
                    <span class="badge bg-warning ms-1" id="pendingCount">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                    <i class="bi bi-check-circle"></i> Approved
                    <span class="badge bg-success ms-1" id="approvedCount">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">
                    <i class="bi bi-x-circle"></i> Rejected
                    <span class="badge bg-danger ms-1" id="rejectedCount">0</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="approvalTabsContent">
            <!-- Pending Tab -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <div class="table-responsive">
                    <table id="pendingTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th width="40">Sr.No</th>
                                <th>Employee</th>
                                <th>Activity</th>
                                <th width="60">Month</th>
                                <th>Crops</th>
                                <th>Varieties</th>
                                <th>Purpose</th>
                                <th width="80">Amount</th>
                                <th width="80">Date</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Approved Tab -->
            <div class="tab-pane fade" id="approved" role="tabpanel">
                <div class="table-responsive">
                    <table id="approvedTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th width="40">Sr.No</th>
                                <th>Employee</th>
                                <th>Activity</th>
                                <th width="60">Month</th>
                                <th>Crops</th>
                                <th>Varieties</th>
                                <th>Purpose</th>
                                <th width="80">Amount</th>
                                <th width="80">Approved Date</th>
                                <th width="60">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rejected Tab -->
            <div class="tab-pane fade" id="rejected" role="tabpanel">
                <div class="table-responsive">
                    <table id="rejectedTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th width="40">Sr.No</th>
                                <th>Employee</th>
                                <th>Activity</th>
                                <th width="60">Month</th>
                                <th>Crops</th>
                                <th>Varieties</th>
                                <th>Purpose</th>
                                <th width="150">Rejected Remark</th>
                                <th width="80">Rejected Date</th>
                                <th width="60">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #dde6ff; color: #1e3a8a;">
                <h5 class="modal-title">
                    <i class="bi bi-file-text"></i> Request Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailModalBody" style="font-size: 0.8rem; max-height: 70vh; overflow-y: auto;">
                <!-- Details will be loaded here via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal (Will be dynamically created) -->
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css') }}">
<link href="{{ asset('vendor/cdn/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
<link href="{{ asset('vendor/cdn/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css') }}" rel="stylesheet" />
<style>
    body {
        background-color: #f8f9fa;
        font-family: Arial, sans-serif;
        font-size: 0.8rem;
    }

    .advance-approvals {
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

    .table-striped > tbody > tr:nth-of-type(odd) > td {
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

    .status-pending {
        background-color: #fff3e0;
        color: #f57c00;
    }

    .status-approved {
        background-color: #e8f5e8;
        color: #388e3c;
    }

    .status-rejected {
        background-color: #ffebee;
        color: #d32f2f;
    }

    /* Amount styling */
    .amount-display {
        font-weight: 600;
        color: #155997;
        font-size: 0.8rem;
    }

    /* Action buttons */
    .btn-approve {
        background-color: #d7f3e6;
        color: #065f46;
        border: none;
        border-radius: 3px;
        padding: 2px 6px;
        font-size: 0.65rem;
        cursor: pointer;
    }

    .btn-approve:hover {
        background-color: #d7f3e6;
        color: #065f46;
    }

    .btn-reject {
        background-color: #fde2e2;
        color: #9b1c1c;
        border: none;
        border-radius: 3px;
        padding: 2px 6px;
        font-size: 0.65rem;
        cursor: pointer;
    }

    .btn-reject:hover {
        background-color: #fde2e2;
        color: #9b1c1c;
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

    /* Detail view styling */
    .detail-section {
        background-color: #f8f9fa;
        padding: 8px;
        border-radius: 4px;
        margin: 4px 0;
        font-size: 0.7rem;
    }

    .detail-label {
        font-weight: 600;
        color: #495057;
        margin-right: 5px;
    }

    .crop-tag, .variety-tag {
        display: inline-block;
        background-color: #e3f2fd;
        color: #1976d2;
        padding: 1px 4px;
        border-radius: 6px;
        font-size: 0.6rem;
        margin: 1px;
        white-space: nowrap;
    }

    .variety-tag {
        background-color: #f3e5f5;
        color: #7b1fa2;
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

    /* Compact row styling */
    .compact-row {
        line-height: 1.2;
    }

    .employee-info {
        font-weight: 600;
        color: #155997;
    }

    /* Form styling */
    .form-control-sm {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .approval-form {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        margin-top: 5px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .advance-approvals {
            margin: 5px;
            padding: 8px;
        }

        .page-header h3 {
            font-size: 1.1rem;
        }

        .table {
            font-size: 0.7rem;
        }

        .table th, .table td {
            padding: 3px 5px;
        }
    }

    /* Approval stages styling */
    .approval-item {
        transition: all 0.2s ease;
    }

    .approval-item:hover {
        background-color: #e9ecef !important;
    }

    .finance-item {
        transition: all 0.2s ease;
    }

    .finance-item:hover {
        background-color: #e9ecef !important;
    }

    /* Nav tabs styling */
    .nav-tabs .nav-link {
        font-size: 0.8rem;
        padding: 8px 16px;
    }

    .nav-tabs .nav-link.active {
        font-weight: 600;
    }

    /* Badge adjustments */
    .nav-link .badge {
        font-size: 0.6rem;
        padding: 1px 4px;
        margin-left: 4px;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/datatables.net@1.13.6/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-responsive-bs5@2.5.0/js/responsive.bootstrap5.min.js') }}"></script>
<script>
    // ==========================================
    // GLOBAL VARIABLES
    // ==========================================
    const authData = document.getElementById('authData');
    const empId = authData ? authData.dataset.employeeId : '';
    const empName = authData ? authData.dataset.employeeName : '';
    const empCode = authData ? authData.dataset.employeeCode : '';
    const department = authData ? authData.dataset.department : '';
    const companyId = authData ? parseInt(authData.dataset.companyId) : 0;
    const yearId = authData ? parseInt(authData.dataset.fyearId) : 0;
    const userId = authData ? parseInt(authData.dataset.userId) : 0;
    const apiBaseUrl = authData ? authData.dataset.apiBaseUrl : '{{ url('api') }}';
    const csrfToken = authData ? authData.dataset.csrfToken : '';
    
    // API endpoints — relative paths, no base URL needed (same-origin requests)
    const apiEndpoints = {
        pending:  '/api/advance/approvals/pending',
        approved: '/api/advance/approvals/approved',
        rejected: '/api/advance/approvals/rejected',
        approve:  '/api/advance/approve',
        reject:   '/api/advance/reject'
    };
    
    let pendingTable, approvedTable, rejectedTable;
    let pendingRequests = [];
    let approvedRequests = [];
    let rejectedRequests = [];

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    function showLoading() {
        $('#loadingOverlay').show();
    }

    function hideLoading() {
        $('#loadingOverlay').hide();
    }

    function showMessage(message, type = 'danger') {
        const messageDiv = $("#Message");
        messageDiv.removeClass('alert-success alert-danger alert-warning')
                  .addClass(`alert-${type}`)
                  .html(`<i class="bi bi-exclamation-triangle me-2"></i>${message}`)
                  .show();
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            messageDiv.fadeOut();
        }, 5000);
    }

    function hideMessage() {
        $("#Message").hide();
    }

    function showModalMessage(message, type = 'danger') {
        const messageDiv = $("#modalMessage");
        messageDiv.removeClass('alert-success alert-danger alert-warning')
                  .addClass(`alert-${type}`)
                  .html(`<i class="bi bi-exclamation-triangle me-2"></i>${message}`)
                  .show();
    }

    function hideModalMessage() {
        $("#modalMessage").hide();
    }

    function formatShortDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatAmount(amount) {
        if (!amount) return '₹0';
        const numAmount = typeof amount === 'string' ? parseFloat(amount.replace(/,/g, '')) : amount;
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(numAmount);
    }

    function getStatusBadgeClass(status) {
        if (!status) return 'status-pending';
        
        switch (status.toString().toLowerCase()) {
            case 'pending':
                return 'status-pending';
            case 'approved':
                return 'status-approved';
            case 'rejected':
                return 'status-rejected';
            default:
                return 'status-pending';
        }
    }

    function getMonthName(monthNumber) {
        if (!monthNumber || monthNumber < 1 || monthNumber > 12) {
            return 'Unknown';
        }
        
        const months = [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ];
        return months[monthNumber - 1] || 'Unknown';
    }

    // ==========================================
    // API CALL FUNCTIONS
    // ==========================================
    function fetchPendingRequests() {
        if (!empId) {
            showMessage('User not authenticated', 'warning');
            return;
        }

        showLoading();
        $.ajax({
            url: apiEndpoints.pending,
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Authorization': `Bearer ${getAuthToken()}` // Add if your API uses token auth
            },
            data: {
                user_id: empId,
                company_id: companyId,
                fyear_id: yearId
            },
            success: function(response) {
                hideLoading();
                console.log('Pending Requests Response:', response);
                
                if (response.success && response.data) {
                    pendingRequests = response.data;
                    initializePendingTable();
                    $('#pendingCount').text(pendingRequests.length);
                } else {
                    pendingRequests = [];
                    initializePendingTable();
                    if (!response.success) {
                        showMessage(response.message || 'Error loading pending requests', 'warning');
                    }
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Error fetching pending requests:', error);
                
                let errorMsg = 'Error fetching pending requests';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                } else {
                    errorMsg += ': ' + error;
                }
                
                showMessage(errorMsg);
                pendingRequests = [];
                initializePendingTable();
            }
        });
    }

    function fetchApprovedRequests() {
        if (!empId) return;

        $.ajax({
            url: apiEndpoints.approved,
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Authorization': `Bearer ${getAuthToken()}`
            },
            data: {
                user_id: empId,
                company_id: companyId,
                fyear_id: yearId
            },
            success: function(response) {
                console.log('Approved Requests Response:', response);
                
                if (response.success && response.data) {
                    approvedRequests = response.data;
                    initializeApprovedTable();
                    $('#approvedCount').text(approvedRequests.length);
                } else {
                    approvedRequests = [];
                    initializeApprovedTable();
                    if (response.message) {
                        console.warn('Approved requests warning:', response.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching approved requests:', error);
                approvedRequests = [];
                initializeApprovedTable();
            }
        });
    }

    function fetchRejectedRequests() {
        if (!empId) return;

        $.ajax({
            url: apiEndpoints.rejected,
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Authorization': `Bearer ${getAuthToken()}`
            },
            data: {
                user_id: empId,
                company_id: companyId,
                fyear_id: yearId
            },
            success: function(response) {
                console.log('Rejected Requests Response:', response);
                
                if (response.success && response.data) {
                    rejectedRequests = response.data;
                    initializeRejectedTable();
                    $('#rejectedCount').text(rejectedRequests.length);
                } else {
                    rejectedRequests = [];
                    initializeRejectedTable();
                    if (response.message) {
                        console.warn('Rejected requests warning:', response.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching rejected requests:', error);
                rejectedRequests = [];
                initializeRejectedTable();
            }
        });
    }

    // ==========================================
    // AUTHENTICATION HELPERS
    // ==========================================
    function getAuthToken() {
        // Try to get token from localStorage or sessionStorage
        return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token') || '';
    }

    // ==========================================
    // DATATABLE FUNCTIONS
    // ==========================================
    function initializePendingTable() {
        if (pendingTable) {
            pendingTable.destroy();
        }

        pendingTable = $('#pendingTable').DataTable({
            data: pendingRequests,
            responsive: true,
            pageLength: 25,
            order: [[7, 'desc']], // Sort by date descending
            columns: [
                {
                    title: 'Sr.No',
                    data: null,
                    orderable: false,
                    searchable: false,
                    width: '40px',
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    title: 'Employee',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        const empName = row.employee_name || 'Unknown';
                        const empCode = row.employee_code || '-';
                        const department = row.department || '-';
                        return `<div class="compact-row">
                            <div class="employee-info">${empName}</div>
                            <small class="text-muted">${empCode}</small>
                            <small class="text-muted d-block">${department}</small>
                        </div>`;
                    }
                },
                {
                    title: 'Activity',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        const activityName = (row.req_data && row.req_data.activity_name) || '';
                        const advanceType = (row.req_data && row.req_data.advance_type_name) || 'Unknown Type';
                        return `<div class="compact-row">
                            <div style="font-weight: 600; font-size: 0.75rem;">${activityName}</div>
                            <small class="text-muted">${advanceType}</small>
                        </div>`;
                    }
                },
                {
                    title: 'Month',
                    data: null,
                    width: '60px',
                    render: function(data, type, row) {
                        const month = (row.req_data && row.req_data.month) || 0;
                        return getMonthName(month);
                    }
                },
                {
                    title: 'Crops',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        let cropsHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const allCrops = [];
                            row.activities.forEach(activity => {
                                if (activity.crop_names) {
                                    allCrops.push(...activity.crop_names);
                                }
                            });
                            const uniqueCrops = [...new Set(allCrops)];
                            cropsHtml = uniqueCrops.slice(0, 3).map(crop => 
                                `<span class="crop-tag">${crop}</span>`
                            ).join('');
                            if (uniqueCrops.length > 3) {
                                cropsHtml += `<span class="crop-tag">+${uniqueCrops.length - 3}</span>`;
                            }
                        }
                        return cropsHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Varieties',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        let varietiesHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const allVarieties = [];
                            row.activities.forEach(activity => {
                                if (activity.variety_names) {
                                    allVarieties.push(...activity.variety_names);
                                }
                            });
                            const uniqueVarieties = [...new Set(allVarieties)];
                            varietiesHtml = uniqueVarieties.slice(0, 2).map(variety => 
                                `<span class="variety-tag">${variety}</span>`
                            ).join('');
                            if (uniqueVarieties.length > 2) {
                                varietiesHtml += `<span class="variety-tag">+${uniqueVarieties.length - 2}</span>`;
                            }
                        }
                        return varietiesHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Purpose',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        let purposeHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const purposes = row.activities.map(activity => activity.purpose).filter(p => p);
                            if (purposes.length > 0) {
                                const allPurposes = purposes.join('; ');
                                const truncated = allPurposes.length > 60 ? allPurposes.substring(0, 60) + '...' : allPurposes;
                                purposeHtml = `<span title="${allPurposes}" style="font-size: 0.7rem;">${truncated}</span>`;
                            }
                        }
                        return purposeHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Amount',
                    data: null,
                    width: '80px',
                    render: function(data, type, row) {
                        // ADV-AMT-01: a later approver sees the amount the previous level approved
                        const asked = parseFloat(row.total_pending_amount || row.total_amount || 0);
                        let amount = 0, any = false;
                        (row.activities || []).forEach(a => {
                            const ok = (a.approvals || []).filter(p => (p.approver_status || '').toLowerCase() === 'approved' && parseFloat(p.approver_amount) > 0);
                            if (ok.length) { any = true; amount += parseFloat(ok[ok.length - 1].approver_amount); } else { amount += parseFloat(a.amount) || 0; }
                        });
                        if (!any) amount = asked;
                        return `<div class="amount-display">${formatAmount(amount)}</div>` +
                            (any && Math.abs(asked - amount) > 0.009 ? `<div class="text-muted" style="font-size:0.6rem;"><s>${formatAmount(asked)}</s> asked</div>` : '');
                    }
                },
                {
                    title: 'Date',
                    data: 'created_at',
                    width: '80px',
                    render: function(data, type, row) {
                        return `<span style="font-size: 0.7rem;">${data ? formatDate(data) : '-'}</span>`;
                    }
                },
                {
                    title: 'Actions',
                    data: null,
                    orderable: false,
                    width: '120px',
                    render: function(data, type, row) {
                        return `
                            <div style="display: flex; gap: 2px;">
                            <button class="btn btn-approve" onclick="showApprovalForm(${row.id}, 'approve')" style="font-size: 0.6rem; padding: 1px 4px;">
                                <i class="bi bi-check"></i> Approve
                            </button>
                            <button class="btn btn-reject" onclick="showApprovalForm(${row.id}, 'reject')" style="font-size: 0.6rem; padding: 1px 4px;">
                                <i class="bi bi-x"></i> Reject
                            </button>
                            <button class="btn btn-detail" onclick="showDetails(${row.id}, 'pending')" style="font-size: 0.6rem; padding: 1px 4px;">
                                <i class="bi bi-eye"></i> View
                            </button>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                emptyTable: "No pending approvals found",
                info: "Showing _START_ to _END_ of _TOTAL_ requests",
                search: "Search requests:"
            }
        });
    }

    function initializeApprovedTable() {
        if (approvedTable) {
            approvedTable.destroy();
        }

        approvedTable = $('#approvedTable').DataTable({
            data: approvedRequests,
            responsive: true,
            pageLength: 25,
            order: [[7, 'desc']], // Sort by amount descending
            columns: [
                {
                    title: 'Sr.No',
                    data: null,
                    orderable: false,
                    searchable: false,
                    width: '40px',
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    title: 'Employee',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        const empName = row.employee_name || 'Unknown';
                        const empCode = row.employee_code || '-';
                        const department = row.department || '-';
                        return `<div class="compact-row">
                            <div class="employee-info">${empName}</div>
                            <small class="text-muted">${empCode}</small>
                            <small class="text-muted d-block">${department}</small>
                        </div>`;
                    }
                },
                {
                    title: 'Activity',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        const activityName = (row.req_data && row.req_data.activity_name) || '';
                        const advanceType = (row.req_data && row.req_data.advance_type_name) || 'Unknown Type';
                        return `<div class="compact-row">
                            <div style="font-weight: 600; font-size: 0.75rem;">${activityName}</div>
                            <small class="text-muted">${advanceType}</small>
                        </div>`;
                    }
                },
                {
                    title: 'Month',
                    data: null,
                    width: '60px',
                    render: function(data, type, row) {
                        const month = (row.req_data && row.req_data.month) || 0;
                        return getMonthName(month);
                    }
                },
                {
                    title: 'Crops',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        let cropsHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const allCrops = [];
                            row.activities.forEach(activity => {
                                if (activity.crop_names) {
                                    allCrops.push(...activity.crop_names);
                                }
                            });
                            const uniqueCrops = [...new Set(allCrops)];
                            cropsHtml = uniqueCrops.slice(0, 3).map(crop => 
                                `<span class="crop-tag">${crop}</span>`
                            ).join('');
                            if (uniqueCrops.length > 3) {
                                cropsHtml += `<span class="crop-tag">+${uniqueCrops.length - 3}</span>`;
                            }
                        }
                        return cropsHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Varieties',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        let varietiesHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const allVarieties = [];
                            row.activities.forEach(activity => {
                                if (activity.variety_names) {
                                    allVarieties.push(...activity.variety_names);
                                }
                            });
                            const uniqueVarieties = [...new Set(allVarieties)];
                            varietiesHtml = uniqueVarieties.slice(0, 2).map(variety => 
                                `<span class="variety-tag">${variety}</span>`
                            ).join('');
                            if (uniqueVarieties.length > 2) {
                                varietiesHtml += `<span class="variety-tag">+${uniqueVarieties.length - 2}</span>`;
                            }
                        }
                        return varietiesHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Purpose',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        let purposeHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const purposes = row.activities.map(activity => activity.purpose).filter(p => p);
                            if (purposes.length > 0) {
                                const allPurposes = purposes.join('; ');
                                const truncated = allPurposes.length > 60 ? allPurposes.substring(0, 60) + '...' : allPurposes;
                                purposeHtml = `<span title="${allPurposes}" style="font-size: 0.7rem;">${truncated}</span>`;
                            }
                        }
                        return purposeHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Amount',
                    data: null,
                    width: '80px',
                    render: function(data, type, row) {
                        let approvedAmount = 0;
                        if (row.activities && row.activities.length > 0) {
                            const activity = row.activities[0];
                            if (activity.approvals && activity.approvals.length > 0) {
                                const latestApproval = activity.approvals[activity.approvals.length - 1];
                                approvedAmount = latestApproval.approver_amount || 0;
                            }
                        }
                        if (approvedAmount === 0) {
                            approvedAmount = row.total_approved_amount || 0;
                        }
                        return `<div class="amount-display">${formatAmount(approvedAmount)}</div>`;
                    }
                },
                {
                    title: 'Approved Date',
                    data: 'updated_at',
                    width: '80px',
                    render: function(data, type, row) {
                        return `<span style="font-size: 0.7rem;">${data ? formatDate(data) : '-'}</span>`;
                    }
                },
                {
                    title: 'Action',
                    data: null,
                    orderable: false,
                    width: '60px',
                    render: function(data, type, row) {
                        return `<button class="btn btn-detail" onclick="showDetails(${row.id}, 'approved')" style="font-size: 0.6rem; padding: 1px 4px;">
                            <i class="bi bi-eye"></i> View
                        </button>`;
                    }
                }
            ],
            language: {
                emptyTable: "No approved requests found",
                info: "Showing _START_ to _END_ of _TOTAL_ requests",
                search: "Search requests:"
            }
        });
    }

    function initializeRejectedTable() {
        if (rejectedTable) {
            rejectedTable.destroy();
        }

        rejectedTable = $('#rejectedTable').DataTable({
            data: rejectedRequests,
            responsive: true,
            pageLength: 25,
            order: [[8, 'desc']], // Sort by rejected date descending
            columns: [
                {
                    title: 'Sr.No',
                    data: null,
                    orderable: false,
                    searchable: false,
                    width: '40px',
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    title: 'Employee',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        const empName = row.employee_name || 'Unknown';
                        const empCode = row.employee_code || '-';
                        const department = row.department || '-';
                        return `<div class="compact-row">
                            <div class="employee-info">${empName}</div>
                            <small class="text-muted">${empCode}</small>
                            <small class="text-muted d-block">${department}</small>
                        </div>`;
                    }
                },
                {
                    title: 'Activity',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        const activityName = (row.req_data && row.req_data.activity_name) || '';
                        const advanceType = (row.req_data && row.req_data.advance_type_name) || 'Unknown Type';
                        return `<div class="compact-row">
                            <div style="font-weight: 600; font-size: 0.75rem;">${activityName}</div>
                            <small class="text-muted">${advanceType}</small>
                        </div>`;
                    }
                },
                {
                    title: 'Month',
                    data: null,
                    width: '60px',
                    render: function(data, type, row) {
                        const month = (row.req_data && row.req_data.month) || 0;
                        return getMonthName(month);
                    }
                },
                {
                    title: 'Crops',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        let cropsHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const allCrops = [];
                            row.activities.forEach(activity => {
                                if (activity.crop_names) {
                                    allCrops.push(...activity.crop_names);
                                }
                            });
                            const uniqueCrops = [...new Set(allCrops)];
                            cropsHtml = uniqueCrops.slice(0, 3).map(crop => 
                                `<span class="crop-tag">${crop}</span>`
                            ).join('');
                            if (uniqueCrops.length > 3) {
                                cropsHtml += `<span class="crop-tag">+${uniqueCrops.length - 3}</span>`;
                            }
                        }
                        return cropsHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Varieties',
                    data: null,
                    width: '120px',
                    render: function(data, type, row) {
                        let varietiesHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const allVarieties = [];
                            row.activities.forEach(activity => {
                                if (activity.variety_names) {
                                    allVarieties.push(...activity.variety_names);
                                }
                            });
                            const uniqueVarieties = [...new Set(allVarieties)];
                            varietiesHtml = uniqueVarieties.slice(0, 2).map(variety => 
                                `<span class="variety-tag">${variety}</span>`
                            ).join('');
                            if (uniqueVarieties.length > 2) {
                                varietiesHtml += `<span class="variety-tag">+${uniqueVarieties.length - 2}</span>`;
                            }
                        }
                        return varietiesHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Purpose',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        let purposeHtml = '';
                        if (row.activities && row.activities.length > 0) {
                            const purposes = row.activities.map(activity => activity.purpose).filter(p => p);
                            if (purposes.length > 0) {
                                const allPurposes = purposes.join('; ');
                                const truncated = allPurposes.length > 60 ? allPurposes.substring(0, 60) + '...' : allPurposes;
                                purposeHtml = `<span title="${allPurposes}" style="font-size: 0.7rem;">${truncated}</span>`;
                            }
                        }
                        return purposeHtml || '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Rejected Remark',
                    data: null,
                    width: '150px',
                    render: function(data, type, row) {
                        let rejectedRemark = '';
                        if (row.activities && row.activities.length > 0) {
                            const activity = row.activities[0];
                            if (activity.approvals && activity.approvals.length > 0) {
                                const rejectedApproval = activity.approvals.find(approval => 
                                    approval.approver_status && approval.approver_status.toLowerCase() === 'rejected'
                                );
                                if (rejectedApproval && rejectedApproval.approver_remark) {
                                    rejectedRemark = rejectedApproval.approver_remark;
                                }
                            }
                        }
                        
                        if (rejectedRemark) {
                            const truncated = rejectedRemark.length > 50 ? rejectedRemark.substring(0, 50) + '...' : rejectedRemark;
                            return `<span title="${rejectedRemark}" style="font-size: 0.7rem; color: #dc3545;">${truncated}</span>`;
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: 'Rejected Date',
                    data: 'updated_at',
                    width: '80px',
                    render: function(data, type, row) {
                        return `<span style="font-size: 0.7rem;">${data ? formatDate(data) : '-'}</span>`;
                    }
                },
                {
                    title: 'Action',
                    data: null,
                    orderable: false,
                    width: '60px',
                    render: function(data, type, row) {
                        return `<button class="btn btn-detail" onclick="showDetails(${row.id}, 'rejected')" style="font-size: 0.6rem; padding: 1px 4px;">
                            <i class="bi bi-eye"></i> View
                        </button>`;
                    }
                }
            ],
            language: {
                emptyTable: "No rejected requests found",
                info: "Showing _START_ to _END_ of _TOTAL_ requests",
                search: "Search requests:"
            }
        });
    }

    // ==========================================
    // APPROVAL FUNCTIONS
    // ==========================================
    function showApprovalForm(requestId, action) {
        const request = pendingRequests.find(req => req.id === requestId);
        if (!request) {
            showMessage('Request not found', 'warning');
            return;
        }

        // Get the last approved amount for multi-level approvals
        let lastApprovedAmount = request.total_pending_amount || request.total_amount || 0;
        
        // Check if this is a multi-level approval by looking at activities
        if (request.activities && request.activities.length > 0) {
            const activity = request.activities[0];
            if (activity.approvals && activity.approvals.length > 0) {
                // Find the last approved amount from previous approvers
                const approvedApprovals = activity.approvals.filter(approval => 
                    approval.approver_status && approval.approver_status.toLowerCase() === 'approved' && 
                    approval.approver_amount
                );
                if (approvedApprovals.length > 0) {
                    // Use the last approved amount
                    lastApprovedAmount = approvedApprovals[approvedApprovals.length - 1].approver_amount;
                }
            }
        }

        const actionTitle = action === 'approve' ? 'Approve' : 'Reject';
        const actionClass = action === 'approve' ? 'btn-success' : 'btn-danger';
        
        // Show approval level information if multi-level
        let approvalLevelInfo = '';
        if (request.activities && request.activities.length > 0) {
            const activity = request.activities[0];
            if (activity.approvals && activity.approvals.length > 0) {
                // Get the last approved approval
                const approvedApprovals = activity.approvals.filter(approval => 
                    approval.approver_status && approval.approver_status.toLowerCase() === 'approved'
                );
                if (approvedApprovals.length > 0) {
                    const lastApproval = approvedApprovals[approvedApprovals.length - 1];
                    approvalLevelInfo = `
                        <div class="mb-2">
                            <div class="alert alert-info" style="padding: 8px; font-size: 0.65rem;">
                                <div style="font-weight: 600; margin-bottom: 4px; color: #155997;">
                                    <i class="bi bi-info-circle"></i> Previous Level: ${lastApproval.approver_name || 'Unknown'}
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Date: <strong>${lastApproval.approver_date ? formatShortDate(lastApproval.approver_date) : 'N/A'}</strong></span>
                                    <span>Amount: <strong style="color: #155997;">${lastApproval.approver_amount ? formatAmount(lastApproval.approver_amount) : 'N/A'}</strong></span>
                                </div>
                                ${lastApproval.approver_remark ? `
                                <div style="margin-top: 4px; padding: 4px; background-color: white; border-radius: 3px; font-size: 0.6rem;">
                                    <strong>Remark:</strong> ${lastApproval.approver_remark}
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }
            }
        }

        // Add history information if available
        let historyInfo = renderHistoryCard(request);

        const amountField = action === 'approve' ? `
            <div class="mb-3">
                <label class="form-label">Approved Amount (₹)</label>
                <input type="number" class="form-control form-control-sm" id="approvalAmount" 
                       value="${lastApprovedAmount}" min="0" step="0.01" required data-max="${lastApprovedAmount}"
                       maxlength="6" oninput="updateAmountCounter(this)">
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">You can lower the amount now; it cannot be changed after you approve.</small>
                    <small class="text-muted" id="amountCounter">0/6</small>
                </div>
            </div>
        ` : '';

        const modalHtml = `
            <div class="modal fade" id="approvalModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header" style="background-color: ${action === 'approve' ? '#28a745' : '#dc3545'}; color: white;">
                            <h5 class="modal-title text-white">
                                <i class="bi bi-${action === 'approve' ? 'check-circle' : 'x-circle'}"></i> ${actionTitle} Request
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            <!-- Modal Alert Message -->
                            <div id="modalMessage" class="alert" style="display: none; margin-bottom: 15px;"></div>
                            
                            <div class="row">
                                <!-- Left Column - Request Details & Action -->
                                <div class="col-8">
                                    <div class="mb-3">
                                        <strong>Employee:</strong> ${request.employee_name} (${request.employee_code})<br>
                                        <strong>Activity:</strong> ${request.req_data.activity_name}<br>
                                        <strong>Month:</strong> ${getMonthName(request.req_data.month)}<br>
                                        <strong>Total Amount:</strong> ${formatAmount(request.total_pending_amount || request.total_amount || 0)}
                                    </div>
                                    
                                    ${approvalLevelInfo}
                                    
                                    ${amountField}
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Remarks ${action === 'reject' ? '(Required)' : '(Optional)'}</label>
                                        <textarea class="form-control form-control-sm" id="approvalRemark" 
                                                  rows="3" placeholder="Enter your remarks..." aria-label="Enter your remarks" ${action === 'reject' ? 'required' : ''}
                                                  maxlength="200" oninput="updateRemarkCounter(this)"></textarea>
                                        <div class="d-flex justify-content-end mt-1">
                                            <small class="text-muted" id="remarkCounter">0/200</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column - History -->
                                <div class="col-4">
                                    ${historyInfo}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn ${actionClass} btn-sm" onclick="submitApproval(${requestId}, '${action}')">
                                ${actionTitle}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        $('#approvalModal').remove();
        
        // Add new modal to body
        $('body').append(modalHtml);
        
        // Initialize counters
        if (action === 'approve') {
            updateAmountCounter($('#approvalAmount')[0]);
        }
        updateRemarkCounter($('#approvalRemark')[0]);
        
        // Show modal
        $('#approvalModal').modal('show');
    }

    function submitApproval(requestId, action) {
        // Hide any previous modal messages
        hideModalMessage();
        
        const request = pendingRequests.find(req => req.id === requestId);
        if (!request) {
            showModalMessage('Request not found', 'warning');
            return;
        }

        const remark = $('#approvalRemark').val().trim();
        
        // Validate remark length (200 characters max)
        if (remark.length > 200) {
            showModalMessage('Remarks cannot exceed 200 characters', 'warning');
            return;
        }
        
        // Use advance_request_id instead of id for the API call
        let payload = {
            employee_id: request.employee_id,
            request_id: request.advance_request_id || requestId,
            user_id: empId,
            company_id: companyId,
            fyear_id: yearId
        };

        let apiUrl = action === 'approve' ? apiEndpoints.approve : apiEndpoints.reject;

        if (action === 'approve') {
            const amount = parseFloat($('#approvalAmount').val());
            // ADV-AMT-02: the cap is the amount carried from the earlier stage (the asked amount at the first stage)
            const totalAmount = parseFloat($('#approvalAmount').data('max')) || request.total_pending_amount || request.total_amount || 0;
            
            if (!amount || amount <= 0) {
                showModalMessage('Please enter a valid approved amount greater than 0', 'warning');
                return;
            }
            if ($('#approvalAmount').val().length > 6) {
                showModalMessage('Amount cannot exceed 6 digits', 'warning');
                return;
            }
            if (amount > totalAmount) {
                showModalMessage(`Approved amount (${formatAmount(amount)}) cannot exceed ${formatAmount(totalAmount)}`, 'warning');
                return;
            }
            
            payload.approver_amount = amount;
            if (remark) {
                payload.approver_remark = remark;
            }
        } else {
            if (!remark) {
                showModalMessage('Please enter rejection reason', 'warning');
                return;
            }
            payload.remarks = remark;
        }

        showLoading();
        $.ajax({
            url: apiUrl,
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Authorization': `Bearer ${getAuthToken()}`,
                'X-CSRF-TOKEN': csrfToken // For Laravel CSRF protection
            },
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(response) {
                hideLoading();
                console.log('Approval Response:', response);
                
                if (response.success) {
                    // Show success message in modal first
                    showModalMessage(`Request ${action}d successfully!`, 'success');
                    
                    // Close modal after 2 seconds and refresh data
                    setTimeout(() => {
                        $('#approvalModal').modal('hide');
                        showMessage(`Request ${action}d successfully!`, 'success');
                        
                        // Refresh the data
                        fetchPendingRequests();
                        if (action === 'approve') {
                            fetchApprovedRequests();
                        } else {
                            fetchRejectedRequests();
                        }
                    }, 2000);
                } else {
                    // Show more descriptive error messages
                    let errorMessage = response.message || `Error ${action}ing request`;
                    
                    // Handle specific error cases
                    if (errorMessage.includes('exceed total activities amount')) {
                        errorMessage = 'Approved amount cannot exceed the total requested amount for activities';
                    } else if (errorMessage.includes('approval amount cannot exceed')) {
                        errorMessage = 'The approved amount is higher than the requested amount';
                    } else if (errorMessage.includes('Invalid')) {
                        errorMessage = 'Invalid request data. Please check the details and try again';
                    } else if (errorMessage.includes('not found')) {
                        errorMessage = 'Request not found. It may have been processed by another approver';
                    } else if (errorMessage.includes('already processed')) {
                        errorMessage = 'This request has already been processed';
                    }
                    
                    showModalMessage(errorMessage, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                
                let errorMessage = `Error ${action}ing request`;
                
                // Try to parse error response for better messages
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                        
                        // Handle specific API error messages
                        if (errorMessage.includes('exceed total activities amount')) {
                            errorMessage = 'Approved amount cannot exceed the total requested amount for activities';
                        } else if (errorMessage.includes('validation failed')) {
                            errorMessage = 'Validation failed. Please check all required fields';
                        } else if (errorMessage.includes('unauthorized')) {
                            errorMessage = 'You are not authorized to perform this action';
                        } else if (errorMessage.includes('Invalid token')) {
                            errorMessage = 'Session expired. Please refresh the page and login again';
                        }
                    }
                } catch (e) {
                    // If response is not JSON, use generic message based on status
                    if (xhr.status === 404) {
                        errorMessage = 'API endpoint not found. Please check the configuration';
                    } else if (xhr.status === 403) {
                        errorMessage = 'You do not have permission to perform this action';
                    } else if (xhr.status === 401) {
                        errorMessage = 'Authentication required. Please login again';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred. Please try again later';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your internet connection';
                    }
                }
                
                showModalMessage(errorMessage, 'danger');
            }
        });
    }

    // ==========================================
    // DETAIL VIEW FUNCTIONS
    // ==========================================
    function showDetails(requestId, tab) {
        let request;
        
        switch(tab) {
            case 'pending':
                request = pendingRequests.find(req => req.id === requestId);
                break;
            case 'approved':
                request = approvedRequests.find(req => req.id === requestId);
                break;
            case 'rejected':
                request = rejectedRequests.find(req => req.id === requestId);
                break;
        }
        
        if (!request) {
            showMessage('Request not found', 'warning');
            return;
        }

        const modalBody = $('#detailModalBody');
        modalBody.html(renderRequestDetails(request));
        $('#detailModal').modal('show');
    }

    function renderRequestDetails(request) {
        const reqData = request.req_data || {};
        const totalAmount = request.activities ? request.activities.reduce((sum, activity) => sum + parseFloat(activity.amount), 0) : (request.total_pending_amount || request.total_amount || 0);
        
        let html = `
            <div class="detail-section">
                <div class="row">
                    <div class="col-4">
                        <span class="detail-label">Employee:</span> ${request.employee_name || 'Unknown'} (${request.employee_code || ''})
                    </div>
                    <div class="col-4">
                        <span class="detail-label">Department:</span> ${request.department || ''}
                    </div>
                    <div class="col-4">
                        <span class="detail-label">Activity:</span> ${reqData ? reqData.activity_name : ''}
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-4">
                        <span class="detail-label">Advance Type:</span> ${reqData ? reqData.advance_type_name : 'Unknown Type'}
                    </div>
                    <div class="col-4">
                        <span class="detail-label">Month:</span> ${reqData ? getMonthName(reqData.month) : 'Unknown'}
                    </div>
                    <div class="col-4">
                        <span class="detail-label">Total Amount:</span> 
                        <span class="amount-display">${formatAmount(totalAmount)}</span>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-4">
                        <span class="detail-label">Status:</span> 
                        ${(() => {
                            let status = 'pending';
                            if (request.activities && request.activities.length > 0) {
                                status = request.activities[0].approval_status || 'pending';
                            }
                            return `<span class="status-badge ${getStatusBadgeClass(status)}">${status}</span>`;
                        })()}
                    </div>
                    <div class="col-4">
                        <span class="detail-label">Created:</span> ${formatDate(request.created_at)}
                    </div>
                    <div class="col-4">
                        <!-- Empty for spacing -->
                    </div>
                </div>
            </div>

            <h6 class="mt-3 mb-2" style="color: #155997;">
                <i class="bi bi-list-task"></i> Activities (${request.activities ? request.activities.length : 0})
            </h6>
        `;

        if (request.activities && request.activities.length > 0) {
            request.activities.forEach((activity, index) => {
                html += `
                    <div class="detail-section">
                        <div class="row mb-2">
                            <div class="col-4">
                                 <span class="detail-label">Activity Type:</span><br>
                                ${activity.activity_type_name || ''}
                            </div>
                            <div class="col-4">
                                <span class="detail-label">Crops:</span><br>
                                ${activity.crop_names ? activity.crop_names.map(crop => `<span class="crop-tag">${crop}</span>`).join('') : '<span class="text-muted">No crops</span>'}
                            </div>
                            <div class="col-4">
                                ${activity.variety_names ? `
                                    <span class="detail-label">Varieties:</span><br>
                                    ${activity.variety_names.map(variety => `<span class="variety-tag">${variety}</span>`).join('')}
                                ` : '<span class="text-muted">No varieties selected</span>'}
                            </div>

                        </div>
                        <div class="row">
                            <div class="col-12">
                                <span class="detail-label">Purpose:</span>
                                <div style="margin-top: 4px; padding: 4px">
                                    ${activity.purpose || 'No purpose specified'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            html += `
                <div class="detail-section">
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-info-circle"></i> No activity details available
                    </div>
                </div>
            `;
        }

        // Add Approvers section below activities
        html += renderApproversSection(request.activities || []);

        // Add Finance section below approvers
        html += renderFinanceSection(request.activities || []);

        // Add Payment section below finance
        html += renderPaymentSection(request.activities || []);

        return html;
    }

    function renderHistoryCard(request) {
        if (request.req_data && request.req_data.approver_advance_history && request.req_data.approver_advance_history.length > 0) {
            return `
                <div class="card" style="border: 1px solid #dee2e6; height: fit-content;">
                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px;">
                        <i class="bi bi-clock-history"></i> Advance History (${request.req_data.approver_advance_history.length})
                    </div>
                    <div class="card-body" style="padding: 8px; max-height: 400px; overflow-y: auto;">
                        ${request.req_data.approver_advance_history.map(history => `
                            <div class="mb-3 p-2" style="background-color: #f8f9fa; border-radius: 4px; border-left: 3px solid #17a2b8;">
                                <!-- Activity Header -->
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong style="font-size: 0.75rem; color: #155997;">${history.activity_name}</strong>
                                    <span style="font-size: 0.6rem; color: #666;">${history.advance_type_name} • ${getMonthName(history.month)}</span>
                                </div>
                                
                                <!-- Employee Records -->
                                ${history.approved_employees && history.approved_employees.length > 0 ? history.approved_employees.map(employee => `
                                    <div class="mb-2 p-2" style="background-color: white; border-radius: 3px; border: 1px solid #e9ecef;">
                                        <!-- Row 1: Employee Info -->
                                       
                                        <!-- Row 2: Request and Approved Details -->
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.6rem;">
                                            <div>
                                                <span style="color: #666;">Request:</span>
                                                <span style="color: #155997; font-weight: 600; margin-left: 4px;">${formatAmount(employee.total_amount)}</span>
                                                <div style="color: #999; font-size: 0.55rem;">${formatShortDate(history.submitted_at)}</div>
                                            </div>
                                            <div class="text-end">
                                                <span style="color: #666;">Approved:</span>
                                                <span style="color: #28a745; font-weight: 600; margin-left: 4px;">${formatAmount(employee.current_approver_amount)}</span>
                                                ${employee.approval_levels_summary ? `
                                                <div style="font-size: 0.55rem; color: #999;">
                                                    ${employee.approval_levels_summary.map(level => 
                                                        `L${level.level}:${new Intl.NumberFormat('en-IN').format(level.approved_amount)} (${formatShortDate(level.date)})`
                                                    ).join(' | ')}
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                        
                                        <!-- Row 3: Finance and Payment Details -->
                                        <div class="d-flex justify-content-between align-items-center" style="font-size: 0.6rem;">
                                            <div>
                                                <span style="color: #666;">Finance:</span>
                                                <span style="color: #17a2b8; font-weight: 600; margin-left: 4px;">${formatAmount(employee.total_finance_amount || 0)}</span>
                                                <div style="color: #999; font-size: 0.55rem;">${employee.finance_status || 'Pending'}</div>
                                            </div>
                                            <div class="text-center">
                                                <span style="color: #666;">Payment:</span>
                                                <span style="color: #6f42c1; font-weight: 600; margin-left: 4px;">${formatAmount(employee.total_payment_amount || 0)}</span>
                                                <div style="color: #999; font-size: 0.55rem;">${employee.payment_status || 'Pending'}</div>
                                            </div>
                                            <div class="text-end" style="color: #999; font-size: 0.55rem;">
                                                ${employee.current_approver_date ? formatShortDate(employee.current_approver_date) : 'Pending'}
                                            </div>
                                        </div>
                                    </div>
                                `).join('') : `
                                    <div class="text-muted text-center py-2" style="font-size: 0.65rem;">
                                        No employee details available
                                    </div>
                                `}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            return `
                <div class="card" style="border: 1px solid #dee2e6; height: fit-content;">
                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px;">
                        <i class="bi bi-clock-history"></i> Advance History
                    </div>
                    <div class="card-body" style="padding: 12px;">
                        <div class="text-muted text-center" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle"></i><br>
                            No previous advance history found
                        </div>
                    </div>
                </div>
            `;
        }
    }

    function renderApproversSection(activities) {
        let approversHtml = `
            <h6 class="mt-4 mb-2" style="color: #155997;">
                <i class="bi bi-people"></i> Approvals
            </h6>
        `;

        let hasApprovers = false;

        activities.forEach((activity, activityIndex) => {
            if (activity.approvals && activity.approvals.length > 0) {
                hasApprovers = true;
                
                approversHtml += `
                    <div class="detail-section">
                `;

                activity.approvals.forEach((approval, index) => {
                    const statusClass = getStatusBadgeClass(approval.approver_status);
                    const actionDate = approval.approver_date ? formatDate(approval.approver_date) : '';
                    
                    approversHtml += `
                        <div class="approval-item mb-2 p-3" style="background-color: #f8f9fa; border-radius: 6px; border-left: 4px solid ${getApprovalBorderColor(approval.approver_status)};">
                            <div class="row">
                                <div class="col-6">
                                    <div>
                                        <strong style="font-size: 0.8rem;">${approval.approver_name}</strong>
                                    </div>
                                    <div class="mt-1">
                                        <small class="text-muted">${approval.approver_designation}</small>
                                    </div>
                                    <div class="mt-1">
                                        <span class="status-badge ${statusClass}" style="font-size: 0.65rem;">${approval.approver_status}</span>
                                    </div>
                                </div>
                                <div class="col-6 text-end">
                                    <div>
                                        <small class="text-muted">Action Date:</small>
                                        <div style="font-size: 0.75rem;">${actionDate}</div>
                                    </div>
                                    ${approval.approver_amount ? `
                                    <div class="mt-1">
                                        <small class="text-muted">Approved Amount:</small>
                                        <div class="amount-display" style="font-size: 0.75rem;">${formatAmount(approval.approver_amount)}</div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            
                            ${approval.approver_remark ? `
                            <div class="mt-2">
                                <small class="detail-label">Remark:</small>
                                <div style="font-size: 0.75rem; padding: 6px; background-color: white; border-radius: 4px; margin-top: 4px;">
                                    ${approval.approver_remark}
                                </div>
                            </div>
                            ` : ''}
                            
                            ${approval.revert_reason ? `
                            <div class="mt-2">
                                <small class="detail-label text-warning">Revert Reason:</small>
                                <div style="font-size: 0.75rem; padding: 6px; background-color: #fff3cd; border-radius: 4px; margin-top: 4px;">
                                    ${approval.revert_reason}
                                    ${approval.reverted_by_name ? `<br><small class="text-muted">Reverted by: ${approval.reverted_by_name}</small>` : ''}
                                    ${approval.reverted_at ? `<br><small class="text-muted">Reverted at: ${formatDate(approval.reverted_at)}</small>` : ''}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    `;
                });

                approversHtml += `</div>`;
            }
        });

        if (!hasApprovers) {
            approversHtml += `
                <div class="detail-section">
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-info-circle"></i> No approver information available
                    </div>
                </div>
            `;
        }

        return approversHtml;
    }

    function renderFinanceSection(activities) {
        let financeHtml = `
            <h6 class="mt-4 mb-2" style="color: #155997;">
                <i class="bi bi-currency-rupee"></i> Finance Details
            </h6>
        `;

        let hasFinanceData = false;

        activities.forEach((activity, activityIndex) => {
            if (activity.finance_id || activity.finance_approved_amount || activity.finance_status || activity.finance_remark || activity.finance_date) {
                hasFinanceData = true;
                
                financeHtml += `
                    <div class="detail-section">
                        
                        <div class="finance-item p-3" style="background-color: #f8f9fa; border-radius: 6px; border-left: 4px solid #28a745;">
                            <div class="row">
                                <div class="col-6">
                                    ${activity.finance_status ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Finance Status:</span>
                                        <span class="status-badge ${getStatusBadgeClass(activity.finance_status)} ms-2">${activity.finance_status}</span>
                                    </div>
                                    ` : ''}
                                    
                                    ${activity.finance_id ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Finance ID:</span>
                                        <span style="font-size: 0.75rem;">${activity.finance_id}</span>
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="col-6 text-end">
                                    ${activity.finance_approved_amount ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Approved Amount:</span>
                                        <div class="amount-display" style="font-size: 0.8rem;">${formatAmount(activity.finance_approved_amount)}</div>
                                    </div>
                                    ` : ''}
                                    
                                    ${activity.finance_date ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Finance Date:</span>
                                        <div style="font-size: 0.75rem;">${formatDate(activity.finance_date)}</div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            
                            ${activity.finance_remark ? `
                            <div class="mt-2">
                                <span class="detail-label">Finance Remark:</span>
                                <div style="font-size: 0.75rem; padding: 6px; background-color: white; border-radius: 4px; margin-top: 4px;">
                                    ${activity.finance_remark}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }
        });

        if (!hasFinanceData) {
            financeHtml += `
                <div class="detail-section">
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-info-circle"></i> No finance information available
                    </div>
                </div>
            `;
        }

        return financeHtml;
    }

    function renderPaymentSection(activities) {
        let paymentHtml = `
            <h6 class="mt-4 mb-2" style="color: #155997;">
                <i class="bi bi-credit-card"></i> Payment Details
            </h6>
        `;

        let hasPaymentData = false;

        activities.forEach((activity, activityIndex) => {
            if (activity.payment_id || activity.payment_approved_amount || activity.payment_status || activity.payment_remark || activity.payment_date) {
                hasPaymentData = true;
                
                paymentHtml += `
                    <div class="detail-section">

                        
                        <div class="finance-item p-3" style="background-color: #f8f9fa; border-radius: 6px; border-left: 4px solid #6f42c1;">
                            <div class="row">
                                <div class="col-6">
                                    ${activity.payment_status ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Payment Status:</span>
                                        <span class="status-badge ${getStatusBadgeClass(activity.payment_status)} ms-2">${activity.payment_status}</span>
                                    </div>
                                    ` : ''}
                                    
                                    ${activity.payment_id ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Payment ID:</span>
                                        <span style="font-size: 0.75rem;">${activity.payment_id}</span>
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="col-6 text-end">
                                    ${activity.payment_approved_amount ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Payment Amount:</span>
                                        <div class="amount-display" style="font-size: 0.8rem;">${formatAmount(activity.payment_approved_amount)}</div>
                                    </div>
                                    ` : ''}
                                    
                                    ${activity.payment_date ? `
                                    <div class="mb-2">
                                        <span class="detail-label">Payment Date:</span>
                                        <div style="font-size: 0.75rem;">${formatDate(activity.payment_date)}</div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            
                            ${activity.payment_remark ? `
                            <div class="mt-2">
                                <span class="detail-label">Payment Remark:</span>
                                <div style="font-size: 0.75rem; padding: 6px; background-color: white; border-radius: 4px; margin-top: 4px;">
                                    ${activity.payment_remark}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }
        });

        if (!hasPaymentData) {
            paymentHtml += `
                <div class="detail-section">
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-info-circle"></i> No payment information available
                    </div>
                </div>
            `;
        }

        return paymentHtml;
    }

    function getApprovalBorderColor(status) {
        switch (status.toLowerCase()) {
            case 'approved':
                return '#28a745';
            case 'pending':
                return '#ffc107';
            case 'rejected':
                return '#dc3545';
            default:
                return '#6c757d';
        }
    }

    // ==========================================
    // TAB FUNCTIONS
    // ==========================================
    function switchTab(tab) {
        switch(tab) {
            case 'approved':
                if (approvedRequests.length === 0) {
                    fetchApprovedRequests();
                }
                break;
            case 'rejected':
                if (rejectedRequests.length === 0) {
                    fetchRejectedRequests();
                }
                break;
        }
    }

    // ==========================================
    // AMOUNT VALIDATION FUNCTIONS
    // ==========================================
    function updateAmountCounter(input) {
        const currentValue = input.value.toString();
        const maxLength = 6;
        const counter = document.getElementById('amountCounter');
        
        // Get the total amount from the modal content
        let maxAmount = 0;
        const modalBody = input.closest('.modal-body');
        if (modalBody) {
            const totalAmountElements = modalBody.querySelectorAll('strong');
            for (let element of totalAmountElements) {
                if (element.textContent.includes('Total Amount:')) {
                    const nextText = element.parentNode.textContent;
                    const amountMatch = nextText.match(/₹([\d,]+)/);
                    if (amountMatch) {
                        maxAmount = parseFloat(amountMatch[1].replace(/,/g, ''));
                        break;
                    }
                }
            }
        }
        
        const currentAmount = parseFloat(currentValue) || 0;
        
        if (counter) {
            counter.textContent = `${currentValue.length}/${maxLength}`;
            
            // Check for amount validation
            let hasError = false;
            let errorMessage = '';
            
            if (currentValue.length > maxLength) {
                hasError = true;
                errorMessage = 'Maximum 6 digits allowed';
            } else if (maxAmount > 0 && currentAmount > maxAmount) {
                hasError = true;
                errorMessage = 'Cannot exceed requested amount';
            }
            
            // Update styling based on validation
            if (hasError) {
                counter.style.color = '#dc3545'; // Red
                input.style.borderColor = '#dc3545';
                
                // Show error message
                let errorDiv = document.getElementById('amountError');
                if (!errorDiv) {
                    errorDiv = document.createElement('small');
                    errorDiv.id = 'amountError';
                    errorDiv.style.color = '#dc3545';
                    errorDiv.style.display = 'block';
                    input.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = errorMessage;
            } else if (currentValue.length >= maxLength * 0.8) {
                counter.style.color = '#ffc107'; // Yellow when near limit
                input.style.borderColor = '#ffc107';
                
                // Remove error message
                const errorDiv = document.getElementById('amountError');
                if (errorDiv) errorDiv.remove();
            } else {
                counter.style.color = '#6c757d'; // Default gray
                input.style.borderColor = '#ced4da';
                
                // Remove error message
                const errorDiv = document.getElementById('amountError');
                if (errorDiv) errorDiv.remove();
            }
            
            // Prevent entering more than 6 digits
            if (currentValue.length > maxLength) {
                input.value = currentValue.substring(0, maxLength);
                counter.textContent = `${maxLength}/${maxLength}`;
            }
        }
    }

    function updateRemarkCounter(textarea) {
        const currentValue = textarea.value;
        const maxLength = 200;
        const counter = document.getElementById('remarkCounter');
        
        if (counter) {
            counter.textContent = `${currentValue.length}/${maxLength}`;
            
            // Update styling based on character count
            if (currentValue.length > maxLength) {
                counter.style.color = '#dc3545'; // Red
                textarea.style.borderColor = '#dc3545';
                
                // Show error message
                let errorDiv = document.getElementById('remarkError');
                if (!errorDiv) {
                    errorDiv = document.createElement('small');
                    errorDiv.id = 'remarkError';
                    errorDiv.style.color = '#dc3545';
                    errorDiv.style.display = 'block';
                    textarea.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = 'Maximum 200 characters allowed';
            } else if (currentValue.length >= maxLength * 0.8) {
                counter.style.color = '#ffc107'; // Yellow when near limit
                textarea.style.borderColor = '#ffc107';
                
                // Remove error message
                const errorDiv = document.getElementById('remarkError');
                if (errorDiv) errorDiv.remove();
            } else {
                counter.style.color = '#6c757d'; // Default gray
                textarea.style.borderColor = '#ced4da';
                
                // Remove error message
                const errorDiv = document.getElementById('remarkError');
                if (errorDiv) errorDiv.remove();
            }
            
            // Prevent entering more than 200 characters
            if (currentValue.length > maxLength) {
                textarea.value = currentValue.substring(0, maxLength);
                counter.textContent = `${maxLength}/${maxLength}`;
            }
        }
    }

    // ==========================================
    // INITIALIZATION
    // ==========================================
    $(document).ready(function() {
        console.log('Initializing advance approvals...');
        console.log('Employee ID:', empId);
        console.log('API Base URL:', apiBaseUrl);
        
        // Load pending requests by default
        fetchPendingRequests();
        
        // Tab change event
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const target = $(e.target).attr('data-bs-target');
            if (target === '#approved') {
                switchTab('approved');
            } else if (target === '#rejected') {
                switchTab('rejected');
            }
        });

        // ── Filter handlers ──────────────────────────────
        // Live filter on typing
        $(document).on('keyup', '.dt-filter', function() {
            applyFilters();
        });

        // Filter on dropdown change
        $(document).on('change', '.dt-filter-select', function() {
            applyFilters();
        });

        $('#btnClearAdvFilter').on('click', function() {
            $('.dt-filter').val('');
            $('.dt-filter-select').val('');
            applyFilters();
        });

        $('#btnRefreshAll').on('click', function() {
            fetchPendingRequests();
            fetchApprovedRequests();
            fetchRejectedRequests();
        });
    });

    // ==========================================
    // FILTER LOGIC
    // ==========================================
    function applyFilters() {
        const tables = [pendingTable, approvedTable, rejectedTable].filter(t => t);

        // Text input filters — search specific columns
        $('.dt-filter').each(function() {
            const val = $(this).val();
            const col = parseInt($(this).data('column'));
            tables.forEach(function(table) {
                table.column(col).search(val, false, true);
            });
        });

        // Select filters
        $('.dt-filter-select').each(function() {
            const val = $(this).val();
            const col = parseInt($(this).data('column'));
            tables.forEach(function(table) {
                table.column(col).search(val ? '^.*' + val + '.*$' : '', true, false);
            });
        });

        tables.forEach(function(table) { table.draw(); });
    }
</script>
@endpush