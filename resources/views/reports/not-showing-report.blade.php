@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <!-- Breadcrumb / Header -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-file-search-line me-2 text-primary"></i>Not Showing Report (VVNR)</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item">Reports</li>
                            <li class="breadcrumb-item active">Not Showing Report</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-soft-primary border-bottom d-flex justify-content-between align-items-center py-2">
                <h6 class="card-title mb-0 text-primary">
                    <i class="ri-filter-3-line me-1"></i> Claims with Courier Date but Pending Verification Receipt
                </h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success" id="btnExport">
                        <i class="ri-file-excel-line me-1"></i> Export CSV
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnRefresh">
                        <i class="ri-refresh-line me-1"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Financial Year</label>
                        <select class="form-select form-select-sm" id="filterYear">
                            @foreach($financialYears as $fy)
                                <option value="{{ $fy->YearId }}" {{ $fy->YearId == $yearId ? 'selected' : '' }}>{{ $fy->Year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Month</label>
                        <select class="form-select form-select-sm" id="filterMonth">
                            <option value="">All Months</option>
                            @foreach($monthNames as $mNum => $mName)
                                <option value="{{ $mNum }}">{{ $mName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Department</label>
                        <select class="form-select form-select-sm" id="filterDepartment">
                            <option value="">All Departments</option>
                            @foreach($departments ?? [] as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Employee</label>
                        <select class="form-select form-select-sm" id="filterEmployee">
                            <option value="">All Employees</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->EmployeeID }}">
                                    {{ $emp->EmpCode_New ?: $emp->EmpCode }} - {{ $emp->Fname }} {{ $emp->Sname ?? '' }} {{ $emp->Lname }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Showing claims where documents have been received by DMS team (<span class="fw-bold text-primary">Courier Date</span>), but not yet received by Finance for verification (<span class="fw-bold text-danger">Receive Date</span> is blank).
                    </div>
                    <div>
                        <span class="badge bg-danger rounded-pill px-3 py-2 fs-12" id="pendingCountBadge">0 Pending Claims</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0" id="notShowingTable" style="width:100%">
                        <thead class="table-light">
                            <tr class="text-nowrap" style="background-color: #15648C; color: white;">
                                <th style="width: 40px; background-color: #15648C; color: white;">#</th>
                                <th style="background-color: #15648C; color: white;">Month</th>
                                <th style="background-color: #15648C; color: white;">Employee Code</th>
                                <th style="background-color: #15648C; color: white;">Employee Name</th>
                                <th style="background-color: #15648C; color: white;">Claim Details</th>
                                <th style="background-color: #15648C; color: white;">Courier Date (DMS)</th>
                                <th style="background-color: #15648C; color: white;">Receive Date for Verification</th>
                                <th style="background-color: #15648C; color: white;">Payment Date of Previous Month</th>
                                <th style="text-align: center; width: 100px; background-color: #15648C; color: white;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="notShowingTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div> Loading report data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Update Receive Date -->
<div class="modal fade" id="modalReceiveDate" tabindex="-1" aria-labelledby="modalReceiveDateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title text-white" id="modalReceiveDateLabel"><i class="ri-calendar-check-line me-1"></i> Update Receive Date for Verification</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-2 rounded bg-light border">
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Employee: <strong class="text-dark" id="modalEmpName">—</strong></span>
                        <span>Month: <strong class="text-primary" id="modalMonth">—</strong></span>
                    </div>
                    <div class="mt-1 small text-muted">
                        Courier Date: <span class="badge bg-info text-dark" id="modalCourierDate">—</span>
                    </div>
                </div>
                <form id="formReceiveDate">
                    <input type="hidden" id="modalEmpId">
                    <input type="hidden" id="modalMonthNum">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Receive Date for Verification (Finance) <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="inputReceiveDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Remark</label>
                        <textarea class="form-control" id="inputDocRemark" rows="2" placeholder="Optional remark..." aria-label="Optional remark"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="btnSubmitReceiveDate">
                    <i class="ri-check-line me-1"></i> Save & Confirm Receipt
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let reportRecords = [];

    function loadReport() {
        $('#notShowingTableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading report data...</td></tr>');

        $.ajax({
            url: "{{ route('reports.not.showing.data') }}",
            type: "GET",
            data: {
                year_id: $('#filterYear').val(),
                month: $('#filterMonth').val(),
                department_id: $('#filterDepartment').val(),
                employee_id: $('#filterEmployee').val(),
            },
            success: function(res) {
                if (res.success) {
                    reportRecords = res.data || [];
                    renderTable(reportRecords);
                } else {
                    $('#notShowingTableBody').html('<tr><td colspan="9" class="text-center text-danger py-4">' + (res.message || 'Error loading data') + '</td></tr>');
                    $('#pendingCountBadge').text('0 Pending Claims').removeClass('bg-danger').addClass('bg-secondary');
                }
            },
            error: function(xhr) {
                $('#notShowingTableBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Error loading data. Please try again.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        $('#pendingCountBadge').text(data.length + ' Pending Claims');
        if (data.length > 0) {
            $('#pendingCountBadge').removeClass('bg-secondary').addClass('bg-danger');
        } else {
            $('#pendingCountBadge').removeClass('bg-danger').addClass('bg-secondary');
        }

        if (data.length === 0) {
            $('#notShowingTableBody').html('<tr><td colspan="9" class="text-center text-muted py-5"><i class="ri-checkbox-circle-line text-success fs-24 d-block mb-2"></i>No claims pending verification receipt. All dispatched documents have been received by Finance.</td></tr>');
            return;
        }

        let html = '';
        data.forEach(function(row, idx) {
            html += '<tr>';
            html += '<td class="text-center text-muted small">' + (idx + 1) + '</td>';
            html += '<td><span class="badge bg-primary-subtle text-primary fw-semibold">' + row.month + '</span></td>';
            html += '<td class="fw-semibold">' + row.emp_code + '</td>';
            html += '<td><div>' + row.emp_name + '</div><small class="text-muted">' + row.department + '</small></td>';
            html += '<td><span class="fw-bold text-dark">' + row.claim_details + '</span></td>';
            html += '<td><span class="badge bg-info text-dark fs-11"><i class="ri-truck-line me-1"></i>' + row.courier_date + '</span></td>';
            html += '<td><span class="text-danger fst-italic"><i class="ri-time-line me-1"></i>Awaiting Receipt</span></td>';
            html += '<td><span class="badge bg-light text-dark border">' + row.prev_payment_date + '</span></td>';
            html += '<td class="text-center">';
            html += '<button type="button" class="btn btn-sm btn-outline-success py-1 px-2 btn-open-receive" data-idx="' + idx + '" title="Receive Hardcopy">';
            html += '<i class="ri-checkbox-circle-line me-1"></i> Receive';
            html += '</button>';
            html += '</td>';
            html += '</tr>';
        });

        $('#notShowingTableBody').html(html);
    }

    // Filter change triggers
    $('#filterYear, #filterMonth, #filterDepartment, #filterEmployee').on('change', function() {
        loadReport();
    });

    $('#btnRefresh').on('click', function() {
        loadReport();
    });

    $('#btnExport').on('click', function() {
        let params = $.param({
            year_id: $('#filterYear').val(),
            month: $('#filterMonth').val(),
            department_id: $('#filterDepartment').val(),
            employee_id: $('#filterEmployee').val(),
        });
        window.location.href = "{{ route('reports.not.showing.export') }}?" + params;
    });

    // Open Receive Date modal
    $(document).on('click', '.btn-open-receive', function() {
        let idx = $(this).data('idx');
        let record = reportRecords[idx];
        if (!record) return;

        $('#modalEmpId').val(record.employee_id);
        $('#modalMonthNum').val(record.month_num);
        $('#modalEmpName').text(record.emp_code + ' - ' + record.emp_name);
        $('#modalMonth').text(record.month);
        $('#modalCourierDate').text(record.courier_date);
        $('#inputReceiveDate').val(new Date().toISOString().split('T')[0]);
        $('#inputDocRemark').val(record.remark || '');

        $('#modalReceiveDate').modal('show');
    });

    // Submit Receive Date
    $('#btnSubmitReceiveDate').on('click', function() {
        let empId = $('#modalEmpId').val();
        let month = $('#modalMonthNum').val();
        let receiveDate = $('#inputReceiveDate').val();
        let remark = $('#inputDocRemark').val();

        if (!receiveDate) {
            Swal.fire('Warning', 'Please select Receive Date for Verification', 'warning');
            return;
        }

        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        $.ajax({
            url: "{{ route('reports.not.showing.update-receive-date') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                employee_id: empId,
                month: month,
                receiving_date: receiveDate,
                doc_rmk: remark
            },
            success: function(res) {
                btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i> Save & Confirm Receipt');
                if (res.success) {
                    $('#modalReceiveDate').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    loadReport();
                } else {
                    Swal.fire('Error', res.message || 'Failed to update', 'error');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i> Save & Confirm Receipt');
                Swal.fire('Error', xhr.responseJSON?.message || 'Error updating receive date', 'error');
            }
        });
    });

    // Initial load
    loadReport();
});
</script>
@endpush
