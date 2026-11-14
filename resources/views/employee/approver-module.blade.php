@php
    use Illuminate\Support\Facades\Route;

    $prefix = $prefix ?? 'vnr';
    $status = $status ?? 'pending';
    $companyId = $companyId ?? session('company_id', 1);
    $routeName = fn (string $name) => "{$prefix}.{$name}";
    $routeUrl = fn (string $name) => Route::has($routeName($name)) ? route($routeName($name)) : null;

    $approverRoutes = [
        'pendingList' => $routeUrl('approver.pending.list'),
        'pendingDay' => $routeUrl('approver.pending.day'),
        'approvedList' => $routeUrl('approver.approved.list'),
        'approvedDay' => $routeUrl('approver.approved.day'),
        'approveClaim' => $routeUrl('approver.claim.approve'),
        'approveHeadwise' => $routeUrl('approver.claim.approve.headwise'),
        'submitClaim' => $routeUrl('approver.claim.submit'),
        'expenseDetail' => $routeUrl('approver.expense.detail'),
        'teamMembers' => $routeUrl('team.members.list'),
        'teamExpenses' => $routeUrl('team.all.expenses'),
    ];

    $actionLinks = [
        'backdate' => Route::has('admin.odo.backdate.approval') ? route('admin.odo.backdate.approval') : url('odo-backdate-approval'),
        'advance' => Route::has('advance.approver.index')
            ? route('advance.approver.index')
            : (Route::has('advanceApprovals.requests.destroy') ? route('advanceApprovals.requests.destroy') : url('advance-requests/advanceApprovals')),
        'odo' => Route::has('admin.odo.approval') ? route('admin.odo.approval') : url('odo-backdate-approval'),
    ];
@endphp

<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12">
                <div class="card card-height-100">
                    <x-action.approver-btn :prefix="$prefix" :status="$status" />
                    <div class="card-body pb-3 pt-3">
                        <div class="d-flex flex-wrap gap-2 mb-3" id="actionButtonsRow">
                            @if($prefix !== 'vvnr' && session('show_backdate_approver') == 'Y')
                                <a href="{{ $actionLinks['backdate'] }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                                    <img alt="" src="{{ asset('assets/images/excess-running-approval.png') }}" width="16" height="16" onerror="this.style.display='none'">
                                    <span>Delayed Odo Details</span>
                                </a>
                            @endif

                            @if($prefix !== 'vvnr' && session('show_approver_advance') == 'Y')
                                <a href="{{ $actionLinks['advance'] }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                                    <img alt="" src="{{ asset('assets/images/advance-request.png') }}" width="16" height="16" onerror="this.style.display='none'">
                                    <span>Adv. Req.</span>
                                </a>
                                @if (\App\Http\Controllers\AdvanceRequestViewController::reportAllowed(auth()->user())) {{-- ADV-REPORT-01 --}}
                                <a href="{{ url('/advance-requests/report') }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" title="Advance Report">
                                    <i class="ri-file-chart-line"></i>
                                    <span>Adv. Report</span>
                                </a>
                                @endif
                            @endif

                            @if($prefix !== 'vvnr' && session('show_odo_approver') == 'Y')
                                <a href="{{ $actionLinks['odo'] }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                                    <img alt="" src="{{ asset('assets/images/excess-running-approval.png') }}" width="16" height="16" onerror="this.style.display='none'">
                                    <span>Excess Running</span>
                                </a>
                            @endif

                            <a href="javascript:void(0)" id="openTeamExpenseBtn" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                <img alt="" src="{{ asset('assets/images/all-team-expense.png') }}" width="16" height="16" onerror="this.style.display='none'">
                                <span>Team Exp.</span>
                            </a>
                        </div>

                        @if($status === 'pending')
                            <div class="alert mb-3 py-2 px-3 approver-warning">
                                <i class="ri-error-warning-line me-1"></i>
                                If there are no changes in the approved amount, kindly click on submit button directly.
                            </div>

                            <div class="table-responsive">
                                <table id="pendingTable" class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr class="text-center align-middle">
                                            <th rowspan="2" class="align-middle text-center">Sr No.</th>
                                            <th rowspan="2" class="align-middle text-center">Claimant Name</th>
                                            <th rowspan="2" class="align-middle text-center">Month</th>
                                            <th colspan="2" class="align-middle text-center">Total</th>
                                            <th rowspan="2" class="align-middle text-center">Courier<br>Status</th>
                                            <th rowspan="2" class="align-middle text-center">Check</th>
                                            <th rowspan="2" class="align-middle text-center">Action</th>
                                        </tr>
                                        <tr class="text-center align-middle">
                                            <th class="align-middle text-center">Claims</th>
                                            <th class="align-middle text-center">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($status === 'approved')
                            <div class="row g-2 mb-3 align-items-end" id="approvedFilterRow">
                                <div class="col-md-4 col-sm-6">
                                    <label class="form-label fw-semibold mb-1 small">Team Member</label>
                                    <select class="form-select form-select-sm" id="filterApprovedMember">
                                        <option value="">All Members</option>
                                    </select>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label fw-semibold mb-1 small">Month</label>
                                    <select class="form-select form-select-sm" id="filterApprovedMonth">
                                        <option value="">All Months</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm btn-primary" id="applyApprovedFilter">
                                        <i class="ri-search-line"></i> Search
                                    </button>
                                    <button class="btn btn-sm btn-light ms-1" id="resetApprovedFilter">
                                        <i class="ri-refresh-line"></i> Reset
                                    </button>
                                </div>
                            </div>

                            <div id="approvedTableWrapper" style="display:none;">
                                <div class="table-responsive">
                                    <table id="approvedTable" class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr class="text-center align-middle">
                                                <th rowspan="2" class="align-middle text-center">Sr No.</th>
                                                <th rowspan="2" class="align-middle text-center">Name</th>
                                                <th rowspan="2" class="align-middle text-center">Month</th>
                                                <th rowspan="2" class="align-middle text-center">Courier Received</th>
                                                <th colspan="4" class="align-middle text-center">Status (Amount &amp; Submitted Date)</th>
                                                <th rowspan="2" class="align-middle text-center">View</th>
                                            </tr>
                                            <tr class="text-center align-middle">
                                                <th class="align-middle text-center">Claimant</th>
                                                <th class="align-middle text-center">Verifier</th>
                                                <th class="align-middle text-center">Approver</th>
                                                <th class="align-middle text-center">Finance</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="approvedPlaceholder" class="text-center py-5">
                                <i class="ri-filter-3-line display-4 text-muted"></i>
                                <p class="mt-2 text-muted fw-semibold">Use the filter above to view approved claims.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pendingDayModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="w-100">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <h5 class="modal-title">Approval Pending : <span id="pendingEmployeeName"></span></h5>
                        <div class="p-1 bg-info bg-opacity-50 rounded small d-flex flex-wrap align-items-center gap-2 approver-summary">
                            <div><strong>Month:</strong> <span id="pendingMonthName">-</span></div>
                            <div><strong>Courier Received:</strong> <span id="pendingCourier">-</span></div>
                            <div><strong>Claimant:</strong> Rs.<span id="pendingClaimantAmt">0</span></div>
                            <div><strong>Verifier:</strong> Rs.<span id="pendingVerifierAmt">0</span></div>
                            <div><strong>Diff Amt:</strong> Rs.<span id="pendingDiffAmt">0</span></div>
                            <div><strong>Approver:</strong> Rs.<span id="pendingApproverAmt">0</span></div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="openDayModalBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="approvedDayModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="w-100">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <h5 class="modal-title">Approved List : <span id="approvedEmployeeName"></span></h5>
                        <div class="p-1 bg-info bg-opacity-50 rounded small d-flex flex-wrap align-items-center gap-2 approver-summary">
                            <div><strong>Month:</strong> <span id="approvedMonthName">-</span></div>
                            <div><strong>Courier Received:</strong> <span id="approvedCourier">-</span></div>
                            <div><strong>Claimant:</strong> Rs.<span id="approvedClaimantAmt">0</span></div>
                            <div><strong>Verifier:</strong> Rs.<span id="approvedVerifierAmt">0</span></div>
                            <div><strong>Approver:</strong> Rs.<span id="approvedApproverAmt">0</span></div>
                            <div><strong>Finance:</strong> Rs.<span id="approvedFinanceAmt">0</span></div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="closeDayModalBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="teamAllExpenseModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="w-100 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <h5 class="modal-title"><i class="ri-team-line"></i> Team All Expenses Report</h5>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <select class="form-select form-select-sm" id="filterClaimType" style="min-width:220px;">
                            <option value="">All Claim Types</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" id="applyFiltersBtn">
                            <i class="ri-filter-3-line"></i> Apply
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="resetFiltersBtn">
                            <i class="ri-refresh-line"></i> Reset
                        </button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="teamExpenseLoader" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Loading team expenses...</p>
                </div>
                <div class="table-responsive" id="teamExpenseTableContainer" style="display:none;">
                    <table class="table table-bordered table-hover mb-0" id="teamExpenseTable">
                        <thead class="table-light">
                            <tr class="text-center align-middle">
                                <th rowspan="2" class="align-middle text-start" style="min-width:180px;">Claim Type</th>
                                <th colspan="12" class="align-middle">Month-wise Expenses (Rs.)</th>
                                <th rowspan="2" class="align-middle" style="min-width:120px;">Total</th>
                            </tr>
                            <tr class="text-center">
                                <th>Apr</th><th>May</th><th>Jun</th><th>Jul</th><th>Aug</th><th>Sep</th>
                                <th>Oct</th><th>Nov</th><th>Dec</th><th>Jan</th><th>Feb</th><th>Mar</th>
                            </tr>
                        </thead>
                        <tbody id="teamExpenseTableBody"></tbody>
                        <tfoot class="table-light fw-bold">
                            <tr class="text-center" id="teamExpenseGrandTotal"></tr>
                        </tfoot>
                    </table>
                </div>
                <div id="noDataMessage" class="text-center py-5" style="display:none;">
                    <i class="ri-inbox-line display-1 text-muted"></i>
                    <p class="mt-3 text-muted fs-5">No expense data available</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .approver-warning {
        background-color: #fff0f0;
        border-left: 4px solid #d00000;
        color: #d00000;
        font-weight: 600;
        font-size: 13px;
    }
    .approver-summary {
        max-width: 100%;
        background-color: rgba(41, 156, 219, 0.16) !important;
    }
    .approver-modal-table th,
    .approver-modal-table td {
        white-space: nowrap;
        vertical-align: middle;
    }
    .approver-modal-table .remark-cell {
        min-width: 180px;
        white-space: normal;
    }
    .rotating {
        animation: approver-spin 1s linear infinite;
        display: inline-block;
    }
    @keyframes approver-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
