@extends('layouts.standalone')

@section('title')Month Report - {{ $month }}@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap@4.2.1/dist/css/bootstrap.min.css') }}">
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
        }
        body { padding: 20px; font-family: Arial, sans-serif; }
        .report-header { text-align: center; margin-bottom: 30px; }
        .report-header img { max-width: 150px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total-row { font-weight: bold; background-color: #e9ecef; }
    </style>
@endpush

@section('content')
<div class="container">
    <div class="report-header">
        <img src="https://expense.vnrseeds.co.in/images/Xeasylogotransparentgrey.png" alt="Logo">
        <h3>Expense Report - {{ date('F', mktime(0,0,0,$month,1)) }} {{ date('Y') }}</h3>
        <p>Employee ID: {{ $employeeId }} | Status: {{ $status == 1 ? 'Open' : 'Closed' }}</p>
    </div>

    <div id="report-content">
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p>Loading report data...</p>
        </div>
    </div>

    <div class="no-print text-center mt-4">
        <button class="btn btn-primary" onclick="window.print()">Print Report</button>
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
    </div>
</div>

<script src="{{ asset('vendor/cdn/jquery@3.3.1/dist/jquery.min.js') }}"></script>
<script>
    const employeeId = '{{ $employeeId }}';
    const month = '{{ $month }}';
    const yearId = '{{ $yearId }}';
    const companyId = '{{ $companyId }}';
    const reportStatus = '{{ $status }}';

    function loadReport() {
        const value = reportStatus == 1 ? 'Openlist_Monthwise' : 'Closelist_Monthwise';

        $.ajax({
            url: @json(\App\Support\LegacyApi::url('common.php')),
            method: "GET",
            data: {
                value: value,
                empid: employeeId,
                Month: month,
                YearId: yearId,
                ComId: companyId,
                tokenid: '{{ $tokenId }}'
            },
            success: function(response) {
                if (response.Code === '300' && response.ExpenseList) {
                    displayReport(response.ExpenseList);
                } else {
                    $('#report-content').html('<div class="alert alert-warning">No data found for this month</div>');
                }
            },
            error: function() {
                $('#report-content').html('<div class="alert alert-danger">Error loading report</div>');
            }
        });
    }

    function displayReport(expenses) {
        let html = '<table class="table table-bordered table-striped">';
        html += '<thead><tr>';
        html += '<th>Sl No</th><th>Date</th><th>Description</th><th>Amount</th><th>Status</th>';
        html += '</tr></thead><tbody>';

        let total = 0;
        expenses.forEach((exp, index) => {
            const amount = parseFloat(exp.Amount) || 0;
            total += amount;
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + (exp.BillDate || exp.Date || '-') + '</td>';
            html += '<td>' + (exp.Description || '-') + '</td>';
            html += '<td class="text-right">' + amount.toFixed(2) + '</td>';
            html += '<td>' + getStatusText(exp.Status) + '</td>';
            html += '</tr>';
        });

        html += '<tr class="total-row"><td colspan="3" class="text-right"><strong>Total</strong></td>';
        html += '<td class="text-right"><strong>' + total.toFixed(2) + '/-</strong></td><td></td></tr>';
        html += '</tbody></table>';

        $('#report-content').html(html);
    }

    function getStatusText(status) {
        const statusMap = {
            'P': 'Pending',
            'F': 'Filled',
            'A': 'Approved',
            'D': 'Denied'
        };
        return statusMap[status] || status || '-';
    }

    $(document).ready(function() {
        loadReport();
    });
</script>
@endsection
