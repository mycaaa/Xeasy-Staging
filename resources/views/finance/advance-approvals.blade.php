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
            background-color: #155997;
            color: white;
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

        .status-ready {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-processed {
            background-color: #e8f5e8;
            color: #388e3c;
        }

        /* Amount styling */
        .amount-display {
            font-weight: 600;
            color: #155997;
            font-size: 0.8rem;
        }

        /* Deactive employee highlighting */
        .employee-deactive {
            background-color: #fff0f0 !important;
            border-left: 3px solid #dc3545;
        }
        .deactive-badge {
            font-size: 0.55rem;
            padding: 1px 4px;
            border-radius: 3px;
            background-color: #dc3545;
            color: #fff;
            margin-left: 4px;
            font-weight: 600;
            vertical-align: middle;
        }

        /* Action buttons */
        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 0.65rem;
            cursor: pointer;
        }

        .btn-approve:hover {
            background-color: #218838;
            color: white;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 0.65rem;
            cursor: pointer;
        }

        .btn-reject:hover {
            background-color: #c82333;
            color: white;
        }

        .btn-detail {
            background-color: #155997;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 0.65rem;
            cursor: pointer;
        }

        .btn-detail:hover {
            background-color: #0d3d66;
            color: white;
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

        .crop-tag,
        .variety-tag {
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

        .activity-count {
            background-color: #155997;
            color: white;
            border-radius: 8px;
            padding: 1px 5px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        /* Form styling */
        .form-control-sm {
            font-size: 0.75rem;
            padding: 0.25rem 0.65rem;
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

        /* Finance specific styling */
        .finance-badge {
            background-color: #17a2b8;
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
        }

        .col-1 {
            flex: 0 0 auto;
            width: 7.5%;
        }

        .form-check-input {
            --bs-form-check-bg: var(--bs-body-bg);
            width: 1em;
            height: 1em;
            margin-top: .25em;
            vertical-align: top;
            background-color: var(--bs-form-check-bg);
            background-image: var(--bs-form-check-bg-image);
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            border: var(--bs-border-width) solid #27323d5e;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
            print-color-adjust: exact;
        }

        .payment-record-item[data-can-select="true"]:hover {
            background-color: #f8f9fa !important;
        }

        /* Enhanced Tab Styling */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            /* font-size:12px; */
            border: none;
            color: #6c757d;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-tabs .nav-link:hover {
            background-color: #f8f9fa;
            color: #155997;
        }

        .nav-tabs .nav-link.active {
            color: #155997;
            background-color: transparent;
            border-bottom: 2px solid #155997;
        }

        .nav-tabs .nav-link .badge {
            font-size: 0.65rem;
            padding: 0.25em 0.6em;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .nav-tabs .nav-link {
                padding: 0.5rem !important;
            }

            .nav-tabs .nav-link span:not(.badge) {
                display: none !important;
            }

            .nav-tabs .nav-link .badge {
                position: absolute;
                top: 2px;
                right: 2px;
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
                <h3>Finance Advance Approvals</h3>
                <div style="width: 80px;"></div>
            </div>

            <!-- Alert Message -->
            <div id="Message" class="alert alert-danger" style="display: none;"></div>

            <!-- Enhanced Tabs -->
            <div class="row g-2 mb-3" id="financeApprovalTabs" role="tablist">

                <div class="col">
                    <button
                        class="btn btn-outline-warning btn-sm w-100 active d-flex align-items-center justify-content-center gap-2"
                        id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                        <i class="fa fa-clock-o"></i>
                        Pending
                        <span class="badge bg-warning rounded-pill" id="pendingCount">0</span>
                    </button>
                </div>

                <div class="col">
                    <button
                        class="btn btn-outline-success btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                        id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button">
                        <i class="fa fa-check-circle"></i>
                        Approved
                        <span class="badge bg-success rounded-pill" id="approvedCount">0</span>
                    </button>
                </div>

                <div class="col">
                    <button class="btn btn-outline-info btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                        id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button">
                        <i class="fa fa-credit-card"></i>
                        Instructed Payment
                        <span class="badge bg-info rounded-pill" id="paymentCount">0</span>
                    </button>
                </div>

                <div class="col">
                    <button
                        class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                        id="confirmed-tab" data-bs-toggle="tab" data-bs-target="#confirmed" type="button">
                        <i class="fa fa-check-circle-o"></i>
                        Confirmed
                        <span class="badge bg-primary rounded-pill" id="confirmedCount">0</span>
                    </button>
                </div>

                <div class="col">
                    <button
                        class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                        id="unsettled-tab" data-bs-toggle="tab" data-bs-target="#unsettled" type="button">
                        <i class="fa fa-exclamation-triangle"></i>
                        Unsettled
                        <span class="badge bg-danger rounded-pill" id="unsettledCount">0</span>
                    </button>
                </div>

                <div class="col">
                    <button
                        class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                        id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button">
                        <i class="fa fa-times-circle"></i>
                        Rejected
                        <span class="badge bg-secondary rounded-pill" id="rejectedCount">0</span>
                    </button>
                </div>

            </div>
            <!-- Dynamic Filter Bar -->
            <div id="dynamicFilterBar" class="mb-2 p-2 mt-2" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;">
                <div class="d-flex align-items-center gap-2 flex-nowrap w-100" id="filterInputsContainer">
                </div>
            </div>
            <!-- Tab Content -->
            <div class="tab-content" id="financeApprovalTabsContent">
                <!-- Pending Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted" style="font-size: 0.8rem;">
                            <i class="fa fa-clock-o"></i> Pending Finance Approvals
                        </div>
                        <div>
                            <button type="button" class="btn btn-info btn-sm" onclick="fetchPendingRequests()">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-success btn-sm" onclick="generateExcelExport()">
                                <i class="fa fa-excel"></i>Excel
                            </button>
                        </div>

                    </div>
                    <div class="table-responsive">
                        <table id="pendingTable" class="table table-striped table-hover table-borderd" style="width:100%">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Sr.No</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Employee</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Crops</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Varieties</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Purpose</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Amount</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Approved Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="checkbox" id="selectAllApproved"
                                onchange="toggleSelectAllApproved()">
                            <label class="form-check-label" for="selectAllApproved">
                                Select All
                            </label>
                        </div>
                        <div>
                            <button type="button" class="btn btn-success btn-sm" id="releasePaymentBtn"
                                onclick="releaseSelectedPayments()" disabled>
                                <i class="fa fa-credit-card"></i> Release Payment
                            </button>
                            @php
                                $canAccessMatrix = (
                                    (string)session('emp_code') === '1740' ||
                                    (string)session('employee_details.EmpCode') === '1740' ||
                                    (string)(Auth::user()?->employee?->EmpCode ?? '') === '1740' ||
                                    (string)(Auth::user()?->employee?->EmpCode_New ?? '') === '1740' ||
                                    (string)(Auth::user()?->employee_id ?? '') === '1740' ||
                                    (string)(Auth::user()?->emp_code ?? '') === '1740'
                                );
                            @endphp
                            @if($canAccessMatrix)
                            <button type="button" class="btn btn-info btn-sm text-white ms-1" id="previewAdvanceMatrixBtn"
                                onclick="previewAdvanceMatrixPayload()" disabled>
                                <i class="fa fa-eye"></i> Preview Payload
                            </button>
                            <button type="button" class="btn btn-primary btn-sm ms-1" id="pushAdvanceToMatrixBtn"
                                onclick="pushSelectedAdvanceToMatrix()" disabled>
                                <i class="fa fa-paper-plane"></i> Push to Matrix
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm ms-1" onclick="syncMatrixAdvanceUpdates()">
                                <i class="fa fa-refresh"></i> Sync Matrix
                            </button>
                            @endif
                            <button type="button" class="btn btn-info btn-sm ms-1" onclick="fetchApprovedRequests()">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="approvedTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Select</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Sr.No</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Employee</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Crops</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Varieties</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Purpose</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Amount</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Approved Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Rejected Tab -->
                <div class="tab-pane fade" id="rejected" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted" style="font-size: 0.8rem;">
                            <i class="fa fa-times-circle"></i> Rejected Finance Requests
                        </div>
                        <button type="button" class="btn btn-info btn-sm" onclick="fetchRejectedRequests()">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="rejectedTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Sr.No</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Employee</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Crops</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Varieties</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Purpose</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Amount</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Rejected Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Instructed Payment Tab -->
                <div class="tab-pane fade" id="payment" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <div id="paymentSummary" class="text-muted" style="font-size: 0.8rem;">
                                <!-- Payment summary will be populated here -->
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info btn-sm" onclick="refreshPaymentData()">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="generateExportBtn"
                                onclick="generateFocusExport()">
                                <i class="fa fa-file-excel-o"></i> Generate Focus Export
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="paymentTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Sr.No</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Employee</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Amount</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Released Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Status</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Confirmed Payments Tab -->
                <div class="tab-pane fade" id="confirmed" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <div id="confirmedSummary" class="text-muted" style="font-size: 0.8rem;">
                                <!-- Confirmed payments summary will be populated here -->
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info btn-sm" onclick="refreshConfirmedData()">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <!-- <button type="button" class="btn btn-warning btn-sm" onclick="confirmPaymentsDirect()">
                                        <i class="fa fa-upload"></i> Sync Payments
                                    </button> -->
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="confirmedPaymentsTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Sr.No</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Employee</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Activity</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Amount</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Transaction Ref</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Payment Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Confirmation Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Status</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Unsettled Payments Tab -->
                <div class="tab-pane fade" id="unsettled" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div id="unsettledSummary" class="text-muted" style="font-size: 0.8rem;">
                            <!-- Unsettled summary will be populated here -->
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info btn-sm" onclick="refreshUnsettledData()">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="unsettledPaymentsTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Sr.No</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Beneficiary</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Instructed Amount</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Reason</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Status</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Created Date</th>
                                    <th
                                        style="font-size: 10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    @include('finance.partials.unsettled-details-modal')
    {{-- confirmed-payment-details-modal removed: showConfirmedPaymentDetails() creates it dynamically --}}
    @include('finance.partials.details-modal')
    @include('finance.partials.finance-approval-modal')
    @include('finance.partials.import-modal')

    <!-- Summary Cards (optional) -->
    @include('finance.partials.summary-cards')
@endsection

@push('scripts')
    <script>
        // ==========================================
        // GLOBAL VARIABLES
        // ==========================================
        const empId = {{ auth()->id() }};
        const yearId = {{ session('year_id', date('Y')) }};
        const comId = {{ session('company_id', 1) }};
        const API_BASE_URL = "{{ url('api') }}"; // runs in the browser: same host as the page (API_NEW_BASE_URL may be a server-internal address)
        const API_LOCAL_BASE_URL = "{{ url('api') }}";
        // const apiBaseUrl = 'https://expensenew.vnrseeds.co.in/api/advance/finance';
        const apiBaseUrl = `${API_BASE_URL}/advance/finance`;

        // Helper: Render employee cell with deactive highlighting
        function renderEmployeeCell(row) {
            const empName = row.employee_name || 'Unknown';
            const empCode = row.employee_code || '-';
            const department = row.department || '-';
            const isDeactive = row.is_active === false || row.emp_status === 'D';
            const badge = isDeactive ? '<span class="deactive-badge">DEACTIVE</span>' : '';
            const cls = isDeactive ? 'employee-deactive' : '';
            return '<div class="compact-row ' + cls + '" style="padding:4px;">' +
                '<div class="employee-info">' + empName + badge + '</div>' +
                '<small class="text-muted">' + empCode + '</small>' +
                '<small class="text-muted d-block">' + department + '</small>' +
                '</div>';
        }


        let pendingTable, approvedTable, rejectedTable, paymentTable, confirmedPaymentsTable, unsettledPaymentsTable;
        let pendingRequests = [];
        let approvedRequests = [];
        let rejectedRequests = [];
        let paymentRequests = [];
        let confirmedPayments = [];
        let unsettledPayments = [];

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
            window.scrollTo(0, 0);
        }

        function hideMessage() {
            $("#Message").hide();
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
            if (!status) return 'status-pending';
            switch (status.toString().toLowerCase()) {
                case 'pending':
                    return 'status-pending';
                case 'approved':
                    return 'status-approved';
                case 'rejected':
                    return 'status-rejected';
                case 'ready for payment':
                    return 'status-ready';
                case 'payment processed':
                    return 'status-processed';
                default:
                    return 'status-pending';
            }
        }

        function getMonthName(monthNumber) {
            if (!monthNumber || monthNumber < 1 || monthNumber > 12) {
                return 'Unknown';
            }
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months[monthNumber - 1] || 'Unknown';
        }

        // ==========================================
        // API CALLS
        // ==========================================
        function fetchPendingRequests() {
            showLoading();
            $.ajax({
                url: apiBaseUrl + '/pending',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { user_id: empId, year_id: yearId },
                success: function (response) {
                    hideLoading();
                    if (response.success && response.data) {
                        pendingRequests = response.data.filter(request => {
                            if (request.activities && request.activities.length > 0) {
                                return request.activities.some(activity =>
                                    activity.approval_status &&
                                    activity.approval_status.toLowerCase() === 'approved' &&
                                    (!activity.finance_status || activity.finance_status === 'Pending')
                                );
                            }
                            return false;
                        });
                        initializePendingTable();
                        updateTabCounts();
                        rebuildCurrentFilters();
                    } else {
                        pendingRequests = [];
                        initializePendingTable();
                        updateTabCounts();
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();
                    console.error('Error fetching pending finance requests:', error);
                    showMessage('Error fetching pending finance requests: ' + error);
                    pendingRequests = [];
                    initializePendingTable();
                    updateTabCounts();
                }
            });
        }

        function fetchApprovedRequests() {
            $.ajax({
                url: apiBaseUrl + '/approved',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { user_id: empId, year_id: yearId },
                success: function (response) {
                    if (response.success && response.data) {
                        approvedRequests = response.data;
                        initializeApprovedTable();
                        updateTabCounts();
                        rebuildCurrentFilters();
                    } else {
                        approvedRequests = [];
                        initializeApprovedTable();
                        updateTabCounts();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching approved finance requests:', error);
                    approvedRequests = [];
                    initializeApprovedTable();
                    updateTabCounts();
                }
            });
        }

        function fetchRejectedRequests() {
            $.ajax({
                url: apiBaseUrl + '/rejected',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { user_id: empId, year_id: yearId },
                success: function (response) {
                    if (response.success && response.data) {
                        rejectedRequests = response.data;
                        initializeRejectedTable();
                        rebuildCurrentFilters();
                    } else {
                        rejectedRequests = [];
                        initializeRejectedTable();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching rejected finance requests:', error);
                    rejectedRequests = [];
                    initializeRejectedTable();
                }
            });
        }

        function fetchPaymentRequests() {
            $.ajax({
                url: apiBaseUrl + '/released-payments',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { year_id: yearId, status: 'Ready for Payment', limit: 100 },
                success: function (response) {
                    if (response.success && response.data && response.data.employees) {
                        paymentRequests = response.data.employees.map(employee => ({
                            id: employee.id,
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
                        rebuildCurrentFilters();
                    } else {
                        paymentRequests = [];
                        initializePaymentTable();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching payment requests:', error);
                    paymentRequests = [];
                    initializePaymentTable();
                }
            });
        }

        function fetchConfirmedPayments() {
            $.ajax({
                url: apiBaseUrl + '/confirmed-payments',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { year_id: yearId, limit: 100 },
                success: function (response) {
                    let payments = [];
                    if (response.success && response.data && response.data.employees) {
                        response.data.employees.forEach(employee => {
                            if (employee.activities && Array.isArray(employee.activities)) {
                                employee.activities.forEach(activity => {
                                    if (activity.payment_status === 'Paid') {
                                        payments.push({
                                            activity_id: activity.id,
                                            advance_request_id: employee.advance_request_id,
                                            employee_id: employee.employee_id,
                                            employee_name: employee.employee_name,
                                            employee_code: employee.employee_code,
                                            employee_email: employee.employee_email,
                                            department: employee.department,
                                            designation: employee.designation,
                                            advance_type_name: employee.req_data ? employee.req_data.advance_type_name : 'Unknown',
                                            activity_type_name: activity.activity_type_name,
                                            requested_amount: activity.amount,
                                            finance_approved_amount: activity.finance_approved_amount,
                                            payment_amount: activity.payment_amount,
                                            payment_date: activity.payment_date,
                                            payment_remark: activity.payment_remark,
                                            payment_status: activity.payment_status,
                                            request_status: employee.req_data ? employee.req_data.current_status : 'Unknown',
                                            submission_no: employee.req_data ? employee.req_data.submission_no : null,
                                            advance_fill_date: employee.req_data ? employee.req_data.advance_fill_date : null,
                                            created_at: activity.created_at,
                                            updated_at: activity.updated_at,
                                            batch_id: activity.payment_confirmation_details ? activity.payment_confirmation_details.batch_id : null,
                                            confirmation_date: activity.payment_confirmation_details ? activity.payment_confirmation_details.confirmed_date : null,
                                            crop_names: activity.crop_names ? activity.crop_names.join(', ') : '',
                                            variety_names: activity.variety_names ? activity.variety_names.join(', ') : '',
                                            purpose: activity.purpose || '',
                                            month: employee.req_data ? employee.req_data.month : null,
                                            expense_submissions: employee.expense_submissions || [],
                                            ledger_balance: employee.ledger_balance || {},
                                            req_data: employee.req_data || {},
                                            approvals: activity.approvals || [],
                                            digi_next_payment_details: activity.digi_next_payment_details || null
                                        });
                                    }
                                });
                            }
                        });
                    }
                    confirmedPayments = payments;
                    setTimeout(() => {
                        initializeConfirmedPaymentsTable();
                        updateConfirmedSummary();
                        updateTabCounts();
                        rebuildCurrentFilters();
                    }, 100);
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching confirmed payments:', error);
                    confirmedPayments = [];
                    initializeConfirmedPaymentsTable();
                    updateConfirmedSummary();
                }
            });
        }

        function fetchUnsettledPayments() {
            $.ajax({
                url: apiBaseUrl + '/unsettled-payments',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                data: { year_id: yearId, limit: 100 },
                success: function (response) {
                    let payments = [];
                    if (response.success) {
                        if (response.data && response.data.unsettled_payments) {
                            payments = response.data.unsettled_payments;
                        } else if (response.data && Array.isArray(response.data)) {
                            payments = response.data;
                        } else if (response.unsettled_payments) {
                            payments = response.unsettled_payments;
                        }
                    }
                    unsettledPayments = payments;
                    initializeUnsettledPaymentsTable();
                    updateUnsettledSummary(response.data);
                    rebuildCurrentFilters();
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching unsettled payments:', error);
                    unsettledPayments = [];
                    initializeUnsettledPaymentsTable();
                    updateUnsettledSummary(null);
                }
            });
        }

        // ==========================================
        // DATATABLE FUNCTIONS
        // ==========================================
        function initializePendingTable() {
            if (pendingTable) { pendingTable.destroy(); }

            pendingTable = $('#pendingTable').DataTable({
                data: pendingRequests,
                responsive: true,
                pageLength: 25,
                order: [[8, 'desc']],

                columns: [
                    {
                        title: 'Sr.No', data: null, orderable: true, searchable: false, width: '40px',
                        render: function (data, type, row, meta) { return meta.row + 1; }
                    },
                    {
                        title: 'Employee', data: null, width: '120px',
                        render: function (data, type, row) {
                            return renderEmployeeCell(row);
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
                        title: 'Crops', data: null, width: '120px',
                        render: function (data, type, row) {
                            let cropsHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const allCrops = [];
                                row.activities.forEach(activity => {
                                    if (activity.crop_names) { allCrops.push(...activity.crop_names); }
                                });
                                const uniqueCrops = [...new Set(allCrops)];
                                cropsHtml = uniqueCrops.slice(0, 3).map(function (crop) {
                                    return '<span class="crop-tag">' + crop + '</span>';
                                }).join('');
                                if (uniqueCrops.length > 3) {
                                    cropsHtml += '<span class="crop-tag">+' + (uniqueCrops.length - 3) + '</span>';
                                }
                            }
                            return cropsHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Varieties', data: null, width: '120px',
                        render: function (data, type, row) {
                            let varietiesHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const allVarieties = [];
                                row.activities.forEach(activity => {
                                    if (activity.variety_names) { allVarieties.push(...activity.variety_names); }
                                });
                                const uniqueVarieties = [...new Set(allVarieties)];
                                varietiesHtml = uniqueVarieties.slice(0, 2).map(function (variety) {
                                    return '<span class="variety-tag">' + variety + '</span>';
                                }).join('');
                                if (uniqueVarieties.length > 2) {
                                    varietiesHtml += '<span class="variety-tag">+' + (uniqueVarieties.length - 2) + '</span>';
                                }
                            }
                            return varietiesHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Purpose', data: null, width: '150px',
                        render: function (data, type, row) {
                            let purposeHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const purposes = row.activities.map(activity => activity.purpose).filter(p => p);
                                if (purposes.length > 0) {
                                    const allPurposes = purposes.join('; ');
                                    const truncated = allPurposes.length > 60 ? allPurposes.substring(0, 60) + '...' : allPurposes;
                                    purposeHtml = '<span title="' + allPurposes + '" style="font-size: 0.7rem;">' + truncated + '</span>';
                                }
                            }
                            return purposeHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Amount', data: null, width: '80px',
                        render: function (data, type, row) {
                            let amount = 0;
                            let asked = 0;
                            if (row.activities && row.activities.length > 0) {
                                amount = row.activities.reduce((sum, activity) => sum + currentAdvanceAmount(activity), 0);
                                asked = row.activities.reduce((sum, activity) => sum + (parseFloat(activity.amount) || 0), 0);
                            }
                            if (amount === 0) {
                                amount = row.total_approved_amount || row.total_pending_amount || row.total_amount || 0;
                            }
                            // ADV-AMT-01: show the approved amount; the amount asked for only when it differs
                            return '<div class="amount-display">' + formatAmount(amount) + '</div>' +
                                (asked && Math.abs(asked - amount) > 0.009 ? '<div class="text-muted" style="font-size:0.6rem;"><s>' + formatAmount(asked) + '</s> asked</div>' : '');
                        }
                    },
                    {
                        title: 'Date',   // 👈 keep same name
                        data: null,
                        width: '80px',
                        render: function (data, type, row) {

                            let approvedDate = null;

                            if (row.activities && row.activities.length > 0) {
                                row.activities.forEach(activity => {
                                    if (activity.approvals) {
                                        activity.approvals.forEach(a => {
                                            if (a.approver_date) {
                                                approvedDate = a.approver_date;
                                            }
                                        });
                                    }
                                });
                            }

                            return '<span style="font-size: 0.7rem;">' +
                                (approvedDate ? formatDate(approvedDate) : '-') +
                                '</span>';
                        }
                    },
                    {
                        title: 'Actions', data: null, orderable: false, width: '120px',
                        render: function (data, type, row) {
                            return '<div style="display: flex;">' +
                                '<button class="btn btn-approve me-1" onclick="showFinanceApprovalForm(' + row.id + ', \'approve\')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-check"></i> Approve' +
                                '</button>' +
                                '<button class="btn btn-reject me-1" onclick="showFinanceApprovalForm(' + row.id + ', \'reject\')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-times"></i> Reject' +
                                '</button>' +
                                '<button class="btn btn-detail" onclick="showDetails(' + row.id + ', \'pending\')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-eye"></i> View' +
                                '</button>' +
                                '</div>';
                        }
                    }
                ],
                language: {
                    emptyTable: "No pending finance approvals found",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                    search: "Search requests:"
                }
            });
        }

        function initializeApprovedTable() {
            if (approvedTable) { approvedTable.destroy(); }

            approvedTable = $('#approvedTable').DataTable({
                data: approvedRequests,
                responsive: true,
                pageLength: 25,
                order: [[9, 'desc']],
                columns: [
                    {
                        title: 'Select', data: null, orderable: false, searchable: false, width: '40px',
                        render: function (data, type, row, meta) {
                            return '<input type="checkbox" class="form-check-input approved-checkbox" value="' + row.id + '" onchange="updateReleasePaymentButton()">';
                        }
                    },
                    {
                        title: 'Sr.No', data: null, orderable: false, searchable: false, width: '40px',
                        render: function (data, type, row, meta) { return meta.row + 1; }
                    },
                    {
                        title: 'Employee', data: null, width: '120px',
                        render: function (data, type, row) {
                            return renderEmployeeCell(row);
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
                        title: 'Crops', data: null, width: '120px',
                        render: function (data, type, row) {
                            let cropsHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const allCrops = [];
                                row.activities.forEach(activity => {
                                    if (activity.crop_names) { allCrops.push(...activity.crop_names); }
                                });
                                const uniqueCrops = [...new Set(allCrops)];
                                cropsHtml = uniqueCrops.slice(0, 3).map(function (crop) {
                                    return '<span class="crop-tag">' + crop + '</span>';
                                }).join('');
                                if (uniqueCrops.length > 3) {
                                    cropsHtml += '<span class="crop-tag">+' + (uniqueCrops.length - 3) + '</span>';
                                }
                            }
                            return cropsHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Varieties', data: null, width: '120px',
                        render: function (data, type, row) {
                            let varietiesHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const allVarieties = [];
                                row.activities.forEach(activity => {
                                    if (activity.variety_names) { allVarieties.push(...activity.variety_names); }
                                });
                                const uniqueVarieties = [...new Set(allVarieties)];
                                varietiesHtml = uniqueVarieties.slice(0, 2).map(function (variety) {
                                    return '<span class="variety-tag">' + variety + '</span>';
                                }).join('');
                                if (uniqueVarieties.length > 2) {
                                    varietiesHtml += '<span class="variety-tag">+' + (uniqueVarieties.length - 2) + '</span>';
                                }
                            }
                            return varietiesHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Purpose', data: null, width: '150px',
                        render: function (data, type, row) {
                            let purposeHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const purposes = row.activities.map(activity => activity.purpose).filter(p => p);
                                if (purposes.length > 0) {
                                    const allPurposes = purposes.join('; ');
                                    const truncated = allPurposes.length > 60 ? allPurposes.substring(0, 60) + '...' : allPurposes;
                                    purposeHtml = '<span title="' + allPurposes + '" style="font-size: 0.7rem;">' + truncated + '</span>';
                                }
                            }
                            return purposeHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Amount', data: null, width: '80px',
                        render: function (data, type, row) {
                            let amount = 0;
                            if (row.activities && row.activities.length > 0) {
                                amount = row.activities.reduce((sum, activity) => {
                                    return sum + (parseFloat(activity.finance_approved_amount) || 0);
                                }, 0);
                            }
                            if (amount === 0) {
                                amount = row.total_approved_amount || row.total_pending_amount || row.total_amount || 0;
                            }
                            return '<div class="amount-display">' + formatAmount(amount) + '</div>';
                        }
                    },
                    {
                        title: 'Approved Date', data: 'updated_at', width: '80px',
                        render: function (data, type, row) {
                            return '<span style="font-size: 0.7rem;">' + (data ? formatDate(data) : '-') + '</span>';
                        }
                    },
                    {
                        title: 'Action', data: null, orderable: false, width: '60px',
                        render: function (data, type, row) {
                            return '<button class="btn btn-detail" onclick="showDetails(' + row.id + ', \'approved\')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-eye"></i> View' +
                                '</button>';
                        }
                    }
                ],
                language: {
                    emptyTable: "No approved finance requests found",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                    search: "Search requests:"
                }
            });
        }

        function initializeRejectedTable() {
            if (rejectedTable) { rejectedTable.destroy(); }

            rejectedTable = $('#rejectedTable').DataTable({
                data: rejectedRequests,
                responsive: true,
                pageLength: 25,
                order: [[8, 'desc']],
                columns: [
                    {
                        title: 'Sr.No', data: null, orderable: false, searchable: false, width: '40px',
                        render: function (data, type, row, meta) { return meta.row + 1; }
                    },
                    {
                        title: 'Employee', data: null, width: '120px',
                        render: function (data, type, row) {
                            return renderEmployeeCell(row);
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
                        title: 'Crops', data: null, width: '120px',
                        render: function (data, type, row) {
                            let cropsHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const allCrops = [];
                                row.activities.forEach(activity => {
                                    if (activity.crop_names) { allCrops.push(...activity.crop_names); }
                                });
                                const uniqueCrops = [...new Set(allCrops)];
                                cropsHtml = uniqueCrops.slice(0, 3).map(function (crop) {
                                    return '<span class="crop-tag">' + crop + '</span>';
                                }).join('');
                                if (uniqueCrops.length > 3) {
                                    cropsHtml += '<span class="crop-tag">+' + (uniqueCrops.length - 3) + '</span>';
                                }
                            }
                            return cropsHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Varieties', data: null, width: '120px',
                        render: function (data, type, row) {
                            let varietiesHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const allVarieties = [];
                                row.activities.forEach(activity => {
                                    if (activity.variety_names) { allVarieties.push(...activity.variety_names); }
                                });
                                const uniqueVarieties = [...new Set(allVarieties)];
                                varietiesHtml = uniqueVarieties.slice(0, 2).map(function (variety) {
                                    return '<span class="variety-tag">' + variety + '</span>';
                                }).join('');
                                if (uniqueVarieties.length > 2) {
                                    varietiesHtml += '<span class="variety-tag">+' + (uniqueVarieties.length - 2) + '</span>';
                                }
                            }
                            return varietiesHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Purpose', data: null, width: '150px',
                        render: function (data, type, row) {
                            let purposeHtml = '';
                            if (row.activities && row.activities.length > 0) {
                                const purposes = row.activities.map(activity => activity.purpose).filter(p => p);
                                if (purposes.length > 0) {
                                    const allPurposes = purposes.join('; ');
                                    const truncated = allPurposes.length > 60 ? allPurposes.substring(0, 60) + '...' : allPurposes;
                                    purposeHtml = '<span title="' + allPurposes + '" style="font-size: 0.7rem;">' + truncated + '</span>';
                                }
                            }
                            return purposeHtml || '<span class="text-muted">-</span>';
                        }
                    },
                    {
                        title: 'Amount', data: null, width: '80px',
                        render: function (data, type, row) {
                            let amount = 0;
                            let asked = 0;
                            if (row.activities && row.activities.length > 0) {
                                amount = row.activities.reduce((sum, activity) => sum + currentAdvanceAmount(activity), 0);
                                asked = row.activities.reduce((sum, activity) => sum + (parseFloat(activity.amount) || 0), 0);
                            }
                            if (amount === 0) {
                                amount = row.total_approved_amount || row.total_pending_amount || row.total_amount || 0;
                            }
                            // ADV-AMT-01: show the approved amount; the amount asked for only when it differs
                            return '<div class="amount-display">' + formatAmount(amount) + '</div>' +
                                (asked && Math.abs(asked - amount) > 0.009 ? '<div class="text-muted" style="font-size:0.6rem;"><s>' + formatAmount(asked) + '</s> asked</div>' : '');
                        }
                    },
                    {
                        title: 'Rejected Date', data: 'updated_at', width: '80px',
                        render: function (data, type, row) {
                            return '<span style="font-size: 0.7rem;">' + (data ? formatDate(data) : '-') + '</span>';
                        }
                    },
                    {
                        title: 'Action', data: null, orderable: false, width: '60px',
                        render: function (data, type, row) {
                            return '<button class="btn btn-detail" onclick="showDetails(' + row.id + ', \'rejected\')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-eye"></i> View' +
                                '</button>';
                        }
                    }
                ],
                language: {
                    emptyTable: "No rejected finance requests found",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                    search: "Search requests:"
                }
            });
        }

        function initializePaymentTable() {
            if (paymentTable) { paymentTable.destroy(); }

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
                            return renderEmployeeCell(row);
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
                                amount = row.activities.reduce((sum, activity) => sum + currentAdvanceAmount(activity), 0); // ADV-AMT-01
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
                            return '<button class="btn btn-detail" onclick="showDetails(' + row.id + ', \'payment\')" style="font-size: 0.6rem; padding: 1px 4px;">' +
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

            setTimeout(() => { updatePaymentSummary(); }, 100);
        }

        function initializeConfirmedPaymentsTable() {
            if (confirmedPaymentsTable) { confirmedPaymentsTable.destroy(); }

            confirmedPaymentsTable = $('#confirmedPaymentsTable').DataTable({
                data: confirmedPayments,
                responsive: true,
                pageLength: 25,
                order: [[8, 'desc']],
                columns: [
                    {
                        title: 'Sr.No', data: null, orderable: false, searchable: false, width: '40px',
                        render: function (data, type, row, meta) { return meta.row + 1; }
                    },
                    {
                        title: 'Employee', data: null, width: '120px',
                        render: function (data, type, row) {
                            return renderEmployeeCell(row);
                        }
                    },
                    {
                        title: 'Activity', data: null, width: '180px',
                        render: function (data, type, row) {
                            const activityName = row.activity_type_name || 'Payment Confirmation';
                            const advanceTypeName = row.advance_type_name || '';
                            const crops = row.crop_names || '';
                            const varieties = row.variety_names || '';
                            const purpose = row.purpose || '';

                            let html = '<div class="compact-row">' +
                                '<div style="font-weight: 600; font-size: 0.75rem;">' + activityName + '</div>';

                            if (advanceTypeName) {
                                html += '<small class="text-muted d-block">' + advanceTypeName + '</small>';
                            }

                            if (crops) {
                                html += '<div style="margin-top: 2px;">';
                                crops.split(', ').forEach(crop => {
                                    html += '<span class="crop-tag">' + crop + '</span>';
                                });
                                html += '</div>';
                            }

                            if (varieties) {
                                html += '<div style="margin-top: 2px;">';
                                varieties.split(', ').forEach(variety => {
                                    html += '<span class="variety-tag">' + variety + '</span>';
                                });
                                html += '</div>';
                            }

                            if (purpose && purpose.length > 0) {
                                html += '<small class="text-muted d-block" style="font-style: italic; margin-top: 2px;">' +
                                    (purpose.length > 30 ? purpose.substring(0, 30) + '...' : purpose) + '</small>';
                            }

                            html += '</div>';
                            return html;
                        }
                    },
                    {
                        title: 'Month', data: null, width: '60px',
                        render: function (data, type, row) {
                            if (row.month && row.month > 0) {
                                return getMonthName(row.month);
                            } else if (row.advance_fill_date) {
                                const date = new Date(row.advance_fill_date);
                                return getMonthName(date.getMonth() + 1);
                            } else if (row.payment_date) {
                                const date = new Date(row.payment_date);
                                return getMonthName(date.getMonth() + 1);
                            } else if (row.created_at) {
                                const date = new Date(row.created_at);
                                return getMonthName(date.getMonth() + 1);
                            }
                            return '-';
                        }
                    },
                    {
                        title: 'Amount', data: 'payment_amount', width: '80px',
                        render: function (data, type, row) {
                            return '<div class="amount-display">' + formatAmount(data || 0) + '</div>';
                        }
                    },
                    {
                        title: 'Transaction Ref', data: null, width: '120px',
                        render: function (data, type, row) {
                            let transactionRef = '-';
                            if (row.payment_remark && row.payment_remark.includes('Ref:')) {
                                const match = row.payment_remark.match(/Ref:\s*([^\s,]+)/);
                                if (match) { transactionRef = match[1]; }
                            }
                            return '<div style="font-size: 0.7rem;">' +
                                '<div><strong>Ref:</strong> ' + transactionRef + '</div>' +
                                '</div>';
                        }
                    },
                    {
                        title: 'Payment Date', data: 'payment_date', width: '80px',
                        render: function (data, type, row) {
                            return '<span style="font-size: 0.7rem;">' + (data ? formatDate(data) : '-') + '</span>';
                        }
                    },
                    {
                        title: 'Confirmation Date', data: null, width: '80px',
                        render: function (data, type, row) {
                            const confirmDate = row.confirmation_date || row.updated_at;
                            return '<span style="font-size: 0.7rem;">' + (confirmDate ? formatDate(confirmDate) : '-') + '</span>';
                        }
                    },
                    {
                        title: 'Status', data: 'payment_status', width: '100px',
                        render: function (data, type, row) {
                            const status = data || 'Unknown';
                            const statusClass = status.toLowerCase().includes('paid') ? 'status-approved' :
                                status.toLowerCase().includes('reject') ? 'status-rejected' : 'status-pending';
                            return '<span class="status-badge ' + statusClass + '">' + status + '</span>';
                        }
                    },
                    {
                        title: 'Action', data: null, orderable: false, width: '60px',
                        render: function (data, type, row, meta) {
                            return '<button class="btn btn-detail" onclick="showConfirmedPaymentByIndex(' + meta.row + ')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-eye"></i> View' +
                                '</button>';
                        }
                    }
                ],
                language: {
                    emptyTable: "No confirmed payments found",
                    info: "Showing _START_ to _END_ of _TOTAL_ payments",
                    search: "Search payments:"
                }
            });

            updateConfirmedSummary();
        }

        function initializeUnsettledPaymentsTable() {
            if (unsettledPaymentsTable) { unsettledPaymentsTable.destroy(); }

            unsettledPaymentsTable = $('#unsettledPaymentsTable').DataTable({
                data: unsettledPayments,
                responsive: true,
                pageLength: 25,
                order: [[5, 'desc']],
                columns: [
                    {
                        title: 'Sr.No', data: null, orderable: false, searchable: false, width: '40px',
                        render: function (data, type, row, meta) { return meta.row + 1; }
                    },
                    {
                        title: 'Employee', data: 'activity_info', width: '150px',
                        render: function (data, type, row) {
                            const info = data || {};
                            const name = info.employee_name || 'Unknown';
                            const code = info.employee_code || '';
                            const account = info.hrims_account_no || row.account_no || '';
                            return '<div class="compact-row">' +
                                '<div class="employee-info">' + name + (code ? ' (' + code + ')' : '') + '</div>' +
                                (account ? '<small class="text-muted d-block">A/c: ' + account + '</small>' : '') +
                                '</div>';
                        }
                    },
                    {
                        title: 'Amount', data: 'approved_amount', width: '100px',
                        render: function (data, type, row) {
                            return '<div class="amount-display" style="color: #28a745;">' + formatAmount(data || 0) + '</div>';
                        }
                    },
                    {
                        title: 'Reason', data: 'unsettled_reason', width: '150px',
                        render: function (data, type, row) {
                            // Show only the first line of the reason (strip retry lines)
                            const firstLine = (data || 'No reason specified').split('\n')[0];
                            const truncated = firstLine.length > 60 ? firstLine.substring(0, 60) + '...' : firstLine;
                            return '<span title="' + (data || '') + '" style="font-size: 0.7rem;">' + truncated + '</span>';
                        }
                    },
                    {
                        title: 'Status', data: 'payment_status', width: '100px',
                        render: function (data, type, row) {
                            const status = data || 'Unknown';
                            const statusClass = status === 'Paid' ? 'status-approved' :
                                status === 'Unsettled' ? 'status-rejected' : 'status-pending';
                            return '<span class="status-badge ' + statusClass + '">' + status + '</span>';
                        }
                    },
                    {
                        title: 'Finance Date', data: 'finance_date', width: '100px',
                        render: function (data, type, row) {
                            return '<span style="font-size: 0.7rem;">' + (data ? formatDate(data) : '-') + '</span>';
                        }
                    },
                    {
                        title: 'Action', data: null, orderable: false, width: '80px',
                        render: function (data, type, row) {
                            return '<div style="display: flex; gap: 2px;">' +
                                '<button class="btn btn-detail" onclick="showUnsettledPaymentDetails(' + (row.activity_id || 0) + ')" style="font-size: 0.6rem; padding: 1px 4px;">' +
                                '<i class="fa fa-eye"></i> View' +
                                '</button>' +
                                '</div>';
                        }
                    }
                ],
                language: {
                    emptyTable: "No unsettled payments found",
                    info: "Showing _START_ to _END_ of _TOTAL_ discrepancies",
                    search: "Search discrepancies:"
                }
            });
        }

        function updateUnsettledSummary(data) {
            const summaryDiv = document.getElementById('unsettledSummary');
            if (data && unsettledPayments.length > 0) {
                const totalCount = data.total_count || unsettledPayments.length;
                const totalExcelAmount = data.total_excel_amount || 0;
                const totalDbAmount = data.total_db_amount || 0;
                const totalDifference = data.total_difference || 0;
                summaryDiv.innerHTML = `
                            <i class="fa fa-info-circle"></i>
                            <strong>${totalCount}</strong> unsettled payment(s) |
                            Excel: <strong>${formatAmount(totalExcelAmount)}</strong> |
                            DB: <strong>${formatAmount(totalDbAmount)}</strong> |
                            Diff: <strong class="${totalDifference > 0 ? 'text-danger' : totalDifference < 0 ? 'text-warning' : ''}">${formatAmount(Math.abs(totalDifference))}</strong>
                        `;
            } else {
                summaryDiv.innerHTML = '<i class="fa fa-info-circle"></i> No unsettled payments found';
            }
        }

        function updatePaymentSummary() {
            const summaryDiv = document.getElementById('paymentSummary');
            if (summaryDiv && paymentRequests.length > 0) {
                const totalAmount = paymentRequests.reduce((sum, req) => {
                    if (req.activities && req.activities.length > 0) {
                        return sum + req.activities.reduce((actSum, activity) => {
                            return actSum + (parseFloat(activity.payment_amount) || parseFloat(activity.finance_approved_amount) || 0);
                        }, 0);
                    }
                    return sum + (parseFloat(req.total_approved_amount) || parseFloat(req.total_amount) || 0);
                }, 0);

                summaryDiv.innerHTML =
                    '<i class="fa fa-info-circle"></i> ' + paymentRequests.length + ' payment(s) ready | Total: ' + formatAmount(totalAmount);
            } else {
                summaryDiv.innerHTML = '<i class="fa fa-info-circle"></i> No payments ready for processing';
            }
        }

        function updateConfirmedSummary() {
            const summaryDiv = document.getElementById('confirmedSummary');
            if (confirmedPayments.length > 0) {
                const totalAmount = confirmedPayments.reduce((sum, payment) => {
                    return sum + (parseFloat(payment.payment_amount) || 0);
                }, 0);

                const totalRequested = confirmedPayments.reduce((sum, payment) => {
                    return sum + (parseFloat(payment.requested_amount) || 0);
                }, 0);

                summaryDiv.innerHTML = `
                            <i class="fa fa-check-circle-o text-success"></i>
                            <strong>${confirmedPayments.length}</strong> confirmed payment(s) |
                            Paid: <strong>${formatAmount(totalAmount)}</strong> |
                            Requested: <strong>${formatAmount(totalRequested)}</strong>
                        `;
            } else {
                summaryDiv.innerHTML = '<i class="fa fa-info-circle"></i> No confirmed payments found';
            }
        }

        function refreshPaymentData() {
            showMessage('Refreshing payment data...', 'info');
            fetchPaymentRequests();
            updateTabCounts();
        }

        function refreshConfirmedData() {
            showMessage('Refreshing confirmed payments...', 'info');
            fetchConfirmedPayments();
            updateTabCounts();
        }

        function refreshUnsettledData() {
            showMessage('Refreshing unsettled payments...', 'info');
            fetchUnsettledPayments();
            updateTabCounts();
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
                    console.log('Export API Response:', response);

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

                            if (response.data.excluded_count) {
                                detailedMessage += `<br><div style="margin-top: 10px; padding: 10px; background-color: #fff3cd; border-left: 4px solid #ffc107;">`;
                                detailedMessage += `<strong>Total Excluded:</strong> ${response.data.excluded_count} activities, <strong>₹${response.data.total_excluded_amount.toLocaleString('en-IN')}</strong>`;
                                detailedMessage += `</div>`;
                            }
                        }

                        if (response.suggestion) {
                            detailedMessage += '<br><div style="margin-top: 10px; padding: 10px; background-color: #d1ecf1; border-left: 4px solid #17a2b8;">';
                            detailedMessage += '<em><i class="fa fa-info-circle"></i> ' + response.suggestion + '</em>';
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
                    console.log('Export API Error:', xhr.responseText);

                    try {
                        const response = JSON.parse(xhr.responseText);
                        let detailedMessage = response.message || 'Error checking export data';

                        if (response.data && response.data.excluded_activities && response.data.excluded_activities.length > 0) {
                            detailedMessage += '<br><br><strong>Excluded:</strong><br>';
                            detailedMessage += '<div style="margin-left: 20px; margin-top: 10px;">';
                            response.data.excluded_activities.forEach((activity, index) => {
                                detailedMessage += `<div style="margin-bottom: 8px;">`;
                                detailedMessage += `${index + 1}. <strong>${activity.employee_name}</strong> (${activity.employee_code}) - <strong>₹${activity.amount.toLocaleString('en-IN')}</strong><br>`;
                                detailedMessage += `</div>`;
                            });
                            detailedMessage += '</div>';

                            if (response.data.excluded_count) {
                                detailedMessage += `<br><div style="margin-top: 10px; padding: 10px; background-color: #fff3cd; border-left: 4px solid #ffc107;">`;
                                detailedMessage += `<strong>Total Excluded:</strong> ${response.data.excluded_count} activities, <strong>₹${response.data.total_excluded_amount.toLocaleString('en-IN')}</strong>`;
                                detailedMessage += `</div>`;
                            }
                        }

                        if (response.suggestion) {
                            detailedMessage += '<br><div style="margin-top: 10px; padding: 10px; background-color: #d1ecf1; border-left: 4px solid #17a2b8;">';
                            detailedMessage += '<em><i class="fa fa-info-circle"></i> ' + response.suggestion + '</em>';
                            detailedMessage += '</div>';
                        }

                        showMessage(detailedMessage, 'warning');
                    } catch (e) {
                        showMessage('Error checking export data: ' + error, 'danger');
                    }
                }
            });
        }

        // ==========================================
        // DETAIL VIEW FUNCTIONS
        // ==========================================
        function showDetails(requestId, type) {
            let request;

            switch (type) {
                case 'pending':
                    request = pendingRequests.find(req => req.id === requestId);
                    break;
                case 'approved':
                    request = approvedRequests.find(req => req.id === requestId);
                    break;
                case 'rejected':
                    request = rejectedRequests.find(req => req.id === requestId);
                    break;
                case 'payment':
                    request = paymentRequests.find(req => req.id === requestId);
                    break;
                default:
                    request = null;
            }

            if (!request) {
                showMessage('Request not found', 'warning');
                return;
            }

            // Show details modal with request data
            $('#detailModal').remove();
            const modalHtml = generateDetailModal(request, type);
            $('body').append(modalHtml);
            $('#detailModal').modal('show');
        }

        function showFinanceApprovalForm(requestId, action) {
            const request = pendingRequests.find(req => req.id === requestId);
            if (!request) {
                showMessage('Request not found', 'warning');
                return;
            }

            $('#financeApprovalModal').remove();
            const modalHtml = generateFinanceApprovalModal(request, action);
            $('body').append(modalHtml);
            $('#financeApprovalModal').modal('show');

            if (action === 'approve') {
                updateFinanceAmountCounter(document.getElementById('financeApprovalAmount'));
            }
        }

        function showUnsettledPaymentDetails(activityId) {
            const payment = unsettledPayments.find(p => p.activity_id === activityId);
            if (!payment) {
                showMessage('Unsettled payment not found', 'warning');
                return;
            }

            const activityInfo = payment.activity_info || {};
            const employeeName = activityInfo.employee_name || 'Unknown';
            const employeeCode = activityInfo.employee_code || '';
            const accountNo    = activityInfo.hrims_account_no || payment.account_no || '';
            const reason       = payment.unsettled_reason || payment.payment_unsettled_reason || '';

            let paymentRecordsHtml = '';
            if (payment.all_employee_payments && payment.all_employee_payments.length > 0) {
                paymentRecordsHtml = payment.all_employee_payments.map((paymentRecord, index) => {
                    const isCurrent  = paymentRecord.is_current_activity;
                    const isPaid     = paymentRecord.payment_status === 'Paid';
                    const statusColor = isPaid ? '#28a745' : (isCurrent ? '#dc3545' : '#ffc107');

                    return `
                                <div class="payment-record-item mb-2 p-2"
                                     style="background-color: white; border-radius: 4px; border-left: 3px solid ${statusColor};">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.7rem; font-weight: 600; color: #155997;">
                                                Activity #${paymentRecord.activity_id}
                                                ${isCurrent ? '<span class="badge bg-danger ms-1" style="font-size:0.55rem;">Current</span>' : ''}
                                            </div>
                                            <div style="font-size: 0.65rem; color: #666;">
                                                Request #${paymentRecord.request_id || '-'}
                                            </div>
                                            ${paymentRecord.utr_number ? `
                                            <div style="font-size: 0.6rem; color: #17a2b8; margin-top: 2px;">
                                                UTR: ${paymentRecord.utr_number}
                                            </div>` : ''}
                                            ${paymentRecord.payment_remark ? `
                                            <div style="font-size: 0.6rem; color: #666; margin-top: 2px;">
                                                ${paymentRecord.payment_remark}
                                            </div>` : ''}
                                        </div>
                                        <div style="text-align: right;">
                                            <div class="amount-display" style="font-size: 0.75rem; color: #155997; font-weight: 600;">
                                                ${formatAmount(paymentRecord.amount)}
                                            </div>
                                            <div style="font-size: 0.6rem; color: #666; margin-top: 2px;">
                                                ${paymentRecord.payment_date ? formatDate(paymentRecord.payment_date) : 'Not paid'}
                                            </div>
                                            <span class="status-badge ${isPaid ? 'status-approved' : 'status-rejected'}" style="font-size: 0.55rem; padding: 1px 4px; margin-top: 4px; display: inline-block;">
                                                ${paymentRecord.payment_status}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                }).join('');
            } else {
                paymentRecordsHtml = '<div class="text-muted text-center py-3" style="font-size: 0.7rem;"><i class="fa fa-info-circle"></i> No payment history found for this employee</div>';
            }

            const modalHtml = `
                        <div class="modal fade" id="unsettledDetailsModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header" style="background-color: #dc3545; color: white;">
                                        <h5 class="modal-title text-white">
                                            <i class="fa fa-exclamation-triangle"></i> Payment Mismatch Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                                        <div id="unsettledModalMessage" class="alert" style="display: none; margin-bottom: 15px;"></div>

                                        <div class="row">
                                            <!-- Left Column - Discrepancy Details -->
                                            <div class="col-md-7">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <h6 class="text-primary mb-3" style="font-size: 0.85rem;"><i class="fa fa-file-text-o"></i> Transaction Information</h6>
                                                        <table class="table table-sm" style="font-size: 0.75rem;">
                                                            <tr><td><strong>Activity ID:</strong></td><td>#${payment.activity_id}</td></tr>
                                                            <tr><td><strong>Request ID:</strong></td><td>#${payment.request_id || '-'}</td></tr>
                                                            <tr><td><strong>Employee:</strong></td><td>${employeeName} ${employeeCode ? '(' + employeeCode + ')' : ''}</td></tr>
                                                            <tr><td><strong>Account No:</strong></td><td>${accountNo || '-'}</td></tr>
                                                            <tr><td><strong>Approved Amount:</strong></td><td>${formatAmount(payment.approved_amount || 0)}</td></tr>
                                                            <tr><td><strong>Finance Date:</strong></td><td>${payment.finance_date ? formatDate(payment.finance_date) : '-'}</td></tr>
                                                            <tr><td><strong>Released Date:</strong></td><td>${payment.payment_released_date ? formatDate(payment.payment_released_date) : '-'}</td></tr>
                                                            <tr><td><strong>Status:</strong></td><td><span class="status-badge ${payment.payment_status === 'Paid' ? 'status-approved' : 'status-rejected'}" style="font-size: 0.65rem;">${payment.payment_status || 'Unknown'}</span></td></tr>
                                                        </table>
                                                    </div>
                                                </div>

                                                ${reason ? `
                                                <div class="row mt-2">
                                                    <div class="col-12">
                                                        <h6 class="text-primary mb-2" style="font-size: 0.85rem;"><i class="fa fa-comment"></i> Reason for Discrepancy</h6>
                                                        <div class="alert alert-info" style="font-size: 0.7rem; padding: 8px;">
                                                            ${reason.split('\\n')[0]}
                                                        </div>
                                                    </div>
                                                </div>
                                                ` : ''}

                                                <!-- Manual Payment Section -->
                                                <div class="row mt-2">
                                                    <div class="col-12">
                                                        <div class="card" style="border: 1px solid #ffc107;">
                                                            <div class="card-header" style="background-color: #fff3cd; padding: 8px 12px;">
                                                                <h6 class="mb-0" style="font-size: 0.8rem;"><i class="fa fa-pencil-square-o"></i> Manual Payment</h6>
                                                            </div>
                                                            <div class="card-body" style="padding: 10px;">
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                                            Transaction Reference <span class="text-danger">*</span>
                                                                        </label>
                                                                        <input type="text" class="form-control form-control-sm" id="manualTransactionRef"
                                                                               placeholder="e.g., BT26013049926940" aria-label="e.g., BT26013049926940" value="${payment.transaction_refno || ''}"
                                                                               style="font-size: 0.75rem;" required>
                                                                    </div>
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                                            Payment Amount (₹) <span class="text-danger">*</span>
                                                                        </label>
                                                                        <input type="number" class="form-control form-control-sm" id="manualPaymentAmount"
                                                                               placeholder="e.g., 2000.00" aria-label="e.g., 2000.00" step="0.01" min="0" value="${payment.db_amount || ''}"
                                                                               style="font-size: 0.75rem;" required>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                                            Payment Date <span class="text-danger">*</span>
                                                                        </label>
                                                                        <input type="date" class="form-control form-control-sm" id="manualPaymentDate"
                                                                               value="${new Date().toISOString().split('T')[0]}"
                                                                               style="font-size: 0.75rem;" required>
                                                                    </div>
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                                            UTR Reference <span class="text-danger">*</span>
                                                                        </label>
                                                                        <input type="text" class="form-control form-control-sm" id="manualUtrRef"
                                                                               placeholder="e.g., BARBD26028317633" aria-label="e.g., BARBD26028317633" style="font-size: 0.75rem;" required>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-12 mb-2">
                                                                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                                            Payment Remark <span class="text-danger">*</span>
                                                                        </label>
                                                                        <input type="text" class="form-control form-control-sm" id="manualPaymentRemark"
                                                                               placeholder="e.g., Manual adjustment" aria-label="e.g., Manual adjustment" style="font-size: 0.75rem;" required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Right Column - Payment Records List -->
                                            <div class="col-md-5">
                                                <div class="card" style="border: 1px solid #dee2e6; height: 100%;">
                                                    <div class="card-header" style="background-color: #f8f9fa; padding: 8px 12px;">
                                                        <h6 class="mb-0" style="font-size: 0.8rem;">
                                                            <i class="fa fa-list-ul"></i> Available Payment Records
                                                            <span class="badge bg-primary" style="font-size: 0.65rem;">${payment.all_employee_payments_count || 0}</span>
                                                        </h6>
                                                    </div>
                                                    <div class="card-body" style="padding: 8px; max-height: 500px; overflow-y: auto;">
                                                        ${paymentRecordsHtml}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="resolveDiscrepancy(${payment.id}, ${activityId})">
                                            <i class="fa fa-check"></i> Mark as Resolved
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

            $('#unsettledDetailsModal').remove();
            $('body').append(modalHtml);
            $('#unsettledDetailsModal').modal('show');
        }

        function selectPaymentRecord(element) {
            const canSelect = element.getAttribute('data-can-select') === 'true';
            if (!canSelect) return;

            const transactionRef = element.getAttribute('data-transaction-ref') || '';
            const amount = element.getAttribute('data-amount') || '';
            const valueDate = element.getAttribute('data-value-date') || '';
            const utrRef = element.getAttribute('data-utr-ref') || '';

            $('#manualTransactionRef').val(transactionRef);
            $('#manualPaymentAmount').val(amount);

            if (valueDate) {
                try {
                    const date = new Date(valueDate);
                    const formattedDate = date.toISOString().split('T')[0];
                    $('#manualPaymentDate').val(formattedDate);
                } catch (e) {
                    console.error('Date formatting error:', e);
                }
            }

            $('#manualUtrRef').val(utrRef);
            $('#manualPaymentRemark').val('Payment matched from bank records');

            showUnsettledModalMessage('Payment details auto-filled. Review and click "Mark as Resolved" to confirm.', 'success');
        }

        function showUnsettledModalMessage(message, type = 'danger') {
            const messageDiv = document.getElementById('unsettledModalMessage');
            if (messageDiv) {
                messageDiv.className = 'alert alert-' + type;
                messageDiv.textContent = message;
                messageDiv.style.display = 'block';
                const modalBody = messageDiv.closest('.modal-body');
                if (modalBody) { modalBody.scrollTop = 0; }
                if (type !== 'success') {
                    setTimeout(() => { messageDiv.style.display = 'none'; }, 5000);
                }
            }
        }

        function resolveDiscrepancy(paymentId, activityId) {
            const manualTransactionRef = $('#manualTransactionRef').val().trim();
            const manualPaymentAmount = $('#manualPaymentAmount').val();
            const manualPaymentDate = $('#manualPaymentDate').val();
            const manualUtrRef = $('#manualUtrRef').val().trim();
            const manualPaymentRemark = $('#manualPaymentRemark').val().trim();

            if (!manualTransactionRef) {
                showUnsettledModalMessage('Transaction Reference is required', 'warning');
                $('#manualTransactionRef').focus();
                return;
            }

            if (!manualPaymentAmount || parseFloat(manualPaymentAmount) <= 0) {
                showUnsettledModalMessage('Valid Payment Amount is required', 'warning');
                $('#manualPaymentAmount').focus();
                return;
            }

            if (!manualPaymentDate) {
                showUnsettledModalMessage('Payment Date is required', 'warning');
                $('#manualPaymentDate').focus();
                return;
            }

            if (!manualUtrRef) {
                showUnsettledModalMessage('UTR Reference is required', 'warning');
                $('#manualUtrRef').focus();
                return;
            }

            if (!manualPaymentRemark) {
                showUnsettledModalMessage('Payment Remark is required', 'warning');
                $('#manualPaymentRemark').focus();
                return;
            }

            if (!confirm('Are you sure you want to resolve this Discrepancy with the provided payment details?')) {
                return;
            }

            showLoading();

            const payload = {
                payment_id: paymentId,
                activity_id: activityId,
                transaction_ref: manualTransactionRef,
                payment_amount: parseFloat(manualPaymentAmount),
                payment_date: manualPaymentDate,
                utr_reference: manualUtrRef,
                payment_remark: manualPaymentRemark,
                resolved_by: empId,
                year_id: yearId
            };

            $.ajax({
                url: apiBaseUrl + '/resolve-discrepancy',
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                data: JSON.stringify(payload),
                success: function (response) {
                    hideLoading();
                    if (response.success) {
                        showMessage('Discrepancy resolved successfully!', 'success');
                        const modal = document.getElementById('unsettledDetailsModal');
                        if (modal) {
                            const modalInstance = bootstrap.Modal.getInstance(modal);
                            if (modalInstance) { modalInstance.hide(); }
                        }
                        fetchUnsettledPayments();
                        fetchConfirmedPayments();
                        updateTabCounts();
                    } else {
                        showUnsettledModalMessage('Failed to resolve discrepancy: ' + (response.message || 'Unknown error'), 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();
                    console.error('Resolve Discrepancy Error:', error);

                    let errorMessage = 'Failed to resolve discrepancy: ' + error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = 'Failed to resolve discrepancy: ' + xhr.responseJSON.message;
                    }

                    showUnsettledModalMessage(errorMessage, 'danger');
                }
            });
        }

        function showConfirmedPaymentByIndex(index) {
            const payment = confirmedPayments[index];
            if (!payment) {
                showMessage('Payment not found at index: ' + index, 'warning');
                return;
            }
            showConfirmedPaymentDetails(payment.activity_id);
        }

        function showConfirmedPaymentDetails(activityId) {
            const payment = confirmedPayments.find(p => String(p.activity_id) === String(activityId));
            if (!payment) {
                showMessage('Payment not found (ID: ' + activityId + ')', 'warning');
                return;
            }

            const modalHtml = `
                        <div class="modal fade" id="paymentDetailsModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header" style="background-color: #155997; color: white;">
                                        <h5 class="modal-title text-white">
                                            <i class="fa fa-file-text-o"></i> Confirmed Payment Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" style="max-height: 85vh; overflow-y: auto; padding: 15px;">
                                        <div class="row">
                                            <div class="col-8">
                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">
                                                        <i class="fa fa-file-text-o"></i> Request Information
                                                    </h6>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                                                <strong>Employee:</strong> ${payment.employee_name || 'Unknown'} (${payment.employee_code || '-'})
                                                            </div>
                                                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                                                <strong>Department:</strong> ${payment.department || '-'}
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                                                <strong>Designation:</strong> ${payment.designation || '-'}
                                                            </div>
                                                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                                                <strong>Advance Type:</strong> ${payment.advance_type_name || '-'}
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                                                <strong>Activity:</strong> ${payment.req_data?.activity_name || '-'}
                                                            </div>
                                                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                                                <strong>Month:</strong> ${payment.month ? getMonthName(payment.month) : '-'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">
                                                        <i class="fa fa-list-ul"></i> Activity Details
                                                    </h6>
                                                    <div style="background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Activity Type:</strong> ${payment.activity_type_name || '-'}
                                                                </div>
                                                                ${payment.crop_names ? `
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Crops:</strong><br>
                                                                    ${payment.crop_names.split(', ').map(crop =>
                `<span class="crop-tag" style="display: inline-block; background-color: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 6px; font-size: 0.65rem; margin: 1px;">${crop}</span>`
            ).join('')}
                                                                </div>
                                                                ` : ''}
                                                            </div>
                                                            <div class="col-6">
                                                                ${payment.variety_names ? `
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Varieties:</strong><br>
                                                                    ${payment.variety_names.split(', ').map(variety =>
                `<span class="variety-tag" style="display: inline-block; background-color: #f3e5f5; color: #7b1fa2; padding: 2px 6px; border-radius: 6px; font-size: 0.65rem; margin: 1px;">${variety}</span>`
            ).join('')}
                                                                </div>
                                                                ` : ''}
                                                                ${payment.purpose ? `
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Purpose:</strong> ${payment.purpose}
                                                                </div>
                                                                ` : ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px;">
                                                        <i class="fa fa-file-text-o"></i> Account Summary
                                                    </h6>

                                                    <iframe
                                                        src="{{ url('/account-summary') }}?e=${payment.employee_id}&y=${yearId}&CompanyId=${comId}"
                                                        width="100%"
                                                        height="420"
                                                        style="border:1px solid #dee2e6;border-radius:4px;"
                                                        frameborder="0"
                                                        scrolling="yes">
                                                    </iframe>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">
                                                        <i class="fa fa-credit-card"></i> Payment Transaction Details
                                                    </h6>
                                                    <div style="background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Transaction Reference:</strong><br>
                                                                    <span style="font-family: monospace; color: #155997; font-weight: 600;">
                                                                        ${(() => {
                    if (payment.digi_next_payment_details?.transaction_refno) {
                        return payment.digi_next_payment_details.transaction_refno;
                    }
                    if (payment.payment_remark?.includes('Ref:')) {
                        const match = payment.payment_remark.match(/Ref:\s*([^\s,]+)/);
                        return match ? match[1] : 'Not Available';
                    }
                    return 'Not Available';
                })()}
                                                                    </span>
                                                                </div>
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Batch ID:</strong><br>
                                                                    <span style="color: #6c757d;">${payment.batch_id || 'Not Available'}</span>
                                                                </div>
                                                                ${payment.digi_next_payment_details?.rbi_utr_reference_number ? `
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>UTR Reference:</strong><br>
                                                                    <span style="font-family: monospace; color: #28a745; font-weight: 600;">${payment.digi_next_payment_details.rbi_utr_reference_number}</span>
                                                                </div>
                                                                ` : ''}
                                                            </div>
                                                            <div class="col-6">
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Payment Date:</strong><br>
                                                                    <span style="color: #6c757d;">${formatDate(payment.payment_date)}</span>
                                                                </div>
                                                                <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                                                    <strong>Confirmation Date:</strong><br>
                                                                    <span style="color: #6c757d;">${formatDate(payment.confirmation_date || payment.updated_at)}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-4">
                                                <div class="card mb-3" style="border: 1px solid #dee2e6;">
                                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                                                        <i class="fa fa-info-circle"></i> Request Status
                                                    </div>
                                                    <div class="card-body" style="padding: 12px;">
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Current Status:</div>
                                                            <div><span class="status-badge ${payment.request_status?.toLowerCase().includes('closed') ? 'status-approved' : 'status-pending'}">${payment.request_status || 'Unknown'}</span></div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Request Date:</div>
                                                            <div style="font-size: 0.8rem;">${formatDate(payment.created_at)}</div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Last Updated:</div>
                                                            <div style="font-size: 0.8rem;">${formatDate(payment.updated_at)}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="card mb-3" style="border: 1px solid #dee2e6;">
                                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                                                        <i class="fa fa-check-circle"></i> Approval History
                                                    </div>
                                                    <div class="card-body" style="padding: 12px;">
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">L1: Hitendra Singh</div>
                                                            <div style="font-size: 0.75rem;">
                                                                <span class="status-badge status-approved">APPROVED</span>
                                                                <span style="color: #6c757d; margin-left: 8px;">${formatDate(payment.created_at)}</span>
                                                            </div>
                                                            <div style="font-size: 0.7rem; color: #28a745; margin-top: 2px;">
                                                                Amount: ${formatAmount(payment.finance_approved_amount || 0)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="card mb-3" style="border: 1px solid #dee2e6;">
                                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                                                        <i class="fa fa-credit-card"></i> Finance Details
                                                    </div>
                                                    <div class="card-body" style="padding: 12px;">
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Finance Status:</div>
                                                            <div><span class="status-badge status-approved">APPROVED</span></div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Finance Date:</div>
                                                            <div style="font-size: 0.8rem;">${formatDate(payment.created_at)}</div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Approved Amount:</div>
                                                            <div class="amount-display" style="font-size: 0.9rem; color: #28a745; font-weight: 600;">
                                                                ${formatAmount(payment.finance_approved_amount || 0)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="card mb-3" style="border: 1px solid #dee2e6;">
                                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                                                        <i class="fa fa-money"></i> Payment Status
                                                    </div>
                                                    <div class="card-body" style="padding: 12px;">
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Status:</div>
                                                            <div><span class="status-badge ${payment.payment_status?.toLowerCase().includes('paid') ? 'status-approved' : 'status-pending'}">${payment.payment_status || 'Unknown'}</span></div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Date:</div>
                                                            <div style="font-size: 0.8rem;">${formatDate(payment.payment_date)}</div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Amount:</div>
                                                            <div class="amount-display" style="font-size: 0.9rem; color: #155997; font-weight: 600;">
                                                                ${formatAmount(payment.payment_amount || 0)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

            $('#paymentDetailsModal').remove();
            $('body').append(modalHtml);
            $('#paymentDetailsModal').modal('show');
        }

        function generateDetailModal(request, type) {
            const empName = request.employee_name || 'Unknown';
            const empCode = request.employee_code || '-';
            const department = request.department || '-';
            const designation = request.designation || '-';
            const activityName = (request.req_data && request.req_data.activity_name) || 'Unknown Activity';
            const advanceTypeName = (request.req_data && request.req_data.advance_type_name) || '';
            const month = getMonthName((request.req_data && request.req_data.month) || 0);

            let content = `
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
                                    <tr><td><strong>Created:</strong></td><td>${formatDate(request.created_at)}</td></tr>
                                </table>
                            </div>
                        </div>
                    `;

            if (request.activities && request.activities.length > 0) {
                content += `
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
                    const status = activity.approval_status || activity.finance_status || 'Pending';
                    const amount = currentAdvanceAmount(activity); // ADV-AMT-01

                    content += `
                                <tr>
                                    <td>${activity.activity_type_name || 'Unknown'}</td>
                                    <td class="text-end">${formatAmount(amount)}</td>
                                    <td><small>${crops}</small></td>
                                    <td><small>${varieties}</small></td>
                                    <td><small>${purpose}</small></td>
                                    <td><span class="status-badge ${getStatusBadgeClass(status)}">${status}</span></td>
                                </tr>
                            `;
                });

                content += `
                                    </tbody>
                                </table>
                            </div>
                        `;
            }

            if (request.activities && request.activities[0] && request.activities[0].approvals) {
                content += `
                            <h6 class="text-primary mb-3"><i class="fa fa-check-circle"></i> Approval History</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Level</th>
                                            <th>Approver</th>
                                            <th>Designation</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                request.activities[0].approvals.forEach((approval, index) => {
                    content += `
                                <tr>
                                    <td>L${approval.approval_level || (index + 1)}</td>
                                    <td>${approval.approver_name || 'Unknown'}</td>
                                    <td>${approval.approver_designation || '-'}</td>
                                    <td><span class="status-badge ${getStatusBadgeClass(approval.approver_status)}">${approval.approver_status || 'Pending'}</span></td>
                                    <td class="text-end">${approval.approver_amount ? formatAmount(approval.approver_amount) : '-'}</td>
                                    <td>${formatDate(approval.approver_date)}</td>
                                    <td><small>${approval.approver_remark || '-'}</small></td>
                                </tr>
                            `;
                });

                content += `
                                    </tbody>
                                </table>
                            </div>
                        `;
            }

            return `
                        <div class="modal fade" id="detailModal" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header" style="background-color: #155997; color: white;">
                                        <h5 class="modal-title">
                                            <i class="fa fa-file-text-o"></i> Request Details - ${type.charAt(0).toUpperCase() + type.slice(1)}
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                                        ${content}
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
        }


        // ADV-AMT-01 (13 Nov 2026): the amount a later stage works with is the last approved one:
        // Finance-approved if set, else the last approver's amount, else the amount asked for.
        function currentAdvanceAmount(activity) {
            if (!activity) return 0;
            const fin = parseFloat(activity.finance_approved_amount);
            if (fin > 0) return fin;
            const appr = (activity.approvals || []).filter(a => (a.approver_status || '').toLowerCase() === 'approved' && parseFloat(a.approver_amount) > 0);
            if (appr.length) return parseFloat(appr[appr.length - 1].approver_amount);
            return parseFloat(activity.amount) || 0;
        }
        function generateFinanceApprovalModal(request, action) {
            let lastApprovedAmount = 0;
            if (request.activities && request.activities.length > 0) {
                // ADV-AMT-02: the amount the approvers approved (fixed from here on)
                lastApprovedAmount = Math.round(request.activities.reduce((sum, activity) => sum + currentAdvanceAmount(activity), 0) * 100) / 100;
            }

            let approvalLevelInfo = '';
            if (request.activities && request.activities.length > 0) {
                const activity = request.activities[0];
                if (activity.approvals && activity.approvals.length > 0) {
                    approvalLevelInfo = `
                                <div class="card mb-3" style="border: 1px solid #dee2e6;">
                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 6px 10px; font-size: 0.75rem;">
                                        <i class="fa fa-list-check"></i> Approval History
                                    </div>
                                    <div class="card-body" style="padding: 6px;">
                            `;

                    activity.approvals.forEach((approval, index) => {
                        const levelNumber = approval.approval_level || (index + 1);
                        const borderColor = approval.approver_status && approval.approver_status.toLowerCase() === 'approved' ? '#28a745' : '#ffc107';

                        approvalLevelInfo += `
                                    <div class="mb-1 p-1" style="background-color: white; border-radius: 3px; border-left: 2px solid ${borderColor};">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; font-size: 0.65rem; color: #155997;">
                                                    L${levelNumber}: ${approval.approver_name || 'Unknown'}
                                                </div>
                                                <div style="font-size: 0.55rem; color: #666;">
                                                    ${approval.approver_designation || ''}
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <span class="status-badge ${getStatusBadgeClass(approval.approver_status)}" style="font-size: 0.5rem; padding: 1px 4px;">${approval.approver_status || 'Pending'}</span>
                                                <div style="font-size: 0.55rem; color: #666; margin-top: 2px;">
                                                    ${approval.approver_date ? formatDate(approval.approver_date) : 'Pending'}
                                                </div>
                                                ${approval.approver_amount ? `
                                                <div style="font-size: 0.6rem; color: #155997; font-weight: 600; margin-top: 1px;">
                                                    ${formatAmount(approval.approver_amount)}
                                                </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                        ${approval.approver_remark ? `
                                        <div style="font-size: 0.55rem; color: #666; margin-top: 4px; padding: 3px; background-color: #f8f9fa; border-radius: 2px;">
                                            ${approval.approver_remark}
                                        </div>
                                        ` : ''}
                                    </div>
                                `;
                    });

                    approvalLevelInfo += `
                                    </div>
                                </div>
                            `;
                }
            }

            const actionTitle = action === 'approve' ? 'Finance Approve' : 'Finance Reject';
            const actionClass = action === 'approve' ? 'btn-success' : 'btn-danger';
            const amountField = action === 'approve' ? `
                        <div class="mb-3">
                            <label class="form-label">Approved Amount (₹)</label>
                            <input type="number" class="form-control form-control-sm bg-light" id="financeApprovalAmount"
                                   value="${lastApprovedAmount}" min="0" step="0.01" required maxlength="6" readonly
                                   data-original-amount="${lastApprovedAmount}">
                            <div class="mt-1">
                                <small class="text-muted">Set by the approver; it cannot be changed at Finance. Reject or revert if it is wrong.</small>
                                <small id="financeAmountCounter" class="text-muted d-none">0/6 digits</small>
                                <div id="financeAmountError" class="text-danger" style="display: none; font-size: 0.75rem;"></div>
                            </div>
                        </div>
                    ` : '';

            return `
                        <div class="modal fade" id="financeApprovalModal" tabindex="-1" data-advance-month="${request.req_data?.month || ''}">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header" style="background-color: ${action === 'approve' ? '#28a745' : '#dc3545'}; color: white;">
                                        <h5 class="modal-title">
                                            <i class="fa fa-${action === 'approve' ? 'check-circle' : 'times-circle'}"></i> ${actionTitle}
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" style="max-height: 85vh; overflow-y: auto;">
                                        <div id="financeModalMessage" class="alert" style="display: none; margin-bottom: 15px;"></div>

                                        <div class="row">
                                            <div class="col-8">
                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px;">
                                                        <i class="fa fa-file-text-o"></i> Request Information
                                                    </h6>
                                                    <div class="row mb-2">
                                                        <div class="col-4">
                                                            <strong>Employee:</strong> ${request.employee_name} (${request.employee_code})
                                                        </div>
                                                        <div class="col-4">
                                                            <span class="detail-label">Raised By:</span> ${(request.req_data?.is_imprest || request.is_imprest || (request.req_data?.created_by === 0) || (request.req_data?.submitted_employee_details?.EmployeeCode === 'SYSTEM')) ? '<span class="badge bg-info text-dark" style="font-size:11px;">System Generated Auto Advance for Imprest</span>' : ((request.req_data?.submitted_employee_details?.EmployeeName || 'Unknown') + ' (' + (request.req_data?.submitted_employee_details?.EmployeeCode || '-') + ')')}
                                                        </div>
                                                        <div class="col-4">
                                                            <strong>Department:</strong> ${request.department}
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <strong>Designation:</strong> ${request.designation}
                                                        </div>
                                                        <div class="col-4">
                                                            <strong>Activity:</strong> ${request.req_data?.activity_name || ''}
                                                        </div>
                                                        <div class="col-4">
                                                            <strong>Advance Type:</strong> ${request.req_data?.advance_type_name || ''}
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-4">
                                                            <strong>Month:</strong> ${getMonthName(request.req_data?.month || 0)}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px;">
                                                        <i class="fa fa-list-ul"></i> Activity Details
                                                    </h6>
                                                    <div style="background-color: #f8f9fa; padding: 8px; border-radius: 4px;">
                                                        ${request.activities?.map(activity => `
                                                            <div class="mb-1 p-1" style="background-color: white; border-radius: 4px; border-left: 3px solid #17a2b8;">
                                                                <div class="row">
                                                                    <div class="col-3">
                                                                        <div style="font-weight: 600; font-size: 0.75rem; color: #155997;">
                                                                            ${activity.activity_type_name}
                                                                        </div>
                                                                        <div style="font-size: 0.7rem; color: #666;">
                                                                            Amount: <span class="amount-display" style="font-size: 0.7rem;">${formatAmount(currentAdvanceAmount(activity))}</span>${Math.abs(currentAdvanceAmount(activity) - (parseFloat(activity.amount) || 0)) > 0.009 ? ' <small class="text-muted">(asked <s>' + formatAmount(activity.amount) + '</s>)</small>' : ''}
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <div style="font-size: 0.65rem; font-weight: 600; color: #495057;">Crops:</div>
                                                                        <div style="font-size: 0.65rem;">
                                                                            ${activity.crop_names ? activity.crop_names.map(crop => `<span class="crop-tag" style="font-size: 0.55rem; padding: 1px 3px;">${crop}</span>`).join(' ') : '<span class="text-muted">No crops</span>'}
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <div style="font-size: 0.65rem; font-weight: 600; color: #495057;">Varieties:</div>
                                                                        <div style="font-size: 0.65rem;">
                                                                            ${activity.variety_names ? activity.variety_names.map(variety => `<span class="variety-tag" style="font-size: 0.55rem; padding: 1px 3px;">${variety}</span>`).join(' ') : '<span class="text-muted">No varieties</span>'}
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <div style="font-size: 0.65rem; font-weight: 600; color: #495057;">Purpose:</div>
                                                                        <div style="font-size: 0.65rem; ${activity.purpose ? '' : 'color: #6c757d; font-style: italic;'}">${activity.purpose || 'No purpose specified'}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        `).join('') || '<div class="text-muted text-center py-2" style="font-size: 0.75rem;"><i class="fa fa-info-circle"></i> No activity details available</div>'}
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 style="color: #155997; margin-bottom: 10px;">
                                                        <i class="fa fa-file-text-o"></i> Account Summary
                                                    </h6>

                                                    <iframe
                                                         src="{{ url('/account-summary') }}?e=${request.employee_id}&y=${yearId}&CompanyId=${comId}"
                                                        width="100%"
                                                        height="420"
                                                        style="border:1px solid #dee2e6;border-radius:4px;"
                                                        frameborder="0"
                                                        scrolling="yes">
                                                    </iframe>
                                                </div>
                                            </div>

                                            <div class="col-4">
                                                ${approvalLevelInfo}

                                                <div class="card" style="border: 1px solid #dee2e6;">
                                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600;">
                                                        <i class="fa fa-credit-card"></i> Finance Action
                                                    </div>
                                                    <div class="card-body">
                                                        ${amountField}

                                                        <div class="mb-3">
                                                            <label class="form-label">Finance Remarks ${action === 'reject' ? '(Required)' : ''}</label>
                                                            <textarea class="form-control form-control-sm" id="financeApprovalRemark"
                                                                      rows="1" placeholder="Enter finance remarks..." aria-label="Enter finance remarks" ${action === 'reject' ? 'required' : ''}></textarea>
                                                        </div>

                                                        <div class="d-grid gap-2">
                                                            <button type="button" class="btn ${actionClass}" onclick="submitFinanceApproval(${request.id}, '${action}')">
                                                                <i class="fa fa-${action === 'approve' ? 'check-circle' : 'times-circle'}"></i> ${actionTitle}
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="fa fa-times"></i> Cancel
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="card mt-3" style="border: 1px solid #dee2e6;">
                                                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600;">
                                                        <i class="fa fa-history"></i> Recent Advance History
                                                    </div>
                                                    <div class="card-body" style="padding: 8px; max-height: 200px; overflow-y: auto;">
                                                        ${request.req_data?.advance_history?.slice(0, 5).map(history => {
                const borderColor = history.current_status === 'Approved' ? '#28a745' : history.current_status === 'Rejected' ? '#dc3545' : '#ffc107';
                return `
                                                                <div class="mb-1 p-2" style="background-color: white; border-radius: 3px; border-left: 2px solid ${borderColor};">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-12">
                                                                            <div style="font-size: 0.65rem; font-weight: 600;">${history.activity_name}</div>
                                                                            <div style="font-size: 0.6rem; color: #666;">${history.advance_type_name}</div>
                                                                            <div class="d-flex justify-content-between mt-1">
                                                                                <span style="font-size: 0.6rem;">${getMonthName(history.month)} ${new Date(history.created_at).getFullYear()}</span>
                                                                                <span style="font-size: 0.65rem; font-weight: 600; color: #155997;">${formatAmount(history.total_amount)}</span>
                                                                                <span class="status-badge ${getStatusBadgeClass(history.current_status)}" style="font-size: 0.55rem; padding: 1px 3px;">${history.current_status === 'In_Approval' ? 'Pending' : history.current_status}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            `;
            }).join('') || '<div class="text-muted text-center py-2" style="font-size: 0.7rem;"><i class="fa fa-info-circle"></i> No recent advance history available</div>'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
        }

        function submitFinanceApproval(requestId, action) {
            const request = pendingRequests.find(req => req.id === requestId);
            if (!request) {
                showMessage('Request not found', 'warning');
                return;
            }

            const remark = $('#financeApprovalRemark').val().trim();

            let payload = {
                employee_id: request.employee_id,
                request_id: request.advance_request_id || requestId,
                user_id: empId
            };

            let apiUrl = '';

            if (action === 'approve') {
                const amount = parseFloat($('#financeApprovalAmount').val());
                const amountString = $('#financeApprovalAmount').val().toString();
                const beforeDecimal = amountString.split('.')[0];
                const originalAmount = parseFloat($('#financeApprovalAmount').data('original-amount')) || 0;

                if (!amount || amount <= 0) {
                    showFinanceModalMessage('Please enter valid finance approved amount', 'warning');
                    return;
                }

                if (beforeDecimal.length > 6) {
                    showFinanceModalMessage('Amount cannot exceed 6 digits', 'warning');
                    return;
                }

                // If amount changed from original, remark is mandatory
                if (amount !== originalAmount && !remark) {
                    showFinanceModalMessage('Finance Remarks is required when amount is changed', 'warning');
                    $('#financeApprovalRemark').focus();
                    return;
                }

                payload.finance_approved_amount = amount;
                if (remark) { payload.finance_remark = remark; }
                apiUrl = apiBaseUrl + '/approve';
            } else if (action === 'reject') {
                if (!remark) {
                    showFinanceModalMessage('Please enter finance rejection reason', 'warning');
                    return;
                }
                payload.remarks = remark;
                apiUrl = apiBaseUrl + '/reject';
            }

            showLoading();
            $.ajax({
                url: apiUrl,
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function (response) {
                    hideLoading();

                    if (response.success) {
                        const message = action === 'approve' ?
                            'Request approved by finance successfully!' :
                            'Request rejected by finance successfully!';
                        showFinanceModalMessage(message, 'success');

                        setTimeout(() => {
                            $('#financeApprovalModal').modal('hide');

                            fetchPendingRequests();
                            if (action === 'approve') {
                                fetchApprovedRequests();
                                updateTabCounts();
                            } else if (action === 'reject') {
                                fetchRejectedRequests();
                                updateTabCounts();
                            }
                        }, 2000);
                    } else {
                        showFinanceModalMessage(response.message || 'Error ' + action + 'ing request', 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);

                    let errorMessage = 'Error ' + action + 'ing request: ' + error;

                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                        }
                    } catch (e) { }

                    showFinanceModalMessage(errorMessage, 'danger');
                }
            });
        }

        function showFinanceModalMessage(message, type = 'danger') {
            const messageDiv = document.getElementById('financeModalMessage');
            if (messageDiv) {
                messageDiv.className = 'alert alert-' + type;
                messageDiv.textContent = message;
                messageDiv.style.display = 'block';

                const modalBody = messageDiv.closest('.modal-body');
                if (modalBody) { modalBody.scrollTop = 0; }

                if (type !== 'success') {
                    setTimeout(() => { messageDiv.style.display = 'none'; }, 5000);
                }
            }
        }

        function updateFinanceAmountCounter(input) {
            const counter = document.getElementById('financeAmountCounter');
            const errorDiv = document.getElementById('financeAmountError');
            const value = input.value.toString();

            if (counter) {
                counter.textContent = `${value.length}/6 digits`;

                if (value.length > 6) {
                    counter.style.color = '#dc3545';
                    errorDiv.textContent = 'Amount cannot exceed 6 digits';
                    errorDiv.style.display = 'block';
                } else {
                    counter.style.color = '#6c757d';
                    errorDiv.style.display = 'none';
                }
            }
        }

        function checkFinanceAmountChanged(input) {
            const originalAmount = parseFloat(input.dataset.originalAmount) || 0;
            const currentAmount = parseFloat(input.value) || 0;
            const remarkLabel = input.closest('.card-body').querySelector('label[for="financeApprovalRemark"], .form-label');
            const remarkTextarea = document.getElementById('financeApprovalRemark');

            // Find the label for Finance Remarks (it's the next .mb-3 sibling's label)
            const remarkContainer = input.closest('.mb-3').nextElementSibling;
            if (remarkContainer) {
                const label = remarkContainer.querySelector('.form-label');
                if (label) {
                    if (currentAmount !== originalAmount) {
                        label.innerHTML = 'Finance Remarks <span class="text-danger">(Required - amount changed)</span>';
                        if (remarkTextarea) remarkTextarea.setAttribute('required', 'required');
                    } else {
                        label.innerHTML = 'Finance Remarks';
                        if (remarkTextarea) remarkTextarea.removeAttribute('required');
                    }
                }
            }
        }

        function validateFinanceAmountInput(event) {
            if ([46, 8, 9, 27, 13, 110, 190].indexOf(event.keyCode) !== -1 ||
                (event.keyCode === 65 && event.ctrlKey === true) ||
                (event.keyCode === 67 && event.ctrlKey === true) ||
                (event.keyCode === 86 && event.ctrlKey === true) ||
                (event.keyCode === 88 && event.ctrlKey === true)) {
                return;
            }

            if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) && (event.keyCode < 96 || event.keyCode > 105)) {
                event.preventDefault();
            }

            const currentValue = event.target.value.toString();
            if (currentValue.length >= 6 && ![46, 8, 9, 27, 13].includes(event.keyCode)) {
                event.preventDefault();
            }
        }

        function toggleSelectAllApproved() {
            const selectAll = document.getElementById('selectAllApproved');
            const checkboxes = document.querySelectorAll('.approved-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateReleasePaymentButton();
        }

        function updateReleasePaymentButton() {
            const checkboxes = document.querySelectorAll('.approved-checkbox:checked');
            const button = document.getElementById('releasePaymentBtn');
            const selectAll = document.getElementById('selectAllApproved');
            const allCheckboxes = document.querySelectorAll('.approved-checkbox');

            button.disabled = checkboxes.length === 0;

            if (checkboxes.length === 0) {
                selectAll.indeterminate = false;
                selectAll.checked = false;
            } else if (checkboxes.length === allCheckboxes.length) {
                selectAll.indeterminate = false;
                selectAll.checked = true;
            } else {
                selectAll.indeterminate = true;
            }

            const matrixBtn = document.getElementById('pushAdvanceToMatrixBtn');
            const previewBtn = document.getElementById('previewAdvanceMatrixBtn');
            if (matrixBtn) {
                matrixBtn.disabled = checkboxes.length === 0;
                if (checkboxes.length > 0) {
                    matrixBtn.innerHTML = `<i class="fa fa-paper-plane"></i> Push to Matrix (${checkboxes.length})`;
                } else {
                    matrixBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Push to Matrix';
                }
            }
            if (previewBtn) {
                previewBtn.disabled = checkboxes.length === 0;
            }

            if (checkboxes.length > 0) {
                button.innerHTML = `<i class="fa fa-credit-card"></i> Release Payment (${checkboxes.length})`;
            } else {
                button.innerHTML = '<i class="fa fa-credit-card"></i> Release Payment';
            }
        }

        function releaseSelectedPayments() {
            const checkboxes = document.querySelectorAll('.approved-checkbox:checked');

            if (checkboxes.length === 0) {
                showMessage('Please select at least one request to release for payment', 'warning');
                return;
            }

            const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            const selectedRequests = approvedRequests.filter(req => selectedIds.includes(req.id));

            const payload = {
                user_id: empId,
                selected_requests: selectedRequests.map(request => ({
                    id: request.id,
                    employee_id: request.employee_id,
                    advance_request_id: request.advance_request_id || request.id,
                    finance_approved_amount: request.activities ?
                        request.activities.reduce((sum, activity) => sum + (parseFloat(activity.finance_approved_amount) || 0), 0) :
                        (request.total_approved_amount || request.total_amount || 0)
                }))
            };

            if (!confirm('Are you sure you want to release ' + selectedRequests.length + ' request(s) for payment?')) {
                return;
            }

            showLoading();
            $.ajax({
                url: apiBaseUrl + '/release-payment',
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                data: JSON.stringify(payload),
                success: function (response) {
                    hideLoading();

                    if (response.success) {
                        selectedRequests.forEach(request => {
                            request.payment_status = 'Ready for Payment';
                            request.payment_released_date = new Date().toISOString();
                            request.payment_batch_id = response.data.payment_batch_id;
                            paymentRequests.push(request);
                        });

                        approvedRequests = approvedRequests.filter(req => !selectedIds.includes(req.id));

                        initializeApprovedTable();
                        initializePaymentTable();
                        updateTabCounts();

                        const paymentTab = document.getElementById('payment-tab');
                        const paymentTabPane = new bootstrap.Tab(paymentTab);
                        paymentTabPane.show();

                        showMessage(response.message || (selectedRequests.length + ' request(s) released for payment successfully!'), 'success');
                    } else {
                        showMessage(response.message || 'Error releasing payments', 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();
                    console.error('Error releasing payments:', error);

                    let errorMessage = 'Error releasing payments: ' + error;

                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                        }
                        if (errorResponse.errors && errorResponse.errors.length > 0) {
                            errorMessage += '\n\nDetails:\n' + errorResponse.errors.map(err =>
                                'Request ID ' + err.request_id + ': ' + err.error
                            ).join('\n');
                        }
                    } catch (e) { }

                    showMessage(errorMessage, 'danger');
                }
            });
        }

        // Push selected advance activities to Matrix
        function pushSelectedAdvanceToMatrix() {
            const checkboxes = document.querySelectorAll('.approved-checkbox:checked');
            if (checkboxes.length === 0) {
                showMessage('Please select at least one request to push to Matrix', 'warning');
                return;
            }

            const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            const selectedRequests = approvedRequests.filter(req => selectedIds.includes(req.id));

            let activityIds = [];
            selectedRequests.forEach(req => {
                if (req.activities && req.activities.length > 0) {
                    req.activities.forEach(act => {
                        if (act.id) activityIds.push(act.id);
                    });
                } else {
                    activityIds.push(req.id);
                }
            });

            if (activityIds.length === 0) {
                showMessage('No valid advance activities found to push', 'warning');
                return;
            }

            if (!confirm('Push ' + activityIds.length + ' advance request activity(ies) to Matrix?\n\nMatrix will hold them for acceptance, approval, bank payment, and Focus ERP processing.')) {
                return;
            }

            const btn = document.getElementById('pushAdvanceToMatrixBtn');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing to Matrix...';

            $.ajax({
                url: "{{ route('matrix.push-advances') }}",
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    ids: activityIds
                },
                success: function(response) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (response.success) {
                        showMessage(response.message || 'Pushed to Matrix successfully!', 'success');
                        fetchApprovedRequests();
                        const paymentTab = document.getElementById('payment-tab');
                        if (paymentTab) {
                            const paymentTabPane = new bootstrap.Tab(paymentTab);
                            paymentTabPane.show();
                        }
                    } else {
                        showMessage(response.message || 'Error pushing to Matrix', 'danger');
                    }
                },
                error: function(xhr) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    showMessage(xhr.responseJSON?.message || 'Failed to push to Matrix', 'danger');
                }
            });
        }

        // Sync Matrix advance payment updates
        function syncMatrixAdvanceUpdates() {
            showLoading();
            $.ajax({
                url: "{{ route('matrix.sync-updates') }}",
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    hideLoading();
                    if (response.success) {
                        showMessage('Matrix sync completed. Processed: ' + (response.total_processed || 0) + ', Updated: ' + (response.updated_instructions || 0), 'success');
                        fetchApprovedRequests();
                        if (typeof fetchPaymentRequests === 'function') fetchPaymentRequests();
                        if (typeof fetchConfirmedPayments === 'function') fetchConfirmedPayments();
                    } else {
                        showMessage(response.message || 'Sync error', 'danger');
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    showMessage(xhr.responseJSON?.message || 'Sync failed', 'danger');
                }
            });
        }

        // Preview advance Matrix JSON payload before pushing
        function previewAdvanceMatrixPayload() {
            const checkboxes = document.querySelectorAll('.approved-checkbox:checked');
            if (checkboxes.length === 0) {
                showMessage('Please select at least one request to preview', 'warning');
                return;
            }

            const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            const selectedRequests = approvedRequests.filter(req => selectedIds.includes(req.id));

            let activityIds = [];
            selectedRequests.forEach(req => {
                if (req.activities && req.activities.length > 0) {
                    req.activities.forEach(act => {
                        if (act.id) activityIds.push(act.id);
                    });
                } else {
                    activityIds.push(req.id);
                }
            });

            if (activityIds.length === 0) {
                showMessage('No valid advance activities found to preview', 'warning');
                return;
            }

            const btn = document.getElementById('previewAdvanceMatrixBtn');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

            $.ajax({
                url: "{{ route('matrix.preview-payload') }}",
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    payment_type: 'advance',
                    ids: activityIds
                },
                success: function(response) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (response.success) {
                        console.log('Matrix Advance Payload Preview:', response.payload);
                        showMatrixPayloadModal(response.payload, response.count);
                    } else {
                        showMessage(response.message || 'Error generating preview', 'danger');
                    }
                },
                error: function(xhr) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    showMessage(xhr.responseJSON?.message || 'Failed to generate preview', 'danger');
                }
            });
        }

        function showMatrixPayloadModal(payload, count) {
            $('#matrixAdvancePayloadModal').remove();
            const jsonStr = JSON.stringify(payload, null, 2);

            const modalHtml = `
            <div class="modal fade" id="matrixAdvancePayloadModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white py-2">
                            <h6 class="modal-title mb-0"><i class="fa fa-code"></i> Matrix Advance Payment Payload Preview (${count} instructions)</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-2" style="background:#1e1e1e;color:#d4d4d4;max-height:70vh;">
                            <pre style="margin:0;font-size:11px;color:#9cdcfe;font-family:Consolas, monospace;"><code>${escapeHtmlAdvance(jsonStr)}</code></pre>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText($('#matrixAdvancePayloadModal pre code').text()); alert('Payload JSON copied to clipboard!');">
                                <i class="fa fa-copy"></i> Copy JSON
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>`;

            $('body').append(modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('matrixAdvancePayloadModal'));
            modal.show();
        }

        function escapeHtmlAdvance(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // ==========================================
        // INITIALIZATION
        // ==========================================
        $(document).ready(function () {
            fetchPendingRequests();

            $('#financeApprovalTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = e.target.getAttribute('data-bs-target');
                buildFiltersForTab(target);

                switch (target) {
                    case '#pending':
                        if (pendingRequests.length === 0) { fetchPendingRequests(); }
                        else if (pendingTable) { pendingTable.columns.adjust().responsive.recalc(); }
                        break;
                    case '#approved':
                        if (approvedRequests.length === 0) { fetchApprovedRequests(); }
                        else if (approvedTable) { approvedTable.columns.adjust().responsive.recalc(); }
                        break;
                    case '#payment':
                        if (paymentRequests.length === 0) { fetchPaymentRequests(); }
                        else if (paymentTable) { paymentTable.columns.adjust().responsive.recalc(); }
                        break;
                    case '#confirmed':
                        if (confirmedPayments.length === 0) { fetchConfirmedPayments(); }
                        else if (confirmedPaymentsTable) { confirmedPaymentsTable.columns.adjust().responsive.recalc(); }
                        break;
                    case '#unsettled':
                        if (unsettledPayments.length === 0) { fetchUnsettledPayments(); }
                        else if (unsettledPaymentsTable) { unsettledPaymentsTable.columns.adjust().responsive.recalc(); }
                        break;
                    case '#rejected':
                        if (rejectedRequests.length === 0) { fetchRejectedRequests(); updateTabCounts(); }
                        else if (rejectedTable) { rejectedTable.columns.adjust().responsive.recalc(); }
                        break;
                }
            });

            // Build initial filters for pending tab (rebuild after data loads)
            buildFiltersForTab('#pending');
        });

        // Rebuild filters when data is loaded (called from fetch success callbacks)
        function rebuildCurrentFilters() {
            var target = ($('#financeApprovalTabs .btn.active').attr('data-bs-target')) || '#pending';
            buildFiltersForTab(target);
        }

        // ==========================================
        // DYNAMIC FILTERS PER TAB
        // ==========================================
        function buildFiltersForTab(tabId) {
            var $c = $('#filterInputsContainer');
            $c.find('.dyn-f').remove();

            // Collect unique values from the ACTIVE TAB's data only
            var tabDataMap = {
                '#pending': pendingRequests || [],
                '#approved': approvedRequests || [],
                '#payment': paymentRequests || [],
                '#confirmed': confirmedPayments || [],
                '#unsettled': unsettledPayments || [],
                '#rejected': rejectedRequests || [],
            };
            var tabData = tabDataMap[tabId] || [];

            var activities = [...new Set(tabData.map(r => (r.req_data && r.req_data.activity_name)||'').filter(v=>v))].sort();
            var employees = [...new Set(tabData.map(r => r.employee_name||'').filter(v=>v))].sort();
            var crops = [];
            tabData.forEach(function(r){
                if(r.activities){r.activities.forEach(function(a){if(a.crop_names)crops=crops.concat(a.crop_names);});}
            });
            crops = [...new Set(crops)].sort();

            var tabFilters = {
                '#pending': [
                    {p:'Employee',field:'employee',t:'select',opts:employees},
                    {p:'Activity',field:'activity',t:'select',opts:activities},
                    {p:'Month',field:'month',t:'select',opts:['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar']},
                    {p:'Crop',field:'crop',t:'select',opts:crops},
                    {p:'Purpose',field:'purpose',t:'text'},
                    {p:'Date',field:'date',t:'date'}
                ],
                '#approved': [
                    {p:'Employee',field:'employee',t:'select',opts:employees},
                    {p:'Activity',field:'activity',t:'select',opts:activities},
                    {p:'Month',field:'month',t:'select',opts:['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar']},
                    {p:'Crop',field:'crop',t:'select',opts:crops},
                    {p:'Approved Date',field:'date',t:'date'}
                ],
                '#payment': [
                    {p:'Employee',field:'employee',t:'select',opts:employees},
                    {p:'Activity',field:'activity',t:'select',opts:activities},
                    {p:'Month',field:'month',t:'select',opts:['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar']},
                    {p:'Released Date',field:'date',t:'date'}
                ],
                '#confirmed': [{p:'Employee',field:'employee',t:'select',opts:employees},{p:'Activity',field:'activity',t:'select',opts:activities},{p:'Confirmed Date',field:'date',t:'date'}],
                '#unsettled': [{p:'Employee',field:'employee',t:'select',opts:employees},{p:'Activity',field:'activity',t:'select',opts:activities}],
                '#rejected': [
                    {p:'Employee',field:'employee',t:'select',opts:employees},
                    {p:'Activity',field:'activity',t:'select',opts:activities},
                    {p:'Month',field:'month',t:'select',opts:['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar']},
                    {p:'Rejected Date',field:'date',t:'date'}
                ]
            };

            var filters = tabFilters[tabId] || tabFilters['#pending'];
            filters.forEach(function(f) {
                if (f.t === 'text') {
                    $c.append('<input type="text" class="form-control form-control-sm dyn-f flex-fill" data-field="'+f.field+'" placeholder="'+f.p+'" aria-label="'+f.p+'" style="font-size:0.72rem;min-width:100px;">');
                } else if (f.t === 'select') {
                    var opts = '<option value="">'+f.p+'</option>';
                    (f.opts||[]).forEach(function(o){opts+='<option value="'+o+'">'+o+'</option>';});
                    $c.append('<select class="form-select form-select-sm dyn-f flex-fill" data-field="'+f.field+'" style="font-size:0.72rem;min-width:90px;">'+opts+'</select>');
                } else if (f.t === 'date') {
                    var el = $('<input type="text" class="form-control form-control-sm dyn-f dyn-dp flex-fill" data-field="'+f.field+'" placeholder="'+f.p+'" aria-label="'+f.p+'" style="font-size:0.72rem;min-width:110px;">');
                    $c.append(el);
                    if(typeof flatpickr!=='undefined'){flatpickr(el[0],{dateFormat:'d M Y',allowInput:true});}
                }
            });
            $c.append('<button class="btn btn-outline-danger btn-sm dyn-f" onclick="clearDynFilters()" style="font-size:0.72rem;padding:3px 10px;white-space:nowrap;">Clear</button>');
        }

        function getActiveFinTable() {
            var t = $('#financeApprovalTabs .btn.active').attr('data-bs-target')||'#pending';
            if(t==='#pending')return pendingTable;
            if(t==='#approved')return approvedTable;
            if(t==='#payment')return paymentTable;
            if(t==='#confirmed')return confirmedPaymentsTable;
            if(t==='#unsettled')return unsettledPaymentsTable;
            if(t==='#rejected')return rejectedTable;
            return pendingTable;
        }

        function applyDynF() {
            var table = getActiveFinTable(); if(!table)return;

            // Clear previous ext search
            $.fn.dataTable.ext.search = [];

            // Get all filter values
            var filters = [];
            $('#filterInputsContainer .dyn-f').not('button').each(function(){
                var v = ($(this).val()||'').trim().toLowerCase();
                var field = $(this).data('field'); // use data-field instead of data-col
                if(v && field) filters.push({field: field, value: v});
            });

            if(filters.length > 0) {
                $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex, rowData) {
                    // Get the actual row data from the table
                    var tbl = getActiveFinTable();
                    if(!tbl || settings.nTable.id !== tbl.table().node().id) return true;

                    var row = tbl.row(dataIndex).data();
                    if(!row) return true;

                    for(var i=0; i<filters.length; i++){
                        var f = filters[i];
                        var cellVal = '';

                        // Extract value based on field name
                        switch(f.field){
                            case 'employee':
                                cellVal = ((row.employee_name||'') + ' ' + (row.employee_code||'')).toLowerCase();
                                break;
                            case 'activity':
                                cellVal = ((row.req_data && row.req_data.activity_name)||'').toLowerCase();
                                break;
                            case 'month':
                                var m = (row.req_data && row.req_data.month) ? row.req_data.month : 0;
                                var mNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                cellVal = (mNames[m]||'').toLowerCase();
                                break;
                            case 'crop':
                                var allCrops = [];
                                if(row.activities){row.activities.forEach(function(a){if(a.crop_names)allCrops=allCrops.concat(a.crop_names);});}
                                cellVal = allCrops.join(' ').toLowerCase();
                                break;
                            case 'purpose':
                                var purposes = [];
                                if(row.activities){row.activities.forEach(function(a){if(a.purpose)purposes.push(a.purpose);});}
                                cellVal = purposes.join(' ').toLowerCase();
                                break;
                            case 'date':
                                cellVal = (row.created_at||row.approved_at||row.released_at||row.confirmed_at||row.rejected_at||'').toLowerCase();
                                break;
                        }

                        if(!cellVal.includes(f.value)) return false;
                    }
                    return true;
                });
            }

            table.draw();
        }

        function clearDynFilters(){
            $('#filterInputsContainer .dyn-f').not('button').val('');
            $.fn.dataTable.ext.search = [];
            var table = getActiveFinTable();
            if(table) table.draw();
        }

        $(document).on('keyup','#filterInputsContainer input.dyn-f',applyDynF);
        $(document).on('change','#filterInputsContainer select.dyn-f, #filterInputsContainer .dyn-dp',applyDynF);


        function updateTabCounts() {
            $('#pendingCount').text(pendingRequests.length || 0);
            $('#approvedCount').text(approvedRequests.length || 0);
            $('#paymentCount').text(paymentRequests.length || 0);
            $('#confirmedCount').text(confirmedPayments.length || 0);
            $('#unsettledCount').text(unsettledPayments.length || 0);
            $('#rejectedCount').text(rejectedRequests.length || 0);
        }







        function exportPendingExcel() {
            let exportData = [];

            pendingRequests.forEach((row, index) => {
                let crops = '';
                let varieties = '';
                let purpose = '';
                let amount = 0;

                if (row.activities) {
                    let allCrops = [];
                    let allVarieties = [];
                    let allPurpose = [];

                    row.activities.forEach(a => {
                        if (a.crop_names) allCrops.push(...a.crop_names);
                        if (a.variety_names) allVarieties.push(...a.variety_names);
                        if (a.purpose) allPurpose.push(a.purpose);
                        amount += parseFloat(a.amount || 0);
                    });

                    crops = [...new Set(allCrops)].join(', ');
                    varieties = [...new Set(allVarieties)].join(', ');
                    purpose = allPurpose.join('; ');
                }

                if (amount === 0) {
                    amount = row.total_approved_amount || row.total_pending_amount || row.total_amount || 0;
                }

                exportData.push({
                    "Sr.No": index + 1,
                    "Employee": row.employee_name,
                    "Code": row.employee_code,
                    "Department": row.department,
                    "Activity": row.req_data?.activity_name,
                    "Month": getMonthName(row.req_data?.month),
                    "Crops": crops,
                    "Varieties": varieties,
                    "Purpose": purpose,
                    "Amount": amount,
                    "Date": formatDate(row.created_at)
                });
            });

            downloadExcel(exportData, "Pending.xlsx");
        }


        function exportApprovedExcel() {
            let exportData = [];

            approvedRequests.forEach((row, index) => {
                let amount = 0;
                let crops = '';
                let varieties = '';
                let purpose = '';

                if (row.activities) {
                    let allCrops = [];
                    let allVarieties = [];
                    let allPurpose = [];

                    row.activities.forEach(a => {
                        if (a.crop_names) allCrops.push(...a.crop_names);
                        if (a.variety_names) allVarieties.push(...a.variety_names);
                        if (a.purpose) allPurpose.push(a.purpose);
                        amount += parseFloat(a.finance_approved_amount || 0);
                    });

                    crops = [...new Set(allCrops)].join(', ');
                    varieties = [...new Set(allVarieties)].join(', ');
                    purpose = allPurpose.join('; ');
                }

                exportData.push({
                    "Sr.No": index + 1,
                    "Employee": row.employee_name,
                    "Code": row.employee_code,
                    "Department": row.department,
                    "Activity": row.req_data?.activity_name,
                    "Month": getMonthName(row.req_data?.month),
                    "Crops": crops,
                    "Varieties": varieties,
                    "Purpose": purpose,
                    "Amount": amount,
                    "Approved Date": formatDate(row.updated_at)
                });
            });

            downloadExcel(exportData, "Approved.xlsx");
        }



        function exportPaymentExcel() {
            let exportData = [];

            paymentRequests.forEach((row, index) => {
                let amount = 0;
                if (row.activities) {
                    row.activities.forEach(a => {
                        amount += parseFloat(a.finance_approved_amount || a.amount || 0);
                    });
                }

                exportData.push({
                    "Sr.No": index + 1,
                    "Employee": row.employee_name,
                    "Code": row.employee_code,
                    "Department": row.department,
                    "Activity": row.req_data?.activity_name,
                    "Month": getMonthName(row.req_data?.month),
                    "Amount": amount,
                    "Released Date": formatDate(row.payment_released_date),
                    "Status": row.payment_status
                });
            });

            downloadExcel(exportData, "Payment_Instructed.xlsx");
        }


        function generateExcelExport() {

            if (pendingRequests.length)
                exportPendingExcel();

            if (approvedRequests.length)
                exportToExcel(approvedRequests, 'Approved.xlsx');

            if (rejectedRequests.length)
                exportToExcel(rejectedRequests, 'Rejected.xlsx');

            if (paymentRequests.length)
                exportToExcel(paymentRequests, 'Payment_Instructed.xlsx');

            if (confirmedPayments.length)
                exportToExcel(confirmedPayments, 'Confirmed_Payments.xlsx');

            if (unsettledPayments.length)
                exportToExcel(unsettledPayments, 'Unsettled_Payments.xlsx');
        }

        function downloadExcel(data, fileName) {
            const worksheet = XLSX.utils.json_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Data");
            XLSX.writeFile(workbook, fileName);
        }


    </script>




@endpush
