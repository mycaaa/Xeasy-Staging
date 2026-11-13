@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12">
                <div class="card card-height-100">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">Employee Running Kilometers Report</h4>
                    </div>
                    <div class="card-body pb-3 pt-0">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select id="yearFilter" class="form-select">
                                    <option value="">Select Year</option>
                                    <!-- Years will be populated dynamically -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="employeeFilter" class="form-select">
                                    <option value="">Select Employee</option>
                                    <!-- Employees will be populated dynamically -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button id="clearFilters" class="btn btn-secondary">Clear Filters</button>
                            </div>
                        </div>
                        <table id="employeeRunningKmTable"
                            class="table nowrap dt-responsive align-middle table-hover table-bordered"
                            style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee Code</th>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')

<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#employeeRunningKmTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "/getEmployeeRunningKm",
                type: "GET",
                data: function(d) {
                    d.yearid = $('#yearFilter').val();
                    d.employee_id = $('#employeeFilter').val();
                },
                error: function(xhr) {
                    alert('Error loading data: ' + xhr.responseJSON.message);
                }
            },
            columns: [{
                    data: null,
                    render: function(data) {
                        return data.emp_code;
                    }
                },
                {
                    data: null,
                    render: function(data) {
                        return data.employee_name;
                    }
                },
                {
                    data: null,
                    render: function(data) {
                        return data.department.name + ' (' + data.department.code + ')';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return '<button class="btn btn-sm btn-primary view-details" data-emp="' + (data.emp_id ?? '') + '">Details</button>';
                    }
                }
            ],
            pageLength: 10,
            responsive: true,
            order: [
                [0, 'asc']
            ],

        });

        // Populate year filter
        $.ajax({
            url: "/getEmployeeRunningKm",
            type: "GET",
            success: function(response) {
                if (response.success) {
                    var years = new Set();
                    response.data.forEach(function(employee) {
                        employee.travel_logs.years.forEach(function(year) {
                            years.add(year.id);
                        });
                    });
                    years.forEach(function(year) {
                        $('#yearFilter').append('<option value="' + year + '">' + year + '</option>');
                    });
                }
            }
        });

        // Populate employee filter
        $.ajax({
            url: "/getEmployeeRunningKm",
            type: "GET",
            success: function(response) {
                if (response.success) {
                    response.data.forEach(function(employee) {
                        $('#employeeFilter').append(
                            '<option value="' + employee.emp_id + '">' +
                            employee.employee_name + ' (' + employee.emp_code + ')</option>'
                        );
                    });
                }
            }
        });

        // Filter table on select change
        $('#yearFilter, #employeeFilter').on('change', function() {
            table.ajax.reload();
        });

        // Clear filters
        $('#clearFilters').on('click', function() {
            $('#yearFilter').val('');
            $('#employeeFilter').val('');
            table.ajax.reload();
        });

        // Delegate click for Details button
        $('#employeeRunningKmTable tbody').on('click', 'button.view-details', function() {
            var tr = $(this).closest('tr');
            var rowData = table.row(tr).data();
            if (!rowData || !rowData.travel_logs || !Array.isArray(rowData.travel_logs.years)) return;

            var monthsOrder = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];

            function normalizeMonthIndex(mVal) {
                if (mVal == null) return -1;
                if (typeof mVal === 'number') {
                    var calIdx = Math.max(1, Math.min(12, mVal)) - 1;
                    return (calIdx + 9) % 12;
                }
                var name = String(mVal).trim().toLowerCase();
                var map = {
                    'jan': 'January',
                    'january': 'January',
                    'feb': 'February',
                    'february': 'February',
                    'mar': 'March',
                    'march': 'March',
                    'apr': 'April',
                    'april': 'April',
                    'may': 'May',
                    'jun': 'June',
                    'june': 'June',
                    'jul': 'July',
                    'july': 'July',
                    'aug': 'August',
                    'august': 'August',
                    'sep': 'September',
                    'sept': 'September',
                    'september': 'September',
                    'oct': 'October',
                    'october': 'October',
                    'nov': 'November',
                    'november': 'November',
                    'dec': 'December',
                    'december': 'December'
                };
                var canon = map[name];
                if (!canon) return -1;
                return monthsOrder.indexOf(canon);
            }

            var html = '';
            html += '<div class="mb-3"><strong style="font-size:1.2em;">' + (rowData.employee_name ?? '') + ' (' + (rowData.emp_code ?? '') + ')</strong></div>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-bordered table-sm" style="border-collapse:collapse; text-align:center;">';

            // Header
            html += '<thead>';
            html += '<tr style="background-color:#dde6ff; color:#1e3a8a; font-weight:bold;"><th rowspan="2">Year</th>';
            monthsOrder.forEach(function(m) {
                html += '<th colspan="2">' + m + '</th>';
            });
            html += '<th colspan="2">Year Totals</th></tr>';

            html += '<tr style="background-color:#cce5ff; font-weight:bold;">';
            for (var i = 0; i < monthsOrder.length + 1; i++) {
                html += '<th>2W</th><th>4W</th>';
            }
            html += '</tr></thead><tbody>';

            var grandTotals = {
                tw: 0,
                fw: 0
            };
            var perMonthGrand = Array(12).fill(null).map(() => ({
                tw: 0,
                fw: 0
            }));

            (rowData.travel_logs.years || []).forEach(function(year, yIndex) {
                var rowByMonth = Array(12).fill(null).map(() => ({
                    tw: null,
                    fw: null
                }));
                (Array.isArray(year.months) ? year.months : []).forEach(function(m) {
                    var idx = normalizeMonthIndex(m.month);
                    if (idx >= 0) {
                        rowByMonth[idx] = {
                            tw: Number(m['2w_km'] || 0),
                            fw: Number(m['4w_km'] || 0)
                        };
                    }
                });

                var yearTw = 0,
                    yearFw = 0;
                html += '<tr style="background-color:' + (yIndex % 2 === 0 ? '#f9f9f9' : '#ffffff') + ';">';
                html += '<td><b>' + (year.id ?? '') + '</b></td>';
                rowByMonth.forEach(function(cell) {
                    var twVal = (cell && cell.tw != null) ? cell.tw : '—';
                    var fwVal = (cell && cell.fw != null) ? cell.fw : '—';
                    html += '<td>' + twVal + '</td><td>' + fwVal + '</td>';
                    yearTw += (cell && cell.tw != null) ? cell.tw : 0;
                    yearFw += (cell && cell.fw != null) ? cell.fw : 0;
                });
                html += '<td style="background-color:#ffeeba;"><b>' + yearTw + '</b></td>';
                html += '<td style="background-color:#ffeeba;"><b>' + yearFw + '</b></td></tr>';

                grandTotals.tw += yearTw;
                grandTotals.fw += yearFw;
                rowByMonth.forEach((cell, idx) => {
                    perMonthGrand[idx].tw += (cell && cell.tw != null) ? cell.tw : 0;
                    perMonthGrand[idx].fw += (cell && cell.fw != null) ? cell.fw : 0;
                });
            });

            // Grand Total
            html += '<tr style="background-color:#d4edda; font-weight:bold;">';
            html += '<td>Grand Total</td>';
            perMonthGrand.forEach(cell => {
                html += '<td>' + cell.tw + '</td><td>' + cell.fw + '</td>';
            });
            html += '<td>' + grandTotals.tw + '</td><td>' + grandTotals.fw + '</td></tr>';

            html += '</tbody></table></div>';

            $('#employeeKmDetailsContainer').html(html);
            var modal = new bootstrap.Modal(document.getElementById('employeeKmDetailsModal'));
            modal.show();
        });

    });
</script>
@endpush
<!-- Details Modal -->
<div class="modal fade" id="employeeKmDetailsModal" tabindex="-1" aria-labelledby="employeeKmDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeKmDetailsModalLabel">Employee Travel Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="employeeKmDetailsContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>