@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
      $sessiondata = session()->all();
    $yearId =session("year_id");
    $company_id =session("company_id");
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
        style="display: none;">
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0">
                <!-- Page Header with Back and New Request buttons -->
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="history.back()" style="font-size: 12px; padding: 3px 10px;">
                            <i class="ri-arrow-left-line align-middle me-1" style="font-size: 12px;"></i> Back
                        </button>
                        
                        <h5 class="card-title mb-0" style="font-size: 14px; font-weight: 600;">
                            <i class="ri-file-list-line me-1" style="font-size: 16px;"></i>
                            Advance Requests
                            <small class="text-muted ms-1" style="font-size: 12px;">
                                ({{ $user->name ?? 'User' }})
                            </small>
                        </h5>
                        
                        <div class="d-flex gap-1">
                            <a href="{{ url('/advance-requests/create') }}" class="btn btn-success btn-sm" style="font-size: 12px; padding: 3px 10px;">
                                <i class="ri-add-line align-middle me-1" style="font-size: 12px;"></i> New Request
                            </a>
                            <a href="{{ url('/advance-requests/report') }}" class="btn btn-outline-info btn-sm" style="font-size: 12px; padding: 3px 10px;">
                                <i class="ri-file-chart-line align-middle me-1" style="font-size: 12px;"></i> Report
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" id="refreshBtn" style="font-size: 12px; padding: 3px 10px;">
                                <i class="ri-refresh-line align-middle me-1" style="font-size: 12px;"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-2">
             
