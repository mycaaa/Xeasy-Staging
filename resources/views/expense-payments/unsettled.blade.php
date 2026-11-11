{{-- resources/views/expense-payments/unsettled.blade.php --}}
@extends('layouts.app')

@section('styles')
    <style>
        .status-tabs {
            border-bottom: 1px solid #e5e7eb;
            margin-top: 10px;
            padding: 0 5px;
        }

        .estable {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .estable th {
            background-color: #15648C;
            color: white;
            padding: 8px 4px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .estable td {
            padding: 10px 4px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        .estable tbody tr:hover {
            background-color: #f5f5f5;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 3px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .month-select {
            font-size: 13px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #000;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .beneficiary-details {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
            line-height: 1.4;
        }

        .reason-text {
            font-size: 11px;
            line-height: 1.5;
            max-width: 250px;
            white-space: normal;
            word-wrap: break-word;
        }

        .amount-right {
            text-align: right;
            font-weight: 600;
        }

        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 20px 0;
        }

        .pagination li {
            margin: 0 2px;
        }

        .pagination li a,
        .pagination li span {
            padding: 6px 12px;
            border: 1px solid #ddd;
            color: #007bff;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }

        .pagination li.active span {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination li.disabled span {
            color: #6c757d;
            pointer-events: none;
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
            width: 3rem;
            height: 3rem;
        }

        /* Adjustment info */
        .adjustment-info {
            font-size: 10px;
            color: #856404;
            background-color: #fff3cd;
            padding: 2px 5px;
            border-radius: 3px;
            margin-top: 3px;
            display: inline-block;
        }

        /* Modal styles */
        .details-table {
            width: 100%;
            font-size: 13px;
        }

        .details-table td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }

        .details-table td:first-child {
            font-weight: 600;
            width: 35%;
            background-color: #f8f9fa;
        }

        .section-title {
            background-color: #15648C;
            color: white;
            padding: 8px 12px;
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px;
        }

        .first-section-title {
            margin-top: 0;
        }

        /* Filter section styles */
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }

        .filter-input {
            font-size: 12px;
            padding: 4px 8px;
        }

        .active-filters {
            margin-top: 10px;
            padding: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 11px;
        }

        .filter-badge {
            background-color: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-right: 5px;
            display: inline-block;
        }

        .filter-badge .remove-filter {
            margin-left: 5px;
            cursor: pointer;
            color: #ffc107;
        }
    </style>
@endsection

@section('content')
    <div class="col-md-12 h-100 p-3 mt-3">
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Status Tabs -->
        <div class="row p-3 bg-white border-bottom">
           <div class="btn-group" role="group">
                <a href="{{ route('expense-payments.index', ['chkval' => 1, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval', 1) == 1 ? 'btn-primary' : 'btn-outline-primary' }}">
                    Pending
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 2, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 2 ? 'btn-success' : 'btn-outline-success' }}">
                    Approved
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 6, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 6 ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    Holded
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 3, 'v' => request('v', 0), 'export_filter' => 0]) }}"
                    class="btn btn-sm {{ request('chkval') == 3 ? 'btn-warning' : 'btn-outline-warning' }}">
                    Instructed Payment
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 4, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 4 ? 'btn-info' : 'btn-outline-info' }}">
                    Confirmed Payments
                </a>
                <a href="{{ route('expense-payments.index', ['chkval' => 5, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 5 ? 'btn-danger' : 'btn-outline-danger' }}">
                    Unsettled Payments
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row ">
            <div class="col-lg-12 shadow">
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 0px;">
                    <div>
                        <b style="font-size: 16px;">Unsettled Expense Payment List</b>
                        <span id="unsettledSummary" class="ms-3" style="font-size: 13px;"></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size:14px;">Month :</span>
                        <select class="month-select" id="SelMonth">
                            <option value="0" {{ request('v', 0) == 0 ? 'selected' : '' }}>All</option>
                            <option value="1" {{ request('v') == 1 ? 'selected' : '' }}>January</option>
                            <option value="2" {{ request('v') == 2 ? 'selected' : '' }}>February</option>
                            <option value="3" {{ request('v') == 3 ? 'selected' : '' }}>March</option>
                            <option value="4" {{ request('v') == 4 ? 'selected' : '' }}>April</option>
                            <option value="5" {{ request('v') == 5 ? 'selected' : '' }}>May</option>
                            <option value="6" {{ request('v') == 6 ? 'selected' : '' }}>June</option>
                            <option value="7" {{ request('v') == 7 ? 'selected' : '' }}>July</option>
                            <option value="8" {{ request('v') == 8 ? 'selected' : '' }}>August</option>
                            <option value="9" {{ request('v') == 9 ? 'selected' : '' }}>September</option>
                            <option value="10" {{ request('v') == 10 ? 'selected' : '' }}>October</option>
                            <option value="11" {{ request('v') == 11 ? 'selected' : '' }}>November</option>
                            <option value="12" {{ request('v') == 12 ? 'selected' : '' }}>December</option>
                        </select>

                        <a class="btn btn-sm btn-primary" onclick="FselMonth()" style="padding: 4px 16px;">
                            <span style="color:#FFFFFF;">Filter</span>
                        </a>

                        <a class="btn btn-sm btn-info" onclick="location.reload()" style="padding: 4px 12px;">
                            <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
                        </a>
                        <button onclick="verifyUnsettledPayments()" class="btn btn-warning btn-sm">
                            Verify Unsettled Payments
                        </button>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="{{ route('expense-payments.index') }}" id="filterForm">
                        <input type="hidden" name="chkval" value="5">
                        <input type="hidden" name="v" value="{{ request('v', 0) }}">

                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <div class="filter-label">Employee Search</div>
                                <input type="text" class="form-control filter-input" name="employee_search"
                                    value="{{ request('employee_search') }}" placeholder="Name or Code" aria-label="Name or Code">
                            </div>

                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Unsettled Date From</div>
                                <input type="date" class="form-control filter-input" name="unsettled_date_from"
                                    value="{{ request('unsettled_date_from') }}">
                            </div>

                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Unsettled Date To</div>
                                <input type="date" class="form-control filter-input" name="unsettled_date_to"
                                    value="{{ request('unsettled_date_to') }}">
                            </div>

                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Min Amount (₹)</div>
                                <input type="number" class="form-control filter-input" name="min_amount"
                                    value="{{ request('min_amount') }}" placeholder="Min Amount" aria-label="Min Amount" step="0.01">
                            </div>

                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Max Amount (₹)</div>
                                <input type="number" class="form-control filter-input" name="max_amount"
                                    value="{{ request('max_amount') }}" placeholder="Max Amount" aria-label="Max Amount" step="0.01">
                            </div>
                        </div>

                        <div class="row">


                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Reason Contains</div>
                                <input type="text" class="form-control filter-input" name="reason_search"
                                    value="{{ request('reason_search') }}" placeholder="Search in reason" aria-label="Search in reason">
                            </div>



                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Sort By</div>
                                <select class="form-select filter-input" name="sort_field">
                                    <option value="payment_unsettled_date"
                                        {{ request('sort_field') == 'payment_unsettled_date' ? 'selected' : '' }}>Unsettled
                                        Date
                                    </option>
                                    <option value="payment_amount"
                                        {{ request('sort_field') == 'payment_amount' ? 'selected' : '' }}>Payment Amount
                                    </option>
                                    <option value="Finance_Amount"
                                        {{ request('sort_field') == 'Finance_Amount' ? 'selected' : '' }}>Finance Amount
                                    </option>
                                    <option value="total_adjustment"
                                        {{ request('sort_field') == 'total_adjustment' ? 'selected' : '' }}>Adjustment
                                        Amount</option>
                                    <option value="employee_name"
                                        {{ request('sort_field') == 'employee_name' ? 'selected' : '' }}>Employee Name
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Sort Order</div>
                                <select class="form-select filter-input" name="sort_order">
                                    <option value="DESC" {{ request('sort_order') == 'DESC' ? 'selected' : '' }}>
                                        Descending
                                    </option>
                                    <option value="ASC" {{ request('sort_order') == 'ASC' ? 'selected' : '' }}>
                                        Ascending
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-2 mb-2">
                                <div class="filter-label">Per Page</div>
                                <select class="form-select filter-input" name="per_page">
                                    <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15
                                    </option>
                                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-2 d-flex align-items-end">
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fa fa-search"></i> Apply Filters
                                    </button>
                                    <a href="{{ route('expense-payments.index', ['chkval' => 5, 'v' => request('v', 0)]) }}"
                                        class="btn btn-sm btn-secondary">
                                        <i class="fa fa-times"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        </div>


                        <!-- Active Filters Display -->
                        @php
                            $activeFilters = [];
                            if (request('employee_search')) {
                                $activeFilters[] = 'Employee: ' . request('employee_search');
                            }
                            if (request('unsettled_date_from')) {
                                $activeFilters[] = 'Date From: ' . request('unsettled_date_from');
                            }
                            if (request('unsettled_date_to')) {
                                $activeFilters[] = 'Date To: ' . request('unsettled_date_to');
                            }
                            if (request('min_amount')) {
                                $activeFilters[] = 'Min Amt: ₹' . number_format(request('min_amount'), 2);
                            }
                            if (request('max_amount')) {
                                $activeFilters[] = 'Max Amt: ₹' . number_format(request('max_amount'), 2);
                            }
                            if (request('reason_search')) {
                                $activeFilters[] = 'Reason: ' . request('reason_search');
                            }
                            if (request('has_adjustment')) {
                                $activeFilters[] = 'Adjustment: ' . (request('has_adjustment') == 'yes' ? 'Yes' : 'No');
                            }
                        @endphp

                        @if (count($activeFilters) > 0)
                            <div class="active-filters">
                                <strong><i class="fa fa-tag"></i> Active Filters:</strong><br>
                                @foreach ($activeFilters as $filter)
                                    <span class="filter-badge">
                                        {{ $filter }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </form>
                </div>

                <!-- Alert Message -->
                <div id="Message" class="alert" style="display: none; margin-bottom: 15px;"></div>

                <!-- Unsettled Payments Table -->
                <div style="overflow-x: auto; width: 100%;">
                    <table class="estable table table-bordered shadow">
                        <thead>
                            <tr>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:40px;">S.No</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:100px;">Employee Code</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:180px;">Employee Name</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:120px;">Payment Amount</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:120px;">Finance Amount</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:100px;">Paid Amount</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:100px;">Adjustment</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:120px;">Unsettled Date</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:250px;">Reason</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:80px;">Status</th>
                                <th style="font-size:10px;vertical-align:middle;background-color: #15648C;color:white;width:140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $index => $payment)
                                @php
                                    $paymentAmount = floatval($payment['payment_amount'] ?? 0);
                                    $financeAmount = floatval($payment['Finance_Amount'] ?? 0);
                                    $adjustmentAmount = floatval($payment['total_adjustment'] ?? 0);
                                    $advanceAmount = floatval($payment['advance_payment_amount'] ?? 0);
                                    $actualExpense = floatval($payment['actual_expense_amount'] ?? 0);
                                @endphp
                                <tr>
                                    <td style="text-align: center;">{{ ($page - 1) * ($per_page ?? 15) + $index + 1 }}
                                    </td>
                                    <td style="text-align: center;">{{ $payment['employee_code'] ?? '-' }}</td>
                                    <td>
                                        <div class="beneficiary-name">{{ $payment['employee_name'] ?? 'Unknown' }}</div>
                                        @if (!empty($payment['account_no']))
                                            <div class="beneficiary-details">A/c: {{ $payment['account_no'] }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align: right; font-weight: 600; color: #dc3545;">
                                        ₹ {{ number_format($paymentAmount, 2) }}
                                    </td>
                                    <td style="text-align: right; color: #28a745;">
                                        ₹ {{ number_format($financeAmount, 2) }}
                                    </td>
                                    <td style="text-align: right;">
                                        @if ($advanceAmount > 0)
                                            <span class="badge-info" style="padding: 2px 5px;">
                                                ₹ {{ number_format($advanceAmount, 2) }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($adjustmentAmount > 0)
                                            <span class="adjustment-info">
                                                ₹ {{ number_format($adjustmentAmount, 2) }}
                                                @if ($payment['total_activity_adjustment'] > 0)
                                                    <br><small>Activity Adj</small>
                                                @endif
                                                @if ($payment['total_expense_adjustment'] > 0)
                                                    <br><small>Expense Adj</small>
                                                @endif
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        {{ !empty($payment['payment_unsettled_date']) && \App\Support\ZeroDate::has($payment['payment_unsettled_date'])
                                            ? date('d-m-Y', strtotime($payment['payment_unsettled_date']))
                                            : '-' }}
                                    </td>
                                    <td>
                                        <div class="reason-text"
                                            title="{{ $payment['payment_unsettled_reason'] ?? '' }}">
                                            {{ $payment['payment_unsettled_reason'] ?: 'No reason provided' }}
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge-danger" style="padding: 3px 8px; border-radius: 3px;">
                                            {{ $payment['payment_status'] ?? 'Unsettled' }}
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn btn-sm btn-info"
                                            onclick="viewPaymentDetails({{ json_encode($payment) }})"
                                            style="padding: 2px 6px; font-size: 10px; margin-right: 5px;">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-success"
                                            onclick="showResolveModal({{ json_encode($payment) }})"
                                            style="padding: 2px 6px; font-size: 10px;">
                                            <i class="fa fa-check-circle"></i> Resolve
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" style="text-align: center; padding: 30px;">
                                        <i class="fa fa-info-circle" style="font-size: 24px; color: #6c757d;"></i><br>
                                        No unsettled payment records found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if ($totalRecords > ($per_page ?? 15))
                    <div class="d-flex justify-content-center mt-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                @php
                                    $perPageValue = $per_page ?? 15;
                                    $totalPages = ceil($totalRecords / $perPageValue);
                                    $currentPage = $page;
                                    $queryParams = request()->except(['page', 'chkval']);
                                    $queryParams['chkval'] = 5;
                                @endphp

                                <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                                    <a class="page-link"
                                        href="{{ $currentPage > 1 ? route('expense-payments.index', array_merge($queryParams, ['page' => $currentPage - 1])) : '#' }}">
                                        &laquo;
                                    </a>
                                </li>

                                @for ($i = 1; $i <= min(10, $totalPages); $i++)
                                    <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                                        <a class="page-link"
                                            href="{{ route('expense-payments.index', array_merge($queryParams, ['page' => $i])) }}">
                                            {{ $i }}
                                        </a>
                                    </li>
                                @endfor

                                @if ($totalPages > 10)
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="{{ route('expense-payments.index', array_merge($queryParams, ['page' => $totalPages])) }}">
                                            {{ $totalPages }}
                                        </a>
                                    </li>
                                @endif

                                <li class="page-item {{ $currentPage == $totalPages ? 'disabled' : '' }}">
                                    <a class="page-link"
                                        href="{{ $currentPage < $totalPages ? route('expense-payments.index', array_merge($queryParams, ['page' => $currentPage + 1])) : '#' }}">
                                        &raquo;
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- View Payment Details Modal --}}
    <div class="modal fade" id="viewPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #15648C; color: white;">
                    <h5 class="modal-title text-white">
                        <i class="fa fa-eye"></i> Unsettled Payment Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div id="viewDetailsContent">
                        <!-- Content will be populated dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Resolve Payment Modal - Manual Update Only --}}
    {{-- Resolve Payment Modal - Manual Update Only --}}
    <div class="modal fade" id="resolvePaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #28a745; color: white;">
                    <h5 class="modal-title text-white">
                        <i class="fa fa-check-circle"></i> Resolve Unsettled Payment - Manual Adjustment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div id="resolveModalMessage" class="alert" style="display: none;"></div>

                    <input type="hidden" id="resolvePaymentId" value="">
                    <input type="hidden" id="resolveActivityId" value="">
                    <input type="hidden" id="resolveYearId" value="{{ session('year_id') }}">

                    <!-- Payment Summary -->
                    <div class="card mb-3">
                        <div class="card-header bg-light fw-bold">
                            <i class="fa fa-info-circle"></i> Payment Summary
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td width="40%"><strong>Payment ID:</strong></td>
                                            <td><span id="resolvePaymentIdDisplay">-</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Employee Name:</strong></td>
                                            <td><span id="resolveEmployeeName">-</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Employee Code:</strong></td>
                                            <td><span id="resolveEmployeeCode">-</span></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td width="40%"><strong>Payment Amount:</strong></td>
                                            <td class="text-danger fw-bold"><span id="resolvePaymentAmount">-</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Finance Amount:</strong></td>
                                            <td class="text-success"><span id="resolveFinanceAmount">-</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Discrepancy:</strong></td>
                                            <td class="text-warning"><span id="resolveDiscrepancy">-</span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Issue -->
                    <div class="card mb-3">
                        <div class="card-header bg-warning fw-bold">
                            <i class="fa fa-exclamation-triangle"></i> Current Issue
                        </div>
                        <div class="card-body">
                            <p id="resolveCurrentIssue" class="mb-0"></p>
                            <small class="text-muted">Unsettled Date: <span id="resolveUnsettledDate"></span></small>
                        </div>
                    </div>

                    <!-- Manual Adjustment Fields -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white fw-bold">
                            <i class="fa fa-cogs"></i> Payment Resolution Details
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Payment Amount -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Payment Amount (₹) <span
                                            class="text-danger">*</span></label>
                                    <input type="number" id="paymentAmount" class="form-control" step="0.01"
                                        placeholder="Enter payment amount" aria-label="Enter payment amount" required>
                                    <small class="text-muted">Actual payment amount being processed</small>
                                </div>

                                <!-- Payment Date -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Payment Date <span
                                            class="text-danger">*</span></label>
                                    <input type="date" id="paymentDate" class="form-control" required>
                                    <small class="text-muted">Date when payment is made</small>
                                </div>

                                <!-- UTR/Reference Number -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">UTR / Reference Number <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="utrReference" class="form-control"
                                        placeholder="Enter UTR number or reference" aria-label="Enter UTR number or reference" required>
                                    <small class="text-muted">Unique Transaction Reference or Bank Reference Number</small>
                                </div>

                                <!-- Remarks -->
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Remarks / Notes</label>
                                    <textarea id="remarks" class="form-control" rows="4"
                                        placeholder="Enter detailed remarks about this resolution..." aria-label="Enter detailed remarks about this resolution"></textarea>
                                    <small class="text-muted">Provide explanation, reason for adjustment, and any
                                        additional information</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmResolveBtn">
                        <i class="fa fa-check-circle"></i> Confirm Resolution
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/cdn/jquery@3.6.0/dist/jquery.min.js') }}"></script>
    <script>
        // ==========================================
        // GLOBAL VARIABLES
        // ==========================================
        const API_LOCAL_BASE_URL = "{{ config('app.api_local_base_url') }}";
        const yearId = {{ session('year_id') }};
        let currentPaymentData = null;

        // ==========================================
        // UTILITY FUNCTIONS
        // ==========================================
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        function showMessage(message, type = 'danger', container = 'Message') {
            const messageDiv = document.getElementById(container);
            if (messageDiv) {
                messageDiv.className = 'alert alert-' + type;
                messageDiv.innerHTML = message;
                messageDiv.style.display = 'block';

                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }
        }

        function formatAmount(amount) {
            const num = parseFloat(amount) || 0;
            return '₹ ' + num.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') return '-';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        // ==========================================
        // SHOW RESOLVE MODAL - UPDATED VERSION
        // ==========================================
        function showResolveModal(payment) {
            currentPaymentData = payment;

            const paymentAmount = parseFloat(payment.payment_amount || 0);
            const financeAmount = parseFloat(payment.Finance_Amount || 0);
            const discrepancy = paymentAmount - financeAmount;

            $('#resolvePaymentId').val(payment.id);
            $('#resolveActivityId').val(payment.activity_id);
            $('#resolvePaymentIdDisplay').text(payment.id);
            $('#resolveEmployeeName').text(payment.employee_name || 'Unknown');
            $('#resolveEmployeeCode').text(payment.employee_code || '-');
            $('#resolvePaymentAmount').html(formatAmount(paymentAmount));
            $('#resolveFinanceAmount').html(formatAmount(financeAmount));
            $('#resolveDiscrepancy').html(formatAmount(discrepancy));
            $('#resolveCurrentIssue').text(payment.payment_unsettled_reason || 'No reason provided');
            $('#resolveUnsettledDate').text(formatDate(payment.payment_unsettled_date));

            // Set default payment amount from the existing payment
            $('#paymentAmount').val(paymentAmount.toFixed(2));

            // Set default payment date to today
            const today = new Date().toISOString().split('T')[0];
            $('#paymentDate').val(today);

            // Reset other fields
            $('#utrReference').val('');
            $('#remarks').val('');
            $('#resolveModalMessage').hide().empty();

            const modal = new bootstrap.Modal(document.getElementById('resolvePaymentModal'));
            modal.show();
        }

        // ==========================================
        // CONFIRM RESOLVE BUTTON - UPDATED VERSION
        // ==========================================
        $('#confirmResolveBtn').on('click', function() {
            const paymentId = $('#resolvePaymentId').val();
            const activityId = $('#resolveActivityId').val();
            const paymentAmount = $('#paymentAmount').val();
            const paymentDate = $('#paymentDate').val();
            const utrReference = $('#utrReference').val();
            const remarks = $('#remarks').val();

            // Validation
            if (!paymentId) {
                showMessage('Payment ID is required', 'warning', 'resolveModalMessage');
                return;
            }

            if (!paymentAmount || parseFloat(paymentAmount) <= 0) {
                showMessage('Please enter a valid payment amount', 'warning', 'resolveModalMessage');
                return;
            }

            if (!paymentDate) {
                showMessage('Please select payment date', 'warning', 'resolveModalMessage');
                return;
            }

            if (!utrReference.trim()) {
                showMessage('Please enter UTR/Reference number', 'warning', 'resolveModalMessage');
                return;
            }

            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<i class="fa fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

            // Prepare the data to send
            let formData = new FormData();
            formData.append('payment_id', paymentId);
            formData.append('activity_id', activityId);
            formData.append('payment_amount', parseFloat(paymentAmount));
            formData.append('payment_date', paymentDate);
            formData.append('utr_reference', utrReference);
            formData.append('remarks', remarks);
            formData.append('year_id', parseInt(yearId));
            formData.append('_token', '{{ csrf_token() }}');

            // Send AJAX request
            $.ajax({
                url: `{{ url('/payment-verification/unsettled') }}/${paymentId}/resolve`,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showMessage('Payment resolved successfully!', 'success');

                        const modal = bootstrap.Modal.getInstance(document.getElementById(
                            'resolvePaymentModal'));
                        if (modal) {
                            modal.hide();
                        }

                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage(response.message || 'Failed to resolve payment', 'danger',
                            'resolveModalMessage');
                        $btn.html(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Error resolving payment: ' + error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    showMessage(errorMessage, 'danger', 'resolveModalMessage');
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        });
        // ==========================================
        // VIEW PAYMENT DETAILS
        // ==========================================
        function viewPaymentDetails(payment) {
            currentPaymentData = payment;

            const paymentAmount = parseFloat(payment.payment_amount || 0);
            const financeAmount = parseFloat(payment.Finance_Amount || 0);
            const adjustmentAmount = parseFloat(payment.total_adjustment || 0);
            const advanceAmount = parseFloat(payment.advance_payment_amount || 0);

            const html = `
                        <div class="container-fluid">
                            <div class="row">

                                <!-- LEFT SIDE -->
                                <div class="col-md-8">

                                    <!-- Transaction Information -->
                                    <h5 class="section-title">Transaction Information</h5>
                                    <div class="row mb-2">
                                        <div class="col-md-4 fw-bold">Beneficiary:</div>
                                        <div class="col-md-8">${payment.employee_name || '-'}</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-md-4 fw-bold">Account No:</div>
                                        <div class="col-md-8">${payment.account_no || '-'}</div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4 fw-bold">Status:</div>
                                        <div class="col-md-8 text-danger">${payment.payment_status || 'Unsettled'}</div>
                                    </div>

                                    <!-- Reason -->
                                    <h5 class="section-title">Reason for Discrepancy</h5>
                                    <div class="reason-box mb-4">
                                        ${payment.payment_unsettled_reason || 'No issue description available'}
                                    </div>

                                    <!-- Activity Details -->
                                    <h5 class="section-title">Activity Details</h5>
                                    <div class="row mb-3">
                                        <div class="col-md-4 fw-bold">Activity ID:</div>
                                        <div class="col-md-2">${payment.activity_id || '-'}</div>

                                        <div class="col-md-3 fw-bold">Finance Date:</div>
                                        <div class="col-md-3">${formatDate(payment.Finance_Date)}</div>
                                    </div>

                                    <!-- Manual Payment -->
                                    <div class="manual-box mt-4 p-3">
                                        <h6 class="mb-3">Manual Payment</h6>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Transaction Reference *</label>
                                                <input type="text" class="form-control" placeholder="Enter reference" aria-label="Enter reference">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Payment Amount (₹) *</label>
                                                <input type="text" class="form-control" value="${paymentAmount.toFixed(2)}">
                                            </div>
                                        </div>

                                        <!-- NEW FIELDS -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Payment Date *</label>
                                                <input type="date" class="form-control">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">UTR Reference *</label>
                                                <input type="text" class="form-control" placeholder="Enter UTR number" aria-label="Enter UTR number">
                                            </div>
                                        </div>

                                    </div>

                                </div>

                                <!-- RIGHT SIDE -->
                                <div class="col-md-4">
                                    <div class="records-box p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Available Payment Records</h6>
                                            <span class="badge bg-primary">0</span>
                                        </div>

                                        <div class="text-center text-muted mt-4">
                                            No matching payment records found
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        `;
            document.getElementById('viewDetailsContent').innerHTML = html;

            const modal = new bootstrap.Modal(document.getElementById('viewPaymentModal'));
            modal.show();
        }

        function getUser(employeeId) {
            if (currentPaymentData && currentPaymentData.EmployeeID === employeeId) {
                return currentPaymentData.employee_name || 'Unknown';
            }
            return 'Unknown';
        }

        function getCode(employeeId) {
            if (currentPaymentData && currentPaymentData.EmployeeID === employeeId) {
                return currentPaymentData.employee_code || '-';
            }
            return '-';
        }

        // ==========================================
        // INITIALIZATION
        // ==========================================
        $(document).ready(function() {
            console.log('Document ready - Unsettled Expense Payments page');

            const totalRecords = {{ $totalRecords }};
            const totalAmount = {{ array_sum(array_column($records, 'payment_amount')) }};

            if (totalRecords > 0) {
                $('#unsettledSummary').html(`
                                            <span class="badge bg-secondary me-2">Total: ${totalRecords}</span>
                                            <span class="badge bg-danger me-2">Total Amount: ${formatAmount(totalAmount)}</span>
                                            <span class="badge bg-warning">Discrepancy: ${formatAmount({{ array_sum(array_column($records, 'payment_amount')) - array_sum(array_column($records, 'Finance_Amount')) }})}</span>
                                        `);
            }
        });

        function FselMonth() {
            const month = $('#SelMonth').val();
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('chkval', '5');
            currentUrl.searchParams.set('v', month);
            window.location.href = currentUrl.toString();
        }

        // Helper function to get employee name from PHP (you may need to pass this data from controller)
        window.getUser = getUser;
        window.getCode = getCode;
        window.formatAmount = formatAmount;
        window.formatDate = formatDate;
    </script>
    <script>
        function verifyUnsettledPayments() {
            if (confirm(
                    'Are you sure you want to verify unsettled payments? This will check for unmatched expenses and advances.'
                    )) {
                // Show loading state
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Verifying...';
                button.disabled = true;

                fetch('/payment-verification/verify-unsettled', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Verification completed. Matched: ' +
                                data.data.expense.matched + ' expense, ' +
                                data.data.advance.matched + ' advance');
                            location.reload(); // Refresh to show updated status
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to verify unsettled payments');
                    })
                    .finally(() => {
                        // Reset button state
                        button.textContent = originalText;
                        button.disabled = false;
                    });
            }
        }
    </script>
@endsection
