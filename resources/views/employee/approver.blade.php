{{-- resources/views/admin/approver/index.blade.php --}}

@extends('layouts.app')

@section('content')

    @php

        $tabs = [];

        /*
        |--------------------------------------------------------------------------
        | Flutter Logic
        |--------------------------------------------------------------------------
        */

        if (session('reporting_vess') == 'Y') {

            $tabs[] = [
                'key' => 'vnr',
                'title' => 'VNR',
                'icon' => 'ri-file-list-3-line'
            ];

            $tabs[] = [
                'key' => 'vvnr',
                'title' => 'VVNR',
                'icon' => 'ri-file-copy-2-line'
            ];

        } else {

            $tabs[] = [
                'key' => 'vnr',
                'title' => 'VNR',
                'icon' => 'ri-file-list-3-line'
            ];
        }

        if (session('reporting_nursery') == 'Y') {

            $middle = floor(count($tabs) / 2);

            array_splice($tabs, $middle, 0, [
                [
                    'key' => 'nursery',
                    'title' => 'NURSERY',
                    'icon' => 'ri-plant-line'
                ]
            ]);
        }

    @endphp


    <div class="page-content">

        <div class="container-fluid">

            <div class="row">

                <div class="col-xl-12">

                    <div class="card card-height-100">

                        {{-- ===================================================== --}}
                        {{-- HEADER --}}
                        {{-- ===================================================== --}}

                        <div class="card-header align-items-center d-flex flex-wrap">

                            <h4 class="card-title mb-0 flex-grow-1">

                              
                            {{-- ===================================================== --}}
                            {{-- TABS --}}
                            {{-- ===================================================== --}}

                            @if(count($tabs))

                                <div class="approver-tabs-wrapper">

                                    <ul class="nav nav-pills custom-approver-tabs" id="approverTabs" role="tablist">

                                        @foreach($tabs as $index => $tab)

                                            <li class="nav-item">

                                                <button class="nav-link {{ $index == 0 ? 'active' : '' }}" data-bs-toggle="pill"
                                                    data-bs-target="#{{ $tab['key'] }}" type="button">

                                                    <i class="{{ $tab['icon'] }}"></i>

                                                    {{ $tab['title'] }}

                                                </button>

                                            </li>

                                        @endforeach

                                    </ul>

                                </div>

                            @endif


                            </h4>

                            {{-- STATUS BUTTONS --}}

                            <div class="status-buttons">

                                <a href="javascript:void(0)" class="status-btn pending active">

                                    <i class="ri-hourglass-line"></i>

                                    Pending

                                </a>

                                <a href="javascript:void(0)" class="status-btn approved">

                                    <i class="ri-checkbox-circle-line"></i>

                                    Approved

                                </a>

                                <a href="javascript:void(0)" class="status-btn team">

                                    <i class="ri-team-line"></i>

                                    Team Expense

                                </a>

                                <a href="javascript:void(0)" class="status-btn excess-running">

                                    <i class="ri-speed-line"></i>

                                    Excess Running

                                </a>

                            </div>

                        </div>


                        {{-- ===================================================== --}}
                        {{-- BODY --}}
                        {{-- ===================================================== --}}

                        <div class="card-body">



                            {{-- ===================================================== --}}
                            {{-- TAB CONTENT --}}
                            {{-- ===================================================== --}}

                            <div class="tab-content">

                                @foreach($tabs as $index => $tab)

                                    <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="{{ $tab['key'] }}">


                                        {{-- ===================================================== --}}
                                        {{-- FILTER CARD --}}
                                        {{-- ===================================================== --}}

                                        <div class="row filter-row" data-tab="{{ $tab['key'] }}">

                                            <div class="col-md-4 mb-3">

                                                <label class="form-label fw-bold">

                                                    Employee

                                                </label>

                                                <select class="form-select filter-employee">

                                                    <option value="">All Employees</option>

                                                </select>

                                            </div>

                                            <div class="col-md-4 mb-3">

                                                <label class="form-label fw-bold">

                                                    Month

                                                </label>

                                                <select class="form-select filter-month">

                                                    <option value="">All</option>
                                                    <option value="1">January</option>
                                                    <option value="2">February</option>
                                                    <option value="3">March</option>
                                                    <option value="4">April</option>
                                                    <option value="5">May</option>
                                                    <option value="6">June</option>
                                                    <option value="7">July</option>
                                                    <option value="8">August</option>
                                                    <option value="9">September</option>
                                                    <option value="10">October</option>
                                                    <option value="11">November</option>
                                                    <option value="12">December</option>

                                                </select>

                                            </div>

                                            <div class="col-md-4 mb-3 d-flex align-items-end">

                                                <button class="btn btn-primary w-100 btn-filter-search">

                                                    <i class="ri-search-line"></i>

                                                    Search

                                                </button>

                                            </div>

                                        </div>



                                        {{-- ===================================================== --}}
                                        {{-- TABLE --}}
                                        {{-- ===================================================== --}}

                                        <div class="table-responsive">

                                            <table class="table table-bordered table-hover align-middle approver-tab-table">

                                                <thead>
                                                    {{-- Pending columns --}}
                                                    <tr class="pending-header">
                                                        <th class="text-center">#</th>
                                                        <th>Claimant Name</th>
                                                        <th class="text-center">Month</th>
                                                        <th class="text-center">Claims</th>
                                                        <th class="text-center">Submitted</th>
                                                        <th class="text-center">Verified</th>
                                                        <th class="text-center">Courier</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                    {{-- Approved columns (hidden by default) --}}
                                                    <tr class="approved-header" style="display:none;">
                                                        <th class="text-center">#</th>
                                                        <th>Claimant Name</th>
                                                        <th class="text-center">Month</th>
                                                        <th class="text-center">Submitted</th>
                                                        <th class="text-center">Verified</th>
                                                        <th class="text-center">Approved</th>
                                                        <th class="text-center">Finance</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                    {{-- Team expense columns (hidden by default) --}}
                                                    <tr class="team-header" style="display:none;">
                                                        <th class="text-center">#</th>
                                                        <th>Claim Type</th>
                                                        <th class="text-center">Total Amount (Year)</th>
                                                        <th class="text-center">Members</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...
                                                        </td>
                                                    </tr>
                                                </tbody>

                                            </table>

                                        </div>

                                        {{-- expand rows are injected dynamically by JS --}}

                                    </div>

                                @endforeach

                            </div>

                            {{-- ===================================================== --}}
                            {{-- EXCESS RUNNING SECTION --}}
                            {{-- ===================================================== --}}
                            <div id="excessRunningSection" style="display:none;">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="fw-bold mb-0" style="color:#15648C;">
                                        <i class="ri-speed-line me-1"></i> Excess Running Approval
                                        <small class="text-danger ms-2">(Reading beyond 1200 KM)</small>
                                    </h5>
                                    <button class="btn btn-sm btn-soft-primary" id="refreshExcessRunning">
                                        <i class="ri-refresh-line"></i> Refresh
                                    </button>
                                </div>
                                <div id="excessRunningContainer">
                                    <div class="text-center py-4 text-muted">Click "Excess Running" to load data</div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>




    {{-- ===================================================== --}}
    {{-- VIEW DETAILS MODAL (PDF-style read-only) --}}
    {{-- ===================================================== --}}

    <div class="modal fade" id="expenseModal" tabindex="-1">

        <div class="modal-dialog modal-xl">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">
                        <i class="ri-file-text-line me-1"></i> Expense Details
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body" id="expenseModalBody">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="btnPrintExpense">
                        <i class="ri-printer-line me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>

            </div>

        </div>

    </div>


    {{-- ===================================================== --}}
    {{-- DENY MODAL --}}
    {{-- ===================================================== --}}

    <div class="modal fade" id="denyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="ri-close-circle-line me-1"></i> Deny Claim</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold">Remark <span class="text-danger">*</span></label>
                    <textarea id="denyRemarkInput" class="form-control" rows="3" placeholder="Enter reason for denial..." aria-label="Enter reason for denial"></textarea>
                    <div id="denyRemarkError" class="text-danger small mt-1" style="display:none;">Remark is required.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmDeny">
                        <i class="ri-close-circle-line"></i> Confirm Deny
                    </button>
                </div>
            </div>
        </div>
    </div>




    {{-- ===================================================== --}}
    {{-- STYLE --}}
    {{-- ===================================================== --}}

    <style>
        .custom-approver-tabs {

            background: #eff1ed;
            padding: 6px;
            border-radius: 40px;
            display: inline-flex;
            gap: 10px;
        }

        .custom-approver-tabs .nav-link {

            border-radius: 16px;
            padding: 5px 16px;
            font-weight: 400;
            border: none;
            color: #000;
            transition: 0.3s;
        }

        .custom-approver-tabs .nav-link.active {

            background: #dde6ff;
            color: #1e3a8a;
        }

        .custom-approver-tabs .nav-link:hover {

            background: #d6dbc7;
        }

        .custom-approver-tabs .nav-link i {

            margin-right: 6px;
        }

        .status-buttons {

            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-btn {

            padding: 8px 18px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            border: 1px solid transparent;
        }

        .status-btn.pending {

            border-color: #ffc107;
            color: #ffc107;
        }

        .status-btn.pending.active,
        .status-btn.pending:hover {

            background: #fdefd2;
            color: #7a4500;
        }

        .status-btn.approved {

            border-color: #198754;
            color: #198754;
        }

        .status-btn.approved.active,
        .status-btn.approved:hover {

            background: #d7f3e6;
            color: #065f46;
        }

        .status-btn.team {

            border-color: #6f42c1;
            color: #6f42c1;
        }

        .status-btn.team.active,
        .status-btn.team:hover {

            background: #6f42c1;
            color: white;
        }

        .table th {

            background: #dde6ff !important;
            color: #1e3a8a !important;
            font-size: 13px;
            vertical-align: middle;
        }

        .table td {

            font-size: 13px;
            vertical-align: middle;
        }

        .card {

            border-radius: 14px;
            overflow: hidden;
        }

        .btn {

            border-radius: 8px;
        }

        .modal-content {
            border-radius: 16px;
        }

        /* ── Inline expand row ── */
        .expand-row td {
            padding: 0 !important;
            background: #f0f4ff;
        }

        .expand-row .expand-inner {
            padding: 12px 16px;
            border-top: 2px solid #15648C;
        }

        .expand-row .expand-inner .expand-title {
            font-size: 13px;
            font-weight: 600;
            color: #15648C;
            margin-bottom: 8px;
        }

        .expense-lines-table th {
            background: #dde6ff !important;
            color: #1e3a8a !important;
            font-size: 12px;
        }

        .expense-lines-table td {
            font-size: 12px;
            vertical-align: middle;
        }

        .expense-lines-table input.appr-amt {
            width: 100px;
            font-size: 12px;
            padding: 3px 6px;
        }

        .expense-lines-table input.appr-remark {
            width: 160px;
            font-size: 12px;
            padding: 3px 6px;
        }

        .btn-approve-line {
            font-size: 11px;
            padding: 2px 8px;
        }

        .line-approved {
            background-color: #d1e7dd !important;
        }

        .btn-approve-list.active-expand {
            background: #0a3d62 !important;
            border-color: #0a3d62 !important;
        }

        .btn-expand-approved.active-expand {
            background: #495057 !important;
            border-color: #495057 !important;
            color: white !important;
        }

        .expand-row-approved td {
            background: #f0fff4 !important;
        }

        .expand-row-approved .expand-inner {
            border-top-color: #198754;
        }

        .expand-row-approved .expand-title {
            color: #198754 !important;
        }

        /* ── Claim detail inline row ── */
        .claim-detail-row td {
            background: #fffdf0 !important;
        }

        .claim-detail-inner {
            border-top: 2px solid #ffc107;
        }

        .claim-detail-inner .form-control,
        .claim-detail-inner select,
        .claim-detail-inner .select2-container {
            pointer-events: none;
            background: #f8f9fa;
        }

        .claim-detail-inner .btn-primary,
        .claim-detail-inner #sendMessage {
            display: none;
        }

        .claim-detail-inner a {
            pointer-events: auto;
        }
    </style>


@endsection