{{-- <div class="d-flex justify-content-between mb-3">
    <a class="btn btn-sm btn-primary" href=""> back</a>
   <a href="{{ route('advance.requests.create') }}" class="btn btn-success btn-sm">
    <i class="ri-add-line align-middle me-1"></i> New Request
</a>
</div> --}}
                    <!-- Alert Message -->
                    <div id="messageAlert" class="alert alert-danger mb-2 p-2" style="display: none; font-size: 12px;"></div>

                    <!-- Request Type Filter -->
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="filter-group">
                                    {{-- <label class="form-label mb-0 fw-semibold me-1" style="font-size: 12px;">Request Type:</label> --}}
                                    <div class="btn-group btn-group-sm" role="group" id="requestTypeGroup">
                                        <input type="radio" class="btn-check" name="requestType" id="advanceType" value="advance" checked>
                                        <label class="btn btn-outline-primary" for="advanceType" style="font-size: 11px; padding: 2px 8px;">
                                            <i class="ri-file-list-line me-1" style="font-size: 12px;"></i> Activity Advance
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="requestType" id="expenseType" value="expense">
                                        <label class="btn btn-outline-primary" for="expenseType" style="font-size: 11px; padding: 2px 8px;">
                                            <i class="ri-money-dollar-circle-line me-1" style="font-size: 12px;"></i> Expense Advance
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="requestType" id="otherType" value="other">
                                        <label class="btn btn-outline-primary" for="otherType" style="font-size: 11px; padding: 2px 8px;">
                                            <i class="ri-file-text-line me-1" style="font-size: 12px;"></i> Other Advance
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="ms-auto">
                                    <small class="text-muted me-2" id="currentTypeDisplay" style="font-size: 11px;">Showing: Activity Advance</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Tabs -->
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <ul class="nav nav-tabs nav-tabs-custom border-bottom-0" role="tablist" style="font-size: 12px;">
                                <li class="nav-item">
                                    <a class="nav-link active px-3 py-1" data-bs-toggle="tab" href="#pending" role="tab">
                                        <i class="ri-time-line me-1" style="font-size: 12px;"></i> Pending 
                                        <span class="badge bg-warning ms-1" id="pendingCount" style="font-size: 10px; padding: 1px 4px;">0</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link px-3 py-1" data-bs-toggle="tab" href="#approved" role="tab">
                                        <i class="ri-check-double-line me-1" style="font-size: 12px;"></i> Approved
                                        <span class="badge bg-success ms-1" id="approvedCount" style="font-size: 10px; padding: 1px 4px;">0</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link px-3 py-1" data-bs-toggle="tab" href="#rejected" role="tab">
                                        <i class="ri-close-circle-line me-1" style="font-size: 12px;"></i> Rejected
                                        <span class="badge bg-danger ms-1" id="rejectedCount" style="font-size: 10px; padding: 1px 4px;">0</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link px-3 py-1" data-bs-toggle="tab" href="#all" role="tab">
                                        <i class="ri-list-check me-1" style="font-size: 12px;"></i> All
                                        <span class="badge bg-secondary ms-1" id="allCount" style="font-size: 10px; padding: 1px 4px;">0</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Pending Tab -->
                        <div class="tab-pane fade show active" id="pending" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="pendingTable" style="font-size: 11px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40" class="py-1 px-2">Sr.No</th>
                                            <th class="py-1 px-2">Employee</th>
                                            <th class="py-1 px-2">Activity</th>
                                            <th width="60" class="py-1 px-2">Month</th>
                                            <th class="py-1 px-2">Crops</th>
                                            <th class="py-1 px-2">Varieties</th>
                                            <th class="py-1 px-2">Purpose</th>
                                            <th width="80" class="py-1 px-2">Amount</th>
                                            <th width="80" class="py-1 px-2">Date</th>
                                            <th width="60" class="py-1 px-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingTableBody">
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Approved Tab -->
                        <div class="tab-pane fade" id="approved" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="approvedTable" style="font-size: 11px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40" class="py-1 px-2">Sr.No</th>
                                            <th class="py-1 px-2">Employee</th>
                                            <th class="py-1 px-2">Activity</th>
                                            <th width="60" class="py-1 px-2">Month</th>
                                            <th class="py-1 px-2">Crops</th>
                                            <th class="py-1 px-2">Varieties</th>
                                            <th class="py-1 px-2">Purpose</th>
                                            <th width="80" class="py-1 px-2">Amount</th>
                                            <th width="80" class="py-1 px-2">Date</th>
                                            <th width="60" class="py-1 px-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="approvedTableBody">
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Rejected Tab -->
                        <div class="tab-pane fade" id="rejected" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="rejectedTable" style="font-size: 11px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40" class="py-1 px-2">Sr.No</th>
                                            <th class="py-1 px-2">Employee</th>
                                            <th class="py-1 px-2">Activity</th>
                                            <th width="60" class="py-1 px-2">Month</th>
                                            <th class="py-1 px-2">Crops</th>
                                            <th class="py-1 px-2">Varieties</th>
                                            <th class="py-1 px-2">Purpose</th>
                                            <th width="80" class="py-1 px-2">Amount</th>
                                            <th width="80" class="py-1 px-2">Date</th>
                                            <th width="60" class="py-1 px-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rejectedTableBody">
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- All Tab -->
                        <div class="tab-pane fade" id="all" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="allTable" style="font-size: 11px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40" class="py-1 px-2">Sr.No</th>
                                            <th class="py-1 px-2">Employee</th>
                                            <th class="py-1 px-2">Activity</th>
                                            <th width="60" class="py-1 px-2">Month</th>
                                            <th class="py-1 px-2">Crops</th>
                                            <th class="py-1 px-2">Varieties</th>
                                            <th class="py-1 px-2">Purpose</th>
                                            <th width="80" class="py-1 px-2">Amount</th>
                                            <th width="80" class="py-1 px-2">Status</th>
                                            <th width="80" class="py-1 px-2">Date</th>
                                            <th width="60" class="py-1 px-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="allTableBody">
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #155997; color: white; padding: 8px 16px;">
                <h5 class="modal-title text-white" style="font-size: 14px; font-weight: 600;">
                    <i class="ri-file-text-line me-1" style="font-size: 14px;"></i>Request Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="transform: scale(0.8);"></button>
            </div>
            <div class="modal-body p-3" id="detailModalBody" style="font-size: 11px;">
                <!-- Details will be loaded here via JavaScript -->
            </div>
            <div class="modal-footer py-2" style="padding: 8px 16px;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="font-size: 11px; padding: 2px 10px;">Close</button>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // ==========================================
    // GLOBAL VARIABLES
    // ==========================================
    const authData = document.getElementById('authData');
    const empId = authData ? authData.dataset.employeeId : '';
    const empName = authData ? authData.dataset.employeeName : '';
    const empCode = authData ? authData.dataset.employeeCode : '';
    const department = authData ? authData.dataset.department : '';
    const companyId = authData ? parseInt(authData.dataset.companyId) : 0;
    const fYearId = authData ? parseInt(authData.dataset.fyearId) : 0;
    const userId = authData ? parseInt(authData.dataset.userId) : 0;
    const API_LOCAL_BASE_URL = "{{ url('api') }}";
    // API endpoints
  const apiEndpoints = {
    advance: `${API_LOCAL_BASE_URL}/advance`,
    expense: `${API_LOCAL_BASE_URL}/advance/expense`,
    other: `${API_LOCAL_BASE_URL}/advance/other`
};

    const requestTypeLabels = {
        advance: 'Activity Advance',
        expense: 'Expense Advance',
        other: 'Other Advance'
    };

    let advanceRequests = [];
    let currentRequestType = 'advance';
    let filteredData = {
        pending: [],
        approved: [],
        rejected: [],
        all: []
    };

    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    function showLoading() {
        $('#loadingOverlay').show();
        $('#refreshBtn').prop('disabled', true);
    }

    function hideLoading() {
        $('#loadingOverlay').hide();
        $('#refreshBtn').prop('disabled', false);
    }

    function showMessage(message, type = 'danger') {
        const messageDiv = $('#messageAlert');
        messageDiv.removeClass('alert-success alert-danger alert-warning alert-info')
                  .addClass(`alert-${type}`)
                  .html(`<i class="ri-error-warning-line me-2"></i>${message}`)
                  .show();
        
        setTimeout(() => {
            messageDiv.fadeOut();
        }, 5000);
    }

    function hideMessage() {
        $('#messageAlert').hide();
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }

    function formatAmount(amount) {
        if (!amount) return '₹0';
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(numAmount);
    }

    function getMonthName(monthNumber) {
        const months = [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ];
        return months[monthNumber - 1] || monthNumber;
    }

    function getStatusBadgeClass(status) {
        switch (status.toLowerCase()) {
            case 'submitted':
                return 'status-submitted';
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

    // ==========================================
    // AUTHENTICATION CHECK
    // ==========================================
    function checkAuthentication() {
        if (!empId) {
            showMessage('User not authenticated. Please login.', 'warning');
            return false;
        }
        return true;
    }

    // ==========================================
    // API CALLS
    // ==========================================
    function fetchAdvanceRequests() {
        if (!checkAuthentication()) return;
        
        showLoading();
        hideMessage();
        
        $.ajax({
            url: apiEndpoints[currentRequestType],
            method: 'GET',
            data: {
                user_id: empId,
                company_id: companyId,
                fyear_id: fYearId
            },
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                hideLoading();
                
                if (response.success && response.data) {
                    advanceRequests = response.data;
                    filterDataByStatus();
                    renderAllTables();
                    updateTabCounts();
                    updateCurrentTypeDisplay();
                } else {
                    advanceRequests = [];
                    clearAllTables();
                    showMessage('No advance requests found', 'info');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                showMessage(`Error loading ${requestTypeLabels[currentRequestType].toLowerCase()}: ${error}`);
                advanceRequests = [];
                clearAllTables();
            }
        });
    }

    // ==========================================
    // DATA FILTERING
    // ==========================================
    function filterDataByStatus() {
        filteredData.pending = advanceRequests.filter(request => {
            if (request.activities && request.activities.length > 0) {
                const status = request.activities[0].approval_status || 'pending';
                return status.toLowerCase() === 'pending';
            }
            return false;
        });

        filteredData.approved = advanceRequests.filter(request => {
            if (request.activities && request.activities.length > 0) {
                const status = request.activities[0].approval_status || 'pending';
                return status.toLowerCase() === 'approved';
            }
            return false;
        });

        filteredData.rejected = advanceRequests.filter(request => {
            if (request.activities && request.activities.length > 0) {
                const status = request.activities[0].approval_status || 'pending';
                return status.toLowerCase() === 'rejected';
            }
            return false;
        });

        filteredData.all = advanceRequests;
    }

    function updateTabCounts() {
        $('#pendingCount').text(filteredData.pending.length);
        $('#approvedCount').text(filteredData.approved.length);
        $('#rejectedCount').text(filteredData.rejected.length);
        $('#allCount').text(filteredData.all.length);
    }

    function updateCurrentTypeDisplay() {
        const count = advanceRequests.length;
        const typeLabel = requestTypeLabels[currentRequestType];
        $('#currentTypeDisplay').text(`Showing: ${typeLabel} (${count})`);
    }

    // ==========================================
    // TABLE RENDERING
    // ==========================================
    function clearAllTables() {
        const emptyHtml = `
            <tr>
                <td colspan="10" class="text-center py-2" style="font-size: 11px;">
                    <div class="text-muted">
                        <i class="ri-inbox-line" style="font-size: 18px;"></i>
                        <div class="mt-1">No requests found</div>
                    </div>
                </td>
            </tr>
        `;
        
        const allEmptyHtml = `
            <tr>
                <td colspan="11" class="text-center py-2" style="font-size: 11px;">
                    <div class="text-muted">
                        <i class="ri-inbox-line" style="font-size: 18px;"></i>
                        <div class="mt-1">No requests found</div>
                    </div>
                </td>
            </tr>
        `;
        
        $('#pendingTableBody').html(emptyHtml);
        $('#approvedTableBody').html(emptyHtml);
        $('#rejectedTableBody').html(emptyHtml);
        $('#allTableBody').html(allEmptyHtml);
        
        updateTabCounts();
        updateCurrentTypeDisplay();
    }

    function renderAllTables() {
        renderTable('pendingTableBody', filteredData.pending, false);
        renderTable('approvedTableBody', filteredData.approved, false);
        renderTable('rejectedTableBody', filteredData.rejected, false);
        renderTable('allTableBody', filteredData.all, true);
    }

    function renderTable(tableBodyId, data, showStatus = false) {
        const tbody = $('#' + tableBodyId);
        tbody.empty();

        if (data.length === 0) {
            const emptyHtml = showStatus ? 
                `<tr><td colspan="11" class="text-center py-2 text-muted" style="font-size: 11px;">No data available</td></tr>` :
                `<tr><td colspan="10" class="text-center py-2 text-muted" style="font-size: 11px;">No data available</td></tr>`;
            tbody.html(emptyHtml);
            return;
        }

        data.forEach((request, index) => {
            const row = createTableRow(request, index + 1, showStatus);
            tbody.append(row);
        });
    }

    function createTableRow(request, srNo, showStatus = false) {
        const reqData = request.req_data || {};
        const activities = request.activities || [];
        const firstActivity = activities.length > 0 ? activities[0] : {};
        
        // Get total amount
        const totalAmount = firstActivity.amount || '0';
        
        // Get status
        const status = firstActivity.approval_status || 'pending';

        // Get crops and varieties
        const crops = firstActivity.crop_names || [];
        const varieties = firstActivity.variety_names || [];
        const purpose = firstActivity.purpose || '';

        // Create crops display
        let cropsHtml = '';
        if (crops.length > 0) {
            crops.slice(0, 2).forEach(crop => {
                cropsHtml += `<span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 9px; padding: 1px 4px;">${crop}</span>`;
            });
            if (crops.length > 2) {
                cropsHtml += `<span class="badge bg-secondary" style="font-size: 9px; padding: 1px 4px;">+${crops.length - 2}</span>`;
            }
        } else {
            cropsHtml = '<span class="text-muted" style="font-size: 10px;">-</span>';
        }

        // Create varieties display
        let varietiesHtml = '';
        if (varieties.length > 0) {
            varieties.slice(0, 2).forEach(variety => {
                varietiesHtml += `<span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 9px; padding: 1px 4px;">${variety}</span>`;
            });
            if (varieties.length > 2) {
                varietiesHtml += `<span class="badge bg-secondary" style="font-size: 9px; padding: 1px 4px;">+${varieties.length - 2}</span>`;
            }
        } else {
            varietiesHtml = '<span class="text-muted" style="font-size: 10px;">-</span>';
        }

        // Create purpose display
        let purposeDisplay = purpose;
        if (purpose.length > 20) {
            purposeDisplay = purpose.substring(0, 20) + '...';
        }

        // Create status badge
        let statusBadge = '';
        if (showStatus) {
            let badgeClass = 'badge bg-secondary';
            if (status.toLowerCase() === 'approved') badgeClass = 'badge bg-success';
            else if (status.toLowerCase() === 'pending') badgeClass = 'badge bg-warning';
            else if (status.toLowerCase() === 'rejected') badgeClass = 'badge bg-danger';
            
            statusBadge = `<span class="${badgeClass}" style="font-size: 9px; padding: 1px 4px;">${status}</span>`;
        }

        const rowHtml = `
            <tr style="font-size: 11px;">
                <td class="text-center py-1 px-2">${srNo}</td>
                <td class="py-1 px-2">
                    <div class="fw-semibold" style="font-size: 11px;">${request.employee_name || 'N/A'}</div>
                    <small class="text-muted" style="font-size: 9px;">${request.employee_code || ''}</small>
                </td>
                <td class="py-1 px-2">
                    <div class="fw-semibold" style="font-size: 11px;">${reqData.activity_name || 'N/A'}</div>
                    <small class="text-muted" style="font-size: 9px;">${reqData.advance_type_name || ''}</small>
                </td>
                <td class="text-center py-1 px-2">${getMonthName(reqData.month)}</td>
                <td class="py-1 px-2">${cropsHtml}</td>
                <td class="py-1 px-2">${varietiesHtml}</td>
                <td class="py-1 px-2">
                    <span title="${purpose}" style="font-size: 10px;">${purposeDisplay || '-'}</span>
                </td>
                <td class="text-end fw-bold text-primary py-1 px-2">
                    ${formatAmount(totalAmount)}
                </td>
                ${showStatus ? `
                <td class="text-center py-1 px-2">
                    ${statusBadge}
                </td>
                ` : ''}
                <td class="text-center py-1 px-2">
                    <span style="font-size: 10px;">${formatDate(request.created_at)}</span>
                </td>
                <td class="text-center py-1 px-2">
                    <button class="btn btn-sm btn-primary view-details-btn" 
                            data-id="${request.id}"
                            title="View Details"
                            style="padding: 1px 5px; font-size: 10px;">
                        <i class="ri-eye-line" style="font-size: 12px;"></i>
                    </button>
                </td>
            </tr>
        `;

        return rowHtml;
    }

    // ==========================================
    // DETAIL VIEW FUNCTIONS - MATCHING YOUR REFERENCE CODE
    // ==========================================
    function showRequestDetails(requestId) {
        const request = advanceRequests.find(req => req.id == requestId);
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
        const activities = request.activities || [];
        const firstActivity = activities.length > 0 ? activities[0] : {};
        
        // Get data from API
        const employeeName = request.employee_name || 'Deepak Gangwar';
        const employeeCode = request.employee_code || '886';
        const department = request.department || 'Sales';
        const activityName = reqData.activity_name || 'Retail Meeting';
        const advanceType = reqData.advance_type_name || 'Activity';
        const month = getMonthName(reqData.month) || 'Feb';
        
        // Calculate total amount
        const totalAmount = activities.reduce((sum, activity) => sum + parseFloat(activity.amount || 0), 0);
        
        // Get crops and varieties
        const crops = firstActivity.crop_names || ['Aash Gourd'];
        const varieties = firstActivity.variety_names || ['VINELO'];
        const purpose = firstActivity.purpose || 'Testing';
        
        // Get status from first activity
        const status = firstActivity.approval_status || 'ANYWHERE';
        
        // Get approval data
        const approvalData = firstActivity.approvals && firstActivity.approvals.length > 0 
            ? firstActivity.approvals[0] 
            : null;
        
        const approverName = approvalData ? approvalData.approver_name : 'Hitendra Singh';
        const approverRole = approvalData ? approvalData.approver_designation : 'General Manager';
        const approvalAmount = approvalData ? approvalData.approver_amount : 7000;
        const approvalDate = approvalData ? formatDate(approvalData.approver_date) : '03 Feb 2026';

        const approvalStatus = approvalData ? approvalData.approver_status:'pending';
        
        // Finance data
        const financeStatus = firstActivity.finance_status || 'ANYWHERE';
        const financeApproved = firstActivity.finance_approved_amount || 'N/A';
        const financeDate = firstActivity.finance_date ? formatDate(firstActivity.finance_date) : 'N/A';
        
        // Payment data
        const paymentStatus = firstActivity.payment_status || 'HEAD OF THE PAYMENT';
        
        // Format dates
        const createdDate = formatDate(request.created_at) || '03 Feb 2026';
        console.log('Approval Data:', approvalData);
        // EXACT FORMAT MATCHING YOUR REFERENCE CODE
        let html = `
            <div style="font-size: 11px;">
                <!-- Request Details Section -->
                <div class="mb-3 p-2" style="background-color: #f8f9fa; border-radius: 4px;">
                    <div class="row">
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Employee:</span>
                            <div style="font-size: 11px; color: #333;">${employeeName} (${employeeCode})</div>
                        </div>
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Department:</span>
                            <div style="font-size: 11px; color: #333;">${department}</div>
                        </div>
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Activity:</span>
                            <div style="font-size: 11px; color: #333;">${activityName}</div>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Advance Type:</span>
                            <div style="font-size: 11px; color: #333;">${advanceType}</div>
                        </div>
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Month:</span>
                            <div style="font-size: 11px; color: #333;">${month}</div>
                        </div>
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Total Amount:</span>
                            <div style="font-size: 11px; color: #333; font-weight: bold;">${formatAmount(totalAmount)}</div>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Status:</span>
                            <div>
                                <span class="badge bg-warning text-dark" style="font-size: 10px; padding: 2px 6px;">${status}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <span style="font-weight: 600; color: #495057;">Created:</span>
                            <div style="font-size: 11px; color: #333;">${createdDate}</div>
                        </div>
                    </div>
                </div>

                <h6 class="mt-3 mb-2" style="color: #155997; font-size: 12px; font-weight: 600;">
                    <i class="ri-list-check me-1"></i> Activities (${activities.length})
                </h6>

                <!-- Activities Section -->
                ${activities.map((activity, index) => {
                    const activityCrops = activity.crop_names || crops;
                    const activityVarieties = activity.variety_names || varieties;
                    const activityPurpose = activity.purpose || purpose;
                    
                    return `
                        <div class="mb-3 p-2" style="background-color: #f8f9fa; border-radius: 4px;">
                            <div class="mb-2">
                                <strong style="color: #495057;">${activity.activity_type_name || 'Off Season Activity (0 BA)'}</strong>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <span style="font-weight: 600; color: #6c757d;">Crops:</span><br>
                                    <div class="mt-1">
                                        ${activityCrops.map(crop => 
                                            `<span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 9px; padding: 1px 4px;">${crop}</span>`
                                        ).join('')}
                                    </div>
                                </div>
                                <div class="col-6">
                                    <span style="font-weight: 600; color: #6c757d;">Varieties:</span><br>
                                    <div class="mt-1">
                                        ${activityVarieties.map(variety => 
                                            `<span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 9px; padding: 1px 4px;">${variety}</span>`
                                        ).join('')}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <span style="font-weight: 600; color: #6c757d;">Purpose:</span>
                                <div style="padding: 4px; font-size: 11px; color: #333;">${activityPurpose}</div>
                            </div>
                        </div>
                    `;
                }).join('')}

                <h6 class="mt-4 mb-2" style="color: #155997; font-size: 12px; font-weight: 600;">
                    <i class="ri-check-double-line me-1"></i> Approvals
                </h6>

                <!-- Approvals Section -->
                ${approvalData ? `
                <div class="mb-3 p-2 row" style="background-color: #f8f9fa; border-radius: 4px;">
                    <div class="mb-1 col-6">
                        <strong style="color: #495057;">${approverName}</strong>
                    </div>
                    <div class="mb-1 col-6" style="color: #6c757d; font-size: 10px;">
                        ${approverRole}
                    </div>
                    <div class="mb-1 col-6">
                        <span class="badge ${approvalStatus === 'Approved' ? 'bg-success' :approvalStatus === 'Pending' ?'bg-warning': 'bg-danger'} text-white" style="font-size: 10px; padding: 2px 6px;">${approvalStatus}</span>
                    </div>
                    <div class="mb-1 col-6">
                        <span style="font-weight: 600; color: #6c757d;">Action Date:</span>
                        <span style="font-size: 11px; color: #333;">${approvalDate}</span>
                    </div>
                    <div>
                        <span style="font-weight: 600; color: #6c757d;">Approval Amount:</span>
                        <span style="font-size: 11px; color: #333; font-weight: bold;">${formatAmount(approvalAmount)}</span>
                    </div>
                </div>
                ` : `
                <div class="mb-3 p-2" style="background-color: #f8f9fa; border-radius: 4px;">
                    <div class="text-muted text-center py-2">
                        <i class="ri-information-line me-1"></i> No approval information available
                    </div>
                </div>
                `}

                <h6 class="mt-4 mb-2" style="color: #155997; font-size: 12px; font-weight: 600;">
                    <i class="ri-money-dollar-circle-line me-1"></i> Finance & Payment Details
                </h6>

                <!-- Finance Information -->
                <div class="mb-3">
                    <div class="mb-2" style="color: #6c757d; font-weight: 600; font-size: 11px;">
                        Finance Information
                    </div>
                    <div class="p-2" style="background-color: #f8f9fa; border-radius: 4px;">
                        <div class="row">
                            <div class="col-4">
                                <span style="font-weight: 600; color: #495057;">Finance Status:</span>
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark" style="font-size: 10px; padding: 2px 6px;">${financeStatus}</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <span style="font-weight: 600; color: #495057;">Finance Approved:</span>
                                <div class="mt-1" style="font-size: 11px; color: #333; font-weight: bold;">${formatAmount(financeApproved)}</div>
                            </div>
                            <div class="col-4">
                                <span style="font-weight: 600; color: #495057;">Finance Date:</span>
                                <div class="mt-1" style="font-size: 11px; color: #333;">${financeDate}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div>
                    <div class="mb-2" style="color: #6c757d; font-weight: 600; font-size: 11px;">
                        Payment Information
                    </div>
                    <div class="p-2" style="background-color: #f8f9fa; border-radius: 4px;">
                        <div class="row">
                            <div class="col-12">
                                <span style="font-weight: 600; color: #495057;">Payment Status:</span>
                                <div class="mt-1">
                                    <span class="badge bg-info" style="font-size: 10px; padding: 2px 6px;">${paymentStatus}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    // ==========================================
    // EVENT LISTENERS
    // ==========================================
    $('#refreshBtn').click(function() {
        fetchAdvanceRequests();
    });

    $('input[name="requestType"]').change(function() {
        currentRequestType = $(this).val();
        fetchAdvanceRequests();
    });

    $(document).on('click', '.view-details-btn', function() {
        const requestId = $(this).data('id');
        showRequestDetails(requestId);
    });

    // ==========================================
    // INITIALIZATION
    // ==========================================
    function initialize() {
        if (checkAuthentication()) {
            fetchAdvanceRequests();
        }
    }

    // Start the application
    initialize();
});
</script>
@endpush

@push('styles')
<style>
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
    width: 1.5rem;
    height: 1.5rem;
}

.nav-tabs-custom .nav-link {
    color: #495057;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 6px 12px;
    font-weight: 500;
}

.nav-tabs-custom .nav-link.active {
    color: #0d6efd;
    background-color: transparent;
    border-bottom: 2px solid #0d6efd;
    font-weight: 600;
}

.nav-tabs-custom .nav-link:hover {
    border-color: #dee2e6 #dee2e6 #0d6efd;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa !important;
    text-transform: uppercase;
    font-size: 11px;
}

.table td {
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.view-details-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.view-details-btn:hover {
    transform: scale(1.05);
}

.table-responsive {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

/* Modal Styling Matching Your Reference */
#detailModal .modal-content {
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

#detailModal .modal-header {
    background-color: #155997 !important;
    color: white;
    padding: 8px 16px;
}

#detailModal .modal-title {
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

#detailModal .modal-body {
    color: #333;
    padding: 12px;
    font-size: 11px;
}

#detailModal .modal-footer {
    padding: 8px 16px;
    border-top: 1px solid #dee2e6;
    background-color: #f8f9fa;
}

/* Badge Styling */
.badge.bg-warning {
    background-color: #f59e0b !important;
    color: #7c2d12 !important;
    font-weight: 500;
}

.badge.bg-success {
    background-color: #10b981 !important;
    color: white !important;
    font-weight: 500;
}

.badge.bg-info {
    background-color: #0ea5e9 !important;
    color: white !important;
    font-weight: 500;
}

.badge.bg-light {
    background-color: #f8f9fa !important;
    color: #212529 !important;
    border: 1px solid #dee2e6 !important;
}

/* Compact layout */
.modal-body > div {
    margin-bottom: 12px;
}

.modal-body > div:last-child {
    margin-bottom: 0;
}

/* Status badge classes */
.status-submitted {
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
}

.status-pending {
    background-color: #fff3e0;
    color: #f57c00;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
}

.status-approved {
    background-color: #e8f5e8;
    color: #388e3c;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
}

.status-rejected {
    background-color: #ffebee;
    color: #d32f2f;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container-fluid {
        padding: 5px !important;
    }
    
    .card-body {
        padding: 8px !important;
    }
    
    .table-responsive {
        font-size: 10px !important;
    }
    
    #detailModal .modal-body {
        padding: 8px;
    }
}
</style>
@endpush