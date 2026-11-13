{{-- resources/views/claims/index.blade.php --}}
@extends('layouts.app')

@section('styles')
    <style>
        .estable {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .estable th {
            background-color: #dde6ff;
            color: #1e3a8a;
            padding: 8px 6px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #ddd;
            white-space: nowrap;
            font-size: 11px;
        }

        .estable td {
            padding: 6px;
            border: 1px solid #ddd;
            vertical-align: middle;
            font-size: 11px;
        }

        .estable tbody tr:hover {
            background-color: #e9f3ff !important;
        }

        .print-link {
            color: #000099;
            cursor: pointer;
            text-decoration: underline;
            margin: 0 2px;
        }

        .view-link {
            color: #15648C;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 15px 0;
        }

        .pagination li { margin: 0 2px; }

        .pagination li a, .pagination li span {
            padding: 5px 10px;
            border: 1px solid #ddd;
            color: #15648C;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            border-radius: 4px;
        }

        .pagination li.active span {
            background-color: #dde6ff;
            color: #1e3a8a;
            border-color: #15648C;
        }

        .total-records-badge {
            background: #dde6ff;
            color: #1e3a8a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
    </style>
@endsection

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="background:#f8fafc;border-bottom:2px solid #15648C;">
                            <div>
                                <h4 class="card-title mb-0" style="color:#15648C;">
                                    <i class="ri-file-list-3-line me-2"></i>
                                    <span id="page-title">{{ $chkval == 2 ? 'Pending Claims (Finance)' : 'Approved Claims (Finance)' }}</span>
                                </h4>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Status Tabs -->
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn {{ $chkval == 2 ? 'btn-primary' : 'btn-outline-primary' }}" id="btnPending" onclick="changeStatus(2)">
                                        <i class="ri-hourglass-line me-1"></i>Pending
                                    </button>
                                    <button type="button" class="btn {{ $chkval == 1 ? 'btn-primary' : 'btn-outline-primary' }}" id="btnApproved" onclick="changeStatus(1)">
                                        <i class="ri-checkbox-circle-line me-1"></i>Approved
                                    </button>
                                </div>

                                <button type="button" class="btn btn-sm btn-soft-secondary" onclick="location.reload(true)">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-2">
                            {{-- Filter Row --}}
                            <div class="row g-2 mb-3 p-2" style="background:#f8fafc;border-radius:6px;">
                                <div class="col-md-3">
                                    <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Search (Name/Code)</label>
                                    <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Employee name or code..." aria-label="Employee name or code">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Department</label>
                                    <select class="form-select form-select-sm" id="filterDepartment">
                                        <option value="">All Departments</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept }}">{{ $dept }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Month</label>
                                    <select class="form-select form-select-sm" id="SelMonth">
                                        <option value="0" {{ $v == 0 ? 'selected' : '' }}>All Months</option>
                                        @for($i = 1; $i <= 12; $i++)
                                            <option value="{{ $i }}" {{ $v == $i ? 'selected' : '' }}>{{ date('F', mktime(0,0,0,$i,1)) }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-1">
                                    <button type="button" class="btn btn-sm btn-primary flex-fill" onclick="applyFilters()">
                                        <i class="ri-search-line"></i> Search
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                                        <i class="ri-refresh-line"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Color Instruction Plate (Legend) --}}
                            <div class="card mb-3 border shadow-none" style="background: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-1 text-secondary fw-bold" style="font-size: 11px;">
                                            <i class="ri-information-line text-primary fs-6 align-middle"></i>
                                            <span>Screen Color Legend:</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 flex-wrap" style="font-size: 11px;">
                                            <div class="d-flex align-items-center gap-1" title="Claimed, Verified, Approved, and Finance amounts are identical">
                                                <span style="width: 14px; height: 14px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 3px; display: inline-block;"></span>
                                                <span class="text-dark">Normal (Amounts Match)</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1" title="Mismatch detected between Claimed, Verified, Approved, or Finance amounts">
                                                <span style="width: 14px; height: 14px; background: #FFF9D6; border: 1.5px solid #eab308; border-radius: 3px; display: inline-block;"></span>
                                                <span class="fw-bold" style="color: #854d0e;">Amount Difference</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-warning text-dark" style="font-size: 10px; padding: 2px 6px;">Pending</span>
                                                <span class="text-muted">Awaiting Action</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-success" style="font-size: 10px; padding: 2px 6px;">Approved</span>
                                                <span class="text-muted">Finance Approved</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-danger" style="font-size: 10px; padding: 2px 6px;">Rejected</span>
                                                <span class="text-muted">Finance Rejected</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Records Container -->
                            <div id="records-container">
                                <div class="text-center py-5">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                    <h5 class="modal-title text-white"><i class="ri-file-text-line me-1"></i> Expense Claim Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="claimlistContainer"></div>
            </div>
        </div>
    </div>



    @push('scripts')
        <script src="{{ asset('custom/js/pages/eligibility_panel.js') }}"></script>
        <script>
            let currentPage = {{ $page }};
            let currentChkval = {{ $chkval }};
            let currentMonth = {{ $v }};
            let currentSearch = '';
            let currentDepartment = '';

            // Change status function
            function changeStatus(value) {
                currentChkval = value;
                currentPage = 1;
                if (value == 2) {
                    $('#page-title').text('Pending Claims (Finance)');
                    $('#btnPending').removeClass('btn-outline-primary').addClass('btn-primary');
                    $('#btnApproved').removeClass('btn-primary').addClass('btn-outline-primary');
                } else {
                    $('#page-title').text('Approved Claims (Finance)');
                    $('#btnApproved').removeClass('btn-outline-primary').addClass('btn-primary');
                    $('#btnPending').removeClass('btn-primary').addClass('btn-outline-primary');
                }
                loadRecords();
            }

            // Apply filters
            function applyFilters() {
                currentSearch = $('#filterSearch').val().trim();
                currentDepartment = $('#filterDepartment').val();
                currentMonth = $('#SelMonth').val();
                currentPage = 1;
                loadRecords();
            }

            // Clear filters
            function clearFilters() {
                $('#filterSearch').val('');
                $('#filterDepartment').val('');
                $('#SelMonth').val('0');
                currentSearch = '';
                currentDepartment = '';
                currentMonth = 0;
                currentPage = 1;
                loadRecords();
            }

            // Month selection function (legacy support)
            function FselMonth() {
                applyFilters();
            }

            // Print function
            function FunPrint(e, m, y, n) {
                var url = "{{ url('expense/print-details') }}?e=" + e + "&y=" + y + "&m=" + m + "&nn=" + n + "&c={{ session('company_id') }}";
                window.open(url, "PForm", "menubar=no,scrollbars=yes,resizable=no,directories=no,width=1200,height=450");
            }

            // Load records via AJAX
            function loadRecords() {
                $('#records-container').html('<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>');

                $.ajax({
                    url: "{{ route('claims.display') }}",
                    type: "GET",
                    data: {
                        chkval: currentChkval,
                        v: currentMonth,
                        page: currentPage,
                        search: currentSearch,
                        department: currentDepartment
                    },
                    success: function (response) {
                        displayRecords(response);
                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                        $('#records-container').html('<div class="alert alert-danger">Error loading records: ' + error + '</div>');
                    },
                    complete: function () {
                        $('.spinner-border').hide();
                    }
                });
            }

            // Display records based on status
            function displayRecords(data) {
                let html = '';

                if (currentChkval == 2) {
                    html = generatePendingClaimsHTML(data);
                } else {
                    html = generateApprovedClaimsHTML(data);
                }

                $('#records-container').html(html);
                updatePagination(data);
            }

            // Generate HTML for pending claims
            function generatePendingClaimsHTML(data) {
                let pageTotalClaims = 0;
                let pageTotalClaimed = 0;
                let pageTotalVerified = 0;
                let pageTotalApproved = 0;

                let html = `
                    <div class="table-responsive">
                        <table class="estable table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;">S.No</th>
                                    <th>Employee</th>
                                    <th style="width:80px;">Code</th>
                                    <th style="width:120px;">Department</th>
                                    <th style="width:100px;">Designation</th>
                                    <th style="width:80px;">Branch</th>
                                    <th style="width:80px;">Month</th>
                                    <th style="width:60px;">Claims</th>
                                    <th style="width:90px;">Claimed Amt</th>
                                    <th style="width:90px;">Verified Amt</th>
                                    <th style="width:90px;">Appr. Amt</th>
                                    <th style="width:80px;">Print</th>
                                    <th style="width:130px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>`;

                if (data.records && data.records.length > 0) {
                    data.records.forEach((record, index) => {
                        let sno = ((currentPage - 1) * data.per_page) + index + 1;
                        let monthName = getMonthName(record.Month);
                        let totalClaims = Number(record.Total_Claim) || 0;
                        let claimedAmt = Number(record.Claim_Amount) || 0;
                        let verifiedAmt = Number(record.Verified_Amount) || 0;
                        let approvedAmt = Number(record.Approved_Amount) || 0;

                        pageTotalClaims += totalClaims;
                        pageTotalClaimed += claimedAmt;
                        pageTotalVerified += verifiedAmt;
                        pageTotalApproved += approvedAmt;

                        html += `
                            <tr>
                                <td style="text-align:center;">${sno}</td>
                                <td>
                                    <a href="#" onclick="showmonthdet(${record.Month},'Open',${record.EmployeeID},'Approved')" class="view-link fw-semibold">
                                        ${escapeHtml(record.userName || '')}
                                    </a>
                                </td>
                                <td style="text-align:center;"><code>${escapeHtml(record.empCode || '')}</code></td>
                                <td style="text-align:center;"><span class="badge bg-info-subtle text-info" style="font-size:9px;">${escapeHtml(record.department || '')}</span></td>
                                <td style="text-align:center;font-size:10px;">${escapeHtml(record.designation || '')}</td>
                                <td style="text-align:center;font-size:10px;">${escapeHtml(record.branch || '')}</td>
                                <td style="text-align:center;">${monthName}</td>
                                <td style="text-align:center;"><span class="fw-bold">${formatNumber(totalClaims)}</span></td>
                                <td style="text-align:right;">₹${formatNumber(claimedAmt)}</td>
                                <td style="text-align:right;">₹${formatNumber(verifiedAmt)}</td>
                                <td style="text-align:right;font-weight:bold;color:#15648C;">₹${formatNumber(approvedAmt)}</td>
                                <td style="text-align:center;">
                                    <span onclick="FunPrint(${record.EmployeeID},${record.Month},{{ session('year_id') }},1)" class="print-link">One</span> |
                                    <span onclick="FunPrint(${record.EmployeeID},${record.Month},{{ session('year_id') }},2)" class="print-link">Two</span>
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn btn-sm btn-warning"
                                        onclick="closeClaimMonth(${record.Month},${record.EmployeeID},'${escapeHtml((record.userName || '').replace(/'/g, "\\'"))}','${monthName}')">
                                        <i class="ri-check-double-line"></i> Finance Close
                                    </button>
                                </td>
                            </tr>
                            <tr id="${record.EmployeeID}${record.Month}Approved" style="display:none;">
                                <td colspan="13" id="detail_${record.EmployeeID}_${record.Month}"></td>
                            </tr>
                            <input type="hidden" id="sts${record.EmployeeID}${record.Month}Approved" value="close">`;
                    });

                    let grandClaims = data.totals ? data.totals.claims : pageTotalClaims;
                    let grandClaimed = data.totals ? data.totals.claimed : pageTotalClaimed;
                    let grandVerified = data.totals ? data.totals.verified : pageTotalVerified;
                    let grandApproved = data.totals ? data.totals.approved : pageTotalApproved;

                    html += `</tbody>
                        <tfoot class="table-light fw-bold" style="background:#f8fafc;border-top:2px solid #15648C;">
                            <tr>
                                <td colspan="7" class="text-end fw-bold text-dark" style="font-size:12px;">Total:</td>
                                <td class="text-center fw-bold" style="font-size:12px;">${formatNumber(grandClaims)}</td>
                                <td class="text-end fw-bold" style="font-size:12px;">₹${formatNumber(grandClaimed)}</td>
                                <td class="text-end fw-bold" style="font-size:12px;">₹${formatNumber(grandVerified)}</td>
                                <td class="text-end fw-bold text-primary" style="font-size:12px;">₹${formatNumber(grandApproved)}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>`;
                } else {
                    html += `<tr><td colspan="13" class="text-center text-muted py-3">No pending records found</td></tr></tbody>`;
                }

                html += `</table></div>`;
                return html;
            }

            // Generate HTML for approved claims
            function generateApprovedClaimsHTML(data) {
                let pageTotalClaims = 0;
                let pageTotalClaimed = 0;
                let pageTotalVerified = 0;
                let pageTotalApproved = 0;
                let pageTotalFinance = 0;

                let html = `
                    <div class="table-responsive">
                        <table class="estable table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;">S.No</th>
                                    <th>Employee</th>
                                    <th style="width:80px;">Code</th>
                                    <th style="width:120px;">Department</th>
                                    <th style="width:100px;">Designation</th>
                                    <th style="width:80px;">Branch</th>
                                    <th style="width:80px;">Month</th>
                                    <th style="width:60px;">Claims</th>
                                    <th style="width:90px;">Claimed Amt</th>
                                    <th style="width:90px;">Verified Amt</th>
                                    <th style="width:90px;">Appr. Amt</th>
                                    <th style="width:95px;">Finance Amt</th>
                                    <th style="width:95px;">Finance Date</th>
                                    <th style="width:80px;">Print</th>
                                </tr>
                            </thead>
                            <tbody>`;

                if (data.records && data.records.length > 0) {
                    data.records.forEach((record, index) => {
                        let sno = ((currentPage - 1) * data.per_page) + index + 1;
                        let monthName = getMonthName(record.Month);
                        let totalClaims = Number(record.Total_Claim) || 0;
                        let claimedAmt = Number(record.Claim_Amount) || 0;
                        let verifiedAmt = Number(record.Verified_Amount) || 0;
                        let approvedAmt = Number(record.Approved_Amount) || 0;
                        let financeAmt = Number(record.Finance_Amount) || 0;
                        let financeDate = record.Finance_Date && record.Finance_Date != '0000-00-00' ?
                            new Date(record.Finance_Date).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}) : '-';

                        pageTotalClaims += totalClaims;
                        pageTotalClaimed += claimedAmt;
                        pageTotalVerified += verifiedAmt;
                        pageTotalApproved += approvedAmt;
                        pageTotalFinance += financeAmt;

                        html += `
                            <tr>
                                <td style="text-align:center;">${sno}</td>
                                <td>
                                    <a href="#" onclick="showmonthdet(${record.Month},'Closed',${record.EmployeeID},'Closed')" class="view-link fw-semibold">
                                        ${escapeHtml(record.userName || '')}
                                    </a>
                                </td>
                                <td style="text-align:center;"><code>${escapeHtml(record.empCode || '')}</code></td>
                                <td style="text-align:center;"><span class="badge bg-info-subtle text-info" style="font-size:9px;">${escapeHtml(record.department || '')}</span></td>
                                <td style="text-align:center;font-size:10px;">${escapeHtml(record.designation || '')}</td>
                                <td style="text-align:center;font-size:10px;">${escapeHtml(record.branch || '')}</td>
                                <td style="text-align:center;">${monthName}</td>
                                <td style="text-align:center;"><span class="fw-bold">${formatNumber(totalClaims)}</span></td>
                                <td style="text-align:right;">₹${formatNumber(claimedAmt)}</td>
                                <td style="text-align:right;">₹${formatNumber(verifiedAmt)}</td>
                                <td style="text-align:right;">₹${formatNumber(approvedAmt)}</td>
                                <td style="text-align:right;font-weight:bold;color:#16a34a;">₹${formatNumber(financeAmt)}</td>
                                <td style="text-align:center;font-size:10px;">${financeDate}</td>
                                <td style="text-align:center;">
                                    <span onclick="FunPrint(${record.EmployeeID},${record.Month},{{ session('year_id') }},1)" class="print-link">One</span> |
                                    <span onclick="FunPrint(${record.EmployeeID},${record.Month},{{ session('year_id') }},2)" class="print-link">Two</span>
                                </td>
                            </tr>
                            <tr id="${record.EmployeeID}${record.Month}Closed" style="display:none;">
                                <td colspan="14" id="detail_${record.EmployeeID}_${record.Month}"></td>
                            </tr>
                            <input type="hidden" id="sts${record.EmployeeID}${record.Month}Closed" value="close">`;
                    });

                    let grandClaims = data.totals ? data.totals.claims : pageTotalClaims;
                    let grandClaimed = data.totals ? data.totals.claimed : pageTotalClaimed;
                    let grandVerified = data.totals ? data.totals.verified : pageTotalVerified;
                    let grandApproved = data.totals ? data.totals.approved : pageTotalApproved;
                    let grandFinance = data.totals ? data.totals.finance : pageTotalFinance;

                    html += `</tbody>
                        <tfoot class="table-light fw-bold" style="background:#f8fafc;border-top:2px solid #15648C;">
                            <tr>
                                <td colspan="7" class="text-end fw-bold text-dark" style="font-size:12px;">Total:</td>
                                <td class="text-center fw-bold" style="font-size:12px;">${formatNumber(grandClaims)}</td>
                                <td class="text-end fw-bold" style="font-size:12px;">₹${formatNumber(grandClaimed)}</td>
                                <td class="text-end fw-bold" style="font-size:12px;">₹${formatNumber(grandVerified)}</td>
                                <td class="text-end fw-bold" style="font-size:12px;">₹${formatNumber(grandApproved)}</td>
                                <td class="text-end fw-bold text-success" style="font-size:12px;">₹${formatNumber(grandFinance)}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>`;
                } else {
                    html += `<tr><td colspan="14" class="text-center text-muted py-3">No approved records found</td></tr></tbody>`;
                }

                html += `</table></div>`;
                return html;
            }

            // Escape HTML to prevent XSS
            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/[&<>]/g, function (m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }

            // Get month name from month number
            function getMonthName(monthNumber) {
                const months = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
                return months[monthNumber - 1] || '';
            }

            // Format number with commas
            function formatNumber(num) {
                if (!num) return '0';
                return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            // Update pagination
            function updatePagination(data) {
                if (data.last_page > 1) {
                    let paginationHtml = '<div class="pagination-container"><ul class="pagination justify-content-center">';

                    if (currentPage > 1) {
                        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a></li>`;
                    } else {
                        paginationHtml += `<li class="page-item disabled"><span class="page-link">Previous</span></li>`;
                    }

                    let start = Math.max(currentPage - 2, 1);
                    let end = Math.min(start + 4, data.last_page);

                    if (start > 1) {
                        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1)">1</a></li>`;
                        if (start > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    }

                    for (let i = start; i <= end; i++) {
                        if (i == currentPage) {
                            paginationHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                        } else {
                            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
                        }
                    }

                    if (end < data.last_page) {
                        if (end < data.last_page - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${data.last_page})">${data.last_page}</a></li>`;
                    }

                    if (currentPage < data.last_page) {
                        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a></li>`;
                    } else {
                        paginationHtml += `<li class="page-item disabled"><span class="page-link">Next</span></li>`;
                    }

                    paginationHtml += `</ul></div>`;
                    paginationHtml += `<div class="text-center mt-2"><span class="total-records-badge">Total ${data.total} Records</span></div>`;

                    $('#records-container').append(paginationHtml);
                } else if (data.total > 0) {
                    $('#records-container').append(`<div class="text-center mt-3"><span class="total-records-badge">Total ${data.total} Records</span></div>`);
                }
            }

            // Change page
            function changePage(page) {
                currentPage = page;
                loadRecords();
            }


            // Close claim month (approve all claims)
            function closeClaimMonth(month, crby, username, monthname) {
                // Check if details table for this employee and month is currently open on screen
                var detailCell = $('#detail_' + crby + '_' + month);
                var claimsToSubmit = [];
                var hasValidationErrors = false;

                if (detailCell.length && detailCell.is(':visible')) {
                    detailCell.find('tr.claim-row').each(function () {
                        var row = $(this);
                        var expid = row.attr('data-expid');
                        var ftamtInput = $('#financetamt_' + expid);
                        var remarkInput = $('#financeremark_' + expid);

                        if (ftamtInput.length) {
                            var currentAmt = parseFloat(ftamtInput.val());
                            var approvedAmt = parseFloat(row.attr('data-approved')) || parseFloat(ftamtInput.attr('data-approved')) || 0;
                            var origAmt = parseFloat(ftamtInput.attr('data-orig'));
                            var remark = (remarkInput.val() || '').trim();

                            if (isNaN(currentAmt) || currentAmt < 0) {
                                alert('Please enter a valid Finance Amount for Claim ID #' + expid);
                                ftamtInput.focus();
                                hasValidationErrors = true;
                                return false; // break
                            }

                            var isAmtChanged = (
                                Math.round(currentAmt * 100) !== Math.round(approvedAmt * 100) ||
                                (!isNaN(origAmt) && Math.round(currentAmt * 100) !== Math.round(origAmt * 100))
                            );

                            if (isAmtChanged && (!remark || remark === '')) {
                                alert('Finance Remark is required for Claim ID #' + expid + ' because Finance Amount (₹' + currentAmt + ') differs from Approved Amount (₹' + approvedAmt + '). Please enter a remark before closing.');
                                remarkInput.focus().css({ 'border-color': '#dc3545', 'background-color': '#fff5f5' });
                                hasValidationErrors = true;
                                return false; // break
                            }

                            claimsToSubmit.push({
                                expid: expid,
                                ftamt: currentAmt,
                                approved: approvedAmt,
                                remark: remark
                            });
                        }
                    });
                }

                if (hasValidationErrors) {
                    return;
                }

                if (confirm('Are you sure to Final Close ' + username + '\'s ' + monthname + ' Claim\'s?')) {
                    $.ajax({
                        url: "{{ route('claims.close.month') }}",
                        type: "POST",
                        data: {
                            month: month,
                            crby: crby,
                            claims: claimsToSubmit,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (data) {
                            if (data.status === 'success' || data.message === 'closed' || data.message === 'All claims auto-approved & month closed') {
                                alert(username + '\'s ' + monthname + ' month Closed Successfully');
                                loadRecords();
                            } else {
                                alert(data.message || 'Error closing claim month');
                            }
                        },
                        error: function (xhr) {
                            var errMsg = xhr.responseJSON?.message || 'Error closing claim month';
                            alert(errMsg);
                        }
                    });
                }
            }

            // Viewer State
            let currentViewerRotation = 0;
            let currentViewerZoom = 1;
            let currentViewerFiles = [];
            let currentViewerIndex = 0;

            function updateViewerTransform() {
                $('#activeViewerImg').css({
                    'transform': `scale(${currentViewerZoom}) rotate(${currentViewerRotation}deg)`,
                    'transition': 'transform 0.2s ease-in-out'
                });
            }

            function rotateViewerImage(deg) {
                currentViewerRotation = (currentViewerRotation + deg) % 360;
                updateViewerTransform();
            }

            function zoomViewerImage(factor) {
                currentViewerZoom = Math.max(0.5, Math.min(3.5, currentViewerZoom + factor));
                updateViewerTransform();
            }

            function resetViewerImage() {
                currentViewerRotation = 0;
                currentViewerZoom = 1;
                updateViewerTransform();
            }

            function switchViewerFile(idx) {
                if (!currentViewerFiles || !currentViewerFiles[idx]) return;
                currentViewerIndex = idx;
                currentViewerRotation = 0;
                currentViewerZoom = 1;

                let file = currentViewerFiles[idx];
                let fileUrl = file.file_url || '';
                let isPdf = fileUrl.toLowerCase().endsWith('.pdf') || fileUrl.toLowerCase().includes('.pdf');

                if (isPdf) {
                    $('#viewerImageContainer').html(`
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 p-4 text-center">
                            <i class="ri-file-pdf-fill text-danger" style="font-size: 64px;"></i>
                            <h6 class="mt-2 text-dark">${escapeHtml(file.file_name || 'Document.pdf')}</h6>
                            <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                <i class="ri-external-link-line me-1"></i> Open PDF
                            </a>
                        </div>
                    `);
                    $('#viewerControls').hide();
                } else {
                    $('#viewerImageContainer').html(`
                        <img id="activeViewerImg" src="${fileUrl}" alt="${escapeHtml(file.file_name || '')}"
                             style="max-height: 440px; max-width: 100%; object-fit: contain; transform-origin: center center; cursor: pointer;"
                             onclick="window.open('${fileUrl}','_blank')"
                             title="Click to view full image">
                    `);
                    $('#viewerControls').show();
                    updateViewerTransform();
                }

                $('#viewerDownloadBtn').attr('href', fileUrl).attr('download', file.file_name || 'claim_attachment');
                $('#viewerOpenBtn').attr('href', fileUrl);
                $('#viewerFileName').text(file.file_name || '');

                $('.viewer-thumb').removeClass('border-primary border-2 shadow-sm').addClass('opacity-75');
                $(`.viewer-thumb[data-idx="${idx}"]`).addClass('border-primary border-2 shadow-sm').removeClass('opacity-75');
            }

            // Show expense details in modal
            function showexpdet(expid, claimId) {
                var modal = new bootstrap.Modal(document.getElementById('myModal'));
                modal.show();

                $('#claimlistContainer').html('<div class="p-4 text-center"><div class="spinner-border text-primary" role="status"></div><br><span class="text-muted mt-2 d-inline-block">Loading claim details...</span></div>');

                $.get("{{ url('claim-detail') }}", {
                    claim_id: claimId,
                    expid: expid,
                    source: 'claims'
                }, function(data) {
                    let eligibilityHtml = buildEligibilityPanel(data.eligibility);
                    currentViewerFiles = data.uploaded_files || [];
                    currentViewerIndex = 0;
                    currentViewerRotation = 0;
                    currentViewerZoom = 1;

                    let finalHtml = `
                    <div class="row g-0 m-0">
                        <!-- LEFT: BIG VIEWER + CONTROLS -->
                        <div class="col-lg-5 col-md-12 border-end bg-light p-3 d-flex flex-column" style="min-height:500px; max-height:680px;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 text-primary fw-bold" style="font-size: 13px;">
                                    <i class="ri-image-line me-1"></i> Attachment Preview
                                </h6>
                                <div id="viewerControls" class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" onclick="rotateViewerImage(-90)" title="Rotate Left (-90°)">
                                        <i class="ri-anticlockwise-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="rotateViewerImage(90)" title="Rotate Right (+90°)">
                                        <i class="ri-clockwise-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="zoomViewerImage(0.25)" title="Zoom In (+)">
                                        <i class="ri-zoom-in-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="zoomViewerImage(-0.25)" title="Zoom Out (-)">
                                        <i class="ri-zoom-out-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetViewerImage()" title="Reset (0° / 100%)">
                                        <i class="ri-restart-line"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Main Display Container -->
                            <div class="bg-white border rounded p-2 d-flex align-items-center justify-content-center position-relative flex-grow-1"
                                 style="min-height: 400px; max-height: 460px; overflow: hidden; background: repeating-conic-gradient(#f8fafc 0% 25%, #ffffff 0% 50%) 50% / 20px 20px;"
                                 id="viewerImageContainer">
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                                <small class="text-truncate text-muted me-2" id="viewerFileName" style="max-width: 220px; font-size: 11px;"></small>
                                <div class="d-flex gap-1">
                                    <a id="viewerDownloadBtn" href="#" download class="btn btn-sm btn-primary shadow-sm" style="font-size: 11px;">
                                        <i class="ri-download-2-line"></i> Download
                                    </a>
                                    <a id="viewerOpenBtn" href="#" target="_blank" class="btn btn-sm btn-outline-secondary shadow-sm" style="font-size: 11px;" title="Open in new tab">
                                        <i class="ri-external-link-line"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Thumbnail Gallery (if multiple) -->
                            <div id="viewerThumbnailsContainer" class="d-flex gap-2 mt-2 pt-2 border-top overflow-auto" style="max-height: 70px;"></div>
                        </div>

                        <!-- RIGHT: FORM + ELIGIBILITY -->
                        <div class="col-lg-7 col-md-12 p-3" style="max-height:680px; overflow-y:auto;">
                            ${data.html}
                            ${eligibilityHtml}
                        </div>
                    </div>
                    `;

                    $('#claimlistContainer').html(finalHtml);

                    if (currentViewerFiles.length > 0) {
                        // Render thumbnails if multiple files
                        if (currentViewerFiles.length > 1) {
                            let thumbHtml = '';
                            currentViewerFiles.forEach(function(file, idx) {
                                let isPdf = (file.file_url || '').toLowerCase().endsWith('.pdf');
                                if (isPdf) {
                                    thumbHtml += `
                                        <div class="viewer-thumb border rounded p-1 text-center bg-white cursor-pointer ${idx === 0 ? 'border-primary border-2 shadow-sm' : 'opacity-75'}"
                                             data-idx="${idx}" onclick="switchViewerFile(${idx})" style="width: 50px; height: 50px; cursor: pointer; flex-shrink: 0;">
                                            <i class="ri-file-pdf-fill text-danger" style="font-size: 24px;"></i>
                                        </div>
                                    `;
                                } else {
                                    thumbHtml += `
                                        <img alt="Bill or document image" src="${file.file_url}" class="viewer-thumb border rounded ${idx === 0 ? 'border-primary border-2 shadow-sm' : 'opacity-75'}"
                                             data-idx="${idx}" onclick="switchViewerFile(${idx})"
                                             style="width: 50px; height: 50px; object-fit: cover; cursor: pointer; flex-shrink: 0;">
                                    `;
                                }
                            });
                            $('#viewerThumbnailsContainer').html(thumbHtml);
                        } else {
                            $('#viewerThumbnailsContainer').hide();
                        }

                        // Load initial file
                        switchViewerFile(0);
                    } else {
                        $('#viewerImageContainer').html('<div class="text-muted text-center py-5"><i class="ri-file-damage-line" style="font-size:48px;"></i><br>No files uploaded</div>');
                        $('#viewerControls').hide();
                        $('#viewerDownloadBtn').hide();
                        $('#viewerOpenBtn').hide();
                        $('#viewerThumbnailsContainer').hide();
                    }
                }).fail(function(xhr) {
                    console.error('Error:', xhr.responseText);
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error loading claim details. Please try again.';
                    $('#claimlistContainer').html('<div class="alert alert-danger m-3"><i class="ri-error-warning-line me-1"></i> ' + msg + '</div>');
                });
            }

/**
 * Build eligibility validation panel HTML
 * (Uses shared buildEligibilityPanel from eligibility_panel.js)
 */

function formatNum(num) {
    return eligibilityFormatNum(num);
}


            // Finance claim approval
            function financeClaim(t, expid) {
                var f = parseInt($('#' + expid + 'financetamt').val());
                var remark = $('#' + expid + 'financetremark').val();
                var claimedAmt = parseInt($('#' + expid + '__FilledTAmt').text().trim()) || 0;
                var verifiedAmt = parseInt($('#' + expid + '__VerifyTAmt').text().trim()) || 0;
                var approvedAmt = parseInt($('#' + expid + '__ApprTAmt').text().trim()) || 0;

                var maxAmt = Math.max(claimedAmt, verifiedAmt, approvedAmt);
                var errorMessage = '';

                if (isNaN(f) || f > maxAmt || f < 0) {
                    errorMessage = `Approved amount cannot be greater than ₹${maxAmt}.`;
                }

                if (!errorMessage && f !== maxAmt) {
                    if (!remark) {
                        errorMessage = 'Remark is required when the amount is changed.';
                    } else if (/[^a-zA-Z0-9\s]/.test(remark)) {
                        errorMessage = 'Remark cannot contain special characters.';
                    } else if (remark.toLowerCase() === 'ok') {
                        errorMessage = 'The text "ok" is not allowed in the remark.';
                    }
                }

                if (errorMessage) {
                    alert(errorMessage);
                    return;
                }

                var msg = t == 'A' ? 'Approve' : 'Reject';
                var agree = confirm("Are you sure, you want to " + msg + " claim?");

                if (agree) {
                    $.ajax({
                        url: "{{ route('claims.approve-finance') }}",
                        type: "POST",
                        data: {
                            act: "financeClaim",
                            expid: expid,
                            ftamt: f,
                            financetremark: remark,
                            t: t,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (data) {
                            if (data.message == 'financed' || data.message == 'success') {
                                $('#' + expid + 'Status').html('Ok');
                                $('#' + expid + 'btn').html('View');
                                $('#' + expid + '_status').html('Approved').attr('class', 'text-success fw-bold');
                                recalcFinanceTotals(expid);
                                alert('Approved Successfully');
                                $('#financection' + expid).html('');
                            }
                            if (data.message == 'Rejected') {
                                $('#' + expid + 'Status').html('Rejected');
                                $('#' + expid + 'btn').html('View');
                                $('#' + expid + '_status').html('Rejected').attr('class', 'text-danger fw-bold');
                                recalcFinanceTotals(expid);
                                alert('Rejected Successfully');
                                $('#financection' + expid).html('');
                            }
                        }
                    });
                }
            }





            // FIN-TOTAL-01: recompute the Finance total (top and bottom rows) after each save,
            // from the amounts now in the rows; a rejected claim counts as 0.
            function recalcFinanceTotals(anyExpid) {
                var table = $('#claim_row_' + anyExpid).closest('table');
                if (!table.length) return;
                var sum = 0;
                table.find('tr.claim-row').each(function () {
                    var id = $(this).attr('data-expid');
                    var st = ($('#' + id + '_status').text() || '').trim();
                    var input = $('#financetamt_' + id);
                    var v = input.length ? parseFloat(input.val()) : parseFloat($(this).attr('data-finance'));
                    if (st === 'Rejected' || isNaN(v)) v = 0;
                    $(this).attr('data-finance', v);
                    sum += v;
                });
                table.find('.fin-total-financed').text(Math.round(sum).toLocaleString('en-IN'));
            }

            function approveClaimFinance(expid) {
                var ftamtInput = $('#financetamt_' + expid);
                var remarkInput = $('#financeremark_' + expid);
                var ftamt = ftamtInput.val();
                var remark = (remarkInput.val() || '').trim();

                if (ftamt === '' || isNaN(ftamt) || parseFloat(ftamt) < 0) {
                    alert('Please enter a valid amount');
                    ftamtInput.focus();
                    return;
                }

                var row = $('#claim_row_' + expid);
                var currentAmt = parseFloat(ftamt);
                var approvedAmt = parseFloat(row.attr('data-approved')) || parseFloat(ftamtInput.attr('data-approved')) || 0;
                var origAmt = parseFloat(ftamtInput.attr('data-orig'));

                var isAmtChanged = (
                    Math.round(currentAmt * 100) !== Math.round(approvedAmt * 100) ||
                    (!isNaN(origAmt) && Math.round(currentAmt * 100) !== Math.round(origAmt * 100))
                );

                if (isAmtChanged && (!remark || remark === '')) {
                    alert('Finance Remark is required because Finance Amount (₹' + currentAmt + ') differs from Approved Amount (₹' + approvedAmt + ').');
                    remarkInput.focus().css({ 'border-color': '#dc3545', 'background-color': '#fff5f5' });
                    return;
                }

                // U-01: name the claim and the amounts in the confirmation
                if (confirm('Approve claim ExpId ' + expid + ' for finance?\n\nApproved amount: Rs. ' + approvedAmt.toLocaleString('en-IN') +
                        '\nFinance amount: Rs. ' + currentAmt.toLocaleString('en-IN') + (isAmtChanged ? '\nRemark: ' + remark : ''))) {
                    $.ajax({
                        url: "{{ route('claims.approve-finance') }}",
                        type: "POST",
                        data: {
                            expid: expid,
                            ftamt: ftamt,
                            remark: remark,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            if (response.message == 'success' || response.message == 'financed' || response.status == 'financed') {
                                // Update status inline
                                $('#' + expid + '_status').html('Approved').attr('class', 'text-success fw-bold');
                                remarkInput.css({ 'border-color': '', 'background-color': '' });
                                ftamtInput.attr('data-orig', ftamt);
                                recalcFinanceTotals(expid);
                                alert('Claim approved successfully!');
                            } else {
                                alert(response.message || 'Error approving claim');
                            }
                        },
                        error: function (xhr) {
                            var errMsg = xhr.responseJSON?.message || 'Error approving claim';
                            alert(errMsg);
                            if (errMsg.toLowerCase().includes('remark')) {
                                remarkInput.focus().css({ 'border-color': '#dc3545', 'background-color': '#fff5f5' });
                            }
                        }
                    });
                }
            }

            // Reject claim from finance
            function rejectClaimFinance(expid) {
                var remark = $('#financeremark_' + expid).val();

                if (!remark) {
                    alert('Please enter a reason for rejection');
                    return;
                }

                if (confirm('Are you sure you want to reject this claim?')) {
                    $.ajax({
                        url: "{{ route('claims.reject-finance') }}",
                        type: "POST",
                        data: {
                            expid: expid,
                            remark: remark,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            if (response.message == 'success' || response.message == 'Rejected') {
                                // Update status inline
                                $('#' + expid + '_status').html('Rejected').attr('class', 'text-danger fw-bold');
                                recalcFinanceTotals(expid);
                                alert('Claim rejected!');
                            }
                        },
                        error: function () {
                            alert('Error rejecting claim');
                        }
                    });
                }
            }

            // Number validation
            function isNumberKey(evt) {
                var charCode = (evt.which) ? evt.which : event.keyCode;
                if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
                    return false;
                }
                return true;
            }

            // Update the showmonthdet function to store current values
            function showmonthdet(month, sts, emp, csts) {

                var idBase = String(emp) + String(month) + csts;

                var status = document.getElementById('sts' + idBase);
                var detailRow = document.getElementById(idBase);
                var detailCell = document.getElementById('detail_' + emp + '_' + month);

                if (!status || !detailRow || !detailCell) {
                    console.error('Element not found', { status, detailRow, detailCell });
                    return;
                }

                if (status.value === 'close') {

                    status.value = 'open';
                    detailRow.style.display = 'table-row';

                    detailCell.innerHTML = `
                                    <div class="text-center p-3">
                                        <div class="spinner-border spinner-border-sm"></div>
                                        Loading...
                                    </div>
                                `;

                    $.ajax({
                        url: "{{ route('claims.month.details') }}",
                        type: "POST",
                        data: {
                            act: "monthdettofinance",
                            month: month,
                            sts: sts,
                            csts: csts,
                            emp: emp,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            detailCell.innerHTML = response?.html || 'No data';
                        }
                    });

                } else {
                    status.value = 'close';
                    detailRow.style.display = 'none';
                }
            }

            // Add hidden inputs to store current values
            $(document).ready(function () {
                $('body').append('<input type="hidden" id="current_month" /><input type="hidden" id="current_emp" />');
                loadRecords();

                // Enter key triggers search
                $('#filterSearch').on('keypress', function(e) {
                    if (e.which === 13) applyFilters();
                });

                // Real-time amount difference recalculation when Finance Amt is edited
                $(document).on('input change', 'input[id^="financetamt_"]', function () {
                    var expid = this.id.replace('financetamt_', '');
                    var row = $('#claim_row_' + expid);
                    if (!row.length) row = $(this).closest('tr');

                    var currentVal = parseFloat($(this).val());
                    if (isNaN(currentVal)) currentVal = 0;

                    var claimed = parseFloat(row.attr('data-claimed')) || 0;
                    var verified = parseFloat(row.attr('data-verified')) || 0;
                    var approved = parseFloat(row.attr('data-approved')) || 0;

                    // Check if amounts differ across stages
                    var isDiff = (
                        Math.round(claimed * 100) !== Math.round(verified * 100) ||
                        Math.round(verified * 100) !== Math.round(approved * 100) ||
                        Math.round(approved * 100) !== Math.round(currentVal * 100) ||
                        Math.round(claimed * 100) !== Math.round(approved * 100) ||
                        Math.round(claimed * 100) !== Math.round(currentVal * 100)
                    );

                    // If finance amt differs from approved amt, require remark
                    var remarkInput = $('#financeremark_' + expid);
                    var remark = (remarkInput.val() || '').trim();
                    var isAmtChangedFromAppr = (Math.round(approved * 100) !== Math.round(currentVal * 100));

                    if (isAmtChangedFromAppr && !remark) {
                        remarkInput.attr('placeholder', 'Remark required!').css({ 'border-color': '#eab308', 'background-color': '#fffde7' });
                    } else if (!isAmtChangedFromAppr && !remark) {
                        remarkInput.attr('placeholder', 'Remark').css({ 'border-color': '', 'background-color': '' });
                    }

                    var statusText = $('#' + expid + '_status').text().trim();
                    if (statusText !== 'Rejected') {
                        if (isDiff) {
                            row.css('background-color', '#FFF9D6');
                            $(this).css({ 'border-color': '#eab308', 'background-color': '#fffde7' });
                        } else {
                            row.css('background-color', '#FFFFFF');
                            $(this).css({ 'border-color': '', 'background-color': '' });
                        }
                    }
                });

                // Clear remark warning styling when user types remark
                $(document).on('input change', 'textarea[id^="financeremark_"]', function () {
                    var expid = this.id.replace('financeremark_', '');
                    var remark = ($(this).val() || '').trim();
                    var ftamt = parseFloat($('#financetamt_' + expid).val()) || 0;
                    var row = $('#claim_row_' + expid);
                    var approved = parseFloat(row.attr('data-approved')) || 0;
                    var isAmtChangedFromAppr = (Math.round(approved * 100) !== Math.round(ftamt * 100));

                    if (isAmtChangedFromAppr && !remark) {
                        $(this).attr('placeholder', 'Remark required!').css({ 'border-color': '#dc3545', 'background-color': '#fff5f5' });
                    } else {
                        $(this).css({ 'border-color': '', 'background-color': '' });
                    }
                });
            });
        </script>
    @endpush
@endsection
