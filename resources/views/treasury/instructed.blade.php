{{-- resources/views/expense-payments/instructed.blade.php --}}


@extends('layouts.app')
<script src="{{ asset('vendor/cdn/jquery@3.6.0/dist/jquery.min.js') }}"></script>
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
            background-color: #15648C;
            color: white;
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
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 4px 12px;
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

        .badge-exported {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            display: inline-block;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            display: inline-block;
        }

        .export-status {
            font-size: 11px;
            color: #28a745;
            font-weight: 500;
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
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination li.disabled span {
            color: #6c757d;
            pointer-events: none;
        }
    </style>
@endsection

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row py-3 bg-white border-bottom">
            <div class="btn-group" role="group">

                {{-- <a href="{{ route('expense-payments.index', ['chkval' => 1, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval', 1) == 1 ? 'btn-primary' : 'btn-outline-primary' }}">
                    Pending
                </a>

                <a href="{{ route('expense-payments.index', ['chkval' => 2, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 2 ? 'btn-success' : 'btn-outline-success' }}">
                    Approved
                </a> --}}

                <a href="{{ route('expense-payments.index', ['chkval' => 3, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 3 ? 'btn-warning' : 'btn-outline-warning' }}">
                    Instructed Payment
                </a>

                {{-- <a href="{{ route('expense-payments.index', ['chkval' => 4, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 4 ? 'btn-info' : 'btn-outline-info' }}">
                    Confirmed Payments
                </a>

                <a href="{{ route('expense-payments.index', ['chkval' => 5, 'v' => request('v', 0)]) }}"
                    class="btn btn-sm {{ request('chkval') == 5 ? 'btn-danger' : 'btn-outline-danger' }}">
                    Unsettled Payments
                </a> --}}

            </div>

            <div class="row">
                <div class="col-lg-12 shadow">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 0px;">
                        <div><b style="font-size: 16px;">Instructed Payment List</b>
                        <!-- Add this button after the Payment Export button -->
<a class="btn btn-sm btn-info ms-3" onclick="verifyPayments()" style="padding: 4px 12px; background-color: #17a2b8; border-color: #17a2b8; color: white;">
    <i class="fa fa-check-circle" aria-hidden="true"></i>
    <span style="color:#FFFFFF;">Verify Payments</span>
</a>

                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <form method="GET" class="px-3 my-auto py-0" action="{{ route('treasury.instructed') }}" style="display:inline;">

                                {{-- Keep existing parameters --}}
                                <input type="hidden" name="chkval" value="{{ request('chkval', 3) }}">
                                <input type="hidden" name="v" value="{{ request('v', 0) }}">
 <span style="font-size:14px;">Export Status :</span>
                                <select name="export_filter"
                                    onchange="this.form.submit()">
                                    {{-- <option value="">All</option> --}}
                                    <option value="0" {{ request('export_filter') === '0' ? 'selected' : '' }}>
                                        Not Exported
                                    </option>
                                    <option value="1" {{ request('export_filter') === '1' ? 'selected' : '' }}>
                                        Exported
                                    </option>
                                    {{-- <option value="2" {{ request('export_filter') === '2' ? 'selected' : '' }}>
                                        Hold
                                    </option> --}}
                                </select>
                            </form>
                            <span style="font-size:14px;">Month :</span>
                            @php
                                $reqMonth = request('v');
                                $selectedMonth = $reqMonth ;
                            @endphp



                            <select class="month-select" id="SelMonth">
                                <option value="0" {{ $selectedMonth == 0 ? 'selected' : '' }}>All</option>
                                <option value="1" {{ $selectedMonth == 1 ? 'selected' : '' }}>January</option>
                                <option value="2" {{ $selectedMonth == 2 ? 'selected' : '' }}>February</option>
                                <option value="3" {{ $selectedMonth == 3 ? 'selected' : '' }}>March</option>
                                <option value="4" {{ $selectedMonth == 4 ? 'selected' : '' }}>April</option>
                                <option value="5" {{ $selectedMonth == 5 ? 'selected' : '' }}>May</option>
                                <option value="6" {{ $selectedMonth == 6 ? 'selected' : '' }}>June</option>
                                <option value="7" {{ $selectedMonth == 7 ? 'selected' : '' }}>July</option>
                                <option value="8" {{ $selectedMonth == 8 ? 'selected' : '' }}>August</option>
                                <option value="9" {{ $selectedMonth == 9 ? 'selected' : '' }}>September</option>
                                <option value="10" {{ $selectedMonth == 10 ? 'selected' : '' }}>October</option>
                                <option value="11" {{ $selectedMonth == 11 ? 'selected' : '' }}>November</option>
                                <option value="12" {{ $selectedMonth == 12 ? 'selected' : '' }}>December</option>
                            </select>
                            <a class="btn btn-sm btn-primary" onclick="FselMonth()" style="padding: 4px 16px;">
                                <span style="color:#FFFFFF;">Click</span>
                            </a>

                            <a class="btn btn-sm btn-primary" href="javascript:location.reload(true)"
                                style="padding: 4px 12px;">
                                <i class="fa fa-refresh" aria-hidden="true"></i> Refresh
                            </a>

                            <a class="btn btn-sm btn-success" onclick="generateFocusExport()" style="padding: 4px 12px;">
                                <i class="fa fa-btn" aria-hidden="true"></i>
                                <span style="color:#FFFFFF;">Payment Export</span>
                            </a>

                            @if(isSuperAdmin())
                            <a class="btn btn-sm btn-outline-secondary" onclick="openRevertLogsModal()" style="padding: 4px 12px;">
                                <i class="fa fa-history"></i> Revert Logs
                            </a>
                            @endif

                        </div>
                    </div>

                    <div style="overflow-x: auto; font-size:10px;width: 100%;">
                        <table class="estable table table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th
                                        style="font-size:10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        S.No</th>
                                    {{-- <th
                                        style="font-size:10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Hold</th> --}}
                                    <th
                                        style="font-size:10px;width:200px;vertical-align:middle;background-color: #15648C;color:white">
                                        Claimant</th>
                                    <th
                                        style="font-size:10px;width:50px;vertical-align:middle;background-color: #15648C;color:white">
                                        EmpCode</th>
                                    <th
                                        style="font-size:10px;width:50px;vertical-align:middle;background-color: #15648C;color:white">
                                        Grade</th>
                                    <th
                                        style="font-size:10px;width:10px;vertical-align:middle;background-color: #15648C;color:white">
                                        Status</th>
                                    <th
                                        style="font-size:10px;width:50px;vertical-align:middle;background-color: #15648C;color:white">
                                        Month</th>
                                    <th
                                        style="font-size:10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Total Claims</th>
                                    <th
                                        style="font-size:10px;width:100px;vertical-align:middle;background-color: #15648C;color:white">
                                        Instructed Amount</th>
                                    <th
                                        style="font-size:10px;width:100px;vertical-align:middle;background-color: #15648C;color:white">
                                        Instructed Date</th>
                                    <th
                                        style="font-size:10px;width:100px;vertical-align:middle;background-color: #15648C;color:white">
                                        Print</th>
                                    <th rowspan="2"
                                        style="font-size:10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Export Status</th>
                                    <th
                                        style="font-size:10px;width:60px;vertical-align:middle;background-color: #15648C;color:white">
                                        Account Summary</th>
                                    @if(isSuperAdmin())
                                    <th
                                        style="font-size:10px;width:80px;vertical-align:middle;background-color: #c82333;color:white">
                                        Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $page = request('page', 1);
                                    $i = ($page - 1) * 1000 + 1;

                                    // dd($records);

                                @endphp

                                @forelse($records as $record)
                                    @php
                                        $employeeStatus = getStsUser(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                        );
                                        $rowBgColor = in_array($employeeStatus, ['D', 'R']) ? '#FFFFA6' : '';
                                        $id = $record['id'];
                                        // Get ledger balance
                                        $ledgerData = getEmployeeLedgerBalance(
                                            $record['EmployeeID'] ?? ($record['employee_id'] ?? 0),
                                        );
                                        $ledgerBalance =
                                            $ledgerData['balance'] ?? ($record['ledger_balance_at_approval'] ?? 0);
                                        $isExported = $record['is_exported'];
                                        // Format dates
                                        $instructedDate =
                                            !empty($record['payment_instructed_date']) &&
                                            \App\Support\ZeroDate::has($record['payment_instructed_date'])
                                                ? date('d-m-Y', strtotime($record['payment_instructed_date']))
                                                : '';

                                        $month = $record['Month'] ?? ($record['month'] ?? 1);
                                        $employeeId = $record['EmployeeID'] ?? ($record['employee_id'] ?? 0);
                                        $yearId =
                                            $record['YearId'] ?? ($record['year_id'] ?? session('year_id', date('Y')));
                                        $totalClaim = $record['Total_Claim'] ?? ($record['total_claim'] ?? 0);
                                        $paymentAmount = $record['payment_amount'] ?? ($record['Finance_Amount'] ?? ($record['Approved_Amount'] ?? 0));

                                        // Check if export exists
                                        $exportDate = ''; // This would come from your export tracking table
                                    @endphp

                                    <tr style="background-color: {{ $rowBgColor }};">
                                        <td style="font-size:10px;text-align: center;">{{ $i }}</td>
                                        {{-- <td style="text-align:center;">
                                            @if ($isExported != 1)
                                                <input type="checkbox" class="hold-checkbox" data-id="{{ $id }}"
                                                    {{ $isExported == 2 ? 'checked' : '' }}>
                                            @else
                                                -
                                            @endif
                                        </td> --}}
                                        <td style="font-size:10px;text-align: left;">{{ getUser($employeeId) }}</td>
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
                                        <td style="font-size:10px;text-align: center;">{{ number_format($totalClaim) }}
                                        </td>
                                        <td style="font-size:10px;text-align: center;">
                                            <strong>{{ number_format($paymentAmount) }}</strong>
                                        </td>
                                        <td style="font-size:10px;text-align: center;">{{ $instructedDate }}</td>
                                        <td style="font-size:10px;text-align: center;">
                                            <span
                                                onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 1)"
                                                class="print-link cursor-pointer text-primary">One</span>
                                            &nbsp;|&nbsp;
                                            <span
                                                onclick="FunPrint({{ $employeeId }}, {{ $month }}, {{ $yearId }}, 2)"
                                                class="print-link  cursor-pointer text-primary">Two</span>
                                        </td>
                                        <td
                                            style="font-size:10px; text-align:center;
    @if ($isExported == 1) color: green;
    @elseif($isExported == 2) color: orange; @endif">

                                            {{ $isExported == 1 ? 'Exported' : ($isExported == 2 ? 'Hold' : '') }}

                                        </td>
                                        <td style="font-size:10px;text-align: center;">
                                            <span
                                                onclick="accSummary({{ $employeeId }}, {{ $month }}, {{ $yearId }})"
                                                class="view-link cursor-pointer text-primary">View</span>
                                        </td>
                                        @if(isSuperAdmin())
                                        <td style="font-size:10px;text-align: center;">
                                            <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2"
                                                onclick="openRevertSingleModal('{{ $id }}', '{{ addslashes(getUser($employeeId)) }}', '{{ getCode($employeeId) }}', '{{ date('F', mktime(0, 0, 0, $month, 1)) }}', '{{ number_format($paymentAmount) }}', 'Approved', 'Instructed')"
                                                title="Revert back to Approved">
                                                <i class="fa fa-undo"></i> Revert
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                    @php $i++; @endphp
                                @empty
                                    <tr>
                                        <td colspan="{{ isSuperAdmin() ? 13 : 12 }}"
                                            style="font-size:10px;text-align: center; padding: 30px; font-size: 14px;">
                                            No instructed payment records found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    @if (isset($totalRecords) && $totalRecords > 0)
                        <div
                            style="font-size:10px;text-align: center; padding: 20px 0; font-family: Times New Roman; font-size:15px; font-weight:bold;">
                            @php
                                $currentPage = request('page', 1);
                                $perPage = 1000;
                                $totalPages = ceil($totalRecords / $perPage);
                                $queryParams = request()->except('page');
                                $queryString = http_build_query($queryParams);
                            @endphp

                            @if ($currentPage > 1)
                                <a href="{{ request()->url() }}?page={{ $currentPage - 1 }}&{{ $queryString }}"
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff; text-decoration: none;">&lt;
                                    Previous</a>
                            @else
                                <span
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #6c757d;">&lt;
                                    Previous</span>
                            @endif

                            @for ($p = 1; $p <= $totalPages; $p++)
                                @if ($p == $currentPage)
                                    <span
                                        style="margin: 0 2px; padding: 6px 12px; border: 1px solid #007bff; background-color: #007bff; color: white;">{{ $p }}</span>
                                @else
                                    <a href="{{ request()->url() }}?page={{ $p }}&{{ $queryString }}"
                                        style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff; text-decoration: none;">{{ $p }}</a>
                                @endif
                            @endfor

                            @if ($currentPage < $totalPages)
                                <a href="{{ request()->url() }}?page={{ $currentPage + 1 }}&{{ $queryString }}"
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #007bff; text-decoration: none;">Next
                                    &gt;</a>
                            @else
                                <span
                                    style="margin: 0 2px; padding: 6px 12px; border: 1px solid #ddd; color: #6c757d;">Next
                                    &gt;</span>
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
        // Month selection function
        function FselMonth() {
            var chkval = $('#chkval').val();
            var SelMonth = $('#SelMonth').val();
            window.location = "{{ route('expense-payments.index') }}?chkval=3&v=" + SelMonth;
        }

        // Move Focus Sheet function
        function FUnMoveCheck(month, chkval) {
            var isChecked = document.getElementById("MoChkM").checked ? 1 : 0;
            window.location = "{{ route('expense-payments.index') }}?Mmord=" + isChecked + "&v=" + month + "&chkval=" +
                chkval;
        }

        // Print function
           function FunPrint(employeeId, month, year, format) {
    if (!employeeId || !month || !year) {
        alert('Invalid parameters for printing');
        return false;
    }

    var url = @json(route('print.details'))
        + "?e=" + employeeId
        + "&m=" + month
        + "&y=" + year
        + "&n=" + format;

    var printWindow = window.open(
        url,
        '_blank',
        'width=1200,height=600,scrollbars=yes,resizable=yes'
    );

    if (!printWindow || printWindow.closed || typeof printWindow.closed == 'undefined') {
        alert('Popup blocker detected! Please allow popups for this site.');
    }

    return false;
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

        // Account summary
        function accSummary(e, m, y) {
            window.open("{{ url('account-summary') }}?e=" + e + "&m=" + m + "&y=" + y,
                "AccSummary", "menubar=no,scrollbars=yes,resizable=no,directories=no,width=800,height=600");
        }



        function generateFocusExport() {
            if (confirm('Generate Focus Export for instructed payments?')) {
                let selectedMonth = $('#SelMonth').val();

                console.log('Selected Month:', selectedMonth);

                $.ajax({
                    url: '{{ route('expense-payments.export-focus') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        month: selectedMonth,
                        chkval: '{{ request('chkval', 3) }}'
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(blob, status, xhr) {
                        console.log('✅ AJAX Success');
                        console.log('Blob size:', blob.size);

                        if (blob.size === 0) {
                            alert('Received empty file from server');
                            return;
                        }

                        // Get filename from header
                        let disposition = xhr.getResponseHeader('Content-Disposition');
                        let filename = 'focus_export.xlsx';

                        if (disposition && disposition.indexOf('filename=') !== -1) {
                            let filenameMatch = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                            if (filenameMatch && filenameMatch[1]) {
                                filename = filenameMatch[1].replace(/['"]/g, '');
                            }
                        }

                        // Create download link
                        try {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            a.style.display = 'none';

                            document.body.appendChild(a);
                            a.click();

                            setTimeout(function() {
                                document.body.removeChild(a);
                                window.URL.revokeObjectURL(url);

                                // After successful download, mark records as exported
                                $.ajax({
                                    url: '{{ route('expense-payments.mark-exported') }}',
                                    type: 'POST',
                                    data: {
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function(response) {
                                        console.log('Records marked as exported:',
                                            response);
                                        // Optionally refresh the page to update export status
                                        // location.reload();
                                    },
                                    error: function(error) {
                                        console.error('Error marking records:', error);
                                    }
                                });

                            }, 100);

                            console.log('✅ Download triggered successfully');

                        } catch (e) {
                            console.error('❌ Download error:', e);
                            alert('Error creating download: ' + e.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // console.error('❌ AJAX error:', error);
                        alert('No payment records found for the selected month');
                    }
                });
            }
        }


        $(document).on('change', '.hold-checkbox', function() {

            let id = $(this).data('id');
            let status = $(this).is(':checked') ? 2 : 0; // 2 = Hold, 0 = Normal

            $.ajax({
                url: "{{ route('mark.as.hold') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    id: id,
                    status: status
                },
                success: function(response) {
                    console.log(response.message);
                    location.reload();
                }
            });

        });




        // Payment Verification Function
function verifyPayments() {
    var selectedMonth = $('#SelMonth').val();

    let confirmMsg = `Are you sure you want to verify payments?

This action cannot be undone!`;

    if (!confirm(confirmMsg)) {
        return false;
    }

    var yearId = "{{ session('year_id', date('Y')) }}";
    var confirmedBy = "{{ auth()->id() }}";

    $.ajax({
        url: '{{ route("payment-verification.verify-bulk") }}',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            year_id: yearId,
            confirmed_by: confirmedBy,
            application: 'expense',
            month: selectedMonth
        },
     success: function(response) {
    if (response.success) {

        let expense = response.data.expense;
        let advance = response.data.advance;

       let message =
    "✅ Verification Completed\n\n" +

    "📊 EXPENSE SUMMARY\n" +
    "----------------------\n" +
    "Total: " + (expense?.total ?? 0) + "\n" +
    "Matched: " + (expense?.matched ?? 0) + "\n" +
    "Unsettled: " + (expense?.unsettled ?? 0) + "\n\n" +

    "📊 ADVANCE SUMMARY\n" +
    "----------------------\n" +
    "Total: " + (advance?.total ?? 0) + "\n" +
    "Matched: " + (advance?.matched ?? 0) + "\n" +
    "Unsettled: " + (advance?.unsettled ?? 0) + "\n\n" +

    "📊 TOTAL DETAILS\n" +
    "----------------------\n" +
    "Total Checked: " + (expense?.api_summary?.total ?? 0) + "\n" +
    "Matched: " + (expense?.api_summary?.matched ?? 0) + "\n" +
    "Not Found: " + (expense?.api_summary?.not_found ?? 0) + "\n";

        alert(message);

    } else {
        alert("❌ " + response.message);
    }
},
        error: function() {
            alert('❌ Error processing verification');
        }
    });
}
    </script>
