@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="card-title mb-0">Open Month Management</h4>
                        <div>
                            <select class="form-select" id="departmentSelect" style="width: 250px;">
                                <option value="0">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->DepartmentId }}" {{ $departmentId == $dept->DepartmentId ? 'selected' : '' }}>
                                        {{ strtoupper($dept->DepartmentName) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($departmentId > 0 && $employees->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" id="openMonthTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="text-center" style="width:50px;font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a" >Sn</th>
                                            <th class="text-center" style="width:80px;font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a" >Code</th>
                                            <th style="font-size: 10px;width:300px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Employee Name</th>
                                            <th class="text-center" style="width:80px;font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a" >Grade</th>
                                            <th class="text-center" style="font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a">Available Months</h>
                                            <th class="text-center" style="width:80px;font-size: 10px;vertical-align:middle;background-color: #dde6ff;color:#1e3a8a; ">Open Month</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($employees as $index => $emp)
                                            <tr>
                                                <td class="text-center">{{ $index + 1 }}</td>
                                                <td class="text-center">{{ $emp->EmpCode }}</td>
                                                <td>{{ $emp->Fname }} {{ $emp->Sname }} {{ $emp->Lname }}</td>
                                                <td class="text-center">{{ $emp->GradeValue }}</td>
                                                <td class="text-center">
                                                    @if(!empty($emp->available_months))
                                                        @foreach($emp->available_months as $m)
                                                            <span class="badge bg-primary-subtle text-primary">{{ $m }}</span>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <select class="form-select form-select-sm open-month-select"
                                                        data-employee-id="{{ $emp->EmployeeID }}"
                                                        style="width:150px; display:inline-block;">
                                                        <option value="">Select Month</option>
                                                        @for($i = 1; $i <= 12; $i++)
                                                            <option value="{{ $i }}">{{ date('F', mktime(0,0,0,$i,1)) }}</option>
                                                        @endfor
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($departmentId > 0)
                            <div class="text-center py-4">
                                <p class="text-muted">No active employees found in this department.</p>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="ri-building-line text-muted" style="font-size: 48px;"></i>
                                <p class="text-muted mt-2">Please select a department to view employees.</p>
                            </div>
                        @endif
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
    // Department change - reload page
    $('#departmentSelect').on('change', function() {
        var deptId = $(this).val();
        window.location.href = '{{ url("open-month") }}?d=' + deptId;
    });

    // Month select - open month for employee
    $(document).on('change', '.open-month-select', function() {
        var month = $(this).val();
        var employeeId = $(this).data('employee-id');
        var selectEl = $(this);

        if (!month || !employeeId) {
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: 'Open this month for the employee?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Open it!',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("open-month.store") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        employee_id: employeeId,
                        month: month
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success', response.message, 'success').then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Warning', response.message, 'warning');
                            selectEl.val('');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to open month. Please try again.', 'error');
                        selectEl.val('');
                    }
                });
            } else {
                selectEl.val('');
            }
        });
    });

    // Initialize DataTable
    if ($('#openMonthTable').length && $('#openMonthTable tbody tr').length > 0) {
        $('#openMonthTable').DataTable({
            ordering: true,
            searching: true,
            paging: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100]
        });
    }
});
</script>
@endpush
