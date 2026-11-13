{{-- resources/views/expense-payments/holded.blade.php --}}
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

        .status-hold {
            background-color: #fdefd2;
            color: #856404;
            font-weight: bold;
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

        .release-column {
            width: 100px;
            text-align: center;
        }

        .btn-release {
            background-color: #d7f3e6;
            color: #065f46;
            border: none;
            padding: 4px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }

        .btn-release:hover {
            background-color: #d7f3e6; color: #065f46;}

        .btn-release:disabled {
            background-color: #eceef3;
            cursor: not-allowed; color: #374151;}
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

            <div class="row card">
                <div class="col-lg-12 shadow">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <b style="font-size: 16px;">Holded List</b>
                                </div>

                                <form method="GET" action="{{ route('expense-payments.index') }}" id="filterForm"
                                    class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                    <input type="hidden" name="chkval" value="6">

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
                                    <a href="{{ route('expense-payments.index', ['chkval' => 6]) }}" class="btn btn-secondary btn-sm">Reset</a>
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
                            <button type="button" class="btn btn-sm btn-success" id="processSelectedBtn" onclick="processSelectedPayments()">
                                <i class="fa fa-play"></i> Release Selected Records
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
                            <button type="button" class="btn btn-sm btn-primary" id="pushToMatrixBtn" onclick="pushSelectedToMatrix()">
                                <i class="fa fa-paper-plane"></i> Push to Matrix
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" id="syncMatrixBtn" onclick="syncMatrixUpdates()">
                                <i class="fa fa-refresh"></i> Sync Matrix Status
                            </button>
                            @endif
                            @if(isSuperAdmin())
                            <button type="button" class="btn btn-sm btn-outline-danger" id="revertSelectedBtn" onclick="openRevertSelectedModal('previous', 'Holded')">
                                <i class="fa fa-undo"></i> Revert Selected
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
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:200px">
                                        Claimant
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:50px">
                                        EmpCode
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:50px">
                                        Grade
                                    </th>
                                    {{-- <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:60px">
                                        Status
                                    </th> --}}
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
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:100px">
                                        Hold Date
                                    </th>
                                    <th style="font-size:10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a;width:100px">
                                        Status
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
                                        $yearId = $record['YearId'] ?? $record['year_id'] ?? session('year_id', date('Y'));
                                        $totalClaim = $record['Total_Claim'] ?? $record['total_claim'] ?? 0;
                                        $paymentAmount = $record['payment_amount'] ?? $record['Finance_Amount'] ?? $record['Approved_Amount'] ?? 0;
                                        $approvedDate = !empty($record['payment_approved_date']) && \App\Support\ZeroDate::has($record['payment_approved_date'])
                                            ? date('d-m-Y', strtotime($record['payment_approved_date']))
                                            : (!empty($record['Fin_AppDate']) && \App\Support\ZeroDate::has($record['Fin_AppDate'])
                                                ? date('d-m-Y', strtotime($record['Fin_AppDate']))
                                                : '');
                                        $recordId = $record['id'] ?? '';

                                        // Get hold date (exported_date or when is_exported was set to 2)
                                        $holdDate = !empty($record['focus_exported_date']) && \App\Support\ZeroDate::has($record['focus_exported_date'])
                                            ? date('d-m-Y', strtotime($record['focus_exported_date']))
                                            : '';
                                    @endphp

                                    <tr style="background-color: {{ $rowBgColor }};" data-record-id="{{ $recordId }}">
                                        <td style="font-size:10px;text-align: center;">
                                            <input type="checkbox" class="row-selector"
                                                   data-id="{{ $recordId }}"
                                                   data-emp="{{ $employeeId }}"
                                                   data-month="{{ $month }}"
                                                   data-amount="{{ $paymentAmount }}">
                                        </td>
                                        <td style="font-size:10px;text-align: center;">{{ $i }}</td>
                                        <td style="font-size:10px;text-align: left;">{{ getUser($employeeId) }}</td>
                                        <td style="font-size:10px;text-align: center;">{{ getCode($employeeId) }}</td>
                                        <td style="font-size:10px;text-align: center;">{{ getGrade($employeeId) }}</td>

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
                                        <td style="font-size:10px;text-align: center;">{{ $holdDate ?: '-' }}</td>
                                        <td style="font-size:10px;text-align: center;">
                                            <span class="status-badge status-hold">HOLD</span>
                                        </td>
                                        @if(isSuperAdmin())
                                        @php
                                            $currStatus = $record['payment_status'] ?? 'Approved';
                                            $targetStage = ($currStatus === 'InstructedPayment') ? 'Approved' : 'Pending';
                                            $currStageLabel = ($currStatus === 'InstructedPayment') ? 'Instructed (Hold)' : 'Approved (Hold)';
                                        @endphp
                                        <td style="font-size:10px;text-align: center;">
                                            <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2"
                                                onclick="openRevertSingleModal('{{ $recordId }}', '{{ addslashes(getUser($employeeId)) }}', '{{ getCode($employeeId) }}', '{{ date('F', mktime(0, 0, 0, $month, 1)) }}', '{{ number_format($paymentAmount) }}', '{{ $targetStage }}', '{{ $currStageLabel }}')"
                                                title="Revert back to {{ $targetStage }}">
                                                <i class="fa fa-undo"></i> Revert
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                    @php $i++; @endphp
                                @empty
                                    <tr>
                                        <td colspan="{{ isSuperAdmin() ? 15 : 14 }}" style="font-size:10px;text-align: center; padding: 30px;">
                                            No holded payment records found.
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
        window.open("{{ url('expense/print-details') }}?e=" + e + "&y=" + y + "&m=" + m + "&nn=" + n + "&c={{ session('company_id') }}",
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

        // Single release button click
        $('.release-single').on('click', function() {
            var $btn = $(this);
            var recordId = $btn.data('id');
            var employeeId = $btn.data('emp');
            var month = $btn.data('month');
            var amount = $btn.data('amount');

            if (confirm('Are you sure you want to release this payment?')) {
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Releasing...');

                $.ajax({
                    url: '{{ route("expense-payments.release-selected") }}',
                    method: 'POST',
                    data: {
                        rows: JSON.stringify([{
                            id: recordId,
                            employee_id: employeeId,
                            month: month,
                            amount: amount,
                            release: true
                        }]),
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success || response.released > 0) {
                            alert('Payment released successfully!');
                            location.reload();
                        } else {
                            alert('Failed to release payment. Please try again.');
                            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Release');
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + (xhr.responseJSON?.error || 'Something went wrong'));
                        $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Release');
                    }
                });
            }
        });
    });

    // Clear all selections
    function clearAllSelections() {
        $('.row-selector').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateSelectedCount();
    }

    // Get selected rows
    function getSelectedRows() {
        let selectedRows = [];

        $('.row-selector:checked').each(function() {
            var $checkbox = $(this);
            selectedRows.push({
                id: $checkbox.data('id'),
                employee_id: $checkbox.data('emp'),
                month: $checkbox.data('month'),
                amount: parseFloat($checkbox.data('amount')) || 0
            });
        });

        return selectedRows;
    }

    function processSelectedPayments() {
        let selectedRows = getSelectedRows();

        if (selectedRows.length === 0) {
            alert('Please select at least one record to release.');
            return;
        }

        if (!confirm('Release ' + selectedRows.length + ' selected payment(s), total Rs. ' + (function(rs){var t=0;rs.forEach(function(r){t+=Number(r.amount)||0});return t.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});})(selectedRows) + '?\n\nThis cannot be undone.')) { // LV-U02
            return;
        }

        $('#processSelectedBtn').addClass('processing-btn').prop('disabled', true);

        $.ajax({
            url: '{{ route("expense-payments.release-selected") }}',
            method: 'POST',
            data: {
                rows: JSON.stringify(selectedRows),
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success || response.released > 0) {
                    alert(' payment(s) released successfully!');
                    location.reload();
                } else {
                    alert('Failed to release payments. Please try again.');
                    $('#processSelectedBtn').removeClass('processing-btn').prop('disabled', false);
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.error || 'Something went wrong'));
                $('#processSelectedBtn').removeClass('processing-btn').prop('disabled', false);
            }
        });
    }

    // Push selected hold records to Matrix
    function pushSelectedToMatrix() {
        let selectedRows = getSelectedRows();
        if (selectedRows.length === 0) {
            alert('Please select at least one record.');
            return;
        }

        let confirmMsg = 'Push ' + selectedRows.length + ' payment record(s) to Matrix, total Rs. ' + (function(rs){var t=0;rs.forEach(function(r){t+=Number(r.amount)||0});return t.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});})(selectedRows) + '?\n\n' + // LV-U02
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
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: {
                rows: JSON.stringify(selectedRows)
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
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
        let rows = getSelectedRows();
        if (rows.length === 0) {
            alert('Please select at least one record to preview.');
            return;
        }

        let btn = $('#previewMatrixBtn');
        let origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

        $.ajax({
            url: "{{ route('matrix.preview-payload') }}",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: {
                payment_type: type || 'reimbursement',
                rows: JSON.stringify(rows)
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
