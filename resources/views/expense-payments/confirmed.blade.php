{{-- resources/views/expense-payments/confirmed.blade.php --}}
@extends('layouts.app')

@section('styles')
    <style>
        .status-tabs {
            border-bottom: 1px solid #e5e7eb;
            margin-top: 10px;
            padding: 0 5px;
        }

        .status-tabs label {
            display: inline-block;
            padding: 8px 14px;
            margin-right: 8px;
            font-size: 14px;
            cursor: pointer;
            color: #2563eb;
            border-bottom: 2px solid transparent;
            margin-bottom: 0;
        }

        .status-tabs input[type="radio"] {
            display: none;
        }

        .status-tabs input[type="radio"]:checked+label {
            font-weight: 600;
            border-bottom: 2px solid #2563eb;
            color: #000;
        }

        .estable {
            font-size: 10px;
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .estable th {
            background-color: #dde6ff;
            color: #1e3a8a;
            padding: 8px 4px;
            font-size: 10px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .estable td {
            padding: 8px 4px;
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
            background-color: #dde6ff;
            border-color: #007bff;
            color: #1e3a8a;
        }

        .btn-success {
            background-color: #d7f3e6;
            border-color: #28a745;
            color: #065f46;
            padding: 4px 12px;
        }

        .btn-edit {
            background-color: #fdefd2;
            border-color: #ffc107;
            color: #000;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 5px;
        }

        .print-link {
            color: #000099;
            cursor: pointer;
            text-decoration: underline;
            margin: 0 2px;
        }

        .view-link {
            color: #007bff;
            cursor: pointer;
            text-decoration: underline;
        }

        .month-select {
            font-size: 13px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .remark-input {
            font-size: 10px;
            width: 100%;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 11px;
        }

        .remark-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .edit-btn {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 12px;
            padding: 0 3px;
        }

        .edit-btn:hover {
            text-decoration: underline;
        }

        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 20px 0;
            font-family: Times New Roman;
            font-size: 15px;
            font-weight: bold;
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
        }

        .pagination li.active span {
            background-color: #dde6ff;
            color: #1e3a8a;
            border-color: #007bff;
        }

        .pagination li.disabled span {
            color: #6c757d;
            pointer-events: none;
        }

        .modal-xl {
            max-width: 1400px;
        }

        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }

        .filter-input form-control form-control-sm {
            font-size: 12px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            width: 100%;
        }

        .filter-label {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 3px;
            color: #495057;
        }

        .clear-filter-btn {
            margin-top: 24px;
        }

        .badge-active-filter {
            background-color: #dde6ff;
            color: #1e3a8a;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 5px;
        }
    </style>
@endsection

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row py-3 bg-white border-bottom">
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

        <div class="row">
            <div class="col-lg-12 shadow">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 0px;">
                    <div><b style="font-size: 16px;">Confirmed Payment List</b></div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size:14px;">Month :</span>
                        <select class="month-select" id="SelMonth">
                            <option value="0" {{ request('v', 0) == 0 ? 'selected' : '' }}>All</option>
                            <option value="01" {{ request('v') == 1 ? 'selected' : '' }}>January</option>
                            <option value="02" {{ request('v') == 2 ? 'selected' : '' }}>February</option>
                            <option value="03" {{ request('v') == 3 ? 'selected' : '' }}>March</option>
                            <option value="04" {{ request('v') == 4 ? 'selected' : '' }}>April</option>
                            <option value="05" {{ request('v') == 5 ? 'selected' : '' }}>May</option>
                            <option value="06" {{ request('v') == 6 ? 'selected' : '' }}>June</option>
                            <option value="07" {{ request('v') == 7 ? 'selected' : '' }}>July</option>
                            <option value="08" {{ request('v') == 8 ? 'selected' : '' }}>August</option>
                            <option value="09" {{ request('v') == 9 ? 'selected' : '' }}>September</option>
                            <option value="10" {{ request('v') == 10 ? 'selected' : '' }}>October</option>
                            <option value="11" {{ request('v') == 11 ? 'selected' : '' }}>November</option>
                            <option value="12" {{ request('v') == 12 ? 'selected' : '' }}>December</option>
                        </select>

                        <a class="btn btn-sm btn-primary" onclick="FselMonth()" style="padding: 4px 16px;">
                            <span style="color:#FFFFFF;">Click</span>
                        </a>

                        <a class="btn btn-sm btn-primary" href="javascript:location.reload(true)"
                            style="padding: 4px 12px;">
                            <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
                        </a>
                    </div>
                </div>

                {{-- Advanced Filter Section --}}
                <div class="filter-section">
                    <form method="GET" action="{{ route('expense-payments.index') }}" id="filterForm">
                        <input type="hidden" name="chkval" value="{{ request('chkval', 4) }}">
                        <input type="hidden" name="v" value="{{ request('v', 0) }}">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="filter-label">Employee Name / Code</div>
                                <input type="text" class="filter-input form-control form-control-sm"
                                    name="employee_search"
                                    value="{{ request('employee_search', $data['employee_search'] ?? '') }}"
                                    placeholder="Search by name or code..." aria-label="Search by name or code">
                            </div>
                            <div class="col-md-2">
                                <div class="filter-label">Payment Date From</div>
                                <input type="date" class="filter-input form-control form-control-sm"
                                    name="payment_date_from"
                                    value="{{ request('payment_date_from', $data['payment_date_from'] ?? '') }}">
                            </div>
                            <div class="col-md-2">
                                <div class="filter-label">Payment Date To</div>
                                <input type="date" class="filter-input form-control form-control-sm"
                                    name="payment_date_to"
                                    value="{{ request('payment_date_to', $data['payment_date_to'] ?? '') }}">
                            </div>
                            <div class="col-md-2">
                                <div class="filter-label">Amount Range (Min)</div>
                                <input type="number" class="filter-input form-control form-control-sm" name="min_amount"
                                    value="{{ request('min_amount', $data['min_amount'] ?? '') }}" placeholder="Min Amount" aria-label="Min Amount"
                                    step="0.01">
                            </div>
                            <div class="col-md-2">
                                <div class="filter-label">Amount Range (Max)</div>
                                <input type="number" class="filter-input form-control form-control-sm" name="max_amount"
                                    value="{{ request('max_amount', $data['max_amount'] ?? '') }}" placeholder="Max Amount" aria-label="Max Amount"
                                    step="0.01">
                            </div>
                            <div class="col-md-1 clear-filter-btn mt-auto">
                                <button type="submit" class="btn btn-sm btn-primary" style="width: 100%;">
                                    <i class="fa fa-search"></i> Filter
                                </button>
                            </div>

                            <div class="col-md-1 mt-auto">
                                <a href="{{ route('expense-payments.index', ['chkval' => 4, 'v' => request('v', 0)]) }}"
                                    class="btn btn-sm btn-secondary">
                                    <i class="fa fa-times"></i> Clear Filters
                                </a>

                            </div>
                        </div>



                    </form>
                </div>

                <div style="overflow-x: auto; font-size:10px;width: 100%;">
                    <table class="estable table table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th
                                    style="font-size:10px;width:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    S.No</th>
                                <th
                                    style="font-size:10px;width:200px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Claimant</th>
                                <th
                                    style="font-size:10px;width:50px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    EmpCode</th>
                                <th
                                    style="font-size:10px;width:50px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Grade</th>
                                <th
                                    style="font-size:10px;width:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Status</th>
                                <th
                                    style="font-size:10px;width:50px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Month</th>
                                <th
                                    style="font-size:10px;width:60px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Total Claims</th>
                                <th
                                    style="font-size:10px;width:100px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Payment Amount</th>
                                <th
                                    style="font-size:10px;width:100px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Payment Date</th>
                                <th
                                    style="font-size:10px;width:100px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    UTR Number</th>
                                <th
                                    style="font-size:10px;width:100px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Payment Remark</th>
                                <th
                                    style="font-size:10px;width:100px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Print</th>
                                <th
                                    style="font-size:10px;width:60px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">
                                    Account Summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $page = request('page', 1);
                                $perPage = 15;
                                $i = ($page - 1) * $perPage + 1;
                            @endphp

                            @forelse($records as $record)
                                @php
                                    $employeeStatus = getStsUser($record['EmployeeID'] ?? 0);
                                    $rowBgColor = in_array($employeeStatus, ['D', 'R']) ? '#FFFFA6' : '';

                                    // Format dates
                                    $paymentDate = '';
                                    if (
                                        !empty($record['payment_confirmed_date']) &&
                                        \App\Support\ZeroDate::has($record['payment_confirmed_date'])
                                    ) {
                                        $paymentDate = date('d-m-Y', strtotime($record['payment_confirmed_date']));
                                    } elseif (
                                        !empty($record['payment_date']) &&
                                        \App\Support\ZeroDate::has($record['payment_date'])
                                    ) {
                                        $paymentDate = date('d-m-Y', strtotime($record['payment_date']));
                                    } elseif (
                                        !empty($record['Fin_PayDate']) &&
                                        \App\Support\ZeroDate::has($record['Fin_PayDate'])
                                    ) {
                                        $paymentDate = date('d-m-Y', strtotime($record['Fin_PayDate']));
                                    } elseif (
                                        !empty($record['payment_instructed_date']) &&
                                        \App\Support\ZeroDate::has($record['payment_instructed_date'])
                                    ) {
                                        $paymentDate = date('d-m-Y', strtotime($record['payment_instructed_date']));
                                    }

                                    // Get payment amount
                                    $paymentAmount = $record['Fin_PayAmt'] ?? ($record['payment_amount'] ?? 0);

                                    // Get payment remark
                                    $paymentRemark = $record['Fin_PayRemark'] ?? '';

                                    // Get UTR number
                                    $utrNumber = $record['utr_number'] ?? '';

                                    $month = $record['Month'] ?? 1;
                                    $employeeId = $record['EmployeeID'] ?? 0;
                                    $yearId = $record['YearId'] ?? 7;
                                    $totalClaim = $record['Total_Claim'] ?? 0;
                                    $finAppById = $record['Fin_AppBy'] ?? 0;
                                    $finPayById = $record['Fin_PayBy'] ?? 0;
                                    $docNo = $record['DocNo'] ?? '';

                                    $recordId = $record['id'] ?? '';
                                @endphp

                                <tr style="background-color: {{ $rowBgColor }};">
                                    <td style="font-size:10px;text-align: center;">
                                        <input type="hidden" id="fin_app_{{ $finAppById }}"
                                            value="{{ getUser($finAppById) }}">
                                        <input type="hidden" id="fin_pay_{{ $finPayById }}"
                                            value="{{ getUser($finPayById) }}">
                                        {{ $i }}
                                    </td>
                                    <td style="font-size:10px;text-align: left;">
                                        {{ getUser($employeeId) }}
                                        <small style="color: #6c757d; display: block; font-size: 9px;">Doc:
                                            {{ $docNo }}</small>
                                    </td>
                                    <td style="font-size:10px;text-align: center;">{{ getCode($employeeId) }}</td>
                                    <td style="font-size:10px;text-align: center;">{{ getGrade($employeeId) }}</td>
                                    <td style="font-size:10px;text-align: center;">{{ $employeeStatus }}</td>
                                    <td style="font-size:10px;text-align: center;">
                                        <a href="#"
                                            onclick="showmonthdet('{{ $month }}','Closed','{{ $employeeId }}','Closed')"
                                            style="color: #007bff;">
                                            {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                        </a>
                                    </td>
                                    <td style="font-size:10px;text-align: center;">{{ number_format($totalClaim) }}</td>
                                    <td style="font-size:10px;text-align: center;">
                                        <strong>{{ number_format((float) $paymentAmount, 2) }}</strong>
                                    </td>
                                    <td style="font-size:10px;text-align: center;">{{ $paymentDate }}</td>
                                    <td style="font-size:10px;text-align: center;">
                                        @if ($utrNumber)
                                            <span
                                                style="font-family: monospace; font-size: 9px;">{{ $utrNumber }}</span>
                                        @elseif (! empty($record['utr_exempt_reason']))
                                            {{-- UTR-02: Finance's reason for no UTR --}}
                                            <span style="color: #6c757d;" title="{{ \App\Support\PaymentUtr::EXEMPT_REASONS[$record['utr_exempt_reason']] ?? '' }}">{{ ['ff_hold' => 'F&F hold', 'da_payroll' => 'DA - payroll', 'advance_adjusted' => 'Adj. advance'][$record['utr_exempt_reason']] ?? '-' }}</span>
                                        @else
                                            <span style="color: #6c757d;">-</span>
                                        @endif
                                    </td>
                                    <td style="font-size:10px;text-align: left;">
                                        <div class="remark-container">
                                            <input style="font-size: 10px;" type="text"
                                                class="remark-input form-control" id="remark_{{ $recordId }}"
                                                value="{{ $paymentRemark }}"
                                                onchange="editRemark('{{ $recordId }}', '{{ addslashes($paymentRemark) }}')"
                                                data-original="{{ $paymentRemark }}">
                                        </div>
                                    </td>
                                    <td style="font-size:10px;text-align: center;">
                                        <span
                                            onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 1)"
                                            class="print-link cursor-pointer text-primary">One</span>
                                        &nbsp;|&nbsp;
                                        <span
                                            onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 2)"
                                            class="print-link cursor-pointer text-primary">Two</span>
                                    </td>
                                    <td style="font-size:10px;text-align: center;">
                                        <button onclick='showPaymentDetails(@json($record))'
                                            class="btn btn-sm btn-info" style="padding: 2px 8px; font-size: 10px;">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                @php $i++; @endphp
                            @empty
                                <tr>
                                    <td colspan="13"
                                        style="font-size:10px;text-align: center; padding: 30px; font-size: 14px;">
                                        No confirmed payment records found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if (isset($totalRecords) && $totalRecords > 0)
                    @php
                        $currentPage = request('page', 1);
                        $perPage = 15;
                        $totalPages = ceil($totalRecords / $perPage);

                        // Get all current query parameters except 'page'
                        $queryParams = request()->except('page');
                        $queryString = http_build_query($queryParams);

                        $start = max($currentPage - 2, 1);
                        $end = min($start + 4, $totalPages);
                        if ($end - $start < 4) {
                            $start = max($end - 4, 1);
                        }
                    @endphp

                    <div class="d-flex flex-column align-items-center mt-4">
                        <nav>
                            <ul class="pagination pagination-sm">
                                <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                                    <a class="page-link"
                                        href="{{ request()->url() }}?page={{ $currentPage - 1 }}&{{ $queryString }}">
                                        &laquo;
                                    </a>
                                </li>

                                @if ($start > 1)
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="{{ request()->url() }}?page=1&{{ $queryString }}">1</a>
                                    </li>
                                    @if ($start > 2)
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    @endif
                                @endif

                                @for ($p = $start; $p <= $end; $p++)
                                    <li class="page-item {{ $p == $currentPage ? 'active' : '' }}">
                                        @if ($p == $currentPage)
                                            <span class="page-link">{{ $p }}</span>
                                        @else
                                            <a class="page-link"
                                                href="{{ request()->url() }}?page={{ $p }}&{{ $queryString }}">
                                                {{ $p }}
                                            </a>
                                        @endif
                                    </li>
                                @endfor

                                @if ($end < $totalPages)
                                    @if ($end < $totalPages - 1)
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    @endif
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="{{ request()->url() }}?page={{ $totalPages }}&{{ $queryString }}">
                                            {{ $totalPages }}
                                        </a>
                                    </li>
                                @endif

                                <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                                    <a class="page-link"
                                        href="{{ request()->url() }}?page={{ $currentPage + 1 }}&{{ $queryString }}">
                                        &raquo;
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <div class="mt-2 fw-bold text-danger">
                            Total {{ $totalRecords }} Records
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Payment Details Modal --}}
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="width:100%">
                <div class="modal-header" style="background-color: #dde6ff; color: #1e3a8a;">
                    <h5 class="modal-title text-white" id="paymentDetailsModalLabel">
                        <i class="fa fa-file-text-o"></i> Confirmed Payment Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 85vh; overflow-y: auto; padding: 15px;"
                    id="paymentDetailsContent">
                    {{-- Content will be populated by JavaScript --}}
                </div>
            </div>
        </div>
    </div>

    @include('expense-payments.partials.modals')