@push('scripts')

    <script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/eligibility_panel.js') }}"></script>

    <script>

        let currentTab       = 'vnr';
        let currentStatus    = '{{ $status ?? "pending" }}';
        let currentModalData = {};
        let currentLineData  = {};   // active approve-list row
        let denyTarget       = null;
        let $activeExpandRow = null; // the currently open <tr class="expand-row">
        let teamMembersCache = {};   // cache per tab: { vnr: [...], vvnr: [...], nursery: [...] }
        let currentMember    = '';   // selected employee filter
        let currentMonth     = '';   // selected month filter



        // =====================================================
        // DOCUMENT READY
        // =====================================================

        $(document).ready(function () {

            // Activate the correct status button based on URL param
            if (currentStatus !== 'pending') {
                $('.status-btn').removeClass('active');
                $('.status-btn.' + currentStatus).addClass('active');
            }

            loadTabData(currentTab, currentStatus);
            loadTeamMembers(currentTab);
            toggleFilterVisibility(currentStatus);

            // ── Tab click ──────────────────────────────────
            $('.custom-approver-tabs .nav-link').on('click', function () {
                currentTab = $(this).data('bs-target').replace('#', '');
                currentMember = '';
                currentMonth  = '';
                teamSelectedMember = '';
                teamSelectedClaim  = '';
                resetFilters();
                closeExpenseList();
                loadTabData(currentTab, currentStatus);
                loadTeamMembers(currentTab);
                toggleFilterVisibility(currentStatus);
            });

            // ── Status buttons ─────────────────────────────
            $('.status-btn').on('click', function () {
                $('.status-btn').removeClass('active');
                $(this).addClass('active');
                currentStatus = $(this).hasClass('pending')  ? 'pending'
                              : $(this).hasClass('approved') ? 'approved'
                              : $(this).hasClass('excess-running') ? 'excess-running'
                              : 'team';
                currentMember = '';
                currentMonth  = '';
                resetFilters();
                closeExpenseList();

                // Toggle excess running section
                if (currentStatus === 'excess-running') {
                    $('.tab-content').hide();
                    $('.filter-row').hide();
                    $('#excessRunningSection').show();
                    loadExcessRunningData();
                } else {
                    $('#excessRunningSection').hide();
                    $('.tab-content').show();
                    toggleFilterVisibility(currentStatus);
                    loadTabData(currentTab, currentStatus);
                }
            });

            // ── Filter search button ───────────────────────
            $(document).on('click', '.btn-filter-search', function () {
                const $tabPane = $(this).closest('.tab-pane');
                currentMember = $tabPane.find('.filter-employee').val() || '';
                currentMonth  = $tabPane.find('.filter-month').val()    || '';
                closeExpenseList();
                loadTabData(currentTab, currentStatus, currentMember, currentMonth);
            });

            // ── View details button ────────────────────────
            $(document).on('click', '.btn-view-details', function () {
                const empId  = $(this).data('empid');
                const month  = $(this).data('month');
                const yearId = $(this).data('yearid');
                currentModalData = { empid: empId, month: month, yearid: yearId };
                $('#expenseModalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>');
                $('#expenseModal').modal('show');
                loadExpenseDetails(empId, month, yearId, currentTab);
            });

            // ── Approve list button ────────────────────────
            $(document).on('click', '.btn-approve-list', function () {
                const $btn    = $(this);
                const empId   = $btn.data('empid');
                const month   = $btn.data('month');
                const yearId  = $btn.data('yearid');
                const empName = $btn.data('empname');
                currentLineData = { empid: empId, month: month, yearid: yearId, empname: empName };
                openExpenseList($btn, empId, month, yearId, empName);
            });

            // ── Approved expand (read-only) button ─────────
            $(document).on('click', '.btn-expand-approved', function () {
                const $btn    = $(this);
                const empId   = $btn.data('empid');
                const month   = $btn.data('month');
                const yearId  = $btn.data('yearid');
                const empName = $btn.data('empname');
                openApprovedExpand($btn, empId, month, yearId, empName);
            });

            // ── Close expense list ─────────────────────────
            $(document).on('click', '.btn-close-expense-list', function () {
                closeExpenseList();
            });

            // ── Close inline claim detail ──────────────────
            $(document).on('click', '.btn-close-claim-detail', function () {
                $(this).closest('.claim-detail-row').hide();
            });

            // ── Team detail month filter ───────────────────
            $(document).on('change', '.team-detail-month-filter', function () {
                const claimId   = $(this).data('claimid');
                const month     = $(this).val();
                const $inner    = $(this).closest('.claim-detail-inner');
                const detailRowId = $(this).closest('.claim-detail-row').attr('id');
                loadTeamClaimDetails(claimId, detailRowId, $inner, month);
            });

            // ── Team filter apply button ───────────────────
            $(document).on('click', '.team-filter-apply', function () {
                teamSelectedMember = $('.team-filter-member').val() || '';
                teamSelectedClaim  = $('.team-filter-claim').val() || '';
                loadTabData(currentTab, 'team', teamSelectedMember, '');
            });

            // ── Approve single line ────────────────────────
            $(document).on('click', '.btn-approve-line', function () {
                const $btn   = $(this);
                const expId  = $btn.data('expid');
                const empId  = $btn.data('empid');
                const month  = $btn.data('month');
                const yearId = $btn.data('yearid');
                const amt    = $btn.closest('tr').find('.appr-amt').val();
                const remark = $btn.closest('tr').find('.appr-remark').val();
                approveSingleLine(expId, empId, month, yearId, amt, remark, $btn);
            });

            // ── Submit all lines ───────────────────────────
            $(document).on('click', '.btn-submit-all', function () {
                const $row = $(this).closest('.expand-row');
                submitAllLines($row);
            });

            // ── Deny all ───────────────────────────────────
            $(document).on('click', '.btn-deny-all', function () {
                $activeExpandRow = $(this).closest('.expand-row');
                denyTarget = 'all';
                $('#denyRemarkInput').val('');
                $('#denyRemarkError').hide();
                $('#denyModal').modal('show');
            });

            // ── Confirm deny ───────────────────────────────
            $(document).on('click', '#btnConfirmDeny', function () {
                const remark = $('#denyRemarkInput').val().trim();
                if (!remark) { $('#denyRemarkError').show(); return; }
                $('#denyRemarkError').hide();
                $('#denyModal').modal('hide');
                executeDeny(remark, $activeExpandRow);
            });

            // ── ExpID detail click ─────────────────────────
            $(document).on('click', '.btn-exp-detail', function () {
                const expId       = $(this).data('expid');
                const claimId     = $(this).data('claimid');
                const detailRowId = $(this).data('detailrowid');
                toggleClaimDetail(expId, claimId, detailRowId);
            });

            // ── Print expense modal ────────────────────────
            $(document).on('click', '#btnPrintExpense', function () {
                printExpenseModal();
            });

        });




        // =====================================================
        // LOAD TEAM MEMBERS (employee filter dropdown)
        // =====================================================

        function loadTeamMembers(tab) {

            // Use cache if already loaded for this tab
            if (teamMembersCache[tab]) {
                populateEmployeeDropdown(teamMembersCache[tab], tab);
                return;
            }

            const memberRoutes = {
                vnr:     '{{ route("vnr.team.members.list") }}',
                vvnr:    '{{ route("vvnr.team.members.list") }}',
                nursery: '{{ route("nursery.team.members.list") }}'
            };
            const url = memberRoutes[tab] || memberRoutes['vnr'];

            $.ajax({
                url: url,
                method: 'GET',
                success: function (response) {
                    const members = response.Team_list || [];
                    teamMembersCache[tab] = members;
                    populateEmployeeDropdown(members, tab);
                },
                error: function () {
                    // silently fail — dropdown stays as "All Employees"
                }
            });
        }

        function populateEmployeeDropdown(members, tab) {
            // Target the active tab pane's employee dropdown
            const $select = $('#' + tab + ' .filter-employee');
            const currentVal = $select.val();
            $select.empty().append('<option value="">All Employees</option>');
            $.each(members, function (i, m) {
                const empId   = m.EmployeeID || '';
                const empCode = m.EmpCode    || '';
                const name    = m.FullName   || m.Ename || '';
                const label   = empCode ? empCode + ' - ' + name : name;
                $select.append('<option value="' + empId + '">' + label + '</option>');
            });
            // Restore previous selection if still valid
            if (currentVal) $select.val(currentVal);
        }

        function resetFilters() {
            $('.filter-employee').val('');
            $('.filter-month').val('');
        }

        function toggleFilterVisibility(status) {
            if (status === 'approved') {
                $('.filter-row').show();
            } else {
                $('.filter-row').hide();
            }
        }


        // =====================================================
        // LOAD TAB DATA
        // =====================================================

        function loadTabData(tab, status, member, month) {

            showLoader();

            const routeMap = {
                pending:  {
                    vnr:     '{{ route("vnr.approver.pending.list") }}',
                    vvnr:    '{{ route("vvnr.approver.pending.list") }}',
                    nursery: '{{ route("nursery.approver.pending.list") }}'
                },
                approved: {
                    vnr:     '{{ route("vnr.approver.approved.list") }}',
                    vvnr:    '{{ route("vvnr.approver.approved.list") }}',
                    nursery: '{{ route("nursery.approver.approved.list") }}'
                },
                team: {
                    vnr:     '{{ route("vnr.team.all.expenses") }}',
                    vvnr:    '{{ route("vvnr.team.all.expenses") }}',
                    nursery: '{{ route("nursery.team.all.expenses") }}'
                }
            };

            const url = (routeMap[status] || routeMap['pending'])[tab] || routeMap['pending']['vnr'];

            const params = {};
            if (member) params.member = member;
            if (month)  params.month  = month;
            // For team status, pass claim_id filter
            if (status === 'team' && teamSelectedClaim) {
                params.claim_id = teamSelectedClaim;
            }

            $.ajax({
                url: url,
                method: 'GET',
                data: params,
                success: function (response) {
                    hideLoader();
                    if (status === 'team') {
                        // Raw API response: { success, data: { ExpenseType: { ClaimTypes, MonthlyGrandTotals, OverallGrandTotal } } }
                        populateTeamTable(response);
                    } else {
                        let data = response.Pending_list || response.Approved_list || [];
                        // If still empty, try other possible keys from API
                        if (!data || data.length === 0) {
                            const possibleKeys = ['Pending_list', 'Approved_list', 'pending_list', 'approved_list', 'data', 'list', 'records'];
                            for (const key of possibleKeys) {
                                if (response[key] && Array.isArray(response[key]) && response[key].length > 0) {
                                    data = response[key];
                                    break;
                                }
                            }
                        }
                        console.log('Approver response (' + status + '):', response);
                        populateTable(data, status);
                    }
                },
                error: function (xhr) {
                    hideLoader();
                    // APPR-LOAD-01: say what failed instead of a bare "Failed to load data"
                    const j = xhr && xhr.responseJSON;
                    const why = (j && (j.message || j.msg)) || (xhr && xhr.status === 504 ? 'The server took too long to answer.' : '') || (xhr && xhr.status ? 'Server answered ' + xhr.status + '.' : 'No answer from the server.');
                    Swal.fire('Could not load the ' + (status || 'pending') + ' list', why + ' Refresh the page to try again.', 'error');
                }
            });
        }




        // =====================================================
        // POPULATE TABLE
        // =====================================================

        function populateTable(data, status) {

            const monthNames = ['January','February','March','April','May','June',
                                'July','August','September','October','November','December'];

            if (status === 'approved') {
                $('.pending-header').hide();
                $('.approved-header').show();
                $('.team-header').hide();
            } else {
                $('.approved-header').hide();
                $('.pending-header').show();
                $('.team-header').hide();
            }

            let tbody = $('.tab-pane.active tbody');
            tbody.empty();

            if (!Array.isArray(data) || data.length === 0) {
                const cols = 8;
                tbody.append('<tr><td colspan="' + cols + '" class="text-center py-4 text-muted fw-semibold">No records found</td></tr>');
                return;
            }

            $.each(data, function (index, item) {

                const empName   = item.Ename
                                || (item.EmpName && typeof item.EmpName === 'object' ? item.EmpName.Ename : item.EmpName)
                                || 'N/A';
                const monthName = monthNames[(parseInt(item.Month) - 1)] || item.Month || '-';
                const courier   = (item.RecevingDate && item.RecevingDate !== '0000-00-00')
                    ? item.RecevingDate
                    : '<span class="badge bg-warning-subtle text-warning">Pending</span>';
                const rowId = 'row-' + index + '-' + item.EmployeeID + '-' + item.Month;

                // Helper: format date, return '—' if invalid
                function fmtDate(d) {
                    if (!d || d === '0000-00-00' || d === '1970-01-01') return '—';
                    try {
                        const dt = new Date(d);
                        if (isNaN(dt)) return '—';
                        return dt.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
                    } catch(e) { return '—'; }
                }

                // Helper: always show amount + date below it; grey out when no data
                function amtWithDate(amt, date) {
                    const hasDate = date && date !== '0000-00-00' && date !== '1970-01-01';
                    const amount  = parseFloat(amt || 0);
                    const amtStr  = amount > 0
                        ? '<span style="font-weight:600;color:#0b1a33;">₹' + amount.toLocaleString('en-IN') + '</span>'
                        : '<span style="color:#94a3b8;">—</span>';
                    const dateStr = hasDate
                        ? '<span style="color:#6b7280;">' + fmtDate(date) + '</span>'
                        : '<span style="color:#cbd5e1;">—</span>';
                    return '<div style="line-height:1.3;">' + amtStr + '</div>' +
                           '<div style="font-size:10px;margin-top:1px;">' + dateStr + '</div>';
                }

                // Build timeline stepper data (Claimant → Verifier → Approver → Finance)
                const stepClaimant = {
                    amt: parseFloat(item.Claim_Amount || 0),
                    date: fmtDate(item.DateOfSubmit),
                    done: item.DateOfSubmit && item.DateOfSubmit !== '0000-00-00'
                };
                const stepVerifier = {
                    amt: parseFloat(item.Verified_Amount || 0),
                    date: fmtDate(item.Verified_Date),
                    done: item.Verified_Date && item.Verified_Date !== '0000-00-00'
                };
                const stepApprover = {
                    amt: parseFloat(item.Approved_Amount || 0),
                    date: fmtDate(item.Approved_Date),
                    done: item.Approved_Date && item.Approved_Date !== '0000-00-00'
                };
                const stepFinance = {
                    amt: parseFloat(item.Finance_Amount || 0),
                    date: fmtDate(item.Finance_Date),
                    done: item.Finance_Date && item.Finance_Date !== '0000-00-00'
                };

                function stepHtml(label, step, isLast) {
                    const color = step.done ? '#198754' : '#6c757d';
                    const stepIcons = {
                        'Claimant': { done: 'ri-user-line',              pending: 'ri-user-line' },
                        'Verifier': { done: 'ri-shield-check-line',      pending: 'ri-shield-line' },
                        'Approver': { done: 'ri-checkbox-circle-line',   pending: 'ri-checkbox-blank-circle-line' },
                        'Finance':  { done: 'ri-bank-line',              pending: 'ri-bank-line' }
                    };
                    const icons = stepIcons[label] || { done: 'ri-checkbox-circle-fill', pending: 'ri-time-line' };
                    const icon  = step.done ? icons.done : icons.pending;
                    const line  = !isLast ? '<div style="flex:1;height:2px;background:' + (step.done ? '#198754' : '#dee2e6') + ';margin:0 4px;align-self:center;"></div>' : '';
                    const amtHtml  = step.done
                        ? '<div style="font-size:11px;color:#7C50D9;font-weight:600;">₹' + step.amt.toLocaleString('en-IN') + '</div>'
                        : '<div style="font-size:11px;color:#94a3b8;">—</div>';
                    const dateHtml = step.done
                        ? '<div style="font-size:10px;color:#6b7280;">' + step.date + '</div>'
                        : '<div style="font-size:10px;color:#cbd5e1;">—</div>';
                    return '<div style="display:flex;flex-direction:column;align-items:center;min-width:90px;">' +
                        '<div style="width:32px;height:32px;border-radius:50%;background:' + (step.done ? '#d1e7dd' : '#f8f9fa') + ';display:flex;align-items:center;justify-content:center;border:2px solid ' + color + ';">' +
                            '<i class="' + icon + '" style="color:' + color + ';font-size:16px;"></i>' +
                        '</div>' +
                        '<div class="fw-bold mt-1" style="font-size:11px;color:' + color + ';">' + label + '</div>' +
                        amtHtml +
                        dateHtml +
                    '</div>' + line;
                }

                const timelineHtml =
                    '<div class="d-flex align-items-start justify-content-center mb-3 py-2 px-3" style="background:#f8fdf9;border-radius:8px;border:1px solid #e9ecef;">' +
                        stepHtml('Claimant', stepClaimant, false) +
                        stepHtml('Verifier', stepVerifier, false) +
                        stepHtml('Approver', stepApprover, false) +
                        stepHtml('Finance', stepFinance, true) +
                    '</div>';

                // Courier / Submission info bar
                const dosDate     = fmtDate(item.DateOfSubmit);
                const recvDate    = fmtDate(item.RecevingDate);
                const docketNo    = item.DocateNo || '';
                const agency      = item.Agency || '';
                const cnIssueDate = fmtDate(item.CNIssueDate);

                let courierInfoHtml = '<div class="d-flex flex-wrap align-items-center gap-3 mb-3 py-2 px-3" style="background:#fff8f0;border-radius:8px;border:1px solid #f0e0cc;font-size:12px;">';
                courierInfoHtml += '<span><i class="ri-send-plane-line me-1" style="color:#e67e22;"></i><strong>Submitted:</strong> ' + dosDate + '</span>';
                courierInfoHtml += '<span><i class="ri-inbox-archive-line me-1" style="color:#198754;"></i><strong>Courier Received:</strong> ' + recvDate + '</span>';
                if (docketNo) courierInfoHtml += '<span><i class="ri-hashtag me-1" style="color:#6f42c1;"></i><strong>Docket No:</strong> ' + docketNo + '</span>';
                if (agency) courierInfoHtml += '<span><i class="ri-truck-line me-1" style="color:#0d6efd;"></i><strong>Agency:</strong> ' + agency + '</span>';
                if (cnIssueDate !== '-') courierInfoHtml += '<span><i class="ri-calendar-check-line me-1" style="color:#dc3545;"></i><strong>CN Issue:</strong> ' + cnIssueDate + '</span>';
                courierInfoHtml += '</div>';

                if (status === 'pending') {
                    const submittedCell = amtWithDate(item.Claim_Amount, item.DateOfSubmit);
                    const verifiedCell  = amtWithDate(item.Verified_Amount, item.Verified_Date);

                    // ── data row ──
                    tbody.append(
                        '<tr data-rowid="' + rowId + '">' +
                        '<td class="text-center fw-bold">' + (index + 1) + '</td>' +
                        '<td>' + empName + '</td>' +
                        '<td class="text-center">' + monthName + '</td>' +
                        '<td class="text-center">' + (item.Total_Claim || '0') + '</td>' +
                        '<td class="text-center">' + submittedCell + '</td>' +
                        '<td class="text-center">' + verifiedCell + '</td>' +
                        '<td class="text-center">' + courier + '</td>' +
                        '<td class="text-center">' +
                            '<div class="d-flex gap-1 justify-content-center flex-wrap">' +
                                '<a href="javascript:void(0)" class="btn btn-sm btn-outline-primary btn-view-details"' +
                                    ' title="View expense detail"' +
                                    ' data-empid="' + item.EmployeeID + '"' +
                                    ' data-month="' + item.Month + '"' +
                                    ' data-yearid="' + item.YearId + '">' +
                                    '<i class="ri-eye-line"></i> View' +
                                '</a>' +
                                '<a href="javascript:void(0)" class="btn btn-sm btn-success btn-approve-list"' +
                                    ' title="Approve / Deny"' +
                                    ' data-rowid="' + rowId + '"' +
                                    ' data-empid="' + item.EmployeeID + '"' +
                                    ' data-month="' + item.Month + '"' +
                                    ' data-yearid="' + item.YearId + '"' +
                                    ' data-empname="' + empName.replace(/"/g, '&quot;') + '">' +
                                    '<i class="ri-check-line"></i> Approve' +
                                '</a>' +
                            '</div>' +
                        '</td>' +
                        '</tr>'
                    );

                    // ── expand row (hidden) ──
                    tbody.append(
                        '<tr class="expand-row" id="expand-' + rowId + '" style="display:none;">' +
                        '<td colspan="8">' +
                            '<div class="expand-inner">' +
                                '<div class="d-flex justify-content-between align-items-center expand-title mb-2">' +
                                    '<span><i class="ri-list-check-2 me-1"></i>Expense Lines — ' + empName + ' / ' + monthName + '</span>' +
                                    '<button class="btn btn-sm btn-outline-secondary btn-close-expand" data-rowid="' + rowId + '">' +
                                        '<i class="ri-close-line"></i>' +
                                    '</button>' +
                                '</div>' +
                                timelineHtml +
                                courierInfoHtml +
                                '<div class="table-responsive">' +
                                    '<table class="table table-bordered table-sm align-middle expense-lines-table mb-0">' +
                                        '<thead><tr>' +
                                            '<th class="text-center">#</th>' +
                                            '<th class="text-center">Exp ID</th>' +
                                            '<th>Date</th>' +
                                            '<th>Claim Type</th>' +
                                            '<th class="text-center">Images</th>' +
                                            '<th class="text-end">Filled Amt</th>' +
                                            '<th style="min-width:110px;">Appr. Amt</th>' +
                                            '<th style="min-width:170px;">Appr. Remark</th>' +
                                            '<th class="text-center">&#10003;</th>' +
                                        '</tr></thead>' +
                                        '<tbody class="expand-lines-tbody">' +
                                            '<tr><td colspan="9" class="text-center py-3">' +
                                                '<div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading...' +
                                            '</td></tr>' +
                                        '</tbody>' +
                                        '<tfoot>' +
                                            '<tr class="table-warning fw-bold expand-footer" style="display:none;">' +
                                                '<td colspan="5" class="text-end">Total:</td>' +
                                                '<td class="text-end expand-filled-total">0</td>' +
                                                '<td class="text-end expand-appr-total">0</td>' +
                                                '<td></td>' +
                                                '<td class="text-center">' +
                                                    '<button class="btn btn-xs btn-success btn-submit-all me-1" style="font-size:11px;padding:2px 8px;"' +
                                                        ' data-empid="' + item.EmployeeID + '"' +
                                                        ' data-month="' + item.Month + '"' +
                                                        ' data-yearid="' + item.YearId + '">' +
                                                        '<i class="ri-check-double-line"></i> Submit' +
                                                    '</button>' +
                                                    '<button class="btn btn-xs btn-danger btn-deny-all" style="font-size:11px;padding:2px 8px;"' +
                                                        ' data-empid="' + item.EmployeeID + '"' +
                                                        ' data-month="' + item.Month + '"' +
                                                        ' data-yearid="' + item.YearId + '">' +
                                                        '<i class="ri-close-circle-line"></i> Deny' +
                                                    '</button>' +
                                                '</td>' +
                                            '</tr>' +
                                        '</tfoot>' +
                                    '</table>' +
                                '</div>' +
                            '</div>' +
                        '</td>' +
                        '</tr>'
                    );

                } else {
                    const submittedCell = amtWithDate(item.Claim_Amount, item.DateOfSubmit);
                    const verifiedCell  = amtWithDate(item.Verified_Amount, item.Verified_Date);
                    const approvedCell  = amtWithDate(item.Approved_Amount, item.Approved_Date);
                    const financeCell   = amtWithDate(item.Finance_Amount, item.Finance_Date);

                    // ── approved data row ──
                    tbody.append(
                        '<tr data-rowid="' + rowId + '">' +
                        '<td class="text-center fw-bold">' + (index + 1) + '</td>' +
                        '<td>' + empName + '</td>' +
                        '<td class="text-center">' + monthName + '</td>' +
                        '<td class="text-center">' + submittedCell + '</td>' +
                        '<td class="text-center">' + verifiedCell + '</td>' +
                        '<td class="text-center">' + approvedCell + '</td>' +
                        '<td class="text-center">' + financeCell + '</td>' +
                        '<td class="text-center">' +
                            '<div class="d-flex gap-1 justify-content-center flex-wrap">' +
                                '<a href="javascript:void(0)" class="btn btn-sm btn-outline-primary btn-view-details"' +
                                    ' data-empid="' + item.EmployeeID + '"' +
                                    ' data-month="' + item.Month + '"' +
                                    ' data-yearid="' + item.YearId + '">' +
                                    '<i class="ri-eye-line"></i> View' +
                                '</a>' +
                                '<a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary btn-expand-approved"' +
                                    ' title="View expense lines"' +
                                    ' data-rowid="' + rowId + '"' +
                                    ' data-empid="' + item.EmployeeID + '"' +
                                    ' data-month="' + item.Month + '"' +
                                    ' data-yearid="' + item.YearId + '"' +
                                    ' data-empname="' + empName.replace(/"/g, '&quot;') + '">' +
                                    '<i class="ri-list-check-2"></i> Lines' +
                                '</a>' +
                            '</div>' +
                        '</td>' +
                        '</tr>'
                    );

                    // ── approved expand row (hidden, read-only) ──
                    tbody.append(
                        '<tr class="expand-row expand-row-approved" id="expand-' + rowId + '" style="display:none;">' +
                        '<td colspan="8">' +
                            '<div class="expand-inner">' +
                                '<div class="d-flex justify-content-between align-items-center expand-title mb-2">' +
                                    '<span><i class="ri-list-check-2 me-1"></i>Expense Lines — ' + empName + ' / ' + monthName + '</span>' +
                                    '<button class="btn btn-sm btn-outline-secondary btn-close-expand" data-rowid="' + rowId + '">' +
                                        '<i class="ri-close-line"></i>' +
                                    '</button>' +
                                '</div>' +
                                timelineHtml +
                                courierInfoHtml +
                                '<div class="table-responsive">' +
                                    '<table class="table table-bordered table-sm align-middle expense-lines-table mb-0">' +
                                        '<thead><tr>' +
                                            '<th class="text-center">#</th>' +
                                            '<th class="text-center">Exp ID</th>' +
                                            '<th>Date</th>' +
                                            '<th>Claim Type</th>' +
                                            '<th class="text-center">Images</th>' +
                                            '<th class="text-end">Filled Amt</th>' +
                                            '<th class="text-end">Appr. Amt</th>' +
                                            '<th>Appr. Remark</th>' +
                                            '<th class="text-center">Status</th>' +
                                        '</tr></thead>' +
                                        '<tbody class="expand-lines-tbody">' +
                                            '<tr><td colspan="9" class="text-center py-3">' +
                                                '<div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading...' +
                                            '</td></tr>' +
                                        '</tbody>' +
                                        '<tfoot>' +
                                            '<tr class="table-success fw-bold expand-footer" style="display:none;">' +
                                                '<td colspan="5" class="text-end">Total:</td>' +
                                                '<td class="text-end expand-filled-total">0</td>' +
                                                '<td class="text-end expand-appr-total">0</td>' +
                                                '<td colspan="2"></td>' +
                                            '</tr>' +
                                        '</tfoot>' +
                                    '</table>' +
                                '</div>' +
                            '</div>' +
                        '</td>' +
                        '</tr>'
                    );
                }
            });

            // ── close expand on X button ──
            $(document).off('click.closeexpand').on('click.closeexpand', '.btn-close-expand', function () {
                const rowId = $(this).data('rowid');
                closeExpandRow(rowId);
            });
        }




        // =====================================================
        // POPULATE TEAM TABLE (Table format with fiscal year months)
        // =====================================================

        let teamClaimTypesCache = []; // cache claim types for the filter dropdown
        let teamSelectedMember = '';
        let teamSelectedClaim  = '';

        function populateTeamTable(response) {

            // Fiscal year months (Apr=4 to Mar=3)
            const fiscalMonths = [4,5,6,7,8,9,10,11,12,1,2,3];
            const monthShort = {1:'Jan',2:'Feb',3:'Mar',4:'Apr',5:'May',6:'Jun',7:'Jul',8:'Aug',9:'Sep',10:'Oct',11:'Nov',12:'Dec'};

            // Hide standard table headers
            $('.pending-header').hide();
            $('.approved-header').hide();
            $('.team-header').hide();

            let tbody = $('.tab-pane.active tbody');
            tbody.empty();

            // Parse the raw API response
            const expenseType   = response?.data?.ExpenseType || null;
            const claimTypes    = expenseType?.ClaimTypes || [];
            const grandTotals   = expenseType?.MonthlyGrandTotals || {};
            const overallTotal  = expenseType?.OverallGrandTotal || '0';

            // Cache claim types for the filter dropdown
            if (claimTypes.length > 0 && !teamSelectedClaim) {
                teamClaimTypesCache = claimTypes;
            }

            // If no data
            if (!expenseType || claimTypes.length === 0) {
                tbody.append(
                    '<tr><td colspan="7" class="text-center py-5">' +
                    '<div class="text-muted fw-semibold"><i class="ri-folder-open-line fs-2 d-block mb-2"></i>No team expenses found</div>' +
                    '</td></tr>'
                );
                return;
            }

            // Build entire content inside a single cell
            let html = '<tr><td colspan="7" style="padding:0 !important;border:none;">';

            // ── FILTER ROW ──────────────────────────────────────────────────
            html += '<div class="p-3 bg-light border-bottom">';
            html += '<div class="row g-2 align-items-end">';

            // Team Member dropdown
            html += '<div class="col-md-4">';
            html += '<label class="form-label fw-bold small mb-1">Team Member</label>';
            html += '<select class="form-select form-select-sm team-filter-member">';
            html += '<option value="">All Members</option>';
            if (teamMembersCache[currentTab]) {
                teamMembersCache[currentTab].forEach(function(m) {
                    const empId = m.EmployeeID || '';
                    const name  = m.FullName || m.Ename || '';
                    const sel   = (empId == teamSelectedMember) ? ' selected' : '';
                    html += '<option value="' + empId + '"' + sel + '>' + name + '</option>';
                });
            }
            html += '</select></div>';

            // Claim Type dropdown
            html += '<div class="col-md-4">';
            html += '<label class="form-label fw-bold small mb-1">Claim Type</label>';
            html += '<select class="form-select form-select-sm team-filter-claim">';
            html += '<option value="">All Claims</option>';
            teamClaimTypesCache.forEach(function(ct) {
                const sel = (ct.ClaimId == teamSelectedClaim) ? ' selected' : '';
                html += '<option value="' + ct.ClaimId + '"' + sel + '>' + (ct.ClaimName || 'N/A') + '</option>';
            });
            html += '</select></div>';

            // Filter button
            html += '<div class="col-md-4">';
            html += '<button class="btn btn-primary btn-sm w-100 team-filter-apply"><i class="ri-filter-3-line me-1"></i>Filter</button>';
            html += '</div>';

            html += '</div></div>';

            // ── EXPENSE TABLE ────────────────────────────────────────────────
            html += '<div class="table-responsive">';
            html += '<table class="table table-bordered table-hover align-middle mb-0" style="font-size:12px;">';

            // Header
            html += '<thead>';
            html += '<tr>';
            html += '<th class="text-center" style="min-width:40px;">#</th>';
            html += '<th style="min-width:140px;">Claim Type</th>';
            fiscalMonths.forEach(function(m) {
                html += '<th class="text-center" style="min-width:70px;">' + monthShort[m] + '</th>';
            });
            html += '<th class="text-center" style="min-width:90px;">Total</th>';
            html += '</tr>';
            html += '</thead>';

            // Body rows
            html += '<tbody>';
            claimTypes.forEach(function(ct, index) {
                const claimName  = ct.ClaimName || '-';
                const monthTotal = parseFloat(ct.MonthTotal || 0);
                const monthly    = ct.MonthlyTotals || {};
                const claimId    = ct.ClaimId || '';

                html += '<tr>';
                html += '<td class="text-center fw-bold">' + (index + 1) + '</td>';
                html += '<td class="fw-semibold text-nowrap">' + claimName + '</td>';

                fiscalMonths.forEach(function(m) {
                    const val = parseFloat(monthly[String(m)] || 0);
                    const cellClass = val > 0 ? 'fw-semibold' : 'text-muted';
                    html += '<td class="text-end ' + cellClass + '">' + (val > 0 ? '₹' + val.toLocaleString('en-IN') : '-') + '</td>';
                });

                html += '<td class="text-end fw-bold text-success">₹' + monthTotal.toLocaleString('en-IN') + '</td>';
                html += '</tr>';
            });
            html += '</tbody>';

            // Footer — Grand Total row
            html += '<tfoot>';
            html += '<tr style="background:#f4f6df;">';
            html += '<td></td>';
            html += '<td class="fw-bold" style="color:#3F227D;">GRAND TOTAL</td>';
            fiscalMonths.forEach(function(m) {
                const val = parseFloat(grandTotals[String(m)] || 0);
                html += '<td class="text-end fw-bold">' + (val > 0 ? '₹' + val.toLocaleString('en-IN') : '-') + '</td>';
            });
            html += '<td class="text-end fw-bold" style="color:#7C50D9;font-size:13px;">₹' + parseFloat(overallTotal).toLocaleString('en-IN') + '</td>';
            html += '</tr>';
            html += '</tfoot>';

            html += '</table>';
            html += '</div>';

            html += '</td></tr>';
            tbody.html(html);
        }


        // =====================================================
        // LOAD EXPENSE DETAILS (view modal)
        // =====================================================

        function loadExpenseDetails(empId, month, yearId, tab) {
            $.ajax({
                url: '{{ route("approver.expense.details") }}',
                method: 'GET',
                data: { empid: empId, month: month, yearid: yearId, tab: tab || currentTab },
                success: function (response) {
                    $('#expenseModalBody').html(response);
                },
                error: function () {
                    $('#expenseModalBody').html('<div class="alert alert-danger">Failed to load expense details</div>');
                }
            });
        }


        // =====================================================
        // OPEN EXPENSE LIST (inline expand row)
        // =====================================================

        function openExpenseList($btn, empId, month, yearId, empName) {

            const rowId      = $btn.data('rowid');
            const $expandRow = $('#expand-' + rowId);
            const isOpen     = $expandRow.is(':visible');

            // Close any other open expand rows first
            $('.expand-row:visible').hide();
            $('.btn-approve-list').removeClass('active-expand');

            if (isOpen) {
                // toggle off — already closed above
                currentLineData = {};
                return;
            }

            // Open this one
            $expandRow.show();
            $btn.addClass('active-expand');
            currentLineData = { empid: empId, month: month, yearid: yearId, empname: empName };

            const $linesTbody = $expandRow.find('.expand-lines-tbody');
            const $footer     = $expandRow.find('.expand-footer');
            $footer.hide();
            $linesTbody.html(
                '<tr><td colspan="9" class="text-center py-3">' +
                '<div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading...</td></tr>'
            );

            const dayRoutes = {
                vnr:     '{{ route("vnr.approver.pending.day") }}',
                vvnr:    '{{ route("vvnr.approver.pending.day") }}',
                nursery: '{{ route("nursery.approver.pending.day") }}'
            };
            const url = dayRoutes[currentTab] || dayRoutes['vnr'];

            $.ajax({
                url: url,
                method: 'GET',
                data: { empid: empId, yearid: '{{ session("year_id") }}', comid: '{{ session("company_id", 1) }}', month: month },
                success: function (response) {
                    renderExpenseLines(response, empId, month, yearId, $expandRow);
                },
                error: function () {
                    $linesTbody.html('<tr><td colspan="9" class="text-center text-danger py-2">Failed to load expense lines</td></tr>');
                }
            });
        }


        // =====================================================
        // RENDER EXPENSE LINES
        // =====================================================

        function renderExpenseLines(response, empId, month, yearId, $expandRow) {

            const $linesTbody = $expandRow.find('.expand-lines-tbody');
            const $footer     = $expandRow.find('.expand-footer');
            const lines = response.DayWiseDetails || response.Day_list || response.data || (Array.isArray(response) ? response : []);

            if (!lines.length) {
                $linesTbody.html('<tr><td colspan="9" class="text-center text-muted py-3">No expense lines found</td></tr>');
                return;
            }

            // ── Status summary bar ──────────────────────────────────────────
            let sumFilled = 0, sumVerify = 0, sumAppr = 0, sumFinance = 0;
            const statusSet = new Set();
            lines.forEach(function (l) {
                sumFilled  += parseFloat(l.FilledTAmt   || 0);
                sumVerify  += parseFloat(l.VerifyTAmt   || 0);
                sumAppr    += parseFloat(l.ApprTAmt     || 0);
                sumFinance += parseFloat(l.FinancedTAmt || 0);
                if (l.ClaimStatus) statusSet.add(l.ClaimStatus);
            });

            const statusColors = {
                'Filled':     { bg: '#fff3cd', color: '#856404', icon: 'ri-edit-line' },
                'Verified':   { bg: '#cff4fc', color: '#055160', icon: 'ri-shield-check-line' },
                'Approved':   { bg: '#d1e7dd', color: '#0a3622', icon: 'ri-checkbox-circle-line' },
                'Financed':   { bg: '#d1e7dd', color: '#0a3622', icon: 'ri-bank-line' },
                'Deactivate': { bg: '#f8d7da', color: '#842029', icon: 'ri-close-circle-line' },
                'Draft':      { bg: '#e2e3e5', color: '#41464b', icon: 'ri-draft-line' },
            };

            let statusBadges = '';
            statusSet.forEach(function (s) {
                const sc = statusColors[s] || { bg: '#e2e3e5', color: '#41464b', icon: 'ri-information-line' };
                statusBadges += '<span style="background:' + sc.bg + ';color:' + sc.color + ';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;margin-right:4px;">' +
                    '<i class="' + sc.icon + ' me-1"></i>' + s + '</span>';
            });

            // inject summary bar before the table (removed — timeline stepper replaces this)

            // ── Rows ────────────────────────────────────────────────────────
            let html = '';
            let totalFilled = 0;

            lines.forEach(function (line, idx) {
                const filled   = parseFloat(line.FilledTAmt || 0);
                const apprAmt  = parseFloat(line.ApprTAmt   || filled);
                const remark   = (line.ApprTRemark || '').replace(/"/g, '&quot;');
                const empRmk   = (line.EmpRemark   || line.Remark || '').replace(/</g, '&lt;');
                const expId    = line.ExpId    || '';
                const claimId  = line.ClaimId  || '';
                const date     = line.BillDate || '';
                const type     = line.ClaimName || '-';
                const img1     = line.opening_filepath || line.Img_1 || '';
                const img2     = line.closing_filepath || line.Img_2 || '';
                const colorHex = line.ColorCode || '';
                totalFilled   += filled;

                // Check if line is already approved
                const isLineApproved = (line.ApprBy && line.ApprBy !== '0' && line.ApprDate && line.ApprDate !== '0000-00-00');
                const approvedBadge = isLineApproved
                    ? '<span class="badge bg-success ms-1" style="font-size:9px;vertical-align:middle;">Approved</span>'
                    : '';
                const rowApprovedClass = isLineApproved ? ' line-approved' : '';

                // Default amount logic:
                // VNR: if already approved use ApprTAmt, else use VerifyTAmt (verifier sets it)
                // VVNR: if already approved use ApprTAmt, else use FilledTAmt (no verifier step)
                let defaultAmt;
                if (isLineApproved) {
                    defaultAmt = apprAmt;
                } else if (currentTab === 'vvnr') {
                    defaultAmt = filled; // VVNR has no verifier, default to filled
                } else {
                    defaultAmt = parseFloat(line.VerifyTAmt || filled); // VNR/Nursery use verified
                }

                // horizontal image strip — no wrapping
                const imgHtml = (img1 || img2)
                    ? '<div style="display:flex;gap:4px;flex-wrap:nowrap;">' +
                        (img1 ? '<a href="' + img1 + '" target="_blank">' +
                            '<img alt="Opening odo" src="' + img1 + '" style="height:36px;width:48px;object-fit:cover;border-radius:3px;border:1px solid #ccc;" title="Opening odo">' +
                        '</a>' : '') +
                        (img2 ? '<a href="' + img2 + '" target="_blank">' +
                            '<img alt="Closing odo" src="' + img2 + '" style="height:36px;width:48px;object-fit:cover;border-radius:3px;border:1px solid #ccc;" title="Closing odo">' +
                        '</a>' : '') +
                      '</div>'
                    : '<span class="text-muted" style="font-size:11px;">—</span>';

                const rowStyle = colorHex ? ' style="border-left:3px solid #' + colorHex + ';"' : '';
                const detailRowId = 'claimdetail-' + expId;

                html += '<tr data-expid="' + expId + '" class="' + rowApprovedClass + '"' + rowStyle + '>' +
                    '<td class="text-center">' + (idx + 1) + '</td>' +
                    '<td class="text-center">' +
                        (expId ? '<a href="javascript:void(0)" class="btn-exp-detail fw-semibold text-primary" style="font-size:11px;text-decoration:underline;cursor:pointer;" data-expid="' + expId + '" data-claimid="' + claimId + '" data-detailrowid="' + detailRowId + '">' + expId + '</a>' : '—') +
                        approvedBadge +
                    '</td>' +
                    '<td class="text-nowrap">' + date + '</td>' +
                    '<td>' +
                        '<div class="fw-semibold" style="font-size:12px;">' + type + '</div>' +
                        (empRmk ? '<div style="font-size:11px;color:#6c757d;margin-top:2px;">' + empRmk + '</div>' : '') +
                    '</td>' +
                    '<td class="text-center" style="white-space:nowrap;">' + imgHtml + '</td>' +
                    '<td class="text-end fw-semibold">&#8377;' + filled.toFixed(0) + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm appr-amt"' +
                        ' value="' + defaultAmt.toFixed(0) + '" min="0" max="' + filled + '"' +
                        ' data-expid="' + expId + '" data-empid="' + empId + '"' +
                        ' data-month="' + month + '" data-yearid="' + yearId + '"></td>' +
                    '<td><input type="text" class="form-control form-control-sm appr-remark"' +
                        ' value="' + remark + '" placeholder="Remark..." aria-label="Remark" data-expid="' + expId + '"></td>' +
                    '<td class="text-center">' +
                        '<button class="btn btn-sm ' + (isLineApproved ? 'btn-outline-success' : 'btn-success') + ' btn-approve-line"' +
                            ' data-expid="' + expId + '" data-empid="' + empId + '"' +
                            ' data-month="' + month + '" data-yearid="' + yearId + '"' +
                            ' title="' + (isLineApproved ? 'Re-approve' : 'Approve') + '">' +
                            '<i class="ri-check-line"></i>' +
                        '</button>' +
                    '</td></tr>' +
                    // hidden claim detail sub-row
                    '<tr class="claim-detail-row" id="' + detailRowId + '" style="display:none;">' +
                    '<td colspan="9" style="padding:0 !important;background:#fffdf0;">' +
                        '<div class="claim-detail-inner p-3"></div>' +
                    '</td></tr>';
            });

            $linesTbody.html(html);
            $expandRow.find('.expand-filled-total').text( totalFilled.toFixed(0));
            recalcApprTotal($expandRow);
            $footer.show();

            $linesTbody.off('input.appramt').on('input.appramt', '.appr-amt', function () {
                apprCapCheck($(this));
                recalcApprTotal($expandRow);
            });
            $linesTbody.find('.appr-amt').each(function () { apprCapCheck($(this)); });
        }


        // =====================================================
        // RECALC APPROVED TOTAL
        // =====================================================

        // APPR-CAP-UI-01 (14 Nov 2026): flag an approved amount above the claimed amount as it is typed and keep that
        // line's Approve disabled until it is corrected (the server refuses it too)
        function apprCapCheck($inp) {
            const v = parseFloat($inp.val()), c = parseFloat($inp.attr('max'));
            const over = !isNaN(v) && !isNaN(c) && c > 0 && v > c + 0.009;
            const $row = $inp.closest('tr');
            $inp.toggleClass('is-invalid', over).attr('aria-invalid', over ? 'true' : 'false');
            $row.find('.appr-cap-msg').remove();
            if (over) { $inp.after('<div class="appr-cap-msg invalid-feedback d-block" style="font-size:10px;">More than the claimed &#8377;' + c.toFixed(0) + '</div>'); }
            $row.find('.btn-approve-line').prop('disabled', over).attr('title', over ? 'The approved amount is more than the claimed amount' : 'Approve');
            return over;
        }

        function recalcApprTotal($expandRow) {
            let total = 0;
            $expandRow.find('.appr-amt').each(function () { total += parseFloat($(this).val()) || 0; });
            $expandRow.find('.expand-appr-total').text( total.toFixed(0));
        }


        // =====================================================
        // APPROVE SINGLE LINE
        // =====================================================

        function approveSingleLine(expId, empId, month, yearId, amt, remark, $btn) {

            if (!expId) { Swal.fire('Error', 'Missing expense ID', 'error'); return; }

            const $row = $btn.closest('tr');
            const remarkVal = $row.find('.appr-remark').val().trim();
            const amtVal = $row.find('.appr-amt').val().trim();

            if (!amtVal) {
                Swal.fire('Error', 'Enter approver amount', 'error');
                return;
            }
            if (apprCapCheck($row.find('.appr-amt'))) {
                Swal.fire('Not allowed', 'The approved amount cannot be more than the claimed amount.', 'warning');
                return;
            }

            // Remark is required only if amount differs from filled amount
            const filledAmt = $row.find('td:eq(5)').text().replace(/[^\d]/g, '');
            if (amtVal !== filledAmt && !remarkVal) {
                Swal.fire('Error', 'Remark is required when amount is changed', 'warning');
                return;
            }

            const approveRoutes = {
                vnr:     '{{ route("vnr.approver.claim.approve") }}',
                vvnr:    '{{ route("vvnr.approver.claim.approve") }}',
                nursery: '{{ route("nursery.approver.claim.approve") }}'
            };
            const url = approveRoutes[currentTab] || approveRoutes['vnr'];

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token:      '{{ csrf_token() }}',
                    exp_id:      expId,
                    employee_id: empId,
                    amount:      amt,
                    remark:      remark,
                    month:       month,
                    year_id:     yearId
                },
                success: function (response) {
                    if (response.Code === '300' || response.Code === '200' || response.success) {
                        // Mark row as approved with green highlight
                        $row.addClass('line-approved');
                        // Add approved badge next to ExpID if not already there
                        const $expCell = $row.find('td:eq(1)');
                        if (!$expCell.find('.badge').length) {
                            $expCell.append('<span class="badge bg-success ms-1" style="font-size:9px;vertical-align:middle;">Approved</span>');
                        }
                        // Keep button active for re-approval (outline style)
                        $btn.prop('disabled', false)
                            .html('<i class="ri-check-line"></i>')
                            .removeClass('btn-success').addClass('btn-outline-success')
                            .attr('title', 'Re-approve');
                        // Update approved amount in the input
                        if (response.Amount) {
                            $row.find('.appr-amt').val(response.Amount);
                        }
                        // Recalculate total
                        const $expandRow = $row.closest('.expand-row').length ? $row.closest('.expand-row') : $row.closest('tbody').closest('tr.expand-row').prev().closest('.expand-row');
                        recalcApprTotal($row.closest('tbody').closest('table').closest('.expand-inner').closest('td').closest('tr'));

                        Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                            title: 'Line approved', showConfirmButton: false, timer: 1500 });
                    } else {
                        $btn.prop('disabled', false).html('<i class="ri-check-line"></i>');
                        Swal.fire('Failed', response.msg || 'Could not approve line', 'error');
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).html('<i class="ri-check-line"></i>');
                    Swal.fire('Not approved', apprXhrMsg(xhr), 'error');
                }
            });
        }


        // =====================================================
        // SUBMIT ALL LINES
        // =====================================================

        // APPR-ERR-01 (14 Nov 2026): say why a request failed instead of "Request failed" (the server sends the reason)
        function apprXhrMsg(xhr) {
            const j = (xhr && xhr.responseJSON) || {};
            if (j.msg || j.message) { return j.msg || j.message; }
            if (xhr && (xhr.status === 419 || xhr.status === 401)) { return 'Your session has expired. Sign in again and retry.'; }
            if (xhr && xhr.status === 0) { return 'No connection to the server. Check the network and retry.'; }
            return 'The server could not complete the request' + (xhr && xhr.status ? ' (error ' + xhr.status + ')' : '') + '. Retry, and tell IT if it keeps happening.';
        }

        function submitAllLines($row) {

            const $btn   = $row.find('.btn-submit-all');
            const empId  = $btn.data('empid');
            const month  = $btn.data('month');
            const yearId = $btn.data('yearid');
            const empName = currentLineData.empname || 'this employee';

            // Calculate totals for the popup
            let totalApproved = 0;
            const $lineRows = $row.find('.expand-lines-tbody tr[data-expid]');
            $row.find('.expand-lines-tbody .appr-amt').each(function () {
                totalApproved += parseFloat($(this).val()) || 0;
            });
            const filledTotal = $row.find('.expand-filled-total').text() || '0';

            const monthNames = ['January','February','March','April','May','June',
                                'July','August','September','October','November','December'];
            const monthName = monthNames[parseInt(month) - 1] || month;

            const submitRoutes = {
                vnr:     '{{ route("vnr.approver.claim.submit") }}',
                vvnr:    '{{ route("vvnr.approver.claim.submit") }}',
                nursery: '{{ route("nursery.approver.claim.submit") }}'
            };
            const url = submitRoutes[currentTab] || submitRoutes['vnr'];

            Swal.fire({
                title: 'Confirm Final Submit',
                html:
                    '<div style="text-align:left;font-size:14px;">' +
                        '<table class="table table-sm table-bordered mb-0">' +
                            '<tr><td class="fw-bold">Employee</td><td>' + empName + '</td></tr>' +
                            '<tr><td class="fw-bold">Month</td><td>' + monthName + '</td></tr>' +
                            '<tr><td class="fw-bold">Total Claims</td><td>' + $lineRows.length + '</td></tr>' +
                            '<tr><td class="fw-bold">Filled Amount</td><td>&#8377;' + filledTotal + '</td></tr>' +
                            '<tr><td class="fw-bold">Approved Amount</td><td class="text-success fw-bold">&#8377;' + totalApproved.toFixed(0) + '</td></tr>' +
                        '</table>' +
                    '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: '<i class="ri-check-double-line me-1"></i> Submit',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        _token:  '{{ csrf_token() }}',
                        emp_id:  empId,
                        month:   month,
                        year_id: yearId
                    },
                    success: function (response) {
                        $btn.prop('disabled', false).html('<i class="ri-check-double-line"></i> Submit');
                        if (response.Code === '300' || response.Code === '200' || response.success) {
                            Swal.fire('Submitted!', response.msg || 'Claim submitted successfully', 'success');
                            closeExpandRow(null, $row);
                            loadTabData(currentTab, currentStatus);
                        } else {
                            Swal.fire('Failed', response.msg || 'Submit failed', 'error');
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html('<i class="ri-check-double-line"></i> Submit');
                        Swal.fire('Not submitted', apprXhrMsg(xhr), 'error');
                    }
                });
            });
        }


        // =====================================================
        // EXECUTE DENY
        // =====================================================

        function executeDeny(remark, $row) {
            const $btn   = $row.find('.btn-deny-all');
            const empId  = $btn.data('empid');
            const month  = $btn.data('month');
            const yearId = $btn.data('yearid');

            $.ajax({
                url: '{{ route("approver.deny") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    empid:  empId,
                    month:  month,
                    yearid: yearId,
                    remark: remark
                },
                success: function (response) {
                    if (response.Code === '300' || response.Code === '200' || response.success) {
                        Swal.fire('Denied', response.msg || 'Claim denied', 'success');
                        closeExpandRow(null, $row);
                        loadTabData(currentTab, currentStatus);
                    } else {
                        Swal.fire('Failed', response.msg || 'Deny failed', 'error');
                    }
                },
                error: function (xhr) {
                    Swal.fire('Not denied', apprXhrMsg(xhr), 'error');
                }
            });
        }


        // =====================================================
        // OPEN APPROVED EXPAND (read-only inline row)
        // =====================================================

        function openApprovedExpand($btn, empId, month, yearId, empName) {

            const rowId      = $btn.data('rowid');
            const $expandRow = $('#expand-' + rowId);
            const isOpen     = $expandRow.is(':visible');

            // Close any other open expand rows first
            $('.expand-row:visible').hide();
            $('.btn-expand-approved').removeClass('active-expand');

            if (isOpen) {
                return;
            }

            // Open this one
            $expandRow.show();
            $btn.addClass('active-expand');

            const $linesTbody = $expandRow.find('.expand-lines-tbody');
            const $footer     = $expandRow.find('.expand-footer');
            $footer.hide();
            $linesTbody.html(
                '<tr><td colspan="9" class="text-center py-3">' +
                '<div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading...</td></tr>'
            );

            const dayRoutes = {
                vnr:     '{{ route("vnr.approver.approved.day") }}',
                vvnr:    '{{ route("vvnr.approver.approved.day") }}',
                nursery: '{{ route("nursery.approver.approved.day") }}'
            };
            const url = dayRoutes[currentTab] || dayRoutes['vnr'];

            $.ajax({
                url: url,
                method: 'GET',
                data: { empid: empId, yearid: '{{ session("year_id") }}', comid: '{{ session("company_id", 1) }}', month: month },
                success: function (response) {
                    renderApprovedLines(response, empId, month, yearId, $expandRow);
                },
                error: function () {
                    $linesTbody.html('<tr><td colspan="9" class="text-center text-danger py-2">Failed to load expense lines</td></tr>');
                }
            });
        }


        // =====================================================
        // RENDER APPROVED LINES (read-only)
        // =====================================================

        function renderApprovedLines(response, empId, month, yearId, $expandRow) {

            const $linesTbody = $expandRow.find('.expand-lines-tbody');
            const $footer     = $expandRow.find('.expand-footer');
            const lines = response.DayWiseDetails || response.Day_list || response.data || (Array.isArray(response) ? response : []);

            if (!lines.length) {
                $linesTbody.html('<tr><td colspan="9" class="text-center text-muted py-3">No expense lines found</td></tr>');
                return;
            }

            // ── Status summary bar ──────────────────────────────────────────
            let sumFilled = 0, sumVerify = 0, sumAppr = 0, sumFinance = 0;
            const statusSet = new Set();
            lines.forEach(function (l) {
                sumFilled  += parseFloat(l.FilledTAmt   || 0);
                sumVerify  += parseFloat(l.VerifyTAmt   || 0);
                sumAppr    += parseFloat(l.ApprTAmt     || 0);
                sumFinance += parseFloat(l.FinancedTAmt || 0);
                if (l.ClaimStatus) statusSet.add(l.ClaimStatus);
            });

            const statusColors = {
                'Filled':     { bg: '#fff3cd', color: '#856404', icon: 'ri-edit-line' },
                'Verified':   { bg: '#cff4fc', color: '#055160', icon: 'ri-shield-check-line' },
                'Approved':   { bg: '#d1e7dd', color: '#0a3622', icon: 'ri-checkbox-circle-line' },
                'Financed':   { bg: '#d1e7dd', color: '#0a3622', icon: 'ri-bank-line' },
                'Deactivate': { bg: '#f8d7da', color: '#842029', icon: 'ri-close-circle-line' },
                'Draft':      { bg: '#e2e3e5', color: '#41464b', icon: 'ri-draft-line' },
            };

            let statusBadges = '';
            statusSet.forEach(function (s) {
                const sc = statusColors[s] || { bg: '#e2e3e5', color: '#41464b', icon: 'ri-information-line' };
                statusBadges += '<span style="background:' + sc.bg + ';color:' + sc.color + ';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;margin-right:4px;">' +
                    '<i class="' + sc.icon + ' me-1"></i>' + s + '</span>';
            });

            // (removed summary bar — timeline stepper replaces this)

            // ── Read-only rows ──────────────────────────────────────────────
            let html = '';
            let totalFilled = 0, totalAppr = 0;

            lines.forEach(function (line, idx) {
                const filled   = parseFloat(line.FilledTAmt || 0);
                const apprAmt  = parseFloat(line.ApprTAmt   || 0);
                const remark   = (line.ApprTRemark || '—').replace(/</g, '&lt;');
                const empRmk   = (line.EmpRemark   || line.Remark || '').replace(/</g, '&lt;');
                const expId    = line.ExpId    || '';
                const claimId  = line.ClaimId  || '';
                const date     = line.BillDate || '';
                const type     = line.ClaimName || '-';
                const status   = line.ClaimStatus || '';
                const img1     = line.opening_filepath || line.Img_1 || '';
                const img2     = line.closing_filepath || line.Img_2 || '';
                const colorHex = line.ColorCode || '';
                totalFilled   += filled;
                totalAppr     += apprAmt;

                const imgHtml = (img1 || img2)
                    ? '<div style="display:flex;gap:4px;flex-wrap:nowrap;">' +
                        (img1 ? '<a href="' + img1 + '" target="_blank">' +
                            '<img alt="Opening odo" src="' + img1 + '" style="height:36px;width:48px;object-fit:cover;border-radius:3px;border:1px solid #ccc;" title="Opening odo">' +
                        '</a>' : '') +
                        (img2 ? '<a href="' + img2 + '" target="_blank">' +
                            '<img alt="Closing odo" src="' + img2 + '" style="height:36px;width:48px;object-fit:cover;border-radius:3px;border:1px solid #ccc;" title="Closing odo">' +
                        '</a>' : '') +
                      '</div>'
                    : '<span class="text-muted" style="font-size:11px;">—</span>';

                const sc = statusColors[status] || { bg: '#e2e3e5', color: '#41464b', icon: 'ri-information-line' };
                const statusBadge = status
                    ? '<span style="background:' + sc.bg + ';color:' + sc.color + ';padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">' +
                        '<i class="' + sc.icon + ' me-1"></i>' + status + '</span>'
                    : '—';

                const rowStyle = colorHex ? ' style="border-left:3px solid #' + colorHex + ';"' : '';
                const detailRowId = 'claimdetail-' + expId;

                html += '<tr data-expid="' + expId + '"' + rowStyle + '>' +
                    '<td class="text-center">' + (idx + 1) + '</td>' +
                    '<td class="text-center">' +
                        (expId ? '<a href="javascript:void(0)" class="btn-exp-detail fw-semibold text-primary" style="font-size:11px;text-decoration:underline;cursor:pointer;" data-expid="' + expId + '" data-claimid="' + claimId + '" data-detailrowid="' + detailRowId + '">' + expId + '</a>' : '—') +
                    '</td>' +
                    '<td class="text-nowrap">' + date + '</td>' +
                    '<td>' +
                        '<div class="fw-semibold" style="font-size:12px;">' + type + '</div>' +
                        (empRmk ? '<div style="font-size:11px;color:#6c757d;margin-top:2px;">' + empRmk + '</div>' : '') +
                    '</td>' +
                    '<td class="text-center" style="white-space:nowrap;">' + imgHtml + '</td>' +
                    '<td class="text-end fw-semibold">&#8377;' + filled.toFixed(0) + '</td>' +
                    '<td class="text-end fw-semibold text-success">&#8377;' + apprAmt.toFixed(0) + '</td>' +
                    '<td style="font-size:12px;color:#555;">' + remark + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '</tr>' +
                    // hidden claim detail sub-row
                    '<tr class="claim-detail-row" id="' + detailRowId + '" style="display:none;">' +
                    '<td colspan="9" style="padding:0 !important;background:#fffdf0;">' +
                        '<div class="claim-detail-inner p-3"></div>' +
                    '</td></tr>';
            });

            $linesTbody.html(html);
            $expandRow.find('.expand-filled-total').text(totalFilled.toFixed(0));
            $expandRow.find('.expand-appr-total').text(totalAppr.toFixed(0));
            $footer.show();
        }


        // =====================================================
        // CLOSE EXPAND ROW
        // =====================================================

        function closeExpandRow(rowId, $row) {
            if ($row) {
                $row.hide();
            } else if (rowId) {
                $('#expand-' + rowId).hide();
            } else {
                $('.expand-row:visible').hide();
            }
            $('.btn-approve-list').removeClass('active-expand');
            $('.btn-expand-approved').removeClass('active-expand');
            currentLineData = {};
        }

        function closeExpenseList() {
            closeExpandRow(null, null);
        }


        // =====================================================
        // PRINT EXP LINE DETAIL (from inline row)
        // =====================================================

        $(document).on('click', '.btn-print-claim-detail', function () {
            const $inner = $(this).closest('.claim-detail-inner');
            const content = $inner.find('.claim-detail-content').html();
            const win = window.open('', '_blank', 'width=860,height=680');
            win.document.write(
                '<!DOCTYPE html><html lang="en"><head>' +
                '<title>Claim Detail</title>' +
                '<link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap@5.3.0/dist/css/bootstrap.min.css') }}">' +
                '<style>' +
                '  body { padding: 20px; font-size: 13px; }' +
                '  table { width:100%; border-collapse:collapse; }' +
                '  th, td { border:1px solid #dee2e6; padding:6px 10px; font-size:12px; }' +
                '  img { max-height:80px; }' +
                '</style>' +
                '</head><body>' + content +
                '<script>window.onload=function(){window.print();window.close();}<\/script>' +
                '</body></html>'
            );
            win.document.close();
        });


        // =====================================================
        // TOGGLE CLAIM DETAIL (inline below expense row)
        // =====================================================

        function toggleClaimDetail(expId, claimId, detailRowId) {
            const $detailRow = $('#' + detailRowId);

            // Toggle: if already visible, just hide it
            if ($detailRow.is(':visible')) {
                $detailRow.hide();
                return;
            }

            // Hide any other open claim detail rows
            $('.claim-detail-row:visible').hide();

            // Show this one with a loader
            const $inner = $detailRow.find('.claim-detail-inner');
            $inner.html(
                '<div class="text-center py-3">' +
                '<div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading team expense details...' +
                '</div>'
            );
            $detailRow.show();

            // Check if this is a team expense (status === 'team')
            if (currentStatus === 'team') {
                loadTeamClaimDetails(claimId, detailRowId, $inner);
            } else {
                // Regular claim detail for pending/approved
                loadRegularClaimDetail(expId, claimId, detailRowId, $inner);
            }
        }

        function loadTeamClaimDetails(claimId, detailRowId, $inner, selectedMonth) {
            // Fetch all team members' detailed records for this claim type
            const tabRoutes = {
                vnr:     '{{ route("vnr.team.member.all-expenses.by-claim") }}',
                vvnr:    '{{ route("vvnr.team.member.all-expenses.by-claim") }}',
                nursery: '{{ route("nursery.team.member.all-expenses.by-claim") }}'
            };
            const url = tabRoutes[currentTab] || tabRoutes['vnr'];

            const reqData = { claim_id: claimId };
            if (selectedMonth) {
                reqData.month = selectedMonth;
            }

            $inner.html(
                '<div class="text-center py-3">' +
                '<div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading...' +
                '</div>'
            );

            $.ajax({
                url: url,
                method: 'GET',
                data: reqData,
                success: function (response) {
                    if (response.success && response.employees) {
                        renderTeamClaimDetail(claimId, response.employees, $inner, selectedMonth);
                    } else {
                        $inner.html('<div class="alert alert-danger mb-0">No records found for this claim type.</div>');
                    }
                },
                error: function () {
                    $inner.html('<div class="alert alert-danger mb-0">Failed to load team expense details.</div>');
                }
            });
        }

        function renderTeamClaimDetail(claimId, employees, $inner, selectedMonth) {
            const monthNames = {
                1:'January', 2:'February', 3:'March', 4:'April', 5:'May', 6:'June',
                7:'July', 8:'August', 9:'September', 10:'October', 11:'November', 12:'December'
            };

            // Helper: extract a field from a record using multiple possible key names
            function fieldVal(record, keys, fallback) {
                for (const k of keys) {
                    if (record[k] !== undefined && record[k] !== null && record[k] !== '') {
                        return record[k];
                    }
                }
                return fallback !== undefined ? fallback : '-';
            }

            // Month filter bar
            let monthOptions = '<option value="">All Months</option>';
            for (let m = 1; m <= 12; m++) {
                const sel = (selectedMonth && parseInt(selectedMonth) === m) ? ' selected' : '';
                monthOptions += '<option value="' + m + '"' + sel + '>' + monthNames[m] + '</option>';
            }

            const filterBar =
                '<div class="d-flex align-items-center gap-2 mb-3">' +
                    '<label class="form-label mb-0 fw-bold text-nowrap" style="font-size:13px;">Filter by Month:</label>' +
                    '<select class="form-select form-select-sm team-detail-month-filter" data-claimid="' + claimId + '" style="max-width:180px;">' +
                        monthOptions +
                    '</select>' +
                '</div>';

            // Close button toolbar
            const toolbar =
                '<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">' +
                    '<span class="fw-bold" style="font-size:14px;color:#15648C;">' +
                        '<i class="ri-team-fill me-2"></i>Team Members — Expense Detail (Claim Type Wise)' +
                    '</span>' +
                    '<button class="btn btn-sm btn-outline-danger btn-close-claim-detail" title="Close">' +
                        '<i class="ri-close-line"></i>' +
                    '</button>' +
                '</div>';

            let htmlContent = '';
            let overallTotal = 0;

            if (!employees || employees.length === 0) {
                htmlContent = '<div class="alert alert-info mb-0">No employees found with expenses for this claim type.</div>';
            } else {
                $.each(employees, function (empIndex, employee) {
                    // Support multiple field name patterns for employee identity
                    const empName = fieldVal(employee, ['EmpName', 'Ename', 'FullName', 'Name', 'empname'], 'Unknown');
                    const empCode = fieldVal(employee, ['EmpCode', 'empcode', 'EmpId', 'EmployeeID'], '');
                    const records  = employee.records || [];
                    const empTotal = parseFloat(employee.total_amount || 0);

                    // Employee card header
                    htmlContent +=
                        '<div class="card mb-3 border" style="box-shadow: 0 1px 3px rgba(0,0,0,0.1);">' +
                            '<div class="card-header py-2" style="background-color:#e8f4f8; border-bottom: 2px solid #15648C;">' +
                                '<div class="d-flex justify-content-between align-items-center">' +
                                    '<div>' +
                                        '<i class="ri-user-3-line me-2" style="color:#15648C;"></i>' +
                                        '<span class="fw-bold" style="color:#15648C;">' + empName + '</span>' +
                                        (empCode ? '<span class="ms-2 text-muted" style="font-size:12px;">(' + empCode + ')</span>' : '') +
                                    '</div>' +
                                    '<span class="badge bg-primary fs-6">₹' + empTotal.toFixed(0) + '</span>' +
                                '</div>' +
                            '</div>';

                    if (records.length === 0) {
                        htmlContent += '<div class="card-body py-2"><p class="text-muted small mb-0">No records available.</p></div></div>';
                    } else {
                        htmlContent +=
                            '<div class="card-body p-0">' +
                                '<div class="table-responsive">' +
                                    '<table class="table table-sm align-middle mb-0">' +
                                        '<thead style="background-color:#f0f7ff;">' +
                                            '<tr>' +
                                                '<th class="text-center" style="width:45px;">#</th>' +
                                                '<th>Date / Month</th>' +
                                                '<th>Remarks</th>' +
                                                '<th class="text-center">Status</th>' +
                                                '<th class="text-end">Amount (₹)</th>' +
                                            '</tr>' +
                                        '</thead>' +
                                        '<tbody>';

                        let empRecordTotal = 0;
                        $.each(records, function (recIndex, record) {
                            // Date: try many possible field names
                            const rawDate = fieldVal(record,
                                ['BillDate', 'ExpDate', 'CrDate', 'bill_date', 'exp_date', 'cr_date', 'Date', 'date'], '');
                            // Month: try to get month number and show name
                            const rawMonth = fieldVal(record, ['ClaimMonth', 'Month', 'month', 'ExpMonth'], '');
                            let dateDisplay = rawDate;
                            if (!dateDisplay && rawMonth) {
                                const mn = parseInt(rawMonth);
                                dateDisplay = (monthNames[mn] || ('Month ' + rawMonth));
                            }
                            if (!dateDisplay) dateDisplay = '-';

                            // Remark
                            const remark = fieldVal(record,
                                ['DateEntryRemark', 'Remark', 'remark', 'Memo', 'memo', 'Description', 'description', 'Narration'], '—');

                            // Amount
                            const amount = parseFloat(fieldVal(record,
                                ['TotAmt', 'FilledTAmt', 'Amount', 'amount', 'Claim_Amount', 'ApprAmt', 'FinalAmt'], 0));

                            // Status
                            const status = fieldVal(record,
                                ['ClaimStatus', 'Status', 'status', 'ExpStatus'], 'Submitted');

                            const statusClasses = {
                                'Approved': 'bg-success-subtle text-success',
                                'Verified': 'bg-info-subtle text-info',
                                'Filled':   'bg-warning-subtle text-warning',
                                'Financed': 'bg-success-subtle text-success',
                            };
                            const sc = statusClasses[status] || 'bg-secondary-subtle text-secondary';
                            const statusBadge = '<span class="badge ' + sc + '">' + status + '</span>';

                            const remarkSafe = String(remark).replace(/"/g, '&quot;');
                            htmlContent +=
                                '<tr>' +
                                '<td class="text-center text-muted">' + (recIndex + 1) + '</td>' +
                                '<td style="white-space:nowrap;">' + dateDisplay + '</td>' +
                                '<td title="' + remarkSafe + '" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + remark + '</td>' +
                                '<td class="text-center">' + statusBadge + '</td>' +
                                '<td class="text-end fw-bold">₹' + amount.toFixed(0) + '</td>' +
                                '</tr>';

                            empRecordTotal += amount;
                        });

                        htmlContent +=
                                    '<tr class="fw-bold" style="background:#f0f7ff;">' +
                                        '<td colspan="4" class="text-end">Subtotal:</td>' +
                                        '<td class="text-end text-primary">₹' + empRecordTotal.toFixed(0) + '</td>' +
                                    '</tr>' +
                                '</tbody>' +
                                '</table>' +
                            '</div>' +
                        '</div></div>';

                        overallTotal += empRecordTotal;
                    }
                });

                // Grand total
                htmlContent +=
                    '<div class="alert mb-0 mt-2" style="background-color:#e3f2fd; border-left:4px solid #15648C;">' +
                        '<div class="d-flex justify-content-between align-items-center">' +
                            '<span class="fw-bold"><i class="ri-money-rupee-circle-line me-1"></i>Overall Total (All Members):</span>' +
                            '<span class="fw-bold" style="font-size:18px;color:#15648C;">₹' + overallTotal.toFixed(0) + '</span>' +
                        '</div>' +
                    '</div>';
            }

            $inner.html(toolbar + filterBar + htmlContent);
        }

        function loadRegularClaimDetail(expId, claimId, detailRowId, $inner) {
            // Map tab to company_id
            const tabCompanyMap = { vnr: '1', vvnr: '11', nursery: '3' };
            const companyId = tabCompanyMap[currentTab] || '1';

            // Fetch from claim-detail API
            $.ajax({
                url: '/claim-detail',
                method: 'GET',
                data: { claim_id: claimId, expid: expId, company_id: companyId },
                success: function (response) {
                    let htmlContent = response.html || '';
                    const files = response.uploaded_files || [];

                    // Build eligibility panel if available
                    let eligHtml = '';
                    if (typeof buildEligibilityPanel === 'function' && response.eligibility) {
                        eligHtml = buildEligibilityPanel(response.eligibility);
                    }

                    // Build uploaded images (left column) and thumbnails (bottom)
                    let leftImages = '';
                    let thumbsHtml = '';
                    if (files.length) {
                        files.forEach(function (f, i) {
                            const url = f.file_url || '';
                            const name = f.file_path || ('File ' + (i + 1));
                            // UAT E-05: label the lodging online payment receipt (UploadSequence 110).
                            const label = f.is_payment_receipt ? ((f.file_label || 'Online Payment Receipt') + ': ' + name) : name;
                            const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(name);
                            if (isImage && url) {
                                // First image goes large on left
                                if (!leftImages) {
                                    leftImages = '<a href="' + url + '" target="_blank">' +
                                        '<img alt="Bill or document image" src="' + url + '" class="claim-detail-main-img" style="width:100%;max-height:350px;object-fit:contain;border-radius:8px;border:1px solid #ddd;display:block;" title="' + label + '">' +
                                    '</a>';
                                }
                                // All images as thumbnails at bottom
                                thumbsHtml += '<a href="' + url + '" target="_blank" class="d-inline-block me-1 mb-1">' +
                                    '<img alt="Bill or document image" src="' + url + '" style="height:50px;width:50px;object-fit:cover;border-radius:4px;border:1px solid #ccc;" title="' + label + '">' +
                                '</a>';
                            } else if (url) {
                                thumbsHtml += '<a href="' + url + '" target="_blank" class="btn btn-sm btn-outline-primary me-1 mb-1">' +
                                    '<i class="ri-file-line me-1"></i>' + label +
                                '</a>';
                            }
                        });
                    }

                    // Close button + print button toolbar
                    const toolbar =
                        '<div class="d-flex justify-content-between align-items-center mb-2">' +
                            '<span class="fw-bold" style="font-size:13px;color:#15648C;">' +
                                '<i class="ri-file-info-line me-1"></i>Claim Detail — Exp #' + expId +
                            '</span>' +
                            '<div>' +
                                '<button class="btn btn-sm btn-outline-secondary btn-print-claim-detail me-1" title="Print">' +
                                    '<i class="ri-printer-line"></i>' +
                                '</button>' +
                                '<button class="btn btn-sm btn-outline-danger btn-close-claim-detail" title="Close">' +
                                    '<i class="ri-close-line"></i>' +
                                '</button>' +
                            '</div>' +
                        '</div>';

                    // Two-column layout: images left, form right
                    let bodyHtml = '';
                    if (leftImages) {
                        bodyHtml =
                            '<div class="row">' +
                                '<div class="col-md-4 mb-2">' +
                                    leftImages +
                                    (thumbsHtml ? '<div class="mt-2">' + thumbsHtml + '</div>' : '') +
                                '</div>' +
                                '<div class="col-md-8">' +
                                    htmlContent +
                                '</div>' +
                            '</div>';
                    } else {
                        // No images — full width form
                        bodyHtml = htmlContent +
                            (thumbsHtml ? '<div class="mt-2 pt-2 border-top">' +
                                '<h6 class="fw-bold" style="font-size:12px;color:#15648C;"><i class="ri-attachment-2 me-1"></i>Files</h6>' +
                                thumbsHtml + '</div>' : '');
                    }

                    $inner.html(toolbar + '<div class="claim-detail-content">' + bodyHtml + eligHtml + '</div>');
                },
                error: function () {
                    $inner.html('<div class="alert alert-danger mb-0">Failed to load claim detail.</div>');
                }
            });
        }

        // Close claim detail inline row
        $(document).on('click', '.btn-close-claim-detail', function () {
            $(this).closest('.claim-detail-row').hide();
        });


        // =====================================================
        // PRINT EXPENSE MODAL
        // =====================================================

        function printExpenseModal() {
            const content = document.getElementById('expenseModalBody').innerHTML;
            const win = window.open('', '_blank', 'width=900,height=700');
            win.document.write(
                '<!DOCTYPE html><html lang="en"><head>' +
                '<title>Expense Details</title>' +
                '<link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap@5.3.0/dist/css/bootstrap.min.css') }}">' +
                '<style>' +
                '  body { padding: 20px; font-size: 13px; }' +
                '  @media print { body { padding: 10px; } }' +
                '  table { width: 100%; border-collapse: collapse; }' +
                '  th, td { border: 1px solid #dee2e6; padding: 6px 10px; font-size: 12px; }' +
                '  th { background: #dde6ff !important; color: #1e3a8a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }' +
                '  img { max-height: 60px; }' +
                '</style>' +
                '</head><body>' +
                content +
                '<script>window.onload = function(){ window.print(); window.close(); }<\/script>' +
                '</body></html>'
            );
            win.document.close();
        }


        // =====================================================
        // LOADER
        // =====================================================

        function showLoader() {
            const cols = currentStatus === 'approved' ? 9 : 7;
            $('.tab-pane.active tbody').html(
                '<tr><td colspan="' + cols + '" class="text-center p-4">' +
                '<div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading...</td></tr>'
            );
        }

        function hideLoader() {}

        // ═══════════════════════════════════════════════════════════════
        // EXCESS RUNNING KM APPROVAL
        // ═══════════════════════════════════════════════════════════════

        $('#refreshExcessRunning').on('click', loadExcessRunningData);

        let excessData = [];

        function loadExcessRunningData() {
            const container = $('#excessRunningContainer');
            container.html('<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading excess running data...</div>');

            const empId = '{{ session("employee_id") }}';
            const yearId = '{{ session("year_id") }}';
            const comId = '{{ session("company_id") }}';
            const tokenId = '{{ session("token_id", "") }}';

            const url = `{{ \App\Support\LegacyApi::baseUrl() }}/${comId}/common.php?value=pending_odo_approve&YearId=${yearId}&tokenid=${tokenId}&ComId=${comId}&empid=${empId}`;

            $.get(url, function(response) {
                if (typeof response === 'string') response = JSON.parse(response);

                if (response.Code === '200' && response.data && response.data.length > 0) {
                    excessData = response.data;
                    container.html(renderExcessRunningTable(excessData));
                } else {
                    excessData = [];
                    container.html('<div class="text-center py-4"><i class="ri-speed-line fs-36 text-muted"></i><p class="text-muted mt-2">No excess running KM pending approval</p></div>');
                }
            }).fail(function() {
                container.html('<div class="alert alert-danger">Failed to load excess running data. Please try again.</div>');
            });
        }

        function getMonthNameFromNumber(monthNum) {
            const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            return months[parseInt(monthNum)] || '';
        }

        function formatExcessDate(dateStr) {
            if (!dateStr || dateStr === '0000-00-00') return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
        }

        function renderExcessRunningTable(data) {
            let html = `<div class="table-responsive">
                <table class="table table-bordered table-hover table-sm align-middle" style="font-size:12px;">
                    <thead style="background:#dde6ff;color:#1e3a8a;">
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>Month</th>
                            <th>Vehicle</th>
                            <th class="text-end">Total KM</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>`;

            data.forEach(function(emp, idx) {
                if (!emp || !emp.FullName) return;
                const monthName = getMonthNameFromNumber(emp.ClaimMonth);
                const totalKm = emp.total_run_km || 0;

                html += `<tr>
                    <td>${idx + 1}</td>
                    <td class="fw-semibold">${emp.FullName}</td>
                    <td><code>${emp.EmpCode || ''}</code></td>
                    <td>${monthName}</td>
                    <td><span class="badge bg-info-subtle text-info">${emp.wheeler_type || ''}</span></td>
                    <td class="text-end fw-bold text-danger">${totalKm} KM</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-primary excess-review-btn" data-index="${idx}">
                            <i class="ri-eye-line me-1"></i>Review & Approve
                        </button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            return html;
        }

        // Open review modal
        $(document).on('click', '.excess-review-btn', function() {
            const idx = $(this).data('index');
            const emp = excessData[idx];
            if (!emp) return;
            showExcessReviewModal(emp, idx);
        });

        function showExcessReviewModal(emp, wholeIndex) {
            const items = emp.items || [];
            const monthName = getMonthNameFromNumber(emp.ClaimMonth);
            const totalKm = emp.total_run_km || 0;

            let itemsHtml = '';
            items.forEach(function(item, i) {
                const statusColor = item.Status === 'Approved' ? 'success' : item.Status === 'Rejected' ? 'danger' : 'warning';
                const km = parseInt(item.TotKm || 0);
                const cumKm = item.CumulativeKm || 0;
                const date = formatExcessDate(item.BillDate);
                const isPending = item.Status === 'Pending' || !item.Status;

                itemsHtml += `<tr id="excess-item-${item.ExpId}">
                    <td>${i + 1}</td>
                    <td>${item.ExpId}</td>
                    <td>${date}</td>
                    <td class="fw-bold">${km}</td>
                    <td>${cumKm}</td>
                    <td>
                        ${item.opening_filepath ? `<a href="${item.opening_filepath}" target="_blank"><img alt="Bill or document image" src="${item.opening_filepath}" style="height:40px;border-radius:4px;cursor:pointer;" onerror="this.style.display='none'"></a>` : '-'}
                    </td>
                    <td>
                        ${item.closing_filepath ? `<a href="${item.closing_filepath}" target="_blank"><img alt="Bill or document image" src="${item.closing_filepath}" style="height:40px;border-radius:4px;cursor:pointer;" onerror="this.style.display='none'"></a>` : '-'}
                    </td>
                    <td><span class="badge bg-${statusColor}-subtle text-${statusColor}" id="status-${item.ExpId}">${item.Status || 'Pending'}</span></td>
                    <td class="text-center" id="action-${item.ExpId}">
                        ${isPending ? `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-success excess-approve-item" data-expid="${item.ExpId}" data-cumkm="${cumKm}" data-index="${wholeIndex}" data-empid="${emp.EmpId}" data-month="${emp.ClaimMonth}">
                                    <i class="ri-check-line"></i>
                                </button>
                                <button class="btn btn-danger excess-reject-item" data-expid="${item.ExpId}" data-index="${wholeIndex}" data-empid="${emp.EmpId}" data-month="${emp.ClaimMonth}">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        ` : `<span class="text-muted">Done</span>`}
                    </td>
                </tr>`;
            });

            const modalHtml = `
            <div class="modal fade" id="excessReviewModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content border-0">
                        <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                            <h5 class="modal-title text-white">
                                <i class="ri-speed-line me-2"></i>${emp.FullName} (${emp.EmpCode}) — ${monthName}
                                <span class="badge bg-danger ms-2">${totalKm} KM Total</span>
                                <span class="badge bg-info ms-1">${emp.wheeler_type || ''}</span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="alert alert-warning mb-0 rounded-0 py-2 px-3" style="font-size:12px;">
                                <i class="ri-error-warning-line me-1"></i>
                                <strong>Excess Running:</strong> This employee's total KM exceeds 1200 KM for this month. Review each entry and approve (full/split) or reject individually. After reviewing, click "Final Submit" to complete.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm align-middle mb-0" style="font-size:11px;">
                                    <thead style="background:#f0f4f8;">
                                        <tr>
                                            <th>#</th>
                                            <th>Exp ID</th>
                                            <th>Date</th>
                                            <th>KM</th>
                                            <th>Cumulative KM</th>
                                            <th>Opening Img</th>
                                            <th>Closing Img</th>
                                            <th>Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>${itemsHtml}</tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button class="btn btn-success excess-final-submit" data-empid="${emp.EmpId}" data-month="${emp.ClaimMonth}" data-index="${wholeIndex}">
                                <i class="ri-check-double-line me-1"></i>Final Submit
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

            $('#excessReviewModal').remove();
            $('body').append(modalHtml);
            $('#excessReviewModal').modal('show');
        }

        // Approve individual item (with split option)
        $(document).on('click', '.excess-approve-item', function() {
            const btn = $(this);
            const expId = btn.data('expid');
            const cumKm = btn.data('cumkm');
            const wholeIndex = btn.data('index');
            const tmemId = btn.data('empid');
            const month = btn.data('month');

            // Show split option if cumulative > 1200
            if (parseInt(cumKm) > 1200) {
                Swal.fire({
                    title: 'Approve Odometer Reading',
                    html: `
                        <p>Cumulative KM: <strong>${cumKm}</strong></p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="splitOption" id="splitFull" value="full" checked>
                            <label class="form-check-label" for="splitFull">Approve Full (up to ${cumKm} KM)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="splitOption" id="splitSplit" value="split">
                            <label class="form-check-label" for="splitSplit">Approve Split-wise (up to 1200 KM only)</label>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    confirmButtonColor: '#198754',
                    preConfirm: () => {
                        return document.querySelector('input[name="splitOption"]:checked').value;
                    }
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    const split = result.value === 'split' ? '1' : '0';
                    const below = '1200';
                    const above = (parseInt(cumKm) - 1200).toString();
                    callApproveOdo(expId, 'approve_odo_reading', split, below, above, wholeIndex, tmemId, month);
                });
            } else {
                Swal.fire({
                    title: 'Approve this reading?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    confirmButtonColor: '#198754',
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    callApproveOdo(expId, 'approve_odo_reading', '0', '', '', wholeIndex, tmemId, month);
                });
            }
        });

        // Reject individual item
        $(document).on('click', '.excess-reject-item', function() {
            const btn = $(this);
            const expId = btn.data('expid');
            const wholeIndex = btn.data('index');
            const tmemId = btn.data('empid');
            const month = btn.data('month');

            Swal.fire({
                title: 'Reject this reading?',
                text: 'This odometer reading will be rejected.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                confirmButtonColor: '#dc3545',
            }).then((result) => {
                if (!result.isConfirmed) return;
                callApproveOdo(expId, 'reject_odo_reading', '', '', '', wholeIndex, tmemId, month);
            });
        });

        // API call for approve/reject individual
        function callApproveOdo(expId, value, split, below1200, above1200, wholeIndex, tmemId, month) {
            const empId = '{{ session("employee_id") }}';
            const yearId = '{{ session("year_id") }}';
            const comId = '{{ session("company_id") }}';
            const tokenId = '{{ session("token_id", "") }}';

            let url = `{{ \App\Support\LegacyApi::baseUrl() }}/${comId}/common.php?value=${value}&YearId=${yearId}&tokenid=${tokenId}&ComId=${comId}&empid=${empId}&ExpId=${expId}`;
            if (split) url += `&split=${split}`;
            if (below1200) url += `&below_1200km=${below1200}`;
            if (above1200) url += `&above_1200km=${above1200}`;

            $.get(url, function(response) {
                if (typeof response === 'string') response = JSON.parse(response);
                if (response.Code === '200') {
                    const isApproved = value === 'approve_odo_reading';
                    const newStatus = isApproved ? 'Approved' : 'Rejected';
                    const statusColor = isApproved ? 'success' : 'danger';

                    // Update UI
                    $(`#status-${expId}`).removeClass().addClass(`badge bg-${statusColor}-subtle text-${statusColor}`).text(newStatus);
                    $(`#action-${expId}`).html('<span class="text-muted">Done</span>');

                    // If split=1, reject all other pending items in this group
                    if (split === '1') {
                        $(`#excessReviewModal tbody tr`).each(function() {
                            const rowExpId = $(this).attr('id')?.replace('excess-item-', '');
                            if (rowExpId && rowExpId !== expId) {
                                $(`#status-${rowExpId}`).removeClass().addClass('badge bg-danger-subtle text-danger').text('Rejected');
                                $(`#action-${rowExpId}`).html('<span class="text-muted">Done</span>');
                            }
                        });
                        // Auto final submit after split
                        callFinalSubmit(tmemId, month);
                    }

                    showAlert(isApproved ? 'success' : 'warning', isApproved ? 'ri-check-line' : 'ri-close-line', `Reading ${newStatus} successfully`);
                } else {
                    showAlert('danger', 'ri-error-warning-line', response.msg || 'Action failed');
                }
            }).fail(function() {
                showAlert('danger', 'ri-error-warning-line', 'Server error. Please try again.');
            });
        }

        // Final submit
        $(document).on('click', '.excess-final-submit', function() {
            const btn = $(this);
            const tmemId = btn.data('empid');
            const month = btn.data('month');

            Swal.fire({
                title: 'Final Submit?',
                text: 'This will finalize all approvals/rejections for this employee\'s excess running KM.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Submit',
                confirmButtonColor: '#198754',
            }).then((result) => {
                if (!result.isConfirmed) return;
                btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> Submitting...');
                callFinalSubmit(tmemId, month);
            });
        });

        function callFinalSubmit(tmemId, month) {
            const empId = '{{ session("employee_id") }}';
            const yearId = '{{ session("year_id") }}';
            const comId = '{{ session("company_id") }}';
            const tokenId = '{{ session("token_id", "") }}';

            const url = `{{ \App\Support\LegacyApi::baseUrl() }}/${comId}/common.php?value=approve_odo_final_submit&YearId=${yearId}&tokenid=${tokenId}&ComId=${comId}&empid=${empId}&tmempid=${tmemId}&month=${month}`;

            $.get(url, function(response) {
                if (typeof response === 'string') response = JSON.parse(response);
                if (response.Code === '200') {
                    Swal.fire('Submitted!', 'Excess running KM finalized successfully.', 'success');
                    $('#excessReviewModal').modal('hide');
                    loadExcessRunningData();
                } else {
                    Swal.fire('Error', response.msg || 'Final submit failed.', 'error');
                    $('.excess-final-submit').prop('disabled', false).html('<i class="ri-check-double-line me-1"></i>Final Submit');
                }
            }).fail(function() {
                Swal.fire('Error', 'Server error during final submit.', 'error');
                $('.excess-final-submit').prop('disabled', false).html('<i class="ri-check-double-line me-1"></i>Final Submit');
            });
        }

    </script>

@endpush
