{{-- resources/views/expense-payments/approved.blade.php --}}
@extends('layouts.app')

@section('styles')
    <style>
        /* Add these styles */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            align-items: center;
            justify-content: space-between;
        }

        .selected-count {
            font-size: 12px;
            color: #666;
            margin-left: 10px;
        }

        .hold-column {
            width: 60px;
            text-align: center;
        }

        .hold-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-column {
            width: 30px;
            text-align: center;
        }

        .status-badge {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
        }

        .estable {
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

        .processing-btn {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Remark column styles */
        .remark-column {
            width: 200px;
            text-align: center;
        }

        .remark-input {
            width: 100%;
            padding: 4px 6px;
            font-size: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .remark-input:focus {
            outline: none;
            border-color: #15648C;
            box-shadow: 0 0 3px rgba(21, 100, 140, 0.3);
        }

        .remark-input:disabled {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        .remark-input.hold-active {
            background-color: #fff3cd;
            border-color: #ffc107;
        }

        .save-remark-btn {
            background: none;
            border: none;
            color: #28a745;
            cursor: pointer;
            font-size: 14px;
            padding: 2px 5px;
            margin-left: 5px;
            display: inline-block;
        }

        .save-remark-btn:hover {
            color: #218838;
        }

        .edit-remark-btn {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 12px;
            padding: 2px 5px;
            margin-left: 5px;
            display: inline-block;
        }

        .edit-remark-btn:hover {
            color: #0056b3;
        }

        .remark-container {
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: center;
        }

        .remark-text {
            font-size: 10px;
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            color: #0066cc;
            background-color: #f8f9fa;
            padding: 4px 8px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
        }

        .remark-text:hover {
            text-decoration: underline;
            background-color: #e9ecef;
        }

        .remark-badge {
            background-color: #fdefd2;
            color: #856404;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            display: inline-block;
        }

        .remark-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .saving-indicator {
            font-size: 10px;
            color: #28a745;
            margin-left: 5px;
        }

        .cursor-pointer {
            cursor: pointer;
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

            <div class="row">
                <div class="col-lg-12 shadow">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <b style="font-size: 16px;">Approved List</b>
                                </div>

                                <form method="GET" action="{{ route('expense-payments.index') }}" id="filterForm"
                                    class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                    <input type="hidden" name="chkval" value="2">

                                    @if(session('company_id') == 11)
                                    <select name="courier_status" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">All</option>
                                        <option value="available" {{ request('courier_status') == 'available' ? 'selected' : '' }}>
                                            Courier Available
                                        </option>
                                        <option value="not_available" {{ request('courier_status') == 'not_available' ? 'selected' : '' }}>
                                            Courier Not Available
                                        </option>
                                    </select>
                                    @endif

                                    <input type="text" class="form-control form-control-sm" id="employee_search"
                                        name="employee_search" value="{{ request('employee_search') }}"
                                        placeholder="Search by name or code..." aria-label="Search by name or code" style="width: 250px;">

                                    <select class="form-select form-select-sm" id="SelMonth" name="v"
                                        style="width: auto;" onchange="this.form.submit()">
                                        <option value="0" {{ request('v', 0) == 0 ? 'selected' : '' }}>All Months</option>
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

                                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                    <a href="{{ route('expense-payments.index', ['chkval' => 2]) }}" class="btn btn-secondary btn-sm">Reset</a>
                                    <a href="javascript:location.reload(true)" class="btn btn-secondary btn-sm">Refresh</a>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="action-buttons d-flex justify-content-between my-2">
                        <div>
                            <input type="checkbox" id="selectAll" style="margin-right: 5px;">
                            <label for="selectAll"><strong>Select All</strong></label>
                            <span class="selected-count" id="selectedCount">(Selected: 0)</span>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="processSelectedBtn" onclick="processSelectedPayments()">
                                <i class="fa fa-play"></i> Process Selected Records (Release & Export)
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
                            <button type="button" class="btn btn-sm btn-info text-white" id="previewMatrixBtn" onclick="previewMatrixPayload('reimbursement')">
                                <i class="fa fa-eye"></i> Preview Payload
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="pushToMatrixBtn" onclick="pushSelectedToMatrix()">
                                <i class="fa fa-paper-plane"></i> Push to Matrix
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" id="syncMatrixBtn" onclick="syncMatrixUpdates()">
                                <i class="fa fa-refresh"></i> Sync Matrix Status
                            </button>
                            @endif
                            @if(isSuperAdmin())
                            <button type="button" class="btn btn-sm btn-outline-danger" id="revertSelectedBtn" onclick="openRevertSelectedModal()">
                                <i class="fa fa-undo"></i> Revert Selected to Pending
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openRevertLogsModal()">
                                <i class="fa fa-history"></i> Revert Logs
                            </button>
                            @endif
                        </div>
                    </div>

                    <div style="overflow-x: auto; font-size:10px;width: 100%;">
                        <table class="estable table table-bordered shadow">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:20px">
                                        Select
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:30px">
                                        S.No
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:60px">
                                        Hold
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:200px">
                                        Claimant
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:50px">
                                        EmpCode
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:50px">
                                        Grade
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:10px">
                                        Status
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:50px">
                                        Month
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:60px">
                                        Total Claims
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:100px">
                                        Approved Amount
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:100px">
                                        Approved Date
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:100px">
                                        Print
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:60px">
                                        Account Summary
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:200px">
                                        Hold Remark
                                    </th>
                                    @if(isSuperAdmin())
                                    <th style="font-size:10px;vertical-align:middle;background-color: #fde2e2;color:#9b1c1c;width:80px">
                                        Action
                                    </th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $page = request('page', 1);
                                    $i = ($page - 1) * 1000 + 1;
                                @endphp

                                @forelse($records as $record)
                                    @php
                                        $employeeStatus = getStsUser($record['EmployeeID'] ?? $record['employee_id'] ?? 0);
                                        $rowBgColor = in_array($employeeStatus, ['D', 'R']) ? '#FFFFA6' : '';
                                        $month = $record['Month'] ?? $record['month'] ?? 1;
                                        $employeeId = $record['EmployeeID'] ?? $record['employee_id'] ?? 0;
                                        $employeeName = getUser($employeeId);
                                        $yearId = $record['YearId'] ?? $record['year_id'] ?? session('year_id', date('Y'));
                                        $totalClaim = $record['Total_Claim'] ?? $record['total_claim'] ?? 0;
                                        $paymentAmount = $record['payment_amount'] ?? $record['Finance_Amount'] ?? $record['Approved_Amount'] ?? 0;
                                        $approvedDate = !empty($record['payment_approved_date']) && \App\Support\ZeroDate::has($record['payment_approved_date'])
                                            ? date('d-m-Y', strtotime($record['payment_approved_date']))
                                            : (!empty($record['Fin_AppDate']) && \App\Support\ZeroDate::has($record['Fin_AppDate'])
                                                ? date('d-m-Y', strtotime($record['Fin_AppDate']))
                                                : '');
                                        $recordId = $record['id'] ?? '';
                                        $isExported = $record['is_exported'] ?? 0;
                                        $holdRemark = $record['hold_remark'] ?? '';
                                    @endphp

                                    <tr style="background-color: {{ $rowBgColor }};" data-record-id="{{ $recordId }}">
                                        <td style="font-size:10px;text-align: center;">
                                            <input type="checkbox" class="row-selector"
                                                   data-id="{{ $recordId }}"
                                                   data-emp="{{ $employeeId }}"
                                                   data-month="{{ $month }}"
                                                   data-amount="{{ $paymentAmount }}"
                                                   data-hold="{{ $isExported == 2 ? '1' : '0' }}">
                                        </td>
                                        <td style="font-size:10px;text-align: center;">{{ $i }}</td>
                                        <td style="font-size:10px;text-align: center;" class="hold-column">
                                            <input type="checkbox" class="hold-checkbox"
                                                   data-id="{{ $recordId }}"
                                                   data-emp-id="{{ $employeeId }}"
                                                   data-emp-name="{{ $employeeName }}"
                                                   data-remark="{{ $holdRemark }}"
                                                   data-is-hold="{{ $isExported == 2 ? '1' : '0' }}"
                                                   {{ $isExported == 2 ? 'checked' : '' }}>
                                        </td>
                                        <td style="font-size:10px;text-align: left;">{{ $employeeName }}</td>
                                        <td style="font-size:10px;text-align: center;">{{ getCode($employeeId) }}</td>
                                        <td style="font-size:10px;text-align: center;">{{ getGrade($employeeId) }}</td>
                                        <td style="font-size:10px;text-align: center;">{{ $employeeStatus }}</td>
                                        <td style="font-size:10px;text-align: center;">
                                            <a href="#" onclick="showmonthdet('{{ $month }}','Closed','{{ $employeeId }}','Closed')" style="color: #007bff;">
                                                {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                            </a>
                                        </td>
                                        <td style="font-size:10px;text-align: center;">{{ number_format($totalClaim) }}</td>
                                        <td style="font-size:10px;text-align: center;">
                                            <strong>{{ number_format($paymentAmount) }}</strong>
                                        </td>
                                        <td style="font-size:10px;text-align: center;">{{ $approvedDate }}</td>
                                        <td style="font-size:10px;text-align: center;">
                                            <span onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 1)"
                                                class="print-link cursor-pointer text-primary">One</span>
                                            &nbsp;|&nbsp;
                                            <span onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 2)"
                                                class="print-link cursor-pointer text-primary">Two</span>
                                        </td>
                                        <td style="font-size:10px;text-align: center;">
                                            <span onclick="accSummary({{ $employeeId }}, {{ $month }}, {{ $yearId }})"
                                                class="view-link cursor-pointer text-primary">View</span>
                                        </td>
                                        <td style="font-size:10px;text-align: center;" class="remark-column">
                                            <div class="remark-container" data-record-id="{{ $recordId }}">
                                                @if($isExported == 2)
                                                    <input type="text"
                                                           class="remark-input hold-active"
                                                           value="{{ htmlspecialchars($holdRemark) }}"
                                                           placeholder="Enter hold remark..." aria-label="Enter hold remark"
                                                           data-record-id="{{ $recordId }}"
                                                           data-original-value="{{ htmlspecialchars($holdRemark) }}">
                                                    <button type="button" class="save-remark-btn btn btn-sm btn-success" title="Save remark">
                                                        ✓
                                                    </button>
                                                @else
                                                    <span class="remark-text" data-record-id="{{ $recordId }}">
                                                        {{ !empty($holdRemark) ? $holdRemark : '-' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        @if(isSuperAdmin())
                                        <td style="font-size:10px;text-align: center;">
                                            <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2"
                                                onclick="openRevertSingleModal('{{ $recordId }}', '{{ addslashes($employeeName) }}', '{{ getCode($employeeId) }}', '{{ date('F', mktime(0, 0, 0, $month, 1)) }}', '{{ number_format($paymentAmount) }}', 'Pending')"
                                                title="Revert back to Pending">
                                                <i class="fa fa-undo"></i> Revert
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                    @php $i++; @endphp
                                @empty
                                    <tr>
                                        <td colspan="{{ isSuperAdmin() ? 15 : 14 }}" style="font-size:10px;text-align: center; padding: 30px;">
                                            No approved payment records found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if(isset($totalRecords) && $totalRecords > 0)
                        <div style="font-size:10px;text-align: center; padding: 20px 0;">
                            @php
                                $currentPage = request('page', 1);
                                $perPage = 1000;
                                $totalPages = ceil($totalRecords / $perPage);
                                $queryParams = request()->except('page');
                                $queryString = http_build_query($queryParams);
                            @endphp

                            @if($currentPage > 1)
                                <a href="{{ request()->url() }}?page={{ $currentPage - 1 }}&{{ $queryString }}"
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff;">&lt; Previous</a>
                            @else
                                <span style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #6c757d;">&lt; Previous</span>
                            @endif

                            @for($p = 1; $p <= $totalPages; $p++)
                                @if($p == $currentPage)
                                    <span style="margin: 0 2px; padding: 6px 12px; border: 1px solid #007bff; background-color: #dde6ff; color: #1e3a8a;">{{ $p }}</span>
                                @else
                                    <a href="{{ request()->url() }}?page={{ $p }}&{{ $queryString }}"
                                        style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff;">{{ $p }}</a>
                                @endif
                            @endfor

                            @if($currentPage < $totalPages)
                                <a href="{{ request()->url() }}?page={{ $currentPage + 1 }}&{{ $queryString }}"
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff;">Next &gt;</a>
                            @else
                                <span style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #6c757d;">Next &gt;</span>
                            @endif

                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <span style="color: #ee4545;">(Total {{ $totalRecords }} Records)</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @include('expense-payments.partials.modals')
        @include('expense-payments.partials.revert-modal')
@endsection

<script src="{{ asset('vendor/cdn/jquery@3.6.0/dist/jquery.min.js') }}"></script>
<script type="text/javascript">
    // Original functions
    function FselMonth() {
        var chkval = $('#chkval').val();
        var SelMonth = $('#SelMonth').val();
        window.location = "{{ route('expense-payments.index') }}?v=" + SelMonth + "&chkval=" + chkval;
    }

    function FunPrint(e, m, y, n) {
        window.open("{{ url('printdetails') }}?e=" + e + "&m=" + m + "&y=" + y + "&n=" + n,
            "PForm", "menubar=no,scrollbars=yes,resizable=no,directories=no,width=1200,height=450");
    }

    function showmonthdet(month, sts, emp, csts) {
        var status = document.getElementById('sts' + emp + month + csts);
        var modal = document.getElementById(emp + month + csts);
        if (status && status.value == 'close') {
            $.post("{{ url('claimlistajax') }}",
                { act: "monthdettofinance", month: month, sts: sts, csts: csts, emp: emp },
                function (data) {
                    if (modal) modal.innerHTML = data;
                }
            );
            if (status) status.value = "open";
        } else if (status && status.value == 'open') {
            if (modal) modal.innerHTML = '';
            if (status) status.value = "close";
        }
    }

    function accSummary(e, m, y) {
        window.open("{{ url('account-summary') }}?e=" + e + "&m=" + m + "&y=" + y,
            "AccSummary", "menubar=no,scrollbars=yes,resizable=no,directories=no,width=800,height=600");
    }

    // Save remark function with page reload
    function saveHoldRemark(recordId, remark, $container, shouldReload = true) {
        $.ajax({
            url: "{{ route('mark.as.hold') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                id: recordId,
                status: 2,  // Keep the hold status
                remark: remark
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Show success indicator
                    var $saveBtn = $container.find('.save-remark-btn');
                    var originalText = $saveBtn.text();
                    $saveBtn.text('✓').css('color', '#28a745');

                    setTimeout(function() {
                        if (shouldReload) {
                            location.reload();
                        } else {
                            $saveBtn.text(originalText).css('color', '');
                            // Update checkbox data attributes
                            var $checkbox = $container.closest('tr').find('.hold-checkbox');
                            $checkbox.data('remark', remark);
                            $checkbox.attr('data-remark', remark);
                        }
                    }, 1000);
                } else {
                    alert('Error: ' + response.message);
                    // Revert to original value
                    var $input = $container.find('.remark-input');
                    var originalValue = $input.data('original-value');
                    $input.val(originalValue);
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr);
                var errorMsg = 'Error saving remark';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                }
                alert(errorMsg);
                // Revert to original value
                var $input = $container.find('.remark-input');
                var originalValue = $input.data('original-value');
                $input.val(originalValue);
            }
        });
    }

    $(document).ready(function() {
        // Select All checkbox
        $('#selectAll').on('change', function() {
            $('.row-selector').prop('checked', this.checked);
            updateSelectedCount();
        });

        // Individual checkbox
        $('.row-selector').on('change', function() {
            var allChecked = $('.row-selector:checked').length === $('.row-selector').length;
            $('#selectAll').prop('checked', allChecked);
            updateSelectedCount();
        });

        // Update selected count display
        function updateSelectedCount() {
            var count = $('.row-selector:checked').length;
            $('#selectedCount').text('(Selected: ' + count + ')');
        }

        updateSelectedCount();

        // Save remark on Enter key or blur
        $(document).on('keypress', '.remark-input', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $(this).blur();
            }
        });

        $(document).on('blur', '.remark-input', function() {
            var $input = $(this);
            var newRemark = $input.val().trim();
            var originalRemark = $input.data('original-value');
            var recordId = $input.data('record-id');
            var $container = $input.closest('.remark-container');

            if (newRemark !== originalRemark) {
                saveHoldRemark(recordId, newRemark, $container, true);
                $input.data('original-value', newRemark);
            }
        });

        // Save button click - triggers page reload
        $(document).on('click', '.save-remark-btn', function() {
            var $container = $(this).closest('.remark-container');
            var $input = $container.find('.remark-input');
            var newRemark = $input.val().trim();
            var originalRemark = $input.data('original-value');
            var recordId = $input.data('record-id');

            if (newRemark !== originalRemark) {
                saveHoldRemark(recordId, newRemark, $container, true);
                $input.data('original-value', newRemark);
            } else {
                // If no change, just reload
                location.reload();
            }
        });

        // Click on remark text to edit (for non-hold items)
        $(document).on('click', '.remark-text', function() {
            var $span = $(this);
            var recordId = $span.data('record-id');
            var currentRemark = $span.text().trim();

            if (currentRemark === 'Click to add remark' || currentRemark === '-') {
                currentRemark = '';
            }

            // Replace span with input field
            var $input = $('<input>', {
                type: 'text',
                class: 'remark-input',
                value: currentRemark,
                placeholder: 'Enter remark...',
                'data-record-id': recordId,
                'data-original-value': currentRemark
            });

            var $saveBtn = $('<button>', {
                type: 'button',
                class: 'save-remark-btn',
                title: 'Save remark',
                html: '✓'
            });

            var $container = $('<div>', {
                class: 'remark-container',
                'data-record-id': recordId
            });

            $container.append($input, $saveBtn);
            $span.replaceWith($container);
            $input.focus();
        });

        // Hold checkbox change event - Mark as Hold with Remark
        $(document).on('change', '.hold-checkbox', function() {
            var $checkbox = $(this);
            var id = $checkbox.data('id');
            var employeeName = $checkbox.data('emp-name');
            var wasChecked = $checkbox.is(':checked');
            var $row = $checkbox.closest('tr');
            var $remarkContainer = $row.find('.remark-container');

            if (wasChecked) {
                // Disable checkbox temporarily
                $checkbox.prop('disabled', true);

                // Call API to mark as hold
                $.ajax({
                    url: "{{ route('mark.as.hold') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        id: id,
                        status: 2,
                        remark: ''
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                            $checkbox.prop('checked', false);
                            $checkbox.prop('disabled', false);
                        }
                    },
                    error: function(xhr) {
                        console.error('AJAX Error:', xhr);
                        var errorMsg = 'Error processing hold request';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg += ': ' + xhr.responseJSON.message;
                        }
                        alert(errorMsg);
                        $checkbox.prop('checked', false);
                        $checkbox.prop('disabled', false);
                    }
                });
            } else {
                // Removing hold - confirm first
                if (confirm('Are you sure you want to remove the hold from this payment for ' + employeeName + '?')) {
                    $checkbox.prop('disabled', true);

                    $.ajax({
                        url: "{{ route('mark.as.hold') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            id: id,
                            status: 0,
                            remark: ''
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                                $checkbox.prop('checked', true);
                                $checkbox.prop('disabled', false);
                            }
                        },
                        error: function(xhr) {
                            console.error('AJAX Error:', xhr);
                            var errorMsg = 'Error processing hold removal';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg += ': ' + xhr.responseJSON.message;
                            }
                            alert(errorMsg);
                            $checkbox.prop('checked', true);
                            $checkbox.prop('disabled', false);
                        }
                    });
                } else {
                    // User cancelled, keep the checkbox checked
                    $checkbox.prop('checked', true);
                }
            }
        });
    });

    // Clear all selections
    function clearAllSelections() {
        $('.row-selector').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateSelectedCount();
    }

    // Get selected rows with their hold status
    function getSelectedRowsWithStatus() {
        let rowsToProcess = [];

        $('.row-selector:checked').each(function() {
            var $checkbox = $(this);
            var $row = $checkbox.closest('tr');
            var $holdCheckbox = $row.find('.hold-checkbox');
            var isHold = $holdCheckbox.is(':checked'); // Get actual hold status from checkbox

            rowsToProcess.push({
                id: $checkbox.data('id'),
                employee_id: $checkbox.data('emp'),
                month: $checkbox.data('month'),
                amount: parseFloat($checkbox.data('amount')) || 0,
                is_hold: isHold
            });
        });

        return rowsToProcess;
    }

    // Process selected payments (Release & Export)
    function processSelectedPayments() {
        let selectedRows = getSelectedRowsWithStatus();

        if (selectedRows.length === 0) {
            alert('Please select at least one record.');
            return;
        }

        // Separate rows based on hold status
        let holdRows = selectedRows.filter(r => r.is_hold === true);
        let nonHoldRows = selectedRows.filter(r => r.is_hold === false);

        let message = 'Selected ' + selectedRows.length + ' record(s):\n';
        message += '- ' + nonHoldRows.length + ' will be RELEASED and EXPORTED\n';
        message += '- ' + holdRows.length + ' will be EXPORTED only (already on hold)\n\n';

        if (nonHoldRows.length > 0) {
            message += '⚠️ Release action will:\n';
            message += '  • Update payment status to "InstructedPayment"\n';
            message += '  • Set payment instructed date\n';
            message += '  • Make records available for final processing\n';
            message += '  • Cannot be undone\n\n';
        }

        message += 'Total amount: Rs. ' + (function(rs){var t=0;rs.forEach(function(r){t+=Number(r.amount)||0});return t.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});})(selectedRows) + ' (' + selectedRows.length + ' employee-month(s))\n\n'; // LV-U02
        message += 'Do you want to continue?';

        if (!confirm(message)) {
            return;
        }

        $('#processSelectedBtn').addClass('processing-btn').prop('disabled', true);
        $('#processSelectedBtn').html('<i class="fa fa-spinner fa-spin"></i> Processing & Exporting...');

        // First release non-hold records, then export all
        releaseAndExportSelected(selectedRows, nonHoldRows, holdRows);
    }

    // Release and Export selected records
    function releaseAndExportSelected(allRows, releaseRows, exportOnlyRows) {
        // Step 1: Release non-hold records if any
        if (releaseRows.length > 0) {
            $.ajax({
                url: "{{ route('expense-payments.release-selected') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    rows: JSON.stringify(releaseRows)
                },
                success: function(releaseResponse) {
                    if (releaseResponse.success === true) {
                        console.log('Release successful');
                        // Step 2: Export all records
                        exportAllSelected(allRows);
                    } else {
                        alert('Release failed: ' + (releaseResponse.error || 'Unknown error'));
                        $('#processSelectedBtn').removeClass('processing-btn').prop('disabled', false);
                        $('#processSelectedBtn').html('<i class="fa fa-play"></i> Process Selected Records (Release & Export)');
                    }
                },
                error: function(xhr) {
                    console.error('Release error:', xhr);
                    let errorMsg = 'Release failed.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg += ' ' + xhr.responseJSON.error;
                    }
                    alert(errorMsg);
                    $('#processSelectedBtn').removeClass('processing-btn').prop('disabled', false);
                    $('#processSelectedBtn').html('<i class="fa fa-play"></i> Process Selected Records (Release & Export)');
                }
            });
        } else {
            // No records to release, just export
            exportAllSelected(allRows);
        }
    }

    // Export all selected records via API
    function exportAllSelected(selectedRows) {
        if (!selectedRows || selectedRows.length === 0) {
            alert('No records selected for export.');
            $('#processSelectedBtn').removeClass('processing-btn').prop('disabled', false);
            $('#processSelectedBtn').html('<i class="fa fa-play"></i> Process Selected Records (Release & Export)');
            return;
        }

        // Prepare the data for the API
        let monthsToExport = [...new Set(selectedRows.map(row => row.month))];
        let usersToExport = [...new Set(selectedRows.map(row => row.employee_id))];

        // Prepare rows data in the expected format
        let rowsData = selectedRows.map(row => ({
            id: row.id,
            employee_id: row.employee_id,
            month: row.month,
            amount: row.amount,
            released: !row.is_hold // Track which records were released
        }));

        // Call the API endpoint
        $.ajax({
            url: '{{ url("/export-claim-stage-data") }}',
            type: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: JSON.stringify({
                yearId: '{{ session("year_id") }}',
                company_id: '{{ session("company_id") }}',
                monthSelect: monthsToExport,
                userSelect: usersToExport,
                rows: rowsData
            }),
            success: function(exportResponse) {
                if (exportResponse.status === 'success' && exportResponse.file_url) {
                    // Open the file in a new tab
                    window.open(exportResponse.file_url, '_blank');

                    let successMsg = 'Focus export generated & downloaded successfully!\n';
                    successMsg += '\n✓ ' + selectedRows.length + ' record(s) exported';

                    let nonHoldCount = selectedRows.filter(r => !r.is_hold).length;
                    if (nonHoldCount > 0) {
                        successMsg += '\n✓ ' + nonHoldCount + ' record(s) released (status: InstructedPayment)';
                    }

                    let holdCount = selectedRows.filter(r => r.is_hold).length;
                    if (holdCount > 0) {
                        successMsg += '\n✓ ' + holdCount + ' record(s) exported (on hold)';
                    }

                    alert(successMsg);
                } else {
                    alert('Focus export failed: ' + (exportResponse.message || 'Unknown error'));
                }

                // Reload the page after 2 seconds
                setTimeout(function() {
                    location.reload();
                }, 2000);
            },
            error: function(xhr, status, error) {
                console.error('Export error:', xhr);
                let errorMsg = 'Focus export failed.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ' ' + xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    errorMsg += ' ' + xhr.responseText;
                }

                alert(errorMsg);

                // Re-enable the button
                $('#processSelectedBtn').removeClass('processing-btn').prop('disabled', false);
                $('#processSelectedBtn').html('<i class="fa fa-play"></i> Process Selected Records (Release & Export)');
            }
        });
    }

    // Push selected reimbursement records to Matrix
    function pushSelectedToMatrix() {
        let selectedRows = getSelectedRowsWithStatus();
        if (selectedRows.length === 0) {
            alert('Please select at least one record.');
            return;
        }

        let nonHoldRows = selectedRows.filter(r => r.is_hold === false);
        if (nonHoldRows.length === 0) {
            alert('All selected records are on hold. Only non-hold records can be pushed to Matrix.');
            return;
        }

        let confirmMsg = 'Push ' + nonHoldRows.length + ' payment record(s) to Matrix, total Rs. ' + (function(rs){var t=0;rs.forEach(function(r){t+=Number(r.amount)||0});return t.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});})(nonHoldRows) + '?\n\n' + // LV-U02
            'Matrix will hold them for finance acceptance, approval, bank payment, and Focus ERP processing.';

        if (!confirm(confirmMsg)) {
            return;
        }

        let btn = $('#pushToMatrixBtn');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Pushing to Matrix...');

        $.ajax({
            url: "{{ route('matrix.push-reimbursements') }}",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                rows: JSON.stringify(nonHoldRows)
            },
            success: function(response) {
                btn.prop('disabled', false).html(originalHtml);
                if (response.success) {
                    alert('Success!\n' + response.message + '\nBatch Ref: ' + (response.batch_ref || 'N/A'));
                    location.reload();
                } else {
                    alert('Matrix Push Warning: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                let msg = 'Failed to push to Matrix.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg += '\n' + xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    }

    // Sync latest status updates from Matrix
    function syncMatrixUpdates() {
        let btn = $('#syncMatrixBtn');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Syncing...');

        $.ajax({
            url: "{{ route('matrix.sync-updates') }}",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                btn.prop('disabled', false).html(originalHtml);
                if (response.success) {
                    alert('Matrix Sync Completed!\nProcessed: ' + (response.total_processed || 0) + ' record(s)\nUpdated: ' + (response.updated_instructions || 0));
                    location.reload();
                } else {
                    alert('Sync error: ' + (response.message || 'Unknown'));
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                alert('Sync failed: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    }

    // Preview Matrix JSON payload before pushing
    function previewMatrixPayload(type) {
        let rows = getSelectedRowsWithStatus();
        if (rows.length === 0) {
            alert('Please select at least one record to preview.');
            return;
        }

        let nonHoldRows = rows.filter(r => r.is_hold === false);
        if (nonHoldRows.length === 0) {
            nonHoldRows = rows;
        }

        let btn = $('#previewMatrixBtn');
        let origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

        $.ajax({
            url: "{{ route('matrix.preview-payload') }}",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                payment_type: type || 'reimbursement',
                rows: JSON.stringify(nonHoldRows)
            },
            success: function(response) {
                btn.prop('disabled', false).html(origHtml);
                if (response.success) {
                    console.log('Matrix Payload Preview:', response.payload);
                    showPayloadModal(response.payload, response.count);
                } else {
                    alert('Preview Error: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(origHtml);
                alert('Failed to generate preview: ' + (xhr.responseJSON?.message || 'Server error'));
            }
        });
    }

    function showPayloadModal(payload, count) {
        $('#matrixPayloadModal').remove();
        let jsonStr = JSON.stringify(payload, null, 2);

        let modalHtml = `
        <div class="modal fade" id="matrixPayloadModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white py-2">
                        <h6 class="modal-title mb-0"><i class="fa fa-code"></i> Matrix Payment Payload Preview (${count} instructions)</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-2" style="background:#1e1e1e;color:#d4d4d4;max-height:70vh;">
                        <pre style="margin:0;font-size:11px;color:#9cdcfe;font-family:Consolas, monospace;"><code>${escapeHtml(jsonStr)}</code></pre>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText($('#matrixPayloadModal pre code').text()); alert('Payload JSON copied to clipboard!');">
                            <i class="fa fa-copy"></i> Copy JSON
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>`;

        $('body').append(modalHtml);
        let modal = new bootstrap.Modal(document.getElementById('matrixPayloadModal'));
        modal.show();
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