@endsection

<script src="{{ asset('vendor/cdn/jquery@3.6.0/dist/jquery.min.js') }}"></script>
<script type="text/javascript">
    // Month selection function
    function FselMonth() {
        var chkval = '{{ request('chkval', 4) }}';
        var SelMonth = $('#SelMonth').val();

        // Preserve other filter parameters
        var params = new URLSearchParams(window.location.search);
        params.set('v', SelMonth);
        params.set('chkval', chkval);

        window.location = "{{ route('expense-payments.index') }}?" + params.toString();
    }

    // Print function
    function FunPrint(e, m, y, n) {
        window.open("{{ url('expense/print-details') }}?e=" + e + "&y=" + y + "&m=" + m + "&nn=" + n + "&c={{ session('company_id') }}",
            "PForm", "menubar=no,scrollbars=yes,resizable=no,directories=no,width=1200,height=450");
    }

    // Show month details
    function showmonthdet(month, sts, emp, csts) {
        var status = document.getElementById('sts' + emp + month + csts);
        var modal = document.getElementById(emp + month + csts);
        if (status && status.value == 'close') {
            $.post("{{ url('claimlistajax') }}", {
                    act: "monthdettofinance",
                    month: month,
                    sts: sts,
                    csts: csts,
                    emp: emp
                },
                function(data) {
                    if (modal) modal.innerHTML = data;
                }
            );
            if (status) status.value = "open";
        } else if (status && status.value == 'open') {
            if (modal) modal.innerHTML = '';
            if (status) status.value = "close";
        }
    }

    // Edit remark function
    function editRemark(recordId, currentRemark) {
        var newRemark = prompt('Enter payment remark:', currentRemark);

        if (newRemark !== null && newRemark !== currentRemark) {
            updateRemark(recordId, newRemark);
        }
    }

    // Update remark via AJAX
    function updateRemark(recordId, newRemark) {
        $.ajax({
            url: '{{ route('expense-payments.update-remark') }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: recordId,
                finPayRemark: newRemark
            },
            success: function(res) {
                if (res.success) {
                    $('#remark_' + recordId).val(newRemark);
                    alert('Remark updated successfully!');
                } else {
                    alert('Error: ' + (res.message || 'Failed to update remark'));
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX Error: ' + error);
            }
        });
    }

    // Show payment details modal
    // Show payment details modal
    function showPaymentDetails(record) {
        // Parse the record if it's a string
        if (typeof record === 'string') {
            record = JSON.parse(record);
        }

        // Format currency
        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(parseFloat(amount || 0));
        };

        // Format date
        const formatDate = (dateStr) => {
            if (!dateStr || dateStr === '0000-00-00' || dateStr === '0000-00-00 00:00:00' || dateStr === '')
            return 'N/A';
            return new Date(dateStr).toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        };

        // Get month name
        const getMonthName = (month) => {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months[parseInt(month) - 1] || 'N/A';
        };

        // Get status badge color
        const getStatusBadge = (status) => {
            const colors = {
                'Closed': '#28a745',
                'ConfirmedPayment': '#007bff',
                'PENDING': '#ffc107',
                'SUCCESS': '#28a745',
                'MATCHED': '#28a745'
            };
            return colors[status] || '#6c757d';
        };

        const approver = $('#fin_app_' + record.Fin_AppBy).val() || 'N/A';
        const payer = $('#fin_pay_' + record.Fin_PayBy).val() || 'N/A';

        // Build the modal content
        const content = `
        <div class="row">
            <div class="col-8">
                {{-- Request Information --}}
                <div class="mb-3 text-start">
                    <h6 style="color: #155997; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">
                        <i class="fa fa-file-text-o"></i> Request Information
                    </h6>
                    <div class="row">
                        <div class="col-4">
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <strong>Employee:</strong> ${record.beneficiary_name} (${record.EmployeeID})
                            </div>
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <strong>Document No:</strong> ${record.DocNo || 'N/A'}
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <strong>Month:</strong> ${getMonthName(record.Month)}
                            </div>
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <strong>Submit Date:</strong> ${formatDate(record.DateOfSubmit)}
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <strong>Total Claims:</strong> ${record.Total_Claim || 0}
                            </div>
                            <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                <strong>Move Focus Sheet:</strong> ${record.Move_FocuseSheet == 'Y' ? 'Yes' : 'No'}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amount Details --}}
                <div class="mb-3 text-start">
                    <h6 style="color: #155997; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">
                        <i class="fa fa-money"></i> Amount Details
                    </h6>
                    <div class="row">
                        <div class="col-3">
                            <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                <strong>Claim Amount:</strong><br>
                                <span style="color: #155997;">₹${formatCurrency(record.Claim_Amount)}</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                <strong>Verified Amount:</strong><br>
                                <span style="color: #28a745;">₹${formatCurrency(record.Verified_Amount)}</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                <strong>Approved Amount:</strong><br>
                                <span style="color: #28a745;">₹${formatCurrency(record.Approved_Amount)}</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div style="font-size: 0.75rem; margin-bottom: 8px;">
                                <strong>Finance Amount:</strong><br>
                                <span style="color: #28a745;">₹${formatCurrency(record.Finance_Amount)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Account Summary --}}
                <div class="mb-3">
                    <h6 style="color: #155997; margin-bottom: 10px;">
                        <i class="fa fa-file-text-o"></i> Account Summary
                    </h6>
                    <iframe src="{{ url('account-summary') }}?e=${record.EmployeeID}&y=${record.YearId}&CompanyId=1"
                            width="100%" height="420"
                            style="border:1px solid #dee2e6;border-radius:4px;"
                            frameborder="0" scrolling="yes">
                    </iframe>
                </div>

                {{-- Payment Transaction Details Card --}}
                <div class="card mb-3 text-start" style="border: 1px solid #dee2e6;">
                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                        <i class="fa fa-university"></i> Payment Transaction Details
                    </div>
                    <div class="card-body row" style="padding: 12px;">
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Beneficiary Name:</div>
                            <div style="font-size: 0.8rem; font-weight: 500;">${record.beneficiary_name || getUserById(record.EmployeeID)}</div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Beneficiary Account:</div>
                            <div style="font-size: 0.8rem; font-family: monospace;">
                                ${record.beneficiary_account_number ? record.beneficiary_account_number.replace(/(\d{4})(?=\d)/g, '$1 ') : 'N/A'}
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Bank Name:</div>
                            <div style="font-size: 0.8rem;">${record.bank_name || 'N/A'}</div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">IFSC Code:</div>
                            <div style="font-size: 0.8rem; font-family: monospace;">${record.ifsc_code || 'N/A'}</div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">UTR / Reference No:</div>
                            <div style="font-size: 0.8rem; font-family: monospace; font-weight: 600; color: #28a745;">
                                ${record.rbi_utr_reference_number || record.transaction_refno || 'N/A'}
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Amount:</div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #155997;">
                                ₹${formatCurrency(record.Fin_PayAmt)}
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Date:</div>
                            <div style="font-size: 0.8rem;">${formatDate(record.payment_confirmed_date || record.payment_instructed_date)}</div>
                        </div>


                        ${record.Fin_PayRemark ? `
                        <div class="col-md-6 mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Remark:</div>
                            <div style="font-size: 0.75rem; background-color: #f8f9fa; padding: 5px; border-radius: 3px;">${record.Fin_PayRemark}</div>
                        </div>` : ''}
                    </div>
                </div>
            </div>

            <div class="col-4 text-start">
                {{-- Request Status Card --}}
                <div class="card mb-3" style="border: 1px solid #dee2e6;">
                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                        <i class="fa fa-info-circle"></i> Request Status
                    </div>
                    <div class="card-body" style="padding: 12px;">
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Current Status:</div>
                            <div>
                                <span style="display: inline-block; padding: 3px 8px; background-color: ${getStatusBadge(record.Status)}; color: white; border-radius: 12px; font-size: 0.7rem;">
                                    ${record.Status || 'N/A'}
                                </span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Payment Status:</div>
                            <div>
                                <span style="display: inline-block; padding: 3px 8px; background-color: ${getStatusBadge(record.payment_status)}; color: white; border-radius: 12px; font-size: 0.7rem;">
                                    ${record.payment_status || 'N/A'}
                                </span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Export Status:</div>
                            <div>
                                <span style="display: inline-block; padding: 3px 8px; background-color: ${record.is_exported ? '#28a745' : '#6c757d'}; color: white; border-radius: 12px; font-size: 0.7rem;">
                                    ${record.is_exported ? 'Exported' : 'Not Exported'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Finance Details Card --}}
                <div class="card mb-3 text-start" style="border: 1px solid #dee2e6;">
                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                        <i class="fa fa-credit-card"></i> Finance Details
                    </div>
                    <div class="card-body" style="padding: 12px;">
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Finance Approved By:</div>
                            <div style="font-size: 0.8rem;">${approver}</div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Finance Date:</div>
                            <div style="font-size: 0.8rem;">${formatDate(record.Fin_AppDate)}</div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Finance Amount:</div>
                            <div style="font-size: 0.9rem; color: #28a745; font-weight: 600;">
                                ₹${formatCurrency(record.Finance_Amount)}
                            </div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Advance Payment:</div>
                            <div style="font-size: 0.8rem; color: #ff6b6b;">
                                ₹${formatCurrency(record.Fin_AdvancePay)}
                            </div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Paid By:</div>
                            <div style="font-size: 0.8rem;">${payer}</div>
                        </div>
                    </div>
                </div>

                {{-- Adjustment Summary Card --}}
                <div class="card mb-3 text-start" style="border: 1px solid #dee2e6;">
                    <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 8px 12px; font-size: 0.8rem;">
                        <i class="fa fa-calculator"></i> Adjustment Summary
                    </div>
                    <div class="card-body" style="padding: 12px;">
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Activity Adjustment:</div>
                            <div style="font-size: 0.8rem;">₹${formatCurrency(record.total_activity_adjustment)}</div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Expense Adjustment:</div>
                            <div style="font-size: 0.8rem;">₹${formatCurrency(record.total_expense_adjustment)}</div>
                        </div>
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Total Adjustment:</div>
                            <div style="font-size: 0.8rem; font-weight: 600;">₹${formatCurrency(record.total_adjustment)}</div>
                        </div>
                        ${record.advance_payment_amount > 0 ? `
                        <div class="mb-2">
                            <div style="font-size: 0.75rem; color: #6c757d;">Advance Payment:</div>
                            <div style="font-size: 0.8rem;">₹${formatCurrency(record.advance_payment_amount)}</div>
                            <div style="font-size: 0.7rem; color: #6c757d;">Date: ${formatDate(record.advance_payment_date)}</div>
                        </div>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;

        // Set the content and show modal
        document.getElementById('paymentDetailsContent').innerHTML = content;

        // Initialize and show the modal
        var modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
        modal.show();
    }
    // Helper function to get user name
    function getUserById(id) {
        return $('#fin_app_' + id).val() || 'Employee ' + id;
    }



    // Auto-submit filter form on enter key
    $(document).ready(function() {
        $('.filter-input form-control form-control-sm').keypress(function(e) {
            if (e.which == 13) {
                e.preventDefault();
                $('#filterForm').submit();
            }
        });
    });
</script>