<script>
    window.openPopUpWindow = function(url) {
        window.open(url, "_blank", "toolbar=no,scrollbars=yes,resizable=yes,top=80,left=200,width=900,height=520");
    };

    $(function() {
        const currentStatus = @json($status);
        const prefix = @json($prefix);
        const comId = @json((string) $companyId);
        const routes = @json($approverRoutes);
        const csrfToken = @json(csrf_token());
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const fiscalMonths = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        let teamExpenseRows = [];
        let lastPendingContext = null;

        const loaderHtml = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

        if (currentStatus === 'pending') {
            loadPendingList();
        }
        if (currentStatus === 'approved') {
            loadTeamMembersForFilter();
        }

        $('#applyApprovedFilter').on('click', function() {
            loadApprovedList($('#filterApprovedMember').val(), $('#filterApprovedMonth').val());
        });

        $('#resetApprovedFilter').on('click', function() {
            $('#filterApprovedMember').val('');
            $('#filterApprovedMonth').val('');
            $('#approvedPlaceholder').show();
            $('#approvedTableWrapper').hide();
            destroyDataTable('#approvedTable');
            $('#approvedTable tbody').empty();
        });

        $('#openTeamExpenseBtn').on('click', function() {
            $('#teamAllExpenseModal').modal('show');
            loadAllTeamExpenses();
        });

        $('#applyFiltersBtn').on('click', function() {
            renderTeamExpenseTable(filterTeamExpenseRows());
        });

        $('#resetFiltersBtn').on('click', function() {
            $('#filterClaimType').val('');
            renderTeamExpenseTable(teamExpenseRows);
        });

        $(document).on('change', 'input[name="prefixSwitch"]', function() {
            window.location.href = '/' + $(this).val() + '/approver?status=' + currentStatus;
        });

        function requireRoute(name) {
            if (!routes[name]) {
                Swal.fire('Unavailable', 'This action is not configured for ' + prefix.toUpperCase() + '.', 'warning');
                return false;
            }
            return true;
        }

        function loadPendingList() {
            if (!requireRoute('pendingList')) return;
            $.getJSON(routes.pendingList)
                .done(function(response) {
                    const list = Array.isArray(response.Pending_list) ? response.Pending_list : [];
                    renderPendingTable(list);
                })
                .fail(function() {
                    $('#pendingTable tbody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load pending claims.</td></tr>');
                });
        }

        function renderPendingTable(list) {
            const $tbody = $('#pendingTable tbody').empty();
            destroyDataTable('#pendingTable');

            if (!list.length) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No pending claims available.</td></tr>');
                return;
            }

            list.forEach(function(item, index) {
                const employeeId = valueOf(item, ['EmployeeID', 'employee_id']);
                const month = valueOf(item, ['Month', 'month']);
                const yearId = valueOf(item, ['YearId', 'year_id']);
                const empName = employeeName(item);
                const claimAmt = amountOf(item, ['Claim_Amount', 'claimAmount']);
                const verifiedAmt = amountOf(item, ['Verified_Amount', 'verifiedAmount']);
                const approvedAmt = amountOf(item, ['Approved_Amount', 'approvedAmount', 'TotalApprovedAmt']);
                const courier = valueOf(item, ['RecevingDate', 'ReceivingDate', 'PostDate']);

                $tbody.append(`
                    <tr>
                        <td class="align-middle text-center fw-bold">${index + 1}</td>
                        <td class="align-middle text-center">${escapeHtml(empName)}</td>
                        <td class="align-middle text-center">
                            <a href="javascript:void(0)" class="view-pending-day text-primary text-decoration-underline fw-semibold"
                                data-empid="${escapeAttr(employeeId)}" data-month="${escapeAttr(month)}" data-yearid="${escapeAttr(yearId)}"
                                data-empname="${escapeAttr(empName)}" data-courier="${escapeAttr(courier)}"
                                data-claimamt="${claimAmt}" data-verifiedamt="${verifiedAmt}" data-approvedamt="${approvedAmt}">
                                ${monthName(month)}
                            </a>
                        </td>
                        <td class="align-middle text-center">${escapeHtml(valueOf(item, ['Total_Claim', 'totalClaim'], '0'))}</td>
                        <td class="align-middle text-center">Rs.${formatAmount(claimAmt)}</td>
                        <td class="align-middle text-center">${dateBadge(courier)}</td>
                        <td class="align-middle text-center">
                            <div class="d-flex justify-content-center gap-2">
                                <a href="javascript:void(0)" class="badge bg-primary-subtle text-primary text-decoration-none"
                                    onclick="openPopUpWindow('https://vnrseeds.co.in/public/api/Att_records?empid=${encodeURIComponent(employeeId)}&Month=${encodeURIComponent(month)}&Year=${new Date().getFullYear()}')">
                                    <i class="ri-calendar-check-line"></i> Att
                                </a>
                                <a href="javascript:void(0)" class="badge bg-info-subtle text-info text-decoration-none view-pending-day"
                                    data-empid="${escapeAttr(employeeId)}" data-month="${escapeAttr(month)}" data-yearid="${escapeAttr(yearId)}"
                                    data-empname="${escapeAttr(empName)}" data-courier="${escapeAttr(courier)}"
                                    data-claimamt="${claimAmt}" data-verifiedamt="${verifiedAmt}" data-approvedamt="${approvedAmt}">
                                    <i class="ri-eye-line"></i> View
                                </a>
                            </div>
                        </td>
                        <td class="align-middle text-center">
                            <a href="javascript:void(0)" class="badge bg-primary-subtle text-primary submit-claim"
                                data-empid="${escapeAttr(employeeId)}" data-month="${escapeAttr(month)}" data-yearid="${escapeAttr(yearId)}">
                                <i class="ri-check-line"></i> Submit
                            </a>
                        </td>
                    </tr>
                `);
            });

            $('#pendingTable').DataTable({
                ordering: false,
                searching: true,
                paging: true,
                info: true,
                lengthChange: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100]
            });
        }

        $(document).on('click', '.view-pending-day', function(e) {
            e.preventDefault();
            lastPendingContext = {
                empid: $(this).data('empid'),
                month: $(this).data('month'),
                yearid: $(this).data('yearid'),
                empname: $(this).data('empname'),
                courier: $(this).data('courier'),
                claimantAmt: numberValue($(this).data('claimamt')),
                verifierAmt: numberValue($(this).data('verifiedamt')),
                approverAmt: numberValue($(this).data('approvedamt'))
            };

            $('#pendingEmployeeName').text(lastPendingContext.empname || '');
            $('#pendingMonthName').text(monthName(lastPendingContext.month));
            $('#pendingCourier').text(validDate(lastPendingContext.courier) ? formatDate(lastPendingContext.courier) : 'Pending');
            $('#pendingClaimantAmt').text(formatAmount(lastPendingContext.claimantAmt));
            $('#pendingVerifierAmt').text(formatAmount(lastPendingContext.verifierAmt));
            $('#pendingDiffAmt').text(formatAmount(lastPendingContext.claimantAmt - lastPendingContext.verifierAmt));
            $('#pendingApproverAmt').text(formatAmount(lastPendingContext.approverAmt));
            $('#openDayModalBody').html(loaderHtml);
            $('#pendingDayModal').modal('show');

            fetchDayDetails(lastPendingContext.empid, lastPendingContext.month, lastPendingContext.yearid, 'pending');
        });

        function fetchDayDetails(empid, month, yearid, mode) {
            const route = mode === 'approved' ? routes.approvedDay : routes.pendingDay;
            if (!route) return;

            $.getJSON(route, { empid: empid, month: month, yearid: yearid, comid: comId })
                .done(function(response) {
                    const details = Array.isArray(response.DayWiseDetails) ? response.DayWiseDetails : [];
                    if (mode === 'approved') {
                        renderDayDetails(details, '#closeDayModalBody', false);
                    } else {
                        renderDayDetails(details, '#openDayModalBody', true);
                    }
                })
                .fail(function() {
                    const target = mode === 'approved' ? '#closeDayModalBody' : '#openDayModalBody';
                    $(target).html('<p class="text-center text-danger">Failed to load details.</p>');
                });
        }

        function renderDayDetails(details, target, editable) {
            if (!details.length) {
                $(target).html('<p class="text-center text-muted py-4">No details available.</p>');
                return;
            }

            let totalClaim = 0;
            let totalVerify = 0;
            let totalApprove = 0;
            let rows = '';

            details.forEach(function(item, index) {
                const expId = valueOf(item, ['ExpId', 'expId']);
                const employeeId = valueOf(item, ['EmployeeID', 'employeeId', 'EId'], lastPendingContext?.empid || '');
                const claimName = valueOf(item, ['ClaimName', 'claimName'], 'Expense');
                const claimAmt = amountOf(item, ['FilledTAmt', 'Claim_Amount', 'filledTAmt']);
                const verifyAmt = amountOf(item, ['VerifyTAmt', 'Verified_Amount', 'verifyTAmt']);
                const savedApproveAmt = amountOf(item, ['ApprTAmt', 'Approved_Amount', 'apprTAmt']);
                const approveAmt = savedApproveAmt > 0 ? savedApproveAmt : verifyAmt;
                const apprDate = valueOf(item, ['ApprDate', 'Approved_Date', 'apprDate']);
                const isApproved = validDate(apprDate);
                const remark = valueOf(item, ['ApprTRemark', 'apprRemark', 'ApprRemark']);
                const cgId = valueOf(item, ['cgId', 'CgId', 'CGId'], '0');

                totalClaim += claimAmt;
                totalVerify += verifyAmt;
                totalApprove += approveAmt;

                rows += `
                    <tr data-expid="${escapeAttr(expId)}">
                        <td class="text-center">${index + 1}</td>
                        <td>
                            <a href="javascript:void(0)" class="view-expense-detail fw-semibold"
                                data-expid="${escapeAttr(expId)}" data-empid="${escapeAttr(employeeId)}" data-claimname="${escapeAttr(claimName)}">
                                ${escapeHtml(claimName)}
                            </a>
                            <div class="small text-muted">Exp #${escapeHtml(expId)}</div>
                        </td>
                        <td class="text-center">${formatDate(valueOf(item, ['BillDate', 'billDate']))}</td>
                        <td class="text-end">Rs.${formatAmount(claimAmt)}</td>
                        <td class="remark-cell">${escapeHtml(valueOf(item, ['DateEntryRemark', 'EmpRemark', 'remark']))}</td>
                        <td class="text-end verify-amount">Rs.${formatAmount(verifyAmt)}</td>
                        <td class="remark-cell">${escapeHtml(valueOf(item, ['VerifyTRemark', 'verifyRemark']))}</td>
                        <td class="text-end">${formatAmount(claimAmt - verifyAmt)}</td>
                        <td>${editable ? `<input type="number" min="0" class="form-control form-control-sm appr-amt-input" value="${approveAmt}">` : 'Rs.' + formatAmount(savedApproveAmt)}</td>
                        <td>${editable ? `<input type="text" class="form-control form-control-sm appr-remark-input" value="${escapeAttr(remark)}">` : escapeHtml(remark)}</td>
                        <td class="text-center">${isApproved ? '<span class="badge bg-success-subtle text-success">Approved</span>' : '<span class="badge bg-warning-subtle text-warning">Pending</span>'}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)" class="badge bg-info-subtle text-info view-expense-detail"
                                data-expid="${escapeAttr(expId)}" data-empid="${escapeAttr(employeeId)}" data-claimname="${escapeAttr(claimName)}">
                                <i class="ri-eye-line"></i> View
                            </a>
                        </td>
                        <td class="text-center">
                            ${editable ? `<a href="javascript:void(0)" class="badge bg-primary-subtle text-primary approve-claim"
                                data-expid="${escapeAttr(expId)}" data-employeeid="${escapeAttr(employeeId)}"
                                data-month="${escapeAttr(valueOf(item, ['ClaimMonth'], lastPendingContext?.month || ''))}" data-cgid="${escapeAttr(cgId)}">
                                <i class="ri-check-line"></i> Approve
                            </a>` : ''}
                        </td>
                    </tr>
                `;
            });

            $(target).html(`
                <div class="table-responsive">
                    <table class="table table-bordered table-hover approver-modal-table">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>Sr</th><th>Claim</th><th>Bill Date</th><th>Claim Amt</th><th>Claim Remark</th>
                                <th>Verified Amt</th><th>Ver. Remark</th><th>Diff</th><th>Approver Amt</th>
                                <th>Appr. Remark</th><th>Status</th><th>View</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="3" class="text-end">Total</td>
                                <td class="text-end">Rs.${formatAmount(totalClaim)}</td>
                                <td></td>
                                <td class="text-end">Rs.${formatAmount(totalVerify)}</td>
                                <td></td>
                                <td class="text-end">Rs.${formatAmount(totalClaim - totalVerify)}</td>
                                <td class="text-end" id="modalApproverTotal">Rs.${formatAmount(totalApprove)}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `);
        }

        function loadApprovedList(memberFilter, monthFilter) {
            if (!requireRoute('approvedList')) return;
            $('#approvedPlaceholder').hide();
            $('#approvedTableWrapper').show();
            destroyDataTable('#approvedTable');
            $('#approvedTable tbody').html('<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...</td></tr>');

            $.getJSON(routes.approvedList, { member: memberFilter || '', month: monthFilter || '' })
                .done(function(response) {
                    renderApprovedTable(Array.isArray(response.Approved_list) ? response.Approved_list : []);
                })
                .fail(function() {
                    $('#approvedTable tbody').html('<tr><td colspan="9" class="text-center text-danger">Failed to load approved claims.</td></tr>');
                });
        }

        function renderApprovedTable(list) {
            const $tbody = $('#approvedTable tbody').empty();
            destroyDataTable('#approvedTable');

            if (!list.length) {
                $tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">No approved claims found.</td></tr>');
                return;
            }

            list.forEach(function(item, index) {
                const employeeId = valueOf(item, ['EmployeeID']);
                const month = valueOf(item, ['Month']);
                const yearId = valueOf(item, ['YearId']);
                const empName = employeeName(item);
                const courier = valueOf(item, ['RecevingDate', 'ReceivingDate', 'PostDate']);
                const claimant = amountOf(item, ['Claim_Amount']);
                const verifier = amountOf(item, ['Verified_Amount']);
                const approver = amountOf(item, ['Approved_Amount']);
                const finance = amountOf(item, ['Finance_Amount']);

                $tbody.append(`
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${escapeHtml(empName)}</td>
                        <td class="text-center">${monthName(month)}</td>
                        <td class="text-center">${dateBadge(courier)}</td>
                        <td class="text-end">Rs.${formatAmount(claimant)}<div class="small text-muted">${formatDate(valueOf(item, ['DateOfSubmit', 'Crdate']))}</div></td>
                        <td class="text-end">Rs.${formatAmount(verifier)}<div class="small text-muted">${formatDate(valueOf(item, ['Verified_Date']))}</div></td>
                        <td class="text-end">Rs.${formatAmount(approver)}<div class="small text-muted">${formatDate(valueOf(item, ['Approved_Date']))}</div></td>
                        <td class="text-end">Rs.${formatAmount(finance)}<div class="small text-muted">${formatDate(valueOf(item, ['Finance_Date']))}</div></td>
                        <td class="text-center">
                            <a href="javascript:void(0)" class="badge bg-info-subtle text-info view-approved-day"
                                data-empid="${escapeAttr(employeeId)}" data-month="${escapeAttr(month)}" data-yearid="${escapeAttr(yearId)}"
                                data-empname="${escapeAttr(empName)}" data-courier="${escapeAttr(courier)}"
                                data-claimant="${claimant}" data-verifier="${verifier}" data-approver="${approver}" data-finance="${finance}">
                                <i class="ri-eye-line"></i> View
                            </a>
                        </td>
                    </tr>
                `);
            });

            $('#approvedTable').DataTable({
                ordering: false,
                searching: true,
                paging: true,
                info: true,
                lengthChange: true,
                pageLength: 10
            });
        }

        $(document).on('click', '.view-approved-day', function(e) {
            e.preventDefault();
            const ctx = {
                empid: $(this).data('empid'),
                month: $(this).data('month'),
                yearid: $(this).data('yearid'),
                empname: $(this).data('empname'),
                courier: $(this).data('courier')
            };

            $('#approvedEmployeeName').text(ctx.empname || '');
            $('#approvedMonthName').text(monthName(ctx.month));
            $('#approvedCourier').text(validDate(ctx.courier) ? formatDate(ctx.courier) : 'Pending');
            $('#approvedClaimantAmt').text(formatAmount($(this).data('claimant')));
            $('#approvedVerifierAmt').text(formatAmount($(this).data('verifier')));
            $('#approvedApproverAmt').text(formatAmount($(this).data('approver')));
            $('#approvedFinanceAmt').text(formatAmount($(this).data('finance')));
            $('#closeDayModalBody').html(loaderHtml);
            $('#approvedDayModal').modal('show');
            fetchDayDetails(ctx.empid, ctx.month, ctx.yearid, 'approved');
        });

        $(document).on('click', '.approve-claim', function(e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            const expId = $(this).data('expid');
            const employeeId = $(this).data('employeeid');
            const month = $(this).data('month') || lastPendingContext?.month;
            const cgId = String($(this).data('cgid') || '0');
            const amount = numberValue($row.find('.appr-amt-input').val());
            const remark = $row.find('.appr-remark-input').val() || '';
            const verifiedAmt = numberValue($row.find('.verify-amount').text());

            if (amount <= 0) {
                Swal.fire('Approver Amount Required', 'Please enter a valid approver amount.', 'warning');
                return;
            }
            if (amount !== verifiedAmt && !remark.trim()) {
                Swal.fire('Remark Required', 'Since the approved amount differs from verified amount, please enter a remark.', 'warning');
                $row.find('.appr-remark-input').focus();
                return;
            }
            if (cgId === '7' && routes.approveHeadwise) {
                showApproveOrEditDialog(expId, employeeId, amount, remark, month, $row);
                return;
            }
            confirmAndApprove(expId, employeeId, amount, remark, month, $row, verifiedAmt);
        });

        function confirmAndApprove(expId, employeeId, amount, remark, month, $row, verifiedAmt) {
            if (amount === verifiedAmt) {
                approveClaim(expId, employeeId, amount, remark, month, $row);
                return;
            }

            Swal.fire({
                title: 'Confirm Approval',
                html: `<div class="text-start">
                    <p><strong>Expense ID:</strong> ${escapeHtml(expId)}</p>
                    <p><strong>Verified Amount:</strong> Rs.${formatAmount(verifiedAmt)}</p>
                    <p><strong>Approver Amount:</strong> Rs.${formatAmount(amount)}</p>
                    ${remark ? `<p><strong>Remark:</strong> ${escapeHtml(remark)}</p>` : ''}
                </div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Approve'
            }).then(function(result) {
                if (result.isConfirmed) {
                    approveClaim(expId, employeeId, amount, remark, month, $row);
                }
            });
        }

        function showApproveOrEditDialog(expId, employeeId, amount, remark, month, $row) {
            Swal.fire({
                title: 'Choose an Action',
                html: `<div class="d-flex flex-column gap-3 mt-2">
                    <button id="swalApproveAmt" class="btn btn-outline-primary w-100 py-3"><i class="ri-check-circle-line me-2"></i> Approve Amount</button>
                    <button id="swalEditHeadwise" class="btn btn-outline-secondary w-100 py-3"><i class="ri-edit-line me-2"></i> Edit Head Wise Amount</button>
                </div>`,
                showConfirmButton: false,
                showCancelButton: true,
                didOpen: function() {
                    $('#swalApproveAmt').on('click', function() {
                        Swal.close();
                        approveClaim(expId, employeeId, amount, remark, month, $row);
                    });
                    $('#swalEditHeadwise').on('click', function() {
                        Swal.close();
                        loadHeadWiseDetail(expId, amount, remark, month, $row);
                    });
                }
            });
        }

        function approveClaim(expId, employeeId, amount, remark, month, $row) {
            if (!requireRoute('approveClaim')) return;
            const $button = $row.find('.approve-claim');
            const originalHtml = $button.html();
            $button.html('<i class="ri-loader-4-line rotating"></i> Processing...').addClass('disabled');

            $.ajax({
                url: routes.approveClaim,
                method: 'POST',
                dataType: 'json',
                data: { _token: csrfToken, exp_id: expId, employee_id: employeeId, amount: amount, remark: remark, month: month }
            }).done(function(response) {
                if (successCode(response)) {
                    markRowApproved($row, amount, remark, response.TotalApprovedAmt);
                    Swal.fire({ icon: 'success', title: 'Approved', timer: 1400, showConfirmButton: false });
                } else {
                    Swal.fire('Approval Failed', response.msg || 'Failed to approve the claim.', 'error');
                    $button.html(originalHtml).removeClass('disabled');
                }
            }).fail(function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.msg || 'An error occurred while approving the claim.', 'error');
                $button.html(originalHtml).removeClass('disabled');
            });
        }

        function loadHeadWiseDetail(expId, amount, remark, month, $row) {
            if (!requireRoute('expenseDetail')) return;
            Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

            $.getJSON(routes.expenseDetail, { exp_id: expId, emp_id: $row.find('.view-expense-detail').data('empid') || lastPendingContext?.empid })
                .done(function(response) {
                    Swal.close();
                    const expenses = response.expenses_summary?.expenses || response.details?.expenses || response.expenses || [];
                    const fillData = response.expenseFillData || response.fillData || {};
                    if (!Array.isArray(expenses) || !expenses.length) {
                        Swal.fire('No Data', 'No head-wise expense data found.', 'info');
                        return;
                    }
                    showHeadWiseModal(expId, expenses, fillData, remark, month, $row);
                })
                .fail(function() {
                    Swal.close();
                    Swal.fire('Error', 'Failed to load expense details.', 'error');
                });
        }

        function showHeadWiseModal(expId, expenses, fillData, remark, month, $row) {
            let rows = '';
            expenses.forEach(function(exp) {
                const label = valueOf(exp, ['label', 'Label', 'title'], 'Head');
                const labelValue = valueOf(exp, ['label_value', 'labelValue', 'value']);
                const claimAmt = numberValue(valueOf(exp, ['amount', 'claim_amount'], 0));
                const verifyAmt = numberValue(valueOf(exp, ['verifier_amount', 'verify_amount'], claimAmt));
                const existingAmt = fillData['app_' + labelValue] || verifyAmt || 0;
                const existingRemark = fillData['app_remark_' + labelValue] || '';

                rows += `<div class="border rounded p-2 mb-2">
                    <div class="fw-semibold text-primary">${escapeHtml(label)}</div>
                    <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
                        <span>Claim: <strong>Rs.${formatAmount(claimAmt)}</strong></span>
                        <span>Verified: <strong>Rs.${formatAmount(verifyAmt)}</strong></span>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="number" class="form-control form-control-sm hw-amt" data-label="${escapeAttr(labelValue)}" data-max="${claimAmt}" value="${escapeAttr(existingAmt)}" style="width:120px;">
                        <input type="text" class="form-control form-control-sm hw-remark" data-label="${escapeAttr(labelValue)}" value="${escapeAttr(existingRemark)}" placeholder="Remark" aria-label="Remark">
                    </div>
                </div>`;
            });

            $('#headWiseModal').remove();
            $('body').append(`<div class="modal fade" id="headWiseModal" data-bs-backdrop="static" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Head-Wise Amount Approve</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">${rows}</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="submitHeadWise"><i class="ri-check-line"></i> Approve</button>
                        </div>
                    </div>
                </div>
            </div>`);

            $('#headWiseModal').data('expid', expId).data('sourceRow', $row).modal('show');
        }

        $(document).on('input', '#headWiseModal .hw-amt', function() {
            const max = numberValue($(this).data('max'));
            const value = numberValue($(this).val());
            if (max > 0 && value > max) {
                $(this).val(max);
                Swal.fire({ icon: 'warning', title: 'Max allowed: Rs.' + formatAmount(max), timer: 1200, showConfirmButton: false });
            }
        });

        $(document).on('click', '#submitHeadWise', function() {
            if (!requireRoute('approveHeadwise')) return;
            const $button = $(this);
            const $sourceRow = $('#headWiseModal').data('sourceRow') || $();
            const expId = $('#headWiseModal').data('expid') || $sourceRow.find('.approve-claim').data('expid');
            const month = $sourceRow.find('.approve-claim').data('month') || lastPendingContext?.month;
            const remark = $sourceRow.find('.appr-remark-input').val() || '';
            const headAmounts = [];
            let total = 0;

            $('#headWiseModal .hw-amt').each(function() {
                const labelValue = $(this).data('label');
                const amount = numberValue($(this).val());
                const headRemark = $('#headWiseModal .hw-remark[data-label="' + labelValue + '"]').val() || '';
                headAmounts.push({ label_value: String(labelValue), app_amount: String(amount), app_remark: headRemark });
                total += amount;
            });

            $button.html('<i class="ri-loader-4-line rotating"></i> Approving...').prop('disabled', true);
            $.ajax({
                url: routes.approveHeadwise,
                method: 'POST',
                dataType: 'json',
                data: { _token: csrfToken, exp_id: expId, month: month, remark: remark, head_amounts: headAmounts }
            }).done(function(response) {
                if (successCode(response)) {
                    markRowApproved($sourceRow, total, remark, response.TotalApprovedAmt);
                    $('#headWiseModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Approved', timer: 1400, showConfirmButton: false });
                } else {
                    Swal.fire('Approval Failed', response.msg || 'Failed to approve head-wise amount.', 'error');
                    $button.html('<i class="ri-check-line"></i> Approve').prop('disabled', false);
                }
            }).fail(function() {
                Swal.fire('Error', 'An error occurred while approving head-wise amount.', 'error');
                $button.html('<i class="ri-check-line"></i> Approve').prop('disabled', false);
            });
        });

        $(document).on('click', '.view-expense-detail', function(e) {
            e.preventDefault();
            if (!requireRoute('expenseDetail')) return;
            const expId = $(this).data('expid');
            const empId = $(this).data('empid');
            const claimName = $(this).data('claimname') || 'Expense Detail';

            $('#expDetailModal').remove();
            $('body').append(`<div class="modal fade" id="expDetailModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="ri-file-list-3-line me-1"></i> ${escapeHtml(claimName)} - Exp #${escapeHtml(expId)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="expDetailBody">${loaderHtml}</div>
                    </div>
                </div>
            </div>`);
            $('#expDetailModal').modal('show');

            $.getJSON(routes.expenseDetail, { exp_id: expId, emp_id: empId })
                .done(renderExpenseDetail)
                .fail(function() {
                    $('#expDetailBody').html('<p class="text-center text-danger">Failed to load expense detail.</p>');
                });
        });

        $(document).on('click', '.submit-claim', function(e) {
            e.preventDefault();
            if (!requireRoute('submitClaim')) return;
            const $button = $(this);
            const empId = $button.data('empid');
            const month = $button.data('month');
            const yearId = $button.data('yearid');

            Swal.fire({
                title: 'Submit Month Claim',
                html: '<p>If there are no changes in the approved amount, kindly click on submit button directly.</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Submit'
            }).then(function(result) {
                if (result.isConfirmed) {
                    submitMonthClaim(empId, month, yearId, $button);
                }
            });
        });

        function submitMonthClaim(empId, month, yearId, $button) {
            const originalHtml = $button.html();
            $button.html('<i class="ri-loader-4-line rotating"></i> Submitting...').addClass('disabled');

            $.ajax({
                url: routes.submitClaim,
                method: 'POST',
                dataType: 'json',
                data: { _token: csrfToken, emp_id: empId, month: month, year_id: yearId }
            }).done(function(response) {
                if (successCode(response)) {
                    Swal.fire({ icon: 'success', title: 'Submitted Successfully', timer: 1800, showConfirmButton: false });
                    loadPendingList();
                } else {
                    Swal.fire('Submission Failed', response.msg || 'Failed to submit the claim.', 'error');
                    $button.html(originalHtml).removeClass('disabled');
                }
            }).fail(function(xhr) {
                Swal.fire('Submission Error', xhr.responseJSON?.msg || 'An error occurred while submitting.', 'error');
                $button.html(originalHtml).removeClass('disabled');
            });
        }

        function loadTeamMembersForFilter() {
            if (!routes.teamMembers) return;
            $.getJSON(routes.teamMembers).done(function(response) {
                const members = Array.isArray(response.Team_list) ? response.Team_list : [];
                const $select = $('#filterApprovedMember');
                members.forEach(function(member) {
                    const id = valueOf(member, ['EmployeeID']);
                    const label = [valueOf(member, ['EmpCode']), valueOf(member, ['FullName', 'Ename'])].filter(Boolean).join(' - ');
                    $select.append(`<option value="${escapeAttr(id)}">${escapeHtml(label || id)}</option>`);
                });
            });
        }

        function loadAllTeamExpenses() {
            if (!requireRoute('teamExpenses')) return;
            $('#teamExpenseLoader').show();
            $('#teamExpenseTableContainer, #noDataMessage').hide();
            $.getJSON(routes.teamExpenses).done(function(response) {
                teamExpenseRows = normalizeTeamExpenseRows(response);
                populateClaimTypeFilter(teamExpenseRows);
                renderTeamExpenseTable(teamExpenseRows);
            }).fail(function() {
                $('#teamExpenseLoader').hide();
                $('#noDataMessage').show().find('p').text('Failed to load team expense data.');
            });
        }

        function normalizeTeamExpenseRows(response) {
            const expenseType = response.data?.ExpenseType || response.ExpenseType || response.data?.data?.ExpenseType || {};
            const claimTypes = Array.isArray(expenseType.ClaimTypes) ? expenseType.ClaimTypes : [];
            return claimTypes.map(function(item) {
                return {
                    id: valueOf(item, ['ClaimId', 'claim_id', 'id'], valueOf(item, ['ClaimName'])),
                    name: valueOf(item, ['ClaimName', 'claim_name'], 'Claim'),
                    total: numberValue(valueOf(item, ['MonthTotal', 'Total', 'total'], 0)),
                    months: item.MonthlyTotals || item.months || {}
                };
            }).filter(function(row) {
                return row.total > 0 || fiscalMonths.some(function(m) { return numberValue(row.months[m] || row.months[String(m)]) > 0; });
            });
        }

        function populateClaimTypeFilter(rows) {
            const $select = $('#filterClaimType').empty().append('<option value="">All Claim Types</option>');
            rows.forEach(function(row) {
                $select.append(`<option value="${escapeAttr(row.id)}">${escapeHtml(row.name)}</option>`);
            });
        }

        function filterTeamExpenseRows() {
            const claimType = $('#filterClaimType').val();
            if (!claimType) return teamExpenseRows;
            return teamExpenseRows.filter(function(row) { return String(row.id) === String(claimType); });
        }

        function renderTeamExpenseTable(rows) {
            $('#teamExpenseLoader').hide();
            if (!rows.length) {
                $('#teamExpenseTableContainer').hide();
                $('#noDataMessage').show();
                return;
            }

            const totals = {};
            let overall = 0;
            let body = '';
            rows.forEach(function(row) {
                let rowTotal = 0;
                let monthCells = '';
                fiscalMonths.forEach(function(month) {
                    const amount = numberValue(row.months[month] || row.months[String(month)] || 0);
                    totals[month] = (totals[month] || 0) + amount;
                    rowTotal += amount;
                    monthCells += `<td class="text-end">Rs.${formatAmount(amount)}</td>`;
                });
                overall += rowTotal;
                body += `<tr><td class="fw-semibold">${escapeHtml(row.name)}</td>${monthCells}<td class="text-end fw-semibold">Rs.${formatAmount(rowTotal || row.total)}</td></tr>`;
            });

            let footer = '<td class="text-end">Grand Total</td>';
            fiscalMonths.forEach(function(month) {
                footer += `<td class="text-end">Rs.${formatAmount(totals[month] || 0)}</td>`;
            });
            footer += `<td class="text-end">Rs.${formatAmount(overall)}</td>`;

            $('#teamExpenseTableBody').html(body);
            $('#teamExpenseGrandTotal').html(footer);
            $('#noDataMessage').hide();
            $('#teamExpenseTableContainer').show();
        }

        function renderExpenseDetail(response) {
            let detail = response.details || response.Detail || response.data || response;
            if (Array.isArray(detail)) detail = detail[0] || {};

            const images = [];
            const pdfs = [];
            ['Img_1', 'Img_2', 'Img_3', 'Image1', 'Image2', 'Image3', 'opening_filepath', 'closing_filepath', 'OpeningFilePath', 'ClosingFilePath'].forEach(function(key) {
                const url = valueOf(detail, [key]);
                if (url) images.push(url);
            });
            ['pdf_1', 'pdf_2', 'Pdf1', 'Pdf2'].forEach(function(key) {
                const url = valueOf(detail, [key]);
                if (url) pdfs.push(url);
            });

            let media = '';
            if (images.length) {
                media += '<div class="row g-2 mb-3">';
                images.forEach(function(url, index) {
                    media += `<div class="col-md-4 col-6">
                        <a href="${escapeAttr(url)}" target="_blank">
                            <img alt="Bill or document image" src="${escapeAttr(url)}" class="img-fluid rounded border" style="height:150px;width:100%;object-fit:cover;" onerror="this.closest('div').style.display='none'">
                        </a>
                        <div class="small text-muted text-center mt-1">Image ${index + 1}</div>
                    </div>`;
                });
                media += '</div>';
            }
            if (pdfs.length) {
                pdfs.forEach(function(url, index) {
                    media += `<div class="mb-2"><a href="${escapeAttr(url)}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="ri-file-pdf-line"></i> Open PDF ${index + 1}</a></div>`;
                });
            }
            if (!media) {
                media = '<div class="text-center text-muted py-3 border rounded mb-3"><i class="ri-image-line fs-1"></i><p class="mb-0">No attachments</p></div>';
            }

            const claim = amountOf(detail, ['FilledTAmt', 'Claim_Amount']);
            const verified = amountOf(detail, ['VerifyTAmt', 'Verified_Amount']);
            const approved = amountOf(detail, ['ApprTAmt', 'Approved_Amount']);
            const rows = [
                ['Claim Type', valueOf(detail, ['ClaimName'], '-')],
                ['Exp ID', valueOf(detail, ['ExpId'], '-')],
                ['Bill Date', formatDate(valueOf(detail, ['BillDate']))],
                ['Upload Date', formatDate(valueOf(detail, ['CrDate']))],
                ['Remark', valueOf(detail, ['DateEntryRemark', 'EmpRemark', 'Remark'], '-')],
                ['Claimed Amt', 'Rs.' + formatAmount(claim)],
                ['Verified Amt', 'Rs.' + formatAmount(verified)],
                ['Diff Amt', 'Rs.' + formatAmount(claim - verified)],
                ['Ver. Remark', valueOf(detail, ['VerifyTRemark'], '-')],
                ['Approver Amt', 'Rs.' + formatAmount(approved)],
                ['Appr. Remark', valueOf(detail, ['ApprTRemark'], '-')],
                ['Status', validDate(valueOf(detail, ['ApprDate'])) ? '<span class="badge bg-success">Approved</span>' : '<span class="badge bg-warning text-dark">Pending</span>']
            ];

            let table = '<table class="table table-sm table-bordered mb-0">';
            rows.forEach(function(row) {
                if (!row[1] || row[1] === '-') return;
                table += `<tr><th class="bg-light" style="width:38%">${escapeHtml(row[0])}</th><td>${row[1]}</td></tr>`;
            });
            table += '</table>';

            $('#expDetailBody').html(media + table);
        }

        function markRowApproved($row, amount, remark, totalApprovedAmt) {
            $row.find('.appr-amt-input').val(amount);
            $row.find('.appr-remark-input').val(remark);
            $row.find('td').eq(10).html('<span class="badge bg-success-subtle text-success">Approved</span>');
            $row.find('.approve-claim').html('<i class="ri-checkbox-circle-line"></i> Approved').removeClass('disabled bg-primary-subtle text-primary').addClass('bg-success-subtle text-success');
            updatePendingApproverTotal(totalApprovedAmt);
        }

        function updatePendingApproverTotal(totalApprovedAmt) {
            if (totalApprovedAmt !== undefined && totalApprovedAmt !== null && totalApprovedAmt !== '') {
                $('#pendingApproverAmt').text(formatAmount(totalApprovedAmt));
                $('#modalApproverTotal').text('Rs.' + formatAmount(totalApprovedAmt));
            } else {
                let total = 0;
                $('#openDayModalBody .appr-amt-input').each(function() { total += numberValue($(this).val()); });
                $('#pendingApproverAmt, #modalApproverTotal').text(function(_, oldText) {
                    return oldText.indexOf('Rs.') === 0 ? 'Rs.' + formatAmount(total) : formatAmount(total);
                });
            }
        }

        function destroyDataTable(selector) {
            if ($.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
                $(selector).DataTable().destroy();
            }
        }

        function valueOf(obj, keys, fallback = '') {
            for (const key of keys) {
                if (obj && obj[key] !== undefined && obj[key] !== null && obj[key] !== '') return obj[key];
            }
            return fallback;
        }

        function amountOf(obj, keys) {
            return numberValue(valueOf(obj, keys, 0));
        }

        function employeeName(item) {
            const empName = item.EmpName;
            if (empName && typeof empName === 'object') return empName.Ename || empName.ename || 'N/A';
            return empName || item.Ename || item.employee_name || item.FullName || 'N/A';
        }

        function numberValue(value) {
            if (value === undefined || value === null) return 0;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            const parsed = parseFloat(cleaned);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function formatAmount(value) {
            return numberValue(value).toLocaleString('en-IN', { maximumFractionDigits: 2 });
        }

        function validDate(value) {
            return value && value !== '0000-00-00' && value !== '0000-00-00 00:00:00' && value !== 'null';
        }

        function formatDate(value) {
            if (!validDate(value)) return '-';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString('en-GB');
        }

        function dateBadge(value) {
            return validDate(value) ? formatDate(value) : '<span class="badge bg-warning-subtle text-warning">Pending</span>';
        }

        function monthName(month) {
            const index = parseInt(month, 10) - 1;
            return monthNames[index] || month || '-';
        }

        function successCode(response) {
            return response && (String(response.Code) === '300' || String(response.Code) === '200' || response.success === true);
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(match) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[match];
            });
        }

        function escapeAttr(value) {
            return escapeHtml(value).replace(/`/g, '&#096;');
        }
    });
</script>
@endpush
