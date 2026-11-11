@extends('layouts.app')

@section('title', 'Claims Verification Report')

@section('content')
    <div class="container-fluid">
        <!-- Filters Section - Enhanced -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Filter Claims</h5>
            </div>
            <div class="card-body">
                <form id="filterForm">
                    <!-- Row 1: Basic Filters -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="filterEmployee" class="form-label">Verified By <span class="text-danger">*</span></label>
                            <select class="form-select" id="filterEmployee" name="employee_id">
                                <option value="">Select Verified By</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee['value'] }}">{{ $employee['text'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="filterMonth" class="form-label">Month (Optional)</label>
                            <select class="form-select" id="filterMonth" name="month">
                                <option value="">Select Months</option>
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">{{ DateTime::createFromFormat('!m', $i)->format('F') }}</option>
                                @endfor
                            </select>
                        </div>

                           <div class="col-md-3">
                            <label for="startDate" class="form-label">Bill Date From</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>
                        <div class="col-md-3">
                            <label for="endDate" class="form-label">Bill Date To</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>


                    </div>

                    <!-- Row 2: Date Filters -->
                    <div class="row g-3 mb-3">

                        <div class="col-md-3">
                            <label for="verificationStartDate" class="form-label">Verification Date From</label>
                            <input type="date" class="form-control" id="verificationStartDate">
                        </div>
                        <div class="col-md-3">
                            <label for="verificationEndDate" class="form-label">Verification Date To</label>
                            <input type="date" class="form-control" id="verificationEndDate">
                        </div>
                          <div class="col-md-2">
                            <label for="perPage" class="form-label">Records Per Page</label>
                            <select class="form-select" id="perPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" id="btnViewClaims">
                                    <i class="ri-eye-line"></i> View Claims
                                </button>
                                <button type="button" class="btn btn-secondary" id="btnReset">
                                    <i class="ri-refresh-line"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employee Info and Summary Header -->
        <div id="employeeInfoSection" style="display: none;">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h5 class="mb-0">
                                <span id="employeeClaimInfo" class="text-primary">-</span>
                            </h5>
                            <small class="text-muted" id="filterInfo"></small>
                        </div>
                        <div id="claimSummaryHeader" class="p-3 rounded d-flex flex-wrap align-items-center gap-4"
                            style="background-color: rgba(41, 156, 219, 0.16) !important;">
                            <div><strong>Total Records:</strong> <span id="h_totalRecords" class="fw-bold">0</span></div>
                            {{-- <div><strong>Total Claimed:</strong> <span id="h_totalClaimedAmt" class="fw-bold text-primary">₹0</span></div>
                            <div><strong>Total Verified:</strong> <span id="h_totalVerifiedAmt" class="fw-bold text-success">₹0</span></div>
                            <div><strong>Verified:</strong> <span id="h_totalVerifiedCount" class="badge bg-success">0</span></div>
                            <div><strong>Pending:</strong> <span id="h_totalVerifyPendingCount" class="badge bg-warning">0</span></div>
                            <div><strong>Rejected:</strong> <span id="h_totalRejectedCount" class="badge bg-danger">0</span></div> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mb-3" id="legendSection" style="display: none;">
            <div class="d-flex gap-4 align-items-center flex-wrap">
                <div class="d-flex align-items-center">
                    <span style="width:12px;height:12px;background:#FFFFFF;border:1px solid #ccc;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                    <small>Normal Claim</small>
                </div>
                <div class="d-flex align-items-center">
                    <span style="width:12px;height:12px;background:#ffff002e;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                    <small>Amount Mismatch</small>
                </div>
                <div class="d-flex align-items-center">
                    <span style="width:12px;height:12px;background:#FEE7F5;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                    <small>Leave/A/OD Employee</small>
                </div>
                <div class="d-flex align-items-center">
                    <span style="width:12px;height:12px;background:#F8A363;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                    <small>Exceeds Eligibility</small>
                </div>
            </div>
        </div>

        <!-- Export Buttons -->
        {{-- <div id="exportSection" style="display: none;" class="mb-3">
            <div class="btn-group">
                <button type="button" class="btn btn-success" id="exportExcel">
                    <i class="ri-file-excel-line"></i> Export to Excel
                </button>
                <button type="button" class="btn btn-danger" id="exportPDF">
                    <i class="ri-file-pdf-line"></i> Export to PDF
                </button>
                <button type="button" class="btn btn-secondary" id="printReport">
                    <i class="ri-printer-line"></i> Print
                </button>
            </div>
        </div> --}}

        <!-- Claims Table Section -->
        <div id="claimsTableSection" style="display: none;">
            <div class="card">
                <div class="card-header">
                    {{-- <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Claims List</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-warning btn-sm" id="verifySelectedBtn">
                                <i class="ri-checkbox-multiple-line"></i> Verify Selected
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="verifyAllBtn">
                                <i class="ri-check-double-fill"></i> Verify All
                            </button>
                        </div>
                    </div> --}}
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                        <table class="table table-bordered table-hover" id="claimsDataTable">
                            <thead class="table-light">
                                <tr>
                                    {{-- <th style="width:30px;">
                                        <input type="checkbox" id="selectAllCheckbox">
                                    </th> --}}
                                    <th style="width:40px;">Sn</th>
                                    <th style="width:70px;">ExpId</th>
                                    <th style="width:100px;">Remark</th>
                                    <th style="width:80px;">File</th>
                                    <th style="width:80px;">Driver</th>
                                    <th style="width:100px;">Claim Type</th>
                                    <th style="width:100px;">Bill Date</th>
                                    <th style="width:100px;">Verify Date</th>
                                    <th style="width:100px;">Claimed Amt</th>
                                    <th style="width:100px;">Verified Amt</th>
                                    <th style="width:150px;">Verifier Remark</th>
                                    <th style="width:100px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="claimsTableBody">
                                <tr>
                                    <td colspan="14" class="text-center">Select an employee to view claims</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light fw-bold" id="claimsTableFooter" style="display: none;">
                                <tr>
                                    <td colspan="9" class="text-end">Total:</td>
                                    <td class="text-end" id="footerClaimedTotal">₹0</td>
                                    <td class="text-end" id="footerVerifiedTotal">₹0</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="paginationContainer" class="mt-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="text-muted" id="totalRecordsInfo">Showing 0 to 0 of 0 records</span>
                            </div>
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="pagination">
                                    <!-- Pagination will be populated by JavaScript -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Claim Details Modal -->
    <div class="modal fade" id="claimDetailsModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Claim Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="claimDetailsContent">
                    <!-- Loading spinner -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img alt="Bill or document image" id="previewImage" src="" style="max-width: 100%; max-height: 80vh;">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .zoom-verify-img {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .zoom-verify-img:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .verify-amt {
            min-width: 100px;
        }

        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .pagination {
            margin-bottom: 0;
        }

        .page-link {
            cursor: pointer;
        }

        .fixed-amount {
            font-family: monospace;
            font-weight: 500;
        }

        .claim-row-selected {
            background-color: #e3f2fd !important;
        }

        .filter-active {
            background-color: #0d6efd !important;
            color: white !important;
        }

        .quick-filter {
            transition: all 0.3s ease;
        }

        .quick-filter:hover {
            transform: translateY(-1px);
        }
    </style>
@endpush

@push('scripts')
    <script>
        let currentPage = 1;
        let currentEmpId = null;
        let currentMonth = null;
        let currentPerPage = 10;
        let currentFilters = {};

        $(document).ready(function () {
            loadClaimTypes();
            loadVerifiers();

            // View claims button click
            $('#btnViewClaims').on('click', function () {
                currentEmpId = $('#filterEmployee').val();
                currentMonth = $('#filterMonth').val();
                currentPerPage = $('#perPage').val();
                currentPage = 1;

                // Collect all filters
                currentFilters = {
                    claim_status: $('#claimStatus').val(),
                    claim_type_id: $('#claimType').val(),
                    verify_by: $('#verifyBy').val(),
                    start_date: $('#startDate').val(),
                    end_date: $('#endDate').val(),
                    verification_start_date: $('#verificationStartDate').val(),
                    verification_end_date: $('#verificationEndDate').val(),
                    min_amount: $('#minAmount').val(),
                    max_amount: $('#maxAmount').val(),
                    search_term: $('#searchTerm').val()
                };

                if (!currentEmpId) {
                    alert('Please select an employee');
                    return;
                }

                loadClaims();
            });

            // Quick filter buttons
            $('.quick-filter').on('click', function() {
                const status = $(this).data('status');
                const dateRange = $(this).data('date');

                if (status) {
                    $('#claimStatus').val(status).trigger('change');
                }

                if (dateRange) {
                    setDateRange(dateRange);
                }

                // Auto-trigger view if employee is selected
                if ($('#filterEmployee').val()) {
                    $('#btnViewClaims').click();
                }
            });

            // Set date range helper
            function setDateRange(range) {
                const today = new Date();
                let startDate = '';
                let endDate = formatDateForInput(today);

                switch(range) {
                    case 'today':
                        startDate = endDate;
                        break;
                    case 'week':
                        const weekAgo = new Date(today);
                        weekAgo.setDate(today.getDate() - 7);
                        startDate = formatDateForInput(weekAgo);
                        break;
                    case 'month':
                        const monthAgo = new Date(today);
                        monthAgo.setDate(today.getDate() - 30);
                        startDate = formatDateForInput(monthAgo);
                        break;
                }

                if (startDate) {
                    $('#startDate').val(startDate);
                    $('#endDate').val(endDate);
                }
            }

            function formatDateForInput(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            // Reset button
            $('#btnReset').on('click', function () {
                $('#filterEmployee').val('');
                $('#filterMonth').val('');
                $('#claimStatus').val('all');
                $('#claimType').val('all');
                $('#verifyBy').val('all');
                $('#startDate').val('');
                $('#endDate').val('');
                $('#verificationStartDate').val('');
                $('#verificationEndDate').val('');
                $('#minAmount').val('');
                $('#maxAmount').val('');
                $('#searchTerm').val('');
                $('#perPage').val('10');
                $('#claimsTableSection').hide();
                $('#employeeInfoSection').hide();
                $('#legendSection').hide();
                $('#exportSection').hide();
                $('#paginationContainer').hide();
                $('#claimsTableBody').html('<tr><td colspan="14" class="text-center">Select an employee to view claims</td></tr>');
                currentFilters = {};
            });

            // Load claim types for dropdown
            function loadClaimTypes() {
                $.ajax({
                    url: '/reports/get-claim-types',
                    type: 'GET',
                    success: function(response) {
                        if (response.success && response.data) {
                            let options = '<option value="all">All Types</option>';
                            response.data.forEach(function(type) {
                                options += `<option value="${type.ClaimId}">${type.ClaimName}</option>`;
                            });
                            $('#claimType').html(options);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading claim types:', xhr);
                    }
                });
            }

            // Load verifiers for dropdown
            function loadVerifiers() {
                $.ajax({
                    url: '/reports/get-verifiers',
                    type: 'GET',
                    success: function(response) {
                        if (response.success && response.data) {
                            let options = '<option value="all">All Verifiers</option>';
                            response.data.forEach(function(verifier) {
                                options += `<option value="${verifier.EmployeeID}">${verifier.VerifierName}</option>`;
                            });
                            $('#verifyBy').html(options);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading verifiers:', xhr);
                    }
                });
            }

            // Load claims with pagination and filters
            function loadClaims() {
                $('#claimsTableBody').html('<tr><td colspan="14" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>');
                $('#claimsTableSection').show();
                $('#legendSection').show();
                $('#exportSection').show();

                let url = `/reports/get-employee-claims/${currentEmpId}`;
                if (currentMonth) {
                    url += `/${currentMonth}`;
                }

                $.ajax({
                    url: url,
                    type: 'GET',
                    data: {
                        page: currentPage,
                        per_page: currentPerPage,
                        ...currentFilters
                    },
                    success: function (response) {
                        if (response.success) {
                            displayEmployeeClaims(response);
                            displayPagination(response.pagination);
                            displayFilterInfo();
                        } else {
                            $('#claimsTableBody').html('<tr><td colspan="14" class="text-center text-danger">' + response.message + '</td></tr>');
                            $('#employeeInfoSection').hide();
                            $('#paginationContainer').hide();
                        }
                    },
                    error: function (xhr) {
                        $('#claimsTableBody').html('<tr><td colspan="14" class="text-center text-danger">Error loading claims</td></tr>');
                        console.error(xhr);
                    }
                });
            }

            // Display filter info
            function displayFilterInfo() {
                let filterTexts = [];
                if (currentFilters.start_date || currentFilters.end_date) {
                    let dateText = '';
                    if (currentFilters.start_date && currentFilters.end_date) {
                        dateText = `Bill Date: ${currentFilters.start_date} to ${currentFilters.end_date}`;
                    } else if (currentFilters.start_date) {
                        dateText = `Bill Date from: ${currentFilters.start_date}`;
                    } else if (currentFilters.end_date) {
                        dateText = `Bill Date until: ${currentFilters.end_date}`;
                    }
                    filterTexts.push(dateText);
                }

                if (currentFilters.claim_status && currentFilters.claim_status !== 'all') {
                    filterTexts.push(`Status: ${currentFilters.claim_status}`);
                }

                if (currentFilters.min_amount || currentFilters.max_amount) {
                    filterTexts.push(`Amount: ${currentFilters.min_amount || '0'} - ${currentFilters.max_amount || '∞'}`);
                }

                if (currentFilters.search_term) {
                    filterTexts.push(`Search: "${currentFilters.search_term}"`);
                }

                if (filterTexts.length > 0) {
                    $('#filterInfo').html(' | Filters: ' + filterTexts.join(' | '));
                } else {
                    $('#filterInfo').html('');
                }
            }

            // Display pagination
            function displayPagination(pagination) {
                if (!pagination || pagination.total <= 0) {
                    $('#paginationContainer').hide();
                    return;
                }

                $('#paginationContainer').show();
                let startRecord = ((pagination.current_page - 1) * pagination.per_page) + 1;
                let endRecord = Math.min(pagination.current_page * pagination.per_page, pagination.total);
                $('#totalRecordsInfo').html(`Showing ${startRecord} to ${endRecord} of <strong>${pagination.total}</strong> records`);

                let paginationHtml = '';
                if (pagination.current_page > 1) {
                    paginationHtml += `<li class="page-item"><a class="page-link" data-page="${pagination.current_page - 1}">Previous</a></li>`;
                } else {
                    paginationHtml += `<li class="page-item disabled"><span class="page-link">Previous</span></li>`;
                }

                let startPage = Math.max(1, pagination.current_page - 2);
                let endPage = Math.min(pagination.last_page, pagination.current_page + 2);

                if (startPage > 1) {
                    paginationHtml += `<li class="page-item"><a class="page-link" data-page="1">1</a></li>`;
                    if (startPage > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }

                for (let i = startPage; i <= endPage; i++) {
                    if (i === pagination.current_page) {
                        paginationHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                    } else {
                        paginationHtml += `<li class="page-item"><a class="page-link" data-page="${i}">${i}</a></li>`;
                    }
                }

                if (endPage < pagination.last_page) {
                    if (endPage < pagination.last_page - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    paginationHtml += `<li class="page-item"><a class="page-link" data-page="${pagination.last_page}">${pagination.last_page}</a></li>`;
                }

                if (pagination.current_page < pagination.last_page) {
                    paginationHtml += `<li class="page-item"><a class="page-link" data-page="${pagination.current_page + 1}">Next</a></li>`;
                } else {
                    paginationHtml += `<li class="page-item disabled"><span class="page-link">Next</span></li>`;
                }

                $('#pagination').html(paginationHtml);
                $('.page-link[data-page]').on('click', function (e) {
                    e.preventDefault();
                    currentPage = parseInt($(this).data('page'));
                    loadClaims();
                });
            }

            // Display employee claims
            function displayEmployeeClaims(data) {
                $('#employeeInfoSection').show();
                $('#legendSection').show();
                $('#claimsTableSection').show();
                $('#exportSection').show();

                $('#employeeClaimInfo').text(`${data.employee.Name} (${data.employee.EmpCode}) - ${data.monthName}`); // S-09
                $('#h_totalRecords').text(data.pagination?.total || 0);
                $('#h_totalClaimedAmt').text('₹' + (parseFloat(data.totals.total_claimed || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})));
                $('#h_totalVerifiedAmt').text('₹' + (parseFloat(data.totals.total_approved || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})));
                $('#h_totalVerifiedCount').text(data.totals.verified_count || 0);
                $('#h_totalVerifyPendingCount').text((data.totals.total_claims || 0) - (data.totals.verified_count || 0));
                $('#h_totalRejectedCount').text(0); // You can calculate this if needed

                let tbody = '';
                let startSerial = ((data.pagination?.current_page || 1) - 1) * (data.pagination?.per_page || 10);

                $.each(data.data, function (index, claim) {
                    let rowColor = '#FFFFFF';
                    let claimedAmount = parseFloat(claim.FilledTAmt || 0);
                    let approvedAmount = parseFloat(claim.VerifyTAmt || 0);

                    if (claim.ClaimId == 1 && claimedAmount > 5000) {
                        rowColor = '#F8A363';
                    } else if (claim.emp_att_status && claim.emp_att_status.status == 'Absent') {
                        rowColor = '#FEE7F5';
                    } else if (approvedAmount > 0 && approvedAmount != claimedAmount) {
                        rowColor = '#ffff002e';
                    }

                    tbody += `<tr style="background-color:${rowColor};" data-expid="${claim.ExpId}">

                        <td class="text-center">${startSerial + index + 1}</td>
                        <td class="text-center">
                            ${claim.ExpId}
                        </td>
                        <td><small>${claim.Remarks || claim.Description || '-'}</small></td>
                        <td class="text-center">${claim.file_preview || '-'}</td>
                        <td class="text-center">${claim.with_driver || '-'}</td>
                        <td>${claim.ClaimName}</td>
                        <td class="text-center">${formatDate(claim.BillDate)}</td>
                        <td class="text-center"${formatDate(claim.VerifyDate)}</td>
                        <td class="text-end fixed-amount">₹${claimedAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        <td class="text-end fixed-amount">
                          ${approvedAmount || claimedAmount}
                        </td>
                        <td>
                            ${claim.VerifyRemark || ''}
                        </td>
                        <td class="text-center">
                          ${claim.ClaimStatus}
                        </td>
                    </tr>`;
                });

                $('#claimsTableBody').html(tbody);
                $('#claimsTableFooter').show();
                calculateTotals();
            }

            // Calculate totals
            function calculateTotals() {
                let totalClaimed = 0;
                let totalVerified = 0;

                $('#claimsTableBody tr').each(function () {
                    let claimedText = $(this).find('td:eq(9)').text().replace('₹', '').replace(/,/g, '');
                    let claimed = parseFloat(claimedText) || 0;
                    let verified = parseFloat($(this).find('.verify-amt').val()) || 0;
                    totalClaimed += claimed;
                    totalVerified += verified;
                });

                $('#footerClaimedTotal').text('₹' + totalClaimed.toLocaleString('en-IN', {minimumFractionDigits: 2}));
                $('#footerVerifiedTotal').text('₹' + totalVerified.toLocaleString('en-IN', {minimumFractionDigits: 2}));
            }

            // Select all checkbox
            $('#selectAllCheckbox').on('change', function() {
                $('.claim-select').prop('checked', $(this).is(':checked'));
            });

            // View claim details
            $(document).on('click', '.view-claim-detail', function (e) {
                e.preventDefault();
                const expId = $(this).data('expid');
                const empId = $(this).data('empid');

                $('#claimDetailsModal').modal('show');
                $('#claimDetailsContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>');

                $.ajax({
                    url: '/reports/get-claim-details',
                    type: 'GET',
                    data: { id: expId },
                    success: function(response) {
                        if (response.success) {
                            displayClaimDetails(response.data);
                        } else {
                            $('#claimDetailsContent').html('<div class="alert alert-danger">' + response.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#claimDetailsContent').html('<div class="alert alert-danger">Error loading claim details</div>');
                    }
                });
            });

            // Display claim details in modal
            function displayClaimDetails(claim) {
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Claim Information</h6>
                            <table class="table table-sm table-bordered">
                                <tr><th width="40%">Expense ID</th><td>${claim.ExpId}</td></tr>
                                <tr><th>Claim Type</th><td>${claim.ClaimName || 'N/A'}</td></tr>
                                <tr><th>Bill Date</th><td>${formatDate(claim.BillDate)}</td></tr>
                                <tr><th>Claim Amount</th><td>₹${parseFloat(claim.ClaimAmount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                                <tr><th>Approved Amount</th><td>₹${parseFloat(claim.ApprovedAmount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td></tr>
                                <tr><th>Remarks</th><td>${claim.Remarks || claim.Description || '-'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Verification Information</h6>
                            <table class="table table-sm table-bordered">
                                <tr><th width="40%">Status</th><td><span class="badge bg-${claim.ClaimStatus == 'Verified' ? 'success' : 'warning'}">${claim.ClaimStatus || 'Pending'}</span></td></tr>
                                <tr><th>Verified By</th><td>${claim.verifier_details?.verifier_name || 'N/A'}</td></tr>
                                <tr><th>Verification Date</th><td>${claim.verification_date_formatted || '-'}</td></tr>
                                <tr><th>Verifier Remark</th><td>${claim.VerifyRemark || '-'}</td></tr>
                            </table>
                        </div>
                    </div>
                `;

                if (claim.file_preview && claim.file_preview !== '-') {
                    html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Attachments</h6>
                                ${claim.file_preview}
                            </div>
                        </div>
                    `;
                }

                $('#claimDetailsContent').html(html);
            }

            // Per page change handler
            $('#perPage').on('change', function () {
                if (currentEmpId) {
                    currentPerPage = $(this).val();
                    currentPage = 1;
                    loadClaims();
                }
            });

            // Export to Excel
            $('#exportExcel').on('click', function() {
                alert('Excel export functionality - Implement as needed');
                // window.location.href = `/reports/export-claims?employee_id=${currentEmpId}&format=excel&${$.param(currentFilters)}`;
            });

            // Print report
            $('#printReport').on('click', function() {
                window.print();
            });
        });

        // Helper function to format date
        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00') return '-';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleDateString('en-GB');
        }

        // View full image function
        function viewFullImage(imageUrl) {
            $('#previewImage').attr('src', imageUrl);
            $('#imagePreviewModal').modal('show');
        }
    </script>
@endpush
